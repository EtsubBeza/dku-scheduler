<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch current user info
$user_stmt = $pdo->prepare("SELECT username, email, profile_picture FROM users WHERE user_id=?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch();

// Get profile image path (using your existing function)
function getAdminProfilePicturePath($profile_picture) {
    if (empty($profile_picture)) {
        return '../assets/default_profile.png';
    }
    
    $locations = [
        __DIR__ . '/../uploads/admin/' . $profile_picture,
        __DIR__ . '/../uploads/' . $profile_picture,
        __DIR__ . '/../../uploads/' . $profile_picture,
        'uploads/admin/' . $profile_picture,
        '../uploads/admin/' . $profile_picture,
        'uploads/' . $profile_picture,
        '../uploads/' . $profile_picture,
    ];
    
    foreach ($locations as $location) {
        if (file_exists($location)) {
            if (strpos($location, '/admin/') !== false) {
                return '../uploads/admin/' . $profile_picture;
            } elseif (strpos($location, 'uploads/admin/') !== false) {
                return 'uploads/admin/' . $profile_picture;
            } elseif (strpos($location, '../uploads/') !== false) {
                return '../uploads/' . $profile_picture;
            } elseif (strpos($location, 'uploads/') !== false) {
                return 'uploads/' . $profile_picture;
            }
        }
    }
    
    return '../assets/default_profile.png';
}

$profile_img_path = getAdminProfilePicturePath($current_user['profile_picture'] ?? '');

// Initialize message variables
$message = "";
$message_type = "success";

// Check for session messages
if(isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Handle instructor assignment by section
if(isset($_POST['assign_instructor'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $course_id = (int)$_POST['course_id'];
        $section_number = (int)$_POST['section_number'];
        $instructor_id = (int)$_POST['instructor_id'];
        
        if($instructor_id <= 0){
            $message = "Please select an instructor.";
            $message_type = "error";
        } else {
            try {
                // Check if instructor exists and is actually an instructor
                $instructor_check = $pdo->prepare("SELECT user_id, username FROM users WHERE user_id = ? AND role = 'instructor'");
                $instructor_check->execute([$instructor_id]);
                
                $instructor_data = $instructor_check->fetch();
                
                if(!$instructor_data){
                    $message = "Selected user is not an instructor.";
                    $message_type = "error";
                } else {
                    // Update all schedules for this course and section
                    $update_stmt = $pdo->prepare("UPDATE schedule SET instructor_id = ? WHERE course_id = ? AND section_number = ? AND year = 'freshman'");
                    $update_stmt->execute([$instructor_id, $course_id, $section_number]);
                    $affected_rows = $update_stmt->rowCount();
                    
                    // Get course info
                    $course_stmt = $pdo->prepare("SELECT course_code, course_name FROM courses WHERE course_id = ?");
                    $course_stmt->execute([$course_id]);
                    $course_data = $course_stmt->fetch();
                    
                    $message = "✅ Instructor '{$instructor_data['username']}' assigned to {$course_data['course_code']} - Section $section_number ($affected_rows sessions updated)!";
                    $message_type = "success";
                    
                    // Refresh the page
                    header("Location: assign_instructors.php?assigned=1");
                    exit;
                }
            } catch (Exception $e) {
                $message = "Error assigning instructor: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Handle bulk assignment to multiple sections
if(isset($_POST['bulk_assign_instructor'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $instructor_id = (int)$_POST['bulk_instructor_id'];
        $course_id = isset($_POST['bulk_course_id']) ? (int)$_POST['bulk_course_id'] : 0;
        $section_number = isset($_POST['bulk_section_number']) ? (int)$_POST['bulk_section_number'] : 0;
        
        if($instructor_id <= 0){
            $message = "Please select an instructor.";
            $message_type = "error";
        } else {
            try {
                // Check if instructor exists
                $instructor_check = $pdo->prepare("SELECT user_id, username FROM users WHERE user_id = ? AND role = 'instructor'");
                $instructor_check->execute([$instructor_id]);
                
                $instructor_data = $instructor_check->fetch();
                
                if(!$instructor_data){
                    $message = "Selected user is not an instructor.";
                    $message_type = "error";
                } else {
                    // Build the WHERE clause
                    $where_clauses = ["year = 'freshman'"];
                    $params = [];
                    
                    if($course_id > 0){
                        $where_clauses[] = "course_id = ?";
                        $params[] = $course_id;
                    }
                    
                    if($section_number > 0){
                        $where_clauses[] = "section_number = ?";
                        $params[] = $section_number;
                    }
                    
                    $where_sql = implode(" AND ", $where_clauses);
                    $params = array_merge([$instructor_id], $params);
                    
                    // Update multiple schedules
                    $update_stmt = $pdo->prepare("UPDATE schedule SET instructor_id = ? WHERE $where_sql");
                    $update_stmt->execute($params);
                    $affected_rows = $update_stmt->rowCount();
                    
                    // Get course info if a specific course was selected
                    $course_info = "";
                    if($course_id > 0){
                        $course_stmt = $pdo->prepare("SELECT course_code, course_name FROM courses WHERE course_id = ?");
                        $course_stmt->execute([$course_id]);
                        $course_data = $course_stmt->fetch();
                        $course_info = " for {$course_data['course_code']}";
                    }
                    
                    $section_info = $section_number > 0 ? "Section $section_number" : "all sections";
                    
                    $message = "✅ Assigned instructor '{$instructor_data['username']}' to $section_info{$course_info} ($affected_rows sessions updated)!";
                    $message_type = "success";
                    
                    header("Location: assign_instructors.php?bulk_assigned=1");
                    exit;
                }
            } catch (Exception $e) {
                $message = "Error in bulk assignment: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Fetch all sections that need instructors (grouped by course and section)
$sections_query = "
    SELECT 
        s.course_id,
        COALESCE(s.section_number, 1) as section_number,
        c.course_code,
        c.course_name,
        COUNT(DISTINCT s.schedule_id) as total_sessions,
        COUNT(DISTINCT se.student_id) as total_students,
        GROUP_CONCAT(DISTINCT r.room_name ORDER BY r.room_name SEPARATOR ', ') as rooms,
        s.instructor_id,
        u.username as current_instructor_name,
        u.email as current_instructor_email,
        s.academic_year,
        s.semester
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN rooms r ON s.room_id = r.room_id
    LEFT JOIN users u ON s.instructor_id = u.user_id
    LEFT JOIN student_enrollments se ON s.schedule_id = se.schedule_id
    WHERE (s.year = 'freshman' OR c.is_freshman = 1)
    GROUP BY s.course_id, s.section_number, s.academic_year, s.semester
    HAVING s.instructor_id IS NULL OR s.instructor_id = 0 OR u.username = 'TBA'
    ORDER BY 
        s.section_number,
        c.course_code,
        s.academic_year DESC,
        s.semester
";

$unassigned_sections = $pdo->query($sections_query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch all instructors
$instructors = $pdo->query("SELECT user_id, username, email FROM users WHERE role = 'instructor' AND username != 'TBA' ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Fetch distinct courses and sections for bulk assignment
$courses = $pdo->query("SELECT DISTINCT c.course_id, c.course_code, c.course_name 
                         FROM schedule s 
                         JOIN courses c ON s.course_id = c.course_id 
                         WHERE (s.instructor_id IS NULL OR s.instructor_id = 0 OR s.instructor_id IN (SELECT user_id FROM users WHERE username = 'TBA'))
                         AND (s.year = 'freshman' OR c.is_freshman = 1)
                         ORDER BY c.course_code")->fetchAll(PDO::FETCH_ASSOC);

$sections = $pdo->query("SELECT DISTINCT COALESCE(section_number, 1) as section_number 
                          FROM schedule 
                          WHERE year = 'freshman' 
                          ORDER BY section_number")->fetchAll(PDO::FETCH_ASSOC);

// Count statistics
$total_unassigned_sessions = 0;
$total_unassigned_students = 0;
foreach($unassigned_sections as $section) {
    $total_unassigned_sessions += $section['total_sessions'];
    $total_unassigned_students += $section['total_students'];
}

// Fetch pending approvals count
$pending_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_approved = 0");
$pending_approvals = $pending_stmt->fetchColumn() ?: 0;

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assign Instructors - DKU Scheduler</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
/* ================= CSS Variables ================= */
:root {
    --bg-primary: #f8f9fa;
    --bg-secondary: #ffffff;
    --bg-card: #ffffff;
    --bg-sidebar: #2c3e50;
    --text-primary: #333333;
    --text-secondary: #666666;
    --text-sidebar: #ffffff;
    --border-color: #dee2e6;
    --shadow-color: rgba(0,0,0,0.1);
    --hover-color: rgba(0,0,0,0.05);
    --table-header: #3498db;
    --success-bg: #d1fae5;
    --success-text: #065f46;
    --success-border: #10b981;
    --error-bg: #fee2e2;
    --error-text: #991b1b;
    --error-border: #ef4444;
    --warning-bg: #fef3c7;
    --warning-text: #92400e;
    --warning-border: #f59e0b;
    --primary-color: #2563eb;
    --primary-hover: #1d4ed8;
    --danger-color: #ef4444;
    --danger-hover: #dc2626;
    --section-1: #3b82f6;
    --section-2: #10b981;
    --section-3: #8b5cf6;
    --section-4: #f59e0b;
    --section-5: #ef4444;
}

[data-theme="dark"] {
    --bg-primary: #1a1a1a;
    --bg-secondary: #2d2d2d;
    --bg-card: #2d2d2d;
    --bg-sidebar: #1e2a3a;
    --text-primary: #e0e0e0;
    --text-secondary: #b0b0b0;
    --text-sidebar: #e0e0e0;
    --border-color: #404040;
    --shadow-color: rgba(0,0,0,0.3);
    --hover-color: rgba(255,255,255,0.05);
    --table-header: #2563eb;
    --success-bg: #064e3b;
    --success-text: #a7f3d0;
    --success-border: #10b981;
    --error-bg: #7f1d1d;
    --error-text: #fecaca;
    --error-border: #ef4444;
    --warning-bg: #78350f;
    --warning-text: #fde68a;
    --warning-border: #f59e0b;
    --primary-color: #3b82f6;
    --primary-hover: #2563eb;
    --danger-color: #ef4444;
    --danger-hover: #dc2626;
    --section-1: #60a5fa;
    --section-2: #34d399;
    --section-3: #a78bfa;
    --section-4: #fbbf24;
    --section-5: #f87171;
}

/* ================= General Reset ================= */
* { margin:0; padding:0; box-sizing:border-box; font-family: "Segoe UI", Arial, sans-serif; }
body { display:flex; min-height:100vh; background: var(--bg-primary); overflow-x:hidden; }

/* ================= Topbar for Mobile ================= */
.topbar {
    display: none;
    position: fixed; top:0; left:0; width:100%;
    background:var(--bg-sidebar); color:var(--text-sidebar);
    padding:15px 20px;
    z-index:1200;
    justify-content:space-between; align-items:center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
    position: fixed; 
    top:0; left:0; 
    width:250px; 
    height:100%; 
    background:var(--bg-sidebar); 
    color:var(--text-sidebar);
    z-index:1100;
    transition: transform 0.3s ease;
    padding: 20px 0;
}
.sidebar.hidden { transform:translateX(-260px); }

.sidebar-profile {
    text-align: center;
    margin-bottom: 25px;
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

.sidebar h2 {
    text-align: center;
    color: var(--text-sidebar);
    margin-bottom: 25px;
    font-size: 22px;
    padding: 0 20px;
}

.sidebar a { 
    display:block; 
    padding:12px 20px; 
    color:var(--text-sidebar); 
    text-decoration:none; 
    transition: background 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
}
.sidebar a:hover, .sidebar a.active { background:#1abc9c; color:white; }

.pending-badge {
    background: #ef4444;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    margin-left: auto;
}

/* ================= Overlay ================= */
.overlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.4); z-index:1050;
    display:none; opacity:0; transition: opacity 0.3s ease;
}
.overlay.active { display:block; opacity:1; }

/* ================= Main Content ================= */
.main-content { 
    margin-left:250px; 
    padding:30px;
    min-height:100vh;
    background: var(--bg-primary);
    transition: all 0.3s ease;
    width: calc(100% - 250px);
}

/* Content Wrapper */
.content-wrapper {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 6px var(--shadow-color);
    min-height: calc(100vh - 60px);
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

/* Page Title */
.page-title {
    font-size: 1.8rem;
    color: var(--text-primary);
    margin-bottom: 25px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ================= Message Styles ================= */
.message {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.3s ease;
    box-shadow: 0 4px 6px var(--shadow-color);
    border-left: 4px solid;
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
    background: linear-gradient(135deg, var(--success-bg), #bbf7d0);
    color: var(--success-text);
    border-color: var(--success-border);
}

.message.error {
    background: linear-gradient(135deg, var(--error-bg), #fecaca);
    color: var(--error-text);
    border-color: var(--error-border);
}

.message.warning {
    background: linear-gradient(135deg, var(--warning-bg), #fde68a);
    color: var(--warning-text);
    border-color: var(--warning-border);
}

.message i {
    font-size: 1.2rem;
}

/* ================= Stats Card ================= */
.stats-card {
    background: var(--bg-card);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    border: 1px solid var(--border-color);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: var(--bg-secondary);
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 5px;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

/* ================= Bulk Assignment ================= */
.bulk-assignment {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border-left: 4px solid var(--primary-color);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    color: var(--text-primary);
}

[data-theme="dark"] .bulk-assignment {
    background: linear-gradient(135deg, #1e3a8a, #1e40af);
    color: #dbeafe;
}

.bulk-assignment h3 {
    margin-bottom: 15px;
    color: var(--primary-color);
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.bulk-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

/* ================= Section Card ================= */
.section-card {
    background: var(--bg-secondary);
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 25px;
    border: 2px solid var(--border-color);
    transition: all 0.3s;
}

.section-card:hover {
    box-shadow: 0 6px 15px var(--shadow-color);
    transform: translateY(-3px);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.section-title {
    color: var(--text-primary);
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.section-badge {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    color: white;
    margin-right: 10px;
}

.section-1 { background: var(--section-1); }
.section-2 { background: var(--section-2); }
.section-3 { background: var(--section-3); }
.section-4 { background: var(--section-4); }
.section-5 { background: var(--section-5); }

.section-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.detail-item i {
    color: var(--primary-color);
    font-size: 1.1rem;
}

.detail-label {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.detail-value {
    color: var(--text-secondary);
    font-size: 0.95rem;
}

/* Instructor Info */
.instructor-info {
    background: var(--bg-primary);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.instructor-current {
    display: flex;
    align-items: center;
    gap: 10px;
}

.instructor-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.badge-tba {
    background: #f59e0b;
    color: #92400e;
}

[data-theme="dark"] .badge-tba {
    background: #78350f;
    color: #fde68a;
}

.badge-assigned {
    background: #10b981;
    color: #065f46;
}

[data-theme="dark"] .badge-assigned {
    background: #064e3b;
    color: #a7f3d0;
}

/* Assignment Form */
.assignment-form {
    background: var(--bg-card);
    padding: 20px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.assignment-form h4 {
    color: var(--text-primary);
    margin-bottom: 15px;
    font-size: 1.1rem;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.required::after {
    content: " *";
    color: #ef4444;
}

.instructor-select {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 1rem;
    transition: all 0.3s;
}

.instructor-select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}

.assignment-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.btn { 
    padding: 12px 24px; 
    border-radius: 8px; 
    border: none; 
    cursor: pointer; 
    font-weight: 600; 
    font-size: 0.95rem;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 140px;
    justify-content: center;
}
.btn-primary { 
    background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); 
    color: white; 
}
.btn-primary:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-secondary {
    background: linear-gradient(135deg, #6b7280, #4b5563);
    color: white;
}
.btn-secondary:hover {
    background: linear-gradient(135deg, #4b5563, #374151);
    transform: translateY(-2px);
}

/* Empty State */
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
    font-size: 1.3rem;
    margin-bottom: 10px;
    color: var(--text-primary);
}

.empty-state p {
    font-size: 0.95rem;
    max-width: 400px;
    margin: 0 auto;
}

/* Responsive adjustments */
@media(max-width: 768px){
    .topbar{ display:flex; }
    .sidebar{ transform:translateX(-100%); }
    .sidebar.active{ transform:translateX(0); }
    .main-content{ 
        margin-left:0; 
        padding: 15px;
        padding-top: 80px;
        width: 100%;
    }
    .content-wrapper {
        padding: 15px;
        border-radius: 0;
    }
    .header { 
        flex-direction: column; 
        gap: 15px; 
        align-items: flex-start; 
    }
    .header h1 { font-size: 1.8rem; }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .bulk-form-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .section-details {
        grid-template-columns: 1fr;
    }
    
    .instructor-info {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .assignment-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}
</style>
</head>
<body>

<!-- Mobile Topbar -->
<div class="topbar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <span>Assign Instructors</span>
</div>

<!-- Overlay for Mobile -->
<div class="overlay" onclick="toggleMenu()"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-profile">
        <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic"
             onerror="this.onerror=null; this.src='../assets/default_profile.png';">
        <p><?= htmlspecialchars($current_user['username']) ?></p>
    </div>
    <h2>Admin Panel</h2>
    <a href="dashboard.php" class="<?= $current_page=='dashboard.php'?'active':'' ?>">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="manage_users.php" class="<?= $current_page=='manage_users.php'?'active':'' ?>">
        <i class="fas fa-users"></i> Manage Users
    </a>
    <a href="approve_users.php" class="<?= $current_page=='approve_users.php'?'active':'' ?>">
        <i class="fas fa-user-check"></i> Approve Users
        <?php if($pending_approvals > 0): ?>
            <span class="pending-badge"><?= $pending_approvals ?></span>
        <?php endif; ?>
    </a>
    <a href="manage_departments.php" class="<?= $current_page=='manage_departments.php'?'active':'' ?>">
        <i class="fas fa-building"></i> Manage Departments
    </a>
    <a href="manage_courses.php" class="<?= $current_page=='manage_courses.php'?'active':'' ?>">
        <i class="fas fa-book"></i> Manage Courses
    </a>
    <a href="manage_rooms.php" class="<?= $current_page=='manage_rooms.php'?'active':'' ?>">
        <i class="fas fa-door-closed"></i> Manage Rooms
    </a>
    <a href="manage_schedules.php">
        <i class="fas fa-calendar-alt"></i> Manage Schedule
    </a>
    <a href="assign_instructors.php" class="active">
        <i class="fas fa-chalkboard-teacher"></i> Assign Instructors
    </a>
      <a href="admin_exam_schedules.php" class="<?= $current_page=='admin_exam_schedules.php'?'active':'' ?>">
        <i class="fas fa-clipboard-list"></i> Exam Scheduling
    </a>
    <a href="manage_announcements.php" class="<?= $current_page=='manage_announcements.php'?'active':'' ?>">
        <i class="fas fa-bullhorn"></i> Manage Announcements
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
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Assign Instructors to Classroom Sections</h1>
                <p>Assign different instructors to the same courses in different classroom sections</p>
            </div>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile" id="headerProfilePic"
                     onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                <div>
                    <div><?= htmlspecialchars($current_user['username']) ?></div>
                    <small>Administrator</small>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-card">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?= count($unassigned_sections) ?></div>
                    <div class="stat-label">Sections Needing Instructors</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $total_unassigned_sessions ?></div>
                    <div class="stat-label">Total Sessions</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $total_unassigned_students ?></div>
                    <div class="stat-label">Students Affected</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= count($instructors) ?></div>
                    <div class="stat-label">Available Instructors</div>
                </div>
            </div>
        </div>

        <!-- Display Error/Success Messages -->
        <?php if($message): ?>
            <div class="message <?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'error' ? 'exclamation-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'check-circle') ?>"></i>
                <?= nl2br(htmlspecialchars($message)) ?>
            </div>
        <?php endif; ?>

        <!-- Bulk Assignment Form -->
        <div class="bulk-assignment">
            <h3><i class="fas fa-users"></i> Bulk Instructor Assignment</h3>
            <form method="POST" id="bulkAssignmentForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="bulk-form-grid">
                    <div class="form-group">
                        <label for="bulk_instructor_id" class="required">Instructor:</label>
                        <select name="bulk_instructor_id" id="bulk_instructor_id" class="instructor-select" required>
                            <option value="">Select Instructor</option>
                            <?php foreach($instructors as $instructor): ?>
                                <option value="<?= $instructor['user_id'] ?>">
                                    <?= htmlspecialchars($instructor['username']) ?> (<?= htmlspecialchars($instructor['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulk_course_id">Filter by Course (Optional):</label>
                        <select name="bulk_course_id" id="bulk_course_id" class="instructor-select">
                            <option value="0">All Courses</option>
                            <?php foreach($courses as $course): ?>
                                <option value="<?= $course['course_id'] ?>">
                                    <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulk_section_number">Filter by Section (Optional):</label>
                        <select name="bulk_section_number" id="bulk_section_number" class="instructor-select">
                            <option value="0">All Sections</option>
                            <?php foreach($sections as $section): ?>
                                <option value="<?= $section['section_number'] ?>">
                                    Section <?= $section['section_number'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div style="margin-top: 15px;">
                    <button type="submit" name="bulk_assign_instructor" class="btn btn-primary">
                        <i class="fas fa-user-check"></i>
                        Assign Instructor to Selected Sections
                    </button>
                    <small style="display: block; margin-top: 10px; color: var(--text-secondary);">
                        This will assign the selected instructor to all schedules in the selected sections/courses that don't have an instructor assigned.
                    </small>
                </div>
            </form>
        </div>

        <!-- Individual Section Assignments -->
        <h2 class="page-title">
            <i class="fas fa-calendar-alt"></i>
            Classroom Sections Needing Instructors
            <span style="font-size: 0.9rem; color: var(--text-secondary); margin-left: 10px;">
                (<?= count($unassigned_sections) ?> sections need instructors)
            </span>
        </h2>

        <?php if(empty($unassigned_sections)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>All Sections Have Instructors!</h3>
                <p>All classroom sections have been assigned instructors. Great job!</p>
                <a href="manage_schedules.php" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-calendar-alt"></i> View All Schedules
                </a>
            </div>
        <?php else: ?>
            <?php foreach($unassigned_sections as $section): 
                $section_color_class = 'section-' . (($section['section_number'] - 1) % 5 + 1);
            ?>
                <div class="section-card">
                    <div class="section-header">
                        <div>
                            <div class="section-title">
                                <?= htmlspecialchars($section['course_code']) ?> - <?= htmlspecialchars($section['course_name']) ?>
                            </div>
                            <div>
                                <span class="section-badge <?= $section_color_class ?>">
                                    Section <?= $section['section_number'] ?>
                                </span>
                                <span style="color: var(--text-secondary); font-size: 0.9rem;">
                                    Academic Year: <?= htmlspecialchars($section['academic_year']) ?> | 
                                    Semester: <?= $section['semester'] == '1' ? '1st Semester' : '2nd Semester' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="section-details">
                        <div class="detail-item">
                            <i class="fas fa-door-closed"></i>
                            <div>
                                <div class="detail-label">Classrooms:</div>
                                <div class="detail-value"><?= htmlspecialchars($section['rooms']) ?></div>
                            </div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <div class="detail-label">Weekly Sessions:</div>
                                <div class="detail-value"><?= $section['total_sessions'] ?> sessions</div>
                            </div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-users"></i>
                            <div>
                                <div class="detail-label">Enrolled Students:</div>
                                <div class="detail-value"><?= $section['total_students'] ?> students</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="instructor-info">
                        <div class="instructor-current">
                            <strong>Current Instructor:</strong>
                            <?php if(empty($section['current_instructor_name']) || $section['current_instructor_name'] == 'TBA'): ?>
                                <span class="instructor-badge badge-tba">TBA (To Be Assigned)</span>
                            <?php else: ?>
                                <span class="instructor-badge badge-assigned">
                                    <?= htmlspecialchars($section['current_instructor_name']) ?> (<?= htmlspecialchars($section['current_instructor_email']) ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">
                            <i class="fas fa-info-circle"></i> 
                            Assigning an instructor will update all <?= $section['total_sessions'] ?> sessions in this section
                        </div>
                    </div>
                    
                    <div class="assignment-form">
                        <h4>Assign New Instructor to This Section:</h4>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="course_id" value="<?= $section['course_id'] ?>">
                            <input type="hidden" name="section_number" value="<?= $section['section_number'] ?>">
                            
                            <div class="form-group">
                                <label for="instructor_id_<?= $section['course_id'] ?>_<?= $section['section_number'] ?>" class="required">Select Instructor:</label>
                                <select name="instructor_id" id="instructor_id_<?= $section['course_id'] ?>_<?= $section['section_number'] ?>" class="instructor-select" required>
                                    <option value="">Select Instructor</option>
                                    <?php foreach($instructors as $instructor): ?>
                                        <option value="<?= $instructor['user_id'] ?>" 
                                            <?= ($section['current_instructor_name'] == $instructor['username']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($instructor['username']) ?> (<?= htmlspecialchars($instructor['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="assignment-actions">
                                <button type="submit" name="assign_instructor" class="btn btn-primary">
                                    <i class="fas fa-user-check"></i>
                                    Assign to Section <?= $section['section_number'] ?>
                                </button>
                                <button type="button" class="btn btn-secondary" 
                                        onclick="resetAssignment(<?= $section['course_id'] ?>, <?= $section['section_number'] ?>)">
                                    <i class="fas fa-redo"></i>
                                    Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Hamburger toggle
function toggleMenu(){
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

// Reset assignment form
function resetAssignment(courseId, sectionNumber) {
    const select = document.getElementById('instructor_id_' + courseId + '_' + sectionNumber);
    if(select) {
        select.selectedIndex = 0;
    }
}

// Bulk assignment confirmation
document.getElementById('bulkAssignmentForm').addEventListener('submit', function(e) {
    const instructorSelect = document.getElementById('bulk_instructor_id');
    const courseSelect = document.getElementById('bulk_course_id');
    const sectionSelect = document.getElementById('bulk_section_number');
    
    if(!instructorSelect.value) {
        e.preventDefault();
        alert('Please select an instructor for bulk assignment.');
        return;
    }
    
    const instructorName = instructorSelect.options[instructorSelect.selectedIndex].text;
    const courseName = courseSelect.value != 0 ? courseSelect.options[courseSelect.selectedIndex].text : 'All Courses';
    const sectionName = sectionSelect.value != 0 ? sectionSelect.options[sectionSelect.selectedIndex].text : 'All Sections';
    
    const confirmation = confirm(
        `📚 Bulk Instructor Assignment:\n\n` +
        `• Instructor: ${instructorName}\n` +
        `• Course Filter: ${courseName}\n` +
        `• Section Filter: ${sectionName}\n\n` +
        `This will assign the instructor to ALL sessions in the selected sections/courses.\n` +
        `Any existing instructor assignments will be replaced.\n\n` +
        `Continue?`
    );
    
    if(!confirmation) {
        e.preventDefault();
    }
});

// Individual assignment confirmation
document.querySelectorAll('.assignment-form form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const courseCode = this.closest('.section-card').querySelector('.section-title').textContent.trim();
        const sectionNumber = this.querySelector('input[name="section_number"]').value;
        const instructorSelect = this.querySelector('select[name="instructor_id"]');
        const instructorName = instructorSelect.options[instructorSelect.selectedIndex].text;
        
        const confirmation = confirm(
            `📚 Assign Instructor to Section ${sectionNumber}\n\n` +
            `• Course: ${courseCode}\n` +
            `• Section: ${sectionNumber}\n` +
            `• Instructor: ${instructorName}\n\n` +
            `This will update all sessions in this section with the selected instructor.\n` +
            `Any existing instructor will be replaced.\n\n` +
            `Continue?`
        );
        
        if(!confirmation) {
            e.preventDefault();
        }
    });
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e){
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.querySelector('.menu-btn');
        if(window.innerWidth <= 768 && sidebar.classList.contains('active') && 
           !sidebar.contains(e.target) && !menuBtn.contains(e.target)){
            sidebar.classList.remove('active');
            document.querySelector('.overlay').classList.remove('active');
        }
    });
    
    // Profile picture fallback
    document.querySelectorAll('img').forEach(img => {
        img.addEventListener('error', function() {
            if (!this.src.includes('default_profile.png')) {
                this.src = '../assets/default_profile.png';
            }
        });
    });
    
    // Auto-focus first select in each form
    document.querySelectorAll('.assignment-form').forEach(form => {
        const select = form.querySelector('.instructor-select');
        if(select) {
            select.focus();
        }
    });
});
</script>

</body>
</html>