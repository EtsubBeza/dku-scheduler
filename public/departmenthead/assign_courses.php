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
$message = "";

// Function to determine message type
function getMessageType($message) {
    if (stripos($message, 'success') !== false || stripos($message, 'assigned') !== false || stripos($message, 'unassigned') !== false) {
        return 'success';
    } elseif (stripos($message, 'warning') !== false || stripos($message, 'approaching') !== false || stripos($message, 'already assigned') !== false) {
        return 'warning';
    } elseif (stripos($message, 'error') !== false || stripos($message, 'exceeded') !== false || stripos($message, 'failed') !== false) {
        return 'error';
    } else {
        return 'info';
    }
}

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

// Handle assignment form submission
if(isset($_POST['assign_course'])){
    
    $course_id = $_POST['course_id'];
    $user_id = $_POST['user_id'];
    $semester = $_POST['semester'];
    $academic_year = $_POST['academic_year'];

    // Prevent duplicate assignment
    $check = $pdo->prepare("
        SELECT ca.* 
        FROM course_assignments ca
        WHERE ca.course_id = ? 
        AND ca.user_id = ? 
        AND ca.semester = ? 
        AND ca.academic_year = ?
    ");
    $check->execute([$course_id, $user_id, $semester, $academic_year]);
    
    if($check->fetch()){
        $message = "<i class='fas fa-exclamation-triangle'></i> This course is already assigned to the selected instructor for the $semester $academic_year semester.";
    } else {
        // Get course credit hours
        $course_credit_stmt = $pdo->prepare("SELECT credit_hours, course_code, course_name FROM courses WHERE course_id = ?");
        $course_credit_stmt->execute([$course_id]);
        $course_data = $course_credit_stmt->fetch();
        $course_credit = $course_data['credit_hours'] ?? 0;
        $course_code = $course_data['course_code'] ?? '';
        $course_name = $course_data['course_name'] ?? '';
        
        // Get instructor info
        $instructor_stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE user_id = ?");
        $instructor_stmt->execute([$user_id]);
        $instructor_data = $instructor_stmt->fetch();
        $instructor_name = !empty($instructor_data['full_name']) ? $instructor_data['full_name'] : $instructor_data['username'];
        
        // Check instructor's current workload for THIS semester/year
        $workload_check = $pdo->prepare("
            SELECT SUM(iw.credit_hours) as current_load 
            FROM instructor_workload iw 
            WHERE iw.instructor_id = ? 
            AND iw.semester = ? 
            AND iw.academic_year = ?
        ");
        $workload_check->execute([$user_id, $semester, $academic_year]);
        $current_load = $workload_check->fetchColumn() ?? 0;
        
        $new_total = $current_load + $course_credit;
        
        // Check workload limits
        if($new_total > 12) {
            $message = "<i class='fas fa-exclamation-circle'></i> <strong>Workload Exceeded!</strong> Assigning '$course_code' to $instructor_name would overload them ({$new_total}/12 credits). Maximum limit is 12 credit hours.";
        } else {
            // Proceed with assignment
            $pdo->beginTransaction();
            try {
                // Insert into course_assignments
                $stmt = $pdo->prepare("INSERT INTO course_assignments (course_id, user_id, semester, academic_year) VALUES (?, ?, ?, ?)");
                $stmt->execute([$course_id, $user_id, $semester, $academic_year]);
                
                // Track in workload table
                $workload_stmt = $pdo->prepare("INSERT INTO instructor_workload (instructor_id, course_id, credit_hours, semester, academic_year) VALUES (?, ?, ?, ?, ?)");
                $workload_stmt->execute([$user_id, $course_id, $course_credit, $semester, $academic_year]);
                
                $pdo->commit();
                
                // Set appropriate message based on workload
                if($new_total >= 9) {
                    $message = "<i class='fas fa-exclamation-triangle'></i> <strong>Warning:</strong> '$course_code' assigned to $instructor_name. They now have {$new_total}/12 credits (approaching maximum).";
                } else {
                    $message = "<i class='fas fa-check-circle'></i> <strong>Success!</strong> '$course_code' has been assigned to $instructor_name for $semester $academic_year.";
                }
                
            } catch(Exception $e) {
                $pdo->rollBack();
                $message = "<i class='fas fa-times-circle'></i> <strong>Error:</strong> " . $e->getMessage();
            }
        }
    }
}

// Handle delete assignment
if(isset($_GET['delete'])){
    $assignment_id = $_GET['delete'];
    
    // Get assignment details to remove from workload tracking
    $get_assignment = $pdo->prepare("
        SELECT ca.course_id, ca.user_id, ca.semester, ca.academic_year, 
               c.course_code, c.course_name, u.username, u.full_name
        FROM course_assignments ca
        JOIN courses c ON ca.course_id = c.course_id
        JOIN users u ON ca.user_id = u.user_id
        WHERE ca.id = ?
    ");
    $get_assignment->execute([$assignment_id]);
    $assignment = $get_assignment->fetch();
    
    if($assignment) {
        $pdo->beginTransaction();
        try {
            // Delete from course_assignments
            $stmt = $pdo->prepare("DELETE FROM course_assignments WHERE id=?");
            $stmt->execute([$assignment_id]);
            
            // Also delete from instructor_workload
            $workload_del = $pdo->prepare("
                DELETE FROM instructor_workload 
                WHERE instructor_id = ? 
                AND course_id = ? 
                AND semester = ? 
                AND academic_year = ?
            ");
            $workload_del->execute([
                $assignment['user_id'], 
                $assignment['course_id'], 
                $assignment['semester'], 
                $assignment['academic_year']
            ]);
            
            $pdo->commit();
            
            // Get instructor name
            $instructor_name = !empty($assignment['full_name']) ? $assignment['full_name'] : $assignment['username'];
            
            // Redirect with success message
            header("Location: assign_courses.php?msg=" . urlencode("<i class='fas fa-check-circle'></i> <strong>Success!</strong> '{$assignment['course_code']}' has been unassigned from $instructor_name."));
            exit;
        } catch(Exception $e) {
            $pdo->rollBack();
            $message = "<i class='fas fa-times-circle'></i> <strong>Error:</strong> Failed to delete assignment: " . $e->getMessage();
        }
    } else {
        $message = "<i class='fas fa-exclamation-triangle'></i> <strong>Error:</strong> Assignment not found.";
    }
}

// Check for success message from redirect
if(isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
}

// Get filter values or use defaults
$selected_semester = $_GET['semester'] ?? '';
$selected_year = $_GET['year'] ?? '';

// Fetch ALL instructor workloads (for all semesters) for the summary
$all_instructors_summary_stmt = $pdo->prepare("
    SELECT 
        u.user_id,
        u.username,
        u.full_name,
        COALESCE(NULLIF(TRIM(u.full_name), ''), u.username) as display_name,
        COALESCE(SUM(iw.credit_hours), 0) as total_credits,
        COUNT(DISTINCT iw.course_id) as total_courses
    FROM users u
    LEFT JOIN instructor_workload iw ON u.user_id = iw.instructor_id
    LEFT JOIN courses c ON iw.course_id = c.course_id
    WHERE u.role = 'instructor' 
        AND u.department_id = ?
    GROUP BY u.user_id, u.username, u.full_name
    ORDER BY total_credits DESC, display_name ASC
");

$all_instructors_summary_stmt->execute([$dept_id]);
$instructor_summary = $all_instructors_summary_stmt->fetchAll();

// Fetch courses and instructors for dropdown
$courses = $pdo->prepare("SELECT * FROM courses WHERE department_id=? ORDER BY course_name ASC");
$courses->execute([$dept_id]);
$courses = $courses->fetchAll();

$instructors = $pdo->prepare("SELECT user_id, username, full_name, email FROM users WHERE role='instructor' AND department_id=? ORDER BY COALESCE(NULLIF(TRIM(full_name), ''), username) ASC");
$instructors->execute([$dept_id]);
$instructors = $instructors->fetchAll();

// Fetch ALL assignments
$assignments_stmt = $pdo->prepare("
    SELECT ca.id, c.course_name, c.course_code, c.credit_hours, 
           u.full_name, u.username, ca.semester, ca.academic_year
    FROM course_assignments ca
    JOIN courses c ON ca.course_id = c.course_id
    JOIN users u ON ca.user_id = u.user_id
    WHERE c.department_id = ?
    ORDER BY ca.academic_year DESC, 
        CASE ca.semester 
            WHEN '1st Semester' THEN 1 
            WHEN '2nd Semester' THEN 2 
            WHEN 'Summer' THEN 3 
            ELSE 4 
        END,
        c.course_name
");
$assignments_stmt->execute([$dept_id]);
$all_assignments = $assignments_stmt->fetchAll();

// Filter assignments if filter is set
$filtered_assignments = $all_assignments;
if($selected_semester) {
    $filtered_assignments = array_filter($filtered_assignments, function($a) use ($selected_semester) {
        return $a['semester'] == $selected_semester;
    });
}
if($selected_year) {
    $filtered_assignments = array_filter($filtered_assignments, function($a) use ($selected_year) {
        return $a['academic_year'] == $selected_year;
    });
}

// Calculate workload statistics for ALL semesters
$overloaded_instructors = [];
$warning_count = 0;
$overloaded_count = 0;
$normal_count = 0;
$no_load_count = 0;
$total_credits = 0;
$total_instructors = count($instructor_summary);

// Count workload distribution for chart (ALL semesters)
$chart_low_load = 0;     // 1-6 credits
$chart_warning_load = 0; // 7-9 credits  
$chart_high_load = 0;    // 10-12 credits

foreach($instructor_summary as $iw) {
    $credits = $iw['total_credits'] ?? 0;
    $total_credits += $credits;
    
    // Count for charts
    if($credits > 0 && $credits <= 6) {
        $chart_low_load++;
    } elseif($credits >= 7 && $credits <= 9) {
        $chart_warning_load++;
    } elseif($credits >= 10 && $credits < 12) {
        $chart_high_load++;
    }
    
    // Count for stats
    if($credits >= 12) {
        $overloaded_count++;
        $overloaded_instructors[] = $iw['display_name'] . " (" . $credits . "/12 credits)";
    } elseif($credits >= 9) {
        $warning_count++;
    } elseif($credits > 0) {
        $normal_count++;
    } else {
        $no_load_count++;
    }
}

$avg_workload = $total_instructors > 0 ? round($total_credits / $total_instructors, 1) : 0;

// Get unique semesters and years for filter dropdowns
$semesters = [];
$years = [];
foreach($all_assignments as $assignment) {
    if(!in_array($assignment['semester'], $semesters)) {
        $semesters[] = $assignment['semester'];
    }
    if(!in_array($assignment['academic_year'], $years)) {
        $years[] = $assignment['academic_year'];
    }
}
sort($years);
rsort($years); // Show most recent first
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<title>Assign Courses | Department Head Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Chart.js for workload visualization -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

/* Workload Stats */
.workload-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    flex: 1;
    min-width: 150px;
    background: var(--bg-secondary);
    padding: 15px;
    border-radius: 10px;
    border-left: 4px solid #6366f1;
}

.stat-card.warning {
    border-left-color: #f59e0b;
    background: #fef3c7;
}

.stat-card.danger {
    border-left-color: #ef4444;
    background: #fee2e2;
}

.stat-card h4 {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-bottom: 5px;
}

.stat-card .value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-primary);
}

.stat-card .subtext {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-top: 5px;
}

/* Charts Container */
.charts-container {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin: 25px 0;
}

.chart-box {
    flex: 1;
    min-width: 300px;
    background: var(--bg-card);
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 8px var(--shadow-color);
}

.chart-box h4 {
    margin-bottom: 15px;
    color: var(--text-primary);
    font-size: 1rem;
}

/* Instructor Workload Preview */
.workload-preview {
    margin-top: 25px;
    padding: 15px;
    background: var(--bg-secondary);
    border-radius: 10px;
    border: 1px solid var(--border-color);
}

.workload-preview h4 {
    margin-bottom: 15px;
    color: var(--text-primary);
}

.instructor-workload-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: var(--bg-card);
    margin-bottom: 8px;
    border-radius: 8px;
    border-left: 4px solid #10b981;
    transition: transform 0.2s ease;
}

.instructor-workload-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px var(--shadow-color);
}

.instructor-workload-item.warning {
    border-left-color: #f59e0b;
}

.instructor-workload-item.danger {
    border-left-color: #ef4444;
}

.instructor-workload-item.no-load {
    border-left-color: #9ca3af;
    opacity: 0.8;
}

.progress-bar {
    height: 8px;
    background: var(--progress-bg);
    border-radius: 4px;
    flex-grow: 1;
    margin: 0 15px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
    background: #10b981;
}

.progress-fill.warning {
    background: #f59e0b;
}

.progress-fill.danger {
    background: #ef4444;
}

.progress-fill.no-load {
    background: #9ca3af;
}

/* Workload Alert */
.workload-alert {
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.workload-alert.warning {
    background: #fef3c7;
    border: 1px solid #fde68a;
    color: #92400e;
}

.workload-alert.danger {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
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

.form-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.form-row .form-group {
    flex: 1;
    min-width: 250px;
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
    border: 1px solid var(--success-border);
}

.message.error {
    background: var(--error-bg);
    color: var(--error-text);
    border: 1px solid var(--error-border);
}

.message.warning {
    background: var(--warning-bg);
    color: var(--warning-text);
    border: 1px solid var(--warning-border);
}

.message.info {
    background: var(--info-bg);
    color: var(--info-text);
    border: 1px solid var(--info-border);
}

/* Table Styles */
.table-container {
    overflow-x: auto;
    border-radius: 15px;
    box-shadow: 0 4px 12px var(--shadow-color);
    margin-top: 20px;
}

.assignment-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-card);
}

.assignment-table th,
.assignment-table td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
}

