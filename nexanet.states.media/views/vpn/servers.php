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

$success_message = '';
$error_message = '';

// Get user role
$user_role = $_SESSION['role'];

// Handle Add Server
if(isset($_POST['action']) && $_POST['action'] == 'add') {
    $vpnServer->kode_server = trim($_POST['kode_server']);
    $vpnServer->nama_server = trim($_POST['nama_server']);
    $vpnServer->ip_address = trim($_POST['ip_address']);
    $vpnServer->port = $_POST['port'];
    $vpnServer->protocol = $_POST['protocol'];
    $vpnServer->server_type = $_POST['server_type'];
    $vpnServer->lokasi = trim($_POST['lokasi']);
    $vpnServer->max_clients = $_POST['max_clients'];
    $vpnServer->status = $_POST['status'];
    $vpnServer->config_file = $_POST['config_file'];
    $vpnServer->keterangan = trim($_POST['keterangan']);
    
    if($vpnServer->create()) {
        $success_message = "Server VPN berhasil ditambahkan!";
    } else {
        $error_message = "Gagal menambahkan server VPN!";
    }
}

// Handle Edit Server
if(isset($_POST['action']) && $_POST['action'] == 'edit') {
    $vpnServer->id = $_POST['id'];
    $vpnServer->kode_server = trim($_POST['kode_server']);
    $vpnServer->nama_server = trim($_POST['nama_server']);
    $vpnServer->ip_address = trim($_POST['ip_address']);
    $vpnServer->port = $_POST['port'];
    $vpnServer->protocol = $_POST['protocol'];
    $vpnServer->server_type = $_POST['server_type'];
    $vpnServer->lokasi = trim($_POST['lokasi']);
    $vpnServer->max_clients = $_POST['max_clients'];
    $vpnServer->status = $_POST['status'];
    $vpnServer->config_file = $_POST['config_file'];
    $vpnServer->keterangan = trim($_POST['keterangan']);
    
    if($vpnServer->update()) {
        $success_message = "Server VPN berhasil diupdate!";
    } else {
        $error_message = "Gagal mengupdate server VPN!";
    }
}

// Handle Delete Server
if(isset($_GET['delete']) && isset($_GET['id'])) {
    $vpnServer->id = $_GET['id'];
    $result = $vpnServer->delete();
    if($result['success']) {
        $success_message = $result['message'];
    } else {
        $error_message = $result['message'];
    }
    header("Location: servers.php");
    exit();
}

