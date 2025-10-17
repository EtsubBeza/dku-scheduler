<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_head'){
    header("Location: ../index.php");
    exit;
}

$dept_id = $_SESSION['department_id'] ?? 0;
$message = "";
$success = false;

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $year = $_POST['year'] ?? null;
    $semester = $_POST['semester'] ?? null;
    $courses = $_POST['courses'] ?? [];

    if(!$year || !$semester || empty($courses)){
        $message = "Please select year, semester, and at least one course.";
    } else {
        $instructors_stmt = $pdo->prepare("SELECT user_id FROM users WHERE role='instructor' AND department_id=?");
        $instructors_stmt->execute([$dept_id]);
        $instructors = $instructors_stmt->fetchAll(PDO::FETCH_COLUMN);

        $rooms_stmt = $pdo->query("SELECT room_id FROM rooms");
        $rooms = $rooms_stmt->fetchAll(PDO::FETCH_COLUMN);

        $days = ["Monday","Tuesday","Wednesday","Thursday","Friday"];
        $start_times = ["08:00","09:30","11:00","13:00","14:30"];
        $end_times = ["09:20","10:50","12:20","14:20","15:50"];

        foreach($courses as $course_id){
            foreach($days as $i => $day){
                $instructor = $instructors[array_rand($instructors)];
                $room = $rooms[array_rand($rooms)];
                $start_time = $start_times[$i % count($start_times)];
                $end_time = $end_times[$i % count($end_times)];

                $stmt = $pdo->prepare("
                    INSERT INTO schedule 
                    (course_id, instructor_id, room_id, day_of_week, start_time, end_time, academic_year, semester, year)
                    VALUES (?,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute([
                    $course_id,
                    $instructor,
                    $room,
                    $day,
                    $start_time,
                    $end_time,
                    date('Y'),
                    $semester,
                    $year
                ]);
            }
        }

        $message = "✅ Schedule generated successfully for year $year, semester $semester.";
        $success = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Auto Generate Schedule</title>
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f3f4f6;
    margin: 0;
    padding: 30px;
}
h1 {
    text-align: center;
    color: #2563eb;
    margin-bottom: 30px;
}

/* Container */
.form-container {
    background: #fff;
    padding: 25px 30px;
    max-width: 500px;
    margin: 0 auto;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
}

/* Labels and Selects */
.form-container label {
    display: block;
    margin: 12px 0 6px;
    font-weight: 600;
    color: #333;
}
.form-container select {
    width: 100%;
    padding: 10px 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 14px;
    cursor: pointer;
}
.form-container select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 6px rgba(37,99,235,0.3);
    outline: none;
}

/* Courses Checkboxes */
.courses-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 5px;
}
.courses-grid label {
    flex: 1 1 45%;
    background: #f1f5f9;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.2s;
}
.courses-grid input[type="checkbox"] {
    margin-right: 6px;
}
.courses-grid label:hover {
    background: #e0f2fe;
}

/* Buttons */
button, .back-btn {
    padding: 10px 18px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
    font-size: 14px;
    transition: 0.2s;
}
button {
    background: #2563eb;
    color: #fff;
    width: 100%;
    margin-top: 15px;
}
button:hover {
    background: #1e40af;
}
.back-btn {
    display: inline-block;
    text-decoration: none;
    background: #1abc9c;
    color: #fff;
    margin-top: 15px;
    text-align: center;
}
.back-btn:hover {
    background: #16a085;
}

/* Message */
.message {
    max-width: 500px;
    margin: 0 auto 20px;
    padding: 12px 18px;
    border-radius: 6px;
    text-align: center;
    font-weight: bold;
    color: #065f06;
    background: #d1ffd1;
    border: 1px solid #1abc9c;
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}

/* Responsive */
@media screen and (max-width: 520px) {
    .form-container, .message {
        width: 90%;
    }
    .courses-grid label {
        flex: 1 1 100%;
    }
}
</style>
</head>
<body>

<h1>Auto Generate Schedule</h1>

<?php if($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if(!$success): ?>
<div class="form-container">
    <form method="POST">
        <label>Select Year:</label>
        <select name="year" required>
            <option value="">--Select Year--</option>
            <option value="1">1st Year</option>
            <option value="2">2nd Year</option>
            <option value="3">3rd Year</option>
            <option value="4">4th Year</option>
        </select>

        <label>Select Semester:</label>
        <select name="semester" required>
            <option value="">--Select Semester--</option>
            <option value="1">1st Semester</option>
            <option value="2">2nd Semester</option>
        </select>

        <label>Select Courses:</label>
        <div class="courses-grid">
            <?php
            $courses_stmt = $pdo->prepare("SELECT * FROM courses WHERE department_id=?");
            $courses_stmt->execute([$dept_id]);
            $courses = $courses_stmt->fetchAll();
            foreach($courses as $c): ?>
                <label>
                    <input type="checkbox" name="courses[]" value="<?= $c['course_id'] ?>">
                    <?= htmlspecialchars($c['course_name']) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <button type="submit">Generate Schedule</button>
    </form>
</div>
<?php else: ?>
    <a href="manage_schedules.php" class="back-btn">← Back to Manage Schedules</a>
<?php endif; ?>

</body>
</html>
