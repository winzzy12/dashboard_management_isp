<?php
class Qris {
    private $conn;
    private $table_name = "qris";

    public $id;
    public $nama;
    public $provider;
    public $qris_code;
    public $qris_image;
    public $nominal_min;
    public $nominal_max;
    public $is_active;
    public $is_default;
    public $urutan;
    public $keterangan;

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

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET nama=:nama, provider=:provider, qris_code=:qris_code,
                      qris_image=:qris_image, nominal_min=:nominal_min, nominal_max=:nominal_max,
                      is_active=:is_active, is_default=:is_default, urutan=:urutan, keterangan=:keterangan";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':nama', $this->nama);
        $stmt->bindParam(':provider', $this->provider);
        $stmt->bindParam(':qris_code', $this->qris_code);
        $stmt->bindParam(':qris_image', $this->qris_image);
        $stmt->bindParam(':nominal_min', $this->nominal_min);
        $stmt->bindParam(':nominal_max', $this->nominal_max);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':is_default', $this->is_default);
        $stmt->bindParam(':urutan', $this->urutan);
        $stmt->bindParam(':keterangan', $this->keterangan);
        
        return $stmt->execute();
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET nama=:nama, provider=:provider, qris_code=:qris_code,
                      qris_image=:qris_image, nominal_min=:nominal_min, nominal_max=:nominal_max,
                      is_active=:is_active, is_default=:is_default, urutan=:urutan, keterangan=:keterangan
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':nama', $this->nama);
        $stmt->bindParam(':provider', $this->provider);
        $stmt->bindParam(':qris_code', $this->qris_code);
        $stmt->bindParam(':qris_image', $this->qris_image);
        $stmt->bindParam(':nominal_min', $this->nominal_min);
        $stmt->bindParam(':nominal_max', $this->nominal_max);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':is_default', $this->is_default);
        $stmt->bindParam(':urutan', $this->urutan);
        $stmt->bindParam(':keterangan', $this->keterangan);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function setDefault($id) {
        $reset_query = "UPDATE " . $this->table_name . " SET is_default = 0";
        $reset_stmt = $this->conn->prepare($reset_query);
        $reset_stmt->execute();
        
        $query = "UPDATE " . $this->table_name . " SET is_default = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>