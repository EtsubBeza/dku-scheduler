<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor'){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

$instructor_id = $_SESSION['user_id'];

// Fetch current user info
$user_stmt = $pdo->prepare("SELECT username, email, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$instructor_id]);
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

// Get profile image path
$profile_img_path = getProfilePicturePath($user['profile_picture'] ?? '');

$message = "";
$message_type = "success"; // success, error, warning

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    
    // Validation
    if(empty($username) || empty($email)) {
        $message = "Please fill in all required fields.";
        $message_type = "error";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    } else {
        // Handle profile picture upload
        $filename = $user['profile_picture'];
        
        if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error']===0){
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['profile_picture']['type'];
            $file_size = $_FILES['profile_picture']['size'];
            
            // Validate file type
            if(!in_array($file_type, $allowed_types)) {
                $message = "Invalid file type. Please upload JPEG, PNG, GIF, or WebP images only.";
                $message_type = "error";
            } elseif($file_size > 2 * 1024 * 1024) { // 2MB limit
                $message = "File is too large. Maximum size is 2MB.";
                $message_type = "error";
            } else {
                $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $filename = "profile_".$instructor_id."_".time().".".$ext;
                $upload_path = __DIR__."/../uploads/".$filename;
                
                // Create uploads directory if it doesn't exist
                if(!is_dir(dirname($upload_path))) {
                    mkdir(dirname($upload_path), 0755, true);
                }
                
                if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    // Delete old profile picture if it exists and is not default
                    if(!empty($user['profile_picture']) && 
                       $user['profile_picture'] != 'default_profile.png' && 
                       file_exists(__DIR__."/../uploads/".$user['profile_picture'])) {
                        unlink(__DIR__."/../uploads/".$user['profile_picture']);
                    }
                } else {
                    $message = "Error uploading profile picture. Please try again.";
                    $message_type = "error";
                    $filename = $user['profile_picture']; // Keep old picture on error
                }
            }
        }
        
        // Update database if no errors
        if($message_type !== "error") {
            $update = $pdo->prepare("UPDATE users SET username=?, email=?, profile_picture=? WHERE user_id=?");
            if($update->execute([$username, $email, $filename, $instructor_id])) {
                $message = "Profile updated successfully!";
                $message_type = "success";
                
                // Refresh user info
                $user['username'] = $username;
                $user['email'] = $email;
                $user['profile_picture'] = $filename;
                
                // Update profile image path
                $profile_img_path = getProfilePicturePath($filename);
            } else {
                $message = "Error updating profile. Please try again.";
                $message_type = "error";
            }
        }
    }
}

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<title>Edit Profile | Instructor Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Include Dark Mode CSS -->
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

