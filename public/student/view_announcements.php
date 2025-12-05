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

// Fetch current user info - MATCHING DASHBOARD
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Determine profile picture path - FIXED VERSION
$uploads_dir = __DIR__ . '/../uploads/';
$assets_dir = __DIR__ . '/../assets/';

// Check if profile picture exists in uploads directory
$profile_picture = $user['profile_picture'] ?? '';
$default_profile = 'default_profile.png';

// Check multiple possible locations
if(!empty($profile_picture)) {
    // Try absolute path first
    if(file_exists($uploads_dir . $profile_picture)) {
        $profile_img_path = '../uploads/' . $profile_picture;
    }
    // Try relative path from current directory
    else if(file_exists('uploads/' . $profile_picture)) {
        $profile_img_path = 'uploads/' . $profile_picture;
    }
    // Try direct uploads path
    else if(file_exists('../uploads/' . $profile_picture)) {
        $profile_img_path = '../uploads/' . $profile_picture;
    }
    // Try ../../uploads path
    else if(file_exists('../../uploads/' . $profile_picture)) {
        $profile_img_path = '../../uploads/' . $profile_picture;
    }
    else {
        // Use default if file doesn't exist
        $profile_img_path = '../assets/' . $default_profile;
    }
} else {
    // Use default if no profile picture
    $profile_img_path = '../assets/' . $default_profile;
}

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
<title>Announcements | Student Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    padding: 20px 0;
}
.sidebar.hidden { transform:translateX(-260px); }
.sidebar a { 
    display:block; 
    padding:12px 20px; 
    color:#fff; 
    text-decoration:none; 
    transition: background 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.sidebar a:hover, .sidebar a.active { background:#1abc9c; }

.sidebar-profile {
    text-align: center;
    margin-bottom: 20px;
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
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

/* Sidebar title */
.sidebar h2 {
    text-align: center;
    color: #ecf0f1;
    margin-bottom: 25px;
    font-size: 22px;
    padding: 0 20px;
}

/* ================= Overlay ================= */
.overlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.4); z-index:1050;
    display:none; opacity:0; transition: opacity 0.3s ease;
}
.overlay.active { display:block; opacity:1; }

/* ================= Main content ================= */
.main-content {
    margin-left: 250px;
    padding:20px;
    min-height:100vh;
    background: #f8fafc;
    transition: all 0.3s ease;
}

/* Content Wrapper */
.content-wrapper {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    min-height: calc(100vh - 40px);
}

/* Header Styles */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.header h1 {
    font-size: 2.2rem;
    color: #1f2937;
    font-weight: 700;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #f8fafc;
    padding: 12px 18px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.user-info img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
}

/* Welcome Section */
.welcome-section {
    margin-bottom: 30px;
}

.welcome-section p {
    color: #6b7280;
    font-size: 1.1rem;
    margin-top: 10px;
}

/* ================= Announcements Section ================= */
.announcements-section {
    margin-top: 30px;
}

.announcement-count {
    display: inline-block;
    padding: 6px 12px;
    background: #e0e7ff;
    color: #3730a3;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 20px;
}

/* Announcement Cards */
.announcement-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.announcement-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
}

.announcement-card h3 {
    font-size: 1.3rem;
    color: #1f2937;
    margin-bottom: 10px;
    font-weight: 600;
}

.announcement-meta {
    font-size: 0.9rem;
    color: #6b7280;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.announcement-meta i {
    font-size: 0.8rem;
}

.announcement-message {
    color: #374151;
    line-height: 1.6;
    margin-bottom: 20px;
    font-size: 1rem;
}

/* Attachments */
.attachment-image, .attachment-video {
    max-width: 300px;
    max-height: 200px;
    width: 100%;
    height: auto;
    margin: 10px 0;
    border-radius: 8px;
    object-fit: cover;
    cursor: pointer;
    transition: transform 0.2s;
    border: 1px solid #e5e7eb;
}

.attachment-image:hover, .attachment-video:hover {
    transform: scale(1.02);
}

.external-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #e0e7ff;
    color: #3730a3;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    margin-top: 10px;
    transition: background 0.3s;
}

.external-link:hover {
    background: #c7d2fe;
}

/* Like Button */
.like-btn {
    cursor: pointer;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    padding: 8px 16px;
    border-radius: 8px;
    color: #ef4444;
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s;
    margin-right: 10px;
}

.like-btn:hover {
    background: #fee2e2;
    border-color: #fecaca;
}

