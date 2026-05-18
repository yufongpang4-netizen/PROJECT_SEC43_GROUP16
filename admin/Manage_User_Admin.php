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
        $message_type = 'error';
    } elseif ($row['role'] === 'admin') {
        $message      = "Cannot alter the status of an Admin account.";
        $message_type = 'error';
    } elseif ((int)$uid === (int)$_SESSION['user_id']) {
        $message      = "You cannot deactivate your own account.";
        $message_type = 'error';
    } else {
        $new_status = ($row['status'] === 'Active') ? 'Inactive' : 'Active';
        
        $upd = $conn->prepare("UPDATE users SET status=? WHERE id=?");
        $upd->bind_param("si", $new_status, $uid);
        $upd->execute();
        $upd->close();
        
        $message = "User " . addslashes($row['name']) . " is now " . $new_status . ".";
        $message_type = 'success';
    }
}
 
// ─── Add User with VALIDATION ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $new_name     = trim($_POST['name'] ?? '');
    $new_staff_id = trim($_POST['staff_id'] ?? '');
    $new_email    = trim($_POST['email'] ?? '');
    $new_phone    = trim($_POST['phone'] ?? '');
    $new_role     = $_POST['role'] ?? '';
    $new_dept     = trim($_POST['department'] ?? ''); 
    $new_pass     = $_POST['password'] ?? '';
    
    $errors = [];
    
    if (empty($new_name)) { $errors[] = "Full name is required."; } 
    elseif (!preg_match('/^[a-zA-Z\s\'-]{2,50}$/', $new_name)) { $errors[] = "Name must be 2-50 characters (letters, spaces only)."; }
    
    if (empty($new_staff_id)) { $errors[] = "Staff ID is required."; } 
    elseif (!preg_match('/^[a-zA-Z0-9]{5,15}$/', $new_staff_id)) { $errors[] = "Staff ID must be 5-15 characters (letters/numbers)."; }
    
    if (empty($new_email)) { $errors[] = "Email address is required."; } 
    elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Please enter a valid email address."; }

    if (empty($new_phone)) { 
        $errors[] = "Phone number is required."; 
    } elseif (!preg_match('/^(\+?6?01)[0-9]{8,9}$/', $new_phone)) { 
        $errors[] = "Please enter a valid Malaysian phone number! (e.g., 0123456789)"; 
    }
    
    if (empty($new_pass)) { $errors[] = "Password is required."; } 
    elseif (strlen($new_pass) < 6) { $errors[] = "Password must be at least 6 characters long."; } 
    elseif (!preg_match('/[A-Za-z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) { $errors[] = "Password must contain letters and numbers."; }
    
    if (empty($new_role) || !in_array($new_role, ['staff', 'finance', 'admin'])) { $errors[] = "Please select a valid role."; }
    
    if ($new_role == 'staff' && empty($new_dept)) { $errors[] = "Department is required for Staff accounts."; }
    
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
    
    if (empty($errors)) {
        $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);
        if ($new_role == 'finance') { $new_dept = 'Finance'; } 
        elseif ($new_role == 'admin') { $new_dept = NULL; }
        
        $ins = $conn->prepare("INSERT INTO users (name, staff_id, email, password, department, phone, role, status) VALUES (?,?,?,?,?,?,?,'Active')");
        $ins->bind_param("sssssss", $new_name, $new_staff_id, $new_email, $hashed_password, $new_dept, $new_phone, $new_role);
        
        if ($ins->execute()) {
            $message = "New user " . addslashes($new_name) . " added successfully.";
            $message_type = 'success';
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        $ins->close();
    }
    
    if (!empty($errors)) {
        $message = implode("<br>", $errors);
        $message_type = 'error';
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
 
$sql = "SELECT * FROM users ORDER BY id DESC";
$stmt = $conn->prepare($sql);
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
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    
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
        
        /* Table Card */
        .table-card { background: white; border-radius: 20px; border: none; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05); margin-bottom: 25px; padding: 20px;}
        
        .btn-add { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; border: none; border-radius: 12px; padding: 10px 20px; font-weight: 600; transition: all 0.3s ease; }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.4); color: white; }
        
        /* Table */
        .table-custom { margin-bottom: 0; }
        .table-custom thead { background: #f1f5f9; }
        .table-custom th { color: #2e1065; font-weight: 600; padding: 15px; border: none; }
        .table-custom td { padding: 12px 15px; vertical-align: middle; border-bottom: 1px solid #f3e8ff; }
        .table-custom tr:hover { background: #faf5ff; }
        
        /* Role Badges */
        .role-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .role-staff { background: #3b82f6; color: white; }
        .role-finance { background: #10b981; color: white; }
        .role-admin { background: #ef4444; color: white; }
        
        /* Status Badges */
        .status-active { background: #d1fae5; color: #059669; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-inactive { background: #fee2e2; color: #dc2626; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        
        /* Action Buttons */
        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-view { background: #8b5cf6; color: white; border: none; border-radius: 8px; padding: 6px 12px; font-size: 12px; transition: all 0.3s ease; text-decoration: none;}
        .btn-view:hover { background: #7c3aed; transform: translateY(-2px); color:white;}
        .btn-deactivate { background: #ef4444; color: white; border: none; border-radius: 8px; padding: 6px 12px; font-size: 12px; text-decoration: none; }
        .btn-deactivate:hover { background: #dc2626; transform: translateY(-2px); color:white; }
        .btn-activate { background: #10b981; color: white; border: none; border-radius: 8px; padding: 6px 12px; font-size: 12px; text-decoration: none;}
        .btn-activate:hover { background: #059669; transform: translateY(-2px); color:white;}
        
        /* Modal */
        .modal-custom-header { background: linear-gradient(135deg, #2e1065 0%, #4c1d95 100%); color: white; }
        .btn-save { background: #8b5cf6; color: white; border: none; border-radius: 50px; padding: 10px 25px; font-weight: 600; transition: all 0.3s ease; }
        .btn-save:hover { background: #7c3aed; transform: translateY(-2px); }
        
        /* Form */
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 10px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        .form-label { font-weight: 600; color: #2e1065; margin-bottom: 8px; }
        
        .invalid-feedback-custom { color: #dc3545; font-size: 12px; margin-top: 5px; display: block; }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        /* DataTables */
        .dataTables_filter input { border-radius: 10px; border: 1px solid #e5e7eb; padding: 6px 12px; margin-left: 10px; }
        .dataTables_filter input:focus { border-color: #8b5cf6; outline: none; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        .dataTables_filter label { color: #2e1065; font-weight: 500; }
        .page-item.active .page-link { background-color: #8b5cf6 !important; border-color: #8b5cf6 !important; color: white !important; }
        .page-link { color: #2e1065 !important; border-radius: 6px; margin: 0 2px; }
        .dataTables_length label { color: #2e1065; }
        .dataTables_info { color: #6b7280; }
        
        hr { border-color: #f3e8ff; }
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
                    <a class="nav-link active" href="Manage_User_Admin.php"><i class="fas fa-users fa-fw me-2"></i> Manage Accounts</a>
                    <a class="nav-link" href="Generate_Report_Admin.php"><i class="fas fa-chart-bar fa-fw me-2"></i> Generate Report</a>
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
                        <h3 class="mb-1"><i class="fas fa-users me-2" style="color: #8b5cf6;"></i>Manage User Accounts</h3>
                        <p class="mb-0 opacity-75">View, add, and manage staff, finance, and admin accounts</p>
                    </div>
                    <button class="btn btn-add mt-2 mt-sm-0" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus me-2"></i>Add New User
                    </button>
                </div>
            </div>
 
            <?php if ($view_user): ?>
            <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header modal-custom-header">
                            <h5 class="modal-title"><i class="fas fa-user me-2" style="color: #8b5cf6;"></i>User Details</h5>
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
                                <tr><th>Status</th><td><span class="<?php echo $view_user['status'] == 'Active' ? 'status-active' : 'status-inactive'; ?>"><?php echo $view_user['status']; ?></span></td>
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
 
            <!-- Users Table -->
            <div class="table-card fade-in">
                <div class="table-responsive">
                    <table class="table table-custom" id="adminUsersTable">
                        <thead>
                            <tr>
                                <th>Staff ID</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($user['staff_id'] ?? '—'); ?></code></td>
                                <td class="fw-semibold"><i class="fas <?php echo match($user['role']) { 'finance' => 'fa-user-tie', 'admin' => 'fa-user-shield', default => 'fa-user' }; ?> me-2" style="color: #8b5cf6;"></i><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="role-badge role-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                <td><?php echo htmlspecialchars($user['department'] ?? '—'); ?></td>
                                <td><span class="<?php echo $user['status'] == 'Active' ? 'status-active' : 'status-inactive'; ?>"><?php echo $user['status']; ?></span></td>
                                <td class="action-buttons">
                                    <a href="?view=<?php echo $user['id']; ?>" class="btn btn-view"><i class="fas fa-eye me-1"></i> View</a>
                                    <?php if ($user['role'] !== 'admin' && (int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                                        <?php if ($user['status'] === 'Active'): ?>
                                            <a href="#" class="btn btn-deactivate" onclick="confirmAction('Deactivate', '<?php echo addslashes($user['name']); ?>', '?toggle_status=<?php echo $user['id']; ?>')"><i class="fas fa-ban me-1"></i> Deactivate</a>
                                        <?php else: ?>
                                            <a href="#" class="btn btn-activate" onclick="confirmAction('Activate', '<?php echo addslashes($user['name']); ?>', '?toggle_status=<?php echo $user['id']; ?>')"><i class="fas fa-check-circle me-1"></i> Activate</a>
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
            <form method="POST" id="addUserForm">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Staff ID *</label>
                            <input type="text" name="staff_id" id="staff_id" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone *</label>
                        <input type="tel" name="phone" id="phoneInput" class="form-control" required 
                               pattern="^(\+?6?01)[0-9]{8,9}$" 
                               title="Please enter a valid Malaysian phone number (e.g., 0123456789)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" id="password" class="form-control" required>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        $('#adminUsersTable').DataTable({
            "pageLength": 10,
            "language": {
                "search": "<i class='fas fa-search' style='color: #8b5cf6;'></i> Search:",
                "paginate": { "next": "<i class='fas fa-chevron-right'></i>", "previous": "<i class='fas fa-chevron-left'></i>" }
            }
        });
    });

    function toggleDepartment() {
        const role = document.getElementById('role').value;
        const deptContainer = document.getElementById('deptContainer');
        const deptSelect = document.getElementById('department');
        if (role === 'admin') { deptContainer.style.display = 'none'; deptSelect.removeAttribute('required'); } 
        else if (role === 'finance') { deptContainer.style.display = 'block'; deptSelect.value = 'Finance'; deptSelect.disabled = true; deptSelect.removeAttribute('required'); } 
        else { deptContainer.style.display = 'block'; deptSelect.disabled = false; deptSelect.value = ''; deptSelect.setAttribute('required', 'required'); }
    }
    
    const phoneInput = document.getElementById('phoneInput');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9+]/g, '');
        });
    }
    
    function confirmAction(action, name, url) {
        let color = action === 'Deactivate' ? '#dc3545' : '#10b981';
        Swal.fire({
            title: `Are you sure?`,
            text: `Do you want to ${action.toLowerCase()} the account for ${name}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: color,
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Yes, ${action} it!`
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        })
    }

    <?php if($message): ?>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: '<?php echo $message_type === 'success' ? 'success' : 'error'; ?>',
            title: '<?php echo addslashes($message); ?>'
        });
    <?php endif; ?>
</script>
</body>
</html>