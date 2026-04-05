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

// Get filter parameters
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Get data
$query = "SELECT b.*, p.nama as nama_pelanggan, p.id_pelanggan, p.alamat, p.no_hp, p.paket_internet
          FROM billing b
          LEFT JOIN pelanggan p ON b.pelanggan_id = p.id
          WHERE b.bulan = :bulan AND b.tahun = :tahun";
if(!empty($status)) {
    $query .= " AND b.status = :status";
}
$query .= " ORDER BY p.nama ASC";

$stmt = $db->prepare($query);
$stmt->bindParam(':bulan', $bulan);
$stmt->bindParam(':tahun', $tahun);
if(!empty($status)) {
    $stmt->bindParam(':status', $status);
}
$stmt->execute();
$tagihan_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_tagihan = 0;
$total_terbayar = 0;
$total_belum_terbayar = 0;

foreach($tagihan_data as $row) {
    $total_tagihan += $row['jumlah'];
    if($row['status'] == 'lunas') {
        $total_terbayar += $row['jumlah'];
    } else {
        $total_belum_terbayar += $row['jumlah'];
    }
}

// Get statistics for chart
$query_chart = "SELECT 
                    status,
                    COUNT(*) as jumlah_tagihan,
                    SUM(jumlah) as total_nominal
                FROM billing
                WHERE bulan = :bulan AND tahun = :tahun
                GROUP BY status";
$stmt_chart = $db->prepare($query_chart);
$stmt_chart->bindParam(':bulan', $bulan);
$stmt_chart->bindParam(':tahun', $tahun);
$stmt_chart->execute();
$chart_data = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);

// Get monthly trend data for the year
$trend_data = [];
for($m = 1; $m <= 12; $m++) {
    $query_trend = "SELECT 
                        SUM(CASE WHEN status = 'lunas' THEN jumlah ELSE 0 END) as total_terbayar,
                        SUM(CASE WHEN status = 'belum_lunas' THEN jumlah ELSE 0 END) as total_belum
                    FROM billing
                    WHERE bulan = :bulan AND tahun = :tahun";
    $stmt_trend = $db->prepare($query_trend);
    $stmt_trend->bindParam(':bulan', $m);
    $stmt_trend->bindParam(':tahun', $tahun);
    $stmt_trend->execute();
    $trend = $stmt_trend->fetch(PDO::FETCH_ASSOC);
    
    $trend_data[] = [
        'bulan' => $m,
        'nama_bulan' => date('F', mktime(0, 0, 0, $m, 1)),
        'terbayar' => $trend['total_terbayar'] ?? 0,
        'belum' => $trend['total_belum'] ?? 0,
        'total' => ($trend['total_terbayar'] ?? 0) + ($trend['total_belum'] ?? 0)
    ];
}

// Get top customers
$query_top = "SELECT 
                p.nama as nama_pelanggan,
                p.id_pelanggan,
                COUNT(b.id) as total_tagihan,
                SUM(b.jumlah) as total_nominal,
                SUM(CASE WHEN b.status = 'lunas' THEN b.jumlah ELSE 0 END) as total_dibayar
              FROM billing b
              LEFT JOIN pelanggan p ON b.pelanggan_id = p.id
              WHERE b.tahun = :tahun
              GROUP BY b.pelanggan_id
              ORDER BY total_nominal DESC
              LIMIT 10";
