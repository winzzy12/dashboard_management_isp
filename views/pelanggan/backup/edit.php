<?php
ob_start();
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
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
    
    if($pelanggan->update()) {
        $_SESSION['success'] = "Data pelanggan berhasil diupdate!";
        header("Location: index.php");
        exit();
    } else {
        $error = "Gagal mengupdate data pelanggan!";
    }
}

// Find current package ID by matching package name
$current_paket_id = null;
foreach($packages as $pkg) {
    $full_name = $pkg['nama_paket'] . ' ' . $pkg['kecepatan'];
    if($full_name == $data['paket_internet']) {
        $current_paket_id = $pkg['id'];
        break;
    }
}

// If not found, try to find by name only (backup method)
if($current_paket_id === null) {
    foreach($packages as $pkg) {
        if($pkg['nama_paket'] == $data['paket_internet']) {
            $current_paket_id = $pkg['id'];
            break;
        }
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
                                <div class="invalid-feedback">
                                    Nama lengkap wajib diisi
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="no_hp" class="form-label">Nomor HP <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="no_hp" name="no_hp" 
                                       value="<?php echo htmlspecialchars($data['no_hp']); ?>" required>
                                <div class="invalid-feedback">
                                    Nomor HP wajib diisi
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="alamat" class="form-label">Alamat <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3" required><?php echo htmlspecialchars($data['alamat']); ?></textarea>
                            <div class="invalid-feedback">
                                Alamat wajib diisi
                            </div>
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
                                <div class="invalid-feedback">
                                    Paket internet wajib dipilih
                                </div>
                                <div class="form-text">Pilih paket internet yang sesuai</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="harga_paket" class="form-label">Harga Paket (Rp) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="harga_paket" name="harga_paket" 
                                       value="<?php echo number_format($data['harga_paket'], 0, ',', '.'); ?>" 
                                       required readonly>
                                <div class="invalid-feedback">
                                    Harga paket wajib diisi
                                </div>
                                <div class="form-text">Harga akan terisi otomatis berdasarkan paket yang dipilih</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="aktif" <?php echo $data['status'] == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="nonaktif" <?php echo $data['status'] == 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                            </select>
                            <div class="invalid-feedback">
                                Status wajib dipilih
                            </div>
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

// Phone number formatting
document.getElementById('no_hp').addEventListener('input', function(e) {
    var x = e.target.value.replace(/\D/g, '').match(/(\d{0,4})(\d{0,4})(\d{0,4})/);
    e.target.value = !x[2] ? x[1] : x[1] + '-' + x[2] + (x[3] ? '-' + x[3] : '');
});
</script>

<?php require_once '../../includes/footer.php'; ?>