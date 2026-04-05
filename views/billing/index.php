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
require_once '../../models/Billing.php';
require_once '../../models/Pelanggan.php';
require_once '../../models/RekeningBank.php';
require_once '../../models/TransaksiPembayaran.php';
require_once '../../models/Konfigurasi.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize models
$billing = new Billing($db);
$pelanggan = new Pelanggan($db);
$rekeningBank = new RekeningBank($db);
$transaksi = new TransaksiPembayaran($db);
$konfigurasi = new Konfigurasi($db);

// Get user role
$user_role = $_SESSION['role'];

// Get domain settings
$app_url = $konfigurasi->get('app_url');
$payment_url = $konfigurasi->get('payment_url');

// Set default if empty
if(empty($app_url)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $app_url = $protocol . $host;
}
if(empty($payment_url)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $payment_url = $protocol . $host . "/views/payment/invoice.php";
}

// Get active rekening banks for display
$active_rekening = $rekeningBank->getActive();

// Handle update domain
if(isset($_POST['action']) && $_POST['action'] == 'update_domain') {
    $new_app_url = rtrim($_POST['app_url'], '/');
    $new_payment_url = rtrim($_POST['payment_url'], '/');
    
    if($konfigurasi->set('app_url', $new_app_url)) {
        if($konfigurasi->set('payment_url', $new_payment_url)) {
            $_SESSION['success'] = "Domain berhasil diupdate!";
            // Refresh domain settings
            $app_url = $konfigurasi->get('app_url');
            $payment_url = $konfigurasi->get('payment_url');
        } else {
            $_SESSION['error'] = "Gagal mengupdate payment URL!";
        }
    } else {
        $_SESSION['error'] = "Gagal mengupdate domain!";
    }
    header("Location: index.php");
    exit();
}

// Handle mark as paid
if(isset($_GET['bayar']) && isset($_GET['id'])) {
    $billing_id = (int)$_GET['id'];
    $tanggal_bayar = date('Y-m-d');
    
    if($billing->markAsPaid($billing_id, $tanggal_bayar)) {
        // Also record to pemasukan
        $billing_data = $billing->getOne($billing_id);
        if($billing_data) {
            $query = "INSERT INTO pemasukan (tanggal, pelanggan_id, jumlah, keterangan) 
                      VALUES (:tanggal, :pelanggan_id, :jumlah, :keterangan)";
            $stmt = $db->prepare($query);
            $keterangan = "Pembayaran tagihan bulan {$billing_data['bulan']}/{$billing_data['tahun']}";
            $stmt->bindParam(':tanggal', $tanggal_bayar);
            $stmt->bindParam(':pelanggan_id', $billing_data['pelanggan_id']);
            $stmt->bindParam(':jumlah', $billing_data['jumlah']);
            $stmt->bindParam(':keterangan', $keterangan);
            $stmt->execute();
        }
        
        $_SESSION['success'] = "Pembayaran tagihan berhasil dicatat!";
    } else {
        $_SESSION['error'] = "Gagal mencatat pembayaran!";
    }
    header("Location: index.php");
    exit();
}

// Handle generate payment link
if(isset($_GET['generate_link']) && isset($_GET['id'])) {
    $billing_id = (int)$_GET['id'];
    
    // Get billing data
    $billing_data = $billing->getOne($billing_id);
    
    if($billing_data && $billing_data['status'] == 'belum_lunas') {
        // Generate unique payment token
        $payment_token = md5($billing_data['id'] . $billing_data['pelanggan_id'] . date('YmdHis') . rand(1000, 9999));
        
        // Update billing with payment token
        if($billing->updatePaymentToken($billing_id, $payment_token)) {
            // Build payment link using custom domain
            $payment_link = rtrim($payment_url, '/') . "?token=" . $payment_token;
            
            // Store in session for modal
            $_SESSION['payment_link'] = $payment_link;
            $_SESSION['show_payment_modal'] = true;
            $_SESSION['success'] = "Link pembayaran berhasil digenerate!";
        } else {
            $_SESSION['error'] = "Gagal generate link pembayaran!";
        }
    } else {
        $_SESSION['error'] = "Tagihan sudah lunas atau tidak ditemukan!";
    }
    header("Location: index.php");
    exit();
}

