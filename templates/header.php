<?php
// Include translation system first
require_once __DIR__ . '/../includes/i18n.php';

// Handle language switching BEFORE any output
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'el'])) {
    setLanguage($_GET['lang']);
    
    // Update user language preference in database if logged in
    if (isset($auth) && $auth->isLoggedIn()) {
        $current_user = $auth->getCurrentUser();
        if ($current_user) {
            $auth->updateLanguage($current_user->id, $_GET['lang']);
        }
    }
    
    // Create clean redirect URL preserving ALL parameters except 'lang'
    $current_url = $_SERVER['REQUEST_URI'];
    $url_parts = parse_url($current_url);
    $path = $url_parts['path'] ?? '';
    
    // Remove leading slash if present
    if (strpos($path, '/') === 0) {
        $path = substr($path, 1);
    }
    
    // Prevent redirect to debug page
    if ($path === 'debug-session.php') {
        $path = 'dashboard.php';
    }
    
    // Rebuild query string without 'lang' parameter
    $query_params = [];
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query_params);
        unset($query_params['lang']); // Remove lang parameter
    }
    
    // Build final redirect URL
    $redirect_url = $path;
    if (!empty($query_params)) {
        $redirect_url .= '?' . http_build_query($query_params);
    }
    
    header("Location: $redirect_url");
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    if (defined('SESSION_NAME')) {
        session_name(SESSION_NAME);
    }
    session_start();
}

// Check if user is logged in
$auth = new Auth();
$is_logged_in = $auth->isLoggedIn();
$current_user = $is_logged_in ? $auth->getCurrentUser() : null;

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Function to build language switch URLs preserving current parameters
function buildLanguageSwitchUrl($lang) {
    $current_url = $_SERVER['REQUEST_URI'];
    $url_parts = parse_url($current_url);
    
    // Get current query parameters
    $query_params = [];
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query_params);
    }
    
    // Add or update the lang parameter
    $query_params['lang'] = $lang;
    
    // Build the URL
    $path = $url_parts['path'] ?? '';
    return $path . '?' . http_build_query($query_params);
}
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php if ($is_logged_in): ?>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><?php echo SITE_NAME; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page === 'index.php' || $current_page === 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> <?php echo __('dashboard'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page === 'search.php') ? 'active' : ''; ?>" href="search.php">
                            <i class="fas fa-search"></i> <?php echo __('search'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page === 'sync.php') ? 'active' : ''; ?>" href="sync.php">
                            <i class="fas fa-sync"></i> <?php echo __('sync'); ?>
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <!-- Language Switcher -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-globe"></i> 
                            <?php 
                            $current_lang = getCurrentLanguage();
                            if ($current_lang === 'el') {
                                echo 'GR';
                            } else {
                                echo 'EN';
                            }
                            ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo buildLanguageSwitchUrl('en'); ?>"><?php echo __('language_english'); ?></a></li>
                            <li><a class="dropdown-item" href="<?php echo buildLanguageSwitchUrl('el'); ?>"><?php echo __('language_greek'); ?></a></li>
                        </ul>
                    </li>
                    
                    <!-- User Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($current_user->name); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card"></i> <?php echo __('profile'); ?></a></li>
                            <li><a class="dropdown-item" href="change-password.php"><i class="fas fa-key"></i> <?php echo __('change_password'); ?></a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i> <?php echo function_exists('__') ? __('logout') : 'Logout'; ?>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <?php
        // Display flash messages if any
        $flash_message = get_flash_message();
        if ($flash_message) {
            $alert_class = 'alert-info';
            
            if ($flash_message['type'] === 'success') {
                $alert_class = 'alert-success';
            } elseif ($flash_message['type'] === 'danger' || $flash_message['type'] === 'error') {
                $alert_class = 'alert-danger';
            } elseif ($flash_message['type'] === 'warning') {
                $alert_class = 'alert-warning';
            }
            
            echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
            echo $flash_message['message'];
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
        }
        ?>