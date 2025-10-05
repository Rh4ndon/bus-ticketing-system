<?php
// config.php - Configuration file for Bus Reservation System

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u491086721_bus_ticket_db');
define('DB_USER', 'u491086721_driver');
define('DB_PASS', 'BusTicketing_2025'); 

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Admin Credentials (HARDCODED)
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'ADMIN!'); // Change this to a strong password

// Security Settings
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('PASSWORD_SALT', 'BusReservationSalt2024'); // Change this to a random string

// System Settings
define('SITE_NAME', 'Bus Reservation System');
define('SITE_URL', 'http://localhost/bus_ticket_system/admin/');

// Error Reporting (Set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Manila');




// Database Connection Class
class Database {
    private $host = DB_HOST;
    private $dbname = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function closeConnection() {
        $this->pdo = null;
    }
}

// Utility Functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateDriverCode() {
    return 'DRV' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function generateBookingReference() {
    return 'BUS' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

function hashPassword($password) {
    return password_hash($password . PASSWORD_SALT, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password . PASSWORD_SALT, $hash);
}

function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

function redirectTo($url) {
    header("Location: $url");
    exit();
}

function showAlert($message, $type = 'info') {
    return "<div class='alert alert-{$type}'>{$message}</div>";
}

function cleanupBusStatuses() {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Find buses that are marked as 'on_trip' but have no active trips
    $stmt = $conn->prepare("
        UPDATE buses b
        SET b.status = 'available'
        WHERE b.status = 'on_trip' 
        AND NOT EXISTS (
            SELECT 1 FROM active_trips at 
            WHERE at.bus_id = b.bus_id 
            AND at.status NOT IN ('completed', 'cancelled')
        )
    ");
    $stmt->execute();
    
    // Also clean up any buses marked as 'full' that should be 'available'
    $stmt = $conn->prepare("
        UPDATE buses b
        SET b.status = 'available'
        WHERE b.status = 'full' 
        AND NOT EXISTS (
            SELECT 1 FROM active_trips at 
            WHERE at.bus_id = b.bus_id 
            AND at.status NOT IN ('completed', 'cancelled')
        )
    ");
    $stmt->execute();

    function generateBookingReference() {
    return 'BUS' . date('Ymd') . strtoupper(substr(md5(uniqid()), 0, 6));
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateBookingReference() {
    return 'BUS' . date('Ymd') . strtoupper(substr(md5(uniqid()), 0, 6));
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

}

?>