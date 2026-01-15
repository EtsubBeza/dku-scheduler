<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

$student_id = $_SESSION['user_id'];

// Fetch current user info - MATCHING DASHBOARD
$user_stmt = $pdo->prepare("SELECT username, profile_picture, email FROM users WHERE user_id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Determine profile picture path - FIXED VERSION (same as dashboard)
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
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<title>Announcements | Student Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Include Dark Mode CSS -->
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
* { box-sizing: border-box; margin:0; padding:0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

/* ================= Topbar for Hamburger ================= */
.topbar {
    display: none;
    position: fixed; top:0; left:0; width:100%;
    background:var(--bg-sidebar); color:var(--text-sidebar);
    padding:15px 20px;
    z-index:1200;
    justify-content:space-between; align-items:center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.menu-btn {
    font-size:26px;
    background:#1abc9c;
    border:none; color:var(--text-sidebar);
    cursor:pointer;
    padding:10px 14px;
    border-radius:8px;
    font-weight:600;
    transition: background 0.3s, transform 0.2s;
}
.menu-btn:hover { background:#159b81; transform:translateY(-2px); }

/* ================= Scrollable Sidebar ================= */
.sidebar {
    position: fixed; top:0; left:0;
    width:250px; height:100vh;
    background:var(--bg-sidebar); color:var(--text-sidebar);
    z-index:1100;
    transition: transform 0.3s ease;
    padding: 20px 0;
    overflow-y: auto; /* Enable vertical scrolling */
    overflow-x: hidden; /* Prevent horizontal scrolling */
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
}

/* Custom scrollbar for WebKit browsers */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
    transition: background 0.3s;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

[data-theme="dark"] .sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
}

[data-theme="dark"] .sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

.sidebar.hidden { 
    transform:translateX(-260px); 
}

.sidebar a { 
    display: flex;
    align-items: center;
    gap: 12px;
    padding:14px 20px; 
    color:var(--text-sidebar); 
    text-decoration:none; 
    transition: all 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.08);
    margin: 2px 10px;
    border-radius: 8px;
    font-size: 15px;
    flex-shrink: 0;
}
.sidebar a:hover { 
    background:rgba(255,255,255,0.1); 
    color:white;
    transform: translateX(5px);
}
.sidebar a.active { 
    background:#1abc9c; 
    color:white;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(26, 188, 156, 0.3);
}

.sidebar a i {
    font-size: 18px;
    width: 24px;
    text-align: center;
}

.sidebar-profile {
    text-align: center;
    margin-bottom: 25px;
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.15);
    flex-shrink: 0;
    position: sticky;
    top: 0;
    z-index: 5;
    background: var(--bg-sidebar);
    backdrop-filter: blur(5px);
}

.sidebar-profile img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 12px;
    border: 3px solid #1abc9c;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    transition: transform 0.3s;
}

.sidebar-profile img:hover {
    transform: scale(1.05);
}

.sidebar-profile p {
    color: var(--text-sidebar);
    font-weight: bold;
    margin: 0;
    font-size: 17px;
    letter-spacing: 0.5px;
}

/* Sidebar title */
.sidebar h2 {
    text-align: center;
    color: var(--text-sidebar);
    margin-bottom: 25px;
    font-size: 22px;
    padding: 0 20px;
    flex-shrink: 0;
    position: sticky;
    top: 165px;
    z-index: 4;
    background: var(--bg-sidebar);
    padding: 15px 20px;
    margin: 0;
}

/* Optional fade effect at bottom when scrolling */
.sidebar::after {
    content: '';
    position: fixed;
    bottom: 0;
    left: 0;
    width: 250px;
    height: 40px;
    background: linear-gradient(to bottom, transparent, var(--bg-sidebar) 90%);
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s;
    z-index: 3;
}

.sidebar.scrolled::after {
    opacity: 1;
}

/* ================= Overlay ================= */
.overlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.5); 
    z-index:1099;
    display:none; 
    opacity:0; 
    transition: opacity 0.3s ease;
    backdrop-filter: blur(3px);
}
.overlay.active { 
    display:block; 
    opacity:1; 
}

/* ================= Main content ================= */
.main-content {
    margin-left: 250px;
    padding:25px 30px;
    min-height:100vh;
    background: var(--bg-primary);
    transition: all 0.3s ease;
}

/* ================= Responsive ================= */
@media (max-width: 768px) {
    .topbar { 
        display: flex; 
    }
    .sidebar { 
        width: 280px;
        transform: translateX(-100%); 
    }
    .sidebar.active { 
        transform: translateX(0); 
        box-shadow: 5px 0 25px rgba(0,0,0,0.3);
    }
    .main-content { 
        margin-left: 0; 
        padding: 15px; 
    }
    .sidebar::after {
        width: 280px;
    }
    .overlay.active {
        z-index: 1000;
    }
}

/* The rest of your CSS (Content Wrapper, Announcements, etc.) remains exactly the same... */

/* ================= Main content ================= */
.main-content {
    margin-left: 250px;
    padding:20px;
    min-height:100vh;
    background: var(--bg-primary);
    transition: all 0.3s ease;
}

/* Content Wrapper */
.content-wrapper {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 6px var(--shadow-color);
    min-height: calc(100vh - 40px);
}

/* Header Styles */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.header h1 {
    font-size: 2.2rem;
    color: var(--text-primary);
    font-weight: 700;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--bg-secondary);
    padding: 12px 18px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.user-info img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
}

