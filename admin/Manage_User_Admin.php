<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
 
require_once '../db.php';
 
$message      = '';
$message_type = 'success';
$view_user    = null;
 
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $uid = (int)$_GET['toggle_status'];
 
    $chk = $conn->prepare("SELECT role, name, status FROM users WHERE id=?");
    $chk->bind_param("i", $uid);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();
 
    if (!$row) {
        $message      = "User not found.";
        $message_type = 'danger';
    } elseif ($row['role'] === 'admin') {
        $message      = "Cannot alter the status of an Admin account.";
        $message_type = 'danger';
    } elseif ((int)$uid === (int)$_SESSION['user_id']) {
        $message      = "You cannot deactivate your own account.";
        $message_type = 'danger';
    } else {
        $new_status = ($row['status'] === 'Active') ? 'Inactive' : 'Active';
        
        $upd = $conn->prepare("UPDATE users SET status=? WHERE id=?");
        $upd->bind_param("si", $new_status, $uid);
        $upd->execute();
        $upd->close();
        
        $message = "User <strong>" . htmlspecialchars($row['name']) . "</strong> is now <strong>{$new_status}</strong>.";
    }
}
 
// ─── Add User ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $new_name     = trim($_POST['name']);
    $new_staff_id = trim($_POST['staff_id']);
    $new_email    = trim($_POST['email']);
    $new_phone    = trim($_POST['phone']);
    $new_role     = $_POST['role'];
    $new_dept     = trim($_POST['department']);
    $new_pass     = password_hash($_POST['password'], PASSWORD_DEFAULT);
 
    $dup = $conn->prepare("SELECT id FROM users WHERE email=? OR staff_id=?");
    $dup->bind_param("ss", $new_email, $new_staff_id);
    $dup->execute();
    $dup->store_result();
 
    if ($dup->num_rows > 0) {
        $message      = "Email or Staff ID is already registered.";
        $message_type = 'danger';
    } else {
        $ins = $conn->prepare("INSERT INTO users (name, staff_id, email, password, department, phone, role, status) VALUES (?,?,?,?,?,?,?,'Active')");
        $ins->bind_param("sssssss", $new_name, $new_staff_id, $new_email, $new_pass, $new_dept, $new_phone, $new_role);
        $ins->execute();
        $ins->close();
        $message = "New user <strong>" . htmlspecialchars($new_name) . "</strong> added successfully.";
    }
    $dup->close();
}
 
// ─── View User ─────────────────────────────────────────────
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $vid   = (int)$_GET['view'];
    $vstmt = $conn->prepare("SELECT * FROM users WHERE id=?");
    $vstmt->bind_param("i", $vid);
    $vstmt->execute();
    $view_user = $vstmt->get_result()->fetch_assoc();
    $vstmt->close();
}
 
// ─── Search & Filter ───────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$filter_role = $_GET['filter_role'] ?? '';
 
$sql    = "SELECT * FROM users WHERE 1=1";
$params = [];
$types  = '';
 
