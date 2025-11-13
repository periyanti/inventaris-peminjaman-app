<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

class Auth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Fungsi login
    public function login($username, $password, $remember_me = false) {
        try {
            // Cari user berdasarkan username atau email
            $query = "SELECT u.*, r.name as role_name FROM users u 
                      LEFT JOIN roles r ON u.role_id = r.id 
                      WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session ID untuk keamanan
                session_regenerate_id(true);
                
                // Set session data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role_name'];
                $_SESSION['full_name'] = $user['full_name'];
                
                // Update last login
                $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $update_stmt = $this->db->prepare($update_query);
                $update_stmt->execute([$user['id']]);
                
                // Handle remember me
                if ($remember_me) {
                    $this->set_remember_me($user['id']);
                }
                
                // Log aktivitas
                log_activity('login', 'users', $user['id'], 'User berhasil login');
                
                return ['success' => true, 'message' => 'Login berhasil'];
            }
            
            return ['success' => false, 'message' => 'Username atau password salah'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
    
    // Fungsi logout
    public function logout() {
        $user_id = $_SESSION['user_id'] ?? null;
        
        // Log aktivitas
        if ($user_id) {
            log_activity('logout', 'users', $user_id, 'User logout');
        }
        
        // Hapus remember me cookie
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', time() - 3600, '/', '', true, true);
        }
        
        // Hapus session
        $_SESSION = [];
        session_destroy();
        
        return true;
    }
    
    // Fungsi untuk set remember me
    private function set_remember_me($user_id) {
        $token = bin2hex(random_bytes(32));
        $expires = time() + (86400 * 30); // 30 hari
        
        // Update token di database
        $query = "UPDATE users SET remember_token = ? WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$token, $user_id]);
        
        // Set cookie
        setcookie('remember_me', $token, $expires, '/', '', true, true);
    }
    
    // Fungsi untuk cek remember me
    public function check_remember_me() {
        if (!isset($_COOKIE['remember_me']) || is_logged_in()) {
            return false;
        }
        
        $token = $_COOKIE['remember_me'];
        
        $query = "SELECT u.*, r.name as role_name FROM users u 
                  LEFT JOIN roles r ON u.role_id = r.id 
                  WHERE u.remember_token = ? AND u.is_active = 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Regenerate session ID
            session_regenerate_id(true);
            
            // Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role_name'];
            $_SESSION['full_name'] = $user['full_name'];
            
            return true;
        }
        
        return false;
    }
    
    // Fungsi untuk registrasi user baru
    public function register($data) {
        try {
            // Cek username/email sudah ada
            $check_query = "SELECT COUNT(*) FROM users WHERE username = ? OR email = ?";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->execute([$data['username'], $data['email']]);
            
            if ($check_stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Username atau email sudah terdaftar'];
            }
            
            // Hash password
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert user baru
            $query = "INSERT INTO users (username, email, password_hash, role_id, full_name, phone) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $data['username'],
                $data['email'],
                $password_hash,
                2, // role user
                $data['full_name'],
                $data['phone'] ?? null
            ]);
            
            $user_id = $this->db->lastInsertId();
            
            // Log aktivitas
            log_activity('register', 'users', $user_id, 'User baru terdaftar');
            
            return ['success' => true, 'message' => 'Registrasi berhasil', 'user_id' => $user_id];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
    
    // Fungsi untuk reset password
    public function reset_password($email) {
        try {
            // Cari user
            $query = "SELECT id FROM users WHERE email = ? AND is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate reset token
                $token = bin2hex(random_bytes(PASSWORD_RESET_TOKEN_LENGTH));
                $expires_at = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRE);
                
                // Simpan token (sederhana - dalam produksi gunakan tabel terpisah)
                $update_query = "UPDATE users SET remember_token = ? WHERE id = ?";
                $update_stmt = $this->db->prepare($update_query);
                $update_stmt->execute([$token, $user['id']]);
                
                // Log aktivitas
                log_activity('password_reset_request', 'users', $user['id'], 'Permintaan reset password');
                
                // Kirim email (implementasi email service diperlukan)
                return ['success' => true, 'message' => 'Link reset password telah dikirim ke email'];
            }
            
            return ['success' => false, 'message' => 'Email tidak ditemukan'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
}
?>