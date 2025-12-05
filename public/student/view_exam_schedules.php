<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only allow students
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$dept_id = $_SESSION['department_id'] ?? 0;
$message = "";
$message_type = "success";

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

// Helper function to display messages
function showMessage($type, $text) {
    global $message, $message_type;
    $message = $text;
    $message_type = $type;
}

// Get exam schedules for enrolled courses only
try {
    $exams_stmt = $pdo->prepare("
        SELECT DISTINCT es.exam_id, es.course_id, es.exam_type, es.exam_date, es.start_time, es.end_time,
               es.room_id, es.supervisor_id, es.max_students, es.is_published,
               c.course_code, c.course_name,
               r.room_name, r.capacity,
               u.username as supervisor_name
        FROM exam_schedules es
        JOIN courses c ON es.course_id = c.course_id
        JOIN enrollments e ON es.course_id = e.course_id
        LEFT JOIN rooms r ON es.room_id = r.room_id
        LEFT JOIN users u ON es.supervisor_id = u.user_id
        WHERE e.student_id = ?
        AND es.is_published = 1
        ORDER BY es.exam_date, es.start_time
    ");
    $exams_stmt->execute([$student_id]);
    $exams = $exams_stmt->fetchAll();
    
} catch (PDOException $e) {
    showMessage('error', "Error loading exam schedules: " . $e->getMessage());
    error_log("Exam schedule error: " . $e->getMessage());
    $exams = [];
}

// Fetch exam statistics for student
try {
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT es.exam_id) as total_exams,
            SUM(CASE WHEN es.exam_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_exams,
            COUNT(DISTINCT es.exam_type) as exam_types_count
        FROM exam_schedules es
        JOIN enrollments e ON es.course_id = e.course_id
        WHERE e.student_id = ?
        AND es.is_published = 1
    ");
    $stats_stmt->execute([$student_id]);
    $stats = $stats_stmt->fetch();
    
    if (!$stats) {
        $stats = [
            'total_exams' => 0,
            'upcoming_exams' => 0,
            'exam_types_count' => 0
        ];
    }
} catch (PDOException $e) {
    $stats = [
        'total_exams' => 0,
        'upcoming_exams' => 0,
        'exam_types_count' => 0
    ];
    error_log("Stats error: " . $e->getMessage());
}

// Fetch upcoming exams (next 7 days)
try {
    $upcoming_stmt = $pdo->prepare("
        SELECT DISTINCT es.exam_id, es.course_id, es.exam_type, es.exam_date, es.start_time, es.end_time,
               c.course_code, c.course_name, r.room_name
        FROM exam_schedules es
        JOIN courses c ON es.course_id = c.course_id
        JOIN enrollments e ON es.course_id = e.course_id
        LEFT JOIN rooms r ON es.room_id = r.room_id
        WHERE e.student_id = ? 
        AND es.is_published = 1
        AND es.exam_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY es.exam_date, es.start_time
        LIMIT 5
    ");
    $upcoming_stmt->execute([$student_id]);
    $upcoming_exams = $upcoming_stmt->fetchAll();
    
    if (!$upcoming_exams) {
        $upcoming_exams = [];
    }
} catch (PDOException $e) {
    $upcoming_exams = [];
    error_log("Upcoming exams error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Exam Schedule | Student Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- FullCalendar CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
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
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
}

.stat-card h3 {
    font-size: 1rem;
    color: #6b7280;
    margin-bottom: 10px;
    font-weight: 600;
}

.stat-card .number {
    font-size: 2rem;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 10px;
}

.stat-card .icon {
    font-size: 2rem;
    margin-bottom: 15px;
    display: block;
}

/* ================= Calendar Card ================= */
.calendar-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
    margin-top: 30px;
}

.calendar-header {
    padding: 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e5e7eb;
}

.calendar-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
}

#examCalendar {
    padding: 20px;
    background: white;
}

