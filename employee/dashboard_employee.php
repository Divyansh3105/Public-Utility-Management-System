<?php
include('../includes/db_connect.php');

$page_title = 'Employee Dashboard - Public Utility System';
$active_page = 'dashboard';
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employee') {
    redirect('index.php');
    exit;
}

$emp_id = $_SESSION['employee_id'];
$name = $_SESSION['name'];

$total_bills = $conn->query("SELECT COUNT(*) AS total FROM electric_bill")->fetch_assoc()['total'];
$paid_bills = $conn->query("SELECT COUNT(*) AS total FROM electric_bill WHERE Status='Paid'")->fetch_assoc()['total'];
$unpaid_bills = $conn->query("SELECT COUNT(*) AS total FROM electric_bill WHERE Status='Unpaid'")->fetch_assoc()['total'];
$total_collection = $conn->query("SELECT SUM(Amount_Paid) AS total FROM payment")->fetch_assoc()['total'] ?? 0;

// Get monthly collection data
$monthly_data = [];
for ($m = 1; $m <= 12; $m++) {
    $month_sum = $conn->query("SELECT SUM(Amount_Paid) AS total FROM payment WHERE MONTH(Date_of_Payment) = $m")->fetch_assoc()['total'] ?? 0;
    $monthly_data[] = $month_sum;
}
?>
<?php include('../includes/header.php'); ?>

    <div class="dashboard-content">
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-file-invoice"></i> Total Bills</h3>
                <div class="stat-value"><?php echo $total_bills; ?></div>
            </div>
            <div class="stat-card success">
                <h3><i class="fas fa-check-circle"></i> Paid Bills</h3>
                <div class="stat-value"><?php echo $paid_bills; ?></div>
            </div>
            <div class="stat-card danger">
                <h3><i class="fas fa-exclamation-circle"></i> Unpaid Bills</h3>
                <div class="stat-value"><?php echo $unpaid_bills; ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-rupee-sign"></i> Total Collection</h3>
                <div class="stat-value">₹<?php echo number_format($total_collection, 2); ?></div>
            </div>
        </div>

        <div class="quick-actions">
            <a href="employee_generate_bill.php" class="action-btn">
                <i class="fas fa-file-invoice-dollar"></i>
                Generate Bill
            </a>
            <a href="employee_update_payment.php" class="action-btn" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="fas fa-credit-card"></i>
                Update Payment
            </a>
            <a href="employee_reports.php" class="action-btn" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="fas fa-chart-bar"></i>
                Reports & Analytics
            </a>
            <a href="employee_logs.php" class="action-btn" style="background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);">
                <i class="fas fa-clipboard-list"></i>
                Employee Logs
            </a>
        </div>

        <h2 class="section-header">
            <i class="fas fa-chart-pie"></i>
            Statistics Overview
        </h2>

        <div class="charts-grid">
            <div class="chart-container">
                <h3><i class="fas fa-chart-pie"></i> Bill Payment Status</h3>
                <canvas id="billStatusChart"></canvas>
            </div>

            <div class="chart-container">
                <h3><i class="fas fa-chart-bar"></i> Monthly Collection Summary</h3>
                <canvas id="monthlyCollectionChart"></canvas>
            </div>
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

        // Bill Status Chart (Doughnut)
        const billStatusCtx = document.getElementById('billStatusChart').getContext('2d');
        new Chart(billStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Unpaid'],
                datasets: [{
                    data: [<?php echo $paid_bills; ?>, <?php echo $unpaid_bills; ?>],
                    backgroundColor: [
                        'rgba(67, 233, 123, 0.8)',
                        'rgba(255, 107, 107, 0.8)'
                    ],
                    borderColor: [
                        '#43e97b',
                        '#ff6b6b'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                size: 14
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = <?php echo $total_bills; ?>;
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Monthly Collection Chart (Bar)
        const monthlyCtx = document.getElementById('monthlyCollectionChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Collection (₹)',
                    data: <?php echo json_encode($monthly_data); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₹' + context.parsed.y.toLocaleString();
                            }
                        }
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
