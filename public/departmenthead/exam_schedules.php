<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only allow department head
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_head'){
    header("Location: ../index.php");
    exit;
}

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
    $semester = $_POST['semester'];
    $max_students = (int)$_POST['max_students'];
    $is_published = isset($_POST['is_published']) ? 1 : 0; // ADDED: Publish status
    
    // Validate time
    if(strtotime($end_time) <= strtotime($start_time)) {
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
                            max_students = ?, is_published = ?
                        WHERE exam_id = ? AND created_by = ?
                    ");
                    $stmt->execute([
                        $course_id, $exam_type, $exam_date, $start_time, $end_time,
                        $room_id, $supervisor_id, $academic_year, $semester, 
                        $max_students, $is_published, $exam_id, $user_id
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
                         supervisor_id, academic_year, semester, max_students, is_published, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $course_id, $exam_type, $exam_date, $start_time, $end_time,
                        $room_id, $supervisor_id, $academic_year, $semester, 
                        $max_students, $is_published, $user_id
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
        COUNT(DISTINCT exam_type) as exam_types_count
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

// Set default academic year and semester
$default_year = date('Y') . '-' . (date('Y') + 1);
$default_semester = date('n') <= 6 ? 'Spring' : 'Fall';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exam Schedules | Department Head Portal</title>
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

/* ================= Type Distribution ================= */
.distribution-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.75rem;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
    border: 1px solid var(--gray-light);
}

.distribution-card .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.distribution-card h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.75rem;
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
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    color: var(--dark);
    font-weight: 500;
    transition: var(--transition);
    border: 1px solid var(--gray-light);
}

.type-badge:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.type-badge .count {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
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
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 1.75rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
}

.calendar-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.schedule-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: var(--radius);
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    backdrop-filter: blur(10px);
}

.schedule-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
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

/* ================= Modal ================= */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1100;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(8px);
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
    background: white;
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: var(--shadow-lg);
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
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
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
    gap: 0.75rem;
}

.modal-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
    font-size: 1.25rem;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.modal-body {
    padding: 2rem;
}

/* ================= Form Styles ================= */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--dark);
    font-size: 0.875rem;
}

.form-group label span {
    color: var(--danger);
}

.form-control {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius);
    font-size: 1rem;
    transition: var(--transition);
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-control:disabled {
    background: var(--light);
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
    border-top: 1px solid var(--gray-light);
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
    
    .modal-content {
        margin: 1rem;
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
            <div>
                <h1>Exam Schedule Management</h1>
                <p style="color: var(--gray); margin-top: 0.5rem;">Schedule and manage all department exams</p>
            </div>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile">
                <div>
                    <div><?= htmlspecialchars($user['username'] ?? 'User') ?></div>
                    <small>Department Head</small>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if($message): ?>
            <div class="message <?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : ($message_type === 'error' ? 'exclamation-circle' : 'info-circle')) ?>"></i>
                <?= $message ?>
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
                    <div class="stat-value"><?= $stats['total_exams'] ?? 0 ?></div>
                    <p class="stat-desc">Scheduled in your department</p>
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
                    <div class="stat-value"><?= $stats['upcoming_exams'] ?? 0 ?></div>
                    <p class="stat-desc">Future scheduled exams</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning), #d97706);">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                </div>
                <div class="stat-content">
                    <h3>Published</h3>
                    <div class="stat-value"><?= $stats['published_exams'] ?? 0 ?></div>
                    <p class="stat-desc">Visible to students</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--secondary), #7c3aed);">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                </div>
                <div class="stat-content">
                    <h3>Exam Types</h3>
                    <div class="stat-value"><?= $stats['exam_types_count'] ?? 0 ?></div>
                    <p class="stat-desc">Different exam formats</p>
                </div>
            </div>
        </div>

        <!-- Exam Type Distribution -->
        <div class="distribution-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Exam Type Distribution</h3>
            </div>
            <div class="card-body">
                <div class="type-distribution">
                    <?php if(!empty($type_distribution)): ?>
                        <?php foreach($type_distribution as $type): ?>
                            <div class="type-badge">
                                <span><?= htmlspecialchars($type['exam_type']) ?></span>
                                <span class="count"><?= $type['count'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty-state">No exams scheduled yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Calendar View -->
        <div class="calendar-card">
            <div class="calendar-header">
                <h3><i class="fas fa-calendar"></i> Exam Calendar View</h3>
                <button class="schedule-btn" onclick="openExamModal()">
                    <i class="fas fa-plus"></i> Schedule New Exam
                </button>
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
                                        <strong style="color: var(--dark); display: block; margin-bottom: 0.25rem;">
                                            <?= htmlspecialchars($exam['room_name']) ?>
                                        </strong>
                                        <small style="color: var(--gray);">Capacity: <?= $exam['capacity'] ?></small>
                                    </td>
                                    <td>
                                        <?php if($exam['supervisor_name']): ?>
                                            <div style="color: var(--dark); font-weight: 500;">
                                                <?= htmlspecialchars($exam['supervisor_name']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--dark);">
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
                                <td colspan="8">
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
                            <small id="roomCapacity" style="display:block; margin-top:0.5rem; color: var(--gray); font-size: 0.875rem;"></small>
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
                                <small style="display: block; margin-top: 5px; color: #6b7280; font-size: 0.85em;">
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
                                <option value="Fall" <?= ($edit_exam['semester'] ?? $default_semester) == 'Fall' ? 'selected' : '' ?>>Fall</option>
                                <option value="Spring" <?= ($edit_exam['semester'] ?? $default_semester) == 'Spring' ? 'selected' : '' ?>>Spring</option>
                                <option value="Summer" <?= ($edit_exam['semester'] ?? '') == 'Summer' ? 'selected' : '' ?>>Summer</option>
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
                        <small style="color: var(--gray); font-size: 0.875rem;">
                            <i class="fas fa-info-circle"></i> When published, students can see this exam in their schedules.
                        </small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="save_exam" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?= $edit_exam ? 'Update Exam' : 'Create Exam Schedule' ?>
                        </button>
                        <button type="button" class="btn" onclick="closeExamModal()" style="background: var(--gray-light); color: var(--dark);">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
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

    // Modal Functions
    function openExamModal() {
        document.getElementById('examModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeExamModal() {
        document.getElementById('examModal').classList.remove('active');
        document.body.style.overflow = 'auto';
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
                    'published' => $exam['is_published'] == 1
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
                
                const status = published ? 'Published' : 'Not Published';
                info.el.title = `${title}\nCourse: ${course}\nRoom: ${room}\nSupervisor: ${supervisor}\nStatus: ${status}`;
                
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
            themeSystem: 'bootstrap5',
            buttonText: {
                today: 'Today',
                month: 'Month',
                week: 'Week',
                day: 'Day'
            }
        });
        
        calendar.render();
        
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
    });
</script>
</body>
</html>