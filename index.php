<?php
require __DIR__ . '/includes/bootstrap.php';
$poli = daftar_poli_aktif();
$nama = pengaturan('nama_klinik', 'Klinik UMKT Care');

page_start('Beranda');
?>
<div class="beranda">
    <header>
        <?= brand_html(false) ?>
        <a class="btn btn-sekunder btn-kecil" href="<?= e(url('admin/login.php')) ?>">Masuk admin</a>
    </header>

    <h1>Daftar dari rumah, datang saat giliran Anda dekat.</h1>
    <p class="lead">Ambil nomor antrean poli lewat ponsel. Dokter melihat antrean sesuai urutan pendaftaran, jadi Anda tidak perlu menunggu lama di ruang tunggu.</p>

    <div class="portal">
        <a class="kartu-portal utama-portal" href="<?= e(url('pasien/login.php')) ?>">
            <h2>Saya pasien</h2>
            <p>Masuk atau buat akun, lalu daftar ke poli yang Anda tuju.</p>
        </a>
        <a class="kartu-portal" href="<?= e(url('dokter/login.php')) ?>">
            <h2>Saya dokter</h2>
            <p>Lihat antrean pasien, periksa, dan berikan resep obat.</p>
        </a>
        <a class="kartu-portal" href="<?= e(url('pasien/register.php')) ?>">
            <h2>Pasien baru</h2>
            <p>Buat akun dengan NIK Anda. Cukup sekali, berlaku untuk kunjungan berikutnya.</p>
        </a>
    </div>

    <h2 style="margin-bottom:12px">Poli yang tersedia hari ini</h2>
    <?php if (!$poli): ?>
        <p class="redup">Belum ada poli yang aktif.</p>
    <?php else: ?>
        <div class="daftar-poli">
            <?php foreach ($poli as $p): ?>
                <div><strong><?= e($p['nama']) ?></strong>
                    <span><?= (int) $p['menunggu'] ?> pasien menunggu, pemeriksaan <?= e(rupiah($p['tarif_pemeriksaan'])) ?></span></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <p class="redup kecil" style="margin-top:32px"><?= e($nama) ?>, <?= e(pengaturan('alamat_klinik')) ?>. Telepon <?= e(pengaturan('telepon_klinik')) ?>.</p>
</div>
<?php page_end();
