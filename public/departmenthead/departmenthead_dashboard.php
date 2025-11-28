<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only allow department head
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_head'){
    header("Location: ../index.php");
    exit;
}

$dept_id = $_SESSION['department_id'] ?? 0;

// Fetch current user info for profile picture
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id=?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch();
$profile_img_path = !empty($current_user['profile_picture']) && file_exists(__DIR__.'/../../uploads/profiles/'.$current_user['profile_picture'])
    ? '../../uploads/profiles/'.$current_user['profile_picture']
    : '../assets/default_profile.png';

// Quick stats
$total_students_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='student' AND department_id = ?");
$total_students_stmt->execute([$dept_id]);
$total_students = $total_students_stmt->fetchColumn();

$total_courses_stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE department_id = ?");
$total_courses_stmt->execute([$dept_id]);
$total_courses = $total_courses_stmt->fetchColumn();

$total_enrollments_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM enrollments e
    JOIN schedule s ON e.schedule_id = s.schedule_id
    JOIN courses c ON s.course_id = c.course_id
    WHERE c.department_id = ?
");
$total_enrollments_stmt->execute([$dept_id]);
$total_enrollments = $total_enrollments_stmt->fetchColumn();

// Fetch current enrollments with multiple days aggregated
$enrollments_stmt = $pdo->prepare("
    SELECT u.username AS student_name, 
           c.course_name, 
           GROUP_CONCAT(DISTINCT s.day ORDER BY FIELD(s.day, 'Monday','Tuesday','Wednesday','Thursday','Friday') SEPARATOR ', ') AS days,
           MIN(s.start_time) AS start_time,
           MAX(s.end_time) AS end_time
    FROM enrollments e
    JOIN users u ON e.student_id = u.user_id
    JOIN schedule s ON e.schedule_id = s.schedule_id
    JOIN courses c ON s.course_id = c.course_id
    WHERE c.department_id = ?
    GROUP BY u.username, c.course_name
    ORDER BY u.username, FIELD(s.day, 'Monday','Tuesday','Wednesday','Thursday','Friday')
");
$enrollments_stmt->execute([$dept_id]);
$enrollments = $enrollments_stmt->fetchAll();

// ---------------- Handle Announcement Form ----------------
$announcement_msg = "";
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement_title'], $_POST['announcement_message'])){
    $title = trim($_POST['announcement_title']);
    $message = trim($_POST['announcement_message']);
    $created_by = $_SESSION['user_id'];

    if($title && $message){
        $stmt = $pdo->prepare("INSERT INTO announcements (title, message, created_by, department_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $message, $created_by, $dept_id]);
        $announcement_msg = "Announcement posted successfully!";
    } else {
        $announcement_msg = "Please fill in both title and message.";
    }
}

// Fetch recent announcements for this department
$announcements_stmt = $pdo->prepare("SELECT * FROM announcements WHERE department_id = ? OR department_id IS NULL ORDER BY created_at DESC LIMIT 5");
$announcements_stmt->execute([$dept_id]);
$announcements = $announcements_stmt->fetchAll();

// Get current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Department Head Dashboard</title>
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

/* Welcome section */
.welcome-section {
    background: linear-gradient(135deg,#6366f1,#3b82f6);
    color:white;
    padding:30px 25px;
    border-radius:15px;
    margin-bottom:30px;
    box-shadow:0 6px 18px rgba(0,0,0,0.1);
}
.welcome-section h1 { font-size:28px; font-weight:600; margin-bottom:8px; }
.welcome-section p { font-size:16px; opacity:0.9; }

/* Stats Cards */
.stats-cards { 
    display:flex; 
    flex-wrap:wrap; 
    gap:20px; 
    margin-bottom:30px; 
}
.stats-cards .card {
    flex:1 1 220px;
    background:#f3f4f6;
    border-radius:15px;
    padding:25px 20px;
    box-shadow:0 6px 20px rgba(0,0,0,0.08);
    display:flex; 
    flex-direction:column; 
    justify-content:space-between;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.stats-cards .card:hover { 
    transform:translateY(-6px); 
    box-shadow:0 10px 28px rgba(0,0,0,0.15); 
}
.card-icon { 
    font-size:40px; 
    margin-bottom:15px; 
    padding:15px; 
    width:60px; 
    height:60px; 
    display:flex; 
    align-items:center; 
    justify-content:center; 
    border-radius:50%; 
    background:#e0e7ff; 
    color:#4f46e5; 
}
.stats-cards .card h3 { 
    font-size:18px; 
    margin-bottom:8px; 
    color:#111827; 
    font-weight:600; 
}
.stats-cards .card p { 
    font-size:24px; 
    font-weight:bold; 
    color:#1f2937; 
    margin-bottom:15px; 
}

/* Table Section */
.table-section, .announcement-section { 
    background-color: white; 
    padding: 25px; 
    border-radius: 15px; 
    box-shadow: 0 6px 18px rgba(0,0,0,0.1); 
    margin-bottom: 30px; 
}

.table-section h2, .announcement-section h2 { 
    margin-bottom: 20px; 
    color: #1f2937; 
    font-size: 1.5rem;
    font-weight: 600;
}

.enrollments-table, .announcements-table { 
    width: 100%; 
    border-collapse: collapse; 
}

.enrollments-table th, .enrollments-table td,
.announcements-table th, .announcements-table td {
    padding: 15px; 
    text-align: left; 
    border-bottom: 1px solid #e5e7eb;
}

.enrollments-table th, .announcements-table th { 
    background-color: #f8fafc; 
    color: #374151; 
    font-weight: 600; 
}

.enrollments-table tr:hover, .announcements-table tr:hover { 
    background-color: #f9fafb; 
}

/* Announcement Form */
.announcement-form { 
    margin-bottom: 25px; 
}

.announcement-form input, .announcement-form textarea { 
    width: 100%; 
    padding: 14px 16px;
    margin-bottom: 16px; 
    border-radius: 10px; 
    border: 1px solid #d1d5db; 
    font-size: 1rem;
    transition: all 0.3s ease;
}

.announcement-form input:focus, .announcement-form textarea:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.announcement-form button { 
    padding: 14px 24px; 
    background-color: #6366f1; 
    color: white; 
    border: none; 
    border-radius: 10px; 
    cursor: pointer; 
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.announcement-form button:hover { 
    background-color: #4f46e5; 
    transform: translateY(-2px);
}

/* Button Styles */
.btn {
    padding: 12px 20px;
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

/* Message Styles */
.message {
    padding: 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
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

/* Card Header Styles */
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

.card-body {
    padding: 25px;
}

/* ================= Responsive ================= */
@media(max-width: 768px){
    .topbar { display:flex; }
    .sidebar { transform:translateX(-100%); }
    .sidebar.active { transform:translateX(0); }
    .main-content { margin-left:0; padding: 20px; padding-top: 80px; }
    .stats-cards { flex-direction: column; }
    .header { flex-direction: column; gap: 15px; align-items: flex-start; }
    .header h1 { font-size: 1.8rem; }
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Department Head</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-profile">
            <img src="<?= $profile_img_path ?>" alt="Profile Picture">
            <p><?= htmlspecialchars($current_user['username']); ?></p>
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
            <h1>Department Head Dashboard</h1>
            <div class="user-info">
                <img src="<?= $profile_img_path ?>" alt="Profile">
                <div>
                    <div><?= htmlspecialchars($current_user['username']) ?></div>
                    <small>Department Head</small>
                </div>
            </div>
        </div>

        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Welcome, <?= htmlspecialchars($current_user['username']); ?> 👋</h1>
            <p>Here is your department dashboard. Use the sidebar to manage students, courses, enrollments, and announcements.</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Total Students</h3>
                <p><?= $total_students ?></p>
            </div>
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-book"></i>
                </div>
                <h3>Total Courses</h3>
                <p><?= $total_courses ?></p>
            </div>
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Total Enrollments</h3>
                <p><?= $total_enrollments ?></p>
            </div>
        </div>

        <!-- Enrollments Table -->
        <div class="table-section">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Current Enrollments</h3>
            </div>
            <div class="card-body">
                <table class="enrollments-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Days</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($enrollments): ?>
                            <?php foreach($enrollments as $e): ?>
                                <tr>
                                    <td><?= htmlspecialchars($e['student_name']) ?></td>
                                    <td><?= htmlspecialchars($e['course_name']) ?></td>
                                    <td><?= htmlspecialchars($e['days']) ?></td>
                                    <td><?= date('h:i A', strtotime($e['start_time'])) ?></td>
                                    <td><?= date('h:i A', strtotime($e['end_time'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 30px;">
                                    <i class="fas fa-inbox" style="font-size: 2.5rem; color: #9ca3af; margin-bottom: 15px;"></i>
                                    <p style="color: #6b7280; font-size: 1.1rem;">No enrollments found.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Announcements Section -->
        <div class="announcement-section">
            <div class="card-header">
                <h3><i class="fas fa-bullhorn"></i> Announcements</h3>
            </div>
            <div class="card-body">
                <h4>Post New Announcement</h4>
                <form method="post" class="announcement-form">
                    <input type="text" name="announcement_title" placeholder="Announcement Title" required>
                    <textarea name="announcement_message" placeholder="Announcement Message" rows="4" required></textarea>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Post Announcement
                    </button>
                </form>
                
                <?php if($announcement_msg): ?>
                    <div class="message <?= strpos($announcement_msg, 'successfully') !== false ? 'success' : 'error' ?>">
                        <i class="fas fa-<?= strpos($announcement_msg, 'successfully') !== false ? 'check-circle' : 'exclamation-circle' ?>"></i>
                        <?= $announcement_msg ?>
                    </div>
                <?php endif; ?>

                <h4 style="margin-top: 30px;">Recent Announcements</h4>
                <table class="announcements-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Posted At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($announcements): ?>
                            <?php foreach($announcements as $a): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($a['title']) ?></strong></td>
                                    <td><?= nl2br(htmlspecialchars($a['message'])) ?></td>
                                    <td><?= date('M d, Y h:i A', strtotime($a['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align:center; padding: 30px;">
                                    <i class="fas fa-bullhorn" style="font-size: 2.5rem; color: #9ca3af; margin-bottom: 15px;"></i>
                                    <p style="color: #6b7280; font-size: 1.1rem;">No announcements yet.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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

        // Simple script for sidebar active state
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
    </script>
</body>
</html>