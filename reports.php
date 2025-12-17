<?php
session_start();
require_once 'includes/header.php';
require_once 'config/database.php';

if (!is_logged_in() || !has_role('admin')) {
    redirect('index.php');
}

$database = new Database();
$db = $database->getConnection();

$type = $_GET['type'] ?? 'dashboard';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

try {
    $stats = [];
    
    $stats['total_loans'] = $db->query("SELECT COUNT(*) FROM loans")->fetchColumn();
    $stats['active_loans'] = $db->query("SELECT COUNT(*) FROM loans WHERE status = 'dipinjam'")->fetchColumn();
    $stats['returned_loans'] = $db->query("SELECT COUNT(*) FROM loans WHERE status = 'dikembalikan'")->fetchColumn();
    $stats['overdue_loans'] = $db->query("SELECT COUNT(*) FROM loans WHERE status = 'terlambat'")->fetchColumn();
    
    $stats['total_users'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['total_items'] = $db->query("SELECT COUNT(*) FROM items")->fetchColumn();
    $stats['total_stock'] = $db->query("SELECT SUM(quantity_total) FROM items")->fetchColumn();
    
    $fines_query = "SELECT SUM(fine_amount) FROM returns";
    $stats['total_fines'] = $db->query($fines_query)->fetchColumn() ?: 0;
    
    if ($type === 'dashboard') {
        $monthly_loans_query = "SELECT DATE_FORMAT(loan_date, '%Y-%m') as month, COUNT(*) as count 
                               FROM loans 
                               WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) 
                               GROUP BY month 
                               ORDER BY month";
        $monthly_loans = $db->query($monthly_loans_query)->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $popular_items_query = "SELECT i.title, COUNT(l.id) as count 
                               FROM loans l 
                               JOIN items i ON l.item_id = i.id 
                               GROUP BY l.item_id 
                               ORDER BY count DESC 
                               LIMIT 5";
        $popular_items = $db->query($popular_items_query)->fetchAll();
        
        $loan_status_query = "SELECT status, COUNT(*) as count FROM loans GROUP BY status";
        $loan_status = $db->query($loan_status_query)->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    if ($type === 'loans') {
        $report_query = "SELECT l.*, u.username, u.full_name, i.title 
                        FROM loans l 
                        JOIN users u ON l.user_id = u.id 
                        JOIN items i ON l.item_id = i.id 
                        WHERE l.loan_date BETWEEN ? AND ? 
                        ORDER BY l.loan_date DESC";
        $stmt = $db->prepare($report_query);
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll();
    } elseif ($type === 'returns') {
        $report_query = "SELECT r.*, l.loan_code, l.loan_date, u.full_name, i.title 
                        FROM returns r 
                        JOIN loans l ON r.loan_id = l.id 
                        JOIN users u ON l.user_id = u.id 
                        JOIN items i ON l.item_id = i.id 
                        WHERE r.return_date BETWEEN ? AND ? 
                        ORDER BY r.return_date DESC";
        $stmt = $db->prepare($report_query);
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll();
    } elseif ($type === 'items') {
        $report_query = "SELECT i.*, c.name as category_name, s.name as supplier_name,
                        (SELECT COUNT(*) FROM loans WHERE item_id = i.id) as total_borrowed
                        FROM items i
                        LEFT JOIN categories c ON i.category_id = c.id
                        LEFT JOIN suppliers s ON i.supplier_id = s.id
                        ORDER BY total_borrowed DESC";
        $report_data = $db->query($report_query)->fetchAll();
    } elseif ($type === 'users') {
        $report_query = "SELECT u.*, 
                        (SELECT COUNT(*) FROM loans WHERE user_id = u.id) as total_loans,
                        (SELECT COUNT(*) FROM loans WHERE user_id = u.id AND status = 'terlambat') as total_late
                        FROM users u
                        ORDER BY total_loans DESC";
        $report_data = $db->query($report_query)->fetchAll();
    }
    
} catch (PDOException $e) {
    set_flash_message('error', 'Gagal memuat data laporan: ' . $e->getMessage());
    $stats = [];
}
?>

<div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Laporan & Statistik</h1>
        <p class="text-gray-600 mt-1">Ringkasan kinerja dan data perpustakaan</p>
    </div>
    
    <div class="mt-4 md:mt-0 flex space-x-2 overflow-x-auto pb-2 md:pb-0">
        <a href="reports.php?type=dashboard" 
           class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors 
           <?php echo $type === 'dashboard' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300'; ?>">
            <i class="fas fa-home mr-2"></i>Dashboard
        </a>
        <a href="reports.php?type=loans" 
           class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors 
           <?php echo $type === 'loans' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300'; ?>">
            <i class="fas fa-book-reader mr-2"></i>Peminjaman
        </a>
        <a href="reports.php?type=returns" 
           class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors 
           <?php echo $type === 'returns' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300'; ?>">
            <i class="fas fa-undo mr-2"></i>Pengembalian
        </a>
        <a href="reports.php?type=items" 
           class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors 
           <?php echo $type === 'items' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300'; ?>">
            <i class="fas fa-box mr-2"></i>Buku
        </a>
        <a href="reports.php?type=users" 
           class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors 
           <?php echo $type === 'users' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300'; ?>">
            <i class="fas fa-users mr-2"></i>User
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                <i class="fas fa-book-reader text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Peminjaman</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_loans']); ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                <i class="fas fa-clock text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Sedang Dipinjam</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['active_loans']); ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-emerald-100 text-emerald-600">
                <i class="fas fa-check-circle text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Dikembalikan</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['returned_loans']); ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-rose-100 text-rose-600">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Terlambat</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['overdue_loans']); ?></p>
            </div>
        </div>
    </div>
