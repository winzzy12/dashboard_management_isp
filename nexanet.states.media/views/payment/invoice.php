<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../../config/database.php';
require_once '../../models/Billing.php';
require_once '../../models/RekeningBank.php';
require_once '../../models/TransaksiPembayaran.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check database connection
if(!$db) {
    die("<div class='alert alert-danger'>Koneksi database gagal! Silakan coba lagi nanti.</div>");
}

// Initialize models
$billing = new Billing($db);
$rekeningBank = new RekeningBank($db);
$transaksi = new TransaksiPembayaran($db);

// Get token from URL
$token = isset($_GET['token']) ? $_GET['token'] : '';

if(empty($token)) {
    die("<div class='alert alert-danger'>Token tidak valid!</div>");
}

// Find billing by payment token - ALWAYS GET LATEST DATA
$query = "SELECT b.*, p.nama as nama_pelanggan, p.id_pelanggan, p.alamat, p.no_hp, p.paket_internet
          FROM billing b
          LEFT JOIN pelanggan p ON b.pelanggan_id = p.id
          WHERE b.payment_token = :token LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':token', $token);
$stmt->execute();
$billing_data = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$billing_data) {
    die("<div class='alert alert-danger'>Tagihan tidak ditemukan atau link tidak valid!</div>");
}

// CHECK BILLING STATUS FIRST - If already paid, don't show payment form
if($billing_data['status'] == 'lunas') {
    // Billing is already paid, just show success message
    $is_paid = true;
} else {
    $is_paid = false;
}

// Get active rekening banks (only if not paid)
$active_rekening = $rekeningBank->getActive();

// Get existing transaction for this billing - check if already confirmed
$transaksi_query = "SELECT * FROM transaksi_pembayaran WHERE billing_id = :billing_id ORDER BY id DESC LIMIT 1";
$transaksi_stmt = $db->prepare($transaksi_query);
$transaksi_stmt->bindParam(':billing_id', $billing_data['id']);
$transaksi_stmt->execute();
$existing_transaction = $transaksi_stmt->fetch(PDO::FETCH_ASSOC);

$payment_success = false;
$payment_error = '';
$transaction_status = $existing_transaction ? $existing_transaction['status'] : '';

// If already confirmed by admin, mark as success display
if($existing_transaction && $existing_transaction['status'] == 'success') {
    $transaction_status = 'success';
}

