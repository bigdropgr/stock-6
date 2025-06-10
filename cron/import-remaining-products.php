<?php
/**
 * Import Remaining Products Script
 * 
 * This script directly imports the ~100 remaining products that weren't imported initially.
 * It's designed to complete your specific import task without timeouts.
 * 
 * Usage: php import-remaining-products.php
 */

// Set unlimited execution time
set_time_limit(0);

// Include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Product.php';
require_once __DIR__ . '/../includes/WooCommerce.php';
require_once __DIR__ . '/../includes/functions.php';

echo "Starting import of remaining products...\n";

// Initialize classes
$db = Database::getInstance();
$woocommerce = new WooCommerce();
$product_manager = new Product();

// Test WooCommerce connection
echo "Testing WooCommerce connection...\n";
$connection_test = $woocommerce->testConnection();
if (!$connection_test['success']) {
    die("WooCommerce API connection failed: " . $connection_test['message'] . "\n");
}
echo "Connection successful!\n";

// Get product count from WooCommerce
$count_result = $woocommerce->getProductCount();
$total_wc_products = $count_result['count'] ?? 0;

// Get product count from local database
$total_local_products = $product_manager->countAll();

echo "Total products in WooCommerce: $total_wc_products\n";
echo "Total products in local database: $total_local_products\n";
echo "Products missing: " . ($total_wc_products - $total_local_products) . "\n\n";

// Start processing from where we left off (starting with a higher page)
$start_page = 240; // Adjust this based on where the import stopped
$per_page = 20;
$page = $start_page;

$added = 0;
$skipped = 0;
$total_processed = 0;
$start_time = microtime(true);

echo "Starting import from page $page...\n";

// Continue until we've processed enough products or found no more
do {
    echo "\nProcessing page $page...\n";
    
    // Get products from WooCommerce
    $wc_products = $woocommerce->getProducts($per_page, $page);
    
    if (empty($wc_products)) {
        echo "No products found on page $page. Stopping.\n";
        break;
    }
    
    echo "Found " . count($wc_products) . " products on page $page\n";
    
    foreach ($wc_products as $wc_product) {
        // Skip variable products
        if (isset($wc_product->type) && $wc_product->type === 'variable') {
            echo "Skipping variable product: " . $wc_product->name . "\n";
            $skipped++;
            continue;
        }
        
        echo "Processing " . $wc_product->name . " (ID: " . $wc_product->id . ")\n";
        
        // Check if product already exists
        $existing_product = $product_manager->getByProductId($wc_product->id);
        
        if ($existing_product) {
            echo "  - Product already exists, skipping\n";
            $skipped++;
            continue;
        }
        
        // Prepare product data
        $product_data = [
            'product_id' => $wc_product->id,
            'title' => $wc_product->name,
            'sku' => isset($wc_product->sku) ? $wc_product->sku : '',
            'category' => isset($wc_product->categories[0]->name) ? $wc_product->categories[0]->name : '',
            'price' => isset($wc_product->price) ? $wc_product->price : 0,
            'image_url' => !empty($wc_product->images) && isset($wc_product->images[0]->src) ? $wc_product->images[0]->src : '',
            'stock' => 0,
            'low_stock_threshold' => DEFAULT_LOW_STOCK_THRESHOLD
        ];
        
        // Add new product
        if ($product_manager->add($product_data)) {
            $added++;
            echo "  - Added new product\n";
        } else {
            echo "  - Failed to add product\n";
        }
        
        $total_processed++;
    }
    
    $page++;
    
    // Show progress
    $elapsed = microtime(true) - $start_time;
    echo "\nProgress: Added $added, Skipped $skipped, Total processed $total_processed\n";
    echo "Elapsed time: " . gmdate("H:i:s", $elapsed) . "\n";
    
    // Stop if we've added enough products or processed 20 pages
    if ($added >= 110 || $page >= $start_page + 20) {
        echo "\nReached target number of products or maximum pages. Stopping.\n";
        break;
    }
    
} while (count($wc_products) === $per_page);

// Log the sync
$now = date('Y-m-d H:i:s');
$sql = "INSERT INTO sync_log 
        (sync_date, products_added, products_updated, status, details) 
        VALUES 
        ('$now', $added, 0, 'success', 'Imported remaining products script')";
$db->query($sql);

// Calculate total time
$total_time = microtime(true) - $start_time;

echo "\n\nImport completed!\n";
echo "Products Added: $added\n";
echo "Products Skipped: $skipped\n";
echo "Total Products Processed: $total_processed\n";
echo "Total Time: " . gmdate("H:i:s", $total_time) . "\n";

$new_total = $product_manager->countAll();
echo "New total in local database: $new_total / $total_wc_products\n";