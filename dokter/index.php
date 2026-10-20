<?php
require __DIR__ . '/../includes/bootstrap.php';
$dokter = require_login('dokter');
tutup_antrean_lama();

$poliId  = (int) $dokter['poli_id'];
$hariIni = date('Y-m-d');

/* Mulai periksa: pasien diambil secara atomik, jadi dua dokter di poli yang sama
   tidak mungkin memegang pasien yang sama. */
if (is_post()) {
    csrf_check();
    if (post('aksi') === 'mulai') {
        $id = (int) post('id');
        $sedang = (int) fetch_val("SELECT COUNT(*) FROM kunjungan WHERE dokter_id = ? AND tanggal = ? AND status = 'diperiksa'", [$dokter['id'], $hariIni]);
        if ($sedang > 0) {
            flash('error', 'Selesaikan dulu pemeriksaan yang sedang berjalan sebelum memanggil pasien berikutnya.');
            redirect(url('dokter/index.php'));
        }
        $stmt = q("UPDATE kunjungan SET status = 'diperiksa', dokter_id = ?, mulai_at = NOW()
                   WHERE id = ? AND poli_id = ? AND tanggal = ? AND status = 'menunggu'",
            [$dokter['id'], $id, $poliId, $hariIni]);
        if ($stmt->rowCount() === 1) {
            redirect(url('dokter/periksa.php?id=' . $id));
        }
        flash('error', 'Pasien tersebut sudah dipanggil dokter lain atau pendaftarannya dibatalkan.');
    }
    redirect(url('dokter/index.php'));
}

$poli = fetch_one('SELECT nama FROM poli WHERE id = ?', [$poliId]);

