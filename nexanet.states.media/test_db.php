<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "<h2>Database Connection Test</h2>";
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    if($db) {
        echo "<p style='color:green'>✓ Database connection successful!</p>";
        $query = "SELECT COUNT(*) as total FROM users";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>✓ Users table found. Total users: " . $result['total'] . "</p>";
    } else {
        echo "<p style='color:red'>✗ Database connection failed!</p>";
    }
} catch(Exception $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}
echo "<br><a href='setup.php' style='padding:10px 20px; background:#4caf50; color:white; text-decoration:none; border-radius:5px;'>Run Setup</a>";
?>