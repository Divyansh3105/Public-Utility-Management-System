<?php
/**
 * Public Utility Management System — Automated Bill Due Date Reminder Engine
 * Cron Script: Runs daily to find unpaid bills due in 3 days (or configured days)
 *
 * Usage:
 *   CLI: php cron_bill_reminders.php [--force]
 *   Web: http://localhost/Public_Utility_Management_System/cron_bill_reminders.php?token=YOUR_CRON_SECRET_TOKEN
 */

$isCli = (PHP_SAPI === 'cli');

// Include database & notification engine
require_once(__DIR__ . '/includes/db_connect.php');
require_once(__DIR__ . '/includes/notification_engine.php');

// Security check for web invocation
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

// Fetch settings
$settings = getNotificationSettings($conn);
$reminderDays = intval($settings['reminder_days_before'] ?? 3);
if ($reminderDays <= 0) $reminderDays = 3;

$startTime = microtime(true);
$runDate = date('Y-m-d H:i:s');

$stats = [
    'execution_date' => $runDate,
    'reminder_target_days' => $reminderDays,
    'electric_bills_checked' => 0,
    'water_bills_checked' => 0,
    'reminders_dispatched' => 0,
    'emails_sent' => 0,
    'sms_sent' => 0,
    'whatsapp_sent' => 0,
    'skipped_duplicate' => 0,
    'errors' => []
];

// 1. Process Electric Bills
$stmt = $conn->prepare("
    SELECT Bill_ID, Due_Date, DATEDIFF(Due_Date, CURDATE()) as days_left
    FROM electric_bill
    WHERE Status = 'Unpaid'
      AND (DATEDIFF(Due_Date, CURDATE()) = ? OR (DATEDIFF(Due_Date, CURDATE()) >= 0 AND DATEDIFF(Due_Date, CURDATE()) <= 3))
");

if ($stmt) {
    $stmt->bind_param("i", $reminderDays);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $stats['electric_bills_checked']++;
        $billId = intval($row['Bill_ID']);
        $daysLeft = intval($row['days_left']);

        $res = notifyBillDueReminder($conn, $billId, 'Electric', $daysLeft);

        if (isset($res['skipped'])) {
            $stats['skipped_duplicate']++;
        } elseif (isset($res['error'])) {
            $stats['errors'][] = "Electric #$billId: " . $res['error'];
        } else {
            $stats['reminders_dispatched']++;
            if (!empty($res['email']['success'])) $stats['emails_sent']++;
            if (!empty($res['sms']['success'])) $stats['sms_sent']++;
            if (!empty($res['whatsapp']['success'])) $stats['whatsapp_sent']++;
        }
    }
    $stmt->close();
}

// 2. Process Water Bills
$stmt = $conn->prepare("
    SELECT Bill_ID, Due_Date, DATEDIFF(Due_Date, CURDATE()) as days_left
    FROM water_bill
    WHERE Status = 'Unpaid'
      AND (DATEDIFF(Due_Date, CURDATE()) = ? OR (DATEDIFF(Due_Date, CURDATE()) >= 0 AND DATEDIFF(Due_Date, CURDATE()) <= 3))
");

if ($stmt) {
    $stmt->bind_param("i", $reminderDays);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $stats['water_bills_checked']++;
        $billId = intval($row['Bill_ID']);
        $daysLeft = intval($row['days_left']);

        $res = notifyBillDueReminder($conn, $billId, 'Water', $daysLeft);

        if (isset($res['skipped'])) {
            $stats['skipped_duplicate']++;
        } elseif (isset($res['error'])) {
            $stats['errors'][] = "Water #$billId: " . $res['error'];
        } else {
            $stats['reminders_dispatched']++;
            if (!empty($res['email']['success'])) $stats['emails_sent']++;
            if (!empty($res['sms']['success'])) $stats['sms_sent']++;
            if (!empty($res['whatsapp']['success'])) $stats['whatsapp_sent']++;
        }
    }
    $stmt->close();
}

$executionTime = round((microtime(true) - $startTime) * 1000, 2);
$stats['execution_time_ms'] = $executionTime;

