<?php
class Database {
    private $host = "localhost";
    private $db_name = "nexanet_db";
    private $username = "root";
    private $password = "Nusan3T_Open";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch(PDOException $exception) {
            die("Database connection failed: " . $exception->getMessage());
        }
        return $this->conn;
    }
}
?>