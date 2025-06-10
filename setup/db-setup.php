<?php
/**
 * Database setup script
 * 
 * This script creates the necessary database tables for the physical store inventory system.
 * Run this script once to set up the database structure.
 */

// Include configuration
require_once '../config/config.php';
require_once '../config/database.php';

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SQL to create the physical_inventory table
$sql_physical_inventory = "
CREATE TABLE IF NOT EXISTS `physical_inventory` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `last_updated` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `is_low_stock` tinyint(1) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 5,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_id` (`product_id`),
  KEY `sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// SQL to create the sync_log table
$sql_sync_log = "
CREATE TABLE IF NOT EXISTS `sync_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `sync_date` datetime NOT NULL,
  `products_added` int(11) NOT NULL DEFAULT 0,
  `products_updated` int(11) NOT NULL DEFAULT 0,
  `status` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// SQL to create the users table
$sql_users = "
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin',
  `created_at` datetime NOT NULL,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Execute the SQL statements
if ($conn->query($sql_physical_inventory) === TRUE) {
    echo "Table 'physical_inventory' created successfully<br>";
} else {
    echo "Error creating table 'physical_inventory': " . $conn->error . "<br>";
}

if ($conn->query($sql_sync_log) === TRUE) {
    echo "Table 'sync_log' created successfully<br>";
} else {
    echo "Error creating table 'sync_log': " . $conn->error . "<br>";
}

if ($conn->query($sql_users) === TRUE) {
    echo "Table 'users' created successfully<br>";
} else {
    echo "Error creating table 'users': " . $conn->error . "<br>";
}

// Create default admin user (password: securepassword)
$default_username = 'admin';
$default_password = password_hash('securepassword', PASSWORD_DEFAULT);
$current_date = date('Y-m-d H:i:s');

$check_user = "SELECT id FROM users WHERE username = '$default_username'";
$result = $conn->query($check_user);

if ($result->num_rows == 0) {
    $sql_insert_admin = "
    INSERT INTO `users` (`username`, `password`, `name`, `email`, `role`, `created_at`)
    VALUES ('$default_username', '$default_password', 'Administrator', 'admin@example.com', 'admin', '$current_date')
    ";
    
    if ($conn->query($sql_insert_admin) === TRUE) {
        echo "Default admin user created successfully<br>";
        echo "Username: admin<br>";
        echo "Password: securepassword<br>";
        echo "<strong>IMPORTANT: Change this password immediately after first login!</strong><br>";
    } else {
        echo "Error creating default admin user: " . $conn->error . "<br>";
    }
} else {
    echo "Admin user already exists<br>";
}

$conn->close();

echo "<br>Database setup completed.";