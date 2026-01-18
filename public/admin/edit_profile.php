<?php
// edit_profile.php for admin
session_start();
require __DIR__ . '/../../includes/db.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

$admin_id = $_SESSION['user_id'];

// Fetch current admin info
$user_stmt = $pdo->prepare("SELECT username, email, profile_picture FROM users WHERE user_id = ? AND role = 'admin'");
$user_stmt->execute([$admin_id]);
$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if(!$current_user){
    // Admin not found in database
    header("Location: logout.php");
    exit;
}

// Get pending approvals count
try {
    // Check if is_approved column exists
    $check_column = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_approved'");
    if($check_column->rowCount() > 0) {
        // Column exists, count unapproved users
        $pending_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND is_approved = 0");
        $pending_stmt->execute();
        $pending_result = $pending_stmt->fetch(PDO::FETCH_ASSOC);
        $pending_approvals = $pending_result['count'] ?? 0;
    } else {
        // Column doesn't exist, show count of all non-admin users
        $pending_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
        $pending_stmt->execute();
        $pending_result = $pending_stmt->fetch(PDO::FETCH_ASSOC);
        $pending_approvals = $pending_result['count'] ?? 0;
    }
} catch (Exception $e) {
    $pending_approvals = 0;
    error_log("Error fetching pending approvals: " . $e->getMessage());
}

// Function to get profile picture path for admin
function getAdminProfilePicturePath($profile_picture) {
    if (empty($profile_picture)) {
        return '../assets/default_profile.png';
    }
    
    // Check multiple possible locations for admin profile pictures
    $locations = [
        // Admin-specific uploads folder (preferred)
        __DIR__ . '/../uploads/admin/' . $profile_picture,
        // Fallback to main uploads
        __DIR__ . '/../uploads/' . $profile_picture,
        __DIR__ . '/../../uploads/' . $profile_picture,
        // Relative paths
        'uploads/admin/' . $profile_picture,
        '../uploads/admin/' . $profile_picture,
        'uploads/' . $profile_picture,
        '../uploads/' . $profile_picture,
    ];
    
    foreach ($locations as $location) {
        if (file_exists($location)) {
            // Return appropriate web path
            if (strpos($location, '/admin/') !== false) {
                return '../uploads/admin/' . $profile_picture;
            } elseif (strpos($location, 'uploads/admin/') !== false) {
                return 'uploads/admin/' . $profile_picture;
            } elseif (strpos($location, '../uploads/') !== false) {
                return '../uploads/' . $profile_picture;
            } elseif (strpos($location, 'uploads/') !== false) {
                return 'uploads/' . $profile_picture;
            }
        }
    }
    
    // If file doesn't exist, return default
    return '../assets/default_profile.png';
}

// Get profile image path
$profile_img_path = getAdminProfilePicturePath($current_user['profile_picture'] ?? '');

$message = "";
$message_type = "success"; // success, error, warning

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // Validation
    $errors = [];
    
    if(empty($username) || empty($email)) {
        $errors[] = "Please fill in all required fields.";
    }
    
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    // Check if email already exists (excluding current admin)
    $email_check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $email_check->execute([$email, $admin_id]);
    if($email_check->fetch()){
        $errors[] = "Email address is already in use.";
    }
    
    // Check if username already exists (excluding current admin)
    $username_check = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
    $username_check->execute([$username, $admin_id]);
    if($username_check->fetch()){
        $errors[] = "Username is already taken.";
    }
    
    // Handle password change if any password field is filled
    if(!empty($current_password) || !empty($new_password) || !empty($confirm_password)){
        // Verify current password
        $password_check = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $password_check->execute([$admin_id]);
        $db_password = $password_check->fetchColumn();
        
        if(empty($current_password)){
            $errors[] = "Current password is required to change password.";
        } elseif(!password_verify($current_password, $db_password)){
            $errors[] = "Current password is incorrect.";
        } elseif(empty($new_password)){
            $errors[] = "New password cannot be empty.";
        } elseif(strlen($new_password) < 8){
            $errors[] = "New password must be at least 8 characters long.";
        } elseif($new_password !== $confirm_password){
            $errors[] = "New passwords do not match.";
        }
    }
    
    // Handle profile picture upload
    $filename = $current_user['profile_picture'] ?? '';
    
    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error']===0 && empty($errors)){
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        
        // Validate file type
        if(!in_array($file_type, $allowed_types)) {
            $errors[] = "Invalid file type. Please upload JPEG, PNG, GIF, or WebP images only.";
        } elseif($file_size > 2 * 1024 * 1024) { // 2MB limit
            $errors[] = "File is too large. Maximum size is 2MB.";
        } else {
            $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            $filename = "admin_".$admin_id."_".time().".".$ext;
            $upload_dir = __DIR__."/../uploads/admin/";
            
            // Create admin uploads directory if it doesn't exist
            if(!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $upload_path = $upload_dir . $filename;
            
            if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                // Delete old profile picture if it exists and is not default
                if(!empty($current_user['profile_picture']) && 
                   !str_starts_with($current_user['profile_picture'], 'default') &&
                   file_exists(__DIR__."/../uploads/admin/".$current_user['profile_picture'])) {
                    unlink(__DIR__."/../uploads/admin/".$current_user['profile_picture']);
                }
            } else {
                $errors[] = "Error uploading profile picture. Please try again.";
                $filename = $current_user['profile_picture']; // Keep old picture on error
            }
        }
    }
    
    // Update database if no errors
    if(empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Prepare update query
            $update_fields = [
                'username' => $username,
                'email' => $email,
                'profile_picture' => $filename
            ];
            
            // Add password to update if changing
            if(!empty($new_password)){
                $update_fields['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            $set_clause = implode(', ', array_map(function($field){
                return "$field = :$field";
            }, array_keys($update_fields)));
            
            $update_fields['user_id'] = $admin_id;
            
            $sql = "UPDATE users SET $set_clause WHERE user_id = :user_id AND role = 'admin'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($update_fields);
            
            $pdo->commit();
            
            $message = "Profile updated successfully!";
            $message_type = "success";
            
            // Refresh user info
            $current_user['username'] = $username;
            $current_user['email'] = $email;
            $current_user['profile_picture'] = $filename;
            
            // Update profile image path
            $profile_img_path = getAdminProfilePicturePath($filename);
            
            // Update session
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $message = "Error updating profile: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);

