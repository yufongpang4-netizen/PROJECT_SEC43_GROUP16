<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: ../login.php");
    exit();
}
 
require_once '../db.php';
 
// ─── Date filter ────────────────────────────────────────────────────
$date_from = $_GET['date_from'] ?? date('Y-m-01');          // 1st of current month
$date_to   = $_GET['date_to']   ?? date('Y-m-t');           // last day of current month
$status_f  = $_GET['status']    ?? 'All';                   
 
// ─── Build query ────────────────────────────────────────────────────
$where = "WHERE c.submitted_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
$params = [$date_from, $date_to];
$types  = 'ss';
if($status_f !== 'All') {
    $where .= " AND c.status = ?";
    $params[] = $status_f;
    $types   .= 's';
}
 
$stmt = $conn->prepare("
    SELECT c.id, c.claim_type, c.amount, c.expense_date, c.status,
           c.submitted_at, c.finance_comment,
           u.name AS staff, u.staff_id, u.department, u.email
    FROM claims c
    JOIN users u ON c.user_id = u.id
    $where
    ORDER BY c.submitted_at DESC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
 
$total_amount = array_sum(array_column($claims, 'amount'));
 
// ─── CSV Export ─────────────────────────────────────────────────────
if(isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="UTMSpace_Claims_Report_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Claim ID','Submitted Date','Staff Name','Staff ID','Department','Email','Claim Type','Amount (RM)','Expense Date','Status','Finance Remark']);
    foreach($claims as $c) {
        fputcsv($out, [
            $c['id'],
            date('d/m/Y', strtotime($c['submitted_at'])),
            $c['staff'],
            $c['staff_id'],
            $c['department'],
            $c['email'],
            $c['claim_type'],
            number_format($c['amount'], 2),
            $c['expense_date'] ? date('d/m/Y', strtotime($c['expense_date'])) : '',
            ucfirst($c['status']),
            $c['finance_comment'] ?? ''
        ]);
    }
    fputcsv($out, ['','','','','','','','TOTAL: RM ' . number_format($total_amount, 2),'','','']);
    fclose($out);
    exit();
}
 
// ─── HTML / Print-PDF Export ─────────────────────────────────────────
if(isset($_GET['export']) && $_GET['export'] === 'pdf') {
    ?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>UTMSPACE Claims Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #1e293b; background: white; }
        .report-container { padding: 30px; }
        h2 { text-align: center; color: #064e3b; margin-bottom: 5px; font-size: 22px; }
        .subtitle { text-align: center; color: #6b7280; font-size: 12px; margin-bottom: 20px; }
        .info-box { background: #ecfdf5; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { background: #064e3b; color: white; padding: 10px 8px; text-align: left; font-weight: 600; }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) { background: #f9fafb; }
        .total-row { font-weight: bold; background: #d1fae5; }
        .footer { text-align: center; margin-top: 25px; color: #9ca3af; font-size: 10px; border-top: 1px solid #e5e7eb; padding-top: 15px; }
        .status-badge { padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; display: inline-block; }
        .status-Pending { background: #fef3c7; color: #d97706; }
        .status-Approved { background: #d1fae5; color: #059669; }
        .status-Paid { background: #dbeafe; color: #2563eb; }
        .status-Rejected { background: #fee2e2; color: #dc2626; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
<div class="no-print" style="padding:15px; background:#f3f4f6; text-align:center; border-bottom:1px solid #ddd;">
    <button onclick="window.print()" style="padding:10px 25px; background:#064e3b; color:white; border:none; border-radius:8px; cursor:pointer; margin-right:10px;">
        🖨️ Print / Save as PDF
    </button>
    <button onclick="window.close()" style="padding:10px 25px; background:#6b7280; color:white; border:none; border-radius:8px; cursor:pointer;">
        ✕ Close
    </button>
</div>
<div class="report-container">
    <h2>UTMSPACE Staff Pay & Claim System</h2>
    <div class="subtitle">Claims Report</div>
    
    <div class="info-box">
        <strong>Period:</strong> <?php echo date('d M Y', strtotime($date_from)); ?> – <?php echo date('d M Y', strtotime($date_to)); ?> &nbsp;|&nbsp;
        <strong>Status:</strong> <?php echo $status_f === 'All' ? 'All' : ucfirst($status_f); ?> &nbsp;|&nbsp;
        <strong>Generated:</strong> <?php echo date('d M Y, h:i A'); ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>#</th><th>Staff Name</th><th>Staff ID</th><th>Dept</th>
                <th>Type</th><th>Amount (RM)</th>
                <th>Expense Date</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($claims as $i => $c): ?>
            <tr>
                <td><?php echo $i+1; ?></td>
                <td><?php echo htmlspecialchars($c['staff']); ?></td>
                <td><?php echo htmlspecialchars($c['staff_id']); ?></td>
                <td><?php echo htmlspecialchars($c['department']); ?></td>
                <td><?php echo htmlspecialchars($c['claim_type']); ?></td>
                <td>RM <?php echo number_format($c['amount'],2); ?></td>
                <td><?php echo $c['expense_date'] ? date('d/m/Y',strtotime($c['expense_date'])) : '-'; ?></td>
                <td><span class="status-badge status-<?php echo ucfirst($c['status']); ?>"><?php echo ucfirst($c['status']); ?></span></td>
            </tr>
        <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="5" style="text-align:right;">TOTAL: </td>
                <td colspan="3">RM <?php echo number_format($total_amount, 2); ?></td>
            </tr>
        </tbody>
    </table>
    <div class="footer">
        Total <?php echo count($claims); ?> record(s) | UTMSPACE Staff Pay & Claim System
    </div>
</div>
<script>
    setTimeout(function(){ window.print(); }, 500);
</script>
</body>
</html>
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Report - Finance | UTMSPACE</title>
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
        
        /* Filter Card */
        .filter-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #d1fae5;
        }
        
        .form-label {
            font-weight: 600;
            color: #064e3b;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .btn-apply {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4);
            color: white;
        }
        
        /* Export Cards */
        .export-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            text-align: center;
            height: 100%;
        }
        
        .export-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .export-icon {
            font-size: 55px;
            margin-bottom: 15px;
        }
        
        .btn-csv {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-csv:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4);
            color: white;
        }
        
        .btn-pdf {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(239, 68, 68, 0.4);
            color: white;
        }
        
        /* Preview Table */
        .preview-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .table-custom {
            margin-bottom: 0;
        }
        
        .table-custom thead {
            background: #f1f5f9;
        }
        
        .table-custom th {
            color: #064e3b;
            font-weight: 600;
            padding: 12px 15px;
            border: none;
        }
        
        .table-custom td {
            padding: 10px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #eef2ff;
        }
        
        .table-custom tr:hover {
            background: #f8fafc;
        }
        
        /* Status Badges */
        .status-pending { background: #fef3c7; color: #d97706; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .status-approved { background: #d1fae5; color: #059669; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .status-paid { background: #dbeafe; color: #2563eb; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .status-rejected { background: #fee2e2; color: #dc2626; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        
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
        
        .record-badge {
            background: #d1fae5;
            color: #064e3b;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        tfoot {
            background: #f8fafc;
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
                    <a class="nav-link" href="All_Claim_Finance.php">
                        <i class="fas fa-file-invoice me-2"></i> All Claims
                    </a>
                    <a class="nav-link active" href="Export_Report_Finance.php">
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
                                <i class="fas fa-download me-2" style="color: #10b981;"></i>
                                Export Claim Report
                            </h3>
                            <p class="mb-0 opacity-75">Generate and download claim reports in multiple formats</p>
                        </div>
                    </div>
                </div>
 
                <!-- Filter Bar -->
                <div class="filter-card fade-in">
                    <form method="GET" action="Export_Report_Finance.php" id="filterForm">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">
                                    <i class="fas fa-calendar me-1" style="color: #10b981;"></i>Date From
                                </label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">
                                    <i class="fas fa-calendar me-1" style="color: #10b981;"></i>Date To
                                </label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">
                                    <i class="fas fa-filter me-1" style="color: #10b981;"></i>Status
                                </label>
                                <select name="status" class="form-select">
                                    <option value="All"      <?php echo $status_f=='All'      ?'selected':''; ?>>All Claims</option>
                                    <option value="Pending"  <?php echo $status_f=='Pending'  ?'selected':''; ?>>Pending</option>
                                    <option value="Approved" <?php echo $status_f=='Approved' ?'selected':''; ?>>Approved</option>
                                    <option value="Paid"     <?php echo $status_f=='Paid'     ?'selected':''; ?>>Paid</option>
                                    <option value="Rejected" <?php echo $status_f=='Rejected' ?'selected':''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-apply w-100">
                                    <i class="fas fa-filter me-2"></i>Apply Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
 
                <!-- Export Options -->
                <div class="row g-4 mb-4 fade-in">
                    <div class="col-md-6">
                        <div class="export-card">
                            <div class="card-body p-4">
                                <div class="export-icon">
                                    <i class="fas fa-file-csv" style="color: #10b981;"></i>
                                </div>
                                <h4 style="color: #064e3b;">Export to CSV / Excel</h4>
                                <p class="text-muted">Download claim data as CSV — open in Excel or Google Sheets</p>
                                <a href="Export_Report_Finance.php?export=csv&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&status=<?php echo urlencode($status_f); ?>"
                                   class="btn btn-csv">
                                    <i class="fas fa-download me-2"></i>Download CSV
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="export-card">
                            <div class="card-body p-4">
                                <div class="export-icon">
                                    <i class="fas fa-file-pdf" style="color: #dc2626;"></i>
                                </div>
                                <h4 style="color: #064e3b;">Export to PDF</h4>
                                <p class="text-muted">Open print-ready report — use browser's Print → Save as PDF</p>
                                <a href="Export_Report_Finance.php?export=pdf&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&status=<?php echo urlencode($status_f); ?>"
                                   target="_blank" class="btn btn-pdf">
                                    <i class="fas fa-print me-2"></i>Print / Save PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
 
                <!-- Report Preview -->
                <div class="preview-card fade-in">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                            <h5 style="color: #064e3b;" class="mb-2 mb-sm-0">
                                <i class="fas fa-chart-line me-2" style="color: #10b981;"></i>
                                Report Preview
                            </h5>
                            <span class="record-badge">
                                <i class="fas fa-list me-1"></i> <?php echo count($claims); ?> record(s)
                                <span class="mx-1">|</span>
                                <i class="fas fa-calendar me-1"></i> <?php echo date('d M Y', strtotime($date_from)); ?> – <?php echo date('d M Y', strtotime($date_to)); ?>
                            </span>
                        </div>
                        <hr>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Submitted</th>
                                        <th>Staff Name</th>
                                        <th>Dept</th>
                                        <th>Type</th>
                                        <th>Amount (RM)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($claims)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                                            <p class="text-muted mb-0">No claims found for this period.</p>
                                            <small class="text-muted">Try adjusting your date range or status filter</small>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach($claims as $i => $c): ?>
                                    <tr>
                                        <td><?php echo $i+1; ?></td>
                                        <td><?php echo date('d M Y', strtotime($c['submitted_at'])); ?></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($c['staff']); ?></td>
                                        <td><?php echo htmlspecialchars($c['department']); ?></td>
                                        <td><?php echo htmlspecialchars($c['claim_type']); ?></td>
                                        <td class="fw-bold" style="color: #064e3b;">RM <?php echo number_format($c['amount'], 2); ?></td>
                                        <td><span class="status-<?php echo strtolower($c['status']); ?>"><?php echo ucfirst($c['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tfoot>
                                        <tr style="background: #f1f5f9;">
                                            <td colspan="5" class="fw-bold text-end">Total Amount:</td>
                                            <td colspan="2" class="fw-bold" style="color: #10b981; font-size: 16px;">
                                                RM <?php echo number_format($total_amount, 2); ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>