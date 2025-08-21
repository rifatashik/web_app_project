<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session if not already started
if (!isset($_SESSION)) {
    session_start();
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Clear the auth token cookie if it exists
if (isset($_COOKIE['auth_token'])) {
    setcookie('auth_token', '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
redirect('login.php');
?> 