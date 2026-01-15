<?php
// db.php - Corrected version
date_default_timezone_set('Africa/Addis_Ababa'); // Or your timezone

// Your existing DB connection code...
$host = 'localhost';
$dbname = 'dkuscheduler1';
$username = 'root';
$password = ''; // Your password if any

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Fixed typo here
    
    // Set MySQL session timezone to match PHP
    $pdo->exec("SET time_zone = '+03:00'"); // Africa/Addis_Ababa is UTC+3
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>