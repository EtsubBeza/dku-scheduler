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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Department Head Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3a56d4;
    --secondary: #7209b7;
    --success: #4cc9f0;
    --danger: #f72585;
    --warning: #f8961e;
    --light: #f8f9fa;
    --dark: #212529;
    --gray: #6c757d;
    --gray-light: #e9ecef;
    --border-radius: 8px;
    --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --transition: all 0.3s ease;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body {
    background-color: #f5f7fb;
    color: var(--dark);
    line-height: 1.6;
}

.container {
    display: flex;
    min-height: 100vh;
}

/* Sidebar Styles */
.sidebar {
    width: 250px;
    background: linear-gradient(180deg, var(--primary), var(--secondary));
    color: white;
    padding: 20px 0;
    box-shadow: var(--box-shadow);
    z-index: 100;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
}

.logo {
    text-align: center;
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 20px;
}

.logo h2 {
    font-size: 1.5rem;
    font-weight: 600;
}

.logo p {
    font-size: 0.8rem;
    opacity: 0.8;
}

.nav-links {
    list-style: none;
}

.nav-links li {
    padding: 12px 20px;
    transition: var(--transition);
}

.nav-links li:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.nav-links li.active {
    background-color: rgba(255, 255, 255, 0.2);
    border-left: 4px solid white;
}

.nav-links a {
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
}

.nav-links i {
    font-size: 1.2rem;
}

/* Main Content Styles */
.main-content {
    flex: 1;
    margin-left: 250px;
    padding: 30px;
    overflow-y: auto;
    background-color: #f9fafb;
    min-height: 100vh;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.header h1 {
    font-size: 2rem;
    color: var(--primary);
    font-weight: 700;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    background: white;
    padding: 10px 15px;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
}

.user-info img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

/* Card Styles */
.card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 25px;
    overflow: hidden;
}

.card-header {
    padding: 20px;
    background: var(--primary);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    font-size: 1.3rem;
    font-weight: 600;
}

.badge {
    background: var(--success);
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.card-body {
    padding: 20px;
}

/* Welcome Section */
.welcome-section {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 30px 20px;
    border-radius: var(--border-radius);
    margin-bottom: 30px;
    box-shadow: var(--box-shadow);
}

.welcome-section h1 { 
    font-size: 28px; 
    margin-bottom: 10px; 
}

.welcome-section p { 
    font-size: 16px; 
    opacity: 0.9; 
}

/* Stats Cards */
.stats-cards { 
    display: flex; 
    gap: 20px; 
    flex-wrap: wrap; 
    margin-bottom: 30px; 
}

.stats-cards .card {
    flex: 1 1 200px;
    background-color: white;
    border-radius: var(--border-radius);
    padding: 20px;
    text-align: center;
    box-shadow: var(--box-shadow);
    transition: var(--transition);
    border: none;
}

.stats-cards .card:hover { 
    transform: translateY(-5px); 
    box-shadow: 0 8px 20px rgba(0,0,0,0.1); 
}

.stats-cards .card h3 { 
    font-size: 18px; 
    margin-bottom: 10px; 
    color: var(--dark);
}

.stats-cards .card p { 
    font-size: 24px; 
    font-weight: bold; 
    color: var(--primary);
}

/* Table Section */
.table-section, .announcement-section { 
    background-color: white; 
    padding: 20px; 
    border-radius: var(--border-radius); 
    box-shadow: var(--box-shadow); 
    margin-bottom: 30px; 
}

.table-section h2, .announcement-section h2 { 
    margin-bottom: 20px; 
    color: var(--dark); 
}

.enrollments-table, .announcements-table { 
    width: 100%; 
    border-collapse: collapse; 
}

.enrollments-table th, .enrollments-table td,
.announcements-table th, .announcements-table td {
    padding: 12px 15px; 
    text-align: left; 
    border-bottom: 1px solid var(--gray-light);
}

.enrollments-table th, .announcements-table th { 
    background-color: var(--gray-light); 
    color: var(--dark); 
    font-weight: 600; 
}

.enrollments-table tr:hover, .announcements-table tr:hover { 
    background-color: #f9fafb; 
    transition: background-color 0.2s; 
}

/* Announcement Form */
.announcement-form { 
    margin-bottom: 20px; 
}

.announcement-form input, .announcement-form textarea { 
    width: 100%; 
    padding: 12px 15px;
    margin-bottom: 15px; 
    border-radius: var(--border-radius); 
    border: 1px solid var(--gray-light); 
    font-size: 1rem;
    transition: var(--transition);
}

.announcement-form input:focus, .announcement-form textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
}

.announcement-form button { 
    padding: 12px 20px; 
    background-color: var(--primary); 
    color: white; 
    border: none; 
    border-radius: var(--border-radius); 
    cursor: pointer; 
    font-weight: 600;
    transition: var(--transition);
}

.announcement-form button:hover { 
    background-color: var(--primary-dark); 
}

/* Button Styles */
.btn {
    padding: 12px 20px;
    border: none;
    border-radius: var(--border-radius);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #e1156f;
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #3ab3d6;
}

/* Message Styles */
.message {
    padding: 15px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.message.warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

/* Responsive */
@media (max-width: 768px) { 
    .container {
        flex-direction: column;
    }
    
    .sidebar {
        width: 100%;
        position: relative;
        height: auto;
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .stats-cards { 
        flex-direction: column; 
    }
    
    .form-row {
        flex-direction: column;
    }
}
</style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <h2><i class="fas fa-university"></i> DKU Scheduler</h2>
                <p>Department Head Portal</p>
            </div>
            <ul class="nav-links">
                <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_students.php"><i class="fas fa-users"></i> Manage Students</a></li>
                <li><a href="manage_courses.php"><i class="fas fa-book"></i> Manage Courses</a></li>
                <li><a href="manage_schedules.php"><i class="fas fa-calendar-plus"></i> Manage Schedules</a></li>
                <li><a href="assign_courses.php"><i class="fas fa-tasks"></i> Assign Courses</a></li>
                <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
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
                    <h3><i class="fas fa-users"></i> Total Students</h3>
                    <p><?= $total_students ?></p>
                </div>
                <div class="card">
                    <h3><i class="fas fa-book"></i> Total Courses</h3>
                    <p><?= $total_courses ?></p>
                </div>
                <div class="card">
                    <h3><i class="fas fa-calendar-check"></i> Total Enrollments</h3>
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
                                    <td colspan="5" style="text-align:center; padding: 20px;">
                                        <i class="fas fa-inbox" style="font-size: 2rem; color: var(--gray); margin-bottom: 10px;"></i>
                                        <p>No enrollments found.</p>
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
                                    <td colspan="3" style="text-align:center; padding: 20px;">
                                        <i class="fas fa-bullhorn" style="font-size: 2rem; color: var(--gray); margin-bottom: 10px;"></i>
                                        <p>No announcements yet.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple script for sidebar active state
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-links a');
            
            navLinks.forEach(link => {
                const linkPage = link.getAttribute('href');
                if (linkPage === currentPage) {
                    link.parentElement.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>