<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only allow department head
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_head'){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

$dept_id = $_SESSION['department_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Fetch current user info for sidebar
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Determine profile picture path
$profile_path = '../../uploads/profiles/' . ($current_user['profile_picture'] ?? '');
if (!empty($current_user['profile_picture']) && file_exists($profile_path)) {
    $profile_src = $profile_path;
} else {
    $profile_src = '../assets/default_profile.png';
}

// ------------------ Handle Create/Edit Announcement ------------------
$message = "";
$editing = false;
$edit_announcement = [];

if(isset($_GET['edit'])){
    $ann_id = (int)$_GET['edit'];
    $edit_stmt = $pdo->prepare("SELECT * FROM announcements WHERE announcement_id=? AND created_by=?");
    $edit_stmt->execute([$ann_id, $user_id]);
    $edit_announcement = $edit_stmt->fetch(PDO::FETCH_ASSOC);
    if($edit_announcement){
        $editing = true;
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['message']) && !isset($_POST['ajax_action'])){
    // Normal create / update (not AJAX)
    $title = trim($_POST['title']);
    $content = trim($_POST['message']);
    $external_link = trim($_POST['external_link'] ?? '');
    $attachment_name = $editing ? $edit_announcement['attachment'] : null;

    if(isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK){
        $file_tmp = $_FILES['attachment']['tmp_name'];
        $file_name = basename($_FILES['attachment']['name']);
        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $allowed = ['pdf','doc','docx','jpg','jpeg','png','mp4','mov','avi'];
        if(in_array(strtolower($ext), $allowed)){
            $new_name = time().'_'.$file_name;
            if(!is_dir(__DIR__.'/../../uploads/announcements')) {
                @mkdir(__DIR__.'/../../uploads/announcements', 0755, true);
            }
            move_uploaded_file($file_tmp, __DIR__.'/../../uploads/announcements/'.$new_name);
            $attachment_name = $new_name;
        }
    }

    if($title && $content){
        if($editing){
            $stmt = $pdo->prepare("UPDATE announcements SET title=?, message=?, attachment=?, external_link=?, updated_at = NOW() WHERE announcement_id=? AND created_by=?");
            $stmt->execute([$title, $content, $attachment_name, $external_link, $edit_announcement['announcement_id'], $user_id]);
            header("Location: manage_announcements.php");
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, message, created_by, department_id, attachment, external_link, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $content, $user_id, $dept_id, $attachment_name, $external_link]);
            $message = "Announcement posted successfully!";
        }
    } else {
        $message = "Please fill in both title and message.";
    }
}

// ------------------ Handle Delete announcement ------------------
if(isset($_GET['delete'])){
    $ann_id = (int)$_GET['delete'];
    $del_stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id=? AND created_by=?");
    $del_stmt->execute([$ann_id, $user_id]);
    header("Location: manage_announcements.php");
    exit;
}

// ------------------ Fetch all announcements with likes and comment counts ------------------
$announcements_stmt = $pdo->prepare("
    SELECT a.*, u.username,
           (SELECT COUNT(*) FROM announcement_likes l WHERE l.announcement_id = a.announcement_id) AS like_count,
           (SELECT GROUP_CONCAT(u2.username SEPARATOR ', ') 
            FROM announcement_likes l2 
            JOIN users u2 ON l2.user_id = u2.user_id 
            WHERE l2.announcement_id = a.announcement_id) AS liked_users,
           (SELECT COUNT(*) FROM announcement_comments c WHERE c.announcement_id = a.announcement_id) AS comment_count
    FROM announcements a
    JOIN users u ON a.created_by = u.user_id
    WHERE a.department_id = ? OR a.department_id IS NULL
    ORDER BY a.created_at DESC
");
$announcements_stmt->execute([$dept_id]);
$announcements = $announcements_stmt->fetchAll(PDO::FETCH_ASSOC);
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<title>Manage Announcements</title>
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

/* Button Styles */
.btn {
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary {
    background: #6366f1;
    color: white;
}

.btn-primary:hover {
    background: #4f46e5;
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

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
}

.btn-info {
    background: #06b6d4;
    color: white;
}

.btn-info:hover {
    background: #0891b2;
    transform: translateY(-2px);
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 0.9rem;
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
}

.message.success {
    background: var(--success-bg);
    color: var(--success-text);
    border: 1px solid var(--success-text);
}

.message.error {
    background: var(--error-bg);
    color: var(--error-text);
    border: 1px solid var(--error-text);
}

.message.warning {
    background: var(--warning-bg);
    color: var(--warning-text);
    border: 1px solid var(--warning-text);
}

/* Table Styles */
.ann-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-card);
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 12px var(--shadow-color);
}

