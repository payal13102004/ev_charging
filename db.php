<?php
// Database configuration - UPDATE THESE CREDENTIALS AS PER YOUR ENVIRONMENT
define('DB_HOST', 'localhost');
define('DB_NAME', 'ev_charging');  // Changed to your database name
define('DB_USER', 'root');     // Change if different in your environment
define('DB_PASS', '');         // Change if you have a password

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Create PDO instance
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Session configuration
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// CSRF token generation and validation
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Input sanitization
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Check availability function with existence check
if (!function_exists('checkAvailability')) {
    function checkAvailability($station_id, $date, $start_time, $duration_hours) {
        global $pdo;
        
        // Calculate end time
        $start_datetime = "$date $start_time";
        $end_datetime = date('Y-m-d H:i:s', strtotime("$start_datetime + $duration_hours hours"));
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as booked_slots 
            FROM bookings 
            WHERE station_id = ? 
            AND status NOT IN ('cancelled') 
            AND (
                (start_time <= ? AND DATE_ADD(start_time, INTERVAL duration_hours HOUR) > ?) OR
                (start_time < ? AND DATE_ADD(start_time, INTERVAL duration_hours HOUR) >= ?) OR
                (start_time >= ? AND DATE_ADD(start_time, INTERVAL duration_hours HOUR) <= ?)
            )
        ");
        
        $stmt->execute([$station_id, $start_datetime, $start_datetime, $end_datetime, $end_datetime, $start_datetime, $end_datetime]);
        $result = $stmt->fetch();
        
        // Get total slots for the station
        $stmt = $pdo->prepare("SELECT total_slots FROM stations WHERE id = ?");
        $stmt->execute([$station_id]);
        $station = $stmt->fetch();
        
        return $station['total_slots'] - $result['booked_slots'];
    }
}
?>