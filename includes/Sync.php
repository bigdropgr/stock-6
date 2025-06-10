<?php
/**
 * Sync Class
 * 
 * Handles synchronization between WooCommerce and physical inventory
 * with features to prevent timeouts and resume interrupted syncs
 */

// Use require_once only for dependency classes - NOT for Sync.php itself!
require_once __DIR__ . '/WooCommerce.php';
require_once __DIR__ . '/Product.php';

class Sync {
    // Sync state constants
    const SYNC_STATE_IDLE = 'idle';
    const SYNC_STATE_IN_PROGRESS = 'in_progress';
    const SYNC_STATE_COMPLETED = 'completed';
    const SYNC_STATE_ERROR = 'error';
    
    private $db;
    private $woocommerce;
    private $product;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
        $this->woocommerce = new WooCommerce();
        $this->product = new Product();
        
        // Create deleted_variations table if it doesn't exist
        $this->createDeletedVariationsTable();
    }
    
    /**
     * Create deleted_variations table if not exists
     */
    private function createDeletedVariationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `deleted_variations` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `variation_id` bigint(20) NOT NULL,
          `deleted_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `variation_id` (`variation_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $this->db->query($sql);
    }
    
    /**
     * Start or continue a sync operation
     * 
     * @param bool $full_sync Whether to perform a full sync (all products) or just new products
     * @return array Results of the sync operation
     */
    public function syncProducts($full_sync = false) {
        // Get current sync state
        $state = $this->getSyncState();
        
        // If a sync is already in progress, continue it
        if ($state['status'] === self::SYNC_STATE_IN_PROGRESS) {
            return $this->continueSyncProducts($state, $full_sync);
        }
        
        // Otherwise, start a new sync
        return $this->startSyncProducts($full_sync);
    }
    
    /**
     * Start a new sync operation
     * 
     * @param bool $full_sync Whether to perform a full sync (all products) or just new products
     * @return array Results of the sync operation
     */
    public function syncProductsComplete($full_sync = false) {
        $results = [
            'products_added' => 0,
            'products_updated' => 0,
            'variations_added' => 0,
            'variations_updated' => 0,
            'errors' => [],
            'start_time' => microtime(true),
            'end_time' => null,
            'status' => 'success',
            'is_complete' => false,
            'phases' => []
        ];

        try {
            // Test WooCommerce API connection
            $connection_test = $this->woocommerce->testConnection();
            if (!$connection_test['success']) {
                throw new Exception("WooCommerce API connection failed: " . $connection_test['message']);
            }

            // Phase 1: Import Simple Products
            $results['phases'][] = 'Importing simple products...';
            $simple_results = $this->syncSimpleProducts($full_sync);
            $results['products_added'] += $simple_results['products_added'];
            $results['products_updated'] += $simple_results['products_updated'];
            $results['errors'] = array_merge($results['errors'], $simple_results['errors']);

            // Phase 2: Import Variable Products and Variations
            $results['phases'][] = 'Importing variable products and variations...';
            $variable_results = $this->syncVariableProductsComplete();
            $results['products_added'] += $variable_results['products_added'];
            $results['products_updated'] += $variable_results['products_updated'];
            $results['variations_added'] = $variable_results['variations_added'];
            $results['variations_updated'] = $variable_results['variations_updated'];
            $results['errors'] = array_merge($results['errors'], $variable_results['errors']);

            $results['is_complete'] = true;
            $results['status'] = 'success';

        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['errors'][] = $e->getMessage();
        }

        $results['end_time'] = microtime(true);
        $results['duration'] = $results['end_time'] - $results['start_time'];

        // Log the sync
        $total_added = $results['products_added'] + $results['variations_added'];
        $total_updated = $results['products_updated'] + $results['variations_updated'];
        
        $this->logSync(
            $total_added,
            $total_updated,
            $results['status'],
            "Complete sync: {$results['products_added']} products, {$results['variations_added']} variations added"
        );

        return $results;
    }

    /**
     * Sync only simple products (non-variable)
     */
    private function syncSimpleProducts($full_sync = false) {
        $results = [
            'products_added' => 0,
            'products_updated' => 0,
            'errors' => []
        ];

        $page = 1;
        $per_page = 50;

        do {
            $wc_products = $this->woocommerce->getProducts($per_page, $page);
            
            if (empty($wc_products)) {
                break;
            }

            foreach ($wc_products as $wc_product) {
                // Only process simple products
                if (isset($wc_product->type) && $wc_product->type !== 'simple') {
                    continue;
                }

                try {
                    $existing_product = $this->product->getByProductId($wc_product->id);
                    
                    $product_data = [
                        'product_id' => $wc_product->id,
                        'title' => $wc_product->name,
                        'sku' => isset($wc_product->sku) ? $wc_product->sku : '',
                        'category' => $this->getCategoryName($wc_product),
                        'price' => isset($wc_product->price) ? $wc_product->price : 0,
                        'image_url' => $this->getProductImage($wc_product),
                        'product_type' => 'simple',
                        'parent_id' => null
                    ];

                    if ($existing_product) {
                        if ($full_sync && $this->product->update($existing_product->id, $product_data)) {
                            $results['products_updated']++;
                        }
                    } else {
                        $product_data['stock'] = 0;
                        $product_data['low_stock_threshold'] = DEFAULT_LOW_STOCK_THRESHOLD;
                        
                        if ($this->product->add($product_data)) {
                            $results['products_added']++;
                        }
                    }
                } catch (Exception $e) {
                    $results['errors'][] = "Error processing simple product {$wc_product->id}: " . $e->getMessage();
                }
            }

            $page++;
            
        } while (count($wc_products) === $per_page);

        return $results;
    }

    /**
     * Complete variable products sync (both parents and variations)
     */
    private function syncVariableProductsComplete() {
        $results = [
            'products_added' => 0,
            'products_updated' => 0,
            'variations_added' => 0,
            'variations_updated' => 0,
            'errors' => []
        ];

        // Get all variable products
        $page = 1;
        $per_page = 20; // Smaller batches for variable products

        do {
            $variable_products = $this->woocommerce->getVariableProducts($per_page, $page);
            
            if (empty($variable_products)) {
                break;
            }

            foreach ($variable_products as $variable_product) {
                try {
                    $product_result = $this->processVariableProduct($variable_product);
                    
                    $results['products_added'] += $product_result['products_added'];
                    $results['products_updated'] += $product_result['products_updated'];
                    $results['variations_added'] += $product_result['variations_added'];
                    $results['variations_updated'] += $product_result['variations_updated'];
                    $results['errors'] = array_merge($results['errors'], $product_result['errors']);
                    
                } catch (Exception $e) {
                    $results['errors'][] = "Error processing variable product {$variable_product->id}: " . $e->getMessage();
                }
            }

            $page++;
            
        } while (count($variable_products) === $per_page);

        return $results;
    }
    
    /**
     * Continue a sync operation from where it left off
     * 
     * @param array $state Current sync state
     * @param bool $full_sync Whether to perform a full sync
     * @return array Results of the sync operation
     */
    private function continueSyncProducts($state, $full_sync) {
        $results = [
            'products_added' => $state['products_added'],
            'products_updated' => $state['products_updated'],
            'errors' => $state['errors'],
            'start_time' => $state['start_time'],
            'end_time' => null,
            'status' => 'success',
            'is_complete' => false,
            'continuation_token' => null,
            'total_products' => $state['total_products'],
            'processed_products' => $state['processed_products'],
            'progress_percent' => 0
        ];
        
        try {
            $page = $state['page'];
            $per_page = $state['per_page'];
            $full_sync = $state['full_sync']; // Use the same sync mode as originally started
            
            // Get products from WooCommerce for this batch
            $wc_products = $this->woocommerce->getProducts($per_page, $page);
            
            // Check if we got a valid response
            if (empty($wc_products)) {
                // If first page returns empty, something is wrong
                if ($page === 1) {
                    throw new Exception("Failed to retrieve products from WooCommerce API");
                }
                
                // No more products, sync is complete
                $this->completeSyncProcess($results);
                return $results;
            }
            
            // Log progress in development mode
            if (defined('APP_ENV') && APP_ENV === 'development') {
                error_log("Processing page {$page} with " . count($wc_products) . " products");
            }
            
            // Update our estimate of total products if this is a later page
            if ($page > 1 && count($wc_products) === $per_page) {
                // We're still getting full pages, so there are likely more products
                $state['estimated_total'] = max($state['estimated_total'], $page * $per_page * 1.2); // Add 20% buffer
            } else if (count($wc_products) < $per_page) {
                // We got a partial page, so we can calculate the exact total
                $state['estimated_total'] = (($page - 1) * $per_page) + count($wc_products);
            }
            
            // Store current count for tracking progress
            $state['last_count'] = count($wc_products);
            
            // Process this batch of products
            foreach ($wc_products as $wc_product) {
                // Skip variable products (get variations separately)
                if (isset($wc_product->type) && $wc_product->type === 'variable') {
                    continue;
                }
                
                // Make sure required fields are present
                if (!isset($wc_product->id) || !isset($wc_product->name)) {
                    $results['errors'][] = "Invalid product data received";
                    continue;
                }
                
                // Check if product already exists in our database
                $existing_product = $this->product->getByProductId($wc_product->id);
                
                // Prepare product data
                $product_data = [
                    'product_id' => $wc_product->id,
                    'title' => $wc_product->name,
                    'sku' => isset($wc_product->sku) ? $wc_product->sku : '',
                    'category' => $this->getCategoryName($wc_product),
                    'price' => isset($wc_product->price) ? $wc_product->price : 0,
                    'image_url' => $this->getProductImage($wc_product),
                ];
                
                if ($existing_product) {
                    if ($full_sync) {
                        // Only update specific fields during sync
                        // Don't overwrite stock if already set in physical inventory
                        $update_data = [
                            'title' => $product_data['title'],
                            'sku' => $product_data['sku'],
                            'category' => $product_data['category'],
                            'price' => $product_data['price'],
                            'image_url' => $product_data['image_url']
                        ];
                        
                        if ($this->product->update($existing_product->id, $update_data)) {
                            $results['products_updated']++;
                            $state['products_updated']++;
                        }
                    }
                } else {
                    // New product, add it
                    // Set default stock to 0 for new products
                    $product_data['stock'] = 0;
                    $product_data['low_stock_threshold'] = DEFAULT_LOW_STOCK_THRESHOLD;
                    
                    if ($this->product->add($product_data)) {
                        $results['products_added']++;
                        $state['products_added']++;
                    }
                }
                
                $results['processed_products']++;
                $state['processed_products']++;
            }
            
            $results['total_products'] += count($wc_products);
            $state['total_products'] += count($wc_products);
            
            // Determine if we should continue
            $should_continue = count($wc_products) === $per_page;
            
            // If we've processed 10 batches without adding or updating any products,
            // and we're over 90% of our estimated total, assume we're done
            if ($page > 10 && $results['products_added'] + $results['products_updated'] == 0 && 
                $state['processed_products'] > ($state['estimated_total'] * 0.9)) {
                $should_continue = false;
            }
            
            if ($should_continue) {
                // More products to process, save state and return continuation token
                $page++;
                $state['page'] = $page;
                $state['per_page'] = $per_page;
                $state['products_added'] = $results['products_added'];
                $state['products_updated'] = $results['products_updated'];
                $state['errors'] = $results['errors'];
                $state['total_products'] = $results['total_products'];
                $state['processed_products'] = $results['processed_products'];
                
                $this->saveSyncState($state);
                
                $results['is_complete'] = false;
                $results['continuation_token'] = base64_encode(json_encode(['page' => $page]));
                
                // Calculate progress percentage based on estimated total
                if ($state['estimated_total'] > 0) {
                    $results['progress_percent'] = min(99, round(($state['processed_products'] / $state['estimated_total']) * 100));
                }
                
                return $results;
            } else {
                // No more products or we've determined we're likely done, mark sync as complete
                $this->completeSyncProcess($results);
                return $results;
            }
            
        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['errors'][] = $e->getMessage();
            
            // Log detailed error
            error_log("Sync Error: " . $e->getMessage());
            
            // Log the error
            $this->logSync(0, 0, 'error', $e->getMessage());
            
            // Reset sync state
            $this->resetSyncState();
            
            return $results;
        }
    }
    
    /**
     * Complete the sync process
     * 
     * @param array $results Sync results
     */
    private function completeSyncProcess(&$results) {
        // Update results
        $results['status'] = 'success';
        $results['is_complete'] = true;
        $results['end_time'] = microtime(true);
        $results['duration'] = $results['end_time'] - $results['start_time'];
        $results['progress_percent'] = 100;
        
        // After standard sync is complete, run variable products sync
        $variable_sync_results = $this->syncVariableProducts();

        // Add variable product results to main results
        $results['products_added'] += $variable_sync_results['products_added'];
        $results['products_updated'] += $variable_sync_results['products_updated'];
        $results['variable_products_added'] = $variable_sync_results['products_added'];
        $results['variable_products_updated'] = $variable_sync_results['products_updated'];
        $results['variations_added'] = $variable_sync_results['variations_added'];
        $results['variations_updated'] = $variable_sync_results['variations_updated'];

        // Add any errors
        if (!empty($variable_sync_results['errors'])) {
            if (!isset($results['errors'])) {
                $results['errors'] = [];
            }
            $results['errors'] = array_merge($results['errors'], $variable_sync_results['errors']);
        }
        
        // Log successful sync
        $this->logSync(
            $results['products_added'], 
            $results['products_updated'], 
            'success',
            "Total products: {$results['total_products']}, Processed: {$results['processed_products']}"
        );
        
        // Reset sync state
        $this->resetSyncState();
    }
    

    /**
     * Get the current sync state
     * 
     * @return array Sync state
     */
    public function getSyncState() {
        $default_state = [
            'status' => self::SYNC_STATE_IDLE,
            'page' => 1,
            'per_page' => 20,
            'products_added' => 0,
            'products_updated' => 0,
            'errors' => [],
            'start_time' => microtime(true),
            'full_sync' => false,
            'total_products' => 0,
            'processed_products' => 0,
            'estimated_total' => 5000,
            'last_count' => 0
        ];
        
        if (!isset($_SESSION['sync_state'])) {
            return $default_state;
        }
        
        $state = $_SESSION['sync_state'];
        
        // Make sure all expected keys exist, use defaults if missing
        foreach ($default_state as $key => $default_value) {
            if (!isset($state[$key])) {
                $state[$key] = $default_value;
            }
        }
        
        // Check if the sync has timed out (more than 60 minutes)
        if ($state['status'] === self::SYNC_STATE_IN_PROGRESS) {
            $timeout = 60 * 60; // 60 minutes
            if (microtime(true) - $state['start_time'] > $timeout) {
                // If timed out, reset state and return default
                $this->resetSyncState();
                return $default_state;
            }
        }
        
        return $state;
    }
    
    /**
     * Save the current sync state
     * 
     * @param array $state Sync state
     */
    private function saveSyncState($state) {
        $_SESSION['sync_state'] = $state;
    }
    
    /**
     * Reset the sync state
     */
    public function resetSyncState() {
        if (isset($_SESSION['sync_state'])) {
            unset($_SESSION['sync_state']);
        }
    }
    
    /**
     * Get the primary category name for a product
     * 
     * @param object $product WooCommerce product object
     * @return string
     */
    private function getCategoryName($product) {
        if (!empty($product->categories) && is_array($product->categories) && isset($product->categories[0]->name)) {
            return $product->categories[0]->name;
        }
        
        return '';
    }
    
    /**
     * Get the featured image URL for a product
     * 
     * @param object $product WooCommerce product object
     * @return string
     */
    private function getProductImage($product) {
        if (!empty($product->images) && is_array($product->images) && isset($product->images[0]->src)) {
            return $product->images[0]->src;
        }
        
        return '';
    }
    
    /**
     * Log a sync operation
     * 
     * @param int $products_added
     * @param int $products_updated
     * @param string $status
     * @param string $details
     * @return bool
     */
    private function logSync($products_added, $products_updated, $status, $details = '') {
        $now = date('Y-m-d H:i:s');
        $products_added = (int)$products_added;
        $products_updated = (int)$products_updated;
        $status = $this->db->escapeString($status);
        $details = $this->db->escapeString($details);
        
        $sql = "INSERT INTO sync_log 
                (sync_date, products_added, products_updated, status, details) 
                VALUES 
                ('$now', $products_added, $products_updated, '$status', '$details')";
        
        return $this->db->query($sql);
    }
    
    /**
     * Get the last sync log
     * 
     * @return object|null
     */
    public function getLastSync() {
        $sql = "SELECT * FROM sync_log ORDER BY sync_date DESC LIMIT 1";
        $result = $this->db->query($sql);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_object();
        }
        
        return null;
    }
    
    /**
     * Get all sync logs
     * 
     * @param int $limit
     * @return array
     */
    public function getSyncLogs($limit = 10) {
        $limit = (int)$limit;
        $sql = "SELECT * FROM sync_log ORDER BY sync_date DESC LIMIT $limit";
        $result = $this->db->query($sql);
        $logs = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_object()) {
                $logs[] = $row;
            }
        }
        
        return $logs;
    }
    
    /**
     * Get current sync progress
     * 
     * @return array Progress information
     */
    public function getSyncProgress() {
        $state = $this->getSyncState();
        
        if ($state['status'] !== self::SYNC_STATE_IN_PROGRESS) {
            return [
                'in_progress' => false,
                'percent' => 0,
                'products_added' => 0,
                'products_updated' => 0,
                'processed' => 0,
                'total' => 0,
                'page' => 1,
                'last_count' => 0
            ];
        }
        
        // Calculate percent based on estimated total
        $percent = 0;
        if ($state['estimated_total'] > 0) {
            $percent = min(99, round(($state['processed_products'] / $state['estimated_total']) * 100));
        }
        
        return [
            'in_progress' => true,
            'percent' => $percent,
            'products_added' => isset($state['products_added']) ? $state['products_added'] : 0,
            'products_updated' => isset($state['products_updated']) ? $state['products_updated'] : 0,
            'processed' => isset($state['processed_products']) ? $state['processed_products'] : 0,
            'total' => isset($state['estimated_total']) ? $state['estimated_total'] : 0,
            'page' => isset($state['page']) ? $state['page'] : 1,
            'last_count' => isset($state['last_count']) ? $state['last_count'] : 0
        ];
    }

    /**
     * Synchronize variable products and their variations with smaller batch sizes
     * Replace the syncVariableProducts method in your Sync.php file with this optimized version
     * 
     * @return array Results of the sync operation
     */
    public function syncVariableProducts() {
        $results = [
            'products_added' => 0,
            'products_updated' => 0,
            'variations_added' => 0,
            'variations_updated' => 0,
            'errors' => [],
            'start_time' => microtime(true),
            'end_time' => null,
            'status' => 'success'
        ];
    
        // Check if we have a variable products sync state
        $var_sync_state = $this->getVariableSyncState();
        
        // If a variable sync is already in progress, resume it
        if ($var_sync_state['in_progress']) {
            return $this->continueVariableProductsSync($var_sync_state);
        }
        
        try {
            // Test WooCommerce API connection
            $connection_test = $this->woocommerce->testConnection();
            if (!$connection_test['success']) {
                throw new Exception("WooCommerce API connection failed: " . $connection_test['message']);
            }
    
            // Use smaller batches for variable products
            $page = 1;
            $per_page = 5; // Smaller batch size to prevent timeouts
            
            // Initialize variable sync state
            $var_sync_state = [
                'in_progress' => true,
                'page' => $page,
                'per_page' => $per_page,
                'products_added' => 0,
                'products_updated' => 0,
                'variations_added' => 0,
                'variations_updated' => 0,
                'processed_parents' => 0,
                'total_parents' => 0,
                'processed_products' => [],
                'current_parent_index' => 0,
                'all_variable_products' => [],
                'errors' => []
            ];
            
            // Get variable products count first
            $count_result = $this->woocommerce->getVariableProductsCount();
            $total_variable_products = $count_result['count'] ?? 100; // Default to 100 if count fails
            $var_sync_state['total_parents'] = $total_variable_products;
            
            // Get first batch of variable products
            $variable_products = $this->woocommerce->getVariableProducts($per_page, $page);
            
            if (!empty($variable_products)) {
                $var_sync_state['all_variable_products'] = $variable_products;
                $this->saveVariableSyncState($var_sync_state);
                
                // Process the first batch
                return $this->continueVariableProductsSync($var_sync_state);
            } else {
                // No variable products found
                $results['end_time'] = microtime(true);
                $results['duration'] = $results['end_time'] - $results['start_time'];
                return $results;
            }
        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['errors'][] = $e->getMessage();
            $results['end_time'] = microtime(true);
            $results['duration'] = $results['end_time'] - $results['start_time'];
            
            // Log the error
            $this->logSync(0, 0, 'error', "Variable products sync error: " . $e->getMessage());
            
            // Reset variable sync state
            $this->resetVariableSyncState();
            
            return $results;
        }
    }
    
    /**
     * Continue syncing variable products from the saved state
     * 
     * @param array $state Current variable sync state
     * @return array Results of the sync operation
     */
    private function continueVariableProductsSync($state) {
        $results = [
            'products_added' => $state['products_added'],
            'products_updated' => $state['products_updated'],
            'variations_added' => $state['variations_added'],
            'variations_updated' => $state['variations_updated'],
            'errors' => $state['errors'],
            'start_time' => microtime(true),
            'end_time' => null,
            'status' => 'success',
            'progress_percent' => 0
        ];
        
        try {
            // Process variable products one at a time
            $products_to_process = $state['all_variable_products'];
            $current_index = $state['current_parent_index'];
            $max_products_per_batch = 1; // Process just one parent per batch to avoid timeouts
            $products_processed = 0;
            
            // Calculate progress percent
            if ($state['total_parents'] > 0) {
                $results['progress_percent'] = min(99, round(($state['processed_parents'] / $state['total_parents']) * 100));
            }
            
            // Process up to max_products_per_batch products
            while ($current_index < count($products_to_process) && $products_processed < $max_products_per_batch) {
                $variable_product = $products_to_process[$current_index];
                
                // Process the variable product
                $product_result = $this->processVariableProduct($variable_product);
                
                // Update counts
                $results['products_added'] += $product_result['products_added'];
                $results['products_updated'] += $product_result['products_updated'];
                $results['variations_added'] += $product_result['variations_added'];
                $results['variations_updated'] += $product_result['variations_updated'];
                
                if (!empty($product_result['errors'])) {
                    $results['errors'] = array_merge($results['errors'], $product_result['errors']);
                }
                
                // Update state
                $state['processed_parents']++;
                $state['products_added'] = $results['products_added'];
                $state['products_updated'] = $results['products_updated'];
                $state['variations_added'] = $results['variations_added'];
                $state['variations_updated'] = $results['variations_updated'];
                $state['processed_products'][] = $variable_product->id;
                
                $current_index++;
                $products_processed++;
            }
            
            // Update the current index
            $state['current_parent_index'] = $current_index;
            
            // Check if we need to get more variable products
            if ($current_index >= count($products_to_process)) {
                $page = $state['page'] + 1;
                $per_page = $state['per_page'];
                
                // Get next batch of variable products
                $new_products = $this->woocommerce->getVariableProducts($per_page, $page);
                
                if (!empty($new_products)) {
                    // Add new products to the list
                    $state['all_variable_products'] = $new_products;
                    $state['current_parent_index'] = 0;
                    $state['page'] = $page;
                } else {
                    // No more products, we're done
                    $results['end_time'] = microtime(true);
                    $results['duration'] = $results['end_time'] - $results['start_time'];
                    $results['is_complete'] = true;
                    $results['progress_percent'] = 100;
                    
                    // Log the sync
                    $log_message = "Variable products sync: Added {$results['products_added']} parents, {$results['variations_added']} variations, Updated {$results['products_updated']} parents, {$results['variations_updated']} variations";
                    $this->logSync(
                        $results['products_added'] + $results['variations_added'], 
                        $results['products_updated'] + $results['variations_updated'], 
                        'success', 
                        $log_message
                    );
                    
                    // Reset variable sync state
                    $this->resetVariableSyncState();
                    
                    return $results;
                }
            }
            
            // Save updated state
            $this->saveVariableSyncState($state);
            
            // Update results with progress
            if ($state['total_parents'] > 0) {
                $results['progress_percent'] = min(99, round(($state['processed_parents'] / $state['total_parents']) * 100));
            }
            
            $results['processed_parents'] = $state['processed_parents'];
            $results['total_parents'] = $state['total_parents'];
            $results['is_complete'] = false;
            
            return $results;
        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['errors'][] = $e->getMessage();
            $results['end_time'] = microtime(true);
            $results['duration'] = $results['end_time'] - $results['start_time'];
            
            // Log the error
            $this->logSync(0, 0, 'error', "Variable products sync error: " . $e->getMessage());
            
            // Don't reset state on error to allow resuming
            return $results;
        }
    }
    
    /**
     * Process a single variable product and its variations
     * 
     * @param object $variable_product The variable product to process
     * @return array Results of processing the product
     */
        /**
     * Process a single variable product and its variations with correct parent_id handling
     * 
     * @param object $variable_product The variable product to process
     * @return array Results of processing the product
     */
    private function processVariableProduct($variable_product) {
        $result = [
            'products_added' => 0,
            'products_updated' => 0,
            'variations_added' => 0,
            'variations_updated' => 0,
            'errors' => []
        ];
        
        try {
            // Check if this variable product already exists in our database
            $existing_product = $this->product->getByProductId($variable_product->id);
            
            // Prepare product data
            $product_data = [
                'product_id' => $variable_product->id,
                'title' => $variable_product->name,
                'sku' => isset($variable_product->sku) ? $variable_product->sku : '',
                'category' => $this->getCategoryName($variable_product),
                'price' => isset($variable_product->price) ? $variable_product->price : 0,
                'image_url' => $this->getProductImage($variable_product),
                'stock' => 0, // Variable products don't have their own stock
                'product_type' => 'variable', // CRITICAL: Set correct product type
                'parent_id' => null, // Variable products have no parent
                'notes' => 'Variable product'
            ];
            
            $parent_database_id = null;
            
            // Add or update the parent variable product
            if ($existing_product) {
                if ($this->product->update($existing_product->id, $product_data)) {
                    $result['products_updated']++;
                    $parent_database_id = $existing_product->id;
                }
            } else {
                $parent_database_id = $this->product->add($product_data);
                if ($parent_database_id) {
                    $result['products_added']++;
                }
            }
            
            if (!$parent_database_id) {
                throw new Exception("Failed to create/update parent product");
            }
            
            // Now get and process the variations
            $variations = $this->woocommerce->getPublishedProductVariations($variable_product->id);
            
            if (empty($variations)) {
                return $result; // No variations to process
            }
    
            // Get deleted variations that should be skipped
            $deleted_variations = [];
            $sql = "SELECT variation_id FROM deleted_variations";
            $db_result = $this->db->query($sql);
            
            if ($db_result && $db_result->num_rows > 0) {
                while ($row = $db_result->fetch_object()) {
                    $deleted_variations[$row->variation_id] = true;
                }
            }
            
            // Process each variation
            foreach ($variations as $variation) {
                // Skip if already deleted
                if (isset($deleted_variations[$variation->id])) {
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
                $variation_title = $variable_product->name;
                if (!empty($attributes_text)) {
                    $variation_title .= ' - ' . $attributes_text;
                }
                
                // Prepare variation data with CORRECT parent_id (database ID)
                $variation_data = [
                    'product_id' => $variation->id,
                    'title' => $variation_title,
                    'sku' => isset($variation->sku) ? $variation->sku : '',
                    'category' => $this->getCategoryName($variable_product),
                    'price' => isset($variation->price) ? $variation->price : 0,
                    'image_url' => $this->getVariationImage($variation, $variable_product),
                    'stock' => 0, // Default stock
                    'low_stock_threshold' => DEFAULT_LOW_STOCK_THRESHOLD,
                    'product_type' => 'variation', // CRITICAL: Set correct product type
                    'parent_id' => $parent_database_id, // CRITICAL: Use database ID, not WooCommerce ID
                    'variation_attributes' => !empty($variation->attributes) ? $variation->attributes : null,
                    'notes' => $attributes_text
                ];
                
                // Add or update variation
                $existing_variation = $this->product->getByProductId($variation->id);
                
                if ($existing_variation) {
                    if ($this->product->update($existing_variation->id, $variation_data)) {
                        $result['variations_updated']++;
                    }
                } else {
                    if ($this->product->add($variation_data)) {
                        $result['variations_added']++;
                    }
                }
            }
            
            return $result;
        } catch (Exception $e) {
            $result['errors'][] = "Error processing product {$variable_product->id}: " . $e->getMessage();
            error_log("Error processing variable product {$variable_product->id}: " . $e->getMessage());
            return $result;
        }
    }
    
    /**
     * Get the variable sync state
     * 
     * @return array Variable sync state
     */
    private function getVariableSyncState() {
        $default_state = [
            'in_progress' => false,
            'page' => 1,
            'per_page' => 5,
            'products_added' => 0,
            'products_updated' => 0,
            'variations_added' => 0,
            'variations_updated' => 0,
            'processed_parents' => 0,
            'total_parents' => 0,
            'processed_products' => [],
            'current_parent_index' => 0,
            'all_variable_products' => [],
            'errors' => []
        ];
        
        if (!isset($_SESSION['variable_sync_state'])) {
            return $default_state;
        }
        
        $state = $_SESSION['variable_sync_state'];
        
        // Make sure all expected keys exist, use defaults if missing
        foreach ($default_state as $key => $default_value) {
            if (!isset($state[$key])) {
                $state[$key] = $default_value;
            }
        }
        
        // Check if the sync has timed out (more than 60 minutes)
        if ($state['in_progress']) {
            $timeout = 60 * 60; // 60 minutes
            if (isset($state['last_update']) && (time() - $state['last_update'] > $timeout)) {
                // If timed out, reset state and return default
                $this->resetVariableSyncState();
                return $default_state;
            }
        }
        
        return $state;
    }
    
    /**
     * Save the variable sync state
     * 
     * @param array $state Variable sync state
     */
    private function saveVariableSyncState($state) {
        $state['last_update'] = time();
        $_SESSION['variable_sync_state'] = $state;
    }
    
    /**
     * Reset the variable sync state
     */
    private function resetVariableSyncState() {
        if (isset($_SESSION['variable_sync_state'])) {
            unset($_SESSION['variable_sync_state']);
        }
    }

    /**
     * Get variation image URL
     * 
     * @param object $variation WooCommerce variation object
     * @param object $parent Parent product object (fallback for image)
     * @return string Image URL
     */
    private function getVariationImage($variation, $parent) {
        if (isset($variation->image) && isset($variation->image->src)) {
            return $variation->image->src;
        }
        
        // Fallback to parent image
        return $this->getProductImage($parent);
    }
}