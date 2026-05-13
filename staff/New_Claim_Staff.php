<?php
session_start();
require_once "../db.php";
 
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../login.php");
    exit();
}
 
$success = '';
$error   = '';
 
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id     = $_SESSION['user_id'];
    $claim_title = trim($_POST['claim_title']);
    $claim_type  = $_POST['claim_type'];
    $amount      = $_POST['amount'];
    $claim_date  = $_POST['date'];
    $description = trim($_POST['description']);
    $action      = $_POST['action'];
 
    // Validate required fields
    if(empty($claim_title) || empty($claim_type) || empty($amount) || empty($claim_date) || empty($description)) {
        $error = "Please fill in all required fields.";
    } elseif(!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid amount.";
    } else {
        $status = ($action == 'submit') ? 'pending' : 'pending'; // only pending/paid/approved/rejected in enum; no draft
        // Note: DB enum is pending|approved|rejected|paid — save submitted as 'pending'
 
        // Handle receipt upload
        $receipt_filename = null;
        if(isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
            $file_type     = $_FILES['receipt']['type'];
 
            if(in_array($file_type, $allowed_types)) {
                $upload_dir = '../uploads/receipts/';
                if(!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
 
                $file_ext         = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
                $receipt_filename = 'receipt_' . time() . '_' . $user_id . '.' . $file_ext;
                $target_file      = $upload_dir . $receipt_filename;
 
                if(!move_uploaded_file($_FILES['receipt']['tmp_name'], $target_file)) {
                    $error = "Failed to save the uploaded receipt. Please try again.";
                }
            } else {
                $error = "Invalid file format. Only PDF, JPG, and PNG are allowed.";
            }
        }
 
        if(empty($error)) {
            // DB columns: claim_title, claim_type, amount, claim_date, description, receipt_file, status
            $stmt = $conn->prepare("
                INSERT INTO claims (user_id, claim_title, claim_type, amount, claim_date, description, receipt_file, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issdssss",
                $user_id,
                $claim_title,
                $claim_type,
                $amount,
                $claim_date,
                $description,
                $receipt_filename,
                $status
            );
 
            if($stmt->execute()) {
                $success = ($action == 'submit')
                    ? "Claim submitted successfully! Finance will review your claim."
                    : "Claim submitted successfully!";
            } else {
                $error = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
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
 
            <!-- Main Content -->
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
                        &nbsp;<a href="Claim_History_Staff.php" class="alert-link">View your claims →</a>
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
                        <form method="POST" enctype="multipart/form-data" id="claimForm">
                            <div class="row">
 
                                <!-- Claim Title -->
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-heading me-1" style="color: #5BC0BE;"></i>Claim Title *
                                    </label>
                                    <input type="text" name="claim_title" class="form-control" required
                                        placeholder="e.g. Taxi to KLCC client meeting"
                                        value="<?php echo htmlspecialchars($_POST['claim_title'] ?? ''); ?>">
                                </div>
 
                                <!-- Claim Type -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-tag me-1" style="color: #5BC0BE;"></i>Claim Type *
                                    </label>
                                    <select name="claim_type" class="form-select" required>
                                        <option value="">Select claim type</option>
                                        <?php
                                        $types = ['Travel','Meal','Accommodation','Transportation','Office Supplies','Training','Medical'];
                                        foreach($types as $t) {
                                            $sel = (($_POST['claim_type'] ?? '') == $t) ? 'selected' : '';
                                            echo "<option value=\"$t\" $sel>$t</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
 
                                <!-- Amount -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-dollar-sign me-1" style="color: #5BC0BE;"></i>Amount (RM) *
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">RM</span>
                                        <input type="number" name="amount" step="0.01" min="0.01" class="form-control"
                                            required placeholder="0.00"
                                            value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
                                    </div>
                                </div>
 
                                <!-- Expense Date -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-calendar me-1" style="color: #5BC0BE;"></i>Expense Date *
                                    </label>
                                    <input type="date" name="date" class="form-control" required
                                        max="<?php echo date('Y-m-d'); ?>"
                                        value="<?php echo htmlspecialchars($_POST['date'] ?? ''); ?>">
                                </div>
 
                                <!-- Receipt Upload -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-paperclip me-1" style="color: #5BC0BE;"></i>Attach Receipt
                                    </label>
                                    <input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png"
                                        id="receiptFile" onchange="previewFile(this)">
                                    <small class="text-muted">Accepted: PDF, JPG, PNG (max 5MB)</small>
                                    <div id="filePreview" class="mt-2" style="display:none;">
                                        <img id="imgPreview" src="" alt="Preview" class="img-thumbnail" style="max-height:120px;">
                                    </div>
                                </div>
 
                                <!-- Description -->
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-align-left me-1" style="color: #5BC0BE;"></i>Description *
                                    </label>
                                    <textarea name="description" rows="4" class="form-control" required
                                        placeholder="Describe your expense in detail..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
 
                            <hr>
 
                            <div class="d-flex gap-3">
                                <button type="submit" name="action" value="submit"
                                    class="btn" style="background: #5BC0BE; color: #0B132B; padding: 12px 30px;">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Claim
                                </button>
                                <a href="dashboard_Staff.php" class="btn" style="background: #3A506B; color: white; padding: 12px 30px;">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
 
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewFile(input) {
            const preview = document.getElementById('filePreview');
            const img     = document.getElementById('imgPreview');
            if(input.files && input.files[0]) {
                const file = input.files[0];
                if(file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = e => { img.src = e.target.result; preview.style.display = 'block'; };
                    reader.readAsDataURL(file);
                } else {
                    preview.style.display = 'none'; // PDF — no image preview
                }
                // Basic size check (5MB)
                if(file.size > 5 * 1024 * 1024) {
                    alert('File is too large. Maximum size is 5MB.');
                    input.value = '';
                    preview.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>