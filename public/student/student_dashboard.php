<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Enhanced Session Security
// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

// Check if user is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

// Validate user agent to prevent session hijacking
if(!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
} else {
    if($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        // User agent changed - possible session hijacking
        session_destroy();
        header("Location: ../index.php");
        exit;
    }
}

// Session expiration (30 minutes)
if(isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    // Session expired
    session_destroy();
    header("Location: ../index.php?expired=1");
    exit;
}
$_SESSION['last_activity'] = time();

require __DIR__ . '/../../includes/db.php';

$student_id = $_SESSION['user_id'];

// Fetch student's info (username & profile picture)
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$student_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch student's schedule
$schedules = $pdo->prepare("
    SELECT s.schedule_id, c.course_name, u.username AS instructor_name, r.room_name, 
           s.academic_year, s.semester, s.day, s.start_time, s.end_time
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN users u ON s.instructor_id = u.user_id
    JOIN rooms r ON s.room_id = r.room_id
    JOIN enrollments e ON s.schedule_id = e.schedule_id
    WHERE e.student_id = ?
    ORDER BY s.day, s.start_time
");
$schedules->execute([$student_id]);
$my_schedule = $schedules->fetchAll();

// Quick stats: total courses
$total_courses_stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT s.course_id) AS total_courses
    FROM schedule s
    JOIN enrollments e ON s.schedule_id = e.schedule_id
    WHERE e.student_id = ?
");
$total_courses_stmt->execute([$student_id]);
$total_courses = $total_courses_stmt->fetchColumn();

