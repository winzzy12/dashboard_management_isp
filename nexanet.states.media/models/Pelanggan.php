<?php
class Pelanggan {
    private $conn;
    private $table_name = "pelanggan";

    public $id;
    public $id_pelanggan;
    public $nama;
    public $alamat;
    public $no_hp;
    public $paket_internet;
    public $harga_paket;
    public $status;
    public $paket_id;
    public $pop_id;
    public $odp_id;
    public $latitude;
    public $longitude;
    public $mikrotik_comment;
    public $mikrotik_ip;
    public $mikrotik_profile;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Read all customers with pagination and search
    public function read($search = '', $limit = 10, $offset = 0) {
        try {
            $query = "SELECT p.*, pk.nama_paket as paket_nama, po.nama_pop, od.nama_odp 
                      FROM " . $this->table_name . " p
                      LEFT JOIN paket_internet pk ON p.paket_id = pk.id
                      LEFT JOIN pop po ON p.pop_id = po.id
                      LEFT JOIN odp od ON p.odp_id = od.id";
            
            if(!empty($search)) {
                $query .= " WHERE p.nama LIKE :search OR p.id_pelanggan LIKE :search OR p.alamat LIKE :search OR p.no_hp LIKE :search";
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
        } catch(PDOException $e) {
            error_log("Error in read: " . $e->getMessage());
            $empty_stmt = $this->conn->prepare("SELECT * FROM " . $this->table_name . " WHERE 1=0");
            $empty_stmt->execute();
            return $empty_stmt;
        }
    }

    // Get total count of customers
    public function getTotal($search = '', $status = '') {
        try {
            $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
            
            $conditions = [];
            if(!empty($search)) {
                $conditions[] = "(nama LIKE :search OR id_pelanggan LIKE :search OR alamat LIKE :search OR no_hp LIKE :search)";
            }
            if(!empty($status)) {
                $conditions[] = "status = :status";
            }
            
            if(count($conditions) > 0) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $stmt = $this->conn->prepare($query);
            
            if(!empty($search)) {
                $searchTerm = "%{$search}%";
                $stmt->bindParam(':search', $searchTerm);
            }
            if(!empty($status)) {
                $stmt->bindParam(':status', $status);
            }
            
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['total'] ?? 0;
        } catch(PDOException $e) {
            error_log("Error in getTotal: " . $e->getMessage());
            return 0;
        }
    }

    // Create new customer
    public function create() {
        try {
            $query = "INSERT INTO " . $this->table_name . "
                      SET id_pelanggan=:id_pelanggan, nama=:nama, alamat=:alamat,
                          no_hp=:no_hp, paket_internet=:paket_internet, 
                          harga_paket=:harga_paket, status=:status, 
                          paket_id=:paket_id, pop_id=:pop_id, odp_id=:odp_id,
                          latitude=:latitude, longitude=:longitude,
                          mikrotik_comment=:mikrotik_comment, mikrotik_ip=:mikrotik_ip,
                          mikrotik_profile=:mikrotik_profile";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':id_pelanggan', $this->id_pelanggan);
            $stmt->bindParam(':nama', $this->nama);
            $stmt->bindParam(':alamat', $this->alamat);
            $stmt->bindParam(':no_hp', $this->no_hp);
            $stmt->bindParam(':paket_internet', $this->paket_internet);
            $stmt->bindParam(':harga_paket', $this->harga_paket);
            $stmt->bindParam(':status', $this->status);
            $stmt->bindParam(':paket_id', $this->paket_id);
            $stmt->bindParam(':pop_id', $this->pop_id);
            $stmt->bindParam(':odp_id', $this->odp_id);
            $stmt->bindParam(':latitude', $this->latitude);
            $stmt->bindParam(':longitude', $this->longitude);
            $stmt->bindParam(':mikrotik_comment', $this->mikrotik_comment);
            $stmt->bindParam(':mikrotik_ip', $this->mikrotik_ip);
            $stmt->bindParam(':mikrotik_profile', $this->mikrotik_profile);
            
            if($stmt->execute()) {
                // After successful create, sync to MikroTik if status is active
                if($this->status == 'aktif') {
                    $this->id = $this->conn->lastInsertId();
                    $this->syncToMikrotik();
                }
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Error in create: " . $e->getMessage());
            return false;
        }
    }

    // Update customer
    public function update() {
        try {
            $query = "UPDATE " . $this->table_name . "
                      SET nama=:nama, alamat=:alamat, no_hp=:no_hp,
                          paket_internet=:paket_internet, harga_paket=:harga_paket,
                          status=:status, paket_id=:paket_id, 
                          pop_id=:pop_id, odp_id=:odp_id,
                          latitude=:latitude, longitude=:longitude,
                          mikrotik_comment=:mikrotik_comment, mikrotik_ip=:mikrotik_ip,
                          mikrotik_profile=:mikrotik_profile
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':nama', $this->nama);
            $stmt->bindParam(':alamat', $this->alamat);
            $stmt->bindParam(':no_hp', $this->no_hp);
            $stmt->bindParam(':paket_internet', $this->paket_internet);
            $stmt->bindParam(':harga_paket', $this->harga_paket);
            $stmt->bindParam(':status', $this->status);
            $stmt->bindParam(':paket_id', $this->paket_id);
            $stmt->bindParam(':pop_id', $this->pop_id);
            $stmt->bindParam(':odp_id', $this->odp_id);
            $stmt->bindParam(':latitude', $this->latitude);
            $stmt->bindParam(':longitude', $this->longitude);
            $stmt->bindParam(':mikrotik_comment', $this->mikrotik_comment);
            $stmt->bindParam(':mikrotik_ip', $this->mikrotik_ip);
            $stmt->bindParam(':mikrotik_profile', $this->mikrotik_profile);
            $stmt->bindParam(':id', $this->id);
            
            if($stmt->execute()) {
                // After successful update, sync to MikroTik based on new status
                $this->syncToMikrotik();
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Error in update: " . $e->getMessage());
            return false;
        }
    }

    // Delete customer
    public function delete() {
        try {
            // First disable in MikroTik
            $this->syncToMikrotik(false); // Disable before delete
            
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error in delete: " . $e->getMessage());
            return false;
        }
    }

    // Get single customer by ID
    public function getOne() {
        try {
            $query = "SELECT p.*, pk.nama_paket as paket_nama, pk.kecepatan as paket_kecepatan,
                             po.nama_pop, po.kode_pop, po.lokasi as pop_lokasi,
                             od.nama_odp, od.kode_odp, od.jumlah_port, od.port_terpakai
                      FROM " . $this->table_name . " p
                      LEFT JOIN paket_internet pk ON p.paket_id = pk.id
                      LEFT JOIN pop po ON p.pop_id = po.id
                      LEFT JOIN odp od ON p.odp_id = od.id
                      WHERE p.id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error in getOne: " . $e->getMessage());
            return false;
        }
    }

    // Get all active customers (for dropdown)
    public function getAll() {
        try {
            $query = "SELECT id, id_pelanggan, nama, harga_paket, mikrotik_comment, mikrotik_ip 
                      FROM " . $this->table_name . " 
                      WHERE status = 'aktif' 
                      ORDER BY nama";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error in getAll: " . $e->getMessage());
            return [];
        }
    }

    // ==================== MIKROTIK INTEGRATION ====================

    // Sync customer to MikroTik
    public function syncToMikrotik($enable = null) {
        require_once 'MikrotikAPI.php';
        $mikrotik = new MikrotikAPI($this->conn);
        
        // Determine action based on status if enable not specified
        if($enable === null) {
            $enable = ($this->status == 'aktif');
        }
        
        try {
            if($enable) {
                return $this->enableMikrotik($mikrotik);
            } else {
                return $this->disableMikrotik($mikrotik);
            }
        } catch(Exception $e) {
            error_log("MikroTik sync error for customer {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    // Enable customer in MikroTik
    private function enableMikrotik($mikrotik) {
        $success = true;
        
        // Connect to MikroTik
        if($mikrotik->connect()) {
            // Set default comment if empty
            if(empty($this->mikrotik_comment)) {
                $this->mikrotik_comment = $this->id_pelanggan;
            }
            
            // Enable address list entry
            if($this->mikrotik_ip) {
                if(!$mikrotik->setAddressList($this->mikrotik_comment, $this->mikrotik_ip, true)) {
                    $success = false;
                    error_log("Failed to enable address list for " . $this->mikrotik_comment);
                }
            }
            
            // Enable PPPoE secret
            if(!$mikrotik->enablePppoeSecret($this->mikrotik_comment)) {
                // Try to create if not exists
                $profile = !empty($this->mikrotik_profile) ? $this->mikrotik_profile : 'default';
                if(!$mikrotik->addPppoeSecret($this->mikrotik_comment, $this->id_pelanggan, 'pppoe', $profile)) {
                    $success = false;
                    error_log("Failed to enable/create PPPoE secret for " . $this->mikrotik_comment);
                }
            }
            
            $mikrotik->disconnect();
        } else {
            $success = false;
            error_log("Failed to connect to MikroTik for customer {$this->id}");
        }
        
        return $success;
    }

    // Disable customer in MikroTik
    private function disableMikrotik($mikrotik) {
        $success = true;
        
        // Connect to MikroTik
        if($mikrotik->connect()) {
            // Disable address list entry
            if($this->mikrotik_ip) {
                if(!$mikrotik->setAddressList($this->mikrotik_comment, $this->mikrotik_ip, false)) {
                    $success = false;
                    error_log("Failed to disable address list for " . $this->mikrotik_comment);
                }
            }
            
            // Disable PPPoE secret
            if(!$mikrotik->disablePppoeSecret($this->mikrotik_comment)) {
                $success = false;
                error_log("Failed to disable PPPoE secret for " . $this->mikrotik_comment);
            }
            
            $mikrotik->disconnect();
        } else {
            $success = false;
            error_log("Failed to connect to MikroTik for customer {$this->id}");
        }
        
        return $success;
    }

    // Update status and sync to MikroTik
    public function updateStatusWithMikrotik($id, $newStatus) {
        $this->id = $id;
        $data = $this->getOne();
        
        if($data) {
            $oldStatus = $data['status'];
            $this->status = $newStatus;
            $this->mikrotik_comment = $data['mikrotik_comment'] ?? $data['id_pelanggan'];
            $this->mikrotik_ip = $data['mikrotik_ip'];
            $this->mikrotik_profile = $data['mikrotik_profile'] ?? 'default';
            $this->id_pelanggan = $data['id_pelanggan'];
            $this->nama = $data['nama'];
            
            // Update database
            $updateQuery = "UPDATE " . $this->table_name . " SET status = :status WHERE id = :id";
            $stmt = $this->conn->prepare($updateQuery);
            $stmt->bindParam(':status', $newStatus);
            $stmt->bindParam(':id', $id);
            
            if($stmt->execute()) {
                // Sync to MikroTik based on new status
                return $this->syncToMikrotik();
            }
        }
        
        return false;
    }

    // Sync all active customers to MikroTik (bulk sync)
    public function syncAllToMikrotik() {
        $query = "SELECT id, id_pelanggan, status, mikrotik_comment, mikrotik_ip, mikrotik_profile 
                  FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $success_count = 0;
        $fail_count = 0;
        
        foreach($customers as $customer) {
            $this->id = $customer['id'];
            $this->id_pelanggan = $customer['id_pelanggan'];
            $this->status = $customer['status'];
            $this->mikrotik_comment = $customer['mikrotik_comment'] ?? $customer['id_pelanggan'];
            $this->mikrotik_ip = $customer['mikrotik_ip'];
            $this->mikrotik_profile = $customer['mikrotik_profile'] ?? 'default';
            
            if($this->syncToMikrotik()) {
                $success_count++;
            } else {
                $fail_count++;
            }
        }
        
        return [
            'success' => $success_count,
            'fail' => $fail_count,
            'total' => count($customers)
        ];
    }

    // ==================== ADDITIONAL METHODS ====================

    // Get customers by POP
    public function getByPop($pop_id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE pop_id = :pop_id ORDER BY nama";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':pop_id', $pop_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error in getByPop: " . $e->getMessage());
            return [];
        }
    }

    // Get customers by ODP
    public function getByOdp($odp_id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE odp_id = :odp_id ORDER BY nama";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':odp_id', $odp_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error in getByOdp: " . $e->getMessage());
            return [];
        }
    }

