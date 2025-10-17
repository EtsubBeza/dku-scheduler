<?php
session_start();
require __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) exit(json_encode([]));
$user_id = $_SESSION['user_id'];
$aid = intval($_GET['announcement_id']);

// Check if already liked
$check = $pdo->prepare("SELECT * FROM announcement_likes WHERE announcement_id=? AND user_id=?");
$check->execute([$aid, $user_id]);

if($check->rowCount()){
    // Unlike
    $pdo->prepare("DELETE FROM announcement_likes WHERE announcement_id=? AND user_id=?")->execute([$aid, $user_id]);
} else {
    // Like
    $pdo->prepare("INSERT INTO announcement_likes (announcement_id, user_id) VALUES (?, ?)")->execute([$aid, $user_id]);
}

// Return updated count & status
$like_count = $pdo->prepare("SELECT COUNT(*) FROM announcement_likes WHERE announcement_id=?");
$like_count->execute([$aid]);

$liked_by_user = $pdo->prepare("SELECT COUNT(*) FROM announcement_likes WHERE announcement_id=? AND user_id=?");
$liked_by_user->execute([$aid, $user_id]);

echo json_encode([
    'like_count' => $like_count->fetchColumn(),
    'liked_by_user' => $liked_by_user->fetchColumn()
]);
