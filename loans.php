<?php
session_start();
require_once 'includes/header.php';
require_once 'config/database.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('error', 'Token tidak valid');
        redirect('loans.php');
    }
    
    try {
        if (isset($_POST['add'])) {
            $user_id = (int)$_POST['user_id'];
            $item_id = (int)$_POST['item_id'];
            $loan_date = $_POST['loan_date'];
            $due_date = $_POST['due_date'];
            $notes = sanitize_input($_POST['notes']);
            
            if (empty($user_id) || empty($item_id) || empty($loan_date) || empty($due_date)) {
                set_flash_message('error', 'Semua field bertanda * harus diisi');
                redirect('loans.php?action=add');
            }
            
            $check_query = "SELECT quantity_available FROM items WHERE id = ? AND is_active = 1";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$item_id]);
            $item = $check_stmt->fetch();
            
            if (!$item) {
                set_flash_message('error', 'Buku tidak ditemukan');
                redirect('loans.php?action=add');
            }
            
            if ($item['quantity_available'] <= 0) {
                set_flash_message('error', 'Buku tidak tersedia untuk dipinjam');
            }
            
            $check_loan_query = "SELECT COUNT(*) FROM loans WHERE user_id = ? AND item_id = ? AND status = 'dipinjam'";
            $check_loan_stmt = $db->prepare($check_loan_query);
            $check_loan_stmt->execute([$user_id, $item_id]);
            
            if ($check_loan_stmt->fetchColumn() > 0) {
                set_flash_message('error', 'User masih memiliki pinjaman aktif untuk buku ini');
            }
            
            $loan_code = 'LN' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $query = "INSERT INTO loans (loan_code, user_id, item_id, loan_date, due_date, notes) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$loan_code, $user_id, $item_id, $loan_date, $due_date, $notes]);
            
            $loan_id = $db->lastInsertId();
            
            $update_query = "UPDATE items SET quantity_available = quantity_available - 1 WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$item_id]);
            
            log_activity('create', 'loans', $loan_id, "Membuat peminjaman baru: $loan_code");
            set_flash_message('success', 'Peminjaman berhasil dicatat');
            
        } elseif (isset($_POST['update'])) {
            $id = (int)$_POST['id'];
            $loan_date = $_POST['loan_date'];
            $due_date = $_POST['due_date'];
            $notes = sanitize_input($_POST['notes']);
            
            $get_query = "SELECT * FROM loans WHERE id = ?";
            $get_stmt = $db->prepare($get_query);
            $get_stmt->execute([$id]);
            $current_loan = $get_stmt->fetch();
            
            if (!$current_loan) {
                set_flash_message('error', 'Data peminjaman tidak ditemukan');
                redirect('loans.php');
            }
            
            $query = "UPDATE loans SET loan_date = ?, due_date = ?, notes = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$loan_date, $due_date, $notes, $id]);
            
            log_activity('update', 'loans', $id, "Memperbarui data peminjaman: $current_loan[loan_code]");
            set_flash_message('success', 'Data peminjaman berhasil diperbarui');
            redirect('loans.php');
            
        } elseif (isset($_POST['delete'])) {
            $id = (int)$_POST['id'];
            
            $get_query = "SELECT l.*, i.title FROM loans l JOIN items i ON l.item_id = i.id WHERE l.id = ?";
            $get_stmt = $db->prepare($get_query);
            $get_stmt->execute([$id]);
            $loan = $get_stmt->fetch();
            
            if (!$loan) {
                set_flash_message('error', 'Data peminjaman tidak ditemukan');
            }
            
            if ($loan['status'] === 'dikembalikan') {
                set_flash_message('error', 'Peminjaman yang sudah dikembalikan tidak dapat dihapus');
            }
            
            $update_query = "UPDATE items SET quantity_available = quantity_available + 1 WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$loan['item_id']]);
            
            $query = "DELETE FROM loans WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            
            log_activity('delete', 'loans', $id, "Menghapus data peminjaman: $loan[loan_code]");
            set_flash_message('success', 'Data peminjaman berhasil dihapus');
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Terjadi kesalahan: ' . $e->getMessage());
        redirect('loans.php');
    }
}

$users_list = [];
$items_list = [];

