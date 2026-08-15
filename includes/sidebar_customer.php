<?php
$base = defined("BASE_URL") ? BASE_URL : "/Public_Utility_Management_System/";
$active = $active_page ?? "";
?>
<aside class="sidebar customer-sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-user"></i>
        <span>Customer Portal</span>
    </div>
    <ul class="sidebar-menu">
        <li class="<?= $active === "dashboard" ? "active" : "" ?>">
            <a href="<?= $base ?>customer/dashboard_customer.php"><i class="fas fa-home"></i> Dashboard</a>
        </li>
        <li class="<?= $active === "bills" ? "active" : "" ?>">
            <a href="<?= $base ?>customer/customer_view_bills.php"><i class="fas fa-receipt"></i> View Bills</a>
        </li>
        <li class="<?= $active === "payment" ? "active" : "" ?>">
            <a href="<?= $base ?>customer/customer_make_payment.php"><i class="fas fa-credit-card"></i> Make Payment</a>
        </li>
        <li class="<?= $active === "history" ? "active" : "" ?>">
            <a href="<?= $base ?>customer/customer_payment_history.php"><i class="fas fa-history"></i> Payment History</a>
        </li>
        <li class="<?= $active === "profile" ? "active" : "" ?>">
            <a href="<?= $base ?>profile.php"><i class="fas fa-user-gear"></i> Profile & Security</a>
        </li>
    </ul>
</aside>