.ann-table th, .ann-table td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.ann-table th {
    background: var(--table-header);
    color: var(--text-sidebar);
    font-weight: 600;
}

.ann-table tr:hover {
    background: var(--hover-color);
}

.ann-table tr:last-child td {
    border-bottom: none;
}

/* Badge Styles */
.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    background: var(--bg-secondary);
    color: var(--text-primary);
}

/* Details row */
.details-row td {
    background: var(--bg-card);
    padding: 25px;
}

.details-content {
    display: flex;
    gap: 30px;
    align-items: flex-start;
}

.details-left {
    flex: 1;
}

.details-right {
    width: 300px;
    background: var(--bg-secondary);
    padding: 20px;
    border-radius: 10px;
}

/* Like button */
.like-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 18px;
    padding: 8px;
    border-radius: 6px;
    transition: background 0.3s;
    color: var(--text-primary);
}

.like-btn:hover {
    background: var(--hover-color);
}

/* Comments */
.comment-section {
    margin-top: 15px;
    background: var(--bg-secondary);
    padding: 15px;
    border-radius: 10px;
    max-height: 250px;
    overflow-y: auto;
}

.comment {
    margin-bottom: 12px;
    padding: 12px;
    background: var(--comment-bg);
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.comment .meta {
    font-size: 13px;
    color: var(--text-secondary);
}

.comment button.delete-comment {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-weight: bold;
    padding: 4px 8px;
    border-radius: 4px;
}

.comment button.delete-comment:hover {
    background: var(--error-bg);
}

/* Add-comment form */
.add-comment-form {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.add-comment-form input {
    flex: 1;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    font-size: 1rem;
    background: var(--bg-input);
    color: var(--text-primary);
}

.add-comment-form button {
    padding: 12px 20px;
    border: none;
    background: #3b82f6;
    color: #fff;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.add-comment-form button:hover {
    background: #2563eb;
}

.text-muted {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

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

/* Dark mode specific adjustments */
[data-theme="dark"] .comment-section {
    background: var(--bg-secondary);
}

[data-theme="dark"] .comment {
    background: rgba(255, 255, 255, 0.05);
}

[data-theme="dark"] .details-right {
    background: rgba(255, 255, 255, 0.05);
}

[data-theme="dark"] .add-comment-form input {
    background: var(--bg-input);
    color: var(--text-primary);
}

[data-theme="dark"] .add-comment-form input::placeholder {
    color: var(--text-secondary);
}

[data-theme="dark"] .like-btn:hover {
    background: rgba(255, 255, 255, 0.1);
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
    
    .header { 
        flex-direction: column; 
        gap: 15px; 
        align-items: flex-start; 
    }
    
    .header h1 { 
        font-size: 1.8rem; 
    }
    
    .form-row { 
        flex-direction: column; 
    }
    
    .form-row .form-group { 
        min-width: auto; 
    }
    
    .details-content { 
        flex-direction: column; 
    }
    
    .details-right { 
        width: 100%; 
    }
    
    .action-buttons { 
        flex-direction: column; 
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
            <?php 
                $page_titles = [
                    'departmenthead_dashboard.php' => 'Department Head Dashboard',
                    'edit_profile.php' => 'Edit Profile',
                    'manage_enrollments.php' => 'Manage Enrollments',
                    'manage_schedules.php' => 'Manage Schedules',
                    'assign_courses.php' => 'Assign Courses',
                    'add_courses.php' => 'Add Courses',
                    'exam_schedules.php' => 'Exam Schedules',
                    'manage_announcements.php' => 'Manage Announcements'
                ];
                $current_page = basename($_SERVER['PHP_SELF']);
                echo htmlspecialchars($page_titles[$current_page] ?? 'Department Head');
            ?>
        </div>
    </div>

    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>
            <?php 
                echo htmlspecialchars($page_titles[$current_page] ?? 'Department Head');
            ?>
        </h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-content" id="sidebarContent">
            <div class="sidebar-profile">
                <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile Picture">
                <p><?= htmlspecialchars($current_user['username'] ?? 'User') ?></p>
            </div>
            <nav>
                <a href="departmenthead_dashboard.php" class="<?= $current_page=='departmenthead_dashboard.php'?'active':'' ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="manage_enrollments.php" class="<?= $current_page=='manage_enrollments.php'?'active':'' ?>">
                    <i class="fas fa-users"></i> Manage Enrollments
                </a>
                <a href="manage_schedules.php" class="<?= $current_page=='manage_schedules.php'?'active':'' ?>">
                    <i class="fas fa-calendar-alt"></i> Manage Schedules
                </a>
                <a href="assign_courses.php" class="<?= $current_page=='assign_courses.php'?'active':'' ?>">
                    <i class="fas fa-chalkboard-teacher"></i> Assign Courses
                </a>
                <a href="add_courses.php" class="<?= $current_page=='add_courses.php'?'active':'' ?>">
                    <i class="fas fa-book"></i> Add Courses
                </a>
                <a href="exam_schedules.php" class="<?= $current_page=='exam_schedules.php'?'active':'' ?>">
                    <i class="fas fa-clipboard-list"></i> Exam Schedules
                </a>
                <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </a>
                <a href="manage_announcements.php" class="<?= $current_page=='manage_announcements.php'?'active':'' ?>">
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
            <h1>Manage Announcements</h1>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile">
                <div>
                    <div><?= htmlspecialchars($current_user['username'] ?? 'User') ?></div>
                    <small>Department Head</small>
                </div>
            </div>
        </div>

        <!-- Create/Edit Announcement Card -->
        <div class="card">
            <div class="card-header">
                <h3><?= $editing ? "Edit Announcement" : "Post New Announcement" ?></h3>
            </div>
            <div class="card-body">
                <?php if(!$editing && $message): ?>
                    <div class="message <?= strpos($message, 'successfully') !== false ? 'success' : 'error' ?>">
                        <i class="fas fa-<?= strpos($message, 'successfully') !== false ? 'check-circle' : 'exclamation-circle' ?>"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title">Title:</label>
                        <input type="text" name="title" id="title" class="form-control" 
                               value="<?= $editing ? htmlspecialchars($edit_announcement['title']) : '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message:</label>
                        <textarea name="message" id="message" class="form-control" rows="4" required><?= $editing ? htmlspecialchars($edit_announcement['message']) : '' ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="attachment">Attachment:</label>
                            <input type="file" name="attachment" id="attachment" class="form-control">
                            <?php if($editing && $edit_announcement['attachment']): ?>
                                <p class="text-muted">Current: <a href="../../uploads/announcements/<?= htmlspecialchars($edit_announcement['attachment']) ?>" target="_blank"><?= htmlspecialchars($edit_announcement['attachment']) ?></a></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="external_link">External Link / Video URL:</label>
                            <input type="url" name="external_link" id="external_link" class="form-control" 
                                   placeholder="https://example.com" value="<?= $editing ? htmlspecialchars($edit_announcement['external_link']) : '' ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-<?= $editing ? 'save' : 'paper-plane' ?>"></i> 
                        <?= $editing ? "Update Announcement" : "Post Announcement" ?>
                    </button>
                    
                    <?php if($editing): ?>
                        <a href="manage_announcements.php" class="btn btn-secondary" style="margin-left: 10px;">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- All Announcements Card -->
        <div class="card">
            <div class="card-header">
                <h3>All Announcements</h3>
            </div>
            <div class="card-body">
                <?php if($announcements): ?>
                    <div style="overflow-x: auto;">
                        <table class="ann-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Posted By</th>
                                    <th>Date</th>
                                    <th>Likes</th>
                                    <th>Comments</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($announcements as $a): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($a['title']) ?></strong></td>
                                    <td><?= htmlspecialchars($a['username']) ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($a['created_at'])) ?></td>
                                    <td><span class="badge like-count-badge" data-ann="<?= $a['announcement_id'] ?>"><?= (int)$a['like_count'] ?></span></td>
                                    <td><span class="badge comment-count-badge" data-ann="<?= $a['announcement_id'] ?>"><?= (int)$a['comment_count'] ?></span></td>
                                    <td>
                                        <div class="action-buttons" style="display: flex; gap: 8px;">
                                            <button class="btn btn-info btn-sm" data-id="<?= $a['announcement_id'] ?>">View</button>
                                            <?php if($a['created_by'] == $user_id): ?>
                                                <a class="btn btn-success btn-sm" href="?edit=<?= $a['announcement_id'] ?>">Edit</a>
                                                <a class="btn btn-danger btn-sm" href="?delete=<?= $a['announcement_id'] ?>" onclick="return confirm('Delete this announcement?')">Delete</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Details row (collapsed by default) -->
                                <tr class="details-row" id="details-<?= $a['announcement_id'] ?>" style="display:none;">
                                    <td colspan="6">
                                        <div class="details-content">
                                            <div class="details-left">
                                                <h3><?= htmlspecialchars($a['title']) ?></h3>
                                                <div class="text-muted" style="margin-bottom: 15px;">
                                                    Posted by <?= htmlspecialchars($a['username']) ?> on <?= date('M d, Y H:i', strtotime($a['created_at'])) ?>
                                                </div>
                                                <p style="line-height: 1.6;"><?= nl2br(htmlspecialchars($a['message'])) ?></p>

                                                <?php if($a['attachment']): ?>
                                                    <p style="margin-top: 15px;">
                                                        <a href="../../uploads/announcements/<?= htmlspecialchars($a['attachment']) ?>" target="_blank" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-download"></i> Download Attachment
                                                        </a>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if($a['external_link']): ?>
                                                    <p style="margin-top: 10px;">
                                                        <a href="<?= htmlspecialchars($a['external_link']) ?>" target="_blank" class="btn btn-info btn-sm">
                                                            <i class="fas fa-external-link-alt"></i> External Link
                                                        </a>
                                                    </p>
                                                <?php endif; ?>

                                                <!-- Like / Likes info -->
                                                <div style="margin-top:20px; display: flex; align-items: center; gap: 10px;">
                                                    <button class="like-btn" data-id="<?= $a['announcement_id'] ?>">
                                                        <?= $a['like_count'] > 0 ? '❤️' : '🤍' ?> <span class="like-count-inline"><?= (int)$a['like_count'] ?></span>
                                                    </button>
                                                    <span class="text-muted">
                                                        <small class="liked-users"><?= htmlspecialchars($a['liked_users'] ?: 'No likes yet') ?></small>
                                                    </span>
                                                </div>

                                                <!-- Comments -->
                                                <div class="comment-section" id="comments-<?= $a['announcement_id'] ?>"></div>

                                                <form class="add-comment-form" data-id="<?= $a['announcement_id'] ?>">
                                                    <input type="text" name="comment" placeholder="Add a comment..." required>
                                                    <button type="submit">Post</button>
                                                </form>
                                            </div>

                                            <div class="details-right">
                                                <h4 style="margin-bottom: 15px;">Announcement Details</h4>
                                                <p><strong>ID:</strong> <?= (int)$a['announcement_id'] ?></p>
                                                <p><strong>Department:</strong> <?= htmlspecialchars($a['department_id'] ?? 'Global') ?></p>
                                                <p><strong>Status:</strong> <span class="badge">Active</span></p>
                                                <?php if($a['updated_at'] && $a['updated_at'] != $a['created_at']): ?>
                                                    <p><strong>Last Updated:</strong> <?= date('M d, Y H:i', strtotime($a['updated_at'])) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bullhorn"></i>
                        <h3>No Announcements Found</h3>
                        <p>No announcements have been posted yet. Create your first announcement using the form above.</p>
                    </div>
                <?php endif; ?>
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
        });

        // Helper: safe JSON fetch with error handling
        async function safeFetch(url, options = {}) {
            try {
                const res = await fetch(url, options);
                if(!res.ok) throw new Error('Network response was not ok: ' + res.status);
                const data = await res.json();
                return data;
            } catch (err) {
                console.error('Fetch error:', err);
                return { error: err.message || 'Fetch error' };
            }
        }

        // Toggle details
        document.querySelectorAll('.btn-info').forEach(btn => {
            btn.addEventListener('click', () => {
                const aid = btn.dataset.id;
                const details = document.getElementById('details-' + aid);
                if(details.style.display === 'none' || details.style.display === ''){
                    details.style.display = 'table-row';
                    loadComments(aid);
                } else {
                    details.style.display = 'none';
                }
            });
        });

        // Load comments for an announcement
        async function loadComments(aid){
            const container = document.getElementById('comments-' + aid);
            container.innerHTML = '<em>Loading comments...</em>';
            const data = await safeFetch(`../common/load_comments.php?announcement_id=${aid}`);
            if(data.error){
                container.innerHTML = `<div style="color:#ef4444">Error loading comments: ${data.error}</div>`;
                return;
            }
            container.innerHTML = '';
            if(data.length === 0){
                container.innerHTML = '<div style="color:var(--text-secondary)">No comments yet.</div>';
            } else {
                data.forEach(c => {
                    const div = document.createElement('div');
                    div.className = 'comment';
                    const left = document.createElement('div');
                    left.innerHTML = `<div class="meta"><strong>${escapeHtml(c.username)}</strong> <small style="color:var(--text-secondary)"> • ${c.created_at}</small></div>
                                      <div style="margin-top:6px;">${escapeHtml(c.comment)}</div>`;
                    const btn = document.createElement('button');
                    btn.className = 'delete-comment';
                    btn.textContent = 'X';
                    btn.title = 'Delete comment';
                    btn.addEventListener('click', async () => {
                        if(!confirm('Delete this comment?')) return;
                        const resp = await safeFetch('../common/delete_comment.php', {
                            method: 'POST',
                            headers: {'Content-Type':'application/x-www-form-urlencoded'},
                            body: `comment_id=${encodeURIComponent(c.comment_id)}`
                        });
                        if(resp && resp.success){
                            await loadComments(aid);
                            // update comment count badge
                            document.querySelector('.comment-count-badge[data-ann="'+aid+'"]').textContent = resp.new_comment_count ?? 0;
                        } else {
                            alert('Error deleting comment: ' + (resp.error || 'unknown'));
                        }
                    });
                    div.appendChild(left);
                    div.appendChild(btn);
                    container.appendChild(div);
                });
            }
            // update comment count badge
            const badge = document.querySelector('.comment-count-badge[data-ann="'+aid+'"]');
            if(badge) badge.textContent = data.length;
        }

        // Add comment
        document.querySelectorAll('.add-comment-form').forEach(form=>{
            form.addEventListener('submit', async e=>{
                e.preventDefault();
                const aid = form.dataset.id;
                const commentInput = form.querySelector('input[name="comment"]');
                const comment = commentInput.value.trim();
                if(!comment) return;
                const res = await safeFetch('../common/add_comment.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: `announcement_id=${encodeURIComponent(aid)}&comment=${encodeURIComponent(comment)}`
                });
                if(res && res.success){
                    commentInput.value = '';
                    await loadComments(aid);
                    // update comment count badge
                    document.querySelector('.comment-count-badge[data-ann="'+aid+'"]').textContent = res.new_comment_count ?? '';
                } else {
                    alert('Error adding comment: ' + (res.error || 'unknown'));
                }
            });
        });

        // Like button (in details)
        document.addEventListener('click', function(e){
            if(e.target && e.target.matches('.like-btn')){
                const aid = e.target.dataset.id;
                (async ()=>{
                    const res = await safeFetch(`../common/like_announcement.php?announcement_id=${aid}`);
                    if(res && !res.error){
                        // update inline like count
                        const inline = e.target.querySelector('.like-count-inline');
                        if(inline) inline.textContent = res.like_count;
                        // update table badge
                        const badge = document.querySelector('.like-count-badge[data-ann="'+aid+'"]');
                        if(badge) badge.textContent = res.like_count;
                        // toggle heart (server returns liked_by_user)
                        e.target.innerHTML = (res.liked_by_user ? '❤️' : '🤍') + ' <span class="like-count-inline">' + res.like_count + '</span>';
                    } else {
                        alert('Error toggling like: ' + (res.error || 'unknown'));
                    }
                })();
            }
        });

        // small helper to escape html
        function escapeHtml(unsafe) {
            if(unsafe === null || unsafe === undefined) return '';
            return unsafe
                 .replaceAll('&','&amp;')
                 .replaceAll('<','&lt;')
                 .replaceAll('>','&gt;')
                 .replaceAll('"','&quot;')
                 .replaceAll("'",'&#039;');
        }
    </script>
</body>
</html>