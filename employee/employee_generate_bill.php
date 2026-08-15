<?php
include('../includes/db_connect.php');
require_once('../includes/log_functions.php');
require_once('../includes/notification_engine.php');
require_once('../includes/tariff_engine.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employee') {
    redirect('index.php');
    exit;
}

// Handle AJAX Calculation Preview
if (isset($_GET['action']) && $_GET['action'] === 'calc_preview') {
    header('Content-Type: application/json');
    $bill_type = sanitize_input($_GET['type'] ?? 'Electric');
    $units = floatval($_GET['units'] ?? 0);
    $category_id = intval($_GET['category_id'] ?? 1);
    $due_date = sanitize_input($_GET['due'] ?? date('Y-m-d', strtotime('+15 days')));

    $calc = calculateBillWithSlabs($conn, $bill_type, $units, $category_id, $due_date);
    echo json_encode($calc);
    exit;
}

// Form Submission (Generate Bill)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $msg = "Invalid request token. Please try again.";
        $msg_type = "error";
    } else {
        $customer_id = intval($_POST['customer_id']);
        $units = floatval($_POST['consumed']);
        $due = sanitize_input($_POST['due']);
        $bill_type = sanitize_input($_POST['type']);
        $house_id = intval($_POST['house_id']);
        $tariff_category_id = intval($_POST['tariff_category_id'] ?? 1);

        if (!in_array($bill_type, ['Electric', 'Water'])) {
            $msg = "Invalid bill type.";
            $msg_type = "error";
        } elseif ($customer_id <= 0 || $units < 0 || $house_id <= 0) {
            $msg = "Please select a valid customer and enter positive consumption.";
            $msg_type = "error";
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            $msg = "Invalid due date format.";
            $msg_type = "error";
        } else {
            // Calculate progressive bill via Tariff Engine
            $calc = calculateBillWithSlabs($conn, $bill_type, $units, $tariff_category_id, $due);

            $base_amount = $calc['base_amount'];
            $fixed_charge = $calc['fixed_charge'];
            $tax_amount = $calc['tax_amount'];
            $total_amount = $calc['total_amount'];
            $rate_per_unit = $calc['effective_rate'];
            $grace_due_date = $calc['grace_due_date'];
            $breakdown_json = json_encode($calc);

            if ($bill_type === 'Electric') {
                $stmt = $conn->prepare("
                    INSERT INTO electric_bill 
                    (Customer_ID, House_ID, Tariff_Category_ID, Units_Consumed, Rate_per_unit, Base_Amount, Fixed_Charge, Tax_Amount, Late_Fee, Bill_Amount, Due_Date, Grace_Due_Date, Status, Slab_Breakdown_JSON) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, ?, ?, 'Unpaid', ?)
                ");
                $stmt->bind_param("iiiddddddsss", $customer_id, $house_id, $tariff_category_id, $units, $rate_per_unit, $base_amount, $fixed_charge, $tax_amount, $total_amount, $due, $grace_due_date, $breakdown_json);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO water_bill 
                    (Customer_ID, House_ID, Tariff_Category_ID, Consumption_Liters, Rate_per_liter, Base_Amount, Fixed_Charge, Tax_Amount, Late_Fee, Bill_Amount, Due_Date, Grace_Due_Date, Status, Slab_Breakdown_JSON) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, ?, ?, 'Unpaid', ?)
                ");
                $stmt->bind_param("iiiddddddsss", $customer_id, $house_id, $tariff_category_id, $units, $rate_per_unit, $base_amount, $fixed_charge, $tax_amount, $total_amount, $due, $grace_due_date, $breakdown_json);
            }

            if ($stmt && $stmt->execute()) {
                $newBillId = $stmt->insert_id;
                $stmt->close();

                logEmployeeAction($conn, $_SESSION['employee_id'], 'Generate Bill', "Generated $bill_type bill #$newBillId (₹$total_amount, $units units) for Customer ID $customer_id");

                // Trigger Automated Notification Engine (Email PDF statement + SMS/WhatsApp)
                $notifRes = notifyBillGenerated($conn, $newBillId, $bill_type);

                $msg = "$bill_type Bill #$newBillId Generated Successfully! (Total: ₹" . number_format($total_amount, 2) . ") Progressive itemized invoice emailed & SMS alert sent.";
                $msg_type = "success";
            } else {
                $msg = "Error generating bill: " . $conn->error;
                $msg_type = "error";
                if ($stmt) $stmt->close();
            }
        }
    }
}

