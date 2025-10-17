<?php
require __DIR__ . '/includes/db.php';


// Set the new plain-text password here
$newPassword = 'admin123'; // Change this to your desired admin password

// Hash the password using PHP's password_hash
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Update the admin in the database (assuming user_id = 1)
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = 1");
$stmt->execute([$hashedPassword]);

echo "Admin password updated successfully!";
