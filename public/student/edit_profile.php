<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// Fetch current info
$user_stmt = $pdo->prepare("SELECT username, email, profile_picture FROM users WHERE user_id=?");
$user_stmt->execute([$student_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
$message = '';
if($_SERVER['REQUEST_METHOD']=='POST'){
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    
    // Profile picture upload
    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error']==0){
        $fileName = time().'_'.basename($_FILES['profile_picture']['name']);
        $target_dir = __DIR__.'/uploads/';
        move_uploaded_file($_FILES['profile_picture']['tmp_name'],$target_dir.$fileName);
    } else {
        $fileName = $user['profile_picture'];
    }

    $update = $pdo->prepare("UPDATE users SET username=?, email=?, profile_picture=? WHERE user_id=?");
    $update->execute([$username,$email,$fileName,$student_id]);
    $message = "Profile updated successfully!";
    $user['username']=$username;
    $user['email']=$email;
    $user['profile_picture']=$fileName;
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
/* General Reset & Sidebar */
* {margin:0;padding:0;box-sizing:border-box;}
body {font-family:Arial,sans-serif;display:flex;min-height:100vh;background:#f3f4f6;}
.sidebar {position:fixed;top:0;left:0;height:100vh;width:220px;background:#2c3e50;padding-top:20px;display:flex;flex-direction:column;align-items:flex-start;box-shadow:2px 0 5px rgba(0,0,0,0.1);z-index:1000;overflow-y:auto;}
.sidebar h2 {color:#ecf0f1;text-align:center;width:100%;margin-bottom:20px;font-size:20px;}
.sidebar a {padding:12px 20px;text-decoration:none;font-size:16px;color:#bdc3c7;width:100%;transition:background 0.3s,color 0.3s;}
.sidebar a.active {background-color:#34495e;color:#fff;font-weight:bold;}
.sidebar a:hover {background-color:#34495e;color:#fff;}

/* Main Content */
.main-content {margin-left:220px;padding:30px;flex-grow:1;min-height:100vh;background:#f3f4f6;}
.main-content h1 {margin-bottom:20px;color:#111827;}

/* Profile Form */
.profile-form{background:#fff;padding:25px;border-radius:12px;box-shadow:0 4px 6px rgba(0,0,0,0.1);max-width:500px;margin-top:20px;}
.profile-form label{display:block;margin-bottom:5px;font-weight:600;margin-top:15px;}
.profile-picture{text-align:center;margin-bottom:20px;}
.profile-picture img{border-radius:50%;object-fit:cover;width:120px;height:120px;border:2px solid #2563eb;}
input[type="text"],input[type="email"],input[type="file"]{padding:8px;width:100%;margin-bottom:10px;border:1px solid #d1d5db;border-radius:5px;}
button,input[type="submit"]{background:#2563eb;color:#fff;border:none;padding:8px 15px;border-radius:5px;cursor:pointer;margin-top:10px;}
button:hover,input[type="submit"]:hover{background:#1d4ed8;}
.message{padding:12px;margin-bottom:15px;border-radius:10px;background:#d1f7d6;color:#2c662d;font-weight:bold;}

/* Responsive */
@media screen and (max-width:768px){
    body{flex-direction:column;}
    .sidebar{width:100%;padding:15px;box-shadow:none;}
    .main-content{margin:0;padding:20px;}
}
</style>
</head>
<body>
<div class="sidebar">
    <h2>Student Panel</h2>
    <a href="student_dashboard.php">Dashboard</a>
    <a href="my_schedule.php">My Schedule</a>
    <a href="view_announcements.php" class="active">Announcements</a>
    <a href="edit_profile.php">Edit Profile</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <h1>Edit Profile</h1>
    <?php if($message): ?><div class="message"><?= $message ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="profile-form">
        <div class="profile-picture">
            <?php if($user['profile_picture'] && file_exists(__DIR__.'/uploads/'.$user['profile_picture'])): ?>
                <img src="uploads/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture">
            <?php else: ?>
                <img src="https://via.placeholder.com/120" alt="Profile Picture">
            <?php endif; ?>
        </div>
        <label>Username</label>
        <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
        <label>Profile Picture</label>
        <input type="file" name="profile_picture" accept="image/*">
        <input type="submit" value="Update Profile">
    </form>
</div>
</body>
</html>
