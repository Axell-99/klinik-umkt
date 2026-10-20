<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_login('admin');

$error = '';
$editId = (int) ($_GET['edit'] ?? post('id'));
if (is_post()) {
    csrf_check();
    $aksi = post('aksi');
    try {
        if ($aksi === 'hapus') {
            try {
                q('DELETE FROM obat WHERE id = ?', [(int) post('id')]);
                flash('ok', 'Obat dihapus.');
            } catch (PDOException $e) {
                flash('error', pesan_db($e, '', 'Obat ini pernah diresepkan, jadi tidak bisa dihapus. Nonaktifkan saja agar tidak muncul di resep baru.'));
            }
            redirect(url('admin/obat.php'));
        }
        if ($aksi === 'tambah_stok') {
            $jml = angka_positif(post('jumlah'), 'Jumlah stok', 100000);
            if ($jml < 1) throw new PesanException('Jumlah penambahan stok minimal 1.');
            q('UPDATE obat SET stok = stok + ? WHERE id = ?', [$jml, (int) post('id')]);
            flash('ok', 'Stok ditambah ' . $jml . '.');
            redirect(url('admin/obat.php'));
        }
        if ($aksi === 'simpan') {
            $id = (int) post('id');
            $nama = post('nama'); $satuan = post('satuan'); $aktif = post('aktif') === '1' ? 1 : 0;
            wajib($nama, 'Nama obat', 100);
            wajib($satuan, 'Satuan', 20);
            $harga = angka_positif(post('harga'), 'Harga per satuan', 100000000);
            $stok = angka_positif(post('stok'), 'Stok', 1000000);
            if ($id === 0) {
                q('INSERT INTO obat (nama, satuan, harga, stok, aktif) VALUES (?,?,?,?,?)', [$nama, $satuan, $harga, $stok, $aktif]);
                flash('ok', 'Obat ditambahkan.');
            } else {
                q('UPDATE obat SET nama=?, satuan=?, harga=?, stok=?, aktif=? WHERE id=?', [$nama, $satuan, $harga, $stok, $aktif, $id]);
                flash('ok', 'Data obat diperbarui.');
            }
            redirect(url('admin/obat.php'));
        }
    } catch (PesanException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = pesan_db($e, 'Nama obat tersebut sudah ada.');
    }
}

$edit = $editId > 0 ? fetch_one('SELECT * FROM obat WHERE id = ?', [$editId]) : null;
$v = fn(string $k, string $d = '') => e(is_post() ? post($k) : (string) ($edit[$k] ?? $d));
$aktifNilai = is_post() ? post('aktif') === '1' : (!$edit || (int) $edit['aktif'] === 1);
$rows = fetch_all('SELECT * FROM obat ORDER BY aktif DESC, nama');

page_start('Obat', 'panel', ['peran' => 'admin', 'sub' => 'Daftar obat, harga, dan stok. Dokter hanya bisa meresepkan obat aktif yang stoknya tersedia.']);
if ($error) echo alert('error', $error);
?>
<details class="lipat"<?= ($edit || $error) ? ' open' : '' ?>>
    <summary><?= $edit ? 'Ubah obat' : 'Tambah obat' ?></summary>
    <div class="isi-lipat">
    <form method="post" novalidate>
        <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="baris-form">
            <div class="field"><label for="nama">Nama obat</label><input type="text" id="nama" name="nama" maxlength="100" required value="<?= $v('nama') ?>"></div>
            <div class="field"><label for="satuan">Satuan</label><input type="text" id="satuan" name="satuan" maxlength="20" required placeholder="tablet, kapsul, botol" value="<?= $v('satuan', 'tablet') ?>"></div>
            <div class="field"><label for="harga">Harga per satuan (Rp)</label><input type="text" id="harga" name="harga" inputmode="numeric" data-angka required value="<?= $v('harga') ?>"></div>
            <div class="field"><label for="stok">Stok</label><input type="text" id="stok" name="stok" inputmode="numeric" data-angka required value="<?= $v('stok', '0') ?>"></div>
        </div>
        <div class="field"><label class="cek"><input type="checkbox" name="aktif" value="1"<?= checked($aktifNilai) ?>> Obat aktif (bisa diresepkan)</label></div>
        <div class="aksi"><button class="btn" type="submit">Simpan</button><?php if ($edit): ?><a class="btn btn-sekunder" href="obat.php">Batal</a><?php endif; ?></div>
    </form>
    </div>
</details>

<section class="kartu">
    <?php if (!$rows): ?><div class="kosong"><strong>Belum ada obat</strong>Tambahkan obat agar dokter bisa membuat resep.</div><?php else: ?>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Obat</th><th class="rata-kanan">Harga</th><th class="rata-kanan">Stok</th><th>Status</th><th>Tambah stok</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr><td><strong><?= e($r['nama']) ?></strong></td><td class="rata-kanan angka nowrap"><?= e(rupiah($r['harga'])) ?> / <?= e($r['satuan']) ?></td>
                <td class="rata-kanan angka nowrap"><?= (int) $r['stok'] ?><?= (int) $r['stok'] <= 20 ? ' <span class="badge badge-merah">menipis</span>' : '' ?></td>
                <td><?= $r['aktif'] ? '<span class="badge badge-hijau">Aktif</span>' : '<span class="badge badge-abu">Nonaktif</span>' ?></td>
                <td><form method="post" class="aksi"><?= csrf_field() ?><input type="hidden" name="aksi" value="tambah_stok"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input type="number" name="jumlah" min="1" max="100000" required style="width:90px" aria-label="Jumlah stok yang ditambah untuk <?= e($r['nama']) ?>"><button class="btn btn-kecil btn-sekunder" type="submit">Tambah</button></form></td>
                <td class="rata-kanan nowrap"><div class="aksi" style="justify-content:flex-end">
                    <a class="btn btn-kecil btn-sekunder" href="obat.php?edit=<?= (int) $r['id'] ?>">Ubah</a>
                    <form method="post" class="inline" data-konfirmasi="Hapus <?= e($r['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-kecil btn-ghost" type="submit">Hapus</button></form>
                </div></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</section>
<?php page_end();
