<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>PHP Syntax Checker</h2>";

$files_to_check = [
    'config/database.php',
    'models/User.php',
    'models/Pelanggan.php',
    'models/Material.php',
    'models/Pemasukan.php',
    'models/Pengeluaran.php',
    'models/Billing.php',
    'controllers/AuthController.php',
    'controllers/DashboardController.php',
    'controllers/MaterialController.php',
    'controllers/PengeluaranController.php',
    'controllers/BillingController.php',
    'controllers/LaporanController.php',
    'includes/header.php',
    'includes/footer.php',
    'includes/sidebar.php',
    'index.php'
];

foreach($files_to_check as $file) {
    if(file_exists($file)) {
        echo "<h3>Checking: $file</h3>";
        
        // Check syntax
        $output = array();
        $return_var = 0;
        exec("php -l " . escapeshellarg($file), $output, $return_var);
        
        if($return_var == 0) {
            echo "<span style='color:green'>✓ Syntax OK</span><br>";
        } else {
            echo "<span style='color:red'>✗ Syntax Error:</span><br>";
            echo "<pre>" . implode("\n", $output) . "</pre>";
        }
    } else {
        echo "<h3>Checking: $file</h3>";
        echo "<span style='color:orange'>⚠ File not found</span><br>";
    }
    echo "<br>";
}

// Check if all required tables exist
echo "<h2>Database Tables Check</h2>";

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $tables = ['users', 'pelanggan', 'material', 'pemasukan', 'pengeluaran', 'billing'];
    
    foreach($tables as $table) {
        $query = "SHOW TABLES LIKE '$table'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            echo "<span style='color:green'>✓ Table '$table' exists</span><br>";
            
            // Check table structure
            $query = "DESCRIBE $table";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "&nbsp;&nbsp;Columns: " . implode(', ', $columns) . "<br>";
        } else {
            echo "<span style='color:red'>✗ Table '$table' does not exist</span><br>";
        }
    }
    
} catch(Exception $e) {
    echo "<span style='color:red'>Error: " . $e->getMessage() . "</span>";
}

echo "<br><a href='setup.php' style='display:inline-block; padding:10px 20px; background:#4caf50; color:white; text-decoration:none; border-radius:5px;'>Run Setup</a> ";
echo "<a href='test_db.php' style='display:inline-block; padding:10px 20px; background:#2196f3; color:white; text-decoration:none; border-radius:5px;'>Test Database</a>";
?>