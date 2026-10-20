<?php
require __DIR__ . '/../includes/bootstrap.php';
redirect_jika_login('admin', 'admin/index.php');

$error = '';
if (is_post()) {
    csrf_check();
    $error = coba_login('admin', mb_strtolower(post('username')), (string) ($_POST['password'] ?? '')) ?? '';
    if ($error === '') {
        redirect(url('admin/index.php'));
    }
}

auth_start('Masuk admin', 'Masuk sebagai admin', 'Admin mengelola pasien, dokter, poli, obat, pembayaran, dan laporan klinik.',
    'Seluruh operasional klinik dalam satu tempat.', 'admin');
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
