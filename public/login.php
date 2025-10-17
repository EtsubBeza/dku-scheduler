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
    body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(-45deg, #2563eb, #1e3a8a, #9333ea, #2563eb); background-size: 400% 400%; animation: gradientBG 12s ease infinite; display: flex; align-items: center; justify-content: center; height: 100vh; overflow: hidden; }
    @keyframes gradientBG { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }
    .circle { position: absolute; border-radius: 50%; background: rgba(255,255,255,0.1); animation: float 10s infinite ease-in-out; }
    .circle:nth-child(1) { width: 120px; height: 120px; left: 10%; top: 20%; animation-duration: 14s; }
    .circle:nth-child(2) { width: 180px; height: 180px; right: 15%; top: 35%; animation-duration: 18s; }
    .circle:nth-child(3) { width: 90px; height: 90px; left: 40%; bottom: 15%; animation-duration: 12s; }
    @keyframes float { 0% { transform: translateY(0); } 50% { transform: translateY(-30px); } 100% { transform: translateY(0); } }
    .login-box { width: 380px; padding: 30px; border-radius: 15px; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); text-align: center; z-index: 2; animation: fadeIn 1s ease-in-out; }
    .logo { width: 70px; height: 70px; margin: 0 auto 15px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: bold; color: #2563eb; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
    h2 { margin-bottom: 20px; color: #fff; }
    label { display: block; text-align: left; margin: 10px 0 5px; color: #e0e7ff; font-size: 0.9rem; }
    input { width: 100%; padding: 10px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 1rem; outline: none; }
    .password-wrapper { position: relative; }
    .toggle-password { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #555; font-size: 1.1rem; user-select: none; }
    button { width: 100%; padding: 12px; background: #10b981; color: #fff; font-weight: bold; border: none; border-radius: 25px; cursor: pointer; font-size: 1rem; animation: pulseGlow 2s infinite; transition: all 0.3s ease; }
    button:hover { background: #059669; transform: scale(1.05); box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
    @keyframes pulseGlow { 0% { box-shadow: 0 0 5px rgba(16,185,129,0.6), 0 0 15px rgba(16,185,129,0.4); } 50% { box-shadow: 0 0 20px rgba(16,185,129,0.8), 0 0 40px rgba(16,185,129,0.6); } 100% { box-shadow: 0 0 5px rgba(16,185,129,0.6), 0 0 15px rgba(16,185,129,0.4); } }
    .back-home { display: inline-block; margin-top: 15px; color: #e0e7ff; text-decoration: none; font-size: 0.9rem; transition: all 0.3s ease; }
    .back-home:hover { color: #fff; text-decoration: underline; }
    .error { color: #ff4d4d; margin-bottom: 15px; font-size: 0.9rem; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body>
  <div class="circle"></div>
  <div class="circle"></div>
  <div class="circle"></div>

  <div class="login-box">
    <div class="logo">DKU</div>
    <h2>Login</h2>

    <?php if ($error): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="username">Username, Email, or ID:</label>
        <input type="text" name="username" required>

        <label for="password">Password:</label>
        <div class="password-wrapper">
            <input type="password" name="password" id="password" required>
            <span class="toggle-password" onclick="togglePassword()">👁</span>
        </div>

        <button type="submit">Login</button>
    </form>

    <a href="index.php" class="back-home">← Back to Home</a>
  </div>

  <script>
    function togglePassword() {
      const passwordInput = document.getElementById("password");
      const toggleIcon = document.querySelector(".toggle-password");
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
