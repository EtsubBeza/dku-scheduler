<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Redirect if not department head
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_head'){
    header("Location: ../index.php");
    exit;
}

$dept_id = $_SESSION['department_id'] ?? 0;
$message = '';

// Fetch current user info for sidebar
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Determine profile picture path
$profile_path = '../../uploads/profiles/' . ($user['profile_picture'] ?? '');
if (!empty($user['profile_picture']) && file_exists($profile_path)) {
    $profile_src = $profile_path;
} else {
    $profile_src = '../assets/default_profile.png';
}

$current_page = basename($_SERVER['PHP_SELF']);

// Handle Add Course
if(isset($_POST['add_course'])){
    // Initialize all variables with default values
    $course_name = isset($_POST['course_name']) ? trim($_POST['course_name']) : '';
    $course_code = isset($_POST['course_code']) ? trim($_POST['course_code']) : '';
    $credit_hours = isset($_POST['credit_hours']) ? (int)$_POST['credit_hours'] : 0;
    $prerequisite = isset($_POST['prerequisite']) ? trim($_POST['prerequisite']) : '';
    $category = isset($_POST['category']) ? $_POST['category'] : 'Compulsory';
    $contact_hours = isset($_POST['contact_hours']) ? (int)$_POST['contact_hours'] : 0;
    $lab_hours = isset($_POST['lab_hours']) ? (int)$_POST['lab_hours'] : 0;
    $tutorial_hours = isset($_POST['tutorial_hours']) ? (int)$_POST['tutorial_hours'] : 0;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    // Validate required fields
    if(empty($course_name) || empty($course_code) || $credit_hours <= 0) {
        $message = "Please fill all required fields (Course Name, Course Code, and Credit Hours).";
    } else {
        // Validate total hours match credit hours
        $total_contact_hours = $contact_hours + $lab_hours + $tutorial_hours;
        
        if($total_contact_hours == $credit_hours){
            try {
                $stmt = $pdo->prepare("INSERT INTO courses 
                    (course_name, course_code, credit_hours, prerequisite, category, 
                     contact_hours, lab_hours, tutorial_hours, description, department_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $course_name, $course_code, $credit_hours, $prerequisite, $category,
                    $contact_hours, $lab_hours, $tutorial_hours, $description, $dept_id
                ]);
                $message = "Course '$course_name' added successfully!";
            } catch(PDOException $e) {
                $message = "Error adding course: " . $e->getMessage();
            }
        } else {
            $message = "Error: Contact hours ($contact_hours) + Lab hours ($lab_hours) + Tutorial hours ($tutorial_hours) must equal Credit hours ($credit_hours)";
        }
    }
}

