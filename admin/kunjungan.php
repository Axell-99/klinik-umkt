<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_login('admin');
tutup_antrean_lama();

$error = '';
if (is_post()) {
    csrf_check();
    $aksi = post('aksi');
    try {
        if ($aksi === 'tambah') {
            $pasienId = (int) post('pasien_id');
            $poliId   = (int) post('poli_id');
            $keluhan  = post('keluhan');
            if ($pasienId <= 0 || !fetch_val('SELECT id FROM pasien WHERE id = ?', [$pasienId])) {
                throw new PesanException('Pilih pasien terlebih dahulu.');
            }
            if ($poliId <= 0) {
                throw new PesanException('Pilih poli tujuan.');
            }
            wajib($keluhan, 'Keluhan', 1000);
            $h = buat_kunjungan($pasienId, $poliId, $keluhan);
            flash('ok', 'Pendaftaran dibuat dengan nomor antrean ' . $h['kode_antrean'] . '.');
            redirect(url('admin/kunjungan.php'));
        } elseif ($aksi === 'batal') {
            $n = q("UPDATE kunjungan SET status = 'batal', dokter_id = NULL WHERE id = ? AND status IN ('menunggu','diperiksa')", [(int) post('id')])->rowCount();
            flash($n ? 'ok' : 'error', $n ? 'Pendaftaran dibatalkan.' : 'Hanya pendaftaran yang belum selesai yang bisa dibatalkan.');
            redirect($_SERVER['REQUEST_URI']);
        } elseif ($aksi === 'hapus') {
            hapus_kunjungan((int) post('id'));
            flash('ok', 'Data kunjungan dihapus. Stok obat yang terpakai sudah dikembalikan.');
            redirect($_SERVER['REQUEST_URI']);
        }
    } catch (PesanException $e) {
        $error = $e->getMessage();
    }
}

$tanggal = array_key_exists('tanggal', $_GET) ? trim((string) $_GET['tanggal']) : date('Y-m-d');
$poliF   = (int) ($_GET['poli'] ?? 0);
$statusF = (string) ($_GET['status'] ?? '');
$bayarF  = (string) ($_GET['bayar'] ?? '');
$cari    = trim((string) ($_GET['q'] ?? ''));

