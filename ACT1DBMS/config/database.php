<?php
/**
 * Database Configuration and Connection
 * File: config/database.php
 */

// Database configuration
$config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'db' => 'simple_norm'
];

// Global PDO connection variable
$pdo = null;

/**
 * Initialize database connection and create tables
 * @return PDO|null Returns PDO connection or null on failure
 */
function initDatabase() {
    global $config, $pdo;
    
    try {
        // Create connection
        $pdo = new PDO("mysql:host={$config['host']}", $config['user'], $config['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$config['db']}");
        $pdo->exec("USE {$config['db']}");
        
        // Create table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS data_norm (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_data TEXT,
            normalized_data TEXT,
            method VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get database connection
 * @return PDO|null
 */
function getConnection() {
    global $pdo;
    
    if ($pdo === null) {
        return initDatabase();
    }
    
    return $pdo;
}
?>