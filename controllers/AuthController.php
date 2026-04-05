<?php
require_once '../config/database.php';
require_once '../models/User.php';

class AuthController {
    private $db;
    private $user;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
    }
    
    public function login() {
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->user->username = $_POST['username'];
            $this->user->password = $_POST['password'];
            
            $result = $this->user->login();
            
            if($result) {
                session_start();
                $_SESSION['user_id'] = $result['id'];
                $_SESSION['username'] = $result['username'];
                $_SESSION['nama_lengkap'] = $result['nama_lengkap'];
                $_SESSION['role'] = $result['role'];
                $_SESSION['email'] = $result['email'];
                
                header("Location: ../index.php");
                exit();
            } else {
                $_SESSION['login_error'] = "Username atau password salah!";
                header("Location: ../views/login.php");
                exit();
            }
        }
    }
    
    public function logout() {
        session_start();
        session_destroy();
        header("Location: ../views/login.php");
        exit();
    }
    
    public function checkAuth() {
        session_start();
        if(!isset($_SESSION['user_id'])) {
            header("Location: ../views/login.php");
            exit();
        }
        return true;
    }
    
    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
    }
    
    public function changePassword() {
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            session_start();
            $user_id = $_SESSION['user_id'];
            $old_password = $_POST['old_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Validate input
            if($new_password !== $confirm_password) {
                return ['success' => false, 'message' => 'Password baru tidak cocok!'];
            }
            
            // Verify old password
            $query = "SELECT password FROM users WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($old_password, $row['password'])) {
                $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $update_query = "UPDATE users SET password = :password WHERE id = :id";
                $update_stmt = $this->db->prepare($update_query);
                $update_stmt->bindParam(':password', $new_password_hash);
                $update_stmt->bindParam(':id', $user_id);
                
                if($update_stmt->execute()) {
                    return ['success' => true, 'message' => 'Password berhasil diubah!'];
                }
            }
            
            return ['success' => false, 'message' => 'Password lama salah!'];
        }
    }
    
    public function registerUser() {
        if($_SERVER['REQUEST_METHOD'] == 'POST' && $this->isAdmin()) {
            $this->user->username = $_POST['username'];
            $this->user->password = $_POST['password'];
            $this->user->nama_lengkap = $_POST['nama_lengkap'];
            $this->user->email = $_POST['email'];
            
            if($this->user->register()) {
                return ['success' => true, 'message' => 'User berhasil ditambahkan!'];
            }
            return ['success' => false, 'message' => 'Gagal menambahkan user!'];
        }
    }
    
    public function getAllUsers() {
        $query = "SELECT id, username, nama_lengkap, email, role, created_at FROM users ORDER BY id DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateUserRole($user_id, $role) {
        if($this->isAdmin()) {
            $query = "UPDATE users SET role = :role WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':id', $user_id);
            return $stmt->execute();
        }
        return false;
    }
}
?>