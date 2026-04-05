<?php
class Paket {
    private $conn;
    private $table_name = "paket_internet";

    public $id;
    public $nama_paket;
    public $kecepatan;
    public $harga;
    public $keterangan;
    public $is_active;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all packages
    public function getAll($active_only = false) {
        $query = "SELECT * FROM " . $this->table_name;
        if($active_only) {
            $query .= " WHERE is_active = 1";
        }
        $query .= " ORDER BY harga ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get single package by ID
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Create new package
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET nama_paket=:nama_paket, kecepatan=:kecepatan, 
                      harga=:harga, keterangan=:keterangan, is_active=:is_active";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':nama_paket', $this->nama_paket);
        $stmt->bindParam(':kecepatan', $this->kecepatan);
        $stmt->bindParam(':harga', $this->harga);
        $stmt->bindParam(':keterangan', $this->keterangan);
        $stmt->bindParam(':is_active', $this->is_active);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Update package
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET nama_paket=:nama_paket, kecepatan=:kecepatan,
                      harga=:harga, keterangan=:keterangan, is_active=:is_active
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':nama_paket', $this->nama_paket);
        $stmt->bindParam(':kecepatan', $this->kecepatan);
        $stmt->bindParam(':harga', $this->harga);
        $stmt->bindParam(':keterangan', $this->keterangan);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':id', $this->id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete package
    public function delete($id) {
        // Check if package is used by any customer
        $check_query = "SELECT COUNT(*) as total FROM pelanggan WHERE paket_id = :id";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->execute();
        $check = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($check['total'] > 0) {
            return ['success' => false, 'message' => 'Paket sedang digunakan oleh ' . $check['total'] . ' pelanggan, tidak dapat dihapus!'];
        }
        
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if($stmt->execute()) {
            return ['success' => true, 'message' => 'Paket berhasil dihapus!'];
        }
        return ['success' => false, 'message' => 'Gagal menghapus paket!'];
    }

    // Toggle active status
    public function toggleActive($id) {
        $query = "UPDATE " . $this->table_name . " SET is_active = NOT is_active WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Update price only
    public function updatePrice($id, $harga) {
        $query = "UPDATE " . $this->table_name . " SET harga = :harga WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':harga', $harga);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Get package statistics
    public function getStats() {
        $query = "SELECT 
                    COUNT(*) as total_paket,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as aktif,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as nonaktif,
                    MIN(harga) as harga_terendah,
                    MAX(harga) as harga_tertinggi,
                    AVG(harga) as harga_rata_rata
                  FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Set default values if no data
        if(!$result['total_paket']) {
            $result['total_paket'] = 0;
            $result['aktif'] = 0;
            $result['nonaktif'] = 0;
            $result['harga_terendah'] = 0;
            $result['harga_tertinggi'] = 0;
            $result['harga_rata_rata'] = 0;
        }
        
        return $result;
    }

    // Get packages with customer count
    public function getWithCustomerCount() {
        $query = "SELECT p.*, 
                         COUNT(DISTINCT pl.id) as jumlah_pelanggan
                  FROM " . $this->table_name . " p
                  LEFT JOIN pelanggan pl ON p.id = pl.paket_id
                  GROUP BY p.id
                  ORDER BY p.harga ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no data, return empty array
        if(!$result) {
            return [];
        }
        
        return $result;
    }
}
?>