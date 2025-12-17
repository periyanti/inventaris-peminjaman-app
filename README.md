# 📦 Sistem Manajemen Inventaris & Peminjaman

Kelompok 2 – Pemrograman Web

## 📌 Deskripsi Proyek

Aplikasi web untuk mengelola aset inventaris dan proses peminjaman/pengembalian barang pada laboratorium/perpustakaan.

Tujuan aplikasi:

* Mengelola data barang inventaris
* Mencatat proses peminjaman dan pengembalian
* Menyediakan laporan aktivitas peminjaman
* Mencegah kehilangan aset dan mempermudah administrasi

---

## 🧩 Fitur Utama

* 🔐 Login & session autentikasi
* 👥 Manajemen User / Roles (admin/peminjam)
* 📦 CRUD Items / Barang
* 🗂 CRUD Categories
* 📝 Peminjaman barang
* 🔁 Pengembalian barang
* 📑 Laporan aktivitas (activity log)
* 📆 Reminder jatuh tempo (opsional)
* 📷 Barcode text sederhana (optional)

---

## 🗄 Struktur Folder

```
/
├── config/
│   ├── config.php
│   └── database.php
├── includes/
│   ├── auth.php
│   ├── footer.php
│   ├── functions.php
│   └── header.php
├── sql/
│   └── scheme.sql
├── index.php
├── items.php
├── loans.php
├── returns.php
├── categories.php
├── login.php
├── logout.php
├── reports.php
└── README.md
```

---

## 🗃 Database

File schema / tabel tersedia pada:

```
/sql/scheme.sql
```

Tabel utama:

* users
* roles
* items
* categories
* loans
* returns
* suppliers
* activity_log

---

## ▶ Cara Menjalankan Aplikasi (Panduan Run)

### 1️⃣ Pastikan sudah ada:

* PHP
* Apache (XAMPP/Laragon)
* MySQL

### 2️⃣ Clone project

```bash
git clone <link repo github>
```

### 3️⃣ Letakkan project ke folder server lokal

contoh

```
c:/xampp/htdocs/inventaris-peminjaman/
```

### 4️⃣ Import database

* buka phpMyAdmin
* buat database baru, misal: `inventaris_db`
* import file `/sql/scheme.sql`

### 5️⃣ Konfigurasi koneksi DB

Edit file:

```
/config/database.php
```

sesuaikan:

```php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "inventaris_db";
```

### 6️⃣ Jalankan aplikasi

akses di browser:

```
http://localhost/inventaris-peminjaman
```

---

## 🔑 Kredensial Login Dummy

```
username: admin
password: admin123
```

(bisa diganti sesuai data di database)

---

Kelompok 7
Nur Aisyah Masdin
Nur Fahila Dwi Irfani Devi
Nur Octavia Kaila Ramadhani
Periyanti Rayo
Riadarmawangsi

---
📎 Link Repository

```
<isi setelah submit>
```

 🌐 Link Demo (opsional)

``
