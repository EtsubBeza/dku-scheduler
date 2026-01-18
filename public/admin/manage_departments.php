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

// Initialize message variables
$message = "";
$message_type = "success";

// Add Department
if(isset($_POST['add_department'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $department_name = trim($_POST['department_name']);
        $department_code = trim($_POST['department_code']);
        $category = trim($_POST['category']);
        $description = trim($_POST['description'] ?? '');
        
        // Validate inputs
        if(empty($department_name) || empty($department_code) || empty($category)){
            $message = "All required fields must be filled!";
            $message_type = "error";
        } else {
            try {
                // Check if department code already exists
                $check_stmt = $pdo->prepare("SELECT department_id FROM departments WHERE department_code = ?");
                $check_stmt->execute([strtoupper($department_code)]);
                $exists = $check_stmt->fetch();
                
                if($exists){
                    $message = "Error: Department code '$department_code' already exists!";
                    $message_type = "error";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO departments 
                        (department_name, department_code, category, description) 
                        VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $department_name, 
                        strtoupper($department_code), 
                        $category, 
                        $description
                    ]);
                    $message = "Department added successfully!";
                    $message_type = "success";
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Edit Department
if(isset($_POST['edit_department'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $department_id = (int)$_POST['department_id'];
        $department_name = trim($_POST['department_name']);
        $department_code = trim($_POST['department_code']);
        $category = trim($_POST['category']);
        $description = trim($_POST['description'] ?? '');
        
        // Validate inputs
        if(empty($department_name) || empty($department_code) || empty($category)){
            $message = "All required fields must be filled!";
            $message_type = "error";
        } else {
            try {
                // Check if department code already exists (excluding current department)
                $check_stmt = $pdo->prepare("SELECT department_id FROM departments WHERE department_code = ? AND department_id != ?");
                $check_stmt->execute([strtoupper($department_code), $department_id]);
                $exists = $check_stmt->fetch();
                
                if($exists){
                    $message = "Error: Department code '$department_code' already exists!";
                    $message_type = "error";
                } else {
                    $stmt = $pdo->prepare("UPDATE departments SET 
                        department_name=?, department_code=?, category=?, description=?
                        WHERE department_id=?");
                    $stmt->execute([
                        $department_name, 
                        strtoupper($department_code), 
                        $category, 
                        $description,
                        $department_id
                    ]);
                    $message = "Department updated successfully!";
                    $message_type = "success";
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Delete Department
if(isset($_POST['delete_department'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $department_id = (int)$_POST['department_id'];
        
        try {
            // Check if department has any courses
            $check_courses = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE department_id = ?");
            $check_courses->execute([$department_id]);
            $has_courses = $check_courses->fetchColumn();
            
            if($has_courses > 0){
                $message = "Cannot delete department: It has existing courses. Please delete or reassign courses first.";
                $message_type = "error";
            } else {
                // Check if department has assigned department head
                $check_head = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ? AND role = 'department_head'");
                $check_head->execute([$department_id]);
                $has_head = $check_head->fetchColumn();
                
                if($has_head > 0){
                    $message = "Cannot delete department: It has an assigned department head. Please reassign or remove the department head first.";
                    $message_type = "error";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM departments WHERE department_id=?");
                    $stmt->execute([$department_id]);
                    $message = "Department deleted successfully!";
                    $message_type = "success";
                }
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Assign Department Head
if(isset($_POST['assign_head'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $department_id = (int)$_POST['department_id'];
        $user_id = (int)$_POST['user_id'];
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Get user details
            $user_stmt = $pdo->prepare("SELECT username, email FROM users WHERE user_id = ?");
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch();
            
            if(!$user){
                throw new Exception("User not found!");
            }
            
            // Check if user is already a department head elsewhere
            $check_current = $pdo->prepare("SELECT department_id FROM users WHERE user_id = ? AND role = 'department_head'");
            $check_current->execute([$user_id]);
            $current_dept = $check_current->fetchColumn();
            
            // Remove user from current department head role if assigned elsewhere
            if($current_dept){
                $remove_stmt = $pdo->prepare("UPDATE users SET department_id = NULL, role = 'instructor' WHERE user_id = ?");
                $remove_stmt->execute([$user_id]);
            }
            
            // Check if department already has a head
            $check_head = $pdo->prepare("SELECT user_id, username FROM users WHERE department_id = ? AND role = 'department_head'");
            $check_head->execute([$department_id]);
            $current_head = $check_head->fetch();
            
            // If department has a current head, demote them to instructor
            if($current_head){
                $demote_stmt = $pdo->prepare("UPDATE users SET role = 'instructor' WHERE user_id = ?");
                $demote_stmt->execute([$current_head['user_id']]);
                
                $message = "Department head reassigned. " . $current_head['username'] . " has been demoted to instructor.";
            }
            
            // Assign new department head
            $assign_stmt = $pdo->prepare("UPDATE users SET department_id = ?, role = 'department_head' WHERE user_id = ?");
            $assign_stmt->execute([$department_id, $user_id]);
            
            $pdo->commit();
            
            if(!isset($message)){
                $message = "Department head assigned successfully!";
            } else {
                $message .= " New department head assigned successfully!";
            }
            $message_type = "success";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Remove Department Head
if(isset($_POST['remove_head'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $user_id = (int)$_POST['user_id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = 'instructor', department_id = NULL WHERE user_id = ? AND role = 'department_head'");
            $stmt->execute([$user_id]);
            
            if($stmt->rowCount() > 0){
                $message = "Department head removed successfully!";
                $message_type = "success";
            } else {
                $message = "Error: User is not a department head or not found.";
                $message_type = "error";
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Fetch department to edit
$edit_department = null;
if(isset($_GET['edit'])){
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id=?");
    $stmt->execute([$edit_id]);
    $edit_department = $stmt->fetch();
}

// Fetch all departments with head information
$departments = $pdo->query("
    SELECT d.*, 
           u.user_id as head_id, 
           u.username as head_name,
           u.email as head_email
    FROM departments d
    LEFT JOIN users u ON d.department_id = u.department_id AND u.role = 'department_head'
    ORDER BY d.category, d.department_name
")->fetchAll();

// Fetch all instructors (potential department heads)
$instructors = $pdo->query("
    SELECT user_id, username, email, department_id, role 
    FROM users 
    WHERE role IN ('instructor', 'department_head')
    ORDER BY username
")->fetchAll();

// Organize instructors by department
$instructors_by_dept = [];
foreach($instructors as $instructor){
    $dept_id = $instructor['department_id'] ?? 0;
    if(!isset($instructors_by_dept[$dept_id])){
        $instructors_by_dept[$dept_id] = [];
    }
    $instructors_by_dept[$dept_id][] = $instructor;
}

// Fetch pending approvals count
$pending_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_approved = 0");
$pending_approvals = $pending_stmt->fetchColumn() ?: 0;

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Departments - DKU Scheduler</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
* { box-sizing: border-box; margin:0; padding:0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

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
    --badge-natural: #10b981;
    --badge-social: #8b5cf6;
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
    --badge-natural: #059669;
    --badge-social: #7c3aed;
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
    top: 60px !important;
}

.sidebar {
    top: 60px !important;
    height: calc(100% - 60px) !important;
}

.overlay {
    top: 60px;
    height: calc(100% - 60px);
}

.main-content {
    margin-top: 60px;
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

/* Card Styles */
.card {
    background: var(--bg-card);
    border-radius: 15px;
    box-shadow: 0 6px 18px var(--shadow-color);
    margin-bottom: 25px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.card-header {
    padding: 20px 25px;
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 15px 15px 0 0;
}

.card-header h3 {
    font-size: 1.4rem;
    font-weight: 600;
}

.card-body {
    padding: 25px;
}

/* Message Styles */
.message {
    padding: 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
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
    background: var(--success-bg);
    color: var(--success-text);
    border-color: var(--success-border);
}

.message.error {
    background: var(--error-bg);
    color: var(--error-text);
    border-color: var(--error-border);
}

.message.warning {
    background: var(--warning-bg);
    color: var(--warning-text);
    border-color: var(--warning-border);
}

.message i {
    font-size: 1.2rem;
}

/* Form Styles */
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
    padding: 14px 16px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.form-control:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.form-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.form-row .form-group {
    flex: 1;
    min-width: 250px;
}

.form-info {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 4px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Button Styles */
.btn {
    padding: 14px 24px;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.btn-primary {
    background: #6366f1;
    color: white;
}

.btn-primary:hover {
    background: #4f46e5;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3);
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-2px);
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
    transform: translateY(-2px);
}

.btn-warning {
    background: #f59e0b;
    color: white;
}

.btn-warning:hover {
    background: #d97706;
    transform: translateY(-2px);
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 0.9rem;
}

.cancel-btn {
    text-decoration: none;
    color: #dc2626;
    margin-left: 10px;
    padding: 12px 20px;
    border: 1px solid #dc2626;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.cancel-btn:hover {
    background: #dc2626;
    color: white;
}

/* Table Styles */
.table-container {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    margin-top: 20px;
}

.departments-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-card);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.departments-table th,
.departments-table td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
}

.departments-table th {
    background: var(--table-header);
    color: var(--text-sidebar);
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.departments-table tr:last-child td {
    border-bottom: none;
}

.departments-table tr:hover {
    background: var(--hover-color);
}

/* Badge Styles */
.department-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.badge-natural {
    background: var(--badge-natural);
    color: white;
}

.badge-social {
    background: var(--badge-social);
    color: white;
}

.head-badge {
    display: inline-block;
    padding: 4px 8px;
    background: #10b981;
    color: white;
    border-radius: 12px;
    font-size: 0.75rem;
    margin-left: 8px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.no-head {
    color: var(--text-secondary);
    font-style: italic;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.action-link {
    text-decoration: none;
    color: #2563eb;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: color 0.3s;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.action-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.action-link.warning {
    color: #f59e0b;
}

.action-link.warning:hover {
    color: #d97706;
}

.action-link.danger {
    color: #dc2626;
}

.action-link.danger:hover {
    color: #b91c1c;
}

.action-link.success {
    color: #10b981;
}

.action-link.success:hover {
    color: #059669;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 50px;
    color: var(--text-secondary);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.empty-state i {
    font-size: 3.5rem;
    margin-bottom: 20px;
    color: var(--border-color);
}

.empty-state h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: var(--text-primary);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.empty-state p {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1300;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--bg-card);
    padding: 30px;
    border-radius: 15px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    border: 1px solid var(--border-color);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
    color: var(--text-primary);
    font-size: 1.4rem;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 5px;
}

.close-modal:hover {
    color: var(--text-primary);
}

.modal-body {
    margin-bottom: 20px;
}

/* Required field indicator */
.required::after {
    content: " *";
    color: #ef4444;
}

/* ================= Responsive ================= */
@media(max-width: 1200px){ 
    .main-content{ padding: 25px; }
    .content-wrapper { padding: 20px; }
}

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
    
    .topbar{ 
        display: flex; 
        top: 60px;
    }
    
    .sidebar{ 
        transform: translateX(-100%); 
        top: 120px;
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
        padding-top: 140px;
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
    
    .form-row { flex-direction: column; }
    .form-row .form-group { min-width: auto; }
    
    /* Mobile table card view */
    .table-container {
        border: none;
    }
    
    .departments-table, .departments-table thead, .departments-table tbody, .departments-table th, .departments-table td, .departments-table tr { 
        display: block; 
        width: 100%; 
    }
    
    .departments-table thead tr { 
        display: none;
    }
    
    .departments-table tr { 
        margin-bottom: 15px; 
        background: var(--bg-card); 
        border-radius: 10px; 
        box-shadow: 0 2px 5px var(--shadow-color); 
        padding: 15px; 
        border: 1px solid var(--border-color);
    }
    
    .departments-table td { 
        text-align: right; 
        padding-left: 50%; 
        position: relative; 
        border: none; 
        margin-bottom: 10px;
        padding: 10px 15px;
        padding-left: 50%;
    }
    
    .departments-table td::before { 
        content: attr(data-label); 
        position: absolute; 
        left: 15px; 
        width: 45%; 
        text-align: left; 
        font-weight: bold; 
        color: var(--text-secondary);
        top: 50%;
        transform: translateY(-50%);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .departments-table td:last-child {
        text-align: center;
        padding-left: 15px;
    }
    
    .departments-table td:last-child::before {
        display: none;
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: stretch;
    }
    
    .modal-content {
        width: 95%;
        padding: 20px;
        margin: 10px;
    }
}

[data-theme="dark"] .action-link {
    color: #60a5fa;
}

[data-theme="dark"] .action-link:hover {
    color: #3b82f6;
}

[data-theme="dark"] .btn-primary {
    background: #4f46e5;
}

[data-theme="dark"] .btn-primary:hover {
    background: #4338ca;
}

[data-theme="dark"] .card-header {
    background: linear-gradient(135deg, #3730a3, #1d4ed8);
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
            Manage Departments
        </div>
    </div>

    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Manage Departments</h2>
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
            
            <!-- Navigation Links -->
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
                <a href="manage_departments.php" class="active">
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
            <!-- Header -->
            <div class="header">
                <div>
                    <h1>Manage Departments</h1>
                    <p>Create, edit, and manage academic departments</p>
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

            <?php if($message): ?>
                <div class="message <?= $message_type ?>">
                    <i class="fas fa-<?= $message_type === 'error' ? 'exclamation-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'check-circle') ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Add/Edit Department Form Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-<?= isset($edit_department) ? 'edit' : 'plus-circle' ?>"></i> <?= isset($edit_department) ? 'Edit Department' : 'Add New Department' ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="departmentForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="department_id" value="<?= $edit_department['department_id'] ?? '' ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="department_name" class="required">Department Name</label>
                                <input type="text" name="department_name" id="department_name" class="form-control" 
                                       placeholder="e.g., Computer Science" required
                                       value="<?= htmlspecialchars($edit_department['department_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="department_code" class="required">Department Code</label>
                                <input type="text" name="department_code" id="department_code" class="form-control" 
                                       placeholder="e.g., CS" required
                                       value="<?= htmlspecialchars($edit_department['department_code'] ?? '') ?>">
                                <small class="form-info">Unique code for the department (2-6 characters)</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="category" class="required">Category</label>
                                <select name="category" id="category" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <option value="Natural" <?= (isset($edit_department) && $edit_department['category']=='Natural')?'selected':'' ?>>Natural Sciences</option>
                                    <option value="Social" <?= (isset($edit_department) && $edit_department['category']=='Social')?'selected':'' ?>>Social Sciences</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Department Description</label>
                            <textarea name="description" id="description" class="form-control" rows="3" 
                                      placeholder="Brief department description..."><?= htmlspecialchars($edit_department['description'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <button type="submit" name="<?= isset($edit_department) ? 'edit_department' : 'add_department' ?>" 
                                    class="btn btn-primary">
                                <i class="fas fa-<?= isset($edit_department) ? 'save' : 'plus-circle' ?>"></i>
                                <?= isset($edit_department) ? 'Update Department' : 'Add Department' ?>
                            </button>
                            <?php if(isset($edit_department)): ?>
                                <a class="cancel-btn" href="manage_departments.php">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Departments List Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> All Departments (<?= count($departments) ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if($departments): ?>
                        <div class="table-container">
                            <table class="departments-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Department Name</th>
                                        <th>Category</th>
                                        <th>Department Head</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($departments as $dept): ?>
                                    <tr>
                                        <td data-label="Code"><strong><?= htmlspecialchars($dept['department_code']) ?></strong></td>
                                        <td data-label="Department Name">
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                            <?php if(!empty($dept['description'])): ?>
                                                <br><small class="form-info"><?= htmlspecialchars(substr($dept['description'], 0, 50)) ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Category">
                                            <span class="department-badge badge-<?= strtolower($dept['category']) ?>">
                                                <?= $dept['category'] ?>
                                            </span>
                                        </td>
                                        <td data-label="Department Head">
                                            <?php if($dept['head_id']): ?>
                                                <div>
                                                    <strong><?= htmlspecialchars($dept['head_name']) ?></strong>
                                                    <span class="head-badge">Head</span>
                                                    <br>
                                                    <small><?= htmlspecialchars($dept['head_email']) ?></small>
                                                </div>
                                            <?php else: ?>
                                                <span class="no-head">No department head assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Actions">
                                            <div class="action-buttons">
                                                <a class="action-link" href="manage_departments.php?edit=<?= $dept['department_id'] ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                
                                                <?php if($dept['head_id']): ?>
                                                    <button class="action-link success" onclick="openAssignModal(<?= $dept['department_id'] ?>, '<?= htmlspecialchars(addslashes($dept['department_name'])) ?>')">
                                                        <i class="fas fa-user-edit"></i> Change Head
                                                    </button>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($dept['head_name'])) ?> as department head?')">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <input type="hidden" name="user_id" value="<?= $dept['head_id'] ?>">
                                                        <button type="submit" name="remove_head" class="action-link warning">
                                                            <i class="fas fa-user-minus"></i> Remove Head
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button class="action-link success" onclick="openAssignModal(<?= $dept['department_id'] ?>, '<?= htmlspecialchars(addslashes($dept['department_name'])) ?>')">
                                                        <i class="fas fa-user-plus"></i> Assign Head
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <form method="POST" style="display:inline;" onsubmit="return confirmDelete(this, '<?= htmlspecialchars(addslashes($dept['department_name'])) ?>')">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="department_id" value="<?= $dept['department_id'] ?>">
                                                    <button type="submit" name="delete_department" class="action-link danger">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-building"></i>
                            <h3>No Departments Found</h3>
                            <p>No departments found in the system yet. Add your first department using the form above.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Department Head Modal -->
    <div class="modal" id="assignModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Assign Department Head</h3>
                <button class="close-modal" onclick="closeAssignModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="assignForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="department_id" id="modal_department_id">
                    
                    <div class="form-group">
                        <label for="department_name_display">Department</label>
                        <input type="text" id="department_name_display" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_id" class="required">Select Instructor</label>
                        <select name="user_id" id="user_id" class="form-control" required>
                            <option value="">Select an instructor...</option>
                            <?php foreach($instructors as $instructor): ?>
                                <option value="<?= $instructor['user_id'] ?>">
                                    <?= htmlspecialchars($instructor['username']) ?> 
                                    (<?= htmlspecialchars($instructor['email']) ?>)
                                    - <?= $instructor['role'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="assign_head" class="btn btn-success">
                            <i class="fas fa-user-check"></i> Assign as Department Head
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Toggle sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.overlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    // Modal functions
    let currentDepartmentId = null;
    let currentDepartmentName = null;

    function openAssignModal(departmentId, departmentName) {
        currentDepartmentId = departmentId;
        currentDepartmentName = departmentName;
        
        document.getElementById('modal_department_id').value = departmentId;
        document.getElementById('department_name_display').value = departmentName;
        document.getElementById('assignModal').style.display = 'flex';
    }

    function closeAssignModal() {
        document.getElementById('assignModal').style.display = 'none';
        document.getElementById('modal_department_id').value = '';
        document.getElementById('department_name_display').value = '';
        document.getElementById('user_id').value = '';
        currentDepartmentId = null;
        currentDepartmentName = null;
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('assignModal');
        if (event.target === modal) {
            closeAssignModal();
        }
    }

    // Confirm delete with department name
    function confirmDelete(form, departmentName) {
        return confirm(`Are you sure you want to delete the department "${departmentName}"? This action cannot be undone.`);
    }

    // Form validation
    document.getElementById('departmentForm').addEventListener('submit', function(e) {
        const departmentCode = document.getElementById('department_code').value.trim();
        const departmentCodeRegex = /^[A-Za-z]{2,6}$/;
        
        if (!departmentCodeRegex.test(departmentCode)) {
            e.preventDefault();
            alert('Department code must be 2-6 letters only (e.g., CS, MATH)');
            document.getElementById('department_code').focus();
            return false;
        }
        
        return true;
    });

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
        
        // Add data-labels for mobile table view
        const tableHeaders = document.querySelectorAll('.departments-table thead th');
        const tableRows = document.querySelectorAll('.departments-table tbody tr');
        
        tableRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            cells.forEach((cell, index) => {
                if(tableHeaders[index]) {
                    cell.setAttribute('data-label', tableHeaders[index].textContent);
                }
            });
        });
        
        // Profile picture fallback
        document.querySelectorAll('img').forEach(img => {
            img.addEventListener('error', function() {
                if (!this.src.includes('default_profile.png')) {
                    this.src = '../assets/default_profile.png';
                }
            });
        });
        
        // Confirm logout
        document.querySelector('a[href="../logout.php"]')?.addEventListener('click', function(e) {
            if(!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
        
        // Auto-capitalize department code
        const deptCodeInput = document.getElementById('department_code');
        if(deptCodeInput) {
            deptCodeInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
        }
        
        // Initialize overlay and sidebar for mobile
        if (window.innerWidth <= 768) {
            document.querySelector('.sidebar').classList.add('hidden');
        }
        
        // Animate table rows
        const tableRowsAnimate = document.querySelectorAll('.departments-table tbody tr');
        tableRowsAnimate.forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateY(10px)';
            setTimeout(() => {
                row.style.transition = 'all 0.5s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, index * 50);
        });
    });

    // Fallback for broken profile pictures
    function handleImageError(img) {
        img.onerror = null;
        img.src = '../assets/default_profile.png';
        return true;
    }
    </script>

</body>
</html>