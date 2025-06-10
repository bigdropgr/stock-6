<?php
/**
 * Enhanced Installation Script
 * 
 * Multi-step setup process for new installations
 */

session_start();

// Prevent access if already installed
if (file_exists('../config/config.php') && file_exists('../config/database.php')) {
    $config_content = file_get_contents('../config/config.php');
    if (strpos($config_content, 'INSTALLATION_COMPLETE') !== false) {
        die('System is already installed. Delete config files to reinstall.');
    }
}

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$error = '';
$success = '';

// Language selection
$lang = isset($_SESSION['install_lang']) ? $_SESSION['install_lang'] : 'en';
if (isset($_POST['language'])) {
    $lang = $_POST['language'];
    $_SESSION['install_lang'] = $lang;
}

// Simple translation function for installer
function i18n($key) {
    global $lang;
    
    $translations = [
        'en' => [
            'installation_wizard' => 'Installation Wizard',
            'language_selection' => 'Language Selection',
            'database_configuration' => 'Database Configuration',
            'woocommerce_api' => 'WooCommerce API Configuration',
            'admin_account' => 'Administrator Account',
            'installation_complete' => 'Installation Complete',
            'select_language' => 'Select your preferred language',
            'continue' => 'Continue',
            'back' => 'Back',
            'install' => 'Install',
            'database_host' => 'Database Host',
            'database_name' => 'Database Name',
            'database_user' => 'Database Username',
            'database_password' => 'Database Password',
            'test_connection' => 'Test Connection',
            'wc_store_url' => 'WooCommerce Store URL',
            'wc_consumer_key' => 'Consumer Key',
            'wc_consumer_secret' => 'Consumer Secret',
            'admin_username' => 'Admin Username',
            'admin_password' => 'Admin Password',
            'admin_name' => 'Full Name',
            'admin_email' => 'Email Address',
            'installation_success' => 'Installation completed successfully!',
            'login_to_continue' => 'You can now login to your inventory system.',
            'go_to_login' => 'Go to Login'
        ],
        'el' => [
            'installation_wizard' => 'Οδηγός Εγκατάστασης',
            'language_selection' => 'Επιλογή Γλώσσας',
            'database_configuration' => 'Ρύθμιση Βάσης Δεδομένων',
            'woocommerce_api' => 'Ρύθμιση WooCommerce API',
            'admin_account' => 'Λογαριασμός Διαχειριστή',
            'installation_complete' => 'Ολοκλήρωση Εγκατάστασης',
            'select_language' => 'Επιλέξτε την προτιμώμενη γλώσσα',
            'continue' => 'Συνέχεια',
            'back' => 'Πίσω',
            'install' => 'Εγκατάσταση',
            'database_host' => 'Διακομιστής Βάσης Δεδομένων',
            'database_name' => 'Όνομα Βάσης Δεδομένων',
            'database_user' => 'Όνομα Χρήστη Βάσης Δεδομένων',
            'database_password' => 'Κωδικός Πρόσβασης Βάσης Δεδομένων',
            'test_connection' => 'Δοκιμή Σύνδεσης',
            'wc_store_url' => 'URL Καταστήματος WooCommerce',
            'wc_consumer_key' => 'Consumer Key',
            'wc_consumer_secret' => 'Consumer Secret',
            'admin_username' => 'Όνομα Χρήστη Διαχειριστή',
            'admin_password' => 'Κωδικός Πρόσβασης Διαχειριστή',
            'admin_name' => 'Πλήρες Όνομα',
            'admin_email' => 'Διεύθυνση Email',
            'installation_success' => 'Η εγκατάσταση ολοκληρώθηκε επιτυχώς!',
            'login_to_continue' => 'Μπορείτε τώρα να συνδεθείτε στο σύστημα διαχείρισης αποθέματος.',
            'go_to_login' => 'Μετάβαση στη Σύνδεση'
        ]
    ];
    
    return isset($translations[$lang][$key]) ? $translations[$lang][$key] : $key;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1: // Language selection
            if (isset($_POST['language'])) {
                header('Location: install.php?step=2');
                exit;
            }
            break;
            
        case 2: // Database configuration
            if (isset($_POST['test_db']) || isset($_POST['save_db'])) {
                $db_host = $_POST['db_host'] ?? '';
                $db_name = $_POST['db_name'] ?? '';
                $db_user = $_POST['db_user'] ?? '';
                $db_pass = $_POST['db_pass'] ?? '';
                
                // Test database connection
                try {
                    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
                    if ($conn->connect_error) {
                        throw new Exception($conn->connect_error);
                    }
                    
                    // Store in session
                    $_SESSION['db_config'] = [
                        'host' => $db_host,
                        'name' => $db_name,
                        'user' => $db_user,
                        'pass' => $db_pass
                    ];
                    
                    if (isset($_POST['save_db'])) {
                        header('Location: install.php?step=3');
                        exit;
                    } else {
                        $success = 'Database connection successful!';
                    }
                    
                    $conn->close();
                } catch (Exception $e) {
                    $error = 'Database connection failed: ' . $e->getMessage();
                }
            }
            break;
            
        case 3: // WooCommerce API
            if (isset($_POST['test_wc']) || isset($_POST['save_wc'])) {
                $wc_url = rtrim($_POST['wc_url'] ?? '', '/');
                $wc_key = $_POST['wc_key'] ?? '';
                $wc_secret = $_POST['wc_secret'] ?? '';
                
                // Test WooCommerce API
                $test_url = $wc_url . '/wp-json/wc/v3/products?per_page=1&consumer_key=' . $wc_key . '&consumer_secret=' . $wc_secret;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $test_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($http_code === 200) {
                    $_SESSION['wc_config'] = [
                        'url' => $wc_url,
                        'key' => $wc_key,
                        'secret' => $wc_secret
                    ];
                    
                    if (isset($_POST['save_wc'])) {
                        header('Location: install.php?step=4');
                        exit;
                    } else {
                        $success = 'WooCommerce API connection successful!';
                    }
                } else {
                    $error = 'WooCommerce API connection failed. Please check your credentials.';
                }
            }
            break;
            
        case 4: // Admin account
            if (isset($_POST['create_admin'])) {
                $admin_username = $_POST['admin_username'] ?? '';
                $admin_password = $_POST['admin_password'] ?? '';
                $admin_name = $_POST['admin_name'] ?? '';
                $admin_email = $_POST['admin_email'] ?? '';
                
                if (empty($admin_username) || empty($admin_password) || empty($admin_name)) {
                    $error = 'All fields except email are required.';
                } elseif (strlen($admin_password) < 8) {
                    $error = 'Password must be at least 8 characters long.';
                } else {
                    $_SESSION['admin_config'] = [
                        'username' => $admin_username,
                        'password' => $admin_password,
                        'name' => $admin_name,
                        'email' => $admin_email
                    ];
                    
                    // Proceed to final installation
                    if (performInstallation()) {
                        header('Location: install.php?step=5');
                        exit;
                    } else {
                        $error = 'Installation failed. Please check file permissions.';
                    }
                }
            }
            break;
    }
}

