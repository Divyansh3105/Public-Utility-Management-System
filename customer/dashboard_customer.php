<?php
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    header('Location: index.php');
    exit;
}

$customer_id = intval($_SESSION['customer_id']);
$customer_name = 'Customer';

$res = $conn->query("SELECT Name FROM customer WHERE Customer_ID = $customer_id");
if ($res && $res->num_rows > 0) {
    $customer_name = htmlspecialchars($res->fetch_assoc()['Name']);
}

$stats = [
    'total' => 0,
    'paid' => 0,
    'unpaid' => 0,
    'paid_amount' => 0,
    'unpaid_amount' => 0
];

$query = "SELECT COUNT(*) as total,
                 SUM(CASE WHEN Status='Paid' THEN 1 ELSE 0 END) as paid,
                 SUM(CASE WHEN Status='Unpaid' THEN 1 ELSE 0 END) as unpaid,
                 SUM(CASE WHEN Status='Paid' THEN Bill_Amount ELSE 0 END) as paid_amount,
                 SUM(CASE WHEN Status='Unpaid' THEN Bill_Amount ELSE 0 END) as unpaid_amount
          FROM (
               SELECT Bill_Amount, Status FROM electric_bill WHERE Customer_ID = $customer_id
               UNION ALL
               SELECT Bill_Amount, Status FROM water_bill WHERE Customer_ID = $customer_id
          ) all_bills";
$res = $conn->query($query);
if ($res && $res->num_rows > 0) $stats = $res->fetch_assoc();

