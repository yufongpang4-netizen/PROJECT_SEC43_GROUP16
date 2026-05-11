<?php
session_start();
require_once "../db.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$real_claims = [];

$stmt = $conn->prepare("SELECT id, claim_type, amount, expense_date, status, description FROM claims WHERE user_id = ? ORDER BY submitted_at DESC");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $real_claims[] = $row;
    }
    $stmt->close();
}
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
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-receipt fs-1" style="color: #5BC0BE;"></i>
                    <h5 class="mt-2">UTMSpace</h5>
                    <small>Staff Portal</small>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard_Staff.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="New_Claim_Staff.php">
                        <i class="fas fa-plus-circle"></i> New Claim
                    </a>
                    <a class="nav-link active" href="Claim_History_Staff.php">
                        <i class="fas fa-history"></i> Claim History
                    </a>
                    <a class="nav-link" href="Edit_profile_Staff.php">
                        <i class="fas fa-user-edit"></i> Edit Profile
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
                                    <?php if(empty($real_claims)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="fas fa-folder-open fs-1 mb-3 d-block"></i>
                                                No claims found. Ready to submit your first claim?
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($real_claims as $claim): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($claim['expense_date']); ?></td>
                                            <td><?php echo htmlspecialchars($claim['claim_type']); ?></td>
                                            <td class="fw-bold"><?php echo number_format($claim['amount'], 2); ?></td>
                                            <td>
                                                <span class="status-<?php echo strtolower($claim['status']); ?>">
                                                    <i class="fas <?php 
                                                        echo $claim['status'] == 'Pending' ? 'fa-clock' : 
                                                            ($claim['status'] == 'Approved' ? 'fa-check' : 
                                                            ($claim['status'] == 'Paid' ? 'fa-dollar-sign' : 
                                                            ($claim['status'] == 'Draft' ? 'fa-save' : 'fa-times'))); 
                                                    ?> me-1"></i>
                                                    <?php echo htmlspecialchars($claim['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm" style="background: #5BC0BE; color: #0B132B;" onclick="alert('Description: <?php echo htmlspecialchars(addslashes($claim['description'])); ?>')">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                
                                                <?php if($claim['status'] == 'Pending' || $claim['status'] == 'Draft'): ?>
                                                    <button class="btn btn-sm btn-danger" onclick="if(confirm('Cancel this claim?')) alert('In future updates, this will delete the claim from database!')">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
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