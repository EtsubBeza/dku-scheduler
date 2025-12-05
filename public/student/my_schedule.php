<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// Fetch current user info - MATCHING DASHBOARD
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Determine profile picture path - FIXED VERSION
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

// Handle Excel/CSV Export
if(isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Fetch schedule data
    $schedules = $pdo->prepare("
        SELECT c.course_name, c.course_code, u.full_name AS instructor_name, 
               r.room_name, s.day, s.start_time, s.end_time
        FROM schedule s
        JOIN courses c ON s.course_id = c.course_id
        JOIN users u ON s.instructor_id = u.user_id
        JOIN rooms r ON s.room_id = r.room_id
        JOIN enrollments e ON s.schedule_id = e.schedule_id
        WHERE e.student_id = ?
        ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), s.start_time
    ");
    $schedules->execute([$student_id]);
    $my_schedule = $schedules->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="my_schedule_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    // Add BOM for UTF-8 to help Excel with special characters
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

// Normal page load - fetch schedule for display
$schedules = $pdo->prepare("
    SELECT s.schedule_id, c.course_name, c.course_code, u.full_name AS instructor_name, 
           r.room_name, s.day, s.start_time, s.end_time
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN users u ON s.instructor_id = u.user_id
    JOIN rooms r ON s.room_id = r.room_id
    JOIN enrollments e ON s.schedule_id = e.schedule_id
    WHERE e.student_id = ?
    ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), s.start_time
");
$schedules->execute([$student_id]);
$my_schedule = $schedules->fetchAll();

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Schedule | Student Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin:0; padding:0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

/* ================= Topbar for Hamburger ================= */
.topbar {
    display: none;
    position: fixed; top:0; left:0; width:100%;
    background:#2c3e50; color:#fff;
    padding:15px 20px;
    z-index:1200;
    justify-content:space-between; align-items:center;
}
.menu-btn {
    font-size:26px;
    background:#1abc9c;
    border:none; color:#fff;
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
    background:#1f2937; color:#fff;
    z-index:1100;
    transition: transform 0.3s ease;
    padding: 20px 0;
}
.sidebar.hidden { transform:translateX(-260px); }
.sidebar a { 
    display:block; 
    padding:12px 20px; 
    color:#fff; 
    text-decoration:none; 
    transition: background 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.sidebar a:hover, .sidebar a.active { background:#1abc9c; }

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
    color: #fff;
    font-weight: bold;
    margin: 0;
    font-size: 16px;
}

/* Sidebar title */
.sidebar h2 {
    text-align: center;
    color: #ecf0f1;
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
    background: #f8fafc;
    transition: all 0.3s ease;
}

/* Content Wrapper */
.content-wrapper {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    min-height: calc(100vh - 40px);
}

/* Header Styles */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.header h1 {
    font-size: 2.2rem;
    color: #1f2937;
    font-weight: 700;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #f8fafc;
    padding: 12px 18px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.user-info img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
}

/* Welcome Section */
.welcome-section {
    margin-bottom: 30px;
}

.welcome-section p {
    color: #6b7280;
    font-size: 1.1rem;
    margin-top: 10px;
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
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.export-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
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

/* ================= Schedule Table ================= */
.schedule-section {
    margin-top: 30px;
}

.table-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
}

.schedule-table {
    width: 100%;
    border-collapse: collapse;
}

.schedule-table th {
    background: #f8fafc;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
}

.schedule-table td {
    padding: 15px;
    border-bottom: 1px solid #f3f4f6;
}

.schedule-table tr:last-child td {
    border-bottom: none;
}

.schedule-table tr:hover {
    background: #f9fafb;
}

.schedule-table .today-row {
    background: #fff7ed !important;
    border-left: 4px solid #f59e0b;
}

.course-code {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 4px;
    font-weight: 500;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: #d1d5db;
}

.empty-state h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: #374151;
}

.empty-state p {
    color: #6b7280;
    max-width: 400px;
    margin: 0 auto;
}

/* ================= Print Styles ================= */
@media print {
    .sidebar, .topbar, .overlay, .export-buttons, .user-info { 
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

    <!-- Sidebar - SAME AS OTHER PAGES -->
    <div class="sidebar">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture">
            <p><?= htmlspecialchars($user['username'] ?? 'Student') ?></p>
        </div>
        <h2>Student Panel</h2>
        <a href="student_dashboard.php" class="<?= $current_page=='student_dashboard.php'?'active':'' ?>">Dashboard</a>
        <a href="my_schedule.php" class="<?= $current_page=='my_schedule.php'?'active':'' ?>">My Schedule</a>
        <a href="view_exam_schedules.php" class="<?= $current_page=='view_exam_schedules.php'?'active':'' ?>">Exam Schedule</a>
        <a href="view_announcements.php" class="<?= $current_page=='view_announcements.php'?'active':'' ?>">Announcements</a>
        <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">Edit Profile</a>
        <a href="../logout.php">Logout</a>
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
                <h2 style="margin-bottom: 20px; color: #1f2937;">Class Timetable</h2>
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
                                        <p>You don't have any classes scheduled at the moment.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if($hasTodayClass): ?>
                <div style="margin-top: 15px; padding: 10px; background: #fff7ed; border-radius: 8px; border-left: 4px solid #f59e0b;">
                    <small style="color: #92400e; font-weight: 600;">
                        <i class="fas fa-info-circle"></i> Highlighted rows indicate today's classes
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

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