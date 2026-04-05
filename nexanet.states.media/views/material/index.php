<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
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
require_once '../../models/Material.php';

// Include header AFTER all processing
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

$material = new Material($db);

// Handle delete - MOVED BEFORE ANY OUTPUT
if(isset($_GET['delete'])) {
    $material->id = (int)$_GET['delete'];
    if($material->delete()) {
        $_SESSION['success'] = "Data material berhasil dihapus!";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Gagal menghapus data material!";
        header("Location: index.php");
        exit();
    }
}

// Handle stock update via AJAX
if(isset($_POST['action']) && $_POST['action'] == 'update_stock') {
    header('Content-Type: application/json');
    $material->id = (int)$_POST['id'];
    $jumlah = (int)$_POST['jumlah'];
    $type = $_POST['type'];
    
    if($material->updateStock($material->id, $jumlah, $type)) {
        echo json_encode(['success' => true, 'message' => 'Stok berhasil diupdate!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate stok atau stok tidak mencukupi!']);
    }
    exit();
}

// Get search parameter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get data
$stmt = $material->read($search, $limit, $offset);
$total = $material->getTotal($search);
$total_pages = ceil($total / $limit);

// Get total value
$total_value = 0;
$all_materials = $material->read('', 9999, 0);
while($row = $all_materials->fetch(PDO::FETCH_ASSOC)) {
    $total_value += $row['stok'] * $row['harga'];
}

// Get low stock items
$low_stock_items = [];
$all_materials_low = $material->read('', 9999, 0);
while($row = $all_materials_low->fetch(PDO::FETCH_ASSOC)) {
    if($row['stok'] < 10) {
        $low_stock_items[] = $row;
    }
}

// Calculate total stok
$total_stok = 0;
$all_materials_stok = $material->read('', 9999, 0);
while($row = $all_materials_stok->fetch(PDO::FETCH_ASSOC)) {
    $total_stok += $row['stok'];
}

// Now include header AFTER all processing
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-boxes"></i> Data Material
        </h1>
        <div>
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Material
            </a>
            <button class="btn btn-success" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <button class="btn btn-info" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
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
                                Total Material</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-boxes fa-2x text-gray-300"></i>
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
                                Total Stok</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_stok); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-cubes fa-2x text-gray-300"></i>
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
                                Nilai Material</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_value, 0, ',', '.'); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Stok Menipis</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count($low_stock_items); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <?php if(count($low_stock_items) > 0): ?>
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card border-left-warning shadow">
                <div class="card-header bg-warning text-white">
                    <i class="fas fa-exclamation-triangle"></i> Peringatan Stok Menipis
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>Nama Material</th><th>Stok Saat Ini</th><th>Status</th><th>Aksi</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($low_stock_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['nama_material']); ?></td>
                                    <td class="text-danger fw-bold"><?php echo number_format($item['stok']); ?></td>
                                    <td><span class="badge bg-danger">Segera Restock</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="updateStock(<?php echo $item['id']; ?>, '<?php echo addslashes($item['nama_material']); ?>', <?php echo $item['stok']; ?>)">
                                            <i class="fas fa-plus"></i> Tambah Stok
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list"></i> Daftar Material
            </h6>
        </div>
        <div class="card-body">
            <!-- Search Form -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <form method="GET" action="" class="d-flex">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Cari berdasarkan nama material..." 
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
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
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
                <table class="table table-bordered table-hover" id="materialTable">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="25%">Nama Material</th>
                            <th width="10%">Stok</th>
                            <th width="15%">Harga Satuan</th>
                            <th width="25%">Keterangan</th>
                            <th width="10%">Total Nilai</th>
                            <th width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = $offset + 1;
                        $grand_total = 0;
                        $has_data = false;
                        
                        // Reset statement for display
                        $display_stmt = $material->read($search, $limit, $offset);
                        
                        while($row = $display_stmt->fetch(PDO::FETCH_ASSOC)): 
                            $has_data = true;
                            $total_nilai = $row['stok'] * $row['harga'];
                            $grand_total += $total_nilai;
                            $stock_class = $row['stok'] < 10 ? 'text-danger fw-bold' : '';
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['nama_material']); ?>
                                <?php if($row['stok'] < 10): ?>
                                    <span class="badge bg-danger ms-2">Stok Menipis</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center <?php echo $stock_class; ?>">
                                <?php echo number_format($row['stok']); ?>
                                <?php if($row['stok'] < 10): ?>
                                    <i class="fas fa-exclamation-circle text-danger" title="Stok menipis!"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($row['keterangan'] ?: '-'); ?></td>
                            <td class="text-end">Rp <?php echo number_format($total_nilai, 0, ',', '.'); ?></td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-info" onclick="updateStock(<?php echo $row['id']; ?>, '<?php echo addslashes($row['nama_material']); ?>', <?php echo $row['stok']; ?>)">
                                        <i class="fas fa-exchange-alt"></i>
                                    </button>
                                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Yakin ingin menghapus material <?php echo htmlspecialchars($row['nama_material']); ?>?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php 
                        endwhile;
                        
                        if(!$has_data):
                        ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                <p>Tidak ada data material</p>
                                <a href="create.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Tambah Material Sekarang
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if($has_data): ?>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="5" class="text-end">Total Keseluruhan Nilai Material:</td>
                            <td class="text-end text-primary">
                                Rp <?php echo number_format($grand_total, 0, ',', '.'); ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
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