// Handle payment confirmation - only if not paid yet
if(!$is_paid && isset($_POST['action']) && $_POST['action'] == 'confirm_payment') {
    $bank_name = trim($_POST['bank_name']);
    $bank_account = trim($_POST['bank_account']);
    $bank_account_name = trim($_POST['bank_account_name']);
    
    // Get amount from hidden field directly
    $amount = isset($_POST['amount_hidden']) ? (int)$_POST['amount_hidden'] : 0;
    
    // Get the correct billing amount
    $billing_amount = (int)$billing_data['jumlah'];
    
    $notes = trim($_POST['notes']);
    
    // Validate input
    if(empty($bank_name) || empty($bank_account) || empty($bank_account_name)) {
        $payment_error = "Semua field harus diisi!";
    } elseif($amount != $billing_amount) {
        $payment_error = "Jumlah pembayaran tidak sesuai!<br>
                         Yang Anda masukkan: Rp " . number_format($amount, 0, ',', '.') . "<br>
                         Yang harus dibayar: Rp " . number_format($billing_amount, 0, ',', '.');
    } else {
        // Check if already have pending or success transaction
        if($existing_transaction && ($existing_transaction['status'] == 'pending' || $existing_transaction['status'] == 'success')) {
            $payment_error = "Anda sudah melakukan konfirmasi pembayaran. Silakan tunggu konfirmasi dari admin.";
        } else {
            // Create transaction record
            $kode_transaksi = $transaksi->generateKodeTransaksi();
            
            $data = [
                'kode_transaksi' => $kode_transaksi,
                'pelanggan_id' => $billing_data['pelanggan_id'],
                'billing_id' => $billing_data['id'],
                'jumlah' => $amount,
                'metode_pembayaran' => 'bank_transfer',
                'rekening_id' => null,
                'qris_id' => null,
                'gateway_id' => null,
                'status' => 'pending',
                'payment_proof' => null,
                'payment_date' => date('Y-m-d H:i:s'),
                'bank_name' => $bank_name,
                'bank_account' => $bank_account,
                'bank_account_name' => $bank_account_name,
                'notes' => $notes
            ];
            
            if($transaksi->create($data)) {
                $payment_success = true;
                $transaction_status = 'pending';
                // Refresh transaction data
                $transaksi_stmt = $db->prepare($transaksi_query);
                $transaksi_stmt->bindParam(':billing_id', $billing_data['id']);
                $transaksi_stmt->execute();
                $existing_transaction = $transaksi_stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $payment_error = "Gagal memproses pembayaran! Silakan coba lagi.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Pembayaran - Nexanet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .invoice-container {
            max-width: 900px;
            margin: 30px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .invoice-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .invoice-body {
            padding: 30px;
        }
        .invoice-footer {
            background: #f8f9fc;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e3e6f0;
        }
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        .status-lunas {
            background-color: #1cc88a;
            color: white;
        }
        .status-belum {
            background-color: #f6c23e;
            color: #333;
        }
        .status-pending {
            background-color: #36b9cc;
            color: white;
        }
        .rekening-card {
            border-left: 4px solid #4e73df;
            margin-bottom: 15px;
            transition: transform 0.2s;
        }
        .rekening-card:hover {
            transform: translateY(-3px);
        }
        .btn-copy {
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-copy:hover {
            color: #4e73df !important;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .invoice-container {
                box-shadow: none;
                margin: 0;
            }
        }
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        .timeline-item {
            position: relative;
            padding-left: 40px;
            margin-bottom: 20px;
        }
        .timeline-icon {
            position: absolute;
            left: 0;
            top: 0;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .timeline-icon.pending {
            background-color: #f6c23e;
            color: #333;
        }
        .timeline-icon.success {
            background-color: #1cc88a;
            color: white;
        }
        .timeline-content {
            background: #f8f9fc;
            padding: 10px 15px;
            border-radius: 8px;
        }
        .required {
            color: red;
        }
        .payment-success-card {
            background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <i class="fas fa-network-wired fa-3x mb-3"></i>
            <h2>INVOICE PEMBAYARAN</h2>
            <p>Nexanet Internet Service Provider</p>
        </div>
        
        <div class="invoice-body">
            <!-- IF BILLING IS ALREADY PAID -->
            <?php if($is_paid): ?>
                <div class="card payment-success-card mb-4">
                    <div class="card-body text-center p-5">
                        <i class="fas fa-check-circle fa-5x mb-3"></i>
                        <h3>PEMBAYARAN LUNAS!</h3>
                        <p class="mb-0">Tagihan ini sudah dibayar dan lunas pada tanggal <?php echo date('d/m/Y', strtotime($billing_data['tanggal_bayar'])); ?>.</p>
                        <p>Terima kasih atas kepercayaan Anda.</p>
                        <hr>
                        <a href="#" class="btn btn-light" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Invoice
                        </a>
                    </div>
                </div>
                
                <!-- Invoice Info (Readonly) -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Informasi Tagihan</h5>
                        <table class="table table-sm">
                            <tr>
                                <td width="40%">No. Invoice</td>
                                <td width="10%">:</td>
                                <td><strong>INV/<?php echo $billing_data['bulan']; ?>/<?php echo $billing_data['tahun']; ?>/<?php echo $billing_data['id']; ?></strong></td>
                            </tr>
                            <tr>
                                <td>Periode</td>
                                <td>:</td>
                                <td><?php echo $billing_data['bulan']; ?>/<?php echo $billing_data['tahun']; ?></td>
                            </tr>
                            <tr>
                                <td>Tanggal Bayar</td>
                                <td>:</td>
                                <td><?php echo date('d/m/Y', strtotime($billing_data['tanggal_bayar'])); ?></td>
                            </tr>
                            <tr>
                                <td>Status</td>
                                <td>:</td>
                                <td>
                                    <span class="status-badge status-lunas">
                                        <i class="fas fa-check-circle"></i> LUNAS
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Informasi Pelanggan</h5>
                        <table class="table table-sm">
                            <tr>
                                <td width="40%">ID Pelanggan</td>
                                <td width="10%">:</td>
                                <td><?php echo $billing_data['id_pelanggan']; ?></td>
                            </tr>
                            <tr>
                                <td>Nama</td>
                                <td>:</td>
                                <td><?php echo htmlspecialchars($billing_data['nama_pelanggan']); ?></td>
                            </tr>
                            <tr>
                                <td>Alamat</td>
                                <td>:</td>
                                <td><?php echo htmlspecialchars($billing_data['alamat']); ?></td>
                            </tr>
                            <tr>
                                <td>Paket</td>
                                <td>:</td>
                                <td><?php echo htmlspecialchars($billing_data['paket_internet']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Detail Tagihan -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-file-invoice"></i> Detail Tagihan
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Deskripsi</th>
                                    <th class="text-end">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Biaya Langganan Internet - <?php echo $billing_data['paket_internet']; ?> (<?php echo $billing_data['bulan']; ?>/<?php echo $billing_data['tahun']; ?>)</td>
                                    <td class="text-end">Rp <?php echo number_format($billing_data['jumlah'], 0, ',', '.'); ?></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td class="text-end">TOTAL</td>
                                    <td class="text-end text-primary">Rp <?php echo number_format($billing_data['jumlah'], 0, ',', '.'); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- IF BILLING IS NOT PAID YET -->
                
                <!-- Invoice Info -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Informasi Tagihan</h5>
                        <table class="table table-sm">
                            <tr>
                                <td width="40%">No. Invoice</td>
                                <td width="10%">:</td>
                                <td><strong>INV/<?php echo $billing_data['bulan']; ?>/<?php echo $billing_data['tahun']; ?>/<?php echo $billing_data['id']; ?></strong></td>
                            </tr>
                            <tr>
                                <td>Periode</td>
                                <td>:</td>
                                <td><?php echo $billing_data['bulan']; ?>/<?php echo $billing_data['tahun']; ?></td>
                            </tr>
                            <tr>
                                <td>Jatuh Tempo</td>
                                <td>:</td>
                                <td><?php echo date('d/m/Y', strtotime($billing_data['tanggal_jatuh_tempo'])); ?></td>
                            </tr>
                            <tr>
                                <td>Status</td>
                                <td>:</td>
                                <td>
                                    <?php if($transaction_status == 'pending'): ?>
                                        <span class="status-badge status-pending">
                                            <i class="fas fa-clock"></i> MENUNGGU KONFIRMASI
                                        </span>
                                    <?php elseif($transaction_status == 'success'): ?>
                                        <span class="status-badge status-lunas">
                                            <i class="fas fa-check-circle"></i> LUNAS
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-belum">
                                            <i class="fas fa-clock"></i> BELUM LUNAS
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Informasi Pelanggan</h5>
                        <table class="table table-sm">
                            <tr>
                                <td width="40%">ID Pelanggan</td>
                                <td width="10%">:</td>
                                <td><?php echo $billing_data['id_pelanggan']; ?></td>
                            </tr>
                            <tr>
                                <td>Nama</td>
                                <td>:</td>
                                <td><?php echo htmlspecialchars($billing_data['nama_pelanggan']); ?></td>
                            </tr>
                            <tr>
                                <td>Alamat</td>
                                <td>:</td>
                                <td><?php echo htmlspecialchars($billing_data['alamat']); ?></td>
                            </tr>
                            <tr>
                                <td>Paket</td>
                                <td>:</td>
                                <td><?php echo htmlspecialchars($billing_data['paket_internet']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Status Timeline for Pending -->
                <?php if($transaction_status == 'pending'): ?>
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-info-circle"></i> Status Konfirmasi Pembayaran
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-icon success">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="timeline-content">
                                    <strong>Konfirmasi Diterima</strong>
                                    <p class="mb-0 small">Pembayaran Anda telah kami terima dan sedang menunggu verifikasi oleh admin.</p>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-icon pending">
                                    <i class="fas fa-spinner fa-pulse"></i>
                                </div>
                                <div class="timeline-content">
                                    <strong>Verifikasi Admin</strong>
                                    <p class="mb-0 small">Admin akan memverifikasi pembayaran Anda dalam waktu 1x24 jam.</p>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Perhatian:</strong> Status pembayaran akan berubah menjadi <strong>LUNAS</strong> setelah admin mengkonfirmasi transfer Anda.
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Detail Tagihan -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-file-invoice"></i> Detail Tagihan
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Deskripsi</th>
                                    <th class="text-end">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Biaya Langganan Internet - <?php echo $billing_data['paket_internet']; ?> (<?php echo $billing_data['bulan']; ?>/<?php echo $billing_data['tahun']; ?>)</td>
                                    <td class="text-end">Rp <?php echo number_format($billing_data['jumlah'], 0, ',', '.'); ?></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td class="text-end">TOTAL</td>
                                    <td class="text-end text-primary">Rp <?php echo number_format($billing_data['jumlah'], 0, ',', '.'); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
                <!-- Rekening Pembayaran (only if not pending and not paid) -->
                <?php if(!$transaction_status || $transaction_status != 'pending'): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-university"></i> Rekening Pembayaran
                    </div>
                    <div class="card-body">
                        <p>Silakan transfer ke salah satu rekening berikut:</p>
                        <?php if(!empty($active_rekening)): ?>
                            <?php foreach($active_rekening as $rek): ?>
                            <div class="card rekening-card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($rek['nama_bank']); ?></h6>
                                            <p class="mb-0">
                                                <strong>No. Rekening:</strong> 
                                                <code><?php echo chunk_split($rek['nomor_rekening'], 4, ' '); ?></code>
                                                <i class="fas fa-copy text-muted btn-copy ms-2" 
                                                   onclick="copyToClipboard('<?php echo $rek['nomor_rekening']; ?>')"
                                                   title="Salin nomor rekening"></i>
                                            </p>
                                            <p class="mb-0"><strong>Atas Nama:</strong> <?php echo htmlspecialchars($rek['nama_pemilik']); ?></p>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <span class="badge bg-primary">Prioritas</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">Belum ada rekening yang tersedia. Silakan hubungi admin.</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Form Konfirmasi Pembayaran (only if not pending and not paid) -->
                <?php if($transaction_status != 'pending' && $transaction_status != 'success'): ?>
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
                    </div>
                    <div class="card-body">
                        <?php if($payment_error): ?>
                            <div class="alert alert-danger"><?php echo $payment_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if($payment_success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Konfirmasi pembayaran berhasil! 
                                Silakan tunggu konfirmasi dari admin. Status pembayaran akan berubah menjadi LUNAS setelah admin memverifikasi.
                            </div>
                        <?php else: ?>
                            <p>Setelah melakukan transfer, silakan konfirmasi pembayaran Anda dengan mengisi data berikut:</p>
                            <form method="POST" action="" id="confirmForm">
                                <input type="hidden" name="action" value="confirm_payment">
                                <input type="hidden" name="amount_hidden" id="amount_hidden" value="<?php echo $billing_data['jumlah']; ?>">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Bank Tujuan <span class="required">*</span></label>
                                        <select class="form-select" name="bank_name" id="bank_name" required>
                                            <option value="">Pilih Bank Tujuan</option>
                                            <?php foreach($active_rekening as $rek): ?>
                                            <option value="<?php echo htmlspecialchars($rek['nama_bank']); ?>">
                                                <?php echo htmlspecialchars($rek['nama_bank']); ?> - <?php echo chunk_split($rek['nomor_rekening'], 4, ' '); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nama Bank Pengirim <span class="required">*</span></label>
                                        <input type="text" class="form-control" name="bank_account_name" id="bank_account_name" 
                                               placeholder="Contoh: BCA, Mandiri, BRI" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">No. Rekening Pengirim <span class="required">*</span></label>
                                        <input type="text" class="form-control" name="bank_account" id="bank_account" 
                                               placeholder="Masukkan nomor rekening pengirim" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Jumlah Transfer</label>
                                        <input type="text" class="form-control" id="amount_display" 
                                               value="Rp <?php echo number_format($billing_data['jumlah'], 0, ',', '.'); ?>" readonly>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Catatan (Opsional)</label>
                                    <textarea class="form-control" name="notes" id="notes" rows="2" 
                                              placeholder="Contoh: Transfer dari BCA a/n Budi, sudah termasuk biaya admin"></textarea>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Penting:</strong> Pastikan data yang Anda masukkan sudah benar. Konfirmasi pembayaran hanya bisa dilakukan satu kali.
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php elseif($transaction_status == 'pending'): ?>
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <i class="fas fa-clock"></i> Menunggu Konfirmasi Admin
                    </div>
                    <div class="card-body text-center">
                        <i class="fas fa-hourglass-half fa-4x text-warning mb-3"></i>
                        <h5>Konfirmasi Pembayaran Diterima</h5>
                        <p>Pembayaran Anda sedang menunggu verifikasi oleh admin.</p>
                        <p>Status akan berubah menjadi <strong>LUNAS</strong> setelah admin mengkonfirmasi transfer Anda.</p>
                        <hr>
                        <p class="text-muted small">Jika dalam waktu 1x24 jam status belum berubah, silakan hubungi customer service.</p>
                    </div>
                </div>
                <?php elseif($transaction_status == 'success'): ?>
                <div class="card payment-success-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-4x mb-3"></i>
                        <h4>PEMBAYARAN TELAH DIKONFIRMASI!</h4>
                        <p>Tagihan Anda sudah dinyatakan LUNAS oleh admin.</p>
                        <p>Terima kasih atas kepercayaan Anda.</p>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="invoice-footer no-print">
            <small>&copy; <?php echo date('Y'); ?> Nexanet Internet Service Provider. All rights reserved.</small>
            <br>
            <small>Jika ada pertanyaan, silakan hubungi customer service kami.</small>
        </div>
    </div>
    
    <script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            const btn = event.target;
            const originalClass = btn.className;
            btn.className = 'fas fa-check text-success ms-2';
            setTimeout(() => {
                btn.className = originalClass;
            }, 2000);
        });
    }
    
    // Form validation
    document.getElementById('confirmForm')?.addEventListener('submit', function(e) {
        const bankName = document.getElementById('bank_name').value;
        const bankAccountName = document.getElementById('bank_account_name').value;
        const bankAccount = document.getElementById('bank_account').value;
        
        if(!bankName) {
            e.preventDefault();
            alert('Silakan pilih bank tujuan!');
            return false;
        }
        if(!bankAccountName) {
            e.preventDefault();
            alert('Silakan masukkan nama bank pengirim!');
            return false;
        }
        if(!bankAccount) {
            e.preventDefault();
            alert('Silakan masukkan nomor rekening pengirim!');
            return false;
        }
        
        if(!confirm('Pastikan data yang Anda masukkan sudah benar. Konfirmasi pembayaran tidak dapat diubah setelah dikirim. Lanjutkan?')) {
            e.preventDefault();
            return false;
        }
    });
    </script>
</body>
</html>