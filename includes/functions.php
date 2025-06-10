<?php
/**
 * Helper Functions
 * 
 * Updated to fix deprecated warnings and improve safety
 */

/**
 * Format a price as currency
 */
function format_price($price) {
    // Handle null/empty values
    if ($price === null || $price === '') {
        $price = 0;
    }
    return '€' . number_format((float)$price, 2, '.', ',');
}

/**
 * Format a date
 */
function format_date($date, $format = 'd/m/Y H:i') {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return 'Never';
    }
    return date($format, strtotime($date));
}

/**
 * Get a time-ago string from a date
 */
function time_ago($date) {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return 'Never';
    }
    
    $timestamp = strtotime($date);
    $strTime = ['second', 'minute', 'hour', 'day', 'month', 'year'];
    $length = ['60', '60', '24', '30', '12', '10'];

    $currentTime = time();
    if ($currentTime >= $timestamp) {
        $diff = $currentTime - $timestamp;
        
        for ($i = 0; $diff >= $length[$i] && $i < count($length) - 1; $i++) {
            $diff = $diff / $length[$i];
        }

        $diff = round($diff);
        if ($diff == 1) {
            return $diff . ' ' . $strTime[$i] . ' ago';
        } else {
            return $diff . ' ' . $strTime[$i] . 's ago';
        }
    }
    
    return 'just now';
}

/**
 * Display a flash message
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    
    return null;
}

/**
 * Sanitize input - handles null values properly
 */
function sanitize($input) {
    if ($input === null) {
        return '';
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Safe htmlspecialchars that handles null values
 */
function safe_htmlspecialchars($string) {
    if ($string === null || $string === '') {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect($url, $status = 302) {
    // Make sure URL starts with a slash for absolute path if it's not a full URL
    if (strpos($url, 'http') !== 0 && strpos($url, '/') !== 0) {
        $url = '/' . $url;
    }
    
    // For URLs with just a slash, redirect to the domain root
    if ($url === '/') {
        $url = '/index.php';
    }
    
    // Handle URLs that might be malformed with double slashes
    $url = preg_replace('#([^:])//+#', '$1/', $url);
    
    header('Location: ' . $url, true, $status);
    exit;
}

/**
 * Check if a request is AJAX
 */
function is_ajax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Return JSON response
 */
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Generate a random string
 */
function random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $randomString;
}

/**
 * Get the base URL of the application
 */
function base_url() {
    return SITE_URL;
}

/**
 * Get the current URL
 */
function current_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Create pagination links
 */
function pagination($current_page, $total_pages, $url_pattern = '?page=%d') {
    if ($total_pages <= 1) {
        return '';
    }
    
    $links = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous link
    if ($current_page > 1) {
        $links .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $current_page - 1) . '">&laquo; Previous</a></li>';
    } else {
        $links .= '<li class="page-item disabled"><a class="page-link" href="#">&laquo; Previous</a></li>';
    }
    
    // Page numbers
    $range = 2;
    
    for ($i = max(1, $current_page - $range); $i <= min($total_pages, $current_page + $range); $i++) {
        if ($i == $current_page) {
            $links .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $links .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $i) . '">' . $i . '</a></li>';
        }
    }
    
    // Next link
    if ($current_page < $total_pages) {
        $links .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $current_page + 1) . '">Next &raquo;</a></li>';
    } else {
        $links .= '<li class="page-item disabled"><a class="page-link" href="#">Next &raquo;</a></li>';
    }
    
    $links .= '</ul></nav>';
    
    return $links;
}

/**
 * Debug function to print variables
 */
function debug($var, $exit = true) {
    echo '<pre>';
    print_r($var);
    echo '</pre>';
    
    if ($exit) {
        exit;
    }
}

/**
 * Log error to file
 */
function log_error($message, $level = 'ERROR') {
    $log_file = __DIR__ . '/../logs/app.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $time = date('Y-m-d H:i:s');
    $log_message = "[$time] [$level] $message" . PHP_EOL;
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * Check if running in CLI mode
 */
function is_cli() {
    return php_sapi_name() === 'cli';
}

/**
 * Truncate a string to a specified length - handles null values
 */
function truncate($string, $length = 100, $append = '...') {
    if ($string === null || $string === '') {
        return '';
    }
    
    if (strlen($string) > $length) {
        $string = substr($string, 0, $length) . $append;
    }
    
    return $string;
}