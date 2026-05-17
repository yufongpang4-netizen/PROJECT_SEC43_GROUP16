<?php
session_start();
require_once "db.php";

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$success = ''; $error = '';
$requested_role = $_GET['role'] ?? 'staff';
if(!in_array($requested_role, ['staff', 'finance', 'admin'])) $requested_role = 'staff';

$bg_image = match($requested_role) {
    'staff' => 'css/images/staff.jpeg',
    'finance' => 'css/images/finance.jpg',
    'admin' => 'css/images/admin.jpg',
    default => 'css/images/utm.jpg'
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $staff_id = trim($_POST['staff_id']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    
    if ($role == 'finance') { $department = 'Finance'; } 
    elseif ($role == 'admin') { $department = NULL; } 
    else { $department = trim($_POST['department'] ?? ''); }

    if (empty($name) || empty($staff_id) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = "Password does not meet security requirements!";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR staff_id = ?");
        $check->bind_param("ss", $email, $staff_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Email or Staff ID already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, staff_id, email, department, password, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
            $stmt->bind_param("sssssss", $name, $staff_id, $email, $department, $hashed_password, $phone, $role);
            if ($stmt->execute()) { $success = "Registration successful!"; } 
            else { $error = "Registration failed."; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --utm-navy: #0B3B5E;
            --utm-red: #C1272D;
            --utm-gray: #475569;
            --utm-light: #F8FAFC;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; }
        
        .blurry-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('<?php echo $bg_image; ?>');
            background-size: cover; background-position: center;
            filter: blur(12px); transform: scale(1.1); z-index: 0;
        }
        
        .blurry-bg::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(11, 59, 94, 0.7);
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
            max-width: 700px;
        }

        .utm-logo-img { max-width: 150px; height: auto; margin-bottom: 10px; }
        .logo-divider { width: 50px; height: 4px; background: var(--utm-red); margin: 0 auto; border-radius: 4px; }
        
        .form-control, .form-select {
            border-radius: 12px;
            padding: 10px 15px;
            border: 1px solid rgba(11, 59, 94, 0.1);
            background: rgba(255, 255, 255, 0.5);
        }

        .form-control:focus { border-color: var(--utm-red); box-shadow: 0 0 0 4px rgba(193, 39, 45, 0.1); }

        .btn-primary-custom {
            background: var(--utm-navy); color: white; border-radius: 50px;
            padding: 12px; font-weight: 600; border: none; transition: all 0.3s;
        }
        .btn-primary-custom:hover { background: var(--utm-red); transform: translateY(-2px); }

        .role-badge {
            background: rgba(193, 39, 45, 0.1);
            color: var(--utm-navy);
            padding: 6px 18px; border-radius: 50px;
            font-weight: 600; font-size: 0.85rem; display: inline-block;
        }

        .role-switch-btn {
            border: 2px solid var(--utm-navy); background: transparent;
            color: var(--utm-navy); border-radius: 50px; padding: 6px 15px;
            font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: all 0.3s;
        }
        .role-switch-btn.active { background: var(--utm-navy); color: white; }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
    </style>
</head>
<body class="register-page">
    <div class="blurry-bg"></div>
    <div class="content-wrapper">
        <div class="glass-card p-4 p-md-5 fade-in-up">
            <div class="text-center mb-4">
                <img src="css/images/utm-logo.png" class="utm-logo-img" onerror="this.src='css/images/utm_space1.jpg'">
                <div class="logo-divider"></div>
            </div>

            <div class="text-center mb-4">
                <h3 class="fw-bold" style="color: var(--utm-navy);"><?php echo ucfirst($requested_role); ?> Registration</h3>
                <div class="role-badge mb-2"><i class="fas fa-id-badge me-2"></i>UTMSPACE Official Portal</div>
            </div>

            <?php if($success): ?>
                <div class="alert alert-success border-0 rounded-4 text-center">
                    <i class="fas fa-check-circle me-2"></i>Registered Successfully! <a href="login.php" class="fw-bold">Login now →</a>
                </div>
            <?php elseif($error): ?>
                <div class="alert alert-danger border-0 rounded-4">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="regForm">
                <input type="hidden" name="role" value="<?php echo $requested_role; ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label ms-1">Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label ms-1">Staff ID</label>
                        <input type="text" name="staff_id" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label ms-1">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label ms-1">Phone</label>
                        <input type="tel" name="phone" class="form-control" placeholder="0123456789">
                    </div>
                    
                    <?php if($requested_role == 'staff'): ?>
                    <div class="col-12">
                        <label class="form-label ms-1">Department</label>
                        <select name="department" class="form-select" required>
                            <option value="">Choose your department...</option>
                            <option>Information Technology</option>
                            <option>Human Resources</option>
                            <option>Marketing</option>
                            <option>Finance</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <label class="form-label ms-1">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                        <ul class="small text-muted mt-2 list-unstyled ms-1" id="reqs">
                            <li id="l">✗ Min 8 characters</li>
                            <li id="u">✗ At least 1 Uppercase & 1 Number</li>
                        </ul>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary-custom w-100 mt-4 shadow-sm">
                    <i class="fas fa-user-check me-2"></i>Create Account
                </button>
            </form>

            <div class="mt-4 pt-3 border-top text-center">
                <p class="small text-muted mb-3">Registering for a different role?</p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="?role=staff" class="role-switch-btn <?php echo $requested_role == 'staff' ? 'active' : ''; ?>">Staff</a>
                    <a href="?role=finance" class="role-switch-btn <?php echo $requested_role == 'finance' ? 'active' : ''; ?>">Finance</a>
                    <a href="?role=admin" class="role-switch-btn <?php echo $requested_role == 'admin' ? 'active' : ''; ?>">Admin</a>
                </div>
                <p class="mt-4 mb-0">Already a member? <a href="login.php" class="fw-bold text-navy">Log In</a></p>
            </div>
        </div>
    </div>

    <script>
        const p = document.getElementById('password');
        const fl = document.getElementById('l'), fu = document.getElementById('u');
        p.addEventListener('input', () => {
            const v = p.value;
            const okL = v.length >= 8;
            const okU = /[A-Z]/.test(v) && /[0-9]/.test(v);
            fl.innerHTML = (okL ? '✓' : '✗') + ' Min 8 characters';
            fl.style.color = okL ? '#28a745' : '#6c757d';
            fu.innerHTML = (okU ? '✓' : '✗') + ' At least 1 Uppercase & 1 Number';
            fu.style.color = okU ? '#28a745' : '#6c757d';
        });
    </script>
</body>
</html>