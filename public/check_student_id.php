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
error_log("check_student_id.php called with student_id: " . ($_POST['student_id'] ?? 'none'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get student_id from POST data
    $student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
    
    // If student ID is empty, it's considered available (optional field)
    if (empty($student_id)) {
        echo json_encode([
            'available' => true,
            'message' => 'Student ID is optional'
        ]);
        exit;
    }
    
    try {
        // Check if student_id exists in database
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $student_exists = $stmt->fetch();
        
        if ($student_exists) {
            // Student ID already exists
            $suggestion = $student_id . 'A';
            echo json_encode([
                'available' => false,
                'message' => 'Student ID already exists',
                'suggestion' => $suggestion
            ]);
        } else {
            // Student ID is available
            echo json_encode([
                'available' => true,
                'message' => 'Student ID is available'
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Database error in check_student_id.php: " . $e->getMessage());
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