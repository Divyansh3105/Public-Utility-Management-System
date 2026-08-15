<?php
$base = defined("BASE_URL") ? BASE_URL : "/Public_Utility_Management_System/";
$active = $active_page ?? "";
$emp_name = $_SESSION["name"] ?? "Field Officer";
$emp_id = $_SESSION["employee_id"] ?? "";
$emp_initial = strtoupper(substr($emp_name, 0, 1));
?>
<!-- Sidebar Backdrop for Mobile -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

<aside class="sidebar employee-sidebar" id="appSidebar">
    <!-- Brand Header -->
    <div class="sidebar-header">
        <a href="<?= $base ?>employee/dashboard_employee.php" class="sidebar-brand">
            <div class="sidebar-brand-icon" style="background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%);">
                <i class="fas fa-id-badge"></i>
            </div>
            <div class="sidebar-brand-text">
                <span class="sidebar-brand-title">Employee Portal</span>
                <span class="sidebar-brand-sub">Field & Billing Desk</span>
            </div>
        </a>
        <button class="sidebar-close-btn" onclick="toggleSidebar()" aria-label="Close Sidebar">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Logged-in Employee Pill -->
    <div class="sidebar-user-card">
        <div class="sidebar-user-avatar" style="background: linear-gradient(135deg, #0284c7 0%, #6366f1 100%);">
            <?= htmlspecialchars($emp_initial) ?>
        </div>
        <div class="sidebar-user-info">
            <span class="sidebar-user-name" title="<?= htmlspecialchars($emp_name) ?>">
                <?= htmlspecialchars($emp_name) ?>
            </span>
            <span class="sidebar-user-role">
                <span class="badge badge-info sidebar-user-badge">Staff <?= $emp_id ? '#' . htmlspecialchars($emp_id) : '' ?></span>
            </span>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <div class="sidebar-heading">Workstation</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item <?= $active === 'dashboard' ? 'active' : '' ?>">
                <a href="<?= $base ?>employee/dashboard_employee.php">
                    <i class="fas fa-gauge-high"></i>
                    <span>Officer Dashboard</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">Field Operations</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item <?= $active === 'generate' ? 'active' : '' ?>">
                <a href="<?= $base ?>employee/employee_generate_bill.php">
                    <i class="fas fa-file-circle-plus"></i>
                    <span>Generate Invoices</span>
                </a>
            </li>
            <li class="sidebar-menu-item <?= $active === 'update_payment' ? 'active' : '' ?>">
                <a href="<?= $base ?>employee/employee_update_payment.php">
                    <i class="fas fa-cash-register"></i>
                    <span>Counter Payment</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">Reports & History</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item <?= $active === 'reports' ? 'active' : '' ?>">
                <a href="<?= $base ?>employee/employee_reports.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Billing Reports</span>
                </a>
            </li>
            <li class="sidebar-menu-item <?= $active === 'logs' ? 'active' : '' ?>">
                <a href="<?= $base ?>employee/employee_logs.php">
                    <i class="fas fa-clock-rotate-left"></i>
                    <span>My Activity Logs</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">Account & Security</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item <?= $active === 'profile' ? 'active' : '' ?>">
                <a href="<?= $base ?>profile.php">
                    <i class="fas fa-user-gear"></i>
                    <span>Staff Profile</span>
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
