# KLINIK UMKT CARE (PHP + MySQL)

Sistem informasi klinik dengan **tiga portal terpisah**: pasien, dokter, dan admin.
Dibuat dengan PHP murni (tanpa framework) dan MySQL/MariaDB, jadi mudah dijalankan di XAMPP/Laragon.

## Isi tiap portal

| Portal | Halaman | Fungsi |
|---|---|---|
| **Pasien** | `pasien/login.php`, `pasien/register.php`, `pasien/daftar.php` | Buat akun (NIK), masuk, lalu satu halaman untuk mendaftar ke poli. Pasien mendapat tiket nomor antrean dan melihat berapa pasien di depannya. |
| **Dokter** | `dokter/login.php`, `index.php`, `periksa.php`, `riwayat.php` | Melihat antrean poli-nya **urut dari yang mendaftar paling awal**, memanggil pasien, mengisi diagnosis dan tindakan, memberi resep obat (stok otomatis berkurang), serta melihat riwayat. |
| **Admin** | `admin/login.php` dan menu: Dashboard, Kunjungan, Pasien, Dokter, Poli dan tarif, Obat, Laporan, Pengaturan, Akun admin | Mengelola semuanya: data master, mendaftarkan pasien di loket, membatalkan/menghapus kunjungan, mencatat pembayaran + cetak nota, laporan pendapatan (unduh CSV), dan akun admin. |

Aturan utama:
- Nomor antrean **per poli, reset tiap hari** (PU-001, PG-001, dst).
- Pasien tidak bisa mendaftar dua kali di poli yang sama selama kunjungannya belum selesai, tapi bisa berkunjung lagi di hari lain (satu akun, banyak kunjungan).
- Antrean hari sebelumnya yang belum selesai ditutup otomatis (status Batal).
- Tagihan = biaya pendaftaran + tarif poli + harga obat yang diresepkan.

## Menjalankan di XAMPP / Laragon

1. Salin folder `klinik-umkt-care` ke `htdocs` (XAMPP) atau `www` (Laragon).
2. Nyalakan Apache dan MySQL.
3. Buka phpMyAdmin, tab **Import**, pilih `database.sql`, klik Go. Database `klinik_umkt` terbentuk beserta data awal.
4. Sesuaikan `config.php` bila perlu (default XAMPP: user `root`, tanpa kata sandi). Atur juga `timezone`.
5. Buka `http://localhost/klinik-umkt-care/`.

Butuh PHP 8.1 atau lebih baru dengan ekstensi `pdo_mysql`.

## Akun awal (ganti setelah login pertama!)

| Peran | Login | Kata sandi |
|---|---|---|
| Admin | `admin` | `admin123` |
| Dokter | `dokter.umum`, `dokter.gigi`, `dokter.anak`, `dokter.dalam`, `dokter.mata`, `dokter.saraf` | `dokter123` |
| Pasien | daftar sendiri lewat halaman *Buat akun pasien* | bebas (min. 8 karakter) |

Ganti kata sandi admin lewat menu **Akun admin**, dan kata sandi dokter lewat menu **Dokter** (Ubah).

## Alur uji cepat

1. Buka halaman pasien, buat akun, daftar ke *Poli Umum*, catat nomor antreannya.
2. Buat akun pasien kedua dan daftar ke poli yang sama (dapat nomor berikutnya).
3. Masuk sebagai `dokter.umum`: pasien pertama muncul paling atas. Klik *Panggil dan periksa*, isi diagnosis dan resep, simpan.
4. Masuk sebagai admin: buka **Kunjungan**, klik *Bayar*, pilih metode, tandai lunas, lalu cetak nota.
5. Lihat **Laporan** untuk rekap pendapatan.

## Struktur folder

```
klinik-umkt-care/
├── index.php            beranda dan pintu masuk tiga portal
├── config.php           pengaturan database dan zona waktu
├── database.sql         skema + data awal
├── includes/            bootstrap (sesi, PDO), auth, helpers, logika antrean, layout
├── assets/              style.css, app.js
├── pasien/  dokter/  admin/
```

## Keamanan yang sudah diterapkan

- Semua query memakai *prepared statement* (PDO), semua keluaran di-escape (`e()`).
- Kata sandi disimpan dengan `password_hash` (bcrypt), tidak pernah plaintext.
- Token CSRF di setiap formulir POST; logout juga lewat POST.
- Tiga peran memakai kunci sesi terpisah, dicek ulang ke database tiap request (dokter yang dinonaktifkan langsung terkeluar). Dokter hanya bisa mengakses pasien di poli-nya.
- Pembatasan percobaan login: 5 kali gagal per akun dalam 10 menit akan dikunci sementara.
- Nomor antrean dibuat di dalam transaksi dengan penguncian baris, jadi pendaftaran serentak tidak menghasilkan nomor ganda. Stok obat juga dikunci saat resep disimpan.

## Sebelum dipakai sungguhan (bukan hanya tugas)

- Pasang **HTTPS**, dan ganti semua kata sandi awal.
- Matikan `debug` di `config.php`, dan jangan pakai akun `root` MySQL tanpa kata sandi.
- Penguncian login per akun berarti orang lain bisa mengunci akun tertentu selama 10 menit dengan sengaja menebak salah. Untuk produksi, tambahkan CAPTCHA atau pembatasan berbasis IP.
- Data kesehatan termasuk data pribadi sensitif. Perlu kebijakan akses, backup rutin, dan kepatuhan UU PDP bila dipakai sungguhan.

## Hosting

Aplikasi ini butuh PHP + MySQL, jadi **tidak bisa di Netlify**. Pakai hosting PHP biasa (cPanel/shared hosting, VPS, atau hosting gratis berbasis PHP). Upload folder, impor `database.sql`, ubah `config.php` (atau set environment variable `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). Folder boleh ditaruh di subfolder mana saja, URL dasar terdeteksi otomatis.
