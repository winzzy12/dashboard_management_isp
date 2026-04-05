<?php
require_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h2 class="mb-4">Laporan</h2>
    </div>
</div>

<div class="row">
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                <h5 class="card-title">Laporan Keuangan</h5>
                <p class="card-text">Laporan pemasukan dan pengeluaran per periode</p>
                <a href="keuangan.php" class="btn btn-primary">
                    <i class="fas fa-eye"></i> Lihat Laporan
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-file-invoice fa-3x text-success mb-3"></i>
                <h5 class="card-title">Laporan Tagihan</h5>
                <p class="card-text">Rekapitulasi tagihan per bulan</p>
                <a href="tagihan.php" class="btn btn-success">
                    <i class="fas fa-eye"></i> Lihat Laporan
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-users fa-3x text-info mb-3"></i>
                <h5 class="card-title">Laporan Pelanggan</h5>
                <p class="card-text">Data pelanggan aktif dan nonaktif</p>
                <a href="pelanggan.php" class="btn btn-info">
                    <i class="fas fa-eye"></i> Lihat Laporan
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-boxes fa-3x text-warning mb-3"></i>
                <h5 class="card-title">Laporan Material</h5>
                <p class="card-text">Stok dan nilai material</p>
                <a href="material.php" class="btn btn-warning">
                    <i class="fas fa-eye"></i> Lihat Laporan
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar"></i> Grafik Pemasukan vs Pengeluaran Tahun <?php echo date('Y'); ?>
                </h5>
            </div>
            <div class="card-body">
                <canvas id="yearlyChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie"></i> Komposisi Pengeluaran
                </h5>
            </div>
            <div class="card-body">
                <canvas id="expenseChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Yearly Chart
var ctx1 = document.getElementById('yearlyChart').getContext('2d');
var yearlyChart = new Chart(ctx1, {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        datasets: [{
            label: 'Pemasukan',
            data: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            backgroundColor: 'rgba(40, 167, 69, 0.2)',
            borderColor: 'rgba(40, 167, 69, 1)',
            borderWidth: 2
        }, {
            label: 'Pengeluaran',
            data: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            backgroundColor: 'rgba(220, 53, 69, 0.2)',
            borderColor: 'rgba(220, 53, 69, 1)',
            borderWidth: 2
        }]
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
        }
    }
});

// Expense Composition Chart
var ctx2 = document.getElementById('expenseChart').getContext('2d');
var expenseChart = new Chart(ctx2, {
    type: 'pie',
    data: {
        labels: ['Operasional', 'Gaji Karyawan', 'Pembelian Material', 'Biaya Internet', 'Lainnya'],
        datasets: [{
            data: [0, 0, 0, 0, 0],
            backgroundColor: [
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 99, 132, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        var label = context.label || '';
                        var value = context.parsed || 0;
                        return label + ': Rp ' + value.toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});

// Fetch data for charts
$.ajax({
    url: '../../controllers/api/laporan_api.php',
    type: 'GET',
    dataType: 'json',
    success: function(data) {
        if(data.yearly) {
            yearlyChart.data.datasets[0].data = data.yearly.pemasukan;
            yearlyChart.data.datasets[1].data = data.yearly.pengeluaran;
            yearlyChart.update();
        }
        if(data.expense_composition) {
            expenseChart.data.datasets[0].data = data.expense_composition;
            expenseChart.update();
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>