// Record Cron Execution in Admin Activity Log if available
if (function_exists('logActivity') || file_exists(__DIR__ . '/admin/activity_log.php')) {
    @require_once(__DIR__ . '/admin/activity_log.php');
    $summaryText = "Automated Due Date Reminders: {$stats['reminders_dispatched']} sent ({$stats['emails_sent']} emails, {$stats['sms_sent']} SMS) in {$executionTime}ms";
    $adminId = $_SESSION['admin_id'] ?? 1;
    $logStmt = $conn->prepare("INSERT INTO `activity_log` (`Admin_ID`, `Action`, `Log_Time`) VALUES (?, ?, NOW())");
    if ($logStmt) {
        $logStmt->bind_param("is", $adminId, $summaryText);
        $logStmt->execute();
        $logStmt->close();
    }
}

// Format Output
if ($isCli) {
    echo "=================================================================\n";
    echo " PUBLIC UTILITY SYSTEM - AUTOMATED DUE DATE REMINDER ENGINE\n";
    echo "=================================================================\n";
    echo " Timestamp            : {$runDate}\n";
    echo " Reminder Threshold   : {$reminderDays} days before due date\n";
    echo " Electric Bills Found : {$stats['electric_bills_checked']}\n";
    echo " Water Bills Found    : {$stats['water_bills_checked']}\n";
    echo " Reminders Sent       : {$stats['reminders_dispatched']}\n";
    echo "   - Emails Sent      : {$stats['emails_sent']}\n";
    echo "   - SMS Sent         : {$stats['sms_sent']}\n";
    echo "   - WhatsApp Sent    : {$stats['whatsapp_sent']}\n";
    echo " Skipped (Deduplicated): {$stats['skipped_duplicate']}\n";
    echo " Errors               : " . count($stats['errors']) . "\n";
    echo " Execution Time       : {$executionTime} ms\n";
    echo "=================================================================\n";
    if (count($stats['errors']) > 0) {
        echo "Errors details:\n";
        foreach ($stats['errors'] as $err) {
            echo "  [!] $err\n";
        }
    }
    echo "Status: COMPLETED\n";
} else {
    $format = $_GET['format'] ?? 'json';
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $stats
        ], JSON_PRETTY_PRINT);
    } else {
        // Render HTML Card
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Cron Execution Result - Public Utility System</title>
            <link rel="stylesheet" href="assets/style.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
            <style>
                body { background: #f8fafc; font-family: sans-serif; padding: 40px 20px; display: flex; justify-content: center; }
                .cron-card { max-width: 600px; width: 100%; background: white; border-radius: 16px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; }
                .cron-header { display: flex; align-items: center; gap: 15px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px; }
                .stat-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f8fafc; }
                .badge-success { background: #10b98122; color: #10b981; padding: 4px 10px; border-radius: 20px; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="cron-card">
                <div class="cron-header">
                    <i class="fas fa-robot" style="font-size: 28px; color: #6366f1;"></i>
                    <div>
                        <h2 style="margin: 0; font-size: 18px;">Automated Due Date Reminder Engine</h2>
                        <small style="color: #64748b;">Executed on <?= date('d M Y, h:i A') ?></small>
                    </div>
                </div>
                <div class="stat-row"><span>Target Reminder Threshold:</span><strong><?= $reminderDays ?> Days Before Due Date</strong></div>
                <div class="stat-row"><span>Electric Bills Scanned:</span><strong><?= $stats['electric_bills_checked'] ?></strong></div>
                <div class="stat-row"><span>Water Bills Scanned:</span><strong><?= $stats['water_bills_checked'] ?></strong></div>
                <div class="stat-row"><span>Reminders Dispatched:</span><span class="badge-success"><?= $stats['reminders_dispatched'] ?> Reminders</span></div>
                <div class="stat-row"><span>Emails Delivered:</span><strong><?= $stats['emails_sent'] ?></strong></div>
                <div class="stat-row"><span>SMS Alerts Sent:</span><strong><?= $stats['sms_sent'] ?></strong></div>
                <div class="stat-row"><span>WhatsApp Messages:</span><strong><?= $stats['whatsapp_sent'] ?></strong></div>
                <div class="stat-row"><span>Skipped (Already Reminded today):</span><strong><?= $stats['skipped_duplicate'] ?></strong></div>
                <div class="stat-row"><span>Execution Time:</span><strong><?= $executionTime ?> ms</strong></div>

                <div style="margin-top: 25px; text-align: center;">
                    <a href="admin/view_notifications.php" class="btn btn-primary" style="text-decoration:none; padding:10px 20px; border-radius:8px; display:inline-block;"><i class="fas fa-list"></i> View Notification Logs</a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
