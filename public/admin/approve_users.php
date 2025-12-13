<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only allow admin
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

// Handle approval action
if(isset($_GET['approve'])){
    $user_id = intval($_GET['approve']);
    $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: approve_users.php");
    exit;
}

// Handle reject action
if(isset($_GET['reject'])){
    $user_id = intval($_GET['reject']);
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND is_approved = 0");
    $stmt->execute([$user_id]);
    header("Location: approve_users.php");
    exit;
}

// Handle bulk actions
if(isset($_POST['bulk_action']) && isset($_POST['selected_users'])) {
    $action = $_POST['bulk_action'];
    $selected_users = $_POST['selected_users'];
    
    if($action === 'approve_all') {
        $placeholders = implode(',', array_fill(0, count($selected_users), '?'));
        $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE user_id IN ($placeholders)");
        $stmt->execute($selected_users);
    } elseif($action === 'reject_all') {
        $placeholders = implode(',', array_fill(0, count($selected_users), '?'));
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id IN ($placeholders) AND is_approved = 0");
        $stmt->execute($selected_users);
    }
    
    header("Location: approve_users.php");
    exit;
}

// Fetch pending users
$pending_users = $pdo->query("
    SELECT u.user_id, u.username, u.full_name, u.email, u.student_id, u.role, u.created_at,
           d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE u.is_approved = 0
    ORDER BY u.created_at DESC
")->fetchAll();

$pending_count = count($pending_users);

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approve Users - DKU Scheduler</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Include Dark Mode CSS -->
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
/* =============== RESET =============== */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: "Segoe UI", Arial, sans-serif;
}

body {
  display: flex;
  min-height: 100vh;
  background: var(--bg-primary);
  overflow-x: hidden;
}

/* =============== Topbar for Mobile =============== */
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

/* =============== SIDEBAR =============== */
.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  width: 250px;
  height: 100vh;
  background: var(--bg-sidebar);
  padding: 20px 0;
  display: flex;
  flex-direction: column;
  z-index: 1000;
  transition: transform 0.3s ease;
}
.sidebar.hidden { transform: translateX(-100%); }

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

/* =============== Overlay =============== */
.overlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.4); z-index:1050;
    display:none; opacity:0; transition: opacity 0.3s ease;
}
.overlay.active { display:block; opacity:1; }

/* =============== MAIN CONTAINER =============== */
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

/* Stats Card */
.stats-card {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    display: flex;
    align-items: center;
    gap: 15px;
}

.stats-card i {
    font-size: 2.5rem;
    opacity: 0.9;
}

.stats-card div h3 {
    font-size: 1.5rem;
    margin-bottom: 5px;
    font-weight: 700;
}

.stats-card div p {
    opacity: 0.9;
    font-size: 0.95rem;
}