    // Get customers without coordinates
    public function getWithoutCoordinates() {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                      WHERE (latitude IS NULL OR longitude IS NULL) 
                      AND status = 'aktif'
                      ORDER BY nama";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error in getWithoutCoordinates: " . $e->getMessage());
            return [];
        }
    }

    // Update customer coordinates
    public function updateCoordinates($id, $lat, $lng) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET latitude = :lat, longitude = :lng 
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':lat', $lat);
            $stmt->bindParam(':lng', $lng);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error in updateCoordinates: " . $e->getMessage());
            return false;
        }
    }

    // Get customer statistics
    public function getStatistics() {
        try {
            $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
                        SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif,
                        SUM(harga_paket) as total_pendapatan,
                        AVG(harga_paket) as rata_rata_paket,
                        COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 END) as memiliki_koordinat,
                        COUNT(CASE WHEN mikrotik_comment IS NOT NULL THEN 1 END) as terintegrasi_mikrotik
                      FROM " . $this->table_name;
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error in getStatistics: " . $e->getMessage());
            return [
                'total' => 0,
                'aktif' => 0,
                'nonaktif' => 0,
                'total_pendapatan' => 0,
                'rata_rata_paket' => 0,
                'memiliki_koordinat' => 0,
                'terintegrasi_mikrotik' => 0
            ];
        }
    }
}
?>