// Handle delete billing
if(isset($_GET['delete']) && isset($_GET['id'])) {
    $billing_id = (int)$_GET['id'];
    if($billing->delete($billing_id)) {
        $_SESSION['success'] = "Tagihan berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus tagihan!";
    }
    header("Location: index.php");
    exit();
}

// Handle generate billing
if(isset($_POST['action']) && $_POST['action'] == 'generate') {
    $bulan = $_POST['bulan'];
    $tahun = $_POST['tahun'];
    
    $result = $billing->generateBilling($bulan, $tahun);
    
    if($result['success']) {
        $_SESSION['success'] = $result['message'];
    } else {
        $_SESSION['error'] = $result['message'];
    }
    header("Location: index.php?bulan={$bulan}&tahun={$tahun}");
    exit();
}

// Get filter parameters
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$status = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get data
$stmt = $billing->read($bulan, $tahun, $status, $limit, $offset);
$total = $billing->getTotal($bulan, $tahun, $status);
$total_pages = ceil($total / $limit);

// Get statistics
$total_belum_lunas = $billing->getTotalBelumLunasByPeriod($bulan, $tahun);
$total_lunas = $billing->getTotalLunasByPeriod($bulan, $tahun);
$total_tagihan = $total_belum_lunas + $total_lunas;

// Get overdue bills
$overdue_bills = $billing->getTagihanTerlambat();

