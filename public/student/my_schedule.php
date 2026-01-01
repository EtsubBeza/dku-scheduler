<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

$student_id = $_SESSION['user_id'];

// Fetch current user info with their YEAR - IMPORTANT!
$user_stmt = $pdo->prepare("SELECT username, profile_picture, year FROM users WHERE user_id = ?");
$user_stmt->execute([$student_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$student_year = $user['year'] ?? ''; // Get the student's year (e.g., "1", "2", "E1", "E2")

// Determine profile picture path
$uploads_dir = __DIR__ . '/../uploads/';
$profile_picture = $user['profile_picture'] ?? '';
$default_profile = 'default_profile.png';

if(!empty($profile_picture)) {
    if(file_exists($uploads_dir . $profile_picture)) {
        $profile_img_path = '../uploads/' . $profile_picture;
    } else if(file_exists('uploads/' . $profile_picture)) {
        $profile_img_path = 'uploads/' . $profile_picture;
    } else if(file_exists('../uploads/' . $profile_picture)) {
        $profile_img_path = '../uploads/' . $profile_picture;
    } else if(file_exists('../../uploads/' . $profile_picture)) {
        $profile_img_path = '../../uploads/' . $profile_picture;
    } else {
        $profile_img_path = '../assets/' . $default_profile;
    }
} else {
    $profile_img_path = '../assets/' . $default_profile;
}

// Handle Excel/CSV Export
if(isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Fetch schedule data WITH YEAR FILTERING
    $schedules = $pdo->prepare("
        SELECT c.course_name, c.course_code, u.full_name AS instructor_name, 
               r.room_name, s.day, s.start_time, s.end_time
        FROM schedule s
        JOIN courses c ON s.course_id = c.course_id
        JOIN users u ON s.instructor_id = u.user_id
        JOIN rooms r ON s.room_id = r.room_id
        JOIN enrollments e ON s.schedule_id = e.schedule_id
        WHERE e.student_id = ? 
        AND s.year = ?  -- CRITICAL: Filter by student's year
        ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), s.start_time
    ");
    $schedules->execute([$student_id, $student_year]);
    $my_schedule = $schedules->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="my_schedule_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Course Name', 'Course Code', 'Instructor', 'Room', 'Day', 'Start Time', 'End Time']);
    
    foreach($my_schedule as $s) {
        fputcsv($output, [
            $s['course_name'],
            $s['course_code'],
            $s['instructor_name'],
            $s['room_name'],
            $s['day'],
            date('g:i A', strtotime($s['start_time'])),
            date('g:i A', strtotime($s['end_time']))
        ]);
    }
    fclose($output);
    exit;
}

// Normal page load - fetch schedule for display WITH YEAR FILTERING
$schedules = $pdo->prepare("
    SELECT s.schedule_id, c.course_name, c.course_code, u.full_name AS instructor_name, 
           r.room_name, s.day, s.start_time, s.end_time, s.year
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN users u ON s.instructor_id = u.user_id
    JOIN rooms r ON s.room_id = r.room_id
    JOIN enrollments e ON s.schedule_id = e.schedule_id
    WHERE e.student_id = ? 
    AND s.year = ?  -- CRITICAL: Filter by student's year
    ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), s.start_time
");
$schedules->execute([$student_id, $student_year]);
$my_schedule = $schedules->fetchAll();

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);

// Debug info (remove in production)
$debug_info = "";
if (empty($my_schedule)) {
    $debug_info = "No schedules found for student ID: $student_id, Year: $student_year";
    
    // Check if student is enrolled in any courses
    $enrollment_check = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM enrollments 
        WHERE student_id = ?
    ");
    $enrollment_check->execute([$student_id]);
    $enrollment_count = $enrollment_check->fetch()['count'];
    
    $debug_info .= " | Enrollments: $enrollment_count";
    
    // Check available schedules for this year
    $schedule_check = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM schedule 
        WHERE year = ?
    ");
    $schedule_check->execute([$student_year]);
    $schedule_count = $schedule_check->fetch()['count'];
    
    $debug_info .= " | Schedules for year $student_year: $schedule_count";
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<title>My Schedule | Student Dashboard</title>
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

/* Sidebar title */
.sidebar h2 {
    text-align: center;
    color: var(--text-sidebar);
    margin-bottom: 25px;
    font-size: 22px;
    padding: 0 20px;
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

/* Student info box */
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

/* Debug info (only show when empty) */
.debug-info {
    background: rgba(239, 68, 68, 0.1);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #ef4444;
    color: var(--error-text);
    font-family: monospace;
    font-size: 0.9rem;
}

/* ================= Export Buttons ================= */
.export-buttons {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.export-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    box-shadow: 0 4px 6px var(--shadow-color);
}

.export-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px var(--shadow-lg);
}

.export-btn.pdf {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.export-btn.excel {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.export-btn.print {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.export-btn i {
    font-size: 1.1rem;
}

[data-theme="dark"] .export-btn.pdf {
    background: linear-gradient(135deg, #f87171, #dc2626);
}

[data-theme="dark"] .export-btn.excel {
    background: linear-gradient(135deg, #34d399, #059669);
}

[data-theme="dark"] .export-btn.print {
    background: linear-gradient(135deg, #60a5fa, #3b82f6);
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

.course-code {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 4px;
    font-weight: 500;
}

/* Empty state */
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

/* Today's classes info box */
.table-container + div {
    margin-top: 15px;
    padding: 10px;
    background: var(--info-bg);
    border-radius: 8px;
    border-left: 4px solid #f59e0b;
}

.table-container + div small {
    color: var(--info-text);
    font-weight: 600;
}

[data-theme="dark"] .table-container + div {
    background: rgba(245, 158, 11, 0.1);
}

[data-theme="dark"] .table-container + div small {
    color: #fcd34d;
}

/* ================= Print Styles ================= */
@media print {
    .sidebar, .topbar, .overlay, .export-buttons, .user-info, .student-info-box, .debug-info { 
        display: none !important; 
    }
    .main-content { 
        margin-left: 0 !important; 
        padding: 0 !important; 
        background: white !important;
    }
    .content-wrapper {
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 20px !important;
    }
    .schedule-table {
        box-shadow: none !important;
        border: 1px solid #000 !important;
    }
    .schedule-table th {
        background-color: #f1f1f1 !important;
        color: #000 !important;
        -webkit-print-color-adjust: exact;
    }
    .today-row {
        background-color: #fef3c7 !important;
        -webkit-print-color-adjust: exact;
    }
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
    .export-buttons { flex-direction: column; }
    .export-btn { width: 100%; justify-content: center; }
    .table-container { overflow-x: auto; }
    .schedule-table { min-width: 600px; }
}

/* Dark mode specific table adjustments */
[data-theme="dark"] .schedule-table th {
    color: var(--text-sidebar);
}

[data-theme="dark"] .schedule-table td {
    color: var(--text-primary);
}

[data-theme="dark"] .schedule-table tr:hover {
    background: rgba(255, 255, 255, 0.05);
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
        <h2>My Schedule</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture">
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
        <a href="student_dashboard.php" class="<?= $current_page=='student_dashboard.php'?'active':'' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="my_schedule.php" class="active">
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
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
                <div class="welcome-section">
                    <h1>My Schedule</h1>
                    <p>View your class timetable and export to PDF/Excel</p>
                </div>
                <div class="user-info">
                    <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile">
                    <div>
                        <div><?= htmlspecialchars($user['username'] ?? 'Student') ?></div>
                        <small>Student</small>
                    </div>
                </div>
            </div>

            <!-- Student Info -->
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

            <?php if(!empty($debug_info)): ?>
                <div class="debug-info">
                    <i class="fas fa-exclamation-triangle"></i> Debug Info: <?= $debug_info ?>
                </div>
            <?php endif; ?>

            <!-- Export Buttons -->
            <div class="export-buttons">
                <a href="?export=pdf" class="export-btn pdf" target="_blank">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
                <a href="?export=excel" class="export-btn excel">
                    <i class="fas fa-file-excel"></i> Export Excel/CSV
                </a>
                <button onclick="window.print()" class="export-btn print">
                    <i class="fas fa-print"></i> Print Schedule
                </button>
            </div>

            <!-- Schedule Table -->
            <div class="schedule-section">
                <h2 style="margin-bottom: 20px; color: var(--text-primary);">Class Timetable</h2>
                <div class="table-container">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Instructor</th>
                                <th>Room</th>
                                <th>Day</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        $today = date('l');
                        $hasTodayClass = false;
                        foreach($my_schedule as $s): 
                            $todayClass = ($s['day']==$today) ? 'today-row' : '';
                            if($s['day']==$today) $hasTodayClass = true;
                        ?>
                            <tr class="<?= $todayClass ?>">
                                <td>
                                    <?= htmlspecialchars($s['course_name']) ?>
                                    <?php if(!empty($s['course_code'])): ?>
                                        <div class="course-code"><?= htmlspecialchars($s['course_code']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($s['instructor_name']) ?></td>
                                <td><?= htmlspecialchars($s['room_name']) ?></td>
                                <td><?= htmlspecialchars($s['day']) ?></td>
                                <td><?= date('g:i A', strtotime($s['start_time'])) . ' - ' . date('g:i A', strtotime($s['end_time'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($my_schedule)): ?>
                            <tr>
                                <td colspan="5">
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
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if($hasTodayClass): ?>
                <div style="margin-top: 15px; padding: 10px; background: var(--info-bg); border-radius: 8px; border-left: 4px solid #f59e0b;">
                    <small style="color: var(--info-text); font-weight: 600;">
                        <i class="fas fa-info-circle"></i> Highlighted rows indicate today's classes
                    </small>
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

    // Confirm logout
    document.querySelector('a[href="../logout.php"]').addEventListener('click', function(e) {
        if(!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });

    // Add animation to table rows on page load
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.querySelectorAll('.schedule-table tbody tr');
        rows.forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                row.style.transition = 'all 0.5s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateX(0)';
            }, index * 50);
        });
    });
    </script>
</body>
</html>