<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
 
require_once '../db.php';
 
// ---------- Live Stats ----------
// Real schema: id, name, staff_id, email, password, department, phone, role, created_at
$total_staff   = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='staff'")->fetch_assoc()['c'];
$total_finance  = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='finance'")->fetch_assoc()['c'];
$total_admin    = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='admin'")->fetch_assoc()['c'];
$total_users    = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
 
// Claims stats (only if claims table exists)
$total_claims = 0;
$total_paid   = 0;
$pending_count = 0;
$claims_exist = $conn->query("SHOW TABLES LIKE 'claims'")->num_rows > 0;
if ($claims_exist) {
    $total_claims  = $conn->query("SELECT COUNT(*) AS c FROM claims")->fetch_assoc()['c'];
    $total_paid    = $conn->query("SELECT IFNULL(SUM(amount),0) AS s FROM claims WHERE status='Paid'")->fetch_assoc()['s'];
    $pending_count = $conn->query("SELECT COUNT(*) AS c FROM claims WHERE status='Pending'")->fetch_assoc()['c'];
}
 
// ---------- Users by Department ----------
$dept_res = $conn->query("
    SELECT department, COUNT(*) AS cnt
    FROM users
    WHERE department IS NOT NULL AND department != ''
    GROUP BY department
    ORDER BY cnt DESC
");
 
// ---------- Recent registrations (last 5) ----------
$recent_users = $conn->query("
    SELECT name, role, department, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
");
 
// ---------- Claims by status (if table exists) ----------
$status_data = [];
if ($claims_exist) {
    $sr = $conn->query("SELECT status, COUNT(*) AS cnt, IFNULL(SUM(amount),0) AS total FROM claims GROUP BY status");
    while ($s = $sr->fetch_assoc()) $status_data[] = $s;
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
                <i class="fas fa-user-shield fs-1" style="color:#5BC0BE;"></i>
                <h5 class="mt-2">UTMSpace</h5>
                <small>Admin Portal</small>
            </div>
            <hr style="border-color:rgba(255,255,255,0.2);">
            <nav class="nav flex-column">
                <a class="nav-link active" href="dashboard_Admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a class="nav-link" href="Manage_User_Admin.php"><i class="fas fa-users"></i> Manage Accounts</a>
                <a class="nav-link" href="Generate_Report_Admin.php"><i class="fas fa-chart-bar"></i> Generate Report</a>
                <hr style="border-color:rgba(255,255,255,0.2);">
                <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
 
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 p-4">
 
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 style="color:white;">
                    <i class="fas fa-tachometer-alt me-2" style="color:#5BC0BE;"></i>
                    Admin Dashboard
                </h2>
                <div class="text-white">
                    <i class="fas fa-user-circle me-1"></i>
                    <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
            </div>
 
            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-number"><?php echo $total_staff; ?></div>
                        <div class="stat-label">Total Staff</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                        <div class="stat-number"><?php echo $total_finance; ?></div>
                        <div class="stat-label">Finance Staff</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
                        <div class="stat-number">
                            <?php echo $total_claims; ?>
                            <?php if ($pending_count > 0): ?>
                                <span class="badge bg-warning text-dark" style="font-size:0.5rem;"><?php echo $pending_count; ?> pending</span>
                            <?php endif; ?>
                        </div>
                        <div class="stat-label">Total Claims</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                        <div class="stat-number">RM <?php echo number_format($total_paid, 2); ?></div>
                        <div class="stat-label">Total Paid</div>
                    </div>
                </div>
            </div>
 
            <div class="row mb-4">
                <!-- Users by Department -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-lg fade-in h-100">
                        <div class="card-body p-4">
                            <h5 style="color:#0B132B;">
                                <i class="fas fa-building me-2" style="color:#5BC0BE;"></i>
                                Users by Department
                            </h5>
                            <hr>
                            <?php if ($dept_res && $dept_res->num_rows > 0): ?>
                            <table class="table">
                                <thead><tr><th>Department</th><th>Users</th></tr></thead>
                                <tbody>
                                    <?php while ($d = $dept_res->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($d['department']); ?></td>
                                        <td>
                                            <span class="badge" style="background:#5BC0BE; color:#0B132B;">
                                                <?php echo $d['cnt']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                                <p class="text-muted">No department data.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
 
                <!-- Recently Registered Users -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-lg fade-in h-100">
                        <div class="card-body p-4">
                            <h5 style="color:#0B132B;">
                                <i class="fas fa-user-plus me-2" style="color:#5BC0BE;"></i>
                                Recent Registrations
                            </h5>
                            <hr>
                            <?php if ($recent_users && $recent_users->num_rows > 0): ?>
                            <ul class="list-unstyled mb-0">
                                <?php while ($u = $recent_users->fetch_assoc()):
                                    $icon = match($u['role']) {
                                        'finance' => 'fa-user-tie',
                                        'admin'   => 'fa-user-shield',
                                        default   => 'fa-user'
                                    };
                                ?>
                                <li class="mb-3">
                                    <i class="fas <?php echo $icon; ?> me-2" style="color:#5BC0BE;"></i>
                                    <strong><?php echo htmlspecialchars($u['name']); ?></strong>
                                    <span class="badge ms-1" style="background:#3A506B; font-size:0.7rem;">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                    <br>
                                    <small class="text-muted ms-4">
                                        <?php echo htmlspecialchars($u['department'] ?? '—'); ?>
                                        · <?php echo date('d M Y', strtotime($u['created_at'])); ?>
                                    </small>
                                </li>
                                <?php endwhile; ?>
                            </ul>
                            <?php else: ?>
                                <p class="text-muted">No users registered yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
 
            <!-- Claims Status Summary (shows only if claims table exists) -->
            <?php if ($claims_exist && !empty($status_data)): ?>
            <div class="card border-0 shadow-lg fade-in">
                <div class="card-body p-4">
                    <h5 style="color:#0B132B;">
                        <i class="fas fa-chart-bar me-2" style="color:#5BC0BE;"></i>
                        Claims Status Summary
                    </h5>
                    <hr>
                    <?php
                    $color_map = [
                        'Pending'  => '#f6c23e',
                        'Approved' => '#5BC0BE',
                        'Rejected' => '#e74a3b',
                        'Paid'     => '#1cc88a',
                    ];
                    ?>
                    <div class="row">
                        <?php foreach ($status_data as $s):
                            $bg = $color_map[$s['status']] ?? '#aaa';
                        ?>
                        <div class="col-md-3 mb-3">
                            <div class="p-3 rounded text-center"
                                 style="background:<?php echo $bg; ?>20; border-left:4px solid <?php echo $bg; ?>;">
                                <div class="fw-bold fs-4" style="color:<?php echo $bg; ?>"><?php echo $s['cnt']; ?></div>
                                <div><?php echo $s['status']; ?></div>
                                <small class="text-muted">RM <?php echo number_format($s['total'], 2); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php elseif (!$claims_exist): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Claims table not found in database. Create it to enable claims tracking.
            </div>
            <?php endif; ?>
 
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>