<?php
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $username;
    public $password;
    public $nama_lengkap;
    public $email;
    public $role;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login() {
        try {
            // First, check if user exists
            $query = "SELECT * FROM " . $this->table_name . " WHERE username = :username LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $this->username);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password
                if(password_verify($this->password, $row['password'])) {
                    return [
                        'id' => $row['id'],
                        'username' => $row['username'],
                        'nama_lengkap' => $row['nama_lengkap'],
                        'email' => $row['email'],
                        'role' => $row['role']
                    ];
                }
            }
            return false;
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    public function register() {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                      SET username=:username, password=:password, 
                          nama_lengkap=:nama_lengkap, email=:email, role='operator'";
            
            $stmt = $this->conn->prepare($query);
            
            $this->password = password_hash($this->password, PASSWORD_BCRYPT);
            
            $stmt->bindParam(':username', $this->username);
            $stmt->bindParam(':password', $this->password);
            $stmt->bindParam(':nama_lengkap', $this->nama_lengkap);
            $stmt->bindParam(':email', $this->email);
            
            if($stmt->execute()) {
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Register error: " . $e->getMessage());
            return false;
        }
    }
    
    // ADD THIS METHOD - Get user by ID
    public function getUserById($id) {
        try {
            $query = "SELECT id, username, nama_lengkap, email, role, password, created_at 
                      FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get user by ID error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get user by username
    public function getUserByUsername($username) {
        try {
            $query = "SELECT id, username, nama_lengkap, email, role, created_at 
                      FROM " . $this->table_name . " WHERE username = :username LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get user by username error: " . $e->getMessage());
            return false;
        }
    }
    
    // Update user password
    public function updatePassword($id, $new_password) {
        try {
            $query = "UPDATE " . $this->table_name . " SET password = :password WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Update password error: " . $e->getMessage());
            return false;
        }
    }
    
    // Update user role
    public function updateRole($id, $role) {
        try {
            $query = "UPDATE " . $this->table_name . " SET role = :role WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Update role error: " . $e->getMessage());
            return false;
        }
    }
    
    // Delete user
    public function deleteUser($id) {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Delete user error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get all users
    public function getAllUsers() {
        try {
            $query = "SELECT id, username, nama_lengkap, email, role, created_at 
                      FROM " . $this->table_name . " ORDER BY id DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get all users error: " . $e->getMessage());
            return [];
        }
    }
    
    // Check if username exists
    public function usernameExists($username) {
        try {
            $query = "SELECT id FROM " . $this->table_name . " WHERE username = :username LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            error_log("Check username error: " . $e->getMessage());
            return false;
        }
    }
}
?>