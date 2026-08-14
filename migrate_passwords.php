<?php
session_start();
include('includes/db_connect.php');

$is_cli = (php_sapi_name() === 'cli');

function output_message($msg, $type = 'info') {
    global $is_cli;
    if ($is_cli) {
        echo "[".strtoupper($type)."] " . strip_tags($msg) . "\n";
    } else {
        $color = ($type === 'success') ? '#22c55e' : (($type === 'warning') ? '#f59e0b' : '#3b82f6');
        echo "<div style='padding: 10px 15px; margin: 8px 0; border-radius: 6px; background: {$color}22; color: {$color}; border: 1px solid {$color}44; font-family: monospace;'>{$msg}</div>";
    }
}

if (!$is_cli) {
    echo "<!DOCTYPE html><html><head><title>Password Hash Migration Tool</title><meta charset='UTF-8'></head><body style='background: #0f172a; color: #f8fafc; font-family: system-ui, sans-serif; padding: 40px; max-width: 800px; margin: 0 auto;'>";
    echo "<h2 style='color: #6366f1; border-bottom: 2px solid #334155; padding-bottom: 10px;'>🔒 Public Utility Management System — Password Migration Tool</h2>";
}

output_message("Starting automated database password migration...", "info");

$stats = [
    'admin' => ['total' => 0, 'migrated' => 0, 'already_hashed' => 0],
    'employee' => ['total' => 0, 'migrated' => 0, 'already_hashed' => 0],
    'customer' => ['total' => 0, 'migrated' => 0, 'already_hashed' => 0],
];

// Helper to migrate table passwords
function migrate_table_passwords($conn, $table, $id_field, &$table_stats) {
    $res = $conn->query("SELECT `$id_field`, `Password` FROM `$table`");
    if (!$res) return;

    $stmt = $conn->prepare("UPDATE `$table` SET `Password` = ? WHERE `$id_field` = ?");

    while ($row = $res->fetch_assoc()) {
        $id = $row[$id_field];
        $pwd = $row['Password'];
        $table_stats['total']++;

        $info = password_get_info($pwd);

        // Check if already a valid Bcrypt hash
        if ($info['algo'] !== 0 || (strlen($pwd) === 60 && substr($pwd, 0, 4) === '$2y$')) {
            $table_stats['already_hashed']++;
        } else {
            // Needs migration
            $hashed = password_hash($pwd, PASSWORD_DEFAULT);
            $stmt->bind_param("si", $hashed, $id);
            $stmt->execute();
            $table_stats['migrated']++;
        }
    }
    $stmt->close();
}

migrate_table_passwords($conn, 'admin', 'Admin_ID', $stats['admin']);
migrate_table_passwords($conn, 'employee', 'Employee_ID', $stats['employee']);
migrate_table_passwords($conn, 'customer', 'Customer_ID', $stats['customer']);

output_message("✅ Admin Accounts: {$stats['admin']['migrated']} migrated, {$stats['admin']['already_hashed']} already secure (Total: {$stats['admin']['total']})", "success");
output_message("✅ Employee Accounts: {$stats['employee']['migrated']} migrated, {$stats['employee']['already_hashed']} already secure (Total: {$stats['employee']['total']})", "success");
output_message("✅ Customer Accounts: {$stats['customer']['migrated']} migrated, {$stats['customer']['already_hashed']} already secure (Total: {$stats['customer']['total']})", "success");

output_message("🎉 Database password migration completed! All accounts now use Bcrypt password hashing.", "success");

if (!$is_cli) {
    echo "<p style='margin-top: 20px;'><a href='index.php' style='display: inline-block; padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;'>Return to Login Portal</a></p>";
    echo "</body></html>";
}
