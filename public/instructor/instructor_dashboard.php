<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor'){
    header("Location: ../index.php");
    exit;
}

$instructor_id = $_SESSION['user_id'];

// Fetch instructor info
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$instructor_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch courses taught by instructor
$courses_stmt = $pdo->prepare("
    SELECT DISTINCT c.course_id, c.course_name
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    WHERE s.instructor_id = ?
");
$courses_stmt->execute([$instructor_id]);
$courses = $courses_stmt->fetchAll();
$total_courses = count($courses);

// Fetch complete weekly schedule
$weekly_schedule_stmt = $pdo->prepare("
    SELECT s.schedule_id, c.course_name, c.course_code, r.room_name, 
           s.day, s.start_time, s.end_time,
           (SELECT COUNT(*) FROM enrollments e WHERE e.schedule_id = s.schedule_id) AS student_count
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN rooms r ON s.room_id = r.room_id
    WHERE s.instructor_id = ?
    ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), s.start_time
");
$weekly_schedule_stmt->execute([$instructor_id]);
$weekly_schedule = $weekly_schedule_stmt->fetchAll();

// Group schedule by day for better display
$schedule_by_day = [
    'Monday' => [],
    'Tuesday' => [],
    'Wednesday' => [],
    'Thursday' => [],
    'Friday' => []
];

foreach($weekly_schedule as $class) {
    $schedule_by_day[$class['day']][] = $class;
}

// Calculate total students across all classes
$total_students = array_sum(array_column($weekly_schedule, 'student_count'));

// Next class (from current time)
$today = date('l');
$current_time = date('H:i:s');
$next_class_stmt = $pdo->prepare("
    SELECT c.course_name, s.day, s.start_time, s.end_time, r.room_name
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN rooms r ON s.room_id = r.room_id
    WHERE s.instructor_id = ?
      AND (
        (s.day = ? AND s.start_time >= ?) OR 
        (s.day > ?)
      )
    ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), s.start_time
    LIMIT 1
");
$next_class_stmt->execute([$instructor_id, $today, $current_time, $today]);
$next_class = $next_class_stmt->fetch(PDO::FETCH_ASSOC);

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Instructor Dashboard</title>
<style>
/* ========== General Reset ========== */
* {margin:0;padding:0;box-sizing:border-box;}
body {
    font-family: Arial, sans-serif;
    display:flex;
    min-height:100vh;
    background: linear-gradient(-45deg, #0f2027, #203a43, #2c5364);
    background-size: 400% 400%;
    animation: gradientBG 15s ease infinite;
}
@keyframes gradientBG {
    0% {background-position:0% 50%;}
    50% {background-position:100% 50%;}
    100% {background-position:0% 50%;}
}

/* ========== Sidebar ========== */
.sidebar {
    position: fixed;
    top:0; left:0;
    height:100vh;
    width:240px;
    background-color: rgba(44,62,80,0.95);
    padding-top:20px;
    display:flex;
    flex-direction:column;
    align-items:flex-start;
    box-shadow:2px 0 10px rgba(0,0,0,0.2);
    z-index:1000;
    overflow-y:auto;
}
.sidebar h2 {
    color:#ecf0f1;
    text-align:center;
    width:100%;
    margin-bottom:25px;
    font-size:22px;
}
.sidebar a {
    padding:12px 20px;
    text-decoration:none;
    font-size:16px;
    color:#bdc3c7;
    width:100%;
    transition:background 0.3s,color 0.3s;
    border-radius:6px;
    margin:3px 0;
}
.sidebar a.active, .sidebar a:hover {
    background-color:#34495e;
    color:#fff;
    font-weight:bold;
}

/* ========== Main Content ========== */
.main-content {
    margin-left:240px;
    padding:30px;
    flex-grow:1;
    min-height:100vh;
    background-color: rgba(243,244,246,0.95);
    border-radius:12px;
    margin-top:20px;
    margin-bottom:20px;
}

/* ========== Profile Picture ========== */
.profile-picture {
    text-align:center;
    margin-bottom:25px;
}
.profile-picture img {
    border-radius:50%;
    object-fit:cover;
    width:130px;
    height:130px;
    border:3px solid #2563eb;
    box-shadow:0 4px 12px rgba(0,0,0,0.25);
}

/* ========== Stats Cards ========== */
.stats-cards {
    display:flex;
    gap:25px;
    flex-wrap:wrap;
    margin-bottom:35px;
}
.stats-cards .card {
    flex:1;
    min-width:180px;
    background: linear-gradient(135deg,#6a11cb,#2575fc);
    color:#fff;
    padding:25px;
    border-radius:16px;
    box-shadow:0 8px 20px rgba(0,0,0,0.15);
    text-align:center;
    transition: transform 0.3s, box-shadow 0.3s;
    position:relative;
}
.stats-cards .card:hover {
    transform: translateY(-5px);
    box-shadow:0 12px 25px rgba(0,0,0,0.25);
}
.stats-cards .card h3 {
    font-size:17px;
    margin-bottom:12px;
}
.stats-cards .card p {
    font-size:24px;
    font-weight:bold;
}
.stats-cards .card::before {
    content:"📘";
    font-size:28px;
    position:absolute;
    top:15px;
    right:15px;
}

/* ========== Weekly Schedule Grid ========== */
.weekly-schedule {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-top: 20px;
}
.day-column {
    background: white;
    border-radius: 12px;
    padding: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.day-header {
    background: #2575fc;
    color: white;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 15px;
    font-weight: bold;
}
.class-slot {
    background: #f8f9fa;
    border-left: 4px solid #6a11cb;
    padding: 12px;
    margin-bottom: 10px;
    border-radius: 6px;
    transition: transform 0.2s;
}
.class-slot:hover {
    transform: translateX(5px);
    background: #e3f2fd;
}
.class-slot.next-class {
    background: #fff3cd;
    border-left-color: #ffc107;
    font-weight: bold;
}
.course-name {
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 5px;
}
.class-details {
    font-size: 12px;
    color: #666;
    line-height: 1.4;
}
.class-time {
    font-weight: bold;
    color: #2575fc;
}
.student-count {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 11px;
    margin-top: 5px;
    display: inline-block;
}

/* ========== No Classes Message ========== */
.no-classes {
    text-align: center;
    padding: 20px;
    color: #666;
    font-style: italic;
    background: #f8f9fa;
    border-radius: 8px;
    margin: 10px 0;
}

/* ========== Responsive ========== */
@media screen and (max-width:1200px){
    .weekly-schedule {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media screen and (max-width:768px){
    body{flex-direction:column;}
    .sidebar{width:100%;padding:15px;box-shadow:none;}
    .main-content{margin:0;padding:20px;border-radius:0;}
    .stats-cards{flex-direction:column;}
    .weekly-schedule{grid-template-columns: 1fr;}
}
</style>
</head>
<body>
<div class="sidebar">
    <h2>Instructor Panel</h2>
    <a href="instructor_dashboard.php" class="<?= $current_page=='instructor_dashboard.php'?'active':'' ?>">Dashboard</a>
    <a href="my_courses.php" class="<?= $current_page=='my_courses.php'?'active':'' ?>">My Courses</a>
    <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">Edit Profile</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="profile-picture">
        <?php if($user['profile_picture'] && file_exists(__DIR__.'/uploads/'.$user['profile_picture'])): ?>
            <img src="uploads/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture">
        <?php else: ?>
            <img src="https://via.placeholder.com/130" alt="Profile Picture">
        <?php endif; ?>
    </div>

    <h1>Welcome, <?= htmlspecialchars($user['username']); ?> 👋</h1>
    <p>Your weekly teaching schedule overview</p>

    <div class="stats-cards">
        <div class="card">
            <h3>Total Courses</h3>
            <p><?= $total_courses ?></p>
        </div>
        <div class="card">
            <h3>Weekly Classes</h3>
            <p><?= count($weekly_schedule) ?></p>
        </div>
        <div class="card">
            <h3>Total Students</h3>
            <p><?= $total_students ?></p>
        </div>
        <div class="card">
            <h3>Next Class</h3>
            <?php if($next_class): ?>
                <p><?= htmlspecialchars($next_class['course_name']) ?></p>
                <p style="font-size:14px;margin-top:5px;">
                    <?= $next_class['day'] ?> at <?= date('g:i A', strtotime($next_class['start_time'])) ?>
                </p>
            <?php else: ?>
                <p>No upcoming classes</p>
            <?php endif; ?>
        </div>
    </div>

    <h2>Weekly Schedule</h2>
    <div class="weekly-schedule">
        <?php 
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        foreach($days as $day): 
            $day_classes = $schedule_by_day[$day];
        ?>
            <div class="day-column">
                <div class="day-header"><?= $day ?></div>
                <?php if(!empty($day_classes)): ?>
                    <?php foreach($day_classes as $class): 
                        $is_next_class = ($next_class && 
                                         $next_class['course_name'] == $class['course_name'] && 
                                         $next_class['day'] == $class['day'] && 
                                         $next_class['start_time'] == $class['start_time']);
                    ?>
                        <div class="class-slot <?= $is_next_class ? 'next-class' : '' ?>">
                            <div class="course-name"><?= htmlspecialchars($class['course_name']) ?></div>
                            <div class="class-details">
                                <div class="class-time">
                                    <?= date('g:i A', strtotime($class['start_time'])) ?> - <?= date('g:i A', strtotime($class['end_time'])) ?>
                                </div>
                                <div>Room: <?= htmlspecialchars($class['room_name']) ?></div>
                                <?php if(!empty($class['course_code'])): ?>
                                    <div>Code: <?= htmlspecialchars($class['course_code']) ?></div>
                                <?php endif; ?>
                                <div class="student-count"><?= $class['student_count'] ?> students</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-classes">No classes scheduled</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>