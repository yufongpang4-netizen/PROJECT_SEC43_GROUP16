<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: ../login.php");
    exit();
}
 
require_once '../db.php';
 
// Get stats from real database
$total     = $conn->query("SELECT COUNT(*) as c FROM claims")->fetch_assoc()['c'];
$pending   = $conn->query("SELECT COUNT(*) as c FROM claims WHERE status='Pending'")->fetch_assoc()['c'];
$approved  = $conn->query("SELECT COUNT(*) as c FROM claims WHERE status='Approved'")->fetch_assoc()['c'];
$paid      = $conn->query("SELECT COUNT(*) as c FROM claims WHERE status='Paid'")->fetch_assoc()['c'];
$rejected  = $conn->query("SELECT COUNT(*) as c FROM claims WHERE status='Rejected'")->fetch_assoc()['c'];
$completed = $paid + $rejected;
 
// Recent 5 claims
$recent_result = $conn->query("
    SELECT c.id, u.name, c.amount, c.status, c.claim_type, c.submitted_at
    FROM claims c
    JOIN users u ON c.user_id = u.id
    ORDER BY c.submitted_at DESC
    LIMIT 5
");
$recent_claims = $recent_result->fetch_all(MYSQLI_ASSOC);
 
// Total amount pending payment (approved but not paid)
$pending_amount = $conn->query("SELECT SUM(amount) as total FROM claims WHERE status='Approved'")->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard - UTMSpace</title>
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
                    <a class="nav-link active" href="dashboard_Finance.php">
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
 
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 style="color: white;">
                        <i class="fas fa-chart-line me-2" style="color: #5BC0BE;"></i>
                        Finance Dashboard
                    </h2>
                    <div class="text-white">
                        <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </div>
                </div>
 
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
                            <div class="stat-number"><?php echo $total; ?></div>
                            <div class="stat-label">Total Claims</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-clock"></i></div>
                            <div class="stat-number"><?php echo $pending; ?></div>
                            <div class="stat-label">Pending Approval</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                            <div class="stat-number"><?php echo $approved; ?></div>
                            <div class="stat-label">Pending Payment</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="stat-number"><?php echo $completed; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </div>
                </div>
 
                <?php if($approved > 0): ?>
                <div class="alert mb-4" style="background: rgba(91,192,190,0.15); border: 1px solid #5BC0BE; color: white;">
                    <i class="fas fa-exclamation-circle me-2" style="color:#5BC0BE;"></i>
                    <strong><?php echo $approved; ?> claim(s)</strong> approved and awaiting payment —
                    Total: <strong>RM <?php echo number_format($pending_amount, 2); ?></strong>
                    <a href="All_Claim_Finance.php?status=Approved" class="btn btn-sm ms-3" style="background:#5BC0BE; color:#0B132B;">Process Now</a>
                </div>
                <?php endif; ?>
 
                <div class="row">
                    <div class="col-md-5">
                        <div class="card border-0 shadow-lg fade-in">
                            <div class="card-body p-4">
                                <h5 style="color: #0B132B;">
                                    <i class="fas fa-bolt me-2" style="color: #5BC0BE;"></i>
                                    Quick Actions
                                </h5>
                                <hr>
                                <a href="All_Claim_Finance.php?status=Pending" class="btn w-100 mb-2" style="background: #5BC0BE; color: #0B132B; border-radius: 10px;">
                                    <i class="fas fa-clock me-2"></i>Review Pending Claims
                                    <?php if($pending > 0): ?>
                                        <span class="badge bg-danger ms-1"><?php echo $pending; ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="All_Claim_Finance.php" class="btn w-100 mb-2" style="background: #3A506B; color: white; border-radius: 10px;">
                                    <i class="fas fa-list me-2"></i>View All Claims
                                </a>
                                <a href="Export_Report_Finance.php" class="btn w-100" style="background: #1C2541; color: white; border-radius: 10px;">
                                    <i class="fas fa-download me-2"></i>Export Monthly Report
                                </a>
                            </div>
                        </div>
 
                        <div class="card border-0 shadow-lg fade-in mt-4">
                            <div class="card-body p-4">
                                <h5 style="color: #0B132B;">
                                    <i class="fas fa-chart-pie me-2" style="color: #5BC0BE;"></i>
                                    Claims Summary
                                </h5>
                                <hr>
                                <div class="d-flex justify-content-between py-1 border-bottom">
                                    <span><span class="status-pending">Pending</span></span>
                                    <strong><?php echo $pending; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between py-1 border-bottom">
                                    <span><span class="status-approved">Approved</span></span>
                                    <strong><?php echo $approved; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between py-1 border-bottom">
                                    <span><span class="status-paid">Paid</span></span>
                                    <strong><?php echo $paid; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between py-1">
                                    <span><span class="status-rejected">Rejected</span></span>
                                    <strong><?php echo $rejected; ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
 
                    <div class="col-md-7">
                        <div class="card border-0 shadow-lg fade-in">
                            <div class="card-body p-4">
                                <h5 style="color: #0B132B;">
                                    <i class="fas fa-bell me-2" style="color: #5BC0BE;"></i>
                                    Recent Claims
                                </h5>
                                <hr>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Staff</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($recent_claims)): ?>
                                            <tr><td colspan="5" class="text-center text-muted">No claims found.</td></tr>
                                            <?php else: ?>
                                            <?php foreach($recent_claims as $rc): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($rc['name']); ?></td>
                                                <td><?php echo htmlspecialchars($rc['claim_type']); ?></td>
                                                <td>RM <?php echo number_format($rc['amount'], 2); ?></td>
                                                <td><span class="status-<?php echo strtolower($rc['status']); ?>"><?php echo ucfirst($rc['status']); ?></span></td>
                                                <td>
                                                    <a href="Claim_details_Finance.php?id=<?php echo $rc['id']; ?>" class="btn btn-sm" style="background:#5BC0BE; color:#0B132B;">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <a href="All_Claim_Finance.php" class="btn btn-sm w-100 mt-2" style="background:#3A506B; color:white;">View All Claims</a>
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