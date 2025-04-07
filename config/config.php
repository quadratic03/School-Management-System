<?php
/**
 * Configuration file for School Management System
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_management');

// Application Configuration
define('APP_NAME', 'School Management System');
define('APP_URL', 'http://localhost/SchoolManagementSystem');
define('ADMIN_EMAIL', 'admin@gmail.com');

// Session Configuration
define('SESSION_PREFIX', 'sms_');
define('SESSION_LIFETIME', 3600); // 1 hour

// File Upload Configuration
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,pdf,doc,docx');

// Authentication Settings
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_RESET_EXPIRY', 86400); // 24 hours

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Time Zone
date_default_timezone_set('UTC'); 