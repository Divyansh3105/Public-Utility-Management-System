<?php
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    header("Location: index.php");
    exit;
}

$name = $_SESSION['name'];
$customer_id = $_SESSION['customer_id'] ?? null;

if (!$customer_id) {
    $stmt = $conn->prepare("SELECT Customer_ID FROM customer WHERE Name=?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $customer_id = $result->fetch_assoc()['Customer_ID'];
        $_SESSION['customer_id'] = $customer_id;
    }
    $stmt->close();
}

// Fetch electric bills
$electric_stmt = $conn->prepare("SELECT * FROM electric_bill WHERE Customer_ID=? ORDER BY Bill_ID DESC");
$electric_stmt->bind_param("i", $customer_id);
$electric_stmt->execute();
$electric = $electric_stmt->get_result();

// Fetch water bills
$water_stmt = $conn->prepare("SELECT * FROM water_bill WHERE Customer_ID=? ORDER BY Bill_ID DESC");
$water_stmt->bind_param("i", $customer_id);
$water_stmt->execute();
$water = $water_stmt->get_result();

// Calculate summaries
$electric_total = 0;
$electric_paid = 0;
$electric_unpaid = 0;
$electric->data_seek(0);
while ($row = $electric->fetch_assoc()) {
    $electric_total += $row['Bill_Amount'];
    if ($row['Status'] == 'Paid') $electric_paid += $row['Bill_Amount'];
    else $electric_unpaid += $row['Bill_Amount'];
}
$electric->data_seek(0);

$water_total = 0;
$water_paid = 0;
$water_unpaid = 0;
$water->data_seek(0);
while ($row = $water->fetch_assoc()) {
    $water_total += $row['Bill_Amount'];
    if ($row['Status'] == 'Paid') $water_paid += $row['Bill_Amount'];
    else $water_unpaid += $row['Bill_Amount'];
}
$water->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bills - Public Utility System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>

<body>
    <header class="dashboard-header" id="header">
        <div class="header-left">
            <h1><i class="fas fa-file-invoice"></i> Your Bills</h1>
            <p>View all your electricity and water bills</p>
        </div>
        <div class="header-actions">
            <button id="toggle-theme" class="btn-icon">
                <i class="fas fa-moon"></i><span>Dark Mode</span>
            </button>
            <a href="dashboard_customer.php" class="btn-icon">
                <i class="fas fa-arrow-left"></i><span>Back</span>
            </a>
            <a href="../logout.php" class="btn-icon logout">
                <i class="fas fa-right-from-bracket"></i><span>Logout</span>
            </a>
        </div>
    </header>

    <div class="dashboard-content">
        <h2 class="section-header"><i class="fas fa-bolt"></i> Electricity Bills</h2>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-rupee-sign"></i> Total</h3>
                <div class="stat-value">₹<?= number_format($electric_total, 2) ?></div>
            </div>
            <div class="stat-card success">
                <h3><i class="fas fa-check-circle"></i> Paid</h3>
                <div class="stat-value">₹<?= number_format($electric_paid, 2) ?></div>
            </div>
            <div class="stat-card danger">
                <h3><i class="fas fa-exclamation-circle"></i> Unpaid</h3>
                <div class="stat-value">₹<?= number_format($electric_unpaid, 2) ?></div>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Bill ID</th>
                        <th>Units Consumed</th>
                        <th>Rate per Unit</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($electric->num_rows > 0): ?>
                        <?php while ($row = $electric->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($row['Bill_ID']) ?></strong></td>
                                <td><?= number_format($row['Units_Consumed'], 2) ?> kWh</td>
                                <td>₹<?= number_format($row['Rate_per_unit'], 2) ?></td>
                                <td><strong>₹<?= number_format($row['Bill_Amount'], 2) ?></strong></td>
                                <td><?= date('d M Y', strtotime($row['Due_Date'])) ?></td>
                                <td>
                                    <span class="badge status-<?= strtolower($row['Status']) ?>">
                                        <?= htmlspecialchars($row['Status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No electricity bills found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h2 class="section-header"><i class="fas fa-droplet"></i> Water Bills</h2>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-rupee-sign"></i> Total</h3>
                <div class="stat-value">₹<?= number_format($water_total, 2) ?></div>
            </div>
            <div class="stat-card success">
                <h3><i class="fas fa-check-circle"></i> Paid</h3>
                <div class="stat-value">₹<?= number_format($water_paid, 2) ?></div>
            </div>
            <div class="stat-card danger">
                <h3><i class="fas fa-exclamation-circle"></i> Unpaid</h3>
                <div class="stat-value">₹<?= number_format($water_unpaid, 2) ?></div>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Bill ID</th>
                        <th>Consumption</th>
                        <th>Rate per Liter</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($water->num_rows > 0): ?>
                        <?php while ($row = $water->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($row['Bill_ID']) ?></strong></td>
                                <td><?= number_format($row['Consumption_Liters'], 2) ?> L</td>
                                <td>₹<?= number_format($row['Rate_per_liter'], 2) ?></td>
                                <td><strong>₹<?= number_format($row['Bill_Amount'], 2) ?></strong></td>
                                <td><?= date('d M Y', strtotime($row['Due_Date'])) ?></td>
                                <td>
                                    <span class="badge status-<?= strtolower($row['Status']) ?>">
                                        <?= htmlspecialchars($row['Status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No water bills found</p>
                                </div>
                            </td>
                        </tr>
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
                btn.innerHTML = mode === 'dark' ? '<i class="fas fa-sun"></i><span>Light Mode</span>' : '<i class="fas fa-moon"></i><span>Dark Mode</span>';
            });
            window.addEventListener('scroll', () => {
                if (window.scrollY > 30) header.classList.add('shrink');
                else header.classList.remove('shrink');
            });
        });
    </script>
</body>

</html>
<?php
$electric_stmt->close();
$water_stmt->close();
?>
