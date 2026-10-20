<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_login('admin');

const METODE_BAYAR = ['Tunai', 'QRIS', 'Transfer', 'BPJS'];

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$k = fetch_one(
    "SELECT k.*, p.nama, p.nik, p.tanggal_lahir, p.jenis_kelamin, po.nama AS poli_nama, d.nama AS dokter_nama
     FROM kunjungan k JOIN pasien p ON p.id = k.pasien_id JOIN poli po ON po.id = k.poli_id LEFT JOIN dokter d ON d.id = k.dokter_id
     WHERE k.id = ?", [$id]);
if (!$k) {
    flash('error', 'Data kunjungan tidak ditemukan.');
    redirect(url('admin/kunjungan.php'));
}

$error = '';
if (is_post() && $k['status'] === 'selesai') {
    csrf_check();
    try {
        if (post('aksi') === 'lunas') {
            $metode = post('metode');
            if (!in_array($metode, METODE_BAYAR, true)) {
                throw new PesanException('Pilih metode pembayaran.');
            }
            $uang = null;
            if ($metode === 'Tunai') {
                $uang = angka_positif(post('uang_diterima'), 'Uang diterima', 1000000000);
                if ($uang < (int) $k['total']) {
                    throw new PesanException('Uang yang diterima kurang dari total tagihan ' . rupiah($k['total']) . '.');
                }
            }
            $n = q("UPDATE kunjungan SET status_bayar = 'lunas', metode_bayar = ?, uang_diterima = ?, dibayar_at = NOW()
                    WHERE id = ? AND status = 'selesai' AND status_bayar = 'belum'", [$metode, $uang, $id])->rowCount();
            flash($n ? 'ok' : 'error', $n ? 'Pembayaran dicatat sebagai lunas.' : 'Tagihan ini sudah lunas.');
            redirect(url('admin/nota.php?id=' . $id));
        } elseif (post('aksi') === 'batal_bayar') {
            q("UPDATE kunjungan SET status_bayar = 'belum', metode_bayar = NULL, uang_diterima = NULL, dibayar_at = NULL
               WHERE id = ? AND status_bayar = 'lunas'", [$id]);
            flash('info', 'Status pembayaran dikembalikan menjadi belum bayar.');
            redirect(url('admin/nota.php?id=' . $id));
        }
    } catch (PesanException $e) {
        $error = $e->getMessage();
    }
}

$resep = fetch_all('SELECT * FROM resep WHERE kunjungan_id = ? ORDER BY id', [$id]);
$noNota = 'NOTA-' . date('Ymd', strtotime($k['tanggal'])) . '-' . str_pad((string) $k['id'], 5, '0', STR_PAD_LEFT);

page_start('Nota ' . $k['kode_antrean'], 'panel', ['peran' => 'admin']);
if ($error) echo alert('error', $error);
?>
<div class="aksi tidak-dicetak" style="margin:-8px 0 16px"><a href="kunjungan.php">Kembali ke daftar kunjungan</a></div>

<?php if ($k['status'] !== 'selesai'): ?>
    <section class="kartu"><div class="kosong"><strong>Nota belum tersedia</strong>Nota muncul setelah dokter menyelesaikan pemeriksaan. Status saat ini: <?= badge_status($k['status']) ?></div></section>
<?php else: ?>
<section class="kartu nota">
    <div class="nota-kepala">
        <h2><?= e(pengaturan('nama_klinik')) ?></h2>
        <div class="redup kecil"><?= e(pengaturan('alamat_klinik')) ?><br>Telepon <?= e(pengaturan('telepon_klinik')) ?></div>
    </div>
    <dl class="rincian" style="margin-bottom:18px">
        <dt>Nomor nota</dt><dd><?= e($noNota) ?></dd>
        <dt>Tanggal</dt><dd><?= e(tgl($k['tanggal'])) ?>, antrean <span class="kode"><?= e($k['kode_antrean']) ?></span></dd>
        <dt>Pasien</dt><dd><?= e($k['nama']) ?> (<?= umur($k['tanggal_lahir']) ?> tahun, <?= e(jk_label($k['jenis_kelamin'])) ?>)</dd>
        <dt>Poli dan dokter</dt><dd><?= e($k['poli_nama']) ?>, <?= e($k['dokter_nama'] ?? '-') ?></dd>
        <dt>Diagnosis</dt><dd><?= e($k['diagnosis']) ?></dd>
    </dl>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Rincian</th><th class="rata-kanan">Jumlah</th><th class="rata-kanan">Harga</th><th class="rata-kanan">Subtotal</th></tr></thead>
        <tbody>
            <tr><td>Biaya pendaftaran</td><td class="rata-kanan">1</td><td class="rata-kanan angka"><?= e(rupiah($k['biaya_pendaftaran'])) ?></td><td class="rata-kanan angka"><?= e(rupiah($k['biaya_pendaftaran'])) ?></td></tr>
            <tr><td>Pemeriksaan <?= e($k['poli_nama']) ?></td><td class="rata-kanan">1</td><td class="rata-kanan angka"><?= e(rupiah($k['biaya_pemeriksaan'])) ?></td><td class="rata-kanan angka"><?= e(rupiah($k['biaya_pemeriksaan'])) ?></td></tr>
            <?php foreach ($resep as $r): ?>
            <tr><td><?= e($r['nama_obat']) ?><br><span class="redup kecil"><?= e($r['aturan']) ?></span></td><td class="rata-kanan nowrap"><?= (int) $r['jumlah'] ?> <?= e($r['satuan']) ?></td>
                <td class="rata-kanan angka"><?= e(rupiah($r['harga'])) ?></td><td class="rata-kanan angka"><?= e(rupiah($r['harga'] * $r['jumlah'])) ?></td></tr>
            <?php endforeach; ?>
            <tr class="total-baris"><td colspan="3" class="rata-kanan">Total</td><td class="rata-kanan angka"><?= e(rupiah($k['total'])) ?></td></tr>
            <?php if ($k['status_bayar'] === 'lunas'): ?>
            <tr><td colspan="3" class="rata-kanan">Dibayar dengan <?= e($k['metode_bayar']) ?></td><td class="rata-kanan angka"><?= e(rupiah($k['uang_diterima'] ?? $k['total'])) ?></td></tr>
            <?php if ($k['uang_diterima'] !== null): ?><tr><td colspan="3" class="rata-kanan">Kembalian</td><td class="rata-kanan angka"><?= e(rupiah($k['uang_diterima'] - $k['total'])) ?></td></tr><?php endif; ?>
            <?php endif; ?>
        </tbody>
    </table></div>
    <p style="margin-top:14px"><?= badge_bayar($k['status'], $k['status_bayar']) ?>
        <?php if ($k['status_bayar'] === 'lunas'): ?><span class="redup kecil"> pada <?= e(tgl($k['dibayar_at'], true)) ?></span><?php endif; ?></p>
</section>

<section class="kartu tidak-dicetak nota">
    <?php if ($k['status_bayar'] === 'belum'): ?>
        <h2>Catat pembayaran</h2>
        <form method="post" novalidate>
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="aksi" value="lunas">
            <div class="baris-form">
                <div class="field"><label for="metode-bayar">Metode pembayaran</label>
                    <select id="metode-bayar" name="metode" required><option value="">Pilih metode</option>
                    <?php foreach (METODE_BAYAR as $m): ?><option value="<?= e($m) ?>"<?= selected($m, post('metode')) ?>><?= e($m) ?></option><?php endforeach; ?></select></div>
                <div class="field" id="baris-uang"><label for="uang-diterima">Uang diterima (Rp)</label>
                    <input type="text" id="uang-diterima" name="uang_diterima" inputmode="numeric" data-angka data-total="<?= (int) $k['total'] ?>" value="<?= old('uang_diterima') ?>">
                    <small>Kembalian: <strong id="kembalian" class="angka">-</strong></small></div>
            </div>
            <button class="btn" type="submit">Tandai lunas</button>
        </form>
    <?php else: ?>
        <div class="aksi">
            <button type="button" class="btn" onclick="window.print()">Cetak nota</button>
            <form method="post" data-konfirmasi="Kembalikan status menjadi belum bayar?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="aksi" value="batal_bayar">
                <button class="btn btn-ghost" type="submit">Batalkan pembayaran</button></form>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php page_end();
