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

// Quick stats
$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$total_faculty  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='faculty'")->fetchColumn();
$total_courses  = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$total_rooms    = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
// Pending approvals count
$pending_approvals = $pdo->query("SELECT COUNT(*) FROM users WHERE is_approved=0 AND role IN ('student', 'faculty')")->fetchColumn();

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Include Dark Mode CSS -->
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
* { box-sizing: border-box; margin:0; padding:0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

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
    position: fixed; top:0; left:0;
    width:250px; height:100%;
    background:var(--bg-sidebar); color:var(--text-sidebar);
    z-index:1100;
    transition: transform 0.3s ease;
    padding: 20px 0;
    box-shadow: 2px 0 10px rgba(0,0,0,0.2);
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
/* ================= Sidebar ================= */
.sidebar {
    position: fixed; top:0; left:0;
    width:250px; height:100%;
    background:var(--bg-sidebar); color:var(--text-sidebar);
    z-index:1100;
    transition: transform 0.3s ease;
    box-shadow: 2px 0 10px rgba(0,0,0,0.2);
    display: flex;
    flex-direction: column;
}

/* Sidebar scrollable content */
.sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding: 20px 0;
}

/* Custom scrollbar for sidebar */
.sidebar-content::-webkit-scrollbar {
    width: 6px;
}

.sidebar-content::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.1);
    border-radius: 3px;
}

.sidebar-content::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.3);
    border-radius: 3px;
}

.sidebar-content::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.4);
}

/* For Firefox */
.sidebar-content {
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.3) rgba(255,255,255,0.1);
}

.sidebar-profile {
    text-align: center;
    margin-bottom: 25px;
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    flex-shrink: 0; /* Prevent shrinking */
}

.sidebar h2 {
    text-align: center;
    color: var(--text-sidebar);
    margin-bottom: 25px;
    font-size: 22px;
    padding: 0 20px;
    flex-shrink: 0; /* Prevent shrinking */
}

