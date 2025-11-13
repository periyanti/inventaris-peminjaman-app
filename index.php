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

// Statistik dashboard
try {
    // Total buku
    $query = "SELECT COUNT(*) as total, SUM(quantity_total) as total_quantity, SUM(quantity_available) as available_quantity FROM items WHERE is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $book_stats = $stmt->fetch();
    
    // Total peminjaman aktif
    $query = "SELECT COUNT(*) as total FROM loans WHERE status = 'dipinjam'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $active_loans = $stmt->fetch();
    
    // Total pengembalian hari ini
    $query = "SELECT COUNT(*) as total FROM returns WHERE DATE(return_date) = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $today_returns = $stmt->fetch();
    
    // Total user
    $query = "SELECT COUNT(*) as total FROM users WHERE is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_users = $stmt->fetch();
    
    // Peminjaman yang jatuh tempo hari ini
    $query = "SELECT l.*, i.title, u.full_name FROM loans l 
              JOIN items i ON l.item_id = i.id 
              JOIN users u ON l.user_id = u.id 
              WHERE l.due_date = CURDATE() AND l.status = 'dipinjam'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $due_today = $stmt->fetchAll();
    
    // Peminjaman terlambat
    $query = "SELECT l.*, i.title, u.full_name, DATEDIFF(CURDATE(), l.due_date) as late_days 
              FROM loans l 
              JOIN items i ON l.item_id = i.id 
              JOIN users u ON l.user_id = u.id 
              WHERE l.due_date < CURDATE() AND l.status = 'dipinjam'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $overdue_loans = $stmt->fetchAll();
    
    // Data untuk chart peminjaman 7 hari terakhir
    $query = "SELECT DATE(loan_date) as date, COUNT(*) as count 
              FROM loans 
              WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              GROUP BY DATE(loan_date)
              ORDER BY date";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $loan_chart_data = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
    <p class="text-gray-600 mt-1">Selamat datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Books -->
    <div class="bg-white rounded-lg shadow-md p-6 card-hover">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-book text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Buku</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($book_stats['total']); ?></p>
                <p class="text-xs text-gray-500"><?php echo number_format($book_stats['available_quantity']); ?> tersedia</p>
            </div>
        </div>
    </div>

    <!-- Active Loans -->
    <div class="bg-white rounded-lg shadow-md p-6 card-hover">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <i class="fas fa-hand-holding text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Peminjaman Aktif</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($active_loans['total']); ?></p>
                <p class="text-xs text-gray-500">Sedang dipinjam</p>
            </div>
        </div>
    </div>

    <!-- Today's Returns -->
    <div class="bg-white rounded-lg shadow-md p-6 card-hover">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-undo text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Pengembalian Hari Ini</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($today_returns['total']); ?></p>
                <p class="text-xs text-gray-500">Sudah dikembalikan</p>
            </div>
        </div>
    </div>

    <!-- Total Users -->
    <div class="bg-white rounded-lg shadow-md p-6 card-hover">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-users text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total User</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_users['total']); ?></p>
                <p class="text-xs text-gray-500">User aktif</p>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Tables -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Loan Chart -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-line mr-2 text-blue-600"></i>
            Peminjaman 7 Hari Terakhir
        </h3>
        <div id="loanChart" style="height: 300px;"></div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-bolt mr-2 text-yellow-600"></i>
            Aksi Cepat
        </h3>
        <div class="space-y-3">
            <a href="items.php?action=add" class="flex items-center p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                <i class="fas fa-plus text-blue-600 mr-3"></i>
                <div>
                    <p class="font-medium text-gray-900">Tambah Buku Baru</p>
                    <p class="text-sm text-gray-600">Masukkan buku ke dalam inventaris</p>
                </div>
            </a>
            
            <a href="loans.php?action=add" class="flex items-center p-3 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition-colors">
                <i class="fas fa-hand-holding text-yellow-600 mr-3"></i>
                <div>
                    <p class="font-medium text-gray-900">Proses Peminjaman</p>
                    <p class="text-sm text-gray-600">Catat peminjaman baru</p>
                </div>
            </a>
            
            <a href="returns.php?action=add" class="flex items-center p-3 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                <i class="fas fa-undo text-green-600 mr-3"></i>
                <div>
                    <p class="font-medium text-gray-900">Proses Pengembalian</p>
                    <p class="text-sm text-gray-600">Catat pengembalian buku</p>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Alerts and Notifications -->
<div class="space-y-6">
    <!-- Due Today -->
    <?php if (count($due_today) > 0): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3"></i>
            <div class="flex-1">
                <h4 class="font-medium text-yellow-800 mb-2">
                    Peringatan Jatuh Tempo Hari Ini (<?php echo count($due_today); ?>)
                </h4>
                <div class="space-y-2">
                    <?php foreach ($due_today as $loan): ?>
                    <div class="text-sm text-yellow-700">
                        <strong><?php echo htmlspecialchars($loan['title']); ?></strong> - 
                        <?php echo htmlspecialchars($loan['full_name']); ?> 
                        <span class="text-xs">(<?php echo format_date($loan['due_date']); ?>)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Overdue Loans -->
    <?php if (count($overdue_loans) > 0): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-start">
            <i class="fas fa-exclamation-circle text-red-600 mt-1 mr-3"></i>
            <div class="flex-1">
                <h4 class="font-medium text-red-800 mb-2">
                    Peminjaman Terlambat (<?php echo count($overdue_loans); ?>)
                </h4>
                <div class="space-y-2">
                    <?php foreach ($overdue_loans as $loan): ?>
                    <div class="text-sm text-red-700">
                        <strong><?php echo htmlspecialchars($loan['title']); ?></strong> - 
                        <?php echo htmlspecialchars($loan['full_name']); ?> 
                        <span class="text-xs font-bold">(<?php echo $loan['late_days']; ?> hari terlambat)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Loan Chart
const loanData = <?php echo json_encode($loan_chart_data); ?>;
const dates = loanData.map(item => item.date);
const counts = loanData.map(item => parseInt(item.count));

const trace = {
    x: dates,
    y: counts,
    type: 'scatter',
    mode: 'lines+markers',
    line: {
        color: '#3b82f6',
        width: 3
    },
    marker: {
        color: '#3b82f6',
        size: 8
    },
    fill: 'tonexty',
    fillcolor: 'rgba(59, 130, 246, 0.1)'
};

const layout = {
    title: {
        text: '',
        font: { size: 16 }
    },
    xaxis: {
        title: 'Tanggal',
        showgrid: true,
        gridcolor: '#f3f4f6'
    },
    yaxis: {
        title: 'Jumlah Peminjaman',
        showgrid: true,
        gridcolor: '#f3f4f6'
    },
    plot_bgcolor: 'rgba(0,0,0,0)',
    paper_bgcolor: 'rgba(0,0,0,0)',
    margin: { t: 20, r: 20, b: 50, l: 50 },
    font: { family: 'Inter, sans-serif' }
};

Plotly.newPlot('loanChart', [trace], layout, {
    responsive: true,
    displayModeBar: false
});
</script>

<?php require_once 'includes/footer.php'; ?>