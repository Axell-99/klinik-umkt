<?php
require __DIR__ . '/../includes/bootstrap.php';
$dokter = require_login('dokter');

$cari = trim((string) ($_GET['q'] ?? ''));
$dari = (string) ($_GET['dari'] ?? '');
$sampai = (string) ($_GET['sampai'] ?? '');

$where = "k.dokter_id = ? AND k.status = 'selesai'";
$par = [$dokter['id']];
if ($cari !== '') {
    $where .= ' AND (p.nama LIKE ? OR p.nik LIKE ? OR k.diagnosis LIKE ?)';
    $like = '%' . addcslashes($cari, '%_\\') . '%';
    array_push($par, $like, $like, $like);
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari)) {
    $where .= ' AND k.tanggal >= ?';
    $par[] = $dari;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
    $where .= ' AND k.tanggal <= ?';
    $par[] = $sampai;
}
$rows = fetch_all(
    "SELECT k.id, k.tanggal, k.kode_antrean, k.diagnosis, p.nama, p.nik,
            (SELECT COUNT(*) FROM resep r WHERE r.kunjungan_id = k.id) AS jml_obat
     FROM kunjungan k JOIN pasien p ON p.id = k.pasien_id
     WHERE $where ORDER BY k.tanggal DESC, k.selesai_at DESC LIMIT 200", $par);

page_start('Riwayat pemeriksaan', 'panel', ['peran' => 'dokter', 'sub' => 'Pemeriksaan yang pernah Anda selesaikan (maksimal 200 terbaru).']);
?>
<section class="kartu">
    <form method="get" class="filter">
        <div class="field"><label for="q">Cari pasien atau diagnosis</label><input type="search" id="q" name="q" value="<?= e($cari) ?>" placeholder="Nama, NIK, atau diagnosis"></div>
        <div class="field"><label for="dari">Dari tanggal</label><input type="date" id="dari" name="dari" value="<?= e($dari) ?>"></div>
        <div class="field"><label for="sampai">Sampai tanggal</label><input type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>"></div>
        <div class="aksi"><button class="btn" type="submit">Terapkan</button><a class="btn btn-sekunder" href="riwayat.php">Atur ulang</a></div>
    </form>
    <?php if (!$rows): ?>
        <div class="kosong"><strong>Belum ada riwayat</strong>Pemeriksaan yang sudah Anda selesaikan akan tampil di sini.</div>
    <?php else: ?>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Tanggal</th><th>Antrean</th><th>Pasien</th><th>Diagnosis</th><th>Obat</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr><td class="nowrap"><?= e(tgl($r['tanggal'])) ?></td><td class="kode"><?= e($r['kode_antrean']) ?></td>
                <td><strong><?= e($r['nama']) ?></strong><br><span class="redup kecil"><?= e($r['nik']) ?></span></td>
                <td><?= e($r['diagnosis']) ?></td><td><?= (int) $r['jml_obat'] ?> jenis</td>
                <td class="rata-kanan"><a class="btn btn-kecil btn-sekunder" href="<?= e(url('dokter/periksa.php?id=' . (int) $r['id'])) ?>">Lihat</a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php page_end();
