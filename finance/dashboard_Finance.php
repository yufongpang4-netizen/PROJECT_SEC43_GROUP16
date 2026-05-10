
<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: ../login.php");
    exit();
}
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
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-chart-line fs-1" style="color: #5BC0BE;"></i>
                    <h5 class="mt-2">UTMSpace</h5>
                    <small>Finance Portal</small>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="all_claims.php">
                        <i class="fas fa-file-invoice"></i> All Claims
                    </a>
                    <a class="nav-link" href="export_report.php">
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
                        <i class="fas fa-chart-line me-2" style="color: #5BC0BE;"></i>
                        Finance Dashboard
                    </h2>
                    <div class="text-white">
                        <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['user_name']; ?>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="stat-number">124</div>
                            <div class="stat-label">Total Claims</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-number">18</div>
                            <div class="stat-label">Pending Approval</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="stat-number">7</div>
                            <div class="stat-label">Pending Payment</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-number">99</div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions & Recent Claims -->
                <div class="row">
                    <div class="col-md-5">
                        <div class="card border-0 shadow-lg fade-in">
                            <div class="card-body p-4">
                                <h5 style="color: #0B132B;">
                                    <i class="fas fa-bolt me-2" style="color: #5BC0BE;"></i>
                                    Quick Actions
                                </h5>
                                <hr>
                                <a href="all_claims.php?status=Pending" class="btn w-100 mb-2" style="background: #5BC0BE; color: #0B132B; border-radius: 10px;">
                                    <i class="fas fa-clock me-2"></i>Review Pending Claims
                                </a>
                                <a href="all_claims.php" class="btn w-100 mb-2" style="background: #3A506B; color: white; border-radius: 10px;">
                                    <i class="fas fa-list me-2"></i>View All Claims
                                </a>
                                <a href="export_report.php" class="btn w-100" style="background: #1C2541; color: white; border-radius: 10px;">
                                    <i class="fas fa-download me-2"></i>Export Monthly Report
                                </a>
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
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>John Staff</td>
                                                <td>RM 150.00</td>
                                                <td><span class="status-pending">Pending</span></td>
                                                <td><a href="claim_details.php?id=1" class="btn btn-sm" style="background:#5BC0BE; color:#0B132B;">Review</a></td>
                                            </tr>
                                            <tr>
                                                <td>Sarah Smith</td>
                                                <td>RM 45.50</td>
                                                <td><span class="status-pending">Pending</span></td>
                                                <td><a href="claim_details.php?id=2" class="btn btn-sm" style="background:#5BC0BE; color:#0B132B;">Review</a></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
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
