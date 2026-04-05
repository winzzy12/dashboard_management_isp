-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 05, 2026 at 10:19 PM
-- Server version: 8.0.35
-- PHP Version: 8.3.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nexanet_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_tambah_paket` (IN `p_nama_paket` VARCHAR(100), IN `p_kecepatan` VARCHAR(50), IN `p_harga` DECIMAL(10,2), IN `p_keterangan` TEXT, IN `p_is_active` TINYINT)   BEGIN
    INSERT INTO paket_internet (nama_paket, kecepatan, harga, keterangan, is_active)
    VALUES (p_nama_paket, p_kecepatan, p_harga, p_keterangan, p_is_active);
    
    SELECT LAST_INSERT_ID() as id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_harga_paket` (IN `p_id` INT, IN `p_harga_baru` DECIMAL(10,2))   BEGIN
    DECLARE v_old_harga DECIMAL(10,2);
    
    -- Ambil harga lama
    SELECT harga INTO v_old_harga FROM paket_internet WHERE id = p_id;
    
    -- Update harga
    UPDATE paket_internet SET harga = p_harga_baru WHERE id = p_id;
    
    -- Log perubahan harga (opsional, buat tabel log terlebih dahulu)
    INSERT INTO log_harga_paket (paket_id, harga_lama, harga_baru, tanggal)
    VALUES (p_id, v_old_harga, p_harga_baru, NOW());
    
    SELECT 'Harga paket berhasil diupdate' as message;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `id` int NOT NULL,
  `kode_transaksi` varchar(50) DEFAULT NULL,
  `pelanggan_id` int NOT NULL,
  `bulan` int NOT NULL,
  `tahun` int NOT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `status` enum('lunas','belum_lunas') DEFAULT 'belum_lunas',
  `payment_token` varchar(100) DEFAULT NULL,
  `reminder_sent` tinyint DEFAULT '0',
  `tanggal_jatuh_tempo` date DEFAULT NULL,
  `tanggal_bayar` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `konfigurasi`
--

