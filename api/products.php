<?php
/**
 * Products API
 * 
 * Handles AJAX requests for product operations with proper variable product support
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Product.php';
require_once '../includes/functions.php';

$auth = new Auth();
$product = new Product();

if (!$auth->isLoggedIn()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {
    case 'search':
        $term = isset($_GET['term']) ? $_GET['term'] : '';
        
        if (empty($term)) {
            json_response(['success' => false, 'message' => 'Search term is required']);
        }
        
        $results = $product->search($term);
        json_response($results);
        break;
        
    case 'get':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id <= 0) {
            json_response(['success' => false, 'message' => 'Invalid product ID']);
        }
        
        $product_data = $product->getById($id);
        
        if (!$product_data) {
            json_response(['success' => false, 'message' => 'Product not found'], 404);
        }
        
        json_response(['success' => true, 'product' => $product_data]);
        break;
        
    case 'update_stock':
        $id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
        
        if ($id <= 0) {
            json_response(['success' => false, 'message' => 'Invalid product ID']);
        }
        
        if ($stock < 0) {
            json_response(['success' => false, 'message' => 'Stock must be a positive number']);
        }
        
        $product_data = $product->getById($id);
        
        if (!$product_data) {
            json_response(['success' => false, 'message' => 'Product not found'], 404);
        }
        
        $result = $product->updateStock($id, $stock);
        
        if ($result) {
            json_response(['success' => true, 'message' => 'Stock updated successfully', 'stock' => $stock]);
        } else {
            json_response(['success' => false, 'message' => 'Failed to update stock']);
        }
        break;
        
    case 'update':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            json_response(['success' => false, 'message' => 'Invalid product ID']);
        }
        
        $product_data = $product->getById($id);
        
        if (!$product_data) {
            json_response(['success' => false, 'message' => 'Product not found'], 404);
        }
        
        $update_data = [];
        
        if (isset($_POST['stock'])) {
            $update_data['stock'] = intval($_POST['stock']);
        }
        
        if (isset($_POST['low_stock_threshold'])) {
            $update_data['low_stock_threshold'] = intval($_POST['low_stock_threshold']);
        }
        
        if (isset($_POST['notes'])) {
            $update_data['notes'] = $_POST['notes'];
        }
        
        if (empty($update_data)) {
            json_response(['success' => false, 'message' => 'No data to update']);
        }
        
        $result = $product->update($id, $update_data);
        
        if ($result) {
            $updated_product = $product->getById($id);
            json_response(['success' => true, 'message' => 'Product updated successfully', 'product' => $updated_product]);
        } else {
            json_response(['success' => false, 'message' => 'Failed to update product']);
        }
        break;
        
    case 'list':
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
        
        if ($page < 1) {
            $page = 1;
        }
        
        if ($per_page < 1 || $per_page > 100) {
            $per_page = 20;
        }
        
        $offset = ($page - 1) * $per_page;
        $products = $product->getAll($per_page, $offset);
        $total = $product->countAll();
        
        json_response([
            'success' => true,
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ]);
        break;
        
    case 'low_stock':
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
        
        if ($limit < 1 || $limit > 100) {
            $limit = 20;
        }
        
        $low_stock = $product->getLowStock($limit);
        
        json_response([
            'success' => true,
            'products' => $low_stock,
            'count' => count($low_stock)
        ]);
        break;
        
    case 'recently_updated':
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
        
        if ($limit < 1 || $limit > 100) {
            $limit = 20;
        }
        
        $recently_updated = $product->getRecentlyUpdated($limit);
        
        json_response([
            'success' => true,
            'products' => $recently_updated,
            'count' => count($recently_updated)
        ]);
        break;
        
    case 'stats':
        $total_products = $product->countAll();
        $total_value = $product->getTotalValue();
        $low_stock_count = count($product->getLowStock(1000));
        
        json_response([
            'success' => true,
            'stats' => [
                'total_products' => $total_products,
                'total_value' => $total_value,
                'low_stock_count' => $low_stock_count
            ]
        ]);
        break;
        
    case 'update_variation_stock':
        $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
        $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
        
        if ($variation_id <= 0) {
            json_response(['success' => false, 'message' => 'Invalid variation ID']);
        }
        
        if ($stock < 0) {
            json_response(['success' => false, 'message' => 'Stock must be a positive number']);
        }
        
        $variation = $product->getById($variation_id);
        
        if (!$variation || $variation->product_type !== 'variation') {
            json_response(['success' => false, 'message' => 'Variation not found'], 404);
        }
        
        $result = $product->updateStock($variation_id, $stock);
        
        if ($result) {
            json_response(['success' => true, 'message' => 'Stock updated successfully', 'stock' => $stock]);
        } else {
            json_response(['success' => false, 'message' => 'Failed to update stock']);
        }
        break;
        
    case 'delete_variation':
        $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
        $wc_variation_id = isset($_POST['wc_variation_id']) ? intval($_POST['wc_variation_id']) : 0;
        
        if ($variation_id <= 0 || $wc_variation_id <= 0) {
            json_response(['success' => false, 'message' => 'Invalid variation ID']);
        }
        
        $variation = $product->getById($variation_id);
        
        if (!$variation || $variation->product_type !== 'variation') {
            json_response(['success' => false, 'message' => 'Variation not found'], 404);
        }
        
        $result = $product->deleteVariation($variation_id, $wc_variation_id);
        
        if ($result) {
            json_response(['success' => true, 'message' => 'Variation deleted successfully']);
        } else {
            json_response(['success' => false, 'message' => 'Failed to delete variation']);
        }
        break;

    case 'get_variations':
        $parent_id = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : 0;
        
        if ($parent_id <= 0) {
            json_response(['success' => false, 'message' => 'Invalid parent product ID']);
        }
        
        $parent_product = $product->getByProductId($parent_id);
        
        if (!$parent_product || !$product->isVariableProduct($parent_product)) {
            json_response(['success' => false, 'message' => 'Parent product not found or not a variable product'], 404);
        }
        
        $variations = $product->getVariations($parent_id);
        
        json_response([
            'success' => true,
            'variations' => $variations,
            'count' => count($variations)
        ]);
        break;
        
    default:
        json_response(['success' => false, 'message' => 'Invalid action'], 400);
        break;
}