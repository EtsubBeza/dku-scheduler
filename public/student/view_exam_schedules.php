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
    
    // Get student year from database and normalize case
    $student_year = $user['year'] ?? '1';
    $student_year_normalized = strtolower(trim($student_year)); // Normalize to lowercase

    error_log("DEBUG: Student ID: $student_id, Year from DB: $student_year, Normalized: $student_year_normalized");

    // Determine student type and display year
    if (substr($student_year_normalized, 0, 1) === 'e') {
        // Extension student: E1, E2, etc. (case insensitive)
        $student_type = 'extension';
        $display_year = substr($student_year, 1); // Remove 'E' prefix (original case)
        $year_for_exam_match = $student_year_normalized; // e1, e2, etc. (lowercase)
    } elseif ($student_year_normalized === 'freshman') {
        // Regular freshman student
        $student_type = 'regular';
        $display_year = 'Freshman'; // Show capital F for display
        $year_for_exam_match = 'freshman'; // Use lowercase for matching exams
    } else {
        // Regular student (year 1, 2, 3, 4)
        $student_type = 'regular';
        $display_year = $student_year;
        $year_for_exam_match = $student_year; // 1, 2, 3, 4
    }
    
    error_log("DEBUG: Student Type: $student_type, Display Year: $display_year, Year for Exam Match: $year_for_exam_match");
    
} catch (PDOException $e) {
    showMessage('error', "Error loading user info: " . $e->getMessage());
    error_log("User info error: " . $e->getMessage());
    $student_type = 'regular';
    $student_year = '1';
    $display_year = '1';
    $year_for_exam_match = '1';
    $user = ['username' => 'Student', 'profile_picture' => '', 'email' => ''];
}

// Determine profile picture path
$uploads_dir = __DIR__ . '/../uploads/';
$assets_dir = __DIR__ . '/../assets/';

// Check if profile picture exists in uploads directory
$profile_picture = $user['profile_picture'] ?? '';
$default_profile = 'default_profile.png';

// Check multiple possible locations
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

