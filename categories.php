<?php
session_start();
require_once 'includes/header.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('error', 'Token tidak valid');
        redirect('categories.php');
    }
    
    try {
        if (isset($_POST['add'])) {
            // Add new category
            $name = sanitize_input($_POST['name']);
            $description = sanitize_input($_POST['description']);
            
            // Cek duplikasi nama
            $check_query = "SELECT COUNT(*) FROM categories WHERE name = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$name]);
            
            if ($check_stmt->fetchColumn() > 0) {
                set_flash_message('error', 'Kategori dengan nama tersebut sudah ada');
            }
            
            $query = "INSERT INTO categories (name, description) VALUES (?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$name, $description]);
            
            $category_id = $db->lastInsertId();
            log_activity('create', 'categories', $category_id, "Menambahkan kategori baru: $name");
            set_flash_message('success', 'Kategori berhasil ditambahkan');
            
        } elseif (isset($_POST['update'])) {
            $id = (int)$_POST['id'];
            $name = sanitize_input($_POST['name']);
            $description = sanitize_input($_POST['description']);
            
            $check_query = "SELECT COUNT(*) FROM categories WHERE name = ? AND id != ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$name, $id]);
            
            if ($check_stmt->fetchColumn() > 0) {
                set_flash_message('error', 'Kategori dengan nama tersebut sudah ada');
            }
            
            $query = "UPDATE categories SET name = ?, description = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$name, $description, $id]);
            
            log_activity('update', 'categories', $id, "Memperbarui kategori: $name");
            set_flash_message('success', 'Kategori berhasil diperbarui');
            
        } elseif (isset($_POST['delete'])) {
            // Delete category
            $id = (int)$_POST['id'];
            
            // Cek apakah kategori masih digunakan
            $check_query = "SELECT COUNT(*) FROM items WHERE category_id = ? AND is_active = 1";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$id]);
            
            if ($check_stmt->fetchColumn() > 0) {
                set_flash_message('error', 'Kategori tidak dapat dihapus karena masih digunakan oleh buku');
            } else {
                $query = "DELETE FROM categories WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                
                log_activity('delete', 'categories', $id, 'Menghapus kategori');
                set_flash_message('success', 'Kategori berhasil dihapus');
            }
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Terjadi kesalahan: ' . $e->getMessage());
    }
}

$current_category = null;
if ($action === 'edit' && $id) {
    try {
        $query = "SELECT * FROM categories WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $current_category = $stmt->fetch();
        
        if (!$current_category) {
            $_SESSION['flash_error'] = 'Kategori tidak ditemukan';
            header('Location: categories.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = 'Gagal memuat data kategori';
        header('Location: categories.php');
        exit();
    }
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = $_GET['search'] ?? '';
$items_per_page = ITEMS_PER_PAGE;

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    $count_query = "SELECT COUNT(*) as total FROM categories $where_clause";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetch()['total'];
    
    $pagination = get_pagination($total_items, $page, $items_per_page);
    
    $query = "SELECT c.*, COUNT(i.id) as book_count 
              FROM categories c 
              LEFT JOIN items i ON c.id = i.category_id AND i.is_active = 1 
              $where_clause 
              GROUP BY c.id 
              ORDER BY c.name ASC 
              LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    $params = array_merge($params, [$items_per_page, $pagination['offset']]);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash_message('error', 'Gagal memuat data: ' . $e->getMessage());
    $categories = [];
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Manajemen Kategori</h1>
    <p class="text-gray-600 mt-1">Kelola kategori buku dalam perpustakaan</p>
</div>

<?php if ($action === 'list'): ?>
<!-- Search -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" class="flex gap-4">
        <div class="flex-1">
            <input type="text" 
                   name="search" 
                   placeholder="Cari kategori..." 
                   value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-search mr-2"></i>Cari
        </button>
        <a href="categories.php" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-refresh mr-2"></i>Reset
        </a>
    </form>
</div>

<!-- Action Buttons -->
<div class="flex justify-between items-center mb-6">
    <div class="text-sm text-gray-600">
        Menampilkan <?php echo count($categories); ?> dari <?php echo number_format($total_items); ?> kategori
    </div>
    <a href="categories.php?action=add" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
        <i class="fas fa-plus mr-2"></i>Tambah Kategori
    </a>
</div>

<!-- Categories Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Kategori</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Buku</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($categories as $category): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900">
                            <?php echo $category['description'] ? htmlspecialchars(substr($category['description'], 0, 50)) . (strlen($category['description']) > 50 ? '...' : '') : '-'; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            <?php echo $category['book_count']; ?> buku
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Apakah Anda yakin ingin menghapus kategori ini?')">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                <button type="submit" name="delete" class="text-red-600 hover:text-red-900">
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

<!-- Pagination -->
<?php if ($pagination['total_pages'] > 1): ?>
<div class="flex items-center justify-between mt-6">
    <div class="text-sm text-gray-700">
        Halaman <?php echo $page; ?> dari <?php echo $pagination['total_pages']; ?>
    </div>
    <div class="flex space-x-2">
        <?php if ($page > 1): ?>
        <a href="categories.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
        <a href="categories.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
           class="px-3 py-2 <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-lg hover:bg-blue-700 transition-colors">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $pagination['total_pages']): ?>
        <a href="categories.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" 
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
        <?php echo $action === 'add' ? 'Tambah Kategori Baru' : 'Edit Kategori'; ?>
    </h2>
    
    <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <?php if ($action === 'edit'): ?>
        <input type="hidden" name="id" value="<?php echo $current_category['id']; ?>">
        <?php endif; ?>
        
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Nama Kategori *</label>
            <input type="text" 
                   name="name" 
                   id="name" 
                   value="<?php echo htmlspecialchars($current_category['name'] ?? ''); ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   required
                   placeholder="Masukkan nama kategori">
        </div>
        
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
            <textarea name="description" 
                      id="description" 
                      rows="4"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="Deskripsi singkat tentang kategori ini..."><?php echo htmlspecialchars($current_category['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="flex justify-end space-x-3 pt-6">
            <a href="categories.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
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
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>