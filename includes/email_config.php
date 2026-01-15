<?php
// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com'); // Or your SMTP server
define('SMTP_PORT', 587); // 587 for TLS, 465 for SSL
define('SMTP_USERNAME', 'abokermuhamed72@gmail.com'); // Your email
define('SMTP_PASSWORD', 'kkywfpisrshydtks'); // Use app password for Gmail
define('SMTP_FROM_EMAIL', 'noreply@dku.edu');
define('SMTP_FROM_NAME', 'DKU Scheduler');
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
define('SMTP_DEBUG', 0); // 0 for production, 2 for debugging

// For Gmail, you need to:
// 1. Enable 2-factor authentication
// 2. Generate an App Password: https://myaccount.google.com/apppasswords
?>