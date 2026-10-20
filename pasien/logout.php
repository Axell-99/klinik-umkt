<?php
require __DIR__ . '/../includes/bootstrap.php';
if (is_post()) {
    csrf_check();
    logout_user('pasien');
}
redirect(url('pasien/login.php'));
