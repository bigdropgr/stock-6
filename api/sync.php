<?php
/**
 * Sync API
 * 
 * Handles AJAX requests for synchronization with WooCommerce
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
$sync = new Sync();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Handle request
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {
    case 'sync_products':
        // Sync products with WooCommerce
        $full_sync = isset($_POST['full_sync']) && intval($_POST['full_sync']) === 1;
        
        // Perform sync
        $result = $sync->syncProducts($full_sync);
        
        // Return result
        json_response([
            'status' => $result['status'],
            'products_added' => $result['products_added'],
            'products_updated' => $result['products_updated'],
            'duration' => $result['duration'],
            'errors' => $result['errors']
        ]);
        break;
        
    case 'get_sync_logs':
        // Get sync logs
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        
        if ($limit < 1 || $limit > 100) {
            $limit = 10;
        }
        
        $logs = $sync->getSyncLogs($limit);
        
        json_response([
            'success' => true,
            'logs' => $logs,
            'count' => count($logs)
        ]);
        break;
        
    case 'get_last_sync':
        // Get last sync
        $last_sync = $sync->getLastSync();
        
        if ($last_sync) {
            json_response([
                'success' => true,
                'sync' => $last_sync
            ]);
        } else {
            json_response([
                'success' => false,
                'message' => 'No sync data available'
            ]);
        }
        break;
        
    default:
        json_response(['success' => false, 'message' => 'Invalid action'], 400);
        break;
}