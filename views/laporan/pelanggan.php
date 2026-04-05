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
require_once '../../models/Billing.php';
require_once '../../models/Pemasukan.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize models
$pelanggan = new Pelanggan($db);
$billing = new Billing($db);
$pemasukan = new Pemasukan($db);

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$paket = isset($_GET['paket']) ? $_GET['paket'] : '';

// Get all customers
$query = "SELECT p.*, 
          (SELECT COUNT(*) FROM billing WHERE pelanggan_id = p.id) as total_tagihan,
          (SELECT COALESCE(SUM(jumlah), 0) FROM billing WHERE pelanggan_id = p.id AND status = 'lunas') as total_dibayar,
          (SELECT COALESCE(SUM(jumlah), 0) FROM billing WHERE pelanggan_id = p.id AND status = 'belum_lunas') as total_hutang,
          (SELECT COUNT(*) FROM billing WHERE pelanggan_id = p.id AND status = 'belum_lunas' AND tanggal_jatuh_tempo < CURDATE()) as tagihan_terlambat
          FROM pelanggan p
          WHERE 1=1";

if(!empty($status)) {
    $query .= " AND p.status = :status";
}
if(!empty($search)) {
    $query .= " AND (p.nama LIKE :search OR p.id_pelanggan LIKE :search OR p.alamat LIKE :search OR p.no_hp LIKE :search)";
}
if(!empty($paket)) {
    $query .= " AND p.paket_internet = :paket";
}
$query .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($query);

if(!empty($status)) {
    $stmt->bindParam(':status', $status);
}
if(!empty($search)) {
    $searchTerm = "%{$search}%";
    $stmt->bindParam(':search', $searchTerm);
}
if(!empty($paket)) {
    $stmt->bindParam(':paket', $paket);
}

$stmt->execute();
$pelanggan_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_pelanggan = count($pelanggan_data);
$total_aktif = 0;
$total_nonaktif = 0;
$total_pendapatan_bulanan = 0;
$total_hutang_keseluruhan = 0;
$total_tagihan_terlambat = 0;

foreach($pelanggan_data as $row) {
    if($row['status'] == 'aktif') {
        $total_aktif++;
        $total_pendapatan_bulanan += $row['harga_paket'];
    } else {
        $total_nonaktif++;
    }
    $total_hutang_keseluruhan += ($row['total_hutang'] ?? 0);
    $total_tagihan_terlambat += ($row['tagihan_terlambat'] ?? 0);
}

// Get package statistics - FIX: Use proper query without array_sum on fetch result
$query_paket = "SELECT paket_internet, COUNT(*) as jumlah, SUM(harga_paket) as total_pendapatan
                FROM pelanggan 
                WHERE status = 'aktif'
                GROUP BY paket_internet
                ORDER BY jumlah DESC";
$stmt_paket = $db->prepare($query_paket);
$stmt_paket->execute();
$paket_stats = $stmt_paket->fetchAll(PDO::FETCH_ASSOC);

// Get registration trend for the year
$trend_data = [];
$current_year = date('Y');
for($m = 1; $m <= 12; $m++) {
    $query_trend = "SELECT COUNT(*) as jumlah 
                    FROM pelanggan 
                    WHERE MONTH(created_at) = :bulan AND YEAR(created_at) = :tahun";
    $stmt_trend = $db->prepare($query_trend);
    $stmt_trend->bindParam(':bulan', $m, PDO::PARAM_INT);
    $stmt_trend->bindParam(':tahun', $current_year, PDO::PARAM_INT);
    $stmt_trend->execute();
    $trend = $stmt_trend->fetch(PDO::FETCH_ASSOC);
    
    $trend_data[] = [
        'bulan' => $m,
        'nama_bulan' => date('F', mktime(0, 0, 0, $m, 1)),
        'jumlah' => $trend ? (int)$trend['jumlah'] : 0
    ];
}

// Get package distribution for chart
$package_labels = [];
$package_counts = [];
foreach($paket_stats as $pkg) {
    $package_labels[] = $pkg['paket_internet'];
    $package_counts[] = (int)$pkg['jumlah'];
}

