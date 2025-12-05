<?php
// Fetch student info for sidebar
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Determine profile picture path
$profile_path = __DIR__ . '/../../uploads/profiles/' . ($user['profile_picture'] ?? '');
$profile_src = (isset($user['profile_picture']) && file_exists($profile_path)) 
    ? '../../uploads/profiles/' . $user['profile_picture'] 
    : '../assets/default_profile.png';

$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 240px;
    height: 100%;
    background: rgba(44, 62, 80, 0.95);
    color: #fff;
    padding-top: 20px;
    font-family: Arial, sans-serif;
    box-shadow: 2px 0 10px rgba(0,0,0,0.2);
    overflow-y: auto;
    z-index: 1000;
}
.sidebar h2 {
    text-align: center;
    color: #ecf0f1;
    margin-bottom: 25px;
    font-size: 22px;
}
.sidebar a {
    display: block;
    color: #bdc3c7;
    padding: 12px 20px;
    text-decoration: none;
    margin: 3px 0;
    border-radius: 6px;
    transition: background 0.3s, color 0.3s;
}
.sidebar a.active, .sidebar a:hover {
    background: #34495e;
    color: #fff;
    font-weight: bold;
}
.sidebar-profile {
    text-align: center;
    margin-bottom: 20px;
}
.sidebar-profile img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #1abc9c;
    margin-bottom: 10px;
}
.sidebar-profile p {
    color: #fff;
    font-weight: bold;
    margin: 0;
}
@media screen and (max-width: 768px) {
    .sidebar { position: relative; width: 100%; height: auto; }
}
</style>

<div class="sidebar">
    <div class="sidebar-profile">
        <img src="<?= htmlspecialchars($profile_src) ?>" alt="Profile Picture">
        <p><?= htmlspecialchars($user['username']); ?></p>
    </div>
    <h2>Student Panel</h2>
    <a href="student_dashboard.php" class="<?= $current_page=='student_dashboard.php'?'active':'' ?>">Dashboard</a>
    <a href="my_schedule.php" class="<?= $current_page=='my_schedule.php'?'active':'' ?>">My Schedule</a>
    <a href="view_exam_schedules.php" class="<?= $current_page=='view_exam_schedules.php'?'active':'' ?>">Exam Schedule</a>
    <a href="view_announcements.php" class="<?= $current_page=='view_announcements.php'?'active':'' ?>">Announcements</a>
    <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">Edit Profile</a>
    <a href="../logout.php">Logout</a>
</div>
