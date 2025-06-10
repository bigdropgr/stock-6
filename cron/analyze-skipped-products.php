<?php
/**
 * Analyze Skipped Products Script
 * 
 * This script identifies which products were skipped during the import process
 * and analyzes why they were skipped (variable products, errors, etc.)
 * 
 * Usage: php analyze-skipped-products.php [--output=file.txt]
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
$output_file = isset($options['output']) ? $options['output'] : 'skipped-products-analysis.txt';

echo "Analyzing skipped products...\n";

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
echo "Products not imported: " . ($total_wc_products - $total_local_products) . "\n\n";

// Open output file
$file = fopen($output_file, 'w');
fwrite($file, "# Skipped Products Analysis Report\n");
fwrite($file, "# Generated on: " . date('Y-m-d H:i:s') . "\n");
fwrite($file, "# WooCommerce products: $total_wc_products\n");
fwrite($file, "# Local products: $total_local_products\n");
fwrite($file, "# Products not imported: " . ($total_wc_products - $total_local_products) . "\n\n");
fwrite($file, "ID\tName\tSKU\tType\tReason\n");
fwrite($file, "----------------------------------------------------------------------------\n");

// Process all products in batches
$per_page = 100;
$page = 1;
$processed = 0;
$variable_products = 0;
$missing_products = 0;
$other_reasons = 0;

echo "\nScanning for skipped products...\n";

do {
    echo "Processing page $page...\n";
    
    // Get products from WooCommerce
    $wc_products = $woocommerce->getProducts($per_page, $page);
    
    if (empty($wc_products)) {
        echo "No products found on page $page. Stopping.\n";
        break;
    }
    
    foreach ($wc_products as $wc_product) {
        // Check if product exists in local database
        $existing_product = $product_manager->getByProductId($wc_product->id);
        
        if (!$existing_product) {
            $reason = "";
            
            // Check if it's a variable product
            if (isset($wc_product->type) && $wc_product->type === 'variable') {
                $reason = "Variable product (has variations)";
                $variable_products++;
            } else {
                $reason = "Unknown reason (simple product)";
                $missing_products++;
            }
            
            echo "Skipped product: " . $wc_product->name . " (ID: " . $wc_product->id . ") - " . $reason . "\n";
            fwrite($file, $wc_product->id . "\t" . 
                   $wc_product->name . "\t" . 
                   (isset($wc_product->sku) ? $wc_product->sku : 'No SKU') . "\t" .
                   (isset($wc_product->type) ? $wc_product->type : 'unknown') . "\t" .
                   $reason . "\n");
        }
        
        $processed++;
    }
    
    echo "Processed $processed products, found $variable_products variable products and $missing_products missing simple products...\n";
    $page++;
    
} while (count($wc_products) === $per_page);

// Write summary to file
fwrite($file, "\n# Summary\n");
fwrite($file, "Total products processed: $processed\n");
fwrite($file, "Variable products (expected to be skipped): $variable_products\n");
fwrite($file, "Missing simple products (should be imported): $missing_products\n");
fwrite($file, "Other skipped products: $other_reasons\n");
fclose($file);

echo "\n\nFinished analysis!\n";
echo "Total products processed: $processed\n";
echo "Variable products (expected to be skipped): $variable_products\n";
echo "Missing simple products (should be imported): $missing_products\n";
echo "Results saved to: $output_file\n";

// Output command to import the missing simple products if any were found
if ($missing_products > 0) {
    echo "\nTo import the missing simple products, run:\n";
    echo "php cron/manual-sync.php --start-id=1 --end-id=10000\n";
}