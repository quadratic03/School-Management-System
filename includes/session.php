<?php
/**
 * Session Management
 * 
 * This file handles session initialization and management
 */

// Include configuration file
require_once __DIR__ . '/../config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set session parameters
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_trans_sid', 0);
    ini_set('session.cookie_httponly', 1);
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    // Set session name
    session_name(SESSION_PREFIX . 'session');
    
    // Set session lifetime
    session_set_cookie_params(SESSION_LIFETIME);
    
    // Start session
    session_start();
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        regenerateSession();
    } else {
        // Regenerate session ID every 30 minutes
        $regenerationTime = 1800; // 30 minutes
        
        if ($_SESSION['last_regeneration'] < (time() - $regenerationTime)) {
            regenerateSession();
        }
    }
}

/**
 * Regenerate session ID
 * 
 * @return void
 */
function regenerateSession() {
    // Update regeneration time
    $_SESSION['last_regeneration'] = time();
    
    // Regenerate session ID
    session_regenerate_id(true);
}

/**
 * Create a new user session
 * 
 * @param array $user User data
 * @return void
 */
function createUserSession($user) {
    // Store user data in session
    $_SESSION[SESSION_PREFIX . 'user_id'] = $user['id'];
    $_SESSION[SESSION_PREFIX . 'username'] = $user['username'];
    $_SESSION[SESSION_PREFIX . 'role'] = $user['role'];
    $_SESSION[SESSION_PREFIX . 'login_time'] = time();
    
    // Update last login time in database
    $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
    executeNonQuery($sql, [$user['id']]);
    
    // Log activity
    logActivity($user['id'], 'login', 'User logged in');
    
    // Regenerate session ID
    regenerateSession();
}

/**
 * Destroy user session
 * 
 * @return void
 */
function destroyUserSession() {
    // Log activity if user is logged in
    if (isset($_SESSION[SESSION_PREFIX . 'user_id'])) {
        $userId = $_SESSION[SESSION_PREFIX . 'user_id'];
        logActivity($userId, 'logout', 'User logged out');
        
        // Delete remember token if exists
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $sql = "DELETE FROM user_tokens WHERE token = ?";
            executeNonQuery($sql, [$token]);
            
            // Delete cookie
            setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        }
    }
    
    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Authenticate user
 * 
 * @param string $username Username
 * @param string $password Password
 * @return array|false User data on success, false on failure
 */
function authenticateUser($username, $password) {
    // Sanitize input
    $username = sanitize($username);
    
    // Get user from database
    $sql = "SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'";
    $user = executeSingleQuery($sql, [$username, $username]);
    
    // Verify user and password
    if ($user && verifyPassword($password, $user['password'])) {
        return $user;
    }
    
    return false;
}

/**
 * Authenticate user with role
 * 
 * @param string $username Username
 * @param string $password Password
 * @param string $role User role
 * @return array|false User data on success, false on failure
 */
function authenticateUserWithRole($username, $password, $role) {
    // Sanitize input
    $username = sanitize($username);
    $role = sanitize($role);
    
    // Get user from database
    $sql = "SELECT * FROM users WHERE (username = ? OR email = ?) AND role = ? AND status = 'active'";
    $user = executeSingleQuery($sql, [$username, $username, $role]);
    
    // Verify user and password
    if ($user && verifyPassword($password, $user['password'])) {
        return $user;
    }
    
    return false;
}

/**
 * Require authentication
 * 
 * Redirects to login page if user is not logged in
 * 
 * @param string|array $role Required role(s), null for any authenticated user
 * @return void
 */
function requireAuth($role = null) {
    // Check if user is logged in
    if (!isLoggedIn()) {
        // Store requested URL for redirect after login
        $_SESSION[SESSION_PREFIX . 'redirect_url'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to login page
        redirect(APP_URL . '/login.php');
    }
    
    // Check role if specified
    if ($role !== null && !hasRole($role)) {
        // Redirect to unauthorized page
        redirect(APP_URL . '/unauthorized.php');
    }
}

/**
 * Get redirect URL after login
 * 
 * @return string Redirect URL or default URL
 */
function getRedirectUrl() {
    if (isset($_SESSION[SESSION_PREFIX . 'redirect_url'])) {
        $redirectUrl = $_SESSION[SESSION_PREFIX . 'redirect_url'];
        unset($_SESSION[SESSION_PREFIX . 'redirect_url']);
        return $redirectUrl;
    }
    
    // Default redirect based on user role
    $role = $_SESSION[SESSION_PREFIX . 'role'];
    
    switch ($role) {
        case 'admin':
            return APP_URL . '/modules/admin/dashboard.php';
        case 'teacher':
            return APP_URL . '/modules/teacher/dashboard.php';
        case 'student':
            return APP_URL . '/modules/student/dashboard.php';
        default:
            return APP_URL . '/index.php';
    }
}

// Remember Me functionality will be initialized in login.php after functions are available 