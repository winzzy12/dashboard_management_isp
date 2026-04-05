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
require_once '../../models/Pop.php';
require_once '../../models/Odp.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize models
$pop = new Pop($db);
$odp = new Odp($db);

$success_message = '';
$error_message = '';

// Handle Add POP
if(isset($_POST['action']) && $_POST['action'] == 'add_pop') {
    $pop->kode_pop = trim($_POST['kode_pop']);
    $pop->nama_pop = trim($_POST['nama_pop']);
    $pop->lokasi = trim($_POST['lokasi']);
    $pop->latitude = $_POST['latitude'] ?: null;
    $pop->longitude = $_POST['longitude'] ?: null;
    $pop->alamat = trim($_POST['alamat']);
    $pop->kapasitas = $_POST['kapasitas'];
    $pop->status = $_POST['status'];
    $pop->keterangan = trim($_POST['keterangan']);
    
    if($pop->create()) {
        $success_message = "POP berhasil ditambahkan!";
    } else {
        $error_message = "Gagal menambahkan POP!";
    }
}

// Handle Edit POP
if(isset($_POST['action']) && $_POST['action'] == 'edit_pop') {
    $pop->id = $_POST['id'];
    $pop->kode_pop = trim($_POST['kode_pop']);
    $pop->nama_pop = trim($_POST['nama_pop']);
    $pop->lokasi = trim($_POST['lokasi']);
    $pop->latitude = $_POST['latitude'] ?: null;
    $pop->longitude = $_POST['longitude'] ?: null;
    $pop->alamat = trim($_POST['alamat']);
    $pop->kapasitas = $_POST['kapasitas'];
    $pop->status = $_POST['status'];
    $pop->keterangan = trim($_POST['keterangan']);
    
    if($pop->update()) {
        $success_message = "POP berhasil diupdate!";
    } else {
        $error_message = "Gagal mengupdate POP!";
    }
}

