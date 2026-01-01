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

// Fetch all departments
$departments = $pdo->query("SELECT * FROM departments")->fetchAll();

// Initialize message variables
$message = "";
$message_type = "success";

// Add/Edit/Delete Course logic with enhanced validation
if(isset($_POST['add_course'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $course_name = trim($_POST['course_name']);
        $course_code = trim($_POST['course_code']);
        $department_id = (int)$_POST['department_id'];
        $credit_hours = isset($_POST['credit_hours']) ? (int)$_POST['credit_hours'] : 3;
        $category = isset($_POST['category']) ? $_POST['category'] : 'Compulsory';
        $contact_hours = isset($_POST['contact_hours']) ? (int)$_POST['contact_hours'] : 3;
        $lab_hours = isset($_POST['lab_hours']) ? (int)$_POST['lab_hours'] : 0;
        $tutorial_hours = isset($_POST['tutorial_hours']) ? (int)$_POST['tutorial_hours'] : 0;
        $prerequisite = trim($_POST['prerequisite'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Validate inputs
        if(empty($course_name) || empty($course_code) || empty($department_id)){
            $message = "All required fields must be filled!";
            $message_type = "error";
        } elseif($credit_hours <= 0) {
            $message = "Credit hours must be greater than 0!";
            $message_type = "error";
        } elseif(!preg_match('/^[A-Za-z]{2,6}\d{3,4}$/', $course_code)) {
            $message = "Invalid course code format. Use format: Letters (2-6) + Numbers (3-4), e.g., CS101";
            $message_type = "error";
        } else {
            // Validate total hours match credit hours
            $total_contact_hours = $contact_hours + $lab_hours + $tutorial_hours;
            
            if($total_contact_hours != $credit_hours){
                $message = "Error: Contact hours ($contact_hours) + Lab hours ($lab_hours) + Tutorial hours ($tutorial_hours) must equal Credit hours ($credit_hours)";
                $message_type = "error";
            } else {
                try {
                    // Check if course code already exists
                    $check_stmt = $pdo->prepare("SELECT course_id FROM courses WHERE course_code = ?");
                    $check_stmt->execute([strtoupper($course_code)]);
                    $exists = $check_stmt->fetch();
                    
                    if($exists){
                        $message = "Error: Course code '$course_code' already exists!";
                        $message_type = "error";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO courses 
                            (course_name, course_code, department_id, credit_hours, category, 
                             contact_hours, lab_hours, tutorial_hours, prerequisite, description) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $course_name, strtoupper($course_code), $department_id, $credit_hours, $category,
                            $contact_hours, $lab_hours, $tutorial_hours, $prerequisite, $description
                        ]);
                        $message = "Course added successfully!";
                        $message_type = "success";
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $message_type = "error";
                }
            }
        }
    }
}

if(isset($_POST['edit_course'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $course_id = (int)$_POST['course_id'];
        $course_name = trim($_POST['course_name']);
        $course_code = trim($_POST['course_code']);
        $department_id = (int)$_POST['department_id'];
        $credit_hours = isset($_POST['credit_hours']) ? (int)$_POST['credit_hours'] : 3;
        $category = isset($_POST['category']) ? $_POST['category'] : 'Compulsory';
        $contact_hours = isset($_POST['contact_hours']) ? (int)$_POST['contact_hours'] : 3;
        $lab_hours = isset($_POST['lab_hours']) ? (int)$_POST['lab_hours'] : 0;
        $tutorial_hours = isset($_POST['tutorial_hours']) ? (int)$_POST['tutorial_hours'] : 0;
        $prerequisite = trim($_POST['prerequisite'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Validate inputs
        if(empty($course_name) || empty($course_code) || empty($department_id)){
            $message = "All required fields must be filled!";
            $message_type = "error";
        } elseif($credit_hours <= 0) {
            $message = "Credit hours must be greater than 0!";
            $message_type = "error";
        } elseif(!preg_match('/^[A-Za-z]{2,6}\d{3,4}$/', $course_code)) {
            $message = "Invalid course code format. Use format: Letters (2-6) + Numbers (3-4), e.g., CS101";
            $message_type = "error";
        } else {
            // Validate total hours match credit hours
            $total_contact_hours = $contact_hours + $lab_hours + $tutorial_hours;
            
            if($total_contact_hours != $credit_hours){
                $message = "Error: Contact hours ($contact_hours) + Lab hours ($lab_hours) + Tutorial hours ($tutorial_hours) must equal Credit hours ($credit_hours)";
                $message_type = "error";
            } else {
                try {
                    // Check if course code already exists (excluding current course)
                    $check_stmt = $pdo->prepare("SELECT course_id FROM courses WHERE course_code = ? AND course_id != ?");
                    $check_stmt->execute([strtoupper($course_code), $course_id]);
                    $exists = $check_stmt->fetch();
                    
                    if($exists){
                        $message = "Error: Course code '$course_code' already exists!";
                        $message_type = "error";
                    } else {
                        $stmt = $pdo->prepare("UPDATE courses SET 
                            course_name=?, course_code=?, department_id=?, credit_hours=?, category=?,
                            contact_hours=?, lab_hours=?, tutorial_hours=?, prerequisite=?, description=?
                            WHERE course_id=?");
                        $stmt->execute([
                            $course_name, strtoupper($course_code), $department_id, $credit_hours, $category,
                            $contact_hours, $lab_hours, $tutorial_hours, $prerequisite, $description,
                            $course_id
                        ]);
                        $message = "Course updated successfully!";
                        $message_type = "success";
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $message_type = "error";
                }
            }
        }
    }
}

if(isset($_POST['delete_course'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $course_id = (int)$_POST['course_id'];
        
        try {
            // Check if course has any schedules before deleting
            $check_schedule = $pdo->prepare("SELECT schedule_id FROM schedule WHERE course_id = ? LIMIT 1");
            $check_schedule->execute([$course_id]);
            $has_schedule = $check_schedule->fetch();
            
            if($has_schedule){
                $message = "Cannot delete course: It has existing schedules. Please delete schedules first.";
                $message_type = "error";
            } else {
                $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id=?");
                $stmt->execute([$course_id]);
                $message = "Course deleted successfully!";
                $message_type = "success";
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Fetch course to edit
$edit_course = null;
if(isset($_GET['edit'])){
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT c.*, d.department_name, d.category as dept_category FROM courses c JOIN departments d ON c.department_id=d.department_id WHERE c.course_id=?");
    $stmt->execute([$edit_id]);
    $edit_course = $stmt->fetch();
}

// Fetch all courses with enhanced details
$courses = $pdo->query("
    SELECT c.course_id, c.course_name, c.course_code, c.credit_hours, c.category as course_category,
           c.contact_hours, c.lab_hours, c.tutorial_hours, c.prerequisite, c.description,
           d.department_name, d.category as dept_category
    FROM courses c
    JOIN departments d ON c.department_id = d.department_id
    ORDER BY d.department_name, c.course_code
")->fetchAll();

// Fetch pending approvals count
$pending_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_approved = 0");
$pending_approvals = $pending_stmt->fetchColumn() ?: 0;

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Manage Courses - DKU Scheduler</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
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
    --badge-compulsory: #3b82f6;
    --badge-elective: #10b981;
    --badge-optional: #f59e0b;
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
    --badge-compulsory: #2563eb;
    --badge-elective: #059669;
    --badge-optional: #d97706;
}

/* ================= Reset ================= */
* { margin:0; padding:0; box-sizing:border-box; font-family: "Segoe UI", Arial, sans-serif;}
body { display:flex; min-height:100vh; background: var(--bg-primary); position:relative; }

/* ================= Topbar for Mobile ================= */
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
    width:250px; 
    height:100vh; 
    background:var(--bg-sidebar); 
    color:var(--text-sidebar);
    z-index:1100;
    transition: transform 0.3s ease;
    padding: 20px 0;
    box-shadow: 2px 0 10px rgba(0,0,0,0.2);
}

.sidebar-profile {
    text-align: center;
    margin-bottom: 25px;
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
    color: var(--text-sidebar);
    font-weight: bold;
    margin: 0;
    font-size: 16px;
}

.sidebar h2 {
    text-align: center;
    color: var(--text-sidebar);
    margin-bottom: 25px;
    font-size: 22px;
    padding: 0 20px;
}

.sidebar a { 
    display:block; 
    padding:12px 20px; 
    color:var(--text-sidebar); 
    text-decoration:none; 
    transition: background 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
}
.sidebar a:hover, .sidebar a.active { background:#1abc9c; color:white; }

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

/* ================= Overlay ================= */
.overlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.4); z-index:1050;
    display:none; opacity:0; transition: opacity 0.3s ease;
}
.overlay.active { display:block; opacity:1; }

/* ================= Main Content ================= */
.main-content { 
    margin-left:250px; 
    padding:30px;
    min-height:100vh;
    background: var(--bg-primary);
    transition: all 0.3s ease;
    width: calc(100% - 250px);
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

/* Card Styles */
.card {
    background: var(--bg-card);
    border-radius: 15px;
    box-shadow: 0 6px 18px var(--shadow-color);
    margin-bottom: 25px;
    overflow: hidden;
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
    border: 1px solid var(--success-border);
}

.message.error {
    background: var(--error-bg);
    color: var(--error-text);
    border: 1px solid var(--error-border);
}

.message.warning {
    background: var(--warning-bg);
    color: var(--warning-text);
    border: 1px solid var(--warning-border);
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
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: var(--bg-input);
    color: var(--text-primary);
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

.hours-info {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 4px;
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
}

.btn-primary {
    background: #6366f1;
    color: white;
}

.btn-primary:hover {
    background: #4f46e5;
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

.btn-warning {
    background: #f59e0b;
    color: white;
}

.btn-warning:hover {
    background: #d97706;
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
}

.cancel-btn:hover {
    background: #dc2626;
    color: white;
}

/* Table Styles */
.table-container {
    overflow-x: auto;
    border-radius: 15px;
    box-shadow: 0 4px 12px var(--shadow-color);
    margin-top: 20px;
}

.courses-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-card);
}

.courses-table th,
.courses-table td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
}

.courses-table th {
    background: var(--table-header);
    color: var(--text-primary);
    font-weight: 600;
}

.courses-table tr:last-child td {
    border-bottom: none;
}

.courses-table tr:hover {
    background: var(--hover-color);
}

/* Badge Styles */
.course-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 8px;
}

.badge-compulsory {
    background: var(--badge-compulsory);
    color: white;
}

.badge-elective {
    background: var(--badge-elective);
    color: white;
}

.badge-optional {
    background: var(--badge-optional);
    color: white;
}

.category-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.category-natural { background: #10b981; color: white; }
.category-social { background: #8b5cf6; color: white; }

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
}

.action-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.action-link.delete {
    color: #dc2626;
}

.action-link.delete:hover {
    color: #b91c1c;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 50px;
    color: var(--text-secondary);
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
}

/* Course Code Validation */
.course-code-checking {
    color: #f59e0b;
    font-size: 0.875rem;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

.course-code-error {
    color: #dc2626;
    font-size: 0.875rem;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

.course-code-success {
    color: #10b981;
    font-size: 0.875rem;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

input.valid {
    border-color: #10b981 !important;
    background: linear-gradient(90deg, var(--bg-input), #d1fae5) !important;
}

input.invalid {
    border-color: #dc2626 !important;
    background: linear-gradient(90deg, var(--bg-input), #fee2e2) !important;
}

input.checking {
    border-color: #f59e0b !important;
    background: linear-gradient(90deg, var(--bg-input), #fef3c7) !important;
}

/* Loading spinner */
.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Required field indicator */
.required::after {
    content: " *";
    color: #ef4444;
}

/* ================= Responsive ================= */
@media(max-width: 1200px){ 
    .main-content{ padding:25px; }
}

@media(max-width: 768px){
    .topbar{ display:flex; }
    .sidebar{ transform:translateX(-100%); }
    .sidebar.active{ transform:translateX(0); }
    .main-content{ 
        margin-left:0; 
        padding: 20px;
        padding-top: 80px;
        width: 100%;
    }
    .header { 
        flex-direction: column; 
        gap: 15px; 
        align-items: flex-start; 
    }
    .header h1 { font-size: 1.8rem; }
    .form-row { flex-direction: column; }
    .form-row .form-group { min-width: auto; }
    .action-buttons { flex-direction: column; }
    
    /* Mobile table card view */
    .courses-table, .courses-table thead, .courses-table tbody, .courses-table th, .courses-table td, .courses-table tr { 
        display: block; 
        width: 100%; 
    }
    .courses-table thead tr { 
        position: absolute;
        top: -9999px;
        left: -9999px;
    }
    .courses-table tr { 
        margin-bottom: 15px; 
        background: var(--bg-card); 
        border-radius: 10px; 
        box-shadow: 0 2px 5px var(--shadow-color); 
        padding: 15px; 
        border: 1px solid var(--border-color);
    }
    .courses-table td { 
        text-align: right; 
        padding-left: 50%; 
        position: relative; 
        border: none; 
        margin-bottom: 10px;
    }
    .courses-table td::before { 
        content: attr(data-label); 
        position: absolute; 
        left: 15px; 
        width: 45%; 
        text-align: left; 
        font-weight: bold; 
        color: var(--text-secondary);
    }
    
    .courses-table td:last-child {
        text-align: center;
        padding-left: 15px;
    }
    .courses-table td:last-child::before {
        display: none;
    }
}

[data-theme="dark"] .category-badge.category-natural {
    background: #059669;
}

[data-theme="dark"] .category-badge.category-social {
    background: #7c3aed;
}

[data-theme="dark"] .action-link {
    color: #60a5fa;
}

[data-theme="dark"] .action-link:hover {
    color: #3b82f6;
}
</style>
</head>
<body>

<!-- Mobile Topbar -->
<div class="topbar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <span>Manage Courses</span>
</div>

<!-- Overlay for Mobile -->
<div class="overlay" onclick="toggleMenu()"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-profile">
        <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic"
             onerror="this.onerror=null; this.src='../assets/default_profile.png';">
        <p><?= htmlspecialchars($current_user['username']) ?></p>
    </div>
    <h2>Admin Panel</h2>
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

<!-- Main Content -->
<div class="main-content">
    <div class="header">
        <h1>Manage Courses</h1>
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

    <!-- Add/Edit Course Form Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-<?= isset($edit_course) ? 'edit' : 'plus-circle' ?>"></i> <?= isset($edit_course) ? 'Edit Course' : 'Add New Course' ?></h3>
        </div>
        <div class="card-body">
            <form method="POST" id="courseForm" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="course_id" value="<?= $edit_course['course_id'] ?? '' ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_code" class="required">Course Code</label>
                        <input type="text" name="course_code" id="course_code" class="form-control" 
                               placeholder="e.g., CS101" required
                               value="<?= htmlspecialchars($edit_course['course_code'] ?? '') ?>"
                               oninput="checkCourseCode()">
                        <div id="course-code-feedback"></div>
                    </div>
                    <div class="form-group">
                        <label for="course_name" class="required">Course Name</label>
                        <input type="text" name="course_name" id="course_name" class="form-control" 
                               placeholder="e.g., Introduction to Programming" required
                               value="<?= htmlspecialchars($edit_course['course_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="department_id" class="required">Department</label>
                        <select name="department_id" id="department_id" class="form-control" required>
                            <option value="">Select Department</option>
                            <?php foreach($departments as $d): ?>
                                <option value="<?= $d['department_id'] ?>" 
                                        data-category="<?= htmlspecialchars($d['category']) ?>"
                                        <?= (isset($edit_course) && $edit_course['department_id']==$d['department_id'])?'selected':'' ?>>
                                    <?= htmlspecialchars($d['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="credit_hours" class="required">Credit Hours</label>
                        <select name="credit_hours" id="credit_hours" class="form-control" required>
                            <option value="">Select Credit Hours</option>
                            <option value="1" <?= (isset($edit_course) && $edit_course['credit_hours']==1)?'selected':'' ?>>1 Credit Hour</option>
                            <option value="2" <?= (isset($edit_course) && $edit_course['credit_hours']==2)?'selected':'' ?>>2 Credit Hours</option>
                            <option value="3" <?= (!isset($edit_course) || (isset($edit_course) && $edit_course['credit_hours']==3))?'selected':'' ?>>3 Credit Hours</option>
                            <option value="4" <?= (isset($edit_course) && $edit_course['credit_hours']==4)?'selected':'' ?>>4 Credit Hours</option>
                            <option value="5" <?= (isset($edit_course) && $edit_course['credit_hours']==5)?'selected':'' ?>>5 Credit Hours</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="category" class="required">Course Category</label>
                        <select name="category" id="category" class="form-control" required>
                            <option value="Compulsory" <?= (!isset($edit_course) || (isset($edit_course) && $edit_course['course_category']=='Compulsory'))?'selected':'' ?>>Compulsory</option>
                            <option value="Elective" <?= (isset($edit_course) && $edit_course['course_category']=='Elective')?'selected':'' ?>>Elective</option>
                            <option value="Optional" <?= (isset($edit_course) && $edit_course['course_category']=='Optional')?'selected':'' ?>>Optional</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="contact_hours" class="required">Contact Hours (Theory)</label>
                        <input type="number" name="contact_hours" id="contact_hours" class="form-control" 
                               min="0" max="5" value="<?= $edit_course['contact_hours'] ?? 3 ?>" required>
                        <small class="hours-info">Classroom teaching hours</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="lab_hours" class="required">Lab Hours</label>
                        <input type="number" name="lab_hours" id="lab_hours" class="form-control" 
                               min="0" max="5" value="<?= $edit_course['lab_hours'] ?? 0 ?>" required>
                        <small class="hours-info">Laboratory/practical hours</small>
                    </div>
                    <div class="form-group">
                        <label for="tutorial_hours" class="required">Tutorial Hours</label>
                        <input type="number" name="tutorial_hours" id="tutorial_hours" class="form-control" 
                               min="0" max="5" value="<?= $edit_course['tutorial_hours'] ?? 0 ?>" required>
                        <small class="hours-info">Tutorial/discussion hours</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="prerequisite">Prerequisite Course</label>
                        <input type="text" name="prerequisite" id="prerequisite" class="form-control" 
                               placeholder="e.g., CS101, MATH102 or None"
                               value="<?= htmlspecialchars($edit_course['prerequisite'] ?? '') ?>">
                        <small class="hours-info">Enter course codes separated by commas</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Course Description</label>
                    <textarea name="description" id="description" class="form-control" rows="3" 
                              placeholder="Brief course description..."><?= htmlspecialchars($edit_course['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <button type="submit" name="<?= isset($edit_course) ? 'edit_course' : 'add_course' ?>" 
                            class="btn btn-primary" id="submit-btn">
                        <i class="fas fa-<?= isset($edit_course) ? 'save' : 'plus-circle' ?>"></i>
                        <?= isset($edit_course) ? 'Update Course' : 'Add Course' ?>
                    </button>
                    <?php if(isset($edit_course)): ?>
                        <a class="cancel-btn" href="manage_courses.php">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Courses Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-book"></i> All Courses (<?= count($courses) ?>)</h3>
        </div>
        <div class="card-body">
            <?php if($courses): ?>
                <div class="table-container">
                    <table class="courses-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Credits</th>
                                <th>Category</th>
                                <th>Hours (C/L/T)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($courses as $c): ?>
                            <tr>
                                <td data-label="Code"><strong><?= htmlspecialchars($c['course_code']) ?></strong></td>
                                <td data-label="Name"><?= htmlspecialchars($c['course_name']) ?></td>
                                <td data-label="Department"><?= htmlspecialchars($c['department_name']) ?>
                                    <br><small><?= $c['dept_category'] ?></small>
                                </td>
                                <td data-label="Credits"><?= $c['credit_hours'] ?></td>
                                <td data-label="Category">
                                    <?= $c['course_category'] ?>
                                    <span class="course-badge badge-<?= strtolower($c['course_category']) ?>">
                                        <?= $c['course_category'] ?>
                                    </span>
                                </td>
                                <td data-label="Hours">
                                    <strong><?= $c['contact_hours'] ?>/<?= $c['lab_hours'] ?>/<?= $c['tutorial_hours'] ?></strong>
                                    <br>
                                    <small class="hours-info">(Theory/Lab/Tutorial)</small>
                                </td>
                                <td data-label="Actions">
                                    <div class="action-buttons">
                                        <a class="action-link" href="manage_courses.php?edit=<?= $c['course_id'] ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirmDelete(this, '<?= htmlspecialchars(addslashes($c['course_name'])) ?>')">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                                            <button type="submit" name="delete_course" class="action-link delete">
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
                    <i class="fas fa-book"></i>
                    <h3>No Courses Found</h3>
                    <p>No courses found in the system yet. Add your first course using the form above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Toggle sidebar for mobile
function toggleMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

// DOM elements
const courseCodeInput = document.getElementById('course_code');
const courseCodeFeedback = document.getElementById('course-code-feedback');
const creditHoursSelect = document.getElementById('credit_hours');
const contactHoursInput = document.getElementById('contact_hours');
const labHoursInput = document.getElementById('lab_hours');
const tutorialHoursInput = document.getElementById('tutorial_hours');
const submitBtn = document.getElementById('submit-btn');

// Simplified Course Code validation
function checkCourseCode() {
    const courseCode = courseCodeInput.value.trim().toUpperCase();
    const courseCodeRegex = /^[A-Za-z]{2,6}\d{3,4}$/;
    
    courseCodeInput.classList.remove('valid', 'invalid');
    
    if (!courseCode) {
        courseCodeFeedback.innerHTML = '';
    } else if (!courseCodeRegex.test(courseCode)) {
        courseCodeInput.classList.add('invalid');
        courseCodeFeedback.innerHTML = '<span class="course-code-error"><i class="fas fa-exclamation-circle"></i> Format: Letters (2-6) + Numbers (3-4), e.g., CS101</span>';
    } else {
        courseCodeInput.classList.add('valid');
        courseCodeFeedback.innerHTML = '<span class="course-code-success"><i class="fas fa-check-circle"></i> Valid format</span>';
    }
}

// Auto-calculate hours based on credit hours
creditHoursSelect.addEventListener('change', function() {
    const creditHours = parseInt(this.value);
    
    // Set default distribution based on credit hours
    if (creditHours === 1) {
        contactHoursInput.value = 1;
        labHoursInput.value = 0;
        tutorialHoursInput.value = 0;
    } else if (creditHours === 2) {
        contactHoursInput.value = 2;
        labHoursInput.value = 0;
        tutorialHoursInput.value = 0;
    } else if (creditHours === 3) {
        contactHoursInput.value = 3;
        labHoursInput.value = 0;
        tutorialHoursInput.value = 0;
    } else if (creditHours === 4) {
        contactHoursInput.value = 3;
        labHoursInput.value = 1;
        tutorialHoursInput.value = 0;
    } else if (creditHours === 5) {
        contactHoursInput.value = 3;
        labHoursInput.value = 2;
        tutorialHoursInput.value = 0;
    }
});

// Form validation
function validateForm() {
    const courseCode = courseCodeInput.value.trim();
    const creditHours = parseInt(creditHoursSelect.value);
    const contactHours = parseInt(contactHoursInput.value);
    const labHours = parseInt(labHoursInput.value);
    const tutorialHours = parseInt(tutorialHoursInput.value);
    
    // Validate course code format
    const courseCodeRegex = /^[A-Za-z]{2,6}\d{3,4}$/i;
    if (!courseCodeRegex.test(courseCode)) {
        alert('Please enter a valid course code format: Letters (2-6) + Numbers (3-4), e.g., CS101');
        courseCodeInput.focus();
        return false;
    }
    
    // Validate hours
    const totalHours = contactHours + labHours + tutorialHours;
    if (totalHours !== creditHours) {
        alert(`Error: Total hours (${totalHours}) must equal credit hours (${creditHours}). Please adjust the hours.`);
        return false;
    }
    
    return true;
}

// Confirm delete with course name
function confirmDelete(form, courseName) {
    return confirm(`Are you sure you want to delete the course "${courseName}"? This action cannot be undone.`);
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
    document.getElementById('course_name').addEventListener('input', function() {
        // Enable real-time validation if needed
    });
    
    courseCodeInput.addEventListener('input', checkCourseCode);
    
    // Hours input validation
    [contactHoursInput, labHoursInput, tutorialHoursInput].forEach(input => {
        input.addEventListener('change', function() {
            const creditHours = parseInt(creditHoursSelect.value);
            const contactHours = parseInt(contactHoursInput.value);
            const labHours = parseInt(labHoursInput.value);
            const tutorialHours = parseInt(tutorialHoursInput.value);
            const totalHours = contactHours + labHours + tutorialHours;
            
            if (creditHours && totalHours !== creditHours) {
                contactHoursInput.style.borderColor = '#ef4444';
                labHoursInput.style.borderColor = '#ef4444';
                tutorialHoursInput.style.borderColor = '#ef4444';
            } else {
                contactHoursInput.style.borderColor = '';
                labHoursInput.style.borderColor = '';
                tutorialHoursInput.style.borderColor = '';
            }
        });
    });
    
    // Add data-labels for mobile table view
    const tableHeaders = document.querySelectorAll('.courses-table thead th');
    const tableRows = document.querySelectorAll('.courses-table tbody tr');
    
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
    
    // If editing, validate existing course code
    <?php if(isset($edit_course)): ?>
        setTimeout(() => {
            checkCourseCode();
        }, 100);
    <?php endif; ?>
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