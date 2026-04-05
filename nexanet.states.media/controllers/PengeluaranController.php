<?php
require_once '../config/database.php';
require_once '../models/Pengeluaran.php';

class PengeluaranController {
    private $db;
    private $pengeluaran;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->pengeluaran = new Pengeluaran($this->db);
    }
    
    public function index() {
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->pengeluaran->read($start_date, $end_date, $limit, $offset);
        $total = $this->pengeluaran->getTotal($start_date, $end_date);
        $total_pages = ceil($total / $limit);
        $total_amount = $this->pengeluaran->getTotalAmount($start_date, $end_date);
        
        return [
            'pengeluaran' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'total_amount' => $total_amount,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'start_date' => $start_date,
            'end_date' => $end_date
        ];
    }
    
    public function create() {
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->pengeluaran->tanggal = $_POST['tanggal'];
            $this->pengeluaran->jenis_pengeluaran = $_POST['jenis_pengeluaran'];
            $this->pengeluaran->jumlah = $_POST['jumlah'];
            $this->pengeluaran->keterangan = $_POST['keterangan'];
            
            if($this->pengeluaran->create()) {
                $_SESSION['success'] = "Pengeluaran berhasil ditambahkan!";
                header("Location: ../views/pengeluaran/index.php");
                exit();
            } else {
                $_SESSION['error'] = "Gagal menambahkan pengeluaran!";
            }
        }
    }
    
    public function edit($id) {
        $this->pengeluaran->id = $id;
        $pengeluaran = $this->pengeluaran->getOne();
        
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->pengeluaran->tanggal = $_POST['tanggal'];
            $this->pengeluaran->jenis_pengeluaran = $_POST['jenis_pengeluaran'];
            $this->pengeluaran->jumlah = $_POST['jumlah'];
            $this->pengeluaran->keterangan = $_POST['keterangan'];
            
            if($this->pengeluaran->update()) {
                $_SESSION['success'] = "Pengeluaran berhasil diupdate!";
                header("Location: ../views/pengeluaran/index.php");
                exit();
            } else {
                $_SESSION['error'] = "Gagal mengupdate pengeluaran!";
            }
        }
        
        return $pengeluaran;
    }
    
    public function delete($id) {
        $this->pengeluaran->id = $id;
        if($this->pengeluaran->delete()) {
            $_SESSION['success'] = "Pengeluaran berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus pengeluaran!";
        }
        header("Location: ../views/pengeluaran/index.php");
        exit();
    }
    
    public function getStatistikByJenis($start_date, $end_date) {
        $query = "SELECT jenis_pengeluaran, SUM(jumlah) as total 
                  FROM pengeluaran 
                  WHERE tanggal BETWEEN :start_date AND :end_date 
                  GROUP BY jenis_pengeluaran 
                  ORDER BY total DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getMonthlyStatistik($tahun = null) {
        if(!$tahun) $tahun = date('Y');
        
        $query = "SELECT 
                    MONTH(tanggal) as bulan,
                    SUM(jumlah) as total
                  FROM pengeluaran
                  WHERE YEAR(tanggal) = :tahun
                  GROUP BY MONTH(tanggal)
                  ORDER BY bulan";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':tahun', $tahun);
        $stmt->execute();
        
        $data = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[$row['bulan']] = $row['total'];
        }
        
        return $data;
    }
    
    public function export($start_date, $end_date) {
        $pengeluaran = $this->pengeluaran->read($start_date, $end_date, 9999, 0)->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="pengeluaran_export.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Tanggal', 'Jenis Pengeluaran', 'Jumlah', 'Keterangan']);
        
        foreach($pengeluaran as $item) {
            fputcsv($output, [
                $item['id'],
                $item['tanggal'],
                $item['jenis_pengeluaran'],
                $item['jumlah'],
                $item['keterangan']
            ]);
        }
        
        fclose($output);
        exit();
    }
}
?>