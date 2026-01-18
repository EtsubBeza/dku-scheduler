<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$error = "";
$success = "";
$user_email = isset($_SESSION['reset_user_email']) ? $_SESSION['reset_user_email'] : "";

// If token is in URL, use it
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT prt.*, u.email 
            FROM password_reset_tokens prt 
            JOIN users u ON prt.user_id = u.user_id 
            WHERE prt.token = ? AND prt.is_used = FALSE AND prt.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($token_data) {
            $_SESSION['reset_user_email'] = $token_data['email'];
            $user_email = $token_data['email'];
        }
    } catch (PDOException $e) {
        error_log("Token verification error: " . $e->getMessage());
    }
}

// Check if we have user email from session
if (!$user_email) {
    $error = "No reset session found. Please start from forgot password.";
    header("Location: forgot_password.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get code from individual digit inputs
    $entered_code = "";
    for ($i = 1; $i <= 6; $i++) {
        $digit = trim($_POST["digit$i"] ?? '');
        $entered_code .= $digit;
    }
    
    try {
        // Check database for valid token/code
        $stmt = $pdo->prepare("
            SELECT prt.* 
            FROM password_reset_tokens prt 
            JOIN users u ON prt.user_id = u.user_id 
            WHERE u.email = ? 
            AND prt.code = ? 
            AND prt.is_used = FALSE 
            AND prt.expires_at > NOW()
            ORDER BY prt.created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_email, $entered_code]);
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($token_data) {
            // Mark token as verified
            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET verified = TRUE WHERE token = ?");
            $stmt->execute([$token_data['token']]);
            
            // Store verified token in session
            $_SESSION['verified_token'] = $token_data['token'];
            
            // Redirect to reset password page
            header("Location: reset_password.php");
            exit();
        } else {
            $error = "Invalid or expired verification code.";
        }
    } catch (PDOException $e) {
        error_log("Database error in verify_code: " . $e->getMessage());
        $error = "An error occurred. Please try again.";
    }
}

