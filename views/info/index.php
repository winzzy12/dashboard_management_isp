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
require_once '../../models/TransaksiPembayaran.php';
require_once '../../models/Billing.php';
require_once '../../models/Pelanggan.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize models
$transaksi = new TransaksiPembayaran($db);
$billing = new Billing($db);
$pelanggan = new Pelanggan($db);

// Get user role
$user_role = $_SESSION['role'];

$success_message = '';
$error_message = '';

// Handle confirm payment (verify payment)
if(isset($_GET['confirm']) && isset($_GET['id'])) {
    $transaksi_id = (int)$_GET['id'];
    
    // Get transaction data
    $transaksi_data = $transaksi->getOne($transaksi_id);
    
    if($transaksi_data) {
        // Update transaction status to success
        if($transaksi->updateStatus($transaksi_id, 'success')) {
            // Update billing status to paid
            if($transaksi_data['billing_id']) {
                $billing->markAsPaid($transaksi_data['billing_id'], date('Y-m-d'));
                
                // Also record to pemasukan
                $query = "INSERT INTO pemasukan (tanggal, pelanggan_id, jumlah, keterangan) 
                          VALUES (:tanggal, :pelanggan_id, :jumlah, :keterangan)";
                $stmt = $db->prepare($query);
                $keterangan = "Pembayaran tagihan via konfirmasi online - " . $transaksi_data['bank_name'] . " a/n " . $transaksi_data['bank_account_name'];
                $stmt->bindParam(':tanggal', date('Y-m-d'));
                $stmt->bindParam(':pelanggan_id', $transaksi_data['pelanggan_id']);
                $stmt->bindParam(':jumlah', $transaksi_data['jumlah']);
                $stmt->bindParam(':keterangan', $keterangan);
                $stmt->execute();
            }
            
            $success_message = "Pembayaran berhasil dikonfirmasi! Tagihan telah dilunasi.";
        } else {
            $error_message = "Gagal mengkonfirmasi pembayaran!";
        }
    } else {
        $error_message = "Data transaksi tidak ditemukan!";
    }
    header("Location: index.php");
    exit();
}

