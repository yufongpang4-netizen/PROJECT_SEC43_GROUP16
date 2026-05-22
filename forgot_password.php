<?php
session_start();
require_once "db.php";

require 'vendor/Exception.php';
require 'vendor/PHPMailer.php';
require 'vendor/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$status = ''; // success or error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $status = 'error';
        $message = 'Please enter your email address.';
    } else {
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            $token = bin2hex(random_bytes(32));
            $expire_time = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expire = ? WHERE email = ?");
            $update_stmt->bind_param("sss", $token, $expire_time, $email);
            
            if ($update_stmt->execute()) {
                $reset_link = "http://localhost/finalproject/PROJECT_SEC43_GROUP16/reset_password.php?token=" . $token;
                
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'utmspaceclaim.demo@gmail.com'; 
                    $mail->Password   = 'kjvsghjthnholvyi'; 
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('utmspaceclaim.demo@gmail.com', 'UTMSPACE Admin');
                    $mail->addAddress($email, $user['name']);

                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request - UTMSPACE';
                    $mail->Body    = "
                        <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                            <h2 style='color: #0B3B5E;'>UTMSPACE Claim System</h2>
                            <p>Hi {$user['name']},</p>
                            <p>We received a request to reset your password. Click the button below to set a new password:</p>
                            <p>
                                <a href='{$reset_link}' style='background-color: #C1272D; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Reset Password</a>
                            </p>
                            <p style='color: #666; font-size: 12px;'>This link will expire in 15 minutes.<br>If you did not request this, please ignore this email.</p>
                        </div>
                    ";

                    $mail->send();
                    $status = 'success';
                    $message = 'A password reset link has been sent to your email!';
                } catch (Exception $e) {
                    $status = 'error';
                    $message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
            } else {
                $status = 'error';
                $message = "Database error. Could not generate token.";
            }
        } else {
            $status = 'error';
            $message = "No account found with that email address.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <style>
        :root { --utm-navy: #0B3B5E; --utm-red: #C1272D; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f0f4f8; }
        .blurry-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-image: url('css/images/utm.jpg'); background-size: cover; background-position: center; filter: blur(12px); transform: scale(1.1); z-index: 0; }
        .blurry-bg::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11, 59, 94, 0.65); }
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(16px); border-radius: 30px; padding: 40px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); width: 100%; max-width: 450px; position: relative; z-index: 1; }
        .form-control { border-radius: 12px; padding: 12px; background: rgba(255,255,255,0.8); }
        .form-control:focus { box-shadow: 0 0 0 3px rgba(193, 39, 45, 0.1); border-color: var(--utm-red); }
        .btn-custom { background: var(--utm-navy); color: white; border-radius: 50px; padding: 12px; font-weight: 600; width: 100%; transition: 0.3s; border: none; }
        .btn-custom:hover { background: #082c47; transform: translateY(-2px); color: white; }
    </style>
</head>
<body>
    <div class="blurry-bg"></div>
    <div class="glass-card animate__animated animate__fadeInUp">
        <div class="text-center mb-4">
            <i class="fas fa-key fa-3x mb-3" style="color: var(--utm-red);"></i>
            <h3 class="fw-bold" style="color: var(--utm-navy);">Forgot Password</h3>
            <p class="text-muted small">Enter your email to receive a password reset link.</p>
        </div>

        <form method="POST" id="forgotForm">
            <div class="mb-4">
                <label class="form-label text-navy fw-semibold"><i class="fas fa-envelope me-2 opacity-75"></i>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="name@utmspace.edu.my" required>
            </div>
            <button type="submit" class="btn btn-custom mb-3" id="submitBtn">
                <i class="fas fa-paper-plane me-2"></i>Send Reset Link
            </button>
            <div class="text-center">
                <a href="login.php" class="text-decoration-none" style="color: var(--utm-gray);"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('forgotForm').addEventListener('submit', function() {
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            document.getElementById('submitBtn').disabled = true;
        });

        <?php if($status === 'success'): ?>
            Swal.fire({
                icon: 'success', title: 'Email Sent!', text: '<?php echo addslashes($message); ?>',
                confirmButtonColor: '#0B3B5E'
            }).then(() => { window.location.href = 'login.php'; });
        <?php elseif($status === 'error'): ?>
            Swal.fire({
                icon: 'error', title: 'Oops...', text: '<?php echo addslashes($message); ?>',
                confirmButtonColor: '#C1272D'
            });
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Reset Link';
            document.getElementById('submitBtn').disabled = false;
        <?php endif; ?>
    </script>
</body>
</html>