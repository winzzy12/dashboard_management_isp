<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../../config/database.php';
require_once '../../models/Pelanggan.php';

$database = new Database();
$db = $database->getConnection();
$pelanggan = new Pelanggan($db);

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$new_status = isset($_POST['status']) ? $_POST['status'] : '';

if($id && $new_status) {
    if($pelanggan->updateStatusWithMikrotik($id, $new_status)) {
        $_SESSION['success'] = "Status berhasil diubah dan sinkronisasi ke MikroTik!";
    } else {
        $_SESSION['error'] = "Status berubah tapi gagal sinkron ke MikroTik!";
    }
} else {
    $_SESSION['error'] = "Data tidak valid!";
}

header("Location: index.php");
exit();
?>