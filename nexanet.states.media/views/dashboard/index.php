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
require_once '../../models/Pelanggan.php';
require_once '../../models/Material.php';
require_once '../../models/Pemasukan.php';
require_once '../../models/Pengeluaran.php';
require_once '../../models/Billing.php';
require_once '../../models/Pop.php';
require_once '../../models/Odp.php';
require_once '../../models/Paket.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize models
$pelanggan = new Pelanggan($db);
$material = new Material($db);
$pemasukan = new Pemasukan($db);
$pengeluaran = new Pengeluaran($db);
$billing = new Billing($db);
$pop = new Pop($db);
$odp = new Odp($db);
$paket = new Paket($db);

// Get statistics
$total_pelanggan = $pelanggan->getTotal();
$total_pelanggan_aktif = $pelanggan->getTotal('', 'aktif');
$total_pelanggan_nonaktif = $pelanggan->getTotal('', 'nonaktif');

// Get material statistics
$total_material = $material->getTotal();
$total_stok_material = 0;
$all_materials = $material->read('', 9999, 0);
while($row = $all_materials->fetch(PDO::FETCH_ASSOC)) {
    $total_stok_material += $row['stok'];
}
$low_stock_count = count($material->getLowStock());

// Get financial statistics
$total_pemasukan_bulan_ini = $pemasukan->getTotalBulanIni();
$total_pengeluaran_bulan_ini = $pengeluaran->getTotalBulanIni();
$saldo_bulan_ini = $total_pemasukan_bulan_ini - $total_pengeluaran_bulan_ini;

// Get billing statistics
$total_billing_belum_lunas = $billing->getTotalBelumLunas();
$overdue_bills = $billing->getTagihanTerlambat();
$total_overdue = count($overdue_bills);

// Get POP & ODP statistics
$pop_stats = $pop->getStats();
$odp_stats = $odp->getStats();

// Get package statistics
$paket_stats = $paket->getStats();
$paket_populer = $paket->getWithCustomerCount();
$top_paket = !empty($paket_populer) ? $paket_populer[0] : null;

// Get chart data for last 6 months
$chart_data = $pemasukan->getChartData();
$chart_pengeluaran = $pengeluaran->getChartData();

// Get recent transactions
$recent_pemasukan = $pemasukan->read(date('Y-m-01'), date('Y-m-t'), 5, 0)->fetchAll(PDO::FETCH_ASSOC);
$recent_pengeluaran = $pengeluaran->read(date('Y-m-01'), date('Y-m-t'), 5, 0)->fetchAll(PDO::FETCH_ASSOC);

// Get recent customers
$recent_customers = $pelanggan->read('', 5, 0)->fetchAll(PDO::FETCH_ASSOC);

// Calculate percentage changes
$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));
$last_month_pemasukan = $pemasukan->getTotalAmount($last_month_start, $last_month_end);
$last_month_pengeluaran = $pengeluaran->getTotalAmount($last_month_start, $last_month_end);

