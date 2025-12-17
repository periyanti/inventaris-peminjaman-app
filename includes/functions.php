<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

function show_flash_message() {
    $message = get_flash_message();
    if ($message) {
        $alert_class = $message['type'] === 'success' ? 'bg-emerald-100 border-emerald-400 text-emerald-700' : 'bg-rose-100 border-rose-400 text-rose-700';
        echo '<div class="border px-4 py-3 rounded relative mb-4 ' . $alert_class . '" role="alert">';
        echo '<span class="block sm:inline">' . htmlspecialchars($message['message']) . '</span>';
        echo '</div>';
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function has_role($role) {
    if (!is_logged_in()) return false;
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function generate_barcode() {
    return 'BK' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

function format_date($date) {
    return date('d/m/Y', strtotime($date));
}

function format_rupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function calculate_late_days($due_date, $return_date = null) {
    $due = new DateTime($due_date);
    $return = $return_date ? new DateTime($return_date) : new DateTime();
    
    if ($return->getTimestamp() <= $due->getTimestamp()) {
        return 0;
    }
    
    $interval = $due->diff($return);
    return (int)$interval->format('%a');
}

function log_activity($action, $table_affected = null, $record_id = null, $description = null) {
    global $pdo;

    if (!$pdo) {
        return;
    }

    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $query = "INSERT INTO activity_log (user_id, action, table_affected, record_id, description, ip_address, user_agent) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        $user_id,
        $action,
        $table_affected,
        $record_id,
        $description,
        $ip_address,
        $user_agent
    ]);
}

function get_pagination($total_items, $current_page, $items_per_page = ITEMS_PER_PAGE) {
    $total_pages = ceil($total_items / $items_per_page);
    $offset = ($current_page - 1) * $items_per_page;
    
    return [
        'total_pages' => $total_pages,
        'offset' => $offset,
        'current_page' => $current_page
    ];
}

function upload_file($file, $upload_dir = UPLOAD_PATH) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload gagal'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File terlalu besar'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
        return ['success' => false, 'message' => 'Tipe file tidak diizinkan'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $upload_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'filename' => $filename, 'path' => $upload_path];
    }
    
    return ['success' => false, 'message' => 'Gagal menyimpan file'];
}
?>