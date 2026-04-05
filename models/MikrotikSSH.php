<?php
class MikrotikSSH {
    private $connection;
    private $host;
    private $port;
    private $username;
    private $password;
    private $connected = false;
    
    public function __construct($host, $username, $password, $port = 22) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }
    
    // Connect to MikroTik via SSH using ssh2_connect
    private function connect() {
        if (!function_exists('ssh2_connect')) {
            error_log("SSH2 extension not installed. Trying alternative method...");
            return $this->connectWithExec();
        }
        
        $this->connection = @ssh2_connect($this->host, $this->port);
        if (!$this->connection) {
            error_log("Cannot connect to {$this->host}:{$this->port}");
            return false;
        }
        
        if (!@ssh2_auth_password($this->connection, $this->username, $this->password)) {
            error_log("Authentication failed for {$this->username}");
            return false;
        }
        
        $this->connected = true;
        return true;
    }
    
    // Alternative connection using exec() if ssh2 extension not available
    private function connectWithExec() {
        // Check if ssh command is available
        $check = shell_exec("which ssh 2>&1");
        if (empty($check)) {
            error_log("SSH command not available");
            return false;
        }
        
        $this->connected = true;
        return true;
    }
    
    // Execute command via SSH
    public function exec($command) {
        if (!$this->connected) {
            if (!$this->connect()) {
                return false;
            }
        }
        
        // If using ssh2 extension
        if (function_exists('ssh2_connect') && $this->connection) {
            $stream = ssh2_exec($this->connection, $command);
            if (!$stream) {
                return false;
            }
            stream_set_blocking($stream, true);
            $output = stream_get_contents($stream);
            fclose($stream);
            return $output;
        }
        
        // Alternative using exec()
        $sshCmd = "sshpass -p '{$this->password}' ssh -o StrictHostKeyChecking=no -p {$this->port} {$this->username}@{$this->host} \"{$command}\" 2>&1";
        return shell_exec($sshCmd);
    }
    
    // Test connection
    public function testConnection() {
        $result = $this->exec("/system resource print");
        return ($result !== false && !empty($result) && strpos($result, 'uptime') !== false);
    }
    
    // Get system resources
    public function getSystemResource() {
        $output = $this->exec("/system resource print");
        $data = [];
        
        if ($output) {
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    $parts = explode(':', $line, 2);
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    $data[$key] = $value;
                }
            }
        }
        
        return $data;
    }
    
    // ==================== ADDRESS LIST MANAGEMENT ====================
    
    public function enableAddressList($comment) {
        $cmd = "/ip firewall address-list set [find comment=\"$comment\"] disabled=no";
        return $this->exec($cmd);
    }
    
    public function disableAddressList($comment) {
        $cmd = "/ip firewall address-list set [find comment=\"$comment\"] disabled=yes";
        return $this->exec($cmd);
    }
    
    public function addAddressList($address, $comment, $list = "allowed") {
        $cmd = "/ip firewall address-list add address=$address list=$list comment=\"$comment\" disabled=no";
        return $this->exec($cmd);
    }
    
    public function removeAddressList($comment) {
        $cmd = "/ip firewall address-list remove [find comment=\"$comment\"]";
        return $this->exec($cmd);
    }
    
    // ==================== PPPoE SECRET MANAGEMENT ====================
    
    public function addPppoeSecret($name, $password, $profile = 'default', $service = 'pppoe') {
        $cmd = "/ppp secret add name=\"$name\" password=\"$password\" profile=\"$profile\" service=\"$service\" disabled=no";
        return $this->exec($cmd);
    }
    
    public function enablePppoeSecret($name) {
        $cmd = "/ppp secret enable [find name=\"$name\"]";
        return $this->exec($cmd);
    }
    
    public function disablePppoeSecret($name) {
        $cmd = "/ppp secret disable [find name=\"$name\"]";
        return $this->exec($cmd);
    }
    
    public function removePppoeSecret($name) {
        $cmd = "/ppp secret remove [find name=\"$name\"]";
        return $this->exec($cmd);
    }
    
    // ==================== BANDWIDTH MANAGEMENT ====================
    
    public function setBandwidthLimit($target, $limit) {
        $name = "limit_{$target}";
        $maxLimit = "{$limit}M/{$limit}M";
        
        $check = $this->exec("/queue simple print where name=\"$name\"");
        
        if (strpos($check, $name) !== false) {
            $cmd = "/queue simple set [find name=\"$name\"] max-limit=\"$maxLimit\"";
        } else {
            $cmd = "/queue simple add name=\"$name\" target=\"$target\" max-limit=\"$maxLimit\" disabled=no";
        }
        
        return $this->exec($cmd);
    }
    
    public function removeBandwidthLimit($target) {
        $name = "limit_{$target}";
        $cmd = "/queue simple remove [find name=\"$name\"]";
        return $this->exec($cmd);
    }
    
    // Disconnect
    public function disconnect() {
        if ($this->connection && function_exists('ssh2_exec')) {
            $this->exec("quit");
            $this->connection = null;
        }
        $this->connected = false;
    }
}
?>