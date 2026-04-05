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

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize model
$material = new Material($db);

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$min_stock = isset($_GET['min_stock']) ? (int)$_GET['min_stock'] : 0;
$max_stock = isset($_GET['max_stock']) ? (int)$_GET['max_stock'] : 999999;

// Get all materials with filter
$query = "SELECT * FROM material WHERE 1=1";
if(!empty($search)) {
    $query .= " AND (nama_material LIKE :search OR keterangan LIKE :search)";
}
if($min_stock > 0) {
    $query .= " AND stok >= :min_stock";
}
if($max_stock < 999999) {
    $query .= " AND stok <= :max_stock";
}
$query .= " ORDER BY stok ASC, nama_material ASC";

$stmt = $db->prepare($query);

if(!empty($search)) {
    $searchTerm = "%{$search}%";
    $stmt->bindParam(':search', $searchTerm);
}
if($min_stock > 0) {
    $stmt->bindParam(':min_stock', $min_stock, PDO::PARAM_INT);
}
if($max_stock < 999999) {
    $stmt->bindParam(':max_stock', $max_stock, PDO::PARAM_INT);
}

$stmt->execute();
$material_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_material = count($material_data);
$total_stok = 0;
$total_nilai = 0;
$low_stock_count = 0;
$out_of_stock_count = 0;
$total_harga = 0;

foreach($material_data as $row) {
    $total_stok += $row['stok'];
    $nilai = $row['stok'] * $row['harga'];
    $total_nilai += $nilai;
    $total_harga += $row['harga'];
    
    if($row['stok'] < 10 && $row['stok'] > 0) {
        $low_stock_count++;
    } elseif($row['stok'] == 0) {
        $out_of_stock_count++;
    }
}

// Get stock distribution for chart
$stock_ranges = [
    'Habis (0)' => 0,
    'Rendah (1-10)' => 0,
    'Sedang (11-50)' => 0,
    'Tinggi (51-100)' => 0,
    'Sangat Tinggi (>100)' => 0
];

foreach($material_data as $row) {
    if($row['stok'] == 0) {
        $stock_ranges['Habis (0)']++;
    } elseif($row['stok'] <= 10) {
        $stock_ranges['Rendah (1-10)']++;
    } elseif($row['stok'] <= 50) {
        $stock_ranges['Sedang (11-50)']++;
    } elseif($row['stok'] <= 100) {
        $stock_ranges['Tinggi (51-100)']++;
    } else {
        $stock_ranges['Sangat Tinggi (>100)']++;
    }
}

// Get top 5 most expensive materials
$query_expensive = "SELECT nama_material, harga FROM material ORDER BY harga DESC LIMIT 5";
$stmt_expensive = $db->prepare($query_expensive);
$stmt_expensive->execute();
$expensive_materials = $stmt_expensive->fetchAll(PDO::FETCH_ASSOC);

// Get top 5 highest stock materials
$query_highest = "SELECT nama_material, stok FROM material ORDER BY stok DESC LIMIT 5";
$stmt_highest = $db->prepare($query_highest);
$stmt_highest->execute();
$highest_stock = $stmt_highest->fetchAll(PDO::FETCH_ASSOC);

