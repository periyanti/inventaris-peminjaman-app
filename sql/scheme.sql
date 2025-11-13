CREATE DATABASE IF NOT EXISTS perpustakaan_inventaris;
USE perpustakaan_inventaris;

CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    remember_token VARCHAR(255),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
);

CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    barcode VARCHAR(50) UNIQUE,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(100),
    category_id INT,
    supplier_id INT,
    isbn VARCHAR(20),
    description TEXT,
    quantity_total INT NOT NULL DEFAULT 1,
    quantity_available INT NOT NULL DEFAULT 1,
    location VARCHAR(50),
    item_condition ENUM('baru','baik','rusak_ringan','rusak_berat') DEFAULT 'baik',
    purchase_date DATE,
    purchase_price DECIMAL(10,2),
    cover_image VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

CREATE TABLE loans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    loan_code VARCHAR(20) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    loan_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    status ENUM('dipinjam', 'dikembalikan', 'terlambat', 'hilang') DEFAULT 'dipinjam',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);


CREATE TABLE returns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    loan_id INT NOT NULL,
    return_date DATE NOT NULL,
    condition ENUM('baik', 'rusak_ringan', 'rusak_berat', 'hilang') DEFAULT 'baik',
    late_days INT DEFAULT 0,
    fine_amount DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);


CREATE TABLE activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    table_affected VARCHAR(50),
    record_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE csrf_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    token VARCHAR(255) UNIQUE NOT NULL,
    user_id INT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO roles (name, description) VALUES
('admin', 'Administrator dengan akses penuh ke semua fitur sistem'),
('user', 'User biasa dengan akses terbatas untuk melihat dan meminjam buku'),
('librarian', 'Pustakawan dengan akses manajemen buku dan peminjaman');

