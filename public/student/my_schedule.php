<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// Fetch schedule
$schedules = $pdo->prepare("
    SELECT s.schedule_id, c.course_name, u.username AS instructor_name, r.room_name, 
           s.day_of_week, s.start_time, s.end_time
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN users u ON s.instructor_id = u.user_id
    JOIN rooms r ON s.room_id = r.room_id
    JOIN enrollments e ON s.schedule_id = e.schedule_id
    WHERE e.student_id = ?
    ORDER BY s.day_of_week, s.start_time
");
$schedules->execute([$student_id]);
$my_schedule = $schedules->fetchAll();

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Schedule</title>
<style>
/* ================= General Reset & Sidebar =============== */
* {margin:0;padding:0;box-sizing:border-box;}
body {font-family:Arial,sans-serif;display:flex;min-height:100vh;background-color:#f3f4f6;}
.sidebar {position:fixed;top:0;left:0;height:100vh;width:220px;background-color:#2c3e50;padding-top:20px;display:flex;flex-direction:column;align-items:flex-start;box-shadow:2px 0 5px rgba(0,0,0,0.1);z-index:1000;overflow-y:auto;}
.sidebar h2 {color:#ecf0f1;text-align:center;width:100%;margin-bottom:20px;font-size:20px;}
.sidebar a {padding:12px 20px;text-decoration:none;font-size:16px;color:#bdc3c7;width:100%;transition:background 0.3s,color 0.3s;}
.sidebar a.active {background-color:#34495e;color:#fff;font-weight:bold;}
.sidebar a:hover {background-color:#34495e;color:#fff;}

/* ================= Main Content ================= */
.main-content {margin-left:220px;padding:30px;flex-grow:1;min-height:100vh;background-color:#f3f4f6;}
.main-content h1, .main-content h2 {margin-bottom:20px;color:#111827;}

/* ================= Table ================= */
.schedule-table {width:100%;border-collapse:collapse;margin-top:20px;background-color:#fff;border-radius:8px;overflow:hidden;box-shadow:0 4px 8px rgba(0,0,0,0.05);}
.schedule-table th, .schedule-table td {border:1px solid #ccc;padding:10px;text-align:left;}
.schedule-table th {background-color:#34495e;color:#fff;}
.schedule-table tr:nth-child(even){background-color:#f2f2f2;}
.schedule-table tr:hover{background-color:#e0e0e0;}
.schedule-table .today-row{background-color:#dff9fb !important;font-weight:bold;}

/* ================= Responsive ================= */
@media screen and (max-width:768px){
    body{flex-direction:column;}
    .sidebar{width:100%;padding:15px;box-shadow:none;}
    .main-content{margin:0;padding:20px;}
    .schedule-table th,.schedule-table td{padding:8px;font-size:12px;}
}
</style>
</head>
<body>
<div class="sidebar">
    <h2>Student Panel</h2>
   <a href="student_dashboard.php">Dashboard</a>
    <a href="my_schedule.php">My Schedule</a>
    <a href="view_announcements.php" class="active">Announcements</a>
    <a href="edit_profile.php">Edit Profile</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <h1>My Schedule</h1>
    <table class="schedule-table">
        <thead>
            <tr>
                <th>Course</th>
                <th>Instructor</th>
                <th>Room</th>
                <th>Day</th>
                <th>Start</th>
                <th>End</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $today = date('l');
        foreach($my_schedule as $s): 
            $todayClass = ($s['day_of_week']==$today) ? 'today-row' : '';
        ?>
            <tr class="<?= $todayClass ?>">
                <td><?= htmlspecialchars($s['course_name']) ?></td>
                <td><?= htmlspecialchars($s['instructor_name']) ?></td>
                <td><?= htmlspecialchars($s['room_name']) ?></td>
                <td><?= htmlspecialchars($s['day_of_week']) ?></td>
                <td><?= htmlspecialchars($s['start_time']) ?></td>
                <td><?= htmlspecialchars($s['end_time']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
