<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

class Auth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function login($username, $password, $remember_me = false) {
        try {
            $query = "SELECT u.*, r.name as role_name FROM users u 
                      LEFT JOIN roles r ON u.role_id = r.id 
                      WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role_name'];
                $_SESSION['full_name'] = $user['full_name'];
                
                $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $update_stmt = $this->db->prepare($update_query);
                $update_stmt->execute([$user['id']]);
                
                if ($remember_me) {
                    $this->set_remember_me($user['id']);
                }
                
                log_activity('login', 'users', $user['id'], 'User berhasil login');
                
                return ['success' => true, 'message' => 'Login berhasil'];
            }
            
            return ['success' => false, 'message' => 'Username atau password salah'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
    
    public function logout() {
        $user_id = $_SESSION['user_id'] ?? null;
        
        if ($user_id) {
            log_activity('logout', 'users', $user_id, 'User logout');
        }
        
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', time() - 3600, '/', '', true, true);
        }
        
        $_SESSION = [];
        session_destroy();
        
        return true;
    }
    
    private function set_remember_me($user_id) {
        $token = bin2hex(random_bytes(32));
        $expires = time() + (86400 * 30);
        
        $query = "UPDATE users SET remember_token = ? WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$token, $user_id]);
        
        setcookie('remember_me', $token, $expires, '/', '', true, true);
    }
    
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
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role_name'];
            $_SESSION['full_name'] = $user['full_name'];
            
            return true;
        }
        
        return false;
    }
    
    public function register($data) {
        try {
            $check_query = "SELECT COUNT(*) FROM users WHERE username = ? OR email = ?";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->execute([$data['username'], $data['email']]);
            
            if ($check_stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Username atau email sudah terdaftar'];
            }
            
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (username, email, password_hash, role_id, full_name, phone) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $data['username'],
                $data['email'],
                $password_hash,
                2,
                $data['full_name'],
                $data['phone'] ?? null
            ]);
            
            $user_id = $this->db->lastInsertId();
            
            log_activity('register', 'users', $user_id, 'User baru terdaftar');
            
            return ['success' => true, 'message' => 'Registrasi berhasil', 'user_id' => $user_id];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
    
    public function reset_password($email) {
        try {
            $query = "SELECT id FROM users WHERE email = ? AND is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                $token = bin2hex(random_bytes(PASSWORD_RESET_TOKEN_LENGTH));
                $expires_at = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRE);
                
                $update_query = "UPDATE users SET remember_token = ? WHERE id = ?";
                $update_stmt = $this->db->prepare($update_query);
                $update_stmt->execute([$token, $user['id']]);
                
                log_activity('password_reset_request', 'users', $user['id'], 'Permintaan reset password');
                
                return ['success' => true, 'message' => 'Link reset password telah dikirim ke email'];
            }
            
            return ['success' => false, 'message' => 'Email tidak ditemukan'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
}
?>