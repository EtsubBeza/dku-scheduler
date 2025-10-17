<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only allow admin or department head
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','department_head'])){
    header("Location: ../index.php");
    exit;
}

// Get all assigned courses
$assignments = $pdo->query("
    SELECT ca.course_id, ca.instructor_id, c.course_name
    FROM course_assignments ca
    JOIN courses c ON ca.course_id = c.course_id
")->fetchAll(PDO::FETCH_ASSOC);

// Define available time slots
$timeSlots = [
    ['08:00:00', '10:30:00'],
    ['10:30:00', '12:00:00'],
    ['02:00:00', '05:00:00']
];

// Available days
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Get all rooms
$rooms = $pdo->query("SELECT room_id FROM rooms")->fetchAll(PDO::FETCH_COLUMN);

$academic_year = '2025';
$semester = 'Fall';
$createdCount = 0;

foreach ($assignments as $assignment) {
    $assigned = false;
    shuffle($days);
    shuffle($timeSlots);
    shuffle($rooms);

    foreach ($days as $day) {
        foreach ($timeSlots as $slot) {
            $start = $slot[0];
            $end = $slot[1];
            $room_id = $rooms[array_rand($rooms)];

            // Check for conflicts
            $conflict = $pdo->prepare("
                SELECT COUNT(*) FROM schedule
                WHERE day_of_week = ? 
                AND (
                    (instructor_id = ? OR room_id = ?)
                    AND (
                        (start_time < ? AND end_time > ?) OR
                        (start_time < ? AND end_time > ?) OR
                        (start_time >= ? AND end_time <= ?)
                    )
                )
            ");
            $conflict->execute([$day, $assignment['instructor_id'], $room_id, $end, $start, $start, $end, $start, $end]);

            if ($conflict->fetchColumn() == 0) {
                // No conflict, insert schedule
                $insert = $pdo->prepare("
                    INSERT INTO schedule (course_id, instructor_id, room_id, academic_year, semester, day_of_week, start_time, end_time)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([
                    $assignment['course_id'],
                    $assignment['instructor_id'],
                    $room_id,
                    $academic_year,
                    $semester,
                    $day,
                    $start,
                    $end
                ]);
                $createdCount++;
                $assigned = true;
                break 2;
            }
        }
    }
}

echo "<h3>✅ Auto Schedule Generation Complete!</h3>";
echo "<p>$createdCount schedules generated successfully.</p>";
echo "<a href='manage_schedules.php'>Return to Manage Schedules</a>";
?>
