<?php
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    header("Location: index.php");
    exit;
}

$customer_id = intval($_SESSION['customer_id']);
$customer_name = 'Customer';

$res = $conn->query("SELECT Name FROM customer WHERE Customer_ID = $customer_id");
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }

        body.dark-mode {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        body.dark-mode .container {
            background: #2b2b3c;
            color: #f1f1f1;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            font-size: 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        body.dark-mode .header h1 {
            background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-back {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }

        .btn-theme {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-theme:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.3);
            transition: transform 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-5px);
        }

        .summary-card:nth-child(2) {
            background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
        }

        .summary-card:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .summary-card:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .summary-card h3 {
            font-size: 16px;
            margin-bottom: 12px;
            opacity: 0.9;
            font-weight: 500;
        }

        .summary-card .value {
            font-size: 36px;
            font-weight: 700;
        }

        /* Filter Section */
        .filter-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        body.dark-mode .filter-section {
            background: #1e1e2e;
        }

        .filter-section input,
        .filter-section select {
            padding: 10px 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
        }

        body.dark-mode .filter-section input,
        body.dark-mode .filter-section select {
            background: #2b2b3c;
            border-color: #3a3a4a;
            color: #f1f1f1;
        }

        .filter-section input:focus,
        .filter-section select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        body.dark-mode .table-container {
            background: #1e1e2e;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        table th {
            color: white;
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
        }

        table td {
            padding: 16px 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
        }

        body.dark-mode table td {
            border-bottom-color: #3a3a4a;
            color: #e0e0e0;
        }

        table tr:last-child td {
            border-bottom: none;
        }

        table tr:hover {
            background: #f8f9ff;
        }

        body.dark-mode table tr:hover {
            background: #323244;
        }

        .mode-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .mode-cash {
            background: #fff3cd;
            color: #856404;
        }

        .mode-online {
            background: #d1ecf1;
            color: #0c5460;
        }

        .mode-upi {
            background: #d4edda;
            color: #155724;
        }

        .mode-card {
            background: #e2e3e5;
            color: #383d41;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }

            .table-container {
                overflow-x: auto;
            }

            table {
                min-width: 800px;
            }

            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-history"></i>
                Payment History - <?php echo $customer_name; ?>
            </h1>
            <div class="header-actions">
                <button id="toggle-theme" class="btn btn-theme">
                    <i class="fas fa-moon"></i>
                    Dark Mode
                </button>
                <a href="dashboard_customer.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>

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
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
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
</body>

</html>
<?php
$stmt->close();
?>
