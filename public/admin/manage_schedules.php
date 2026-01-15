<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit;
}

// Debug database connection
try {
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
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

// Define time slots for the week
$time_slots = [
    ['08:00:00', '11:00:00'], // Morning slot
    ['14:30:00', '16:20:00'], // Afternoon slot 1
    ['16:30:00', '18:00:00']  // Afternoon slot 2
];

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Store recently created schedules for preview
$recent_schedules = [];
$recent_schedule_details = [];

// View mode flag - check if we're in view mode
$view_mode = false;
if (isset($_GET['view']) && $_GET['view'] === 'enrollments') {
    $view_mode = true;
}

// Handle delete all schedules
if(isset($_GET['delete_all']) && $view_mode){
    // CSRF validation
    if(!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']){
        $_SESSION['message'] = "Security token invalid. Please try again.";
        $_SESSION['message_type'] = "error";
        header("Location: ?view=enrollments");
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Count schedules before deletion
        $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM schedule WHERE year = 'freshman'");
        $total_schedules = $count_stmt->fetchColumn();
        
        if($total_schedules == 0) {
            $_SESSION['message'] = "No schedules to delete.";
            $_SESSION['message_type'] = "warning";
            header("Location: ?view=enrollments");
            exit;
        }
        
        // First delete all enrollments from enrollments table
        try {
            // Check if enrollments table exists
            $table_check = $pdo->query("SHOW TABLES LIKE 'enrollments'");
            if($table_check->fetch()) {
                $pdo->query("DELETE FROM enrollments WHERE schedule_id IN (SELECT schedule_id FROM schedule WHERE year = 'freshman')");
            }
        } catch (Exception $e) {
            // Table might not exist or foreign key constraint
            error_log("Note: Could not delete enrollments: " . $e->getMessage());
        }
        
        // Then delete all freshman schedules
        $delete_stmt = $pdo->prepare("DELETE FROM schedule WHERE year = 'freshman'");
        $delete_stmt->execute();
        
        $pdo->commit();
        
        // Set success message
        $_SESSION['message'] = "✅ Successfully deleted all $total_schedules freshman schedules and their enrollments!";
        $_SESSION['message_type'] = "success";
        header("Location: ?view=enrollments");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "❌ Error deleting all schedules: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: ?view=enrollments");
        exit;
    }
}

// Handle delete schedule
if(isset($_GET['delete_schedule']) && !$view_mode){
    $schedule_id = (int)$_GET['delete_schedule'];
    
    // Check if schedule exists
    $check_stmt = $pdo->prepare("SELECT schedule_id FROM schedule WHERE schedule_id = ?");
    $check_stmt->execute([$schedule_id]);
    
    if($check_stmt->fetch()){
        try {
            $pdo->beginTransaction();
            
            // First delete enrollments from enrollments table
            try {
                $pdo->prepare("DELETE FROM enrollments WHERE schedule_id = ?")->execute([$schedule_id]);
            } catch (Exception $e) {
                // Table might not exist
                error_log("Note: Could not delete enrollments: " . $e->getMessage());
            }
            
            // Then delete the schedule
            $delete_stmt = $pdo->prepare("DELETE FROM schedule WHERE schedule_id = ?");
            $delete_stmt->execute([$schedule_id]);
            
            $pdo->commit();
            
            // Set success message and redirect
            $_SESSION['message'] = "Schedule deleted successfully!";
            $_SESSION['message_type'] = "success";
            header("Location: ?view=enrollments");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['message'] = "Error deleting schedule: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
            header("Location: ?view=enrollments");
            exit;
        }
    } else {
        $_SESSION['message'] = "Schedule not found!";
        $_SESSION['message_type'] = "error";
        header("Location: ?view=enrollments");
        exit;
    }
}

