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
require_once '../../models/Odp.php';
require_once '../../models/Pop.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize models
$odp = new Odp($db);
$pop = new Pop($db);

$success_message = '';
$error_message = '';

// Handle Add ODP
if(isset($_POST['action']) && $_POST['action'] == 'add_odp') {
    $odp->kode_odp = trim($_POST['kode_odp']);
    $odp->nama_odp = trim($_POST['nama_odp']);
    $odp->pop_id = $_POST['pop_id'];
    $odp->latitude = $_POST['latitude'] ?: null;
    $odp->longitude = $_POST['longitude'] ?: null;
    $odp->alamat = trim($_POST['alamat']);
    $odp->jumlah_port = $_POST['jumlah_port'];
    $odp->port_terpakai = $_POST['port_terpakai'] ?: 0;
    $odp->status = $_POST['status'];
    $odp->keterangan = trim($_POST['keterangan']);
    
    if($odp->create()) {
        $success_message = "ODP berhasil ditambahkan!";
    } else {
        $error_message = "Gagal menambahkan ODP!";
    }
}

// Handle Edit ODP
if(isset($_POST['action']) && $_POST['action'] == 'edit_odp') {
    $odp->id = $_POST['id'];
    $odp->kode_odp = trim($_POST['kode_odp']);
    $odp->nama_odp = trim($_POST['nama_odp']);
    $odp->pop_id = $_POST['pop_id'];
    $odp->latitude = $_POST['latitude'] ?: null;
    $odp->longitude = $_POST['longitude'] ?: null;
    $odp->alamat = trim($_POST['alamat']);
    $odp->jumlah_port = $_POST['jumlah_port'];
    $odp->port_terpakai = $_POST['port_terpakai'] ?: 0;
    $odp->status = $_POST['status'];
    $odp->keterangan = trim($_POST['keterangan']);
    
    if($odp->update()) {
        $success_message = "ODP berhasil diupdate!";
    } else {
        $error_message = "Gagal mengupdate ODP!";
    }
}

