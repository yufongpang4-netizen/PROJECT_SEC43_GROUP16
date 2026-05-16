<?php
session_start();
require_once "../db.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$totalClaims = 0;
$pendingClaims = 0;
$approvedClaims = 0;
$totalAmount = 0.00;


$stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_claims,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_claims,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved_claims,
        COALESCE(SUM(amount), 0) AS total_amount
    FROM claims
    WHERE user_id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $totalClaims = $row['total_claims'];
        $pendingClaims = $row['pending_claims'];
        $approvedClaims = $row['approved_claims'];
        $totalAmount = $row['total_amount'];
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - UTMSPACE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Staff Dashboard - Soft Blue Theme */
        :root {
            --staff-primary: #1e3a5f;
            --staff-secondary: #3b82f6;
            --staff-soft: #e8f0fe;
            --staff-accent: #5BC0BE;
            --staff-light: #f8fafc;
            --staff-white: #ffffff;
            --staff-text: #1e293b;
            --staff-gray: #64748b;
        }
        
        body {
            background: linear-gradient(135deg, #e8f0fe 0%, #d9e6f5 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #1e3a5f 0%, #2c5282 100%);
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
            background: rgba(91, 192, 190, 0.2);
            color: #5BC0BE;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: #5BC0BE;
            color: #1e3a5f;
            font-weight: 600;
        }
        
        /* Stats Cards */
        .stat-card {
            background: var(--staff-white);
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s ease;
            border: 1px solid rgba(30, 58, 95, 0.1);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-color: #5BC0BE;
        }
        
        .stat-icon {
            width: 55px;
            height: 55px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #3b82f6;
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #1e3a5f;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Action Cards */
        .action-card {
            background: var(--staff-white);
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
            color: white;
        }
        
        .btn-secondary-custom {
            background: #e2e8f0;
            color: #1e3a5f;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-secondary-custom:hover {
            background: #cbd5e1;
            transform: translateY(-2px);
        }
        
        /* Welcome Header */
        .welcome-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
            border-radius: 20px;
            padding: 20px 25px;
            color: white;
            margin-bottom: 25px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        hr {
            border-color: rgba(30, 58, 95, 0.1);
        }
        
        /* Recent Claims Table */
        .recent-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .recent-table thead {
            background: #f1f5f9;
            color: #1e3a5f;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-Pending { background: #fef3c7; color: #d97706; }
        .status-Approved { background: #d1fae5; color: #059669; }
        .status-Paid { background: #dbeafe; color: #2563eb; }
        .status-Rejected { background: #fee2e2; color: #dc2626; }
    </style>
</head>

<body>
    <div class="container-fluid p-0">
        <div class="row g-0">

            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-receipt fs-1" style="color: #5BC0BE;"></i>
                    <h5 class="mt-2">UTMSPACE</h5>
                    <small>Staff Portal</small>
                </div>

                <hr style="border-color: rgba(255,255,255,0.2);">

                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard_Staff.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="New_Claim_Staff.php">
                        <i class="fas fa-plus-circle me-2"></i> New Claim
                    </a>
                    <a class="nav-link" href="Claim_History_Staff.php">
                        <i class="fas fa-history me-2"></i> Claim History
                    </a>
                    <a class="nav-link" href="Edit_profile_Staff.php">
                        <i class="fas fa-user-edit me-2"></i> Edit Profile
                    </a>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                
                <!-- Welcome Header -->
                <div class="welcome-header fade-in">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h3 class="mb-1">
                                <i class="fas fa-smile-wink me-2" style="color: #5BC0BE;"></i>
                                Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
                            </h3>
                            <p class="mb-0 opacity-75">Here's what's happening with your claims today.</p>
                        </div>
                        <div class="mt-2 mt-sm-0">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['email']); ?>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row g-4 mb-4 fade-in">
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card text-center">
                            <div class="stat-icon mx-auto">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="stat-number"><?php echo $totalClaims; ?></div>
                            <div class="stat-label">Total Claims</div>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card text-center">
                            <div class="stat-icon mx-auto">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-number"><?php echo $pendingClaims; ?></div>
                            <div class="stat-label">Pending Approval</div>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card text-center">
                            <div class="stat-icon mx-auto">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-number"><?php echo $approvedClaims; ?></div>
                            <div class="stat-label">Approved</div>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card text-center">
                            <div class="stat-icon mx-auto">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-number">RM <?php echo number_format($totalAmount, 2); ?></div>
                            <div class="stat-label">Total Claimed</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 fade-in">
                    <!-- Quick Actions -->
                    <div class="col-md-6">
                        <div class="action-card">
                            <div class="card-body p-4">
                                <h5 style="color: #1e3a5f;">
                                    <i class="fas fa-bolt me-2" style="color: #3b82f6;"></i>
                                    Quick Actions
                                </h5>
                                <hr>
                                
                                <a href="New_Claim_Staff.php" class="btn btn-primary-custom w-100 mb-3">
                                    <i class="fas fa-plus-circle me-2"></i>Submit New Claim
                                </a>

                                <a href="Claim_History_Staff.php" class="btn btn-secondary-custom w-100">
                                    <i class="fas fa-history me-2"></i>View Claim History
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Help Section -->
                    <div class="col-md-6">
                        <div class="action-card">
                            <div class="card-body p-4">
                                <h5 style="color: #1e3a5f;">
                                    <i class="fas fa-life-ring me-2" style="color: #3b82f6;"></i>
                                    How to Submit a Claim
                                </h5>
                                <hr>
                                <ol class="mb-0" style="color: #475569; line-height: 1.8;">
                                    <li>Click <strong>"New Claim"</strong> in the sidebar</li>
                                    <li>Fill in claim details and amount</li>
                                    <li>Upload receipt (PDF/JPG/PNG)</li>
                                    <li>Submit for finance approval</li>
                                    <li>Track status in "Claim History"</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity (Optional) -->
                <div class="mt-4 fade-in">
                    <div class="action-card">
                        <div class="card-body p-4">
                            <h5 style="color: #1e3a5f;">
                                <i class="fas fa-chart-line me-2" style="color: #3b82f6;"></i>
                                Quick Tips
                            </h5>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-info-circle me-2" style="color: #3b82f6;"></i>
                                        <small>Maximum claim amount per submission: <strong>RM 200</strong></small>
                                    </div>
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-info-circle me-2" style="color: #3b82f6;"></i>
                                        <small>Monthly claim limit: <strong>RM 500</strong> (max 3 claims)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-info-circle me-2" style="color: #3b82f6;"></i>
                                        <small>Pending claims block new submissions until approved</small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-info-circle me-2" style="color: #3b82f6;"></i>
                                        <small>Attach clear receipt images for faster approval</small>
                                    </div>
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