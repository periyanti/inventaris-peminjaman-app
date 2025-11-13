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
        redirect('suppliers.php');
    }
    
    try {
        if (isset($_POST['add'])) {
            // Add new supplier
            $name = sanitize_input($_POST['name']);
            $contact_person = sanitize_input($_POST['contact_person']);
            $phone = sanitize_input($_POST['phone']);
            $email = sanitize_input($_POST['email']);
            $address = sanitize_input($_POST['address']);
            
            // Validasi input
            if (empty($name)) {
                set_flash_message('error', 'Nama supplier harus diisi');
                redirect('suppliers.php?action=add');
            }
            
            if ($email && !is_valid_email($email)) {
                set_flash_message('error', 'Format email tidak valid');
                redirect('suppliers.php?action=add');
            }
            
            $query = "INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$name, $contact_person, $phone, $email, $address]);
            
            $supplier_id = $db->lastInsertId();
            log_activity('create', 'suppliers', $supplier_id, "Menambahkan supplier baru: $name");
            set_flash_message('success', 'Supplier berhasil ditambahkan');
            
        } elseif (isset($_POST['update'])) {
            // Update supplier
            $id = (int)$_POST['id'];
            $name = sanitize_input($_POST['name']);
            $contact_person = sanitize_input($_POST['contact_person']);
            $phone = sanitize_input($_POST['phone']);
            $email = sanitize_input($_POST['email']);
            $address = sanitize_input($_POST['address']);
            
            // Validasi input
            if (empty($name)) {
                set_flash_message('error', 'Nama supplier harus diisi');
                redirect('suppliers.php?action=edit&id=' . $id);
            }
            
            if ($email && !is_valid_email($email)) {
                set_flash_message('error', 'Format email tidak valid');
                redirect('suppliers.php?action=edit&id=' . $id);
            }
            
            $query = "UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$name, $contact_person, $phone, $email, $address, $id]);
            
            log_activity('update', 'suppliers', $id, "Memperbarui data supplier: $name");
            set_flash_message('success', 'Supplier berhasil diperbarui');
            redirect('suppliers.php');
            
        } elseif (isset($_POST['delete'])) {
            // Delete supplier
            $id = (int)$_POST['id'];
            
            // Cek apakah supplier masih digunakan
            $check_query = "SELECT COUNT(*) FROM items WHERE supplier_id = ? AND is_active = 1";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$id]);
            
            if ($check_stmt->fetchColumn() > 0) {
                set_flash_message('error', 'Supplier tidak dapat dihapus karena masih digunakan oleh buku');
            } else {
                $query = "DELETE FROM suppliers WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                
                log_activity('delete', 'suppliers', $id, 'Menghapus supplier');
                set_flash_message('success', 'Supplier berhasil dihapus');
            }
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Terjadi kesalahan: ' . $e->getMessage());
        redirect('suppliers.php');
    }
}

