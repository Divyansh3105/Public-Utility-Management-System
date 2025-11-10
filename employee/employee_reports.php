<?php
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employee') {
    header("Location: index.php");
    exit;
}

$name = $_SESSION['name'];

// Get monthly collection data
$monthly_data = [];
for ($m = 1; $m <= 12; $m++) {
    $month_sum = $conn->query("SELECT SUM(Amount_Paid) AS total FROM payment WHERE MONTH(Date_of_Payment) = $m AND YEAR(Date_of_Payment) = YEAR(CURDATE())")->fetch_assoc()['total'] ?? 0;
    $monthly_data[] = $month_sum;
}

// Get filtered data if requested
$filtered_total = null;
if (isset($_GET['from']) && isset($_GET['to'])) {
    $from = $_GET['from'];
    $to = $_GET['to'];
    $filtered = $conn->query("SELECT SUM(Amount_Paid) AS total FROM payment WHERE Date_of_Payment BETWEEN '$from' AND '$to'");
    $row = $filtered->fetch_assoc();
    $filtered_total = $row['total'] ?? 0;
}

// Handle CSV export
if (isset($_POST['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="payment_report.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Payment_ID', 'Bill_Type', 'Amount_Paid', 'Date_of_Payment', 'Mode_of_Payment']);
    $res = $conn->query("SELECT Payment_ID, Bill_Type, Amount_Paid, Date_of_Payment, Mode_of_Payment FROM payment ORDER BY Date_of_Payment DESC");
    while ($r = $res->fetch_assoc()) {
        fputcsv($output, $r);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Public Utility System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <header class="dashboard-header" id="header">
        <div class="header-left">
            <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
            <p>View payment trends and generate reports</p>
        </div>
        <div class="header-actions">
            <button id="toggle-theme" class="btn-icon">
                <i class="fas fa-moon"></i><span>Dark Mode</span>
            </button>
            <a href="dashboard_employee.php" class="btn-icon">
                <i class="fas fa-arrow-left"></i><span>Back</span>
            </a>
            <a href="../logout.php" class="btn-icon logout">
                <i class="fas fa-right-from-bracket"></i><span>Logout</span>
            </a>
        </div>
    </header>

    <div class="dashboard-content">
        <h2 class="section-header"><i class="fas fa-chart-line"></i> Yearly Revenue Report</h2>

        <div style="background: white; border-radius: 16px; padding: 30px; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1); margin-bottom: 30px;">
            <canvas id="yearlyRevenueChart" style="max-height: 400px;"></canvas>
        </div>

        <h2 class="section-header"><i class="fas fa-filter"></i> Filtered Report</h2>

        <div class="form-container">
            <form method="GET" class="form-grid">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="from" class="form-control" value="<?= $_GET['from'] ?? '' ?>" required>
                </div>
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="to" class="form-control" value="<?= $_GET['to'] ?? '' ?>" required>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button class="btn btn-primary" type="submit" style="width: 100%;">
                        <i class="fas fa-search"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>

        <?php if ($filtered_total !== null): ?>
            <div class="alert alert-info" style="margin-top: 20px;">
                <i class="fas fa-info-circle"></i>
                <span><strong>Collection from <?= $_GET['from'] ?> to <?= $_GET['to'] ?>:</strong> ₹<?= number_format($filtered_total, 2) ?></span>
            </div>
        <?php endif; ?>

        <h2 class="section-header"><i class="fas fa-download"></i> Export Reports</h2>
        <form method="POST">
            <button class="btn btn-success" name="export_csv">
                <i class="fas fa-file-csv"></i> Export to CSV
            </button>
        </form>
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

        const ctx = document.getElementById('yearlyRevenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Collection (₹)',
                    data: <?= json_encode($monthly_data) ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102,126,234,0.2)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointBackgroundColor: '#764ba2',
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>