.like-btn.active {
    background: #fee2e2;
    border-color: #ef4444;
}

.like-count {
    font-weight: 600;
    color: #374151;
}

/* Comments Section */
.comment-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.comment {
    padding: 10px;
    background: #f8fafc;
    margin-bottom: 8px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.comment strong {
    color: #1f2937;
    margin-right: 5px;
}

.comment-text {
    color: #374151;
}

.add-comment-form {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.add-comment-form input {
    flex: 1;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    font-size: 0.95rem;
    transition: border-color 0.3s;
}

.add-comment-form input:focus {
    outline: none;
    border-color: #3b82f6;
}

.add-comment-form button {
    padding: 10px 20px;
    border: none;
    background: #3b82f6;
    color: #fff;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.3s;
}

.add-comment-form button:hover {
    background: #2563eb;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    color: #d1d5db;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: #374151;
}

.empty-state p {
    color: #6b7280;
    max-width: 400px;
    margin: 0 auto;
    line-height: 1.5;
}

/* ================= Responsive ================= */
@media (max-width: 768px) {
    .topbar { display: flex; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .main-content { margin-left: 0; padding: 15px; }
    .content-wrapper { padding: 20px; border-radius: 0; }
    .header { flex-direction: column; gap: 15px; align-items: flex-start; }
    .header h1 { font-size: 1.8rem; }
    .announcement-card { padding: 20px; }
    .attachment-image, .attachment-video { 
        max-width: 100%;
        max-height: 250px;
    }
    .add-comment-form {
        flex-direction: column;
    }
    .add-comment-form button {
        width: 100%;
    }
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>Announcements</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar - SAME AS OTHER PAGES -->
    <div class="sidebar">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture">
            <p><?= htmlspecialchars($user['username'] ?? 'Student') ?></p>
        </div>
        <h2>Student Panel</h2>
        <a href="student_dashboard.php" class="<?= $current_page=='student_dashboard.php'?'active':'' ?>">Dashboard</a>
        <a href="my_schedule.php" class="<?= $current_page=='my_schedule.php'?'active':'' ?>">My Schedule</a>
        <a href="view_exam_schedules.php" class="<?= $current_page=='view_exam_schedules.php'?'active':'' ?>">Exam Schedule</a>
        <a href="view_announcements.php" class="<?= $current_page=='view_announcements.php'?'active':'' ?>">Announcements</a>
        <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">Edit Profile</a>
        <a href="../logout.php">Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
                <div class="welcome-section">
                    <h1>Announcements</h1>
                    <p>Stay updated with the latest news and updates from your department</p>
                </div>
                <div class="user-info">
                    <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile">
                    <div>
                        <div><?= htmlspecialchars($user['username'] ?? 'Student') ?></div>
                        <small>Student</small>
                    </div>
                </div>
            </div>

            <!-- Announcements Section -->
            <div class="announcements-section">
                <div class="announcement-count">
                    <i class="fas fa-bullhorn"></i> <?= count($announcements) ?> Announcements
                </div>

                <?php if($announcements): ?>
                    <?php foreach($announcements as $a): ?>
                        <div class="announcement-card" data-id="<?= $a['announcement_id'] ?>">
                            <h3><?= htmlspecialchars($a['title']) ?></h3>
                            <div class="announcement-meta">
                                <i class="fas fa-user"></i> Posted by <?= htmlspecialchars($a['creator_name']) ?>
                                <i class="fas fa-clock"></i> <?= date('M d, Y • h:i A', strtotime($a['created_at'])) ?>
                            </div>
                            
                            <div class="announcement-message">
                                <?= nl2br(htmlspecialchars($a['message'])) ?>
                            </div>

                            <!-- Attachments -->
                            <?php if(!empty($a['attachment'])): ?>
                                <?php
                                $ext = pathinfo($a['attachment'], PATHINFO_EXTENSION);
                                if(in_array(strtolower($ext), ['jpg','jpeg','png','gif'])): ?>
                                    <img src="../../uploads/announcements/<?= htmlspecialchars($a['attachment']) ?>" 
                                         class="attachment-image"
                                         onclick="openImage('<?= htmlspecialchars($a['attachment']) ?>')">
                                <?php elseif(in_array(strtolower($ext), ['mp4','webm','ogg'])): ?>
                                    <video src="../../uploads/announcements/<?= htmlspecialchars($a['attachment']) ?>" 
                                           controls class="attachment-video"></video>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if(!empty($a['external_link'])): ?>
                                <a href="<?= htmlspecialchars($a['external_link']) ?>" 
                                   target="_blank" 
                                   class="external-link">
                                    <i class="fas fa-external-link-alt"></i> Open External Link
                                </a>
                            <?php endif; ?>

                            <!-- Like Button -->
                            <button class="like-btn <?= $a['liked_by_user'] ? 'active' : '' ?>" data-id="<?= $a['announcement_id'] ?>">
                                <?php if($a['liked_by_user']): ?>
                                    <i class="fas fa-heart"></i>
                                <?php else: ?>
                                    <i class="far fa-heart"></i>
                                <?php endif; ?>
                                <span class="like-count"><?= $a['like_count'] ?></span> Likes
                            </button>

                            <!-- Comments -->
                            <div class="comment-section" id="comments-<?= $a['announcement_id'] ?>">
                                <!-- Comments will be loaded here -->
                            </div>
                            
                            <form class="add-comment-form" data-id="<?= $a['announcement_id'] ?>">
                                <input type="text" name="comment" placeholder="Write a comment..." required>
                                <button type="submit">
                                    <i class="fas fa-paper-plane"></i> Post
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bullhorn"></i>
                        <h3>No Announcements Yet</h3>
                        <p>There are no announcements to display at the moment. Check back later for updates.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    // Set active state for current page
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.sidebar a');
        
        navLinks.forEach(link => {
            const linkPage = link.getAttribute('href');
            if (linkPage === currentPage) {
                link.classList.add('active');
            }
        });
        
        // Load comments for each announcement
        document.querySelectorAll('.announcement-card').forEach(card => {
            const aid = card.dataset.id;
            loadComments(aid);
        });
    });

    // Confirm logout
    document.querySelector('a[href="../logout.php"]').addEventListener('click', function(e) {
        if(!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });

    // Load comments for an announcement
    function loadComments(aid) {
        fetch(`../common/load_comments.php?announcement_id=${aid}`)
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById(`comments-${aid}`);
            container.innerHTML = '';
            
            if(data.length === 0) {
                container.innerHTML = '<p style="color:#6b7280;font-size:0.9rem;">No comments yet. Be the first to comment!</p>';
                return;
            }
            
            data.forEach(c => {
                const commentDiv = document.createElement('div');
                commentDiv.className = 'comment';
                commentDiv.innerHTML = `
                    <strong>${c.username}:</strong>
                    <span class="comment-text">${c.comment}</span>
                    <div style="font-size:0.8rem;color:#6b7280;margin-top:4px;">
                        ${new Date(c.created_at).toLocaleDateString()}
                    </div>
                `;
                container.appendChild(commentDiv);
            });
        })
        .catch(error => {
            console.error('Error loading comments:', error);
        });
    }

    // Like button toggle
    document.querySelectorAll('.like-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const aid = btn.dataset.id;
            const likeBtn = btn;
            
            fetch(`../common/like_announcement.php?announcement_id=${aid}`)
            .then(res => res.json())
            .then(data => {
                likeBtn.querySelector('.like-count').textContent = data.like_count;
                
                if(data.liked_by_user) {
                    likeBtn.innerHTML = '<i class="fas fa-heart"></i> <span class="like-count">' + data.like_count + '</span> Likes';
                    likeBtn.classList.add('active');
                } else {
                    likeBtn.innerHTML = '<i class="far fa-heart"></i> <span class="like-count">' + data.like_count + '</span> Likes';
                    likeBtn.classList.remove('active');
                }
            })
            .catch(error => {
                console.error('Error liking announcement:', error);
            });
        });
    });

    // Add comment
    document.querySelectorAll('.add-comment-form').forEach(form => {
        form.addEventListener('submit', e => {
            e.preventDefault();
            const aid = form.dataset.id;
            const comment = form.querySelector('input[name="comment"]').value;
            
            fetch('../common/add_comment.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `announcement_id=${aid}&comment=${encodeURIComponent(comment)}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    form.querySelector('input[name="comment"]').value = '';
                    loadComments(aid);
                }
            })
            .catch(error => {
                console.error('Error adding comment:', error);
            });
        });
    });

    // Open image in full screen
    function openImage(filename) {
        window.open('../../uploads/announcements/' + filename, '_blank');
    }

    // Add animation to announcement cards on page load
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.announcement-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
    </script>
</body>
</html>