<?php
ob_start();
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../../config/database.php';
require_once '../../models/Material.php';
require_once '../../includes/header.php';

$database = new Database();
$db = $database->getConnection();
$material = new Material($db);

if(!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$material->id = $_GET['id'];
$data = $material->getOne();

if(!$data) {
    header("Location: index.php");
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $material->nama_material = trim($_POST['nama_material']);
    $material->stok = $_POST['stok'];
    $material->harga = $_POST['harga'];
    $material->keterangan = trim($_POST['keterangan']);
    
    if($material->update()) {
        $_SESSION['success'] = "Material berhasil diupdate!";
        header("Location: index.php");
        exit();
    } else {
        $error = "Gagal mengupdate material!";
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h5 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-edit me-1"></i> Edit Material
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
                            <div class="col-md-8 mb-3">
                                <label for="nama_material" class="form-label">Nama Material <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nama_material" name="nama_material" 
                                       value="<?php echo htmlspecialchars($data['nama_material']); ?>" required>
                                <div class="invalid-feedback">
                                    Nama material wajib diisi
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="stok" class="form-label">Stok <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="stok" name="stok" 
                                       value="<?php echo $data['stok']; ?>" min="0" required>
                                <div class="invalid-feedback">
                                    Stok wajib diisi
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="harga" class="form-label">Harga Satuan (Rp) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="harga" name="harga" 
                                       value="<?php echo $data['harga']; ?>" min="0" required>
                                <div class="invalid-feedback">
                                    Harga wajib diisi
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="keterangan" class="form-label">Keterangan</label>
                                <input type="text" class="form-control" id="keterangan" name="keterangan" 
                                       value="<?php echo htmlspecialchars($data['keterangan']); ?>">
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