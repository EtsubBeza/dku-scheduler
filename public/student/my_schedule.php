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

// Fetch current user info
$user_stmt = $pdo->prepare("SELECT username, profile_picture, year FROM users WHERE user_id = ?");
$user_stmt->execute([$student_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$student_year = $user['year'] ?? '';

// Debug: Show what year is stored for this student
error_log("Student ID $student_id has year value: " . $student_year);

// Check if enrollments table exists
$table_check = $pdo->query("SHOW TABLES LIKE 'enrollments'");
$enrollments_table_exists = $table_check->fetch();

// Initialize search variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_placeholder = "Search by course name or code...";

if (!$enrollments_table_exists) {
    error_log("ERROR: enrollments table not found!");
    $debug_info = "ERROR: Enrollment table 'enrollments' not found in database.";
    $my_schedule = [];
} else {
    error_log("enrollments table found, proceeding with schedule fetch...");
    
    // Create a mapping of equivalent year values
    $year_equivalents = [
        // Regular years
        'freshman' => ['1', 'freshman', 'Freshman', 'FIRST YEAR', 'first year'],
        '1' => ['1', 'freshman', 'Freshman', 'FIRST YEAR', 'first year'],
        '2' => ['2', 'sophomore', 'Sophomore', 'SECOND YEAR', 'second year'],
        '3' => ['3', 'junior', 'Junior', 'THIRD YEAR', 'third year'],
        '4' => ['4', 'senior', 'Senior', 'FOURTH YEAR', 'fourth year'],
        '5' => ['5', 'fifth', 'Fifth', 'FIFTH YEAR', 'fifth year'],
        
        // Extension years
        'E1' => ['E1', 'e1', 'Extension 1', 'extension 1', 'EXTENSION 1'],
        'E2' => ['E2', 'e2', 'Extension 2', 'extension 2', 'EXTENSION 2'],
        'E3' => ['E3', 'e3', 'Extension 3', 'extension 3', 'EXTENSION 3'],
        'E4' => ['E4', 'e4', 'Extension 4', 'extension 4', 'EXTENSION 4'],
        'E5' => ['E5', 'e5', 'Extension 5', 'extension 5', 'EXTENSION 5'],
    ];

    // Function to get all equivalent year values for a given year
    function getYearEquivalents($year, $year_equivalents) {
        $equivalents = [];
        
        // First, try exact match
        foreach ($year_equivalents as $key => $values) {
            if (strtolower($year) == strtolower($key)) {
                $equivalents = array_merge($equivalents, $values);
            }
        }
        
        // Also check if year is in any of the value arrays
        foreach ($year_equivalents as $values) {
            foreach ($values as $value) {
                if (strtolower($year) == strtolower($value)) {
                    $equivalents = array_merge($equivalents, $values);
                }
            }
        }
        
        // Add the original year
        $equivalents[] = $year;
        
        // Remove duplicates and return
        return array_unique($equivalents);
    }

    // Get all possible year values that match this student's year
    $year_search_values = getYearEquivalents($student_year, $year_equivalents);

    // Create IN clause for SQL query
    $placeholders = str_repeat('?,', count($year_search_values) - 1) . '?';
    
    // Check enrollments in enrollments table
    $enrollment_check = $pdo->prepare("
        SELECT COUNT(*) as count, GROUP_CONCAT(schedule_id) as schedule_ids
        FROM enrollments 
        WHERE student_id = ?
    ");
    $enrollment_check->execute([$student_id]);
    $enrollment_data = $enrollment_check->fetch();
    $enrollment_count = $enrollment_data['count'];
    $enrolled_schedule_ids = $enrollment_data['schedule_ids'] ?? '';
    
    error_log("Student $student_id has $enrollment_count enrollments in enrollments table");
    error_log("Enrolled schedule IDs: " . $enrolled_schedule_ids);
    
    if ($enrollment_count > 0) {
        // Build base query with search condition
        $base_query = "
            SELECT s.schedule_id, c.course_name, c.course_code, 
                   COALESCE(u.username, 'TBA') AS instructor_name, 
                   r.room_name, s.day, s.start_time, s.end_time, s.year as schedule_year
            FROM schedule s
            JOIN courses c ON s.course_id = c.course_id
            LEFT JOIN users u ON s.instructor_id = u.user_id
            JOIN rooms r ON s.room_id = r.room_id
            JOIN enrollments e ON s.schedule_id = e.schedule_id
            WHERE e.student_id = ? 
            AND s.year IN ($placeholders)
        ";
        
        // Add search condition if search term is provided
        $search_query = "";
        $search_params = [];
        
        if (!empty($search)) {
            $search_query = " AND (c.course_name LIKE ? OR c.course_code LIKE ?)";
            $search_param = "%{$search}%";
            $search_params = [$search_param, $search_param];
        }
        
        // Complete query with ordering
        $complete_query = $base_query . $search_query . "
            ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), s.start_time
        ";
        
        // Prepare and execute the query
        $schedules = $pdo->prepare($complete_query);
        
        // Prepare parameters: student_id + year equivalents + search params
        $params = array_merge([$student_id], $year_search_values, $search_params);
        error_log("Executing query with params: " . implode(', ', $params));
        
        $schedules->execute($params);
        $my_schedule = $schedules->fetchAll();
        
        error_log("Found " . count($my_schedule) . " schedules for student $student_id");
        
        // Debug info if no schedules found
        if (empty($my_schedule) && empty($search)) {
            // Check available schedules for this year
            $schedule_check = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM schedule 
                WHERE year IN ($placeholders)
            ");
            $schedule_check->execute($year_search_values);
            $schedule_count = $schedule_check->fetch()['count'];
            
            $debug_info = "No schedules found. Student ID: $student_id, Year: $student_year";
            $debug_info .= " | Enrollments found: $enrollment_count";
            $debug_info .= " | Available schedules for year: $schedule_count";
            
            if ($enrolled_schedule_ids) {
                $debug_info .= " | Enrolled schedule IDs: " . $enrolled_schedule_ids;
            }
            
            // Additional debug: check if enrolled schedules exist in schedule table
            if ($enrolled_schedule_ids) {
                $schedule_ids_array = explode(',', $enrolled_schedule_ids);
                $schedule_check = $pdo->prepare("
                    SELECT schedule_id, year, course_id
                    FROM schedule 
                    WHERE schedule_id IN (" . str_repeat('?,', count($schedule_ids_array) - 1) . "?)
                ");
                $schedule_check->execute($schedule_ids_array);
                $found_schedules = $schedule_check->fetchAll();
                
                error_log("Checking specific schedule IDs: " . $enrolled_schedule_ids);
                error_log("Found " . count($found_schedules) . " matching schedules in schedule table");
                
                if (count($found_schedules) > 0) {
                    $debug_info .= " | Schedules found in table: " . count($found_schedules);
                    foreach ($found_schedules as $fs) {
                        $debug_info .= " | ID:" . $fs['schedule_id'] . " Year:" . $fs['year'];
                    }
                } else {
                    $debug_info .= " | WARNING: None of the enrolled schedule IDs exist in schedule table!";
                }
            }
        }
    } else {
        $debug_info = "You have no enrollments. Please contact your department head to enroll you in courses.";
        $my_schedule = [];
    }
}

// Determine student type for display
$is_freshman = false;
$is_extension = false;
$is_regular = false;

if (strtolower($student_year) === 'freshman' || $student_year === '1' || $student_year === 'first year') {
    $is_freshman = true;
} elseif (strtoupper(substr($student_year, 0, 1)) === 'E') {
    $is_extension = true;
} elseif (is_numeric($student_year) && $student_year >= 2 && $student_year <= 5) {
    $is_regular = true;
}

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

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);

// Handle Excel/CSV Export
if(isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="my_schedule_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Course Name', 'Course Code', 'Instructor', 'Room', 'Day', 'Start Time', 'End Time', 'Year']);
    
    foreach($my_schedule as $s) {
        fputcsv($output, [
            $s['course_name'],
            $s['course_code'],
            $s['instructor_name'],
            $s['room_name'],
            $s['day'],
            date('g:i A', strtotime($s['start_time'])),
            date('g:i A', strtotime($s['end_time'])),
            $s['schedule_year']
        ]);
    }
    fclose($output);
    exit;
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
/* Add search bar styles */
.search-container {
    margin-bottom: 30px;
    position: relative;
    max-width: 500px;
}

.search-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-box {
    flex: 1;
    padding: 14px 50px 14px 20px;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px var(--shadow-color);
}

.search-box:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.search-btn {
    padding: 14px 24px;
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px var(--shadow-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px var(--shadow-lg);
    background: linear-gradient(135deg, #4f46e5, #2563eb);
}

.clear-btn {
    padding: 14px 20px;
    background: linear-gradient(135deg, #6b7280, #4b5563);
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px var(--shadow-color);
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.clear-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px var(--shadow-lg);
    background: linear-gradient(135deg, #4b5563, #374151);
}

.search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    font-size: 1.1rem;
}

.search-results-info {
    margin-top: 10px;
    padding: 10px 15px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-results-info i {
    color: #6366f1;
}

.no-results {
    text-align: center;
    padding: 40px 20px;
    background: var(--bg-card);
    border-radius: 12px;
    margin-top: 20px;
    border: 1px solid var(--border-color);
}

.no-results i {
    font-size: 3rem;
    color: var(--text-secondary);
    margin-bottom: 15px;
}

.no-results h3 {
    color: var(--text-primary);
    margin-bottom: 10px;
}

.no-results p {
    color: var(--text-secondary);
    margin-bottom: 20px;
}

.try-again-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
}

.try-again-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px var(--shadow-color);
}

/* Responsive adjustments for search */
@media (max-width: 768px) {
    .search-form {
        flex-direction: column;
        gap: 10px;
    }
    
    .search-box, .search-btn, .clear-btn {
        width: 100%;
    }
    
    .search-container {
        max-width: 100%;
    }
}

* { box-sizing: border-box; margin:0; padding:0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

/* ================= University Header (ADDED) ================= */
.university-header {
    background: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
    color: white;
    padding: 0.5rem 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1201;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.dku-logo-img {
    width: 45px;
    height: 45px;
    object-fit: contain;
    border-radius: 5px;
    background: white;
    padding: 4px;
}

.system-title {
    font-size: 0.9rem;
    font-weight: 600;
    opacity: 0.95;
}

.header-right {
    font-size: 0.8rem;
    opacity: 0.9;
}

@media (max-width: 768px) {
    .university-header {
        padding: 0.5rem 15px;
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }
    
    .header-left, .header-right {
        width: 100%;
        justify-content: center;
    }
    
    .system-title {
        font-size: 0.8rem;
    }
    
    .header-right {
        font-size: 0.75rem;
    }
}

/* Adjust other elements for university header */
.topbar {
    top: 60px !important; /* Adjusted for university header */
}

.sidebar {
    top: 60px !important; /* Adjusted for university header */
    height: calc(100% - 60px) !important;
}

.overlay {
    top: 60px; /* Adjusted for university header */
    height: calc(100% - 60px);
}

.main-content {
    margin-top: 60px; /* Added for university header */
}

/* ================= Topbar for Hamburger ================= */
.topbar {
    display: none;
    position: fixed; 
    top:60px; 
    left:0; 
    width:100%;
    background:var(--bg-sidebar); 
    color:var(--text-sidebar);
    padding:12px 20px;
    z-index:1200;
    justify-content:space-between; 
    align-items:center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.menu-btn {
    font-size:26px;
    background:#1abc9c;
    border:none; 
    color:var(--text-sidebar);
    cursor:pointer;
    padding:8px 12px;
    border-radius:8px;
    font-weight:600;
    transition: background 0.3s, transform 0.2s;
}
.menu-btn:hover { 
    background:#159b81; 
    transform:translateY(-2px); 
}

/* ================= Sidebar ================= */
.sidebar {
    position: fixed; 
    top:60px; 
    left:0;
    width:250px; 
    height:calc(100% - 60px);
    background:var(--bg-sidebar); 
    color:var(--text-sidebar);
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

/* Sidebar Title */
.sidebar h2 {
    text-align: center;
    color: var(--text-sidebar);
    margin-bottom: 25px;
    font-size: 22px;
    padding: 0 20px;
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
    font-size: 1.1rem;
}

/* ================= Overlay ================= */
.overlay {
    position: fixed; 
    top:60px; 
    left:0; 
    width:100%; 
    height:calc(100% - 60px);
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
    padding:20px;
    min-height:100vh;
    background: var(--bg-primary);
    transition: all 0.3s ease;
    margin-top: 60px;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 15px;
        padding-top: 140px; /* Adjusted for headers on mobile */
        margin-top: 120px; /* 60px header + 60px topbar */
    }
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

/* Student type indicator */
.student-type-indicator {
    display: inline-block;
    padding: 2px 8px;
    background: <?= ($is_freshman ? '#10b981' : ($is_extension ? '#8b5cf6' : '#3b82f6')) ?>;
    color: white;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 5px;
    vertical-align: middle;
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

/* Info box */
.info-box {
    background: rgba(59, 130, 246, 0.1);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #3b82f6;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.info-box i {
    color: #3b82f6;
    margin-right: 8px;
}

/* Database info box */
.db-info-box {
    background: rgba(34, 197, 94, 0.1);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #10b981;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.db-info-box i {
    color: #10b981;
    margin-right: 8px;
}

/* Warning box */
.warning-box {
    background: rgba(249, 115, 22, 0.1);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #f97316;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.warning-box i {
    color: #f97316;
    margin-right: 8px;
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
    .university-header, .sidebar, .topbar, .overlay, .export-buttons, .user-info, .student-info-box, .debug-info, .info-box, .db-info-box, .warning-box, .search-container { 
        display: none !important; 
    }
    .main-content { 
        margin-left: 0 !important; 
        margin-top: 0 !important;
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
    .university-header {
        padding: 0.5rem 15px;
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }
    
    .header-left, .header-right {
        width: 100%;
        justify-content: center;
    }
    
    .system-title {
        font-size: 0.8rem;
    }
    
    .header-right {
        font-size: 0.75rem;
    }
    
    .topbar { 
        display:flex;
        top: 60px; /* Adjusted for mobile with header */
    }
    
    .sidebar { 
        transform:translateX(-100%); 
        top: 120px; /* 60px header + 60px topbar */
        height: calc(100% - 120px) !important;
    }
    
    .sidebar.active { 
        transform:translateX(0); 
    }
    
    .overlay {
        top: 120px;
        height: calc(100% - 120px);
    }
    
    .main-content {
        padding-top: 140px; /* Adjusted for headers on mobile */
        margin-top: 120px; /* 60px header + 60px topbar */
    }
    
    .header { 
        flex-direction: column; 
        gap: 15px; 
        align-items: flex-start; 
    }
    
    .header h1 { 
        font-size: 1.8rem; 
    }
    
    .export-buttons { 
        flex-direction: column; 
    }
    
    .export-btn { 
        width: 100%; 
        justify-content: center; 
    }
    
    .table-container { 
        overflow-x: auto; 
    }
    
    .schedule-table { 
        min-width: 600px; 
    }
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
</style>
</head>
<body>
    <!-- =========== ADDED: University Header =========== -->
    <div class="university-header">
        <div class="header-left">
            <img src="../assets/images/dku logo.jpg" alt="Debark University Logo" class="dku-logo-img">
            <div class="system-title">Debark University Class Scheduling System</div>
        </div>
        <div class="header-right">
            My Schedule
        </div>
    </div>

    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>My Schedule</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-content" id="sidebarContent">
            <div class="sidebar-profile">
                <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture">
                <p><?= htmlspecialchars($user['username'] ?? 'Student') ?></p>
                <?php if($student_year): ?>
                    <span class="year-badge">
                        <?php 
                        if (strtoupper(substr($student_year, 0, 1)) === 'E') {
                            echo 'Extension Year ' . substr($student_year, 1);
                        } elseif (is_numeric($student_year)) {
                            echo 'Year ' . $student_year;
                        } else {
                            echo ucfirst($student_year);
                        }
                        ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <h2>Student Dashboard</h2>
            
            <nav>
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
            </nav>
        </div>
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
                            if (strtoupper(substr($student_year, 0, 1)) === 'E') {
                                echo 'Extension Year ' . substr($student_year, 1);
                            } elseif (is_numeric($student_year)) {
                                echo 'Year ' . $student_year;
                            } else {
                                echo ucfirst($student_year);
                            }
                        } else {
                            echo 'Year not set';
                        }
                        ?>
                    </span>
                    <span class="student-type-indicator">
                        <?php
                        if ($is_freshman) {
                            echo 'Freshman';
                        } elseif ($is_extension) {
                            echo 'Extension';
                        } elseif ($is_regular) {
                            echo 'Regular';
                        } else {
                            echo 'Unknown';
                        }
                        ?>
                    </span>
                </div>
            </div>

            <?php if(isset($enrollments_table_exists) && $enrollments_table_exists): ?>
            <div class="db-info-box">
                <i class="fas fa-database"></i>
                <strong>Database Info:</strong> Using 'enrollments' table for all students
            </div>
            <?php endif; ?>

            <?php if(!empty($debug_info)): ?>
                <div class="debug-info">
                    <i class="fas fa-exclamation-triangle"></i> Debug Info: <?= $debug_info ?>
                </div>
            <?php endif; ?>

            <?php if(!$enrollments_table_exists): ?>
                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Critical Error:</strong> 'enrollments' table not found in database. Please contact administrator.
                </div>
            <?php endif; ?>

            <?php if(empty($my_schedule) && isset($debug_info) && strpos($debug_info, 'no enrollments') !== false): ?>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>No Enrollments Found:</strong> 
                    Please contact your department head to enroll you in courses for <?= htmlspecialchars($student_year) ?>.
                </div>
            <?php endif; ?>

            <!-- Search Bar -->
            <div class="search-container">
                <form method="GET" class="search-form">
                    <div style="position: relative; flex: 1;">
                        <i class="fas fa-search search-icon"></i>
                        <input 
                            type="text" 
                            name="search" 
                            class="search-box" 
                            placeholder="<?= $search_placeholder ?>"
                            value="<?= htmlspecialchars($search) ?>"
                            autocomplete="off"
                        >
                    </div>
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if(!empty($search)): ?>
                        <a href="my_schedule.php" class="clear-btn">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
                
                <?php if(!empty($search)): ?>
                    <div class="search-results-info">
                        <i class="fas fa-info-circle"></i>
                        Showing results for "<?= htmlspecialchars($search) ?>"
                        <?php if(!empty($my_schedule)): ?>
                            - Found <?= count($my_schedule) ?> course(s)
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Export Buttons (only show when there are results) -->
            <?php if(!empty($my_schedule)): ?>
            <div class="export-buttons">
                <a href="?export=excel<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="export-btn excel">
                    <i class="fas fa-file-excel"></i> Export Excel/CSV
                </a>
                <button onclick="window.print()" class="export-btn print">
                    <i class="fas fa-print"></i> Print Schedule
                </button>
            </div>
            <?php endif; ?>

            <!-- Schedule Table -->
            <div class="schedule-section">
                <h2 style="margin-bottom: 20px; color: var(--text-primary);">Class Timetable</h2>
                
                <?php if(!empty($search) && empty($my_schedule)): ?>
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <h3>No courses found</h3>
                        <p>No courses match your search for "<?= htmlspecialchars($search) ?>"</p>
                        <a href="my_schedule.php" class="try-again-btn">
                            <i class="fas fa-redo"></i> View All Courses
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Instructor</th>
                                    <th>Room</th>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Year</th>
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
                                    <td><small><?= htmlspecialchars($s['schedule_year']) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if(empty($my_schedule) && empty($search)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="fas fa-calendar-times"></i>
                                            <h3>No Classes Scheduled</h3>
                                            <p>You don't have any classes scheduled for 
                                                <?php 
                                                if($student_year) {
                                                    if (strtoupper(substr($student_year, 0, 1)) === 'E') {
                                                        echo 'Extension Year ' . substr($student_year, 1);
                                                    } elseif (is_numeric($student_year)) {
                                                        echo 'Year ' . $student_year;
                                                    } else {
                                                        echo ucfirst($student_year);
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
                    
                    <?php if($hasTodayClass && !empty($my_schedule)): ?>
                    <div style="margin-top: 15px; padding: 10px; background: var(--info-bg); border-radius: 8px; border-left: 4px solid #f59e0b;">
                        <small style="color: var(--info-text); font-weight: 600;">
                            <i class="fas fa-info-circle"></i> Highlighted rows indicate today's classes
                        </small>
                    </div>
                    <?php endif; ?>
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
        
        // Focus on search box if search parameter exists
        const urlParams = new URLSearchParams(window.location.search);
        const searchParam = urlParams.get('search');
        if (searchParam) {
            const searchBox = document.querySelector('.search-box');
            if (searchBox) {
                searchBox.focus();
                searchBox.setSelectionRange(searchParam.length, searchParam.length);
            }
        }
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

    // Enhance search functionality with keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Focus search box with Ctrl+F or Cmd+F
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            const searchBox = document.querySelector('.search-box');
            if (searchBox) {
                searchBox.focus();
                searchBox.select();
            }
        }
        
        // Clear search with Escape key
        if (e.key === 'Escape') {
            const searchBox = document.querySelector('.search-box');
            if (searchBox && searchBox.value) {
                window.location.href = 'my_schedule.php';
            }
        }
    });
    </script>
</body>
</html>