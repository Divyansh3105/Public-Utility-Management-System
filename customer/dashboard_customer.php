<?php
include('../includes/db_connect.php');

$page_title = 'Customer Dashboard - Public Utility System';
$active_page = 'dashboard';
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
<?php include('../includes/header.php'); ?>

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
<?php include('../includes/footer.php'); ?>
