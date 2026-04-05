<?php
class VpnServer {
    private $conn;
    private $table_name = "vpn_servers";

    public $id;
    public $kode_server;
    public $nama_server;
    public $ip_address;
    public $port;
    public $protocol;
    public $server_type;
    public $lokasi;
    public $max_clients;
    public $current_clients;
    public $status;
    public $config_file;
    public $keterangan;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read($search = '', $limit = 10, $offset = 0) {
        $query = "SELECT s.*, 
                         (SELECT COUNT(*) FROM vpn_clients WHERE server_id = s.id) as total_clients
                  FROM " . $this->table_name . " s";
        
        if(!empty($search)) {
            $query .= " WHERE s.nama_server LIKE :search OR s.kode_server LIKE :search OR s.lokasi LIKE :search";
        }
        
        $query .= " ORDER BY s.id DESC LIMIT :limit OFFSET :offset";
        
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
            $query .= " WHERE nama_server LIKE :search OR kode_server LIKE :search OR lokasi LIKE :search";
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
                  SET kode_server=:kode_server, nama_server=:nama_server, 
                      ip_address=:ip_address, port=:port, protocol=:protocol,
                      server_type=:server_type, lokasi=:lokasi, max_clients=:max_clients,
                      status=:status, config_file=:config_file, keterangan=:keterangan";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':kode_server', $this->kode_server);
        $stmt->bindParam(':nama_server', $this->nama_server);
        $stmt->bindParam(':ip_address', $this->ip_address);
        $stmt->bindParam(':port', $this->port);
        $stmt->bindParam(':protocol', $this->protocol);
        $stmt->bindParam(':server_type', $this->server_type);
        $stmt->bindParam(':lokasi', $this->lokasi);
        $stmt->bindParam(':max_clients', $this->max_clients);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':config_file', $this->config_file);
        $stmt->bindParam(':keterangan', $this->keterangan);
        
        return $stmt->execute();
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET kode_server=:kode_server, nama_server=:nama_server,
                      ip_address=:ip_address, port=:port, protocol=:protocol,
                      server_type=:server_type, lokasi=:lokasi, max_clients=:max_clients,
                      status=:status, config_file=:config_file, keterangan=:keterangan
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':kode_server', $this->kode_server);
        $stmt->bindParam(':nama_server', $this->nama_server);
        $stmt->bindParam(':ip_address', $this->ip_address);
        $stmt->bindParam(':port', $this->port);
        $stmt->bindParam(':protocol', $this->protocol);
        $stmt->bindParam(':server_type', $this->server_type);
        $stmt->bindParam(':lokasi', $this->lokasi);
        $stmt->bindParam(':max_clients', $this->max_clients);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':config_file', $this->config_file);
        $stmt->bindParam(':keterangan', $this->keterangan);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    public function delete() {
        $check_query = "SELECT COUNT(*) as total FROM vpn_clients WHERE server_id = :id";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':id', $this->id);
        $check_stmt->execute();
        $check = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($check['total'] > 0) {
            return ['success' => false, 'message' => 'Server memiliki ' . $check['total'] . ' client, tidak dapat dihapus!'];
        }
        
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        
        if($stmt->execute()) {
            return ['success' => true, 'message' => 'Server berhasil dihapus!'];
        }
        return ['success' => false, 'message' => 'Gagal menghapus server!'];
    }

    public function getOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE status = 'active' ORDER BY nama_server";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats() {
        $query = "SELECT 
                    COUNT(*) as total_servers,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as aktif,
                    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                    SUM(max_clients) as total_capacity,
                    SUM(current_clients) as total_clients
                  FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>