<?php
// Highlight active page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
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
