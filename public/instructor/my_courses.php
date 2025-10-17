<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor'){
    header("Location: ../index.php");
    exit;
}

$instructor_id = $_SESSION['user_id'];

// Fetch instructor info
$user_stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
$user_stmt->execute([$instructor_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch courses taught by instructor
$courses_stmt = $pdo->prepare("
    SELECT DISTINCT c.course_id, c.course_name
    FROM schedule s
    JOIN courses c ON s.course_id = c.course_id
    WHERE s.instructor_id = ?
");
$courses_stmt->execute([$instructor_id]);
$courses = $courses_stmt->fetchAll();

// Sidebar active page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Courses</title>
<style>
body {
    font-family: Arial,sans-serif;
    display:flex;
    min-height:100vh;
    background: linear-gradient(-45deg, #0f2027, #203a43, #2c5364);
    background-size:400% 400%;
    animation: gradientBG 15s ease infinite;
}
@keyframes gradientBG{0%{background-position:0% 50%;}50%{background-position:100% 50%;}100%{background-position:0% 50%;}}

.sidebar{
    position:fixed;top:0;left:0;height:100vh;width:240px;
    background-color: rgba(44,62,80,0.95);padding-top:20px;
    display:flex;flex-direction:column;align-items:flex-start;
    box-shadow:2px 0 10px rgba(0,0,0,0.2);z-index:1000;overflow-y:auto;
}
.sidebar h2{color:#ecf0f1;text-align:center;width:100%;margin-bottom:25px;font-size:22px;}
.sidebar a{padding:12px 20px;text-decoration:none;font-size:16px;color:#bdc3c7;width:100%;transition:background 0.3s,color 0.3s;border-radius:6px;margin:3px 0;}
.sidebar a.active,.sidebar a:hover{background-color:#34495e;color:#fff;font-weight:bold;}

.main-content{margin-left:240px;padding:30px;flex-grow:1;min-height:100vh;background-color: rgba(243,244,246,0.95);border-radius:12px;margin-top:20px;margin-bottom:20px;}
h1,h2{margin-bottom:20px;color:#111827;}

table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,0.1);}
th,td{padding:14px;text-align:left;}
th{background-color:#2575fc;color:#fff;font-weight:600;}
tr:nth-child(even){background-color:#f7f9fc;}
tr:hover{background-color:#d0e7ff;}

button.view-students-btn{background:#2575fc;color:#fff;border:none;padding:8px 15px;border-radius:6px;cursor:pointer;transition:0.3s;}
button.view-students-btn:hover{background:#1254c1;}

/* Modal styles */
.modal{display:none;position:fixed;z-index:2000;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.6);}
.modal-content{background:#fff;margin:10% auto;padding:20px;border-radius:12px;width:90%;max-width:600px;position:relative;}
.close{position:absolute;top:10px;right:15px;font-size:28px;font-weight:bold;color:#333;cursor:pointer;transition:0.3s;}
.close:hover{color:#000;}
.modal table{width:100%;border-collapse:collapse;margin-top:10px;}
.modal table th, .modal table td{padding:10px;border:1px solid #ccc;}
.modal table th{background:#2575fc;color:#fff;}

/* Responsive */
@media screen and (max-width:768px){body{flex-direction:column;}.sidebar{width:100%;padding:15px;box-shadow:none;}.main-content{margin:0;padding:20px;border-radius:0;}table th,table td{padding:10px;font-size:12px;}}
</style>
</head>
<body>

<div class="sidebar">
    <h2>Instructor Panel</h2>
    <a href="instructor_dashboard.php" class="<?= $current_page=='instructor_dashboard.php'?'active':'' ?>">Dashboard</a>
    <a href="my_courses.php" class="<?= $current_page=='my_courses.php'?'active':'' ?>">My Courses</a>
    <a href="edit_profile.php" class="<?= $current_page=='edit_profile.php'?'active':'' ?>">Edit Profile</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <h1>My Courses</h1>
    <table>
        <thead>
            <tr>
                <th>Course Name</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($courses as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['course_name']) ?></td>
                <td>
                    <button class="view-students-btn" onclick="openModal(<?= $c['course_id'] ?>)">View Students</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Structure -->
<div id="studentsModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Enrolled Students</h2>
        <div id="modalBody">
            <!-- Student table will be inserted here dynamically -->
        </div>
    </div>
</div>

<script>
// Open modal & fetch students
function openModal(courseId){
    fetch(`view_students_ajax.php?course_id=${courseId}`)
    .then(response=>response.text())
    .then(data=>{
        document.getElementById('modalBody').innerHTML = data;
        document.getElementById('studentsModal').style.display = 'block';
    });
}

// Close modal
function closeModal(){
    document.getElementById('studentsModal').style.display = 'none';
}

// Close modal if click outside
window.onclick = function(event){
    if(event.target==document.getElementById('studentsModal')){
        closeModal();
    }
}
</script>

</body>
</html>
