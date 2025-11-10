<?php
session_start();
include('../includes/db_connect.php');
include('../includes/log_functions.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employee') {
    header("Location: index.php");
    exit;
}

$update_query = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bill_id = $_POST['bill_id'] ?? '';
    $amount_paid = $_POST['amount'] ?? '';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $bill_type = $_POST['bill_type'] ?? '';
    $payment_mode = $_POST['mode'] ?? NULL;

    if (!empty($bill_id) && $amount_paid !== '') {
        $bill_table = (strtolower($bill_type) === 'water') ? 'water_bill' : 'electric_bill';
        $update_query = "UPDATE `$bill_table` SET Status='Paid' WHERE Bill_ID='" . $conn->real_escape_string($bill_id) . "'";

        if ($conn->query($update_query)) {
            $stmt = $conn->prepare("INSERT INTO payment (Bill_Type, Bill_ID, Amount_Paid, Date_of_Payment, Mode_of_Payment) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sidss", $bill_type, $bill_id, $amount_paid, $payment_date, $payment_mode);
                $stmt->execute();
                $stmt->close();
            }
            if (function_exists('logEmployeeAction')) {
                $desc = 'Updated payment for ' . ($bill_type ?: 'Electric') . ' Bill ID ' . $bill_id . ' (₹' . $amount_paid . ')';
                logEmployeeAction($conn, $_SESSION['employee_id'], 'Update Payment', $desc);
            }
            $msg = "Payment Updated Successfully!";
            $msg_type = "success";
        } else {
            $msg = "Error: " . $conn->error;
            $msg_type = "error";
        }
    } else {
        $msg = "Please fill all required fields.";
        $msg_type = "error";
    }
}

$unpaid_bills = [];
$electric_stmt = $conn->prepare("SELECT eb.Bill_ID, eb.Bill_Amount, eb.Due_Date, c.Name as Customer_Name, 'Electric' as Bill_Type FROM electric_bill eb LEFT JOIN customer c ON eb.Customer_ID = c.Customer_ID WHERE eb.Status='Unpaid' ORDER BY eb.Due_Date");
$electric_stmt->execute();
$electric_result = $electric_stmt->get_result();
while ($row = $electric_result->fetch_assoc()) $unpaid_bills[] = $row;
$electric_stmt->close();

$water_stmt = $conn->prepare("SELECT wb.Bill_ID, wb.Bill_Amount, wb.Due_Date, c.Name as Customer_Name, 'Water' as Bill_Type FROM water_bill wb LEFT JOIN customer c ON wb.Customer_ID = c.Customer_ID WHERE wb.Status='Unpaid' ORDER BY wb.Due_Date");
$water_stmt->execute();
$water_result = $water_stmt->get_result();
while ($row = $water_result->fetch_assoc()) $unpaid_bills[] = $row;
$water_stmt->close();

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Payment - Public Utility System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .pagination {
            width: 100%;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .pagination .page-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 12px;
            min-width: 44px;
            justify-content: center;
            text-decoration: none;
            font-weight: 600;
            background: transparent;
            color: #333;
            border: 2px solid transparent;
            transition: all 0.25s ease;
        }

        body.dark-mode .pagination .page-btn {
            color: #e8e8e8;
        }

        .pagination .page-btn:not(.active) {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid rgba(102, 126, 234, 0.15);
        }

        body.dark-mode .pagination .page-btn:not(.active) {
            background: rgba(43, 43, 60, 0.6);
            border-color: rgba(102, 126, 234, 0.1);
        }

        .pagination .page-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff !important;
            box-shadow: 0 8px 24px rgba(118, 75, 162, 0.18);
        }

        .pagination .page-btn:hover:not(.active) {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.18);
        }
    </style>
</head>

