<?php
/**
 * Main configuration file
 */

// Site settings
define('SITE_NAME', 'Physical Store Inventory');
define('SITE_URL', 'https://stock.vakoufaris.com');
define('ADMIN_EMAIL', 'admin@example.com');

// Define application environment
define('APP_ENV', 'development'); // Options: development, production

// Session settings
define('SESSION_NAME', 'store_inventory_session');
define('SESSION_LIFETIME', 3600); // 1 hour in seconds

// WooCommerce API settings
define('WC_STORE_URL', 'https://vakoufaris.com');
define('WC_CONSUMER_KEY', 'ck_536816afd00c1ef239dffedef68342bb7ae8b6bb');
define('WC_CONSUMER_SECRET', 'cs_65dca7980530866472e13d2d192644fb9a9d8774');
define('WC_API_VERSION', 'wc/v3');

// Low stock threshold default
define('DEFAULT_LOW_STOCK_THRESHOLD', 5);

// Error reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set default timezone
date_default_timezone_set('Europe/Athens');