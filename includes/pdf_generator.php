<?php
/**
 * Public Utility Management System — Standalone PDF Generation Service
 * Generates PDF Bill Statements and Official Payment Receipts
 */

require_once(__DIR__ . '/lib/fpdf/fpdf.php');
require_once(__DIR__ . '/config.php');

class UtilityPDF extends FPDF
{
    public string $docType = 'STATEMENT'; // STATEMENT or RECEIPT
    public string $docNumber = '';

    public function Header(): void
    {
        // Top accent bar
        $this->SetFillColor(99, 102, 241); // Indigo #6366f1
        $this->Rect(0, 0, 210, 6, 'F');

        // Brand Name & Subtitle
        $this->SetXY(15, 12);
        $this->SetFont('Helvetica', 'B', 18);
        $this->SetTextColor(30, 41, 59); // Slate 800
        $this->Cell(110, 8, 'PUBLIC UTILITY SYSTEM', 0, 1, 'L');

        $this->SetX(15);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(100, 116, 139); // Slate 500
        $this->Cell(110, 5, 'Municipal Utilities & Services Authority - Government of India', 0, 1, 'L');

        $this->SetX(15);
        $this->SetFont('Helvetica', '', 8);
        $this->Cell(110, 4, 'Support: 1800-111-2233 | Web: support@publicutility.local', 0, 0, 'L');

        // Right side: Document Title & Badge
        $this->SetXY(130, 12);
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(99, 102, 241);
        $this->Cell(65, 7, strtoupper($this->docType), 0, 1, 'R');

        $this->SetXY(130, 20);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(71, 85, 105);
        $this->Cell(65, 5, $this->docNumber, 0, 1, 'R');

        // Divider Line
        $this->SetDrawColor(226, 232, 240); // Slate 200
        $this->SetLineWidth(0.5);
        $this->Line(15, 32, 195, 32);

        $this->SetY(38);
    }

    public function Footer(): void
    {
        $this->SetY(-22);
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.3);
        $this->Line(15, $this->GetY(), 195, $this->GetY());

        $this->SetY(-18);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(90, 4, 'This is a computer-generated document. No physical signature required.', 0, 0, 'L');
        $this->Cell(90, 4, 'Page ' . $this->PageNo() . ' of {nb}', 0, 1, 'R');

        $this->SetX(15);
        $this->Cell(180, 4, 'Official Document • Public Utility Management System v2.0 • ' . date('d M Y H:i:s'), 0, 0, 'C');
    }
}

/**
 * Generate a PDF Bill Statement
 *
 * @param array $bill Data array containing customer, house, and bill details
 * @param string $dest Output destination ('S' = return binary string, 'D' = download, 'I' = inline, 'F' = save file)
 * @param string $filename File path or download name
 * @return string PDF binary content when dest is 'S'
 */
