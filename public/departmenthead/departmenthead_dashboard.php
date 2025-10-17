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
           GROUP_CONCAT(DISTINCT s.day_of_week ORDER BY FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday') SEPARATOR ', ') AS days,
           MIN(s.start_time) AS start_time,
           MAX(s.end_time) AS end_time
    FROM enrollments e
    JOIN users u ON e.student_id = u.user_id
    JOIN schedule s ON e.schedule_id = s.schedule_id
    JOIN courses c ON s.course_id = c.course_id
    WHERE c.department_id = ?
    GROUP BY u.username, c.course_name
    ORDER BY u.username, FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday')
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
<link rel="stylesheet" href="../assets/style.css">
<style>
/* ================= Dashboard Main Content ================= */
.main-content {
    margin-left: 250px; /* Sidebar width */
    padding: 30px;
    background-color: #f9fafb;
    min-height: 100vh;
    transition: all 0.3s ease;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Welcome Section */
.welcome-section {
    background: linear-gradient(135deg, #4f46e5, #3b82f6);
    color: white;
    padding: 30px 20px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.welcome-section h1 { font-size: 28px; margin-bottom: 10px; }
.welcome-section p { font-size: 16px; opacity: 0.9; }

/* Stats Cards */
.stats-cards { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 30px; }
.stats-cards .card {
    flex: 1 1 200px;
    background-color: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
}
.stats-cards .card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
.stats-cards .card h3 { font-size: 18px; margin-bottom: 10px; color: #374151; }
.stats-cards .card p { font-size: 24px; font-weight: bold; color: #111827; }

/* Table Section */
.table-section, .announcement-section { background-color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 30px; }
.table-section h2, .announcement-section h2 { margin-bottom: 20px; color: #111827; }

.enrollments-table, .announcements-table { width: 100%; border-collapse: collapse; }
.enrollments-table th, .enrollments-table td,
.announcements-table th, .announcements-table td {
    padding: 12px 15px; text-align: left; border-bottom: 1px solid #e5e7eb;
}
.enrollments-table th, .announcements-table th { background-color: #f3f4f6; color: #374151; font-weight: 600; }
.enrollments-table tr:hover, .announcements-table tr:hover { background-color: #f9fafb; transition: background-color 0.2s; }

/* Announcement Form */
.announcement-form { margin-bottom: 20px; }
.announcement-form input, .announcement-form textarea { width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 6px; border: 1px solid #ccc; }
.announcement-form button { padding: 10px 20px; background-color: #4f46e5; color: white; border: none; border-radius: 6px; cursor: pointer; }
.announcement-form button:hover { background-color: #3b36c4; }

/* Responsive */
@media (max-width: 768px) { .stats-cards { flex-direction: column; } }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="welcome-section">
        <h1>Welcome, <?= htmlspecialchars($current_user['username']); ?> 👋</h1>
        <p>Here is your department dashboard. Use the sidebar to manage students, courses, enrollments, and announcements.</p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-cards">
        <div class="card"><h3>Total Students</h3><p><?= $total_students ?></p></div>
        <div class="card"><h3>Total Courses</h3><p><?= $total_courses ?></p></div>
        <div class="card"><h3>Total Enrollments</h3><p><?= $total_enrollments ?></p></div>
    </div>

    <!-- Enrollments Table -->
    <div class="table-section">
        <h2>Current Enrollments</h2>
        <table class="enrollments-table">
            <thead>
                <tr><th>Student</th><th>Course</th><th>Days</th><th>Start Time</th><th>End Time</th></tr>
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
                    <tr><td colspan="5" style="text-align:center;">No enrollments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Announcements Section -->
    <div class="announcement-section">
        <h2>Post Announcement</h2>
        <form method="post" class="announcement-form">
            <input type="text" name="announcement_title" placeholder="Title" required>
            <textarea name="announcement_message" placeholder="Message" rows="4" required></textarea>
            <button type="submit">Post Announcement</button>
        </form>
        <?php if($announcement_msg): ?>
            <p style="color:green;"><?= $announcement_msg ?></p>
        <?php endif; ?>

        <h2>Recent Announcements</h2>
        <table class="announcements-table">
            <thead>
                <tr><th>Title</th><th>Message</th><th>Posted At</th></tr>
            </thead>
            <tbody>
                <?php if($announcements): ?>
                    <?php foreach($announcements as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['title']) ?></td>
                            <td><?= nl2br(htmlspecialchars($a['message'])) ?></td>
                            <td><?= date('M d, Y h:i A', strtotime($a['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" style="text-align:center;">No announcements yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
