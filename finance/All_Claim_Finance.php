<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: ../login.php");
    exit();
}

$status_filter = $_GET['status'] ?? 'All';
$mock_claims = [
    ['id' => 1, 'staff' => 'John Staff', 'staff_id' => 'STF001', 'type' => 'Travel', 'amount' => 150.00, 'date' => '2026-04-15', 'status' => 'Pending', 'department' => 'IT'],
    ['id' => 2, 'staff' => 'Sarah Smith', 'staff_id' => 'STF002', 'type' => 'Meal', 'amount' => 45.50, 'date' => '2026-04-14', 'status' => 'Pending', 'department' => 'HR'],
    ['id' => 3, 'staff' => 'Mike Johnson', 'staff_id' => 'STF003', 'type' => 'Office Supplies', 'amount' => 200.00, 'date' => '2026-04-13', 'status' => 'Approved', 'department' => 'IT'],
    ['id' => 4, 'staff' => 'Lisa Wong', 'staff_id' => 'STF004', 'type' => 'Travel', 'amount' => 320.00, 'date' => '2026-04-12', 'status' => 'Paid', 'department' => 'Marketing'],
    ['id' => 5, 'staff' => 'Ahmad Razi', 'staff_id' => 'STF005', 'type' => 'Transportation', 'amount' => 75.00, 'date' => '2026-04-11', 'status' => 'Rejected', 'department' => 'Operations'],
];

$filtered_claims = $mock_claims;
if($status_filter != 'All') {
    $filtered_claims = array_filter($mock_claims, function($claim) use ($status_filter) {
        return $claim['status'] == $status_filter;
    });
}
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
                        All Submitted Claims
                    </h2>
                </div>
                
                <!-- Filter Bar -->
                <div class="filter-bar">
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-filter me-1" style="color: #5BC0BE;"></i>Filter by Status
                            </label>
                            <select id="statusFilter" class="form-select" onchange="window.location.href='?status='+this.value">
                                <option value="All" <?php echo $status_filter == 'All' ? 'selected' : ''; ?>>All Claims</option>
                                <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Approved" <?php echo $status_filter == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="Paid" <?php echo $status_filter == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-search me-1" style="color: #5BC0BE;"></i>Search Staff
                            </label>
                            <input type="text" id="searchInput" class="form-control" placeholder="Type staff name...">
                        </div>
                        <div class="col-md-2">
                            <button class="btn w-100" style="background: #5BC0BE; color: #0B132B;" onclick="window.location.href='all_claims.php'">
                                <i class="fas fa-sync-alt me-1"></i>Reset
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Claims Table -->
                <div class="card border-0 shadow-lg fade-in">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-custom mb-0" id="claimsTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
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
                                    <?php foreach($filtered_claims as $claim): ?>
                                    <tr>
                                        <td><?php echo $claim['date']; ?></td>
                                        <td><?php echo $claim['staff']; ?></td>
                                        <td><?php echo $claim['staff_id']; ?></td>
                                        <td><?php echo $claim['department']; ?></td>
                                        <td><?php echo $claim['type']; ?></td>
                                        <td class="fw-bold">RM <?php echo number_format($claim['amount'], 2); ?></td>
                                        <td>
                                            <span class="status-<?php echo strtolower($claim['status']); ?>">
                                                <?php echo $claim['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="claim_details.php?id=<?php echo $claim['id']; ?>" class="btn btn-sm" style="background: #5BC0BE; color: #0B132B;">
                                                <i class="fas fa-eye"></i> Review
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let searchValue = this.value.toLowerCase();
            let rows = document.querySelectorAll('#claimsTable tbody tr');
            rows.forEach(row => {
                let staffName = row.cells[1].textContent.toLowerCase();
                row.style.display = staffName.includes(searchValue) ? '' : 'none';
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
