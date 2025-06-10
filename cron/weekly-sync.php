<?php
/**
 * Weekly Sync Cron Job
 * 
 * This script is meant to be executed by a cron job every Monday
 * to synchronize products with WooCommerce.
 * 
 * Set up the cron job to run this script once a week:
 * 0 1 * * 1 php /path/to/stock.vakoufaris.com/cron/weekly-sync.php
 */

// Define CLI mode
define('IS_CLI', php_sapi_name() === 'cli');

// Exit if not running in CLI mode
if (!IS_CLI) {
    echo "This script can only be run from the command line.";
    exit(1);
}

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

echo "Starting weekly product synchronization...\n";
$start_time = microtime(true);

// Perform sync (not full sync - only add new products and update basic info)
$result = $sync->syncProducts(false);

$end_time = microtime(true);
$duration = $end_time - $start_time;

// Output results
echo "Synchronization completed in " . number_format($duration, 2) . " seconds.\n";
echo "Products added: " . $result['products_added'] . "\n";
echo "Products updated: " . $result['products_updated'] . "\n";
echo "Status: " . $result['status'] . "\n";

if ($result['status'] === 'error') {
    echo "Errors: " . implode("\n", $result['errors']) . "\n";
    exit(1);
}

exit(0);