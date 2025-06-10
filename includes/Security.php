<?php
/**
 * Security Class
 * 
 * Enhanced security features for the application
 */

class Security {
    private static $instance = null;
    private $db;
    
    /**
     * Singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->db = Database::getInstance();
        $this->initializeSecurity();
    }
    
    /**
     * Initialize security measures
     */
    private function initializeSecurity() {
        // Set secure session configuration
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_name(SESSION_NAME);
            session_start();
        }
        
        // Set security headers
        $this->setSecurityHeaders();
        
        // Check for suspicious activity
        $this->checkSuspiciousActivity();
    }
    
    /**
     * Set security headers
     */
    private function setSecurityHeaders() {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self'");
        
        // HSTS (only for HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Check for suspicious activity
     */
    private function checkSuspiciousActivity() {
        $ip = $this->getClientIP();
        
        // Check for too many requests
        $this->checkRateLimit($ip);
        
        // Log suspicious requests
        $this->logSuspiciousRequest($ip);
    }
    
    /**
     * Get client IP address
     */
    public function getClientIP() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Check rate limiting
     */
    private function checkRateLimit($ip) {
        $current_time = time();
        $window = 300; // 5 minutes
        $max_requests = 100; // Max requests per window
        
        // Create rate limit table if not exists
        $this->createRateLimitTable();
        
        // Clean old entries
        $this->db->query("DELETE FROM rate_limits WHERE created_at < " . ($current_time - $window));
        
        // Count recent requests from this IP
        $escaped_ip = $this->db->escapeString($ip);
        $result = $this->db->query("SELECT COUNT(*) as count FROM rate_limits WHERE ip = '$escaped_ip' AND created_at > " . ($current_time - $window));
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_object();
            
            if ($row->count >= $max_requests) {
                $this->blockRequest('Rate limit exceeded');
            }
        }
        
        // Log this request
        $this->db->query("INSERT INTO rate_limits (ip, created_at) VALUES ('$escaped_ip', $current_time)");
    }
    
    /**
     * Create rate limit table
     */
    private function createRateLimitTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `rate_limits` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `ip` varchar(45) NOT NULL,
          `created_at` bigint(20) NOT NULL,
          PRIMARY KEY (`id`),
          KEY `ip_time` (`ip`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $this->db->query($sql);
    }
    
    /**
     * Log suspicious requests
     */
    private function logSuspiciousRequest($ip) {
        $suspicious_patterns = [
            'wp-admin', 'phpmyadmin', '.env', 'config.php', 'wp-config.php',
            'admin.php', 'login.php', 'setup.php', 'install.php',
            '../', '..\\', '<script', 'javascript:', 'eval(',
            'SELECT ', 'UNION ', 'INSERT ', 'DELETE ', 'UPDATE ',
            'DROP ', 'CREATE ', 'ALTER ', 'EXEC'
        ];
        
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $query_string = $_SERVER['QUERY_STRING'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        foreach ($suspicious_patterns as $pattern) {
            if (stripos($request_uri, $pattern) !== false || 
                stripos($query_string, $pattern) !== false ||
                stripos($user_agent, $pattern) !== false) {
                
                $this->logSecurityEvent('suspicious_request', [
                    'ip' => $ip,
                    'uri' => $request_uri,
                    'query' => $query_string,
                    'user_agent' => $user_agent,
                    'pattern' => $pattern
                ]);
                
                break;
            }
        }
    }
    
    /**
     * Block suspicious request
     */
    private function blockRequest($reason) {
        $this->logSecurityEvent('blocked_request', [
            'ip' => $this->getClientIP(),
            'reason' => $reason,
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Too many requests']);
        exit;
    }
    
    /**
     * Log security events
     */
    private function logSecurityEvent($event_type, $data) {
        $this->createSecurityLogTable();
        
        $escaped_type = $this->db->escapeString($event_type);
        $escaped_data = $this->db->escapeString(json_encode($data));
        $timestamp = time();
        
        $this->db->query("INSERT INTO security_logs (event_type, event_data, created_at) VALUES ('$escaped_type', '$escaped_data', $timestamp)");
    }
    
    /**
     * Create security log table
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
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || !$token) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Sanitize input data
     */
    public function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        
        // Remove null bytes
        $data = str_replace("\0", '', $data);
        
        // Trim whitespace
        $data = trim($data);
        
        // Remove potentially dangerous characters
        $data = filter_var($data, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        
        return $data;
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encrypt($data) {
        if (!defined('SECURE_KEY')) {
            throw new Exception('SECURE_KEY not defined');
        }
        
        $cipher = 'AES-256-GCM';
        $key = hash('sha256', SECURE_KEY, true);
        $iv = random_bytes(12); // 96-bit IV for GCM
        
        $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        
        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }
        
        return base64_encode($iv . $tag . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decrypt($encryptedData) {
        if (!defined('SECURE_KEY')) {
            throw new Exception('SECURE_KEY not defined');
        }
        
        $data = base64_decode($encryptedData);
        
        if ($data === false || strlen($data) < 28) { // 12 + 16 minimum
            return false;
        }
        
        $cipher = 'AES-256-GCM';
        $key = hash('sha256', SECURE_KEY, true);
        
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $encrypted = substr($data, 28);
        
        $decrypted = openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        
        return $decrypted;
    }
    
    /**
     * Generate secure password
     */
    public function generateSecurePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Check password strength
     */
    public function checkPasswordStrength($password) {
        $score = 0;
        $feedback = [];
        
        // Length check
        if (strlen($password) >= 8) {
            $score++;
        } else {
            $feedback[] = __('password_too_short');
        }
        
        // Uppercase check
        if (preg_match('/[A-Z]/', $password)) {
            $score++;
        } else {
            $feedback[] = 'Add uppercase letters';
        }
        
        // Lowercase check
        if (preg_match('/[a-z]/', $password)) {
            $score++;
        } else {
            $feedback[] = 'Add lowercase letters';
        }
        
        // Number check
        if (preg_match('/[0-9]/', $password)) {
            $score++;
        } else {
            $feedback[] = 'Add numbers';
        }
        
        // Special character check
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $score++;
        } else {
            $feedback[] = 'Add special characters';
        }
        
        return [
            'score' => $score,
            'strength' => $this->getStrengthLabel($score),
            'feedback' => $feedback
        ];
    }
    
    /**
     * Get password strength label
     */
    private function getStrengthLabel($score) {
        switch ($score) {
            case 0:
            case 1:
                return 'Very Weak';
            case 2:
                return 'Weak';
            case 3:
                return 'Fair';
            case 4:
                return 'Good';
            case 5:
                return 'Strong';
            default:
                return 'Unknown';
        }
    }
    
    /**
     * Clean old security logs
     */
    public function cleanOldLogs($days = 30) {
        $cutoff = time() - ($days * 24 * 60 * 60);
        
        $this->db->query("DELETE FROM security_logs WHERE created_at < $cutoff");
        $this->db->query("DELETE FROM rate_limits WHERE created_at < $cutoff");
    }
}