.user-info div div {
    font-weight: 600;
    color: var(--text-primary);
}

.user-info small {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* Welcome Section */
.welcome-section {
    margin-bottom: 30px;
}

.welcome-section h1 {
    font-size: 2.2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.5rem;
}

.welcome-section p {
    color: var(--text-secondary);
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
    background: var(--badge-primary-bg);
    color: var(--badge-primary-text);
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 20px;
}

/* Announcement Cards */
.announcement-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.announcement-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px var(--shadow-lg);
}

.announcement-card h3 {
    font-size: 1.3rem;
    color: var(--text-primary);
    margin-bottom: 10px;
    font-weight: 600;
}

.announcement-meta {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.announcement-meta i {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.announcement-message {
    color: var(--text-primary);
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
    border: 1px solid var(--border-color);
}

.attachment-image:hover, .attachment-video:hover {
    transform: scale(1.02);
}

.external-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: var(--badge-primary-bg);
    color: var(--badge-primary-text);
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    margin-top: 10px;
    transition: background 0.3s;
}

.external-link:hover {
    background: var(--hover-color);
}

/* Like Button */
.like-btn {
    cursor: pointer;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
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
    background: var(--error-bg);
    border-color: var(--error-border);
}

.like-btn.active {
    background: var(--error-bg);
    border-color: #ef4444;
}

.like-count {
    font-weight: 600;
    color: var(--text-primary);
}

/* Comments Section */
.comment-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

.comment {
    padding: 10px;
    background: var(--bg-secondary);
    margin-bottom: 8px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.comment strong {
    color: var(--text-primary);
    margin-right: 5px;
}

.comment-text {
    color: var(--text-primary);
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
    border: 1px solid var(--border-color);
    font-size: 0.95rem;
    transition: border-color 0.3s;
    background: var(--bg-secondary);
    color: var(--text-primary);
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
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    color: var(--border-color);
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: var(--text-primary);
}

.empty-state p {
    color: var(--text-secondary);
    max-width: 400px;
    margin: 0 auto;
    line-height: 1.5;
}

/* Dark mode specific adjustments */
[data-theme="dark"] .user-info div div {
    color: var(--text-primary);
}

[data-theme="dark"] .announcement-card h3 {
    color: var(--text-primary);
}

[data-theme="dark"] .announcement-meta {
    color: var(--text-secondary);
}

[data-theme="dark"] .announcement-message {
    color: var(--text-primary);
}

[data-theme="dark"] .comment strong {
    color: var(--text-primary);
}

[data-theme="dark"] .comment-text {
    color: var(--text-primary);
}

[data-theme="dark"] .like-count {
    color: var(--text-primary);
}

/* Animation classes */
.fade-in {
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.5s ease;
}

.fade-in.active {
    opacity: 1;
    transform: translateY(0);
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

    <!-- Scrollable Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-profile">
            <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic">
            <p><?= htmlspecialchars($user['username'] ?? 'Student') ?></p>
        </div>
        
        <h2>Student Dashboard</h2>
        
        <a href="student_dashboard.php" class="<?= $current_page=='student_dashboard.php'?'active':'' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="my_schedule.php" class="<?= $current_page=='my_schedule.php'?'active':'' ?>">
            <i class="fas fa-calendar-alt"></i> My Schedule
        </a>
        <a href="view_exam_schedules.php" class="<?= $current_page=='view_exam_schedules.php'?'active':'' ?>">
            <i class="fas fa-clipboard-list"></i> Exam Schedule
        </a>
        <a href="view_announcements.php" class="<?= $current_page=='view_announcements.php'?'active':'' ?>">
            <i class="fas fa-bullhorn"></i> Announcements
        </a>
        <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">
            <i class="fas fa-user-edit"></i> Edit Profile
        </a>
        <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
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
                    <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile" id="headerProfilePic">
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
                        <div class="announcement-card fade-in" data-id="<?= $a['announcement_id'] ?>">
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

    <!-- Include darkmode.js -->
    <script src="../../assets/js/darkmode.js"></script>
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
        
        // Animate announcement cards
        const cards = document.querySelectorAll('.fade-in');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.classList.add('active');
            }, index * 100);
        });
        
        // Debug: Log profile picture paths
        console.log('Sidebar profile pic src:', document.getElementById('sidebarProfilePic').src);
        console.log('Header profile pic src:', document.getElementById('headerProfilePic').src);
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
                container.innerHTML = '<p style="color:var(--text-secondary);font-size:0.9rem;">No comments yet. Be the first to comment!</p>';
                return;
            }
            
            data.forEach(c => {
                const commentDiv = document.createElement('div');
                commentDiv.className = 'comment';
                commentDiv.innerHTML = `
                    <strong>${c.username}:</strong>
                    <span class="comment-text">${c.comment}</span>
                    <div style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px;">
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

    // Fallback for broken profile pictures
    function handleImageError(img) {
        img.onerror = null;
        img.src = '../assets/default_profile.png';
        return true;
    }
    
    // Set profile picture fallbacks
    document.addEventListener('DOMContentLoaded', function() {
        const profileImages = document.querySelectorAll('img[src*="profile"]');
        profileImages.forEach(img => {
            img.onerror = function() {
                this.src = '../assets/default_profile.png';
            };
        });
    });
    </script>
</body>
</html>