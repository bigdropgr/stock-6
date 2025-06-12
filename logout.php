<?php
/**
 * Logout Page
 * 
 * Handles user logout
 */

// Start session first
session_start();

// Include required files
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/functions.php';

// Initialize authentication
$auth = new Auth();

// Log the user out (this should destroy the session)
$auth->logout();

// Redirect to login page
header('Location: login.php');
exit;
?>