/* Sidebar navigation items */
.sidebar nav {
    flex: 1;
    overflow-y: auto;
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
    flex-shrink: 0; /* Prevent shrinking */
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
    margin-left: 250px;
    padding:30px;
    min-height:100vh;
    background: var(--bg-primary);
    transition: all 0.3s ease;
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

/* Welcome section */
.welcome-section {
    background: linear-gradient(135deg,#6366f1,#3b82f6);
    color:white;
    padding:30px 25px;
    border-radius:15px;
    margin-bottom:30px;
    box-shadow:0 6px 18px rgba(0,0,0,0.1);
}
.welcome-section h1 { font-size:28px; font-weight:600; margin-bottom:8px; }
.welcome-section p { font-size:16px; opacity:0.9; }

/* Management Cards */
.management-cards { 
    display:flex; 
    flex-wrap:wrap; 
    gap:20px; 
    margin-bottom:30px; 
}
.management-cards .card {
    flex:1 1 220px;
    background: var(--bg-card);
    border-radius:15px;
    padding:25px 20px;
    box-shadow:0 6px 20px var(--shadow-color);
    display:flex; 
    flex-direction:column; 
    justify-content:space-between;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid var(--border-color);
}
.management-cards .card:hover { 
    transform:translateY(-6px); 
    box-shadow:0 10px 28px var(--shadow-lg);
}
.card-icon { 
    font-size:40px; 
    margin-bottom:15px; 
    padding:15px; 
    width:60px; 
    height:60px; 
    display:flex; 
    align-items:center; 
    justify-content:center; 
    border-radius:50%; 
    background:var(--bg-secondary); 
    color:#4f46e5; 
}
.card h3 { 
    font-size:18px; 
    margin-bottom:8px; 
    color:var(--text-primary); 
    font-weight:600; 
}
.card p { 
    font-size:24px; 
    font-weight:bold; 
    color:var(--text-primary); 
    margin-bottom:15px; 
}
.card a { 
    display:inline-block; 
    text-decoration:none; 
    color:white; 
    background-color:#4f46e5; 
    padding:10px 18px; 
    border-radius:8px; 
    font-size:14px; 
    font-weight:500; 
    text-align:center; 
    transition: background-color 0.3s, transform 0.2s; 
}
.card a:hover { 
    background-color:#3b36c4; 
    transform:translateY(-2px); 
}

/* Stats Cards */
.stats-cards {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.stat-card {
    flex: 1;
    min-width: 200px;
    background: var(--bg-card);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px var(--shadow-lg);
}

.stat-card h3 {
    font-size: 1rem;
    color: var(--text-secondary);
    margin-bottom: 10px;
    font-weight: 600;
}

.stat-card .number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--text-primary);
    margin-bottom: 10px;
}

.stat-card .icon {
    font-size: 2rem;
    margin-bottom: 15px;
    display: block;
}

/* Icon colors */
.stat-card .fa-user-graduate.icon { color: #3b82f6; }
.stat-card .fa-chalkboard-teacher.icon { color: #10b981; }
.stat-card .fa-book.icon { color: #8b5cf6; }
.stat-card .fa-door-closed.icon { color: #f59e0b; }
.stat-card .fa-user-clock.icon { color: #ef4444; }

/* Pending approvals badge */
.pending-badge {
    background: #ef4444;
    color: white;
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 8px;
}

[data-theme="dark"] .pending-badge {
    background: #dc2626;
}

/* Quick Actions Section */
.quick-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

.quick-actions h2 {
    color: var(--text-primary);
    margin-bottom: 20px;
    font-size: 1.5rem;
    font-weight: 600;
}

.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.3s;
}

.action-btn:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(37, 99, 235, 0.3);
}

[data-theme="dark"] .action-btn {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
}

[data-theme="dark"] .action-btn:hover {
    background: linear-gradient(135deg, #1d4ed8, #1e40af);
}

/* Dark mode specific adjustments */
[data-theme="dark"] .management-cards .card a {
    background-color: #3730a3;
}

[data-theme="dark"] .management-cards .card a:hover {
    background-color: #312e81;
}

[data-theme="dark"] .welcome-section {
    background: linear-gradient(135deg, #3730a3, #1d4ed8);
}

/* ================= Responsive ================= */
@media(max-width: 768px){
    .topbar { display:flex; }
    .sidebar { transform:translateX(-100%); }
    .sidebar.active { transform:translateX(0); }
    .main-content { 
        margin-left: 0; 
        padding: 20px 15px;
        padding-top: 80px;
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
    .header h1 { font-size: 1.8rem; }
    .stats-cards { flex-direction: column; }
    .stat-card { min-width: auto; }
    .management-cards .card { 
        flex: 1 1 100%; 
        max-width: 100%;
    }
    .action-buttons {
        flex-direction: column;
    }
    .action-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
</head>
<body>

<!-- Topbar with Hamburger -->
<div class="topbar">
    <button class="menu-btn" onclick="toggleSidebar()">☰</button>
    <span>Admin Dashboard</span>
</div>

<!-- Overlay for mobile -->
<div class="overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Scrollable content wrapper -->
    <div class="sidebar-content">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic"
                 onerror="this.onerror=null; this.src='../assets/default_profile.png';">
            <p><?= htmlspecialchars($current_user['username']) ?></p>
        </div>
        <h2>Admin Panel</h2>
        
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
            <a href="manage_exam_schedules.php" class="<?= $current_page=='manage_exam_schedules.php'?'active':'' ?>">
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
<!-- Main content -->
<div class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="header">
            <div class="welcome-section">
                <h1>Welcome, <?= htmlspecialchars($current_user['username']); ?> 👋</h1>
                <p>This is your admin dashboard. Manage users, courses, rooms, and announcements here.</p>
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

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <i class="fas fa-user-graduate icon"></i>
                <h3>Total Students</h3>
                <div class="number"><?= $total_students ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-chalkboard-teacher icon"></i>
                <h3>Total Faculty</h3>
                <div class="number"><?= $total_faculty ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-book icon"></i>
                <h3>Total Courses</h3>
                <div class="number"><?= $total_courses ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-door-closed icon"></i>
                <h3>Total Rooms</h3>
                <div class="number"><?= $total_rooms ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-clock icon"></i>
                <h3>Pending Approvals</h3>
                <div class="number"><?= $pending_approvals ?></div>
            </div>
        </div>

        <!-- Management Cards -->
        <div class="management-cards">
            <div class="card">
                <div class="card-icon">👨‍🎓</div>
                <h3>Users</h3>
                <p><?= $total_students + $total_faculty ?></p>
                <a href="manage_users.php">Manage Users</a>
            </div>
            <div class="card">
                <div class="card-icon">👩‍🏫</div>
                <h3>Approve Users</h3>
                <p><?= $pending_approvals ?> pending</p>
                <a href="approve_users.php">Approve Users</a>
            </div>
            <div class="card">
                <div class="card-icon">📚</div>
                <h3>Courses</h3>
                <p><?= $total_courses ?></p>
                <a href="manage_courses.php">Manage Courses</a>
            </div>
            <div class="card">
                <div class="card-icon">🏫</div>
                <h3>Rooms</h3>
                <p><?= $total_rooms ?></p>
                <a href="manage_rooms.php">Manage Rooms</a>
            </div>
            <div class="card">
                <div class="card-icon">📢</div>
                <h3>Announcements</h3>
                <p>View / Post</p>
                <a href="manage_announcements.php">Manage Announcements</a>
            </div>
            <div class="card">
                <div class="card-icon">📅</div>
                <h3>Schedule</h3>
                <p>Manage</p>
                <a href="manage_schedule.php">Manage Schedule</a>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="action-buttons">
                <a href="manage_users.php" class="action-btn">
                    <i class="fas fa-user-plus"></i> Add New User
                </a>
                <a href="manage_courses.php" class="action-btn">
                    <i class="fas fa-plus-circle"></i> Add New Course
                </a>
                <a href="manage_rooms.php" class="action-btn">
                    <i class="fas fa-door-open"></i> Add New Room
                </a>
                <a href="manage_announcements.php" class="action-btn">
                    <i class="fas fa-bullhorn"></i> Post Announcement
                </a>
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
    
    // Add animation to stats cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Add animation to management cards
    const mgmtCards = document.querySelectorAll('.management-cards .card');
    mgmtCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, (index * 100) + 200);
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