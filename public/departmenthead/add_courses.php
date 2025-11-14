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
    $credit_hours = (int)$_POST['credit_hours'];
    $prerequisite = trim($_POST['prerequisite']);
    $category = $_POST['category'];
    $contact_hours = (int)$_POST['contact_hours'];
    $lab_hours = (int)$_POST['lab_hours'];
    $tutorial_hours = (int)$_POST['tutorial_hours'];
    $description = trim($_POST['description']);

    // Validate total hours match credit hours
    $total_contact_hours = $contact_hours + $lab_hours + $tutorial_hours;
    
    if($course_name && $course_code && $credit_hours > 0){
        if($total_contact_hours == $credit_hours){
            $stmt = $pdo->prepare("INSERT INTO courses 
                (course_name, course_code, credit_hours, prerequisite, category, 
                 contact_hours, lab_hours, tutorial_hours, description, department_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $course_name, $course_code, $credit_hours, $prerequisite, $category,
                $contact_hours, $lab_hours, $tutorial_hours, $description, $dept_id
            ]);
            $message = "Course '$course_name' added successfully!";
        } else {
            $message = "Error: Contact hours ($contact_hours) + Lab hours ($lab_hours) + Tutorial hours ($tutorial_hours) must equal Credit hours ($credit_hours)";
        }
    } else {
        $message = "Please fill all required fields correctly.";
    }
}

