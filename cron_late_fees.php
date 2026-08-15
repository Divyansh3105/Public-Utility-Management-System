<?php
/**
 * Public Utility Management System — Automated Late Fee & Penalty Cron Engine
 * Runs daily to scan unpaid bills passing grace period, calculate late surcharge, and alert consumers.
 *
 * Usage:
 *   CLI: php cron_late_fees.php [--force]
 *   Web: http://localhost/Public_Utility_Management_System/cron_late_fees.php?token=YOUR_CRON_SECRET_TOKEN
 */

$isCli = (PHP_SAPI === 'cli');

require_once(__DIR__ . '/includes/db_connect.php');
require_once(__DIR__ . '/includes/tariff_engine.php');
require_once(__DIR__ . '/includes/notification_engine.php');

// Security Check for Web Invocation
if (!$isCli) {
    $settings = getNotificationSettings($conn);
    $cronToken = $settings['cron_secret_token'] ?? 'pums_secure_cron_reminder_key_2026';
    $providedToken = $_GET['token'] ?? $_GET['key'] ?? '';
    $isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

    if (!$isAdmin && (!hash_equals($cronToken, $providedToken))) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'forbidden',
            'error' => 'Invalid or missing cron authentication token. Please provide ?token=YOUR_CRON_TOKEN or login as admin.'
        ]);
        exit;
    }
}

$startTime = microtime(true);
$runDate = date('Y-m-d H:i:s');

$batchResults = applyLateFeesToOverdueBills($conn);

$executionTime = round(microtime(true) - $startTime, 4);

$response = [
    'status' => 'success',
    'executed_at' => $runDate,
    'execution_time_seconds' => $executionTime,
    'electric_bills_penalized' => $batchResults['electric_updated'],
    'water_bills_penalized' => $batchResults['water_updated'],
    'total_late_fees_added' => round($batchResults['total_late_fees_added'], 2),
    'details' => $batchResults['details']
];

if ($isCli) {
    echo "====================================================\n";
    echo "  PUMS AUTOMATED LATE FEE & PENALTY CRON WORKER\n";
    echo "====================================================\n";
    echo "Execution Timestamp : {$runDate}\n";
    echo "Electric Bills Adjusted : {$batchResults['electric_updated']}\n";
    echo "Water Bills Adjusted    : {$batchResults['water_updated']}\n";
    echo "Total Surcharges Added  : ₹" . number_format($batchResults['total_late_fees_added'], 2) . "\n";
    echo "Execution Time          : {$executionTime}s\n";
    if (!empty($batchResults['details'])) {
        echo "----------------------------------------------------\n";
        echo "Adjusted Bills:\n";
        foreach ($batchResults['details'] as $item) {
            echo "  - [{$item['type']} #{$item['bill_id']}] {$item['customer']}: +₹{$item['late_fee']} (New Total: ₹{$item['new_total']})\n";
        }
    }
    echo "====================================================\n";
} else {
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
}
