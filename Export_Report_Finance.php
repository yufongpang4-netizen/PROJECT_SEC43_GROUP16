<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: ../login.php");
    exit();
}

$report_type = $_GET['type'] ?? '';
$message = '';

if($report_type == 'excel') {
    $message = "Excel report would be generated here with actual database data.";
} elseif($report_type == 'pdf') {
    $message = "PDF report would be generated here with actual database data.";
}

$mock_claims = [
    ['date' => '2026-04-15', 'staff' => 'John Staff', 'type' => 'Travel', 'amount' => 150.00, 'status' => 'Pending'],
    ['date' => '2026-04-14', 'staff' => 'Sarah Smith', 'type' => 'Meal', 'amount' => 45.50, 'status' => 'Pending'],
    ['date' => '2026-04-13', 'staff' => 'Mike Johnson', 'type' => 'Office', 'amount' => 200.00, 'status' => 'Approved'],
    ['date' => '2026-04-12', 'staff' => 'Lisa Wong', 'type' => 'Travel', 'amount' => 320.00, 'status' => 'Paid'],
];
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
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="all_claims.php">
                        <i class="fas fa-file-invoice"></i> All Claims
                    </a>
                    <a class="nav-link active" href="export_report.php">
                        <i class="fas fa-download"></i> Export Report
                    </a>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="logout.php">
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
                
                <?php if($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Export Options -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-lg text-center card-hover">
                            <div class="card-body p-5">
                                <i class="fas fa-file-excel" style="font-size: 60px; color: #5BC0BE;"></i>
                                <h4 class="mt-3">Export to Excel</h4>
                                <p class="text-muted">Generate claim summary report in Excel format (.xlsx)</p>
                                <a href="?type=excel" class="btn" style="background: #48bb78; color: white;">
                                    <i class="fas fa-download me-2"></i>Download Excel
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-lg text-center card-hover">
                            <div class="card-body p-5">
                                <i class="fas fa-file-pdf" style="font-size: 60px; color: #e53e3e;"></i>
                                <h4 class="mt-3">Export to PDF</h4>
                                <p class="text-muted">Generate claim summary report in PDF format</p>
                                <a href="?type=pdf" class="btn btn-danger">
                                    <i class="fas fa-download me-2"></i>Download PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Report Preview -->
                <div class="card border-0 shadow-lg fade-in">
                    <div class="card-body p-4">
                        <h5 style="color: #0B132B;">
                            <i class="fas fa-chart-line me-2" style="color: #5BC0BE;"></i>
                            Report Preview (Last 30 Days)
                        </h5>
                        <hr>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Staff Name</th>
                                        <th>Claim Type</th>
                                        <th>Amount (RM)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total = 0;
                                    foreach($mock_claims as $claim): 
                                        $total += $claim['amount'];
                                    ?>
                                    <tr>
                                        <td><?php echo $claim['date']; ?></td>
                                        <td><?php echo $claim['staff']; ?></td>
                                        <td><?php echo $claim['type']; ?></td>
                                        <td><?php echo number_format($claim['amount'], 2); ?></td>
                                        <td><span class="status-<?php echo strtolower($claim['status']); ?>"><?php echo $claim['status']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr style="background: #f8f9fa;">
                                        <td colspan="3" class="fw-bold text-end">Total Amount:</td>
                                        <td colspan="2" class="fw-bold" style="color: #5BC0BE;">RM <?php echo number_format($total, 2); ?></td>
                                    </tr>
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
