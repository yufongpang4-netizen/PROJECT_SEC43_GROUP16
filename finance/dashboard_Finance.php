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
    <title>Finance Dashboard - UTMSPACE</title>
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
        
        /* Stats Cards with Hover Colors */
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s ease;
            border: 1px solid #d1fae5;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }
        
        /* Total Claims - Green theme */
        .stat-card-total:hover {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border-color: #10b981;
        }
        .stat-card-total:hover .stat-icon { background: #10b981; color: white; }
        
        /* Pending - Yellow theme */
        .stat-card-pending:hover {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border-color: #f59e0b;
        }
        .stat-card-pending:hover .stat-icon { background: #f59e0b; color: white; }
        .stat-card-pending:hover .stat-number { color: #b45309; }
        
        /* Pending Payment - Blue theme */
        .stat-card-approved:hover {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-color: #3b82f6;
        }
        .stat-card-approved:hover .stat-icon { background: #3b82f6; color: white; }
        .stat-card-approved:hover .stat-number { color: #1e40af; }
        
        /* Completed - Purple theme */
        .stat-card-completed:hover {
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            border-color: #8b5cf6;
        }
        .stat-card-completed:hover .stat-icon { background: #8b5cf6; color: white; }
        .stat-card-completed:hover .stat-number { color: #6d28d9; }
        
        .stat-icon {
            width: 55px;
            height: 55px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #10b981;
            margin: 0 auto 15px;
            transition: all 0.3s ease;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #064e3b;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        /* Alert Banner */
        .payment-alert {
            background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%);
            border-left: 4px solid #f59e0b;
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 25px;
            color: #92400e;
        }
        
        /* Action Cards */
        .action-card, .summary-card, .recent-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .action-card:hover, .summary-card:hover, .recent-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }
        
        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4);
            color: white;
        }
        
        .btn-secondary-custom {
            background: #f1f5f9;
            color: #064e3b;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-secondary-custom:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
            color: #064e3b;
        }
        
        .btn-dark-custom {
            background: #1f2937;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-dark-custom:hover {
            background: #374151;
            transform: translateY(-2px);
            color: white;
        }
        
        /* Status Badges */
        .status-pending { background: #fef3c7; color: #d97706; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-approved { background: #d1fae5; color: #059669; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-paid { background: #dbeafe; color: #2563eb; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-rejected { background: #fee2e2; color: #dc2626; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        
        /* Summary Items */
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .summary-item:last-child { border-bottom: none; }
        
        .summary-label { display: flex; align-items: center; gap: 10px; }
        .summary-value { font-weight: 700; color: #064e3b; font-size: 18px; }
        
        /* Table */
        .table-custom { margin-bottom: 0; }
        .table-custom th { color: #064e3b; font-weight: 600; border-bottom: 2px solid #e5e7eb; }
        .table-custom td { vertical-align: middle; padding: 12px 8px; }
        
        .btn-view {
            background: #10b981;
            color: white;
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        
        .btn-view:hover {
            background: #059669;
            transform: translateY(-2px);
            color: white;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in { animation: fadeIn 0.5s ease-out; }
        hr { border-color: #e5e7eb; }
        
        .badge-number {
            background: #ef4444;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
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
                    <a class="nav-link active" href="dashboard_Finance.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="All_Claim_Finance.php">
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
                                <i class="fas fa-chart-line me-2" style="color: #10b981;"></i>
                                Finance Dashboard
                            </h3>
                            <p class="mb-0 opacity-75">Manage and review all staff claims</p>
                        </div>
                        <div class="mt-2 mt-sm-0">
                            <i class="fas fa-user-circle me-1"></i> 
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </div>
                    </div>
                </div>
 
                <!-- Stats Cards with Individual Hover Colors -->
                <div class="row g-4 mb-4 fade-in">
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card stat-card-total text-center">
                            <div class="stat-icon mx-auto"><i class="fas fa-file-invoice"></i></div>
                            <div class="stat-number"><?php echo $total; ?></div>
                            <div class="stat-label">Total Claims</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card stat-card-pending text-center">
                            <div class="stat-icon mx-auto"><i class="fas fa-clock"></i></div>
                            <div class="stat-number"><?php echo $pending; ?></div>
                            <div class="stat-label">Pending Approval</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card stat-card-approved text-center">
                            <div class="stat-icon mx-auto"><i class="fas fa-hourglass-half"></i></div>
                            <div class="stat-number"><?php echo $approved; ?></div>
                            <div class="stat-label">Pending Payment</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card stat-card-completed text-center">
                            <div class="stat-icon mx-auto"><i class="fas fa-check-circle"></i></div>
                            <div class="stat-number"><?php echo $completed; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </div>
                </div>
 
                <!-- Payment Alert -->
                <?php if($approved > 0): ?>
                <div class="payment-alert fade-in">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <i class="fas fa-exclamation-circle me-2" style="color: #f59e0b;"></i>
                            <strong><?php echo $approved; ?> claim(s)</strong> approved and awaiting payment —
                            Total: <strong>RM <?php echo number_format($pending_amount, 2); ?></strong>
                        </div>
                        <a href="All_Claim_Finance.php?status=Approved" class="btn btn-sm mt-2 mt-sm-0" style="background: #f59e0b; color: white; border-radius: 10px;">
                            <i class="fas fa-money-bill me-1"></i> Process Payment
                        </a>
                    </div>
                </div>
                <?php endif; ?>
 
                <div class="row g-4 fade-in">
                    <!-- Quick Actions Column -->
                    <div class="col-md-5">
                        <div class="action-card">
                            <div class="card-body p-4">
                                <h5 style="color: #064e3b;">
                                    <i class="fas fa-bolt me-2" style="color: #10b981;"></i>
                                    Quick Actions
                                </h5>
                                <hr>
                                <a href="All_Claim_Finance.php?status=Pending" class="btn btn-primary-custom w-100 mb-3">
                                    <i class="fas fa-clock me-2"></i>Review Pending Claims
                                    <?php if($pending > 0): ?>
                                        <span class="badge-number"><?php echo $pending; ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="All_Claim_Finance.php" class="btn btn-secondary-custom w-100 mb-3">
                                    <i class="fas fa-list me-2"></i>View All Claims
                                </a>
                                <a href="Export_Report_Finance.php" class="btn btn-dark-custom w-100">
                                    <i class="fas fa-download me-2"></i>Export Monthly Report
                                </a>
                            </div>
                        </div>
 
                        <!-- Claims Summary Card -->
                        <div class="summary-card mt-4">
                            <div class="card-body p-4">
                                <h5 style="color: #064e3b;">
                                    <i class="fas fa-chart-pie me-2" style="color: #10b981;"></i>
                                    Claims Summary
                                </h5>
                                <hr>
                                <div class="summary-item">
                                    <div class="summary-label"><span class="status-pending">Pending</span></div>
                                    <div class="summary-value"><?php echo $pending; ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label"><span class="status-approved">Approved</span></div>
                                    <div class="summary-value"><?php echo $approved; ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label"><span class="status-paid">Paid</span></div>
                                    <div class="summary-value"><?php echo $paid; ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label"><span class="status-rejected">Rejected</span></div>
                                    <div class="summary-value"><?php echo $rejected; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
 
                    <!-- Recent Claims Column -->
                    <div class="col-md-7">
                        <div class="recent-card">
                            <div class="card-body p-4">
                                <h5 style="color: #064e3b;">
                                    <i class="fas fa-clock me-2" style="color: #10b981;"></i>
                                    Recent Claims
                                </h5>
                                <hr>
                                <div class="table-responsive">
                                    <table class="table table-custom">
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
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                                                    No claims found
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                            <?php foreach($recent_claims as $rc): ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($rc['name']); ?></td>
                                                <td><?php echo htmlspecialchars($rc['claim_type']); ?></td>
                                                <td class="fw-bold" style="color: #064e3b;">RM <?php echo number_format($rc['amount'], 2); ?></td>
                                                <td><span class="status-<?php echo strtolower($rc['status']); ?>"><?php echo ucfirst($rc['status']); ?></span></td>
                                                <td>
                                                    <a href="Claim_details_Finance.php?id=<?php echo $rc['id']; ?>" class="btn btn-view btn-sm">
                                                        <i class="fas fa-eye me-1"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <a href="All_Claim_Finance.php" class="btn btn-secondary-custom w-100 mt-3">
                                    <i class="fas fa-arrow-right me-2"></i>View All Claims
                                </a>
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