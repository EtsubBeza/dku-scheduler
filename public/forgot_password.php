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
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(-45deg, #2563eb, #1e3a8a, #9333ea, #2563eb); background-size: 400% 400%; animation: gradientBG 12s ease infinite; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        @keyframes gradientBG { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }
        .reset-box { width: 100%; max-width: 500px; padding: 40px; border-radius: 15px; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); text-align: center; z-index: 2; animation: fadeIn 1s ease-in-out; }
        .logo { width: 70px; height: 70px; margin: 0 auto 20px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: bold; color: #2563eb; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        h2 { margin-bottom: 20px; color: #fff; }
        label { display: block; text-align: left; margin: 10px 0 5px; color: #e0e7ff; font-size: 0.9rem; }
        input { width: 100%; padding: 12px; margin-bottom: 20px; border: none; border-radius: 8px; font-size: 1rem; outline: none; background: rgba(255,255,255,0.9); }
        button { width: 100%; padding: 14px; background: #10b981; color: #fff; font-weight: bold; border: none; border-radius: 25px; cursor: pointer; font-size: 1rem; transition: all 0.3s ease; margin-top: 10px; }
        button:hover { background: #059669; transform: scale(1.02); }
        .back-login { display: inline-block; margin-top: 20px; color: #e0e7ff; text-decoration: none; font-size: 0.9rem; }
        .success { color: #10b981; margin-bottom: 20px; font-size: 0.95rem; line-height: 1.5; background: rgba(16, 185, 129, 0.1); padding: 15px; border-radius: 8px; border-left: 4px solid #10b981; }
        .error { color: #ff4d4d; margin-bottom: 20px; font-size: 0.95rem; line-height: 1.5; background: rgba(255, 77, 77, 0.1); padding: 15px; border-radius: 8px; border-left: 4px solid #ff4d4d; }
        .info-box { background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: left; }
        .info-box h4 { color: #fff; margin-top: 0; }
        .info-box ul { color: #e0e7ff; padding-left: 20px; margin: 10px 0; }
        .info-box li { margin: 5px 0; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="reset-box">
        <div class="logo">DKU</div>
        <h2>Forgot Password</h2>
        
        <div class="info-box">
            <h4>📧 Password Reset Process:</h4>
            <ul>
                <li>Enter your registered email address</li>
                <li>Check your email for a 6-digit code</li>
                <li>Enter the code on the next page</li>
                <li>Set your new password</li>
                <li>Code expires in 10 minutes</li>
            </ul>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success && empty($_POST['email'])): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <div style="margin-top: 20px;">
                <a href="login.php" class="back-login">← Back to Login</a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <label for="email">Enter your registered email address:</label>
                <input type="email" name="email" required placeholder="your.email@dku.edu">
                
                <button type="submit">Send Verification Code</button>
            </form>
            
            <a href="login.php" class="back-login">← Back to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>