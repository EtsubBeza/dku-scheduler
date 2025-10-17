<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Redirect if not logged in
if(!isset($_SESSION['user_id'])){
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if(isset($_POST['update_profile'])){
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);

    // Handle profile picture upload
    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0){
        $allowed_ext = ['jpg','jpeg','png','gif'];
        $file_name = $_FILES['profile_picture']['name'];
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if(in_array($file_ext, $allowed_ext)){
            $new_name = 'profile_'.$user_id.'_'.time().'.'.$file_ext;
            $upload_dir = __DIR__.'/../../uploads/profiles/';
            if(!is_dir($upload_dir)){
                mkdir($upload_dir, 0755, true);
            }
            move_uploaded_file($file_tmp, $upload_dir.$new_name);

            // Delete old picture if exists
            if(!empty($user['profile_picture']) && file_exists($upload_dir.$user['profile_picture'])){
                unlink($upload_dir.$user['profile_picture']);
            }

            $profile_picture = $new_name;
        } else {
            $message = "Invalid file type. Allowed: jpg, jpeg, png, gif.";
        }
    } else {
        $profile_picture = $user['profile_picture']; // Keep old
    }

    // Update user info
    if(empty($message)){
        $update_stmt = $pdo->prepare("UPDATE users SET username=?, email=?, profile_picture=? WHERE user_id=?");
        $update_stmt->execute([$username, $email, $profile_picture, $user_id]);
        $message = "Profile updated successfully!";
        // Refresh user data
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Profile</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
body {font-family: Arial, sans-serif; margin:0; background:#f3f4f6;}
.sidebar {position: fixed; top:0; left:0; width:240px; height:100%; background:#2c3e50; color:#fff; padding-top:20px;}
.sidebar h2 {text-align:center; margin-bottom:20px;}
.sidebar a {display:block; color:#fff; padding:12px 20px; text-decoration:none; margin-bottom:5px;}
.sidebar a.active, .sidebar a:hover {background:#1abc9c;}
.main-content {margin-left:240px; padding:30px;}
form {background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 8px rgba(0,0,0,0.05); margin-top:20px;}
form label {display:block; margin:10px 0 5px;}
form input[type="text"], form input[type="email"], form input[type="file"], form input[type="submit"] {
    padding:10px; border-radius:6px; border:1px solid #ccc; margin-bottom:15px; width:100%;
}
form input[type="submit"] {background:#2563eb; color:#fff; border:none; cursor:pointer;}
form input[type="submit"]:hover {background:#1e40af;}
.message {padding:10px; background:#d1ffd1; border:1px solid #1abc9c; margin-bottom:20px; border-radius:5px; font-weight:bold;}
.profile-pic {width:120px; height:120px; border-radius:50%; object-fit:cover; margin-bottom:15px; border:2px solid #ccc;}
@media screen and (max-width:768px){.sidebar{position:relative;width:100%;height:auto;}.main-content{margin-left:0;}}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <h1>Edit Profile</h1>

    <?php if($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php
    $profile_path = __DIR__.'/../../uploads/profiles/'.($user['profile_picture'] ?? '');
    if(!empty($user['profile_picture']) && file_exists($profile_path)): ?>
        <img src="../../uploads/profiles/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture" class="profile-pic">
    <?php else: ?>
        <img src="../assets/default_profile.png" alt="Profile Picture" class="profile-pic">
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Username:</label>
        <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES) ?>" required>

        <label>Email:</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES) ?>" required>

        <label>Profile Picture:</label>
        <input type="file" name="profile_picture" accept="image/*">

        <input type="submit" name="update_profile" value="Update Profile">
    </form>
</div>

</body>
</html>
