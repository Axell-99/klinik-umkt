<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_login('admin');

$error = '';
if (is_post()) {
    csrf_check();
    try {
        $nama = post('nama_klinik'); $alamat = post('alamat_klinik'); $telp = post('telepon_klinik');
        wajib($nama, 'Nama klinik', 100);
        wajib($alamat, 'Alamat klinik', 200);
        wajib($telp, 'Telepon klinik', 30);
        $biaya = angka_positif(post('biaya_pendaftaran'), 'Biaya pendaftaran', 10000000);
        foreach (['nama_klinik' => $nama, 'alamat_klinik' => $alamat, 'telepon_klinik' => $telp, 'biaya_pendaftaran' => (string) $biaya] as $k => $nilai) {
            q('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)', [$k, $nilai]);
        }
        flash('ok', 'Pengaturan disimpan. Biaya pendaftaran baru berlaku untuk pendaftaran berikutnya.');
        redirect(url('admin/pengaturan.php'));
    } catch (PesanException $e) {
        $error = $e->getMessage();
    }
}
$v = fn(string $k, string $d = '') => e(is_post() ? post($k) : pengaturan($k, $d));

page_start('Pengaturan', 'panel', ['peran' => 'admin', 'sub' => 'Identitas klinik untuk nota dan biaya pendaftaran.']);
if ($error) echo alert('error', $error);
?>
<section class="kartu">
    <form method="post" novalidate style="max-width:560px">
        <?= csrf_field() ?>
        <div class="field"><label for="nama_klinik">Nama klinik</label><input type="text" id="nama_klinik" name="nama_klinik" maxlength="100" required value="<?= $v('nama_klinik') ?>"></div>
        <div class="field"><label for="alamat_klinik">Alamat klinik</label><input type="text" id="alamat_klinik" name="alamat_klinik" maxlength="200" required value="<?= $v('alamat_klinik') ?>"></div>
        <div class="field"><label for="telepon_klinik">Telepon klinik</label><input type="text" id="telepon_klinik" name="telepon_klinik" maxlength="30" required value="<?= $v('telepon_klinik') ?>"></div>
        <div class="field"><label for="biaya_pendaftaran">Biaya pendaftaran (Rp)</label><input type="text" id="biaya_pendaftaran" name="biaya_pendaftaran" inputmode="numeric" data-angka required value="<?= $v('biaya_pendaftaran', '0') ?>">
            <small>Dikenakan satu kali untuk setiap kunjungan. Tarif pemeriksaan diatur per poli di menu Poli dan tarif.</small></div>
        <button class="btn" type="submit">Simpan pengaturan</button>
    </form>
</section>
<?php page_end();
