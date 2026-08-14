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
    <header class="dashboard-header" id="header">
        <div class="header-left">
            <h1><i class="fas fa-shield-halved"></i> <?= htmlspecialchars($page_title) ?></h1>
            <p>Public Utility Management System &bull; Welcome, <?= htmlspecialchars($user_name) ?> (<?= ucfirst($role) ?>)</p>
        </div>
        <div class="header-actions">
            <button id="toggle-theme" class="btn-icon" type="button" aria-label="Toggle Dark Mode">
                <i class="fas fa-moon"></i>
                <span>Dark Mode</span>
            </button>
            <a href="<?= $base ?>logout.php" class="btn-icon logout">
                <i class="fas fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </header>
