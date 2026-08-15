-- Public Utility Management System — Notification Engine Tables & Initial Settings

-- 1. Notification Settings Table
CREATE TABLE IF NOT EXISTS `notification_settings` (
  `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default Settings Seed Data
INSERT INTO `notification_settings` (`setting_key`, `setting_value`) VALUES
('email_provider', 'simulated'),
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_secure', 'tls'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_from_email', 'billing@publicutility.local'),
('smtp_from_name', 'Public Utility Management System'),
('sendgrid_api_key', ''),
('sendgrid_from_email', 'billing@publicutility.local'),
('sendgrid_from_name', 'Public Utility Management System'),
('sms_provider', 'simulated'),
('twilio_account_sid', ''),
('twilio_auth_token', ''),
('twilio_phone_number', ''),
('twilio_whatsapp_number', ''),
('fast2sms_api_key', ''),
('fast2sms_route', 'q'),
('fast2sms_sender_id', 'FSTSMS'),
('whatsapp_enabled', '0'),
('reminder_days_before', '3'),
('notify_on_bill_create_email', '1'),
('notify_on_bill_create_sms', '1'),
('notify_on_bill_create_whatsapp', '0'),
('notify_on_payment_email', '1'),
('notify_on_payment_sms', '1'),
('notify_on_payment_whatsapp', '0'),
('notify_on_due_reminder_email', '1'),
('notify_on_due_reminder_sms', '1'),
('notify_on_due_reminder_whatsapp', '0'),
('cron_secret_token', 'pums_secure_cron_reminder_key_2026')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;

-- 2. Notification Logs Table
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