// Get top 5 lowest stock materials (excluding zero)
$query_lowest = "SELECT nama_material, stok FROM material WHERE stok > 0 ORDER BY stok ASC LIMIT 5";
$stmt_lowest = $db->prepare($query_lowest);
$stmt_lowest->execute();
$lowest_stock = $stmt_lowest->fetchAll(PDO::FETCH_ASSOC);

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-boxes"></i> Laporan Data Material
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
                <div class="col-md-4">
                    <label class="form-label">Cari Material</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Nama material atau keterangan"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Stok Minimal</label>
                    <input type="number" name="min_stock" class="form-control" 
                           placeholder="Minimal stok"
                           value="<?php echo $min_stock > 0 ? $min_stock : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Stok Maksimal</label>
                    <input type="number" name="max_stock" class="form-control" 
                           placeholder="Maksimal stok"
                           value="<?php echo $max_stock < 999999 ? $max_stock : ''; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Tampilkan
                        </button>
                        <a href="material.php" class="btn btn-secondary">
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
                                Total Material</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_material); ?>
                            </div>
                            <div class="small text-muted">
                                Jenis material tersedia
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
                            <div class="small text-muted">
                                Unit material
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
                                Total Nilai Material</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($total_nilai, 0, ',', '.'); ?>
                            </div>
                            <div class="small text-muted">
                                Nilai seluruh stok
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
                                Rata-rata Harga</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo $total_material > 0 ? number_format($total_harga / $total_material, 0, ',', '.') : '0'; ?>
                            </div>
                            <div class="small text-muted">
                                Per jenis material
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

    <!-- Additional Statistics Row -->
    <div class="row">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Stok Menipis</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($low_stock_count); ?>
                            </div>
                            <div class="small text-muted">
                                Material dengan stok < 10
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                                Stok Habis</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($out_of_stock_count); ?>
                            </div>
                            <div class="small text-muted">
                                Perlu segera di-restock
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-ban fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Stok Tersedia</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_material - $out_of_stock_count); ?>
                            </div>
                            <div class="small text-muted">
                                Material dengan stok > 0
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                        <i class="fas fa-chart-pie"></i> Distribusi Stok Material
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="stockChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar"></i> Top 5 Material Termahal
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="expensiveChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Status Cards -->
    <div class="row">
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-arrow-up"></i> 5 Material dengan Stok Tertinggi
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Material</th>
                                    <th class="text-end">Stok</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach($highest_stock as $item): ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($item['nama_material']); ?></td>
                                    <td class="text-end fw-bold text-success">
                                        <?php echo number_format($item['stok']); ?> unit
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($highest_stock)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">Tidak ada data</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-warning">
                        <i class="fas fa-arrow-down"></i> 5 Material dengan Stok Terendah
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Material</th>
                                    <th class="text-end">Stok</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach($lowest_stock as $item): ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($item['nama_material']); ?></td>
                                    <td class="text-end fw-bold text-warning">
                                        <?php echo number_format($item['stok']); ?> unit
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($lowest_stock)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">Tidak ada data</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Material Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i> Detail Data Material
                        <?php if($search): ?>
                        <span class="badge bg-info ms-2">Pencarian: <?php echo htmlspecialchars($search); ?></span>
                        <?php endif; ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="materialTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="25%">Nama Material</th>
                                    <th width="10%">Stok</th>
                                    <th width="15%">Harga Satuan</th>
                                    <th width="20%">Total Nilai</th>
                                    <th width="25%">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach($material_data as $row): 
                                    $total_nilai_item = $row['stok'] * $row['harga'];
                                    $stock_class = '';
                                    $stock_badge = '';
                                    if($row['stok'] == 0) {
                                        $stock_class = 'text-danger fw-bold';
                                        $stock_badge = '<span class="badge bg-danger ms-2">Habis</span>';
                                    } elseif($row['stok'] < 10) {
                                        $stock_class = 'text-warning fw-bold';
                                        $stock_badge = '<span class="badge bg-warning ms-2">Menipis</span>';
                                    }
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['nama_material']); ?>
                                        <?php echo $stock_badge; ?>
                                    </td>
                                    <td class="text-center <?php echo $stock_class; ?>">
                                        <?php echo number_format($row['stok']); ?> unit
                                    </td>
                                    <td class="text-end">
                                        Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-end">
                                        Rp <?php echo number_format($total_nilai_item, 0, ',', '.'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['keterangan'] ?: '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($material_data)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                        <p>Tidak ada data material yang ditemukan</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="2" class="text-end">Total Keseluruhan:</td>
                                    <td class="text-center"><?php echo number_format($total_stok); ?> unit</td>
                                    <td class="text-end">-</td>
                                    <td class="text-end text-primary">
                                        Rp <?php echo number_format($total_nilai, 0, ',', '.'); ?>
                                    </td>
                                    <td></td>
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
// Stock Distribution Chart Data
const stockRanges = <?php echo json_encode($stock_ranges); ?>;
const stockLabels = Object.keys(stockRanges);
const stockValues = Object.values(stockRanges);

// Stock Distribution Pie Chart
const ctx1 = document.getElementById('stockChart').getContext('2d');
const stockColors = ['#e74a3b', '#f6c23e', '#36b9cc', '#1cc88a', '#4e73df'];

new Chart(ctx1, {
    type: 'pie',
    data: {
        labels: stockLabels,
        datasets: [{
            data: stockValues,
            backgroundColor: stockColors.slice(0, stockLabels.length),
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
                        return `${label}: ${value} material (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Top 5 Most Expensive Materials Chart
const expensiveLabels = <?php echo json_encode(array_column($expensive_materials, 'nama_material')); ?>;
const expensivePrices = <?php echo json_encode(array_column($expensive_materials, 'harga')); ?>;

if(expensiveLabels.length > 0) {
    const ctx2 = document.getElementById('expensiveChart').getContext('2d');
    new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: expensiveLabels,
            datasets: [{
                label: 'Harga (Rp)',
                data: expensivePrices,
                backgroundColor: '#4e73df',
                borderColor: '#2e59d9',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Rp ' + context.parsed.y.toLocaleString('id-ID');
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
                    },
                    title: {
                        display: true,
                        text: 'Harga (Rp)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Nama Material'
                    }
                }
            }
        }
    });
}

// Export to Excel
function exportToExcel() {
    let table = document.getElementById('materialTable');
    let html = table.outerHTML;
    
    const searchInput = document.querySelector('input[name="search"]');
    const search = searchInput ? searchInput.value || 'Semua' : 'Semua';
    
    html = `
        <html>
        <head>
            <title>Laporan Data Material</title>
            <style>
                th { background-color: #4e73df; color: white; }
                td { border: 1px solid #ddd; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .text-success { color: #1cc88a; }
                .text-warning { color: #f6c23e; }
                .text-danger { color: #e74a3b; }
            </style>
        </head>
        <body>
            <h2>Laporan Data Material</h2>
            <p>Pencarian: ${search}</p>
            <p>Tanggal Export: ${new Date().toLocaleString()}</p>
            <p><strong>Ringkasan:</strong></p>
            <p>Total Material: <?php echo $total_material; ?></p>
            <p>Total Stok: <?php echo number_format($total_stok); ?> unit</p>
            <p>Total Nilai: Rp <?php echo number_format($total_nilai, 0, ',', '.'); ?></p>
            <p>Stok Menipis: <?php echo $low_stock_count; ?> material</p>
            <p>Stok Habis: <?php echo $out_of_stock_count; ?> material</p>
            ${html}
        </body>
        </html>
    `;
    
    let blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    let link = document.createElement('a');
    let url = URL.createObjectURL(blob);
    
    link.href = url;
    link.download = 'laporan_material.xls';
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
.border-left-warning { border-left: 4px solid #f6c23e !important; }
.border-left-danger { border-left: 4px solid #e74a3b !important; }
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

.badge {
    font-size: 11px;
    padding: 4px 8px;
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