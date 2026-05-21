<?php
session_start();
require_once "../db.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$pendingClaims = 0;
$approvedClaims = 0;
$paidClaims = 0;
$totalAmount = 0.00;

$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_claims,
        COALESCE(SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END), 0) AS approved_claims,
        COALESCE(SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END), 0) AS paid_claims,
        COALESCE(SUM(CASE WHEN status NOT IN ('Cancelled', 'Rejected') THEN amount ELSE 0 END), 0) AS total_amount
    FROM claims
    WHERE user_id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $pendingClaims = $row['pending_claims'] ?? 0;
        $approvedClaims = $row['approved_claims'] ?? 0;
        $paidClaims = $row['paid_claims'] ?? 0;
        $totalAmount = $row['total_amount'] ?? 0.00;
    }
    $stmt->close();
}

$recent_stmt = $conn->prepare("
    SELECT id, claim_type, amount, status, submitted_at 
    FROM claims 
    WHERE user_id = ? 
    ORDER BY submitted_at DESC 
    LIMIT 4
");
$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();
$recent_claims = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* STAFF - LIGHT BACKGROUND WITH DARK BLUE CARDS */
        :root {
            --staff-primary: #0f2b4d;
            --staff-secondary: #1e4d8c;
            --staff-accent: #3b82f6;
            --staff-bg: #f0f4f8;
            --staff-card: #ffffff;
            --staff-text: #1e293b;
            --staff-gray: #64748b;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; margin: 0; padding: 0; }
        body { background: var(--staff-bg); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; }
        
        .container-fluid { height: 100%; overflow: hidden; }
        .row.g-0 { height: 100%; }
        
        /* Sidebar */
        .sidebar { background: linear-gradient(180deg, #0f2b4d 0%, #1e4d8c 100%); height: 100vh; color: white; transition: all 0.3s ease; overflow-y: auto; position: sticky; top: 0; }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 5px; }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.85); padding: 12px 20px; margin: 5px 0; border-radius: 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(59, 130, 246, 0.2); color: #3b82f6; transform: translateX(5px); }
        .sidebar .nav-link.active { background: #3b82f6; color: #0f2b4d; font-weight: 600; }
        
        /* Main Content */
        .main-content { height: 100vh; overflow-y: auto; padding: 20px; }
        .main-content::-webkit-scrollbar { width: 8px; }
        .main-content::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 10px; }
        .main-content::-webkit-scrollbar-thumb { background: #3b82f6; border-radius: 10px; }
        
        /* Page Header */
        .page-header { background: linear-gradient(135deg, #0f2b4d 0%, #1e4d8c 100%); border-radius: 20px; padding: 20px 25px; color: white; margin-bottom: 25px; }
        
        /* Stats Cards - Interactive Hover Colors */
        .stat-card { background: white; border-radius: 20px; padding: 20px; transition: all 0.3s ease; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; cursor: pointer; height: 100%; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); }
        
        /* Pending - Yellow theme */
        .stat-card-pending { border-left: 4px solid #f59e0b; }
        .stat-card-pending:hover { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); }
        .stat-card-pending:hover .stat-icon { background: #f59e0b; color: white; }
        .stat-card-pending:hover .stat-number { color: #b45309; }
        
        /* Approved - Green theme */
        .stat-card-approved { border-left: 4px solid #10b981; }
        .stat-card-approved:hover { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); }
        .stat-card-approved:hover .stat-icon { background: #10b981; color: white; }
        .stat-card-approved:hover .stat-number { color: #065f46; }
        
        /* Paid - Purple theme */
        .stat-card-paid { border-left: 4px solid #8b5cf6; }
        .stat-card-paid:hover { background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); }
        .stat-card-paid:hover .stat-icon { background: #8b5cf6; color: white; }
        .stat-card-paid:hover .stat-number { color: #6d28d9; }
        
        /* Total Amount - Blue theme */
        .stat-card-amount { border-left: 4px solid #3b82f6; }
        .stat-card-amount:hover { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); }
        .stat-card-amount:hover .stat-icon { background: #3b82f6; color: white; }
        .stat-card-amount:hover .stat-number { color: #1e40af; }
        
        .stat-icon { width: 55px; height: 55px; background: #f1f5f9; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #64748b; margin: 0 auto 15px; transition: all 0.3s ease; }
        .stat-number { font-size: 28px; font-weight: 700; color: #0f2b4d; margin-bottom: 5px; transition: all 0.3s ease; }
        .stat-label { color: #64748b; font-size: 14px; font-weight: 500; transition: all 0.3s ease; }
        
        .stat-card:hover .stat-label { color: #1e293b; font-weight: 600; }
        
        /* Action Cards & Tables */
        .action-card { background: white; border-radius: 20px; border: none; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05); transition: all 0.3s ease; }
        .action-card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); }
        .action-card h5 { color: #0f2b4d; font-weight: 600; }
        
        .table-custom { margin-bottom: 0; }
        .table-custom th { color: #0f2b4d; font-weight: 600; border-bottom: 2px solid #e2e8f0; padding: 12px; }
        .table-custom td { vertical-align: middle; padding: 12px; border-bottom: 1px solid #f1f5f9; }
        .table-custom tr:hover { background: #f8fafc; }
        
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-approved { background: #d1fae5; color: #059669; }
        .status-paid { background: #dbeafe; color: #2563eb; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        .status-cancelled { background: #e5e7eb; color: #4b5563; }
        
        /* Buttons */
        .btn-primary-custom { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; border: none; padding: 12px; border-radius: 12px; font-weight: 600; transition: all 0.3s ease; }
        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4); color: white; }
        
        .btn-secondary-custom { background: #f1f5f9; color: #0f2b4d; border: none; padding: 12px; border-radius: 12px; font-weight: 600; transition: all 0.3s ease; }
        .btn-secondary-custom:hover { background: #e2e8f0; transform: translateY(-2px); color: #0f2b4d; }
        
        .btn-sm-view { background: #f1f5f9; color: #3b82f6; border-radius: 8px; padding: 5px 10px; font-size: 12px; transition: all 0.3s ease; text-decoration: none; }
        .btn-sm-view:hover { background: #3b82f6; color: white; }
        
        .tips-text { color: #475569; }
        hr { border-color: #e2e8f0; }
        
        .fade-in { animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        @media (max-width: 768px) { .sidebar { height: auto; position: relative; } }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0">
        
        <div class="col-md-3 col-lg-2 sidebar">
            <div class="p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-receipt fs-1" style="color: #3b82f6;"></i>
                    <h5 class="mt-2">UTMSPACE</h5>
                    <small>Staff Portal</small>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard_Staff.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i> Dashboard</a>
                    <a class="nav-link" href="New_Claim_Staff.php"><i class="fas fa-plus-circle fa-fw me-2"></i> New Claim</a>
                    <a class="nav-link" href="Claim_History_Staff.php"><i class="fas fa-history fa-fw me-2"></i> Claim History</a>
                    <a class="nav-link" href="Edit_profile_Staff.php"><i class="fas fa-user-edit fa-fw me-2"></i> Edit Profile</a>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout</a>
                </nav>
            </div>
        </div>
        
        <div class="col-md-9 col-lg-10 main-content">
            
            <div class="page-header fade-in">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h3 class="mb-1"><i class="fas fa-smile-wink me-2" style="color: #3b82f6;"></i>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h3>
                        <p class="mb-0 opacity-75">Here is the latest update on your expense claims.</p>
                    </div>
                    <div class="mt-2 mt-sm-0">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['email']); ?>
                    </div>
                </div>
            </div>
            
            <div class="row g-4 mb-4 fade-in">
                <div class="col-md-3 col-sm-6">
                    <a href="Claim_History_Staff.php?status=Pending" class="text-decoration-none">
                        <div class="stat-card stat-card-pending text-center">
                            <div class="stat-icon mx-auto"><i class="fas fa-clock"></i></div>
                            <div class="stat-number"><?php echo $pendingClaims; ?></div>
                            <div class="stat-label">Pending Approval <i class="fas fa-external-link-alt ms-1 small"></i></div>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <a href="Claim_History_Staff.php?status=Approved" class="text-decoration-none">
                        <div class="stat-card stat-card-approved text-center">
                            <div class="stat-icon mx-auto"><i class="fas fa-check-circle"></i></div>
                            <div class="stat-number"><?php echo $approvedClaims; ?></div>
                            <div class="stat-label">Approved <i class="fas fa-external-link-alt ms-1 small"></i></div>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <a href="Claim_History_Staff.php?status=Paid" class="text-decoration-none">
                        <div class="stat-card stat-card-paid text-center">
                            <div class="stat-icon mx-auto"><i class="fas fa-money-check-alt"></i></div>
                            <div class="stat-number"><?php echo $paidClaims; ?></div>
                            <div class="stat-label">Successfully Paid <i class="fas fa-external-link-alt ms-1 small"></i></div>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <a href="Claim_History_Staff.php" class="text-decoration-none">
                        <div class="stat-card stat-card-amount text-center">
                            <div class="stat-icon mx-auto"><i class="fas fa-dollar-sign"></i></div>
                            <div class="stat-number">RM <?php echo number_format($totalAmount, 2); ?></div>
                            <div class="stat-label">Total Claim Amount <i class="fas fa-external-link-alt ms-1 small"></i></div>
                        </div>
                    </a>
                </div>
            </div>
            
            <div class="row g-4 fade-in">
                <div class="col-md-5">
                    <div class="action-card mb-4">
                        <div class="card-body p-4">
                            <h5><i class="fas fa-bolt me-2" style="color: #3b82f6;"></i>Quick Actions</h5>
                            <hr>
                            <a href="New_Claim_Staff.php" class="btn btn-primary-custom w-100 mb-3"><i class="fas fa-plus-circle me-2"></i>Submit New Claim</a>
                            <a href="Claim_History_Staff.php" class="btn btn-secondary-custom w-100"><i class="fas fa-history me-2"></i>View Claim History</a>
                        </div>
                    </div>
                    
                    <div class="action-card">
                        <div class="card-body p-4">
                            <h5><i class="fas fa-info-circle me-2" style="color: #3b82f6;"></i>Claim Guidelines</h5>
                            <hr>
                            <div class="d-flex align-items-center mb-3 tips-text"><i class="fas fa-check text-success me-3"></i><small>Max claim amount per receipt: <strong>RM 200</strong></small></div>
                            <div class="d-flex align-items-center mb-3 tips-text"><i class="fas fa-check text-success me-3"></i><small>Monthly allowance: <strong>RM 500</strong> (max 3 claims)</small></div>
                            <div class="d-flex align-items-center mb-3 tips-text"><i class="fas fa-exclamation-triangle text-warning me-3"></i><small>Pending claims block new submissions</small></div>
                            <div class="d-flex align-items-center tips-text"><i class="fas fa-ban text-danger me-3"></i><small>Cancelled/Rejected claims refund your quota</small></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-7">
                    <div class="action-card h-100">
                        <div class="card-body p-4 d-flex flex-column">
                            <h5><i class="fas fa-clock me-2" style="color: #3b82f6;"></i>Recent Claim Activity</h5>
                            <hr>
                            <div class="table-responsive flex-grow-1">
                                <table class="table table-custom">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($recent_claims)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-5">
                                                <i class="fas fa-folder-open fa-3x mb-3 d-block opacity-25"></i>
                                                You haven't submitted any claims yet.
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach($recent_claims as $rc): ?>
                                        <tr>
                                            <td class="text-muted"><small><?php echo date('d M Y', strtotime($rc['submitted_at'])); ?></small></td>
                                            <td class="fw-semibold text-dark"><?php echo htmlspecialchars($rc['claim_type']); ?></td>
                                            <td class="fw-bold" style="color: #0f2b4d;">RM <?php echo number_format($rc['amount'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($rc['status']); ?>">
                                                    <i class="fas <?php echo match(strtolower($rc['status'])) { 'pending' => 'fa-clock', 'approved' => 'fa-check', 'paid' => 'fa-dollar-sign', 'cancelled' => 'fa-ban', default => 'fa-times' }; ?>"></i>
                                                    <?php echo ucfirst($rc['status']); ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <a href="Claim_History_Staff.php" class="btn-sm-view">Detail <i class="fas fa-chevron-right ms-1"></i></a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if(!empty($recent_claims)): ?>
                            <a href="Claim_History_Staff.php" class="btn btn-secondary-custom w-100 mt-3">
                                <i class="fas fa-arrow-right me-2"></i>See All Claims
                            </a>
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