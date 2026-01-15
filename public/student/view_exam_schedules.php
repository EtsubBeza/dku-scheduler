<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only allow students
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

$student_id = $_SESSION['user_id'];
$message = "";
$message_type = "success";

// Helper function to display messages
function showMessage($type, $text) {
    global $message, $message_type;
    $message = $text;
    $message_type = $type;
}

// Fetch current user info
try {
    $user_stmt = $pdo->prepare("SELECT username, profile_picture, email, year FROM users WHERE user_id = ?");
    $user_stmt->execute([$_SESSION['user_id']]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: ../index.php");
        exit;
    }
    
    // Get student year from database
    $student_year = $user['year'] ?? '1';
    $student_year_trimmed = trim($student_year);

    // Determine student type and display year
    if (strtoupper(substr($student_year_trimmed, 0, 1)) === 'E') {
        // Extension student: E1, E2, etc.
        $student_type = 'extension';
        $display_year = substr($student_year_trimmed, 1); // Remove 'E' prefix
        $year_for_exam_match = $student_year_trimmed; // E1, E2, etc.
    } elseif (strtolower($student_year_trimmed) === 'freshman') {
        // Regular freshman student
        $student_type = 'regular';
        $display_year = 'Freshman';
        $year_for_exam_match = 'freshman';
    } else {
        // Regular student (year 1, 2, 3, 4, 5)
        $student_type = 'regular';
        $display_year = $student_year_trimmed;
        $year_for_exam_match = $student_year_trimmed;
    }
    
} catch (PDOException $e) {
    showMessage('error', "Error loading user info: " . $e->getMessage());
    $student_type = 'regular';
    $student_year = '1';
    $display_year = '1';
    $year_for_exam_match = '1';
    $user = ['username' => 'Student', 'profile_picture' => '', 'email' => ''];
}

// Determine profile picture path
$default_profile = 'default_profile.png';
$profile_img_path = '../assets/' . $default_profile;

if(!empty($user['profile_picture'])) {
    $possible_paths = [
        '../uploads/' . $user['profile_picture'],
        'uploads/' . $user['profile_picture'],
        '../../uploads/' . $user['profile_picture'],
        '../assets/' . $user['profile_picture']
    ];
    
    foreach($possible_paths as $path) {
        if(file_exists($path)) {
            $profile_img_path = $path;
            break;
        }
    }
}

// Get student's enrolled courses
try {
    $enrolled_courses_stmt = $pdo->prepare("
        SELECT DISTINCT c.course_id, c.course_code, c.course_name
        FROM enrollments e
        JOIN schedule s ON e.schedule_id = s.schedule_id
        JOIN courses c ON s.course_id = c.course_id
        WHERE e.student_id = ?
    ");
    $enrolled_courses_stmt->execute([$student_id]);
    $enrolled_courses = $enrolled_courses_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($enrolled_courses)) {
        showMessage('warning', "You are not enrolled in any courses. Please contact your advisor to get enrolled.");
    }
    
    $enrolled_course_ids = array_column($enrolled_courses, 'course_id');
    
} catch (PDOException $e) {
    showMessage('error', "Error loading enrolled courses: " . $e->getMessage());
    $enrolled_courses = [];
    $enrolled_course_ids = [];
}

