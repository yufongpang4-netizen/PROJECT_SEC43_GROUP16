<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: ../login.php");
    exit();
}
 
require_once '../db.php';
 
$status_filter = $_GET['status'] ?? 'All';
$search        = $_GET['search'] ?? '';
 
$where_parts = [];
$params      = [];
$types       = '';
 
if($status_filter !== 'All') {
    $where_parts[] = "c.status = ?";
    $params[]      = $status_filter;
    $types        .= 's';
}
 
if(!empty($search)) {
    $where_parts[] = "(u.name LIKE ? OR u.staff_id LIKE ?)";
    $params[]      = "%$search%";
    $params[]      = "%$search%";
    $types        .= 'ss';
}
 
$where_sql = $where_parts ? "WHERE " . implode(" AND ", $where_parts) : "";
 
$sql = "
    SELECT c.id, u.name AS staff, u.staff_id, u.department, c.claim_type, c.amount, c.expense_date, c.status, c.submitted_at
    FROM claims c
    JOIN users u ON c.user_id = u.id
    $where_sql
    ORDER BY c.submitted_at DESC
";
 
$stmt = $conn->prepare($sql);
if($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
 
// Count per status for filter badges
$counts = [];
$count_result = $conn->query("SELECT status, COUNT(*) as c FROM claims GROUP BY status");
while($row = $count_result->fetch_assoc()) {
    $counts[$row['status']] = $row['c'];
}
$counts['All'] = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Claims - Finance | UTMSPACE</title>
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
        
        html, body { height: 100%; margin: 0; padding: 0; }
        
        body {
            background: var(--finance-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        .container-fluid { height: 100%; overflow: hidden; }
        .row.g-0 { height: 100%; }
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #064e3b 0%, #047857 100%);
            height: 100vh;
            color: white;
            transition: all 0.3s ease;
            overflow-y: auto;
            position: sticky;
            top: 0;
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
        
        .main-content {
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
        }
        
        .main-content::-webkit-scrollbar { width: 8px; }
        .main-content::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 10px; }
        .main-content::-webkit-scrollbar-thumb { background: #10b981; border-radius: 10px; }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #064e3b 0%, #047857 100%);
            border-radius: 20px;
            padding: 20px 25px;
            color: white;
            margin-bottom: 25px;
        }
        
        /* Filter Bar */
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
        
        .btn-search {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4);
            color: white;
        }
        
        .btn-reset {
            background: #f1f5f9;
            color: #064e3b;
            border: none;
            border-radius: 12px;
            padding: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-reset:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
            color: #064e3b;
        }
        
        /* Table Card */
        .table-card {
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
            padding: 15px;
            border: none;
        }
        
        .table-custom td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #eef2ff;
        }
        
        .table-custom tr:hover {
            background: #f8fafc;
        }
        
        /* Status Badges */
        .status-pending { background: #fef3c7; color: #d97706; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-approved { background: #d1fae5; color: #059669; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-paid { background: #dbeafe; color: #2563eb; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-rejected { background: #fee2e2; color: #dc2626; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        
        /* Review Button */
        .btn-review {
            background: #10b981;
            color: white;
            border-radius: 8px;
            padding: 6px 15px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-review:hover {
            background: #059669;
            transform: translateY(-2px);
            color: white;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px;
        }
        
        .empty-icon {
            font-size: 60px;
            color: #d1d5db;
            margin-bottom: 20px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        .record-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        tfoot {
            background: #f8fafc;
        }
        
        tfoot td {
            font-weight: 700;
            color: #064e3b;
        }
        
        @media (max-width: 768px) {
            .sidebar { height: auto; position: relative; }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-3">
                    <div class="text-center mb-4">
                        <i class="fas fa-chart-line fs-1" style="color: #10b981;"></i>
                        <h5 class="mt-2">UTMSPACE</h5>
                        <small>Finance Portal</small>
                    </div>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard_Finance.php">
                            <i class="fas fa-tachometer-alt fa-fw me-2"></i> Dashboard
                        </a>
                        <a class="nav-link active" href="All_Claim_Finance.php">
                            <i class="fas fa-file-invoice fa-fw me-2"></i> All Claims
                        </a>
                        <a class="nav-link" href="Export_Report_Finance.php">
                            <i class="fas fa-download fa-fw me-2"></i> Export Report
                        </a>
                        <hr style="border-color: rgba(255,255,255,0.2);">
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>
 
            <div class="col-md-9 col-lg-10 main-content">
                <div class="page-header fade-in">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h3 class="mb-1">
                                <i class="fas fa-file-invoice me-2" style="color: #10b981;"></i>
                                All Submitted Claims
                            </h3>
                            <p class="mb-0 opacity-75">Review and manage all staff claims</p>
                        </div>
                        <span class="record-badge mt-2 mt-sm-0">
                            <i class="fas fa-list me-1"></i> <?php echo count($claims); ?> record(s) found
                        </span>
                    </div>
                </div>
 
                <div class="filter-card fade-in">
                    <form method="GET" action="All_Claim_Finance.php">
                        <div class="row align-items-end g-3">
                            <div class="col-md-3">
                                <label class="form-label">
                                    <i class="fas fa-filter me-1" style="color: #10b981;"></i>Filter by Status
                                </label>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="All"      <?php echo $status_filter == 'All'      ? 'selected' : ''; ?>>All Claims (<?php echo $counts['All'] ?? 0; ?>)</option>
                                    <option value="Pending"  <?php echo $status_filter == 'Pending'  ? 'selected' : ''; ?>>Pending (<?php echo $counts['Pending'] ?? 0; ?>)</option>
                                    <option value="Approved" <?php echo $status_filter == 'Approved' ? 'selected' : ''; ?>>Approved (<?php echo $counts['Approved'] ?? 0; ?>)</option>
                                    <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected (<?php echo $counts['Rejected'] ?? 0; ?>)</option>
                                    <option value="Paid"     <?php echo $status_filter == 'Paid'     ? 'selected' : ''; ?>>Paid (<?php echo $counts['Paid'] ?? 0; ?>)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">
                                    <i class="fas fa-search me-1" style="color: #10b981;"></i>Search Staff
                                </label>
                                <input type="text" name="search" class="form-control" placeholder="Name or Staff ID..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-search w-100">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="All_Claim_Finance.php" class="btn btn-reset w-100">
                                    <i class="fas fa-sync-alt me-1"></i>Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
 
                <div class="table-card fade-in">
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Submitted</th>
                                    <th>Staff Name</th>
                                    <th>Staff ID</th>
                                    <th>Department</th>
                                    <th>Claim Type</th>
                                    <th>Amount (RM)</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($claims)): ?>
                                <tr>
                                    <td colspan="9" class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-inbox"></i>
                                        </div>
                                        <h5 style="color: #064e3b;">No claims found</h5>
                                        <p class="text-muted">Try adjusting your search or filter criteria</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach($claims as $i => $claim): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $i + 1; ?></td>
                                    <td><?php echo date('d M Y', strtotime($claim['submitted_at'])); ?></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($claim['staff']); ?></td>
                                    <td><code><?php echo htmlspecialchars($claim['staff_id']); ?></code></td>
                                    <td><?php echo htmlspecialchars($claim['department'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($claim['claim_type']); ?></td>
                                    <td class="fw-bold" style="color: #064e3b;">RM <?php echo number_format($claim['amount'], 2); ?></td>
                                    <td>
                                        <span class="status-<?php echo strtolower($claim['status']); ?>">
                                            <?php echo ucfirst($claim['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="Claim_details_Finance.php?id=<?php echo $claim['id']; ?>" class="btn-review">
                                            <i class="fas fa-eye me-1"></i> Review
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <?php if(!empty($claims)): ?>
                            <tfoot>
                                <tr>
                                    <td colspan="6" class="fw-bold text-end">Total Amount:</td>
                                    <td colspan="3" class="fw-bold" style="color: #10b981; font-size: 18px;">
                                        RM <?php echo number_format(array_sum(array_column($claims, 'amount')), 2); ?>
                                    </td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>