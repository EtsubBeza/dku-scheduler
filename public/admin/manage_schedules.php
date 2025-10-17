<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit;
}

// Handle Add/Edit/Delete
$editing = false;
$edit_schedule_id = 0;

// Delete schedule
if(isset($_POST['delete_schedule'])){
    $stmt = $pdo->prepare("DELETE FROM schedule WHERE schedule_id=?");
    $stmt->execute([$_POST['schedule_id']]);
    header("Location: manage_schedules.php");
    exit;
}

// Edit schedule
if(isset($_GET['edit'])){
    $edit_schedule_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM schedule WHERE schedule_id=?");
    $stmt->execute([$edit_schedule_id]);
    $editing = true;
    $edit_schedule = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Add/Edit schedule
if(isset($_POST['add_schedule']) || isset($_POST['edit_schedule'])){
    $data = [
        $_POST['course_id'], $_POST['instructor_id'], $_POST['room_id'],
        $_POST['academic_year'], $_POST['semester'], $_POST['day_of_week'],
        $_POST['start_time'], $_POST['end_time']
    ];

    if(isset($_POST['edit_schedule'])){
        $stmt = $pdo->prepare("UPDATE schedule SET course_id=?, instructor_id=?, room_id=?, academic_year=?, semester=?, day_of_week=?, start_time=?, end_time=? WHERE schedule_id=?");
        $data[] = $edit_schedule_id;
        $stmt->execute($data);
    } else {
        $stmt = $pdo->prepare("INSERT INTO schedule (course_id, instructor_id, room_id, academic_year, semester, day_of_week, start_time, end_time) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute($data);
    }
    header("Location: manage_schedules.php");
    exit;
}

// Fetch dropdowns and schedules
$courses = $pdo->query("SELECT * FROM courses")->fetchAll(PDO::FETCH_ASSOC);
$rooms = $pdo->query("SELECT * FROM rooms")->fetchAll(PDO::FETCH_ASSOC);
$instructors = $pdo->query("SELECT * FROM users WHERE role='instructor'")->fetchAll(PDO::FETCH_ASSOC);

$schedules = $pdo->query("
    SELECT s.*, c.course_name, r.room_name, u.username AS instructor_name
    FROM schedule s
    LEFT JOIN courses c ON s.course_id = c.course_id
    LEFT JOIN rooms r ON s.room_id = r.room_id
    LEFT JOIN users u ON s.instructor_id = u.user_id
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Schedules - DKU Scheduler</title>
<style>
/* ===== General Reset ===== */
* { margin:0; padding:0; box-sizing:border-box; font-family:Arial,sans-serif; }

/* ===== Sidebar ===== */
.sidebar {
    position: fixed; top:0; left:0; width:230px; height:100vh; background:#2c3e50; padding-top:20px;
    display:flex; flex-direction:column; z-index:1000; transition:0.3s;
}
.sidebar h2 { color:#ecf0f1; text-align:center; margin-bottom:20px; font-size:22px; }
.sidebar a { padding:12px 20px; color:#bdc3c7; text-decoration:none; border-radius:8px; margin-bottom:5px; transition:0.3s;}
.sidebar a.active, .sidebar a:hover { background:#1abc9c; color:#fff; font-weight:bold; }

/* ===== Topbar (mobile) ===== */
.topbar { display:none; background:#2c3e50; color:#fff; padding:15px 20px; justify-content:space-between; align-items:center; position:fixed; top:0; width:100%; z-index:1100;}
.menu-btn { font-size:24px; background:none; border:none; color:white; cursor:pointer; }

/* ===== Main content ===== */
.main-content { margin-left:230px; padding:30px; min-height:100vh; background:#f3f4f6; transition:0.3s; }
.main-content h1 { font-size:28px; margin-bottom:20px; color:#111827; }

/* ===== Form Styling ===== */
.schedule-form { display:flex; flex-wrap:wrap; gap:10px; }
.schedule-form select, .schedule-form input { padding:10px; border-radius:8px; border:1px solid #ccc; font-size:14px; flex:1 1 200px; min-width:120px; }
.schedule-form button { background:#2563eb; color:#fff; border:none; cursor:pointer; padding:10px 16px; border-radius:8px; transition:0.2s; }
.schedule-form button:hover { background:#1d4ed8; }
.cancel-btn { text-decoration:none; color:#dc2626; padding:10px 16px; border-radius:8px; margin-left:10px; display:inline-block; }
.auto-generate-btn { background:#1abc9c; color:#fff; padding:10px 16px; border:none; border-radius:8px; font-weight:bold; cursor:pointer; transition:0.2s; margin-bottom:20px;}
.auto-generate-btn:hover { background:#16a085; }

/* ===== Table Styling ===== */
.table-container {
    width: 100%;
    max-height: 400px;       /* Vertical scroll if needed */
    overflow-x: auto;        /* Horizontal scroll */
    overflow-y: auto;        /* Vertical scroll */
    -webkit-overflow-scrolling: touch;
    margin-top:20px;
    padding-bottom:10px;
}
.table-container::-webkit-scrollbar { height:12px; }
.table-container::-webkit-scrollbar-track { background:#f3f4f6; border-radius:6px; }
.table-container::-webkit-scrollbar-thumb { background:#888; border-radius:6px; }
.table-container::-webkit-scrollbar-thumb:hover { background:#555; }

.schedule-table { width:100%; border-collapse:collapse; min-width:800px; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 4px 8px rgba(0,0,0,0.05);}
.schedule-table th, .schedule-table td { padding:12px; border-bottom:1px solid #ddd; text-align:left;}
.schedule-table th { background:#34495e; color:#fff;}
.schedule-table tr:nth-child(even){ background:#f2f2f2;}
.schedule-table tr:hover{ background:#e0e0e0;}
.schedule-table button { background:#dc2626; color:#fff; border:none; padding:5px 8px; border-radius:5px; cursor:pointer;}
.schedule-table button:hover{ background:#b91c1c;}
.schedule-table a.edit-btn { background:#2563eb; color:#fff; padding:5px 8px; border-radius:5px; text-decoration:none;}
.schedule-table a.edit-btn:hover{ background:#1d4ed8; }

/* ===== Responsive ===== */
@media(max-width:768px){
    .sidebar { position:fixed; left:-250px; width:230px; transition:0.3s; }
    .sidebar.active { left:0; }
    .main-content { margin-left:0; padding:20px;}
    .schedule-form { flex-direction:column; }
    .schedule-form select, .schedule-form input, .schedule-form button, .cancel-btn { width:100%; margin:5px 0; }
    .topbar{display:flex;}
    /* Card-style table for mobile */
    .schedule-table, .schedule-table thead, .schedule-table tbody, .schedule-table th, .schedule-table td, .schedule-table tr { display:block; width:100%; }
    .schedule-table thead tr { display:none; }
    .schedule-table tr { margin-bottom:15px; background:#fff; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.1); padding:10px;}
    .schedule-table td { text-align:right; padding-left:50%; position:relative; border:none;}
    .schedule-table td::before { content: attr(data-label); position:absolute; left:15px; width:45%; text-align:left; font-weight:bold;}
}
</style>
</head>
<body>

<div class="topbar">
    <button class="menu-btn" onclick="toggleSidebar()">☰</button>
    <h2>Admin Panel</h2>
</div>

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

<div class="main-content">
    <h1><?= $editing ? "Edit Schedule" : "Add Schedule" ?></h1>

    <form action="auto_generate_schedule.php" method="POST">
        <button type="submit" class="auto-generate-btn">⚙️ Auto Generate Schedule</button>
    </form>

    <form method="POST" class="schedule-form">
        <select name="course_id" required>
            <option value="">Select Course</option>
            <?php foreach($courses as $c): ?>
                <option value="<?= $c['course_id'] ?>" <?= ($editing && $edit_schedule['course_id']==$c['course_id'])?'selected':'' ?>>
                    <?= htmlspecialchars($c['course_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="instructor_id" required>
            <option value="">Select Instructor</option>
            <?php foreach($instructors as $i): ?>
                <option value="<?= $i['user_id'] ?>" <?= ($editing && $edit_schedule['instructor_id']==$i['user_id'])?'selected':'' ?>>
                    <?= htmlspecialchars($i['username']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="room_id" required>
            <option value="">Select Room</option>
            <?php foreach($rooms as $r): ?>
                <option value="<?= $r['room_id'] ?>" <?= ($editing && $edit_schedule['room_id']==$r['room_id'])?'selected':'' ?>>
                    <?= htmlspecialchars($r['room_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="academic_year" placeholder="Academic Year" required value="<?= $editing?$edit_schedule['academic_year']:'' ?>">
        <input type="text" name="semester" placeholder="Semester" required value="<?= $editing?$edit_schedule['semester']:'' ?>">
        <select name="day_of_week" required>
            <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday'] as $day): ?>
                <option value="<?= $day ?>" <?= ($editing && $edit_schedule['day_of_week']==$day)?'selected':'' ?>><?= $day ?></option>
            <?php endforeach; ?>
        </select>
        <input type="time" name="start_time" required value="<?= $editing?$edit_schedule['start_time']:'' ?>">
        <input type="time" name="end_time" required value="<?= $editing?$edit_schedule['end_time']:'' ?>">
        <button type="submit" name="<?= $editing?'edit_schedule':'add_schedule' ?>"><?= $editing?'Update Schedule':'Add Schedule' ?></button>
        <?php if($editing): ?>
            <a href="manage_schedules.php" class="cancel-btn">Cancel</a>
        <?php endif; ?>
    </form>

    <div class="table-container">
        <table class="schedule-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Course</th>
                    <th>Instructor</th>
                    <th>Room</th>
                    <th>Academic Year</th>
                    <th>Semester</th>
                    <th>Day</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($schedules as $s): ?>
                <tr>
                    <td data-label="ID"><?= $s['schedule_id'] ?></td>
                    <td data-label="Course"><?= htmlspecialchars($s['course_name']) ?></td>
                    <td data-label="Instructor"><?= htmlspecialchars($s['instructor_name']) ?></td>
                    <td data-label="Room"><?= htmlspecialchars($s['room_name']) ?></td>
                    <td data-label="Academic Year"><?= htmlspecialchars($s['academic_year']) ?></td>
                    <td data-label="Semester"><?= htmlspecialchars($s['semester']) ?></td>
                    <td data-label="Day"><?= htmlspecialchars($s['day_of_week']) ?></td>
                    <td data-label="Start"><?= htmlspecialchars($s['start_time']) ?></td>
                    <td data-label="End"><?= htmlspecialchars($s['end_time']) ?></td>
                    <td data-label="Actions">
                        <div style="white-space:nowrap;">
                            <a class="edit-btn" href="?edit=<?= $s['schedule_id'] ?>">Edit</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this schedule?')">
                                <input type="hidden" name="schedule_id" value="<?= $s['schedule_id'] ?>">
                                <button type="submit" name="delete_schedule">Delete</button>
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
// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(e){
    const sidebar = document.getElementById('sidebar');
    if(window.innerWidth <= 768 && sidebar.classList.contains('active') && !sidebar.contains(e.target) && !e.target.classList.contains('menu-btn')){
        sidebar.classList.remove('active');
    }
});
</script>
</body>
</html>
