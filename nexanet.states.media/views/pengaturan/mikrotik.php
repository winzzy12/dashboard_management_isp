<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if($_SESSION['role'] != 'admin') {
    $_SESSION['error'] = "Anda tidak memiliki akses!";
    header("Location: ../dashboard/index.php");
    exit();
}

require_once '../../config/database.php';
require_once '../../models/MikrotikAPI.php';

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle update MikroTik config
if(isset($_POST['action']) && $_POST['action'] == 'update_config') {
    $host = trim($_POST['host']);
    $port = (int)$_POST['port'];
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Check if config exists
    $check = $db->prepare("SELECT id FROM mikrotik_config LIMIT 1");
    $check->execute();
    
    if($check->rowCount() > 0) {
        // Update existing
        $query = "UPDATE mikrotik_config SET host = :host, port = :port, username = :username, password = :password, updated_at = NOW()";
    } else {
        // Insert new
        $query = "INSERT INTO mikrotik_config (host, port, username, password) VALUES (:host, :port, :username, :password)";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':host', $host);
    $stmt->bindParam(':port', $port);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $password);
    
    if($stmt->execute()) {
        $success_message = "Konfigurasi MikroTik berhasil disimpan!";
    } else {
        $error_message = "Gagal menyimpan konfigurasi!";
    }
}

// Handle test connection
if(isset($_POST['action']) && $_POST['action'] == 'test_connection') {
    $mikrotik = new MikrotikAPI($db);
    if($mikrotik->testConnection()) {
        $success_message = "Koneksi ke MikroTik berhasil!";
    } else {
        $error_message = "Koneksi ke MikroTik gagal! Periksa host, port, username, dan password.";
    }
}

// Get current config
$config_query = "SELECT * FROM mikrotik_config WHERE is_active = 1 LIMIT 1";
$config_stmt = $db->prepare($config_query);
$config_stmt->execute();
$config = $config_stmt->fetch(PDO::FETCH_ASSOC);

// Get all pelanggan with MikroTik info
$pelanggan_query = "SELECT id, id_pelanggan, nama, status, mikrotik_comment, mikrotik_ip, mikrotik_profile 
                    FROM pelanggan ORDER BY id DESC";
$pelanggan_stmt = $db->prepare($pelanggan_query);
$pelanggan_stmt->execute();
$pelanggan_list = $pelanggan_stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-router"></i> Konfigurasi MikroTik
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <?php if($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- MikroTik Configuration Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-cog"></i> Konfigurasi Koneksi MikroTik
            </h6>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="row g-3">
                <input type="hidden" name="action" value="update_config">
                
                <div class="col-md-3">
                    <label class="form-label">Host / IP Address</label>
                    <input type="text" class="form-control" name="host" 
                           value="<?php echo $config['host'] ?? ''; ?>" required
                           placeholder="192.168.1.1">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Port</label>
                    <input type="number" class="form-control" name="port" 
                           value="<?php echo $config['port'] ?? '8728'; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" 
                           value="<?php echo $config['username'] ?? ''; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" 
                           value="<?php echo $config['password'] ?? ''; ?>" required>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Simpan</button>
                </div>
            </form>
            
            <hr>
            
            <form method="POST" action="" class="mt-3">
                <input type="hidden" name="action" value="test_connection">
                <button type="submit" class="btn btn-info">
                    <i class="fas fa-plug"></i> Test Koneksi
                </button>
            </form>
        </div>
    </div>

    <!-- Pelanggan MikroTik Mapping -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-users"></i> Mapping Pelanggan ke MikroTik
            </h6>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Cara Kerja:</strong>
                <ul class="mb-0 mt-2">
                    <li>Status pelanggan <strong>Aktif</strong> akan otomatis mengaktifkan akses di MikroTik</li>
                    <li>Status pelanggan <strong>Nonaktif</strong> akan otomatis memblokir akses di MikroTik</li>
                    <li>Menggunakan fitur <strong>Address List</strong> dan <strong>PPPoE Secret</strong> di MikroTik</li>
                </ul>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>ID Pelanggan</th>
                            <th>Nama</th>
                            <th>Status</th>
                            <th>MikroTik Comment</th>
                            <th>IP Address</th>
                            <th>Profile</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pelanggan_list as $p): ?>
                        <form method="POST" action="update_pelanggan_mikrotik.php">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <tr>
                                <td class="text-center"><?php echo $p['id']; ?></td>
                                <td><?php echo htmlspecialchars($p['id_pelanggan']); ?></td>
                                <td><?php echo htmlspecialchars($p['nama']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $p['status'] == 'aktif' ? 'success' : 'danger'; ?>">
                                        <?php echo $p['status'] == 'aktif' ? 'Aktif' : 'Nonaktif'; ?>
                                    </span>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" 
                                           name="mikrotik_comment" value="<?php echo htmlspecialchars($p['mikrotik_comment'] ?? $p['id_pelanggan']); ?>">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" 
                                           name="mikrotik_ip" value="<?php echo htmlspecialchars($p['mikrotik_ip'] ?? ''); ?>" 
                                           placeholder="192.168.1.100">
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" name="mikrotik_profile">
                                        <option value="default" <?php echo $p['mikrotik_profile'] == 'default' ? 'selected' : ''; ?>>Default</option>
                                        <option value="1mbps" <?php echo $p['mikrotik_profile'] == '1mbps' ? 'selected' : ''; ?>>1 Mbps</option>
                                        <option value="2mbps" <?php echo $p['mikrotik_profile'] == '2mbps' ? 'selected' : ''; ?>>2 Mbps</option>
                                        <option value="5mbps" <?php echo $p['mikrotik_profile'] == '5mbps' ? 'selected' : ''; ?>>5 Mbps</option>
                                        <option value="10mbps" <?php echo $p['mikrotik_profile'] == '10mbps' ? 'selected' : ''; ?>>10 Mbps</option>
                                    </select>
                                </td>
                                <td class="text-center">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-save"></i> Update
                                    </button>
                                    <button type="button" class="btn btn-sm btn-info sync-btn" 
                                            data-id="<?php echo $p['id']; ?>"
                                            data-comment="<?php echo $p['mikrotik_comment'] ?? $p['id_pelanggan']; ?>">
                                        <i class="fas fa-sync-alt"></i> Sync
                                    </button>
                                </td>
                            </tr>
                        </form>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Sync button handler
document.querySelectorAll('.sync-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const comment = this.dataset.comment;
        
        if(confirm(`Sync pelanggan ini ke MikroTik?`)) {
            fetch('sync_mikrotik.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}&comment=${comment}`
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Sync berhasil!');
                } else {
                    alert('Sync gagal: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>