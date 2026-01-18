<?php
// reset_password.php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Initialize variables
$error = "";
$success = "";
$valid_token = false;
$user_email = "";
$user_data = [];

// Check if we have a verified token in session
if (!isset($_SESSION['verified_token'])) {
    $error = "No valid reset session found. Please start the password reset process again.";
} else {
    $token = $_SESSION['verified_token'];
    
    try {
        // Verify token is still valid and not used
        $stmt = $pdo->prepare("
            SELECT prt.*, u.user_id, u.email, u.username 
            FROM password_reset_tokens prt 
            JOIN users u ON prt.user_id = u.user_id 
            WHERE prt.token = ? 
            AND prt.verified = TRUE 
            AND prt.is_used = FALSE 
            AND prt.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($token_data) {
            $valid_token = true;
            $user_email = $token_data['email'];
            $user_data = [
                'user_id' => $token_data['user_id'],
                'email' => $token_data['email'],
                'username' => $token_data['username']
            ];
        } else {
            $error = "Reset session has expired or is invalid. Please start over.";
            // Clear session to prevent reuse
            unset($_SESSION['verified_token']);
        }
    } catch (PDOException $e) {
        error_log("Token verification error in reset_password: " . $e->getMessage());
        $error = "An error occurred while verifying your session.";
    }
}

// Handle password reset form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate passwords
    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in both password fields.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Update user password
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_data['user_id']]);
            
            // Mark token as used
            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET is_used = TRUE WHERE token = ?");
            $stmt->execute([$token]);
            
            // Commit transaction
            $pdo->commit();
            
            // Clear session variables
            unset($_SESSION['verified_token']);
            unset($_SESSION['reset_user_email']);
            
            $success = "✅ Your password has been reset successfully! You can now login with your new password.";
            
            // Set auto-redirect header
            header("refresh:3;url=login.php");
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Password reset error: " . $e->getMessage());
            $error = "An error occurred while resetting your password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - DKU Scheduler</title>
    <!-- Version: 2.0 - Updated styling -->
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
        
        /* Reset container */
        .reset-container {
            width: 100%;
            max-width: 500px;
            position: relative;
        }
        
        /* Reset box with glass effect */
        .reset-box { 
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
        .reset-box::before {
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
        .reset-title {
            margin-bottom: 25px; 
            color: #fff; 
            font-size: 28px;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            letter-spacing: 0.5px;
            position: relative;
            display: inline-block;
        }
        
        .reset-title::after {
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
        
        /* User info box */
        .user-info-box {
            background: rgba(255, 255, 255, 0.07);
            padding: 20px;
            border-radius: 14px;
            margin-bottom: 25px;
            text-align: center;
            border: 1px solid rgba(96, 165, 250, 0.3);
            animation: fadeIn 0.8s ease;
        }
        
        .user-info-label {
            color: #cbd5e1;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .user-info-value {
            color: #fff;
            font-size: 18px;
            font-weight: 600;
            word-break: break-all;
        }
        
        /* Form styling */
        .form-group {
            margin-bottom: 25px;
            text-align: left;
            position: relative;
        }
        
        .form-label { 
            display: block; 
            margin-bottom: 10px; 
            color: #e2e8f0; 
            font-size: 0.95rem; 
            font-weight: 500;
            letter-spacing: 0.3px;
            padding-left: 5px;
        }
        
        .input-wrapper {
            position: relative;
            transition: transform 0.3s ease;
        }
        
        .input-wrapper:hover {
            transform: translateY(-2px);
        }
        
        .form-input { 
            width: 100%; 
            padding: 16px 48px 16px 20px; 
            border: 1px solid rgba(255, 255, 255, 0.15); 
            border-radius: 14px; 
            font-size: 1rem; 
            outline: none; 
            background: rgba(255, 255, 255, 0.07);
            color: #fff;
            transition: all 0.3s ease;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.95rem;
        }
        
        .form-input:focus {
            border-color: #60a5fa;
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 
                0 0 0 3px rgba(96, 165, 250, 0.2),
                0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* Password toggle button */
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: rgba(255, 255, 255, 0.6);
            font-size: 18px;
            user-select: none;
            background: rgba(255, 255, 255, 0.1);
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .toggle-password:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-50%) scale(1.1);
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
            content: '🔑';
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
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(16, 185, 129, 0.5);
        }
        
        .btn-submit:hover::after {
            left: 100%;
        }
        
        .btn-submit:active {
            transform: translateY(-1px);
        }
        
        /* Back button */
        .btn-back { 
            width: 100%; 
            padding: 15px; 
            background: rgba(255, 255, 255, 0.1);
            color: #cbd5e1; 
            font-weight: 600; 
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            border: 1px solid rgba(255, 255, 255, 0.15); 
            border-radius: 14px; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            margin-top: 15px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-back:hover { 
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.25);
        }
        
        .btn-back::before {
            content: '←';
            font-size: 18px;
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
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
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
        
        .step.completed .step-circle {
            background: #10b981;
            border-color: #34d399;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.5);
        }
        
        .step.completed .step-circle::after {
            content: '✓';
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
        
        .step.completed .step-label,
        .step.active .step-label {
            color: #fff;
            font-weight: 500;
        }
        
        /* Password requirements */
        .requirements-box {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 14px;
            margin-top: 15px;
            text-align: left;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .requirements-title {
            color: #e2e8f0;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .requirements-title::before {
            content: '🔒';
            font-size: 16px;
        }
        
        .requirement-item {
            color: #cbd5e1;
            font-size: 13px;
            margin: 8px 0;
            padding-left: 20px;
            position: relative;
        }
        
        .requirement-item::before {
            content: '•';
            position: absolute;
            left: 0;
            color: #60a5fa;
        }
        
        /* Redirect message */
        .redirect-message {
            color: #cbd5e1;
            font-size: 14px;
            margin: 20px 0;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* Footer text */
        .reset-footer {
            margin-top: 30px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85rem;
            text-align: center;
            line-height: 1.5;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .reset-box {
                padding: 35px 25px;
            }
            
            .dku-logo-img {
                width: 80px;
                height: 80px;
            }
            
            .reset-title {
                font-size: 24px;
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
        }
        
        @media (max-width: 480px) {
            .reset-box {
                padding: 30px 20px;
                border-radius: 20px;
            }
            
            .dku-logo-img {
                width: 70px;
                height: 70px;
            }
            
            .reset-title {
                font-size: 22px;
            }
            
            .btn-submit, .btn-back {
                padding: 16px;
            }
            
            .user-info-value {
                font-size: 16px;
            }
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
    </style>
</head>
<body>
    <!-- Background elements -->
    <div class="gradient-overlay"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>

    <div class="main-container">
        <div class="reset-container">
            <div class="reset-box">
                <div class="logo-container">
                    <img src="assets/images/dku logo.jpg" alt="Debark University Logo" class="dku-logo-img">
                    <div class="logo-text">DEBARK UNIVERSITY</div>
                    <div class="logo-subtext">Set New Password</div>
                </div>
                
                <?php if (!$valid_token && !$success): ?>
                    <h2 class="reset-title">Session Expired</h2>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    <a href="forgot_password.php" class="btn-back">
                        <span>Start Password Reset Over</span>
                    </a>
                    <a href="login.php" class="btn-back" style="margin-top: 10px;">
                        <span>Back to Login</span>
                    </a>
                    
                <?php elseif ($success): ?>
                    <h2 class="reset-title">✅ Password Reset Successful</h2>
                    <div class="success-message">
                        <p><?php echo htmlspecialchars($success); ?></p>
                        <div class="redirect-message">
                            Redirecting to login page in 3 seconds...
                        </div>
                    </div>
                    <a href="login.php" class="btn-submit">
                        <span>Go to Login Now</span>
                    </a>
                    
                    <!-- Process steps -->
                    <div class="process-steps">
                        <div class="step completed">
                            <div class="step-circle"></div>
                            <div class="step-label">Enter Email</div>
                        </div>
                        <div class="step completed">
                            <div class="step-circle"></div>
                            <div class="step-label">Verify Code</div>
                        </div>
                        <div class="step completed">
                            <div class="step-circle"></div>
                            <div class="step-label">New Password</div>
                        </div>
                        <div class="step active">
                            <div class="step-circle">4</div>
                            <div class="step-label">Complete</div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <h2 class="reset-title">Set New Password</h2>
                    
                    <!-- Process steps -->
                    <div class="process-steps">
                        <div class="step completed">
                            <div class="step-circle"></div>
                            <div class="step-label">Enter Email</div>
                        </div>
                        <div class="step completed">
                            <div class="step-circle"></div>
                            <div class="step-label">Verify Code</div>
                        </div>
                        <div class="step active">
                            <div class="step-circle">3</div>
                            <div class="step-label">New Password</div>
                        </div>
                        <div class="step">
                            <div class="step-circle">4</div>
                            <div class="step-label">Complete</div>
                        </div>
                    </div>
                    
                    <div class="user-info-box">
                        <div class="user-info-label">Setting new password for:</div>
                        <div class="user-info-value"><?php echo htmlspecialchars($user_data['email']); ?></div>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label" for="password">New Password:</label>
                            <div class="input-wrapper">
                                <input type="password" name="password" id="password" class="form-input" required minlength="8" placeholder="Minimum 8 characters">
                                <span class="toggle-password" onclick="togglePassword('password')">👁</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm New Password:</label>
                            <div class="input-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" class="form-input" required minlength="8" placeholder="Re-enter your password">
                                <span class="toggle-password" onclick="togglePassword('confirm_password')">👁</span>
                            </div>
                        </div>
                        
                        <div class="requirements-box">
                            <div class="requirements-title">Password Requirements:</div>
                            <div class="requirement-item">At least 8 characters long</div>
                            <div class="requirement-item">Use a mix of letters, numbers, and symbols</div>
                            <div class="requirement-item">Avoid using common passwords</div>
                            <div class="requirement-item">Make it unique to your account</div>
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <span>Reset Password</span>
                        </button>
                    </form>
                    
                    <a href="login.php" class="btn-back">
                        <span>Back to Login</span>
                    </a>
                <?php endif; ?>
                
                <div class="reset-footer">
                    Need help? Contact the DKU IT Support Team<br>
                    Email: support@dku.edu | Phone: (123) 456-7890
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password toggle functionality
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = passwordInput.parentElement.querySelector('.toggle-password');
            
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                toggleIcon.textContent = "🙈";
                toggleIcon.title = "Hide password";
            } else {
                passwordInput.type = "password";
                toggleIcon.textContent = "👁";
                toggleIcon.title = "Show password";
            }
        }
        
        // Add ripple effect to submit button
        const submitButton = document.querySelector('.btn-submit');
        if (submitButton) {
            submitButton.addEventListener('click', function(e) {
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
        
        // Add focus effects for inputs
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-4px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
            
            // Password strength indicator
            if (input.id === 'password') {
                input.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    
                    if (password.length >= 8) strength++;
                    if (/[A-Z]/.test(password)) strength++;
                    if (/[0-9]/.test(password)) strength++;
                    if (/[^A-Za-z0-9]/.test(password)) strength++;
                    
                    // Update border color based on strength
                    switch(strength) {
                        case 0:
                            this.style.borderColor = '';
                            break;
                        case 1:
                            this.style.borderColor = '#f87171';
                            break;
                        case 2:
                            this.style.borderColor = '#fbbf24';
                            break;
                        case 3:
                            this.style.borderColor = '#60a5fa';
                            break;
                        case 4:
                            this.style.borderColor = '#34d399';
                            break;
                    }
                });
            }
            
            // Confirm password validation
            if (input.id === 'confirm_password') {
                input.addEventListener('input', function() {
                    const password = document.getElementById('password').value;
                    const confirmPassword = this.value;
                    
                    if (confirmPassword.length > 0 && password !== confirmPassword) {
                        this.style.borderColor = '#f87171';
                    } else if (confirmPassword.length > 0) {
                        this.style.borderColor = '#34d399';
                    } else {
                        this.style.borderColor = '';
                    }
                });
            }
        });
        
        // Form validation
        const resetForm = document.querySelector('form');
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password.length < 8) {
                    e.preventDefault();
                    showError('Password must be at least 8 characters long');
                    document.getElementById('password').style.borderColor = '#f87171';
                    document.getElementById('password').focus();
                    return;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    showError('Passwords do not match');
                    document.getElementById('confirm_password').style.borderColor = '#f87171';
                    document.getElementById('confirm_password').focus();
                    return;
                }
            });
        }
        
        function showError(message) {
            // Remove existing error messages
            const existingError = document.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
            }
            
            // Create new error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = message;
            
            // Insert after the user info box
            const userInfoBox = document.querySelector('.user-info-box');
            if (userInfoBox) {
                userInfoBox.parentNode.insertBefore(errorDiv, userInfoBox.nextElementSibling);
            }
        }
        
        // Add page load animation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.reset-box').style.animationPlayState = 'running';
            
            // Auto-focus password field if on reset form
            const passwordField = document.getElementById('password');
            if (passwordField && passwordField.type !== 'hidden') {
                setTimeout(() => {
                    passwordField.focus();
                }, 500);
            }
        });
    </script>
</body>
</html>