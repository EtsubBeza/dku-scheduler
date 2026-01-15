<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Redirect if not department head
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_head'){
    header("Location: ../index.php");
    exit;
}

$dept_id = $_SESSION['department_id'] ?? 0;
$message = "";

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

// Handle form submission for enrollment
if(isset($_POST['enroll'])){
    $student_ids = $_POST['student_ids'] ?? [];
    $course_ids = $_POST['course_ids'] ?? [];

    if(!empty($student_ids) && !empty($course_ids)){
        $enrollment_count = 0;
        $errors = [];
        
        // Prepare the insert statement
        $insert_stmt = $pdo->prepare("INSERT INTO enrollments (student_id, schedule_id) VALUES (?, ?)");
        
        foreach($student_ids as $student_id){
            foreach($course_ids as $course_id){
                // Get all schedules for this course
                $schedules_stmt = $pdo->prepare("SELECT schedule_id FROM schedule WHERE course_id = ?");
                $schedules_stmt->execute([$course_id]);
                $course_schedules = $schedules_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if(empty($course_schedules)){
                    $errors[] = "Course ID $course_id has no schedule entries.";
                    continue;
                }
                
                foreach($course_schedules as $schedule_id){
                    // Prevent duplicate enrollment
                    $check = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id = ? AND schedule_id = ?");
                    $check->execute([$student_id, $schedule_id]);
                    
                    if(!$check->fetch()){
                        try {
                            $insert_stmt->execute([$student_id, $schedule_id]);
                            $enrollment_count++;
                        } catch (PDOException $e) {
                            $errors[] = "Error enrolling student $student_id in schedule $schedule_id: " . $e->getMessage();
                        }
                    }
                }
            }
        }
        
        if($enrollment_count > 0){
            $message = "Successfully enrolled students in $enrollment_count schedule session(s)!";
            if(!empty($errors)){
                $message .= " Some issues: " . implode(" ", array_slice($errors, 0, 3));
            }
        } else {
            $message = "No new enrollments were added. ";
            if(!empty($errors)){
                $message .= "Issues: " . implode(" ", $errors);
            } else {
                $message .= "Students may already be enrolled in all selected courses.";
            }
        }
    } else {
        $message = "Please select at least one student and one course.";
    }
}

