<?php
require __DIR__ . '/../includes/bootstrap.php';
$pasien = require_login('pasien');

$error = '';
if (is_post()) {
    csrf_check();
    try {
        $poliId  = (int) post('poli_id');
        $keluhan = post('keluhan');
        wajib($keluhan, 'Keluhan', 1000);
        if ($poliId <= 0) {
            throw new PesanException('Pilih poli yang dituju.');
        }
        $hasil = buat_kunjungan((int) $pasien['id'], $poliId, $keluhan);
        flash('ok', 'Pendaftaran berhasil. Nomor antrean Anda ' . $hasil['kode_antrean'] . ' di ' . $hasil['poli'] . '.');
        redirect(url('pasien/daftar.php'));
    } catch (PesanException $e) {
        $error = $e->getMessage();
    }
}

$poli = daftar_poli_aktif();

/* Pendaftaran hari ini yang masih berjalan, lengkap dengan jumlah pasien di depan */
$aktif = fetch_all(
    "SELECT k.*, p.nama AS poli_nama,
            (SELECT COUNT(*) FROM kunjungan x WHERE x.poli_id = k.poli_id AND x.tanggal = k.tanggal
               AND x.status = 'menunggu' AND x.no_antrean < k.no_antrean) AS di_depan
     FROM kunjungan k JOIN poli p ON p.id = k.poli_id
     WHERE k.pasien_id = ? AND k.tanggal = ? AND k.status IN ('menunggu','diperiksa')
     ORDER BY k.no_antrean", [$pasien['id'], date('Y-m-d')]);

$riwayat = fetch_all(
    "SELECT k.tanggal, k.kode_antrean, k.status, p.nama AS poli_nama
     FROM kunjungan k JOIN poli p ON p.id = k.poli_id
     WHERE k.pasien_id = ? AND NOT (k.tanggal = ? AND k.status IN ('menunggu','diperiksa'))
     ORDER BY k.tanggal DESC, k.id DESC LIMIT 8", [$pasien['id'], date('Y-m-d')]);

page_start('Pendaftaran', 'pasien');
?>
<div class="judul-halaman"><h1>Pendaftaran pasien</h1>
    <p>Halo, <?= e($pasien['nama']) ?>. Pilih poli dan ceritakan keluhan Anda.</p></div>

<?php foreach ($aktif as $k): ?>
    <section class="tiket" aria-label="Nomor antrean <?= e($k['kode_antrean']) ?>">
        <div class="tiket-info">
            <small><?= e(tgl($k['tanggal'])) ?>, terdaftar pukul <?= e(jam($k['didaftarkan_at'])) ?></small>
            <h2><?= e($k['poli_nama']) ?></h2>
            <?php if ($k['status'] === 'diperiksa'): ?>
                <p>Giliran Anda. Silakan masuk ke ruang periksa.</p>
            <?php elseif ((int) $k['di_depan'] === 0): ?>
                <p>Anda berikutnya. Mohon bersiap di ruang tunggu.</p>
            <?php else: ?>
                <p><?= (int) $k['di_depan'] ?> pasien di depan Anda.</p>
            <?php endif; ?>
            <p class="kecil" style="margin-top:8px">Keluhan: <?= e($k['keluhan']) ?></p>
        </div>
        <div class="tiket-no"><div><strong><?= e($k['kode_antrean']) ?></strong><small>nomor antrean</small></div></div>
    </section>
<?php endforeach; ?>

<section class="kartu">
    <h2>Daftar ke poli</h2>
    <?php if ($error) echo alert('error', $error); ?>
    <?php if (!$poli): ?>
        <div class="kosong"><strong>Belum ada poli yang buka</strong>Silakan hubungi klinik.</div>
    <?php else: ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="poli_id">Poli tujuan</label>
                <select id="poli_id" name="poli_id" required>
                    <option value="">Pilih poli</option>
                    <?php foreach ($poli as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"<?= selected($p['id'], is_post() ? post('poli_id') : '') ?>>
                            <?= e($p['nama']) ?> (<?= (int) $p['menunggu'] ?> menunggu)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="keluhan">Keluhan</label>
                <textarea id="keluhan" name="keluhan" maxlength="1000" required placeholder="Contoh: demam sejak dua hari, batuk berdahak"><?= old('keluhan') ?></textarea>
                <small>Biaya pendaftaran <?= e(rupiah(pengaturan('biaya_pendaftaran', '0'))) ?>. Pembayaran dilakukan di loket setelah pemeriksaan.</small>
            </div>
            <button class="btn" type="submit">Ambil nomor antrean</button>
        </form>
    <?php endif; ?>
</section>

<?php if ($riwayat): ?>
<section class="kartu">
    <h2>Pendaftaran sebelumnya</h2>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Tanggal</th><th>Poli</th><th>Antrean</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($riwayat as $r): ?>
            <tr><td class="nowrap"><?= e(tgl($r['tanggal'])) ?></td><td><?= e($r['poli_nama']) ?></td>
                <td class="kode"><?= e($r['kode_antrean']) ?></td><td><?= badge_status($r['status']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php endif; ?>
<?php page_end();
