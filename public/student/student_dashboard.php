<?php
session_start();

// Check if user is logged in and is a student
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

require __DIR__ . '/../../includes/db.php';

$student_id = $_SESSION['user_id'];

// Fetch student's info (username & profile picture)
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$student_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);


// Fetch current user info for profile picture
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id=?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch();

$profile_img_path = !empty($current_user['profile_picture']) && file_exists(__DIR__ . '/uploads/' . $current_user['profile_picture'])
    ? 'uploads/' . $current_user['profile_picture']
    : 'assets/default_profile.png';

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
    padding:20px;
    min-height:100vh;
    background: #f8fafc;
    transition: all 0.3s ease;
}

/* Content Wrapper */
.content-wrapper {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    min-height: calc(100vh - 40px);
}

/* Header Styles */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e5e7eb;
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
    background: #f8fafc;
    padding: 12px 18px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.user-info img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
}

/* Welcome Section */
.welcome-section {
    margin-bottom: 30px;
}

.welcome-section p {
    color: #6b7280;
    font-size: 1.1rem;
    margin-top: 10px;
}

/* ================= Stats Cards ================= */
.stats-cards {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.stat-card {
    flex: 1;
    min-width: 200px;
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
}

.stat-card h3 {
    font-size: 1rem;
    color: #6b7280;
    margin-bottom: 10px;
    font-weight: 600;
}

.stat-card .number {
    font-size: 2rem;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 10px;
}

.stat-card .icon {
    font-size: 2rem;
    margin-bottom: 15px;
    display: block;
}

/* ================= Schedule Table ================= */
.schedule-section {
    margin-top: 30px;
}

.table-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
}

.schedule-table {
    width: 100%;
    border-collapse: collapse;
}

.schedule-table th {
    background: #f8fafc;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
}

.schedule-table td {
    padding: 15px;
    border-bottom: 1px solid #f3f4f6;
}

.schedule-table tr:last-child td {
    border-bottom: none;
}

.schedule-table tr:hover {
    background: #f9fafb;
}

.schedule-table .today-row {
    background: #fff7ed !important;
    border-left: 4px solid #f59e0b;
}

/* Profile Section */
.profile-section {
    text-align: center;
    margin: 30px 0;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
}

.profile-picture {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #3b82f6;
    margin-bottom: 15px;
}

/* ================= Responsive ================= */
@media (max-width: 768px) {
    .topbar { display: flex; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .main-content { margin-left: 0; padding: 15px; }
    .content-wrapper { padding: 20px; border-radius: 0; }
    .header { flex-direction: column; gap: 15px; align-items: flex-start; }
    .header h1 { font-size: 1.8rem; }
    .stats-cards { flex-direction: column; }
    .stat-card { min-width: auto; }
    .table-container { overflow-x: auto; }
    .schedule-table { min-width: 600px; }
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Student Dashboard</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile Picture">
            <p><?= htmlspecialchars($user['username'] ?? 'Student') ?></p>
        </div>
        <a href="student_dashboard.php" class="<?= $current_page=='student_dashboard.php'?'active':'' ?>">Dashboard</a>
        <a href="my_schedule.php" class="<?= $current_page=='my_schedule.php'?'active':'' ?>">My Schedule</a>
        <a href="view_announcements.php" class="<?= $current_page=='view_announcements.php'?'active':'' ?>">Announcements</a>
        <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">Edit Profile</a>
        <a href="../logout.php">Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
                <div class="welcome-section">
                    <h1>Welcome, <?= htmlspecialchars($user['username']); ?> 👋</h1>
                    <p>Here is your personal dashboard. Use the sidebar to navigate.</p>
                </div>
                <div class="user-info">
                    <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile">
                    <div>
                        <div><?= htmlspecialchars($user['username'] ?? 'Student') ?></div>
                        <small>Student</small>
                    </div>
                </div>
            </div>

<!-- Profile Picture -->
<div class="profile-section">
    <?php 
    $profile_pic_path = __DIR__ . '/../uploads/' . ($user['profile_picture'] ?? '');
    if(!empty($user['profile_picture']) && file_exists($profile_pic_path)): 
    ?>
        <img src="../uploads/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture" class="profile-picture">
    <?php else: ?>
        <img src="../assets/default_profile.png" alt="Profile Picture" class="profile-picture">
    <?php endif; ?>
</div>
            <!-- Quick Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <i class="fas fa-book icon" style="color: #3b82f6;"></i>
                    <h3>Total Courses</h3>
                    <div class="number"><?= $total_courses ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-alt icon" style="color: #10b981;"></i>
                    <h3>Upcoming Classes Today</h3>
                    <div class="number"><?= $upcoming_classes ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock icon" style="color: #f59e0b;"></i>
                    <h3>Next Class</h3>
                    <?php if($next_class): ?>
                        <div class="number" style="font-size: 1.2rem;">
                            <?= htmlspecialchars($next_class['course_name']) ?>
                        </div>
                        <div style="color: #6b7280; font-size: 0.9rem;">
                            at <?= date('H:i', strtotime($next_class['start_time'])) ?>
                        </div>
                        <div id="countdown" style="color: #ef4444; font-size: 0.8rem; margin-top: 5px;"></div>
                        <script>
                            const startTime = "<?= date('H:i:s', strtotime($next_class['start_time'])) ?>";
                            let todayDate = new Date().toISOString().split('T')[0];
                            const classDateTime = new Date(todayDate + "T" + startTime);
                            function updateCountdown(){
                                const now = new Date();
                                const diff = classDateTime - now;
                                if(diff <= 0){
                                    document.getElementById('countdown').innerText = "Class is starting now!";
                                    clearInterval(timerInterval);
                                    return;
                                }
                                const hours = Math.floor(diff/(1000*60*60));
                                const minutes = Math.floor((diff % (1000*60*60))/(1000*60));
                                const seconds = Math.floor((diff % (1000*60))/1000);
                                document.getElementById('countdown').innerText = `Starts in: ${hours}h ${minutes}m ${seconds}s`;
                            }
                            updateCountdown();
                            const timerInterval = setInterval(updateCountdown,1000);
                        </script>
                    <?php else: ?>
                        <div class="number" style="font-size: 1rem; color: #6b7280;">No more classes today</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Schedule Table -->
            <div class="schedule-section">
                <h2 style="margin-bottom: 20px; color: #1f2937;">My Schedule</h2>
                <div class="table-container">
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
                                <td><?= date('H:i', strtotime($s['start_time'])) ?></td>
                                <td><?= date('H:i', strtotime($s['end_time'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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

    // Set active state for current page
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.sidebar a');
        
        navLinks.forEach(link => {
            const linkPage = link.getAttribute('href');
            if (linkPage === currentPage) {
                link.classList.add('active');
            }
        });
    });

    // Confirm logout
    document.querySelector('a[href="../logout.php"]').addEventListener('click', function(e) {
        if(!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });
    </script>
</body>
</html>