<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../../config/database.php';
require_once '../../models/Pelanggan.php';
require_once '../../models/Billing.php';
require_once '../../models/Pemasukan.php';
require_once '../../includes/header.php';

// Get user role
$user_role = $_SESSION['role'];

$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$pelanggan_id = (int)$_GET['id'];

// Get customer detail with join to paket, pop, odp
$query = "SELECT p.*, 
          pk.nama_paket as paket_nama, pk.kecepatan as paket_kecepatan,
          po.nama_pop, po.kode_pop, po.lokasi as pop_lokasi, po.latitude as pop_lat, po.longitude as pop_lng,
          od.nama_odp, od.kode_odp, od.alamat as odp_alamat, od.jumlah_port, od.port_terpakai,
          od.latitude as odp_lat, od.longitude as odp_lng
          FROM pelanggan p
          LEFT JOIN paket_internet pk ON p.paket_id = pk.id
          LEFT JOIN pop po ON p.pop_id = po.id
          LEFT JOIN odp od ON p.odp_id = od.id
          WHERE p.id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $pelanggan_id);
$stmt->execute();
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$customer) {
    $_SESSION['error'] = "Data pelanggan tidak ditemukan!";
    header("Location: index.php");
    exit();
}

// Get billing history for this customer
$billing_query = "SELECT * FROM billing 
                  WHERE pelanggan_id = :pelanggan_id 
                  ORDER BY tahun DESC, bulan DESC 
                  LIMIT 12";
$billing_stmt = $db->prepare($billing_query);
$billing_stmt->bindParam(':pelanggan_id', $pelanggan_id);
$billing_stmt->execute();
$billing_history = $billing_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment history
$payment_query = "SELECT * FROM pemasukan 
                  WHERE pelanggan_id = :pelanggan_id 
                  ORDER BY tanggal DESC 
                  LIMIT 10";
$payment_stmt = $db->prepare($payment_query);
$payment_stmt->bindParam(':pelanggan_id', $pelanggan_id);
$payment_stmt->execute();
$payment_history = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_tagihan = 0;
$total_bayar = 0;
$total_belum_bayar = 0;

foreach($billing_history as $bill) {
    $total_tagihan += $bill['jumlah'];
    if($bill['status'] == 'lunas') {
        $total_bayar += $bill['jumlah'];
    } else {
        $total_belum_bayar += $bill['jumlah'];
    }
}

