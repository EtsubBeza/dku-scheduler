<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit;
}

// Fetch all departments
$departments = $pdo->query("SELECT * FROM departments")->fetchAll();

// Add/Edit/Delete Course logic
if(isset($_POST['add_course'])){
    $stmt = $pdo->prepare("INSERT INTO courses (course_name, course_code, department_id) VALUES (?, ?, ?)");
    $stmt->execute([$_POST['course_name'], $_POST['course_code'], $_POST['department_id']]);
    header("Location: manage_courses.php");
    exit;
}
if(isset($_POST['edit_course'])){
    $stmt = $pdo->prepare("UPDATE courses SET course_name=?, course_code=?, department_id=? WHERE course_id=?");
    $stmt->execute([$_POST['course_name'], $_POST['course_code'], $_POST['department_id'], $_POST['course_id']]);
    header("Location: manage_courses.php");
    exit;
}
if(isset($_POST['delete_course'])){
    $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id=?");
    $stmt->execute([$_POST['course_id']]);
    header("Location: manage_courses.php");
    exit;
}

// Fetch course to edit
$edit_course = null;
if(isset($_GET['edit'])){
    $stmt = $pdo->prepare("SELECT c.*, d.category FROM courses c JOIN departments d ON c.department_id=d.department_id WHERE c.course_id=?");
    $stmt->execute([$_GET['edit']]);
    $edit_course = $stmt->fetch();
}

