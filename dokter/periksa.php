<?php
require __DIR__ . '/../includes/bootstrap.php';
$dokter = require_login('dokter');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$sql = "SELECT k.*, p.nama, p.nik, p.tanggal_lahir, p.jenis_kelamin, p.alamat, p.no_hp, p.alergi, po.nama AS poli_nama, d.nama AS dokter_nama
        FROM kunjungan k JOIN pasien p ON p.id = k.pasien_id JOIN poli po ON po.id = k.poli_id LEFT JOIN dokter d ON d.id = k.dokter_id
        WHERE k.id = ? AND k.poli_id = ?";
$k = fetch_one($sql, [$id, $dokter['poli_id']]);
if (!$k) {
    flash('error', 'Data pemeriksaan tidak ditemukan di poli Anda.');
    redirect(url('dokter/index.php'));
}
$bisaEdit = $k['status'] === 'diperiksa' && (int) $k['dokter_id'] === (int) $dokter['id'];
if (!$bisaEdit && $k['status'] !== 'selesai') {
    flash('error', $k['status'] === 'diperiksa' ? 'Pasien ini sedang diperiksa dokter lain.' : 'Pasien ini belum dipanggil atau pendaftarannya dibatalkan.');
    redirect(url('dokter/index.php'));
}

$error = '';
if (is_post() && $bisaEdit) {
    csrf_check();
    $aksi = post('aksi');
    try {
        if ($aksi === 'kembali') {
            q("UPDATE kunjungan SET status = 'menunggu', dokter_id = NULL, mulai_at = NULL WHERE id = ? AND dokter_id = ? AND status = 'diperiksa'", [$id, $dokter['id']]);
            flash('info', 'Pasien dikembalikan ke antrean dengan nomor semula.');
            redirect(url('dokter/index.php'));
        }

        if ($aksi === 'selesai') {
            $diagnosis = post('diagnosis');
            $tindakan  = post('tindakan');
            $catatan   = post('catatan');
            wajib($diagnosis, 'Diagnosis', 255);
            wajib($tindakan, 'Tindakan', 255);
            if (mb_strlen($catatan) > 2000) {
                throw new PesanException('Catatan maksimal 2000 karakter.');
            }

            $items = [];
            $ids = (array) ($_POST['obat_id'] ?? []);
            $jml = (array) ($_POST['jumlah'] ?? []);
            $atr = (array) ($_POST['aturan'] ?? []);
            foreach ($ids as $i => $oid) {
                $oid = (int) $oid;
                if ($oid <= 0) {
                    continue; // baris kosong diabaikan
                }
                $j = (int) ($jml[$i] ?? 0);
                $a = trim((string) ($atr[$i] ?? ''));
                if ($j < 1 || $j > 999) {
                    throw new PesanException('Jumlah obat harus antara 1 dan 999.');
                }
                if ($a === '' || mb_strlen($a) > 150) {
                    throw new PesanException('Isi aturan pakai (maksimal 150 karakter) untuk setiap obat, misalnya "3 x 1 sesudah makan".');
                }
                if (isset($items[$oid])) {
                    throw new PesanException('Obat yang sama dipilih lebih dari sekali. Gabungkan jadi satu baris.');
                }
                $items[$oid] = ['jumlah' => $j, 'aturan' => $a];
            }
            ksort($items); // urutan kunci tetap mencegah deadlock

            $pdo = db();
            $pdo->beginTransaction();
            try {
                $kunci = fetch_one("SELECT id, poli_id FROM kunjungan WHERE id = ? AND dokter_id = ? AND status = 'diperiksa' FOR UPDATE", [$id, $dokter['id']]);
                if (!$kunci) {
                    throw new PesanException('Pemeriksaan ini sudah tidak aktif.');
                }
                $biayaObat = 0;
                foreach ($items as $oid => $it) {
                    $o = fetch_one('SELECT * FROM obat WHERE id = ? AND aktif = 1 FOR UPDATE', [$oid]);
                    if (!$o) {
                        throw new PesanException('Salah satu obat tidak tersedia lagi.');
                    }
                    if ((int) $o['stok'] < $it['jumlah']) {
                        throw new PesanException('Stok ' . $o['nama'] . ' tidak cukup. Sisa ' . $o['stok'] . ' ' . $o['satuan'] . '.');
                    }
                    q('UPDATE obat SET stok = stok - ? WHERE id = ?', [$it['jumlah'], $oid]);
                    q('INSERT INTO resep (kunjungan_id, obat_id, nama_obat, satuan, harga, jumlah, aturan) VALUES (?,?,?,?,?,?,?)',
                        [$id, $oid, $o['nama'], $o['satuan'], $o['harga'], $it['jumlah'], $it['aturan']]);
                    $biayaObat += (int) $o['harga'] * $it['jumlah'];
                }
                $tarif = (int) fetch_val('SELECT tarif_pemeriksaan FROM poli WHERE id = ?', [$kunci['poli_id']]);
                $total = (int) $k['biaya_pendaftaran'] + $tarif + $biayaObat;
                q("UPDATE kunjungan SET status = 'selesai', diagnosis = ?, tindakan = ?, catatan = ?,
                       biaya_pemeriksaan = ?, biaya_obat = ?, total = ?, selesai_at = NOW() WHERE id = ?",
                    [$diagnosis, $tindakan, $catatan !== '' ? $catatan : null, $tarif, $biayaObat, $total, $id]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            flash('ok', 'Pemeriksaan ' . $k['kode_antrean'] . ' selesai dan tagihan sudah diteruskan ke admin.');
            redirect(url('dokter/index.php'));
        }
    } catch (PesanException $e) {
        $error = $e->getMessage();
    }
}

$obat = fetch_all('SELECT id, nama, satuan, harga, stok FROM obat WHERE aktif = 1 AND stok > 0 ORDER BY nama');
$resepTersimpan = $k['status'] === 'selesai' ? fetch_all('SELECT * FROM resep WHERE kunjungan_id = ? ORDER BY id', [$id]) : [];
$riwayat = fetch_all(
    "SELECT k.tanggal, k.diagnosis, k.tindakan, po.nama AS poli_nama
     FROM kunjungan k JOIN poli po ON po.id = k.poli_id
     WHERE k.pasien_id = ? AND k.id <> ? AND k.status = 'selesai' ORDER BY k.tanggal DESC, k.id DESC LIMIT 5", [$k['pasien_id'], $id]);

/* Baris resep untuk ditampilkan ulang saat validasi gagal */
$baris = [];
if (is_post() && $error !== '') {
    foreach ((array) ($_POST['obat_id'] ?? []) as $i => $oid) {
        $baris[] = ['obat_id' => (int) $oid, 'jumlah' => (string) (((array) $_POST['jumlah'])[$i] ?? '1'), 'aturan' => (string) (((array) ($_POST['aturan'] ?? []))[$i] ?? '')];
    }
}
if (!$baris) {
    $baris[] = ['obat_id' => 0, 'jumlah' => '1', 'aturan' => ''];
}

function html_baris_resep(array $obat, array $b): string
{
    $o = '<option value="">Pilih obat</option>';
    foreach ($obat as $x) {
        $o .= '<option value="' . (int) $x['id'] . '" data-harga="' . (int) $x['harga'] . '" data-stok="' . (int) $x['stok'] . '"'
            . selected($x['id'], $b['obat_id']) . '>' . e($x['nama']) . ' (stok ' . (int) $x['stok'] . ' ' . e($x['satuan']) . ', ' . e(rupiah($x['harga'])) . ')</option>';
    }
    return '<div class="resep-baris">'
        . '<div class="field f-obat"><label>Obat</label><select name="obat_id[]">' . $o . '</select></div>'
        . '<div class="field"><label>Jumlah</label><input type="number" name="jumlah[]" min="1" max="999" value="' . e($b['jumlah']) . '"></div>'
        . '<div class="field f-aturan"><label>Aturan pakai</label><input type="text" name="aturan[]" maxlength="150" placeholder="3 x 1 sesudah makan" value="' . e($b['aturan']) . '"></div>'
        . '<div class="field"><label>&nbsp;</label><button type="button" class="btn btn-ghost btn-kecil hapus-resep" aria-label="Hapus baris obat">Hapus</button></div>'
        . '</div>';
}

page_start('Pemeriksaan ' . $k['kode_antrean'], 'panel', ['peran' => 'dokter']);
?>
<div class="aksi" style="margin:-8px 0 16px"><a href="<?= e(url('dokter/index.php')) ?>">Kembali ke antrean</a></div>
<?php if ($error) echo alert('error', $error); ?>

<section class="kartu">
    <div class="kartu-kepala"><h2><?= e($k['nama']) ?></h2><?= badge_status($k['status']) ?></div>
    <dl class="rincian">
        <dt>Antrean</dt><dd class="kode"><?= e($k['kode_antrean']) ?> di <?= e($k['poli_nama']) ?></dd>
        <dt>Umur dan jenis kelamin</dt><dd><?= umur($k['tanggal_lahir']) ?> tahun, <?= e(jk_label($k['jenis_kelamin'])) ?></dd>
        <dt>NIK</dt><dd><?= e($k['nik']) ?></dd>
        <dt>Kontak</dt><dd><?= e($k['no_hp']) ?>, <?= e($k['alamat']) ?></dd>
        <dt>Keluhan</dt><dd><?= e($k['keluhan']) ?></dd>
        <dt>Alergi obat</dt><dd><?= $k['alergi'] ? '<span class="alergi">' . e($k['alergi']) . '</span>' : '<span class="redup">Tidak dicatat</span>' ?></dd>
    </dl>
</section>

<?php if ($riwayat): ?>
<section class="kartu">
    <h2>Kunjungan sebelumnya</h2>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Tanggal</th><th>Poli</th><th>Diagnosis</th><th>Tindakan</th></tr></thead>
        <tbody><?php foreach ($riwayat as $r): ?>
            <tr><td class="nowrap"><?= e(tgl($r['tanggal'])) ?></td><td><?= e($r['poli_nama']) ?></td><td><?= e($r['diagnosis']) ?></td><td><?= e($r['tindakan']) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</section>
<?php endif; ?>

<?php if ($bisaEdit): ?>
<form method="post" class="kartu" novalidate>
    <h2>Hasil pemeriksaan</h2>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="aksi" value="selesai">
    <div class="field"><label for="diagnosis">Diagnosis</label>
        <input type="text" id="diagnosis" name="diagnosis" maxlength="255" required value="<?= old('diagnosis') ?>"></div>
    <div class="field"><label for="tindakan">Tindakan</label>
        <input type="text" id="tindakan" name="tindakan" maxlength="255" required placeholder="Contoh: konsultasi dan pemeriksaan fisik" value="<?= old('tindakan') ?>"></div>
    <div class="field"><label for="catatan">Catatan dokter (opsional)</label>
        <textarea id="catatan" name="catatan" maxlength="2000"><?= old('catatan') ?></textarea></div>

    <h2 style="margin:24px 0 12px">Resep obat</h2>
    <?php if (!$obat): ?><p class="redup">Belum ada obat dengan stok tersedia. Hubungi admin untuk menambah stok.</p><?php endif; ?>
    <div id="resep-list">
        <?php foreach ($baris as $b) echo html_baris_resep($obat, $b); ?>
    </div>
    <template id="tpl-resep"><?= html_baris_resep($obat, ['obat_id' => 0, 'jumlah' => '1', 'aturan' => '']) ?></template>
    <button type="button" class="btn btn-sekunder btn-kecil" id="tambah-resep">Tambah obat</button>
    <p class="perkiraan">Perkiraan biaya obat: <span id="perkiraan-obat" class="angka">Rp 0</span></p>
    <p class="redup kecil">Kosongkan pilihan obat jika pasien tidak memerlukan resep. Stok berkurang saat Anda menyimpan.</p>

    <div class="aksi" style="margin-top:22px"><button class="btn" type="submit">Simpan dan selesaikan</button></div>
</form>
<form method="post" data-konfirmasi="Kembalikan pasien ini ke antrean? Data yang belum disimpan akan hilang.">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="aksi" value="kembali">
    <button class="btn btn-ghost btn-kecil" type="submit">Pasien belum hadir, kembalikan ke antrean</button>
</form>

<?php else: ?>
<section class="kartu">
    <h2>Hasil pemeriksaan</h2>
    <dl class="rincian">
        <dt>Diagnosis</dt><dd><?= e($k['diagnosis']) ?></dd>
        <dt>Tindakan</dt><dd><?= e($k['tindakan']) ?></dd>
        <dt>Catatan</dt><dd><?= $k['catatan'] ? nl2br(e($k['catatan'])) : '<span class="redup">-</span>' ?></dd>
        <dt>Diperiksa oleh</dt><dd><?= e($k['dokter_nama']) ?>, selesai <?= e(tgl($k['selesai_at'], true)) ?></dd>
    </dl>
    <h3 style="margin:20px 0 8px">Resep obat</h3>
    <?php if (!$resepTersimpan): ?><p class="redup">Tidak ada obat yang diresepkan.</p><?php else: ?>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Obat</th><th>Jumlah</th><th>Aturan pakai</th></tr></thead>
        <tbody><?php foreach ($resepTersimpan as $r): ?>
            <tr><td><?= e($r['nama_obat']) ?></td><td class="nowrap"><?= (int) $r['jumlah'] ?> <?= e($r['satuan']) ?></td><td><?= e($r['aturan']) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</section>
<?php endif; ?>
<?php page_end();