// Check if need to show payment modal
$show_payment_modal = isset($_SESSION['show_payment_modal']) ? $_SESSION['show_payment_modal'] : false;
$payment_link = isset($_SESSION['payment_link']) ? $_SESSION['payment_link'] : '';
if($show_payment_modal) {
    unset($_SESSION['show_payment_modal']);
    unset($_SESSION['payment_link']);
}

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-file-invoice"></i> Billing / Tagihan
        </h1>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateModal">
                <i class="fas fa-plus-circle"></i> Generate Tagihan
            </button>
            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#domainModal">
                <i class="fas fa-globe"></i> Edit Domain
            </button>
            <button class="btn btn-success" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <button class="btn btn-info" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Overdue Alert -->
    <?php if(count($overdue_bills) > 0): ?>
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card border-left-danger shadow">
                <div class="card-header bg-danger text-white">
                    <i class="fas fa-exclamation-triangle"></i> Peringatan Tagihan Terlambat
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Pelanggan</th>
                                    <th>Periode</th>
                                    <th>Jumlah</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Keterlambatan</th>
                                    <th>Aksi</th>
                                </thead>
                            <tbody>
                                <?php foreach($overdue_bills as $overdue): 
                                    $tgl_jatuh = new DateTime($overdue['tanggal_jatuh_tempo']);
                                    $tgl_sekarang = new DateTime();
                                    $selisih = $tgl_jatuh->diff($tgl_sekarang);
                                    $hari_terlambat = $selisih->days;
                                ?>
                                <tr class="table-danger">
                                    <td><?php echo htmlspecialchars($overdue['nama_pelanggan']); ?></td>
                                    <td class="text-center"><?php echo $overdue['bulan']; ?>/<?php echo $overdue['tahun']; ?></td>
                                    <td class="text-danger fw-bold">Rp <?php echo number_format($overdue['jumlah'], 0, ',', '.'); ?></td>
                                    <td class="text-center"><?php echo date('d/m/Y', strtotime($overdue['tanggal_jatuh_tempo'])); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">
                                            Terlambat <?php echo $hari_terlambat; ?> hari
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="?bayar=1&id=<?php echo $overdue['id']; ?>" 
                                           class="btn btn-sm btn-success"
                                           onclick="return confirm('Bayar tagihan ini?')">
                                            <i class="fas fa-money-bill-wave"></i> Bayar
                                        </a>
                                        <a href="?generate_link=1&id=<?php echo $overdue['id']; ?>" 
                                           class="btn btn-sm btn-info"
                                           onclick="return confirm('Generate link pembayaran untuk tagihan ini?')">
                                            <i class="fas fa-link"></i> Link Bayar
                                        </a>
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

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Tagihan
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_tagihan, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                Periode: <?php echo $bulan; ?>/<?php echo $tahun; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice fa-2x text-gray-300"></i>
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
                                Tagihan Lunas
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_lunas, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                <?php 
                                $persen_lunas = $total_tagihan > 0 ? ($total_lunas / $total_tagihan) * 100 : 0;
                                echo number_format($persen_lunas, 1); ?>% dari total
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
                                Tagihan Belum Lunas
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_belum_lunas, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                <?php 
                                $persen_belum = $total_tagihan > 0 ? ($total_belum_lunas / $total_tagihan) * 100 : 0;
                                echo number_format($persen_belum, 1); ?>% dari total
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hourglass-half fa-2x text-gray-300"></i>
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
                                Tagihan Terlambat
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count($overdue_bills); ?> Tagihan
                            </div>
                            <div class="small text-muted">
                                Perlu segera ditagih
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rekening Bank Info -->
    <?php if(!empty($active_rekening)): ?>
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card border-left-info shadow">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-university"></i> Informasi Rekening Pembayaran
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach($active_rekening as $rek): ?>
                        <div class="col-md-4 mb-2">
                            <div class="card">
                                <div class="card-body p-3">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($rek['nama_bank']); ?></h6>
                                    <p class="mb-0 small">
                                        <strong>No. Rekening:</strong> 
                                        <code><?php echo chunk_split($rek['nomor_rekening'], 4, ' '); ?></code>
                                        <button class="btn btn-sm btn-link p-0 ms-1 copy-btn" data-copy="<?php echo $rek['nomor_rekening']; ?>">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </p>
                                    <p class="mb-0 small"><strong>Atas Nama:</strong> <?php echo htmlspecialchars($rek['nama_pemilik']); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
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
                    <label class="form-label">Bulan</label>
                    <select name="bulan" class="form-select">
                        <option value="1" <?php echo $bulan == '1' ? 'selected' : ''; ?>>Januari</option>
                        <option value="2" <?php echo $bulan == '2' ? 'selected' : ''; ?>>Februari</option>
                        <option value="3" <?php echo $bulan == '3' ? 'selected' : ''; ?>>Maret</option>
                        <option value="4" <?php echo $bulan == '4' ? 'selected' : ''; ?>>April</option>
                        <option value="5" <?php echo $bulan == '5' ? 'selected' : ''; ?>>Mei</option>
                        <option value="6" <?php echo $bulan == '6' ? 'selected' : ''; ?>>Juni</option>
                        <option value="7" <?php echo $bulan == '7' ? 'selected' : ''; ?>>Juli</option>
                        <option value="8" <?php echo $bulan == '8' ? 'selected' : ''; ?>>Agustus</option>
                        <option value="9" <?php echo $bulan == '9' ? 'selected' : ''; ?>>September</option>
                        <option value="10" <?php echo $bulan == '10' ? 'selected' : ''; ?>>Oktober</option>
                        <option value="11" <?php echo $bulan == '11' ? 'selected' : ''; ?>>November</option>
                        <option value="12" <?php echo $bulan == '12' ? 'selected' : ''; ?>>Desember</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tahun</label>
                    <select name="tahun" class="form-select">
                        <?php 
                        $current_year = date('Y');
                        for($y = $current_year - 2; $y <= $current_year + 1; $y++):
                        ?>
                        <option value="<?php echo $y; ?>" <?php echo $tahun == $y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        <option value="lunas" <?php echo $status == 'lunas' ? 'selected' : ''; ?>>Lunas</option>
                        <option value="belum_lunas" <?php echo $status == 'belum_lunas' ? 'selected' : ''; ?>>Belum Lunas</option>
                    </select>
                </div>
                <div class="col-md-5 d-flex align-items-end">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Tampilkan
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Reset
                        </a>
                        <a href="?bulan=<?php echo date('m'); ?>&tahun=<?php echo date('Y'); ?>" class="btn btn-info">
                            <i class="fas fa-calendar-alt"></i> Bulan Ini
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list"></i> Daftar Tagihan
                <span class="badge bg-primary ms-2">Periode: <?php echo $bulan; ?>/<?php echo $tahun; ?></span>
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
                <table class="table table-bordered table-hover" id="billingTable">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="10%">ID Pelanggan</th>
                            <th width="15%">Nama Pelanggan</th>
                            <th width="8%">Periode</th>
                            <th width="12%">Jumlah Tagihan</th>
                            <th width="10%">Status</th>
                            <th width="12%">Jatuh Tempo</th>
                            <th width="20%">Link Pembayaran</th>
                            <th width="8%">Aksi</th>
                        </thead>
                    <tbody>
                        <?php 
                        $no = $offset + 1;
                        $has_data = false;
                        
                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                            $has_data = true;
                            $is_late = ($row['status'] == 'belum_lunas' && strtotime($row['tanggal_jatuh_tempo']) < time());
                        ?>
                        <tr class="<?php echo $is_late ? 'table-danger' : ''; ?>">
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['id_pelanggan'] ?? '-'); ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['nama_pelanggan'] ?? '-'); ?>
                                <?php if($is_late): ?>
                                    <br><small class="text-danger">⚠ Terlambat</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo $row['bulan']; ?>/<?php echo $row['tahun']; ?></td>
                            <td class="text-end fw-bold">
                                Rp <?php echo number_format($row['jumlah'], 0, ',', '.'); ?>
                            </td>
                            <td class="text-center">
                                <?php if($row['status'] == 'lunas'): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle"></i> Lunas
                                    </span>
                                    <?php if($row['tanggal_bayar']): ?>
                                        <br><small class="text-muted"><?php echo date('d/m/Y', strtotime($row['tanggal_bayar'])); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-clock"></i> Belum Lunas
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($row['tanggal_jatuh_tempo']): ?>
                                    <?php echo date('d/m/Y', strtotime($row['tanggal_jatuh_tempo'])); ?>
                                    <?php if($is_late): ?>
                                        <br><small class="text-danger">Terlambat</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($row['status'] == 'belum_lunas'): ?>
                                    <?php if(empty($row['payment_token'])): ?>
                                        <a href="?generate_link=1&id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-info"
                                           onclick="return confirm('Generate link pembayaran untuk tagihan ini?')">
                                            <i class="fas fa-link"></i> Generate Link
                                        </a>
                                    <?php else: 
                                        $full_link = rtrim($payment_url, '/') . "?token=" . $row['payment_token'];
                                    ?>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control form-control-sm" 
                                                   value="<?php echo $full_link; ?>" 
                                                   id="link_<?php echo $row['id']; ?>" readonly 
                                                   style="min-width: 250px; font-size: 11px;">
                                            <button class="btn btn-sm btn-primary" onclick="copyLink('link_<?php echo $row['id']; ?>')">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                            <a href="<?php echo $full_link; ?>" class="btn btn-sm btn-success" target="_blank">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <?php if($row['status'] == 'belum_lunas'): ?>
                                        <a href="?bayar=1&id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-success"
                                           title="Catat Pembayaran"
                                           onclick="return confirm('Konfirmasi pembayaran tagihan <?php echo htmlspecialchars($row['nama_pelanggan']); ?>?')">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </a>
                                        <a href="?delete=1&id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           title="Hapus Tagihan"
                                           onclick="return confirm('Hapus tagihan ini?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-success"><i class="fas fa-check-circle"></i> Lunas</span>
                                    <?php endif; ?>
                                    <a href="#" class="btn btn-sm btn-secondary" 
                                       onclick="showInvoice(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                                       title="Lihat Invoice">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php 
                        endwhile;
                        
                        if(!$has_data):
                        ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                <p>Tidak ada data tagihan untuk periode yang dipilih</p>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#generateModal">
                                    <i class="fas fa-plus-circle"></i> Generate Tagihan Sekarang
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if($has_data): ?>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="4" class="text-end">Total Tagihan: </td>
                            <td class="text-end text-primary">
                                Rp <?php echo number_format($total_tagihan, 0, ',', '.'); ?>
                            </td>
                            <td colspan="4"></td>
                        </tr>
                        <tr class="fw-bold">
                            <td colspan="4" class="text-end">Total Lunas: </td>
                            <td class="text-end text-success">
                                Rp <?php echo number_format($total_lunas, 0, ',', '.'); ?>
                            </td>
                            <td colspan="4"></td>
                        </tr>
                        <tr class="fw-bold">
                            <td colspan="4" class="text-end">Total Belum Lunas: </td>
                            <td class="text-end text-warning">
                                Rp <?php echo number_format($total_belum_lunas, 0, ',', '.'); ?>
                            </td>
                            <td colspan="4"></td>
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
                                <a class="page-link" href="?page=1&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&status=<?php echo $status; ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&status=<?php echo $status; ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&status=<?php echo $status; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&status=<?php echo $status; ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&status=<?php echo $status; ?>">
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

