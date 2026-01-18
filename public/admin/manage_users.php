<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch current user info
$user_stmt = $pdo->prepare("SELECT username, email, profile_picture FROM users WHERE user_id=?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch();

// Function to get profile picture path for admin
function getAdminProfilePicturePath($profile_picture) {
    if (empty($profile_picture)) {
        return '../assets/default_profile.png';
    }
    
    // Check multiple possible locations for admin profile pictures
    $locations = [
        __DIR__ . '/../uploads/admin/' . $profile_picture,
        __DIR__ . '/../uploads/' . $profile_picture,
        __DIR__ . '/../../uploads/' . $profile_picture,
        'uploads/admin/' . $profile_picture,
        '../uploads/admin/' . $profile_picture,
        'uploads/' . $profile_picture,
        '../uploads/' . $profile_picture,
    ];
    
    foreach ($locations as $location) {
        if (file_exists($location)) {
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
    
    return '../assets/default_profile.png';
}

// Get profile image path
$profile_img_path = getAdminProfilePicturePath($current_user['profile_picture'] ?? '');

$message = "";
$message_type = "success";

// Initialize variables
$editing = false;
$edit_id = null;
$edit_data = [];

// Handle Delete
if(isset($_GET['delete'])){
    $delete_id = (int)$_GET['delete'];
    if($delete_id > 0 && $delete_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id=?");
        $stmt->execute([$delete_id]);
        $message = "User deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Cannot delete your own account or invalid user!";
        $message_type = "error";
    }
}

// Handle Edit
if(isset($_GET['edit'])){
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if($edit_data){
        $editing = true;
    }
}

// Handle Form Submission
if(isset($_POST['save_user'])){
    // CSRF Validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $student_id = ($_POST['role'] === 'student' && isset($_POST['student_id'])) ? trim($_POST['student_id']) : NULL;
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $role = $_POST['role'];
        $department_id = isset($_POST['department_id']) && $_POST['department_id'] ? (int)$_POST['department_id'] : NULL;
        $year = isset($_POST['year']) ? trim($_POST['year']) : NULL;
        
        // Debug log
        error_log("Form Submission - Role: $role, Year: " . ($year ?? 'NULL') . ", Student ID: " . ($student_id ?? 'NULL') . ", Department: " . ($department_id ?? 'NULL'));
        
        // Validate required fields
        if(empty($username) || empty($email) || empty($role)) {
            $message = "Please fill in all required fields!";
            $message_type = "error";
        } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address!";
            $message_type = "error";
        } elseif(!$editing && empty($password)) {
            $message = "Password is required for new users!";
            $message_type = "error";
        } elseif(!$editing && strlen($password) < 8) {
            $message = "Password must be at least 8 characters long!";
            $message_type = "error";
        } elseif(!$editing && !preg_match('/[A-Z]/', $password)) {
            $message = "Password must contain at least one uppercase letter!";
            $message_type = "error";
        } elseif(!$editing && !preg_match('/[a-z]/', $password)) {
            $message = "Password must contain at least one lowercase letter!";
            $message_type = "error";
        } elseif(!$editing && !preg_match('/[0-9]/', $password)) {
            $message = "Password must contain at least one number!";
            $message_type = "error";
        } else {
            // Validate Student ID uniqueness for students
            if($role === 'student' && $student_id){
                if($editing && $edit_id){
                    $check_stmt = $pdo->prepare("SELECT user_id FROM users WHERE student_id = ? AND user_id != ?");
                    $check_stmt->execute([$student_id, $edit_id]);
                } else {
                    $check_stmt = $pdo->prepare("SELECT user_id FROM users WHERE student_id = ?");
                    $check_stmt->execute([$student_id]);
                }
                
                $existing_user = $check_stmt->fetch();
                
                if($existing_user){
                    $message = "Error: Student ID '$student_id' already exists!";
                    $message_type = "error";
                } else {
                    // Proceed with save/update
                    saveUser();
                }
            } else {
                // For non-student roles, proceed normally
                saveUser();
            }
        }
    }
}

function saveUser() {
    global $pdo, $username, $full_name, $student_id, $email, $password, $role, $department_id, $year, $editing, $edit_id, $message, $message_type;
    
    // Validate department for non-freshman students, instructors, and department heads
    if($role === 'student' && $year !== 'Freshman' && empty($department_id)) {
        $message = "Department is required for non-freshman students!";
        $message_type = "error";
        return;
    }
    
    if(($role === 'instructor' || $role === 'department_head') && empty($department_id)) {
        $message = "Department is required for instructors and department heads!";
        $message_type = "error";
        return;
    }
    
    try {
        // Debug log
        error_log("saveUser() called - Role: $role, Year: " . ($year ?? 'NULL') . ", Department: " . ($department_id ?? 'NULL'));
        
        if($editing && $edit_id){
            if($password){
                $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, student_id=?, email=?, password=?, role=?, department_id=?, year=? WHERE user_id=?");
                $stmt->execute([$username, $full_name, $student_id, $email, password_hash($password, PASSWORD_DEFAULT), $role, $department_id, $year, $edit_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, student_id=?, email=?, role=?, department_id=?, year=? WHERE user_id=?");
                $stmt->execute([$username, $full_name, $student_id, $email, $role, $department_id, $year, $edit_id]);
            }
            $message = "User updated successfully!";
            $message_type = "success";
        } else {
            if(empty($password)) {
                $message = "Password is required for new users!";
                $message_type = "error";
                return;
            }
            
            // Prepare SQL based on whether department_id is NULL
            $sql = "INSERT INTO users (username, full_name, student_id, email, password, role, year";
            $placeholders = "?, ?, ?, ?, ?, ?, ?";
            $values = [$username, $full_name, $student_id, $email, password_hash($password, PASSWORD_DEFAULT), $role, $year];
            
            // Add department_id if it's not NULL
            if($department_id !== NULL) {
                $sql .= ", department_id";
                $placeholders .= ", ?";
                $values[] = $department_id;
            }
            
            $sql .= ") VALUES (" . $placeholders . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            
            $message = "User added successfully!";
            $message_type = "success";
            
            // Clear form for new entry
            $editing = false;
            $edit_id = null;
            $edit_data = [];
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
        error_log("Database error: " . $e->getMessage());
    }
}

// Fetch pending approvals count
$pending_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_approved = 0");
$pending_approvals = $pending_stmt->fetchColumn() ?: 0;

// Fetch users and departments
$users = $pdo->query("SELECT u.*, d.department_name FROM users u LEFT JOIN departments d ON u.department_id=d.department_id ORDER BY u.role, u.year, u.username")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments")->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users - DKU Scheduler</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
/* ================= General Reset ================= */
* { margin:0; padding:0; box-sizing:border-box; font-family: "Segoe UI", Arial, sans-serif; }
body { display:flex; min-height:100vh; background: var(--bg-primary, #f8f9fa); overflow-x:hidden; }

/* ================= CSS Variables ================= */
:root {
    --bg-primary: #f8f9fa;
    --bg-secondary: #ffffff;
    --bg-card: #ffffff;
    --bg-sidebar: #2c3e50;
    --text-primary: #333333;
    --text-secondary: #666666;
    --text-sidebar: #ffffff;
    --border-color: #dee2e6;
    --shadow-color: rgba(0,0,0,0.1);
    --hover-color: rgba(0,0,0,0.05);
    --table-header: #3498db;
    --success-bg: #d1fae5;
    --success-text: #065f46;
    --success-border: #10b981;
    --error-bg: #fee2e2;
    --error-text: #991b1b;
    --error-border: #ef4444;
    --warning-bg: #fef3c7;
    --warning-text: #92400e;
    --warning-border: #f59e0b;
    --badge-primary-bg: #e0f2fe;
    --badge-primary-text: #0369a1;
    --badge-secondary-bg: #f3f4f6;
    --badge-secondary-text: #374151;
}

[data-theme="dark"] {
    --bg-primary: #1a1a1a;
    --bg-secondary: #2d2d2d;
    --bg-card: #2d2d2d;
    --bg-sidebar: #1e2a3a;
    --text-primary: #e0e0e0;
    --text-secondary: #b0b0b0;
    --text-sidebar: #e0e0e0;
    --border-color: #404040;
    --shadow-color: rgba(0,0,0,0.3);
    --hover-color: rgba(255,255,255,0.05);
    --table-header: #2563eb;
    --success-bg: #064e3b;
    --success-text: #a7f3d0;
    --success-border: #10b981;
    --error-bg: #7f1d1d;
    --error-text: #fecaca;
    --error-border: #ef4444;
    --warning-bg: #78350f;
    --warning-text: #fde68a;
    --warning-border: #f59e0b;
    --badge-primary-bg: #0c4a6e;
    --badge-primary-text: #7dd3fc;
    --badge-secondary-bg: #374151;
    --badge-secondary-text: #d1d5db;
}

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

/* Adjust elements for university header */
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

/* ================= Topbar for Mobile ================= */
.topbar {
    display: none;
    position: fixed; 
    top: 60px; 
    left: 0; 
    width: 100%;
    background: var(--bg-sidebar); 
    color: var(--text-sidebar);
    padding: 12px 20px;
    z-index: 1200;
    justify-content: space-between; 
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.menu-btn {
    font-size: 26px;
    background: #1abc9c;
    border: none; 
    color: var(--text-sidebar);
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 8px;
    font-weight: 600;
    transition: background 0.3s, transform 0.2s;
}

.menu-btn:hover { 
    background: #159b81; 
    transform: translateY(-2px); 
}

/* ================= Sidebar ================= */
.sidebar { 
    position: fixed; 
    top: 60px; 
    left: 0; 
    width: 250px; 
    height: calc(100% - 60px);
    background: var(--bg-sidebar); 
    color: var(--text-sidebar);
    z-index: 1100;
    transition: transform 0.3s ease;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 2px 0 10px rgba(0,0,0,0.2);
}

.sidebar.hidden { 
    transform: translateX(-260px); 
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
    flex-shrink: 0;
}

/* Sidebar Links */
.sidebar a { 
    display: block; 
    padding: 12px 20px; 
    color: var(--text-sidebar); 
    text-decoration: none; 
    transition: all 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    flex-shrink: 0;
}

.sidebar a:hover, .sidebar a.active { 
    background: #1abc9c; 
    color: white; 
    padding-left: 25px;
}

/* Pending Badge */
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
    top: 60px; 
    left: 0; 
    width: 100%; 
    height: calc(100% - 60px);
    background: rgba(0,0,0,0.4); 
    z-index: 1050;
    display: none; 
    opacity: 0; 
    transition: opacity 0.3s ease;
}

.overlay.active { 
    display: block; 
    opacity: 1; 
}

/* ================= Main Content ================= */
.main-content { 
    margin-left: 250px; 
    padding: 30px;
    min-height: 100vh;
    background: var(--bg-primary);
    transition: all 0.3s ease;
    width: calc(100% - 250px);
}

/* Content Wrapper */
.content-wrapper {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 6px var(--shadow-color);
    min-height: calc(100vh - 60px);
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

/* Page Title */
.page-title {
    font-size: 1.8rem;
    color: var(--text-primary);
    margin-bottom: 25px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ================= Form Styling ================= */
.user-form { 
    background: var(--bg-card); 
    padding: 25px; 
    border-radius: 12px; 
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    margin-bottom: 30px; 
}

.user-form input, .user-form select, .user-form button { 
    padding: 10px; 
    margin: 8px 5px 12px 0; 
    border-radius: 8px; 
    font-size: 14px; 
    border: 1px solid var(--border-color);
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.user-form button { 
    background: #2563eb; 
    color: #fff; 
    border: none; 
    cursor: pointer; 
    transition: 0.2s; 
    font-weight: 600;
    padding: 12px 24px;
}

.user-form button:hover { background: #1d4ed8; transform: translateY(-1px); }

.user-form button:disabled {
    background: #94a3b8;
    cursor: not-allowed;
    transform: none;
}

.cancel-btn { 
    text-decoration: none; 
    color: #dc2626; 
    margin-left: 10px; 
    font-weight: 500;
    padding: 10px 15px;
    border-radius: 8px;
    border: 1px solid #dc2626;
    transition: all 0.3s;
    display: inline-block;
}

.cancel-btn:hover {
    background: #dc2626;
    color: white;
}

.form-group { margin-bottom: 15px; }

.form-group label { 
    display: block; 
    margin-bottom: 5px; 
    font-weight: 500; 
    color: var(--text-primary); 
}

.form-group input, .form-group select { 
    width: 100%; 
    padding: 12px; 
    border-radius: 8px; 
    border: 1px solid var(--border-color); 
    font-size: 14px; 
    background: var(--bg-secondary);
    color: var(--text-primary);
    transition: all 0.3s;
}

.form-group input:focus, .form-group select:focus { 
    outline: none; 
    border-color: #2563eb; 
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2); 
}

/* ================= Password Field with Toggle ================= */
.password-container {
    position: relative;
    width: 100%;
}

.password-input {
    width: 100%;
    padding-right: 40px !important; /* Make room for the toggle button */
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

/* ================= Password Strength Meter ================= */
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

.password-requirements {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-top: 5px;
    line-height: 1.4;
}

.password-requirements ul {
    padding-left: 20px;
    margin: 5px 0;
}

.password-requirements li {
    margin-bottom: 3px;
}

.password-requirements li.valid {
    color: #10b981;
}

.password-requirements li.invalid {
    color: #dc2626;
}

.password-requirements li i {
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

.email-checking {
    color: #f59e0b;
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

input.checking {
    border-color: #f59e0b !important;
    background: linear-gradient(90deg, var(--bg-secondary), #fef3c7) !important;
}

/* Required field indicator */
.required::after {
    content: " *";
    color: #ef4444;
}

/* Department not required for Freshman */
.department-optional {
    opacity: 0.7;
}

.department-optional label::after {
    content: " (Optional for Freshman)";
    font-size: 0.8rem;
    color: var(--text-secondary);
    font-weight: normal;
}

/* ================= Table Styling ================= */
.table-container {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: var(--border-color) var(--bg-secondary);
    position: relative;
    border-radius: 10px;
    border: 1px solid var(--border-color);
}

.table-container::-webkit-scrollbar {
    height: 12px;
}

.table-container::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 6px;
}

.table-container::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 6px;
}

.table-container::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}

.user-table { 
    width: 100%; 
    border-collapse: collapse; 
    min-width: 1000px; 
    background: var(--bg-card);
}

.user-table th, .user-table td { 
    padding: 15px; 
    text-align: left; 
    border-bottom: 1px solid var(--border-color); 
    color: var(--text-primary);
}

.user-table th { 
    background: var(--table-header); 
    color: var(--text-sidebar); 
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.user-table tr:nth-child(even){ background: var(--bg-secondary); }

.user-table tr:hover { background: var(--hover-color); }

.button-action { 
    padding: 8px 15px; 
    border-radius: 6px; 
    text-decoration: none; 
    color: #fff; 
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    display: inline-block;
    margin: 0 2px;
}

.button-edit { background: #2563eb; } 
.button-delete { background: #dc2626; }

.button-edit:hover { background: #1d4ed8; transform: translateY(-1px); } 
.button-delete:hover { background: #b91c1c; transform: translateY(-1px); }

.role-badge { 
    display: inline-block; 
    padding: 5px 12px; 
    border-radius: 12px; 
    font-size: 0.8rem; 
    font-weight: 600; 
    text-transform: uppercase; 
    letter-spacing: 0.5px;
}

.role-admin { background: #ef4444; color: white; }
.role-student { background: #10b981; color: white; }
.role-instructor { background: #3b82f6; color: white; }
.role-department_head { background: #8b5cf6; color: white; }

.year-badge {
    display: inline-block;
    padding: 4px 10px;
    background: var(--badge-primary-bg);
    color: var(--badge-primary-text);
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-left: 5px;
}

.student-id-badge {
    display: inline-block;
    padding: 4px 10px;
    background: var(--badge-secondary-bg);
    color: var(--badge-secondary-text);
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-left: 5px;
    border: 1px solid var(--border-color);
}

/* Status Badge for Approval */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-left: 5px;
}

.status-approved { background: #10b981; color: white; }
.status-pending { background: #f59e0b; color: white; }

/* Action Buttons Container */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* Form Section Title */
.form-section-title {
    color: var(--text-primary);
    margin-bottom: 15px;
    font-size: 1.2rem;
    font-weight: 600;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Success/Error Messages */
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

/* ================= Student ID Validation Styles ================= */
.student-id-error {
    color: #dc2626;
    font-size: 0.875rem;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

.student-id-success {
    color: #10b981;
    font-size: 0.875rem;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

.student-id-checking {
    color: #f59e0b;
    font-size: 0.875rem;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

/* ================= Responsive ================= */
@media (max-width: 1200px){ 
    .main-content{ padding: 25px; }
    .content-wrapper { padding: 20px; }
}

@media (max-width: 768px){
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
    
    .topbar{ 
        display: flex; 
        top: 60px;
    }
    
    .sidebar{ 
        transform: translateX(-100%); 
        top: 120px; /* 60px header + 60px topbar */
        height: calc(100% - 120px) !important;
    }
    
    .sidebar.active{ 
        transform: translateX(0); 
    }
    
    .overlay {
        top: 120px;
        height: calc(100% - 120px);
    }
    
    .main-content{ 
        margin-left: 0; 
        padding: 15px;
        padding-top: 140px; /* Adjusted for headers on mobile */
        width: 100%;
    }
    
    .content-wrapper {
        padding: 15px;
        border-radius: 0;
    }
    
    .header { 
        flex-direction: column; 
        gap: 15px; 
        align-items: flex-start; 
    }
    
    .header h1 { font-size: 1.8rem; }
    
    .user-form{ padding: 15px; }
    .user-form input, .user-form select{ width: 100%; margin: 8px 0; }

    /* Mobile-friendly card-style table */
    .table-container {
        border: none;
    }
    
    .user-table, .user-table thead, .user-table tbody, .user-table th, .user-table td, .user-table tr { 
        display: block; 
        width: 100%; 
    }
    
    .user-table thead tr { display: none; }
    
    .user-table tr { 
        margin-bottom: 15px; 
        background: var(--bg-card); 
        border-radius: 10px; 
        box-shadow: 0 2px 5px var(--shadow-color); 
        padding: 15px; 
        border: 1px solid var(--border-color);
        position: relative;
    }
    
    .user-table td { 
        text-align: right; 
        padding-left: 50%; 
        position: relative; 
        border: none; 
        margin-bottom: 10px;
        padding: 10px 15px;
        padding-left: 50%;
    }
    
    .user-table td::before { 
        content: attr(data-label); 
        position: absolute; 
        left: 15px; 
        width: 45%; 
        text-align: left; 
        font-weight: bold; 
        color: var(--text-secondary);
        top: 50%;
        transform: translateY(-50%);
    }
    
    /* Action buttons in mobile */
    .action-buttons {
        justify-content: flex-end;
    }
}

/* Loading spinner */
.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
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
            Manage Users
        </div>
    </div>

    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Manage Users</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-content" id="sidebarContent">
            <div class="sidebar-profile">
                <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic"
                     onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                <p><?= htmlspecialchars($current_user['username']) ?></p>
            </div>
            <h2>Admin Dashboard</h2>
            <a href="dashboard.php" class="<?= $current_page=='dashboard.php'?'active':'' ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="manage_users.php" class="active">
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
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1>Manage Users</h1>
                    <p>Add, edit, or delete user accounts</p>
                </div>
                <div class="user-info">
                    <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile" id="headerProfilePic"
                         onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                    <div>
                        <div><?= htmlspecialchars($current_user['username']) ?></div>
                        <small>Administrator</small>
                    </div>
                </div>
            </div>

            <!-- Display Error/Success Messages -->
            <?php if($message): ?>
                <div class="message <?= $message_type ?>">
                    <i class="fas fa-<?= $message_type === 'error' ? 'exclamation-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'check-circle') ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Form Section -->
            <div class="user-form-section">
                <div class="form-section-title">
                    <i class="fas fa-<?= $editing ? 'edit' : 'user-plus' ?>"></i>
                    <?= $editing ? "Edit User" : "Add New User" ?>
                </div>

                <form method="POST" class="user-form" id="userForm" onsubmit="return validateForm()">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="form-group">
                        <label class="required">Username:</label>
                        <input type="text" name="username" value="<?= isset($edit_data['username']) ? htmlspecialchars($edit_data['username']) : '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Full Name:</label>
                        <input type="text" name="full_name" value="<?= isset($edit_data['full_name']) ? htmlspecialchars($edit_data['full_name']) : '' ?>" required>
                    </div>
                    
                    <div class="form-group" id="student-id-group" style="display:none;">
                        <label>Student ID:</label>
                        <input type="text" name="student_id" id="student-id-input" 
                               value="<?= (isset($edit_data['role']) && $edit_data['role'] === 'student' && isset($edit_data['student_id'])) ? htmlspecialchars($edit_data['student_id']) : '' ?>"
                               oninput="checkStudentID()">
                        <div id="student-id-feedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Email:</label>
                        <input type="email" name="email" id="email-input" 
                               value="<?= isset($edit_data['email']) ? htmlspecialchars($edit_data['email']) : '' ?>" 
                               required oninput="validateEmail()">
                        <div id="email-validation"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="<?= !$editing ? 'required' : '' ?>">Password: <?= $editing ? "<small>(Leave blank to keep current)</small>" : "" ?></label>
                        <div class="password-container">
                            <input type="password" name="password" id="password-input" class="password-input" 
                                   <?= !$editing ? 'required' : '' ?> oninput="validatePassword()">
                            <button type="button" class="toggle-password" id="toggle-password-btn" 
                                    onclick="togglePasswordVisibility()">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        
                        <!-- Password strength meter -->
                        <div class="password-strength-container" id="password-strength-container" style="<?= $editing ? 'display:none;' : '' ?>">
                            <div class="password-strength-bar">
                                <div class="strength-fill" id="strength-fill"></div>
                            </div>
                            <div class="password-strength-text" id="password-strength-text"></div>
                            
                            <!-- Password requirements -->
                            <div class="password-requirements" id="password-requirements">
                                <p>Password must contain:</p>
                                <ul>
                                    <li id="req-length" class="invalid"><i class="fas fa-times"></i> At least 8 characters</li>
                                    <li id="req-uppercase" class="invalid"><i class="fas fa-times"></i> At least one uppercase letter</li>
                                    <li id="req-lowercase" class="invalid"><i class="fas fa-times"></i> At least one lowercase letter</li>
                                    <li id="req-number" class="invalid"><i class="fas fa-times"></i> At least one number</li>
                                    <li id="req-special" class="invalid"><i class="fas fa-times"></i> At least one special character (optional but recommended)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Role:</label>
                        <select name="role" id="role-select" required>
                            <option value="">--Select Role--</option>
                            <option value="admin" <?= (isset($edit_data['role']) && $edit_data['role']=='admin')?'selected':'' ?>>Admin</option>
                            <option value="student" <?= (isset($edit_data['role']) && $edit_data['role']=='student')?'selected':'' ?>>Student</option>
                            <option value="instructor" <?= (isset($edit_data['role']) && $edit_data['role']=='instructor')?'selected':'' ?>>Instructor</option>
                            <option value="department_head" <?= (isset($edit_data['role']) && $edit_data['role']=='department_head')?'selected':'' ?>>Department Head</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="year-group" style="display:none;">
                        <label>Student Type:</label>
                        <select name="year_type" id="year-type-select" onchange="toggleYearDropdown()">
                            <option value="">--Select Student Type--</option>
                            <option value="regular" <?= (isset($edit_data['role']) && $edit_data['role']=='student' && isset($edit_data['year']) && (is_numeric($edit_data['year']) || $edit_data['year'] == 'Freshman'))?'selected':'' ?>>Regular Student</option>
                            <option value="extension" <?= (isset($edit_data['role']) && $edit_data['role']=='student' && isset($edit_data['year']) && (strpos($edit_data['year'], 'E') !== false || $edit_data['year'] == 'Freshman'))?'selected':'' ?>>Extension Student</option>
                        </select>
                    </div>

                    <div class="form-group" id="regular-year-group" style="display:none;">
                        <label>Regular Year:</label>
                        <select name="regular_year" id="regular-year-select" onchange="updateDepartmentRequirement()">
                            <option value="">--Select Year--</option>
                            <option value="Freshman" <?= (isset($edit_data['role']) && $edit_data['role']=='student' && isset($edit_data['year']) && $edit_data['year']=='Freshman')?'selected':'' ?>>
                                Freshman
                            </option>
                            <?php for($i=2; $i<=5; $i++): ?>
                                <option value="<?= $i ?>" <?= (isset($edit_data['role']) && $edit_data['role']=='student' && isset($edit_data['year']) && $edit_data['year']==$i)?'selected':'' ?>>
                                    Year <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group" id="extension-year-group" style="display:none;">
                        <label>Extension Year:</label>
                        <select name="extension_year" id="extension-year-select" onchange="updateDepartmentRequirement()">
                            <option value="">--Select Extension Year--</option>
                            <option value="Freshman" <?= (isset($edit_data['role']) && $edit_data['role']=='student' && isset($edit_data['year']) && $edit_data['year']=="Freshman")?'selected':'' ?>>
                                Freshman (Extension)
                            </option>
                            <?php for($i=2; $i<=5; $i++): ?>
                                <option value="E<?= $i ?>" <?= (isset($edit_data['role']) && $edit_data['role']=='student' && isset($edit_data['year']) && $edit_data['year']=="E$i")?'selected':'' ?>>
                                    Extension Year <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <input type="hidden" name="year" id="year-hidden" value="<?= isset($edit_data['year']) ? htmlspecialchars($edit_data['year']) : '' ?>">
                    
                    <div class="form-group" id="department-group" style="display:none;">
                        <label id="department-label">Department:</label>
                        <select name="department_id" id="department-select">
                            <option value="">--Select Department--</option>
                            <?php foreach($departments as $d): ?>
                                <option value="<?= $d['department_id'] ?>" <?= (isset($edit_data['department_id']) && $edit_data['department_id']==$d['department_id'])?'selected':'' ?>>
                                    <?= htmlspecialchars($d['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="save_user" id="submit-btn" <?= $editing ? '' : 'disabled' ?>>
                            <i class="fas fa-<?= $editing ? 'save' : 'plus' ?>"></i>
                            <?= $editing ? "Update User" : "Add User" ?>
                        </button>
                        <?php if($editing): ?>
                            <a href="manage_users.php" class="cancel-btn">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Users Table Section -->
            <div class="users-table-section">
                <div class="form-section-title">
                    <i class="fas fa-list"></i>
                    Existing Users (<?= count($users) ?>)
                </div>
                
                <div class="table-container">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Student ID</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Year</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $u): ?>
                                <tr>
                                    <td data-label="ID"><?= $u['user_id'] ?></td>
                                    <td data-label="Username">
                                        <strong><?= htmlspecialchars($u['username']) ?></strong>
                                    </td>
                                    <td data-label="Full Name"><?= htmlspecialchars($u['full_name'] ?? '-') ?></td>
                                    <td data-label="Student ID">
                                        <?php if($u['role'] === 'student' && !empty($u['student_id'])): ?>
                                            <span class="student-id-badge"><?= htmlspecialchars($u['student_id']) ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Email"><?= htmlspecialchars($u['email']) ?></td>
                                    <td data-label="Role">
                                        <span class="role-badge role-<?= $u['role'] ?>">
                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $u['role']))) ?>
                                        </span>
                                    </td>
                                    <td data-label="Year">
                                        <?php if($u['role'] === 'student' && isset($u['year'])): ?>
                                            <?php if($u['year'] == 'Freshman'): ?>
                                                <span class="year-badge">Freshman</span>
                                            <?php elseif(strpos($u['year'], 'E') === 0): ?>
                                                <span class="year-badge">E<?= substr($u['year'], 1) ?></span>
                                            <?php elseif(is_numeric($u['year'])): ?>
                                                <span class="year-badge">Year <?= $u['year'] ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Department"><?= htmlspecialchars($u['department_name'] ?? '-') ?></td>
                                    <td data-label="Status">
                                        <?php if(isset($u['is_approved'])): ?>
                                            <span class="status-badge status-<?= $u['is_approved'] ? 'approved' : 'pending' ?>">
                                                <?= $u['is_approved'] ? 'Approved' : 'Pending' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-approved">Approved</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="action-buttons">
                                            <a class="button-action button-edit" href="?edit=<?= $u['user_id'] ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php if($u['user_id'] != $_SESSION['user_id']): ?>
                                                <a class="button-action button-delete" href="?delete=<?= $u['user_id'] ?>" onclick="return confirm('Are you sure you want to delete this user?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleSidebar(){
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    // DOM elements
    const roleSelect = document.getElementById('role-select');
    const yearGroup = document.getElementById('year-group');
    const yearTypeSelect = document.getElementById('year-type-select');
    const regularYearGroup = document.getElementById('regular-year-group');
    const regularYearSelect = document.getElementById('regular-year-select');
    const extensionYearGroup = document.getElementById('extension-year-group');
    const extensionYearSelect = document.getElementById('extension-year-select');
    const yearHidden = document.getElementById('year-hidden');
    const studentIdGroup = document.getElementById('student-id-group');
    const studentIdInput = document.getElementById('student-id-input');
    const departmentGroup = document.getElementById('department-group');
    const departmentSelect = document.getElementById('department-select');
    const departmentLabel = document.getElementById('department-label');
    const studentIdFeedback = document.getElementById('student-id-feedback');
    const submitBtn = document.getElementById('submit-btn');
    const passwordInput = document.getElementById('password-input');
    const emailInput = document.getElementById('email-input');
    const emailValidation = document.getElementById('email-validation');
    const passwordStrengthContainer = document.getElementById('password-strength-container');
    const strengthFill = document.getElementById('strength-fill');
    const passwordStrengthText = document.getElementById('password-strength-text');
    const passwordRequirements = document.getElementById('password-requirements');
    const togglePasswordBtn = document.getElementById('toggle-password-btn');
    const togglePasswordIcon = togglePasswordBtn.querySelector('i');

    let studentIdValid = true;
    let emailValid = false;
    let passwordValid = false;
    let passwordVisible = false;

    // Toggle password visibility
    function togglePasswordVisibility() {
        passwordVisible = !passwordVisible;
        
        if (passwordVisible) {
            passwordInput.type = 'text';
            togglePasswordIcon.classList.remove('fa-eye');
            togglePasswordIcon.classList.add('fa-eye-slash');
            togglePasswordBtn.setAttribute('aria-label', 'Hide password');
            togglePasswordBtn.title = 'Hide password';
        } else {
            passwordInput.type = 'password';
            togglePasswordIcon.classList.remove('fa-eye-slash');
            togglePasswordIcon.classList.add('fa-eye');
            togglePasswordBtn.setAttribute('aria-label', 'Show password');
            togglePasswordBtn.title = 'Show password';
        }
    }

    // Show/hide fields based on role
    function toggleRoleFields(){
        const role = roleSelect.value;
        const isStudent = role === 'student';
        const needsDepartment = ['student', 'instructor', 'department_head'].includes(role);
        
        // Year and Student ID (students only)
        yearGroup.style.display = isStudent ? 'block' : 'none';
        yearTypeSelect.required = isStudent;
        if(!isStudent) {
            yearTypeSelect.value = '';
            regularYearSelect.value = '';
            extensionYearSelect.value = '';
            yearHidden.value = '';
            toggleYearDropdown();
        }
        
        studentIdGroup.style.display = isStudent ? 'block' : 'none';
        studentIdInput.required = isStudent;
        if(!isStudent) {
            studentIdInput.value = '';
            studentIdInput.classList.remove('valid', 'invalid', 'checking');
            studentIdFeedback.innerHTML = '';
            studentIdValid = true;
        }
        
        // Department
        departmentGroup.style.display = needsDepartment ? 'block' : 'none';
        if(!needsDepartment) departmentSelect.value = '';
        
        // Password strength meter (only for new users)
        passwordStrengthContainer.style.display = <?= $editing ? 'false' : 'true' ?> && !isStudent ? 'block' : 'none';
        
        // Update department requirement based on year selection
        updateDepartmentRequirement();
        
        // Enable/disable submit button
        updateSubmitButton();
    }

    // Toggle year dropdown based on student type
    function toggleYearDropdown() {
        const yearType = yearTypeSelect.value;
        
        regularYearGroup.style.display = 'none';
        extensionYearGroup.style.display = 'none';
        regularYearSelect.required = false;
        extensionYearSelect.required = false;
        
        regularYearSelect.value = '';
        extensionYearSelect.value = '';
        
        if (yearType === 'regular') {
            regularYearGroup.style.display = 'block';
            regularYearSelect.required = true;
        } else if (yearType === 'extension') {
            extensionYearGroup.style.display = 'block';
            extensionYearSelect.required = true;
        }
        
        updateYearHiddenField();
        updateDepartmentRequirement();
        updateSubmitButton();
    }

    // Update department requirement based on year selection
    function updateDepartmentRequirement() {
        const role = roleSelect.value;
        const yearType = yearTypeSelect.value;
        let selectedYear = '';
        
        if (yearType === 'regular') {
            selectedYear = regularYearSelect.value;
        } else if (yearType === 'extension') {
            selectedYear = extensionYearSelect.value;
        }
        
        const isFreshman = selectedYear === 'Freshman';
        
        if (role === 'student' && isFreshman) {
            departmentSelect.required = false;
            departmentGroup.classList.add('department-optional');
            departmentLabel.innerHTML = 'Department <small>(Optional for Freshman)</small>';
        } else if (role === 'student') {
            departmentSelect.required = true;
            departmentGroup.classList.remove('department-optional');
            departmentLabel.innerHTML = 'Department:';
        } else if (['instructor', 'department_head'].includes(role)) {
            departmentSelect.required = true;
            departmentGroup.classList.remove('department-optional');
            departmentLabel.innerHTML = 'Department:';
        }
        
        updateSubmitButton();
    }

    // Update hidden year field when selections change
    function updateYearHiddenField() {
        const yearType = yearTypeSelect.value;
        
        if (yearType === 'regular') {
            yearHidden.value = regularYearSelect.value;
        } else if (yearType === 'extension') {
            yearHidden.value = extensionYearSelect.value;
        } else {
            yearHidden.value = '';
        }
        
        updateDepartmentRequirement();
        updateSubmitButton();
    }

    // Initialize year selection from edit data
    function initializeYearSelection() {
        <?php if($editing && isset($edit_data['role']) && $edit_data['role'] === 'student' && isset($edit_data['year'])): ?>
            const yearValue = '<?= $edit_data['year'] ?>';
            
            if (yearValue) {
                if (yearValue === 'Freshman' || (!isNaN(yearValue) && yearValue !== '')) {
                    yearTypeSelect.value = 'regular';
                    toggleYearDropdown();
                    if (yearValue === 'Freshman') {
                        regularYearSelect.value = 'Freshman';
                    } else {
                        regularYearSelect.value = yearValue;
                    }
                    yearHidden.value = yearValue;
                } 
                else if (yearValue.startsWith('E')) {
                    yearTypeSelect.value = 'extension';
                    toggleYearDropdown();
                    extensionYearSelect.value = yearValue;
                    yearHidden.value = yearValue;
                }
            }
            
            updateDepartmentRequirement();
        <?php endif; ?>
    }

    // Check Student ID uniqueness
    function checkStudentID() {
        const studentId = studentIdInput.value.trim();
        const editing = <?= $editing ? 'true' : 'false' ?>;
        const editId = <?= $edit_id ?: 'null' ?>;
        
        studentIdInput.classList.remove('valid', 'invalid', 'checking');
        studentIdValid = false;
        updateSubmitButton();
        
        if (!studentId) {
            studentIdFeedback.innerHTML = '';
            studentIdValid = true;
            updateSubmitButton();
            return;
        }
        
        studentIdInput.classList.add('checking');
        studentIdFeedback.innerHTML = '<span class="student-id-checking"><i class="fas fa-spinner fa-spin spinner"></i> Checking Student ID...</span>';
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'check_student_id.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.available) {
                        studentIdInput.classList.remove('checking');
                        studentIdInput.classList.add('valid');
                        studentIdFeedback.innerHTML = '<span class="student-id-success"><i class="fas fa-check-circle"></i> Student ID is available!</span>';
                        studentIdValid = true;
                    } else {
                        studentIdInput.classList.remove('checking');
                        studentIdInput.classList.add('invalid');
                        studentIdFeedback.innerHTML = `<span class="student-id-error"><i class="fas fa-exclamation-circle"></i> Student ID already exists! ${response.suggestion ? 'Suggestion: ' + response.suggestion : ''}</span>`;
                        studentIdValid = false;
                    }
                } catch (e) {
                    handleCheckError();
                }
            } else {
                handleCheckError();
            }
            updateSubmitButton();
        };
        
        xhr.onerror = handleCheckError;
        
        function handleCheckError() {
            studentIdInput.classList.remove('checking');
            studentIdFeedback.innerHTML = '<span class="student-id-error"><i class="fas fa-exclamation-circle"></i> Error checking Student ID</span>';
            studentIdValid = false;
            updateSubmitButton();
        }
        
        xhr.send(`student_id=${encodeURIComponent(studentId)}&editing=${editing}&edit_id=${editId}`);
    }

    // Validate email format
    function validateEmail() {
        const email = emailInput.value.trim();
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        const universityRegex = /@dku\.edu\.et$/i;
        
        emailInput.classList.remove('valid', 'invalid');
        
        if (!email) {
            emailValidation.innerHTML = '';
            emailValid = false;
            updateSubmitButton();
            return;
        }
        
        if (!emailRegex.test(email)) {
            emailInput.classList.add('invalid');
            emailValidation.innerHTML = '<span class="email-invalid"><i class="fas fa-exclamation-circle"></i> Please enter a valid email address</span>';
            emailValid = false;
        } else if (!universityRegex.test(email)) {
            emailInput.classList.add('valid');
            emailValidation.innerHTML = '<span class="email-valid"><i class="fas fa-check-circle"></i> Valid email format (Note: Not a DKU email)</span>';
            emailValid = true;
        } else {
            emailInput.classList.add('valid');
            emailValidation.innerHTML = '<span class="email-valid"><i class="fas fa-check-circle"></i> Valid DKU email format</span>';
            emailValid = true;
        }
        
        updateSubmitButton();
    }

    // Validate password strength
    function validatePassword() {
        const password = passwordInput.value;
        const editing = <?= $editing ? 'true' : 'false' ?>;
        
        if (editing && !password) {
            // When editing and password is empty, hide strength meter
            passwordStrengthContainer.style.display = 'none';
            passwordValid = true; // Empty password is allowed when editing
            updateSubmitButton();
            return;
        }
        
        // Show strength meter for new users or when password is entered during edit
        passwordStrengthContainer.style.display = 'block';
        
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
        document.getElementById('req-special').innerHTML = (requirements.special ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>') + ' At least one special character (optional but recommended)';
        
        // Calculate strength score
        if (requirements.length) strength++;
        if (requirements.uppercase) strength++;
        if (requirements.lowercase) strength++;
        if (requirements.number) strength++;
        if (requirements.special) strength++;
        
        // Update strength meter
        const strengthPercent = (strength / 5) * 100;
        strengthFill.className = 'strength-fill strength-' + strength;
        strengthFill.style.width = strengthPercent + '%';
        
        // Update strength text
        const strengthTexts = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        const strengthColors = ['#dc2626', '#ef4444', '#f59e0b', '#10b981', '#059669'];
        passwordStrengthText.textContent = 'Password Strength: ' + strengthTexts[strength];
        passwordStrengthText.style.color = strengthColors[strength];
        
        // Password is valid if it meets minimum requirements
        passwordValid = requirements.length && requirements.uppercase && requirements.lowercase && requirements.number;
        
        // Update input border color based on strength
        passwordInput.classList.remove('valid', 'invalid');
        if (password.length === 0) {
            // Do nothing
        } else if (strength < 3) {
            passwordInput.classList.add('invalid');
        } else {
            passwordInput.classList.add('valid');
        }
        
        updateSubmitButton();
    }

    // Update submit button state
    function updateSubmitButton() {
        const role = roleSelect.value;
        const isStudent = role === 'student';
        const editing = <?= $editing ? 'true' : 'false' ?>;
        const hasPassword = passwordInput.value.trim().length > 0;
        const yearType = yearTypeSelect.value;
        const yearHiddenValue = yearHidden.value;
        const departmentValue = departmentSelect.value;
        
        let enabled = true;
        
        // Basic validation
        if (!role) enabled = false;
        if (!emailValid) enabled = false;
        if (isStudent && !studentIdValid) enabled = false;
        if (isStudent && !yearType) enabled = false;
        if (isStudent && !yearHiddenValue) enabled = false;
        
        // Password validation
        if (!editing && !passwordValid) {
            enabled = false;
        }
        
        // Department validation (not required for Freshman)
        if (isStudent && yearHiddenValue && yearHiddenValue !== 'Freshman' && !departmentValue) {
            enabled = false;
        }
        if (['instructor', 'department_head'].includes(role) && !departmentValue) {
            enabled = false;
        }
        
        submitBtn.disabled = !enabled;
    }

    // Form validation
    function validateForm() {
        const role = roleSelect.value;
        const isStudent = role === 'student';
        const editing = <?= $editing ? 'true' : 'false' ?>;
        const password = passwordInput.value.trim();
        const yearType = yearTypeSelect.value;
        const yearHiddenValue = yearHidden.value;
        const departmentValue = departmentSelect.value;
        
        // Email validation
        if (!emailValid) {
            alert('Please enter a valid email address');
            emailInput.focus();
            return false;
        }
        
        // Student ID validation
        if (isStudent && !studentIdValid) {
            alert('Please fix the Student ID errors before submitting');
            studentIdInput.focus();
            return false;
        }
        
        // Year validation for students
        if (isStudent) {
            if (!yearType) {
                alert('Please select student type (Regular or Extension)');
                yearTypeSelect.focus();
                return false;
            }
            
            if (!yearHiddenValue) {
                alert('Please select a year');
                if (yearType === 'regular') {
                    regularYearSelect.focus();
                } else {
                    extensionYearSelect.focus();
                }
                return false;
            }
            
            // Department validation (not required for Freshman)
            if (yearHiddenValue !== 'Freshman' && !departmentValue) {
                alert('Please select a department (required for non-freshman students)');
                departmentSelect.focus();
                return false;
            }
        }
        
        // Department validation for instructors and department heads
        if (['instructor', 'department_head'].includes(role) && !departmentValue) {
            alert('Please select a department');
            departmentSelect.focus();
            return false;
        }
        
        // Password validation
        if (!editing) {
            if (!passwordValid) {
                alert('Please ensure the password meets all requirements (8+ characters, uppercase, lowercase, number)');
                passwordInput.focus();
                return false;
            }
            
            if (password.length < 8) {
                alert('Password must be at least 8 characters long');
                passwordInput.focus();
                return false;
            }
        }
        
        return true;
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Set active nav
        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('.sidebar a').forEach(link => {
            const linkPage = link.getAttribute('href');
            if (linkPage === currentPage) {
                link.classList.add('active');
            }
        });
        
        // Event listeners
        roleSelect.addEventListener('change', toggleRoleFields);
        passwordInput.addEventListener('input', validatePassword);
        yearTypeSelect.addEventListener('change', toggleYearDropdown);
        regularYearSelect.addEventListener('change', updateYearHiddenField);
        extensionYearSelect.addEventListener('change', updateYearHiddenField);
        departmentSelect.addEventListener('change', updateSubmitButton);
        emailInput.addEventListener('input', validateEmail);
        
        // Initial field setup
        toggleRoleFields();
        initializeYearSelection();
        
        // Validate email if editing
        <?php if($editing && isset($edit_data['email'])): ?>
            setTimeout(() => {
                validateEmail();
            }, 100);
        <?php endif; ?>
        
        // If editing a student, check student ID
        <?php if($editing && isset($edit_data['role']) && $edit_data['role'] === 'student'): ?>
            setTimeout(() => {
                if (studentIdInput.value.trim()) {
                    checkStudentID();
                }
            }, 500);
        <?php endif; ?>
        
        // Profile picture fallback
        document.querySelectorAll('img').forEach(img => {
            img.addEventListener('error', function() {
                if (!this.src.includes('default_profile.png')) {
                    this.src = '../assets/default_profile.png';
                }
            });
        });
        
        // Animate table rows
        const tableRows = document.querySelectorAll('.user-table tbody tr');
        tableRows.forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateY(10px)';
            setTimeout(() => {
                row.style.transition = 'all 0.5s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, index * 50);
        });
        
        // Initialize overlay and sidebar for mobile
        if (window.innerWidth <= 768) {
            document.querySelector('.sidebar').classList.add('hidden');
        }
        
        // Initial password validation
        validatePassword();
        
        // Add keyboard shortcut for password toggle (Alt+P)
        document.addEventListener('keydown', function(e) {
            if (e.altKey && e.key === 'p') {
                e.preventDefault();
                togglePasswordVisibility();
            }
        });
    });

    // Confirm logout
    document.querySelector('a[href="../logout.php"]')?.addEventListener('click', function(e) {
        if(!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });
    </script>

</body>
</html>