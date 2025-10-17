<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit;
}

// Handle new announcement
if(isset($_POST['post_announcement'])){
    $title = $_POST['title'] ?? '';
    $message = $_POST['message'] ?? '';
    $attachment = $_FILES['attachment']['name'] ?? '';

    if($title && $message){
        if($attachment){
            move_uploaded_file($_FILES['attachment']['tmp_name'], __DIR__.'/../../uploads/'.$attachment);
        }
        $stmt = $pdo->prepare("INSERT INTO announcements (title, message, attachment, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$title, $message, $attachment, $_SESSION['user_id']]);
        header("Location: manage_announcements.php");
        exit;
    }
}

// Handle deletion
if(isset($_POST['delete_announcement'])){
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id=?");
    $stmt->execute([$_POST['announcement_id']]);
    header("Location: manage_announcements.php");
    exit;
}

// Handle update
$edit_announcement = null;
if(isset($_GET['edit'])){
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE announcement_id=?");
    $stmt->execute([$_GET['edit']]);
    $edit_announcement = $stmt->fetch(PDO::FETCH_ASSOC);
}

if(isset($_POST['edit_announcement'])){
    $id = $_POST['announcement_id'];
    $title = $_POST['title'];
    $message = $_POST['message'];
    $attachment = $_FILES['attachment']['name'] ?? '';

    if($title && $message){
        if($attachment){
            move_uploaded_file($_FILES['attachment']['tmp_name'], __DIR__.'/../../uploads/'.$attachment);
            $stmt = $pdo->prepare("UPDATE announcements SET title=?, message=?, attachment=?, updated_at=NOW() WHERE announcement_id=?");
            $stmt->execute([$title, $message, $attachment, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE announcements SET title=?, message=?, updated_at=NOW() WHERE announcement_id=?");
            $stmt->execute([$title, $message, $id]);
        }
        header("Location: manage_announcements.php");
        exit;
    }
}

// Fetch announcements
$announcements = $pdo->query("SELECT a.*, u.username AS author FROM announcements a LEFT JOIN users u ON a.created_by=u.user_id ORDER BY a.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Announcements - DKU Scheduler</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family: Arial, sans-serif; }
body { background:#f3f4f6; }

/* Sidebar */
.sidebar {
    position: fixed; top:0; left:0;
    width: 230px; height: 100vh;
    background:#2c3e50; color:#fff;
    padding-top:20px; display:flex; flex-direction:column; transition:0.3s; z-index:1000;
}
.sidebar h2 { text-align:center; margin-bottom:20px; font-size:22px; }
.sidebar a {
    padding:12px 20px; color:#bdc3c7; text-decoration:none; border-radius:8px; margin-bottom:5px; transition:0.3s;
}
.sidebar a.active, .sidebar a:hover { background:#1abc9c; color:#fff; font-weight:bold; }

/* Topbar for mobile */
.topbar { display:none; background:#2c3e50; color:#fff; padding:15px 20px; justify-content:space-between; align-items:center; position:fixed; top:0; width:100%; z-index:1100; }
.menu-btn { font-size:24px; background:none; border:none; color:white; cursor:pointer; }

/* Main Content */
.main-content { margin-left:230px; padding:30px; transition:0.3s; }
.main-content h1 { font-size:28px; margin-bottom:20px; color:#111827; }

/* Form Styling */
.announcement-form { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px; }
.announcement-form input, .announcement-form textarea { padding:10px; border-radius:8px; border:1px solid #ccc; flex:1 1 150px; min-width:120px; }
.announcement-form button { background:#2563eb; color:#fff; border:none; cursor:pointer; padding:10px 16px; border-radius:8px; transition:0.2s; }
.announcement-form button:hover { background:#1d4ed8; }
.cancel-btn { text-decoration:none; color:#dc2626; padding:10px 16px; border-radius:8px; }

/* Table Styling */
.table-container { width:100%; max-height:400px; overflow-x:auto; overflow-y:auto; -webkit-overflow-scrolling: touch; padding-bottom:10px; background:#fff; border-radius:8px; box-shadow:0 4px 8px rgba(0,0,0,0.05); }
.table-container::-webkit-scrollbar { height:12px; width:12px; }
.table-container::-webkit-scrollbar-track { background:#f3f4f6; border-radius:6px; }
.table-container::-webkit-scrollbar-thumb { background:#888; border-radius:6px; }
.table-container::-webkit-scrollbar-thumb:hover { background:#555; }

.announcement-table { width:100%; border-collapse:collapse; min-width:600px; }
.announcement-table th, .announcement-table td { padding:12px; border-bottom:1px solid #ddd; text-align:left; }
.announcement-table th { background:#34495e; color:#fff; }
.announcement-table tr:nth-child(even){ background:#f2f2f2; }
.announcement-table tr:hover{ background:#e0e0e0; }
.announcement-table button { background:#dc2626; color:#fff; border:none; padding:5px 8px; border-radius:5px; cursor:pointer; }
.announcement-table button:hover { background:#b91c1c; }
.announcement-table a.edit-btn { background:#2563eb; color:#fff; padding:5px 8px; border-radius:5px; text-decoration:none; }
.announcement-table a.edit-btn:hover { background:#1d4ed8; }

/* Responsive */
@media(max-width:768px){
    .sidebar { position:fixed; left:-250px; width:230px; transition:0.3s; }
    .sidebar.active { left:0; }
    .main-content { margin-left:0; padding:20px; }
    .topbar{display:flex;}
    .announcement-form { flex-direction:column; }
    .announcement-form input, .announcement-form textarea, .announcement-form button, .cancel-btn { width:100%; margin:5px 0; }
    .announcement-table, .announcement-table thead, .announcement-table tbody, .announcement-table th, .announcement-table td, .announcement-table tr { display:block; width:100%; }
    .announcement-table thead tr { display:none; }
    .announcement-table tr { margin-bottom:15px; background:#fff; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.1); padding:10px; }
    .announcement-table td { text-align:right; padding-left:50%; position:relative; border:none; }
    .announcement-table td::before { content: attr(data-label); position:absolute; left:15px; width:45%; text-align:left; font-weight:bold; }
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
    <h1>Manage Announcements</h1>

    <!-- Add/Edit Announcement Form -->
    <form method="POST" class="announcement-form" enctype="multipart/form-data">
        <input type="hidden" name="announcement_id" value="<?= $edit_announcement['announcement_id'] ?? '' ?>">
        <input type="text" name="title" placeholder="Announcement Title" required value="<?= htmlspecialchars($edit_announcement['title'] ?? '') ?>">
        <textarea name="message" placeholder="Announcement Message" rows="4" required><?= htmlspecialchars($edit_announcement['message'] ?? '') ?></textarea>
        <input type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.png">
        <button type="submit" name="<?= isset($edit_announcement)? 'edit_announcement':'post_announcement' ?>">
            <?= isset($edit_announcement)? 'Update Announcement':'Post Announcement' ?>
        </button>
        <?php if(isset($edit_announcement)): ?>
            <a href="manage_announcements.php" class="cancel-btn">Cancel</a>
        <?php endif; ?>
    </form>

    <!-- Announcements Table -->
    <div class="table-container">
        <table class="announcement-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($announcements as $a): ?>
                <tr>
                    <td data-label="ID"><?= $a['announcement_id'] ?></td>
                    <td data-label="Title"><?= htmlspecialchars($a['title']) ?></td>
                    <td data-label="Author"><?= htmlspecialchars($a['author'] ?? 'Unknown') ?></td>
                    <td data-label="Date"><?= date('Y-m-d H:i', strtotime($a['created_at'])) ?></td>
                    <td data-label="Actions">
                        <div style="white-space:nowrap;">
                            <a class="edit-btn" href="?edit=<?= $a['announcement_id'] ?>">Edit</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this announcement?')">
                                <input type="hidden" name="announcement_id" value="<?= $a['announcement_id'] ?>">
                                <button type="submit" name="delete_announcement">Delete</button>
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
