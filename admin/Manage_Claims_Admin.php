<?php
// =========================================================================
// SECTION 1: SESSION MANAGEMENT & SECURITY VALIDATION
// Purpose: Secure the page so only authorized admins can access it.
// =========================================================================
session_start(); // Start or resume the existing session

// Security Check: If the user is not logged in or doesn't have the 'admin' role, kick them out.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit(); // Terminate script execution immediately for security
}

// Include the database connection configuration
require_once '../db.php';

// =========================================================================
// SECTION 2: HANDLE FORM SUBMISSION (FORCE CANCEL CLAIM)
// Purpose: Process the admin's request to void/cancel a claim securely.
// =========================================================================
// Check if the request is a POST method and the 'void_id' variable was sent.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['void_id'])) {

    // Sanitize input: Convert the ID to an integer to absolutely prevent SQL injection.
    $vid = intval($_POST['void_id']);

    // Use Prepared Statements to securely update the database.
    $stmt = $conn->prepare("UPDATE claims SET status = 'Cancelled' WHERE id = ?");
    $stmt->bind_param("i", $vid); // 'i' indicates the parameter is an integer

    // Execute the query. If successful AND the logActivity function exists, log this action.
    if ($stmt->execute() && function_exists('logActivity')) {
        logActivity($conn, $_SESSION['user_id'], 'Force Cancel Claim', "Admin force-cancelled claim #$vid");
    }
    $stmt->close(); // Close the statement to free up server resources
}

// =========================================================================
// SECTION 3: FETCH CLAIM STATISTICS (FOR TOP CARDS)
// Purpose: Get the count of claims grouped by their status to display on the filter cards.
// =========================================================================
// Initialize an array with default values of 0 to avoid "undefined index" errors on the frontend.
$status_data = ['Pending' => 0, 'Approved' => 0, 'Paid' => 0, 'Rejected' => 0, 'Cancelled' => 0];

// Execute a highly efficient GROUP BY query to get all counts in a single database call.
$stat_res = $conn->query("SELECT status, COUNT(*) as cnt FROM claims GROUP BY status");

// Loop through the results and populate our $status_data array.
while ($row = $stat_res->fetch_assoc()) {
    if (isset($status_data[$row['status']])) {
        $status_data[$row['status']] = $row['cnt'];
    }
}

