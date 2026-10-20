<?php
declare(strict_types=1);

function icon(string $nama): string
{
    static $path = [
        'dashboard'  => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'antrean'    => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'riwayat'    => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'kunjungan'  => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'pasien'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'dokter'     => '<circle cx="12" cy="7" r="4"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2M12 14v4M10 16h4"/>',
        'poli'       => '<path d="M3 21h18M5 21V8l7-5 7 5v13"/><path d="M10 21v-6h4v6"/>',
        'obat'       => '<path d="M10.5 20.5a5 5 0 0 1-7-7l9-9a5 5 0 0 1 7 7z"/><path d="M8.5 8.5l7 7"/>',
        'laporan'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'pengaturan' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>',
        'akun'       => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'keluar'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
    ];
    return '<svg class="ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($path[$nama] ?? '') . '</svg>';
}

function nav_menu(string $peran): array
{
    return match ($peran) {
        'admin' => [
            ['index.php',      'Dashboard',     'dashboard'],
            ['kunjungan.php',  'Kunjungan',     'kunjungan'],
            ['pasien.php',     'Pasien',        'pasien'],
            ['dokter.php',     'Dokter',        'dokter'],
            ['poli.php',       'Poli dan tarif', 'poli'],
            ['obat.php',       'Obat',          'obat'],
            ['laporan.php',    'Laporan',       'laporan'],
            ['pengaturan.php', 'Pengaturan',    'pengaturan'],
            ['akun.php',       'Akun admin',    'akun'],
        ],
        'dokter' => [
            ['index.php',   'Antrean pasien',   'antrean'],
            ['riwayat.php', 'Riwayat', 'riwayat'],
        ],
        default => [],
    };
}

function brand_html(bool $gelap = true): string
{
    return '<div class="brand' . ($gelap ? '' : ' brand-terang') . '"><span class="logo" aria-hidden="true"></span>'
        . '<span><strong>' . e(pengaturan('nama_klinik', 'Klinik UMKT Care')) . '</strong><small>Sistem informasi klinik</small></span></div>';
}

function logout_form(string $peran, string $kelas = 'btn btn-ghost btn-kecil'): string
{
    return '<form method="post" action="' . e(url($peran . '/logout.php')) . '" class="inline">' . csrf_field()
        . '<button type="submit" class="' . $kelas . '">' . icon('keluar') . ' Keluar</button></form>';
}

function html_head(string $judul, array $opsi): void
{
    $nama = pengaturan('nama_klinik', 'Klinik UMKT Care');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($judul) . ' | ' . e($nama) . '</title>';
    if (!empty($opsi['refresh'])) {
        echo '<meta http-equiv="refresh" content="' . (int) $opsi['refresh'] . '">';
    }
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">'
        . '<link rel="stylesheet" href="' . e(url('assets/style.css')) . '">'
        . '</head>';
}

/*
 * Layout:
 *  'publik' : halaman biasa tanpa menu (beranda)
 *  'pasien' : topbar sederhana, satu kolom
 *  'panel'  : sidebar + konten (admin & dokter), wajib opsi 'peran'
 */
function page_start(string $judul, string $layout = 'publik', array $opsi = []): void
{
    html_head($judul, $opsi);
    $GLOBALS['layout_aktif'] = $layout;
    $peran = $opsi['peran'] ?? null;

    if ($layout === 'panel' && $peran) {
        $user = current_user($peran);
        $aktif = basename($_SERVER['SCRIPT_NAME']);
        $label = $peran === 'admin' ? 'Admin klinik' : 'Dokter';
        echo '<body class="panel"><a class="lewati" href="#konten">Lewati ke konten</a><div class="app">';
        echo '<aside class="sidebar" id="sidebar">' . brand_html() . '<nav class="menu" aria-label="Menu utama">';
        foreach (nav_menu($peran) as [$file, $nama, $ikon]) {
            $kelas = $file === $aktif || ($aktif === 'periksa.php' && $file === 'index.php') || ($aktif === 'nota.php' && $file === 'kunjungan.php') ? ' aktif' : '';
            echo '<a class="menu-item' . $kelas . '" href="' . e(url($peran . '/' . $file)) . '"' . ($kelas ? ' aria-current="page"' : '') . '>' . icon($ikon) . '<span>' . e($nama) . '</span></a>';
        }
        echo '</nav><div class="sidebar-user"><div><strong>' . e($user['nama'] ?? '') . '</strong><small>' . $label . '</small></div>'
            . logout_form($peran, 'btn btn-ghost-gelap btn-kecil') . '</div></aside>';
        echo '<div class="scrim" id="scrim"></div>';
        echo '<div class="utama"><header class="topbar-mobile"><button type="button" class="btn btn-ghost btn-kecil" id="buka-menu" aria-controls="sidebar" aria-expanded="false">Menu</button>'
            . '<strong>' . e($judul) . '</strong></header>';
        echo '<main class="isi" id="konten"><div class="judul-halaman"><h1>' . e($judul) . '</h1>'
            . (!empty($opsi['sub']) ? '<p>' . e($opsi['sub']) . '</p>' : '') . '</div>' . flash_render();
        return;
    }

    if ($layout === 'pasien') {
        $user = current_user('pasien');
        echo '<body class="pasien"><a class="lewati" href="#konten">Lewati ke konten</a>';
        echo '<header class="topbar">' . brand_html(false) . '<div class="topbar-user"><span>' . e($user['nama'] ?? '') . '</span>' . logout_form('pasien') . '</div></header>';
        echo '<main class="isi isi-sempit" id="konten">' . flash_render();
        return;
    }

    echo '<body class="publik"><main id="konten">' . flash_render();
}

function page_end(): void
{
    $layout = $GLOBALS['layout_aktif'] ?? 'publik';
    echo '</main>';
    if ($layout === 'panel') {
        echo '</div></div>'; // .utama dan .app
    }
    echo '<script src="' . e(url('assets/app.js')) . '" defer></script></body></html>';
}

/* Halaman masuk/daftar dua kolom. Isi form ditulis di antara auth_start() dan auth_end(). */
function auth_start(string $judul, string $heading, string $sub, string $pitch, string $peran): void
{
    html_head($judul, []);
    echo '<body class="auth"><div class="auth-grid">';
    echo '<aside class="auth-sisi">' . brand_html() . '<div class="auth-pitch"><h2>' . e($pitch) . '</h2>'
        . '<p>' . e($sub) . '</p></div><a class="auth-kembali" href="' . e(url('index.php')) . '">Kembali ke beranda</a></aside>';
    echo '<main class="auth-isi" id="konten"><div class="auth-kartu"><h1>' . e($heading) . '</h1>' . flash_render();
}

function auth_end(): void
{
    echo '</div></main></div><script src="' . e(url('assets/app.js')) . '" defer></script></body></html>';
}