// Quick stats: upcoming classes today
$upcoming_classes_stmt = $pdo->prepare("
    SELECT COUNT(*) AS upcoming
    FROM schedule s
    JOIN enrollments e ON s.schedule_id = e.schedule_id
    WHERE e.student_id = ?
      AND s.day = DAYNAME(CURDATE())
      AND s.start_time >= CURTIME()
");
$upcoming_classes_stmt->execute([$student_id]);
$upcoming_classes = $upcoming_classes_stmt->fetchColumn();

// Quick stat: next class
$next_class_stmt = $pdo->prepare("
    SELECT c.course_name, s.start_time
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    JOIN enrollments e ON s.schedule_id = e.schedule_id
    WHERE e.student_id = ?
      AND s.day = DAYNAME(CURDATE())
      AND s.start_time >= CURTIME()
    ORDER BY s.start_time ASC
    LIMIT 1
");
$next_class_stmt->execute([$student_id]);
$next_class = $next_class_stmt->fetch(PDO::FETCH_ASSOC);

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Dashboard</title>
<style>
/* ================= General Reset ================= */
* {margin:0;padding:0;box-sizing:border-box;}
body {
    font-family: Arial, sans-serif;
    display: flex;
    min-height: 100vh;
    background: linear-gradient(-45deg, #1e3c72, #2a5298, #00c6ff, #0072ff);
    background-size: 400% 400%;
    animation: gradientBG 15s ease infinite;
}
@keyframes gradientBG {
    0% {background-position:0% 50%;}
    50% {background-position:100% 50%;}
    100% {background-position:0% 50%;}
}

/* ================= Sidebar ================= */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 240px;
    background-color: rgba(44, 62, 80, 0.95);
    padding-top: 20px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    box-shadow: 2px 0 10px rgba(0,0,0,0.2);
    z-index: 1000;
    overflow-y: auto;
}
.sidebar h2 {
    color: #ecf0f1;
    text-align: center;
    width: 100%;
    margin-bottom: 25px;
    font-size: 22px;
}
.sidebar a {
    padding: 12px 20px;
    text-decoration: none;
    font-size: 16px;
    color: #bdc3c7;
    width: 100%;
    transition: background 0.3s,color 0.3s;
    border-radius: 6px;
    margin: 3px 0;
}
.sidebar a.active, .sidebar a:hover {
    background-color: #34495e;
    color: #fff;
    font-weight: bold;
}

/* ================= Main Content ================= */
.main-content {
    margin-left: 240px;
    padding: 30px;
    flex-grow: 1;
    min-height: 100vh;
    background-color: rgba(243, 244, 246, 0.95);
    border-radius: 12px;
    margin-top: 20px;
    margin-bottom: 20px;
}

/* ================= Profile Picture ================= */
.profile-picture {
    text-align: center;
    margin-bottom: 25px;
}
.profile-picture img {
    border-radius: 50%;
    object-fit: cover;
    width: 130px;
    height: 130px;
    border: 3px solid #2563eb;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
}

/* ================= Quick Stats Cards ================= */
.stats-cards {
    display: flex;
    gap: 25px;
    flex-wrap: wrap;
    margin-bottom: 35px;
}
.stats-cards .card {
    flex: 1;
    min-width: 180px;
    background: linear-gradient(135deg,#6a11cb,#2575fc);
    color: #fff;
    padding: 25px;
    border-radius: 16px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    text-align: center;
    transition: transform 0.3s, box-shadow 0.3s;
    position: relative;
}
.stats-cards .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.25);
}
.stats-cards .card h3 {
    font-size: 17px;
    margin-bottom: 12px;
}
.stats-cards .card p {
    font-size: 24px;
    font-weight: bold;
}
.stats-cards .card::before {
    content: "📚";
    font-size: 28px;
    position: absolute;
    top: 15px;
    right: 15px;
}

/* ================= Schedule Table ================= */
.schedule-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    background-color: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}
.schedule-table th, .schedule-table td {
    border: none;
    padding: 14px;
    text-align: left;
}
.schedule-table th {
    background-color: #2575fc;
    color: #fff;
    font-weight: 600;
}
.schedule-table tr:nth-child(even) {background-color: #f7f9fc;}
.schedule-table tr:hover {background-color: #d0e7ff;}
.schedule-table .today-row {background-color: #ffeaa7 !important;font-weight:bold;}

/* ================= Security Notice ================= */
.security-notice {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.security-notice i {
    font-size: 16px;
}

/* ================= Session Timer ================= */
.session-timer {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
    padding: 8px 12px;
    border-radius: 5px;
    font-size: 12px;
    margin-top: 10px;
    display: inline-block;
}

/* ================= Responsive ================= */
@media screen and (max-width:768px){
    body{flex-direction:column;}
    .sidebar{width:100%;padding:15px;box-shadow:none;}
    .main-content{margin:0;padding:20px;border-radius:0;}
    .stats-cards{flex-direction:column;}
    .schedule-table th,.schedule-table td{padding:10px;font-size:12px;}
}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<!-- Sidebar -->
<div class="sidebar">
    <h2>Student Panel</h2>
    <a href="student_dashboard.php" class="<?= $current_page=='student_dashboard.php'?'active':'' ?>">Dashboard</a>
    <a href="my_schedule.php" class="<?= $current_page=='my_schedule.php'?'active':'' ?>">My Schedule</a>
    <a href="view_announcements.php" class="<?= $current_page=='view_announcements.php'?'active':'' ?>">Announcements</a>
    <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">Edit Profile</a>
    <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Security Notice -->
    <div class="security-notice">
        <i class="fas fa-shield-alt"></i>
        <div>
            <strong>Security Notice:</strong> This session will automatically expire after 30 minutes of inactivity. 
            For your security, please logout when finished.
        </div>
    </div>

    <!-- Session Timer -->
    <div class="session-timer" id="sessionTimer">
        <i class="fas fa-clock"></i> Session expires in: <span id="timer">30:00</span>
    </div>

    <!-- Profile Picture -->
    <div class="profile-picture">
        <?php if($user['profile_picture'] && file_exists(__DIR__.'/uploads/'.$user['profile_picture'])): ?>
            <img src="uploads/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture">
        <?php else: ?>
            <img src="https://via.placeholder.com/130" alt="Profile Picture">
        <?php endif; ?>
    </div>

    <h1>Welcome, <?= htmlspecialchars($user['username']); ?> 👋</h1>
    <p>Here is your personal dashboard. Use the sidebar to navigate.</p>

    <!-- Quick Stats Cards -->
    <div class="stats-cards">
        <div class="card">
            <h3>Total Courses</h3>
            <p><?= $total_courses ?></p>
        </div>
        <div class="card">
            <h3>Upcoming Classes Today</h3>
            <p><?= $upcoming_classes ?></p>
        </div>
        <div class="card">
            <h3>Next Class</h3>
            <?php if($next_class): ?>
                <p><?= htmlspecialchars($next_class['course_name']) ?> at <?= date('H:i', strtotime($next_class['start_time'])) ?></p>
                <p id="countdown"></p>
                <script>
                    const startTime = "<?= date('H:i:s', strtotime($next_class['start_time'])) ?>";
                    let todayDate = new Date().toISOString().split('T')[0];
                    const classDateTime = new Date(todayDate + "T" + startTime);
                    function updateCountdown(){
                        const now = new Date();
                        const diff = classDateTime - now;
                        if(diff <= 0){document.getElementById('countdown').innerText = "Class is starting now!";clearInterval(timerInterval);return;}
                        const hours=Math.floor(diff/(1000*60*60));
                        const minutes=Math.floor((diff % (1000*60*60))/(1000*60));
                        const seconds=Math.floor((diff % (1000*60))/1000);
                        document.getElementById('countdown').innerText = `Starts in: ${hours}h ${minutes}m ${seconds}s`;
                    }
                    updateCountdown();
                    const timerInterval = setInterval(updateCountdown,1000);
                </script>
            <?php else: ?>
                <p>No more classes today</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Schedule Table -->
    <h2>My Schedule</h2>
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
        $today = date('l'); // Current day name
        foreach($my_schedule as $s): 
            $todayClass = ($s['day'] === $today) ? 'today-row' : '';
        ?>
            <tr class="<?= $todayClass ?>">
                <td><?= htmlspecialchars($s['course_name']) ?></td>
                <td><?= htmlspecialchars($s['instructor_name']) ?></td>
                <td><?= htmlspecialchars($s['room_name']) ?></td>
                <td><?= htmlspecialchars($s['day']) ?></td>
                <td><?= htmlspecialchars($s['start_time']) ?></td>
                <td><?= htmlspecialchars($s['end_time']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
// Session timer countdown (30 minutes = 1800 seconds)
let sessionTime = 1800; // 30 minutes in seconds

function updateSessionTimer() {
    const minutes = Math.floor(sessionTime / 60);
    const seconds = sessionTime % 60;
    
    document.getElementById('timer').textContent = 
        `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    
    if (sessionTime <= 0) {
        // Session expired, redirect to logout
        window.location.href = '../logout.php?expired=1';
    } else {
        sessionTime--;
    }
}

// Update timer every second
setInterval(updateSessionTimer, 1000);

// Reset timer on user activity
function resetSessionTimer() {
    sessionTime = 1800; // Reset to 30 minutes
}

// Add event listeners for user activity
document.addEventListener('mousemove', resetSessionTimer);
document.addEventListener('keypress', resetSessionTimer);
document.addEventListener('click', resetSessionTimer);
document.addEventListener('scroll', resetSessionTimer);

// Initial timer update
updateSessionTimer();

// Confirm logout
document.querySelector('a[href="../logout.php"]').addEventListener('click', function(e) {
    if(!confirm('Are you sure you want to logout?')) {
        e.preventDefault();
    }
});
</script>
</body>
</html>