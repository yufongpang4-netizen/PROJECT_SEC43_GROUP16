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

// ========== EDIT CLAIM FUNCTIONALITY ==========
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle Edit Claim
    if(isset($_POST['edit_id'])) {
        $edit_id = intval($_POST['edit_id']);
        $claim_type = $_POST['claim_type'];
        $amount = floatval($_POST['amount']);
        $expense_date = $_POST['expense_date'];
        $description = trim($_POST['description']);
        
        // Verify claim belongs to user and is still PENDING
        $check_sql = "SELECT id, status, receipt FROM claims WHERE id = ? AND user_id = ? AND status = 'Pending'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $edit_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows == 0) {
            $error = "Claim cannot be edited. Either it doesn't exist, doesn't belong to you, or has already been processed.";
        } else {
            $claim_data = $check_result->fetch_assoc();
            $old_receipt = $claim_data['receipt'];
            
            // Validate inputs
            if(empty($claim_type) || empty($amount) || empty($expense_date) || empty($description)) {
                $error = "Please fill in all required fields.";
            } elseif(!is_numeric($amount) || $amount <= 0) {
                $error = "Please enter a valid amount.";
            } elseif($amount > 200) {
                $error = "Maximum claim amount per claim is RM 200.00";
            } else {
                // Handle new receipt upload if provided
                $receipt_filename = $old_receipt; // Keep old receipt by default
                
                if(isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0 && $_FILES['receipt']['size'] > 0) {
                    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
                    $file_type = $_FILES['receipt']['type'];
                    $max_file_size = 5 * 1024 * 1024; // 5MB
                    
                    if($_FILES['receipt']['size'] > $max_file_size) {
                        $error = "File size too large. Maximum size is 5MB.";
                    } elseif(in_array($file_type, $allowed_types)) {
                        $upload_dir = '../uploads/receipts/';
                        if(!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
                        $receipt_filename = 'receipt_' . time() . '_' . $user_id . '.' . $file_ext;
                        $target_file = $upload_dir . $receipt_filename;
                        
                        if(move_uploaded_file($_FILES['receipt']['tmp_name'], $target_file)) {
                            // Delete old receipt if exists
                            if($old_receipt && file_exists($upload_dir . $old_receipt)) {
                                unlink($upload_dir . $old_receipt);
                            }
                        } else {
                            $error = "Failed to save the uploaded receipt.";
                        }
                    } else {
                        $error = "Invalid file format. Only PDF, JPG, and PNG are allowed.";
                    }
                }
                
                if(empty($error)) {
                    // Update the claim
                    $update_sql = "UPDATE claims SET claim_type = ?, amount = ?, expense_date = ?, description = ?, receipt = ? WHERE id = ? AND user_id = ? AND status = 'Pending'";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("sdsssii", $claim_type, $amount, $expense_date, $description, $receipt_filename, $edit_id, $user_id);
                    
                    if($update_stmt->execute()) {
                        $success = "Claim #$edit_id has been updated successfully!";
                        logActivity($conn, $user_id, 'Edit Claim', "Edited claim #$edit_id");
                    } else {
                        $error = "Failed to update claim. Please try again.";
                    }
                    $update_stmt->close();
                }
            }
        }
        $check_stmt->close();
    }
    
    // Handle Cancel (delete pending claim)
    elseif(isset($_POST['cancel_id'])) {
        $cancel_id = intval($_POST['cancel_id']);
        
        // First get the receipt filename to delete it
        $receipt_sql = "SELECT receipt FROM claims WHERE id = ? AND user_id = ? AND status = 'Pending'";
        $receipt_stmt = $conn->prepare($receipt_sql);
        $receipt_stmt->bind_param("ii", $cancel_id, $user_id);
        $receipt_stmt->execute();
        $receipt_result = $receipt_stmt->get_result();
        
        if($receipt_data = $receipt_result->fetch_assoc()) {
            $receipt_file = $receipt_data['receipt'];
            
            // Delete the claim
            $stmt = $conn->prepare("DELETE FROM claims WHERE id = ? AND user_id = ? AND status = 'Pending'");
            $stmt->bind_param('ii', $cancel_id, $user_id);
            
            if($stmt->execute() && $stmt->affected_rows > 0) {
                // Delete receipt file if exists
                if($receipt_file && file_exists("../uploads/receipts/" . $receipt_file)) {
                    unlink("../uploads/receipts/" . $receipt_file);
                }
                $success = "Claim #$cancel_id has been cancelled and removed.";
                logActivity($conn, $user_id, 'Cancel Claim', "Cancelled claim #$cancel_id");
            } else {
                $error = "Could not cancel the claim. It may have already been processed.";
            }
            $stmt->close();
        }
        $receipt_stmt->close();
    }
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
                                                <!-- View Button - Available for all claims -->
                                                <button class="btn btn-sm mb-1" style="background: #5BC0BE; color: #0B132B;"
                                                    data-bs-toggle="modal" data-bs-target="#detailModal"
                                                    data-id="<?php echo $claim['id']; ?>"
                                                    data-type="<?php echo htmlspecialchars($claim['claim_type']); ?>"
                                                    data-amount="<?php echo number_format($claim['amount'], 2); ?>"
                                                    data-expense-date="<?php echo $claim['expense_date']; ?>"
                                                    data-status="<?php echo ucfirst($claim['status']); ?>"
                                                    data-desc="<?php echo htmlspecialchars($claim['description']); ?>"
                                                    data-remark="<?php echo htmlspecialchars($claim['finance_comment'] ?? ''); ?>"
                                                    data-receipt="<?php echo htmlspecialchars($claim['receipt'] ?? ''); ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                
                                                <!-- Edit Button - ONLY for PENDING claims -->
                                                <?php if(strtolower($claim['status']) === 'pending'): ?>
                                                <button class="btn btn-sm btn-warning mb-1"
                                                    data-bs-toggle="modal" data-bs-target="#editModal"
                                                    data-id="<?php echo $claim['id']; ?>"
                                                    data-type="<?php echo htmlspecialchars($claim['claim_type']); ?>"
                                                    data-amount="<?php echo $claim['amount']; ?>"
                                                    data-expense-date="<?php echo $claim['expense_date']; ?>"
                                                    data-desc="<?php echo htmlspecialchars($claim['description']); ?>"
                                                    data-receipt="<?php echo htmlspecialchars($claim['receipt'] ?? ''); ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <?php endif; ?>
                                                
                                                <!-- Cancel Button - ONLY for PENDING claims -->
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
 
    <!-- View Detail Modal -->
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
    
    <!-- Edit Claim Modal - ONLY for PENDING claims -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background:#5BC0BE; color:#0B132B;">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Edit Pending Claim
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="edit_id" id="edit-id">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            You can only edit claims while they are in <strong>Pending</strong> status. Once approved or paid, claims cannot be modified.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Claim Type *</label>
                                <select name="claim_type" id="edit-type" class="form-select" required>
                                    <option value="">Select claim type</option>
                                    <option value="Travel">Travel</option>
                                    <option value="Meal">Meal</option>
                                    <option value="Accommodation">Accommodation</option>
                                    <option value="Transportation">Transportation</option>
                                    <option value="Office Supplies">Office Supplies</option>
                                    <option value="Training">Training</option>
                                    <option value="Medical">Medical</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Amount (RM) *</label>
                                <input type="number" name="amount" id="edit-amount" step="0.01" min="0.01" max="200" class="form-control" required>
                                <small class="text-muted">Maximum: RM 200.00 per claim</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Expense Date *</label>
                                <input type="date" name="expense_date" id="edit-expense-date" class="form-control" required max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Receipt (Optional)</label>
                                <input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png" id="edit-receipt">
                                <small class="text-muted">Leave empty to keep current receipt. Max 5MB.</small>
                                <div id="current-receipt" class="mt-2"></div>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold">Description *</label>
                                <textarea name="description" id="edit-description" rows="4" class="form-control" required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" style="background: #5BC0BE; color: #0B132B;">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
 
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Populate view modal with claim data
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
            
            // Format date nicely
            const expenseDate = btn.dataset.expenseDate;
            if(expenseDate) {
                const date = new Date(expenseDate);
                document.getElementById('modal-date').textContent = date.toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
            } else {
                document.getElementById('modal-date').textContent = '-';
            }
            
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
        
        // Populate edit modal with claim data (for PENDING claims only)
        document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
            const btn = e.relatedTarget;
            
            document.getElementById('edit-id').value = btn.dataset.id;
            document.getElementById('edit-type').value = btn.dataset.type;
            document.getElementById('edit-amount').value = btn.dataset.amount;
            document.getElementById('edit-expense-date').value = btn.dataset.expenseDate;
            document.getElementById('edit-description').value = btn.dataset.desc;
            
            // Show current receipt if exists
            const receipt = btn.dataset.receipt;
            const receiptContainer = document.getElementById('current-receipt');
            if (receipt) {
                receiptContainer.innerHTML = `<div class="alert alert-info">
                    <i class="fas fa-paperclip me-2"></i>
                    Current receipt: <a href="../uploads/receipts/${receipt}" target="_blank">View attached file</a>
                    <br><small>Upload a new file if you want to replace it.</small>
                </div>`;
            } else {
                receiptContainer.innerHTML = `<div class="alert alert-secondary">
                    <i class="fas fa-info-circle me-2"></i>
                    No receipt currently attached. You can upload one now.
                </div>`;
            }
        });
        
        // Real-time amount validation in edit modal
        document.getElementById('editModal').addEventListener('shown.bs.modal', function() {
            const amountInput = document.getElementById('edit-amount');
            amountInput.addEventListener('input', function() {
                if(parseFloat(this.value) > 200) {
                    this.setCustomValidity('Amount cannot exceed RM 200.00');
                    this.style.borderColor = '#dc3545';
                } else {
                    this.setCustomValidity('');
                    this.style.borderColor = '#ced4da';
                }
            });
        });
    </script>
</body>
</html>