<?php
include('../includes/db_connect.php');
require_once('../includes/tariff_engine.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    redirect('index.php');
    exit;
}

$name = $_SESSION['name'];
$customer_id = $_SESSION['customer_id'] ?? null;

// Get customer ID if not in session
if (!$customer_id) {
    $stmt = $conn->prepare("SELECT Customer_ID FROM customer WHERE Name=?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $customer_id = $result->fetch_assoc()['Customer_ID'];
        $_SESSION['customer_id'] = $customer_id;
    }
    $stmt->close();
}

if (isset($_POST['pay']) && isset($_POST['csrf_token'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $msg = "Invalid request. Please try again.";
        $msg_type = "error";
    } else {
        $bill_id = intval($_POST['bill_id']);
        $bill_type = $_POST['bill_type'];
        $amount = floatval($_POST['amount']);
        $mode = sanitize_input($_POST['mode']);
        $date = date('Y-m-d');

        if (!in_array($bill_type, ['Electric', 'Water'])) {
            $msg = "Invalid bill type!";
            $msg_type = "error";
        } elseif ($amount <= 0) {
            $msg = "Invalid amount!";
            $msg_type = "error";
        } else {
            // Verify bill exists and get amount
            if ($bill_type == 'Electric') {
                $stmt = $conn->prepare("SELECT Bill_Amount, Status FROM electric_bill WHERE Bill_ID=? AND Customer_ID=?");
            } else {
                $stmt = $conn->prepare("SELECT Bill_Amount, Status FROM water_bill WHERE Bill_ID=? AND Customer_ID=?");
            }

            $stmt->bind_param("ii", $bill_id, $customer_id);
            $stmt->execute();
            $bill_result = $stmt->get_result();

            if ($bill_result->num_rows > 0) {
                $bill_data = $bill_result->fetch_assoc();

                if ($bill_data['Status'] == 'Paid') {
                    $msg = "Bill already paid!";
                    $msg_type = "error";
                } elseif ($amount < $bill_data['Bill_Amount']) {
                    $msg = "Payment amount must be at least ₹" . number_format($bill_data['Bill_Amount'], 2);
                    $msg_type = "error";
                } else {
                    // Insert payment
                    $pay_stmt = $conn->prepare("INSERT INTO payment (Bill_Type, Bill_ID, Amount_Paid, Date_of_Payment, Mode_of_Payment) VALUES (?, ?, ?, ?, ?)");
                    $pay_stmt->bind_param("sidss", $bill_type, $bill_id, $amount, $date, $mode);

                    if ($pay_stmt->execute()) {
                        $newPaymentId = $pay_stmt->insert_id;
                        $pay_stmt->close();

                        // Update bill status
                        if ($bill_type == 'Electric') {
                            $update_stmt = $conn->prepare("UPDATE electric_bill SET Status='Paid' WHERE Bill_ID=?");
                        } else {
                            $update_stmt = $conn->prepare("UPDATE water_bill SET Status='Paid' WHERE Bill_ID=?");
                        }
                        $update_stmt->bind_param("i", $bill_id);
                        $update_stmt->execute();
                        $update_stmt->close();

                        // Trigger Automated Notification Engine (Email PDF Receipt + SMS/WhatsApp confirmation)
                        require_once('../includes/notification_engine.php');
                        $notifRes = notifyPaymentReceipt($conn, $newPaymentId);

                        $last_paid_receipt_id = $newPaymentId;
                        $msg = "Payment Successful! Digital PDF receipt #REC-$newPaymentId generated, emailed, and SMS confirmation dispatched.";
                        $msg_type = "success";
                    } else {
                        $msg = "Payment failed. Please try again.";
                        $msg_type = "error";
                        $pay_stmt->close();
                    }
                }
            } else {
                $msg = "Bill not found!";
                $msg_type = "error";
            }
            $stmt->close();
        }
    }
}

// Fetch unpaid bills with full itemized breakdown
$bills = [];
$stmt = $conn->prepare("
    SELECT eb.Bill_ID, eb.Bill_Amount, eb.Base_Amount, eb.Fixed_Charge, eb.Tax_Amount, eb.Late_Fee, eb.Due_Date, eb.Grace_Due_Date, eb.Status, 'Electric' as Bill_Type,
           COALESCE(tc.category_name, 'Domestic Plan') as category_name
    FROM electric_bill eb 
    LEFT JOIN tariff_categories tc ON eb.Tariff_Category_ID = tc.category_id 
    WHERE eb.Customer_ID=? AND eb.Status='Unpaid' 
    ORDER BY eb.Due_Date ASC
");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $bills[] = $row;
}
$stmt->close();

$stmt = $conn->prepare("
    SELECT wb.Bill_ID, wb.Bill_Amount, wb.Base_Amount, wb.Fixed_Charge, wb.Tax_Amount, wb.Late_Fee, wb.Due_Date, wb.Grace_Due_Date, wb.Status, 'Water' as Bill_Type,
           COALESCE(tc.category_name, 'Domestic Plan') as category_name
    FROM water_bill wb 
    LEFT JOIN tariff_categories tc ON wb.Tariff_Category_ID = tc.category_id 
    WHERE wb.Customer_ID=? AND wb.Status='Unpaid' 
    ORDER BY wb.Due_Date ASC
");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $bills[] = $row;
}
$stmt->close();

$csrf_token = generate_csrf_token();
$active_page = 'payment';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - Public Utility System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>

<body>
    <div class="dashboard-layout">
        <?php include('../includes/sidebar_customer.php'); ?>

        <div class="main-content">
            <header class="dashboard-header" id="header">
                <div class="header-left">
                    <button class="sidebar-mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-title-block">
                        <h1><i class="fas fa-wallet"></i> Instant Utility Checkout</h1>
                        <p>Settle outstanding electricity & water bills securely</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button id="toggle-theme" class="btn-icon">
                        <i class="fas fa-moon"></i><span>Dark Mode</span>
                    </button>
                    <a href="dashboard_customer.php" class="btn-icon">
                        <i class="fas fa-arrow-left"></i><span>Dashboard</span>
                    </a>
                    <a href="../logout.php" class="btn-icon logout">
                        <i class="fas fa-right-from-bracket"></i><span>Logout</span>
                    </a>
                </div>
            </header>

    <div class="dashboard-content">
        <?= display_flash_msg($toast ?? $msg ?? null, $toast_type ?? $msg_type ?? "success") ?>

        <?php if (!empty($last_paid_receipt_id)): ?>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 20px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <i class="fas fa-circle-check" style="font-size: 32px; color: #10b981;"></i>
                    <div>
                        <h4 style="margin: 0; color: #065f46; font-size: 16px;">Payment Cleared & Official Receipt Generated!</h4>
                        <p style="margin: 4px 0 0 0; color: #047857; font-size: 14px;">Receipt #REC-<?= $last_paid_receipt_id ?> has been dispatched to your email.</p>
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="../download_pdf.php?type=receipt&id=<?= $last_paid_receipt_id ?>" class="btn btn-primary" style="background: linear-gradient(135deg,#10b981,#059669); text-decoration: none;">
                        <i class="fas fa-file-pdf"></i> Download PDF Receipt
                    </a>
                    <a href="../print_receipt.php?payment_id=<?= $last_paid_receipt_id ?>" target="_blank" class="btn btn-secondary" style="text-decoration: none;">
                        <i class="fas fa-print"></i> View / Print
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <h2 class="section-header"><i class="fas fa-credit-card"></i> Pay Outstanding Bill</h2>

        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="bill_type" id="billType">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Select Unpaid Bill</label>
                        <select name="bill_id" id="billSelect" class="form-control" required onchange="updateBillDetails()">
                            <option value="">Choose a bill to pay...</option>
                            <?php foreach ($bills as $b): 
                                $hasLate = ((float)($b['Late_Fee'] ?? 0) > 0);
                            ?>
                                <option value="<?= $b['Bill_ID'] ?>"
                                    data-amount="<?= $b['Bill_Amount'] ?>"
                                    data-base="<?= $b['Base_Amount'] ?: $b['Bill_Amount'] ?>"
                                    data-fixed="<?= $b['Fixed_Charge'] ?: 0 ?>"
                                    data-tax="<?= $b['Tax_Amount'] ?: 0 ?>"
                                    data-late="<?= $b['Late_Fee'] ?: 0 ?>"
                                    data-due="<?= date('d M Y', strtotime($b['Due_Date'])) ?>"
                                    data-plan="<?= htmlspecialchars($b['category_name']) ?>"
                                    data-type="<?= $b['Bill_Type'] ?>">
                                    <?= htmlspecialchars($b['Bill_Type']) ?> Bill #<?= $b['Bill_ID'] ?> - ₹<?= number_format($b['Bill_Amount'], 2) ?> (Due: <?= date('d M', strtotime($b['Due_Date'])) ?>) <?= $hasLate ? '[+Late Fee]' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Amount to Pay (₹)</label>
                        <input type="number" name="amount" id="amountInput" class="form-control" placeholder="0.00" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label>Payment Mode</label>
                        <select name="mode" class="form-control" required>
                            <option value="">Select payment method...</option>
                            <option value="UPI">UPI / QR Code</option>
                            <option value="Online">Net Banking</option>
                            <option value="Card">Debit / Credit Card</option>
                            <option value="Cash">Cash at Counter</option>
                        </select>
                    </div>
                </div>

                <!-- Selected Bill Summary Breakdown Box -->
                <div id="billBreakdownBox" style="display: none; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 18px; margin-top: 15px;">
                    <h4 style="margin-top: 0; color: #4338ca;"><i class="fas fa-receipt"></i> Selected Bill Cost Breakdown</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; font-size: 14px;">
                        <div>
                            <small style="color: #64748b; display: block;">Energy / Base</small>
                            <strong id="dispBase">₹0.00</strong>
                        </div>
                        <div>
                            <small style="color: #64748b; display: block;">Fixed Meter Charge</small>
                            <strong id="dispFixed">₹0.00</strong>
                        </div>
                        <div>
                            <small style="color: #64748b; display: block;">Duty / Tax</small>
                            <strong id="dispTax">₹0.00</strong>
                        </div>
                        <div id="dispLateWrapper" style="display: none;">
                            <small style="color: #dc2626; display: block;">Late Fee Penalty</small>
                            <strong id="dispLate" style="color: #dc2626;">+₹0.00</strong>
                        </div>
                        <div style="background: #e0e7ff; padding: 6px 10px; border-radius: 6px;">
                            <small style="color: #4338ca; font-weight: bold; display: block;">Total Due</small>
                            <strong id="dispTotal" style="color: #3730a3; font-size: 16px;">₹0.00</strong>
                        </div>
                    </div>
                </div>

                <button type="submit" name="pay" class="btn btn-primary" style="margin-top: 25px; padding: 12px 25px; font-size: 16px;">
                    <i class="fas fa-check-circle"></i> Complete Payment & Download Receipt
                </button>
            </form>
        </div>

        <?php if (count($bills) == 0): ?>
            <div class="alert alert-success" style="margin-top: 30px;">
                <i class="fas fa-check-circle"></i>
                <span>Great! You have no outstanding bills at the moment. All utility accounts are clear!</span>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('toggle-theme');
            const header = document.getElementById('header');
            const saved = localStorage.getItem('theme') || 'light';

            if (saved === 'dark') {
                document.body.classList.add('dark-mode');
                btn.innerHTML = '<i class="fas fa-sun"></i><span>Light Mode</span>';
            }

            btn.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                const mode = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
                localStorage.setItem('theme', mode);
                btn.innerHTML = mode === 'dark' ?
                    '<i class="fas fa-sun"></i><span>Light Mode</span>' :
                    '<i class="fas fa-moon"></i><span>Dark Mode</span>';
            });

            window.addEventListener('scroll', () => {
                if (window.scrollY > 30) {
                    header.classList.add('shrink');
                } else {
                    header.classList.remove('shrink');
                }
            });
        });

        function updateBillDetails() {
            const select = document.getElementById('billSelect');
            const option = select.options[select.selectedIndex];
            const amount = option.getAttribute('data-amount');
            const type = option.getAttribute('data-type');
            const base = option.getAttribute('data-base');
            const fixed = option.getAttribute('data-fixed');
            const tax = option.getAttribute('data-tax');
            const late = parseFloat(option.getAttribute('data-late') || 0);
            const box = document.getElementById('billBreakdownBox');

            if (amount && type) {
                document.getElementById('amountInput').value = amount;
                document.getElementById('amountInput').min = amount;
                document.getElementById('billType').value = type;

                document.getElementById('dispBase').innerText = `₹${parseFloat(base || amount).toFixed(2)}`;
                document.getElementById('dispFixed').innerText = `₹${parseFloat(fixed || 0).toFixed(2)}`;
                document.getElementById('dispTax').innerText = `₹${parseFloat(tax || 0).toFixed(2)}`;
                document.getElementById('dispTotal').innerText = `₹${parseFloat(amount).toFixed(2)}`;

                if (late > 0) {
                    document.getElementById('dispLateWrapper').style.display = 'block';
                    document.getElementById('dispLate').innerText = `+₹${late.toFixed(2)}`;
                } else {
                    document.getElementById('dispLateWrapper').style.display = 'none';
                }

                box.style.display = 'block';
            } else {
                box.style.display = 'none';
            }
        }
    </script>
        </div> <!-- close .main-content -->
    </div> <!-- close .dashboard-layout -->

    <?php include('../includes/footer.php'); ?>
