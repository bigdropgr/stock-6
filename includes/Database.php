<?php
/**
 * Database Class
 * 
 * Handles database connections and operations
 */
class Database {
    private $host;
    private $user;
    private $password;
    private $db_name;
    private $conn;
    private static $instance;
    
    /**
     * Constructor - private to implement singleton pattern
     */
    private function __construct() {
        $this->host = DB_HOST;
        $this->user = DB_USER;
        $this->password = DB_PASSWORD;
        $this->db_name = DB_NAME;
        $this->connect();
    }
    
    /**
     * Get Database instance (Singleton pattern)
     * 
     * @return Database
     */
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    /**
     * Connect to database
     */
    private function connect() {
        $this->conn = new mysqli($this->host, $this->user, $this->password, $this->db_name);
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
        
        $this->conn->set_charset(DB_CHARSET);
    }
    
    /**
     * Get database connection
     * 
     * @return mysqli
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Execute query
     * 
     * @param string $sql
     * @return mysqli_result|bool
     */
    public function query($sql) {
        return $this->conn->query($sql);
    }
    
    /**
     * Prepare statement
     * 
     * @param string $sql
     * @return mysqli_stmt
     */
    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }
    
    /**
     * Escape string
     * 
     * @param string $string
     * @return string
     */
    public function escapeString($string) {
        return $this->conn->real_escape_string($string);
    }
    
    /**
     * Get last insert ID
     * 
     * @return int
     */
    public function lastInsertId() {
        return $this->conn->insert_id;
    }
    
    /**
     * Get error
     * 
     * @return string
     */
    public function getError() {
        return $this->conn->error;
    }
    
    /**
     * Begin transaction
     * 
     * @return bool
     */
    public function beginTransaction() {
        return $this->conn->begin_transaction();
    }
    
    /**
     * Commit transaction
     * 
     * @return bool
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     * Rollback transaction
     * 
     * @return bool
     */
    public function rollback() {
        return $this->conn->rollback();
    }
    
    /**
     * Close connection
     */
    public function close() {
        $this->conn->close();
    }
}