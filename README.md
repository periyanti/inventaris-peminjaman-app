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