// Handle Toggle Status
if(isset($_GET['toggle']) && isset($_GET['id'])) {
    $vpnServer->id = $_GET['id'];
    $server = $vpnServer->getOne();
    if($server) {
        $new_status = $server['status'] == 'active' ? 'maintenance' : ($server['status'] == 'maintenance' ? 'inactive' : 'active');
        $vpnServer->status = $new_status;
        $vpnServer->update();
        $success_message = "Status server berhasil diubah menjadi " . ucfirst($new_status);
    }
    header("Location: servers.php");
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get data
$stmt = $vpnServer->read($search, $limit, $offset);
$total = $vpnServer->getTotal($search);
$total_pages = ceil($total / $limit);

// Get statistics
$stats = $vpnServer->getStats();

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-server"></i> Kelola Server VPN
        </h1>
        <div>
            <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServerModal">
                <i class="fas fa-plus"></i> Tambah Server
            </button>
            <?php endif; ?>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

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
                                <?php echo number_format($stats['total_servers'] ?? 0); ?>
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
                                Server Aktif</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['aktif'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                Maintenance</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['maintenance'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tools fa-2x text-gray-300"></i>
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
                                Total Kapasitas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_capacity'] ?? 0); ?>
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

    <!-- Search Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-search"></i> Cari Server
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-10">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Cari berdasarkan nama server, kode server, atau lokasi..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Cari
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Server List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list"></i> Daftar Server VPN
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
                            <th width="12%">IP Address</th>
                            <th width="8%">Port</th>
                            <th width="8%">Protocol</th>
                            <th width="8%">Type</th>
                            <th width="10%">Lokasi</th>
                            <th width="8%">Client</th>
                            <th width="8%">Status</th>
                            <th width="10%">Aksi</th>
                        </thead>
                    <tbody>
                        <?php 
                        $no = $offset + 1;
                        $has_data = false;
                        
                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                            $has_data = true;
                            $total_clients = isset($row['total_clients']) ? (int)$row['total_clients'] : 0;
                            $max_clients = isset($row['max_clients']) ? (int)$row['max_clients'] : 1;
                            $usage_percent = $max_clients > 0 ? ($total_clients / $max_clients) * 100 : 0;
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
                            <td class="text-center">
                                <span class="badge bg-info"><?php echo strtoupper($row['server_type']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($row['lokasi']); ?></td>
                            <td class="text-center">
                                <?php echo $total_clients; ?>/<?php echo $max_clients; ?>
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
                                    <a href="clients.php?server_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info" title="Lihat Client">
                                        <i class="fas fa-users"></i>
                                    </a>
                                    <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
                                    <button class="btn btn-sm btn-warning" onclick="editServer(<?php echo htmlspecialchars(json_encode($row)); ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?toggle=1&id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm <?php echo $row['status'] == 'active' ? 'btn-secondary' : 'btn-success'; ?>" 
                                       title="Ubah Status"
                                       onclick="return confirm('Ubah status server <?php echo addslashes($row['nama_server']); ?>?')">
                                        <i class="fas <?php echo $row['status'] == 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                    </a>
                                    <?php if($total_clients == 0): ?>
                                    <a href="?delete=1&id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       title="Hapus"
                                       onclick="return confirm('Yakin ingin menghapus server <?php echo addslashes($row['nama_server']); ?>?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-danger" disabled title="Tidak dapat dihapus karena memiliki client">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if(!$has_data): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                <p>Belum ada server VPN</p>
                                <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addServerModal">
                                    <i class="fas fa-plus"></i> Tambah Server Sekarang
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="row mt-3">
                <div class="col-md-6">
                    <p class="text-muted">
                        Menampilkan <?php echo $offset + 1; ?> - 
                        <?php echo min($offset + $limit, $total); ?> 
                        dari <?php echo $total; ?> data
                    </p>
                </div>
                <div class="col-md-6">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-end">
                            <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Add Server -->
<div class="modal fade" id="addServerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus"></i> Tambah Server VPN
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kode Server <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="kode_server" required>
                            <div class="form-text">Contoh: VPN-SG-01</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Server <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_server" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">IP Address <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ip_address" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Port</label>
                            <input type="number" class="form-control" name="port" value="1194">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Protocol</label>
                            <select class="form-select" name="protocol">
                                <option value="udp">UDP</option>
                                <option value="tcp">TCP</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Server Type</label>
                            <select class="form-select" name="server_type">
                                <option value="openvpn">OpenVPN</option>
                                <option value="wireguard">WireGuard</option>
                                <option value="l2tp">L2TP</option>
                                <option value="pptp">PPTP</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Lokasi</label>
                            <input type="text" class="form-control" name="lokasi" placeholder="Contoh: Singapore, Jakarta">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Clients</label>
                            <input type="number" class="form-control" name="max_clients" value="50">
                        </div>
                    </div>
                    
                    <div class="row">
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
                        <label class="form-label">Config File (Base64)</label>
                        <textarea class="form-control" name="config_file" rows="3" placeholder="Masukkan konfigurasi server dalam base64"></textarea>
                        <div class="form-text">Opsional, untuk konfigurasi server</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="2" placeholder="Informasi tambahan tentang server"></textarea>
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

<!-- Modal Edit Server -->
<div class="modal fade" id="editServerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Edit Server VPN
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kode Server <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="kode_server" id="edit_kode_server" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Server <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_server" id="edit_nama_server" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">IP Address <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ip_address" id="edit_ip_address" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Port</label>
                            <input type="number" class="form-control" name="port" id="edit_port">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Protocol</label>
                            <select class="form-select" name="protocol" id="edit_protocol">
                                <option value="udp">UDP</option>
                                <option value="tcp">TCP</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Server Type</label>
                            <select class="form-select" name="server_type" id="edit_server_type">
                                <option value="openvpn">OpenVPN</option>
                                <option value="wireguard">WireGuard</option>
                                <option value="l2tp">L2TP</option>
                                <option value="pptp">PPTP</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Lokasi</label>
                            <input type="text" class="form-control" name="lokasi" id="edit_lokasi">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Clients</label>
                            <input type="number" class="form-control" name="max_clients" id="edit_max_clients">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Config File (Base64)</label>
                        <textarea class="form-control" name="config_file" id="edit_config_file" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" id="edit_keterangan" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit Server function
function editServer(server) {
    document.getElementById('edit_id').value = server.id;
    document.getElementById('edit_kode_server').value = server.kode_server;
    document.getElementById('edit_nama_server').value = server.nama_server;
    document.getElementById('edit_ip_address').value = server.ip_address;
    document.getElementById('edit_port').value = server.port;
    document.getElementById('edit_protocol').value = server.protocol;
    document.getElementById('edit_server_type').value = server.server_type;
    document.getElementById('edit_lokasi').value = server.lokasi || '';
    document.getElementById('edit_max_clients').value = server.max_clients;
    document.getElementById('edit_status').value = server.status;
    document.getElementById('edit_config_file').value = server.config_file || '';
    document.getElementById('edit_keterangan').value = server.keterangan || '';
    
    var modal = new bootstrap.Modal(document.getElementById('editServerModal'));
    modal.show();
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

.modal-header .btn-close {
    filter: brightness(0) invert(1);
}
</style>

<?php require_once '../../includes/footer.php'; ?>