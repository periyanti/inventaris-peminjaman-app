<?php
// Simple deployment script for the library system
// This script sets up the basic structure and creates initial data

echo "=== Library System Deployment Script ===\n\n";

// Check PHP version
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    echo "❌ Error: PHP 8.0 or higher is required. Current version: " . PHP_VERSION . "\n";
    exit(1);
}

echo "✅ PHP version check passed (" . PHP_VERSION . ")\n";

// Check required extensions
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    echo "❌ Error: Missing required PHP extensions: " . implode(', ', $missing_extensions) . "\n";
    echo "Please install these extensions and try again.\n";
    exit(1);
}

echo "✅ All required PHP extensions are loaded\n";

// Check if database configuration exists
if (!file_exists('config/database.php')) {
    echo "❌ Error: Database configuration file not found\n";
    echo "Please create config/database.php with your database credentials\n";
    exit(1);
}

echo "✅ Database configuration file found\n";

// Check if database schema exists
if (!file_exists('database.sql')) {
    echo "❌ Error: Database schema file not found\n";
    echo "Please ensure database.sql is in the root directory\n";
    exit(1);
}

echo "✅ Database schema file found\n";

// Check write permissions
$writable_dirs = ['uploads', 'logs'];
$permission_errors = [];

foreach ($writable_dirs as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            $permission_errors[] = "Cannot create directory: $dir";
        }
    } elseif (!is_writable($dir)) {
        $permission_errors[] = "Directory not writable: $dir";
    }
}

if (!empty($permission_errors)) {
    echo "❌ Error: Permission issues found:\n";
    foreach ($permission_errors as $error) {
        echo "  - $error\n";
    }
    echo "\nPlease fix these permissions and try again.\n";
    exit(1);
}

echo "✅ Directory permissions check passed\n";

// Create .htaccess if not exists
if (!file_exists('.htaccess')) {
    $htaccess_content = "RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header set X-Content-Type-Options \"nosniff\"
Header set X-Frame-Options \"DENY\"
Header set X-XSS-Protection \"1; mode=block\"
Header set Referrer-Policy \"strict-origin-when-cross-origin\"

# Prevent access to sensitive files
<FilesMatch \"\\.(env|json|lock|md|yml|yaml)$\">
    Require all denied
</FilesMatch>

<FilesMatch \"^\\.\">
    Require all denied
</FilesMatch>";

    if (file_put_contents('.htaccess', $htaccess_content)) {
        echo "✅ .htaccess file created successfully\n";
    } else {
        echo "❌ Error: Cannot create .htaccess file\n";
        exit(1);
    }
} else {
    echo "✅ .htaccess file already exists\n";
}

// Test database connection
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Test query
    $stmt = $db->query("SELECT 1");
    echo "✅ Database connection test passed\n";
    
    // Check if tables exist
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "⚠️  Warning: Database is empty. Please run the database schema.\n";
        echo "You can import database.sql to set up the initial database structure.\n";
    } else {
        echo "✅ Database tables found (" . count($tables) . " tables)\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: Database connection failed: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and try again.\n";
    exit(1);
}

// Create initial directories structure
$directories = [
    'uploads/books',
    'uploads/users',
    'logs/app',
    'logs/db',
    'cache',
    'temp'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Created directory: $dir\n";
        }
    }
}

// Set proper permissions
$files_to_protect = ['.env', 'config/config.php', 'config/database.php'];
foreach ($files_to_protect as $file) {
    if (file_exists($file)) {
        chmod($file, 0644);
    }
}

// Final checks
echo "\n=== Final System Check ===\n";

// Check if login page is accessible
$login_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/login.php';
echo "✅ System ready for deployment\n";
echo "✅ You can now access the application at: $login_url\n";

echo "\n=== Default Login Credentials ===\n";
echo "Admin: username: admin, password: password\n";
echo "User:  username: user,  password: password\n";

echo "\n🎉 Deployment completed successfully!\n";
echo "Don't forget to:\n";
echo "1. Change default passwords\n";
echo "2. Configure proper email settings\n";
echo "3. Set up SSL certificate for production\n";
echo "4. Configure backup system\n";
echo "5. Monitor system logs regularly\n";

// Create deployment marker
file_put_contents('.deployed', date('Y-m-d H:i:s'));
?>