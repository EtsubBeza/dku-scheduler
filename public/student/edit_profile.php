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
$message_type = 'success'; // success, error, warning

if($_SERVER['REQUEST_METHOD']=='POST'){
    // Check which form was submitted
    if(isset($_POST['update_profile'])) {
        // Profile update form
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        
        // Profile picture upload
        if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error']==0){
            $fileName = time().'_'.basename($_FILES['profile_picture']['name']);
            $target_dir = __DIR__.'/uploads/';
            
            // Create uploads directory if it doesn't exist
            if(!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = mime_content_type($_FILES['profile_picture']['tmp_name']);
            
            if(in_array($file_type, $allowed_types)) {
                if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_dir.$fileName)) {
                    // Delete old profile picture if it exists and is not default
                    if($user['profile_picture'] && $user['profile_picture'] != 'default.jpg' && file_exists($target_dir.$user['profile_picture'])) {
                        unlink($target_dir.$user['profile_picture']);
                    }
                } else {
                    $fileName = $user['profile_picture'];
                    $message = "Error uploading profile picture. Please try again.";
                    $message_type = 'error';
                }
            } else {
                $fileName = $user['profile_picture'];
                $message = "Invalid file type. Please upload JPEG, PNG, GIF, or WebP images only.";
                $message_type = 'error';
            }
        } else {
            $fileName = $user['profile_picture'];
        }

        $update = $pdo->prepare("UPDATE users SET username=?, email=?, profile_picture=? WHERE user_id=?");
        if($update->execute([$username, $email, $fileName, $student_id])) {
            $message = "Profile updated successfully!";
            $message_type = 'success';
            $user['username'] = $username;
            $user['email'] = $email;
            $user['profile_picture'] = $fileName;
        } else {
            $message = "Error updating profile. Please try again.";
            $message_type = 'error';
        }
        
    } elseif(isset($_POST['change_password'])) {
        // Password change form
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Fetch current password hash
        $password_stmt = $pdo->prepare("SELECT password FROM users WHERE user_id=?");
        $password_stmt->execute([$student_id]);
        $current_password_hash = $password_stmt->fetchColumn();
        
        // Verify current password
        if(!password_verify($current_password, $current_password_hash)) {
            $message = "Current password is incorrect.";
            $message_type = 'error';
        } elseif($new_password !== $confirm_password) {
            $message = "New passwords do not match.";
            $message_type = 'error';
        } elseif(strlen($new_password) < 6) {
            $message = "New password must be at least 6 characters long.";
            $message_type = 'error';
        } else {
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_password = $pdo->prepare("UPDATE users SET password=? WHERE user_id=?");
            
            if($update_password->execute([$new_password_hash, $student_id])) {
                $message = "Password changed successfully!";
                $message_type = 'success';
            } else {
                $message = "Error changing password. Please try again.";
                $message_type = 'error';
            }
        }
    }
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
.profile-form, .password-form {
    background:#fff;
    padding:25px;
    border-radius:12px;
    box-shadow:0 4px 6px rgba(0,0,0,0.1);
    max-width:500px;
    margin-bottom:25px;
}
.profile-form label, .password-form label {
    display:block;
    margin-bottom:5px;
    font-weight:600;
    margin-top:15px;
}
.profile-picture {
    text-align:center;
    margin-bottom:20px;
}
.profile-picture img {
    border-radius:50%;
    object-fit:cover;
    width:120px;
    height:120px;
    border:2px solid #2563eb;
}
input[type="text"], input[type="email"], input[type="file"], input[type="password"] {
    padding:10px;
    width:100%;
    margin-bottom:10px;
    border:1px solid #d1d5db;
    border-radius:5px;
    font-size:14px;
}
input[type="text"]:focus, input[type="email"]:focus, input[type="password"]:focus {
    outline:none;
    border-color:#2563eb;
    box-shadow:0 0 0 3px rgba(37, 99, 235, 0.1);
}
button, input[type="submit"] {
    background:#2563eb;
    color:#fff;
    border:none;
    padding:10px 20px;
    border-radius:5px;
    cursor:pointer;
    margin-top:10px;
    font-weight:600;
    transition:background 0.3s;
}
button:hover, input[type="submit"]:hover {
    background:#1d4ed8;
}
.form-section {
    margin-bottom:30px;
}
.form-section h2 {
    color:#1e293b;
    margin-bottom:15px;
    font-size:1.5rem;
    border-bottom:2px solid #e2e8f0;
    padding-bottom:8px;
}

