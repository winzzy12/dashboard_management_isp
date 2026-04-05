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
require_once '../../models/RekeningBank.php';
require_once '../../models/Qris.php';
require_once '../../models/PaymentGateway.php';
require_once '../../models/TransaksiPembayaran.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize models
$rekeningBank = new RekeningBank($db);
$qris = new Qris($db);
$paymentGateway = new PaymentGateway($db);
$transaksi = new TransaksiPembayaran($db);

$success_message = '';
$error_message = '';

// Get user role
$user_role = $_SESSION['role'] ?? 'viewer';

// Get all data with error handling
try {
    $rekening_list = $rekeningBank->getAll();
} catch(Exception $e) {
    $rekening_list = [];
}

try {
    $qris_list = $qris->getAll();
} catch(Exception $e) {
    $qris_list = [];
}

try {
    $gateway_list = $paymentGateway->getAll();
} catch(Exception $e) {
    $gateway_list = [];
}

try {
    $transaksi_list = $transaksi->getRecent(20);
} catch(Exception $e) {
    $transaksi_list = [];
}

// Get statistics
$total_rekening = count($rekening_list);
$total_qris = count($qris_list);
$total_gateway = count($gateway_list);

try {
    $total_transaksi_hari_ini = $transaksi->getTotalToday();
} catch(Exception $e) {
    $total_transaksi_hari_ini = 0;
}

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-credit-card"></i> Management Pembayaran
        </h1>
        <div>
            <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus"></i> Tambah
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="rekening.php">
                        <i class="fas fa-university"></i> Tambah Rekening
                    </a></li>
                    <li><a class="dropdown-item" href="qris.php">
                        <i class="fas fa-qrcode"></i> Tambah QRIS
                    </a></li>
                    <li><a class="dropdown-item" href="gateway.php">
                        <i class="fas fa-globe"></i> Tambah Payment Gateway
                    </a></li>
                </ul>
            </div>
            <?php endif; ?>
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
                                Total Rekening</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $total_rekening; ?>
                            </div>
                            <div class="small text-muted">
                                Rekening bank terdaftar
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-university fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="rekening.php" class="text-primary text-decoration-none">
                        Kelola Rekening <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total QRIS</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $total_qris; ?>
                            </div>
                            <div class="small text-muted">
                                QRIS terdaftar
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-qrcode fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="qris.php" class="text-success text-decoration-none">
                        Kelola QRIS <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Payment Gateway</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $total_gateway; ?>
                            </div>
                            <div class="small text-muted">
                                Gateway terdaftar
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-globe fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="gateway.php" class="text-info text-decoration-none">
                        Kelola Gateway <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Transaksi Hari Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $total_transaksi_hari_ini; ?>
                            </div>
                            <div class="small text-muted">
                                Total transaksi hari ini
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exchange-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rekening Bank Section -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-university"></i> Rekening Bank
            </h6>
            <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
            <a href="rekening.php" class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Tambah Rekening
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if(empty($rekening_list)): ?>
                <div class="alert alert-info">Belum ada data rekening bank. <a href="rekening.php">Tambah rekening</a></div>
            <?php else: ?>
                <div class="row">
                    <?php foreach($rekening_list as $rek): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 border-left-<?php echo $rek['is_default'] ? 'success' : 'secondary'; ?> shadow">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title">
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($rek['nama_bank']); ?>
                                        </h5>
                                        <h6 class="card-subtitle mb-2 text-muted">
                                            <?php echo htmlspecialchars($rek['kode_bank']); ?>
                                        </h6>
                                    </div>
                                    <div>
                                        <?php if($rek['is_default']): ?>
                                            <span class="badge bg-success">Default</span>
                                        <?php endif; ?>
                                        <?php if(!$rek['is_active']): ?>
                                            <span class="badge bg-secondary">Nonaktif</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <hr>
                                <p><strong>Nomor Rekening:</strong><br>
                                <code class="fs-4"><?php echo chunk_split($rek['nomor_rekening'], 4, ' '); ?></code></p>
                                <p><strong>Atas Nama:</strong><br>
                                <?php echo htmlspecialchars($rek['nama_pemilik']); ?></p>
                                <?php if($rek['cabang']): ?>
                                <p><strong>Cabang:</strong><br>
                                <?php echo htmlspecialchars($rek['cabang']); ?></p>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-outline-primary copy-btn" 
                                            data-copy="<?php echo $rek['nomor_rekening']; ?>">
                                        <i class="fas fa-copy"></i> Salin No Rekening
                                    </button>
                                    <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
                                    <a href="rekening.php?edit=1&id=<?php echo $rek['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-end mt-2">
                    <a href="rekening.php" class="btn btn-sm btn-primary">Lihat Semua Rekening <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- QRIS Section -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-qrcode"></i> QRIS (Quick Response Code Indonesian Standard)
            </h6>
            <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
            <a href="qris.php" class="btn btn-sm btn-success">
                <i class="fas fa-plus"></i> Tambah QRIS
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if(empty($qris_list)): ?>
                <div class="alert alert-info">Belum ada data QRIS. <a href="qris.php">Tambah QRIS</a></div>
            <?php else: ?>
                <div class="row">
                    <?php foreach($qris_list as $qr): ?>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100 text-center border-left-<?php echo $qr['is_default'] ? 'success' : 'secondary'; ?> shadow">
                            <div class="card-body">
                                <div class="mb-3">
                                    <?php if($qr['qris_image']): ?>
                                    <img src="<?php echo $qr['qris_image']; ?>" alt="QRIS" class="img-fluid" style="max-height: 150px;">
                                    <?php else: ?>
                                    <div class="bg-light p-3 rounded">
                                        <i class="fas fa-qrcode fa-4x text-primary"></i>
                                        <p class="mt-2 mb-0">QRIS <?php echo htmlspecialchars($qr['provider']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <h6><?php echo htmlspecialchars($qr['nama']); ?></h6>
                                <p class="text-muted small">Provider: <?php echo htmlspecialchars($qr['provider']); ?></p>
                                <?php if($qr['nominal_min'] > 0 || $qr['nominal_max'] > 0): ?>
                                <p class="small">
                                    Min: Rp <?php echo number_format($qr['nominal_min'], 0, ',', '.'); ?><br>
                                    Max: Rp <?php echo number_format($qr['nominal_max'], 0, ',', '.'); ?>
                                </p>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <?php if($qr['is_default']): ?>
                                        <span class="badge bg-success">Default</span>
                                    <?php endif; ?>
                                    <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
                                    <a href="qris.php?edit=1&id=<?php echo $qr['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-end mt-2">
                    <a href="qris.php" class="btn btn-sm btn-primary">Lihat Semua QRIS <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Gateway Section -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-globe"></i> Payment Gateway
            </h6>
            <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
            <a href="gateway.php" class="btn btn-sm btn-info">
                <i class="fas fa-plus"></i> Tambah Gateway
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if(empty($gateway_list)): ?>
                <div class="alert alert-info">Belum ada data payment gateway. <a href="gateway.php">Tambah Gateway</a></div>
            <?php else: ?>
                <div class="row">
                    <?php foreach($gateway_list as $gw): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 border-left-<?php echo $gw['is_default'] ? 'success' : 'secondary'; ?> shadow">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h5 class="card-title">
                                        <i class="fas fa-credit-card"></i> <?php echo htmlspecialchars($gw['nama_gateway']); ?>
                                    </h5>
                                    <?php if($gw['is_default']): ?>
                                        <span class="badge bg-success">Default</span>
                                    <?php endif; ?>
                                </div>
                                <p class="card-text">
                                    <small class="text-muted">Kode: <?php echo strtoupper($gw['kode_gateway']); ?></small>
                                </p>
                                <hr>
                                <p><strong>Environment:</strong> 
                                    <span class="badge bg-<?php echo $gw['environment'] == 'production' ? 'danger' : 'warning'; ?>">
                                        <?php echo ucfirst($gw['environment']); ?>
                                    </span>
                                </p>
                                <p><strong>Minimal Transaksi:</strong><br>
                                Rp <?php echo number_format($gw['minimal_transaksi'], 0, ',', '.'); ?></p>
                                <p><strong>Biaya:</strong><br>
                                <?php if($gw['fee_percent'] > 0): ?>
                                    <?php echo $gw['fee_percent']; ?>% 
                                <?php endif; ?>
                                <?php if($gw['fee_fixed'] > 0): ?>
                                    + Rp <?php echo number_format($gw['fee_fixed'], 0, ',', '.'); ?>
                                <?php endif; ?>
                                </p>
                                <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
                                <div class="mt-2">
                                    <a href="gateway.php?edit=1&id=<?php echo $gw['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-end mt-2">
                    <a href="gateway.php" class="btn btn-sm btn-primary">Lihat Semua Gateway <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-history"></i> Transaksi Terbaru
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Transaksi</th>
                            <th>Jumlah</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </thead>
                    <tbody>
                        <?php if(empty($transaksi_list)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    <p>Belum ada transaksi</p>
                                 </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($transaksi_list as $trx): ?>
                            <tr>
                                <td><?php echo $trx['kode_transaksi']; ?></td>
                                <td class="text-end fw-bold">Rp <?php echo number_format($trx['jumlah'], 0, ',', '.'); ?></td>
                                <td>
                                    <?php
                                    $method_icon = [
                                        'bank_transfer' => 'university',
                                        'qris' => 'qrcode',
                                        'payment_gateway' => 'globe'
                                    ];
                                    $method_text = [
                                        'bank_transfer' => 'Transfer Bank',
                                        'qris' => 'QRIS',
                                        'payment_gateway' => 'Payment Gateway'
                                    ];
                                    $icon = $method_icon[$trx['metode_pembayaran']] ?? 'credit-card';
                                    $text = $method_text[$trx['metode_pembayaran']] ?? $trx['metode_pembayaran'];
                                    ?>
                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                    <?php echo $text; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = [
                                        'pending' => 'warning',
                                        'success' => 'success',
                                        'failed' => 'danger',
                                        'expired' => 'secondary'
                                    ];
                                    $class = $status_class[$trx['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $class; ?>">
                                        <?php echo ucfirst($trx['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($trx['created_at'])); ?></td>
                                <td>
                                    <a href="transaksi-detail.php?id=<?php echo $trx['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Copy to clipboard
document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const text = this.dataset.copy;
        navigator.clipboard.writeText(text);
        
        const originalText = this.innerHTML;
        this.innerHTML = '<i class="fas fa-check"></i> Tersalin!';
        setTimeout(() => {
            this.innerHTML = originalText;
        }, 2000);
    });
});

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
.border-left-info { border-left: 4px solid #36b9cc !important; }
.border-left-warning { border-left: 4px solid #f6c23e !important; }

.card {
    transition: transform 0.2s;
}
.card:hover {
    transform: translateY(-5px);
}

code {
    font-size: 1.1rem;
    background: #f8f9fc;
    padding: 5px 10px;
    border-radius: 5px;
}

.copy-btn {
    transition: all 0.2s;
}
.copy-btn:hover {
    transform: scale(1.02);
}

.card-footer {
    padding: 0.75rem 1.25rem;
    background-color: #f8f9fc;
    border-top: 1px solid #e3e6f0;
}

.btn-group .btn {
    margin: 0 2px;
}
</style>

<?php require_once '../../includes/footer.php'; ?>