// Fetch all courses
$courses = $pdo->query("
    SELECT c.course_id, c.course_name, c.course_code, d.department_name, d.category
    FROM courses c
    JOIN departments d ON c.department_id = d.department_id
    ORDER BY c.course_id DESC
")->fetchAll();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Manage Courses</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
/* ================= Reset ================= */
* { margin:0; padding:0; box-sizing:border-box; font-family: "Segoe UI", Arial, sans-serif;}
body { display:flex; min-height:100vh; background:#f3f4f6; position:relative; } /* removed overflow-x:hidden */

/* ================= Sidebar ================= */
.sidebar {
    position: fixed; top:0; left:0;
    width:230px; height:100vh;
    background:#2c3e50;
    padding-top:20px;
    display:flex; flex-direction:column; align-items:stretch;
    transition: transform 0.3s ease-in-out; z-index:1100;
}
.sidebar h2 { color:#ecf0f1; text-align:center; margin-bottom:25px; font-size:22px; }
.sidebar a { padding:12px 20px; text-decoration:none; font-size:16px; color:#bdc3c7; width:100%; display:block; transition: background 0.3s, color 0.3s, font-weight 0.3s;}
.sidebar a:hover, .sidebar a.active { background:#1abc9c; color:#fff; font-weight:bold; }

/* ================= Overlay ================= */
.overlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.4); z-index:1000; display:none; opacity:0;
    transition: opacity 0.3s ease;
}
.overlay.active { display:block; opacity:1; }

/* ================= Topbar for Hamburger ================= */
.topbar {
    display:none; position: fixed; top:0; left:0; width:100%;
    background:#2c3e50; color:#fff; padding:15px 20px;
    z-index:1200; justify-content:space-between; align-items:center;
}
.menu-btn { font-size:26px; background:none; border:none; color:white; cursor:pointer; }

/* ================= Main Content ================= */
.content { margin-left:230px; padding:40px 30px; flex:1; background:#f9fafb; min-height:100vh; transition: margin-left 0.3s ease-in-out; }
h1 { font-size:28px; margin-bottom:20px; color:#111827; }

/* ================= Form ================= */
.course-form-wrapper {
    overflow-x: auto; 
    -webkit-overflow-scrolling: touch;
    width: 100%;
    margin-bottom:20px;
}
.course-form {
    display:grid;
    grid-template-columns: 1fr 1fr 180px 180px auto;
    gap:10px; align-items:center;
    background:#fff; padding:15px; border-radius:10px;
    box-shadow:0 4px 12px rgba(0,0,0,0.05); border:1px solid rgba(15,23,42,0.05);
    min-width:850px; /* ensures horizontal scroll */
}
.course-form input, .course-form select { padding:10px; border-radius:6px; border:1px solid #d1d5db; font-size:14px; }
.course-form .actions { display:flex; gap:8px; align-items:center; }
.btn { padding:10px 12px; border-radius:6px; border:none; cursor:pointer; font-weight:600; font-size:14px; }
.btn-primary { background:#1abc9c; color:#fff; } .btn-primary:hover { background:#159b81; }
.cancel-btn { text-decoration:none; color:#ef4444; font-weight:600; }

/* ================= Table ================= */
.table-wrapper {
    position: relative; margin-top:20px;
    background:#fff; padding:12px; border-radius:8px; border:1px solid rgba(15,23,42,0.04);
    box-shadow:0 6px 18px rgba(0,0,0,0.03);
}
.table-container { width:100%; overflow-x:auto; -webkit-overflow-scrolling: touch; }
.course-table { width:100%; min-width:900px; border-collapse: collapse; font-size:14px; }
.course-table thead th { position:sticky; top:0; background:linear-gradient(180deg,#1abc9c,#16a085); color:#fff; padding:12px; text-align:left; font-weight:700; z-index:5; }
.course-table th, .course-table td { border-bottom:1px solid #e6e8eb; padding:12px; }
.course-table tbody tr:hover { background:#f7fafb; }
.course-table td .small-action { margin-right:8px; color:#0ea5a3; }
.course-table button { background:transparent; border:none; color:#ef4444; cursor:pointer; font-weight:600; }

/* ================= Responsive ================= */
@media(max-width:768px){
    .topbar{ display:flex; }
    .sidebar{ transform:translateX(-100%); }
    .sidebar.active{ transform:translateX(0); }
    .content{ margin-left:0; padding-top:80px; }
    .course-form{ grid-template-columns: repeat(auto-fill, minmax(180px,1fr)); min-width:850px; }
    .course-form .actions{ justify-content:flex-start; }
    .table-container{ max-width:100%; overflow-x:auto; }
}
</style>
</head>
<body>

<!-- Mobile Topbar -->
<div class="topbar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <span>Manage Courses</span>
</div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>Admin Panel</h2>
    <a href="dashboard.php" class="<?= $current_page=='dashboard.php'?'active':'' ?>">Dashboard</a>
    <a href="manage_users.php" class="<?= $current_page=='manage_users.php'?'active':'' ?>">Manage Users</a>
    <a href="approve_users.php" class="<?= $current_page=='approve_users.php'?'active':'' ?>">Approve Users</a>
    <a href="manage_schedules.php" class="<?= $current_page=='manage_schedules.php'?'active':'' ?>">Manage Schedules</a>
    <a href="manage_rooms.php" class="<?= $current_page=='manage_rooms.php'?'active':'' ?>">Manage Rooms</a>
    <a href="manage_courses.php" class="<?= $current_page=='manage_courses.php'?'active':'' ?>">Manage Courses</a>
    <a href="manage_announcements.php" class="<?= $current_page=='manage_announcements.php'?'active':'' ?>">Manage Announcements</a>
    <a href="../logout.php">Logout</a>
</div>

<!-- Overlay -->
<div class="overlay" id="overlay" onclick="toggleMenu()"></div>

<!-- Main Content -->
<div class="content">
    <h1>Manage Courses</h1>

    <!-- Add/Edit Form -->
    <div class="course-form-wrapper">
        <form method="POST" class="course-form">
            <input type="hidden" name="course_id" value="<?= $edit_course['course_id'] ?? '' ?>">
            <input type="text" name="course_name" placeholder="Course Name" required value="<?= htmlspecialchars($edit_course['course_name'] ?? '') ?>">
            <input type="text" name="course_code" placeholder="Course Code" required value="<?= htmlspecialchars($edit_course['course_code'] ?? '') ?>">
            <select id="category" aria-label="Filter by category">
                <option value="">All Categories</option>
                <option value="Social" <?= (isset($edit_course) && $edit_course['category']=='Social')?'selected':'' ?>>Social</option>
                <option value="Natural" <?= (isset($edit_course) && $edit_course['category']=='Natural')?'selected':'' ?>>Natural</option>
            </select>
            <select id="department" name="department_id" required>
                <option value="">Select Department</option>
                <?php foreach($departments as $d): ?>
                    <option value="<?= $d['department_id'] ?>" data-category="<?= htmlspecialchars($d['category']) ?>" <?= (isset($edit_course) && $edit_course['department_id']==$d['department_id'])?'selected':'' ?>><?= htmlspecialchars($d['department_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="actions">
              <button class="btn btn-primary" type="submit" name="<?= isset($edit_course) ? 'edit_course' : 'add_course' ?>"><?= isset($edit_course) ? 'Update Course' : 'Add Course' ?></button>
              <?php if(isset($edit_course)): ?>
                <a class="cancel-btn" href="manage_courses.php">Cancel</a>
              <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Courses Table -->
    <div class="table-wrapper">
        <div class="table-container">
            <table class="course-table" id="courseTable">
                <thead>
                    <tr>
                        <th>ID</th><th>Course Name</th><th>Course Code</th><th>Department</th><th>Category</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($courses as $c): ?>
                    <tr data-category="<?= htmlspecialchars($c['category']) ?>">
                        <td><?= $c['course_id'] ?></td>
                        <td><?= htmlspecialchars($c['course_name']) ?></td>
                        <td><?= htmlspecialchars($c['course_code']) ?></td>
                        <td><?= htmlspecialchars($c['department_name']) ?></td>
                        <td><?= htmlspecialchars($c['category']) ?></td>
                        <td>
                          <a class="small-action" href="manage_courses.php?edit=<?= $c['course_id'] ?>">Edit</a> |
                          <form method="POST" onsubmit="return confirm('Delete this course?')" style="display:inline;">
                            <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                            <button type="submit" name="delete_course">Delete</button>
                          </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Hamburger toggle
function toggleMenu(){
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

// Category filter
const categorySelect = document.getElementById('category');
const departmentSelect = document.getElementById('department');
const rows = document.querySelectorAll('#courseTable tbody tr');
function filterDepartments(){
  const cat = categorySelect.value;
  for(let option of departmentSelect.options){
    if(option.value==='') continue;
    option.style.display = (!cat || option.dataset.category===cat)?'block':'none';
  }
  for(let row of rows){
    row.style.display = (!cat || row.dataset.category===cat)?'':'none';
  }
}
categorySelect.addEventListener('change',filterDepartments);
filterDepartments();
</script>

</body>
</html>
