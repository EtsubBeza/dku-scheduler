<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only allow department head
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_head') {
    header("Location: ../index.php");
    exit;
}

$dept_id = $_SESSION['department_id'] ?? 0;
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

// Check for stored session messages from redirect
if(isset($_SESSION['schedule_message'])) {
    $message = $_SESSION['schedule_message'];
    $message_type = $_SESSION['schedule_message_type'];
    unset($_SESSION['schedule_message']);
    unset($_SESSION['schedule_message_type']);
}

// Helpers
function fetchAllSafe($stmt) {
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $res ?: [];
}

function setMessage(&$message, &$message_type, $text, $type = "success") {
    $message = $text;
    $message_type = $type;
}

// Create necessary tables if they don't exist
function ensureTablesExist($pdo) {
    // Create rooms table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rooms (
            room_id INT AUTO_INCREMENT PRIMARY KEY,
            room_name VARCHAR(50) NOT NULL,
            capacity INT,
            room_type ENUM('classroom', 'lab', 'auditorium') DEFAULT 'classroom',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create schedule table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schedule (
            schedule_id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            instructor_id INT NOT NULL,
            room_id INT NOT NULL,
            day ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            academic_year VARCHAR(10) NOT NULL,
            semester VARCHAR(20) NOT NULL,
            year VARCHAR(10) NOT NULL,
            is_extension TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_course (course_id),
            INDEX idx_instructor (instructor_id),
            INDEX idx_room_day (room_id, day, start_time),
            INDEX idx_department_schedule (academic_year, semester, year, is_extension)
        )
    ");
    
    // Insert sample rooms if none exist
    $roomCheck = $pdo->query("SELECT COUNT(*) FROM rooms");
    if ($roomCheck->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO rooms (room_name, capacity, room_type) VALUES 
            ('Room 101', 30, 'classroom'),
            ('Room 102', 25, 'classroom'),
            ('Lab A', 20, 'lab'),
            ('Room 201', 40, 'classroom'),
            ('Auditorium', 100, 'auditorium')
        ");
    }
}

// Ensure tables exist
ensureTablesExist($pdo);

// ---------------- DELETE SELECTED ----------------
if(isset($_POST['delete_selected'])){
    $to_delete = $_POST['delete_ids'] ?? [];
    if(!empty($to_delete)){
        $placeholders = implode(',', array_fill(0, count($to_delete), '?'));
        $del_stmt = $pdo->prepare("DELETE FROM schedule WHERE schedule_id IN ($placeholders)");
        if($del_stmt->execute($to_delete)){
            setMessage($message, $message_type, count($to_delete)." schedule(s) deleted successfully.","success");
        } else {
            setMessage($message, $message_type, "Failed to delete schedules.","error");
        }
    } else {
        setMessage($message, $message_type, "No schedules selected for deletion.","error");
    }
}