CREATE TABLE `konfigurasi` (
  `id` int NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `value` text,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `konfigurasi`
--

INSERT INTO `konfigurasi` (`id`, `key_name`, `value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'app_url', 'https://nexanet.states.media', 'URL utama aplikasi', '2026-04-02 09:57:30', '2026-04-02 10:08:29'),
(2, 'payment_url', 'https://nexanet.states.media/views/payment/invoice.php', 'URL untuk link pembayaran', '2026-04-02 09:57:30', '2026-04-02 09:57:30');

-- --------------------------------------------------------

--
-- Table structure for table `log_harga_paket`
--

CREATE TABLE `log_harga_paket` (
  `id` int NOT NULL,
  `paket_id` int NOT NULL,
  `harga_lama` decimal(10,2) DEFAULT NULL,
  `harga_baru` decimal(10,2) DEFAULT NULL,
  `tanggal` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `material`
--

CREATE TABLE `material` (
  `id` int NOT NULL,
  `nama_material` varchar(100) NOT NULL,
  `stok` int DEFAULT '0',
  `harga` decimal(10,2) DEFAULT NULL,
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `mikrotik_config`
--

CREATE TABLE `mikrotik_config` (
  `id` int NOT NULL,
  `host` varchar(100) NOT NULL,
  `port` int DEFAULT '22',
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `mikrotik_config`
--

INSERT INTO `mikrotik_config` (`id`, `host`, `port`, `username`, `password`, `is_active`, `created_at`, `updated_at`) VALUES
(2, '192.168.1.1', 22, 'admin', 'admin', 1, '2026-04-03 04:52:33', '2026-04-03 05:06:28');

-- --------------------------------------------------------

--
-- Table structure for table `odp`
--

CREATE TABLE `odp` (
  `id` int NOT NULL,
  `kode_odp` varchar(20) NOT NULL,
  `nama_odp` varchar(100) NOT NULL,
  `pop_id` int DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `alamat` text,
  `jumlah_port` int DEFAULT '0',
  `port_terpakai` int DEFAULT '0',
  `status` enum('aktif','penuh','nonaktif') DEFAULT 'aktif',
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `paket_internet`
--

CREATE TABLE `paket_internet` (
  `id` int NOT NULL,
  `nama_paket` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kecepatan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `paket_internet`
--

INSERT INTO `paket_internet` (`id`, `nama_paket`, `kecepatan`, `harga`, `keterangan`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Silver', '20 Mbps', 250000.00, '', 1, '2026-04-03 01:44:14', '2026-04-03 01:44:14');

--
-- Triggers `paket_internet`
--
DELIMITER $$
CREATE TRIGGER `update_pelanggan_harga_paket` AFTER UPDATE ON `paket_internet` FOR EACH ROW BEGIN
    -- Update harga pelanggan yang menggunakan paket ini, tapi hanya untuk pelanggan baru
    -- atau bisa dikomentari jika tidak ingin update otomatis
    UPDATE pelanggan 
    SET harga_paket = NEW.harga 
    WHERE paket_id = NEW.id 
    AND status = 'aktif';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payment_gateway`
--

CREATE TABLE `payment_gateway` (
  `id` int NOT NULL,
  `nama_gateway` varchar(50) NOT NULL,
  `kode_gateway` varchar(30) NOT NULL,
  `merchant_id` varchar(100) DEFAULT NULL,
  `api_key` text,
  `api_secret` text,
  `api_url` varchar(255) DEFAULT NULL,
  `environment` enum('sandbox','production') DEFAULT 'sandbox',
  `minimal_transaksi` int DEFAULT '0',
  `fee_percent` decimal(5,2) DEFAULT '0.00',
  `fee_fixed` int DEFAULT '0',
  `is_active` tinyint DEFAULT '1',
  `is_default` tinyint DEFAULT '0',
  `urutan` int DEFAULT '0',
  `logo` varchar(255) DEFAULT NULL,
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pelanggan`
--

CREATE TABLE `pelanggan` (
  `id` int NOT NULL,
  `id_pelanggan` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `alamat` text,
  `no_hp` varchar(15) DEFAULT NULL,
  `paket_internet` varchar(50) DEFAULT NULL,
  `paket_id` int DEFAULT NULL,
  `pop_id` int DEFAULT NULL,
  `odp_id` int DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `harga_paket` decimal(10,2) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `mikrotik_comment` varchar(100) DEFAULT NULL,
  `mikrotik_ip` varchar(50) DEFAULT NULL,
  `mikrotik_profile` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `pemasukan`
--

CREATE TABLE `pemasukan` (
  `id` int NOT NULL,
  `tanggal` date NOT NULL,
  `pelanggan_id` int DEFAULT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `pengeluaran`
--

CREATE TABLE `pengeluaran` (
  `id` int NOT NULL,
  `tanggal` date NOT NULL,
  `jenis_pengeluaran` varchar(100) NOT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `pop`
--

CREATE TABLE `pop` (
  `id` int NOT NULL,
  `kode_pop` varchar(20) NOT NULL,
  `nama_pop` varchar(100) NOT NULL,
  `lokasi` text,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `alamat` text,
  `kapasitas` int DEFAULT '0',
  `status` enum('aktif','maintenance','nonaktif') DEFAULT 'aktif',
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pop`
--

INSERT INTO `pop` (`id`, `kode_pop`, `nama_pop`, `lokasi`, `latitude`, `longitude`, `alamat`, `kapasitas`, `status`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'POP-003', 'POP BSD', 'BSD City Blok A No. 10', -6.30000000, 106.70000000, '', 10, 'aktif', '', '2026-04-03 01:52:15', '2026-04-03 01:52:15');

-- --------------------------------------------------------

--
-- Table structure for table `qris`
--

CREATE TABLE `qris` (
  `id` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `qris_code` text,
  `qris_image` varchar(255) DEFAULT NULL,
  `nominal_min` int DEFAULT '0',
  `nominal_max` int DEFAULT '0',
  `is_active` tinyint DEFAULT '1',
  `is_default` tinyint DEFAULT '0',
  `urutan` int DEFAULT '0',
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rekening_bank`
--

CREATE TABLE `rekening_bank` (
  `id` int NOT NULL,
  `kode_bank` varchar(20) NOT NULL,
  `nama_bank` varchar(50) NOT NULL,
  `nomor_rekening` varchar(50) NOT NULL,
  `nama_pemilik` varchar(100) NOT NULL,
  `cabang` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `is_active` tinyint DEFAULT '1',
  `is_default` tinyint DEFAULT '0',
  `urutan` int DEFAULT '0',
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_pembayaran`
--

CREATE TABLE `transaksi_pembayaran` (
  `id` int NOT NULL,
  `kode_transaksi` varchar(50) NOT NULL,
  `pelanggan_id` int DEFAULT NULL,
  `billing_id` int DEFAULT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `metode_pembayaran` enum('bank_transfer','qris','payment_gateway') NOT NULL,
  `rekening_id` int DEFAULT NULL,
  `qris_id` int DEFAULT NULL,
  `gateway_id` int DEFAULT NULL,
  `status` enum('pending','success','failed','expired') DEFAULT 'pending',
  `payment_proof` varchar(255) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `bank_name` varchar(50) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `bank_account_name` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','operator','viewer') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `email`, `role`, `created_at`) VALUES
(1, 'admin', '$2a$12$T/f9f7YwsJH86YEwiqnkieM7mKsQv64BdviMKgbFc2P/Wbn6e0.bi', 'admin', 'admin@gmail.com', 'admin', '2026-03-27 14:14:33');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_paket_statistik`
-- (See below for the actual view)
--
CREATE TABLE `view_paket_statistik` (
`harga` decimal(10,2)
,`id` int
,`is_active` tinyint
,`jumlah_pelanggan` bigint
,`kecepatan` varchar(50)
,`keterangan` text
,`nama_paket` varchar(100)
,`pelanggan_aktif` decimal(23,0)
,`pelanggan_nonaktif` decimal(23,0)
,`pendapatan_belum_terbayar` decimal(32,2)
,`pendapatan_terbayar` decimal(32,2)
,`total_pendapatan` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `vpn_clients`
--

CREATE TABLE `vpn_clients` (
  `id` int NOT NULL,
  `client_id` varchar(50) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `server_id` int DEFAULT NULL,
  `pelanggan_id` int DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `port` int DEFAULT NULL,
  `certificate` text,
  `private_key` text,
  `public_key` text,
  `status` enum('active','inactive','expired') DEFAULT 'active',
  `bandwidth_limit` int DEFAULT '0',
  `expired_date` date DEFAULT NULL,
  `last_connected` datetime DEFAULT NULL,
  `data_used` bigint DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vpn_servers`
--

CREATE TABLE `vpn_servers` (
  `id` int NOT NULL,
  `kode_server` varchar(50) NOT NULL,
  `nama_server` varchar(100) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `port` int DEFAULT '1194',
  `protocol` enum('udp','tcp') DEFAULT 'udp',
  `server_type` enum('openvpn','wireguard','l2tp','pptp') DEFAULT 'openvpn',
  `lokasi` varchar(100) DEFAULT NULL,
  `max_clients` int DEFAULT '50',
  `current_clients` int DEFAULT '0',
  `status` enum('active','maintenance','inactive') DEFAULT 'active',
  `config_file` text,
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_billing` (`pelanggan_id`,`bulan`,`tahun`),
  ADD KEY `idx_period` (`bulan`,`tahun`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `konfigurasi`
--
ALTER TABLE `konfigurasi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_name` (`key_name`);

--
-- Indexes for table `log_harga_paket`
--
ALTER TABLE `log_harga_paket`
  ADD PRIMARY KEY (`id`),
  ADD KEY `paket_id` (`paket_id`);

--
-- Indexes for table `material`
--
ALTER TABLE `material`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mikrotik_config`
--
ALTER TABLE `mikrotik_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `odp`
--
ALTER TABLE `odp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_odp` (`kode_odp`),
  ADD KEY `idx_pop` (`pop_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_kode` (`kode_odp`);

--
-- Indexes for table `paket_internet`
--
ALTER TABLE `paket_internet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_harga` (`harga`);

--
-- Indexes for table `payment_gateway`
--
ALTER TABLE `payment_gateway`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_gateway` (`kode_gateway`);

--
-- Indexes for table `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_pelanggan` (`id_pelanggan`),
  ADD KEY `fk_pelanggan_paket` (`paket_id`),
  ADD KEY `pop_id` (`pop_id`),
  ADD KEY `odp_id` (`odp_id`);

--
-- Indexes for table `pemasukan`
--
ALTER TABLE `pemasukan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pelanggan_id` (`pelanggan_id`),
  ADD KEY `idx_tanggal` (`tanggal`);

--
-- Indexes for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tanggal` (`tanggal`);

--
-- Indexes for table `pop`
--
ALTER TABLE `pop`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_pop` (`kode_pop`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_kode` (`kode_pop`);

--
-- Indexes for table `qris`
--
ALTER TABLE `qris`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_provider` (`provider`);

--
-- Indexes for table `rekening_bank`
--
ALTER TABLE `rekening_bank`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bank` (`kode_bank`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `transaksi_pembayaran`
--
ALTER TABLE `transaksi_pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_transaksi` (`kode_transaksi`),
  ADD KEY `idx_kode` (`kode_transaksi`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_metode` (`metode_pembayaran`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `vpn_clients`
--
ALTER TABLE `vpn_clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_id` (`client_id`),
  ADD KEY `idx_server` (`server_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_client` (`client_id`);

--
-- Indexes for table `vpn_servers`
--
ALTER TABLE `vpn_servers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_server` (`kode_server`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type` (`server_type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `konfigurasi`
--
ALTER TABLE `konfigurasi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `log_harga_paket`
--
ALTER TABLE `log_harga_paket`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `material`
--
ALTER TABLE `material`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mikrotik_config`
--
ALTER TABLE `mikrotik_config`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `odp`
--
ALTER TABLE `odp`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paket_internet`
--
ALTER TABLE `paket_internet`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payment_gateway`
--
ALTER TABLE `payment_gateway`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pemasukan`
--
ALTER TABLE `pemasukan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pop`
--
ALTER TABLE `pop`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `qris`
--
ALTER TABLE `qris`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rekening_bank`
--
ALTER TABLE `rekening_bank`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaksi_pembayaran`
--
ALTER TABLE `transaksi_pembayaran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vpn_clients`
--
ALTER TABLE `vpn_clients`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vpn_servers`
--
ALTER TABLE `vpn_servers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `view_paket_statistik`
--
DROP TABLE IF EXISTS `view_paket_statistik`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_paket_statistik`  AS SELECT `p`.`id` AS `id`, `p`.`nama_paket` AS `nama_paket`, `p`.`kecepatan` AS `kecepatan`, `p`.`harga` AS `harga`, `p`.`keterangan` AS `keterangan`, `p`.`is_active` AS `is_active`, count(distinct `pl`.`id`) AS `jumlah_pelanggan`, sum((case when (`pl`.`status` = 'aktif') then 1 else 0 end)) AS `pelanggan_aktif`, sum((case when (`pl`.`status` = 'nonaktif') then 1 else 0 end)) AS `pelanggan_nonaktif`, coalesce(sum(`b`.`jumlah`),0) AS `total_pendapatan`, coalesce(sum((case when (`b`.`status` = 'lunas') then `b`.`jumlah` else 0 end)),0) AS `pendapatan_terbayar`, coalesce(sum((case when (`b`.`status` = 'belum_lunas') then `b`.`jumlah` else 0 end)),0) AS `pendapatan_belum_terbayar` FROM ((`paket_internet` `p` left join `pelanggan` `pl` on((`p`.`id` = `pl`.`paket_id`))) left join `billing` `b` on((`pl`.`id` = `b`.`pelanggan_id`))) GROUP BY `p`.`id`, `p`.`nama_paket`, `p`.`kecepatan`, `p`.`harga`, `p`.`keterangan`, `p`.`is_active` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `log_harga_paket`
--
ALTER TABLE `log_harga_paket`
  ADD CONSTRAINT `log_harga_paket_ibfk_1` FOREIGN KEY (`paket_id`) REFERENCES `paket_internet` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `odp`
--
ALTER TABLE `odp`
  ADD CONSTRAINT `odp_ibfk_1` FOREIGN KEY (`pop_id`) REFERENCES `pop` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vpn_clients`
--
ALTER TABLE `vpn_clients`
  ADD CONSTRAINT `vpn_clients_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `vpn_servers` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
