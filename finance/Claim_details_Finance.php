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
    <title>Claim Details - Finance | UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* FINANCE - DARK GREEN THEME WITH LIGHT BACKGROUND */
        :root {
            --finance-primary: #064e3b;
            --finance-secondary: #047857;
            --finance-accent: #10b981;
            --finance-bg: #ecfdf5;
            --finance-card: #ffffff;
            --finance-text: #064e3b;
            --finance-gray: #6b7280;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: var(--finance-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #064e3b 0%, #047857 100%);
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
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: #10b981;
            color: #064e3b;
            font-weight: 600;
        }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #064e3b 0%, #047857 100%);
            border-radius: 20px;
            padding: 20px 25px;
            color: white;
            margin-bottom: 25px;
        }
        
        /* Cards */
        .info-card, .receipt-card, .action-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .card-title {
            color: #064e3b;
            font-size: 18px;
            font-weight: 600;
        }
        
        /* Info Grid */
        .info-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-weight: 600;
            color: #064e3b;
            margin-bottom: 12px;
        }
        
        .info-value-amount {
            font-size: 28px;
            font-weight: 700;
            color: #10b981;
        }
        
        /* Status Badges */
        .status-pending { background: #fef3c7; color: #d97706; padding: 5px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; }
        .status-approved { background: #d1fae5; color: #059669; padding: 5px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; }
        .status-paid { background: #dbeafe; color: #2563eb; padding: 5px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; }
        .status-rejected { background: #fee2e2; color: #dc2626; padding: 5px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; }
        
        /* Buttons */
        .btn-approve {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4);
            color: white;
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(239, 68, 68, 0.4);
            color: white;
        }
        
        .btn-paid {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-paid:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.4);
            color: white;
        }
        
        .btn-back {
            background: #f1f5f9;
            color: #064e3b;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
            color: #064e3b;
        }
        
        .btn-preview, .btn-download {
            border-radius: 10px;
            padding: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-preview {
            background: #10b981;
            color: white;
        }
        
        .btn-preview:hover {
            background: #059669;
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-download {
            background: #064e3b;
            color: white;
        }
        
        .btn-download:hover {
            background: #047857;
            transform: translateY(-2px);
            color: white;
        }
        
        /* Alert States */
        .alert-info-custom {
            background: #dbeafe;
            border-left: 4px solid #2563eb;
            color: #1e40af;
        }
        
        .alert-success-custom {
            background: #d1fae5;
            border-left: 4px solid #059669;
            color: #065f46;
        }
        
        .alert-danger-custom {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            color: #991b1b;
        }
        
        .alert-warning-custom {
            background: #fef3c7;
            border-left: 4px solid #d97706;
            color: #92400e;
        }
        
        /* Form Controls */
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        hr {
            border-color: #e5e7eb;
        }
        
        .receipt-img {
            max-height: 180px;
            object-fit: contain;
            border-radius: 10px;
        }
        
        .remark-box {
            background: #f0fdf4;
            border-radius: 12px;
            padding: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-chart-line fs-1" style="color: #10b981;"></i>
                    <h5 class="mt-2">UTMSPACE</h5>
                    <small>Finance Portal</small>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard_Finance.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    <a class="nav-link active" href="All_Claim_Finance.php">
                        <i class="fas fa-file-invoice me-2"></i> All Claims
                    </a>
                    <a class="nav-link" href="Export_Report_Finance.php">
                        <i class="fas fa-download me-2"></i> Export Report
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
                                <i class="fas fa-file-invoice me-2" style="color: #10b981;"></i>
                                Claim Details
                            </h3>
                            <p class="mb-0 opacity-75">Review and manage claim #<?php echo $claim['id']; ?></p>
                        </div>
                        <a href="All_Claim_Finance.php" class="btn btn-back mt-2 mt-sm-0">
                            <i class="fas fa-arrow-left me-2"></i>Back to Claims
                        </a>
                    </div>
                </div>
 
                <?php if($success): ?>
                    <div class="alert alert-success-custom fade-in">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                <?php endif; ?>
                <?php if($error): ?>
                    <div class="alert alert-danger-custom fade-in">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>
 
                <div class="row g-4 fade-in">
                    <!-- Claim Information Column -->
                    <div class="col-md-7">
                        <div class="info-card">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title">
                                        <i class="fas fa-receipt me-2" style="color: #10b981;"></i>
                                        Claim Information
                                    </h5>
                                    <span class="status-<?php echo $status; ?>"><?php echo ucfirst($status); ?></span>
                                </div>
                                <hr>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-label">Claim ID</div>
                                        <div class="info-value">#<?php echo $claim['id']; ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-label">Submitted On</div>
                                        <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($claim['submitted_at'])); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-label">Staff Name</div>
                                        <div class="info-value"><?php echo htmlspecialchars($claim['staff']); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-label">Staff ID</div>
                                        <div class="info-value"><?php echo htmlspecialchars($claim['staff_id']); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-label">Email</div>
                                        <div class="info-value"><?php echo htmlspecialchars($claim['staff_email']); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-label">Department</div>
                                        <div class="info-value"><?php echo htmlspecialchars($claim['department'] ?: '—'); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-label">Claim Type</div>
                                        <div class="info-value"><?php echo htmlspecialchars($claim['claim_type']); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-label">Expense Date</div>
                                        <div class="info-value"><?php echo $claim['expense_date'] ? date('d M Y', strtotime($claim['expense_date'])) : '-'; ?></div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="info-label">Amount</div>
                                        <div class="info-value-amount">RM <?php echo number_format($claim['amount'], 2); ?></div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="info-label">Description</div>
                                        <div class="info-value"><?php echo nl2br(htmlspecialchars($claim['description'] ?? '-')); ?></div>
                                    </div>
                                    
                                    <?php if(!empty($claim['finance_comment'])): ?>
                                    <div class="col-12 mt-3">
                                        <div class="remark-box">
                                            <i class="fas fa-comment me-2" style="color: #10b981;"></i>
                                            <strong>Finance Remark</strong>
                                            <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($claim['finance_comment'])); ?></p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
 
                    <!-- Receipt & Actions Column -->
                    <div class="col-md-5">
                        <!-- Receipt Card -->
                        <div class="receipt-card mb-4">
                            <div class="card-body text-center p-4">
                                <i class="fas fa-paperclip" style="font-size: 45px; color: #10b981;"></i>
                                <h5 class="mt-3" style="color: #064e3b;">Attached Receipt</h5>
                                
                                <?php if(!empty($claim['receipt'])): ?>
                                    <p class="text-muted small"><?php echo htmlspecialchars($claim['receipt']); ?></p>
                                    
                                    <?php
                                    $receipt_path = '../uploads/receipts/' . $claim['receipt'];
                                    $ext = strtolower(pathinfo($claim['receipt'], PATHINFO_EXTENSION));
                                    ?>
                                    
                                    <?php if(in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                                        <img src="<?php echo $receipt_path; ?>" class="receipt-img mb-3" alt="Receipt">
                                    <?php endif; ?>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="<?php echo $receipt_path; ?>" target="_blank" class="btn btn-preview">
                                            <i class="fas fa-eye me-2"></i>Preview Receipt
                                        </a>
                                        <a href="<?php echo $receipt_path; ?>" download class="btn btn-download">
                                            <i class="fas fa-download me-2"></i>Download Receipt
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="alert-warning-custom p-3 rounded mt-2">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        No receipt attached to this claim.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
 
                        <!-- Actions Card -->
                        <div class="action-card">
                            <div class="card-body p-4">
                                <h5 class="card-title">
                                    <i class="fas fa-gavel me-2" style="color: #10b981;"></i>
                                    Finance Actions
                                </h5>
                                <hr>
 
                                <?php if($status == 'pending'): ?>
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Comments / Remarks</label>
                                            <textarea name="comments" class="form-control" rows="3"
                                                placeholder="Add your comments here... (required for rejection)"></textarea>
                                            <small class="text-muted mt-1 d-block">
                                                <i class="fas fa-info-circle me-1"></i> Remarks will be visible to the staff member
                                            </small>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="approve" class="btn btn-approve"
                                                onclick="return confirm('Approve this claim?')">
                                                <i class="fas fa-check-circle me-2"></i>Approve Claim
                                            </button>
                                            <button type="submit" name="reject" class="btn btn-reject"
                                                onclick="return confirm('Reject this claim? This action cannot be undone.')">
                                                <i class="fas fa-times-circle me-2"></i>Reject Claim
                                            </button>
                                        </div>
                                    </form>
 
                                <?php elseif($status == 'approved'): ?>
                                    <div class="alert-warning-custom p-3 mb-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Claim Approved</strong><br>
                                        This claim has been approved. Mark as paid once payment is processed.
                                    </div>
                                    <form method="POST">
                                        <div class="d-grid">
                                            <button type="submit" name="mark_paid" class="btn btn-paid"
                                                onclick="return confirm('Confirm payment of RM <?php echo number_format($claim['amount'],2); ?>? This will mark the claim as PAID.')">
                                                <i class="fas fa-dollar-sign me-2"></i>Mark as Paid
                                            </button>
                                        </div>
                                    </form>
 
                                <?php elseif($status == 'paid'): ?>
                                    <div class="alert-success-custom p-3 text-center">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Claim Paid</strong><br>
                                        This claim has been paid. No further action required.
                                    </div>
 
                                <?php elseif($status == 'rejected'): ?>
                                    <div class="alert-danger-custom p-3 text-center">
                                        <i class="fas fa-times-circle me-2"></i>
                                        <strong>Claim Rejected</strong><br>
                                        This claim has been rejected.
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