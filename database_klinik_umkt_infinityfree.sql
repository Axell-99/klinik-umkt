-- KLINIK UMKT CARE - skema database untuk InfinityFree
-- PENTING: Pilih database if0_43225950_klinik_umkt di phpMyAdmin sebelum Import.
-- CREATE DATABASE dan USE sengaja tidak disertakan karena hosting membatasi akses tersebut.

CREATE TABLE pengaturan (
    kunci VARCHAR(50) NOT NULL PRIMARY KEY,
    nilai VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    dibuat_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE poli (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(5) NOT NULL UNIQUE,
    nama VARCHAR(80) NOT NULL UNIQUE,
    tarif_pemeriksaan INT UNSIGNED NOT NULL DEFAULT 50000,
    aktif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dokter (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    poli_id INT UNSIGNED NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    dibuat_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dokter_poli FOREIGN KEY (poli_id) REFERENCES poli(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pasien (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nik CHAR(16) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    tanggal_lahir DATE NOT NULL,
    jenis_kelamin ENUM('L','P') NOT NULL,
    alamat VARCHAR(255) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    alergi VARCHAR(255) NULL,
    password_hash VARCHAR(255) NOT NULL,
    dibuat_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE obat (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL UNIQUE,
    satuan VARCHAR(20) NOT NULL DEFAULT 'tablet',
    harga INT UNSIGNED NOT NULL DEFAULT 0,
    stok INT UNSIGNED NOT NULL DEFAULT 0,
    aktif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kunjungan (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    pasien_id INT UNSIGNED NOT NULL,
    poli_id INT UNSIGNED NOT NULL,
    dokter_id INT UNSIGNED NULL,
    tanggal DATE NOT NULL,
    no_antrean SMALLINT UNSIGNED NOT NULL,
    kode_antrean VARCHAR(12) NOT NULL,
    keluhan TEXT NOT NULL,
    status ENUM('menunggu','diperiksa','selesai','batal') NOT NULL DEFAULT 'menunggu',
    diagnosis VARCHAR(255) NULL,
    tindakan VARCHAR(255) NULL,
    catatan TEXT NULL,
    biaya_pendaftaran INT UNSIGNED NOT NULL DEFAULT 0,
    biaya_pemeriksaan INT UNSIGNED NOT NULL DEFAULT 0,
    biaya_obat INT UNSIGNED NOT NULL DEFAULT 0,
    total INT UNSIGNED NOT NULL DEFAULT 0,
    status_bayar ENUM('belum','lunas') NOT NULL DEFAULT 'belum',
    metode_bayar VARCHAR(20) NULL,
    uang_diterima INT UNSIGNED NULL,
    dibayar_at DATETIME NULL,
    didaftarkan_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    mulai_at DATETIME NULL,
    selesai_at DATETIME NULL,
    UNIQUE KEY uq_antrean (poli_id, tanggal, no_antrean),
    KEY idx_antrean (poli_id, tanggal, status, no_antrean),
    KEY idx_pasien (pasien_id, tanggal),
    KEY idx_tanggal (tanggal, status),
    CONSTRAINT fk_kunjungan_pasien FOREIGN KEY (pasien_id) REFERENCES pasien(id),
    CONSTRAINT fk_kunjungan_poli FOREIGN KEY (poli_id) REFERENCES poli(id),
    CONSTRAINT fk_kunjungan_dokter FOREIGN KEY (dokter_id) REFERENCES dokter(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resep (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    kunjungan_id BIGINT UNSIGNED NOT NULL,
    obat_id INT UNSIGNED NOT NULL,
    nama_obat VARCHAR(100) NOT NULL,
    satuan VARCHAR(20) NOT NULL,
    harga INT UNSIGNED NOT NULL,
    jumlah SMALLINT UNSIGNED NOT NULL,
    aturan VARCHAR(150) NOT NULL,
    CONSTRAINT fk_resep_kunjungan FOREIGN KEY (kunjungan_id) REFERENCES kunjungan(id) ON DELETE CASCADE,
    CONSTRAINT fk_resep_obat FOREIGN KEY (obat_id) REFERENCES obat(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE percobaan_login (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    peran VARCHAR(10) NOT NULL,
    identitas VARCHAR(50) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_identitas (peran, identitas, waktu),
    KEY idx_ip (ip, waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Data awal =====
INSERT INTO pengaturan (kunci, nilai) VALUES
    ('nama_klinik', 'Klinik UMKT Care'),
    ('alamat_klinik', 'Jl. Contoh No. 1'),
    ('telepon_klinik', '0800-0000-000'),
    ('biaya_pendaftaran', '20000');

-- Akun admin awal: admin / admin123 (ganti setelah login pertama)
INSERT INTO admin (username, nama, password_hash) VALUES
    ('admin', 'Administrator', '$2y$10$2OuE8NwMWOGkLMvm4uiFW.KaI6AoKrbJZ9EVTBdFGcRf9pvwqvcva');

INSERT INTO poli (kode, nama, tarif_pemeriksaan) VALUES
    ('PU',  'Poli Umum',           50000),
    ('PG',  'Poli Gigi',           75000),
    ('PA',  'Poli Anak',           60000),
    ('PPD', 'Poli Penyakit Dalam', 80000),
    ('PM',  'Poli Mata',           70000),
    ('PS',  'Poli Saraf',          85000);

-- Akun dokter contoh: kata sandi semuanya dokter123 (ganti lewat menu Dokter di admin)
INSERT INTO dokter (poli_id, username, nama, password_hash) VALUES
    (1, 'dokter.umum',  'dr. Andi Pratama',       '$2y$10$8U/xY9XuReSJ2hjAKmjpNeuGFg1ZKg26C5rk0IR8eIZGVLevossIC'),
    (2, 'dokter.gigi',  'drg. Sinta Maharani',    '$2y$10$8U/xY9XuReSJ2hjAKmjpNeuGFg1ZKg26C5rk0IR8eIZGVLevossIC'),
    (3, 'dokter.anak',  'dr. Rina Kusuma, Sp.A',  '$2y$10$8U/xY9XuReSJ2hjAKmjpNeuGFg1ZKg26C5rk0IR8eIZGVLevossIC'),
    (4, 'dokter.dalam', 'dr. Budi Santoso, Sp.PD','$2y$10$8U/xY9XuReSJ2hjAKmjpNeuGFg1ZKg26C5rk0IR8eIZGVLevossIC'),
    (5, 'dokter.mata',  'dr. Lestari Wulan, Sp.M','$2y$10$8U/xY9XuReSJ2hjAKmjpNeuGFg1ZKg26C5rk0IR8eIZGVLevossIC'),
    (6, 'dokter.saraf', 'dr. Hendra Wijaya, Sp.N','$2y$10$8U/xY9XuReSJ2hjAKmjpNeuGFg1ZKg26C5rk0IR8eIZGVLevossIC');

INSERT INTO obat (nama, satuan, harga, stok) VALUES
    ('Paracetamol 500 mg',       'tablet',  1000, 500),
    ('Amoxicillin 500 mg',       'kapsul',  2500, 300),
    ('Ibuprofen 400 mg',         'tablet',  1500, 300),
    ('Asam Mefenamat 500 mg',    'tablet',  1500, 300),
    ('Cetirizine 10 mg',         'tablet',  1200, 250),
    ('Ambroxol 30 mg',           'tablet',  1000, 250),
    ('Antasida DOEN',            'tablet',   800, 400),
    ('Vitamin C 500 mg',         'tablet',   700, 400),
    ('Oralit',                   'sachet',  2000, 150),
    ('Salep Hidrokortison 2,5%', 'tube',   12000,  60),
    ('Tetes Mata Natrium Klorida','botol', 18000,  40);