-- Insert admin default
INSERT INTO users (username, email, password_hash, role_id, full_name, is_active) VALUES
('admin', 'admin@perpustakaan.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'Administrator', TRUE),
('user', 'user@perpustakaan.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'User Demo', TRUE);

INSERT INTO categories (name, description) VALUES
('Fiksi', 'Buku cerita, novel, dan karya fiksi lainnya'),
('Non-Fiksi', 'Buku ilmiah, edukatif, dan referensi'),
('Referensi', 'Kamus, ensiklopedia, dan buku referensi'),
('Jurnal', 'Jurnal ilmiah, majalah, dan publikasi berkala'),
('Komik', 'Komik, manga, dan graphic novel'),
('Pelajaran', 'Buku pelajaran untuk sekolah dan universitas');

-- Insert supplier default
INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES
('Gramedia', 'Sales Department', '021-1234567', 'sales@gramedia.com', 'Jakarta, Indonesia'),
('Erlangga', 'Marketing Team', '021-2345678', 'info@erlangga.co.id', 'Jakarta, Indonesia'),
('Mizan Publishing', 'Customer Service', '021-3456789', 'info@mizan.com', 'Bandung, Indonesia');

-- Insert beberapa buku sample
INSERT INTO items (barcode, title, author, category_id, supplier_id, isbn, quantity_total, quantity_available, location) VALUES
('BK001', 'Pemrograman PHP untuk Pemula', 'Budi Santoso', 2, 1, '978-123-456789-0', 5, 4, 'Rak A1'),
('BK002', 'Dasar-Dasar MySQL Database', 'Siti Rahayu', 2, 2, '978-234-567890-1', 3, 3, 'Rak A2'),
('BK003', 'Harry Potter dan Batu Bertuah', 'J.K. Rowling', 1, 1, '978-345-678901-2', 7, 6, 'Rak B1'),
('BK004', 'Kamus Bahasa Indonesia Lengkap', 'Tim Penyusun', 3, 1, '978-456-789012-3', 2, 2, 'Rak C1'),
('BK005', 'JavaScript Modern untuk Developer', 'Ahmad Wijaya', 2, 3, '978-567-890123-4', 4, 3, 'Rak A3'),
('BK006', 'Naruto Volume 1', 'Masashi Kishimoto', 5, 3, '978-678-901234-5', 10, 9, 'Rak D1');

-- Indexes untuk items
CREATE INDEX idx_items_barcode ON items(barcode);
CREATE INDEX idx_items_category ON items(category_id);
CREATE INDEX idx_items_supplier ON items(supplier_id);
CREATE INDEX idx_items_active ON items(is_active);

-- Indexes untuk loans
CREATE INDEX idx_loans_user ON loans(user_id);
CREATE INDEX idx_loans_item ON loans(item_id);
CREATE INDEX idx_loans_status ON loans(status);
CREATE INDEX idx_loans_due_date ON loans(due_date);

-- Indexes untuk activity_log
CREATE INDEX idx_activity_log_user ON activity_log(user_id);
CREATE INDEX idx_activity_log_created ON activity_log(created_at);
CREATE INDEX idx_activity_log_action ON activity_log(action);

-- Indexes untuk returns
CREATE INDEX idx_returns_loan ON returns(loan_id);
CREATE INDEX idx_returns_date ON returns(return_date);

-- View untuk dashboard statistics
CREATE VIEW dashboard_stats AS
SELECT 
    (SELECT COUNT(*) FROM items WHERE is_active = 1) as total_books,
    (SELECT SUM(quantity_total) FROM items WHERE is_active = 1) as total_quantity,
    (SELECT SUM(quantity_available) FROM items WHERE is_active = 1) as available_quantity,
    (SELECT COUNT(*) FROM loans WHERE status = 'dipinjam') as active_loans,
    (SELECT COUNT(*) FROM loans WHERE due_date < CURDATE() AND status = 'dipinjam') as overdue_loans,
    (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users,
    (SELECT COUNT(*) FROM returns) as total_returns,
    (SELECT COALESCE(SUM(fine_amount), 0) FROM returns WHERE fine_amount > 0) as total_fines;

-- View untuk laporan peminjaman
CREATE VIEW loan_report AS
SELECT 
    l.id,
    l.loan_code,
    l.loan_date,
    l.due_date,
    l.return_date,
    l.status,
    l.notes,
    u.username,
    u.full_name as user_name,
    i.title as book_title,
    i.barcode as book_barcode,
    i.author as book_author,
    c.name as category_name,
    s.name as supplier_name
FROM loans l
JOIN users u ON l.user_id = u.id
JOIN items i ON l.item_id = i.id
LEFT JOIN categories c ON i.category_id = c.id
LEFT JOIN suppliers s ON i.supplier_id = s.id;

-- View untuk laporan pengembalian
CREATE VIEW return_report AS
SELECT 
    r.id,
    r.return_date,
    r.condition,
    r.late_days,
    r.fine_amount,
    r.notes,
    l.loan_code,
    l.loan_date,
    l.due_date,
    u.username,
    u.full_name as user_name,
    i.title as book_title,
    i.barcode as book_barcode
FROM returns r
JOIN loans l ON r.loan_id = l.id
JOIN users u ON l.user_id = u.id
JOIN items i ON l.item_id = i.id;

-- Trigger untuk update quantity_available saat peminjaman
DELIMITER //
CREATE TRIGGER after_loan_insert
AFTER INSERT ON loans
FOR EACH ROW
BEGIN
    UPDATE items 
    SET quantity_available = quantity_available - 1 
    WHERE id = NEW.item_id;
END//

-- Trigger untuk update quantity_available saat pengembalian
CREATE TRIGGER after_return_insert
AFTER INSERT ON returns
FOR EACH ROW
BEGIN
    UPDATE items 
    SET quantity_available = quantity_available + 1 
    WHERE id = (SELECT item_id FROM loans WHERE id = NEW.loan_id);
END//

-- Trigger untuk update status loan menjadi terlambat
CREATE TRIGGER update_overdue_loans
BEFORE UPDATE ON loans
FOR EACH ROW
BEGIN
    IF NEW.due_date < CURDATE() AND NEW.status = 'dipinjam' THEN
        SET NEW.status = 'terlambat';
    END IF;
END//
DELIMITER ;

-- Procedure untuk membuat peminjaman baru
DELIMITER //
CREATE PROCEDURE create_loan(
    IN p_user_id INT,
    IN p_item_id INT,
    IN p_loan_date DATE,
    IN p_due_date DATE,
    IN p_notes TEXT
)
BEGIN
    DECLARE v_loan_code VARCHAR(20);
    DECLARE v_available_qty INT;
    
    -- Cek ketersediaan buku
    SELECT quantity_available INTO v_available_qty 
    FROM items 
    WHERE id = p_item_id AND is_active = 1;
    
    IF v_available_qty <= 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Buku tidak tersedia untuk dipinjam';
    END IF;
    
    -- Generate loan code
    SET v_loan_code = CONCAT('LN', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(FLOOR(RAND() * 9999) + 1, 4, '0'));
    
    -- Insert loan record
    INSERT INTO loans (loan_code, user_id, item_id, loan_date, due_date, notes) 
    VALUES (v_loan_code, p_user_id, p_item_id, p_loan_date, p_due_date, p_notes);
    
    -- Update quantity_available
    UPDATE items 
    SET quantity_available = quantity_available - 1 
    WHERE id = p_item_id;
    
    SELECT v_loan_code as loan_code, LAST_INSERT_ID() as loan_id;
END//

-- Procedure untuk membuat pengembalian
CREATE PROCEDURE create_return(
    IN p_loan_id INT,
    IN p_return_date DATE,
    IN p_condition ENUM('baik', 'rusak_ringan', 'rusak_berat', 'hilang'),
    IN p_notes TEXT
)
BEGIN
    DECLARE v_item_id INT;
    DECLARE v_due_date DATE;
    DECLARE v_late_days INT;
    DECLARE v_fine_amount DECIMAL(10,2);
    
    -- Get loan details
    SELECT item_id, due_date INTO v_item_id, v_due_date
    FROM loans 
    WHERE id = p_loan_id AND status = 'dipinjam';
    
    IF v_item_id IS NULL THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Peminjaman tidak valid atau sudah dikembalikan';
    END IF;
    
    -- Calculate late days and fine
    SET v_late_days = GREATEST(0, DATEDIFF(p_return_date, v_due_date));
    SET v_fine_amount = v_late_days * 1000; -- 1000 per hari
    
    -- Update loan status
    UPDATE loans 
    SET status = 'dikembalikan', return_date = p_return_date 
    WHERE id = p_loan_id;
    
    -- Insert return record
    INSERT INTO returns (loan_id, return_date, condition, late_days, fine_amount, notes) 
    VALUES (p_loan_id, p_return_date, p_condition, v_late_days, v_fine_amount, p_notes);
    
    -- Update item quantity
    UPDATE items 
    SET quantity_available = quantity_available + 1 
    WHERE id = v_item_id;
    
    SELECT LAST_INSERT_ID() as return_id, v_fine_amount as fine_amount;
END//
DELIMITER ;

INSERT INTO activity_log (user_id, action, table_affected, description) VALUES
(NULL, 'system_init', 'database', 'Database initialized with default data'),
(NULL, 'system_ready', 'database', 'System ready for use');

SELECT 'Database schema created successfully!' as message;
SELECT 'Default data inserted successfully!' as message;
SELECT 'You can now start using the library system!' as message;