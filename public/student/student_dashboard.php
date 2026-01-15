<?php
session_start();

// Check if user is logged in and is a student
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

require __DIR__ . '/../../includes/db.php';

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

$student_id = $_SESSION['user_id'];

// Fetch student's info (username, profile picture, email, AND YEAR)
$user_stmt = $pdo->prepare("SELECT username, profile_picture, email, year FROM users WHERE user_id = ?");
$user_stmt->execute([$student_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get student's year
$student_year = $user['year'] ?? '';

// Create an array of possible year matches for flexible filtering
$year_variations = [$student_year];
if (substr($student_year, 0, 1) === 'E') {
    // For Extension students (E1, E2, E3)
    $year_variations[] = 'E';
    $year_variations[] = 'Extension';
    $year_variations[] = 'Ext';
    $year_variations[] = substr($student_year, 1); // Also check regular year equivalent
} else {
    // For regular students, also check for Extension versions
    $year_variations[] = 'E' . $student_year;
}

// Create placeholders for IN clause
$placeholders = str_repeat('?,', count($year_variations) - 1) . '?';

// Fetch student's schedule WITH YEAR FILTERING
$schedules = $pdo->prepare("
    SELECT s.schedule_id, c.course_name, u.username AS instructor_name, r.room_name, 
           s.academic_year, s.semester, s.day, s.start_time, s.end_time
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN users u ON s.instructor_id = u.user_id
    JOIN rooms r ON s.room_id = r.room_id
    JOIN enrollments e ON s.schedule_id = e.schedule_id
    WHERE e.student_id = ?
    AND s.year IN ($placeholders)
    ORDER BY s.day, s.start_time
");

$schedule_params = array_merge([$student_id], $year_variations);
$schedules->execute($schedule_params);
$my_schedule = $schedules->fetchAll();

// Quick stats: total courses WITH YEAR FILTER
$total_courses_stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT s.course_id) AS total_courses
    FROM schedule s
    JOIN enrollments e ON s.schedule_id = e.schedule_id
    WHERE e.student_id = ?
    AND s.year IN ($placeholders)
");
$total_courses_stmt->execute($schedule_params);
$total_courses = $total_courses_stmt->fetchColumn();

// Quick stats: upcoming classes today WITH YEAR FILTER
$today = date('l');
$upcoming_classes_stmt = $pdo->prepare("
    SELECT COUNT(*) AS upcoming
    FROM schedule s
    JOIN enrollments e ON s.schedule_id = e.schedule_id
    WHERE e.student_id = ?
    AND s.year IN ($placeholders)
    AND s.day = ?
    AND s.start_time >= CURTIME()
");
$today_params = array_merge([$student_id], $year_variations, [$today]);
$upcoming_classes_stmt->execute($today_params);
$upcoming_classes = $upcoming_classes_stmt->fetchColumn();

// Quick stat: next class WITH YEAR FILTER
$next_class_stmt = $pdo->prepare("
    SELECT c.course_name, s.start_time
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN enrollments e ON s.schedule_id = e.schedule_id
    WHERE e.student_id = ?
    AND s.year IN ($placeholders)
    AND s.day = ?
    AND s.start_time >= CURTIME()
    ORDER BY s.start_time ASC
    LIMIT 1
");
$next_class_stmt->execute($today_params);
$next_class = $next_class_stmt->fetch(PDO::FETCH_ASSOC);

// Determine profile picture path - FIXED PATHS
// First, let's define the base uploads directory
$uploads_dir = __DIR__ . '/../uploads/';
$assets_dir = __DIR__ . '/../assets/';

// Check if profile picture exists in uploads directory
$profile_picture = $user['profile_picture'] ?? '';
$default_profile = 'default_profile.png';

// Check multiple possible locations
if(!empty($profile_picture)) {
    // Try absolute path first
    if(file_exists($uploads_dir . $profile_picture)) {
        $profile_img_path = '../uploads/' . $profile_picture;
    }
    // Try relative path from current directory
    else if(file_exists('uploads/' . $profile_picture)) {
        $profile_img_path = 'uploads/' . $profile_picture;
    }
    // Try direct uploads path
    else if(file_exists('../uploads/' . $profile_picture)) {
        $profile_img_path = '../uploads/' . $profile_picture;
    }
    // Try ../../uploads path
    else if(file_exists('../../uploads/' . $profile_picture)) {
        $profile_img_path = '../../uploads/' . $profile_picture;
    }
    else {
        // Use default if file doesn't exist
        $profile_img_path = '../assets/' . $default_profile;
    }
} else {
    // Use default if no profile picture
    $profile_img_path = '../assets/' . $default_profile;
}

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<title>Student Dashboard</title>
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
}
.sidebar.hidden { transform:translateX(-260px); }
.sidebar a { 
    display:block; 
    padding:12px 20px; 
    color:var(--text-sidebar); 
    text-decoration:none; 
    transition: background 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.sidebar a:hover, .sidebar a.active { background:#1abc9c; color:white; }

.sidebar-profile {
    text-align: center;
    margin-bottom: 20px;
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

/* ================= Updated Sidebar ================= */
.sidebar {
    position: fixed; 
    top:0; 
    left:0;
    width:250px; 
    height:100%;
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
    flex-shrink: 0; /* Prevent shrinking */
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

/* Year badge in sidebar */
.year-badge {
    display: inline-block;
    padding: 3px 10px;
    background: <?= (substr($student_year, 0, 1) === 'E') ? '#8b5cf6' : '#3b82f6' ?>;
    color: white;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-top: 5px;
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

/* Optional: Add fade effect at bottom when scrolling */
.sidebar-content::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 30px;
    background: linear-gradient(to bottom, transparent, var(--bg-sidebar));
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s;
}

.sidebar-content.scrolled::after {
    opacity: 1;
}

/* ================= Overlay ================= */
.overlay {
    position: fixed; 
    top:0; 
    left:0; 
    width:100%; 
    height:100%;
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
    padding:20px;
    min-height:100vh;
    background: var(--bg-primary);
    transition: all 0.3s ease;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 15px;
        padding-top: 80px;
    }
}
/* Year badge in sidebar */
.year-badge {
    display: inline-block;
    padding: 3px 10px;
    background: <?= (substr($student_year, 0, 1) === 'E') ? '#8b5cf6' : '#3b82f6' ?>;
    color: white;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-top: 5px;
}

/* Sidebar title */
.sidebar h2 {
    text-align: center;
    color: var(--text-sidebar);
    margin-bottom: 25px;
    font-size: 22px;
    padding: 0 20px;
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
    padding:20px;
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
    min-height: calc(100vh - 40px);
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

/* Welcome Section */
.welcome-section {
    margin-bottom: 30px;
}

.welcome-section h1 {
    font-size: 2.2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.5rem;
}

.welcome-section p {
    color: var(--text-secondary);
    font-size: 1.1rem;
    margin-top: 10px;
}

/* ================= Stats Cards ================= */
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

/* Course icon color */
.stat-card .fa-book.icon {
    color: #3b82f6;
}

.stat-card .fa-calendar-alt.icon {
    color: #10b981;
}

.stat-card .fa-clock.icon {
    color: #f59e0b;
}

/* Countdown text */
.stat-card #countdown {
    color: var(--danger);
    font-size: 0.8rem;
    margin-top: 5px;
}

[data-theme="dark"] .stat-card #countdown {
    color: #fca5a5;
}

/* ================= Student Info Box ================= */
.student-info-box {
    background: rgba(99, 102, 241, 0.1);
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #6366f1;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text-primary);
}

.student-info-box i {
    color: #6366f1;
    font-size: 1.2rem;
}

.student-year-badge {
    display: inline-block;
    padding: 4px 12px;
    background: <?= (substr($student_year, 0, 1) === 'E') ? '#8b5cf6' : '#3b82f6' ?>;
    color: white;
    border-radius: 15px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-left: 10px;
}

/* ================= Schedule Table ================= */
.schedule-section {
    margin-top: 30px;
}

.schedule-section h2 {
    margin-bottom: 20px;
    color: var(--text-primary);
    font-size: 1.5rem;
    font-weight: 600;
}

.table-container {
    background: var(--bg-card);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
}

.schedule-table {
    width: 100%;
    border-collapse: collapse;
}

.schedule-table th {
    background: var(--table-header);
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: var(--text-sidebar);
    border-bottom: 1px solid var(--border-color);
}

.schedule-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
}

.schedule-table tr:last-child td {
    border-bottom: none;
}

.schedule-table tr:hover {
    background: var(--hover-color);
}

.schedule-table .today-row {
    background: var(--today-bg) !important;
    border-left: 4px solid #f59e0b;
}

[data-theme="dark"] .schedule-table .today-row {
    background: rgba(245, 158, 11, 0.1) !important;
}

/* Today's classes info box */
.table-container > div {
    margin-top: 15px;
    padding: 10px;
    background: var(--info-bg);
    border-radius: 8px;
    border-left: 4px solid #f59e0b;
}

.table-container > div small {
    color: var(--info-text);
    font-weight: 600;
}

[data-theme="dark"] .table-container > div {
    background: rgba(245, 158, 11, 0.1);
}

[data-theme="dark"] .table-container > div small {
    color: #fcd34d;
}

/* Profile Section */
.profile-section {
    text-align: center;
    margin: 30px 0;
    padding: 20px;
    background: var(--bg-secondary);
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.profile-picture {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #3b82f6;
    margin-bottom: 15px;
    box-shadow: 0 4px 12px var(--shadow-color);
}

.profile-info {
    margin-top: 15px;
}

.profile-info p {
    color: var(--text-primary);
    margin-bottom: 8px;
    font-size: 1rem;
}

.profile-info strong {
    color: var(--text-primary);
    font-weight: 600;
}

.profile-info a {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: color 0.3s ease;
}

.profile-info a:hover {
    color: #2563eb;
}

[data-theme="dark"] .profile-info a {
    color: #60a5fa;
}

[data-theme="dark"] .profile-info a:hover {
    color: #93c5fd;
}

/* Empty state for schedule */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: var(--border-color);
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
}

/* Debug info (optional, remove in production) */
.debug-info {
    background: rgba(239, 68, 68, 0.1);
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    border-left: 4px solid #ef4444;
    color: var(--error-text);
    font-family: monospace;
    font-size: 0.85rem;
    display: none; /* Hide by default, show only when needed */
}

/* Dark mode specific adjustments */
[data-theme="dark"] .stat-card .number {
    color: var(--text-primary);
}

[data-theme="dark"] .stat-card h3 {
    color: var(--text-secondary);
}

[data-theme="dark"] .schedule-table th {
    color: var(--text-sidebar);
}

[data-theme="dark"] .schedule-table td {
    color: var(--text-primary);
}

[data-theme="dark"] .schedule-table tr:hover {
    background: rgba(255, 255, 255, 0.05);
}

/* Next class countdown styling */
.next-class-info {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.next-class-info i {
    color: var(--text-secondary);
}

/* Status text */
[data-theme="dark"] .profile-info span {
    color: #10b981;
}

/* Edit profile link styling in dark mode */
[data-theme="dark"] .profile-info a {
    background: linear-gradient(135deg, #60a5fa, #3b82f6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* ================= Responsive ================= */
@media (max-width: 768px) {
    .topbar { display: flex; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .main-content { margin-left: 0; padding: 15px; }
    .content-wrapper { padding: 20px; border-radius: 0; }
    .header { flex-direction: column; gap: 15px; align-items: flex-start; }
    .header h1 { font-size: 1.8rem; }
    .stats-cards { flex-direction: column; }
    .stat-card { min-width: auto; }
    .table-container { overflow-x auto; }
    .schedule-table { min-width: 600px; }
    .profile-picture { width: 120px; height: 120px; }
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
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Student Dashboard</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-content" id="sidebarContent">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic">
            <p><?= htmlspecialchars($user['username'] ?? 'Student') ?></p>
            <?php if($student_year): ?>
                <span class="year-badge">
                    <?php 
                    if(substr($student_year, 0, 1) === 'E') {
                        echo 'Ext. Year ' . substr($student_year, 1);
                    } else {
                        echo 'Year ' . $student_year;
                    }
                    ?>
                </span>
            <?php endif; ?>
        </div>
        
        <h2>Student Panel</h2>
        
        <nav>
            <a href="student_dashboard.php" class="<?= $current_page=='student_dashboard.php'?'active':'' ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="my_schedule.php" class="<?= $current_page=='my_schedule.php'?'active':'' ?>">
                <i class="fas fa-calendar-alt"></i> My Schedule
            </a>
            <a href="view_exam_schedules.php" class="<?= $current_page=='view_exam_schedules.php'?'active':'' ?>">
                <i class="fas fa-clipboard-list"></i> Exam Schedule
            </a>
            <a href="view_announcements.php" class="<?= $current_page=='view_announcements.php'?'active':'' ?>">
                <i class="fas fa-bullhorn"></i> Announcements
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

<!-- Overlay for Mobile -->
<div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
                <div class="welcome-section">
                    <h1>Welcome, <?= htmlspecialchars($user['username']); ?> 👋</h1>
                    <p>Here is your personal dashboard. Use the sidebar to navigate.</p>
                </div>
                <div class="user-info">
                    <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile" id="headerProfilePic">
                    <div>
                        <div><?= htmlspecialchars($user['username'] ?? 'Student') ?></div>
                        <small>Student</small>
                    </div>
                </div>
            </div>

            <!-- Student Info Box -->
            <div class="student-info-box">
                <i class="fas fa-user-graduate"></i>
                <div>
                    <strong>Student Information:</strong> 
                    <?= htmlspecialchars($user['username'] ?? 'Student') ?>
                    <span class="student-year-badge">
                        <?php 
                        if($student_year) {
                            if(substr($student_year, 0, 1) === 'E') {
                                echo 'Extension Year ' . substr($student_year, 1);
                            } else {
                                echo 'Year ' . $student_year;
                            }
                        } else {
                            echo 'Year not set';
                        }
                        ?>
                    </span>
                </div>
            </div>

           <!-- Profile Section -->
<div class="profile-section">
    <?php 
    // SIMPLIFIED and FIXED profile picture logic
    $profile_pic_for_display = '../assets/default_profile.png';
    
    if(!empty($user['profile_picture'])) {
        // First, let's check if we can get the absolute path
        $uploads_base = __DIR__ . '/../uploads/';
        $profile_file = $user['profile_picture'];
        
        // Remove any directory traversal from filename for security
        $profile_file = basename($profile_file);
        
        // Check if file exists in uploads directory
        if(file_exists($uploads_base . $profile_file)) {
            // File exists - use relative path from current location
            $profile_pic_for_display = '../uploads/' . $profile_file;
        } else {
            // File doesn't exist in uploads - try to see if it's a URL
            if(filter_var($profile_file, FILTER_VALIDATE_URL)) {
                $profile_pic_for_display = $profile_file;
            } else {
                // Check if it might be stored in a different format in database
                // Sometimes the full path is stored, sometimes just filename
                $possible_locations = [
                    $profile_file, // Try as-is (might be full path)
                    'uploads/' . $profile_file,
                    '../uploads/' . $profile_file,
                    '../../uploads/' . $profile_file,
                    '/uploads/' . $profile_file,
                    $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $profile_file
                ];
                
                foreach($possible_locations as $location) {
                    if(file_exists($location) || @getimagesize($location)) {
                        $profile_pic_for_display = $location;
                        break;
                    }
                }
            }
        }
    }
    ?>
    <img src="<?= htmlspecialchars($profile_pic_for_display) ?>" 
         alt="Profile Picture" 
         class="profile-picture" 
         id="mainProfilePic"
         onerror="this.onerror=null; this.src='../assets/default_profile.png';">
    
    <div class="profile-info">
        <p><strong>Username:</strong> <?= htmlspecialchars($user['username'] ?? 'Student') ?></p>
        <?php if(isset($user['email'])): ?>
            <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
        <?php endif; ?>
        <p><strong>Year:</strong> 
            <span style="color: <?= (substr($student_year, 0, 1) === 'E') ? '#8b5cf6' : '#3b82f6' ?>; font-weight: 600;">
                <?php 
                if($student_year) {
                    if(substr($student_year, 0, 1) === 'E') {
                        echo 'Extension Year ' . substr($student_year, 1);
                    } else {
                        echo 'Year ' . $student_year;
                    }
                } else {
                    echo 'Not set';
                }
                ?>
            </span>
        </p>
        <p><strong>Status:</strong> <span style="color: #10b981; font-weight: 600;">Active Student</span></p>
        <p>
            <a href="edit_profile.php">
                <i class="fas fa-edit"></i> Edit Profile
            </a>
        </p>
    </div>
</div>

            <!-- Quick Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <i class="fas fa-book icon" style="color: #3b82f6;"></i>
                    <h3>Total Courses</h3>
                    <div class="number"><?= $total_courses ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-alt icon" style="color: #10b981;"></i>
                    <h3>Upcoming Classes Today</h3>
                    <div class="number"><?= $upcoming_classes ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock icon" style="color: #f59e0b;"></i>
                    <h3>Next Class</h3>
                    <?php if($next_class): ?>
                        <div class="number" style="font-size: 1.2rem;">
                            <?= htmlspecialchars($next_class['course_name']) ?>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;" class="next-class-info">
                            <i class="fas fa-clock"></i> at <?= date('h:i A', strtotime($next_class['start_time'])) ?>
                        </div>
                        <div id="countdown" style="color: #ef4444; font-size: 0.8rem; margin-top: 5px;"></div>
                        <script>
                            const startTime = "<?= date('H:i:s', strtotime($next_class['start_time'])) ?>";
                            let todayDate = new Date().toISOString().split('T')[0];
                            const classDateTime = new Date(todayDate + "T" + startTime);
                            function updateCountdown(){
                                const now = new Date();
                                const diff = classDateTime - now;
                                if(diff <= 0){
                                    document.getElementById('countdown').innerText = "Class is starting now!";
                                    clearInterval(timerInterval);
                                    return;
                                }
                                const hours = Math.floor(diff/(1000*60*60));
                                const minutes = Math.floor((diff % (1000*60*60))/(1000*60));
                                const seconds = Math.floor((diff % (1000*60))/1000);
                                document.getElementById('countdown').innerText = `Starts in: ${hours}h ${minutes}m ${seconds}s`;
                            }
                            updateCountdown();
                            const timerInterval = setInterval(updateCountdown,1000);
                        </script>
                    <?php else: ?>
                        <div class="number" style="font-size: 1rem; color: var(--text-secondary);">
                            <i class="fas fa-check-circle"></i> No more classes today
                        </div>
                    <?php endif; ?>
                </div>
            </div>

         <!-- Schedule Table -->
<div class="schedule-section">
    <h2 style="margin-bottom: 20px; color: var(--text-primary);">My Schedule</h2>
    <div class="table-container">
        <?php 
        $hasTodayClass = false; // Initialize here
        $today = date('l'); // Current day name
        
        if(!empty($my_schedule)): 
        ?>
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Instructor</th>
                        <th>Room</th>
                        <th>Day</th>
                        <th>Start</th>
                        <th>End</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($my_schedule as $s): 
                    $todayClass = ($s['day'] === $today) ? 'today-row' : '';
                    if($s['day'] === $today) $hasTodayClass = true;
                ?>
                    <tr class="<?= $todayClass ?>">
                        <td><?= htmlspecialchars($s['course_name']) ?></td>
                        <td><?= htmlspecialchars($s['instructor_name']) ?></td>
                        <td><?= htmlspecialchars($s['room_name']) ?></td>
                        <td><?= htmlspecialchars($s['day']) ?></td>
                        <td><?= date('h:i A', strtotime($s['start_time'])) ?></td>
                        <td><?= date('h:i A', strtotime($s['end_time'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if($hasTodayClass): ?>
            <div style="margin-top: 15px; padding: 10px; background: var(--info-bg); border-radius: 8px; border-left: 4px solid #f59e0b;">
                <small style="color: var(--info-text); font-weight: 600;">
                    <i class="fas fa-info-circle"></i> Highlighted rows indicate today's classes
                </small>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Classes Scheduled</h3>
                <p>You don't have any classes scheduled for 
                    <?php 
                    if($student_year) {
                        if(substr($student_year, 0, 1) === 'E') {
                            echo 'Extension Year ' . substr($student_year, 1);
                        } else {
                            echo 'Year ' . $student_year;
                        }
                    } else {
                        echo 'your year';
                    }
                    ?>
                </p>
                <p style="margin-top: 10px; font-size: 0.9rem;">
                    Please check with your department head or administrator.
                </p>
            </div>
        <?php endif; ?>
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
        
        // Add animation to stats cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 200);
        });
        
        // Add animation to table rows
        const tableRows = document.querySelectorAll('.schedule-table tbody tr');
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
        console.log('Main profile pic src:', document.getElementById('mainProfilePic').src);
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
    </script>
</body>
</html>