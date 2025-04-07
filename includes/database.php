<?php
/**
 * Database Connection
 * 
 * This file handles the database connection for the School Management System
 */

// Include configuration file
require_once __DIR__ . '/../config/config.php';

/**
 * Get database connection
 * 
 * @return PDO Database connection object
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            // Create PDO connection
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error and exit
            error_log("Database Connection Error: " . $e->getMessage());
            exit("Database Connection Failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Execute a query and return results
 * 
 * @param string $sql SQL query
 * @param array $params Parameters for prepared statement
 * @return array|false Query results or false on failure
 */
function executeQuery($sql, $params = []) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Query Execution Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Execute a query and return single row
 * 
 * @param string $sql SQL query
 * @param array $params Parameters for prepared statement
 * @return array|false Single result row or false on failure
 */
function executeSingleQuery($sql, $params = []) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Query Execution Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Execute a query without returning results (INSERT, UPDATE, DELETE)
 * 
 * @param string $sql SQL query
 * @param array $params Parameters for prepared statement
 * @return int|false Number of affected rows or false on failure
 */
function executeNonQuery($sql, $params = []) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Query Execution Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get the last inserted ID
 * 
 * @return string|false Last inserted ID or false on failure
 */
function getLastInsertId() {
    try {
        $pdo = getDBConnection();
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error getting last insert ID: " . $e->getMessage());
        return false;
    }
}

/**
 * Start a transaction
 * 
 * @return bool True on success, false on failure
 */
function startTransaction() {
    try {
        $pdo = getDBConnection();
        return $pdo->beginTransaction();
    } catch (PDOException $e) {
        error_log("Error starting transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Commit a transaction
 * 
 * @return bool True on success, false on failure
 */
function commitTransaction() {
    try {
        $pdo = getDBConnection();
        return $pdo->commit();
    } catch (PDOException $e) {
        error_log("Error committing transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Rollback a transaction
 * 
 * @return bool True on success, false on failure
 */
function rollbackTransaction() {
    try {
        $pdo = getDBConnection();
        return $pdo->rollBack();
    } catch (PDOException $e) {
        error_log("Error rolling back transaction: " . $e->getMessage());
        return false;
    }
} 