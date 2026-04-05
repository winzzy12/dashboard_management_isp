<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h2>Test Material Data</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if($db) {
        echo "<p style='color:green'>✓ Database connected</p>";
        
        // Check material table
        $query = "SHOW TABLES LIKE 'material'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            echo "<p style='color:green'>✓ Material table exists</p>";
            
            // Count materials
            $query = "SELECT COUNT(*) as total FROM material";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<p>Total material: " . $result['total'] . "</p>";
            
            if($result['total'] == 0) {
                echo "<p style='color:orange'>⚠ No data found. Inserting sample data...</p>";
                
                // Insert sample data
                $insert = "INSERT INTO material (nama_material, stok, harga, keterangan) VALUES
                    ('Kabel UTP Cat6', 50, 150000, 'Kabel jaringan per rol'),
                    ('Router MikroTik', 10, 850000, 'Routerboard'),
                    ('Switch 8 Port', 15, 350000, 'Switch Gigabit'),
                    ('Connector RJ45', 500, 500, 'Konektor RJ45 per 100 pcs')";
                
                $db->exec($insert);
                echo "<p style='color:green'>✓ Sample data inserted!</p>";
            }
            
            // Display all materials
            $query = "SELECT * FROM material ORDER BY id DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>Data Material:</h3>";
            echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
            echo "<tr style='background:#4e73df; color:white;'><th>ID</th><th>Nama Material</th><th>Stok</th><th>Harga</th><th>Keterangan</th></tr>";
            
            foreach($materials as $m) {
                echo "<tr>";
                echo "<td>{$m['id']}</td>";
                echo "<td>{$m['nama_material']}</td>";
                echo "<td>{$m['stok']}</td>";
                echo "<td>Rp " . number_format($m['harga'], 0, ',', '.') . "</td>";
                echo "<td>{$m['keterangan']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
        } else {
            echo "<p style='color:red'>✗ Material table does not exist!</p>";
            echo "<p>Please run setup.php first.</p>";
        }
    } else {
        echo "<p style='color:red'>✗ Database connection failed!</p>";
    }
    
} catch(Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='views/material/index.php' style='padding:10px 20px; background:#4caf50; color:white; text-decoration:none; border-radius:5px;'>Go to Material Page</a>";
echo " <a href='setup.php' style='padding:10px 20px; background:#2196f3; color:white; text-decoration:none; border-radius:5px;'>Run Setup</a>";
?>