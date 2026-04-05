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
require_once '../../models/Pengeluaran.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize model
$pengeluaran = new Pengeluaran($db);

$error = '';

// Get list of expense types for dropdown suggestions
$expense_types = [
    'Operasional - Biaya Operasional Harian',
    'Gaji - Gaji Karyawan',
    'Material - Pembelian Material',
    'Internet - Biaya Bandwidth Internet',
    'Listrik - Biaya Listrik',
    'Sewa - Sewa Tempat',
    'Perawatan - Perawatan Jaringan',
    'Promosi - Biaya Promosi',
    'Lainnya - Pengeluaran Lain-lain'
];

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pengeluaran->tanggal = $_POST['tanggal'];
    $pengeluaran->jenis_pengeluaran = $_POST['jenis_pengeluaran'];
    
    // Clean the amount - remove dots and commas
    $jumlah = $_POST['jumlah'];
    $jumlah = str_replace('.', '', $jumlah);
    $jumlah = str_replace(',', '', $jumlah);
    $pengeluaran->jumlah = (int)$jumlah;
    
    $pengeluaran->keterangan = $_POST['keterangan'];
    
    if($pengeluaran->create()) {
        $_SESSION['success'] = "Data pengeluaran berhasil ditambahkan!";
        header("Location: index.php");
        exit();
    } else {
        $error = "Gagal menambahkan data pengeluaran!";
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
                        <i class="fas fa-plus-circle me-1"></i> Tambah Pengeluaran
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
                                <label for="jenis_pengeluaran" class="form-label">Jenis Pengeluaran <span class="text-danger">*</span></label>
                                <select class="form-select" id="jenis_pengeluaran" name="jenis_pengeluaran" required>
                                    <option value="">-- Pilih Jenis Pengeluaran --</option>
                                    <?php foreach($expense_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Jenis pengeluaran wajib dipilih
                                </div>
                                <div class="form-text">Atau Anda dapat mengetikkan jenis pengeluaran baru</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="jumlah" class="form-label">Jumlah (Rp) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="jumlah" name="jumlah" 
                                       placeholder="Contoh: 500.000 atau 500000" required 
                                       onkeyup="formatRupiah(this)">
                                <div class="invalid-feedback">
                                    Jumlah wajib diisi
                                </div>
                                <div class="form-text">Gunakan format: 500.000 atau 500000</div>
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
    let value = angka.value.replace(/\D/g, '');
    let number = parseInt(value);
    if (!isNaN(number) && number > 0) {
        angka.value = number.toLocaleString('id-ID');
    } else if (value === '') {
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

// Allow custom input for jenis pengeluaran
document.getElementById('jenis_pengeluaran').addEventListener('change', function() {
    if(this.value === '') {
        // Allow custom input - you can add an input field for custom type
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>