<body>
    <header class="dashboard-header" id="header">
        <div class="header-left">
            <h1><i class="fas fa-credit-card"></i> Update Payment</h1>
            <p>Record bill payments and update status</p>
        </div>
        <div class="header-actions">
            <button id="toggle-theme" class="btn-icon">
                <i class="fas fa-moon"></i><span>Dark Mode</span>
            </button>
            <a href="dashboard_employee.php" class="btn-icon">
                <i class="fas fa-arrow-left"></i><span>Back</span>
            </a>
            <a href="../logout.php" class="btn-icon logout">
                <i class="fas fa-right-from-bracket"></i><span>Logout</span>
            </a>
        </div>
    </header>

    <div class="dashboard-content">
        <?php if (isset($msg)): ?>
            <div class="alert alert-<?= $msg_type ?>">
                <i class="fas <?= $msg_type == 'success' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                <span><?= htmlspecialchars($msg) ?></span>
            </div>
        <?php endif; ?>

        <h2 class="section-header"><i class="fas fa-money-bill-wave"></i> Payment Form</h2>

        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Select Unpaid Bill</label>
                        <select name="bill_id" id="billSelect" class="form-control" required onchange="updateBillInfo()">
                            <option value="">Choose a bill...</option>
                            <?php foreach ($unpaid_bills as $bill): ?>
                                <option value="<?= $bill['Bill_ID'] ?>"
                                    data-amount="<?= $bill['Bill_Amount'] ?>"
                                    data-type="<?= $bill['Bill_Type'] ?>"
                                    data-customer="<?= htmlspecialchars($bill['Customer_Name']) ?>"
                                    data-due="<?= $bill['Due_Date'] ?>">
                                    <?= htmlspecialchars($bill['Bill_Type']) ?> #<?= $bill['Bill_ID'] ?> -
                                    <?= htmlspecialchars($bill['Customer_Name']) ?> -
                                    ₹<?= number_format($bill['Bill_Amount'], 2) ?>
                                    (Due: <?= $bill['Due_Date'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="bill_type" id="billTypeInput">
                    <input type="hidden" name="payment_date" value="<?= date('Y-m-d') ?>">

                    <div class="form-group">
                        <label>Customer</label>
                        <input type="text" id="customerDisplay" class="form-control" placeholder="Auto-filled" readonly>
                    </div>

                    <div class="form-group">
                        <label>Amount Paid (₹)</label>
                        <input type="number" name="amount" id="amountInput" class="form-control" placeholder="0.00" step="0.01" min="0.01" required>
                    </div>

                    <div class="form-group">
                        <label>Payment Mode</label>
                        <select name="mode" class="form-control" required>
                            <option value="">Select Mode</option>
                            <option value="Cash">Cash</option>
                            <option value="Online">Online</option>
                            <option value="UPI">UPI</option>
                            <option value="Card">Card</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="update" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-check"></i> Record Payment
                </button>
            </form>
        </div>

        <h3 class="section-header"><i class="fas fa-list"></i> Unpaid Bills Summary</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Bill ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($unpaid_bills) > 0): ?>
                        <?php foreach ($unpaid_bills as $bill): ?>
                            <tr>
                                <td><?= htmlspecialchars($bill['Bill_Type']) ?></td>
                                <td>#<?= $bill['Bill_ID'] ?></td>
                                <td><?= htmlspecialchars($bill['Customer_Name']) ?></td>
                                <td>₹<?= number_format($bill['Bill_Amount'], 2) ?></td>
                                <td><?= htmlspecialchars($bill['Due_Date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <p>No unpaid bills found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
                btn.innerHTML = mode === 'dark' ? '<i class="fas fa-sun"></i><span>Light Mode</span>' : '<i class="fas fa-moon"></i><span>Dark Mode</span>';
            });
            window.addEventListener('scroll', () => {
                if (window.scrollY > 30) header.classList.add('shrink');
                else header.classList.remove('shrink');
            });
        });

        function updateBillInfo() {
            const select = document.getElementById('billSelect');
            const option = select.options[select.selectedIndex];
            const amount = option.getAttribute('data-amount');
            const type = option.getAttribute('data-type');
            const customer = option.getAttribute('data-customer');
            if (amount && type && customer) {
                document.getElementById('amountInput').value = amount;
                document.getElementById('amountInput').min = amount;
                document.getElementById('billTypeInput').value = type;
                document.getElementById('customerDisplay').value = 'Customer: ' + customer;
            } else {
                document.getElementById('amountInput').value = '';
                document.getElementById('amountInput').min = 0.01;
                document.getElementById('billTypeInput').value = '';
                document.getElementById('customerDisplay').value = '';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const rowsPerPage = 50;
            const table = document.querySelector('table');
            const rows = Array.from(table.querySelectorAll('tbody tr'));
            const paginationContainer = document.createElement('div');
            paginationContainer.className = 'pagination';
            table.parentNode.appendChild(paginationContainer);

            let currentPage = 1;
            const totalPages = Math.ceil(rows.length / rowsPerPage);

            function showPage(page) {
                rows.forEach((row, index) => {
                    row.style.display = (index >= (page - 1) * rowsPerPage && index < page * rowsPerPage) ? '' : 'none';
                });
            }

            function updatePagination() {
                paginationContainer.innerHTML = '';

                // Previous button
                const prevBtn = document.createElement('button');
                prevBtn.className = 'page-btn';
                prevBtn.innerHTML = '&laquo;';
                prevBtn.disabled = currentPage === 1;
                prevBtn.onclick = () => {
                    if (currentPage > 1) {
                        currentPage--;
                        showPage(currentPage);
                        updatePagination();
                    }
                };
                paginationContainer.appendChild(prevBtn);

                // Determine start and end range (3 pages visible)
                let startPage = Math.max(1, currentPage - 1);
                let endPage = Math.min(totalPages, startPage + 2);

                // Adjust if near end
                if (endPage - startPage < 2) {
                    startPage = Math.max(1, endPage - 2);
                }

                // Add page number buttons
                for (let i = startPage; i <= endPage; i++) {
                    const btn = document.createElement('button');
                    btn.className = 'page-btn' + (i === currentPage ? ' active' : '');
                    btn.textContent = i;
                    btn.onclick = () => {
                        currentPage = i;
                        showPage(currentPage);
                        updatePagination();
                    };
                    paginationContainer.appendChild(btn);
                }

                // Next button
                const nextBtn = document.createElement('button');
                nextBtn.className = 'page-btn';
                nextBtn.innerHTML = '&raquo;';
                nextBtn.disabled = currentPage === totalPages;
                nextBtn.onclick = () => {
                    if (currentPage < totalPages) {
                        currentPage++;
                        showPage(currentPage);
                        updatePagination();
                    }
                };
                paginationContainer.appendChild(nextBtn);
            }

            showPage(currentPage);
            updatePagination();
        });

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.createElement('input');
            searchInput.placeholder = 'Search Unpaid Bill...';
            searchInput.className = 'form-control mb-2';
            const selectBox = document.querySelector('select[name="bill_id"]');
            selectBox.parentNode.insertBefore(searchInput, selectBox);

            searchInput.addEventListener('keyup', function() {
                const filter = searchInput.value.toLowerCase();
                for (let option of selectBox.options) {
                    const text = option.text.toLowerCase();
                    option.style.display = text.includes(filter) ? '' : 'none';
                }
            });
        });
    </script>

</body>

</html>
