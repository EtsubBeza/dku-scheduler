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
    SELECT e.enrollment_id, u.username AS student_name, c.course_name, s.day, s.start_time, s.end_time
    FROM enrollments e
    JOIN users u ON e.student_id = u.user_id
    JOIN schedule s ON e.schedule_id = s.schedule_id
    JOIN courses c ON s.course_id = c.course_id
    WHERE c.department_id = ?
    ORDER BY u.username, s.day, s.start_time
");
$enrollments_stmt->execute([$dept_id]);
$enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Enrollments | Department Head Portal</title>
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

select.form-control[multiple] {
    height: 200px;
}

.select-container {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.select-container > div {
    flex: 1;
    min-width: 300px;
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

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
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

.enrollment-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.enrollment-table th,
.enrollment-table td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.enrollment-table th {
    background: #f8fafc;
    color: #374151;
    font-weight: 600;
}

.enrollment-table tr:last-child td {
    border-bottom: none;
}

.enrollment-table tr:hover {
    background: #f9fafb;
}

.enrollment-table tr.selected {
    background-color: #e0e7ff;
}

.checkbox-cell {
    width: 50px;
    text-align: center;
}

.enrollment-table input[type="checkbox"] {
    transform: scale(1.2);
    cursor: pointer;
}

/* ================= Responsive ================= */
@media(max-width: 768px){
    .topbar { display:flex; }
    .sidebar { transform:translateX(-100%); }
    .sidebar.active { transform:translateX(0); }
    .main-content { margin-left:0; padding: 20px; padding-top: 80px; }
    .header { flex-direction: column; gap: 15px; align-items: flex-start; }
    .header h1 { font-size: 1.8rem; }
    .select-container { flex-direction: column; }
    .select-container > div { min-width: auto; }
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Manage Enrollments</h2>
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
            <h1>Manage Enrollments</h1>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile">
                <div>
                    <div><?= htmlspecialchars($user['username'] ?? 'User') ?></div>
                    <small>Department Head</small>
                </div>
            </div>
        </div>

        <?php if($message): ?>
            <div class="message <?= strpos($message, 'successful') !== false ? 'success' : (strpos($message, 'Please') !== false ? 'warning' : 'error') ?>">
                <i class="fas fa-<?= strpos($message, 'successful') !== false ? 'check-circle' : (strpos($message, 'Please') !== false ? 'exclamation-triangle' : 'exclamation-circle') ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Enrollment Form Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> Enroll Students</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="select-container">
                        <div class="form-group">
                            <label for="student_ids">Select Students:</label>
                            <select name="student_ids[]" id="student_ids" class="form-control" multiple size="10" required>
                                <?php foreach($students as $s): ?>
                                    <option value="<?= (int)$s['user_id'] ?>"><?= htmlspecialchars($s['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="course_ids">Select Courses:</label>
                            <select name="course_ids[]" id="course_ids" class="form-control" multiple size="10" required>
                                <?php foreach($courses as $c): ?>
                                    <option value="<?= (int)$c['course_id'] ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="enroll" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Enroll Students
                    </button>
                    <small style="display:block; margin-top:10px; color:#6b7280;">
                        Hold Ctrl/Cmd to select multiple students and courses. Students will be enrolled in all schedule sessions for selected courses.
                    </small>
                </form>
            </div>
        </div>

        <!-- Current Enrollments Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Current Enrollments</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="table-container">
                        <table class="enrollment-table" role="table" aria-label="Current enrollments">
                            <thead>
                                <tr>
                                    <th class="checkbox-cell">
                                        <input type="checkbox" id="select-all">
                                    </th>
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
                                            <td class="checkbox-cell">
                                                <input type="checkbox" name="unenroll_ids[]" value="<?= (int)$e['enrollment_id'] ?>" class="enrollment-checkbox">
                                            </td>
                                            <td><?= htmlspecialchars($e['student_name']) ?></td>
                                            <td><?= htmlspecialchars($e['course_name']) ?></td>
                                            <td><?= htmlspecialchars($e['day']) ?></td>
                                            <td><?= htmlspecialchars($e['start_time']) ?> - <?= htmlspecialchars($e['end_time']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:30px; color:#6b7280;">
                                            <i class="fas fa-inbox" style="font-size:3rem; margin-bottom:15px; display:block;"></i>
                                            No enrollments yet.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if($enrollments): ?>
                        <div style="margin-top:20px; display:flex; gap:15px; flex-wrap:wrap;">
                            <button type="submit" name="unenroll_selected" class="btn btn-danger" id="unenrollBtn" disabled>
                                <i class="fas fa-user-minus"></i> Unenroll Selected
                            </button>
                            <button type="submit" name="unenroll_all" class="btn btn-danger" onclick="return confirm('Are you sure you want to unenroll ALL students from ALL courses? This action cannot be undone.')">
                                <i class="fas fa-trash"></i> Unenroll All
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
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

        // Select/Deselect all checkboxes
        document.getElementById('select-all').addEventListener('change', function(){
            const checked = this.checked;
            document.querySelectorAll('.enrollment-checkbox').forEach(cb => cb.checked = checked);
            updateUnenrollButton();
        });

        // Update unenroll button state
        function updateUnenrollButton() {
            const checked = document.querySelectorAll('.enrollment-checkbox:checked');
            const unenrollBtn = document.getElementById('unenrollBtn');
            if (unenrollBtn) {
                unenrollBtn.disabled = checked.length === 0;
                unenrollBtn.innerHTML = checked.length > 0 ? 
                    `<i class="fas fa-user-minus"></i> Unenroll Selected (${checked.length})` : 
                    '<i class="fas fa-user-minus"></i> Unenroll Selected';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Checkbox change handler
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('enrollment-checkbox')) {
                    e.target.closest('tr').classList.toggle('selected', e.target.checked);
                    updateUnenrollButton();
                }
            });

            // Set active state for current page
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.sidebar a');
            
            navLinks.forEach(link => {
                const linkPage = link.getAttribute('href');
                if (linkPage === currentPage) {
                    link.classList.add('active');
                }
            });

            // Initialize unenroll button state
            updateUnenrollButton();
        });
    </script>
</body>
</html>