<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Redirect if not department head
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_head'){
    header("Location: ../index.php");
    exit;
}

$dept_id = $_SESSION['department_id'] ?? 0;
$message = "";

// Handle form submission for enrollment
if(isset($_POST['enroll'])){
    $student_ids = $_POST['student_ids'] ?? [];
    $course_ids = $_POST['course_ids'] ?? [];

    if(!empty($student_ids) && !empty($course_ids)){
        $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, schedule_id) VALUES (?, ?)");
        foreach($student_ids as $student_id){
            foreach($course_ids as $course_id){
                // Get all schedules for this course
                $schedules_stmt = $pdo->prepare("SELECT schedule_id FROM schedule WHERE course_id=?");
                $schedules_stmt->execute([$course_id]);
                $course_schedules = $schedules_stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach($course_schedules as $sid){
                    // Prevent duplicate enrollment
                    $check = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id=? AND schedule_id=?");
                    $check->execute([$student_id, $sid]);
                    if(!$check->fetch()){
                        $stmt->execute([$student_id, $sid]);
                    }
                }
            }
        }
        $message = "Enrollment successful!";
    } else {
        $message = "Please select at least one student and one course.";
    }
}

// Handle bulk unenroll selected
if(isset($_POST['unenroll_selected'])){
    $unenroll_ids = $_POST['unenroll_ids'] ?? [];
    if(!empty($unenroll_ids)){
        $placeholders = implode(',', array_fill(0, count($unenroll_ids), '?'));
        $del_stmt = $pdo->prepare("
            DELETE e FROM enrollments e
            JOIN schedule s ON e.schedule_id = s.schedule_id
            JOIN courses c ON s.course_id = c.course_id
            WHERE e.enrollment_id IN ($placeholders) AND c.department_id = ?
        ");
        $del_stmt->execute([...$unenroll_ids, $dept_id]);
        $message = count($unenroll_ids) . " student(s) unenrolled successfully!";
    } else {
        $message = "No enrollments selected.";
    }
}

// Handle unenroll all enrollments in this department
if(isset($_POST['unenroll_all'])){
    $del_stmt = $pdo->prepare("
        DELETE e FROM enrollments e
        JOIN schedule s ON e.schedule_id = s.schedule_id
        JOIN courses c ON s.course_id = c.course_id
        WHERE c.department_id = ?
    ");
    $del_stmt->execute([$dept_id]);
    $message = "All enrollments have been removed for this department.";
}

// Fetch students
$students_stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE role='student' AND department_id=? ORDER BY username");
$students_stmt->execute([$dept_id]);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch courses
$courses_stmt = $pdo->prepare("SELECT course_id, course_name FROM courses WHERE department_id=? ORDER BY course_name");
$courses_stmt->execute([$dept_id]);
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch enrollments
$enrollments_stmt = $pdo->prepare("
    SELECT e.enrollment_id, u.username AS student_name, c.course_name, s.day_of_week, s.start_time, s.end_time
    FROM enrollments e
    JOIN users u ON e.student_id = u.user_id
    JOIN schedule s ON e.schedule_id = s.schedule_id
    JOIN courses c ON s.course_id = c.course_id
    WHERE c.department_id = ?
    ORDER BY u.username, s.day_of_week, s.start_time
");
$enrollments_stmt->execute([$dept_id]);
$enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Enrollments</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
body {font-family: Arial, sans-serif; margin:0; background:#f3f4f6;}
.main-content {margin-left:240px; padding:30px; min-height:100vh;}

/* Form Card */
.form-card {
    background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 8px rgba(0,0,0,0.05); margin-top:20px;
}
.form-card h2 {margin-bottom:15px;}
.form-card label {display:block; margin:10px 0 5px; font-weight:bold;}
.form-card select, .form-card input[type="submit"] {
    padding:10px; border-radius:6px; border:1px solid #ccc; margin-bottom:15px; width:100%;
}
.form-card input[type="submit"] { background:#2563eb; color:#fff; border:none; cursor:pointer; }
.form-card input[type="submit"]:hover { background:#1e40af; }

/* Side by side multi-select */
.select-container {
    display:flex; gap:20px; flex-wrap:wrap;
}
.select-container > div {flex:1;}

/* Message */
.message {padding:10px; background:#d1ffd1; border:1px solid #1abc9c; margin-bottom:20px; border-radius:5px; font-weight:bold;}

/* Enrollment Table */
.enrollment-table {width:100%; border-collapse:collapse; margin-top:20px; background:#fff; border-radius:12px; overflow:hidden;}
.enrollment-table th, .enrollment-table td {border:1px solid #ccc; padding:10px; text-align:left;}
.enrollment-table th {background:#1e40af; color:#fff;}
.enrollment-table tr:nth-child(even) {background:#f3f4f6;}

/* Checkbox styling */
.enrollment-table input[type="checkbox"] {transform: scale(1.2); cursor:pointer;}

/* Buttons */
.button-unenroll, .unenroll-button {
    background:#dc2626; color:#fff; padding:5px 10px; border-radius:5px; text-decoration:none; border:none; cursor:pointer;
}
.button-unenroll:hover, .unenroll-button:hover {opacity:0.85;}

/* Responsive */
@media screen and (max-width:768px){
    .main-content {margin:0; padding:20px;}
    .select-container {flex-direction:column;}
}
</style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
    <h1>Manage Enrollments</h1>

    <?php if($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST">
            <div class="select-container">
                <div>
                    <label for="student_ids">Select Students:</label>
                    <select name="student_ids[]" id="student_ids" multiple size="10" required>
                        <?php foreach($students as $s): ?>
                            <option value="<?= (int)$s['user_id'] ?>"><?= htmlspecialchars($s['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="course_ids">Select Courses:</label>
                    <select name="course_ids[]" id="course_ids" multiple size="10" required>
                        <?php foreach($courses as $c): ?>
                            <option value="<?= (int)$c['course_id'] ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <input type="submit" name="enroll" value="Enroll Students">
        </form>
    </div>

    <h2 style="margin-top:30px;">Current Enrollments</h2>

    <form method="POST">
        <table class="enrollment-table" role="table" aria-label="Current enrollments">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Day</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if($enrollments): ?>
                    <?php foreach($enrollments as $e): ?>
                        <tr>
                            <td><input type="checkbox" name="unenroll_ids[]" value="<?= (int)$e['enrollment_id'] ?>"></td>
                            <td><?= htmlspecialchars($e['student_name']) ?></td>
                            <td><?= htmlspecialchars($e['course_name']) ?></td>
                            <td><?= htmlspecialchars($e['day_of_week']) ?></td>
                            <td><?= htmlspecialchars($e['start_time']) ?> - <?= htmlspecialchars($e['end_time']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center;">No enrollments yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <input type="submit" name="unenroll_selected" class="unenroll-button" value="Unenroll Selected" style="margin-top:10px;">
        <input type="submit" name="unenroll_all" class="unenroll-button" value="Unenroll All" style="margin-top:10px; background:#b91c1c;">
    </form>
</div>

<script>
// Select/Deselect all checkboxes
document.getElementById('select-all').addEventListener('change', function(){
    const checked = this.checked;
    document.querySelectorAll('input[name="unenroll_ids[]"]').forEach(cb => cb.checked = checked);
});
</script>

</body>
</html>
