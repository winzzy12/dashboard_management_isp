<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION['user_id'])) {
    header("Location: views/login.php");
    exit();
}

// Load database connection for sidebar
require_once __DIR__ . '/../config/database.php';
$database = new Database();
$db = $database->getConnection();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Dashboard Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            overflow-x: hidden;
        }
        
        /* Wrapper */
        .wrapper {
            display: flex;
            width: 100%;
            position: relative;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transition: all 0.3s ease-in-out;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 1050;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        /* Sidebar collapsed state (hidden) */
        .sidebar.collapsed {
            left: -280px;
        }
        
        /* For mobile: visible state */
        .sidebar.visible {
            left: 0;
        }
        
        /* Content Styles */
        .content {
            flex: 1;
            min-height: 100vh;
            transition: all 0.3s ease-in-out;
            width: calc(100% - 280px);
            margin-left: 280px;
            position: relative;
        }
        
        /* When sidebar is collapsed, content takes full width */
        .content.expanded {
            margin-left: 0;
            width: 100%;
        }
        
        /* Navbar Styles */
        .navbar {
            padding: 15px 20px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            position: sticky;
            top: 0;
            z-index: 1040;
        }
        
        /* Toggle Button */
        .toggle-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .toggle-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 2px 8px rgba(102,126,234,0.4);
        }
        
        .toggle-btn i {
            font-size: 18px;
        }
        
        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1045;
            display: none;
            transition: all 0.3s ease;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        /* Sidebar Header */
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header i {
            font-size: 40px;
        }
        
        .sidebar-header h4 {
            margin: 10px 0 5px;
            font-size: 1.2rem;
        }
        
        .sidebar-header small {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        /* Sidebar Menu */
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 20px 0 20px 0;
        }
        
        .sidebar-menu li {
            margin: 5px 0;
        }
        
        .sidebar-menu > li > a {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            border-radius: 8px;
            margin: 0 10px;
        }
        
        .sidebar-menu > li > a:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 25px;
        }
        
        .sidebar-menu > li.active > a {
            background: rgba(255,255,255,0.2);
            border-left: 3px solid white;
        }
        
        .sidebar-menu li a i {
            width: 25px;
            margin-right: 10px;
            font-size: 18px;
        }
        
        /* Submenu Styles - for Pengaturan only */
        .sidebar-menu .has-submenu {
            position: relative;
        }
        
        .sidebar-menu .has-submenu > a {
            cursor: pointer;
        }
        
        .sidebar-menu .submenu {
            list-style: none;
            padding-left: 45px;
            margin: 0;
            display: none;
            background: rgba(0,0,0,0.2);
            border-radius: 8px;
            margin: 5px 10px;
        }
        
        .sidebar-menu .submenu.show {
            display: block;
        }
        
        .sidebar-menu .submenu li {
            margin: 0;
        }
        
        .sidebar-menu .submenu li a {
            padding: 10px 15px;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.9);
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s;
            border-radius: 5px;
        }
        
        .sidebar-menu .submenu li a:hover {
            background: rgba(255,255,255,0.15);
            padding-left: 20px;
            color: white;
        }
        
        .sidebar-menu .submenu li.active a {
            background: rgba(255,255,255,0.2);
            color: white;
            border-left: 2px solid white;
        }
        
        .sidebar-menu .submenu li a i {
            width: 20px;
            margin-right: 8px;
            font-size: 12px;
        }
        
        .sidebar-menu .has-submenu > a .chevron {
            margin-left: auto;
            transition: transform 0.3s ease;
            font-size: 12px;
        }
        
        .sidebar-menu .has-submenu.active > a .chevron {
            transform: rotate(90deg);
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
            }
            
            .sidebar.visible {
                left: 0;
            }
            
            .content {
                margin-left: 0;
                width: 100%;
            }
            
            .toggle-btn span {
                display: inline;
            }
            
            .navbar {
                padding: 10px 15px;
            }
        }
        
        /* Desktop styles - sidebar visible by default */
        @media (min-width: 769px) {
            .sidebar {
                left: 0;
            }
            
            .sidebar.collapsed {
                left: -280px;
            }
            
            .content {
                margin-left: 280px;
                width: calc(100% - 280px);
            }
            
            .content.expanded {
                margin-left: 0;
                width: 100%;
            }
            
            .sidebar-overlay {
                display: none !important;
            }
        }
        
        /* Tablet styles */
        @media (min-width: 769px) and (max-width: 1024px) {
            .sidebar {
                width: 260px;
            }
            
            .content {
                margin-left: 260px;
                width: calc(100% - 260px);
            }
        }
        
        /* Utility Classes */
        .border-left-primary { border-left: 4px solid #4e73df !important; }
        .border-left-success { border-left: 4px solid #1cc88a !important; }
        .border-left-warning { border-left: 4px solid #f6c23e !important; }
        .border-left-danger { border-left: 4px solid #e74a3b !important; }
        .text-gray-800 { color: #5a5c69 !important; }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 5px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #764ba2;
        }
        
        /* Container fluid padding */
        .container-fluid {
            padding: 0 20px;
        }
        
        /* Table responsive */
        .table-responsive {
            overflow-x: auto;
        }
        
        /* Clearfix */
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Overlay for mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-network-wired"></i>
            <h4>Dashboard</h4>
            <small>Management System</small>
        </div>
        
        <ul class="sidebar-menu">
            <?php
            $current_page = basename($_SERVER['PHP_SELF']);
            $current_dir = basename(dirname($_SERVER['PHP_SELF']));
            $base_url = '';
            
            // Determine base URL
            if($current_dir == 'views' || $current_dir == 'rt_rw_net' || $current_dir == '') {
                $base_url = '';
            } elseif(in_array($current_dir, ['pelanggan','dashboard','material','pemasukan','pengeluaran','billing','laporan','pengaturan','paket','pop','vpn','rekening','info'])) {
                $base_url = '../../';
            } else {
                $base_url = '';
            }
            
            // Get pending count for info menu
            $pending_count = 0;
            try {
                $query_pending = "SELECT COUNT(*) as total FROM transaksi_pembayaran WHERE status = 'pending'";
                $stmt_pending = $db->prepare($query_pending);
                $stmt_pending->execute();
                $pending_count = $stmt_pending->fetchColumn();
            } catch(Exception $e) {
                $pending_count = 0;
            }
            ?>
            
            <li class="<?php echo ($current_dir == 'dashboard') ? 'active' : ''; ?>">
                <a href="<?php echo $base_url; ?>views/dashboard/index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            
            <li class="<?php echo ($current_dir == 'pelanggan') ? 'active' : ''; ?>">
                <a href="<?php echo $base_url; ?>views/pelanggan/index.php">
                    <i class="fas fa-users"></i> Data Pelanggan
                </a>
            </li>
            
            <li class="<?php echo ($current_dir == 'material') ? 'active' : ''; ?>">
                <a href="<?php echo $base_url; ?>views/material/index.php">
                    <i class="fas fa-boxes"></i> Data Material
                </a>
            </li>
            
            <li class="<?php echo ($current_dir == 'pemasukan') ? 'active' : ''; ?>">
                <a href="<?php echo $base_url; ?>views/pemasukan/index.php">
                    <i class="fas fa-money-bill-wave"></i> Pemasukan
                </a>
            </li>
            
            <li class="<?php echo ($current_dir == 'pengeluaran') ? 'active' : ''; ?>">
                <a href="<?php echo $base_url; ?>views/pengeluaran/index.php">
                    <i class="fas fa-chart-line"></i> Pengeluaran
                </a>
            </li>
            
            <li class="<?php echo ($current_dir == 'billing') ? 'active' : ''; ?>">
                <a href="<?php echo $base_url; ?>views/billing/index.php">
                    <i class="fas fa-file-invoice"></i> Billing/Tagihan
                </a>
            </li>
            
            <!-- Info Pembayaran Menu -->
            <li class="<?php echo ($current_dir == 'info') ? 'active' : ''; ?>">
                <a href="<?php echo $base_url; ?>views/info/index.php">
                    <i class="fas fa-credit-card"></i> Info Pembayaran
                    <?php if($pending_count > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-2"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="<?php echo ($current_dir == 'rekening') ? 'active' : ''; ?>">
                <a href="<?php echo $base_url; ?>views/rekening/index.php">
                    <i class="fas fa-university"></i> Rekening
                </a>
            </li>
            
            <li class="<?php echo ($current_dir == 'laporan') ? 'active' : ''; ?>">
                <a href="<?php echo $base_url; ?>views/laporan/index.php">
                    <i class="fas fa-print"></i> Laporan
                </a>
            </li>
            
            <li class="<?php echo ($current_dir == 'pop') ? 'active' : ''; ?>">
                <a href="<?php echo $base_url; ?>views/pop/index.php">
                    <i class="fas fa-satellite-dish"></i> POP/ODP
                </a>
            </li>
            
            <!-- Menu Kelola Paket -->
            <li class="<?php echo ($current_dir == 'paket') ? 'active' : ''; ?>">
                <a href="<?php echo $base_url; ?>views/paket/index.php">
                    <i class="fas fa-tags"></i> Kelola Paket
                </a>
            </li>
            
<!-- Menu Pengaturan dengan Submenu -->
<li class="has-submenu <?php echo ($current_dir == 'pengaturan') ? 'active' : ''; ?>">
    <a href="javascript:void(0);">
        <i class="fas fa-cog"></i> Pengaturan
        <i class="fas fa-chevron-right chevron"></i>
    </a>
    <ul class="submenu">
        <li class="<?php echo ($current_dir == 'pengaturan' && $current_page == 'index.php') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>views/pengaturan/index.php">
                <i class="fas fa-user-cog"></i> User Management
            </a>
        </li>
        <li class="<?php echo ($current_dir == 'pengaturan' && $current_page == 'fix_database.php') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>views/pengaturan/fix_database.php">
                <i class="fas fa-database"></i> Fix Database
            </a>
        </li>
                <li class="<?php echo ($current_dir == 'pengaturan' && $current_page == 'mikrotik.php') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>views/pengaturan/mikrotik.php">
                <i class="fas fa-microchip"></i> Konfig Mikrotik
            </a>
        </li>
    </ul>
</li>
            
            <li>
                <a href="<?php echo $base_url; ?>logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Content -->
    <div class="content" id="content">
        <nav class="navbar">
            <div class="container-fluid">
                <button type="button" class="toggle-btn" id="toggleSidebarBtn">
                    <i class="fas fa-bars"></i> <span>Menu</span>
                </button>
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?php echo $_SESSION['nama_lengkap']; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <div class="container-fluid">