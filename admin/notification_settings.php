<?php
/**
 * Public Utility Management System — Notification Engine Settings & Testing Hub
 */

include('../includes/db_connect.php');
require_once('../includes/notification_engine.php');
require_once('activity_log.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    redirect('index.php');
    exit;
}

$page_title = 'Notification Settings - Public Utility System';
$active_page = 'notification_settings';
$msg = null;
$msg_type = 'success';

// Handle Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $msg = "Invalid or expired session token. Please try again.";
        $msg_type = "error";
    } else {
        $newSettings = [
            'email_provider' => sanitize_input($_POST['email_provider'] ?? 'simulated'),
            'smtp_host' => sanitize_input($_POST['smtp_host'] ?? ''),
            'smtp_port' => sanitize_input($_POST['smtp_port'] ?? '587'),
            'smtp_secure' => sanitize_input($_POST['smtp_secure'] ?? 'tls'),
            'smtp_user' => sanitize_input($_POST['smtp_user'] ?? ''),
            'smtp_pass' => $_POST['smtp_pass'] ?? '',
            'smtp_from_email' => sanitize_input($_POST['smtp_from_email'] ?? ''),
            'smtp_from_name' => sanitize_input($_POST['smtp_from_name'] ?? ''),
            'sendgrid_api_key' => trim($_POST['sendgrid_api_key'] ?? ''),
            'sendgrid_from_email' => sanitize_input($_POST['sendgrid_from_email'] ?? ''),
            'sendgrid_from_name' => sanitize_input($_POST['sendgrid_from_name'] ?? ''),
            'sms_provider' => sanitize_input($_POST['sms_provider'] ?? 'simulated'),
            'twilio_account_sid' => trim($_POST['twilio_account_sid'] ?? ''),
            'twilio_auth_token' => trim($_POST['twilio_auth_token'] ?? ''),
            'twilio_phone_number' => sanitize_input($_POST['twilio_phone_number'] ?? ''),
            'twilio_whatsapp_number' => sanitize_input($_POST['twilio_whatsapp_number'] ?? ''),
            'fast2sms_api_key' => trim($_POST['fast2sms_api_key'] ?? ''),
            'fast2sms_route' => sanitize_input($_POST['fast2sms_route'] ?? 'q'),
            'fast2sms_sender_id' => sanitize_input($_POST['fast2sms_sender_id'] ?? 'FSTSMS'),
            'whatsapp_enabled' => isset($_POST['whatsapp_enabled']) ? '1' : '0',
            'reminder_days_before' => max(1, intval($_POST['reminder_days_before'] ?? 3)),
            'notify_on_bill_create_email' => isset($_POST['notify_on_bill_create_email']) ? '1' : '0',
            'notify_on_bill_create_sms' => isset($_POST['notify_on_bill_create_sms']) ? '1' : '0',
            'notify_on_bill_create_whatsapp' => isset($_POST['notify_on_bill_create_whatsapp']) ? '1' : '0',
            'notify_on_payment_email' => isset($_POST['notify_on_payment_email']) ? '1' : '0',
            'notify_on_payment_sms' => isset($_POST['notify_on_payment_sms']) ? '1' : '0',
            'notify_on_payment_whatsapp' => isset($_POST['notify_on_payment_whatsapp']) ? '1' : '0',
            'notify_on_due_reminder_email' => isset($_POST['notify_on_due_reminder_email']) ? '1' : '0',
            'notify_on_due_reminder_sms' => isset($_POST['notify_on_due_reminder_sms']) ? '1' : '0',
            'notify_on_due_reminder_whatsapp' => isset($_POST['notify_on_due_reminder_whatsapp']) ? '1' : '0',
            'cron_secret_token' => sanitize_input($_POST['cron_secret_token'] ?? 'pums_secure_cron_reminder_key_2026')
        ];

        if (updateNotificationSettings($conn, $newSettings)) {
            $msg = "Notification settings updated successfully!";
            $msg_type = "success";
            if (function_exists('logActivity')) {
                logActivity($_SESSION['admin_id'] ?? 1, "Updated Notification Engine Settings");
            }
        } else {
            $msg = "Failed to update settings. Please check database permissions.";
            $msg_type = "error";
        }
    }
}

