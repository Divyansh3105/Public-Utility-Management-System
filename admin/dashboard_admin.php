<?php
include('../includes/db_connect.php');
require_once('activity_log.php');

$page_title = 'Admin Dashboard - Public Utility System';
$active_page = 'dashboard';

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
<?php include('../includes/header.php'); ?>

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
<?php include('../includes/footer.php'); ?>
