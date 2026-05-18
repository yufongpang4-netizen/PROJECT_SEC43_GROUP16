<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
 
require_once '../db.php';
 
$filter_role = $_GET['filter_role']   ?? '';
$filter_dept = $_GET['department']    ?? '';
$date_from   = $_GET['date_from']     ?? '';
$date_to     = $_GET['date_to']       ?? '';
$generated   = false;
$users       = [];
 
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $q = buildQuery($filter_role, $filter_dept, $date_from, $date_to);
    $res = runQuery($conn, $q['sql'], $q['types'], $q['params']);
 
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="UTMSpace_Users_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Staff ID', 'Name', 'Email', 'Phone', 'Role', 'Department', 'Status', 'Registered']);
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [
            $row['id'],
            $row['staff_id'] ?? '',
            $row['name'],
            $row['email'],
            $row['phone'] ?? '',
            ucfirst($row['role']),
            $row['department'] ?? '',
            $row['status'] ?? 'Active',
            date('d M Y', strtotime($row['created_at'])),
        ]);
    }
    fclose($out);
    exit();
}
 
function buildQuery($role, $dept, $from, $to) {
    $sql    = "SELECT * FROM users WHERE 1=1";
    $params = [];
    $types  = '';
 
    if ($role !== '') {
        $sql     .= " AND role=?";
        $params[] = $role;
        $types   .= 's';
    }
    if ($dept !== '') {
        $sql     .= " AND department=?";
        $params[] = $dept;
        $types   .= 's';
    }
    if ($from !== '') {
        $sql     .= " AND DATE(created_at) >= ?";
        $params[] = $from;
        $types   .= 's';
    }
    if ($to !== '') {
        $sql     .= " AND DATE(created_at) <= ?";
        $params[] = $to;
        $types   .= 's';
    }
    $sql .= " ORDER BY created_at DESC";
    return compact('sql', 'types', 'params');
}
 
function runQuery($conn, $sql, $types, $params) {
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}
 
if (isset($_GET['generate'])) {
    $generated = true;
    $q         = buildQuery($filter_role, $filter_dept, $date_from, $date_to);
    $res       = runQuery($conn, $q['sql'], $q['types'], $q['params']);
    while ($row = $res->fetch_assoc()) $users[] = $row;
}
 
