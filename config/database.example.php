<?php
/**
 * Database Configuration Template
 * 
 * ⚠️ INSTALLATION INSTRUCTIONS:
 * 1. Copy this file to conf_db.php:
 *    cp config/database.example.php config/conf_db.php
 * 
 * 2. Edit config/conf_db.php with your actual credentials
 * 
 * 3. DO NOT commit conf_db.php to Git (protected by .gitignore)
 */

// Database connection parameters
define('DB_HOST', 'localhost');
define('DB_USER', 'your_database_username');  // Change this
define('DB_PASS', 'your_database_password');  // Change this
define('DB_NAME', 'your_database_name');      // Change this

// For MAMP on Mac: uncomment this line
// define('DB_PORT', '8889');

/**
 * Get database connection
 */
function getDBConnection() {
    $port = defined('DB_PORT') ? DB_PORT : 3306;
    
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, $port);
    
    if ($conn->connect_error) {
        die("❌ Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

/**
 * Close database connection
 */
function closeDBConnection($conn) {
    if ($conn && $conn->ping()) {
        $conn->close();
    }
}

// Global connection
$conn = getDBConnection();
?>
