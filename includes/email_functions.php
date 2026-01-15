<?php
// email_functions.php - FIXED PATHS
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * Send password reset verification code email
 */
function sendPasswordResetCode($to_email, $username, $code, $token) {
    // Load email configuration
    require_once __DIR__ . '/email_config.php';
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = SMTP_DEBUG;
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $username);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'DKU Scheduler - Password Reset Code';
        
        // Get the base URL dynamically
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $base_url = $protocol . $host;
        
        $reset_link = $base_url . "/dkuscheduler1/public/verify_code.php?token=" . urlencode($token);
        
        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; }
                    .header { background: #2563eb; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { padding: 30px; background: #f9f9f9; }
                    .code { font-size: 32px; font-weight: bold; color: #2563eb; text-align: center; margin: 20px 0; padding: 15px; background: white; border: 2px dashed #2563eb; border-radius: 5px; }
                    .expiry { color: #666; font-size: 14px; margin-top: 20px; }
                    .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>DKU Scheduler Password Reset</h2>
                    </div>
                    <div class='content'>
                        <p>Hello <strong>{$username}</strong>,</p>
                        
                        <p>You requested to reset your password. Use the verification code below:</p>
                        
                        <div class='code'>{$code}</div>
                        
                        <p>Or click the link below to verify:</p>
                        <p><a href='{$reset_link}' style='background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                            Verify Email Address
                        </a></p>
                        
                        <div class='expiry'>
                            <strong>⚠️ Important:</strong>
                            <ul>
                                <li>This code will expire in 10 minutes</li>
                                <li>If you didn't request this reset, please ignore this email</li>
                                <li>Do not share this code with anyone</li>
                            </ul>
                        </div>
                        
                        <p>Best regards,<br>DKU Scheduler Team</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                        <p>&copy; " . date('Y') . " DKU Scheduler. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Plain text alternative
        $mail->AltBody = "DKU Scheduler Password Reset\n\nHello {$username},\n\nYour verification code is: {$code}\n\nThis code will expire in 10 minutes.\n\nIf you didn't request this reset, please ignore this email.\n\nBest regards,\nDKU Scheduler Team";
        
        return $mail->send();
        
    } catch (Exception $e) {
        // Log error but don't expose to user
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}