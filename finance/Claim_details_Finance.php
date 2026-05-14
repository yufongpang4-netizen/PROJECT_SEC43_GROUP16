<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: ../login.php");
    exit();
}
 
require_once '../db.php';
 
$claim_id = intval($_GET['id'] ?? 0);
if(!$claim_id) {
    header("Location: All_Claim_Finance.php");
    exit();
}
 
$success = '';
$error   = '';
 
// Handle form actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $remark = trim($_POST['comments'] ?? '');
 
    if(isset($_POST['approve'])) {
        $stmt = $conn->prepare("UPDATE claims SET status='Approved', finance_comment=? WHERE id=?");
        $stmt->bind_param('si', $remark, $claim_id);
        $stmt->execute();
        $success = "Claim has been <strong>APPROVED</strong> successfully!";
 
    } elseif(isset($_POST['reject'])) {
        if(empty($remark)) {
            $error = "Please provide a reason for rejection.";
        } else {
            $stmt = $conn->prepare("UPDATE claims SET status='Rejected', finance_comment=? WHERE id=?");
            $stmt->bind_param('si', $remark, $claim_id);
            $stmt->execute();
            $success = "Claim has been <strong>REJECTED</strong>.";
        }
 
    } elseif(isset($_POST['mark_paid'])) {
        $stmt = $conn->prepare("UPDATE claims SET status='Paid' WHERE id=?");
        $stmt->bind_param('i', $claim_id);
        $stmt->execute();
        $success = "Claim has been marked as <strong>PAID</strong>!";
    }
}
 
$stmt = $conn->prepare("
    SELECT c.*, u.name AS staff, u.staff_id, u.email AS staff_email, u.department
    FROM claims c
    JOIN users u ON c.user_id = u.id
    WHERE c.id = ?
");
$stmt->bind_param('i', $claim_id);
$stmt->execute();
$claim = $stmt->get_result()->fetch_assoc();
 
if(!$claim) {
    header("Location: All_Claim_Finance.php");
    exit();
}
 
$status = strtolower($claim['status']);
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
                    <a class="nav-link active" href="All_Claim_Finance.php">
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
 
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 style="color: white;">
                        <i class="fas fa-file-invoice me-2" style="color: #5BC0BE;"></i>
                        Claim Details
                    </h2>
                    <a href="All_Claim_Finance.php" class="btn" style="background: #3A506B; color: white;">
                        <i class="fas fa-arrow-left me-2"></i>Back to Claims
                    </a>
                </div>
 
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                <?php endif; ?>
                <?php if($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
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
                                        <p><span class="status-<?php echo $status; ?>"><?php echo ucfirst($status); ?></span></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Staff Name</label>
                                        <p class="fw-bold mb-0"><?php echo htmlspecialchars($claim['staff']); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Staff ID</label>
                                        <p class="fw-bold mb-0"><?php echo htmlspecialchars($claim['staff_id']); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Email</label>
                                        <p><?php echo htmlspecialchars($claim['staff_email']); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Department</label>
                                        <p><?php echo htmlspecialchars($claim['department']); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Claim Type</label>
                                        <p><?php echo htmlspecialchars($claim['claim_type']); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Amount</label>
                                        <p class="fs-3 fw-bold" style="color: #5BC0BE;">RM <?php echo number_format($claim['amount'], 2); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Expense Date</label>
                                        <p><?php echo $claim['expense_date'] ? date('d M Y', strtotime($claim['expense_date'])) : '-'; ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">Submitted On</label>
                                        <p><?php echo date('d M Y, h:i A', strtotime($claim['submitted_at'])); ?></p>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="text-muted small">Description</label>
                                        <p><?php echo nl2br(htmlspecialchars($claim['description'] ?? '-')); ?></p>
                                    </div>
                                    
                                    <?php if(!empty($claim['finance_comment'])): ?>
                                    <div class="col-12">
                                        <label class="text-muted small">Finance Remark</label>
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-comment me-2"></i>
                                            <?php echo nl2br(htmlspecialchars($claim['finance_comment'])); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
 
                    <div class="col-md-5">
                        <div class="card border-0 shadow-lg mb-4">
                            <div class="card-body text-center p-4">
                                <i class="fas fa-paperclip" style="font-size: 40px; color: #5BC0BE;"></i>
                                <h5 class="mt-3">Attached Receipt</h5>
                                <?php if(!empty($claim['receipt'])): ?>
                                    <p class="text-muted small"><?php echo htmlspecialchars($claim['receipt']); ?></p>
                                    <div class="d-grid gap-2">
                                        <?php
                                        $receipt_path = '../uploads/receipts/' . $claim['receipt'];
                                        $ext = strtolower(pathinfo($claim['receipt'], PATHINFO_EXTENSION));
                                        ?>
                                        <?php if(in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                                            <img src="<?php echo $receipt_path; ?>" class="img-fluid rounded mb-2" alt="Receipt" style="max-height:200px; object-fit:contain;">
                                        <?php endif; ?>
                                        <a href="<?php echo $receipt_path; ?>" target="_blank" class="btn" style="background: #5BC0BE; color: #0B132B;">
                                            <i class="fas fa-eye me-2"></i>Preview Receipt
                                        </a>
                                        <a href="<?php echo $receipt_path; ?>" download class="btn" style="background: #3A506B; color: white;">
                                            <i class="fas fa-download me-2"></i>Download Receipt
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No receipt attached.</p>
                                <?php endif; ?>
                            </div>
                        </div>
 
                        <div class="card border-0 shadow-lg">
                            <div class="card-body p-4">
                                <h5 style="color: #0B132B;">
                                    <i class="fas fa-gavel me-2" style="color: #5BC0BE;"></i>
                                    Finance Actions
                                </h5>
                                <hr>
 
                                <?php if($status == 'pending'): ?>
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Comments / Remarks</label>
                                            <textarea name="comments" class="form-control" rows="3"
                                                placeholder="Add your comments here... (required for rejection)"></textarea>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="approve" class="btn" style="background: #48bb78; color: white;"
                                                onclick="return confirm('Approve this claim?')">
                                                <i class="fas fa-check me-2"></i>Approve Claim
                                            </button>
                                            <button type="submit" name="reject" class="btn btn-danger"
                                                onclick="return confirm('Reject this claim? Please ensure you have entered a reason.')">
                                                <i class="fas fa-times me-2"></i>Reject Claim
                                            </button>
                                        </div>
                                    </form>
 
                                <?php elseif($status == 'approved'): ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        This claim has been approved. Mark as paid once payment is processed.
                                    </div>
                                    <form method="POST">
                                        <div class="d-grid">
                                            <button type="submit" name="mark_paid" class="btn" style="background: #5BC0BE; color: #0B132B;"
                                                onclick="return confirm('Confirm payment of RM <?php echo number_format($claim['amount'],2); ?>?')">
                                                <i class="fas fa-dollar-sign me-2"></i>Mark as Paid
                                            </button>
                                        </div>
                                    </form>
 
                                <?php elseif($status == 'paid'): ?>
                                    <div class="alert alert-success text-center">
                                        <i class="fas fa-check-circle me-2"></i>
                                        This claim has been <strong>paid</strong>. No further action required.
                                    </div>
 
                                <?php elseif($status == 'rejected'): ?>
                                    <div class="alert alert-danger text-center">
                                        <i class="fas fa-times-circle me-2"></i>
                                        This claim has been <strong>rejected</strong>.
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