// Calculate total values safely
$total_harga_paket = 0;
$total_dibayar_all = 0;
foreach($pelanggan_data as $row) {
    $total_harga_paket += $row['harga_paket'];
    $total_dibayar_all += ($row['total_dibayar'] ?? 0);
}

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-users"></i> Laporan Data Pelanggan
        </h1>
        <div>
            <button class="btn btn-success" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <button class="btn btn-danger" onclick="exportToPDF()">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            <button class="btn btn-info" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
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
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        <option value="aktif" <?php echo $status == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="nonaktif" <?php echo $status == 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Paket Internet</label>
                    <select name="paket" class="form-select">
                        <option value="">Semua Paket</option>
                        <option value="Paket Silver 10 Mbps" <?php echo $paket == 'Paket Silver 10 Mbps' ? 'selected' : ''; ?>>Paket Silver 10 Mbps</option>
                        <option value="Paket Gold 20 Mbps" <?php echo $paket == 'Paket Gold 20 Mbps' ? 'selected' : ''; ?>>Paket Gold 20 Mbps</option>
                        <option value="Paket Platinum 50 Mbps" <?php echo $paket == 'Paket Platinum 50 Mbps' ? 'selected' : ''; ?>>Paket Platinum 50 Mbps</option>
                        <option value="Paket Diamond 100 Mbps" <?php echo $paket == 'Paket Diamond 100 Mbps' ? 'selected' : ''; ?>>Paket Diamond 100 Mbps</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cari</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Nama, ID, Alamat, atau No HP"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Tampilkan
                        </button>
                        <a href="pelanggan.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
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
                                Total Pelanggan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_pelanggan); ?>
                            </div>
                            <div class="small text-muted">
                                Seluruh pelanggan terdaftar
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                Pelanggan Aktif</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_aktif); ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo $total_pelanggan > 0 ? number_format(($total_aktif / $total_pelanggan) * 100, 1) : 0; ?>% dari total
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
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
                                Pelanggan Nonaktif</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_nonaktif); ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo $total_pelanggan > 0 ? number_format(($total_nonaktif / $total_pelanggan) * 100, 1) : 0; ?>% dari total
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-slash fa-2x text-gray-300"></i>
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
                                Pendapatan Bulanan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_pendapatan_bulanan, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                Dari pelanggan aktif
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Statistics Row -->
    <div class="row">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Hutang</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_hutang_keseluruhan, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                Seluruh tagihan belum dibayar
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Tagihan Terlambat</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_tagihan_terlambat); ?>
                            </div>
                            <div class="small text-muted">
                                Tagihan melewati jatuh tempo
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                Rata-rata per Pelanggan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo $total_aktif > 0 ? number_format($total_pendapatan_bulanan / $total_aktif, 0, ',', '.') : '0'; ?>
                            </div>
                            <div class="small text-muted">
                                Pendapatan per pelanggan aktif
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

    <!-- Charts Row -->
    <div class="row">
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-pie"></i> Distribusi Paket Internet
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="packageChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line"></i> Tren Pendaftaran Pelanggan <?php echo date('Y'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Pelanggan Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i> Detail Data Pelanggan
                        <?php if($status): ?>
                        <span class="badge bg-info ms-2">Status: <?php echo $status == 'aktif' ? 'Aktif' : 'Nonaktif'; ?></span>
                        <?php endif; ?>
                        <?php if($paket): ?>
                        <span class="badge bg-info ms-2">Paket: <?php echo $paket; ?></span>
                        <?php endif; ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="pelangganTable">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>ID Pelanggan</th>
                                    <th>Nama</th>
                                    <th>Alamat</th>
                                    <th>No HP</th>
                                    <th>Paket Internet</th>
                                    <th>Harga</th>
                                    <th>Status</th>
                                    <th>Total Tagihan</th>
                                    <th>Total Dibayar</th>
                                    <th>Sisa Hutang</th>
                                    <th>Terlambat</th>
                                 '</tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach($pelanggan_data as $row): 
                                    $sisa_hutang = ($row['total_hutang'] ?? 0) - ($row['total_dibayar'] ?? 0);
                                    $status_class = $row['status'] == 'aktif' ? 'success' : 'secondary';
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($row['id_pelanggan']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                    <td><?php echo htmlspecialchars($row['alamat']); ?></td>
                                    <td><?php echo htmlspecialchars($row['no_hp']); ?></td>
                                    <td><?php echo htmlspecialchars($row['paket_internet']); ?></td>
                                    <td class="text-end">Rp <?php echo number_format($row['harga_paket'], 0, ',', '.'); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo $row['status'] == 'aktif' ? 'Aktif' : 'Nonaktif'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if(($row['total_tagihan'] ?? 0) > 0): ?>
                                            Rp <?php echo number_format($row['total_tagihan'], 0, ',', '.'); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-success">
                                        <?php if(($row['total_dibayar'] ?? 0) > 0): ?>
                                            Rp <?php echo number_format($row['total_dibayar'], 0, ',', '.'); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end <?php echo $sisa_hutang > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php if($sisa_hutang > 0): ?>
                                            Rp <?php echo number_format($sisa_hutang, 0, ',', '.'); ?>
                                        <?php else: ?>
                                            Lunas
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if(($row['tagihan_terlambat'] ?? 0) > 0): ?>
                                            <span class="badge bg-danger">
                                                <?php echo $row['tagihan_terlambat']; ?> Tagihan
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Tepat Waktu</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($pelanggan_data)): ?>
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                        <p>Tidak ada data pelanggan yang ditemukan</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="6" class="text-end">Total Keseluruhan:</td>
                                    <td class="text-end">Rp <?php echo number_format($total_harga_paket, 0, ',', '.'); ?></td>
                                    <td colspan="2"></td>
                                    <td class="text-end text-success">
                                        Rp <?php echo number_format($total_dibayar_all, 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-end text-danger">
                                        Rp <?php echo number_format($total_hutang_keseluruhan, 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $total_tagihan_terlambat; ?> Tagihan
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart Data
const packageLabels = <?php echo json_encode($package_labels); ?>;
const packageCounts = <?php echo json_encode($package_counts); ?>;
const trendData = <?php echo json_encode($trend_data); ?>;

// Package Distribution Pie Chart
const ctx1 = document.getElementById('packageChart').getContext('2d');
const packageColors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'];

if(packageLabels.length > 0) {
    new Chart(ctx1, {
        type: 'pie',
        data: {
            labels: packageLabels,
            datasets: [{
                data: packageCounts,
                backgroundColor: packageColors.slice(0, packageLabels.length),
                borderWidth: 0
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
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: ${value} pelanggan (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Registration Trend Chart
const ctx2 = document.getElementById('trendChart').getContext('2d');
new Chart(ctx2, {
    type: 'line',
    data: {
        labels: trendData.map(item => item.nama_bulan.substring(0, 3)),
        datasets: [{
            label: 'Jumlah Pelanggan Baru',
            data: trendData.map(item => item.jumlah),
            borderColor: '#4e73df',
            backgroundColor: 'rgba(78, 115, 223, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.3,
            pointBackgroundColor: '#4e73df',
            pointBorderColor: '#fff',
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y + ' pelanggan';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                },
                title: {
                    display: true,
                    text: 'Jumlah Pelanggan'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Bulan'
                }
            }
        }
    }
});

// Export to Excel
function exportToExcel() {
    let table = document.getElementById('pelangganTable');
    let html = table.outerHTML;
    
    const statusSelect = document.querySelector('select[name="status"]');
    const paketSelect = document.querySelector('select[name="paket"]');
    const status = statusSelect ? statusSelect.value || 'Semua' : 'Semua';
    const paket = paketSelect ? paketSelect.value || 'Semua' : 'Semua';
    
    html = `
        <html>
        <head>
            <title>Laporan Data Pelanggan</title>
            <style>
                th { background-color: #4e73df; color: white; }
                td { border: 1px solid #ddd; }
                .text-end { text-align: right; }
                .text-success { color: #1cc88a; }
                .text-danger { color: #e74a3b; }
            </style>
        </head>
        <body>
            <h2>Laporan Data Pelanggan</h2>
            <p>Status: ${status}</p>
            <p>Paket: ${paket}</p>
            <p>Tanggal Export: ${new Date().toLocaleString()}</p>
            <p><strong>Ringkasan:</strong></p>
            <p>Total Pelanggan: <?php echo $total_pelanggan; ?></p>
            <p>Pelanggan Aktif: <?php echo $total_aktif; ?></p>
            <p>Pelanggan Nonaktif: <?php echo $total_nonaktif; ?></p>
            <p>Pendapatan Bulanan: Rp <?php echo number_format($total_pendapatan_bulanan, 0, ',', '.'); ?></p>
            <p>Total Hutang: Rp <?php echo number_format($total_hutang_keseluruhan, 0, ',', '.'); ?></p>
            ${html}
        </body>
        </html>
    `;
    
    let blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    let link = document.createElement('a');
    let url = URL.createObjectURL(blob);
    
    link.href = url;
    link.download = 'laporan_pelanggan.xls';
    link.click();
    
    URL.revokeObjectURL(url);
}

// Export to PDF (print)
function exportToPDF() {
    window.print();
}
</script>

<style>
.border-left-primary { border-left: 4px solid #4e73df !important; }
.border-left-success { border-left: 4px solid #1cc88a !important; }
.border-left-danger { border-left: 4px solid #e74a3b !important; }
.border-left-warning { border-left: 4px solid #f6c23e !important; }
.border-left-info { border-left: 4px solid #36b9cc !important; }
.border-left-secondary { border-left: 4px solid #858796 !important; }
.text-gray-800 { color: #5a5c69 !important; }

.table-responsive {
    overflow-x: auto;
}

.table th, .table td {
    vertical-align: middle;
}

.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

@media print {
    .btn, .pagination, .alert, form, .card-header .btn, .card-footer, .no-print {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
        page-break-inside: avoid;
    }
    .table {
        font-size: 10px;
    }
    body {
        padding: 0;
        margin: 0;
    }
    .badge {
        border: 1px solid #ddd;
        background: #f8f9fc !important;
        color: #333 !important;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>