<!-- Modal Generate Tagihan -->
<div class="modal fade" id="generateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle"></i> Generate Tagihan Bulanan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="generate">
                    
                    <div class="mb-3">
                        <label class="form-label">Bulan</label>
                        <select name="bulan" class="form-select" required>
                            <option value="1">Januari</option>
                            <option value="2">Februari</option>
                            <option value="3">Maret</option>
                            <option value="4">April</option>
                            <option value="5">Mei</option>
                            <option value="6">Juni</option>
                            <option value="7">Juli</option>
                            <option value="8">Agustus</option>
                            <option value="9">September</option>
                            <option value="10">Oktober</option>
                            <option value="11">November</option>
                            <option value="12">Desember</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tahun</label>
                        <select name="tahun" class="form-select" required>
                            <?php 
                            $current_year = date('Y');
                            for($y = $current_year - 1; $y <= $current_year + 1; $y++):
                            ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Tagihan akan digenerate untuk semua pelanggan yang berstatus <strong>Aktif</strong>.
                        Jatuh tempo tagihan adalah tanggal 10 bulan berikutnya.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Generate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Domain -->
<div class="modal fade" id="domainModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="fas fa-globe"></i> Edit Domain Link Pembayaran
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_domain">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Informasi:</strong> Domain ini akan digunakan untuk membuat link pembayaran yang akan dibagikan ke pelanggan.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">URL Aplikasi <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-link"></i></span>
                            <input type="url" class="form-control" name="app_url" 
                                   value="<?php echo $app_url; ?>" required
                                   placeholder="https://domainanda.com">
                        </div>
                        <div class="form-text">Contoh: https://nexanet.states.media</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">URL Payment <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-credit-card"></i></span>
                            <input type="url" class="form-control" name="payment_url" 
                                   value="<?php echo $payment_url; ?>" required
                                   placeholder="https://domainanda.com/views/payment/invoice.php">
                        </div>
                        <div class="form-text">Contoh: https://nexanet.states.media/views/payment/invoice.php</div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Contoh Link yang akan dihasilkan:</strong><br>
                        <code id="previewLink"><?php echo $payment_url; ?>?token=CONTOH_TOKEN</code>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Payment Link -->
