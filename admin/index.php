<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_login('admin');
tutup_antrean_lama();

$hariIni = date('Y-m-d');
$besok = date('Y-m-d', strtotime('+1 day'));

$jmlPasien   = (int) fetch_val('SELECT COUNT(*) FROM pasien');
$jmlHariIni  = (int) fetch_val("SELECT COUNT(*) FROM kunjungan WHERE tanggal = ? AND status <> 'batal'", [$hariIni]);
$jmlMenunggu = (int) fetch_val("SELECT COUNT(*) FROM kunjungan WHERE tanggal = ? AND status = 'menunggu'", [$hariIni]);
$pendapatan  = (int) fetch_val("SELECT COALESCE(SUM(total),0) FROM kunjungan WHERE status_bayar = 'lunas' AND dibayar_at >= ? AND dibayar_at < ?", [$hariIni, $besok]);
$belumBayar  = fetch_one("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS jumlah FROM kunjungan WHERE status = 'selesai' AND status_bayar = 'belum'");
$stokTipis   = fetch_all('SELECT nama, stok, satuan FROM obat WHERE aktif = 1 AND stok <= 20 ORDER BY stok, nama LIMIT 6');

$perPoli = fetch_all(
    "SELECT p.nama,
        COALESCE(SUM(k.status = 'menunggu'), 0) AS menunggu,
        COALESCE(SUM(k.status = 'diperiksa'), 0) AS diperiksa,
        COALESCE(SUM(k.status = 'selesai'), 0) AS selesai,
        COALESCE(SUM(k.status = 'batal'), 0) AS batal
     FROM poli p LEFT JOIN kunjungan k ON k.poli_id = p.id AND k.tanggal = ?
     WHERE p.aktif = 1 GROUP BY p.id, p.nama ORDER BY p.nama", [$hariIni]);

$tagihan = fetch_all(
    "SELECT k.id, k.kode_antrean, k.total, k.tanggal, p.nama
     FROM kunjungan k JOIN pasien p ON p.id = k.pasien_id
     WHERE k.status = 'selesai' AND k.status_bayar = 'belum' ORDER BY k.selesai_at LIMIT 8");

page_start('Dashboard', 'panel', ['peran' => 'admin', 'sub' => 'Ringkasan klinik, ' . tgl($hariIni) . '.']);
?>
<div class="statistik">
    <div class="stat"><span>Kunjungan hari ini</span><strong><?= $jmlHariIni ?></strong></div>
    <div class="stat kuning"><span>Pasien menunggu</span><strong><?= $jmlMenunggu ?></strong></div>
    <div class="stat"><span>Pendapatan hari ini</span><strong><?= e(rupiah($pendapatan)) ?></strong></div>
    <div class="stat merah"><span>Tagihan belum dibayar</span><strong><?= (int) $belumBayar['n'] ?></strong><span><?= e(rupiah($belumBayar['jumlah'])) ?></span></div>
    <div class="stat"><span>Total pasien terdaftar</span><strong><?= $jmlPasien ?></strong></div>
</div>

<section class="kartu">
    <h2>Antrean per poli hari ini</h2>
    <?php if (!$perPoli): ?><div class="kosong"><strong>Belum ada poli aktif</strong><a href="poli.php">Tambah poli</a></div><?php else: ?>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Poli</th><th class="rata-kanan">Menunggu</th><th class="rata-kanan">Diperiksa</th><th class="rata-kanan">Selesai</th><th class="rata-kanan">Batal</th></tr></thead>
        <tbody><?php foreach ($perPoli as $r): ?>
            <tr><td><?= e($r['nama']) ?></td><td class="rata-kanan angka"><?= (int) $r['menunggu'] ?></td><td class="rata-kanan angka"><?= (int) $r['diperiksa'] ?></td>
                <td class="rata-kanan angka"><?= (int) $r['selesai'] ?></td><td class="rata-kanan angka"><?= (int) $r['batal'] ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</section>

<section class="kartu">
    <div class="kartu-kepala"><h2>Menunggu pembayaran</h2><a href="kunjungan.php?bayar=belum&status=selesai&tanggal=">Lihat semua</a></div>
    <?php if (!$tagihan): ?>
        <div class="kosong"><strong>Semua tagihan sudah lunas</strong>Pemeriksaan yang selesai akan muncul di sini sampai dibayar.</div>
    <?php else: ?>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Tanggal</th><th>Antrean</th><th>Pasien</th><th class="rata-kanan">Total</th><th></th></tr></thead>
        <tbody><?php foreach ($tagihan as $t): ?>
            <tr><td class="nowrap"><?= e(tgl($t['tanggal'])) ?></td><td class="kode"><?= e($t['kode_antrean']) ?></td><td><?= e($t['nama']) ?></td>
                <td class="rata-kanan angka"><?= e(rupiah($t['total'])) ?></td>
                <td class="rata-kanan"><a class="btn btn-kecil" href="nota.php?id=<?= (int) $t['id'] ?>">Proses pembayaran</a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</section>

<?php if ($stokTipis): ?>
<section class="kartu">
    <div class="kartu-kepala"><h2>Stok obat menipis</h2><a href="obat.php">Kelola obat</a></div>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Obat</th><th class="rata-kanan">Sisa stok</th></tr></thead>
        <tbody><?php foreach ($stokTipis as $o): ?>
            <tr><td><?= e($o['nama']) ?></td><td class="rata-kanan angka"><?= (int) $o['stok'] ?> <?= e($o['satuan']) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</section>
<?php endif; ?>
<?php page_end();
