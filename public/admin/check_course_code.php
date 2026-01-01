<?php
session_start();
require __DIR__ . '/../includes/db.php';

// Only allow AJAX requests from admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    http_response_code(403);
    echo json_encode(['available' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get POST data
$course_code = isset($_POST['course_code']) ? trim($_POST['course_code']) : '';
$editing = isset($_POST['editing']) ? $_POST['editing'] === 'true' : false;
$course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;

// Validate input
if(empty($course_code)){
    echo json_encode(['available' => false, 'error' => 'Course code is required']);
    exit;
}

// Course code format validation (e.g., CS101, MATH201)
if(!preg_match('/^[A-Za-z]{2,6}\d{3,4}$/', $course_code)){
    echo json_encode(['available' => false, 'error' => 'Invalid format. Use format: Letters (2-6) + Numbers (3-4), e.g., CS101']);
    exit;
}

try {
    // Check if course code exists
    if($editing && $course_id > 0){
        // When editing, check if code exists excluding current course
        $stmt = $pdo->prepare("SELECT course_id, course_name FROM courses WHERE course_code = ? AND course_id != ?");
        $stmt->execute([strtoupper($course_code), $course_id]);
    } else {
        // When adding new, check if code exists
        $stmt = $pdo->prepare("SELECT course_id, course_name FROM courses WHERE course_code = ?");
        $stmt->execute([strtoupper($course_code)]);
    }
    
    $existing = $stmt->fetch();
    
    if($existing){
        // Generate a suggestion if course code exists
        $suggestion = generateSuggestion($course_code, $pdo);
        
        echo json_encode([
            'available' => false,
            'course_id' => $existing['course_id'],
            'course_name' => $existing['course_name'],
            'suggestion' => $suggestion,
            'message' => 'Course code already exists'
        ]);
    } else {
        echo json_encode([
            'available' => true,
            'message' => 'Course code is available'
        ]);
    }
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        'available' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

/**
 * Generate a suggested course code if the original is taken
 */
function generateSuggestion($course_code, $pdo) {
    // Extract letters and numbers
    preg_match('/^([A-Za-z]+)(\d+)$/', $course_code, $matches);
    
    if(count($matches) < 3) {
        return null;
    }
    
    $letters = strtoupper($matches[1]);
    $numbers = (int)$matches[2];
    
    // Try incrementing the number
    for($i = 1; $i <= 5; $i++) {
        $suggestion = $letters . ($numbers + $i);
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_code = ?");
        $stmt->execute([$suggestion]);
        $count = $stmt->fetchColumn();
        
        if($count == 0) {
            return $suggestion;
        }
    }
    
    // Try appending letters
    $suffixes = ['A', 'B', 'C', 'D', 'E'];
    foreach($suffixes as $suffix) {
        $suggestion = $course_code . $suffix;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_code = ?");
        $stmt->execute([$suggestion]);
        $count = $stmt->fetchColumn();
        
        if($count == 0) {
            return $suggestion;
        }
    }
    
    return null;
}