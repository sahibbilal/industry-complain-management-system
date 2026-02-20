<?php
/**
 * Database Configuration and Connection
 * ICMS - Industry Complaint Management System
 */

// Database configuration
define('DB_HOST', 'db');
define('DB_NAME', 'db');
define('DB_USER', 'db');
define('DB_PASS', 'db');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get database connection using PDO
 * @return PDO Database connection object
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please contact the administrator.");
        }
    }
    
    return $pdo;
}

// Initialize connection
$db = getDBConnection();
?>
