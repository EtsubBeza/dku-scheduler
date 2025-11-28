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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assign Courses | Department Head Portal</title>
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

.assignment-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.assignment-table th,
.assignment-table td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.assignment-table th {
    background: #f8fafc;
    color: #374151;
    font-weight: 600;
}

.assignment-table tr:last-child td {
    border-bottom: none;
}

.assignment-table tr:hover {
    background: #f9fafb;
}

.action-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.action-btn.delete {
    background: #ef4444;
    color: white;
}

.action-btn.delete:hover {
    background: #dc2626;
    transform: translateY(-1px);
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
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Assign Courses</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile Picture">
            <p><?= htmlspecialchars($user['username'] ?? 'User') ?></p>
        </div>
        <a href="departmenthead_dashboard.php" class="<?= $current_page=='departmenthead_dashboard.php'?'active':'' ?>">Dashboard</a>
        <a href="manage_enrollments.php" class="<?= $current_page=='manage_enrollments.php'?'active':'' ?>">Manage Enrollments</a>
        <a href="manage_schedules.php" class="<?= $current_page=='manage_schedules.php'?'active':'' ?>">Manage Schedules</a>
        <a href="assign_courses.php" class="<?= $current_page=='assign_courses.php'?'active':'' ?>">Assign Courses</a>
        <a href="add_courses.php" class="<?= $current_page=='add_courses.php'?'active':'' ?>">Add Courses</a>
        <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">Edit Profile</a>
        <a href="manage_announcements.php" class="<?= $current_page=='manage_announcements.php'?'active':'' ?>">Announcements</a>
        <a href="../logout.php">Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Assign Courses to Instructors</h1>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile">
                <div>
                    <div><?= htmlspecialchars($user['username'] ?? 'User') ?></div>
                    <small>Department Head</small>
                </div>
            </div>
        </div>

        <?php if($message): ?>
            <div class="message <?= strpos($message, 'successful') !== false ? 'success' : (strpos($message, 'already') !== false ? 'warning' : 'error') ?>">
                <i class="fas fa-<?= strpos($message, 'successful') !== false ? 'check-circle' : (strpos($message, 'already') !== false ? 'exclamation-triangle' : 'exclamation-circle') ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Assignment Form Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-book"></i> Assign Course to Instructor</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="course_id">Select Course:</label>
                            <select name="course_id" id="course_id" class="form-control" required>
                                <option value="">-- Select Course --</option>
                                <?php foreach($courses as $course): ?>
                                    <option value="<?= $course['course_id'] ?>">
                                        <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="user_id">Select Instructor:</label>
                            <select name="user_id" id="user_id" class="form-control" required>
                                <option value="">-- Select Instructor --</option>
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
                            <label for="semester">Semester:</label>
                            <select name="semester" id="semester" class="form-control" required>
                                <option value="">-- Select Semester --</option>
                                <option value="Fall">Fall</option>
                                <option value="Spring">Spring</option>
                                <option value="Summer">Summer</option>
                                <option value="Winter">Winter</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="academic_year">Academic Year:</label>
                            <input type="text" name="academic_year" id="academic_year" class="form-control" required 
                                   placeholder="e.g., 2024-2025" value="<?= date('Y') . '-' . (date('Y') + 1) ?>">
                        </div>
                    </div>

                    <button type="submit" name="assign_course" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Assign Course
                    </button>
                </form>
            </div>
        </div>

        <!-- Current Assignments Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Current Assignments</h3>
            </div>
            <div class="card-body">
                <?php if($assignments): ?>
                    <div class="table-container">
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
                                <?php foreach($assignments as $a): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($a['course_code'] ?? 'N/A') ?></strong></td>
                                        <td><?= htmlspecialchars($a['course_name']) ?></td>
                                        <td>
                                            <?php
                                            // Display full_name if available, otherwise use username
                                            $displayName = !empty(trim($a['full_name'])) ? $a['full_name'] : $a['username'];
                                            echo htmlspecialchars($displayName);
                                            ?>
                                        </td>
                                        <td><span class="badge"><?= htmlspecialchars($a['semester']) ?></span></td>
                                        <td><?= htmlspecialchars($a['academic_year']) ?></td>
                                        <td>
                                            <a class="action-btn delete" href="?delete=<?= $a['id'] ?>" onclick="return confirm('Are you sure you want to delete this assignment?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h3>No Assignments Yet</h3>
                        <p>Assign your first course to an instructor using the form above.</p>
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