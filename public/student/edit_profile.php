<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// Fetch current user info - INCLUDING EMAIL
$user_stmt = $pdo->prepare("SELECT username, email, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// FIXED: Simplified profile picture path logic
$default_profile = '../assets/default_profile.png';

// Function to check if profile picture exists
function getProfilePicturePath($profile_picture) {
    if (empty($profile_picture)) {
        return '../assets/default_profile.png';
    }
    
    // Try multiple possible locations
    $locations = [
        __DIR__ . '/../uploads/' . $profile_picture,
        __DIR__ . '/../../uploads/' . $profile_picture,
        'uploads/' . $profile_picture,
        '../uploads/' . $profile_picture,
    ];
    
    foreach ($locations as $location) {
        if (file_exists($location)) {
            // Return the appropriate web path
            if (strpos($location, '../../uploads/') !== false) {
                return '../../uploads/' . $profile_picture;
            } elseif (strpos($location, '../uploads/') !== false) {
                return '../uploads/' . $profile_picture;
            } elseif (strpos($location, 'uploads/') !== false) {
                return 'uploads/' . $profile_picture;
            }
        }
    }
    
    // If file doesn't exist anywhere, return default
    return '../assets/default_profile.png';
}

// Get profile image path for sidebar
$profile_img_path = getProfilePicturePath($user['profile_picture'] ?? '');

// Handle form submission
$message = '';
$message_type = 'success'; // success, error, warning

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    // Check which form was submitted
    if(isset($_POST['update_profile'])) {
        // Profile update form
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $fileName = $user['profile_picture'] ?? ''; // Keep existing by default
        
        // Profile picture upload
        if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/';
            
            // Create uploads directory if it doesn't exist
            if(!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Get file info
            $file_name = $_FILES['profile_picture']['name'];
            $file_tmp = $_FILES['profile_picture']['tmp_name'];
            $file_size = $_FILES['profile_picture']['size'];
            
            // Validate file size (2MB = 2097152 bytes)
            if ($file_size > 2097152) {
                $message = "File is too large. Maximum size is 2MB.";
                $message_type = 'error';
            } else {
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = mime_content_type($file_tmp);
                
                if(in_array($file_type, $allowed_types)) {
                    // Generate unique filename
                    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    $fileName = time() . '_' . uniqid() . '.' . $file_extension;
                    
                    // Move uploaded file
                    if(move_uploaded_file($file_tmp, $upload_dir . $fileName)) {
                        // Delete old profile picture if it exists and is not default
                        if(!empty($user['profile_picture']) && 
                           $user['profile_picture'] != 'default_profile.png' && 
                           file_exists($upload_dir . $user['profile_picture'])) {
                            unlink($upload_dir . $user['profile_picture']);
                        }
                    } else {
                        $fileName = $user['profile_picture'] ?? '';
                        $message = "Error uploading profile picture. Please try again.";
                        $message_type = 'error';
                    }
                } else {
                    $fileName = $user['profile_picture'] ?? '';
                    $message = "Invalid file type. Please upload JPEG, PNG, GIF, or WebP images only.";
                    $message_type = 'error';
                }
            }
        }
        
        // Update user in database
        $update = $pdo->prepare("UPDATE users SET username = ?, email = ?, profile_picture = ? WHERE user_id = ?");
        if($update->execute([$username, $email, $fileName, $student_id])) {
            $message = "Profile updated successfully!";
            $message_type = 'success';
            
            // Update user array with new data
            $user['username'] = $username;
            $user['email'] = $email;
            $user['profile_picture'] = $fileName;
            
            // Update profile image path
            $profile_img_path = getProfilePicturePath($fileName);
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
        $password_stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
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
            $update_password = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            
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
<title>Edit Profile | Student Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin:0; padding:0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

/* ================= Topbar for Hamburger ================= */
.topbar {
    display: none;
    position: fixed; top:0; left:0; width:100%;
    background:#2c3e50; color:#fff;
    padding:15px 20px;
    z-index:1200;
    justify-content:space-between; align-items:center;
}
.menu-btn {
    font-size:26px;
    background:#1abc9c;
    border:none; color:#fff;
    cursor:pointer;
    padding:10px 14px;
    border-radius:8px;
    font-weight:600;
    transition: background 0.3s, transform 0.2s;
}
.menu-btn:hover { background:#159b81; transform:translateY(-2px); }

/* ================= Sidebar ================= */
.sidebar {
    position: fixed; top:0; left:0;
    width:250px; height:100%;
    background:#1f2937; color:#fff;
    z-index:1100;
    transition: transform 0.3s ease;
    padding: 20px 0;
}
.sidebar.hidden { transform:translateX(-260px); }
.sidebar a { 
    display:block; 
    padding:12px 20px; 
    color:#fff; 
    text-decoration:none; 
    transition: background 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.sidebar a:hover, .sidebar a.active { background:#1abc9c; }

.sidebar-profile {
    text-align: center;
    margin-bottom: 20px;
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.sidebar-profile img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 10px;
    border: 2px solid #1abc9c;
    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
}

.sidebar-profile p {
    color: #fff;
    font-weight: bold;
    margin: 0;
    font-size: 16px;
}

/* Sidebar title */
.sidebar h2 {
    text-align: center;
    color: #ecf0f1;
    margin-bottom: 25px;
    font-size: 22px;
    padding: 0 20px;
}

/* ================= Overlay ================= */
.overlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.4); z-index:1050;
    display:none; opacity:0; transition: opacity 0.3s ease;
}
.overlay.active { display:block; opacity:1; }

/* ================= Main content ================= */
.main-content {
    margin-left: 250px;
    padding:20px;
    min-height:100vh;
    background: #f8fafc;
    transition: all 0.3s ease;
}

/* Content Wrapper */
.content-wrapper {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    min-height: calc(100vh - 40px);
}

/* Header Styles */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.header h1 {
    font-size: 2.2rem;
    color: #1f2937;
    font-weight: 700;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #f8fafc;
    padding: 12px 18px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.user-info img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
}

/* Welcome Section */
.welcome-section {
    margin-bottom: 30px;
}

.welcome-section p {
    color: #6b7280;
    font-size: 1.1rem;
    margin-top: 10px;
}

/* ================= Forms Section ================= */
.forms-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-top: 30px;
}

@media (max-width: 1024px) {
    .forms-section {
        grid-template-columns: 1fr;
    }
}

/* Form Cards */
.form-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.form-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
}

.form-card h2 {
    font-size: 1.5rem;
    color: #1f2937;
    margin-bottom: 25px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e5e7eb;
}

.form-card h2 i {
    color: #3b82f6;
}

/* Form Elements */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
    color: #374151;
    transition: all 0.3s;
    background: #f9fafb;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    background: white;
}

.form-control::placeholder {
    color: #9ca3af;
}

/* Profile Picture Section */
.profile-picture-section {
    text-align: center;
    margin-bottom: 25px;
}

.current-profile-pic {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #3b82f6;
    margin-bottom: 15px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.file-input-wrapper {
    position: relative;
    display: inline-block;
    width: 100%;
}

.file-input-wrapper input[type="file"] {
    width: 0.1px;
    height: 0.1px;
    opacity: 0;
    overflow: hidden;
    position: absolute;
    z-index: -1;
}

.file-input-wrapper label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: #3b82f6;
    color: white;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.3s;
    width: 100%;
    justify-content: center;
}

.file-input-wrapper label:hover {
    background: #2563eb;
}

.file-input-wrapper label i {
    font-size: 1.1rem;
}

.file-name {
    margin-top: 8px;
    font-size: 0.85rem;
    color: #6b7280;
    text-align: center;
}

/* Submit Buttons */
.btn-submit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 25px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s;
    margin-top: 10px;
    width: 100%;
    justify-content: center;
}

