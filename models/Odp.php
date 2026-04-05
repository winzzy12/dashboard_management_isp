<?php
class Odp {
    private $conn;
    private $table_name = "odp";

    public $id;
    public $kode_odp;
    public $nama_odp;
    public $pop_id;
    public $latitude;
    public $longitude;
    public $alamat;
    public $jumlah_port;
    public $port_terpakai;
    public $status;
    public $keterangan;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read($search = '', $pop_id = '', $limit = 10, $offset = 0) {
        $query = "SELECT o.*, p.nama_pop as nama_pop, p.kode_pop as kode_pop
                  FROM " . $this->table_name . " o
                  LEFT JOIN pop p ON o.pop_id = p.id";
        
        $conditions = [];
        if(!empty($search)) {
            $conditions[] = "(o.nama_odp LIKE :search OR o.kode_odp LIKE :search OR o.alamat LIKE :search)";
        }
        if(!empty($pop_id)) {
            $conditions[] = "o.pop_id = :pop_id";
        }
        
        if(count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY o.id DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        
        if(!empty($search)) {
            $searchTerm = "%{$search}%";
            $stmt->bindParam(':search', $searchTerm);
        }
        if(!empty($pop_id)) {
            $stmt->bindParam(':pop_id', $pop_id);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }

    public function getTotal($search = '', $pop_id = '') {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        
        $conditions = [];
        if(!empty($search)) {
            $conditions[] = "(nama_odp LIKE :search OR kode_odp LIKE :search OR alamat LIKE :search)";
        }
        if(!empty($pop_id)) {
            $conditions[] = "pop_id = :pop_id";
        }
        
        if(count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $stmt = $this->conn->prepare($query);
        
        if(!empty($search)) {
            $searchTerm = "%{$search}%";
            $stmt->bindParam(':search', $searchTerm);
        }
        if(!empty($pop_id)) {
            $stmt->bindParam(':pop_id', $pop_id);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET kode_odp=:kode_odp, nama_odp=:nama_odp, pop_id=:pop_id,
                      latitude=:latitude, longitude=:longitude, alamat=:alamat,
                      jumlah_port=:jumlah_port, port_terpakai=:port_terpakai,
                      status=:status, keterangan=:keterangan";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':kode_odp', $this->kode_odp);
        $stmt->bindParam(':nama_odp', $this->nama_odp);
        $stmt->bindParam(':pop_id', $this->pop_id);
        $stmt->bindParam(':latitude', $this->latitude);
        $stmt->bindParam(':longitude', $this->longitude);
        $stmt->bindParam(':alamat', $this->alamat);
        $stmt->bindParam(':jumlah_port', $this->jumlah_port);
        $stmt->bindParam(':port_terpakai', $this->port_terpakai);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':keterangan', $this->keterangan);
        
        return $stmt->execute();
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET kode_odp=:kode_odp, nama_odp=:nama_odp, pop_id=:pop_id,
                      latitude=:latitude, longitude=:longitude, alamat=:alamat,
                      jumlah_port=:jumlah_port, port_terpakai=:port_terpakai,
                      status=:status, keterangan=:keterangan
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':kode_odp', $this->kode_odp);
        $stmt->bindParam(':nama_odp', $this->nama_odp);
        $stmt->bindParam(':pop_id', $this->pop_id);
        $stmt->bindParam(':latitude', $this->latitude);
        $stmt->bindParam(':longitude', $this->longitude);
        $stmt->bindParam(':alamat', $this->alamat);
        $stmt->bindParam(':jumlah_port', $this->jumlah_port);
        $stmt->bindParam(':port_terpakai', $this->port_terpakai);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':keterangan', $this->keterangan);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    public function delete() {
        // Check if ODP has customers
        $check_query = "SELECT COUNT(*) as total FROM pelanggan WHERE odp_id = :id";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':id', $this->id);
        $check_stmt->execute();
        $check = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($check['total'] > 0) {
            return ['success' => false, 'message' => 'ODP memiliki ' . $check['total'] . ' pelanggan, tidak dapat dihapus!'];
        }
        
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        
        if($stmt->execute()) {
            return ['success' => true, 'message' => 'ODP berhasil dihapus!'];
        }
        return ['success' => false, 'message' => 'Gagal menghapus ODP!'];
    }

    public function getOne() {
        $query = "SELECT o.*, p.nama_pop as nama_pop, p.kode_pop as kode_pop,
                         (SELECT COUNT(*) FROM pelanggan WHERE odp_id = o.id) as jumlah_pelanggan
                  FROM " . $this->table_name . " o
                  LEFT JOIN pop p ON o.pop_id = p.id
                  WHERE o.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByPop($pop_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE pop_id = :pop_id AND status = 'aktif' ORDER BY nama_odp";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pop_id', $pop_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats() {
        $query = "SELECT 
                    COUNT(*) as total_odp,
                    SUM(jumlah_port) as total_port,
                    SUM(port_terpakai) as total_port_terpakai,
                    SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
                    SUM(CASE WHEN status = 'penuh' THEN 1 ELSE 0 END) as penuh,
                    SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif
                  FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>