// Handle reject payment
if(isset($_GET['reject']) && isset($_GET['id'])) {
    $transaksi_id = (int)$_GET['id'];
    
    if($transaksi->updateStatus($transaksi_id, 'failed')) {
        $success_message = "Pembayaran ditolak!";
    } else {
        $error_message = "Gagal menolak pembayaran!";
    }
    header("Location: index.php");
    exit();
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get transactions with filters
$query = "SELECT t.*, p.nama as pelanggan_nama, p.id_pelanggan, p.no_hp, 
          b.bulan, b.tahun, b.jumlah as tagihan
          FROM transaksi_pembayaran t
          LEFT JOIN pelanggan p ON t.pelanggan_id = p.id
          LEFT JOIN billing b ON t.billing_id = b.id
          WHERE 1=1";

if(!empty($status)) {
    $query .= " AND t.status = :status";
}
if(!empty($search)) {
    $query .= " AND (p.nama LIKE :search OR p.id_pelanggan LIKE :search OR t.bank_account_name LIKE :search OR t.kode_transaksi LIKE :search)";
}
$query .= " ORDER BY t.created_at DESC LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);

if(!empty($status)) {
    $stmt->bindParam(':status', $status);
}
if(!empty($search)) {
    $searchTerm = "%{$search}%";
    $stmt->bindParam(':search', $searchTerm);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM transaksi_pembayaran t
                LEFT JOIN pelanggan p ON t.pelanggan_id = p.id
                WHERE 1=1";
if(!empty($status)) {
    $count_query .= " AND t.status = :status";
}
if(!empty($search)) {
    $count_query .= " AND (p.nama LIKE :search OR p.id_pelanggan LIKE :search OR t.bank_account_name LIKE :search OR t.kode_transaksi LIKE :search)";
}
$count_stmt = $db->prepare($count_query);
if(!empty($status)) {
    $count_stmt->bindParam(':status', $status);
}
if(!empty($search)) {
    $searchTerm = "%{$search}%";
    $count_stmt->bindParam(':search', $searchTerm);
}
$count_stmt->execute();
$total_row = $count_stmt->fetch(PDO::FETCH_ASSOC);
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Get statistics
$stats_query = "SELECT 
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                    COUNT(CASE WHEN status = 'success' THEN 1 END) as success,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                    SUM(CASE WHEN status = 'pending' THEN jumlah ELSE 0 END) as total_pending,
                    SUM(CASE WHEN status = 'success' THEN jumlah ELSE 0 END) as total_success
                FROM transaksi_pembayaran
                WHERE DATE(created_at) = CURDATE()";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-credit-card"></i> Info Pembayaran Masuk
        </h1>
        <div>
            <button class="btn btn-primary" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Menunggu Konfirmasi</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['pending'] ?? 0); ?>
                            </div>
                            <div class="small text-muted">
                                Total: Rp <?php echo number_format($stats['total_pending'] ?? 0, 0, ',', '.'); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                Berhasil Dikonfirmasi</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['success'] ?? 0); ?>
                            </div>
                            <div class="small text-muted">
                                Total: Rp <?php echo number_format($stats['total_success'] ?? 0, 0, ',', '.'); ?>
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
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Ditolak / Gagal</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['failed'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
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
                                Total Transaksi</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
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

    <!-- Filter Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-filter"></i> Filter Data
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Menunggu Konfirmasi</option>
                        <option value="success" <?php echo $status == 'success' ? 'selected' : ''; ?>>Berhasil</option>
                        <option value="failed" <?php echo $status == 'failed' ? 'selected' : ''; ?>>Ditolak/Gagal</option>
                        <option value="">Semua Status</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Cari</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Cari berdasarkan nama pelanggan, ID, atau bank pengirim"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Tampilkan
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list"></i> Daftar Konfirmasi Pembayaran
                <?php if($status == 'pending'): ?>
                    <span class="badge bg-warning ms-2">Menunggu Konfirmasi</span>
                <?php elseif($status == 'success'): ?>
                    <span class="badge bg-success ms-2">Berhasil</span>
                <?php elseif($status == 'failed'): ?>
                    <span class="badge bg-danger ms-2">Ditolak</span>
                <?php endif; ?>
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="10%">Kode Transaksi</th>
                            <th width="12%">Tanggal</th>
                            <th width="12%">Pelanggan</th>
                            <th width="10%">Periode</th>
                            <th width="10%">Jumlah</th>
                            <th width="15%">Info Transfer</th>
                            <th width="10%">Status</th>
                            <th width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = $offset + 1; foreach($transactions as $trx): 
                            $status_class = [
                                'pending' => 'warning',
                                'success' => 'success',
                                'failed' => 'danger'
                            ];
                            $status_text = [
                                'pending' => 'Menunggu Konfirmasi',
                                'success' => 'Berhasil',
                                'failed' => 'Ditolak'
                            ];
                        ?>
                        <tr class="<?php echo $trx['status'] == 'pending' ? 'table-warning' : ($trx['status'] == 'success' ? 'table-success' : ''); ?>">
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo $trx['kode_transaksi']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($trx['created_at'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($trx['pelanggan_nama']); ?></strong><br>
                                <small class="text-muted">ID: <?php echo $trx['id_pelanggan']; ?></small>
                            </td>
                            <td><?php echo $trx['bulan']; ?>/<?php echo $trx['tahun']; ?></td>
                            <td class="text-end fw-bold">
                                Rp <?php echo number_format($trx['jumlah'], 0, ',', '.'); ?>
                            </td>
                            <td>
                                <strong>Bank Tujuan:</strong> <?php echo htmlspecialchars($trx['bank_name']); ?><br>
                                <strong>Bank Pengirim:</strong> <?php echo htmlspecialchars($trx['bank_account_name']); ?><br>
                                <strong>No Rekening:</strong> <?php echo htmlspecialchars($trx['bank_account']); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $status_class[$trx['status']]; ?>">
                                    <?php echo $status_text[$trx['status']]; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if($trx['status'] == 'pending'): ?>
                                    <div class="btn-group" role="group">
                                        <a href="?confirm=1&id=<?php echo $trx['id']; ?>" 
                                           class="btn btn-sm btn-success"
                                           onclick="return confirm('Konfirmasi pembayaran ini? Tagihan akan otomatis dilunasi.')">
                                            <i class="fas fa-check-circle"></i> Konfirmasi
                                        </a>
                                        <a href="?reject=1&id=<?php echo $trx['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Tolak pembayaran ini? Pastikan data tidak valid.')">
                                            <i class="fas fa-times-circle"></i> Tolak
                                        </a>
                                    </div>
                                <?php elseif($trx['status'] == 'success'): ?>
                                    <a href="../billing/index.php" class="btn btn-sm btn-info">
                                        <i class="fas fa-file-invoice"></i> Lihat Tagihan
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($transactions)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                <p>Tidak ada data konfirmasi pembayaran</p>
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
                                <a class="page-link" href="?page=1&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
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
.border-left-warning { border-left: 4px solid #f6c23e !important; }
.border-left-success { border-left: 4px solid #1cc88a !important; }
.border-left-danger { border-left: 4px solid #e74a3b !important; }
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
</style>

<?php require_once '../../includes/footer.php'; ?>