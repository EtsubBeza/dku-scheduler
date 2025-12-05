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

// Fetch current user info
$user_stmt = $pdo->prepare("SELECT username, full_name, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Determine profile picture path
$profile_path = '../../uploads/profiles/' . ($user['profile_picture'] ?? '');
if (!empty($user['profile_picture']) && file_exists($profile_path)) {
    $profile_src = $profile_path;
} else {
    $profile_src = '../assets/default_profile.png';
}

$current_page = basename($_SERVER['PHP_SELF']);

// Helper function to display messages
function showMessage($type, $text) {
    global $message, $message_type;
    $message = $text;
    $message_type = $type;
}

// FIXED: Get exam schedules for enrolled courses only - USING DISTINCT
try {
    $exams_stmt = $pdo->prepare("
        SELECT DISTINCT es.exam_id, es.course_id, es.exam_type, es.exam_date, es.start_time, es.end_time,
               es.room_id, es.supervisor_id, es.max_students, es.is_published,
               c.course_code, c.course_name,
               r.room_name, r.capacity,
               u.full_name as supervisor_name
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

// FIXED: Fetch exam statistics for student - USING DISTINCT
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

// FIXED: Fetch upcoming exams (next 7 days) - USING DISTINCT
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

// Set default academic year
$default_year = date('Y') . '-' . (date('Y') + 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Exam Schedules | Student Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- FullCalendar CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
<style>
:root {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --secondary: #8b5cf6;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    --light: #f8fafc;
    --dark: #1f2937;
    --gray: #6b7280;
    --gray-light: #e5e7eb;
    --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --radius: 12px;
    --radius-lg: 20px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
    min-height: 100vh;
    color: #374151;
    line-height: 1.6;
}

/* ================= Topbar ================= */
.topbar {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: white;
    padding: 1rem 1.5rem;
    box-shadow: var(--shadow);
    z-index: 1000;
    align-items: center;
    justify-content: space-between;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.menu-btn {
    background: var(--primary);
    color: white;
    border: none;
    width: 48px;
    height: 48px;
    border-radius: var(--radius);
    font-size: 1.5rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
}

.menu-btn:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.topbar h2 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* ================= Sidebar ================= */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 280px;
    background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
    color: white;
    z-index: 1000;
    transition: var(--transition);
    overflow-y: auto;
    box-shadow: var(--shadow-lg);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar.hidden {
    transform: translateX(-100%);
}

.sidebar-profile {
    padding: 2.5rem 1.5rem 1.5rem;
    text-align: center;
    background: linear-gradient(180deg, rgba(99, 102, 241, 0.1) 0%, transparent 100%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-profile img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--primary);
    box-shadow: 0 0 20px rgba(99, 102, 241, 0.3);
    margin-bottom: 1rem;
    transition: var(--transition);
}

.sidebar-profile img:hover {
    transform: scale(1.05);
}

.sidebar-profile p {
    font-size: 1.1rem;
    font-weight: 600;
    color: white;
    margin: 0;
}

.sidebar nav {
    padding: 1rem 0;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: var(--transition);
    border-left: 3px solid transparent;
    font-weight: 500;
}

.sidebar a i {
    width: 20px;
    font-size: 1.1rem;
}

.sidebar a:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: var(--primary);
}

.sidebar a.active {
    background: linear-gradient(90deg, rgba(99, 102, 241, 0.2) 0%, transparent 100%);
    color: white;
    border-left-color: var(--primary);
}

/* ================= Overlay ================= */
.overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    display: none;
    backdrop-filter: blur(4px);
}

.overlay.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* ================= Main Content ================= */
.main-content {
    margin-left: 280px;
    padding: 2rem;
    min-height: 100vh;
    transition: var(--transition);
}

@media (max-width: 1024px) {
    .main-content {
        margin-left: 0;
        padding-top: 80px;
    }
}

/* ================= Header ================= */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2.5rem;
    padding: 1.5rem 0;
}

.header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    line-height: 1.2;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: white;
    padding: 0.75rem 1.25rem;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    transition: var(--transition);
}

.user-info:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.user-info img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--primary);
}

.user-info div {
    display: flex;
    flex-direction: column;
}

.user-info div div {
    font-weight: 600;
    color: var(--dark);
}

.user-info small {
    color: var(--gray);
    font-size: 0.875rem;
}

