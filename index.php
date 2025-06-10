<?php
/**
 * Index Page
 * 
 * Redirects to dashboard or login page depending on authentication status
 */

// Include required files
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/functions.php';

// Initialize authentication
$auth = new Auth();

// Redirect to appropriate page
if ($auth->isLoggedIn()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}