// ---------------- CLEAR ALL SCHEDULES ----------------
if(isset($_POST['clear_all_schedules'])) {
    try {
        // Delete all schedules for this department
        $clear_stmt = $pdo->prepare("
            DELETE s FROM schedule s
            JOIN courses c ON s.course_id = c.course_id
            WHERE c.department_id = ?
        ");
        if($clear_stmt->execute([$dept_id])) {
            $rowCount = $clear_stmt->rowCount();
            setMessage($message, $message_type, "Successfully cleared all $rowCount schedule(s).", "success");
        } else {
            setMessage($message, $message_type, "Failed to clear schedules.", "error");
        }
    } catch(Exception $e) {
        setMessage($message, $message_type, "Error clearing schedules: " . $e->getMessage(), "error");
    }
}

// ---------------- AUTO GENERATE SCHEDULE ----------------
if(isset($_POST['auto_generate'])){
    // Get form data
    $auto_courses = $_POST['auto_courses'] ?? [];
    $auto_year = trim($_POST['auto_year'] ?? '');
    $auto_semester = trim($_POST['auto_semester'] ?? '');
    $auto_academic_year = trim($_POST['auto_academic_year'] ?? '');
    $auto_room_id = $_POST['room_id'] ?? null; // NEW: Room selection

    // Validate year (supports regular years 1-5 and extension years E1-E5)
    $is_extension = substr($auto_year, 0, 1) === 'E';
    $year_value = $is_extension ? substr($auto_year, 1) : $auto_year;
    
    // Validation
    if(empty($auto_courses) || empty($auto_semester) || empty($auto_academic_year) || empty($auto_year)){
        setMessage($message, $message_type, "Please fill all fields correctly. Missing: " . 
            (empty($auto_courses) ? "courses, " : "") .
            (empty($auto_semester) ? "semester, " : "") .
            (empty($auto_academic_year) ? "academic year, " : "") .
            (empty($auto_year) ? "year" : ""), 
        "error");
    } elseif($is_extension) {
        // Validate extension year
        if(!is_numeric($year_value) || $year_value < 1 || $year_value > 5) {
            setMessage($message, $message_type, "Invalid extension year selected. Please select Extension Year 1-5.", "error");
        } else {
            // Proceed with auto-generation for extension students
            processAutoGeneration($is_extension, $auto_room_id);
        }
    } else {
        // Validate regular year
        if(!is_numeric($year_value) || $year_value < 1 || $year_value > 5) {
            setMessage($message, $message_type, "Invalid year selected. Please select Year 1-5.", "error");
        } else {
            // Proceed with auto-generation for regular students
            processAutoGeneration($is_extension, $auto_room_id);
        }
    }
}

function processAutoGeneration($is_extension = false, $selected_room_id = null) {
    global $pdo, $auto_courses, $auto_year, $auto_semester, $auto_academic_year, $message, $message_type, $dept_id;
    
    error_log("Auto generation started for department $dept_id");
    error_log("Courses to schedule: " . implode(', ', $auto_courses));
    
    // Define available days based on student type
    if ($is_extension) {
        // Extension students: Saturday and Sunday only
        $days = ["Saturday", "Sunday"];
        // Time slots for extension students
        $time_slots = [
            ["08:00:00", "11:00:00"],    // Morning
            ["02:30:00", "04:00:00"],    // Afternoon 1
            ["04:30:00", "06:00:00"]     // Afternoon 2
        ];
        $total_slots = 6; // 2 days × 3 slots
    } else {
        // Regular students: Monday to Friday
        $days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
        $time_slots = [
            ["08:00:00", "11:00:00"],    // Morning
            ["02:30:00", "04:20:00"],    // Afternoon 1
            ["04:30:00", "06:20:00"]     // Afternoon 2
        ];
        $total_slots = 15; // 5 days × 3 slots
    }

    try {
        // Clear existing schedules for this combination first
        $clear_stmt = $pdo->prepare("
            DELETE FROM schedule 
            WHERE year = ?
            AND semester = ?
            AND academic_year = ?
            AND is_extension = ?
        ");
        
        $clear_stmt->execute([$auto_year, $auto_semester, $auto_academic_year, $is_extension ? 1 : 0]);
        error_log("Cleared existing schedules for this combination");
        
        // Get all available rooms
        $rooms_stmt = $pdo->prepare("SELECT * FROM rooms ORDER BY room_id");
        $rooms_stmt->execute();
        $rooms = fetchAllSafe($rooms_stmt);
        
        if(empty($rooms)) {
            setMessage($message, $message_type, "No rooms found in the system. Please add rooms first.", "error");
            return;
        }
        
        // Get ALL instructors from the department
        $instructors_stmt = $pdo->prepare("
            SELECT user_id, full_name, username 
            FROM users 
            WHERE role = 'instructor' 
            AND department_id = ?
            ORDER BY user_id
        ");
        $instructors_stmt->execute([$dept_id]);
        $instructors = fetchAllSafe($instructors_stmt);
        
        if(empty($instructors)) {
            setMessage($message, $message_type, "No instructors found in your department. Please add instructors first.", "error");
            return;
        }
        
        // Verify courses exist
        $course_details = [];
        foreach($auto_courses as $course_id) {
            $course_stmt = $pdo->prepare("SELECT course_name, course_code FROM courses WHERE course_id = ? AND department_id = ?");
            $course_stmt->execute([$course_id, $dept_id]);
            $course = $course_stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$course) {
                setMessage($message, $message_type, "Course ID $course_id not found or doesn't belong to your department.", "error");
                return;
            }
            $course_details[$course_id] = $course;
        }

        // DETERMINE ROOM TO USE
        $room_id = null;
        $room_name = "";
        
        if($selected_room_id) {
            // Use the user-selected room
            $room_stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_id = ?");
            $room_stmt->execute([$selected_room_id]);
            $selected_room = $room_stmt->fetch(PDO::FETCH_ASSOC);
            
            if($selected_room) {
                $room_id = $selected_room['room_id'];
                $room_name = $selected_room['room_name'];
            } else {
                // Fall back to first room if selected room doesn't exist
                $room_id = $rooms[0]['room_id'];
                $room_name = $rooms[0]['room_name'];
            }
        } else {
            // Auto-select the first available classroom (not lab or auditorium)
            foreach($rooms as $room) {
                if($room['room_type'] === 'classroom') {
                    $room_id = $room['room_id'];
                    $room_name = $room['room_name'];
                    break;
                }
            }
            
            // If no classroom found, use first room
            if(!$room_id && !empty($rooms)) {
                $room_id = $rooms[0]['room_id'];
                $room_name = $rooms[0]['room_name'];
            }
        }
        
        if(!$room_id) {
            setMessage($message, $message_type, "No room available for scheduling.", "error");
            return;
        }

        $pdo->beginTransaction();
        $scheduled_count = 0;
        $schedule_plan = [];
        
        // Calculate how many times we need to repeat courses to fill all slots
        $courses_count = count($auto_courses);
        $repeat_count = ceil($total_slots / $courses_count);
        
        // Create an array of courses to fill all slots (repeat if necessary)
        $courses_to_schedule = [];
        for($i = 0; $i < $repeat_count; $i++) {
            foreach($auto_courses as $course_id) {
                if(count($courses_to_schedule) < $total_slots) {
                    $courses_to_schedule[] = $course_id;
                } else {
                    break 2; // Stop if we have enough courses to fill all slots
                }
            }
        }
        
        // If we still don't have enough to fill all slots, repeat the first course
        while(count($courses_to_schedule) < $total_slots) {
            $courses_to_schedule[] = $auto_courses[0];
        }
        
        error_log("Total slots to fill: $total_slots, Courses prepared: " . count($courses_to_schedule));
        error_log("Using room: $room_name (ID: $room_id) for all classes");
        
        // SYSTEMATICALLY FILL ALL SLOTS WITH SAME ROOM
        $instructor_index = 0;
        $course_index = 0;
        
        // Loop through each day
        foreach($days as $day) {
            // Loop through each time slot
            foreach($time_slots as $time_slot) {
                // Get the next course
                $course_id = $courses_to_schedule[$course_index % count($courses_to_schedule)];
                $course = $course_details[$course_id];
                $course_name = $course['course_name'];
                $course_code = $course['course_code'];
                
                $start_time = $time_slot[0];
                $end_time = $time_slot[1];
                
                // Get instructor (round robin)
                $instructor = $instructors[$instructor_index % count($instructors)];
                $instructor_id = $instructor['user_id'];
                $instructor_name = !empty($instructor['full_name']) ? $instructor['full_name'] : $instructor['username'];
                
                // Insert schedule - SAME ROOM FOR ALL
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO schedule 
                        (course_id, instructor_id, room_id, day, start_time, end_time, 
                         academic_year, semester, year, is_extension, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $result = $stmt->execute([
                        $course_id, 
                        $instructor_id, 
                        $room_id, // SAME ROOM FOR ALL
                        $day, 
                        $start_time, 
                        $end_time, 
                        $auto_academic_year, 
                        $auto_semester, 
                        $auto_year,
                        $is_extension ? 1 : 0
                    ]);
                    
                    if($result) {
                        $scheduled_count++;
                        $schedule_plan[] = [
                            'course_id' => $course_id,
                            'course_name' => $course_name,
                            'course_code' => $course_code,
                            'day' => $day,
                            'time' => substr($start_time, 0, 5) . ' - ' . substr($end_time, 0, 5),
                            'room' => $room_name, // SAME ROOM
                            'instructor' => $instructor_name
                        ];
                        
                        error_log("Scheduled: $course_code on $day at $start_time in $room_name");
                        
                        // Move to next course and instructor
                        $course_index++;
                        $instructor_index++;
                    } else {
                        error_log("Failed to insert schedule for $course_code");
                    }
                } catch(PDOException $e) {
                    error_log("Database error scheduling $course_code: " . $e->getMessage());
                }
            }
        }

        $pdo->commit();
        error_log("Successfully scheduled $scheduled_count out of $total_slots slots");

        // Prepare success message
        if($scheduled_count > 0) {
            $year_text = $is_extension ? 
                "Extension Year " . substr($auto_year, 1) : 
                "Year $auto_year";
            
            $slot_usage = round(($scheduled_count / $total_slots) * 100, 1);
            
            $schedule_info = "✅ SUCCESS! Filled $scheduled_count out of $total_slots available slots!\n\n";
            $schedule_info .= "📅 For: $year_text\n";
            $schedule_info .= "📍 All Classes in: $room_name\n";
            $schedule_info .= "📊 Slot Utilization: $slot_usage% ($scheduled_count/$total_slots slots filled)\n";
            $schedule_info .= "📚 Semester: $auto_semester $auto_academic_year\n\n";
            
            // Organize schedule by day for better display
            $schedule_by_day = [];
            foreach($schedule_plan as $session) {
                $schedule_by_day[$session['day']][] = $session;
            }
            
            $schedule_info .= "📋 Schedule Details:\n";
            foreach($days as $day) {
                if(isset($schedule_by_day[$day])) {
                    $schedule_info .= "\n$day:\n";
                    foreach($schedule_by_day[$day] as $session) {
                        $time_display = substr($session['time'], 0, 5) . '-' . substr($session['time'], 8, 5);
                        $schedule_info .= "  ⏰ $time_display: {$session['course_code']}\n";
                        $schedule_info .= "     👨‍🏫 {$session['instructor']}\n";
                    }
                } else {
                    $schedule_info .= "\n$day: No classes scheduled\n";
                }
            }
            
            if($scheduled_count < $total_slots) {
                $empty_slots = $total_slots - $scheduled_count;
                $schedule_info .= "\n⚠️ Note: $empty_slots slot(s) could not be filled.\n";
                $final_message_type = "warning";
            } else {
                $schedule_info .= "\n🎉 ALL $total_slots slots successfully filled!\n";
                $schedule_info .= "🏫 All classes are in: $room_name";
                $final_message_type = "success";
            }
            
            $final_message = $schedule_info;
        } else {
            $final_message = "❌ ERROR: Could not schedule any courses.\n\n";
            $final_message .= "🔍 Check if:\n";
            $final_message .= "1. You have rooms in the system\n";
            $final_message .= "2. You have instructors in your department\n";
            $final_message .= "3. The selected courses exist\n\n";
            $final_message .= "💡 Try adding rooms/instructors first, then try again.";
            $final_message_type = "error";
        }

        // Store message in session and redirect
        $_SESSION['schedule_message'] = $final_message;
        $_SESSION['schedule_message_type'] = $final_message_type;
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
        
    } catch(Exception $e) {
        if(isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Store error in session instead of setting message directly
        $_SESSION['schedule_message'] = "Auto-scheduling failed: " . $e->getMessage();
        $_SESSION['schedule_message_type'] = "error";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ---------------- FETCH DATA ----------------
// Fetch courses for the department
$courses_stmt = $pdo->prepare("SELECT * FROM courses WHERE department_id = ? ORDER BY course_name");
$courses_stmt->execute([$dept_id]);
$courses = fetchAllSafe($courses_stmt);

// Fetch all rooms for dropdown
$rooms_stmt = $pdo->prepare("SELECT * FROM rooms ORDER BY room_name");
$rooms_stmt->execute();
$all_rooms = fetchAllSafe($rooms_stmt);

// Fetch current schedules
$schedules_stmt = $pdo->prepare("
    SELECT s.*, c.course_name, c.course_code, u.full_name AS instructor_name, r.room_name,
           TIME_FORMAT(s.start_time,'%H:%i') as start_time, TIME_FORMAT(s.end_time,'%H:%i') as end_time
    FROM schedule s
    LEFT JOIN courses c ON s.course_id = c.course_id
    LEFT JOIN users u ON s.instructor_id = u.user_id
    LEFT JOIN rooms r ON s.room_id = r.room_id
    WHERE c.department_id = ? OR (c.department_id IS NULL AND s.course_id IN (SELECT course_id FROM courses WHERE department_id = ?))
    ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time
");
$schedules_stmt->execute([$dept_id, $dept_id]);
$all_schedules = fetchAllSafe($schedules_stmt);

// Group schedules for display
$grouped_schedules = [];
foreach($all_schedules as $schedule){
    $key = $schedule['schedule_id'];
    $grouped_schedules[$key] = [
        'schedule_id' => $schedule['schedule_id'],
        'course_name' => $schedule['course_name'] ?? 'Unknown Course',
        'course_code' => $schedule['course_code'] ?? 'N/A',
        'instructor_name' => $schedule['instructor_name'] ?? 'Unknown Instructor',
        'room_name' => $schedule['room_name'] ?? 'Unknown Room',
        'academic_year' => $schedule['academic_year'],
        'semester' => $schedule['semester'],
        'year' => $schedule['year'],
        'day' => $schedule['day'],
        'time_slots' => [$schedule['day'].' '.$schedule['start_time'].' - '.$schedule['end_time']]
    ];
}

// Fetch stats for dashboard
$total_courses = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE department_id = ?");
$total_courses->execute([$dept_id]);
$total_courses_count = $total_courses->fetchColumn();

$active_instructors = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ? AND role = 'instructor'");
$active_instructors->execute([$dept_id]);
$active_instructors_count = $active_instructors->fetchColumn();

$available_rooms = $pdo->query("SELECT COUNT(*) FROM rooms");
$available_rooms_count = $available_rooms->fetchColumn();

$scheduled_classes = $pdo->prepare("
    SELECT COUNT(*) 
    FROM schedule s 
    JOIN courses c ON s.course_id = c.course_id 
    WHERE c.department_id = ?
");
$scheduled_classes->execute([$dept_id]);
$scheduled_classes_count = $scheduled_classes->fetchColumn();

// Fetch recent schedules for preview
$recent_schedules_stmt = $pdo->prepare("
    SELECT s.day, TIME_FORMAT(s.start_time,'%H:%i') as start_time, 
           TIME_FORMAT(s.end_time,'%H:%i') as end_time, 
           COALESCE(c.course_name, 'Unknown Course') as course_name, 
           COALESCE(c.course_code, 'N/A') as course_code,
           COALESCE(u.full_name, 'Unknown Instructor') as instructor_name, 
           COALESCE(r.room_name, 'Unknown Room') as room_name, 
           s.year
    FROM schedule s
    LEFT JOIN courses c ON s.course_id = c.course_id
    LEFT JOIN users u ON s.instructor_id = u.user_id
    LEFT JOIN rooms r ON s.room_id = r.room_id
    WHERE c.department_id = ? OR (c.department_id IS NULL AND s.course_id IN (SELECT course_id FROM courses WHERE department_id = ?))
    ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
             s.start_time
    LIMIT 30
");
$recent_schedules_stmt->execute([$dept_id, $dept_id]);
$recent_schedules = fetchAllSafe($recent_schedules_stmt);

// Group recent schedules by day for preview
$preview_schedules = [
    'Monday' => [], 'Tuesday' => [], 'Wednesday' => [], 'Thursday' => [], 'Friday' => [],
    'Saturday' => [], 'Sunday' => []
];

foreach($recent_schedules as $schedule){
    $preview_schedules[$schedule['day']][] = $schedule;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management | Department Head Portal</title>
    <!-- Include Dark Mode -->
    <?php include __DIR__ . '/../includes/darkmode.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ================= CSS VARIABLES ================= */
        :root {
            --bg-primary: #f9fafb;
            --bg-secondary: #f3f4f6;
            --bg-card: #ffffff;
            --bg-sidebar: #1f2937;
            --bg-input: #ffffff;
            --text-primary: #111827;
            --text-light: #6b7280;
            --text-sidebar: #e5e7eb;
            --border-color: #d1d5db;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --hover-color: rgba(99, 102, 241, 0.05);
            --table-header: #f9fafb;
            --success-bg: #d1fae5;
            --success-text: #065f46;
            --error-bg: #fee2e2;
            --error-text: #991b1b;
            --warning-bg: #fef3c7;
            --warning-text: #92400e;
        }

        /* Dark mode overrides */
        .dark-mode {
            --bg-primary: #111827;
            --bg-secondary: #1f2937;
            --bg-card: #1f2937;
            --bg-sidebar: #111827;
            --bg-input: #374151;
            --text-primary: #f9fafb;
            --text-light: #d1d5db;
            --text-sidebar: #f3f4f6;
            --border-color: #4b5563;
            --shadow-color: rgba(0, 0, 0, 0.3);
            --hover-color: rgba(99, 102, 241, 0.1);
            --table-header: #374151;
        }

        /* ================= RESET & BASE STYLES ================= */
        * { 
            box-sizing: border-box; 
            margin:0; 
            padding:0; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }

        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
            overflow-x: hidden;
        }

        /* ================= TOPBAR FOR MOBILE ================= */
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
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

        /* ================= SIDEBAR ================= */
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
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
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
            font-size: 0.95rem;
        }

        .sidebar a i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background:#1abc9c;
            color:white;
            padding-left: 25px;
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
            border: 3px solid #1abc9c;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .sidebar-profile p {
            color: var(--text-sidebar);
            font-weight: bold;
            margin: 0;
            font-size: 16px;
        }

        /* ================= OVERLAY ================= */
        .overlay {
            position: fixed;
            top:0;
            left:0;
            width:100%;
            height:100%;
            background: rgba(0,0,0,0.5);
            z-index:1050;
            display:none;
            opacity:0;
            transition: opacity 0.3s ease;
        }

        .overlay.active {
            display:block;
            opacity:1;
        }

        /* ================= MAIN CONTENT ================= */
        .main-content {
            margin-left: 250px;
            padding:30px 50px;
            min-height:100vh;
            transition: all 0.3s ease;
        }

        /* ================= HEADER STYLES ================= */
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
            background: linear-gradient(135deg, #6366f1, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--bg-card);
            padding: 12px 18px;
            border-radius: 12px;
            box-shadow: 0 4px 12px var(--shadow-color);
            transition: transform 0.3s ease;
        }

        .user-info:hover {
            transform: translateY(-2px);
        }

        .user-info img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #6366f1;
        }

        .user-info div {
            line-height: 1.4;
        }

        .user-info small {
            color: var(--text-light);
            font-size: 0.85rem;
        }

        /* ================= CARD STYLES ================= */
        .card {
            background: var(--bg-card);
            border-radius: 15px;
            box-shadow: 0 6px 18px var(--shadow-color);
            margin-bottom: 25px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px var(--shadow-color);
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge {
            background: #10b981;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .card-body {
            padding: 25px;
        }

        /* ================= STATS ================= */
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            flex: 1;
            background: var(--bg-card);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 6px 18px var(--shadow-color);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px var(--shadow-color);
        }

        .stat-card i {
            font-size: 2.5rem;
            color: #6366f1;
            margin-bottom: 15px;
        }

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .stat-card p {
            color: var(--text-light);
            font-weight: 500;
            font-size: 0.95rem;
        }

        /* ================= FORM STYLES ================= */
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

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        /* ================= BUTTON STYLES ================= */
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
            background: linear-gradient(135deg, #6366f1, #3b82f6);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4f46e5, #2563eb);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #d97706, #b45309);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* ================= MESSAGE STYLES ================= */
        .message {
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-weight: 500;
            white-space: pre-line;
            line-height: 1.6;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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

        /* ================= TABLE STYLES ================= */
        .table-container {
            overflow-x: auto;
            border-radius: 15px;
            box-shadow: 0 4px 12px var(--shadow-color);
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-card);
        }

        .schedule-table th,
        .schedule-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .schedule-table th {
            background: var(--table-header);
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .schedule-table tr:last-child td {
            border-bottom: none;
        }

        .schedule-table tr:hover {
            background: var(--hover-color);
        }

        .schedule-table tr.selected {
            background-color: rgba(99, 102, 241, 0.1);
        }

        .checkbox-cell {
            width: 50px;
            text-align: center;
        }

        /* ================= YEAR BADGE STYLING ================= */
        .year-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 5px;
        }

        .regular-year {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .extension-year {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }

        /* ================= SEMESTER BADGE STYLING ================= */
        .semester-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .semester-1 {
            background: linear-gradient(135deg, #10b981, #047857);
            color: white;
        }

        .semester-2 {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .semester-summer {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
        }

        /* ================= SCHEDULE PREVIEW ================= */
        .schedule-preview {
            margin-top: 30px;
        }

        .preview-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .preview-day {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px var(--shadow-color);
            min-height: 280px;
            transition: transform 0.3s ease;
        }

        .preview-day:hover {
            transform: translateY(-3px);
        }

        .preview-day h4 {
            text-align: center;
            margin-bottom: 15px;
            color: #6366f1;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            font-weight: 600;
        }

        .time-slot {
            background: var(--bg-secondary);
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            font-size: 0.9rem;
            border-left: 4px solid #6366f1;
            color: var(--text-primary);
            transition: transform 0.2s ease;
        }

        .time-slot:hover {
            transform: translateX(5px);
        }

        .time-slot.morning {
            border-left-color: #4cc9f0;
        }

        .time-slot.afternoon1 {
            border-left-color: #f8961e;
        }

        .time-slot.afternoon2 {
            border-left-color: #7209b7;
        }

        .time-slot small {
            display: block;
            margin-top: 5px;
            font-size: 0.8rem;
            color: var(--text-light);
        }

        /* ================= EMPTY STATE ================= */
        .empty-state {
            text-align: center;
            padding: 50px;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: var(--border-color);
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .empty-state p {
            color: var(--text-light);
            max-width: 400px;
            margin: 0 auto;
        }

        /* ================= COURSE INFO ================= */
        .course-info {
            background: rgba(99, 102, 241, 0.1);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 5px solid #6366f1;
            color: var(--text-primary);
        }

        .course-info h4 {
            color: var(--text-primary);
            margin-bottom: 15px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .course-list {
            list-style: none;
            max-height: 300px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .course-list::-webkit-scrollbar {
            width: 6px;
        }

        .course-list::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 3px;
        }

        .course-list::-webkit-scrollbar-thumb {
            background: #6366f1;
            border-radius: 3px;
        }

        .course-list li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(99, 102, 241, 0.2);
            color: var(--text-primary);
        }

        .course-list li:last-child {
            border-bottom: none;
        }

        .course-list li strong {
            color: var(--text-primary);
        }

        .course-list li span {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* ================= TIME SLOT INFO ================= */
        .time-slot-info {
            background: rgba(16, 185, 129, 0.1);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 1rem;
            border-left: 5px solid #10b981;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .time-slot-info h5 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .time-slot-info div {
            margin-bottom: 8px;
        }

        .time-slot-info small {
            color: var(--text-light);
            font-size: 0.9rem;
            display: block;
            margin-top: 5px;
        }

        /* ================= ROOM SELECTION STYLES ================= */
        .room-selection {
            background: rgba(245, 158, 11, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .room-selection h6 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .room-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .room-option {
            flex: 1;
            min-width: 200px;
        }

        .room-details {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 5px;
            display: flex;
            justify-content: space-between;
        }

        /* ================= TIP BOX ================= */
        .tip-box {
            margin-top: 15px;
            padding: 12px;
            background: #f0f9ff;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }

        .tip-box small {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1e40af;
        }

        /* ================= DEBUG INFO ================= */
        .debug-info {
            background: #f3f4f6;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* ================= RESPONSIVE ================= */
        @media(max-width: 768px){
            .topbar {
                display:flex;
            }
            
            .sidebar {
                transform:translateX(-100%);
            }
            
            .sidebar.active {
                transform:translateX(0);
            }
            
            .main-content {
                margin-left:0;
                padding: 20px;
                padding-top: 80px;
            }
            
            .stats {
                flex-direction: column;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .preview-grid {
                grid-template-columns: 1fr;
            }
            
            .user-info {
                width: 100%;
                justify-content: flex-start;
            }
            
            .room-options {
                flex-direction: column;
            }
            
            .room-option {
                min-width: 100%;
            }
        }

        @media (max-width: 1200px) {
            .preview-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) {
            .preview-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
                padding-top: 70px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .btn {
                padding: 12px 20px;
                font-size: 0.9rem;
            }
        }

        /* ================= CUSTOM SCROLLBAR ================= */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }

        ::-webkit-scrollbar-thumb {
            background: #6366f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #4f46e5;
        }
    </style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Schedule Management</h2>
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
            <a href="manage_schedules.php" class="active">
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
            <h1>Schedule Management</h1>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile">
                <div>
                    <div><?= htmlspecialchars($user['username'] ?? 'User') ?></div>
                    <small>Department Head</small>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats">
            <div class="stat-card">
                <i class="fas fa-book"></i>
                <h3><?php echo $total_courses_count; ?></h3>
                <p>Total Courses</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3><?php echo $active_instructors_count; ?></h3>
                <p>Active Instructors</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-door-open"></i>
                <h3><?php echo $available_rooms_count; ?></h3>
                <p>Available Rooms</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar-check"></i>
                <h3><?php echo $scheduled_classes_count; ?></h3>
                <p>Scheduled Classes</p>
            </div>
        </div>

        <!-- Messages -->
        <?php if($message): ?>
            <div class="message <?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?>"></i>
                <?= nl2br(htmlspecialchars($message)) ?>
            </div>
        <?php endif; ?>

        <!-- Time Slot Information -->
        <div class="time-slot-info">
            <h5><i class="fas fa-clock"></i> Scheduling Information</h5>
            <div><strong>Regular Students (Year 1-5):</strong> Monday to Friday | Time Slots: 2:30-4:20, 4:30-6:20, 8:00-11:00</div>
            <div><strong>Extension Students (Extension Year 1-5):</strong> Saturday & Sunday only | Time Slots: 2:30-4:00, 4:30-6:00, 8:00-11:00</div>
            <div><strong>Total Weekday Slots:</strong> 15 (5 days × 3 time slots)</div>
            <div><strong>Total Weekend Slots:</strong> 6 (2 days × 3 time slots)</div>
            <div><strong>Important:</strong> All classes will be scheduled in the SAME room</div>
        </div>

        <!-- Course Information -->
        <div class="course-info">
            <h4><i class="fas fa-info-circle"></i> Available Courses</h4>
            <ul class="course-list">
                <?php foreach($courses as $course): ?>
                    <li>
                        <strong><?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['course_code']) ?>)</strong>
                        <span style="color: var(--text-light);">- ID: <?= $course['course_id'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Auto Generate Schedule Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-magic"></i> Auto Generate Schedule</h3>
                <span class="badge">Smart Scheduling</span>
            </div>
            <div class="card-body">
                <form method="POST" id="scheduleForm">
                    <input type="hidden" name="auto_generate" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="auto_courses">Select Courses</label>
                            <select name="auto_courses[]" id="auto_courses" multiple class="form-control" required>
                                <?php foreach($courses as $c): ?>
                                    <option value="<?= $c['course_id'] ?>">
                                        <?= htmlspecialchars($c['course_name']) ?> 
                                        (<?= htmlspecialchars($c['course_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: var(--text-light);">Hold Ctrl/Cmd to select multiple courses. All selected courses will be scheduled.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="auto_year">Year / Extension</label>
                            <select name="auto_year" id="auto_year" class="form-control" required>
                                <option value="">Select Year / Extension</option>
                                <!-- Regular Years -->
                                <?php for($y=1;$y<=5;$y++): ?>
                                <option value="<?= $y ?>">Year <?= $y ?></option>
                                <?php endfor; ?>
                                
                                <!-- Extension Years -->
                                <?php for($e=1;$e<=5;$e++): ?>
                                <option value="E<?= $e ?>">Extension Year <?= $e ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="auto_academic_year">Academic Year</label>
                            <input type="text" name="auto_academic_year" id="auto_academic_year" class="form-control" placeholder="e.g., 2024-2025" required value="2024-2025">
                        </div>
                        
                        <div class="form-group">
                            <label for="auto_semester">Semester</label>
                            <select name="auto_semester" id="auto_semester" class="form-control" required>
                                <option value="">Select Semester</option>
                                <option value="1st Semester" selected>1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                                <option value="Summer">Summer</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Room Selection Section -->
                    <div class="room-selection">
                        <h6><i class="fas fa-door-open"></i> Room Selection (All classes will use the same room)</h6>
                        <div class="room-options">
                            <div class="room-option">
                                <select name="room_id" id="room_id" class="form-control">
                                    <option value="">Auto-select first available classroom</option>
                                    <?php foreach($all_rooms as $room): 
                                        $room_type = $room['room_type'];
                                        $type_display = ucfirst($room_type);
                                        $capacity = $room['capacity'] ? " - Capacity: {$room['capacity']}" : "";
                                    ?>
                                        <option value="<?= $room['room_id'] ?>">
                                            <?= htmlspecialchars($room['room_name']) ?> (<?= $type_display ?><?= $capacity ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="room-details">
                            <span><i class="fas fa-info-circle"></i> Leave empty to auto-select first classroom</span>
                            <span><i class="fas fa-check-circle"></i> All classes will be in the same room</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" id="generateBtn">
                                <i class="fas fa-bolt"></i> Generate Schedule
                            </button>
                            <button type="button" class="btn btn-warning" onclick="clearAllSchedules()" style="margin-left: 10px;">
                                <i class="fas fa-trash-alt"></i> Clear All Schedules First
                            </button>
                        </div>
                    </div>
                    
                    <div class="tip-box">
                        <small><i class="fas fa-lightbulb"></i> <strong>Tip:</strong> All selected courses will be scheduled in the SAME room across all time slots. This ensures consistency and avoids room conflicts.</small>
                    </div>
                </form>
            </div>
        </div>

        <!-- Schedule Preview -->
        <div class="card schedule-preview">
            <div class="card-header">
                <h3><i class="fas fa-eye"></i> Schedule Preview</h3>
                <span class="badge">Recent Schedules</span>
            </div>
            <div class="card-body">
                <?php if(!empty($recent_schedules)): ?>
                <div class="preview-grid">
                    <?php foreach($preview_schedules as $day => $day_schedules): ?>
                        <div class="preview-day">
                            <h4><?= $day ?></h4>
                            <?php if(!empty($day_schedules)): ?>
                                <?php foreach($day_schedules as $schedule): 
                                    // Determine time slot type for styling
                                    $start_time = $schedule['start_time'];
                                    $time_slot_class = '';
                                    if ($start_time == '02:30') $time_slot_class = 'morning';
                                    elseif ($start_time == '04:30') $time_slot_class = 'afternoon1';
                                    elseif ($start_time == '08:00') $time_slot_class = 'afternoon2';
                                    elseif ($start_time == '11:30') $time_slot_class = 'morning';
                                    elseif ($start_time == '15:00') $time_slot_class = 'afternoon1';
                                    
                                    // Determine year display
                                    $year_display = $schedule['year'] ?? '';
                                    if (is_numeric($year_display)) {
                                        $year_text = "Year $year_display";
                                    } elseif (substr($year_display, 0, 1) === 'E') {
                                        $extension_num = substr($year_display, 1);
                                        $year_text = "Ext. Year $extension_num";
                                    } else {
                                        $year_text = $year_display;
                                    }
                                ?>
                                    <div class="time-slot <?= $time_slot_class ?>">
                                        <strong><?= htmlspecialchars($schedule['course_name']) ?></strong><br>
                                        (<?= htmlspecialchars($schedule['course_code']) ?>)<br>
                                        <?= htmlspecialchars($schedule['instructor_name']) ?> | <?= htmlspecialchars($schedule['room_name']) ?><br>
                                        <?= $schedule['start_time'] ?> - <?= $schedule['end_time'] ?><br>
                                        <small><?= $year_text ?></small>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align:center; color:var(--text-light); font-style:italic; margin-top: 20px;">No classes</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-plus"></i>
                    <h3>No Schedules Yet</h3>
                    <p>Generate your first schedule to see the preview here.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Current Schedules -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Current Schedules</h3>
                <div>
                    <button type="button" class="btn btn-warning" onclick="clearAllSchedules()" style="margin-right: 10px;">
                        <i class="fas fa-trash-alt"></i> Clear All
                    </button>
                    <button type="submit" form="deleteForm" name="delete_selected" class="btn btn-danger" id="deleteBtn" disabled onclick="return confirm('Are you sure you want to delete selected schedules?')">
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if(!empty($grouped_schedules)): ?>
                <form method="POST" id="deleteForm">
                    <div class="table-container">
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th class="checkbox-cell">
                                        <input type="checkbox" id="select_all" onclick="toggleAll(this)">
                                    </th>
                                    <th>Course</th>
                                    <th>Instructor</th>
                                    <th>Room</th>
                                    <th>Academic Year</th>
                                    <th>Semester</th>
                                    <th>Year / Extension</th>
                                    <th>Schedule</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($grouped_schedules as $key=>$s): ?>
                                    <tr>
                                        <td class="checkbox-cell">
                                            <input type="checkbox" name="delete_ids[]" value="<?= $s['schedule_id'] ?>" class="delete-checkbox">
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($s['course_name']) ?></strong><br>
                                            <small style="color: var(--text-light);"><?= htmlspecialchars($s['course_code']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($s['instructor_name']) ?></td>
                                        <td><?= htmlspecialchars($s['room_name']) ?></td>
                                        <td><?= htmlspecialchars($s['academic_year']) ?></td>
                                        <td>
                                            <?php 
                                            $semester_display = $s['semester'];
                                            if ($semester_display == '1st Semester') {
                                                echo '<span class="semester-badge semester-1">1st Semester</span>';
                                            } elseif ($semester_display == '2nd Semester') {
                                                echo '<span class="semester-badge semester-2">2nd Semester</span>';
                                            } elseif ($semester_display == 'Summer') {
                                                echo '<span class="semester-badge semester-summer">Summer</span>';
                                            } else {
                                                echo htmlspecialchars($semester_display);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $year_display = $s['year'];
                                            if (is_numeric($year_display)) {
                                                echo '<span class="year-badge regular-year">Year ' . htmlspecialchars($year_display) . '</span>';
                                            } elseif (substr($year_display, 0, 1) === 'E') {
                                                $extension_num = substr($year_display, 1);
                                                echo '<span class="year-badge extension-year">Ext. ' . htmlspecialchars($extension_num) . '</span>';
                                            } else {
                                                echo htmlspecialchars($year_display);
                                            }
                                            ?>
                                        </td>
                                        <td><?= implode('<br>', $s['time_slots']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Schedules Found</h3>
                        <p>Generate a schedule using the form above to get started.</p>
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

        // Toggle all checkboxes
        function toggleAll(master) {
            const checkboxes = document.querySelectorAll('.delete-checkbox');
            checkboxes.forEach(cb => cb.checked = master.checked);
            updateDeleteButton();
        }

        // Update delete button state
        function updateDeleteButton() {
            const checked = document.querySelectorAll('.delete-checkbox:checked');
            const deleteBtn = document.getElementById('deleteBtn');
            if (deleteBtn) {
                deleteBtn.disabled = checked.length === 0;
                deleteBtn.innerHTML = checked.length > 0 ? 
                    `<i class="fas fa-trash"></i> Delete Selected (${checked.length})` : 
                    '<i class="fas fa-trash"></i> Delete Selected';
            }
        }

        // Clear all schedules
        function clearAllSchedules() {
            if(confirm('⚠️ WARNING: This will delete ALL schedules for your department!\n\nAre you absolutely sure?')) {
                // Create a form and submit it to clear all schedules
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.href;
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'clear_all_schedules';
                input.value = '1';
                form.appendChild(input);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Checkbox change handler
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('delete-checkbox')) {
                    e.target.closest('tr').classList.toggle('selected', e.target.checked);
                    updateDeleteButton();
                }
            });

            // Prevent form resubmission with proper form submission
            const generateBtn = document.getElementById('generateBtn');
            const scheduleForm = document.getElementById('scheduleForm');
            
            if(generateBtn && scheduleForm) {
                scheduleForm.addEventListener('submit', function(e) {
                    // Only show loading if form is valid
                    if(this.checkValidity()) {
                        generateBtn.disabled = true;
                        generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Schedule...';
                        
                        // Allow the form to submit normally
                        return true;
                    }
                });
            }

            // Initialize delete button state
            updateDeleteButton();

            // Set active state for current page
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.sidebar a');
            
            navLinks.forEach(link => {
                const linkPage = link.getAttribute('href');
                if (linkPage === currentPage) {
                    link.classList.add('active');
                }
            });

            // Auto-fill room selection with first classroom if available
            const roomSelect = document.getElementById('room_id');
            if (roomSelect) {
                // Find first classroom option
                for(let i = 0; i < roomSelect.options.length; i++) {
                    if(roomSelect.options[i].text.includes('(Classroom)')) {
                        roomSelect.value = roomSelect.options[i].value;
                        break;
                    }
                }
            }
        });
    </script>
    
    <!-- Optional: Add Font Awesome JS if not already loaded -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>