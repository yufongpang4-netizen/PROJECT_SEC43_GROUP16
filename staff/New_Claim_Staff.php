<?php
session_start();
require_once "../db.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $claim_type = $_POST['claim_type'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $action = $_POST['action'];
    
    $status = ($action == 'submit') ? 'Pending' : 'Draft';
    
    $receipt_filename = null;
    if(isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        $file_type = $_FILES['receipt']['type'];
        
        if(in_array($file_type, $allowed_types)) {
            $upload_dir = '../uploads/receipts/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES["receipt"]["name"], PATHINFO_EXTENSION);
            $receipt_filename = "receipt_" . time() . "_" . $user_id . "." . $file_extension;
            $target_file = $upload_dir . $receipt_filename;
            
            if(!move_uploaded_file($_FILES["receipt"]["tmp_name"], $target_file)) {
                $error = "Failed to save the uploaded receipt.";
            }
        } else {
            $error = "Invalid file format. Only PDF, JPG, and PNG are allowed.";
        }
    }

    if(empty($error)) {
        $stmt = $conn->prepare("INSERT INTO claims (user_id, claim_type, amount, expense_date, description, receipt, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("isdssss", $user_id, $claim_type, $amount, $date, $description, $receipt_filename, $status);
        
        if($stmt->execute()) {
            $success = $action == 'submit' ? "Claim submitted successfully!" : "Draft saved successfully!";
        } else {
            $error = "Database error: " . $conn->error;
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
    <title>New Claim - UTMSpace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
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
                    <a class="nav-link active" href="New_Claim_Staff.php">
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
            
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 style="color: white;">
                        <i class="fas fa-plus-circle me-2" style="color: #5BC0BE;"></i>
                        Submit New Claim
                    </h2>
                </div>
                
                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <a href="Claim_History_Staff.php" class="alert-link">View your claims</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card border-0 shadow-lg fade-in">
                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-tag me-1" style="color: #5BC0BE;"></i>Claim Type *
                                    </label>
                                    <select name="claim_type" class="form-select" required>
                                        <option value="">Select claim type</option>
                                        <option value="Travel">Travel</option>
                                        <option value="Meal">Meal</option>
                                        <option value="Accommodation">Accommodation</option>
                                        <option value="Transportation">Transportation</option>
                                        <option value="Office Supplies">Office Supplies</option>
                                        <option value="Training">Training</option>
                                        <option value="Medical">Medical</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-dollar-sign me-1" style="color: #5BC0BE;"></i>Amount (RM) *
                                    </label>
                                    <input type="number" name="amount" step="0.01" class="form-control" required placeholder="0.00">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-calendar me-1" style="color: #5BC0BE;"></i>Expense Date *
                                    </label>
                                    <input type="date" name="date" class="form-control" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-paperclip me-1" style="color: #5BC0BE;"></i>Attach Receipt
                                    </label>
                                    <input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">Accepted: PDF, JPG, PNG</small>
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-align-left me-1" style="color: #5BC0BE;"></i>Description *
                                    </label>
                                    <textarea name="description" rows="4" class="form-control" required placeholder="Describe your expense..."></textarea>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex gap-3">
                                <button type="submit" name="action" value="submit" class="btn" style="background: #5BC0BE; color: #0B132B; padding: 12px 30px;">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Claim
                                </button>
                                <button type="submit" name="action" value="draft" class="btn" style="background: #3A506B; color: white; padding: 12px 30px;">
                                    <i class="fas fa-save me-2"></i>Save as Draft
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>