// Get exam schedules from exam_schedules table
try {
    if (empty($enrolled_courses)) {
        $exams = [];
        $all_exams = [];
    } else {
        // Get course IDs
        $course_ids = array_column($enrolled_courses, 'course_id');
        $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
        
        // Get ALL published active exams for enrolled courses
        $exams_stmt = $pdo->prepare("
            SELECT DISTINCT
                es.exam_id, 
                es.course_id, 
                es.exam_type, 
                es.exam_date, 
                es.start_time, 
                es.end_time,
                es.room_id, 
                es.supervisor_id, 
                es.max_students, 
                es.is_published, 
                es.academic_year, 
                es.semester,
                es.instructions,
                es.status,
                es.student_type,
                es.year,
                c.course_code, 
                c.course_name,
                r.room_name, 
                r.capacity,
                u.username as supervisor_name,
                u.full_name as supervisor_full_name,
                es.section_number
            FROM exam_schedules es
            JOIN courses c ON es.course_id = c.course_id
            LEFT JOIN rooms r ON es.room_id = r.room_id
            LEFT JOIN users u ON es.supervisor_id = u.user_id
            WHERE es.course_id IN ($placeholders)
            AND es.is_published = 1
            AND es.status = 'active'
            ORDER BY es.exam_date, es.start_time
        ");
        
        $exams_stmt->execute($course_ids);
        $all_exams = $exams_stmt->fetchAll();
        
        // Now filter exams based on student type and year
        $exams = [];
        foreach($all_exams as $exam) {
            $exam_student_type = $exam['student_type'] ?? '';
            $exam_year = $exam['year'] ?? '';
            
            // Check if exam matches student
            $matches = false;
            
            // Case 1: Exam has no student type/year restrictions (shows to everyone)
            if (empty($exam_student_type) && empty($exam_year)) {
                $matches = true;
            }
            // Case 2: Exam for all students of a specific type (all years)
            elseif (!empty($exam_student_type) && empty($exam_year)) {
                if ($exam_student_type === $student_type) {
                    $matches = true;
                }
            }
            // Case 3: Exam for specific type and year
            elseif (!empty($exam_student_type) && !empty($exam_year)) {
                if ($exam_student_type === $student_type && $exam_year == $year_for_exam_match) {
                    $matches = true;
                }
            }
            // Case 4: Special case for 'freshman' students
            elseif ($student_year_trimmed === 'freshman' && $exam_student_type === 'regular' && $exam_year === 'freshman') {
                $matches = true;
            }
            
            if ($matches) {
                $exams[] = $exam;
            }
        }
    }
    
} catch (PDOException $e) {
    showMessage('error', "Error loading exam schedules: " . $e->getMessage());
    $exams = [];
    $all_exams = [];
}

// Fetch exam statistics for student
try {
    if (empty($exams)) {
        $stats = [
            'total_exams' => 0,
            'upcoming_exams' => 0,
            'exam_types_count' => 0
        ];
    } else {
        // Count statistics from filtered exams
        $total_exams = count($exams);
        $upcoming_exams = 0;
        $exam_types = [];
        
        foreach($exams as $exam) {
            // Check if exam is upcoming
            $exam_date = $exam['exam_date'];
            $current_date = date('Y-m-d');
            if ($exam_date >= $current_date) {
                $upcoming_exams++;
            }
            
            // Count unique exam types
            $exam_type = $exam['exam_type'];
            if (!in_array($exam_type, $exam_types)) {
                $exam_types[] = $exam_type;
            }
        }
        
        $stats = [
            'total_exams' => $total_exams,
            'upcoming_exams' => $upcoming_exams,
            'exam_types_count' => count($exam_types)
        ];
    }
} catch (PDOException $e) {
    $stats = [
        'total_exams' => 0,
        'upcoming_exams' => 0,
        'exam_types_count' => 0
    ];
}

// Prepare calendar events data for JavaScript
$calendar_events = [];
foreach($exams as $exam) {
    // Simple color mapping for different exam types
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
    
    // Base color based on exam type
    $baseColor = $colorMap[$exam['exam_type']] ?? '#6b7280';
    
    // Different colors for different student types
    $exam_student_type = $exam['student_type'] ?? 'regular';
    $exam_year = $exam['year'] ?? '';
    
    if ($exam_student_type == 'extension') {
        $baseColor = '#8b5cf6'; // Purple for extension
    } elseif ($exam_year == 'freshman') {
        $baseColor = '#06b6d4'; // Cyan for freshman
    }
    
    $calendar_events[] = [
        'id' => $exam['exam_id'],
        'title' => $exam['course_code'] . ' - ' . $exam['exam_type'],
        'start' => $exam['exam_date'] . 'T' . $exam['start_time'],
        'end' => $exam['exam_date'] . 'T' . $exam['end_time'],
        'backgroundColor' => $baseColor,
        'borderColor' => $baseColor,
        'textColor' => '#ffffff',
        'extendedProps' => [
            'course' => $exam['course_name'],
            'room' => $exam['room_name'] ?? 'Not Assigned',
            'instructor' => $exam['supervisor_full_name'] ?? $exam['supervisor_name'] ?? 'Not Assigned',
            'type' => $exam['exam_type'],
            'student_type' => $exam['student_type'] ?? 'regular',
            'year' => $exam['year'] ?? 'Not specified',
            'academic_year' => $exam['academic_year'],
            'semester' => $exam['semester']
        ]
    ];
}

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exam Schedule | Student Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- FullCalendar CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
<style>
/* ================= CSS VARIABLES ================= */
:root {
    --bg-primary: #f9fafb;
    --bg-secondary: #f3f4f6;
    --bg-card: #ffffff;
    --bg-sidebar: #1f2937;
    --bg-input: #ffffff;
    --text-primary: #111827;
    --text-light: #6b7280;
    --text-sidebar: #e5e7eb;
    --border-color: #d1d5db;
    --shadow-color: rgba(0, 0, 0, 0.1);
    --hover-color: rgba(99, 102, 241, 0.05);
    --table-header: #f9fafb;
    --today-bg: #fffbeb;
    --success-bg: #d1fae5;
    --success-text: #065f46;
    --error-bg: #fee2e2;
    --error-text: #991b1b;
    --warning-bg: #fef3c7;
    --warning-text: #92400e;
    --badge-primary-bg: #dbeafe;
    --badge-primary-text: #1e40af;
    --badge-success-bg: #d1fae5;
    --badge-success-text: #065f46;
    --badge-warning-bg: #fef3c7;
    --badge-warning-text: #92400e;
    --badge-danger-bg: #fee2e2;
    --badge-danger-text: #991b1b;
    --badge-secondary-bg: #f3f4f6;
    --badge-secondary-text: #4b5563;
}

/* Dark mode overrides */
.dark-mode {
    --bg-primary: #111827;
    --bg-secondary: #1f2937;
    --bg-card: #1f2937;
    --bg-sidebar: #111827;
    --bg-input: #374151;
    --text-primary: #f9fafb;
    --text-light: #d1d5db;
    --text-sidebar: #f3f4f6;
    --border-color: #4b5563;
    --shadow-color: rgba(0, 0, 0, 0.3);
    --hover-color: rgba(99, 102, 241, 0.1);
    --table-header: #374151;
    --today-bg: rgba(245, 158, 11, 0.1);
    --success-bg: rgba(16, 185, 129, 0.1);
    --success-text: #10b981;
    --error-bg: rgba(239, 68, 68, 0.1);
    --error-text: #ef4444;
    --warning-bg: rgba(245, 158, 11, 0.1);
    --warning-text: #f59e0b;
    --badge-primary-bg: rgba(59, 130, 246, 0.1);
    --badge-primary-text: #93c5fd;
    --badge-success-bg: rgba(16, 185, 129, 0.1);
    --badge-success-text: #10b981;
    --badge-warning-bg: rgba(245, 158, 11, 0.1);
    --badge-warning-text: #f59e0b;
    --badge-danger-bg: rgba(239, 68, 68, 0.1);
    --badge-danger-text: #ef4444;
    --badge-secondary-bg: rgba(156, 163, 175, 0.1);
    --badge-secondary-text: #9ca3af;
}

/* ================= RESET & BASE STYLES ================= */
* { 
    box-sizing: border-box; 
    margin:0; 
    padding:0; 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
}

body {
    background: var(--bg-primary);
    color: var(--text-primary);
    transition: background 0.3s ease, color 0.3s ease;
    overflow-x: hidden;
}

/* ================= TOPBAR FOR MOBILE ================= */
.topbar {
    display: none;
    position: fixed;
    top:0;
    left:0;
    width:100%;
    background:var(--bg-sidebar);
    color:var(--text-sidebar);
    padding:15px 20px;
    z-index:1200;
    justify-content:space-between;
    align-items:center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.menu-btn {
    font-size:26px;
    background:#1abc9c;
    border:none;
    color:var(--text-sidebar);
    cursor:pointer;
    padding:10px 14px;
    border-radius:8px;
    font-weight:600;
    transition: background 0.3s, transform 0.2s;
}

.menu-btn:hover {
    background:#159b81;
    transform:translateY(-2px);
}

/* ================= SIDEBAR ================= */
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
}

.sidebar.hidden { 
    transform:translateX(-260px); 
}

/* Sidebar Profile */
.sidebar-profile {
    text-align: center;
    padding: 20px;
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

/* Student badge in sidebar */
.student-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-top: 5px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.student-badge.regular {
    background: rgba(59, 130, 246, 0.2);
    color: #93c5fd;
    border-color: rgba(59, 130, 246, 0.3);
}

.student-badge.extension {
    background: rgba(139, 92, 246, 0.2);
    color: #c4b5fd;
    border-color: rgba(139, 92, 246, 0.3);
}

.student-badge.freshman {
    background: rgba(6, 182, 212, 0.2);
    color: #67e8f9;
    border-color: rgba(6, 182, 212, 0.3);
}

.year-badge {
    display: inline-block;
    padding: 2px 8px;
    margin-left: 5px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 600;
}

.year-badge.regular {
    background: #3b82f6;
    color: white;
}

.year-badge.extension {
    background: #8b5cf6;
    color: white;
}

.year-badge.freshman {
    background: #06b6d4;
    color: white;
}

/* Sidebar Title */
.sidebar h2 {
    text-align: center;
    color: var(--text-sidebar);
    padding: 15px 20px;
    font-size: 22px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    flex-shrink: 0;
    margin: 0;
}

/* Scrollable Navigation Links */
.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding-bottom: 20px;
}

/* Style the scrollbar */
.sidebar-nav::-webkit-scrollbar {
    width: 6px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 3px;
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Navigation Links */
.sidebar a { 
    display: block; 
    padding: 12px 20px; 
    color: var(--text-sidebar); 
    text-decoration: none; 
    transition: background 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar a:hover, .sidebar a.active { 
    background: #1abc9c; 
    color: white; 
}

.sidebar a i {
    width: 25px;
    text-align: center;
    margin-right: 10px;
}

/* ================= OVERLAY ================= */
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

/* ================= MAIN CONTENT ================= */
.main-content {
    margin-left: 250px;
    padding:20px;
    min-height:100vh;
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

/* ================= HEADER STYLES ================= */
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

/* Welcome Section */
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

/* Student Type Display in Welcome */
.student-type-display {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-top: 10px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
}

.student-type-display.regular {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    border-color: rgba(59, 130, 246, 0.2);
}

.student-type-display.extension {
    background: rgba(139, 92, 246, 0.1);
    color: #8b5cf6;
    border-color: rgba(139, 92, 246, 0.2);
}

.student-type-display.freshman {
    background: rgba(6, 182, 212, 0.1);
    color: #06b6d4;
    border-color: rgba(6, 182, 212, 0.2);
}

/* Freshman Note */
.freshman-note {
    background: rgba(6, 182, 212, 0.1);
    border: 1px solid rgba(6, 182, 212, 0.2);
    border-radius: 8px;
    padding: 12px 15px;
    margin-top: 10px;
    font-size: 0.85rem;
    color: #06b6d4;
}

.freshman-note i {
    color: #06b6d4;
    margin-right: 8px;
}

/* User Info */
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

/* ================= STATS CARDS ================= */
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
    box-shadow: 0 8px 15px var(--shadow-color);
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
.stat-card .fa-calendar-alt.icon { color: #3b82f6; }
.stat-card .fa-clock.icon { color: #10b981; }
.stat-card .fa-file-alt.icon { color: #f59e0b; }

/* ================= CALENDAR CARD ================= */
.calendar-card {
    background: var(--bg-card);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    margin-top: 30px;
    margin-bottom: 30px;
}

.calendar-header {
    padding: 20px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
}

.calendar-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
}

#examCalendar {
    padding: 20px;
    background: var(--bg-card);
}

/* FullCalendar Custom Styling */
.fc {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.fc-toolbar-title {
    font-size: 1.5rem !important;
    font-weight: 600;
    color: var(--text-primary) !important;
}

.fc-button {
    background: var(--bg-secondary) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--text-primary) !important;
    font-weight: 500 !important;
    border-radius: 8px !important;
    padding: 6px 12px !important;
    transition: all 0.3s ease !important;
}

.fc-button:hover {
    background: var(--hover-color) !important;
    transform: translateY(-1px);
}

.fc-button-primary:not(:disabled):active, 
.fc-button-primary:not(:disabled).fc-button-active {
    background: #1abc9c !important;
    border-color: #1abc9c !important;
    color: white !important;
}

.fc-day-today {
    background-color: var(--today-bg) !important;
}

.fc-col-header-cell {
    background: var(--table-header) !important;
    color: var(--text-sidebar) !important;
}

.fc-col-header-cell-cushion {
    color: var(--text-sidebar) !important;
}

.fc-daygrid-day-number {
    color: var(--text-primary) !important;
}

.fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
    color: var(--text-primary) !important;
    font-weight: bold;
}

.fc-event {
    border-radius: 6px !important;
    border: none !important;
    padding: 4px 8px !important;
    font-size: 0.85rem !important;
    cursor: pointer !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
}

.fc-event:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
}

.fc-scrollgrid {
    border-color: var(--border-color) !important;
}

.fc-scrollgrid td, .fc-scrollgrid th {
    border-color: var(--border-color) !important;
}

/* ================= EXAM TABLE ================= */
.exam-section {
    margin-top: 30px;
}

.table-container {
    background: var(--bg-card);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
}

.exam-table {
    width: 100%;
    border-collapse: collapse;
}

.exam-table th {
    background: var(--table-header);
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: var(--text-sidebar);
    border-bottom: 1px solid var(--border-color);
}

.exam-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
}

.exam-table tr:last-child td {
    border-bottom: none;
}

.exam-table tr:hover {
    background: var(--hover-color);
}

/* Exam row styling */
.exam-table .today-exam {
    background: var(--today-bg) !important;
    border-left: 4px solid #f59e0b;
}

.exam-table .upcoming-exam {
    background: var(--success-bg) !important;
    border-left: 4px solid #10b981;
}

.exam-table .past-exam {
    background: var(--bg-secondary) !important;
    border-left: 4px solid #94a3b8;
}

/* ================= BADGE STYLES ================= */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-primary {
    background: var(--badge-primary-bg);
    color: var(--badge-primary-text);
}

.badge-success {
    background: var(--badge-success-bg);
    color: var(--badge-success-text);
}

.badge-warning {
    background: var(--badge-warning-bg);
    color: var(--badge-warning-text);
}

.badge-danger {
    background: var(--badge-danger-bg);
    color: var(--badge-danger-text);
}

.badge-secondary {
    background: var(--badge-secondary-bg);
    color: var(--badge-secondary-text);
}

/* Student Type Badge in Table */
.student-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 5px;
}

.student-type-badge.regular {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.student-type-badge.extension {
    background: rgba(139, 92, 246, 0.1);
    color: #8b5cf6;
    border: 1px solid rgba(139, 92, 246, 0.2);
}

.student-type-badge.freshman {
    background: rgba(6, 182, 212, 0.1);
    color: #06b6d4;
    border: 1px solid rgba(6, 182, 212, 0.2);
}

/* Year Badge */
.year-badge-table {
    display: inline-block;
    padding: 2px 8px;
    margin-left: 5px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 600;
}

.year-badge-regular {
    background: #3b82f6;
    color: white;
}

.year-badge-extension {
    background: #8b5cf6;
    color: white;
}

.year-badge-freshman {
    background: #06b6d4;
    color: white;
}

.year-badge-all {
    background: #6b7280;
    color: white;
}

/* ================= MESSAGE STYLING ================= */
.message {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-left: 4px solid;
}

.message.success {
    background: var(--success-bg);
    color: var(--success-text);
    border-color: #10b981;
}

.message.error {
    background: var(--error-bg);
    color: var(--error-text);
    border-color: #ef4444;
}

.message.warning {
    background: var(--warning-bg);
    color: var(--warning-text);
    border-color: #f59e0b;
}

/* ================= EMPTY STATE ================= */
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

/* ================= RESPONSIVE ================= */
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
    .exam-table { min-width: 800px; }
    .fc-toolbar {
        flex-direction: column !important;
        gap: 10px !important;
    }
    .fc-toolbar-title {
        font-size: 1.2rem !important;
    }
    #examCalendar {
        padding: 10px;
    }
}

