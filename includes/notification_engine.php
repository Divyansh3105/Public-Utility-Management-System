<?php
/**
 * Public Utility Management System — Automated SMS & Email Notification Engine
 * Multi-Gateway Dispatcher (PHPMailer/SMTP, SendGrid, Twilio SMS/WhatsApp, Fast2SMS)
 */

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/lib/smtp_client.php');
require_once(__DIR__ . '/pdf_generator.php');

// Global cache for notification settings
$GLOBALS['pums_notification_settings'] = null;

/**
 * Ensure notification tables exist in the database (Self-Healing)
 */
function ensureNotificationTablesExist($conn): void
{
    static $checked = false;
    if ($checked || !$conn) return;

    // Check if table exists
    $check = $conn->query("SHOW TABLES LIKE 'notification_settings'");
    if (!$check || $check->num_rows === 0) {
        // Create settings table
        $conn->query("
            CREATE TABLE IF NOT EXISTS `notification_settings` (
              `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
              `setting_value` TEXT NULL,
              `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        // Seed defaults
        $defaults = [
            'email_provider' => 'simulated',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => '587',
            'smtp_secure' => 'tls',
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_from_email' => 'billing@publicutility.local',
            'smtp_from_name' => 'Public Utility Management System',
            'sendgrid_api_key' => '',
            'sendgrid_from_email' => 'billing@publicutility.local',
            'sendgrid_from_name' => 'Public Utility Management System',
            'sms_provider' => 'simulated',
            'twilio_account_sid' => '',
            'twilio_auth_token' => '',
            'twilio_phone_number' => '',
            'twilio_whatsapp_number' => '',
            'fast2sms_api_key' => '',
            'fast2sms_route' => 'q',
            'fast2sms_sender_id' => 'FSTSMS',
            'whatsapp_enabled' => '0',
            'reminder_days_before' => '3',
            'notify_on_bill_create_email' => '1',
            'notify_on_bill_create_sms' => '1',
            'notify_on_bill_create_whatsapp' => '0',
            'notify_on_payment_email' => '1',
            'notify_on_payment_sms' => '1',
            'notify_on_payment_whatsapp' => '0',
            'notify_on_due_reminder_email' => '1',
            'notify_on_due_reminder_sms' => '1',
            'notify_on_due_reminder_whatsapp' => '0',
            'cron_secret_token' => 'pums_secure_cron_reminder_key_2026'
        ];

        foreach ($defaults as $k => $v) {
            $stmt = $conn->prepare("INSERT IGNORE INTO `notification_settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param("ss", $k, $v);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    $checkLogs = $conn->query("SHOW TABLES LIKE 'notification_logs'");
    if (!$checkLogs || $checkLogs->num_rows === 0) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `notification_logs` (
              `Log_ID` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `Customer_ID` INT(11) DEFAULT NULL,
              `Recipient` VARCHAR(255) NOT NULL,
              `Channel` ENUM('Email', 'SMS', 'WhatsApp') NOT NULL,
              `Notification_Type` ENUM('Bill_Generated', 'Payment_Receipt', 'Due_Reminder', 'Custom_Alert', 'Test_Message') NOT NULL,
              `Reference_ID` INT(11) DEFAULT NULL,
              `Subject` VARCHAR(255) DEFAULT NULL,
              `Message` TEXT DEFAULT NULL,
              `Status` ENUM('Sent', 'Failed', 'Simulated', 'Queued') DEFAULT 'Sent',
              `Gateway` VARCHAR(50) DEFAULT NULL,
              `Error_Message` TEXT DEFAULT NULL,
              `Sent_At` DATETIME DEFAULT CURRENT_TIMESTAMP,
              INDEX `idx_notif_customer` (`Customer_ID`),
              INDEX `idx_notif_channel` (`Channel`),
              INDEX `idx_notif_type` (`Notification_Type`),
              INDEX `idx_notif_status` (`Status`),
              INDEX `idx_notif_sent_at` (`Sent_At`),
              INDEX `idx_notif_ref` (`Notification_Type`, `Reference_ID`, `Channel`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
    }

    $checked = true;
}

/**
 * Fetch all notification settings as key-value associative array
 */
function getNotificationSettings($conn, bool $forceReload = false): array
{
    if ($GLOBALS['pums_notification_settings'] !== null && !$forceReload) {
        return $GLOBALS['pums_notification_settings'];
    }

    ensureNotificationTablesExist($conn);

    $settings = [
        'email_provider' => 'simulated',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => '587',
        'smtp_secure' => 'tls',
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_from_email' => 'billing@publicutility.local',
        'smtp_from_name' => 'Public Utility Management System',
        'sendgrid_api_key' => '',
        'sendgrid_from_email' => 'billing@publicutility.local',
        'sendgrid_from_name' => 'Public Utility Management System',
        'sms_provider' => 'simulated',
        'twilio_account_sid' => '',
        'twilio_auth_token' => '',
        'twilio_phone_number' => '',
        'twilio_whatsapp_number' => '',
        'fast2sms_api_key' => '',
        'fast2sms_route' => 'q',
        'fast2sms_sender_id' => 'FSTSMS',
        'whatsapp_enabled' => '0',
        'reminder_days_before' => '3',
        'notify_on_bill_create_email' => '1',
        'notify_on_bill_create_sms' => '1',
        'notify_on_bill_create_whatsapp' => '0',
        'notify_on_payment_email' => '1',
        'notify_on_payment_sms' => '1',
        'notify_on_payment_whatsapp' => '0',
        'notify_on_due_reminder_email' => '1',
        'notify_on_due_reminder_sms' => '1',
        'notify_on_due_reminder_whatsapp' => '0',
        'cron_secret_token' => 'pums_secure_cron_reminder_key_2026'
    ];

    if ($conn) {
        $res = $conn->query("SELECT `setting_key`, `setting_value` FROM `notification_settings`");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }

    $GLOBALS['pums_notification_settings'] = $settings;
    return $settings;
}

/**
 * Fetch a single notification setting
 */
function getNotificationSetting($conn, string $key, $default = '')
{
    $settings = getNotificationSettings($conn);
    return $settings[$key] ?? $default;
}

/**
 * Update multiple notification settings
 */
function updateNotificationSettings($conn, array $newSettings): bool
{
    ensureNotificationTablesExist($conn);
    $stmt = $conn->prepare("INSERT INTO `notification_settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`)");
    if (!$stmt) return false;

    foreach ($newSettings as $k => $v) {
        $vStr = (string)$v;
        $stmt->bind_param("ss", $k, $vStr);
        $stmt->execute();
    }
    $stmt->close();

    // Invalidate cache
    $GLOBALS['pums_notification_settings'] = null;
    return true;
}

/**
 * Log notification dispatch to database
 */
function logNotification($conn, ?int $customerId, string $recipient, string $channel, string $type, ?int $refId, string $subject, string $message, string $status, string $gateway, ?string $errorMessage = null): int
{
    if (!$conn) return 0;
    ensureNotificationTablesExist($conn);
    $stmt = $conn->prepare("
        INSERT INTO `notification_logs`
        (`Customer_ID`, `Recipient`, `Channel`, `Notification_Type`, `Reference_ID`, `Subject`, `Message`, `Status`, `Gateway`, `Error_Message`, `Sent_At`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    if (!$stmt) return 0;

    $stmt->bind_param(
        "isssisssss",
        $customerId,
        $recipient,
        $channel,
        $type,
        $refId,
        $subject,
        $message,
        $status,
        $gateway,
        $errorMessage
    );
    $stmt->execute();
    $insertId = $stmt->insert_id;
    $stmt->close();

    return $insertId;
}

/**
 * HTML Email Template Generator
 */
function buildBrandedEmailHtml(string $title, string $preheader, string $contentHtml, string $actionUrl = '', string $actionText = ''): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'Public Utility Management System';
    $year = date('Y');

    $buttonHtml = '';
    if (!empty($actionUrl) && !empty($actionText)) {
        $buttonHtml = '
        <div style="text-align: center; margin: 30px 0 20px 0;">
            <a href="' . htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') . '" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: bold; font-size: 15px; display: inline-block; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">' . htmlspecialchars($actionText, ENT_QUOTES, 'UTF-8') . '</a>
        </div>';
    }

    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #334155; }
        .container { max-width: 600px; margin: 30px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 30px 25px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
        .header p { margin: 6px 0 0 0; font-size: 13px; opacity: 0.9; }
        .content { padding: 35px 30px; font-size: 15px; line-height: 1.6; color: #1e293b; }
        .badge-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px 20px; margin: 20px 0; }
        .footer { background-color: #f8fafc; padding: 20px 30px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
        .footer a { color: #6366f1; text-decoration: none; }
        @media only screen and (max-width: 600px) {
            .container { margin: 0; border-radius: 0; }
            .content { padding: 25px 20px; }
        }
    </style>
</head>
<body>
    <div style="display:none;font-size:1px;color:#333333;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">
        ' . htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8') . '
    </div>
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f1f5f9; padding: 20px 0;">
        <tr>
            <td align="center">
                <div class="container">
                    <div class="header">
                        <h1>' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</h1>
                        <p>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>
                    </div>
                    <div class="content">
                        ' . $contentHtml . '
                        ' . $buttonHtml . '
                    </div>
                    <div class="footer">
                        <p>This is an automated notification from ' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '.</p>
                        <p>Municipal Utilities &bull; Support: 1800-111-2233 &bull; <a href="mailto:support@publicutility.local">support@publicutility.local</a></p>
                        <p>&copy; ' . $year . ' ' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '. All rights reserved.</p>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>';
}

/**
 * Dispatch Email Notification via Configured Gateway
 *
 * @return array ['success' => bool, 'message' => string, 'gateway' => string, 'log_id' => int]
 */
function sendEmailNotification($conn, string $toEmail, string $toName, string $subject, string $htmlBody, string $plainText = '', array $attachments = [], string $notificationType = 'Custom_Alert', ?int $customerId = null, ?int $refId = null): array
{
    $settings = getNotificationSettings($conn);
    $provider = strtolower($settings['email_provider'] ?? 'simulated');

    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $err = "Invalid or empty recipient email: $toEmail";
        $logId = logNotification($conn, $customerId, $toEmail ?: 'unknown', 'Email', $notificationType, $refId, $subject, strip_tags($htmlBody), 'Failed', $provider, $err);
        return ['success' => false, 'message' => $err, 'gateway' => $provider, 'log_id' => $logId];
    }

    $fromEmail = !empty($settings['smtp_from_email']) ? $settings['smtp_from_email'] : 'billing@publicutility.local';
    $fromName = !empty($settings['smtp_from_name']) ? $settings['smtp_from_name'] : (defined('APP_NAME') ? APP_NAME : 'Public Utility System');

    // 1. SendGrid API
    if ($provider === 'sendgrid') {
        $apiKey = $settings['sendgrid_api_key'] ?? '';
        if (empty($apiKey)) {
            $err = "SendGrid API Key is not configured.";
            $logId = logNotification($conn, $customerId, $toEmail, 'Email', $notificationType, $refId, $subject, strip_tags($htmlBody), 'Failed', 'SendGrid', $err);
            return ['success' => false, 'message' => $err, 'gateway' => 'SendGrid', 'log_id' => $logId];
        }

        $sgFromEmail = !empty($settings['sendgrid_from_email']) ? $settings['sendgrid_from_email'] : $fromEmail;
        $sgFromName = !empty($settings['sendgrid_from_name']) ? $settings['sendgrid_from_name'] : $fromName;

        $payload = [
            'personalizations' => [
                [
                    'to' => [['email' => $toEmail, 'name' => $toName]],
                    'subject' => $subject
                ]
            ],
            'from' => ['email' => $sgFromEmail, 'name' => $sgFromName],
            'content' => [
                ['type' => 'text/html', 'value' => $htmlBody]
            ]
        ];

        if (!empty($attachments)) {
            $payload['attachments'] = [];
            foreach ($attachments as $att) {
                $payload['attachments'][] = [
                    'content' => base64_encode($att['data']),
                    'type' => $att['type'] ?? 'application/pdf',
                    'filename' => $att['name'] ?? 'document.pdf',
                    'disposition' => 'attachment'
                ];
            }
        }

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $logId = logNotification($conn, $customerId, $toEmail, 'Email', $notificationType, $refId, $subject, strip_tags($htmlBody), 'Sent', 'SendGrid', null);
            return ['success' => true, 'message' => 'Email sent successfully via SendGrid', 'gateway' => 'SendGrid', 'log_id' => $logId];
        } else {
            $err = "SendGrid HTTP $httpCode: " . ($response ?: $curlErr);
            $logId = logNotification($conn, $customerId, $toEmail, 'Email', $notificationType, $refId, $subject, strip_tags($htmlBody), 'Failed', 'SendGrid', $err);
            return ['success' => false, 'message' => $err, 'gateway' => 'SendGrid', 'log_id' => $logId];
        }
    }

    // 2. PHPMailer / SimpleSMTP
    if ($provider === 'smtp' || $provider === 'phpmailer_smtp' || $provider === 'phpmailer') {
        $host = $settings['smtp_host'] ?? 'smtp.gmail.com';
        $port = intval($settings['smtp_port'] ?? 587);
        $secure = $settings['smtp_secure'] ?? 'tls';
        $user = $settings['smtp_user'] ?? '';
        $pass = $settings['smtp_pass'] ?? '';

        $smtp = new SimpleSMTPClient($host, $port, $secure, $user, $pass);
        $sent = $smtp->send($toEmail, $toName, $fromEmail, $fromName, $subject, $htmlBody, $plainText, $attachments);

        if ($sent) {
            $logId = logNotification($conn, $customerId, $toEmail, 'Email', $notificationType, $refId, $subject, strip_tags($htmlBody), 'Sent', 'PHPMailer_SMTP', null);
            return ['success' => true, 'message' => 'Email sent successfully via SMTP (' . $host . ')', 'gateway' => 'PHPMailer_SMTP', 'log_id' => $logId];
        } else {
            $err = $smtp->getLastError();
            $logId = logNotification($conn, $customerId, $toEmail, 'Email', $notificationType, $refId, $subject, strip_tags($htmlBody), 'Failed', 'PHPMailer_SMTP', $err);
            return ['success' => false, 'message' => $err, 'gateway' => 'PHPMailer_SMTP', 'log_id' => $logId];
        }
    }

    // 3. Native PHP mail()
    if ($provider === 'mail' || $provider === 'native') {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: $fromName <$fromEmail>\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        $sent = @mail($toEmail, $subject, $htmlBody, $headers);
        if ($sent) {
            $logId = logNotification($conn, $customerId, $toEmail, 'Email', $notificationType, $refId, $subject, strip_tags($htmlBody), 'Sent', 'PHP_mail', null);
            return ['success' => true, 'message' => 'Email sent successfully via PHP mail()', 'gateway' => 'PHP_mail', 'log_id' => $logId];
        } else {
            $err = "Native mail() returned false. Check php.ini sendmail configuration.";
            $logId = logNotification($conn, $customerId, $toEmail, 'Email', $notificationType, $refId, $subject, strip_tags($htmlBody), 'Failed', 'PHP_mail', $err);
            return ['success' => false, 'message' => $err, 'gateway' => 'PHP_mail', 'log_id' => $logId];
        }
    }

    // 4. Default: Simulated Mode (Dev / Local Sandbox)
    $attInfo = count($attachments) > 0 ? ' [Attachments: ' . implode(', ', array_column($attachments, 'name')) . ']' : '';
    $logMsg = strip_tags($htmlBody) . $attInfo;
    $logId = logNotification($conn, $customerId, $toEmail, 'Email', $notificationType, $refId, $subject, $logMsg, 'Simulated', 'Simulated_Email', 'Simulated delivery recorded in system log');

    return [
        'success' => true,
        'message' => 'Email dispatch simulated successfully (Logged to system logs). To send live emails, configure SMTP or SendGrid credentials in Notification Settings.',
        'gateway' => 'Simulated_Email',
        'log_id' => $logId
    ];
}

/**
 * Dispatch SMS Notification via Configured Gateway
 *
 * @return array ['success' => bool, 'message' => string, 'gateway' => string, 'log_id' => int]
 */
function sendSMSNotification($conn, string $toPhone, string $messageText, string $notificationType = 'Custom_Alert', ?int $customerId = null, ?int $refId = null): array
{
    $settings = getNotificationSettings($conn);
    $provider = strtolower($settings['sms_provider'] ?? 'simulated');

    $cleanPhone = preg_replace('/[^0-9+]/', '', $toPhone);
    if (empty($cleanPhone) || strlen($cleanPhone) < 10) {
        $err = "Invalid recipient phone number: $toPhone";
        $logId = logNotification($conn, $customerId, $toPhone ?: 'unknown', 'SMS', $notificationType, $refId, 'SMS Notification', $messageText, 'Failed', $provider, $err);
        return ['success' => false, 'message' => $err, 'gateway' => $provider, 'log_id' => $logId];
    }

    // 1. Twilio SMS
    if ($provider === 'twilio') {
        $sid = $settings['twilio_account_sid'] ?? '';
        $token = $settings['twilio_auth_token'] ?? '';
        $fromNumber = $settings['twilio_phone_number'] ?? '';

        if (empty($sid) || empty($token) || empty($fromNumber)) {
            $err = "Twilio credentials (Account SID, Auth Token, or Twilio Phone Number) are missing.";
            $logId = logNotification($conn, $customerId, $cleanPhone, 'SMS', $notificationType, $refId, 'SMS Alert', $messageText, 'Failed', 'Twilio_SMS', $err);
            return ['success' => false, 'message' => $err, 'gateway' => 'Twilio_SMS', 'log_id' => $logId];
        }

        // Ensure E.164 format
        $destPhone = (strpos($cleanPhone, '+') === 0) ? $cleanPhone : '+91' . ltrim($cleanPhone, '0');

        $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
        $postData = [
            'From' => $fromNumber,
            'To' => $destPhone,
            'Body' => $messageText
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $json = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && isset($json['sid'])) {
            $logId = logNotification($conn, $customerId, $destPhone, 'SMS', $notificationType, $refId, 'SMS Alert', $messageText, 'Sent', 'Twilio_SMS', null);
            return ['success' => true, 'message' => "SMS delivered via Twilio (SID: {$json['sid']})", 'gateway' => 'Twilio_SMS', 'log_id' => $logId];
        } else {
            $err = "Twilio Error ($httpCode): " . ($json['message'] ?? $response ?: $curlErr);
            $logId = logNotification($conn, $customerId, $destPhone, 'SMS', $notificationType, $refId, 'SMS Alert', $messageText, 'Failed', 'Twilio_SMS', $err);
            return ['success' => false, 'message' => $err, 'gateway' => 'Twilio_SMS', 'log_id' => $logId];
        }
    }

    // 2. Fast2SMS API
    if ($provider === 'fast2sms') {
        $apiKey = $settings['fast2sms_api_key'] ?? '';
        $route = $settings['fast2sms_route'] ?? 'q';
        $senderId = $settings['fast2sms_sender_id'] ?? 'FSTSMS';

        if (empty($apiKey)) {
            $err = "Fast2SMS API authorization key is not configured.";
            $logId = logNotification($conn, $customerId, $cleanPhone, 'SMS', $notificationType, $refId, 'SMS Alert', $messageText, 'Failed', 'Fast2SMS', $err);
            return ['success' => false, 'message' => $err, 'gateway' => 'Fast2SMS', 'log_id' => $logId];
        }

        // Fast2SMS expects 10 digit Indian number
        $tenDigitPhone = substr(preg_replace('/[^0-9]/', '', $cleanPhone), -10);

        $fields = [
            'route' => $route,
            'message' => $messageText,
            'language' => 'english',
            'numbers' => $tenDigitPhone
        ];
        if ($route === 'dlt') {
            $fields['sender_id'] = $senderId;
        }

        $ch = curl_init('https://www.fast2sms.com/dev/bulkV2');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "authorization: $apiKey",
            "accept: */*",
            "content-type: application/x-www-form-urlencoded"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $json = json_decode($response, true);
        if ($httpCode === 200 && isset($json['return']) && $json['return'] === true) {
            $logId = logNotification($conn, $customerId, $tenDigitPhone, 'SMS', $notificationType, $refId, 'SMS Alert', $messageText, 'Sent', 'Fast2SMS', null);
            return ['success' => true, 'message' => "SMS delivered via Fast2SMS", 'gateway' => 'Fast2SMS', 'log_id' => $logId];
        } else {
            $err = "Fast2SMS Error: " . ($json['message'][0] ?? $response ?: $curlErr);
            $logId = logNotification($conn, $customerId, $tenDigitPhone, 'SMS', $notificationType, $refId, 'SMS Alert', $messageText, 'Failed', 'Fast2SMS', $err);
            return ['success' => false, 'message' => $err, 'gateway' => 'Fast2SMS', 'log_id' => $logId];
        }
    }

    // 3. Default: Simulated Mode
    $logId = logNotification($conn, $customerId, $cleanPhone, 'SMS', $notificationType, $refId, 'SMS Alert', $messageText, 'Simulated', 'Simulated_SMS', 'Simulated SMS dispatch recorded in system log');

    return [
        'success' => true,
        'message' => 'SMS dispatch simulated successfully (Logged to system logs). To send live SMS, configure Twilio or Fast2SMS credentials in Notification Settings.',
        'gateway' => 'Simulated_SMS',
        'log_id' => $logId
    ];
}

/**
 * Dispatch WhatsApp Notification via Twilio
 *
 * @return array ['success' => bool, 'message' => string, 'gateway' => string, 'log_id' => int]
 */
function sendWhatsAppNotification($conn, string $toPhone, string $messageText, string $notificationType = 'Custom_Alert', ?int $customerId = null, ?int $refId = null): array
{
    $settings = getNotificationSettings($conn);
    $cleanPhone = preg_replace('/[^0-9+]/', '', $toPhone);

    if (empty($cleanPhone) || strlen($cleanPhone) < 10) {
        $err = "Invalid WhatsApp recipient number: $toPhone";
        $logId = logNotification($conn, $customerId, $toPhone ?: 'unknown', 'WhatsApp', $notificationType, $refId, 'WhatsApp Alert', $messageText, 'Failed', 'Twilio_WhatsApp', $err);
        return ['success' => false, 'message' => $err, 'gateway' => 'Twilio_WhatsApp', 'log_id' => $logId];
    }

    $sid = $settings['twilio_account_sid'] ?? '';
    $token = $settings['twilio_auth_token'] ?? '';
    $fromWhatsApp = $settings['twilio_whatsapp_number'] ?? '';

    // If Twilio credentials are configured
    if (!empty($sid) && !empty($token) && !empty($fromWhatsApp)) {
        $destPhone = (strpos($cleanPhone, '+') === 0) ? $cleanPhone : '+91' . ltrim($cleanPhone, '0');
        $fromFormatted = (strpos($fromWhatsApp, 'whatsapp:') === 0) ? $fromWhatsApp : 'whatsapp:' . $fromWhatsApp;
        $toFormatted = 'whatsapp:' . $destPhone;

        $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
        $postData = [
            'From' => $fromFormatted,
            'To' => $toFormatted,
            'Body' => $messageText
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $json = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && isset($json['sid'])) {
            $logId = logNotification($conn, $customerId, $destPhone, 'WhatsApp', $notificationType, $refId, 'WhatsApp Message', $messageText, 'Sent', 'Twilio_WhatsApp', null);
            return ['success' => true, 'message' => "WhatsApp message delivered via Twilio (SID: {$json['sid']})", 'gateway' => 'Twilio_WhatsApp', 'log_id' => $logId];
        } else {
            $err = "Twilio WhatsApp Error ($httpCode): " . ($json['message'] ?? $response ?: $curlErr);
            $logId = logNotification($conn, $customerId, $destPhone, 'WhatsApp', $notificationType, $refId, 'WhatsApp Message', $messageText, 'Failed', 'Twilio_WhatsApp', $err);
            return ['success' => false, 'message' => $err, 'gateway' => 'Twilio_WhatsApp', 'log_id' => $logId];
        }
    }

    // Fallback: Simulated
    $logId = logNotification($conn, $customerId, $cleanPhone, 'WhatsApp', $notificationType, $refId, 'WhatsApp Alert', $messageText, 'Simulated', 'Simulated_WhatsApp', 'Simulated WhatsApp message recorded in system log');

    return [
        'success' => true,
        'message' => 'WhatsApp dispatch simulated successfully.',
        'gateway' => 'Simulated_WhatsApp',
        'log_id' => $logId
    ];
}

/**
 * AUTOMATED WORKFLOW: Trigger on Bill Generation
 * Generates PDF Bill Statement, Emails PDF to customer, and sends SMS/WhatsApp alert
 */
function notifyBillGenerated($conn, int $billId, string $billType = 'Electric'): array
{
    $settings = getNotificationSettings($conn);
    $billTable = (strtolower($billType) === 'water') ? 'water_bill' : 'electric_bill';

    // Fetch complete bill, customer, and house details
    $stmt = $conn->prepare("
        SELECT b.*, c.Name as Customer_Name, c.Email, c.Phone, h.House_Number, h.Address, '$billType' as Bill_Type
        FROM `$billTable` b
        LEFT JOIN customer c ON b.Customer_ID = c.Customer_ID
        LEFT JOIN house h ON b.House_ID = h.House_ID
        WHERE b.Bill_ID = ?
        LIMIT 1
    ");

    if (!$stmt) return ['error' => 'Database query preparation failed'];
    $stmt->bind_param("i", $billId);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$bill) return ['error' => "Bill #$billId not found in $billTable"];

    $customerName = $bill['Customer_Name'] ?? 'Customer';
    $email = $bill['Email'] ?? '';
    $phone = $bill['Phone'] ?? '';
    $amount = floatval($bill['Bill_Amount'] ?? 0);
    $dueDate = !empty($bill['Due_Date']) ? date('d M Y', strtotime($bill['Due_Date'])) : 'N/A';
    $amountFormatted = '₹' . number_format($amount, 2);

    $results = [
        'bill_id' => $billId,
        'bill_type' => $billType,
        'customer' => $customerName,
        'email' => null,
        'sms' => null,
        'whatsapp' => null
    ];

    // 1. Generate PDF Statement
    $pdfData = generateBillStatementPDF($bill, 'S');
    $pdfFilename = "Bill_Statement_{$billType}_{$billId}.pdf";

    // 2. Email Statement with PDF Attachment
    if (($settings['notify_on_bill_create_email'] ?? '1') === '1' && !empty($email)) {
        $subject = "New $billType Utility Bill Statement #BILL-$billId - $amountFormatted Due by $dueDate";
        $preheader = "Your new $billType bill statement of $amountFormatted is ready. Due date: $dueDate.";

        $htmlContent = '
        <p>Dear <strong>' . htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
        <p>Your utility bill for <strong>' . htmlspecialchars($billType, ENT_QUOTES, 'UTF-8') . ' Service</strong> has been generated and is now ready for payment.</p>

        <div class="badge-box">
            <table width="100%" style="border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; color: #64748b;"><strong>Statement No:</strong></td>
                    <td style="padding: 6px 0; text-align: right; font-weight: bold; color: #4f46e5;">#BILL-' . $billId . '</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #64748b;"><strong>Utility Service:</strong></td>
                    <td style="padding: 6px 0; text-align: right;">' . htmlspecialchars($billType, ENT_QUOTES, 'UTF-8') . ' Utility</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #64748b;"><strong>Total Amount Due:</strong></td>
                    <td style="padding: 6px 0; text-align: right; font-size: 18px; font-weight: bold; color: #1e293b;">' . $amountFormatted . '</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #64748b;"><strong>Payment Due Date:</strong></td>
                    <td style="padding: 6px 0; text-align: right; font-weight: bold; color: #e11d48;">' . $dueDate . '</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #64748b;"><strong>House / Meter:</strong></td>
                    <td style="padding: 6px 0; text-align: right;">House #' . htmlspecialchars($bill['House_Number'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
            </table>
        </div>

        <p>Please find your official PDF bill statement attached to this email. You can pay your bill online through your customer dashboard using UPI, Credit/Debit Card, or Net Banking.</p>
        ';

        $actionUrl = (defined('BASE_URL') ? BASE_URL : '/Public_Utility_Management_System/') . 'customer/customer_make_payment.php';
        $fullHtml = buildBrandedEmailHtml("New Utility Bill Statement", $preheader, $htmlContent, $actionUrl, "Pay Bill Now ($amountFormatted)");

        $attachments = [
            [
                'name' => $pdfFilename,
                'data' => $pdfData,
                'type' => 'application/pdf'
            ]
        ];

        $results['email'] = sendEmailNotification(
            $conn,
            $email,
            $customerName,
            $subject,
            $fullHtml,
            strip_tags($htmlContent),
            $attachments,
            'Bill_Generated',
            $bill['Customer_ID'] ?? null,
            $billId
        );
    }

    // 3. SMS Notification
    if (($settings['notify_on_bill_create_sms'] ?? '1') === '1' && !empty($phone)) {
        $smsText = "Dear $customerName, your $billType bill #BILL-$billId for $amountFormatted is generated. Due date: $dueDate. Pay online to avoid late fee. Public Utility Dept.";
        $results['sms'] = sendSMSNotification(
            $conn,
            $phone,
            $smsText,
            'Bill_Generated',
            $bill['Customer_ID'] ?? null,
            $billId
        );
    }

    // 4. WhatsApp Notification (if enabled)
    if (($settings['notify_on_bill_create_whatsapp'] ?? '0') === '1' && !empty($phone)) {
        $waText = "*Public Utility Notification*\n\nDear {$customerName},\nYour *{$billType} Bill #BILL-{$billId}* for *{$amountFormatted}* has been issued.\n\n*Due Date:* {$dueDate}\n*Status:* Unpaid\n\nPlease log in to your Customer Portal to view and pay your bill. Thank you!";
        $results['whatsapp'] = sendWhatsAppNotification(
            $conn,
            $phone,
            $waText,
            'Bill_Generated',
            $bill['Customer_ID'] ?? null,
            $billId
        );
    }

    return $results;
}

/**
 * AUTOMATED WORKFLOW: Trigger on Payment Completion
 * Generates PDF Payment Receipt, Emails Receipt to customer, and sends SMS/WhatsApp confirmation
 */
function notifyPaymentReceipt($conn, int $paymentId): array
{
    $settings = getNotificationSettings($conn);

    // Fetch complete payment, bill, customer, and house details
    $stmt = $conn->prepare("
        SELECT p.*,
               CASE WHEN p.Bill_Type = 'Electric' THEN eb.Customer_ID ELSE wb.Customer_ID END as Customer_ID,
               CASE WHEN p.Bill_Type = 'Electric' THEN eb.House_ID ELSE wb.House_ID END as House_ID,
               c.Name as Customer_Name, c.Email, c.Phone,
               h.House_Number, h.Address
        FROM payment p
        LEFT JOIN electric_bill eb ON p.Bill_Type = 'Electric' AND p.Bill_ID = eb.Bill_ID
        LEFT JOIN water_bill wb ON p.Bill_Type = 'Water' AND p.Bill_ID = wb.Bill_ID
        LEFT JOIN customer c ON (eb.Customer_ID = c.Customer_ID OR wb.Customer_ID = c.Customer_ID)
        LEFT JOIN house h ON (eb.House_ID = h.House_ID OR wb.House_ID = h.House_ID)
        WHERE p.Payment_ID = ?
        LIMIT 1
    ");

    if (!$stmt) return ['error' => 'Database query preparation failed'];
    $stmt->bind_param("i", $paymentId);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payment) return ['error' => "Payment #$paymentId not found"];

    $customerName = $payment['Customer_Name'] ?? 'Customer';
    $email = $payment['Email'] ?? '';
    $phone = $payment['Phone'] ?? '';
    $amount = floatval($payment['Amount_Paid'] ?? 0);
    $payDate = !empty($payment['Date_of_Payment']) ? date('d M Y', strtotime($payment['Date_of_Payment'])) : date('d M Y');
    $mode = $payment['Mode_of_Payment'] ?? 'Online';
    $billType = $payment['Bill_Type'] ?? 'Utility';
    $billId = $payment['Bill_ID'] ?? 0;
    $amountFormatted = '₹' . number_format($amount, 2);

    $results = [
        'payment_id' => $paymentId,
        'bill_type' => $billType,
        'customer' => $customerName,
        'email' => null,
        'sms' => null,
        'whatsapp' => null
    ];

    // 1. Generate PDF Receipt
    $pdfData = generatePaymentReceiptPDF($payment, 'S');
    $pdfFilename = "Payment_Receipt_REC_{$paymentId}.pdf";

    // 2. Email Digital Receipt with PDF Attachment
    if (($settings['notify_on_payment_email'] ?? '1') === '1' && !empty($email)) {
        $subject = "Payment Receipt #REC-$paymentId - $amountFormatted Received for $billType Bill #$billId";
        $preheader = "Your payment of $amountFormatted has been received successfully. Receipt #REC-$paymentId.";

        $htmlContent = '
        <p>Dear <strong>' . htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
        <p>Thank you! Your payment of <strong>' . $amountFormatted . '</strong> has been received and credited to your utility account.</p>

        <div class="badge-box" style="background-color: #ecfdf5; border-color: #a7f3d0;">
            <table width="100%" style="border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; color: #065f46;"><strong>Receipt Number:</strong></td>
                    <td style="padding: 6px 0; text-align: right; font-weight: bold; color: #059669;">#REC-' . $paymentId . '</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #065f46;"><strong>Settled Bill:</strong></td>
                    <td style="padding: 6px 0; text-align: right; font-weight: bold;">' . htmlspecialchars($billType, ENT_QUOTES, 'UTF-8') . ' Bill #' . $billId . '</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #065f46;"><strong>Amount Paid:</strong></td>
                    <td style="padding: 6px 0; text-align: right; font-size: 18px; font-weight: bold; color: #059669;">' . $amountFormatted . '</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #065f46;"><strong>Payment Date:</strong></td>
                    <td style="padding: 6px 0; text-align: right;">' . $payDate . '</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #065f46;"><strong>Payment Mode:</strong></td>
                    <td style="padding: 6px 0; text-align: right;">' . htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #065f46;"><strong>Status:</strong></td>
                    <td style="padding: 6px 0; text-align: right; color: #059669; font-weight: bold;">CLEARED / PAID</td>
                </tr>
            </table>
        </div>

        <p>Your official PDF digital receipt is attached to this email for your records.</p>
        ';

        $actionUrl = (defined('BASE_URL') ? BASE_URL : '/Public_Utility_Management_System/') . 'customer/customer_payment_history.php';
        $fullHtml = buildBrandedEmailHtml("Official Payment Receipt", $preheader, $htmlContent, $actionUrl, "View Payment History");

        $attachments = [
            [
                'name' => $pdfFilename,
                'data' => $pdfData,
                'type' => 'application/pdf'
            ]
        ];

        $results['email'] = sendEmailNotification(
            $conn,
            $email,
            $customerName,
            $subject,
            $fullHtml,
            strip_tags($htmlContent),
            $attachments,
            'Payment_Receipt',
            $payment['Customer_ID'] ?? null,
            $paymentId
        );
    }

    // 3. SMS Notification
    if (($settings['notify_on_payment_sms'] ?? '1') === '1' && !empty($phone)) {
        $smsText = "Dear $customerName, payment of $amountFormatted for $billType bill #$billId received successfully via $mode on $payDate. Receipt #REC-$paymentId. Public Utility Dept.";
        $results['sms'] = sendSMSNotification(
            $conn,
            $phone,
            $smsText,
            'Payment_Receipt',
            $payment['Customer_ID'] ?? null,
            $paymentId
        );
    }

    // 4. WhatsApp Notification
    if (($settings['notify_on_payment_whatsapp'] ?? '0') === '1' && !empty($phone)) {
        $waText = "*Payment Confirmation - Receipt #REC-{$paymentId}*\n\nDear {$customerName},\nWe have received your payment of *{$amountFormatted}* for *{$billType} Bill #{$billId}* on {$payDate} via {$mode}.\n\n*Status:* PAID\nThank you for paying on time!";
        $results['whatsapp'] = sendWhatsAppNotification(
            $conn,
            $phone,
            $waText,
            'Payment_Receipt',
            $payment['Customer_ID'] ?? null,
            $paymentId
        );
    }

    return $results;
}

/**
 * AUTOMATED WORKFLOW: Trigger Due Date Reminder (3 Days Before Due Date)
 */
function notifyBillDueReminder($conn, int $billId, string $billType, int $daysRemaining): array
{
    $settings = getNotificationSettings($conn);
    $billTable = (strtolower($billType) === 'water') ? 'water_bill' : 'electric_bill';

    // Deduplication check: Has a reminder already been sent for this bill in the last 24 hours?
    $dedupStmt = $conn->prepare("
        SELECT Log_ID FROM `notification_logs`
        WHERE `Notification_Type` = 'Due_Reminder'
          AND `Reference_ID` = ?
          AND `Sent_At` >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        LIMIT 1
    ");

    if ($dedupStmt) {
        $dedupStmt->bind_param("i", $billId);
        $dedupStmt->execute();
        $hasRecentReminder = ($dedupStmt->get_result()->num_rows > 0);
        $dedupStmt->close();

        if ($hasRecentReminder) {
            return ['skipped' => true, 'reason' => "Reminder already sent in the last 24h for $billType Bill #$billId"];
        }
    }

    // Fetch bill and customer details
    $stmt = $conn->prepare("
        SELECT b.*, c.Name as Customer_Name, c.Email, c.Phone, h.House_Number, '$billType' as Bill_Type
        FROM `$billTable` b
        LEFT JOIN customer c ON b.Customer_ID = c.Customer_ID
        LEFT JOIN house h ON b.House_ID = h.House_ID
        WHERE b.Bill_ID = ? AND b.Status = 'Unpaid'
        LIMIT 1
    ");

    if (!$stmt) return ['error' => 'Database query preparation failed'];
    $stmt->bind_param("i", $billId);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$bill) return ['skipped' => true, 'reason' => "Bill #$billId is already paid or not found"];

    $customerName = $bill['Customer_Name'] ?? 'Customer';
    $email = $bill['Email'] ?? '';
    $phone = $bill['Phone'] ?? '';
    $amount = floatval($bill['Bill_Amount'] ?? 0);
    $dueDate = !empty($bill['Due_Date']) ? date('d M Y', strtotime($bill['Due_Date'])) : 'N/A';
    $amountFormatted = '₹' . number_format($amount, 2);

    $daysText = ($daysRemaining === 0) ? "TODAY" : (($daysRemaining === 1) ? "TOMORROW" : "in $daysRemaining days");

    $results = [
        'bill_id' => $billId,
        'bill_type' => $billType,
        'customer' => $customerName,
        'days_remaining' => $daysRemaining,
        'email' => null,
        'sms' => null,
        'whatsapp' => null
    ];

    // 1. Email Reminder
    if (($settings['notify_on_due_reminder_email'] ?? '1') === '1' && !empty($email)) {
        $subject = "REMINDER: Your $billType Bill of $amountFormatted is Due $daysText ($dueDate)";
        $preheader = "Urgent reminder: Your utility bill payment of $amountFormatted is due $daysText.";

        $htmlContent = '
        <p>Dear <strong>' . htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
        <p>This is a friendly reminder that your <strong>' . htmlspecialchars($billType, ENT_QUOTES, 'UTF-8') . ' Utility Bill</strong> is due for payment <strong>' . $daysText . ' (' . $dueDate . ')</strong>.</p>

        <div class="badge-box" style="background-color: #fff1f2; border-color: #fecdd3;">
            <table width="100%" style="border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; color: #9f1239;"><strong>Bill Reference:</strong></td>
                    <td style="padding: 6px 0; text-align: right; font-weight: bold; color: #be123c;">#BILL-' . $billId . ' (' . htmlspecialchars($billType, ENT_QUOTES, 'UTF-8') . ')</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #9f1239;"><strong>Amount Due:</strong></td>
                    <td style="padding: 6px 0; text-align: right; font-size: 20px; font-weight: bold; color: #e11d48;">' . $amountFormatted . '</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #9f1239;"><strong>Due Date:</strong></td>
                    <td style="padding: 6px 0; text-align: right; font-weight: bold; color: #e11d48;">' . $dueDate . ' (' . $daysText . ')</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #9f1239;"><strong>House:</strong></td>
                    <td style="padding: 6px 0; text-align: right;">House #' . htmlspecialchars($bill['House_Number'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
            </table>
        </div>

        <p>To avoid late payment surcharges or service interruption, please settle your bill online before the due date.</p>
        ';

        $actionUrl = (defined('BASE_URL') ? BASE_URL : '/Public_Utility_Management_System/') . 'customer/customer_make_payment.php';
        $fullHtml = buildBrandedEmailHtml("Payment Due Reminder", $preheader, $htmlContent, $actionUrl, "Pay Now ($amountFormatted)");

        $results['email'] = sendEmailNotification(
            $conn,
            $email,
            $customerName,
            $subject,
            $fullHtml,
            strip_tags($htmlContent),
            [],
            'Due_Reminder',
            $bill['Customer_ID'] ?? null,
            $billId
        );
    }

    // 2. SMS Reminder
    if (($settings['notify_on_due_reminder_sms'] ?? '1') === '1' && !empty($phone)) {
        $smsText = "URGENT REMINDER: Dear $customerName, your $billType bill #$billId of $amountFormatted is due $daysText ($dueDate). Please pay now to avoid disconnection/late charges. Public Utility Dept.";
        $results['sms'] = sendSMSNotification(
            $conn,
            $phone,
            $smsText,
            'Due_Reminder',
            $bill['Customer_ID'] ?? null,
            $billId
        );
    }

    // 3. WhatsApp Reminder
    if (($settings['notify_on_due_reminder_whatsapp'] ?? '0') === '1' && !empty($phone)) {
        $waText = "*URGENT: Utility Bill Payment Reminder*\n\nDear {$customerName},\nYour *{$billType} Bill #{$billId}* of *{$amountFormatted}* is due *{$daysText} ({$dueDate})*.\n\n*Status:* Unpaid\n\nPlease pay your bill promptly to avoid late penalties. Thank you!";
        $results['whatsapp'] = sendWhatsAppNotification(
            $conn,
            $phone,
            $waText,
            'Due_Reminder',
            $bill['Customer_ID'] ?? null,
            $billId
        );
    }

    return $results;
}
