<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor'){
    header("Location: ../index.php");
    exit;
}

$instructor_id = $_SESSION['user_id'];

// Fetch enrolled students for this instructor's courses
$stmt = $pdo->prepare("
    SELECT 
        u.username,
        u.email,
        c.course_name,
        e.enrolled_at
    FROM enrollments e
    JOIN users u ON e.student_id = u.user_id
    JOIN schedule s ON e.schedule_id = s.schedule_id
    JOIN courses c ON s.course_id = c.course_id
    WHERE s.instructor_id = ?
    ORDER BY c.course_name, u.username
");
$stmt->execute([$instructor_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Enrolled Students</title>
<style>
/* ================= General Reset ================= */
* {margin:0;padding:0;box-sizing:border-box;}
body {font-family:Arial,sans-serif;display:flex;min-height:100vh;background: linear-gradient(135deg, #f3f4f6, #dbeafe);}

/* ================= Sidebar ================= */
.sidebar {position:fixed;top:0;left:0;height:100vh;width:220px;background-color:#2c3e50;padding-top:20px;display:flex;flex-direction:column;align-items:flex-start;box-shadow:2px 0 5px rgba(0,0,0,0.1);z-index:1000;overflow-y:auto;}
.sidebar h2 {color:#ecf0f1;text-align:center;width:100%;margin-bottom:20px;font-size:20px;}
.sidebar a {padding:12px 20px;text-decoration:none;font-size:16px;color:#bdc3c7;width:100%;transition:background 0.3s,color 0.3s;}
.sidebar a.active {background-color:#34495e;color:#fff;font-weight:bold;}
.sidebar a:hover {background-color:#34495e;color:#fff;}

/* ================= Main Content ================= */
.main-content {margin-left:220px;padding:30px;flex-grow:1;min-height:100vh;background:transparent;}
.main-content h1 {margin-bottom:20px;color:#111827;}
.main-content h2 {margin-bottom:15px;color:#2c3e50;}

/* ================= Tables ================= */
table {width:100%;border-collapse:collapse;margin-bottom:20px;background-color:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 10px rgba(0,0,0,0.1);}
th, td {padding:12px;text-align:left;border-bottom:1px solid #ddd;}
th {background-color:#2563eb;color:#fff;font-weight:600;}
tr:nth-child(even){background-color:#f9fafb;}
tr:hover{background-color:#e0f2fe;}

/* ================= Responsive ================= */
@media screen and (max-width:768px){
    body{flex-direction:column;}
    .sidebar{width:100%;padding:15px;box-shadow:none;}
    .main-content{margin:0;padding:20px;}
    table th, table td{padding:8px;font-size:14px;}
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h2>Instructor Panel</h2>
    <a href="instructor_dashboard.php" class="<?= $current_page=='instructor_dashboard.php'?'active':'' ?>">Dashboard</a>
    <a href="my_courses.php" class="<?= $current_page=='my_courses.php'?'active':'' ?>">My Courses</a>
    <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">Edit Profile</a>
    <a href="../logout.php">Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <h1>Enrolled Students</h1>

    <?php if($students): ?>
    <table>
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Email</th>
                <th>Course</th>
                <th>Enrolled At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($students as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['username']) ?></td>
                <td><?= htmlspecialchars($s['email']) ?></td>
                <td><?= htmlspecialchars($s['course_name']) ?></td>
                <td><?= date('Y-m-d H:i', strtotime($s['enrolled_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>No students enrolled in your courses yet.</p>
    <?php endif; ?>
</div>

</body>
</html>
