<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor'){
    header("Location: ../index.php");
    exit;
}

$instructor_id = $_SESSION['user_id'];

// Fetch current user info
$user_stmt = $pdo->prepare("SELECT username, email, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$instructor_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$message = "";

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    
    // Handle profile picture upload
    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error']===0){
        $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $filename = "profile_".$instructor_id."_".time().".".$ext;
        move_uploaded_file($_FILES['profile_picture']['tmp_name'], __DIR__."/uploads/".$filename);
    } else {
        $filename = $user['profile_picture'];
    }

    $update = $pdo->prepare("UPDATE users SET username=?, email=?, profile_picture=? WHERE user_id=?");
    $update->execute([$username, $email, $filename, $instructor_id]);
    $message = "Profile updated successfully!";
    // Refresh user info
    $user['username']=$username;
    $user['email']=$email;
    $user['profile_picture']=$filename;
}

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Profile</title>
<style>
body{font-family:Arial,sans-serif;display:flex;min-height:100vh;background: linear-gradient(-45deg, #0f2027, #203a43, #2c5364);background-size:400% 400%;animation: gradientBG 15s ease infinite;}
@keyframes gradientBG{0%{background-position:0% 50%;}50%{background-position:100% 50%;}100%{background-position:0% 50%;}}

.sidebar{position:fixed;top:0;left:0;height:100vh;width:240px;background-color: rgba(44,62,80,0.95);padding-top:20px;display:flex;flex-direction:column;align-items:flex-start;box-shadow:2px 0 10px rgba(0,0,0,0.2);z-index:1000;overflow-y:auto;}
.sidebar h2{color:#ecf0f1;text-align:center;width:100%;margin-bottom:25px;font-size:22px;}
.sidebar a{padding:12px 20px;text-decoration:none;font-size:16px;color:#bdc3c7;width:100%;transition:background 0.3s,color 0.3s;border-radius:6px;margin:3px 0;}
.sidebar a.active,.sidebar a:hover{background-color:#34495e;color:#fff;font-weight:bold;}

.main-content{margin-left:240px;padding:30px;flex-grow:1;min-height:100vh;background-color: rgba(243,244,246,0.95);border-radius:12px;margin-top:20px;margin-bottom:20px;}
h1,h2{margin-bottom:20px;color:#111827;}

form{max-width:500px;background:#fff;padding:25px;border-radius:16px;box-shadow:0 8px 20px rgba(0,0,0,0.15);}
form label{display:block;margin-bottom:8px;font-weight:600;margin-top:15px;}
form input{width:100%;padding:10px;border-radius:6px;border:1px solid #ccc;margin-bottom:12px;}
form input[type="file"]{padding:3px;}
form button{background:#2575fc;color:#fff;padding:10px 20px;border:none;border-radius:8px;cursor:pointer;transition:0.3s;}
form button:hover{background:#1254c1;}
.message{padding:10px 15px;margin-bottom:15px;border-radius:10px;background:#d1f7d6;color:#2c662d;font-weight:bold;}

@media screen and (max-width:768px){body{flex-direction:column;}.sidebar{width:100%;padding:15px;box-shadow:none;}.main-content{margin:0;padding:20px;border-radius:0;}}
</style>
</head>
<body>
<div class="sidebar">
    <h2>Instructor Panel</h2>
    <a href="instructor_dashboard.php" class="<?= $current_page=='instructor_dashboard.php'?'active':'' ?>">Dashboard</a>
    <a href="my_courses.php" class="<?= $current_page=='my_courses.php'?'active':'' ?>">My Courses</a>
    <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">Edit Profile</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <h1>Edit Profile</h1>
    <?php if($message): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <label>Username</label>
        <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>

        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>

        <label>Profile Picture</label>
        <input type="file" name="profile_picture">

        <button type="submit">Update Profile</button>
    </form>
</div>
</body>
</html>