function generateBillStatementPDF(array $bill, string $dest = 'S', string $filename = ''): string
{
    $pdf = new UtilityPDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->docType = ($bill['Bill_Type'] ?? 'Utility') . ' BILL STATEMENT';
    $pdf->docNumber = '#BILL-' . ($bill['Bill_ID'] ?? '0000');
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    // 1. Customer & Bill Meta Box (2 columns)
    $yStart = 38;

    // Left Column: Customer & Property Info
    $pdf->SetXY(15, $yStart);
    $pdf->SetFillColor(248, 250, 252); // #f8fafc
    $pdf->RoundedRect(15, $yStart, 85, 42, 3, 'F');

    $pdf->SetXY(18, $yStart + 3);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(99, 102, 241);
    $pdf->Cell(79, 5, 'BILLED TO (CONSUMER)', 0, 1, 'L');

    $pdf->SetX(18);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->Cell(79, 5, $bill['Customer_Name'] ?? 'Customer', 0, 1, 'L');

    $pdf->SetX(18);
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(79, 4.5, 'Consumer ID: #' . ($bill['Customer_ID'] ?? 'N/A'), 0, 1, 'L');

    $houseText = 'House #' . ($bill['House_Number'] ?? 'N/A');
    if (!empty($bill['Address'])) {
        $houseText .= ', ' . $bill['Address'];
    }
    $pdf->SetX(18);
    $pdf->MultiCell(79, 4, $houseText, 0, 'L');

    if (!empty($bill['Phone'])) {
        $pdf->SetX(18);
        $pdf->Cell(79, 4, 'Phone: ' . $bill['Phone'], 0, 1, 'L');
    }

    // Right Column: Statement Information
    $pdf->SetXY(110, $yStart);
    $pdf->RoundedRect(110, $yStart, 85, 42, 3, 'F');

    $pdf->SetXY(113, $yStart + 3);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(99, 102, 241);
    $pdf->Cell(79, 5, 'STATEMENT SUMMARY', 0, 1, 'L');

    $billDate = !empty($bill['Bill_Date']) ? date('d M Y', strtotime($bill['Bill_Date'])) : date('d M Y');
    $dueDate = !empty($bill['Due_Date']) ? date('d M Y', strtotime($bill['Due_Date'])) : 'N/A';
    $status = strtoupper($bill['Status'] ?? 'UNPAID');

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(71, 85, 105);

    $pdf->SetX(113);
    $pdf->Cell(40, 5, 'Bill Issue Date:', 0, 0, 'L');
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(39, 5, $billDate, 0, 1, 'R');

    $pdf->SetX(113);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(40, 5, 'Payment Due Date:', 0, 0, 'L');
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(225, 29, 72); // Red alert for due date
    $pdf->Cell(39, 5, $dueDate, 0, 1, 'R');

    $pdf->SetX(113);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(40, 5, 'Bill Status:', 0, 0, 'L');

    if ($status === 'PAID') {
        $pdf->SetTextColor(16, 185, 129); // Emerald Green
    } else {
        $pdf->SetTextColor(239, 68, 68); // Red
    }
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(39, 5, $status, 0, 1, 'R');

    $pdf->SetX(113);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(40, 5, 'Utility Type:', 0, 0, 'L');
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(39, 5, ($bill['Bill_Type'] ?? 'Electric') . ' Service', 0, 1, 'R');

    // 2. Consumption & Tariff Table
    $pdf->SetY(87);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->Cell(180, 7, 'Tariff & Consumption Breakdown', 0, 1, 'L');

    // Table Header
    $pdf->SetFillColor(79, 70, 229); // Indigo 600
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(70, 8, ' Description / Service', 0, 0, 'L', true);
    $pdf->Cell(35, 8, 'Consumption', 0, 0, 'C', true);
    $pdf->Cell(35, 8, 'Tariff Rate (INR)', 0, 0, 'R', true);
    $pdf->Cell(40, 8, 'Amount (INR) ', 0, 1, 'R', true);

    // Table Row
    $isWater = strtolower($bill['Bill_Type'] ?? '') === 'water';
    $unitLabel = $isWater ? ' Liters' : ' kWh (Units)';
    $rateLabel = $isWater ? ' / Liter' : ' / Unit';
    $desc = $isWater ? 'Domestic Water Utility Consumption' : 'Domestic Electricity Consumption';

    $consumption = floatval($bill['Units_Consumed'] ?? $bill['Consumption_Liters'] ?? $bill['Consumption'] ?? 0);
    $rate = floatval($bill['Rate_per_unit'] ?? $bill['Rate_per_liter'] ?? $bill['Rate'] ?? 0);
    $amount = floatval($bill['Bill_Amount'] ?? ($consumption * $rate));

    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetTextColor(51, 65, 85);
    $pdf->SetFont('Helvetica', '', 9);

    $pdf->Cell(70, 9, ' ' . $desc, 'B', 0, 'L', true);
    $pdf->Cell(35, 9, number_format($consumption, 2) . $unitLabel, 'B', 0, 'C', true);
    $pdf->Cell(35, 9, 'Rs. ' . number_format($rate, 2) . $rateLabel, 'B', 0, 'R', true);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(40, 9, 'Rs. ' . number_format($amount, 2) . ' ', 'B', 1, 'R', true);

    // Additional row for zero fixed charges / taxes for completeness
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(70, 7, ' Municipal Utility Surcharge & Tax', 'B', 0, 'L', false);
    $pdf->Cell(35, 7, 'Included', 'B', 0, 'C', false);
    $pdf->Cell(35, 7, '0.00', 'B', 0, 'R', false);
    $pdf->Cell(40, 7, 'Rs. 0.00 ', 'B', 1, 'R', false);

    // Total Amount Row
    $pdf->SetY($pdf->GetY() + 4);
    $pdf->SetFillColor(238, 242, 255); // Indigo 50
    $pdf->RoundedRect(100, $pdf->GetY(), 95, 18, 2, 'F');

    $pdf->SetXY(105, $pdf->GetY() + 3);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(79, 70, 229);
    $pdf->Cell(40, 5, 'TOTAL AMOUNT DUE:', 0, 0, 'L');
    $pdf->SetFont('Helvetica', 'B', 13);
    $pdf->Cell(45, 5, 'Rs. ' . number_format($amount, 2), 0, 1, 'R');

    $pdf->SetX(105);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(85, 4, 'Pay on or before ' . $dueDate . ' to avoid late fee.', 0, 1, 'L');

    // 3. Payment Instructions & QR Card
    $pdf->SetY(145);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->RoundedRect(15, 145, 180, 48, 3, 'DF');

    $pdf->SetXY(20, 149);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->Cell(170, 5, 'HOW TO PAY YOUR BILL', 0, 1, 'L');

    $pdf->SetXY(20, 156);
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->MultiCell(170, 4.5, "1. Online Portal: Log in to your Customer Portal and pay securely via UPI, NetBanking, Debit/Credit Card.\n2. In-Person / Cash Desk: Visit the nearest Municipal Utility Counter during working hours (9:00 AM - 5:00 PM).\n3. UPI Direct: Scan UPI QR code in the Customer Portal or use Virtual Payment Address (VPA): pay.utility@icici\n4. Please mention Bill Reference #BILL-" . ($bill['Bill_ID'] ?? '') . " for instant reconciliation.");

    // 4. Status Watermark
    if ($status === 'PAID') {
        $pdf->SetXY(15, 205);
        $pdf->SetFillColor(209, 250, 229); // Emerald 100
        $pdf->RoundedRect(15, 205, 180, 14, 3, 'F');
        $pdf->SetXY(15, 209);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(5, 150, 105);
        $pdf->Cell(180, 6, 'PAID - THANK YOU FOR YOUR PROMPT PAYMENT', 0, 0, 'C');
    } else {
        $pdf->SetXY(15, 205);
        $pdf->SetFillColor(254, 242, 242); // Rose 50
        $pdf->RoundedRect(15, 205, 180, 14, 3, 'F');
        $pdf->SetXY(15, 209);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(225, 29, 72);
        $pdf->Cell(180, 6, 'PAYMENT PENDING - DUE DATE: ' . $dueDate, 0, 0, 'C');
    }

    if (empty($filename)) {
        $filename = 'Bill_Statement_' . ($bill['Bill_Type'] ?? 'Utility') . '_' . ($bill['Bill_ID'] ?? '0') . '.pdf';
    }

    return $pdf->Output($dest, $filename);
}

