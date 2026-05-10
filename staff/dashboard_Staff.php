<?php
session_start();
require_once "db.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location:login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$totalClaims = 0;
$pendingClaims = 0;
$approvedClaims = 0;
$totalAmount = 0.00;

/*
    This part reads claim statistics from the MySQL claims table.
    If the claims table is not created yet, create it using the SQL below.
*/

$stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_claims,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_claims,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_claims,
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
    <title>Staff Dashboard - UTMSpace</title>

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
                    <i class="fas fa-receipt fs-1" style="color: #5BC0BE;"></i>
                    <h5 class="mt-2">UTMSpace</h5>
                    <small>Staff Portal</small>
                </div>

                <hr style="border-color: rgba(255,255,255,0.2);">

                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>

                    <a class="nav-link" href="new_claim.php">
                        <i class="fas fa-plus-circle"></i> New Claim
                    </a>

                    <a class="nav-link" href="claim_history.php">
                        <i class="fas fa-history"></i> Claim History
                    </a>

                    <a class="nav-link" href="edit_profile.php">
                        <i class="fas fa-user-edit"></i> Edit Profile
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
                        <i class="fas fa-tachometer-alt me-2" style="color: #5BC0BE;"></i>
                        Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
                    </h2>

                    <div class="text-white">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['email']); ?>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="stat-number"><?php echo $totalClaims; ?></div>
                            <div class="stat-label">Total Claims</div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-number"><?php echo $pendingClaims; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-number"><?php echo $approvedClaims; ?></div>
                            <div class="stat-label">Approved</div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-number"><?php echo number_format($totalAmount, 2); ?></div>
                            <div class="stat-label">Total Amount (RM)</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-lg fade-in">
                            <div class="card-body p-4">
                                <h5 style="color: #0B132B;">
                                    <i class="fas fa-rocket me-2" style="color: #5BC0BE;"></i>
                                    Quick Actions
                                </h5>

                                <hr>

                                <a href="new_claim.php" class="btn w-100 mb-2" style="background: #5BC0BE; color: #0B132B; border-radius: 10px;">
                                    <i class="fas fa-plus-circle me-2"></i>Submit New Claim
                                </a>

                                <a href="claim_history.php" class="btn w-100" style="background: #3A506B; color: white; border-radius: 10px;">
                                    <i class="fas fa-history me-2"></i>View Claim History
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card border-0 shadow-lg fade-in">
                            <div class="card-body p-4">
                                <h5 style="color: #0B132B;">
                                    <i class="fas fa-info-circle me-2" style="color: #5BC0BE;"></i>
                                    How to Submit a Claim
                                </h5>

                                <hr>

                                <ol class="mb-0" style="color: #3A506B;">
                                    <li>Click "New Claim" in the sidebar</li>
                                    <li>Fill in claim details</li>
                                    <li>Attach receipt if required</li>
                                    <li>Submit for approval</li>
                                    <li>Track status in "Claim History"</li>
                                </ol>
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