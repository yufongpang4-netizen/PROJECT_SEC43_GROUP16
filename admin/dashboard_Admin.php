<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - UTMSpace</title>
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
                    <i class="fas fa-user-shield fs-1" style="color: #5BC0BE;"></i>
                    <h5 class="mt-2">UTMSpace</h5>
                    <small>Admin Portal</small>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="manage_users.php">
                        <i class="fas fa-users"></i> Manage Accounts
                    </a>
                    <a class="nav-link" href="generate_report.php">
                        <i class="fas fa-chart-bar"></i> Generate Report
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
                        <i class="fas fa-tachometer-alt me-2" style="color: #5BC0BE;"></i>
                        Admin Dashboard
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
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number">45</div>
                            <div class="stat-label">Total Staff</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="stat-number">3</div>
                            <div class="stat-label">Finance Staff</div>
                        </div>
                    </div>
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
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-number">RM 28,450</div>
                            <div class="stat-label">Total Paid</div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-lg fade-in">
                            <div class="card-body p-4">
                                <h5 style="color: #0B132B;">
                                    <i class="fas fa-chart-pie me-2" style="color: #5BC0BE;"></i>
                                    Claims by Department
                                </h5>
                                <hr>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Department</th>
                                                <th>Claims</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>IT</td>
                                                <td>35</td>
                                                <td>RM 12,500</td>
                                            </tr>
                                            <tr>
                                                <td>HR</td>
                                                <td>28</td>
                                                <td>RM 8,200</td>
                                            </tr>
                                            <tr>
                                                <td>Finance</td>
                                                <td>22</td>
                                                <td>RM 15,300</td>
                                            </tr>
                                            <tr>
                                                <td>Marketing</td>
                                                <td>39</td>
                                                <td>RM 18,750</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-0 shadow-lg fade-in">
                            <div class="card-body p-4">
                                <h5 style="color: #0B132B;">
                                    <i class="fas fa-bell me-2" style="color: #5BC0BE;"></i>
                                    Recent Activity
                                </h5>
                                <hr>
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="fas fa-check-circle me-2" style="color: #48bb78;"></i> New claim submitted by John Staff</li>
                                    <li class="mb-2"><i class="fas fa-check-circle me-2" style="color: #48bb78;"></i> Claim approved by Finance</li>
                                    <li class="mb-2"><i class="fas fa-user-plus me-2" style="color: #5BC0BE;"></i> New staff registered: Sarah Smith</li>
                                    <li class="mb-2"><i class="fas fa-dollar-sign me-2" style="color: #5BC0BE;"></i> Payment made to Lisa Wong</li>
                                    <li><i class="fas fa-chart-line me-2" style="color: #ed8936;"></i> Monthly report generated</li>
                                </ul>
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
