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
require_once '../../models/Pemasukan.php';
require_once '../../models/Pelanggan.php';

// Handle delete - MOVED BEFORE ANY OUTPUT
if(isset($_GET['delete'])) {
    $database = new Database();
    $db = $database->getConnection();
    $pemasukan = new Pemasukan($db);
    $pemasukan->id = (int)$_GET['delete'];
    
    if($pemasukan->delete()) {
        $_SESSION['success'] = "Data pemasukan berhasil dihapus!";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Gagal menghapus data pemasukan!";
        header("Location: index.php");
        exit();
    }
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize models
$pemasukan = new Pemasukan($db);
$pelanggan = new Pelanggan($db);

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get data
$stmt = $pemasukan->read($start_date, $end_date, $limit, $offset);
$total = $pemasukan->getTotal($start_date, $end_date);
$total_pages = ceil($total / $limit);
$total_amount = $pemasukan->getTotalAmount($start_date, $end_date);

// Get list of customers for dropdown - FIXED: Check if method exists
$pelanggan_list = [];
if(method_exists($pelanggan, 'getAll')) {
    $pelanggan_list = $pelanggan->getAll();
} else {
    // Alternative query if method doesn't exist
    $query = "SELECT id, id_pelanggan, nama, harga_paket FROM pelanggan WHERE status = 'aktif' ORDER BY nama ASC";
    $stmt_pel = $db->prepare($query);
    $stmt_pel->execute();
    $pelanggan_list = $stmt_pel->fetchAll(PDO::FETCH_ASSOC);
}

// Get statistics for current month
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$monthly_total = $pemasukan->getTotalAmount($current_month_start, $current_month_end);
$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));
$last_month_total = $pemasukan->getTotalAmount($last_month_start, $last_month_end);

// Calculate percentage change
$percentage_change = 0;
if($last_month_total > 0) {
    $percentage_change = (($monthly_total - $last_month_total) / $last_month_total) * 100;
}

// Get daily average
$days_in_month = date('t');
$daily_average = $days_in_month > 0 ? $monthly_total / $days_in_month : 0;

// Now include header AFTER all processing
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-money-bill-wave"></i> Data Pemasukan
        </h1>
        <div>
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Pemasukan
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
                                Total Pemasukan (Periode)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_amount, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo date('d M Y', strtotime($start_date)); ?> - 
                                <?php echo date('d M Y', strtotime($end_date)); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
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
                                Pemasukan Bulan Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($monthly_total, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo date('F Y'); ?>
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
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Rata-rata Harian</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($daily_average, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                Per hari (bulan ini)
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-bar fa-2x text-gray-300"></i>
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
                                Perubahan Bulan Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php if($percentage_change >= 0): ?>
                                    <span class="text-success">
                                        <i class="fas fa-arrow-up"></i> +<?php echo number_format($percentage_change, 1); ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="text-danger">
                                        <i class="fas fa-arrow-down"></i> <?php echo number_format($percentage_change, 1); ?>%
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted">
                                Dibanding bulan lalu
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-percent fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-filter"></i> Filter Data
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tanggal Awal</label>
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" name="end_date" class="form-control" 
                           value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Tampilkan
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Reset
                        </a>
                        <button type="button" class="btn btn-info" onclick="setThisMonth()">
                            <i class="fas fa-calendar-alt"></i> Bulan Ini
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list"></i> Daftar Pemasukan
            </h6>
        </div>
        <div class="card-body">
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
                <table class="table table-bordered table-hover" id="pemasukanTable">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="15%">Tanggal</th>
                            <th width="20%">Pelanggan</th>
                            <th width="15%">Jumlah</th>
                            <th width="30%">Keterangan</th>
                            <th width="15%">Aksi</th>
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
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['nama_pelanggan'] ?: '-'); ?>
                                <?php if($row['nama_pelanggan']): ?>
                                    <br><small class="text-muted">(ID: <?php echo $row['pelanggan_id']; ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold text-success">
                                Rp <?php echo number_format($row['jumlah'], 0, ',', '.'); ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['keterangan'] ?: '-'); ?></td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Yakin ingin menghapus data pemasukan ini?')">
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
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                <p>Tidak ada data pemasukan untuk periode yang dipilih</p>
                                <a href="create.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Tambah Pemasukan
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if($has_data): ?>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="3" class="text-end">Total Pemasukan:</td>
                            <td class="text-end text-success">
                                Rp <?php echo number_format($total_amount, 0, ',', '.'); ?>
                            </td>
                            <td colspan="2"></td>
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
                                <a class="page-link" href="?page=1&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">
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

<script>
// Set date filter to current month
function setThisMonth() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    document.querySelector('input[name="start_date"]').value = formatDate(firstDay);
    document.querySelector('input[name="end_date"]').value = formatDate(lastDay);
    document.querySelector('form').submit();
}

// Format date to YYYY-MM-DD
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Export to Excel
function exportToExcel() {
    let table = document.getElementById('pemasukanTable');
    let html = table.outerHTML;
    
    const startDate = document.querySelector('input[name="start_date"]').value;
    const endDate = document.querySelector('input[name="end_date"]').value;
    
    html = `
        <html>
        <head>
            <title>Data Pemasukan</title>
            <style>
                th { background-color: #4e73df; color: white; }
                td { border: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <h2>Data Pemasukan</h2>
            <p>Periode: ${startDate} s/d ${endDate}</p>
            <p>Tanggal Export: ${new Date().toLocaleString()}</p>
            ${html}
        </body>
        </html>
    `;
    
    let blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    let link = document.createElement('a');
    let url = URL.createObjectURL(blob);
    
    link.href = url;
    link.download = 'data_pemasukan.xls';
    link.click();
    
    URL.revokeObjectURL(url);
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