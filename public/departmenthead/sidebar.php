<?php
// Fetch dept head info for sidebar
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch();

// Determine profile picture path
$profile_path = '../../uploads/profiles/' . ($user['profile_picture'] ?? '');
if (!empty($user['profile_picture']) && file_exists($profile_path)) {
    $profile_src = $profile_path;
} else {
    $profile_src = '../assets/default_profile.png'; // default image
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
/* Sidebar Styling */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 240px;
    height: 100%;
    background: #2c3e50;
    color: #fff;
    padding-top: 20px;
    font-family: Arial, sans-serif;
    box-shadow: 2px 0 6px rgba(0,0,0,0.2);
}

.sidebar h2 {
    text-align: center;
    margin-bottom: 20px;
    font-size: 18px;
    color: #ecf0f1;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    padding-bottom: 10px;
}

.sidebar a {
    display: block;
    color: #fff;
    padding: 12px 20px;
    text-decoration: none;
    margin-bottom: 5px;
    transition: background 0.3s;
}

.sidebar a.active,
.sidebar a:hover {
    background: #1abc9c;
}

.sidebar-profile {
    text-align: center;
    margin-bottom: 20px;
    padding: 0 10px;
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

/* Make sure main content shifts correctly */
.main-content {
    margin-left: 240px;
    padding: 30px;
    background: #f3f4f6;
    min-height: 100vh;
}

/* Responsive Sidebar */
@media screen and (max-width: 768px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }
    .main-content {
        margin-left: 0;
    }
}
</style>

<div class="sidebar">
    <div class="sidebar-profile">
        <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile Picture">
        <p><?= htmlspecialchars($user['username']); ?></p>
    </div>
    <h2>Dept Head Panel</h2>
    <a href="departmenthead_dashboard.php" class="<?= $current_page=='departmenthead_dashboard.php'?'active':'' ?>">Dashboard</a>
    <a href="manage_enrollments.php" class="<?= $current_page=='manage_enrollments.php'?'active':'' ?>">Manage Enrollments</a>
    <a href="manage_schedules.php" class="<?= $current_page=='manage_schedules.php'?'active':'' ?>">Manage Schedules</a>
    <a href="assign_courses.php" class="<?= $current_page=='assign_courses.php'?'active':'' ?>">Assign Courses</a>
    <a href="add_courses.php" class="<?= $current_page=='add_courses.php'?'active':'' ?>">Add Courses</a>
    <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">Edit Profile</a>
    <a href="manage_announcements.php" class="<?= $current_page=='manage_announcements.php'?'active':'' ?>">Announcements</a>
    <a href="../logout.php">Logout</a>
</div>
