<?php
class PaymentGateway {
    private $conn;
    private $table_name = "payment_gateway";

    public $id;
    public $nama_gateway;
    public $kode_gateway;
    public $merchant_id;
    public $api_key;
    public $api_secret;
    public $api_url;
    public $environment;
    public $minimal_transaksi;
    public $fee_percent;
    public $fee_fixed;
    public $is_active;
    public $is_default;
    public $urutan;
    public $logo;
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

    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . "
                  SET nama_gateway=:nama_gateway, kode_gateway=:kode_gateway,
                      merchant_id=:merchant_id, api_key=:api_key, api_secret=:api_secret,
                      api_url=:api_url, environment=:environment, minimal_transaksi=:minimal_transaksi,
                      fee_percent=:fee_percent, fee_fixed=:fee_fixed,
                      is_active=:is_active, is_default=:is_default, urutan=:urutan,
                      logo=:logo, keterangan=:keterangan";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':nama_gateway', $data['nama_gateway']);
        $stmt->bindParam(':kode_gateway', $data['kode_gateway']);
        $stmt->bindParam(':merchant_id', $data['merchant_id']);
        $stmt->bindParam(':api_key', $data['api_key']);
        $stmt->bindParam(':api_secret', $data['api_secret']);
        $stmt->bindParam(':api_url', $data['api_url']);
        $stmt->bindParam(':environment', $data['environment']);
        $stmt->bindParam(':minimal_transaksi', $data['minimal_transaksi']);
        $stmt->bindParam(':fee_percent', $data['fee_percent']);
        $stmt->bindParam(':fee_fixed', $data['fee_fixed']);
        $stmt->bindParam(':is_active', $data['is_active']);
        $stmt->bindParam(':is_default', $data['is_default']);
        $stmt->bindParam(':urutan', $data['urutan']);
        $stmt->bindParam(':logo', $data['logo']);
        $stmt->bindParam(':keterangan', $data['keterangan']);
        
        return $stmt->execute();
    }

    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . "
                  SET nama_gateway=:nama_gateway, kode_gateway=:kode_gateway,
                      merchant_id=:merchant_id, api_key=:api_key, api_secret=:api_secret,
                      api_url=:api_url, environment=:environment, minimal_transaksi=:minimal_transaksi,
                      fee_percent=:fee_percent, fee_fixed=:fee_fixed,
                      is_active=:is_active, is_default=:is_default, urutan=:urutan,
                      logo=:logo, keterangan=:keterangan
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':nama_gateway', $data['nama_gateway']);
        $stmt->bindParam(':kode_gateway', $data['kode_gateway']);
        $stmt->bindParam(':merchant_id', $data['merchant_id']);
        $stmt->bindParam(':api_key', $data['api_key']);
        $stmt->bindParam(':api_secret', $data['api_secret']);
        $stmt->bindParam(':api_url', $data['api_url']);
        $stmt->bindParam(':environment', $data['environment']);
        $stmt->bindParam(':minimal_transaksi', $data['minimal_transaksi']);
        $stmt->bindParam(':fee_percent', $data['fee_percent']);
        $stmt->bindParam(':fee_fixed', $data['fee_fixed']);
        $stmt->bindParam(':is_active', $data['is_active']);
        $stmt->bindParam(':is_default', $data['is_default']);
        $stmt->bindParam(':urutan', $data['urutan']);
        $stmt->bindParam(':logo', $data['logo']);
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