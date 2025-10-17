<?php
// Replace 'admin123' with the password you want for the admin
$plainPassword = 'admin123';
$hashed = password_hash($plainPassword, PASSWORD_DEFAULT);

echo "Hashed password for admin: " . $hashed;
