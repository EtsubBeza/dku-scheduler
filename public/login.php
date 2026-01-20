<?php
session_start();
require __DIR__ . '/../includes/db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usernameOrEmailOrID = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Fetch user by username, email, or id_number
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :ue OR email = :ue OR id_number = :ue LIMIT 1");
    $stmt->execute(['ue' => $usernameOrEmailOrID]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['is_approved'] == 0) {
            $error = "Your account is not yet approved by the admin. Please wait.";
        } elseif (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if (array_key_exists('department_id', $user) && !is_null($user['department_id'])) {
                $_SESSION['department_id'] = $user['department_id'];
            }

            switch ($user['role']) {
                case 'admin':
                    header("Location: admin/dashboard.php");
                    break;
                case 'student':
                    header("Location: student/student_dashboard.php");
                    break;
                case 'instructor':
                    header("Location: instructor/instructor_dashboard.php");
                    break;
                case 'department_head':
                    header("Location: departmenthead/departmenthead_dashboard.php");
                    break;
                default:
                    header("Location: index.php");
                    break;
            }
            exit;
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - DKU Scheduler</title>
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
    
    /* Login container */
    .login-container {
      width: 100%;
      max-width: 440px;
      position: relative;
    }
    
    /* Login box with glass effect */
    .login-box { 
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
    .login-box::before {
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
      margin-bottom: 30px;
      position: relative;
    }
    
    .dku-logo-img {
      width: 100px;
      height: 100px;
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
    .login-title {
      margin-bottom: 30px; 
      color: #fff; 
      font-size: 28px;
      font-weight: 600;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
      letter-spacing: 0.5px;
      position: relative;
      display: inline-block;
    }
    
    .login-title::after {
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
      padding: 16px 50px 16px 20px; 
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
    
    /* Password field specific styling */
    .password-wrapper {
      position: relative;
    }
    
    .password-toggle {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      background: transparent;
      border: none;
      color: rgba(255, 255, 255, 0.6);
      font-size: 1.2rem;
      cursor: pointer;
      padding: 5px;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 30px;
      height: 30px;
      border-radius: 50%;
    }
    
    .password-toggle:hover {
      color: #60a5fa;
      background: rgba(255, 255, 255, 0.1);
    }
    
    .password-toggle:active {
      transform: translateY(-50%) scale(0.95);
    }
    
    .password-toggle .eye-icon {
      display: inline-block;
      transition: transform 0.3s ease;
    }
    
    .password-toggle:hover .eye-icon {
      transform: scale(1.1);
    }
    
    /* Button styling */
    .btn-login { 
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
    }
    
    .btn-login::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.7s;
    }
    
    .btn-login:hover { 
      background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
      transform: translateY(-3px);
      box-shadow: 0 12px 25px rgba(37, 99, 235, 0.5);
    }
    
    .btn-login:hover::before {
      left: 100%;
    }
    
    .btn-login:active {
      transform: translateY(-1px);
    }
    
    /* Links container */
    .links-container {
      margin-top: 30px;
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 15px;
    }
    
    .login-link { 
      color: #cbd5e1; 
      text-decoration: none; 
      font-size: 0.9rem; 
      transition: all 0.3s ease; 
      position: relative;
      padding: 5px 0;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    
    .login-link i {
      font-size: 0.8rem;
    }
    
    .login-link::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 0;
      height: 1px;
      background: linear-gradient(to right, #60a5fa, #34d399);
      transition: width 0.3s ease;
    }
    
    .login-link:hover { 
      color: #fff; 
    }
    
    .login-link:hover::after {
      width: 100%;
    }
    
    /* Error message styling */
    .error-message { 
      color: #f87171; 
      margin-bottom: 25px; 
      font-size: 0.9rem; 
      background: rgba(248, 113, 113, 0.1);
      padding: 15px;
      border-radius: 12px;
      border-left: 4px solid #f87171;
      text-align: left;
      animation: shake 0.5s ease;
      backdrop-filter: blur(5px);
    }
    
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-5px); }
      40%, 80% { transform: translateX(5px); }
    }
    
    /* Footer text */
    .login-footer {
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
      .login-box {
        padding: 35px 25px;
      }
      
      .dku-logo-img {
        width: 90px;
        height: 90px;
      }
      
      .login-title {
        font-size: 24px;
      }
      
      .links-container {
        flex-direction: column;
        align-items: center;
        gap: 12px;
      }
      
      .form-input {
        padding: 14px 45px 14px 18px;
      }
    }
    
    @media (max-width: 480px) {
      .login-box {
        padding: 30px 20px;
        border-radius: 20px;
      }
      
      .dku-logo-img {
        width: 80px;
        height: 80px;
      }
      
      .login-title {
        font-size: 22px;
      }
      
      .btn-login {
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
    <div class="login-container">
      <div class="login-box">
        <div class="logo-container">
          <img src="assets/images/dku logo.jpg" alt="Debark University Logo" class="dku-logo-img">
          <div class="logo-text">DEBARK UNIVERSITY</div>
          <div class="logo-subtext">Academic Scheduler System</div>
        </div>
        
        <h2 class="login-title">Secure Login</h2>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
          <div class="form-group">
            <label class="form-label" for="username">Username, Email, or ID:</label>
            <div class="input-wrapper">
              <input type="text" name="username" class="form-input" required placeholder="Enter your username, email or ID">
              <div class="input-icon">👤</div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="password">Password:</label>
            <div class="input-wrapper password-wrapper">
              <input type="password" name="password" id="password" class="form-input" required placeholder="Enter your password">
              <button type="button" class="password-toggle" id="togglePassword">
                <span class="eye-icon">👁</span>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-login">
            <span>Login to Dashboard</span>
          </button>
        </form>

        <div class="links-container">
          <a href="forgot_password.php" class="login-link">
            <i>↺</i> Forgot Password?
          </a>
          <a href="index.php" class="login-link">
            <i>←</i> Back to Homepage
          </a>
        </div>
        
        <div class="login-footer">
          Secure access to Debark University's Academic Scheduling System<br>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Password visibility toggle functionality
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = togglePassword.querySelector('.eye-icon');
    
    togglePassword.addEventListener('click', function() {
      // Toggle the type attribute
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      
      // Toggle the eye icon
      if (type === 'password') {
        eyeIcon.textContent = '👁';
        eyeIcon.style.transform = 'scale(1)';
      } else {
        eyeIcon.textContent = '🙈';
        eyeIcon.style.transform = 'scale(1.1)';
      }
      
      // Add animation effect
      eyeIcon.style.transition = 'transform 0.3s ease';
      setTimeout(() => {
        eyeIcon.style.transform = 'scale(1)';
      }, 300);
      
      // Focus back on the password input
      passwordInput.focus();
    });
    
    // Keyboard accessibility - toggle with Enter/Space key
    togglePassword.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        this.click();
      }
    });

    // Add ripple effect to login button
    const loginButton = document.querySelector('.btn-login');
    loginButton.addEventListener('click', function(e) {
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
    
    // Form submission validation
    const loginForm = document.querySelector('form');
    loginForm.addEventListener('submit', function(e) {
      const username = document.querySelector('input[name="username"]').value.trim();
      const password = document.querySelector('input[name="password"]').value.trim();
      
      if (!username || !password) {
        e.preventDefault();
        // Add visual feedback for empty fields
        if (!username) {
          document.querySelector('input[name="username"]').style.borderColor = '#f87171';
        }
        if (!password) {
          document.querySelector('input[name="password"]').style.borderColor = '#f87171';
        }
      }
    });
    
    // Reset border color on input
    document.querySelectorAll('.form-input').forEach(input => {
      input.addEventListener('input', function() {
        this.style.borderColor = '';
      });
    });
    
    // Add page load animation
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelector('.login-box').style.animationPlayState = 'running';
    });
  </script>
</body>
</html>