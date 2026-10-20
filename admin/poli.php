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
                q('DELETE FROM poli WHERE id = ?', [(int) post('id')]);
                flash('ok', 'Poli dihapus.');
            } catch (PDOException $e) {
                flash('error', pesan_db($e, '', 'Poli ini sudah punya dokter atau riwayat kunjungan. Nonaktifkan saja agar tidak muncul untuk pasien.'));
            }
            redirect(url('admin/poli.php'));
        }
        if ($aksi === 'simpan') {
            $id = (int) post('id');
            $kode = mb_strtoupper(post('kode')); $nama = post('nama'); $aktif = post('aktif') === '1' ? 1 : 0;
            if (!preg_match('/^[A-Z]{1,5}$/', $kode)) throw new PesanException('Kode poli 1 sampai 5 huruf, contoh: PU.');
            wajib($nama, 'Nama poli', 80);
            $tarif = angka_positif(post('tarif'), 'Tarif pemeriksaan');
            if ($id === 0) {
                q('INSERT INTO poli (kode, nama, tarif_pemeriksaan, aktif) VALUES (?,?,?,?)', [$kode, $nama, $tarif, $aktif]);
                flash('ok', 'Poli ditambahkan.');
            } else {
                q('UPDATE poli SET kode=?, nama=?, tarif_pemeriksaan=?, aktif=? WHERE id=?', [$kode, $nama, $tarif, $aktif, $id]);
                flash('ok', 'Poli diperbarui. Tarif baru berlaku untuk pemeriksaan berikutnya.');
            }
            redirect(url('admin/poli.php'));
        }
    } catch (PesanException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = pesan_db($e, 'Kode atau nama poli sudah dipakai poli lain.');
    }
}

$edit = $editId > 0 ? fetch_one('SELECT * FROM poli WHERE id = ?', [$editId]) : null;
$v = fn(string $k, string $d = '') => e(is_post() ? post($k) : ($edit[$k] ?? $d));
$aktifNilai = is_post() ? post('aktif') === '1' : (!$edit || (int) $edit['aktif'] === 1);
$rows = fetch_all('SELECT p.*, (SELECT COUNT(*) FROM dokter d WHERE d.poli_id = p.id) AS jml_dokter FROM poli p ORDER BY p.nama');

page_start('Poli dan tarif', 'panel', ['peran' => 'admin', 'sub' => 'Atur poli yang dibuka dan tarif pemeriksaan tiap poli.']);
if ($error) echo alert('error', $error);
?>
<details class="lipat"<?= ($edit || $error) ? ' open' : '' ?>>
    <summary><?= $edit ? 'Ubah poli' : 'Tambah poli' ?></summary>
    <div class="isi-lipat">
    <form method="post" novalidate>
        <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="baris-form">
            <div class="field"><label for="kode">Kode</label><input type="text" id="kode" name="kode" maxlength="5" required value="<?= $v('kode') ?>"><small>Dipakai di nomor antrean, contoh PU-001.</small></div>
            <div class="field"><label for="nama">Nama poli</label><input type="text" id="nama" name="nama" maxlength="80" required value="<?= $v('nama') ?>"></div>
            <div class="field"><label for="tarif">Tarif pemeriksaan (Rp)</label><input type="text" id="tarif" name="tarif" inputmode="numeric" data-angka required value="<?= $v('tarif', (string) ($edit['tarif_pemeriksaan'] ?? '50000')) ?>"></div>
        </div>
        <div class="field"><label class="cek"><input type="checkbox" name="aktif" value="1"<?= checked($aktifNilai) ?>> Poli aktif (bisa dipilih pasien)</label></div>
        <div class="aksi"><button class="btn" type="submit">Simpan</button><?php if ($edit): ?><a class="btn btn-sekunder" href="poli.php">Batal</a><?php endif; ?></div>
    </form>
    </div>
</details>

<section class="kartu">
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Kode</th><th>Poli</th><th class="rata-kanan">Tarif pemeriksaan</th><th class="rata-kanan">Dokter</th><th>Status</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr><td class="kode"><?= e($r['kode']) ?></td><td><?= e($r['nama']) ?></td><td class="rata-kanan angka"><?= e(rupiah($r['tarif_pemeriksaan'])) ?></td>
                <td class="rata-kanan angka"><?= (int) $r['jml_dokter'] ?></td>
                <td><?= $r['aktif'] ? '<span class="badge badge-hijau">Aktif</span>' : '<span class="badge badge-abu">Nonaktif</span>' ?></td>
                <td class="rata-kanan nowrap"><div class="aksi" style="justify-content:flex-end">
                    <a class="btn btn-kecil btn-sekunder" href="poli.php?edit=<?= (int) $r['id'] ?>">Ubah</a>
                    <form method="post" class="inline" data-konfirmasi="Hapus <?= e($r['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-kecil btn-ghost" type="submit">Hapus</button></form>
                </div></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</section>
<?php page_end();
