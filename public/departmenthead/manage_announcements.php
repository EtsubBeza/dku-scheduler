<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only allow department head
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_head'){
    header("Location: ../index.php");
    exit;
}

$dept_id = $_SESSION['department_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// ------------------ Handle Create/Edit Announcement ------------------
$message = "";
$editing = false;
$edit_announcement = [];

if(isset($_GET['edit'])){
    $ann_id = (int)$_GET['edit'];
    $edit_stmt = $pdo->prepare("SELECT * FROM announcements WHERE announcement_id=? AND created_by=?");
    $edit_stmt->execute([$ann_id, $user_id]);
    $edit_announcement = $edit_stmt->fetch(PDO::FETCH_ASSOC);
    if($edit_announcement){
        $editing = true;
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['message']) && !isset($_POST['ajax_action'])){
    // Normal create / update (not AJAX)
    $title = trim($_POST['title']);
    $content = trim($_POST['message']);
    $external_link = trim($_POST['external_link'] ?? '');
    $attachment_name = $editing ? $edit_announcement['attachment'] : null;

    if(isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK){
        $file_tmp = $_FILES['attachment']['tmp_name'];
        $file_name = basename($_FILES['attachment']['name']);
        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $allowed = ['pdf','doc','docx','jpg','jpeg','png','mp4','mov','avi'];
        if(in_array(strtolower($ext), $allowed)){
            $new_name = time().'_'.$file_name;
            if(!is_dir(__DIR__.'/../../uploads/announcements')) {
                @mkdir(__DIR__.'/../../uploads/announcements', 0755, true);
            }
            move_uploaded_file($file_tmp, __DIR__.'/../../uploads/announcements/'.$new_name);
            $attachment_name = $new_name;
        }
    }

    if($title && $content){
        if($editing){
            $stmt = $pdo->prepare("UPDATE announcements SET title=?, message=?, attachment=?, external_link=?, updated_at = NOW() WHERE announcement_id=? AND created_by=?");
            $stmt->execute([$title, $content, $attachment_name, $external_link, $edit_announcement['announcement_id'], $user_id]);
            header("Location: manage_announcements.php");
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, message, created_by, department_id, attachment, external_link, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $content, $user_id, $dept_id, $attachment_name, $external_link]);
            $message = "Announcement posted successfully!";
        }
    } else {
        $message = "Please fill in both title and message.";
    }
}

// ------------------ Handle Delete announcement ------------------
if(isset($_GET['delete'])){
    $ann_id = (int)$_GET['delete'];
    $del_stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id=? AND created_by=?");
    $del_stmt->execute([$ann_id, $user_id]);
    header("Location: manage_announcements.php");
    exit;
}

