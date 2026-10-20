<?php
declare(strict_types=1);

/*
 * Tiga peran (pasien, dokter, admin) memakai kunci sesi terpisah,
 * jadi masing-masing login sendiri dan tidak saling mewarisi hak akses.
 */
const TABEL_PERAN = ['pasien' => 'pasien', 'dokter' => 'dokter', 'admin' => 'admin'];
const HASH_PALSU  = '$2y$10$tTpk4mL7c0NAE/w023JeSOqKQK39z3Lj9TU9b2w9eRdovmITjslpm';

function login_user(string $peran, int $id): void
{
    session_regenerate_id(true);
    $_SESSION['auth'][$peran] = $id;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function logout_user(string $peran): void
{
    unset($_SESSION['auth'][$peran]);
    session_regenerate_id(true);
}

function current_user(string $peran): ?array
{
    static $cache = [];
    if (array_key_exists($peran, $cache)) {
        return $cache[$peran];
    }
    $id = $_SESSION['auth'][$peran] ?? null;
    if (!$id || !isset(TABEL_PERAN[$peran])) {
        return $cache[$peran] = null;
    }
    $tabel = TABEL_PERAN[$peran];
    $sql = "SELECT * FROM {$tabel} WHERE id = ?" . ($peran === 'dokter' ? ' AND aktif = 1' : '');
    $baris = fetch_one($sql, [(int) $id]);
    if (!$baris) {
        unset($_SESSION['auth'][$peran]);
    }
    return $cache[$peran] = $baris;
}

function require_login(string $peran): array
{
    $user = current_user($peran);
    if (!$user) {
        redirect(url($peran . '/login.php'));
    }
    return $user;
}

function redirect_jika_login(string $peran, string $tujuan): void
{
    if (current_user($peran)) {
        redirect(url($tujuan));
    }
}

function ip_klien(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function login_terkunci(string $peran, string $identitas): bool
{
    $perAkun = (int) fetch_val(
        'SELECT COUNT(*) FROM percobaan_login WHERE peran = ? AND identitas = ? AND waktu > (NOW() - INTERVAL 10 MINUTE)',
        [$peran, $identitas]
    );
    $perIp = (int) fetch_val(
        'SELECT COUNT(*) FROM percobaan_login WHERE ip = ? AND waktu > (NOW() - INTERVAL 10 MINUTE)',
        [ip_klien()]
    );
    return $perAkun >= 5 || $perIp >= 30;
}

function catat_gagal_login(string $peran, string $identitas): void
{
    q('INSERT INTO percobaan_login (peran, identitas, ip) VALUES (?, ?, ?)',
        [$peran, mb_substr($identitas, 0, 50), ip_klien()]);
    q('DELETE FROM percobaan_login WHERE waktu < (NOW() - INTERVAL 1 DAY)');
}

/* Mengembalikan pesan error, atau null jika login berhasil. */
function coba_login(string $peran, string $identitas, string $password): ?string
{
    $identitas = trim($identitas);
    $label = $peran === 'pasien' ? 'NIK' : 'Username';
    if ($identitas === '' || $password === '') {
        return "Isi $label dan kata sandi.";
    }
    if (login_terkunci($peran, $identitas)) {
        return 'Terlalu banyak percobaan gagal. Coba lagi dalam 10 menit.';
    }

    $tabel = TABEL_PERAN[$peran];
    $kolom = $peran === 'pasien' ? 'nik' : 'username';
    $user  = fetch_one("SELECT * FROM {$tabel} WHERE {$kolom} = ?", [$identitas]);

    // password_verify tetap dijalankan walau akun tidak ada, agar waktu respons seragam.
    $cocok = password_verify($password, $user['password_hash'] ?? HASH_PALSU);
    if (!$user || !$cocok) {
        catat_gagal_login($peran, $identitas);
        return "$label atau kata sandi salah.";
    }
    if ($peran === 'dokter' && (int) $user['aktif'] !== 1) {
        return 'Akun ini dinonaktifkan. Hubungi admin klinik.';
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q("UPDATE {$tabel} SET password_hash = ? WHERE id = ?",
            [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    q('DELETE FROM percobaan_login WHERE peran = ? AND identitas = ?', [$peran, $identitas]);
    login_user($peran, (int) $user['id']);
    return null;
}

function hash_password(string $pw): string
{
    return password_hash($pw, PASSWORD_DEFAULT);
}
