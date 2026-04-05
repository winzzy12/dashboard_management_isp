<?php
class Billing {
    private $conn;
    private $table_name = "billing";

    public $id;
    public $pelanggan_id;
    public $bulan;
    public $tahun;
    public $jumlah;
    public $status;
    public $payment_token;
    public $tanggal_jatuh_tempo;
    public $tanggal_bayar;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Read billing with filters
    public function read($bulan = '', $tahun = '', $status = '', $limit = 10, $offset = 0) {
        $query = "SELECT b.*, p.nama as nama_pelanggan, p.id_pelanggan, p.alamat, p.paket_internet
                  FROM " . $this->table_name . " b
                  LEFT JOIN pelanggan p ON b.pelanggan_id = p.id";
        
        $conditions = [];
        if(!empty($bulan)) {
            $conditions[] = "b.bulan = :bulan";
        }
        if(!empty($tahun)) {
            $conditions[] = "b.tahun = :tahun";
        }
        if(!empty($status)) {
            $conditions[] = "b.status = :status";
        }
        
        if(count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY b.tahun DESC, b.bulan DESC, p.nama ASC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        
        if(!empty($bulan)) {
            $stmt->bindParam(':bulan', $bulan);
        }
        if(!empty($tahun)) {
            $stmt->bindParam(':tahun', $tahun);
        }
        if(!empty($status)) {
            $stmt->bindParam(':status', $status);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }

    // Get total count
    public function getTotal($bulan = '', $tahun = '', $status = '') {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        
        $conditions = [];
        if(!empty($bulan)) {
            $conditions[] = "bulan = :bulan";
        }
        if(!empty($tahun)) {
            $conditions[] = "tahun = :tahun";
        }
        if(!empty($status)) {
            $conditions[] = "status = :status";
        }
        
        if(count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $stmt = $this->conn->prepare($query);
        
        if(!empty($bulan)) {
            $stmt->bindParam(':bulan', $bulan);
        }
        if(!empty($tahun)) {
            $stmt->bindParam(':tahun', $tahun);
        }
        if(!empty($status)) {
            $stmt->bindParam(':status', $status);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    // Get total belum lunas by period
    public function getTotalBelumLunasByPeriod($bulan, $tahun) {
        $query = "SELECT COALESCE(SUM(jumlah), 0) as total FROM " . $this->table_name . " 
                  WHERE bulan = :bulan AND tahun = :tahun AND status = 'belum_lunas'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':bulan', $bulan);
        $stmt->bindParam(':tahun', $tahun);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    // Get total lunas by period
    public function getTotalLunasByPeriod($bulan, $tahun) {
        $query = "SELECT COALESCE(SUM(jumlah), 0) as total FROM " . $this->table_name . " 
                  WHERE bulan = :bulan AND tahun = :tahun AND status = 'lunas'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':bulan', $bulan);
        $stmt->bindParam(':tahun', $tahun);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    // Get total belum lunas overall
    public function getTotalBelumLunas() {
        $query = "SELECT COALESCE(SUM(jumlah), 0) as total FROM " . $this->table_name . " WHERE status = 'belum_lunas'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    // Get overdue bills
    public function getTagihanTerlambat() {
        $current_date = date('Y-m-d');
        $query = "SELECT b.*, p.nama as nama_pelanggan, p.id_pelanggan, p.alamat
                  FROM " . $this->table_name . " b
                  LEFT JOIN pelanggan p ON b.pelanggan_id = p.id
                  WHERE b.status = 'belum_lunas' 
                  AND b.tanggal_jatuh_tempo < :current_date
                  ORDER BY b.tanggal_jatuh_tempo ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':current_date', $current_date);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Generate billing for all active customers
    public function generateBilling($bulan, $tahun) {
        // Check if billing already exists
        $check_query = "SELECT COUNT(*) as total FROM " . $this->table_name . " 
                        WHERE bulan = :bulan AND tahun = :tahun";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':bulan', $bulan);
        $check_stmt->bindParam(':tahun', $tahun);
        $check_stmt->execute();
        $check = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($check['total'] > 0) {
            return ['success' => false, 'message' => 'Tagihan untuk periode ini sudah dibuat!'];
        }
        
        // Get all active customers
        $query = "SELECT id, nama, harga_paket FROM pelanggan WHERE status = 'aktif'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $pelanggan = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if(count($pelanggan) == 0) {
            return ['success' => false, 'message' => 'Tidak ada pelanggan aktif!'];
        }
        
        // Calculate due date (10th of next month)
        $due_date = date('Y-m-d', strtotime("$tahun-$bulan-10 +1 month"));
        
        $success_count = 0;
        foreach($pelanggan as $p) {
            $insert_query = "INSERT INTO " . $this->table_name . " 
                             SET pelanggan_id = :pelanggan_id, bulan = :bulan, tahun = :tahun, 
                                 jumlah = :jumlah, status = 'belum_lunas', 
                                 tanggal_jatuh_tempo = :tanggal_jatuh_tempo";
            $insert_stmt = $this->conn->prepare($insert_query);
            $insert_stmt->bindParam(':pelanggan_id', $p['id']);
            $insert_stmt->bindParam(':bulan', $bulan);
            $insert_stmt->bindParam(':tahun', $tahun);
            $insert_stmt->bindParam(':jumlah', $p['harga_paket']);
            $insert_stmt->bindParam(':tanggal_jatuh_tempo', $due_date);
            
            if($insert_stmt->execute()) {
                $success_count++;
            }
        }
        
        return [
            'success' => true, 
            'message' => "Berhasil membuat {$success_count} tagihan untuk bulan {$bulan}/{$tahun}"
        ];
    }

    // Mark billing as paid
    public function markAsPaid($id, $tanggal_bayar) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = 'lunas', tanggal_bayar = :tanggal_bayar 
                  WHERE id = :id AND status = 'belum_lunas'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':tanggal_bayar', $tanggal_bayar);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    // Delete billing
    public function delete($id = null) {
        if($id) {
            $this->id = $id;
        }
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND status = 'belum_lunas'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    // Get single billing by ID
    public function getOne($id = null) {
        if($id) {
            $this->id = $id;
        }
        $query = "SELECT b.*, p.nama as nama_pelanggan, p.id_pelanggan, p.alamat, p.paket_internet
                  FROM " . $this->table_name . " b
                  LEFT JOIN pelanggan p ON b.pelanggan_id = p.id
                  WHERE b.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update payment token
    public function updatePaymentToken($id, $token) {
        $query = "UPDATE " . $this->table_name . " SET payment_token = :token WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>