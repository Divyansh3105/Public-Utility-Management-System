<?php
include("../includes/db_connect.php");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    redirect("index.php");
    exit;
}

$db_ok = ($conn && !$conn->connect_error);
$php_version = phpversion();
$mysql_version = $conn ? $conn->server_info : "N/A";
$extensions = ["mysqli", "mbstring", "session", "json", "openssl"];
$ext_status = [];
foreach ($extensions as $ext) {
    $ext_status[$ext] = extension_loaded($ext);
}

$page_title = "System Diagnostics & Health Check";
$active_page = "health";
?>
<?php include("../includes/header.php"); ?>

<div class="dashboard-content">
    <h2 class="section-header"><i class="fas fa-stethoscope"></i> System Diagnostics & Environment Health</h2>

    <div class="stats-grid">
        <div class="stat-card <?= $db_ok ? "success" : "danger" ?>">
            <h3><i class="fas fa-database"></i> Database Connection</h3>
            <div class="stat-value"><?= $db_ok ? "ONLINE" : "OFFLINE" ?></div>
            <small>MySQL Version: <?= e($mysql_version) ?></small>
        </div>

        <div class="stat-card success">
            <h3><i class="fas fa-code"></i> PHP Engine</h3>
            <div class="stat-value">v<?= e($php_version) ?></div>
            <small>Environment: <?= defined("ENVIRONMENT") ? ENVIRONMENT : "default" ?></small>
        </div>

        <div class="stat-card success">
            <h3><i class="fas fa-shield-halved"></i> Password Security</h3>
            <div class="stat-value">BCRYPT</div>
            <small>Session Security: HTTPOnly & SameSite Lax</small>
        </div>
    </div>

    <div class="charts-grid">
        <div class="chart-container">
            <h3><i class="fas fa-puzzle-piece"></i> Required PHP Extensions</h3>
            <table class="table">
                <thead><tr><th>Extension</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($ext_status as $ext => $ok): ?>
                        <tr>
                            <td><strong><?= e($ext) ?></strong></td>
                            <td><?= $ok ? '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Loaded</span>' : '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Missing</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="chart-container">
            <h3><i class="fas fa-sliders"></i> Configuration Constants</h3>
            <p><strong>App Name:</strong> <?= APP_NAME ?></p>
            <p style="margin-top:10px;"><strong>App Version:</strong> <?= APP_VERSION ?></p>
            <p style="margin-top:10px;"><strong>Timezone:</strong> <?= date_default_timezone_get() ?></p>
            <p style="margin-top:10px;"><strong>Base URL:</strong> <?= BASE_URL ?></p>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>
