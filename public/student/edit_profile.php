<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

$student_id = $_SESSION['user_id'];

// Function to validate username
function validateUsername($username) {
    // Trim whitespace
    $username = trim($username);
    
    // Check if empty
    if (empty($username)) {
        return "Username is required!";
    }
    
    // Check if it contains only numbers
    if (preg_match('/^[0-9\s]+$/', $username)) {
        return "Username cannot contain only numbers! Please use letters or a combination of letters and numbers.";
    }
    
    // Check if it's too short (minimum 3 characters after removing spaces)
    if (strlen(str_replace(' ', '', $username)) < 3) {
        return "Username is too short! Minimum 3 characters required.";
    }
    
    // Check if it's too long
    if (strlen($username) > 50) {
        return "Username is too long! Maximum 50 characters allowed.";
    }
    
    // Check for invalid characters (allow letters, numbers, underscore, and hyphen)
    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $username)) {
        return "Username contains invalid characters! Only letters, numbers, underscore (_) and hyphen (-) are allowed. No spaces.";
    }
    
    // Check if it starts with a letter
    if (!preg_match('/^[A-Za-z]/', $username)) {
        return "Username must start with a letter!";
    }
    
    // Valid username
    return true;
}

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
        
        // Validate username first
        $username_validation = validateUsername($username);
        if ($username_validation !== true) {
            $message = $username_validation;
            $message_type = 'error';
        }
        // Email validation
        elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address!";
            $message_type = 'error';
        } else {
            // Check if username already exists (excluding current student)
            $username_check = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $username_check->execute([$username, $student_id]);
            if($username_check->fetch()){
                $message = "Username is already taken. Please choose a different username.";
                $message_type = 'error';
            }
            // Check if email already exists (excluding current student)
            elseif($pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?")->execute([$email, $student_id]) && 
                  $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?")->fetch()){
                $message = "Email address is already in use. Please use a different email.";
                $message_type = 'error';
            } else {
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
                
                // Update user in database if no errors
                if($message_type !== 'error') {
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
                }
            }
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
        } elseif(strlen($new_password) < 8) {
            $message = "New password must be at least 8 characters long.";
            $message_type = 'error';
        } elseif(!preg_match('/[A-Z]/', $new_password)) {
            $message = "New password must contain at least one uppercase letter.";
            $message_type = 'error';
        } elseif(!preg_match('/[a-z]/', $new_password)) {
            $message = "New password must contain at least one lowercase letter.";
            $message_type = 'error';
        } elseif(!preg_match('/[0-9]/', $new_password)) {
            $message = "New password must contain at least one number.";
            $message_type = 'error';
        } elseif($new_password === $current_password) {
            $message = "New password must be different from current password.";
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
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<title>Edit Profile | Student Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Include Dark Mode CSS -->
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
* { box-sizing: border-box; margin:0; padding:0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

/* ================= University Header ================= */
.university-header {
    background: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
    color: white;
    padding: 0.5rem 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1201;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.dku-logo-img {
    width: 45px;
    height: 45px;
    object-fit: contain;
    border-radius: 5px;
    background: white;
    padding: 4px;
}

.system-title {
    font-size: 0.9rem;
    font-weight: 600;
    opacity: 0.95;
}

.header-right {
    font-size: 0.8rem;
    opacity: 0.9;
}

@media (max-width: 768px) {
    .university-header {
        padding: 0.5rem 15px;
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }
    
    .header-left, .header-right {
        width: 100%;
        justify-content: center;
    }
    
    .system-title {
        font-size: 0.8rem;
    }
    
    .header-right {
        font-size: 0.75rem;
    }
}

/* Adjust other elements for university header */
.topbar {
    top: 60px !important; /* Adjusted for university header */
}

.sidebar {
    top: 60px !important; /* Adjusted for university header */
    height: calc(100% - 60px) !important;
}

.overlay {
    top: 60px; /* Adjusted for university header */
    height: calc(100% - 60px);
}

.main-content {
    margin-top: 60px; /* Added for university header */
}

/* ================= Topbar for Hamburger ================= */
.topbar {
    display: none;
    position: fixed; 
    top:60px; 
    left:0; 
    width:100%;
    background:var(--bg-sidebar); 
    color:var(--text-sidebar);
    padding:12px 20px;
    z-index:1200;
    justify-content:space-between; 
    align-items:center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.menu-btn {
    font-size:26px;
    background:#1abc9c;
    border:none; 
    color:var(--text-sidebar);
    cursor:pointer;
    padding:8px 12px;
    border-radius:8px;
    font-weight:600;
    transition: background 0.3s, transform 0.2s;
}
.menu-btn:hover { 
    background:#159b81; 
    transform:translateY(-2px); 
}

/* ================= Sidebar ================= */
.sidebar {
    position: fixed; 
    top:60px; 
    left:0;
    width:250px; 
    height:calc(100% - 60px);
    background:var(--bg-sidebar); 
    color:var(--text-sidebar);
    z-index:1100;
    transition: transform 0.3s ease;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.sidebar.hidden { 
    transform:translateX(-260px); 
}

/* Sidebar Content (scrollable) */
.sidebar-content {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 20px 0;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
}

/* Custom scrollbar for sidebar */
.sidebar-content::-webkit-scrollbar {
    width: 6px;
}

.sidebar-content::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 3px;
}

.sidebar-content::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

.sidebar-content::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

[data-theme="dark"] .sidebar-content::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
}

[data-theme="dark"] .sidebar-content::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Sidebar Profile */
.sidebar-profile {
    text-align: center;
    margin-bottom: 25px;
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    flex-shrink: 0;
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

/* Sidebar Title */
.sidebar h2 {
    text-align: center;
    color: var(--text-sidebar);
    margin-bottom: 25px;
    font-size: 22px;
    padding: 0 20px;
}

/* Sidebar Navigation */
.sidebar nav {
    display: flex;
    flex-direction: column;
}

.sidebar a { 
    display: flex; 
    align-items: center;
    gap: 10px;
    padding: 12px 20px; 
    color: var(--text-sidebar); 
    text-decoration: none; 
    transition: all 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.sidebar a:hover, .sidebar a.active { 
    background: #1abc9c; 
    color: white; 
    padding-left: 25px;
}

.sidebar a i {
    width: 20px;
    text-align: center;
    font-size: 1.1rem;
}

/* ================= Overlay ================= */
.overlay {
    position: fixed; 
    top:60px; 
    left:0; 
    width:100%; 
    height:calc(100% - 60px);
    background: rgba(0,0,0,0.4); 
    z-index:1050;
    display:none; 
    opacity:0; 
    transition: opacity 0.3s ease;
}
.overlay.active { 
    display:block; 
    opacity:1; 
}

/* ================= Main content ================= */
.main-content {
    margin-left: 250px;
    padding:20px;
    min-height:100vh;
    background: var(--bg-primary);
    transition: all 0.3s ease;
    margin-top: 60px;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 15px;
        padding-top: 140px; /* Adjusted for headers on mobile */
        margin-top: 120px; /* 60px header + 60px topbar */
    }
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
    background: var(--bg-card);
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.form-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 15px var(--shadow-lg);
}

.form-card h2 {
    font-size: 1.5rem;
    color: var(--text-primary);
    margin-bottom: 25px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border-color);
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

.btn-submit:disabled {
    background: #6b7280 !important;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

/* Password Requirements */
.password-requirements {
    background: var(--bg-secondary);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #3b82f6;
}

.password-requirements h4 {
    font-size: 0.95rem;
    color: var(--text-primary);
    margin-bottom: 8px;
    font-weight: 600;
}

.password-requirements ul {
    margin: 0;
    padding-left: 20px;
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.password-requirements li {
    margin-bottom: 5px;
    line-height: 1.4;
}

/* ================= Validation Styles ================= */
/* Username validation */
.username-feedback {
    font-size: 0.875rem;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

.username-success {
    color: #10b981;
}

.username-error {
    color: #dc2626;
}

/* Password strength meter */
.password-strength-container {
    margin-top: 5px;
}

.password-strength-bar {
    height: 6px;
    border-radius: 3px;
    margin-top: 8px;
    background: var(--border-color);
    overflow: hidden;
    position: relative;
}

.strength-fill {
    height: 100%;
    width: 0%;
    transition: all 0.3s ease;
    border-radius: 3px;
}

.password-strength-text {
    font-size: 0.85rem;
    margin-top: 5px;
    font-weight: 500;
}

/* Password strength colors */
.strength-0 { background: #dc2626; } /* Very weak */
.strength-1 { background: #ef4444; } /* Weak */
.strength-2 { background: #f59e0b; } /* Fair */
.strength-3 { background: #10b981; } /* Good */
.strength-4 { background: #059669; } /* Strong */

/* Password requirements list */
.password-requirements-list {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-top: 5px;
    line-height: 1.4;
}

.password-requirements-list ul {
    padding-left: 20px;
    margin: 5px 0;
}

.password-requirements-list li {
    margin-bottom: 3px;
}

.password-requirements-list li.valid {
    color: #10b981;
}

.password-requirements-list li.invalid {
    color: #dc2626;
}

.password-requirements-list li i {
    margin-right: 5px;
}

/* Email validation */
.email-validation {
    font-size: 0.85rem;
    margin-top: 5px;
    font-weight: 500;
}

.email-valid {
    color: #10b981;
}

.email-invalid {
    color: #dc2626;
}

/* Input Validation States */
input.valid {
    border-color: #10b981 !important;
    background: linear-gradient(90deg, var(--bg-secondary), #d1fae5) !important;
}

input.invalid {
    border-color: #dc2626 !important;
    background: linear-gradient(90deg, var(--bg-secondary), #fee2e2) !important;
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
    background: var(--success-bg);
    color: var(--success-text);
}

.password-match.invalid {
    background: var(--error-bg);
    color: var(--error-text);
}

/* ================= End Validation Styles ================= */

/* Form Tips */
.form-tip {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 5px;
    font-style: italic;
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

[data-theme="dark"] .form-card h2 {
    border-bottom-color: var(--border-color);
}

[data-theme="dark"] .password-requirements {
    border-left-color: #3b82f6;
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
    border-left: 4px solid var(--success-border);
}

.message.error {
    background: linear-gradient(135deg, var(--error-bg), #fecaca);
    color: var(--error-text);
    border-left: 4px solid var(--error-border);
}

.message.warning {
    background: linear-gradient(135deg, var(--warning-bg), #fde68a);
    color: var(--warning-text);
    border-left: 4px solid var(--warning-border);
}

.message i {
    font-size: 1.2rem;
}

/* Password container with toggle */
.password-container {
    position: relative;
    width: 100%;
}

.password-container input {
    width: 100%;
    padding-right: 40px !important;
}

.toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-secondary);
    font-size: 1rem;
    padding: 5px;
    border-radius: 4px;
    transition: all 0.3s;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.toggle-password:hover {
    color: #2563eb;
    background: var(--hover-color);
}

.toggle-password:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.3);
}

/* ================= Responsive ================= */
@media (max-width: 768px) {
    .university-header {
        padding: 0.5rem 15px;
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }
    
    .header-left, .header-right {
        width: 100%;
        justify-content: center;
    }
    
    .system-title {
        font-size: 0.8rem;
    }
    
    .header-right {
        font-size: 0.75rem;
    }
    
    .topbar { 
        display:flex;
        top: 60px; /* Adjusted for mobile with header */
    }
    
    .sidebar { 
        transform:translateX(-100%); 
        top: 120px; /* 60px header + 60px topbar */
        height: calc(100% - 120px) !important;
    }
    
    .sidebar.active { 
        transform:translateX(0); 
    }
    
    .overlay {
        top: 120px;
        height: calc(100% - 120px);
    }
    
    .main-content {
        padding-top: 140px; /* Adjusted for headers on mobile */
        margin-top: 120px; /* 60px header + 60px topbar */
    }
    
    .header { 
        flex-direction: column; 
        gap: 15px; 
        align-items: flex-start; 
    }
    
    .header h1 { 
        font-size: 1.8rem; 
    }
    
    .forms-section { 
        gap: 20px; 
    }
    
    .form-card { 
        padding: 20px; 
    }
    
    .current-profile-pic { 
        width: 120px; 
        height: 120px; 
    }
}

/* Improved sidebar icons */
.sidebar a {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar a i {
    width: 20px;
    text-align: center;
    font-size: 1.1rem;
}
</style>
</head>
<body>
    <!-- University Header -->
    <div class="university-header">
        <div class="header-left">
            <img src="../assets/images/dku logo.jpg" alt="Debark University Logo" class="dku-logo-img">
            <div class="system-title">Debark University Class Scheduling System</div>
        </div>
        <div class="header-right">
            Edit Profile
        </div>
    </div>

    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Edit Profile</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <div class="sidebar-profile">
                <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic"
                     onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                <p><?= htmlspecialchars($user['username'] ?? 'Student') ?></p>
            </div>
            
            <h2>Student Dashboard</h2>
            
            <nav>
                <a href="student_dashboard.php" class="<?= $current_page=='student_dashboard.php'?'active':'' ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="my_schedule.php" class="<?= $current_page=='my_schedule.php'?'active':'' ?>">
                    <i class="fas fa-calendar-alt"></i> My Schedule
                </a>
                <a href="view_exam_schedules.php" class="<?= $current_page=='view_exam_schedules.php'?'active':'' ?>">
                    <i class="fas fa-clipboard-list"></i> Exam Schedule
                </a>
                <a href="view_announcements.php" class="<?= $current_page=='view_announcements.php'?'active':'' ?>">
                    <i class="fas fa-bullhorn"></i> Announcements
                </a>
                <a href="edit_profile.php" class="active">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </a>
                <a href="../logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </div>
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
                    <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile" id="headerProfilePic"
                         onerror="this.onerror=null; this.src='../assets/default_profile.png';">
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
                                   value="<?= htmlspecialchars($user['username'] ?? '') ?>" 
                                   required 
                                   pattern="[A-Za-z][A-Za-z0-9_-]{2,49}"
                                   title="Username must start with a letter, be 3-50 characters long, and can only contain letters, numbers, underscore (_) and hyphen (-)."
                                   oninput="validateUsername()">
                            <div class="username-feedback" id="username-feedback"></div>
                            <div class="form-tip">Start with a letter • 3-50 characters • Letters, numbers, _, - only</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" required oninput="validateEmail()">
                            <div class="email-validation" id="email-validation"></div>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn-submit" id="profileSubmitBtn">
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
                            <li>At least 8 characters long</li>
                            <li>At least one uppercase letter</li>
                            <li>At least one lowercase letter</li>
                            <li>At least one number</li>
                            <li>Should be different from your current password</li>
                        </ul>
                    </div>
                    
                    <form method="post" id="passwordForm">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <div class="password-container">
                                <input type="password" id="current_password" name="current_password" 
                                       class="form-control" required placeholder="Enter your current password">
                                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('current_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="password-container">
                                <input type="password" id="new_password" name="new_password" 
                                       class="form-control" required placeholder="Enter new password" oninput="validatePassword()">
                                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('new_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            
                            <!-- Password strength meter -->
                            <div class="password-strength-container" id="password-strength-container">
                                <div class="password-strength-bar">
                                    <div class="strength-fill" id="strength-fill"></div>
                                </div>
                                <div class="password-strength-text" id="password-strength-text"></div>
                                
                                <!-- Password requirements list -->
                                <div class="password-requirements-list" id="password-requirements">
                                    <p>Password must contain:</p>
                                    <ul>
                                        <li id="req-length" class="invalid"><i class="fas fa-times"></i> At least 8 characters</li>
                                        <li id="req-uppercase" class="invalid"><i class="fas fa-times"></i> At least one uppercase letter</li>
                                        <li id="req-lowercase" class="invalid"><i class="fas fa-times"></i> At least one lowercase letter</li>
                                        <li id="req-number" class="invalid"><i class="fas fa-times"></i> At least one number</li>
                                        <li id="req-special" class="invalid"><i class="fas fa-times"></i> At least one special character (optional)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="password-container">
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="form-control" required placeholder="Confirm new password" oninput="validatePasswordMatch()">
                                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-match" id="passwordMatch"></div>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn-submit" id="passwordSubmitBtn">
                            <i class="fas fa-key"></i> Change Password
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
        
        // Add animation to form cards
        const formCards = document.querySelectorAll('.form-card');
        formCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 200);
        });
        
        // Debug: Log profile picture paths
        console.log('Sidebar profile pic src:', document.getElementById('sidebarProfilePic').src);
        console.log('Header profile pic src:', document.getElementById('headerProfilePic').src);
        console.log('Profile preview src:', document.getElementById('profilePreview').src);
        
        // Initialize validation
        validateUsername();
        validateEmail();
        validatePassword();
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

    // Toggle password visibility
    function togglePasswordVisibility(fieldId, button) {
        const input = document.getElementById(fieldId);
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
            button.setAttribute('aria-label', 'Hide password');
            button.title = 'Hide password';
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
            button.setAttribute('aria-label', 'Show password');
            button.title = 'Show password';
        }
    }

    // Username validation
    function validateUsername() {
        const usernameInput = document.getElementById('username');
        const username = usernameInput.value.trim();
        const feedback = document.getElementById('username-feedback');
        
        usernameInput.classList.remove('valid', 'invalid');
        feedback.innerHTML = '';
        
        if (!username) {
            updateProfileSubmitButton();
            return;
        }
        
        // Check if it contains only numbers
        if (/^[0-9\s]+$/.test(username)) {
            usernameInput.classList.add('invalid');
            feedback.innerHTML = '<span class="username-error"><i class="fas fa-exclamation-circle"></i> Username cannot contain only numbers!</span>';
            updateProfileSubmitButton();
            return;
        }
        
        // Check length (minimum 3 characters after removing spaces)
        if (username.replace(/\s+/g, '').length < 3) {
            usernameInput.classList.add('invalid');
            feedback.innerHTML = '<span class="username-error"><i class="fas fa-exclamation-circle"></i> Username is too short! Minimum 3 characters required.</span>';
            updateProfileSubmitButton();
            return;
        }
        
        // Check maximum length
        if (username.length > 50) {
            usernameInput.classList.add('invalid');
            feedback.innerHTML = '<span class="username-error"><i class="fas fa-exclamation-circle"></i> Username is too long! Maximum 50 characters allowed.</span>';
            updateProfileSubmitButton();
            return;
        }
        
        // Check for invalid characters
        if (!/^[A-Za-z0-9_\-]+$/.test(username)) {
            usernameInput.classList.add('invalid');
            feedback.innerHTML = '<span class="username-error"><i class="fas fa-exclamation-circle"></i> Invalid characters! Only letters, numbers, underscore (_) and hyphen (-) are allowed.</span>';
            updateProfileSubmitButton();
            return;
        }
        
        // Check if it starts with a letter
        if (!/^[A-Za-z]/.test(username)) {
            usernameInput.classList.add('invalid');
            feedback.innerHTML = '<span class="username-error"><i class="fas fa-exclamation-circle"></i> Username must start with a letter!</span>';
            updateProfileSubmitButton();
            return;
        }
        
        // Valid username
        usernameInput.classList.add('valid');
        feedback.innerHTML = '<span class="username-success"><i class="fas fa-check-circle"></i> Valid username</span>';
        updateProfileSubmitButton();
    }

    // Email validation
    function validateEmail() {
        const emailInput = document.getElementById('email');
        const emailValidation = document.getElementById('email-validation');
        const email = emailInput.value.trim();
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        
        emailInput.classList.remove('valid', 'invalid');
        
        if (!email) {
            emailValidation.innerHTML = '';
            updateProfileSubmitButton();
            return;
        }
        
        if (!emailRegex.test(email)) {
            emailInput.classList.add('invalid');
            emailValidation.innerHTML = '<span class="email-invalid"><i class="fas fa-exclamation-circle"></i> Please enter a valid email address</span>';
        } else {
            emailInput.classList.add('valid');
            emailValidation.innerHTML = '<span class="email-valid"><i class="fas fa-check-circle"></i> Valid email format</span>';
        }
        
        updateProfileSubmitButton();
    }

    // Password strength validation
    function validatePassword() {
        const passwordInput = document.getElementById('new_password');
        const password = passwordInput.value;
        
        // Calculate password strength
        let strength = 0;
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
        };
        
        // Update requirement indicators
        document.getElementById('req-length').className = requirements.length ? 'valid' : 'invalid';
        document.getElementById('req-length').innerHTML = (requirements.length ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>') + ' At least 8 characters';
        
        document.getElementById('req-uppercase').className = requirements.uppercase ? 'valid' : 'invalid';
        document.getElementById('req-uppercase').innerHTML = (requirements.uppercase ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>') + ' At least one uppercase letter';
        
        document.getElementById('req-lowercase').className = requirements.lowercase ? 'valid' : 'invalid';
        document.getElementById('req-lowercase').innerHTML = (requirements.lowercase ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>') + ' At least one lowercase letter';
        
        document.getElementById('req-number').className = requirements.number ? 'valid' : 'invalid';
        document.getElementById('req-number').innerHTML = (requirements.number ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>') + ' At least one number';
        
        document.getElementById('req-special').className = requirements.special ? 'valid' : 'invalid';
        document.getElementById('req-special').innerHTML = (requirements.special ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>') + ' At least one special character (optional)';
        
        // Calculate strength score
        if (requirements.length) strength++;
        if (requirements.uppercase) strength++;
        if (requirements.lowercase) strength++;
        if (requirements.number) strength++;
        if (requirements.special) strength++;
        
        // Update strength meter
        const strengthPercent = (strength / 5) * 100;
        const strengthFill = document.getElementById('strength-fill');
        strengthFill.className = 'strength-fill strength-' + strength;
        strengthFill.style.width = strengthPercent + '%';
        
        // Update strength text
        const strengthTexts = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        const strengthColors = ['#dc2626', '#ef4444', '#f59e0b', '#10b981', '#059669'];
        const passwordStrengthText = document.getElementById('password-strength-text');
        passwordStrengthText.textContent = 'Password Strength: ' + strengthTexts[strength];
        passwordStrengthText.style.color = strengthColors[strength];
        
        // Update input border color based on strength
        passwordInput.classList.remove('valid', 'invalid');
        if (password.length === 0) {
            // Do nothing
        } else if (strength < 3) {
            passwordInput.classList.add('invalid');
        } else {
            passwordInput.classList.add('valid');
        }
        
        // Validate password match
        validatePasswordMatch();
        
        updatePasswordSubmitButton();
    }

    // Password confirmation validation
    function validatePasswordMatch() {
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordMatch = document.getElementById('passwordMatch');
        
        if (newPassword.value === '' || confirmPassword.value === '') {
            passwordMatch.textContent = '';
            passwordMatch.className = 'password-match';
            updatePasswordSubmitButton();
            return;
        }
        
        if (newPassword.value === confirmPassword.value) {
            passwordMatch.textContent = '✓ Passwords match';
            passwordMatch.className = 'password-match valid';
            confirmPassword.classList.remove('invalid');
            confirmPassword.classList.add('valid');
        } else {
            passwordMatch.textContent = '✗ Passwords do not match';
            passwordMatch.className = 'password-match invalid';
            confirmPassword.classList.remove('valid');
            confirmPassword.classList.add('invalid');
        }
        
        updatePasswordSubmitButton();
    }

    // Update profile submit button state
    function updateProfileSubmitButton() {
        const usernameInput = document.getElementById('username');
        const emailInput = document.getElementById('email');
        const submitBtn = document.getElementById('profileSubmitBtn');
        
        const username = usernameInput.value.trim();
        const email = emailInput.value.trim();
        
        // Validate username
        if (!username) {
            submitBtn.disabled = true;
            return;
        }
        
        if (/^[0-9\s]+$/.test(username)) {
            submitBtn.disabled = true;
            return;
        }
        
        if (username.replace(/\s+/g, '').length < 3) {
            submitBtn.disabled = true;
            return;
        }
        
        if (username.length > 50) {
            submitBtn.disabled = true;
            return;
        }
        
        if (!/^[A-Za-z0-9_\-]+$/.test(username)) {
            submitBtn.disabled = true;
            return;
        }
        
        if (!/^[A-Za-z]/.test(username)) {
            submitBtn.disabled = true;
            return;
        }
        
        // Validate email
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        const emailValid = emailRegex.test(email);
        
        // Enable button only if both are valid
        submitBtn.disabled = !(username && emailValid);
    }

    // Update password submit button state
    function updatePasswordSubmitButton() {
        const currentPassword = document.getElementById('current_password');
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('passwordSubmitBtn');
        
        // Check if all fields are filled
        const allFilled = currentPassword.value.trim() !== '' && 
                         newPassword.value.trim() !== '' && 
                         confirmPassword.value.trim() !== '';
        
        // Check password strength
        const password = newPassword.value;
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password)
        };
        const passwordValid = requirements.length && requirements.uppercase && 
                            requirements.lowercase && requirements.number;
        
        // Check if passwords match
        const passwordsMatch = newPassword.value === confirmPassword.value;
        
        // Enable button only if all conditions are met
        submitBtn.disabled = !(allFilled && passwordValid && passwordsMatch);
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
        
        // Username validation
        if (/^[0-9\s]+$/.test(username)) {
            e.preventDefault();
            alert('Username cannot contain only numbers! Please use letters or a combination of letters and numbers.');
            return false;
        }
        
        if (username.replace(/\s+/g, '').length < 3) {
            e.preventDefault();
            alert('Username is too short! Minimum 3 characters required.');
            return false;
        }
        
        if (username.length > 50) {
            e.preventDefault();
            alert('Username is too long! Maximum 50 characters allowed.');
            return false;
        }
        
        if (!/^[A-Za-z0-9_\-]+$/.test(username)) {
            e.preventDefault();
            alert('Username contains invalid characters! Only letters, numbers, underscore (_) and hyphen (-) are allowed.');
            return false;
        }
        
        if (!/^[A-Za-z]/.test(username)) {
            e.preventDefault();
            alert('Username must start with a letter!');
            return false;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address');
            return false;
        }
        
        return true;
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
        
        if (newPass.length < 8) {
            e.preventDefault();
            alert('New password must be at least 8 characters long');
            return false;
        }
        
        if (!/[A-Z]/.test(newPass)) {
            e.preventDefault();
            alert('New password must contain at least one uppercase letter');
            return false;
        }
        
        if (!/[a-z]/.test(newPass)) {
            e.preventDefault();
            alert('New password must contain at least one lowercase letter');
            return false;
        }
        
        if (!/[0-9]/.test(newPass)) {
            e.preventDefault();
            alert('New password must contain at least one number');
            return false;
        }
        
        // Check if new password is same as current
        if (newPass === currentPass) {
            e.preventDefault();
            alert('New password must be different from current password');
            return false;
        }
        
        return true;
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
    
    // Add event listeners for real-time validation
    document.getElementById('email').addEventListener('input', validateEmail);
    document.getElementById('username').addEventListener('input', validateUsername);
    document.getElementById('current_password').addEventListener('input', updatePasswordSubmitButton);
    document.getElementById('new_password').addEventListener('input', function() {
        validatePassword();
        validatePasswordMatch();
    });
    document.getElementById('confirm_password').addEventListener('input', function() {
        validatePasswordMatch();
    });
    </script>
</body>
</html>