$stmt_top = $db->prepare($query_top);
$stmt_top->bindParam(':tahun', $tahun);
$stmt_top->execute();
$top_customers = $stmt_top->fetchAll(PDO::FETCH_ASSOC);

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-file-invoice"></i> Laporan Tagihan
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
                <i class="fas fa-filter"></i> Filter Laporan
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
                        <a href="tagihan.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Reset
                        </a>
                        <a href="?bulan=<?php echo date('m'); ?>&tahun=<?php echo date('Y'); ?>" class="btn btn-info">
                            <i class="fas fa-calendar-alt"></i> Bulan Ini
                        </a>
                        <a href="?bulan=<?php echo date('m'); ?>&tahun=<?php echo date('Y') - 1; ?>" class="btn btn-info">
                            <i class="fas fa-calendar-alt"></i> Tahun Lalu
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Tagihan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_tagihan, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun)); ?>
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
                                Total Terbayar</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_terbayar, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo $total_tagihan > 0 ? number_format(($total_terbayar / $total_tagihan) * 100, 1) : 0; ?>% dari total
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
                                Belum Terbayar</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_belum_terbayar, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo $total_tagihan > 0 ? number_format(($total_belum_terbayar / $total_tagihan) * 100, 1) : 0; ?>% dari total
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
                                Jumlah Tagihan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count($tagihan_data); ?> Tagihan
                            </div>
                            <div class="small text-muted">
                                <?php 
                                $lunas_count = count(array_filter($tagihan_data, function($item) {
                                    return $item['status'] == 'lunas';
                                }));
                                echo $lunas_count . ' Lunas, ' . (count($tagihan_data) - $lunas_count) . ' Belum Lunas';
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-receipt fa-2x text-gray-300"></i>
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
                        <i class="fas fa-chart-pie"></i> Status Tagihan
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="statusPieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line"></i> Tren Tagihan <?php echo $tahun; ?>
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

    <!-- Top Customers -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-trophy"></i> Top 10 Pelanggan (Berdasarkan Total Tagihan)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>ID Pelanggan</th>
                                    <th>Nama Pelanggan</th>
                                    <th class="text-end">Total Tagihan</th>
                                    <th class="text-end">Total Dibayar</th>
                                    <th class="text-end">Sisa</th>
                                    <th>Performa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach($top_customers as $customer): 
                                    $sisa = $customer['total_nominal'] - $customer['total_dibayar'];
                                    $persentase_bayar = $customer['total_nominal'] > 0 ? ($customer['total_dibayar'] / $customer['total_nominal']) * 100 : 0;
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($customer['id_pelanggan']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['nama_pelanggan']); ?></td>
                                    <td class="text-end">Rp <?php echo number_format($customer['total_nominal'], 0, ',', '.'); ?></td>
                                    <td class="text-end text-success">Rp <?php echo number_format($customer['total_dibayar'], 0, ',', '.'); ?></td>
                                    <td class="text-end <?php echo $sisa > 0 ? 'text-danger' : 'text-success'; ?>">
                                        Rp <?php echo number_format($sisa, 0, ',', '.'); ?>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $persentase_bayar >= 90 ? 'success' : ($persentase_bayar >= 50 ? 'warning' : 'danger'); ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $persentase_bayar; ?>%;"
                                                 aria-valuenow="<?php echo $persentase_bayar; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo number_format($persentase_bayar, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($top_customers)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Belum ada data tagihan</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Tagihan Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i> Detail Tagihan
                        <span class="badge bg-primary ms-2">Periode: <?php echo date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun)); ?></span>
                        <?php if($status): ?>
                        <span class="badge bg-info ms-2">Status: <?php echo $status == 'lunas' ? 'Lunas' : 'Belum Lunas'; ?></span>
                        <?php endif; ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="tagihanTable">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>ID Pelanggan</th>
                                    <th>Nama Pelanggan</th>
                                    <th>Paket Internet</th>
                                    <th>Jumlah Tagihan</th>
                                    <th>Status</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Tanggal Bayar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach($tagihan_data as $row): ?>
                                <tr class="<?php echo $row['status'] == 'belum_lunas' && strtotime($row['tanggal_jatuh_tempo']) < time() ? 'table-danger' : ''; ?>">
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($row['id_pelanggan']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_pelanggan']); ?></td>
                                    <td><?php echo htmlspecialchars($row['paket_internet']); ?></td>
                                    <td class="text-end fw-bold">Rp <?php echo number_format($row['jumlah'], 0, ',', '.'); ?></td>
                                    <td class="text-center">
                                        <?php if($row['status'] == 'lunas'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle"></i> Lunas
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-clock"></i> Belum Lunas
                                            </span>
                                            <?php if(strtotime($row['tanggal_jatuh_tempo']) < time()): ?>
                                                <br><small class="text-danger">(Terlambat)</small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if($row['tanggal_jatuh_tempo']): ?>
                                            <?php echo date('d/m/Y', strtotime($row['tanggal_jatuh_tempo'])); ?>
                                            <?php if(strtotime($row['tanggal_jatuh_tempo']) < time() && $row['status'] == 'belum_lunas'): ?>
                                                <br><small class="text-danger">
                                                    <?php 
                                                    $tgl_jatuh = new DateTime($row['tanggal_jatuh_tempo']);
                                                    $tgl_sekarang = new DateTime();
                                                    $selisih = $tgl_jatuh->diff($tgl_sekarang);
                                                    echo 'Terlambat ' . $selisih->days . ' hari';
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if($row['tanggal_bayar']): ?>
                                            <?php echo date('d/m/Y', strtotime($row['tanggal_bayar'])); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($tagihan_data)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                        <p>Tidak ada data tagihan untuk periode yang dipilih</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="4" class="text-end">Total Keseluruhan: </td>
                                    <td class="text-end text-primary">Rp <?php echo number_format($total_tagihan, 0, ',', '.'); ?></td>
                                    <td colspan="3"></td>
                                </tr>
                                <tr class="fw-bold">
                                    <td colspan="4" class="text-end">Total Lunas: </td>
                                    <td class="text-end text-success">Rp <?php echo number_format($total_terbayar, 0, ',', '.'); ?></td>
                                    <td colspan="3"></td>
                                </tr>
                                <tr class="fw-bold">
                                    <td colspan="4" class="text-end">Total Belum Lunas: </td>
                                    <td class="text-end text-danger">Rp <?php echo number_format($total_belum_terbayar, 0, ',', '.'); ?></td>
                                    <td colspan="3"></td>
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
const chartData = <?php echo json_encode($chart_data); ?>;
const trendData = <?php echo json_encode($trend_data); ?>;

// Status Pie Chart
const ctx1 = document.getElementById('statusPieChart').getContext('2d');
const lunasData = chartData.find(item => item.status === 'lunas');
const belumData = chartData.find(item => item.status === 'belum_lunas');

new Chart(ctx1, {
    type: 'pie',
    data: {
        labels: ['Lunas', 'Belum Lunas'],
        datasets: [{
            data: [
                lunasData ? lunasData.total_nominal : 0,
                belumData ? belumData.total_nominal : 0
            ],
            backgroundColor: ['#1cc88a', '#f6c23e'],
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
                        return `${label}: Rp ${value.toLocaleString('id-ID')} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Trend Chart
const ctx2 = document.getElementById('trendChart').getContext('2d');
new Chart(ctx2, {
    type: 'line',
    data: {
        labels: trendData.map(item => item.nama_bulan.substring(0, 3)),
        datasets: [
            {
                label: 'Total Terbayar',
                data: trendData.map(item => item.terbayar),
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            },
            {
                label: 'Total Belum Bayar',
                data: trendData.map(item => item.belum),
                borderColor: '#f6c23e',
                backgroundColor: 'rgba(246, 194, 62, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
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

// Export to Excel
function exportToExcel() {
    let table = document.getElementById('tagihanTable');
    let html = table.outerHTML;
    
    const bulan = document.querySelector('select[name="bulan"]').value;
    const tahun = document.querySelector('select[name="tahun"]').value;
    const bulanNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    
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
            <p>Periode: ${bulanNames[parseInt(bulan) - 1]} ${tahun}</p>
            <p>Status: ${document.querySelector('select[name="status"]').value || 'Semua'}</p>
            <p>Tanggal Export: ${new Date().toLocaleString()}</p>
            <p><strong>Ringkasan:</strong></p>
            <p>Total Tagihan: Rp <?php echo number_format($total_tagihan, 0, ',', '.'); ?></p>
            <p>Total Terbayar: Rp <?php echo number_format($total_terbayar, 0, ',', '.'); ?></p>
            <p>Total Belum Terbayar: Rp <?php echo number_format($total_belum_terbayar, 0, ',', '.'); ?></p>
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

// Export to PDF (print)
function exportToPDF() {
    window.print();
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

.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

.progress {
    background-color: #e9ecef;
    border-radius: 5px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.6s ease;
    line-height: 20px;
    color: white;
    font-size: 11px;
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