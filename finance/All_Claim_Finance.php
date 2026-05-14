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
    <title>All Claims - Finance</title>
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
                        All Submitted Claims
                    </h2>
                    <span class="text-white opacity-75"><?php echo count($claims); ?> record(s) found</span>
                </div>
 
                <div class="filter-bar mb-4">
                    <form method="GET" action="All_Claim_Finance.php">
                        <div class="row align-items-end g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-filter me-1" style="color: #5BC0BE;"></i>Filter by Status
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
                                <label class="form-label fw-bold">
                                    <i class="fas fa-search me-1" style="color: #5BC0BE;"></i>Search Staff
                                </label>
                                <input type="text" name="search" class="form-control" placeholder="Name or Staff ID..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn w-100" style="background: #5BC0BE; color: #0B132B;">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="All_Claim_Finance.php" class="btn w-100" style="background: #3A506B; color: white;">
                                    <i class="fas fa-sync-alt me-1"></i>Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
 
                <div class="card border-0 shadow-lg fade-in">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-custom mb-0">
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
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            No claims found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach($claims as $i => $claim): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo date('d M Y', strtotime($claim['submitted_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($claim['staff']); ?></td>
                                        <td><?php echo htmlspecialchars($claim['staff_id']); ?></td>
                                        <td><?php echo htmlspecialchars($claim['department']); ?></td>
                                        <td><?php echo htmlspecialchars($claim['claim_type']); ?></td>
                                        <td class="fw-bold">RM <?php echo number_format($claim['amount'], 2); ?></td>
                                        <td>
                                            <span class="status-<?php echo strtolower($claim['status']); ?>">
                                                <?php echo ucfirst($claim['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="Claim_details_Finance.php?id=<?php echo $claim['id']; ?>" class="btn btn-sm" style="background: #5BC0BE; color: #0B132B;">
                                                <i class="fas fa-eye"></i> Review
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <?php if(!empty($claims)): ?>
                                <tfoot>
                                    <tr style="background:#f8f9fa;">
                                        <td colspan="6" class="fw-bold text-end">Total Amount:</td>
                                        <td colspan="3" class="fw-bold" style="color:#5BC0BE;">
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
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>