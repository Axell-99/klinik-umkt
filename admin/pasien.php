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
                q('DELETE FROM pasien WHERE id = ?', [(int) post('id')]);
                flash('ok', 'Data pasien dihapus.');
            } catch (PDOException $e) {
                flash('error', pesan_db($e, '', 'Pasien ini punya riwayat kunjungan, jadi tidak bisa dihapus. Hapus dulu kunjungannya jika benar-benar perlu.'));
            }
            redirect(url('admin/pasien.php'));
        }
        if ($aksi === 'simpan') {
            $id = (int) post('id');
            $nik = post('nik'); $nama = post('nama'); $lahir = post('tanggal_lahir'); $jk = post('jenis_kelamin');
            $alamat = post('alamat'); $hp = post('no_hp'); $alergi = post('alergi'); $pw = (string) ($_POST['password'] ?? '');
            validasi_nik($nik);
            wajib($nama, 'Nama lengkap', 100);
            validasi_tanggal_lahir($lahir);
            if (!in_array($jk, ['L', 'P'], true)) throw new PesanException('Pilih jenis kelamin.');
            wajib($alamat, 'Alamat', 255);
            $hp = validasi_hp($hp);
            if (mb_strlen($alergi) > 255) throw new PesanException('Keterangan alergi maksimal 255 karakter.');
            $alergi = $alergi !== '' ? $alergi : null;
            if ($id === 0) {
                validasi_password($pw);
                q('INSERT INTO pasien (nik, nama, tanggal_lahir, jenis_kelamin, alamat, no_hp, alergi, password_hash) VALUES (?,?,?,?,?,?,?,?)',
                    [$nik, $nama, $lahir, $jk, $alamat, $hp, $alergi, hash_password($pw)]);
                flash('ok', 'Pasien ditambahkan.');
            } else {
                q('UPDATE pasien SET nik=?, nama=?, tanggal_lahir=?, jenis_kelamin=?, alamat=?, no_hp=?, alergi=? WHERE id=?',
                    [$nik, $nama, $lahir, $jk, $alamat, $hp, $alergi, $id]);
                if ($pw !== '') {
                    validasi_password($pw);
                    q('UPDATE pasien SET password_hash = ? WHERE id = ?', [hash_password($pw), $id]);
                }
                flash('ok', 'Data pasien diperbarui.');
            }
            redirect(url('admin/pasien.php'));
        }
    } catch (PesanException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = pesan_db($e, 'NIK tersebut sudah dipakai pasien lain.');
    }
}

$edit = $editId > 0 ? fetch_one('SELECT * FROM pasien WHERE id = ?', [$editId]) : null;
$v = fn(string $k) => e(is_post() ? post($k) : ($edit[$k] ?? ''));
$formTerbuka = $edit || ($error && post('aksi') === 'simpan');
$jkNilai = is_post() ? post('jenis_kelamin') : ($edit['jenis_kelamin'] ?? '');

