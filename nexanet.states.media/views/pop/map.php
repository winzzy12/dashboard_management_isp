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
require_once '../../models/Pop.php';
require_once '../../models/Odp.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize models
$pop = new Pop($db);
$odp = new Odp($db);

// Get all POPs with coordinates
$pops = $pop->getAll();
$pops_with_coords = [];
foreach($pops as $p) {
    if($p['latitude'] && $p['longitude']) {
        $pops_with_coords[] = $p;
    }
}

// Get all ODPs with coordinates
$odps = $odp->read('', '', 9999, 0);
$odps_with_coords = [];
while($odp_data = $odps->fetch(PDO::FETCH_ASSOC)) {
    if($odp_data['latitude'] && $odp_data['longitude']) {
        $odps_with_coords[] = $odp_data;
    }
}

// Get center point (first POP or default)
$center_lat = -6.200000;
$center_lng = 106.816666;
if(!empty($pops_with_coords)) {
    $center_lat = $pops_with_coords[0]['latitude'];
    $center_lng = $pops_with_coords[0]['longitude'];
}

// Get specific coordinates from URL if provided
if(isset($_GET['lat']) && isset($_GET['lng'])) {
    $center_lat = $_GET['lat'];
    $center_lng = $_GET['lng'];
}

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-map-marked-alt"></i> Peta Lokasi POP & ODP
        </h1>
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <button class="btn btn-info" onclick="refreshMap()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button class="btn btn-success" onclick="printMap()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Map Container -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-map"></i> Peta Infrastruktur Jaringan
            </h6>
        </div>
        <div class="card-body">
            <div id="map" style="height: 600px; width: 100%; border-radius: 10px;"></div>
        </div>
    </div>

    <!-- Legend Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-info-circle"></i> Keterangan
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="d-flex align-items-center mb-2">
                        <div style="width: 30px; height: 30px; background: #e74a3b; border-radius: 50%; margin-right: 10px;"></div>
                        <span><strong>POP (Point of Presence)</strong> - Titik utama jaringan</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <div style="width: 30px; height: 30px; background: #f6c23e; border-radius: 50%; margin-right: 10px;"></div>
                        <span><strong>ODP (Optical Distribution Point)</strong> - Titik distribusi</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Tips:</strong> Klik pada marker untuk melihat detail informasi lokasi.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Card -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total POP</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count($pops); ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo count($pops_with_coords); ?> dengan koordinat
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tower-broadcast fa-2x text-gray-300"></i>
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
                                Total ODP</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $total_odp = 0;
                                $odp_count_stmt = $odp->read('', '', 9999, 0);
                                while($odp_count_stmt->fetch()) $total_odp++;
                                echo $total_odp;
                                ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo count($odps_with_coords); ?> dengan koordinat
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exchange-alt fa-2x text-gray-300"></i>
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
                                POP Aktif</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $aktif_count = 0;
                                foreach($pops as $p) {
                                    if($p['status'] == 'aktif') $aktif_count++;
                                }
                                echo $aktif_count;
                                ?>
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
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                ODP Aktif</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $odp_aktif = 0;
                                $odp_aktif_stmt = $odp->read('', '', 9999, 0);
                                while($odp_aktif_data = $odp_aktif_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    if($odp_aktif_data['status'] == 'aktif') $odp_aktif++;
                                }
                                echo $odp_aktif;
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-plug fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet CSS and JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
#map {
    background-color: #f8f9fc;
}

.leaflet-popup-content {
    min-width: 250px;
    font-size: 14px;
}

.leaflet-popup-content h6 {
    color: #4e73df;
    margin-bottom: 10px;
    font-weight: bold;
}

.leaflet-popup-content p {
    margin-bottom: 5px;
}

.leaflet-popup-content hr {
    margin: 8px 0;
}

.custom-marker-pop {
    background-color: #e74a3b;
    border: 2px solid white;
    border-radius: 50%;
    width: 12px;
    height: 12px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
}

.custom-marker-odp {
    background-color: #f6c23e;
    border: 2px solid white;
    border-radius: 50%;
    width: 10px;
    height: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
}
</style>