// Handle Test Email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email_btn'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $msg = "Invalid session token.";
        $msg_type = "error";
    } else {
        $testEmail = sanitize_input($_POST['test_email_address'] ?? '');
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $msg = "Please enter a valid test email address.";
            $msg_type = "error";
        } else {
            // Generate sample test PDF
            $sampleBill = [
                'Bill_ID' => 9999,
                'Bill_Type' => 'Electric',
                'Customer_ID' => 1,
                'Customer_Name' => 'Valued Consumer (Test)',
                'House_Number' => 'TEST-01',
                'Address' => '123 Main Utility Ave',
                'Phone' => '9876543210',
                'Units_Consumed' => 120.5,
                'Rate_per_unit' => 7.5,
                'Bill_Amount' => 903.75,
                'Due_Date' => date('Y-m-d', strtotime('+3 days')),
                'Status' => 'Unpaid'
            ];
            $testPdf = generateBillStatementPDF($sampleBill, 'S');

            $subject = "Public Utility Notification Engine - Test Email";
            $preheader = "Your notification engine test email is successful.";
            $content = '
                <p>Hello,</p>
                <p>This is a <strong>live test message</strong> from your <strong>Public Utility Management System Notification Engine</strong>.</p>
                <div class="badge-box">
                    <p style="margin:0;"><strong>Gateway Status:</strong> Operational</p>
                    <p style="margin:5px 0 0 0;"><strong>Active Provider:</strong> ' . htmlspecialchars(getNotificationSetting($conn, 'email_provider'), ENT_QUOTES, 'UTF-8') . '</p>
                    <p style="margin:5px 0 0 0;"><strong>Timestamp:</strong> ' . date('d M Y H:i:s') . '</p>
                </div>
                <p>A sample PDF statement has been attached to verify binary attachment delivery.</p>
            ';
            $html = buildBrandedEmailHtml("Notification Engine Test", $preheader, $content);
            $attachments = [
                ['name' => 'Sample_Bill_Statement.pdf', 'data' => $testPdf, 'type' => 'application/pdf']
            ];

            $res = sendEmailNotification($conn, $testEmail, 'Test Recipient', $subject, $html, strip_tags($content), $attachments, 'Test_Message');
            if ($res['success']) {
                $msg = "Test Email sent! " . $res['message'];
                $msg_type = "success";
            } else {
                $msg = "Test Email Failed: " . $res['message'];
                $msg_type = "error";
            }
        }
    }
}

// Handle Test SMS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_sms_btn'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $msg = "Invalid session token.";
        $msg_type = "error";
    } else {
        $testPhone = sanitize_input($_POST['test_sms_phone'] ?? '');
        $smsText = "Public Utility System: Test SMS alert sent successfully on " . date('d M Y H:i:s') . ". Gateway active.";

        $res = sendSMSNotification($conn, $testPhone, $smsText, 'Test_Message');
        if ($res['success']) {
            $msg = "Test SMS Result: " . $res['message'];
            $msg_type = "success";
        } else {
            $msg = "Test SMS Failed: " . $res['message'];
            $msg_type = "error";
        }
    }
}

// Handle Test WhatsApp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_whatsapp_btn'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $msg = "Invalid session token.";
        $msg_type = "error";
    } else {
        $testPhone = sanitize_input($_POST['test_wa_phone'] ?? '');
        $waText = "*Public Utility System Test Message*\n\nHello! This is a test WhatsApp alert from your Utility Management Engine on " . date('d M Y H:i:s') . ".\n\nGateway is active and working!";

        $res = sendWhatsAppNotification($conn, $testPhone, $waText, 'Test_Message');
        if ($res['success']) {
            $msg = "Test WhatsApp Result: " . $res['message'];
            $msg_type = "success";
        } else {
            $msg = "Test WhatsApp Failed: " . $res['message'];
            $msg_type = "error";
        }
    }
}

// Fetch current settings
$s = getNotificationSettings($conn, true);
$csrf_token = generate_csrf_token();
$active_page = 'notification_settings';
$page_title = 'Notification Engine Settings - Public Utility System';
?>
<?php include('../includes/header.php'); ?>