/* FullCalendar Custom Styling */
.fc {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.fc-toolbar-title {
    font-size: 1.5rem !important;
    font-weight: 600;
    color: #1f2937;
}

.fc-button {
    background: #f8fafc !important;
    border: 1px solid #e5e7eb !important;
    color: #374151 !important;
    font-weight: 500 !important;
    border-radius: 8px !important;
    padding: 6px 12px !important;
    transition: all 0.3s ease !important;
}

.fc-button:hover {
    background: #e5e7eb !important;
    transform: translateY(-1px);
}

.fc-button-primary:not(:disabled):active, 
.fc-button-primary:not(:disabled).fc-button-active {
    background: #1abc9c !important;
    border-color: #1abc9c !important;
}

.fc-day-today {
    background-color: #fff7ed !important;
}

.fc-event {
    border-radius: 6px !important;
    border: none !important;
    padding: 4px 8px !important;
    font-size: 0.85rem !important;
    cursor: pointer !important;
}

.fc-event:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* ================= Exam Table ================= */
.exam-section {
    margin-top: 30px;
}

.table-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
}

.exam-table {
    width: 100%;
    border-collapse: collapse;
}

.exam-table th {
    background: #f8fafc;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
}

.exam-table td {
    padding: 15px;
    border-bottom: 1px solid #f3f4f6;
}

.exam-table tr:last-child td {
    border-bottom: none;
}

.exam-table tr:hover {
    background: #f9fafb;
}

.exam-table .today-exam {
    background: #fff7ed !important;
    border-left: 4px solid #f59e0b;
}

.exam-table .upcoming-exam {
    background: #f0fdf4 !important;
    border-left: 4px solid #10b981;
}

.exam-table .past-exam {
    background: #f8fafc !important;
    border-left: 4px solid #94a3b8;
}

/* Badge styles */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-primary {
    background: #e0e7ff;
    color: #3730a3;
}

