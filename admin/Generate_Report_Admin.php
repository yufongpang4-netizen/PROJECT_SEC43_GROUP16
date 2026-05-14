<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
 
require_once '../db.php';
 
// Real schema: id, name, staff_id, email, password, department, phone, role, status, created_at
// This report is a USER ACCOUNT report
 
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
 
// Dynamic department list from actual data
$depts_res   = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
$departments = [];
while ($d = $depts_res->fetch_assoc()) $departments[] = $d['department'];
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
 
        <div class="col-md-3 col-lg-2 sidebar p-3">
            <div class="text-center mb-4">
                <i class="fas fa-user-shield fs-1" style="color:#5BC0BE;"></i>
                <h5 class="mt-2">UTMSpace</h5>
                <small>Admin Portal</small>
            </div>
            <hr style="border-color:rgba(255,255,255,0.2);">
            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard_Admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a class="nav-link" href="Manage_User_Admin.php"><i class="fas fa-users"></i> Manage Accounts</a>
                <a class="nav-link active" href="Generate_Report_Admin.php"><i class="fas fa-chart-bar"></i> Generate Report</a>
                <hr style="border-color:rgba(255,255,255,0.2);">
                <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
 
        <div class="col-md-9 col-lg-10 p-4">
 
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 style="color:white;">
                    <i class="fas fa-chart-bar me-2" style="color:#5BC0BE;"></i>
                    Generate User Reports
                </h2>
            </div>
 
            <div class="card border-0 shadow-lg mb-4">
                <div class="card-body p-4">
                    <h5 style="color:#0B132B;">
                        <i class="fas fa-filter me-2" style="color:#5BC0BE;"></i>
                        Filter Users
                    </h5>
                    <hr>
                    <form method="GET">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Role</label>
                                <select name="filter_role" class="form-select">
                                    <option value="">All Roles</option>
                                    <option value="staff"   <?php echo $filter_role === 'staff'   ? 'selected' : ''; ?>>Staff</option>
                                    <option value="finance" <?php echo $filter_role === 'finance' ? 'selected' : ''; ?>>Finance</option>
                                    <option value="admin"   <?php echo $filter_role === 'admin'   ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Department</label>
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
                            <div class="col-md-2 mb-3">
                                <label class="form-label fw-bold">Registered From</label>
                                <input type="date" name="date_from" class="form-control"
                                       value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label fw-bold">Registered To</label>
                                <input type="date" name="date_to" class="form-control"
                                       value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-2 mb-3 d-flex align-items-end gap-2">
                                <button type="submit" name="generate" class="btn flex-fill"
                                        style="background:#5BC0BE; color:#0B132B;">
                                    <i class="fas fa-chart-line me-1"></i> Generate
                                </button>
                                <a href="Generate_Report_Admin.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
 
            <?php if ($generated): ?>
            <div class="card border-0 shadow-lg fade-in">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 style="color:#0B132B;">
                            <i class="fas fa-users me-2" style="color:#5BC0BE;"></i>
                            User Report
                            <small class="text-muted fs-6 ms-2">
                                (<?php echo count($users); ?> record<?php echo count($users) !== 1 ? 's' : ''; ?>)
                            </small>
                        </h5>
                        <div class="d-flex gap-2">
                            <a href="?generate=1&filter_role=<?php echo urlencode($filter_role); ?>&department=<?php echo urlencode($filter_dept); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&export=csv"
                               class="btn btn-sm" style="background:#1cc88a; color:white;">
                                <i class="fas fa-file-csv me-1"></i> Export CSV
                            </a>
                            <button class="btn btn-sm btn-secondary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i> Print
                            </button>
                        </div>
                    </div>
 
                    <div class="alert alert-light border mb-3">
                        <strong>Filters:</strong>
                        Role: <strong><?php echo $filter_role ?: 'All'; ?></strong> |
                        Department: <strong><?php echo $filter_dept ?: 'All'; ?></strong> |
                        Registered: <strong><?php echo $date_from ?: 'Start'; ?></strong>
                        to <strong><?php echo $date_to ?: 'Now'; ?></strong>
                    </div>
 
                    <?php if (empty($users)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fs-1 d-block mb-2"></i>
                        No users found for the selected filters.
                    </div>
                    <?php else: ?>
 
                    <?php
                    $role_counts = array_count_values(array_column($users, 'role'));
                    $badge_map   = ['staff' => '#3A506B', 'finance' => '#5BC0BE', 'admin' => '#0B132B'];
                    ?>
                    <div class="mb-3 d-flex gap-2 flex-wrap">
                        <?php foreach ($role_counts as $r => $cnt): ?>
                        <span class="badge px-3 py-2" style="background:<?php echo $badge_map[$r] ?? '#aaa'; ?>; font-size:0.85rem;">
                            <?php echo ucfirst($r); ?>: <?php echo $cnt; ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
 
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Staff ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Status</th> <th>Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $i => $u):
                                    $badge_bg = $badge_map[$u['role']] ?? '#aaa';
                                ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><code><?php echo htmlspecialchars($u['staff_id'] ?? '—'); ?></code></td>
                                    <td><?php echo htmlspecialchars($u['name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><?php echo htmlspecialchars($u['phone'] ?? '—'); ?></td>
                                    <td>
                                        <span class="badge" style="background:<?php echo $badge_bg; ?>">
                                            <?php echo ucfirst($u['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['department'] ?? '—'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo ($u['status'] ?? 'Active') == 'Active' ? 'success' : 'danger'; ?>">
                                            <?php echo $u['status'] ?? 'Active'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:#f0f9f9;">
                                    <td colspan="8" class="fw-bold text-end">Total Users:</td>
                                    <td class="fw-bold" style="color:#5BC0BE;"><?php echo count($users); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
 
        </div>
    </div>
</div>
 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
@media print {
    .sidebar, button, a.btn { display: none !important; }
    .col-md-9 { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; }
    body { background: white !important; }
}
</style>
</body>
</html>