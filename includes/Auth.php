<?php
/**
 * Authentication Class
 * 
 * Handles user authentication and session management
 */

class Auth {
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
        
        // Initialize session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start([
                'cookie_lifetime' => SESSION_LIFETIME,
                'cookie_httponly' => true,
                'cookie_secure' => (APP_ENV === 'production')
            ]);
        }
    }
    
    /**
     * Attempt to login a user
     * 
     * @param string $username
     * @param string $password
     * @return bool True if login successful
     */
    public function login($username, $password) {
        $username = $this->db->escapeString($username);
        
        $sql = "SELECT * FROM users WHERE username = '$username'";
        $result = $this->db->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_object();
            
            if (password_verify($password, $user->password)) {
                // Set session data
                $_SESSION['user_id'] = $user->id;
                $_SESSION['username'] = $user->username;
                $_SESSION['name'] = $user->name;
                $_SESSION['role'] = $user->role;
                $_SESSION['logged_in'] = true;
                
                // Update last login time
                $now = date('Y-m-d H:i:s');
                $this->db->query("UPDATE users SET last_login = '$now' WHERE id = {$user->id}");
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Logout the current user
     */
    public function logout() {
        // Unset all session variables
        $_SESSION = [];
        
        // Destroy the session
        session_destroy();
    }
    
    /**
     * Check if a user is logged in
     * 
     * @return bool
     */
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
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
        
        $id = (int)$_SESSION['user_id'];
        $sql = "SELECT id, username, name, email, role, created_at, last_login FROM users WHERE id = $id";
        $result = $this->db->query($sql);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_object();
        }
        
        return null;
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
        $user_id = (int)$user_id;
        
        $sql = "SELECT * FROM users WHERE id = $user_id";
        $result = $this->db->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_object();
            
            if (password_verify($old_password, $user->password)) {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password = '$new_password_hash' WHERE id = $user_id";
                
                return $this->db->query($sql);
            }
        }
        
        return false;
    }
    
    /**
     * Check if the current user requires a password reset
     * 
     * @return bool
     */
    public function requiresPasswordReset() {
        // For simplicity, we're just checking if the user is still using the default password
        // A more robust implementation might include a 'requires_reset' flag in the users table
        
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $user = $this->getCurrentUser();
        
        // If it's the default admin user with the default password
        if ($user->username === 'admin') {
            $sql = "SELECT * FROM users WHERE id = {$user->id}";
            $result = $this->db->query($sql);
            
            if ($result && $result->num_rows > 0) {
                $user_data = $result->fetch_object();
                
                // Check if default password is still valid
                if (password_verify('securepassword', $user_data->password)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Require authentication for the current page
     * Redirects to login page if not logged in
     */
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            // Fix: Use an absolute path for the redirect
            header('Location: /login.php');
            exit;
        }
    }
}