<?php
session_start();
require_once "db.php";

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$success = '';
$error = '';

$requested_role = isset($_GET['role']) ? $_GET['role'] : 'staff';
if(!in_array($requested_role, ['staff', 'finance', 'admin'])) {
    $requested_role = 'staff';
}

// Set background image based on role
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
    
    // ========== SET DEPARTMENT BASED ON ROLE ==========
    if ($role == 'finance') {
        $department = 'Finance';
    } elseif ($role == 'admin') {
        $department = NULL;
    } else {
        $department = trim($_POST['department'] ?? '');
    }

    // ========== VALIDATION RULES ==========
    if (empty($name) || empty($staff_id) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!preg_match('/^[a-zA-Z\s\'-]{2,50}$/', $name)) {
        $error = "Name must be 2-50 characters and can only contain letters, spaces, hyphens, and apostrophes.";
    } elseif (!preg_match('/^[a-zA-Z0-9]{5,15}$/', $staff_id)) {
        $error = "Staff ID must be 5-15 characters and can only contain letters and numbers.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } elseif ($role == 'staff' && empty($department)) {
        $error = "Please select a department for Staff account.";
    } elseif (!empty($phone) && !preg_match('/^(\+?6?01)[0-9]{8,9}$/', $phone)) {
        $error = "Please enter a valid phone number! Example: 0123456789";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long!";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least 1 uppercase letter!";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least 1 lowercase letter!";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least 1 number!";
    } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?]/', $password)) {
        $error = "Password must contain at least 1 special character!";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR staff_id = ?");
        $check->bind_param("ss", $email, $staff_id);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            $error = "Email or Staff ID already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, staff_id, email, department, password, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
            $stmt->bind_param("sssssss", $name, $staff_id, $email, $department, $hashed_password, $phone, $role);
            
            if ($stmt->execute()) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register as <?php echo ucfirst($requested_role); ?> - UTMSPACE</title>
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
        
        .register-page { position: relative; min-height: 100vh; }
        
        .blurry-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('<?php echo $bg_image; ?>');
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
            background: rgba(11, 59, 94, 0.7);
        }
        
        .content-wrapper {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        /* Logo Styles */
        .utm-logo {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(193, 39, 45, 0.15);
        }
        
        .utm-logo-img {
            max-width: 160px;
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
        
        .btn-register {
            background: var(--utm-navy);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
            background: var(--utm-red);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(193, 39, 45, 0.4);
            color: white;
        }
        
        .role-switch-btn {
            border: 2px solid var(--utm-navy);
            background: transparent;
            color: var(--utm-navy);
            border-radius: 50px;
            padding: 8px 18px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .role-switch-btn:hover, .role-switch-btn.active {
            background: var(--utm-navy);
            color: white;
            transform: translateY(-2px);
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 12px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--utm-red);
            box-shadow: 0 0 0 3px rgba(193, 39, 45, 0.1);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--utm-navy);
            margin-bottom: 8px;
        }
        
        .role-badge {
            font-size: 0.75rem;
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-block;
        }
        .role-staff { background: #3b82f6; color: white; }
        .role-finance { background: #10b981; color: white; }
        .role-admin { background: var(--utm-red); color: white; }
        
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
            transition: all 0.3s;
            display: none;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in-up { animation: fadeInUp 0.6s ease-out; }
    </style>
</head>
<body class="register-page">
    <div class="blurry-bg"></div>
    
    <div class="content-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-7">
                    <div class="glass-card fade-in-up">
                        <div class="card-body p-4 p-md-5">
                            <!-- UTMSPACE Logo Image -->
                            <div class="utm-logo">
                                <img src="css/images/utm-logo.png" alt="UTMSPACE Logo" class="utm-logo-img" 
                                     onerror="this.src='css/images/utm space1.jpg'">
                                <div class="logo-divider"></div>
                            </div>
                            
                            <div class="text-center mb-4">
                                <i class="fas <?php echo $requested_role == 'admin' ? 'fa-user-shield' : ($requested_role == 'finance' ? 'fa-user-tie' : 'fa-user-plus'); ?>" style="font-size: 45px; color: var(--utm-red);"></i>
                                <h3 class="mt-2" style="color: var(--utm-navy);"><?php echo ucfirst($requested_role); ?> Registration</h3>
                                <p style="color: var(--utm-gray);">Create your account with valid information</p>
                                
                                <?php if($requested_role == 'finance'): ?>
                                    <span class="role-badge role-finance"><i class="fas fa-building"></i> Department: Finance (Auto-assigned)</span>
                                <?php elseif($requested_role == 'admin'): ?>
                                    <span class="role-badge role-admin"><i class="fas fa-user-shield"></i> No Department (Admin)</span>
                                <?php else: ?>
                                    <span class="role-badge role-staff"><i class="fas fa-users"></i> Select your department</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if($success): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                    <a href="login.php" class="fw-bold text-decoration-none d-block mt-2">Login here →</a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" id="registrationForm">
                                <input type="hidden" name="role" value="<?php echo htmlspecialchars($requested_role); ?>">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fas fa-user me-1" style="color: var(--utm-red);"></i>Full Name *</label>
                                        <input type="text" name="name" class="form-control" required>
                                        <small class="text-muted">Letters, spaces, hyphens (2-50 chars)</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fas fa-id-card me-1" style="color: var(--utm-red);"></i>Staff ID *</label>
                                        <input type="text" name="staff_id" class="form-control" required>
                                        <small class="text-muted">5-15 characters, letters & numbers only</small>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label"><i class="fas fa-envelope me-1" style="color: var(--utm-red);"></i>Email *</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                    
                                    <?php if($requested_role == 'staff'): ?>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label"><i class="fas fa-building me-1" style="color: var(--utm-red);"></i>Department *</label>
                                        <select name="department" class="form-select" required>
                                            <option value="">Select Department</option>
                                            <option value="Information Technology">Information Technology</option>
                                            <option value="Human Resources">Human Resources</option>
                                            <option value="Marketing">Marketing</option>
                                            <option value="Operations">Operations</option>
                                            <option value="Sales">Sales</option>
                                            <option value="Customer Service">Customer Service</option>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if($requested_role == 'finance'): ?>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label"><i class="fas fa-building me-1" style="color: var(--utm-red);"></i>Department</label>
                                        <input type="text" class="form-control" value="Finance" disabled style="background: #e9ecef;">
                                        <small class="text-success">Finance department is automatically assigned</small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if($requested_role == 'admin'): ?>
                                    <div class="col-md-12 mb-3">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> Admin accounts have no department assignment.
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label"><i class="fas fa-phone me-1" style="color: var(--utm-red);"></i>Phone Number</label>
                                        <input type="tel" name="phone" class="form-control" placeholder="0123456789">
                                        <small class="text-muted">Optional - Malaysian format</small>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label"><i class="fas fa-key me-1" style="color: var(--utm-red);"></i>Password *</label>
                                        <input type="password" name="password" class="form-control" id="password" required>
                                        <div class="password-strength" id="passwordStrength"></div>
                                        <small class="text-muted">Min 8 chars with uppercase, lowercase, number & special character</small>
                                        <ul class="small mt-1" id="passwordRequirements">
                                            <li id="req-length" class="text-muted">✗ At least 8 characters</li>
                                            <li id="req-upper" class="text-muted">✗ At least 1 uppercase letter</li>
                                            <li id="req-lower" class="text-muted">✗ At least 1 lowercase letter</li>
                                            <li id="req-number" class="text-muted">✗ At least 1 number</li>
                                            <li id="req-special" class="text-muted">✗ At least 1 special character</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-register w-100">
                                    <i class="fas fa-check-circle me-2"></i>Complete Registration
                                </button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <div class="text-center">
                                <p class="mb-3">Already have an account? <a href="login.php" style="color: var(--utm-red); font-weight: bold;">Login here</a></p>
                            </div>
                            
                            <div class="mt-3 text-center">
                                <p class="mb-2"><small class="text-muted">Register as different role?</small></p>
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    <a href="?role=staff" class="role-switch-btn <?php echo $requested_role == 'staff' ? 'active' : ''; ?>">
                                        <i class="fas fa-user"></i> Staff
                                    </a>
                                    <a href="?role=finance" class="role-switch-btn <?php echo $requested_role == 'finance' ? 'active' : ''; ?>">
                                        <i class="fas fa-user-tie"></i> Finance
                                    </a>
                                    <a href="?role=admin" class="role-switch-btn <?php echo $requested_role == 'admin' ? 'active' : ''; ?>">
                                        <i class="fas fa-user-shield"></i> Admin
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const password = document.getElementById('password');
        const reqLength = document.getElementById('req-length');
        const reqUpper = document.getElementById('req-upper');
        const reqLower = document.getElementById('req-lower');
        const reqNumber = document.getElementById('req-number');
        const reqSpecial = document.getElementById('req-special');
        const strengthBar = document.getElementById('passwordStrength');
        
        password.addEventListener('input', function() {
            const val = this.value;
            const hasLength = val.length >= 8;
            const hasUpper = /[A-Z]/.test(val);
            const hasLower = /[a-z]/.test(val);
            const hasNumber = /[0-9]/.test(val);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(val);
            
            updateRequirement(reqLength, hasLength);
            updateRequirement(reqUpper, hasUpper);
            updateRequirement(reqLower, hasLower);
            updateRequirement(reqNumber, hasNumber);
            updateRequirement(reqSpecial, hasSpecial);
            
            let strength = [hasLength, hasUpper, hasLower, hasNumber, hasSpecial].filter(Boolean).length;
            
            if (val.length === 0) {
                strengthBar.style.display = 'none';
            } else {
                strengthBar.style.display = 'block';
                let color, width;
                if (strength <= 2) { color = '#dc3545'; width = '20%'; }
                else if (strength <= 3) { color = '#ffc107'; width = '50%'; }
                else if (strength <= 4) { color = '#17a2b8'; width = '75%'; }
                else { color = '#28a745'; width = '100%'; }
                strengthBar.style.background = color;
                strengthBar.style.width = width;
            }
        });
        
        function updateRequirement(element, isValid) {
            if (isValid) {
                element.innerHTML = '✓ ' + element.innerText.substring(2);
                element.style.color = '#28a745';
            } else {
                element.innerHTML = '✗ ' + element.innerText.substring(2);
                element.style.color = '#6c757d';
            }
        }
    </script>
</body>
</html>