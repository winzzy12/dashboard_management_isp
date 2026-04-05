<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Include required files
require_once '../../config/database.php';
require_once '../../models/VpnServer.php';
require_once '../../models/VpnClient.php';
require_once '../../models/Pelanggan.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize models
$vpnServer = new VpnServer($db);
$vpnClient = new VpnClient($db);
$pelanggan = new Pelanggan($db);

$success_message = '';
$error_message = '';

// Get user role
$user_role = $_SESSION['role'];

// Get statistics with null checking
$server_stats = $vpnServer->getStats();
$client_stats = $vpnClient->getStats();

// Ensure values are not null
$total_servers = isset($server_stats['total_servers']) ? (int)$server_stats['total_servers'] : 0;
$aktif_servers = isset($server_stats['aktif']) ? (int)$server_stats['aktif'] : 0;
$total_clients = isset($client_stats['total_clients']) ? (int)$client_stats['total_clients'] : 0;
$aktif_clients = isset($client_stats['aktif']) ? (int)$client_stats['aktif'] : 0;
$total_capacity = isset($server_stats['total_capacity']) ? (int)$server_stats['total_capacity'] : 0;
$total_clients_count = isset($server_stats['total_clients']) ? (int)$server_stats['total_clients'] : 0;
$total_data_used = isset($client_stats['total_data_used']) ? (float)$client_stats['total_data_used'] : 0;

// Get all servers for dropdown
$servers = $vpnServer->getAll();

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-shield-alt"></i> VPN Management
        </h1>
        <div>
            <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServerModal">
                <i class="fas fa-plus"></i> Tambah Server
            </button>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addClientModal">
                <i class="fas fa-user-plus"></i> Tambah Client
            </button>
            <?php endif; ?>
            <a href="servers.php" class="btn btn-info">
                <i class="fas fa-server"></i> Kelola Server
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Server</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_servers); ?>
                            </div>
                            <div class="small text-muted">
                                Aktif: <?php echo number_format($aktif_servers); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-server fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Client</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_clients); ?>
                            </div>
                            <div class="small text-muted">
                                Aktif: <?php echo number_format($aktif_clients); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Total Kapasitas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_capacity); ?>
                            </div>
                            <div class="small text-muted">
                                Client: <?php echo number_format($total_clients_count); ?>/<?php echo number_format($total_capacity); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Data Terpakai</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $total_data_gb = $total_data_used / (1024 * 1024 * 1024);
                                echo number_format($total_data_gb, 2) . ' GB';
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-database fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Server List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-server"></i> Daftar Server VPN
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="10%">Kode</th>
                            <th width="15%">Nama Server</th>
                            <th width="10%">IP Address</th>
                            <th width="8%">Port</th>
                            <th width="8%">Protocol</th>
                            <th width="10%">Lokasi</th>
                            <th width="8%">Client</th>
                            <th width="8%">Status</th>
                            <th width="10%">Aksi</th>
                        </thead>
                    <tbody>
                        <?php 
                        $stmt = $vpnServer->read('', 10, 0);
                        $no = 1;
                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                            $total_clients_server = isset($row['total_clients']) ? (int)$row['total_clients'] : 0;
                            $max_clients = isset($row['max_clients']) ? (int)$row['max_clients'] : 1;
                            $usage_percent = $max_clients > 0 ? ($total_clients_server / $max_clients) * 100 : 0;
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['kode_server']); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['nama_server']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                            <td class="text-center"><?php echo $row['port']; ?></td>
                            <td class="text-center">
                                <span class="badge bg-secondary"><?php echo strtoupper($row['protocol']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($row['lokasi']); ?></td>
                            <td class="text-center">
                                <?php echo $total_clients_server; ?>/<?php echo $max_clients; ?>
                                <div class="progress mt-1" style="height: 5px;">
                                    <div class="progress-bar bg-<?php echo $usage_percent > 80 ? 'danger' : ($usage_percent > 60 ? 'warning' : 'success'); ?>" 
                                         style="width: <?php echo $usage_percent; ?>%;"></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php
                                $status_class = [
                                    'active' => 'success',
                                    'maintenance' => 'warning',
                                    'inactive' => 'secondary'
                                ];
                                $status_badge = isset($status_class[$row['status']]) ? $status_class[$row['status']] : 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $status_badge; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="clients.php?server_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-users"></i>
                                    </a>
                                    <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
                                    <button class="btn btn-sm btn-warning" onclick="editServer(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($stmt->rowCount() == 0): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                <p>Belum ada server VPN</p>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addServerModal">
                                    <i class="fas fa-plus"></i> Tambah Server Sekarang
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Clients -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-users"></i> Client VPN Terbaru
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="10%">Client ID</th>
                            <th width="15%">Username</th>
                            <th width="15%">Server</th>
                            <th width="10%">IP Address</th>
                            <th width="10%">Status</th>
                            <th width="15%">Expired Date</th>
                            <th width="10%">Aksi</th>
                        </thead>
                    <tbody>
                        <?php 
                        $stmt = $vpnClient->read('', '', 10, 0);
                        $no = 1;
                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                            $is_expired = !empty($row['expired_date']) && strtotime($row['expired_date']) < time();
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['client_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['server_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['ip_address'] ?: '-'); ?></td>
                            <td class="text-center">
                                <?php
                                $status_class = [
                                    'active' => 'success',
                                    'inactive' => 'secondary',
                                    'expired' => 'danger'
                                ];
                                $status_badge = isset($status_class[$row['status']]) ? $status_class[$row['status']] : 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $status_badge; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php echo $row['expired_date'] ? date('d/m/Y', strtotime($row['expired_date'])) : '-'; ?>
                                <?php if($is_expired && $row['status'] != 'expired'): ?>
                                    <br><small class="text-danger">Expired</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="client-config.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success" target="_blank">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
                                    <button class="btn btn-sm btn-warning" onclick="editClient(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($stmt->rowCount() == 0): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                <p>Belum ada client VPN</p>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addClientModal">
                                    <i class="fas fa-plus"></i> Tambah Client Sekarang
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-3">
                <a href="clients.php" class="btn btn-sm btn-primary">Lihat Semua Client <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Server (Sederhana) -->
<div class="modal fade" id="addServerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Tambah Server VPN</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="servers.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Kode Server <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kode_server" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Server <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_server" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">IP Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ip_address" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Port</label>
                            <input type="number" class="form-control" name="port" value="1194">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Protocol</label>
                            <select class="form-select" name="protocol">
                                <option value="udp">UDP</option>
                                <option value="tcp">TCP</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lokasi</label>
                        <input type="text" class="form-control" name="lokasi">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Clients</label>
                            <input type="number" class="form-control" name="max_clients" value="50">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit Server function
function editServer(server) {
    alert('Fitur edit server akan segera hadir');
}

// Edit Client function
function editClient(client) {
    alert('Fitur edit client akan segera hadir');
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
.border-left-info { border-left: 4px solid #36b9cc !important; }
.text-gray-800 { color: #5a5c69 !important; }

.table th, .table td {
    vertical-align: middle;
}

.progress {
    background-color: #e9ecef;
    border-radius: 5px;
    overflow: hidden;
}

.badge {
    font-size: 11px;
    padding: 5px 8px;
}

.btn-group .btn {
    margin: 0 2px;
}
</style>

<?php require_once '../../includes/footer.php'; ?>