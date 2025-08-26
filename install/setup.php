<?php
echo "=== Coaching Center HR System Installation ===\n";

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0') < 0) {
    die("Error: PHP 7.4.0 or higher is required. Current version: " . PHP_VERSION . "\n");
}

// Check required extensions
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'zip', 'gd'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    die("Error: Missing required PHP extensions: " . implode(', ', $missing_extensions) . "\n");
}

echo "✓ PHP version check passed\n";
echo "✓ Required extensions check passed\n";

// Create directories
$directories = [
    'assets/uploads',
    'assets/uploads/cvs',
    'assets/uploads/profile_pics',
    'logs'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✓ Created directory: $dir\n";
    }
}

// Set permissions
chmod('assets/uploads', 0755);
chmod('assets/uploads/cvs', 0755);
chmod('assets/uploads/profile_pics', 0755);

echo "✓ Directory permissions set\n";

// Install database tables
echo "Installing database tables...\n";

require_once '../config/install.php';
$installer = new Installer();

if ($installer->createTables()) {
    echo "✓ Database tables created successfully\n";
    
    if ($installer->createDefaultAdmin()) {
        echo "✓ Default admin user created\n";
        echo "  Username: admin\n";
        echo "  Email: admin@coachingcenter.com\n";
        echo "  Password: admin123\n";
        echo "  ⚠️ Please change the default password after first login!\n";
    }
} else {
    echo "✗ Error creating database tables\n";
}

echo "\n=== Installation Complete ===\n";
echo "Please configure your database settings in config/config.php\n";
echo "Access your application at: " . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : 'your-domain') . "\n";
?>