// Handle bulk unenroll selected
if(isset($_POST['unenroll_selected'])){
    $unenroll_ids = $_POST['unenroll_ids'] ?? [];
    if(!empty($unenroll_ids)){
        $placeholders = implode(',', array_fill(0, count($unenroll_ids), '?'));
        $del_stmt = $pdo->prepare("
            DELETE e FROM enrollments e
            JOIN schedule s ON e.schedule_id = s.schedule_id
            JOIN courses c ON s.course_id = c.course_id
            WHERE e.enrollment_id IN ($placeholders) AND c.department_id = ?
        ");
        $del_stmt->execute([...$unenroll_ids, $dept_id]);
        $message = count($unenroll_ids) . " student enrollment(s) removed successfully!";
    } else {
        $message = "No enrollments selected.";
    }
}

// Handle unenroll all enrollments in this department
if(isset($_POST['unenroll_all'])){
    $del_stmt = $pdo->prepare("
        DELETE e FROM enrollments e
        JOIN schedule s ON e.schedule_id = s.schedule_id
        JOIN courses c ON s.course_id = c.course_id
        WHERE c.department_id = ?
    ");
    $del_stmt->execute([$dept_id]);
    $message = "All enrollments have been removed for this department.";
}

// Fetch all unique years from students in this department
$years_stmt = $pdo->prepare("
    SELECT DISTINCT year 
    FROM users 
    WHERE role='student' AND department_id=? AND year IS NOT NULL AND year != ''
    ORDER BY 
        CASE 
            WHEN year LIKE 'E%' THEN 2  -- Put extension years after regular years
            ELSE 1
        END,
        CAST(
            CASE 
                WHEN year LIKE 'E%' THEN SUBSTRING(year, 2)
                ELSE year 
            END AS UNSIGNED
        )
");
$years_stmt->execute([$dept_id]);
$available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);

// If no year column exists or no years set, use default years
if(empty($available_years)){
    $available_years = [1, 2, 3, 4]; // Default years
}

// Get selected year from GET/POST or default to first year
$selected_year = $_GET['year'] ?? $_POST['year'] ?? ($available_years[0] ?? 1);

// Fetch students based on selected year
if($selected_year == 'all') {
    $students_stmt = $pdo->prepare("
        SELECT user_id, username, year 
        FROM users 
        WHERE role='student' AND department_id=? 
        ORDER BY 
            CASE 
                WHEN year LIKE 'E%' THEN 2
                ELSE 1
            END,
            CAST(
                CASE 
                    WHEN year LIKE 'E%' THEN SUBSTRING(year, 2)
                    ELSE year 
                END AS UNSIGNED
            ),
            username
    ");
    $students_stmt->execute([$dept_id]);
} else {
    $students_stmt = $pdo->prepare("
        SELECT user_id, username, year 
        FROM users 
        WHERE role='student' AND department_id=? AND year = ?
        ORDER BY username
    ");
    $students_stmt->execute([$dept_id, $selected_year]);
}
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch courses with schedule info
$courses_stmt = $pdo->prepare("
    SELECT c.course_id, c.course_name, 
           COUNT(s.schedule_id) as schedule_count
    FROM courses c
    LEFT JOIN schedule s ON c.course_id = s.course_id
    WHERE c.department_id = ?
    GROUP BY c.course_id, c.course_name
    ORDER BY c.course_name
");
$courses_stmt->execute([$dept_id]);
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch enrollments
$enrollments_stmt = $pdo->prepare("
    SELECT e.enrollment_id, 
           u.user_id as student_id,
           u.username AS student_name, 
           u.year as student_year,
           c.course_id,
           c.course_name, 
           s.day, 
           TIME_FORMAT(s.start_time, '%h:%i %p') as start_time,
           TIME_FORMAT(s.end_time, '%h:%i %p') as end_time
    FROM enrollments e
    JOIN users u ON e.student_id = u.user_id
    JOIN schedule s ON e.schedule_id = s.schedule_id
    JOIN courses c ON s.course_id = c.course_id
    WHERE c.department_id = ?
    ORDER BY 
        CASE 
            WHEN u.year LIKE 'E%' THEN 2
            ELSE 1
        END,
        CAST(
            CASE 
                WHEN u.year LIKE 'E%' THEN SUBSTRING(u.year, 2)
                ELSE u.year 
            END AS UNSIGNED
        ),
        u.username, s.day, s.start_time
");
$enrollments_stmt->execute([$dept_id]);
$enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group enrollments by year for display
$enrollments_by_year = [];
foreach($enrollments as $e){
    $year = $e['student_year'] ?? 'Unknown';
    if(!isset($enrollments_by_year[$year])){
        $enrollments_by_year[$year] = [];
    }
    $enrollments_by_year[$year][] = $e;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Enrollments | Department Head Portal</title>
<!-- Include Dark Mode -->
<?php include __DIR__ . '/../includes/darkmode.php'; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
/* ================= Updated Sidebar ================= */
.sidebar {
    position: fixed; 
    top:0; 
    left:0;
    width:250px; 
    height:100%;
    background: var(--bg-sidebar); 
    color: var(--text-sidebar);
    z-index:1100;
    transition: transform 0.3s ease;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.sidebar.hidden { 
    transform:translateX(-260px); 
}

/* Sidebar Content (scrollable) */
.sidebar-content {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 20px 0;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
}

/* Custom scrollbar for sidebar */
.sidebar-content::-webkit-scrollbar {
    width: 6px;
}

.sidebar-content::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 3px;
}

.sidebar-content::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

.sidebar-content::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

[data-theme="dark"] .sidebar-content::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
}

[data-theme="dark"] .sidebar-content::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Sidebar Profile */
.sidebar-profile {
    text-align: center;
    margin-bottom: 25px;
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    flex-shrink: 0; /* Prevent shrinking */
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

/* Sidebar Navigation */
.sidebar nav {
    display: flex;
    flex-direction: column;
}

.sidebar a { 
    display: flex; 
    align-items: center;
    gap: 10px;
    padding: 12px 20px; 
    color: var(--text-sidebar); 
    text-decoration: none; 
    transition: all 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.sidebar a:hover, .sidebar a.active { 
    background: #1abc9c; 
    color: white; 
    padding-left: 25px;
}

.sidebar a i {
    width: 20px;
    text-align: center;
}

/* Optional: Add fade effect at bottom when scrolling */
.sidebar-content::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 30px;
    background: linear-gradient(to bottom, transparent, var(--bg-sidebar));
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s;
}

.sidebar-content.scrolled::after {
    opacity: 1;
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

@media(max-width: 768px){
    .main-content {
        margin-left: 0;
        padding: 20px;
        padding-top: 80px;
    }
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
    padding:30px 50px;
    min-height:100vh;
    background:var(--bg-primary);
    color:var(--text-primary);
    transition: all 0.3s ease;
}

/* Header Styles */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px 0;
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
    background: var(--bg-card);
    padding: 12px 18px;
    border-radius: 12px;
    box-shadow: 0 4px 12px var(--shadow-color);
}

.user-info img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
}

/* Card Styles */
.card {
    background: var(--bg-card);
    border-radius: 15px;
    box-shadow: 0 6px 18px var(--shadow-color);
    margin-bottom: 25px;
    overflow: hidden;
}

.card-header {
    padding: 20px 25px;
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 15px 15px 0 0;
}

.card-header h3 {
    font-size: 1.4rem;
    font-weight: 600;
}

.card-body {
    padding: 25px;
}

/* Year Filter Styles */
.year-filter-container {
    background: var(--bg-secondary);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border: 1px solid var(--border-color);
}

.year-filter {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.year-filter label {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.year-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.year-btn {
    padding: 8px 16px;
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: var(--text-primary);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.year-btn:hover {
    background: var(--hover-color);
    border-color: var(--text-light);
}

.year-btn.active {
    background: #6366f1;
    color: white;
    border-color: #6366f1;
}

.year-btn.all {
    background: #10b981;
    color: white;
    border-color: #10b981;
}

.year-btn.all.active {
    background: #059669;
}

.year-btn.extension {
    background: #8b5cf6;
    color: white;
    border-color: #8b5cf6;
}

.year-btn.extension.active {
    background: #7c3aed;
}

.year-stats {
    margin-top: 10px;
    font-size: 0.9rem;
    color: var(--text-light);
}

.year-stats .badge {
    background: #e0e7ff;
    color: #4f46e5;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    margin-left: 5px;
}

/* Form Styles */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-primary);
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: var(--bg-input);
    color: var(--text-primary);
}

.form-control:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

select.form-control[multiple] {
    height: 200px;
}

.select-container {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.select-container > div {
    flex: 1;
    min-width: 300px;
}

/* Button Styles */
.btn {
    padding: 14px 24px;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: #6366f1;
    color: white;
}

.btn-primary:hover {
    background: #4f46e5;
    transform: translateY(-2px);
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
    transform: translateY(-2px);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.85rem;
}

/* Message Styles */
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

.message.info {
    background: var(--info-bg);
    color: var(--info-text);
    border: 1px solid var(--info-text);
}

/* Table Styles */
.table-container {
    overflow-x: auto;
    border-radius: 15px;
    box-shadow: 0 4px 12px var(--shadow-color);
    margin-top: 20px;
}

.enrollment-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-card);
}

.enrollment-table th,
.enrollment-table td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.enrollment-table th {
    background: var(--table-header);
    color: var(--text-primary);
    font-weight: 600;
}

.enrollment-table tr:last-child td {
    border-bottom: none;
}

.enrollment-table tr:hover {
    background: var(--hover-color);
}

.enrollment-table tr.selected {
    background-color: rgba(99, 102, 241, 0.1);
}

.checkbox-cell {
    width: 50px;
    text-align: center;
}

.enrollment-table input[type="checkbox"] {
    transform: scale(1.2);
    cursor: pointer;
}

.course-info {
    font-size: 0.85rem;
    color: var(--text-light);
    margin-top: 3px;
}

.student-year-badge {
    background: #e0e7ff;
    color: #4f46e5;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    margin-left: 8px;
}

.year-section {
    margin-bottom: 30px;
}

.year-section-header {
    background: var(--bg-secondary);
    padding: 12px 20px;
    border-radius: 10px 10px 0 0;
    border: 1px solid var(--border-color);
    border-bottom: none;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.year-section-header .badge {
    background: #6366f1;
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-light);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    display: block;
    color: var(--border-color);
}

.empty-state h3 {
    margin-bottom: 10px;
    color: var(--text-primary);
}

/* ================= Responsive ================= */
@media(max-width: 768px){
    .topbar { display:flex; }
    .sidebar { transform:translateX(-100%); }
    .sidebar.active { transform:translateX(0); }
    .main-content { margin-left:0; padding: 20px; padding-top: 80px; }
    .header { flex-direction: column; gap: 15px; align-items: flex-start; }
    .header h1 { font-size: 1.8rem; }
    .select-container { flex-direction: column; }
    .select-container > div { min-width: auto; }
    .year-buttons { justify-content: center; }
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Manage Enrollments</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

   <!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-content" id="sidebarContent">
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
</div>

<!-- Overlay for Mobile -->
<div class="overlay" onclick="toggleSidebar()"></div>
    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Manage Enrollments</h1>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile">
                <div>
                    <div><?= htmlspecialchars($user['username'] ?? 'User') ?></div>
                    <small>Department Head</small>
                </div>
            </div>
        </div>

        <?php if($message): ?>
            <div class="message <?= 
                strpos($message, 'Successfully') !== false ? 'success' : 
                (strpos($message, 'Please') !== false ? 'warning' : 
                (strpos($message, 'No') !== false ? 'info' : 'error')) 
            ?>">
                <i class="fas fa-<?= 
                    strpos($message, 'Successfully') !== false ? 'check-circle' : 
                    (strpos($message, 'Please') !== false ? 'exclamation-triangle' : 
                    (strpos($message, 'No') !== false ? 'info-circle' : 'exclamation-circle')) 
                ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Enrollment Form Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> Enroll Students</h3>
            </div>
            <div class="card-body">
                <!-- Year Filter -->
                <div class="year-filter-container">
                    <div class="year-filter">
                        <label>Filter Students by Year:</label>
                        <div class="year-buttons">
                            <button type="button" class="year-btn all <?= ($selected_year == 'all') ? 'active' : '' ?>" onclick="filterStudents('all')">
                                All Years
                            </button>
                            <?php foreach($available_years as $year): ?>
                                <?php 
                                    // Check if this is an extension year (starts with E)
                                    $is_extension = (is_string($year) && strpos($year, 'E') === 0);
                                    $display_year = $is_extension ? 'E' . substr($year, 1) : $year;
                                    $year_class = $is_extension ? 'extension' : '';
                                ?>
                                <button type="button" class="year-btn <?= $year_class ?> <?= ($selected_year == $year) ? 'active' : '' ?>" 
                                        onclick="filterStudents('<?= htmlspecialchars($year, ENT_QUOTES) ?>')">
                                    <?php if($is_extension): ?>
                                        E<?= substr($year, 1) ?>
                                    <?php else: ?>
                                        Year <?= $display_year ?>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="year-stats">
                        Showing <?= count($students) ?> student(s) from 
                        <?php 
                            if($selected_year == 'all') {
                                echo 'all years';
                            } elseif(is_string($selected_year) && strpos($selected_year, 'E') === 0) {
                                echo 'Extension Year ' . substr($selected_year, 1);
                            } else {
                                echo 'Year ' . $selected_year;
                            }
                        ?>
                        <span class="badge"><?= count($students) ?></span>
                    </div>
                </div>

                <form method="POST" id="enrollmentForm">
                    <input type="hidden" name="year" id="selectedYear" value="<?= $selected_year ?>">
                    
                    <div class="select-container">
                        <div class="form-group">
                            <label for="student_ids">Select Students:</label>
                            <select name="student_ids[]" id="student_ids" class="form-control" multiple size="10" required>
                                <?php if(count($students) > 0): ?>
                                    <?php foreach($students as $s): ?>
                                        <option value="<?= (int)$s['user_id'] ?>">
                                            <?= htmlspecialchars($s['username']) ?>
                                            <?php if(isset($s['year']) && $s['year'] !== ''): ?>
                                                <?php if(is_string($s['year']) && strpos($s['year'], 'E') === 0): ?>
                                                    <span style="color: var(--text-light); font-size: 0.9em;">(E<?= substr($s['year'], 1) ?>)</span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-light); font-size: 0.9em;">(Year <?= $s['year'] ?>)</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option disabled>
                                        <?php 
                                            if($selected_year == 'all') {
                                                echo 'No students found';
                                            } elseif(is_string($selected_year) && strpos($selected_year, 'E') === 0) {
                                                echo 'No extension students found for E' . substr($selected_year, 1);
                                            } else {
                                                echo 'No students found for Year ' . $selected_year;
                                            }
                                        ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="course_ids">Select Courses:</label>
                            <select name="course_ids[]" id="course_ids" class="form-control" multiple size="10" required>
                                <?php foreach($courses as $c): ?>
                                    <option value="<?= (int)$c['course_id'] ?>">
                                        <?= htmlspecialchars($c['course_name']) ?>
                                        <span style="color: var(--text-light); font-size: 0.9em;">
                                            (<?= (int)$c['schedule_count'] ?> schedule<?= $c['schedule_count'] != 1 ? 's' : '' ?>)
                                        </span>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px;">
                        <button type="button" class="btn btn-sm" onclick="selectAllStudents()">
                            <i class="fas fa-check-double"></i> Select All Students
                        </button>
                        <button type="button" class="btn btn-sm" onclick="deselectAllStudents()">
                            <i class="fas fa-times"></i> Deselect All
                        </button>
                    </div>
                    
                    <button type="submit" name="enroll" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Enroll Selected Students
                    </button>
                    <div class="course-info">
                        <i class="fas fa-info-circle"></i> 
                        Hold Ctrl/Cmd to select multiple students and courses. 
                        Students will be enrolled in all schedule sessions for selected courses.
                        Courses with 0 schedules cannot be enrolled in.
                    </div>
                </form>
            </div>
        </div>

        <!-- Current Enrollments Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Current Enrollments (<?= count($enrollments) ?>)</h3>
            </div>
            <div class="card-body">
                <?php if(count($enrollments) > 0): ?>
                    <form method="POST">
                        <?php foreach($enrollments_by_year as $year => $year_enrollments): ?>
                            <div class="year-section">
                                <div class="year-section-header">
                                    <span>
                                        <?php if(is_string($year) && strpos($year, 'E') === 0): ?>
                                            Extension Year <?= substr($year, 1) ?> Students
                                        <?php else: ?>
                                            Year <?= $year ?> Students
                                        <?php endif; ?>
                                    </span>
                                    <span class="badge"><?= count($year_enrollments) ?> enrollment(s)</span>
                                </div>
                                <div class="table-container">
                                    <table class="enrollment-table" role="table" aria-label="Enrollments for Year <?= $year ?>">
                                        <thead>
                                            <tr>
                                                <th class="checkbox-cell">
                                                    <input type="checkbox" class="select-year-checkbox" data-year="<?= $year ?>">
                                                </th>
                                                <th>Student</th>
                                                <th>Course</th>
                                                <th>Day</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($year_enrollments as $e): ?>
                                                <tr>
                                                    <td class="checkbox-cell">
                                                        <input type="checkbox" name="unenroll_ids[]" value="<?= (int)$e['enrollment_id'] ?>" class="enrollment-checkbox">
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($e['student_name']) ?>
                                                        <span class="student-year-badge">
                                                            <?php if(is_string($e['student_year']) && strpos($e['student_year'], 'E') === 0): ?>
                                                                E<?= substr($e['student_year'], 1) ?>
                                                            <?php else: ?>
                                                                Year <?= $e['student_year'] ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($e['course_name']) ?>
                                                        <div class="course-info">ID: <?= (int)$e['course_id'] ?></div>
                                                    </td>
                                                    <td><?= htmlspecialchars($e['day']) ?></td>
                                                    <td><?= htmlspecialchars($e['start_time']) ?> - <?= htmlspecialchars($e['end_time']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div style="margin-top:20px; display:flex; gap:15px; flex-wrap:wrap;">
                            <button type="submit" name="unenroll_selected" class="btn btn-danger" id="unenrollBtn" disabled>
                                <i class="fas fa-user-minus"></i> Unenroll Selected
                            </button>
                            <button type="submit" name="unenroll_all" class="btn btn-danger" onclick="return confirm('Are you sure you want to unenroll ALL students from ALL courses? This action cannot be undone.')">
                                <i class="fas fa-trash"></i> Unenroll All
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Enrollments Yet</h3>
                        <p>Select students and courses above to create enrollments.</p>
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

        // Filter students by year
        function filterStudents(year) {
            // Handle special cases
            if(year === 'all') {
                document.getElementById('selectedYear').value = 'all';
            } else {
                document.getElementById('selectedYear').value = year;
            }
            document.getElementById('enrollmentForm').submit();
        }

        // Select all students in dropdown
        function selectAllStudents() {
            const studentSelect = document.getElementById('student_ids');
            for(let i = 0; i < studentSelect.options.length; i++) {
                studentSelect.options[i].selected = true;
            }
            // Update visual feedback
            studentSelect.style.borderColor = '#6366f1';
            studentSelect.style.backgroundColor = 'rgba(99, 102, 241, 0.1)';
        }

        // Deselect all students
        function deselectAllStudents() {
            const studentSelect = document.getElementById('student_ids');
            for(let i = 0; i < studentSelect.options.length; i++) {
                studentSelect.options[i].selected = false;
            }
            // Update visual feedback
            studentSelect.style.borderColor = 'var(--border-color)';
            studentSelect.style.backgroundColor = 'var(--bg-input)';
        }

        // Select/Deselect all checkboxes in a year section
        document.addEventListener('DOMContentLoaded', function() {
            // Year section select all
            document.querySelectorAll('.select-year-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const year = this.dataset.year;
                    const checkboxes = document.querySelectorAll(`.enrollment-checkbox`);
                    const yearCheckboxes = Array.from(checkboxes).filter(cb => {
                        const row = cb.closest('tr');
                        const yearBadge = row.querySelector('.student-year-badge');
                        if(yearBadge) {
                            if(year.startsWith('E')) {
                                return yearBadge.textContent.includes('E' + year.substring(1));
                            } else {
                                return yearBadge.textContent.includes('Year ' + year);
                            }
                        }
                        return false;
                    });
                    
                    yearCheckboxes.forEach(cb => {
                        cb.checked = this.checked;
                        cb.closest('tr').classList.toggle('selected', this.checked);
                    });
                    updateUnenrollButton();
                });
            });

            // Global select all
            const selectAllCheckbox = document.getElementById('select-all');
            if(selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function(){
                    const checked = this.checked;
                    document.querySelectorAll('.enrollment-checkbox').forEach(cb => {
                        cb.checked = checked;
                        cb.closest('tr').classList.toggle('selected', checked);
                    });
                    updateUnenrollButton();
                });
            }

            // Individual checkbox change handler
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('enrollment-checkbox')) {
                    e.target.closest('tr').classList.toggle('selected', e.target.checked);
                    updateUnenrollButton();
                }
            });

            // Update unenroll button state
            function updateUnenrollButton() {
                const checked = document.querySelectorAll('.enrollment-checkbox:checked');
                const unenrollBtn = document.getElementById('unenrollBtn');
                if (unenrollBtn) {
                    unenrollBtn.disabled = checked.length === 0;
                    unenrollBtn.innerHTML = checked.length > 0 ? 
                        `<i class="fas fa-user-minus"></i> Unenroll Selected (${checked.length})` : 
                        '<i class="fas fa-user-minus"></i> Unenroll Selected';
                }
            }

            // Set active state for current page
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.sidebar a');
            
            navLinks.forEach(link => {
                const linkPage = link.getAttribute('href');
                if (linkPage === currentPage) {
                    link.classList.add('active');
                }
            });

            // Initialize unenroll button state
            updateUnenrollButton();
            
            // Add visual feedback for multiple select
            const multiSelects = document.querySelectorAll('select[multiple]');
            multiSelects.forEach(select => {
                select.addEventListener('change', function() {
                    const selectedCount = Array.from(this.selectedOptions).length;
                    if(selectedCount > 0) {
                        this.style.borderColor = '#6366f1';
                        this.style.backgroundColor = 'rgba(99, 102, 241, 0.1)';
                    } else {
                        this.style.borderColor = 'var(--border-color)';
                        this.style.backgroundColor = 'var(--bg-input)';
                    }
                });
            });
        });
    </script>
    
    <!-- Optional: Add Font Awesome JS if not already loaded -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>