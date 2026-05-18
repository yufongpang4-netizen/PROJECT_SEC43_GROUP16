<?php
session_start();
require_once "db.php";

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $staff_id = trim($_POST['staff_id']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($email) || empty($staff_id) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = "Password does not meet security requirements!";
    } else {
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND staff_id = ?");
        $stmt->bind_param("ss", $email, $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user['id']);
            
            if ($update_stmt->execute()) {
                $success = "Password has been successfully reset for " . $user['name'] . "!";
            } else {
                $error = "Failed to update password. Please try again.";
            }
            $update_stmt->close();
        } else {
            $error = "Authentication failed. Email and Staff ID do not match our records.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
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
        
        .blurry-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('css/images/utm.jpg');
            background-size: cover; background-position: center;
            filter: blur(12px); transform: scale(1.1); z-index: 0;
        }
        .blurry-bg::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(11, 59, 94, 0.65);
        }
        .content-wrapper {
            position: relative; z-index: 1; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 40px 20px;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border-radius: 30px; border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            width: 100%; max-width: 500px;
        }

        /* Logo Styles - SAME AS INDEX */
        .utm-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .utmspace-logo-img {
            max-width: 150px;
            width: 100%;
            height: auto;
            margin-bottom: 10px;
            background: transparent;
        }
        
        .logo-divider {
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, var(--utm-red), #ff4b52);
            margin: 10px auto 0;
            border-radius: 3px;
        }
        
        .form-control {
            border-radius: 15px; padding: 12px 18px;
            border: 1px solid rgba(11, 59, 94, 0.1);
            background: rgba(255, 255, 255, 0.5); transition: all 0.3s;
        }
        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(193, 39, 45, 0.1);
            border-color: var(--utm-red); background: white;
        }

        .btn-primary-custom {
            background: var(--utm-navy); color: white; border-radius: 50px;
            padding: 12px; font-weight: 600; transition: all 0.3s; border: none;
            box-shadow: 0 4px 15px rgba(11, 59, 94, 0.2);
        }
        .btn-primary-custom:hover {
            background: var(--utm-red); transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(193, 39, 45, 0.4); color: white;
        }
        
        .btn-outline-custom {
            border: 2px solid var(--utm-navy); color: var(--utm-navy);
            border-radius: 50px; padding: 10px; font-weight: 600;
            transition: all 0.3s; text-decoration: none;
            display: block; text-align: center;
        }
        .btn-outline-custom:hover { background: var(--utm-navy); color: white; }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        
        .info-badge {
            background: rgba(11, 59, 94, 0.08);
            border: 1px solid rgba(11, 59, 94, 0.2);
            color: var(--utm-navy);
            padding: 8px 25px; border-radius: 30px;
            display: inline-block; font-size: 0.85rem; font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="blurry-bg"></div>
    <div class="content-wrapper">
        <div class="container w-100 d-flex justify-content-center">
            <div class="glass-card p-4 p-md-5 fade-in-up">
                
                <!-- Logo - SAME AS INDEX -->
                <div class="utm-logo">
                    <img src="css/images/utmspace logo.png" alt="UTMSPACE Logo" class="utmspace-logo-img" 
                         style="background: transparent;"
                         onerror="this.src='css/images/utm_space1.jpg'">
                    <div class="logo-divider"></div>
                </div>

                <div class="text-center mb-4">
                    <h3 class="fw-bold" style="color: var(--utm-navy);">Reset Password</h3>
                    <div class="info-badge mt-2">
                        <i class="fas fa-shield-alt me-1"></i> Verify identity to continue
                    </div>
                </div>

                <form method="POST" id="resetForm">
                    <div class="mb-3">
                        <label class="form-label text-navy ms-1 fw-bold"><i class="fas fa-envelope me-2 opacity-75"></i>Email Address *</label>
                        <input type="email" name="email" class="form-control" placeholder="Registered Email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-navy ms-1 fw-bold"><i class="fas fa-id-card me-2 opacity-75"></i>Staff ID *</label>
                        <input type="text" name="staff_id" class="form-control" placeholder="Your Staff ID" required value="<?php echo htmlspecialchars($_POST['staff_id'] ?? ''); ?>">
                        <small class="text-muted ms-2">Used for security verification.</small>
                    </div>
                    
                    <hr style="border-color: rgba(11, 59, 94, 0.1); border-width: 2px;">

                    <div class="mb-3 mt-3">
                        <label class="form-label text-navy ms-1 fw-bold"><i class="fas fa-key me-2 opacity-75"></i>New Password *</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                        <ul class="small text-muted mt-2 list-unstyled ms-1" id="reqs">
                            <li id="l">✗ Min 8 characters</li>
                            <li id="u">✗ At least 1 Uppercase & 1 Number</li>
                        </ul>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-navy ms-1 fw-bold"><i class="fas fa-check-double me-2 opacity-75"></i>Confirm New Password *</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required>
                        <small id="match-msg" class="ms-1 fw-bold" style="display:none;"></small>
                    </div>

                    <button type="submit" class="btn btn-primary-custom w-100 mb-4">
                        <i class="fas fa-unlock-alt me-2"></i>Reset Password
                    </button>
                </form>

                <div class="text-center">
                    <p class="text-muted mb-3">Remember your password?</p>
                    <a href="login.php" class="btn btn-outline-custom w-100">
                        <i class="fas fa-arrow-left me-2"></i>Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const p = document.getElementById('password');
        const cp = document.getElementById('confirm_password');
        const fl = document.getElementById('l'), fu = document.getElementById('u');
        const matchMsg = document.getElementById('match-msg');
        const form = document.getElementById('resetForm');
        let isPasswordValid = false;
        let isMatch = false;

        p.addEventListener('input', () => {
            const v = p.value;
            const okL = v.length >= 8;
            const okU = /[A-Z]/.test(v) && /[0-9]/.test(v);
            fl.innerHTML = (okL ? '✓' : '✗') + ' Min 8 characters';
            fl.style.color = okL ? '#28a745' : '#6c757d';
            fu.innerHTML = (okU ? '✓' : '✗') + ' At least 1 Uppercase & 1 Number';
            fu.style.color = okU ? '#28a745' : '#6c757d';
            
            isPasswordValid = okL && okU;
            checkMatch();
        });

        cp.addEventListener('input', checkMatch);

        function checkMatch() {
            if (cp.value.length > 0) {
                matchMsg.style.display = 'block';
                isMatch = (p.value === cp.value);
                if (isMatch) {
                    matchMsg.innerHTML = '✓ Passwords match';
                    matchMsg.style.color = '#28a745';
                } else {
                    matchMsg.innerHTML = '✗ Passwords do not match';
                    matchMsg.style.color = '#C1272D';
                }
            } else {
                matchMsg.style.display = 'none';
                isMatch = false;
            }
        }

        form.addEventListener('submit', function(e) {
            if (!isPasswordValid) {
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'Weak Password', text: 'Please ensure your new password meets all security requirements.', confirmButtonColor: '#C1272D', background: 'rgba(255, 255, 255, 0.95)', backdrop: `rgba(11, 59, 94, 0.4)` });
            } else if (!isMatch) {
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'Mismatch', text: 'The passwords you entered do not match.', confirmButtonColor: '#C1272D', background: 'rgba(255, 255, 255, 0.95)', backdrop: `rgba(11, 59, 94, 0.4)` });
            }
        });

        <?php if($success): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo addslashes($success); ?>',
                confirmButtonText: '<i class="fas fa-sign-in-alt me-1"></i> Login Now',
                confirmButtonColor: '#0B3B5E',
                background: 'rgba(255, 255, 255, 0.95)',
                backdrop: `rgba(11, 59, 94, 0.4)`
            }).then(() => {
                window.location.href = 'login.php';
            });
        <?php elseif($error): ?>
            Swal.fire({
                icon: 'error',
                title: 'Reset Failed',
                text: '<?php echo addslashes($error); ?>',
                confirmButtonColor: '#C1272D',
                background: 'rgba(255, 255, 255, 0.95)',
                backdrop: `rgba(11, 59, 94, 0.4)`,
                showClass: { popup: 'animate__animated animate__shakeX' }
            });
        <?php endif; ?>
    </script>
</body>
</html>