/* ================= CUSTOM SCROLLBAR ================= */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
}

::-webkit-scrollbar-thumb {
    background: #6366f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #4f46e5;
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

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic">
            <p><?= htmlspecialchars($user['username'] ?? 'Student') ?></p>
            <div class="student-badge <?= $student_type ?>">
                <i class="fas fa-<?= $student_type == 'regular' ? 'user-graduate' : ($student_type == 'extension' ? 'user-tie' : 'user') ?>"></i>
                <?= ucfirst($student_type) ?> Student
                <span class="year-badge <?= $student_type ?>">
                    Year <?= htmlspecialchars($display_year) ?>
                </span>
            </div>
        </div>
        
        <h2>Student Dashboard</h2>
        
        <div class="sidebar-nav">
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
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
                <div class="welcome-section">
                    <h1>Exam Schedules</h1>
                    <p>View all your upcoming and past exam schedules</p>
                    <div class="student-type-display <?= $student_type ?>">
                        <i class="fas fa-<?= $student_type == 'regular' ? 'user-graduate' : ($student_type == 'extension' ? 'user-tie' : 'user') ?>"></i>
                        <?= ucfirst($student_type) ?> Student - Year <?= htmlspecialchars($display_year) ?>
                    </div>
                    <?php if($student_year_trimmed === 'freshman'): ?>
                    <div class="freshman-note">
                        <i class="fas fa-info-circle"></i>
                        Note: Showing exams for Freshman students
                    </div>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile" id="headerProfilePic">
                    <div>
                        <div><?= htmlspecialchars($user['username'] ?? 'Student') ?></div>
                        <small>Student</small>
                        <div class="student-badge <?= $student_type ?>" style="margin-top: 5px; font-size: 0.75rem;">
                            <?= ucfirst($student_type) ?> - Year <?= htmlspecialchars($display_year) ?>
                        </div>
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

            <!-- All Exam Schedules Table -->
            <div class="exam-section">
                <h2 style="margin-bottom: 20px; color: var(--text-primary);">All Exam Schedules</h2>
                <div class="table-container">
                    <table class="exam-table">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Exam Type</th>
                                <th>Student Type</th>
                                <th>Date & Time</th>
                                <th>Room</th>
                                <th>Supervisor</th>
                                <th>Academic Year</th>
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
                                    
                                    $exam_student_type = $exam['student_type'] ?? 'Not specified';
                                    $exam_year = $exam['year'] ?? '';
                                    ?>
                                    <tr class="<?= $row_class ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($exam['course_code']) ?></strong><br>
                                            <small style="color: var(--text-secondary);"><?= htmlspecialchars($exam['course_name']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary"><?= htmlspecialchars($exam['exam_type']) ?></span>
                                        </td>
                                        <td>
                                            <?php if($exam_student_type && $exam_student_type !== 'Not specified'): ?>
                                                <span class="student-type-badge <?= $exam_student_type ?>">
                                                    <?= ucfirst($exam_student_type) ?>
                                                    <?php if($exam_year): ?>
                                                        <span class="year-badge-table <?= $exam_student_type ?>">
                                                            Year <?= htmlspecialchars($exam_year) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">All Students</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= date('M d, Y', strtotime($exam['exam_date'])) ?><br>
                                            <small style="color: var(--text-secondary);">
                                                <?= date('h:i A', strtotime($exam['start_time'])) ?> - 
                                                <?= date('h:i A', strtotime($exam['end_time'])) ?>
                                            </small>
                                        </td>
                                        <td><?= htmlspecialchars($exam['room_name'] ?? 'TBA') ?></td>
                                        <td><?= htmlspecialchars($exam['supervisor_full_name'] ?? $exam['supervisor_name'] ?? 'Not Assigned') ?></td>
                                        <td>
                                            <?= htmlspecialchars($exam['academic_year'] ?? 'N/A') ?><br>
                                            <small style="color: var(--text-secondary);"><?= htmlspecialchars($exam['semester'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?= $status_class ?>"><?= $status ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <i class="fas fa-calendar-times"></i>
                                            <h3>No Exam Schedules Found</h3>
                                            <?php if(empty($enrolled_courses)): ?>
                                                <p>You are not enrolled in any courses. Please contact your advisor to get enrolled in courses first.</p>
                                            <?php else: ?>
                                                <p>No exam schedules found for your enrolled courses.</p>
                                                <p style="margin-top: 10px; font-size: 0.9rem;">
                                                    <i class="fas fa-info-circle"></i> Showing exams for <?= ucfirst($student_type) ?> Year <?= htmlspecialchars($display_year) ?> students.
                                                </p>
                                            <?php endif; ?>
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

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize FullCalendar
        const calendarEl = document.getElementById('examCalendar');
        
        // Prepare events from PHP data
        const calendarEvents = <?= json_encode($calendar_events) ?>;
        
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
                const instructor = info.event.extendedProps.instructor;
                const type = info.event.extendedProps.type;
                const studentType = info.event.extendedProps.student_type;
                const year = info.event.extendedProps.year;
                const academicYear = info.event.extendedProps.academic_year;
                const semester = info.event.extendedProps.semester;
                
                const startTime = info.event.start ? info.event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
                const endTime = info.event.end ? info.event.end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
                const date = info.event.start ? info.event.start.toLocaleDateString([], {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'}) : '';
                
                let studentTypeInfo = '';
                if (studentType && studentType !== 'Not specified') {
                    studentTypeInfo = `👨‍🎓 Student Type: ${studentType} ${year ? 'Year ' + year : ''}\n`;
                }
                
                alert(
                    `📚 ${info.event.title}\n\n` +
                    `📖 Course: ${course}\n` +
                    studentTypeInfo +
                    `📅 Academic Year: ${academicYear} - ${semester}\n` +
                    `📅 Date: ${date}\n` +
                    `⏰ Time: ${startTime} - ${endTime}\n` +
                    `🚪 Room: ${room}\n` +
                    `👨‍🏫 Supervisor: ${instructor}\n` +
                    `📝 Type: ${type}`
                );
            },
            eventDidMount: function(info) {
                // Add tooltip
                const title = info.event.title;
                const course = info.event.extendedProps.course;
                const room = info.event.extendedProps.room;
                const instructor = info.event.extendedProps.instructor;
                const studentType = info.event.extendedProps.student_type;
                const year = info.event.extendedProps.year;
                
                let studentTypeInfo = '';
                if (studentType && studentType !== 'Not specified') {
                    studentTypeInfo = `Student Type: ${studentType} ${year ? 'Year ' + year : ''}\n`;
                }
                
                info.el.title = `${title}\nCourse: ${course}\n${studentTypeInfo}Room: ${room}\nSupervisor: ${instructor}`;
                
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
            contentHeight: 500,
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
            themeSystem: 'standard'
        });
        
        calendar.render();
        
        // Set active state for current page
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
    document.querySelector('a[href="../logout.php"]')?.addEventListener('click', function(e) {
        if(!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
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
    
    // Profile picture fallback
    document.querySelectorAll('img[alt="Profile Picture"], img[alt="Profile"]').forEach(img => {
        img.onerror = function() {
            this.src = '../assets/default_profile.png';
        };
    });
    </script>
</body>
</html>