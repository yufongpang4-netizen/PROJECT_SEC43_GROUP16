<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'finance') {
    header("Location: ../login.php");
    exit();
}

require_once '../db.php';

$success = '';
$error = '';
$claim_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("
    SELECT c.id, c.amount, c.claim_type, u.name as staff_name, u.staff_id 
    FROM claims c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.id = ? AND c.status = 'Approved'
");
$stmt->bind_param("i", $claim_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: All_Claim_Finance.php");
    exit();
}

$claim = $result->fetch_assoc();

$mock_bank_suffix = substr(preg_replace("/[^0-9]/", "", md5($claim['staff_id'])), 0, 4);
if(strlen($mock_bank_suffix) < 4) $mock_bank_suffix = '8821';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $update_stmt = $conn->prepare("UPDATE claims SET status = 'Paid' WHERE id = ?");
    $update_stmt->bind_param("i", $claim_id);
    
    if($update_stmt->execute()) {
        $success = "Payment of RM " . number_format($claim['amount'], 2) . " to " . $claim['staff_name'] . " was successful.";
        
        if (function_exists('logActivity')) {
            logActivity($conn, $_SESSION['user_id'], 'Process Payment', "Processed payment of RM " . number_format($claim['amount'], 2) . " for claim #$claim_id");
        }
    } else {
        $error = "Payment failed due to system error.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment - UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <style>
        body { 
            background-color: #f8fafc; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .payment-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px -10px rgba(16, 185, 129, 0.15);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .payment-header {
            background: linear-gradient(135deg, #064e3b 0%, #047857 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .amount-display {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 15px 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px dashed #e5e7eb;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #6b7280; font-weight: 500; }
        .detail-value { color: #064e3b; font-weight: 600; text-align: right; }
        .bank-badge {
            background: #f1f5f9;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #e2e8f0;
        }
        .btn-pay {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4);
            color: white;
        }
        .btn-cancel {
            background: white;
            color: #64748b;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s;
        }
        .btn-cancel:hover { background: #f8fafc; color: #0f2b4d; }
        .secure-badge {
            text-align: center;
            color: #10b981;
            font-size: 0.85rem;
            margin-top: 20px;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center">
    <div class="payment-card animate__animated animate__fadeInUp">
        <div class="payment-header">
            <h5 class="mb-0 opacity-75"><i class="fas fa-shield-alt me-2"></i>UTMSPACE SecurePay</h5>
            <div class="amount-display">RM <?php echo number_format($claim['amount'], 2); ?></div>
            <span class="badge bg-white text-success rounded-pill px-3 py-2">Ready for Transfer</span>
        </div>
        
        <div class="p-4">
            <div class="detail-row">
                <span class="detail-label">Claim Reference</span>
                <span class="detail-value">#<?php echo str_pad($claim['id'], 5, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Claim Type</span>
                <span class="detail-value"><?php echo htmlspecialchars($claim['claim_type']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payee Name</span>
                <span class="detail-value"><?php echo htmlspecialchars($claim['staff_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Transfer To</span>
                <div class="detail-value">
                    <div class="bank-badge">
                        <i class="fas fa-university text-primary"></i>
                        <span>CIMB Bank</span>
                    </div>
                    <div class="mt-1 small text-muted">**** **** **** <?php echo $mock_bank_suffix; ?></div>
                </div>
            </div>

            <form method="POST" id="paymentForm" class="mt-4">
                <button type="submit" class="btn btn-pay">
                    <i class="fas fa-fingerprint me-2"></i>Confirm Transfer
                </button>
                <a href="All_Claim_Finance.php" class="btn btn-cancel d-block text-center text-decoration-none">
                    Cancel
                </a>
            </form>
            
            <div class="secure-badge">
                <i class="fas fa-lock me-1"></i> End-to-End Encrypted Transfer
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const form = document.getElementById('paymentForm');

    form.addEventListener('submit', function(e) {
        <?php if(!$success): ?> 
        e.preventDefault();
        
        Swal.fire({
            title: 'Processing Transfer',
            html: 'Connecting to Secure Banking Gateway...<br><b>Please do not close this window.</b>',
            allowOutsideClick: false,
            showConfirmButton: false,
            background: 'rgba(255, 255, 255, 0.95)',
            backdrop: `rgba(4, 120, 87, 0.4)`,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        setTimeout(() => {
            form.submit();
        }, 2500);
        <?php endif; ?>
    });

    <?php if($success): ?>
        Swal.fire({
            icon: 'success',
            title: 'Transfer Successful!',
            text: '<?php echo addslashes($success); ?>',
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Return to Dashboard',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'All_Claim_Finance.php';
            }
        });
    <?php elseif($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Transfer Failed',
            text: '<?php echo addslashes($error); ?>',
            confirmButtonColor: '#C1272D'
        });
    <?php endif; ?>
</script>

</body>
</html>