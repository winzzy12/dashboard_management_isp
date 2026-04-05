<?php
require_once __DIR__ . '/MikrotikSSH.php';

class MikrotikManager {
    private $conn;
    private $mikrotik;
    private $config;
    
    public function __construct($db) {
        $this->conn = $db;
        $this->loadConfig();
    }
    
    private function loadConfig() {
        $query = "SELECT * FROM mikrotik_config WHERE is_active = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$this->config) {
            // Return default config instead of throwing exception
            $this->config = [
                'host' => '192.168.1.1',
                'port' => 22,
                'username' => 'admin',
                'password' => '',
                'connection_type' => 'ssh'
            ];
        }
        
        return $this->config;
    }
    
    public function getConnection() {
        if (!$this->mikrotik) {
            $this->mikrotik = new MikrotikSSH(
                $this->config['host'],
                $this->config['username'],
                $this->config['password'],
                $this->config['port']
            );
        }
        
        return $this->mikrotik;
    }
    
    // Enable user by comment
    public function enableUser($comment) {
        try {
            $mikrotik = $this->getConnection();
            
            // Enable address list
            $result1 = $mikrotik->enableAddressList($comment);
            
            // Enable PPPoE secret
            $result2 = $mikrotik->enablePppoeSecret($comment);
            
            return ($result1 !== false || $result2 !== false);
        } catch(Exception $e) {
            error_log("Enable user error: " . $e->getMessage());
            return false;
        }
    }
    
    // Disable user by comment
    public function disableUser($comment) {
        try {
            $mikrotik = $this->getConnection();
            
            // Disable address list
            $result1 = $mikrotik->disableAddressList($comment);
            
            // Disable PPPoE secret
            $result2 = $mikrotik->disablePppoeSecret($comment);
            
            return ($result1 !== false || $result2 !== false);
        } catch(Exception $e) {
            error_log("Disable user error: " . $e->getMessage());
            return false;
        }
    }
    
    // Add new user
    public function addUser($comment, $ipAddress = null, $password = null, $profile = 'default') {
        try {
            $mikrotik = $this->getConnection();
            $success = true;
            
            // Add to address list if IP provided
            if ($ipAddress) {
                if (!$mikrotik->addAddressList($ipAddress, $comment)) {
                    $success = false;
                }
            }
            
            // Add PPPoE secret if password provided
            if ($password) {
                if (!$mikrotik->addPppoeSecret($comment, $password, $profile)) {
                    $success = false;
                }
            }
            
            return $success;
        } catch(Exception $e) {
            error_log("Add user error: " . $e->getMessage());
            return false;
        }
    }
    
    // Remove user
    public function removeUser($comment) {
        try {
            $mikrotik = $this->getConnection();
            
            // Remove from address list
            $result1 = $mikrotik->removeAddressList($comment);
            
            // Remove PPPoE secret
            $result2 = $mikrotik->removePppoeSecret($comment);
            
            return ($result1 !== false || $result2 !== false);
        } catch(Exception $e) {
            error_log("Remove user error: " . $e->getMessage());
            return false;
        }
    }
    
    // Set bandwidth limit
    public function setBandwidth($target, $limitMbps) {
        try {
            $mikrotik = $this->getConnection();
            return $mikrotik->setBandwidthLimit($target, $limitMbps);
        } catch(Exception $e) {
            error_log("Set bandwidth error: " . $e->getMessage());
            return false;
        }
    }
    
    // Test connection
    public function testConnection() {
        try {
            $mikrotik = $this->getConnection();
            return $mikrotik->testConnection();
        } catch(Exception $e) {
            error_log("Test connection error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get system info
    public function getSystemInfo() {
        try {
            $mikrotik = $this->getConnection();
            return $mikrotik->getSystemResource();
        } catch(Exception $e) {
            error_log("Get system info error: " . $e->getMessage());
            return [];
        }
    }
}
?>