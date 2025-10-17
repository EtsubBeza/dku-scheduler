<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Redirect if not department head
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_head'){
    header("Location: ../index.php");
    exit;
}

$dept_id = $_SESSION['department_id'] ?? 0;
$current_page = basename($_SERVER['PHP_SELF']);
$message = '';

// Handle Add Course
if(isset($_POST['add_course'])){
    $course_name = trim($_POST['course_name']);
    $course_code = trim($_POST['course_code']);

    if($course_name && $course_code){
        $stmt = $pdo->prepare("INSERT INTO courses (course_name, course_code, department_id) VALUES (?, ?, ?)");
        $stmt->execute([$course_name, $course_code, $dept_id]);
        $message = "Course '$course_name' added successfully!";
    } else {
        $message = "Please enter both course name and code.";
    }
}

// Handle Edit Course
if(isset($_POST['edit_course'])){
    $course_id = $_POST['course_id'];
    $course_name = trim($_POST['course_name']);
    $course_code = trim($_POST['course_code']);
    if($course_name && $course_code){
        $stmt = $pdo->prepare("UPDATE courses SET course_name=?, course_code=? WHERE course_id=? AND department_id=?");
        $stmt->execute([$course_name, $course_code, $course_id, $dept_id]);
        $message = "Course updated successfully!";
    } else {
        $message = "Please enter both course name and code.";
    }
}

// Handle Delete Course
if(isset($_POST['delete_course'])){
    $course_id = $_POST['course_id'];
    $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id=? AND department_id=?");
    $stmt->execute([$course_id, $dept_id]);
    $message = "Course deleted successfully!";
}

// Fetch existing courses in this department
$courses_stmt = $pdo->prepare("SELECT course_id, course_name, course_code FROM courses WHERE department_id = ? ORDER BY course_name");
$courses_stmt->execute([$dept_id]);
$courses = $courses_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Courses</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
/* Sidebar */
.sidebar {position: fixed; top: 0; left: 0; width: 220px; height: 100%; background: #2c3e50; color: #fff; padding-top: 20px;}
.sidebar h2 {text-align:center;margin-bottom:20px;}
.sidebar a {display:block;color:#fff;padding:12px 20px;text-decoration:none;margin-bottom:5px;}
.sidebar a.active, .sidebar a:hover {background:#1abc9c;}

/* Main content */
.main-content {margin-left:240px; padding:30px;}
h1 {margin-bottom:20px;}
form input, form button {padding:8px 12px;margin-right:10px;margin-bottom:10px;}
form button {background:#1abc9c; color:#fff; border:none; border-radius:5px; cursor:pointer;}
form button:hover {background:#16a085;}

/* Courses Table */
table {width:100%; border-collapse:collapse; margin-top:20px;}
th, td {border:1px solid #ccc; padding:10px; text-align:left;}
th {background:#1abc9c; color:#fff;}
tr:nth-child(even){background:#f2f2f2;}
tr:hover{background:#e0e0e0;}
.message {padding:10px; background:#d1ffd1; border:1px solid #1abc9c; margin-bottom:20px; border-radius:5px;}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>


<div class="main-content">
    <h1>Add a New Course</h1>

    <?php if($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="course_code" placeholder="Course Code" required>
        <input type="text" name="course_name" placeholder="Course Name" required>
        <button type="submit" name="add_course">Add Course</button>
    </form>

    <h2>Existing Courses in Your Department</h2>
    <?php if($courses): ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Course Code</th>
                <th>Course Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($courses as $c): ?>
            <tr>
                <td><?= $c['course_id'] ?></td>
                <td><?= htmlspecialchars($c['course_code']) ?></td>
                <td><?= htmlspecialchars($c['course_name']) ?></td>
                <td>
                    <!-- Edit Form -->
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                        <input type="text" name="course_code" value="<?= htmlspecialchars($c['course_code']) ?>" required>
                        <input type="text" name="course_name" value="<?= htmlspecialchars($c['course_name']) ?>" required>
                        <button type="submit" name="edit_course">Edit</button>
                    </form>
                    <!-- Delete Form -->
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this course?');">
                        <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                        <button type="submit" name="delete_course">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>No courses found in your department yet.</p>
    <?php endif; ?>
</div>

</body>
</html>