// =========================================================================
// SECTION 4: FETCH ALL CLAIMS (MAIN TABLE DATA)
// Purpose: Retrieve all claims and link them to the staff member's name.
// =========================================================================
// Use an INNER JOIN to combine data from the 'claims' table and the 'users' table based on user_id.
// This allows us to display the actual 'name' instead of just a raw 'user_id' number.
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
        /* =========================================================================
           SECTION 6: CUSTOM CSS STYLING
           ========================================================================= */
        /* CSS Variables for UTMSPACE brand colors */
        :root {
            --admin-primary: #2e1065;
            --admin-secondary: #4c1d95;
            --admin-accent: #8b5cf6;
            --admin-bg: #faf5ff;
        }

        body {
            background: var(--admin-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        /* Sidebar Styling */
        .sidebar {
            background: linear-gradient(180deg, #2e1065 0%, #4c1d95 100%);
            height: 100vh;
            color: white;
            position: sticky;
            top: 0;
            overflow-y: auto;
        }

        .sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 5px;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover {
            background: rgba(139, 92, 246, 0.2);
            color: #8b5cf6;
            transform: translateX(5px);
        }

        .sidebar .nav-link.active {
            background: #8b5cf6;
            color: #2e1065;
            font-weight: 600;
        }

        /* Main Content & Header */
        .main-content {
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #2e1065 0%, #4c1d95 100%);
            border-radius: 20px;
            padding: 20px 25px;
            color: white;
            margin-bottom: 25px;
        }

        /* Interactive Status Filter Cards */
        .status-card {
            padding: 15px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            background: white;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
            position: relative;
        }

        .status-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        /* Active state for filter cards (pops out) */
        .status-card.active-filter {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            z-index: 10;
        }

        /* Dimmed state for non-selected filter cards */
        .status-card.dimmed {
            opacity: 0.4;
            transform: scale(0.96);
            filter: grayscale(40%);
            box-shadow: none;
        }

        /* Table container and custom table styling */
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }

        .table-custom th {
            color: #2e1065;
            font-weight: 600;
            padding: 15px;
            border: none;
            background: #f1f5f9;
        }

        .table-custom td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f3e8ff;
        }

        /* UI Badges for Claim Status */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-Pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-Approved {
            background: #d1fae5;
            color: #059669;
        }

        .status-Paid {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-Rejected {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-Cancelled {
            background: #e5e7eb;
            color: #4b5563;
        }

        /* Custom Buttons */
        .btn-view {
            background: #8b5cf6;
            color: white;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            transition: 0.3s;
            border: none;
        }

        .btn-view:hover {
            background: #7c3aed;
            transform: translateY(-2px);
            color: white;
        }

        .btn-void {
            background: #ef4444;
            color: white;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            transition: 0.3s;
            border: none;
        }

        .btn-void:hover {
            background: #dc2626;
            transform: translateY(-2px);
            color: white;
        }

        .btn-void:disabled {
            background: #fca5a5;
            cursor: not-allowed;
            transform: none;
        }

        /* DataTables overrides to match theme */
        .dataTables_length select {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 5px 30px 5px 12px !important;
            margin: 0 5px;
        }

        /* Modal and Animations */
        .modal-custom-header {
            background: linear-gradient(135deg, #2e1065 0%, #4c1d95 100%);
            color: white;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
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
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h3 class="mb-1"><i class="fas fa-file-invoice-dollar me-2"></i>Manage All Claims</h3>
                            <p class="mb-0 opacity-75">Click on the status cards below to quickly filter the table</p>
                        </div>

                        <div id="filterBadge" style="display: none;" class="mt-2 mt-sm-0">
                            <span class="badge bg-light text-dark fs-6 px-3 py-2 rounded-pill shadow-sm">
                                <i class="fas fa-filter" style="color: #8b5cf6;"></i> Filtering: <span id="filterText" class="fw-bold"></span>
                                <i class="fas fa-times ms-2 text-muted" style="cursor:pointer;" onclick="clearFilter()" title="Clear Filter"></i>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4 fade-in">
                    <?php
                    // Define the order and exact keys we want to display based on the $status_data fetched earlier
                    $display_stats = [
                        'Pending'   => $status_data['Pending'],
                        'Approved'  => $status_data['Approved'],
                        'Paid'      => $status_data['Paid'],
                        'Rejected'  => $status_data['Rejected'],
                        'Cancelled' => $status_data['Cancelled']
                    ];

                    // Loop through the array to generate a card for each status
                    foreach ($display_stats as $s => $cnt):
                        // PHP 8+ Match Expression to assign specific theme colors to each status card
                        $color = match ($s) {
                            'Pending'   => '#f59e0b',
                            'Approved'  => '#10b981',
                            'Paid'      => '#3b82f6',
                            'Rejected'  => '#ef4444',
                            'Cancelled' => '#6b7280'
                        };
                    ?>
                        <div class="col-sm-6 col-lg">
                            <div class="status-card filter-btn h-100" data-status="<?php echo $s; ?>" style="border-top: 4px solid <?php echo $color; ?>;" title="Click to filter by <?php echo $s; ?>">
                                <div class="fs-3 fw-bold" style="color: <?php echo $color; ?>;"><?php echo $cnt; ?></div>
                                <div class="small fw-semibold mt-1 text-muted"><?php echo strtoupper($s); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="table-card fade-in">
                    <table class="table table-custom" id="claimsTable" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Staff</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($c = $claims->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold text-muted" data-order="<?php echo $c['id']; ?>">#<?php echo $c['id']; ?></td>

                                    <td><?php echo htmlspecialchars($c['staff_name']); ?></td>
                                    <td><?php echo htmlspecialchars($c['claim_type']); ?></td>
                                    <td class="fw-bold">RM <?php echo number_format($c['amount'], 2); ?></td>

                                    <td><span class="status-badge status-<?php echo $c['status']; ?>"><?php echo $c['status']; ?></span></td>

                                    <td class="text-muted"><?php echo date('d M Y', strtotime($c['submitted_at'])); ?></td>
                                    <td>
                                        <div class="d-flex gap-2">

                                            <button class="btn btn-view"
                                                data-bs-toggle="modal" data-bs-target="#auditModal"
                                                data-id="<?php echo $c['id']; ?>"
                                                data-type="<?php echo htmlspecialchars($c['claim_type']); ?>"
                                                data-amount="<?php echo number_format($c['amount'], 2); ?>"
                                                data-desc="<?php echo htmlspecialchars($c['description']); ?>"
                                                data-receipt="<?php echo htmlspecialchars($c['receipt'] ?? ''); ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>

                                            <form method="POST" id="void-<?php echo $c['id']; ?>">
                                                <input type="hidden" name="void_id" value="<?php echo $c['id']; ?>">

                                                <button type="button" class="btn btn-void"
                                                    onclick="confirmVoid(<?php echo $c['id']; ?>)"
                                                    <?php echo (in_array($c['status'], ['Cancelled', 'Paid', 'Rejected'])) ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-ban"></i> Force Cancel
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="auditModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-custom-header">
                    <h5 class="modal-title"><i class="fas fa-search-dollar me-2"></i>Audit Claim Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Claim ID</label>
                            <p class="fw-bold mb-0">#<span id="modal-id"></span></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Claim Type</label>
                            <p class="fw-bold mb-0" id="modal-type"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Amount</label>
                            <p class="fw-bold mb-0 fs-5" style="color: #8b5cf6;">RM <span id="modal-amount"></span></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Receipt</label>
                            <p class="mb-0" id="modal-receipt"></p>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="text-muted small">Staff Description</label>
                            <div class="p-3 bg-light rounded" id="modal-desc"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let table;
        let currentFilter = '';

        $(document).ready(function() {

            // 1. Initialize DataTables
            table = $('#claimsTable').DataTable({
                "order": [
                    [0, "desc"]
                ], // Automatically sort by the first column (ID) descending (newest first)
                "language": {
                    // Customize the text and add FontAwesome icons to the UI
                    "search": "<i class='fas fa-search' style='color: #8b5cf6;'></i> Search:",
                    "paginate": {
                        "next": "<i class='fas fa-chevron-right'></i>",
                        "previous": "<i class='fas fa-chevron-left'></i>"
                    }
                }
            });

            // 2. Custom Filtering Logic for Status Cards
            $('.filter-btn').on('click', function() {
                let status = $(this).data('status'); // Get the status from the clicked card

                // Toggle Logic: If the user clicks the currently active filter, clear the filter.
                if (currentFilter === status) {
                    clearFilter();
                } else {
                    currentFilter = status;

                    // Advanced DataTables Filtering:
                    // We target column index 4 (Status column).
                    // We use Regex '^' (starts with) and '$' (ends with) for an exact match.
                    // e.g., filtering for "Paid" won't accidentally show "Unpaid" (if that existed).
                    table.column(4).search('^' + status + '$', true, false).draw();

                    // UI Visual Updates: Dim unselected cards, highlight selected card, show badge
                    $('.filter-btn').removeClass('active-filter').addClass('dimmed');
                    $(this).removeClass('dimmed').addClass('active-filter');

                    $('#filterText').text(status);
                    $('#filterBadge').fadeIn();
                }
            });

            // 3. Modal Data Injection
            // Triggered right before the Bootstrap modal becomes visible
            $('#auditModal').on('show.bs.modal', function(e) {
                // 'e.relatedTarget' refers to the specific "View" button that was clicked
                const btn = $(e.relatedTarget);

                // Extract the 'data-*' attributes from the button and inject them into the modal spans
                $('#modal-id').text(btn.data('id'));
                $('#modal-type').text(btn.data('type'));
                $('#modal-amount').text(btn.data('amount'));
                $('#modal-desc').text(btn.data('desc'));

                // Handle the receipt link conditionally
                const receipt = btn.data('receipt');
                if (receipt) {
                    // Render a clickable link opening in a new tab if a file exists
                    $('#modal-receipt').html(`<a href="../uploads/receipts/${receipt}" target="_blank" class="text-decoration-none" style="color: #8b5cf6;"><i class="fas fa-paperclip"></i> View File</a>`);
                } else {
                    $('#modal-receipt').text('No file attached');
                }
            });
        });

        // Function to completely reset the DataTables search and restore UI card states
        function clearFilter() {
            currentFilter = '';
            table.column(4).search('').draw(); // Clear column 4 search
            $('.filter-btn').removeClass('active-filter dimmed'); // Remove all highlight/dim classes
            $('#filterBadge').fadeOut(); // Hide the filtering notification badge
        }

        // Function to trigger a SweetAlert confirmation before submitting the Force Cancel form
        function confirmVoid(id) {
            Swal.fire({
                title: 'Force Cancel Claim #' + id + '?',
                text: "This will void the claim and return the quota to the staff member.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, void it!'
            }).then((result) => {
                // If the user clicks 'Yes', programmatically submit the specific hidden form
                if (result.isConfirmed) {
                    document.getElementById('void-' + id).submit();
                }
            });
        }
    </script>
</body>

</html>