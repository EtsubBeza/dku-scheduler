<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// Fetch student info
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$student_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch announcements (department-wide or global)
$announcements_stmt = $pdo->prepare("
    SELECT a.*, u.username AS creator_name,
           (SELECT COUNT(*) FROM announcement_likes l WHERE l.announcement_id = a.announcement_id) AS like_count,
           (SELECT COUNT(*) FROM announcement_likes l WHERE l.announcement_id = a.announcement_id AND l.user_id = ?) AS liked_by_user
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.user_id
    WHERE a.department_id IS NULL OR a.department_id = (
        SELECT department_id FROM users WHERE user_id = ?
    )
    ORDER BY a.created_at DESC
");
$announcements_stmt->execute([$student_id, $student_id]);
$announcements = $announcements_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Announcements</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
body {font-family: Arial,sans-serif; display:flex; background:#f3f4f6; min-height:100vh; margin:0;}
.sidebar {position:fixed; top:0; left:0; width:240px; height:100vh; background:#2c3e50; padding-top:20px; color:#fff; box-shadow:2px 0 10px rgba(0,0,0,0.2); overflow-y:auto;}
.sidebar h2 {text-align:center;margin-bottom:20px;}
.sidebar a {display:block;padding:12px 20px;text-decoration:none;color:#fff;margin:5px 0;border-radius:5px;}
.sidebar a.active, .sidebar a:hover {background-color: #1abc9c;color:#fff;font-weight:bold;}
.main-content {margin-left:240px;padding:30px;flex-grow:1;}
.announcement-card {background:#fff;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);padding:20px;margin-bottom:20px;transition:transform 0.2s;}
.announcement-card:hover {transform:translateY(-3px);}
.announcement-card h3 {margin-bottom:8px;color:#111827;}
.announcement-card p.message {margin-bottom:12px;color:#374151;}
.attachment-image, .attachment-video {
    max-width:300px;
    max-height:200px;
    width:100%;
    height:auto;
    margin:10px 0;
    border-radius:8px;
    object-fit:cover;
    cursor:pointer;
    transition: transform 0.2s;
}
.attachment-image:hover, .attachment-video:hover {transform: scale(1.05);}
.announcement-meta {font-size:12px;color:#6b7280;margin-bottom:10px;}
.like-btn {cursor:pointer;background:none;border:none;color:#ef4444;font-size:16px;margin-right:10px;}
.comment-section {margin-top:15px;}
.comment {padding:8px 12px;background:#f3f4f6;margin-bottom:6px;border-radius:8px;}
.add-comment-form {display:flex;gap:8px;margin-top:8px;}
.add-comment-form input {flex:1;padding:6px 10px;border-radius:8px;border:1px solid #d1d5db;}
.add-comment-form button {padding:6px 12px;border:none;background:#3b82f6;color:#fff;border-radius:8px;cursor:pointer;}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h2>Student Panel</h2>
    <a href="student_dashboard.php">Dashboard</a>
    <a href="my_schedule.php">My Schedule</a>
    <a href="view_announcements.php" class="active">Announcements</a>
    <a href="edit_profile.php">Edit Profile</a>
    <a href="../logout.php">Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <h1>Announcements</h1>

    <?php if($announcements): ?>
        <?php foreach($announcements as $a): ?>
            <div class="announcement-card" data-id="<?= $a['announcement_id'] ?>">
                <h3><?= htmlspecialchars($a['title']) ?></h3>
                <div class="announcement-meta">
                    Posted by <?= htmlspecialchars($a['creator_name']) ?> on <?= date('M d, Y H:i', strtotime($a['created_at'])) ?>
                </div>
                <p class="message"><?= nl2br(htmlspecialchars($a['message'])) ?></p>

                <!-- Attachments -->
                <?php if(!empty($a['attachment'])): ?>
                    <?php
                    $ext = pathinfo($a['attachment'], PATHINFO_EXTENSION);
                    if(in_array(strtolower($ext), ['jpg','jpeg','png','gif'])): ?>
                        <img src="../../uploads/announcements/<?= htmlspecialchars($a['attachment']) ?>" class="attachment-image">
                    <?php elseif(in_array(strtolower($ext), ['mp4','webm','ogg'])): ?>
                        <video src="../../uploads/announcements/<?= htmlspecialchars($a['attachment']) ?>" controls class="attachment-video"></video>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if(!empty($a['external_link'])): ?>
                    <p><a href="<?= htmlspecialchars($a['external_link']) ?>" target="_blank">External Link</a></p>
                <?php endif; ?>

                <!-- Like Button -->
                <button class="like-btn" data-id="<?= $a['announcement_id'] ?>">
                    <?= $a['liked_by_user'] ? '❤️' : '🤍' ?> <span class="like-count"><?= $a['like_count'] ?></span>
                </button>

                <!-- Comments -->
                <div class="comment-section" id="comments-<?= $a['announcement_id'] ?>"></div>
                <form class="add-comment-form" data-id="<?= $a['announcement_id'] ?>">
                    <input type="text" name="comment" placeholder="Add a comment..." required>
                    <button type="submit">Post</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No announcements found.</p>
    <?php endif; ?>
</div>

<script>
// Load comments for each announcement
function loadComments(aid){
    fetch(`../common/load_comments.php?announcement_id=${aid}`)
    .then(res => res.json())
    .then(data => {
        const container = document.getElementById(`comments-${aid}`);
        container.innerHTML = '';
        data.forEach(c=>{
            const div = document.createElement('div');
            div.className='comment';
            div.textContent=`${c.username}: ${c.comment}`;
            container.appendChild(div);
        });
    });
}

// Initial load
document.querySelectorAll('.announcement-card').forEach(card=>{
    const aid = card.dataset.id;
    loadComments(aid);
});

// Like button toggle
document.querySelectorAll('.like-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
        const aid = btn.dataset.id;
        fetch(`../common/like_announcement.php?announcement_id=${aid}`)
        .then(res=>res.json())
        .then(data=>{
            btn.querySelector('.like-count').textContent = data.like_count;
            btn.textContent = data.liked_by_user ? '❤️ ' + data.like_count : '🤍 ' + data.like_count;
        });
    });
});

// Add comment
document.querySelectorAll('.add-comment-form').forEach(form=>{
    form.addEventListener('submit', e=>{
        e.preventDefault();
        const aid = form.dataset.id;
        const comment = form.comment.value;
        fetch('../common/add_comment.php',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`announcement_id=${aid}&comment=${encodeURIComponent(comment)}`
        })
        .then(res=>res.json())
        .then(data=>{
            form.comment.value='';
            loadComments(aid);
        });
    });
});
</script>
</body>
</html>