$cari = trim((string) ($_GET['q'] ?? ''));
$where = '1=1'; $par = [];
if ($cari !== '') {
    $where = '(p.nama LIKE ? OR p.nik LIKE ? OR p.no_hp LIKE ?)';
    $like = '%' . addcslashes($cari, '%_\\') . '%';
    $par = [$like, $like, $like];
}
$rows = fetch_all(
    "SELECT p.*, (SELECT COUNT(*) FROM kunjungan k WHERE k.pasien_id = p.id) AS jml_kunjungan
     FROM pasien p WHERE $where ORDER BY p.nama LIMIT 300", $par);

page_start('Pasien', 'panel', ['peran' => 'admin', 'sub' => 'Kelola akun dan data pasien.']);
if ($error) echo alert('error', $error);
?>
<details class="lipat"<?= $formTerbuka ? ' open' : '' ?>>
    <summary><?= $edit ? 'Ubah data pasien' : 'Tambah pasien' ?></summary>
    <div class="isi-lipat">
    <form method="post" novalidate>
        <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="baris-form">
            <div class="field"><label for="nik">NIK</label><input type="text" id="nik" name="nik" maxlength="16" inputmode="numeric" data-angka required value="<?= $v('nik') ?>"></div>
            <div class="field"><label for="nama">Nama lengkap</label><input type="text" id="nama" name="nama" maxlength="100" required value="<?= $v('nama') ?>"></div>
            <div class="field"><label for="tanggal_lahir">Tanggal lahir</label><input type="date" id="tanggal_lahir" name="tanggal_lahir" max="<?= date('Y-m-d') ?>" required value="<?= $v('tanggal_lahir') ?>"></div>
            <div class="field"><label for="jk">Jenis kelamin</label><select id="jk" name="jenis_kelamin" required><option value="">Pilih</option>
                <option value="L"<?= selected('L', $jkNilai) ?>>Laki-laki</option><option value="P"<?= selected('P', $jkNilai) ?>>Perempuan</option></select></div>
            <div class="field"><label for="no_hp">Nomor HP</label><input type="tel" id="no_hp" name="no_hp" maxlength="20" required value="<?= $v('no_hp') ?>"></div>
            <div class="field"><label for="alergi">Alergi obat</label><input type="text" id="alergi" name="alergi" maxlength="255" value="<?= $v('alergi') ?>"></div>
        </div>
        <div class="field"><label for="alamat">Alamat</label><input type="text" id="alamat" name="alamat" maxlength="255" required value="<?= $v('alamat') ?>"></div>
        <div class="field" style="max-width:340px"><label for="password"><?= $edit ? 'Kata sandi baru (opsional)' : 'Kata sandi' ?></label>
            <input type="password" id="password" name="password" minlength="8" autocomplete="new-password"<?= $edit ? '' : ' required' ?>>
            <small><?= $edit ? 'Isi hanya jika pasien lupa kata sandinya. Minimal 8 karakter.' : 'Minimal 8 karakter. Pasien bisa masuk dengan NIK dan kata sandi ini.' ?></small></div>
        <div class="aksi"><button class="btn" type="submit">Simpan</button><?php if ($edit): ?><a class="btn btn-sekunder" href="pasien.php">Batal</a><?php endif; ?></div>
    </form>
    </div>
</details>

<section class="kartu">
    <form method="get" class="filter">
        <div class="field"><label for="q">Cari pasien</label><input type="search" id="q" name="q" value="<?= e($cari) ?>" placeholder="Nama, NIK, atau nomor HP"></div>
        <div class="aksi"><button class="btn" type="submit">Cari</button><a class="btn btn-sekunder" href="pasien.php">Atur ulang</a></div>
    </form>
    <?php if (!$rows): ?>
        <div class="kosong"><strong>Pasien tidak ditemukan</strong>Pasien juga bisa membuat akun sendiri dari halaman pendaftaran.</div>
    <?php else: ?>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>NIK</th><th>Nama</th><th>Umur</th><th>Kontak</th><th>Alergi</th><th class="rata-kanan">Kunjungan</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr><td class="nowrap angka"><?= e($r['nik']) ?></td><td><strong><?= e($r['nama']) ?></strong><br><span class="redup kecil"><?= e(jk_label($r['jenis_kelamin'])) ?></span></td>
                <td class="nowrap"><?= umur($r['tanggal_lahir']) ?> tahun</td><td><?= e($r['no_hp']) ?><br><span class="redup kecil"><?= e($r['alamat']) ?></span></td>
                <td><?= $r['alergi'] ? '<span class="alergi">' . e($r['alergi']) . '</span>' : '<span class="redup">-</span>' ?></td>
                <td class="rata-kanan angka"><?= (int) $r['jml_kunjungan'] ?></td>
                <td class="rata-kanan nowrap"><div class="aksi" style="justify-content:flex-end">
                    <a class="btn btn-kecil btn-sekunder" href="pasien.php?edit=<?= (int) $r['id'] ?>">Ubah</a>
                    <form method="post" class="inline" data-konfirmasi="Hapus pasien <?= e($r['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-kecil btn-ghost" type="submit">Hapus</button></form>
                </div></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php page_end();
