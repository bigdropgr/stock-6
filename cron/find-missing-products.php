<?php
/**
 * Find Missing Products Script
 * 
 * This script identifies products that exist in WooCommerce but haven't been imported
 * to the physical inventory system yet.
 * 
 * Usage: php find-missing-products.php [--output=file.txt]
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

// Parse command line arguments
$options = getopt('', ['output:']);
$output_file = isset($options['output']) ? $options['output'] : 'missing-products.txt';

echo "Finding missing products...\n";

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
$total_wc_products = $count_result['count'];

echo "Total products in WooCommerce: $total_wc_products\n";

// Get product count from local database
$total_local_products = $product_manager->countAll();
echo "Total products in local database: $total_local_products\n";

// Open output file
$file = fopen($output_file, 'w');
fwrite($file, "# Missing Products Report\n");
fwrite($file, "# Generated on: " . date('Y-m-d H:i:s') . "\n");
fwrite($file, "# WooCommerce products: $total_wc_products\n");
fwrite($file, "# Local products: $total_local_products\n\n");
fwrite($file, "ID\tName\tSKU\n");
fwrite($file, "----------------------------------------\n");

// Process all products in batches
$per_page = 100;
$page = 1;
$processed = 0;
$missing = 0;

echo "\nScanning for missing products...\n";

do {
    echo "Processing page $page...\n";
    
    // Get products from WooCommerce
    $wc_products = $woocommerce->getProducts($per_page, $page);
    
    if (empty($wc_products)) {
        echo "No products found on page $page. Stopping.\n";
        break;
    }
    
    foreach ($wc_products as $wc_product) {
        // Skip variable products
        if (isset($wc_product->type) && $wc_product->type === 'variable') {
            continue;
        }
        
        // Check if product exists in local database
        $existing_product = $product_manager->getByProductId($wc_product->id);
        
        if (!$existing_product) {
            echo "Missing product: " . $wc_product->name . " (ID: " . $wc_product->id . ")\n";
            fwrite($file, $wc_product->id . "\t" . $wc_product->name . "\t" . (isset($wc_product->sku) ? $wc_product->sku : '') . "\n");
            $missing++;
        }
        
        $processed++;
    }
    
    echo "Processed $processed products, found $missing missing...\n";
    $page++;
    
} while (count($wc_products) === $per_page);

fwrite($file, "\n# Total missing products: $missing\n");
fclose($file);

echo "\n\nFinished scanning!\n";
echo "Total products processed: $processed\n";
echo "Total missing products found: $missing\n";
echo "Results saved to: $output_file\n";

// Output command to import missing products if any were found
if ($missing > 0) {
    echo "\nTo import missing products, run:\n";
    echo "php manual-sync.php\n";
    echo "Or to import specific products by ID (faster):\n";
    echo "php manual-sync.php --start-id=[FIRST_ID] --end-id=[LAST_ID]\n";
}