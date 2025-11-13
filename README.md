# Sistem Inventaris Peminjaman Perpustakaan

Aplikasi web berbasis PHP dan MySQL untuk mengelola inventaris dan peminjaman buku perpustakaan dengan fitur lengkap dan keamanan tinggi.

## Fitur Utama

### 🔐 Keamanan
- Autentikasi dan otorisasi dengan role-based access control
- CSRF protection dengan token
- SQL injection prevention menggunakan prepared statements
- XSS prevention dengan output escaping
- Session management dengan regenerasi ID
- Password hashing dengan bcrypt
- HTTPS-ready dengan security headers

### 📚 Manajemen Inventaris
- CRUD lengkap untuk buku, kategori, dan supplier
- Barcode system untuk identifikasi buku
- Tracking kondisi buku (baru, baik, rusak)
- Manajemen stok dan ketersediaan
- Lokasi penyimpanan buku

### 📖 Peminjaman & Pengembalian
- Sistem peminjaman dengan kode unik
- Tracking jatuh tempo dan keterlambatan
- Perhitungan denda otomatis
- Pencatatan kondisi buku saat pengembalian
- Notifikasi jatuh tempo

### 👥 Manajemen User
- Role-based access control (Admin, Librarian, User)
- Manajemen user lengkap
- Activity logging
- Session tracking

### 📊 Laporan & Statistik
- Dashboard dengan grafik interaktif
- Laporan peminjaman dan pengembalian
- Statistik penggunaan buku
- Log aktivitas lengkap
- Export data

### 🎨 User Interface
- Responsive design dengan Tailwind CSS
- Interactive charts dengan Plotly.js
- Loading states dan animations
- Form validation client-side dan server-side
- Search dan pagination

## Teknologi yang Digunakan

- **Backend**: PHP 8.1+
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **CSS Framework**: Tailwind CSS
- **Charts**: Plotly.js
- **Icons**: Font Awesome
- **Server**: Apache/Nginx

## Instalasi

### Metode 1: Docker (Recommended)

1. Clone repository:
```bash
git clone <repository-url>
cd perpustakaan-system
```

2. Jalankan dengan Docker Compose:
```bash
docker-compose up -d
```

3. Akses aplikasi di:
```
http://localhost:8080
```

4. Login dengan akun default:
- **Admin**: username: `admin`, password: `password`
- **User**: username: `user`, password: `password`

### Metode 2: Manual Installation

1. **Persiapkan Environment**:
   - PHP 8.1 atau lebih baru
   - MySQL 8.0 atau lebih baru
   - Apache/Nginx web server

2. **Setup Database**:
```sql
CREATE DATABASE perpustakaan_inventaris;
USE perpustakaan_inventaris;
SOURCE database.sql;
```

3. **Konfigurasi Database**:
   Edit file `config/database.php` dan sesuaikan kredensial:
```php
private $host = "localhost";
private $db_name = "perpustakaan_inventaris";
private $username = "root";
private $password = "";
```

4. **Setup Web Server**:
   - Point document root ke folder project
   - Pastikan mod_rewrite enabled untuk Apache
   - Konfigurasi .htaccess sudah tersedia

5. **Akses Aplikasi**:
```
http://localhost/perpustakaan-system
```

## Struktur Folder

```
perpustakaan-system/
├── config/                 # Konfigurasi aplikasi
│   ├── config.php         # Konfigurasi umum
│   └── database.php       # Konfigurasi database
├── includes/              # File-file include
│   ├── header.php         # Header template
│   ├── footer.php         # Footer template
│   ├── functions.php      # Fungsi umum
│   └── auth.php           # Autentikasi
├── assets/                # Aset statis
│   ├── css/              # File CSS
│   ├── js/               # File JavaScript
│   └── images/           # Gambar
├── uploads/               # Upload files
├── logs/                  # Log files
├── database.sql          # Database schema
├── docker-compose.yml    # Docker configuration
└── README.md             # Dokumentasi
```

## Konfigurasi

### Environment Variables

Copy `.env.example` ke `.env` dan sesuaikan:

