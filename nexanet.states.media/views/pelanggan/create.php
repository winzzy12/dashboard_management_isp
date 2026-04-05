<?php
ob_start();
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check role - hanya admin dan operator yang bisa menambah
$user_role = $_SESSION['role'];
if($user_role != 'admin' && $user_role != 'operator') {
    $_SESSION['error'] = "Anda tidak memiliki akses untuk menambah data!";
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

$error = '';

// Get active packages
$packages = $paketModel->getAll(true);

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $last_id = $pelanggan->getTotal();
    $pelanggan->id_pelanggan = 'PLG' . str_pad($last_id + 1, 3, '0', STR_PAD_LEFT);
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
    
    if($pelanggan->create()) {
        $_SESSION['success'] = "Data pelanggan berhasil ditambahkan! ID: " . $pelanggan->id_pelanggan;
        header("Location: index.php");
        exit();
    } else {
        $error = "Gagal menambahkan data pelanggan!";
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h5 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus-circle me-1"></i> Tambah Pelanggan Baru
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
                                       placeholder="Masukkan nama lengkap" required>
                                <div class="invalid-feedback">Nama lengkap wajib diisi</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="no_hp" class="form-label">Nomor HP <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="no_hp" name="no_hp" 
                                       placeholder="Contoh: 081234567890" required>
                                <div class="invalid-feedback">Nomor HP wajib diisi</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="alamat" class="form-label">Alamat <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3" 
                                      placeholder="Masukkan alamat lengkap" required></textarea>
                            <div class="invalid-feedback">Alamat wajib diisi</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="paket_id" class="form-label">Paket Internet <span class="text-danger">*</span></label>
                                <select class="form-select" id="paket_id" name="paket_id" required>
                                    <option value="">Pilih Paket</option>
                                    <?php foreach($packages as $pkg): ?>
                                    <option value="<?php echo $pkg['id']; ?>" 
                                            data-harga="<?php echo $pkg['harga']; ?>">
                                        <?php echo htmlspecialchars($pkg['nama_paket'] . ' - ' . $pkg['kecepatan'] . ' (Rp ' . number_format($pkg['harga'], 0, ',', '.') . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Paket internet wajib dipilih</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="harga_paket" class="form-label">Harga Paket (Rp) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="harga_paket" name="harga_paket" 
                                       placeholder="Harga akan terisi otomatis" required readonly>
                                <div class="invalid-feedback">Harga paket wajib diisi</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                            <div class="invalid-feedback">Status wajib dipilih</div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('paket_id').addEventListener('change', function() {
    var selected = this.options[this.selectedIndex];
    var harga = selected.getAttribute('data-harga');
    if(harga) {
        document.getElementById('harga_paket').value = new Intl.NumberFormat('id-ID').format(harga);
    } else {
        document.getElementById('harga_paket').value = '';
    }
});

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

document.getElementById('no_hp').addEventListener('input', function(e) {
    var x = e.target.value.replace(/\D/g, '').match(/(\d{0,4})(\d{0,4})(\d{0,4})/);
    e.target.value = !x[2] ? x[1] : x[1] + '-' + x[2] + (x[3] ? '-' + x[3] : '');
});
</script>

<?php require_once '../../includes/footer.php'; ?>