// Get single supplier for edit
$supplier = null;
if ($action === 'edit' && $id) {
    try {
        $query = "SELECT * FROM suppliers WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $supplier = $stmt->fetch();
        
        if (!$supplier) {
            set_flash_message('error', 'Supplier tidak ditemukan');
            redirect('suppliers.php');
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Gagal memuat data supplier');
        redirect('suppliers.php');
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = $_GET['search'] ?? '';
$items_per_page = ITEMS_PER_PAGE;

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(name LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total suppliers
try {
    $count_query = "SELECT COUNT(*) as total FROM suppliers $where_clause";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetch()['total'];
    
    $pagination = get_pagination($total_items, $page, $items_per_page);
    
    // Get suppliers for current page
    $query = "SELECT s.*, COUNT(i.id) as item_count 
              FROM suppliers s 
              LEFT JOIN items i ON s.id = i.supplier_id AND i.is_active = 1 
              $where_clause 
              GROUP BY s.id 
              ORDER BY s.name ASC 
              LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    $params = array_merge($params, [$items_per_page, $pagination['offset']]);
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash_message('error', 'Gagal memuat data: ' . $e->getMessage());
    $suppliers = [];
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Manajemen Supplier</h1>
    <p class="text-gray-600 mt-1">Kelola data supplier dalam perpustakaan</p>
</div>

<?php if ($action === 'list'): ?>
<!-- Search -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" class="flex gap-4">
        <div class="flex-1">
            <input type="text" 
                   name="search" 
                   placeholder="Cari supplier..." 
                   value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-search mr-2"></i>Cari
        </button>
        <a href="suppliers.php" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-refresh mr-2"></i>Reset
        </a>
    </form>
</div>

<!-- Action Buttons -->
<div class="flex justify-between items-center mb-6">
    <div class="text-sm text-gray-600">
        Menampilkan <?php echo count($suppliers); ?> dari <?php echo number_format($total_items); ?> supplier
    </div>
    <a href="suppliers.php?action=add" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
        <i class="fas fa-plus mr-2"></i>Tambah Supplier
    </a>
</div>

<!-- Suppliers Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Supplier</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kontak</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telepon</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Buku</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($suppliers as $supplier): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($supplier['name']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($supplier['address'] ?: '-'); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($supplier['contact_person'] ?: '-'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($supplier['phone'] ?: '-'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($supplier['email'] ?: '-'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            <?php echo $supplier['item_count']; ?> buku
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <a href="suppliers.php?action=view&id=<?php echo $supplier['id']; ?>" 
                               class="text-green-600 hover:text-green-900">
                                <i class="fas fa-eye"></i>
                            </a>
                            <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Apakah Anda yakin ingin menghapus supplier ini?')">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="id" value="<?php echo $supplier['id']; ?>">
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
        <a href="suppliers.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
        <a href="suppliers.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
           class="px-3 py-2 <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-lg hover:bg-blue-700 transition-colors">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $pagination['total_pages']): ?>
        <a href="suppliers.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" 
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
        <?php echo $action === 'add' ? 'Tambah Supplier Baru' : 'Edit Supplier'; ?>
    </h2>
    
    <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <?php if ($action === 'edit'): ?>
        <input type="hidden" name="id" value="<?php echo $supplier['id']; ?>">
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Nama Supplier *</label>
                <input type="text" 
                       name="name" 
                       id="name" 
                       value="<?php echo htmlspecialchars($supplier['name'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       required
                       placeholder="Masukkan nama supplier">
            </div>
            
            <div>
                <label for="contact_person" class="block text-sm font-medium text-gray-700 mb-2">Contact Person</label>
                <input type="text" 
                       name="contact_person" 
                       id="contact_person" 
                       value="<?php echo htmlspecialchars($supplier['contact_person'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Nama kontak person">
            </div>
            
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">No. Telepon</label>
                <input type="tel" 
                       name="phone" 
                       id="phone" 
                       value="<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Nomor telepon">
            </div>
            
            <div class="md:col-span-2">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                <input type="email" 
                       name="email" 
                       id="email" 
                       value="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Email supplier">
            </div>
            
            <div class="md:col-span-2">
                <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Alamat</label>
                <textarea name="address" 
                          id="address" 
                          rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                          placeholder="Alamat lengkap supplier..."><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></textarea>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3 pt-6">
            <a href="suppliers.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
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
<!-- View Supplier Details -->
<?php
// Get supplier details
if (!$supplier) {
    try {
        $query = "SELECT s.*, COUNT(i.id) as item_count 
                  FROM suppliers s 
                  LEFT JOIN items i ON s.id = i.supplier_id AND i.is_active = 1 
                  WHERE s.id = ? 
                  GROUP BY s.id";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $supplier = $stmt->fetch();
        
        if (!$supplier) {
            set_flash_message('error', 'Supplier tidak ditemukan');
            redirect('suppliers.php');
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Gagal memuat data supplier');
        redirect('suppliers.php');
    }
}
?>

<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-start mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Detail Supplier</h2>
        <a href="suppliers.php" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Kembali
        </a>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Nama Supplier</h3>
                <p class="mt-1 text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($supplier['name']); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Contact Person</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo htmlspecialchars($supplier['contact_person'] ?: '-'); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">No. Telepon</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo htmlspecialchars($supplier['phone'] ?: '-'); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Email</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo htmlspecialchars($supplier['email'] ?: '-'); ?></p>
            </div>
        </div>
        
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Alamat</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo nl2br(htmlspecialchars($supplier['address'] ?: '-')); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Jumlah Buku</h3>
                <span class="mt-1 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                    <?php echo $supplier['item_count']; ?> buku
                </span>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Tanggal Registrasi</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo date('d/m/Y H:i', strtotime($supplier['created_at'])); ?></p>
            </div>
            
            <div>
                <h3 class="text-sm font-medium text-gray-500">Terakhir Diupdate</h3>
                <p class="mt-1 text-lg text-gray-900"><?php echo date('d/m/Y H:i', strtotime($supplier['updated_at'])); ?></p>
            </div>
        </div>
    </div>
    
    <div class="flex justify-end space-x-3 mt-8 pt-6 border-t">
        <a href="suppliers.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Kembali
        </a>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>