<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_login('admin');

$error = '';
$bagian = '';
if (is_post()) {
    csrf_check();
    $aksi = post('aksi');
    $bagian = $aksi;
    try {
        if ($aksi === 'ganti_pw') {
            $lama = (string) ($_POST['lama'] ?? ''); $baru = (string) ($_POST['baru'] ?? ''); $ulang = (string) ($_POST['ulang'] ?? '');
            if (!password_verify($lama, $admin['password_hash'])) throw new PesanException('Kata sandi saat ini salah.');
            validasi_password($baru, $ulang);
            q('UPDATE admin SET password_hash = ? WHERE id = ?', [hash_password($baru), $admin['id']]);
            session_regenerate_id(true);
            flash('ok', 'Kata sandi Anda sudah diganti.');
            redirect(url('admin/akun.php'));
        }
        if ($aksi === 'tambah') {
            $nama = post('nama'); $username = mb_strtolower(post('username')); $pw = (string) ($_POST['password'] ?? '');
            wajib($nama, 'Nama', 100);
            validasi_username($username);
            validasi_password($pw);
            q('INSERT INTO admin (username, nama, password_hash) VALUES (?,?,?)', [$username, $nama, hash_password($pw)]);
            flash('ok', 'Akun admin ditambahkan.');
            redirect(url('admin/akun.php'));
        }
        if ($aksi === 'reset') {
            $pw = (string) ($_POST['password'] ?? '');
            validasi_password($pw);
            q('UPDATE admin SET password_hash = ? WHERE id = ?', [hash_password($pw), (int) post('id')]);
            flash('ok', 'Kata sandi admin tersebut sudah diatur ulang.');
            redirect(url('admin/akun.php'));
        }
        if ($aksi === 'hapus') {
            $id = (int) post('id');
            if ($id === (int) $admin['id']) throw new PesanException('Anda tidak bisa menghapus akun yang sedang dipakai.');
            q('DELETE FROM admin WHERE id = ?', [$id]);
            flash('ok', 'Akun admin dihapus.');
            redirect(url('admin/akun.php'));
        }
    } catch (PesanException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = pesan_db($e, 'Username tersebut sudah dipakai.');
    }
}
$rows = fetch_all('SELECT id, username, nama, dibuat_at FROM admin ORDER BY nama');

page_start('Akun admin', 'panel', ['peran' => 'admin', 'sub' => 'Kelola siapa saja yang bisa masuk sebagai admin.']);
if ($error) echo alert('error', $error);
?>
<section class="kartu">
    <h2>Ganti kata sandi saya</h2>
    <form method="post" novalidate style="max-width:420px">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="ganti_pw">
        <div class="field"><label for="lama">Kata sandi saat ini</label><input type="password" id="lama" name="lama" autocomplete="current-password" required></div>
        <div class="field"><label for="baru">Kata sandi baru</label><input type="password" id="baru" name="baru" minlength="8" autocomplete="new-password" required><small>Minimal 8 karakter.</small></div>
        <div class="field"><label for="ulang">Ulangi kata sandi baru</label><input type="password" id="ulang" name="ulang" minlength="8" autocomplete="new-password" required></div>
        <button class="btn" type="submit">Ganti kata sandi</button>
    </form>
</section>

<details class="lipat"<?= $error && $bagian === 'tambah' ? ' open' : '' ?>>
    <summary>Tambah admin</summary>
    <div class="isi-lipat">
    <form method="post" novalidate>
        <?= csrf_field() ?><input type="hidden" name="aksi" value="tambah">
        <div class="baris-form">
            <div class="field"><label for="nama">Nama</label><input type="text" id="nama" name="nama" maxlength="100" required value="<?= $bagian === 'tambah' ? old('nama') : '' ?>"></div>
            <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" maxlength="30" autocomplete="off" required value="<?= $bagian === 'tambah' ? old('username') : '' ?>"></div>
            <div class="field"><label for="password">Kata sandi</label><input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required></div>
        </div>
        <button class="btn" type="submit">Tambah admin</button>
    </form>
    </div>
</details>

<section class="kartu">
    <h2>Daftar admin</h2>
    <div class="tabel-bungkus"><table>
        <thead><tr><th>Nama</th><th>Username</th><th>Dibuat</th><th>Atur ulang kata sandi</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): $saya = (int) $r['id'] === (int) $admin['id']; ?>
            <tr><td><strong><?= e($r['nama']) ?></strong><?= $saya ? ' <span class="badge badge-biru">Anda</span>' : '' ?></td><td><?= e($r['username']) ?></td><td class="nowrap"><?= e(tgl($r['dibuat_at'])) ?></td>
                <td><?php if (!$saya): ?><form method="post" class="aksi"><?= csrf_field() ?><input type="hidden" name="aksi" value="reset"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input type="password" name="password" minlength="8" placeholder="Kata sandi baru" autocomplete="new-password" required style="width:170px" aria-label="Kata sandi baru untuk <?= e($r['nama']) ?>"><button class="btn btn-kecil btn-sekunder" type="submit">Atur ulang</button></form>
                    <?php else: ?><span class="redup kecil">Gunakan form di atas</span><?php endif; ?></td>
                <td class="rata-kanan"><?php if (!$saya): ?><form method="post" class="inline" data-konfirmasi="Hapus akun admin <?= e($r['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-kecil btn-ghost" type="submit">Hapus</button></form><?php endif; ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</section>
<?php page_end();
