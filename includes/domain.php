<?php
declare(strict_types=1);

/* Pengaturan klinik (tabel pengaturan), dibaca sekali per request */
function pengaturan(string $kunci, string $default = ''): string
{
    static $semua = null;
    if ($semua === null) {
        $semua = array_column(fetch_all('SELECT kunci, nilai FROM pengaturan'), 'nilai', 'kunci');
    }
    return $semua[$kunci] ?? $default;
}

/*
 * Buat pendaftaran kunjungan untuk hari ini.
 * Baris poli dikunci (FOR UPDATE) selama transaksi, jadi dua pasien yang
 * mendaftar bersamaan tidak akan mendapat nomor antrean yang sama.
 * Nomor antrean mulai dari 001 lagi setiap hari, per poli.
 */
function buat_kunjungan(int $pasienId, int $poliId, string $keluhan): array
{
    $pdo = db();
    $tanggal = date('Y-m-d');
    $pdo->beginTransaction();
    try {
        $poli = fetch_one('SELECT id, kode, nama FROM poli WHERE id = ? AND aktif = 1 FOR UPDATE', [$poliId]);
        if (!$poli) {
            throw new PesanException('Poli yang dipilih tidak tersedia.');
        }
        $aktif = (int) fetch_val(
            "SELECT COUNT(*) FROM kunjungan WHERE pasien_id = ? AND poli_id = ? AND tanggal = ? AND status IN ('menunggu','diperiksa')",
            [$pasienId, $poliId, $tanggal]
        );
        if ($aktif > 0) {
            throw new PesanException('Pasien ini sudah terdaftar di ' . $poli['nama'] . ' hari ini dan belum selesai diperiksa.');
        }
        $no = (int) fetch_val('SELECT COALESCE(MAX(no_antrean), 0) + 1 FROM kunjungan WHERE poli_id = ? AND tanggal = ?', [$poliId, $tanggal]);
        $kode = sprintf('%s-%03d', $poli['kode'], $no);
        q('INSERT INTO kunjungan (pasien_id, poli_id, tanggal, no_antrean, kode_antrean, keluhan, biaya_pendaftaran, total)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$pasienId, $poliId, $tanggal, $no, $kode, $keluhan, (int) pengaturan('biaya_pendaftaran', '0'), (int) pengaturan('biaya_pendaftaran', '0')]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return ['id' => $id, 'kode_antrean' => $kode, 'poli' => $poli['nama']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/*
 * Antrean hari-hari sebelumnya yang belum selesai ditutup otomatis (status batal),
 * supaya tidak menumpuk di layar dokter. Data pemeriksaan baru tersimpan saat
 * dokter menyelesaikan pemeriksaan, jadi tidak ada data klinis yang hilang.
 */
function tutup_antrean_lama(): void
{
    q("UPDATE kunjungan SET status = 'batal', dokter_id = NULL
       WHERE tanggal < ? AND status IN ('menunggu','diperiksa')", [date('Y-m-d')]);
}

/* Hapus kunjungan; jika sudah selesai, stok obat yang terpakai dikembalikan. */
function hapus_kunjungan(int $id): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $k = fetch_one('SELECT id, status FROM kunjungan WHERE id = ? FOR UPDATE', [$id]);
        if (!$k) {
            throw new PesanException('Data kunjungan tidak ditemukan.');
        }
        if ($k['status'] === 'selesai') {
            foreach (fetch_all('SELECT obat_id, jumlah FROM resep WHERE kunjungan_id = ?', [$id]) as $r) {
                q('UPDATE obat SET stok = stok + ? WHERE id = ?', [$r['jumlah'], $r['obat_id']]);
            }
        }
        q('DELETE FROM kunjungan WHERE id = ?', [$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* Poli yang masih aktif, beserta jumlah pasien yang sedang menunggu hari ini */
function daftar_poli_aktif(): array
{
    return fetch_all(
        "SELECT p.id, p.kode, p.nama, p.tarif_pemeriksaan,
                (SELECT COUNT(*) FROM kunjungan k WHERE k.poli_id = p.id AND k.tanggal = ? AND k.status = 'menunggu') AS menunggu
         FROM poli p WHERE p.aktif = 1 ORDER BY p.nama",
        [date('Y-m-d')]
    );
}
