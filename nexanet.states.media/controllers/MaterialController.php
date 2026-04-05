<?php
require_once '../config/database.php';
require_once '../models/Material.php';

class MaterialController {
    private $db;
    private $material;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->material = new Material($this->db);
    }
    
    public function index() {
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->material->read($search, $limit, $offset);
        $total = $this->material->getTotal($search);
        $total_pages = ceil($total / $limit);
        
        return [
            'materials' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'search' => $search
        ];
    }
    
    public function create() {
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->material->nama_material = $_POST['nama_material'];
            $this->material->stok = $_POST['stok'];
            $this->material->harga = $_POST['harga'];
            $this->material->keterangan = $_POST['keterangan'];
            
            if($this->material->create()) {
                $_SESSION['success'] = "Material berhasil ditambahkan!";
                header("Location: ../views/material/index.php");
                exit();
            } else {
                $_SESSION['error'] = "Gagal menambahkan material!";
            }
        }
    }
    
    public function edit($id) {
        $this->material->id = $id;
        $material = $this->material->getOne();
        
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->material->nama_material = $_POST['nama_material'];
            $this->material->stok = $_POST['stok'];
            $this->material->harga = $_POST['harga'];
            $this->material->keterangan = $_POST['keterangan'];
            
            if($this->material->update()) {
                $_SESSION['success'] = "Material berhasil diupdate!";
                header("Location: ../views/material/index.php");
                exit();
            } else {
                $_SESSION['error'] = "Gagal mengupdate material!";
            }
        }
        
        return $material;
    }
    
    public function delete($id) {
        $this->material->id = $id;
        if($this->material->delete()) {
            $_SESSION['success'] = "Material berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus material!";
        }
        header("Location: ../views/material/index.php");
        exit();
    }
    
    public function updateStok() {
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $id = $_POST['id'];
            $jumlah = $_POST['jumlah'];
            $type = $_POST['type']; // 'in' or 'out'
            
            if($type == 'in') {
                $query = "UPDATE material SET stok = stok + :jumlah WHERE id = :id";
            } else {
                $query = "UPDATE material SET stok = stok - :jumlah WHERE id = :id AND stok >= :jumlah";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':jumlah', $jumlah);
            $stmt->bindParam(':id', $id);
            
            if($stmt->execute()) {
                return ['success' => true, 'message' => 'Stok berhasil diupdate!'];
            }
            return ['success' => false, 'message' => 'Stok tidak mencukupi!'];
        }
    }
    
    public function getLowStock() {
        $query = "SELECT * FROM material WHERE stok < 10 ORDER BY stok ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function export() {
        $materials = $this->material->getAll();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="material_export.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Nama Material', 'Stok', 'Harga', 'Keterangan']);
        
        foreach($materials as $material) {
            fputcsv($output, [
                $material['id'],
                $material['nama_material'],
                $material['stok'],
                $material['harga'],
                $material['keterangan']
            ]);
        }
        
        fclose($output);
        exit();
    }
}
?>