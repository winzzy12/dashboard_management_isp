<?php
class Pengeluaran {
    private $conn;
    private $table_name = "pengeluaran";

    public $id;
    public $tanggal;
    public $jenis_pengeluaran;
    public $jumlah;
    public $keterangan;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read($start_date = '', $end_date = '', $limit = 10, $offset = 0) {
        $query = "SELECT * FROM " . $this->table_name;
        
        $conditions = [];
        if(!empty($start_date) && !empty($end_date)) {
            $conditions[] = "tanggal BETWEEN :start_date AND :end_date";
        } elseif(!empty($start_date)) {
            $conditions[] = "tanggal >= :start_date";
        } elseif(!empty($end_date)) {
            $conditions[] = "tanggal <= :end_date";
        }
        
        if(count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY tanggal DESC, id DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        
        if(!empty($start_date) && !empty($end_date)) {
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
        } elseif(!empty($start_date)) {
            $stmt->bindParam(':start_date', $start_date);
        } elseif(!empty($end_date)) {
            $stmt->bindParam(':end_date', $end_date);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }

    public function getTotal($start_date = '', $end_date = '') {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        
        $conditions = [];
        if(!empty($start_date) && !empty($end_date)) {
            $conditions[] = "tanggal BETWEEN :start_date AND :end_date";
        } elseif(!empty($start_date)) {
            $conditions[] = "tanggal >= :start_date";
        } elseif(!empty($end_date)) {
            $conditions[] = "tanggal <= :end_date";
        }
        
        if(count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $stmt = $this->conn->prepare($query);
        
        if(!empty($start_date) && !empty($end_date)) {
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
        } elseif(!empty($start_date)) {
            $stmt->bindParam(':start_date', $start_date);
        } elseif(!empty($end_date)) {
            $stmt->bindParam(':end_date', $end_date);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    public function getTotalAmount($start_date = '', $end_date = '') {
        $query = "SELECT SUM(jumlah) as total FROM " . $this->table_name;
        
        $conditions = [];
        if(!empty($start_date) && !empty($end_date)) {
            $conditions[] = "tanggal BETWEEN :start_date AND :end_date";
        } elseif(!empty($start_date)) {
            $conditions[] = "tanggal >= :start_date";
        } elseif(!empty($end_date)) {
            $conditions[] = "tanggal <= :end_date";
        }
        
        if(count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $stmt = $this->conn->prepare($query);
        
        if(!empty($start_date) && !empty($end_date)) {
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
        } elseif(!empty($start_date)) {
            $stmt->bindParam(':start_date', $start_date);
        } elseif(!empty($end_date)) {
            $stmt->bindParam(':end_date', $end_date);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ? (int)$row['total'] : 0;
    }

    public function getTotalBulanIni() {
        $bulan = date('m');
        $tahun = date('Y');
        $query = "SELECT SUM(jumlah) as total FROM " . $this->table_name . " 
                  WHERE MONTH(tanggal) = :bulan AND YEAR(tanggal) = :tahun";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':bulan', $bulan);
        $stmt->bindParam(':tahun', $tahun);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ? (int)$row['total'] : 0;
    }

    public function getChartData() {
        $data = ['labels' => [], 'values' => []];
        for($i = 5; $i >= 0; $i--) {
            $bulan = date('m', strtotime("-$i months"));
            $tahun = date('Y', strtotime("-$i months"));
            $nama_bulan = date('M', strtotime("-$i months"));
            
            $query = "SELECT SUM(jumlah) as total FROM " . $this->table_name . " 
                      WHERE MONTH(tanggal) = :bulan AND YEAR(tanggal) = :tahun";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':bulan', $bulan);
            $stmt->bindParam(':tahun', $tahun);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $data['labels'][] = $nama_bulan;
            $data['values'][] = $row['total'] ? (int)$row['total'] : 0;
        }
        return $data;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET tanggal=:tanggal, jenis_pengeluaran=:jenis_pengeluaran, 
                      jumlah=:jumlah, keterangan=:keterangan";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':tanggal', $this->tanggal);
        $stmt->bindParam(':jenis_pengeluaran', $this->jenis_pengeluaran);
        $stmt->bindParam(':jumlah', $this->jumlah);
        $stmt->bindParam(':keterangan', $this->keterangan);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET tanggal=:tanggal, jenis_pengeluaran=:jenis_pengeluaran,
                      jumlah=:jumlah, keterangan=:keterangan
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':tanggal', $this->tanggal);
        $stmt->bindParam(':jenis_pengeluaran', $this->jenis_pengeluaran);
        $stmt->bindParam(':jumlah', $this->jumlah);
        $stmt->bindParam(':keterangan', $this->keterangan);
        $stmt->bindParam(':id', $this->id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // ADD THIS METHOD - Get single record by ID
    public function getOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get by ID with different name
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY tanggal DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getJenisPengeluaran() {
        $query = "SELECT DISTINCT jenis_pengeluaran FROM " . $this->table_name . " ORDER BY jenis_pengeluaran";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTotalAmountByMonth($bulan, $tahun) {
        $query = "SELECT SUM(jumlah) as total FROM " . $this->table_name . " 
                  WHERE MONTH(tanggal) = :bulan AND YEAR(tanggal) = :tahun";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':bulan', $bulan);
        $stmt->bindParam(':tahun', $tahun);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ? (int)$row['total'] : 0;
    }

    public function getTotalAmountByYear($tahun) {
        $query = "SELECT SUM(jumlah) as total FROM " . $this->table_name . " 
                  WHERE YEAR(tanggal) = :tahun";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':tahun', $tahun);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ? (int)$row['total'] : 0;
    }
}
?>