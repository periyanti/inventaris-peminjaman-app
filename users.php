<?php
session_start();
require_once 'includes/header.php';
require_once 'config/database.php';

// Redirect jika belum login atau bukan admin
if (!is_logged_in() || !has_role('admin')) {
    set_flash_message('error', 'Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.');
    redirect('index.php');
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('error', 'Token tidak valid');
        redirect('users.php');
    }
    
    try {
        if (isset($_POST['add'])) {
            // Add new user
            $username = sanitize_input($_POST['username']);
            $email = sanitize_input($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $full_name = sanitize_input($_POST['full_name']);
            $phone = sanitize_input($_POST['phone']);
            $role_id = (int)$_POST['role_id'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Validasi input
            if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
                set_flash_message('error', 'Semua field bertanda * harus diisi');
                redirect('users.php?action=add');
            }
            
            if (!is_valid_email($email)) {
                set_flash_message('error', 'Format email tidak valid');
                redirect('users.php?action=add');
            }
            
            if ($password !== $confirm_password) {
                set_flash_message('error', 'Password dan konfirmasi password tidak cocok');
                redirect('users.php?action=add');
            }
            
            if (strlen($password) < 6) {
                set_flash_message('error', 'Password minimal 6 karakter');
                redirect('users.php?action=add');
            }
            
            // Cek duplikasi username/email
            $check_query = "SELECT COUNT(*) FROM users WHERE username = ? OR email = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$username, $email]);
            
            if ($check_stmt->fetchColumn() > 0) {
                set_flash_message('error', 'Username atau email sudah terdaftar');
                redirect('users.php?action=add');
            }
            
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (username, email, password_hash, role_id, full_name, phone, is_active) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$username, $email, $password_hash, $role_id, $full_name, $phone, $is_active]);
            
            $user_id = $db->lastInsertId();
            log_activity('create', 'users', $user_id, "Menambahkan user baru: $username");
            set_flash_message('success', 'User berhasil ditambahkan');
            redirect('users.php');
            
        } elseif (isset($_POST['update'])) {
            // Update user
            $id = (int)$_POST['id'];
            $username = sanitize_input($_POST['username']);
            $email = sanitize_input($_POST['email']);
            $full_name = sanitize_input($_POST['full_name']);
            $phone = sanitize_input($_POST['phone']);
            $role_id = (int)$_POST['role_id'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Validasi input
            if (empty($username) || empty($email) || empty($full_name)) {
                set_flash_message('error', 'Semua field bertanda * harus diisi');
                redirect('users.php?action=edit&id=' . $id);
            }
            
            if (!is_valid_email($email)) {
                set_flash_message('error', 'Format email tidak valid');
                redirect('users.php?action=edit&id=' . $id);
            }
            
            // Cek duplikasi username/email (kecuali untuk record yang sedang diupdate)
            $check_query = "SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$username, $email, $id]);
            
            if ($check_stmt->fetchColumn() > 0) {
                set_flash_message('error', 'Username atau email sudah terdaftar');
                redirect('users.php?action=edit&id=' . $id);
            }
            
            $query = "UPDATE users SET username = ?, email = ?, role_id = ?, full_name = ?, phone = ?, is_active = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$username, $email, $role_id, $full_name, $phone, $is_active, $id]);
            
            log_activity('update', 'users', $id, "Memperbarui data user: $username");
            set_flash_message('success', 'User berhasil diperbarui');
            redirect('users.php');
            
        } elseif (isset($_POST['delete'])) {
            // Delete user
            $id = (int)$_POST['id'];
            
            // Prevent deleting own account
            if ($id == $_SESSION['user_id']) {
                set_flash_message('error', 'Anda tidak dapat menghapus akun sendiri');
                redirect('users.php');
            }
            
            // Prevent deleting admin account (optional)
            $check_query = "SELECT role_id FROM users WHERE id = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$id]);
            $user_role = $check_stmt->fetch();
            
            if ($user_role && $user_role['role_id'] == 1) {
                set_flash_message('error', 'Akun administrator tidak dapat dihapus');
                redirect('users.php');
            }
            
            $query = "DELETE FROM users WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            
            log_activity('delete', 'users', $id, 'Menghapus user');
            set_flash_message('success', 'User berhasil dihapus');
            redirect('users.php');
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Terjadi kesalahan: ' . $e->getMessage());
        redirect('users.php');
    }
}