/* ================= Topbar for Hamburger ================= */
.topbar {
    display: none;
    position: fixed; top:0; left:0; width:100%;
    background:var(--bg-sidebar); color:var(--text-sidebar);
    padding:15px 20px;
    z-index:1200;
    justify-content:space-between; align-items:center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.menu-btn {
    font-size:26px;
    background:#1abc9c;
    border:none; color:var(--text-sidebar);
    cursor:pointer;
    padding:10px 14px;
    border-radius:8px;
    font-weight:600;
    transition: background 0.3s, transform 0.2s;
}
.menu-btn:hover { background:#159b81; transform:translateY(-2px); }

/* ================= Sidebar ================= */
.sidebar {
    position: fixed;
    top:0; left:0;
    height:100vh;
    width:240px;
    background: var(--bg-sidebar);
    padding: 30px 0 20px;
    display:flex;
    flex-direction:column;
    align-items:center;
    box-shadow:2px 0 10px rgba(0,0,0,0.2);
    z-index:1000;
    overflow-y:auto;
    transition: transform 0.3s ease;
}
.sidebar.hidden { transform:translateX(-100%); }

.sidebar-profile {
    text-align: center;
    margin-bottom: 25px;
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    width: 100%;
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
    color: var(--text-sidebar);
    font-weight: bold;
    margin: 0;
    font-size: 16px;
}

.sidebar h2 {
    color: var(--text-sidebar);
    text-align:center;
    width:100%;
    margin-bottom:25px;
    font-size:22px;
    padding: 0 20px;
}
.sidebar a {
    padding:12px 20px;
    text-decoration:none;
    font-size:16px;
    color:var(--text-sidebar);
    width:100%;
    transition: background 0.3s, color 0.3s;
    border-radius:6px;
    margin:3px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.sidebar a.active, .sidebar a:hover {
    background:#1abc9c;
    color:#fff;
    font-weight:bold;
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
    margin-left:240px;
    padding:30px;
    flex-grow:1;
    min-height:100vh;
    background: var(--bg-primary);
    border-radius:12px;
    margin-top:20px;
    margin-bottom:20px;
    width: calc(100% - 240px);
    transition: all 0.3s ease;
}

/* Content Wrapper */
.content-wrapper {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 6px var(--shadow-color);
    min-height: calc(100vh - 40px);
}

/* Header Styles */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.header h1 {
    font-size: 2.2rem;
    color: var(--text-primary);
    font-weight: 700;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--bg-secondary);
    padding: 12px 18px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.user-info img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
}

.user-info div div {
    font-weight: 600;
    color: var(--text-primary);
}

.user-info small {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* Welcome Section */
.welcome-section {
    margin-bottom: 30px;
}

.welcome-section h1 {
    font-size: 2.2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.5rem;
}

.welcome-section p {
    color: var(--text-secondary);
    font-size: 1.1rem;
    margin-top: 10px;
}

/* ================= Edit Profile Form ================= */
.edit-profile-container {
    margin-top: 30px;
}

.profile-form-card {
    background: var(--bg-card);
    max-width: 600px;
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 8px 20px var(--shadow-color);
    border: 1px solid var(--border-color);
    margin: 0 auto;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.profile-form-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 25px var(--shadow-lg);
}

.profile-form-card h2 {
    color: var(--text-primary);
    margin-bottom: 25px;
    font-size: 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border-color);
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
    box-shadow: 0 4px 12px var(--shadow-color);
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
    color: var(--text-secondary);
    text-align: center;
}

/* Form Elements */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 1rem;
    color: var(--text-primary);
    transition: all 0.3s;
    background: var(--bg-secondary);
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    background: var(--bg-card);
}

.form-control::placeholder {
    color: var(--text-secondary);
}

/* Submit Button */
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

/* Form Tips */
.form-tip {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 5px;
    font-style: italic;
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
    box-shadow: 0 4px 6px var(--shadow-color);
    border-left: 4px solid;
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
    background: linear-gradient(135deg, var(--success-bg), #bbf7d0);
    color: var(--success-text);
    border-color: var(--success-border);
}

.message.error {
    background: linear-gradient(135deg, var(--error-bg), #fecaca);
    color: var(--error-text);
    border-color: var(--error-border);
}

.message.warning {
    background: linear-gradient(135deg, var(--warning-bg), #fde68a);
    color: var(--warning-text);
    border-color: var(--warning-border);
}

.message i {
    font-size: 1.2rem;
}

/* Dark mode specific adjustments */
[data-theme="dark"] .btn-submit {
    background: linear-gradient(135deg, #059669, #047857);
}

[data-theme="dark"] .btn-submit:hover {
    background: linear-gradient(135deg, #047857, #065f46);
}

[data-theme="dark"] .file-input-wrapper label {
    background: #2563eb;
}

[data-theme="dark"] .file-input-wrapper label:hover {
    background: #1d4ed8;
}

[data-theme="dark"] .current-profile-pic {
    border-color: #3b82f6;
}

[data-theme="dark"] .profile-form-card h2 {
    border-bottom-color: var(--border-color);
}

/* ================= Responsive ================= */
@media screen and (max-width: 768px){
    .topbar { display: flex; }
    .sidebar { transform: translateX(-100%); width: 250px; }
    .sidebar.active { transform: translateX(0); }
    .main-content { margin-left: 0; padding: 15px; width: 100%; }
    .content-wrapper { padding: 20px; border-radius: 0; }
    .header { flex-direction: column; gap: 15px; align-items: flex-start; }
    .header h1 { font-size: 1.8rem; }
    .profile-form-card { padding: 20px; }
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

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic"
                 onerror="this.onerror=null; this.src='../assets/default_profile.png';">
            <p><?= htmlspecialchars($user['username'] ?? 'Instructor') ?></p>
        </div>
        <h2>Instructor Dashboard</h2>
        <a href="instructor_dashboard.php" class="<?= $current_page=='instructor_dashboard.php'?'active':'' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="my_courses.php" class="<?= $current_page=='my_courses.php'?'active':'' ?>">
            <i class="fas fa-book"></i> My Courses
        </a>

        <a href="edit_profile.php" class="active">
            <i class="fas fa-user-edit"></i> Edit Profile
        </a>
        <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
                <div class="welcome-section">
                    <h1>Edit Profile</h1>
                    <p>Update your personal information and profile picture</p>
                </div>
                <div class="user-info">
                    <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile" id="headerProfilePic"
                         onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                    <div>
                        <div><?= htmlspecialchars($user['username'] ?? 'Instructor') ?></div>
                        <small>Instructor</small>
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

            <!-- Edit Profile Form -->
            <div class="edit-profile-container">
                <div class="profile-form-card">
                    <h2><i class="fas fa-user-edit"></i> Update Your Information</h2>
                    
                    <form method="post" enctype="multipart/form-data" id="profileForm">
                        <div class="profile-picture-section">
                            <img src="<?= htmlspecialchars($profile_img_path) ?>" 
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
                        
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Include darkmode.js -->
    <script src="../../assets/js/darkmode.js"></script>
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
        
        // Add animation to form card
        const formCard = document.querySelector('.profile-form-card');
        formCard.style.opacity = '0';
        formCard.style.transform = 'translateY(20px)';
        setTimeout(() => {
            formCard.style.transition = 'all 0.5s ease';
            formCard.style.opacity = '1';
            formCard.style.transform = 'translateY(0)';
        }, 200);
        
        // Debug: Log profile picture paths
        console.log('Sidebar profile pic src:', document.getElementById('sidebarProfilePic').src);
        console.log('Header profile pic src:', document.getElementById('headerProfilePic').src);
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
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address');
            return false;
        }
    });
    
    // Fallback for broken profile pictures
    function handleImageError(img) {
        img.onerror = null;
        img.src = '../assets/default_profile.png';
        return true;
    }
    
    // Set profile picture fallbacks
    document.addEventListener('DOMContentLoaded', function() {
        const profileImages = document.querySelectorAll('img[src*="profile"], img[alt*="Profile"]');
        profileImages.forEach(img => {
            img.onerror = function() {
                this.src = '../assets/default_profile.png';
            };
        });
    });
    </script>
</body>
</html>