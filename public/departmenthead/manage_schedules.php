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
            day ENUM('Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            academic_year VARCHAR(10) NOT NULL,
            semester VARCHAR(20) NOT NULL,
            year INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
            FOREIGN KEY (instructor_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE
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

// ---------------- AUTO GENERATE SCHEDULE ----------------
if(isset($_POST['auto_generate'])){
    // Get form data
    $auto_courses = $_POST['auto_courses'] ?? [];
    $auto_year = (int)($_POST['auto_year'] ?? 0);
    $auto_semester = trim($_POST['auto_semester'] ?? '');
    $auto_academic_year = trim($_POST['auto_academic_year'] ?? '');

    // Validation
    if(empty($auto_courses) || $auto_year < 1 || $auto_year > 4 || empty($auto_semester) || empty($auto_academic_year)){
        setMessage($message, $message_type, "Please fill all fields correctly.", "error");
    } else {
        // Define available days and time slots
        $days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
        $time_slots = [
            ["02:30:00", "04:20:00"],   
            ["04:30:00", "06:20:00"],   
            ["08:00:00", "11:00:00"]    
        ];

        try {
            // Get the first available room (use same room for all courses)
            $rooms_stmt = $pdo->prepare("SELECT * FROM rooms ORDER BY room_id LIMIT 1");
            $rooms_stmt->execute();
            $room = $rooms_stmt->fetch(PDO::FETCH_ASSOC);

            if(!$room) {
                setMessage($message, $message_type, "No rooms found in the system.", "error");
            } else {
                $room_id = $room['room_id'];
                $room_name = $room['room_name'];

                // Get instructors from course_assignments table
                $placeholders = implode(',', array_fill(0, count($auto_courses), '?'));
                $instr_stmt = $pdo->prepare("
                    SELECT DISTINCT ca.course_id, ca.user_id as instructor_id, u.full_name, u.username
                    FROM course_assignments ca
                    JOIN users u ON ca.user_id = u.user_id
                    WHERE ca.course_id IN ($placeholders) AND u.role = 'instructor'
                ");
                $instr_stmt->execute($auto_courses);
                $course_instructors = fetchAllSafe($instr_stmt);

                // Map instructors by course
                $course_instructor_map = [];
                foreach($course_instructors as $ci){
                    $course_instructor_map[$ci['course_id']] = [
                        'id' => $ci['instructor_id'],
                        'name' => !empty($ci['full_name']) ? $ci['full_name'] : $ci['username']
                    ];
                }

                // Check which courses have instructors
                $courses_with_instructors = array_keys($course_instructor_map);
                $courses_without_instructors = array_diff($auto_courses, $courses_with_instructors);
                
                if(!empty($courses_without_instructors)) {
                    // Get course names for courses without instructors
                    $course_names = [];
                    $name_stmt = $pdo->prepare("SELECT course_id, course_name FROM courses WHERE course_id IN (" . 
                        implode(',', array_fill(0, count($courses_without_instructors), '?')) . ")");
                    $name_stmt->execute($courses_without_instructors);
                    $courses_data = fetchAllSafe($name_stmt);
                    
                    foreach($courses_data as $course) {
                        $course_names[] = $course['course_name'];
                    }
                    
                    setMessage($message, $message_type, 
                        "The following courses have no instructors assigned: " . implode(", ", $course_names) . 
                        ". Please assign instructors first.", "error");
                } else if(empty($course_instructor_map)){
                    setMessage($message, $message_type, "No instructors assigned to selected courses.", "error");
                } else {
                    $pdo->beginTransaction();
                    
                    $scheduled_count = 0;
                    $total_slots = count($days) * count($time_slots);
                    $total_courses = count($auto_courses);
                    
                    // Calculate how many sessions each course should get
                    $course_sessions = [];
                    
                    if($total_courses <= 5) {
                        // Few courses: distribute sessions more evenly
                        $base_sessions = floor($total_slots / $total_courses);
                        $remaining_sessions = $total_slots % $total_courses;
                        
                        foreach($auto_courses as $course_id) {
                            $course_sessions[$course_id] = $base_sessions;
                        }
                        
                        // Distribute remaining sessions to first few courses
                        $course_ids = array_values($auto_courses);
                        for($i = 0; $i < $remaining_sessions; $i++) {
                            $course_sessions[$course_ids[$i]]++;
                        }
                    } else {
                        // Many courses: try to give each course at least 1 session
                        $sessions_per_course = 1;
                        $remaining_slots = $total_slots - ($total_courses * $sessions_per_course);
                        
                        foreach($auto_courses as $course_id) {
                            $course_sessions[$course_id] = $sessions_per_course;
                        }
                        
                        // Distribute remaining slots
                        if($remaining_slots > 0) {
                            $course_ids = array_values($auto_courses);
                            for($i = 0; $i < $remaining_slots; $i++) {
                                $course_sessions[$course_ids[$i % $total_courses]]++;
                            }
                        }
                    }
                    
                    // Create a schedule plan
                    $schedule_plan = [];
                    
                    // Track scheduled courses per day (to prevent same course on same day)
                    $daily_courses = [];
                    foreach($days as $day) {
                        $daily_courses[$day] = [];
                    }
                    
                    // Track scheduled time slots
                    $scheduled_slots = [];
                    foreach($days as $day) {
                        $scheduled_slots[$day] = array_fill(0, count($time_slots), false);
                    }
                    
                    // Track remaining sessions needed per course
                    $remaining_sessions = $course_sessions;
                    
                    // Schedule sessions - prioritize giving each course at least one session first
                    $courses_to_schedule = $auto_courses;
                    $max_attempts = 100; // Prevent infinite loop
                    $attempts = 0;
                    
                    // First pass: Schedule each course at least once
                    while(!empty($courses_to_schedule) && $attempts < $max_attempts) {
                        $attempts++;
                        
                        foreach($courses_to_schedule as $index => $course_id) {
                            $scheduled_this_course = false;
                            
                            // Try to find an available slot for this course
                            foreach($days as $day) {
                                if($scheduled_this_course) break;
                                
                                // Skip if course already scheduled on this day
                                if(in_array($course_id, $daily_courses[$day])) {
                                    continue;
                                }
                                
                                foreach($time_slots as $slot_index => $time_slot) {
                                    if($scheduled_this_course) break;
                                    
                                    // Skip if time slot already taken
                                    if($scheduled_slots[$day][$slot_index]) {
                                        continue;
                                    }
                                    
                                    $instructor_id = $course_instructor_map[$course_id]['id'];
                                    $start_time = $time_slot[0];
                                    $end_time = $time_slot[1];
                                    
                                    try {
                                        $stmt = $pdo->prepare("
                                            INSERT INTO schedule 
                                            (course_id, instructor_id, room_id, day, start_time, end_time, academic_year, semester, year)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                        ");
                                        
                                        $result = $stmt->execute([
                                            $course_id, 
                                            $instructor_id, 
                                            $room_id,
                                            $day, 
                                            $start_time, 
                                            $end_time, 
                                            $auto_academic_year, 
                                            $auto_semester, 
                                            $auto_year
                                        ]);
                                        
                                        if($result) {
                                            // Mark slot as scheduled
                                            $scheduled_slots[$day][$slot_index] = true;
                                            
                                            // Track course on this day
                                            $daily_courses[$day][] = $course_id;
                                            
                                            // Update remaining sessions
                                            $remaining_sessions[$course_id]--;
                                            
                                            $scheduled_count++;
                                            $scheduled_this_course = true;
                                            
                                            // Add to schedule plan
                                            $schedule_plan[] = [
                                                'course_id' => $course_id,
                                                'day' => $day,
                                                'time' => substr($start_time, 0, 5) . ' - ' . substr($end_time, 0, 5),
                                                'room' => $room_name
                                            ];
                                            
                                            // Remove course from to-schedule list if it has all its sessions
                                            if($remaining_sessions[$course_id] <= 0) {
                                                unset($courses_to_schedule[$index]);
                                                // Reindex array
                                                $courses_to_schedule = array_values($courses_to_schedule);
                                            }
                                        }
                                    } catch(PDOException $e) {
                                        // If insertion fails, continue to next slot
                                        continue;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Second pass: Schedule remaining sessions for courses that need more
                    $attempts = 0;
                    $has_remaining_sessions = true;
                    
                    while($has_remaining_sessions && $attempts < $max_attempts) {
                        $attempts++;
                        $has_remaining_sessions = false;
                        
                        foreach($remaining_sessions as $course_id => $sessions_needed) {
                            if($sessions_needed <= 0) continue;
                            
                            $has_remaining_sessions = true;
                            $scheduled_this_course = false;
                            
                            // Try to find an available slot for this course
                            foreach($days as $day) {
                                if($scheduled_this_course) break;
                                
                                // Skip if course already scheduled on this day
                                if(in_array($course_id, $daily_courses[$day])) {
                                    continue;
                                }
                                
                                foreach($time_slots as $slot_index => $time_slot) {
                                    if($scheduled_this_course) break;
                                    
                                    // Skip if time slot already taken
                                    if($scheduled_slots[$day][$slot_index]) {
                                        continue;
                                    }
                                    
                                    $instructor_id = $course_instructor_map[$course_id]['id'];
                                    $start_time = $time_slot[0];
                                    $end_time = $time_slot[1];
                                    
                                    try {
                                        $stmt = $pdo->prepare("
                                            INSERT INTO schedule 
                                            (course_id, instructor_id, room_id, day, start_time, end_time, academic_year, semester, year)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                        ");
                                        
                                        $result = $stmt->execute([
                                            $course_id, 
                                            $instructor_id, 
                                            $room_id,
                                            $day, 
                                            $start_time, 
                                            $end_time, 
                                            $auto_academic_year, 
                                            $auto_semester, 
                                            $auto_year
                                        ]);
                                        
                                        if($result) {
                                            // Mark slot as scheduled
                                            $scheduled_slots[$day][$slot_index] = true;
                                            
                                            // Track course on this day
                                            $daily_courses[$day][] = $course_id;
                                            
                                            // Update remaining sessions
                                            $remaining_sessions[$course_id]--;
                                            
                                            $scheduled_count++;
                                            $scheduled_this_course = true;
                                            
                                            // Add to schedule plan
                                            $schedule_plan[] = [
                                                'course_id' => $course_id,
                                                'day' => $day,
                                                'time' => substr($start_time, 0, 5) . ' - ' . substr($end_time, 0, 5),
                                                'room' => $room_name
                                            ];
                                        }
                                    } catch(PDOException $e) {
                                        // If insertion fails, continue to next slot
                                        continue;
                                    }
                                }
                            }
                        }
                    }

                    $pdo->commit();

                    // Prepare success/error message with detailed information
                    if($scheduled_count > 0) {
                        // Get course names for display
                        $course_names = [];
                        $name_stmt = $pdo->prepare("SELECT course_id, course_name FROM courses WHERE course_id IN (" . 
                            implode(',', array_fill(0, count($auto_courses), '?')) . ")");
                        $name_stmt->execute($auto_courses);
                        $courses_data = fetchAllSafe($name_stmt);
                        
                        foreach($courses_data as $course) {
                            $course_names[$course['course_id']] = $course['course_name'];
                        }
                        
                        // Calculate actual sessions per course
                        $actual_sessions = [];
                        foreach($auto_courses as $course_id) {
                            $actual_sessions[$course_id] = 0;
                        }
                        
                        foreach($schedule_plan as $session) {
                            $actual_sessions[$session['course_id']]++;
                        }
                        
                        $schedule_info = "Successfully scheduled $scheduled_count sessions in $room_name!\n\n";
                        $schedule_info .= "Course Distribution:\n";
                        
                        $all_courses_scheduled = true;
                        foreach($auto_courses as $course_id) {
                            $course_name = $course_names[$course_id] ?? "Course ID $course_id";
                            $planned = $course_sessions[$course_id];
                            $actual = $actual_sessions[$course_id];
                            $status = ($actual >= $planned) ? "✓" : "⚠";
                            $schedule_info .= "$status $course_name: $actual session(s)\n";
                            
                            if($actual == 0) {
                                $all_courses_scheduled = false;
                            }
                        }
                        
                        $schedule_info .= "\nDaily Schedule:\n";
                        foreach($days as $day) {
                            $day_sessions = array_filter($schedule_plan, function($session) use ($day) {
                                return $session['day'] == $day;
                            });
                            
                            $schedule_info .= "$day: " . count($day_sessions) . " session(s)\n";
                            foreach($day_sessions as $session) {
                                $course_name = $course_names[$session['course_id']] ?? "Course ID {$session['course_id']}";
                                $schedule_info .= "  - {$session['time']}: $course_name\n";
                            }
                        }
                        
                        $unscheduled_sessions = array_sum($remaining_sessions);
                        if($unscheduled_sessions > 0) {
                            $schedule_info .= "\n⚠ Could not schedule $unscheduled_sessions session(s) due to conflicts.\n";
                        }
                        
                        if($all_courses_scheduled) {
                            $final_message = $schedule_info;
                            $final_message_type = "success";
                        } else {
                            $final_message = $schedule_info . "\nWarning: Some courses could not be scheduled at all.";
                            $final_message_type = "warning";
                        }
                    } else {
                        $final_message = "Could not schedule any sessions. All time slots might be occupied or there are constraint conflicts.";
                        $final_message_type = "error";
                    }

                    // Store message in session and redirect to prevent resubmission
                    $_SESSION['schedule_message'] = $final_message;
                    $_SESSION['schedule_message_type'] = $final_message_type;
                    
                    // Redirect to same page
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            }
        } catch(Exception $e) {
            if($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setMessage($message, $message_type, "Auto-scheduling failed: " . $e->getMessage(), "error");
        }
    }
}

// ---------------- FETCH DATA ----------------
// Fetch courses for the department
$courses_stmt = $pdo->prepare("SELECT * FROM courses WHERE department_id = ? ORDER BY course_name");
$courses_stmt->execute([$dept_id]);
$courses = fetchAllSafe($courses_stmt);

// Fetch current schedules
$schedules_stmt = $pdo->prepare("
    SELECT s.schedule_id, s.course_id, s.instructor_id, s.room_id, s.academic_year, s.semester, s.year,
           c.course_name, c.course_code, u.full_name AS instructor_name, r.room_name,
           s.day, TIME_FORMAT(s.start_time,'%H:%i') as start_time, TIME_FORMAT(s.end_time,'%H:%i') as end_time
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN users u ON s.instructor_id = u.user_id
    JOIN rooms r ON s.room_id = r.room_id
    WHERE c.department_id = ?
    ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), s.start_time
");
$schedules_stmt->execute([$dept_id]);
$all_schedules = fetchAllSafe($schedules_stmt);

// Group schedules for display
$grouped_schedules = [];
foreach($all_schedules as $schedule){
    $key = $schedule['course_id'].'_'.$schedule['instructor_id'].'_'.$schedule['room_id'].'_'.$schedule['academic_year'].'_'.$schedule['semester'].'_'.$schedule['year'];
    if(!isset($grouped_schedules[$key])){
        $grouped_schedules[$key] = [
            'schedule_id' => $schedule['schedule_id'],
            'course_name' => $schedule['course_name'],
            'course_code' => $schedule['course_code'],
            'instructor_name' => $schedule['instructor_name'],
            'room_name' => $schedule['room_name'],
            'academic_year' => $schedule['academic_year'],
            'semester' => $schedule['semester'],
            'year' => $schedule['year'],
            'time_slots' => []
        ];
    }
    $grouped_schedules[$key]['time_slots'][] = $schedule['day'].' '.$schedule['start_time'].' - '.$schedule['end_time'];
}

// Fetch stats for dashboard
$total_courses = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE department_id = ?");
$total_courses->execute([$dept_id]);
$total_courses_count = $total_courses->fetchColumn();

// Use course_assignments table
$active_instructors = $pdo->prepare("
    SELECT COUNT(DISTINCT u.user_id) 
    FROM users u 
    JOIN course_assignments ca ON u.user_id = ca.user_id 
    JOIN courses c ON ca.course_id = c.course_id 
    WHERE c.department_id = ? AND u.role = 'instructor'
");
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
           TIME_FORMAT(s.end_time,'%H:%i') as end_time, c.course_name, c.course_code,
           u.full_name as instructor_name, r.room_name
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN users u ON s.instructor_id = u.user_id
    JOIN rooms r ON s.room_id = r.room_id
    WHERE c.department_id = ?
    ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
             s.start_time
    LIMIT 30
");
$recent_schedules_stmt->execute([$dept_id]);
$recent_schedules = fetchAllSafe($recent_schedules_stmt);

// Group recent schedules by day for preview
$preview_schedules = [
    'Monday' => [],
    'Tuesday' => [],
    'Wednesday' => [],
    'Thursday' => [],
    'Friday' => []
];

foreach($recent_schedules as $schedule){
    $preview_schedules[$schedule['day']][] = $schedule;
}

// Use course_assignments table
$courses_with_instructors_stmt = $pdo->prepare("
    SELECT c.course_id, c.course_name, c.course_code, u.full_name 
    FROM courses c 
    LEFT JOIN course_assignments ca ON c.course_id = ca.course_id 
    LEFT JOIN users u ON ca.user_id = u.user_id 
    WHERE c.department_id = ? 
    ORDER BY c.course_name
");
$courses_with_instructors_stmt->execute([$dept_id]);
$courses_with_instructors = fetchAllSafe($courses_with_instructors_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management | Department Head Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin:0; padding:0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        /* ================= Topbar for Hamburger ================= */
        .topbar {
            display: none;
            position: fixed; top:0; left:0; width:100%;
            background:#2c3e50; color:#fff;
            padding:15px 20px;
            z-index:1200;
            justify-content:space-between; align-items:center;
        }
        .menu-btn {
            font-size:26px;
            background:#1abc9c;
            border:none; color:#fff;
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
            background:#1f2937; color:#fff;
            z-index:1100;
            transition: transform 0.3s ease;
            padding: 20px 0;
        }
        .sidebar.hidden { transform:translateX(-260px); }
        .sidebar a { 
            display:block; 
            padding:12px 20px; 
            color:#fff; 
            text-decoration:none; 
            transition: background 0.3s; 
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar a:hover, .sidebar a.active { background:#1abc9c; }

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
            color: #fff;
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
            background:#ffffff;
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
            color: #1f2937;
            font-weight: 700;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 12px 18px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .user-info img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Card Styles */
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
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

        .badge {
            background: #10b981;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .card-body {
            padding: 25px;
        }

        /* Stats */
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            flex: 1;
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .stat-card i {
            font-size: 2.5rem;
            color: #6366f1;
            margin-bottom: 15px;
        }

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 8px;
            color: #1f2937;
        }

        .stat-card p {
            color: #6b7280;
            font-weight: 500;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
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

        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
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
            white-space: pre-line;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .message.warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .schedule-table th,
        .schedule-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .schedule-table th {
            background: #f8fafc;
            color: #374151;
            font-weight: 600;
        }

        .schedule-table tr:last-child td {
            border-bottom: none;
        }

        .schedule-table tr:hover {
            background: #f9fafb;
        }

        .schedule-table tr.selected {
            background-color: #e0e7ff;
        }

        .checkbox-cell {
            width: 50px;
            text-align: center;
        }

        /* Schedule Preview */
        .schedule-preview {
            margin-top: 30px;
        }

        .preview-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .preview-day {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            min-height: 280px;
        }

        .preview-day h4 {
            text-align: center;
            margin-bottom: 15px;
            color: #6366f1;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
            font-weight: 600;
        }

        .time-slot {
            background: #f8fafc;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            font-size: 0.9rem;
            border-left: 4px solid #6366f1;
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

        .empty-state {
            text-align: center;
            padding: 50px;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: #d1d5db;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #374151;
        }

        .course-info {
            background: #e0e7ff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 5px solid #6366f1;
        }

        .course-info h4 {
            color: #374151;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .course-list {
            list-style: none;
        }

        .course-list li {
            padding: 8px 0;
            border-bottom: 1px solid #c7d2fe;
        }

        .course-list li:last-child {
            border-bottom: none;
        }

        .time-slot-info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 1rem;
            border-left: 5px solid #10b981;
        }

        .time-slot-info h5 {
            color: #374151;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }

        /* ================= Responsive ================= */
        @media(max-width: 768px){
            .topbar { display:flex; }
            .sidebar { transform:translateX(-100%); }
            .sidebar.active { transform:translateX(0); }
            .main-content { margin-left:0; padding: 20px; padding-top: 80px; }
            .stats { flex-direction: column; }
            .header { flex-direction: column; gap: 15px; align-items: flex-start; }
            .header h1 { font-size: 1.8rem; }
            .form-row { flex-direction: column; }
            .preview-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 1200px) {
            .preview-grid {
                grid-template-columns: repeat(3, 1fr);
            }
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

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile Picture">
            <p><?= htmlspecialchars($user['username'] ?? 'User') ?></p>
        </div>
        <a href="departmenthead_dashboard.php" class="<?= $current_page=='departmenthead_dashboard.php'?'active':'' ?>">Dashboard</a>
        <a href="manage_enrollments.php" class="<?= $current_page=='manage_enrollments.php'?'active':'' ?>">Manage Enrollments</a>
        <a href="manage_schedules.php" class="<?= $current_page=='manage_schedules.php'?'active':'' ?>">Manage Schedules</a>
        <a href="assign_courses.php" class="<?= $current_page=='assign_courses.php'?'active':'' ?>">Assign Courses</a>
        <a href="add_courses.php" class="<?= $current_page=='add_courses.php'?'active':'' ?>">Add Courses</a>
        <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">Edit Profile</a>
        <a href="manage_announcements.php" class="<?= $current_page=='manage_announcements.php'?'active':'' ?>">Announcements</a>
        <a href="../logout.php">Logout</a>
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
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Time Slot Information -->
        <div class="time-slot-info">
            <h5><i class="fas fa-clock"></i> Scheduling Information</h5>
            <div><strong>Room:</strong> Same room used for all courses</div>
            <div><strong>Days:</strong> Monday to Friday</div>
            <div><strong>Time Slots:</strong>  2:30-4:20, 4:30-6:20, 8:00-11:00</div>
            <div><strong>Total Slots:</strong> 15 (5 days × 3 time slots)</div>
            <div><strong>Guarantee:</strong> ALL selected courses will be included in the schedule</div>
            <div><small>Courses will be repeated to fill remaining slots after all courses are scheduled at least once.</small></div>
        </div>

        <!-- Course Information -->
        <div class="course-info">
            <h4><i class="fas fa-info-circle"></i> Available Courses with Current Instructors</h4>
            <ul class="course-list">
                <?php 
                // Get all courses for the department
                $all_courses_stmt = $pdo->prepare("
                    SELECT course_id, course_name, course_code 
                    FROM courses 
                    WHERE department_id = ? 
                    ORDER BY course_name
                ");
                $all_courses_stmt->execute([$dept_id]);
                $all_courses = fetchAllSafe($all_courses_stmt);
                
                foreach($all_courses as $course): 
                    // Improved query to check for course assignments
                    $instructor_stmt = $pdo->prepare("
                        SELECT u.user_id, u.full_name, u.username, ca.semester, ca.academic_year 
                        FROM course_assignments ca 
                        JOIN users u ON ca.user_id = u.user_id 
                        WHERE ca.course_id = ? AND u.role = 'instructor'
                        ORDER BY ca.assigned_date DESC 
                        LIMIT 1
                    ");
                    $instructor_stmt->execute([$course['course_id']]);
                    $instructor = $instructor_stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                    <li>
                        <strong><?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['course_code']) ?>)</strong>
                        <?php if($instructor && !empty($instructor['full_name'])): ?>
                            - Currently assigned to: <?= htmlspecialchars($instructor['full_name']) ?>
                            <?php if(!empty($instructor['semester']) && !empty($instructor['academic_year'])): ?>
                                (<?= htmlspecialchars($instructor['semester']) ?> <?= htmlspecialchars($instructor['academic_year']) ?>)
                            <?php endif; ?>
                        <?php elseif($instructor && !empty($instructor['username'])): ?>
                            - Currently assigned to: <?= htmlspecialchars($instructor['username']) ?>
                            <?php if(!empty($instructor['semester']) && !empty($instructor['academic_year'])): ?>
                                (<?= htmlspecialchars($instructor['semester']) ?> <?= htmlspecialchars($instructor['academic_year']) ?>)
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #ef4444;">- No instructor assigned</span>
                        <?php endif; ?>
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
                            <label for="auto_courses">Select Courses (ALL selected courses will be included)</label>
                            <select name="auto_courses[]" id="auto_courses" multiple class="form-control" required>
                                <?php foreach($courses as $c): 
                                    // Check course_assignments table
                                    $has_instructor = $pdo->prepare("SELECT COUNT(*) FROM course_assignments WHERE course_id = ?");
                                    $has_instructor->execute([$c['course_id']]);
                                    $instructor_count = $has_instructor->fetchColumn();
                                ?>
                                    <option value="<?= $c['course_id'] ?>" <?= $instructor_count > 0 ? '' : 'disabled' ?>>
                                        <?= htmlspecialchars($c['course_name']) ?> 
                                        (<?= htmlspecialchars($c['course_code']) ?>)
                                        <?= $instructor_count > 0 ? '✓' : '✗ No Instructor' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Hold Ctrl/Cmd to select multiple courses. ALL selected courses will be guaranteed in the schedule.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="auto_year">Year</label>
                            <select name="auto_year" id="auto_year" class="form-control" required>
                                <option value="">Select Year</option>
                               <?php for($y=1;$y<=4;$y++): ?>
                               <option value="<?= $y ?>" <?= (isset($_POST['auto_year']) && $_POST['auto_year'] == $y) ? 'selected' : '' ?>>Year <?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="auto_academic_year">Academic Year</label>
                            <input type="text" name="auto_academic_year" id="auto_academic_year" class="form-control" placeholder="e.g., 2024-2025" required value="<?= isset($_POST['auto_academic_year']) ? htmlspecialchars($_POST['auto_academic_year']) : '2024-2025' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="auto_semester">Semester</label>
                            <select name="auto_semester" id="auto_semester" class="form-control" required>
                                <option value="">Select Semester</option>
                                <option value="Fall" <?= (isset($_POST['auto_semester']) && $_POST['auto_semester'] == 'Fall') ? 'selected' : 'selected' ?>>Fall</option>
                                <option value="Spring" <?= (isset($_POST['auto_semester']) && $_POST['auto_semester'] == 'Spring') ? 'selected' : '' ?>>Spring</option>
                                <option value="Summer" <?= (isset($_POST['auto_semester']) && $_POST['auto_semester'] == 'Summer') ? 'selected' : '' ?>>Summer</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="generateBtn">
                        <i class="fas fa-bolt"></i> Generate Schedule
                    </button>
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
                                    elseif ($start_time == '04:30') $time_slot_class = 'morning2';
                                    elseif ($start_time == '08:00') $time_slot_class = 'afternoon';
                                ?>
                                    <div class="time-slot <?= $time_slot_class ?>">
                                        <strong><?= htmlspecialchars($schedule['course_name']) ?></strong><br>
                                        (<?= htmlspecialchars($schedule['course_code']) ?>)<br>
                                        <?= htmlspecialchars($schedule['instructor_name']) ?> | <?= htmlspecialchars($schedule['room_name']) ?><br>
                                        <?= $schedule['start_time'] ?> - <?= $schedule['end_time'] ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align:center; color:#6b7280; font-style:italic; margin-top: 20px;">No classes</p>
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
                                    <th>Year</th>
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
                                            <small><?= htmlspecialchars($s['course_code']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($s['instructor_name']) ?></td>
                                        <td><?= htmlspecialchars($s['room_name']) ?></td>
                                        <td><?= htmlspecialchars($s['academic_year']) ?></td>
                                        <td><?= htmlspecialchars($s['semester']) ?></td>
                                        <td><?= htmlspecialchars($s['year']) ?></td>
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
        });
    </script>
</body>
</html>