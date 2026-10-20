<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    exit('KLINIK UMKT CARE membutuhkan PHP 8.1 atau lebih baru.');
}

$GLOBALS['CONFIG'] = require dirname(__DIR__) . '/config.php';
function config(string $kunci)
{
    return $GLOBALS['CONFIG'][$kunci] ?? null;
}

date_default_timezone_set((string) config('timezone'));
error_reporting(E_ALL);
ini_set('display_errors', config('debug') ? '1' : '0');
ini_set('log_errors', '1');

/* ---- BASE_URL otomatis: aplikasi bisa ditaruh di folder mana saja ---- */
$skrip = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$dirSkrip = rtrim(dirname($skrip), '/');
if (in_array(basename($dirSkrip), ['admin', 'dokter', 'pasien'], true)) {
    $dirSkrip = rtrim(dirname($dirSkrip), '/');
}
define('BASE_URL', $dirSkrip === '.' ? '' : $dirSkrip);
unset($skrip, $dirSkrip);

/* ---- Header keamanan & sesi ---- */
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

session_name('umktcare');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => BASE_URL === '' ? '/' : BASE_URL,
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

/* Pesan kesalahan untuk pengguna (bukan bug), contoh: stok habis, data ganda */
class PesanException extends RuntimeException
{
}

function error_page(string $pesan, int $kode = 500): never
{
    http_response_code($kode);
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Terjadi masalah</title><style>body{font-family:system-ui,sans-serif;background:#f3f6f5;color:#14262a;display:grid;place-items:center;min-height:100vh;margin:0}'
        . 'div{max-width:440px;background:#fff;border:1px solid #e0e8e6;border-radius:14px;padding:32px;margin:16px}h1{font-size:20px;margin:0 0 8px}p{margin:0 0 16px;line-height:1.6;color:#5d7276}a{color:#0a6b60;font-weight:600}</style></head>'
        . '<body><div><h1>Terjadi masalah</h1><p>' . htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') . '</p><a href="' . htmlspecialchars(BASE_URL . '/', ENT_QUOTES, 'UTF-8') . '">Kembali ke beranda</a></div></body></html>';
    exit;
}

set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    error_page(config('debug') ? $e->getMessage() : 'Terjadi kesalahan pada server. Coba lagi beberapa saat.');
});

/* ---- Database ---- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        config('db_host'), config('db_port'), config('db_name'));
    try {
        $pdo = new PDO($dsn, (string) config('db_user'), (string) config('db_pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Samakan zona waktu MySQL dengan PHP supaya NOW() dan date() konsisten.
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    } catch (PDOException $e) {
        error_log('Koneksi database gagal: ' . $e->getMessage());
        error_page(config('debug')
            ? 'Koneksi database gagal: ' . $e->getMessage()
            : 'Tidak dapat terhubung ke database. Periksa config.php dan pastikan database.sql sudah diimpor.');
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function fetch_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}
function fetch_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}
function fetch_val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/* Ubah error database umum menjadi pesan yang bisa dipahami pengguna */
function pesan_db(PDOException $e, string $duplikat, string $terpakai = 'Data ini masih dipakai data lain, jadi tidak bisa dihapus.'): string
{
    $kode = (int) ($e->errorInfo[1] ?? 0);
    if ($kode === 1062) {
        return $duplikat;
    }
    if ($kode === 1451) {
        return $terpakai;
    }
    throw $e;
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/domain.php';
require __DIR__ . '/layout.php';
