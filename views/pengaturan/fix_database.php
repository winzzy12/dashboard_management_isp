<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if($_SESSION['role'] != 'admin') {
    $_SESSION['error'] = "Anda tidak memiliki akses ke halaman ini!";
    header("Location: ../dashboard/index.php");
    exit();
}

// Include required files
require_once '../../config/database.php';

$message = '';
$error = '';
$fixed_tables = [];
$failed_tables = [];

// Handle fix database action
if(isset($_POST['action']) && $_POST['action'] == 'fix_database') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if(!$db) {
            throw new Exception("Koneksi database gagal!");
        }
        
        // Daftar tabel yang perlu diperbaiki
        $tables = [
            'pelanggan',
            'material', 
            'pemasukan',
            'pengeluaran',
            'billing',
            'users',
            'paket_internet',
            'rekening_bank',
            'qris',
            'payment_gateway',
            'transaksi_pembayaran',
            'vpn_servers',
            'vpn_clients',
            'pop',
            'odp',
            'konfigurasi'
        ];
        
        foreach($tables as $table) {
            // Cek apakah tabel ada
            $check = $db->query("SHOW TABLES LIKE '$table'");
            if($check->rowCount() == 0) {
                $failed_tables[] = "$table (tabel tidak ditemukan)";
                continue;
            }
            
            try {
                // Cek struktur kolom id
                $columns = $db->query("DESCRIBE $table");
                $has_id = false;
                $is_auto = false;
                
                while($col = $columns->fetch(PDO::FETCH_ASSOC)) {
                    if($col['Field'] == 'id') {
                        $has_id = true;
                        if(strpos($col['Extra'], 'auto_increment') !== false) {
                            $is_auto = true;
                        }
                        break;
                    }
                }
                
                if(!$has_id) {
                    $failed_tables[] = "$table (tidak memiliki kolom id)";
                    continue;
                }
                
                if(!$is_auto) {
                    // Perbaiki kolom id menjadi AUTO_INCREMENT
                    $db->exec("ALTER TABLE $table MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
                }
                
                // Hapus data dengan id = 0
                $deleted = $db->exec("DELETE FROM $table WHERE id = 0");
                
                // Reset auto increment
                                $db->exec("ALTER TABLE $table AUTO_INCREMENT = 1");
                
                $fixed_tables[] = [
                    'name' => $table,
                    'auto_fixed' => !$is_auto,
                    'deleted' => $deleted
                ];
                
            } catch(PDOException $e) {
                $failed_tables[] = "$table (" . $e->getMessage() . ")";
            }
        }
        
        if(count($fixed_tables) > 0) {
            $message = "Database berhasil diperbaiki! " . count($fixed_tables) . " tabel telah dioptimasi.";
        } else {
            $error = "Tidak ada tabel yang dapat diperbaiki.";
        }
        
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle reset specific table
if(isset($_POST['action']) && $_POST['action'] == 'reset_table' && isset($_POST['table_name'])) {
    $table_name = $_POST['table_name'];
    $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';
    
    if($confirm == 'YES') {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Cek apakah tabel ada
            $check = $db->query("SHOW TABLES LIKE '$table_name'");
            if($check->rowCount() > 0) {
                // Backup data
                $backup_table = $table_name . "_backup_" . date('Ymd_His');
                $db->exec("CREATE TABLE $backup_table AS SELECT * FROM $table_name");
                
                // Truncate table
                $db->exec("TRUNCATE TABLE $table_name");
                
                // Reset auto increment
                $db->exec("ALTER TABLE $table_name AUTO_INCREMENT = 1");
                
                $message = "Tabel $table_name berhasil direset! Backup disimpan sebagai $backup_table";
            } else {
                $error = "Tabel $table_name tidak ditemukan!";
            }
        } catch(Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Konfirmasi tidak sesuai! Harus ketik 'YES' untuk reset.";
    }
}

// Get all tables info
$database = new Database();
$db = $database->getConnection();
$tables_info = [];

if($db) {
    $result = $db->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    foreach($tables as $table) {
        $columns = $db->query("DESCRIBE $table");
        $has_id = false;
        $is_auto = false;
        $id_type = '';
        
        while($col = $columns->fetch(PDO::FETCH_ASSOC)) {
            if($col['Field'] == 'id') {
                $has_id = true;
                $id_type = $col['Type'];
                if(strpos($col['Extra'], 'auto_increment') !== false) {
                    $is_auto = true;
                }
                break;
            }
        }
        
        // Get row count
        $count = $db->query("SELECT COUNT(*) as total FROM $table")->fetch(PDO::FETCH_ASSOC);
        
        $tables_info[] = [
            'name' => $table,
            'has_id' => $has_id,
            'is_auto' => $is_auto,
            'id_type' => $id_type,
            'rows' => $count['total']
        ];
    }
}

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-database"></i> Fix Database
        </h1>
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Fix Database Button -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-tools"></i> Perbaiki Database
            </h6>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Perhatian:</strong> Fitur ini akan memperbaiki struktur tabel database, termasuk:
                <ul class="mb-0 mt-2">
                    <li>Memperbaiki kolom ID menjadi AUTO_INCREMENT</li>
                    <li>Menghapus data dengan ID = 0</li>
                    <li>Reset auto increment ke nilai yang benar</li>
                </ul>
            </div>
            
            <form method="POST" action="" onsubmit="return confirm('Yakin ingin memperbaiki database? Data dengan ID=0 akan dihapus.')">
                <input type="hidden" name="action" value="fix_database">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-hammer"></i> Perbaiki Database Sekarang
                </button>
            </form>
        </div>
    </div>

    <!-- Tables Status -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list"></i> Status Tabel Database
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Nama Tabel</th>
                            <th>Jumlah Data</th>
                            <th>Kolom ID</th>
                            <th>Auto Increment</th>
                            <th>Status</th>
                        </thead>
                    <tbody>
                        <?php $no = 1; foreach($tables_info as $table): ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><code><?php echo $table['name']; ?></code></td>
                            <td class="text-center"><?php echo number_format($table['rows']); ?></td>
                            <td class="text-center">
                                <?php if($table['has_id']): ?>
                                    <span class="badge bg-success">Ada</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Tidak Ada</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($table['is_auto']): ?>
                                    <span class="badge bg-success">✓ Aktif</span>
                                <?php elseif($table['has_id']): ?>
                                    <span class="badge bg-warning">✗ Tidak Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($table['has_id'] && !$table['is_auto']): ?>
                                    <button class="btn btn-sm btn-warning" onclick="fixSingleTable('<?php echo $table['name']; ?>')">
                                        <i class="fas fa-hammer"></i> Perbaiki
                                    </button>
                                <?php elseif($table['has_id'] && $table['is_auto']): ?>
                                    <span class="text-success"><i class="fas fa-check-circle"></i> OK</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Reset Table Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-danger">
                <i class="fas fa-trash-alt"></i> Reset Tabel (Hati-hati!)
            </h6>
        </div>
        <div class="card-body">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Peringatan!</strong> Fitur ini akan menghapus SEMUA data dalam tabel yang dipilih.
                Data akan dibackup terlebih dahulu sebelum dihapus.
            </div>
            
            <form method="POST" action="" class="row g-3">
                <input type="hidden" name="action" value="reset_table">
                
                <div class="col-md-4">
                    <label class="form-label">Pilih Tabel</label>
                    <select name="table_name" class="form-select" required>
                        <option value="">Pilih Tabel</option>
                        <?php foreach($tables_info as $table): ?>
                        <option value="<?php echo $table['name']; ?>"><?php echo $table['name']; ?> (<?php echo number_format($table['rows']); ?> data)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Ketik "YES" untuk konfirmasi</label>
                    <input type="text" name="confirm" class="form-control" placeholder="Ketik YES" required>
                </div>
                
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Yakin ingin mereset tabel? Data akan dihapus!')">
                        <i class="fas fa-trash-alt"></i> Reset Tabel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Fix Result -->
    <?php if(!empty($fixed_tables)): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-success">
                <i class="fas fa-check-circle"></i> Hasil Perbaikan
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Tabel</th>
                            <th>Auto Increment</th>
                            <th>Data Dihapus (ID=0)</th>
                        </thead>
                    <tbody>
                        <?php foreach($fixed_tables as $fixed): ?>
                        <tr>
                            <td><code><?php echo $fixed['name']; ?></code></td>
                            <td>
                                <?php if($fixed['auto_fixed']): ?>
                                    <span class="badge bg-success">Diperbaiki</span>
                                <?php else: ?>
                                    <span class="badge bg-info">Sudah OK</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $fixed['deleted']; ?> data</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!empty($failed_tables)): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-danger">
                <i class="fas fa-times-circle"></i> Gagal Diproses
            </h6>
        </div>
        <div class="card-body">
            <ul>
                <?php foreach($failed_tables as $failed): ?>
                <li class="text-danger"><?php echo $failed; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function fixSingleTable(tableName) {
    if(confirm('Perbaiki tabel ' + tableName + '? Data dengan ID=0 akan dihapus.')) {
        // Create form and submit
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'fix_database';
        form.appendChild(actionInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-hide alerts
setTimeout(function() {
    let alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(() => {
            let bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 3000);
    });
}, 1000);
</script>

<style>
.border-left-primary { border-left: 4px solid #4e73df !important; }
.border-left-success { border-left: 4px solid #1cc88a !important; }
.border-left-warning { border-left: 4px solid #f6c23e !important; }
.border-left-danger { border-left: 4px solid #e74a3b !important; }
.text-gray-800 { color: #5a5c69 !important; }

.table th, .table td {
    vertical-align: middle;
}

code {
    background: #f8f9fc;
    padding: 2px 5px;
    border-radius: 4px;
}
</style>

<?php require_once '../../includes/footer.php'; ?>