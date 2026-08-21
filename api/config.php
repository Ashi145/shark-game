<?php
// ============================================
// DATABASE CONFIGURATION
// Update these with your cPanel MySQL credentials
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'sharktank_db');
define('DB_USER', 'sharktank_user');  // Create this user in cPanel → MySQL Databases
define('DB_PASS', 'YOUR_PASSWORD_HERE'); // Set a strong password

// CORS - allow game to call API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    return $pdo;
}

// Helper: JSON response
function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Helper: Get JSON input
function getInput() {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?: [];
}

// Helper: Get client IP
function getClientIP() {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}

// Clean stale sessions (called periodically)
function cleanStaleSessions($db) {
    $db->exec("UPDATE sessions SET is_active = 0 WHERE last_ping < DATE_SUB(NOW(), INTERVAL 60 SECOND)");
}
