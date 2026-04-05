<?php
// Enable error reporting for debugging
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
require_once '../../models/User.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal!</div>");
}

// Initialize models
$user = new User($db);

// Variables for messages
$success_message = '';
$error_message = '';

// Handle Change Password
if(isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if(empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Semua field password harus diisi!";
    } elseif($new_password !== $confirm_password) {
        $error_message = "Password baru dan konfirmasi password tidak cocok!";
    } elseif(strlen($new_password) < 6) {
        $error_message = "Password baru minimal 6 karakter!";
    } else {
        // Get current user
        $current_user = $user->getUserById($_SESSION['user_id']);
        
        if($current_user && password_verify($old_password, $current_user['password'])) {
            // Update password
            $query = "UPDATE users SET password = :password WHERE id = :id";
            $stmt = $db->prepare($query);
            $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt->bindParam(':password', $new_password_hash);
            $stmt->bindParam(':id', $_SESSION['user_id']);
            
            if($stmt->execute()) {
                $success_message = "Password berhasil diubah!";
            } else {
                $error_message = "Gagal mengubah password!";
            }
        } else {
            $error_message = "Password lama salah!";
        }
    }
}

// Handle Add User
if(isset($_POST['action']) && $_POST['action'] == 'add_user') {
    // Only admin can add users
    if($_SESSION['role'] == 'admin') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        
        // Validate input
        if(empty($username) || empty($password) || empty($nama_lengkap) || empty($email)) {
            $error_message = "Semua field harus diisi!";
        } elseif($password !== $confirm_password) {
            $error_message = "Password dan konfirmasi password tidak cocok!";
        } elseif(strlen($password) < 6) {
            $error_message = "Password minimal 6 karakter!";
        } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format email tidak valid!";
        } else {
            // Check if username already exists
            $check_query = "SELECT id FROM users WHERE username = :username";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->execute();
            
            if($check_stmt->rowCount() > 0) {
                $error_message = "Username sudah digunakan!";
            } else {
                // Insert new user
                $insert_query = "INSERT INTO users (username, password, nama_lengkap, email, role) 
                                 VALUES (:username, :password, :nama_lengkap, :email, :role)";
                $insert_stmt = $db->prepare($insert_query);
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $insert_stmt->bindParam(':username', $username);
                $insert_stmt->bindParam(':password', $password_hash);
                $insert_stmt->bindParam(':nama_lengkap', $nama_lengkap);
                $insert_stmt->bindParam(':email', $email);
                $insert_stmt->bindParam(':role', $role);
                
                if($insert_stmt->execute()) {
                    $success_message = "User baru berhasil ditambahkan!";
                    // Clear form data after successful insert
                    $_POST = array();
                } else {
                    $error_message = "Gagal menambahkan user!";
                }
            }
        }
    } else {
        $error_message = "Anda tidak memiliki akses untuk menambahkan user!";
    }
}

// Handle Delete User
if(isset($_GET['delete']) && isset($_GET['id'])) {
    if($_SESSION['role'] == 'admin') {
        $user_id = (int)$_GET['id'];
        
        // Cannot delete own account
        if($user_id == $_SESSION['user_id']) {
            $error_message = "Anda tidak dapat menghapus akun sendiri!";
        } else {
            $delete_query = "DELETE FROM users WHERE id = :id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':id', $user_id);
            
            if($delete_stmt->execute()) {
                $success_message = "User berhasil dihapus!";
            } else {
                $error_message = "Gagal menghapus user!";
            }
        }
    } else {
        $error_message = "Anda tidak memiliki akses untuk menghapus user!";
    }
    header("Location: index.php");
    exit();
}

// Handle Update User Role
if(isset($_POST['action']) && $_POST['action'] == 'update_role') {
    if($_SESSION['role'] == 'admin') {
        $user_id = (int)$_POST['user_id'];
        $new_role = $_POST['role'];
        
        // Cannot change own role
        if($user_id == $_SESSION['user_id']) {
            $error_message = "Anda tidak dapat mengubah role sendiri!";
        } else {
            $update_query = "UPDATE users SET role = :role WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':role', $new_role);
            $update_stmt->bindParam(':id', $user_id);
            
            if($update_stmt->execute()) {
                $success_message = "Role user berhasil diubah!";
            } else {
                $error_message = "Gagal mengubah role user!";
            }
        }
    }
}

