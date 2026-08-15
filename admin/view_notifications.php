<?php
/**
 * Public Utility Management System — Notification Logs & Audit Trail
 */

include('../includes/db_connect.php');
require_once('../includes/notification_engine.php');
require_once('activity_log.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    redirect('index.php');
    exit;
}

$page_title = 'Notification Logs - Public Utility System';
$active_page = 'notification_logs';
$msg = null;
$msg_type = 'success';

// Handle Resend / Retry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retry_log_id'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $msg = "Invalid session token.";
        $msg_type = "error";
    } else {
        $retryId = intval($_POST['retry_log_id']);
        $stmt = $conn->prepare("SELECT * FROM `notification_logs` WHERE `Log_ID` = ? LIMIT 1");
        $stmt->bind_param("i", $retryId);
        $stmt->execute();
        $logEntry = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($logEntry) {
            $channel = $logEntry['Channel'];
            $recipient = $logEntry['Recipient'];
            $subject = $logEntry['Subject'] ?? 'Notification';
            $message = $logEntry['Message'] ?? '';
            $type = $logEntry['Notification_Type'];
            $custId = $logEntry['Customer_ID'];
            $refId = $logEntry['Reference_ID'];

            if ($channel === 'Email') {
                $html = buildBrandedEmailHtml($subject, $subject, nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')));
                $res = sendEmailNotification($conn, $recipient, 'Consumer', $subject, $html, $message, [], $type, $custId, $refId);
            } elseif ($channel === 'SMS') {
                $res = sendSMSNotification($conn, $recipient, $message, $type, $custId, $refId);
            } else {
                $res = sendWhatsAppNotification($conn, $recipient, $message, $type, $custId, $refId);
            }

            if ($res['success']) {
                $msg = "Notification #$retryId re-dispatched successfully!";
                $msg_type = "success";
            } else {
                $msg = "Re-dispatch failed: " . $res['message'];
                $msg_type = "error";
            }
        }
    }
}

// Pagination & Filtering
$pagination = get_pagination_params(30);
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];

$channelFilter = sanitize_input($_GET['channel'] ?? '');
$typeFilter = sanitize_input($_GET['type'] ?? '');
$statusFilter = sanitize_input($_GET['status'] ?? '');
$search = sanitize_input($_GET['search'] ?? '');

$where = [];
$params = [];
$types = '';

if (!empty($channelFilter) && in_array($channelFilter, ['Email', 'SMS', 'WhatsApp'])) {
    $where[] = "l.Channel = ?";
    $params[] = $channelFilter;
    $types .= 's';
}

if (!empty($typeFilter) && in_array($typeFilter, ['Bill_Generated', 'Payment_Receipt', 'Due_Reminder', 'Custom_Alert', 'Test_Message'])) {
    $where[] = "l.Notification_Type = ?";
    $params[] = $typeFilter;
    $types .= 's';
}

if (!empty($statusFilter) && in_array($statusFilter, ['Sent', 'Failed', 'Simulated', 'Queued'])) {
    $where[] = "l.Status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if (!empty($search)) {
    $where[] = "(l.Recipient LIKE ? OR l.Subject LIKE ? OR l.Message LIKE ? OR c.Name LIKE ?)";
    $sParam = "%$search%";
    $params[] = $sParam;
    $params[] = $sParam;
    $params[] = $sParam;
    $params[] = $sParam;
    $types .= 'ssss';
}

$whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// Count total
$countQuery = "SELECT COUNT(*) as total FROM `notification_logs` l LEFT JOIN `customer` c ON l.Customer_ID = c.Customer_ID $whereSql";
$stmt = $conn->prepare($countQuery);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$totalPages = calculate_total_pages($totalRecords, $limit);
$stmt->close();

// Fetch Records
$dataQuery = "
    SELECT l.*, c.Name as Customer_Name
    FROM `notification_logs` l
    LEFT JOIN `customer` c ON l.Customer_ID = c.Customer_ID
    $whereSql
    ORDER BY l.Log_ID DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($dataQuery);