<div class="modal fade" id="paymentLinkModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-link"></i> Link Pembayaran
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Link pembayaran telah digenerate. Silakan bagikan link berikut ke pelanggan:</p>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="paymentLinkInput" readonly style="font-size: 12px;">
                    <button class="btn btn-primary" onclick="copyPaymentLink()">
                        <i class="fas fa-copy"></i> Salin
                    </button>
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Link ini dapat dibagikan melalui WhatsApp, Email, atau SMS.
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <a href="#" id="whatsappLink" class="btn btn-success w-100" target="_blank">
                            <i class="fab fa-whatsapp"></i> Bagikan ke WhatsApp
                        </a>
                    </div>
                    <div class="col-md-6">
                        <button class="btn btn-secondary w-100" onclick="copyPaymentLink()">
                            <i class="fas fa-copy"></i> Salin Link
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <a href="#" id="previewLinkBtn" class="btn btn-info" target="_blank">
                    <i class="fas fa-external-link-alt"></i> Preview Link
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Invoice -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-file-invoice"></i> Invoice Pembayaran
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="invoiceContent">
                <!-- Invoice content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button class="btn btn-primary" onclick="printInvoice()">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
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
        this.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => {
            this.innerHTML = originalText;
        }, 2000);
    });
});

// Copy link function
function copyLink(elementId) {
    const input = document.getElementById(elementId);
    input.select();
    input.setSelectionRange(0, 99999);
    document.execCommand('copy');
    
    // Show notification
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Tersalin!';
    setTimeout(() => {
        btn.innerHTML = originalText;
    }, 2000);
}

