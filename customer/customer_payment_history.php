<?php
include('../includes/db_connect.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    redirect('index.php');
    exit;
}

$customer_id = intval($_SESSION['customer_id']);
$customer_name = 'Customer';

$stmt = $conn->prepare("SELECT Name FROM customer WHERE Customer_ID = ?"); $stmt->bind_param("i", $customer_id); $stmt->execute(); $res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $customer_name = htmlspecialchars($res->fetch_assoc()['Name']);
}

// Fetch all payments for this customer with bill details
$stmt = $conn->prepare("
    SELECT
        p.Payment_ID,
        p.Bill_Type,
        p.Bill_ID,
        p.Amount_Paid,
        p.Date_of_Payment,
        p.Mode_of_Payment,
        CASE
            WHEN p.Bill_Type = 'Electric' THEN eb.Bill_Amount
            WHEN p.Bill_Type = 'Water' THEN wb.Bill_Amount
        END as Bill_Amount,
        CASE
            WHEN p.Bill_Type = 'Electric' THEN eb.Units_Consumed
            ELSE NULL
        END as Units_Consumed,
        CASE
            WHEN p.Bill_Type = 'Water' THEN wb.Consumption_Liters
            ELSE NULL
        END as Consumption_Liters
    FROM payment p
    LEFT JOIN electric_bill eb ON p.Bill_Type = 'Electric' AND p.Bill_ID = eb.Bill_ID
    LEFT JOIN water_bill wb ON p.Bill_Type = 'Water' AND p.Bill_ID = wb.Bill_ID
    WHERE (eb.Customer_ID = ? OR wb.Customer_ID = ?)
    ORDER BY p.Date_of_Payment DESC, p.Payment_ID DESC
");
$stmt->bind_param("ii", $customer_id, $customer_id);
$stmt->execute();
$result = $stmt->get_result();

// Calculate summary statistics
$total_paid = 0;
$payment_count = 0;
$payment_modes = ['Cash' => 0, 'Online' => 0, 'UPI' => 0, 'Card' => 0];
$monthly_payments = [];

$result->data_seek(0);
while ($row = $result->fetch_assoc()) {
    $total_paid += $row['Amount_Paid'];
    $payment_count++;
    if (isset($payment_modes[$row['Mode_of_Payment']])) {
        $payment_modes[$row['Mode_of_Payment']]++;
    }

    $month = date('M Y', strtotime($row['Date_of_Payment']));
    if (!isset($monthly_payments[$month])) {
        $monthly_payments[$month] = 0;
    }
    $monthly_payments[$month] += $row['Amount_Paid'];
}
$result->data_seek(0);
$active_page = 'history';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - <?php echo $customer_name; ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>

<body>
    <div class="dashboard-layout">
        <?php include('../includes/sidebar_customer.php'); ?>

        <div class="main-content">
            <header class="dashboard-header" id="header">
                <div class="header-left">
                    <button class="sidebar-mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-title-block">
                        <h1><i class="fas fa-history"></i> Payment History & Receipts</h1>
                        <p>Receipts and transaction log for <?php echo $customer_name; ?></p>
                    </div>
                </div>
                <div class="header-actions">
                    <button id="toggle-theme" class="btn-icon">
                        <i class="fas fa-moon"></i><span>Dark Mode</span>
                    </button>
                    <a href="dashboard_customer.php" class="btn-icon">
                        <i class="fas fa-arrow-left"></i><span>Dashboard</span>
                    </a>
                    <a href="../logout.php" class="btn-icon logout">
                        <i class="fas fa-right-from-bracket"></i><span>Logout</span>
                    </a>
                </div>
            </header>

            <div class="dashboard-content">
        <div class="summary-grid">
            <div class="summary-card">
                <h3><i class="fas fa-rupee-sign"></i> Total Paid</h3>
                <div class="value">₹<?php echo number_format($total_paid, 2); ?></div>
            </div>
            <div class="summary-card">
                <h3><i class="fas fa-receipt"></i> Total Payments</h3>
                <div class="value"><?php echo $payment_count; ?></div>
            </div>
            <div class="summary-card">
                <h3><i class="fas fa-money-bill"></i> Cash Payments</h3>
                <div class="value"><?php echo $payment_modes['Cash']; ?></div>
            </div>
            <div class="summary-card">
                <h3><i class="fas fa-mobile-alt"></i> Online Payments</h3>
                <div class="value"><?php echo $payment_modes['Online'] + $payment_modes['UPI'] + $payment_modes['Card']; ?></div>
            </div>
        </div>

        <div class="filter-section">
            <i class="fas fa-filter"></i>
            <input type="text" id="searchInput" placeholder="Search by Bill ID or Amount...">
            <select id="filterType">
                <option value="">All Bill Types</option>
                <option value="Electric">Electric</option>
                <option value="Water">Water</option>
            </select>
            <select id="filterMode">
                <option value="">All Payment Modes</option>
                <option value="Cash">Cash</option>
                <option value="Online">Online</option>
                <option value="UPI">UPI</option>
                <option value="Card">Card</option>
            </select>
            <input type="date" id="filterDate" placeholder="Filter by date">
        </div>

        <div class="table-container">
            <table id="paymentTable">
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Bill Type</th>
                        <th>Bill ID</th>
                        <th>Bill Amount</th>
                        <th>Amount Paid</th>
                        <th>Date</th>
                        <th>Mode</th>
                        <th>Details</th>
                        <th style="text-align: right;">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($row['Payment_ID']); ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($row['Bill_Type']); ?></strong></td>
                                <td>#<?php echo htmlspecialchars($row['Bill_ID']); ?></td>
                                <td>₹<?php echo number_format($row['Bill_Amount'], 2); ?></td>
                                <td><strong>₹<?php echo number_format($row['Amount_Paid'], 2); ?></strong></td>
                                <td><?php echo date('d M Y', strtotime($row['Date_of_Payment'])); ?></td>
                                <td>
                                    <span class="mode-badge mode-<?php echo strtolower($row['Mode_of_Payment']); ?>">
                                        <?php echo htmlspecialchars($row['Mode_of_Payment']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['Bill_Type'] == 'Electric' && $row['Units_Consumed']): ?>
                                        <?php echo number_format($row['Units_Consumed'], 2); ?> kWh
                                    <?php elseif ($row['Bill_Type'] == 'Water' && $row['Consumption_Liters']): ?>
                                        <?php echo number_format($row['Consumption_Liters'], 2); ?> L
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; justify-content: flex-end; gap: 6px;">
                                        <a href="../download_pdf.php?type=receipt&id=<?= $row['Payment_ID'] ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" title="Download PDF Receipt">
                                            <i class="fas fa-file-pdf" style="color: #ef4444;"></i> PDF
                                        </a>
                                        <a href="../print_receipt.php?payment_id=<?= $row['Payment_ID'] ?>" target="_blank" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px; text-decoration: none;" title="Print Receipt">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-receipt"></i>
                                    <p>No payment history found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <script>
        // Dark mode toggle
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('toggle-theme');
            const saved = localStorage.getItem('theme') || 'light';

            if (saved === 'dark') {
                document.body.classList.add('dark-mode');
                btn.innerHTML = '<i class="fas fa-sun"></i> Light Mode';
            }

            btn.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                const mode = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
                localStorage.setItem('theme', mode);
                btn.innerHTML = mode === 'dark' ?
                    '<i class="fas fa-sun"></i> Light Mode' :
                    '<i class="fas fa-moon"></i> Dark Mode';
            });
        });

        // Search and filter functionality
        const searchInput = document.getElementById('searchInput');
        const filterType = document.getElementById('filterType');
        const filterMode = document.getElementById('filterMode');
        const filterDate = document.getElementById('filterDate');
        const table = document.getElementById('paymentTable');
        const rows = table.querySelectorAll('tbody tr');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const typeFilter = filterType.value.toLowerCase();
            const modeFilter = filterMode.value.toLowerCase();
            const dateFilter = filterDate.value;

            rows.forEach(row => {
                if (row.querySelector('.empty-state')) return;

                const text = row.textContent.toLowerCase();
                const billType = row.children[1].textContent.toLowerCase();
                const paymentMode = row.children[6].textContent.toLowerCase();
                const paymentDate = row.children[5].textContent;

                const matchesSearch = text.includes(searchTerm);
                const matchesType = !typeFilter || billType.includes(typeFilter);
                const matchesMode = !modeFilter || paymentMode.includes(modeFilter);
                const matchesDate = !dateFilter || paymentDate.includes(dateFilter);

                if (matchesSearch && matchesType && matchesMode && matchesDate) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('keyup', filterTable);
        filterType.addEventListener('change', filterTable);
        filterMode.addEventListener('change', filterTable);
        filterDate.addEventListener('change', filterTable);
    </script>
            </div> <!-- close .dashboard-content -->
        </div> <!-- close .main-content -->
    </div> <!-- close .dashboard-layout -->

    <?php include('../includes/footer.php'); ?>
<?php
$stmt->close();
?>