if ($types) {
    $typesWithLimit = $types . 'ii';
    $paramsWithLimit = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$logs = $stmt->get_result();
$stmt->close();

// Overall Stats for Top Cards
$stats = [
    'total' => 0,
    'email' => 0,
    'sms' => 0,
    'whatsapp' => 0,
    'failed' => 0
];
$statRes = $conn->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN Channel = 'Email' AND Status IN ('Sent', 'Simulated') THEN 1 ELSE 0 END) as email,
        SUM(CASE WHEN Channel = 'SMS' AND Status IN ('Sent', 'Simulated') THEN 1 ELSE 0 END) as sms,
        SUM(CASE WHEN Channel = 'WhatsApp' AND Status IN ('Sent', 'Simulated') THEN 1 ELSE 0 END) as whatsapp,
        SUM(CASE WHEN Status = 'Failed' THEN 1 ELSE 0 END) as failed
    FROM `notification_logs`
");
if ($statRes) $stats = $statRes->fetch_assoc();

$csrf_token = generate_csrf_token();
$active_page = 'notifications';
$page_title = 'Notification Logs - Public Utility System';
?>
<?php include('../includes/header.php'); ?>

<div class="dashboard-content">
        <?= display_flash_msg($msg, $msg_type) ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="margin: 0; font-size: 22px;"><i class="fas fa-tower-cell" style="color:#6366f1;"></i> Notification Logs & Audit Trail</h2>
                <p style="margin: 4px 0 0 0; color: #64748b;">Real-time history of all Email, SMS, and WhatsApp alerts dispatched by the system</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="<?= BASE_URL ?>admin/notification_settings.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-gear"></i> Notification Settings
                </a>
                <a href="<?= BASE_URL ?>cron_bill_reminders.php?token=<?= urlencode(getNotificationSetting($conn, 'cron_secret_token', 'pums_secure_cron_reminder_key_2026')) ?>" target="_blank" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-clock-rotate-left"></i> Run Due Reminders
                </a>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 25px;">
            <div class="stat-card">
                <h3><i class="fas fa-paper-plane"></i> Total Dispatched</h3>
                <div class="stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #6366f1;">
                <h3><i class="fas fa-envelope"></i> Emails Sent</h3>
                <div class="stat-value" style="color: #6366f1;"><?= number_format($stats['email'] ?? 0) ?></div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #10b981;">
                <h3><i class="fas fa-comment-sms"></i> SMS Sent</h3>
                <div class="stat-value" style="color: #10b981;"><?= number_format($stats['sms'] ?? 0) ?></div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #22c55e;">
                <h3><i class="fab fa-whatsapp"></i> WhatsApp Sent</h3>
                <div class="stat-value" style="color: #22c55e;"><?= number_format($stats['whatsapp'] ?? 0) ?></div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #ef4444;">
                <h3><i class="fas fa-triangle-exclamation"></i> Failed / Errors</h3>
                <div class="stat-value" style="color: #ef4444;"><?= number_format($stats['failed'] ?? 0) ?></div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="form-container" style="padding: 18px; margin-bottom: 20px;">
            <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 180px;">
                    <label style="font-size: 12px; color: #64748b; font-weight: bold; margin-bottom: 4px; display: block;">Search Keyword</label>
                    <input type="text" name="search" class="form-control" placeholder="Search recipient, name, subject..." value="<?= e($search) ?>">
                </div>

                <div style="min-width: 140px;">
                    <label style="font-size: 12px; color: #64748b; font-weight: bold; margin-bottom: 4px; display: block;">Channel</label>
                    <select name="channel" class="form-control">
                        <option value="">All Channels</option>
                        <option value="Email" <?= $channelFilter === 'Email' ? 'selected' : '' ?>>Email</option>
                        <option value="SMS" <?= $channelFilter === 'SMS' ? 'selected' : '' ?>>SMS</option>
                        <option value="WhatsApp" <?= $channelFilter === 'WhatsApp' ? 'selected' : '' ?>>WhatsApp</option>
                    </select>
                </div>

                <div style="min-width: 160px;">
                    <label style="font-size: 12px; color: #64748b; font-weight: bold; margin-bottom: 4px; display: block;">Notification Type</label>
                    <select name="type" class="form-control">
                        <option value="">All Types</option>
                        <option value="Bill_Generated" <?= $typeFilter === 'Bill_Generated' ? 'selected' : '' ?>>Bill Generated</option>
                        <option value="Payment_Receipt" <?= $typeFilter === 'Payment_Receipt' ? 'selected' : '' ?>>Payment Receipt</option>
                        <option value="Due_Reminder" <?= $typeFilter === 'Due_Reminder' ? 'selected' : '' ?>>Due Reminder</option>
                        <option value="Test_Message" <?= $typeFilter === 'Test_Message' ? 'selected' : '' ?>>Test Message</option>
                    </select>
                </div>

                <div style="min-width: 130px;">
                    <label style="font-size: 12px; color: #64748b; font-weight: bold; margin-bottom: 4px; display: block;">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="Sent" <?= $statusFilter === 'Sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="Simulated" <?= $statusFilter === 'Simulated' ? 'selected' : '' ?>>Simulated</option>
                        <option value="Failed" <?= $statusFilter === 'Failed' ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>

                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 18px;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <?php if ($search || $channelFilter || $typeFilter || $statusFilter): ?>
                        <a href="view_notifications.php" class="btn btn-secondary" style="height: 40px; line-height: 40px; padding: 0 14px; text-decoration: none;">
                            <i class="fas fa-xmark"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Channel</th>
                        <th>Type</th>
                        <th>Recipient & Customer</th>
                        <th>Subject / Summary</th>
                        <th>Status</th>
                        <th>Gateway</th>
                        <th>Sent At</th>
                        <th style="text-align: right; width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs && $logs->num_rows > 0): ?>
                        <?php while ($row = $logs->fetch_assoc()): ?>
                            <?php
                                $chan = $row['Channel'];
                                $chanIcon = ($chan === 'Email') ? 'fa-envelope' : (($chan === 'SMS') ? 'fa-comment-sms' : 'fa-whatsapp');
                                $chanColor = ($chan === 'Email') ? '#6366f1' : (($chan === 'SMS') ? '#10b981' : '#22c55e');

                                $status = $row['Status'];
                                $statusBadge = ($status === 'Sent')
                                    ? '<span class="badge badge-success"><i class="fas fa-check"></i> Sent</span>'
                                    : (($status === 'Simulated')
                                        ? '<span class="badge" style="background:#e0e7ff; color:#4338ca;"><i class="fas fa-flask"></i> Simulated</span>'
                                        : '<span class="badge badge-danger"><i class="fas fa-triangle-exclamation"></i> Failed</span>');

                                $cleanType = str_replace('_', ' ', $row['Notification_Type']);
                            ?>
                            <tr>
                                <td><strong>#<?= $row['Log_ID'] ?></strong></td>
                                <td>
                                    <span style="display:inline-flex; align-items:center; gap:6px; font-weight:600; color:<?= $chanColor ?>;">
                                        <i class="fas <?= $chanIcon ?>"></i> <?= e($chan) ?>
                                    </span>
                                </td>
                                <td><span style="font-size: 12px; font-weight: 600; color: #475569; background: #f1f5f9; padding: 3px 8px; border-radius: 4px;"><?= e($cleanType) ?></span></td>
                                <td>
                                    <strong><?= e($row['Recipient']) ?></strong>
                                    <?php if (!empty($row['Customer_Name'])): ?>
                                        <div style="font-size: 12px; color: #64748b;"><?= e($row['Customer_Name']) ?> (ID: #<?= $row['Customer_ID'] ?>)</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($row['Subject'] ?: $row['Message']) ?>">
                                        <strong><?= e($row['Subject'] ?: 'Alert') ?></strong>
                                    </div>
                                    <div style="font-size: 11px; color: #94a3b8; max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= e(substr($row['Message'] ?? '', 0, 80)) ?>
                                    </div>
                                </td>
                                <td><?= $statusBadge ?></td>
                                <td><small style="color: #64748b; font-family: monospace;"><?= e($row['Gateway'] ?: 'N/A') ?></small></td>
                                <td><small style="color: #64748b;"><?= date('d M Y, h:i A', strtotime($row['Sent_At'])) ?></small></td>
                                <td style="text-align: right;">
                                    <div style="display: flex; justify-content: flex-end; gap: 6px;">
                                        <button type="button" class="btn btn-secondary" style="padding: 5px 9px; font-size: 12px;" onclick="viewNotificationDetails(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($status === 'Failed'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Retry sending this notification?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="retry_log_id" value="<?= $row['Log_ID'] ?>">
                                                <button type="submit" class="btn btn-primary" style="padding: 5px 9px; font-size: 12px;" title="Retry Dispatch">
                                                    <i class="fas fa-rotate-right"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state" style="padding: 40px; text-align: center; color: #94a3b8;">
                                    <i class="fas fa-envelope-open-text" style="font-size: 40px; margin-bottom: 10px; color: #cbd5e1;"></i>
                                    <p style="margin: 0; font-size: 15px;">No notification logs found matching your criteria.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination" style="margin-top: 25px; display: flex; justify-content: center; gap: 6px;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php
                        $queryArgs = $_GET;
                        $queryArgs['page'] = $i;
                        $pageUrl = '?' . http_build_query($queryArgs);
                    ?>
                    <a href="<?= $pageUrl ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 6px 12px; font-size: 13px;">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; max-width:600px; width:90%; border-radius:12px; padding:25px; box-shadow:0 20px 40px rgba(0,0,0,0.2); max-height:85vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:15px;">
            <h3 id="modalTitle" style="margin:0; font-size:18px; color:#1e293b;">Notification Details</h3>
            <button onclick="closeModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748b;">&times;</button>
        </div>
        <div id="modalBody" style="font-size:14px; color:#334155; line-height:1.6;"></div>
        <div style="margin-top:20px; text-align:right;">
            <button onclick="closeModal()" class="btn btn-secondary">Close</button>
        </div>
    </div>
</div>

<script>
function viewNotificationDetails(data) {
    document.getElementById('modalTitle').textContent = `Notification #${data.Log_ID} Details`;
    let errorBox = '';
    if (data.Error_Message) {
        errorBox = `<div style="background:#fef2f2; border:1px solid #fecdd3; color:#991b1b; padding:10px 12px; border-radius:6px; margin:12px 0;"><strong>Error / Trace:</strong><br>${escapeHtml(data.Error_Message)}</div>`;
    }

    document.getElementById('modalBody').innerHTML = `
        <p><strong>Channel:</strong> ${escapeHtml(data.Channel)} | <strong>Type:</strong> ${escapeHtml(data.Notification_Type)}</p>
        <p><strong>Recipient:</strong> ${escapeHtml(data.Recipient)} ${data.Customer_Name ? '(' + escapeHtml(data.Customer_Name) + ')' : ''}</p>
        <p><strong>Gateway:</strong> ${escapeHtml(data.Gateway || 'N/A')} | <strong>Status:</strong> ${escapeHtml(data.Status)}</p>
        <p><strong>Timestamp:</strong> ${escapeHtml(data.Sent_At)}</p>
        ${data.Subject ? '<p><strong>Subject:</strong> ' + escapeHtml(data.Subject) + '</p>' : ''}
        ${errorBox}
        <div style="margin-top:15px;">
            <strong>Message Content:</strong>
            <pre style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; white-space:pre-wrap; font-family:sans-serif; font-size:13px; max-height:220px; overflow-y:auto; margin-top:6px;">${escapeHtml(data.Message || '')}</pre>
        </div>
    `;
    document.getElementById('detailsModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include('../includes/footer.php'); ?>