// Handle Edit Course
if(isset($_POST['edit_course'])){
    $course_id = isset($_POST['course_id']) ? $_POST['course_id'] : 0;
    $course_name = isset($_POST['course_name']) ? trim($_POST['course_name']) : '';
    $course_code = isset($_POST['course_code']) ? trim($_POST['course_code']) : '';
    $credit_hours = isset($_POST['credit_hours']) ? (int)$_POST['credit_hours'] : 0;
    $prerequisite = isset($_POST['prerequisite']) ? trim($_POST['prerequisite']) : '';
    $category = isset($_POST['category']) ? $_POST['category'] : 'Compulsory';
    $contact_hours = isset($_POST['contact_hours']) ? (int)$_POST['contact_hours'] : 0;
    $lab_hours = isset($_POST['lab_hours']) ? (int)$_POST['lab_hours'] : 0;
    $tutorial_hours = isset($_POST['tutorial_hours']) ? (int)$_POST['tutorial_hours'] : 0;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    if($course_id > 0 && !empty($course_name) && !empty($course_code) && $credit_hours > 0) {
        $total_contact_hours = $contact_hours + $lab_hours + $tutorial_hours;
        
        if($total_contact_hours == $credit_hours){
            try {
                $stmt = $pdo->prepare("UPDATE courses SET 
                    course_name=?, course_code=?, credit_hours=?, prerequisite=?, 
                    category=?, contact_hours=?, lab_hours=?, tutorial_hours=?, description=?
                    WHERE course_id=? AND department_id=?");
                
                $stmt->execute([
                    $course_name, $course_code, $credit_hours, $prerequisite, $category,
                    $contact_hours, $lab_hours, $tutorial_hours, $description, $course_id, $dept_id
                ]);
                $message = "Course updated successfully!";
            } catch(PDOException $e) {
                $message = "Error updating course: " . $e->getMessage();
            }
        } else {
            $message = "Error: Total contact hours must equal credit hours";
        }
    } else {
        $message = "Please fill all required fields correctly.";
    }
}

// Handle Delete Course
if(isset($_POST['delete_course'])){
    $course_id = isset($_POST['course_id']) ? $_POST['course_id'] : 0;
    if($course_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id=? AND department_id=?");
            $stmt->execute([$course_id, $dept_id]);
            $message = "Course deleted successfully!";
        } catch(PDOException $e) {
            $message = "Error deleting course: " . $e->getMessage();
        }
    } else {
        $message = "Error: Course ID not provided";
    }
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
<title>Add Courses | Department Head Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin:0; padding:0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

/* ================= Topbar for Hamburger ================= */
.topbar {
    display: none;
    position: fixed; top:0; left:0; width:100%;
    background:#2c3e50; color:#fff;
    padding:15px 20px;
    z-index:1200;
    justify-content:space-between; align-items:center;
}
.menu-btn {
    font-size:26px;
    background:#1abc9c;
    border:none; color:#fff;
    cursor:pointer;
    padding:10px 14px;
    border-radius:8px;
    font-weight:600;
    transition: background 0.3s, transform 0.2s;
}
.menu-btn:hover { background:#159b81; transform:translateY(-2px); }

/* ================= Sidebar ================= */
.sidebar {
    position: fixed; top:0; left:0;
    width:250px; height:100%;
    background:#1f2937; color:#fff;
    z-index:1100;
    transition: transform 0.3s ease;
    padding: 20px 0;
}
.sidebar.hidden { transform:translateX(-260px); }
.sidebar a { 
    display:block; 
    padding:12px 20px; 
    color:#fff; 
    text-decoration:none; 
    transition: background 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.sidebar a:hover, .sidebar a.active { background:#1abc9c; }

.sidebar-profile {
    text-align: center;
    margin-bottom: 20px;
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.sidebar-profile img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 10px;
    border: 2px solid #1abc9c;
    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
}

.sidebar-profile p {
    color: #fff;
    font-weight: bold;
    margin: 0;
    font-size: 16px;
}

/* ================= Overlay ================= */
.overlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.4); z-index:1050;
    display:none; opacity:0; transition: opacity 0.3s ease;
}
.overlay.active { display:block; opacity:1; }

/* ================= Main content ================= */
.main-content {
    margin-left: 250px;
    padding:30px 50px;
    min-height:100vh;
    background:#ffffff;
    transition: all 0.3s ease;
}

/* Header Styles */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px 0;
}

.header h1 {
    font-size: 2.2rem;
    color: #1f2937;
    font-weight: 700;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    background: white;
    padding: 12px 18px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.user-info img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
}

/* Card Styles */
.card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.1);
    margin-bottom: 25px;
    overflow: hidden;
}

.card-header {
    padding: 20px 25px;
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 15px 15px 0 0;
}

.card-header h3 {
    font-size: 1.4rem;
    font-weight: 600;
}

.card-body {
    padding: 25px;
}

/* Form Styles */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.form-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.form-row .form-group {
    flex: 1;
    min-width: 250px;
}

/* Button Styles */
.btn {
    padding: 14px 24px;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: #6366f1;
    color: white;
}

.btn-primary:hover {
    background: #4f46e5;
    transform: translateY(-2px);
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
}

.btn-warning {
    background: #f59e0b;
    color: white;
}

.btn-warning:hover {
    background: #d97706;
    transform: translateY(-2px);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 0.9rem;
}

/* Message Styles */
.message {
    padding: 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
}

.message.success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.message.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.message.warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}

/* Table Styles */
.table-container {
    overflow-x: auto;
    border-radius: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin-top: 20px;
}

.courses-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.courses-table th,
.courses-table td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.courses-table th {
    background: #f8fafc;
    color: #374151;
    font-weight: 600;
}

.courses-table tr:last-child td {
    border-bottom: none;
}

.courses-table tr:hover {
    background: #f9fafb;
}

/* Badge Styles */
.course-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 8px;
}

.badge-compulsory {
    background: #dc2626;
    color: white;
}

.badge-elective {
    background: #2563eb;
    color: white;
}

.badge-optional {
    background: #059669;
    color: white;
}

.hours-info {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 4px;
}

.empty-state {
    text-align: center;
    padding: 50px;
    color: #6b7280;
}

.empty-state i {
    font-size: 3.5rem;
    margin-bottom: 20px;
    color: #d1d5db;
}

