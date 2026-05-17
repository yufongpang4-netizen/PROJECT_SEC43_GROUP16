<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
 
require_once '../db.php';
 
// ---------- Live Stats ----------
$total_staff   = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='staff'")->fetch_assoc()['c'];
$total_finance = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='finance'")->fetch_assoc()['c'];
$total_admin   = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='admin'")->fetch_assoc()['c'];
$total_users   = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
 
// Claims stats
$total_claims = 0;
$total_paid   = 0;
$pending_count = 0;
$claims_exist = $conn->query("SHOW TABLES LIKE 'claims'")->num_rows > 0;
if ($claims_exist) {
    $total_claims  = $conn->query("SELECT COUNT(*) AS c FROM claims")->fetch_assoc()['c'];
    $total_paid    = $conn->query("SELECT IFNULL(SUM(amount),0) AS s FROM claims WHERE status='Paid'")->fetch_assoc()['s'];
    $pending_count = $conn->query("SELECT COUNT(*) AS c FROM claims WHERE status='Pending'")->fetch_assoc()['c'];
}
 
// Users by Department
$dept_res = $conn->query("
    SELECT department, COUNT(*) AS cnt
    FROM users
    WHERE department IS NOT NULL AND department != ''
    GROUP BY department
    ORDER BY cnt DESC
");
 
// Recent registrations
$recent_users = $conn->query("
    SELECT name, role, department, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
");
 
$status_data = [
    'Pending'   => ['cnt' => 0, 'total' => 0.00],
    'Approved'  => ['cnt' => 0, 'total' => 0.00],
    'Paid'      => ['cnt' => 0, 'total' => 0.00],
    'Rejected'  => ['cnt' => 0, 'total' => 0.00],
    'Cancelled' => ['cnt' => 0, 'total' => 0.00] // Added Cancelled for soft delete
];

$trend_labels = [];
$trend_data = [];
$dept_pie_labels = [];
$dept_pie_data = [];

if ($claims_exist) {
    $sr = $conn->query("SELECT status, COUNT(*) AS cnt, IFNULL(SUM(amount),0) AS total FROM claims GROUP BY status");
    while ($s = $sr->fetch_assoc()) {
        if(isset($status_data[$s['status']])) {
            $status_data[$s['status']]['cnt'] = $s['cnt'];
            $status_data[$s['status']]['total'] = $s['total'];
        }
    }

    // Chart 1 Data: Monthly Trend (Last 6 months)
    $trend_sql = "SELECT DATE_FORMAT(submitted_at, '%b %Y') as month_name, SUM(amount) as total
                  FROM claims
                  WHERE status != 'Cancelled' AND status != 'Rejected'
                  GROUP BY DATE_FORMAT(submitted_at, '%Y-%m'), month_name
                  ORDER BY DATE_FORMAT(submitted_at, '%Y-%m') ASC LIMIT 6";
    $trend_res = $conn->query($trend_sql);
    while($row = $trend_res->fetch_assoc()) {
        $trend_labels[] = $row['month_name'];
        $trend_data[] = floatval($row['total']);
    }

    // Chart 2 Data: Department Cost Split
    $pie_sql = "SELECT u.department, SUM(c.amount) as total_cost
                FROM claims c
                JOIN users u ON c.user_id = u.id
                WHERE u.department IS NOT NULL AND u.department != '' AND c.status != 'Cancelled'
                GROUP BY u.department";
    $pie_res = $conn->query($pie_sql);
    while($row = $pie_res->fetch_assoc()) {
        $dept_pie_labels[] = $row['department'];
        $dept_pie_data[] = floatval($row['total_cost']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --admin-primary: #4c1d95;
            --admin-secondary: #8b5cf6;
            --admin-soft: #f5f3ff;
            --admin-accent: #5BC0BE;
            --admin-white: #ffffff;
            --admin-text: #4c1d95;
            --admin-gray: #6b7280;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; margin: 0; padding: 0; }
        body { background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; }
        .container-fluid { height: 100%; overflow: hidden; }
        .row.g-0 { height: 100%; }
        
        .sidebar { background: linear-gradient(180deg, #4c1d95 0%, #6d28d9 100%); height: 100vh; color: white; transition: all 0.3s ease; overflow-y: auto; position: sticky; top: 0; }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 5px; }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.85); padding: 12px 20px; margin: 5px 0; border-radius: 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(91, 192, 190, 0.2); color: #5BC0BE; transform: translateX(5px); }
        .sidebar .nav-link.active { background: #5BC0BE; color: #4c1d95; font-weight: 600; }
        
        .main-content { height: 100vh; overflow-y: auto; padding: 20px; }
        .main-content::-webkit-scrollbar { width: 8px; }
        .main-content::-webkit-scrollbar-track { background: #e5e7eb; border-radius: 10px; }
        .main-content::-webkit-scrollbar-thumb { background: #8b5cf6; border-radius: 10px; }
        
        .page-header { background: linear-gradient(135deg, #4c1d95 0%, #6d28d9 100%); border-radius: 20px; padding: 20px 25px; color: white; margin-bottom: 25px; }
        
        .stat-card { background: white; border-radius: 20px; padding: 20px; transition: all 0.3s ease; border: 1px solid #ede9fe; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); border-color: #8b5cf6; }
        .stat-icon { width: 55px; height: 55px; background: rgba(139, 92, 246, 0.1); border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #8b5cf6; margin: 0 auto 15px; }
        .stat-number { font-size: 28px; font-weight: 700; color: #4c1d95; margin-bottom: 5px; }
        .stat-label { color: #6b7280; font-size: 14px; font-weight: 500; }
        .badge-pending { background: #fef3c7; color: #d97706; font-size: 10px; padding: 2px 8px; border-radius: 20px; margin-left: 8px; }
        
        .info-card { background: white; border-radius: 20px; border: none; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05); }
        .card-title { color: #4c1d95; font-size: 18px; font-weight: 600; }
        
        .dept-table { margin-bottom: 0; }
        .dept-table td { padding: 10px 0; border-bottom: 1px solid #f3e8ff; }
        .dept-table tr:last-child td { border-bottom: none; }
        .dept-badge { background: #8b5cf6; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
        
        .user-list { list-style: none; padding: 0; margin: 0; }
        .user-list li { padding: 12px 0; border-bottom: 1px solid #f3e8ff; }
        .user-list li:last-child { border-bottom: none; }
        .user-name { font-weight: 600; color: #4c1d95; }
        .role-badge { padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; }
        .role-staff { background: #10b981; color: white; }
        .role-finance { background: #3b82f6; color: white; }
        .role-admin { background: #ef4444; color: white; }
        .user-meta { font-size: 11px; color: #6b7280; }
        
        .status-card { padding: 15px; border-radius: 15px; text-align: center; transition: all 0.3s ease; }
        .status-card:hover { transform: translateY(-3px); }
        .status-count { font-size: 28px; font-weight: 700; }
        .status-label { font-size: 13px; font-weight: 500; }
        .status-amount { font-size: 11px; color: #6b7280; }
        
        .chart-container { position: relative; height: 300px; width: 100%; display: flex; justify-content: center; align-items: center; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        hr { border-color: #f3e8ff; }
        @media (max-width: 768px) { .sidebar { height: auto; position: relative; } .main-content { height: auto; overflow-y: visible; } .stat-number { font-size: 22px; } }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0">
 
        <div class="col-md-3 col-lg-2 sidebar">
            <div class="p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-user-shield fs-1" style="color:#5BC0BE;"></i>
                    <h5 class="mt-2">UTMSPACE</h5>
                    <small>Admin Portal</small>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard_Admin.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i> Dashboard</a>
                    <a class="nav-link" href="Manage_User_Admin.php"><i class="fas fa-users fa-fw me-2"></i> Manage Accounts</a>
                    <a class="nav-link" href="Generate_Report_Admin.php"><i class="fas fa-chart-bar fa-fw me-2"></i> Generate Report</a>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout</a>
                </nav>
            </div>
        </div>
 
        <div class="col-md-9 col-lg-10 main-content">
 
            <div class="page-header fade-in">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h3 class="mb-1"><i class="fas fa-tachometer-alt me-2" style="color: #5BC0BE;"></i>Admin Dashboard</h3>
                        <p class="mb-0 opacity-75">Overview of system activity and user statistics</p>
                    </div>
                    <div class="mt-2 mt-sm-0">
                        <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </div>
                </div>
            </div>
 
            <div class="row g-4 mb-4 fade-in">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card text-center">
                        <div class="stat-icon mx-auto"><i class="fas fa-users"></i></div>
                        <div class="stat-number"><?php echo $total_staff; ?></div>
                        <div class="stat-label">Total Staff</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card text-center">
                        <div class="stat-icon mx-auto"><i class="fas fa-user-tie"></i></div>
                        <div class="stat-number"><?php echo $total_finance; ?></div>
                        <div class="stat-label">Finance Staff</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card text-center">
                        <div class="stat-icon mx-auto"><i class="fas fa-file-invoice"></i></div>
                        <div class="stat-number">
                            <?php echo $total_claims; ?>
                            <?php if ($pending_count > 0): ?>
                                <span class="badge-pending"><?php echo $pending_count; ?> pending</span>
                            <?php endif; ?>
                        </div>
                        <div class="stat-label">Total Claims</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card text-center">
                        <div class="stat-icon mx-auto"><i class="fas fa-dollar-sign"></i></div>
                        <div class="stat-number">RM <?php echo number_format($total_paid, 2); ?></div>
                        <div class="stat-label">Total Paid</div>
                    </div>
                </div>
            </div>

            <?php if ($claims_exist && !empty($trend_labels)): ?>
            <div class="row g-4 mb-4 fade-in">
                <div class="col-md-7">
                    <div class="info-card h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title"><i class="fas fa-chart-line me-2" style="color: #8b5cf6;"></i>Monthly Expense Trend</h5>
                            <hr>
                            <div class="chart-container">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="info-card h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title"><i class="fas fa-chart-pie me-2" style="color: #8b5cf6;"></i>Cost by Department</h5>
                            <hr>
                            <div class="chart-container">
                                <canvas id="deptChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
 
            <div class="row g-4 mb-4 fade-in">
                <div class="col-md-6">
                    <div class="info-card h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title"><i class="fas fa-building me-2" style="color: #8b5cf6;"></i>Users by Department</h5>
                            <hr>
                            <?php if ($dept_res && $dept_res->num_rows > 0): ?>
                                <table class="dept-table table w-100">
                                    <tbody>
                                        <?php while ($d = $dept_res->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($d['department']); ?></td>
                                            <td class="text-end"><span class="dept-badge"><?php echo $d['cnt']; ?> users</span></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-muted mb-0">No department data available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
 
                <div class="col-md-6">
                    <div class="info-card h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title"><i class="fas fa-user-plus me-2" style="color: #8b5cf6;"></i>Recent Registrations</h5>
                            <hr>
                            <?php if ($recent_users && $recent_users->num_rows > 0): ?>
                                <ul class="user-list">
                                    <?php while ($u = $recent_users->fetch_assoc()):
                                        $role_class = match($u['role']) { 'finance' => 'role-finance', 'admin' => 'role-admin', default => 'role-staff' };
                                    ?>
                                    <li>
                                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                                            <div>
                                                <span class="user-name"><?php echo htmlspecialchars($u['name']); ?></span>
                                                <span class="role-badge <?php echo $role_class; ?> ms-2"><?php echo ucfirst($u['role']); ?></span>
                                            </div>
                                        </div>
                                        <div class="user-meta mt-1">
                                            <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($u['department'] ?? '—'); ?>
                                            <span class="mx-1">•</span>
                                            <i class="fas fa-calendar me-1"></i> <?php echo date('d M Y', strtotime($u['created_at'])); ?>
                                        </div>
                                    </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">No users registered yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
 
            <?php if ($claims_exist): ?>
            <div class="info-card mb-4 fade-in">
                <div class="card-body p-4">
                    <h5 class="card-title"><i class="fas fa-server me-2" style="color: #8b5cf6;"></i>Database Status Summary</h5>
                    <hr>
                    <div class="row g-3">
                        <?php foreach ($status_data as $status_name => $s):
                            $color = match($status_name) {
                                'Pending'  => '#f59e0b', 'Approved' => '#5BC0BE', 'Rejected' => '#ef4444', 'Paid' => '#10b981', 'Cancelled' => '#6b7280', default => '#6b7280'
                            };
                            $bg_color = match($status_name) {
                                'Pending'  => '#fef3c7', 'Approved' => '#e8f0fe', 'Rejected' => '#fee2e2', 'Paid' => '#d1fae5', 'Cancelled' => '#f3f4f6', default => '#f3f4f6'
                            };
                        ?>
                        <div class="col-md">
                            <div class="status-card" style="background: <?php echo $bg_color; ?>; border-left: 4px solid <?php echo $color; ?>;">
                                <div class="status-count" style="color: <?php echo $color; ?>;"><?php echo $s['cnt']; ?></div>
                                <div class="status-label" style="color: <?php echo $color; ?>;"><?php echo $status_name; ?></div>
                                <div class="status-amount">RM <?php echo number_format($s['total'], 2); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info fade-in" style="border-radius: 15px;"><i class="fas fa-info-circle me-2"></i>Claims table not found.</div>
            <?php endif; ?>
            
            <div style="height: 20px;"></div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($claims_exist && !empty($trend_labels)): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. 渲染折线图 (Monthly Trend)
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trend_labels); ?>,
            datasets: [{
                label: 'Total Claimed (RM)',
                data: <?php echo json_encode($trend_data); ?>,
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139, 92, 246, 0.2)',
                borderWidth: 3,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#8b5cf6',
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: true,
                tension: 0.4 // 让折线图变成平滑的曲线
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                x: { grid: { display: false } }
            }
        }
    });

    // 2. 渲染环形图 (Department Pie)
    const deptCtx = document.getElementById('deptChart').getContext('2d');
    new Chart(deptCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($dept_pie_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($dept_pie_data); ?>,
                backgroundColor: ['#4c1d95', '#8b5cf6', '#5BC0BE', '#10b981', '#f59e0b', '#ef4444', '#64748b'],
                borderWidth: 0,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { usePointStyle: true, padding: 20 } }
            },
            cutout: '70%' // 让环形图中间变空
        }
    });
});
</script>
<?php endif; ?>
</body>
</html>