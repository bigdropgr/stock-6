<?php
/**
 * Login Page - Fixed Structure
 */

// Include required files FIRST
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/functions.php';

// Include translation system if available
if (file_exists('includes/i18n.php')) {
    require_once 'includes/i18n.php';
}

// Initialize authentication
$auth = new Auth();

// If user is already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// MOVE ALL FORM PROCESSING TO THE TOP
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($username) || empty($password)) {
        $error = function_exists('__') ? __('Please enter both username and password') : 'Please enter both username and password';
    } else {
        // Attempt to login
        if ($auth->login($username, $password)) {
            // Check if there's an intended destination
            $redirect_to = isset($_SESSION['intended_url']) ? $_SESSION['intended_url'] : 'dashboard.php';
            unset($_SESSION['intended_url']);
            
            // Clean the redirect URL to prevent issues
            if (strpos($redirect_to, '/') === 0) {
                $redirect_to = substr($redirect_to, 1); // Remove leading slash
            }
            
            // REDIRECT HAPPENS HERE - BEFORE ANY HTML
            header('Location: ' . $redirect_to);
            exit;
        } else {
            $error = function_exists('__') ? __('invalid_credentials') : 'Invalid username or password';
        }
    }
}

// Handle language switching BEFORE HTML
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'el'])) {
    if (function_exists('setLanguage')) {
        setLanguage($_GET['lang']);
    }
    // Redirect to login page without language parameter
    header('Location: login.php');
    exit;
}

// Set page title
$page_title = (function_exists('__') ? __('login') : 'Login') . ' - ' . SITE_NAME;

// NOW START THE HTML OUTPUT
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('getCurrentLanguage') ? getCurrentLanguage() : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="login-container bg-white">
            <div class="text-center mb-4">
                <h1 class="h3"><?php echo SITE_NAME; ?></h1>
                <p class="text-muted"><?php echo function_exists('__') ? __('Physical Store Inventory Management') : 'Physical Store Inventory Management'; ?></p>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form method="post" action="login.php">
                <div class="mb-3">
                    <label for="username" class="form-label"><?php echo function_exists('__') ? __('username') : 'Username'; ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="<?php echo function_exists('__') ? __('Enter your username') : 'Enter your username'; ?>" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label"><?php echo function_exists('__') ? __('password') : 'Password'; ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="<?php echo function_exists('__') ? __('Enter your password') : 'Enter your password'; ?>" required>
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg"><?php echo function_exists('__') ? __('login') : 'Login'; ?></button>
                </div>
            </form>
            
            <!-- Language switcher for login page -->
            <?php if (function_exists('getCurrentLanguage')): ?>
            <div class="text-center mt-4">
                <div class="btn-group" role="group">
                    <a href="?lang=en" class="btn btn-outline-secondary btn-sm <?php echo getCurrentLanguage() === 'en' ? 'active' : ''; ?>">
                        EN English
                    </a>
                    <a href="?lang=el" class="btn btn-outline-secondary btn-sm <?php echo getCurrentLanguage() === 'el' ? 'active' : ''; ?>">
                        EL Ελληνικά
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <p class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?></p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>