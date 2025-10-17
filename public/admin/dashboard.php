<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only allow admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit;
}

// Fetch current user info
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id=?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch();
$profile_img_path = !empty($current_user['profile_picture']) && file_exists(__DIR__.'/../../uploads/profiles/'.$current_user['profile_picture'])
    ? '../../uploads/profiles/'.$current_user['profile_picture']
    : '../assets/default_profile.png';

// Quick stats
$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$total_faculty  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='faculty'")->fetchColumn();
$total_courses  = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$total_rooms    = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="../assets/style.css">
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
}
.sidebar.hidden { transform:translateX(-260px); }
.sidebar a { display:block; padding:12px 20px; color:#fff; text-decoration:none; transition: background 0.3s; }
.sidebar a:hover, .sidebar a.active { background:#1abc9c; }

/* ================= Overlay ================= */
.overlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.4); z-index:1050;
    display:none; opacity:0; transition: opacity 0.3s ease;
}
.overlay.active { display:block; opacity:1; }

/* ================= Main content ================= */
.main-content {
    padding:30px 50px;
    min-height:100vh;
    background:#ffffff;
    transition: all 0.3s ease;
}

/* Welcome section */
.welcome-section {
    background: linear-gradient(135deg,#6366f1,#3b82f6);
    color:white;
    padding:30px 25px;
    border-radius:15px;
    margin-bottom:30px;
    box-shadow:0 6px 18px rgba(0,0,0,0.1);
}
.welcome-section h1 { font-size:28px; font-weight:600; margin-bottom:8px; }
.welcome-section p { font-size:16px; opacity:0.9; }

/* Management Cards */
.management-cards { display:flex; flex-wrap:wrap; gap:20px; margin-bottom:30px; }
.management-cards .card {
    flex:1 1 220px;
    background:#f3f4f6;
    border-radius:15px;
    padding:25px 20px;
    box-shadow:0 6px 20px rgba(0,0,0,0.08);
    display:flex; flex-direction:column; justify-content:space-between;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.management-cards .card:hover { transform:translateY(-6px); box-shadow:0 10px 28px rgba(0,0,0,0.15); }
.card-icon { font-size:40px; margin-bottom:15px; padding:15px; width:60px; height:60px; display:flex; align-items:center; justify-content:center; border-radius:50%; background:#e0e7ff; color:#4f46e5; }
.card h3 { font-size:18px; margin-bottom:8px; color:#111827; font-weight:600; }
.card p { font-size:24px; font-weight:bold; color:#1f2937; margin-bottom:15px; }
.card a { display:inline-block; text-decoration:none; color:white; background-color:#4f46e5; padding:10px 18px; border-radius:8px; font-size:14px; font-weight:500; text-align:center; transition: background-color 0.3s, transform 0.2s; }
.card a:hover { background-color:#3b36c4; transform:translateY(-2px); }

/* ================= Responsive ================= */
@media(max-width:768px){
    .topbar { display:flex; }
    .sidebar { transform:translateX(-100%); }
    .sidebar.active { transform:translateX(0); }
    .main-content { margin-left:0; padding-top:80px; }
}
</style>
</head>
<body>

<!-- Topbar with Hamburger -->
<div class="topbar">
    <button class="menu-btn" onclick="toggleSidebar()">☰</button>
    <span>Admin Dashboard</span>
</div>

<!-- Overlay for mobile -->
<div class="overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<?php include 'sidebar.php'; ?>

<!-- Main content -->
<div class="main-content">
    <!-- Welcome -->
    <div class="welcome-section">
        <h1>Welcome, <?= htmlspecialchars($current_user['username']); ?> 👋</h1>
        <p>This is your admin dashboard. Manage users, courses, rooms, and announcements here.</p>
    </div>

    <!-- Management Cards -->
    <div class="management-cards">
        <div class="card">
            <div class="card-icon">👨‍🎓</div>
            <h3>Users</h3>
            <p><?= $total_students ?></p>
            <a href="manage_users.php">Manage Users</a>
        </div>
        <div class="card">
            <div class="card-icon">👩‍🏫</div>
            <h3>Approve</h3>
            <p><?= $total_faculty ?></p>
            <a href="approve_users.php">Approve Users</a>
        </div>
        <div class="card">
            <div class="card-icon">📚</div>
            <h3>Courses</h3>
            <p><?= $total_courses ?></p>
            <a href="manage_courses.php">Manage Courses</a>
        </div>
        <div class="card">
            <div class="card-icon">🏫</div>
            <h3>Rooms</h3>
            <p><?= $total_rooms ?></p>
            <a href="manage_rooms.php">Manage Rooms</a>
        </div>
        <div class="card">
            <div class="card-icon">📢</div>
            <h3>Announcements</h3>
            <p>View / Post</p>
            <a href="manage_announcements.php">Manage Announcements</a>
        </div>
    </div>
</div>

<script>
function toggleSidebar(){
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}
</script>

</body>
</html>
