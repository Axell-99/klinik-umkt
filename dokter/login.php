<?php
require __DIR__ . '/../includes/bootstrap.php';
redirect_jika_login('dokter', 'dokter/index.php');

$error = '';
if (is_post()) {
    csrf_check();
    $error = coba_login('dokter', mb_strtolower(post('username')), (string) ($_POST['password'] ?? '')) ?? '';
    if ($error === '') {
        redirect(url('dokter/index.php'));
    }
}

auth_start('Masuk dokter', 'Masuk sebagai dokter', 'Akun dokter dibuat oleh admin klinik. Hubungi admin jika Anda belum memilikinya.',
    'Pasien terurut sesuai siapa yang mendaftar lebih dulu.', 'dokter');
if ($error) echo alert('error', $error);
?>
<form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" autocomplete="username" required value="<?= old('username') ?>">
    </div>
    <div class="field">
        <label for="password">Kata sandi</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <button class="btn btn-lebar" type="submit">Masuk</button>
</form>
<?php auth_end();
