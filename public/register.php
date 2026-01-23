<?php
session_start();

// Adjust the path to correctly find the includes directory
$root_dir = dirname(dirname(__FILE__));
require $root_dir . '/includes/db.php';

// Include dark mode - adjust path
$darkmode_file = $root_dir . '/includes/darkmode.php';
if (file_exists($darkmode_file)) {
    include $darkmode_file;
} else {
    $darkMode = false; // Default if darkmode file doesn't exist
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";
$message_type = "success";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    } else {
        // Get form data
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $role = $_POST['role']; // 'student' or 'instructor'
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : NULL;
        $student_id = ($role === 'student' && isset($_POST['student_id'])) ? trim($_POST['student_id']) : NULL;
        
        // Get year based on student type
        $year = NULL;
        if ($role === 'student') {
            if (isset($_POST['regular_year']) && !empty($_POST['regular_year'])) {
                $year = trim($_POST['regular_year']);
            } elseif (isset($_POST['extension_year']) && !empty($_POST['extension_year'])) {
                $year = trim($_POST['extension_year']);
            }
        }
        
        // Validate required fields
        if (empty($username) || empty($full_name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
            $message = "Please fill in all required fields!";
            $message_type = "error";
        } elseif (!preg_match('/^[a-zA-Z\s.\'-]+$/', $full_name)) {
            $message = "Full name can only contain letters, spaces, dots, apostrophes and hyphens!";
            $message_type = "error";
        } elseif (!preg_match('/^[a-zA-Z_]+$/', $username)) {
            $message = "Username can only contain letters and underscores! Numbers are not allowed.";
            $message_type = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address!";
            $message_type = "error";
        } elseif (strlen($password) < 8) {
            $message = "Password must be at least 8 characters long!";
            $message_type = "error";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $message = "Password must contain at least one uppercase letter!";
            $message_type = "error";
        } elseif (!preg_match('/[a-z]/', $password)) {
            $message = "Password must contain at least one lowercase letter!";
            $message_type = "error";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $message = "Password must contain at least one number!";
            $message_type = "error";
        } elseif ($password !== $confirm_password) {
            $message = "Passwords do not match!";
            $message_type = "error";
        } else {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $message = "Username or email already exists!";
                $message_type = "error";
            } else {
                // Check Student ID uniqueness for students
                if ($role === 'student' && $student_id) {
                    $check_stmt = $pdo->prepare("SELECT user_id FROM users WHERE student_id = ?");
                    $check_stmt->execute([$student_id]);
                    if ($check_stmt->fetch()) {
                        $message = "Student ID '$student_id' already exists!";
                        $message_type = "error";
                    } else {
                        // Proceed with registration
                        registerUser();
                    }
                } else {
                    // For instructors, proceed normally
                    registerUser();
                }
            }
        }
    }
}

function registerUser() {
    global $pdo, $username, $full_name, $email, $password, $role, $department_id, $student_id, $year, $message, $message_type;
    
    try {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Upload profile picture if provided
        $profile_picture = uploadProfilePicture();
        
        // Upload supporting documents
        $documents = uploadDocuments();
        
        // Prepare SQL
        $sql = "INSERT INTO users (username, full_name, email, password, role, department_id, student_id, year, profile_picture, is_approved) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $full_name, $email, $hashed_password, $role, $department_id, $student_id, $year, $profile_picture]);
        
        // Save uploaded documents to database
        $user_id = $pdo->lastInsertId();
        saveDocuments($user_id, $documents);
        
        $message = "Registration successful! Your account is pending approval. You'll receive an email when approved.";
        $message_type = "success";
        
        // Clear form
        $_POST = array();
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
        error_log("Registration error: " . $e->getMessage());
    }
}

function uploadProfilePicture() {
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
        return NULL;
    }
    
    $file = $_FILES['profile_picture'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Profile picture upload failed with error code: " . $file['error']);
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed for profile pictures.");
    }
    
    // Validate file size (max 2MB)
    $max_size = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $max_size) {
        throw new Exception("Profile picture is too large. Maximum size is 2MB.");
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('profile_', true) . '.' . $extension;
    
    // Create uploads directory if it doesn't exist
    $upload_dir = dirname(dirname(__FILE__)) . '/uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Move uploaded file
    $destination = $upload_dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Failed to save profile picture.");
    }
    
    return $filename;
}