.assignment-table th {
    background: var(--table-header);
    color: var(--text-primary);
    font-weight: 600;
}

.assignment-table tr:last-child td {
    border-bottom: none;
}

.assignment-table tr:hover {
    background: var(--hover-color);
}

.action-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.action-btn.delete {
    background: #ef4444;
    color: white;
}

.action-btn.delete:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.empty-state {
    text-align: center;
    padding: 50px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3.5rem;
    margin-bottom: 20px;
    color: var(--border-color);
}

.empty-state h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: var(--text-primary);
}

/* Badge styles */
.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-success {
    background: var(--success-bg);
    color: var(--success-text);
}

.badge-warning {
    background: var(--warning-bg);
    color: var(--warning-text);
}

.badge-danger {
    background: var(--error-bg);
    color: var(--error-text);
}

.badge-secondary {
    background: #e5e7eb;
    color: #374151;
}

/* Filter Styles */
.filter-section {
    background: var(--bg-secondary);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.filter-form {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-form select,
.filter-form input {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-card);
    color: var(--text-primary);
}

.filter-btn {
    padding: 8px 16px;
    background: #6366f1;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.filter-btn:hover {
    background: #4f46e5;
}

.filter-btn.reset {
    background: #6b7280;
}

.filter-btn.reset:hover {
    background: #4b5563;
}

/* ================= Responsive ================= */
@media(max-width: 768px){
    .topbar { display:flex; }
    .sidebar { transform:translateX(-100%); }
    .sidebar.active { transform:translateX(0); }
    .main-content { margin-left:0; padding: 20px; padding-top: 80px; }
    .header { flex-direction: column; gap: 15px; align-items: flex-start; }
    .header h1 { font-size: 1.8rem; }
    .form-row { flex-direction: column; }
    .form-row .form-group { min-width: auto; }
    .charts-container { flex-direction: column; }
    .chart-box { min-width: 100%; }
    .workload-stats { flex-direction: column; }
    .instructor-workload-item {
        flex-direction: column;
        gap: 10px;
    }
    .instructor-workload-item > div {
        width: 100%;
        text-align: center;
    }
    .progress-bar {
        margin: 10px 0;
        width: 100%;
    }
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Assign Courses</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

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
        <div class="header">
            <h1>Assign Courses to Instructors</h1>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile">
                <div>
                    <div><?= htmlspecialchars($user['username'] ?? 'User') ?></div>
                    <small>Department Head</small>
                </div>
            </div>
        </div>

        <?php if($message): ?>
            <div class="message <?= getMessageType($message) ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Workload Statistics Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie"></i> Workload Monitoring Dashboard</h3>
                <small>Showing: Overall Summary (All Semesters)</small>
            </div>
            <div class="card-body">
                <?php if(!empty($overloaded_instructors)): ?>
                    <div class="workload-alert danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <strong>Overload Alert!</strong>
                            <p>The following instructors have exceeded 12 credit hours: <?= implode(', ', $overloaded_instructors) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Workload Stats -->
                <div class="workload-stats">
                    <div class="stat-card">
                        <h4>Total Instructors</h4>
                        <div class="value"><?= $total_instructors ?></div>
                        <div class="subtext">In department</div>
                    </div>
                    
                    <div class="stat-card">
                        <h4>Avg Workload</h4>
                        <div class="value"><?= $avg_workload ?></div>
                        <div class="subtext">Credit hours/instructor</div>
                    </div>
                    
                    <div class="stat-card <?= $warning_count > 0 ? 'warning' : '' ?>">
                        <h4>Near Limit</h4>
                        <div class="value"><?= $warning_count ?></div>
                        <div class="subtext">9-11 credits</div>
                    </div>
                    
                    <div class="stat-card <?= $overloaded_count > 0 ? 'danger' : '' ?>">
                        <h4>Overloaded</h4>
                        <div class="value"><?= $overloaded_count ?></div>
                        <div class="subtext">≥12 credits</div>
                    </div>

                    <div class="stat-card">
                        <h4>No Load</h4>
                        <div class="value"><?= $no_load_count ?></div>
                        <div class="subtext">0 credits assigned</div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="charts-container">
                    <div class="chart-box">
                        <h4><i class="fas fa-chart-bar"></i> Workload Distribution</h4>
                        <canvas id="workloadChart" style="width: 100%; height: 200px;"></canvas>
                    </div>
                    
                    <div class="chart-box">
                        <h4><i class="fas fa-chart-pie"></i> Status Overview</h4>
                        <canvas id="statusChart" style="width: 100%; height: 200px;"></canvas>
                    </div>
                </div>

                <!-- Instructor Workload Preview -->
                <div class="workload-preview">
                    <h4><i class="fas fa-users"></i> Instructor Workload Details (All Semesters Combined)</h4>
                    <?php if(!empty($instructor_summary)): ?>
                        <?php foreach($instructor_summary as $iw): ?>
                            <?php
                            $credits = $iw['total_credits'] ?? 0;
                            $percentage = min(100, ($credits / 12) * 100);
                            $display_name = $iw['display_name'] ?? 'Unknown Instructor';
                            
                            // Determine status
                            if($credits >= 12) {
                                $status_text = 'Overloaded';
                                $status_class = 'danger';
                                $icon = 'exclamation-circle';
                                $item_class = 'danger';
                                $progress_class = 'danger';
                            } elseif($credits >= 9) {
                                $status_text = 'Near Limit';
                                $status_class = 'warning';
                                $icon = 'exclamation-triangle';
                                $item_class = 'warning';
                                $progress_class = 'warning';
                            } elseif($credits > 0) {
                                $status_text = 'Normal';
                                $status_class = 'success';
                                $icon = 'check-circle';
                                $item_class = '';
                                $progress_class = '';
                            } else {
                                $status_text = 'No Load';
                                $status_class = 'secondary';
                                $icon = 'info-circle';
                                $item_class = 'no-load';
                                $progress_class = 'no-load';
                            }
                            ?>
                            
                            <div class="instructor-workload-item <?= $item_class ?>">
                                <!-- Left: Instructor Info -->
                                <div style="min-width: 200px;">
                                    <div style="font-weight: bold; font-size: 1rem; margin-bottom: 5px; color: var(--text-primary);">
                                        <?= htmlspecialchars($display_name) ?>
                                    </div>
                                    
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);">
                                        <div style="margin-bottom: 3px;">
                                            <i class="fas fa-book" style="margin-right: 5px;"></i>
                                            <?= $iw['total_courses'] ?? 0 ?> course(s) total
                                        </div>
                                        
                                        <!-- Get semester-specific details for this instructor -->
                                        <?php
                                        $semester_details_stmt = $pdo->prepare("
                                            SELECT iw.semester, iw.academic_year, SUM(iw.credit_hours) as semester_credits
                                            FROM instructor_workload iw
                                            WHERE iw.instructor_id = ?
                                            GROUP BY iw.semester, iw.academic_year
                                            ORDER BY iw.academic_year DESC, 
                                                CASE iw.semester 
                                                    WHEN '1st Semester' THEN 1 
                                                    WHEN '2nd Semester' THEN 2 
                                                    WHEN 'Summer' THEN 3 
                                                    ELSE 4 
                                                END
                                        ");
                                        $semester_details_stmt->execute([$iw['user_id']]);
                                        $semester_details = $semester_details_stmt->fetchAll();
                                        
                                        if(!empty($semester_details)): 
                                        ?>
                                            <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 3px;">
                                                <i class="fas fa-calendar-alt" style="margin-right: 5px;"></i>
                                                <?php 
                                                $detail_strings = [];
                                                foreach($semester_details as $detail) {
                                                    $detail_strings[] = $detail['semester'] . ' ' . $detail['academic_year'] . ' (' . $detail['semester_credits'] . 'cr)';
                                                }
                                                echo htmlspecialchars(implode(', ', $detail_strings));
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Middle: Workload Status -->
                                <div style="min-width: 100px; text-align: center;">
                                    <div style="font-size: 1.3rem; font-weight: bold; color: var(--text-primary);">
                                        <?= $credits ?>/12
                                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px;">
                                            (<?= round($percentage) ?>%)
                                        </div>
                                    </div>
                                    <span class="badge badge-<?= $status_class ?>" 
                                          style="display: inline-block; margin-top: 5px; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem;">
                                        <i class="fas fa-<?= $icon ?>"></i> <?= $status_text ?>
                                    </span>
                                </div>
                                
                                <!-- Right: Progress Bar -->
                                <div class="progress-bar">
                                    <div class="progress-fill <?= $progress_class ?>" style="width: <?= $percentage ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px; color: var(--text-secondary);">
                            <i class="fas fa-user-graduate" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.5;"></i>
                            <p>No instructor workload data available.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Assignment Form Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-book"></i> Assign Course to Instructor</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="assignForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="course_id">Select Course:</label>
                            <select name="course_id" id="course_id" class="form-control" required>
                                <option value="">-- Select Course --</option>
                                <?php foreach($courses as $course): ?>
                                    <?php 
                                    $course_credits = $course['credit_hours'];
                                    $course_info = htmlspecialchars($course['course_code'] . ' - ' . $course['course_name'] . " ({$course_credits} credits)");
                                    ?>
                                    <option value="<?= $course['course_id'] ?>">
                                        <?= $course_info ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="user_id">Select Instructor:</label>
                            <select name="user_id" id="user_id" class="form-control" required>
                                <option value="">-- Select Instructor --</option>
                                <?php foreach($instructors as $inst): ?>
                                    <?php 
                                    $displayName = !empty(trim($inst['full_name'])) ? $inst['full_name'] : $inst['username'];
                                    ?>
                                    <option value="<?= $inst['user_id'] ?>">
                                        <?= htmlspecialchars($displayName . ' (' . $inst['email'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="semester">Semester:</label>
                            <select name="semester" id="semester" class="form-control" required>
                                <option value="">-- Select Semester --</option>
                                <option value="1st Semester">1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                                <option value="Summer">Summer</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="academic_year">Academic Year:</label>
                            <input type="text" name="academic_year" id="academic_year" class="form-control" required 
                                   placeholder="e.g., 2024-2025" value="2024-2025">
                        </div>
                    </div>

                    <button type="submit" name="assign_course" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Assign Course
                    </button>
                    
                    <button type="button" class="btn" style="background: #6b7280; color: white; margin-left: 10px;" onclick="resetForm()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </form>
            </div>
        </div>

        <!-- Current Assignments Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Current Assignments</h3>
            </div>
            <div class="card-body">
                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="filter-form">
                        <select name="semester" class="form-control">
                            <option value="">All Semesters</option>
                            <?php foreach($semesters as $sem): ?>
                                <option value="<?= htmlspecialchars($sem) ?>" <?= ($selected_semester == $sem) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sem) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="year" class="form-control">
                            <option value="">All Years</option>
                            <?php foreach($years as $year): ?>
                                <option value="<?= htmlspecialchars($year) ?>" <?= ($selected_year == $year) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($year) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" class="filter-btn">Filter</button>
                        <a href="assign_courses.php" class="filter-btn reset">Reset</a>
                    </form>
                </div>

                <?php if($all_assignments): ?>
                    <div class="table-container">
                        <table class="assignment-table">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Name</th>
                                    <th>Credits</th>
                                    <th>Instructor</th>
                                    <th>Semester</th>
                                    <th>Academic Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($filtered_assignments)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 30px;">
                                            No assignments found for the selected filters.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($filtered_assignments as $a): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($a['course_code'] ?? 'N/A') ?></strong></td>
                                            <td><?= htmlspecialchars($a['course_name']) ?></td>
                                            <td><span class="badge"><?= $a['credit_hours'] ?> credits</span></td>
                                            <td>
                                                <?php
                                                $displayName = !empty(trim($a['full_name'])) ? $a['full_name'] : $a['username'];
                                                echo htmlspecialchars($displayName);
                                                ?>
                                            </td>
                                            <td><span class="badge"><?= htmlspecialchars($a['semester']) ?></span></td>
                                            <td><?= htmlspecialchars($a['academic_year']) ?></td>
                                            <td>
                                                <a class="action-btn delete" href="?delete=<?= $a['id'] ?>" onclick="return confirm('Are you sure you want to delete this assignment?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h3>No Assignments Yet</h3>
                        <p>Assign your first course to an instructor using the form above.</p>
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

        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Set active state for current page
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.sidebar a');
            
            navLinks.forEach(link => {
                const linkPage = link.getAttribute('href');
                if (linkPage === currentPage) {
                    link.classList.add('active');
                }
            });

            // Initialize Charts
            initCharts();
        });

        // Workload Chart Data
        const workloadData = {
            labels: [
                '0 - No Load',
                '1-6 Credits', 
                '7-9 Credits', 
                '10-12 Credits', 
                'Overloaded (>12)'
            ],
            datasets: [{
                label: 'Number of Instructors',
                data: [
                    <?= $no_load_count ?>,
                    <?= $chart_low_load ?>,
                    <?= $chart_warning_load ?>,
                    <?= $chart_high_load ?>,
                    <?= $overloaded_count ?>
                ],
                backgroundColor: [
                    '#9ca3af',  // Gray for no load
                    '#10b981',  // Green for low load
                    '#f59e0b',  // Yellow for warning
                    '#f97316',  // Orange for high load
                    '#ef4444'   // Red for overloaded
                ],
                borderColor: [
                    '#6b7280',
                    '#059669',
                    '#d97706',
                    '#ea580c',
                    '#dc2626'
                ],
                borderWidth: 1
            }]
        };

        const statusData = {
            labels: ['No Load', 'Normal', 'Near Limit', 'Overloaded'],
            datasets: [{
                data: [<?= $no_load_count ?>, <?= $normal_count ?>, <?= $warning_count ?>, <?= $overloaded_count ?>],
                backgroundColor: [
                    '#9ca3af',
                    '#10b981',
                    '#f59e0b',
                    '#ef4444'
                ],
                borderColor: [
                    '#6b7280',
                    '#059669',
                    '#d97706',
                    '#dc2626'
                ],
                borderWidth: 1
            }]
        };

        function initCharts() {
            // Workload Distribution Chart
            const workloadCtx = document.getElementById('workloadChart').getContext('2d');
            if (workloadCtx) {
                new Chart(workloadCtx, {
                    type: 'bar',
                    data: workloadData,
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `${context.dataset.label}: ${context.raw}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    color: 'var(--text-secondary)'
                                },
                                grid: {
                                    color: 'var(--border-color)'
                                }
                            },
                            x: {
                                ticks: {
                                    color: 'var(--text-secondary)',
                                    maxRotation: 45
                                },
                                grid: {
                                    color: 'var(--border-color)'
                                }
                            }
                        }
                    }
                });
            }

            // Status Overview Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            if (statusCtx) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: statusData,
                    options: {
                        responsive: true,
                        cutout: '60%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: 'var(--text-primary)',
                                    padding: 20
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((context.raw / total) * 100);
                                        return `${context.label}: ${context.raw} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        function resetForm() {
            document.getElementById('assignForm').reset();
        }
    </script>
    
    <!-- Include darkmode.js -->
    <script src="../../assets/js/darkmode.js"></script>
</body>
</html>