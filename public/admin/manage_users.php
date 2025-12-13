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

// Fetch current user info
$user_stmt = $pdo->prepare("SELECT username, email, profile_picture FROM users WHERE user_id=?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch();

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
        __DIR__ . '/../../uploads/profiles/' . $profile_picture,
        'uploads/' . $profile_picture,
        '../uploads/' . $profile_picture,
        '../../uploads/profiles/' . $profile_picture,
    ];
    
    foreach ($locations as $location) {
        if (file_exists($location)) {
            // Return the appropriate web path
            if (strpos($location, '../../uploads/profiles/') !== false) {
                return '../../uploads/profiles/' . $profile_picture;
            } elseif (strpos($location, '../../uploads/') !== false) {
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
$profile_img_path = getProfilePicturePath($current_user['profile_picture'] ?? '');

$editing = false;
$edit_id = 0;

// Handle Delete
if(isset($_GET['delete'])){
    $delete_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id=?");
    $stmt->execute([$delete_id]);
    header("Location: manage_users.php");
    exit;
}

// Handle Edit
if(isset($_GET['edit'])){
    $edit_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
    $stmt->execute([$edit_id]);
    $editing = true;
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle Form Submission
if(isset($_POST['save_user'])){
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $student_id = ($_POST['role'] === 'student' && isset($_POST['student_id'])) ? trim($_POST['student_id']) : NULL;
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];
    $department_id = $_POST['department_id'] ?: NULL;
    $year = ($role === 'student' && isset($_POST['year'])) ? (int)$_POST['year'] : NULL;

    if($editing){
        if($password){
            $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, student_id=?, email=?, password=?, role=?, department_id=?, year=? WHERE user_id=?");
            $stmt->execute([$username, $full_name, $student_id, $email, password_hash($password,PASSWORD_DEFAULT), $role, $department_id, $year, $edit_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, student_id=?, email=?, role=?, department_id=?, year=? WHERE user_id=?");
            $stmt->execute([$username, $full_name, $student_id, $email, $role, $department_id, $year, $edit_id]);
        }
        header("Location: manage_users.php");
        exit;
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, full_name, student_id, email, password, role, department_id, year) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $full_name, $student_id, $email, password_hash($password,PASSWORD_DEFAULT), $role, $department_id, $year]);
    }
}

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
<!-- Include Dark Mode CSS -->
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
/* ================= General Reset ================= */
* { margin:0; padding:0; box-sizing:border-box; font-family: "Segoe UI", Arial, sans-serif; }
body { display:flex; min-height:100vh; background: var(--bg-primary); overflow-x:hidden; }

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
    height:100%; 
    background:var(--bg-sidebar); 
    color:var(--text-sidebar);
    z-index:1100;
    transition: transform 0.3s ease;
    padding: 20px 0;
}
.sidebar.hidden { transform:translateX(-260px); }

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
}
.sidebar a:hover, .sidebar a.active { background:#1abc9c; color:white; }

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
    padding:10px; 
    margin:8px 5px 12px 0; 
    border-radius:8px; 
    font-size:14px; 
    border:1px solid var(--border-color);
    background: var(--bg-secondary);
    color: var(--text-primary);
}
.user-form button { 
    background:#2563eb; 
    color:#fff; 
    border:none; 
    cursor:pointer; 
    transition:0.2s; 
    font-weight: 600;
}
.user-form button:hover { background:#1d4ed8; transform: translateY(-1px); }
.cancel-btn { 
    text-decoration:none; 
    color:#dc2626; 
    margin-left:10px; 
    font-weight: 500;
    padding: 10px 15px;
    border-radius: 8px;
    border: 1px solid #dc2626;
    transition: all 0.3s;
}
.cancel-btn:hover {
    background: #dc2626;
    color: white;
}

.form-group { margin-bottom:15px; }
.form-group label { 
    display:block; 
    margin-bottom:5px; 
    font-weight:500; 
    color:var(--text-primary); 
}
.form-group input, .form-group select { 
    width:100%; 
    padding:12px; 
    border-radius:8px; 
    border:1px solid var(--border-color); 
    font-size:14px; 
    background: var(--bg-secondary);
    color: var(--text-primary);
    transition: all 0.3s;
}
.form-group input:focus, .form-group select:focus { 
    outline:none; 
    border-color:#2563eb; 
    box-shadow:0 0 0 3px rgba(37, 99, 235, 0.2); 
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
    width:100%; 
    border-collapse:collapse; 
    min-width:1000px; 
    background: var(--bg-card);
}
.user-table th, .user-table td { 
    padding:15px; 
    text-align:left; 
    border-bottom:1px solid var(--border-color); 
    color: var(--text-primary);
}
.user-table th { 
    background:var(--table-header); 
    color:var(--text-sidebar); 
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.user-table tr:nth-child(even){ background:var(--bg-secondary); }
.user-table tr:hover { background:var(--hover-color); }
.button-action { 
    padding:8px 15px; 
    border-radius:6px; 
    text-decoration:none; 
    color:#fff; 
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    display: inline-block;
    margin: 0 2px;
}
.button-edit { background:#2563eb; } 
.button-delete { background:#dc2626; }
.button-edit:hover { background:#1d4ed8; transform: translateY(-1px); } 
.button-delete:hover { background:#b91c1c; transform: translateY(-1px); }

.role-badge { 
    display: inline-block; 
    padding: 5px 12px; 
    border-radius: 12px; 
    font-size: 0.8rem; 
    font-weight: 600; 
    text-transform: uppercase; 
    letter-spacing: 0.5px;
}
.role-admin { background:#ef4444; color:white; }
.role-student { background:#10b981; color:white; }
.role-instructor { background:#3b82f6; color:white; }
.role-department_head { background:#8b5cf6; color:white; }

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

/* ================= Responsive ================= */
@media (max-width: 1200px){ 
    .main-content{ padding:25px; }
    .content-wrapper { padding: 20px; }
}
@media (max-width: 768px){
    .topbar{ display:flex; }
    .sidebar{ transform:translateX(-100%); }
    .sidebar.active{ transform:translateX(0); }
    .main-content{ 
        margin-left:0; 
        padding: 15px;
        padding-top: 80px;
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
    .user-form{ padding:15px; }
    .user-form input, .user-form select{ width:100%; margin:8px 0; }

    /* Mobile-friendly card-style table */
    .user-table, .user-table thead, .user-table tbody, .user-table th, .user-table td, .user-table tr { 
        display:block; 
        width:100%; 
    }
    .user-table thead tr { display:none; }
    .user-table tr { 
        margin-bottom:15px; 
        background:var(--bg-card); 
        border-radius:10px; 
        box-shadow:0 2px 5px var(--shadow-color); 
        padding:15px; 
        border: 1px solid var(--border-color);
    }
    .user-table td { 
        text-align:right; 
        padding-left:50%; 
        position:relative; 
        border:none; 
        margin-bottom: 10px;
    }
    .user-table td::before { 
        content: attr(data-label); 
        position:absolute; 
        left:15px; 
        width:45%; 
        text-align:left; 
        font-weight:bold; 
        color: var(--text-secondary);
    }
    
    /* Action buttons in mobile */
    .action-buttons {
        justify-content: flex-end;
    }
}
</style>
</head>
<body>

<!-- Topbar for Mobile -->
<div class="topbar">
    <button class="menu-btn" onclick="toggleSidebar()">☰</button>
    <h2>Manage Users</h2>
</div>

<!-- Overlay for Mobile -->
<div class="overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-profile">
        <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic"
             onerror="this.onerror=null; this.src='../assets/default_profile.png';">
        <p><?= htmlspecialchars($current_user['username']) ?></p>
    </div>
    <h2>Admin Panel</h2>
    <a href="dashboard.php" class="<?= $current_page=='dashboard.php'?'active':'' ?>">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="manage_users.php" class="active">
        <i class="fas fa-users"></i> Manage Users
    </a>
    <a href="approve_users.php" class="<?= $current_page=='approve_users.php'?'active':'' ?>">
        <i class="fas fa-user-check"></i> Approve Users
    </a>
    <a href="manage_courses.php" class="<?= $current_page=='manage_courses.php'?'active':'' ?>">
        <i class="fas fa-book"></i> Manage Courses
    </a>
    <a href="manage_rooms.php" class="<?= $current_page=='manage_rooms.php'?'active':'' ?>">
        <i class="fas fa-door-closed"></i> Manage Rooms
    </a>
    <a href="manage_schedule.php" class="<?= $current_page=='manage_schedule.php'?'active':'' ?>">
        <i class="fas fa-calendar-alt"></i> Manage Schedule
    </a>
    <a href="manage_announcements.php" class="<?= $current_page=='manage_announcements.php'?'active':'' ?>">
        <i class="fas fa-bullhorn"></i> Announcements
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

        <!-- Form Section -->
        <div class="user-form-section">
            <div class="form-section-title">
                <i class="fas fa-<?= $editing ? 'edit' : 'user-plus' ?>"></i>
                <?= $editing ? "Edit User" : "Add New User" ?>
            </div>

            <form method="POST" class="user-form">
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" name="username" value="<?= $editing ? htmlspecialchars($edit_data['username']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Full Name:</label>
                    <input type="text" name="full_name" value="<?= $editing ? htmlspecialchars($edit_data['full_name']) : '' ?>" required>
                </div>
                
                <div class="form-group" id="student-id-group" style="display:none;">
                    <label>Student ID:</label>
                    <input type="text" name="student_id" id="student-id-input" value="<?= $editing && isset($edit_data['student_id']) ? htmlspecialchars($edit_data['student_id']) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" value="<?= $editing ? htmlspecialchars($edit_data['email']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Password: <?= $editing ? "<small>(Leave blank to keep current)</small>" : "" ?></label>
                    <input type="password" name="password" <?= !$editing ? 'required' : '' ?>>
                </div>
                
                <div class="form-group">
                    <label>Role:</label>
                    <select name="role" id="role-select" required>
                        <option value="">--Select Role--</option>
                        <option value="admin" <?= ($editing && $edit_data['role']=='admin')?'selected':'' ?>>Admin</option>
                        <option value="student" <?= ($editing && $edit_data['role']=='student')?'selected':'' ?>>Student</option>
                        <option value="instructor" <?= ($editing && $edit_data['role']=='instructor')?'selected':'' ?>>Instructor</option>
                        <option value="department_head" <?= ($editing && $edit_data['role']=='department_head')?'selected':'' ?>>Department Head</option>
                    </select>
                </div>
                
                <div class="form-group" id="year-group" style="display:none;">
                    <label>Year (for students):</label>
                    <select name="year" id="year-select">
                        <option value="">--Select Year--</option>
                        <option value="1" <?= ($editing && $edit_data['role']=='student' && $edit_data['year']==1)?'selected':'' ?>>Year 1</option>
                        <option value="2" <?= ($editing && $edit_data['role']=='student' && $edit_data['year']==2)?'selected':'' ?>>Year 2</option>
                        <option value="3" <?= ($editing && $edit_data['role']=='student' && $edit_data['year']==3)?'selected':'' ?>>Year 3</option>
                        <option value="4" <?= ($editing && $edit_data['role']=='student' && $edit_data['year']==4)?'selected':'' ?>>Year 4</option>
                        <option value="5" <?= ($editing && $edit_data['role']=='student' && $edit_data['year']==5)?'selected':'' ?>>Year 5</option>
                    </select>
                </div>
                
                <div class="form-group" id="department-group" style="display:none;">
                    <label>Department:</label>
                    <select name="department_id" id="department-select">
                        <option value="">--Select Department--</option>
                        <?php foreach($departments as $d): ?>
                            <option value="<?= $d['department_id'] ?>" <?= ($editing && $edit_data['department_id']==$d['department_id'])?'selected':'' ?>>
                                <?= htmlspecialchars($d['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="save_user">
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
                                        <?= htmlspecialchars($u['role']) ?>
                                    </span>
                                </td>
                                <td data-label="Year">
                                    <?php if($u['role'] === 'student' && !empty($u['year'])): ?>
                                        <span class="year-badge">Year <?= $u['year'] ?></span>
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
                                        <a class="button-action button-delete" href="?delete=<?= $u['user_id'] ?>" onclick="return confirm('Are you sure you want to delete this user?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
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

<!-- Include darkmode.js -->
<script src="../../assets/js/darkmode.js"></script>
<script>
function toggleSidebar(){
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
    
    // Add animation to table rows
    const tableRows = document.querySelectorAll('.user-table tbody tr');
    tableRows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            row.style.transition = 'all 0.5s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateX(0)';
        }, index * 50);
    });
    
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

// Show/hide department, year, and student ID fields based on role
const roleSelect = document.getElementById('role-select');
const yearGroup = document.getElementById('year-group');
const yearSelect = document.getElementById('year-select');
const studentIdGroup = document.getElementById('student-id-group');
const studentIdInput = document.getElementById('student-id-input');
const departmentGroup = document.getElementById('department-group');
const departmentSelect = document.getElementById('department-select');

function toggleRoleFields(){
    const role = roleSelect.value;
    
    // Show/hide year and student ID fields (only for students)
    if(role === 'student'){
        yearGroup.style.display = 'block';
        yearSelect.required = true;
        studentIdGroup.style.display = 'block';
        studentIdInput.required = true;
    } else {
        yearGroup.style.display = 'none';
        yearSelect.required = false;
        yearSelect.value = '';
        studentIdGroup.style.display = 'none';
        studentIdInput.required = false;
        studentIdInput.value = '';
    }
    
    // Show/hide department field (for students, instructors, department heads)
    if(['student', 'instructor', 'department_head'].includes(role)){
        departmentGroup.style.display = 'block';
        departmentSelect.required = true;
    } else {
        departmentGroup.style.display = 'none';
        departmentSelect.required = false;
        departmentSelect.value = '';
    }
}

// Initialize on page load
roleSelect.addEventListener('change', toggleRoleFields);
window.addEventListener('load', function() {
    toggleRoleFields();
    
    // If editing a student, make sure year and student ID fields are visible
    <?php if($editing && $edit_data['role'] === 'student'): ?>
        yearGroup.style.display = 'block';
        yearSelect.required = true;
        studentIdGroup.style.display = 'block';
        studentIdInput.required = true;
    <?php endif; ?>
    
    // If editing a user that needs department, make sure department field is visible
    <?php if($editing && in_array($edit_data['role'], ['student', 'instructor', 'department_head'])): ?>
        departmentGroup.style.display = 'block';
        departmentSelect.required = true;
    <?php endif; ?>
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