$where = ['1=1'];
$par = [];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) { $where[] = 'k.tanggal = ?'; $par[] = $tanggal; } else { $tanggal = ''; }
if ($poliF > 0) { $where[] = 'k.poli_id = ?'; $par[] = $poliF; }
if (in_array($statusF, ['menunggu', 'diperiksa', 'selesai', 'batal'], true)) { $where[] = 'k.status = ?'; $par[] = $statusF; } else { $statusF = ''; }
if (in_array($bayarF, ['belum', 'lunas'], true)) { $where[] = "k.status = 'selesai' AND k.status_bayar = ?"; $par[] = $bayarF; } else { $bayarF = ''; }
if ($cari !== '') {
    $where[] = '(p.nama LIKE ? OR p.nik LIKE ? OR k.kode_antrean LIKE ?)';
    $like = '%' . addcslashes($cari, '%_\\') . '%';
    array_push($par, $like, $like, $like);
}
$rows = fetch_all(
    "SELECT k.id, k.tanggal, k.kode_antrean, k.status, k.status_bayar, k.total, k.didaftarkan_at, p.nama AS pasien, po.nama AS poli, d.nama AS dokter
     FROM kunjungan k JOIN pasien p ON p.id = k.pasien_id JOIN poli po ON po.id = k.poli_id LEFT JOIN dokter d ON d.id = k.dokter_id
     WHERE " . implode(' AND ', $where) . ' ORDER BY k.tanggal DESC, k.poli_id, k.no_antrean LIMIT 300', $par);

$poliAll = fetch_all('SELECT id, nama FROM poli ORDER BY nama');
$poliAktif = fetch_all('SELECT id, nama FROM poli WHERE aktif = 1 ORDER BY nama');
$pasienAll = fetch_all('SELECT id, nama, nik FROM pasien ORDER BY nama LIMIT 2000');

page_start('Kunjungan', 'panel', ['peran' => 'admin', 'sub' => 'Pantau antrean, daftarkan pasien langsung di loket, dan proses pembayaran.']);
if ($error) echo alert('error', $error);
?>
<details class="lipat"<?= $error && post('aksi') === 'tambah' ? ' open' : '' ?>>
    <summary>Daftarkan pasien di loket</summary>
    <div class="isi-lipat">
        <?php if (!$pasienAll): ?>
            <p class="redup">Belum ada pasien. <a href="pasien.php">Tambah pasien</a> terlebih dahulu.</p>
        <?php else: ?>
        <form method="post" novalidate>
            <?= csrf_field() ?><input type="hidden" name="aksi" value="tambah">
            <div class="baris-form">
                <div class="field"><label for="t-pasien">Pasien</label>
                    <select id="t-pasien" name="pasien_id" required><option value="">Pilih pasien</option>
                    <?php foreach ($pasienAll as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected($p['id'], post('pasien_id')) ?>><?= e($p['nama']) ?> (<?= e($p['nik']) ?>)</option><?php endforeach; ?></select></div>
                <div class="field"><label for="t-poli">Poli</label>
                    <select id="t-poli" name="poli_id" required><option value="">Pilih poli</option>
                    <?php foreach ($poliAktif as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected($p['id'], post('poli_id')) ?>><?= e($p['nama']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="field"><label for="t-keluhan">Keluhan</label><input type="text" id="t-keluhan" name="keluhan" maxlength="1000" required value="<?= old('keluhan') ?>"></div>
            <button class="btn" type="submit">Buat pendaftaran</button>
        </form>
        <?php endif; ?>
    </div>
</details>

<section class="kartu">
    <form method="get" class="filter">
        <div class="field"><label for="tanggal">Tanggal</label><input type="date" id="tanggal" name="tanggal" value="<?= e($tanggal) ?>"></div>
        <div class="field"><label for="poli">Poli</label><select id="poli" name="poli"><option value="">Semua poli</option>
            <?php foreach ($poliAll as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected($p['id'], $poliF) ?>><?= e($p['nama']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">Semua status</option>
            <?php foreach (['menunggu' => 'Menunggu', 'diperiksa' => 'Sedang diperiksa', 'selesai' => 'Selesai', 'batal' => 'Batal'] as $v => $l): ?><option value="<?= $v ?>"<?= selected($v, $statusF) ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="bayar">Pembayaran</label><select id="bayar" name="bayar"><option value="">Semua</option>
            <option value="belum"<?= selected('belum', $bayarF) ?>>Belum bayar</option><option value="lunas"<?= selected('lunas', $bayarF) ?>>Lunas</option></select></div>
        <div class="field"><label for="q">Cari</label><input type="search" id="q" name="q" value="<?= e($cari) ?>" placeholder="Nama, NIK, nomor antrean"></div>
        <div class="aksi"><button class="btn" type="submit">Terapkan</button><a class="btn btn-sekunder" href="kunjungan.php">Hari ini</a></div>
    </form>
    <p class="redup kecil" style="margin-bottom:12px">Kosongkan tanggal untuk melihat semua hari. Menampilkan maksimal 300 data.</p>

    <?php if (!$rows): ?>
        <div class="kosong"><strong>Tidak ada kunjungan yang cocok</strong>Ubah filter atau daftarkan pasien baru.</div>
    <?php else: ?>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Tanggal</th><th>Antrean</th><th>Pasien</th><th>Poli</th><th>Dokter</th><th>Status</th><th>Pembayaran</th><th class="rata-kanan">Total</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr>
                <td class="nowrap"><?= e(tgl($r['tanggal'])) ?></td><td class="kode"><?= e($r['kode_antrean']) ?></td>
                <td><?= e($r['pasien']) ?></td><td><?= e($r['poli']) ?></td><td><?= $r['dokter'] ? e($r['dokter']) : '<span class="redup">-</span>' ?></td>
                <td><?= badge_status($r['status']) ?></td><td><?= badge_bayar($r['status'], $r['status_bayar']) ?></td>
                <td class="rata-kanan angka nowrap"><?= $r['status'] === 'selesai' ? e(rupiah($r['total'])) : '-' ?></td>
                <td class="rata-kanan nowrap">
                    <div class="aksi" style="justify-content:flex-end">
                    <?php if ($r['status'] === 'selesai'): ?><a class="btn btn-kecil <?= $r['status_bayar'] === 'belum' ? '' : 'btn-sekunder' ?>" href="nota.php?id=<?= (int) $r['id'] ?>"><?= $r['status_bayar'] === 'belum' ? 'Bayar' : 'Nota' ?></a><?php endif; ?>
                    <?php if (in_array($r['status'], ['menunggu', 'diperiksa'], true)): ?>
                        <form method="post" class="inline" data-konfirmasi="Batalkan pendaftaran <?= e($r['kode_antrean']) ?>?"><?= csrf_field() ?><input type="hidden" name="aksi" value="batal"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-kecil btn-sekunder" type="submit">Batalkan</button></form>
                    <?php endif; ?>
                    <form method="post" class="inline" data-konfirmasi="Hapus permanen data kunjungan <?= e($r['kode_antrean']) ?> milik <?= e($r['pasien']) ?>?<?= $r['status'] === 'selesai' ? ' Stok obat akan dikembalikan.' : '' ?>"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-kecil btn-ghost" type="submit">Hapus</button></form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php page_end();
