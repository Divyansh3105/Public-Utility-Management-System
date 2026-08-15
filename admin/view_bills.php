<?php
include('../includes/db_connect.php');
require_once('../includes/tariff_engine.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    redirect('index.php');
    exit;
}

$active_page = 'bills';

// Get pagination parameters
$pagination = get_pagination_params(50);
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$tab = isset($_GET['tab']) && in_array($_GET['tab'], ['electric', 'water']) ? $_GET['tab'] : 'electric';

// Build WHERE clause for electric
$where_conditions = [];
$params = [];
$types = '';

if ($status_filter && in_array($status_filter, ['Paid', 'Unpaid'])) {
    $where_conditions[] = "eb.Status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search) {
    $where_conditions[] = "(c.Name LIKE ? OR eb.Bill_ID LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Count total electric bills
$count_query = "SELECT COUNT(*) as total FROM electric_bill eb
                LEFT JOIN customer c ON eb.Customer_ID = c.Customer_ID
                $where_clause";
$count_stmt = $conn->prepare($count_query);
if ($types) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$electric_total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$electric_total_pages = calculate_total_pages($electric_total_records, $limit);
$count_stmt->close();

// Fetch paginated electric bills with tariff category
$electric_query = "
    SELECT eb.*, c.Name as Customer_Name, COALESCE(tc.category_name, 'Domestic Plan') as category_name
    FROM electric_bill eb
    LEFT JOIN customer c ON eb.Customer_ID = c.Customer_ID
    LEFT JOIN tariff_categories tc ON eb.Tariff_Category_ID = tc.category_id
    $where_clause
    ORDER BY eb.Bill_ID DESC
    LIMIT ? OFFSET ?
";

$electric_stmt = $conn->prepare($electric_query);
$params_with_limit = $params;
$params_with_limit[] = $limit;
$params_with_limit[] = $offset;
$types_with_limit = $types . 'ii';

if ($types_with_limit) {
    $electric_stmt->bind_param($types_with_limit, ...$params_with_limit);
}
$electric_stmt->execute();
$electric = $electric_stmt->get_result();

// Reset for water bills
$where_conditions_w = [];
$params_w = [];
$types_w = '';

if ($status_filter && in_array($status_filter, ['Paid', 'Unpaid'])) {
    $where_conditions_w[] = "wb.Status = ?";
    $params_w[] = $status_filter;
    $types_w .= 's';
}

if ($search) {
    $where_conditions_w[] = "(c.Name LIKE ? OR wb.Bill_ID LIKE ?)";
    $search_param = "%$search%";
    $params_w[] = $search_param;
    $params_w[] = $search_param;
    $types_w .= 'ss';
}

$where_clause_w = $where_conditions_w ? 'WHERE ' . implode(' AND ', $where_conditions_w) : '';

// Count total water bills
$count_query_w = "SELECT COUNT(*) as total FROM water_bill wb
                  LEFT JOIN customer c ON wb.Customer_ID = c.Customer_ID
                  $where_clause_w";
$count_stmt_w = $conn->prepare($count_query_w);
if ($types_w) {
    $count_stmt_w->bind_param($types_w, ...$params_w);
}
$count_stmt_w->execute();
$water_total_records = $count_stmt_w->get_result()->fetch_assoc()['total'];
$water_total_pages = calculate_total_pages($water_total_records, $limit);
$count_stmt_w->close();

// Fetch paginated water bills with tariff category
$water_query = "
    SELECT wb.*, c.Name as Customer_Name, COALESCE(tc.category_name, 'Domestic Plan') as category_name
    FROM water_bill wb
    LEFT JOIN customer c ON wb.Customer_ID = c.Customer_ID
    LEFT JOIN tariff_categories tc ON wb.Tariff_Category_ID = tc.category_id
    $where_clause_w
    ORDER BY wb.Bill_ID DESC
    LIMIT ? OFFSET ?
";

$water_stmt = $conn->prepare($water_query);
$params_with_limit_w = $params_w;
$params_with_limit_w[] = $limit;
$params_with_limit_w[] = $offset;
$types_with_limit_w = $types_w . 'ii';

if ($types_with_limit_w) {
    $water_stmt->bind_param($types_with_limit_w, ...$params_with_limit_w);
}
$water_stmt->execute();
$water = $water_stmt->get_result();

// Calculate summaries
$electric_summary = $conn->query("
    SELECT
        COALESCE(SUM(Bill_Amount), 0) as total,
        COALESCE(SUM(CASE WHEN Status='Paid' THEN Bill_Amount ELSE 0 END), 0) as paid,
        COALESCE(SUM(CASE WHEN Status='Unpaid' THEN Bill_Amount ELSE 0 END), 0) as unpaid,
        COUNT(CASE WHEN Status='Paid' THEN 1 END) as count_paid,
        COUNT(CASE WHEN Status='Unpaid' THEN 1 END) as count_unpaid
    FROM electric_bill
")->fetch_assoc();

$water_summary = $conn->query("
    SELECT
        COALESCE(SUM(Bill_Amount), 0) as total,
        COALESCE(SUM(CASE WHEN Status='Paid' THEN Bill_Amount ELSE 0 END), 0) as paid,
        COALESCE(SUM(CASE WHEN Status='Unpaid' THEN Bill_Amount ELSE 0 END), 0) as unpaid,
        COUNT(CASE WHEN Status='Paid' THEN 1 END) as count_paid,
        COUNT(CASE WHEN Status='Unpaid' THEN 1 END) as count_unpaid
    FROM water_bill
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Management - Public Utility System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .tabs-header {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .tab-btn.active {
            color: #ffffff;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

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
        <?php include('../includes/sidebar_admin.php'); ?>

        <div class="main-content">
            <header class="dashboard-header" id="header">
                <div class="header-left">
                    <button class="sidebar-mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-title-block">
                        <h1><i class="fas fa-file-invoice"></i> Utility Invoices & Statements</h1>
                        <p>Track progressive tiered consumption, late fee surcharges, and customer billing records</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button id="toggle-theme" class="btn-icon">
                        <i class="fas fa-moon"></i><span>Dark Mode</span>
                    </button>
                    <a href="dashboard_admin.php" class="btn-icon">
                        <i class="fas fa-arrow-left"></i><span>Dashboard</span>
                    </a>
                </div>
            </header>

            <div class="dashboard-content">
                <!-- Tab Controls -->
                <div class="tabs-header">
                    <a href="view_bills.php?tab=electric" class="tab-btn <?= $tab === 'electric' ? 'active' : '' ?>">
                        <i class="fas fa-bolt"></i> Electricity Invoices (<?= $electric_summary['count_unpaid'] + $electric_summary['count_paid'] ?>)
                    </a>
                    <a href="view_bills.php?tab=water" class="tab-btn <?= $tab === 'water' ? 'active' : '' ?>">
                        <i class="fas fa-droplet"></i> Water Invoices (<?= $water_summary['count_unpaid'] + $water_summary['count_paid'] ?>)
                    </a>
                </div>

                <!-- Filter Bar -->
                <div style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.06);">
                    <form method="GET">
                        <input type="hidden" name="tab" value="<?= e($tab) ?>">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) auto; gap: 15px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Search by Customer or Bill ID</label>
                                <input type="text" name="search" class="form-control" placeholder="Type name or #ID..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Status Filter</label>
                                <select name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <option value="Paid" <?= $status_filter === 'Paid' ? 'selected' : '' ?>>Paid</option>
                                    <option value="Unpaid" <?= $status_filter === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" style="height: 42px;">
                                <i class="fas fa-filter"></i> Apply Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ELECTRIC TAB -->
                <?php if ($tab === 'electric'): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3><i class="fas fa-rupee-sign"></i> Total Billed Revenue</h3>
                            <div class="stat-value">₹<?= number_format($electric_summary['total'], 2) ?></div>
                        </div>
                        <div class="stat-card success">
                            <h3><i class="fas fa-check-circle"></i> Collected (<?= $electric_summary['count_paid'] ?>)</h3>
                            <div class="stat-value">₹<?= number_format($electric_summary['paid'], 2) ?></div>
                        </div>
                        <div class="stat-card danger">
                            <h3><i class="fas fa-exclamation-circle"></i> Uncollected (<?= $electric_summary['count_unpaid'] ?>)</h3>
                            <div class="stat-value">₹<?= number_format($electric_summary['unpaid'], 2) ?></div>
                        </div>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Bill ID</th>
                                    <th>Customer</th>
                                    <th>Tariff Category</th>
                                    <th>Consumption</th>
                                    <th>Base Charge</th>
                                    <th>Late Fee</th>
                                    <th>Total Bill</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($electric->num_rows > 0): ?>
                                    <?php while ($row = $electric->fetch_assoc()): 
                                        $hasLate = ((float)($row['Late_Fee'] ?? 0) > 0);
                                    ?>
                                        <tr>
                                            <td><strong>#<?= htmlspecialchars($row['Bill_ID']) ?></strong></td>
                                            <td>
                                                <strong><?= htmlspecialchars($row['Customer_Name'] ?? 'N/A') ?></strong>
                                                <br><small style="color: #64748b;">Cust #<?= $row['Customer_ID'] ?></small>
                                            </td>
                                            <td><span class="badge badge-primary"><?= htmlspecialchars($row['category_name']) ?></span></td>
                                            <td><?= number_format($row['Units_Consumed'], 2) ?> kWh</td>
                                            <td>₹<?= number_format($row['Base_Amount'] ?: $row['Bill_Amount'], 2) ?></td>
                                            <td>
                                                <?php if ($hasLate): ?>
                                                    <span class="badge badge-danger">+₹<?= number_format($row['Late_Fee'], 2) ?></span>
                                                <?php else: ?>
                                                    <span style="color: #94a3b8;">₹0.00</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong style="color: #10b981; font-size: 15px;">₹<?= number_format($row['Bill_Amount'], 2) ?></strong></td>
                                            <td><?= date('d M Y', strtotime($row['Due_Date'])) ?></td>
                                            <td>
                                                <span class="badge status-<?= strtolower($row['Status']) ?>">
                                                    <?= htmlspecialchars($row['Status']) ?>
                                                </span>
                                            </td>
                                            <td style="white-space: nowrap;">
                                                <button class="btn btn-sm btn-secondary" onclick='viewBreakdown(<?= json_encode($row) ?>, "Electric")' title="View Slabs">
                                                    <i class="fas fa-layer-group"></i> Slabs
                                                </button>
                                                <a href="../download_pdf.php?type=bill&id=<?= $row['Bill_ID'] ?>&bill_type=Electric" class="btn btn-sm btn-secondary" title="Download PDF">
                                                    <i class="fas fa-file-pdf" style="color: #ef4444;"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="10" style="text-align: center; color: #94a3b8; padding: 25px;">No electricity bills matching criteria.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- WATER TAB -->
                <?php if ($tab === 'water'): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3><i class="fas fa-rupee-sign"></i> Total Billed Revenue</h3>
                            <div class="stat-value">₹<?= number_format($water_summary['total'], 2) ?></div>
                        </div>
                        <div class="stat-card success">
                            <h3><i class="fas fa-check-circle"></i> Collected (<?= $water_summary['count_paid'] ?>)</h3>
                            <div class="stat-value">₹<?= number_format($water_summary['paid'], 2) ?></div>
                        </div>
                        <div class="stat-card danger">
                            <h3><i class="fas fa-exclamation-circle"></i> Uncollected (<?= $water_summary['count_unpaid'] ?>)</h3>
                            <div class="stat-value">₹<?= number_format($water_summary['unpaid'], 2) ?></div>
                        </div>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Bill ID</th>
                                    <th>Customer</th>
                                    <th>Tariff Category</th>
                                    <th>Consumption</th>
                                    <th>Base Charge</th>
                                    <th>Late Fee</th>
                                    <th>Total Bill</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($water->num_rows > 0): ?>
                                    <?php while ($row = $water->fetch_assoc()): 
                                        $hasLate = ((float)($row['Late_Fee'] ?? 0) > 0);
                                    ?>
                                        <tr>
                                            <td><strong>#<?= htmlspecialchars($row['Bill_ID']) ?></strong></td>
                                            <td>
                                                <strong><?= htmlspecialchars($row['Customer_Name'] ?? 'N/A') ?></strong>
                                                <br><small style="color: #64748b;">Cust #<?= $row['Customer_ID'] ?></small>
                                            </td>
                                            <td><span class="badge badge-primary"><?= htmlspecialchars($row['category_name']) ?></span></td>
                                            <td><?= number_format($row['Consumption_Liters'], 2) ?> L</td>
                                            <td>₹<?= number_format($row['Base_Amount'] ?: $row['Bill_Amount'], 2) ?></td>
                                            <td>
                                                <?php if ($hasLate): ?>
                                                    <span class="badge badge-danger">+₹<?= number_format($row['Late_Fee'], 2) ?></span>
                                                <?php else: ?>
                                                    <span style="color: #94a3b8;">₹0.00</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong style="color: #10b981; font-size: 15px;">₹<?= number_format($row['Bill_Amount'], 2) ?></strong></td>
                                            <td><?= date('d M Y', strtotime($row['Due_Date'])) ?></td>
                                            <td>
                                                <span class="badge status-<?= strtolower($row['Status']) ?>">
                                                    <?= htmlspecialchars($row['Status']) ?>
                                                </span>
                                            </td>
                                            <td style="white-space: nowrap;">
                                                <button class="btn btn-sm btn-secondary" onclick='viewBreakdown(<?= json_encode($row) ?>, "Water")' title="View Slabs">
                                                    <i class="fas fa-layer-group"></i> Slabs
                                                </button>
                                                <a href="../download_pdf.php?type=bill&id=<?= $row['Bill_ID'] ?>&bill_type=Water" class="btn btn-sm btn-secondary" title="Download PDF">
                                                    <i class="fas fa-file-pdf" style="color: #ef4444;"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="10" style="text-align: center; color: #94a3b8; padding: 25px;">No water bills matching criteria.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
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
                    <strong>Total Amount:</strong>
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
    </script>
</body>

</html>
<?php
$electric_stmt->close();
$water_stmt->close();
?>
