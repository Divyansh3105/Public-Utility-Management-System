<?php
$base = defined("BASE_URL") ? BASE_URL : "/Public_Utility_Management_System/";
$active = $active_page ?? "";
?>
<aside class="sidebar employee-sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-id-card"></i>
        <span>Employee Portal</span>
    </div>
    <ul class="sidebar-menu">
        <li class="<?= $active === "dashboard" ? "active" : "" ?>">
            <a href="<?= $base ?>employee/dashboard_employee.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        </li>
        <li class="<?= $active === "generate" ? "active" : "" ?>">
            <a href="<?= $base ?>employee/employee_generate_bill.php"><i class="fas fa-file-circle-plus"></i> Generate Bill</a>
        </li>
        <li class="<?= $active === "update_payment" ? "active" : "" ?>">
            <a href="<?= $base ?>employee/employee_update_payment.php"><i class="fas fa-file-invoice-dollar"></i> Process Payment</a>
        </li>
        <li class="<?= $active === "reports" ? "active" : "" ?>">
            <a href="<?= $base ?>employee/employee_reports.php"><i class="fas fa-chart-line"></i> View Reports</a>
        </li>
        <li class="<?= $active === "logs" ? "active" : "" ?>">
            <a href="<?= $base ?>employee/employee_logs.php"><i class="fas fa-list-check"></i> Action Logs</a>
        </li>
    </ul>
</aside>
