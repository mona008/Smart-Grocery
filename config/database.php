<?php
/**
 * Database connection (PDO)
 * -------------------------
 * Every page that needs the database includes this file and gets a
 * ready-to-use $pdo object. Using PDO with prepared statements everywhere
 * protects against SQL injection.
 *
 * If you get a "connection failed" error, check:
 *   - XAMPP's MySQL service is running (green in the XAMPP control panel)
 *   - DB_NAME below matches the database you created in phpMyAdmin
 *   - DB_USER / DB_PASS match your MySQL login (XAMPP default is root / no password)
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'grocery_planner');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
