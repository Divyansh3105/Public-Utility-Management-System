<?php
session_start();
include('../includes/db_connect.php');
require_once('activity_log.php');

// ===== Admin Details =====
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_id = $_SESSION['admin_id'] ?? 1;

// ===== Dashboard Stats =====
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM customer"))['total'];
$total_employees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employee"))['total'];
$total_electric_bills = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM electric_bill"))['total'];
$total_water_bills = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM water_bill"))['total'];
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(Amount_Paid) AS total FROM payment"))['total'] ?? 0;

// ===== Monthly Revenue Chart Data =====
$monthly_data = [];
$result = mysqli_query($conn, "
  SELECT DATE_FORMAT(Date_of_Payment, '%b') AS month, SUM(Amount_Paid) AS total
  FROM payment
  WHERE Date_of_Payment >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
  GROUP BY MONTH(Date_of_Payment)
  ORDER BY Date_of_Payment ASC
");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $monthly_data[$row['month']] = $row['total'];
    }
}

// ===== Recent Admin Activities =====
$logs = mysqli_query($conn, "
  SELECT a.Name AS AdminName, l.Action, DATE_FORMAT(l.Log_Time, '%d %b %Y %H:%i') AS Time
  FROM activity_log l
  LEFT JOIN admin a ON l.Admin_ID = a.Admin_ID
  ORDER BY l.Log_Time DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Public Utility System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            text-decoration: none;
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

        /* Stats Grid */
        .stats-grid {
            display: flex;
            grid-template-columns: repeat(auto-fit, minmax(280px, 3fr));
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

        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }

        body.dark-mode .chart-container {
            background: #2b2b3c;
        }

        .chart-container h3 {
            font-size: 20px;
            color: #764ba2;
            margin-bottom: 20px;
        }

        body.dark-mode .chart-container h3 {
            color: #a78bfa;
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
        }
    </style>
</head>

<body>
    <header class="dashboard-header" id="header">
        <div class="header-left">
            <h1><i class="fas fa-user-shield"></i> Welcome, <?= htmlspecialchars($admin_name) ?></h1>
            <p>Public Utility Management System - Admin Dashboard</p>
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
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-users"></i> Total Customers</h3>
                <div class="stat-value"><?= $total_customers ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-user-tie"></i> Total Employees</h3>
                <div class="stat-value"><?= $total_employees ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-bolt"></i> Electric Bills</h3>
                <div class="stat-value"><?= $total_electric_bills ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-droplet"></i> Water Bills</h3>
                <div class="stat-value"><?= $total_water_bills ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-rupee-sign"></i> Total Revenue</h3>
                <div class="stat-value">₹<?= number_format($total_revenue, 2) ?></div>
            </div>
        </div>

        <div class="quick-actions">
            <a href="manage_customers.php" class="action-btn">
                <i class="fas fa-users"></i>
                Manage Customers
            </a>
            <a href="manage_employees.php" class="action-btn" style="background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);">
                <i class="fas fa-user-tie"></i>
                Manage Employees
            </a>
            <a href="view_bills.php" class="action-btn" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="fas fa-file-invoice"></i>
                View Bills
            </a>
            <a href="view_payments.php" class="action-btn" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="fas fa-money-check-alt"></i>
                View Payments
            </a>
            <a href="view_logs.php" class="action-btn" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <i class="fas fa-clipboard-list"></i>
                Activity Logs
            </a>
        </div>

        <h2 class="section-header">
            <i class="fas fa-chart-line"></i>
            Revenue Overview (Last 6 Months)
        </h2>
        <div class="chart-container">
            <canvas id="revenueChart" height="100"></canvas>
        </div>

        <h2 class="section-header">
            <i class="fas fa-history"></i>
            Recent Admin Activity
        </h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Admin Name</th>
                        <th>Action</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($logs && mysqli_num_rows($logs) > 0) {
                        while ($log = mysqli_fetch_assoc($logs)) {
                            echo "<tr>
                                <td>" . htmlspecialchars($log['AdminName'] ?? 'Unknown') . "</td>
                                <td>" . htmlspecialchars($log['Action']) . "</td>
                                <td>" . $log['Time'] . "</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3'><div class='empty-state'>
                            <i class='fas fa-inbox'></i>
                            <p>No recent activity found.</p>
                        </div></td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Dark mode toggle
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

        // Chart.js Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_keys($monthly_data)) ?>,
                datasets: [{
                    label: 'Revenue (₹)',
                    data: <?= json_encode(array_values($monthly_data)) ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.2)',
                    borderColor: '#667eea',
                    borderWidth: 3,
                    pointBackgroundColor: '#764ba2',
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.4,
                    fill: true
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
