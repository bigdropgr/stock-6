<?php
/**
 * Authentication Class - Complete Rewrite
 * 
 * Handles user authentication and session management with proper error handling
 */

class Auth {
    private $db;
    private $security;
    private $session_started = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        try {
            $this->db = Database::getInstance();
        } catch (Exception $e) {
            error_log("Auth: Database connection failed - " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
        
        // Load Security class if available
        if (class_exists('Security')) {
            $this->security = Security::getInstance();
        }
        
        // Initialize session
        $this->initializeSession();
        
        // Initialize language if available
        if (class_exists('Language') && $this->isLoggedIn()) {
            $this->initializeUserLanguage();
        }
    }
    
    /**
     * Initialize session properly
     */
    private function initializeSession() {
        // Don't start session if headers already sent
        if (headers_sent()) {
            error_log("Auth: Cannot start session - headers already sent");
            return false;
        }
        
        // Don't start if session already active
        if (session_status() !== PHP_SESSION_NONE) {
            $this->session_started = true;
            return true;
        }
        
        try {
            // Set session name if defined
            if (defined('SESSION_NAME')) {
                session_name(SESSION_NAME);
            }
            
            // Configure session parameters
            $session_config = [
                'cookie_lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600,
                'cookie_httponly' => true,
                'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'use_strict_mode' => true,
                'use_only_cookies' => true,
                'cookie_samesite' => 'Strict'
            ];
            
            // Start session
            if (session_start($session_config)) {
                $this->session_started = true;
                
                // Regenerate session ID periodically for security
                if (!isset($_SESSION['last_regeneration'])) {
                    $_SESSION['last_regeneration'] = time();
                } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                    session_regenerate_id(true);
                    $_SESSION['last_regeneration'] = time();
                }
                
                return true;
            }
        } catch (Exception $e) {
            error_log("Auth: Session start failed - " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Initialize user language preference
     */
    private function initializeUserLanguage() {
        if ($this->isLoggedIn()) {
            $user = $this->getCurrentUser();
            if ($user && isset($user->language) && function_exists('setLanguage')) {
                setLanguage($user->language);
            }
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        if ($this->security && method_exists($this->security, 'getClientIP')) {
            return $this->security->getClientIP();
        }
        
        // Fallback IP detection
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR', 
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Sanitize input data
     */
    private function sanitizeInput($data) {
        if ($this->security && method_exists($this->security, 'sanitizeInput')) {
            return $this->security->sanitizeInput($data);
        }
        
        // Fallback sanitization
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        
        // Remove null bytes and trim
        $data = str_replace("\0", '', trim($data));
        
        // Basic sanitization
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Check if account is locked due to failed attempts
     */
    private function isAccountLocked($username) {
        $max_attempts = 5;
        $lockout_time = 900; // 15 minutes
        $current_time = time();
        
        try {
            $this->createFailedAttemptsTable();
            
            $escaped_username = $this->db->escapeString($username);
            $cutoff_time = $current_time - $lockout_time;
            
            $sql = "SELECT COUNT(*) as attempts FROM failed_login_attempts 
                    WHERE username = '$escaped_username' 
                    AND created_at > $cutoff_time";
            
            $result = $this->db->query($sql);
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_object();
                return $row->attempts >= $max_attempts;
            }
        } catch (Exception $e) {
            error_log("Auth: Error checking account lock - " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Log failed login attempt
     */
    private function logFailedAttempt($username, $reason) {
        try {
            $this->createFailedAttemptsTable();
            
            $escaped_username = $this->db->escapeString($username);
            $escaped_reason = $this->db->escapeString($reason);
            $ip = $this->getClientIP();
            $escaped_ip = $this->db->escapeString($ip);
            $current_time = time();
            
            $sql = "INSERT INTO failed_login_attempts (username, ip, reason, created_at) 
                    VALUES ('$escaped_username', '$escaped_ip', '$escaped_reason', $current_time)";
            
            $this->db->query($sql);
            
            // Log security event
            $this->logSecurityEvent('failed_login', [
                'username' => $username,
                'ip' => $ip,
                'reason' => $reason
            ]);
        } catch (Exception $e) {
            error_log("Auth: Error logging failed attempt - " . $e->getMessage());
        }
    }
    
    /**
     * Clear failed attempts for user
     */
    private function clearFailedAttempts($username) {
        try {
            $escaped_username = $this->db->escapeString($username);
            $this->db->query("DELETE FROM failed_login_attempts WHERE username = '$escaped_username'");
        } catch (Exception $e) {
            error_log("Auth: Error clearing failed attempts - " . $e->getMessage());
        }
    }
    
    /**
     * Create failed attempts table if not exists
     */
    private function createFailedAttemptsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `failed_login_attempts` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `username` varchar(50) NOT NULL,
          `ip` varchar(45) NOT NULL,
          `reason` varchar(100) NOT NULL,
          `created_at` bigint(20) NOT NULL,
          PRIMARY KEY (`id`),
          KEY `username_time` (`username`, `created_at`),
          KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $this->db->query($sql);
    }
    
    /**
     * Log security event
     */
    private function logSecurityEvent($event_type, $data) {
        try {
            $this->createSecurityLogTable();
            
            $escaped_type = $this->db->escapeString($event_type);
            $escaped_data = $this->db->escapeString(json_encode($data));
            $timestamp = time();
            
            $sql = "INSERT INTO security_logs (event_type, event_data, created_at) 
                    VALUES ('$escaped_type', '$escaped_data', $timestamp)";
            
            $this->db->query($sql);
        } catch (Exception $e) {
            error_log("Auth: Error logging security event - " . $e->getMessage());
        }
    }
    
    /**
     * Create security log table if not exists
     */
    private function createSecurityLogTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `security_logs` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `event_type` varchar(50) NOT NULL,
          `event_data` text NOT NULL,
          `created_at` bigint(20) NOT NULL,
          PRIMARY KEY (`id`),
          KEY `event_type` (`event_type`),
          KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $this->db->query($sql);
    }
    
    /**
     * Attempt to login a user
     * 
     * @param string $username
     * @param string $password
     * @return bool True if login successful
     */
    public function login($username, $password) {
        // Validate input
        if (empty($username) || empty($password)) {
            return false;
        }
        
        // Sanitize input
        $username = $this->sanitizeInput($username);
        $username = $this->db->escapeString($username);
        
        // Check for account lockout
        if ($this->isAccountLocked($username)) {
            $this->logFailedAttempt($username, 'Account locked');
            return false;
        }
        
        try {
            $sql = "SELECT * FROM users WHERE username = '$username'";
            $result = $this->db->query($sql);
            
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_object();
                
                if (password_verify($password, $user->password)) {
                    // Clear failed attempts
                    $this->clearFailedAttempts($username);
                    
                    // Ensure session is started
                    if (!$this->session_started) {
                        $this->initializeSession();
                    }
                    
                    // Set session data
                    $_SESSION['user_id'] = $user->id;
                    $_SESSION['username'] = $user->username;
                    $_SESSION['name'] = $user->name;
                    $_SESSION['role'] = $user->role;
                    $_SESSION['language'] = $user->language ?? 'en';
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    
                    // Generate CSRF token if Security class is available
                    if ($this->security && method_exists($this->security, 'generateCSRFToken')) {
                        $this->security->generateCSRFToken();
                    }
                    
                    // Set user language if function exists
                    if (function_exists('setLanguage')) {
                        setLanguage($user->language ?? 'en');
                    }
                    
                    // Update last login time
                    $now = date('Y-m-d H:i:s');
                    $ip = $this->getClientIP();
                    $escaped_ip = $this->db->escapeString($ip);
                    
                    $update_sql = "UPDATE users SET last_login = '$now', last_ip = '$escaped_ip' WHERE id = {$user->id}";
                    $this->db->query($update_sql);
                    
                    // Log successful login
                    $this->logSecurityEvent('successful_login', [
                        'user_id' => $user->id,
                        'username' => $user->username,
                        'ip' => $ip
                    ]);
                    
                    return true;
                } else {
                    $this->logFailedAttempt($username, 'Invalid password');
                }
            } else {
                $this->logFailedAttempt($username, 'User not found');
            }
        } catch (Exception $e) {
            error_log("Auth: Login error - " . $e->getMessage());
            $this->logFailedAttempt($username, 'System error');
        }
        
        return false;
    }
    
    /**
     * Logout the current user
     */
    public function logout() {
        // Log logout event
        if ($this->isLoggedIn()) {
            $this->logSecurityEvent('logout', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'username' => $_SESSION['username'] ?? null,
                'ip' => $this->getClientIP()
            ]);
        }
        
        // Clear session data
        if ($this->session_started) {
            $_SESSION = [];
            
            // Destroy session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Destroy session
            session_destroy();
            $this->session_started = false;
        }
    }
    
    /**
     * Check if a user is logged in
     * 
     * @return bool
     */
    public function isLoggedIn() {
        // Check if session is available
        if (!$this->session_started || !isset($_SESSION['logged_in'])) {
            return false;
        }
        
        // Check basic session validity
        if ($_SESSION['logged_in'] !== true || empty($_SESSION['user_id'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['login_time'])) {
            $session_lifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600;
            $session_duration = time() - $_SESSION['login_time'];
            
            if ($session_duration > $session_lifetime) {
                $this->logout();
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get the current user
     * 
     * @return object|null
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $id = (int)$_SESSION['user_id'];
            $sql = "SELECT id, username, name, email, role, language, created_at, last_login, last_ip 
                    FROM users WHERE id = $id";
            $result = $this->db->query($sql);
            
            if ($result && $result->num_rows > 0) {
                return $result->fetch_object();
            }
        } catch (Exception $e) {
            error_log("Auth: Error getting current user - " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Update user profile
     * 
     * @param int $user_id
     * @param string $name
     * @param string $email
     * @return bool
     */
    public function updateProfile($user_id, $name, $email = null) {
        try {
            $user_id = (int)$user_id;
            $name = $this->sanitizeInput($name);
            $escaped_name = $this->db->escapeString($name);
            
            $updates = ["name = '$escaped_name'"];
            
            if ($email !== null) {
                $email = $this->sanitizeInput($email);
                $escaped_email = $this->db->escapeString($email);
                $updates[] = "email = '$escaped_email'";
            }
            
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = $user_id";
            $result = $this->db->query($sql);
            
            if ($result) {
                // Update session data
                $_SESSION['name'] = $name;
                
                // Log profile update
                $this->logSecurityEvent('profile_updated', [
                    'user_id' => $user_id,
                    'ip' => $this->getClientIP()
                ]);
                
                return true;
            }
        } catch (Exception $e) {
            error_log("Auth: Error updating profile - " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Change a user's password
     * 
     * @param int $user_id
     * @param string $old_password
     * @param string $new_password
     * @return bool
     */
    public function changePassword($user_id, $old_password, $new_password) {
        try {
            $user_id = (int)$user_id;
            
            // Basic password validation
            if (strlen($new_password) < 8) {
                return false;
            }
            
            $sql = "SELECT * FROM users WHERE id = $user_id";
            $result = $this->db->query($sql);
            
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_object();
                
                if (password_verify($old_password, $user->password)) {
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $escaped_hash = $this->db->escapeString($new_password_hash);
                    
                    $update_sql = "UPDATE users SET password = '$escaped_hash' WHERE id = $user_id";
                    $result = $this->db->query($update_sql);
                    
                    if ($result) {
                        // Log password change
                        $this->logSecurityEvent('password_changed', [
                            'user_id' => $user_id,
                            'ip' => $this->getClientIP()
                        ]);
                        
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Auth: Error changing password - " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Update user language preference
     * 
     * @param int $user_id
     * @param string $language
     * @return bool
     */
    public function updateLanguage($user_id, $language) {
        if (!in_array($language, ['en', 'el'])) {
            return false;
        }
        
        try {
            $user_id = (int)$user_id;
            $escaped_language = $this->db->escapeString($language);
            
            $sql = "UPDATE users SET language = '$escaped_language' WHERE id = $user_id";
            $result = $this->db->query($sql);
            
            if ($result) {
                $_SESSION['language'] = $language;
                
                if (function_exists('setLanguage')) {
                    setLanguage($language);
                }
                
                return true;
            }
        } catch (Exception $e) {
            error_log("Auth: Error updating language - " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Check if the current user requires a password reset
     * 
     * @return bool
     */
    public function requiresPasswordReset() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        try {
            $user = $this->getCurrentUser();
            
            // Check if it's the default admin user with default password
            if ($user && $user->username === 'admin') {
                $sql = "SELECT password FROM users WHERE id = {$user->id}";
                $result = $this->db->query($sql);
                
                if ($result && $result->num_rows > 0) {
                    $user_data = $result->fetch_object();
                    
                    // Check if default password is still valid
                    if (password_verify('securepassword', $user_data->password)) {
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Auth: Error checking password reset requirement - " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Require authentication for the current page
     * Redirects to login page if not logged in
     */
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            // Get current page for redirect after login
            $current_page = $_SERVER['REQUEST_URI'] ?? '';
            
            // Clean the URL
            if (strpos($current_page, '/') === 0) {
                $current_page = substr($current_page, 1);
            }
            
            // Don't save certain pages as intended destination
            $excluded_pages = ['login.php', 'index.php', 'logout.php', 'debug-session.php', 'fix-session.php'];
            $page_name = basename($current_page);
            
            if (!in_array($page_name, $excluded_pages) && !empty($current_page)) {
                $_SESSION['intended_url'] = $current_page;
            }
            
            // Redirect to login
            if (!headers_sent()) {
                header('Location: login.php');
                exit;
            } else {
                // Fallback if headers already sent
                echo '<script>window.location.href="login.php";</script>';
                echo '<noscript><meta http-equiv="refresh" content="0;url=login.php"></noscript>';
                exit;
            }
        }
    }
    
    /**
     * Clean old security logs and failed attempts
     */
    public function cleanOldLogs($days = 30) {
        try {
            $cutoff = time() - ($days * 24 * 60 * 60);
            
            $this->db->query("DELETE FROM security_logs WHERE created_at < $cutoff");
            $this->db->query("DELETE FROM failed_login_attempts WHERE created_at < $cutoff");
            
            return true;
        } catch (Exception $e) {
            error_log("Auth: Error cleaning old logs - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get session status for debugging
     */
    public function getSessionStatus() {
        return [
            'session_started' => $this->session_started,
            'session_id' => session_id(),
            'session_name' => session_name(),
            'logged_in' => $this->isLoggedIn(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null
        ];
    }
}