<?php
// Enable error reporting for debugging
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
    $_SESSION['error'] = "Anda tidak memiliki akses ke halaman ini!";
    header("Location: ../index.php");
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
    header("Location: index.php");
    exit();
}

// Handle Toggle Active
if(isset($_GET['toggle']) && isset($_GET['id'])) {
    if($paket->toggleActive($_GET['id'])) {
        $success_message = "Status paket berhasil diubah!";
    } else {
        $error_message = "Gagal mengubah status paket!";
    }
    header("Location: index.php");
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

// Get all packages with customer count
$packages = $paket->getWithCustomerCount();
$stats = $paket->getStats();

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
            <a href="../pengaturan/index.php" class="btn btn-secondary">
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

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Paket</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_paket']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tags fa-2x text-gray-300"></i>
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
                                Paket Aktif</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['aktif']); ?>
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
                                Harga Termurah</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($stats['harga_terendah'], 0, ',', '.'); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-down fa-2x text-gray-300"></i>
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
                                Harga Termahal</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp <?php echo number_format($stats['harga_tertinggi'], 0, ',', '.'); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-up fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Package List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list"></i> Daftar Paket Internet
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="paketTable">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="20%">Nama Paket</th>
                            <th width="12%">Kecepatan</th>
                            <th width="12%">Harga</th>
                            <th width="20%">Keterangan</th>
                            <th width="8%">Pelanggan</th>
                            <th width="8%">Status</th>
                            <th width="15%">Aksi</th>
                        </thead>
                    <tbody>
                        <?php $no = 1; if(!empty($packages)): foreach($packages as $pkg): ?>
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
                                <?php if($pkg['jumlah_pelanggan'] > 0): ?>
                                    <span class="badge bg-info">
                                        <?php echo $pkg['jumlah_pelanggan']; ?> Pelanggan
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">0 Pelanggan</span>
                                <?php endif; ?>
                            </td>
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
                                    <button class="btn btn-sm btn-warning" onclick="updatePrice(<?php echo $pkg['id']; ?>, '<?php echo addslashes($pkg['nama_paket']); ?>', <?php echo $pkg['harga']; ?>)">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </button>
                                    <a href="?toggle=1&id=<?php echo $pkg['id']; ?>" 
                                       class="btn btn-sm <?php echo $pkg['is_active'] ? 'btn-secondary' : 'btn-success'; ?>"
                                       onclick="return confirm('Ubah status paket <?php echo addslashes($pkg['nama_paket']); ?>?')">
                                        <i class="fas <?php echo $pkg['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                    </a>
                                    <?php if($pkg['jumlah_pelanggan'] == 0): ?>
                                    <a href="?delete=1&id=<?php echo $pkg['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Yakin ingin menghapus paket <?php echo addslashes($pkg['nama_paket']); ?>?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-danger" disabled title="Tidak dapat dihapus karena digunakan oleh pelanggan">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                <p>Belum ada paket internet</p>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPaketModal">
                                    <i class="fas fa-plus"></i> Tambah Paket Sekarang
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Info Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-info">
                <i class="fas fa-info-circle"></i> Informasi Penting
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb"></i>
                        <strong>Tips Pengelolaan Paket:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Perubahan harga paket akan berlaku untuk <strong>pelanggan baru</strong>.</li>
                            <li>Untuk pelanggan existing, harga tidak berubah otomatis.</li>
                            <li>Jika ingin mengubah harga untuk semua pelanggan dengan paket tertentu, silakan update manual di halaman <strong>Data Pelanggan</strong>.</li>
                            <li>Paket yang <strong>dinonaktifkan</strong> tidak akan muncul di dropdown saat menambah pelanggan baru.</li>
                            <li>Paket yang sedang digunakan oleh pelanggan <strong>tidak dapat dihapus</strong>.</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Perhatian:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Hapus paket hanya jika tidak ada pelanggan yang menggunakan paket tersebut.</li>
                            <li>Nonaktifkan paket jika ingin menghentikan penjualan paket tersebut.</li>
                            <li>Pastikan harga yang dimasukkan sesuai dengan kecepatan dan benefit yang diberikan.</li>
                        </ul>
                    </div>
                </div>
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
                        <input type="text" class="form-control" name="nama_paket" required placeholder="Contoh: Paket Silver">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kecepatan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kecepatan" placeholder="Contoh: 20 Mbps" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="harga" id="harga_add" 
                               placeholder="Contoh: 250000" required onkeyup="formatRupiah(this)">
                        <div class="form-text">Masukkan angka tanpa titik, akan diformat otomatis</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="2" 
                                  placeholder="Deskripsi paket, kelebihan, dll"></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="is_active_add" checked>
                        <label class="form-check-label" for="is_active_add">Aktif</label>
                        <div class="form-text">Paket aktif akan muncul di form pendaftaran pelanggan</div>
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
                        <div class="form-text">Nonaktifkan jika paket tidak dijual lagi</div>
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
                        <strong>Perhatian:</strong> Perubahan harga hanya berlaku untuk <strong>pelanggan baru</strong>. 
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

// Initialize DataTable if needed
$(document).ready(function() {
    if($('#paketTable tbody tr').length > 0 && $('#paketTable tbody tr td').length > 1) {
        $('#paketTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
            },
            pageLength: 10,
            responsive: true,
            order: [[3, 'asc']] // Sort by price ascending
        });
    }
});
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

.badge {
    font-size: 11px;
    padding: 5px 8px;
}

.border-left-primary { border-left: 4px solid #4e73df !important; }
.border-left-success { border-left: 4px solid #1cc88a !important; }
.border-left-info { border-left: 4px solid #36b9cc !important; }
.border-left-warning { border-left: 4px solid #f6c23e !important; }
.text-gray-800 { color: #5a5c69 !important; }
</style>

<?php require_once '../../includes/footer.php'; ?>