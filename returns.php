<?php
session_start();
require_once 'includes/header.php';
require_once 'config/database.php';

// Redirect jika belum login
if (!is_logged_in()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$loan_id = $_GET['loan_id'] ?? null;

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('error', 'Token tidak valid');
        redirect('returns.php');
    }
    
    try {
        if (isset($_POST['add'])) {
            // Add new return
            $loan_id = (int)$_POST['loan_id'];
            $return_date = $_POST['return_date'];
            $item_condition = $_POST['condition']; // Tetap condition di form, tapi simpan sebagai item_condition
            $notes = sanitize_input($_POST['notes']);
            
            // Validasi input
            if (empty($loan_id) || empty($return_date) || empty($item_condition)) {
                set_flash_message('error', 'Semua field bertanda * harus diisi');
                redirect('returns.php?action=add');
            }
            
            // Get loan data
            $loan_query = "SELECT l.*, i.title, i.quantity_total, i.quantity_available 
                           FROM loans l 
                           JOIN items i ON l.item_id = i.id 
                           WHERE l.id = ? AND l.status = 'dipinjam'";
            $loan_stmt = $db->prepare($loan_query);
            $loan_stmt->execute([$loan_id]);
            $loan = $loan_stmt->fetch();
            
            if (!$loan) {
                set_flash_message('error', 'Data peminjaman tidak ditemukan atau sudah dikembalikan');
            }
            
            // Calculate late days and fine
            $late_days = calculate_late_days($loan['due_date'], $return_date);
            $fine_amount = $late_days * LATE_FINE_PER_DAY;
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Update loan status
                $update_loan_query = "UPDATE loans SET status = 'dikembalikan', return_date = ? WHERE id = ?";
                $update_loan_stmt = $db->prepare($update_loan_query);
                $update_loan_stmt->execute([$return_date, $loan_id]);
                
                // Update item quantity
                $update_item_query = "UPDATE items SET quantity_available = quantity_available + 1 WHERE id = ?";
                $update_item_stmt = $db->prepare($update_item_query);
                $update_item_stmt->execute([$loan['item_id']]);
                
                // Insert return record - PERBAIKAN DI SINI: ganti condition menjadi item_condition
                $insert_return_query = "INSERT INTO returns (loan_id, return_date, item_condition, late_days, fine_amount, notes) 
                                       VALUES (?, ?, ?, ?, ?, ?)";
                $insert_return_stmt = $db->prepare($insert_return_query);
                $insert_return_stmt->execute([$loan_id, $return_date, $item_condition, $late_days, $fine_amount, $notes]);
                
                // Commit transaction
                $db->commit();
                
                $return_id = $db->lastInsertId();
                log_activity('create', 'returns', $return_id, "Mencatat pengembalian untuk peminjaman: $loan[loan_code]");
                
                $message = 'Pengembalian berhasil dicatat';
                if ($fine_amount > 0) {
                    $message .= '. Denda terlambat: ' . format_rupiah($fine_amount);
                }
                set_flash_message('success', $message);
                
            } catch (Exception $e) {
                // Rollback transaction
                $db->rollBack();
                throw $e;
            }
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Terjadi kesalahan: ' . $e->getMessage());
    }
}

