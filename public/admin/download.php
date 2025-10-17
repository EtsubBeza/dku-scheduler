<?php
session_start();

// Optional: only allow logged-in users
if(!isset($_SESSION['user_id'])){
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied.');
}

$file = $_GET['file'] ?? '';
// Adjust path to match your uploads/announcements folder
$filepath = __DIR__ . '/../../uploads/announcements/' . basename($file);

if($file && file_exists($filepath)){
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($filepath).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}else{
    http_response_code(404);
    echo "File not found: " . htmlspecialchars($file);
}
