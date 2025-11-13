<?php
session_start();
require_once 'includes/header.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!is_logged_in()) {
    redirect('login.php');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// PROCESS FORM SUBMISSIONS - USING SIMPLE LOGIC THAT WORKS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = 'Token tidak valid';
        header('Location: items.php');
        exit();
    }

    try {
        // ADD BOOK
        if (isset($_POST['add_book'])) {
            $barcode = $_POST['barcode'] ?: generate_barcode();
            $title = sanitize_input($_POST['title']);
            $author = sanitize_input($_POST['author']);
            $category_id = (int)$_POST['category_id'];
            $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
            $isbn = sanitize_input($_POST['isbn'] ?? '');
            $description = sanitize_input($_POST['description'] ?? '');
            $quantity_total = (int)$_POST['quantity_total'];
            $quantity_available = (int)$_POST['quantity_available'];
            $location = sanitize_input($_POST['location'] ?? '');
            $condition = $_POST['condition'] ?? 'baik';
            $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
            $purchase_price = !empty($_POST['purchase_price']) ? (float)$_POST['purchase_price'] : null;

            // Validation
            if (empty($title) || empty($author) || empty($category_id)) {
                throw new Exception('Judul, penulis, dan kategori harus diisi');
            }

            if ($quantity_available > $quantity_total) {
                throw new Exception('Jumlah tersedia tidak boleh lebih dari jumlah total');
            }

            $query = "INSERT INTO items (barcode, title, author, category_id, supplier_id, isbn, description, 
                      quantity_total, quantity_available, location, item_condition, purchase_date, purchase_price) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $barcode, $title, $author, $category_id, $supplier_id, $isbn, $description,
                $quantity_total, $quantity_available, $location, $condition, $purchase_date, $purchase_price
            ]);

            if ($result) {
                $item_id = $db->lastInsertId();
                log_activity('create', 'items', $item_id, "Menambahkan buku baru: $title");
                $_SESSION['success'] = 'Buku berhasil ditambahkan';
            } else {
                throw new Exception('Gagal menambahkan buku');
            }

        // UPDATE BOOK
        } elseif (isset($_POST['update_book'])) {
            $id = (int)$_POST['id'];
            $barcode = $_POST['barcode'];
            $title = sanitize_input($_POST['title']);
            $author = sanitize_input($_POST['author']);
            $category_id = (int)$_POST['category_id'];
            $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
            $isbn = sanitize_input($_POST['isbn'] ?? '');
            $description = sanitize_input($_POST['description'] ?? '');
            $quantity_total = (int)$_POST['quantity_total'];
            $quantity_available = (int)$_POST['quantity_available'];
            $location = sanitize_input($_POST['location'] ?? '');
            $condition = $_POST['condition'] ?? 'baik';
            $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
            $purchase_price = !empty($_POST['purchase_price']) ? (float)$_POST['purchase_price'] : null;

            // Validation
            if (empty($title) || empty($author) || empty($category_id)) {
                throw new Exception('Judul, penulis, dan kategori harus diisi');
            }

            if ($quantity_available > $quantity_total) {
                throw new Exception('Jumlah tersedia tidak boleh lebih dari jumlah total');
            }

            $query = "UPDATE items SET barcode = ?, title = ?, author = ?, category_id = ?, supplier_id = ?, 
                      isbn = ?, description = ?, quantity_total = ?, quantity_available = ?, location = ?, 
                      item_condition = ?, purchase_date = ?, purchase_price = ? WHERE id = ?";
            
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $barcode, $title, $author, $category_id, $supplier_id, $isbn, $description,
                $quantity_total, $quantity_available, $location, $condition, $purchase_date, 
                $purchase_price, $id
            ]);

            if ($result) {
                log_activity('update', 'items', $id, "Memperbarui data buku: $title");
                $_SESSION['success'] = 'Buku berhasil diperbarui';
            } else {
                throw new Exception('Gagal memperbarui buku');
            }

        // DELETE BOOK
        } elseif (isset($_POST['delete_book'])) {
            $id = (int)$_POST['id'];

            // Check if book is currently borrowed
            $check_query = "SELECT COUNT(*) FROM loans WHERE item_id = ? AND status = 'dipinjam'";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$id]);
            
            if ($check_stmt->fetchColumn() > 0) {
                $_SESSION['error'] = 'Buku tidak dapat dihapus karena sedang dipinjam';
            } else {
                $query = "UPDATE items SET is_active = 0 WHERE id = ?";
                $stmt = $db->prepare($query);
                $result = $stmt->execute([$id]);

                if ($result) {
                    log_activity('delete', 'items', $id, 'Menghapus data buku');
                    $_SESSION['success'] = 'Buku berhasil dihapus';
                } else {
                    throw new Exception('Gagal menghapus buku');
                }
            }
        }

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: items.php?action=' . (isset($_POST['add_book']) ? 'add' : 'edit') . '&id=' . ($id ?? ''));
        exit();
    }
}

