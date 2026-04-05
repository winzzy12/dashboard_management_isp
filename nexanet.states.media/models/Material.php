<?php
class Material {
    private $conn;
    private $table_name = "material";

    public $id;
    public $nama_material;
    public $stok;
    public $harga;
    public $keterangan;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read($search = '', $limit = 10, $offset = 0) {
        $query = "SELECT * FROM " . $this->table_name;
        
        if(!empty($search)) {
            $query .= " WHERE nama_material LIKE :search OR keterangan LIKE :search";
        }
        
        $query .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
        
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
            $query .= " WHERE nama_material LIKE :search OR keterangan LIKE :search";
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
                  SET nama_material=:nama_material, stok=:stok, 
                      harga=:harga, keterangan=:keterangan";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':nama_material', $this->nama_material);
        $stmt->bindParam(':stok', $this->stok);
        $stmt->bindParam(':harga', $this->harga);
        $stmt->bindParam(':keterangan', $this->keterangan);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET nama_material=:nama_material, stok=:stok,
                      harga=:harga, keterangan=:keterangan
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':nama_material', $this->nama_material);
        $stmt->bindParam(':stok', $this->stok);
        $stmt->bindParam(':harga', $this->harga);
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

    public function getOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY nama_material ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLowStock($limit = 10) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE stok < 10 
                  ORDER BY stok ASC 
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStock($id, $jumlah, $type = 'out') {
        if($type == 'in') {
            $query = "UPDATE " . $this->table_name . " 
                      SET stok = stok + :jumlah 
                      WHERE id = :id";
        } else {
            $query = "UPDATE " . $this->table_name . " 
                      SET stok = stok - :jumlah 
                      WHERE id = :id AND stok >= :jumlah";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':jumlah', $jumlah);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    // Method untuk mendapatkan total nilai material
    public function getTotalValue() {
        $query = "SELECT SUM(stok * harga) as total FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ?: 0;
    }
}
?>