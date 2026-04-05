<?php
class TransaksiPembayaran {
    private $conn;
    private $table_name = "transaksi_pembayaran";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getRecent($limit = 20) {
        $query = "SELECT t.*, p.nama as pelanggan_nama 
                  FROM " . $this->table_name . " t
                  LEFT JOIN pelanggan p ON t.pelanggan_id = p.id
                  ORDER BY t.created_at DESC 
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalToday() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " 
                  WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ?? 0;
    }

    public function generateKodeTransaksi() {
        $prefix = 'TRX';
        $date = date('Ymd');
        $random = rand(1000, 9999);
        return $prefix . $date . $random;
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . "
                  SET kode_transaksi=:kode_transaksi, pelanggan_id=:pelanggan_id,
                      billing_id=:billing_id, jumlah=:jumlah, metode_pembayaran=:metode_pembayaran,
                      rekening_id=:rekening_id, qris_id=:qris_id, gateway_id=:gateway_id,
                      status=:status, payment_proof=:payment_proof, payment_date=:payment_date,
                      bank_name=:bank_name, bank_account=:bank_account, 
                      bank_account_name=:bank_account_name, notes=:notes";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':kode_transaksi', $data['kode_transaksi']);
        $stmt->bindParam(':pelanggan_id', $data['pelanggan_id']);
        $stmt->bindParam(':billing_id', $data['billing_id']);
        $stmt->bindParam(':jumlah', $data['jumlah']);
        $stmt->bindParam(':metode_pembayaran', $data['metode_pembayaran']);
        $stmt->bindParam(':rekening_id', $data['rekening_id']);
        $stmt->bindParam(':qris_id', $data['qris_id']);
        $stmt->bindParam(':gateway_id', $data['gateway_id']);
        $stmt->bindParam(':status', $data['status']);
        $stmt->bindParam(':payment_proof', $data['payment_proof']);
        $stmt->bindParam(':payment_date', $data['payment_date']);
        $stmt->bindParam(':bank_name', $data['bank_name']);
        $stmt->bindParam(':bank_account', $data['bank_account']);
        $stmt->bindParam(':bank_account_name', $data['bank_account_name']);
        $stmt->bindParam(':notes', $data['notes']);
        
        return $stmt->execute();
    }

    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function getOne($id) {
        $query = "SELECT t.*, p.nama as pelanggan_nama, p.id_pelanggan
                  FROM " . $this->table_name . " t
                  LEFT JOIN pelanggan p ON t.pelanggan_id = p.id
                  WHERE t.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>