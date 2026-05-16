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
        :root {
            --utm-navy: #0B3B5E;
            --utm-red: #C1272D;
            --utm-gray: #475569;
            --utm-light: #F8FAFC;
            --utm-dark: #082c47;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        .login-page { position: relative; min-height: 100vh; }
        
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
        
        .blurry-bg::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(11, 59, 94, 0.75);
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
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .utm-logo {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(193, 39, 45, 0.15);
        }
        
        .utm-logo-img {
            max-width: 150px;
            height: auto;
            margin-bottom: 8px;
        }
        
        .logo-divider {
            width: 50px;
            height: 3px;
            background: var(--utm-red);
            margin: 10px auto 0;
            border-radius: 3px;
        }
        
        .btn-login {
            background: var(--utm-navy);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: var(--utm-red);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(193, 39, 45, 0.4);
            color: white;
        }
        
        .btn-register {
            border: 2px solid var(--utm-navy);
            background: transparent;
            color: var(--utm-navy);
            border-radius: 50px;
            padding: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        
        .btn-register:hover {
            background: var(--utm-navy);
            color: white;
            transform: translateY(-2px);
        }
        
        .form-control {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 12px 15px;
        }
        
        .form-control:focus {
            border-color: var(--utm-red);
            box-shadow: 0 0 0 3px rgba(193, 39, 45, 0.1);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--utm-navy);
            margin-bottom: 8px;
        }
        
        .demo-card {
            background: rgba(193, 39, 45, 0.05);
            border-left: 3px solid var(--utm-red);
            border-radius: 12px;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in-up { animation: fadeInUp 0.6s ease-out; }
        
        .accent-red { color: var(--utm-red); }
    </style>
</head>
<body class="login-page">
    <div class="blurry-bg"></div>
    
    <div class="content-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="glass-card p-4 p-md-5 fade-in-up">
                        <!-- UTMSPACE Logo Image -->
                        <div class="utm-logo">
                            <img src="css/images/utm-logo.png" alt="UTMSPACE Logo" class="utm-logo-img" 
                                 onerror="this.src='css/images/utm space1.jpg'">
                            <div class="logo-divider"></div>
                        </div>
                        
                        <div class="text-center mb-4">
                            <i class="fas fa-lock" style="font-size: 45px; color: var(--utm-red);"></i>
                            <h3 class="mt-3" style="color: var(--utm-navy);">Welcome Back!</h3>
                            <p style="color: #64748b;">Login to your account</p>
                        </div>
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-envelope me-1" style="color: var(--utm-red);"></i>Email Address
                                </label>
                                <input type="email" name="email" class="form-control" required placeholder="staff@utmspace.com">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-key me-1" style="color: var(--utm-red);"></i>Password
                                </label>
                                <input type="password" name="password" class="form-control" required placeholder="••••••">
                            </div>
                            
                            <button type="submit" class="btn btn-login w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-3" style="color: var(--utm-navy);">Don't have an account?</p>
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
                        
                        <div class="mt-4 p-3 demo-card">
                            <small>
                                <i class="fas fa-info-circle me-1" style="color: var(--utm-red);"></i>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>