<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Redirect if not admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

// CSRF Token - Only generate if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch current user info
$user_stmt = $pdo->prepare("SELECT username, email, profile_picture FROM users WHERE user_id=?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch();

// Function to get profile picture path for admin
function getAdminProfilePicturePath($profile_picture) {
    if (empty($profile_picture)) {
        return '../assets/default_profile.png';
    }
    
    // Check multiple possible locations for admin profile pictures
    $locations = [
        __DIR__ . '/../uploads/admin/' . $profile_picture,
        __DIR__ . '/../uploads/' . $profile_picture,
        __DIR__ . '/../../uploads/' . $profile_picture,
        'uploads/admin/' . $profile_picture,
        '../uploads/admin/' . $profile_picture,
        'uploads/' . $profile_picture,
        '../uploads/' . $profile_picture,
    ];
    
    foreach ($locations as $location) {
        if (file_exists($location)) {
            if (strpos($location, '/admin/') !== false) {
                return '../uploads/admin/' . $profile_picture;
            } elseif (strpos($location, 'uploads/admin/') !== false) {
                return 'uploads/admin/' . $profile_picture;
            } elseif (strpos($location, '../uploads/') !== false) {
                return '../uploads/' . $profile_picture;
            } elseif (strpos($location, 'uploads/') !== false) {
                return 'uploads/' . $profile_picture;
            }
        }
    }
    
    return '../assets/default_profile.png';
}

// Get profile image path
$profile_img_path = getAdminProfilePicturePath($current_user['profile_picture'] ?? '');

// Initialize message variables
$message = "";
$message_type = "success";

// Add Room
if(isset($_POST['add_room'])){
    // Debug logging
    error_log("Add room form submitted");
    error_log("CSRF Token from form: " . ($_POST['csrf_token'] ?? 'none'));
    error_log("CSRF Token from session: " . ($_SESSION['csrf_token'] ?? 'none'));
    error_log("Room name: " . ($_POST['room_name'] ?? 'none'));
    
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
        error_log("CSRF token mismatch");
    } else {
        $room_name = trim($_POST['room_name']);
        $capacity = (int)$_POST['capacity'];
        $building = trim($_POST['building']);
        
        // Validate inputs
        if(empty($room_name) || empty($building) || $capacity <= 0){
            $message = "Please fill in all fields correctly. Capacity must be greater than 0.";
            $message_type = "error";
        } else {
            try {
                // Check if room name already exists in the same building
                $check_stmt = $pdo->prepare("SELECT room_id FROM rooms WHERE room_name = ? AND building = ?");
                $check_stmt->execute([$room_name, $building]);
                $exists = $check_stmt->fetch();
                
                if($exists){
                    $message = "Error: Room '$room_name' already exists in '$building'!";
                    $message_type = "error";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO rooms (room_name, capacity, building) VALUES (?, ?, ?)");
                    $stmt->execute([$room_name, $capacity, $building]);
                    $message = "Room added successfully!";
                    $message_type = "success";
                    
                    // Redirect to clear POST data
                    header("Location: manage_rooms.php?success=1");
                    exit;
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = "error";
                error_log("Database error: " . $e->getMessage());
            }
        }
    }
}

// Edit Room
if(isset($_POST['edit_room'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $room_id = (int)$_POST['room_id'];
        $room_name = trim($_POST['room_name']);
        $capacity = (int)$_POST['capacity'];
        $building = trim($_POST['building']);
        
        // Validate inputs
        if(empty($room_name) || empty($building) || $capacity <= 0){
            $message = "Please fill in all fields correctly. Capacity must be greater than 0.";
            $message_type = "error";
        } else {
            try {
                // Check if room name already exists in the same building (excluding current room)
                $check_stmt = $pdo->prepare("SELECT room_id FROM rooms WHERE room_name = ? AND building = ? AND room_id != ?");
                $check_stmt->execute([$room_name, $building, $room_id]);
                $exists = $check_stmt->fetch();
                
                if($exists){
                    $message = "Error: Room '$room_name' already exists in '$building'!";
                    $message_type = "error";
                } else {
                    $stmt = $pdo->prepare("UPDATE rooms SET room_name=?, capacity=?, building=? WHERE room_id=?");
                    $stmt->execute([$room_name, $capacity, $building, $room_id]);
                    $message = "Room updated successfully!";
                    $message_type = "success";
                    
                    // Redirect to clear POST data
                    header("Location: manage_rooms.php?success=2");
                    exit;
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Delete Room
if(isset($_POST['delete_room'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $room_id = (int)$_POST['room_id'];
        
        try {
            // Check if room has any schedules before deleting
            $check_schedule = $pdo->prepare("SELECT schedule_id FROM schedule WHERE room_id = ? LIMIT 1");
            $check_schedule->execute([$room_id]);
            $has_schedule = $check_schedule->fetch();
            
            if($has_schedule){
                $message = "Cannot delete room: It has existing schedules. Please delete schedules first.";
                $message_type = "error";
            } else {
                $stmt = $pdo->prepare("DELETE FROM rooms WHERE room_id=?");
                $stmt->execute([$room_id]);
                $message = "Room deleted successfully!";
                $message_type = "success";
                
                // Redirect to clear POST data
                header("Location: manage_rooms.php?success=3");
                exit;
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Check for success messages from redirects
if(isset($_GET['success'])) {
    switch($_GET['success']) {
        case '1':
            $message = "Room added successfully!";
            $message_type = "success";
            break;
        case '2':
            $message = "Room updated successfully!";
            $message_type = "success";
            break;
        case '3':
            $message = "Room deleted successfully!";
            $message_type = "success";
            break;
    }
}

// Fetch room to edit
$edit_room = null;
if(isset($_GET['edit'])){
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_id=?");
    $stmt->execute([$edit_id]);
    $edit_room = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch rooms
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY building, room_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending approvals count
$pending_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_approved = 0");
$pending_approvals = $pending_stmt->fetchColumn() ?: 0;

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Rooms - DKU Scheduler</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../../assets/css/darkmode.css">
<style>
/* ================= CSS Variables ================= */
:root {
    --bg-primary: #f8f9fa;
    --bg-secondary: #ffffff;
    --bg-card: #ffffff;
    --bg-sidebar: #2c3e50;
    --text-primary: #333333;
    --text-secondary: #666666;
    --text-sidebar: #ffffff;
    --border-color: #dee2e6;
    --shadow-color: rgba(0,0,0,0.1);
    --hover-color: rgba(0,0,0,0.05);
    --table-header: #3498db;
    --success-bg: #d1fae5;
    --success-text: #065f46;
    --success-border: #10b981;
    --error-bg: #fee2e2;
    --error-text: #991b1b;
    --error-border: #ef4444;
    --warning-bg: #fef3c7;
    --warning-text: #92400e;
    --warning-border: #f59e0b;
}

[data-theme="dark"] {
    --bg-primary: #1a1a1a;
    --bg-secondary: #2d2d2d;
    --bg-card: #2d2d2d;
    --bg-sidebar: #1e2a3a;
    --text-primary: #e0e0e0;
    --text-secondary: #b0b0b0;
    --text-sidebar: #e0e0e0;
    --border-color: #404040;
    --shadow-color: rgba(0,0,0,0.3);
    --hover-color: rgba(255,255,255,0.05);
    --table-header: #2563eb;
    --success-bg: #064e3b;
    --success-text: #a7f3d0;
    --success-border: #10b981;
    --error-bg: #7f1d1d;
    --error-text: #fecaca;
    --error-border: #ef4444;
    --warning-bg: #78350f;
    --warning-text: #fde68a;
    --warning-border: #f59e0b;
}

/* ================= General Reset ================= */
* { margin:0; padding:0; box-sizing:border-box; font-family: "Segoe UI", Arial, sans-serif; }
body { display:flex; min-height:100vh; background: var(--bg-primary); overflow-x:hidden; }

/* ================= Topbar for Mobile ================= */
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
    width:250px; 
    height:100%; 
    background:var(--bg-sidebar); 
    color:var(--text-sidebar);
    z-index:1100;
    transition: transform 0.3s ease;
    padding: 20px 0;
}
.sidebar.hidden { transform:translateX(-260px); }

.sidebar-profile {
    text-align: center;
    margin-bottom: 25px;
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
    color: var(--text-sidebar);
    font-weight: bold;
    margin: 0;
    font-size: 16px;
}

.sidebar h2 {
    text-align: center;
    color: var(--text-sidebar);
    margin-bottom: 25px;
    font-size: 22px;
    padding: 0 20px;
}

.sidebar a { 
    display:block; 
    padding:12px 20px; 
    color:var(--text-sidebar); 
    text-decoration:none; 
    transition: background 0.3s; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
}
.sidebar a:hover, .sidebar a.active { background:#1abc9c; color:white; }

.pending-badge {
    background: #ef4444;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    margin-left: auto;
}

/* ================= Overlay ================= */
.overlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.4); z-index:1050;
    display:none; opacity:0; transition: opacity 0.3s ease;
}
.overlay.active { display:block; opacity:1; }

/* ================= Main Content ================= */
.main-content { 
    margin-left:250px; 
    padding:30px;
    min-height:100vh;
    background: var(--bg-primary);
    transition: all 0.3s ease;
    width: calc(100% - 250px);
}

/* Content Wrapper */
.content-wrapper {
    background: var(--bg-card);
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 6px var(--shadow-color);
    min-height: calc(100vh - 60px);
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

/* Page Title */
.page-title {
    font-size: 1.8rem;
    color: var(--text-primary);
    margin-bottom: 25px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ================= Message Styles ================= */
.message {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.3s ease;
    box-shadow: 0 4px 6px var(--shadow-color);
    border-left: 4px solid;
}

@keyframes slideIn {
    from {
        transform: translateY(-10px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.message.success {
    background: linear-gradient(135deg, var(--success-bg), #bbf7d0);
    color: var(--success-text);
    border-color: var(--success-border);
}

.message.error {
    background: linear-gradient(135deg, var(--error-bg), #fecaca);
    color: var(--error-text);
    border-color: var(--error-border);
}

.message.warning {
    background: linear-gradient(135deg, var(--warning-bg), #fde68a);
    color: var(--warning-text);
    border-color: var(--warning-border);
}

.message i {
    font-size: 1.2rem;
}

/* ================= Form Styling ================= */
.form-section-title {
    color: var(--text-primary);
    margin-bottom: 15px;
    font-size: 1.2rem;
    font-weight: 600;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Room Form */
.room-form-wrapper {
    background: var(--bg-card);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    margin-bottom: 30px;
}

.room-form {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.form-group {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.room-form input {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    font-size: 14px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    transition: all 0.3s;
}
.room-form input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}

/* Required field indicator */
.required::after {
    content: " *";
    color: #ef4444;
}

/* Button Styles */
.btn { 
    padding: 12px 20px; 
    border-radius: 8px; 
    border: none; 
    cursor: pointer; 
    font-weight: 600; 
    font-size: 14px;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary { 
    background: #2563eb; 
    color: #fff; 
}
.btn-primary:hover { 
    background: #1d4ed8; 
    transform: translateY(-1px); 
}
.btn-primary:disabled {
    background: #94a3b8;
    cursor: not-allowed;
    transform: none;
}
.btn-danger { 
    background: #dc2626; 
    color: #fff; 
}
.btn-danger:hover { 
    background: #b91c1c; 
    transform: translateY(-1px); 
}

.cancel-btn { 
    text-decoration: none; 
    color: #dc2626; 
    margin-left: 10px; 
    font-weight: 500;
    padding: 10px 15px;
    border-radius: 8px;
    border: 1px solid #dc2626;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.cancel-btn:hover {
    background: #dc2626;
    color: white;
}

.form-actions { 
    display: flex; 
    gap: 10px; 
    align-items: center;
    grid-column: 1 / -1;
    margin-top: 10px;
}

/* Room validation feedback */
.room-checking {
    color: #f59e0b;
    font-size: 0.875rem;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

.room-error {
    color: #dc2626;
    font-size: 0.875rem;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

.room-success {
    color: #10b981;
    font-size: 0.875rem;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

input.valid {
    border-color: #10b981 !important;
    background: linear-gradient(90deg, var(--bg-secondary), #d1fae5) !important;
}

input.invalid {
    border-color: #dc2626 !important;
    background: linear-gradient(90deg, var(--bg-secondary), #fee2e2) !important;
}

input.checking {
    border-color: #f59e0b !important;
    background: linear-gradient(90deg, var(--bg-secondary), #fef3c7) !important;
}

/* ================= Table Styling ================= */
.table-section-title {
    color: var(--text-primary);
    margin: 30px 0 15px;
    font-size: 1.2rem;
    font-weight: 600;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-wrapper {
    position: relative; 
    background: var(--bg-card); 
    padding: 20px; 
    border-radius: 12px; 
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 6px var(--shadow-color);
    margin-top: 20px;
}

.table-container { 
    width: 100%; 
    overflow-x: auto; 
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: var(--border-color) var(--bg-secondary);
    max-height: 500px;
    overflow-y: auto;
}
.table-container::-webkit-scrollbar {
    height: 12px;
    width: 12px;
}
.table-container::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 6px;
}
.table-container::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 6px;
}
.table-container::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}

.room-table { 
    width: 100%; 
    min-width: 700px; 
    border-collapse: collapse; 
    font-size: 14px; 
}
.room-table thead th { 
    position: sticky; 
    top: 0; 
    background: var(--table-header); 
    color: var(--text-sidebar); 
    padding: 15px; 
    text-align: left; 
    font-weight: 700; 
    z-index: 5; 
}
.room-table th, .room-table td { 
    border-bottom: 1px solid var(--border-color); 
    padding: 15px; 
    color: var(--text-primary);
}
.room-table tbody tr:hover { 
    background: var(--hover-color); 
}
.room-table tbody tr:nth-child(even) { 
    background: var(--bg-secondary); 
}

/* Capacity Badge */
.capacity-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
}

/* Building Badge */
.building-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

/* Action Links */
.action-link { 
    text-decoration: none; 
    color: #2563eb; 
    font-weight: 500;
    margin: 0 5px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: color 0.3s;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
}
.action-link:hover { 
    color: #1d4ed8; 
    text-decoration: underline;
}
.action-link.delete { 
    color: #dc2626; 
}
.action-link.delete:hover { 
    color: #b91c1c; 
}

.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* Building header in table */
.building-header {
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb) !important;
    font-size: 1rem !important;
    color: var(--text-primary) !important;
    border-bottom: 2px solid var(--border-color);
}

[data-theme="dark"] .building-header {
    background: linear-gradient(135deg, #374151, #4b5563) !important;
}

/* Loading spinner */
.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* ================= Responsive ================= */
@media(max-width: 1200px){ 
    .main-content{ padding:25px; }
    .content-wrapper { padding: 20px; }
}
@media(max-width: 768px){
    .topbar{ display:flex; }
    .sidebar{ transform:translateX(-100%); }
    .sidebar.active{ transform:translateX(0); }
    .main-content{ 
        margin-left:0; 
        padding: 15px;
        padding-top: 80px;
        width: 100%;
    }
    .content-wrapper {
        padding: 15px;
        border-radius: 0;
    }
    .header { 
        flex-direction: column; 
        gap: 15px; 
        align-items: flex-start; 
    }
    .header h1 { font-size: 1.8rem; }
    
    .room-form {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-actions .btn,
    .form-actions .cancel-btn {
        width: 100%;
        margin: 5px 0;
        text-align: center;
        justify-content: center;
    }
    
    /* Mobile table card view */
    .room-table, .room-table thead, .room-table tbody, .room-table th, .room-table td, .room-table tr { 
        display: block; 
        width: 100%; 
    }
    .room-table thead tr { 
        position: absolute;
        top: -9999px;
        left: -9999px;
    }
    .room-table tr { 
        margin-bottom: 15px; 
        background: var(--bg-card); 
        border-radius: 10px; 
        box-shadow: 0 2px 5px var(--shadow-color); 
        padding: 15px; 
        border: 1px solid var(--border-color);
    }
    .room-table td { 
        text-align: right; 
        padding-left: 50%; 
        position: relative; 
        border: none; 
        margin-bottom: 10px;
    }
    .room-table td::before { 
        content: attr(data-label); 
        position: absolute; 
        left: 15px; 
        width: 45%; 
        text-align: left; 
        font-weight: bold; 
        color: var(--text-secondary);
    }
    
    .room-table td:last-child {
        text-align: center;
        padding-left: 15px;
    }
    .room-table td:last-child::before {
        display: none;
    }
    
    .action-buttons {
        justify-content: center;
        gap: 10px;
    }
    
    /* Adjust badges for mobile */
    .capacity-badge, .building-badge {
        font-size: 0.7rem;
        padding: 3px 8px;
    }
}

/* Dark mode specific adjustments */
[data-theme="dark"] .capacity-badge {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
}

[data-theme="dark"] .building-badge {
    background: linear-gradient(135deg, #059669, #047857);
}

[data-theme="dark"] .action-link {
    color: #60a5fa;
}

[data-theme="dark"] .action-link:hover {
    color: #3b82f6;
}
</style>
</head>
<body>

<!-- Mobile Topbar -->
<div class="topbar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <span>Manage Rooms</span>
</div>

<!-- Overlay for Mobile -->
<div class="overlay" onclick="toggleMenu()"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-profile">
        <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile Picture" id="sidebarProfilePic"
             onerror="this.onerror=null; this.src='../assets/default_profile.png';">
        <p><?= htmlspecialchars($current_user['username']) ?></p>
    </div>
    <h2>Admin Panel</h2>
    <a href="dashboard.php" class="<?= $current_page=='dashboard.php'?'active':'' ?>">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="manage_users.php" class="<?= $current_page=='manage_users.php'?'active':'' ?>">
        <i class="fas fa-users"></i> Manage Users
    </a>
    <a href="approve_users.php" class="<?= $current_page=='approve_users.php'?'active':'' ?>">
        <i class="fas fa-user-check"></i> Approve Users
        <?php if($pending_approvals > 0): ?>
            <span class="pending-badge"><?= $pending_approvals ?></span>
        <?php endif; ?>
    </a>
    <a href="manage_departments.php" class="<?= $current_page=='manage_departments.php'?'active':'' ?>">
        <i class="fas fa-building"></i> Manage Departments
    </a>
    <a href="manage_courses.php" class="<?= $current_page=='manage_courses.php'?'active':'' ?>">
        <i class="fas fa-book"></i> Manage Courses
    </a>
    <a href="manage_rooms.php" class="<?= $current_page=='manage_rooms.php'?'active':'' ?>">
        <i class="fas fa-door-closed"></i> Manage Rooms
    </a>
    <a href="manage_schedules.php" class="<?= $current_page=='manage_schedules.php'?'active':'' ?>">
        <i class="fas fa-calendar-alt"></i> Manage Schedule
    </a>
     <a href="assign_instructors.php" class="<?= $current_page=='assign_instructors.php'?'active':'' ?>">
        <i class="fas fa-user-graduate"></i> Assign Instructors
    </a>
      <a href="admin_exam_schedules.php" class="<?= $current_page=='admin_exam_schedules.php'?'active':'' ?>">
        <i class="fas fa-clipboard-list"></i> Exam Scheduling
    </a>
    <a href="manage_announcements.php" class="<?= $current_page=='manage_announcements.php'?'active':'' ?>">
        <i class="fas fa-bullhorn"></i> Manage Announcements
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
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Manage Rooms</h1>
                <p>Add, edit, or delete rooms for scheduling</p>
            </div>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_img_path) ?>" alt="Profile" id="headerProfilePic"
                     onerror="this.onerror=null; this.src='../assets/default_profile.png';">
                <div>
                    <div><?= htmlspecialchars($current_user['username']) ?></div>
                    <small>Administrator</small>
                </div>
            </div>
        </div>

        <!-- Display Error/Success Messages -->
        <?php if($message): ?>
            <div class="message <?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'error' ? 'exclamation-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'check-circle') ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Form Section -->
        <div class="form-section">
            <div class="form-section-title">
                <i class="fas fa-<?= isset($edit_room) ? 'edit' : 'plus-circle' ?>"></i>
                <?= isset($edit_room) ? "Edit Room" : "Add New Room" ?>
            </div>

            <div class="room-form-wrapper">
                <form method="POST" class="room-form" id="roomForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="room_id" value="<?= $edit_room['room_id'] ?? '' ?>">
                    
                    <div class="form-group">
                        <label class="required">Room Name:</label>
                        <input type="text" name="room_name" id="room-name" placeholder="e.g., Classroom 201, Lab A" required 
                               value="<?= isset($edit_room['room_name']) ? htmlspecialchars($edit_room['room_name']) : '' ?>"
                               oninput="checkRoomAvailability()">
                        <div id="room-name-feedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Capacity:</label>
                        <input type="number" name="capacity" id="capacity" placeholder="Number of seats" min="1" max="500" required 
                               value="<?= isset($edit_room['capacity']) ? htmlspecialchars($edit_room['capacity']) : '30' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Building:</label>
                        <input type="text" name="building" id="building" placeholder="e.g., Main Building, Science Block" required 
                               value="<?= isset($edit_room['building']) ? htmlspecialchars($edit_room['building']) : '' ?>"
                               oninput="checkRoomAvailability()">
                    </div>
                    
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit" name="<?= isset($edit_room) ? 'edit_room' : 'add_room' ?>" id="submit-btn" <?= isset($edit_room) ? '' : 'disabled' ?>>
                            <i class="fas fa-<?= isset($edit_room) ? 'save' : 'plus' ?>"></i>
                            <?= isset($edit_room) ? 'Update Room' : 'Add Room' ?>
                        </button>
                        <?php if(isset($edit_room)): ?>
                            <a href="manage_rooms.php" class="cancel-btn">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Rooms Table Section -->
        <div class="table-section">
            <div class="table-section-title">
                <i class="fas fa-list"></i>
                Existing Rooms (<?= count($rooms) ?>)
            </div>

            <div class="table-wrapper">
                <div class="table-container">
                    <table class="room-table" id="roomTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Room Name</th>
                                <th>Capacity</th>
                                <th>Building</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($rooms)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:30px; color:var(--text-secondary);">
                                        No rooms found. Add your first room above.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $currentBuilding = '';
                                foreach($rooms as $r): 
                                    if($r['building'] !== $currentBuilding):
                                        $currentBuilding = $r['building'];
                                ?>
                                <tr class="building-header">
                                    <td colspan="5" style="text-align:left;">
                                        <i class="fas fa-building"></i> <?= htmlspecialchars($currentBuilding) ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr data-label="Room">
                                    <td data-label="ID"><?= $r['room_id'] ?></td>
                                    <td data-label="Room Name">
                                        <strong><?= htmlspecialchars($r['room_name']) ?></strong>
                                    </td>
                                    <td data-label="Capacity">
                                        <span class="capacity-badge">
                                            <i class="fas fa-users"></i> <?= htmlspecialchars($r['capacity']) ?> seats
                                        </span>
                                    </td>
                                    <td data-label="Building">
                                        <span class="building-badge">
                                            <i class="fas fa-building"></i> <?= htmlspecialchars($r['building']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="action-buttons">
                                            <a class="action-link" href="?edit=<?= $r['room_id'] ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete(this, '<?= htmlspecialchars(addslashes($r['room_name'])) ?>')">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="room_id" value="<?= $r['room_id'] ?>">
                                                <button type="submit" name="delete_room" class="action-link delete">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Hamburger toggle
function toggleMenu(){
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

// DOM elements
const roomNameInput = document.getElementById('room-name');
const buildingInput = document.getElementById('building');
const capacityInput = document.getElementById('capacity');
const roomNameFeedback = document.getElementById('room-name-feedback');
const submitBtn = document.getElementById('submit-btn');

let roomValid = false;
let checkingTimeout = null;

// Check Room uniqueness
function checkRoomAvailability() {
    const roomName = roomNameInput.value.trim();
    const building = buildingInput.value.trim();
    const editing = <?= isset($edit_room) ? 'true' : 'false' ?>;
    const roomId = <?= isset($edit_room) ? $edit_room['room_id'] : 'null' ?>;
    
    // Clear any existing timeout
    if (checkingTimeout) {
        clearTimeout(checkingTimeout);
    }
    
    // Reset
    roomNameInput.classList.remove('valid', 'invalid', 'checking');
    roomValid = false;
    updateSubmitButton();
    
    if (!roomName || !building) {
        roomNameFeedback.innerHTML = '';
        roomValid = false;
        updateSubmitButton();
        return;
    }
    
    // Debounce the check to avoid too many requests
    checkingTimeout = setTimeout(() => {
        // Show checking state
        roomNameInput.classList.add('checking');
        roomNameFeedback.innerHTML = '<span class="room-checking"><i class="fas fa-spinner fa-spin spinner"></i> Checking room availability...</span>';
        
        // AJAX request
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'check_room_availability.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.available) {
                        roomNameInput.classList.remove('checking');
                        roomNameInput.classList.add('valid');
                        roomNameFeedback.innerHTML = '<span class="room-success"><i class="fas fa-check-circle"></i> Room is available!</span>';
                        roomValid = true;
                    } else {
                        roomNameInput.classList.remove('checking');
                        roomNameInput.classList.add('invalid');
                        roomNameFeedback.innerHTML = `<span class="room-error"><i class="fas fa-exclamation-circle"></i> ${response.message || 'Room already exists!'}</span>`;
                        roomValid = false;
                    }
                } catch (e) {
                    handleCheckError();
                }
            } else {
                handleCheckError();
            }
            updateSubmitButton();
        };
        
        xhr.onerror = handleCheckError;
        
        function handleCheckError() {
            roomNameInput.classList.remove('checking');
            roomNameFeedback.innerHTML = '<span class="room-error"><i class="fas fa-exclamation-circle"></i> Error checking room availability</span>';
            roomValid = false;
            updateSubmitButton();
        }
        
        xhr.send(`room_name=${encodeURIComponent(roomName)}&building=${encodeURIComponent(building)}&editing=${editing}&room_id=${roomId}`);
    }, 500); // 500ms debounce
}

// Update submit button state
function updateSubmitButton() {
    const roomName = roomNameInput.value.trim();
    const building = buildingInput.value.trim();
    const capacity = parseInt(capacityInput.value) || 0;
    
    let enabled = true;
    
    if (!roomName || !building || capacity <= 0 || capacity > 500) {
        enabled = false;
    }
    
    if (!roomValid && roomName && building) {
        enabled = false;
    }
    
    submitBtn.disabled = !enabled;
}

// Simple form validation (server-side validation is still primary)
function validateForm() {
    const roomName = roomNameInput.value.trim();
    const building = buildingInput.value.trim();
    const capacity = parseInt(capacityInput.value) || 0;
    
    if (!roomName || !building) {
        alert('Please fill in all required fields');
        return false;
    }
    
    if (capacity <= 0 || capacity > 500) {
        alert('Capacity must be between 1 and 500');
        capacityInput.focus();
        return false;
    }
    
    return true;
}

// Confirm delete with room name
function confirmDelete(form, roomName) {
    return confirm(`Are you sure you want to delete the room "${roomName}"? This action cannot be undone.`);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Set active nav
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.sidebar a').forEach(link => {
        const linkPage = link.getAttribute('href');
        if (linkPage === currentPage) {
            link.classList.add('active');
        }
    });
    
    // Initialize submit button state
    submitBtn.disabled = <?= isset($edit_room) ? 'false' : 'true' ?>;
    
    // Event listeners
    roomNameInput.addEventListener('input', checkRoomAvailability);
    buildingInput.addEventListener('input', checkRoomAvailability);
    capacityInput.addEventListener('input', updateSubmitButton);
    
    // Initial validation
    updateSubmitButton();
    
    // If editing a room, check availability
    <?php if(isset($edit_room)): ?>
        setTimeout(() => {
            if (roomNameInput.value.trim() && buildingInput.value.trim()) {
                roomValid = true; // Assume valid for editing
                updateSubmitButton();
            }
        }, 500);
    <?php endif; ?>
    
    // Add data-labels for mobile table view
    const tableHeaders = document.querySelectorAll('#roomTable thead th');
    const tableRows = document.querySelectorAll('#roomTable tbody tr:not(.building-header)');
    
    tableRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        cells.forEach((cell, index) => {
            if(tableHeaders[index]) {
                cell.setAttribute('data-label', tableHeaders[index].textContent);
            }
        });
    });
    
    // Animate table rows
    tableRows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            row.style.transition = 'all 0.5s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateX(0)';
        }, index * 50);
    });
    
    // Profile picture fallback
    document.querySelectorAll('img').forEach(img => {
        img.addEventListener('error', function() {
            if (!this.src.includes('default_profile.png')) {
                this.src = '../assets/default_profile.png';
            }
        });
    });
    
    // Confirm logout
    document.querySelector('a[href="../logout.php"]')?.addEventListener('click', function(e) {
        if(!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e){
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.querySelector('.menu-btn');
        if(window.innerWidth <= 768 && sidebar.classList.contains('active') && 
           !sidebar.contains(e.target) && !menuBtn.contains(e.target)){
            sidebar.classList.remove('active');
            document.querySelector('.overlay').classList.remove('active');
        }
    });
});
</script>

</body>
</html>