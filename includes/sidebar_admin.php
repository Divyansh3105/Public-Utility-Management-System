<?php
$base = defined("BASE_URL") ? BASE_URL : "/Public_Utility_Management_System/";
$active = $active_page ?? "";
$admin_name = $_SESSION["name"] ?? $_SESSION["admin_name"] ?? "System Admin";
$admin_initial = strtoupper(substr($admin_name, 0, 1));
?>
<!-- Sidebar Backdrop for Mobile -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

<aside class="sidebar admin-sidebar" id="appSidebar">
    <!-- Brand Header -->
    <div class="sidebar-header">
        <a href="<?= $base ?>admin/dashboard_admin.php" class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <i class="fas fa-shield-halved"></i>
            </div>
            <div class="sidebar-brand-text">
                <span class="sidebar-brand-title">PUMS Admin</span>
                <span class="sidebar-brand-sub">Enterprise Hub</span>
            </div>
        </a>
        <button class="sidebar-close-btn" onclick="toggleSidebar()" aria-label="Close Sidebar">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Logged-in User Pill -->
    <div class="sidebar-user-card">
        <div class="sidebar-user-avatar">
            <?= htmlspecialchars($admin_initial) ?>
        </div>
        <div class="sidebar-user-info">
            <span class="sidebar-user-name" title="<?= htmlspecialchars($admin_name) ?>">
                <?= htmlspecialchars($admin_name) ?>
            </span>
            <span class="sidebar-user-role">
                <span class="badge badge-info sidebar-user-badge">Administrator</span>
            </span>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <div class="sidebar-heading">Core Overview</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item <?= $active === 'dashboard' ? 'active' : '' ?>">
                <a href="<?= $base ?>admin/dashboard_admin.php">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard & Analytics</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">User Management</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item <?= $active === 'customers' ? 'active' : '' ?>">
                <a href="<?= $base ?>admin/manage_customers.php">
                    <i class="fas fa-users"></i>
                    <span>Customer Accounts</span>
                </a>
            </li>
            <li class="sidebar-menu-item <?= $active === 'employees' ? 'active' : '' ?>">
                <a href="<?= $base ?>admin/manage_employees.php">
                    <i class="fas fa-user-tie"></i>
                    <span>Utility Employees</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">Billing & Revenue</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item <?= $active === 'bills' ? 'active' : '' ?>">
                <a href="<?= $base ?>admin/view_bills.php">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Utility Invoices</span>
                </a>
            </li>
            <li class="sidebar-menu-item <?= $active === 'payments' ? 'active' : '' ?>">
                <a href="<?= $base ?>admin/view_payments.php">
                    <i class="fas fa-receipt"></i>
                    <span>Payment Records</span>
                </a>
            </li>
            <li class="sidebar-menu-item <?= $active === 'tariffs' ? 'active' : '' ?>">
                <a href="<?= $base ?>admin/manage_tariffs.php">
                    <i class="fas fa-layer-group"></i>
                    <span>Tariffs & Late Fees</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">Automation & Operations</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item <?= $active === 'notifications' ? 'active' : '' ?>">
                <a href="<?= $base ?>admin/view_notifications.php">
                    <i class="fas fa-bell"></i>
                    <span>Notification Logs</span>
                </a>
            </li>
            <li class="sidebar-menu-item <?= $active === 'notification_settings' ? 'active' : '' ?>">
                <a href="<?= $base ?>admin/notification_settings.php">
                    <i class="fas fa-sliders"></i>
                    <span>Notification Engine</span>
                </a>
            </li>
            <li class="sidebar-menu-item <?= $active === 'logs' ? 'active' : '' ?>">
                <a href="<?= $base ?>admin/view_logs.php">
                    <i class="fas fa-list-check"></i>
                    <span>System Audit Trail</span>
                </a>
            </li>
            <li class="sidebar-menu-item <?= $active === 'health' ? 'active' : '' ?>">
                <a href="<?= $base ?>admin/system_health.php">
                    <i class="fas fa-heart-pulse"></i>
                    <span>System Health</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">Account & Security</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item <?= $active === 'profile' ? 'active' : '' ?>">
                <a href="<?= $base ?>profile.php">
                    <i class="fas fa-user-gear"></i>
                    <span>Admin Profile</span>
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
