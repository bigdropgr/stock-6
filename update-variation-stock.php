<?php
/**
 * Simple Variation Update Endpoint
 * 
 * Temporary direct endpoint to update variation stock
 * Save this as: update-variation-stock.php in your root directory
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';
require_once 'includes/functions.php';

// Set JSON content type
header('Content-Type: application/json');

try {
    $auth = new Auth();
    $product = new Product();

    // Check authentication
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    // Get and validate input
    $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;

    if ($variation_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid variation ID']);
        exit;
    }

    if ($stock < 0) {
        echo json_encode(['success' => false, 'message' => 'Stock must be a positive number']);
        exit;
    }

    // Get the variation
    $variation = $product->getById($variation_id);

    if (!$variation || $variation->product_type !== 'variation') {
        echo json_encode(['success' => false, 'message' => 'Variation not found']);
        exit;
    }

    // Update the stock
    $result = $product->updateStock($variation_id, $stock);

    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Stock updated successfully', 
            'stock' => $stock,
            'variation_id' => $variation_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update stock']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}