function uploadDocuments() {
    $documents = [];
    
    if (!isset($_FILES['documents']) || !is_array($_FILES['documents']['name'])) {
        return $documents;
    }
    
    $files = $_FILES['documents'];
    $upload_dir = dirname(dirname(__FILE__)) . '/uploads/documents/';
    
    // Create documents directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Loop through uploaded files
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            throw new Exception("Document upload failed for file: " . $files['name'][$i]);
        }
        
        // Validate file type
        $allowed_types = [
            'application/pdf',
            'image/jpeg', 'image/jpg', 'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        $file_type = mime_content_type($files['tmp_name'][$i]);
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception("Invalid document type for file: " . $files['name'][$i] . ". Allowed: PDF, JPG, PNG, DOC, DOCX");
        }
        
        // Validate file size (max 5MB per file)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($files['size'][$i] > $max_size) {
            throw new Exception("Document is too large: " . $files['name'][$i] . ". Maximum size is 5MB.");
        }
        
        // Generate unique filename
        $extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $filename = uniqid('doc_', true) . '.' . $extension;
        $original_name = $files['name'][$i];
        
        // Move uploaded file
        $destination = $upload_dir . $filename;
        if (!move_uploaded_file($files['tmp_name'][$i], $destination)) {
            throw new Exception("Failed to save document: " . $original_name);
        }
        
        $documents[] = [
            'filename' => $filename,
            'original_name' => $original_name,
            'type' => $file_type
        ];
    }
    
    return $documents;
}

function saveDocuments($user_id, $documents) {
    global $pdo;
    
    if (empty($documents)) {
        return;
    }
    
    $stmt = $pdo->prepare("INSERT INTO user_documents (user_id, filename, original_name, file_type) VALUES (?, ?, ?, ?)");
    
    foreach ($documents as $doc) {
        $stmt->execute([$user_id, $doc['filename'], $doc['original_name'], $doc['type']]);
    }
}

// Fetch departments
$departments = $pdo->query("SELECT * FROM departments ORDER BY department_name")->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($darkMode) && $darkMode ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - DKU Scheduler</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Use correct path for CSS -->
<link rel="stylesheet" href="<?= $root_dir ?>/assets/css/darkmode.css">
<style>
/* ================= General Styles ================= */
:root {
    --bg-primary: #f8f9fa;
    --bg-secondary: #ffffff;
    --bg-card: #ffffff;
    --text-primary: #333333;
    --text-secondary: #666666;
    --border-color: #dee2e6;
    --shadow-color: rgba(0,0,0,0.1);
    --primary-color: #3498db;
    --success-color: #2ecc71;
    --error-color: #e74c3c;
}

[data-theme="dark"] {
    --bg-primary: #1a1a1a;
    --bg-secondary: #2d2d2d;
    --bg-card: #2d2d2d;
    --text-primary: #e0e0e0;
    --text-secondary: #b0b0b0;
    --border-color: #404040;
    --shadow-color: rgba(0,0,0,0.3);
    --primary-color: #2980b9;
    --success-color: #27ae60;
    --error-color: #c0392b;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Segoe UI", Arial, sans-serif;
}