</div>

<?php if ($type === 'dashboard'): ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Tren Peminjaman (12 Bulan Terakhir)</h3>
        <div id="monthlyLoansChart" style="height: 300px;"></div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Status Peminjaman Saat Ini</h3>
        <div id="loanStatusChart" style="height: 300px;"></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md p-6">
    <h3 class="text-lg font-bold text-gray-900 mb-4">5 Buku Terpopuler</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul Buku</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Dipinjam</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Persentase</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php 
                $max_loans = $popular_items[0]['count'] ?? 1;
                foreach ($popular_items as $item): 
                    $percent = ($item['count'] / $stats['total_loans']) * 100;
                    $width = ($item['count'] / $max_loans) * 100;
                ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($item['title']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo number_format($item['count']); ?> kali
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap align-middle">
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-indigo-600 h-2.5 rounded-full" style="width: <?php echo $width; ?>%"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
<script>
    const monthlyData = {
        x: <?php echo json_encode(array_keys($monthly_loans)); ?>,
        y: <?php echo json_encode(array_values($monthly_loans)); ?>,
        type: 'scatter',
        mode: 'lines+markers',
        line: {color: '#4f46e5', width: 3},
        marker: {color: '#4f46e5', size: 8}
    };
    
    const monthlyLayout = {
        margin: {t: 20, r: 20, b: 40, l: 40},
        xaxis: {showgrid: false},
        yaxis: {gridcolor: '#f3f4f6'},
        showlegend: false
    };
    
    Plotly.newPlot('monthlyLoansChart', [monthlyData], monthlyLayout, {displayModeBar: false});
    
    const statusData = {
        values: <?php echo json_encode(array_values($loan_status)); ?>,
        labels: <?php echo json_encode(array_map('ucfirst', array_keys($loan_status))); ?>,
        type: 'pie',
        hole: 0.4,
        marker: {
            colors: ['#4f46e5', '#10b981', '#f43f5e', '#d946ef', '#f59e0b']
        }
    };
    
    const statusLayout = {
        margin: {t: 20, r: 20, b: 20, l: 20},
        showlegend: true,
        legend: {orientation: 'h', y: -0.1}
    };
    
    Plotly.newPlot('loanStatusChart', [statusData], statusLayout, {displayModeBar: false});
</script>

<?php else: ?>
    <?php if ($type === 'loans' || $type === 'returns'): ?>
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <input type="hidden" name="type" value="<?php echo $type; ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                       class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                       class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                <i class="fas fa-filter mr-2"></i>Filter
            </button>
            <button type="button" onclick="window.print()" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-print mr-2"></i>Cetak
            </button>
        </form>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <?php if ($type === 'loans'): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Peminjam</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Buku</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <?php elseif ($type === 'returns'): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Pinjam</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Kembali</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Peminjam</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Buku</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kondisi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Denda</th>
                        <?php elseif ($type === 'items'): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Stok</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dipinjam</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa</th>
                        <?php elseif ($type === 'users'): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bergabung</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Pinjam</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Terlambat</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($report_data)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">Tidak ada data untuk ditampilkan</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($report_data as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <?php if ($type === 'loans'): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['loan_code']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo format_date($row['loan_date']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div class="font-medium"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['username']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['title']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $row['status'] === 'dipinjam' ? 'bg-indigo-100 text-indigo-800' : 
                                                ($row['status'] === 'dikembalikan' ? 'bg-emerald-100 text-emerald-800' : 
                                                ($row['status'] === 'terlambat' ? 'bg-rose-100 text-rose-800' : 'bg-gray-100 text-gray-800')); ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                            <?php elseif ($type === 'returns'): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['loan_code']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo format_date($row['return_date']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['title']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $row['item_condition'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo format_rupiah($row['fine_amount']); ?></td>
                            <?php elseif ($type === 'items'): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['title']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['category_name'] ?: '-'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($row['quantity_total']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($row['total_borrowed']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($row['quantity_available']); ?></td>
                            <?php elseif ($type === 'users'): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <div class="font-medium"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['username']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo ucfirst($row['role']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo format_date($row['created_at']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($row['total_loans']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-rose-600 font-semibold"><?php echo number_format($row['total_late']); ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>