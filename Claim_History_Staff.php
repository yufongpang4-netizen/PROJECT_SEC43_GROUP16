<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../login.php");
    exit();
}

$mock_claims = [
    ['id' => 1, 'type' => 'Travel', 'amount' => 150.00, 'date' => '2026-04-15', 'status' => 'Pending', 'description' => 'Taxi to client meeting'],
    ['id' => 2, 'type' => 'Meal', 'amount' => 45.50, 'date' => '2026-04-10', 'status' => 'Approved', 'description' => 'Lunch with team'],
    ['id' => 3, 'type' => 'Office Supplies', 'amount' => 89.99, 'date' => '2026-04-05', 'status' => 'Paid', 'description' => 'Printer ink'],
    ['id' => 4, 'type' => 'Training', 'amount' => 500.00, 'date' => '2026-03-20', 'status' => 'Rejected', 'description' => 'Online course'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim History - UTMSpace</title>
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
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="new_claim.php">
                        <i class="fas fa-plus-circle"></i> New Claim
                    </a>
                    <a class="nav-link active" href="claim_history.php">
                        <i class="fas fa-history"></i> Claim History
                    </a>
                    <a class="nav-link" href="edit_profile.php">
                        <i class="fas fa-user-edit"></i> Edit Profile
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
                        <i class="fas fa-history me-2" style="color: #5BC0BE;"></i>
                        My Claim History
                    </h2>
                </div>
                
                <div class="card border-0 shadow-lg fade-in">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-calendar me-1"></i> Date</th>
                                        <th><i class="fas fa-tag me-1"></i> Claim Type</th>
                                        <th><i class="fas fa-dollar-sign me-1"></i> Amount (RM)</th>
                                        <th><i class="fas fa-chart-line me-1"></i> Status</th>
                                        <th><i class="fas fa-cog me-1"></i> Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($mock_claims as $claim): ?>
                                    <tr>
                                        <td><?php echo $claim['date']; ?></td>
                                        <td><?php echo $claim['type']; ?></td>
                                        <td class="fw-bold"><?php echo number_format($claim['amount'], 2); ?></td>
                                        <td>
                                            <span class="status-<?php echo strtolower($claim['status']); ?>">
                                                <i class="fas <?php 
                                                    echo $claim['status'] == 'Pending' ? 'fa-clock' : 
                                                          ($claim['status'] == 'Approved' ? 'fa-check' : 
                                                          ($claim['status'] == 'Paid' ? 'fa-dollar-sign' : 'fa-times')); 
                                                ?> me-1"></i>
                                                <?php echo $claim['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm" style="background: #5BC0BE; color: #0B132B;" onclick="alert('Claim #<?php echo $claim['id']; ?> Details')">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <?php if($claim['status'] == 'Pending'): ?>
                                                <button class="btn btn-sm btn-danger" onclick="if(confirm('Cancel this claim?')) alert('Claim cancelled')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            <?php endif; ?>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
