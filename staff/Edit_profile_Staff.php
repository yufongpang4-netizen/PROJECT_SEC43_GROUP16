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

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = $_POST['department'];
    $password = $_POST['password'];

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
    } else {
        $error = "Failed to update profile: " . $conn->error;
    }
    $stmt->close();
}

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
        
        body {
            background: linear-gradient(135deg, #e8f0fe 0%, #d9e6f5 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #1e3a5f 0%, #2c5282 100%);
            min-height: 100vh;
            color: white;
            transition: all 0.3s ease;
        }
        
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
            border-radius: 10px;
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
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-size: 12px;
            color: #64748b;
        }
        
        .info-value {
            font-weight: 600;
            color: #1e3a5f;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        hr {
            border-color: #e2e8f0;
        }
        
        .text-muted-small {
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-receipt fs-1" style="color: #5BC0BE;"></i>
                    <h5 class="mt-2">UTMSPACE</h5>
                    <small>Staff Portal</small>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard_Staff.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="New_Claim_Staff.php">
                        <i class="fas fa-plus-circle me-2"></i> New Claim
                    </a>
                    <a class="nav-link" href="Claim_History_Staff.php">
                        <i class="fas fa-history me-2"></i> Claim History
                    </a>
                    <a class="nav-link active" href="Edit_profile_Staff.php">
                        <i class="fas fa-user-edit me-2"></i> Edit Profile
                    </a>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <!-- Page Header -->
                <div class="page-header fade-in">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h3 class="mb-1">
                                <i class="fas fa-user-edit me-2" style="color: #5BC0BE;"></i>
                                Edit Profile
                            </h3>
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
                                <h5 class="mb-4" style="color: #1e3a5f;">
                                    <i class="fas fa-pen me-2" style="color: #3b82f6;"></i>
                                    Personal Information
                                </h5>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-user me-1" style="color: #3b82f6;"></i>Full Name
                                        </label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['name']); ?>" disabled>
                                        <small class="text-muted-small">Name cannot be changed. Contact admin for changes.</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-envelope me-1" style="color: #3b82f6;"></i>Email Address
                                        </label>
                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-phone me-1" style="color: #3b82f6;"></i>Phone Number
                                        </label>
                                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($current_phone); ?>" placeholder="0123456789">
                                        <small class="text-muted-small">Malaysian format: 0123456789</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-building me-1" style="color: #3b82f6;"></i>Department
                                        </label>
                                        <select name="department" class="form-select">
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
                                        <label class="form-label">
                                            <i class="fas fa-key me-1" style="color: #3b82f6;"></i>New Password
                                        </label>
                                        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                                        <small class="text-muted-small">Minimum 8 characters with uppercase, lowercase, number, and special character</small>
                                    </div>
                                    
                                    <hr>
                                    
                                    <button type="submit" class="btn btn-save">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
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
</body>
</html>