.empty-state h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: #374151;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* ================= Responsive ================= */
@media(max-width: 768px){
    .topbar { display:flex; }
    .sidebar { transform:translateX(-100%); }
    .sidebar.active { transform:translateX(0); }
    .main-content { margin-left:0; padding: 20px; padding-top: 80px; }
    .header { flex-direction: column; gap: 15px; align-items: flex-start; }
    .header h1 { font-size: 1.8rem; }
    .form-row { flex-direction: column; }
    .form-row .form-group { min-width: auto; }
    .action-buttons { flex-direction: column; }
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Add Courses</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile Picture">
            <p><?= htmlspecialchars($user['username'] ?? 'User') ?></p>
        </div>
        <nav>
            <a href="departmenthead_dashboard.php" class="<?= $current_page=='departmenthead_dashboard.php'?'active':'' ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="manage_enrollments.php" class="<?= $current_page=='manage_enrollments.php'?'active':'' ?>">
                <i class="fas fa-users"></i> Manage Enrollments
            </a>
            <a href="manage_schedules.php" class="<?= $current_page=='manage_schedules.php'?'active':'' ?>">
                <i class="fas fa-calendar-alt"></i> Manage Schedules
            </a>
            <a href="assign_courses.php" class="<?= $current_page=='assign_courses.php'?'active':'' ?>">
                <i class="fas fa-chalkboard-teacher"></i> Assign Courses
            </a>
            <a href="add_courses.php" class="<?= $current_page=='add_courses.php'?'active':'' ?>">
                <i class="fas fa-book"></i> Add Courses
            </a>
            <a href="exam_schedules.php" class="<?= $current_page=='exam_schedules.php'?'active':'' ?>">
                <i class="fas fa-clipboard-list"></i> Exam Schedules
            </a>
            <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">
                <i class="fas fa-user-edit"></i> Edit Profile
            </a>
            <a href="manage_announcements.php" class="<?= $current_page=='manage_announcements.php'?'active':'' ?>">
                <i class="fas fa-bullhorn"></i> Announcements
            </a>
            <a href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Add Courses</h1>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile">
                <div>
                    <div><?= htmlspecialchars($user['username'] ?? 'User') ?></div>
                    <small>Department Head</small>
                </div>
            </div>
        </div>

        <?php if($message): ?>
            <div class="message <?= strpos($message, 'Error:') === 0 ? 'error' : (strpos($message, 'successfully') !== false ? 'success' : 'warning') ?>">
                <i class="fas fa-<?= strpos($message, 'Error:') === 0 ? 'exclamation-circle' : (strpos($message, 'successfully') !== false ? 'check-circle' : 'exclamation-triangle') ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Add Course Form Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> Add New Course</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="course_code">Course Code *</label>
                            <input type="text" name="course_code" id="course_code" class="form-control" placeholder="e.g., CS101" required>
                        </div>
                        <div class="form-group">
                            <label for="course_name">Course Name *</label>
                            <input type="text" name="course_name" id="course_name" class="form-control" placeholder="e.g., Introduction to Programming" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="credit_hours">Credit Hours *</label>
                            <select name="credit_hours" id="credit_hours" class="form-control" required>
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
                            <select name="category" id="category" class="form-control" required>
                                <option value="Compulsory">Compulsory</option>
                                <option value="Elective">Elective</option>
                                <option value="Optional">Optional</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_hours">Contact Hours (Theory) *</label>
                            <input type="number" name="contact_hours" id="contact_hours" class="form-control" min="0" max="5" value="3" required>
                            <small class="hours-info">Classroom teaching hours</small>
                        </div>
                        <div class="form-group">
                            <label for="lab_hours">Lab Hours *</label>
                            <input type="number" name="lab_hours" id="lab_hours" class="form-control" min="0" max="5" value="0" required>
                            <small class="hours-info">Laboratory/practical hours</small>
                        </div>
                        <div class="form-group">
                            <label for="tutorial_hours">Tutorial Hours *</label>
                            <input type="number" name="tutorial_hours" id="tutorial_hours" class="form-control" min="0" max="5" value="0" required>
                            <small class="hours-info">Tutorial/discussion hours</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="prerequisite">Prerequisite Course</label>
                            <input type="text" name="prerequisite" id="prerequisite" class="form-control" placeholder="e.g., CS101, MATH102 or None">
                            <small class="hours-info">Enter course codes separated by commas</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Course Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3" placeholder="Brief course description..."></textarea>
                    </div>

                    <button type="submit" name="add_course" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Add Course
                    </button>
                </form>
            </div>
        </div>

        <!-- Existing Courses Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-book"></i> Existing Courses in Your Department</h3>
            </div>
            <div class="card-body">
                <?php if($courses): ?>
                    <div class="table-container">
                        <table class="courses-table">
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
                                        <small class="hours-info">(Theory/Lab/Tutorial)</small>
                                    </td>
                                    <td><?= $c['prerequisite'] ? htmlspecialchars($c['prerequisite']) : 'None' ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- Edit Form -->
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                                                <button type="submit" name="edit_course" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </form>
                                            <!-- Delete Form -->
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this course?');">
                                                <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                                                <button type="submit" name="delete_course" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h3>No Courses Found</h3>
                        <p>No courses found in your department yet. Add your first course using the form above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

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

        // Set active state for current page
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.sidebar a');
            
            navLinks.forEach(link => {
                const linkPage = link.getAttribute('href');
                if (linkPage === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>