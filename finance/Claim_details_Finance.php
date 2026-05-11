<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: ../login.php");
    exit();
}

$claim_id = $_GET['id'] ?? 1;
$success = '';

$claim = [
    'id' => $claim_id,
    'staff' => 'John Staff',
    'staff_id' => 'STF001',
    'staff_email' => 'john@utmspace.com',
    'department' => 'IT',
    'type' => 'Travel',
    'amount' => 150.00,
    'date' => '2026-04-15',
    'status' => 'Pending',
    'description' => 'Taxi to client meeting at KLCC. Met with potential client about new project.',
    'receipt' => 'receipt_taxi.pdf',
    'submitted_date' => '2026-04-15 14:30:00'
];

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['approve'])) {
        $claim['status'] = 'Approved';
        $success = "Claim has been APPROVED!";
    } elseif(isset($_POST['reject'])) {
        $claim['status'] = 'Rejected';
        $success = "Claim has been REJECTED.";
    } elseif(isset($_POST['mark_paid'])) {
        $claim['status'] = 'Paid';
        $success = "Claim has been marked as PAID!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Details - Finance</title>
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
                    <i class="fas fa-chart-line fs-1" style="color: #5BC0BE;"></i>
                    <h5 class="mt-2">UTMSpace</h5>
                    <small>Finance Portal</small>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard_Finance.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="All_Claim_Finance.php">
                        <i class="fas fa-file-invoice"></i> All Claims
                    </a>
                    <a class="nav-link" href="Export_Report_Finance.php">
                        <i class="fas fa-download"></i> Export Report
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
                        <i class="fas fa-file-invoice me-2" style="color: #5BC0BE;"></i>
                        Claim Details
                    </h2>
                    <a href="all_claims.php" class="btn" style="background: #3A506B; color: white;">
                        <i class="fas fa-arrow-left me-2"></i>Back to Claims
                    </a>
                </div>
                
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-7">
                        <div class="card border-0 shadow-lg fade-in">
                            <div class="card-body p-4">
                                <h4 style="color: #0B132B;">
                                    <i class="fas fa-receipt me-2" style="color: #5BC0BE;"></i>
                                    Claim Information
                                </h4>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Claim ID</label>
                                        <p class="fw-bold mb-0">#<?php echo $claim['id']; ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Status</label>
                                        <p><span class="status-<?php echo strtolower($claim['status']); ?>"><?php echo $claim['status']; ?></span></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Staff Name</label>
                                        <p class="fw-bold mb-0"><?php echo $claim['staff']; ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Staff ID</label>
                                        <p class="fw-bold mb-0"><?php echo $claim['staff_id']; ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Department</label>
                                        <p><?php echo $claim['department']; ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Claim Type</label>
                                        <p><?php echo $claim['type']; ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Amount</label>
                                        <p class="fs-3 fw-bold" style="color: #5BC0BE;">RM <?php echo number_format($claim['amount'], 2); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Expense Date</label>
                                        <p><?php echo $claim['date']; ?></p>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="text-muted small">Description</label>
                                        <p><?php echo $claim['description']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-5">
                        <!-- Receipt Section -->
                        <div class="card border-0 shadow-lg mb-4">
                            <div class="card-body text-center p-4">
                                <i class="fas fa-paperclip" style="font-size: 40px; color: #5BC0BE;"></i>
                                <h5 class="mt-3">Attached Receipt</h5>
                                <p class="text-muted small"><?php echo $claim['receipt']; ?></p>
                                <div class="d-grid gap-2">
                                    <button class="btn" style="background: #5BC0BE; color: #0B132B;" onclick="alert('Preview: Receipt would open here')">
                                        <i class="fas fa-eye me-2"></i>Preview Receipt
                                    </button>
                                    <button class="btn" style="background: #3A506B; color: white;" onclick="alert('Downloading receipt...')">
                                        <i class="fas fa-download me-2"></i>Download Receipt
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Finance Actions -->
                        <div class="card border-0 shadow-lg">
                            <div class="card-body p-4">
                                <h5 style="color: #0B132B;">
                                    <i class="fas fa-gavel me-2" style="color: #5BC0BE;"></i>
                                    Finance Actions
                                </h5>
                                <hr>
                                
                                <?php if($claim['status'] == 'Pending'): ?>
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Comments / Remarks</label>
                                            <textarea name="comments" class="form-control" rows="3" placeholder="Add your comments here..."></textarea>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="approve" class="btn" style="background: #48bb78; color: white;">
                                                <i class="fas fa-check me-2"></i>Approve Claim
                                            </button>
                                            <button type="submit" name="reject" class="btn btn-danger">
                                                <i class="fas fa-times me-2"></i>Reject Claim
                                            </button>
                                        </div>
                                    </form>
                                <?php elseif($claim['status'] == 'Approved'): ?>
                                    <form method="POST">
                                        <div class="d-grid">
                                            <button type="submit" name="mark_paid" class="btn" style="background: #5BC0BE; color: #0B132B;">
                                                <i class="fas fa-dollar-sign me-2"></i>Mark as Paid
                                            </button>
                                        </div>
                                    </form>
                                <?php elseif($claim['status'] == 'Paid'): ?>
                                    <div class="alert alert-success text-center">
                                        <i class="fas fa-check-circle me-2"></i>This claim has been paid.
                                    </div>
                                <?php elseif($claim['status'] == 'Rejected'): ?>
                                    <div class="alert alert-danger text-center">
                                        <i class="fas fa-times-circle me-2"></i>This claim has been rejected.
                                    </div>
                                <?php endif; ?>
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
