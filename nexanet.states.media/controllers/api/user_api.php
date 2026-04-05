<?php
header('Content-Type: application/json');
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if($action == 'update_role') {
        $user_id = $_POST['user_id'];
        $role = $_POST['role'];
        
        $query = "UPDATE users SET role = :role WHERE id = :id AND id != :current_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':id', $user_id);
        $stmt->bindParam(':current_id', $_SESSION['user_id']);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    } elseif($action == 'delete_user') {
        $user_id = $_POST['user_id'];
        
        $query = "DELETE FROM users WHERE id = :id AND id != :current_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $user_id);
        $stmt->bindParam(':current_id', $_SESSION['user_id']);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }
}
?>