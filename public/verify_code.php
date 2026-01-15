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
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(-45deg, #2563eb, #1e3a8a, #9333ea, #2563eb); background-size: 400% 400%; animation: gradientBG 12s ease infinite; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        @keyframes gradientBG { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }
        .verify-box { width: 100%; max-width: 500px; padding: 40px; border-radius: 15px; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); text-align: center; z-index: 2; animation: fadeIn 1s ease-in-out; }
        .logo { width: 70px; height: 70px; margin: 0 auto 20px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: bold; color: #2563eb; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        h2 { margin-bottom: 20px; color: #fff; }
        .email-display { color: #fff; background: rgba(255,255,255,0.1); padding: 10px 20px; border-radius: 25px; margin-bottom: 20px; display: inline-block; }
        .instructions { background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: left; }
        .instructions h4 { color: #fff; margin-top: 0; }
        .instructions ul { color: #e0e7ff; padding-left: 20px; margin: 10px 0; }
        .timer { font-size: 24px; font-weight: bold; color: #10b981; margin: 20px 0; }
        .timer.expired { color: #ff4d4d; }
        button { width: 100%; padding: 14px; background: #10b981; color: #fff; font-weight: bold; border: none; border-radius: 25px; cursor: pointer; font-size: 1rem; transition: all 0.3s ease; margin-top: 10px; }
        button:hover { background: #059669; transform: scale(1.02); }
        .back-login, .new-code { display: inline-block; margin-top: 20px; color: #e0e7ff; text-decoration: none; font-size: 0.9rem; margin-right: 15px; }
        .error { color: #ff4d4d; margin-bottom: 20px; font-size: 0.95rem; line-height: 1.5; background: rgba(255, 77, 77, 0.1); padding: 15px; border-radius: 8px; border-left: 4px solid #ff4d4d; }
        .success { color: #10b981; margin-bottom: 20px; font-size: 0.95rem; line-height: 1.5; background: rgba(16, 185, 129, 0.1); padding: 15px; border-radius: 8px; border-left: 4px solid #10b981; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Digit input styling */
        .digit-inputs {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 25px 0;
        }
        .digit-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.9);
            color: #2563eb;
            transition: all 0.3s ease;
            outline: none;
        }
        .digit-input:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3);
            transform: scale(1.05);
        }
        .digit-input.filled {
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.1);
        }
        .digit-input.error {
            border-color: #ff4d4d;
            animation: shake 0.5s ease;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Resend button */
        .resend-btn {
            background: transparent;
            border: 2px solid #2563eb;
            color: #e0e7ff;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .resend-btn:hover {
            background: #2563eb;
            color: white;
        }
        .resend-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Auto-submit message */
        .auto-submit {
            color: #10b981;
            font-size: 0.9rem;
            margin-top: 10px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="verify-box">
        <div class="logo">DKU</div>
        <h2>Enter Verification Code</h2>
        
        <?php if ($user_email): ?>
            <div class="email-display">
                Code sent to: <strong><?php echo htmlspecialchars($user_email); ?></strong>
            </div>
        <?php endif; ?>
        
        <div class="instructions">
            <h4>📝 Instructions:</h4>
            <ul>
                <li>Check your email for the 6-digit code</li>
                <li>Enter each digit in the boxes below</li>
                <li>Code will auto-advance and auto-submit</li>
                <li>Code expires in 10 minutes</li>
            </ul>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="timer <?php echo ($time_remaining === '00:00') ? 'expired' : ''; ?>">
            ⏰ Time remaining: <?php echo $time_remaining; ?>
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
                ⚡ Code will auto-submit when complete
            </div>
            
            <button type="submit" id="submitBtn" style="opacity: 0.7;" disabled>Verify Code</button>
        </form>
        
        <div style="margin-top: 20px;">
            <a href="forgot_password.php?resend=true" class="new-code">← Request new code</a>
            <a href="login.php" class="back-login">← Back to Login</a>
        </div>
        
        <button class="resend-btn" id="resendBtn" onclick="resendCode()">
            ↻ Resend Code <span id="resendTimer">(60s)</span>
        </button>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const digitInputs = document.querySelectorAll('.digit-input');
            const form = document.getElementById('verifyForm');
            const submitBtn = document.getElementById('submitBtn');
            const fullCodeField = document.getElementById('fullCode');
            const resendBtn = document.getElementById('resendBtn');
            const resendTimer = document.getElementById('resendTimer');
            
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
                
                // Remove error class when user starts typing again
                input.addEventListener('focus', function() {
                    this.classList.remove('error');
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
                submitBtn.style.opacity = allFilled ? '1' : '0.7';
                
                // Auto-submit if all digits are filled
                if (allFilled && code.length === 6) {
                    setTimeout(() => {
                        form.submit();
                    }, 300);
                }
            }
            
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
            
            // Update timer every second
            setInterval(updateResendTimer, 1000);
            
            // Update main timer
            function updateMainTimer() {
                const timerElement = document.querySelector('.timer');
                if (timerElement && !timerElement.classList.contains('expired')) {
                    const timeText = timerElement.textContent;
                    const timeMatch = timeText.match(/(\d{2}):(\d{2})/);
                    
                    if (timeMatch) {
                        let minutes = parseInt(timeMatch[1]);
                        let seconds = parseInt(timeMatch[2]);
                        
                        if (seconds > 0) {
                            seconds--;
                        } else if (minutes > 0) {
                            minutes--;
                            seconds = 59;
                        } else {
                            timerElement.classList.add('expired');
                            timerElement.textContent = '⏰ Code expired!';
                            return;
                        }
                        
                        const newTime = minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
                        timerElement.textContent = '⏰ Time remaining: ' + newTime;
                    }
                }
            }
            
            setInterval(updateMainTimer, 1000);
        });
        
        function resendCode() {
            if (!canResend) return;
            
            window.location.href = 'forgot_password.php?resend=true';
        }
        
        function clearAllDigits() {
            const digitInputs = document.querySelectorAll('.digit-input');
            digitInputs.forEach(input => {
                input.value = '';
                input.classList.remove('filled');
            });
            digitInputs[0].focus();
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').style.opacity = '0.7';
        }
    </script>
</body>
</html>