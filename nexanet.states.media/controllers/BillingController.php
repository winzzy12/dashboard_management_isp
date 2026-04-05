<?php
require_once '../config/database.php';
require_once '../models/Billing.php';
require_once '../models/Pelanggan.php';

class BillingController {
    private $db;
    private $billing;
    private $pelanggan;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->billing = new Billing($this->db);
        $this->pelanggan = new Pelanggan($this->db);
    }
    
    public function index() {
        $bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
        $tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->billing->read($bulan, $tahun, $status, $limit, $offset);
        $total = $this->billing->getTotal($bulan, $tahun, $status);
        $total_pages = ceil($total / $limit);
        $total_belum_lunas = $this->billing->getTotalBelumLunasByPeriod($bulan, $tahun);
        $total_lunas = $this->billing->getTotalLunasByPeriod($bulan, $tahun);
        
        return [
            'billings' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'total_belum_lunas' => $total_belum_lunas,
            'total_lunas' => $total_lunas,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'status' => $status
        ];
    }
    
    public function generate() {
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $bulan = $_POST['bulan'];
            $tahun = $_POST['tahun'];
            
            $result = $this->billing->generateBilling($bulan, $tahun);
            
            if($result['success']) {
                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }
            
            header("Location: ../views/billing/index.php?bulan={$bulan}&tahun={$tahun}");
            exit();
        }
    }
    
    public function bayar($id) {
        $this->billing->id = $id;
        $billing = $this->billing->getOne();
        
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $tanggal_bayar = $_POST['tanggal_bayar'];
            
            if($this->billing->markAsPaid($id, $tanggal_bayar)) {
                // Also record to pemasukan
                $this->recordPemasukan($billing, $tanggal_bayar);
                
                $_SESSION['success'] = "Pembayaran berhasil dicatat!";
                header("Location: ../views/billing/index.php");
                exit();
            } else {
                $_SESSION['error'] = "Gagal mencatat pembayaran!";
            }
        }
        
        return $billing;
    }
    
    private function recordPemasukan($billing, $tanggal_bayar) {
        $query = "INSERT INTO pemasukan (tanggal, pelanggan_id, jumlah, keterangan) 
                  VALUES (:tanggal, :pelanggan_id, :jumlah, :keterangan)";
        $stmt = $this->db->prepare($query);
        
        $keterangan = "Pembayaran tagihan bulan {$billing['bulan']}/{$billing['tahun']}";
        
        $stmt->bindParam(':tanggal', $tanggal_bayar);
        $stmt->bindParam(':pelanggan_id', $billing['pelanggan_id']);
        $stmt->bindParam(':jumlah', $billing['jumlah']);
        $stmt->bindParam(':keterangan', $keterangan);
        
        $stmt->execute();
    }
    
    public function getTagihanTerlambat() {
        $current_date = date('Y-m-d');
        $query = "SELECT b.*, p.nama as nama_pelanggan, p.no_hp 
                  FROM billing b
                  LEFT JOIN pelanggan p ON b.pelanggan_id = p.id
                  WHERE b.status = 'belum_lunas' 
                  AND b.tanggal_jatuh_tempo < :current_date
                  ORDER BY b.tanggal_jatuh_tempo ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':current_date', $current_date);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function sendReminder($id) {
        $billing = $this->billing->getOne($id);
        if($billing && $billing['status'] == 'belum_lunas') {
            // Here you can implement SMS or Email reminder
            // For now, just mark as reminded
            $query = "UPDATE billing SET reminder_sent = 1 WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        }
        return false;
    }
    
    public function getStatistikBulanan($tahun = null) {
        if(!$tahun) $tahun = date('Y');
        
        $query = "SELECT 
                    bulan,
                    COUNT(*) as total_tagihan,
                    SUM(CASE WHEN status = 'lunas' THEN 1 ELSE 0 END) as lunas,
                    SUM(CASE WHEN status = 'belum_lunas' THEN 1 ELSE 0 END) as belum_lunas,
                    SUM(jumlah) as total_nominal,
                    SUM(CASE WHEN status = 'lunas' THEN jumlah ELSE 0 END) as total_terbayar
                  FROM billing
                  WHERE tahun = :tahun
                  GROUP BY bulan
                  ORDER BY bulan";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':tahun', $tahun);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getLaporanTahunan($tahun = null) {
        if(!$tahun) $tahun = date('Y');
        
        $query = "SELECT 
                    b.bulan,
                    b.tahun,
                    COUNT(b.id) as total_tagihan,
                    SUM(CASE WHEN b.status = 'lunas' THEN 1 ELSE 0 END) as total_lunas,
                    SUM(CASE WHEN b.status = 'belum_lunas' THEN 1 ELSE 0 END) as total_belum_lunas,
                    SUM(b.jumlah) as total_nominal,
                    SUM(CASE WHEN b.status = 'lunas' THEN b.jumlah ELSE 0 END) as total_terbayar,
                    SUM(CASE WHEN b.status = 'belum_lunas' THEN b.jumlah ELSE 0 END) as total_belum_terbayar
                  FROM billing b
                  WHERE b.tahun = :tahun
                  GROUP BY b.bulan, b.tahun
                  ORDER BY b.bulan";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':tahun', $tahun);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>