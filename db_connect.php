<?php
// ============================================================
//  Greenfield Institute – Course Registration System
//  File   : db_connect.php
//  Layer  : Business Logic Tier (PHP)
//  Desc   : Establishes a secure MySQLi connection.
//           Every other PHP file includes this one.
// ============================================================

// -- Connection credentials ----------------------------------
define('DB_HOST', 'localhost');
define('DB_USER', 'greenfield_user');
define('DB_PASS', 'Greenfield!');   // ← change before going live
define('DB_NAME', 'greenfield_db');
define('DB_PORT', 3306);
// ------------------------------------------------------------

// Create connection using MySQLi (object-oriented style)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    // In production: log the error, never expose details to users
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(503);
    die(json_encode([
        'success' => false,
        'message' => 'Service temporarily unavailable. Please try again later.'
    ]));
}

// Set charset to utf8mb4 for full Unicode support
$conn->set_charset('utf8mb4');

// ============================================================
//  HOW TO CREATE THE DATABASE USER (run once in MySQL shell):
//
//  CREATE USER 'greenfield_user'@'localhost'
//      IDENTIFIED BY 'Greenfield!';
//
//  GRANT SELECT, INSERT, UPDATE, DELETE
//      ON greenfield_db.*
//      TO 'greenfield_user'@'localhost';
//
//  FLUSH PRIVILEGES;
//
//  This grants only the permissions the app actually needs
//  (no DROP, CREATE, or ALTER) – principle of least privilege.
// ============================================================
?>