// Handle Edit Course
if(isset($_POST['edit_course'])){
    $course_id = $_POST['course_id'];
    $course_name = trim($_POST['course_name']);
    $course_code = trim($_POST['course_code']);
    $credit_hours = (int)$_POST['credit_hours'];
    $prerequisite = trim($_POST['prerequisite']);
    $category = $_POST['category'];
    $contact_hours = (int)$_POST['contact_hours'];
    $lab_hours = (int)$_POST['lab_hours'];
    $tutorial_hours = (int)$_POST['tutorial_hours'];
    $description = trim($_POST['description']);

    // Validate total hours match credit hours
    $total_contact_hours = $contact_hours + $lab_hours + $tutorial_hours;
    
    if($course_name && $course_code && $credit_hours > 0){
        if($total_contact_hours == $credit_hours){
            $stmt = $pdo->prepare("UPDATE courses SET 
                course_name=?, course_code=?, credit_hours=?, prerequisite=?, 
                category=?, contact_hours=?, lab_hours=?, tutorial_hours=?, description=?
                WHERE course_id=? AND department_id=?");
            
            $stmt->execute([
                $course_name, $course_code, $credit_hours, $prerequisite, $category,
                $contact_hours, $lab_hours, $tutorial_hours, $description, $course_id, $dept_id
            ]);
            $message = "Course updated successfully!";
        } else {
            $message = "Error: Total contact hours must equal credit hours";
        }
    } else {
        $message = "Please fill all required fields correctly.";
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
$courses_stmt = $pdo->prepare("SELECT * FROM courses WHERE department_id = ? ORDER BY category, course_name");
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
.form-group {margin-bottom:15px;}
.form-group label {display:block; margin-bottom:5px; font-weight:bold;}
.form-group input, .form-group select, .form-group textarea {
    width:100%; max-width:400px; padding:8px 12px; border:1px solid #ccc; border-radius:4px;
}
.form-row {display:flex; gap:15px;}
.form-row .form-group {flex:1;}
form button {background:#1abc9c; color:#fff; border:none; border-radius:5px; padding:10px 20px; cursor:pointer; margin-top:10px;}
form button:hover {background:#16a085;}

/* Courses Table */
table {width:100%; border-collapse:collapse; margin-top:20px;}
th, td {border:1px solid #ccc; padding:12px; text-align:left;}
th {background:#1abc9c; color:#fff;}
tr:nth-child(even){background:#f2f2f2;}
tr:hover{background:#e0e0e0;}
.message {padding:10px; background:#d1ffd1; border:1px solid #1abc9c; margin-bottom:20px; border-radius:5px;}
.message.error {background:#ffd1d1; border-color:#ff6b6b;}
.course-badge {display:inline-block; padding:2px 8px; border-radius:12px; font-size:0.8em; margin-left:5px;}
.badge-compulsory {background:#e74c3c; color:white;}
.badge-elective {background:#3498db; color:white;}
.badge-optional {background:#2ecc71; color:white;}
.hours-info {font-size:0.9em; color:#666;}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <h1>Add a New Course</h1>

    <?php if($message): ?>
        <div class="message <?= strpos($message, 'Error:') === 0 ? 'error' : '' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label for="course_code">Course Code *</label>
                <input type="text" name="course_code" placeholder="e.g., CS101" required>
            </div>
            <div class="form-group">
                <label for="course_name">Course Name *</label>
                <input type="text" name="course_name" placeholder="e.g., Introduction to Programming" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="credit_hours">Credit Hours *</label>
                <select name="credit_hours" required>
                    <option value="">Select Credit Hours</option>
                    <option value="1">1 Credit Hour</option>
                    <option value="2">2 Credit Hours</option>
                    <option value="3" selected>3 Credit Hours</option>
                    <option value="4">4 Credit Hours</option>
                    <option value="5">5 Credit Hours</option>
                </select>
            </div>
            <div class="form-group">
                <label for="category">Course Category *</label>
                <select name="category" required>
                    <option value="Compulsory">Compulsory</option>
                    <option value="Elective">Elective</option>
                    <option value="Optional">Optional</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="contact_hours">Contact Hours (Theory) *</label>
                <input type="number" name="contact_hours" min="0" max="5" value="3" required>
                <small class="hours-info">Classroom teaching hours</small>
            </div>
            <div class="form-group">
                <label for="lab_hours">Lab Hours *</label>
                <input type="number" name="lab_hours" min="0" max="5" value="0" required>
                <small class="hours-info">Laboratory/practical hours</small>
            </div>
            <div class="form-group">
                <label for="tutorial_hours">Tutorial Hours *</label>
                <input type="number" name="tutorial_hours" min="0" max="5" value="0" required>
                <small class="hours-info">Tutorial/discussion hours</small>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="prerequisite">Prerequisite Course</label>
                <input type="text" name="prerequisite" placeholder="e.g., CS101, MATH102 or None">
                <small class="hours-info">Enter course codes separated by commas</small>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Course Description</label>
            <textarea name="description" rows="3" placeholder="Brief course description..."></textarea>
        </div>

        <button type="submit" name="add_course">Add Course</button>
    </form>

    <h2>Existing Courses in Your Department</h2>
    <?php if($courses): ?>
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Credits</th>
                <th>Category</th>
                <th>Hours (C/L/T)</th>
                <th>Prerequisite</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($courses as $c): ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['course_code']) ?></strong></td>
                <td><?= htmlspecialchars($c['course_name']) ?></td>
                <td><?= $c['credit_hours'] ?></td>
                <td>
                    <?= $c['category'] ?>
                    <span class="course-badge badge-<?= strtolower($c['category']) ?>">
                        <?= $c['category'] ?>
                    </span>
                </td>
                <td>
                    <strong><?= $c['contact_hours'] ?>/<?= $c['lab_hours'] ?>/<?= $c['tutorial_hours'] ?></strong>
                    <br>
                    <small>(Theory/Lab/Tutorial)</small>
                </td>
                <td><?= $c['prerequisite'] ? htmlspecialchars($c['prerequisite']) : 'None' ?></td>
                <td>
                    <!-- Edit Form -->
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                        <button type="submit" name="edit_course" style="background:#3498db;">Edit</button>
                    </form>
                    <!-- Delete Form -->
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this course?');">
                        <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                        <button type="submit" name="delete_course" style="background:#e74c3c;">Delete</button>
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

<script>
// Auto-calculate hours based on credit hours
document.querySelector('select[name="credit_hours"]').addEventListener('change', function() {
    const creditHours = parseInt(this.value);
    const contactInput = document.querySelector('input[name="contact_hours"]');
    const labInput = document.querySelector('input[name="lab_hours"]');
    const tutorialInput = document.querySelector('input[name="tutorial_hours"]');
    
    // Set default distribution based on credit hours
    if (creditHours === 1) {
        contactInput.value = 1;
        labInput.value = 0;
        tutorialInput.value = 0;
    } else if (creditHours === 2) {
        contactInput.value = 2;
        labInput.value = 0;
        tutorialInput.value = 0;
    } else if (creditHours === 3) {
        contactInput.value = 3;
        labInput.value = 0;
        tutorialInput.value = 0;
    } else if (creditHours === 4) {
        contactInput.value = 3;
        labInput.value = 1;
        tutorialInput.value = 0;
    } else if (creditHours === 5) {
        contactInput.value = 3;
        labInput.value = 2;
        tutorialInput.value = 0;
    }
});

// Validate hours total equals credit hours
document.querySelector('form').addEventListener('submit', function(e) {
    const creditHours = parseInt(document.querySelector('select[name="credit_hours"]').value);
    const contactHours = parseInt(document.querySelector('input[name="contact_hours"]').value);
    const labHours = parseInt(document.querySelector('input[name="lab_hours"]').value);
    const tutorialHours = parseInt(document.querySelector('input[name="tutorial_hours"]').value);
    
    const totalHours = contactHours + labHours + tutorialHours;
    
    if (totalHours !== creditHours) {
        e.preventDefault();
        alert(`Error: Total hours (${totalHours}) must equal credit hours (${creditHours}). Please adjust the hours.`);
    }
});
</script>

</body>
</html>