function performInstallation() {
    try {
        $db_config = $_SESSION['db_config'];
        $wc_config = $_SESSION['wc_config'];
        $admin_config = $_SESSION['admin_config'];
        $lang = $_SESSION['install_lang'];
        
        // Create config files
        $config_content = generateConfigFile($wc_config, $lang);
        $db_content = generateDatabaseFile($db_config);
        
        if (!file_put_contents('../config/config.php', $config_content)) {
            throw new Exception('Could not write config.php');
        }
        
        if (!file_put_contents('../config/database.php', $db_content)) {
            throw new Exception('Could not write database.php');
        }
        
        // Create database tables
        $conn = new mysqli($db_config['host'], $db_config['user'], $db_config['pass'], $db_config['name']);
        if ($conn->connect_error) {
            throw new Exception($conn->connect_error);
        }
        
        // Create tables
        createDatabaseTables($conn);
        
        // Create admin user
        createAdminUser($conn, $admin_config);
        
        $conn->close();
        
        // Clear session
        unset($_SESSION['db_config'], $_SESSION['wc_config'], $_SESSION['admin_config']);
        
        return true;
    } catch (Exception $e) {
        error_log('Installation error: ' . $e->getMessage());
        return false;
    }
}

function generateConfigFile($wc_config, $lang) {
    $timezone = $lang === 'el' ? 'Europe/Athens' : 'UTC';
    
    return '<?php
/**
 * Main configuration file
 * Generated automatically during installation
 */

// Installation marker
define(\'INSTALLATION_COMPLETE\', true);

// Site settings
define(\'SITE_NAME\', \'Physical Store Inventory\');
define(\'SITE_URL\', \'https://\' . $_SERVER[\'HTTP_HOST\']);
define(\'ADMIN_EMAIL\', \'admin@\' . $_SERVER[\'HTTP_HOST\']);

// Application environment
define(\'APP_ENV\', \'production\');

// Default language
define(\'DEFAULT_LANGUAGE\', \'' . $lang . '\');

// Session settings
define(\'SESSION_NAME\', \'store_inventory_session\');
define(\'SESSION_LIFETIME\', 3600);

// WooCommerce API settings
define(\'WC_STORE_URL\', \'' . addslashes($wc_config['url']) . '\');
define(\'WC_CONSUMER_KEY\', \'' . addslashes($wc_config['key']) . '\');
define(\'WC_CONSUMER_SECRET\', \'' . addslashes($wc_config['secret']) . '\');
define(\'WC_API_VERSION\', \'wc/v3\');

// Low stock threshold default
define(\'DEFAULT_LOW_STOCK_THRESHOLD\', 5);

// Error reporting
error_reporting(0);
ini_set(\'display_errors\', 0);

// Set default timezone
date_default_timezone_set(\'' . $timezone . '\');

// Security settings
define(\'SECURE_KEY\', \'' . bin2hex(random_bytes(32)) . '\');
';
}

function generateDatabaseFile($db_config) {
    return '<?php
/**
 * Database configuration file
 * Generated automatically during installation
 */

// Database credentials
define(\'DB_HOST\', \'' . addslashes($db_config['host']) . '\');
define(\'DB_NAME\', \'' . addslashes($db_config['name']) . '\');
define(\'DB_USER\', \'' . addslashes($db_config['user']) . '\');
define(\'DB_PASSWORD\', \'' . addslashes($db_config['pass']) . '\');
define(\'DB_CHARSET\', \'utf8mb4\');
';
}

function createDatabaseTables($conn) {
    $sql_files = [
        'physical_inventory' => "CREATE TABLE IF NOT EXISTS `physical_inventory` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `product_id` bigint(20) NOT NULL,
          `parent_id` bigint(20) DEFAULT NULL,
          `product_type` varchar(20) NOT NULL DEFAULT 'simple',
          `variation_attributes` text DEFAULT NULL,
          `title` varchar(255) NOT NULL,
          `sku` varchar(100) NOT NULL,
          `category` varchar(255) DEFAULT NULL,
          `price` decimal(10,2) NOT NULL DEFAULT 0.00,
          `wholesale_price` decimal(10,2) NOT NULL DEFAULT 0.00,
          `stock` int(11) NOT NULL DEFAULT 0,
          `image_url` varchar(255) DEFAULT NULL,
          `last_updated` datetime NOT NULL,
          `created_at` datetime NOT NULL,
          `is_low_stock` tinyint(1) NOT NULL DEFAULT 0,
          `low_stock_threshold` int(11) NOT NULL DEFAULT 5,
          `notes` text DEFAULT NULL,
          `aisle` varchar(100) DEFAULT NULL,
          `shelf` varchar(100) DEFAULT NULL,
          `storage_notes` text DEFAULT NULL,
          `date_of_entry` date DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `product_id` (`product_id`),
          KEY `sku` (`sku`),
          KEY `parent_id` (`parent_id`),
          KEY `product_type` (`product_type`),
          KEY `idx_wholesale_price` (`wholesale_price`),
          KEY `idx_location` (`aisle`, `shelf`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        'sync_log' => "CREATE TABLE IF NOT EXISTS `sync_log` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `sync_date` datetime NOT NULL,
          `products_added` int(11) NOT NULL DEFAULT 0,
          `products_updated` int(11) NOT NULL DEFAULT 0,
          `status` varchar(50) NOT NULL,
          `details` text DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        'users' => "CREATE TABLE IF NOT EXISTS `users` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `username` varchar(50) NOT NULL,
          `password` varchar(255) NOT NULL,
          `name` varchar(100) NOT NULL,
          `email` varchar(100) DEFAULT NULL,
          `role` varchar(20) NOT NULL DEFAULT 'admin',
          `language` varchar(2) NOT NULL DEFAULT 'en',
          `created_at` datetime NOT NULL,
          `last_login` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        'deleted_variations' => "CREATE TABLE IF NOT EXISTS `deleted_variations` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `variation_id` bigint(20) NOT NULL,
          `deleted_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `variation_id` (`variation_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];
    
    foreach ($sql_files as $table => $sql) {
        if (!$conn->query($sql)) {
            throw new Exception("Error creating table $table: " . $conn->error);
        }
    }
}

function createAdminUser($conn, $admin_config) {
    $username = $conn->real_escape_string($admin_config['username']);
    $password = password_hash($admin_config['password'], PASSWORD_DEFAULT);
    $name = $conn->real_escape_string($admin_config['name']);
    $email = $conn->real_escape_string($admin_config['email']);
    $lang = $_SESSION['install_lang'];
    $current_date = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO `users` (`username`, `password`, `name`, `email`, `role`, `language`, `created_at`)
            VALUES ('$username', '$password', '$name', '$email', 'admin', '$lang', '$current_date')";
    
    if (!$conn->query($sql)) {
        throw new Exception('Error creating admin user: ' . $conn->error);
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo i18n('installation_wizard'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .install-container { max-width: 600px; margin: 50px auto; }
        .step-indicator { margin-bottom: 30px; }
        .step { padding: 10px 15px; margin: 0 5px; border-radius: 50px; }
        .step.active { background-color: #007bff; color: white; }
        .step.completed { background-color: #28a745; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="install-container">
            <div class="card shadow">
                <div class="card-header text-center">
                    <h3><?php echo i18n('installation_wizard'); ?></h3>
                    
                    <!-- Step indicator -->
                    <div class="step-indicator d-flex justify-content-center mt-3">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="step <?php echo ($i < $step) ? 'completed' : (($i == $step) ? 'active' : ''); ?>">
                            <?php echo $i; ?>
                        </span>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($step == 1): ?>
                    <!-- Step 1: Language Selection -->
                    <h5><?php echo i18n('language_selection'); ?></h5>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label"><?php echo i18n('select_language'); ?></label>
                            <select name="language" class="form-select" required>
                                <option value="en" <?php echo ($lang == 'en') ? 'selected' : ''; ?>>English</option>
                                <option value="el" <?php echo ($lang == 'el') ? 'selected' : ''; ?>>Ελληνικά</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><?php echo i18n('continue'); ?></button>
                    </form>
                    
                    <?php elseif ($step == 2): ?>
                    <!-- Step 2: Database Configuration -->
                    <h5><?php echo i18n('database_configuration'); ?></h5>
                    <form method="post">
                        <div class="mb-3">
                            <label for="db_host" class="form-label"><?php echo i18n('database_host'); ?></label>
                            <input type="text" class="form-control" name="db_host" value="<?php echo $_SESSION['db_config']['host'] ?? 'localhost'; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="db_name" class="form-label"><?php echo i18n('database_name'); ?></label>
                            <input type="text" class="form-control" name="db_name" value="<?php echo $_SESSION['db_config']['name'] ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="db_user" class="form-label"><?php echo i18n('database_user'); ?></label>
                            <input type="text" class="form-control" name="db_user" value="<?php echo $_SESSION['db_config']['user'] ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="db_pass" class="form-label"><?php echo i18n('database_password'); ?></label>
                            <input type="password" class="form-control" name="db_pass" value="<?php echo $_SESSION['db_config']['pass'] ?? ''; ?>">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="test_db" class="btn btn-outline-primary"><?php echo i18n('test_connection'); ?></button>
                            <button type="submit" name="save_db" class="btn btn-primary"><?php echo i18n('continue'); ?></button>
                            <a href="install.php?step=1" class="btn btn-secondary"><?php echo i18n('back'); ?></a>
                        </div>
                    </form>
                    
                    <?php elseif ($step == 3): ?>
                    <!-- Step 3: WooCommerce API -->
                    <h5><?php echo i18n('woocommerce_api'); ?></h5>
                    <form method="post">
                        <div class="mb-3">
                            <label for="wc_url" class="form-label"><?php echo i18n('wc_store_url'); ?></label>
                            <input type="url" class="form-control" name="wc_url" value="<?php echo $_SESSION['wc_config']['url'] ?? ''; ?>" placeholder="https://yourstore.com" required>
                        </div>
                        <div class="mb-3">
                            <label for="wc_key" class="form-label"><?php echo i18n('wc_consumer_key'); ?></label>
                            <input type="text" class="form-control" name="wc_key" value="<?php echo $_SESSION['wc_config']['key'] ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="wc_secret" class="form-label"><?php echo i18n('wc_consumer_secret'); ?></label>
                            <input type="text" class="form-control" name="wc_secret" value="<?php echo $_SESSION['wc_config']['secret'] ?? ''; ?>" required>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="test_wc" class="btn btn-outline-primary"><?php echo i18n('test_connection'); ?></button>
                            <button type="submit" name="save_wc" class="btn btn-primary"><?php echo i18n('continue'); ?></button>
                            <a href="install.php?step=2" class="btn btn-secondary"><?php echo i18n('back'); ?></a>
                        </div>
                    </form>
                    
                    <?php elseif ($step == 4): ?>
                    <!-- Step 4: Admin Account -->
                    <h5><?php echo i18n('admin_account'); ?></h5>
                    <form method="post">
                        <div class="mb-3">
                            <label for="admin_username" class="form-label"><?php echo i18n('admin_username'); ?></label>
                            <input type="text" class="form-control" name="admin_username" value="<?php echo $_SESSION['admin_config']['username'] ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="admin_password" class="form-label"><?php echo i18n('admin_password'); ?></label>
                            <input type="password" class="form-control" name="admin_password" required>
                            <div class="form-text">Minimum 8 characters</div>
                        </div>
                        <div class="mb-3">
                            <label for="admin_name" class="form-label"><?php echo i18n('admin_name'); ?></label>
                            <input type="text" class="form-control" name="admin_name" value="<?php echo $_SESSION['admin_config']['name'] ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="admin_email" class="form-label"><?php echo i18n('admin_email'); ?></label>
                            <input type="email" class="form-control" name="admin_email" value="<?php echo $_SESSION['admin_config']['email'] ?? ''; ?>">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="create_admin" class="btn btn-success"><?php echo i18n('install'); ?></button>
                            <a href="install.php?step=3" class="btn btn-secondary"><?php echo i18n('back'); ?></a>
                        </div>
                    </form>
                    
                    <?php elseif ($step == 5): ?>
                    <!-- Step 5: Installation Complete -->
                    <div class="text-center">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        <h5 class="mt-3"><?php echo i18n('installation_complete'); ?></h5>
                        <p class="text-muted"><?php echo i18n('installation_success'); ?></p>
                        <p><?php echo i18n('login_to_continue'); ?></p>
                        <a href="../login.php" class="btn btn-primary"><?php echo i18n('go_to_login'); ?></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>