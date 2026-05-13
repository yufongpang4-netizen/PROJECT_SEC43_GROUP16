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
$status_f  = $_GET['status']    ?? 'all';
 
// ─── Build query ────────────────────────────────────────────────────
$where = "WHERE c.submitted_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
$params = [$date_from, $date_to];
$types  = 'ss';
if($status_f !== 'all') {
    $where .= " AND c.status = ?";
    $params[] = $status_f;
    $types   .= 's';
}
 
$stmt = $conn->prepare("
    SELECT c.id, c.claim_title, c.claim_type, c.amount, c.claim_date, c.status,
           c.submitted_at, c.finance_remark,
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
    // Header row
    fputcsv($out, ['Claim ID','Submitted Date','Staff Name','Staff ID','Department','Email','Claim Title','Claim Type','Amount (RM)','Expense Date','Status','Finance Remark']);
    foreach($claims as $c) {
        fputcsv($out, [
            $c['id'],
            date('d/m/Y', strtotime($c['submitted_at'])),
            $c['staff'],
            $c['staff_id'],
            $c['department'],
            $c['email'],
            $c['claim_title'],
            $c['claim_type'],
            number_format($c['amount'], 2),
            $c['claim_date'] ? date('d/m/Y', strtotime($c['claim_date'])) : '',
            ucfirst($c['status']),
            $c['finance_remark'] ?? ''
        ]);
    }
    // Total row
    fputcsv($out, ['','','','','','','','','TOTAL: RM ' . number_format($total_amount, 2),'','','']);
    fclose($out);
    exit();
}
 
