<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit;
}

$editing = false;
$edit_id = 0;

// Handle Delete
if(isset($_GET['delete'])){
    $delete_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id=?");
    $stmt->execute([$delete_id]);
    header("Location: manage_users.php");
    exit;
}

// Handle Edit
if(isset($_GET['edit'])){
    $edit_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
    $stmt->execute([$edit_id]);
    $editing = true;
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle Form Submission
if(isset($_POST['save_user'])){
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];
    $department_id = $_POST['department_id'] ?: NULL;

    if($editing){
        if($password){
            $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, password=?, role=?, department_id=? WHERE user_id=?");
            $stmt->execute([$username, $email, password_hash($password,PASSWORD_DEFAULT), $role, $department_id, $edit_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, role=?, department_id=? WHERE user_id=?");
            $stmt->execute([$username, $email, $role, $department_id, $edit_id]);
        }
        header("Location: manage_users.php");
        exit;
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username,email,password,role,department_id) VALUES (?,?,?,?,?)");
        $stmt->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT),$role,$department_id]);
    }
}

// Fetch users and departments
$users = $pdo->query("SELECT u.*, d.department_name FROM users u LEFT JOIN departments d ON u.department_id=d.department_id")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments")->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users - DKU Scheduler</title>
<style>
/* ================= General Reset ================= */
* { margin:0; padding:0; box-sizing:border-box; font-family: "Segoe UI", Arial, sans-serif; }
body { display:flex; min-height:100vh; background:#f3f4f6; overflow-x:hidden; }

/* ================= Sidebar ================= */
.sidebar { position: fixed; top:0; left:0; width:230px; height:100vh; background:#2c3e50; padding-top:20px; display:flex; flex-direction:column; align-items:stretch; transition: transform 0.3s; z-index:1000; }
.sidebar h2 { color:#ecf0f1; text-align:center; margin-bottom:25px; font-size:22px; }
.sidebar a { padding:12px 20px; text-decoration:none; font-size:16px; color:#bdc3c7; width:100%; transition:0.3s; border-radius:8px; display:block; }
.sidebar a:hover { background:#34495e; color:#fff; font-weight:bold; }
.sidebar a.active { background:#1abc9c; color:#fff; font-weight:bold; }

/* ================= Topbar (Mobile) ================= */
.topbar { display:none; background:#2c3e50; color:#fff; padding:15px 20px; align-items:center; justify-content:space-between; width:100%; position:fixed; top:0; left:0; z-index:1100; }
.menu-btn { font-size:24px; background:none; border:none; color:white; cursor:pointer; }

/* ================= Main Content ================= */
.main-content { margin-left:230px; padding:40px 30px; flex:1; transition:margin-left 0.3s; }
.main-content h1 { font-size:28px; font-weight:bold; margin-bottom:20px; color:#111827; }

/* ================= Form Styling ================= */
.user-form { background:#fff; padding:20px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:25px; }
.user-form input, .user-form select, .user-form button { padding:10px; margin:8px 5px 12px 0; border-radius:8px; font-size:14px; border:1px solid #ccc; }
.user-form button { background:#2563eb; color:#fff; border:none; cursor:pointer; transition:0.2s; }
.user-form button:hover { background:#1d4ed8; }
.cancel-btn { text-decoration:none; color:#dc2626; margin-left:10px; }

/* ================= Table Styling ================= */
.table-container {
    width: 100%;
    overflow-x: auto; /* horizontal scroll always visible */
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #888 #f3f4f6;
    position: relative;
}
.table-container::-webkit-scrollbar {
    height: 12px;
}
.table-container::-webkit-scrollbar-track {
    background: #f3f4f6;
    border-radius: 6px;
}
.table-container::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 6px;
}
.table-container::-webkit-scrollbar-thumb:hover {
    background: #555;
}

.user-table { width:100%; border-collapse:collapse; min-width:700px; }
.user-table th, .user-table td { padding:12px; text-align:left; border-bottom:1px solid #ddd; }
.user-table th { background:#34495e; color:white; }
.user-table tr:nth-child(even){ background:#f2f2f2; }
.user-table tr:hover { background:#e0e0e0; }
.button-action { padding:5px 10px; border-radius:5px; text-decoration:none; color:#fff; }
.button-edit { background:#2563eb; } .button-delete { background:#dc2626; }
.button-edit:hover { background:#1d4ed8; } .button-delete:hover { background:#b91c1c; }

/* ================= Responsive ================= */
@media (max-width:992px){ .main-content{padding:25px;} }
@media (max-width:768px){
    .topbar{display:flex;}
    .sidebar{transform:translateX(-100%);}
    .sidebar.active{transform:translateX(0);}
    .main-content{margin-left:0; padding-top:80px;}
    .user-form{padding:15px;}
    .user-form input, .user-form select{width:100%; margin:8px 0;}

    /* Mobile-friendly card-style table */
    .user-table, .user-table thead, .user-table tbody, .user-table th, .user-table td, .user-table tr { display:block; width:100%; }
    .user-table thead tr { display:none; }
    .user-table tr { margin-bottom:15px; background:#fff; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.1); padding:10px; }
    .user-table td { text-align:right; padding-left:50%; position:relative; border:none; }
    .user-table td::before { content: attr(data-label); position:absolute; left:15px; width:45%; text-align:left; font-weight:bold; }

    /* Ensure horizontal scroll always visible on mobile */
    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}
</style>
</head>
<body>

<div class="topbar">
    <button class="menu-btn" onclick="toggleSidebar()">☰</button>
    <h2>Admin Panel</h2>
</div>

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

<div class="main-content">
    <h1><?= $editing ? "Edit User" : "Add User" ?></h1>

    <form method="POST" class="user-form">
        <label>Username:</label>
        <input type="text" name="username" value="<?= $editing ? $edit_data['username'] : '' ?>" required>
        <label>Email:</label>
        <input type="email" name="email" value="<?= $editing ? $edit_data['email'] : '' ?>" required>
        <label>Password: <?= $editing ? "(Leave blank to keep current)" : "" ?></label>
        <input type="password" name="password">
        <label>Role:</label>
        <select name="role" id="role-select" required>
            <option value="">--Select Role--</option>
            <option value="admin" <?= ($editing && $edit_data['role']=='admin')?'selected':'' ?>>Admin</option>
            <option value="student" <?= ($editing && $edit_data['role']=='student')?'selected':'' ?>>Student</option>
            <option value="instructor" <?= ($editing && $edit_data['role']=='instructor')?'selected':'' ?>>Instructor</option>
            <option value="department_head" <?= ($editing && $edit_data['role']=='department_head')?'selected':'' ?>>Department Head</option>
        </select>
        <label id="dept-label">Department:</label>
        <select name="department_id" id="department-select">
            <option value="">--Select Department--</option>
            <?php foreach($departments as $d): ?>
                <option value="<?= $d['department_id'] ?>" <?= ($editing && $edit_data['department_id']==$d['department_id'])?'selected':'' ?>>
                    <?= htmlspecialchars($d['department_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br>
        <button type="submit" name="save_user"><?= $editing ? "Update User" : "Add User" ?></button>
        <?php if($editing): ?>
            <a href="manage_users.php" class="cancel-btn">Cancel</a>
        <?php endif; ?>
    </form>

    <h2>Existing Users</h2>
    <div class="table-container">
        <table class="user-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                    <tr>
                        <td data-label="Username"><?= htmlspecialchars($u['username']) ?></td>
                        <td data-label="Email"><?= htmlspecialchars($u['email']) ?></td>
                        <td data-label="Role"><?= htmlspecialchars($u['role']) ?></td>
                        <td data-label="Department"><?= htmlspecialchars($u['department_name'] ?? '-') ?></td>
                        <td data-label="Actions">
                            <a class="button-action button-edit" href="?edit=<?= $u['user_id'] ?>">Edit</a>
                            <a class="button-action button-delete" href="?delete=<?= $u['user_id'] ?>" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleSidebar(){ document.getElementById("sidebar").classList.toggle("active"); }

// Show/hide department based on role
const roleSelect=document.getElementById('role-select');
const deptSelect=document.getElementById('department-select');
const deptLabel=document.getElementById('dept-label');
function toggleDept(){
    if(['department_head','instructor','student'].includes(roleSelect.value)){
        deptSelect.style.display='inline-block';
        deptLabel.style.display='inline-block';
        deptSelect.required=true;
    } else {
        deptSelect.style.display='none';
        deptLabel.style.display='none';
        deptSelect.required=false;
        deptSelect.value='';
    }
}
roleSelect.addEventListener('change', toggleDept);
window.addEventListener('load', toggleDept);
</script>

</body>
</html>
