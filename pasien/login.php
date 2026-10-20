<?php
require __DIR__ . '/../includes/bootstrap.php';
redirect_jika_login('pasien', 'pasien/daftar.php');

$error = '';
if (is_post()) {
    csrf_check();
    $error = coba_login('pasien', post('nik'), (string) ($_POST['password'] ?? '')) ?? '';
    if ($error === '') {
        redirect(url('pasien/daftar.php'));
    }
}

auth_start('Masuk pasien', 'Masuk sebagai pasien', 'Gunakan NIK dan kata sandi yang Anda buat saat membuat akun.',
    'Daftar dari rumah, datang saat giliran Anda dekat.', 'pasien');
if ($error) echo alert('error', $error);
?>
<form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="nik">NIK</label>
        <input type="text" id="nik" name="nik" inputmode="numeric" maxlength="16" autocomplete="username" data-angka required value="<?= old('nik') ?>">
    </div>
    <div class="field">
        <label for="password">Kata sandi</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <button class="btn btn-lebar" type="submit">Masuk</button>
</form>
<p class="tautan">Belum punya akun? <a href="<?= e(url('pasien/register.php')) ?>">Buat akun pasien</a></p>
<?php auth_end();