```env
APP_NAME="Inventaris Perpustakaan"
APP_URL=http://localhost:8080
DB_HOST=localhost
DB_NAME=perpustakaan_inventaris
DB_USER=root
DB_PASS=
```

### Security Configuration

Edit `config/config.php` untuk mengatur:

- **Session Lifetime**: Lama hidup session (default: 3600 detik)
- **CSRF Token Length**: Panjang token CSRF (default: 32)
- **Password Reset Expire**: Lama berlaku reset password (default: 3600 detik)
- **Late Fine**: Denda keterlambatan per hari (default: Rp 1000)

## Penggunaan

### Login dan Role

1. **Administrator (Admin)**:
   - Akses penuh ke semua fitur
   - Manajemen user dan sistem
   - Laporan dan statistik

2. **Pustakawan (Librarian)**:
   - Manajemen buku dan peminjaman
   - Proses pengembalian
   - Laporan terbatas

3. **User Biasa (User)**:
   - Melihat daftar buku
   - Melihat riwayat peminjaman
   - Akses terbatas

### Fitur Utama

#### Manajemen Buku
- Tambah/edit/hapus buku
- Import data dari CSV
- Cetak barcode
- Tracking kondisi buku

#### Peminjaman
- Scan barcode untuk peminjaman
- Validasi ketersediaan buku
- Pencatatan otomatis
- Notifikasi jatuh tempo

#### Pengembalian
- Proses pengembalian dengan scan
- Perhitungan denda otomatis
- Update kondisi buku
- Update stok ketersediaan

#### Laporan
- Dashboard dengan grafik
- Laporan peminjaman per periode
- Statistik penggunaan buku
- Export data ke CSV/PDF

## Keamanan

### Implementasi Keamanan

1. **SQL Injection Prevention**:
   - Menggunakan PDO prepared statements
   - Parameter binding untuk semua query
   - Input sanitization

2. **XSS Prevention**:
   - Output escaping dengan `htmlspecialchars()`
   - CSRF tokens untuk semua form
   - Content Security Policy headers

3. **CSRF Protection**:
   - Token generation untuk setiap form
   - Token validation di server-side
   - Double submit cookie pattern

4. **Session Security**:
   - Session regeneration setelah login
   - Secure session configuration
   - Session timeout handling

5. **Password Security**:
   - Password hashing dengan bcrypt
   - Minimum password requirements
   - Secure password reset flow

### Best Practices

- Selalu gunakan HTTPS di production
- Update dependencies secara berkala
- Implementasi rate limiting
- Backup database secara rutin
- Monitor log aktivitas

## Troubleshooting

### Masalah Umum

1. **Database Connection Error**:
   - Cek kredensial database
   - Pastikan MySQL service running
   - Verifikasi host dan port

2. **Permission Denied**:
   - Cek file permissions (644 untuk files, 755 untuk directories)
   - Pastikan web server punya akses

3. **Session Issues**:
   - Cek session save path
   - Pastikan disk space tersedia
   - Verifikasi session configuration

4. **CSRF Token Error**:
   - Clear browser cache
   - Cek session timeout
   - Pastikan JavaScript enabled

### Log dan Debugging

- Error log: `logs/error.log`
- Access log: `logs/access.log`
- Database queries dapat dilihat dengan mengaktifkan query log

## Maintenance

### Backup

1. **Database Backup**:
```bash
mysqldump -u root -p perpustakaan_inventaris > backup_$(date +%Y%m%d).sql
```

2. **File Backup**:
```bash
tar -czf backup_$(date +%Y%m%d).tar.gz /path/to/application
```

### Update

1. Backup database dan files
2. Update kode dari repository
3. Jalankan database migrations jika ada
4. Test functionality
5. Clear cache jika diperlukan

## Kontribusi

1. Fork repository
2. Buat branch untuk fitur baru
3. Commit changes dengan pesan yang jelas
4. Push ke branch
5. Buat Pull Request

## Lisensi

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

Untuk pertanyaan atau masalah:
- Email: support@perpustakaan.local
- Documentation: docs.perpustakaan.local
- Issues: GitHub Issues

---

**Dikembangkan dengan ❤️ untuk perpustakaan**