// Get roles for dropdown
$roles = [];
try {
    $stmt = $db->prepare("SELECT id, name, description FROM roles ORDER BY id");
    $stmt->execute();
    $roles = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash_message('error', 'Gagal memuat data roles');
}

// Get single user for edit
$user = null;
if ($action === 'edit' && $id) {
    try {
        $query = "SELECT u.*, r.name as role_name FROM users u 
                  LEFT JOIN roles r ON u.role_id = r.id 
                  WHERE u.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            set_flash_message('error', 'User tidak ditemukan');
            redirect('users.php');
        }
        
        // Prevent editing own account role
        if ($user['id'] == $_SESSION['user_id'] && $user['role_id'] == 1) {
            // Allow editing own profile but with restrictions
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Gagal memuat data user');
        redirect('users.php');
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$items_per_page = ITEMS_PER_PAGE;

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($role_filter) {
    $where_conditions[] = "u.role_id = ?";
    $params[] = $role_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "u.is_active = ?";
    $params[] = $status_filter;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total users
try {
    $count_query = "SELECT COUNT(*) as total FROM users u $where_clause";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetch()['total'];
    
    $pagination = get_pagination($total_items, $page, $items_per_page);
    
    // Get users for current page
    $query = "SELECT u.*, r.name as role_name 
              FROM users u 
              LEFT JOIN roles r ON u.role_id = r.id 
              $where_clause 
              ORDER BY u.created_at DESC 
              LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    $params = array_merge($params, [$items_per_page, $pagination['offset']]);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash_message('error', 'Gagal memuat data: ' . $e->getMessage());
    $users = [];
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Manajemen User</h1>
    <p class="text-gray-600 mt-1">Kelola akun pengguna dalam sistem</p>
</div>

<?php if ($action === 'list'): ?>
<!-- Search and Filter -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" class="flex flex-wrap gap-4">
        <div class="flex-1 min-w-48">
            <input type="text" 
                   name="search" 
                   placeholder="Cari berdasarkan username, email, atau nama..." 
                   value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <div class="min-w-32">
            <select name="role" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Semua Role</option>
                <?php foreach ($roles as $role): ?>
                <option value="<?php echo $role['id']; ?>" <?php echo $role_filter == $role['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($role['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="min-w-32">
            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Semua Status</option>
                <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Aktif</option>
                <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Nonaktif</option>
            </select>
        </div>
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-search mr-2"></i>Cari
        </button>
        <a href="users.php" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-refresh mr-2"></i>Reset
        </a>
    </form>
</div>

<!-- Action Buttons -->
<div class="flex justify-between items-center mb-6">
    <div class="text-sm text-gray-600">
        Menampilkan <?php echo count($users); ?> dari <?php echo number_format($total_items); ?> user
    </div>
    <a href="users.php?action=add" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
        <i class="fas fa-plus mr-2"></i>Tambah User
    </a>
</div>

<!-- Users Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Lengkap</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Terakhir Login</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($users as $user): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['username']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                              <?php echo $user['role_id'] == 1 ? 'bg-red-100 text-red-800' : 
                                        ($user['role_id'] == 2 ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'); ?>">
                            <?php echo htmlspecialchars($user['role_name']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                              <?php echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $user['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-'; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" 
                               class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="users.php?action=view&id=<?php echo $user['id']; ?>" 
                               class="text-green-600 hover:text-green-900">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Apakah Anda yakin ingin menghapus user ini?')">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="delete" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($pagination['total_pages'] > 1): ?>
<div class="flex items-center justify-between mt-6">
    <div class="text-sm text-gray-700">
        Halaman <?php echo $page; ?> dari <?php echo $pagination['total_pages']; ?>
    </div>
    <div class="flex space-x-2">
        <?php if ($page > 1): ?>
        <a href="users.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
        <a href="users.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
           class="px-3 py-2 <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-lg hover:bg-blue-700 transition-colors">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $pagination['total_pages']): ?>
        <a href="users.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-6">
        <?php echo $action === 'add' ? 'Tambah User Baru' : 'Edit User'; ?>
    </h2>
    
    <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <?php if ($action === 'edit'): ?>
        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username *</label>
                <input type="text" 
                       name="username" 
                       id="username" 
                       value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       required
                       <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
            </div>
            
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                <input type="email" 
                       name="email" 
                       id="email" 
                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       required>
            </div>
            
            <div class="md:col-span-2">
                <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap *</label>
                <input type="text" 
                       name="full_name" 
                       id="full_name" 
                       value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       required>
            </div>
            
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">No. Telepon</label>
                <input type="tel" 
                       name="phone" 
                       id="phone" 
                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <div>
                <label for="role_id" class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                <select name="role_id" id="role_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Pilih Role</option>
                    <?php foreach ($roles as $role): ?>
                    <option value="<?php echo $role['id']; ?>" 
                            <?php echo ($user['role_id'] ?? '') == $role['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($role['name']); ?> - <?php echo htmlspecialchars($role['description']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($action === 'add'): ?>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                <input type="password" 
                       name="password" 
                       id="password" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       required
                       minlength="6">
            </div>
            
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password *</label>
                <input type="password" 
                       name="confirm_password" 
                       id="confirm_password" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       required
                       minlength="6">
            </div>
            <?php endif; ?>
        </div>
        
        <div class="flex items-center">
            <input type="checkbox" 
                   name="is_active" 
                   id="is_active" 
                   <?php echo ($action === 'add' || ($user['is_active'] ?? 0)) ? 'checked' : ''; ?>
                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
            <label for="is_active" class="ml-2 block text-sm text-gray-700">
                Akun aktif (dapat login ke sistem)
            </label>
        </div>
        
        <div class="flex justify-end space-x-3 pt-6">
            <a href="users.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-times mr-2"></i>Batal
            </a>
            <button type="submit" 
                    name="<?php echo $action; ?>" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-save mr-2"></i><?php echo $action === 'add' ? 'Simpan' : 'Update'; ?>
            </button>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $id): ?>
<!-- View User Details -->
<?php
// Get user details
if (!$user) {
    try {
        $query = "SELECT u.*, r.name as role_name FROM users u 
                  LEFT JOIN roles r ON u.role_id = r.id 
                  WHERE u.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            set_flash_message('error', 'User tidak ditemukan');
            redirect('users.php');
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Gagal memuat data user');
        redirect('users.php');
    }
}
?>

<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-start mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Detail User</h2>
        <a href="users.php" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Kembali
        </a>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Username</h3>
                <p class="mt-1 text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($user['username']); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Email</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Nama Lengkap</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">No. Telepon</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo htmlspecialchars($user['phone'] ?: '-'); ?></p>
            </div>
        </div>
        
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Role</h3>
                <span class="mt-1 inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                      <?php echo $user['role_id'] == 1 ? 'bg-red-100 text-red-800' : 
                                ($user['role_id'] == 2 ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'); ?>">
                    <?php echo htmlspecialchars($user['role_name']); ?>
                </span>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Status Akun</h3>
                <span class="mt-1 inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                      <?php echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo $user['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                </span>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Tanggal Registrasi</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Terakhir Login</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-'; ?></p>
            </div>
        </div>
    </div>
    
    <div class="flex justify-end space-x-3 mt-8 pt-6 border-t">
        <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" 
           class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-edit mr-2"></i>Edit
        </a>
        <a href="users.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Kembali
        </a>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>