$menunggu = fetch_all(
    "SELECT k.id, k.kode_antrean, k.keluhan, k.didaftarkan_at, p.nama, p.tanggal_lahir, p.jenis_kelamin, p.alergi
     FROM kunjungan k JOIN pasien p ON p.id = k.pasien_id
     WHERE k.poli_id = ? AND k.tanggal = ? AND k.status = 'menunggu'
     ORDER BY k.no_antrean ASC", [$poliId, $hariIni]);

$berjalan = fetch_all(
    "SELECT k.id, k.kode_antrean, k.dokter_id, d.nama AS dokter_nama, p.nama, k.mulai_at
     FROM kunjungan k JOIN pasien p ON p.id = k.pasien_id LEFT JOIN dokter d ON d.id = k.dokter_id
     WHERE k.poli_id = ? AND k.tanggal = ? AND k.status = 'diperiksa' ORDER BY k.mulai_at", [$poliId, $hariIni]);

$selesai = fetch_all(
    "SELECT k.id, k.kode_antrean, k.diagnosis, k.selesai_at, p.nama
     FROM kunjungan k JOIN pasien p ON p.id = k.pasien_id
     WHERE k.poli_id = ? AND k.tanggal = ? AND k.status = 'selesai' ORDER BY k.selesai_at DESC", [$poliId, $hariIni]);

$sayaSedang = null;
foreach ($berjalan as $b) {
    if ((int) $b['dokter_id'] === (int) $dokter['id']) {
        $sayaSedang = $b;
    }
}
$berikutnya = $menunggu[0] ?? null;

page_start('Antrean pasien', 'panel', [
    'peran'   => 'dokter',
    'sub'     => $poli['nama'] . ', ' . tgl($hariIni) . '. Halaman ini diperbarui otomatis setiap 30 detik.',
    'refresh' => 30,
]);
?>
<div class="statistik">
    <div class="stat kuning"><span>Menunggu</span><strong><?= count($menunggu) ?></strong></div>
    <div class="stat"><span>Sedang diperiksa</span><strong><?= count($berjalan) ?></strong></div>
    <div class="stat"><span>Selesai hari ini</span><strong><?= count($selesai) ?></strong></div>
</div>

<?php if ($sayaSedang): ?>
    <div class="flash flash-info">Anda sedang memeriksa <strong><?= e($sayaSedang['nama']) ?></strong> (<?= e($sayaSedang['kode_antrean']) ?>).
        <a href="<?= e(url('dokter/periksa.php?id=' . (int) $sayaSedang['id'])) ?>">Lanjutkan pemeriksaan</a></div>
<?php elseif ($berikutnya): ?>
    <section class="berikutnya" aria-label="Pasien berikutnya">
        <div class="no"><?= e($berikutnya['kode_antrean']) ?></div>
        <div>
            <div class="label-kecil">Pasien berikutnya, mendaftar pukul <?= e(jam($berikutnya['didaftarkan_at'])) ?></div>
            <h2><?= e($berikutnya['nama']) ?>, <?= umur($berikutnya['tanggal_lahir']) ?> tahun</h2>
            <p>Keluhan: <?= e($berikutnya['keluhan']) ?></p>
            <?php if ($berikutnya['alergi']): ?><span class="alergi">Alergi: <?= e($berikutnya['alergi']) ?></span><?php endif; ?>
        </div>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="mulai"><input type="hidden" name="id" value="<?= (int) $berikutnya['id'] ?>">
            <button class="btn btn-sekunder" type="submit">Panggil dan periksa</button></form>
    </section>
<?php endif; ?>

<section class="kartu">
    <div class="kartu-kepala"><h2>Antrean menunggu</h2><span class="redup kecil">Urut dari yang mendaftar paling awal</span></div>
    <?php if (!$menunggu): ?>
        <div class="kosong"><strong>Tidak ada pasien yang menunggu</strong>Pasien yang mendaftar akan muncul di sini.</div>
    <?php else: ?>
        <div class="tabel-bungkus"><table>
            <thead><tr><th>Antrean</th><th>Pasien</th><th>Keluhan</th><th>Mendaftar</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($menunggu as $i => $k): ?>
                <tr>
                    <td class="kode"><?= e($k['kode_antrean']) ?></td>
                    <td><strong><?= e($k['nama']) ?></strong><br><span class="redup kecil"><?= umur($k['tanggal_lahir']) ?> tahun, <?= e(jk_label($k['jenis_kelamin'])) ?></span>
                        <?php if ($k['alergi']): ?><br><span class="alergi">Alergi: <?= e($k['alergi']) ?></span><?php endif; ?></td>
                    <td><?= e($k['keluhan']) ?></td>
                    <td class="nowrap"><?= e(jam($k['didaftarkan_at'])) ?></td>
                    <td class="rata-kanan">
                        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="mulai"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                            <button class="btn btn-kecil <?= $i === 0 ? '' : 'btn-sekunder' ?>" type="submit"<?= $sayaSedang ? ' disabled' : '' ?>>Periksa</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php if ($berjalan): ?>
<section class="kartu">
    <h2>Sedang diperiksa</h2>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Antrean</th><th>Pasien</th><th>Dokter</th><th>Mulai</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($berjalan as $b): ?>
            <tr><td class="kode"><?= e($b['kode_antrean']) ?></td><td><?= e($b['nama']) ?></td>
                <td><?= e($b['dokter_nama']) ?></td><td><?= e(jam($b['mulai_at'])) ?></td>
                <td class="rata-kanan"><?php if ((int) $b['dokter_id'] === (int) $dokter['id']): ?>
                    <a class="btn btn-kecil" href="<?= e(url('dokter/periksa.php?id=' . (int) $b['id'])) ?>">Lanjutkan</a><?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php endif; ?>

<?php if ($selesai): ?>
<section class="kartu">
    <h2>Selesai hari ini</h2>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Antrean</th><th>Pasien</th><th>Diagnosis</th><th>Selesai</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($selesai as $s): ?>
            <tr><td class="kode"><?= e($s['kode_antrean']) ?></td><td><?= e($s['nama']) ?></td><td><?= e($s['diagnosis']) ?></td>
                <td><?= e(jam($s['selesai_at'])) ?></td>
                <td class="rata-kanan"><a class="btn btn-kecil btn-sekunder" href="<?= e(url('dokter/periksa.php?id=' . (int) $s['id'])) ?>">Lihat</a></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php endif; ?>
<?php page_end();
