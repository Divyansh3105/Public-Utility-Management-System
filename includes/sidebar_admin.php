<?php
$base = defined("BASE_URL") ? BASE_URL : "/Public_Utility_Management_System/";
$active = $active_page ?? "";
?>
<aside class="sidebar admin-sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-user-shield"></i>
        <span>Admin Portal</span>
    </div>
    <ul class="sidebar-menu">
        <li class="<?= $active === "dashboard" ? "active" : "" ?>">
            <a href="<?= $base ?>admin/dashboard_admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        </li>
        <li class="<?= $active === "customers" ? "active" : "" ?>">
            <a href="<?= $base ?>admin/manage_customers.php"><i class="fas fa-users"></i> Manage Customers</a>
        </li>
        <li class="<?= $active === "employees" ? "active" : "" ?>">
            <a href="<?= $base ?>admin/manage_employees.php"><i class="fas fa-user-tie"></i> Manage Employees</a>
        </li>
        <li class="<?= $active === "bills" ? "active" : "" ?>">
            <a href="<?= $base ?>admin/view_bills.php"><i class="fas fa-file-invoice"></i> View Bills</a>
        </li>
        <li class="<?= $active === "payments" ? "active" : "" ?>">
            <a href="<?= $base ?>admin/view_payments.php"><i class="fas fa-money-check-alt"></i> View Payments</a>
        </li>
        <li class="<?= $active === "logs" ? "active" : "" ?>">
            <a href="<?= $base ?>admin/view_logs.php"><i class="fas fa-clipboard-list"></i> Activity Logs</a>
        </li>
    </ul>
</aside>
