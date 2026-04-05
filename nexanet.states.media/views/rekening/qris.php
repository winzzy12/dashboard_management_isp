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
require_once '../../models/Qris.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize model
$qris = new Qris($db);

$success_message = '';
$error_message = '';

// Handle Add
if(isset($_POST['action']) && $_POST['action'] == 'add') {
    $data = [
        'nama' => trim($_POST['nama']),
        'provider' => trim($_POST['provider']),
        'qris_code' => trim($_POST['qris_code']),
        'qris_image' => trim($_POST['qris_image']),
        'nominal_min' => (int)str_replace('.', '', $_POST['nominal_min']),
        'nominal_max' => (int)str_replace('.', '', $_POST['nominal_max']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'is_default' => isset($_POST['is_default']) ? 1 : 0,
        'urutan' => (int)$_POST['urutan'],
        'keterangan' => trim($_POST['keterangan'])
    ];
    
    if($qris->create($data)) {
        if($data['is_default'] == 1) {
            $qris->setDefault($db->lastInsertId());
        }
        $success_message = "QRIS berhasil ditambahkan!";
    } else {
        $error_message = "Gagal menambahkan QRIS!";
    }
    header("Location: index.php");
    exit();
}

// Handle Edit
if(isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = (int)$_POST['id'];
    $data = [
        'nama' => trim($_POST['nama']),
        'provider' => trim($_POST['provider']),
        'qris_code' => trim($_POST['qris_code']),
        'qris_image' => trim($_POST['qris_image']),
        'nominal_min' => (int)str_replace('.', '', $_POST['nominal_min']),
        'nominal_max' => (int)str_replace('.', '', $_POST['nominal_max']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'is_default' => isset($_POST['is_default']) ? 1 : 0,
        'urutan' => (int)$_POST['urutan'],
        'keterangan' => trim($_POST['keterangan'])
    ];
    
    if($qris->update($id, $data)) {
        if($data['is_default'] == 1) {
            $qris->setDefault($id);
        }
        $success_message = "QRIS berhasil diupdate!";
    } else {
        $error_message = "Gagal mengupdate QRIS!";
    }
    header("Location: index.php");
    exit();
}

// Handle Delete
if(isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if($qris->delete($id)) {
        $success_message = "QRIS berhasil dihapus!";
    } else {
        $error_message = "Gagal menghapus QRIS!";
    }
    header("Location: index.php");
    exit();
}

// Handle Set Default
if(isset($_GET['default']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if($qris->setDefault($id)) {
        $success_message = "QRIS default berhasil diubah!";
    } else {
        $error_message = "Gagal mengubah QRIS default!";
    }
    header("Location: index.php");
    exit();
}

// Get data for edit
$edit_data = null;
if(isset($_GET['edit']) && isset($_GET['id'])) {
    $edit_data = $qris->getOne((int)$_GET['id']);
}

// Get all QRIS
$qris_list = $qris->getAll();

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-qrcode"></i> Kelola QRIS
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
                <?php echo $edit_data ? 'Edit QRIS' : 'Tambah QRIS'; ?>
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
                        <label class="form-label">Nama QRIS <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama" 
                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['nama']) : ''; ?>" required>
                        <div class="form-text">Contoh: QRIS BCA, QRIS Mandiri</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Provider <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="provider" 
                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['provider']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">QRIS Code</label>
                        <textarea class="form-control" name="qris_code" rows="3"><?php echo $edit_data ? htmlspecialchars($edit_data['qris_code']) : ''; ?></textarea>
                        <div class="form-text">Kode QRIS (string)</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">QRIS Image URL</label>
                        <input type="text" class="form-control" name="qris_image" 
                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['qris_image']) : ''; ?>">
                        <div class="form-text">URL gambar QRIS</div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nominal Minimal</label>
                        <input type="text" class="form-control" name="nominal_min" id="nominal_min"
                               value="<?php echo $edit_data ? number_format($edit_data['nominal_min'], 0, ',', '.') : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nominal Maksimal</label>
                        <input type="text" class="form-control" name="nominal_max" id="nominal_max"
                               value="<?php echo $edit_data ? number_format($edit_data['nominal_max'], 0, ',', '.') : ''; ?>">
                    </div>
                </div>
                
                <div class="row">
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

    <!-- List QRIS -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list"></i> Daftar QRIS
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>Provider</th>
                            <th>Min/Max</th>
                            <th>Status</th>
                            <th>Default</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no=1; foreach($qris_list as $qr): ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($qr['nama']); ?></td>
                            <td><?php echo htmlspecialchars($qr['provider']); ?></td>
                            <td>Rp <?php echo number_format($qr['nominal_min'], 0, ',', '.'); ?> - <?php echo number_format($qr['nominal_max'], 0, ',', '.'); ?></td>
                            <td class="text-center">
                                <?php if($qr['is_active']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($qr['is_default']): ?>
                                    <span class="badge bg-primary">Default</span>
                                <?php else: ?>
                                    <a href="?default=1&id=<?php echo $qr['id']; ?>" class="btn btn-sm btn-outline-primary"
                                       onclick="return confirm('Jadikan QRIS ini sebagai default?')">
                                        Jadikan Default
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="?edit=1&id=<?php echo $qr['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=1&id=<?php echo $qr['id']; ?>" class="btn btn-sm btn-danger"
                                       onclick="return confirm('Yakin ingin menghapus QRIS ini?')">
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
</div>

<script>
function formatRupiah(element) {
    let value = element.value.replace(/\D/g, '');
    if(value) {
        element.value = new Intl.NumberFormat('id-ID').format(value);
    }
}

document.getElementById('nominal_min')?.addEventListener('input', function() { formatRupiah(this); });
document.getElementById('nominal_max')?.addEventListener('input', function() { formatRupiah(this); });
</script>

<?php require_once '../../includes/footer.php'; ?>