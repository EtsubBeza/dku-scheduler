<?php
session_start();
require __DIR__ . '/../includes/db.php';

$message = '';

// Fetch departments for dropdown
$departments = $pdo->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll();

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $role = trim($_POST['role'] ?? 'student');
    $department_id = intval($_POST['department_id'] ?? 0);
    $id_number = trim($_POST['id_number'] ?? '');

    if(empty($username) || empty($email) || empty($password) || empty($confirm_password) || $department_id === 0 || empty($id_number)){
        $message = "All fields are required!";
    } elseif($password !== $confirm_password){
        $message = "Passwords do not match!";
    } else {
        // Check duplicates
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ? OR id_number = ?");
        $stmt->execute([$email, $username, $id_number]);

        if($stmt->rowCount() > 0){
            $existing = $stmt->fetch();
            if($existing['email'] === $email) $message = "Email already registered!";
            elseif($existing['username'] === $username) $message = "Username already taken!";
            elseif($existing['id_number'] === $id_number) $message = "ID number already registered!";
        } else {
            // Insert user with is_approved = 0
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (username, email, password, role, department_id, id_number, is_approved) VALUES (?, ?, ?, ?, ?, ?, 0)");

            if($insert->execute([$username, $email, $hashed_password, $role, $department_id, $id_number])){
                $message = "Registration successful! Your account will be approved by the admin before you can login.";
            } else {
                $message = "Registration failed, please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - DKU Scheduler</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
body { background-color: #f3f4f6; display: flex; flex-direction: column; min-height: 100vh; }
header { background-color: #1d4ed8; color: #fff; padding: 15px 20px; text-align: center; font-size: 24px; font-weight: bold; }
.container { flex: 1; display: flex; justify-content: center; align-items: center; padding: 20px; }
.form-wrapper { width: 100%; max-width: 400px; }
form { background-color: #fff; padding: 30px 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 100%; }
h2 { text-align: center; margin-bottom: 20px; color: #333; }
form label { display: block; margin-bottom: 5px; color: #555; }
form input, form select { width: 100%; padding: 10px 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; }
form button { width: 100%; padding: 12px; background-color: #1d4ed8; color: #fff; font-size: 16px; border: none; border-radius: 8px; cursor: pointer; transition: background-color 0.3s; }
form button:hover { background-color: #2563eb; }
.back-login { display: block; text-align: center; margin-top: 15px; color: #1d4ed8; text-decoration: none; font-size: 14px; }
.back-login:hover { text-decoration: underline; }
.message { text-align: center; margin-bottom: 15px; padding: 10px; border-radius: 8px; word-wrap: break-word; opacity: 0; animation: fadeInOut 5s forwards; }
@keyframes fadeInOut { 0% { opacity: 0; transform: translateY(-10px); } 10% { opacity: 1; transform: translateY(0); } 80% { opacity: 1; transform: translateY(0); } 100% { opacity: 0; transform: translateY(-10px); } }
.message.success { background-color: #d1fae5; color: #065f46; }
.message.error { background-color: #fee2e2; color: #991b1b; }
footer { background-color: #1d4ed8; color: #fff; text-align: center; padding: 10px; font-size: 14px; }
</style>
</head>
<body>

<header>DKU Scheduler</header>

<div class="container">
    <div class="form-wrapper">
        <h2>Register</h2>

        <?php if($message): ?>
            <div class="message <?php echo (strpos($message,'successful') !== false) ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <label>Username:</label>
            <input type="text" name="username" required>

            <label>Email:</label>
            <input type="email" name="email" required>

            <label>ID Number:</label>
            <input type="text" name="id_number" required placeholder="6-8 digit ID">

            <label>Role:</label>
            <select name="role" required>
                <option value="student">Student</option>
                <option value="instructor">Instructor</option>
            </select>

            <label>Department:</label>
            <select name="department_id" required>
                <option value="">-- Select Department --</option>
                <?php foreach($departments as $dept): ?>
                    <option value="<?php echo $dept['department_id']; ?>">
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Password:</label>
            <input type="password" name="password" required>

            <label>Confirm Password:</label>
            <input type="password" name="confirm_password" required>

            <button type="submit">Register</button>
        </form>

        <a class="back-login" href="login.php">Back to Login</a>
    </div>
</div>

<footer>
    &copy; <?php echo date("Y"); ?> DKU Scheduler. All rights reserved.
</footer>

</body>
</html>
