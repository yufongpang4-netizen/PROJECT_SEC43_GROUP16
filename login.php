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
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; }
        
        .login-page { position: relative; min-height: 100vh; }
        
        .blurry-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('css/images/utm.jpg');
            background-size: cover;
            background-position: center;
            filter: blur(12px);
            transform: scale(1.1);
            z-index: 0;
        }
        
        .blurry-bg::after {
            content: '';
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(11, 59, 94, 0.65);
        }
        
        .content-wrapper {
            position: relative; z-index: 1; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 40px 20px;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
        }

        .utm-logo-img { max-width: 180px; height: auto; margin-bottom: 15px; }
        .logo-divider { width: 60px; height: 4px; background: var(--utm-red); margin: 0 auto; border-radius: 4px; }
        
        .form-control {
            border-radius: 15px;
            padding: 12px 18px;
            border: 1px solid rgba(11, 59, 94, 0.1);
            background: rgba(255, 255, 255, 0.5);
            transition: all 0.3s;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(193, 39, 45, 0.1);
            border-color: var(--utm-red);
            background: white;
        }

        .btn-primary-custom {
            background: var(--utm-navy);
            color: white;
            border-radius: 50px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            box-shadow: 0 4px 15px rgba(11, 59, 94, 0.2);
        }
        
        .btn-primary-custom:hover {
            background: var(--utm-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(11, 59, 94, 0.3);
            color: white;
        }

        .btn-outline-custom {
            border: 2px solid var(--utm-navy);
            color: var(--utm-navy);
            border-radius: 50px;
            padding: 10px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: block; text-align: center;
        }

        .btn-outline-custom:hover {
            background: var(--utm-navy);
            color: white;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
    </style>
</head>
<body class="login-page">
    <div class="blurry-bg"></div>
    <div class="content-wrapper">
        <div class="glass-card p-4 p-md-5 fade-in-up">
            <div class="text-center mb-4">
                <img src="css/images/utm-logo.png" alt="UTMSPACE" class="utm-logo-img" onerror="this.src='css/images/utm_space1.jpg'">
                <div class="logo-divider"></div>
            </div>

            <div class="text-center mb-4">
                <h3 class="fw-bold" style="color: var(--utm-navy);">Welcome Back</h3>
                <p class="text-muted">Enter your credentials to access your account</p>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger border-0 rounded-4 mb-4">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label text-navy ms-1"><i class="fas fa-envelope me-2 opacity-75"></i>Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="name@utmspace.edu.my" required>
                </div>
                <div class="mb-4">
                    <label class="form-label text-navy ms-1"><i class="fas fa-lock me-2 opacity-75"></i>Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary-custom w-100 mb-4">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>

            <div class="text-center">
                <p class="text-muted mb-3">Don't have an account yet?</p>
                <a href="register.php" class="btn btn-outline-custom w-100">
                    <i class="fas fa-user-plus me-2"></i>Create New Account
                </a>
            </div>
        </div>
    </div>
</body>
</html>