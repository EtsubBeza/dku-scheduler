<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only allow department head
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_head'){
    header("Location: ../index.php");
    exit;
}

$dept_id = $_SESSION['department_id'] ?? 0;
$message = "";

// Handle assignment form submission
if(isset($_POST['assign_course'])){
    $course_id = $_POST['course_id'];
    $user_id = $_POST['user_id'];
    $semester = $_POST['semester'];
    $academic_year = $_POST['academic_year'];

    // Prevent duplicate assignment
    $check = $pdo->prepare("SELECT * FROM course_assignments WHERE course_id=? AND user_id=? AND semester=? AND academic_year=?");
    $check->execute([$course_id, $user_id, $semester, $academic_year]);

    if($check->fetch()){
        $message = "This course is already assigned to the selected instructor for this semester.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO course_assignments (course_id, user_id, semester, academic_year) VALUES (?, ?, ?, ?)");
        $stmt->execute([$course_id, $user_id, $semester, $academic_year]);
        $message = "Course assigned successfully!";
    }
}

// Handle delete assignment
if(isset($_GET['delete'])){
    $assignment_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM course_assignments WHERE id=?");
    $stmt->execute([$assignment_id]);
    header("Location: assign_courses.php");
    exit;
}

// Fetch courses and instructors for dropdown
$courses = $pdo->prepare("SELECT * FROM courses WHERE department_id=? ORDER BY course_name ASC");
$courses->execute([$dept_id]);
$courses = $courses->fetchAll();

$instructors = $pdo->prepare("SELECT user_id, username, full_name, email FROM users WHERE role='instructor' AND department_id=? ORDER BY full_name ASC, username ASC");
$instructors->execute([$dept_id]);
$instructors = $instructors->fetchAll();

// Fetch current assignments
$assignments_stmt = $pdo->prepare("
    SELECT ca.id, c.course_name, c.course_code, u.full_name, u.username, ca.semester, ca.academic_year 
    FROM course_assignments ca
    JOIN courses c ON ca.course_id = c.course_id
    JOIN users u ON ca.user_id = u.user_id
    WHERE c.department_id = ?
    ORDER BY ca.academic_year DESC, ca.semester, c.course_name
");
$assignments_stmt->execute([$dept_id]);
$assignments = $assignments_stmt->fetchAll();

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assign Courses</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
body {font-family: Arial, sans-serif; margin:0; background:#f3f4f6;}
.sidebar {
    position: fixed; top:0; left:0; width:240px; height:100%; background:#2c3e50; color:#fff; padding-top:20px;
}
.sidebar h2 {text-align:center; margin-bottom:20px;}
.sidebar a {display:block; color:#fff; padding:12px 20px; text-decoration:none; margin-bottom:5px;}
.sidebar a.active, .sidebar a:hover {background:#1abc9c;}
.main-content {margin-left:240px; padding:30px;}
.main-content h1 {margin-bottom:20px;}
.form-box {background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 8px rgba(0,0,0,0.05); margin-bottom:20px;}
.form-box select, .form-box input, .form-box button {padding:8px; margin-bottom:10px; width:100%; border-radius:6px; border:1px solid #ccc;}
.form-box button {background:#2563eb; color:#fff; border:none; cursor:pointer;}
.form-box button:hover {background:#1e40af;}
.message {padding:10px; background:#d1ffd1; border:1px solid #1abc9c; margin-bottom:20px; border-radius:5px; font-weight:bold;}
.assignment-table {width:100%; border-collapse: collapse; margin-top:20px; background:#fff; border-radius:12px; overflow:hidden;}
.assignment-table th, .assignment-table td {border:1px solid #ccc; padding:8px; text-align:left;}
.assignment-table th {background-color:#1e40af; color:#fff;}
.assignment-table tr:nth-child(even) {background-color:#f3f4f6;}
.button-action {padding:5px 10px; border-radius:5px; text-decoration:none; color:#fff;}
.button-delete {background-color:#dc2626;}
.button-action:hover {opacity:0.85; cursor:pointer;}
.form-row {display: flex; gap: 15px; margin-bottom: 15px;}
.form-group {flex: 1;}
@media screen and (max-width:768px){
    .sidebar {position:relative; width:100%; height:auto;}
    .main-content {margin-left:0;}
    .form-row {flex-direction: column;}
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <h1>Assign Courses to Instructors</h1>

    <?php if($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="form-box">
        <div class="form-row">
            <div class="form-group">
                <label>Select Course:</label>
                <select name="course_id" required>
                    <option value="">--Select Course--</option>
                    <?php foreach($courses as $course): ?>
                        <option value="<?= $course['course_id'] ?>">
                            <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Select Instructor:</label>
                <select name="user_id" required>
                    <option value="">--Select Instructor--</option>
                    <?php foreach($instructors as $inst): ?>
                        <option value="<?= $inst['user_id'] ?>">
                            <?php 
                            // Display full_name if available, otherwise use username
                            $displayName = !empty(trim($inst['full_name'])) ? $inst['full_name'] : $inst['username'];
                            echo htmlspecialchars($displayName . ' (' . $inst['email'] . ')');
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Semester:</label>
                <select name="semester" required>
                    <option value="">--Select Semester--</option>
                    <option value="Fall">Fall</option>
                    <option value="Spring">Spring</option>
                    <option value="Summer">Summer</option>
                    <option value="Winter">Winter</option>
                </select>
            </div>

            <div class="form-group">
                <label>Academic Year:</label>
                <input type="text" name="academic_year" required placeholder="e.g., 2024-2025" value="<?= date('Y') . '-' . (date('Y') + 1) ?>">
            </div>
        </div>

        <button type="submit" name="assign_course">Assign Course</button>
    </form>

    <h2>Current Assignments</h2>
    <table class="assignment-table">
        <thead>
            <tr>
                <th>Course Code</th>
                <th>Course Name</th>
                <th>Instructor</th>
                <th>Semester</th>
                <th>Academic Year</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if($assignments): ?>
                <?php foreach($assignments as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['course_code'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($a['course_name']) ?></td>
                        <td>
                            <?php
                            // Display full_name if available, otherwise use username
                            $displayName = !empty(trim($a['full_name'])) ? $a['full_name'] : $a['username'];
                            echo htmlspecialchars($displayName);
                            ?>
                        </td>
                        <td><?= htmlspecialchars($a['semester']) ?></td>
                        <td><?= htmlspecialchars($a['academic_year']) ?></td>
                        <td>
                            <a class="button-action button-delete" href="?delete=<?= $a['id'] ?>" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center;">No assignments yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>