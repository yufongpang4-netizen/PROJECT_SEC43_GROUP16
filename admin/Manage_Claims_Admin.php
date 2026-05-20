<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../db.php';

// Handle Delete Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $did = intval($_POST['delete_id']);
    $conn->query("DELETE FROM claims WHERE id = $did");
}

$status_data = ['Pending' => 0, 'Approved' => 0, 'Paid' => 0, 'Rejected' => 0];
$stat_res = $conn->query("SELECT status, COUNT(*) as cnt FROM claims GROUP BY status");
while($row = $stat_res->fetch_assoc()){
    if(isset($status_data[$row['status']])) $status_data[$row['status']] = $row['cnt'];
}

$sql = "SELECT c.*, u.name as staff_name FROM claims c JOIN users u ON c.user_id = u.id ORDER BY c.submitted_at DESC";
$claims = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Claims - Admin | UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <style>
        :root { --admin-primary: #2e1065; --admin-secondary: #4c1d95; --admin-accent: #8b5cf6; --admin-bg: #faf5ff; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--admin-bg); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; }
        
        .sidebar { background: linear-gradient(180deg, #2e1065 0%, #4c1d95 100%); height: 100vh; color: white; position: sticky; top: 0; overflow-y: auto; }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 5px; }
        .sidebar .nav-link { color: rgba(255,255,255,0.85); padding: 12px 20px; margin: 5px 0; border-radius: 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; transform: translateX(5px); }
        .sidebar .nav-link.active { background: #8b5cf6; color: #2e1065; font-weight: 600; }
        
        .main-content { height: 100vh; overflow-y: auto; padding: 20px; }
        .page-header { background: linear-gradient(135deg, #2e1065 0%, #4c1d95 100%); border-radius: 20px; padding: 20px 25px; color: white; margin-bottom: 25px; }
        
        .status-card { padding: 15px; border-radius: 15px; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.05); background: white; transition: transform 0.3s ease; }
        .status-card:hover { transform: translateY(-3px); }
        .table-card { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        
        .table-custom th { color: #2e1065; font-weight: 600; padding: 15px; border: none; background: #f1f5f9; }
        .table-custom td { padding: 12px 15px; vertical-align: middle; border-bottom: 1px solid #f3e8ff; }
        
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .status-Pending { background: #fef3c7; color: #d97706; }
        .status-Approved { background: #d1fae5; color: #059669; }
        .status-Paid { background: #dbeafe; color: #2563eb; }
        .status-Rejected { background: #fee2e2; color: #dc2626; }
        
        .btn-delete { background: #ef4444; color: white; border-radius: 8px; padding: 5px 12px; font-size: 12px; transition: 0.3s; border: none; }
        .btn-delete:hover { background: #dc2626; transform: translateY(-2px); color: white; }
        
        .dataTables_length select { border-radius: 8px; border: 1px solid #e5e7eb; padding: 5px 35px 5px 12px !important; margin: 0 5px; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.5s ease-out; }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0">
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
                    <a class="nav-link active" href="Manage_Claims_Admin.php"><i class="fas fa-file-invoice-dollar fa-fw me-2"></i> Manage Claims</a>
                    <a class="nav-link" href="Generate_Report_Admin.php"><i class="fas fa-chart-bar fa-fw me-2"></i> Generate Report</a>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout</a>
                </nav>
            </div>
        </div>
        
        <div class="col-md-9 col-lg-10 main-content">
            <div class="page-header fade-in">
                <h3 class="mb-1"><i class="fas fa-file-invoice-dollar me-2"></i>Manage All Claims</h3>
                <p class="mb-0 opacity-75">Full administrative control over all records</p>
            </div>
            
            <div class="row g-4 mb-4 fade-in">
                <?php foreach($status_data as $s => $cnt): 
                    $color = match($s){ 'Pending'=>'#f59e0b', 'Approved'=>'#10b981', 'Paid'=>'#3b82f6', 'Rejected'=>'#ef4444' };
                ?>
                <div class="col-md-3">
                    <div class="status-card" style="border-top: 4px solid <?php echo $color; ?>;">
                        <div class="fs-3 fw-bold" style="color: <?php echo $color; ?>;"><?php echo $cnt; ?></div>
                        <div class="small fw-semibold mt-1 text-muted"><?php echo strtoupper($s); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="table-card fade-in">
                <table class="table table-custom" id="claimsTable">
                    <thead>
                        <tr><th>ID</th><th>Staff</th><th>Type</th><th>Amount</th><th>Status</th><th>Date</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php while($c = $claims->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-bold text-muted" data-order="<?php echo $c['id']; ?>">#<?php echo $c['id']; ?></td>
                            <td><?php echo htmlspecialchars($c['staff_name']); ?></td>
                            <td><?php echo htmlspecialchars($c['claim_type']); ?></td>
                            <td class="fw-bold">RM <?php echo number_format($c['amount'], 2); ?></td>
                            <td><span class="status-badge status-<?php echo $c['status']; ?>"><?php echo $c['status']; ?></span></td>
                            <td class="text-muted"><?php echo date('d M Y', strtotime($c['submitted_at'])); ?></td>
                            <td>
                                <form method="POST" id="del-<?php echo $c['id']; ?>">
                                    <input type="hidden" name="delete_id" value="<?php echo $c['id']; ?>">
                                    <button type="button" class="btn btn-delete" onclick="confirmDelete(<?php echo $c['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script> 
    $(document).ready(function() { 
        $('#claimsTable').DataTable({
            "order": [[ 0, "desc" ]],
            "language": {
                "search": "<i class='fas fa-search' style='color: #8b5cf6;'></i> Search:",
                "paginate": { "next": "<i class='fas fa-chevron-right'></i>", "previous": "<i class='fas fa-chevron-left'></i>" }
            }
        }); 
    }); 

    function confirmDelete(id) {
        Swal.fire({
            title: 'Delete Claim #' + id + '?',
            text: "This action cannot be undone.",
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('del-'+id).submit();
            }
        });
    }
</script>
</body>
</html>