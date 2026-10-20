<?php
require __DIR__ . '/../includes/bootstrap.php';
if (is_post()) {
    csrf_check();
    logout_user('admin');
}
redirect(url('admin/login.php'));
