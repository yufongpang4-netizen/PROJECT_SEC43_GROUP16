<?php
session_start();
require_once "../db.php";
 
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../login.php");
    exit();
}
 
$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';
 
// ─── Handle Cancel (delete pending claim) ───────────────────────────
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_id'])) {
    $cancel_id = intval($_POST['cancel_id']);
 
    // Only allow cancelling own pending claims
    $stmt = $conn->prepare("DELETE FROM claims WHERE id = ? AND user_id = ? AND status = 'Pending'");
    $stmt->bind_param('ii', $cancel_id, $user_id);
 
    if($stmt->execute() && $stmt->affected_rows > 0) {
        $success = "Claim #$cancel_id has been cancelled and removed.";
    } else {
        $error = "Could not cancel the claim. It may have already been processed.";
    }
    $stmt->close();
}
 
// ─── Filter ──────────────────────────────────────────────────────────
$status_filter = $_GET['status'] ?? 'All';
$status_filter = ucfirst($status_filter);
 
$where  = "WHERE user_id = ?";
$params = [$user_id];
$types  = 'i';
 
if($status_filter !== 'All') {
    $where   .= " AND status = ?";
    $params[] = $status_filter;
    $types   .= 's';
}
 
$stmt = $conn->prepare("
    SELECT id, claim_type, amount, expense_date, status, description, receipt, finance_comment, submitted_at
    FROM claims
    $where
    ORDER BY submitted_at DESC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
 
// Counts for filter badges
$counts_result = $conn->prepare("SELECT status, COUNT(*) as c FROM claims WHERE user_id = ? GROUP BY status");
$counts_result->bind_param('i', $user_id);
$counts_result->execute();
$counts_rows = $counts_result->get_result()->fetch_all(MYSQLI_ASSOC);
$counts = ['All' => 0];
foreach($counts_rows as $r) {
    $counts[$r['status']] = $r['c'];
    $counts['All'] += $r['c'];
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
                    <a href="New_Claim_Staff.php" class="btn" style="background: #5BC0BE; color: #0B132B;">
                        <i class="fas fa-plus me-1"></i> New Claim
                    </a>
                </div>
 
                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
 
                <div class="mb-3">
                    <?php
                    $tab_statuses = [
                        'All'      => 'All',
                        'Pending'  => 'Pending',
                        'Approved' => 'Approved',
                        'Paid'     => 'Paid',
                        'Rejected' => 'Rejected',
                    ];
                    foreach($tab_statuses as $val => $label):
                        $active = ($status_filter === $val) ? 'active' : '';
                        $count  = $counts[$val] ?? 0;
                    ?>
                    <a href="?status=<?php echo $val; ?>"
                       class="btn btn-sm me-1 mb-1 <?php echo $active ? '' : 'btn-outline-secondary'; ?>"
                       style="<?php echo $active ? 'background:#5BC0BE; color:#0B132B; border-color:#5BC0BE;' : 'color:white; border-color:rgba(255,255,255,0.3);'; ?>">
                        <?php echo $label; ?>
                        <span class="badge ms-1" style="background:<?php echo $active ? '#0B132B' : 'rgba(255,255,255,0.2)'; ?>">
                            <?php echo $count; ?>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
 
                <div class="card border-0 shadow-lg fade-in">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Submitted</th>
                                        <th>Type</th>
                                        <th>Amount (RM)</th>
                                        <th>Expense Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($claims)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="fas fa-folder-open fa-3x mb-3 d-block" style="color:#5BC0BE; opacity:0.5;"></i>
                                                No claims found.
                                                <a href="New_Claim_Staff.php" class="d-block mt-2" style="color:#5BC0BE;">Submit your first claim →</a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($claims as $i => $claim): ?>
                                        <tr>
                                            <td><?php echo $i + 1; ?></td>
                                            <td><?php echo date('d M Y', strtotime($claim['submitted_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($claim['claim_type']); ?></td>
                                            <td class="fw-bold">RM <?php echo number_format($claim['amount'], 2); ?></td>
                                            <td><?php echo $claim['expense_date'] ? date('d M Y', strtotime($claim['expense_date'])) : '-'; ?></td>
                                            <td>
                                                <?php
                                                $icons = [
                                                    'pending'  => 'fa-clock',
                                                    'approved' => 'fa-check',
                                                    'paid'     => 'fa-dollar-sign',
                                                    'rejected' => 'fa-times',
                                                ];
                                                $icon = $icons[strtolower($claim['status'])] ?? 'fa-circle';
                                                ?>
                                                <span class="status-<?php echo strtolower($claim['status']); ?>">
                                                    <i class="fas <?php echo $icon; ?> me-1"></i>
                                                    <?php echo ucfirst($claim['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm mb-1" style="background: #5BC0BE; color: #0B132B;"
                                                    data-bs-toggle="modal" data-bs-target="#detailModal"
                                                    data-id="<?php echo $claim['id']; ?>"
                                                    data-type="<?php echo htmlspecialchars($claim['claim_type']); ?>"
                                                    data-amount="<?php echo number_format($claim['amount'], 2); ?>"
                                                    data-date="<?php echo $claim['expense_date'] ? date('d M Y', strtotime($claim['expense_date'])) : '-'; ?>"
                                                    data-status="<?php echo ucfirst($claim['status']); ?>"
                                                    data-desc="<?php echo htmlspecialchars($claim['description']); ?>"
                                                    data-remark="<?php echo htmlspecialchars($claim['finance_comment'] ?? ''); ?>"
                                                    data-receipt="<?php echo htmlspecialchars($claim['receipt'] ?? ''); ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
 
                                                <?php if(strtolower($claim['status']) === 'pending'): ?>
                                                <form method="POST" class="d-inline"
                                                      onsubmit="return confirm('Cancel and delete claim #<?php echo $claim['id']; ?>? This cannot be undone.');">
                                                    <input type="hidden" name="cancel_id" value="<?php echo $claim['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger mb-1">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <?php if(!empty($claims)): ?>
                                <tfoot>
                                    <tr style="background:#f8f9fa;">
                                        <td colspan="3" class="fw-bold text-end">Total Amount:</td>
                                        <td colspan="4" class="fw-bold" style="color:#5BC0BE;">
                                            RM <?php echo number_format(array_sum(array_column($claims, 'amount')), 2); ?>
                                        </td>
                                    </tr>
                                </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
 
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background:#0B132B; color:white;">
                    <h5 class="modal-title">
                        <i class="fas fa-receipt me-2" style="color:#5BC0BE;"></i>
                        Claim Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Claim ID</label>
                            <p class="fw-bold mb-0">#<span id="modal-id"></span></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Status</label>
                            <p class="mb-0"><span id="modal-status" class="badge fs-6"></span></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Claim Type</label>
                            <p class="fw-bold mb-0" id="modal-type"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Amount</label>
                            <p class="fw-bold mb-0 fs-5" style="color:#5BC0BE;">RM <span id="modal-amount"></span></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Expense Date</label>
                            <p class="mb-0" id="modal-date"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Receipt</label>
                            <p class="mb-0" id="modal-receipt-container"></p>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="text-muted small">Description</label>
                            <p class="mb-0" id="modal-desc"></p>
                        </div>
                        <div class="col-12" id="modal-remark-wrapper" style="display:none;">
                            <label class="text-muted small">Finance Remark</label>
                            <div class="alert alert-info mb-0" id="modal-remark"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
 
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Populate modal with claim data
        document.getElementById('detailModal').addEventListener('show.bs.modal', function(e) {
            const btn    = e.relatedTarget;
            const status = btn.dataset.status.toLowerCase();
            const statusColors = {
                'pending':  '#f59e0b',
                'approved': '#10b981',
                'paid':     '#5BC0BE',
                'rejected': '#ef4444'
            };
 
            document.getElementById('modal-id').textContent     = btn.dataset.id;
            document.getElementById('modal-type').textContent   = btn.dataset.type;
            document.getElementById('modal-amount').textContent = btn.dataset.amount;
            document.getElementById('modal-date').textContent   = btn.dataset.date;
            document.getElementById('modal-desc').textContent   = btn.dataset.desc;
 
            const statusEl = document.getElementById('modal-status');
            statusEl.textContent        = btn.dataset.status;
            statusEl.style.background   = statusColors[status] || '#888';
            statusEl.style.color        = 'white';
 
            const receipt = btn.dataset.receipt;
            const receiptContainer = document.getElementById('modal-receipt-container');
            if (receipt) {
                receiptContainer.innerHTML = `<a href="../uploads/receipts/${receipt}" target="_blank" class="text-decoration-none" style="color: #5BC0BE;"><i class="fas fa-paperclip"></i> View Attached Receipt</a>`;
            } else {
                receiptContainer.textContent = 'No receipt attached';
            }
 
            const remarkWrapper = document.getElementById('modal-remark-wrapper');
            const remark        = btn.dataset.remark;
            if(remark) {
                remarkWrapper.style.display = 'block';
                document.getElementById('modal-remark').textContent = remark;
            } else {
                remarkWrapper.style.display = 'none';
            }
        });
    </script>
</body>
</html>