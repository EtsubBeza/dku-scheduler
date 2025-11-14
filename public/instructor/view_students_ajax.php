<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor'){
    echo "Unauthorized access";
    exit;
}

$instructor_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'] ?? 0;

if($course_id <= 0) {
    echo "Invalid course ID";
    exit;
}

// Verify that the instructor actually teaches this course
$verify_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM schedule s 
    WHERE s.course_id = ? AND s.instructor_id = ?
");
$verify_stmt->execute([$course_id, $instructor_id]);
$is_teaching = $verify_stmt->fetchColumn();

if(!$is_teaching) {
    echo "You are not teaching this course";
    exit;
}

// Fetch enrolled students for this course
$students_stmt = $pdo->prepare("
    SELECT DISTINCT u.user_id, u.username, u.full_name, u.email
    FROM enrollments e
    JOIN schedule s ON e.schedule_id = s.schedule_id
    JOIN users u ON e.student_id = u.user_id
    WHERE s.course_id = ? AND u.role = 'student'
    ORDER BY u.full_name, u.username
");
$students_stmt->execute([$course_id]);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get course name for display
$course_stmt = $pdo->prepare("SELECT course_name FROM courses WHERE course_id = ?");
$course_stmt->execute([$course_id]);
$course = $course_stmt->fetch(PDO::FETCH_ASSOC);
?>

<h3>Students enrolled in: <?= htmlspecialchars($course['course_name'] ?? 'Unknown Course') ?></h3>

<?php if(!empty($students)): ?>
    <table>
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Full Name</th>
                <th>Username</th>
                <th>Email</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($students as $student): ?>
                <tr>
                    <td><?= htmlspecialchars($student['user_id']) ?></td>
                    <td><?= htmlspecialchars($student['full_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($student['username']) ?></td>
                    <td><?= htmlspecialchars($student['email'] ?? 'N/A') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top: 15px; font-weight: bold;">Total Students: <?= count($students) ?></p>
<?php else: ?>
    <p style="text-align: center; padding: 20px; color: #666; font-style: italic;">
        No students enrolled in this course yet.
    </p>
<?php endif; ?>