<?php
ob_start();
session_start();
if(!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
require_once '../../config/database.php';
require_once '../../models/Pelanggan.php';
require_once '../../includes/header.php';

$database = new Database();
$db = $database->getConnection();
$pelanggan = new Pelanggan($db);

if(isset($_GET['delete'])) {
    $pelanggan->id = $_GET['delete'];
    if($pelanggan->delete()) { $_SESSION['success'] = "Data berhasil dihapus!"; header("Location: index.php"); exit(); }
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$stmt = $pelanggan->read($search, $limit, $offset);
$total = $pelanggan->getTotal($search);
$total_pages = ceil($total / $limit);
?>

<div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between">
        <h5><i class="fas fa-users"></i> Data Pelanggan</h5>
        <a href="create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tambah</a>
    </div>
    <div class="card-body">
        <form method="GET" class="row mb-3">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Cari..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Cari</button>
                    <?php if($search): ?><a href="index.php" class="btn btn-secondary">Reset</a><?php endif; ?>
                </div>
            </div>
        </form>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>No</th><th>ID</th><th>Nama</th><th>Alamat</th><th>No HP</th><th>Paket</th><th>Harga</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php $no = $offset+1; while($row = $stmt->fetch()): ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo $row['id_pelanggan']; ?></td>
                        <td><?php echo $row['nama']; ?></td>
                        <td><?php echo $row['alamat']; ?></td>
                        <td><?php echo $row['no_hp']; ?></td>
                        <td><?php echo $row['paket_internet']; ?></td>
                        <td>Rp <?php echo number_format($row['harga_paket'],0,',','.'); ?></td>
                        <td><span class="badge bg-<?php echo $row['status']=='aktif'?'success':'danger'; ?>"><?php echo $row['status']; ?></span></td>
                        <td>
                            <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                            <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; if($stmt->rowCount()==0): ?><tr><td colspan="9" class="text-center">Tidak ada data</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_pages>1): ?>
        <nav><ul class="pagination justify-content-center">
            <?php for($i=1;$i<=$total_pages;$i++): ?>
            <li class="page-item <?php echo $i==$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a></li>
            <?php endfor; ?>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>