<?php
// Database connection file for DKU Scheduler

$host = "localhost";       // use localhost
$dbname = "dkuscheduler1"; // our database name
$username = "root";        // your MySQL username (default XAMPP is root)
$password = "";            // your MySQL password (default XAMPP is empty)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Set error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