// GET DATA FOR DISPLAY - USING ORIGINAL CODE
$categories = [];
$suppliers = [];

try {
    $stmt = $db->prepare("SELECT id, name FROM categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT id, name FROM suppliers ORDER BY name");
    $stmt->execute();
    $suppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    // Silent error for data loading
}

// Get single item for edit/view
$item = null;
if (($action === 'edit' || $action === 'view') && $id) {
    try {
        $query = "SELECT i.*, c.name as category_name, s.name as supplier_name 
                  FROM items i 
                  LEFT JOIN categories c ON i.category_id = c.id 
                  LEFT JOIN suppliers s ON i.supplier_id = s.id 
                  WHERE i.id = ? AND i.is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        
        if (!$item) {
            $_SESSION['error'] = 'Buku tidak ditemukan';
            header('Location: items.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal memuat data buku';
        header('Location: items.php');
        exit();
    }
}

// Pagination untuk list view
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$items_per_page = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;

$where_conditions = ["i.is_active = 1"];
$params = [];

if ($search) {
    $where_conditions[] = "(i.title LIKE ? OR i.author LIKE ? OR i.barcode LIKE ? OR i.isbn LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($category_filter) {
    $where_conditions[] = "i.category_id = ?";
    $params[] = $category_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Count total items
try {
    $count_query = "SELECT COUNT(*) as total FROM items i WHERE $where_clause";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetch()['total'];
    
    $pagination = get_pagination($total_items, $page, $items_per_page);
    
    // Get items for current page
    $query = "SELECT i.*, c.name as category_name, s.name as supplier_name 
              FROM items i 
              LEFT JOIN categories c ON i.category_id = c.id 
              LEFT JOIN suppliers s ON i.supplier_id = s.id 
              WHERE $where_clause 
              ORDER BY i.created_at DESC 
              LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    $limit_params = array_merge($params, [$items_per_page, $pagination['offset']]);
    $stmt->execute($limit_params);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    $items = [];
    $total_items = 0;
    $pagination = ['total_pages' => 1, 'offset' => 0];
}

// Get flash messages from session
$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Manajemen Buku</h1>
    <p class="text-gray-600 mt-1">Kelola data buku dalam perpustakaan</p>
</div>

<!-- Custom Flash Messages -->
<?php if ($success_msg): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
    <span class="block sm:inline"><?php echo htmlspecialchars($success_msg); ?></span>
</div>
<?php endif; ?>

<?php if ($error_msg): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
    <span class="block sm:inline"><?php echo htmlspecialchars($error_msg); ?></span>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<!-- Search and Filter -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" class="flex flex-wrap gap-4">
        <div class="flex-1 min-w-48">
            <input type="text" 
                   name="search" 
                   placeholder="Cari berdasarkan judul, penulis, atau barcode..." 
                   value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <div class="min-w-32">
            <select name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $category): ?>
                <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-search mr-2"></i>Cari
        </button>
        <a href="items.php" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-refresh mr-2"></i>Reset
        </a>
    </form>
</div>

<!-- Action Buttons -->
<div class="flex justify-between items-center mb-6">
    <div class="text-sm text-gray-600">
        Menampilkan <?php echo count($items); ?> dari <?php echo number_format($total_items); ?> buku
    </div>
    <a href="items.php?action=add" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
        <i class="fas fa-plus mr-2"></i>Tambah Buku
    </a>
</div>

<!-- Items Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barcode</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Penulis</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tersedia</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                        Tidak ada data buku
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($items as $item): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($item['barcode']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['title']); ?></div>
                        <?php if (!empty($item['isbn'])): ?>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($item['isbn']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($item['author']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($item['category_name'] ?? '-'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm font-medium text-gray-900"><?php echo $item['quantity_total']; ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                              <?php echo $item['quantity_available'] > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $item['quantity_available']; ?> tersedia
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <a href="items.php?action=edit&id=<?php echo $item['id']; ?>" 
                               class="text-blue-600 hover:text-blue-900" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="items.php?action=view&id=<?php echo $item['id']; ?>" 
                               class="text-green-600 hover:text-green-900" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus buku ini?')">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                <button type="submit" name="delete_book" class="text-red-600 hover:text-red-900" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
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
        <a href="items.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
        <a href="items.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>" 
           class="px-3 py-2 <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-lg hover:bg-blue-700 transition-colors">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $pagination['total_pages']): ?>
        <a href="items.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>" 
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
        <?php echo $action === 'add' ? 'Tambah Buku Baru' : 'Edit Buku'; ?>
    </h2>
    
    <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <?php if ($action === 'edit'): ?>
        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="barcode" class="block text-sm font-medium text-gray-700 mb-2">Barcode</label>
                <input type="text" 
                       name="barcode" 
                       id="barcode" 
                       value="<?php echo htmlspecialchars($item['barcode'] ?? generate_barcode()); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       required>
            </div>
            
            <div>
                <label for="isbn" class="block text-sm font-medium text-gray-700 mb-2">ISBN</label>
                <input type="text" 
                       name="isbn" 
                       id="isbn" 
                       value="<?php echo htmlspecialchars($item['isbn'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <div class="md:col-span-2">
                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Judul Buku *</label>
                <input type="text" 
                       name="title" 
                       id="title" 
                       value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       required>
            </div>
            
            <div>
                <label for="author" class="block text-sm font-medium text-gray-700 mb-2">Penulis *</label>
                <input type="text" 
                       name="author" 
                       id="author" 
                       value="<?php echo htmlspecialchars($item['author'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       required>
            </div>
            
            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">Kategori *</label>
                <select name="category_id" id="category_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Pilih Kategori</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" 
                            <?php echo ($item['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-2">Supplier</label>
                <select name="supplier_id" id="supplier_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Pilih Supplier</option>
                    <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?php echo $supplier['id']; ?>" 
                            <?php echo ($item['supplier_id'] ?? '') == $supplier['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($supplier['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="quantity_total" class="block text-sm font-medium text-gray-700 mb-2">Jumlah Total *</label>
                <input type="number" 
                       name="quantity_total" 
                       id="quantity_total" 
                       value="<?php echo $item['quantity_total'] ?? 1; ?>"
                       min="1"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       required>
            </div>
            
            <div>
                <label for="quantity_available" class="block text-sm font-medium text-gray-700 mb-2">Jumlah Tersedia *</label>
                <input type="number" 
                       name="quantity_available" 
                       id="quantity_available" 
                       value="<?php echo $item['quantity_available'] ?? 1; ?>"
                       min="0"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       required>
            </div>
            
            <div>
                <label for="location" class="block text-sm font-medium text-gray-700 mb-2">Lokasi</label>
                <input type="text" 
                       name="location" 
                       id="location" 
                       value="<?php echo htmlspecialchars($item['location'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <div>
                <label for="condition" class="block text-sm font-medium text-gray-700 mb-2">Kondisi</label>
                <select name="condition" id="condition" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="baru" <?php echo ($item['item_condition'] ?? '') === 'baru' ? 'selected' : ''; ?>>Baru</option>
                    <option value="baik" <?php echo ($item['item_condition'] ?? '') === 'baik' ? 'selected' : ''; ?>>Baik</option>
                    <option value="rusak_ringan" <?php echo ($item['item_condition'] ?? '') === 'rusak_ringan' ? 'selected' : ''; ?>>Rusak Ringan</option>
                    <option value="rusak_berat" <?php echo ($item['item_condition'] ?? '') === 'rusak_berat' ? 'selected' : ''; ?>>Rusak Berat</option>
                </select>
            </div>
            
            <div>
                <label for="purchase_date" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Pembelian</label>
                <input type="date" 
                       name="purchase_date" 
                       id="purchase_date" 
                       value="<?php echo $item['purchase_date'] ?? ''; ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <div>
                <label for="purchase_price" class="block text-sm font-medium text-gray-700 mb-2">Harga Pembelian</label>
                <input type="number" 
                       name="purchase_price" 
                       id="purchase_price" 
                       value="<?php echo $item['purchase_price'] ?? ''; ?>"
                       step="0.01"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
        </div>
        
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
            <textarea name="description" 
                      id="description" 
                      rows="4"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="Deskripsi singkat tentang buku ini..."><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="flex justify-end space-x-3 pt-6">
            <a href="items.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-times mr-2"></i>Batal
            </a>
            <button type="submit" 
                    name="<?php echo $action === 'add' ? 'add_book' : 'update_book'; ?>" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-save mr-2"></i><?php echo $action === 'add' ? 'Simpan' : 'Update'; ?>
            </button>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $id): ?>
<!-- View Item Details -->
<?php
// Get item details if not already loaded
if (!$item) {
    try {
        $query = "SELECT i.*, c.name as category_name, s.name as supplier_name 
                  FROM items i 
                  LEFT JOIN categories c ON i.category_id = c.id 
                  LEFT JOIN suppliers s ON i.supplier_id = s.id 
                  WHERE i.id = ? AND i.is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        
        if (!$item) {
            $_SESSION['error'] = 'Buku tidak ditemukan';
            header('Location: items.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal memuat data buku';
        header('Location: items.php');
        exit();
    }
}
?>

<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-start mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Detail Buku</h2>
        <a href="items.php" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Kembali
        </a>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Barcode</h3>
                <p class="mt-1 text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($item['barcode']); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">ISBN</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo htmlspecialchars($item['isbn'] ?: '-'); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Judul</h3>
                <p class="mt-1 text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($item['title']); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Penulis</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo htmlspecialchars($item['author']); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Kategori</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo htmlspecialchars($item['category_name'] ?: '-'); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Supplier</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo htmlspecialchars($item['supplier_name'] ?: '-'); ?></p>
            </div>
        </div>
        
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Stok</h3>
                <div class="mt-1 flex space-x-4">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-blue-600"><?php echo $item['quantity_total']; ?></p>
                        <p class="text-xs text-gray-500">Total</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-green-600"><?php echo $item['quantity_available']; ?></p>
                        <p class="text-xs text-gray-500">Tersedia</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-red-600"><?php echo $item['quantity_total'] - $item['quantity_available']; ?></p>
                        <p class="text-xs text-gray-500">Dipinjam</p>
                    </div>
                </div>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Lokasi</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo htmlspecialchars($item['location'] ?: '-'); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Kondisi</h3>
                <span class="mt-1 inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                      <?php echo $item['item_condition'] === 'baru' ? 'bg-green-100 text-green-800' : 
                                ($item['item_condition'] === 'baik' ? 'bg-blue-100 text-blue-800' : 
                                ($item['item_condition'] === 'rusak_ringan' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')); ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $item['item_condition'])); ?>
                </span>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Tanggal Pembelian</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo $item['purchase_date'] ? format_date($item['purchase_date']) : '-'; ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Harga Pembelian</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo $item['purchase_price'] ? format_rupiah($item['purchase_price']) : '-'; ?></p>
            </div>
        </div>
    </div>
    
    <?php if ($item['description']): ?>
    <div class="mt-8">
        <h3 class="text-sm font-medium text-gray-500 mb-2">Deskripsi</h3>
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-gray-900"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="flex justify-end space-x-3 mt-8 pt-6 border-t">
        <a href="items.php?action=edit&id=<?php echo $item['id']; ?>" 
           class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-edit mr-2"></i>Edit
        </a>
        <a href="items.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Kembali
        </a>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>