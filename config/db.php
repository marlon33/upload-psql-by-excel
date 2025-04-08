<?php
/**
 * Database connection configuration file
 * Contains PostgreSQL connection parameters
 */

// Load environment variables from .env file
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// PostgreSQL connection parameters from environment variables
$host = $_ENV['DB_HOST'];
$port = $_ENV['DB_PORT'];
$dbname = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$password = $_ENV['DB_PASSWORD'];

/**
 * Get database connection
 * @return PDO|null PDO connection object or null on failure
 */
function getDbConnection() {
    global $host, $port, $dbname, $user, $password;
    
    try {
        // Create a new PDO instance for PostgreSQL connection
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $conn = new PDO($dsn, $user, $password);
        
        // Set error mode to exception for better error handling
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        return $conn;
    } catch (PDOException $e) {
        // Log connection error
        error_log('Database Connection Error: ' . $e->getMessage());
        return null;
    }
}