// Calculate time remaining
$time_remaining = "10:00";
try {
    if ($user_email) {
        $stmt = $pdo->prepare("
            SELECT expires_at 
            FROM password_reset_tokens prt 
            JOIN users u ON prt.user_id = u.user_id 
            WHERE u.email = ? 
            AND prt.is_used = FALSE 
            ORDER BY prt.created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_email]);
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($token_data && $token_data['expires_at']) {
            $expires = strtotime($token_data['expires_at']);
            $now = time();
            $remaining = $expires - $now;
            
            if ($remaining > 0) {
                $minutes = floor($remaining / 60);
                $seconds = $remaining % 60;
                $time_remaining = sprintf("%02d:%02d", $minutes, $seconds);
            } else {
                $time_remaining = "00:00";
                if (!$error) $error = "Code has expired. Please request a new one.";
            }
        }
    }
} catch (PDOException $e) {
    error_log("Time calculation error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code - DKU Scheduler</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Campus Image Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('assets/images/dku2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            z-index: 1;
            animation: subtleZoom 20s ease-in-out infinite alternate;
        }
        
        @keyframes subtleZoom {
            0% {
                transform: scale(1);
            }
            100% {
                transform: scale(1.05);
            }
        }
        
        /* Overlay for better readability */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.85) 0%, rgba(30, 41, 59, 0.9) 100%);
            z-index: 2;
        }
        
        /* Animated gradient overlay */
        .gradient-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, 
                rgba(37, 99, 235, 0.3) 0%, 
                rgba(30, 58, 138, 0.4) 25%, 
                rgba(147, 51, 234, 0.3) 50%, 
                rgba(16, 185, 129, 0.3) 75%, 
                rgba(37, 99, 235, 0.3) 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: 3;
            opacity: 0.5;
        }
        
        @keyframes gradientShift { 
            0% { background-position: 0% 50%; } 
            50% { background-position: 100% 50%; } 
            100% { background-position: 0% 50%; } 
        }
        
        /* Floating particles */
        .particle {
            position: fixed;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(5px);
            z-index: 4;
            animation: floatParticle 20s infinite linear;
        }
        
        .particle:nth-child(1) { 
            width: 150px; 
            height: 150px; 
            left: 10%; 
            top: 20%; 
            animation-duration: 25s; 
            background: radial-gradient(circle, rgba(37, 99, 235, 0.1) 0%, transparent 70%);
        }
        
        .particle:nth-child(2) { 
            width: 200px; 
            height: 200px; 
            right: 15%; 
            bottom: 25%; 
            animation-duration: 30s; 
            background: radial-gradient(circle, rgba(147, 51, 234, 0.1) 0%, transparent 70%);
            animation-delay: 5s;
        }
        
        .particle:nth-child(3) { 
            width: 100px; 
            height: 100px; 
            left: 70%; 
            top: 15%; 
            animation-duration: 20s; 
            background: radial-gradient(circle, rgba(16, 185, 129, 0.1) 0%, transparent 70%);
            animation-delay: 10s;
        }
        
        @keyframes floatParticle { 
            0% { transform: translate(0, 0) rotate(0deg) scale(1); } 
            25% { transform: translate(50px, -80px) rotate(90deg) scale(1.1); } 
            50% { transform: translate(0, -150px) rotate(180deg) scale(1); } 
            75% { transform: translate(-50px, -80px) rotate(270deg) scale(0.9); } 
            100% { transform: translate(0, 0) rotate(360deg) scale(1); } 
        }
        
        /* Main container */
        .main-container {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        /* Verify container */
        .verify-container {
            width: 100%;
            max-width: 520px;
            position: relative;
        }
        
        /* Verify box with glass effect */
        .verify-box { 
            width: 100%;
            padding: 40px 35px; 
            border-radius: 24px; 
            background: rgba(255, 255, 255, 0.08); 
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.05);
            text-align: center; 
            animation: slideUp 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        
        /* Glowing border effect */
        .verify-box::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, 
                rgba(37, 99, 235, 0.6), 
                rgba(147, 51, 234, 0.6), 
                rgba(16, 185, 129, 0.6), 
                rgba(37, 99, 235, 0.6));
            background-size: 400% 400%;
            border-radius: 26px;
            z-index: -1;
            animation: borderGlow 3s ease infinite;
            opacity: 0.7;
        }
        
        @keyframes borderGlow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        @keyframes slideUp { 
            from { 
                opacity: 0; 
                transform: translateY(60px) scale(0.95); 
            } 
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            } 
        }
        
        /* Logo styling */
        .logo-container {
            margin-bottom: 25px;
            position: relative;
        }
        
        .dku-logo-img {
            width: 90px;
            height: 90px;
            margin: 0 auto 15px;
            border-radius: 50%;
            object-fit: cover;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            box-shadow: 
                0 8px 25px rgba(0, 0, 0, 0.3),
                inset 0 2px 4px rgba(255, 255, 255, 0.2);
            animation: logoFloat 6s ease-in-out infinite;
            border: 2px solid rgba(255, 255, 255, 0.15);
        }
        
        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .logo-text {
            font-size: 18px;
            color: #fff;
            font-weight: 600;
            letter-spacing: 1px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .logo-subtext {
            font-size: 14px;
            color: #cbd5e1;
            margin-top: 5px;
            font-weight: 400;
        }
        
        /* Title styling */
        .verify-title {
            margin-bottom: 25px; 
            color: #fff; 
            font-size: 28px;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            letter-spacing: 0.5px;
            position: relative;
            display: inline-block;
        }
        
        .verify-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(to right, #60a5fa, #34d399);
            border-radius: 2px;
        }
        
        /* Email display box */
        .email-display {
            background: rgba(255, 255, 255, 0.07);
            padding: 18px 25px;
            border-radius: 14px;
            margin: 25px 0;
            border: 1px solid rgba(255, 255, 255, 0.15);
            animation: fadeIn 0.8s ease;
            text-align: center;
        }
        
        .email-display .email-label {
            color: #cbd5e1;
            font-size: 0.95rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .email-display .email-address {
            color: #fff;
            font-weight: 600;
            font-size: 1.05rem;
            word-break: break-all;
        }
        
        /* Instructions box */
        .instructions-box {
            background: rgba(255, 255, 255, 0.07);
            padding: 22px 25px;
            border-radius: 14px;
            margin-bottom: 25px;
            text-align: left;
            border-left: 4px solid #60a5fa;
            animation: fadeIn 0.8s ease;
        }
        
        .instructions-box h4 {
            color: #fff;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .instructions-box h4::before {
            content: '📝';
            font-size: 18px;
        }
        
        .instructions-box ul {
            color: #cbd5e1;
            padding-left: 20px;
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        
        .instructions-box li {
            margin: 8px 0;
            position: relative;
            padding-left: 5px;
        }
        
        .instructions-box li::marker {
            color: #60a5fa;
        }
        
        /* Timer styling */
        .timer-box {
            background: rgba(255, 255, 255, 0.07);
            padding: 20px;
            border-radius: 14px;
            margin: 25px 0;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.15);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { 
                border-color: rgba(16, 185, 129, 0.3);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.2);
            }
            50% { 
                border-color: rgba(16, 185, 129, 0.6);
                box-shadow: 0 0 0 5px rgba(16, 185, 129, 0.1);
            }
        }
        
        .timer-box.expired {
            animation: none;
            border-color: rgba(248, 113, 113, 0.3);
            background: rgba(248, 113, 113, 0.05);
        }
        
        .timer-box.expired .timer-text {
            color: #f87171;
        }
        
        .timer-icon {
            font-size: 20px;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        .timer-text {
            color: #34d399;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 2px;
            text-shadow: 0 0 10px rgba(52, 211, 153, 0.3);
        }
        
        /* Digit input styling */
        .digit-inputs {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 30px 0;
            position: relative;
        }
        
        .digit-input {
            width: 55px;
            height: 70px;
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.09);
            color: #fff;
            transition: all 0.3s ease;
            outline: none;
            font-family: monospace;
            caret-color: transparent;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .digit-input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        
        .digit-input:focus {
            border-color: #60a5fa;
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 
                0 0 0 3px rgba(96, 165, 250, 0.2),
                0 8px 20px rgba(0, 0, 0, 0.2);
            transform: translateY(-3px);
        }
        
        .digit-input.filled {
            border-color: #34d399;
            background: rgba(52, 211, 153, 0.1);
            box-shadow: 0 0 15px rgba(52, 211, 153, 0.2);
        }
        
        .digit-input.error {
            border-color: #f87171;
            background: rgba(248, 113, 113, 0.1);
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        
        /* Auto-submit message */
        .auto-submit {
            color: #60a5fa;
            font-size: 0.9rem;
            margin: 15px 0 25px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .auto-submit::before {
            content: '⚡';
            font-size: 16px;
        }
        
        /* Button styling */
        .btn-submit { 
            width: 100%; 
            padding: 17px; 
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: #fff; 
            font-weight: 600; 
            font-size: 1rem;
            letter-spacing: 0.5px;
            border: none; 
            border-radius: 14px; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            margin-top: 10px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-submit::before {
            content: '✓';
            font-size: 18px;
        }
        
        .btn-submit::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.7s;
        }
        
        .btn-submit:hover { 
            background: linear-gradient(135deg, #0da271 0%, #10b981 100%);
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(16, 185, 129, 0.5);
        }
        
        .btn-submit:hover::after {
            left: 100%;
        }
        
        .btn-submit:active {
            transform: translateY(-1px);
        }
        
        .btn-submit:disabled {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-submit:disabled::before {
            content: '⌛';
        }
        
        /* Action buttons container */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        /* Resend button */
        .btn-resend { 
            flex: 1;
            padding: 16px; 
            background: rgba(255, 255, 255, 0.1);
            color: #cbd5e1; 
            font-weight: 600; 
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            border: 1px solid rgba(255, 255, 255, 0.15); 
            border-radius: 14px; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-resend::before {
            content: '↻';
            font-size: 18px;
        }
        
        .btn-resend:hover { 
            background: rgba(37, 99, 235, 0.2);
            color: #60a5fa;
            transform: translateY(-2px);
            border-color: rgba(37, 99, 235, 0.3);
        }
        
        .btn-resend.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* Back button */
        .btn-back { 
            flex: 1;
            padding: 16px; 
            background: rgba(255, 255, 255, 0.1);
            color: #cbd5e1; 
            font-weight: 600; 
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            border: 1px solid rgba(255, 255, 255, 0.15); 
            border-radius: 14px; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            margin-top: 0;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-back::before {
            content: '←';
            font-size: 18px;
        }
        
        .btn-back:hover { 
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.25);
        }
        
        /* Message styling */
        .success-message { 
            color: #34d399; 
            margin-bottom: 25px; 
            font-size: 0.95rem; 
            line-height: 1.6;
            background: rgba(52, 211, 153, 0.1);
            padding: 20px;
            border-radius: 14px;
            border-left: 4px solid #34d399;
            text-align: center;
            animation: fadeIn 0.8s ease;
            backdrop-filter: blur(5px);
        }
        
        .error-message { 
            color: #f87171; 
            margin-bottom: 25px; 
            font-size: 0.95rem; 
            line-height: 1.6;
            background: rgba(248, 113, 113, 0.1);
            padding: 20px;
            border-radius: 14px;
            border-left: 4px solid #f87171;
            text-align: center;
            animation: shake 0.5s ease;
            backdrop-filter: blur(5px);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Process steps */
        .process-steps {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            position: relative;
        }
        
        .process-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: rgba(255, 255, 255, 0.2);
            z-index: 1;
        }
        
        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
            color: #fff;
            border: 2px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .step.active .step-circle {
            background: #2563eb;
            border-color: #60a5fa;
            box-shadow: 0 0 15px rgba(37, 99, 235, 0.5);
        }
        
        .step-label {
            font-size: 0.8rem;
            color: #cbd5e1;
        }
        
        .step.active .step-label {
            color: #fff;
            font-weight: 500;
        }
        
        /* Footer text */
        .verify-footer {
            margin-top: 30px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85rem;
            text-align: center;
            line-height: 1.5;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Ripple effect */
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: scale(0);
            animation: rippleEffect 0.6s linear;
            pointer-events: none;
        }
        
        @keyframes rippleEffect {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .verify-box {
                padding: 35px 25px;
            }
            
            .dku-logo-img {
                width: 80px;
                height: 80px;
            }
            
            .verify-title {
                font-size: 24px;
            }
            
            .digit-inputs {
                gap: 10px;
            }
            
            .digit-input {
                width: 50px;
                height: 65px;
                font-size: 28px;
            }
            
            .process-steps {
                flex-direction: column;
                gap: 20px;
                align-items: center;
            }
            
            .process-steps::before {
                display: none;
            }
            
            .step {
                display: flex;
                align-items: center;
                gap: 15px;
                width: 100%;
                max-width: 250px;
            }
            
            .step-circle {
                margin: 0;
                flex-shrink: 0;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            .verify-box {
                padding: 30px 20px;
                border-radius: 20px;
            }
            
            .dku-logo-img {
                width: 70px;
                height: 70px;
            }
            
            .verify-title {
                font-size: 22px;
            }
            
            .digit-inputs {
                gap: 8px;
            }
            
            .digit-input {
                width: 45px;
                height: 60px;
                font-size: 26px;
            }
            
            .btn-submit, .btn-resend, .btn-back {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Background elements -->
    <div class="gradient-overlay"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>

    <div class="main-container">
        <div class="verify-container">
            <div class="verify-box">
                <div class="logo-container">
                    <img src="assets/images/dku logo.jpg" alt="Debark University Logo" class="dku-logo-img">
                    <div class="logo-text">DEBARK UNIVERSITY</div>
                    <div class="logo-subtext">Verification Code</div>
                </div>
                
                <h2 class="verify-title">Verify Your Code</h2>
                
                <!-- Process steps -->
                <div class="process-steps">
                    <div class="step">
                        <div class="step-circle">1</div>
                        <div class="step-label">Enter Email</div>
                    </div>
                    <div class="step active">
                        <div class="step-circle">2</div>
                        <div class="step-label">Verify Code</div>
                    </div>
                    <div class="step">
                        <div class="step-circle">3</div>
                        <div class="step-label">New Password</div>
                    </div>
                    <div class="step">
                        <div class="step-circle">4</div>
                        <div class="step-label">Complete</div>
                    </div>
                </div>
                
                <?php if ($user_email): ?>
                    <div class="email-display">
                        <div class="email-label">Code sent to:</div>
                        <div class="email-address"><?php echo htmlspecialchars($user_email); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="instructions-box">
                    <h4>Verification Process</h4>
                    <ul>
                        <li>Check your email for the 6-digit verification code</li>
                        <li>Enter each digit in the boxes below</li>
                        <li>The form will auto-advance between boxes</li>
                        <li>Will auto-submit when all digits are entered</li>
                        <li>Verification code expires in 10 minutes</li>
                    </ul>
                </div>
                
                <div class="timer-box <?php echo ($time_remaining === '00:00') ? 'expired' : ''; ?>">
                    <span class="timer-icon">⏰</span>
                    <span class="timer-text" id="timerDisplay">
                        Time remaining: <?php echo $time_remaining; ?>
                    </span>
                </div>
                
                <form method="POST" action="" id="verifyForm">
                    <div class="digit-inputs">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <input type="text" 
                                   name="digit<?php echo $i; ?>" 
                                   class="digit-input" 
                                   maxlength="1" 
                                   pattern="[0-9]" 
                                   inputmode="numeric"
                                   autocomplete="off"
                                   data-index="<?php echo $i; ?>"
                                   <?php if ($i == 1) echo 'autofocus'; ?>>
                        <?php endfor; ?>
                    </div>
                    
                    <!-- Hidden field for debugging -->
                    <input type="hidden" name="full_code" id="fullCode">
                    
                    <div class="auto-submit">
                        Code will auto-submit when complete
                    </div>
                    
                    <button type="submit" class="btn-submit" id="submitBtn" disabled>
                        <span>Verify Code</span>
                    </button>
                </form>
                
                <div class="action-buttons">
                    <button class="btn-resend" id="resendBtn" onclick="resendCode()">
                        <span>Resend Code</span>
                        <span id="resendTimer"></span>
                    </button>
                    <a href="login.php" class="btn-back">
                        <span>Back to Login</span>
                    </a>
                </div>
                
                <div class="verify-footer">
                    Didn't receive code? Check spam folder or try resend<br>
                    Contact DKU IT Support: support@dku.edu | (123) 456-7890
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const digitInputs = document.querySelectorAll('.digit-input');
            const form = document.getElementById('verifyForm');
            const submitBtn = document.getElementById('submitBtn');
            const fullCodeField = document.getElementById('fullCode');
            const resendBtn = document.getElementById('resendBtn');
            const resendTimer = document.getElementById('resendTimer');
            const timerDisplay = document.getElementById('timerDisplay');
            
            let canResend = false;
            let resendCountdown = 60;
            
            // Focus first input
            digitInputs[0].focus();
            
            // Handle digit input
            digitInputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    const value = this.value;
                    
                    // Only allow numbers
                    if (!/^\d?$/.test(value)) {
                        this.value = '';
                        return;
                    }
                    
                    if (value.length === 1) {
                        // Add filled class
                        this.classList.add('filled');
                        this.classList.remove('error');
                        
                        // Move to next input if available
                        if (index < digitInputs.length - 1) {
                            digitInputs[index + 1].focus();
                        }
                        
                        // Check if all digits are filled
                        checkComplete();
                    }
                });
                
                // Handle paste
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text');
                    if (/^\d{6}$/.test(pastedData)) {
                        // Fill all inputs with pasted code
                        for (let i = 0; i < 6; i++) {
                            digitInputs[i].value = pastedData[i];
                            digitInputs[i].classList.add('filled');
                            digitInputs[i].classList.remove('error');
                        }
                        checkComplete();
                        digitInputs[5].focus();
                    }
                });
                
                // Handle backspace
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && this.value === '' && index > 0) {
                        // Move to previous input and clear it
                        digitInputs[index - 1].value = '';
                        digitInputs[index - 1].classList.remove('filled');
                        digitInputs[index - 1].focus();
                        checkComplete();
                    } else if (e.key === 'ArrowLeft' && index > 0) {
                        digitInputs[index - 1].focus();
                        e.preventDefault();
                    } else if (e.key === 'ArrowRight' && index < digitInputs.length - 1) {
                        digitInputs[index + 1].focus();
                        e.preventDefault();
                    }
                });
            });
            
            function checkComplete() {
                let code = '';
                let allFilled = true;
                
                digitInputs.forEach(input => {
                    code += input.value;
                    if (input.value === '') {
                        allFilled = false;
                    }
                });
                
                // Update hidden field
                fullCodeField.value = code;
                
                // Enable/disable submit button
                submitBtn.disabled = !allFilled;
                
                // Auto-submit if all digits are filled
                if (allFilled && code.length === 6) {
                    setTimeout(() => {
                        form.submit();
                    }, 300);
                }
            }
            
            // Update timer display
            function updateMainTimer() {
                const timerText = timerDisplay.textContent;
                const timeMatch = timerText.match(/(\d{2}):(\d{2})/);
                
                if (timeMatch) {
                    let minutes = parseInt(timeMatch[1]);
                    let seconds = parseInt(timeMatch[2]);
                    
                    if (seconds > 0) {
                        seconds--;
                    } else if (minutes > 0) {
                        minutes--;
                        seconds = 59;
                    } else {
                        // Timer expired
                        timerDisplay.textContent = 'Code expired!';
                        document.querySelector('.timer-box').classList.add('expired');
                        return;
                    }
                    
                    const newTime = minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
                    timerDisplay.textContent = 'Time remaining: ' + newTime;
                }
            }
            
            // Start main timer
            const mainTimer = setInterval(updateMainTimer, 1000);
            
            // Resend code countdown
            function updateResendTimer() {
                if (resendCountdown > 0) {
                    resendCountdown--;
                    resendTimer.textContent = `(${resendCountdown}s)`;
                    resendBtn.classList.add('disabled');
                    canResend = false;
                } else {
                    resendTimer.textContent = '';
                    resendBtn.classList.remove('disabled');
                    canResend = true;
                }
            }
            
            // Update resend timer every second
            setInterval(updateResendTimer, 1000);
            
            // Add ripple effect to buttons
            function addRippleEffect(button) {
                button.addEventListener('click', function(e) {
                    let x = e.clientX - e.target.getBoundingClientRect().left;
                    let y = e.clientY - e.target.getBoundingClientRect().top;
                    
                    let ripple = document.createElement('span');
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple');
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            }
            
            addRippleEffect(submitBtn);
            addRippleEffect(resendBtn);
            
            // Add focus effects for inputs
            digitInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-4px)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });
            });
        });
        
        function resendCode() {
            if (!canResend) return;
            
            // Add loading state
            const resendBtn = document.getElementById('resendBtn');
            const originalText = resendBtn.innerHTML;
            resendBtn.innerHTML = '<span>Sending...</span>';
            resendBtn.disabled = true;
            
            // Send request to resend code
            window.location.href = 'forgot_password.php?resend=true';
        }
        
        function clearAllDigits() {
            const digitInputs = document.querySelectorAll('.digit-input');
            digitInputs.forEach(input => {
                input.value = '';
                input.classList.remove('filled', 'error');
            });
            digitInputs[0].focus();
            document.getElementById('submitBtn').disabled = true;
        }
    </script>
</body>
</html>