// Handle Delete POP
if(isset($_GET['delete']) && isset($_GET['id'])) {
    $pop->id = $_GET['id'];
    $result = $pop->delete();
    if($result['success']) {
        $success_message = $result['message'];
    } else {
        $error_message = $result['message'];
    }
    header("Location: index.php");
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get data with manual count for customers
$query = "SELECT p.*, 
          (SELECT COUNT(*) FROM odp WHERE pop_id = p.id) as jumlah_odp,
          (SELECT COUNT(*) FROM pelanggan WHERE pop_id = p.id) as jumlah_pelanggan
          FROM pop p";

if(!empty($search)) {
    $query .= " WHERE p.nama_pop LIKE :search OR p.kode_pop LIKE :search OR p.lokasi LIKE :search";
}

$query .= " ORDER BY p.id DESC LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);

if(!empty($search)) {
    $searchTerm = "%{$search}%";
    $stmt->bindParam(':search', $searchTerm);
}

$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

// Get total count
$total_query = "SELECT COUNT(*) as total FROM pop";
if(!empty($search)) {
    $total_query .= " WHERE nama_pop LIKE :search OR kode_pop LIKE :search OR lokasi LIKE :search";
}
$total_stmt = $db->prepare($total_query);
if(!empty($search)) {
    $searchTerm = "%{$search}%";
    $total_stmt->bindParam(':search', $searchTerm);
}
$total_stmt->execute();
$total_row = $total_stmt->fetch(PDO::FETCH_ASSOC);
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total_pop,
                    SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
                    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
                    SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif,
                    SUM(kapasitas) as total_kapasitas
                FROM pop";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Set default values if no data
if(!$stats['total_pop']) {
    $stats['total_pop'] = 0;
    $stats['aktif'] = 0;
    $stats['maintenance'] = 0;
    $stats['nonaktif'] = 0;
    $stats['total_kapasitas'] = 0;
}

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-tower-cell"></i> Management POP & ODP
        </h1>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPopModal">
                <i class="fas fa-plus"></i> Tambah POP
            </button>
            <a href="odp.php" class="btn btn-info">
                <i class="fas fa-exchange-alt"></i> Kelola ODP
            </a>
            <button class="btn btn-success" onclick="window.location.href='map.php'">
                <i class="fas fa-map-marked-alt"></i> Lihat Peta
            </button>
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
                                Total POP</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_pop']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tower-cell fa-2x text-gray-300"></i>
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
                                POP Aktif</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['aktif']); ?>
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
                                <?php echo number_format($stats['maintenance']); ?>
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
                                <?php echo number_format($stats['total_kapasitas']); ?>
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
                <i class="fas fa-search"></i> Cari POP
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-10">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Cari berdasarkan nama POP, kode POP, atau lokasi..."
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

    <!-- POP List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list"></i> Daftar POP
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="10%">Kode POP</th>
                            <th width="15%">Nama POP</th>
                            <th width="15%">Lokasi</th>
                            <th width="10%">Koordinat</th>
                            <th width="10%">Kapasitas</th>
                            <th width="8%">Jumlah ODP</th>
                            <th width="8%">Pelanggan</th>
                            <th width="8%">Status</th>
                            <th width="11%">Aksi</th>
                        </thead>
                    <tbody>
                        <?php $no = $offset + 1; while($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['kode_pop']); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['nama_pop']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['lokasi']); ?></td>
                            <td class="text-center">
                                <?php if($row['latitude'] && $row['longitude']): ?>
                                    <span class="badge bg-info">
                                        <?php echo $row['latitude']; ?>, <?php echo $row['longitude']; ?>
                                    </span>
                                    <br>
                                    <a href="map.php?lat=<?php echo $row['latitude']; ?>&lng=<?php echo $row['longitude']; ?>" 
                                       target="_blank" class="btn btn-sm btn-link">
                                        <i class="fas fa-map-marker-alt"></i> Lihat Peta
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo number_format($row['kapasitas']); ?></td>
                            <td class="text-center">
                                <span class="badge bg-secondary"><?php echo $row['jumlah_odp'] ?? 0; ?> ODP</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info"><?php echo $row['jumlah_pelanggan'] ?? 0; ?> Pelanggan</span>
                            </td>
                            <td class="text-center">
                                <?php
                                $status_class = [
                                    'aktif' => 'success',
                                    'maintenance' => 'warning',
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
                                    <button class="btn btn-sm btn-info" onclick="editPop(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="odp.php?pop_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-exchange-alt"></i>
                                    </a>
                                    <?php if(($row['jumlah_odp'] ?? 0) == 0): ?>
                                    <a href="?delete=1&id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Yakin ingin menghapus POP <?php echo addslashes($row['nama_pop']); ?>?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-danger" disabled title="Tidak dapat dihapus karena memiliki ODP">
                                        <i class="fas fa-trash"></i>
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
                                <p>Belum ada data POP</p>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPopModal">
                                    <i class="fas fa-plus"></i> Tambah POP Sekarang
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
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
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

<!-- Modal Add POP -->
<div class="modal fade" id="addPopModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus"></i> Tambah POP
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_pop">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kode POP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="kode_pop" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama POP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_pop" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Latitude</label>
                            <input type="text" class="form-control" name="latitude" id="latitude" placeholder="Contoh: -6.200000">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Longitude</label>
                            <input type="text" class="form-control" name="longitude" id="longitude" placeholder="Contoh: 106.816666">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Lokasi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="lokasi" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea class="form-control" name="alamat" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kapasitas <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="kapasitas" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="aktif">Aktif</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="2"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Tips:</strong> Anda dapat mengambil koordinat dari Google Maps. Klik kanan pada lokasi, pilih "What's here?" untuk melihat koordinat.
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

<!-- Modal Edit POP -->
<div class="modal fade" id="editPopModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Edit POP
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_pop">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kode POP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="kode_pop" id="edit_kode_pop" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama POP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_pop" id="edit_nama_pop" required>
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
                        <label class="form-label">Lokasi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="lokasi" id="edit_lokasi" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea class="form-control" name="alamat" id="edit_alamat" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kapasitas <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="kapasitas" id="edit_kapasitas" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="aktif">Aktif</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
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
// Edit POP function
function editPop(pop) {
    document.getElementById('edit_id').value = pop.id;
    document.getElementById('edit_kode_pop').value = pop.kode_pop;
    document.getElementById('edit_nama_pop').value = pop.nama_pop;
    document.getElementById('edit_latitude').value = pop.latitude || '';
    document.getElementById('edit_longitude').value = pop.longitude || '';
    document.getElementById('edit_lokasi').value = pop.lokasi;
    document.getElementById('edit_alamat').value = pop.alamat || '';
    document.getElementById('edit_kapasitas').value = pop.kapasitas;
    document.getElementById('edit_status').value = pop.status;
    document.getElementById('edit_keterangan').value = pop.keterangan || '';
    
    var modal = new bootstrap.Modal(document.getElementById('editPopModal'));
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