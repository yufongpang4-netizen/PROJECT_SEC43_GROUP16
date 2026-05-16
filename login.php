<?php
session_start();
require_once "db.php";

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter email and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'Inactive') {
                    $error = "Your account has been deactivated. Please contact Admin.";
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['email'];

                    if ($user['role'] === 'staff') {
                        header("Location: staff/dashboard_Staff.php");
                    } elseif ($user['role'] === 'finance') {
                        header("Location: finance/dashboard_Finance.php");
                    } elseif ($user['role'] === 'admin') {
                        header("Location: admin/dashboard_Admin.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit();
                }
            } else {
                $error = "Invalid email or password!";
            }
        } else {
            $error = "Invalid email or password!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Blurry Background */
        .login-page {
            position: relative;
            min-height: 100vh;
        }
        
        .blurry-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('css/images/utm space.jpeg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(8px);
            transform: scale(1.05);
            z-index: 0;
        }
        
        .blurry-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(11, 19, 43, 0.65);
        }
        
        .content-wrapper {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #5BC0BE 0%, #3a9e9c 100%);
            color: #0B132B;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(91, 192, 190, 0.4);
            color: #0B132B;
        }
        
        .btn-register {
            border: 2px solid #3A506B;
            background: transparent;
            color: #3A506B;
            border-radius: 10px;
            padding: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        
        .btn-register:hover {
            background: #3A506B;
            color: white;
            transform: translateY(-2px);
        }
        
        .utm-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .utm-logo h1 {
            font-size: 32px;
            font-weight: 800;
            color: #0B132B;
            letter-spacing: 3px;
            margin-bottom: 5px;
        }
        
        .utm-logo .estd {
            color: #5BC0BE;
            font-size: 10px;
            letter-spacing: 4px;
            font-weight: 500;
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
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .demo-card {
            background: rgba(91, 192, 190, 0.1);
            border-left: 3px solid #5BC0BE;
        }
        
        .form-control {
            border-radius: 10px;
            border: 1px solid #dee2e6;
            padding: 12px 15px;
        }
        
        .form-control:focus {
            border-color: #5BC0BE;
            box-shadow: 0 0 0 3px rgba(91, 192, 190, 0.2);
        }
        
        .form-label {
            font-weight: 600;
            color: #0B132B;
            margin-bottom: 8px;
        }
    </style>
</head>
<body class="login-page">
    <!-- Blurry Background Image -->
    <div class="blurry-bg"></div>
    
    <div class="content-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="glass-card fade-in-up">
                        <div class="card-body p-5">
                            <div class="utm-logo">
                                <h1>UTMSPACE</h1>
                                <p class="estd">ESTD 1993</p>
                            </div>
                            
                            <div class="text-center mb-4">
                                <i class="fas fa-lock" style="font-size: 45px; color: #5BC0BE;"></i>
                                <h3 class="mt-3" style="color: #0B132B;">Welcome Back!</h3>
                                <p style="color: #3A506B;">Login to your account</p>
                            </div>
                            
                            <?php if($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-envelope me-1" style="color: #5BC0BE;"></i>Email Address
                                    </label>
                                    <input type="email" name="email" class="form-control" required placeholder="staff@utmspace.com">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-key me-1" style="color: #5BC0BE;"></i>Password
                                    </label>
                                    <input type="password" name="password" class="form-control" required placeholder="••••••">
                                </div>
                                
                                <button type="submit" class="btn btn-login w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <div class="text-center">
                                <p class="mb-3" style="color: #3A506B;">Don't have an account?</p>
                                <div class="d-flex flex-column gap-2">
                                    <a href="register.php?role=staff" class="btn-register">
                                        <i class="fas fa-user-plus me-2"></i>Register as Staff
                                    </a>
                                    <a href="register.php?role=finance" class="btn-register">
                                        <i class="fas fa-user-tie me-2"></i>Register as Finance
                                    </a>
                                    <a href="register.php?role=admin" class="btn-register">
                                        <i class="fas fa-user-shield me-2"></i>Register as Admin
                                    </a>
                                </div>
                            </div>
                            
                            <div class="mt-4 p-3 rounded demo-card">
                                <small>
                                    <i class="fas fa-info-circle me-1" style="color: #5BC0BE;"></i>
                                    <strong>Demo Credentials:</strong><br>
                                    <i class="fas fa-user me-1"></i> Staff: staff@utmspace.com / staff123<br>
                                    <i class="fas fa-user-tie me-1"></i> Finance: finance@utmspace.com / finance123<br>
                                    <i class="fas fa-user-shield me-1"></i> Admin: admin@utmspace.com / admin123
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>