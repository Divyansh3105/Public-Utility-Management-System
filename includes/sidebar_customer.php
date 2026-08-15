<?php
$base = defined("BASE_URL") ? BASE_URL : "/Public_Utility_Management_System/";
$active = $active_page ?? "";
$cust_name = $_SESSION["name"] ?? "Valued Consumer";
$cust_id = $_SESSION["customer_id"] ?? "";
$cust_initial = strtoupper(substr($cust_name, 0, 1));
?>
<!-- Sidebar Backdrop for Mobile -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

<aside class="sidebar customer-sidebar" id="appSidebar">
    <!-- Brand Header -->
    <div class="sidebar-header">
        <a href="<?= $base ?>customer/dashboard_customer.php" class="sidebar-brand">
            <div class="sidebar-brand-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <i class="fas fa-house-chimney-user"></i>
            </div>
            <div class="sidebar-brand-text">
                <span class="sidebar-brand-title">Customer Desk</span>
                <span class="sidebar-brand-sub">Self-Service Hub</span>
            </div>
        </a>
        <button class="sidebar-close-btn" onclick="toggleSidebar()" aria-label="Close Sidebar">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Logged-in Customer Pill -->
    <div class="sidebar-user-card">
        <div class="sidebar-user-avatar" style="background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);">
            <?= htmlspecialchars($cust_initial) ?>
        </div>
        <div class="sidebar-user-info">
            <span class="sidebar-user-name" title="<?= htmlspecialchars($cust_name) ?>">
                <?= htmlspecialchars($cust_name) ?>
            </span>
            <span class="sidebar-user-role">
                <span class="badge badge-success sidebar-user-badge">Consumer <?= $cust_id ? '#' . htmlspecialchars($cust_id) : '' ?></span>
            </span>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <div class="sidebar-heading">My Account</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item <?= $active === 'dashboard' ? 'active' : '' ?>">
                <a href="<?= $base ?>customer/dashboard_customer.php">
                    <i class="fas fa-house"></i>
                    <span>Portal Overview</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">Billing & Payments</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item <?= $active === 'bills' ? 'active' : '' ?>">
                <a href="<?= $base ?>customer/customer_view_bills.php">
                    <i class="fas fa-file-invoice"></i>
                    <span>Utility Bills & Slabs</span>
                </a>
            </li>
            <li class="sidebar-menu-item <?= $active === 'payment' ? 'active' : '' ?>">
                <a href="<?= $base ?>customer/customer_make_payment.php">
                    <i class="fas fa-credit-card"></i>
                    <span>Instant Checkout</span>
                </a>
            </li>
            <li class="sidebar-menu-item <?= $active === 'history' ? 'active' : '' ?>">
                <a href="<?= $base ?>customer/customer_payment_history.php">
                    <i class="fas fa-clock-rotate-left"></i>
                    <span>Receipts & History</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">Preferences</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item <?= $active === 'profile' ? 'active' : '' ?>">
                <a href="<?= $base ?>profile.php">
                    <i class="fas fa-user-gear"></i>
                    <span>Profile & Security</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <a href="<?= $base ?>profile.php" class="sidebar-footer-btn" title="Account Settings">
            <i class="fas fa-gear"></i>
        </a>
        <button type="button" class="sidebar-footer-btn toggle-theme-btn" onclick="toggleTheme()" title="Toggle Dark/Light Mode">
            <i class="fas fa-moon theme-icon"></i>
        </button>
        <a href="<?= $base ?>logout.php" class="sidebar-footer-btn logout" title="Sign Out">
            <i class="fas fa-right-from-bracket"></i>
        </a>
    </div>
</aside>
