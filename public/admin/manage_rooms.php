<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Redirect if not admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit;
}

// Add Room
if(isset($_POST['add_room'])){
    $stmt = $pdo->prepare("INSERT INTO rooms (room_name, capacity, building) VALUES (?, ?, ?)");
    $stmt->execute([
        $_POST['room_name'],
        $_POST['capacity'],
        $_POST['building']
    ]);
    header("Location: manage_rooms.php");
    exit;
}

// Edit Room
if(isset($_POST['edit_room'])){
    $stmt = $pdo->prepare("UPDATE rooms SET room_name=?, capacity=?, building=? WHERE room_id=?");
    $stmt->execute([
        $_POST['room_name'],
        $_POST['capacity'],
        $_POST['building'],
        $_POST['room_id']
    ]);
    header("Location: manage_rooms.php");
    exit;
}

// Delete Room
if(isset($_POST['delete_room'])){
    $stmt = $pdo->prepare("DELETE FROM rooms WHERE room_id=?");
    $stmt->execute([$_POST['room_id']]);
    header("Location: manage_rooms.php");
    exit;
}

// Fetch room to edit
$edit_room = null;
if(isset($_GET['edit'])){
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_id=?");
    $stmt->execute([$_GET['edit']]);
    $edit_room = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch rooms
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Rooms - DKU Scheduler</title>
<style>
/* ===== General Reset ===== */
* { margin:0; padding:0; box-sizing:border-box; font-family: Arial, sans-serif; }
body { background: #f3f4f6; }

/* ===== Sidebar ===== */
.sidebar {
    position: fixed;
    top:0; left:0;
    width: 230px;
    height: 100vh;
    background:#2c3e50;
    color:#fff;
    padding-top:20px;
    display:flex;
    flex-direction:column;
    transition:0.3s;
    z-index:1000;
}
.sidebar h2 { text-align:center; margin-bottom:20px; font-size:22px; }
.sidebar a {
    padding:12px 20px;
    color:#bdc3c7;
    text-decoration:none;
    border-radius:8px;
    margin-bottom:5px;
    transition:0.3s;
}
.sidebar a.active, .sidebar a:hover { background:#1abc9c; color:#fff; font-weight:bold; }

/* ===== Topbar for Mobile ===== */
.topbar { display:none; background:#2c3e50; color:#fff; padding:15px 20px; justify-content:space-between; align-items:center; position:fixed; top:0; width:100%; z-index:1100; }
.menu-btn { font-size:24px; background:none; border:none; color:white; cursor:pointer; }

/* ===== Main Content ===== */
.main-content {
    margin-left:230px;
    padding:30px;
    transition:0.3s;
}
.main-content h1 { font-size:28px; margin-bottom:20px; color:#111827; }

/* ===== Form Styling ===== */
.room-form {
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-bottom:20px;
}
.room-form input {
    padding:10px;
    border-radius:8px;
    border:1px solid #ccc;
    flex:1 1 150px;
    min-width:120px;
}
.room-form button {
    background:#2563eb;
    color:#fff;
    border:none;
    cursor:pointer;
    padding:10px 16px;
    border-radius:8px;
    transition:0.2s;
}
.room-form button:hover { background:#1d4ed8; }
.cancel-btn { text-decoration:none; color:#dc2626; padding:10px 16px; border-radius:8px; }

/* ===== Table Styling ===== */
.table-container {
    width:100%;
    max-height:400px;
    overflow-x:auto;
    overflow-y:auto;
    -webkit-overflow-scrolling: touch;
    padding-bottom:10px;
    background:#fff;
    border-radius:8px;
    box-shadow:0 4px 8px rgba(0,0,0,0.05);
}
.table-container::-webkit-scrollbar { height:12px; width:12px; }
.table-container::-webkit-scrollbar-track { background:#f3f4f6; border-radius:6px; }
.table-container::-webkit-scrollbar-thumb { background:#888; border-radius:6px; }
.table-container::-webkit-scrollbar-thumb:hover { background:#555; }

.room-table {
    width:100%;
    border-collapse:collapse;
    min-width:600px;
}
.room-table th, .room-table td {
    padding:12px;
    border-bottom:1px solid #ddd;
    text-align:left;
}
.room-table th { background:#34495e; color:#fff; }
.room-table tr:nth-child(even){ background:#f2f2f2; }
.room-table tr:hover{ background:#e0e0e0; }
.room-table button { background:#dc2626; color:#fff; border:none; padding:5px 8px; border-radius:5px; cursor:pointer; }
.room-table button:hover { background:#b91c1c; }
.room-table a.edit-btn { background:#2563eb; color:#fff; padding:5px 8px; border-radius:5px; text-decoration:none; }
.room-table a.edit-btn:hover { background:#1d4ed8; }

/* ===== Responsive ===== */
@media(max-width:768px){
    .sidebar { position:fixed; left:-250px; width:230px; transition:0.3s; }
    .sidebar.active { left:0; }
    .main-content { margin-left:0; padding:20px;}
    .topbar{display:flex;}
    .room-form { flex-direction:column; }
    .room-form input, .room-form button, .cancel-btn { width:100%; margin:5px 0; }
    /* Card-style table for mobile */
    .room-table, .room-table thead, .room-table tbody, .room-table th, .room-table td, .room-table tr { display:block; width:100%; }
    .room-table thead tr { display:none; }
    .room-table tr { margin-bottom:15px; background:#fff; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.1); padding:10px;}
    .room-table td { text-align:right; padding-left:50%; position:relative; border:none;}
    .room-table td::before { content: attr(data-label); position:absolute; left:15px; width:45%; text-align:left; font-weight:bold;}
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
    <button class="menu-btn" onclick="toggleSidebar()">☰</button>
    <h2>Admin Panel</h2>
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

<!-- Main Content -->
<div class="main-content">
    <h1>Manage Rooms</h1>

    <!-- Add/Edit Room Form -->
    <form method="POST" class="room-form">
        <input type="hidden" name="room_id" value="<?= $edit_room['room_id'] ?? '' ?>">
        <input type="text" name="room_name" placeholder="Room Name" required value="<?= htmlspecialchars($edit_room['room_name'] ?? '') ?>">
        <input type="number" name="capacity" placeholder="Capacity" min="1" required value="<?= htmlspecialchars($edit_room['capacity'] ?? '') ?>">
        <input type="text" name="building" placeholder="Building" required value="<?= htmlspecialchars($edit_room['building'] ?? '') ?>">
        <button type="submit" name="<?= isset($edit_room) ? 'edit_room' : 'add_room' ?>">
            <?= isset($edit_room) ? 'Update Room' : 'Add Room' ?>
        </button>
        <?php if(isset($edit_room)): ?>
            <a href="manage_rooms.php" class="cancel-btn">Cancel</a>
        <?php endif; ?>
    </form>

    <!-- Rooms Table -->
    <div class="table-container">
        <table class="room-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Room Name</th>
                    <th>Capacity</th>
                    <th>Building</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($rooms as $r): ?>
                <tr>
                    <td data-label="ID"><?= $r['room_id'] ?></td>
                    <td data-label="Room Name"><?= htmlspecialchars($r['room_name']) ?></td>
                    <td data-label="Capacity"><?= htmlspecialchars($r['capacity']) ?></td>
                    <td data-label="Building"><?= htmlspecialchars($r['building']) ?></td>
                    <td data-label="Actions">
                        <div style="white-space:nowrap;">
                            <a class="edit-btn" href="?edit=<?= $r['room_id'] ?>">Edit</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this room?')">
                                <input type="hidden" name="room_id" value="<?= $r['room_id'] ?>">
                                <button type="submit" name="delete_room">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleSidebar(){
    document.getElementById("sidebar").classList.toggle("active");
}
// Close sidebar on mobile click outside
document.addEventListener('click', function(e){
    const sidebar = document.getElementById('sidebar');
    if(window.innerWidth <= 768 && sidebar.classList.contains('active') && !sidebar.contains(e.target) && !e.target.classList.contains('menu-btn')){
        sidebar.classList.remove('active');
    }
});
</script>

</body>
</html>
