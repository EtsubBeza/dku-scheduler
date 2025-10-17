<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only allow admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit;
}

// Handle approval action
if(isset($_GET['approve'])){
    $user_id = intval($_GET['approve']);
    $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: approve_users.php");
    exit;
}

// Fetch pending users
$pending_users = $pdo->query("
    SELECT u.user_id, u.username, u.email, u.id_number, u.role, d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE u.is_approved = 0
    ORDER BY u.user_id ASC
")->fetchAll();

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approve Users - DKU Scheduler</title>
<style>
/* =============== RESET =============== */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: "Segoe UI", Arial, sans-serif;
}

body {
  display: flex;
  min-height: 100vh;
  background-color: #f3f4f6;
  overflow-x: hidden;
}

/* =============== SIDEBAR =============== */
.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  width: 230px;
  height: 100vh;
  background-color: #2c3e50;
  padding-top: 20px;
  display: flex;
  flex-direction: column;
  z-index: 1000;
  transition: transform 0.3s ease-in-out;
}

.sidebar h2 {
  color: #ecf0f1;
  text-align: center;
  margin-bottom: 25px;
  font-size: 22px;
}

.sidebar a {
  padding: 12px 20px;
  text-decoration: none;
  font-size: 16px;
  color: #bdc3c7;
  border-radius: 8px;
  display: block;
  transition: all 0.3s;
}

.sidebar a:hover {
  background-color: #34495e;
  color: #fff;
  font-weight: bold;
}

.sidebar a.active {
  background-color: #1abc9c;
  color: #fff;
  font-weight: bold;
}

/* =============== TOPBAR (MOBILE) =============== */
.topbar {
  display: none;
  background-color: #2c3e50;
  color: white;
  padding: 15px 20px;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  position: fixed;
  top: 0;
  z-index: 1100;
}

.menu-btn {
  font-size: 24px;
  background: none;
  border: none;
  color: white;
  cursor: pointer;
}

/* =============== MAIN CONTAINER =============== */
.container {
  margin-left: 230px;
  padding: 40px 30px;
  flex: 1;
  background-color: #f9fafb;
  min-height: 100vh;
  transition: margin-left 0.3s ease-in-out;
}

.container h1 {
  font-size: 26px;
  margin-bottom: 20px;
  color: #111827;
}

/* =============== TABLE =============== */
.table-container {
  width: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  -webkit-overflow-scrolling: touch;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 700px;
}

th, td {
  padding: 14px;
  text-align: left;
  border-bottom: 1px solid #ddd;
  font-size: 15px;
}

th {
  background-color: #34495e;
  color: white;
  text-transform: uppercase;
  font-size: 13px;
}

tr:nth-child(even) { background-color: #f9fafb; }
tr:hover { background-color: #ecf0f1; }

button {
  padding: 8px 14px;
  background-color: #10b981;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
}

button:hover { background-color: #059669; }

/* =============== RESPONSIVE =============== */
@media (max-width: 992px) {
  .container { padding: 25px; }
}

@media (max-width: 768px) {
  .topbar { display: flex; }
  .sidebar { transform: translateX(-100%); }
  .sidebar.active { transform: translateX(0); }
  .container { margin-left: 0; padding-top: 80px; }

  th, td { font-size: 13px; padding: 10px; }
}

/* =============== MOBILE CARD VIEW =============== */
@media (max-width: 600px) {
  .table-container { overflow-x: visible; }
  table, thead, tbody, th, td, tr { display: block; }
  thead { display: none; }

  tr {
    margin-bottom: 15px;
    background: white;
    border-radius: 10px;
    padding: 15px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
  }

  td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: none;
    border-bottom: 1px solid #eee;
    padding: 8px 10px;
    font-size: 14px;
  }

  td:last-child { border-bottom: none; }
  td::before {
    content: attr(data-label);
    font-weight: bold;
    color: #333;
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
  <a href="approve_users.php" class="<?= $current_page=='approve_users.php'?'active':'' ?>">
    Approve Users
    <?php
    $count = $pdo->query("SELECT COUNT(*) FROM users WHERE is_approved = 0")->fetchColumn();
    if($count > 0) echo " ($count)";
    ?>
  </a>
  <a href="manage_schedules.php" class="<?= $current_page=='manage_schedules.php'?'active':'' ?>">Manage Schedules</a>
  <a href="manage_rooms.php" class="<?= $current_page=='manage_rooms.php'?'active':'' ?>">Manage Rooms</a>
  <a href="manage_courses.php" class="<?= $current_page=='manage_courses.php'?'active':'' ?>">Manage Courses</a>
  <a href="manage_announcements.php" class="<?= $current_page=='manage_announcements.php'?'active':'' ?>">Manage Announcements</a>
  <a href="../logout.php">Logout</a>
</div>

<div class="container">
  <h1>Pending User Approvals</h1>

  <?php if(count($pending_users) === 0): ?>
    <p>No pending users.</p>
  <?php else: ?>
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Email</th>
          <th>ID Number</th>
          <th>Role</th>
          <th>Department</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($pending_users as $user): ?>
        <tr>
          <td data-label="ID"><?= htmlspecialchars($user['user_id']); ?></td>
          <td data-label="Username"><?= htmlspecialchars($user['username']); ?></td>
          <td data-label="Email"><?= htmlspecialchars($user['email']); ?></td>
          <td data-label="ID Number"><?= htmlspecialchars($user['id_number']); ?></td>
          <td data-label="Role"><?= htmlspecialchars($user['role']); ?></td>
          <td data-label="Department"><?= htmlspecialchars($user['department_name']); ?></td>
          <td data-label="Action"><a href="?approve=<?= $user['user_id']; ?>"><button>Approve</button></a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("active");
}
</script>

</body>
</html>
