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

// Function to get profile picture path for admin
function getAdminProfilePicturePath($profile_picture) {
    if (empty($profile_picture)) {
        return '../assets/default_profile.png';
    }
    
    // Check multiple possible locations for admin profile pictures
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

// Get profile image path
$profile_img_path = getAdminProfilePicturePath($current_user['profile_picture'] ?? '');

// Initialize message variables
$message = "";
$message_type = "success";

// Handle Add/Edit/Delete
$editing = false;
$edit_schedule_id = 0;
$edit_schedule = [];

// Delete schedule
if(isset($_POST['delete_schedule'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $schedule_id = (int)$_POST['schedule_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM schedule WHERE schedule_id=?");
            $stmt->execute([$schedule_id]);
            $message = "Schedule deleted successfully!";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Edit schedule
if(isset($_GET['edit'])){
    $edit_schedule_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM schedule WHERE schedule_id=?");
    $stmt->execute([$edit_schedule_id]);
    $edit_schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    if($edit_schedule){
        $editing = true;
    }
}

// Add/Edit schedule
if(isset($_POST['add_schedule']) || isset($_POST['edit_schedule'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $course_id = (int)$_POST['course_id'];
        $instructor_id = (int)$_POST['instructor_id'];
        $room_id = (int)$_POST['room_id'];
        $academic_year = trim($_POST['academic_year']);
        $semester = trim($_POST['semester']);
        $day_of_week = trim($_POST['day_of_week']);
        $start_time = trim($_POST['start_time']);
        $end_time = trim($_POST['end_time']);
        
        // Validate inputs
        if(empty($course_id) || empty($instructor_id) || empty($room_id) || 
           empty($academic_year) || empty($semester) || empty($day_of_week) || 
           empty($start_time) || empty($end_time)){
            $message = "Please fill in all required fields!";
            $message_type = "error";
        } elseif($start_time >= $end_time){
            $message = "Start time must be earlier than end time!";
            $message_type = "error";
        } else {
            // Check for schedule conflicts
            try {
                $conflict_sql = "SELECT schedule_id FROM schedule WHERE 
                    room_id = ? AND day_of_week = ? AND 
                    ((start_time <= ? AND end_time > ?) OR 
                    (start_time < ? AND end_time >= ?) OR 
                    (start_time >= ? AND end_time <= ?))";
                
                if($editing && $edit_schedule_id){
                    $conflict_sql .= " AND schedule_id != ?";
                    $conflict_params = [$room_id, $day_of_week, $start_time, $start_time, 
                                       $end_time, $end_time, $start_time, $end_time, 
                                       $edit_schedule_id];
                } else {
                    $conflict_params = [$room_id, $day_of_week, $start_time, $start_time, 
                                       $end_time, $end_time, $start_time, $end_time];
                }
                
                $conflict_stmt = $pdo->prepare($conflict_sql);
                $conflict_stmt->execute($conflict_params);
                $conflict = $conflict_stmt->fetch();
                
                if($conflict){
                    $message = "Schedule conflict: Room already booked for this time slot!";
                    $message_type = "error";
                } else {
                    // Check instructor availability
                    $instructor_sql = "SELECT schedule_id FROM schedule WHERE 
                        instructor_id = ? AND day_of_week = ? AND 
                        ((start_time <= ? AND end_time > ?) OR 
                        (start_time < ? AND end_time >= ?) OR 
                        (start_time >= ? AND end_time <= ?))";
                    
                    if($editing && $edit_schedule_id){
                        $instructor_sql .= " AND schedule_id != ?";
                        $instructor_params = [$instructor_id, $day_of_week, $start_time, $start_time, 
                                             $end_time, $end_time, $start_time, $end_time, 
                                             $edit_schedule_id];
                    } else {
                        $instructor_params = [$instructor_id, $day_of_week, $start_time, $start_time, 
                                             $end_time, $end_time, $start_time, $end_time];
                    }
                    
                    $instructor_stmt = $pdo->prepare($instructor_sql);
                    $instructor_stmt->execute($instructor_params);
                    $instructor_conflict = $instructor_stmt->fetch();
                    
                    if($instructor_conflict){
                        $message = "Schedule conflict: Instructor already has a class at this time!";
                        $message_type = "error";
                    } else {
                        // Save the schedule
                        if(isset($_POST['edit_schedule'])){
                            $stmt = $pdo->prepare("UPDATE schedule SET 
                                course_id=?, instructor_id=?, room_id=?, 
                                academic_year=?, semester=?, day_of_week=?, 
                                start_time=?, end_time=? 
                                WHERE schedule_id=?");
                            $params = [$course_id, $instructor_id, $room_id, 
                                      $academic_year, $semester, $day_of_week, 
                                      $start_time, $end_time, $edit_schedule_id];
                            $stmt->execute($params);
                            $message = "Schedule updated successfully!";
                            $message_type = "success";
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO schedule 
                                (course_id, instructor_id, room_id, academic_year, 
                                semester, day_of_week, start_time, end_time) 
                                VALUES (?,?,?,?,?,?,?,?)");
                            $params = [$course_id, $instructor_id, $room_id, 
                                      $academic_year, $semester, $day_of_week, 
                                      $start_time, $end_time];
                            $stmt->execute($params);
                            $message = "Schedule added successfully!";
                            $message_type = "success";
                            
                            // Clear form for new entry
                            $editing = false;
                            $edit_schedule_id = 0;
                            $edit_schedule = [];
                        }
                    }
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Fetch dropdowns and schedules
$courses = $pdo->query("SELECT * FROM courses ORDER BY course_name")->fetchAll(PDO::FETCH_ASSOC);
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_name")->fetchAll(PDO::FETCH_ASSOC);
$instructors = $pdo->query("SELECT * FROM users WHERE role='instructor' ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

$schedules = $pdo->query("
    SELECT s.*, c.course_name, r.room_name, u.username AS instructor_name
    FROM schedule s
    LEFT JOIN courses c ON s.course_id = c.course_id
    LEFT JOIN rooms r ON s.room_id = r.room_id
    LEFT JOIN users u ON s.instructor_id = u.user_id
    ORDER BY s.academic_year DESC, s.semester, s.day, s.start_time
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
<title>Manage Schedules - DKU Scheduler</title>
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

/* ================= Form Styling ================= */
.form-section-title {
    color: var(--text-primary);
    margin-bottom: 15px;
    font-size: 1.2rem;
    font-weight: 600;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Auto Generate Button */
.auto-generate-btn {
    background: linear-gradient(135deg, #1abc9c, #16a085);
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 25px;
    font-size: 1rem;
}
.auto-generate-btn:hover {
    background: linear-gradient(135deg, #16a085, #149174);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(26, 188, 156, 0.3);
}

/* Required field indicator */
.required::after {
    content: " *";
    color: #ef4444;
}

/* Schedule Form */
.schedule-form-wrapper {
    background: var(--bg-card);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    margin-bottom: 30px;
}

.schedule-form {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.form-group {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.schedule-form select, .schedule-form input {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    font-size: 14px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    transition: all 0.3s;
}
.schedule-form select:focus, .schedule-form input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}

/* Conflict indicator */
.conflict-checking {
    color: #f59e0b;
    font-size: 0.875rem;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

.conflict-error {
    color: #dc2626;
    font-size: 0.875rem;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

.conflict-success {
    color: #10b981;
    font-size: 0.875rem;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

select.valid {
    border-color: #10b981 !important;
    background: linear-gradient(90deg, var(--bg-secondary), #d1fae5) !important;
}

select.invalid {
    border-color: #dc2626 !important;
    background: linear-gradient(90deg, var(--bg-secondary), #fee2e2) !important;
}

select.checking {
    border-color: #f59e0b !important;
    background: linear-gradient(90deg, var(--bg-secondary), #fef3c7) !important;
}

/* Button Styles */
.btn { 
    padding: 12px 20px; 
    border-radius: 8px; 
    border: none; 
    cursor: pointer; 
    font-weight: 600; 
    font-size: 14px;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary { 
    background: #2563eb; 
    color: #fff; 
}
.btn-primary:hover { 
    background: #1d4ed8; 
    transform: translateY(-1px); 
}
.btn-primary:disabled {
    background: #94a3b8;
    cursor: not-allowed;
    transform: none;
}
.btn-danger { 
    background: #dc2626; 
    color: #fff; 
}
.btn-danger:hover { 
    background: #b91c1c; 
    transform: translateY(-1px); 
}

.cancel-btn { 
    text-decoration: none; 
    color: #dc2626; 
    margin-left: 10px; 
    font-weight: 500;
    padding: 10px 15px;
    border-radius: 8px;
    border: 1px solid #dc2626;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.cancel-btn:hover {
    background: #dc2626;
    color: white;
}

.form-actions { 
    display: flex; 
    gap: 10px; 
    align-items: center;
    grid-column: 1 / -1;
    margin-top: 10px;
}

/* ================= Table Styling ================= */
.table-section-title {
    color: var(--text-primary);
    margin: 30px 0 15px;
    font-size: 1.2rem;
    font-weight: 600;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-wrapper {
    position: relative; 
    background: var(--bg-card); 
    padding: 20px; 
    border-radius: 12px; 
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 6px var(--shadow-color);
    margin-top: 20px;
}

.table-container { 
    width: 100%; 
    overflow-x: auto; 
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: var(--border-color) var(--bg-secondary);
    max-height: 500px;
    overflow-y: auto;
}
.table-container::-webkit-scrollbar {
    height: 12px;
    width: 12px;
}
.table-container::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 6px;
}
.table-container::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 6px;
}
.table-container::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}

.schedule-table { 
    width: 100%; 
    min-width: 1000px; 
    border-collapse: collapse; 
    font-size: 14px; 
}
.schedule-table thead th { 
    position: sticky; 
    top: 0; 
    background: var(--table-header); 
    color: var(--text-sidebar); 
    padding: 15px; 
    text-align: left; 
    font-weight: 700; 
    z-index: 5; 
}
.schedule-table th, .schedule-table td { 
    border-bottom: 1px solid var(--border-color); 
    padding: 15px; 
    color: var(--text-primary);
}
.schedule-table tbody tr:hover { 
    background: var(--hover-color); 
}
.schedule-table tbody tr:nth-child(even) { 
    background: var(--bg-secondary); 
}

/* Day Badges */
.day-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.day-monday { background: #3b82f6; color: white; }
.day-tuesday { background: #8b5cf6; color: white; }
.day-wednesday { background: #10b981; color: white; }
.day-thursday { background: #f59e0b; color: white; }
.day-friday { background: #ef4444; color: white; }

/* Time Display */
.time-display {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: var(--text-primary);
    background: var(--bg-secondary);
    padding: 4px 8px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
}

/* Action Links */
.action-link { 
    text-decoration: none; 
    color: #2563eb; 
    font-weight: 500;
    margin: 0 5px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: color 0.3s;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
}
.action-link:hover { 
    color: #1d4ed8; 
    text-decoration: underline;
}
.action-link.delete { 
    color: #dc2626; 
}
.action-link.delete:hover { 
    color: #b91c1c; 
}

.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* Academic Year header in table */
.year-header {
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb) !important;
    font-size: 1rem !important;
    color: var(--text-primary) !important;
    border-bottom: 2px solid var(--border-color);
}

[data-theme="dark"] .year-header {
    background: linear-gradient(135deg, #374151, #4b5563) !important;
}

/* Loading spinner */
.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* ================= Responsive ================= */
@media(max-width: 1200px){ 
    .main-content{ padding:25px; }
    .content-wrapper { padding: 20px; }
}
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
    
    .schedule-form {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-actions .btn,
    .form-actions .cancel-btn {
        width: 100%;
        margin: 5px 0;
        text-align: center;
        justify-content: center;
    }
    
    /* Mobile table card view */
    .schedule-table, .schedule-table thead, .schedule-table tbody, .schedule-table th, .schedule-table td, .schedule-table tr { 
        display: block; 
        width: 100%; 
    }
    .schedule-table thead tr { 
        position: absolute;
        top: -9999px;
        left: -9999px;
    }
    .schedule-table tr { 
        margin-bottom: 15px; 
        background: var(--bg-card); 
        border-radius: 10px; 
        box-shadow: 0 2px 5px var(--shadow-color); 
        padding: 15px; 
        border: 1px solid var(--border-color);
    }
    .schedule-table td { 
        text-align: right; 
        padding-left: 50%; 
        position: relative; 
        border: none; 
        margin-bottom: 10px;
    }
    .schedule-table td::before { 
        content: attr(data-label); 
        position: absolute; 
        left: 15px; 
        width: 45%; 
        text-align: left; 
        font-weight: bold; 
        color: var(--text-secondary);
    }
    
    .schedule-table td:last-child {
        text-align: center;
        padding-left: 15px;
    }
    .schedule-table td:last-child::before {
        display: none;
    }
    
    .action-buttons {
        justify-content: center;
        gap: 10px;
    }
}

/* Dark mode specific adjustments */
[data-theme="dark"] .auto-generate-btn {
    background: linear-gradient(135deg, #16a085, #149174);
}

[data-theme="dark"] .auto-generate-btn:hover {
    background: linear-gradient(135deg, #149174, #12866d);
}

[data-theme="dark"] .action-link {
    color: #60a5fa;
}

[data-theme="dark"] .action-link:hover {
    color: #3b82f6;
}

[data-theme="dark"] .day-badge {
    opacity: 0.9;
}
</style>
</head>
<body>

<!-- Mobile Topbar -->
<div class="topbar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <span>Manage Schedules</span>
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
    <a href="manage_schedules.php" class="active">
        <i class="fas fa-calendar-alt"></i> Manage Schedule
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
                <h1>Manage Schedules</h1>
                <p>Schedule courses, assign instructors and rooms</p>
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

        <!-- Display Error/Success Messages -->
        <?php if($message): ?>
            <div class="message <?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'error' ? 'exclamation-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'check-circle') ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Auto Generate Button -->
        <form action="auto_generate_schedule.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button type="submit" class="auto-generate-btn">
                <i class="fas fa-robot"></i> Auto Generate Schedule
            </button>
        </form>

        <!-- Form Section -->
        <div class="form-section">
            <div class="form-section-title">
                <i class="fas fa-<?= $editing ? 'edit' : 'plus-circle' ?>"></i>
                <?= $editing ? "Edit Schedule" : "Add New Schedule" ?>
            </div>

            <div class="schedule-form-wrapper">
                <form method="POST" class="schedule-form" id="scheduleForm" onsubmit="return validateForm()">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="form-group">
                        <label class="required">Course:</label>
                        <select name="course_id" id="course-select" required>
                            <option value="">Select Course</option>
                            <?php foreach($courses as $c): ?>
                                <option value="<?= $c['course_id'] ?>" <?= ($editing && isset($edit_schedule['course_id']) && $edit_schedule['course_id']==$c['course_id'])?'selected':'' ?>>
                                    <?= htmlspecialchars($c['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Instructor:</label>
                        <select name="instructor_id" id="instructor-select" required>
                            <option value="">Select Instructor</option>
                            <?php foreach($instructors as $i): ?>
                                <option value="<?= $i['user_id'] ?>" <?= ($editing && isset($edit_schedule['instructor_id']) && $edit_schedule['instructor_id']==$i['user_id'])?'selected':'' ?>>
                                    <?= htmlspecialchars($i['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="instructor-feedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Room:</label>
                        <select name="room_id" id="room-select" required>
                            <option value="">Select Room</option>
                            <?php foreach($rooms as $r): ?>
                                <option value="<?= $r['room_id'] ?>" <?= ($editing && isset($edit_schedule['room_id']) && $edit_schedule['room_id']==$r['room_id'])?'selected':'' ?>>
                                    <?= htmlspecialchars($r['room_name']) ?> (Capacity: <?= $r['capacity'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="room-feedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Academic Year:</label>
                        <input type="text" name="academic_year" id="academic-year" placeholder="e.g., 2024-2025" required 
                               value="<?= $editing && isset($edit_schedule['academic_year']) ? htmlspecialchars($edit_schedule['academic_year']) : date('Y') . '-' . (date('Y') + 1) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Semester:</label>
                        <select name="semester" id="semester-select" required>
                            <option value="">Select Semester</option>
                            <option value="Fall" <?= ($editing && isset($edit_schedule['semester']) && $edit_schedule['semester']=='Fall')?'selected':'' ?>>Fall</option>
                            <option value="Spring" <?= ($editing && isset($edit_schedule['semester']) && $edit_schedule['semester']=='Spring')?'selected':'' ?>>Spring</option>
                            <option value="Summer" <?= ($editing && isset($edit_schedule['semester']) && $edit_schedule['semester']=='Summer')?'selected':'' ?>>Summer</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Day of Week:</label>
                        <select name="day_of_week" id="day-select" required>
                            <?php 
                            $days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
                            foreach($days as $day): 
                            ?>
                                <option value="<?= $day ?>" <?= ($editing && isset($edit_schedule['day_of_week']) && $edit_schedule['day_of_week']==$day)?'selected':'' ?>>
                                    <?= $day ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Start Time:</label>
                        <input type="time" name="start_time" id="start-time" required 
                               value="<?= $editing && isset($edit_schedule['start_time']) ? htmlspecialchars($edit_schedule['start_time']) : '08:00' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="required">End Time:</label>
                        <input type="time" name="end_time" id="end-time" required 
                               value="<?= $editing && isset($edit_schedule['end_time']) ? htmlspecialchars($edit_schedule['end_time']) : '09:30' ?>">
                        <div id="time-feedback"></div>
                    </div>
                    
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit" name="<?= $editing?'edit_schedule':'add_schedule' ?>" id="submit-btn" <?= $editing ? '' : 'disabled' ?>>
                            <i class="fas fa-<?= $editing ? 'save' : 'plus' ?>"></i>
                            <?= $editing ? 'Update Schedule' : 'Add Schedule' ?>
                        </button>
                        <?php if($editing): ?>
                            <a href="manage_schedules.php" class="cancel-btn">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Schedules Table Section -->
        <div class="table-section">
            <div class="table-section-title">
                <i class="fas fa-list"></i>
                Existing Schedules (<?= count($schedules) ?>)
            </div>

            <div class="table-wrapper">
                <div class="table-container">
                    <table class="schedule-table" id="scheduleTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Course</th>
                                <th>Instructor</th>
                                <th>Room</th>
                                <th>Academic Year</th>
                                <th>Semester</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($schedules)): ?>
                                <tr>
                                    <td colspan="9" style="text-align:center; padding:30px; color:var(--text-secondary);">
                                        No schedules found. Add your first schedule above.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $currentYearSem = '';
                                foreach($schedules as $s): 
                                    $yearSem = $s['academic_year'] . ' - ' . $s['semester'];
                                    if($yearSem !== $currentYearSem):
                                        $currentYearSem = $yearSem;
                                ?>
                                <tr class="year-header">
                                    <td colspan="9" style="text-align:left;">
                                        <i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($currentYearSem) ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php 
                                    $day_of_week = isset($s['day_of_week']) && !empty($s['day_of_week']) ? $s['day_of_week'] : 'Not Set';
                                    $dayClass = isset($s['day_of_week']) ? strtolower(str_replace('day', '', $s['day_of_week'])) : 'unknown';
                                ?>
                                <tr data-label="Schedule">
                                    <td data-label="ID"><?= $s['schedule_id'] ?></td>
                                    <td data-label="Course"><?= htmlspecialchars($s['course_name'] ?? 'N/A') ?></td>
                                    <td data-label="Instructor"><?= htmlspecialchars($s['instructor_name'] ?? 'N/A') ?></td>
                                    <td data-label="Room"><?= htmlspecialchars($s['room_name'] ?? 'N/A') ?></td>
                                    <td data-label="Academic Year"><?= htmlspecialchars($s['academic_year'] ?? 'N/A') ?></td>
                                    <td data-label="Semester"><?= htmlspecialchars($s['semester'] ?? 'N/A') ?></td>
                                    <td data-label="Day">
                                        <?php if(isset($s['day_of_week']) && !empty($s['day_of_week'])): ?>
                                            <span class="day-badge day-<?= $dayClass ?>">
                                                <?= htmlspecialchars($day_of_week) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary); font-style:italic;">
                                                Not Set
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Time">
                                        <span class="time-display">
                                            <?= htmlspecialchars($s['start_time'] ?? 'N/A') ?> - <?= htmlspecialchars($s['end_time'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="action-buttons">
                                            <a class="action-link" href="?edit=<?= $s['schedule_id'] ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete(this, '<?= htmlspecialchars(addslashes($s['course_name'] ?? 'Schedule')) ?>')">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="schedule_id" value="<?= $s['schedule_id'] ?>">
                                                <button type="submit" name="delete_schedule" class="action-link delete">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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

// DOM elements
const courseSelect = document.getElementById('course-select');
const instructorSelect = document.getElementById('instructor-select');
const roomSelect = document.getElementById('room-select');
const academicYearInput = document.getElementById('academic-year');
const semesterSelect = document.getElementById('semester-select');
const daySelect = document.getElementById('day-select');
const startTimeInput = document.getElementById('start-time');
const endTimeInput = document.getElementById('end-time');
const instructorFeedback = document.getElementById('instructor-feedback');
const roomFeedback = document.getElementById('room-feedback');
const timeFeedback = document.getElementById('time-feedback');
const submitBtn = document.getElementById('submit-btn');

let scheduleValid = false;

// Check for schedule conflicts
function checkScheduleConflicts() {
    const instructorId = instructorSelect.value;
    const roomId = roomSelect.value;
    const day = daySelect.value;
    const startTime = startTimeInput.value;
    const endTime = endTimeInput.value;
    const editing = <?= $editing ? 'true' : 'false' ?>;
    const scheduleId = <?= $edit_schedule_id ?: 'null' ?>;
    
    // Reset
    instructorSelect.classList.remove('valid', 'invalid', 'checking');
    roomSelect.classList.remove('valid', 'invalid', 'checking');
    scheduleValid = false;
    updateSubmitButton();
    
    if (!instructorId || !roomId || !day || !startTime || !endTime) {
        instructorFeedback.innerHTML = '';
        roomFeedback.innerHTML = '';
        timeFeedback.innerHTML = '';
        scheduleValid = false;
        updateSubmitButton();
        return;
    }
    
    if (startTime >= endTime) {
        timeFeedback.innerHTML = '<span class="conflict-error"><i class="fas fa-exclamation-circle"></i> Start time must be earlier than end time</span>';
        scheduleValid = false;
        updateSubmitButton();
        return;
    }
    
    // Show checking state
    instructorSelect.classList.add('checking');
    roomSelect.classList.add('checking');
    instructorFeedback.innerHTML = '<span class="conflict-checking"><i class="fas fa-spinner fa-spin spinner"></i> Checking instructor availability...</span>';
    roomFeedback.innerHTML = '<span class="conflict-checking"><i class="fas fa-spinner fa-spin spinner"></i> Checking room availability...</span>';
    timeFeedback.innerHTML = '';
    
    // AJAX request
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'check_schedule_conflict.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                
                if (response.available) {
                    instructorSelect.classList.remove('checking');
                    roomSelect.classList.remove('checking');
                    instructorSelect.classList.add('valid');
                    roomSelect.classList.add('valid');
                    instructorFeedback.innerHTML = '<span class="conflict-success"><i class="fas fa-check-circle"></i> Instructor available</span>';
                    roomFeedback.innerHTML = '<span class="conflict-success"><i class="fas fa-check-circle"></i> Room available</span>';
                    scheduleValid = true;
                } else {
                    instructorSelect.classList.remove('checking');
                    roomSelect.classList.remove('checking');
                    instructorSelect.classList.add('invalid');
                    roomSelect.classList.add('invalid');
                    
                    if (response.conflict_type === 'instructor') {
                        instructorFeedback.innerHTML = `<span class="conflict-error"><i class="fas fa-exclamation-circle"></i> ${response.message}</span>`;
                        roomFeedback.innerHTML = '<span class="conflict-success"><i class="fas fa-check-circle"></i> Room available</span>';
                    } else if (response.conflict_type === 'room') {
                        instructorFeedback.innerHTML = '<span class="conflict-success"><i class="fas fa-check-circle"></i> Instructor available</span>';
                        roomFeedback.innerHTML = `<span class="conflict-error"><i class="fas fa-exclamation-circle"></i> ${response.message}</span>`;
                    } else {
                        instructorFeedback.innerHTML = `<span class="conflict-error"><i class="fas fa-exclamation-circle"></i> ${response.message}</span>`;
                        roomFeedback.innerHTML = `<span class="conflict-error"><i class="fas fa-exclamation-circle"></i> ${response.message}</span>`;
                    }
                    scheduleValid = false;
                }
            } catch (e) {
                handleCheckError();
            }
        } else {
            handleCheckError();
        }
        updateSubmitButton();
    };
    
    xhr.onerror = handleCheckError;
    
    function handleCheckError() {
        instructorSelect.classList.remove('checking');
        roomSelect.classList.remove('checking');
        instructorFeedback.innerHTML = '<span class="conflict-error"><i class="fas fa-exclamation-circle"></i> Error checking availability</span>';
        roomFeedback.innerHTML = '<span class="conflict-error"><i class="fas fa-exclamation-circle"></i> Error checking availability</span>';
        scheduleValid = false;
        updateSubmitButton();
    }
    
    xhr.send(`instructor_id=${encodeURIComponent(instructorId)}&room_id=${encodeURIComponent(roomId)}&day=${encodeURIComponent(day)}&start_time=${encodeURIComponent(startTime)}&end_time=${encodeURIComponent(endTime)}&editing=${editing}&schedule_id=${scheduleId}`);
}

// Update submit button state
function updateSubmitButton() {
    const courseId = courseSelect.value;
    const instructorId = instructorSelect.value;
    const roomId = roomSelect.value;
    const academicYear = academicYearInput.value.trim();
    const semester = semesterSelect.value;
    const day = daySelect.value;
    const startTime = startTimeInput.value;
    const endTime = endTimeInput.value;
    
    let enabled = true;
    
    if (!courseId || !instructorId || !roomId || !academicYear || !semester || !day || !startTime || !endTime) {
        enabled = false;
    }
    
    if (!scheduleValid) {
        enabled = false;
    }
    
    if (startTime >= endTime) {
        enabled = false;
    }
    
    submitBtn.disabled = !enabled;
}

// Form validation
function validateForm() {
    const startTime = startTimeInput.value;
    const endTime = endTimeInput.value;
    
    if (startTime >= endTime) {
        alert('Start time must be earlier than end time');
        startTimeInput.focus();
        return false;
    }
    
    if (!scheduleValid) {
        alert('Please fix the schedule conflict errors before submitting');
        return false;
    }
    
    return true;
}

// Confirm delete with course name
function confirmDelete(form, courseName) {
    return confirm(`Are you sure you want to delete the schedule for "${courseName}"? This action cannot be undone.`);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Set active nav
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.sidebar a').forEach(link => {
        const linkPage = link.getAttribute('href');
        if (linkPage === currentPage) {
            link.classList.add('active');
        }
    });
    
    // Event listeners
    instructorSelect.addEventListener('change', checkScheduleConflicts);
    roomSelect.addEventListener('change', checkScheduleConflicts);
    daySelect.addEventListener('change', checkScheduleConflicts);
    startTimeInput.addEventListener('change', checkScheduleConflicts);
    endTimeInput.addEventListener('change', checkScheduleConflicts);
    
    // Input event listeners for validation
    courseSelect.addEventListener('change', updateSubmitButton);
    academicYearInput.addEventListener('input', updateSubmitButton);
    semesterSelect.addEventListener('change', updateSubmitButton);
    
    // Initial validation
    updateSubmitButton();
    
    // If editing a schedule, check conflicts
    <?php if($editing): ?>
        setTimeout(() => {
            checkScheduleConflicts();
        }, 500);
    <?php endif; ?>
    
    // Add data-labels for mobile table view
    const tableHeaders = document.querySelectorAll('#scheduleTable thead th');
    const tableRows = document.querySelectorAll('#scheduleTable tbody tr:not(.year-header)');
    
    tableRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        cells.forEach((cell, index) => {
            if(tableHeaders[index]) {
                cell.setAttribute('data-label', tableHeaders[index].textContent);
            }
        });
    });
    
    // Animate table rows
    tableRows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            row.style.transition = 'all 0.5s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateX(0)';
        }, index * 50);
    });
    
    // Profile picture fallback
    document.querySelectorAll('img').forEach(img => {
        img.addEventListener('error', function() {
            if (!this.src.includes('default_profile.png')) {
                this.src = '../assets/default_profile.png';
            }
        });
    });
    
    // Confirm logout
    document.querySelector('a[href="../logout.php"]')?.addEventListener('click', function(e) {
        if(!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });
    
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
});

// Fallback for broken profile pictures
function handleImageError(img) {
    img.onerror = null;
    img.src = '../assets/default_profile.png';
    return true;
}
</script>

</body>
</html>