<?php
session_start();
if(isset($_SESSION['user_id'])) { 
    header("Location: ../views/dashboard/index.php"); 
    exit(); 
}

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once '../config/database.php';
    require_once '../models/User.php';
    try {
        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);
        $user->username = $_POST['username'];
        $user->password = $_POST['password'];
        $result = $user->login();
        if($result) {
            $_SESSION['user_id'] = $result['id'];
            $_SESSION['username'] = $result['username'];
            $_SESSION['nama_lengkap'] = $result['nama_lengkap'];
            $_SESSION['role'] = $result['role'];
            // Redirect to dashboard view
            header("Location: ../views/dashboard/index.php");
            exit();
        } else { 
            $error = "Username atau password salah!"; 
        }
    } catch(Exception $e) { 
        $error = "Error: " . $e->getMessage(); 
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Nexanet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card { 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.1); 
            padding: 40px; 
            width: 100%; 
            max-width: 400px;
            animation: fadeInUp 0.5s ease-out;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .login-header { 
            text-align: center; 
            margin-bottom: 30px; 
        }
        .login-header i { 
            font-size: 60px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
        }
        .login-header h3 {
            margin-top: 15px;
            color: #333;
        }
        .btn-login { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            border: none; 
            width: 100%; 
            padding: 12px; 
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        .form-control {
            padding: 12px;
            border-radius: 8px;
        }
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-network-wired"></i>
            <h3>Nexanet Management</h3>
            <p class="text-muted">Sistem Manajemen Jaringan Internet</p>
        </div>
        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" name="username" placeholder="Username" required autofocus>
                </div>
            </div>
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Login
            </button>
        </form>
        <hr class="my-4">
     
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>