// Get all users
$query = "SELECT id, username, nama_lengkap, email, role, created_at FROM users ORDER BY id DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-cog"></i> Pengaturan
        </h1>

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

    <div class="row">
        <!-- Change Password Card -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-key"></i> Ubah Password
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label for="old_password" class="form-label">Password Lama <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="old_password" name="old_password" 
                                       placeholder="Masukkan password lama" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('old_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">
                                Password lama wajib diisi
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Password Baru <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       placeholder="Minimal 6 karakter" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">
                                Password baru wajib diisi (minimal 6 karakter)
                            </div>
                            <div class="form-text">Password minimal 6 karakter</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Konfirmasi password baru" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">
                                Konfirmasi password wajib diisi
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Ubah Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add User Card (Only for Admin) -->
        <?php if($_SESSION['role'] == 'admin'): ?>
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-user-plus"></i> Tambah User Baru
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="add_user">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           placeholder="Masukkan username" required>
                                </div>
                                <div class="invalid-feedback">
                                    Username wajib diisi
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="nama_lengkap" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-circle"></i></span>
                                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" 
                                           placeholder="Masukkan nama lengkap" required>
                                </div>
                                <div class="invalid-feedback">
                                    Nama lengkap wajib diisi
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="Masukkan email" required>
                                </div>
                                <div class="invalid-feedback">
                                    Email wajib diisi dengan format yang benar
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="operator">Operator</option>
                                        <option value="viewer">Viewer (Hanya Baca)</option>
                                    </select>
                                </div>
                                <div class="invalid-feedback">
                                    Role wajib dipilih
                                </div>
                                <div class="form-text">
                                    <strong>Operator:</strong> Dapat mengelola data (CRUD)<br>
                                    <strong>Viewer:</strong> Hanya dapat melihat data
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Minimal 6 karakter" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">
                                    Password wajib diisi (minimal 6 karakter)
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password_user" class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password_user" name="confirm_password" 
                                           placeholder="Konfirmasi password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password_user')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">
                                    Konfirmasi password wajib diisi
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus"></i> Tambah User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- User List Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-users"></i> Daftar User
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="userTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="15%">Username</th>
                                    <th width="20%">Nama Lengkap</th>
                                    <th width="20%">Email</th>
                                    <th width="10%">Role</th>
                                    <th width="15%">Tanggal Dibuat</th>
                                    <th width="15%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach($users as $row): ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td class="text-center">
                                        <?php if($row['role'] == 'admin'): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-crown"></i> Admin
                                            </span>
                                        <?php elseif($row['role'] == 'operator'): ?>
                                            <span class="badge bg-primary">
                                                <i class="fas fa-edit"></i> Operator
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-eye"></i> Viewer
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                    <td class="text-center">
                                        <?php if($_SESSION['role'] == 'admin' && $row['id'] != $_SESSION['user_id']): ?>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                        onclick="showEditRoleModal(<?php echo $row['id']; ?>, '<?php echo $row['role']; ?>', '<?php echo htmlspecialchars($row['username']); ?>')">
                                                    <i class="fas fa-edit"></i> Edit Role
                                                </button>
                                                <a href="?delete=1&id=<?php echo $row['id']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Yakin ingin menghapus user <?php echo htmlspecialchars($row['username']); ?>?')">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </a>
                                            </div>
                                        <?php elseif($row['id'] == $_SESSION['user_id']): ?>
                                            <span class="text-muted">Akun Anda</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                        <p>Tidak ada data user</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

  

<!-- Modal Edit Role -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Edit Role User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit_username" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role Baru</label>
                        <select name="role" id="edit_role" class="form-select" required>
                            <option value="operator">Operator</option>
                            <option value="viewer">Viewer (Hanya Baca)</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Keterangan:</strong><br>
                        - <strong>Operator:</strong> Dapat mengelola data (tambah, edit, hapus)<br>
                        - <strong>Viewer:</strong> Hanya dapat melihat data
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
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

// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
    field.setAttribute('type', type);
    
    // Toggle eye icon
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');
    if (icon) {
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    }
}

// Show edit role modal
function showEditRoleModal(userId, currentRole, username) {
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = currentRole;
    
    var modal = new bootstrap.Modal(document.getElementById('editRoleModal'));
    modal.show();
}

// Auto-hide alerts after 3 seconds
setTimeout(function() {
    let alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(() => {
            let bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 2000);
    });
}, 1000);
</script>

<style>
.border-left-primary { border-left: 4px solid #4e73df !important; }
.border-left-success { border-left: 4px solid #1cc88a !important; }
.border-left-warning { border-left: 4px solid #f6c23e !important; }
.border-left-danger { border-left: 4px solid #e74a3b !important; }
.border-left-info { border-left: 4px solid #36b9cc !important; }
.text-gray-800 { color: #5a5c69 !important; }

.table-responsive {
    overflow-x: auto;
}

.table th, .table td {
    vertical-align: middle;
}

.btn-group .btn {
    margin: 0 2px;
}

.badge {
    font-size: 12px;
    padding: 5px 10px;
}

.card.bg-danger, .card.bg-primary, .card.bg-info {
    transition: transform 0.3s ease;
}

.card.bg-danger:hover, .card.bg-primary:hover, .card.bg-info:hover {
    transform: translateY(-5px);
}

@media print {
    .btn, .pagination, .alert, form, .card-header .btn, .card-footer, .modal {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
        page-break-inside: avoid;
    }
    .table {
        font-size: 10px;
    }
    body {
        padding: 0;
        margin: 0;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>