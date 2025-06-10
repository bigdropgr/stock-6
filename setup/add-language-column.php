<?php
/**
 * Add language column to users table
 * 
 * Run this script once to add language support to existing installations
 */

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Update - Adding Language Support</h2>";

// Check if language column already exists
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'language'");

if ($result->num_rows > 0) {
    echo "<p>✓ Language column already exists in users table.</p>";
} else {
    // Add language column
    $sql = "ALTER TABLE `users` ADD COLUMN `language` varchar(2) NOT NULL DEFAULT 'en' AFTER `role`";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>✓ Language column added successfully to users table.</p>";
    } else {
        echo "<p>✗ Error adding language column: " . $conn->error . "</p>";
    }
}

// Check if last_ip column exists
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'last_ip'");

if ($result->num_rows > 0) {
    echo "<p>✓ Last IP column already exists in users table.</p>";
} else {
    // Add last_ip column
    $sql = "ALTER TABLE `users` ADD COLUMN `last_ip` varchar(45) DEFAULT NULL AFTER `last_login`";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>✓ Last IP column added successfully to users table.</p>";
    } else {
        echo "<p>✗ Error adding last IP column: " . $conn->error . "</p>";
    }
}

// Set default language for existing users based on site default
$default_lang = defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'en';
$sql = "UPDATE users SET language = '$default_lang' WHERE language = '' OR language IS NULL";

if ($conn->query($sql) === TRUE) {
    $affected_rows = $conn->affected_rows;
    echo "<p>✓ Updated language preference for $affected_rows existing users to '$default_lang'.</p>";
} else {
    echo "<p>✗ Error updating language preferences: " . $conn->error . "</p>";
}

$conn->close();

echo "<h3>Update Complete!</h3>";
echo "<p>Your system now supports multiple languages:</p>";
echo "<ul>";
echo "<li>Users can switch between English and Greek</li>";
echo "<li>Language preferences are saved per user</li>";
echo "<li>The interface will remember the user's choice</li>";
echo "</ul>";
echo "<p><a href='../dashboard.php'>← Return to Dashboard</a></p>";
?>