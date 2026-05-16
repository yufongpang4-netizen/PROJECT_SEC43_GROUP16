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
 
// ─── Add User with VALIDATION ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $new_name     = trim($_POST['name']);
    $new_staff_id = trim($_POST['staff_id']);
    $new_email    = trim($_POST['email']);
    $new_phone    = trim($_POST['phone']);
    $new_role     = $_POST['role'];
    $new_dept     = trim($_POST['department']);
    $new_pass     = $_POST['password'];
    
    // ========== VALIDATION RULES ==========
    $errors = [];
    
    // 1. Name validation
    if (empty($new_name)) {
        $errors[] = "Full name is required.";
    } elseif (!preg_match('/^[a-zA-Z\s\'-]{2,50}$/', $new_name)) {
        $errors[] = "Name must be 2-50 characters and can only contain letters, spaces, hyphens, and apostrophes.";
    }
    
    // 2. Staff ID validation
    if (empty($new_staff_id)) {
        $errors[] = "Staff ID is required.";
    } elseif (!preg_match('/^[a-zA-Z0-9]{5,15}$/', $new_staff_id)) {
        $errors[] = "Staff ID must be 5-15 characters and can only contain letters and numbers.";
    }
    
    // 3. Email validation
    if (empty($new_email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address (e.g., name@domain.com).";
    }
    
    // 4. Phone validation (optional but must be valid if provided)
    if (!empty($new_phone) && !preg_match('/^(\+?6?01)[0-9]{8,9}$/', $new_phone)) {
        $errors[] = "Please enter a valid phone number! Example: 0123456789 or +60123456789";
    }
    
    // 5. Password validation
    if (empty($new_pass)) {
        $errors[] = "Password is required.";
    } elseif (strlen($new_pass) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    } elseif (!preg_match('/[A-Za-z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) {
        $errors[] = "Password must contain at least 1 letter and 1 number.";
    }
    
    // 6. Role validation
    if (empty($new_role) || !in_array($new_role, ['staff', 'finance', 'admin'])) {
        $errors[] = "Please select a valid role.";
    }
    
    // 7. Department validation - required for staff, auto for finance
    if ($new_role == 'staff' && empty($new_dept)) {
        $errors[] = "Department is required for Staff accounts.";
    }
    
    // 8. Check for duplicate email or staff_id
    if (empty($errors)) {
        $dup = $conn->prepare("SELECT id FROM users WHERE email = ? OR staff_id = ?");
        $dup->bind_param("ss", $new_email, $new_staff_id);
        $dup->execute();
        $dup->store_result();
        
        if ($dup->num_rows > 0) {
            $errors[] = "Email or Staff ID is already registered.";
        }
        $dup->close();
    }
    
    // If no errors, proceed with insertion
    if (empty($errors)) {
        $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);
        
        // Set department based on role
        if ($new_role == 'finance') {
            $new_dept = 'Finance';
        } elseif ($new_role == 'admin') {
            $new_dept = NULL;
        }
        
        $ins = $conn->prepare("INSERT INTO users (name, staff_id, email, password, department, phone, role, status) VALUES (?,?,?,?,?,?,?,'Active')");
        $ins->bind_param("sssssss", $new_name, $new_staff_id, $new_email, $hashed_password, $new_dept, $new_phone, $new_role);
        
        if ($ins->execute()) {
            $message = "New user <strong>" . htmlspecialchars($new_name) . "</strong> added successfully.";
            $message_type = 'success';
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        $ins->close();
    }
    
    // Display errors if any
    if (!empty($errors)) {
        $message = implode("<br>", $errors);
        $message_type = 'danger';
    }
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
    <title>Manage Users - Admin | UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Admin Dashboard - Soft Purple Theme */
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
        
        body {
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        .container-fluid { height: 100%; overflow: hidden; }
        .row.g-0 { height: 100%; }
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #4c1d95 0%, #6d28d9 100%);
            height: 100vh;
            color: white;
            transition: all 0.3s ease;
            overflow-y: auto;
            position: sticky;
            top: 0;
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
            color: #4c1d95;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
        }
        
        .main-content::-webkit-scrollbar { width: 8px; }
        .main-content::-webkit-scrollbar-track { background: #e5e7eb; border-radius: 10px; }
        .main-content::-webkit-scrollbar-thumb { background: #8b5cf6; border-radius: 10px; }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #4c1d95 0%, #6d28d9 100%);
            border-radius: 20px;
            padding: 20px 25px;
            color: white;
            margin-bottom: 25px;
        }
        
        /* Search Card */
        .search-card, .table-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        
        .form-label {
            font-weight: 600;
            color: #4c1d95;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 10px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .btn-search {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.4);
            color: white;
        }
        
        .btn-reset {
            background: #e5e7eb;
            color: #4c1d95;
            border: none;
            border-radius: 12px;
            padding: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-reset:hover {
            background: #d1d5db;
            transform: translateY(-2px);
        }
        
        .btn-add {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.4);
            color: white;
        }
        
        /* Table */
        .table-custom { margin-bottom: 0; }
        .table-custom thead { background: #f1f5f9; }
        .table-custom th { color: #4c1d95; font-weight: 600; padding: 15px; border: none; }
        .table-custom td { padding: 12px 15px; vertical-align: middle; border-bottom: 1px solid #eef2ff; }
        .table-custom tr:hover { background: #faf5ff; }
        
        .role-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .role-staff { background: #3b82f6; color: white; }
        .role-finance { background: #10b981; color: white; }
        .role-admin { background: #ef4444; color: white; }
        
        .status-active { background: #d1fae5; color: #059669; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-inactive { background: #fee2e2; color: #dc2626; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        
        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-view { background: #8b5cf6; color: white; border: none; border-radius: 8px; padding: 6px 12px; font-size: 12px; transition: all 0.3s ease; }
        .btn-view:hover { background: #7c3aed; transform: translateY(-2px); }
        .btn-deactivate { background: #ef4444; color: white; border: none; border-radius: 8px; padding: 6px 12px; font-size: 12px; }
        .btn-deactivate:hover { background: #dc2626; transform: translateY(-2px); }
        .btn-activate { background: #10b981; color: white; border: none; border-radius: 8px; padding: 6px 12px; font-size: 12px; }
        .btn-activate:hover { background: #059669; transform: translateY(-2px); }
        
        /* Modal */
        .modal-custom-header {
            background: linear-gradient(135deg, #4c1d95 0%, #6d28d9 100%);
            color: white;
        }
        .btn-save { background: #8b5cf6; color: white; border: none; border-radius: 50px; padding: 10px 25px; font-weight: 600; transition: all 0.3s ease; }
        .btn-save:hover { background: #7c3aed; transform: translateY(-2px); }
        
        /* Validation styles */
        .invalid-feedback-custom { color: #dc3545; font-size: 12px; margin-top: 5px; display: block; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        
        [data-tooltip] { position: relative; cursor: pointer; }
        [data-tooltip]:before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background: #4c1d95;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
            display: none;
        }
        [data-tooltip]:after {
            content: '';
            position: absolute;
            bottom: 115%;
            left: 50%;
            transform: translateX(-50%);
            border-width: 5px;
            border-style: solid;
            border-color: #4c1d95 transparent transparent transparent;
            display: none;
        }
        [data-tooltip]:hover:before, [data-tooltip]:hover:after { display: block; }
        
        @media (max-width: 768px) {
            .sidebar { height: auto; position: relative; }
            .main-content { height: auto; overflow-y: visible; }
            .action-buttons { flex-direction: column; gap: 5px; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row g-0">
 
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 sidebar">
            <div class="p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-user-shield fs-1" style="color:#5BC0BE;"></i>
                    <h5 class="mt-2">UTMSPACE</h5>
                    <small>Admin Portal</small>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard_Admin.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
                    <a class="nav-link active" href="Manage_User_Admin.php"><i class="fas fa-users me-2"></i> Manage Accounts</a>
                    <a class="nav-link" href="Generate_Report_Admin.php"><i class="fas fa-chart-bar me-2"></i> Generate Report</a>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                </nav>
            </div>
        </div>
 
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 main-content">
 
            <div class="page-header fade-in">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h3 class="mb-1"><i class="fas fa-users me-2" style="color: #5BC0BE;"></i>Manage User Accounts</h3>
                        <p class="mb-0 opacity-75">View, add, and manage staff, finance, and admin accounts</p>
                    </div>
                    <button class="btn btn-add mt-2 mt-sm-0" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus me-2"></i>Add New User
                    </button>
                </div>
            </div>
 
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show fade-in" role="alert">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
 
            <?php if ($view_user): ?>
            <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header modal-custom-header">
                            <h5 class="modal-title"><i class="fas fa-user me-2" style="color: #5BC0BE;"></i>User Details</h5>
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
                                <tr><th>Status</th><td><span class="<?php echo $view_user['status'] == 'Active' ? 'status-active' : 'status-inactive'; ?>"><?php echo $view_user['status']; ?></span></td></tr>
                                <tr><th>Registered</th><td><?php echo date('d M Y, H:i', strtotime($view_user['created_at'])); ?></td></tr>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <a href="Manage_User_Admin.php" class="btn btn-secondary">Close</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
 
            <!-- Search Card -->
            <div class="search-card fade-in">
                <div class="card-body p-4">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search by name, email or staff ID..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filter by Role</label>
                            <select name="filter_role" class="form-select">
                                <option value="">All Roles</option>
                                <option value="staff" <?php echo $filter_role === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                <option value="finance" <?php echo $filter_role === 'finance' ? 'selected' : ''; ?>>Finance</option>
                                <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-search w-100"><i class="fas fa-search me-1"></i> Search</button>
                        </div>
                        <div class="col-md-2">
                            <a href="Manage_User_Admin.php" class="btn btn-reset w-100"><i class="fas fa-sync-alt me-1"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>
 
            <!-- Users Table -->
            <div class="table-card fade-in">
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Staff ID</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users_result->num_rows === 0): ?>
                            <tr><td colspan="7" class="text-center py-5">No users found.</td></tr>
                            <?php endif; ?>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($user['staff_id'] ?? '—'); ?></code></td>
                                <td class="fw-semibold"><i class="fas <?php echo match($user['role']) { 'finance' => 'fa-user-tie', 'admin' => 'fa-user-shield', default => 'fa-user' }; ?> me-2" style="color: #8b5cf6;"></i><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="role-badge role-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                <td><?php echo htmlspecialchars($user['department'] ?? '—'); ?></td>
                                <td><span class="<?php echo $user['status'] == 'Active' ? 'status-active' : 'status-inactive'; ?>"><?php echo $user['status']; ?></span></td>
                                <td class="action-buttons">
                                    <a href="?view=<?php echo $user['id']; ?>" class="btn btn-view" data-tooltip="View Details"><i class="fas fa-eye me-1"></i> View</a>
                                    <?php if ($user['role'] !== 'admin' && (int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                                        <?php if ($user['status'] === 'Active'): ?>
                                            <a href="?toggle_status=<?php echo $user['id']; ?>" class="btn btn-deactivate" onclick="return confirm('Deactivate <?php echo addslashes($user['name']); ?>?')"><i class="fas fa-ban me-1"></i> Deactivate</a>
                                        <?php else: ?>
                                            <a href="?toggle_status=<?php echo $user['id']; ?>" class="btn btn-activate" onclick="return confirm('Activate <?php echo addslashes($user['name']); ?>?')"><i class="fas fa-check-circle me-1"></i> Activate</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div style="height: 20px;"></div>
        </div>
    </div>
</div>
 
<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-custom-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addUserForm" onsubmit="return validateForm()">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                            <div id="nameFeedback" class="invalid-feedback-custom"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Staff ID *</label>
                            <input type="text" name="staff_id" id="staff_id" class="form-control" placeholder="e.g. ST12345" required>
                            <div id="staffIdFeedback" class="invalid-feedback-custom"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                        <div id="emailFeedback" class="invalid-feedback-custom"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="phone" class="form-control" placeholder="0123456789 or +60123456789">
                        <div id="phoneFeedback" class="invalid-feedback-custom"></div>
                        <small class="text-muted">Malaysian format: 0123456789 or +60123456789</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                        <div id="passwordFeedback" class="invalid-feedback-custom"></div>
                        <small class="text-muted">Minimum 6 characters with at least 1 letter and 1 number</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role" id="role" class="form-select" required onchange="toggleDepartment()">
                                <option value="staff">Staff</option>
                                <option value="finance">Finance</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="deptContainer">
                            <label class="form-label">Department</label>
                            <select name="department" id="department" class="form-select">
                                <option value="">— Select —</option>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Human Resources">Human Resources</option>
                                <option value="Finance">Finance</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Operations">Operations</option>
                            </select>
                            <div id="deptFeedback" class="invalid-feedback-custom"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-save"><i class="fas fa-save me-1"></i> Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>
 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleDepartment() {
        const role = document.getElementById('role').value;
        const deptContainer = document.getElementById('deptContainer');
        const deptSelect = document.getElementById('department');
        
        if (role === 'admin') {
            deptContainer.style.display = 'none';
            deptSelect.removeAttribute('required');
        } else if (role === 'finance') {
            deptContainer.style.display = 'block';
            deptSelect.value = 'Finance';
            deptSelect.disabled = true;
            deptSelect.removeAttribute('required');
        } else {
            deptContainer.style.display = 'block';
            deptSelect.disabled = false;
            deptSelect.value = '';
            deptSelect.setAttribute('required', 'required');
        }
    }
    
    function validateName() {
        const name = document.getElementById('name').value;
        const feedback = document.getElementById('nameFeedback');
        const regex = /^[a-zA-Z\s\'-]{2,50}$/;
        if (!name) { feedback.innerHTML = 'Full name is required.'; return false; }
        else if (!regex.test(name)) { feedback.innerHTML = 'Name must be 2-50 characters (letters, spaces, hyphens only).'; return false; }
        else { feedback.innerHTML = ''; return true; }
    }
    
    function validateStaffId() {
        const staffId = document.getElementById('staff_id').value;
        const feedback = document.getElementById('staffIdFeedback');
        const regex = /^[a-zA-Z0-9]{5,15}$/;
        if (!staffId) { feedback.innerHTML = 'Staff ID is required.'; return false; }
        else if (!regex.test(staffId)) { feedback.innerHTML = 'Staff ID must be 5-15 characters (letters and numbers only).'; return false; }
        else { feedback.innerHTML = ''; return true; }
    }
    
    function validateEmail() {
        const email = document.getElementById('email').value;
        const feedback = document.getElementById('emailFeedback');
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email) { feedback.innerHTML = 'Email address is required.'; return false; }
        else if (!regex.test(email)) { feedback.innerHTML = 'Please enter a valid email address.'; return false; }
        else { feedback.innerHTML = ''; return true; }
    }
    
    function validatePhone() {
        const phone = document.getElementById('phone').value;
        const feedback = document.getElementById('phoneFeedback');
        const regex = /^(\+?6?01)[0-9]{8,9}$/;
        if (phone && !regex.test(phone)) { feedback.innerHTML = 'Please enter a valid Malaysian phone number.'; return false; }
        else { feedback.innerHTML = ''; return true; }
    }
    
    function validatePassword() {
        const password = document.getElementById('password').value;
        const feedback = document.getElementById('passwordFeedback');
        const hasLetter = /[A-Za-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        if (!password) { feedback.innerHTML = 'Password is required.'; return false; }
        else if (password.length < 6) { feedback.innerHTML = 'Password must be at least 6 characters.'; return false; }
        else if (!hasLetter || !hasNumber) { feedback.innerHTML = 'Password must contain at least 1 letter and 1 number.'; return false; }
        else { feedback.innerHTML = ''; return true; }
    }
    
    function validateDepartment() {
        const role = document.getElementById('role').value;
        const dept = document.getElementById('department').value;
        const feedback = document.getElementById('deptFeedback');
        if (role === 'staff' && !dept) { feedback.innerHTML = 'Department is required for Staff accounts.'; return false; }
        else { feedback.innerHTML = ''; return true; }
    }
    
    // Add event listeners
    document.getElementById('name').addEventListener('input', validateName);
    document.getElementById('staff_id').addEventListener('input', validateStaffId);
    document.getElementById('email').addEventListener('input', validateEmail);
    document.getElementById('phone').addEventListener('input', validatePhone);
    document.getElementById('password').addEventListener('input', validatePassword);
    document.getElementById('role').addEventListener('change', validateDepartment);
    document.getElementById('department').addEventListener('change', validateDepartment);
    
    function validateForm() {
        return validateName() && validateStaffId() && validateEmail() && validatePhone() && validatePassword() && validateDepartment();
    }
    
    // Reset form when modal opens
    document.getElementById('addUserModal').addEventListener('show.bs.modal', function() {
        document.getElementById('addUserForm').reset();
        document.querySelectorAll('.invalid-feedback-custom').forEach(el => el.innerHTML = '');
        toggleDepartment();
    });
</script>
</body>
</html>
