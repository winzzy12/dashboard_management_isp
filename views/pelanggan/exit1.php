<?php
ob_start();
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check role - hanya admin dan operator yang bisa edit
$user_role = $_SESSION['role'];
if($user_role != 'admin' && $user_role != 'operator') {
    $_SESSION['error'] = "Anda tidak memiliki akses untuk mengedit data!";
    header("Location: index.php");
    exit();
}

require_once '../../config/database.php';
require_once '../../models/Pelanggan.php';
require_once '../../models/Paket.php';
require_once '../../includes/header.php';

$database = new Database();
$db = $database->getConnection();
$pelanggan = new Pelanggan($db);
$paketModel = new Paket($db);

// Check if ID is provided
if(!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

// Get data
$pelanggan->id = $_GET['id'];
$data = $pelanggan->getOne();

if(!$data) {
    header("Location: index.php");
    exit();
}

// Get all packages (active only for selection)
$packages = $paketModel->getAll(true);

// Get POP list
$pop_list = [];
$pop_query = "SELECT id, kode_pop, nama_pop, latitude, longitude FROM pop WHERE status = 'aktif' ORDER BY nama_pop";
$pop_stmt = $db->prepare($pop_query);
$pop_stmt->execute();
$pop_list = $pop_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ODP list for selected POP
$odp_list = [];
if($data['pop_id']) {
    $odp_query = "SELECT id, kode_odp, nama_odp, jumlah_port, port_terpakai, latitude, longitude 
                  FROM odp WHERE pop_id = :pop_id AND status = 'aktif' ORDER BY nama_odp";
    $odp_stmt = $db->prepare($odp_query);
    $odp_stmt->bindParam(':pop_id', $data['pop_id']);
    $odp_stmt->execute();
    $odp_list = $odp_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pelanggan->nama = trim($_POST['nama']);
    $pelanggan->alamat = trim($_POST['alamat']);
    $pelanggan->no_hp = trim($_POST['no_hp']);
    
    // Get package info
    $paket_id = $_POST['paket_id'];
    $selected_paket = $paketModel->getById($paket_id);
    
    $pelanggan->paket_internet = $selected_paket['nama_paket'] . ' ' . $selected_paket['kecepatan'];
    $pelanggan->harga_paket = $selected_paket['harga'];
    $pelanggan->status = $_POST['status'];
    $pelanggan->paket_id = $paket_id;
    $pelanggan->pop_id = !empty($_POST['pop_id']) ? $_POST['pop_id'] : null;
    $pelanggan->odp_id = !empty($_POST['odp_id']) ? $_POST['odp_id'] : null;
    $pelanggan->latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
    $pelanggan->longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
    
    if($pelanggan->update()) {
        $_SESSION['success'] = "Data pelanggan berhasil diupdate!";
        header("Location: index.php");
        exit();
    } else {
        $error = "Gagal mengupdate data pelanggan!";
    }
}

// Find current package ID
$current_paket_id = null;
foreach($packages as $pkg) {
    $full_name = $pkg['nama_paket'] . ' ' . $pkg['kecepatan'];
    if($full_name == $data['paket_internet']) {
        $current_paket_id = $pkg['id'];
        break;
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h5 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-edit me-1"></i> Edit Pelanggan
                    </h5>
                </div>
                <div class="card-body">
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nama" name="nama" 
                                       value="<?php echo htmlspecialchars($data['nama']); ?>" required>
                                <div class="invalid-feedback">Nama lengkap wajib diisi</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="no_hp" class="form-label">Nomor HP <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="no_hp" name="no_hp" 
                                       value="<?php echo htmlspecialchars($data['no_hp']); ?>" required>
                                <div class="invalid-feedback">Nomor HP wajib diisi</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="alamat" class="form-label">Alamat <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3" required><?php echo htmlspecialchars($data['alamat']); ?></textarea>
                            <div class="invalid-feedback">Alamat wajib diisi</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="paket_id" class="form-label">Paket Internet <span class="text-danger">*</span></label>
                                <select class="form-select" id="paket_id" name="paket_id" required>
                                    <option value="">Pilih Paket</option>
                                    <?php foreach($packages as $pkg): ?>
                                    <option value="<?php echo $pkg['id']; ?>" 
                                            data-harga="<?php echo $pkg['harga']; ?>"
                                            <?php echo ($current_paket_id == $pkg['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pkg['nama_paket'] . ' - ' . $pkg['kecepatan'] . ' (Rp ' . number_format($pkg['harga'], 0, ',', '.') . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Paket internet wajib dipilih</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="harga_paket" class="form-label">Harga Paket (Rp) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="harga_paket" name="harga_paket" 
                                       value="<?php echo number_format($data['harga_paket'], 0, ',', '.'); ?>" 
                                       required readonly>
                                <div class="invalid-feedback">Harga paket wajib diisi</div>
                            </div>
                        </div>
                        
                        <!-- POP dan ODP Section -->
                        <div class="row mt-3">
                            <div class="col-md-6 mb-3">
                                <label for="pop_id" class="form-label">POP (Point of Presence)</label>
                                <select class="form-select" id="pop_id" name="pop_id">
                                    <option value="">-- Pilih POP --</option>
                                    <?php foreach($pop_list as $pop): ?>
                                    <option value="<?php echo $pop['id']; ?>" 
                                            data-lat="<?php echo $pop['latitude']; ?>"
                                            data-lng="<?php echo $pop['longitude']; ?>"
                                            <?php echo ($data['pop_id'] == $pop['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pop['kode_pop'] . ' - ' . $pop['nama_pop']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Pilih POP terdekat dengan lokasi pelanggan</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="odp_id" class="form-label">ODP (Optical Distribution Point)</label>
                                <select class="form-select" id="odp_id" name="odp_id">
                                    <option value="">-- Pilih POP Terlebih Dahulu --</option>
                                    <?php foreach($odp_list as $odp): ?>
                                    <option value="<?php echo $odp['id']; ?>" 
                                            data-lat="<?php echo $odp['latitude']; ?>"
                                            data-lng="<?php echo $odp['longitude']; ?>"
                                            <?php echo ($data['odp_id'] == $odp['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($odp['kode_odp'] . ' - ' . $odp['nama_odp'] . ' (' . ($odp['jumlah_port'] - $odp['port_terpakai']) . ' port tersedia)'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">ODP yang tersedia pada POP yang dipilih</div>
                            </div>
                        </div>
                        
                        <!-- Koordinat Section -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input type="text" class="form-control" id="latitude" name="latitude" 
                                       value="<?php echo $data['latitude']; ?>"
                                       placeholder="Contoh: -6.200000">
                                <div class="form-text">Koordinat lintang (opsional)</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input type="text" class="form-control" id="longitude" name="longitude" 
                                       value="<?php echo $data['longitude']; ?>"
                                       placeholder="Contoh: 106.816666">
                                <div class="form-text">Koordinat bujur (opsional)</div>
                            </div>
                        </div>
                        
                        <!-- Tombol Ambil Koordinat -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <button type="button" class="btn btn-sm btn-info" onclick="getCurrentLocation()">
                                    <i class="fas fa-map-marker-alt"></i> Ambil Koordinat Lokasi Saya
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="showMap()">
                                    <i class="fas fa-map"></i> Pilih dari Peta
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="aktif" <?php echo $data['status'] == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="nonaktif" <?php echo $data['status'] == 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                            </select>
                            <div class="invalid-feedback">Status wajib dipilih</div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Peta -->
<div class="modal fade" id="mapModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-map-marked-alt"></i> Pilih Lokasi dari Peta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="map" style="height: 400px;"></div>
                <p class="mt-2 text-muted">Klik pada peta untuk memilih koordinat</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" onclick="setCoordinatesFromMap()">Gunakan Koordinat Ini</button>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<script>
// Auto fill harga when package selected
document.getElementById('paket_id').addEventListener('change', function() {
    var selected = this.options[this.selectedIndex];
    var harga = selected.getAttribute('data-harga');
    if(harga) {
        document.getElementById('harga_paket').value = new Intl.NumberFormat('id-ID').format(harga);
    } else {
        document.getElementById('harga_paket').value = '';
    }
});

// Load ODP based on selected POP
document.getElementById('pop_id').addEventListener('change', function() {
    var popId = this.value;
    var odpSelect = document.getElementById('odp_id');
    
    if(popId) {
        fetch('get_odp_by_pop.php?pop_id=' + popId)
            .then(response => response.json())
            .then(data => {
                odpSelect.innerHTML = '<option value="">-- Pilih ODP --</option>';
                data.forEach(odp => {
                    odpSelect.innerHTML += `<option value="${odp.id}" data-lat="${odp.latitude}" data-lng="${odp.longitude}">${odp.kode_odp} - ${odp.nama_odp} (${odp.jumlah_port - odp.port_terpakai} port tersedia)</option>`;
                });
            })
            .catch(error => {
                console.error('Error:', error);
            });
        
        var selectedPop = document.getElementById('pop_id').options[document.getElementById('pop_id').selectedIndex];
        var lat = selectedPop.getAttribute('data-lat');
        var lng = selectedPop.getAttribute('data-lng');
        if(lat && lng) {
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
        }
    } else {
        odpSelect.innerHTML = '<option value="">-- Pilih POP Terlebih Dahulu --</option>';
    }
});

// Auto fill coordinates from ODP
document.getElementById('odp_id').addEventListener('change', function() {
    var selectedOdp = this.options[this.selectedIndex];
    var lat = selectedOdp.getAttribute('data-lat');
    var lng = selectedOdp.getAttribute('data-lng');
    if(lat && lng) {
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;
    }
});

function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
            alert('Koordinat berhasil diambil!');
        }, function(error) {
            alert('Gagal mengambil lokasi: ' + error.message);
        });
    } else {
        alert('Browser tidak mendukung geolocation');
    }
}

var map;
var marker;
var selectedLat = null;
var selectedLng = null;

function showMap() {
    var lat = parseFloat(document.getElementById('latitude').value) || -6.200000;
    var lng = parseFloat(document.getElementById('longitude').value) || 106.816666;
    
    var mapModal = new bootstrap.Modal(document.getElementById('mapModal'));
    mapModal.show();
    
    setTimeout(() => {
        if(map) {
            map.remove();
        }
        map = L.map('map').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        marker = L.marker([lat, lng]).addTo(map);
        
        map.on('click', function(e) {
            if(marker) {
                map.removeLayer(marker);
            }
            marker = L.marker([e.latlng.lat, e.latlng.lng]).addTo(map);
            selectedLat = e.latlng.lat;
            selectedLng = e.latlng.lng;
        });
    }, 500);
}

function setCoordinatesFromMap() {
    if(selectedLat && selectedLng) {
        document.getElementById('latitude').value = selectedLat.toFixed(8);
        document.getElementById('longitude').value = selectedLng.toFixed(8);
        alert('Koordinat berhasil disimpan!');
    } else {
        alert('Silakan klik pada peta terlebih dahulu');
    }
    var modal = bootstrap.Modal.getInstance(document.getElementById('mapModal'));
    modal.hide();
}

// Form validation
(function() {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php require_once '../../includes/footer.php'; ?>