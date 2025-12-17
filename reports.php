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

// Get report parameters
$report_type = $_GET['type'] ?? 'dashboard';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Generate reports data
try {
    // Dashboard statistics
    $stats = [];
    
    // Total books
    $query = "SELECT COUNT(*) as total, SUM(quantity_total) as total_quantity, SUM(quantity_available) as available_quantity 
              FROM items WHERE is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['books'] = $stmt->fetch();
    
    // Active loans
    $query = "SELECT COUNT(*) as total FROM loans WHERE status = 'dipinjam'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['active_loans'] = $stmt->fetch();
    
    // Overdue loans
    $query = "SELECT COUNT(*) as total FROM loans WHERE due_date < CURDATE() AND status = 'dipinjam'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['overdue_loans'] = $stmt->fetch();
    
    // Total returns
    $query = "SELECT COUNT(*) as total FROM returns";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_returns'] = $stmt->fetch();
    
    // Total fine collected
    $query = "SELECT SUM(fine_amount) as total FROM returns WHERE fine_amount > 0";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_fine'] = $stmt->fetch();
    
    // Active users
    $query = "SELECT COUNT(*) as total FROM users WHERE is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['active_users'] = $stmt->fetch();
    
    // Loans by status
    $query = "SELECT status, COUNT(*) as count FROM loans GROUP BY status";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['loans_by_status'] = $stmt->fetchAll();
    
    // Top borrowed books
    $query = "SELECT i.title, i.author, COUNT(l.id) as borrow_count 
              FROM items i 
              JOIN loans l ON i.id = l.item_id 
              GROUP BY i.id, i.title, i.author 
              ORDER BY borrow_count DESC 
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['top_books'] = $stmt->fetchAll();
    
    // Top active users
    $query = "SELECT u.full_name, u.username, COUNT(l.id) as loan_count 
              FROM users u 
              JOIN loans l ON u.id = l.user_id 
              GROUP BY u.id, u.full_name, u.username 
              ORDER BY loan_count DESC 
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['top_users'] = $stmt->fetchAll();
    
    // Monthly statistics for the selected period
    $query = "SELECT 
                DATE_FORMAT(loan_date, '%Y-%m') as month,
                COUNT(*) as loan_count,
                SUM(CASE WHEN status = 'dikembalikan' THEN 1 ELSE 0 END) as return_count
              FROM loans 
              WHERE loan_date BETWEEN ? AND ?
              GROUP BY DATE_FORMAT(loan_date, '%Y-%m')
              ORDER BY month";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from, $date_to]);
    $stats['monthly_stats'] = $stmt->fetchAll();
    
    // Category distribution
    $query = "SELECT c.name, COUNT(i.id) as book_count 
              FROM categories c 
              LEFT JOIN items i ON c.id = i.category_id AND i.is_active = 1 
              GROUP BY c.id, c.name 
              ORDER BY book_count DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['category_distribution'] = $stmt->fetchAll();
    
} catch (PDOException $e) {
    set_flash_message('error', 'Gagal memuat data laporan: ' . $e->getMessage());
    $stats = [];
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Laporan Perpustakaan</h1>
    <p class="text-gray-600 mt-1">Analisis dan statistik sistem perpustakaan</p>
</div>

<!-- Report Navigation -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <nav class="flex space-x-4">
        <a href="reports.php?type=dashboard" 
           class="px-4 py-2 rounded-lg transition-colors <?php echo $report_type === 'dashboard' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            <i class="fas fa-chart-bar mr-2"></i>Dashboard
        </a>
        <a href="reports.php?type=loans" 
           class="px-4 py-2 rounded-lg transition-colors <?php echo $report_type === 'loans' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            <i class="fas fa-hand-holding mr-2"></i>Peminjaman
        </a>
        <a href="reports.php?type=returns" 
           class="px-4 py-2 rounded-lg transition-colors <?php echo $report_type === 'returns' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            <i class="fas fa-undo mr-2"></i>Pengembalian
        </a>
        <a href="reports.php?type=books" 
           class="px-4 py-2 rounded-lg transition-colors <?php echo $report_type === 'books' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            <i class="fas fa-book mr-2"></i>Buku
        </a>
        <a href="reports.php?type=users" 
           class="px-4 py-2 rounded-lg transition-colors <?php echo $report_type === 'users' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            <i class="fas fa-users mr-2"></i>User
        </a>
    </nav>
</div>

<?php if ($report_type === 'dashboard'): ?>
<!-- Dashboard Report -->
<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-md p-6 card-hover">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-book text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Buku</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['books']['total'] ?? 0); ?></p>
                <p class="text-xs text-gray-500"><?php echo number_format($stats['books']['available_quantity'] ?? 0); ?> tersedia</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 card-hover">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <i class="fas fa-hand-holding text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Peminjaman Aktif</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['active_loans']['total'] ?? 0); ?></p>
                <p class="text-xs text-gray-500">Sedang dipinjam</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 card-hover">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-undo text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Pengembalian</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_returns']['total'] ?? 0); ?></p>
                <p class="text-xs text-gray-500">Sudah dikembalikan</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 card-hover">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-users text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">User Aktif</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['active_users']['total'] ?? 0); ?></p>
                <p class="text-xs text-gray-500">User terdaftar</p>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Loan Status Chart -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-pie mr-2 text-blue-600"></i>
            Status Peminjaman
        </h3>
        <div id="loanStatusChart" style="height: 300px;"></div>
    </div>

    <!-- Category Distribution Chart -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-tags mr-2 text-green-600"></i>
            Distribusi Kategori Buku
        </h3>
        <div id="categoryChart" style="height: 300px;"></div>
    </div>
