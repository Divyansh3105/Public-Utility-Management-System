<?php
/**
 * Public Utility Management System — On-Demand PDF Downloader & Previewer
 * Streams dynamic PDF Bill Statements and Payment Receipts directly to browser
 */

require_once(__DIR__ . '/includes/db_connect.php');
require_once(__DIR__ . '/includes/pdf_generator.php');

if (!isset($_SESSION['role'])) {
    redirect('index.php');
    exit;
}

$role = $_SESSION['role'];
$sessionCustomerId = intval($_SESSION['customer_id'] ?? 0);

$docType = sanitize_input($_GET['type'] ?? 'bill'); // 'bill' or 'receipt'
$id = intval($_GET['id'] ?? 0);
$billType = sanitize_input($_GET['bill_type'] ?? 'Electric');
$action = (isset($_GET['action']) && strtolower($_GET['action']) === 'inline') ? 'I' : 'D';

if ($id <= 0) {
    die("Invalid document ID.");
}

// 1. Download Bill Statement PDF
if ($docType === 'bill') {
    $billTable = (strtolower($billType) === 'water') ? 'water_bill' : 'electric_bill';

    $stmt = $conn->prepare("
        SELECT b.*, c.Name as Customer_Name, c.Email, c.Phone, h.House_Number, h.Address, '$billType' as Bill_Type
        FROM `$billTable` b
        LEFT JOIN customer c ON b.Customer_ID = c.Customer_ID
        LEFT JOIN house h ON b.House_ID = h.House_ID
        WHERE b.Bill_ID = ?
        LIMIT 1
    ");

    if (!$stmt) die("Database error: " . $conn->error);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$bill) {
        die("Bill #$id not found.");
    }

    // Role-based security check
    if ($role === 'customer' && intval($bill['Customer_ID']) !== $sessionCustomerId) {
        http_response_code(403);
        die("Access Denied: You do not have permission to access this bill statement.");
    }

    $filename = "Bill_Statement_{$billType}_{$id}.pdf";
    generateBillStatementPDF($bill, $action, $filename);
    exit;
}

// 2. Download Payment Receipt PDF
if ($docType === 'receipt') {
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

    if (!$stmt) die("Database error: " . $conn->error);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        die("Payment Receipt #$id not found.");
    }

    // Role-based security check
    if ($role === 'customer' && intval($payment['Customer_ID']) !== $sessionCustomerId) {
        http_response_code(403);
        die("Access Denied: You do not have permission to access this receipt.");
    }

    $filename = "Payment_Receipt_REC_{$id}.pdf";
    generatePaymentReceiptPDF($payment, $action, $filename);
    exit;
}

die("Invalid request type.");