$depts_res   = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
$departments = [];
while ($d = $depts_res->fetch_assoc()) $departments[] = $d['department'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Report - Admin | UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <style>
        /* ADMIN - DARK PURPLE THEME WITH LIGHT BACKGROUND */
        :root {
            --admin-primary: #2e1065;
            --admin-secondary: #4c1d95;
            --admin-accent: #8b5cf6;
            --admin-bg: #faf5ff;
            --admin-card: #ffffff;
            --admin-text: #2e1065;
            --admin-gray: #6b7280;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; margin: 0; padding: 0; }
        body { background: var(--admin-bg); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; }
        
        .container-fluid { height: 100%; overflow: hidden; }
        .row.g-0 { height: 100%; }
        
        /* Sidebar */
        .sidebar { background: linear-gradient(180deg, #2e1065 0%, #4c1d95 100%); height: 100vh; color: white; transition: all 0.3s ease; overflow-y: auto; position: sticky; top: 0; }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 5px; }
        
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.85); padding: 12px 20px; margin: 5px 0; border-radius: 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; transform: translateX(5px); }
        .sidebar .nav-link.active { background: #8b5cf6; color: #2e1065; font-weight: 600; }
        
        /* Main Content */
        .main-content { height: 100vh; overflow-y: auto; padding: 20px; }
        .main-content::-webkit-scrollbar { width: 8px; }
        .main-content::-webkit-scrollbar-track { background: #e5e7eb; border-radius: 10px; }
        .main-content::-webkit-scrollbar-thumb { background: #8b5cf6; border-radius: 10px; }
        
        /* Page Header */
        .page-header { background: linear-gradient(135deg, #2e1065 0%, #4c1d95 100%); border-radius: 20px; padding: 20px 25px; color: white; margin-bottom: 25px; }
        
        /* Filter Card */
        .filter-card { background: white; border-radius: 20px; border: none; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05); margin-bottom: 25px; }
        .filter-card .card-title { color: #2e1065; font-size: 18px; font-weight: 600; }
        
        .form-label { font-weight: 600; color: #2e1065; margin-bottom: 8px; }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #e5e7eb; padding: 10px 15px; transition: all 0.3s ease; }
        .form-control:focus, .form-select:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        
        /* Buttons */
        .btn-generate, .btn-reset {
            padding: 10px 15px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-generate { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; border: none; }
        .btn-generate:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.4); color: white; }
        
        .btn-reset { background: #f1f5f9; color: #2e1065; border: none; }
        .btn-reset:hover { background: #e2e8f0; transform: translateY(-2px); color: #2e1065;}
        
        .btn-export-csv { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; border-radius: 10px; padding: 8px 16px; font-weight: 500; transition: all 0.3s ease; }
        .btn-export-csv:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4); color: white; }
        
        .btn-print { background: #6b7280; color: white; border: none; border-radius: 10px; padding: 8px 16px; transition: all 0.3s ease; }
        .btn-print:hover { background: #4b5563; transform: translateY(-2px); color: white; }
        
        /* Report Card */
        .report-card { background: white; border-radius: 20px; border: none; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05); padding: 20px; }
        .filters-info { background: #faf5ff; border-radius: 12px; padding: 12px 15px; margin-bottom: 20px; border-left: 4px solid #8b5cf6; }
        
        /* Table */
        .table-custom { margin-bottom: 0; }
        .table-custom thead { background: #f1f5f9; }
        .table-custom th { color: #2e1065; font-weight: 600; padding: 12px 15px; border: none; }
        .table-custom td { padding: 10px 15px; vertical-align: middle; border-bottom: 1px solid #f3e8ff; }
        .table-custom tr:hover { background: #faf5ff; }
        
        /* Role Badges */
        .role-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .role-staff { background: #3b82f6; color: white; }
        .role-finance { background: #10b981; color: white; }
        .role-admin { background: #ef4444; color: white; }
        
        /* Status Badges */
        .status-active { background: #d1fae5; color: #059669; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-inactive { background: #fee2e2; color: #dc2626; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 60px; }
        .empty-icon { font-size: 60px; color: #cbd5e1; margin-bottom: 20px; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        
        /* DataTables */
        .dataTables_filter input { border-radius: 10px; border: 1px solid #e5e7eb; padding: 6px 12px; margin-left: 10px; }
        .dataTables_filter input:focus { border-color: #8b5cf6; outline: none; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        .dataTables_filter label { color: #2e1065; font-weight: 500; }
        .page-item.active .page-link { background-color: #8b5cf6 !important; border-color: #8b5cf6 !important; color: white !important; }
        .page-link { color: #2e1065 !important; border-radius: 6px; margin: 0 2px; }
        .dataTables_length label { color: #2e1065; }
        .dataTables_info { color: #6b7280; }
        
        hr { border-color: #f3e8ff; }
        
        @media print {
            .sidebar, .btn-generate, .btn-reset, .btn-export-csv, .btn-print, .page-header button, .dataTables_filter, .dataTables_paginate, .dataTables_info, .dataTables_length { display: none !important; }
            .col-md-9, .main-content { width: 100% !important; margin: 0 !important; padding: 0 !important; }
            body { background: white !important; }
            .filter-card { display: none !important; }
            .report-card { box-shadow: none; border: none; }
        }
        
        @media (max-width: 768px) { .sidebar { height: auto; position: relative; } .main-content { height: auto; overflow-y: visible; } }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0">
 
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 sidebar">
            <div class="p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-user-shield fs-1" style="color:#8b5cf6;"></i>
                    <h5 class="mt-2">UTMSPACE</h5>
                    <small>Admin Portal</small>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard_Admin.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i> Dashboard</a>
                    <a class="nav-link" href="Manage_User_Admin.php"><i class="fas fa-users fa-fw me-2"></i> Manage Accounts</a>
                    <a class="nav-link active" href="Generate_Report_Admin.php"><i class="fas fa-chart-bar fa-fw me-2"></i> Generate Report</a>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout</a>
                </nav>
            </div>
        </div>
 
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 main-content">
            <div class="page-header fade-in">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h3 class="mb-1"><i class="fas fa-chart-bar me-2" style="color: #8b5cf6;"></i>Generate User Reports</h3>
                        <p class="mb-0 opacity-75">Export user data with custom filters</p>
                    </div>
                </div>
            </div>
 
            <!-- Filter Card -->
            <div class="filter-card fade-in">
                <div class="card-body p-4">
                    <h5 class="card-title mb-3">
                        <i class="fas fa-filter me-2" style="color: #8b5cf6;"></i>Filter Users
                    </h5>
                    <hr>
                    <form method="GET">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Role</label>
                                <select name="filter_role" class="form-select">
                                    <option value="">All Roles</option>
                                    <option value="staff"   <?php echo $filter_role === 'staff'   ? 'selected' : ''; ?>>Staff</option>
                                    <option value="finance" <?php echo $filter_role === 'finance' ? 'selected' : ''; ?>>Finance</option>
                                    <option value="admin"   <?php echo $filter_role === 'admin'   ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Department</label>
                                <select name="department" class="form-select">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>"
                                        <?php echo $filter_dept === $dept ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Registered From</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Registered To</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label d-block">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="generate" class="btn btn-generate flex-grow-1">
                                        <i class="fas fa-chart-line me-1"></i> Generate
                                    </button>
                                    <a href="Generate_Report_Admin.php" class="btn btn-reset">
                                        <i class="fas fa-sync-alt"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
 
            <!-- Report Results -->
            <?php if ($generated): ?>
            <div class="report-card fade-in mb-5">
                <div class="card-body p-0">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                        <h5 class="card-title mb-2 mb-sm-0" style="color: #2e1065; font-weight:600;">
                            <i class="fas fa-users me-2" style="color: #8b5cf6;"></i>
                            User Report
                            <span class="text-muted fs-6 ms-2">(<?php echo count($users); ?> record<?php echo count($users) !== 1 ? 's' : ''; ?>)</span>
                        </h5>
                        <div class="d-flex gap-2">
                            <a href="?generate=1&filter_role=<?php echo urlencode($filter_role); ?>&department=<?php echo urlencode($filter_dept); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&export=csv"
                               class="btn btn-export-csv">
                                <i class="fas fa-file-csv me-1"></i> Export CSV
                            </a>
                            <button class="btn btn-print" onclick="window.print()">
                                <i class="fas fa-print me-1"></i> Print
                            </button>
                        </div>
                    </div>
 
                    <div class="filters-info">
                        <i class="fas fa-info-circle me-2" style="color: #8b5cf6;"></i>
                        <strong>Filters Applied:</strong>
                        Role: <strong><?php echo $filter_role ? ucfirst($filter_role) : 'All'; ?></strong> |
                        Department: <strong><?php echo $filter_dept ?: 'All'; ?></strong> |
                        Registered: <strong><?php echo $date_from ?: 'Start'; ?></strong> to <strong><?php echo $date_to ?: 'Now'; ?></strong>
                    </div>
 
                    <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                        <h5 style="color: #2e1065;">No users found</h5>
                        <p class="text-muted">Try adjusting your filter criteria</p>
                    </div>
                    <?php else: ?>
                    
                    <?php $role_counts = array_count_values(array_column($users, 'role')); ?>
                    <div class="mb-3 d-flex gap-2 flex-wrap">
                        <?php foreach ($role_counts as $r => $cnt): ?>
                        <span class="role-badge role-<?php echo $r; ?>" style="background: <?php echo $r == 'staff' ? '#3b82f6' : ($r == 'finance' ? '#10b981' : '#ef4444'); ?>; color: white;">
                            <?php echo ucfirst($r); ?>: <?php echo $cnt; ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
 
                    <div class="table-responsive">
                        <table class="table table-custom" id="generatedReportTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Staff ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $i => $u): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $i + 1; ?></td>
                                    <td><code><?php echo htmlspecialchars($u['staff_id'] ?? '—'); ?></code></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($u['name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><?php echo htmlspecialchars($u['phone'] ?? '—'); ?></td>
                                    <td><span class="role-badge role-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                                    <td><?php echo htmlspecialchars($u['department'] ?? '—'); ?></td>
                                    <td><span class="<?php echo ($u['status'] ?? 'Active') == 'Active' ? 'status-active' : 'status-inactive'; ?>"><?php echo $u['status'] ?? 'Active'; ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
 
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        $('#generatedReportTable').DataTable({
            "pageLength": 10,
            "lengthChange": false, 
            "language": {
                "search": "<i class='fas fa-search' style='color: #8b5cf6;'></i> Search within results:",
                "paginate": { "next": "<i class='fas fa-chevron-right'></i>", "previous": "<i class='fas fa-chevron-left'></i>" }
            }
        });
    });
</script>
</body>
</html>