if ($search !== '') {
    $sql     .= " AND (name LIKE ? OR email LIKE ? OR staff_id LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}
if ($filter_role !== '') {
    $sql     .= " AND role=?";
    $params[] = $filter_role;
    $types   .= 's';
}
$sql .= " ORDER BY id DESC";
 
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Tooltip styling */
        [data-tooltip] {
            position: relative;
            cursor: pointer;
        }
        
        [data-tooltip]:before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background: #0B132B;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            display: none;
            font-weight: normal;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        [data-tooltip]:after {
            content: '';
            position: absolute;
            bottom: 115%;
            left: 50%;
            transform: translateX(-50%);
            border-width: 5px;
            border-style: solid;
            border-color: #0B132B transparent transparent transparent;
            display: none;
            z-index: 1000;
        }
        
        [data-tooltip]:hover:before,
        [data-tooltip]:hover:after {
            display: block;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn-icon {
            transition: transform 0.2s;
        }
        
        .btn-icon:hover {
            transform: scale(1.05);
        }
    </style>
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
                <a class="nav-link active" href="Manage_User_Admin.php"><i class="fas fa-users"></i> Manage Accounts</a>
                <a class="nav-link" href="Generate_Report_Admin.php"><i class="fas fa-chart-bar"></i> Generate Report</a>
                <hr style="border-color:rgba(255,255,255,0.2);">
                <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
 
        <div class="col-md-9 col-lg-10 p-4">
 
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 style="color:white;">
                    <i class="fas fa-users me-2" style="color:#5BC0BE;"></i>
                    Manage Staff & Finance Accounts
                </h2>
                <button class="btn" style="background:#5BC0BE; color:#0B132B;"
                        data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus me-2"></i>Add New User
                </button>
            </div>
 
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
 
            <?php if ($view_user): ?>
            <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header" style="background:#0B132B; color:white;">
                            <h5 class="modal-title">
                                <i class="fas fa-user me-2" style="color:#5BC0BE;"></i>
                                User Details
                            </h5>
                            <a href="Manage_User_Admin.php" class="btn-close btn-close-white"></a>
                        </div>
                        <div class="modal-body">
                            <table class="table table-bordered">
                                <tr><th>ID</th><td><?php echo $view_user['id']; ?></td></tr>
                                <tr><th>Staff ID</th><td><?php echo htmlspecialchars($view_user['staff_id'] ?? '—'); ?></td></tr>
                                <tr><th>Name</th><td><?php echo htmlspecialchars($view_user['name']); ?></td></tr>
                                <tr><th>Email</th><td><?php echo htmlspecialchars($view_user['email']); ?></td></tr>
                                <tr><th>Phone</th><td><?php echo htmlspecialchars($view_user['phone'] ?? '—'); ?></td></tr>
                                <tr><th>Role</th><td><?php echo ucfirst($view_user['role']); ?></td></tr>
                                <tr><th>Department</th><td><?php echo htmlspecialchars($view_user['department'] ?? '—'); ?></td></tr>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        <span class="badge bg-<?php echo $view_user['status'] == 'Active' ? 'success' : 'danger'; ?>">
                                            <?php echo $view_user['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr><th>Registered</th><td><?php echo date('d M Y, H:i', strtotime($view_user['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <a href="Manage_User_Admin.php" class="btn btn-secondary">Close</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
 
            <div class="card border-0 shadow mb-3">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <input type="text" name="search" class="form-control"
                                   placeholder="Search by name, email or staff ID..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="filter_role" class="form-select">
                                <option value="">All Roles</option>
                                <option value="staff"   <?php echo $filter_role === 'staff'   ? 'selected' : ''; ?>>Staff</option>
                                <option value="finance" <?php echo $filter_role === 'finance' ? 'selected' : ''; ?>>Finance</option>
                                <option value="admin"   <?php echo $filter_role === 'admin'   ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn w-100" style="background:#5BC0BE; color:#0B132B;">
                                <i class="fas fa-search me-1"></i> Search
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="Manage_User_Admin.php" class="btn btn-outline-secondary w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
 
            <div class="card border-0 shadow-lg fade-in">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Staff ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users_result->num_rows === 0): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No users found.</td>
                                </tr>
                                <?php endif; ?>
                                <?php while ($user = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($user['staff_id'] ?? '—'); ?></code></td>
                                    <td>
                                        <?php
                                        $icon = match($user['role']) {
                                            'finance' => 'fa-user-tie',
                                            'admin'   => 'fa-user-shield',
                                            default   => 'fa-user'
                                        };
                                        ?>
                                        <i class="fas <?php echo $icon; ?> me-2" style="color:#5BC0BE;"></i>
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php
                                        $badge_bg = match($user['role']) {
                                            'finance' => '#5BC0BE',
                                            'admin'   => '#0B132B',
                                            default   => '#3A506B'
                                        };
                                        ?>
                                        <span class="badge" style="background:<?php echo $badge_bg; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['department'] ?? '—'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['status'] == 'Active' ? 'success' : 'danger'; ?>">
                                            <?php echo $user['status']; ?>
                                        </span>
                                    </td>
                                    <td class="action-buttons">
                                        <!-- View Button -->
                                        <a href="?view=<?php echo $user['id']; ?>"
                                           class="btn btn-sm btn-icon"
                                           style="background:#5BC0BE; color:#0B132B;"
                                           data-tooltip="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($user['role'] !== 'admin' && (int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                                            <?php if ($user['status'] === 'Active'): ?>
                                                <!-- Deactivate Button -->
                                                <a href="?toggle_status=<?php echo $user['id']; ?>"
                                                   class="btn btn-sm btn-danger btn-icon"
                                                   data-tooltip="Deactivate User"
                                                   onclick="return confirm('Deactivate <?php echo htmlspecialchars(addslashes($user['name'])); ?>? They will not be able to log in.')">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                            <?php else: ?>
                                                <!-- Activate Button -->
                                                <a href="?toggle_status=<?php echo $user['id']; ?>"
                                                   class="btn btn-sm btn-success btn-icon"
                                                   data-tooltip="Activate User"
                                                   onclick="return confirm('Reactivate <?php echo htmlspecialchars(addslashes($user['name'])); ?>? They will be able to log in again.')">
                                                    <i class="fas fa-check-circle"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
 
        </div>
    </div>
</div>
 
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#0B132B; color:white;">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2" style="color:#5BC0BE;"></i>
                    Add New User
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Full Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Staff ID *</label>
                            <input type="text" name="staff_id" class="form-control" placeholder="e.g. ST999" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Address *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="+601XXXXXXXX">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Password *</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                        <small class="text-muted">Minimum 6 characters.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Role *</label>
                            <select name="role" class="form-select" required>
                                <option value="staff">Staff</option>
                                <option value="finance">Finance</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Department</label>
                            <select name="department" class="form-select">
                                <option value="">— Select —</option>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Human Resources">Human Resources</option>
                                <option value="Finance">Finance</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Operations">Operations</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" style="background:#5BC0BE; color:#0B132B;">
                        <i class="fas fa-save me-1"></i> Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>