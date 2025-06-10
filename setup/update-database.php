<?php
/**
 * Database Update Script
 * 
 * Run this script to add the new wholesale price and location fields
 * Save as: setup/update-database.php
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

echo "<h2>Database Update Script</h2>";
echo "<p>Adding new fields for wholesale price and location tracking...</p>";

// Check if columns already exist
$check_columns = "SHOW COLUMNS FROM physical_inventory LIKE 'wholesale_price'";
$result = $conn->query($check_columns);

if ($result->num_rows > 0) {
    echo "<p>✓ Wholesale price column already exists.</p>";
} else {
    // Add wholesale_price column
    $sql = "ALTER TABLE `physical_inventory` ADD COLUMN `wholesale_price` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `price`";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>✓ Added wholesale_price column successfully.</p>";
    } else {
        echo "<p>✗ Error adding wholesale_price column: " . $conn->error . "</p>";
    }
}

// Check if location columns exist
$check_aisle = "SHOW COLUMNS FROM physical_inventory LIKE 'aisle'";
$result = $conn->query($check_aisle);

if ($result->num_rows > 0) {
    echo "<p>✓ Location columns already exist.</p>";
} else {
    // Add location columns
    $location_sql = "ALTER TABLE `physical_inventory` 
                     ADD COLUMN `aisle` varchar(100) DEFAULT NULL AFTER `notes`,
                     ADD COLUMN `shelf` varchar(100) DEFAULT NULL AFTER `aisle`,
                     ADD COLUMN `storage_notes` text DEFAULT NULL AFTER `shelf`,
                     ADD COLUMN `date_of_entry` date DEFAULT NULL AFTER `storage_notes`";
    
    if ($conn->query($location_sql) === TRUE) {
        echo "<p>✓ Added location columns successfully.</p>";
    } else {
        echo "<p>✗ Error adding location columns: " . $conn->error . "</p>";
    }
}

// Add indexes for better performance
$index_sql1 = "ALTER TABLE `physical_inventory` ADD INDEX `idx_wholesale_price` (`wholesale_price`)";
$index_sql2 = "ALTER TABLE `physical_inventory` ADD INDEX `idx_location` (`aisle`, `shelf`)";

// Check if indexes exist
$check_index1 = "SHOW INDEX FROM physical_inventory WHERE Key_name = 'idx_wholesale_price'";
$result1 = $conn->query($check_index1);

if ($result1->num_rows > 0) {
    echo "<p>✓ Wholesale price index already exists.</p>";
} else {
    if ($conn->query($index_sql1) === TRUE) {
        echo "<p>✓ Added wholesale price index successfully.</p>";
    } else {
        echo "<p>✗ Error adding wholesale price index: " . $conn->error . "</p>";
    }
}

$check_index2 = "SHOW INDEX FROM physical_inventory WHERE Key_name = 'idx_location'";
$result2 = $conn->query($check_index2);

if ($result2->num_rows > 0) {
    echo "<p>✓ Location index already exists.</p>";
} else {
    if ($conn->query($index_sql2) === TRUE) {
        echo "<p>✓ Added location index successfully.</p>";
    } else {
        echo "<p>✗ Error adding location index: " . $conn->error . "</p>";
    }
}

// Show final table structure
echo "<h3>Updated Table Structure:</h3>";
$show_columns = "SHOW COLUMNS FROM physical_inventory";
$result = $conn->query($show_columns);

if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row["Field"] . "</td>";
        echo "<td>" . $row["Type"] . "</td>";
        echo "<td>" . $row["Null"] . "</td>";
        echo "<td>" . $row["Key"] . "</td>";
        echo "<td>" . $row["Default"] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();

echo "<h3>Update Complete!</h3>";
echo "<p>Your database has been updated with the new fields. You can now:</p>";
echo "<ul>";
echo "<li>Set wholesale prices for your products</li>";
echo "<li>Track product locations in your physical store</li>";
echo "<li>View wholesale inventory value on the dashboard</li>";
echo "</ul>";
echo "<p><a href='../dashboard.php'>← Return to Dashboard</a></p>";
?>