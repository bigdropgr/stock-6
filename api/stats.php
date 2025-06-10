<?php
/**
 * Stats API
 * 
 * Handles AJAX requests for dashboard statistics
 */

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Product.php';
require_once '../includes/WooCommerce.php';
require_once '../includes/Sync.php';
require_once '../includes/functions.php';

// Initialize classes
$auth = new Auth();
$product = new Product();
$woocommerce = new WooCommerce();
$sync = new Sync();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Handle request
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {
    case 'dashboard':
        // Get dashboard statistics
        $total_products = $product->countAll();
        $total_value = $product->getTotalValue();
        $low_stock_products = $product->getLowStock(5);
        $recently_updated = $product->getRecentlyUpdated(5);
        
        // Get WooCommerce data
        $top_selling = $woocommerce->getTopSellingProducts(5);
        $wc_low_stock = $woocommerce->getLowStockProducts(5, 5);
        $recently_added = $woocommerce->getRecentlyAddedProducts(7, 5);
        
        // Get last sync
        $last_sync = $sync->getLastSync();
        
        json_response([
            'success' => true,
            'stats' => [
                'total_products' => $total_products,
                'total_value' => $total_value,
                'low_stock_count' => count($low_stock_products),
                'last_sync' => $last_sync ? $last_sync->sync_date : null
            ],
            'products' => [
                'low_stock' => $low_stock_products,
                'recently_updated' => $recently_updated,
                'top_selling' => $top_selling,
                'wc_low_stock' => $wc_low_stock,
                'recently_added' => $recently_added
            ]
        ]);
        break;
        
    case 'inventory_value':
        // Get inventory value
        $total_value = $product->getTotalValue();
        
        json_response([
            'success' => true,
            'value' => $total_value,
            'formatted_value' => format_price($total_value)
        ]);
        break;
        
    case 'product_counts':
        // Get product counts
        $total_products = $product->countAll();
        
        // Count products by stock status
        $db = Database::getInstance();
        
        // Out of stock
        $sql = "SELECT COUNT(*) as count FROM physical_inventory WHERE stock = 0";
        $result = $db->query($sql);
        $out_of_stock = ($result && $result->num_rows > 0) ? $result->fetch_object()->count : 0;
        
        // Low stock
        $sql = "SELECT COUNT(*) as count FROM physical_inventory WHERE is_low_stock = 1 AND stock > 0";
        $result = $db->query($sql);
        $low_stock = ($result && $result->num_rows > 0) ? $result->fetch_object()->count : 0;
        
        // In stock
        $sql = "SELECT COUNT(*) as count FROM physical_inventory WHERE is_low_stock = 0 AND stock > 0";
        $result = $db->query($sql);
        $in_stock = ($result && $result->num_rows > 0) ? $result->fetch_object()->count : 0;
        
        json_response([
            'success' => true,
            'counts' => [
                'total' => $total_products,
                'in_stock' => $in_stock,
                'low_stock' => $low_stock,
                'out_of_stock' => $out_of_stock
            ]
        ]);
        break;
        
    default:
        json_response(['success' => false, 'message' => 'Invalid action'], 400);
        break;
}