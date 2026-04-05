<?php
class MikrotikAPI {
    private $conn;
    private $socket;
    private $host;
    private $port;
    private $username;
    private $password;
    private $connected = false;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Load MikroTik configuration from database
    public function loadConfig() {
        $query = "SELECT * FROM mikrotik_config WHERE is_active = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($config) {
            $this->host = $config['host'];
            $this->port = $config['port'];
            $this->username = $config['username'];
            $this->password = $config['password'];
            return true;
        }
        return false;
    }
    
    // Connect to MikroTik
    public function connect() {
        if(!$this->host || !$this->port) {
            $this->loadConfig();
        }
        
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
        
        if(!$this->socket) {
            error_log("MikroTik connection failed: $errstr ($errno)");
            return false;
        }
        
        // Read welcome message
        $welcome = fread($this->socket, 4096);
        
        // Send login
        $login = "/login\n";
        fwrite($this->socket, $login);
        $response = fread($this->socket, 4096);
        
        // Parse response for challenge
        if(preg_match('/=ret=([a-f0-9]+)/', $response, $matches)) {
            $challenge = $matches[1];
            $response_password = md5(chr(0) . $this->password . pack('H*', $challenge));
            $login_cmd = "/login\n=name=" . $this->username . "\n=response=00" . $response_password . "\n";
            fwrite($this->socket, $login_cmd);
            $response = fread($this->socket, 4096);
            
            if(strpos($response, '!done') !== false) {
                $this->connected = true;
                return true;
            }
        }
        
        return false;
    }
    
    // Send command to MikroTik
    public function command($cmd) {
        if(!$this->connected) {
            if(!$this->connect()) {
                return false;
            }
        }
        
        fwrite($this->socket, $cmd . "\n");
        $response = '';
        while(!feof($this->socket)) {
            $line = fgets($this->socket, 4096);
            $response .= $line;
            if(strpos($line, '!done') !== false) {
                break;
            }
        }
        
        return $response;
    }
    
    // Enable/Disable address list entry
    public function setAddressList($comment, $ipAddress, $enable = true) {
        // First, check if entry exists
        $checkCmd = "/ip firewall address-list print where comment=\"$comment\"";
        $response = $this->command($checkCmd);
        
        if(strpos($response, "!re") !== false) {
            // Entry exists, update it
            if($enable) {
                $cmd = "/ip firewall address-list set [find comment=\"$comment\"] address=$ipAddress disabled=no";
            } else {
                $cmd = "/ip firewall address-list set [find comment=\"$comment\"] disabled=yes";
            }
        } else {
            // Entry doesn't exist, create new
            if($enable) {
                $cmd = "/ip firewall address-list add address=$ipAddress comment=\"$comment\" disabled=no";
            } else {
                // If disable and doesn't exist, nothing to do
                return true;
            }
        }
        
        $result = $this->command($cmd);
        return strpos($result, '!done') !== false;
    }
    
    // Add or remove simple queue for bandwidth management
    public function setSimpleQueue($name, $target, $maxLimit = null, $enable = true) {
        if($enable && $maxLimit) {
            // Check if queue exists
            $checkCmd = "/queue simple print where name=\"$name\"";
            $response = $this->command($checkCmd);
            
            if(strpos($response, "!re") !== false) {
                // Update existing queue
                $cmd = "/queue simple set [find name=\"$name\"] target=\"$target\" max-limit=\"$maxLimit\" disabled=no";
            } else {
                // Create new queue
                $cmd = "/queue simple add name=\"$name\" target=\"$target\" max-limit=\"$maxLimit\" disabled=no";
            }
        } else {
            // Disable or remove queue
            $cmd = "/queue simple disable [find name=\"$name\"]";
        }
        
        $result = $this->command($cmd);
        return strpos($result, '!done') !== false;
    }
    
    // Disconnect from MikroTik
    public function disconnect() {
        if($this->socket) {
            fclose($this->socket);
            $this->connected = false;
        }
    }
    
    // Test connection
    public function testConnection() {
        $this->connect();
        $result = $this->command("/system resource print");
        $this->disconnect();
        return strpos($result, '!re') !== false;
    }
    
    // Get system resources
    public function getSystemResource() {
        $this->connect();
        $result = $this->command("/system resource print");
        $this->disconnect();
        return $result;
    }
    
    // Get active connections
    public function getActiveConnections() {
        $this->connect();
        $result = $this->command("/ip firewall connection print");
        $this->disconnect();
        return $result;
    }
    
    // Add PPPoE secret
    public function addPppoeSecret($name, $password, $service = 'pppoe', $profile = 'default') {
        $cmd = "/ppp secret add name=\"$name\" password=\"$password\" service=\"$service\" profile=\"$profile\" disabled=no";
        $result = $this->command($cmd);
        return strpos($result, '!done') !== false;
    }
    
    // Disable PPPoE secret
    public function disablePppoeSecret($name) {
        $cmd = "/ppp secret disable [find name=\"$name\"]";
        $result = $this->command($cmd);
        return strpos($result, '!done') !== false;
    }
    
    // Enable PPPoE secret
    public function enablePppoeSecret($name) {
        $cmd = "/ppp secret enable [find name=\"$name\"]";
        $result = $this->command($cmd);
        return strpos($result, '!done') !== false;
    }
}
?>