/* Bulk Actions */
.bulk-actions {
    background: var(--bg-secondary);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.bulk-select {
    display: flex;
    align-items: center;
    gap: 10px;
}

.bulk-select input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.bulk-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.bulk-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.bulk-btn.approve {
    background: #10b981;
    color: white;
}

.bulk-btn.approve:hover {
    background: #059669;
    transform: translateY(-2px);
}

.bulk-btn.reject {
    background: #ef4444;
    color: white;
}

.bulk-btn.reject:hover {
    background: #dc2626;
    transform: translateY(-2px);
}

/* =============== TABLE =============== */
.table-container {
  width: 100%;
  overflow-x: auto;
  background: var(--bg-card);
  border-radius: 12px;
  box-shadow: 0 4px 6px var(--shadow-color);
  border: 1px solid var(--border-color);
  -webkit-overflow-scrolling: touch;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 900px;
}

th, td {
  padding: 15px;
  text-align: left;
  border-bottom: 1px solid var(--border-color);
  font-size: 14px;
  color: var(--text-primary);
}

th {
  background: var(--table-header);
  color: var(--text-sidebar);
  text-transform: uppercase;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.5px;
}

tr:nth-child(even) { background: var(--bg-secondary); }
tr:hover { background: var(--hover-color); }

/* Checkbox column */
td:first-child {
    width: 40px;
    text-align: center;
}

/* Action buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-approve, .btn-reject {
    padding: 8px 15px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-approve {
    background: #10b981;
    color: white;
}

.btn-approve:hover {
    background: #059669;
    transform: translateY(-1px);
}

.btn-reject {
    background: #ef4444;
    color: white;
}

.btn-reject:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

/* Role badges */
.role-badge { 
    display: inline-block; 
    padding: 5px 12px; 
    border-radius: 12px; 
    font-size: 12px; 
    font-weight: 600; 
    text-transform: uppercase; 
    letter-spacing: 0.5px;
}
.role-student { background:#10b981; color:white; }
.role-instructor { background:#3b82f6; color:white; }
.role-department_head { background:#8b5cf6; color:white; }

/* Student ID badge */
.student-id-badge {
    display: inline-block;
    padding: 4px 10px;
    background: var(--badge-secondary-bg);
    color: var(--badge-secondary-text);
    border-radius: 10px;
    font-size: 12px;
    font-weight: 500;
    margin-top: 5px;
    border: 1px solid var(--border-color);
}

/* Time badge */
.time-badge {
    display: inline-block;
    padding: 4px 10px;
    background: var(--bg-secondary);
    color: var(--text-secondary);
    border-radius: 10px;
    font-size: 11px;
    font-weight: 500;
    margin-top: 5px;
    border: 1px solid var(--border-color);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    color: var(--border-color);
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: var(--text-primary);
}

.empty-state p {
    color: var(--text-secondary);
    max-width: 400px;
    margin: 0 auto;
    line-height: 1.5;
}

/* Form for bulk actions */
.bulk-form {
    margin: 0;
    padding: 0;
}

/* =============== RESPONSIVE =============== */
@media (max-width: 1200px) {
  .main-content { padding: 25px; }
  .content-wrapper { padding: 20px; }
}

@media (max-width: 768px) {
  .topbar { display: flex; }
  .sidebar { transform: translateX(-100%); }
  .sidebar.active { transform: translateX(0); }
  .main-content { 
    margin-left: 0; 
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
  
  .bulk-actions {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }
  
  .bulk-buttons {
    width: 100%;
  }
  
  .bulk-btn {
    flex: 1;
    justify-content: center;
  }
  
  .action-buttons {
    flex-direction: column;
    gap: 5px;
  }
  
  .btn-approve, .btn-reject {
    width: 100%;
    justify-content: center;
  }
}

/* =============== MOBILE CARD VIEW =============== */
@media (max-width: 600px) {
  .table-container { overflow-x: visible; }
  table, thead, tbody, th, td, tr { display: block; }
  thead { display: none; }

  tr {
    margin-bottom: 15px;
    background: var(--bg-card);
    border-radius: 12px;
    padding: 15px;
    box-shadow: 0 2px 5px var(--shadow-color);
    border: 1px solid var(--border-color);
  }

  td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: none;
    border-bottom: 1px solid var(--border-color);
    padding: 10px 0;
    font-size: 14px;
  }

  td:last-child { 
    border-bottom: none; 
    padding-top: 15px;
  }
  
  td::before {
    content: attr(data-label);
    font-weight: bold;
    color: var(--text-primary);
    margin-right: 10px;
    min-width: 120px;
  }
  
  /* Special handling for action buttons */
  td[data-label="Actions"]::before {
    align-self: flex-start;
  }
  
  .action-buttons {
    flex-direction: row;
    flex-wrap: wrap;
    justify-content: flex-end;
    width: 100%;
  }
  
  .btn-approve, .btn-reject {
    width: auto;
    min-width: 100px;
  }
}
</style>
</head>
<body>

<!-- Topbar for Mobile -->
<div class="topbar">
  <button class="menu-btn" onclick="toggleSidebar()">☰</button>
  <h2>Approve Users</h2>
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
    <a href="admin_dashboard.php" class="<?= $current_page=='admin_dashboard.php'?'active':'' ?>">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="manage_users.php" class="<?= $current_page=='manage_users.php'?'active':'' ?>">
        <i class="fas fa-users"></i> Manage Users
    </a>
    <a href="approve_users.php" class="active">
        <i class="fas fa-user-check"></i> Approve Users
        <?php if($pending_count > 0): ?>
            <span style="background:#ef4444; color:white; padding:2px 8px; border-radius:10px; font-size:12px; margin-left:auto;">
                <?= $pending_count ?>
            </span>
        <?php endif; ?>
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
                <h1>Approve Users</h1>
                <p style="color: var(--text-secondary); margin-top: 5px;">Review and approve pending user registrations</p>
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

        <!-- Stats Card -->
        <div class="stats-card">
            <i class="fas fa-user-clock"></i>
            <div>
                <h3><?= $pending_count ?> Pending Approval<?= $pending_count !== 1 ? 's' : '' ?></h3>
                <p>Users waiting for review and approval</p>
            </div>
        </div>

        <!-- Bulk Actions -->
        <?php if($pending_count > 0): ?>
        <form method="POST" class="bulk-form">
            <div class="bulk-actions">
                <div class="bulk-select">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                    <label for="selectAll" style="color: var(--text-primary); font-weight: 500;">
                        Select All
                    </label>
                </div>
                <div class="bulk-buttons">
                    <input type="hidden" name="bulk_action" id="bulkAction">
                    <button type="button" class="bulk-btn approve" onclick="submitBulkAction('approve_all')">
                        <i class="fas fa-check-circle"></i> Approve Selected
                    </button>
                    <button type="button" class="bulk-btn reject" onclick="submitBulkAction('reject_all')">
                        <i class="fas fa-times-circle"></i> Reject Selected
                    </button>
                </div>
            </div>

        <!-- Users Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllHeader" onchange="toggleSelectAll()"></th>
                        <th>User Details</th>
                        <th>Role & Department</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pending_users as $user): ?>
                    <tr>
                        <td data-label="Select">
                            <input type="checkbox" name="selected_users[]" value="<?= $user['user_id'] ?>" class="user-checkbox">
                        </td>
                        <td data-label="User Details">
                            <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 5px;">
                                <?= htmlspecialchars($user['username']) ?>
                            </div>
                            <div style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 5px;">
                                <?= htmlspecialchars($user['email']) ?>
                            </div>
                            <?php if(!empty($user['full_name'])): ?>
                                <div style="color: var(--text-primary); font-size: 0.9rem; margin-bottom: 5px;">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($user['full_name']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if(!empty($user['student_id'])): ?>
                                <div class="student-id-badge">
                                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($user['student_id']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Role & Department">
                            <div class="role-badge role-<?= $user['role'] ?>" style="margin-bottom: 8px;">
                                <?= htmlspecialchars($user['role']) ?>
                            </div>
                            <?php if(!empty($user['department_name'])): ?>
                                <div style="color: var(--text-primary); font-size: 0.9rem;">
                                    <i class="fas fa-building"></i> <?= htmlspecialchars($user['department_name']) ?>
                                </div>
                            <?php else: ?>
                                <div style="color: var(--text-secondary); font-size: 0.9rem;">
                                    <i class="fas fa-building"></i> No Department
                                </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Registration Date">
                            <div style="color: var(--text-primary); margin-bottom: 5px;">
                                <?= date('M d, Y', strtotime($user['created_at'])) ?>
                            </div>
                            <div class="time-badge">
                                <i class="fas fa-clock"></i> <?= date('h:i A', strtotime($user['created_at'])) ?>
                            </div>
                        </td>
                        <td data-label="Actions">
                            <div class="action-buttons">
                                <a href="?approve=<?= $user['user_id'] ?>" class="btn-approve">
                                    <i class="fas fa-check"></i> Approve
                                </a>
                                <a href="?reject=<?= $user['user_id'] ?>" class="btn-reject" onclick="return confirm('Are you sure you want to reject this user?')">
                                    <i class="fas fa-times"></i> Reject
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </form>
        <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-user-check"></i>
            <h3>No Pending Approvals</h3>
            <p>All users have been approved. Check back later for new registration requests.</p>
        </div>
        <?php endif; ?>
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
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            row.style.transition = 'all 0.5s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateX(0)';
        }, index * 100);
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

// Bulk selection functions
function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const selectAllHeader = document.getElementById('selectAllHeader');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    
    const isChecked = selectAllCheckbox.checked || selectAllHeader.checked;
    userCheckboxes.forEach(checkbox => {
        checkbox.checked = isChecked;
    });
    
    // Sync both select all checkboxes
    selectAllCheckbox.checked = isChecked;
    selectAllHeader.checked = isChecked;
}

function submitBulkAction(action) {
    const selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
    
    if (selectedUsers.length === 0) {
        alert('Please select at least one user to perform this action.');
        return;
    }
    
    const actionText = action === 'approve_all' ? 'approve' : 'reject';
    if (confirm(`Are you sure you want to ${actionText} ${selectedUsers.length} user(s)?`)) {
        document.getElementById('bulkAction').value = action;
        document.querySelector('.bulk-form').submit();
    }
}

// Individual checkbox change handler
document.addEventListener('DOMContentLoaded', function() {
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    const selectAllHeader = document.getElementById('selectAllHeader');
    
    userCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allChecked = Array.from(userCheckboxes).every(cb => cb.checked);
            const anyChecked = Array.from(userCheckboxes).some(cb => cb.checked);
            
            selectAllCheckbox.checked = allChecked;
            selectAllHeader.checked = allChecked;
            
            // Indeterminate state for select all
            selectAllCheckbox.indeterminate = anyChecked && !allChecked;
            selectAllHeader.indeterminate = anyChecked && !allChecked;
        });
    });
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