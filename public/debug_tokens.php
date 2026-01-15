<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

echo "<h2>Debug: Password Reset Tokens</h2>";

// Show session data
echo "<h3>Session Data:</h3>";
echo "<pre>";
var_dump($_SESSION);
echo "</pre>";

// Show all tokens in database
try {
    $stmt = $pdo->query("
        SELECT 
            prt.id,
            prt.user_id,
            LEFT(prt.token, 20) as token_short,
            prt.code,
            prt.verified,
            prt.is_used,
            prt.expires_at,
            prt.created_at,
            u.email,
            u.username
        FROM password_reset_tokens prt 
        JOIN users u ON prt.user_id = u.user_id 
        ORDER BY prt.created_at DESC 
        LIMIT 5
    ");
    
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Recent Tokens in Database:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>
        <th>ID</th>
        <th>User ID</th>
        <th>Email</th>
        <th>Token</th>
        <th>Code</th>
        <th>Verified</th>
        <th>Used</th>
        <th>Expires</th>
        <th>Created</th>
        <th>Status</th>
    </tr>";
    
    foreach ($tokens as $token) {
        $now = time();
        $expires = strtotime($token['expires_at']);
        $expired = $expires < $now;
        $status = $token['is_used'] ? 'Used' : ($expired ? 'Expired' : 'Active');
        
        echo "<tr>";
        echo "<td>{$token['id']}</td>";
        echo "<td>{$token['user_id']}</td>";
        echo "<td>{$token['email']}</td>";
        echo "<td title='{$token['token_short']}...'>{$token['token_short']}...</td>";
        echo "<td><strong style='color: red;'>{$token['code']}</strong></td>";
        echo "<td>" . ($token['verified'] ? 'Yes' : 'No') . "</td>";
        echo "<td>" . ($token['is_used'] ? 'Yes' : 'No') . "</td>";
        echo "<td>{$token['expires_at']} " . ($expired ? '⚠️' : '') . "</td>";
        echo "<td>{$token['created_at']}</td>";
        echo "<td>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Check current time in database
echo "<h3>Database Time:</h3>";
try {
    $stmt = $pdo->query("SELECT NOW() as db_time");
    $time = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Database time: " . $time['db_time'] . "<br>";
    echo "PHP time: " . date('Y-m-d H:i:s');
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>