// Fetch all customers with their house and tariff category details
$customers_stmt = $conn->prepare("
    SELECT c.Customer_ID, c.Name, c.Tariff_Category_ID, 
           h.House_ID, h.House_Number, 
           COALESCE(tc.category_name, 'Residential / Domestic') as category_name,
           COALESCE(tc.category_code, 'DOMESTIC') as category_code
    FROM customer c 
    LEFT JOIN house h ON c.House_ID = h.House_ID 
    LEFT JOIN tariff_categories tc ON c.Tariff_Category_ID = tc.category_id 
    ORDER BY c.Name ASC
");
$customers_stmt->execute();
$customers = $customers_stmt->get_result();

$csrf_token = generate_csrf_token();
$active_page = 'generate';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Bill - Public Utility System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .search-dropdown {
            position: relative;
        }

        .search-dropdown input {
            width: 100%;
        }

        .dropdown-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #667eea;
            border-top: none;
            border-radius: 0 0 10px 10px;
            max-height: 280px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        body.dark-mode .dropdown-list {
            background: #2b2b3c;
            border-color: #818cf8;
        }

        .dropdown-list.show {
            display: block;
        }

        .dropdown-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s ease;
        }

        body.dark-mode .dropdown-item {
            border-bottom-color: #3a3a4a;
            color: #e0e0e0;
        }

        .dropdown-item:hover {
            background: #f8f9ff;
        }

        body.dark-mode .dropdown-item:hover {
            background: #323244;
        }

        .dropdown-item strong {
            color: #667eea;
            display: block;
        }

        body.dark-mode .dropdown-item strong {
            color: #818cf8;
        }

        .dropdown-item small {
            color: #666;
            font-size: 12px;
        }

        body.dark-mode .dropdown-item small {
            color: #a0a0a0;
        }

        .no-results {
            padding: 15px;
            text-align: center;
            color: #999;
            font-style: italic;
        }

        .slab-preview-card {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            border: 1px solid #c7d2fe;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            display: none;
        }

        body.dark-mode .slab-preview-card {
            background: linear-gradient(135deg, #181826 0%, #1e1b4b 100%);
            border-color: #3730a3;
        }

        .tier-line {
            display: flex;
            justify-content: space-between;
            padding: 6px 12px;
            background: white;
            border-radius: 6px;
            margin-bottom: 6px;
            font-size: 13px;
            border-left: 3px solid #6366f1;
        }

        body.dark-mode .tier-line {
            background: #252538;
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include('../includes/sidebar_employee.php'); ?>

        <div class="main-content">
            <header class="dashboard-header" id="header">
                <div class="header-left">
                    <button class="sidebar-mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-title-block">
                        <h1><i class="fas fa-file-invoice-dollar"></i> Generate Utility Bill</h1>
                        <p>Calculate and dispatch progressive slab bills for electricity and water consumers</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button id="toggle-theme" class="btn-icon">
                        <i class="fas fa-moon"></i><span>Dark Mode</span>
                    </button>
                    <a href="dashboard_employee.php" class="btn-icon">
                        <i class="fas fa-arrow-left"></i><span>Dashboard</span>
                    </a>
                    <a href="../logout.php" class="btn-icon logout">
                        <i class="fas fa-right-from-bracket"></i><span>Logout</span>
                    </a>
                </div>
            </header>

            <div class="dashboard-content">
                <?= display_flash_msg($toast ?? $msg ?? null, $toast_type ?? $msg_type ?? "success") ?>

                <h2 class="section-header"><i class="fas fa-plus-circle"></i> New Bill Generation Form</h2>

                <div class="form-grid">
                    <!-- Bill Type -->
                    <div class="form-group">
                        <label><i class="fas fa-plug"></i> Bill Utility Type</label>
                        <select name="type" id="billTypeSelect" class="form-control" required onchange="triggerLiveCalc()">
                            <option value="">-- Select Utility Type --</option>
                            <option value="Electric">Electricity (kWh)</option>
                            <option value="Water">Water (Liters)</option>
                        </select>
                    </div>

                    <!-- Customer Search -->
                    <div class="form-group search-dropdown">
                        <label><i class="fas fa-user"></i> Select Customer</label>
                        <input
                            type="text"
                            id="customerSearch"
                            class="form-control"
                            placeholder="Type customer name or ID..."
                            autocomplete="off"
                            onfocus="showDropdown()">
                        <div id="customerDropdown" class="dropdown-list"></div>
                    </div>

                    <!-- House Info -->
                    <div class="form-group">
                        <label><i class="fas fa-home"></i> Property Allocation</label>
                        <input type="text" id="houseNumDisplay" class="form-control" placeholder="Auto-filled on customer selection" readonly>
                    </div>

                    <!-- Tariff Plan Info -->
                    <div class="form-group">
                        <label><i class="fas fa-layer-group"></i> Active Tariff Plan</label>
                        <input type="text" id="tariffPlanDisplay" class="form-control" placeholder="Residential / Domestic" readonly>
                    </div>

                    <!-- Consumed Units -->
                    <div class="form-group">
                        <label id="consumptionLabel"><i class="fas fa-gauge"></i> Units Consumed</label>
                        <input type="number" name="consumed" id="consumedInput" class="form-control" placeholder="0.00" step="0.01" min="0.01" required oninput="triggerLiveCalc()">
                    </div>

                    <!-- Due Date -->
                    <div class="form-group">
                        <label><i class="fas fa-calendar-day"></i> Payment Due Date</label>
                        <input type="date" name="due" id="dueDateInput" class="form-control" value="<?= date('Y-m-d', strtotime('+15 days')) ?>" required min="<?= date('Y-m-d') ?>" onchange="triggerLiveCalc()">
                    </div>
                </div>

                <!-- Live Dynamic Slab Calculation Preview Box -->
                <div class="slab-preview-card" id="slabPreviewBox">
                    <h3 style="margin-top: 0; color: #4338ca; display: flex; justify-content: space-between; align-items: center;">
                        <span><i class="fas fa-receipt"></i> Progressive Slab Cost Breakdown</span>
                        <span id="previewEffectiveRate" class="badge badge-info"></span>
                    </h3>

                    <div id="previewTiersList" style="margin: 12px 0;"></div>

                    <div style="background: white; border-radius: 8px; padding: 15px; border: 1px solid #c7d2fe; display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-top: 10px;">
                        <div>
                            <small style="color: #64748b; display: block;">Energy / Base</small>
                            <strong id="previewBase" style="font-size: 16px; color: #1e293b;">₹0.00</strong>
                        </div>
                        <div>
                            <small style="color: #64748b; display: block;">Fixed Meter Fee</small>
                            <strong id="previewFixed" style="font-size: 16px; color: #1e293b;">₹0.00</strong>
                        </div>
                        <div>
                            <small style="color: #64748b; display: block;">Duty / Tax (<span id="previewTaxRate">5</span>%)</small>
                            <strong id="previewTax" style="font-size: 16px; color: #1e293b;">₹0.00</strong>
                        </div>
                        <div style="background: #e0e7ff; padding: 6px 10px; border-radius: 6px;">
                            <small style="color: #4338ca; font-weight: bold; display: block;">Total Bill Amount</small>
                            <strong id="previewTotal" style="font-size: 20px; color: #3730a3;">₹0.00</strong>
                        </div>
                    </div>
                    <small style="color: #64748b; display: block; margin-top: 8px;">
                        <i class="fas fa-info-circle"></i> Grace period: 3 days after due date (<span id="previewGraceDate"></span>). Late fee penalty will apply if unpaid past grace date.
                    </small>
                </div>

                <button type="submit" name="generate" class="btn btn-primary" style="margin-top: 25px; padding: 12px 25px; font-size: 16px;">
                    <i class="fas fa-paper-plane"></i> Generate & Dispatch Bill Statement
                </button>
            </form>
        </div>
    </div>

    <script>
        // Customer list from PHP
        const customers = <?php
                            $customers->data_seek(0);
                            $customerData = [];
                            while ($cust = $customers->fetch_assoc()) {
                                $customerData[] = [
                                    'id' => (int)$cust['Customer_ID'],
                                    'name' => $cust['Name'],
                                    'house_id' => (int)$cust['House_ID'],
                                    'house_number' => $cust['House_Number'],
                                    'category_id' => (int)($cust['Tariff_Category_ID'] ?? 1),
                                    'category_name' => $cust['category_name'] ?? 'Residential / Domestic'
                                ];
                            }
                            echo json_encode($customerData);
                            ?>;

        let filteredCustomers = [...customers];

        document.getElementById('customerSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            if (searchTerm === '') {
                filteredCustomers = [...customers];
            } else {
                filteredCustomers = customers.filter(customer =>
                    customer.name.toLowerCase().includes(searchTerm) ||
                    customer.id.toString().includes(searchTerm)
                );
            }
            updateDropdown();
        });

        function showDropdown() {
            updateDropdown();
            document.getElementById('customerDropdown').classList.add('show');
        }

        function updateDropdown() {
            const dropdown = document.getElementById('customerDropdown');
            if (filteredCustomers.length === 0) {
                dropdown.innerHTML = '<div class="no-results">No customers found</div>';
            } else {
                dropdown.innerHTML = filteredCustomers.map(customer => `
                    <div class="dropdown-item" onclick="selectCustomer(${customer.id})">
                        <strong>${escapeHtml(customer.name)}</strong>
                        <small>ID: #${customer.id} | House: ${escapeHtml(customer.house_number || 'N/A')} | Plan: ${escapeHtml(customer.category_name)}</small>
                    </div>
                `).join('');
            }
            dropdown.classList.add('show');
        }

        function selectCustomer(customerId) {
            const customer = customers.find(c => c.id === customerId);
            if (customer) {
                document.getElementById('customerSearch').value = customer.name;
                document.getElementById('customerIdInput').value = customer.id;
                document.getElementById('houseIdInput').value = customer.house_id || '';
                document.getElementById('tariffCatIdInput').value = customer.category_id || 1;
                document.getElementById('houseNumDisplay').value = customer.house_number ?
                    `House #${customer.house_number} (ID: ${customer.house_id})` : 'No house assigned';
                document.getElementById('tariffPlanDisplay').value = customer.category_name;
                document.getElementById('customerDropdown').classList.remove('show');
                triggerLiveCalc();
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-dropdown')) {
                document.getElementById('customerDropdown').classList.remove('show');
            }
        });

        // Trigger Live Progressive Slab Calculation via AJAX
        async function triggerLiveCalc() {
            const billType = document.getElementById('billTypeSelect').value;
            const units = parseFloat(document.getElementById('consumedInput').value) || 0;
            const categoryId = document.getElementById('tariffCatIdInput').value || 1;
            const dueDate = document.getElementById('dueDateInput').value;
            const previewBox = document.getElementById('slabPreviewBox');
            const consumptionLabel = document.getElementById('consumptionLabel');

            if (billType === 'Electric') {
                consumptionLabel.innerHTML = '<i class="fas fa-bolt"></i> Units Consumed (kWh)';
            } else if (billType === 'Water') {
                consumptionLabel.innerHTML = '<i class="fas fa-droplet"></i> Volume Consumed (Liters)';
            }

            if (!billType || units <= 0) {
                previewBox.style.display = 'none';
                return;
            }

            try {
                const res = await fetch(`employee_generate_bill.php?action=calc_preview&type=${billType}&units=${units}&category_id=${categoryId}&due=${dueDate}`);
                const data = await res.json();

                previewBox.style.display = 'block';
                document.getElementById('previewEffectiveRate').innerText = `Avg Rate: ₹${data.effective_rate}/${billType === 'Electric' ? 'kWh' : 'L'}`;
                document.getElementById('previewBase').innerText = `₹${parseFloat(data.base_amount).toFixed(2)}`;
                document.getElementById('previewFixed').innerText = `₹${parseFloat(data.fixed_charge).toFixed(2)}`;
                document.getElementById('previewTaxRate').innerText = data.tax_percent;
                document.getElementById('previewTax').innerText = `₹${parseFloat(data.tax_amount).toFixed(2)}`;
                document.getElementById('previewTotal').innerText = `₹${parseFloat(data.total_amount).toFixed(2)}`;
                document.getElementById('previewGraceDate').innerText = data.grace_due_date;

                let tiersHtml = '';
                if (data.slabs && data.slabs.length > 0) {
                    data.slabs.forEach(slab => {
                        tiersHtml += `
                            <div class="tier-line">
                                <div><strong>${escapeHtml(slab.slab_name)}</strong> &bull; ${slab.units_in_slab} ${billType === 'Electric' ? 'kWh' : 'L'} @ ₹${parseFloat(slab.rate_per_unit).toFixed(2)}</div>
                                <div><strong>₹${parseFloat(slab.subtotal).toFixed(2)}</strong></div>
                            </div>
                        `;
                    });
                }
                document.getElementById('previewTiersList').innerHTML = tiersHtml;
            } catch (err) {
                console.error(err);
            }
        }
    </script>
            </div> <!-- close .dashboard-content -->
        </div> <!-- close .main-content -->
    </div> <!-- close .dashboard-layout -->

    <?php include('../includes/footer.php'); ?>
