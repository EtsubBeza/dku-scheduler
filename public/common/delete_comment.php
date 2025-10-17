<?php
session_start();
require __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])){
    echo json_encode(['success'=>false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$dept_id = $_SESSION['department_id'] ?? null;

$comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
if(!$comment_id){
    echo json_encode(['success'=>false, 'error' => 'Invalid comment id']);
    exit;
}

// get comment + announcement info
$stmt = $pdo->prepare("SELECT c.user_id AS comment_user, c.announcement_id, a.department_id
                       FROM announcement_comments c
                       JOIN announcements a ON c.announcement_id = a.announcement_id
                       WHERE c.comment_id = ?");
$stmt->execute([$comment_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$row){
    echo json_encode(['success'=>false, 'error' => 'Comment not found']);
    exit;
}

// permission check: comment author OR department head of same department OR admin
$allowed = false;
if($user_id == $row['comment_user']) $allowed = true;
if($role === 'admin') $allowed = true;
if($role === 'department_head' && $dept_id !== null && (int)$dept_id === (int)$row['department_id']) $allowed = true;

if(!$allowed){
    echo json_encode(['success'=>false, 'error' => 'Permission denied']);
    exit;
}

// delete
$del = $pdo->prepare("DELETE FROM announcement_comments WHERE comment_id = ?");
$del->execute([$comment_id]);

// return new comment count
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM announcement_comments WHERE announcement_id = ?");
$count_stmt->execute([$row['announcement_id']]);
$new_count = (int)$count_stmt->fetchColumn();

echo json_encode(['success'=>true, 'new_comment_count' => $new_count]);
exit;