<!-- Modal Update Stock -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exchange-alt"></i> Update Stok Material
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="stockForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="id" id="material_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Material</label>
                        <input type="text" class="form-control" id="material_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Stok Saat Ini</label>
                        <input type="text" class="form-control" id="current_stock" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipe Transaksi</label>
                        <select class="form-select" id="stock_type" name="type" required>
                            <option value="in">Tambah Stok (Masuk)</option>
                            <option value="out">Kurangi Stok (Keluar)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Jumlah</label>
                        <input type="number" class="form-control" id="jumlah" name="jumlah" min="1" required>
                        <div class="form-text">Masukkan jumlah dalam satuan unit</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Update stock function
function updateStock(id, name, currentStock) {
    document.getElementById('material_id').value = id;
    document.getElementById('material_name').value = name;
    document.getElementById('current_stock').value = currentStock;
    document.getElementById('jumlah').value = '';
    document.getElementById('stock_type').value = 'in';
    
    var myModal = new bootstrap.Modal(document.getElementById('stockModal'));
    myModal.show();
}

// Handle stock form submission
document.getElementById('stockForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var formData = new FormData(this);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            var modal = bootstrap.Modal.getInstance(document.getElementById('stockModal'));
            modal.hide();
            showAlert('success', data.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        showAlert('danger', 'Terjadi kesalahan!');
        console.error('Error:', error);
    });
});

// Show alert function
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-1"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const cardBody = document.querySelector('.card-body');
    const existingAlert = cardBody.querySelector('.alert');
    if(existingAlert) existingAlert.remove();
    cardBody.insertAdjacentHTML('afterbegin', alertHtml);
    
    setTimeout(() => {
        const alert = cardBody.querySelector('.alert');
        if(alert) alert.remove();
    }, 3000);
}

// Export to Excel
function exportToExcel() {
    let table = document.getElementById('materialTable');
    let html = table.outerHTML;
    
    html = `
        <html>
        <head>
            <title>Data Material</title>
            <style>
                th { background-color: #4e73df; color: white; }
                td { border: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <h2>Data Material</h2>
            <p>Tanggal Export: ${new Date().toLocaleString()}</p>
            ${html}
        </body>
        </html>
    `;
    
    let blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    let link = document.createElement('a');
    let url = URL.createObjectURL(blob);
    
    link.href = url;
    link.download = 'data_material.xls';
    link.click();
    
    URL.revokeObjectURL(url);
}

// Refresh data
function refreshData() {
    location.reload();
}

// Auto-hide alerts
setTimeout(function() {
    let alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(() => {
            let bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 2000);
    });
}, 1000);
</script>

<style>
.border-left-primary { border-left: 4px solid #4e73df !important; }
.border-left-success { border-left: 4px solid #1cc88a !important; }
.border-left-warning { border-left: 4px solid #f6c23e !important; }
.border-left-danger { border-left: 4px solid #e74a3b !important; }
.text-gray-800 { color: #5a5c69 !important; }

.table-responsive {
    overflow-x: auto;
}

.table th, .table td {
    vertical-align: middle;
}

.btn-group .btn {
    margin: 0 2px;
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
        font-size: 12px;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>