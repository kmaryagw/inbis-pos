# INBIS POS

Sistem Point of Sales untuk penjualan produk pada kegiatan pameran — Inkubator Bisnis Universitas Udayana.
Mengacu pada *Dokumen Kebutuhan Sistem INBIS POS v1.0*.

## Stack

- Laravel 12 (PHP ≥ 8.2), MySQL/MariaDB
- Blade + Bootstrap 5 untuk halaman admin
- Alpine.js untuk halaman kasir (keranjang, total, kembalian dihitung di browser; server hanya dihubungi saat simpan)
- Tanpa build step: Bootstrap, Bootstrap Icons, dan Alpine disimpan lokal di `public/vendor`

## Instalasi

```bash
composer install
cp .env.example .env          # isi DB_* dan SEED_*_PASSWORD
php artisan key:generate
php artisan migrate --seed    # membuat akun administrator
php artisan storage:link      # untuk foto produk
php artisan serve --host=0.0.0.0   # agar bisa diakses HP di jaringan yang sama
```

Data contoh (kegiatan aktif, 12 produk, transaksi contoh, akun kasir & pimpinan):
`php artisan db:seed --class=DemoSeeder`

### Akun awal

Seeder membuat akun `admin` (dan `kasir1`, `pimpinan` untuk data contoh). Kata sandinya diambil dari
`SEED_ADMIN_PASSWORD`, `SEED_CASHIER_PASSWORD`, dan `SEED_MANAGER_PASSWORD` di `.env`. Bila dikosongkan,
seeder membuat kata sandi acak dan menampilkannya sekali di terminal. Ganti kata sandi setelah login pertama
(menu akun → Ubah kata sandi). Jangan menulis kata sandi atau isi `.env` di README maupun commit.

## Tes

Tes memakai database MySQL terpisah bernama `inbis_pos_test` (lihat `phpunit.xml`). Buat database
kosong tersebut dengan user database Anda, lalu jalankan:

```bash
php artisan test
```

## Keputusan desain penting

- **Stok per kegiatan** di `event_products.current_stock`, selalu diubah bersamaan dengan baris
  `stock_movements` lewat `App\Services\StockService` (satu-satunya pintu perubahan stok).
- **Checkout atomik** (`App\Services\CheckoutService`): baris stok dikunci `SELECT … FOR UPDATE`,
  stok & harga divalidasi ulang di server, transaksi + detail + pergerakan stok disimpan dalam satu
  transaksi database. Dua kasir yang menjual stok terakhir bersamaan tidak membuat stok minus.
- **Anti transaksi ganda**: setiap transaksi membawa `idempotency_key` (UUID dari browser, unik di DB).
  Tombol yang ditekan dua kali atau retry jaringan mengembalikan transaksi yang sama.
- **Nomor transaksi** `POS-[KODE]-YYYYMMDD-NNNN` dari tabel `transaction_counters` yang dikunci per kegiatan per hari.
- **Snapshot** nama, kode, dan harga produk disimpan di `transaction_details`.
- **Harga berubah** saat kasir sedang melayani: server menolak bila total berbeda dari yang dilihat kasir.
- Uang disimpan sebagai bilangan bulat rupiah. Zona waktu default `Asia/Makassar` (WITA).

## Status pengembangan

- [x] MVP 1 — login, role Admin/Kasir, kegiatan (Draft/Aktif/Selesai), master produk & kategori,
      alokasi produk per kegiatan, harga & stok, stok masuk/keluar, riwayat stok, penugasan kasir
- [x] MVP 2 — halaman kasir mobile-first, keranjang, tunai/QRIS (pencatatan), validasi stok, nomor transaksi
- [x] MVP 3 — struk thermal 58/55 mm, cetak ulang, riwayat transaksi (filter nomor/tanggal/kasir/metode/status),
      pembatalan oleh admin dengan alasan + pergerakan stok pembalik
- [x] MVP 4 — dashboard, laporan ringkasan penjualan / per produk / metode pembayaran / stok,
      filter lengkap, tampilan cetak A4, ekspor XLSX (OpenSpout), perbandingan pendapatan antar kegiatan
- [ ] Pengembangan — cetak langsung dari HP (RawBT/Web Bluetooth), barcode, diskon, PWA, mode offline

## Role

| Role | Akses |
|---|---|
| Administrator | Semua menu, termasuk pembatalan transaksi |
| Kasir | Halaman kasir & riwayat transaksi pada kegiatan yang ditugaskan |
| Pengelola/Pimpinan | Dashboard, laporan, ekspor, riwayat transaksi (hanya-baca) |

## Cetak struk (printer iWare X583, 58 mm)

Dua jalur, dipilih per perangkat di halaman kasir → menu ☰ → **Pengaturan printer** (tersimpan di browser perangkat itu):

**HP Android + Bluetooth (RawBT)** — default di Android
1. Pasang aplikasi **RawBT** dari Play Store.
2. Pasangkan (pair) X583 di pengaturan Bluetooth HP.
3. Di RawBT: pilih koneksi Bluetooth → pilih X583, driver ESC/POS, lebar kertas 58 mm.
4. Di INBIS POS: Pengaturan printer → RawBT → **Cetak struk uji**.

Server membuat struk dalam perintah ESC/POS 32 kolom (`App\Services\EscPosReceipt`) dan browser
mengirimnya ke RawBT lewat intent `intent:base64,…;scheme=rawbt`.

**Laptop + USB (dialog cetak browser)** — default di laptop
Pasang driver printer 58 mm, lalu di dialog cetak pilih printer X583, ukuran kertas 58 mm, margin *None*,
skala 100%. Template: `resources/views/receipts/show.blade.php` (`@page { size: 58mm auto }`, area cetak 48 mm).

Opsi **Cetak otomatis setelah transaksi** membuka cetak segera setelah pembayaran tersimpan. Browser hanya
mengizinkannya beberapa detik setelah tombol ditekan; bila koneksi lambat, tekan **Cetak struk** secara manual.