</div>

<!-- Top Lists -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Top Borrowed Books -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-star mr-2 text-yellow-600"></i>
            Buku Paling Sering Dipinjam
        </h3>
        <div class="space-y-3">
            <?php foreach ($stats['top_books'] as $index => $book): ?>
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center">
                    <span class="w-6 h-6 bg-blue-600 text-white text-xs rounded-full flex items-center justify-center mr-3">
                        <?php echo $index + 1; ?>
                    </span>
                    <div>
                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($book['title']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($book['author']); ?></p>
                    </div>
                </div>
                <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                    <?php echo $book['borrow_count']; ?> kali
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Top Active Users -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-users mr-2 text-purple-600"></i>
            User Paling Aktif
        </h3>
        <div class="space-y-3">
            <?php foreach ($stats['top_users'] as $index => $user): ?>
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center">
                    <span class="w-6 h-6 bg-purple-600 text-white text-xs rounded-full flex items-center justify-center mr-3">
                        <?php echo $index + 1; ?>
                    </span>
                    <div>
                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                </div>
                <span class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded-full">
                    <?php echo $user['loan_count']; ?> pinjaman
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php elseif ($report_type === 'loans'): ?>
<!-- Loans Report -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
        <i class="fas fa-hand-holding mr-2 text-blue-600"></i>
        Laporan Peminjaman
    </h3>
    
    <div class="mb-4">
        <form method="GET" class="flex gap-4">
            <input type="hidden" name="type" value="loans">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-filter mr-2"></i>Filter
            </button>
        </form>
    </div>
    
    <?php
    // Get loans data for the selected period
    $query = "SELECT l.*, u.full_name, u.username, i.title, i.barcode 
              FROM loans l 
              JOIN users u ON l.user_id = u.id 
              JOIN items i ON l.item_id = i.id 
              WHERE l.loan_date BETWEEN ? AND ?
              ORDER BY l.loan_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from, $date_to]);
    $loans_data = $stmt->fetchAll();
    ?>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Buku</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal Pinjam</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jatuh Tempo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($loans_data as $loan): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($loan['loan_code']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($loan['full_name']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($loan['username']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($loan['title']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($loan['barcode']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo format_date($loan['loan_date']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo format_date($loan['due_date']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                              <?php echo $loan['status'] === 'dipinjam' ? 'bg-blue-100 text-blue-800' : 
                                        ($loan['status'] === 'dikembalikan' ? 'bg-green-100 text-green-800' : 
                                        ($loan['status'] === 'terlambat' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')); ?>">
                            <?php echo ucfirst($loan['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="mt-4 text-sm text-gray-600">
        Total: <?php echo count($loans_data); ?> peminjaman dalam periode <?php echo format_date($date_from); ?> - <?php echo format_date($date_to); ?>
    </div>
</div>

<?php elseif ($report_type === 'returns'): ?>
<!-- Returns Report -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
        <i class="fas fa-undo mr-2 text-green-600"></i>
        Laporan Pengembalian
    </h3>
    
    <div class="mb-4">
        <form method="GET" class="flex gap-4">
            <input type="hidden" name="type" value="returns">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-filter mr-2"></i>Filter
            </button>
        </form>
    </div>
    
    <?php
    // Get returns data for the selected period
    $query = "SELECT r.*, l.loan_code, l.loan_date, l.due_date, u.full_name, u.username, i.title, i.barcode 
              FROM returns r 
              JOIN loans l ON r.loan_id = l.id 
              JOIN users u ON l.user_id = u.id 
              JOIN items i ON l.item_id = i.id 
              WHERE r.return_date BETWEEN ? AND ?
              ORDER BY r.return_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$date_from, $date_to]);
    $returns_data = $stmt->fetchAll();
    ?>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode Pinjam</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Buku</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal Kembali</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kondisi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Denda</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($returns_data as $return): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($return['loan_code']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($return['full_name']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($return['username']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($return['title']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($return['barcode']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo format_date($return['return_date']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                              <?php echo $return['condition'] === 'baik' ? 'bg-green-100 text-green-800' : 
                                        ($return['condition'] === 'rusak_ringan' ? 'bg-yellow-100 text-yellow-800' : 
                                        ($return['condition'] === 'rusak_berat' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $return['condition'])); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php if ($return['fine_amount'] > 0): ?>
                        <span class="font-semibold text-red-600"><?php echo format_rupiah($return['fine_amount']); ?></span>
                        <?php else: ?>
                        <span class="text-gray-500">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="mt-4 text-sm text-gray-600">
        Total: <?php echo count($returns_data); ?> pengembalian dalam periode <?php echo format_date($date_from); ?> - <?php echo format_date($date_to); ?>
    </div>
</div>

<?php elseif ($report_type === 'books'): ?>
<!-- Books Report -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
        <i class="fas fa-book mr-2 text-blue-600"></i>
        Laporan Buku
    </h3>
    
    <?php
    // Get books data
    $query = "SELECT i.*, c.name as category_name, s.name as supplier_name,
              (SELECT COUNT(*) FROM loans WHERE item_id = i.id) as loan_count
              FROM items i 
              LEFT JOIN categories c ON i.category_id = c.id 
              LEFT JOIN suppliers s ON i.supplier_id = s.id 
              WHERE i.is_active = 1
              ORDER BY i.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $books_data = $stmt->fetchAll();
    ?>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Judul</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Penulis</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stok</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tersedia</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dipinjam</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($books_data as $book): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($book['barcode']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($book['title']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($book['author']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($book['author']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($book['category_name'] ?? '-'); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $book['quantity_total']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $book['quantity_available']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $book['quantity_total'] - $book['quantity_available']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="mt-4 text-sm text-gray-600">
        Total: <?php echo count($books_data); ?> buku dalam sistem
    </div>
</div>

<?php elseif ($report_type === 'users'): ?>
<!-- Users Report -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
        <i class="fas fa-users mr-2 text-purple-600"></i>
        Laporan User
    </h3>
    
    <?php
    // Get users data
    $query = "SELECT u.*, r.name as role_name,
              (SELECT COUNT(*) FROM loans WHERE user_id = u.id) as loan_count
              FROM users u 
              LEFT JOIN roles r ON u.role_id = r.id 
              ORDER BY u.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $users_data = $stmt->fetchAll();
    ?>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Lengkap</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pinjaman</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Terakhir Login</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($users_data as $user): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['username']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></td>
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
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $user['loan_count']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="mt-4 text-sm text-gray-600">
        Total: <?php echo count($users_data); ?> user dalam sistem
    </div>
</div>
<?php endif; ?>

<script>
// Loan Status Chart
const loanStatusData = <?php echo json_encode($stats['loans_by_status'] ?? []); ?>;
const loanStatusLabels = loanStatusData.map(item => item.status);
const loanStatusValues = loanStatusData.map(item => parseInt(item.count));

const loanStatusTrace = {
    labels: loanStatusLabels,
    values: loanStatusValues,
    type: 'pie',
    hole: 0.4,
    marker: {
        colors: ['#3b82f6', '#10b981', '#ef4444', '#6b7280']
    },
    textinfo: 'label+percent',
    textposition: 'outside'
};

const loanStatusLayout = {
    title: '',
    showlegend: true,
    margin: { t: 20, r: 20, b: 20, l: 20 },
    font: { family: 'Inter, sans-serif' }
};

Plotly.newPlot('loanStatusChart', [loanStatusTrace], loanStatusLayout, {
    responsive: true,
    displayModeBar: false
});

// Category Distribution Chart
const categoryData = <?php echo json_encode($stats['category_distribution'] ?? []); ?>;
const categoryLabels = categoryData.map(item => item.name);
const categoryValues = categoryData.map(item => parseInt(item.book_count));

const categoryTrace = {
    x: categoryLabels,
    y: categoryValues,
    type: 'bar',
    marker: {
        color: '#10b981'
    }
};

const categoryLayout = {
    title: '',
    xaxis: {
        title: 'Kategori',
        tickangle: -45
    },
    yaxis: {
        title: 'Jumlah Buku'
    },
    margin: { t: 20, r: 20, b: 100, l: 50 },
    font: { family: 'Inter, sans-serif' }
};

Plotly.newPlot('categoryChart', [categoryTrace], categoryLayout, {
    responsive: true,
    displayModeBar: false
});
</script>

<?php require_once 'includes/footer.php'; ?>