// Get dashboard stats with error handling
try {
    // Get total non-admin users
    $total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
} catch (Exception $e) {
    $total_users = 0;
    error_log("Error fetching total users: " . $e->getMessage());
}

try {
    // Get total instructors
    $total_instructors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'instructor'")->fetchColumn();
} catch (Exception $e) {
    $total_instructors = 0;
    error_log("Error fetching instructors: " . $e->getMessage());
}

try {
    // Get total courses - check if courses table exists
    $total_courses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
} catch (Exception $e) {
    $total_courses = 0;
    error_log("Error fetching courses: " . $e->getMessage());
}

try {
    // Get total rooms - check if rooms table exists
    $total_rooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
} catch (Exception $e) {
    $total_rooms = 0;
    error_log("Error fetching rooms: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Profile | Admin Panel</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Include Dark Mode CSS -->
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

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
    box-shadow: 2px 0 10px rgba(0,0,0,0.2);
}
.sidebar.hidden { 
    transform:translateX(-100%); 
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

/* Pending approvals badge */
.pending-badge {
    background: #ef4444;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    margin-left: auto;
}

[data-theme="dark"] .pending-badge {
    background: #dc2626;
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

/* ================= Main Content ================= */
.main-content { 
    margin-left:250px; 
    padding:30px;
    min-height:100vh;
    background: var(--bg-primary);
    transition: all 0.3s ease;
    width: calc(100% - 250px);
    margin-top: 60px;
}

/* Content Wrapper */
.content-wrapper {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 6px var(--shadow-color);
    min-height: calc(100vh - 120px);
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

.header-left h1 {
    font-size: 2.2rem;
    color: var(--text-primary);
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.header-left p {
    color: var(--text-secondary);
    font-size: 1.1rem;
    margin-top: 10px;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

/* Dark Mode Toggle in Header */
.dark-mode-toggle {
    background: transparent;
    border: 2px solid var(--border-color);
    color: var(--text-primary);
    padding: 8px 16px;
    border-radius: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    font-weight: 500;
}
.dark-mode-toggle:hover {
    background: var(--bg-secondary);
    border-color: #3b82f6;
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

/* ================= Edit Profile Form ================= */
.edit-profile-container {
    margin-top: 30px;
}

.profile-form-card {
    background: var(--bg-card);
    max-width: 700px;
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
    max-width: 300px;
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

.form-group label .required {
    color: #ef4444;
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

/* Password Fields */
.password-field {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 4px;
    font-size: 0.9rem;
}

.password-toggle:hover {
    color: var(--text-primary);
}

/* Form row for 2 columns */
.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.form-row .form-group {
    flex: 1;
    margin-bottom: 0;
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

/* Dashboard Stats */
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 20px;
    border: 1px solid var(--border-color);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px var(--shadow-lg);
}

.stat-card i {
    font-size: 2rem;
    margin-bottom: 10px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.stat-card h3 {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 10px 0 5px;
    color: var(--text-primary);
}

.stat-card p {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin: 0;
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

[data-theme="dark"] .dark-mode-toggle {
    border-color: #4b5563;
}

[data-theme="dark"] .dark-mode-toggle:hover {
    border-color: #3b82f6;
}

/* ================= Responsive ================= */
@media screen and (max-width: 992px){
    .form-row {
        flex-direction: column;
        gap: 0;
    }
}

@media screen and (max-width: 768px){
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
        margin-left:0; 
        padding: 15px;
        padding-top: 140px; /* Adjusted for headers on mobile */
        width: 100%;
        margin-top: 120px; /* 60px header + 60px topbar */
    }
    
    .content-wrapper {
        padding: 20px;
        border-radius: 0;
    }
    
    .header { 
        flex-direction: column; 
        gap: 15px; 
        align-items: flex-start; 
    }
    
    .header-right { 
        flex-direction: column; 
        align-items: flex-start;
        width: 100%;
    }
    
    .header-left h1 { 
        font-size: 1.8rem; 
    }
    
    .profile-form-card { 
        padding: 20px; 
    }
    
    .current-profile-pic { 
        width: 120px; 
        height: 120px; 
    }
    
    .dashboard-stats {
        grid-template-columns: 1fr;
    }
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
            Admin Profile Settings
        </div>
    </div>

    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Admin Profile</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <div class="sidebar-profile">
                <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic"
                     onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                <p><?= htmlspecialchars($current_user['username']) ?></p>
            </div>
            <h2>Admin Dashboard</h2>
            
            <!-- Navigation Container -->
            <nav>
                <a href="dashboard.php" class="<?= $current_page=='dashboard.php'?'active':'' ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="manage_users.php" class="<?= $current_page=='manage_users.php'?'active':'' ?>">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="approve_users.php" class="<?= $current_page=='approve_users.php'?'active':'' ?>">
                    <i class="fas fa-user-check"></i> Approve Users
                    <?php if($pending_approvals > 0): ?>
                        <span class="pending-badge"><?= $pending_approvals ?></span>
                    <?php endif; ?>
                </a>
                <a href="manage_departments.php" class="<?= $current_page=='manage_departments.php'?'active':'' ?>">
                    <i class="fas fa-building"></i> Manage Departments
                </a>
                <a href="manage_courses.php" class="<?= $current_page=='manage_courses.php'?'active':'' ?>">
                    <i class="fas fa-book"></i> Manage Courses
                </a>
                <a href="manage_rooms.php" class="<?= $current_page=='manage_rooms.php'?'active':'' ?>">
                    <i class="fas fa-door-closed"></i> Manage Rooms
                </a>
                <a href="manage_schedules.php" class="<?= $current_page=='manage_schedules.php'?'active':'' ?>">
                    <i class="fas fa-calendar-alt"></i> Manage Schedule
                </a>
                <a href="assign_instructors.php" class="<?= $current_page=='assign_instructors.php'?'active':'' ?>">
                    <i class="fas fa-user-graduate"></i> Assign Instructors
                </a>
                <a href="admin_exam_schedules.php" class="<?= $current_page=='admin_exam_schedules.php'?'active':'' ?>">
                    <i class="fas fa-clipboard-list"></i> Exam Scheduling
                </a>
                <a href="manage_announcements.php" class="<?= $current_page=='manage_announcements.php'?'active':'' ?>">
                    <i class="fas fa-bullhorn"></i> Manage Announcements
                </a>
                <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </a>
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
                <div class="header-left">
                    <h1>Admin Profile Settings</h1>
                    <p>Update your personal information, profile picture, and password</p>
                </div>
                <div class="header-right">
                    <!-- Dark Mode Toggle -->
                    <button class="dark-mode-toggle" id="darkModeToggle">
                        <i class="fas fa-<?= $darkMode ? 'sun' : 'moon' ?>"></i>
                        <?= $darkMode ? 'Light Mode' : 'Dark Mode' ?>
                    </button>
                    
                    <div class="user-info">
                        <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile" id="headerProfilePic"
                             onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                        <div>
                            <div><?= htmlspecialchars($current_user['username'] ?? 'Admin') ?></div>
                            <small>Administrator</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?= $total_users ?></h3>
                    <p>Total Users</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h3><?= $total_instructors ?></h3>
                    <p>Instructors</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-book-open"></i>
                    <h3><?= $total_courses ?></h3>
                    <p>Total Courses</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-door-closed"></i>
                    <h3><?= $total_rooms ?></h3>
                    <p>Total Rooms</p>
                </div>
            </div>

            <!-- Messages -->
            <?php if($message): ?>
                <div class="message <?= $message_type ?>">
                    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?>"></i>
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- Edit Profile Form -->
            <div class="edit-profile-container">
                <div class="profile-form-card">
                    <h2><i class="fas fa-user-cog"></i> Update Profile Information</h2>
                    
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
                                        <i class="fas fa-camera"></i> Change Profile Picture
                                    </label>
                                </div>
                                <div class="file-name" id="fileName"></div>
                                <div class="form-tip">Max file size: 2MB • Supported: JPG, PNG, GIF, WebP</div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Username <span class="required">*</span></label>
                                <input type="text" id="username" name="username" class="form-control" 
                                       value="<?= htmlspecialchars($current_user['username'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($current_user['email'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <h3 style="margin: 30px 0 20px; color: var(--text-primary); font-size: 1.2rem;">
                            <i class="fas fa-key"></i> Change Password (Optional)
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <div class="password-field">
                                    <input type="password" id="current_password" name="current_password" class="form-control">
                                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('current_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <div class="password-field">
                                    <input type="password" id="new_password" name="new_password" class="form-control">
                                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('new_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-tip">Minimum 8 characters</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <div class="password-field">
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
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
        
        // Add animation to stats cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = `all 0.5s ease ${index * 0.1}s`;
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 300);
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
                // Also update sidebar and header images
                document.getElementById('sidebarProfilePic').src = e.target.result;
                document.getElementById('headerProfilePic').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    }

    // Toggle password visibility
    function togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        const toggleBtn = input.parentNode.querySelector('.password-toggle i');
        
        if (input.type === 'password') {
            input.type = 'text';
            toggleBtn.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            toggleBtn.className = 'fas fa-eye';
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
        const currentPassword = document.getElementById('current_password').value.trim();
        const newPassword = document.getElementById('new_password').value.trim();
        const confirmPassword = document.getElementById('confirm_password').value.trim();
        
        // Basic validation
        if (!username || !email) {
            e.preventDefault();
            showAlert('Please fill in all required fields', 'error');
            return false;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            showAlert('Please enter a valid email address', 'error');
            return false;
        }
        
        // Password validation if any password field is filled
        if (currentPassword || newPassword || confirmPassword) {
            if (!currentPassword) {
                e.preventDefault();
                showAlert('Current password is required to change password', 'error');
                return false;
            }
            
            if (!newPassword) {
                e.preventDefault();
                showAlert('New password cannot be empty', 'error');
                return false;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                showAlert('New password must be at least 8 characters long', 'error');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                showAlert('New passwords do not match', 'error');
                return false;
            }
        }
        
        // All validations passed
        return true;
    });
    
    function showAlert(message, type) {
        // Remove existing alerts
        const existingAlert = document.querySelector('.message');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        // Create new alert
        const alertDiv = document.createElement('div');
        alertDiv.className = `message ${type}`;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle')}"></i>
            ${message}
        `;
        
        // Insert before form
        const formCard = document.querySelector('.profile-form-card');
        formCard.parentNode.insertBefore(alertDiv, formCard);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            alertDiv.style.opacity = '0';
            alertDiv.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 500);
        }, 5000);
    }
    
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
    
    // Dark mode toggle
    document.getElementById('darkModeToggle').addEventListener('click', function() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        // Update theme
        document.documentElement.setAttribute('data-theme', newTheme);
        
        // Update button icon and text
        const icon = this.querySelector('i');
        const isDark = newTheme === 'dark';
        icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
        this.innerHTML = `<i class="${icon.className}"></i> ${isDark ? 'Light Mode' : 'Dark Mode'}`;
        
        // Save to localStorage
        localStorage.setItem('admin-theme', newTheme);
        
        // Send request to server
        fetch(`?toggle_dark_mode=1`, { method: 'GET' });
    });
    
    // Load saved theme
    const savedTheme = localStorage.getItem('admin-theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
        const toggleBtn = document.getElementById('darkModeToggle');
        const isDark = savedTheme === 'dark';
        toggleBtn.querySelector('i').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
        toggleBtn.innerHTML = `<i class="${isDark ? 'fas fa-sun' : 'fas fa-moon'}"></i> ${isDark ? 'Light Mode' : 'Dark Mode'}`;
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e){
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.querySelector('.menu-btn');
        const overlay = document.querySelector('.overlay');
        
        if(window.innerWidth <= 768 && sidebar.classList.contains('active') && 
           !sidebar.contains(e.target) && !menuBtn.contains(e.target)){
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
    });
    </script>
</body>
</html>