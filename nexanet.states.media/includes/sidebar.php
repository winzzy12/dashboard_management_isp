<?php
// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

function isActive($page, $dir = '') {
    global $current_page, $current_dir;
    if($dir) {
        return ($current_dir == $dir) ? 'active' : '';
    }
    return ($current_page == $page) ? 'active' : '';
}

// Base URL for navigation
$base_url = '../';
if($current_dir == 'views' || $current_dir == 'rt_rw_net') {
    $base_url = '';
} elseif(in_array($current_dir, ['pelanggan','material','pemasukan','pengeluaran','billing','laporan','pengaturan'])) {
    $base_url = '../../';
} else {
    $base_url = '';
}
?>

<nav id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <i class="fas fa-network-wired fa-2x"></i>
            <h4>Dashboard</h4>
            <small>Management System</small>
        </div>
    </div>
    
    <ul class="list-unstyled components">
        <li class="<?php echo ($current_page == 'index.php' && $current_dir == 'rt_rw_net') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>index.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li class="<?php echo ($current_dir == 'pelanggan') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>views/pelanggan/index.php">
                <i class="fas fa-users"></i>
                <span>Data Pelanggan</span>
            </a>
        </li>
        
        <li class="<?php echo ($current_dir == 'material') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>views/material/index.php">
                <i class="fas fa-boxes"></i>
                <span>Data Material</span>
            </a>
        </li>
        
        <li class="<?php echo ($current_dir == 'pemasukan') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>views/pemasukan/index.php">
                <i class="fas fa-money-bill-wave"></i>
                <span>Pemasukan</span>
            </a>
        </li>
        
        <li class="<?php echo ($current_dir == 'pengeluaran') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>views/pengeluaran/index.php">
                <i class="fas fa-chart-line"></i>
                <span>Pengeluaran</span>
            </a>
        </li>
        
        <li class="<?php echo ($current_dir == 'billing') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>views/billing/index.php">
                <i class="fas fa-file-invoice"></i>
                <span>Billing/Tagihan</span>
            </a>
        </li>
        
        <li class="<?php echo ($current_dir == 'laporan') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>views/laporan/index.php">
                <i class="fas fa-print"></i>
                <span>Laporan</span>
            </a>
        </li>
        
        
        
        
        
        <li class="<?php echo ($current_dir == 'pengaturan') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>views/pengaturan/index.php">
                <i class="fas fa-cog"></i>
                <span>Pengaturan</span>
            </a>
        </li>
        
        <!-- Di dalam sidebar-menu, tambahkan setelah pengaturan atau sebelum logout -->
<li class="<?php echo ($current_dir == 'paket') ? 'active' : ''; ?>">
    <a href="<?php echo $base_url; ?>views/pengaturan/paket.php">
        <i class="fas fa-tags"></i> Kelola Paket
    </a>
</li>
        
        <li>
            <a href="<?php echo $base_url; ?>logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <div>
                <strong><?php echo isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : 'User'; ?></strong>
                <small><?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'Operator'; ?></small>
            </div>
        </div>
    </div>
</nav>

<style>
#sidebar {
    width: 250px;
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    z-index: 999;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    transition: all 0.3s ease-in-out;
}

#sidebar .sidebar-header {
    padding: 20px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

#sidebar .sidebar-header h4 {
    margin: 10px 0 5px;
    font-size: 1.2rem;
}

#sidebar .sidebar-header small {
    font-size: 0.8rem;
    opacity: 0.8;
}

#sidebar ul.components {
    padding: 0;
    margin: 0;
    list-style: none;
}

#sidebar ul li a {
    padding: 12px 20px;
    display: block;
    color: white;
    text-decoration: none;
    transition: all 0.3s;
}

#sidebar ul li a:hover {
    background: rgba(255,255,255,0.1);
    padding-left: 25px;
}

#sidebar ul li.active a {
    background: rgba(255,255,255,0.2);
    border-left: 3px solid white;
}

#sidebar ul li a i {
    width: 25px;
    margin-right: 10px;
}

#sidebar .sidebar-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 15px 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

#sidebar .user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

#sidebar .user-info i {
    font-size: 2rem;
}

#sidebar .user-info div {
    display: flex;
    flex-direction: column;
}

#sidebar .user-info strong {
    font-size: 0.9rem;
}

#sidebar .user-info small {
    font-size: 0.7rem;
    opacity: 0.8;
}

@media (max-width: 768px) {
    #sidebar {
        margin-left: -250px;
    }
    #sidebar.active {
        margin-left: 0;
    }
}
</style>