<?php
session_start();

// Set the correct path for the database connection
$root_dir = dirname(dirname(__FILE__));
require $root_dir . '/includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Log incoming request for debugging
error_log("check_username.php called with username: " . ($_POST['username'] ?? 'none'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get username from POST data
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    
    // Validate input
    if (empty($username)) {
        echo json_encode([
            'available' => false,
            'message' => 'Username is required'
        ]);
        exit;
    }
    
    // Validate username format
    if (strlen($username) < 3) {
        echo json_encode([
            'available' => false,
            'message' => 'Username must be at least 3 characters'
        ]);
        exit;
    }
    
    // Check if username contains only allowed characters
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        echo json_encode([
            'available' => false,
            'message' => 'Username can only contain letters, numbers, and underscores'
        ]);
        exit;
    }
    
    try {
        // Check if username exists in database
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user_exists = $stmt->fetch();
        
        if ($user_exists) {
            // Username already exists
            $suggestion = $username . rand(100, 999);
            echo json_encode([
                'available' => false,
                'message' => 'Username already taken',
                'suggestion' => $suggestion
            ]);
        } else {
            // Username is available
            echo json_encode([
                'available' => true,
                'message' => 'Username is available'
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Database error in check_username.php: " . $e->getMessage());
        echo json_encode([
            'available' => false,
            'message' => 'Database error occurred'
        ]);
    }
} else {
    echo json_encode([
        'available' => false,
        'message' => 'Invalid request method'
    ]);
}
?>