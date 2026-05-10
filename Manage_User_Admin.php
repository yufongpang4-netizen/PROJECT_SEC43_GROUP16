<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';
if(isset($_GET['deactivate'])) {
    $user_id = $_GET['deactivate'];
    $message = "User #$user_id has been DEACTIVATED.";
}

$users = [
    ['id' => 1, 'name' => 'John Staff', 'email' => 'john@utmspace.com', 'role' => 'staff', 'status' => 'Active', 'joined' => '2024-01-15'],
    ['id' => 2, 'name' => 'Sarah Smith', 'email' => 'sarah@utmspace.com', 'role' => 'staff', 'status' => 'Active', 'joined' => '2024-02-20'],
    ['id' => 3, 'name' => 'Jane Finance', 'email' => 'jane@utmspace.com', 'role' => 'finance', 'status' => 'Active', 'joined' => '2024-01-10'],
    ['id' => 4, 'name' => 'Mike Johnson', 'email' => 'mike@utmspace.com', 'role' => 'staff', 'status' => 'Inactive', 'joined' => '2023-11-05'],
    ['id' => 5, 'name' => 'Admin User', 'email' => 'admin@utmspace.com', 'role' => 'admin', 'status' => 'Active', 'joined' => '2024-01-01'],
];
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
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-user-shield fs-1" style="color: #5BC0BE;"></i>
                    <h5 class="mt-2">UTMSpace</h5>
                    <small>Admin Portal</small>
                </div>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link active" href="manage_users.php">
                        <i class="fas fa-users"></i> Manage Accounts
                    </a>
                    <a class="nav-link" href="generate_report.php">
                        <i class="fas fa-chart-bar"></i> Generate Report
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
                        <i class="fas fa-users me-2" style="color: #5BC0BE;"></i>
                        Manage Staff & Finance Accounts
                    </h2>
                    <button class="btn" style="background: #5BC0BE; color: #0B132B;" onclick="alert('Add new user form would open here')">
                        <i class="fas fa-plus me-2"></i>Add New User
                    </button>
                </div>
                
                <?php if($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card border-0 shadow-lg fade-in">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Joined Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td>
                                            <i class="fas <?php echo $user['role'] == 'staff' ? 'fa-user' : ($user['role'] == 'finance' ? 'fa-user-tie' : 'fa-user-shield'); ?> me-2" style="color: #5BC0BE;"></i>
                                            <?php echo $user['name']; ?>
                                        </td>
                                        <td><?php echo $user['email']; ?></td>
                                        <td>
                                            <span class="badge" style="background: <?php echo $user['role'] == 'staff' ? '#3A506B' : ($user['role'] == 'finance' ? '#5BC0BE' : '#0B132B'); ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $user['joined']; ?></td>
                                        <td>
                                            <span class="status-<?php echo strtolower($user['status'] == 'Active' ? 'approved' : 'rejected'); ?>">
                                                <?php echo $user['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm" style="background: #5BC0BE; color: #0B132B;" onclick="alert('View details for <?php echo $user['name']; ?>')">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <?php if($user['status'] == 'Active' && $user['role'] != 'admin'): ?>
                                                <a href="?deactivate=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Deactivate this account?')">
                                                    <i class="fas fa-ban"></i> Deactivate
                                                </a>
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
