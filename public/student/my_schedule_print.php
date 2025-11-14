<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// Fetch schedule data for print view
$schedules = $pdo->prepare("
    SELECT s.schedule_id, c.course_name, c.course_code, u.full_name AS instructor_name, 
           r.room_name, s.day, s.start_time, s.end_time
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN users u ON s.instructor_id = u.user_id
    JOIN rooms r ON s.room_id = r.room_id
    JOIN enrollments e ON s.schedule_id = e.schedule_id
    WHERE e.student_id = ?
    ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), s.start_time
");
$schedules->execute([$student_id]);
$my_schedule = $schedules->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Schedule - <?= date('F j, Y') ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: white; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { color: #2c3e50; margin: 0; }
        .header .subtitle { color: #666; font-size: 16px; }
        .day-section { margin-bottom: 25px; page-break-inside: avoid; }
        .day-header { background: #34495e; color: white; padding: 8px 12px; font-weight: bold; margin-bottom: 10px; }
        .schedule-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .schedule-table th { background: #f8f9fa; text-align: left; padding: 10px; border: 1px solid #ddd; }
        .schedule-table td { padding: 10px; border: 1px solid #ddd; }
        .course-code { font-size: 12px; color: #666; }
        .no-classes { text-align: center; padding: 20px; color: #666; font-style: italic; }
        @media print {
            body { margin: 0; }
            .header { margin-bottom: 20px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>My Class Schedule</h1>
        <div class="subtitle">Generated on: <?= date('F j, Y g:i A') ?></div>
    </div>

    <?php
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    foreach($days as $day) {
        $day_schedules = array_filter($my_schedule, function($s) use ($day) {
            return $s['day'] == $day;
        });
        
        if(!empty($day_schedules)) {
            echo '<div class="day-section">';
            echo '<div class="day-header">' . $day . '</div>';
            echo '<table class="schedule-table">';
            echo '<tr>';
            echo '<th width="30%">Course</th>';
            echo '<th width="25%">Instructor</th>';
            echo '<th width="15%">Room</th>';
            echo '<th width="30%">Time</th>';
            echo '</tr>';
            
            foreach($day_schedules as $s) {
                echo '<tr>';
                echo '<td>';
                echo htmlspecialchars($s['course_name']);
                if(!empty($s['course_code'])) {
                    echo '<div class="course-code">' . htmlspecialchars($s['course_code']) . '</div>';
                }
                echo '</td>';
                echo '<td>' . htmlspecialchars($s['instructor_name']) . '</td>';
                echo '<td>' . htmlspecialchars($s['room_name']) . '</td>';
                echo '<td>' . date('g:i A', strtotime($s['start_time'])) . ' - ' . date('g:i A', strtotime($s['end_time'])) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo '</div>';
        }
    }
    
    if(empty($my_schedule)) {
        echo '<div class="no-classes">No classes scheduled</div>';
    }
    ?>

    <script>
        // Auto-print and close after delay
        window.onload = function() {
            window.print();
            setTimeout(function() {
                window.close();
            }, 1000);
        };
    </script>
</body>
</html>