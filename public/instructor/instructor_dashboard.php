<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor'){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

$instructor_id = $_SESSION['user_id'];

// Fetch instructor info - INCLUDING EMAIL
$user_stmt = $pdo->prepare("SELECT username, email, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$instructor_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

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
        'uploads/' . $profile_picture,
        '../uploads/' . $profile_picture,
    ];
    
    foreach ($locations as $location) {
        if (file_exists($location)) {
            // Return the appropriate web path
            if (strpos($location, '../../uploads/') !== false) {
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
$profile_img_path = getProfilePicturePath($user['profile_picture'] ?? '');

// Fetch courses taught by instructor
$courses_stmt = $pdo->prepare("
    SELECT DISTINCT c.course_id, c.course_name, c.course_code
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    WHERE s.instructor_id = ?
    ORDER BY c.course_name
");
$courses_stmt->execute([$instructor_id]);
$courses = $courses_stmt->fetchAll();
$total_courses = count($courses);

// Fetch complete weekly schedule
$weekly_schedule_stmt = $pdo->prepare("
    SELECT s.schedule_id, c.course_name, c.course_code, r.room_name, 
           s.day, s.start_time, s.end_time,
           (SELECT COUNT(*) FROM enrollments e WHERE e.schedule_id = s.schedule_id) AS student_count
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN rooms r ON s.room_id = r.room_id
    WHERE s.instructor_id = ?
    ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), s.start_time
");
$weekly_schedule_stmt->execute([$instructor_id]);
$weekly_schedule = $weekly_schedule_stmt->fetchAll();

// Group schedule by day for better display
$schedule_by_day = [
    'Monday' => [],
    'Tuesday' => [],
    'Wednesday' => [],
    'Thursday' => [],
    'Friday' => []
];

foreach($weekly_schedule as $class) {
    $schedule_by_day[$class['day']][] = $class;
}

// Calculate total students across all classes
$total_students = array_sum(array_column($weekly_schedule, 'student_count'));

// Next class (from current time)
$today = date('l');
$current_time = date('H:i:s');
$next_class_stmt = $pdo->prepare("
    SELECT c.course_name, s.day, s.start_time, s.end_time, r.room_name
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN rooms r ON s.room_id = r.room_id
    WHERE s.instructor_id = ?
      AND (
        (s.day = ? AND s.start_time >= ?) OR 
        (s.day > ?)
      )
    ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), s.start_time
    LIMIT 1
");
$next_class_stmt->execute([$instructor_id, $today, $current_time, $today]);
$next_class = $next_class_stmt->fetch(PDO::FETCH_ASSOC);

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<title>Instructor Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Include Dark Mode CSS -->
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
/* ========== General Reset ========== */
* {margin:0;padding:0;box-sizing:border-box;}
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    display:flex;
    min-height:100vh;
    background: var(--bg-primary);
    background-size: 400% 400%;
    overflow-x: hidden;
}

/* Animation for background gradient */
[data-theme="light"] body {
    background: linear-gradient(-45deg, #f0f2f5, #e3e7f0, #f5f7fa);
    background-size: 400% 400%;
    animation: gradientBG 15s ease infinite;
}

[data-theme="dark"] body {
    background: linear-gradient(-45deg, #0f2027, #203a43, #2c5364);
    background-size: 400% 400%;
    animation: gradientBG 15s ease infinite;
}

@keyframes gradientBG {
    0% {background-position:0% 50%;}
    50% {background-position:100% 50%;}
    100% {background-position:0% 50%;}
}

/* ========== Topbar for Mobile ========== */
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

/* ========== Sidebar ========== */
.sidebar {
    position: fixed;
    top:0; left:0;
    height:100vh;
    width:240px;
    background: var(--bg-sidebar);
    padding: 30px 0 20px;
    display:flex;
    flex-direction:column;
    align-items:center;
    box-shadow:2px 0 10px rgba(0,0,0,0.2);
    z-index:1000;
    overflow-y:auto;
    transition: transform 0.3s ease;
}
.sidebar.hidden { transform:translateX(-100%); }

.sidebar-profile {
    text-align: center;
    margin-bottom: 25px;
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    width: 100%;
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
    color: var(--text-sidebar);
    text-align:center;
    width:100%;
    margin-bottom:25px;
    font-size:22px;
    padding: 0 20px;
}
.sidebar a {
    padding:12px 20px;
    text-decoration:none;
    font-size:16px;
    color:var(--text-sidebar);
    width:100%;
    transition: background 0.3s, color 0.3s;
    border-radius:6px;
    margin:3px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.sidebar a.active, .sidebar a:hover {
    background:#1abc9c;
    color:#fff;
    font-weight:bold;
}

/* ========== Updated Sidebar ========== */
.sidebar {
    position: fixed;
    top:0; 
    left:0;
    height:100vh;
    width:240px;
    background: var(--bg-sidebar);
    padding: 30px 0 20px;
    display:flex;
    flex-direction:column;
    align-items:center;
    box-shadow:2px 0 10px rgba(0,0,0,0.2);
    z-index:1000;
    overflow-y: auto;
    transition: transform 0.3s ease;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
}

/* Custom scrollbar for sidebar */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

[data-theme="dark"] .sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
}

[data-theme="dark"] .sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

.sidebar.hidden { 
    transform:translateX(-100%); 
}

/* Sidebar Profile */
.sidebar-profile {
    text-align: center;
    margin-bottom: 25px;
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    flex-shrink: 0; /* Prevent shrinking */
    width: 100%;
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
    color: var(--text-sidebar);
    text-align:center;
    width:100%;
    margin-bottom:25px;
    font-size:22px;
    padding: 0 20px;
}

.sidebar a {
    padding:12px 20px;
    text-decoration:none;
    font-size:16px;
    color:var(--text-sidebar);
    width:100%;
    transition: background 0.3s, color 0.3s;
    border-radius:6px;
    margin:3px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar a:last-child {
    border-bottom: none;
}

.sidebar a.active, .sidebar a:hover {
    background:#1abc9c;
    color:#fff;
    font-weight:bold;
    padding-left: 25px;
}

/* Optional: Add fade effect at bottom when scrolling */
.sidebar::after {
    content: '';
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 30px;
    width: 240px;
    background: linear-gradient(to bottom, transparent, var(--bg-sidebar));
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s;
    z-index: 1001;
}

.sidebar.scrolled::after {
    opacity: 1;
}

/* ========== Overlay ========== */
.overlay {
    position: fixed; 
    top:0; 
    left:0; 
    width:100%; 
    height:100%;
    background: rgba(0,0,0,0.4); 
    z-index:999;
    display:none; 
    opacity:0; 
    transition: opacity 0.3s ease;
}

.overlay.active { 
    display:block; 
    opacity:1; 
}

/* ========== Main Content ========== */
.main-content {
    margin-left:240px;
    padding:30px;
    flex-grow:1;
    min-height:100vh;
    background: var(--bg-primary);
    border-radius:12px;
    margin-top:20px;
    margin-bottom:20px;
    width: calc(100% - 240px);
    transition: all 0.3s ease;
}

@media screen and (max-width:768px){
    .sidebar { 
        transform: translateX(-100%); 
        width: 280px;
    }
    .sidebar.active { 
        transform: translateX(0); 
    }
    .main-content { 
        margin-left: 0; 
        padding: 15px; 
        width: 100%; 
        margin-top: 0;
    }
    .sidebar::after {
        width: 280px;
    }
}
/* ========== Overlay ========== */
.overlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.4); z-index:1050;
    display:none; opacity:0; transition: opacity 0.3s ease;
}
.overlay.active { display:block; opacity:1; }

/* ========== Main Content ========== */
.main-content {
    margin-left:240px;
    padding:30px;
    flex-grow:1;
    min-height:100vh;
    background: var(--bg-primary);
    border-radius:12px;
    margin-top:20px;
    margin-bottom:20px;
    width: calc(100% - 240px);
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

/* ========== Stats Cards ========== */
.stats-cards {
    display:flex;
    gap:25px;
    flex-wrap:wrap;
    margin-bottom:35px;
}
.stats-cards .card {
    flex:1;
    min-width:180px;
    background: linear-gradient(135deg,#6a11cb,#2575fc);
    color:#fff;
    padding:25px;
    border-radius:16px;
    box-shadow:0 8px 20px rgba(0,0,0,0.15);
    text-align:center;
    transition: transform 0.3s, box-shadow 0.3s;
    position:relative;
    border: 1px solid var(--border-color);
}
.stats-cards .card:hover {
    transform: translateY(-5px);
    box-shadow:0 12px 25px rgba(0,0,0,0.25);
}
.stats-cards .card h3 {
    font-size:17px;
    margin-bottom:12px;
    font-weight: 600;
}
.stats-cards .card p {
    font-size:24px;
    font-weight:bold;
}
.stats-cards .card::before {
    content:"📘";
    font-size:28px;
    position:absolute;
    top:15px;
    right:15px;
}

/* Dark mode adjustments for stats cards */
[data-theme="dark"] .stats-cards .card {
    background: linear-gradient(135deg, #3730a3, #1d4ed8);
}

/* ========== Weekly Schedule Grid ========== */
.weekly-schedule-container {
    margin-top: 30px;
}

.weekly-schedule-container h2 {
    color: var(--text-primary);
    margin-bottom: 20px;
    font-size: 1.5rem;
    font-weight: 600;
}

.weekly-schedule {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-top: 20px;
}
.day-column {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 15px;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
}
.day-header {
    background: var(--table-header);
    color: var(--text-sidebar);
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 15px;
    font-weight: bold;
}
.class-slot {
    background: var(--bg-secondary);
    border-left: 4px solid #6a11cb;
    padding: 12px;
    margin-bottom: 10px;
    border-radius: 6px;
    transition: transform 0.2s, background 0.2s;
}
.class-slot:hover {
    transform: translateX(5px);
    background: var(--hover-color);
}
.class-slot.next-class {
    background: var(--today-bg);
    border-left-color: #f59e0b;
    font-weight: bold;
}
.course-name {
    font-weight: bold;
    color: var(--text-primary);
    margin-bottom: 5px;
}
.class-details {
    font-size: 12px;
    color: var(--text-secondary);
    line-height: 1.4;
}
.class-time {
    font-weight: bold;
    color: #2575fc;
}
.student-count {
    background: var(--bg-secondary);
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 11px;
    margin-top: 5px;
    display: inline-block;
    color: var(--text-primary);
}

/* ========== No Classes Message ========== */
.no-classes {
    text-align: center;
    padding: 20px;
    color: var(--text-secondary);
    font-style: italic;
    background: var(--bg-secondary);
    border-radius: 8px;
    margin: 10px 0;
}

/* ========== Course List ========== */
.course-list {
    margin-top: 30px;
}

.course-list h2 {
    color: var(--text-primary);
    margin-bottom: 20px;
    font-size: 1.5rem;
    font-weight: 600;
}

.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.course-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.course-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px var(--shadow-lg);
}

.course-card h3 {
    font-size: 1.1rem;
    color: var(--text-primary);
    margin-bottom: 8px;
    font-weight: 600;
}

.course-card .course-code {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-bottom: 10px;
}

.course-stats {
    display: flex;
    justify-content: space-between;
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px solid var(--border-color);
}

.course-stats div {
    text-align: center;
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.course-stats span {
    display: block;
    font-weight: bold;
    color: var(--text-primary);
    font-size: 1.1rem;
    margin-top: 5px;
}

/* ========== Next Class Countdown ========== */
.next-class-info {
    margin-top: 10px;
    font-size: 0.9rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 5px;
}

.next-class-info i {
    color: var(--text-secondary);
}

/* ========== Dark Mode Specific ========== */
[data-theme="dark"] .class-time {
    color: #60a5fa;
}

[data-theme="dark"] .student-count {
    background: rgba(255,255,255,0.1);
}

[data-theme="dark"] .class-slot {
    border-left-color: #8b5cf6;
}

[data-theme="dark"] .day-header {
    background: rgba(37, 99, 235, 0.2);
    border: 1px solid rgba(37, 99, 235, 0.3);
}

[data-theme="dark"] .stats-cards .card::before {
    filter: brightness(1.2);
}

/* ========== Responsive ========== */
@media screen and (max-width:1200px){
    .weekly-schedule {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media screen and (max-width:1024px){
    .weekly-schedule {
        grid-template-columns: repeat(2, 1fr);
    }
    .courses-grid {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    }
}
@media screen and (max-width:768px){
    .topbar { display: flex; }
    .sidebar { transform: translateX(-100%); width: 250px; }
    .sidebar.active { transform: translateX(0); }
    .main-content { margin-left: 0; padding: 15px; width: 100%; }
    .content-wrapper { padding: 20px; border-radius: 0; }
    .header { flex-direction: column; gap: 15px; align-items: flex-start; }
    .header h1 { font-size: 1.8rem; }
    .stats-cards{flex-direction:column;}
    .weekly-schedule{grid-template-columns: 1fr;}
    .courses-grid { grid-template-columns: 1fr; }
}
@media screen and (max-width:480px){
    .day-column { padding: 10px; }
    .class-slot { padding: 10px; }
    .stats-cards .card { padding: 20px; }
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Instructor Dashboard</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-profile">
        <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic"
             onerror="this.onerror=null; this.src='../assets/default_profile.png';">
        <p><?= htmlspecialchars($user['username'] ?? 'Instructor') ?></p>
    </div>
    <h2>Instructor Dashboard</h2>
    <a href="instructor_dashboard.php" class="<?= $current_page=='instructor_dashboard.php'?'active':'' ?>">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="announcements.php" class="<?= $current_page=='announcements.php'?'active':'' ?>">
        <i class="fas fa-bullhorn"></i> Announcements
    </a>
    <a href="exam_assignments.php" class="<?= $current_page=='exam_assignments.php'?'active':'' ?>">
        <i class="fas fa-clipboard-list"></i> Exam Assignments
    </a>
    <a href="my_courses.php" class="<?= $current_page=='my_courses.php'?'active':'' ?>">
        <i class="fas fa-book"></i> My Courses
    </a>
    <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">
        <i class="fas fa-user-edit"></i> Edit Profile
    </a>
    <a href="../logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Overlay for Mobile -->
<div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
                <div class="welcome-section">
                    <h1>Welcome, <?= htmlspecialchars($user['username']); ?> 👋</h1>
                    <p>Your weekly teaching schedule overview</p>
                </div>
                <div class="user-info">
                    <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile" id="headerProfilePic"
                         onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                    <div>
                        <div><?= htmlspecialchars($user['username'] ?? 'Instructor') ?></div>
                        <small>Instructor</small>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="card">
                    <h3>Total Courses</h3>
                    <p><?= $total_courses ?></p>
                </div>
                <div class="card">
                    <h3>Weekly Classes</h3>
                    <p><?= count($weekly_schedule) ?></p>
                </div>
                <div class="card">
                    <h3>Total Students</h3>
                    <p><?= $total_students ?></p>
                </div>
                <div class="card">
                    <h3>Next Class</h3>
                    <?php if($next_class): ?>
                        <p><?= htmlspecialchars($next_class['course_name']) ?></p>
                        <div class="next-class-info">
                            <i class="fas fa-calendar-day"></i> <?= $next_class['day'] ?> 
                            <i class="fas fa-clock"></i> <?= date('g:i A', strtotime($next_class['start_time'])) ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size: 1rem; margin-top: 10px;">No upcoming classes</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Course List -->
            <?php if($courses): ?>
            <div class="course-list">
                <h2>Your Courses</h2>
                <div class="courses-grid">
                    <?php foreach($courses as $course): ?>
                    <div class="course-card">
                        <h3><?= htmlspecialchars($course['course_name']) ?></h3>
                        <div class="course-code"><?= htmlspecialchars($course['course_code']) ?></div>
                        <?php
                        // Get course stats
                        $course_stats_stmt = $pdo->prepare("
                            SELECT 
                                COUNT(DISTINCT s.schedule_id) as class_count,
                                SUM((SELECT COUNT(*) FROM enrollments e WHERE e.schedule_id = s.schedule_id)) as total_students
                            FROM schedule s
                            WHERE s.course_id = ? AND s.instructor_id = ?
                        ");
                        $course_stats_stmt->execute([$course['course_id'], $instructor_id]);
                        $stats = $course_stats_stmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                        <div class="course-stats">
                            <div>
                                <span><?= $stats['class_count'] ?? 0 ?></span>
                                Classes
                            </div>
                            <div>
                                <span><?= $stats['total_students'] ?? 0 ?></span>
                                Students
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Weekly Schedule -->
            <div class="weekly-schedule-container">
                <h2>Weekly Schedule</h2>
                <div class="weekly-schedule">
                    <?php 
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                    foreach($days as $day): 
                        $day_classes = $schedule_by_day[$day];
                    ?>
                        <div class="day-column">
                            <div class="day-header"><?= $day ?></div>
                            <?php if(!empty($day_classes)): ?>
                                <?php foreach($day_classes as $class): 
                                    $is_next_class = ($next_class && 
                                                     $next_class['course_name'] == $class['course_name'] && 
                                                     $next_class['day'] == $class['day'] && 
                                                     $next_class['start_time'] == $class['start_time']);
                                ?>
                                    <div class="class-slot <?= $is_next_class ? 'next-class' : '' ?>">
                                        <div class="course-name"><?= htmlspecialchars($class['course_name']) ?></div>
                                        <div class="class-details">
                                            <div class="class-time">
                                                <?= date('g:i A', strtotime($class['start_time'])) ?> - <?= date('g:i A', strtotime($class['end_time'])) ?>
                                            </div>
                                            <div>Room: <?= htmlspecialchars($class['room_name']) ?></div>
                                            <?php if(!empty($class['course_code'])): ?>
                                                <div>Code: <?= htmlspecialchars($class['course_code']) ?></div>
                                            <?php endif; ?>
                                            <div class="student-count"><?= $class['student_count'] ?> students</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-classes">No classes scheduled</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
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
        
        // Add animation to stats cards
        const statCards = document.querySelectorAll('.stats-cards .card');
        statCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 200);
        });
        
        // Add animation to course cards
        const courseCards = document.querySelectorAll('.course-card');
        courseCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, (index * 100) + 200);
        });
        
        // Add animation to schedule columns
        const scheduleColumns = document.querySelectorAll('.day-column');
        scheduleColumns.forEach((column, index) => {
            column.style.opacity = '0';
            column.style.transform = 'translateX(20px)';
            setTimeout(() => {
                column.style.transition = 'all 0.5s ease';
                column.style.opacity = '1';
                column.style.transform = 'translateX(0)';
            }, (index * 100) + 400);
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