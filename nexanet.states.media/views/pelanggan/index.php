<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../../config/database.php';
require_once '../../models/Pelanggan.php';
require_once '../../models/Paket.php';
require_once '../../includes/header.php';

// Get user role
$user_role = $_SESSION['role'];

$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal! Silakan cek konfigurasi database.</div>");
}

$pelanggan = new Pelanggan($db);
$paket = new Paket($db);

// Handle delete - ONLY for admin and operator
if(isset($_GET['delete']) && ($user_role == 'admin' || $user_role == 'operator')) {
    $pelanggan->id = $_GET['delete'];
    if($pelanggan->delete()) {
        $_SESSION['success'] = "Data pelanggan berhasil dihapus!";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Gagal menghapus data pelanggan!";
    }
}

// Get search parameter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get data with join to pop and odp
$query = "SELECT p.*, 
          pk.nama_paket as paket_nama, 
          po.nama_pop, po.kode_pop,
          od.nama_odp, od.kode_odp,
          CASE 
              WHEN p.latitude IS NOT NULL AND p.longitude IS NOT NULL 
              THEN CONCAT(p.latitude, ', ', p.longitude)
              ELSE '-'
          END as koordinat
          FROM pelanggan p
          LEFT JOIN paket_internet pk ON p.paket_id = pk.id
          LEFT JOIN pop po ON p.pop_id = po.id
          LEFT JOIN odp od ON p.odp_id = od.id";

if(!empty($search)) {
    $query .= " WHERE p.nama LIKE :search OR p.id_pelanggan LIKE :search OR p.alamat LIKE :search OR p.no_hp LIKE :search";
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
$total_query = "SELECT COUNT(*) as total FROM pelanggan";
if(!empty($search)) {
    $total_query .= " WHERE nama LIKE :search OR id_pelanggan LIKE :search OR alamat LIKE :search OR no_hp LIKE :search";
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
?>

<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h5 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-users me-1"></i> Data Pelanggan
            </h5>
            <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
            <a href="create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Tambah Pelanggan
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <!-- Search Form -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <form method="GET" action="" class="d-flex">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Cari berdasarkan nama, ID, alamat, atau no HP..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Cari
                            </button>
                            <?php if(!empty($search)): ?>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-success btn-sm" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                    <button class="btn btn-info btn-sm" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="map.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-map-marked-alt"></i> Lihat Peta
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-1"></i> <?php echo $_SESSION['success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-1"></i> <?php echo $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Data Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="pelangganTable">
                    <thead class="table-light">
                        <tr>
                            <th width="3%">No</th>
                            <th width="8%">ID Pelanggan</th>
                            <th width="12%">Nama</th>
                            <th width="15%">Alamat</th>
                            <th width="8%">No HP</th>
                            <th width="10%">Paket Internet</th>
                            <th width="8%">Harga</th>
                            <th width="8%">POP</th>
                            <th width="8%">ODP</th>
                            <th width="8%">Koordinat</th>
                            <th width="5%">Status</th>
                            <th width="7%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = $offset + 1;
                        $has_data = false;
                        
                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                            $has_data = true;
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['id_pelanggan']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['nama']); ?></strong>
                                <?php if($row['latitude'] && $row['longitude']): ?>
                                    <i class="fas fa-map-marker-alt text-info ms-1" title="Memiliki koordinat"></i>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(substr($row['alamat'], 0, 50)) . (strlen($row['alamat']) > 50 ? '...' : ''); ?></td>
                            <td><?php echo htmlspecialchars($row['no_hp']); ?></td>
                            <td><?php echo htmlspecialchars($row['paket_internet']); ?></td>
                            <td class="text-end">Rp <?php echo number_format($row['harga_paket'], 0, ',', '.'); ?></td>
                            <td class="text-center">
                                <?php if($row['nama_pop']): ?>
                                    <span class="badge bg-primary" title="<?php echo htmlspecialchars($row['kode_pop']); ?>">
                                        <?php echo htmlspecialchars($row['nama_pop']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($row['nama_odp']): ?>
                                    <span class="badge bg-info" title="<?php echo htmlspecialchars($row['kode_odp']); ?>">
                                        <?php echo htmlspecialchars($row['nama_odp']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($row['latitude'] && $row['longitude']): ?>
                                    <span class="badge bg-secondary" title="<?php echo $row['koordinat']; ?>">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo substr($row['koordinat'], 0, 20); ?>...
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($row['status'] == 'aktif'): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Yakin ingin menghapus data pelanggan <?php echo htmlspecialchars($row['nama']); ?>?')"
                                           title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled title="Viewer tidak memiliki akses edit/hapus">
                                            <i class="fas fa-eye"></i> View Only
                                        </button>
                                    <?php endif; ?>
                                    <a href="detail.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-info" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if($row['latitude'] && $row['longitude']): ?>
                                    <a href="https://www.google.com/maps?q=<?php echo $row['latitude']; ?>,<?php echo $row['longitude']; ?>" 
                                       class="btn btn-sm btn-secondary" target="_blank" title="Lihat di Google Maps">
                                        <i class="fas fa-map-marked-alt"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php 
                        endwhile;
                        
                        if(!$has_data):
                        ?>
                        <tr>
                            <td colspan="12" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                <p>Tidak ada data pelanggan</p>
                                <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
                                <a href="create.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Tambah Pelanggan Sekarang
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if($has_data): ?>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="6" class="text-end">Total Pelanggan: </td>
                            <td class="text-end text-primary">
                                <?php echo $total; ?> Pelanggan
                            </td>
                            <td colspan="5"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
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

<script>
function exportToExcel() {
    let table = document.getElementById('pelangganTable');
    let html = table.outerHTML;
    let blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    let link = document.createElement('a');
    let url = URL.createObjectURL(blob);
    link.href = url;
    link.download = 'data_pelanggan.xls';
    link.click();
    URL.revokeObjectURL(url);
}

setTimeout(function() {
    let alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        let bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 3000);

// Initialize DataTable for better sorting and searching (optional)
$(document).ready(function() {
    $('#pelangganTable').DataTable({
        "paging": false,
        "searching": false,
        "ordering": true,
        "info": false,
        "order": [[0, 'desc']],
        "language": {
            "zeroRecords": "Tidak ada data ditemukan",
            "infoEmpty": "Tidak ada data"
        }
    });
});
</script>

<style>
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

@media print {
    .btn, .pagination, .alert, form, .card-header .btn, .card-footer {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    .table {
        font-size: 11px;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>