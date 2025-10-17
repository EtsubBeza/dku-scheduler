<?php
session_start();
require __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json');

$aid = isset($_GET['announcement_id']) ? (int)$_GET['announcement_id'] : 0;
if(!$aid){
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT c.comment_id, c.comment, c.user_id, c.created_at, u.username
                       FROM announcement_comments c
                       JOIN users u ON c.user_id = u.user_id
                       WHERE c.announcement_id = ?
                       ORDER BY c.created_at ASC");
$stmt->execute([$aid]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($comments);
exit;