$next_due = null;
$res = $conn->query("SELECT 'Electric' AS Type, Bill_ID, Bill_Amount, Due_Date FROM electric_bill WHERE Customer_ID=$customer_id AND Status='Unpaid'
                     UNION SELECT 'Water' AS Type, Bill_ID, Bill_Amount, Due_Date FROM water_bill WHERE Customer_ID=$customer_id AND Status='Unpaid'
                     ORDER BY Due_Date ASC LIMIT 1");
if ($res && $res->num_rows > 0) $next_due = $res->fetch_assoc();

$recent_bills = [];
$res = $conn->query("SELECT 'Electric' AS Type, Bill_ID, Bill_Amount, Due_Date, Status FROM electric_bill WHERE Customer_ID=$customer_id
                     UNION ALL SELECT 'Water' AS Type, Bill_ID, Bill_Amount, Due_Date, Status FROM water_bill WHERE Customer_ID=$customer_id
                     ORDER BY Due_Date DESC LIMIT 6");
if ($res) while ($r = $res->fetch_assoc()) $recent_bills[] = $r;

$recent_payments = [];
$res = $conn->query("SELECT p.Payment_ID, p.Bill_Type, p.Bill_ID, p.Amount_Paid, p.Date_of_Payment, p.Mode_of_Payment
                     FROM payment p
                     LEFT JOIN electric_bill e ON p.Bill_Type='Electric' AND p.Bill_ID=e.Bill_ID
                     LEFT JOIN water_bill w ON p.Bill_Type='Water' AND p.Bill_ID=w.Bill_ID
                     WHERE e.Customer_ID=$customer_id OR w.Customer_ID=$customer_id
                     ORDER BY p.Date_of_Payment DESC LIMIT 6");
if ($res) while ($p = $res->fetch_assoc()) $recent_payments[] = $p;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Public Utility System</title>
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
            transition: all 0.4s ease;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        body.dark-mode {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }

        /* Fixed Header */
        .dashboard-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        body.dark-mode .dashboard-header {
            background: rgba(26, 26, 46, 0.95);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .dashboard-header.shrink {
            padding: 12px 40px;
        }

        .header-left h1 {
            font-size: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }

        body.dark-mode .header-left h1 {
            background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-left p {
            font-size: 14px;
            color: #666;
        }

        body.dark-mode .header-left p {
            color: #a0a0a0;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-icon.logout {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
        }

        .btn-icon.logout:hover {
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
        }

        /* Main Content */
        .dashboard-content {
            padding: 100px 40px 40px 40px;
            max-width: 1800px;
            margin: 0 auto;
        }

        /* Alert Box */
        .alert-box {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-left: 5px solid #ffc107;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2);
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-box.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-left-color: #28a745;
        }

        body.dark-mode .alert-box {
            background: linear-gradient(135deg, #3a3a1a 0%, #4a4a2a 100%);
            color: #ffd700;
        }

        body.dark-mode .alert-box.success {
            background: linear-gradient(135deg, #1a3a1a 0%, #2a4a2a 100%);
            color: #90ee90;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.3);
        }

        body.dark-mode .stat-card {
            background: #2b2b3c;
            color: #f1f1f1;
        }

        .stat-card h3 {
            font-size: 16px;
            color: #666;
            margin-bottom: 12px;
            font-weight: 500;
        }

        body.dark-mode .stat-card h3 {
            color: #a0a0a0;
        }

        .stat-card .stat-value {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card.danger .stat-value {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        .action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .action-btn i {
            font-size: 20px;
        }

        /* Section Headers */
        .section-header {
            font-size: 24px;
            font-weight: 700;
            margin: 40px 0 20px 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        body.dark-mode .section-header {
            color: #f1f1f1;
        }

        .section-header i {
            color: #667eea;
        }

        /* Tables */
        .table-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        body.dark-mode .table-container {
            background: #2b2b3c;
            color: #f1f1f1;
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

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-paid {
            background: #d4edda;
            color: #28a745;
        }

        .status-unpaid {
            background: #f8d7da;
            color: #dc3545;
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

        /* Empty State */
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
            .dashboard-header {
                flex-direction: column;
                gap: 15px;
                padding: 15px 20px;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }

            .dashboard-content {
                padding: 160px 20px 40px 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .table-container {
                overflow-x: auto;
            }

            table {
                min-width: 600px;
            }

            .section-header {
                font-size: 20px;
            }
        }
    </style>
</head>

<body>
    <header class="dashboard-header" id="header">
        <div class="header-left">
            <h1><i class="fas fa-user-circle"></i> Welcome, <?php echo $customer_name; ?></h1>
            <p>Your personalized utility dashboard</p>
        </div>
        <div class="header-actions">
            <button id="toggle-theme" class="btn-icon">
                <i class="fas fa-moon"></i>
                <span>Dark Mode</span>
            </button>
            <a href="../logout.php" class="btn-icon logout">
                <i class="fas fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </header>

    <div class="dashboard-content">
        <?php if ($next_due): ?>
            <div class="alert-box">
                <strong><i class="fas fa-exclamation-triangle"></i> Payment Due:</strong>
                <?php echo $next_due['Type']; ?> Bill #<?php echo $next_due['Bill_ID']; ?> —
                ₹<?php echo number_format($next_due['Bill_Amount'], 2); ?>
                (Due: <?php echo date('d M Y', strtotime($next_due['Due_Date'])); ?>)
            </div>
        <?php else: ?>
            <div class="alert-box success">
                <strong><i class="fas fa-check-circle"></i> All Clear!</strong>
                No pending bills — You're all caught up!
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-file-invoice"></i> Total Bills</h3>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-check-circle"></i> Paid Bills</h3>
                <div class="stat-value"><?php echo $stats['paid']; ?></div>
            </div>
            <div class="stat-card danger">
                <h3><i class="fas fa-exclamation-circle"></i> Unpaid Bills</h3>
                <div class="stat-value"><?php echo $stats['unpaid']; ?></div>
            </div>
            <div class="stat-card danger">
                <h3><i class="fas fa-rupee-sign"></i> Outstanding Amount</h3>
                <div class="stat-value">₹<?php echo number_format($stats['unpaid_amount'], 2); ?></div>
            </div>
        </div>

        <div class="quick-actions">
            <a href="customer_view_bills.php" class="action-btn">
                <i class="fas fa-file-invoice"></i>
                View Bills
            </a>
            <a href="customer_make_payment.php" class="action-btn" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="fas fa-wallet"></i>
                Make Payment
            </a>
            <a href="customer_payment_history.php" class="action-btn" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="fas fa-clock-rotate-left"></i>
                Payment History
            </a>
        </div>

        <h2 class="section-header">
            <i class="fas fa-receipt"></i>
            Recent Bills
        </h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Bill ID</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_bills)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No bills found</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_bills as $b): ?>
                            <tr>
                                <td><strong><?php echo $b['Type']; ?></strong></td>
                                <td>#<?php echo $b['Bill_ID']; ?></td>
                                <td><strong>₹<?php echo number_format($b['Bill_Amount'], 2); ?></strong></td>
                                <td><?php echo date('d M Y', strtotime($b['Due_Date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($b['Status']); ?>">
                                        <?php echo $b['Status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h2 class="section-header">
            <i class="fas fa-money-check-alt"></i>
            Recent Payments
        </h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Bill ID</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Mode</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_payments)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fas fa-receipt"></i>
                                    <p>No payment history found</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_payments as $p): ?>
                            <tr>
                                <td><strong><?php echo $p['Bill_Type']; ?></strong></td>
                                <td>#<?php echo $p['Bill_ID']; ?></td>
                                <td><strong>₹<?php echo number_format($p['Amount_Paid'], 2); ?></strong></td>
                                <td><?php echo date('d M Y', strtotime($p['Date_of_Payment'])); ?></td>
                                <td>
                                    <span class="mode-badge mode-<?php echo strtolower($p['Mode_of_Payment']); ?>">
                                        <?php echo $p['Mode_of_Payment']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('toggle-theme');
            const header = document.getElementById('header');
            const saved = localStorage.getItem('theme') || 'light';

            if (saved === 'dark') {
                document.body.classList.add('dark-mode');
                btn.innerHTML = '<i class="fas fa-sun"></i><span>Light Mode</span>';
            }

            btn.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                const mode = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
                localStorage.setItem('theme', mode);
                btn.innerHTML = mode === 'dark' ?
                    '<i class="fas fa-sun"></i><span>Light Mode</span>' :
                    '<i class="fas fa-moon"></i><span>Dark Mode</span>';
            });

            window.addEventListener('scroll', () => {
                if (window.scrollY > 30) {
                    header.classList.add('shrink');
                } else {
                    header.classList.remove('shrink');
                }
            });
        });
    </script>
</body>

</html>
