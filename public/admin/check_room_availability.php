<?php
session_start();
require __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

// Check if user is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    echo json_encode(['available' => false, 'message' => 'Unauthorized']);
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_name = trim($_POST['room_name'] ?? '');
    $building = trim($_POST['building'] ?? '');
    $editing = $_POST['editing'] === 'true';
    $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : null;
    
    if(empty($room_name) || empty($building)) {
        echo json_encode(['available' => false, 'message' => 'Room name and building required']);
        exit;
    }
    
    try {
        if($editing && $room_id) {
            // For edit: check if another room has same name in same building
            $stmt = $pdo->prepare("SELECT room_id FROM rooms WHERE room_name = ? AND building = ? AND room_id != ?");
            $stmt->execute([$room_name, $building, $room_id]);
        } else {
            // For new room: check if any room has same name in same building
            $stmt = $pdo->prepare("SELECT room_id FROM rooms WHERE room_name = ? AND building = ?");
            $stmt->execute([$room_name, $building]);
        }
        
        $exists = $stmt->fetch();
        
        if($exists) {
            echo json_encode([
                'available' => false, 
                'message' => "Room '$room_name' already exists in '$building'"
            ]);
        } else {
            echo json_encode(['available' => true]);
        }
    } catch (Exception $e) {
        echo json_encode(['available' => false, 'message' => 'Database error']);
    }
}
?>