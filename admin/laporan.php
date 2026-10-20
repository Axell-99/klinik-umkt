<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_login('admin');

$tanggalValid = fn(string $t) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) && checkdate((int) substr($t, 5, 2), (int) substr($t, 8, 2), (int) substr($t, 0, 4));
$dari   = (string) ($_GET['dari'] ?? date('Y-m-01'));
$sampai = (string) ($_GET['sampai'] ?? date('Y-m-d'));
if (!$tanggalValid($dari)) $dari = date('Y-m-01');
if (!$tanggalValid($sampai)) $sampai = date('Y-m-d');
if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];
$poliF = (int) ($_GET['poli'] ?? 0);

$where = "k.status = 'selesai' AND k.tanggal BETWEEN ? AND ?";
$par = [$dari, $sampai];
if ($poliF > 0) { $where .= ' AND k.poli_id = ?'; $par[] = $poliF; }

$rows = fetch_all(
    "SELECT k.tanggal, po.nama AS poli, COUNT(*) AS jml,
            SUM(k.biaya_pendaftaran) AS pendaftaran, SUM(k.biaya_pemeriksaan) AS pemeriksaan, SUM(k.biaya_obat) AS obat,
            SUM(k.total) AS total, SUM(CASE WHEN k.status_bayar = 'lunas' THEN k.total ELSE 0 END) AS lunas
     FROM kunjungan k JOIN poli po ON po.id = k.poli_id
     WHERE $where GROUP BY k.tanggal, po.id, po.nama ORDER BY k.tanggal DESC, po.nama", $par);

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-' . $dari . '-sd-' . $sampai . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
    fputcsv($out, ['Tanggal', 'Poli', 'Jumlah kunjungan', 'Pendaftaran', 'Pemeriksaan', 'Obat', 'Total tagihan', 'Sudah dibayar']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['tanggal'], $r['poli'], $r['jml'], $r['pendaftaran'], $r['pemeriksaan'], $r['obat'], $r['total'], $r['lunas']]);
    }
    fclose($out);
    exit;
}

