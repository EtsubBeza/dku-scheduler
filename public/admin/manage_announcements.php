<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only admin
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
        $success = "Announcement posted successfully.";
    } else {
        $error = "Title and message are required.";
    }
}

// Handle deletion
if(isset($_POST['delete'])){
    $delete_id = intval($_POST['delete_id']);
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id=?");
    $stmt->execute([$delete_id]);
    header("Location: manage_announcements.php");
    exit;
}

// Handle update
if(isset($_POST['update_announcement'])){
    $id = intval($_POST['id'] ?? 0);
    $title = $_POST['title'] ?? '';
    $message = $_POST['message'] ?? '';
    $attachment = $_FILES['attachment']['name'] ?? '';

    if($id && $title && $message){
        if($attachment){
            move_uploaded_file($_FILES['attachment']['tmp_name'], __DIR__.'/../../uploads/'.$attachment);
            $stmt = $pdo->prepare("UPDATE announcements SET title=?, message=?, attachment=?, updated_at=NOW() WHERE announcement_id=?");
            $stmt->execute([$title, $message, $attachment, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE announcements SET title=?, message=?, updated_at=NOW() WHERE announcement_id=?");
            $stmt->execute([$title, $message, $id]);
        }
        $success = "Announcement updated successfully.";
    } else {
        $error = "All fields are required to update.";
    }
}

// Fetch announcements
$announcements_stmt = $pdo->query("
    SELECT a.announcement_id AS id, a.title, a.message AS content, a.attachment, a.created_at, u.username AS author
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.user_id
    ORDER BY a.created_at DESC
");
$announcements = $announcements_stmt->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel - Manage Announcements</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;}
body{background:#f3f4f6;}

/* ================= Topbar ================= */
.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    position:fixed; top:0; left:0; width:100%;
    background:#2c3e50;
    color:#fff;
    padding:12px 20px;
    z-index:1200;
    height:60px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.topbar span {
    font-size:18px;
    font-weight:600;
}

/* Hamburger button */
.menu-btn{
    font-size:26px;
    background:#1abc9c;
    border:none;
    color:#fff;
    cursor:pointer;
    padding:8px 12px;
    border-radius:8px;
    font-weight:600;
    transition: background 0.3s, transform 0.2s;
}
.menu-btn:hover{ background:#159b81; transform:translateY(-2px); }

/* ================= Sidebar ================= */
.sidebar{
    position:fixed; top:0; left:0;
    width:250px; height:100%;
    background:#1f2937;
    color:#fff;
    padding-top:60px;
    transition: transform 0.3s ease;
    z-index:1100;
}
.sidebar a{
    display:block; padding:12px 20px;
    color:#fff; text-decoration:none; transition:0.3s;
}
.sidebar a:hover, .sidebar a.active{ background:#1abc9c; }

/* ================= Overlay ================= */
.overlay{
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.4); z-index:1050;
    display:none; opacity:0; transition: opacity 0.3s ease;
}
.overlay.active{ display:block; opacity:1; }

/* ================= Main content ================= */
.main-content{
    margin-left:250px;
    padding:80px 50px 50px 50px;
    transition: margin-left 0.3s ease;
}

/* Card */
.card{
    background:#fff; padding:25px; border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.05);
    margin-bottom:30px;
}

/* Form */
form input, form textarea{width:100%;padding:12px;margin-bottom:15px;border-radius:8px;border:1px solid #d1d5db;font-size:14px;}
form button{background:#4f46e5;color:#fff;padding:12px 20px;border:none;border-radius:8px;cursor:pointer;font-weight:600;transition:0.3s;}
form button:hover{background:#3b36c4;}

/* Alerts */
.alert{padding:14px 18px;margin-bottom:25px;border-radius:8px;font-size:14px;}
.alert-success{background:#d1fae5;color:#065f46;}
.alert-error{background:#fee2e2;color:#991b1b;}

/* Table */
.table-wrapper{overflow-x:auto;position:relative;}
table{width:100%;border-collapse:collapse;font-size:14px;}
thead th{position:sticky;top:0;background:#f9fafb;z-index:2;padding:14px;font-weight:600;border-bottom:1px solid #e5e7eb;}
table th,table td{padding:12px;text-align:left;border-bottom:1px solid #e5e7eb;}
table tr:hover{background:#f1f5f9;}
button.delete-btn, button.view-btn, button.edit-btn{padding:6px 14px;border-radius:6px;color:#fff;border:none;font-size:12px;margin-right:6px;cursor:pointer;}
button.delete-btn{background:#ef4444;}
button.view-btn{background:#4f46e5;}
button.edit-btn{background:#1abc9c;}
button.view-btn:hover{background:#3b36c4;}
button.edit-btn:hover{background:#159b81;}

/* Modal */
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);justify-content:center;align-items:center;z-index:400;}
.modal-content{background:#fff;padding:25px;border-radius:12px;width:90%;max-width:550px;position:relative;}
.modal-close{position:absolute;top:10px;right:15px;font-size:22px;font-weight:bold;cursor:pointer;color:#4f46e5;}

/* ================= Responsive ================= */
@media(max-width:768px){
    .sidebar{transform:translateX(-100%);}
    .sidebar.active{transform:translateX(0);}
    .main-content{margin-left:0; padding:100px 20px 20px 20px;}
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
    <button class="menu-btn" onclick="toggleSidebar()">☰</button>
    <span>Admin Panel</span>
</div>

<!-- Overlay -->
<div class="overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<div class="sidebar">
    <a href="dashboard.php" class="<?= $current_page=='dashboard.php'?'active':'' ?>">Dashboard</a>
    <a href="manage_users.php" class="<?= $current_page=='manage_users.php'?'active':'' ?>">Manage Users</a>
    <a href="approve_users.php" class="<?= $current_page=='approve_users.php'?'active':'' ?>">Approve Users</a>
    <a href="manage_schedules.php" class="<?= $current_page=='manage_schedules.php'?'active':'' ?>">Manage Schedules</a>
    <a href="manage_rooms.php" class="<?= $current_page=='manage_rooms.php'?'active':'' ?>">Manage Rooms</a>
    <a href="manage_courses.php" class="<?= $current_page=='manage_courses.php'?'active':'' ?>">Manage Courses</a>
    <a href="manage_announcements.php" class="<?= $current_page=='manage_announcements.php'?'active':'' ?>">Manage Announcements</a>
    <a href="../logout.php">Logout</a>
</div>

<!-- Main content -->
<div class="main-content">

<?php if(!empty($success)) echo "<div class='alert alert-success'>{$success}</div>"; ?>
<?php if(!empty($error)) echo "<div class='alert alert-error'>{$error}</div>"; ?>

<!-- Post Announcement Form -->
<div class="card">
    <h2 style="margin-bottom:20px;">Post New Announcement</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="Announcement Title" required>
        <textarea name="message" rows="4" placeholder="Announcement Content" required></textarea>
        <input type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.png">
        <button type="submit" name="post_announcement">Post Announcement</button>
    </form>
</div>

<!-- Announcements Table -->
<div class="card table-wrapper">
    <h2 style="margin-bottom:20px;">All Announcements</h2>
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Author</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($announcements as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['title']); ?></td>
                <td><?= htmlspecialchars($a['author'] ?? 'Unknown'); ?></td>
                <td><?= isset($a['created_at']) ? date('Y-m-d H:i', strtotime($a['created_at'])) : ''; ?></td>
                <td>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this announcement?');">
                        <input type="hidden" name="delete_id" value="<?= $a['id'] ?? 0; ?>">
                        <button type="submit" name="delete" class="delete-btn">Delete</button>
                    </form>
                    <button class="view-btn" onclick="openModal('view', <?= $a['id'] ?>)">View</button>
                    <button class="edit-btn" onclick="openModal('edit', <?= $a['id'] ?>)">Edit</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</div>

<!-- Modal -->
<div class="modal" id="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">×</span>
        <div id="modal-body"></div>
    </div>
</div>

<script>
const announcements = <?php echo json_encode($announcements); ?>;
const modal = document.getElementById('modal');
const modalBody = document.getElementById('modal-body');

function openModal(type, id){
    const a = announcements.find(x=>x.id==id);
    if(!a) return;
    if(type==='view'){
        modalBody.innerHTML = `<h2>${a.title}</h2>
        <p><strong>Author:</strong> ${a.author}</p>
        <p><strong>Date:</strong> ${a.created_at}</p>
        <hr>
        <p>${(a.content ?? '').replace(/\n/g,'<br>')}</p>
        ${a.attachment ? `<p><strong>Attachment:</strong> 
        <a href="download.php?file=${encodeURIComponent(a.attachment)}">${a.attachment}</a></p>` : ''}`;
    } else if(type==='edit'){
        modalBody.innerHTML = `<h2>Edit Announcement</h2>
        <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="${a.id}">
        <input type="text" name="title" value="${a.title}" required>
        <textarea name="message" rows="4" required>${a.content}</textarea>
        <input type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.png">
        <button type="submit" name="update_announcement">Update</button>
        </form>`;
    }
    modal.style.display='flex';
}

function closeModal(){ modal.style.display='none'; }

function toggleSidebar(){
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.overlay').classList.toggle('active');
}

window.onclick = e => { if(e.target==modal) closeModal(); }
</script>

</body>
</html>
