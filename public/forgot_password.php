<?php
// forgot_password.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email_functions.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT user_id, username, email FROM users WHERE email = ? AND is_approved = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Generate 6-digit code and token
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        try {
            // Delete any existing tokens for this user
            $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND is_used = FALSE");
            $stmt->execute([$user['user_id']]);
            
            // Store new token with code in database ONLY
            $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, code, expires_at) VALUES (?, ?, ?, ?)");
            $inserted = $stmt->execute([$user['user_id'], $token, $code, $expires_at]);
            
            if ($inserted) {
                // Send email with code
                if (sendPasswordResetCode($user['email'], $user['username'], $code, $token)) {
                    // Store only email in session for verification page
                    $_SESSION['reset_user_email'] = $user['email'];
                    
                    // Redirect to verification page
                    header("Location: verify_code.php");
                    exit();
                } else {
                    $error = "❌ Failed to send email. Please try again.";
                }
            } else {
                $error = "Failed to generate reset code. Please try again.";
            }
            
        } catch (PDOException $e) {
            error_log("Database error in forgot_password: " . $e->getMessage());
            $error = "An error occurred. Please try again.";
        }
        
    } else {
        // For security, show same message whether user exists or not
        $success = "If an account exists with this email, a verification code has been sent.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - DKU Scheduler</title>
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
            max-width: 480px;
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
        
        /* Info box styling */
        .info-box {
            background: rgba(255, 255, 255, 0.07);
            padding: 20px;
            border-radius: 14px;
            margin-bottom: 25px;
            text-align: left;
            border-left: 4px solid #60a5fa;
            animation: fadeIn 0.8s ease;
        }
        
        .info-box h4 {
            color: #fff;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-box h4::before {
            content: '📧';
            font-size: 18px;
        }
        
        .info-box ul {
            color: #cbd5e1;
            padding-left: 20px;
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        
        .info-box li {
            margin: 8px 0;
            position: relative;
            padding-left: 5px;
        }
        
        .info-box li::marker {
            color: #60a5fa;
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
            padding: 16px 20px; 
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
        
        /* Button styling */
        .btn-submit { 
            width: 100%; 
            padding: 17px; 
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
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
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-submit::before {
            content: '📨';
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
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(37, 99, 235, 0.5);
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
                    <div class="logo-subtext">Password Recovery</div>
                </div>
                
                <h2 class="reset-title">Reset Your Password</h2>
                
                <!-- Process steps -->
                <div class="process-steps">
                    <div class="step active">
                        <div class="step-circle">1</div>
                        <div class="step-label">Enter Email</div>
                    </div>
                    <div class="step">
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
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success && empty($_POST['email'])): ?>
                    <div class="success-message">
                        <p><?php echo htmlspecialchars($success); ?></p>
                        <p style="margin-top: 10px; font-size: 0.9rem; opacity: 0.9;">
                            Please check your email for the verification code.
                        </p>
                    </div>
                    
                    <a href="login.php" class="btn-back">
                        <span>Return to Login</span>
                    </a>
                    
                <?php else: ?>
                
                    <div class="info-box">
                        <h4>Password Reset Process</h4>
                        <ul>
                            <li>Enter your registered email address below</li>
                            <li>Check your email for a 6-digit verification code</li>
                            <li>Enter the code on the verification page</li>
                            <li>Set a new secure password for your account</li>
                            <li>Verification code expires in 10 minutes</li>
                        </ul>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label" for="email">Registered Email Address:</label>
                            <div class="input-wrapper">
                                <input type="email" name="email" id="email" class="form-input" required 
                                       placeholder="your.email@dku.edu"
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <span>Send Verification Code</span>
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
            
            // Add glow effect on valid input
            input.addEventListener('input', function() {
                if (this.value.length > 0) {
                    this.style.boxShadow = '0 0 0 3px rgba(96, 165, 250, 0.2), 0 5px 15px rgba(0, 0, 0, 0.2)';
                } else {
                    this.style.boxShadow = '';
                }
            });
        });
        
        // Form validation
        const resetForm = document.querySelector('form');
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                const email = document.getElementById('email').value.trim();
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (!email) {
                    e.preventDefault();
                    showError('Please enter your email address');
                    document.getElementById('email').style.borderColor = '#f87171';
                    return;
                }
                
                if (!emailPattern.test(email)) {
                    e.preventDefault();
                    showError('Please enter a valid email address');
                    document.getElementById('email').style.borderColor = '#f87171';
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
            
            // Insert after the title
            const title = document.querySelector('.reset-title');
            title.parentNode.insertBefore(errorDiv, title.nextElementSibling.nextElementSibling);
        }
        
        // Reset border color on input
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('input', function() {
                this.style.borderColor = '';
            });
        });
        
        // Add page load animation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.reset-box').style.animationPlayState = 'running';
            
            // Add typing effect to email placeholder
            const emailInput = document.getElementById('email');
            if (emailInput) {
                const placeholderText = 'your.email@dku.edu';
                let i = 0;
                
                function typePlaceholder() {
                    if (i < placeholderText.length) {
                        emailInput.placeholder = placeholderText.substring(0, i + 1);
                        i++;
                        setTimeout(typePlaceholder, 50);
                    }
                }
                
                // Start typing effect after a delay
                setTimeout(typePlaceholder, 1000);
            }
        });
    </script>
</body>
</html>