/* ================= Message ================= */
.message {
    padding: 1.25rem 1.5rem;
    border-radius: var(--radius);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    font-weight: 500;
    animation: slideIn 0.3s ease;
    box-shadow: var(--shadow);
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
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    color: #166534;
    border-left: 4px solid var(--success);
}

.message.error {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
    border-left: 4px solid var(--danger);
}

.message.warning {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    border-left: 4px solid var(--warning);
}

.message.info {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #1e40af;
    border-left: 4px solid var(--info);
}

/* ================= Stats Cards ================= */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

.stat-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.75rem;
    box-shadow: var(--shadow);
    transition: var(--transition);
    border: 1px solid var(--gray-light);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.stat-content h3 {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
}

.stat-content .stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark);
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-content .stat-desc {
    font-size: 0.875rem;
    color: var(--gray);
}

/* ================= Upcoming Exams ================= */
.upcoming-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.75rem;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
    border: 1px solid var(--gray-light);
}

.upcoming-card .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.upcoming-card h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.upcoming-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.upcoming-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    background: var(--light);
    border-radius: var(--radius);
    border: 1px solid var(--gray-light);
    transition: var(--transition);
}

.upcoming-item:hover {
    transform: translateX(4px);
    box-shadow: var(--shadow);
}

.upcoming-info h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.upcoming-info p {
    color: var(--gray);
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.upcoming-time {
    text-align: right;
}

.upcoming-time .date {
    font-weight: 600;
    color: var(--dark);
    display: block;
    margin-bottom: 0.25rem;
}

.upcoming-time .time {
    color: var(--gray);
    font-size: 0.875rem;
}

/* ================= Calendar Card ================= */
.calendar-card {
    background: white;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
    border: 1px solid var(--gray-light);
}

.calendar-header {
    padding: 1.5rem 1.75rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.calendar-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

#examCalendar {
    padding: 1.5rem;
    background: white;
}

/* ================= Table Card ================= */
.table-card {
    background: white;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-light);
}

.table-card .card-header {
    padding: 1.5rem 1.75rem;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-bottom: 1px solid var(--gray-light);
}

.table-card h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.table-container {
    overflow-x: auto;
    padding: 0.5rem;
}

.exam-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

.exam-table thead {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
}

.exam-table th {
    padding: 1rem 1.25rem;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 2px solid var(--gray-light);
}

.exam-table td {
    padding: 1.25rem;
    border-bottom: 1px solid var(--gray-light);
    transition: var(--transition);
}

.exam-table tbody tr {
    transition: var(--transition);
}

.exam-table tbody tr:hover {
    background: linear-gradient(90deg, rgba(99, 102, 241, 0.05) 0%, transparent 100%);
    transform: translateX(4px);
}

.exam-table tbody tr:last-child td {
    border-bottom: none;
}

/* ================= Badges ================= */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.875rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
}

.badge-primary {
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    color: var(--primary-dark);
}

.badge-success {
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    color: #166534;
}

.badge-warning {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
}

.badge-danger {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
}

.badge-info {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #1e40af;
}

.badge-secondary {
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    color: var(--gray);
}

/* ================= Buttons ================= */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #059669);
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning), #d97706);
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4);
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger), #dc2626);
    color: white;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
}

.btn-group {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* ================= Progress Bar ================= */
.progress-container {
    width: 100px;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: var(--gray-light);
    border-radius: 4px;
    overflow: hidden;
    margin-top: 0.5rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #059669);
    border-radius: 4px;
    transition: width 0.6s ease;
}

/* ================= Empty State ================= */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--gray);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    color: var(--gray-light);
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.75rem;
}

.empty-state p {
    color: var(--gray);
    max-width: 400px;
    margin: 0 auto;
}