$sum = ['jml' => 0, 'total' => 0, 'lunas' => 0, 'obat' => 0];
foreach ($rows as $r) {
    $sum['jml'] += (int) $r['jml']; $sum['total'] += (int) $r['total']; $sum['lunas'] += (int) $r['lunas']; $sum['obat'] += (int) $r['obat'];
}
$perPoli = fetch_all(
    "SELECT po.nama AS poli, COUNT(*) AS jml, SUM(k.total) AS total FROM kunjungan k JOIN poli po ON po.id = k.poli_id
     WHERE $where GROUP BY po.id, po.nama ORDER BY total DESC", $par);
$perMetode = fetch_all(
    "SELECT k.metode_bayar AS metode, COUNT(*) AS jml, SUM(k.total) AS total FROM kunjungan k
     WHERE $where AND k.status_bayar = 'lunas' GROUP BY k.metode_bayar ORDER BY total DESC", $par);
$obatTop = fetch_all(
    "SELECT r.nama_obat, SUM(r.jumlah) AS jml, SUM(r.jumlah * r.harga) AS nilai FROM resep r JOIN kunjungan k ON k.id = r.kunjungan_id
     WHERE $where GROUP BY r.nama_obat ORDER BY jml DESC LIMIT 5", $par);
$poliAll = fetch_all('SELECT id, nama FROM poli ORDER BY nama');

page_start('Laporan', 'panel', ['peran' => 'admin', 'sub' => 'Rekap kunjungan yang sudah selesai diperiksa dan pendapatannya.']);
?>
<section class="kartu">
    <form method="get" class="filter">
        <div class="field"><label for="dari">Dari tanggal</label><input type="date" id="dari" name="dari" value="<?= e($dari) ?>"></div>
        <div class="field"><label for="sampai">Sampai tanggal</label><input type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>"></div>
        <div class="field"><label for="poli">Poli</label><select id="poli" name="poli"><option value="">Semua poli</option>
            <?php foreach ($poliAll as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected($p['id'], $poliF) ?>><?= e($p['nama']) ?></option><?php endforeach; ?></select></div>
        <div class="aksi"><button class="btn" type="submit">Tampilkan</button>
            <a class="btn btn-sekunder" href="laporan.php?<?= e(http_build_query(['dari' => $dari, 'sampai' => $sampai, 'poli' => $poliF, 'export' => 'csv'])) ?>">Unduh CSV</a></div>
    </form>
</section>

<div class="statistik">
    <div class="stat"><span>Kunjungan selesai</span><strong><?= $sum['jml'] ?></strong></div>
    <div class="stat"><span>Total tagihan</span><strong><?= e(rupiah($sum['total'])) ?></strong></div>
    <div class="stat"><span>Sudah dibayar</span><strong><?= e(rupiah($sum['lunas'])) ?></strong></div>
    <div class="stat merah"><span>Belum dibayar</span><strong><?= e(rupiah($sum['total'] - $sum['lunas'])) ?></strong></div>
    <div class="stat"><span>Penjualan obat</span><strong><?= e(rupiah($sum['obat'])) ?></strong></div>
</div>

<?php if (!$rows): ?>
    <section class="kartu"><div class="kosong"><strong>Tidak ada data pada periode ini</strong>Ubah rentang tanggal atau pilih poli lain.</div></section>
<?php else: ?>
<section class="kartu">
    <h2>Rincian per hari dan poli</h2>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Tanggal</th><th>Poli</th><th class="rata-kanan">Kunjungan</th><th class="rata-kanan">Pendaftaran</th><th class="rata-kanan">Pemeriksaan</th><th class="rata-kanan">Obat</th><th class="rata-kanan">Total</th><th class="rata-kanan">Dibayar</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr><td class="nowrap"><?= e(tgl($r['tanggal'])) ?></td><td><?= e($r['poli']) ?></td><td class="rata-kanan angka"><?= (int) $r['jml'] ?></td>
                <td class="rata-kanan angka nowrap"><?= e(rupiah($r['pendaftaran'])) ?></td><td class="rata-kanan angka nowrap"><?= e(rupiah($r['pemeriksaan'])) ?></td>
                <td class="rata-kanan angka nowrap"><?= e(rupiah($r['obat'])) ?></td><td class="rata-kanan angka nowrap"><strong><?= e(rupiah($r['total'])) ?></strong></td>
                <td class="rata-kanan angka nowrap"><?= e(rupiah($r['lunas'])) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</section>

<section class="kartu">
    <h2>Ringkasan per poli</h2>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Poli</th><th class="rata-kanan">Kunjungan</th><th class="rata-kanan">Total tagihan</th></tr></thead>
        <tbody><?php foreach ($perPoli as $r): ?><tr><td><?= e($r['poli']) ?></td><td class="rata-kanan angka"><?= (int) $r['jml'] ?></td><td class="rata-kanan angka"><?= e(rupiah($r['total'])) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
</section>

<?php if ($perMetode): ?>
<section class="kartu">
    <h2>Pembayaran berdasarkan metode</h2>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Metode</th><th class="rata-kanan">Transaksi</th><th class="rata-kanan">Jumlah</th></tr></thead>
        <tbody><?php foreach ($perMetode as $r): ?><tr><td><?= e($r['metode']) ?></td><td class="rata-kanan angka"><?= (int) $r['jml'] ?></td><td class="rata-kanan angka"><?= e(rupiah($r['total'])) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
</section>
<?php endif; ?>

<?php if ($obatTop): ?>
<section class="kartu">
    <h2>Lima obat paling banyak diresepkan</h2>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Obat</th><th class="rata-kanan">Jumlah keluar</th><th class="rata-kanan">Nilai</th></tr></thead>
        <tbody><?php foreach ($obatTop as $r): ?><tr><td><?= e($r['nama_obat']) ?></td><td class="rata-kanan angka"><?= (int) $r['jml'] ?></td><td class="rata-kanan angka"><?= e(rupiah($r['nilai'])) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
</section>
<?php endif; ?>
<?php endif; ?>
<?php page_end();
