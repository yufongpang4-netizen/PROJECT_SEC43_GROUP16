<?php
session_start();
require_once "../db.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error   = '';

// ========== CLAIM LIMITS CONFIGURATION ==========
$MAX_CLAIM_AMOUNT_PER_MONTH = 500;   // Maximum RM 500 per month total
$MAX_NUMBER_OF_CLAIMS_PER_MONTH = 3;  // Maximum 3 claims per month
$MAX_AMOUNT_PER_CLAIM = 200;          // Maximum RM 200 per single claim

$user_id = $_SESSION['user_id'];
$current_month = date('Y-m');

// Check current month's claims
$check_sql = "
    SELECT 
        COUNT(*) as total_claims,
        SUM(amount) as total_amount
    FROM claims 
    WHERE user_id = ? 
    AND DATE_FORMAT(submitted_at, '%Y-%m') = ?
";

$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("is", $user_id, $current_month);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$monthly_data = $check_result->fetch_assoc();

$total_claims_this_month = $monthly_data['total_claims'] ?? 0;
$total_amount_this_month = $monthly_data['total_amount'] ?? 0;

// Check for pending claims
$pending_sql = "SELECT COUNT(*) as pending_count FROM claims WHERE user_id = ? AND status = 'Pending'";
$pending_stmt = $conn->prepare($pending_sql);
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending_data = $pending_result->fetch_assoc();
$has_pending_claims = ($pending_data['pending_count'] > 0);

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $claim_type   = $_POST['claim_type'];
    $amount       = floatval($_POST['amount']);
    $expense_date = $_POST['date'];
    $description  = trim($_POST['description']);
    
    // Validate required fields
    if(empty($claim_type) || empty($amount) || empty($expense_date) || empty($description)) {
        $error = "Please fill in all required fields.";
    } 
    elseif(!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid amount.";
    }
    // Check if user has pending claims
    elseif($has_pending_claims) {
        $error = "You have pending claims awaiting approval. Please wait for approval before submitting new claims.";
    }
    // Check maximum amount per claim
    elseif($amount > $MAX_AMOUNT_PER_CLAIM) {
        $error = "Maximum claim amount per claim is RM " . number_format($MAX_AMOUNT_PER_CLAIM, 2) . 
                 ". Your claim amount: RM " . number_format($amount, 2);
    }
    // Check minimum amount
    elseif($amount < 1) {
        $error = "Minimum claim amount is RM 1.00";
    }
    // Check monthly limit (total amount)
    elseif(($total_amount_this_month + $amount) > $MAX_CLAIM_AMOUNT_PER_MONTH) {
        $remaining = $MAX_CLAIM_AMOUNT_PER_MONTH - $total_amount_this_month;
        $error = "Monthly claim limit reached! You have claimed RM " . 
                 number_format($total_amount_this_month, 2) . " out of RM " . 
                 number_format($MAX_CLAIM_AMOUNT_PER_MONTH, 2) . 
                 ". Remaining: RM " . number_format($remaining, 2);
    }
    // Check number of claims per month
    elseif($total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) {
        $error = "You have reached the maximum of " . $MAX_NUMBER_OF_CLAIMS_PER_MONTH . 
                 " claims for this month.";
    }
    else {
        $status = 'Pending';
        
        // Handle receipt upload
        $receipt_filename = null;
        if(isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
            $file_type     = $_FILES['receipt']['type'];
            $max_file_size = 5 * 1024 * 1024; // 5MB
            
            if($_FILES['receipt']['size'] > $max_file_size) {
                $error = "File size too large. Maximum size is 5MB.";
            }
            elseif(in_array($file_type, $allowed_types)) {
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
            $stmt = $conn->prepare("
                INSERT INTO claims (user_id, claim_type, amount, expense_date, description, receipt, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isdssss",
                $user_id,
                $claim_type,
                $amount,
                $expense_date,
                $description,
                $receipt_filename,
                $status
            );
            
            if($stmt->execute()) {
                $success = "Claim submitted successfully! Finance will review your claim.";
                // Log the activity
                logActivity($conn, $user_id, 'Submit Claim', "Submitted claim of RM " . number_format($amount, 2) . " for " . $claim_type);
                
                // Refresh monthly data
                $total_amount_this_month += $amount;
                $total_claims_this_month++;
            } else {
                $error = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Calculate remaining amounts for display
$remaining_amount = $MAX_CLAIM_AMOUNT_PER_MONTH - $total_amount_this_month;
$remaining_claims = $MAX_NUMBER_OF_CLAIMS_PER_MONTH - $total_claims_this_month;
$amount_percentage = ($total_amount_this_month / $MAX_CLAIM_AMOUNT_PER_MONTH) * 100;
$claim_percentage = ($total_claims_this_month / $MAX_NUMBER_OF_CLAIMS_PER_MONTH) * 100;
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
    <style>
        .limit-card {
            background: linear-gradient(135deg, #0B132B 0%, #1C2541 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
        }
        .progress {
            height: 8px;
            border-radius: 10px;
            background-color: rgba(255,255,255,0.2);
        }
        .progress-bar {
            background-color: #5BC0BE;
        }
        .limit-warning {
            background-color: rgba(255,193,7,0.2);
            border-left: 4px solid #ffc107;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .claim-disabled {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
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
 
                <!-- Limits Summary Card -->
                <div class="limit-card">
                    <h5><i class="fas fa-chart-line me-2"></i>Your Monthly Claim Limits</h5>
                    <div class="row mt-3">
                        <div class="col-md-4 mb-3">
                            <small>Monthly Budget</small>
                            <h6 class="mb-2">RM <?php echo number_format($total_amount_this_month, 2); ?> / RM <?php echo number_format($MAX_CLAIM_AMOUNT_PER_MONTH, 2); ?></h6>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo min($amount_percentage, 100); ?>%"></div>
                            </div>
                            <small class="text-info">Remaining: RM <?php echo number_format(max($remaining_amount, 0), 2); ?></small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small>Claims Count</small>
                            <h6 class="mb-2"><?php echo $total_claims_this_month; ?> / <?php echo $MAX_NUMBER_OF_CLAIMS_PER_MONTH; ?></h6>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo min($claim_percentage, 100); ?>%"></div>
                            </div>
                            <small class="text-info">Remaining: <?php echo max($remaining_claims, 0); ?> claims</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small>Per Claim Limit</small>
                            <h6 class="mb-2">RM <?php echo number_format($MAX_AMOUNT_PER_CLAIM, 2); ?></h6>
                            <small class="text-info">Maximum per submission</small>
                        </div>
                    </div>
                    
                    <?php if($has_pending_claims): ?>
                    <div class="limit-warning">
                        <i class="fas fa-clock me-2"></i>
                        <strong>Pending Claims Alert!</strong> You have <?php echo $pending_data['pending_count']; ?> pending claim(s) awaiting approval. 
                        You cannot submit new claims until they are processed.
                    </div>
                    <?php endif; ?>
                    
                    <?php if($total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH): ?>
                    <div class="limit-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Monthly budget exhausted!</strong> You have reached your monthly claim limit of RM <?php echo number_format($MAX_CLAIM_AMOUNT_PER_MONTH, 2); ?>.
                    </div>
                    <?php endif; ?>
                    
                    <?php if($total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH): ?>
                    <div class="limit-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Monthly claim limit reached!</strong> You have submitted the maximum of <?php echo $MAX_NUMBER_OF_CLAIMS_PER_MONTH; ?> claims this month.
                    </div>
                    <?php endif; ?>
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
                        <form method="POST" enctype="multipart/form-data" id="claimForm" 
                              <?php echo ($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) ? 'class="claim-disabled"' : ''; ?>>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-tag me-1" style="color: #5BC0BE;"></i>Claim Type *
                                    </label>
                                    <select name="claim_type" class="form-select" required 
                                            <?php echo ($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) ? 'disabled' : ''; ?>>
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
 
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-dollar-sign me-1" style="color: #5BC0BE;"></i>Amount (RM) *
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">RM</span>
                                        <input type="number" name="amount" step="0.01" min="0.01" 
                                               max="<?php echo $MAX_AMOUNT_PER_CLAIM; ?>"
                                               class="form-control" required placeholder="0.00"
                                               value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>"
                                               <?php echo ($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) ? 'disabled' : ''; ?>>
                                    </div>
                                    <small class="text-muted">Maximum: RM <?php echo number_format($MAX_AMOUNT_PER_CLAIM, 2); ?> per claim</small>
                                </div>
 
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-calendar me-1" style="color: #5BC0BE;"></i>Expense Date *
                                    </label>
                                    <input type="date" name="date" class="form-control" required
                                        max="<?php echo date('Y-m-d'); ?>"
                                        value="<?php echo htmlspecialchars($_POST['date'] ?? ''); ?>"
                                        <?php echo ($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) ? 'disabled' : ''; ?>>
                                </div>
 
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-paperclip me-1" style="color: #5BC0BE;"></i>Attach Receipt
                                    </label>
                                    <input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png"
                                        id="receiptFile" onchange="previewFile(this)"
                                        <?php echo ($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) ? 'disabled' : ''; ?>>
                                    <small class="text-muted">Accepted: PDF, JPG, PNG (max 5MB)</small>
                                    <div id="filePreview" class="mt-2" style="display:none;">
                                        <img id="imgPreview" src="" alt="Preview" class="img-thumbnail" style="max-height:120px;">
                                    </div>
                                </div>
 
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold" style="color: #0B132B;">
                                        <i class="fas fa-align-left me-1" style="color: #5BC0BE;"></i>Description *
                                    </label>
                                    <textarea name="description" rows="4" class="form-control" required
                                        placeholder="Describe your expense in detail..."
                                        <?php echo ($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH) ? 'disabled' : ''; ?>><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
 
                            <hr>
 
                            <div class="d-flex gap-3">
                                <?php if($has_pending_claims || $total_amount_this_month >= $MAX_CLAIM_AMOUNT_PER_MONTH || $total_claims_this_month >= $MAX_NUMBER_OF_CLAIMS_PER_MONTH): ?>
                                    <button type="button" class="btn btn-secondary" style="padding: 12px 30px;" disabled>
                                        <i class="fas fa-ban me-2"></i>Claim Submission Disabled
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="submit"
                                        class="btn" style="background: #5BC0BE; color: #0B132B; padding: 12px 30px;">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Claim
                                    </button>
                                <?php endif; ?>
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
                    preview.style.display = 'none';
                }
                // Basic size check (5MB)
                if(file.size > 5 * 1024 * 1024) {
                    alert('File is too large. Maximum size is 5MB.');
                    input.value = '';
                    preview.style.display = 'none';
                }
            }
        }
        
        // Real-time amount validation
        const amountInput = document.querySelector('input[name="amount"]');
        if(amountInput) {
            amountInput.addEventListener('input', function() {
                const maxAmount = <?php echo $MAX_AMOUNT_PER_CLAIM; ?>;
                if(parseFloat(this.value) > maxAmount) {
                    this.setCustomValidity(`Amount cannot exceed RM ${maxAmount.toFixed(2)}`);
                    this.style.borderColor = '#dc3545';
                } else {
                    this.setCustomValidity('');
                    this.style.borderColor = '#ced4da';
                }
            });
        }
    </script>
</body>
</html>