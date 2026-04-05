<?php
require_once '../config/database.php';
require_once '../models/Pemasukan.php';
require_once '../models/Pengeluaran.php';
require_once '../models/Billing.php';
require_once '../models/Pelanggan.php';

class LaporanController {
    private $db;
    private $pemasukan;
    private $pengeluaran;
    private $billing;
    private $pelanggan;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->pemasukan = new Pemasukan($this->db);
        $this->pengeluaran = new Pengeluaran($this->db);
        $this->billing = new Billing($this->db);
        $this->pelanggan = new Pelanggan($this->db);
    }
    
    public function laporanKeuangan() {
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
        
        $total_pemasukan = $this->pemasukan->getTotalAmount($start_date, $end_date);
        $total_pengeluaran = $this->pengeluaran->getTotalAmount($start_date, $end_date);
        $saldo = $total_pemasukan - $total_pengeluaran;
        
        $pemasukan_data = $this->pemasukan->read($start_date, $end_date, 9999, 0)->fetchAll(PDO::FETCH_ASSOC);
        $pengeluaran_data = $this->pengeluaran->read($start_date, $end_date, 9999, 0)->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'total_pemasukan' => $total_pemasukan,
            'total_pengeluaran' => $total_pengeluaran,
            'saldo' => $saldo,
            'pemasukan_data' => $pemasukan_data,
            'pengeluaran_data' => $pengeluaran_data
        ];
    }
    
    public function laporanTagihan() {
        $bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
        $tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
        
        $query = "SELECT b.*, p.nama as nama_pelanggan, p.alamat, p.no_hp, p.paket_internet
                  FROM billing b
                  LEFT JOIN pelanggan p ON b.pelanggan_id = p.id
                  WHERE b.bulan = :bulan AND b.tahun = :tahun
                  ORDER BY p.nama";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bulan', $bulan);
        $stmt->bindParam(':tahun', $tahun);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_tagihan = array_sum(array_column($data, 'jumlah'));
        $total_terbayar = array_sum(array_filter(array_column($data, 'jumlah'), function($key) use ($data) {
            return $data[$key]['status'] == 'lunas';
        }, ARRAY_FILTER_USE_KEY));
        
        return [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'data' => $data,
            'total_tagihan' => $total_tagihan,
            'total_terbayar' => $total_terbayar,
            'total_belum_terbayar' => $total_tagihan - $total_terbayar
        ];
    }
    
    public function laporanPelanggan() {
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        
        $query = "SELECT * FROM pelanggan";
        if($status) {
            $query .= " WHERE status = :status";
        }
        $query .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($query);
        if($status) {
            $stmt->bindParam(':status', $status);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_aktif = $this->pelanggan->getTotal('', 'aktif');
        $total_nonaktif = $this->pelanggan->getTotal('', 'nonaktif');
        
        return [
            'data' => $data,
            'total' => count($data),
            'total_aktif' => $total_aktif,
            'total_nonaktif' => $total_nonaktif,
            'status_filter' => $status
        ];
    }
    
    public function laporanMaterial() {
        $query = "SELECT * FROM material ORDER BY stok ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_stok = array_sum(array_column($data, 'stok'));
        $total_nilai = array_sum(array_map(function($item) {
            return $item['stok'] * $item['harga'];
        }, $data));
        
        return [
            'data' => $data,
            'total_item' => count($data),
            'total_stok' => $total_stok,
            'total_nilai' => $total_nilai
        ];
    }
    
    public function laporanRekapTahunan($tahun = null) {
        if(!$tahun) $tahun = date('Y');
        
        $query = "SELECT 
                    MONTH(tanggal) as bulan,
                    SUM(CASE WHEN type = 'pemasukan' THEN jumlah ELSE 0 END) as pemasukan,
                    SUM(CASE WHEN type = 'pengeluaran' THEN jumlah ELSE 0 END) as pengeluaran
                  FROM (
                      SELECT tanggal, jumlah, 'pemasukan' as type FROM pemasukan WHERE YEAR(tanggal) = :tahun
                      UNION ALL
                      SELECT tanggal, jumlah, 'pengeluaran' as type FROM pengeluaran WHERE YEAR(tanggal) = :tahun
                  ) as transactions
                  GROUP BY MONTH(tanggal)
                  ORDER BY bulan";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':tahun', $tahun);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_pemasukan = $this->pemasukan->getTotalAmountByYear($tahun);
        $total_pengeluaran = $this->pengeluaran->getTotalAmountByYear($tahun);
        
        return [
            'tahun' => $tahun,
            'data' => $data,
            'total_pemasukan' => $total_pemasukan,
            'total_pengeluaran' => $total_pengeluaran,
            'saldo' => $total_pemasukan - $total_pengeluaran
        ];
    }
    
    public function exportKeuangan($start_date, $end_date) {
        $data = $this->laporanKeuangan($start_date, $end_date);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="laporan_keuangan.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['LAPORAN KEUANGAN']);
        fputcsv($output, ['Periode:', $start_date, 's/d', $end_date]);
        fputcsv($output, []);
        fputcsv($output, ['RINGKASAN']);
        fputcsv($output, ['Total Pemasukan', 'Rp ' . number_format($data['total_pemasukan'], 0, ',', '.')]);
        fputcsv($output, ['Total Pengeluaran', 'Rp ' . number_format($data['total_pengeluaran'], 0, ',', '.')]);
        fputcsv($output, ['Saldo', 'Rp ' . number_format($data['saldo'], 0, ',', '.')]);
        fputcsv($output, []);
        fputcsv($output, ['DETAIL PEMASUKAN']);
        fputcsv($output, ['Tanggal', 'Pelanggan', 'Jumlah', 'Keterangan']);
        
        foreach($data['pemasukan_data'] as $item) {
            fputcsv($output, [
                $item['tanggal'],
                $item['nama_pelanggan'] ?? '-',
                $item['jumlah'],
                $item['keterangan']
            ]);
        }
        
        fputcsv($output, []);
        fputcsv($output, ['DETAIL PENGELUARAN']);
        fputcsv($output, ['Tanggal', 'Jenis', 'Jumlah', 'Keterangan']);
        
        foreach($data['pengeluaran_data'] as $item) {
            fputcsv($output, [
                $item['tanggal'],
                $item['jenis_pengeluaran'],
                $item['jumlah'],
                $item['keterangan']
            ]);
        }
        
        fclose($output);
        exit();
    }
    
    public function printPreview($type, $params = []) {
        ob_start();
        
        switch($type) {
            case 'keuangan':
                $data = $this->laporanKeuangan($params['start_date'], $params['end_date']);
                include '../views/laporan/print_keuangan.php';
                break;
            case 'tagihan':
                $data = $this->laporanTagihan($params['bulan'], $params['tahun']);
                include '../views/laporan/print_tagihan.php';
                break;
            case 'pelanggan':
                $data = $this->laporanPelanggan($params['status']);
                include '../views/laporan/print_pelanggan.php';
                break;
            case 'material':
                $data = $this->laporanMaterial();
                include '../views/laporan/print_material.php';
                break;
        }
        
        $html = ob_get_clean();
        echo $html;
    }
}
?>