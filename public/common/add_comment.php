<?php
session_start();
require __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) exit(json_encode([]));
$user_id = $_SESSION['user_id'];

$aid = intval($_POST['announcement_id'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if($aid && $comment){
    $stmt = $pdo->prepare("INSERT INTO announcement_comments (announcement_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->execute([$aid, $user_id, $comment]);
}

echo json_encode(['success'=>true]);
