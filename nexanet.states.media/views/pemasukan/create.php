<?php
// Enable error reporting
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
require_once '../../models/Pelanggan.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize models
$pemasukan = new Pemasukan($db);
$pelanggan = new Pelanggan($db);

// Get list of customers
$pelanggan_list = [];
if(method_exists($pelanggan, 'getAll')) {
    $pelanggan_list = $pelanggan->getAll();
} else {
    $query = "SELECT id, id_pelanggan, nama, harga_paket FROM pelanggan WHERE status = 'aktif' ORDER BY nama ASC";
    $stmt_pel = $db->prepare($query);
    $stmt_pel->execute();
    $pelanggan_list = $stmt_pel->fetchAll(PDO::FETCH_ASSOC);
}

$error = '';

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pemasukan->tanggal = $_POST['tanggal'];
    $pemasukan->pelanggan_id = $_POST['pelanggan_id'] ?: null;
    
    // FIX: Clean the amount - remove dots and commas, then convert to integer
    $jumlah = $_POST['jumlah'];
    // Remove all dots (thousand separators) and commas
    $jumlah = str_replace('.', '', $jumlah);
    $jumlah = str_replace(',', '', $jumlah);
    // Convert to integer
    $pemasukan->jumlah = (int)$jumlah;
    
    $pemasukan->keterangan = $_POST['keterangan'];
    
    if($pemasukan->create()) {
        $_SESSION['success'] = "Data pemasukan berhasil ditambahkan!";
        header("Location: index.php");
        exit();
    } else {
        $error = "Gagal menambahkan data pemasukan!";
    }
}

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h5 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus-circle me-1"></i> Tambah Pemasukan
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
                                <label for="tanggal" class="form-label">Tanggal <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="tanggal" name="tanggal" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">
                                    Tanggal wajib diisi
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="pelanggan_id" class="form-label">Pelanggan</label>
                                <select class="form-select" id="pelanggan_id" name="pelanggan_id">
                                    <option value="">-- Pilih Pelanggan (Opsional) --</option>
                                    <?php foreach($pelanggan_list as $p): ?>
                                    <option value="<?php echo $p['id']; ?>">
                                        <?php echo htmlspecialchars($p['id_pelanggan'] . ' - ' . $p['nama']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Pilih pelanggan jika ini adalah pembayaran tagihan</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="jumlah" class="form-label">Jumlah (Rp) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="jumlah" name="jumlah" 
                                       placeholder="Contoh: 200.000 atau 200000" required 
                                       onkeyup="formatRupiah(this)">
                                <div class="invalid-feedback">
                                    Jumlah wajib diisi
                                </div>
                                <div class="form-text">Gunakan format: 200.000 atau 200000</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="keterangan" class="form-label">Keterangan</label>
                                <input type="text" class="form-control" id="keterangan" name="keterangan" 
                                       placeholder="Keterangan tambahan (opsional)">
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                            <button type="submit" class="btn btn-primary" onclick="return validateForm()">
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
// Format Rupiah function
function formatRupiah(angka) {
    // Remove all non-digit characters
    let value = angka.value.replace(/\D/g, '');
    
    // Convert to number
    let number = parseInt(value);
    
    // Format with dots
    if (!isNaN(number)) {
        angka.value = number.toLocaleString('id-ID');
    } else {
        angka.value = '';
    }
}

// Validate form before submit
function validateForm() {
    let jumlahInput = document.getElementById('jumlah');
    let jumlahValue = jumlahInput.value.replace(/\D/g, '');
    
    if (jumlahValue === '' || parseInt(jumlahValue) === 0) {
        alert('Jumlah harus diisi dengan angka yang valid!');
        return false;
    }
    
    return true;
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

// Auto set today's date
document.getElementById('tanggal').value = new Date().toISOString().split('T')[0];
</script>

<?php require_once '../../includes/footer.php'; ?>