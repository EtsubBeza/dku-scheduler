<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only allow department head
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_head'){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

$dept_id = $_SESSION['department_id'] ?? 0;
$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "success";

// Fetch current user info for sidebar
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
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

// Helper function to check for conflicts
function checkExamConflicts($pdo, $exam_date, $start_time, $end_time, $room_id, $supervisor_id, $course_id, $exclude_exam_id = 0) {
    $conflicts = [];
    
    // Check for room conflicts
    $room_check = $pdo->prepare("
        SELECT exam_id, course_code, exam_type 
        FROM exam_schedules es
        JOIN courses c ON es.course_id = c.course_id
        WHERE es.room_id = ? 
        AND es.exam_date = ? 
        AND NOT (? >= es.end_time OR ? <= es.start_time)
        AND es.exam_id != ?
    ");
    $room_check->execute([$room_id, $exam_date, $start_time, $end_time, $exclude_exam_id]);
    $room_conflicts = $room_check->fetchAll();
    
    foreach($room_conflicts as $conflict) {
        $conflicts[] = [
            'type' => 'Room',
            'details' => "Room already booked for {$conflict['course_code']} ({$conflict['exam_type']}) at this time"
        ];
    }
    
    // Check for supervisor conflicts
    if($supervisor_id) {
        $supervisor_check = $pdo->prepare("
            SELECT exam_id, course_code, exam_type 
            FROM exam_schedules es
            JOIN courses c ON es.course_id = c.course_id
            WHERE es.supervisor_id = ? 
            AND es.exam_date = ? 
            AND NOT (? >= es.end_time OR ? <= es.start_time)
            AND es.exam_id != ?
        ");
        $supervisor_check->execute([$supervisor_id, $exam_date, $start_time, $end_time, $exclude_exam_id]);
        $supervisor_conflicts = $supervisor_check->fetchAll();
        
        foreach($supervisor_conflicts as $conflict) {
            $conflicts[] = [
                'type' => 'Supervisor',
                'details' => "Supervisor already assigned to {$conflict['course_code']} ({$conflict['exam_type']}) at this time"
            ];
        }
    }
    
    // Check for same course multiple exams on same day
    $course_check = $pdo->prepare("
        SELECT exam_id, exam_type 
        FROM exam_schedules 
        WHERE course_id = ? 
        AND exam_date = ? 
        AND exam_id != ?
    ");
    $course_check->execute([$course_id, $exam_date, $exclude_exam_id]);
    if($course_check->rowCount() > 0) {
        $conflicts[] = [
            'type' => 'Course',
            'details' => "This course already has an exam scheduled on this date"
        ];
    }
    
    return $conflicts;
}

// Handle Add/Edit Exam Schedule
if(isset($_POST['save_exam'])){
    $exam_id = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0;
    $course_id = (int)$_POST['course_id'];
    $exam_type = $_POST['exam_type'];
    $exam_date = $_POST['exam_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $room_id = (int)$_POST['room_id'];
    $supervisor_id = !empty($_POST['supervisor_id']) ? (int)$_POST['supervisor_id'] : null;
    $academic_year = $_POST['academic_year'];
    $semester = $_POST['semester']; // 1st Semester or 2nd Semester
    $student_type = $_POST['student_type']; // regular or extension
    $year = $_POST['year']; // 1-5 or E1-E5
    $max_students = (int)$_POST['max_students'];
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    
    // Validate student type and year
    if($student_type === 'extension' && !preg_match('/^E[1-5]$/', $year)) {
        showMessage('error', "Invalid extension year. Must be E1-E5.");
    } elseif($student_type === 'regular' && (!is_numeric($year) || $year < 1 || $year > 5)) {
        showMessage('error', "Invalid regular year. Must be 1-5.");
    }
    // Validate time
    elseif(strtotime($end_time) <= strtotime($start_time)) {
        showMessage('error', "End time must be after start time");
    } elseif(strtotime($exam_date) < strtotime('today')) {
        showMessage('error', "Exam date cannot be in the past");
    } else {
        // Check for conflicts
        $conflicts = checkExamConflicts($pdo, $exam_date, $start_time, $end_time, $room_id, $supervisor_id, $course_id, $exam_id);
        
        if(!empty($conflicts)) {
            $conflict_messages = [];
            foreach($conflicts as $conflict) {
                $conflict_messages[] = "{$conflict['type']} Conflict: {$conflict['details']}";
            }
            showMessage('error', "Exam conflicts detected:<br>" . implode("<br>", $conflict_messages));
        } else {
            try {
                $pdo->beginTransaction();
                
                if($exam_id > 0) {
                    // Update existing exam
                    $stmt = $pdo->prepare("
                        UPDATE exam_schedules 
                        SET course_id = ?, exam_type = ?, exam_date = ?, start_time = ?, end_time = ?, 
                            room_id = ?, supervisor_id = ?, academic_year = ?, semester = ?, 
                            student_type = ?, year = ?, max_students = ?, is_published = ?
                        WHERE exam_id = ? AND created_by = ?
                    ");
                    $stmt->execute([
                        $course_id, $exam_type, $exam_date, $start_time, $end_time,
                        $room_id, $supervisor_id, $academic_year, $semester, 
                        $student_type, $year, $max_students, $is_published, $exam_id, $user_id
                    ]);
                    
                    if($stmt->rowCount() > 0) {
                        showMessage('success', "Exam schedule updated successfully!");
                    } else {
                        showMessage('error', "No changes made or exam not found.");
                    }
                } else {
                    // Insert new exam
                    $stmt = $pdo->prepare("
                        INSERT INTO exam_schedules 
                        (course_id, exam_type, exam_date, start_time, end_time, room_id, 
                         supervisor_id, academic_year, semester, student_type, year, 
                         max_students, is_published, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $course_id, $exam_type, $exam_date, $start_time, $end_time,
                        $room_id, $supervisor_id, $academic_year, $semester, 
                        $student_type, $year, $max_students, $is_published, $user_id
                    ]);
                    
                    showMessage('success', "Exam schedule created successfully!");
                }
                
                $pdo->commit();
            } catch(PDOException $e) {
                $pdo->rollBack();
                showMessage('error', "Database error: " . $e->getMessage());
            }
        }
    }
}

// Handle Delete Exam
if(isset($_GET['delete'])){
    $delete_id = (int)$_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM exam_schedules WHERE exam_id = ? AND created_by = ?");
        $stmt->execute([$delete_id, $user_id]);
        
        if($stmt->rowCount() > 0) {
            showMessage('success', "Exam schedule deleted successfully!");
        } else {
            showMessage('error', "Exam not found or you don't have permission to delete it.");
        }
    } catch (PDOException $e) {
        showMessage('error', "Error deleting exam: " . $e->getMessage());
    }
}

// Handle Publish/Unpublish Exam
if(isset($_GET['publish'])) {
    $exam_id = (int)$_GET['publish'];
    $action = $_GET['action'];
    
    try {
        if($action == 'publish') {
            $stmt = $pdo->prepare("UPDATE exam_schedules SET is_published = 1 WHERE exam_id = ? AND created_by = ?");
            $message = "Exam published successfully! Students can now see it.";
        } elseif($action == 'unpublish') {
            $stmt = $pdo->prepare("UPDATE exam_schedules SET is_published = 0 WHERE exam_id = ? AND created_by = ?");
            $message = "Exam unpublished successfully! Students can no longer see it.";
        }
        
        if(isset($stmt)) {
            $stmt->execute([$exam_id, $user_id]);
            
            if($stmt->rowCount() > 0) {
                showMessage('success', $message);
            } else {
                showMessage('error', "Exam not found or you don't have permission to modify it.");
            }
        }
    } catch (PDOException $e) {
        showMessage('error', "Error: " . $e->getMessage());
    }
    
    // Redirect to refresh page
    header("Location: exam_schedules.php");
    exit;
}

// Handle Edit - Load exam data
$edit_exam = null;
if(isset($_GET['edit'])){
    $edit_id = (int)$_GET['edit'];
    
    $stmt = $pdo->prepare("
        SELECT es.*, c.course_code, c.course_name, r.room_name
        FROM exam_schedules es
        JOIN courses c ON es.course_id = c.course_id
        JOIN rooms r ON es.room_id = r.room_id
        WHERE es.exam_id = ? AND es.created_by = ?
    ");
    $stmt->execute([$edit_id, $user_id]);
    $edit_exam = $stmt->fetch();
    
    if(!$edit_exam) {
        showMessage('error', "Exam not found or you don't have permission to edit it.");
    }
}

// Fetch data for dropdowns
$courses = $pdo->prepare("
    SELECT course_id, course_code, course_name 
    FROM courses 
    WHERE department_id = ? 
    ORDER BY course_code
");
$courses->execute([$dept_id]);

$rooms = $pdo->prepare("
    SELECT room_id, room_name, capacity
    FROM rooms 
    ORDER BY room_name
");
$rooms->execute();

// Fetch all exam schedules for this department
$exams_stmt = $pdo->prepare("
    SELECT es.*, 
           c.course_code, c.course_name,
           r.room_name, r.capacity,
           u.full_name as supervisor_name,
           0 as registered_count
    FROM exam_schedules es
    JOIN courses c ON es.course_id = c.course_id
    JOIN rooms r ON es.room_id = r.room_id
    LEFT JOIN users u ON es.supervisor_id = u.user_id
    WHERE c.department_id = ?
    ORDER BY es.exam_date DESC, es.start_time
");
$exams_stmt->execute([$dept_id]);
$exams = $exams_stmt->fetchAll();

// Fetch exam statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_exams,
        SUM(CASE WHEN exam_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_exams,
        SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published_exams,
        COUNT(DISTINCT exam_type) as exam_types_count,
        COUNT(DISTINCT student_type) as student_types_count
    FROM exam_schedules es
    JOIN courses c ON es.course_id = c.course_id
    WHERE c.department_id = ?
");
$stats_stmt->execute([$dept_id]);
$stats = $stats_stmt->fetch();

// Fetch exam type distribution
$type_dist_stmt = $pdo->prepare("
    SELECT exam_type, COUNT(*) as count
    FROM exam_schedules es
    JOIN courses c ON es.course_id = c.course_id
    WHERE c.department_id = ?
    GROUP BY exam_type
    ORDER BY count DESC
");
$type_dist_stmt->execute([$dept_id]);
$type_distribution = $type_dist_stmt->fetchAll();

// Fetch student type distribution
$student_type_stmt = $pdo->prepare("
    SELECT 
        student_type,
        year,
        COUNT(*) as count
    FROM exam_schedules es
    JOIN courses c ON es.course_id = c.course_id
    WHERE c.department_id = ?
    GROUP BY student_type, year
    ORDER BY student_type DESC, year
");
$student_type_stmt->execute([$dept_id]);
$student_type_distribution = $student_type_stmt->fetchAll();

// Set default academic year and semester
$default_year = date('Y') . '-' . (date('Y') + 1);
$default_semester = date('n') <= 6 ? '2nd Semester' : '1st Semester';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exam Schedules | Department Head Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- FullCalendar CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
<!-- Include Dark Mode CSS -->
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
* { 
    box-sizing: border-box; 
    margin:0; 
    padding:0; 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
}

body {
    background: var(--bg-primary);
    min-height: 100vh;
    color: var(--text-primary);
    line-height: 1.6;
}

/* ================= Topbar for Hamburger ================= */
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

.topbar h2 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-sidebar);
}

/* ================= Sidebar ================= */
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
    padding: 20px 0;
}

.sidebar.hidden { 
    transform:translateX(-260px); 
}

.sidebar a { 
    display:block; 
    padding:12px 20px; 
    color:var(--text-sidebar); 
    text-decoration:none; 
    transition: background 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar a:hover, .sidebar a.active { 
    background:#1abc9c; 
    color:white; 
}

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
    padding:30px 50px;
    min-height:100vh;
    background:var(--bg-primary);
    color:var(--text-primary);
    transition: all 0.3s ease;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding-top: 80px;
        padding: 20px;
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
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    line-height: 1.2;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: var(--bg-card);
    padding: 0.75rem 1.25rem;
    border-radius: 15px;
    box-shadow: 0 6px 20px var(--shadow-color);
    transition: all 0.3s ease;
}

.user-info:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 28px var(--shadow-color);
}

.user-info img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #1abc9c;
}

.user-info div {
    display: flex;
    flex-direction: column;
}

.user-info div div {
    font-weight: 600;
    color: var(--text-primary);
}

.user-info small {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* ================= Welcome Section ================= */
.welcome-section {
    background: linear-gradient(135deg,#6366f1,#3b82f6);
    color:white;
    padding:30px 25px;
    border-radius:15px;
    margin-bottom:30px;
    box-shadow:0 6px 18px var(--shadow-color);
}

.welcome-section h1 { 
    font-size:28px; 
    font-weight:600; 
    margin-bottom:8px; 
}

.welcome-section p { 
    font-size:16px; 
    opacity:0.9; 
}

/* ================= Stats Cards ================= */
.stats-cards { 
    display:flex; 
    flex-wrap:wrap; 
    gap:20px; 
    margin-bottom:30px; 
}

.stats-cards .card {
    flex:1 1 220px;
    background:var(--bg-card);
    border-radius:15px;
    padding:25px 20px;
    box-shadow:0 6px 20px var(--shadow-color);
    display:flex; 
    flex-direction:column; 
    justify-content:space-between;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stats-cards .card:hover { 
    transform:translateY(-6px); 
    box-shadow:0 10px 28px var(--shadow-color); 
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
    background:#e0e7ff; 
    color:#4f46e5; 
}

.stats-cards .card h3 { 
    font-size:18px; 
    margin-bottom:8px; 
    color:var(--text-primary); 
    font-weight:600; 
}

.stats-cards .card p { 
    font-size:24px; 
    font-weight:bold; 
    color:var(--text-primary); 
    margin-bottom:15px; 
}

/* Update card icons for dark mode */
[data-theme="dark"] .card-icon {
    background: #1e40af;
    color: #93c5fd;
}

/* ================= Table Section ================= */
.table-section { 
    background-color: var(--bg-card); 
    padding: 25px; 
    border-radius: 15px; 
    box-shadow: 0 6px 18px var(--shadow-color); 
    margin-bottom: 30px; 
}

.table-section h2 { 
    margin-bottom: 20px; 
    color: var(--text-primary); 
    font-size: 1.5rem;
    font-weight: 600;
}

.exam-table { 
    width: 100%; 
    border-collapse: collapse; 
}

.exam-table th, .exam-table td {
    padding: 15px; 
    text-align: left; 
    border-bottom: 1px solid var(--border-color);
}

.exam-table th { 
    background-color: var(--table-header); 
    color: var(--text-sidebar); 
    font-weight: 600; 
}

.exam-table tr:hover { 
    background-color: var(--hover-color); 
}

/* ================= Message Styles ================= */
.message {
    padding: 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
}

.message.success {
    background: var(--success-bg);
    color: var(--success-text);
    border: 1px solid var(--success-text);
}

.message.error {
    background: var(--error-bg);
    color: var(--error-text);
    border: 1px solid var(--error-text);
}

.message.warning {
    background: var(--warning-bg);
    color: var(--warning-text);
    border: 1px solid var(--warning-text);
}

/* ================= Button Styles ================= */
.btn {
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary {
    background: #6366f1;
    color: white;
}

.btn-primary:hover {
    background: #4f46e5;
    transform: translateY(-2px);
}

.btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #d97706, #b45309);
    transform: translateY(-2px);
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    transform: translateY(-2px);
}

.btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-2px);
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.btn-sm {
    padding: 8px 15px;
    font-size: 0.875rem;
}

.btn-group {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
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
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.badge-success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.badge-warning {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.badge-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.badge-secondary {
    background: rgba(156, 163, 175, 0.1);
    color: #9ca3af;
    border: 1px solid rgba(156, 163, 175, 0.2);
}

/* ================= Distribution Cards ================= */
.distribution-card {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 6px 20px var(--shadow-color);
    margin-bottom: 30px;
}

.distribution-card h3 {
    margin-bottom: 20px;
    color: var(--text-primary);
    font-size: 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.type-distribution {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    border-radius: 50px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-weight: 500;
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
}

.type-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px var(--shadow-color);
}

.type-badge .count {
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* ================= Student Type Badges ================= */
.student-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    border-radius: 50px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-weight: 500;
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
}

.student-type-badge.regular {
    border-left: 4px solid #3b82f6;
}

.student-type-badge.extension {
    border-left: 4px solid #8b5cf6;
}

.student-type-badge .count {
    background: var(--bg-card);
    color: var(--text-primary);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid var(--border-color);
}

.student-type-distribution {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 15px;
}

/* Year badge in table */
.year-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 5px;
}

.regular-year-badge {
    background: #3b82f6;
    color: white;
}

.extension-year-badge {
    background: #8b5cf6;
    color: white;
}

/* ================= Calendar Card ================= */
.calendar-card {
    background: var(--bg-card);
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 6px 20px var(--shadow-color);
    margin-bottom: 30px;
}

.calendar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 25px;
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    color: white;
    border-radius: 15px 15px 0 0;
}

.calendar-header h3 {
    font-size: 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.schedule-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 12px 20px;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.schedule-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

#examCalendar {
    padding: 20px;
    background: var(--bg-card);
}

/* ================= Table Card ================= */
.table-card {
    background: var(--bg-card);
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 6px 20px var(--shadow-color);
}

.table-card .card-header {
    padding: 20px 25px;
    background: var(--table-header);
    border-bottom: 1px solid var(--border-color);
}

.table-card h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
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
    background: var(--table-header);
}

.exam-table th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: var(--text-sidebar);
    border-bottom: 2px solid var(--border-color);
}

.exam-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
    transition: all 0.3s ease;
    color: var(--text-primary);
}

.exam-table tbody tr {
    transition: all 0.3s ease;
}

.exam-table tbody tr:hover {
    background: var(--hover-color);
    transform: translateX(4px);
}

.exam-table tbody tr:last-child td {
    border-bottom: none;
}

/* ================= Progress Bar ================= */
.progress-container {
    width: 100px;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: var(--progress-bg);
    border-radius: 4px;
    overflow: hidden;
    margin-top: 0.5rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
    border-radius: 4px;
    transition: width 0.6s ease;
}

/* ================= Empty State ================= */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-light);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 20px;
    display: block;
    color: var(--border-color);
}

.empty-state h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.75rem;
}

.empty-state p {
    color: var(--text-secondary);
    max-width: 400px;
    margin: 0 auto;
}

/* ================= Modal ================= */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--overlay-bg);
    z-index: 1100;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal.active {
    display: flex;
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.modal-content {
    background: var(--bg-card);
    border-radius: 15px;
    width: 100%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    padding: 20px 25px;
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    color: white;
    border-radius: 15px 15px 0 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 1;
}

.modal-header h3 {
    font-size: 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.25rem;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.modal-body {
    padding: 25px;
}

/* ================= Form Styles ================= */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.form-group label span {
    color: #ef4444;
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: var(--bg-input);
    color: var(--text-primary);
}

.form-control:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-control:disabled {
    background: var(--bg-secondary);
    cursor: not-allowed;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

/* ================= Checkbox Styles ================= */
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.checkbox-group label {
    margin-bottom: 0;
    cursor: pointer;
    color: var(--text-primary);
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
    
    .stats-cards {
        flex-direction: column;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .btn-group {
        flex-wrap: wrap;
    }
    
    .modal-content {
        margin: 1rem;
    }
    
    .welcome-section h1 {
        font-size: 24px;
    }
}

/* ================= FullCalendar Styles ================= */
#examCalendar {
    padding: 20px;
    background: var(--bg-card);
}

/* Light mode (default) */
.fc .fc-view-harness,
.fc .fc-daygrid-body,
.fc .fc-scrollgrid-section-body,
.fc .fc-scrollgrid-section {
    color: var(--text-primary);
}

.fc .fc-daygrid-day-number,
.fc .fc-col-header-cell-cushion,
.fc .fc-event-time,
.fc .fc-event-title,
.fc .fc-toolbar-title {
    color: var(--text-primary);
}

.fc .fc-button {
    color: var(--text-primary);
}

.fc .fc-col-header-cell {
    background: var(--table-header);
    border-color: var(--border-color);
}

.fc .fc-daygrid-day {
    border-color: var(--border-color);
}

.fc .fc-day-other .fc-daygrid-day-top {
    opacity: 0.5;
}

.fc .fc-event {
    border-radius: 6px;
    border: none;
    box-shadow: 0 2px 4px var(--shadow-color);
}

/* ================= FullCalendar Dark Mode Overrides ================= */
[data-theme="dark"] .fc {
    --fc-border-color: var(--border-color);
    --fc-button-bg-color: #6366f1;
    --fc-button-border-color: #6366f1;
    --fc-button-hover-bg-color: #4f46e5;
    --fc-button-hover-border-color: #4f46e5;
    --fc-button-active-bg-color: #4f46e5;
    --fc-button-active-border-color: #4f46e5;
    --fc-neutral-bg-color: var(--bg-secondary);
    --fc-neutral-text-color: var(--text-primary);
    --fc-page-bg-color: var(--bg-card);
    --fc-event-bg-color: #6366f1;
    --fc-event-border-color: #6366f1;
    --fc-event-text-color: white;
}

[data-theme="dark"] .fc-theme-standard .fc-scrollgrid,
[data-theme="dark"] .fc-theme-standard td,
[data-theme="dark"] .fc-theme-standard th {
    border-color: var(--border-color);
}

[data-theme="dark"] .fc .fc-daygrid-day.fc-day-today {
    background-color: rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] .fc .fc-button-primary:not(:disabled).fc-button-active,
[data-theme="dark"] .fc .fc-button-primary:not(:disabled):active {
    background-color: #4f46e5;
    border-color: #4f46e5;
}

[data-theme="dark"] .fc .fc-button-primary:disabled {
    background-color: var(--border-color);
    border-color: var(--border-color);
}

/* Dark mode specific text colors - WHITE TEXT */
[data-theme="dark"] .fc .fc-view-harness,
[data-theme="dark"] .fc .fc-daygrid-body,
[data-theme="dark"] .fc .fc-scrollgrid-section-body,
[data-theme="dark"] .fc .fc-scrollgrid-section {
    color: white;
}

[data-theme="dark"] .fc .fc-daygrid-day-number,
[data-theme="dark"] .fc .fc-col-header-cell-cushion,
[data-theme="dark"] .fc .fc-event-time,
[data-theme="dark"] .fc .fc-event-title,
[data-theme="dark"] .fc .fc-toolbar-title {
    color: white;
}

[data-theme="dark"] .fc .fc-button {
    color: white;
}

[data-theme="dark"] .fc .fc-col-header-cell {
    background: rgba(99, 102, 241, 0.2);
    border-color: var(--border-color);
}

[data-theme="dark"] .fc .fc-col-header-cell-cushion {
    font-weight: 600;
}

[data-theme="dark"] .fc .fc-daygrid-day {
    border-color: var(--border-color);
}

[data-theme="dark"] .fc .fc-day-other .fc-daygrid-day-top {
    opacity: 0.5;
}

[data-theme="dark"] .fc .fc-daygrid-day-number {
    font-weight: 500;
}

[data-theme="dark"] .fc .fc-event {
    border-radius: 6px;
    border: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

[data-theme="dark"] .fc .fc-event:hover {
    opacity: 0.9;
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

[data-theme="dark"] .fc .fc-toolbar-title {
    font-weight: 600;
}

[data-theme="dark"] .fc .fc-button {
    font-weight: 500;
    border-radius: 8px;
    padding: 8px 16px;
}

[data-theme="dark"] .fc .fc-button:hover {
    transform: translateY(-1px);
}

[data-theme="dark"] .fc .fc-button-primary:not(:disabled):focus {
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.3);
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Exam Schedules</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile Picture">
            <p><?= htmlspecialchars($user['username'] ?? 'User') ?></p>
        </div>
        <nav>
            <a href="departmenthead_dashboard.php" class="<?= $current_page=='departmenthead_dashboard.php'?'active':'' ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="manage_enrollments.php" class="<?= $current_page=='manage_enrollments.php'?'active':'' ?>">
                <i class="fas fa-users"></i> Manage Enrollments
            </a>
            <a href="manage_schedules.php" class="<?= $current_page=='manage_schedules.php'?'active':'' ?>">
                <i class="fas fa-calendar-alt"></i> Manage Schedules
            </a>
            <a href="assign_courses.php" class="<?= $current_page=='assign_courses.php'?'active':'' ?>">
                <i class="fas fa-chalkboard-teacher"></i> Assign Courses
            </a>
            <a href="add_courses.php" class="<?= $current_page=='add_courses.php'?'active':'' ?>">
                <i class="fas fa-book"></i> Add Courses
            </a>
            <a href="exam_schedules.php" class="<?= $current_page=='exam_schedules.php'?'active':'' ?>">
                <i class="fas fa-clipboard-list"></i> Exam Schedules
            </a>
            <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">
                <i class="fas fa-user-edit"></i> Edit Profile
            </a>
            <a href="manage_announcements.php" class="<?= $current_page=='manage_announcements.php'?'active':'' ?>">
                <i class="fas fa-bullhorn"></i> Announcements
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
            <h1>Exam Schedule Management</h1>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile">
                <div>
                    <div><?= htmlspecialchars($user['username'] ?? 'User') ?></div>
                    <small>Department Head</small>
                </div>
            </div>
        </div>

        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Welcome, <?= htmlspecialchars($user['username'] ?? 'User'); ?> 👋</h1>
            <p>Schedule and manage all department exams. Use the calendar to view and schedule exams.</p>
        </div>

        <!-- Messages -->
        <?php if($message): ?>
            <div class="message <?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : ($message_type === 'error' ? 'exclamation-circle' : 'info-circle')) ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3>Total Exams</h3>
                <p><?= $stats['total_exams'] ?? 0 ?></p>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Upcoming Exams</h3>
                <p><?= $stats['upcoming_exams'] ?? 0 ?></p>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <h3>Published</h3>
                <p><?= $stats['published_exams'] ?? 0 ?></p>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <h3>Exam Types</h3>
                <p><?= $stats['exam_types_count'] ?? 0 ?></p>
            </div>
            
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h3>Student Types</h3>
                <p><?= $stats['student_types_count'] ?? 0 ?></p>
            </div>
        </div>

        <!-- Distribution Cards -->
        <div class="distribution-card">
            <h3><i class="fas fa-chart-bar"></i> Exam Type Distribution</h3>
            <div class="type-distribution">
                <?php if(!empty($type_distribution)): ?>
                    <?php foreach($type_distribution as $type): ?>
                        <div class="type-badge">
                            <i class="fas fa-<?= $type['exam_type'] == 'Midterm' ? 'file-alt' : ($type['exam_type'] == 'Final' ? 'graduation-cap' : ($type['exam_type'] == 'Quiz' ? 'question-circle' : 'tasks')) ?>"></i>
                            <span><?= htmlspecialchars($type['exam_type']) ?></span>
                            <span class="count"><?= $type['count'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty-state">No exams scheduled yet</p>
                <?php endif; ?>
            </div>
            
            <h3 style="margin-top: 25px;"><i class="fas fa-user-graduate"></i> Student Type Distribution</h3>
            <div class="student-type-distribution">
                <?php if(!empty($student_type_distribution)): ?>
                    <?php foreach($student_type_distribution as $dist): ?>
                        <div class="student-type-badge <?= $dist['student_type'] ?>">
                            <i class="fas fa-<?= $dist['student_type'] == 'regular' ? 'user' : 'user-tie' ?>"></i>
                            <span><?= ucfirst($dist['student_type']) ?> - Year <?= $dist['year'] ?></span>
                            <span class="count"><?= $dist['count'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">No student type data available</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Calendar View -->
        <div class="table-section">
            <div class="calendar-header">
                <h3><i class="fas fa-calendar"></i> Exam Calendar View</h3>
                <button class="schedule-btn" onclick="openExamModal()">
                    <i class="fas fa-plus"></i> Schedule New Exam
                </button>
            </div>
            <div id="examCalendar"></div>
        </div>

        <!-- Exam Schedule Table -->
        <div class="table-section">
            <h2><i class="fas fa-table"></i> All Exam Schedules</h2>
            <div class="table-container">
                <table class="exam-table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Exam Type</th>
                            <th>Date & Time</th>
                            <th>Room</th>
                            <th>Student Type</th>
                            <th>Supervisor</th>
                            <th>Students</th>
                            <th>Status</th>
                            <th>Actions</th>
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
                                        <strong style="color: var(--text-primary); display: block; margin-bottom: 0.25rem;">
                                            <?= htmlspecialchars($exam['course_code']) ?>
                                        </strong>
                                        <small style="color: var(--text-secondary);"><?= htmlspecialchars($exam['course_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?= htmlspecialchars($exam['exam_type']) ?></span>
                                    </td>
                                    <td>
                                        <strong style="color: var(--text-primary); display: block; margin-bottom: 0.25rem;">
                                            <?= date('M d, Y', strtotime($exam['exam_date'])) ?>
                                        </strong>
                                        <small style="color: var(--text-secondary);">
                                            <?= date('h:i A', strtotime($exam['start_time'])) ?> - <?= date('h:i A', strtotime($exam['end_time'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong style="color: var(--text-primary); display: block; margin-bottom: 0.25rem;">
                                            <?= htmlspecialchars($exam['room_name']) ?>
                                        </strong>
                                        <small style="color: var(--text-secondary);">Capacity: <?= $exam['capacity'] ?></small>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 5px;">
                                            <span style="font-weight: 500; color: var(--text-primary);">
                                                <?= ucfirst($exam['student_type']) ?>
                                            </span>
                                            <span class="year-badge <?= $exam['student_type'] ?>-year-badge">
                                                Year <?= $exam['year'] ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($exam['supervisor_name']): ?>
                                            <div style="color: var(--text-primary); font-weight: 500;">
                                                <?= htmlspecialchars($exam['supervisor_name']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-primary);">
                                            <?= $exam['max_students'] ?> max
                                        </div>
                                        <div class="progress-container">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                            <?php if($exam['is_published'] == 1): ?>
                                                <span class="badge badge-success">
                                                    <i class="fas fa-eye"></i> Published
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">
                                                    <i class="fas fa-eye-slash"></i> Not Published
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if($is_past): ?>
                                                <span class="badge badge-secondary">Past</span>
                                            <?php elseif($is_upcoming): ?>
                                                <span class="badge badge-success">Upcoming</span>
                                            <?php else: ?>
                                                <span class="badge badge-primary">Today</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="?edit=<?= $exam['exam_id'] ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            
                                            <?php if($exam['is_published'] == 0): ?>
                                                <a href="?publish=<?= $exam['exam_id'] ?>&action=publish" 
                                                   class="btn btn-success btn-sm"
                                                   onclick="return confirm('Publish this exam? Students will be able to see it.')">
                                                    <i class="fas fa-eye"></i> Publish
                                                </a>
                                            <?php else: ?>
                                                <a href="?publish=<?= $exam['exam_id'] ?>&action=unpublish" 
                                                   class="btn btn-secondary btn-sm"
                                                   onclick="return confirm('Unpublish this exam? Students will no longer see it.')">
                                                    <i class="fas fa-eye-slash"></i> Unpublish
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="?delete=<?= $exam['exam_id'] ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Are you sure you want to delete this exam schedule?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-calendar-times"></i>
                                        <h3>No Exam Schedules Found</h3>
                                        <p>Schedule your first exam by clicking the button above.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Exam Modal Form -->
    <div id="examModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-plus"></i> <?= $edit_exam ? 'Edit Exam Schedule' : 'Create New Exam Schedule' ?></h3>
                <button class="modal-close" onclick="closeExamModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="examForm">
                    <input type="hidden" name="exam_id" value="<?= $edit_exam['exam_id'] ?? '' ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="course_id">Course <span>*</span></label>
                            <select class="form-control" id="course_id" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php 
                                // Reset courses pointer and loop
                                $courses->execute([$dept_id]);
                                while($course = $courses->fetch()): ?>
                                    <option value="<?= $course['course_id'] ?>" 
                                        <?= ($edit_exam['course_id'] ?? '') == $course['course_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="exam_type">Exam Type <span>*</span></label>
                            <select class="form-control" id="exam_type" name="exam_type" required>
                                <option value="">Select Type</option>
                                <option value="Midterm" <?= ($edit_exam['exam_type'] ?? '') == 'Midterm' ? 'selected' : '' ?>>Midterm</option>
                                <option value="Final" <?= ($edit_exam['exam_type'] ?? '') == 'Final' ? 'selected' : '' ?>>Final</option>
                                <option value="Quiz" <?= ($edit_exam['exam_type'] ?? '') == 'Quiz' ? 'selected' : '' ?>>Quiz</option>
                                <option value="Practical" <?= ($edit_exam['exam_type'] ?? '') == 'Practical' ? 'selected' : '' ?>>Practical</option>
                                <option value="Project Defense" <?= ($edit_exam['exam_type'] ?? '') == 'Project Defense' ? 'selected' : '' ?>>Project Defense</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="exam_date">Exam Date <span>*</span></label>
                            <input type="date" class="form-control" id="exam_date" name="exam_date" 
                                   value="<?= $edit_exam['exam_date'] ?? date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="start_time">Start Time <span>*</span></label>
                            <input type="time" class="form-control" id="start_time" name="start_time" 
                                   value="<?= $edit_exam['start_time'] ?? '09:00' ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="end_time">End Time <span>*</span></label>
                            <input type="time" class="form-control" id="end_time" name="end_time" 
                                   value="<?= $edit_exam['end_time'] ?? '10:30' ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="room_id">Room <span>*</span></label>
                            <select class="form-control" id="room_id" name="room_id" required>
                                <option value="">Select Room</option>
                                <?php 
                                // Reset rooms pointer and loop
                                $rooms->execute();
                                while($room = $rooms->fetch()): ?>
                                    <option value="<?= $room['room_id'] ?>" 
                                        <?= ($edit_exam['room_id'] ?? '') == $room['room_id'] ? 'selected' : '' ?>
                                        data-capacity="<?= $room['capacity'] ?>">
                                        <?= htmlspecialchars($room['room_name']) ?> (Capacity: <?= $room['capacity'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small id="roomCapacity" style="display:block; margin-top:0.5rem; color: var(--text-secondary); font-size: 0.875rem;"></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="supervisor_id">Supervisor (Optional)</label>
                            <?php
                            // Query for instructors directly here
                            $instructor_stmt = $pdo->query("SELECT user_id, username, full_name, department_id FROM users WHERE role = 'instructor' ORDER BY full_name, username");
                            $instructors_list = $instructor_stmt->fetchAll();
                            $instructor_count = count($instructors_list);
                            ?>
                            
                            <select class="form-control" id="supervisor_id" name="supervisor_id">
                                <option value="">No Supervisor</option>
                                <?php if($instructor_count > 0): ?>
                                    <?php foreach($instructors_list as $instructor): ?>
                                        <option value="<?= $instructor['user_id'] ?>" 
                                            <?= ($edit_exam['supervisor_id'] ?? '') == $instructor['user_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($instructor['full_name'] ?? $instructor['username']) ?>
                                            ( <?= $instructor['username'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No instructors found in database</option>
                                <?php endif; ?>
                            </select>
                            
                            <?php if($instructor_count > 0): ?>
                                <small style="display: block; margin-top: 5px; color: var(--text-secondary); font-size: 0.85em;">
                                    <i class="fas fa-info-circle"></i> Found <?= $instructor_count ?> instructor(s) in system
                                </small>
                            <?php else: ?>
                                <small style="display: block; margin-top: 5px; color: #ef4444; font-size: 0.85em;">
                                    <i class="fas fa-exclamation-triangle"></i> No instructors found! Please add instructors first.
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="academic_year">Academic Year <span>*</span></label>
                            <input type="text" class="form-control" id="academic_year" name="academic_year" 
                                   value="<?= $edit_exam['academic_year'] ?? $default_year ?>" 
                                   placeholder="e.g., 2023-2024" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="semester">Semester <span>*</span></label>
                            <select class="form-control" id="semester" name="semester" required>
                                <option value="1st Semester" <?= ($edit_exam['semester'] ?? $default_semester) == '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
                                <option value="2nd Semester" <?= ($edit_exam['semester'] ?? $default_semester) == '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="student_type">Student Type <span>*</span></label>
                            <select class="form-control" id="student_type" name="student_type" required onchange="updateYearOptions()">
                                <option value="regular" <?= ($edit_exam['student_type'] ?? 'regular') == 'regular' ? 'selected' : '' ?>>Regular</option>
                                <option value="extension" <?= ($edit_exam['student_type'] ?? '') == 'extension' ? 'selected' : '' ?>>Extension</option>
                            </select>
                            <small style="color: var(--text-secondary); font-size: 0.875rem;">
                                <i class="fas fa-info-circle"></i> Regular: Year 1-5, Extension: Year E1-E5
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="year">Year <span>*</span></label>
                            <select class="form-control" id="year" name="year" required>
                                <!-- Options will be populated by JavaScript -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_students">Maximum Students <span>*</span></label>
                            <input type="number" class="form-control" id="max_students" name="max_students" 
                                   value="<?= $edit_exam['max_students'] ?? 50 ?>" min="1" max="500" required>
                        </div>
                    </div>
                    
                    <!-- Publish Checkbox -->
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_published" name="is_published" value="1" 
                                   <?= isset($edit_exam['is_published']) && $edit_exam['is_published'] == 1 ? 'checked' : '' ?>>
                            <label for="is_published">
                                Publish this exam (make visible to students)
                            </label>
                        </div>
                        <small style="color: var(--text-secondary); font-size: 0.875rem;">
                            <i class="fas fa-info-circle"></i> When published, students can see this exam in their schedules.
                        </small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="save_exam" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?= $edit_exam ? 'Update Exam' : 'Create Exam Schedule' ?>
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeExamModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<!-- Include darkmode.js -->
<script src="../../assets/js/darkmode.js"></script>
<script>
    // Sidebar Toggle
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    // Modal Functions
    function openExamModal() {
        document.getElementById('examModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeExamModal() {
        document.getElementById('examModal').classList.remove('active');
        document.body.style.overflow = 'auto';
    }

    // Update year options based on student type
    function updateYearOptions() {
        const studentType = document.getElementById('student_type').value;
        const yearSelect = document.getElementById('year');
        
        // Clear existing options
        yearSelect.innerHTML = '';
        
        let years = [];
        if (studentType === 'regular') {
            years = ['1', '2', '3', '4', '5'];
        } else if (studentType === 'extension') {
            years = ['E1', 'E2', 'E3', 'E4', 'E5'];
        }
        
        // Add new options
        years.forEach(year => {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = `Year ${year}`;
            
            // If editing, select the saved year
            <?php if(isset($edit_exam)): ?>
                if(year === '<?= $edit_exam['year'] ?? '' ?>') {
                    option.selected = true;
                }
            <?php endif; ?>
            
            yearSelect.appendChild(option);
        });
        
        // If editing and year not in options (shouldn't happen), add it
        <?php if(isset($edit_exam)): ?>
            if(!years.includes('<?= $edit_exam['year'] ?? '' ?>') && '<?= $edit_exam['year'] ?? '' ?>' !== '') {
                const option = document.createElement('option');
                option.value = '<?= $edit_exam['year'] ?>';
                option.textContent = `Year <?= $edit_exam['year'] ?>`;
                option.selected = true;
                yearSelect.appendChild(option);
            }
        <?php endif; ?>
    }

    // Show room capacity when selected
    document.getElementById('room_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const capacity = selectedOption.getAttribute('data-capacity');
        const capacityDisplay = document.getElementById('roomCapacity');
        
        if(capacity) {
            capacityDisplay.textContent = `Room Capacity: ${capacity} students`;
            capacityDisplay.style.display = 'block';
            
            // Set max students to room capacity by default
            document.getElementById('max_students').max = capacity;
            if(parseInt(document.getElementById('max_students').value) > parseInt(capacity)) {
                document.getElementById('max_students').value = capacity;
            }
        } else {
            capacityDisplay.style.display = 'none';
        }
    });

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
                'room' => $exam['room_name'],
                'supervisor' => $exam['supervisor_name'] || 'Not Assigned',
                'published' => $exam['is_published'] == 1,
                'student_type' => $exam['student_type'],
                'year' => $exam['year']
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
            // When event is clicked, open edit modal
            window.location.href = '?edit=' + info.event.id;
        },
        eventDidMount: function(info) {
            // Add tooltip
            const title = info.event.title;
            const course = info.event.extendedProps.course;
            const room = info.event.extendedProps.room;
            const supervisor = info.event.extendedProps.supervisor;
            const published = info.event.extendedProps.published;
            const studentType = info.event.extendedProps.student_type;
            const year = info.event.extendedProps.year;
            
            const status = published ? 'Published' : 'Not Published';
            info.el.title = `${title}\nCourse: ${course}\nRoom: ${room}\nSupervisor: ${supervisor}\nStudent Type: ${studentType} Year ${year}\nStatus: ${status}`;
            
            // Add custom styling
            info.el.style.borderRadius = '6px';
            info.el.style.boxShadow = '0 2px 6px rgba(0,0,0,0.1)';
            info.el.style.padding = '4px 8px';
            info.el.style.fontSize = '0.85rem';
            
            // Add unpublished indicator (gray border)
            if(!info.event.extendedProps.published) {
                info.el.style.opacity = '0.7';
                info.el.style.border = '2px dashed #9ca3af';
            }
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
        buttonText: {
            today: 'Today',
            month: 'Month',
            week: 'Week',
            day: 'Day'
        }
    });
    
    calendar.render();
    
    // Initialize year options
    updateYearOptions();
    
    // If edit parameter exists, open modal automatically
    <?php if(isset($_GET['edit'])): ?>
        setTimeout(() => openExamModal(), 100);
    <?php endif; ?>
    
    // Initialize room capacity display
    const roomSelect = document.getElementById('room_id');
    if(roomSelect.value) {
        roomSelect.dispatchEvent(new Event('change'));
    }
});
    
    // Form validation
    document.getElementById('examForm').addEventListener('submit', function(e) {
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        const examDate = document.getElementById('exam_date').value;
        const today = new Date().toISOString().split('T')[0];
        const studentType = document.getElementById('student_type').value;
        const year = document.getElementById('year').value;
        
        if(startTime >= endTime) {
            e.preventDefault();
            showToast('error', 'End time must be after start time.');
            return false;
        }
        
        if(examDate < today) {
            e.preventDefault();
            showToast('error', 'Exam date cannot be in the past.');
            return false;
        }
        
        // Validate year based on student type
        if(studentType === 'regular') {
            const yearNum = parseInt(year);
            if(isNaN(yearNum) || yearNum < 1 || yearNum > 5) {
                e.preventDefault();
                showToast('error', 'Regular year must be between 1 and 5.');
                return false;
            }
        } else if(studentType === 'extension') {
            if(!/^E[1-5]$/.test(year)) {
                e.preventDefault();
                showToast('error', 'Extension year must be E1 to E5.');
                return false;
            }
        }
        
        return true;
    });

    // Close modal with ESC key
    document.addEventListener('keydown', function(e) {
        if(e.key === 'Escape') {
            closeExamModal();
        }
    });
    
    // Toast notification function
    function showToast(type, message) {
        const toast = document.createElement('div');
        toast.className = `message ${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            ${message}
        `;
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.style.maxWidth = '350px';
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
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
    
    // Simple script for sidebar active state
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
</script>
</body>
</html>