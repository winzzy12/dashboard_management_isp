<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'models/MikrotikManager.php';

echo "<h2>Test Koneksi MikroTik SSH</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $mikrotik = new MikrotikManager($db);
    
    echo "<h3>Test Connection:</h3>";
    if($mikrotik->testConnection()) {
        echo "<p style='color:green'>✓ Koneksi SSH BERHASIL!</p>";
        
        echo "<h3>System Info:</h3>";
        $info = $mikrotik->getSystemInfo();
        echo "<pre>";
        print_r($info);
        echo "</pre>";
        
    } else {
        echo "<p style='color:red'>✗ Koneksi SSH GAGAL!</p>";
    }
    
} catch(Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>