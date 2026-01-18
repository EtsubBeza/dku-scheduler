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

// Define exam types
$exam_types = ['Midterm', 'Final', 'Quiz', 'Practical', 'Assignment'];

// Helper function to check for exam conflicts
function checkExamConflicts($pdo, $exam_date, $start_time, $end_time, $room_id, $section_number, $exclude_exam_id = 0) {
    $conflicts = [];
    
    // Check for room conflicts (same room, same time, same section)
    $room_check = $pdo->prepare("
        SELECT exam_id, course_code, exam_type 
        FROM exam_schedules es
        JOIN courses c ON es.course_id = c.course_id
        WHERE es.room_id = ? 
        AND es.exam_date = ? 
        AND es.section_number = ?
        AND NOT (? >= es.end_time OR ? <= es.start_time)
        AND es.exam_id != ?
    ");
    $room_check->execute([$room_id, $exam_date, $section_number, $start_time, $end_time, $exclude_exam_id]);
    $room_conflicts = $room_check->fetchAll();
    
    foreach($room_conflicts as $conflict) {
        $conflicts[] = [
            'type' => 'Room',
            'details' => "Room already booked for {$conflict['exam_type']} exam at this time (Section $section_number)"
        ];
    }
    
    // Check for section conflicts (same section, same time, different room)
    $section_check = $pdo->prepare("
        SELECT exam_id, course_code, exam_type, room_name
        FROM exam_schedules es
        JOIN courses c ON es.course_id = c.course_id
        JOIN rooms r ON es.room_id = r.room_id
        WHERE es.section_number = ? 
        AND es.exam_date = ? 
        AND NOT (? >= es.end_time OR ? <= es.start_time)
        AND es.exam_id != ?
    ");
    $section_check->execute([$section_number, $exam_date, $start_time, $end_time, $exclude_exam_id]);
    $section_conflicts = $section_check->fetchAll();
    
    foreach($section_conflicts as $conflict) {
        $conflicts[] = [
            'type' => 'Section',
            'details' => "Section $section_number already has {$conflict['exam_type']} exam in {$conflict['room_name']} at this time"
        ];
    }
    
    return $conflicts;
}

// Handle Add/Edit Exam Schedule
if(isset($_POST['save_exam'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $exam_id = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0;
        $course_id = (int)$_POST['course_id'];
        $section_number = (int)$_POST['section_number'];
        $exam_type = $_POST['exam_type'];
        $exam_date = $_POST['exam_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $room_id = (int)$_POST['room_id'];
        $academic_year = $_POST['academic_year'];
        $semester = $_POST['semester'];
        $max_students = (int)$_POST['max_students'];
        $is_published = isset($_POST['is_published']) ? 1 : 0;
        
        // For freshman students, we need to set student_type and year
        $student_type = 'regular'; // Freshman are regular students
        $year = 'freshman'; // This will be 'freshman' in the database
        
        // Validate inputs
        if(strtotime($end_time) <= strtotime($start_time)) {
            $message = "End time must be after start time";
            $message_type = "error";
        } elseif(strtotime($exam_date) < strtotime('today')) {
            $message = "Exam date cannot be in the past";
            $message_type = "error";
        } elseif($max_students < 1) {
            $message = "Maximum students must be at least 1";
            $message_type = "error";
        } else {
            // Check for conflicts
            $conflicts = checkExamConflicts($pdo, $exam_date, $start_time, $end_time, $room_id, $section_number, $exam_id);
            
            if(!empty($conflicts)) {
                $conflict_messages = [];
                foreach($conflicts as $conflict) {
                    $conflict_messages[] = "{$conflict['type']} Conflict: {$conflict['details']}";
                }
                $message = "Exam conflicts detected:<br>" . implode("<br>", $conflict_messages);
                $message_type = "error";
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    if($exam_id > 0) {
                        // Update existing exam - ADDED student_type and year
                        $stmt = $pdo->prepare("
                            UPDATE exam_schedules 
                            SET course_id = ?, section_number = ?, exam_type = ?, exam_date = ?, 
                                start_time = ?, end_time = ?, room_id = ?, 
                                academic_year = ?, semester = ?, max_students = ?, 
                                student_type = ?, year = ?, is_published = ?
                            WHERE exam_id = ?
                        ");
                        $stmt->execute([
                            $course_id, $section_number, $exam_type, $exam_date,
                            $start_time, $end_time, $room_id,
                            $academic_year, $semester, $max_students,
                            $student_type, $year, $is_published, $exam_id
                        ]);
                        
                        if($stmt->rowCount() > 0) {
                            $message = "✅ Exam schedule updated successfully!";
                            $message_type = "success";
                        } else {
                            $message = "⚠️ No changes made or exam not found.";
                            $message_type = "warning";
                        }
                    } else {
                        // Insert new exam - ADDED student_type and year
                        $stmt = $pdo->prepare("
                            INSERT INTO exam_schedules 
                            (course_id, section_number, exam_type, exam_date, start_time, end_time, 
                             room_id, academic_year, semester, max_students, 
                             student_type, year, is_published, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $course_id, $section_number, $exam_type, $exam_date,
                            $start_time, $end_time, $room_id,
                            $academic_year, $semester, $max_students,
                            $student_type, $year, $is_published, $_SESSION['user_id']
                        ]);
                        
                        $exam_id = $pdo->lastInsertId();
                        $message = "🎉 Exam schedule created successfully!";
                        $message_type = "success";
                    }
                    
                    $pdo->commit();
                } catch(PDOException $e) {
                    $pdo->rollBack();
                    $message = "❌ Database error: " . $e->getMessage();
                    $message_type = "error";
                }
            }
        }
    }
}

// Handle Delete Exam - FIXED: Use current page instead of hardcoded URL
if(isset($_GET['delete'])){
    $delete_id = (int)$_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM exam_schedules WHERE exam_id = ?");
        $stmt->execute([$delete_id]);
        
        if($stmt->rowCount() > 0) {
            $_SESSION['message'] = "✅ Exam schedule deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "❌ Exam not found.";
            $_SESSION['message_type'] = "error";
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = "❌ Error deleting exam: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    // FIXED: Redirect to current page instead of hardcoded URL
    $current_page = basename($_SERVER['PHP_SELF']);
    header("Location: " . $current_page);
    exit;
}

// Handle Publish/Unpublish Exam - FIXED: Use current page
if(isset($_GET['publish'])) {
    $exam_id = (int)$_GET['publish'];
    $action = $_GET['action'];
    
    try {
        if($action == 'publish') {
            $stmt = $pdo->prepare("UPDATE exam_schedules SET is_published = 1 WHERE exam_id = ?");
            $msg = "✅ Exam published successfully! Students can now see it.";
        } elseif($action == 'unpublish') {
            $stmt = $pdo->prepare("UPDATE exam_schedules SET is_published = 0 WHERE exam_id = ?");
            $msg = "✅ Exam unpublished successfully! Students can no longer see it.";
        }
        
        if(isset($stmt)) {
            $stmt->execute([$exam_id]);
            
            if($stmt->rowCount() > 0) {
                $_SESSION['message'] = $msg;
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "❌ Exam not found.";
                $_SESSION['message_type'] = "error";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = "❌ Error: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    // FIXED: Redirect to current page
    $current_page = basename($_SERVER['PHP_SELF']);
    header("Location: " . $current_page);
    exit;
}

// Handle Bulk Exam Scheduling for all sections
if(isset($_POST['bulk_schedule_exam'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "❌ Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $course_ids = isset($_POST['course_ids']) ? $_POST['course_ids'] : [];
        $exam_type = $_POST['bulk_exam_type'];
        $exam_date = $_POST['bulk_exam_date'];
        $start_time = $_POST['bulk_start_time'];
        $end_time = $_POST['bulk_end_time'];
        $academic_year = $_POST['bulk_academic_year'];
        $semester = $_POST['bulk_semester'];
        $max_students = (int)$_POST['bulk_max_students'];
        
        // For freshman students
        $student_type = 'regular';
        $year = 'freshman';
        
        if(empty($course_ids)) {
            $message = "❌ Please select at least one course.";
            $message_type = "error";
        } elseif(strtotime($end_time) <= strtotime($start_time)) {
            $message = "❌ End time must be after start time";
            $message_type = "error";
        } else {
            try {
                $pdo->beginTransaction();
                
                $created_count = 0;
                $conflict_count = 0;
                $course_details = [];
                
                // Get all sections that have this course scheduled
                foreach($course_ids as $course_id) {
                    $course_id = (int)$course_id;
                    
                    // Get course info for display
                    $course_stmt = $pdo->prepare("SELECT course_code, course_name FROM courses WHERE course_id = ?");
                    $course_stmt->execute([$course_id]);
                    $course = $course_stmt->fetch();
                    
                    // Get all sections that have this course
                    $sections_stmt = $pdo->prepare("
                        SELECT DISTINCT section_number 
                        FROM schedule 
                        WHERE course_id = ? AND year = 'freshman'
                        ORDER BY section_number
                    ");
                    $sections_stmt->execute([$course_id]);
                    $sections = $sections_stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if(empty($sections)) {
                        continue; // No sections found for this course
                    }
                    
                    // Store course details
                    $course_details[$course_id] = [
                        'code' => $course['course_code'],
                        'name' => $course['course_name'],
                        'sections' => count($sections)
                    ];
                    
                    // Get all available rooms
                    $rooms_stmt = $pdo->query("SELECT room_id, room_name, capacity FROM rooms ORDER BY room_name");
                    $rooms = $rooms_stmt->fetchAll();
                    
                    if(empty($rooms)) {
                        $message = "❌ No rooms available. Please add rooms first.";
                        $message_type = "error";
                        $pdo->rollBack();
                        break;
                    }
                    
                    // Schedule exam for each section
                    foreach($sections as $section_number) {
                        // Find available room for this section
                        $room_assigned = false;
                        
                        foreach($rooms as $room) {
                            // Check if room is available for this exam time
                            $conflict_check = $pdo->prepare("
                                SELECT COUNT(*) as conflicts
                                FROM exam_schedules 
                                WHERE room_id = ? 
                                AND exam_date = ? 
                                AND section_number = ?
                                AND NOT (? >= end_time OR ? <= start_time)
                            ");
                            $conflict_check->execute([$room['room_id'], $exam_date, $section_number, $start_time, $end_time]);
                            $conflicts = $conflict_check->fetchColumn();
                            
                            if($conflicts == 0) {
                                // Room is available, schedule exam - ADDED student_type and year
                                $insert_stmt = $pdo->prepare("
                                    INSERT INTO exam_schedules 
                                    (course_id, section_number, exam_type, exam_date, start_time, end_time, 
                                     room_id, academic_year, semester, max_students, 
                                     student_type, year, is_published, created_by)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
                                ");
                                $insert_stmt->execute([
                                    $course_id, $section_number, $exam_type, $exam_date,
                                    $start_time, $end_time, $room['room_id'],
                                    $academic_year, $semester, $max_students,
                                    $student_type, $year, $_SESSION['user_id']
                                ]);
                                
                                $created_count++;
                                $room_assigned = true;
                                break; // Move to next section
                            }
                        }
                        
                        if(!$room_assigned) {
                            $conflict_count++;
                        }
                    }
                }
                
                $pdo->commit();
                
                // Build detailed success message
                if($created_count > 0) {
                    $message = "<div style='margin-bottom: 10px;'>";
                    $message .= "🎉 <strong>Bulk Exam Scheduling Complete!</strong>";
                    $message .= "</div>";
                    
                    $message .= "<div style='background: var(--success-bg); padding: 15px; border-radius: 8px; margin-bottom: 10px;'>";
                    $message .= "✅ <strong>Successfully scheduled $created_count exams!</strong>";
                    
                    // Add course details
                    $message .= "<div style='margin-top: 10px; font-size: 0.9rem;'>";
                    $message .= "<strong>Courses scheduled:</strong><br>";
                    foreach($course_details as $course_id => $details) {
                        $message .= "• {$details['code']} - {$details['name']} ({$details['sections']} sections)<br>";
                    }
                    $message .= "</div>";
                    
                    $message .= "<div style='margin-top: 10px;'>";
                    $message .= "<strong>Exam Details:</strong><br>";
                    $message .= "• Type: $exam_type<br>";
                    $message .= "• Date: " . date('F j, Y', strtotime($exam_date)) . "<br>";
                    $message .= "• Time: " . date('g:i A', strtotime($start_time)) . " - " . date('g:i A', strtotime($end_time)) . "<br>";
                    $message .= "• Semester: $semester<br>";
                    $message .= "• Academic Year: $academic_year<br>";
                    $message .= "• Student Type: Regular (Freshman)<br>";
                    $message .= "</div>";
                    
                    if($conflict_count > 0) {
                        $message .= "<div style='margin-top: 10px; padding: 10px; background: var(--warning-bg); border-radius: 5px;'>";
                        $message .= "⚠️ <strong>Note:</strong> Could not schedule $conflict_count exams due to room conflicts.";
                        $message .= "</div>";
                        $message_type = "success";
                    } else {
                        $message_type = "success";
                    }
                    
                    $message .= "</div>";
                    
                    // Add auto-refresh notice
                    $message .= "<div style='font-size: 0.85rem; color: var(--text-secondary); margin-top: 10px;'>";
                    $message .= "<i class='fas fa-sync-alt'></i> Page will refresh in 5 seconds to show updated schedule...";
                    $message .= "</div>";
                    
                    // Auto-refresh page after 5 seconds
                    echo "<script>setTimeout(function() { location.reload(); }, 5000);</script>";
                } else {
                    $message = "❌ No exams could be scheduled due to room conflicts.";
                    $message_type = "error";
                }
                
            } catch(PDOException $e) {
                $pdo->rollBack();
                $message = "❌ Database error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Check for session messages
if(isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Handle Edit - Load exam data
$edit_exam = null;
if(isset($_GET['edit'])){
    $edit_id = (int)$_GET['edit'];
    
    $stmt = $pdo->prepare("
        SELECT es.*, c.course_code, c.course_name, r.room_name, r.capacity
        FROM exam_schedules es
        JOIN courses c ON es.course_id = c.course_id
        JOIN rooms r ON es.room_id = r.room_id
        WHERE es.exam_id = ?
    ");
    $stmt->execute([$edit_id]);
    $edit_exam = $stmt->fetch();
    
    if(!$edit_exam) {
        $message = "❌ Exam not found.";
        $message_type = "error";
    }
}

// Fetch data for dropdowns
$freshman_courses = $pdo->query("
    SELECT DISTINCT c.course_id, c.course_code, c.course_name 
    FROM courses c 
    JOIN schedule s ON c.course_id = s.course_id 
    WHERE (c.is_freshman = 1 OR s.year = 'freshman')
    ORDER BY c.course_code
")->fetchAll();

$rooms = $pdo->query("SELECT room_id, room_name, capacity FROM rooms ORDER BY room_name")->fetchAll();

// Fetch all sections
$sections_stmt = $pdo->query("
    SELECT DISTINCT COALESCE(section_number, 1) as section_number 
    FROM schedule 
    WHERE year = 'freshman' 
    ORDER BY section_number
");
$all_sections = $sections_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch all exam schedules for freshman - UPDATED to include student_type and year
$exams_stmt = $pdo->prepare("
    SELECT es.*, 
           c.course_code, c.course_name,
           r.room_name, r.capacity,
           COUNT(DISTINCT e.student_id) as registered_count
    FROM exam_schedules es
    JOIN courses c ON es.course_id = c.course_id
    JOIN rooms r ON es.room_id = r.room_id
    LEFT JOIN enrollments e ON e.course_id = es.course_id 
        AND e.year = 'freshman'
        AND e.schedule_id IN (
            SELECT schedule_id 
            FROM schedule 
            WHERE course_id = es.course_id 
            AND COALESCE(section_number, 1) = es.section_number
            AND year = 'freshman'
        )
    WHERE (es.student_type = 'regular' AND es.year = 'freshman')
       OR (es.student_type IS NULL AND es.year IS NULL)
    GROUP BY es.exam_id
    ORDER BY es.exam_date DESC, es.start_time, es.section_number
");
$exams_stmt->execute();
$exams = $exams_stmt->fetchAll();

// Calculate statistics
$total_exams = count($exams);
$upcoming_exams = 0;
$published_exams = 0;
$today = date('Y-m-d');

foreach($exams as $exam) {
    if($exam['exam_date'] >= $today) {
        $upcoming_exams++;
    }
    if($exam['is_published'] == 1) {
        $published_exams++;
    }
}

// Count exams by type
$exam_type_counts = [];
foreach($exam_types as $type) {
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM exam_schedules 
        WHERE exam_type = ? 
        AND (student_type = 'regular' AND year = 'freshman' OR student_type IS NULL)
    ");
    $count_stmt->execute([$type]);
    $exam_type_counts[$type] = $count_stmt->fetchColumn();
}

// Count exams by section
$section_counts = [];
foreach($all_sections as $section) {
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM exam_schedules 
        WHERE section_number = ? 
        AND (student_type = 'regular' AND year = 'freshman' OR student_type IS NULL)
    ");
    $count_stmt->execute([$section]);
    $section_counts[$section] = $count_stmt->fetchColumn();
}

// Fetch pending approvals count for sidebar
$pending_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_approved = 0");
$pending_approvals = $pending_stmt->fetchColumn() ?: 0;

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Freshman Exam Scheduling - DKU Scheduler</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- FullCalendar CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
* { box-sizing: border-box; margin:0; padding:0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

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

/* ================= University Header ================= */
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

/* Adjust elements for university header */
.topbar {
    top: 60px !important;
}

.sidebar {
    top: 60px !important;
    height: calc(100% - 60px) !important;
}

.overlay {
    top: 60px;
    height: calc(100% - 60px);
}

.main-content {
    margin-top: 60px;
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

/* ================= Topbar for Mobile ================= */
.topbar {
    display: none;
    position: fixed; 
    top: 60px; 
    left: 0; 
    width: 100%;
    background: var(--bg-sidebar); 
    color: var(--text-sidebar);
    padding: 12px 20px;
    z-index: 1200;
    justify-content: space-between; 
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.menu-btn {
    font-size: 26px;
    background: #1abc9c;
    border: none; 
    color: var(--text-sidebar);
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 8px;
    font-weight: 600;
    transition: background 0.3s, transform 0.2s;
}

.menu-btn:hover { 
    background: #159b81; 
    transform: translateY(-2px); 
}

/* ================= Sidebar ================= */
.sidebar { 
    position: fixed; 
    top: 60px; 
    left: 0; 
    width: 250px; 
    height: calc(100% - 60px);
    background: var(--bg-sidebar); 
    color: var(--text-sidebar);
    z-index: 1100;
    transition: transform 0.3s ease;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 2px 0 10px rgba(0,0,0,0.2);
}

.sidebar.hidden { 
    transform: translateX(-260px); 
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

/* Sidebar Title */
.sidebar h2 {
    text-align: center;
    color: var(--text-sidebar);
    margin-bottom: 25px;
    font-size: 22px;
    padding: 0 20px;
    flex-shrink: 0;
}

/* Sidebar Links */
.sidebar a { 
    display: block; 
    padding: 12px 20px; 
    color: var(--text-sidebar); 
    text-decoration: none; 
    transition: all 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    flex-shrink: 0;
}

.sidebar a:hover, .sidebar a.active { 
    background: #1abc9c; 
    color: white; 
    padding-left: 25px;
}

/* Pending Badge */
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

[data-theme="dark"] .pending-badge {
    background: #dc2626;
}

/* ================= Overlay ================= */
.overlay {
    position: fixed; 
    top: 60px; 
    left: 0; 
    width: 100%; 
    height: calc(100% - 60px);
    background: rgba(0,0,0,0.4); 
    z-index: 1050;
    display: none; 
    opacity: 0; 
    transition: opacity 0.3s ease;
}

.overlay.active { 
    display: block; 
    opacity: 1; 
}

/* ================= Main Content ================= */
.main-content { 
    margin-left: 250px; 
    padding: 30px;
    min-height: 100vh;
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
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    font-weight: 500;
    animation: slideIn 0.3s ease;
    box-shadow: 0 4px 6px var(--shadow-color);
    border-left: 4px solid;
    position: relative;
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
    margin-right: 10px;
}

.message strong {
    display: inline-block;
    margin-bottom: 5px;
}

/* ================= Stats Cards ================= */
.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--bg-secondary);
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    border: 1px solid var(--border-color);
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px var(--shadow-color);
}

.stat-icon {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: var(--primary-color);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

/* ================= Distribution Cards ================= */
.distribution-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.distribution-card {
    background: var(--bg-card);
    padding: 20px;
    border-radius: 10px;
    border: 1px solid var(--border-color);
}

.distribution-card h3 {
    color: var(--text-primary);
    margin-bottom: 15px;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.type-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.type-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 15px;
    background: var(--bg-secondary);
    border-radius: 20px;
    color: var(--text-primary);
    font-weight: 500;
    border: 1px solid var(--border-color);
}

.type-badge .count {
    background: var(--primary-color);
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Section badges */
.section-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
    margin-right: 5px;
}

.section-1 { background: var(--section-1); }
.section-2 { background: var(--section-2); }
.section-3 { background: var(--section-3); }
.section-4 { background: var(--section-4); }
.section-5 { background: var(--section-5); }

/* ================= Bulk Exam Scheduling ================= */
.bulk-scheduling {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border-left: 4px solid var(--primary-color);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    color: var(--text-primary);
}

[data-theme="dark"] .bulk-scheduling {
    background: linear-gradient(135deg, #1e3a8a, #1e40af);
    color: #dbeafe;
}

.bulk-scheduling h3 {
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

/* ================= Calendar Card ================= */
.calendar-card {
    background: var(--bg-card);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 30px;
    border: 1px solid var(--border-color);
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    color: white;
}

.calendar-header h3 {
    font-size: 1.2rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

#examCalendar {
    padding: 15px;
    background: var(--bg-card);
}

/* ================= Exam Table ================= */
.exam-table-container {
    background: var(--bg-card);
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--border-color);
    margin-bottom: 30px;
}

.table-header {
    padding: 15px 20px;
    background: var(--table-header);
    color: var(--text-sidebar);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h3 {
    font-size: 1.2rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.exam-table {
    width: 100%;
    border-collapse: collapse;
}

.exam-table th {
    padding: 15px;
    text-align: left;
    background: var(--table-header);
    color: var(--text-sidebar);
    font-weight: 600;
    border-bottom: 2px solid var(--border-color);
}

.exam-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
}

.exam-table tr:hover {
    background: var(--hover-color);
}

.exam-table tr:last-child td {
    border-bottom: none;
}

/* Student Type Badge */
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

.student-type-badge.freshman {
    background: rgba(6, 182, 212, 0.1);
    color: #06b6d4;
    border: 1px solid rgba(6, 182, 212, 0.2);
}

/* Year Badge */
.year-badge {
    display: inline-block;
    padding: 2px 8px;
    margin-left: 5px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 600;
    background: #06b6d4;
    color: white;
}

/* ================= Button Styles ================= */
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

.btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}
.btn-success:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-2px);
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger-color), var(--danger-hover));
    color: white;
}
.btn-danger:hover {
    background: linear-gradient(135deg, var(--danger-hover), #b91c1c);
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

.btn-sm {
    padding: 8px 15px;
    font-size: 0.85rem;
    min-width: auto;
}

.btn-group {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* ================= Badges ================= */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-success {
    background: #d1fae5;
    color: #065f46;
}

[data-theme="dark"] .badge-success {
    background: #064e3b;
    color: #a7f3d0;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

[data-theme="dark"] .badge-warning {
    background: #78350f;
    color: #fde68a;
}

.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}

[data-theme="dark"] .badge-danger {
    background: #7f1d1d;
    color: #fecaca;
}

.badge-secondary {
    background: #f3f4f6;
    color: #6b7280;
}

[data-theme="dark"] .badge-secondary {
    background: #374151;
    color: #d1d5db;
}

/* Exam type badges */
.exam-badge-midterm {
    background: #3b82f6;
    color: white;
}
.exam-badge-final {
    background: #ef4444;
    color: white;
}
.exam-badge-quiz {
    background: #10b981;
    color: white;
}
.exam-badge-practical {
    background: #f59e0b;
    color: white;
}
.exam-badge-assignment {
    background: #8b5cf6;
    color: white;
}

/* ================= Modal Styles ================= */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 2000;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-card);
    padding: 30px;
    border-radius: 15px;
    max-width: 800px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
    color: var(--text-primary);
    font-size: 1.4rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-close {
    background: transparent;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 5px;
    border-radius: 5px;
    transition: all 0.3s;
}

.modal-close:hover {
    background: var(--hover-color);
    color: var(--danger-color);
}

/* ================= Form Styles ================= */
.form-group {
    margin-bottom: 20px;
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

.form-control {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 1rem;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

/* ================= Course Selection ================= */
.course-selection {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 15px;
    background: var(--bg-secondary);
}

.course-item {
    display: flex;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid var(--border-color);
    transition: background 0.3s;
}

.course-item:last-child {
    border-bottom: none;
}

.course-item:hover {
    background: var(--hover-color);
}

.course-item input[type="checkbox"] {
    margin-right: 10px;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.course-info {
    flex: 1;
}

.course-code {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.course-name {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-top: 2px;
}

.freshman-badge {
    display: inline-block;
    padding: 3px 8px;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 8px;
}

/* ================= Empty State ================= */
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

/* ================= Progress Bar ================= */
.progress-container {
    width: 100px;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: var(--bg-secondary);
    border-radius: 4px;
    overflow: hidden;
    margin-top: 5px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
    border-radius: 4px;
    transition: width 0.6s ease;
}

/* ================= Responsive Design ================= */
@media(max-width: 768px){
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
    
    .topbar{ 
        display: flex; 
        top: 60px;
    }
    
    .sidebar{ 
        transform: translateX(-100%); 
        top: 120px;
        height: calc(100% - 120px) !important;
    }
    
    .sidebar.active{ 
        transform: translateX(0); 
    }
    
    .overlay {
        top: 120px;
        height: calc(100% - 120px);
    }
    
    .main-content{ 
        margin-left: 0; 
        padding: 15px;
        padding-top: 140px;
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
    
    .stats-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .distribution-cards {
        grid-template-columns: 1fr;
    }
    
    .bulk-form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .calendar-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .table-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
    
    .modal-content {
        padding: 15px;
    }
    
    .exam-table {
        display: block;
        overflow-x: auto;
    }
}
</style>
</head>
<body>

<!-- University Header -->
<div class="university-header">
    <div class="header-left">
        <img src="../assets/images/dku logo.jpg" alt="Debark University Logo" class="dku-logo-img">
        <div class="system-title">Debark University Class Scheduling System</div>
    </div>
    <div class="header-right">
        Freshman Exam Scheduling
    </div>
</div>

<!-- Mobile Topbar -->
<div class="topbar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <span>Freshman Exams</span>
</div>

<!-- Overlay for Mobile -->
<div class="overlay" onclick="toggleMenu()"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Scrollable content wrapper -->
    <div class="sidebar-content">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic"
                 onerror="this.onerror=null; this.src='../assets/default_profile.png';">
            <p><?= htmlspecialchars($current_user['username']) ?></p>
        </div>
        <h2>Admin Dashboard</h2>
        
        <!-- Navigation Container -->
        <nav>
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
            <a href="assign_instructors.php">
                <i class="fas fa-chalkboard-teacher"></i> Assign Instructors
            </a>
            <a href="admin_exam_schedules.php" class="active">
                <i class="fas fa-clipboard-list"></i> Exam Scheduling
            </a>
            <a href="manage_announcements.php">
                <i class="fas fa-bullhorn"></i> Manage Announcements
            </a>
            <a href="edit_profile.php">
                <i class="fas fa-user-edit"></i> Edit Profile
            </a>
            <a href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>
</div>

<!-- Exam Modal -->
<div id="examModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus"></i> <?= $edit_exam ? 'Edit Exam Schedule' : 'Schedule New Exam' ?></h3>
            <button class="modal-close" onclick="closeExamModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" id="examForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="exam_id" value="<?= $edit_exam['exam_id'] ?? '' ?>">
                
                <!-- Hidden fields for student type and year -->
                <input type="hidden" name="student_type" value="regular">
                <input type="hidden" name="year" value="freshman">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_id" class="required">Course</label>
                        <select class="form-control" id="course_id" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach($freshman_courses as $course): ?>
                                <option value="<?= $course['course_id'] ?>" 
                                    <?= ($edit_exam['course_id'] ?? '') == $course['course_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="section_number" class="required">Section</label>
                        <select class="form-control" id="section_number" name="section_number" required>
                            <option value="">Select Section</option>
                            <?php foreach($all_sections as $section): ?>
                                <option value="<?= $section ?>" 
                                    <?= ($edit_exam['section_number'] ?? '') == $section ? 'selected' : '' ?>>
                                    Section <?= $section ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="exam_type" class="required">Exam Type</label>
                        <select class="form-control" id="exam_type" name="exam_type" required>
                            <option value="">Select Type</option>
                            <?php foreach($exam_types as $type): ?>
                                <option value="<?= $type ?>" 
                                    <?= ($edit_exam['exam_type'] ?? '') == $type ? 'selected' : '' ?>>
                                    <?= $type ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="exam_date" class="required">Exam Date</label>
                        <input type="date" class="form-control" id="exam_date" name="exam_date" 
                               value="<?= $edit_exam['exam_date'] ?? date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_time" class="required">Start Time</label>
                        <input type="time" class="form-control" id="start_time" name="start_time" 
                               value="<?= $edit_exam['start_time'] ?? '09:00' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time" class="required">End Time</label>
                        <input type="time" class="form-control" id="end_time" name="end_time" 
                               value="<?= $edit_exam['end_time'] ?? '10:30' ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="room_id" class="required">Room</label>
                        <select class="form-control" id="room_id" name="room_id" required onchange="updateRoomCapacity()">
                            <option value="">Select Room</option>
                            <?php foreach($rooms as $room): ?>
                                <option value="<?= $room['room_id'] ?>" 
                                    <?= ($edit_exam['room_id'] ?? '') == $room['room_id'] ? 'selected' : '' ?>
                                    data-capacity="<?= $room['capacity'] ?>">
                                    <?= htmlspecialchars($room['room_name']) ?> (Capacity: <?= $room['capacity'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="roomCapacity" style="display:block; margin-top:0.5rem; color: var(--text-secondary);"></small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="academic_year" class="required">Academic Year</label>
                        <input type="text" class="form-control" id="academic_year" name="academic_year" 
                               value="<?= $edit_exam['academic_year'] ?? date('Y') . '-' . (date('Y') + 1) ?>" 
                               placeholder="e.g., 2024-2025" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="semester" class="required">Semester</label>
                        <select class="form-control" id="semester" name="semester" required>
                            <option value="1st Semester" <?= ($edit_exam['semester'] ?? '') == '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
                            <option value="2nd Semester" <?= ($edit_exam['semester'] ?? '') == '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_students" class="required">Maximum Students</label>
                        <input type="number" class="form-control" id="max_students" name="max_students" 
                               value="<?= $edit_exam['max_students'] ?? 50 ?>" min="1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="is_published" name="is_published" value="1" 
                               <?= ($edit_exam['is_published'] ?? 0) == 1 ? 'checked' : '' ?>>
                        Publish this exam (make visible to students)
                    </label>
                    <small style="color: var(--text-secondary); font-size: 0.875rem;">
                        <i class="fas fa-info-circle"></i> When published, students can see this exam in their schedules.
                    </small>
                </div>
                
                <!-- Student Type Info -->
                <div class="form-group" style="background: rgba(6, 182, 212, 0.1); padding: 10px 15px; border-radius: 8px; border: 1px solid rgba(6, 182, 212, 0.2);">
                    <small style="color: #06b6d4;">
                        <i class="fas fa-info-circle"></i> This exam will be scheduled for <strong>Regular Freshman</strong> students.
                        Semester: <?= ($edit_exam['semester'] ?? '1st Semester') ?>
                    </small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="save_exam" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $edit_exam ? 'Update Exam' : 'Schedule Exam' ?>
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeExamModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Freshman Exam Scheduling</h1>
                <p>Schedule exams for freshman students across all classroom sections</p>
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
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-value"><?= $total_exams ?></div>
                <div class="stat-label">Total Exams</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-value"><?= $upcoming_exams ?></div>
                <div class="stat-label">Upcoming Exams</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-value"><?= $published_exams ?></div>
                <div class="stat-label">Published</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-value">Regular (Freshman)</div>
                <div class="stat-label">Student Type</div>
            </div>
        </div>

        <!-- Distribution Cards -->
        <div class="distribution-cards">
            <div class="distribution-card">
                <h3><i class="fas fa-chart-pie"></i> Exam Type Distribution</h3>
                <div class="type-badges">
                    <?php foreach($exam_type_counts as $type => $count): ?>
                        <?php if($count > 0): ?>
                            <div class="type-badge">
                                <i class="fas fa-<?= $type == 'Midterm' ? 'file-alt' : ($type == 'Final' ? 'graduation-cap' : ($type == 'Quiz' ? 'question-circle' : ($type == 'Practical' ? 'flask' : 'tasks'))) ?>"></i>
                                <span><?= $type ?></span>
                                <span class="count"><?= $count ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="distribution-card">
                <h3><i class="fas fa-door-closed"></i> Exams by Section</h3>
                <div class="type-badges">
                    <?php foreach($section_counts as $section => $count): ?>
                        <?php if($count > 0): ?>
                            <div class="type-badge">
                                <span class="section-badge section-<?= (($section - 1) % 5) + 1 ?>">Section <?= $section ?></span>
                                <span class="count"><?= $count ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Bulk Exam Scheduling -->
        <div class="bulk-scheduling">
            <h3><i class="fas fa-bolt"></i> Bulk Exam Scheduling</h3>
            <form method="POST" id="bulkExamForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="bulk_exam_type" class="required">Exam Type</label>
                        <select class="form-control" id="bulk_exam_type" name="bulk_exam_type" required>
                            <option value="">Select Type</option>
                            <?php foreach($exam_types as $type): ?>
                                <option value="<?= $type ?>"><?= $type ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulk_exam_date" class="required">Exam Date</label>
                        <input type="date" class="form-control" id="bulk_exam_date" name="bulk_exam_date" 
                               value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulk_start_time" class="required">Start Time</label>
                        <input type="time" class="form-control" id="bulk_start_time" name="bulk_start_time" 
                               value="09:00" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulk_end_time" class="required">End Time</label>
                        <input type="time" class="form-control" id="bulk_end_time" name="bulk_end_time" 
                               value="10:30" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="bulk_academic_year" class="required">Academic Year</label>
                        <input type="text" class="form-control" id="bulk_academic_year" name="bulk_academic_year" 
                               value="<?= date('Y') . '-' . (date('Y') + 1) ?>" placeholder="e.g., 2024-2025" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulk_semester" class="required">Semester</label>
                        <select class="form-control" id="bulk_semester" name="bulk_semester" required>
                            <option value="1st Semester">1st Semester</option>
                            <option value="2nd Semester">2nd Semester</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulk_max_students" class="required">Max Students per Room</label>
                        <input type="number" class="form-control" id="bulk_max_students" name="bulk_max_students" 
                               value="50" min="1" required>
                    </div>
                </div>
                
                <!-- Hidden fields for bulk scheduling -->
                <input type="hidden" name="student_type" value="regular">
                <input type="hidden" name="year" value="freshman">
                
                <div class="form-group">
                    <label>Select Courses to Schedule Exams:</label>
                    <div class="course-selection">
                        <?php foreach($freshman_courses as $course): ?>
                            <div class="course-item">
                                <input type="checkbox" name="course_ids[]" value="<?= $course['course_id'] ?>" 
                                       id="bulk_course_<?= $course['course_id'] ?>">
                                <label for="bulk_course_<?= $course['course_id'] ?>" class="course-info">
                                    <div class="course-code">
                                        <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                                        <span class="freshman-badge">Freshman</span>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" name="bulk_schedule_exam" class="btn btn-primary">
                        <i class="fas fa-bolt"></i>
                        Schedule Exams for All Sections
                    </button>
                    <small style="display: block; margin-top: 10px; color: var(--text-secondary);">
                        This will schedule exams for ALL sections of selected courses, automatically assigning available rooms.
                        <br><strong>Student Type:</strong> Regular (Freshman)
                    </small>
                </div>
            </form>
        </div>

        <!-- Calendar View -->
        <div class="calendar-card">
            <div class="calendar-header">
                <h3><i class="fas fa-calendar"></i> Exam Calendar View</h3>
                <button class="btn btn-primary btn-sm" onclick="openExamModal()">
                    <i class="fas fa-plus"></i> Schedule Single Exam
                </button>
            </div>
            <div id="examCalendar"></div>
        </div>

        <!-- Exam Schedule Table -->
        <div class="exam-table-container">
            <div class="table-header">
                <h3><i class="fas fa-table"></i> All Freshman Exam Schedules</h3>
                <div>
                    <button class="btn btn-success btn-sm" onclick="openExamModal()">
                        <i class="fas fa-plus"></i> New Exam
                    </button>
                </div>
            </div>
            
            <?php if(!empty($exams)): ?>
                <div style="overflow-x: auto;">
                    <table class="exam-table">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Section</th>
                                <th>Exam Type</th>
                                <th>Date & Time</th>
                                <th>Room</th>
                                <th>Student Type</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($exams as $exam): ?>
                                <?php
                                $current_time = time();
                                $exam_timestamp = strtotime($exam['exam_date'] . ' ' . $exam['start_time']);
                                $is_past = $exam_timestamp < $current_time;
                                $is_upcoming = $exam_timestamp > $current_time;
                                $is_today = date('Y-m-d') == $exam['exam_date'];
                                
                                $student_type = $exam['student_type'] ?? 'Not specified';
                                $year = $exam['year'] ?? '';
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-primary);">
                                            <?= htmlspecialchars($exam['course_code']) ?>
                                        </div>
                                        <div style="color: var(--text-secondary); font-size: 0.9rem;">
                                            <?= htmlspecialchars($exam['course_name']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="section-badge section-<?= (($exam['section_number'] - 1) % 5) + 1 ?>">
                                            Section <?= $exam['section_number'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge exam-badge-<?= strtolower($exam['exam_type']) ?>">
                                            <?= htmlspecialchars($exam['exam_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-primary);">
                                            <?= date('M d, Y', strtotime($exam['exam_date'])) ?>
                                        </div>
                                        <div style="color: var(--text-secondary); font-size: 0.9rem;">
                                            <?= date('g:i A', strtotime($exam['start_time'])) ?> - <?= date('g:i A', strtotime($exam['end_time'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-primary);">
                                            <?= htmlspecialchars($exam['room_name']) ?>
                                        </div>
                                        <div style="color: var(--text-secondary); font-size: 0.9rem;">
                                            Capacity: <?= $exam['capacity'] ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($student_type && $student_type !== 'Not specified'): ?>
                                            <span class="student-type-badge <?= $student_type ?>">
                                                <?= ucfirst($student_type) ?>
                                                <?php if($year): ?>
                                                    <span class="year-badge">
                                                        Year <?= htmlspecialchars($year) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-primary);">
                                            <?= $exam['registered_count'] ?> / <?= $exam['max_students'] ?>
                                        </div>
                                        <div class="progress-container">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?= min(100, ($exam['registered_count'] / $exam['max_students']) * 100) ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 5px;">
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
                                            <?php elseif($is_today): ?>
                                                <span class="badge badge-warning">Today</span>
                                            <?php elseif($is_upcoming): ?>
                                                <span class="badge badge-success">Upcoming</span>
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
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Exam Schedules Found</h3>
                    <p>Schedule your first exam using the buttons above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script>
// Sidebar Toggle
function toggleMenu() {
    const sidebar = document.getElementById('sidebar');
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

// Update room capacity display
function updateRoomCapacity() {
    const roomSelect = document.getElementById('room_id');
    const selectedOption = roomSelect.options[roomSelect.selectedIndex];
    const capacity = selectedOption.getAttribute('data-capacity');
    const capacityDisplay = document.getElementById('roomCapacity');
    
    if(capacity) {
        capacityDisplay.textContent = `Room Capacity: ${capacity} students`;
        capacityDisplay.style.display = 'block';
        
        // Update max students input
        const maxStudents = document.getElementById('max_students');
        maxStudents.max = capacity;
        if(parseInt(maxStudents.value) > parseInt(capacity)) {
            maxStudents.value = capacity;
        }
    } else {
        capacityDisplay.style.display = 'none';
    }
}

// Initialize FullCalendar
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('examCalendar');
    
    // Prepare events from PHP data
    const calendarEvents = <?= json_encode(array_map(function($exam) {
        $colorMap = [
            'Midterm' => '#3b82f6',
            'Final' => '#ef4444',
            'Quiz' => '#10b981',
            'Practical' => '#f59e0b',
            'Assignment' => '#8b5cf6'
        ];
        
        return [
            'id' => $exam['exam_id'],
            'title' => $exam['course_code'] . ' - Sec ' . $exam['section_number'] . ' (' . $exam['exam_type'] . ')',
            'start' => $exam['exam_date'] . 'T' . $exam['start_time'],
            'end' => $exam['exam_date'] . 'T' . $exam['end_time'],
            'backgroundColor' => $colorMap[$exam['exam_type']] ?? '#6b7280',
            'borderColor' => $colorMap[$exam['exam_type']] ?? '#6b7280',
            'textColor' => '#ffffff',
            'extendedProps' => [
                'course' => $exam['course_name'],
                'room' => $exam['room_name'],
                'published' => $exam['is_published'] == 1,
                'section' => $exam['section_number'],
                'student_type' => $exam['student_type'] ?? 'regular',
                'year' => $exam['year'] ?? 'freshman'
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
            const published = info.event.extendedProps.published;
            const section = info.event.extendedProps.section;
            const student_type = info.event.extendedProps.student_type;
            const year = info.event.extendedProps.year;
            
            const status = published ? 'Published' : 'Not Published';
            info.el.title = `${title}\nCourse: ${course}\nRoom: ${room}\nSection: ${section}\nStudent Type: ${student_type} (Year ${year})\nStatus: ${status}`;
            
            // Add custom styling
            info.el.style.borderRadius = '6px';
            info.el.style.boxShadow = '0 2px 6px rgba(0,0,0,0.1)';
            info.el.style.padding = '4px 8px';
            info.el.style.fontSize = '0.85rem';
            
            // Add unpublished indicator (dashed border)
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
    
    // If edit parameter exists, open modal automatically
    <?php if(isset($_GET['edit'])): ?>
        setTimeout(() => openExamModal(), 100);
    <?php endif; ?>
    
    // Initialize room capacity display
    const roomSelect = document.getElementById('room_id');
    if(roomSelect && roomSelect.value) {
        updateRoomCapacity();
    }
});

// Form validation for single exam
document.getElementById('examForm').addEventListener('submit', function(e) {
    const startTime = document.getElementById('start_time').value;
    const endTime = document.getElementById('end_time').value;
    const examDate = document.getElementById('exam_date').value;
    const today = new Date().toISOString().split('T')[0];
    
    if(startTime >= endTime) {
        e.preventDefault();
        alert('❌ End time must be after start time.');
        return false;
    }
    
    if(examDate < today) {
        e.preventDefault();
        alert('❌ Exam date cannot be in the past.');
        return false;
    }
    
    return true;
});

// Form validation for bulk exam
document.getElementById('bulkExamForm').addEventListener('submit', function(e) {
    const startTime = document.getElementById('bulk_start_time').value;
    const endTime = document.getElementById('bulk_end_time').value;
    const examDate = document.getElementById('bulk_exam_date').value;
    const today = new Date().toISOString().split('T')[0];
    const selectedCourses = document.querySelectorAll('input[name="course_ids[]"]:checked').length;
    
    if(startTime >= endTime) {
        e.preventDefault();
        alert('❌ End time must be after start time.');
        return false;
    }
    
    if(examDate < today) {
        e.preventDefault();
        alert('❌ Exam date cannot be in the past.');
        return false;
    }
    
    if(selectedCourses === 0) {
        e.preventDefault();
        alert('❌ Please select at least one course.');
        return false;
    }
    
    const confirmation = confirm(
        `📚 Bulk Exam Scheduling:\n\n` +
        `This will schedule exams for ALL sections of ${selectedCourses} course(s).\n` +
        `Student Type: Regular (Freshman)\n` +
        `The system will automatically assign available rooms.\n\n` +
        `Continue?`
    );
    
    if(!confirmation) {
        e.preventDefault();
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

// Initialize sidebar active state
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.sidebar a');
    
    navLinks.forEach(link => {
        const linkPage = link.getAttribute('href');
        if (linkPage === currentPage) {
            link.classList.add('active');
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
});
</script>
</body>
</html>