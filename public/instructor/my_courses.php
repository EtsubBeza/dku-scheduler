<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor'){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

$instructor_id = $_SESSION['user_id'];

// Fetch instructor info - INCLUDING EMAIL
$user_stmt = $pdo->prepare("SELECT username, email, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$instructor_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// FIXED: Simplified profile picture path logic
$default_profile = '../assets/default_profile.png';

// Function to check if profile picture exists
function getProfilePicturePath($profile_picture) {
    if (empty($profile_picture)) {
        return '../assets/default_profile.png';
    }
    
    // Try multiple possible locations
    $locations = [
        __DIR__ . '/../uploads/' . $profile_picture,
        __DIR__ . '/../../uploads/' . $profile_picture,
        'uploads/' . $profile_picture,
        '../uploads/' . $profile_picture,
    ];
    
    foreach ($locations as $location) {
        if (file_exists($location)) {
            // Return the appropriate web path
            if (strpos($location, '../../uploads/') !== false) {
                return '../../uploads/' . $profile_picture;
            } elseif (strpos($location, '../uploads/') !== false) {
                return '../uploads/' . $profile_picture;
            } elseif (strpos($location, 'uploads/') !== false) {
                return 'uploads/' . $profile_picture;
            }
        }
    }
    
    // If file doesn't exist anywhere, return default
    return '../assets/default_profile.png';
}

// Get profile image path
$profile_img_path = getProfilePicturePath($user['profile_picture'] ?? '');

// Fetch courses taught by instructor
$courses_stmt = $pdo->prepare("
    SELECT DISTINCT c.course_id, c.course_name, c.course_code,
           (SELECT COUNT(*) FROM enrollments e 
            JOIN schedule s ON e.schedule_id = s.schedule_id 
            WHERE s.course_id = c.course_id AND s.instructor_id = ?) as student_count
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    WHERE s.instructor_id = ?
    ORDER BY c.course_name
");
$courses_stmt->execute([$instructor_id, $instructor_id]);
$courses = $courses_stmt->fetchAll();

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<title>My Courses | Instructor Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Include Dark Mode CSS -->
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

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

/* ================= Sidebar ================= */
.sidebar {
    position: fixed;
    top:0; left:0;
    height:100vh;
    width:240px;
    background: var(--bg-sidebar);
    padding: 30px 0 20px;
    display:flex;
    flex-direction:column;
    align-items:center;
    box-shadow:2px 0 10px rgba(0,0,0,0.2);
    z-index:1000;
    overflow-y:auto;
    transition: transform 0.3s ease;
}
.sidebar.hidden { transform:translateX(-100%); }

.sidebar-profile {
    text-align: center;
    margin-bottom: 25px;
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    width: 100%;
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
    color: var(--text-sidebar);
    font-weight: bold;
    margin: 0;
    font-size: 16px;
}

.sidebar h2 {
    color: var(--text-sidebar);
    text-align:center;
    width:100%;
    margin-bottom:25px;
    font-size:22px;
    padding: 0 20px;
}
.sidebar a {
    padding:12px 20px;
    text-decoration:none;
    font-size:16px;
    color:var(--text-sidebar);
    width:100%;
    transition: background 0.3s, color 0.3s;
    border-radius:6px;
    margin:3px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.sidebar a.active, .sidebar a:hover {
    background:#1abc9c;
    color:#fff;
    font-weight:bold;
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
    margin-left:240px;
    padding:30px;
    flex-grow:1;
    min-height:100vh;
    background: var(--bg-primary);
    border-radius:12px;
    margin-top:20px;
    margin-bottom:20px;
    width: calc(100% - 240px);
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

/* ================= Courses Table ================= */
.courses-container {
    margin-top: 30px;
}

.table-container {
    background: var(--bg-card);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
}

.courses-table {
    width: 100%;
    border-collapse: collapse;
}

.courses-table th {
    background: var(--table-header);
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: var(--text-sidebar);
    border-bottom: 1px solid var(--border-color);
}

.courses-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
}

.courses-table tr:last-child td {
    border-bottom: none;
}

.courses-table tr:hover {
    background: var(--hover-color);
}

/* Badge for student count */
.student-count-badge {
    background: var(--badge-primary-bg);
    color: var(--badge-primary-text);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* View Students Button */
.view-students-btn {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.view-students-btn:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(37, 99, 235, 0.3);
}

[data-theme="dark"] .view-students-btn {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
}

[data-theme="dark"] .view-students-btn:hover {
    background: linear-gradient(135deg, #1d4ed8, #1e40af);
}

/* Empty State */
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

/* ================= Modal Styles ================= */
.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.6);
    backdrop-filter: blur(2px);
}

.modal-content {
    background: var(--bg-card);
    margin: 5% auto;
    padding: 30px;
    border-radius: 15px;
    width: 90%;
    max-width: 800px;
    position: relative;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    border: 1px solid var(--border-color);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.close {
    position: absolute;
    top: 20px;
    right: 25px;
    font-size: 28px;
    font-weight: bold;
    color: var(--text-primary);
    cursor: pointer;
    transition: color 0.3s;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.close:hover {
    color: #ef4444;
    background: var(--bg-secondary);
}

.modal-content h2 {
    color: var(--text-primary);
    margin-bottom: 20px;
    font-size: 1.5rem;
    font-weight: 600;
}

.modal-table-container {
    margin-top: 20px;
    background: var(--bg-secondary);
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.modal-table {
    width: 100%;
    border-collapse: collapse;
}

.modal-table th {
    background: var(--table-header);
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: var(--text-sidebar);
    border-bottom: 1px solid var(--border-color);
}

.modal-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
}

.modal-table tr:last-child td {
    border-bottom: none;
}

.modal-table tr:hover {
    background: var(--hover-color);
}

/* Loading and Error states in modal */
.loading {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
    font-style: italic;
}

.error {
    text-align: center;
    padding: 20px;
    color: #ef4444;
    background: var(--error-bg);
    border-radius: 8px;
    margin: 10px 0;
    border-left: 4px solid #ef4444;
}

/* Modal empty state */
.modal-empty {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}

.modal-empty i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: var(--border-color);
}

/* ================= Responsive ================= */
@media screen and (max-width: 768px){
    .topbar { display: flex; }
    .sidebar { transform: translateX(-100%); width: 250px; }
    .sidebar.active { transform: translateX(0); }
    .main-content { margin-left: 0; padding: 15px; width: 100%; }
    .content-wrapper { padding: 20px; border-radius: 0; }
    .header { flex-direction: column; gap: 15px; align-items: flex-start; }
    .header h1 { font-size: 1.8rem; }
    .table-container { overflow-x: auto; }
    .courses-table { min-width: 600px; }
    .modal-content { 
        margin: 10% auto; 
        padding: 20px;
        width: 95%;
    }
    .modal-table { min-width: 500px; }
}
@media screen and (max-width: 480px){
    .modal-content { padding: 15px; }
    .courses-table th, .courses-table td { padding: 12px 10px; }
    .modal-table th, .modal-table td { padding: 12px 10px; }
}
</style>
</head>
<body>
    <!-- Topbar for Mobile -->
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h2>My Courses</h2>
    </div>

    <!-- Overlay for Mobile -->
    <div class="overlay" onclick="toggleSidebar()"></div>

 <!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-profile">
        <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic"
             onerror="this.onerror=null; this.src='../assets/default_profile.png';">
        <p><?= htmlspecialchars($user['username'] ?? 'Instructor') ?></p>
    </div>
    <h2>Instructor Dashboard</h2>
    <a href="instructor_dashboard.php" class="<?= $current_page=='instructor_dashboard.php'?'active':'' ?>">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="announcements.php" class="<?= $current_page=='announcements.php'?'active':'' ?>">
        <i class="fas fa-bullhorn"></i> Announcements
    </a>
    <a href="exam_assignments.php" class="<?= $current_page=='exam_assignments.php'?'active':'' ?>">
        <i class="fas fa-clipboard-list"></i> Exam Assignments
    </a>
    <a href="my_courses.php" class="<?= $current_page=='my_courses.php'?'active':'' ?>">
        <i class="fas fa-book"></i> My Courses
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
                    <h1>My Courses</h1>
                    <p>Manage and view students enrolled in your courses</p>
                </div>
                <div class="user-info">
                    <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile" id="headerProfilePic"
                         onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                    <div>
                        <div><?= htmlspecialchars($user['username'] ?? 'Instructor') ?></div>
                        <small>Instructor</small>
                    </div>
                </div>
            </div>

            <div class="courses-container">
                <?php if(empty($courses)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h3>No Courses Assigned</h3>
                        <p>You are not currently assigned to any courses. Contact your department head for course assignments.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="courses-table">
                            <thead>
                                <tr>
                                    <th>Course Name</th>
                                    <th>Course Code</th>
                                    <th>Students</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($courses as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['course_name']) ?></td>
                                    <td><?= htmlspecialchars($c['course_code'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="student-count-badge">
                                            <i class="fas fa-users"></i>
                                            <?= $c['student_count'] ?> students
                                        </span>
                                    </td>
                                    <td>
                                        <button class="view-students-btn" onclick="openModal(<?= $c['course_id'] ?>, '<?= htmlspecialchars($c['course_name']) ?>')">
                                            <i class="fas fa-eye"></i> View Students
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Structure -->
    <div id="studentsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Enrolled Students</h2>
            <div id="modalBody" class="loading">
                <i class="fas fa-spinner fa-spin"></i> Loading students...
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
        
        // Add animation to table rows
        const tableRows = document.querySelectorAll('.courses-table tbody tr');
        tableRows.forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                row.style.transition = 'all 0.5s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateX(0)';
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

    // Open modal & fetch students
    function openModal(courseId, courseName){
        // Update modal title
        document.getElementById('modalTitle').textContent = `Students - ${courseName}`;
        
        // Show loading state
        document.getElementById('modalBody').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading students...</div>';
        document.getElementById('studentsModal').style.display = 'block';
        
        // Prevent body scrolling when modal is open
        document.body.style.overflow = 'hidden';
        
        fetch(`view_students_ajax.php?course_id=${courseId}`)
        .then(response => {
            if(!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(data => {
            if (data.trim() === '') {
                document.getElementById('modalBody').innerHTML = 
                    '<div class="modal-empty">' +
                    '<i class="fas fa-users-slash"></i>' +
                    '<h3>No Students Enrolled</h3>' +
                    '<p>No students are currently enrolled in this course.</p>' +
                    '</div>';
            } else {
                document.getElementById('modalBody').innerHTML = data;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('modalBody').innerHTML = 
                '<div class="error">' +
                '<i class="fas fa-exclamation-circle"></i>' +
                '<h3>Error Loading Students</h3>' +
                '<p>There was an error loading the student list. Please try again.</p>' +
                '</div>';
        });
    }

    // Close modal
    function closeModal(){
        document.getElementById('studentsModal').style.display = 'none';
        document.body.style.overflow = 'auto'; // Restore scrolling
    }

    // Close modal if click outside
    window.onclick = function(event){
        if(event.target == document.getElementById('studentsModal')){
            closeModal();
        }
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(event){
        if(event.key === 'Escape'){
            closeModal();
        }
    });

    // Fallback for broken profile pictures
    function handleImageError(img) {
        img.onerror = null;
        img.src = '../assets/default_profile.png';
        return true;
    }
    
    // Set profile picture fallbacks
    document.addEventListener('DOMContentLoaded', function() {
        const profileImages = document.querySelectorAll('img[src*="profile"], img[alt*="Profile"]');
        profileImages.forEach(img => {
            img.onerror = function() {
                this.src = '../assets/default_profile.png';
            };
        });
    });
    </script>
</body>
</html>