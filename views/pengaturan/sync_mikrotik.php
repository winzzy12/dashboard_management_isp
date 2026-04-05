<?php
header('Content-Type: application/json');

session_start();
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/database.php';
require_once '../../models/Pelanggan.php';
require_once '../../models/MikrotikAPI.php';

$database = new Database();
$db = $database->getConnection();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$comment = isset($_POST['comment']) ? $_POST['comment'] : '';

if($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

// Get pelanggan data
$pelanggan = new Pelanggan($db);
$pelanggan->id = $id;
$data = $pelanggan->getOne();

if(!$data) {
    echo json_encode(['success' => false, 'message' => 'Pelanggan tidak ditemukan']);
    exit();
}

$pelanggan->status = $data['status'];
$pelanggan->mikrotik_comment = $comment ?: $data['id_pelanggan'];
$pelanggan->mikrotik_ip = $data['mikrotik_ip'];
$pelanggan->mikrotik_profile = $data['mikrotik_profile'];
$pelanggan->id_pelanggan = $data['id_pelanggan'];

if($pelanggan->syncToMikrotik()) {
    echo json_encode(['success' => true, 'message' => 'Sync berhasil']);
} else {
    echo json_encode(['success' => false, 'message' => 'Sync gagal, periksa koneksi MikroTik']);
}
?>