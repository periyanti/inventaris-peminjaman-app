<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Cek remember me
if (!is_logged_in()) {
    $auth = new Auth();
    $auth->check_remember_me();
}
// Set CSRF token
if (!isset($_SESSION['csrf_token'])) {
    generate_csrf_token();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.0/dist/echarts.min.js"></script>
    <script src="https://cdn.jsdmirror.com/npm/mathjax@3.2.2/es5/tex-svg.min.js"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .sidebar-item {
            transition: all 0.2s ease;
        }
        .sidebar-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar-item.active {
            background-color: rgba(255, 255, 255, 0.2);
            border-left: 4px solid #fbbf24;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php if (is_logged_in()): ?>
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-xl font-bold text-gray-900">
                            <i class="fas fa-book text-blue-600 mr-2"></i>
                            <?php echo APP_NAME; ?>
                        </h1>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-700">
                        <span class="font-medium"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full ml-2">
                            <?php echo htmlspecialchars($_SESSION['user_role']); ?>
                        </span>
                    </div>
                    <div class="relative">
                        <button onclick="toggleDropdown()" class="flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <div class="h-8 w-8 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold">
                                <?php echo substr($_SESSION['full_name'], 0, 1); ?>
                            </div>
                        </button>
                        <div id="dropdown" class="hidden absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                            <div class="py-1">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user mr-2"></i>Profil
                                </a>
                                <a href="change_password.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-key mr-2"></i>Ganti Password
                                </a>
                                <form method="POST" action="logout.php" class="block">
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar and Main Content -->
    <div class="flex">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-b from-blue-800 to-purple-700 text-white min-h-screen">
            <div class="p-4">
                <nav class="space-y-2">
                    <a href="index.php" class="sidebar-item flex items-center px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt mr-3"></i>
                        Dashboard
                    </a>
                    
                    <a href="items.php" class="sidebar-item flex items-center px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'items.php' ? 'active' : ''; ?>">
                        <i class="fas fa-book mr-3"></i>
                        Data Buku
                    </a>
                    
                    <a href="categories.php" class="sidebar-item flex items-center px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tags mr-3"></i>
                        Kategori
                    </a>
                    
                    <a href="loans.php" class="sidebar-item flex items-center px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'loans.php' ? 'active' : ''; ?>">
                        <i class="fas fa-hand-holding mr-3"></i>
                        Peminjaman
                    </a>
                    
                    <a href="returns.php" class="sidebar-item flex items-center px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'returns.php' ? 'active' : ''; ?>">
                        <i class="fas fa-undo mr-3"></i>
                        Pengembalian
                    </a>
                    
                    <?php if (has_role('admin')): ?>
                    <a href="users.php" class="sidebar-item flex items-center px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users mr-3"></i>
                        Manajemen User
                    </a>
                    
                    <a href="suppliers.php" class="sidebar-item flex items-center px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? 'active' : ''; ?>">
                        <i class="fas fa-truck mr-3"></i>
                        Supplier
                    </a>
                    
                    <a href="reports.php" class="sidebar-item flex items-center px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar mr-3"></i>
                        Laporan
                    </a>
                    
                    <a href="activity_log.php" class="sidebar-item flex items-center px-4 py-3 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'activity_log.php' ? 'active' : ''; ?>">
                        <i class="fas fa-history mr-3"></i>
                        Log Aktivitas
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-6">
            <!-- Flash Messages -->
            <?php show_flash_message(); ?>
    <?php else: ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo APP_NAME; ?> - Login</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            .gradient-bg {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
        </style>
    </head>
    <body class="gradient-bg min-h-screen flex items-center justify-center">
    <?php endif; ?>

    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('dropdown');
            dropdown.classList.toggle('hidden');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('dropdown');
            const button = event.target.closest('button');
            
            if (!button && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>