// ------------------ Fetch all announcements with likes and comment counts ------------------
$announcements_stmt = $pdo->prepare("
    SELECT a.*, u.username,
           (SELECT COUNT(*) FROM announcement_likes l WHERE l.announcement_id = a.announcement_id) AS like_count,
           (SELECT GROUP_CONCAT(u2.username SEPARATOR ', ') 
            FROM announcement_likes l2 
            JOIN users u2 ON l2.user_id = u2.user_id 
            WHERE l2.announcement_id = a.announcement_id) AS liked_users,
           (SELECT COUNT(*) FROM announcement_comments c WHERE c.announcement_id = a.announcement_id) AS comment_count
    FROM announcements a
    JOIN users u ON a.created_by = u.user_id
    WHERE a.department_id = ? OR a.department_id IS NULL
    ORDER BY a.created_at DESC
");
$announcements_stmt->execute([$dept_id]);
$announcements = $announcements_stmt->fetchAll(PDO::FETCH_ASSOC);
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Announcements</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
/* Layout */
.main-content { margin-left: 240px; padding: 30px; background:#f9fafb; min-height:100vh; font-family:'Segoe UI', sans-serif;}
h1 { margin-bottom: 20px; }

/* Form */
.announcement-form input, .announcement-form textarea, .announcement-form input[type="url"], .announcement-form input[type="file"] { width: 100%; padding: 10px; margin-bottom: 10px; border-radius:6px; border:1px solid #ccc; }
.announcement-form button { padding: 10px 20px; background-color: #4f46e5; color: #fff; border:none; border-radius:6px; cursor:pointer; }
.announcement-form button:hover { background-color: #3b36c4; }

/* Table */
.ann-table { width:100%; border-collapse: collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.05);}
.ann-table th, .ann-table td { padding:12px 14px; border-bottom:1px solid #f1f5f9; text-align:left; vertical-align: middle;}
.ann-table th { background:#f8fafc; font-weight:600; color:#111827; }
.ann-table tr:hover { background:#fcfcfe; }

/* Action links */
.btn { display:inline-block; padding:6px 10px; border-radius:6px; text-decoration:none; cursor:pointer; }
.btn-view { background:#06b6d4; color:#fff; }
.btn-edit { background:#10b981; color:#fff; }
.btn-delete { background:#ef4444; color:#fff; }

/* Details row */
.details-row td { background:#ffffff; padding:18px; }
.details-content { display:flex; gap:20px; align-items:flex-start; }
.details-left { flex:1; }
.details-right { width:320px; }

/* Like button */
.like-btn { background:none; border:none; cursor:pointer; font-size:16px; }

/* Comments */
.comment-section { margin-top:10px; background:#f3f4f6; padding:8px; border-radius:8px; max-height:220px; overflow-y:auto; }
.comment { margin-bottom:8px; padding:8px; background:#eef2ff; border-radius:6px; display:flex; justify-content:space-between; align-items:center; }
.comment .meta { font-size:12px; color:#334155; }
.comment button.delete-comment { background:none; border:none; color:#ef4444; cursor:pointer; font-weight:bold; }

/* add-comment form */
.add-comment-form { display:flex; gap:8px; margin-top:8px; }
.add-comment-form input { flex:1; padding:8px; border-radius:6px; border:1px solid #d1d5db; }
.add-comment-form button { padding:8px 12px; border:none; background:#3b82f6; color:#fff; border-radius:6px; cursor:pointer; }

/* small badges */
.badge { display:inline-block; padding:3px 8px; border-radius:999px; background:#eef2ff; font-size:12px; color:#0f172a; margin-right:6px; }
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <h1><?= $editing ? "Edit Announcement" : "Post New Announcement" ?></h1>
    <form method="post" class="announcement-form" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="Title" value="<?= $editing ? htmlspecialchars($edit_announcement['title']) : '' ?>" required>
        <textarea name="message" placeholder="Message" rows="4" required><?= $editing ? htmlspecialchars($edit_announcement['message']) : '' ?></textarea>
        <label>Attach a File:</label>
        <input type="file" name="attachment">
        <?php if($editing && $edit_announcement['attachment']): ?>
            <p>Current: <a href="../../uploads/announcements/<?= htmlspecialchars($edit_announcement['attachment']) ?>" target="_blank"><?= htmlspecialchars($edit_announcement['attachment']) ?></a></p>
        <?php endif; ?>
        <label>External Link / Video URL:</label>
        <input type="url" name="external_link" placeholder="https://example.com" value="<?= $editing ? htmlspecialchars($edit_announcement['external_link']) : '' ?>">
        <button type="submit"><?= $editing ? "Update Announcement" : "Post Announcement" ?></button>
    </form>
    <?php if(!$editing && $message): ?><p style="color:green;"><?= htmlspecialchars($message) ?></p><?php endif; ?>

    <h1>All Announcements</h1>

    <?php if($announcements): ?>
    <table class="ann-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Posted By</th>
                <th>Date</th>
                <th>Likes</th>
                <th>Comments</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($announcements as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['title']) ?></td>
                <td><?= htmlspecialchars($a['username']) ?></td>
                <td><?= date('M d, Y H:i', strtotime($a['created_at'])) ?></td>
                <td><span class="badge like-count-badge" data-ann="<?= $a['announcement_id'] ?>"><?= (int)$a['like_count'] ?></span></td>
                <td><span class="badge comment-count-badge" data-ann="<?= $a['announcement_id'] ?>"><?= (int)$a['comment_count'] ?></span></td>
                <td>
                    <button class="btn btn-view" data-id="<?= $a['announcement_id'] ?>">View</button>
                    <?php if($a['created_by'] == $user_id): ?>
                        <a class="btn btn-edit" href="?edit=<?= $a['announcement_id'] ?>">Edit</a>
                        <a class="btn btn-delete" href="?delete=<?= $a['announcement_id'] ?>" onclick="return confirm('Delete this announcement?')">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>

            <!-- Details row (collapsed by default) -->
            <tr class="details-row" id="details-<?= $a['announcement_id'] ?>" style="display:none;">
                <td colspan="6">
                    <div class="details-content">
                        <div class="details-left">
                            <h3><?= htmlspecialchars($a['title']) ?></h3>
                            <div class="announcement-meta">Posted by <?= htmlspecialchars($a['username']) ?> on <?= date('M d, Y H:i', strtotime($a['created_at'])) ?></div>
                            <p><?= nl2br(htmlspecialchars($a['message'])) ?></p>

                            <?php if($a['attachment']): ?>
                                <p><a href="../../uploads/announcements/<?= htmlspecialchars($a['attachment']) ?>" target="_blank">Download attachment</a></p>
                            <?php endif; ?>

                            <?php if($a['external_link']): ?>
                                <p><a href="<?= htmlspecialchars($a['external_link']) ?>" target="_blank">External link</a></p>
                            <?php endif; ?>

                            <!-- Like / Likes info -->
                            <div style="margin-top:10px;">
                                <button class="like-btn" data-id="<?= $a['announcement_id'] ?>">
                                    <?= $a['like_count'] > 0 ? '❤️' : '🤍' ?> <span class="like-count-inline"><?= (int)$a['like_count'] ?></span>
                                </button>
                                <span style="margin-left:8px; font-size:13px; color:#475569;">
                                    <small class="liked-users"><?= htmlspecialchars($a['liked_users'] ?: 'No likes yet') ?></small>
                                </span>
                            </div>

                            <!-- Comments -->
                            <div class="comment-section" id="comments-<?= $a['announcement_id'] ?>"></div>

                            <form class="add-comment-form" data-id="<?= $a['announcement_id'] ?>">
                                <input type="text" name="comment" placeholder="Add a comment..." required>
                                <button type="submit">Post</button>
                            </form>
                        </div>

                        <div class="details-right">
                            <!-- Additional quick actions or meta can go here -->
                            <p><strong>Meta</strong></p>
                            <p>Announcement ID: <?= (int)$a['announcement_id'] ?></p>
                            <p>Department: <?= htmlspecialchars($a['department_id'] ?? 'Global') ?></p>
                        </div>
                    </div>
                </td>
            </tr>

        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>No announcements found.</p>
    <?php endif; ?>
</div>

<script>
// Helper: safe JSON fetch with error handling
async function safeFetch(url, options = {}) {
    try {
        const res = await fetch(url, options);
        if(!res.ok) throw new Error('Network response was not ok: ' + res.status);
        const data = await res.json();
        return data;
    } catch (err) {
        console.error('Fetch error:', err);
        return { error: err.message || 'Fetch error' };
    }
}

// Toggle details
document.querySelectorAll('.btn-view').forEach(btn => {
    btn.addEventListener('click', () => {
        const aid = btn.dataset.id;
        const details = document.getElementById('details-' + aid);
        if(details.style.display === 'none' || details.style.display === ''){
            details.style.display = 'table-row';
            loadComments(aid);
        } else {
            details.style.display = 'none';
        }
    });
});

// Load comments for an announcement
async function loadComments(aid){
    const container = document.getElementById('comments-' + aid);
    container.innerHTML = '<em>Loading comments...</em>';
    const data = await safeFetch(`../common/load_comments.php?announcement_id=${aid}`);
    if(data.error){
        container.innerHTML = `<div style="color:#ef4444">Error loading comments: ${data.error}</div>`;
        return;
    }
    container.innerHTML = '';
    if(data.length === 0){
        container.innerHTML = '<div style="color:#64748b">No comments yet.</div>';
    } else {
        data.forEach(c => {
            const div = document.createElement('div');
            div.className = 'comment';
            const left = document.createElement('div');
            left.innerHTML = `<div class="meta"><strong>${escapeHtml(c.username)}</strong> <small style="color:#475569"> • ${c.created_at}</small></div>
                              <div style="margin-top:6px;">${escapeHtml(c.comment)}</div>`;
            const btn = document.createElement('button');
            btn.className = 'delete-comment';
            btn.textContent = 'X';
            btn.title = 'Delete comment';
            btn.addEventListener('click', async () => {
                if(!confirm('Delete this comment?')) return;
                const resp = await safeFetch('../common/delete_comment.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: `comment_id=${encodeURIComponent(c.comment_id)}`
                });
                if(resp && resp.success){
                    await loadComments(aid);
                    // update comment count badge
                    document.querySelector('.comment-count-badge[data-ann="'+aid+'"]').textContent = resp.new_comment_count ?? 0;
                } else {
                    alert('Error deleting comment: ' + (resp.error || 'unknown'));
                }
            });
            div.appendChild(left);
            div.appendChild(btn);
            container.appendChild(div);
        });
    }
    // update comment count badge
    const badge = document.querySelector('.comment-count-badge[data-ann="'+aid+'"]');
    if(badge) badge.textContent = data.length;
}

// Add comment
document.querySelectorAll('.add-comment-form').forEach(form=>{
    form.addEventListener('submit', async e=>{
        e.preventDefault();
        const aid = form.dataset.id;
        const commentInput = form.querySelector('input[name="comment"]');
        const comment = commentInput.value.trim();
        if(!comment) return;
        const res = await safeFetch('../common/add_comment.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: `announcement_id=${encodeURIComponent(aid)}&comment=${encodeURIComponent(comment)}`
        });
        if(res && res.success){
            commentInput.value = '';
            await loadComments(aid);
            // update comment count badge
            document.querySelector('.comment-count-badge[data-ann="'+aid+'"]').textContent = res.new_comment_count ?? '';
        } else {
            alert('Error adding comment: ' + (res.error || 'unknown'));
        }
    });
});

// Like button (in details)
document.addEventListener('click', function(e){
    if(e.target && e.target.matches('.like-btn')){
        const aid = e.target.dataset.id;
        (async ()=>{
            const res = await safeFetch(`../common/like_announcement.php?announcement_id=${aid}`);
            if(res && !res.error){
                // update inline like count
                const inline = e.target.querySelector('.like-count-inline');
                if(inline) inline.textContent = res.like_count;
                // update table badge
                const badge = document.querySelector('.like-count-badge[data-ann="'+aid+'"]');
                if(badge) badge.textContent = res.like_count;
                // toggle heart (server returns liked_by_user)
                e.target.innerHTML = (res.liked_by_user ? '❤️' : '🤍') + ' <span class="like-count-inline">' + res.like_count + '</span>';
            } else {
                alert('Error toggling like: ' + (res.error || 'unknown'));
            }
        })();
    }
});

// small helper to escape html
function escapeHtml(unsafe) {
    if(unsafe === null || unsafe === undefined) return '';
    return unsafe
         .replaceAll('&','&amp;')
         .replaceAll('<','&lt;')
         .replaceAll('>','&gt;')
         .replaceAll('"','&quot;')
         .replaceAll("'",'&#039;');
}
</script>
</body>
</html>