<script>
// Map data from PHP
const popsData = <?php echo json_encode($pops_with_coords); ?>;
const odpsData = <?php echo json_encode($odps_with_coords); ?>;
const centerLat = <?php echo $center_lat; ?>;
const centerLng = <?php echo $center_lng; ?>;
const defaultZoom = 13;

// Initialize map
var map = L.map('map').setView([centerLat, centerLng], defaultZoom);

// Add tile layer (OpenStreetMap)
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
}).addTo(map);

// Custom icons
var popIcon = L.divIcon({
    className: 'custom-marker-pop',
    html: '<div style="background-color: #e74a3b; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
    iconSize: [16, 16],
    popupAnchor: [0, -8]
});

var odpIcon = L.divIcon({
    className: 'custom-marker-odp',
    html: '<div style="background-color: #f6c23e; width: 10px; height: 10px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
    iconSize: [14, 14],
    popupAnchor: [0, -7]
});

// Add POP markers
popsData.forEach(function(pop) {
    var popupContent = `
        <div style="min-width: 250px;">
            <h6 style="color: #e74a3b; margin-bottom: 10px;">
                <i class="fas fa-tower-broadcast"></i> ${pop.nama_pop}
            </h6>
            <hr style="margin: 8px 0;">
            <p><strong>Kode POP:</strong> ${pop.kode_pop}</p>
            <p><strong>Lokasi:</strong> ${pop.lokasi}</p>
            <p><strong>Alamat:</strong> ${pop.alamat || '-'}</p>
            <p><strong>Kapasitas:</strong> ${formatNumber(pop.kapasitas)}</p>
            <p><strong>Status:</strong> 
                <span class="badge bg-${pop.status == 'aktif' ? 'success' : (pop.status == 'maintenance' ? 'warning' : 'secondary')}">
                    ${pop.status == 'aktif' ? 'Aktif' : (pop.status == 'maintenance' ? 'Maintenance' : 'Nonaktif')}
                </span>
            </p>
            <p><strong>Koordinat:</strong> ${pop.latitude}, ${pop.longitude}</p>
            <hr>
            <a href="odp.php?pop_id=${pop.id}" class="btn btn-sm btn-primary" style="width: 100%;">
                <i class="fas fa-exchange-alt"></i> Lihat ODP
            </a>
        </div>
    `;
    
    var marker = L.marker([pop.latitude, pop.longitude], { icon: popIcon }).addTo(map);
    marker.bindPopup(popupContent);
    
    // Add hover effect
    marker.on('mouseover', function(e) {
        this.openPopup();
    });
});

// Add ODP markers
odpsData.forEach(function(odp) {
    // Get status badge class
    var statusClass = odp.status == 'aktif' ? 'success' : (odp.status == 'penuh' ? 'danger' : 'secondary');
    var statusText = odp.status == 'aktif' ? 'Aktif' : (odp.status == 'penuh' ? 'Penuh' : 'Nonaktif');
    
    var popupContent = `
        <div style="min-width: 250px;">
            <h6 style="color: #f6c23e; margin-bottom: 10px;">
                <i class="fas fa-exchange-alt"></i> ${odp.nama_odp}
            </h6>
            <hr style="margin: 8px 0;">
            <p><strong>Kode ODP:</strong> ${odp.kode_odp}</p>
            <p><strong>POP:</strong> ${odp.nama_pop || '-'}</p>
            <p><strong>Alamat:</strong> ${odp.alamat || '-'}</p>
            <p><strong>Port:</strong> ${odp.jumlah_port} port</p>
            <p><strong>Port Terpakai:</strong> ${odp.port_terpakai} (${Math.round((odp.port_terpakai / odp.jumlah_port) * 100)}%)</p>
            <p><strong>Status:</strong> 
                <span class="badge bg-${statusClass}">${statusText}</span>
            </p>
            <p><strong>Koordinat:</strong> ${odp.latitude}, ${odp.longitude}</p>
        </div>
    `;
    
    var marker = L.marker([odp.latitude, odp.longitude], { icon: odpIcon }).addTo(map);
    marker.bindPopup(popupContent);
    
    // Add hover effect
    marker.on('mouseover', function(e) {
        this.openPopup();
    });
});

// Add scale control
L.control.scale({ metric: true, imperial: false }).addTo(map);

// Add zoom control
L.control.zoom({ position: 'topright' }).addTo(map);