// ─── HTML / Print-PDF Export ─────────────────────────────────────────
if(isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Render a print-ready HTML page; browser prints it to PDF
    ?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>UTMSpace Claims Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #222; }
        h2   { text-align: center; margin-bottom: 4px; }
        p.sub{ text-align: center; color: #555; margin-top:0; }
        table{ width:100%; border-collapse: collapse; margin-top:16px; }
        th   { background:#0B132B; color:white; padding:6px; text-align:left; }
        td   { padding:5px 6px; border-bottom:1px solid #ddd; }
        tr:nth-child(even){ background:#f5f5f5; }
        .total-row { font-weight:bold; background:#e8f4f8; }
        .footer{ text-align:center; margin-top:20px; color:#888; font-size:11px; }
        @media print {
            .no-print { display:none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
<div class="no-print" style="padding:10px; background:#eee; text-align:center;">
    <button onclick="window.print()" style="padding:8px 20px; background:#0B132B; color:white; border:none; border-radius:4px; cursor:pointer; margin-right:10px;">
        🖨️ Print / Save as PDF
    </button>
    <button onclick="window.close()" style="padding:8px 20px; background:#888; color:white; border:none; border-radius:4px; cursor:pointer;">
        ✕ Close
    </button>
</div>
<h2>UTMSpace Staff Pay & Claim System</h2>
<p class="sub">
    Claims Report &nbsp;|&nbsp; Period: <?php echo date('d M Y', strtotime($date_from)); ?> – <?php echo date('d M Y', strtotime($date_to)); ?>
    &nbsp;|&nbsp; Status: <?php echo $status_f === 'all' ? 'All' : ucfirst($status_f); ?>
    &nbsp;|&nbsp; Generated: <?php echo date('d M Y, h:i A'); ?>
</p>
<table>
    <thead>
        <tr>
            <th>#</th><th>Staff Name</th><th>Staff ID</th><th>Dept</th>
            <th>Claim Title</th><th>Type</th><th>Amount (RM)</th>
            <th>Expense Date</th><th>Status</th><th>Remark</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($claims as $i => $c): ?>
        <tr>
            <td><?php echo $i+1; ?></td>
            <td><?php echo htmlspecialchars($c['staff']); ?></td>
            <td><?php echo htmlspecialchars($c['staff_id']); ?></td>
            <td><?php echo htmlspecialchars($c['department']); ?></td>
            <td><?php echo htmlspecialchars($c['claim_title']); ?></td>
            <td><?php echo htmlspecialchars($c['claim_type']); ?></td>
            <td><?php echo number_format($c['amount'],2); ?></td>
            <td><?php echo $c['claim_date'] ? date('d/m/Y',strtotime($c['claim_date'])) : '-'; ?></td>
            <td><?php echo ucfirst($c['status']); ?></td>
            <td><?php echo htmlspecialchars($c['finance_remark'] ?? ''); ?></td>
        </tr>
    <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="6" style="text-align:right;">TOTAL:</td>
            <td>RM <?php echo number_format($total_amount, 2); ?></td>
            <td colspan="3"></td>
        </tr>
    </tbody>
</table>
<div class="footer">
    Total <?php echo count($claims); ?> record(s) &nbsp;|&nbsp; UTMSpace Staff Pay & Claim System
</div>
<script>
    // Auto-open print dialog after short delay
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
    <title>Export Report - Finance</title>
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
                    <a class="nav-link active" href="Export_Report_Finance.php">
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
                        <i class="fas fa-download me-2" style="color: #5BC0BE;"></i>
                        Export Claim Summary Report
                    </h2>
                </div>
 
                <!-- Filters -->
                <div class="filter-bar mb-4">
                    <form method="GET" action="Export_Report_Finance.php" id="filterForm">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Date From</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Date To</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="all"     <?php echo $status_f=='all'      ?'selected':''; ?>>All</option>
                                    <option value="pending" <?php echo $status_f=='pending'  ?'selected':''; ?>>Pending</option>
                                    <option value="approved"<?php echo $status_f=='approved' ?'selected':''; ?>>Approved</option>
                                    <option value="paid"    <?php echo $status_f=='paid'     ?'selected':''; ?>>Paid</option>
                                    <option value="rejected"<?php echo $status_f=='rejected' ?'selected':''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn w-100" style="background:#5BC0BE; color:#0B132B;">
                                    <i class="fas fa-filter me-1"></i>Apply Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
 
                <!-- Export Buttons -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-lg text-center card-hover">
                            <div class="card-body p-5">
                                <i class="fas fa-file-csv" style="font-size: 60px; color: #5BC0BE;"></i>
                                <h4 class="mt-3">Export to CSV / Excel</h4>
                                <p class="text-muted">Download claim data as CSV — open in Excel or Google Sheets</p>
                                <a href="Export_Report_Finance.php?export=csv&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&status=<?php echo urlencode($status_f); ?>"
                                   class="btn" style="background: #48bb78; color: white;">
                                    <i class="fas fa-download me-2"></i>Download CSV
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-lg text-center card-hover">
                            <div class="card-body p-5">
                                <i class="fas fa-file-pdf" style="font-size: 60px; color: #e53e3e;"></i>
                                <h4 class="mt-3">Export to PDF</h4>
                                <p class="text-muted">Open print-ready report — use browser's Print → Save as PDF</p>
                                <a href="Export_Report_Finance.php?export=pdf&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&status=<?php echo urlencode($status_f); ?>"
                                   target="_blank" class="btn btn-danger">
                                    <i class="fas fa-print me-2"></i>Print / Save PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
 
                <!-- Report Preview Table -->
                <div class="card border-0 shadow-lg fade-in">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 style="color: #0B132B;" class="mb-0">
                                <i class="fas fa-chart-line me-2" style="color: #5BC0BE;"></i>
                                Report Preview
                            </h5>
                            <small class="text-muted">
                                <?php echo date('d M Y', strtotime($date_from)); ?> – <?php echo date('d M Y', strtotime($date_to)); ?>
                                &nbsp;|&nbsp; <?php echo count($claims); ?> record(s)
                            </small>
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
                                        <th>Claim Title</th>
                                        <th>Type</th>
                                        <th>Amount (RM)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($claims)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            No claims found for this period.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach($claims as $i => $c): ?>
                                    <tr>
                                        <td><?php echo $i+1; ?></td>
                                        <td><?php echo date('d M Y', strtotime($c['submitted_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($c['staff']); ?></td>
                                        <td><?php echo htmlspecialchars($c['department']); ?></td>
                                        <td><?php echo htmlspecialchars($c['claim_title']); ?></td>
                                        <td><?php echo htmlspecialchars($c['claim_type']); ?></td>
                                        <td><?php echo number_format($c['amount'], 2); ?></td>
                                        <td><span class="status-<?php echo strtolower($c['status']); ?>"><?php echo ucfirst($c['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr style="background: #f8f9fa;">
                                        <td colspan="6" class="fw-bold text-end">Total Amount:</td>
                                        <td colspan="2" class="fw-bold" style="color: #5BC0BE;">
                                            RM <?php echo number_format($total_amount, 2); ?>
                                        </td>
                                    </tr>
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