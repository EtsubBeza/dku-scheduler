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

// Fetch current user info
$user_stmt = $pdo->prepare("SELECT username, profile_picture, department_id FROM users WHERE user_id = ?");
$user_stmt->execute([$instructor_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Determine profile picture path
function getProfilePicturePath($profile_picture) {
    if (empty($profile_picture)) {
        return '../assets/default_profile.png';
    }
    
    $locations = [
        __DIR__ . '/../uploads/' . $profile_picture,
        __DIR__ . '/../../uploads/' . $profile_picture,
        'uploads/' . $profile_picture,
        '../uploads/' . $profile_picture,
    ];
    
    foreach ($locations as $location) {
        if (file_exists($location)) {
            if (strpos($location, '../../uploads/') !== false) {
                return '../../uploads/' . $profile_picture;
            } elseif (strpos($location, '../uploads/') !== false) {
                return '../uploads/' . $profile_picture;
            } elseif (strpos($location, 'uploads/') !== false) {
                return 'uploads/' . $profile_picture;
            }
        }
    }
    
    return '../assets/default_profile.png';
}

$profile_img_path = getProfilePicturePath($user['profile_picture'] ?? '');

// Fetch exam assignments for this instructor
$exam_assignments_stmt = $pdo->prepare("
    SELECT 
        es.*,
        c.course_code,
        c.course_name,
        r.room_name,
        r.capacity as room_capacity,
        d.department_name,
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id) as estimated_students
    FROM exam_schedules es
    JOIN courses c ON es.course_id = c.course_id
    JOIN rooms r ON es.room_id = r.room_id
    JOIN departments d ON c.department_id = d.department_id
    WHERE es.supervisor_id = ?
    ORDER BY es.exam_date ASC, es.start_time
");
$exam_assignments_stmt->execute([$instructor_id]);
$exam_assignments = $exam_assignments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Count statistics
$total_assignments = count($exam_assignments);
$upcoming_assignments = 0;
$past_assignments = 0;
$today_assignments = 0;
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

foreach($exam_assignments as $exam) {
    $exam_date = $exam['exam_date'];
    $start_time = $exam['start_time'];
    
    if($exam_date > $current_date) {
        $upcoming_assignments++;
    } elseif($exam_date == $current_date) {
        $today_assignments++;
        if($start_time > $current_time) {
            $upcoming_assignments++;
        } else {
            $past_assignments++;
        }
    } else {
        $past_assignments++;
    }
}

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exam Supervisory Assignments | Instructor Dashboard</title>
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
    overflow-x: hidden;
}

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

/* ========== Topbar ========== */
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

.content-wrapper {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 6px var(--shadow-color);
    min-height: calc(100vh - 40px);
}

/* ========== Header ========== */
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
.stats-cards .card i {
    font-size:28px;
    position:absolute;
    top:15px;
    right:15px;
    opacity:0.8;
}

[data-theme="dark"] .stats-cards .card {
    background: linear-gradient(135deg, #3730a3, #1d4ed8);
}

/* ========== Messages ========== */
.message {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: fadeIn 0.5s ease;
}

.message.success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.message.error {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ========== Exam Assignments Table ========== */
.assignments-container {
    margin-top: 30px;
}

.assignments-container h2 {
    color: var(--text-primary);
    margin-bottom: 20px;
    font-size: 1.5rem;
    font-weight: 600;
}

.table-container {
    overflow-x: auto;
    border-radius: 12px;
    box-shadow: 0 4px 6px var(--shadow-color);
}

.assignments-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-card);
}

.assignments-table th,
.assignments-table td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.assignments-table th {
    background: var(--table-header);
    color: var(--text-sidebar);
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.assignments-table tr:hover {
    background: var(--hover-color);
}

.assignments-table tr.upcoming {
    border-left: 4px solid #10b981;
}

.assignments-table tr.past {
    border-left: 4px solid #6b7280;
    opacity: 0.8;
}

.assignments-table tr.today {
    border-left: 4px solid #f59e0b;
    background: rgba(245, 158, 11, 0.05);
}

/* Status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.status-upcoming {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-past {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
    border: 1px solid rgba(107, 114, 128, 0.3);
}

.status-today {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-ongoing {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

/* Exam type badges */
.exam-type-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 5px;
}

.exam-midterm {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: white;
}

.exam-final {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.exam-quiz {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.exam-practical {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.exam-project-defense {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
}

/* Student count badge */
.student-count {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

/* Student type badge */
.student-type-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 5px;
}

.student-regular {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.student-extension {
    background: rgba(139, 92, 246, 0.1);
    color: #8b5cf6;
    border: 1px solid rgba(139, 92, 246, 0.3);
}

/* Countdown timer */
.countdown-timer {
    font-family: 'Courier New', monospace;
    font-weight: bold;
    padding: 6px 12px;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-radius: 8px;
    border: 1px solid rgba(239, 68, 68, 0.3);
    font-size: 0.9rem;
    margin-top: 5px;
    display: inline-block;
}

/* Empty state */
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

/* Exam status badge */
.exam-status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 5px;
}

.status-published {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-draft {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
    border: 1px solid rgba(107, 114, 128, 0.3);
}

/* Instructions box */
.instructions-box {
    background: rgba(99, 102, 241, 0.05);
    border-left: 3px solid #6366f1;
    padding: 10px 15px;
    margin-top: 10px;
    border-radius: 0 8px 8px 0;
    font-size: 0.85rem;
    color: var(--text-secondary);
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.instructions-box:hover {
    white-space: normal;
    overflow: visible;
    z-index: 10;
    position: relative;
    background: var(--bg-card);
    box-shadow: 0 4px 12px var(--shadow-color);
}

/* ========== Supervisor Notice ========== */
.supervisor-notice {
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 6px 20px rgba(30, 64, 175, 0.2);
    border-left: 5px solid #f59e0b;
}

.supervisor-notice h3 {
    font-size: 1.3rem;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.supervisor-notice p {
    font-size: 1rem;
    opacity: 0.9;
    line-height: 1.5;
}

/* ========== Assignment Info Card ========== */
.assignment-info {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border: 2px solid var(--border-color);
    transition: all 0.3s ease;
}

.assignment-info:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px var(--shadow-color);
    border-color: #3b82f6;
}

.assignment-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.assignment-title {
    flex: 1;
}

.assignment-title h4 {
    color: var(--text-primary);
    font-size: 1.2rem;
    margin-bottom: 5px;
    font-weight: 600;
}

.assignment-title small {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.assignment-status {
    text-align: right;
}

.assignment-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: var(--bg-secondary);
    border-radius: 8px;
}

.detail-item i {
    color: #3b82f6;
    font-size: 1.2rem;
}

.detail-item div {
    flex: 1;
}

.detail-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 3px;
}

.detail-value {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1rem;
}

/* ========== Responsive ========== */
@media screen and (max-width: 768px){
    .topbar { display: flex; }
    .sidebar { transform: translateX(-100%); width: 250px; }
    .sidebar.active { transform: translateX(0); }
    .main-content { margin-left: 0; padding: 15px; width: 100%; }
    .content-wrapper { padding: 20px; border-radius: 0; }
    .header { flex-direction: column; gap: 15px; align-items: flex-start; }
    .header h1 { font-size: 1.8rem; }
    .stats-cards { flex-direction: column; }
    .assignment-details { grid-template-columns: 1fr; }
    .assignments-table { min-width: 800px; }
}

@media screen and (max-width: 480px){
    .assignments-table th, 
    .assignments-table td { padding: 12px; }
    .supervisor-notice { padding: 15px; }
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Exam Supervisory Assignments</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

   <!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-profile">
        <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" 
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

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
                <div class="welcome-section">
                    <h1>Exam Supervisory Assignments</h1>
                    <p>Your assigned exam supervision duties</p>
                </div>
                <div class="user-info">
                    <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile" 
                         onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                    <div>
                        <div><?= htmlspecialchars($user['username'] ?? 'Instructor') ?></div>
                        <small>Exam Supervisor</small>
                    </div>
                </div>
            </div>

            <!-- Supervisor Notice -->
            <div class="supervisor-notice">
                <h3><i class="fas fa-bell"></i> Important Notice</h3>
                <p>You have been assigned as a supervisor for the following exams. Please note the dates, times, and locations. Arrive at least 30 minutes before each exam starts. For any questions or issues, contact the department head.</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="card">
                    <h3>Total Assignments</h3>
                    <p><?= $total_assignments ?></p>
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="card">
                    <h3>Upcoming Exams</h3>
                    <p><?= $upcoming_assignments ?></p>
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="card">
                    <h3>Today's Exams</h3>
                    <p><?= $today_assignments ?></p>
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="card">
                    <h3>Past Exams</h3>
                    <p><?= $past_assignments ?></p>
                    <i class="fas fa-history"></i>
                </div>
            </div>

            <!-- Exam Assignments List -->
            <div class="assignments-container">
                <h2>Your Exam Supervision Schedule</h2>
                
                <?php if(!empty($exam_assignments)): ?>
                    <div class="assignments-list">
                        <?php foreach($exam_assignments as $exam): 
                            $exam_id = $exam['exam_id'];
                            $exam_date = $exam['exam_date'];
                            $start_time = $exam['start_time'];
                            $end_time = $exam['end_time'];
                            
                            $current_datetime = new DateTime();
                            $exam_start_datetime = new DateTime("$exam_date $start_time");
                            $exam_end_datetime = new DateTime("$exam_date $end_time");
                            
                            $is_past = $exam_end_datetime < $current_datetime;
                            $is_today = $exam_date == date('Y-m-d');
                            $is_ongoing = $current_datetime >= $exam_start_datetime && $current_datetime <= $exam_end_datetime;
                            $is_upcoming = $exam_start_datetime > $current_datetime;
                            
                            // Determine status
                            if($is_ongoing) {
                                $status_text = 'Ongoing Now';
                                $status_class = 'status-ongoing';
                                $status_icon = 'fa-hourglass-half';
                            } elseif($is_today && $is_upcoming) {
                                $status_text = 'Today';
                                $status_class = 'status-today';
                                $status_icon = 'fa-calendar-day';
                            } elseif($is_today) {
                                $status_text = 'Today';
                                $status_class = 'status-today';
                                $status_icon = 'fa-calendar-day';
                            } elseif($is_past) {
                                $status_text = 'Completed';
                                $status_class = 'status-past';
                                $status_icon = 'fa-check-circle';
                            } else {
                                $days_left = $current_datetime->diff($exam_start_datetime)->days;
                                $status_text = $days_left == 0 ? 'Tomorrow' : "In $days_left days";
                                $status_class = 'status-upcoming';
                                $status_icon = 'fa-calendar-check';
                            }
                            
                            // Student count
                            $estimated_students = $exam['estimated_students'] ?? 0;
                            $max_students = $exam['max_students'] ?? 50;
                        ?>
                            <div class="assignment-info">
                                <div class="assignment-header">
                                    <div class="assignment-title">
                                        <h4>
                                            <?= htmlspecialchars($exam['course_code']) ?> - <?= htmlspecialchars($exam['course_name']) ?>
                                            <span class="exam-type-badge exam-<?= strtolower(str_replace(' ', '-', $exam['exam_type'])) ?>">
                                                <?= htmlspecialchars($exam['exam_type']) ?>
                                            </span>
                                        </h4>
                                        <small>
                                            <i class="fas fa-building"></i> <?= htmlspecialchars($exam['department_name']) ?> Department
                                        </small>
                                    </div>
                                    <div class="assignment-status">
                                        <span class="status-badge <?= $status_class ?>">
                                            <i class="fas <?= $status_icon ?>"></i>
                                            <?= $status_text ?>
                                        </span>
                                        <?php if($exam['is_published'] == 1): ?>
                                            <span class="exam-status-badge status-published">
                                                <i class="fas fa-eye"></i> Published
                                            </span>
                                        <?php else: ?>
                                            <span class="exam-status-badge status-draft">
                                                <i class="fas fa-eye-slash"></i> Not Published
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="assignment-details">
                                    <div class="detail-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <div>
                                            <div class="detail-label">Date</div>
                                            <div class="detail-value"><?= date('l, F j, Y', strtotime($exam_date)) ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <div>
                                            <div class="detail-label">Time</div>
                                            <div class="detail-value"><?= date('h:i A', strtotime($start_time)) ?> - <?= date('h:i A', strtotime($end_time)) ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <div>
                                            <div class="detail-label">Location</div>
                                            <div class="detail-value"><?= htmlspecialchars($exam['room_name']) ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <i class="fas fa-users"></i>
                                        <div>
                                            <div class="detail-label">Students</div>
                                            <div class="detail-value"><?= $estimated_students ?> / <?= $max_students ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <i class="fas fa-user-graduate"></i>
                                        <div>
                                            <div class="detail-label">Student Type</div>
                                            <div class="detail-value">
                                                <?= ucfirst($exam['student_type'] ?? 'regular') ?> Year <?= $exam['year'] ?? '1' ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <i class="fas fa-chalkboard-teacher"></i>
                                        <div>
                                            <div class="detail-label">Your Role</div>
                                            <div class="detail-value">Exam Supervisor</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if(!empty($exam['instructions'])): ?>
                                    <div class="instructions-box" style="margin-top: 15px; max-width: 100%;">
                                        <i class="fas fa-info-circle"></i> 
                                        <strong>Special Instructions:</strong> <?= htmlspecialchars($exam['instructions']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if($is_upcoming && !$is_today): ?>
                                    <div class="countdown-timer" style="margin-top: 15px;">
                                        <i class="fas fa-clock"></i> 
                                        Exam starts in: <span id="timer-<?= $exam['exam_id'] ?>"></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-check"></i>
                        <h3>No Exam Assignments</h3>
                        <p>You haven't been assigned to supervise any exams yet. The department head will assign you to exams when needed.</p>
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
        
        // Initialize countdown timers
        initializeCountdownTimers();
        
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
        
        // Add animation to assignment cards
        const assignmentCards = document.querySelectorAll('.assignment-info');
        assignmentCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 300 + (index * 100));
        });
    });

    // Countdown timer function
    function initializeCountdownTimers() {
        const countdownElements = document.querySelectorAll('[id^="timer-"]');
        
        countdownElements.forEach(element => {
            const examId = element.id.split('-')[1];
            updateCountdown(examId, element);
            
            setInterval(() => {
                updateCountdown(examId, element);
            }, 60000);
        });
    }
    
    function updateCountdown(examId, element) {
        const card = element.closest('.assignment-info');
        const dateElement = card.querySelector('.detail-item:nth-child(1) .detail-value');
        const timeElement = card.querySelector('.detail-item:nth-child(2) .detail-value');
        
        if(!dateElement || !timeElement) return;
        
        const dateText = dateElement.textContent.trim();
        const timeText = timeElement.textContent.split('-')[0].trim();
        
        // Parse date from "Monday, January 6, 2025"
        const examDateTime = new Date(dateText + ' ' + timeText);
        const now = new Date();
        const diff = examDateTime - now;
        
        if (diff > 0) {
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            
            let text = '';
            if (days > 0) {
                text = `${days} day${days > 1 ? 's' : ''}, ${hours} hour${hours > 1 ? 's' : ''}`;
            } else if (hours > 0) {
                text = `${hours} hour${hours > 1 ? 's' : ''}, ${minutes} minute${minutes > 1 ? 's' : ''}`;
            } else {
                text = `${minutes} minute${minutes > 1 ? 's' : ''}`;
            }
            
            element.textContent = text;
        } else {
            element.textContent = 'Exam has started';
        }
    }

    // Confirm logout
    document.querySelector('a[href="../logout.php"]').addEventListener('click', function(e) {
        if(!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });
    
    // Close sidebar when clicking on a link (mobile)
    document.querySelectorAll('.sidebar a').forEach(link => {
        link.addEventListener('click', function() {
            if(window.innerWidth <= 768) {
                toggleSidebar();
            }
        });
    });
    </script>
</body>
</html>