// Function to format number
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Function to refresh map (recenter)
function refreshMap() {
    map.setView([centerLat, centerLng], defaultZoom);
    showToast('Peta telah di-refresh', 'info');
}

// Function to print map
function printMap() {
    window.print();
}

// Function to show toast notification
function showToast(message, type = 'info') {
    const bgColor = type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8';
    const toast = $(`
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
            <div class="toast show" role="alert">
                <div class="toast-header" style="background: ${bgColor}; color: white;">
                    <strong class="me-auto">Notifikasi</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        </div>
    `);
    
    $('body').append(toast);
    setTimeout(function() {
        toast.fadeOut(function() {
            $(this).remove();
        });
    }, 3000);
}

// Add layer control for toggling POP and ODP (optional)
var popGroup = L.layerGroup();
var odpGroup = L.layerGroup();

// Re-add markers to groups for layer control
popsData.forEach(function(pop) {
    var marker = L.marker([pop.latitude, pop.longitude], { icon: popIcon });
    marker.bindPopup(`
        <div style="min-width: 250px;">
            <h6 style="color: #e74a3b;">${pop.nama_pop}</h6>
            <p><strong>Kode:</strong> ${pop.kode_pop}</p>
            <p><strong>Lokasi:</strong> ${pop.lokasi}</p>
            <p><strong>Status:</strong> ${pop.status}</p>
        </div>
    `);
    popGroup.addLayer(marker);
});

odpsData.forEach(function(odp) {
    var marker = L.marker([odp.latitude, odp.longitude], { icon: odpIcon });
    marker.bindPopup(`
        <div style="min-width: 250px;">
            <h6 style="color: #f6c23e;">${odp.nama_odp}</h6>
            <p><strong>Kode:</strong> ${odp.kode_odp}</p>
            <p><strong>POP:</strong> ${odp.nama_pop || '-'}</p>
            <p><strong>Port:</strong> ${odp.jumlah_port} (${odp.port_terpakai} terpakai)</p>
        </div>
    `);
    odpGroup.addLayer(marker);
});

// Add groups to map
popGroup.addTo(map);
odpGroup.addTo(map);

// Add layer control
var overlayControl = L.control.layers(null, {
    'POP (Point of Presence)': popGroup,
    'ODP (Optical Distribution Point)': odpGroup
}, { collapsed: false }).addTo(map);

// Fit map to show all markers
function fitMapToMarkers() {
    var allMarkers = [...popsData, ...odpsData];
    if(allMarkers.length > 0) {
        var bounds = L.latLngBounds([]);
        allMarkers.forEach(function(marker) {
            bounds.extend([marker.latitude, marker.longitude]);
        });
        map.fitBounds(bounds, { padding: [50, 50] });
    }
}

// Optional: Fit map to show all markers (uncomment if needed)
// fitMapToMarkers();

// Add search functionality (optional)
var searchControl = L.control.search({
    position: 'topleft',
    url: 'https://nominatim.openstreetmap.org/search?format=json&q={s}',
    jsonpParam: 'json_callback',
    propertyName: 'display_name',
    propertyLoc: ['lat', 'lon'],
    marker: L.marker([0,0]),
    autoCollapse: true,
    autoType: false,
    minLength: 2
}).addTo(map);

// Add current location button
L.control.locate({
    position: 'topright',
    strings: {
        title: 'Lokasi Saya'
    },
    locateOptions: {
        setView: true,
        maxZoom: 16
    }
}).addTo(map);
</script>

<style>
@media print {
    .btn, .card-header .btn, .navbar, .sidebar, .sidebar-overlay, .leaflet-control-zoom, 
    .leaflet-control-attribution, .leaflet-control-scale, .leaflet-control-locate,
    .leaflet-control-search, .leaflet-control-layers {
        display: none !important;
    }
    
    #map {
        height: 100vh !important;
        width: 100% !important;
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
    }
    
    .card {
        break-inside: avoid;
    }
}

.leaflet-popup-content {
    font-size: 13px;
    line-height: 1.4;
}

.leaflet-popup-content strong {
    color: #4e73df;
}

.badge {
    font-size: 11px;
    padding: 3px 6px;
}
</style>

<?php require_once '../../includes/footer.php'; ?>