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
    <title>Login - UTMSpace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body style="background: linear-gradient(135deg, #0B132B 0%, #1C2541 50%, #3A506B 100%); min-height: 100vh;">
    <div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="row justify-content-center w-100">
            <div class="col-md-5">
                <div class="card border-0 shadow-lg fade-in" style="border-radius: 20px;">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-lock" style="font-size: 50px; color: #5BC0BE;"></i>
                            <h2 class="mt-3" style="color: #0B132B;">Welcome Back!</h2>
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
                                <label class="form-label fw-bold" style="color: #0B132B;">
                                    <i class="fas fa-envelope me-1" style="color: #5BC0BE;"></i>Email Address
                                </label>
                                <input type="email" name="email" class="form-control" required placeholder="staff@utmspace.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold" style="color: #0B132B;">
                                    <i class="fas fa-key me-1" style="color: #5BC0BE;"></i>Password
                                </label>
                                <input type="password" name="password" class="form-control" required placeholder="••••••">
                            </div>
                            <button type="submit" class="btn w-100" style="background: #5BC0BE; color: #0B132B; padding: 12px; border-radius: 10px; font-weight: bold;">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-2" style="color: #3A506B;">Don't have an account?</p>
                            <a href="register.php?role=staff" class="btn btn-outline w-100 mb-2" style="border-color: #3A506B; color: #3A506B; border-radius: 10px;">
                                <i class="fas fa-user-plus me-2"></i>Register as Staff
                            </a>

                            <a href="register.php?role=finance" class="btn btn-outline w-100 mb-2" style="border-color: #3A506B; color: #3A506B; border-radius: 10px;">
                                <i class="fas fa-user-tie me-2"></i>Register as Finance
                            </a>

                            <a href="register.php?role=admin" class="btn btn-outline w-100" style="border-color: #3A506B; color: #3A506B; border-radius: 10px;">
                                <i class="fas fa-user-shield me-2"></i>Register as Admin
                            </a>
                        </div>
                        
                        <div class="mt-4 p-3 rounded" style="background: #f8f9fa;">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Demo Credentials:</strong><br>
                                Staff: staff@utmspace.com / staff123<br>
                                Finance: finance@utmspace.com / finance123<br>
                                Admin: admin@utmspace.com / admin123
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