body {
    background: var(--bg-primary);
    color: var(--text-primary);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ================= Header ================= */
.university-header {
    background: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
    color: white;
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.dku-logo-img {
    width: 50px;
    height: 50px;
    object-fit: contain;
    border-radius: 5px;
    background: white;
    padding: 4px;
}

.system-title {
    font-size: 1rem;
    font-weight: 600;
}

.header-right {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.header-right a {
    color: white;
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 5px;
    transition: background 0.3s;
}

.header-right a:hover {
    background: rgba(255, 255, 255, 0.1);
}

/* ================= Main Container ================= */
.main-container {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 2rem;
}

.registration-container {
    background: var(--bg-card);
    border-radius: 15px;
    box-shadow: 0 10px 30px var(--shadow-color);
    padding: 2rem;
    width: 100%;
    max-width: 800px;
    margin: 2rem auto;
}

/* ================= Form Header ================= */
.form-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.form-header h1 {
    color: var(--text-primary);
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.form-header p {
    color: var(--text-secondary);
    font-size: 1rem;
}

/* ================= Messages ================= */
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
    background: linear-gradient(135deg, #d1fae5, #bbf7d0);
    color: #065f46;
    border-color: #10b981;
}

.message.error {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
    border-color: #ef4444;
}

/* ================= Form Styles ================= */
.registration-form {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-primary);
}

.form-group label.required::after {
    content: " *";
    color: var(--error-color);
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 14px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    transition: all 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
}

/* Input validation states */
.form-group input.valid {
    border-color: #10b981 !important;
    background: linear-gradient(90deg, var(--bg-secondary), #d1fae5) !important;
}

.form-group input.invalid {
    border-color: #dc2626 !important;
    background: linear-gradient(90deg, var(--bg-secondary), #fee2e2) !important;
}

/* ================= File Upload ================= */
.file-upload {
    position: relative;
    cursor: pointer;
}

.file-upload input[type="file"] {
    position: absolute;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}

.file-upload-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    border: 2px dashed var(--border-color);
    border-radius: 8px;
    background: var(--bg-secondary);
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.file-upload-label:hover {
    border-color: var(--primary-color);
    background: rgba(52, 152, 219, 0.05);
}

.file-upload-label i {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.file-preview {
    margin-top: 1rem;
    display: none;
}

.file-preview.active {
    display: block;
}

.file-preview-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    background: var(--bg-secondary);
    border-radius: 5px;
    margin-bottom: 0.5rem;
}

.file-preview-item i {
    color: var(--primary-color);
}

/* ================= Password Strength ================= */
.password-container {
    position: relative;
}

.toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-secondary);
    font-size: 1rem;
}

.password-strength {
    margin-top: 0.5rem;
}

.strength-bar {
    height: 6px;
    background: var(--border-color);
    border-radius: 3px;
    margin-bottom: 0.25rem;
    overflow: hidden;
}

.strength-fill {
    height: 100%;
    width: 0;
    transition: width 0.3s;
    border-radius: 3px;
}

.strength-text {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

/* ================= Role Selection ================= */
.role-selection {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.role-option {
    flex: 1;
    text-align: center;
    cursor: pointer;
}

.role-option input[type="radio"] {
    display: none;
}

.role-card {
    padding: 1.5rem;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    transition: all 0.3s;
    background: var(--bg-secondary);
}

.role-card:hover {
    border-color: var(--primary-color);
    transform: translateY(-2px);
}

.role-option input[type="radio"]:checked + .role-card {
    border-color: var(--primary-color);
    background: rgba(52, 152, 219, 0.1);
    box-shadow: 0 5px 15px rgba(52, 152, 219, 0.2);
}

.role-card i {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.role-card h3 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.role-card p {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

/* ================= Conditional Fields ================= */
.conditional-field {
    display: none;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.conditional-field.active {
    display: block;
}

/* ================= Submit Button ================= */
.submit-section {
    grid-column: 1 / -1;
    text-align: center;
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.submit-btn {
    background: linear-gradient(135deg, var(--primary-color), #2980b9);
    color: white;
    border: none;
    padding: 15px 40px;
    font-size: 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 600;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
}

.submit-btn:disabled {
    background: var(--border-color);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* ================= Login Link ================= */
.login-link {
    text-align: center;
    margin-top: 1rem;
    color: var(--text-secondary);
}

.login-link a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
}

.login-link a:hover {
    text-decoration: underline;
}

/* ================= Responsive Design ================= */
@media (max-width: 768px) {
    .registration-form {
        grid-template-columns: 1fr;
    }
    
    .role-selection {
        flex-direction: column;
    }
    
    .university-header {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .header-right {
        flex-wrap: wrap;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .registration-container {
        padding: 1rem;
    }
    
    .form-header h1 {
        font-size: 1.5rem;
    }
}
</style>
</head>
<body>
    <!-- University Header -->
    <div class="university-header">
        <div class="header-left">
            <img src="<?= $root_dir ?>/public/assets/images/dku logo.jpg" alt="Debark University Logo" class="dku-logo-img">
            <div class="system-title">Debark University Class Scheduling System</div>
        </div>
        <div class="header-right">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
        </div>
    </div>

    <!-- Main Container -->
    <div class="main-container">
        <div class="registration-container">
            <!-- Form Header -->
            <div class="form-header">
                <h1>Create Your Account</h1>
                <p>Join Debark University's scheduling system as a student or instructor</p>
            </div>

            <!-- Messages -->
            <?php if($message): ?>
                <div class="message <?= $message_type ?>">
                    <i class="fas fa-<?= $message_type === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST" enctype="multipart/form-data" class="registration-form" id="registrationForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <!-- Role Selection -->
                <div class="form-group full-width">
                    <div class="role-selection">
                        <label class="role-option">
                            <input type="radio" name="role" value="student" id="role-student" required>
                            <div class="role-card">
                                <i class="fas fa-graduation-cap"></i>
                                <h3>Student</h3>
                                <p>Access class schedules</p>
                            </div>
                        </label>
                        
                        <label class="role-option">
                            <input type="radio" name="role" value="instructor" id="role-instructor">
                            <div class="role-card">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <h3>Instructor</h3>
                                <p>Access class schedules, access assigned courses</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Basic Information -->
                <div class="form-group">
                    <label class="required">Username:</label>
                    <input type="text" name="username" id="username" 
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" 
                           required oninput="validateUsername()">
                    <div id="username-feedback" class="feedback"></div>
                </div>

                <div class="form-group">
                    <label class="required">Full Name:</label>
                    <input type="text" name="full_name" id="full_name"
                           value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>"
                           required oninput="validateFullName()">
                    <div id="fullname-feedback" class="feedback"></div>
                </div>

                <div class="form-group">
                    <label class="required">Email Address:</label>
                    <input type="email" name="email" id="email"
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                           required oninput="validateEmail()">
                    <div id="email-feedback" class="feedback"></div>
                </div>

                <!-- Student Fields (Hidden by default) -->
                <div class="conditional-field" id="student-fields">
                    <div class="form-group">
                        <label>Student ID:</label>
                        <input type="text" name="student_id" id="student_id"
                               value="<?= isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : '' ?>"
                               oninput="checkStudentID()">
                        <div id="student-id-feedback" class="feedback"></div>
                    </div>

                    <div class="form-group">
                        <label>Student Type:</label>
                        <select name="student_type" id="student-type" onchange="toggleYearFields()">
                            <option value="">-- Select Type --</option>
                            <option value="regular" <?= isset($_POST['student_type']) && $_POST['student_type'] == 'regular' ? 'selected' : '' ?>>Regular Student</option>
                            <option value="extension" <?= isset($_POST['student_type']) && $_POST['student_type'] == 'extension' ? 'selected' : '' ?>>Extension Student</option>
                        </select>
                    </div>

                    <div class="form-group conditional-field" id="regular-year-field">
                        <label>Year:</label>
                        <select name="regular_year" id="regular-year">
                            <option value="">-- Select Year --</option>
                            <option value="Freshman" <?= isset($_POST['regular_year']) && $_POST['regular_year'] == 'Freshman' ? 'selected' : '' ?>>Freshman</option>
                            <option value="1" <?= isset($_POST['regular_year']) && $_POST['regular_year'] == '1' ? 'selected' : '' ?>>Year 1</option>
                            <?php for($i=2; $i<=5; $i++): ?>
                                <option value="<?= $i ?>" <?= isset($_POST['regular_year']) && $_POST['regular_year'] == $i ? 'selected' : '' ?>>
                                    Year <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group conditional-field" id="extension-year-field">
                        <label>Extension Year:</label>
                        <select name="extension_year" id="extension-year">
                            <option value="">-- Select Year --</option>
                            <option value="Freshman" <?= isset($_POST['extension_year']) && $_POST['extension_year'] == 'Freshman' ? 'selected' : '' ?>>Freshman (Extension)</option>
                            <option value="E1" <?= isset($_POST['extension_year']) && $_POST['extension_year'] == 'E1' ? 'selected' : '' ?>>Extension Year 1</option>
                            <?php for($i=2; $i<=5; $i++): ?>
                                <option value="E<?= $i ?>" <?= isset($_POST['extension_year']) && $_POST['extension_year'] == "E$i" ? 'selected' : '' ?>>
                                    Extension Year <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <!-- Department Field (for both students and instructors) -->
                <div class="form-group conditional-field" id="department-field">
                    <label class="required">Department:</label>
                    <select name="department_id" id="department">
                        <option value="">-- Select Department --</option>
                        <?php foreach($departments as $d): ?>
                            <option value="<?= $d['department_id'] ?>" <?= isset($_POST['department_id']) && $_POST['department_id'] == $d['department_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Password Fields -->
                <div class="form-group">
                    <label class="required">Password:</label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" required oninput="validatePassword()">
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strength-fill"></div>
                        </div>
                        <div class="strength-text" id="strength-text">Password strength</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="required">Confirm Password:</label>
                    <div class="password-container">
                        <input type="password" name="confirm_password" id="confirm_password" required oninput="checkPasswordMatch()">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="password-match-feedback" class="feedback"></div>
                </div>

                <!-- Profile Picture Upload -->
                <div class="form-group">
                    <label>Profile Picture (Optional):</label>
                    <div class="file-upload">
                        <input type="file" name="profile_picture" id="profile-picture" accept="image/*" onchange="previewProfilePicture()">
                        <div class="file-upload-label" id="profile-upload-label">
                            <i class="fas fa-user-circle"></i>
                            <span>Click to upload profile picture</span>
                            <small>Max size: 2MB (JPG, PNG, GIF)</small>
                        </div>
                        <div class="file-preview" id="profile-preview"></div>
                    </div>
                </div>

                <!-- Document Upload -->
                <div class="form-group full-width">
                    <label>Supporting Documents (Optional):</label>
                    <div class="file-upload">
                        <input type="file" name="documents[]" id="documents" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="previewDocuments()">
                        <div class="file-upload-label" id="documents-upload-label">
                            <i class="fas fa-file-upload"></i>
                            <span>Click to upload documents</span>
                            <small>Max 5 files, 5MB each (PDF, JPG, PNG, DOC, DOCX)</small>
                        </div>
                        <div class="file-preview" id="documents-preview"></div>
                    </div>
                    <small>Upload supporting documents like ID cards, transcripts, or certificates</small>
                </div>

                <!-- Terms and Conditions -->
                <div class="form-group full-width">
                    <label>
                        <input type="checkbox" name="terms" id="terms" required>
                        I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>
                    </label>
                </div>

                <!-- Submit Section -->
                <div class="submit-section">
                    <button type="submit" class="submit-btn" id="submit-btn">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                    <div class="login-link">
                        Already have an account? <a href="login.php">Login here</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    // DOM Elements
    const roleStudent = document.getElementById('role-student');
    const roleInstructor = document.getElementById('role-instructor');
    const studentFields = document.getElementById('student-fields');
    const departmentField = document.getElementById('department-field');
    const studentTypeSelect = document.getElementById('student-type');
    const regularYearField = document.getElementById('regular-year-field');
    const extensionYearField = document.getElementById('extension-year-field');
    const regularYearSelect = document.getElementById('regular-year');
    const extensionYearSelect = document.getElementById('extension-year');
    const departmentSelect = document.getElementById('department');
    const submitBtn = document.getElementById('submit-btn');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const strengthFill = document.getElementById('strength-fill');
    const strengthText = document.getElementById('strength-text');
    const termsCheckbox = document.getElementById('terms');

    // Validation states
    let usernameValid = false;
    let fullNameValid = false;
    let emailValid = false;
    let studentIdValid = true;
    let passwordValid = false;
    let passwordsMatch = false;
    let termsAccepted = false;

    // Toggle password visibility
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const button = input.nextElementSibling;
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Validate username (no numbers allowed)
    function validateUsername() {
        const usernameInput = document.getElementById('username');
        const username = usernameInput.value.trim();
        const feedback = document.getElementById('username-feedback');
        
        usernameInput.classList.remove('valid', 'invalid');
        usernameValid = false;
        
        if (!username) {
            feedback.innerHTML = '';
            return;
        }
        
        // Check for numbers in username
        if (/\d/.test(username)) {
            usernameInput.classList.add('invalid');
            feedback.innerHTML = '<small style="color: #e74c3c;"><i class="fas fa-times-circle"></i> Username cannot contain numbers!</small>';
            usernameValid = false;
        } 
        // Check for special characters (only underscore allowed)
        else if (!/^[a-zA-Z_]+$/.test(username)) {
            usernameInput.classList.add('invalid');
            feedback.innerHTML = '<small style="color: #e74c3c;"><i class="fas fa-times-circle"></i> Username can only contain letters and underscores!</small>';
            usernameValid = false;
        }
        // Check length
        else if (username.length < 3) {
            usernameInput.classList.add('invalid');
            feedback.innerHTML = '<small style="color: #e74c3c;"><i class="fas fa-times-circle"></i> Username must be at least 3 characters</small>';
            usernameValid = false;
        } else {
            usernameInput.classList.add('valid');
            feedback.innerHTML = '<small style="color: #2ecc71;"><i class="fas fa-check-circle"></i> Username format is valid</small>';
            usernameValid = true;
        }
        
        updateSubmitButton();
    }

    // Validate full name (no numbers allowed, allows spaces, hyphens, apostrophes, and dots)
    function validateFullName() {
        const fullNameInput = document.getElementById('full_name');
        const fullName = fullNameInput.value.trim();
        const feedback = document.getElementById('fullname-feedback');
        
        fullNameInput.classList.remove('valid', 'invalid');
        fullNameValid = false;
        
        if (!fullName) {
            feedback.innerHTML = '';
            return;
        }
        
        // Check for numbers in full name
        if (/\d/.test(fullName)) {
            fullNameInput.classList.add('invalid');
            feedback.innerHTML = '<small style="color: #e74c3c;"><i class="fas fa-times-circle"></i> Full name cannot contain numbers!</small>';
            fullNameValid = false;
        } 
        // Check for invalid special characters
        else if (!/^[a-zA-Z\s.\'-]+$/.test(fullName)) {
            fullNameInput.classList.add('invalid');
            feedback.innerHTML = '<small style="color: #e74c3c;"><i class="fas fa-times-circle"></i> Full name can only contain letters, spaces, dots, apostrophes and hyphens!</small>';
            fullNameValid = false;
        }
        // Check if it has at least 2 parts (first and last name)
        else if (fullName.split(' ').length < 2) {
            fullNameInput.classList.add('invalid');
            feedback.innerHTML = '<small style="color: #e74c3c;"><i class="fas fa-times-circle"></i> Please enter both first and last name</small>';
            fullNameValid = false;
        } else {
            fullNameInput.classList.add('valid');
            feedback.innerHTML = '<small style="color: #2ecc71;"><i class="fas fa-check-circle"></i> Name format is valid</small>';
            fullNameValid = true;
        }
        
        updateSubmitButton();
    }

    // Show/hide fields based on role
    function toggleRoleFields() {
        const isStudent = roleStudent.checked;
        const isInstructor = roleInstructor.checked;
        
        // Student fields
        if (isStudent) {
            studentFields.classList.add('active');
            departmentField.classList.add('active');
            departmentSelect.required = false; // Will be validated based on year
        } else {
            studentFields.classList.remove('active');
        }
        
        // Department field for instructors
        if (isInstructor) {
            departmentField.classList.add('active');
            departmentSelect.required = true;
        }
        
        if (!isStudent && !isInstructor) {
            departmentField.classList.remove('active');
        }
        
        updateSubmitButton();
    }

    // Toggle year fields based on student type
    function toggleYearFields() {
        const studentType = studentTypeSelect.value;
        
        regularYearField.classList.remove('active');
        extensionYearField.classList.remove('active');
        
        if (studentType === 'regular') {
            regularYearField.classList.add('active');
            regularYearSelect.required = true;
            extensionYearSelect.required = false;
        } else if (studentType === 'extension') {
            extensionYearField.classList.add('active');
            extensionYearSelect.required = true;
            regularYearSelect.required = false;
        }
        
        updateDepartmentRequirement();
        updateSubmitButton();
    }

    // Update department requirement based on year
    function updateDepartmentRequirement() {
        if (!roleStudent.checked) return;
        
        const studentType = studentTypeSelect.value;
        let year = '';
        
        if (studentType === 'regular') {
            year = regularYearSelect.value;
        } else if (studentType === 'extension') {
            year = extensionYearSelect.value;
        }
        
        const isFreshman = year === 'Freshman';
        
        if (isFreshman) {
            departmentSelect.required = false;
        } else {
            departmentSelect.required = true;
        }
        
        updateSubmitButton();
    }

    // Validate email
    function validateEmail() {
        const emailInput = document.getElementById('email');
        const email = emailInput.value.trim();
        const feedback = document.getElementById('email-feedback');
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        
        emailInput.classList.remove('valid', 'invalid');
        emailValid = false;
        
        if (!email) {
            feedback.innerHTML = '';
            return;
        }
        
        if (!emailRegex.test(email)) {
            emailInput.classList.add('invalid');
            feedback.innerHTML = '<small style="color: #e74c3c;"><i class="fas fa-times-circle"></i> Please enter a valid email address</small>';
            emailValid = false;
        } else {
            emailInput.classList.add('valid');
            feedback.innerHTML = '<small style="color: #2ecc71;"><i class="fas fa-check-circle"></i> Valid email format</small>';
            emailValid = true;
        }
        
        updateSubmitButton();
    }

    // Check Student ID
    function checkStudentID() {
        const studentIdInput = document.getElementById('student_id');
        const studentId = studentIdInput.value.trim();
        const feedback = document.getElementById('student-id-feedback');
        
        studentIdInput.classList.remove('valid', 'invalid');
        studentIdValid = false;
        
        if (!studentId) {
            feedback.innerHTML = '';
            studentIdValid = true; // Student ID is optional
            updateSubmitButton();
            return;
        }
        
        // Check via AJAX
        fetch('check_student_id.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'student_id=' + encodeURIComponent(studentId)
        })
        .then(response => response.json())
        .then(data => {
            if (data.available) {
                studentIdInput.classList.add('valid');
                feedback.innerHTML = '<small style="color: #2ecc71;"><i class="fas fa-check-circle"></i> Student ID is available!</small>';
                studentIdValid = true;
            } else {
                studentIdInput.classList.add('invalid');
                feedback.innerHTML = '<small style="color: #e74c3c;"><i class="fas fa-times-circle"></i> Student ID already exists</small>';
                studentIdValid = false;
            }
            updateSubmitButton();
        })
        .catch(() => {
            studentIdInput.classList.add('invalid');
            feedback.innerHTML = '<small style="color: #e74c3c;"><i class="fas fa-times-circle"></i> Error checking Student ID</small>';
            studentIdValid = false;
            updateSubmitButton();
        });
    }

    // Validate password strength
    function validatePassword() {
        const password = passwordInput.value;
        
        // Calculate strength
        let strength = 0;
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
        };
        
        if (requirements.length) strength++;
        if (requirements.uppercase) strength++;
        if (requirements.lowercase) strength++;
        if (requirements.number) strength++;
        if (requirements.special) strength++;
        
        // Update strength bar
        const strengthPercent = (strength / 5) * 100;
        strengthFill.style.width = strengthPercent + '%';
        
        // Set color based on strength
        if (strength <= 1) {
            strengthFill.style.background = '#e74c3c';
            strengthText.textContent = 'Very Weak';
        } else if (strength === 2) {
            strengthFill.style.background = '#e67e22';
            strengthText.textContent = 'Weak';
        } else if (strength === 3) {
            strengthFill.style.background = '#f1c40f';
            strengthText.textContent = 'Fair';
        } else if (strength === 4) {
            strengthFill.style.background = '#2ecc71';
            strengthText.textContent = 'Good';
        } else if (strength === 5) {
            strengthFill.style.background = '#27ae60';
            strengthText.textContent = 'Strong';
        }
        
        // Password is valid if it meets minimum requirements
        passwordValid = requirements.length && requirements.uppercase && 
                       requirements.lowercase && requirements.number;
        
        checkPasswordMatch();
        updateSubmitButton();
    }

    // Check if passwords match
    function checkPasswordMatch() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        const feedback = document.getElementById('password-match-feedback');
        
        passwordsMatch = false;
        
        if (!password || !confirmPassword) {
            feedback.innerHTML = '';
            return;
        }
        
        if (password === confirmPassword) {
            feedback.innerHTML = '<small style="color: #2ecc71;"><i class="fas fa-check-circle"></i> Passwords match!</small>';
            passwordsMatch = true;
        } else {
            feedback.innerHTML = '<small style="color: #e74c3c;"><i class="fas fa-times-circle"></i> Passwords do not match</small>';
            passwordsMatch = false;
        }
        
        updateSubmitButton();
    }

    // Preview profile picture
    function previewProfilePicture() {
        const file = document.getElementById('profile-picture').files[0];
        const preview = document.getElementById('profile-preview');
        const label = document.getElementById('profile-upload-label');
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `
                    <div class="file-preview-item">
                        <i class="fas fa-image"></i>
                        <span>${file.name} (${(file.size / 1024).toFixed(1)} KB)</span>
                    </div>
                `;
                preview.classList.add('active');
                label.style.display = 'none';
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '';
            preview.classList.remove('active');
            label.style.display = 'flex';
        }
    }

    // Preview documents
    function previewDocuments() {
        const files = document.getElementById('documents').files;
        const preview = document.getElementById('documents-preview');
        const label = document.getElementById('documents-upload-label');
        
        preview.innerHTML = '';
        
        if (files.length > 0) {
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const item = document.createElement('div');
                item.className = 'file-preview-item';
                item.innerHTML = `
                    <i class="fas fa-file"></i>
                    <span>${file.name} (${(file.size / 1024).toFixed(1)} KB)</span>
                `;
                preview.appendChild(item);
            }
            preview.classList.add('active');
            label.style.display = 'none';
        } else {
            preview.classList.remove('active');
            label.style.display = 'flex';
        }
    }

    // Update submit button state
    function updateSubmitButton() {
        const isStudent = roleStudent.checked;
        const isInstructor = roleInstructor.checked;
        const studentType = studentTypeSelect.value;
        const regularYear = regularYearSelect.value;
        const extensionYear = extensionYearSelect.value;
        const department = departmentSelect.value;
        termsAccepted = termsCheckbox.checked;
        
        let enabled = true;
        
        // Basic validation
        if (!usernameValid) enabled = false;
        if (!fullNameValid) enabled = false;
        if (!emailValid) enabled = false;
        if (!passwordValid) enabled = false;
        if (!passwordsMatch) enabled = false;
        if (!termsAccepted) enabled = false;
        
        // Role selection
        if (!isStudent && !isInstructor) enabled = false;
        
        // Student-specific validation
        if (isStudent) {
            if (!studentType) enabled = false;
            
            if (studentType === 'regular' && !regularYear) {
                enabled = false;
            } else if (studentType === 'extension' && !extensionYear) {
                enabled = false;
            }
            
            if (!studentIdValid) enabled = false;
            
            // Department validation (not required for Freshman)
            const year = studentType === 'regular' ? regularYear : extensionYear;
            if (year && year !== 'Freshman' && !department) {
                enabled = false;
            }
        }
        
        // Instructor-specific validation
        if (isInstructor && !department) {
            enabled = false;
        }
        
        submitBtn.disabled = !enabled;
    }

    // Form validation before submission
    function validateForm() {
        const isStudent = roleStudent.checked;
        const studentType = studentTypeSelect.value;
        const regularYear = regularYearSelect.value;
        const extensionYear = extensionYearSelect.value;
        const department = departmentSelect.value;
        
        // Final validation
        if (isStudent) {
            const year = studentType === 'regular' ? regularYear : extensionYear;
            if (year && year !== 'Freshman' && !department) {
                alert('Department is required for non-freshman students');
                departmentSelect.focus();
                return false;
            }
        }
        
        if (roleInstructor.checked && !department) {
            alert('Please select a department');
            departmentSelect.focus();
            return false;
        }
        
        return true;
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Event listeners
        roleStudent.addEventListener('change', toggleRoleFields);
        roleInstructor.addEventListener('change', toggleRoleFields);
        studentTypeSelect.addEventListener('change', toggleYearFields);
        regularYearSelect.addEventListener('change', updateDepartmentRequirement);
        extensionYearSelect.addEventListener('change', updateDepartmentRequirement);
        departmentSelect.addEventListener('change', updateSubmitButton);
        termsCheckbox.addEventListener('change', updateSubmitButton);
        
        // Input event listeners
        document.getElementById('username').addEventListener('input', validateUsername);
        document.getElementById('full_name').addEventListener('input', validateFullName);
        document.getElementById('email').addEventListener('input', validateEmail);
        document.getElementById('student_id').addEventListener('input', checkStudentID);
        passwordInput.addEventListener('input', validatePassword);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        
        // Initial validation
        toggleRoleFields();
        
        // Form submission
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
            }
        });
    });
    </script>
</body>
</html>