// Get returns data with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = $_GET['search'] ?? '';
$items_per_page = ITEMS_PER_PAGE;

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(l.loan_code LIKE ? OR u.username LIKE ? OR u.full_name LIKE ? OR i.title LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total returns
try {
    $count_query = "SELECT COUNT(*) as total FROM returns r 
                    JOIN loans l ON r.loan_id = l.id 
                    JOIN users u ON l.user_id = u.id 
                    JOIN items i ON l.item_id = i.id 
                    $where_clause";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetch()['total'];
    
    $pagination = get_pagination($total_items, $page, $items_per_page);
    
    // Get returns for current page
    $query = "SELECT r.*, l.loan_code, l.loan_date, l.due_date, u.username, u.full_name, i.title, i.barcode 
              FROM returns r 
              JOIN loans l ON r.loan_id = l.id 
              JOIN users u ON l.user_id = u.id 
              JOIN items i ON l.item_id = i.id 
              $where_clause 
              ORDER BY r.return_date DESC, r.created_at DESC 
              LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    $params = array_merge($params, [$items_per_page, $pagination['offset']]);
    $stmt->execute($params);
    $returns = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash_message('error', 'Gagal memuat data: ' . $e->getMessage());
    $returns = [];
}

// Get loan data for add form
$loan_data = null;
if ($action === 'add' && $loan_id) {
    try {
        $query = "SELECT l.*, u.username, u.full_name, i.title, i.barcode 
                  FROM loans l 
                  JOIN users u ON l.user_id = u.id 
                  JOIN items i ON l.item_id = i.id 
                  WHERE l.id = ? AND l.status = 'dipinjam'";
        $stmt = $db->prepare($query);
        $stmt->execute([$loan_id]);
        $loan_data = $stmt->fetch();
        
        if (!$loan_data) {
            set_flash_message('error', 'Data peminjaman tidak ditemukan atau sudah dikembalikan');
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Gagal memuat data peminjaman');
        redirect('returns.php');
    }
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Manajemen Pengembalian</h1>
    <p class="text-gray-600 mt-1">Kelola data pengembalian buku dalam perpustakaan</p>
</div>

<?php if ($action === 'list'): ?>
<!-- Search -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" class="flex gap-4">
        <div class="flex-1">
            <input type="text" 
                   name="search" 
                   placeholder="Cari berdasarkan kode pinjam, user, atau judul buku..." 
                   value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-search mr-2"></i>Cari
        </button>
        <a href="returns.php" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-refresh mr-2"></i>Reset
        </a>
    </form>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-md p-6 card-hover">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-check-circle text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Pengembalian</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_items); ?></p>
                <p class="text-xs text-gray-500">Semua pengembalian</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 card-hover">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <i class="fas fa-clock text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Pengembalian Hari Ini</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php 
                    $today_query = "SELECT COUNT(*) FROM returns WHERE DATE(return_date) = CURDATE()";
                    $today_stmt = $db->prepare($today_query);
                    $today_stmt->execute();
                    echo $today_stmt->fetchColumn();
                    ?>
                </p>
                <p class="text-xs text-gray-500">Hari ini</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 card-hover">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Denda Terlambat</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php 
                    $fine_query = "SELECT SUM(fine_amount) FROM returns WHERE fine_amount > 0";
                    $fine_stmt = $db->prepare($fine_query);
                    $fine_stmt->execute();
                    $total_fine = $fine_stmt->fetchColumn() ?? 0;
                    echo format_rupiah($total_fine);
                    ?>
                </p>
                <p class="text-xs text-gray-500">Total denda</p>
            </div>
        </div>
    </div>
</div>

<!-- Returns Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Pinjam</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Buku</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Kembali</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kondisi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Denda</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($returns as $return): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($return['loan_code']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($return['full_name']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($return['username']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($return['title']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($return['barcode']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo format_date($return['return_date']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                              <?php echo $return['item_condition'] === 'baik' ? 'bg-green-100 text-green-800' : 
                                        ($return['item_condition'] === 'rusak_ringan' ? 'bg-yellow-100 text-yellow-800' : 
                                        ($return['item_condition'] === 'rusak_berat' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $return['item_condition'])); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php if ($return['fine_amount'] > 0): ?>
                        <span class="font-semibold text-red-600"><?php echo format_rupiah($return['fine_amount']); ?></span>
                        <div class="text-xs text-gray-500"><?php echo $return['late_days']; ?> hari terlambat</div>
                        <?php else: ?>
                        <span class="text-gray-500">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <a href="returns.php?action=view&id=<?php echo $return['id']; ?>" 
                               class="text-green-600 hover:text-green-900">
                                <i class="fas fa-eye"></i>
                            </a>
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
        <a href="returns.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
        <a href="returns.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
           class="px-3 py-2 <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-lg hover:bg-blue-700 transition-colors">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $pagination['total_pages']): ?>
        <a href="returns.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php elseif ($action === 'add'): ?>
<!-- Add Return Form -->
<?php if (!$loan_data): ?>
<div class="bg-red-50 border border-red-200 rounded-lg p-4">
    <div class="flex items-center">
        <i class="fas fa-exclamation-circle text-red-600 mr-3"></i>
        <div>
            <h4 class="font-medium text-red-800">Data peminjaman tidak valid</h4>
            <p class="text-sm text-red-700">Silakan pilih peminjaman yang valid untuk diproses pengembalian.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-6">Proses Pengembalian Buku</h2>
    
    <!-- Loan Information -->
    <div class="bg-blue-50 rounded-lg p-4 mb-6">
        <h3 class="text-lg font-semibold text-blue-800 mb-3">Informasi Peminjaman</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-blue-600 font-medium">Kode Pinjam:</span>
                <span class="ml-2"><?php echo htmlspecialchars($loan_data['loan_code']); ?></span>
            </div>
            <div>
                <span class="text-blue-600 font-medium">Peminjam:</span>
                <span class="ml-2"><?php echo htmlspecialchars($loan_data['full_name']); ?></span>
            </div>
            <div>
                <span class="text-blue-600 font-medium">Judul Buku:</span>
                <span class="ml-2"><?php echo htmlspecialchars($loan_data['title']); ?></span>
            </div>
            <div>
                <span class="text-blue-600 font-medium">Jatuh Tempo:</span>
                <span class="ml-2"><?php echo format_date($loan_data['due_date']); ?></span>
            </div>
        </div>
    </div>
    
    <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="loan_id" value="<?php echo $loan_data['id']; ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="return_date" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Pengembalian *</label>
                <input type="date" 
                       name="return_date" 
                       id="return_date" 
                       value="<?php echo date('Y-m-d'); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       required>
            </div>
            
            <div>
                <label for="condition" class="block text-sm font-medium text-gray-700 mb-2">Kondisi Buku *</label>
                <select name="condition" id="condition" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Pilih Kondisi</option>
                    <option value="baik">Baik</option>
                    <option value="rusak_ringan">Rusak Ringan</option>
                    <option value="rusak_berat">Rusak Berat</option>
                    <option value="hilang">Hilang</option>
                </select>
            </div>
        </div>
        
        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
            <textarea name="notes" 
                      id="notes" 
                      rows="3"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="Catatan tambahan untuk pengembalian ini..."></textarea>
        </div>
        
        <!-- Fine Calculation Display -->
        <div id="fine_info" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 hidden">
            <h4 class="font-medium text-yellow-800 mb-2">Informasi Denda</h4>
            <div class="text-sm text-yellow-700">
                <p>Hari terlambat: <span id="late_days">0</span> hari</p>
                <p>Denda: <span id="fine_amount" class="font-semibold">Rp 0</span></p>
                <p class="text-xs mt-1">Denda dihitung Rp <?php echo number_format(LATE_FINE_PER_DAY); ?> per hari terlambat</p>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3 pt-6">
            <a href="loans.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-times mr-2"></i>Batal
            </a>
            <button type="submit" 
                    name="add" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-save mr-2"></i>Simpan Pengembalian
            </button>
        </div>
    </form>
</div>

<script>
// Calculate fine based on return date
document.getElementById('return_date').addEventListener('change', function() {
    const returnDate = new Date(this.value);
    const dueDate = new Date('<?php echo $loan_data['due_date']; ?>');
    const finePerDay = <?php echo LATE_FINE_PER_DAY; ?>;
    
    // Reset ke 0 jika tanggal sama atau lebih awal
    if (returnDate <= dueDate) {
        document.getElementById('late_days').textContent = '0';
        document.getElementById('fine_amount').textContent = 'Rp 0';
        document.getElementById('fine_info').classList.add('hidden');
    } else {
        // Hitung hanya jika returnDate > dueDate
        const diffTime = returnDate.getTime() - dueDate.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        const fineAmount = diffDays * finePerDay;
        
        document.getElementById('late_days').textContent = diffDays;
        document.getElementById('fine_amount').textContent = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR'
        }).format(fineAmount);
        document.getElementById('fine_info').classList.remove('hidden');
    }
});
</script>

<?php endif; ?>

<?php elseif ($action === 'view' && $id): ?>
<!-- View Return Details -->
<?php
// Get return details
$return_data = null;
try {
    $query = "SELECT r.*, l.loan_code, l.loan_date, l.due_date, u.username, u.full_name, i.title, i.barcode 
              FROM returns r 
              JOIN loans l ON r.loan_id = l.id 
              JOIN users u ON l.user_id = u.id 
              JOIN items i ON l.item_id = i.id 
              WHERE r.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $return_data = $stmt->fetch();
    
    if (!$return_data) {
        set_flash_message('error', 'Data pengembalian tidak ditemukan');
        redirect('returns.php');
    }
} catch (PDOException $e) {
    set_flash_message('error', 'Gagal memuat data pengembalian');
    redirect('returns.php');
}
?>

<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-start mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Detail Pengembalian</h2>
        <a href="returns.php" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Kembali
        </a>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Kode Peminjaman</h3>
                <p class="mt-1 text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($return_data['loan_code']); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Data Peminjam</h3>
                <div class="mt-1">
                    <p class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($return_data['full_name']); ?></p>
                    <p class="text-sm text-gray-600">Username: <?php echo htmlspecialchars($return_data['username']); ?></p>
                </div>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Data Buku</h3>
                <div class="mt-1">
                    <p class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($return_data['title']); ?></p>
                    <p class="text-sm text-gray-600">Barcode: <?php echo htmlspecialchars($return_data['barcode']); ?></p>
                </div>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Tanggal Pinjam - Jatuh Tempo</h3>
                <p class="mt-1 text-lg text-gray-900">
                    <?php echo format_date($return_data['loan_date']); ?> - 
                    <?php echo format_date($return_data['due_date']); ?>
                </p>
            </div>
        </div>
        
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Tanggal Pengembalian</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo format_date($return_data['return_date']); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Kondisi Buku</h3>
                <span class="mt-1 inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                      <?php echo $return_data['item_condition'] === 'baik' ? 'bg-green-100 text-green-800' : 
                                ($return_data['item_condition'] === 'rusak_ringan' ? 'bg-yellow-100 text-yellow-800' : 
                                ($return_data['item_condition'] === 'rusak_berat' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')); ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $return_data['item_condition'])); ?>
                </span>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Keterlambatan</h3>
                <p class="mt-1 text-lg text-gray-900">
                    <?php echo $return_data['late_days']; ?> hari terlambat
                    <?php if ($return_data['late_days'] > 0): ?>
                    <span class="text-red-600 font-semibold">(<?php echo format_rupiah($return_data['fine_amount']); ?>)</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Total Denda</h3>
                <p class="mt-1 text-lg font-semibold 
                   <?php echo $return_data['fine_amount'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                    <?php echo format_rupiah($return_data['fine_amount']); ?>
                </p>
            </div>
        </div>
    </div>
    
    <?php if ($return_data['notes']): ?>
    <div class="mt-8">
        <h3 class="text-sm font-medium text-gray-500 mb-2">Catatan</h3>
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-gray-900"><?php echo nl2br(htmlspecialchars($return_data['notes'])); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="flex justify-end space-x-3 mt-8 pt-6 border-t">
        <a href="returns.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Kembali
        </a>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>