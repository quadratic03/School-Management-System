<?php
/**
 * Logout Page
 */

// Start session
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Delete remember me token if exists
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $sql = "DELETE FROM user_tokens WHERE token = ?";
        executeNonQuery($sql, [$token]);
        
        // Delete cookie
        setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    }
    
    // Destroy user session
    destroyUserSession();
}

// Set flash message
setFlashMessage('success', 'You have been successfully logged out');

// Redirect to login page
redirect(APP_URL . '/login.php'); 