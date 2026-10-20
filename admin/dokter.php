<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_login('admin');

$error = '';
$editId = (int) ($_GET['edit'] ?? post('id'));
if (is_post()) {
    csrf_check();
    $aksi = post('aksi');
    try {
        if ($aksi === 'hapus') {
            try {
                q('DELETE FROM dokter WHERE id = ?', [(int) post('id')]);
                flash('ok', 'Akun dokter dihapus.');
            } catch (PDOException $e) {
                flash('error', pesan_db($e, '', 'Dokter ini sudah punya riwayat pemeriksaan. Nonaktifkan akunnya agar tidak bisa masuk, tanpa menghapus riwayat.'));
            }
            redirect(url('admin/dokter.php'));
        }
        if ($aksi === 'simpan') {
            $id = (int) post('id');
            $nama = post('nama'); $username = mb_strtolower(post('username')); $poliId = (int) post('poli_id');
            $aktif = post('aktif') === '1' ? 1 : 0; $pw = (string) ($_POST['password'] ?? '');
            wajib($nama, 'Nama dokter', 100);
            validasi_username($username);
            if (!fetch_val('SELECT id FROM poli WHERE id = ?', [$poliId])) throw new PesanException('Pilih poli tempat dokter bertugas.');
            if ($id === 0) {
                validasi_password($pw);
                q('INSERT INTO dokter (poli_id, username, nama, password_hash, aktif) VALUES (?,?,?,?,?)', [$poliId, $username, $nama, hash_password($pw), $aktif]);
                flash('ok', 'Akun dokter ditambahkan.');
            } else {
                q('UPDATE dokter SET poli_id=?, username=?, nama=?, aktif=? WHERE id=?', [$poliId, $username, $nama, $aktif, $id]);
                if ($pw !== '') {
                    validasi_password($pw);
                    q('UPDATE dokter SET password_hash = ? WHERE id = ?', [hash_password($pw), $id]);
                }
                flash('ok', 'Data dokter diperbarui.');
            }
            redirect(url('admin/dokter.php'));
        }
    } catch (PesanException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = pesan_db($e, 'Username tersebut sudah dipakai.');
    }
}

$edit = $editId > 0 ? fetch_one('SELECT * FROM dokter WHERE id = ?', [$editId]) : null;
$v = fn(string $k) => e(is_post() ? post($k) : ($edit[$k] ?? ''));
$poliNilai = is_post() ? post('poli_id') : ($edit['poli_id'] ?? '');
$aktifNilai = is_post() ? post('aktif') === '1' : (!$edit || (int) $edit['aktif'] === 1);
$poliAll = fetch_all('SELECT id, nama FROM poli ORDER BY nama');
$rows = fetch_all(
    "SELECT d.*, p.nama AS poli_nama, (SELECT COUNT(*) FROM kunjungan k WHERE k.dokter_id = d.id AND k.status = 'selesai') AS jml_periksa
     FROM dokter d JOIN poli p ON p.id = d.poli_id ORDER BY p.nama, d.nama");

page_start('Dokter', 'panel', ['peran' => 'admin', 'sub' => 'Kelola akun dokter dan poli tempat mereka bertugas.']);
if ($error) echo alert('error', $error);
?>
<details class="lipat"<?= ($edit || $error) ? ' open' : '' ?>>
    <summary><?= $edit ? 'Ubah dokter' : 'Tambah dokter' ?></summary>
    <div class="isi-lipat">
    <form method="post" novalidate>
        <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="baris-form">
            <div class="field"><label for="nama">Nama dokter</label><input type="text" id="nama" name="nama" maxlength="100" required value="<?= $v('nama') ?>"></div>
            <div class="field"><label for="poli_id">Poli</label><select id="poli_id" name="poli_id" required><option value="">Pilih poli</option>
                <?php foreach ($poliAll as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected($p['id'], $poliNilai) ?>><?= e($p['nama']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" maxlength="30" autocomplete="off" required value="<?= $v('username') ?>"><small>Huruf kecil, angka, titik, garis bawah, atau strip.</small></div>
            <div class="field"><label for="password"><?= $edit ? 'Kata sandi baru (opsional)' : 'Kata sandi' ?></label><input type="password" id="password" name="password" minlength="8" autocomplete="new-password"<?= $edit ? '' : ' required' ?>><small>Minimal 8 karakter.</small></div>
        </div>
        <div class="field"><label class="cek"><input type="checkbox" name="aktif" value="1"<?= checked($aktifNilai) ?>> Akun aktif (boleh masuk)</label></div>
        <div class="aksi"><button class="btn" type="submit">Simpan</button><?php if ($edit): ?><a class="btn btn-sekunder" href="dokter.php">Batal</a><?php endif; ?></div>
    </form>
    </div>
</details>

<section class="kartu">
    <?php if (!$rows): ?><div class="kosong"><strong>Belum ada dokter</strong>Tambahkan dokter agar pasien bisa diperiksa.</div><?php else: ?>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Nama</th><th>Username</th><th>Poli</th><th class="rata-kanan">Pemeriksaan selesai</th><th>Status</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr><td><strong><?= e($r['nama']) ?></strong></td><td><?= e($r['username']) ?></td><td><?= e($r['poli_nama']) ?></td>
                <td class="rata-kanan angka"><?= (int) $r['jml_periksa'] ?></td>
                <td><?= $r['aktif'] ? '<span class="badge badge-hijau">Aktif</span>' : '<span class="badge badge-abu">Nonaktif</span>' ?></td>
                <td class="rata-kanan nowrap"><div class="aksi" style="justify-content:flex-end">
                    <a class="btn btn-kecil btn-sekunder" href="dokter.php?edit=<?= (int) $r['id'] ?>">Ubah</a>
                    <form method="post" class="inline" data-konfirmasi="Hapus akun <?= e($r['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-kecil btn-ghost" type="submit">Hapus</button></form>
                </div></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</section>
<?php page_end();
