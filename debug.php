<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Get student ID from session
$student_id = $_SESSION['user_id'] ?? 1; // Use 1 for testing if needed

echo "<h2>DEBUG: Checking Why Exams Are Not Showing</h2>";

// Step 1: Check if student exists
echo "<h3>1. Checking Student ID: $student_id</h3>";
$student_check = $pdo->prepare("SELECT user_id, username, full_name FROM users WHERE user_id = ?");
$student_check->execute([$student_id]);
$student = $student_check->fetch();
if ($student) {
    echo "✅ Student found: " . htmlspecialchars($student['full_name'] ?? $student['username']) . "<br>";
} else {
    echo "❌ Student not found!<br>";
}

// Step 2: Check enrollments
echo "<h3>2. Checking Student Enrollments:</h3>";
$enrollments = $pdo->prepare("
    SELECT e.enrollment_id, e.schedule_id, s.course_id, c.course_code, c.course_name
    FROM enrollments e
    LEFT JOIN schedule s ON e.schedule_id = s.schedule_id
    LEFT JOIN courses c ON s.course_id = c.course_id
    WHERE e.student_id = ?
");
$enrollments->execute([$student_id]);
$student_enrollments = $enrollments->fetchAll();

if (empty($student_enrollments)) {
    echo "❌ No enrollments found for this student!<br>";
} else {
    echo "✅ Found " . count($student_enrollments) . " enrollments:<br>";
    echo "<table border='1'>";
    echo "<tr><th>Enrollment ID</th><th>Schedule ID</th><th>Course ID</th><th>Course Code</th><th>Course Name</th></tr>";
    foreach ($student_enrollments as $enrollment) {
        echo "<tr>";
        echo "<td>" . $enrollment['enrollment_id'] . "</td>";
        echo "<td>" . $enrollment['schedule_id'] . "</td>";
        echo "<td>" . $enrollment['course_id'] . "</td>";
        echo "<td>" . ($enrollment['course_code'] ?? 'NULL') . "</td>";
        echo "<td>" . ($enrollment['course_name'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Step 3: Check if schedule table has course_id
echo "<h3>3. Checking Schedule Table Structure:</h3>";
$schedule_structure = $pdo->query("DESCRIBE schedule");
$schedule_cols = $schedule_structure->fetchAll(PDO::FETCH_COLUMN, 0);
echo "Schedule table columns: " . implode(', ', $schedule_cols) . "<br>";
if (in_array('course_id', $schedule_cols)) {
    echo "✅ Schedule table has course_id column<br>";
} else {
    echo "❌ Schedule table DOES NOT have course_id column!<br>";
}

// Step 4: Check exam_schedules table
echo "<h3>4. Checking Exam Schedules Table:</h3>";
$exam_schedules = $pdo->query("
    SELECT es.*, c.course_code, c.course_name 
    FROM exam_schedules es
    LEFT JOIN courses c ON es.course_id = c.course_id
    LIMIT 10
");
$all_exams = $exam_schedules->fetchAll();

if (empty($all_exams)) {
    echo "❌ No exams found in exam_schedules table!<br>";
    echo "The table is empty. Head/Admin needs to create exams.<br>";
} else {
    echo "✅ Found " . count($all_exams) . " exams in exam_schedules table:<br>";
    echo "<table border='1'>";
    echo "<tr><th>Exam ID</th><th>Course ID</th><th>Course</th><th>Type</th><th>Date</th><th>Published</th></tr>";
    foreach ($all_exams as $exam) {
        echo "<tr>";
        echo "<td>" . $exam['exam_id'] . "</td>";
        echo "<td>" . $exam['course_id'] . "</td>";
        echo "<td>" . ($exam['course_code'] ?? 'N/A') . "</td>";
        echo "<td>" . $exam['exam_type'] . "</td>";
        echo "<td>" . $exam['exam_date'] . "</td>";
        echo "<td>" . ($exam['is_published'] == 1 ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Step 5: Test the actual query
echo "<h3>5. Testing the Exam Query:</h3>";
if (!empty($student_enrollments)) {
    // Get course IDs from enrollments
    $course_ids = [];
    foreach ($student_enrollments as $enrollment) {
        if (!empty($enrollment['course_id'])) {
            $course_ids[] = $enrollment['course_id'];
        }
    }
    
    if (empty($course_ids)) {
        echo "❌ No course_ids found in student's enrollments!<br>";
        echo "Check if schedule table has course_id data.<br>";
    } else {
        echo "Student's course IDs: " . implode(', ', $course_ids) . "<br>";
        
        // Test the query
        $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
        $test_query = $pdo->prepare("
            SELECT es.*, c.course_code, c.course_name
            FROM exam_schedules es
            JOIN courses c ON es.course_id = c.course_id
            WHERE es.course_id IN ($placeholders)
            AND es.is_published = 1
        ");
        $test_query->execute($course_ids);
        $found_exams = $test_query->fetchAll();
        
        if (empty($found_exams)) {
            echo "❌ No published exams found for these course IDs!<br>";
            echo "Possible reasons:<br>";
            echo "1. No exams exist for these courses<br>";
            echo "2. Exams exist but are not published (is_published = 0)<br>";
            echo "3. Course IDs don't match between schedule and exam_schedules<br>";
        } else {
            echo "✅ Found " . count($found_exams) . " published exams for student's courses:<br>";
            echo "<table border='1'>";
            echo "<tr><th>Exam ID</th><th>Course</th><th>Type</th><th>Date</th><th>Time</th></tr>";
            foreach ($found_exams as $exam) {
                echo "<tr>";
                echo "<td>" . $exam['exam_id'] . "</td>";
                echo "<td>" . $exam['course_code'] . "</td>";
                echo "<td>" . $exam['exam_type'] . "</td>";
                echo "<td>" . $exam['exam_date'] . "</td>";
                echo "<td>" . $exam['start_time'] . " - " . $exam['end_time'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
}

// Step 6: Check if schedule table has data
echo "<h3>6. Checking Schedule Table Data:</h3>";
$schedule_data = $pdo->query("SELECT schedule_id, course_id FROM schedule LIMIT 10");
$schedules = $schedule_data->fetchAll();

if (empty($schedules)) {
    echo "❌ Schedule table is empty or has no course_id data!<br>";
} else {
    echo "Schedule table sample data:<br>";
    echo "<table border='1'>";
    echo "<tr><th>Schedule ID</th><th>Course ID</th></tr>";
    foreach ($schedules as $schedule) {
        echo "<tr>";
        echo "<td>" . $schedule['schedule_id'] . "</td>";
        echo "<td>" . ($schedule['course_id'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>