// Get latest billing status
$latest_billing = !empty($billing_history) ? $billing_history[0] : null;
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user-circle"></i> Detail Pelanggan
        </h1>
        <div>
            <?php if($user_role == 'admin' || $user_role == 'operator'): ?>
            <a href="edit.php?id=<?php echo $customer['id']; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit Pelanggan
            </a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <button class="btn btn-info" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <div class="row">
        <!-- Customer Info Card -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i> Informasi Pelanggan
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-circle fa-5x text-gray-300"></i>
                        <h4 class="mt-2"><?php echo htmlspecialchars($customer['nama']); ?></h4>
                        <span class="badge bg-<?php echo $customer['status'] == 'aktif' ? 'success' : 'danger'; ?>">
                            <?php echo $customer['status'] == 'aktif' ? 'Aktif' : 'Nonaktif'; ?>
                        </span>
                    </div>
                    
                    <table class="table table-sm">
                        <tr>
                            <td width="35%"><strong>ID Pelanggan</strong></td>
                            <td width="5%">:</td>
                            <td><code><?php echo $customer['id_pelanggan']; ?></code></td>
                        </tr>
                        <tr>
                            <td><strong>Nama Lengkap</strong></td>
                            <td>:</td>
                            <td><?php echo htmlspecialchars($customer['nama']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Nomor HP</strong></td>
                            <td>:</td>
                            <td><?php echo htmlspecialchars($customer['no_hp']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Alamat</strong></td>
                            <td>:</td>
                            <td><?php echo nl2br(htmlspecialchars($customer['alamat'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Paket Internet</strong></td>
                            <td>:</td>
                            <td>
                                <strong><?php echo htmlspecialchars($customer['paket_nama'] ?? $customer['paket_internet']); ?></strong>
                                <?php if($customer['paket_kecepatan']): ?>
                                    <br><small class="text-muted"><?php echo $customer['paket_kecepatan']; ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Harga Paket</strong></td>
                            <td>:</td>
                            <td class="text-primary">Rp <?php echo number_format($customer['harga_paket'], 0, ',', '.'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Tanggal Daftar</strong></td>
                            <td>:</td>
                            <td><?php echo date('d/m/Y H:i', strtotime($customer['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Terakhir Update</strong></td>
                            <td>:</td>
                            <td><?php echo date('d/m/Y H:i', strtotime($customer['updated_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Statistics Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line"></i> Statistik Tagihan
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="h5 text-primary"><?php echo count($billing_history); ?></div>
                            <div class="small text-muted">Total Tagihan</div>
                        </div>
                        <div class="col-4">
                            <div class="h5 text-success">Rp <?php echo number_format($total_bayar, 0, ',', '.'); ?></div>
                            <div class="small text-muted">Total Dibayar</div>
                        </div>
                        <div class="col-4">
                            <div class="h5 text-danger">Rp <?php echo number_format($total_belum_bayar, 0, ',', '.'); ?></div>
                            <div class="small text-muted">Sisa Hutang</div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <div class="h4">
                            <?php 
                            $persentase = $total_tagihan > 0 ? ($total_bayar / $total_tagihan) * 100 : 0;
                            ?>
                            <span class="text-<?php echo $persentase >= 90 ? 'success' : ($persentase >= 50 ? 'warning' : 'danger'); ?>">
                                <?php echo number_format($persentase, 1); ?>%
                            </span>
                        </div>
                        <div class="small text-muted">Tingkat Pembayaran</div>
                        <div class="progress mt-2" style="height: 8px;">
                            <div class="progress-bar bg-<?php echo $persentase >= 90 ? 'success' : ($persentase >= 50 ? 'warning' : 'danger'); ?>" 
                                 style="width: <?php echo $persentase; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Infrastructure Info Card -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-network-wired"></i> Informasi Infrastruktur
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- POP Information -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-left-primary">
                                <div class="card-body">
                                    <h6 class="card-title text-primary">
                                        <i class="fas fa-broadcast-tower"></i> POP (Point of Presence)
                                    </h6>
                                    <?php if($customer['nama_pop']): ?>
                                        <table class="table table-sm">
                                            <tr>
                                                <td width="35%"><strong>Kode POP</strong></td>
                                                <td width="5%">:</td>
                                                <td><code><?php echo htmlspecialchars($customer['kode_pop']); ?></code></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Nama POP</strong></td>
                                                <td>:</td>
                                                <td><?php echo htmlspecialchars($customer['nama_pop']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Lokasi</strong></td>
                                                <td>:</td>
                                                <td><?php echo htmlspecialchars($customer['pop_lokasi'] ?? '-'); ?></td>
                                            </tr>
                                            <?php if($customer['pop_lat'] && $customer['pop_lng']): ?>
                                            <tr>
                                                <td><strong>Koordinat</strong></td>
                                                <td>:</td>
                                                <td>
                                                    <?php echo $customer['pop_lat']; ?>, <?php echo $customer['pop_lng']; ?>
                                                    <a href="https://www.google.com/maps?q=<?php echo $customer['pop_lat']; ?>,<?php echo $customer['pop_lng']; ?>" 
                                                       target="_blank" class="btn btn-sm btn-link">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    <?php else: ?>
                                        <p class="text-muted">Belum ditentukan</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ODP Information -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-left-info">
                                <div class="card-body">
                                    <h6 class="card-title text-info">
                                        <i class="fas fa-exchange-alt"></i> ODP (Optical Distribution Point)
                                    </h6>
                                    <?php if($customer['nama_odp']): ?>
                                        <table class="table table-sm">
                                            <tr>
                                                <td width="35%"><strong>Kode ODP</strong></td>
                                                <td width="5%">:</td>
                                                <td><code><?php echo htmlspecialchars($customer['kode_odp']); ?></code></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Nama ODP</strong></td>
                                                <td>:</td>
                                                <td><?php echo htmlspecialchars($customer['nama_odp']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Alamat</strong></td>
                                                <td>:</td>
                                                <td><?php echo htmlspecialchars($customer['odp_alamat'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Port Status</strong></td>
                                                <td>:</td>
                                                <td>
                                                    <?php echo $customer['port_terpakai']; ?>/<?php echo $customer['jumlah_port']; ?> terpakai
                                                    <div class="progress mt-1" style="height: 5px;">
                                                        <div class="progress-bar bg-<?php echo ($customer['port_terpakai'] / $customer['jumlah_port']) * 100 > 80 ? 'danger' : 'success'; ?>" 
                                                             style="width: <?php echo ($customer['port_terpakai'] / $customer['jumlah_port']) * 100; ?>%;"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php if($customer['odp_lat'] && $customer['odp_lng']): ?>
                                            <tr>
                                                <td><strong>Koordinat</strong></td>
                                                <td>:</td>
                                                <td>
                                                    <?php echo $customer['odp_lat']; ?>, <?php echo $customer['odp_lng']; ?>
                                                    <a href="https://www.google.com/maps?q=<?php echo $customer['odp_lat']; ?>,<?php echo $customer['odp_lng']; ?>" 
                                                       target="_blank" class="btn btn-sm btn-link">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    <?php else: ?>
                                        <p class="text-muted">Belum ditentukan</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Location Map -->
                    <?php if($customer['latitude'] && $customer['longitude']): ?>
                    <div class="mt-3">
                        <h6 class="font-weight-bold">
                            <i class="fas fa-map-marked-alt"></i> Lokasi Pelanggan
                        </h6>
                        <div id="customerMap" style="height: 300px; border-radius: 8px;"></div>
                        <div class="text-center mt-2">
                            <a href="https://www.google.com/maps?q=<?php echo $customer['latitude']; ?>,<?php echo $customer['longitude']; ?>" 
                               target="_blank" class="btn btn-sm btn-primary">
                                <i class="fas fa-external-link-alt"></i> Buka di Google Maps
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Billing History -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-file-invoice"></i> Riwayat Tagihan
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Periode</th>
                            <th class="text-end">Jumlah Tagihan</th>
                            <th>Status</th>
                            <th>Tanggal Bayar</th>
                            <th>Jatuh Tempo</th>
                        </thead>
                    <tbody>
                        <?php foreach($billing_history as $bill): ?>
                        <tr class="<?php echo $bill['status'] == 'belum_lunas' && strtotime($bill['tanggal_jatuh_tempo']) < time() ? 'table-danger' : ''; ?>">
                            <td><?php echo $bill['bulan']; ?>/<?php echo $bill['tahun']; ?></td>
                            <td class="text-end">Rp <?php echo number_format($bill['jumlah'], 0, ',', '.'); ?></td>
                            <td>
                                <?php if($bill['status'] == 'lunas'): ?>
                                    <span class="badge bg-success">Lunas</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Belum Lunas</span>
                                <?php endif; ?>
                             </td>
                            <td><?php echo $bill['tanggal_bayar'] ? date('d/m/Y', strtotime($bill['tanggal_bayar'])) : '-'; ?></td>
                            <td><?php echo $bill['tanggal_jatuh_tempo'] ? date('d/m/Y', strtotime($bill['tanggal_jatuh_tempo'])) : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($billing_history)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">Belum ada data tagihan</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td class="text-end">Total:</td>
                            <td class="text-end text-primary">Rp <?php echo number_format($total_tagihan, 0, ',', '.'); ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Payment History -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-success">
                <i class="fas fa-history"></i> Riwayat Pembayaran
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th class="text-end">Jumlah</th>
                            <th>Keterangan</th>
                        </thead>
                    <tbody>
                        <?php foreach($payment_history as $payment): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($payment['tanggal'])); ?></td>
                            <td class="text-end text-success">Rp <?php echo number_format($payment['jumlah'], 0, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($payment['keterangan']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($payment_history)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">Belum ada riwayat pembayaran</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td class="text-end">Total:</td>
                            <td class="text-end text-primary">Rp <?php echo number_format($total_bayar, 0, ',', '.'); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet CSS and JS for Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Initialize customer location map
<?php if($customer['latitude'] && $customer['longitude']): ?>
var customerMap = L.map('customerMap').setView([<?php echo $customer['latitude']; ?>, <?php echo $customer['longitude']; ?>], 15);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(customerMap);

// Add marker for customer location
var customerIcon = L.divIcon({
    html: '<div style="background-color: #e74a3b; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
    iconSize: [16, 16],
    popupAnchor: [0, -8]
});

var marker = L.marker([<?php echo $customer['latitude']; ?>, <?php echo $customer['longitude']; ?>], { icon: customerIcon }).addTo(customerMap);
marker.bindPopup('<strong><?php echo addslashes($customer['nama']); ?></strong><br><?php echo addslashes($customer['alamat']); ?>');

// Add POP marker if available
<?php if($customer['pop_lat'] && $customer['pop_lng']): ?>
var popIcon = L.divIcon({
    html: '<div style="background-color: #4e73df; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
    iconSize: [16, 16],
    popupAnchor: [0, -8]
});
var popMarker = L.marker([<?php echo $customer['pop_lat']; ?>, <?php echo $customer['pop_lng']; ?>], { icon: popIcon }).addTo(customerMap);
popMarker.bindPopup('<strong>POP: <?php echo addslashes($customer['nama_pop']); ?></strong><br><?php echo addslashes($customer['pop_lokasi']); ?>');
<?php endif; ?>

// Add ODP marker if available
<?php if($customer['odp_lat'] && $customer['odp_lng']): ?>
var odpIcon = L.divIcon({
    html: '<div style="background-color: #f6c23e; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
    iconSize: [16, 16],
    popupAnchor: [0, -8]
});
var odpMarker = L.marker([<?php echo $customer['odp_lat']; ?>, <?php echo $customer['odp_lng']; ?>], { icon: odpIcon }).addTo(customerMap);
odpMarker.bindPopup('<strong>ODP: <?php echo addslashes($customer['nama_odp']); ?></strong><br><?php echo addslashes($customer['odp_alamat']); ?>');
<?php endif; ?>

// Fit bounds to show all markers
var bounds = L.latLngBounds([]);
bounds.extend([<?php echo $customer['latitude']; ?>, <?php echo $customer['longitude']; ?>]);
<?php if($customer['pop_lat'] && $customer['pop_lng']): ?>
bounds.extend([<?php echo $customer['pop_lat']; ?>, <?php echo $customer['pop_lng']; ?>]);
<?php endif; ?>
<?php if($customer['odp_lat'] && $customer['odp_lng']): ?>
bounds.extend([<?php echo $customer['odp_lat']; ?>, <?php echo $customer['odp_lng']; ?>]);
<?php endif; ?>
customerMap.fitBounds(bounds, { padding: [50, 50] });
<?php endif; ?>
</script>

<style>
.border-left-primary { border-left: 4px solid #4e73df !important; }
.border-left-info { border-left: 4px solid #36b9cc !important; }
.table th, .table td {
    vertical-align: middle;
}
.progress {
    background-color: #e9ecef;
    border-radius: 5px;
    overflow: hidden;
}
@media print {
    .btn, .pagination, .alert, form, .card-header .btn, .card-footer, .no-print {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
        break-inside: avoid;
    }
    .table {
        font-size: 10px;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>