// Handle Delete ODP
if(isset($_GET['delete']) && isset($_GET['id'])) {
    $odp->id = $_GET['id'];
    $result = $odp->delete();
    if($result['success']) {
        $success_message = $result['message'];
    } else {
        $error_message = $result['message'];
    }
    header("Location: odp.php");
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$pop_id = isset($_GET['pop_id']) ? $_GET['pop_id'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get data with jumlah_pelanggan
$query = "SELECT o.*, p.nama_pop as nama_pop, p.kode_pop as kode_pop,
          (SELECT COUNT(*) FROM pelanggan WHERE odp_id = o.id) as jumlah_pelanggan
          FROM odp o
          LEFT JOIN pop p ON o.pop_id = p.id";

$conditions = [];
if(!empty($search)) {
    $conditions[] = "(o.nama_odp LIKE :search OR o.kode_odp LIKE :search OR o.alamat LIKE :search)";
}
if(!empty($pop_id)) {
    $conditions[] = "o.pop_id = :pop_id";
}

if(count($conditions) > 0) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY o.id DESC LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);

if(!empty($search)) {
    $searchTerm = "%{$search}%";
    $stmt->bindParam(':search', $searchTerm);
}
if(!empty($pop_id)) {
    $stmt->bindParam(':pop_id', $pop_id);
}

$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

// Get total count
$total_query = "SELECT COUNT(*) as total FROM odp o";
$total_conditions = [];
if(!empty($search)) {
    $total_conditions[] = "(o.nama_odp LIKE :search OR o.kode_odp LIKE :search OR o.alamat LIKE :search)";
}
if(!empty($pop_id)) {
    $total_conditions[] = "o.pop_id = :pop_id";
}
if(count($total_conditions) > 0) {
    $total_query .= " WHERE " . implode(" AND ", $total_conditions);
}

$total_stmt = $db->prepare($total_query);
if(!empty($search)) {
    $searchTerm = "%{$search}%";
    $total_stmt->bindParam(':search', $searchTerm);
}
if(!empty($pop_id)) {
    $total_stmt->bindParam(':pop_id', $pop_id);
}
$total_stmt->execute();
$total_row = $total_stmt->fetch(PDO::FETCH_ASSOC);
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total_odp,
                    SUM(jumlah_port) as total_port,
                    SUM(port_terpakai) as total_port_terpakai,
                    SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
                    SUM(CASE WHEN status = 'penuh' THEN 1 ELSE 0 END) as penuh,
                    SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif
                FROM odp";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Set default values if no data
if(!$stats['total_odp']) {
    $stats['total_odp'] = 0;
    $stats['total_port'] = 0;
    $stats['total_port_terpakai'] = 0;
    $stats['aktif'] = 0;
    $stats['penuh'] = 0;
    $stats['nonaktif'] = 0;
}

// Get POP list for filter
$pop_list = $pop->getAll();

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-exchange-alt"></i> Management ODP
        </h1>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOdpModal">
                <i class="fas fa-plus"></i> Tambah ODP
            </button>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke POP
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
                                Total ODP</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_odp']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exchange-alt fa-2x text-gray-300"></i>
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
                                Total Port</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_port']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-plug fa-2x text-gray-300"></i>
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
                                Port Terpakai</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_port_terpakai']); ?>
                            </div>
                            <div class="small text-muted">
                                <?php 
                                $persen = $stats['total_port'] > 0 ? ($stats['total_port_terpakai'] / $stats['total_port']) * 100 : 0;
                                echo number_format($persen, 1); ?>% terpakai
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
                                Port Tersedia</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_port'] - $stats['total_port_terpakai']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                <i class="fas fa-search"></i> Filter ODP
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Cari berdasarkan nama ODP, kode ODP..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="pop_id" class="form-select">
                        <option value="">Semua POP</option>
                        <?php foreach($pop_list as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $pop_id == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['nama_pop']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ODP List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list"></i> Daftar ODP
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="10%">Kode ODP</th>
                            <th width="15%">Nama ODP</th>
                            <th width="15%">POP</th>
                            <th width="10%">Koordinat</th>
                            <th width="8%">Port</th>
                            <th width="8%">Terpakai</th>
                            <th width="8%">Tersedia</th>
                            <th width="8%">Pelanggan</th>
                            <th width="8%">Status</th>
                            <th width="10%">Aksi</th>
                        </thead>
                    <tbody>
                        <?php $no = $offset + 1; while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                            $tersedia = $row['jumlah_port'] - $row['port_terpakai'];
                            $port_class = $tersedia == 0 ? 'text-danger' : ($tersedia < 5 ? 'text-warning' : 'text-success');
                            $jumlah_pelanggan = $row['jumlah_pelanggan'] ?? 0;
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['kode_odp']); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['nama_odp']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['nama_pop'] ?? '-'); ?></td>
                            <td class="text-center">
                                <?php if($row['latitude'] && $row['longitude']): ?>
                                    <span class="badge bg-info">
                                        <?php echo $row['latitude']; ?>, <?php echo $row['longitude']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo number_format($row['jumlah_port']); ?></td>
                            <td class="text-center"><?php echo number_format($row['port_terpakai']); ?></td>
                            <td class="text-center <?php echo $port_class; ?>">
                                <?php echo number_format($tersedia); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info"><?php echo $jumlah_pelanggan; ?> Pelanggan</span>
                            </td>
                            <td class="text-center">
                                <?php
                                $status_class = [
                                    'aktif' => 'success',
                                    'penuh' => 'danger',
                                    'nonaktif' => 'secondary'
                                ];
                                $status_badge = $status_class[$row['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $status_badge; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-info" onclick="editOdp(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if($jumlah_pelanggan == 0): ?>
                                    <a href="?delete=1&id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Yakin ingin menghapus ODP <?php echo addslashes($row['nama_odp']); ?>?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-danger" disabled title="Tidak dapat dihapus karena memiliki <?php echo $jumlah_pelanggan; ?> pelanggan">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($stmt->rowCount() == 0): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                <p>Belum ada data ODP</p>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addOdpModal">
                                    <i class="fas fa-plus"></i> Tambah ODP Sekarang
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&pop_id=<?php echo $pop_id; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Add ODP -->
<div class="modal fade" id="addOdpModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus"></i> Tambah ODP
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_odp">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kode ODP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="kode_odp" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama ODP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_odp" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">POP <span class="text-danger">*</span></label>
                            <select class="form-select" name="pop_id" required>
                                <option value="">Pilih POP</option>
                                <?php foreach($pop_list as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nama_pop']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="aktif">Aktif</option>
                                <option value="penuh">Penuh</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Latitude</label>
                            <input type="text" class="form-control" name="latitude" placeholder="Contoh: -6.200000">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Longitude</label>
                            <input type="text" class="form-control" name="longitude" placeholder="Contoh: 106.816666">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" name="alamat" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jumlah Port <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="jumlah_port" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Port Terpakai</label>
                            <input type="number" class="form-control" name="port_terpakai" value="0">
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

<!-- Modal Edit ODP -->
<div class="modal fade" id="editOdpModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Edit ODP
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_odp">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kode ODP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="kode_odp" id="edit_kode_odp" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama ODP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_odp" id="edit_nama_odp" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">POP <span class="text-danger">*</span></label>
                            <select class="form-select" name="pop_id" id="edit_pop_id" required>
                                <option value="">Pilih POP</option>
                                <?php foreach($pop_list as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nama_pop']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="aktif">Aktif</option>
                                <option value="penuh">Penuh</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Latitude</label>
                            <input type="text" class="form-control" name="latitude" id="edit_latitude">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Longitude</label>
                            <input type="text" class="form-control" name="longitude" id="edit_longitude">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" name="alamat" id="edit_alamat" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jumlah Port <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="jumlah_port" id="edit_jumlah_port" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Port Terpakai</label>
                            <input type="number" class="form-control" name="port_terpakai" id="edit_port_terpakai">
                        </div>
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
// Edit ODP function
function editOdp(odp) {
    document.getElementById('edit_id').value = odp.id;
    document.getElementById('edit_kode_odp').value = odp.kode_odp;
    document.getElementById('edit_nama_odp').value = odp.nama_odp;
    document.getElementById('edit_pop_id').value = odp.pop_id;
    document.getElementById('edit_latitude').value = odp.latitude || '';
    document.getElementById('edit_longitude').value = odp.longitude || '';
    document.getElementById('edit_alamat').value = odp.alamat || '';
    document.getElementById('edit_jumlah_port').value = odp.jumlah_port;
    document.getElementById('edit_port_terpakai').value = odp.port_terpakai || 0;
    document.getElementById('edit_status').value = odp.status;
    document.getElementById('edit_keterangan').value = odp.keterangan || '';
    
    var modal = new bootstrap.Modal(document.getElementById('editOdpModal'));
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

.btn-group .btn {
    margin: 0 2px;
}

.badge {
    font-size: 11px;
    padding: 5px 8px;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
}
</style>

<?php require_once '../../includes/footer.php'; ?>