/* ================= Registration Status ================= */
.registration-status {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.registered {
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    color: #166534;
}

.not-registered {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
}

/* ================= Responsive Design ================= */
@media (max-width: 768px) {
    .topbar {
        display: flex;
    }
    
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .header h1 {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .btn-group {
        flex-wrap: wrap;
    }
    
    .exam-table {
        min-width: 600px;
    }
    
    .upcoming-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .upcoming-time {
        text-align: left;
        width: 100%;
    }
}

/* ================= Custom Scrollbar ================= */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--gray-light);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, var(--primary-dark), var(--secondary));
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h2>My Exam Schedules</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile Picture">
            <p><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Student') ?></p>
        </div>
        <nav>
            <a href="student_dashboard.php" class="<?= $current_page=='student_dashboard.php'?'active':'' ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="my_courses.php" class="<?= $current_page=='my_courses.php'?'active':'' ?>">
                <i class="fas fa-book"></i> My Courses
            </a>
            <a href="view_exam_schedules.php" class="<?= $current_page=='view_exam_schedules.php'?'active':'' ?>">
                <i class="fas fa-clipboard-list"></i> Exam Schedules
            </a>
            <a href="my_grades.php" class="<?= $current_page=='my_grades.php'?'active':'' ?>">
                <i class="fas fa-chart-line"></i> My Grades
            </a>
            <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">
                <i class="fas fa-user-edit"></i> Edit Profile
            </a>
            <a href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>My Exam Schedules</h1>
                <p style="color: var(--gray); margin-top: 0.5rem;">View exam schedules for your enrolled courses</p>
            </div>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile">
                <div>
                    <div><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Student') ?></div>
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

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="stat-content">
                    <h3>Total Exams</h3>
                    <div class="stat-value"><?= htmlspecialchars($stats['total_exams']) ?></div>
                    <p class="stat-desc">For your enrolled courses</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), #059669);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
                <div class="stat-content">
                    <h3>Upcoming Exams</h3>
                    <div class="stat-value"><?= htmlspecialchars($stats['upcoming_exams']) ?></div>
                    <p class="stat-desc">Future scheduled exams</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--info), #0284c7);">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div class="stat-content">
                    <h3>Exam Types</h3>
                    <div class="stat-value"><?= htmlspecialchars($stats['exam_types_count']) ?></div>
                    <p class="stat-desc">Different exam formats</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--secondary), #7c3aed);">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                </div>
                <div class="stat-content">
                    <h3>Enrolled Courses</h3>
                    <div class="stat-value">
                        <?php 
                        // Get enrolled courses count
                        try {
                            $courses_stmt = $pdo->prepare("SELECT COUNT(DISTINCT course_id) as count FROM enrollments WHERE student_id = ?");
                            $courses_stmt->execute([$student_id]);
                            $courses_count = $courses_stmt->fetch();
                            echo htmlspecialchars($courses_count['count'] ?? 0);
                        } catch (PDOException $e) {
                            echo "0";
                        }
                        ?>
                    </div>
                    <p class="stat-desc">Courses you're enrolled in</p>
                </div>
            </div>
        </div>

        <!-- Upcoming Exams -->
        <?php if(!empty($upcoming_exams)): ?>
        <div class="upcoming-card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Upcoming Exams (Next 7 Days)</h3>
            </div>
            <div class="upcoming-list">
                <?php foreach($upcoming_exams as $exam): ?>
                <div class="upcoming-item">
                    <div class="upcoming-info">
                        <h4><?= htmlspecialchars($exam['course_code']) ?> - <?= htmlspecialchars($exam['exam_type']) ?></h4>
                        <p>
                            <i class="fas fa-book"></i> <?= htmlspecialchars($exam['course_name']) ?>
                            <?php if(!empty($exam['room_name'])): ?>
                                <span style="margin: 0 8px;">•</span>
                                <i class="fas fa-door-open"></i> <?= htmlspecialchars($exam['room_name']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="upcoming-time">
                        <span class="date"><?= date('M d, Y', strtotime($exam['exam_date'])) ?></span>
                        <span class="time"><?= date('h:i A', strtotime($exam['start_time'])) ?> - <?= date('h:i A', strtotime($exam['end_time'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Calendar View -->
        <div class="calendar-card">
            <div class="calendar-header">
                <h3><i class="fas fa-calendar"></i> Exam Calendar View</h3>
            </div>
            <div id="examCalendar"></div>
        </div>

        <!-- Exam Schedule Table -->
        <div class="table-card">
            <div class="card-header">
                <h3><i class="fas fa-table"></i> All Exam Schedules</h3>
            </div>
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
                                $is_past = $exam_timestamp < $current_time;
                                $is_upcoming = $exam_timestamp > $current_time;
                                ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--dark); display: block; margin-bottom: 0.25rem;">
                                            <?= htmlspecialchars($exam['course_code']) ?>
                                        </strong>
                                        <small style="color: var(--gray);"><?= htmlspecialchars($exam['course_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?= htmlspecialchars($exam['exam_type']) ?></span>
                                    </td>
                                    <td>
                                        <strong style="color: var(--dark); display: block; margin-bottom: 0.25rem;">
                                            <?= date('M d, Y', strtotime($exam['exam_date'])) ?>
                                        </strong>
                                        <small style="color: var(--gray);">
                                            <?= date('h:i A', strtotime($exam['start_time'])) ?> - <?= date('h:i A', strtotime($exam['end_time'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if(!empty($exam['room_name'])): ?>
                                            <strong style="color: var(--dark); display: block; margin-bottom: 0.25rem;">
                                                <?= htmlspecialchars($exam['room_name']) ?>
                                            </strong>
                                            <small style="color: var(--gray);">Capacity: <?= htmlspecialchars($exam['capacity'] ?? 'N/A') ?></small>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if(!empty($exam['supervisor_name'])): ?>
                                            <div style="color: var(--dark); font-weight: 500;">
                                                <?= htmlspecialchars($exam['supervisor_name']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($is_past): ?>
                                            <span class="badge badge-secondary">Completed</span>
                                        <?php elseif($is_upcoming): ?>
                                            <span class="badge badge-success">Upcoming</span>
                                        <?php else: ?>
                                            <span class="badge badge-primary">Today</span>
                                        <?php endif; ?>
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

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script>
    // Sidebar Toggle
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');
        const mainContent = document.querySelector('.main-content');
        
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        
        if(window.innerWidth <= 1024) {
            mainContent.style.marginLeft = sidebar.classList.contains('active') ? '280px' : '0';
        }
    }

    // Initialize FullCalendar
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('examCalendar');
        
        // Prepare events from PHP data
        const calendarEvents = <?= json_encode(array_map(function($exam) {
            $colorMap = [
                'Midterm' => '#6366f1',
                'Final' => '#ef4444',
                'Quiz' => '#10b981',
                'Practical' => '#f59e0b',
                'Project Defense' => '#8b5cf6'
            ];
            
            return [
                'id' => $exam['exam_id'],
                'title' => $exam['course_code'] . ' - ' . $exam['exam_type'],
                'start' => $exam['exam_date'] . 'T' . $exam['start_time'],
                'end' => $exam['exam_date'] . 'T' . $exam['end_time'],
                'backgroundColor' => $colorMap[$exam['exam_type']] ?? '#6b7280',
                'borderColor' => $colorMap[$exam['exam_type']] ?? '#6b7280',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'course' => $exam['course_name'],
                    'room' => $exam['room_name'] ?? 'Not Assigned',
                    'supervisor' => $exam['supervisor_name'] ?? 'Not Assigned'
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
                
                const startTime = info.event.start ? info.event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
                const endTime = info.event.end ? info.event.end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
                
                Swal.fire({
                    title: info.event.title,
                    html: `
                        <div style="text-align: left;">
                            <p><strong>Course:</strong> ${course}</p>
                            <p><strong>Room:</strong> ${room}</p>
                            <p><strong>Supervisor:</strong> ${supervisor}</p>
                            <p><strong>Time:</strong> ${startTime} - ${endTime}</p>
                        </div>
                    `,
                    icon: 'info',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#6366f1'
                });
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
                info.el.style.boxShadow = '0 2px 6px rgba(0,0,0,0.1)';
                info.el.style.padding = '4px 8px';
                info.el.style.fontSize = '0.85rem';
            },
            editable: false,
            selectable: false,
            height: 'auto',
            contentHeight: 500,
            dayMaxEvents: 3,
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                meridiem: 'short'
            },
            themeSystem: 'bootstrap5',
            buttonText: {
                today: 'Today',
                month: 'Month',
                week: 'Week',
                day: 'Day'
            }
        });
        
        calendar.render();
    });
    
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
    
    // Add animation to table rows on page load
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.querySelectorAll('.exam-table tbody tr');
        rows.forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                row.style.transition = 'all 0.5s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateX(0)';
            }, index * 50);
        });
        
        // Add animation to upcoming items
        const upcomingItems = document.querySelectorAll('.upcoming-item');
        upcomingItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(10px)';
            setTimeout(() => {
                item.style.transition = 'all 0.5s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
</script>
<!-- SweetAlert2 for better alerts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>