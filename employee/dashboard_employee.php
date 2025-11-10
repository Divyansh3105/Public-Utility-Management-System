<?php
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employee') {
    header("Location: index.php");
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
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Employee Dashboard - Public Utility System</title>
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
            flex-wrap: wrap;
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

        .stat-card.success .stat-value {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
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

        /* Charts Container */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        body.dark-mode .chart-container {
            background: #2b2b3c;
        }

        .chart-container h3 {
            font-size: 18px;
            color: #764ba2;
            margin-bottom: 20px;
            text-align: center;
        }

        body.dark-mode .chart-container h3 {
            color: #a78bfa;
        }

        .chart-container canvas {
            max-height: 300px;
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
                grid-template-columns: 3fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <header class="dashboard-header" id="header">
        <div class="header-left">
            <h1><i class="fas fa-user-tie"></i> Welcome, <?php echo htmlspecialchars($name); ?></h1>
            <p>Employee Dashboard - Bill Management System</p>
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
</body>

</html>
