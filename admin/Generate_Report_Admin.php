<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$selected_dept = $_GET['department'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$generated = false;

if($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['generate'])) {
    $generated = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Report - Admin</title>
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
                    <a class="nav-link" href="dashboard_Admin.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="Manage_User_Admin.php">
                        <i class="fas fa-users"></i> Manage Accounts
                    </a>
                    <a class="nav-link" href="Generate_Report_Admin.php">
                        <i class="fas fa-chart-bar"></i> Generate Report
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
                        <i class="fas fa-chart-bar me-2" style="color: #5BC0BE;"></i>
                        Generate Claim Reports
                    </h2>
                </div>
                
                <!-- Filter Form -->
                <div class="card border-0 shadow-lg mb-4">
                    <div class="card-body p-4">
                        <h5 style="color: #0B132B;">
                            <i class="fas fa-filter me-2" style="color: #5BC0BE;"></i>
                            Filter Claims
                        </h5>
                        <hr>
                        <form method="GET">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label fw-bold">Department</label>
                                    <select name="department" class="form-select">
                                        <option value="">All Departments</option>
                                        <option value="IT" <?php echo $selected_dept == 'IT' ? 'selected' : ''; ?>>IT</option>
                                        <option value="HR" <?php echo $selected_dept == 'HR' ? 'selected' : ''; ?>>HR</option>
                                        <option value="Finance" <?php echo $selected_dept == 'Finance' ? 'selected' : ''; ?>>Finance</option>
                                        <option value="Marketing" <?php echo $selected_dept == 'Marketing' ? 'selected' : ''; ?>>Marketing</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label fw-bold">Date From</label>
                                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label fw-bold">Date To</label>
                                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                                </div>
                                <div class="col-md-3 mb-3 d-flex align-items-end">
                                    <button type="submit" name="generate" class="btn w-100" style="background: #5BC0BE; color: #0B132B;">
                                        <i class="fas fa-chart-line me-2"></i>Generate Report
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if($generated): ?>
                <!-- Report Results -->
                <div class="card border-0 shadow-lg fade-in">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 style="color: #0B132B;">
                                <i class="fas fa-chart-line me-2" style="color: #5BC0BE;"></i>
                                Claim Report
                            </h5>
                            <div>
                                <button class="btn btn-sm" style="background: #48bb78; color: white;" onclick="alert('Exporting to Excel...')">
                                    <i class="fas fa-file-excel me-1"></i> Excel
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="alert('Exporting to PDF...')">
                                    <i class="fas fa-file-pdf me-1"></i> PDF
                                </button>
                            </div>
                        </div>
                        <hr>
                        <p><strong>Filters Applied:</strong> 
                            Department: <?php echo $selected_dept ?: 'All'; ?> | 
                            Date: <?php echo $date_from ?: 'Start'; ?> to <?php echo $date_to ?: 'End'; ?>
                        </p>
                        
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Staff Name</th>
                                        <th>Department</th>
                                        <th>Claim Type</th>
                                        <th>Amount (RM)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>2026-04-15</td>
                                        <td>John Staff</td>
                                        <td>IT</td>
                                        <td>Travel</td>
                                        <td>150.00</td>
                                        <td><span class="status-pending">Pending</span></td>
                                    </tr>
                                    <tr>
                                        <td>2026-04-14</td>
                                        <td>Sarah Smith</td>
                                        <td>HR</td>
                                        <td>Meal</td>
                                        <td>45.50</td>
                                        <td><span class="status-approved">Approved</span></td>
                                    </tr>
                                    <tr>
                                        <td>2026-04-13</td>
                                        <td>Mike Johnson</td>
                                        <td>IT</td>
                                        <td>Office</td>
                                        <td>200.00</td>
                                        <td><span class="status-paid">Paid</span></td>
                                    </tr>
                                    <tr style="background: #f8f9fa;">
                                        <td colspan="4" class="fw-bold text-end">Total Amount:</td>
                                        <td colspan="2" class="fw-bold" style="color: #5BC0BE;">RM 395.50</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
