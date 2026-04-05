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
require_once '../../models/Pengeluaran.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize models
$pemasukan = new Pemasukan($db);
$pengeluaran = new Pengeluaran($db);

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'monthly';

// Get data
$total_pemasukan = $pemasukan->getTotalAmount($start_date, $end_date);
$total_pengeluaran = $pengeluaran->getTotalAmount($start_date, $end_date);
$saldo = $total_pemasukan - $total_pengeluaran;

// Get detailed data for table
$stmt_pemasukan = $pemasukan->read($start_date, $end_date, 9999, 0);
$pemasukan_data = $stmt_pemasukan->fetchAll(PDO::FETCH_ASSOC);

$stmt_pengeluaran = $pengeluaran->read($start_date, $end_date, 9999, 0);
$pengeluaran_data = $stmt_pengeluaran->fetchAll(PDO::FETCH_ASSOC);

// Get daily data for chart
$daily_data = [];
$current_date = new DateTime($start_date);
$end = new DateTime($end_date);
$end->modify('+1 day');

while($current_date < $end) {
    $date_str = $current_date->format('Y-m-d');
    $pemasukan_harian = $pemasukan->getTotalAmount($date_str, $date_str);
    $pengeluaran_harian = $pengeluaran->getTotalAmount($date_str, $date_str);
    
    $daily_data[] = [
        'tanggal' => $date_str,
        'pemasukan' => $pemasukan_harian,
        'pengeluaran' => $pengeluaran_harian,
        'saldo' => $pemasukan_harian - $pengeluaran_harian
    ];
    
    $current_date->modify('+1 day');
}

// Get monthly data for yearly report
$monthly_data = [];
$current_year = date('Y');
for($month = 1; $month <= 12; $month++) {
    $month_start = date("$current_year-$month-01");
    $month_end = date("$current_year-$month-t");
    $pemasukan_bulanan = $pemasukan->getTotalAmount($month_start, $month_end);
    $pengeluaran_bulanan = $pengeluaran->getTotalAmount($month_start, $month_end);
    
    $monthly_data[] = [
        'bulan' => $month,
        'nama_bulan' => date('F', mktime(0, 0, 0, $month, 1)),
        'pemasukan' => $pemasukan_bulanan,
        'pengeluaran' => $pengeluaran_bulanan,
        'saldo' => $pemasukan_bulanan - $pengeluaran_bulanan
    ];
}

// Get category data for expense pie chart
$query = "SELECT jenis_pengeluaran, SUM(jumlah) as total 
          FROM pengeluaran 
          WHERE tanggal BETWEEN :start AND :end 
          GROUP BY jenis_pengeluaran 
          ORDER BY total DESC 
          LIMIT 10";