.btn-submit:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

/* Password Requirements */
.password-requirements {
    background: #f8fafc;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #3b82f6;
}

.password-requirements h4 {
    font-size: 0.95rem;
    color: #374151;
    margin-bottom: 8px;
    font-weight: 600;
}

.password-requirements ul {
    margin: 0;
    padding-left: 20px;
    color: #6b7280;
    font-size: 0.85rem;
}

.password-requirements li {
    margin-bottom: 5px;
    line-height: 1.4;
}

/* Message Styles */
.message {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

@keyframes slideIn {
    from {
        transform: translateY(-10px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.message.success {
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    color: #166534;
    border-left: 4px solid #10b981;
}

.message.error {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

.message.warning {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    border-left: 4px solid #f59e0b;
}

.message i {
    font-size: 1.2rem;
}

/* Password match indicator */
.password-match {
    font-size: 0.85rem;
    margin-top: 5px;
    padding: 5px;
    border-radius: 4px;
    text-align: center;
}

.password-match.valid {
    background: #dcfce7;
    color: #166534;
}

.password-match.invalid {
    background: #fee2e2;
    color: #991b1b;
}

/* Form Tips */
.form-tip {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 5px;
    font-style: italic;
}

/* ================= Responsive ================= */
@media (max-width: 768px) {
    .topbar { display: flex; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .main-content { margin-left: 0; padding: 15px; }
    .content-wrapper { padding: 20px; border-radius: 0; }
    .header { flex-direction: column; gap: 15px; align-items: flex-start; }
    .header h1 { font-size: 1.8rem; }
    .forms-section { gap: 20px; }
    .form-card { padding: 20px; }
    .current-profile-pic { width: 120px; height: 120px; }
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Edit Profile</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar - SAME AS OTHER PAGES -->
    <div class="sidebar">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" onerror="this.onerror=null; this.src='../assets/default_profile.png';">
            <p><?= htmlspecialchars($user['username'] ?? 'Student') ?></p>
        </div>
        <h2>Student Panel</h2>
        <a href="student_dashboard.php" class="<?= $current_page=='student_dashboard.php'?'active':'' ?>">Dashboard</a>
        <a href="my_schedule.php" class="<?= $current_page=='my_schedule.php'?'active':'' ?>">My Schedule</a>
        <a href="view_exam_schedules.php" class="<?= $current_page=='view_exam_schedules.php'?'active':'' ?>">Exam Schedule</a>
        <a href="view_announcements.php" class="<?= $current_page=='view_announcements.php'?'active':'' ?>">Announcements</a>
        <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">Edit Profile</a>
        <a href="../logout.php">Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
                <div class="welcome-section">
                    <h1>Edit Profile</h1>
                    <p>Update your personal information and manage your account settings</p>
                </div>
                <div class="user-info">
                    <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile" onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                    <div>
                        <div><?= htmlspecialchars($user['username'] ?? 'Student') ?></div>
                        <small>Student</small>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if($message): ?>
                <div class="message <?= $message_type ?>">
                    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Forms Section -->
            <div class="forms-section">
                <!-- Profile Information Form -->
                <div class="form-card">
                    <h2><i class="fas fa-user-edit"></i> Profile Information</h2>
                    
                    <form method="post" enctype="multipart/form-data" id="profileForm">
                        <div class="profile-picture-section">
                            <?php 
                            // Use the same function for consistency
                            $profile_pic_path = getProfilePicturePath($user['profile_picture'] ?? '');
                            ?>
                            <img src="<?= htmlspecialchars($profile_pic_path) ?>" 
                                 alt="Current Profile Picture" 
                                 class="current-profile-pic" 
                                 id="profilePreview"
                                 onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                            
                            <div class="form-group">
                                <div class="file-input-wrapper">
                                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" onchange="previewProfilePicture(this)">
                                    <label for="profile_picture">
                                        <i class="fas fa-camera"></i> Choose New Picture
                                    </label>
                                </div>
                                <div class="file-name" id="fileName"></div>
                                <div class="form-tip">Max file size: 2MB • Supported: JPG, PNG, GIF, WebP</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" 
                                   value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn-submit">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>

                <!-- Password Change Form -->
                <div class="form-card">
                    <h2><i class="fas fa-lock"></i> Change Password</h2>
                    
                    <div class="password-requirements">
                        <h4>Password Requirements:</h4>
                        <ul>
                            <li>At least 6 characters long</li>
                            <li>Should be different from your current password</li>
                            <li>Use a combination of letters, numbers, and symbols for better security</li>
                        </ul>
                    </div>
                    
                    <form method="post" id="passwordForm">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" 
                                   class="form-control" required placeholder="Enter your current password">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" 
                                   class="form-control" required placeholder="Enter new password" minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   class="form-control" required placeholder="Confirm new password" minlength="6">
                            <div class="password-match" id="passwordMatch"></div>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn-submit">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    // Set active state for current page
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.sidebar a');
        
        navLinks.forEach(link => {
            const linkPage = link.getAttribute('href');
            if (linkPage === currentPage) {
                link.classList.add('active');
            }
        });
    });

    // Confirm logout
    document.querySelector('a[href="../logout.php"]').addEventListener('click', function(e) {
        if(!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });

    // Profile picture preview
    function previewProfilePicture(input) {
        const fileName = document.getElementById('fileName');
        const preview = document.getElementById('profilePreview');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            fileName.textContent = file.name;
            
            // Validate file size (2MB = 2 * 1024 * 1024 bytes)
            if (file.size > 2 * 1024 * 1024) {
                fileName.innerHTML = '<span style="color:#ef4444;">File too large! Max 2MB</span>';
                input.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    }

    // Password confirmation validation
    function validatePassword() {
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordMatch = document.getElementById('passwordMatch');
        
        if (newPassword.value === '' || confirmPassword.value === '') {
            passwordMatch.textContent = '';
            passwordMatch.className = 'password-match';
            return;
        }
        
        if (newPassword.value === confirmPassword.value) {
            passwordMatch.textContent = '✓ Passwords match';
            passwordMatch.className = 'password-match valid';
        } else {
            passwordMatch.textContent = '✗ Passwords do not match';
            passwordMatch.className = 'password-match invalid';
        }
    }
    
    // Add event listeners for password validation
    document.getElementById('new_password').addEventListener('input', validatePassword);
    document.getElementById('confirm_password').addEventListener('input', validatePassword);

    // Auto-close messages after 5 seconds
    setTimeout(() => {
        const message = document.querySelector('.message');
        if (message) {
            message.style.opacity = '0';
            message.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                if (message.parentNode) {
                    message.parentNode.removeChild(message);
                }
            }, 500);
        }
    }, 5000);

    // Form validation
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        const username = document.getElementById('username').value.trim();
        const email = document.getElementById('email').value.trim();
        
        if (!username || !email) {
            e.preventDefault();
            alert('Please fill in all required fields');
            return false;
        }
    });

    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        const currentPass = document.getElementById('current_password').value;
        const newPass = document.getElementById('new_password').value;
        const confirmPass = document.getElementById('confirm_password').value;
        
        if (!currentPass || !newPass || !confirmPass) {
            e.preventDefault();
            alert('Please fill in all password fields');
            return false;
        }
        
        if (newPass !== confirmPass) {
            e.preventDefault();
            alert('New passwords do not match');
            return false;
        }
        
        if (newPass.length < 6) {
            e.preventDefault();
            alert('New password must be at least 6 characters long');
            return false;
        }
    });
    </script>
</body>
</html>