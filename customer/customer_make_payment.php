<?php
include('../includes/db_connect.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    redirect('index.php');
    exit;
}

$name = $_SESSION['name'];
$customer_id = $_SESSION['customer_id'] ?? null;

// Get customer ID if not in session - USE PREPARED STATEMENT
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
    // FIX: Always verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $msg = "Invalid request. Please try again.";
        $msg_type = "error";
    } else {
        $bill_id = intval($_POST['bill_id']);
        $bill_type = $_POST['bill_type']; // From hidden field
        $amount = floatval($_POST['amount']);
        $mode = sanitize_input($_POST['mode']);
        $date = date('Y-m-d');

        // FIX: Validate bill_type
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
                        $msg = "Payment Successful! Digital PDF receipt has been generated, emailed, and SMS confirmation dispatched.";
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

// Fetch unpaid bills - USE PREPARED STATEMENT
$bills = [];
$stmt = $conn->prepare("SELECT Bill_ID, Bill_Amount, Status, 'Electric' as Bill_Type FROM electric_bill WHERE Customer_ID=? AND Status='Unpaid'");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $bills[] = $row;
}
$stmt->close();

$stmt = $conn->prepare("SELECT Bill_ID, Bill_Amount, Status, 'Water' as Bill_Type FROM water_bill WHERE Customer_ID=? AND Status='Unpaid'");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $bills[] = $row;
}
$stmt->close();

$csrf_token = generate_csrf_token();
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
    <!-- Fixed Header -->
    <header class="dashboard-header" id="header">
        <div class="header-left">
            <h1>
                <i class="fas fa-wallet"></i>
                Make Payment
            </h1>
            <p>Pay your utility bills quickly and securely</p>
        </div>
        <div class="header-actions">
            <button id="toggle-theme" class="btn-icon">
                <i class="fas fa-moon"></i>
                <span>Dark Mode</span>
            </button>
            <a href="dashboard_customer.php" class="btn-icon">
                <i class="fas fa-arrow-left"></i>
                <span>Back</span>
            </a>
            <a href="../logout.php" class="btn-icon logout">
                <i class="fas fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <div class="dashboard-content">

        <?= display_flash_msg($toast ?? $msg ?? null, $toast_type ?? $msg_type ?? "success") ?>

        <?php if (!empty($last_paid_receipt_id)): ?>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 20px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <i class="fas fa-circle-check" style="font-size: 32px; color: #10b981;"></i>
                    <div>
                        <h4 style="margin: 0; color: #065f46; font-size: 16px;">Payment Cleared & Official Receipt Generated!</h4>
                        <p style="margin: 4px 0 0 0; color: #047857; font-size: 14px;">Receipt #REC-<?= $last_paid_receipt_id ?> is emailed to your registered address.</p>
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

        <h2 class="section-header">
            <i class="fas fa-credit-card"></i>
            Payment Form
        </h2>

        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Select Unpaid Bill</label>
                        <select name="bill_id" id="billSelect" class="form-control" required onchange="updateBillDetails()">
                            <option value="">Choose a bill...</option>
                            <?php foreach ($bills as $b): ?>
                                <option value="<?= $b['Bill_ID'] ?>"
                                    data-amount="<?= $b['Bill_Amount'] ?>"
                                    data-type="<?= $b['Bill_Type'] ?>">
                                    <?= htmlspecialchars($b['Bill_Type']) ?> Bill #<?= $b['Bill_ID'] ?> - ₹<?= number_format($b['Bill_Amount'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="bill_type" id="billType">

                    <div class="form-group">
                        <label>Amount to Pay (₹)</label>
                        <input type="number" name="amount" id="amountInput" class="form-control" placeholder="0.00" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label>Payment Mode</label>
                        <select name="mode" class="form-control" required>
                            <option value="">Select mode...</option>
                            <option value="Cash">Cash</option>
                            <option value="Online">Online Banking</option>
                            <option value="UPI">UPI</option>
                            <option value="Card">Card</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="pay" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-check-circle"></i>
                    Pay Now
                </button>
            </form>
        </div>

        <?php if (count($bills) == 0): ?>
            <div class="alert alert-success" style="margin-top: 30px;">
                <i class="fas fa-check-circle"></i>
                <span>Great! You have no unpaid bills at the moment.</span>
            </div>
        <?php endif; ?>

    </div>

    <script>
        // Dark mode toggle
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

            if (amount && type) {
                document.getElementById('amountInput').value = amount;
                document.getElementById('amountInput').min = amount;
                document.getElementById('billType').value = type;
            }
        }
    </script>
</body>

</html>
