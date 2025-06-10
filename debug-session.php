<?php
/**
 * Debug Session Issues - Fixed Version
 */

// Include config first
if (file_exists('config/config.php')) {
    require_once 'config/config.php';
    echo "✓ Config loaded<br>";
} else {
    echo "✗ Config file not found!<br>";
}

if (file_exists('config/database.php')) {
    require_once 'config/database.php';
    echo "✓ Database config loaded<br>";
} else {
    echo "✗ Database config file not found!<br>";
}

session_start();

echo "<h2>Session Debug Information</h2>";

echo "<h3>PHP Session Info:</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Name: " . session_name() . "<br>";
echo "Expected Session Name: " . (defined('SESSION_NAME') ? SESSION_NAME : 'not defined') . "<br>";
echo "Session Status: " . session_status() . "<br>";
echo "Session Cookie Params: ";
var_dump(session_get_cookie_params());

echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Server Info:</h3>";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "<br>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "<br>";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set') . "<br>";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'not set') . "<br>";

echo "<h3>Cookie Info:</h3>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

echo "<h3>Config Constants:</h3>";
echo "SESSION_NAME: " . (defined('SESSION_NAME') ? SESSION_NAME : 'not defined') . "<br>";
echo "SESSION_LIFETIME: " . (defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 'not defined') . "<br>";
echo "SITE_NAME: " . (defined('SITE_NAME') ? SITE_NAME : 'not defined') . "<br>";
echo "APP_ENV: " . (defined('APP_ENV') ? APP_ENV : 'not defined') . "<br>";

echo "<h3>Database Constants:</h3>";
echo "DB_HOST: " . (defined('DB_HOST') ? 'defined' : 'NOT DEFINED') . "<br>";
echo "DB_NAME: " . (defined('DB_NAME') ? 'defined' : 'NOT DEFINED') . "<br>";
echo "DB_USER: " . (defined('DB_USER') ? 'defined' : 'NOT DEFINED') . "<br>";
echo "DB_PASSWORD: " . (defined('DB_PASSWORD') ? 'defined' : 'NOT DEFINED') . "<br>";

// Test authentication
echo "<h3>Authentication Test:</h3>";
try {
    if (defined('DB_HOST')) {
        require_once 'includes/Database.php';
        require_once 'includes/Auth.php';
        
        $auth = new Auth();
        echo "✓ Auth object created successfully<br>";
        echo "Is logged in: " . ($auth->isLoggedIn() ? 'YES' : 'NO') . "<br>";
        
        if ($auth->isLoggedIn()) {
            $user = $auth->getCurrentUser();
            if ($user) {
                echo "Current user: " . $user->username . " (" . $user->name . ")<br>";
            } else {
                echo "User data not found<br>";
            }
        }
    } else {
        echo "✗ Database constants not defined - cannot test authentication<br>";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>Actions:</h3>";
echo '<a href="login.php">Go to Login</a> | ';
echo '<a href="dashboard.php">Go to Dashboard</a> | ';
echo '<a href="?clear_session=1">Clear Session</a> | ';
echo '<a href="?fix_session=1">Fix Session Name</a>';

if (isset($_GET['clear_session'])) {
    session_destroy();
    echo "<br><strong>Session cleared!</strong>";
    echo '<br><a href="debug-session.php">Refresh</a>';
}

if (isset($_GET['fix_session'])) {
    // Destroy current session
    session_destroy();
    
    // Start new session with correct name
    if (defined('SESSION_NAME')) {
        session_name(SESSION_NAME);
    }
    session_start();
    
    echo "<br><strong>Session name fixed!</strong>";
    echo '<br><a href="debug-session.php">Refresh</a>';
}
?>