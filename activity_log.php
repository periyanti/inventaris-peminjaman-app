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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = $_GET['search'] ?? '';
$user_filter = $_GET['user'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$items_per_page = ITEMS_PER_PAGE;

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(a.action LIKE ? OR a.description LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($user_filter) {
    $where_conditions[] = "a.user_id = ?";
    $params[] = $user_filter;
}

if ($action_filter) {
    $where_conditions[] = "a.action = ?";
    $params[] = $action_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(a.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(a.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total activities
try {
    $count_query = "SELECT COUNT(*) as total FROM activity_log a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    $where_clause";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetch()['total'];
    
    $pagination = get_pagination($total_items, $page, $items_per_page);
    
    // Get activities for current page
    $query = "SELECT a.*, u.username, u.full_name 
              FROM activity_log a 
              LEFT JOIN users u ON a.user_id = u.id 
              $where_clause 
              ORDER BY a.created_at DESC 
              LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    $params = array_merge($params, [$items_per_page, $pagination['offset']]);
    $stmt->execute($params);
    $activities = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash_message('error', 'Gagal memuat data: ' . $e->getMessage());
    $activities = [];
}

// Get users for filter
try {
    $user_query = "SELECT id, username, full_name FROM users WHERE is_active = 1 ORDER BY full_name";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute();
    $users = $user_stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
}

// Get unique actions for filter
try {
    $action_query = "SELECT DISTINCT action FROM activity_log ORDER BY action";
    $action_stmt = $db->prepare($action_query);
    $action_stmt->execute();
    $actions = $action_stmt->fetchAll();
} catch (PDOException $e) {
    $actions = [];
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Log Aktivitas</h1>
    <p class="text-gray-600 mt-1">Monitoring aktivitas user dalam sistem</p>
</div>

<!-- Search and Filter -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
                <input type="text" 
                       name="search" 
                       id="search" 
                       placeholder="Cari aktivitas..." 
                       value="<?php echo htmlspecialchars($search); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <div>
                <label for="user" class="block text-sm font-medium text-gray-700 mb-1">User</label>
                <select name="user" id="user" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Semua User</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="action" class="block text-sm font-medium text-gray-700 mb-1">Aksi</label>
                <select name="action" id="action" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Semua Aksi</option>
                    <?php foreach ($actions as $action): ?>
                    <option value="<?php echo $action['action']; ?>" <?php echo $action_filter == $action['action'] ? 'selected' : ''; ?>>
                        <?php echo ucfirst(str_replace('_', ' ', $action['action'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
                <input type="date" 
                       name="date_from" 
                       id="date_from" 
                       value="<?php echo htmlspecialchars($date_from); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
                <input type="date" 
                       name="date_to" 
                       id="date_to" 
                       value="<?php echo htmlspecialchars($date_to); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
        </div>
        
        <div class="flex gap-4">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-search mr-2"></i>Cari
            </button>
            <a href="activity_log.php" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-refresh mr-2"></i>Reset
            </a>
        </div>
    </form>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-md p-6 card-hover">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-history text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Aktivitas</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_items); ?></p>
                <p class="text-xs text-gray-500">Semua aktivitas</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 card-hover">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-plus text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Aktivitas Hari Ini</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php 
                    $today_query = "SELECT COUNT(*) FROM activity_log WHERE DATE(created_at) = CURDATE()";
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
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <i class="fas fa-user text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">User Aktif</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php 
                    $active_query = "SELECT COUNT(DISTINCT user_id) FROM activity_log WHERE user_id IS NOT NULL";
                    $active_stmt = $db->prepare($active_query);
                    $active_stmt->execute();
                    echo $active_stmt->fetchColumn();
                    ?>
                </p>
                <p class="text-xs text-gray-500">User yang aktif</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 card-hover">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Login Gagal</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php 
                    $failed_query = "SELECT COUNT(*) FROM activity_log WHERE action = 'login_failed'";
                    $failed_stmt = $db->prepare($failed_query);
                    $failed_stmt->execute();
                    echo $failed_stmt->fetchColumn();
                    ?>
                </p>
                <p class="text-xs text-gray-500">Percobaan gagal</p>
            </div>
        </div>
    </div>
</div>

<!-- Activities Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Daftar Aktivitas</h3>
        <p class="text-sm text-gray-600">Menampilkan <?php echo count($activities); ?> dari <?php echo number_format($total_items); ?> aktivitas</p>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tabel</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($activities as $activity): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></div>
                        <div class="text-xs text-gray-500"><?php echo date('s', strtotime($activity['created_at'])); ?> detik yang lalu</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($activity['user_id']): ?>
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($activity['full_name'] ?? 'Unknown'); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($activity['username'] ?? 'Unknown'); ?></div>
                        <?php else: ?>
                        <div class="text-sm text-gray-500">System/Guest</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                              <?php 
                              $action_colors = [
                                  'login' => 'bg-green-100 text-green-800',
                                  'logout' => 'bg-blue-100 text-blue-800',
                                  'create' => 'bg-indigo-100 text-indigo-800',
                                  'update' => 'bg-yellow-100 text-yellow-800',
                                  'delete' => 'bg-red-100 text-red-800',
                                  'login_failed' => 'bg-red-100 text-red-800'
                              ];
                              $color = $action_colors[$activity['action']] ?? 'bg-gray-100 text-gray-800';
                              echo $color;
                              ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($activity['table_affected'] ?? '-'); ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <?php echo htmlspecialchars($activity['description'] ?? '-'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <code class="text-xs bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($activity['ip_address']); ?></code>
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
        <a href="activity_log.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&user=<?php echo urlencode($user_filter); ?>&action=<?php echo urlencode($action_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
        <a href="activity_log.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&user=<?php echo urlencode($user_filter); ?>&action=<?php echo urlencode($action_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" 
           class="px-3 py-2 <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-lg hover:bg-blue-700 transition-colors">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $pagination['total_pages']): ?>
        <a href="activity_log.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&user=<?php echo urlencode($user_filter); ?>&action=<?php echo urlencode($action_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>