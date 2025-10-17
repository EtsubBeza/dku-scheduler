<?php
session_start();
require __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])){
    echo json_encode(['status'=>'error','message'=>'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$announcement_id = intval($_POST['announcement_id'] ?? 0);
$reaction = $_POST['reaction'] ?? '';

$allowed = ['like','love','wow','haha'];
if(!$announcement_id || !in_array($reaction, $allowed)){
    echo json_encode(['status'=>'error','message'=>'Invalid request']);
    exit;
}

// Check if reaction already exists
$check = $pdo->prepare("SELECT id FROM announcement_reactions WHERE announcement_id=? AND user_id=? AND reaction_type=?");
$check->execute([$announcement_id, $user_id, $reaction]);
if($check->rowCount() > 0){
    // Remove reaction if clicked again (toggle)
    $del = $pdo->prepare("DELETE FROM announcement_reactions WHERE announcement_id=? AND user_id=? AND reaction_type=?");
    $del->execute([$announcement_id, $user_id, $reaction]);
} else {
    // Add reaction
    $ins = $pdo->prepare("INSERT INTO announcement_reactions (announcement_id,user_id,reaction_type) VALUES (?,?,?)");
    $ins->execute([$announcement_id, $user_id, $reaction]);
}

// Count all reactions for this type
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM announcement_reactions WHERE announcement_id=? AND reaction_type=?");
$count_stmt->execute([$announcement_id, $reaction]);
$count = $count_stmt->fetchColumn();

echo json_encode(['status'=>'success','count'=>$count]);
