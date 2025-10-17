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

/* ----------------- helpers ----------------- */
function fetchAllSafe($stmt) {
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $res ?: [];
}

function setMessage(&$message, &$message_type, $text, $type = "success") {
    $message = $text;
    $message_type = $type;
}

/* ----------------- DELETE SELECTED ----------------- */
if (isset($_POST['delete_selected'])) {
    $to_delete = $_POST['delete_ids'] ?? [];
    if (!empty($to_delete)) {
        $placeholders = implode(',', array_fill(0, count($to_delete), '?'));
        $del_stmt = $pdo->prepare("DELETE FROM schedule WHERE schedule_id IN ($placeholders)");
        if ($del_stmt->execute($to_delete)) {
            setMessage($message, $message_type, count($to_delete) . " schedule(s) deleted successfully.", "success");
        } else {
            setMessage($message, $message_type, "Failed to delete schedules.", "error");
        }
    } else {
        setMessage($message, $message_type, "No schedules selected for deletion.", "error");
    }
}

/* ----------------- AUTO GENERATOR ----------------- */
if (isset($_POST['auto_generate'])) {
    $auto_courses = $_POST['auto_courses'] ?? [];
    $auto_year = (int)($_POST['auto_year'] ?? 0);
    $auto_semester = trim($_POST['auto_semester'] ?? '');
    $auto_academic_year = trim($_POST['auto_academic_year'] ?? '');

    // Basic validation
    if (empty($auto_courses) || $auto_year < 1 || $auto_year > 4 || empty($auto_semester) || empty($auto_academic_year)) {
        setMessage($message, $message_type, "Please fill all fields: select courses, year (1-4), semester, and academic year.", "error");
    } else {
        // Prevent duplicate generation
        $exists_stmt = $pdo->prepare("
            SELECT 1 FROM schedule s
            JOIN courses c ON s.course_id = c.course_id
            WHERE c.department_id = ? AND s.academic_year = ? AND s.semester = ? AND s.year = ? 
            LIMIT 1
        ");
        $exists_stmt->execute([$dept_id, $auto_academic_year, $auto_semester, $auto_year]);
        
        if ($exists_stmt->fetch()) {
            setMessage($message, $message_type, "Schedule already exists for $auto_academic_year, $auto_semester, Year $auto_year.", "error");
        } else {
            // Setup days and time slots
            $days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
            $time_slots = [
                ["08:00:00", "11:00:00"],
                ["14:30:00", "16:30:00"],
                ["16:30:00", "18:00:00"]
            ];

            // Get available resources
            $rooms = fetchAllSafe($pdo->query("SELECT * FROM rooms ORDER BY room_name"));
            $instr_stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'instructor' AND department_id = ? ORDER BY username");
            $instr_stmt->execute([$dept_id]);
            $instructors = fetchAllSafe($instr_stmt);

            if (empty($rooms) || empty($instructors)) {
                setMessage($message, $message_type, "No rooms or instructors available in your department.", "error");
            } else {
                try {
                    $pdo->beginTransaction();
                    $scheduled_count = 0;
                    $unscheduled = [];

                    // Create all possible time slots
                    $all_slots = [];
                    foreach ($days as $day) {
                        foreach ($time_slots as $slot) {
                            $all_slots[] = [
                                'day' => $day,
                                'start' => $slot[0],
                                'end' => $slot[1]
                            ];
                        }
                    }

                    // Track assignments to avoid conflicts
                    $assigned_slots = [];
                    $course_days = []; // Track which courses are assigned to which days

                    foreach ($auto_courses as $course_id) {
                        $course_id = (int)$course_id;
                        $assigned = false;

                        // Try to assign this course to a slot
                        foreach ($all_slots as $slot_index => $slot) {
                            if ($assigned) break;

                            $day = $slot['day'];
                            $start_time = $slot['start'];
                            $end_time = $slot['end'];

                            // Skip if this course already scheduled on this day
                            if (isset($course_days[$course_id][$day])) {
                                continue;
                            }

                            // Try each instructor
                            foreach ($instructors as $instructor) {
                                if ($assigned) break;

                                $instructor_id = $instructor['user_id'];

                                // Try each room
                                foreach ($rooms as $room) {
                                    if ($assigned) break;

                                    $room_id = $room['room_id'];

                                    // Check if this slot is already occupied for this instructor or room
                                    $slot_key = $day . '_' . $start_time;
                                    $instructor_busy = false;
                                    $room_busy = false;

                                    if (isset($assigned_slots[$slot_key])) {
                                        if (in_array($instructor_id, $assigned_slots[$slot_key]['instructors'])) {
                                            $instructor_busy = true;
                                        }
                                        if (in_array($room_id, $assigned_slots[$slot_key]['rooms'])) {
                                            $room_busy = true;
                                        }
                                    }

                                    if (!$instructor_busy && !$room_busy) {
                                        // Double-check with database
                                        $check_stmt = $pdo->prepare("
                                            SELECT 1 FROM schedule 
                                            WHERE day_of_week = ? 
                                            AND academic_year = ? 
                                            AND semester = ? 
                                            AND year = ?
                                            AND (instructor_id = ? OR room_id = ?)
                                            AND NOT (end_time <= ? OR start_time >= ?)
                                            LIMIT 1
                                        ");
                                        $check_stmt->execute([
                                            $day, $auto_academic_year, $auto_semester, $auto_year,
                                            $instructor_id, $room_id, $start_time, $end_time
                                        ]);

                                        if (!$check_stmt->fetch()) {
                                            // Schedule this course
                                            $ins_stmt = $pdo->prepare("
                                                INSERT INTO schedule 
                                                (course_id, instructor_id, room_id, day_of_week, start_time, end_time, academic_year, semester, year, created_at)
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                                            ");
                                            $ins_stmt->execute([
                                                $course_id, $instructor_id, $room_id, $day, 
                                                $start_time, $end_time, $auto_academic_year, $auto_semester, $auto_year
                                            ]);

                                            // Track the assignment
                                            if (!isset($assigned_slots[$slot_key])) {
                                                $assigned_slots[$slot_key] = ['instructors' => [], 'rooms' => []];
                                            }
                                            $assigned_slots[$slot_key]['instructors'][] = $instructor_id;
                                            $assigned_slots[$slot_key]['rooms'][] = $room_id;
                                            
                                            $course_days[$course_id][$day] = true;
                                            $scheduled_count++;
                                            $assigned = true;
                                            break;
                                        }
                                    }
                                }
                            }
                        }

                        if (!$assigned) {
                            $unscheduled[] = $course_id;
                        }
                    }

                    $pdo->commit();

                    // Report results
                    if (!empty($unscheduled)) {
                        $course_names = [];
                        $name_stmt = $pdo->prepare("SELECT course_name FROM courses WHERE course_id = ?");
                        foreach ($unscheduled as $course_id) {
                            $name_stmt->execute([$course_id]);
                            $name = $name_stmt->fetchColumn();
                            $course_names[] = $name ?: "Course ID $course_id";
                        }
                        setMessage($message, $message_type, 
                            "Scheduled $scheduled_count courses. Could not schedule: " . implode(", ", $course_names), 
                            "error");
                    } else {
                        setMessage($message, $message_type, 
                            "Successfully scheduled all " . count($auto_courses) . " courses!", 
                            "success");
                    }

                } catch (Exception $e) {
                    $pdo->rollBack();
                    setMessage($message, $message_type, "Auto-scheduling failed: " . $e->getMessage(), "error");
                }
            }
        }
    }
}

/* ------------------ FETCH DATA ------------------ */
// Get courses for this department
$courses_stmt = $pdo->prepare("SELECT * FROM courses WHERE department_id = ? ORDER BY course_name");
$courses_stmt->execute([$dept_id]);
$courses = fetchAllSafe($courses_stmt);

// Get existing schedules with better grouping
$schedules_stmt = $pdo->prepare("
    SELECT 
        s.schedule_id,
        s.course_id,
        s.instructor_id,
        s.room_id,
        s.academic_year,
        s.semester,
        s.year,
        c.course_name,
        u.username AS instructor_name,
        r.room_name,
        s.day_of_week,
        TIME_FORMAT(s.start_time, '%H:%i') as start_time,
        TIME_FORMAT(s.end_time, '%H:%i') as end_time
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN users u ON s.instructor_id = u.user_id
    JOIN rooms r ON s.room_id = r.room_id
    WHERE c.department_id = ?
    ORDER BY s.academic_year DESC, s.semester, s.year, c.course_name, s.day_of_week, s.start_time
");
$schedules_stmt->execute([$dept_id]);
$all_schedules = fetchAllSafe($schedules_stmt);

// Group schedules for display
$grouped_schedules = [];
foreach ($all_schedules as $schedule) {
    $key = $schedule['course_id'] . '_' . $schedule['instructor_id'] . '_' . $schedule['room_id'] . '_' . $schedule['academic_year'] . '_' . $schedule['semester'] . '_' . $schedule['year'];
    
    if (!isset($grouped_schedules[$key])) {
        $grouped_schedules[$key] = [
            'schedule_id' => $schedule['schedule_id'],
            'course_name' => $schedule['course_name'],
            'instructor_name' => $schedule['instructor_name'],
            'room_name' => $schedule['room_name'],
            'academic_year' => $schedule['academic_year'],
            'semester' => $schedule['semester'],
            'year' => $schedule['year'],
            'time_slots' => []
        ];
    }
    
    $grouped_schedules[$key]['time_slots'][] = $schedule['day_of_week'] . ' ' . $schedule['start_time'] . ' - ' . $schedule['end_time'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Manage Schedules</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .main-content { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .schedule-form { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #dee2e6; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-control { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; }
        select[multiple].form-control { height: 150px; }
        .message { padding: 12px; border-radius: 4px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .schedule-table { width: 100%; border-collapse: collapse; background: white; }
        .schedule-table th, .schedule-table td { padding: 12px; border: 1px solid #dee2e6; text-align: left; }
        .schedule-table th { background: #e9ecef; font-weight: bold; }
        .schedule-table tr.selected { background-color: #e3f2fd; }
        .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:disabled { background: #6c757d; cursor: not-allowed; }
        .loading { display: none; text-align: center; padding: 20px; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <h1>Manage Department Schedules</h1>

    <?php if(!empty($message)): ?>
        <div class="message <?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div id="loading" class="loading">
        <p>Generating schedule... Please wait.</p>
    </div>

    <form method="POST" class="schedule-form" id="scheduleForm">
        <h3>Auto Generate Schedule</h3>
        
        <div class="form-group">
            <label>Select Courses (Hold Ctrl/Cmd to select multiple):</label>
            <select name="auto_courses[]" multiple class="form-control" required>
                <?php foreach($courses as $c): ?>
                    <option value="<?= $c['course_id'] ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Year:</label>
            <select name="auto_year" class="form-control" required>
                <option value="">Select Year</option>
                <?php for($y=1; $y<=4; $y++): ?>
                    <option value="<?= $y ?>">Year <?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Academic Year:</label>
            <input type="text" name="auto_academic_year" class="form-control" placeholder="e.g., 2025-2026" required>
        </div>

        <div class="form-group">
            <label>Semester:</label>
            <select name="auto_semester" class="form-control" required>
                <option value="">Select Semester</option>
                <option value="Fall">Fall</option>
                <option value="Spring">Spring</option>
                <option value="Summer">Summer</option>
            </select>
        </div>

        <button type="submit" name="auto_generate" class="btn btn-primary" id="generateBtn">Generate Schedule</button>
    </form>

    <h2>Current Schedules</h2>
    
    <form method="POST" id="deleteForm">
        <?php if(!empty($grouped_schedules)): ?>
            <div style="margin-bottom: 15px;">
                <button type="submit" name="delete_selected" class="btn btn-danger" id="deleteBtn" disabled>
                    Delete Selected
                </button>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select_all" onclick="toggleAll(this)"></th>
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
                        <?php foreach($grouped_schedules as $key => $schedule): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="delete_ids[]" value="<?= $schedule['schedule_id'] ?>" class="delete-checkbox">
                                </td>
                                <td><?= htmlspecialchars($schedule['course_name']) ?></td>
                                <td><?= htmlspecialchars($schedule['instructor_name']) ?></td>
                                <td><?= htmlspecialchars($schedule['room_name']) ?></td>
                                <td><?= htmlspecialchars($schedule['academic_year']) ?></td>
                                <td><?= htmlspecialchars($schedule['semester']) ?></td>
                                <td><?= htmlspecialchars($schedule['year']) ?></td>
                                <td>
                                    <?= implode('<br>', $schedule['time_slots']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No schedules found.</p>
        <?php endif; ?>
    </form>
</div>

<script>
function toggleAll(master) {
    const checkboxes = document.querySelectorAll('.delete-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = master.checked;
        cb.closest('tr').classList.toggle('selected', master.checked);
    });
    updateDeleteButton();
}

function updateDeleteButton() {
    const checked = document.querySelectorAll('.delete-checkbox:checked');
    const deleteBtn = document.getElementById('deleteBtn');
    if (deleteBtn) {
        deleteBtn.disabled = checked.length === 0;
        deleteBtn.textContent = checked.length > 0 ? 
            `Delete Selected (${checked.length})` : 'Delete Selected';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Update delete button when checkboxes change
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('delete-checkbox')) {
            e.target.closest('tr').classList.toggle('selected', e.target.checked);
            updateDeleteButton();
        }
    });
    
    // Show loading when generating schedule
    const scheduleForm = document.getElementById('scheduleForm');
    if (scheduleForm) {
        scheduleForm.addEventListener('submit', function() {
            const generateBtn = document.getElementById('generateBtn');
            const loading = document.getElementById('loading');
            if (generateBtn) generateBtn.disabled = true;
            if (loading) loading.style.display = 'block';
        });
    }
});
</script>
</body>
</html>