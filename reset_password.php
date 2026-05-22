<?php
session_start();
require_once "db.php";

$error = '';
$success = false;
$valid_token = false;
$user_id = null;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $stmt = $conn->prepare("SELECT id, reset_token_expire FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $expire_time = strtotime($user['reset_token_expire']);
        $current_time = time();
        
        if ($current_time <= $expire_time) {
            $valid_token = true;
            $user_id = $user['id'];
        } else {
            $error = "This password reset link has expired. Please request a new one.";
        }
    } else {
        $error = "Invalid password reset link.";
    }
} else {
    $error = "No reset token provided.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expire = NULL WHERE id = ?");
        $update_stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($update_stmt->execute()) {
            $success = true;
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <style>
        :root { --utm-navy: #0B3B5E; --utm-red: #C1272D; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f0f4f8; }
        .blurry-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-image: url('css/images/utm.jpg'); background-size: cover; background-position: center; filter: blur(12px); transform: scale(1.1); z-index: 0; }
        .blurry-bg::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11, 59, 94, 0.65); }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(16px); border-radius: 30px; padding: 40px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); width: 100%; max-width: 450px; position: relative; z-index: 1; }
        .form-control { border-radius: 12px; padding: 12px; }
        .form-control:focus { box-shadow: 0 0 0 3px rgba(11, 59, 94, 0.1); border-color: var(--utm-navy); }
        .btn-custom { background: var(--utm-navy); color: white; border-radius: 50px; padding: 12px; font-weight: 600; width: 100%; transition: 0.3s; border: none; }
        .btn-custom:hover { background: #082c47; transform: translateY(-2px); color: white; }
    </style>
</head>
<body>
    <div class="blurry-bg"></div>
    <div class="glass-card">
        <div class="text-center mb-4">
            <h3 class="fw-bold" style="color: var(--utm-navy);">Reset Password</h3>
            <p class="text-muted small">Create a new, strong password.</p>
        </div>

        <?php if (!$valid_token && !$success): ?>
            <div class="alert alert-danger text-center">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <div class="text-center mt-4">
                <a href="forgot_password.php" class="btn btn-custom">Request New Link</a>
            </div>
        <?php elseif ($success): ?>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Password Updated!',
                        text: 'Your password has been changed successfully. You can now login.',
                        confirmButtonColor: '#0B3B5E',
                        allowOutsideClick: false
                    }).then((result) => {
                        window.location.href = 'login.php';
                    });
                });
            </script>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label text-navy fw-semibold">New Password</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Enter new password">
                </div>
                <div class="mb-4">
                    <label class="form-label text-navy fw-semibold">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6" placeholder="Confirm new password">
                </div>
                <button type="submit" class="btn btn-custom mb-3">
                    <i class="fas fa-save me-2"></i>Save New Password
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>