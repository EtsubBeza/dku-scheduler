<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Redirect if not logged in
if(!isset($_SESSION['user_id'])){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = ""; // success, error, warning

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Function to get profile picture path
function getProfilePicturePath($profile_picture) {
    if (empty($profile_picture)) {
        return '../assets/default_profile.png';
    }
    
    // Try multiple possible locations
    $locations = [
        __DIR__ . '/../../uploads/profiles/' . $profile_picture,
        __DIR__ . '/../uploads/' . $profile_picture,
        'uploads/profiles/' . $profile_picture,
        '../uploads/profiles/' . $profile_picture,
    ];
    
    foreach ($locations as $location) {
        if (file_exists($location)) {
            // Return the appropriate web path
            if (strpos($location, '../../uploads/profiles/') !== false) {
                return '../../uploads/profiles/' . $profile_picture;
            } elseif (strpos($location, '../uploads/') !== false) {
                return '../uploads/profiles/' . $profile_picture;
            } elseif (strpos($location, 'uploads/') !== false) {
                return 'uploads/profiles/' . $profile_picture;
            }
        }
    }
    
    // If file doesn't exist anywhere, return default
    return '../assets/default_profile.png';
}

// Function to validate username - ONLY check it's not all numbers
function validateUsername($username) {
    $username = trim($username);
    
    // Check if empty
    if (empty($username)) {
        return ["isValid" => false, "message" => "Username cannot be empty."];
    }
    
    // Check length
    if (strlen($username) < 3) {
        return ["isValid" => false, "message" => "Username must be at least 3 characters long."];
    }
    
    if (strlen($username) > 50) {
        return ["isValid" => false, "message" => "Username cannot exceed 50 characters."];
    }
    
    // ONLY RESTRICTION: Check if it's only numbers
    if (preg_match('/^\d+$/', $username)) {
        return ["isValid" => false, "message" => "Username cannot consist only of numbers."];
    }
    
    // That's it! Any other combination is allowed
    return ["isValid" => true, "message" => "Valid username."];
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    // Check which form was submitted
    if(isset($_POST['update_profile'])){
        // Profile update form
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $fileName = $user['profile_picture'] ?? ''; // Keep existing by default
        
        // Validate username
        $usernameValidation = validateUsername($username);
        if (!$usernameValidation['isValid']) {
            $message = "Error: " . $usernameValidation['message'];
            $message_type = 'error';
        }
        // Email validation
        else if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address!";
            $message_type = 'error';
        } else {
            // Profile picture upload
            if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../../uploads/profiles/';
                
                // Create uploads directory if it doesn't exist
                if(!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Get file info
                $file_name = $_FILES['profile_picture']['name'];
                $file_tmp = $_FILES['profile_picture']['tmp_name'];
                $file_size = $_FILES['profile_picture']['size'];
                
                // Validate file size (5MB = 5242880 bytes)
                if ($file_size > 5242880) {
                    $message = "File is too large. Maximum size is 5MB.";
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
            if($update->execute([$username, $email, $fileName, $user_id])) {
                $message = "Profile updated successfully!";
                $message_type = 'success';
                
                // Update user array with new data
                $user['username'] = $username;
                $user['email'] = $email;
                $user['profile_picture'] = $fileName;
                
                // Refresh user data
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $message = "Error updating profile. Please try again.";
                $message_type = 'error';
            }
        }
        
    } elseif(isset($_POST['change_password'])) {
        // Password change form
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Fetch current password hash
        $password_stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $password_stmt->execute([$user_id]);
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
            
            if($update_password->execute([$new_password_hash, $user_id])) {
                $message = "Password changed successfully!";
                $message_type = 'success';
            } else {
                $message = "Error changing password. Please try again.";
                $message_type = 'error';
            }
        }
    }
}

// Get profile image path for sidebar
$profile_src = getProfilePicturePath($user['profile_picture'] ?? '');

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $darkMode ? 'dark' : 'light'; ?>">
<head>
<meta charset="UTF-8">
<title>Edit Profile</title>
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
    padding:15px 20px;
    z-index:1200;
    justify-content:space-between; 
    align-items:center;
}
.menu-btn {
    font-size:26px;
    background:#1abc9c;
    border:none; 
    color:var(--text-sidebar);
    cursor:pointer;
    padding:10px 14px;
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
    padding: 20px 0;
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
    padding:30px 50px;
    min-height:100vh;
    background:var(--bg-primary);
    color:var(--text-primary);
    transition: all 0.3s ease;
    margin-top: 60px; /* Added for university header */
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 20px;
        padding-top: 140px; /* Adjusted for headers on mobile */
        margin-top: 120px; /* 60px header + 60px topbar */
    }
}

