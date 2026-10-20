<?php
declare(strict_types=1);

/* Cadangan bila ekstensi mbstring tidak aktif di PHP (kebanyakan hosting sudah punya). */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int
    {
        return preg_match_all('/./us', $s) ?: 0;
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null): string
    {
        preg_match_all('/./us', $s, $m);
        return implode('', array_slice($m[0], $start, $length));
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $s): string
    {
        return strtolower($s);
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $s): string
    {
        return strtoupper($s);
    }
}

const NAMA_BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function e($nilai): string
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $tujuan): never
{
    header('Location: ' . $tujuan);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/* ---- Flash message ---- */
function flash(string $jenis, string $pesan): void
{
    $_SESSION['flash'][] = [$jenis, $pesan];
}
function flash_render(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as [$jenis, $pesan]) {
        $html .= alert($jenis, $pesan);
    }
    unset($_SESSION['flash']);
    return $html;
}
function alert(string $jenis, string $pesan): string
{
    $peran = $jenis === 'error' ? 'alert' : 'status';
    return '<div class="flash flash-' . e($jenis) . '" role="' . $peran . '">' . e($pesan) . '</div>';
}

/* ---- CSRF ---- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['_csrf'] ?? ''))) {
        error_page('Formulir sudah kedaluwarsa. Muat ulang halaman, lalu coba lagi.', 419);
    }
}

/* ---- Input ---- */
function post(string $nama, string $default = ''): string
{
    $v = $_POST[$nama] ?? $default;
    return is_string($v) ? trim($v) : $default;
}
function old(string $nama, string $default = ''): string
{
    return e(is_post() ? post($nama, $default) : $default);
}
function selected($a, $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}
function checked($kondisi): string
{
    return $kondisi ? ' checked' : '';
}

/* ---- Format ---- */
function rupiah($angka): string
{
    return 'Rp ' . number_format((int) $angka, 0, ',', '.');
}
function tgl(?string $tanggal, bool $denganJam = false): string
{
    if (!$tanggal) {
        return '-';
    }
    $t = strtotime($tanggal);
    $s = date('j', $t) . ' ' . NAMA_BULAN[(int) date('n', $t)] . ' ' . date('Y', $t);
    return $denganJam ? $s . ', ' . date('H:i', $t) : $s;
}
function jam(?string $waktu): string
{
    return $waktu ? date('H:i', strtotime($waktu)) : '-';
}
function umur(string $lahir): int
{
    return (new DateTime($lahir))->diff(new DateTime('today'))->y;
}
function jk_label(string $jk): string
{
    return $jk === 'L' ? 'Laki-laki' : 'Perempuan';
}

function badge_status(string $status): string
{
    $peta = [
        'menunggu'  => ['kuning', 'Menunggu'],
        'diperiksa' => ['biru',   'Sedang diperiksa'],
        'selesai'   => ['hijau',  'Selesai'],
        'batal'     => ['abu',    'Batal'],
    ];
    [$kelas, $label] = $peta[$status] ?? ['abu', $status];
    return '<span class="badge badge-' . $kelas . '">' . e($label) . '</span>';
}
function badge_bayar(string $statusKunjungan, string $statusBayar): string
{
    if ($statusKunjungan === 'batal') {
        return '<span class="badge badge-abu">-</span>';
    }
    if ($statusKunjungan !== 'selesai') {
        return '<span class="badge badge-abu">Belum ditagih</span>';
    }
    if ($statusBayar === 'lunas') {
        return '<span class="badge badge-hijau">Lunas</span>';
    }
    return '<span class="badge badge-merah">Belum bayar</span>';
}

/* ---- Validasi ---- */
function validasi_nik(string $nik): void
{
    if (!preg_match('/^\d{16}$/', $nik)) {
        throw new PesanException('NIK harus terdiri dari 16 digit angka.');
    }
}
function validasi_hp(string $hp): string
{
    $bersih = preg_replace('/[\s\-]/', '', $hp);
    if (!preg_match('/^\+?\d{9,15}$/', $bersih)) {
        throw new PesanException('Nomor HP tidak valid. Gunakan 9 sampai 15 digit angka.');
    }
    return $bersih;
}
function validasi_tanggal_lahir(string $tanggal): void
{
    $d = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$d || $d->format('Y-m-d') !== $tanggal) {
        throw new PesanException('Tanggal lahir tidak valid.');
    }
    if ($d > new DateTime('today') || $d < new DateTime('1900-01-01')) {
        throw new PesanException('Tanggal lahir di luar rentang yang wajar.');
    }
}
function validasi_password(string $pw, ?string $ulang = null): void
{
    if (mb_strlen($pw) < 8) {
        throw new PesanException('Kata sandi minimal 8 karakter.');
    }
    if ($ulang !== null && $pw !== $ulang) {
        throw new PesanException('Konfirmasi kata sandi tidak sama.');
    }
}
function validasi_username(string $u): void
{
    if (!preg_match('/^[a-z0-9._-]{4,30}$/', $u)) {
        throw new PesanException('Username 4 sampai 30 karakter: huruf kecil, angka, titik, garis bawah, atau strip.');
    }
}
function wajib(string $nilai, string $label, int $maks = 255): void
{
    if ($nilai === '') {
        throw new PesanException($label . ' wajib diisi.');
    }
    if (mb_strlen($nilai) > $maks) {
        throw new PesanException($label . ' maksimal ' . $maks . ' karakter.');
    }
}
function angka_positif(string $nilai, string $label, int $maks = 100000000): int
{
    if (!preg_match('/^\d+$/', $nilai) || (int) $nilai > $maks) {
        throw new PesanException($label . ' harus berupa angka bulat 0 sampai ' . number_format($maks, 0, ',', '.') . '.');
    }
    return (int) $nilai;
}
