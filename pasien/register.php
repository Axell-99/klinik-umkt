<?php
require __DIR__ . '/../includes/bootstrap.php';
redirect_jika_login('pasien', 'pasien/daftar.php');

$error = '';
if (is_post()) {
    csrf_check();
    try {
        $nik    = post('nik');
        $nama   = post('nama');
        $lahir  = post('tanggal_lahir');
        $jk     = post('jenis_kelamin');
        $alamat = post('alamat');
        $hp     = post('no_hp');
        $alergi = post('alergi');
        $pw     = (string) ($_POST['password'] ?? '');
        $pw2    = (string) ($_POST['password2'] ?? '');

        validasi_nik($nik);
        wajib($nama, 'Nama lengkap', 100);
        validasi_tanggal_lahir($lahir);
        if (!in_array($jk, ['L', 'P'], true)) {
            throw new PesanException('Pilih jenis kelamin.');
        }
        wajib($alamat, 'Alamat', 255);
        $hp = validasi_hp($hp);
        if (mb_strlen($alergi) > 255) {
            throw new PesanException('Keterangan alergi maksimal 255 karakter.');
        }
        validasi_password($pw, $pw2);

        q('INSERT INTO pasien (nik, nama, tanggal_lahir, jenis_kelamin, alamat, no_hp, alergi, password_hash) VALUES (?,?,?,?,?,?,?,?)',
            [$nik, $nama, $lahir, $jk, $alamat, $hp, $alergi !== '' ? $alergi : null, hash_password($pw)]);
        login_user('pasien', (int) db()->lastInsertId());
        flash('ok', 'Akun berhasil dibuat. Silakan pilih poli untuk mendaftar.');
        redirect(url('pasien/daftar.php'));
    } catch (PesanException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = pesan_db($e, 'NIK ini sudah terdaftar. Silakan masuk dengan NIK tersebut.');
    }
}

auth_start('Buat akun pasien', 'Buat akun pasien', 'Data ini dipakai dokter untuk mengenali Anda saat pemeriksaan. Anda hanya perlu mengisinya sekali.',
    'Satu akun untuk semua kunjungan Anda.', 'pasien');
if ($error) echo alert('error', $error);
?>
<form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="nik">NIK</label>
        <input type="text" id="nik" name="nik" inputmode="numeric" maxlength="16" data-angka required value="<?= old('nik') ?>">
        <small>16 digit sesuai KTP. NIK dipakai sebagai nama pengguna saat masuk.</small>
    </div>
    <div class="field">
        <label for="nama">Nama lengkap</label>
        <input type="text" id="nama" name="nama" maxlength="100" autocomplete="name" required value="<?= old('nama') ?>">
    </div>
    <div class="baris-form">
        <div class="field">
            <label for="tanggal_lahir">Tanggal lahir</label>
            <input type="date" id="tanggal_lahir" name="tanggal_lahir" max="<?= date('Y-m-d') ?>" required value="<?= old('tanggal_lahir') ?>">
        </div>
        <fieldset class="field">
            <legend>Jenis kelamin</legend>
            <div class="pilihan">
                <label class="cek"><input type="radio" name="jenis_kelamin" value="L" required<?= checked(is_post() && post('jenis_kelamin') === 'L') ?>> Laki-laki</label>
                <label class="cek"><input type="radio" name="jenis_kelamin" value="P"<?= checked(is_post() && post('jenis_kelamin') === 'P') ?>> Perempuan</label>
            </div>
        </fieldset>
    </div>
    <div class="field">
        <label for="alamat">Alamat</label>
        <input type="text" id="alamat" name="alamat" maxlength="255" autocomplete="street-address" required value="<?= old('alamat') ?>">
    </div>
    <div class="field">
        <label for="no_hp">Nomor HP</label>
        <input type="tel" id="no_hp" name="no_hp" inputmode="tel" maxlength="20" autocomplete="tel" required value="<?= old('no_hp') ?>">
    </div>
    <div class="field">
        <label for="alergi">Alergi obat (jika ada)</label>
        <input type="text" id="alergi" name="alergi" maxlength="255" placeholder="Contoh: penisilin, ibuprofen" value="<?= old('alergi') ?>">
        <small>Dokter akan melihat ini sebelum memberikan obat.</small>
    </div>
    <div class="baris-form">
        <div class="field">
            <label for="password">Kata sandi</label>
            <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required>
            <small>Minimal 8 karakter.</small>
        </div>
        <div class="field">
            <label for="password2">Ulangi kata sandi</label>
            <input type="password" id="password2" name="password2" minlength="8" autocomplete="new-password" required>
        </div>
    </div>
    <button class="btn btn-lebar" type="submit">Buat akun</button>
</form>
<p class="tautan">Sudah punya akun? <a href="<?= e(url('pasien/login.php')) ?>">Masuk</a></p>
<?php auth_end();
