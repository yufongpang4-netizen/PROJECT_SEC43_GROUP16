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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $staff_id = trim($_POST['staff_id']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    
    // ========== SET DEPARTMENT BASED ON ROLE ==========
    if ($role == 'finance') {
        $department = 'Finance'; // Auto-assigned for finance
    } elseif ($role == 'admin') {
        $department = NULL; // No department for admin
    } else {
        $department = trim($_POST['department'] ?? ''); // Staff selects their department
    }

    // ========== VALIDATION RULES ==========
    
    // 1. Check all required fields
    if (empty($name) || empty($staff_id) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    }
    
    // 2. NAME validation
    elseif (!preg_match('/^[a-zA-Z\s\'-]{2,50}$/', $name)) {
        $error = "Name must be 2-50 characters and can only contain letters, spaces, hyphens, and apostrophes.";
    }
    
    // 3. STAFF ID validation
    elseif (!preg_match('/^[a-zA-Z0-9]{5,15}$/', $staff_id)) {
        $error = "Staff ID must be 5-15 characters and can only contain letters and numbers (no spaces or symbols).";
    }
    
    // 4. EMAIL validation
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address (example: name@domain.com)!";
    }
    
    // 5. DEPARTMENT validation - ONLY for staff role (must select a department)
    elseif ($role == 'staff' && empty($department)) {
        $error = "Please select a department for Staff account.";
    }
    
    // 6. PHONE validation (optional but must be valid if provided)
    elseif (!empty($phone) && !preg_match('/^(\+?6?01)[0-9]{8,9}$/', $phone)) {
        $error = "Please enter a valid phone number! Example: 0123456789 or +60123456789";
    }
    
    // 7. PASSWORD validation
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long!";
    }
    elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least 1 uppercase letter!";
    }
    elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least 1 lowercase letter!";
    }
    elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least 1 number!";
    }
    elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?]/', $password)) {
        $error = "Password must contain at least 1 special character (!@#$%^&* etc)!";
    }
    
    else {
        // Check if email or staff_id already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR staff_id = ?");
        $check->bind_param("ss", $email, $staff_id);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            $error = "Email or Staff ID already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users 
                (name, staff_id, email, department, password, phone, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "sssssss",
                $name,
                $staff_id,
                $email,
                $department,
                $hashed_password,
                $phone,
                $role
            );

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
    <title>Register - UTMSpace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .role-badge {
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 20px;
        }
        .role-staff { background: #17a2b8; color: white; }
        .role-finance { background: #28a745; color: white; }
        .role-admin { background: #dc3545; color: white; }
    </style>
</head>
<body style="background: linear-gradient(135deg, #0B132B 0%, #1C2541 50%, #3A506B 100%); min-height: 100vh;">
    <div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="row justify-content-center w-100">
            <div class="col-md-6">
                <div class="card border-0 shadow-lg fade-in" style="border-radius: 20px;">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas <?php echo $requested_role == 'admin' ? 'fa-user-shield' : ($requested_role == 'finance' ? 'fa-user-tie' : 'fa-user-plus'); ?>" style="font-size: 50px; color: #5BC0BE;"></i>
                            <h2 class="mt-3" style="color: #0B132B;"><?php echo ucfirst($requested_role); ?> Registration</h2>
                            <p style="color: #3A506B;">Create your account with valid information</p>
                            
                            <!-- Show role info badge -->
                            <?php if($requested_role == 'finance'): ?>
                                <span class="role-badge role-finance"><i class="fas fa-building"></i> Department: Finance </span>
                            <?php elseif($requested_role == 'admin'): ?>
                                <span class="role-badge role-admin"><i class="fas fa-user-shield"></i> No Department (Admin)</span>
                            <?php else: ?>
                                <span class="role-badge role-staff"><i class="fas fa-users"></i> Select your department</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                <a href="login.php" class="fw-bold text-decoration-none">Login here</a>
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
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-user me-1" style="color: #5BC0BE;"></i>Full Name *
                                    </label>
                                    <input type="text" name="name" class="form-control" id="name" required 
                                           pattern="[a-zA-Z\s\'-]{2,50}" 
                                           title="Only letters, spaces, hyphens, and apostrophes. 2-50 characters.">
                                    <small class="text-muted">Only letters, spaces, hyphens, and apostrophes (2-50 chars)</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-id-card me-1" style="color: #5BC0BE;"></i>Staff ID *
                                    </label>
                                    <input type="text" name="staff_id" class="form-control" id="staff_id" required
                                           pattern="[a-zA-Z0-9]{5,15}"
                                           title="Only letters and numbers, 5-15 characters.">
                                    <small class="text-muted">5-15 characters, letters and numbers only (e.g., STAFF123)</small>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-envelope me-1" style="color: #5BC0BE;"></i>Email *
                                    </label>
                                    <input type="email" name="email" class="form-control" id="email" required>
                                    <small class="text-muted">Enter a valid email address</small>
                                </div>
                                
                                <!-- DEPARTMENT FIELD - Only show for STAFF role -->
                                <?php if($requested_role == 'staff'): ?>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-building me-1" style="color: #5BC0BE;"></i>Department *
                                    </label>
                                    <select name="department" class="form-select" id="department" required>
                                        <option value="">Select Department</option>
                                        <option value="Information Technology">Information Technology</option>
                                        <option value="Human Resources">Human Resources</option>
                                        <option value="Marketing">Marketing</option>
                                        <option value="Operations">Operations</option>
                                        <option value="Sales">Sales</option>
                                        <option value="Customer Service">Customer Service</option>
                                        <option value="Research & Development">Research & Development</option>
                                    </select>
                                    <small class="text-muted">Select your working department</small>
                                </div>
                                <?php endif; ?>
                                
                                <!-- For FINANCE role - Show disabled field with "Finance" auto-filled -->
                                <?php if($requested_role == 'finance'): ?>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-building me-1" style="color: #5BC0BE;"></i>Department
                                    </label>
                                    <input type="text" class="form-control" value="Finance" disabled 
                                           style="background: #e9ecef;">
                                    <small class="text-muted text-success">
                                        <i class="fas fa-info-circle"></i> Finance department is automatically assigned
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <!-- For ADMIN role - Show no department field at all -->
                                <?php if($requested_role == 'admin'): ?>
                                <div class="col-md-12 mb-3">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> 
                                        <strong>Admin Account</strong> 
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-phone me-1" style="color: #5BC0BE;"></i>Phone Number (Optional)
                                    </label>
                                    <input type="tel" name="phone" class="form-control" id="phone" 
                                           pattern="^(\+?6?01)[0-9]{8,9}$"
                                           placeholder="0123456789 or +60123456789">
                                    <small class="text-muted">Malaysian format: 0123456789 or +60123456789</small>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-key me-1" style="color: #5BC0BE;"></i>Password *
                                    </label>
                                    <input type="password" name="password" class="form-control" id="password" required>
                                    <div class="password-strength" id="passwordStrength"></div>
                                    <small class="text-muted">
                                        Minimum 8 characters with: uppercase, lowercase, number, and special character (!@#$%^&*)
                                    </small>
                                    <ul class="small mt-1" id="passwordRequirements">
                                        <li id="req-length" class="text-muted">✗ At least 8 characters</li>
                                        <li id="req-upper" class="text-muted">✗ At least 1 uppercase letter</li>
                                        <li id="req-lower" class="text-muted">✗ At least 1 lowercase letter</li>
                                        <li id="req-number" class="text-muted">✗ At least 1 number</li>
                                        <li id="req-special" class="text-muted">✗ At least 1 special character</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn w-100 mt-2" style="background: #5BC0BE; color: #0B132B; padding: 12px; border-radius: 10px; font-weight: bold;">
                                <i class="fas fa-check-circle me-2"></i>Complete Registration
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-0">Already have an account? <a href="login.php" style="color: #5BC0BE; font-weight: bold;">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time password validation
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
                if (strength <= 2) {
                    color = '#dc3545';
                    width = '20%';
                } else if (strength <= 3) {
                    color = '#ffc107';
                    width = '50%';
                } else if (strength <= 4) {
                    color = '#17a2b8';
                    width = '75%';
                } else {
                    color = '#28a745';
                    width = '100%';
                }
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
        
        // Phone validation
        const phoneInput = document.getElementById('phone');
        if(phoneInput) {
            phoneInput.addEventListener('input', function() {
                if (this.value && !this.value.match(/^(\+?6?01)[0-9]{8,9}$/)) {
                    this.style.borderColor = '#dc3545';
                } else {
                    this.style.borderColor = '#ced4da';
                }
            });
        }
    </script>
</body>
</html>