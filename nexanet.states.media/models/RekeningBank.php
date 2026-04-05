<?php
class RekeningBank {
    private $conn;
    private $table_name = "rekening_bank";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY urutan ASC, is_default DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActive() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE is_active = 1 ORDER BY urutan ASC, is_default DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOne($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . "
                  SET kode_bank=:kode_bank, nama_bank=:nama_bank, nomor_rekening=:nomor_rekening,
                      nama_pemilik=:nama_pemilik, cabang=:cabang, is_active=:is_active, 
                      is_default=:is_default, urutan=:urutan, keterangan=:keterangan";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':kode_bank', $data['kode_bank']);
        $stmt->bindParam(':nama_bank', $data['nama_bank']);
        $stmt->bindParam(':nomor_rekening', $data['nomor_rekening']);
        $stmt->bindParam(':nama_pemilik', $data['nama_pemilik']);
        $stmt->bindParam(':cabang', $data['cabang']);
        $stmt->bindParam(':is_active', $data['is_active']);
        $stmt->bindParam(':is_default', $data['is_default']);
        $stmt->bindParam(':urutan', $data['urutan']);
        $stmt->bindParam(':keterangan', $data['keterangan']);
        
        return $stmt->execute();
    }

    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . "
                  SET kode_bank=:kode_bank, nama_bank=:nama_bank, nomor_rekening=:nomor_rekening,
                      nama_pemilik=:nama_pemilik, cabang=:cabang, is_active=:is_active, 
                      is_default=:is_default, urutan=:urutan, keterangan=:keterangan
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':kode_bank', $data['kode_bank']);
        $stmt->bindParam(':nama_bank', $data['nama_bank']);
        $stmt->bindParam(':nomor_rekening', $data['nomor_rekening']);
        $stmt->bindParam(':nama_pemilik', $data['nama_pemilik']);
        $stmt->bindParam(':cabang', $data['cabang']);
        $stmt->bindParam(':is_active', $data['is_active']);
        $stmt->bindParam(':is_default', $data['is_default']);
        $stmt->bindParam(':urutan', $data['urutan']);
        $stmt->bindParam(':keterangan', $data['keterangan']);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function setDefault($id) {
        // Reset all defaults
        $reset_query = "UPDATE " . $this->table_name . " SET is_default = 0";
        $reset_stmt = $this->conn->prepare($reset_query);
        $reset_stmt->execute();
        
        // Set new default
        $query = "UPDATE " . $this->table_name . " SET is_default = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>