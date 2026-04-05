<?php
class VpnClient {
    private $conn;
    private $table_name = "vpn_clients";

    public $id;
    public $client_id;
    public $username;
    public $password;
    public $server_id;
    public $pelanggan_id;
    public $ip_address;
    public $port;
    public $certificate;
    public $private_key;
    public $public_key;
    public $status;
    public $bandwidth_limit;
    public $expired_date;
    public $last_connected;
    public $data_used;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read($search = '', $server_id = '', $limit = 10, $offset = 0) {
        $query = "SELECT c.*, s.nama_server as server_name, p.nama as pelanggan_nama, p.id_pelanggan
                  FROM " . $this->table_name . " c
                  LEFT JOIN vpn_servers s ON c.server_id = s.id
                  LEFT JOIN pelanggan p ON c.pelanggan_id = p.id";
        
        $conditions = [];
        if(!empty($search)) {
            $conditions[] = "(c.username LIKE :search OR c.client_id LIKE :search OR p.nama LIKE :search)";
        }
        if(!empty($server_id)) {
            $conditions[] = "c.server_id = :server_id";
        }
        
        if(count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY c.id DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        
        if(!empty($search)) {
            $searchTerm = "%{$search}%";
            $stmt->bindParam(':search', $searchTerm);
        }
        if(!empty($server_id)) {
            $stmt->bindParam(':server_id', $server_id);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }

    public function getTotal($search = '', $server_id = '') {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " c";
        
        $conditions = [];
        if(!empty($search)) {
            $conditions[] = "(c.username LIKE :search OR c.client_id LIKE :search)";
        }
        if(!empty($server_id)) {
            $conditions[] = "c.server_id = :server_id";
        }
        
        if(count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $stmt = $this->conn->prepare($query);
        
        if(!empty($search)) {
            $searchTerm = "%{$search}%";
            $stmt->bindParam(':search', $searchTerm);
        }
        if(!empty($server_id)) {
            $stmt->bindParam(':server_id', $server_id);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET client_id=:client_id, username=:username, password=:password,
                      server_id=:server_id, pelanggan_id=:pelanggan_id,
                      ip_address=:ip_address, port=:port,
                      certificate=:certificate, private_key=:private_key, public_key=:public_key,
                      status=:status, bandwidth_limit=:bandwidth_limit,
                      expired_date=:expired_date";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':client_id', $this->client_id);
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':password', $this->password);
        $stmt->bindParam(':server_id', $this->server_id);
        $stmt->bindParam(':pelanggan_id', $this->pelanggan_id);
        $stmt->bindParam(':ip_address', $this->ip_address);
        $stmt->bindParam(':port', $this->port);
        $stmt->bindParam(':certificate', $this->certificate);
        $stmt->bindParam(':private_key', $this->private_key);
        $stmt->bindParam(':public_key', $this->public_key);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':bandwidth_limit', $this->bandwidth_limit);
        $stmt->bindParam(':expired_date', $this->expired_date);
        
        return $stmt->execute();
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET username=:username, password=:password,
                      server_id=:server_id, pelanggan_id=:pelanggan_id,
                      ip_address=:ip_address, port=:port,
                      certificate=:certificate, private_key=:private_key, public_key=:public_key,
                      status=:status, bandwidth_limit=:bandwidth_limit,
                      expired_date=:expired_date
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':password', $this->password);
        $stmt->bindParam(':server_id', $this->server_id);
        $stmt->bindParam(':pelanggan_id', $this->pelanggan_id);
        $stmt->bindParam(':ip_address', $this->ip_address);
        $stmt->bindParam(':port', $this->port);
        $stmt->bindParam(':certificate', $this->certificate);
        $stmt->bindParam(':private_key', $this->private_key);
        $stmt->bindParam(':public_key', $this->public_key);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':bandwidth_limit', $this->bandwidth_limit);
        $stmt->bindParam(':expired_date', $this->expired_date);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        
        if($stmt->execute()) {
            return ['success' => true, 'message' => 'Client berhasil dihapus!'];
        }
        return ['success' => false, 'message' => 'Gagal menghapus client!'];
    }

    public function getOne() {
        $query = "SELECT c.*, s.nama_server as server_name, p.nama as pelanggan_nama
                  FROM " . $this->table_name . " c
                  LEFT JOIN vpn_servers s ON c.server_id = s.id
                  LEFT JOIN pelanggan p ON c.pelanggan_id = p.id
                  WHERE c.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getStats() {
        $query = "SELECT 
                    COUNT(*) as total_clients,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as aktif,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                    SUM(data_used) as total_data_used
                  FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function generateClientId() {
        $prefix = 'VPN';
        $number = rand(1000, 9999);
        $this->client_id = $prefix . $number . date('ymd');
        return $this->client_id;
    }

    public function generateConfig() {
        $server = $this->getServerInfo();
        $config = "# VPN Configuration for {$this->username}\n";
        $config .= "client\n";
        $config .= "dev tun\n";
        $config .= "proto {$server['protocol']}\n";
        $config .= "remote {$server['ip_address']} {$server['port']}\n";
        $config .= "resolv-retry infinite\n";
        $config .= "nobind\n";
        $config .= "persist-key\n";
        $config .= "persist-tun\n";
        $config .= "remote-cert-tls server\n";
        $config .= "cipher AES-256-CBC\n";
        $config .= "auth SHA256\n";
        $config .= "key-direction 1\n";
        $config .= "verb 3\n";
        
        if($this->certificate) {
            $config .= "\n<ca>\n{$this->certificate}\n</ca>\n";
        }
        if($this->private_key) {
            $config .= "\n<key>\n{$this->private_key}\n</key>\n";
        }
        
        return $config;
    }

    private function getServerInfo() {
        $query = "SELECT * FROM vpn_servers WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->server_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>