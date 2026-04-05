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
require_once '../../models/PaymentGateway.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize model
$gateway = new PaymentGateway($db);

$success_message = '';
$error_message = '';

// Handle Add
if(isset($_POST['action']) && $_POST['action'] == 'add') {
    $data = [
        'nama_gateway' => trim($_POST['nama_gateway']),
        'kode_gateway' => trim($_POST['kode_gateway']),
        'merchant_id' => trim($_POST['merchant_id']),
        'api_key' => trim($_POST['api_key']),
        'api_secret' => trim($_POST['api_secret']),
        'api_url' => trim($_POST['api_url']),
        'environment' => $_POST['environment'],
        'minimal_transaksi' => (int)str_replace('.', '', $_POST['minimal_transaksi']),
        'fee_percent' => (float)$_POST['fee_percent'],
        'fee_fixed' => (int)str_replace('.', '', $_POST['fee_fixed']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'is_default' => isset($_POST['is_default']) ? 1 : 0,
        'urutan' => (int)$_POST['urutan'],
        'logo' => trim($_POST['logo']),
        'keterangan' => trim($_POST['keterangan'])
    ];
    
    if($gateway->create($data)) {
        if($data['is_default'] == 1) {
            $gateway->setDefault($db->lastInsertId());
        }
        $success_message = "Payment Gateway berhasil ditambahkan!";
    } else {
        $error_message = "Gagal menambahkan Payment Gateway!";
    }
    header("Location: index.php");
    exit();
}

// Handle Edit
if(isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = (int)$_POST['id'];
    $data = [
        'nama_gateway' => trim($_POST['nama_gateway']),
        'kode_gateway' => trim($_POST['kode_gateway']),
        'merchant_id' => trim($_POST['merchant_id']),
        'api_key' => trim($_POST['api_key']),
        'api_secret' => trim($_POST['api_secret']),
        'api_url' => trim($_POST['api_url']),
        'environment' => $_POST['environment'],
        'minimal_transaksi' => (int)str_replace('.', '', $_POST['minimal_transaksi']),
        'fee_percent' => (float)$_POST['fee_percent'],
        'fee_fixed' => (int)str_replace('.', '', $_POST['fee_fixed']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'is_default' => isset($_POST['is_default']) ? 1 : 0,
        'urutan' => (int)$_POST['urutan'],
        'logo' => trim($_POST['logo']),
        'keterangan' => trim($_POST['keterangan'])
    ];
    
    if($gateway->update($id, $data)) {
        if($data['is_default'] == 1) {
            $gateway->setDefault($id);
        }
        $success_message = "Payment Gateway berhasil diupdate!";
    } else {
        $error_message = "Gagal mengupdate Payment Gateway!";
    }
    header("Location: index.php");
    exit();
}

// Handle Delete
if(isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if($gateway->delete($id)) {
        $success_message = "Payment Gateway berhasil dihapus!";
    } else {
        $error_message = "Gagal menghapus Payment Gateway!";
    }
    header("Location: index.php");
    exit();
}

// Handle Set Default
if(isset($_GET['default']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if($gateway->setDefault($id)) {
        $success_message = "Payment Gateway default berhasil diubah!";
    } else {
        $error_message = "Gagal mengubah Payment Gateway default!";
    }
    header("Location: index.php");
    exit();
}

// Get data for edit
$edit_data = null;
if(isset($_GET['edit']) && isset($_GET['id'])) {
    $edit_data = $gateway->getOne((int)$_GET['id']);
}

// Get all gateways
$gateway_list = $gateway->getAll();

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-globe"></i> Kelola Payment Gateway
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
                <?php echo $edit_data ? 'Edit Payment Gateway' : 'Tambah Payment Gateway'; ?>
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
                        <label class="form-label">Nama Gateway <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_gateway" 
                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['nama_gateway']) : ''; ?>" required>
                        <div class="form-text">Contoh: Midtrans, Xendit, Tripay</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kode Gateway <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kode_gateway" 
                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['kode_gateway']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Merchant ID</label>
                        <input type="text" class="form-control" name="merchant_id" 
                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['merchant_id']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Environment</label>
                        <select class="form-select" name="environment">
                            <option value="sandbox" <?php echo ($edit_data && $edit_data['environment'] == 'sandbox') ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                            <option value="production" <?php echo ($edit_data && $edit_data['environment'] == 'production') ? 'selected' : ''; ?>>Production (Live)</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">API Key</label>
                        <input type="text" class="form-control" name="api_key" 
                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['api_key']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">API Secret</label>
                        <input type="text" class="form-control" name="api_secret" 
                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['api_secret']) : ''; ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">API URL</label>
                    <input type="text" class="form-control" name="api_url" 
                           value="<?php echo $edit_data ? htmlspecialchars($edit_data['api_url']) : ''; ?>">
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Minimal Transaksi</label>
                        <input type="text" class="form-control" name="minimal_transaksi" id="minimal_transaksi"
                               value="<?php echo $edit_data ? number_format($edit_data['minimal_transaksi'], 0, ',', '.') : '0'; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Fee (%)</label>
                        <input type="number" step="0.01" class="form-control" name="fee_percent" 
                               value="<?php echo $edit_data ? $edit_data['fee_percent'] : '0'; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Fee Fixed</label>
                        <input type="text" class="form-control" name="fee_fixed" id="fee_fixed"
                               value="<?php echo $edit_data ? number_format($edit_data['fee_fixed'], 0, ',', '.') : '0'; ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Logo URL</label>
                        <input type="text" class="form-control" name="logo" 
                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['logo']) : ''; ?>">
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

    <!-- List Gateways -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list"></i> Daftar Payment Gateway
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>Kode</th>
                            <th>Environment</th>
                            <th>Min Transaksi</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Default</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no=1; foreach($gateway_list as $gw): ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($gw['nama_gateway']); ?></td>
                            <td><?php echo htmlspecialchars($gw['kode_gateway']); ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $gw['environment'] == 'production' ? 'danger' : 'warning'; ?>">
                                    <?php echo ucfirst($gw['environment']); ?>
                                </span>
                            </td>
                            <td class="text-end">Rp <?php echo number_format($gw['minimal_transaksi'], 0, ',', '.'); ?></td>
                            <td class="text-end">
                                <?php if($gw['fee_percent'] > 0): ?>
                                    <?php echo $gw['fee_percent']; ?>%
                                <?php endif; ?>
                                <?php if($gw['fee_fixed'] > 0): ?>
                                    + Rp <?php echo number_format($gw['fee_fixed'], 0, ',', '.'); ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($gw['is_active']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($gw['is_default']): ?>
                                    <span class="badge bg-primary">Default</span>
                                <?php else: ?>
                                    <a href="?default=1&id=<?php echo $gw['id']; ?>" class="btn btn-sm btn-outline-primary"
                                       onclick="return confirm('Jadikan gateway ini sebagai default?')">
                                        Jadikan Default
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="?edit=1&id=<?php echo $gw['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=1&id=<?php echo $gw['id']; ?>" class="btn btn-sm btn-danger"
                                       onclick="return confirm('Yakin ingin menghapus gateway ini?')">
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

document.getElementById('minimal_transaksi')?.addEventListener('input', function() { formatRupiah(this); });
document.getElementById('fee_fixed')?.addEventListener('input', function() { formatRupiah(this); });
</script>

<?php require_once '../../includes/footer.php'; ?>