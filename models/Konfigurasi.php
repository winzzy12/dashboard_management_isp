<?php
class Konfigurasi {
    private $conn;
    private $table_name = "konfigurasi";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function get($key_name) {
        $query = "SELECT value FROM " . $this->table_name . " WHERE key_name = :key_name LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':key_name', $key_name);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['value'] : null;
    }

    public function set($key_name, $value) {
        $query = "INSERT INTO " . $this->table_name . " (key_name, value) 
                  VALUES (:key_name, :value) 
                  ON DUPLICATE KEY UPDATE value = :value";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':key_name', $key_name);
        $stmt->bindParam(':value', $value);
        return $stmt->execute();
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY key_name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>