<?php
session_start();
require __DIR__ . '/../../includes/db.php';

// Only admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../index.php");
    exit;
}

// Include dark mode
include __DIR__ . '/../includes/darkmode.php';

// CSRF Token
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

// Handle new announcement
if(isset($_POST['post_announcement'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $title = trim($_POST['title'] ?? '');
        $message_text = trim($_POST['message'] ?? '');
        $attachment = $_FILES['attachment']['name'] ?? '';
        
        // Validate inputs
        if(empty($title) || empty($message_text)){
            $message = "Title and message are required!";
            $message_type = "error";
        } else {
            try {
                // Handle file upload if exists
                if(!empty($attachment) && isset($_FILES['attachment']['tmp_name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK){
                    $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                    $file_extension = strtolower(pathinfo($attachment, PATHINFO_EXTENSION));
                    
                    if(!in_array($file_extension, $allowed_extensions)){
                        $message = "Invalid file type. Allowed: PDF, DOC, JPG, PNG";
                        $message_type = "error";
                    } else {
                        // Create uploads directory if it doesn't exist
                        $upload_dir = __DIR__ . '/../../uploads/announcements/';
                        if(!is_dir($upload_dir)){
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        // Generate unique filename
                        $unique_name = time() . '_' . uniqid() . '.' . $file_extension;
                        $upload_path = $upload_dir . $unique_name;
                        
                        if(move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)){
                            $attachment = 'announcements/' . $unique_name;
                        } else {
                            $message = "Failed to upload file.";
                            $message_type = "error";
                        }
                    }
                }
                
                if($message_type !== 'error'){
                    $stmt = $pdo->prepare("INSERT INTO announcements (title, message, attachment, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$title, $message_text, $attachment, $_SESSION['user_id']]);
                    $message = "Announcement posted successfully!";
                    $message_type = "success";
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Handle deletion
if(isset($_POST['delete'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $delete_id = (int)($_POST['delete_id'] ?? 0);
        
        try {
            // First get the announcement to check for attachment
            $stmt = $pdo->prepare("SELECT attachment FROM announcements WHERE announcement_id=?");
            $stmt->execute([$delete_id]);
            $announcement = $stmt->fetch();
            
            if($announcement && !empty($announcement['attachment'])){
                // Delete the attachment file
                $file_path = __DIR__ . '/../../uploads/' . $announcement['attachment'];
                if(file_exists($file_path)){
                    unlink($file_path);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id=?");
            $stmt->execute([$delete_id]);
            $message = "Announcement deleted successfully!";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Handle update
if(isset($_POST['update_announcement'])){
    // CSRF validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $message_text = trim($_POST['message'] ?? '');
        
        // Validate inputs
        if(empty($id) || empty($title) || empty($message_text)){
            $message = "All fields are required!";
            $message_type = "error";
        } else {
            try {
                // Handle file upload if exists
                $attachment_sql = "";
                $attachment_params = [];
                
                if(isset($_FILES['attachment']['name']) && !empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK){
                    $attachment = $_FILES['attachment']['name'];
                    $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                    $file_extension = strtolower(pathinfo($attachment, PATHINFO_EXTENSION));
                    
                    if(!in_array($file_extension, $allowed_extensions)){
                        $message = "Invalid file type. Allowed: PDF, DOC, JPG, PNG";
                        $message_type = "error";
                    } else {
                        // Create uploads directory if it doesn't exist
                        $upload_dir = __DIR__ . '/../../uploads/announcements/';
                        if(!is_dir($upload_dir)){
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        // Generate unique filename
                        $unique_name = time() . '_' . uniqid() . '.' . $file_extension;
                        $upload_path = $upload_dir . $unique_name;
                        
                        if(move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)){
                            // Get old attachment to delete
                            $stmt = $pdo->prepare("SELECT attachment FROM announcements WHERE announcement_id=?");
                            $stmt->execute([$id]);
                            $old_announcement = $stmt->fetch();
                            
                            if($old_announcement && !empty($old_announcement['attachment'])){
                                $old_file = __DIR__ . '/../../uploads/' . $old_announcement['attachment'];
                                if(file_exists($old_file)){
                                    unlink($old_file);
                                }
                            }
                            
                            $attachment_sql = ", attachment=?";
                            $attachment = 'announcements/' . $unique_name;
                            $attachment_params[] = $attachment;
                        } else {
                            $message = "Failed to upload file.";
                            $message_type = "error";
                        }
                    }
                }
                
                if($message_type !== 'error'){
                    $stmt = $pdo->prepare("UPDATE announcements SET title=?, message=?, updated_at=NOW() $attachment_sql WHERE announcement_id=?");
                    $params = array_merge([$title, $message_text], $attachment_params, [$id]);
                    $stmt->execute($params);
                    $message = "Announcement updated successfully!";
                    $message_type = "success";
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Fetch pending approvals count
$pending_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_approved = 0");
$pending_approvals = $pending_stmt->fetchColumn() ?: 0;

// Fetch announcements
try {
    $announcements_stmt = $pdo->query("
        SELECT a.*, u.username AS author
        FROM announcements a
        LEFT JOIN users u ON a.created_by = u.user_id
        ORDER BY a.created_at DESC
    ");
    $announcements = $announcements_stmt->fetchAll() ?: [];
} catch (Exception $e) {
    $announcements = [];
    // Check if announcements table exists, if not create it
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS announcements (
            announcement_id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            attachment VARCHAR(255),
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME,
            FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
        )
    ");
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Announcements - DKU Scheduler</title>
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

/* Announcement Form */
.announcement-form-wrapper {
    background: var(--bg-card);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-primary);
    font-size: 0.9rem;
}

/* Required field indicator */
.required::after {
    content: " *";
    color: #ef4444;
}

.form-group input[type="text"],
.form-group textarea,
.form-group input[type="file"] {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    font-size: 14px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    transition: all 0.3s;
}
.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}

.form-group textarea {
    min-height: 150px;
    resize: vertical;
    font-family: inherit;
    line-height: 1.5;
}

/* File upload styling */
.file-upload {
    position: relative;
    display: inline-block;
    width: 100%;
}

.file-upload input[type="file"] {
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.file-upload-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 12px 15px;
    background: var(--bg-secondary);
    border: 2px dashed var(--border-color);
    border-radius: 8px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s;
    min-height: 46px;
}

.file-upload-label:hover {
    background: var(--hover-color);
    border-color: #2563eb;
    color: var(--text-primary);
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

.form-actions { 
    display: flex; 
    gap: 10px; 
    align-items: center;
    margin-top: 20px;
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
}
.table-container::-webkit-scrollbar {
    height: 12px;
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

.announcement-table { 
    width: 100%; 
    min-width: 800px; 
    border-collapse: collapse; 
    font-size: 14px; 
}
.announcement-table thead th { 
    position: sticky; 
    top: 0; 
    background: var(--table-header); 
    color: var(--text-sidebar); 
    padding: 15px; 
    text-align: left; 
    font-weight: 700; 
    z-index: 5; 
}
.announcement-table th, .announcement-table td { 
    border-bottom: 1px solid var(--border-color); 
    padding: 15px; 
    color: var(--text-primary);
}
.announcement-table tbody tr:hover { 
    background: var(--hover-color); 
}
.announcement-table tbody tr:nth-child(even) { 
    background: var(--bg-secondary); 
}

/* Date Badge */
.date-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
}

/* Attachment Badge */
.attachment-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    background: var(--bg-secondary);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
    text-decoration: none;
    transition: all 0.3s;
}
.attachment-badge:hover {
    background: var(--hover-color);
    border-color: #2563eb;
    color: var(--text-primary);
    text-decoration: none;
}

/* Action Links */
.action-link { 
    text-decoration: none; 
    font-weight: 500;
    margin: 0 5px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
}
.action-link.view { 
    background: #2563eb; 
    color: #fff; 
}
.action-link.view:hover { 
    background: #1d4ed8; 
    transform: translateY(-1px); 
}
.action-link.edit { 
    background: #10b981; 
    color: #fff; 
}
.action-link.edit:hover { 
    background: #059669; 
    transform: translateY(-1px); 
}
.action-link.delete { 
    background: #dc2626; 
    color: #fff; 
}
.action-link.delete:hover { 
    background: #b91c1c; 
    transform: translateY(-1px); 
}

.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* ================= Modal Styling ================= */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 2000;
    justify-content: center;
    align-items: center;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 30px;
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    position: relative;
    border: 1px solid var(--border-color);
}

.modal-close {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 24px;
    color: var(--text-secondary);
    cursor: pointer;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s;
}

.modal-close:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
    transform: rotate(90deg);
}

.modal-title {
    color: var(--text-primary);
    margin-bottom: 20px;
    font-size: 1.5rem;
    font-weight: 600;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
}

.modal-body {
    color: var(--text-primary);
    line-height: 1.6;
}

.modal-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.modal-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
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
    
    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-actions .btn {
        width: 100%;
        margin: 5px 0;
        text-align: center;
        justify-content: center;
    }
    
    /* Mobile table card view */
    .announcement-table, .announcement-table thead, .announcement-table tbody, 
    .announcement-table th, .announcement-table td, .announcement-table tr { 
        display: block; 
        width: 100%; 
    }
    .announcement-table thead tr { 
        position: absolute;
        top: -9999px;
        left: -9999px;
    }
    .announcement-table tr { 
        margin-bottom: 15px; 
        background: var(--bg-card); 
        border-radius: 10px; 
        box-shadow: 0 2px 5px var(--shadow-color); 
        padding: 15px; 
        border: 1px solid var(--border-color);
    }
    .announcement-table td { 
        text-align: right; 
        padding-left: 50%; 
        position: relative; 
        border: none; 
        margin-bottom: 10px;
    }
    .announcement-table td::before { 
        content: attr(data-label); 
        position: absolute; 
        left: 15px; 
        width: 45%; 
        text-align: left; 
        font-weight: bold; 
        color: var(--text-secondary);
    }
    
    .announcement-table td:last-child {
        text-align: center;
        padding-left: 15px;
    }
    .announcement-table td:last-child::before {
        display: none;
    }
    
    .action-buttons {
        justify-content: center;
        gap: 10px;
    }
    
    /* Adjust badges for mobile */
    .date-badge, .attachment-badge {
        font-size: 0.7rem;
        padding: 3px 8px;
    }
    
    /* Modal adjustments for mobile */
    .modal-content {
        width: 95%;
        padding: 20px;
    }
}

/* Dark mode specific adjustments */
[data-theme="dark"] .action-link.view {
    background: #3b82f6;
}

[data-theme="dark"] .action-link.edit {
    background: #10b981;
}

[data-theme="dark"] .action-link.delete {
    background: #dc2626;
}

[data-theme="dark"] .file-upload-label:hover {
    border-color: #3b82f6;
}

[data-theme="dark"] .attachment-badge:hover {
    border-color: #3b82f6;
}
</style>
</head>
<body>

<!-- Mobile Topbar -->
<div class="topbar">
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
    <span>Manage Announcements</span>
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
                <h1>Manage Announcements</h1>
                <p>Create and manage announcements for all users</p>
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
                <i class="fas fa-<?= $message_type === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Form Section -->
        <div class="form-section">
            <div class="form-section-title">
                <i class="fas fa-plus-circle"></i>
                Create New Announcement
            </div>

            <div class="announcement-form-wrapper">
                <form method="POST" enctype="multipart/form-data" id="announcementForm" onsubmit="return validateForm()">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="form-group">
                        <label class="required">Announcement Title:</label>
                        <input type="text" name="title" id="title" placeholder="Enter announcement title" required 
                               maxlength="255" oninput="updateSubmitButton()">
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Announcement Content:</label>
                        <textarea name="message" id="message" placeholder="Enter announcement content" required 
                                  oninput="updateSubmitButton()"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Attachment (Optional):</label>
                        <div class="file-upload">
                            <input type="file" name="attachment" id="attachment" 
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" 
                                   onchange="updateFileName()">
                            <label for="attachment" class="file-upload-label">
                                <i class="fas fa-paperclip"></i>
                                <span id="file-name">Choose file (PDF, DOC, JPG, PNG)</span>
                                <span style="font-size:0.8rem; color:var(--text-secondary);">
                                    Max 5MB
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit" name="post_announcement" id="submit-btn" disabled>
                            <i class="fas fa-bullhorn"></i>
                            Post Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Announcements Table Section -->
        <div class="table-section">
            <div class="table-section-title">
                <i class="fas fa-list"></i>
                All Announcements (<?= count($announcements) ?>)
            </div>

            <div class="table-wrapper">
                <div class="table-container">
                    <table class="announcement-table" id="announcementTable">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Date</th>
                                <th>Attachment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($announcements)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:30px; color:var(--text-secondary);">
                                        No announcements found. Create your first announcement above.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($announcements as $a): 
                                    $date = isset($a['created_at']) ? date('M d, Y - h:i A', strtotime($a['created_at'])) : 'N/A';
                                    $attachment_name = basename($a['attachment'] ?? '');
                                ?>
                                <tr data-label="Announcement">
                                    <td data-label="Title">
                                        <strong><?= htmlspecialchars($a['title'] ?? 'Untitled') ?></strong>
                                    </td>
                                    <td data-label="Author"><?= htmlspecialchars($a['author'] ?? 'Unknown') ?></td>
                                    <td data-label="Date">
                                        <span class="date-badge">
                                            <i class="far fa-calendar"></i> <?= $date ?>
                                        </span>
                                    </td>
                                    <td data-label="Attachment">
                                        <?php if(!empty($a['attachment'])): ?>
                                            <a href="../../uploads/<?= htmlspecialchars($a['attachment']) ?>" 
                                               target="_blank" class="attachment-badge">
                                                <i class="fas fa-paperclip"></i> 
                                                <?= htmlspecialchars($attachment_name) ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary); font-style:italic;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="action-buttons">
                                            <button class="action-link view" onclick="openModal('view', <?= $a['announcement_id'] ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="action-link edit" onclick="openModal('edit', <?= $a['announcement_id'] ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" style="display:inline;" 
                                                  onsubmit="return confirmDelete(this, '<?= htmlspecialchars(addslashes($a['title'] ?? 'Untitled')) ?>')">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="delete_id" value="<?= $a['announcement_id'] ?>">
                                                <button type="submit" name="delete" class="action-link delete">
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

<!-- Modal -->
<div class="modal" id="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">×</span>
        <div id="modal-body"></div>
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
const titleInput = document.getElementById('title');
const messageInput = document.getElementById('message');
const attachmentInput = document.getElementById('attachment');
const fileNameSpan = document.getElementById('file-name');
const submitBtn = document.getElementById('submit-btn');

// Update file name display
function updateFileName() {
    const file = attachmentInput.files[0];
    if(file) {
        fileNameSpan.textContent = file.name;
        
        // Validate file size (5MB max)
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if(file.size > maxSize) {
            alert('File size exceeds 5MB limit. Please choose a smaller file.');
            attachmentInput.value = '';
            fileNameSpan.textContent = 'Choose file (PDF, DOC, JPG, PNG)';
        }
    } else {
        fileNameSpan.textContent = 'Choose file (PDF, DOC, JPG, PNG)';
    }
    updateSubmitButton();
}

// Update submit button state
function updateSubmitButton() {
    const title = titleInput.value.trim();
    const message = messageInput.value.trim();
    const file = attachmentInput.files[0];
    
    let enabled = true;
    
    if (!title || !message) {
        enabled = false;
    }
    
    // Validate file if exists
    if(file) {
        const allowedTypes = ['application/pdf', 'application/msword', 
                             'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                             'image/jpeg', 'image/jpg', 'image/png'];
        
        if(!allowedTypes.includes(file.type)) {
            enabled = false;
        }
        
        const maxSize = 5 * 1024 * 1024;
        if(file.size > maxSize) {
            enabled = false;
        }
    }
    
    submitBtn.disabled = !enabled;
}

// Form validation
function validateForm() {
    const title = titleInput.value.trim();
    const message = messageInput.value.trim();
    const file = attachmentInput.files[0];
    
    if (!title || !message) {
        alert('Title and message are required!');
        return false;
    }
    
    if (file) {
        const allowedTypes = ['application/pdf', 'application/msword', 
                             'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                             'image/jpeg', 'image/jpg', 'image/png'];
        
        if(!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Allowed: PDF, DOC, JPG, PNG');
            return false;
        }
        
        const maxSize = 5 * 1024 * 1024;
        if(file.size > maxSize) {
            alert('File size exceeds 5MB limit. Please choose a smaller file.');
            return false;
        }
    }
    
    return true;
}

// Confirm delete with announcement title
function confirmDelete(form, title) {
    return confirm(`Are you sure you want to delete the announcement "${title}"? This action cannot be undone.`);
}

// Modal functionality
const announcements = <?php echo json_encode($announcements); ?>;
const modal = document.getElementById('modal');
const modalBody = document.getElementById('modal-body');

function openModal(type, id){
    const a = announcements.find(x => x.announcement_id == id);
    if(!a) return;
    
    if(type === 'view'){
        const date = a.created_at ? new Date(a.created_at).toLocaleDateString('en-US', {
            year: 'numeric', month: 'long', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        }) : 'N/A';
        
        modalBody.innerHTML = `
            <div class="modal-title">${escapeHtml(a.title)}</div>
            <div class="modal-meta">
                <span><i class="fas fa-user"></i> ${escapeHtml(a.author || 'Unknown')}</span>
                <span><i class="far fa-calendar"></i> ${escapeHtml(date)}</span>
            </div>
            <div class="modal-body">
                <p>${escapeHtml(a.message || '').replace(/\n/g, '<br>')}</p>
                ${a.attachment ? `
                <div style="margin-top:20px; padding-top:15px; border-top:1px solid var(--border-color);">
                    <strong><i class="fas fa-paperclip"></i> Attachment:</strong>
                    <p style="margin-top:5px;">
                        <a href="../../uploads/${escapeHtml(a.attachment)}" target="_blank" 
                           style="color:#2563eb; text-decoration:none;">
                           ${escapeHtml(basename(a.attachment))}
                        </a>
                    </p>
                </div>` : ''}
            </div>
        `;
    } else if(type === 'edit'){
        modalBody.innerHTML = `
            <div class="modal-title">Edit Announcement</div>
            <form method="post" enctype="multipart/form-data" id="editForm" onsubmit="return validateEditForm(this)">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="id" value="${a.announcement_id}">
                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:500; color:var(--text-primary);">
                        Title:
                    </label>
                    <input type="text" name="title" value="${escapeHtml(a.title)}" 
                           style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-secondary); color:var(--text-primary);"
                           required maxlength="255">
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:500; color:var(--text-primary);">
                        Content:
                    </label>
                    <textarea name="message" rows="6" 
                              style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-secondary); color:var(--text-primary); resize:vertical;"
                              required>${escapeHtml(a.message || '')}</textarea>
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block; margin-bottom:5px; font-weight:500; color:var(--text-primary);">
                        Attachment (Leave empty to keep current):
                    </label>
                    <input type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                           style="width:100%; padding:8px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-secondary); color:var(--text-primary);">
                </div>
                <button type="submit" name="update_announcement" 
                        style="background:#2563eb; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:600; transition:all 0.3s;">
                    <i class="fas fa-save"></i> Update Announcement
                </button>
            </form>
        `;
    }
    
    modal.style.display = 'flex';
}

function closeModal(){
    modal.style.display = 'none';
}

// Helper functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function basename(path) {
    return path.split('/').pop().split('\\').pop();
}

function validateEditForm(form) {
    const title = form.querySelector('input[name="title"]').value.trim();
    const message = form.querySelector('textarea[name="message"]').value.trim();
    const file = form.querySelector('input[name="attachment"]').files[0];
    
    if (!title || !message) {
        alert('Title and message are required!');
        return false;
    }
    
    if (file) {
        const allowedTypes = ['application/pdf', 'application/msword', 
                             'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                             'image/jpeg', 'image/jpg', 'image/png'];
        
        if(!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Allowed: PDF, DOC, JPG, PNG');
            return false;
        }
        
        const maxSize = 5 * 1024 * 1024;
        if(file.size > maxSize) {
            alert('File size exceeds 5MB limit. Please choose a smaller file.');
            return false;
        }
    }
    
    return true;
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
    
    // Event listeners
    titleInput.addEventListener('input', updateSubmitButton);
    messageInput.addEventListener('input', updateSubmitButton);
    
    // Add data-labels for mobile table view
    const tableHeaders = document.querySelectorAll('#announcementTable thead th');
    const tableRows = document.querySelectorAll('#announcementTable tbody tr');
    
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
    
    // Close modal when clicking outside
    window.onclick = function(e) {
        if(e.target == modal) {
            closeModal();
        }
    };
});

// Fallback for broken profile pictures
function handleImageError(img) {
    img.onerror = null;
    img.src = '../assets/default_profile.png';
    return true;
}
</script>

</body>
</html>