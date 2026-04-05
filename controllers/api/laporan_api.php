<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../models/Pemasukan.php';
require_once '../../models/Pengeluaran.php';

$database = new Database();
$db = $database->getConnection();
$pemasukan = new Pemasukan($db);
$pengeluaran = new Pengeluaran($db);

$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Get yearly data
$yearly_pemasukan = array_fill(0, 12, 0);
$yearly_pengeluaran = array_fill(0, 12, 0);

for($i = 1; $i <= 12; $i++) {
    $bulan = str_pad($i, 2, '0', STR_PAD_LEFT);
    $yearly_pemasukan[$i-1] = $pemasukan->getTotalAmount("$tahun-$bulan-01", "$tahun-$bulan-31");
    $yearly_pengeluaran[$i-1] = $pengeluaran->getTotalAmount("$tahun-$bulan-01", "$tahun-$bulan-31");
}

// Get expense composition for current month
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');
$query = "SELECT jenis_pengeluaran, SUM(jumlah) as total 
          FROM pengeluaran 
          WHERE tanggal BETWEEN :start AND :end 
          GROUP BY jenis_pengeluaran";
$stmt = $db->prepare($query);
$stmt->bindParam(':start', $start_date);
$stmt->bindParam(':end', $end_date);
$stmt->execute();
$expense_composition = array_fill(0, 5, 0);

while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $jenis = $row['jenis_pengeluaran'];
    if(strpos($jenis, 'Operasional') !== false) $expense_composition[0] = $row['total'];
    elseif(strpos($jenis, 'Gaji') !== false) $expense_composition[1] = $row['total'];
    elseif(strpos($jenis, 'Material') !== false) $expense_composition[2] = $row['total'];
    elseif(strpos($jenis, 'Internet') !== false) $expense_composition[3] = $row['total'];
    else $expense_composition[4] = $row['total'];
}

echo json_encode([
    'yearly' => [
        'pemasukan' => $yearly_pemasukan,
        'pengeluaran' => $yearly_pengeluaran
    ],
    'expense_composition' => $expense_composition
]);
?>