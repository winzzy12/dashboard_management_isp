<?php
class Pop {
    private $conn;
    private $table_name = "pop";

    public $id;
    public $kode_pop;
    public $nama_pop;
    public $lokasi;
    public $latitude;
    public $longitude;
    public $alamat;
    public $kapasitas;
    public $status;
    public $keterangan;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read($search = '', $limit = 10, $offset = 0) {
        $query = "SELECT p.*, 
                         (SELECT COUNT(*) FROM odp WHERE pop_id = p.id) as jumlah_odp,
                         (SELECT COUNT(*) FROM pelanggan WHERE pop_id = p.id) as jumlah_pelanggan
                  FROM " . $this->table_name . " p";
        
        if(!empty($search)) {
            $query .= " WHERE p.nama_pop LIKE :search OR p.kode_pop LIKE :search OR p.lokasi LIKE :search";
        }
        
        $query .= " ORDER BY p.id DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        
        if(!empty($search)) {
            $searchTerm = "%{$search}%";
            $stmt->bindParam(':search', $searchTerm);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }

    public function getTotal($search = '') {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        
        if(!empty($search)) {
            $query .= " WHERE nama_pop LIKE :search OR kode_pop LIKE :search OR lokasi LIKE :search";
            $stmt = $this->conn->prepare($query);
            $searchTerm = "%{$search}%";
            $stmt->bindParam(':search', $searchTerm);
        } else {
            $stmt = $this->conn->prepare($query);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET kode_pop=:kode_pop, nama_pop=:nama_pop, lokasi=:lokasi,
                      latitude=:latitude, longitude=:longitude, alamat=:alamat,
                      kapasitas=:kapasitas, status=:status, keterangan=:keterangan";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':kode_pop', $this->kode_pop);
        $stmt->bindParam(':nama_pop', $this->nama_pop);
        $stmt->bindParam(':lokasi', $this->lokasi);
        $stmt->bindParam(':latitude', $this->latitude);
        $stmt->bindParam(':longitude', $this->longitude);
        $stmt->bindParam(':alamat', $this->alamat);
        $stmt->bindParam(':kapasitas', $this->kapasitas);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':keterangan', $this->keterangan);
        
        return $stmt->execute();
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET kode_pop=:kode_pop, nama_pop=:nama_pop, lokasi=:lokasi,
                      latitude=:latitude, longitude=:longitude, alamat=:alamat,
                      kapasitas=:kapasitas, status=:status, keterangan=:keterangan
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':kode_pop', $this->kode_pop);
        $stmt->bindParam(':nama_pop', $this->nama_pop);
        $stmt->bindParam(':lokasi', $this->lokasi);
        $stmt->bindParam(':latitude', $this->latitude);
        $stmt->bindParam(':longitude', $this->longitude);
        $stmt->bindParam(':alamat', $this->alamat);
        $stmt->bindParam(':kapasitas', $this->kapasitas);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':keterangan', $this->keterangan);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    public function delete() {
        // Check if POP has ODP
        $check_query = "SELECT COUNT(*) as total FROM odp WHERE pop_id = :id";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':id', $this->id);
        $check_stmt->execute();
        $check = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($check['total'] > 0) {
            return ['success' => false, 'message' => 'POP memiliki ' . $check['total'] . ' ODP, tidak dapat dihapus!'];
        }
        
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        
        if($stmt->execute()) {
            return ['success' => true, 'message' => 'POP berhasil dihapus!'];
        }
        return ['success' => false, 'message' => 'Gagal menghapus POP!'];
    }

    public function getOne() {
        $query = "SELECT p.*, 
                         (SELECT COUNT(*) FROM odp WHERE pop_id = p.id) as jumlah_odp,
                         (SELECT COUNT(*) FROM pelanggan WHERE pop_id = p.id) as jumlah_pelanggan
                  FROM " . $this->table_name . " p
                  WHERE p.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE status = 'aktif' ORDER BY nama_pop";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats() {
        $query = "SELECT 
                    COUNT(*) as total_pop,
                    SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
                    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
                    SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif,
                    SUM(kapasitas) as total_kapasitas
                  FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>