.badge-success {
    background: #dcfce7;
    color: #166534;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}

.badge-secondary {
    background: #f3f4f6;
    color: #6b7280;
}

/* Message styling */
.message {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.message.success {
    background: #dcfce7;
    color: #166534;
    border-left: 4px solid #10b981;
}

.message.error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

.message.warning {
    background: #fef3c7;
    color: #92400e;
    border-left: 4px solid #f59e0b;
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
    .table-container { overflow-x: auto; }
    .exam-table { min-width: 600px; }
    .fc-toolbar {
        flex-direction: column !important;
        gap: 10px !important;
    }
    .fc-toolbar-title {
        font-size: 1.2rem !important;
    }
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Exam Schedule</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar - EXACTLY LIKE STUDENT DASHBOARD -->
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
                    <h1>Exam Schedules</h1>
                    <p>View all your upcoming and past exam schedules</p>
                </div>
                <div class="user-info">
                    <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile">
                    <div>
                        <div><?= htmlspecialchars($user['username'] ?? 'Student') ?></div>
                        <small>Student</small>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if($message): ?>
                <div class="message <?= $message_type ?>">
                    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : ($message_type === 'error' ? 'exclamation-circle' : 'info-circle')) ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Quick Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <i class="fas fa-calendar-alt icon" style="color: #3b82f6;"></i>
                    <h3>Total Exams</h3>
                    <div class="number"><?= $stats['total_exams'] ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock icon" style="color: #10b981;"></i>
                    <h3>Upcoming Exams</h3>
                    <div class="number"><?= $stats['upcoming_exams'] ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-file-alt icon" style="color: #f59e0b;"></i>
                    <h3>Exam Types</h3>
                    <div class="number"><?= $stats['exam_types_count'] ?></div>
                </div>
            </div>

            <!-- Calendar View -->
            <div class="calendar-card">
                <div class="calendar-header">
                    <h3><i class="fas fa-calendar"></i> Exam Calendar View</h3>
                </div>
                <div id="examCalendar"></div>
            </div>

            <!-- Upcoming Exams Section -->
            <?php if(!empty($upcoming_exams)): ?>
            <div class="exam-section">
                <h2 style="margin-bottom: 20px; color: #1f2937;">Upcoming Exams (Next 7 Days)</h2>
                <div class="table-container">
                    <table class="exam-table">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Exam Type</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Room</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($upcoming_exams as $exam): 
                                $exam_timestamp = strtotime($exam['exam_date'] . ' ' . $exam['start_time']);
                                $is_today = date('Y-m-d', $exam_timestamp) == date('Y-m-d');
                            ?>
                            <tr class="upcoming-exam">
                                <td>
                                    <strong><?= htmlspecialchars($exam['course_code']) ?></strong><br>
                                    <small style="color: #6b7280;"><?= htmlspecialchars($exam['course_name']) ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?= htmlspecialchars($exam['exam_type']) ?></span>
                                </td>
                                <td><?= date('M d, Y', strtotime($exam['exam_date'])) ?></td>
                                <td>
                                    <?= date('h:i A', strtotime($exam['start_time'])) ?> - 
                                    <?= date('h:i A', strtotime($exam['end_time'])) ?>
                                </td>
                                <td><?= htmlspecialchars($exam['room_name'] ?? 'TBA') ?></td>
                                <td>
                                    <?php if($is_today): ?>
                                        <span class="badge badge-warning">Today</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Upcoming</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- All Exam Schedules Table -->
            <div class="exam-section">
                <h2 style="margin-bottom: 20px; color: #1f2937;">All Exam Schedules</h2>
                <div class="table-container">
                    <table class="exam-table">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Exam Type</th>
                                <th>Date & Time</th>
                                <th>Room</th>
                                <th>Supervisor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($exams)): ?>
                                <?php foreach($exams as $exam): ?>
                                    <?php
                                    $current_time = time();
                                    $exam_timestamp = strtotime($exam['exam_date'] . ' ' . $exam['start_time']);
                                    $is_today = date('Y-m-d', $exam_timestamp) == date('Y-m-d');
                                    $is_past = $exam_timestamp < $current_time;
                                    $is_upcoming = $exam_timestamp > $current_time;
                                    $row_class = '';
                                    
                                    if ($is_today) {
                                        $row_class = 'today-exam';
                                        $status = 'Today';
                                        $status_class = 'badge-warning';
                                    } elseif ($is_past) {
                                        $row_class = 'past-exam';
                                        $status = 'Completed';
                                        $status_class = 'badge-secondary';
                                    } else {
                                        $row_class = 'upcoming-exam';
                                        $status = 'Upcoming';
                                        $status_class = 'badge-success';
                                    }
                                    ?>
                                    <tr class="<?= $row_class ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($exam['course_code']) ?></strong><br>
                                            <small style="color: #6b7280;"><?= htmlspecialchars($exam['course_name']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary"><?= htmlspecialchars($exam['exam_type']) ?></span>
                                        </td>
                                        <td>
                                            <?= date('M d, Y', strtotime($exam['exam_date'])) ?><br>
                                            <small style="color: #6b7280;">
                                                <?= date('h:i A', strtotime($exam['start_time'])) ?> - 
                                                <?= date('h:i A', strtotime($exam['end_time'])) ?>
                                            </small>
                                        </td>
                                        <td><?= htmlspecialchars($exam['room_name'] ?? 'TBA') ?></td>
                                        <td><?= htmlspecialchars($exam['supervisor_name'] ?? 'TBA') ?></td>
                                        <td>
                                            <span class="badge <?= $status_class ?>"><?= $status ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="fas fa-calendar-times"></i>
                                            <h3>No Exam Schedules Found</h3>
                                            <p>You don't have any exam schedules for your enrolled courses.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
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

    // Initialize FullCalendar
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('examCalendar');
        
        // Prepare events from PHP data
        const calendarEvents = <?= json_encode(array_map(function($exam) {
            // Color mapping for different exam types
            $colorMap = [
                'Midterm' => '#3b82f6',     // Blue
                'Final' => '#ef4444',       // Red
                'Quiz' => '#10b981',        // Green
                'Practical' => '#f59e0b',   // Orange
                'Project' => '#8b5cf6',     // Purple
                'Assignment' => '#06b6d4',  // Cyan
                'Lab' => '#84cc16',         // Lime
                'Presentation' => '#f97316' // Orange
            ];
            
            // Determine status for styling
            $current_time = time();
            $exam_timestamp = strtotime($exam['exam_date'] . ' ' . $exam['start_time']);
            $is_past = $exam_timestamp < $current_time;
            $is_today = date('Y-m-d', $exam_timestamp) == date('Y-m-d');
            
            // Adjust color for past exams (lighter)
            $baseColor = $colorMap[$exam['exam_type']] ?? '#6b7280';
            if ($is_past) {
                // Make past exams lighter
                $color = lightenColor($baseColor, 40);
            } else if ($is_today) {
                // Make today's exams more vibrant
                $color = $baseColor;
            } else {
                $color = $baseColor;
            }
            
            return [
                'id' => $exam['exam_id'],
                'title' => $exam['course_code'] . ' - ' . $exam['exam_type'],
                'start' => $exam['exam_date'] . 'T' . $exam['start_time'],
                'end' => $exam['exam_date'] . 'T' . $exam['end_time'],
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'course' => $exam['course_name'],
                    'room' => $exam['room_name'] ?? 'Not Assigned',
                    'supervisor' => $exam['supervisor_name'] ?? 'Not Assigned',
                    'type' => $exam['exam_type']
                ]
            ];
        }, $exams)) ?>;
        
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: calendarEvents,
            eventClick: function(info) {
                // Show exam details when clicked
                const course = info.event.extendedProps.course;
                const room = info.event.extendedProps.room;
                const supervisor = info.event.extendedProps.supervisor;
                const type = info.event.extendedProps.type;
                
                const startTime = info.event.start ? info.event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
                const endTime = info.event.end ? info.event.end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
                const date = info.event.start ? info.event.start.toLocaleDateString([], {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'}) : '';
                
                // Create a simple alert (you could use a modal here)
                alert(
                    `📚 ${info.event.title}\n\n` +
                    `📖 Course: ${course}\n` +
                    `📅 Date: ${date}\n` +
                    `⏰ Time: ${startTime} - ${endTime}\n` +
                    `🚪 Room: ${room}\n` +
                    `👨‍🏫 Supervisor: ${supervisor}\n` +
                    `📝 Type: ${type}`
                );
            },
            eventDidMount: function(info) {
                // Add tooltip
                const title = info.event.title;
                const course = info.event.extendedProps.course;
                const room = info.event.extendedProps.room;
                const supervisor = info.event.extendedProps.supervisor;
                
                info.el.title = `${title}\nCourse: ${course}\nRoom: ${room}\nSupervisor: ${supervisor}`;
                
                // Add custom styling
                info.el.style.borderRadius = '6px';
                info.el.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
                info.el.style.padding = '4px 8px';
                info.el.style.fontSize = '0.85rem';
                info.el.style.margin = '2px 0';
            },
            editable: false,
            selectable: false,
            height: 'auto',
            contentHeight: 450,
            dayMaxEvents: 3,
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            },
            buttonText: {
                today: 'Today',
                month: 'Month',
                week: 'Week',
                day: 'Day'
            },
            themeSystem: 'standard',
            dayCellContent: function(e) {
                e.dayNumberText = e.dayNumberText.replace('日', '');
            }
        });
        
        calendar.render();
    });
    
    // Helper function to lighten colors
    function lightenColor(color, percent) {
        const num = parseInt(color.replace("#", ""), 16);
        const amt = Math.round(2.55 * percent);
        const R = (num >> 16) + amt;
        const G = (num >> 8 & 0x00FF) + amt;
        const B = (num & 0x0000FF) + amt;
        
        return "#" + (
            0x1000000 +
            (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 +
            (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 +
            (B < 255 ? B < 1 ? 0 : B : 255)
        ).toString(16).slice(1);
    }
    
    // Auto-close messages after 5 seconds
    setTimeout(function() {
        const messages = document.querySelectorAll('.message');
        messages.forEach(function(message) {
            message.style.opacity = '0';
            message.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                if (message.parentNode) {
                    message.parentNode.removeChild(message);
                }
            }, 500);
        });
    }, 5000);
    </script>
</body>
</html>