$pemasukan_change = $last_month_pemasukan > 0 ? (($total_pemasukan_bulan_ini - $last_month_pemasukan) / $last_month_pemasukan) * 100 : 0;
$pengeluaran_change = $last_month_pengeluaran > 0 ? (($total_pengeluaran_bulan_ini - $last_month_pengeluaran) / $last_month_pengeluaran) * 100 : 0;

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </h1>
        <div>
            <button class="btn btn-sm btn-primary shadow-sm" onclick="location.reload()">
                <i class="fas fa-sync-alt fa-sm"></i> Refresh
            </button>
            <button class="btn btn-sm btn-success shadow-sm" onclick="window.print()">
                <i class="fas fa-print fa-sm"></i> Print
            </button>
        </div>
    </div>

    <!-- Welcome Card -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card bg-gradient-primary text-white shadow">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-2">Selamat Datang, <?php echo $_SESSION['nama_lengkap']; ?>!</h4>
                            <p class="mb-0">Anda login sebagai <strong><?php echo $_SESSION['role']; ?></strong> pada <?php echo date('d F Y H:i:s'); ?></p>
                            <p class="mb-0 mt-2">
                                <i class="fas fa-chart-line me-1"></i> 
                                Total pendapatan bulan ini: <strong>Rp <?php echo number_format($total_pemasukan_bulan_ini, 0, ',', '.'); ?></strong>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <i class="fas fa-chart-line fa-4x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <!-- Pelanggan Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Pelanggan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_pelanggan); ?>
                            </div>
                            <div class="small text-muted">
                                Aktif: <?php echo number_format($total_pelanggan_aktif); ?> | 
                                Nonaktif: <?php echo number_format($total_pelanggan_nonaktif); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="../pelanggan/index.php" class="text-primary text-decoration-none">
                        Lihat Detail <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Pemasukan Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Pemasukan Bulan Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_pemasukan_bulan_ini, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                <?php if($pemasukan_change >= 0): ?>
                                    <span class="text-success">
                                        <i class="fas fa-arrow-up"></i> <?php echo number_format($pemasukan_change, 1); ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="text-danger">
                                        <i class="fas fa-arrow-down"></i> <?php echo number_format(abs($pemasukan_change), 1); ?>%
                                    </span>
                                <?php endif; ?>
                                dari bulan lalu
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="../pemasukan/index.php" class="text-success text-decoration-none">
                        Lihat Detail <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Pengeluaran Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Pengeluaran Bulan Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_pengeluaran_bulan_ini, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                <?php if($pengeluaran_change >= 0): ?>
                                    <span class="text-danger">
                                        <i class="fas fa-arrow-up"></i> <?php echo number_format($pengeluaran_change, 1); ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="text-success">
                                        <i class="fas fa-arrow-down"></i> <?php echo number_format(abs($pengeluaran_change), 1); ?>%
                                    </span>
                                <?php endif; ?>
                                dari bulan lalu
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="../pengeluaran/index.php" class="text-danger text-decoration-none">
                        Lihat Detail <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Laba/Rugi Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-<?php echo $saldo_bulan_ini >= 0 ? 'success' : 'warning'; ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?php echo $saldo_bulan_ini >= 0 ? 'success' : 'warning'; ?> text-uppercase mb-1">
                                Laba/Rugi Bulan Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span class="text-<?php echo $saldo_bulan_ini >= 0 ? 'success' : 'danger'; ?>">
                                    Rp <?php echo number_format(abs($saldo_bulan_ini), 0, ',', '.'); ?>
                                    <?php echo $saldo_bulan_ini >= 0 ? '(Laba)' : '(Rugi)'; ?>
                                </span>
                            </div>
                            <div class="small text-muted">
                                Pendapatan - Pengeluaran
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Row Statistics -->
    <div class="row">
        <!-- Material Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Data Material</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_material); ?> Jenis
                            </div>
                            <div class="small text-muted">
                                Total Stok: <?php echo number_format($total_stok_material); ?> unit
                            </div>
                            <?php if($low_stock_count > 0): ?>
                                <div class="small text-warning">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo $low_stock_count; ?> material stok menipis
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-boxes fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="../material/index.php" class="text-info text-decoration-none">
                        Lihat Detail <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Tagihan Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Tagihan Belum Lunas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_billing_belum_lunas, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                <?php if($total_overdue > 0): ?>
                                    <span class="text-danger">
                                        <i class="fas fa-clock"></i> <?php echo $total_overdue; ?> tagihan terlambat
                                    </span>
                                <?php else: ?>
                                    <span class="text-success">Semua tagihan lancar</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="../billing/index.php" class="text-warning text-decoration-none">
                        Lihat Detail <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- POP & ODP Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                Infrastruktur</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($pop_stats['total_pop']); ?> POP | <?php echo number_format($odp_stats['total_odp']); ?> ODP
                            </div>
                            <div class="small text-muted">
                                POP Aktif: <?php echo number_format($pop_stats['aktif']); ?> | 
                                ODP Aktif: <?php echo number_format($odp_stats['aktif']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-broadcast-tower fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="../pop/index.php" class="text-secondary text-decoration-none">
                        Lihat Peta <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Paket Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-purple shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-purple text-uppercase mb-1">
                                Paket Internet</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($paket_stats['total_paket']); ?> Paket
                            </div>
                            <div class="small text-muted">
                                Aktif: <?php echo number_format($paket_stats['aktif']); ?> | 
                                Nonaktif: <?php echo number_format($paket_stats['nonaktif']); ?>
                            </div>
                            <?php if($top_paket): ?>
                                <div class="small text-muted mt-1">
                                    <i class="fas fa-chart-line"></i> Populer: <?php echo $top_paket['nama_paket']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tags fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="../paket/index.php" class="text-purple text-decoration-none">
                        Kelola Paket <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line me-1"></i> Grafik Pemasukan vs Pengeluaran (6 Bulan Terakhir)
                    </h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end shadow animated--fade-in">
                            <a class="dropdown-item" href="#" onclick="exportChartAsImage('incomeExpenseChart')">
                                <i class="fas fa-download fa-sm fa-fw me-2"></i> Download Chart
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 350px;">
                        <canvas id="incomeExpenseChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-pie me-1"></i> Status Tagihan
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 350px;">
                        <canvas id="billingStatusChart"></canvas>
                    </div>
                    <div class="mt-3 text-center">
                        <div class="row">
                            <div class="col-6">
                                <div class="text-success">Lunas</div>
                                <div class="h5"><?php echo number_format(($total_billing_belum_lunas > 0 ? 100 - ($total_billing_belum_lunas / ($total_billing_belum_lunas + 1)) : 0), 1); ?>%</div>
                            </div>
                            <div class="col-6">
                                <div class="text-warning">Belum Lunas</div>
                                <div class="h5"><?php echo number_format(($total_billing_belum_lunas > 0 ? 100 : 0), 1); ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities Row -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-money-bill-wave me-1"></i> Transaksi Pemasukan Terbaru
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Pelanggan</th>
                                    <th class="text-end">Jumlah</th>
                                    <th>Keterangan</th>
                                </thead>
                            <tbody>
                                <?php if(!empty($recent_pemasukan)): ?>
                                    <?php foreach($recent_pemasukan as $item): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($item['tanggal'])); ?></td>
                                        <td><?php echo htmlspecialchars($item['nama_pelanggan'] ?? '-'); ?></td>
                                        <td class="text-end text-success">Rp <?php echo number_format($item['jumlah'], 0, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($item['keterangan'] ?: '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Belum ada transaksi</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-2">
                        <a href="../pemasukan/index.php" class="btn btn-sm btn-success">Lihat Semua <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-danger">
                        <i class="fas fa-chart-line me-1"></i> Transaksi Pengeluaran Terbaru
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jenis</th>
                                    <th class="text-end">Jumlah</th>
                                    <th>Keterangan</th>
                                </thead>
                            <tbody>
                                <?php if(!empty($recent_pengeluaran)): ?>
                                    <?php foreach($recent_pengeluaran as $item): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($item['tanggal'])); ?></td>
                                        <td><?php echo htmlspecialchars($item['jenis_pengeluaran']); ?></td>
                                        <td class="text-end text-danger">Rp <?php echo number_format($item['jumlah'], 0, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($item['keterangan'] ?: '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Belum ada transaksi</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-2">
                        <a href="../pengeluaran/index.php" class="btn btn-sm btn-danger">Lihat Semua <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Customers and Overdue Bills -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-users me-1"></i> Pelanggan Baru
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Paket</th>
                                    <th>Status</th>
                                </thead>
                            <tbody>
                                <?php if(!empty($recent_customers)): ?>
                                    <?php foreach($recent_customers as $customer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($customer['id_pelanggan']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['paket_internet']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $customer['status'] == 'aktif' ? 'success' : 'secondary'; ?>">
                                                <?php echo $customer['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Belum ada pelanggan baru</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-2">
                        <a href="../pelanggan/index.php" class="btn btn-sm btn-primary">Lihat Semua <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i> Tagihan Terlambat
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Pelanggan</th>
                                    <th>Periode</th>
                                    <th class="text-end">Jumlah</th>
                                    <th>Jatuh Tempo</th>
                                </thead>
                            <tbody>
                                <?php if(!empty($overdue_bills)): ?>
                                    <?php foreach(array_slice($overdue_bills, 0, 5) as $bill): ?>
                                    <tr class="table-danger">
                                        <td><?php echo htmlspecialchars($bill['nama_pelanggan']); ?></td>
                                        <td><?php echo $bill['bulan']; ?>/<?php echo $bill['tahun']; ?></td>
                                        <td class="text-end">Rp <?php echo number_format($bill['jumlah'], 0, ',', '.'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($bill['tanggal_jatuh_tempo'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-success">
                                            <i class="fas fa-check-circle"></i> Semua tagihan lancar
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if(!empty($overdue_bills)): ?>
                    <div class="text-end mt-2">
                        <a href="../billing/index.php?status=belum_lunas" class="btn btn-sm btn-warning">Lihat Semua <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart Data
const incomeLabels = <?php echo json_encode($chart_data['labels']); ?>;
const incomeValues = <?php echo json_encode($chart_data['values']); ?>;
const expenseLabels = <?php echo json_encode($chart_pengeluaran['labels']); ?>;
const expenseValues = <?php echo json_encode($chart_pengeluaran['values']); ?>;

// Income vs Expense Chart
const ctx1 = document.getElementById('incomeExpenseChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: incomeLabels,
        datasets: [
            {
                label: 'Pemasukan',
                data: incomeValues,
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#1cc88a',
                pointBorderColor: '#fff',
                pointRadius: 4,
                pointHoverRadius: 6
            },
            {
                label: 'Pengeluaran',
                data: expenseValues,
                borderColor: '#e74a3b',
                backgroundColor: 'rgba(231, 74, 59, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#e74a3b',
                pointBorderColor: '#fff',
                pointRadius: 4,
                pointHoverRadius: 6
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                    }
                }
            },
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + value.toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});

// Billing Status Chart
const totalTagihan = <?php echo $total_billing_belum_lunas; ?>;
const totalLunas = <?php echo $total_billing_belum_lunas > 0 ? 100 - $total_billing_belum_lunas : 0; ?>;

const ctx2 = document.getElementById('billingStatusChart').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: ['Belum Lunas', 'Lunas'],
        datasets: [{
            data: [totalTagihan > 0 ? 100 : 0, totalTagihan > 0 ? 0 : 100],
            backgroundColor: ['#f6c23e', '#1cc88a'],
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        return `${label}: ${value}%`;
                    }
                }
            }
        },
        cutout: '70%'
    }
});

// Export chart as image
function exportChartAsImage(chartId) {
    const canvas = document.getElementById(chartId);
    const link = document.createElement('a');
    link.download = `${chartId}.png`;
    link.href = canvas.toDataURL();
    link.click();
}

// Auto-refresh every 60 seconds (optional)
setInterval(function() {
    location.reload();
}, 60000);
</script>

<style>
.border-left-primary { border-left: 4px solid #4e73df !important; }
.border-left-success { border-left: 4px solid #1cc88a !important; }
.border-left-danger { border-left: 4px solid #e74a3b !important; }
.border-left-warning { border-left: 4px solid #f6c23e !important; }
.border-left-info { border-left: 4px solid #36b9cc !important; }
.border-left-secondary { border-left: 4px solid #858796 !important; }
.border-left-purple { border-left: 4px solid #6f42c1 !important; }

.text-gray-800 { color: #5a5c69 !important; }
.text-purple { color: #6f42c1 !important; }

.bg-gradient-primary {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
}

.opacity-50 {
    opacity: 0.5;
}

.chart-container {
    position: relative;
    width: 100%;
}

.card-footer {
    padding: 0.75rem 1.25rem;
    background-color: #f8f9fc;
    border-top: 1px solid #e3e6f0;
}

.table td, .table th {
    vertical-align: middle;
}

@media print {
    .btn, .card-footer .btn, .navbar, .sidebar, .dropdown, .sidebar-overlay {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        box-shadow: none !important;
        border: 1px solid #ddd;
    }
    
    .card-header {
        background: #f8f9fc !important;
        color: #333 !important;
    }
    
    .chart-container {
        page-break-inside: avoid;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>