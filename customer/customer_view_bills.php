<?php
include('../includes/db_connect.php');
require_once('../includes/tariff_engine.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    redirect('index.php');
    exit;
}

$name = $_SESSION['name'];
$customer_id = $_SESSION['customer_id'] ?? null;

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

// Fetch electric bills with tariff category
$electric_stmt = $conn->prepare("
    SELECT eb.*, COALESCE(tc.category_name, 'Domestic Plan') as category_name 
    FROM electric_bill eb 
    LEFT JOIN tariff_categories tc ON eb.Tariff_Category_ID = tc.category_id 
    WHERE eb.Customer_ID=? 
    ORDER BY eb.Bill_ID DESC
");
$electric_stmt->bind_param("i", $customer_id);
$electric_stmt->execute();
$electric = $electric_stmt->get_result();

// Fetch water bills with tariff category
$water_stmt = $conn->prepare("
    SELECT wb.*, COALESCE(tc.category_name, 'Domestic Plan') as category_name 
    FROM water_bill wb 
    LEFT JOIN tariff_categories tc ON wb.Tariff_Category_ID = tc.category_id 
    WHERE wb.Customer_ID=? 
    ORDER BY wb.Bill_ID DESC
");
$water_stmt->bind_param("i", $customer_id);
$water_stmt->execute();
$water = $water_stmt->get_result();

// Calculate summaries
$electric_total = 0;
$electric_paid = 0;
$electric_unpaid = 0;
$electric->data_seek(0);
while ($row = $electric->fetch_assoc()) {
    $electric_total += $row['Bill_Amount'];
    if ($row['Status'] == 'Paid') $electric_paid += $row['Bill_Amount'];
    else $electric_unpaid += $row['Bill_Amount'];
}
$electric->data_seek(0);

$water_total = 0;
$water_paid = 0;
$water_unpaid = 0;
$water->data_seek(0);
while ($row = $water->fetch_assoc()) {
    $water_total += $row['Bill_Amount'];
    if ($row['Status'] == 'Paid') $water_paid += $row['Bill_Amount'];
    else $water_unpaid += $row['Bill_Amount'];
}
$water->data_seek(0);
$active_page = 'bills';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bills - Public Utility System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .tier-modal-line {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            background: #f8fafc;
            border-radius: 6px;
            margin-bottom: 6px;
            font-size: 13px;
            border-left: 3px solid #6366f1;
        }

        body.dark-mode .tier-modal-line {
            background: #1e1e2d;
            border-left-color: #818cf8;
        }
    </style>
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
                        <h1><i class="fas fa-file-invoice"></i> Your Utility Invoices</h1>
                        <p>Review progressive tiered charges, meter consumption, and payment receipts</p>
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
        <!-- Electricity Section -->
        <h2 class="section-header"><i class="fas fa-bolt"></i> Electricity Invoices</h2>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-rupee-sign"></i> Total Billed</h3>
                <div class="stat-value">₹<?= number_format($electric_total, 2) ?></div>
            </div>
            <div class="stat-card success">
                <h3><i class="fas fa-check-circle"></i> Paid Total</h3>
                <div class="stat-value">₹<?= number_format($electric_paid, 2) ?></div>
            </div>
            <div class="stat-card danger">
                <h3><i class="fas fa-exclamation-circle"></i> Outstanding Due</h3>
                <div class="stat-value">₹<?= number_format($electric_unpaid, 2) ?></div>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Bill ID</th>
                        <th>Tariff Plan</th>
                        <th>Consumption</th>
                        <th>Base Amount</th>
                        <th>Late Fee</th>
                        <th>Total Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th style="text-align: right;">Breakdown & PDF</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($electric->num_rows > 0): ?>
                        <?php while ($row = $electric->fetch_assoc()): 
                            $isOverdue = ($row['Status'] === 'Unpaid' && strtotime(date('Y-m-d')) > strtotime($row['Due_Date']));
                            $hasLateFee = ((float)($row['Late_Fee'] ?? 0) > 0);
                        ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($row['Bill_ID']) ?></strong></td>
                                <td><span class="badge badge-primary"><?= htmlspecialchars($row['category_name']) ?></span></td>
                                <td><?= number_format($row['Units_Consumed'], 2) ?> kWh</td>
                                <td>₹<?= number_format($row['Base_Amount'] ?: $row['Bill_Amount'], 2) ?></td>
                                <td>
                                    <?php if ($hasLateFee): ?>
                                        <span class="badge badge-danger">+₹<?= number_format($row['Late_Fee'], 2) ?></span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">₹0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong style="color: #10b981; font-size: 15px;">₹<?= number_format($row['Bill_Amount'], 2) ?></strong></td>
                                <td>
                                    <?= date('d M Y', strtotime($row['Due_Date'])) ?>
                                    <?php if ($isOverdue && $hasLateFee): ?>
                                        <br><small style="color: #ef4444; font-weight: bold;"><i class="fas fa-triangle-exclamation"></i> Overdue</small>
                                    <?php elseif ($isOverdue): ?>
                                        <br><small style="color: #f59e0b; font-weight: bold;"><i class="fas fa-clock"></i> In Grace Period</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge status-<?= strtolower($row['Status']) ?>">
                                        <?= htmlspecialchars($row['Status']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button class="btn btn-sm btn-secondary" onclick='viewBreakdown(<?= json_encode($row) ?>, "Electric")' style="padding: 4px 8px; font-size: 12px;" title="View Progressive Slab Details">
                                        <i class="fas fa-layer-group"></i> Slabs
                                    </button>
                                    <a href="../download_pdf.php?type=bill&id=<?= $row['Bill_ID'] ?>&bill_type=Electric" class="btn btn-secondary" style="padding: 4px 8px; font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" title="Download PDF Statement">
                                        <i class="fas fa-file-pdf" style="color:#ef4444;"></i> PDF
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No electricity bills found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Water Section -->
        <h2 class="section-header" style="margin-top: 35px;"><i class="fas fa-droplet"></i> Water Invoices</h2>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-rupee-sign"></i> Total Billed</h3>
                <div class="stat-value">₹<?= number_format($water_total, 2) ?></div>
            </div>
            <div class="stat-card success">
                <h3><i class="fas fa-check-circle"></i> Paid Total</h3>
                <div class="stat-value">₹<?= number_format($water_paid, 2) ?></div>
            </div>
            <div class="stat-card danger">
                <h3><i class="fas fa-exclamation-circle"></i> Outstanding Due</h3>
                <div class="stat-value">₹<?= number_format($water_unpaid, 2) ?></div>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Bill ID</th>
                        <th>Tariff Plan</th>
                        <th>Consumption</th>
                        <th>Base Amount</th>
                        <th>Late Fee</th>
                        <th>Total Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th style="text-align: right;">Breakdown & PDF</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($water->num_rows > 0): ?>
                        <?php while ($row = $water->fetch_assoc()): 
                            $isOverdue = ($row['Status'] === 'Unpaid' && strtotime(date('Y-m-d')) > strtotime($row['Due_Date']));
                            $hasLateFee = ((float)($row['Late_Fee'] ?? 0) > 0);
                        ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($row['Bill_ID']) ?></strong></td>
                                <td><span class="badge badge-primary"><?= htmlspecialchars($row['category_name']) ?></span></td>
                                <td><?= number_format($row['Consumption_Liters'], 2) ?> L</td>
                                <td>₹<?= number_format($row['Base_Amount'] ?: $row['Bill_Amount'], 2) ?></td>
                                <td>
                                    <?php if ($hasLateFee): ?>
                                        <span class="badge badge-danger">+₹<?= number_format($row['Late_Fee'], 2) ?></span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">₹0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong style="color: #10b981; font-size: 15px;">₹<?= number_format($row['Bill_Amount'], 2) ?></strong></td>
                                <td>
                                    <?= date('d M Y', strtotime($row['Due_Date'])) ?>
                                    <?php if ($isOverdue && $hasLateFee): ?>
                                        <br><small style="color: #ef4444; font-weight: bold;"><i class="fas fa-triangle-exclamation"></i> Overdue</small>
                                    <?php elseif ($isOverdue): ?>
                                        <br><small style="color: #f59e0b; font-weight: bold;"><i class="fas fa-clock"></i> In Grace Period</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge status-<?= strtolower($row['Status']) ?>">
                                        <?= htmlspecialchars($row['Status']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button class="btn btn-sm btn-secondary" onclick='viewBreakdown(<?= json_encode($row) ?>, "Water")' style="padding: 4px 8px; font-size: 12px;" title="View Progressive Slab Details">
                                        <i class="fas fa-layer-group"></i> Slabs
                                    </button>
                                    <a href="../download_pdf.php?type=bill&id=<?= $row['Bill_ID'] ?>&bill_type=Water" class="btn btn-secondary" style="padding: 4px 8px; font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" title="Download PDF Statement">
                                        <i class="fas fa-file-pdf" style="color:#ef4444;"></i> PDF
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No water bills found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- BREAKDOWN MODAL -->
    <div id="breakdownModal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; width: 90%; max-width: 520px; border-radius: 12px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 id="modalBillTitle" style="margin: 0;"><i class="fas fa-receipt"></i> Invoice Breakdown</h3>
                <button type="button" onclick="closeBreakdownModal()" style="border: none; background: none; font-size: 22px; cursor: pointer;">&times;</button>
            </div>
            
            <div id="modalPlanBadge" style="margin-bottom: 15px;"></div>
            <div id="modalTiersContainer"></div>

            <div style="background: #f1f5f9; border-radius: 8px; padding: 15px; margin-top: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span>Base Energy / Water Charge:</span>
                    <strong id="modalBaseCost">₹0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span>Fixed Meter Charge:</span>
                    <strong id="modalFixedCost">₹0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span>Tax / Duty Amount:</span>
                    <strong id="modalTaxCost">₹0.00</strong>
                </div>
                <div id="modalLateFeeRow" style="display: none; justify-content: space-between; margin-bottom: 6px; color: #dc2626;">
                    <span>Late Fee Penalty Surcharge:</span>
                    <strong id="modalLateFeeCost">+₹0.00</strong>
                </div>
                <hr style="margin: 10px 0; border: 0; border-top: 1px solid #cbd5e1;">
                <div style="display: flex; justify-content: space-between; font-size: 16px;">
                    <strong>Total Amount Payable:</strong>
                    <strong id="modalTotalCost" style="color: #4f46e5; font-size: 18px;">₹0.00</strong>
                </div>
            </div>

            <div style="margin-top: 20px; text-align: right;">
                <button type="button" onclick="closeBreakdownModal()" class="btn btn-secondary">Close</button>
            </div>
        </div>
    </div>

    <script>
        function viewBreakdown(bill, utilityType) {
            document.getElementById('modalBillTitle').innerHTML = `<i class="fas ${utilityType === 'Electric' ? 'fa-bolt' : 'fa-droplet'}"></i> ${utilityType} Bill #${bill.Bill_ID}`;
            document.getElementById('modalPlanBadge').innerHTML = `<span class="badge badge-primary"><i class="fas fa-layer-group"></i> ${bill.category_name || 'Tariff Plan'}</span>`;

            let tiersHtml = '';
            let parsed = null;
            if (bill.Slab_Breakdown_JSON) {
                try {
                    parsed = JSON.parse(bill.Slab_Breakdown_JSON);
                } catch (e) {
                    console.error(e);
                }
            }

            if (parsed && parsed.slabs && parsed.slabs.length > 0) {
                parsed.slabs.forEach(slab => {
                    tiersHtml += `
                        <div class="tier-modal-line">
                            <div><strong>${slab.slab_name}</strong> (${slab.units_in_slab} ${utilityType === 'Electric' ? 'kWh' : 'L'} @ ₹${parseFloat(slab.rate_per_unit).toFixed(2)})</div>
                            <div><strong>₹${parseFloat(slab.subtotal).toFixed(2)}</strong></div>
                        </div>
                    `;
                });
            } else {
                const units = bill.Units_Consumed || bill.Consumption_Liters || 0;
                const rate = bill.Rate_per_unit || bill.Rate_per_liter || 0;
                tiersHtml = `
                    <div class="tier-modal-line">
                        <div><strong>Standard Consumption</strong> (${units} @ ₹${rate})</div>
                        <div><strong>₹${parseFloat(bill.Base_Amount || bill.Bill_Amount).toFixed(2)}</strong></div>
                    </div>
                `;
            }
            document.getElementById('modalTiersContainer').innerHTML = tiersHtml;

            const base = bill.Base_Amount ? parseFloat(bill.Base_Amount) : (parsed ? parseFloat(parsed.base_amount) : parseFloat(bill.Bill_Amount));
            const fixed = bill.Fixed_Charge ? parseFloat(bill.Fixed_Charge) : (parsed ? parseFloat(parsed.fixed_charge) : 0);
            const tax = bill.Tax_Amount ? parseFloat(bill.Tax_Amount) : (parsed ? parseFloat(parsed.tax_amount) : 0);
            const lateFee = parseFloat(bill.Late_Fee || 0);
            const total = parseFloat(bill.Bill_Amount);

            document.getElementById('modalBaseCost').innerText = `₹${base.toFixed(2)}`;
            document.getElementById('modalFixedCost').innerText = `₹${fixed.toFixed(2)}`;
            document.getElementById('modalTaxCost').innerText = `₹${tax.toFixed(2)}`;

            if (lateFee > 0) {
                document.getElementById('modalLateFeeRow').style.display = 'flex';
                document.getElementById('modalLateFeeCost').innerText = `+₹${lateFee.toFixed(2)}`;
            } else {
                document.getElementById('modalLateFeeRow').style.display = 'none';
            }

            document.getElementById('modalTotalCost').innerText = `₹${total.toFixed(2)}`;
            document.getElementById('breakdownModal').style.display = 'flex';
        }

        function closeBreakdownModal() {
            document.getElementById('breakdownModal').style.display = 'none';
        }

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
    </script>
        </div> <!-- close .main-content -->
    </div> <!-- close .dashboard-layout -->

    <?php include('../includes/footer.php'); ?>
<?php
$electric_stmt->close();
$water_stmt->close();
?>
