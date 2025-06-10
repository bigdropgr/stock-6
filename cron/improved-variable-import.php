<?php
/**
 * Improved Variable Products Import Script
 * 
 * This script properly imports variable products with correct parent-child relationships
 * Replace your existing cron/improved-variable-import.php with this version
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
$options = getopt('', ['output:', 'debug']);
$output_file = isset($options['output']) ? $options['output'] : 'variable-products-import.log';
$debug_mode = isset($options['debug']);

echo "Starting improved variable products import...\n";
echo "Debug mode: " . ($debug_mode ? "ON" : "OFF") . "\n";

// Open log file
$log_file = fopen($output_file, 'w');
fwrite($log_file, "# Improved Variable Products Import Log\n");
fwrite($log_file, "# Generated on: " . date('Y-m-d H:i:s') . "\n\n");

// Initialize classes
$db = Database::getInstance();
$woocommerce = new WooCommerce();
$product_manager = new Product();

// Create helper function to log messages
function log_message($message, $log_file, $console_output = true) {
    if (is_resource($log_file)) {
        fwrite($log_file, $message . "\n");
    }
    
    if ($console_output) {
        echo $message . "\n";
    }
}

// Test WooCommerce connection
log_message("Testing WooCommerce connection...", $log_file);
$connection_test = $woocommerce->testConnection();
if (!$connection_test['success']) {
    log_message("WooCommerce API connection failed: " . $connection_test['message'], $log_file);
    fclose($log_file);
    die();
}
log_message("Connection successful!", $log_file);

// Create the deleted_variations table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS `deleted_variations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `variation_id` bigint(20) NOT NULL,
  `deleted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `variation_id` (`variation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (!$db->query($sql)) {
    log_message("Warning: Could not create the deleted_variations table: " . $db->getError(), $log_file);
}

// Get deleted variations that should be skipped
$sql = "SELECT variation_id FROM deleted_variations";
$result = $db->query($sql);
$deleted_variation_ids = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_object()) {
        $deleted_variation_ids[$row->variation_id] = true;
    }
}

log_message("Found " . count($deleted_variation_ids) . " deleted variations to skip.", $log_file);

// Step 1: Get all variable products 
log_message("\nSTEP 1: Fetching all variable products...", $log_file);
$per_page = 20; // Smaller batch size to prevent timeouts
$page = 1;
$all_variable_products = [];

do {
    log_message("  Fetching page $page...", $log_file);
    
    try {
        $variable_products = $woocommerce->getVariableProducts($per_page, $page);
        
        if (empty($variable_products)) {
            log_message("  No products found on page $page.", $log_file);
            break;
        }
        
        log_message("  Found " . count($variable_products) . " variable products on page $page", $log_file);
        
        foreach ($variable_products as $product) {
            if (isset($product->type) && $product->type === 'variable') {
                $all_variable_products[$product->id] = $product;
                
                if ($debug_mode) {
                    log_message("    Variable product: " . $product->name . " (ID: " . $product->id . ")", $log_file);
                }
            }
        }
        
        $page++;
        
    } catch (Exception $e) {
        log_message("  ERROR on page $page: " . $e->getMessage(), $log_file);
        $page++;
    }
    
} while (!empty($variable_products) && count($variable_products) == $per_page);

log_message("Found " . count($all_variable_products) . " variable products total.", $log_file);

// Step 2: Process each variable product and its variations
log_message("\nSTEP 2: Processing variable products and variations...", $log_file);

$parents_imported = 0;
$parents_updated = 0;
$variations_imported = 0;
$variations_updated = 0;
$start_time = microtime(true);

foreach ($all_variable_products as $parent_wc_id => $parent_product) {
    log_message("Processing parent: " . $parent_product->name . " (WC ID: $parent_wc_id)", $log_file);
    
    // Step 2a: Import/update the parent variable product
    $existing_parent = $product_manager->getByProductId($parent_wc_id);
    
    $parent_data = [
        'product_id' => $parent_wc_id,
        'title' => $parent_product->name,
        'sku' => isset($parent_product->sku) ? $parent_product->sku : '',
        'category' => isset($parent_product->categories[0]->name) ? $parent_product->categories[0]->name : '',
        'price' => isset($parent_product->price) ? $parent_product->price : 0,
        'image_url' => !empty($parent_product->images) && isset($parent_product->images[0]->src) ? 
                     $parent_product->images[0]->src : '',
        'stock' => 0, // Variable products don't have their own stock
        'low_stock_threshold' => DEFAULT_LOW_STOCK_THRESHOLD,
        'product_type' => 'variable',
        'parent_id' => null, // Variable products have no parent
        'notes' => 'Variable product'
    ];
    
    $parent_database_id = null;
    
    if ($existing_parent) {
        // Update existing parent
        if ($product_manager->update($existing_parent->id, $parent_data)) {
            $parents_updated++;
            $parent_database_id = $existing_parent->id;
            log_message("  - Updated existing parent product (DB ID: {$existing_parent->id})", $log_file);
        } else {
            log_message("  - Failed to update parent product", $log_file);
            continue; // Skip variations if parent failed
        }
    } else {
        // Add new parent
        $parent_database_id = $product_manager->add($parent_data);
        
        if ($parent_database_id) {
            $parents_imported++;
            log_message("  - Imported new parent product (DB ID: $parent_database_id)", $log_file);
        } else {
            log_message("  - Failed to import parent product", $log_file);
            continue; // Skip variations if parent failed
        }
    }
    
    // Step 2b: Get and process variations for this parent
    $variations = $woocommerce->getPublishedProductVariations($parent_wc_id);
    
    if (empty($variations)) {
        log_message("    No published variations found.", $log_file);
        continue;
    }
    
    log_message("    Found " . count($variations) . " variations to process", $log_file);
    
    foreach ($variations as $variation) {
        // Skip if this variation was deleted by the user
        if (isset($deleted_variation_ids[$variation->id])) {
            log_message("    Skipping deleted variation: " . $variation->id, $log_file);
            continue;
        }
        
        // Create variation attributes text
        $attributes_text = '';
        if (!empty($variation->attributes)) {
            $attrs = [];
            foreach ($variation->attributes as $attr) {
                if (isset($attr->name) && isset($attr->option)) {
                    $attrs[] = $attr->name . ': ' . $attr->option;
                } elseif (isset($attr->option)) {
                    $attrs[] = $attr->option;
                }
            }
            if (!empty($attrs)) {
                $attributes_text = implode(', ', $attrs);
            }
        }
        
        // Create variation title
        $variation_title = $parent_product->name;
        if (!empty($attributes_text)) {
            $variation_title .= ' - ' . $attributes_text;
        }
        
        // Prepare variation data with CORRECT parent_id (database ID, not WooCommerce ID)
        $variation_data = [
            'product_id' => $variation->id,
            'title' => $variation_title,
            'sku' => isset($variation->sku) ? $variation->sku : '',
            'category' => isset($parent_product->categories[0]->name) ? $parent_product->categories[0]->name : '',
            'price' => isset($variation->price) ? $variation->price : 0,
            'image_url' => !empty($variation->image) && isset($variation->image->src) ? $variation->image->src : 
                        (!empty($parent_product->images) && isset($parent_product->images[0]->src) ? $parent_product->images[0]->src : ''),
            'stock' => 0, // Default stock
            'low_stock_threshold' => DEFAULT_LOW_STOCK_THRESHOLD,
            'product_type' => 'variation',
            'parent_id' => $parent_database_id, // CRITICAL: Use database ID, not WooCommerce ID
            'variation_attributes' => !empty($variation->attributes) ? $variation->attributes : null,
            'notes' => $attributes_text
        ];
        
        // Add or update variation
        $existing_variation = $product_manager->getByProductId($variation->id);
        
        if ($existing_variation) {
            // Update existing variation - make sure parent_id is correct
            if ($product_manager->update($existing_variation->id, $variation_data)) {
                $variations_updated++;
                log_message("    - Updated variation: " . $variation_title, $log_file);
            } else {
                log_message("    - Failed to update variation: " . $variation_title, $log_file);
            }
        } else {
            // Add new variation
            if ($product_manager->add($variation_data)) {
                $variations_imported++;
                log_message("    - Imported new variation: " . $variation_title, $log_file);
            } else {
                log_message("    - Failed to import variation: " . $variation_title, $log_file);
            }
        }
    }
}

// Log the import to the database
$now = date('Y-m-d H:i:s');
$details = "Improved variable products import: Parents added/updated: $parents_imported/$parents_updated, Variations added/updated: $variations_imported/$variations_updated";
$total_added = $parents_imported + $variations_imported;
$total_updated = $parents_updated + $variations_updated;

$sql = "INSERT INTO sync_log 
        (sync_date, products_added, products_updated, status, details) 
        VALUES 
        ('$now', $total_added, $total_updated, 'success', '$details')";
$db->query($sql);

// Calculate total time
$total_time = (float)(microtime(true) - $start_time);

// Write summary to log
fwrite($log_file, "\n# Summary\n");
fwrite($log_file, "Parent variable products imported: $parents_imported\n");
fwrite($log_file, "Parent variable products updated: $parents_updated\n");
fwrite($log_file, "Variations imported: $variations_imported\n");
fwrite($log_file, "Variations updated: $variations_updated\n");
fwrite($log_file, "Total import time: " . gmdate("H:i:s", $total_time) . "\n");

// Output results
echo "\nImport completed!\n";
echo "Parent products imported: $parents_imported\n";
echo "Parent products updated: $parents_updated\n";
echo "Variations imported: $variations_imported\n";
echo "Variations updated: $variations_updated\n";
echo "Total time: " . gmdate("H:i:s", $total_time) . "\n";
echo "Results saved to: $output_file\n";

// Close the log file
fclose($log_file);
?>