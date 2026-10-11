# AlyraBeauty Store — Sistem Manajemen Persediaan

Mini Project 3 untuk materi **Relational Database + Advanced PHP**. Website ini dibuat untuk membantu toko kosmetik AlyraBeauty Store mencatat data produk, kategori, supplier, serta barang masuk dan keluar.

## Fitur website
- Login dan logout menggunakan session serta password hashing (`password_hash` dan `password_verify`).
- Dua peran pengguna, yaitu Admin dan Staff. Admin dapat mengelola pengguna dan data master.
- Pengelolaan kategori, produk, dan supplier.
- Relasi database one-to-one (`users`–`user_profiles`), one-to-many (`categories`–`products`), serta many-to-many (`products`–`suppliers` melalui `product_suppliers`).
- Foreign key dan aturan referential integrity untuk menjaga hubungan antartabel.
- Transaksi stok masuk dan keluar, termasuk histori. Perubahan stok dan penyimpanan histori memakai transaksi database, commit, rollback, dan penguncian baris.
- Stok tidak boleh negatif dan jumlah barang keluar tidak boleh melebihi stok yang tersedia.
- Pencarian produk berdasarkan nama atau SKU, filter kategori, sorting dengan pilihan yang diizinkan, dan pagination.
- Dashboard berisi ringkasan persediaan dan stok yang perlu diperhatikan.
- Laporan menggunakan JOIN, WHERE, LIKE, ORDER BY, GROUP BY, COUNT, SUM, dan AVG sesuai kebutuhan data.
- Prepared statements, validasi di sisi server, escaping output HTML, token CSRF, dan pemeriksaan hak akses.

## Kebutuhan
- PHP 8.1 atau lebih baru dengan ekstensi PDO MySQL dan mbstring.
- MySQL 8+ atau MariaDB yang mendukung InnoDB.
- XAMPP atau Laragon.

## Cara menjalankan di XAMPP
1. Ekstrak folder `AlyraBeauty_Store` ke `C:\xampp\htdocs\`.
2. Jalankan Apache dan MySQL melalui XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin`.
4. Pilih menu **Import**, lalu impor file `database/schema.sql`. File ini membuat database `alyrabeauty_inventory`, tabel, dan data contoh.
5. Jika konfigurasi MySQL berbeda, sesuaikan `config/database.php`. Pengaturan awal menggunakan host `127.0.0.1`, database `alyrabeauty_inventory`, user `root`, dan password kosong.
6. Buka `http://localhost/AlyraBeauty_Store/public/`.

## Akun demo
- Username: `admin`
- Password: `password`

Akun tersebut hanya untuk menjalankan dan menguji project secara lokal. Ganti password sebelum digunakan untuk kebutuhan nyata.

## Hal yang perlu diuji
1. Login sebagai Admin, lalu periksa dashboard.
2. Tambahkan kategori dan produk dengan SKU yang belum pernah dipakai.
3. Coba menyimpan SKU duplikat; database harus menolaknya.
4. Catat transaksi barang masuk, lalu pastikan stok dan histori berubah.
5. Coba barang keluar dengan jumlah melebihi stok; transaksi harus gagal dan stok tidak berubah.
6. Coba pencarian nama/SKU, filter kategori, sorting, dan pagination.
7. Buat akun Staff, lalu pastikan menu administrasi tidak bisa diakses oleh Staff.
8. Periksa laporan persediaan.
9. Coba menghapus data yang masih dipakai relasi atau histori; aturan foreign key menjaga integritas data.

## Struktur folder
```text
AlyraBeauty_Store/
├── config/
│   └── database.php
├── database/
│   └── schema.sql
├── helpers/
│   ├── auth.php
│   ├── escape.php
│   └── validation.php
├── public/
│   ├── assets/
│   │   └── style.css
│   └── index.php
└── README.md
```

## Relasi database
- `users` 1 — 1 `user_profiles`
- `categories` 1 — N `products`
- `products` N — N `suppliers` melalui `product_suppliers`
- `products` 1 — N `stock_movements`
- `users` 1 — N `stock_movements`

## Aturan bisnis
- SKU produk harus unik.
- Harga, harga supplier, dan stok minimum tidak boleh negatif.
- Jumlah transaksi harus lebih dari nol.
- Barang keluar tidak boleh melebihi stok tersedia.
- Perubahan stok dan pencatatan histori dilakukan dalam satu transaksi database.
- Produk yang memiliki histori tidak boleh dihapus sembarangan.
- Halaman internal memerlukan login.
- Hanya Admin yang dapat mengelola pengguna dan data master.
- Kolom sorting dipilih dari daftar yang diizinkan, bukan langsung dari input SQL pengguna.

## Catatan
Sesudah website berhasil dijalankan, ambil screenshot halaman login, dashboard, produk, transaksi stok, dan laporan untuk dilampirkan pada pengumpulan tugas. Project ini disiapkan untuk pembelajaran lokal; lakukan pengujian di komputer sendiri sebelum dikumpulkan.