// Check for session messages
if(isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Handle batch scheduling for multiple classrooms
if(isset($_POST['batch_schedule']) && !$view_mode){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $course_ids = isset($_POST['course_ids']) ? $_POST['course_ids'] : [];
        $classroom_sections = isset($_POST['classroom_sections']) ? $_POST['classroom_sections'] : [];
        $academic_year = trim($_POST['academic_year']);
        $semester = trim($_POST['semester']);
        
        // Validate inputs
        if(empty($course_ids) || empty($classroom_sections) || 
           empty($academic_year) || empty($semester)){
            $message = "Please select courses, create classroom sections, and fill academic year/semester!";
            $message_type = "error";
        } else {
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                $total_created = 0;
                $section_results = [];
                $created_schedules = [];
                
                // Get or create a TBA instructor
                $tba_instructor_id = null;
                $tba_check = $pdo->prepare("SELECT user_id FROM users WHERE username = 'TBA' AND role = 'instructor' LIMIT 1");
                $tba_check->execute();
                $tba_instructor = $tba_check->fetch();
                
                if($tba_instructor) {
                    $tba_instructor_id = $tba_instructor['user_id'];
                } else {
                    // Create a TBA instructor if not exists
                    $create_tba = $pdo->prepare("INSERT INTO users (username, email, role, is_approved, year) VALUES ('TBA', 'tba@university.edu', 'instructor', 1, 'instructor')");
                    $create_tba->execute();
                    $tba_instructor_id = $pdo->lastInsertId();
                }
                
                // Store course details
                $course_details = [];
                foreach($course_ids as $course_id) {
                    $course_id = (int)$course_id;
                    $course_details_stmt = $pdo->prepare("SELECT course_code, course_name FROM courses WHERE course_id=?");
                    $course_details_stmt->execute([$course_id]);
                    $course_details[$course_id] = $course_details_stmt->fetch();
                }
                
                // Process each classroom section separately
                foreach($classroom_sections as $section_index => $section_data) {
                    if(empty($section_data['room_id']) || empty($section_data['student_ids'])) continue;
                    
                    $room_id = (int)$section_data['room_id'];
                    $student_ids = $section_data['student_ids'];
                    
                    $section_number = $section_index + 1; // This is the section number
                    
                    $section_result = [
                        'section_number' => $section_number,
                        'room_id' => $room_id,
                        'student_count' => count($student_ids),
                        'created_sessions' => 0,
                        'room_name' => '',
                        'courses_scheduled' => 0
                    ];
                    
                    // Get room details
                    $room_stmt = $pdo->prepare("SELECT room_name, capacity FROM rooms WHERE room_id=?");
                    $room_stmt->execute([$room_id]);
                    $room_data = $room_stmt->fetch();
                    $section_result['room_name'] = $room_data['room_name'] ?? 'Unknown';
                    
                    // Clear existing schedules for this room/academic year/semester/freshman combination
                    $clear_stmt = $pdo->prepare("DELETE FROM schedule WHERE room_id = ? AND academic_year = ? AND semester = ? AND year = 'freshman' AND section_number = ?");
                    $clear_stmt->execute([$room_id, $academic_year, $semester, $section_number]);
                    
                    // Track which courses have been scheduled on which days
                    $scheduled_on_day = [];
                    foreach($days_of_week as $day) {
                        $scheduled_on_day[$day] = [];
                    }
                    
                    // Track which time slots are occupied on which days
                    $occupied_slots = [];
                    foreach($days_of_week as $day) {
                        $occupied_slots[$day] = [0 => false, 1 => false, 2 => false];
                    }
                    
                    // Calculate slots distribution
                    $total_courses = count($course_ids);
                    $total_slots_per_week = 15;
                    $slots_per_course = floor($total_slots_per_week / $total_courses);
                    $extra_slots = $total_slots_per_week % $total_courses;
                    
                    // Schedule courses for this section
                    $course_slot_counts = array_fill(0, $total_courses, 0);
                    $section_created_sessions = 0;
                    $scheduled_course_ids = [];
                    $attempts = 0;
                    $max_attempts = $total_slots_per_week * 3;
                    
                    while ($section_created_sessions < $total_slots_per_week && $attempts < $max_attempts) {
                        $attempts++;
                        
                        for($course_index = 0; $course_index < $total_courses && $section_created_sessions < $total_slots_per_week; $course_index++) {
                            $course_id = $course_ids[$course_index];
                            $course = $course_details[$course_id];
                            
                            $target_slots = $slots_per_course + ($course_index < $extra_slots ? 1 : 0);
                            
                            if($course_slot_counts[$course_index] >= $target_slots) {
                                continue;
                            }
                            
                            $course_scheduled_this_round = true;
                            
                            foreach($days_of_week as $day) {
                                if(in_array($course_id, $scheduled_on_day[$day])) {
                                    continue;
                                }
                                
                                foreach($time_slots as $slot_index => $slot) {
                                    if($occupied_slots[$day][$slot_index]) {
                                        continue;
                                    }
                                    
                                    $start_time = $slot[0];
                                    $end_time = $slot[1];
                                    
                                    try {
                                        // Insert schedule with TBA instructor and section number
                                        $stmt = $pdo->prepare("INSERT INTO schedule 
                                            (course_id, instructor_id, room_id, academic_year, semester, 
                                            day, start_time, end_time, year, section_number) 
                                            VALUES (?,?,?,?,?,?,?,?,?,?)");
                                        $stmt->execute([
                                            $course_id, $tba_instructor_id, $room_id, $academic_year, $semester,
                                            $day, $start_time, $end_time, 'freshman', $section_number
                                        ]);
                                        
                                    } catch (Exception $e) {
                                        // Try without section_number if column doesn't exist
                                        try {
                                            $stmt = $pdo->prepare("INSERT INTO schedule 
                                                (course_id, instructor_id, room_id, academic_year, semester, 
                                                day, start_time, end_time, year) 
                                                VALUES (?,?,?,?,?,?,?,?,?)");
                                            $stmt->execute([
                                                $course_id, $tba_instructor_id, $room_id, $academic_year, $semester,
                                                $day, $start_time, $end_time, 'freshman'
                                            ]);
                                        } catch (Exception $e2) {
                                            error_log("Schedule insertion error: " . $e2->getMessage());
                                            continue;
                                        }
                                    }
                                    
                                    $schedule_id = $pdo->lastInsertId();
                                    $section_created_sessions++;
                                    $total_created++;
                                    $course_slot_counts[$course_index]++;
                                    
                                    $scheduled_on_day[$day][] = $course_id;
                                    $occupied_slots[$day][$slot_index] = true;
                                    
                                    if(!in_array($course_id, $scheduled_course_ids)) {
                                        $scheduled_course_ids[] = $course_id;
                                    }
                                    
                                    // Store schedule details
                                    $created_schedules[] = [
                                        'schedule_id' => $schedule_id,
                                        'course_id' => $course_id,
                                        'course_code' => $course['course_code'],
                                        'course_name' => $course['course_name'],
                                        'day' => $day,
                                        'start_time' => $start_time,
                                        'end_time' => $end_time,
                                        'academic_year' => $academic_year,
                                        'semester' => $semester,
                                        'section_number' => $section_number,
                                        'room_name' => $section_result['room_name'],
                                        'instructor_name' => 'TBA'
                                    ];
                                    
                                    // Enroll students in this schedule using enrollments table
                                    foreach($student_ids as $student_id) {
                                        $student_id = (int)$student_id;
                                        
                                        // Check if enrollments table exists
                                        try {
                                            $table_check = $pdo->query("SHOW TABLES LIKE 'enrollments'");
                                            if($table_check->fetch()) {
                                                // Check if already enrolled
                                                $enrollment_check = $pdo->prepare("SELECT enrollment_id FROM enrollments 
                                                    WHERE student_id=? AND schedule_id=?");
                                                $enrollment_check->execute([$student_id, $schedule_id]);
                                                
                                                if(!$enrollment_check->fetch()) {
                                                    // Insert into enrollments table with both schedule_id and course_id
                                                    $enroll_stmt = $pdo->prepare("INSERT INTO enrollments 
                                                        (student_id, schedule_id, course_id, enrolled_at) 
                                                        VALUES (?,?,?,NOW())");
                                                    $enroll_stmt->execute([$student_id, $schedule_id, $course_id]);
                                                }
                                            }
                                        } catch (Exception $e) {
                                            error_log("Student enrollment error: " . $e->getMessage());
                                        }
                                    }
                                    
                                    break 2;
                                }
                            }
                        }
                    }
                    
                    $section_result['created_sessions'] = $section_created_sessions;
                    $section_result['courses_scheduled'] = count($scheduled_course_ids);
                    $section_results[] = $section_result;
                }
                
                $pdo->commit();
                
                // Store results for preview
                $recent_schedules = $created_schedules;
                $recent_schedule_details = [
                    'section_results' => $section_results,
                    'total_sections' => count($section_results),
                    'total_sessions' => $total_created,
                    'academic_year' => $academic_year,
                    'semester' => $semester,
                    'courses_selected' => count($course_ids)
                ];
                
                if($total_created > 0) {
                    $message = "✅ Successfully scheduled courses for " . count($section_results) . " classroom sections!\n";
                    $message .= "📊 Total class sessions created: $total_created\n";
                    $message .= "👨‍🏫 Instructor: TBA (To Be Assigned)\n";
                    $message .= "📅 Academic Year: $academic_year | Semester: " . ($semester == '1' ? '1st Semester' : '2nd Semester') . "\n\n";
                    
                    foreach($section_results as $result) {
                        $message .= "Classroom Section {$result['section_number']}:\n";
                        $message .= "  🏫 Classroom: {$result['room_name']}\n";
                        $message .= "  👥 Students enrolled: {$result['student_count']}\n";
                        $message .= "  📚 Courses scheduled: {$result['courses_scheduled']}/" . count($course_ids) . "\n";
                        $message .= "  ⏰ Sessions created: {$result['created_sessions']}/15\n\n";
                    }
                    
                    if($total_created < (count($section_results) * count($course_ids) * 3)) {
                        $message .= "⚠️ Note: Some courses may have fewer sessions due to time conflicts.";
                        $message_type = "warning";
                    } else {
                        $message .= "🎉 All courses successfully scheduled for all sections!";
                        $message_type = "success";
                    }
                    
                    // Add note about instructor assignment
                    $message .= "\n\n📝 Note: Instructors are set as 'TBA' (To Be Assigned).\n";
                    $message .= "You can assign actual instructors later from the 'Assign Instructors' page.";
                    
                } else {
                    $message = "❌ No classes could be scheduled. Please check room availability.";
                    $message_type = "error";
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Fetch data for the form
$freshman_courses = $pdo->query("SELECT * FROM courses WHERE is_freshman = 1 ORDER BY course_name")->fetchAll(PDO::FETCH_ASSOC);
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch freshman students
$freshman_students = $pdo->query("
    SELECT u.user_id, u.username, u.email, d.department_name 
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.department_id 
    WHERE u.role = 'student' 
    AND u.year = 'freshman'
    ORDER BY u.username
")->fetchAll(PDO::FETCH_ASSOC);

// Check if section_number column exists in schedule table
$column_check = $pdo->query("SHOW COLUMNS FROM schedule LIKE 'section_number'")->fetch();
if (!$column_check) {
    // Create the section_number column if it doesn't exist
    try {
        $pdo->query("ALTER TABLE schedule ADD COLUMN section_number INT DEFAULT 1");
        error_log("Added section_number column to schedule table");
    } catch (Exception $e) {
        error_log("Could not add section_number column: " . $e->getMessage());
    }
}

// Fetch existing schedules with enrollment count from enrollments table
$existing_schedules = [];
try {
    $schedules_query = "
        SELECT 
            s.schedule_id,
            s.course_id,
            s.room_id,
            s.day,
            s.start_time,
            s.end_time,
            s.academic_year,
            s.semester,
            s.year,
            COALESCE(s.section_number, 1) as section_number,
            c.course_code, 
            c.course_name,
            r.room_name,
            COALESCE(u.username, 'TBA') as instructor_name,
            COALESCE(u.email, 'Not Assigned') as instructor_email,
            COALESCE(
                (SELECT COUNT(DISTINCT e.student_id) 
                 FROM enrollments e 
                 WHERE e.schedule_id = s.schedule_id),
                0
            ) as enrolled_students,
            (SELECT GROUP_CONCAT(DISTINCT u2.username SEPARATOR ', ') 
             FROM enrollments e2 
             JOIN users u2 ON e2.student_id = u2.user_id 
             WHERE e2.schedule_id = s.schedule_id 
             LIMIT 3) as sample_students
        FROM schedule s
        JOIN courses c ON s.course_id = c.course_id
        JOIN rooms r ON s.room_id = r.room_id
        LEFT JOIN users u ON s.instructor_id = u.user_id
        WHERE (s.year = 'freshman' OR c.is_freshman = 1)
        ORDER BY 
            s.section_number,
            c.course_name,
            CASE s.day 
                WHEN 'Monday' THEN 1
                WHEN 'Tuesday' THEN 2
                WHEN 'Wednesday' THEN 3
                WHEN 'Thursday' THEN 4
                WHEN 'Friday' THEN 5
                ELSE 6
            END,
            s.start_time
    ";
    
    $existing_schedules = $pdo->query($schedules_query)->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching schedules: " . $e->getMessage());
    $existing_schedules = [];
}

// Fetch enrollments if in view mode
$schedule_enrollments = [];
$schedule_details = null;
if ($view_mode && isset($_GET['schedule_id'])) {
    $schedule_id = (int)$_GET['schedule_id'];
    
    // Get schedule details
    $schedule_stmt = $pdo->prepare("
        SELECT s.*, COALESCE(s.section_number, 1) as section_number, 
               c.course_code, c.course_name, r.room_name,
               COALESCE(u.username, 'TBA') as instructor_name
        FROM schedule s
        JOIN courses c ON s.course_id = c.course_id
        JOIN rooms r ON s.room_id = r.room_id
        LEFT JOIN users u ON s.instructor_id = u.user_id
        WHERE s.schedule_id = ?
    ");
    $schedule_stmt->execute([$schedule_id]);
    $schedule_details = $schedule_stmt->fetch();
    
    if ($schedule_details) {
        // Get enrolled students from enrollments table
        $enrollments_stmt = $pdo->prepare("
            SELECT u.user_id, u.username, u.email, u.year, d.department_name, e.enrolled_at
            FROM enrollments e
            JOIN users u ON e.student_id = u.user_id
            LEFT JOIN departments d ON u.department_id = d.department_id
            WHERE e.schedule_id = ?
            ORDER BY u.username
        ");
        $enrollments_stmt->execute([$schedule_id]);
        $schedule_enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Count total freshman schedules for delete all confirmation
$total_freshman_schedules = 0;
if($view_mode) {
    $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM schedule WHERE year = 'freshman'");
    $total_freshman_schedules = $count_stmt->fetchColumn();
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
<title>Schedule Multiple Classroom Sections - DKU Scheduler</title>
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

/* ================= Updated Sidebar ================= */
.sidebar { 
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 250px; 
    height: 100%; 
    background: var(--bg-sidebar); 
    color: var(--text-sidebar);
    z-index: 1100;
    transition: transform 0.3s ease;
    display: flex;
    flex-direction: column;
    overflow: hidden;
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

/* Sidebar Title */
.sidebar h2 {
    text-align: center;
    color: var(--text-sidebar);
    margin-bottom: 25px;
    font-size: 22px;
    padding: 0 20px;
    flex-shrink: 0; /* Prevent shrinking */
}

/* Sidebar Links */
.sidebar a { 
    display: block; 
    padding: 12px 20px; 
    color: var(--text-sidebar); 
    text-decoration: none; 
    transition: background 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    flex-shrink: 0; /* Prevent shrinking */
}
.sidebar a:hover, .sidebar a.active { 
    background: #1abc9c; 
    color: white; 
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

/* ================= Recent Schedules Preview ================= */
.recent-schedules-preview {
    background: var(--bg-card);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    margin-bottom: 30px;
    display: <?= !empty($recent_schedules) ? 'block' : 'none' ?>;
}

.preview-header {
    color: var(--text-primary);
    margin-bottom: 20px;
    font-size: 1.4rem;
    font-weight: 600;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.preview-summary {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border-left: 4px solid var(--primary-color);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    color: var(--text-primary);
}

[data-theme="dark"] .preview-summary {
    background: linear-gradient(135deg, #1e3a8a, #1e40af);
    color: #dbeafe;
}

.preview-summary p {
    margin: 5px 0;
    font-size: 0.95rem;
}

.preview-summary .count {
    font-weight: 600;
    color: var(--primary-color);
}

.schedules-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    font-size: 0.9rem;
}

.schedules-table th {
    background: var(--table-header);
    color: var(--text-sidebar);
    padding: 12px;
    text-align: left;
    font-weight: 700;
}

.schedules-table td {
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
}

.schedules-table tr:hover {
    background: var(--hover-color);
}

.schedules-table tr:nth-child(even) {
    background: var(--bg-secondary);
}

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

.time-display {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: var(--text-primary);
    background: var(--bg-secondary);
    padding: 4px 8px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
}

.view-all-btn {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
    font-size: 0.9rem;
}

.view-all-btn:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
}

/* ================= Existing Schedules Section ================= */
.existing-schedules-section {
    background: var(--bg-card);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    margin-bottom: 30px;
    display: <?= ($view_mode && !isset($_GET['schedule_id'])) ? 'block' : 'none' ?>;
}

.section-header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border-color);
}

.section-header-actions h2 {
    color: var(--text-primary);
    font-size: 1.6rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.delete-all-btn {
    background: linear-gradient(135deg, var(--danger-color), var(--danger-hover));
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.delete-all-btn:hover {
    background: linear-gradient(135deg, var(--danger-hover), #b91c1c);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
}

.delete-all-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.schedule-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s;
}

.schedule-card:hover {
    box-shadow: 0 4px 12px var(--shadow-color);
    transform: translateY(-2px);
}

.schedule-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.schedule-info h3 {
    color: var(--primary-color);
    margin-bottom: 5px;
    font-size: 1.2rem;
}

.schedule-info p {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin: 3px 0;
}

.schedule-time {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
    color: white;
    padding: 8px 15px;
    border-radius: 8px;
    font-weight: 600;
    font-family: 'Courier New', monospace;
}

.schedule-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.detail-item i {
    color: var(--primary-color);
    font-size: 1rem;
}

.detail-label {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.detail-value {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.student-preview {
    background: var(--hover-color);
    padding: 10px 15px;
    border-radius: 8px;
    margin-top: 10px;
}

.student-preview p {
    margin: 5px 0;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.btn-small {
    padding: 8px 15px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s;
}

.btn-small:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.btn-primary-small {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
    color: white;
}

.btn-success-small {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-danger-small {
    background: linear-gradient(135deg, var(--danger-color), var(--danger-hover));
    color: white;
}

.btn-danger-small:hover {
    background: linear-gradient(135deg, var(--danger-hover), #b91c1c);
}

/* Delete buttons in schedule cards */
.delete-schedule-btn {
    background: transparent;
    border: 1px solid var(--danger-color);
    color: var(--danger-color);
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.3s;
}

.delete-schedule-btn:hover {
    background: var(--danger-color);
    color: white;
}

/* ================= Enrollment View ================= */
.enrollment-view {
    background: var(--bg-card);
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    margin-bottom: 30px;
    display: <?= ($view_mode && isset($_GET['schedule_id'])) ? 'block' : 'none' ?>;
}

.back-button {
    background: linear-gradient(135deg, #6b7280, #4b5563);
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
    text-decoration: none;
}

.back-button:hover {
    background: linear-gradient(135deg, #4b5563, #374151);
    transform: translateY(-2px);
}

.enrollment-summary {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border-left: 4px solid var(--primary-color);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    color: var(--text-primary);
}

[data-theme="dark"] .enrollment-summary {
    background: linear-gradient(135deg, #1e3a8a, #1e40af);
    color: #dbeafe;
}

.enrollment-summary h2 {
    color: var(--primary-color);
    margin-bottom: 15px;
    font-size: 1.5rem;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.summary-item {
    padding: 10px;
    background: var(--bg-secondary);
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.summary-item label {
    display: block;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.summary-item span {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.enrolled-students-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    font-size: 0.9rem;
}

.enrolled-students-table th {
    background: var(--table-header);
    color: var(--text-sidebar);
    padding: 12px;
    text-align: left;
    font-weight: 700;
}

.enrolled-students-table td {
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
}

.enrolled-students-table tr:hover {
    background: var(--hover-color);
}

.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-enrolled {
    background: #d1fae5;
    color: #065f46;
}

[data-theme="dark"] .status-enrolled {
    background: #064e3b;
    color: #a7f3d0;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

[data-theme="dark"] .status-pending {
    background: #78350f;
    color: #fde68a;
}

/* Section badge */
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

/* Instructor badge */
.instructor-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 8px;
}

.instructor-tba {
    background: #f59e0b;
    color: #92400e;
}

[data-theme="dark"] .instructor-tba {
    background: #78350f;
    color: #fde68a;
}

.instructor-assigned {
    background: #10b981;
    color: #065f46;
}

[data-theme="dark"] .instructor-assigned {
    background: #064e3b;
    color: #a7f3d0;
}

/* ================= Classroom Sections Form ================= */
.classroom-sections-form {
    background: var(--bg-card);
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    margin-bottom: 30px;
    display: <?= !$view_mode ? 'block' : 'none' ?>;
}

.form-section-title {
    color: var(--text-primary);
    margin-bottom: 20px;
    font-size: 1.4rem;
    font-weight: 600;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1rem;
}

.required::after {
    content: " *";
    color: #ef4444;
}

/* Course Selection */
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

/* Section containers */
.section-container {
    border: 2px solid var(--border-color);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
    background: var(--bg-secondary);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.section-header h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text-primary);
    font-size: 1.2rem;
}

.section-number {
    background: var(--primary-color);
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.add-section-btn {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.add-section-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(37, 99, 235, 0.3);
}

.remove-section-btn {
    background: var(--danger-color);
    color: white;
    border: none;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    transition: all 0.3s;
}

.remove-section-btn:hover {
    background: var(--danger-hover);
    transform: scale(1.1);
}

.section-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

/* Room Selection */
.room-selection select {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    font-size: 1rem;
    background: var(--bg-secondary);
    color: var(--text-primary);
    transition: all 0.3s;
}

.room-selection select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}

.room-info {
    margin-top: 10px;
    padding: 10px;
    background: var(--hover-color);
    border-radius: 6px;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

/* Student Selection */
.student-selection {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 15px;
    background: var(--bg-secondary);
}

.student-item {
    display: flex;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid var(--border-color);
    transition: background 0.3s;
}

.student-item:last-child {
    border-bottom: none;
}

.student-item:hover {
    background: var(--hover-color);
}

.student-item input[type="checkbox"] {
    margin-right: 10px;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.student-info {
    flex: 1;
}

.student-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.student-email {
    color: var(--text-secondary);
    font-size: 0.85rem;
    margin-top: 2px;
}

.department-badge {
    display: inline-block;
    padding: 3px 8px;
    background: var(--bg-primary);
    color: var(--text-secondary);
    border-radius: 4px;
    font-size: 0.75rem;
    margin-left: 8px;
    border: 1px solid var(--border-color);
}

/* Academic Year and Semester */
.academic-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.academic-info .form-group {
    margin-bottom: 0;
}

.academic-info input,
.academic-info select {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    font-size: 1rem;
    background: var(--bg-secondary);
    color: var(--text-primary);
    transition: all 0.3s;
}

.academic-info input:focus,
.academic-info select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}

/* Selection Summary */
.selection-summary {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border-left: 4px solid var(--primary-color);
    padding: 15px;
    border-radius: 8px;
    margin: 20px 0;
    color: var(--text-primary);
}

[data-theme="dark"] .selection-summary {
    background: linear-gradient(135deg, #1e3a8a, #1e40af);
    color: #dbeafe;
}

.selection-summary p {
    margin: 5px 0;
    font-size: 0.95rem;
}

.selection-summary .count {
    font-weight: 600;
    color: var(--primary-color);
}

/* Info Box */
.info-box {
    background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
    border-left: 4px solid #8b5cf6;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 25px;
    color: #6d28d9;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    display: <?= !$view_mode ? 'flex' : 'none' ?>;
}

[data-theme="dark"] .info-box {
    background: linear-gradient(135deg, #4c1d95, #5b21b6);
    color: #e9d5ff;
    border-left-color: #a78bfa;
}

.info-box i {
    margin-top: 2px;
    font-size: 1.2rem;
}

.info-box ol {
    margin-left: 20px;
    margin-top: 8px;
}

.info-box li {
    margin-bottom: 5px;
}

/* Time Slots Display */
.time-slots-section {
    margin: 25px 0;
    padding: 20px;
    background: var(--bg-secondary);
    border-radius: 10px;
    border: 1px solid var(--border-color);
    display: <?= !$view_mode ? 'block' : 'none' ?>;
}

.time-slots-title {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 15px;
    font-size: 1.1rem;
}

.time-slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.time-slot-card {
    background: var(--bg-card);
    padding: 15px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    text-align: center;
}

.time-slot-day {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 10px;
    font-size: 1rem;
}

.time-slot {
    font-family: 'Courier New', monospace;
    background: var(--primary-color);
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-weight: 600;
    margin: 5px 0;
    display: block;
}

/* Button Styles */
.form-actions {
    display: flex;
    justify-content: center;
    margin-top: 30px;
    gap: 15px;
}

.btn { 
    padding: 14px 30px; 
    border-radius: 10px; 
    border: none; 
    cursor: pointer; 
    font-weight: 600; 
    font-size: 1rem;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-width: 200px;
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

/* Counter Badge */
.counter-badge {
    background: white;
    color: var(--primary-color);
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 600;
    margin-left: 8px;
}

/* Tabs */
.view-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 0;
}

.view-tabs .tab-button {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s;
    font-size: 1rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.view-tabs .tab-button:hover {
    color: var(--primary-color);
    background: var(--hover-color);
}

.view-tabs .tab-button.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
    background: var(--hover-color);
}

.tab-button .badge {
    background: var(--primary-color);
    color: white;
    border-radius: 10px;
    padding: 2px 8px;
    font-size: 0.75rem;
    margin-left: 8px;
}

/* Confirmation Modal */
.confirmation-modal {
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
}

.confirmation-modal-content {
    background: var(--bg-card);
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    max-width: 500px;
    width: 90%;
}

.confirmation-modal h3 {
    color: var(--danger-color);
    margin-bottom: 15px;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.confirmation-modal p {
    color: var(--text-primary);
    margin-bottom: 20px;
    line-height: 1.6;
}

.confirmation-modal-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
}

.btn-cancel {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.btn-cancel:hover {
    background: var(--hover-color);
}

.btn-confirm-delete {
    background: linear-gradient(135deg, var(--danger-color), var(--danger-hover));
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-confirm-delete:hover {
    background: linear-gradient(135deg, var(--danger-hover), #b91c1c);
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

/* Selection states */
input[type="checkbox"]:checked + .course-info .course-code,
input[type="checkbox"]:checked + .student-info .student-name {
    color: var(--primary-color);
    font-weight: 700;
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
    
    .form-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .academic-info {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .time-slots-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btn {
        width: 100%;
        margin: 5px 0;
    }
    
    .course-selection,
    .student-selection {
        max-height: 250px;
    }
    
    .schedules-table {
        display: block;
        overflow-x: auto;
    }
    
    .view-tabs {
        flex-direction: column;
        gap: 5px;
    }
    
    .view-tabs .tab-button {
        width: 100%;
        text-align: left;
    }
    
    .schedule-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .enrolled-students-table {
        display: block;
        overflow-x: auto;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-small {
        width: 100%;
        justify-content: center;
    }
    
    .confirmation-modal-content {
        width: 95%;
        padding: 20px;
    }
    
    .confirmation-modal-actions {
        flex-direction: column;
    }
    
    .btn-cancel, .btn-confirm-delete {
        width: 100%;
    }
    
    .section-content {
        grid-template-columns: 1fr;
    }
    
    .section-header-actions {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .delete-all-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
</head>
<body>

<!-- Mobile Topbar -->
<div class="topbar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <span>Schedule Sections</span>
</div>

<!-- Overlay for Mobile -->
<div class="overlay" onclick="toggleMenu()"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-content" id="sidebarContent">
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
        <a href="assign_instructors.php" class="<?= $current_page=='assign_instructors.php'?'active':'' ?>">
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
</div>

<!-- Confirmation Modal -->
<div class="confirmation-modal" id="confirmationModal">
    <div class="confirmation-modal-content">
        <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
        <p id="confirmationModalText">Are you sure you want to delete this schedule? This action cannot be undone.</p>
        <div class="confirmation-modal-actions">
            <button class="btn-cancel" onclick="closeConfirmationModal()">Cancel</button>
            <a class="btn-confirm-delete" id="confirmDeleteBtn" href="#">Delete Schedule</a>
        </div>
    </div>
</div>

<!-- Delete All Confirmation Modal -->
<div class="confirmation-modal" id="deleteAllConfirmationModal">
    <div class="confirmation-modal-content">
        <h3><i class="fas fa-exclamation-triangle"></i> Delete All Schedules</h3>
        <p id="deleteAllConfirmationText">
            Are you sure you want to delete ALL freshman schedules?<br><br>
            This will delete <strong><?= $total_freshman_schedules ?> schedules</strong> and remove all student enrollments.<br>
            This action cannot be undone!
        </p>
        <div class="confirmation-modal-actions">
            <button class="btn-cancel" onclick="closeDeleteAllConfirmationModal()">Cancel</button>
            <a class="btn-confirm-delete" id="confirmDeleteAllBtn" href="?view=enrollments&delete_all=true&csrf_token=<?= $_SESSION['csrf_token'] ?>">Delete All Schedules</a>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Schedule Multiple Classroom Sections</h1>
                <p>Create parallel sections of the same courses in different classrooms with different students.</p>
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

        <!-- View Tabs -->
        <div class="view-tabs">
            <a href="?view=create" class="tab-button <?= !$view_mode ? 'active' : '' ?>">
                <i class="fas fa-calendar-plus"></i>
                Create Sections
            </a>
            <a href="?view=enrollments" class="tab-button <?= $view_mode ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                View Scheduled Classes
                <span class="badge"><?= count($existing_schedules) ?> sessions</span>
            </a>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Classroom Sections System:</strong> 
                <ol style="margin-left: 20px; margin-top: 8px;">
                    <li>Create <strong>parallel sections</strong> of the same freshman courses</li>
                    <li>Each section is in a <strong>different classroom</strong> with <strong>different students</strong></li>
                    <li>All sections have the <strong>same course schedule</strong> (same days/times)</li>
                    <li>Students are automatically enrolled in their assigned section</li>
                    <li>Each section gets 15 weekly time slots to distribute</li>
                </ol>
            </div>
        </div>

        <!-- Display Error/Success Messages -->
        <?php if($message): ?>
            <div class="message <?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'error' ? 'exclamation-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'check-circle') ?>"></i>
                <?= nl2br(htmlspecialchars($message)) ?>
            </div>
        <?php endif; ?>

        <!-- Enrollment View -->
        <?php if($view_mode && isset($_GET['schedule_id'])): ?>
        <div class="enrollment-view">
            <a href="?view=enrollments" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Back to Schedules
            </a>
            
            <?php if($schedule_details): ?>
                <div class="enrollment-summary">
                    <h2>Class Enrollment Details</h2>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <label>Course:</label>
                            <span><?= htmlspecialchars($schedule_details['course_code']) ?> - <?= htmlspecialchars($schedule_details['course_name']) ?></span>
                        </div>
                        <div class="summary-item">
                            <label>Classroom:</label>
                            <span><?= htmlspecialchars($schedule_details['room_name']) ?></span>
                        </div>
                        <div class="summary-item">
                            <label>Section:</label>
                            <span>Section <?= $schedule_details['section_number'] ?? 1 ?></span>
                        </div>
                        <div class="summary-item">
                            <label>Instructor:</label>
                            <span>
                                <?= htmlspecialchars($schedule_details['instructor_name']) ?>
                                <?php if($schedule_details['instructor_name'] == 'TBA'): ?>
                                    <span class="instructor-badge instructor-tba">TBA</span>
                                <?php else: ?>
                                    <span class="instructor-badge instructor-assigned">Assigned</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="summary-item">
                            <label>Day & Time:</label>
                            <span><?= htmlspecialchars($schedule_details['day']) ?>, 
                                <?= date('g:i A', strtotime($schedule_details['start_time'])) ?> - 
                                <?= date('g:i A', strtotime($schedule_details['end_time'])) ?></span>
                        </div>
                        <div class="summary-item">
                            <label>Academic Year:</label>
                            <span><?= htmlspecialchars($schedule_details['academic_year']) ?></span>
                        </div>
                        <div class="summary-item">
                            <label>Semester:</label>
                            <span><?= $schedule_details['semester'] == '1' ? '1st Semester' : '2nd Semester' ?></span>
                        </div>
                        <div class="summary-item">
                            <label>Total Enrolled Students:</label>
                            <span><?= count($schedule_enrollments) ?></span>
                        </div>
                    </div>
                </div>
                
                <h3 style="color: var(--text-primary); margin-bottom: 15px;">Enrolled Students (<?= count($schedule_enrollments) ?>)</h3>
                
                <?php if(!empty($schedule_enrollments)): ?>
                    <table class="enrolled-students-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Year</th>
                                <th>Department</th>
                                <th>Enrollment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($schedule_enrollments as $enrollment): ?>
                                <tr>
                                    <td><?= htmlspecialchars($enrollment['username']) ?></td>
                                    <td><?= htmlspecialchars($enrollment['email']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($enrollment['year'])) ?></td>
                                    <td><?= htmlspecialchars($enrollment['department_name'] ?? 'N/A') ?></td>
                                    <td><?= date('M d, Y', strtotime($enrollment['enrolled_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-graduate"></i>
                        <h3>No Students Enrolled</h3>
                        <p>No students are currently enrolled in this class schedule.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i>
                    Schedule not found or invalid schedule ID.
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Existing Schedules List -->
        <?php if($view_mode && !isset($_GET['schedule_id'])): ?>
        <div class="existing-schedules-section">
            <div class="section-header-actions">
                <h2>
                    <i class="fas fa-calendar-alt"></i>
                    Existing Classroom Sections
                </h2>
                <button class="delete-all-btn" id="deleteAllBtn" onclick="showDeleteAllConfirmation()" 
                        <?= $total_freshman_schedules == 0 ? 'disabled' : '' ?>>
                    <i class="fas fa-trash-alt"></i>
                    Delete All Schedules
                    <?php if($total_freshman_schedules > 0): ?>
                        <span style="background: white; color: var(--danger-color); border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; margin-left: 5px; font-size: 0.8rem;">
                            <?= $total_freshman_schedules ?>
                        </span>
                    <?php endif; ?>
                </button>
            </div>
            
            <?php if(!empty($existing_schedules)): ?>
                <?php 
                // First, get all unique sections from the schedules
                $all_sections = [];
                foreach($existing_schedules as $schedule) {
                    $section_num = isset($schedule['section_number']) ? $schedule['section_number'] : 1;
                    if(!in_array($section_num, $all_sections)) {
                        $all_sections[] = $section_num;
                    }
                }
                sort($all_sections); // Sort sections numerically
                
                // Display schedules grouped by section first
                foreach($all_sections as $section_number): ?>
                    <div style="margin-bottom: 30px; border-bottom: 2px solid var(--border-color); padding-bottom: 20px;">
                        <h2 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <span class="section-badge section-<?= (($section_number - 1) % 5) + 1 ?>" style="font-size: 1.2rem; padding: 8px 16px;">
                                Section <?= $section_number ?>
                            </span>
                            <span style="font-size: 1rem; color: var(--text-secondary); margin-left: 10px;">
                                (<?= count(array_filter($existing_schedules, function($s) use ($section_number) { 
                                    $section_num = isset($s['section_number']) ? $s['section_number'] : 1;
                                    return $section_num == $section_number; 
                                })) ?> sessions)
                            </span>
                        </h2>
                        
                        <?php
                        // Get schedules for this specific section
                        $section_schedules = array_filter($existing_schedules, function($schedule) use ($section_number) {
                            $section_num = isset($schedule['section_number']) ? $schedule['section_number'] : 1;
                            return $section_num == $section_number;
                        });
                        
                        // Group by course for this specific section
                        $grouped_by_course = [];
                        foreach($section_schedules as $schedule) {
                            $course_key = $schedule['course_id'] . '-' . $schedule['room_id'];
                            if(!isset($grouped_by_course[$course_key])) {
                                $grouped_by_course[$course_key] = [
                                    'course_id' => $schedule['course_id'],
                                    'course_code' => $schedule['course_code'],
                                    'course_name' => $schedule['course_name'],
                                    'room_name' => $schedule['room_name'],
                                    'instructor_name' => $schedule['instructor_name'],
                                    'instructor_email' => $schedule['instructor_email'],
                                    'academic_year' => $schedule['academic_year'],
                                    'semester' => $schedule['semester'],
                                    'schedules' => []
                                ];
                            }
                            $grouped_by_course[$course_key]['schedules'][] = $schedule;
                        }
                        ?>
                        
                        <?php if(empty($grouped_by_course)): ?>
                            <div class="empty-state" style="padding: 20px;">
                                <i class="fas fa-calendar-times"></i>
                                <p>No schedules found for Section <?= $section_number ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach($grouped_by_course as $course_key => $course_data): ?>
                                <div class="schedule-card">
                                    <div class="schedule-header">
                                        <div class="schedule-info">
                                            <h3>
                                                <?= htmlspecialchars($course_data['course_code']) ?> - <?= htmlspecialchars($course_data['course_name']) ?>
                                                <span class="section-badge section-<?= (($section_number - 1) % 5) + 1 ?>">
                                                    Section <?= $section_number ?>
                                                </span>
                                            </h3>
                                            <p><i class="fas fa-door-closed"></i> Classroom: <?= htmlspecialchars($course_data['room_name']) ?></p>
                                            <p>
                                                <i class="fas fa-chalkboard-teacher"></i> Instructor: 
                                                <?= htmlspecialchars($course_data['instructor_name']) ?>
                                                <?php if($course_data['instructor_name'] == 'TBA'): ?>
                                                    <span class="instructor-badge instructor-tba">TBA</span>
                                                <?php else: ?>
                                                    <span class="instructor-badge instructor-assigned">Assigned</span>
                                                <?php endif; ?>
                                            </p>
                                            <p><i class="fas fa-calendar-day"></i> Academic Year: <?= htmlspecialchars($course_data['academic_year']) ?></p>
                                            <p><i class="fas fa-book"></i> Semester: <?= $course_data['semester'] == '1' ? '1st Semester' : '2nd Semester' ?></p>
                                            <p><i class="fas fa-clock"></i> Total Sessions: <?= count($course_data['schedules']) ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="schedule-details">
                                        <?php 
                                        // Calculate total enrolled students across all sessions
                                        $total_enrolled = 0;
                                        $days_with_sessions = [];
                                        foreach($course_data['schedules'] as $session) {
                                            $total_enrolled += $session['enrolled_students'];
                                            $days_with_sessions[$session['day']] = true;
                                        }
                                        ?>
                                        <div class="detail-item">
                                            <i class="fas fa-users"></i>
                                            <div>
                                                <div class="detail-label">Total Enrolled Students:</div>
                                                <div class="detail-value"><?= $total_enrolled ?> students</div>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-calendar-week"></i>
                                            <div>
                                                <div class="detail-label">Scheduled Days:</div>
                                                <div class="detail-value"><?= count($days_with_sessions) ?> / 5 days</div>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-chart-bar"></i>
                                            <div>
                                                <div class="detail-label">Weekly Sessions:</div>
                                                <div class="detail-value"><?= count($course_data['schedules']) ?> / 15 slots</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Show individual session details -->
                                    <div style="margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                                        <h4 style="color: var(--text-primary); margin-bottom: 10px; font-size: 1rem;">
                                            <i class="fas fa-list"></i> Individual Class Sessions:
                                        </h4>
                                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px;">
                                            <?php foreach($course_data['schedules'] as $session): ?>
                                                <div style="background: var(--bg-primary); padding: 10px; border-radius: 6px; border: 1px solid var(--border-color);">
                                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                                        <div>
                                                            <span class="day-badge day-<?= strtolower(substr($session['day'], 0, 3)) ?>">
                                                                <?= htmlspecialchars($session['day']) ?>
                                                            </span>
                                                            <span style="font-family: 'Courier New', monospace; font-weight: 600; margin-left: 8px;">
                                                                <?= date('g:i A', strtotime($session['start_time'])) ?> - <?= date('g:i A', strtotime($session['end_time'])) ?>
                                                            </span>
                                                        </div>
                                                        <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                                            <?= $session['enrolled_students'] ?> students
                                                        </div>
                                                    </div>
                                                    <div style="margin-top: 5px; display: flex; gap: 5px; justify-content: flex-end;">
                                                        <button class="btn-small btn-primary-small" style="padding: 4px 8px; font-size: 0.75rem;" 
                                                                onclick="location.href='?view=enrollments&schedule_id=<?= $session['schedule_id'] ?>'">
                                                            <i class="fas fa-eye"></i> View
                                                        </button>
                                                        <button class="delete-schedule-btn" style="padding: 4px 8px; font-size: 0.75rem;" 
                                                                onclick="showDeleteConfirmation(<?= $session['schedule_id'] ?>, '<?= htmlspecialchars($session['course_code']) ?> - <?= date('g:i A', strtotime($session['start_time'])) ?> on <?= htmlspecialchars($session['day']) ?> (Section <?= $section_number ?>)')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <!-- Summary of all sections -->
                <div style="background: linear-gradient(135deg, #f0f9ff, #e0f2fe); padding: 20px; border-radius: 10px; margin-top: 30px;">
                    <h3 style="color: var(--text-primary); margin-bottom: 15px;">
                        <i class="fas fa-chart-pie"></i> Summary of All Sections
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div style="text-align: center; padding: 10px; background: white; border-radius: 8px;">
                            <div style="font-size: 1.2rem; font-weight: 600; color: var(--primary-color);">
                                <?= count($all_sections) ?>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--text-secondary);">Classroom Sections</div>
                        </div>
                        <div style="text-align: center; padding: 10px; background: white; border-radius: 8px;">
                            <div style="font-size: 1.2rem; font-weight: 600; color: var(--primary-color);">
                                <?= count($existing_schedules) ?>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--text-secondary);">Total Class Sessions</div>
                        </div>
                        <div style="text-align: center; padding: 10px; background: white; border-radius: 8px;">
                            <div style="font-size: 1.2rem; font-weight: 600; color: var(--primary-color);">
                                <?= count(array_unique(array_column($existing_schedules, 'course_id'))) ?>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--text-secondary);">Unique Courses</div>
                        </div>
                        <div style="text-align: center; padding: 10px; background: white; border-radius: 8px;">
                            <?php
                            $total_enrolled_all = array_sum(array_column($existing_schedules, 'enrolled_students'));
                            ?>
                            <div style="font-size: 1.2rem; font-weight: 600; color: var(--primary-color);">
                                <?= $total_enrolled_all ?>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--text-secondary);">Total Enrolled Students</div>
                        </div>
                    </div>
                    
                    <!-- Section breakdown -->
                    <div style="margin-top: 20px;">
                        <h4 style="color: var(--text-primary); margin-bottom: 10px; font-size: 1rem;">
                            <i class="fas fa-door-closed"></i> Section Breakdown:
                        </h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <?php foreach($all_sections as $section): 
                                $section_sessions = array_filter($existing_schedules, function($s) use ($section) { 
                                    $section_num = isset($s['section_number']) ? $s['section_number'] : 1;
                                    return $section_num == $section; 
                                });
                                $section_enrolled = array_sum(array_column($section_sessions, 'enrolled_students'));
                                $section_rooms = array_unique(array_column($section_sessions, 'room_name'));
                            ?>
                                <div style="display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: white; border-radius: 8px;">
                                    <span class="section-badge section-<?= (($section - 1) % 5) + 1 ?>">
                                        Section <?= $section ?>
                                    </span>
                                    <span style="font-size: 0.9rem; color: var(--text-secondary);">
                                        <?= count($section_sessions) ?> sessions, <?= $section_enrolled ?> students
                                        <?php if(!empty($section_rooms)): ?>
                                            <br><small>Room: <?= htmlspecialchars($section_rooms[0]) ?></small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Classroom Sections Found</h3>
                    <p>No classroom sections have been created yet. Use the form below to create new sections.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Recent Schedules Preview -->
        <?php if(!empty($recent_schedules) && !$view_mode): ?>
        <div class="recent-schedules-preview" id="recent-schedules">
            <div class="preview-header">
                <i class="fas fa-eye"></i>
                Recently Created Sections Preview
            </div>
            
            <div class="preview-summary">
                <p><strong>Schedule Summary:</strong></p>
                <p>Total Sections: <span class="count"><?= $recent_schedule_details['total_sections'] ?></span></p>
                <p>Total Sessions Created: <span class="count"><?= $recent_schedule_details['total_sessions'] ?></span></p>
                <p>Academic Year: <span class="count"><?= htmlspecialchars($recent_schedule_details['academic_year']) ?></span></p>
                <p>Semester: <span class="count"><?= $recent_schedule_details['semester'] == '1' ? '1st Semester' : '2nd Semester' ?></span></p>
                
                <?php foreach($recent_schedule_details['section_results'] as $result): ?>
                    <div style="margin-top: 10px; padding: 10px; background: var(--bg-primary); border-radius: 6px;">
                        <strong>Section <?= $result['section_number'] ?>:</strong>
                        <p style="margin: 5px 0;">Classroom: <?= htmlspecialchars($result['room_name']) ?></p>
                        <p style="margin: 5px 0;">Students: <?= $result['student_count'] ?></p>
                        <p style="margin: 5px 0;">Sessions: <?= $result['created_sessions'] ?>/15</p>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php 
            // Group recent schedules by course and section
            $grouped_recent = [];
            foreach($recent_schedules as $schedule) {
                $key = $schedule['course_code'] . '-Section-' . $schedule['section_number'];
                if(!isset($grouped_recent[$key])) {
                    $grouped_recent[$key] = [
                        'course_code' => $schedule['course_code'],
                        'course_name' => $schedule['course_name'],
                        'section_number' => $schedule['section_number'],
                        'room_name' => $schedule['room_name'],
                        'sessions' => []
                    ];
                }
                $grouped_recent[$key]['sessions'][] = $schedule;
            }
            ?>
            
            <?php foreach($grouped_recent as $course_data): ?>
                <h4 style="color: var(--text-primary); margin: 20px 0 10px 0;">
                    <?= htmlspecialchars($course_data['course_code']) ?> - <?= htmlspecialchars($course_data['course_name']) ?>
                    <span class="section-badge section-<?= (($course_data['section_number'] - 1) % 5) + 1 ?>" style="margin-left: 10px;">
                        Section <?= $course_data['section_number'] ?>
                    </span>
                    <span style="font-size: 0.9rem; color: var(--text-secondary); margin-left: 10px;">
                        (<?= count($course_data['sessions']) ?> sessions in <?= htmlspecialchars($course_data['room_name']) ?>)
                    </span>
                </h4>
                <table class="schedules-table">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Time Slot</th>
                            <th>Academic Year</th>
                            <th>Semester</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($course_data['sessions'] as $schedule): ?>
                            <?php 
                            $dayClass = strtolower(str_replace('day', '', $schedule['day']));
                            ?>
                            <tr>
                                <td>
                                    <span class="day-badge day-<?= $dayClass ?>">
                                        <?= htmlspecialchars($schedule['day']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="time-display">
                                        <?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($schedule['academic_year']) ?></td>
                                <td>
                                    <?= $schedule['semester'] == '1' ? '1st Semester' : '2nd Semester' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
            
            <button class="view-all-btn" onclick="location.href='?view=enrollments'">
                <i class="fas fa-users"></i>
                View All Scheduled Classes
            </button>
        </div>
        <?php endif; ?>

        <!-- Classroom Sections Form -->
        <?php if(!$view_mode): ?>
        <form method="POST" class="classroom-sections-form" id="classroomSectionsForm" onsubmit="return validateSectionsForm()">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <!-- Course Selection (same for all sections) -->
            <div class="form-group">
                <div class="form-section-title">
                    <i class="fas fa-book"></i>
                    Select Freshman Courses (Same for All Sections)
                </div>
                
                <?php if(empty($freshman_courses)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h3>No Freshman Courses</h3>
                        <p>No freshman courses available. Please add freshman courses first.</p>
                    </div>
                <?php else: ?>
                    <div class="course-selection">
                        <?php foreach($freshman_courses as $course): ?>
                            <div class="course-item">
                                <input type="checkbox" name="course_ids[]" value="<?= $course['course_id'] ?>" 
                                       id="course_<?= $course['course_id'] ?>" class="course-checkbox"
                                       onchange="updateSelectionCounts()">
                                <label for="course_<?= $course['course_id'] ?>" class="course-info">
                                    <div class="course-code">
                                        <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                                        <span class="freshman-badge">Freshman</span>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Classroom Sections -->
            <div class="form-section-title" style="margin-top: 30px;">
                <i class="fas fa-door-closed"></i>
                Classroom Sections
                <button type="button" class="add-section-btn" onclick="addSection()">
                    <i class="fas fa-plus"></i> Add Section
                </button>
            </div>
            
            <div id="classroomSectionsContainer">
                <!-- Section 1 (default) -->
                <div class="section-container" data-section="1">
                    <div class="section-header">
                        <h3>
                            <span class="section-number section-1">1</span>
                            Classroom Section 1
                        </h3>
                        <button type="button" class="remove-section-btn" onclick="removeSection(1)" <?= count($freshman_students) > 0 ? '' : 'disabled' ?>>
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="section-content">
                        <!-- Room Selection for this section -->
                        <div class="form-group">
                            <label for="room_id_1" class="required">Classroom for Section 1:</label>
                            <select name="classroom_sections[0][room_id]" id="room_id_1" class="section-room" required>
                                <option value="">Select Classroom</option>
                                <?php foreach($rooms as $room): ?>
                                    <option value="<?= $room['room_id'] ?>">
                                        <?= htmlspecialchars($room['room_name']) ?> (Capacity: <?= $room['capacity'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Student Selection for this section -->
                        <div class="form-group">
                            <label for="students_section_1" class="required">Students for Section 1:</label>
                            <?php if(empty($freshman_students)): ?>
                                <div class="empty-state" style="padding: 10px;">
                                    <p>No freshman students available.</p>
                                </div>
                            <?php else: ?>
                                <div class="student-selection" style="max-height: 200px;">
                                    <?php foreach($freshman_students as $student): ?>
                                        <div class="student-item">
                                            <input type="checkbox" name="classroom_sections[0][student_ids][]" 
                                                   value="<?= $student['user_id'] ?>" 
                                                   id="student_section_1_<?= $student['user_id'] ?>"
                                                   class="section-student-checkbox"
                                                   data-section="1">
                                            <label for="student_section_1_<?= $student['user_id'] ?>" class="student-info">
                                                <div class="student-name">
                                                    <?= htmlspecialchars($student['username']) ?>
                                                    <?php if($student['department_name']): ?>
                                                        <span class="department-badge"><?= htmlspecialchars($student['department_name']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="student-email">
                                                    <?= htmlspecialchars($student['email']) ?>
                                                </div>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Academic Info -->
            <div class="academic-info">
                <div class="form-group">
                    <label for="academic_year" class="required">Academic Year:</label>
                    <input type="text" name="academic_year" id="academic_year" 
                           placeholder="e.g., 2026-2027" required
                           value="<?= date('Y') . '-' . (date('Y') + 1) ?>">
                </div>
                
                <div class="form-group">
                    <label for="semester" class="required">Semester:</label>
                    <select name="semester" id="semester" required>
                        <option value="">Select Semester</option>
                        <option value="1" selected>1st Semester</option>
                        <option value="2">2nd Semester</option>
                    </select>
                </div>
            </div>

            <!-- Time Slots Info -->
            <div class="time-slots-section">
                <div class="time-slots-title">
                    <i class="fas fa-clock"></i> Weekly Schedule Time Slots (Monday - Friday)
                    <span style="font-size: 0.9rem; color: var(--text-secondary); margin-left: 10px;">
                        Each course gets 1 session per day × 5 days = up to 15 sessions per course per section
                    </span>
                </div>
                <div class="time-slots-grid">
                    <?php foreach($days_of_week as $day): ?>
                        <div class="time-slot-card">
                            <div class="time-slot-day"><?= $day ?></div>
                            <?php foreach($time_slots as $index => $slot): ?>
                                <span class="time-slot">
                                    Slot <?= $index + 1 ?>: <?= date('g:i A', strtotime($slot[0])) ?> - <?= date('g:i A', strtotime($slot[1])) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Selection Summary -->
            <div class="selection-summary" id="selection-summary" style="display: none;">
                <p><strong>Selected for Scheduling:</strong></p>
                <p>Courses: <span class="count" id="summary-courses">0</span></p>
                <p>Classroom Sections: <span class="count" id="summary-sections">1</span></p>
                <p>Total Students: <span class="count" id="summary-students">0</span></p>
                <div id="section-details"></div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" name="batch_schedule" class="btn btn-primary" id="submit-btn" disabled>
                    <i class="fas fa-calendar-plus"></i>
                    Schedule All Sections
                </button>
                <button type="reset" class="btn btn-secondary" onclick="resetForm()">
                    <i class="fas fa-redo"></i>
                    Reset All
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
let sectionCounter = 1;

// Hamburger toggle
function toggleMenu(){
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

// Add new classroom section
function addSection() {
    sectionCounter++;
    
    const container = document.getElementById('classroomSectionsContainer');
    const sectionDiv = document.createElement('div');
    sectionDiv.className = 'section-container';
    sectionDiv.dataset.section = sectionCounter;
    
    const sectionColorClass = `section-${((sectionCounter - 1) % 5) + 1}`;
    
    sectionDiv.innerHTML = `
        <div class="section-header">
            <h3>
                <span class="section-number ${sectionColorClass}">${sectionCounter}</span>
                Classroom Section ${sectionCounter}
            </h3>
            <button type="button" class="remove-section-btn" onclick="removeSection(${sectionCounter})">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="section-content">
            <div class="form-group">
                <label for="room_id_${sectionCounter}" class="required">Classroom for Section ${sectionCounter}:</label>
                <select name="classroom_sections[${sectionCounter - 1}][room_id]" id="room_id_${sectionCounter}" class="section-room" required>
                    <option value="">Select Classroom</option>
                    <?php foreach($rooms as $room): ?>
                        <option value="<?= $room['room_id'] ?>">
                            <?= htmlspecialchars($room['room_name']) ?> (Capacity: <?= $room['capacity'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="students_section_${sectionCounter}" class="required">Students for Section ${sectionCounter}:</label>
                <?php if(!empty($freshman_students)): ?>
                    <div class="student-selection" style="max-height: 200px;">
                        <?php foreach($freshman_students as $student): ?>
                            <div class="student-item">
                                <input type="checkbox" name="classroom_sections[${sectionCounter - 1}][student_ids][]" 
                                       value="<?= $student['user_id'] ?>" 
                                       id="student_section_${sectionCounter}_<?= $student['user_id'] ?>"
                                       class="section-student-checkbox"
                                       data-section="${sectionCounter}">
                                <label for="student_section_${sectionCounter}_<?= $student['user_id'] ?>" class="student-info">
                                    <div class="student-name">
                                        <?= htmlspecialchars($student['username']) ?>
                                        <?php if($student['department_name']): ?>
                                            <span class="department-badge"><?= htmlspecialchars($student['department_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="student-email">
                                        <?= htmlspecialchars($student['email']) ?>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    `;
    
    container.appendChild(sectionDiv);
    updateSelectionCounts();
}

// Remove classroom section
function removeSection(sectionNumber) {
    if (sectionCounter <= 1) {
        alert("You must have at least one classroom section.");
        return;
    }
    
    if (confirm(`Are you sure you want to remove Section ${sectionNumber}?`)) {
        const sectionDiv = document.querySelector(`[data-section="${sectionNumber}"]`);
        if (sectionDiv) {
            sectionDiv.remove();
            sectionCounter--;
            
            // Renumber remaining sections
            const sections = document.querySelectorAll('.section-container');
            sections.forEach((section, index) => {
                const newNumber = index + 1;
                section.dataset.section = newNumber;
                
                // Update section number display
                const sectionNumberSpan = section.querySelector('.section-number');
                sectionNumberSpan.textContent = newNumber;
                sectionNumberSpan.className = `section-number section-${((newNumber - 1) % 5) + 1}`;
                
                // Update headers
                const header = section.querySelector('h3');
                header.innerHTML = `<span class="section-number section-${((newNumber - 1) % 5) + 1}">${newNumber}</span> Classroom Section ${newNumber}`;
                
                // Update room select name
                const roomSelect = section.querySelector('.section-room');
                roomSelect.name = `classroom_sections[${newNumber - 1}][room_id]`;
                roomSelect.id = `room_id_${newNumber}`;
                roomSelect.previousElementSibling.setAttribute('for', `room_id_${newNumber}`);
                roomSelect.previousElementSibling.textContent = `Classroom for Section ${newNumber}:`;
                
                // Update student checkboxes
                const studentCheckboxes = section.querySelectorAll('.section-student-checkbox');
                studentCheckboxes.forEach(checkbox => {
                    checkbox.name = `classroom_sections[${newNumber - 1}][student_ids][]`;
                    checkbox.dataset.section = newNumber;
                    const studentId = checkbox.value;
                    checkbox.id = `student_section_${newNumber}_${studentId}`;
                    checkbox.nextElementSibling.setAttribute('for', `student_section_${newNumber}_${studentId}`);
                });
            });
            
            updateSelectionCounts();
        }
    }
}

// Update selection counts
function updateSelectionCounts() {
    const courseCheckboxes = document.querySelectorAll('.course-checkbox:checked');
    const sectionContainers = document.querySelectorAll('.section-container');
    const submitBtn = document.getElementById('submit-btn');
    const summaryDiv = document.getElementById('selection-summary');
    
    // Count total students across all sections
    let totalStudents = 0;
    const sectionDetails = [];
    
    sectionContainers.forEach((section, index) => {
        const sectionNumber = index + 1;
        const studentCheckboxes = section.querySelectorAll('.section-student-checkbox:checked');
        const roomSelect = section.querySelector('.section-room');
        const roomSelected = roomSelect && roomSelect.value;
        
        totalStudents += studentCheckboxes.length;
        
        sectionDetails.push({
            section: sectionNumber,
            students: studentCheckboxes.length,
            room: roomSelected ? roomSelect.options[roomSelect.selectedIndex].text : 'Not selected'
        });
    });
    
    // Update summary
    if(document.getElementById('summary-courses')) {
        document.getElementById('summary-courses').textContent = courseCheckboxes.length;
    }
    if(document.getElementById('summary-sections')) {
        document.getElementById('summary-sections').textContent = sectionContainers.length;
    }
    if(document.getElementById('summary-students')) {
        document.getElementById('summary-students').textContent = totalStudents;
    }
    
    // Update section details
    const sectionDetailsDiv = document.getElementById('section-details');
    if(sectionDetailsDiv) {
        sectionDetailsDiv.innerHTML = '';
        sectionDetails.forEach(detail => {
            const colorClass = `section-${((detail.section - 1) % 5) + 1}`;
            sectionDetailsDiv.innerHTML += `
                <div style="margin-top: 5px; padding: 5px; background: var(--bg-primary); border-radius: 4px;">
                    <span class="section-badge ${colorClass}">Section ${detail.section}</span>
                    ${detail.students} students, ${detail.room}
                </div>
            `;
        });
    }
    
    // Show/hide summary
    if(summaryDiv) {
        if(courseCheckboxes.length > 0 || totalStudents > 0) {
            summaryDiv.style.display = 'block';
        } else {
            summaryDiv.style.display = 'none';
        }
    }
    
    // Enable/disable submit button
    if(submitBtn) {
        const hasCourses = courseCheckboxes.length > 0;
        const hasStudents = totalStudents > 0;
        const allSectionsHaveRooms = Array.from(sectionContainers).every(section => {
            const roomSelect = section.querySelector('.section-room');
            return roomSelect && roomSelect.value;
        });
        
        submitBtn.disabled = !(hasCourses && hasStudents && allSectionsHaveRooms && sectionContainers.length > 0);
    }
}

// Form validation
function validateSectionsForm() {
    const courseCheckboxes = document.querySelectorAll('.course-checkbox:checked');
    const sectionContainers = document.querySelectorAll('.section-container');
    
    if(courseCheckboxes.length === 0) {
        alert('Please select at least one freshman course.');
        return false;
    }
    
    if(sectionContainers.length === 0) {
        alert('Please create at least one classroom section.');
        return false;
    }
    
    // Check each section
    let hasErrors = false;
    sectionContainers.forEach((section, index) => {
        const sectionNumber = index + 1;
        const studentCheckboxes = section.querySelectorAll('.section-student-checkbox:checked');
        const roomSelect = section.querySelector('.section-room');
        
        if(studentCheckboxes.length === 0) {
            alert(`Section ${sectionNumber}: Please select at least one student.`);
            hasErrors = true;
        }
        
        if(!roomSelect || !roomSelect.value) {
            alert(`Section ${sectionNumber}: Please select a classroom.`);
            hasErrors = true;
        }
    });
    
    if(hasErrors) return false;
    
    const academicYear = document.getElementById('academic_year').value.trim();
    const semester = document.getElementById('semester').value;
    
    if(!academicYear) {
        alert('Please enter academic year.');
        return false;
    }
    
    if(!semester) {
        alert('Please select semester.');
        return false;
    }
    
    // Confirm before submitting
    const totalStudents = document.getElementById('summary-students').textContent;
    const totalSections = document.getElementById('summary-sections').textContent;
    const totalCourses = courseCheckboxes.length;
    
    const confirmation = confirm(
        `Schedule ${totalCourses} courses for ${totalSections} classroom sections:\n\n` +
        `• Courses: ${totalCourses} freshman courses (same for all sections)\n` +
        `• Sections: ${totalSections} classroom sections (different rooms)\n` +
        `• Total Students: ${totalStudents}\n` +
        `• Academic Year: ${academicYear}\n` +
        `• Semester: ${semester === '1' ? '1st Semester' : '2nd Semester'}\n\n` +
        `Each section will have parallel schedules in their assigned classrooms.\n\n` +
        `Continue?`
    );
    
    return confirmation;
}

// Reset form
function resetForm() {
    if(confirm('Are you sure you want to reset all selections and sections?')) {
        // Remove all sections except the first one
        const sections = document.querySelectorAll('.section-container');
        sections.forEach((section, index) => {
            if(index > 0) {
                section.remove();
            }
        });
        
        // Reset first section
        const firstSection = document.querySelector('.section-container');
        if(firstSection) {
            const checkboxes = firstSection.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => checkbox.checked = false);
            const selects = firstSection.querySelectorAll('select');
            selects.forEach(select => select.selectedIndex = 0);
        }
        
        // Reset course selection
        const courseCheckboxes = document.querySelectorAll('.course-checkbox');
        courseCheckboxes.forEach(checkbox => checkbox.checked = false);
        
        // Reset academic info
        document.getElementById('academic_year').value = '';
        document.getElementById('semester').selectedIndex = 0;
        
        updateSelectionCounts();
    }
}

// Delete confirmation functions
function showDeleteConfirmation(scheduleId, scheduleInfo) {
    const modal = document.getElementById('confirmationModal');
    const text = document.getElementById('confirmationModalText');
    const deleteBtn = document.getElementById('confirmDeleteBtn');
    
    text.textContent = `Are you sure you want to delete this schedule?\n\n` +
                      `Schedule: ${scheduleInfo}\n\n` +
                      `This will also remove all student enrollments for this session. ` +
                      `This action cannot be undone.`;
    
    // Set the delete URL with proper encoding
    deleteBtn.href = `?delete_schedule=${scheduleId}`;
    
    modal.style.display = 'flex';
}

function closeConfirmationModal() {
    document.getElementById('confirmationModal').style.display = 'none';
}

// Delete all confirmation functions
function showDeleteAllConfirmation() {
    const modal = document.getElementById('deleteAllConfirmationModal');
    modal.style.display = 'flex';
}

function closeDeleteAllConfirmationModal() {
    document.getElementById('deleteAllConfirmationModal').style.display = 'none';
}

// Close modal when clicking outside
document.querySelectorAll('.confirmation-modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if(e.target === this) {
            this.style.display = 'none';
        }
    });
});

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
    
    // Confirm logout
    document.querySelector('a[href="../logout.php"]')?.addEventListener('click', function(e) {
        if(!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });
    
    // Add change event listeners to all section elements
    document.addEventListener('change', function(e) {
        if(e.target.classList.contains('section-room') || 
           e.target.classList.contains('section-student-checkbox') ||
           e.target.classList.contains('course-checkbox')) {
            updateSelectionCounts();
        }
    });
    
    // Initialize counters
    updateSelectionCounts();
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