<div class="dashboard-content">
        <?= display_flash_msg($msg, $msg_type) ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="margin: 0; font-size: 22px;"><i class="fas fa-tower-broadcast" style="color:#6366f1;"></i> Notification Engine Settings</h2>
                <p style="margin: 4px 0 0 0; color: #64748b;">Configure PHPMailer (SMTP), SendGrid, Twilio SMS & WhatsApp, and Fast2SMS API</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="<?= BASE_URL ?>admin/view_notifications.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-list-check"></i> View Notification Logs
                </a>
                <a href="<?= BASE_URL ?>cron_bill_reminders.php?token=<?= urlencode($s['cron_secret_token'] ?? '') ?>" target="_blank" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-clock-rotate-left"></i> Run Due Reminders Now
                </a>
            </div>
        </div>

        <!-- Master Form -->
        <form method="POST">
            <?= csrf_field() ?>

            <!-- 1. Email Configuration Card -->
            <div class="form-container" style="margin-bottom: 25px;">
                <h3 class="section-header" style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-envelope" style="color: #6366f1;"></i> Email Gateway Configuration
                </h3>

                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Active Email Provider</label>
                        <select name="email_provider" id="emailProviderSelect" class="form-control" onchange="toggleEmailSections()">
                            <option value="simulated" <?= ($s['email_provider'] ?? '') === 'simulated' ? 'selected' : '' ?>>Simulated Mode (Dev / Local Testing — Logs to DB)</option>
                            <option value="smtp" <?= ($s['email_provider'] ?? '') === 'smtp' || ($s['email_provider'] ?? '') === 'phpmailer_smtp' ? 'selected' : '' ?>>PHPMailer (SMTP — Gmail, Outlook, Amazon SES, Custom)</option>
                            <option value="sendgrid" <?= ($s['email_provider'] ?? '') === 'sendgrid' ? 'selected' : '' ?>>SendGrid REST API (Cloud Delivery)</option>
                            <option value="mail" <?= ($s['email_provider'] ?? '') === 'mail' ? 'selected' : '' ?>>Native PHP mail()</option>
                        </select>
                    </div>
                </div>

                <!-- SMTP Fields -->
                <div id="smtpFields" style="margin-top: 15px; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                    <h4 style="margin: 0 0 12px 0; color: #475569; font-size: 14px;"><i class="fas fa-server"></i> SMTP Connection Parameters</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control" value="<?= e($s['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                        </div>
                        <div class="form-group">
                            <label>SMTP Port</label>
                            <input type="number" name="smtp_port" class="form-control" value="<?= e($s['smtp_port'] ?? '587') ?>" placeholder="587">
                        </div>
                        <div class="form-group">
                            <label>Encryption Protocol</label>
                            <select name="smtp_secure" class="form-control">
                                <option value="tls" <?= ($s['smtp_secure'] ?? '') === 'tls' ? 'selected' : '' ?>>STARTTLS (Port 587 - Recommended)</option>
                                <option value="ssl" <?= ($s['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL / SMTPS (Port 465)</option>
                                <option value="none" <?= ($s['smtp_secure'] ?? '') === 'none' ? 'selected' : '' ?>>None (Plain / Insecure)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>SMTP Username / Email</label>
                            <input type="text" name="smtp_user" class="form-control" value="<?= e($s['smtp_user'] ?? '') ?>" placeholder="your-email@gmail.com">
                        </div>
                        <div class="form-group">
                            <label>SMTP Password / App Password</label>
                            <input type="password" name="smtp_pass" class="form-control" value="<?= e($s['smtp_pass'] ?? '') ?>" placeholder="••••••••••••••••">
                        </div>
                        <div class="form-group">
                            <label>Sender 'From' Email</label>
                            <input type="email" name="smtp_from_email" class="form-control" value="<?= e($s['smtp_from_email'] ?? '') ?>" placeholder="billing@publicutility.local">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Sender 'From' Name</label>
                            <input type="text" name="smtp_from_name" class="form-control" value="<?= e($s['smtp_from_name'] ?? '') ?>" placeholder="Public Utility Management System">
                        </div>
                    </div>
                </div>

                <!-- SendGrid Fields -->
                <div id="sendgridFields" style="margin-top: 15px; border-top: 1px dashed #e2e8f0; padding-top: 15px; display: none;">
                    <h4 style="margin: 0 0 12px 0; color: #475569; font-size: 14px;"><i class="fas fa-cloud"></i> SendGrid API Credentials</h4>
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: span 2;">
                            <label>SendGrid API Key</label>
                            <input type="password" name="sendgrid_api_key" class="form-control" value="<?= e($s['sendgrid_api_key'] ?? '') ?>" placeholder="SG.xxxxxxxxxxxxxxxxxxxxxxxxxx">
                        </div>
                        <div class="form-group">
                            <label>Verified Sender Email</label>
                            <input type="email" name="sendgrid_from_email" class="form-control" value="<?= e($s['sendgrid_from_email'] ?? '') ?>" placeholder="verified-sender@domain.com">
                        </div>
                        <div class="form-group">
                            <label>Sender Display Name</label>
                            <input type="text" name="sendgrid_from_name" class="form-control" value="<?= e($s['sendgrid_from_name'] ?? '') ?>" placeholder="Public Utility Billing">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. SMS & WhatsApp Configuration Card -->
            <div class="form-container" style="margin-bottom: 25px;">
                <h3 class="section-header" style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-comment-sms" style="color: #10b981;"></i> SMS & WhatsApp Gateway Configuration
                </h3>

                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Active SMS Provider</label>
                        <select name="sms_provider" id="smsProviderSelect" class="form-control" onchange="toggleSmsSections()">
                            <option value="simulated" <?= ($s['sms_provider'] ?? '') === 'simulated' ? 'selected' : '' ?>>Simulated Mode (Dev / Local Testing — Logs to DB)</option>
                            <option value="twilio" <?= ($s['sms_provider'] ?? '') === 'twilio' ? 'selected' : '' ?>>Twilio REST API (Global SMS & WhatsApp)</option>
                            <option value="fast2sms" <?= ($s['sms_provider'] ?? '') === 'fast2sms' ? 'selected' : '' ?>>Fast2SMS API (Indian Bulk SMS / DLT)</option>
                        </select>
                    </div>
                </div>

                <!-- Twilio Fields -->
                <div id="twilioFields" style="margin-top: 15px; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                    <h4 style="margin: 0 0 12px 0; color: #475569; font-size: 14px;"><i class="fab fa-twilio" style="color:#ef4444;"></i> Twilio API Settings</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Twilio Account SID</label>
                            <input type="text" name="twilio_account_sid" class="form-control" value="<?= e($s['twilio_account_sid'] ?? '') ?>" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                        </div>
                        <div class="form-group">
                            <label>Twilio Auth Token</label>
                            <input type="password" name="twilio_auth_token" class="form-control" value="<?= e($s['twilio_auth_token'] ?? '') ?>" placeholder="••••••••••••••••••••••••••••••••">
                        </div>
                        <div class="form-group">
                            <label>Twilio Outgoing SMS Number</label>
                            <input type="text" name="twilio_phone_number" class="form-control" value="<?= e($s['twilio_phone_number'] ?? '') ?>" placeholder="+1234567890">
                        </div>
                        <div class="form-group">
                            <label>Twilio WhatsApp Sender Number</label>
                            <input type="text" name="twilio_whatsapp_number" class="form-control" value="<?= e($s['twilio_whatsapp_number'] ?? '') ?>" placeholder="whatsapp:+14155238886">
                        </div>
                    </div>
                </div>

                <!-- Fast2SMS Fields -->
                <div id="fast2smsFields" style="margin-top: 15px; border-top: 1px dashed #e2e8f0; padding-top: 15px; display: none;">
                    <h4 style="margin: 0 0 12px 0; color: #475569; font-size: 14px;"><i class="fas fa-mobile-screen"></i> Fast2SMS Settings</h4>
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Fast2SMS Authorization API Key</label>
                            <input type="password" name="fast2sms_api_key" class="form-control" value="<?= e($s['fast2sms_api_key'] ?? '') ?>" placeholder="Your Fast2SMS API Key">
                        </div>
                        <div class="form-group">
                            <label>SMS Route</label>
                            <select name="fast2sms_route" class="form-control">
                                <option value="q" <?= ($s['fast2sms_route'] ?? '') === 'q' ? 'selected' : '' ?>>Quick SMS Route (q)</option>
                                <option value="dlt" <?= ($s['fast2sms_route'] ?? '') === 'dlt' ? 'selected' : '' ?>>DLT Approved Sender ID (dlt)</option>
                                <option value="v3" <?= ($s['fast2sms_route'] ?? '') === 'v3' ? 'selected' : '' ?>>Promotional / Bulk (v3)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sender ID (DLT 6-character code)</label>
                            <input type="text" name="fast2sms_sender_id" class="form-control" value="<?= e($s['fast2sms_sender_id'] ?? 'FSTSMS') ?>" placeholder="FSTSMS">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Automated Triggers & Reminder Thresholds -->
            <div class="form-container" style="margin-bottom: 25px;">
                <h3 class="section-header" style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-sliders" style="color: #f59e0b;"></i> Automation & Due Date Reminders
                </h3>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Bill Due Reminder Window (Days Before Due Date)</label>
                        <input type="number" name="reminder_days_before" class="form-control" value="<?= e($s['reminder_days_before'] ?? '3') ?>" min="1" max="30" required>
                        <small style="color:#64748b;">The automated cron engine will notify customers this many days before their due date.</small>
                    </div>

                    <div class="form-group">
                        <label>Cron Secret Security Token</label>
                        <input type="text" name="cron_secret_token" class="form-control" value="<?= e($s['cron_secret_token'] ?? '') ?>" required>
                        <small style="color:#64748b;">Protect your automated cron endpoint: <code>cron_bill_reminders.php?token=TOKEN</code></small>
                    </div>
                </div>

                <div style="margin-top: 20px; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                    <h4 style="margin: 0 0 15px 0; color: #475569; font-size: 14px;">Notification Dispatch Triggers</h4>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
                        <!-- Bill Creation Trigger -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px;">
                            <strong style="display:block; margin-bottom: 10px; color:#1e293b;"><i class="fas fa-file-invoice-dollar" style="color:#6366f1;"></i> Upon Bill Generation</strong>
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                                <input type="checkbox" name="notify_on_bill_create_email" value="1" <?= ($s['notify_on_bill_create_email'] ?? '1') === '1' ? 'checked' : '' ?>> Email PDF Bill Statement
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                                <input type="checkbox" name="notify_on_bill_create_sms" value="1" <?= ($s['notify_on_bill_create_sms'] ?? '1') === '1' ? 'checked' : '' ?>> Send SMS Bill Alert
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="notify_on_bill_create_whatsapp" value="1" <?= ($s['notify_on_bill_create_whatsapp'] ?? '0') === '1' ? 'checked' : '' ?>> Send WhatsApp Alert
                            </label>
                        </div>

                        <!-- Payment Receipt Trigger -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px;">
                            <strong style="display:block; margin-bottom: 10px; color:#1e293b;"><i class="fas fa-receipt" style="color:#10b981;"></i> Upon Payment Completion</strong>
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                                <input type="checkbox" name="notify_on_payment_email" value="1" <?= ($s['notify_on_payment_email'] ?? '1') === '1' ? 'checked' : '' ?>> Email PDF Payment Receipt
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                                <input type="checkbox" name="notify_on_payment_sms" value="1" <?= ($s['notify_on_payment_sms'] ?? '1') === '1' ? 'checked' : '' ?>> Send SMS Payment Confirmation
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="notify_on_payment_whatsapp" value="1" <?= ($s['notify_on_payment_whatsapp'] ?? '0') === '1' ? 'checked' : '' ?>> Send WhatsApp Confirmation
                            </label>
                        </div>

                        <!-- 3-Day Due Date Reminder Trigger -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px;">
                            <strong style="display:block; margin-bottom: 10px; color:#1e293b;"><i class="fas fa-bell" style="color:#e11d48;"></i> 3-Day Due Date Reminder</strong>
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                                <input type="checkbox" name="notify_on_due_reminder_email" value="1" <?= ($s['notify_on_due_reminder_email'] ?? '1') === '1' ? 'checked' : '' ?>> Email Due Reminder Alert
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                                <input type="checkbox" name="notify_on_due_reminder_sms" value="1" <?= ($s['notify_on_due_reminder_sms'] ?? '1') === '1' ? 'checked' : '' ?>> Send SMS Due Reminder
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="notify_on_due_reminder_whatsapp" value="1" <?= ($s['notify_on_due_reminder_whatsapp'] ?? '0') === '1' ? 'checked' : '' ?>> Send WhatsApp Due Reminder
                            </label>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 25px; text-align: right;">
                    <button type="submit" name="save_settings" class="btn btn-primary" style="padding: 12px 28px; font-size: 15px;">
                        <i class="fas fa-floppy-disk"></i> Save Configuration
                    </button>
                </div>
            </div>
        </form>

        <!-- 4. Interactive Testing Sandbox -->
        <div class="form-container" style="margin-bottom: 25px;">
            <h3 class="section-header" style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-flask" style="color: #8b5cf6;"></i> Live Notification Testing Sandbox
            </h3>
            <p style="color: #64748b; margin-top: -5px; font-size: 14px;">Verify your SMTP, SendGrid, Twilio, and Fast2SMS credentials with immediate feedback.</p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 15px;">
                <!-- Test Email -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #1e293b;"><i class="fas fa-paper-plane" style="color:#6366f1;"></i> Send Test Email</h4>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>Recipient Email Address</label>
                            <input type="email" name="test_email_address" class="form-control" placeholder="user@example.com" required>
                        </div>
                        <button type="submit" name="test_email_btn" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-envelope"></i> Send Test Email (with PDF)
                        </button>
                    </form>
                </div>

                <!-- Test SMS -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #1e293b;"><i class="fas fa-comment-sms" style="color:#10b981;"></i> Send Test SMS</h4>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>Mobile Number (with +Country Code)</label>
                            <input type="text" name="test_sms_phone" class="form-control" placeholder="+919876543210" required>
                        </div>
                        <button type="submit" name="test_sms_btn" class="btn btn-primary" style="width: 100%; background: linear-gradient(135deg,#10b981,#059669);">
                            <i class="fas fa-mobile-screen"></i> Send Test SMS Alert
                        </button>
                    </form>
                </div>

                <!-- Test WhatsApp -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #1e293b;"><i class="fab fa-whatsapp" style="color:#22c55e;"></i> Send Test WhatsApp</h4>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>WhatsApp Number (+Country Code)</label>
                            <input type="text" name="test_wa_phone" class="form-control" placeholder="+919876543210" required>
                        </div>
                        <button type="submit" name="test_whatsapp_btn" class="btn btn-primary" style="width: 100%; background: linear-gradient(135deg,#22c55e,#16a34a);">
                            <i class="fab fa-whatsapp"></i> Send Test WhatsApp Msg
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<script>
function toggleEmailSections() {
    const p = document.getElementById('emailProviderSelect').value;
    const smtp = document.getElementById('smtpFields');
    const sg = document.getElementById('sendgridFields');
    if (p === 'smtp' || p === 'phpmailer_smtp' || p === 'phpmailer') {
        smtp.style.display = 'block';
        sg.style.display = 'none';
    } else if (p === 'sendgrid') {
        smtp.style.display = 'none';
        sg.style.display = 'block';
    } else {
        smtp.style.display = 'none';
        sg.style.display = 'none';
    }
}

function toggleSmsSections() {
    const p = document.getElementById('smsProviderSelect').value;
    const twilio = document.getElementById('twilioFields');
    const fast2sms = document.getElementById('fast2smsFields');
    if (p === 'twilio') {
        twilio.style.display = 'block';
        fast2sms.style.display = 'none';
    } else if (p === 'fast2sms') {
        twilio.style.display = 'none';
        fast2sms.style.display = 'block';
    } else {
        twilio.style.display = 'none';
        fast2sms.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    toggleEmailSections();
    toggleSmsSections();
});
</script>

<?php include('../includes/footer.php'); ?>
