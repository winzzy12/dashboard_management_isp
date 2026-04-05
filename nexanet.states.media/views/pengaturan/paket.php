<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if($_SESSION['role'] != 'admin') {
    header("Location: ../dashboard.php");
    exit();
}

// Include required files
require_once '../../config/database.php';
require_once '../../models/Paket.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize model
$paket = new Paket($db);

$success_message = '';
$error_message = '';

// Handle Add Package
if(isset($_POST['action']) && $_POST['action'] == 'add_paket') {
    $paket->nama_paket = trim($_POST['nama_paket']);
    $paket->kecepatan = trim($_POST['kecepatan']);
    $paket->harga = str_replace('.', '', $_POST['harga']);
    $paket->keterangan = trim($_POST['keterangan']);
    $paket->is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if($paket->create()) {
        $success_message = "Paket internet berhasil ditambahkan!";
    } else {
        $error_message = "Gagal menambahkan paket internet!";
    }
}

// Handle Update Package
if(isset($_POST['action']) && $_POST['action'] == 'update_paket') {
    $paket->id = $_POST['id'];
    $paket->nama_paket = trim($_POST['nama_paket']);
    $paket->kecepatan = trim($_POST['kecepatan']);
    $paket->harga = str_replace('.', '', $_POST['harga']);
    $paket->keterangan = trim($_POST['keterangan']);
    $paket->is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if($paket->update()) {
        $success_message = "Paket internet berhasil diupdate!";
    } else {
        $error_message = "Gagal mengupdate paket internet!";
    }
}

// Handle Delete Package
if(isset($_GET['delete']) && isset($_GET['id'])) {
    $result = $paket->delete($_GET['id']);
    if($result['success']) {
        $success_message = $result['message'];
    } else {
        $error_message = $result['message'];
    }
    header("Location: paket.php");
    exit();
}

// Handle Toggle Active
if(isset($_GET['toggle']) && isset($_GET['id'])) {
    if($paket->toggleActive($_GET['id'])) {
        $success_message = "Status paket berhasil diubah!";
    } else {
        $error_message = "Gagal mengubah status paket!";
    }
    header("Location: paket.php");
    exit();
}

// Handle Update Price Only
if(isset($_POST['action']) && $_POST['action'] == 'update_price') {
    $id = $_POST['id'];
    $harga = str_replace('.', '', $_POST['harga']);
    
    if($paket->updatePrice($id, $harga)) {
        $success_message = "Harga paket berhasil diupdate!";
    } else {
        $error_message = "Gagal mengupdate harga paket!";
    }
}