// Show invoice modal
function showInvoice(billing) {
    const invoiceHtml = `
        <div class="invoice-container">
            <div class="text-center mb-4">
                <h3>INVOICE PEMBAYARAN</h3>
                <p>Nexanet Internet Service Provider</p>
                <hr>
            </div>
            <div class="row">
                <div class="col-6">
                    <p><strong>No. Invoice:</strong> INV/${billing.bulan}/${billing.tahun}/${billing.id}</p>
                    <p><strong>Tanggal:</strong> ${new Date().toLocaleDateString('id-ID')}</p>
                </div>
                <div class="col-6 text-end">
                    <p><strong>Jatuh Tempo:</strong> ${billing.tanggal_jatuh_tempo ? new Date(billing.tanggal_jatuh_tempo).toLocaleDateString('id-ID') : '-'}</p>
                    <p><strong>Status:</strong> <span class="badge bg-${billing.status == 'lunas' ? 'success' : 'warning'}">${billing.status == 'lunas' ? 'LUNAS' : 'BELUM LUNAS'}</span></p>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-12">
                    <p><strong>Kepada Yth:</strong><br>
                    ${billing.nama_pelanggan || '-'}<br>
                    ${billing.alamat || '-'}</p>
                </div>
            </div>
            <hr>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Deskripsi</th>
                        <th class="text-end">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Tagihan Internet - Periode ${billing.bulan}/${billing.tahun}</td>
                        <td class="text-end">Rp ${formatNumber(billing.jumlah)}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td class="text-end">Total</td>
                        <td class="text-end">Rp ${formatNumber(billing.jumlah)}</td>
                    </tr>
                </tfoot>
            </table>
            <hr>
            <div class="row">
                <div class="col-12">
                    <p><strong>Pembayaran dapat dilakukan melalui:</strong></p>
                    <?php foreach($active_rekening as $rek): ?>
                    <div class="mb-2">
                        <strong><?php echo $rek['nama_bank']; ?></strong><br>
                        No. Rekening: <?php echo chunk_split($rek['nomor_rekening'], 4, ' '); ?><br>
                        Atas Nama: <?php echo $rek['nama_pemilik']; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <hr>
            <div class="text-center mt-3">
                <small>Terima kasih atas kepercayaan Anda menggunakan layanan kami.</small>
            </div>
        </div>
    `;
    
    document.getElementById('invoiceContent').innerHTML = invoiceHtml;
    var modal = new bootstrap.Modal(document.getElementById('invoiceModal'));
    modal.show();
}

