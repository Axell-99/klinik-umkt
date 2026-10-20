<?php
/*
 * Konfigurasi aplikasi.
 * Nilai bisa diubah langsung di sini, atau lewat environment variable
 * (berguna saat deploy: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS).
 */
return [
    'db_host'  => getenv('DB_HOST') ?: '127.0.0.1',
    'db_port'  => getenv('DB_PORT') ?: '3306',
    'db_name'  => getenv('DB_NAME') ?: 'klinik_umkt',
    'db_user'  => getenv('DB_USER') ?: 'root',
    'db_pass'  => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',

    // Zona waktu klinik: Asia/Jakarta (WIB), Asia/Makassar (WITA), Asia/Jayapura (WIT)
    'timezone' => 'Asia/Makassar',

    // true = tampilkan pesan error asli (hanya untuk development!)
    'debug'    => false,
];
