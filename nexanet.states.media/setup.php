<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <title>RT/RW Net Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; }
        .success { color: green; background: #e8f5e9; padding: 10px; margin: 10px 0; border-left: 4px solid green; }
        .error { color: red; background: #ffebee; padding: 10px; margin: 10px 0; border-left: 4px solid red; }
        .info { color: blue; background: #e3f2fd; padding: 10px; margin: 10px 0; border-left: 4px solid blue; }
    </style>
</head>
<body>
    <h1>RT/RW Net Database Setup</h1>
<?php
try {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'rt_rw_net';
    
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='success'>✓ Connected to MySQL server</div>";
    
    $conn->exec("CREATE DATABASE IF NOT EXISTS $database");
    echo "<div class='success'>✓ Database '$database' created</div>";
    
    $conn->exec("USE $database");
    
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        nama_lengkap VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        role ENUM('admin', 'operator') DEFAULT 'operator',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<div class='success'>✓ Users table created</div>";
    
    $conn->exec("CREATE TABLE IF NOT EXISTS pelanggan (
        id INT PRIMARY KEY AUTO_INCREMENT,
        id_pelanggan VARCHAR(20) UNIQUE NOT NULL,
        nama VARCHAR(100) NOT NULL,
        alamat TEXT,
        no_hp VARCHAR(15),
        paket_internet VARCHAR(50),
        harga_paket DECIMAL(10,2),
        status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "<div class='success'>✓ Pelanggan table created</div>";
    
    $conn->exec("CREATE TABLE IF NOT EXISTS material (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nama_material VARCHAR(100) NOT NULL,
        stok INT DEFAULT 0,
        harga DECIMAL(10,2),
        keterangan TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<div class='success'>✓ Material table created</div>";
    
    $conn->exec("CREATE TABLE IF NOT EXISTS pemasukan (
        id INT PRIMARY KEY AUTO_INCREMENT,
        tanggal DATE NOT NULL,
        pelanggan_id INT,
        jumlah DECIMAL(10,2) NOT NULL,
        keterangan TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE SET NULL,
        INDEX idx_tanggal (tanggal)
    )");
    echo "<div class='success'>✓ Pemasukan table created</div>";
    
    $conn->exec("CREATE TABLE IF NOT EXISTS pengeluaran (
        id INT PRIMARY KEY AUTO_INCREMENT,
        tanggal DATE NOT NULL,
        jenis_pengeluaran VARCHAR(100) NOT NULL,
        jumlah DECIMAL(10,2) NOT NULL,
        keterangan TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tanggal (tanggal)
    )");
    echo "<div class='success'>✓ Pengeluaran table created</div>";
    
    $conn->exec("CREATE TABLE IF NOT EXISTS billing (
        id INT PRIMARY KEY AUTO_INCREMENT,
        pelanggan_id INT NOT NULL,
        bulan INT NOT NULL,
        tahun INT NOT NULL,
        jumlah DECIMAL(10,2) NOT NULL,
        status ENUM('lunas', 'belum_lunas') DEFAULT 'belum_lunas',
        tanggal_jatuh_tempo DATE,
        tanggal_bayar DATE,
        reminder_sent TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE CASCADE,
        UNIQUE KEY unique_billing (pelanggan_id, bulan, tahun),
        INDEX idx_period (bulan, tahun)
    )");
    echo "<div class='success'>✓ Billing table created</div>";
    
    $check = $conn->query("SELECT COUNT(*) as total FROM users WHERE username = 'admin'")->fetch();
    if($check['total'] == 0) {
        $password = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role) VALUES ('admin', :password, 'Administrator', 'admin@rtnet.com', 'admin')");
        $stmt->bindParam(':password', $password);
        $stmt->execute();
        echo "<div class='success'>✓ Default admin user created (username: admin, password: admin123)</div>";
    }
    
    $check = $conn->query("SELECT COUNT(*) as total FROM pelanggan")->fetch();
    if($check['total'] == 0) {
        $conn->exec("INSERT INTO pelanggan (id_pelanggan, nama, alamat, no_hp, paket_internet, harga_paket, status) VALUES
            ('PLG001', 'Budi Santoso', 'Jl. Merdeka No. 1', '081234567890', 'Paket Silver 10 Mbps', 150000, 'aktif'),
            ('PLG002', 'Siti Aminah', 'Jl. Merdeka No. 2', '081234567891', 'Paket Gold 20 Mbps', 250000, 'aktif'),
            ('PLG003', 'Ahmad Fauzi', 'Jl. Merdeka No. 3', '081234567892', 'Paket Platinum 50 Mbps', 500000, 'nonaktif')");
        
        $conn->exec("INSERT INTO material (nama_material, stok, harga, keterangan) VALUES
            ('Kabel UTP Cat6', 100, 150000, 'Kabel jaringan per rol'),
            ('Router MikroTik', 5, 850000, 'Routerboard'),
            ('Switch 8 Port', 10, 350000, 'Switch Gigabit'),
            ('Connector RJ45', 500, 500, 'Per 100 pcs')");
        echo "<div class='success'>✓ Sample data inserted</div>";
    }
    
    echo "<div class='success' style='background:#4caf50; color:white; padding:15px;'><strong>✓ Setup completed successfully!</strong><br>Login: admin / admin123</div>";
    
} catch(PDOException $e) {
    echo "<div class='error'>✗ Error: " . $e->getMessage() . "</div>";
}
?>
    <br>
    <a href="views/login.php" style="display:inline-block; padding:10px 20px; background:#4caf50; color:white; text-decoration:none; border-radius:5px;">Go to Login</a>
</body>
</html>