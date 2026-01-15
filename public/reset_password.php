<?php
session_start();
require __DIR__ . '/../includes/db.php';

$error = "";
$success = "";
$valid_token = false;
$user_data = null;

// Check for verified token from session
if (isset($_SESSION['verified_token'])) {
    $token = $_SESSION['verified_token'];
    
    try {
        // Verify token is valid and verified
        $stmt = $pdo->prepare("
            SELECT prt.*, u.username, u.email 
            FROM password_reset_tokens prt 
            JOIN users u ON prt.user_id = u.user_id 
            WHERE prt.token = ? AND prt.verified = TRUE AND prt.is_used = FALSE AND prt.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($token_data) {
            $valid_token = true;
            $user_data = $token_data;
        } else {
            $error = "Invalid or expired reset session. Please start over.";
            unset($_SESSION['verified_token']);
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
} else {
    $error = "No reset session found. Please start from forgot password.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Hash new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update user's password
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_data['user_id']]);
            
            // Mark token as used
            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET is_used = TRUE WHERE token = ?");
            $stmt->execute([$token]);
            
            // Clear session
            unset($_SESSION['verified_token']);
            unset($_SESSION['reset_user_email']);
            
            // Commit transaction
            $pdo->commit();
            
            $success = "✅ Password has been reset successfully! You can now login with your new password.";
            $valid_token = false;
            
            // Auto-redirect after 3 seconds
          echo '<script>setTimeout(function() { window.location.href = "login.php"; }, 3000);</script>';
            
        } catch (PDOException $e) {
            $pdo->rollBack();
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
    <title>Reset Password - DKU Scheduler</title>
    <style>
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(-45deg, #2563eb, #1e3a8a, #9333ea, #2563eb); background-size: 400% 400%; animation: gradientBG 12s ease infinite; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        @keyframes gradientBG { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }
        .reset-box { width: 100%; max-width: 500px; padding: 40px; border-radius: 15px; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); text-align: center; z-index: 2; animation: fadeIn 1s ease-in-out; }
        .logo { width: 70px; height: 70px; margin: 0 auto 20px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: bold; color: #2563eb; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        h2 { margin-bottom: 20px; color: #fff; }
        label { display: block; text-align: left; margin: 10px 0 5px; color: #e0e7ff; font-size: 0.9rem; }
        input { width: 100%; padding: 12px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 1rem; outline: none; background: rgba(255,255,255,0.9); }
        .password-wrapper { position: relative; }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #555; font-size: 1.2rem; user-select: none; background: white; padding: 2px 8px; border-radius: 5px; }
        button { width: 100%; padding: 14px; background: #10b981; color: #fff; font-weight: bold; border: none; border-radius: 25px; cursor: pointer; font-size: 1rem; transition: all 0.3s ease; margin-top: 10px; }
        button:hover { background: #059669; transform: scale(1.02); }
        .back-login { display: inline-block; margin-top: 20px; color: #e0e7ff; text-decoration: none; font-size: 0.9rem; }
        .success { color: #10b981; margin-bottom: 20px; font-size: 0.95rem; line-height: 1.5; background: rgba(16, 185, 129, 0.1); padding: 15px; border-radius: 8px; border-left: 4px solid #10b981; }
        .error { color: #ff4d4d; margin-bottom: 20px; font-size: 0.95rem; line-height: 1.5; background: rgba(255, 77, 77, 0.1); padding: 15px; border-radius: 8px; border-left: 4px solid #ff4d4d; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="reset-box">
        <div class="logo">DKU</div>
        
        <?php if (!$valid_token && !$success): ?>
            <h2>Reset Password</h2>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <a href="forgot_password.php" class="back-login">← Start Over</a>
            <div style="margin-top: 20px;">
                <a href="login.php" class="back-login">← Back to Login</a>
            </div>
            
        <?php elseif ($success): ?>
            <h2>✅ Password Reset Successful</h2>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <p style="color: #e0e7ff; font-size: 0.9rem; margin: 20px 0;">
                Redirecting to login page in 3 seconds...
            </p>
            <a href="login.php" class="back-login" style="background: #2563eb; color: white; padding: 10px 20px; border-radius: 25px; text-decoration: none; display: inline-block;">Go to Login Now</a>
            
        <?php else: ?>
            <h2>Set New Password</h2>
            <p style="color: #e0e7ff; margin-bottom: 20px;">
                Account: <strong><?php echo htmlspecialchars($user_data['email']); ?></strong>
            </p>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <label for="password">New Password:</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" required minlength="8" placeholder="Minimum 8 characters">
                    <span class="toggle-password" onclick="togglePassword('password')">👁</span>
                </div>
                
                <label for="confirm_password">Confirm New Password:</label>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" required minlength="8" placeholder="Re-enter your password">
                    <span class="toggle-password" onclick="togglePassword('confirm_password')">👁</span>
                </div>
                
                <button type="submit">Reset Password</button>
            </form>
            
            <a href="login.php" class="back-login">← Back to Login</a>
        <?php endif; ?>
    </div>

    <script>
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = passwordInput.nextElementSibling;
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                toggleIcon.textContent = "🙈";
            } else {
                passwordInput.type = "password";
                toggleIcon.textContent = "👁";
            }
        }
    </script>
</body>
</html>