$stmt_cat = $db->prepare($query);
$stmt_cat->bindParam(':start', $start_date);
$stmt_cat->bindParam(':end', $end_date);
$stmt_cat->execute();
$expense_categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-chart-line"></i> Laporan Keuangan
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
                        <a href="keuangan.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Reset
                        </a>
                        <button type="button" class="btn btn-info" onclick="setThisMonth()">
                            <i class="fas fa-calendar-alt"></i> Bulan Ini
                        </button>
                        <button type="button" class="btn btn-info" onclick="setLastMonth()">
                            <i class="fas fa-calendar-alt"></i> Bulan Lalu
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Pemasukan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_pemasukan, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo date('d M Y', strtotime($start_date)); ?> - 
                                <?php echo date('d M Y', strtotime($end_date)); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Total Pengeluaran</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_pengeluaran, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo date('d M Y', strtotime($start_date)); ?> - 
                                <?php echo date('d M Y', strtotime($end_date)); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-<?php echo $saldo >= 0 ? 'success' : 'warning'; ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?php echo $saldo >= 0 ? 'success' : 'warning'; ?> text-uppercase mb-1">
                                Saldo / Laba Rugi</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span class="text-<?php echo $saldo >= 0 ? 'success' : 'danger'; ?>">
                                    Rp <?php echo number_format(abs($saldo), 0, ',', '.'); ?>
                                    <?php echo $saldo >= 0 ? '(Laba)' : '(Rugi)'; ?>
                                </span>
                            </div>
                            <div class="small text-muted">
                                <?php echo $saldo >= 0 ? 'Keuntungan bersih' : 'Kerugian bersih'; ?>
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

    <!-- Charts Row -->
    <div class="row">
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line"></i> Grafik Pemasukan vs Pengeluaran
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="incomeExpenseChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-danger">
                        <i class="fas fa-chart-pie"></i> Komposisi Pengeluaran
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="expensePieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Tables -->
    <div class="row">
        <div class="col-xl-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-list"></i> Detail Pemasukan
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Pelanggan</th>
                                    <th>Jumlah</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($pemasukan_data) > 0): ?>
                                    <?php foreach($pemasukan_data as $row): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_pelanggan'] ?? '-'); ?></td>
                                        <td class="text-end text-success">Rp <?php echo number_format($row['jumlah'], 0, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($row['keterangan'] ?: '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Tidak ada data pemasukan</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="2" class="text-end">Total Pemasukan:</td>
                                    <td class="text-end text-success">Rp <?php echo number_format($total_pemasukan, 0, ',', '.'); ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-danger">
                        <i class="fas fa-list"></i> Detail Pengeluaran
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jenis Pengeluaran</th>
                                    <th>Jumlah</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($pengeluaran_data) > 0): ?>
                                    <?php foreach($pengeluaran_data as $row): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['jenis_pengeluaran']); ?></td>
                                        <td class="text-end text-danger">Rp <?php echo number_format($row['jumlah'], 0, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($row['keterangan'] ?: '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Tidak ada data pengeluaran</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="2" class="text-end">Total Pengeluaran:</td>
                                    <td class="text-end text-danger">Rp <?php echo number_format($total_pengeluaran, 0, ',', '.'); ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Summary for Yearly Report -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar"></i> Rekap Bulanan Tahun <?php echo date('Y'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Bulan</th>
                                    <th class="text-end">Pemasukan</th>
                                    <th class="text-end">Pengeluaran</th>
                                    <th class="text-end">Saldo</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($monthly_data as $month): ?>
                                <tr>
                                    <td><?php echo $month['nama_bulan']; ?></td>
                                    <td class="text-end text-success">Rp <?php echo number_format($month['pemasukan'], 0, ',', '.'); ?></td>
                                    <td class="text-end text-danger">Rp <?php echo number_format($month['pengeluaran'], 0, ',', '.'); ?></td>
                                    <td class="text-end <?php echo $month['saldo'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        Rp <?php echo number_format(abs($month['saldo']), 0, ',', '.'); ?>
                                        <?php echo $month['saldo'] >= 0 ? '(Laba)' : '(Rugi)'; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $persentase = $month['pemasukan'] > 0 ? ($month['pengeluaran'] / $month['pemasukan']) * 100 : 0;
                                        if($persentase <= 50): ?>
                                            <span class="badge bg-success">Sangat Baik</span>
                                        <?php elseif($persentase <= 80): ?>
                                            <span class="badge bg-warning">Cukup</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Perlu Perhatian</span>
                                        <?php endif; ?>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

// Set date filter to last month
function setLastMonth() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
    
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

// Chart Data
const dailyData = <?php echo json_encode($daily_data); ?>;
const expenseCategories = <?php echo json_encode($expense_categories); ?>;
const monthlyData = <?php echo json_encode($monthly_data); ?>;

// Income vs Expense Line Chart
const ctx1 = document.getElementById('incomeExpenseChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: dailyData.map(item => item.tanggal),
        datasets: [
            {
                label: 'Pemasukan',
                data: dailyData.map(item => item.pemasukan),
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            },
            {
                label: 'Pengeluaran',
                data: dailyData.map(item => item.pengeluaran),
                borderColor: '#e74a3b',
                backgroundColor: 'rgba(231, 74, 59, 0.1)',
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

// Expense Pie Chart
const ctx2 = document.getElementById('expensePieChart').getContext('2d');
const pieColors = [
    '#e74a3b', '#f6c23e', '#4e73df', '#1cc88a', '#36b9cc',
    '#858796', '#5a5c69', '#6f42c1', '#fd7e14', '#20c997'
];

new Chart(ctx2, {
    type: 'pie',
    data: {
        labels: expenseCategories.map(item => item.jenis_pengeluaran),
        datasets: [{
            data: expenseCategories.map(item => item.total),
            backgroundColor: pieColors.slice(0, expenseCategories.length),
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    font: { size: 10 }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `${label}: Rp ${value.toLocaleString('id-ID')} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Export to Excel
function exportToExcel() {
    let html = `
        <html>
        <head>
            <title>Laporan Keuangan</title>
            <style>
                body { font-family: Arial, sans-serif; }
                h1 { color: #4e73df; }
                table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #4e73df; color: white; }
                .text-end { text-align: right; }
                .text-success { color: #1cc88a; }
                .text-danger { color: #e74a3b; }
            </style>
        </head>
        <body>
            <h1>Laporan Keuangan</h1>
            <p>Periode: ${document.querySelector('input[name="start_date"]').value} s/d ${document.querySelector('input[name="end_date"]').value}</p>
            <p>Tanggal Export: ${new Date().toLocaleString()}</p>
            
            <h2>Ringkasan Keuangan</h2>
            <table>
                <tr><th>Total Pemasukan</th><td class="text-end">Rp <?php echo number_format($total_pemasukan, 0, ',', '.'); ?></td></tr>
                <tr><th>Total Pengeluaran</th><td class="text-end">Rp <?php echo number_format($total_pengeluaran, 0, ',', '.'); ?></td></tr>
                <tr><th>Saldo / Laba Rugi</th><td class="text-end">Rp <?php echo number_format(abs($saldo), 0, ',', '.'); ?> (<?php echo $saldo >= 0 ? 'Laba' : 'Rugi'; ?>)</td></tr>
            </table>
            
            <h2>Detail Pemasukan</h2>
            <table>
                <tr><th>Tanggal</th><th>Pelanggan</th><th>Jumlah</th><th>Keterangan</th></tr>
                <?php foreach($pemasukan_data as $row): ?>
                <tr>
                    <td><?php echo $row['tanggal']; ?></td>
                    <td><?php echo htmlspecialchars($row['nama_pelanggan'] ?? '-'); ?></td>
                    <td class="text-end"><?php echo number_format($row['jumlah'], 0, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars($row['keterangan'] ?: '-'); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            
            <h2>Detail Pengeluaran</h2>
            <table>
                <tr><th>Tanggal</th><th>Jenis Pengeluaran</th><th>Jumlah</th><th>Keterangan</th></tr>
                <?php foreach($pengeluaran_data as $row): ?>
                <tr>
                    <td><?php echo $row['tanggal']; ?></td>
                    <td><?php echo htmlspecialchars($row['jenis_pengeluaran']); ?></td>
                    <td class="text-end"><?php echo number_format($row['jumlah'], 0, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars($row['keterangan'] ?: '-'); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </body>
        </html>
    `;
    
    let blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    let link = document.createElement('a');
    let url = URL.createObjectURL(blob);
    
    link.href = url;
    link.download = 'laporan_keuangan.xls';
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
.border-left-danger { border-left: 4px solid #e74a3b !important; }
.border-left-warning { border-left: 4px solid #f6c23e !important; }
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
}
</style>

<?php require_once '../../includes/footer.php'; ?>