<?php
session_start();
require_once "../db.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT staff_id, name, email, phone, department, created_at FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

$current_phone = $user_data['phone'] ?? '';
$current_dept = $user_data['department'] ?? '';
$staff_id = $user_data['staff_id'];
$join_year = date('Y', strtotime($user_data['created_at']));

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = $_POST['department'];
    $password = $_POST['password'];
    
    // ========== VALIDATION RULES ==========
    $errors = [];
    
    // 1. Email validation
    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address (e.g., name@domain.com).";
    } else {
        // Check if email already exists for another user
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_email->bind_param("si", $email, $user_id);
        $check_email->execute();
        $check_email->store_result();
        if ($check_email->num_rows > 0) {
            $errors[] = "This email is already registered by another user.";
        }
        $check_email->close();
    }
    
    // 2. Phone validation (optional but must be valid if provided)
    if (!empty($phone) && !preg_match('/^(\+?6?01)[0-9]{8,9}$/', $phone)) {
        $errors[] = "Please enter a valid phone number! Example: 0123456789 or +60123456789";
    }
    
    // 3. Password validation (only if user wants to change password)
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least 1 uppercase letter.";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least 1 lowercase letter.";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least 1 number.";
        }
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?]/', $password)) {
            $errors[] = "Password must contain at least 1 special character (!@#$%^&* etc).";
        }
    }
    
    // If no errors, proceed with update
    if (empty($errors)) {
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET email=?, phone=?, department=?, password=? WHERE id=?");
            $stmt->bind_param("ssssi", $email, $phone, $department, $hashed_password, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET email=?, phone=?, department=? WHERE id=?");
            $stmt->bind_param("sssi", $email, $phone, $department, $user_id);
        }
        
        if($stmt->execute()) {
            $success = "Profile updated successfully!";
            $_SESSION['email'] = $email;
            
            // Refresh user data
            $stmt2 = $conn->prepare("SELECT staff_id, name, email, phone, department, created_at FROM users WHERE id=?");
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $user_data = $result2->fetch_assoc();
            $current_phone = $user_data['phone'] ?? '';
            $current_dept = $user_data['department'] ?? '';
            $stmt2->close();
        } else {
            $error = "Failed to update profile: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Staff Dashboard - Soft Blue Theme */
        :root {
            --staff-primary: #1e3a5f;
            --staff-secondary: #3b82f6;
            --staff-soft: #e8f0fe;
            --staff-accent: #5BC0BE;
            --staff-white: #ffffff;
            --staff-text: #1e293b;
            --staff-gray: #64748b;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html, body { height: 100%; margin: 0; padding: 0; }
        
        body {
            background: linear-gradient(135deg, #e8f0fe 0%, #d9e6f5 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        .container-fluid { height: 100%; overflow: hidden; }
        .row.g-0 { height: 100%; }
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #1e3a5f 0%, #2c5282 100%);
            height: 100vh;
            color: white;
            transition: all 0.3s ease;
            overflow-y: auto;
            position: sticky;
            top: 0;
        }
        
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 5px; }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(91, 192, 190, 0.2);
            color: #5BC0BE;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: #5BC0BE;
            color: #1e3a5f;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
        }
        
        .main-content::-webkit-scrollbar { width: 8px; }
        .main-content::-webkit-scrollbar-track { background: #e5e7eb; border-radius: 10px; }
        .main-content::-webkit-scrollbar-thumb { background: #3b82f6; border-radius: 10px; }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
            border-radius: 20px;
            padding: 20px 25px;
            color: white;
            margin-bottom: 25px;
        }
        
        /* Form Card */
        .form-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }
        
        .form-label {
            font-weight: 600;
            color: #1e3a5f;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-control:disabled {
            background: #f1f5f9;
            color: #64748b;
        }
        
        .is-invalid {
            border-color: #dc3545 !important;
        }
        
        .invalid-feedback-custom {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }
        
        .valid-feedback-custom {
            color: #28a745;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
            color: white;
        }
        
        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
        }
        
        .profile-avatar {
            position: relative;
            margin-top: 50px;
        }
        
        .profile-avatar i {
            font-size: 80px;
            color: #5BC0BE;
            background: white;
            border-radius: 50%;
            padding: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .profile-name {
            margin-top: 20px;
            color: #1e3a5f;
        }
        
        .profile-role {
            color: #64748b;
            font-size: 14px;
        }
        
        .info-item {
            padding: 10px 0;
            border-bottom: 1px solid #eef2ff;
        }
        
        .info-item:last-child { border-bottom: none; }
        .info-label { font-size: 12px; color: #64748b; }
        .info-value { font-weight: 600; color: #1e3a5f; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in { animation: fadeIn 0.5s ease-out; }
        hr { border-color: #e2e8f0; }
        
        @media (max-width: 768px) {
            .sidebar { height: auto; position: relative; }
            .main-content { height: auto; overflow-y: visible; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row g-0">
        
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 sidebar">
            <div class="p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-receipt fs-1" style="color: #5BC0BE;"></i>
                    <h5 class="mt-2">UTMSPACE</h5>
                    <small>Staff Portal</small>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard_Staff.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
                    <a class="nav-link" href="New_Claim_Staff.php"><i class="fas fa-plus-circle me-2"></i> New Claim</a>
                    <a class="nav-link" href="Claim_History_Staff.php"><i class="fas fa-history me-2"></i> Claim History</a>
                    <a class="nav-link active" href="Edit_profile_Staff.php"><i class="fas fa-user-edit me-2"></i> Edit Profile</a>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 main-content">
            
            <!-- Page Header -->
            <div class="page-header fade-in">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h3 class="mb-1"><i class="fas fa-user-edit me-2" style="color: #5BC0BE;"></i>Edit Profile</h3>
                        <p class="mb-0 opacity-75">Update your personal information and account settings</p>
                    </div>
                </div>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show fade-in" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row g-4 fade-in">
                <!-- Edit Form Column -->
                <div class="col-md-8">
                    <div class="form-card">
                        <div class="card-body p-4">
                            <h5 class="mb-4" style="color: #1e3a5f;"><i class="fas fa-pen me-2" style="color: #3b82f6;"></i>Personal Information</h5>
                            
                            <form method="POST" id="editProfileForm">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-user me-1" style="color: #3b82f6;"></i>Full Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['name']); ?>" disabled>
                                    <small class="text-muted">Name cannot be changed. Contact admin for changes.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-envelope me-1" style="color: #3b82f6;"></i>Email Address *</label>
                                    <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                                    <div id="emailFeedback" class="invalid-feedback-custom"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-phone me-1" style="color: #3b82f6;"></i>Phone Number</label>
                                    <input type="tel" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($current_phone); ?>" placeholder="0123456789">
                                    <div id="phoneFeedback" class="invalid-feedback-custom"></div>
                                    <small class="text-muted">Malaysian format: 0123456789 or +60123456789</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-building me-1" style="color: #3b82f6;"></i>Department</label>
                                    <select name="department" id="department" class="form-select">
                                        <option value="Information Technology" <?php echo ($current_dept == 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                                        <option value="Human Resources" <?php echo ($current_dept == 'Human Resources') ? 'selected' : ''; ?>>Human Resources</option>
                                        <option value="Finance" <?php echo ($current_dept == 'Finance') ? 'selected' : ''; ?>>Finance</option>
                                        <option value="Marketing" <?php echo ($current_dept == 'Marketing') ? 'selected' : ''; ?>>Marketing</option>
                                        <option value="Operations" <?php echo ($current_dept == 'Operations') ? 'selected' : ''; ?>>Operations</option>
                                        <option value="Sales" <?php echo ($current_dept == 'Sales') ? 'selected' : ''; ?>>Sales</option>
                                        <option value="Customer Service" <?php echo ($current_dept == 'Customer Service') ? 'selected' : ''; ?>>Customer Service</option>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label"><i class="fas fa-key me-1" style="color: #3b82f6;"></i>New Password</label>
                                    <input type="password" name="password" id="password" class="form-control" placeholder="Leave blank to keep current password">
                                    <div id="passwordFeedback" class="invalid-feedback-custom"></div>
                                    <small class="text-muted">Minimum 8 characters with uppercase, lowercase, number, and special character</small>
                                </div>
                                
                                <div id="passwordRequirements" class="small mb-3" style="display: none;">
                                    <div id="req-length" class="text-muted">✗ At least 8 characters</div>
                                    <div id="req-upper" class="text-muted">✗ At least 1 uppercase letter</div>
                                    <div id="req-lower" class="text-muted">✗ At least 1 lowercase letter</div>
                                    <div id="req-number" class="text-muted">✗ At least 1 number</div>
                                    <div id="req-special" class="text-muted">✗ At least 1 special character</div>
                                </div>
                                
                                <hr>
                                
                                <button type="submit" class="btn btn-save"><i class="fas fa-save me-2"></i>Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Card Column -->
                <div class="col-md-4">
                    <div class="profile-card">
                        <div class="card-body p-4">
                            <div class="profile-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <h5 class="profile-name"><?php echo htmlspecialchars($user_data['name']); ?></h5>
                            <p class="profile-role">Staff Member</p>
                            
                            <hr>
                            
                            <div class="info-item">
                                <div class="info-label">Staff ID</div>
                                <div class="info-value"><?php echo htmlspecialchars($staff_id); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><?php echo $join_year; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Department</div>
                                <div class="info-value"><?php echo htmlspecialchars($current_dept ?: 'Not set'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value" style="font-size: 12px; word-break: break-all;"><?php echo htmlspecialchars($user_data['email']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($current_phone ?: 'Not provided'); ?></div>
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
    // Real-time validation functions
    function validateEmail() {
        const email = document.getElementById('email').value;
        const feedback = document.getElementById('emailFeedback');
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (!email) {
            feedback.innerHTML = 'Email address is required.';
            return false;
        } else if (!regex.test(email)) {
            feedback.innerHTML = 'Please enter a valid email address.';
            return false;
        } else {
            feedback.innerHTML = '';
            return true;
        }
    }
    
    function validatePhone() {
        const phone = document.getElementById('phone').value;
        const feedback = document.getElementById('phoneFeedback');
        const regex = /^(\+?6?01)[0-9]{8,9}$/;
        
        if (phone && !regex.test(phone)) {
            feedback.innerHTML = 'Please enter a valid Malaysian phone number.';
            return false;
        } else {
            feedback.innerHTML = '';
            return true;
        }
    }
    
    // Password validation with real-time requirements
    const passwordInput = document.getElementById('password');
    const reqContainer = document.getElementById('passwordRequirements');
    const reqLength = document.getElementById('req-length');
    const reqUpper = document.getElementById('req-upper');
    const reqLower = document.getElementById('req-lower');
    const reqNumber = document.getElementById('req-number');
    const reqSpecial = document.getElementById('req-special');
    const passwordFeedback = document.getElementById('passwordFeedback');
    
    function validatePassword() {
        const password = passwordInput.value;
        
        if (password.length === 0) {
            reqContainer.style.display = 'none';
            passwordFeedback.innerHTML = '';
            return true;
        }
        
        reqContainer.style.display = 'block';
        
        const hasLength = password.length >= 8;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
        
        // Update requirement display
        updateRequirement(reqLength, hasLength);
        updateRequirement(reqUpper, hasUpper);
        updateRequirement(reqLower, hasLower);
        updateRequirement(reqNumber, hasNumber);
        updateRequirement(reqSpecial, hasSpecial);
        
        const isValid = hasLength && hasUpper && hasLower && hasNumber && hasSpecial;
        
        if (password && !isValid) {
            passwordFeedback.innerHTML = 'Please meet all password requirements.';
            return false;
        } else {
            passwordFeedback.innerHTML = '';
            return true;
        }
    }
    
    function updateRequirement(element, isValid) {
        if (isValid) {
            element.innerHTML = '✓ ' + element.innerText.substring(2);
            element.style.color = '#28a745';
        } else {
            element.innerHTML = '✗ ' + element.innerText.substring(2);
            element.style.color = '#6c757d';
        }
    }
    
    // Add event listeners
    document.getElementById('email').addEventListener('input', validateEmail);
    document.getElementById('phone').addEventListener('input', validatePhone);
    passwordInput.addEventListener('input', validatePassword);
    
    function validateForm() {
        const isEmailValid = validateEmail();
        const isPhoneValid = validatePhone();
        const isPasswordValid = validatePassword();
        
        return isEmailValid && isPhoneValid && isPasswordValid;
    }
    
    // Form submission validation
    document.getElementById('editProfileForm').addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
            alert('Please fix the errors before submitting.');
        }
    });
</script>
</body>
</html>