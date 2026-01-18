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
<title>Admin Dashboard | Debark University</title>
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
    box-shadow: 2px 0 10px rgba(0,0,0,0.2);
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
    padding:30px;
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
    
    .management-cards .card { 
        flex: 1 1 100%; 
        max-width: 100%;
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
            Admin Dashboard
        </div>
    </div>

    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Admin Dashboard</h2>
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
        
        // Add animation to management cards
        const mgmtCards = document.querySelectorAll('.management-cards .card');
        mgmtCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
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