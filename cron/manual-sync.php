<?php
/**
 * Manual Sync Script
 * 
 * This script runs the sync process manually without requiring a web session
 * It can be used to complete a sync that was interrupted due to timeouts
 * 
 * Usage: php manual-sync.php [--full] [--page=X] [--limit=Y]
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
$options = getopt('', ['full', 'page:', 'limit:', 'start-id:', 'end-id:']);
$full_sync = isset($options['full']);
$start_page = isset($options['page']) ? intval($options['page']) : 1;
$per_page = isset($options['limit']) ? intval($options['limit']) : 20;
$start_id = isset($options['start-id']) ? intval($options['start-id']) : 0;
$end_id = isset($options['end-id']) ? intval($options['end-id']) : 0;

echo "Starting manual sync process...\n";
echo "Full sync: " . ($full_sync ? "Yes" : "No") . "\n";
echo "Starting page: $start_page\n";
echo "Products per page: $per_page\n";

if ($start_id > 0) {
    echo "Starting from product ID: $start_id\n";
}
if ($end_id > 0) {
    echo "Ending at product ID: $end_id\n";
}

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

// Start sync process
$start_time = microtime(true);
$products_added = 0;
$products_updated = 0;
$total_processed = 0;
$page = $start_page;

// Set initial batch size
$batch_size = $per_page;

echo "\nStarting import from page $page with $batch_size products per page\n";

try {
    // Use ID-based import if specified
    if ($start_id > 0) {
        echo "Performing ID-based import...\n";
        
        // Get the total number of products via API
        $result = $woocommerce->getProductCount();
        $total_products = $result['count'] ?? 5000;
        echo "Total products on WooCommerce: approximately $total_products\n";
        
        // Get product IDs from WooCommerce (if available)
        // This is an optional step, as some WooCommerce sites may not provide this efficiently
        echo "Fetching product IDs from WooCommerce...\n";
        $id_range = $woocommerce->getProductIdRange();
        
        // Use provided range or default to a wide range
        $current_id = $start_id;
        $end_id = $end_id > 0 ? $end_id : ($id_range['max'] ?? 100000);
        
        echo "Processing products with IDs from $current_id to $end_id\n";
        
        // Fetch products one by one by ID
        while ($current_id <= $end_id) {
            echo "Fetching product ID: $current_id\n";
            $wc_product = $woocommerce->getProduct($current_id);
            
            if ($wc_product && isset($wc_product->id)) {
                echo "Processing " . $wc_product->name . " (ID: " . $wc_product->id . ")\n";
                
                // Check if product already exists
                $existing_product = $product_manager->getByProductId($wc_product->id);
                
                // Prepare product data
                $product_data = [
                    'product_id' => $wc_product->id,
                    'title' => $wc_product->name,
                    'sku' => isset($wc_product->sku) ? $wc_product->sku : '',
                    'category' => isset($wc_product->categories[0]->name) ? $wc_product->categories[0]->name : '',
                    'price' => isset($wc_product->price) ? $wc_product->price : 0,
                    'image_url' => !empty($wc_product->images) && isset($wc_product->images[0]->src) ? $wc_product->images[0]->src : '',
                ];
                
                if ($existing_product) {
                    if ($full_sync) {
                        // Update existing product
                        $update_data = [
                            'title' => $product_data['title'],
                            'sku' => $product_data['sku'],
                            'category' => $product_data['category'],
                            'price' => $product_data['price'],
                            'image_url' => $product_data['image_url']
                        ];
                        
                        if ($product_manager->update($existing_product->id, $update_data)) {
                            $products_updated++;
                            echo "  - Updated existing product\n";
                        }
                    } else {
                        echo "  - Product already exists, skipping\n";
                    }
                } else {
                    // Add new product
                    $product_data['stock'] = 0;
                    $product_data['low_stock_threshold'] = DEFAULT_LOW_STOCK_THRESHOLD;
                    
                    if ($product_manager->add($product_data)) {
                        $products_added++;
                        echo "  - Added new product\n";
                    } else {
                        echo "  - Failed to add product\n";
                    }
                }
                
                $total_processed++;
            } else {
                echo "No product found with ID: $current_id\n";
            }
            
            $current_id++;
            
            // Show progress every 10 products
            if ($total_processed % 10 === 0) {
                $elapsed = microtime(true) - $start_time;
                echo "\nProgress: Added $products_added, Updated $products_updated, Total $total_processed\n";
                echo "Elapsed time: " . gmdate("H:i:s", $elapsed) . "\n\n";
            }
        }
    } else {
        // Standard page-based processing
        do {
            echo "\nProcessing page $page...\n";
            // Get products from WooCommerce
            $wc_products = $woocommerce->getProducts($batch_size, $page);
            
            if (empty($wc_products)) {
                echo "No products found on page $page. Stopping.\n";
                break;
            }
            
            echo "Found " . count($wc_products) . " products on page $page\n";
            
            // Process products
            foreach ($wc_products as $wc_product) {
                // Skip variable products
                if (isset($wc_product->type) && $wc_product->type === 'variable') {
                    echo "Skipping variable product: " . $wc_product->name . "\n";
                    continue;
                }
                
                echo "Processing " . $wc_product->name . " (ID: " . $wc_product->id . ")\n";
                
                // Check if product already exists
                $existing_product = $product_manager->getByProductId($wc_product->id);
                
                // Prepare product data
                $product_data = [
                    'product_id' => $wc_product->id,
                    'title' => $wc_product->name,
                    'sku' => isset($wc_product->sku) ? $wc_product->sku : '',
                    'category' => isset($wc_product->categories[0]->name) ? $wc_product->categories[0]->name : '',
                    'price' => isset($wc_product->price) ? $wc_product->price : 0,
                    'image_url' => !empty($wc_product->images) && isset($wc_product->images[0]->src) ? $wc_product->images[0]->src : '',
                ];
                
                if ($existing_product) {
                    if ($full_sync) {
                        // Update existing product
                        $update_data = [
                            'title' => $product_data['title'],
                            'sku' => $product_data['sku'],
                            'category' => $product_data['category'],
                            'price' => $product_data['price'],
                            'image_url' => $product_data['image_url']
                        ];
                        
                        if ($product_manager->update($existing_product->id, $update_data)) {
                            $products_updated++;
                            echo "  - Updated existing product\n";
                        }
                    } else {
                        echo "  - Product already exists, skipping\n";
                    }
                } else {
                    // Add new product
                    $product_data['stock'] = 0;
                    $product_data['low_stock_threshold'] = DEFAULT_LOW_STOCK_THRESHOLD;
                    
                    if ($product_manager->add($product_data)) {
                        $products_added++;
                        echo "  - Added new product\n";
                    } else {
                        echo "  - Failed to add product\n";
                    }
                }
                
                $total_processed++;
            }
            
            $page++;
            
            // Show progress stats
            $elapsed = microtime(true) - $start_time;
            echo "\nProgress: Added $products_added, Updated $products_updated, Total $total_processed\n";
            echo "Elapsed time: " . gmdate("H:i:s", $elapsed) . "\n";
            
            // Continue until we get fewer products than requested
        } while (count($wc_products) === $batch_size);
    }
    
    // Calculate total time
    $end_time = microtime(true);
    $total_time = $end_time - $start_time;
    
    echo "\n\nSync completed successfully!\n";
    echo "Products Added: $products_added\n";
    echo "Products Updated: $products_updated\n";
    echo "Total Products Processed: $total_processed\n";
    echo "Total Time: " . gmdate("H:i:s", $total_time) . "\n";
    
    // Log the sync
    $now = date('Y-m-d H:i:s');
    $sql = "INSERT INTO sync_log 
            (sync_date, products_added, products_updated, status, details) 
            VALUES 
            ('$now', $products_added, $products_updated, 'success', 'Manual sync completed successfully')";
    $db->query($sql);
    
} catch (Exception $e) {
    echo "\n\nError during sync: " . $e->getMessage() . "\n";
    
    // Log the error
    $now = date('Y-m-d H:i:s');
    $error_message = $db->escapeString($e->getMessage());
    $sql = "INSERT INTO sync_log 
            (sync_date, products_added, products_updated, status, details) 
            VALUES 
            ('$now', $products_added, $products_updated, 'error', 'Manual sync error: $error_message')";
    $db->query($sql);
}