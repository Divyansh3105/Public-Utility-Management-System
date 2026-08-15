<?php
require_once(__DIR__ . "/db_connect.php");

$page_title = $page_title ?? "Public Utility Management System";
$active_page = $active_page ?? "";
$user_name = $_SESSION["name"] ?? $_SESSION["admin_name"] ?? "User";
$role = $_SESSION["role"] ?? "guest";
$base = defined("BASE_URL") ? BASE_URL : "/Public_Utility_Management_System/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" href="<?= $base ?>assets/public.png" type="image/png">
    <link rel="stylesheet" href="<?= $base ?>assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function() {
            const savedTheme = localStorage.getItem("theme");
            if (savedTheme === "dark") {
                document.documentElement.classList.add("dark-mode");
            }
        })();
    </script>
</head>
<body>
    <div class="dashboard-layout">
        <?php
        if ($role === 'admin') {
            include(__DIR__ . '/sidebar_admin.php');
        } elseif ($role === 'employee') {
            include(__DIR__ . '/sidebar_employee.php');
        } elseif ($role === 'customer') {
            include(__DIR__ . '/sidebar_customer.php');
        }
        ?>

        <div class="main-content">
            <header class="dashboard-header" id="header">
                <div class="header-left">
                    <button class="sidebar-mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-title-block">
                        <h1><?= htmlspecialchars($page_title) ?></h1>
                        <p>Public Utility Management System &bull; Welcome, <?= htmlspecialchars($user_name) ?> (<?= ucfirst($role) ?>)</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button id="toggle-theme" class="btn-icon" type="button" aria-label="Toggle Dark Mode">
                        <i class="fas fa-moon"></i>
                        <span>Dark Mode</span>
                    </button>
                    <a href="<?= $base ?>profile.php" class="btn-icon" style="background:linear-gradient(135deg,#3b82f6,#0284c7);">
                        <i class="fas fa-user-gear"></i><span>Profile</span>
                    </a>
                    <a href="<?= $base ?>logout.php" class="btn-icon logout">
                        <i class="fas fa-right-from-bracket"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </header>