/* Header Styles */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px 0;
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
    background: var(--bg-card);
    padding: 12px 18px;
    border-radius: 12px;
    box-shadow: 0 4px 12px var(--shadow-color);
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

/* Forms Section */
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

.profile-pic {
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
    background: #6b7280;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
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
.username-validation {
    font-size: 0.85rem;
    margin-top: 5px;
    font-weight: 500;
    padding: 5px;
    border-radius: 4px;
}

.username-valid {
    background: var(--success-bg);
    color: var(--success-text);
    border: 1px solid var(--success-border);
}

.username-invalid {
    background: var(--error-bg);
    color: var(--error-text);
    border: 1px solid var(--error-border);
}

/* Username requirements list */
.username-requirements-list {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-top: 5px;
    line-height: 1.4;
}

.username-requirements-list ul {
    padding-left: 20px;
    margin: 5px 0;
}

.username-requirements-list li {
    margin-bottom: 3px;
}

.username-requirements-list li.valid {
    color: #10b981;
}

.username-requirements-list li.invalid {
    color: #dc2626;
}

.username-requirements-list li i {
    margin-right: 5px;
    font-size: 0.75rem;
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
    padding: 5px;
    border-radius: 4px;
}

.email-valid {
    background: var(--success-bg);
    color: var(--success-text);
    border: 1px solid var(--success-border);
}

.email-invalid {
    background: var(--error-bg);
    color: var(--error-text);
    border: 1px solid var(--error-border);
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
    border: 1px solid var(--success-border);
}

.password-match.invalid {
    background: var(--error-bg);
    color: var(--error-text);
    border: 1px solid var(--error-border);
}

/* ================= End Validation Styles ================= */

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
@media(max-width: 768px){
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
    
    .profile-pic { 
        width: 120px; 
        height: 120px; 
    }
}

/* Dark mode specific adjustments */
[data-theme="dark"] .user-info {
    background: var(--bg-secondary);
}

[data-theme="dark"] .form-control {
    background: var(--bg-input);
    color: var(--text-primary);
}

[data-theme="dark"] .form-control::placeholder {
    color: var(--text-secondary);
}

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

[data-theme="dark"] .form-card h2 {
    border-bottom-color: var(--border-color);
}

[data-theme="dark"] .password-requirements {
    border-left-color: #3b82f6;
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
        <div class="sidebar-content" id="sidebarContent">
            <div class="sidebar-profile">
                <img src="<?php echo htmlspecialchars($profile_src); ?>" alt="Profile Picture" id="sidebarProfilePic"
                     onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                <p><?php echo htmlspecialchars($user['username'] ?? 'User'); ?></p>
            </div>
            <nav>
                <a href="departmenthead_dashboard.php" class="<?php echo $current_page=='departmenthead_dashboard.php'?'active':''; ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="manage_enrollments.php" class="<?php echo $current_page=='manage_enrollments.php'?'active':''; ?>">
                    <i class="fas fa-users"></i> Manage Enrollments
                </a>
                <a href="manage_schedules.php" class="<?php echo $current_page=='manage_schedules.php'?'active':'' ?>">
                    <i class="fas fa-calendar-alt"></i> Manage Schedules
                </a>
                <a href="assign_courses.php" class="<?php echo $current_page=='assign_courses.php'?'active':'' ?>">
                    <i class="fas fa-chalkboard-teacher"></i> Assign Courses
                </a>
                <a href="add_courses.php" class="<?php echo $current_page=='add_courses.php'?'active':'' ?>">
                    <i class="fas fa-book"></i> Add Courses
                </a>
                <a href="exam_schedules.php" class="<?php echo $current_page=='exam_schedules.php'?'active':'' ?>">
                    <i class="fas fa-clipboard-list"></i> Exam Schedules
                </a>
                <a href="edit_profile.php" class="<?php echo $current_page=='edit_profile.php'?'active':'' ?>">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </a>
                <a href="manage_announcements.php" class="<?php echo $current_page=='manage_announcements.php'?'active':'' ?>">
                    <i class="fas fa-bullhorn"></i> Announcements
                </a>
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Edit Profile</h1>
            <div class="user-info">
                <img src="<?php echo htmlspecialchars($profile_src); ?>" alt="Profile" id="headerProfilePic"
                     onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                <div>
                    <div><?php echo htmlspecialchars($user['username'] ?? 'User'); ?></div>
                    <small>Department Head</small>
                </div>
            </div>
        </div>

        <?php if($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
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
                        $profile_pic_path = getProfilePicturePath($user['profile_picture'] ?? '');
                        ?>
                        <img src="<?php echo htmlspecialchars($profile_pic_path); ?>" 
                             alt="Current Profile Picture" 
                             class="profile-pic" 
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
                            <div class="form-tip">Max file size: 5MB • Supported: JPG, PNG, GIF, WebP</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required oninput="validateUsername()">
                        <div class="username-validation" id="username-validation"></div>
                        
                        <!-- Username requirements list -->
                        <div class="username-requirements-list" id="username-requirements">
                            <p>Username requirements:</p>
                            <ul>
                                <li id="username-req-length" class="<?php echo (!empty($user['username']) && strlen($user['username']) >= 3) ? 'valid' : 'invalid'; ?>">
                                    <i class="fas fa-<?php echo (!empty($user['username']) && strlen($user['username']) >= 3) ? 'check' : 'times'; ?>"></i> 
                                    3-50 characters
                                </li>
                                <li id="username-req-not-only-numbers" class="<?php echo (!empty($user['username']) && !preg_match('/^\d+$/', $user['username'])) ? 'valid' : 'invalid'; ?>">
                                    <i class="fas fa-<?php echo (!empty($user['username']) && !preg_match('/^\d+$/', $user['username'])) ? 'check' : 'times'; ?>"></i> 
                                    Cannot be only numbers
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required oninput="validateEmail()">
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
        
        // Initialize validation
        validateUsername();
        validateEmail();
        validatePassword();
    });

    // Username validation - ONLY checks it's not all numbers
    function validateUsername() {
        const usernameInput = document.getElementById('username');
        const usernameValidation = document.getElementById('username-validation');
        const username = usernameInput.value.trim();
        
        // Update requirement indicators
        updateUsernameRequirements(username);
        
        usernameInput.classList.remove('valid', 'invalid');
        usernameValidation.innerHTML = '';
        
        if (!username) {
            usernameValidation.innerHTML = '';
            usernameInput.classList.remove('valid', 'invalid');
            updateProfileSubmitButton();
            return false;
        }
        
        // Check length
        if (username.length < 3 || username.length > 50) {
            usernameInput.classList.add('invalid');
            usernameValidation.innerHTML = '<span class="username-invalid"><i class="fas fa-exclamation-circle"></i> Username must be 3-50 characters long</span>';
            updateProfileSubmitButton();
            return false;
        }
        
        // ONLY RESTRICTION: Check if it's only numbers
        if (/^\d+$/.test(username)) {
            usernameInput.classList.add('invalid');
            usernameValidation.innerHTML = '<span class="username-invalid"><i class="fas fa-exclamation-circle"></i> Username cannot consist only of numbers</span>';
            updateProfileSubmitButton();
            return false;
        }
        
        // That's it! Any other combination is valid
        usernameInput.classList.add('valid');
        usernameValidation.innerHTML = '<span class="username-valid"><i class="fas fa-check-circle"></i> Valid username</span>';
        updateProfileSubmitButton();
        return true;
    }
    
    // Update username requirement indicators
    function updateUsernameRequirements(username) {
        // Length requirement
        const lengthValid = username.length >= 3 && username.length <= 50;
        document.getElementById('username-req-length').className = lengthValid ? 'valid' : 'invalid';
        document.getElementById('username-req-length').innerHTML = (lengthValid ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>') + ' 3-50 characters';
        
        // Not only numbers requirement
        const notOnlyNumbersValid = !/^\d+$/.test(username);
        document.getElementById('username-req-not-only-numbers').className = notOnlyNumbersValid ? 'valid' : 'invalid';
        document.getElementById('username-req-not-only-numbers').innerHTML = (notOnlyNumbersValid ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>') + ' Cannot be only numbers';
    }

    // Profile picture preview
    function previewProfilePicture(input) {
        const fileName = document.getElementById('fileName');
        const preview = document.getElementById('profilePreview');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            fileName.textContent = file.name;
            
            // Validate file size (5MB = 5 * 1024 * 1024 bytes)
            if (file.size > 5 * 1024 * 1024) {
                fileName.innerHTML = '<span style="color:#ef4444;">File too large! Max 5MB</span>';
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

    // Email validation
    function validateEmail() {
        const emailInput = document.getElementById('email');
        const emailValidation = document.getElementById('email-validation');
        const email = emailInput.value.trim();
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        
        emailInput.classList.remove('valid', 'invalid');
        
        if (!email) {
            emailValidation.innerHTML = '';
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
        const emailInput = document.getElementById('email');
        const usernameInput = document.getElementById('username');
        const submitBtn = document.getElementById('profileSubmitBtn');
        
        // Check if email is valid
        const email = emailInput.value.trim();
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        const emailValid = emailRegex.test(email);
        
        // Check if username is valid
        const usernameValid = validateUsername();
        
        // Enable button only if both are valid
        submitBtn.disabled = !(emailValid && usernameValid);
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
        if (!validateUsername()) {
            e.preventDefault();
            alert('Please fix the username errors before submitting.');
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