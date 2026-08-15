<?php
include("includes/db_connect.php");

if (!isset($_SESSION["role"])) {
    redirect("index.php");
    exit;
}

$payment_id = isset($_GET["payment_id"]) ? intval($_GET["payment_id"]) : 0;
$bill_id = isset($_GET["bill_id"]) ? intval($_GET["bill_id"]) : 0;
$type = isset($_GET["type"]) ? sanitize_input($_GET["type"]) : "Electric";

$payment = null;
if ($payment_id > 0) {
    $stmt = $conn->prepare("SELECT p.*, c.Name as CustomerName, c.Email, c.Phone, h.House_Number, h.Address
                            FROM payment p
                            LEFT JOIN customer c ON 1=1
                            LEFT JOIN house h ON c.House_ID = h.House_ID
                            WHERE p.Payment_ID = ? LIMIT 1");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$bill_amount = $payment["Amount_Paid"] ?? 0;
$date = $payment["Date_of_Payment"] ?? date("Y-m-d");
$mode = $payment["Mode_of_Payment"] ?? "Online";
$cust_name = $payment["CustomerName"] ?? $_SESSION["name"] ?? "Customer";
$house_num = $payment["House_Number"] ?? "N/A";
$address = $payment["Address"] ?? "N/A";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Receipt - #<?= $payment_id ?: $bill_id ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background: #f8fafc; color: #0f172a; padding: 40px 20px; }
        .receipt-card {
            max-width: 650px; margin: 0 auto; background: white; border-radius: 16px;
            padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;
        }
        .receipt-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 25px; }
        .brand { font-size: 20px; font-weight: 700; color: #6366f1; }
        .watermark { background: #10b98122; color: #10b981; padding: 6px 16px; border-radius: 20px; font-weight: 700; text-transform: uppercase; }
        .receipt-table { width: 100%; margin: 25px 0; border-collapse: collapse; }
        .receipt-table th, .receipt-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: left; }
        .total-row { font-size: 18px; font-weight: 700; color: #6366f1; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .receipt-card { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>
    <div class="receipt-card">
        <div class="receipt-header">
            <div>
                <div class="brand"><i class="fas fa-shield-halved"></i> Public Utility Management</div>
                <small style="color:#64748b;">Official Payment Receipt</small>
            </div>
            <div class="watermark"><i class="fas fa-check-circle"></i> PAID</div>
        </div>

        <div style="display: flex; justify-content: space-between; margin-bottom: 25px;">
            <div>
                <strong>Billed To:</strong><br>
                <?= e($cust_name) ?><br>
                House #<?= e($house_num) ?><br>
                <?= e($address) ?>
            </div>
            <div style="text-align: right;">
                <strong>Receipt No:</strong> #REC-<?= $payment_id ?: rand(1000, 9999) ?><br>
                <strong>Date:</strong> <?= date("d M Y", strtotime($date)) ?><br>
                <strong>Payment Mode:</strong> <?= e($mode) ?>
            </div>
        </div>

        <table class="receipt-table">
            <thead>
                <tr><th>Description</th><th>Type</th><th>Amount</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Utility Service Payment (Bill #<?= $bill_id ?: $payment_id ?>)</td>
                    <td><?= e($type) ?> Utility</td>
                    <td><?= format_currency($bill_amount) ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="2">Total Paid</td>
                    <td><?= format_currency($bill_amount) ?></td>
                </tr>
            </tbody>
        </table>

        <div style="text-align: center; margin-top: 30px;" class="no-print">
            <a href="download_pdf.php?type=receipt&id=<?= $payment_id ?: $bill_id ?>" class="btn btn-primary" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px; padding:10px 20px; border-radius:8px;">
                <i class="fas fa-file-pdf"></i> Download Official PDF
            </a>
            <button onclick="window.print()" class="btn btn-secondary" style="margin-left:8px; padding:10px 18px; border-radius:8px;">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="javascript:history.back()" class="btn btn-secondary" style="margin-left:8px; padding:10px 18px; border-radius:8px;"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>
</body>
</html>