try {
    $stmt = $db->prepare("SELECT id, username, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
    $stmt->execute();
    $users_list = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT id, title, barcode, quantity_available FROM items WHERE is_active = 1 AND quantity_available > 0 ORDER BY title");
    $stmt->execute();
    $items_list = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash_message('error', 'Gagal memuat data: ' . $e->getMessage());
}

$loan = null;
if ($action === 'edit' && $id) {
    try {
        $query = "SELECT l.*, u.username, u.full_name, i.title, i.barcode 
                  FROM loans l 
                  JOIN users u ON l.user_id = u.id 
                  JOIN items i ON l.item_id = i.id 
                  WHERE l.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $loan = $stmt->fetch();
        
        if (!$loan) {
            set_flash_message('error', 'Data peminjaman tidak ditemukan');
            redirect('loans.php');
        }
        
        if ($loan['status'] === 'dikembalikan') {
            set_flash_message('error', 'Peminjaman yang sudah dikembalikan tidak dapat diedit');
            redirect('loans.php');
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Gagal memuat data peminjaman');
        redirect('loans.php');
    }
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$items_per_page = ITEMS_PER_PAGE;

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(l.loan_code LIKE ? OR u.username LIKE ? OR u.full_name LIKE ? OR i.title LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter) {
    $where_conditions[] = "l.status = ?";
    $params[] = $status_filter;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    $count_query = "SELECT COUNT(*) as total FROM loans l 
                    JOIN users u ON l.user_id = u.id 
                    JOIN items i ON l.item_id = i.id 
                    $where_clause";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetch()['total'];
    
    $pagination = get_pagination($total_items, $page, $items_per_page);
    
    $query = "SELECT l.*, u.username, u.full_name, i.title, i.barcode 
              FROM loans l 
              JOIN users u ON l.user_id = u.id 
              JOIN items i ON l.item_id = i.id 
              $where_clause 
              ORDER BY l.created_at DESC 
              LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    $params = array_merge($params, [$items_per_page, $pagination['offset']]);
    $stmt->execute($params);
    $loans = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash_message('error', 'Gagal memuat data: ' . $e->getMessage());
    $loans = [];
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Manajemen Peminjaman</h1>
    <p class="text-gray-600 mt-1">Kelola data peminjaman buku dalam perpustakaan</p>
</div>

<?php if ($action === 'list'): ?>
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" class="flex flex-wrap gap-4">
        <div class="flex-1 min-w-48">
            <input type="text" 
                   name="search" 
                   placeholder="Cari berdasarkan kode, user, atau judul buku..." 
                   value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        </div>
        <div class="min-w-32">
            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <option value="">Semua Status</option>
                <option value="dipinjam" <?php echo $status_filter === 'dipinjam' ? 'selected' : ''; ?>>Dipinjam</option>
                <option value="dikembalikan" <?php echo $status_filter === 'dikembalikan' ? 'selected' : ''; ?>>Dikembalikan</option>
                <option value="terlambat" <?php echo $status_filter === 'terlambat' ? 'selected' : ''; ?>>Terlambat</option>
                <option value="hilang" <?php echo $status_filter === 'hilang' ? 'selected' : ''; ?>>Hilang</option>
            </select>
        </div>
        <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
            <i class="fas fa-search mr-2"></i>Cari
        </button>
        <a href="loans.php" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-refresh mr-2"></i>Reset
        </a>
    </form>
</div>

<div class="flex justify-between items-center mb-6">
    <div class="text-sm text-gray-600">
        Menampilkan <?php echo count($loans); ?> dari <?php echo number_format($total_items); ?> peminjaman
    </div>
    <a href="loans.php?action=add" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
        <i class="fas fa-plus mr-2"></i>Tambah Peminjaman
    </a>
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Buku</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Pinjam</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jatuh Tempo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($loans as $loan): 
                    $is_overdue = $loan['status'] === 'dipinjam' && strtotime($loan['due_date']) < time();
                    if ($is_overdue && $loan['status'] === 'dipinjam') {
                        $update_query = "UPDATE loans SET status = 'terlambat' WHERE id = ?";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->execute([$loan['id']]);
                        $loan['status'] = 'terlambat';
                    }
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($loan['loan_code']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($loan['full_name']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($loan['username']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($loan['title']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($loan['barcode']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo format_date($loan['loan_date']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <span class="<?php echo $is_overdue ? 'text-rose-600 font-semibold' : ''; ?>">
                            <?php echo format_date($loan['due_date']); ?>
                        </span>
                        <?php if ($is_overdue): ?>
                        <span class="ml-1 px-2 py-1 text-xs bg-rose-100 text-rose-800 rounded-full">
                            <?php echo calculate_late_days($loan['due_date']); ?> hari terlambat
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                              <?php echo $loan['status'] === 'dipinjam' ? 'bg-indigo-100 text-indigo-800' : 
                                        ($loan['status'] === 'dikembalikan' ? 'bg-emerald-100 text-emerald-800' : 
                                        ($loan['status'] === 'terlambat' ? 'bg-rose-100 text-rose-800' : 'bg-gray-100 text-gray-800')); ?>">
                            <?php echo ucfirst($loan['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <a href="loans.php?action=edit&id=<?php echo $loan['id']; ?>" 
                               class="text-indigo-600 hover:text-indigo-900 <?php echo $loan['status'] === 'dikembalikan' ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                <?php echo $loan['status'] === 'dikembalikan' ? 'onclick="return false;"' : ''; ?>>
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="loans.php?action=view&id=<?php echo $loan['id']; ?>" 
                               class="text-emerald-600 hover:text-emerald-900">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($loan['status'] !== 'dikembalikan'): ?>
                            <a href="returns.php?action=add&loan_id=<?php echo $loan['id']; ?>" 
                               class="text-violet-600 hover:text-violet-900">
                                <i class="fas fa-undo"></i>
                            </a>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Apakah Anda yakin ingin menghapus data peminjaman ini?')">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="id" value="<?php echo $loan['id']; ?>">
                                <button type="submit" name="delete" class="text-rose-600 hover:text-rose-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pagination['total_pages'] > 1): ?>
<div class="flex items-center justify-between mt-6">
    <div class="text-sm text-gray-700">
        Halaman <?php echo $page; ?> dari <?php echo $pagination['total_pages']; ?>
    </div>
    <div class="flex space-x-2">
        <?php if ($page > 1): ?>
        <a href="loans.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
        <a href="loans.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
           class="px-3 py-2 <?php echo $i == $page ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-lg hover:bg-indigo-700 transition-colors">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $pagination['total_pages']): ?>
        <a href="loans.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-6">
        <?php echo $action === 'add' ? 'Tambah Peminjaman Baru' : 'Edit Data Peminjaman'; ?>
    </h2>
    
    <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <?php if ($action === 'edit'): ?>
        <input type="hidden" name="id" value="<?php echo $loan['id']; ?>">
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="user_id" class="block text-sm font-medium text-gray-700 mb-2">Peminjam *</label>
                <select name="user_id" id="user_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="">Pilih Peminjam</option>
                    <?php foreach ($users_list as $user): ?>
                    <option value="<?php echo $user['id']; ?>" 
                            <?php echo ($loan['user_id'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['username'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="item_id" class="block text-sm font-medium text-gray-700 mb-2">Buku *</label>
                <select name="item_id" id="item_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="">Pilih Buku</option>
                    <?php foreach ($items_list as $item): ?>
                    <option value="<?php echo $item['id']; ?>" 
                            <?php echo ($loan['item_id'] ?? '') == $item['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($item['title'] . ' (' . $item['quantity_available'] . ' tersedia)'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="loan_date" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Pinjam *</label>
                <input type="date" 
                       name="loan_date" 
                       id="loan_date" 
                       value="<?php echo $loan['loan_date'] ?? date('Y-m-d'); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                       required>
            </div>
            
            <div>
                <label for="due_date" class="block text-sm font-medium text-gray-700 mb-2">Jatuh Tempo *</label>
                <input type="date" 
                       name="due_date" 
                       id="due_date" 
                       value="<?php echo $loan['due_date'] ?? date('Y-m-d', strtotime('+7 days')); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                       required>
            </div>
        </div>
        
        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
            <textarea name="notes" 
                      id="notes" 
                      rows="3"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                      placeholder="Catatan tambahan untuk peminjaman ini..."><?php echo htmlspecialchars($loan['notes'] ?? ''); ?></textarea>
        </div>
        
        <div class="flex justify-end space-x-3 pt-6">
            <a href="loans.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-times mr-2"></i>Batal
            </a>
            <button type="submit" 
                    name="<?php echo $action; ?>" 
                    class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                <i class="fas fa-save mr-2"></i><?php echo $action === 'add' ? 'Simpan' : 'Update'; ?>
            </button>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $id): ?>
<?php
if (!$loan) {
    try {
        $query = "SELECT l.*, u.username, u.full_name, u.email, u.phone, i.title, i.barcode, i.author, i.isbn 
                  FROM loans l 
                  JOIN users u ON l.user_id = u.id 
                  JOIN items i ON l.item_id = i.id 
                  WHERE l.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $loan = $stmt->fetch();
        
        if (!$loan) {
            set_flash_message('error', 'Data peminjaman tidak ditemukan');
            redirect('loans.php');
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Gagal memuat data peminjaman');
        redirect('loans.php');
    }
}

$late_days = 0;
if ($loan['status'] === 'dipinjam' || $loan['status'] === 'terlambat') {
    $late_days = calculate_late_days($loan['due_date']);
}
?>

<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-start mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Detail Peminjaman</h2>
        <a href="loans.php" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Kembali
        </a>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Kode Peminjaman</h3>
                <p class="mt-1 text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($loan['loan_code']); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Data Peminjam</h3>
                <div class="mt-1">
                    <p class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($loan['full_name']); ?></p>
                    <p class="text-sm text-gray-600">Username: <?php echo htmlspecialchars($loan['username']); ?></p>
                    <p class="text-sm text-gray-600">Email: <?php echo htmlspecialchars($loan['email']); ?></p>
                    <p class="text-sm text-gray-600">Telepon: <?php echo htmlspecialchars($loan['phone'] ?: '-'); ?></p>
                </div>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Data Buku</h3>
                <div class="mt-1">
                    <p class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($loan['title']); ?></p>
                    <p class="text-sm text-gray-600">Barcode: <?php echo htmlspecialchars($loan['barcode']); ?></p>
                    <p class="text-sm text-gray-600">Penulis: <?php echo htmlspecialchars($loan['author']); ?></p>
                    <p class="text-sm text-gray-600">ISBN: <?php echo htmlspecialchars($loan['isbn'] ?: '-'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Tanggal Pinjam</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo format_date($loan['loan_date']); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Jatuh Tempo</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo format_date($loan['due_date']); ?></p>
                <?php if ($late_days > 0): ?>
                <p class="text-sm text-rose-600 font-semibold"><?php echo $late_days; ?> hari terlambat</p>
                <?php endif; ?>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Status</h3>
                <span class="mt-1 inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                      <?php echo $loan['status'] === 'dipinjam' ? 'bg-indigo-100 text-indigo-800' : 
                                ($loan['status'] === 'dikembalikan' ? 'bg-emerald-100 text-emerald-800' : 
                                ($loan['status'] === 'terlambat' ? 'bg-rose-100 text-rose-800' : 'bg-gray-100 text-gray-800')); ?>">
                    <?php echo ucfirst($loan['status']); ?>
                </span>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Tanggal Pengembalian</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo $loan['return_date'] ? format_date($loan['return_date']) : '-'; ?></p>
            </div>
        </div>
    </div>
    
    <?php if ($loan['notes']): ?>
    <div class="mt-8">
        <h3 class="text-sm font-medium text-gray-500 mb-2">Catatan</h3>
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-gray-900"><?php echo nl2br(htmlspecialchars($loan['notes'])); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="flex justify-end space-x-3 mt-8 pt-6 border-t">
        <?php if ($loan['status'] !== 'dikembalikan'): ?>
        <a href="loans.php?action=edit&id=<?php echo $loan['id']; ?>" 
           class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
            <i class="fas fa-edit mr-2"></i>Edit
        </a>
        <a href="returns.php?action=add&loan_id=<?php echo $loan['id']; ?>" 
           class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors">
            <i class="fas fa-undo mr-2"></i>Proses Pengembalian
        </a>
        <?php endif; ?>
        <a href="loans.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Kembali
        </a>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>