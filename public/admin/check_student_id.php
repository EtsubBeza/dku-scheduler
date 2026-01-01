<?php
// check_student_id.php
session_start();
require __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if(!isset($_POST['student_id']) || empty(trim($_POST['student_id']))){
    echo json_encode(['error' => 'No student ID provided']);
    exit;
}

$student_id = trim($_POST['student_id']);
$editing = isset($_POST['editing']) && $_POST['editing'] === 'true';
$edit_id = isset($_POST['edit_id']) && $_POST['edit_id'] !== 'null' ? (int)$_POST['edit_id'] : null;

// Check if student ID already exists
if($editing && $edit_id){
    $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE student_id = ? AND user_id != ?");
    $stmt->execute([$student_id, $edit_id]);
} else {
    $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE student_id = ?");
    $stmt->execute([$student_id]);
}

$existing_user = $stmt->fetch();

if($existing_user){
    // Generate a suggestion (append a number)
    $suggestion = $student_id . '_' . rand(100, 999);
    
    // Try to find a unique suggestion
    for($i = 1; $i <= 10; $i++){
        $check_stmt = $pdo->prepare("SELECT user_id FROM users WHERE student_id = ?");
        $check_stmt->execute([$suggestion]);
        if(!$check_stmt->fetch()){
            break;
        }
        $suggestion = $student_id . '_' . rand(100, 999);
    }
    
    echo json_encode([
        'available' => false,
        'message' => 'Student ID already exists',
        'existing_user_id' => $existing_user['user_id'],
        'existing_username' => $existing_user['username'],
        'suggestion' => $suggestion
    ]);
} else {
    echo json_encode([
        'available' => true,
        'message' => 'Student ID is available'
    ]);
}