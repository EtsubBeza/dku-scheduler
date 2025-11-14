<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// Handle PDF Export (Simple HTML to PDF using browser print)
if(isset($_GET['export']) && $_GET['export'] == 'pdf') {
    // We'll use a print-friendly page that users can "Save as PDF"
    header("Location: my_schedule_print.php");
    exit;
}

// Handle Excel/CSV Export
if(isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Fetch schedule data
    $schedules = $pdo->prepare("
        SELECT c.course_name, c.course_code, u.full_name AS instructor_name, 
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

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="my_schedule_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    // Add BOM for UTF-8 to help Excel with special characters
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Course Name', 'Course Code', 'Instructor', 'Room', 'Day', 'Start Time', 'End Time']);
    
    foreach($my_schedule as $s) {
        fputcsv($output, [
            $s['course_name'],
            $s['course_code'],
            $s['instructor_name'],
            $s['room_name'],
            $s['day'],
            date('g:i A', strtotime($s['start_time'])),
            date('g:i A', strtotime($s['end_time']))
        ]);
    }
    fclose($output);
    exit;
}

// Normal page load - fetch schedule for display
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

/* ================= Export Buttons ================= */
.export-buttons {margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;}
.export-btn {padding:10px 15px;background-color:#34495e;color:white;border:none;border-radius:5px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px;font-size:14px;transition:background-color 0.3s;}
.export-btn:hover {background-color:#2c3e50;}
.export-btn.pdf {background-color:#e74c3c;}
.export-btn.pdf:hover {background-color:#c0392b;}
.export-btn.excel {background-color:#27ae60;}
.export-btn.excel:hover {background-color:#219a52;}
.export-btn.print {background-color:#3498db;}
.export-btn.print:hover {background-color:#2980b9;}

/* ================= Table ================= */
.schedule-table {width:100%;border-collapse:collapse;margin-top:20px;background-color:#fff;border-radius:8px;overflow:hidden;box-shadow:0 4px 8px rgba(0,0,0,0.05);}
.schedule-table th, .schedule-table td {border:1px solid #ccc;padding:12px;text-align:left;}
.schedule-table th {background-color:#34495e;color:#fff;}
.schedule-table tr:nth-child(even){background-color:#f2f2f2;}
.schedule-table tr:hover{background-color:#e0e0e0;}
.schedule-table .today-row{background-color:#dff9fb !important;font-weight:bold;}
.course-code {font-size:12px;color:#666;margin-top:4px;}

/* ================= Print Styles ================= */
@media print {
    .sidebar, .export-buttons {display:none !important;}
    .main-content {margin:0 !important;padding:0 !important;}
    body {background:white !important;}
    .schedule-table {box-shadow:none !important;border:1px solid #000 !important;}
    .schedule-table th {background-color:#ccc !important;color:#000 !important;-webkit-print-color-adjust:exact;}
}

/* ================= Responsive ================= */
@media screen and (max-width:768px){
    body{flex-direction:column;}
    .sidebar{width:100%;padding:15px;box-shadow:none;}
    .main-content{margin:0;padding:20px;}
    .schedule-table th,.schedule-table td{padding:8px;font-size:12px;}
    .export-buttons{flex-direction:column;}
}
</style>
</head>
<body>
<div class="sidebar">
    <h2>Student Panel</h2>
    <a href="student_dashboard.php">Dashboard</a>
    <a href="my_schedule.php" class="active">My Schedule</a>
    <a href="view_announcements.php">Announcements</a>
    <a href="edit_profile.php">Edit Profile</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <h1>My Schedule</h1>
    
    <!-- Export Buttons -->
    <div class="export-buttons">
        <a href="?export=pdf" class="export-btn pdf" target="_blank">
            <i class="fas fa-file-pdf"></i> Export PDF
        </a>
        <a href="?export=excel" class="export-btn excel">
            <i class="fas fa-file-excel"></i> Export Excel/CSV
        </a>
        <button onclick="window.print()" class="export-btn print">
            <i class="fas fa-print"></i> Print Schedule
        </button>
    </div>

    <table class="schedule-table">
        <thead>
            <tr>
                <th>Course</th>
                <th>Instructor</th>
                <th>Room</th>
                <th>Day</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $today = date('l');
        foreach($my_schedule as $s): 
            $todayClass = ($s['day']==$today) ? 'today-row' : '';
        ?>
            <tr class="<?= $todayClass ?>">
                <td>
                    <?= htmlspecialchars($s['course_name']) ?>
                    <?php if(!empty($s['course_code'])): ?>
                        <div class="course-code"><?= htmlspecialchars($s['course_code']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($s['instructor_name']) ?></td>
                <td><?= htmlspecialchars($s['room_name']) ?></td>
                <td><?= htmlspecialchars($s['day']) ?></td>
                <td><?= date('g:i A', strtotime($s['start_time'])) . ' - ' . date('g:i A', strtotime($s['end_time'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if(empty($my_schedule)): ?>
            <tr>
                <td colspan="5" style="text-align:center;padding:20px;">No classes scheduled</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Font Awesome for icons -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>