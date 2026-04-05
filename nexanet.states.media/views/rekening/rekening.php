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

// Check role
if($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'operator') {
    $_SESSION['error'] = "Anda tidak memiliki akses!";
    header("Location: index.php");
    exit();
}

// Include required files
require_once '../../config/database.php';
require_once '../../models/RekeningBank.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize model
$rekeningBank = new RekeningBank($db);

$success_message = '';
$error_message = '';

// Handle Add
if(isset($_POST['action']) && $_POST['action'] == 'add') {
    // Validate required fields
    $kode_bank = trim($_POST['kode_bank']);
    $nama_bank = trim($_POST['nama_bank']);
    $nomor_rekening = trim($_POST['nomor_rekening']);
    $nama_pemilik = trim($_POST['nama_pemilik']);
    
    if(empty($kode_bank) || empty($nama_bank) || empty($nomor_rekening) || empty($nama_pemilik)) {
        $error_message = "Semua field wajib diisi!";
    } else {
        $data = [
            'kode_bank' => $kode_bank,
            'nama_bank' => $nama_bank,
            'nomor_rekening' => $nomor_rekening,
            'nama_pemilik' => $nama_pemilik,
            'cabang' => trim($_POST['cabang']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_default' => isset($_POST['is_default']) ? 1 : 0,
            'urutan' => (int)$_POST['urutan'],
            'keterangan' => trim($_POST['keterangan'])
        ];
        
        if($rekeningBank->create($data)) {
            // If this is default, unset others
            if($data['is_default'] == 1) {
                $rekeningBank->setDefault($db->lastInsertId());
            }
            $success_message = "Rekening bank berhasil ditambahkan!";
        } else {
            $error_message = "Gagal menambahkan rekening bank!";
        }
    }
    header("Location: index.php");
    exit();
}

// Handle Edit
if(isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = (int)$_POST['id'];
    
    // Validate required fields
    $kode_bank = trim($_POST['kode_bank']);
    $nama_bank = trim($_POST['nama_bank']);
    $nomor_rekening = trim($_POST['nomor_rekening']);
    $nama_pemilik = trim($_POST['nama_pemilik']);
    
    if(empty($kode_bank) || empty($nama_bank) || empty($nomor_rekening) || empty($nama_pemilik)) {
        $error_message = "Semua field wajib diisi!";
    } else {
        $data = [
            'kode_bank' => $kode_bank,
            'nama_bank' => $nama_bank,
            'nomor_rekening' => $nomor_rekening,
            'nama_pemilik' => $nama_pemilik,
            'cabang' => trim($_POST['cabang']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_default' => isset($_POST['is_default']) ? 1 : 0,
            'urutan' => (int)$_POST['urutan'],
            'keterangan' => trim($_POST['keterangan'])
        ];
        
        if($rekeningBank->update($id, $data)) {
            if($data['is_default'] == 1) {
                $rekeningBank->setDefault($id);
            }
            $success_message = "Rekening bank berhasil diupdate!";
        } else {
            $error_message = "Gagal mengupdate rekening bank!";
        }
    }
    header("Location: index.php");
    exit();
}

// Handle Delete
if(isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if($rekeningBank->delete($id)) {
        $success_message = "Rekening bank berhasil dihapus!";
    } else {
        $error_message = "Gagal menghapus rekening bank!";
    }
    header("Location: index.php");
    exit();
}

// Handle Set Default
if(isset($_GET['default']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if($rekeningBank->setDefault($id)) {
        $success_message = "Rekening default berhasil diubah!";
    } else {
        $error_message = "Gagal mengubah rekening default!";
    }
    header("Location: index.php");
    exit();
}

// Get data for edit
$edit_data = null;
if(isset($_GET['edit']) && isset($_GET['id'])) {
    $edit_data = $rekeningBank->getOne((int)$_GET['id']);
}

// Get all rekening
$rekening_list = $rekeningBank->getAll();

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-university"></i> Kelola Rekening Bank
        </h1>
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Form Add/Edit -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-<?php echo $edit_data ? 'edit' : 'plus'; ?>"></i> 
                <?php echo $edit_data ? 'Edit Rekening Bank' : 'Tambah Rekening Bank'; ?>
            </h6>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $edit_data ? 'edit' : 'add'; ?>">
                <?php if($edit_data): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kode Bank <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kode_bank" 
                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['kode_bank']) : ''; ?>" required>
                        <div class="form-text">Contoh: BCA, MANDIRI, BRI, BNI, BSI</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Bank <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_bank" 
                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['nama_bank']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nomor Rekening <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nomor_rekening" id="nomor_rekening"
                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['nomor_rekening']) : ''; ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Pemilik <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_pemilik" 
                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['nama_pemilik']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cabang</label>
                        <input type="text" class="form-control" name="cabang" 
                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['cabang']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Urutan</label>
                        <input type="number" class="form-control" name="urutan" 
                               value="<?php echo $edit_data ? $edit_data['urutan'] : '0'; ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="is_active" 
                                   <?php echo ($edit_data && $edit_data['is_active']) || !$edit_data ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Aktif</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_default" id="is_default" 
                                   <?php echo ($edit_data && $edit_data['is_default']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_default">Jadikan Default</label>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Keterangan</label>
                    <textarea class="form-control" name="keterangan" rows="2"><?php echo $edit_data ? htmlspecialchars($edit_data['keterangan']) : ''; ?></textarea>
                </div>
                
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- List Rekening -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list"></i> Daftar Rekening Bank
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Kode</th>
                            <th>Nama Bank</th>
                            <th>No Rekening</th>
                            <th>Atas Nama</th>
                            <th>Status</th>
                            <th>Default</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no=1; foreach($rekening_list as $rek): ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($rek['kode_bank']); ?></td>
                            <td><?php echo htmlspecialchars($rek['nama_bank']); ?></td>
                            <td><?php echo htmlspecialchars($rek['nomor_rekening']); ?></td>
                            <td><?php echo htmlspecialchars($rek['nama_pemilik']); ?></td>
                            <td class="text-center">
                                <?php if($rek['is_active']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($rek['is_default']): ?>
                                    <span class="badge bg-primary">Default</span>
                                <?php else: ?>
                                    <a href="?default=1&id=<?php echo $rek['id']; ?>" class="btn btn-sm btn-outline-primary"
                                       onclick="return confirm('Jadikan rekening ini sebagai default?')">
                                        Jadikan Default
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="?edit=1&id=<?php echo $rek['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=1&id=<?php echo $rek['id']; ?>" class="btn btn-sm btn-danger"
                                       onclick="return confirm('Yakin ingin menghapus rekening ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($rekening_list)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">Belum ada data rekening</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('nomor_rekening')?.addEventListener('input', function(e) {
    let value = this.value.replace(/\D/g, '');
    let formatted = value.replace(/(\d{4})(?=\d)/g, '$1 ');
    this.value = formatted.trim();
});
</script>

<?php require_once '../../includes/footer.php'; ?>