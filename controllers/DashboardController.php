<?php
require_once '../config/database.php';
require_once '../models/Pelanggan.php';
require_once '../models/Material.php';
require_once '../models/Pemasukan.php';
require_once '../models/Pengeluaran.php';
require_once '../models/Billing.php';

class DashboardController {
    private $db;
    private $pelanggan;
    private $material;
    private $pemasukan;
    private $pengeluaran;
    private $billing;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->pelanggan = new Pelanggan($this->db);
        $this->material = new Material($this->db);
        $this->pemasukan = new Pemasukan($this->db);
        $this->pengeluaran = new Pengeluaran($this->db);
        $this->billing = new Billing($this->db);
    }
    
    public function getDashboardData() {
        return [
            'total_pelanggan' => $this->pelanggan->getTotal(),
            'total_pelanggan_aktif' => $this->getTotalPelangganAktif(),
            'total_material' => $this->material->getTotal(),
            'total_pemasukan_bulan_ini' => $this->pemasukan->getTotalBulanIni(),
            'total_pengeluaran_bulan_ini' => $this->pengeluaran->getTotalBulanIni(),
            'total_billing_belum_lunas' => $this->billing->getTotalBelumLunas(),
            'total_billing_bulan_ini' => $this->billing->getTotalBulanIni(),
            'pendapatan_bulan_ini' => $this->getPendapatanBulanIni(),
            'chart_pemasukan' => $this->pemasukan->getChartData(),
            'chart_pengeluaran' => $this->pengeluaran->getChartData(),
            'recent_pemasukan' => $this->getRecentPemasukan(),
            'recent_pengeluaran' => $this->getRecentPengeluaran(),
            'recent_tagihan' => $this->getRecentTagihan()
        ];
    }
    
    private function getTotalPelangganAktif() {
        $query = "SELECT COUNT(*) as total FROM pelanggan WHERE status = 'aktif'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
    
    private function getPendapatanBulanIni() {
        $bulan = date('m');
        $tahun = date('Y');
        $query = "SELECT SUM(jumlah) as total FROM pemasukan 
                  WHERE MONTH(tanggal) = :bulan AND YEAR(tanggal) = :tahun";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bulan', $bulan);
        $stmt->bindParam(':tahun', $tahun);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ? $row['total'] : 0;
    }
    
    private function getRecentPemasukan($limit = 5) {
        $query = "SELECT p.*, pl.nama as nama_pelanggan 
                  FROM pemasukan p
                  LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.id
                  ORDER BY p.tanggal DESC, p.id DESC 
                  LIMIT :limit";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getRecentPengeluaran($limit = 5) {
        $query = "SELECT * FROM pengeluaran 
                  ORDER BY tanggal DESC, id DESC 
                  LIMIT :limit";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getRecentTagihan($limit = 5) {
        $query = "SELECT b.*, pl.nama as nama_pelanggan 
                  FROM billing b
                  LEFT JOIN pelanggan pl ON b.pelanggan_id = pl.id
                  ORDER BY b.created_at DESC 
                  LIMIT :limit";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getStatistikTahunan($tahun = null) {
        if(!$tahun) $tahun = date('Y');
        
        $query = "SELECT 
                    MONTH(tanggal) as bulan,
                    SUM(jumlah) as total
                  FROM pemasukan
                  WHERE YEAR(tanggal) = :tahun
                  GROUP BY MONTH(tanggal)
                  ORDER BY bulan";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':tahun', $tahun);
        $stmt->execute();
        $pemasukan = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $query2 = "SELECT 
                    MONTH(tanggal) as bulan,
                    SUM(jumlah) as total
                  FROM pengeluaran
                  WHERE YEAR(tanggal) = :tahun
                  GROUP BY MONTH(tanggal)
                  ORDER BY bulan";
        $stmt2 = $this->db->prepare($query2);
        $stmt2->bindParam(':tahun', $tahun);
        $stmt2->execute();
        $pengeluaran = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'pemasukan' => $pemasukan,
            'pengeluaran' => $pengeluaran
        ];
    }
}
?>