/**
 * Generate a PDF Payment Receipt
 *
 * @param array $payment Data array containing payment, customer, and bill details
 * @param string $dest Output destination ('S' = return binary string, 'D' = download, 'I' = inline, 'F' = save file)
 * @param string $filename File path or download name
 * @return string PDF binary content when dest is 'S'
 */
function generatePaymentReceiptPDF(array $payment, string $dest = 'S', string $filename = ''): string
{
    $pdf = new UtilityPDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->docType = 'OFFICIAL RECEIPT';
    $pdf->docNumber = '#REC-' . ($payment['Payment_ID'] ?? rand(1000, 9999));
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    $yStart = 38;

    // 1. Success Banner
    $pdf->SetXY(15, $yStart);
    $pdf->SetFillColor(236, 253, 245); // Green 50
    $pdf->SetDrawColor(167, 243, 208); // Green 200
    $pdf->RoundedRect(15, $yStart, 180, 16, 3, 'DF');

    $pdf->SetXY(15, $yStart + 3);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(5, 150, 105);
    $pdf->Cell(180, 5, 'PAYMENT CONFIRMATION - TRANSACTION SUCCESSFUL', 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(4, 120, 87);
    $pdf->Cell(180, 4, 'Thank you! Your payment has been received and credited to your utility account.', 0, 1, 'C');

    // 2. Details Grid (2 Columns)
    $yGrid = $yStart + 22;

    // Left Box: Payer Details
    $pdf->SetXY(15, $yGrid);
    $pdf->SetFillColor(248, 250, 252);
    $pdf->RoundedRect(15, $yGrid, 85, 40, 3, 'F');

    $pdf->SetXY(18, $yGrid + 3);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(99, 102, 241);
    $pdf->Cell(79, 5, 'RECEIVED FROM (CUSTOMER)', 0, 1, 'L');

    $pdf->SetX(18);
    $pdf->SetFont('Helvetica', 'B', 10.5);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->Cell(79, 5, $payment['Customer_Name'] ?? 'Customer', 0, 1, 'L');

    $pdf->SetX(18);
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(79, 4.5, 'Customer ID: #' . ($payment['Customer_ID'] ?? 'N/A'), 0, 1, 'L');

    $houseText = 'House #' . ($payment['House_Number'] ?? 'N/A');
    if (!empty($payment['Address'])) {
        $houseText .= ', ' . $payment['Address'];
    }
    $pdf->SetX(18);
    $pdf->MultiCell(79, 4, $houseText, 0, 'L');

    // Right Box: Transaction Meta
    $pdf->SetXY(110, $yGrid);
    $pdf->RoundedRect(110, $yGrid, 85, 40, 3, 'F');

    $pdf->SetXY(113, $yGrid + 3);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(99, 102, 241);
    $pdf->Cell(79, 5, 'TRANSACTION DETAILS', 0, 1, 'L');

    $payDate = !empty($payment['Date_of_Payment']) ? date('d M Y', strtotime($payment['Date_of_Payment'])) : date('d M Y');
    $mode = $payment['Mode_of_Payment'] ?? 'Online';

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(71, 85, 105);

    $pdf->SetX(113);
    $pdf->Cell(40, 5, 'Receipt No:', 0, 0, 'L');
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(39, 5, '#REC-' . ($payment['Payment_ID'] ?? '0'), 0, 1, 'R');

    $pdf->SetX(113);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(40, 5, 'Payment Date:', 0, 0, 'L');
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(39, 5, $payDate, 0, 1, 'R');

    $pdf->SetX(113);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(40, 5, 'Payment Mode:', 0, 0, 'L');
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(16, 185, 129);
    $pdf->Cell(39, 5, $mode, 0, 1, 'R');

    $pdf->SetX(113);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(40, 5, 'Bill Reference:', 0, 0, 'L');
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(39, 5, ($payment['Bill_Type'] ?? 'Utility') . ' #' . ($payment['Bill_ID'] ?? '0'), 0, 1, 'R');

    // 3. Payment Items Table
    $pdf->SetY($yGrid + 47);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->Cell(180, 7, 'Payment Summary & Applied Charges', 0, 1, 'L');

    $pdf->SetFillColor(79, 70, 229);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(90, 8, ' Particulars', 0, 0, 'L', true);
    $pdf->Cell(45, 8, 'Account / Type', 0, 0, 'C', true);
    $pdf->Cell(45, 8, 'Amount Paid (INR) ', 0, 1, 'R', true);

    $amountPaid = floatval($payment['Amount_Paid'] ?? 0);
    $billType = $payment['Bill_Type'] ?? 'Electric';

    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetTextColor(51, 65, 85);
    $pdf->SetFont('Helvetica', '', 9);

    $pdf->Cell(90, 9, ' Settlement of ' . $billType . ' Bill #' . ($payment['Bill_ID'] ?? ''), 'B', 0, 'L', true);
    $pdf->Cell(45, 9, $billType . ' Utility Account', 'B', 0, 'C', true);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(45, 9, 'Rs. ' . number_format($amountPaid, 2) . ' ', 'B', 1, 'R', true);

    // Total Paid Box
    $pdf->SetY($pdf->GetY() + 4);
    $pdf->SetFillColor(236, 253, 245);
    $pdf->RoundedRect(100, $pdf->GetY(), 95, 16, 2, 'F');

    $pdf->SetXY(105, $pdf->GetY() + 3);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(5, 150, 105);
    $pdf->Cell(40, 5, 'NET AMOUNT PAID:', 0, 0, 'L');
    $pdf->SetFont('Helvetica', 'B', 12.5);
    $pdf->Cell(45, 5, 'Rs. ' . number_format($amountPaid, 2), 0, 1, 'R');

    $pdf->SetX(105);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(85, 4, 'Status: Cleared / Verified via ' . $mode, 0, 1, 'L');

    // 4. Authorized Stamp & Notes
    $pdf->SetY(175);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->RoundedRect(15, 175, 180, 42, 3, 'DF');

    $pdf->SetXY(20, 179);
    $pdf->SetFont('Helvetica', 'B', 9.5);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->Cell(170, 5, 'TERMS & VERIFICATION NOTE', 0, 1, 'L');

    $pdf->SetXY(20, 185);
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->MultiCell(170, 4.5, "1. This receipt acts as official proof of payment for your municipal utility service.\n2. Electronic transaction records are maintained in the central database under Receipt Reference #REC-" . ($payment['Payment_ID'] ?? '') . ".\n3. For any billing inquiries or discrepancies, please quote your Receipt Number and Customer ID to customer support.\n4. Keep this digital receipt for your personal tax and audit records.");

    if (empty($filename)) {
        $filename = 'Receipt_REC_' . ($payment['Payment_ID'] ?? '0') . '.pdf';
    }

    return $pdf->Output($dest, $filename);
}
