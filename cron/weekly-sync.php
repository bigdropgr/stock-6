<?php
/**
 * Enhanced Weekly Sync Cron Job
 * 
 * This script runs automatically every Monday to sync products with WooCommerce
 * Enhanced with better error handling and email notifications
 * 
 * Set up the cron job to run this script once a week:
 * 0 2 * * 1 php /path/to/stock.vakoufaris.com/cron/weekly-sync.php
 */

// Define CLI mode
define('IS_CLI', php_sapi_name() === 'cli');

// Set unlimited execution time for large imports
set_time_limit(0);

// Enhanced error reporting
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/weekly-sync.log');

// Create logs directory if it doesn't exist
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Log function
function log_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Log to file
    file_put_contents(__DIR__ . '/../logs/weekly-sync.log', $log_message, FILE_APPEND);
    
    // Also output to console if running in CLI
    if (IS_CLI) {
        echo $log_message;
    }
}

// Start logging
log_message("=== Starting Weekly Product Synchronization ===");

try {
    // Include required files
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/Database.php';
    require_once __DIR__ . '/../includes/Product.php';
    require_once __DIR__ . '/../includes/WooCommerce.php';
    require_once __DIR__ . '/../includes/Sync.php';
    require_once __DIR__ . '/../includes/functions.php';

    // Initialize classes
    $sync = new Sync();
    $woocommerce = new WooCommerce();

    log_message("Classes initialized successfully");

    // Test WooCommerce connection first
    log_message("Testing WooCommerce API connection...");
    $connection_test = $woocommerce->testConnection();
    
    if (!$connection_test['success']) {
        throw new Exception("WooCommerce API connection failed: " . $connection_test['message']);
    }
    
    log_message("WooCommerce API connection successful");

    // Get initial stats
    $product = new Product();
    $initial_count = $product->countAll();
    log_message("Initial product count: $initial_count");

    $start_time = microtime(true);
    
    // Perform sync (not full sync - only add new products and update basic info)
    log_message("Starting product synchronization (incremental sync)...");
    $result = $sync->syncProducts(false);

    $end_time = microtime(true);
    $duration = $end_time - $start_time;

    // Get final stats
    $final_count = $product->countAll();
    $net_added = $final_count - $initial_count;

    // Output results
    log_message("Synchronization completed in " . number_format($duration, 2) . " seconds");
    log_message("Products added: " . $result['products_added']);
    log_message("Products updated: " . $result['products_updated']);
    log_message("Total products before sync: $initial_count");
    log_message("Total products after sync: $final_count");
    log_message("Net products added: $net_added");
    log_message("Status: " . $result['status']);

    // Check for errors
    if ($result['status'] === 'error') {
        $error_details = implode("\n", $result['errors']);
        log_message("Sync completed with errors: $error_details", 'ERROR');
        
        // Send error notification email
        send_error_notification("Weekly sync completed with errors", $error_details, $result);
        
        exit(1);
    } else {
        log_message("=== Weekly sync completed successfully ===");
        
        // Send success notification if significant changes occurred
        if ($result['products_added'] > 0 || $result['products_updated'] > 10) {
            send_success_notification($result, $duration);
        }
    }

    exit(0);

} catch (Exception $e) {
    $error_message = "Fatal error during weekly sync: " . $e->getMessage();
    log_message($error_message, 'FATAL');
    
    // Send error notification
    send_error_notification("Weekly sync failed", $error_message, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    exit(1);
}

/**
 * Send error notification email
 */
function send_error_notification($subject, $message, $details = []) {
    $to = ADMIN_EMAIL;
    $subject = "[" . SITE_NAME . "] $subject";
    
    $body = "An error occurred during the weekly product synchronization:\n\n";
    $body .= "Error: $message\n\n";
    
    if (!empty($details)) {
        $body .= "Details:\n";
        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $body .= "- $key: $value\n";
        }
        $body .= "\n";
    }
    
    $body .= "Time: " . date('Y-m-d H:i:s') . "\n";
    $body .= "Server: " . $_SERVER['SERVER_NAME'] . "\n";
    $body .= "\nPlease check the system logs for more information.";
    
    $headers = "From: noreply@" . $_SERVER['SERVER_NAME'] . "\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    if (function_exists('mail')) {
        mail($to, $subject, $body, $headers);
        log_message("Error notification sent to $to");
    } else {
        log_message("Could not send error notification - mail function not available", 'WARNING');
    }
}

/**
 * Send success notification email for significant changes
 */
function send_success_notification($result, $duration) {
    $to = ADMIN_EMAIL;
    $subject = "[" . SITE_NAME . "] Weekly sync completed - " . $result['products_added'] . " products added";
    
    $body = "Weekly product synchronization completed successfully!\n\n";
    $body .= "Summary:\n";
    $body .= "- Products added: " . $result['products_added'] . "\n";
    $body .= "- Products updated: " . $result['products_updated'] . "\n";
    $body .= "- Duration: " . number_format($duration, 2) . " seconds\n";
    $body .= "- Time: " . date('Y-m-d H:i:s') . "\n\n";
    
    if (isset($result['variations_added']) && $result['variations_added'] > 0) {
        $body .= "Variable Products:\n";
        $body .= "- Variations added: " . $result['variations_added'] . "\n";
        $body .= "- Variations updated: " . $result['variations_updated'] . "\n\n";
    }
    
    $body .= "No action required - this is an informational message.\n";
    $body .= "You can view the updated inventory at: " . SITE_URL;
    
    $headers = "From: noreply@" . $_SERVER['SERVER_NAME'] . "\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    if (function_exists('mail')) {
        mail($to, $subject, $body, $headers);
        log_message("Success notification sent to $to");
    }
}