// Get student's current academic year from enrollments
try {
    $academic_info_stmt = $pdo->prepare("
        SELECT DISTINCT s.academic_year 
        FROM enrollments e
        JOIN schedule s ON e.schedule_id = s.schedule_id
        WHERE e.student_id = ?
        LIMIT 1
    ");
    $academic_info_stmt->execute([$student_id]);
    $academic_info = $academic_info_stmt->fetch();
    
    $academic_year = $academic_info['academic_year'] ?? '2026-2027';
    
    // Determine semester based on current month
    // Assume: 1st Semester: August-January, 2nd Semester: February-July
    $current_month = date('n');
    
    if ($current_month >= 8 || $current_month <= 1) {
        $semester = '1st Semester';
    } else {
        $semester = '2nd Semester';
    }
    
    error_log("DEBUG: Academic Year: $academic_year, Current Month: $current_month, Semester: $semester");
    
} catch (PDOException $e) {
    $academic_year = '2026-2027';
    $semester = '1st Semester';
    error_log("Academic info error: " . $e->getMessage());
}

// Get all courses the student is enrolled in
try {
    // First, let's check if student exists in enrollments at all
    $check_enrollment_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ?");
    $check_enrollment_stmt->execute([$student_id]);
    $enrollment_count = $check_enrollment_stmt->fetch()['count'];
    
    if ($enrollment_count > 0) {
        // Student has enrollments, get the course IDs
        $enrolled_courses_stmt = $pdo->prepare("
            SELECT DISTINCT s.course_id 
            FROM enrollments e
            JOIN schedule s ON e.schedule_id = s.schedule_id
            WHERE e.student_id = ?
        ");
        $enrolled_courses_stmt->execute([$student_id]);
        $enrolled_course_ids = $enrolled_courses_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } else {
        // Student has no enrollments - show a helpful message
        $enrolled_course_ids = [];
        showMessage('warning', "You are not enrolled in any courses. Please contact your advisor to get enrolled.");
    }
    
    error_log("DEBUG: Enrollment count: $enrollment_count, Enrolled Course IDs: " . implode(', ', $enrolled_course_ids));
    
} catch (PDOException $e) {
    showMessage('error', "Error loading enrolled courses: " . $e->getMessage());
    error_log("Enrolled courses error: " . $e->getMessage());
    $enrolled_course_ids = [];
}

// Get exam schedules from exam_schedules table
try {
    if (empty($enrolled_course_ids)) {
        $exams = [];
    } else {
        $placeholders = str_repeat('?,', count($enrolled_course_ids) - 1) . '?';
        
        // FIXED QUERY - Handle both 'freshman' and '1' for freshman students
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
                u.username as supervisor_name
            FROM exam_schedules es
            JOIN courses c ON es.course_id = c.course_id
            LEFT JOIN rooms r ON es.room_id = r.room_id
            LEFT JOIN users u ON es.supervisor_id = u.user_id
            WHERE es.course_id IN ($placeholders)
            AND es.is_published = 1
            AND (es.academic_year = ? OR es.academic_year IS NULL OR es.academic_year = '')
            AND (
                es.semester IS NULL OR 
                es.semester = '' OR
                es.semester = ?
            )
            AND (
                -- Match student_type exactly OR exam has no student_type restriction
                es.student_type IS NULL OR 
                es.student_type = '' OR
                LOWER(TRIM(es.student_type)) = LOWER(?)
            )
            AND (
                -- For freshman students, check both 'freshman' and '1'
                es.year IS NULL OR 
                es.year = '' OR
                LOWER(TRIM(es.year)) = LOWER(?) OR
                (LOWER(TRIM(?)) = 'freshman' AND (LOWER(TRIM(es.year)) = '1' OR es.year = '1'))
            )
            ORDER BY es.exam_date, es.start_time
        ");
        
        $params = array_merge($enrolled_course_ids, [
            $academic_year, 
            $semester,  // Only 1st Semester or 2nd Semester
            $student_type,
            $year_for_exam_match,
            $year_for_exam_match  // Extra parameter for freshman/1 check
        ]);
        
        $exams_stmt->execute($params);
        $exams = $exams_stmt->fetchAll();
        
        // Add section_number for consistency (not in exam_schedules table)
        foreach($exams as &$exam) {
            $exam['section_number'] = 'N/A';
            $exam['instructor_name'] = $exam['supervisor_name'];
        }
    }
    
    error_log("DEBUG: Found " . count($exams) . " exams for student");
    error_log("DEBUG: Query params - Academic Year: $academic_year, Semester: $semester, Student Type: $student_type, Year for Exam Match: $year_for_exam_match");
    
    // Debug: Check what exams exist for freshman
    if ($student_year_normalized === 'freshman' && empty($exams)) {
        $debug_stmt = $pdo->prepare("
            SELECT es.year, es.student_type, es.semester, es.academic_year, c.course_code, es.exam_type
            FROM exam_schedules es
            JOIN courses c ON es.course_id = c.course_id
            WHERE es.course_id IN (SELECT DISTINCT course_id FROM schedule WHERE schedule_id IN (SELECT schedule_id FROM enrollments WHERE student_id = ?))
            AND es.is_published = 1
            LIMIT 10
        ");
        $debug_stmt->execute([$student_id]);
        $debug_exams = $debug_stmt->fetchAll();
        error_log("DEBUG: Available exams for student courses: " . json_encode($debug_exams));
    }
    
} catch (PDOException $e) {
    showMessage('error', "Error loading exam schedules: " . $e->getMessage());
    error_log("Exam schedule error: " . $e->getMessage());
    $exams = [];
}

// Fetch exam statistics for student
try {
    if (empty($enrolled_course_ids)) {
        $stats = [
            'total_exams' => 0,
            'upcoming_exams' => 0,
            'exam_types_count' => 0
        ];
    } else {
        $placeholders = str_repeat('?,', count($enrolled_course_ids) - 1) . '?';
        
        $stats_stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT es.exam_id) as total_exams,
                SUM(CASE WHEN es.exam_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_exams,
                COUNT(DISTINCT es.exam_type) as exam_types_count
            FROM exam_schedules es
            WHERE es.course_id IN ($placeholders)
            AND es.is_published = 1
            AND (es.academic_year = ? OR es.academic_year IS NULL OR es.academic_year = '')
            AND (
                es.semester IS NULL OR 
                es.semester = '' OR
                es.semester = ?
            )
            AND (
                es.student_type IS NULL OR 
                es.student_type = '' OR
                LOWER(TRIM(es.student_type)) = LOWER(?)
            )
            AND (
                -- For freshman students, check both 'freshman' and '1'
                es.year IS NULL OR 
                es.year = '' OR
                LOWER(TRIM(es.year)) = LOWER(?) OR
                (LOWER(TRIM(?)) = 'freshman' AND (LOWER(TRIM(es.year)) = '1' OR es.year = '1'))
            )
        ");
        
        $params = array_merge($enrolled_course_ids, [
            $academic_year, 
            $semester,
            $student_type,
            $year_for_exam_match,
            $year_for_exam_match  // Extra parameter for freshman/1 check
        ]);
        
        $stats_stmt->execute($params);
        $stats = $stats_stmt->fetch();
        
        if (!$stats) {
            $stats = [
                'total_exams' => 0,
                'upcoming_exams' => 0,
                'exam_types_count' => 0
            ];
        }
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
    if (empty($enrolled_course_ids)) {
        $upcoming_exams = [];
    } else {
        $placeholders = str_repeat('?,', count($enrolled_course_ids) - 1) . '?';
        
        $upcoming_stmt = $pdo->prepare("
            SELECT DISTINCT es.exam_id, es.course_id, es.exam_type, es.exam_date, es.start_time, es.end_time,
                   c.course_code, c.course_name, r.room_name, es.student_type, es.year,
                   'N/A' as section_number
            FROM exam_schedules es
            JOIN courses c ON es.course_id = c.course_id
            LEFT JOIN rooms r ON es.room_id = r.room_id
            WHERE es.course_id IN ($placeholders)
            AND es.is_published = 1
            AND (es.academic_year = ? OR es.academic_year IS NULL OR es.academic_year = '')
            AND (
                es.semester IS NULL OR 
                es.semester = '' OR
                es.semester = ?
            )
            AND es.exam_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND (
                es.student_type IS NULL OR 
                es.student_type = '' OR
                LOWER(TRIM(es.student_type)) = LOWER(?)
            )
            AND (
                -- For freshman students, check both 'freshman' and '1'
                es.year IS NULL OR 
                es.year = '' OR
                LOWER(TRIM(es.year)) = LOWER(?) OR
                (LOWER(TRIM(?)) = 'freshman' AND (LOWER(TRIM(es.year)) = '1' OR es.year = '1'))
            )
            ORDER BY es.exam_date, es.start_time
            LIMIT 5
        ");
        
        $params = array_merge($enrolled_course_ids, [
            $academic_year, 
            $semester,
            $student_type,
            $year_for_exam_match,
            $year_for_exam_match  // Extra parameter for freshman/1 check
        ]);
        
        $upcoming_stmt->execute($params);
        $upcoming_exams = $upcoming_stmt->fetchAll();
    }
    
    if (!$upcoming_exams) {
        $upcoming_exams = [];
    }
} catch (PDOException $e) {
    $upcoming_exams = [];
    error_log("Upcoming exams error: " . $e->getMessage());
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
    if ($exam_student_type == 'extension') {
        $baseColor = '#8b5cf6'; // Purple for extension
    } elseif (strtolower($exam['year'] ?? '') == 'freshman' || $exam['year'] == '1') {
        $baseColor = '#06b6d4'; // Cyan for freshman
    }
    
    // Check if exam is past or today
    $current_time = time();
    $exam_timestamp = strtotime($exam['exam_date'] . ' ' . $exam['start_time']);
    $is_past = $exam_timestamp < $current_time;
    $is_today = date('Y-m-d', $exam_timestamp) == date('Y-m-d');
    
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
            'instructor' => $exam['supervisor_name'] ?? 'Not Assigned',
            'type' => $exam['exam_type'],
            'section' => 'N/A',
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
<title>Exam Schedule | Student Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- FullCalendar CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
<!-- Include Dark Mode CSS -->
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
/* [Keep all the same CSS styles from previous version] */
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
    color: white;
    border-radius: 15px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-left: 10px;
}

.student-year-badge.regular { background: #3b82f6; }
.student-year-badge.extension { background: #8b5cf6; }
.student-year-badge.freshman { background: #06b6d4; }

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

/* Icon colors */
.stat-card .fa-calendar-alt.icon { color: #3b82f6; }
.stat-card .fa-clock.icon { color: #10b981; }
.stat-card .fa-file-alt.icon { color: #f59e0b; }

/* ================= Calendar Card ================= */
.calendar-card {
    background: var(--bg-card);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    margin-top: 30px;
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

/* FullCalendar Custom Styling - Dark Mode Support */
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

/* ================= Exam Table ================= */
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

/* Badge styles for dark mode */
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

/* Message styling for dark mode */
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
    border-color: var(--success-border);
}

.message.error {
    background: var(--error-bg);
    color: var(--error-text);
    border-color: var(--error-border);
}

.message.warning {
    background: var(--warning-bg);
    color: var(--warning-text);
    border-color: var(--warning-border);
}

/* Empty state for dark mode */
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

/* Student info alert */
.student-info-alert {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.student-info-alert i {
    font-size: 1.2rem;
    color: #f59e0b;
}

.student-info-alert div {
    flex: 1;
}

.student-info-alert h4 {
    margin: 0 0 5px 0;
    color: var(--text-primary);
    font-size: 1rem;
}

.student-info-alert p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

/* Freshman info note */
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

/* Dark mode specific adjustments */
[data-theme="dark"] .stat-card .number {
    color: var(--text-primary);
}

[data-theme="dark"] .exam-table th {
    color: var(--text-sidebar);
}

[data-theme="dark"] .exam-table td {
    color: var(--text-primary);
}

[data-theme="dark"] .exam-table tr:hover {
    background: rgba(255, 255, 255, 0.05);
}

/* Calendar dark mode fixes */
[data-theme="dark"] .fc .fc-daygrid-day.fc-day-today {
    background-color: rgba(245, 158, 11, 0.1) !important;
}

[data-theme="dark"] .fc-theme-standard .fc-scrollgrid {
    border-color: var(--border-color) !important;
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
    #examCalendar {
        padding: 10px;
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
                        <span style="margin-left: 10px; font-size: 0.8rem;">
                            (<?= htmlspecialchars($academic_year) ?> - <?= htmlspecialchars($semester) ?>)
                        </span>
                    </div>
                    <?php if($student_year_normalized === 'freshman'): ?>
                    <div class="freshman-note">
                        <i class="fas fa-info-circle"></i>
                        Note: Showing exams for Freshman students (also matching exams marked as Year 1)
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

            <!-- Student Info Box -->
            <div class="student-info-box">
                <i class="fas fa-user-graduate"></i>
                <div>
                    <strong>Student Information:</strong> 
                    <?= htmlspecialchars($user['username'] ?? 'Student') ?>
                    <span class="student-year-badge <?= $student_type ?>">
                        <?= ucfirst($student_type) ?> Year <?= htmlspecialchars($display_year) ?>
                    </span>
                    <span style="margin-left: 10px; font-size: 0.9rem;">
                        <i class="fas fa-calendar"></i> <?= htmlspecialchars($academic_year) ?> - <?= htmlspecialchars($semester) ?>
                    </span>
                </div>
            </div>

            <!-- Academic Info Alert -->
            <div class="student-info-alert">
                <i class="fas fa-info-circle"></i>
                <div>
                    <h4>Exam Filter Information</h4>
                    <p>Showing exams for <strong><?= ucfirst($student_type) ?> Year <?= htmlspecialchars($display_year) ?></strong> students 
                    in <strong><?= htmlspecialchars($academic_year) ?> - <?= htmlspecialchars($semester) ?></strong>. 
                    Exams are filtered by student type and year from the unified exam schedule system.</p>
                    <?php if($student_year_normalized === 'freshman'): ?>
                    <p><i class="fas fa-info-circle" style="color: #06b6d4;"></i> <strong>Freshman Note:</strong> Also matching exams marked as "Year 1" for compatibility.</p>
                    <?php endif; ?>
                    <?php if(empty($enrolled_course_ids)): ?>
                    <p style="color: #ef4444; margin-top: 5px;">
                        <i class="fas fa-exclamation-triangle"></i> You are not enrolled in any courses. Please contact your advisor.
                    </p>
                    <?php endif; ?>
                </div>
            </div>

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
                                        <td><?= htmlspecialchars($exam['supervisor_name'] ?? 'TBA') ?></td>
                                        <td>
                                            <span class="badge <?= $status_class ?>"><?= $status ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="fas fa-calendar-times"></i>
                                            <h3>No Exam Schedules Found</h3>
                                            <?php if(empty($enrolled_course_ids)): ?>
                                                <p>You are not enrolled in any courses. Please contact your advisor to get enrolled in courses first.</p>
                                            <?php else: ?>
                                                <p>You don't have any exam schedules for your enrolled courses in <?= htmlspecialchars($academic_year) ?> - <?= htmlspecialchars($semester) ?>.</p>
                                                <p style="margin-top: 10px; font-size: 0.9rem;">
                                                    <i class="fas fa-info-circle"></i> Showing exams for <?= ucfirst($student_type) ?> Year <?= htmlspecialchars($display_year) ?> students.
                                                    <?php if($student_year_normalized === 'freshman'): ?>
                                                    <br><i class="fas fa-info-circle" style="color: #06b6d4;"></i> Also checking for exams marked as "Year 1".
                                                    <?php endif; ?>
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
                if (studentType !== 'Not specified') {
                    studentTypeInfo = `👨‍🎓 Student Type: ${studentType} Year ${year}\n`;
                }
                
                // Create a simple alert
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
                if (studentType !== 'Not specified') {
                    studentTypeInfo = `Student Type: ${studentType} Year ${year}\n`;
                }
                
                info.el.title = `${title}\nCourse: ${course}\n${studentTypeInfo}Room: ${room}\nSupervisor: ${instructor}`;
                
                // Add custom styling
                info.el.style.borderRadius = '6px';
                info.el.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
                info.el.style.padding = '4px 8px';
                info.el.style.fontSize = '0.85rem';
                info.el.style.margin = '2px 0';
                
                // Add border for extension student exams
                if (studentType === 'extension') {
                    info.el.style.borderLeft = '3px solid #8b5cf6';
                } else if (studentType === 'freshman' || year === '1') {
                    info.el.style.borderLeft = '3px solid #06b6d4';
                }
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
    
    // Fallback for broken profile pictures
    function handleImageError(img) {
        img.onerror = null;
        img.src = '../assets/default_profile.png';
        return true;
    }
    </script>
</body>
</html>