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

// Fetch enrolled students for this course (UPDATED to include year)
$students_stmt = $pdo->prepare("
    SELECT DISTINCT 
        u.user_id, 
        u.username, 
        u.full_name, 
        u.email,
        u.year  -- Added year column
    FROM enrollments e
    JOIN schedule s ON e.schedule_id = s.schedule_id
    JOIN users u ON e.student_id = u.user_id
    WHERE s.course_id = ? AND u.role = 'student'
    ORDER BY 
        CASE 
            WHEN u.year = 'Freshman' THEN 1
            WHEN u.year = 'Sophomore' THEN 2
            WHEN u.year = 'Junior' THEN 3
            WHEN u.year = 'Senior' THEN 4
            WHEN u.year = 'E1' THEN 1
            WHEN u.year = 'E2' THEN 2
            WHEN u.year = 'E3' THEN 3
            WHEN u.year = 'E4' THEN 4
            WHEN u.year REGEXP '^[0-9]+$' THEN CAST(u.year AS UNSIGNED)
            ELSE 99
        END,
        u.full_name, 
        u.username
");
$students_stmt->execute([$course_id]);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get course name for display
$course_stmt = $pdo->prepare("SELECT course_name FROM courses WHERE course_id = ?");
$course_stmt->execute([$course_id]);
$course = $course_stmt->fetch(PDO::FETCH_ASSOC);

// Function to get year badge class
function getYearBadgeClass($year) {
    if (empty($year)) return 'unknown';
    
    $year = strtolower(trim($year));
    
    // Handle numeric years
    if (is_numeric($year)) {
        $year_num = (int)$year;
        if ($year_num >= 1 && $year_num <= 4) {
            return "year-$year_num";
        }
    }
    
    // Handle text years
    switch ($year) {
        case '1':
        case 'freshman':
        case 'e1':
            return 'year-1';
        case '2':
        case 'sophomore':
        case 'e2':
            return 'year-2';
        case '3':
        case 'junior':
        case 'e3':
            return 'year-3';
        case '4':
        case 'senior':
        case 'e4':
            return 'year-4';
        default:
            return 'unknown';
    }
}

// Function to format year display
function formatYearDisplay($year) {
    if (empty($year)) return 'Not Specified';
    
    $year = trim($year);
    
    // If it's already a word like "Freshman", return as is
    if (!is_numeric($year) && !preg_match('/^E[1-4]$/i', $year)) {
        return ucfirst($year);
    }
    
    // Handle numeric years
    if (is_numeric($year)) {
        $year_num = (int)$year;
        $suffix = '';
        if ($year_num == 1) {
            $suffix = 'st';
        } elseif ($year_num == 2) {
            $suffix = 'nd';
        } elseif ($year_num == 3) {
            $suffix = 'rd';
        } else {
            $suffix = 'th';
        }
        return $year_num . $suffix . ' Year';
    }
    
    // Handle E1, E2, etc.
    if (preg_match('/^E([1-4])$/i', $year, $matches)) {
        $year_num = (int)$matches[1];
        $suffix = '';
        if ($year_num == 1) {
            $suffix = 'st';
        } elseif ($year_num == 2) {
            $suffix = 'nd';
        } elseif ($year_num == 3) {
            $suffix = 'rd';
        } else {
            $suffix = 'th';
        }
        return 'E' . $year_num . ' (' . $year_num . $suffix . ' Year)';
    }
    
    return $year;
}
?>

<h3>Students enrolled in: <?= htmlspecialchars($course['course_name'] ?? 'Unknown Course') ?></h3>

<?php if(!empty($students)): ?>
    <div class="modal-table-container">
        <table class="modal-table">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Year</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($students as $student): ?>
                    <tr>
                        <td><?= htmlspecialchars($student['user_id']) ?></td>
                        <td><?= htmlspecialchars($student['full_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($student['username']) ?></td>
                        <td>
                            <?php 
                            $year_value = $student['year'] ?? '';
                            $badge_class = getYearBadgeClass($year_value);
                            $formatted_year = formatYearDisplay($year_value);
                            ?>
                            <span class="year-badge <?= $badge_class ?>">
                                <?= htmlspecialchars($formatted_year) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($student['email'] ?? 'N/A') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="margin-top: 15px; font-weight: bold;">Total Students: <?= count($students) ?></p>
<?php else: ?>
    <div class="modal-empty">
        <i class="fas fa-users-slash"></i>
        <h3>No Students Enrolled</h3>
        <p>No students are currently enrolled in this course.</p>
    </div>
<?php endif; ?>