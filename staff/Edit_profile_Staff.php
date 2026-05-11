<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../login.php");
    exit();
}

$success = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $success = "Profile updated successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - UTMSpace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-receipt fs-1" style="color: #5BC0BE;"></i>
                    <h5 class="mt-2">UTMSpace</h5>
                    <small>Staff Portal</small>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard_Staff.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="New_Claim_Staff.php">
                        <i class="fas fa-plus-circle"></i> New Claim
                    </a>
                    <a class="nav-link" href="Claim_History_Staff.php">
                        <i class="fas fa-history"></i> Claim History
                    </a>
                    <a class="nav-link" href="Edit_profile_Staff.php">
                        <i class="fas fa-user-edit"></i> Edit Profile
                    </a>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 style="color: white;">
                        <i class="fas fa-user-edit me-2" style="color: #5BC0BE;"></i>
                        Edit Profile
                    </h2>
                </div>
                
                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card border-0 shadow-lg fade-in">
                            <div class="card-body p-4">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold" style="color: #0B132B;">
                                            <i class="fas fa-user me-1" style="color: #5BC0BE;"></i>Full Name
                                        </label>
                                        <input type="text" class="form-control" value="<?php echo $_SESSION['user_name']; ?>" disabled>
                                        <small class="text-muted">Name cannot be changed. Contact admin for changes.</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold" style="color: #0B132B;">
                                            <i class="fas fa-envelope me-1" style="color: #5BC0BE;"></i>Email Address
                                        </label>
                                        <input type="email" name="email" class="form-control" value="<?php echo $_SESSION['email']; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold" style="color: #0B132B;">
                                            <i class="fas fa-phone me-1" style="color: #5BC0BE;"></i>Phone Number
                                        </label>
                                        <input type="tel" name="phone" class="form-control" value="+60123456789">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold" style="color: #0B132B;">
                                            <i class="fas fa-building me-1" style="color: #5BC0BE;"></i>Department
                                        </label>
                                        <select name="department" class="form-select">
                                            <option>Information Technology</option>
                                            <option>Human Resources</option>
                                            <option>Finance</option>
                                            <option>Marketing</option>
                                            <option>Operations</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold" style="color: #0B132B;">
                                            <i class="fas fa-key me-1" style="color: #5BC0BE;"></i>New Password
                                        </label>
                                        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
                                    </div>
                                    
                                    <hr>
                                    
                                    <button type="submit" class="btn" style="background: #5BC0BE; color: #0B132B; padding: 12px 30px;">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border-0 shadow-lg">
                            <div class="card-body text-center p-4">
                                <i class="fas fa-user-circle" style="font-size: 80px; color: #5BC0BE;"></i>
                                <h5 class="mt-3" style="color: #0B132B;"><?php echo $_SESSION['user_name']; ?></h5>
                                <p class="text-muted">Staff Member</p>
                                <hr>
                                <div class="text-start">
                                    <small><i class="fas fa-id-card me-1" style="color: #5BC0BE;"></i> Staff ID: STF001</small><br>
                                    <small><i class="fas fa-calendar me-1" style="color: #5BC0BE;"></i> Member since: 2024</small>
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
