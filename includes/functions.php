<?php
/**
 * Helper Functions
 * 
 * This file contains utility functions used throughout the application
 */

// Include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/database.php';

/**
 * Sanitize input data
 * 
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate email address
 * 
 * @param string $email Email address to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Hash password
 * 
 * @param string $password Password to hash
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_HASH_ALGO);
}

/**
 * Verify password
 * 
 * @param string $password Plain password
 * @param string $hash Hashed password
 * @return bool True if password matches hash, false otherwise
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate a random token
 * 
 * @param int $length Length of the token
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Redirect to a URL
 * 
 * @param string $url URL to redirect to
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Get current user from session
 * 
 * @return array|null User data or null if not logged in
 */
function getCurrentUser() {
    if (isset($_SESSION[SESSION_PREFIX . 'user_id'])) {
        $userId = $_SESSION[SESSION_PREFIX . 'user_id'];
        $sql = "SELECT * FROM users WHERE id = ?";
        return executeSingleQuery($sql, [$userId]);
    }
    return null;
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION[SESSION_PREFIX . 'user_id']);
}

/**
 * Check if user has a specific role
 * 
 * @param string|array $role Role(s) to check
 * @return bool True if user has the role, false otherwise
 */
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user = getCurrentUser();
    
    if (is_array($role)) {
        return in_array($user['role'], $role);
    }
    
    return $user['role'] === $role;
}

/**
 * Format date for display
 * 
 * @param string $date Date string
 * @param string $format Date format
 * @return string Formatted date
 */
function formatDate($date, $format = 'Y-m-d') {
    return date($format, strtotime($date));
}

/**
 * Get profile data based on user ID and role
 * 
 * @param int $userId User ID
 * @param string $role User role
 * @return array|false Profile data or false on failure
 */
function getUserProfile($userId, $role) {
    $table = '';
    
    switch ($role) {
        case 'admin':
            $table = 'admin_profiles';
            break;
        case 'teacher':
            $table = 'teacher_profiles';
            break;
        case 'student':
            $table = 'student_profiles';
            break;
        default:
            return false;
    }
    
    $sql = "SELECT * FROM $table WHERE user_id = ?";
    return executeSingleQuery($sql, [$userId]);
}

/**
 * Log activity
 * 
 * @param int $userId User ID
 * @param string $action Action performed
 * @param string $details Additional details
 * @return bool True on success, false on failure
 */
function logActivity($userId, $action, $details = '') {
    $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
    $params = [$userId, $action, $details, $_SERVER['REMOTE_ADDR']];
    return executeNonQuery($sql, $params) !== false;
}

/**
 * Display flash message
 * 
 * @param string $type Message type (success, error, warning, info)
 * @param string $message Message text
 * @return void
 */
function setFlashMessage($type, $message) {
    $_SESSION[SESSION_PREFIX . 'flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get flash message and clear it
 * 
 * @return array|null Flash message or null if not set
 */
function getFlashMessage() {
    if (isset($_SESSION[SESSION_PREFIX . 'flash_message'])) {
        $message = $_SESSION[SESSION_PREFIX . 'flash_message'];
        unset($_SESSION[SESSION_PREFIX . 'flash_message']);
        return $message;
    }
    return null;
}

/**
 * Upload file
 * 
 * @param array $file $_FILES array element
 * @param string $destination Destination folder
 * @param array $allowedTypes Allowed file types
 * @param int $maxSize Maximum file size in bytes
 * @return string|false Filename on success, false on failure
 */
function uploadFile($file, $destination, $allowedTypes = [], $maxSize = 0) {
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Check file size
    if ($maxSize > 0 && $file['size'] > $maxSize) {
        return false;
    }
    
    // Check file type
    if (!empty($allowedTypes)) {
        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileType, $allowedTypes)) {
            return false;
        }
    }
    
    // Create destination directory if it doesn't exist
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . basename($file['name']);
    $targetPath = $destination . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $filename;
    }
    
    return false;
}

/**
 * Generate pagination
 * 
 * @param int $totalItems Total number of items
 * @param int $itemsPerPage Items per page
 * @param int $currentPage Current page number
 * @param string $urlPattern URL pattern with :page placeholder
 * @return array Pagination data
 */
function getPagination($totalItems, $itemsPerPage, $currentPage, $urlPattern) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    
    // Ensure current page is valid
    $currentPage = max(1, min($currentPage, $totalPages));
    
    // Calculate pagination links
    $links = [];
    
    if ($totalPages > 1) {
        // Previous page
        if ($currentPage > 1) {
            $links['prev'] = str_replace(':page', $currentPage - 1, $urlPattern);
        }
        
        // Page links
        $links['pages'] = [];
        
        // Determine range of visible pages
        $range = 2;
        $startPage = max(1, $currentPage - $range);
        $endPage = min($totalPages, $currentPage + $range);
        
        // Add first page if not included in range
        if ($startPage > 1) {
            $links['pages'][1] = str_replace(':page', 1, $urlPattern);
            
            if ($startPage > 2) {
                $links['pages']['ellipsis1'] = '...';
            }
        }
        
        // Add pages in range
        for ($i = $startPage; $i <= $endPage; $i++) {
            $links['pages'][$i] = str_replace(':page', $i, $urlPattern);
        }
        
        // Add last page if not included in range
        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                $links['pages']['ellipsis2'] = '...';
            }
            
            $links['pages'][$totalPages] = str_replace(':page', $totalPages, $urlPattern);
        }
        
        // Next page
        if ($currentPage < $totalPages) {
            $links['next'] = str_replace(':page', $currentPage + 1, $urlPattern);
        }
    }
    
    return [
        'currentPage' => $currentPage,
        'totalPages' => $totalPages,
        'itemsPerPage' => $itemsPerPage,
        'totalItems' => $totalItems,
        'links' => $links
    ];
}

/**
 * Get the last database query error
 * 
 * @return string The last error message
 */
function getLastQueryError() {
    global $conn;
    return $conn->error ?? 'Unknown error';
} 