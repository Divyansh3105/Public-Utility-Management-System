<?php
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employee') {
    header("Location: index.php");
    exit;
}

$emp_id = $_SESSION['employee_id'];
$name = $_SESSION['name'];

// Fetch logs
$logs = $conn->query("SELECT Action, Description, Log_Time FROM employee_log WHERE Employee_ID = $emp_id ORDER BY Log_Time DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Logs - Public Utility System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>

<body>
    <header class="dashboard-header" id="header">
        <div class="header-left">
            <h1><i class="fas fa-clipboard-list"></i> Employee Activity Logs</h1>
            <p>Your action history and work records</p>
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
        <div class="search-filter">
            <input type="text" id="searchInput" placeholder="🔍 Search logs...">
        </div>

        <div class="table-container">
            <table id="logsTable">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Date/Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs && $logs->num_rows > 0): ?>
                        <?php while ($row = $logs->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['Action']) ?></strong></td>
                                <td><?= htmlspecialchars($row['Description']) ?></td>
                                <td><?= date('d M Y H:i', strtotime($row['Log_Time'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No logs found for your account.</p>
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

        document.getElementById('searchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#logsTable tbody tr');
            rows.forEach(row => {
                if (row.querySelector('.empty-state')) return;
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>

</html>