/* Message Styles */
.message {
    padding:12px 15px;
    margin-bottom:20px;
    border-radius:8px;
    font-weight:600;
    border:1px solid transparent;
}
.message.success {
    background:#d1f7d6;
    color:#2c662d;
    border-color:#c3e6cb;
}
.message.error {
    background:#f8d7da;
    color:#721c24;
    border-color:#f5c6cb;
}
.message.warning {
    background:#fff3cd;
    color:#856404;
    border-color:#ffeaa7;
}

/* Password Requirements */
.password-requirements {
    background:#f8f9fa;
    padding:10px;
    border-radius:5px;
    margin:10px 0;
    font-size:12px;
    color:#6c757d;
}
.password-requirements ul {
    margin:5px 0;
    padding-left:20px;
}
.password-requirements li {
    margin-bottom:3px;
}

/* Responsive */
@media screen and (max-width:768px){
    body{flex-direction:column;}
    .sidebar{width:100%;padding:15px;box-shadow:none;}
    .main-content{margin:0;padding:20px;}
    .profile-form, .password-form {
        margin:10px 0;
        padding:15px;
    }
}
</style>
</head>
<body>
<div class="sidebar">
    <h2>Student Panel</h2>
    <a href="student_dashboard.php">Dashboard</a>
    <a href="my_schedule.php">My Schedule</a>
    <a href="view_announcements.php">Announcements</a>
    <a href="edit_profile.php" class="active">Edit Profile</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <h1>Edit Profile</h1>
    
    <?php if($message): ?>
        <div class="message <?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Profile Information Form -->
    <div class="form-section">
        <h2><i class="fas fa-user-edit"></i> Profile Information</h2>
        <form method="post" enctype="multipart/form-data" class="profile-form">
            <div class="profile-picture">
                <?php if($user['profile_picture'] && file_exists(__DIR__.'/uploads/'.$user['profile_picture'])): ?>
                    <img src="uploads/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture">
                <?php else: ?>
                    <img src="https://via.placeholder.com/120" alt="Profile Picture">
                <?php endif; ?>
            </div>
            
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            
            <label for="profile_picture">Profile Picture</label>
            <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
            <small style="color:#6c757d; font-size:12px;">Supported formats: JPG, PNG, GIF, WebP. Max size: 2MB</small>
            
            <input type="submit" name="update_profile" value="Update Profile">
        </form>
    </div>

    <!-- Password Change Form -->
    <div class="form-section">
        <h2><i class="fas fa-lock"></i> Change Password</h2>
        <form method="post" class="password-form">
            <div class="password-requirements">
                <strong>Password Requirements:</strong>
                <ul>
                    <li>At least 6 characters long</li>
                    <li>Should be different from your current password</li>
                </ul>
            </div>
            
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" required placeholder="Enter your current password">
            
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required placeholder="Enter new password" minlength="6">
            
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password" minlength="6">
            
            <input type="submit" name="change_password" value="Change Password">
        </form>
    </div>
</div>

<!-- Font Awesome for icons -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>

<script>
// Password confirmation validation
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePassword() {
        if(newPassword.value !== confirmPassword.value) {
            confirmPassword.style.borderColor = '#e53e3e';
        } else {
            confirmPassword.style.borderColor = '#38a169';
        }
    }
    
    newPassword.addEventListener('input', validatePassword);
    confirmPassword.addEventListener('input', validatePassword);
    
    // Clear message after 5 seconds
    const message = document.querySelector('.message');
    if(message) {
        setTimeout(() => {
            message.style.opacity = '0';
            message.style.transition = 'opacity 0.5s ease';
            setTimeout(() => message.remove(), 500);
        }, 5000);
    }
});
</script>
</body>
</html>