// Get all packages
$packages = $paket->getAll();

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-tags"></i> Kelola Paket Internet
        </h1>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaketModal">
                <i class="fas fa-plus"></i> Tambah Paket
            </button>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Package List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list"></i> Daftar Paket Internet
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="20%">Nama Paket</th>
                            <th width="15%">Kecepatan</th>
                            <th width="15%">Harga</th>
                            <th width="25%">Keterangan</th>
                            <th width="10%">Status</th>
                            <th width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach($packages as $pkg): ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($pkg['nama_paket']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($pkg['kecepatan']); ?></td>
                            <td class="text-end">
                                <span class="fw-bold text-primary">
                                    Rp <?php echo number_format($pkg['harga'], 0, ',', '.'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($pkg['keterangan'] ?: '-'); ?></td>
                            <td class="text-center">
                                <?php if($pkg['is_active']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-info" onclick="editPackage(<?php echo htmlspecialchars(json_encode($pkg)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="updatePrice(<?php echo $pkg['id']; ?>, '<?php echo $pkg['nama_paket']; ?>', <?php echo $pkg['harga']; ?>)">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </button>
                                    <a href="?toggle=1&id=<?php echo $pkg['id']; ?>" 
                                       class="btn btn-sm <?php echo $pkg['is_active'] ? 'btn-secondary' : 'btn-success'; ?>"
                                       onclick="return confirm('Ubah status paket ini?')">
                                        <i class="fas <?php echo $pkg['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                    </a>
                                    <a href="?delete=1&id=<?php echo $pkg['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Yakin ingin menghapus paket <?php echo htmlspecialchars($pkg['nama_paket']); ?>?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Info Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-info">
                <i class="fas fa-info-circle"></i> Informasi
            </h6>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-lightbulb"></i>
                <strong>Tips:</strong>
                <ul class="mb-0 mt-2">
                    <li>Perubahan harga paket akan berlaku untuk pelanggan baru. Untuk pelanggan existing, harga paket tidak berubah otomatis.</li>
                    <li>Jika ingin mengubah harga untuk semua pelanggan dengan paket tertentu, silakan update manual di halaman Data Pelanggan.</li>
                    <li>Paket yang dinonaktifkan tidak akan muncul di dropdown saat menambah pelanggan baru.</li>
                    <li>Paket yang sedang digunakan oleh pelanggan tidak dapat dihapus.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Package -->
<div class="modal fade" id="addPaketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus"></i> Tambah Paket Internet
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_paket">
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Paket <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_paket" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kecepatan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kecepatan" placeholder="Contoh: 20 Mbps" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="harga" id="harga_add" 
                               placeholder="Contoh: 250000" required onkeyup="formatRupiah(this)">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="2" placeholder="Deskripsi paket"></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="is_active_add" checked>
                        <label class="form-check-label" for="is_active_add">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Package -->
<div class="modal fade" id="editPaketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Edit Paket Internet
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_paket">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Paket <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_paket" id="edit_nama_paket" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kecepatan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kecepatan" id="edit_kecepatan" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="harga" id="edit_harga" 
                               required onkeyup="formatRupiah(this)">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" id="edit_keterangan" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                        <label class="form-check-label" for="edit_is_active">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Update Price -->
<div class="modal fade" id="priceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-money-bill-wave"></i> Update Harga Paket
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_price">
                    <input type="hidden" name="id" id="price_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Paket</label>
                        <input type="text" class="form-control" id="price_nama" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Harga Baru (Rp) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="harga" id="price_harga" 
                               required onkeyup="formatRupiah(this)">
                        <div class="form-text">Masukkan harga baru untuk paket ini</div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Perhatian:</strong> Perubahan harga hanya berlaku untuk pelanggan baru. 
                        Untuk pelanggan existing, harga tidak berubah otomatis.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update Harga</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Format Rupiah
function formatRupiah(angka) {
    let value = angka.value.replace(/\D/g, '');
    let number = parseInt(value);
    if (!isNaN(number) && number > 0) {
        angka.value = number.toLocaleString('id-ID');
    } else if (value === '') {
        angka.value = '';
    }
}

// Edit Package
function editPackage(pkg) {
    document.getElementById('edit_id').value = pkg.id;
    document.getElementById('edit_nama_paket').value = pkg.nama_paket;
    document.getElementById('edit_kecepatan').value = pkg.kecepatan;
    document.getElementById('edit_harga').value = new Intl.NumberFormat('id-ID').format(pkg.harga);
    document.getElementById('edit_keterangan').value = pkg.keterangan || '';
    document.getElementById('edit_is_active').checked = pkg.is_active == 1;
    
    var modal = new bootstrap.Modal(document.getElementById('editPaketModal'));
    modal.show();
}

// Update Price
function updatePrice(id, nama, harga) {
    document.getElementById('price_id').value = id;
    document.getElementById('price_nama').value = nama;
    document.getElementById('price_harga').value = new Intl.NumberFormat('id-ID').format(harga);
    
    var modal = new bootstrap.Modal(document.getElementById('priceModal'));
    modal.show();
}

// Auto-hide alerts
setTimeout(function() {
    let alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(() => {
            let bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 3000);
    });
}, 1000);
</script>

<style>
.table th, .table td {
    vertical-align: middle;
}

.btn-group .btn {
    margin: 0 2px;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
}
</style>

<?php require_once '../../includes/footer.php'; ?>