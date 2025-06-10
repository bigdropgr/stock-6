<?php
/**
 * Index Page
 * 
 * Fixed redirect loop issue
 */

// Include required files
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/functions.php';

// Include translation system if available
if (file_exists('includes/i18n.php')) {
    require_once 'includes/i18n.php';
}

// Initialize authentication
$auth = new Auth();

// Check if user is logged in
if ($auth->isLoggedIn()) {
    // Redirect to dashboard without leading slash to avoid conflicts
    header('Location: dashboard.php');
    exit;
} else {
    // Redirect to login page without leading slash
    header('Location: login.php');
    exit;
}