function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function printInvoice() {
    const printContent = document.getElementById('invoiceContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Invoice Pembayaran</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { padding: 20px; }
                .invoice-container { max-width: 800px; margin: 0 auto; }
                @media print {
                    .btn { display: none; }
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            ${printContent}
            <script>window.print();<\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Export to Excel
function exportToExcel() {
    let table = document.getElementById('billingTable');
    let html = table.outerHTML;
    
    const bulan = document.querySelector('select[name="bulan"]').value;
    const tahun = document.querySelector('select[name="tahun"]').value;
    const bulan_nama = getBulanName(bulan);
    
    html = `
        <html>
        <head>
            <title>Laporan Tagihan</title>
            <style>
                th { background-color: #4e73df; color: white; }
                td { border: 1px solid #ddd; }
                .text-end { text-align: right; }
                .text-success { color: #1cc88a; }
                .text-danger { color: #e74a3b; }
            </style>
        </head>
        <body>
            <h2>Laporan Tagihan</h2>
            <p>Periode: ${bulan_nama} ${tahun}</p>
            <p>Status: ${document.querySelector('select[name="status"]').value || 'Semua'}</p>
            <p>Tanggal Export: ${new Date().toLocaleString()}</p>
            <p><strong>Ringkasan:</strong></p>
            <p>Total Tagihan: Rp <?php echo number_format($total_tagihan, 0, ',', '.'); ?></p>
            <p>Total Terbayar: Rp <?php echo number_format($total_lunas, 0, ',', '.'); ?></p>
            <p>Total Belum Terbayar: Rp <?php echo number_format($total_belum_lunas, 0, ',', '.'); ?></p>
            ${html}
        </body>
        </html>
    `;
    
    let blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    let link = document.createElement('a');
    let url = URL.createObjectURL(blob);
    
    link.href = url;
    link.download = 'laporan_tagihan.xls';
    link.click();
    
    URL.revokeObjectURL(url);
}

function getBulanName(bulan) {
    const bulanNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return bulanNames[parseInt(bulan) - 1];
}

// Payment link functions
let currentPaymentLink = '<?php echo $payment_link; ?>';
let currentPaymentToken = '';

function showPaymentLink(link) {
    currentPaymentLink = link;
    document.getElementById('paymentLinkInput').value = link;
    
    // Extract token from link
    const urlParams = new URLSearchParams(link.split('?')[1]);
    const token = urlParams.get('token');
    currentPaymentToken = token;
    
    // Set WhatsApp link
    const whatsappLink = `https://wa.me/?text=${encodeURIComponent('*INVOICE PEMBAYARAN NEXANET*\n\nBerikut link pembayaran tagihan Anda:\n' + link + '\n\nTerima kasih.')}`;
    document.getElementById('whatsappLink').href = whatsappLink;
    
    // Set preview link
    document.getElementById('previewLinkBtn').href = link;
    
    var modal = new bootstrap.Modal(document.getElementById('paymentLinkModal'));
    modal.show();
}

function copyPaymentLink() {
    const input = document.getElementById('paymentLinkInput');
    input.select();
    input.setSelectionRange(0, 99999);
    document.execCommand('copy');
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Tersalin!';
    setTimeout(() => {
        btn.innerHTML = originalText;
    }, 2000);
}

// Preview link saat input berubah di modal domain
document.querySelector('input[name="payment_url"]')?.addEventListener('input', function() {
    const preview = document.getElementById('previewLink');
    if(preview) {
        preview.textContent = this.value + '?token=CONTOH_TOKEN';
    }
});

document.querySelector('input[name="app_url"]')?.addEventListener('input', function() {
    const paymentInput = document.querySelector('input[name="payment_url"]');
    const preview = document.getElementById('previewLink');
    if(preview && paymentInput && paymentInput.value === '<?php echo $payment_url; ?>') {
        preview.textContent = this.value + '/views/payment/invoice.php?token=CONTOH_TOKEN';
    }
});

<?php if($show_payment_modal && $payment_link): ?>
showPaymentLink('<?php echo addslashes($payment_link); ?>');
<?php endif; ?>

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

.badge {
    font-size: 12px;
    padding: 5px 10px;
}

.invoice-container {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

@media print {
    .btn, .pagination, .alert, form, .card-header .btn, .card-footer, .modal {
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