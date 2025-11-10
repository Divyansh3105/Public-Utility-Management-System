<?php
session_start();
include('../includes/db_connect.php');
require_once('../includes/log_functions.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employee') {
    header("Location: index.php");
    exit;
}

// FIX: Verify CSRF token on form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $msg = "Invalid request. Please try again.";
        $msg_type = "error";
    } else {
        $customer_id = intval($_POST['customer_id']);
        $units = floatval($_POST['consumed']);
        $rate = floatval($_POST['rate']);
        $due = sanitize_input($_POST['due']);
        $bill_type = sanitize_input($_POST['type']);
        $house_id = intval($_POST['house_id']);

        // FIX: Validate bill type
        if (!in_array($bill_type, ['Electric', 'Water'])) {
            $msg = "Invalid bill type.";
            $msg_type = "error";
        } elseif ($customer_id <= 0 || $units <= 0 || $rate <= 0 || $house_id <= 0) {
            $msg = "Please fill all required fields with valid values.";
            $msg_type = "error";
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            $msg = "Invalid date format.";
            $msg_type = "error";
        } else {
            $amount = $units * $rate;
            $bill_date = date('Y-m-d');

            // FIX: Use prepared statements
            if ($bill_type == 'Electric') {
                $stmt = $conn->prepare("INSERT INTO electric_bill (Customer_ID, House_ID, Units_Consumed, Rate_per_unit, Bill_Amount, Due_Date, Status) VALUES (?, ?, ?, ?, ?, ?, 'Unpaid')");
                $stmt->bind_param("iiddds", $customer_id, $house_id, $units, $rate, $amount, $due);
            } else {
                $stmt = $conn->prepare("INSERT INTO water_bill (Customer_ID, House_ID, Consumption_Liters, Rate_per_liter, Bill_Amount, Due_Date, Status) VALUES (?, ?, ?, ?, ?, ?, 'Unpaid')");
                $stmt->bind_param("iiddds", $customer_id, $house_id, $units, $rate, $amount, $due);
            }

            if ($stmt->execute()) {
                logEmployeeAction($conn, $_SESSION['employee_id'], 'Generate Bill', "Generated $bill_type bill for Customer ID $customer_id");
                $msg = "Bill Generated Successfully!";
                $msg_type = "success";
            } else {
                $msg = "Error: " . $conn->error;
                $msg_type = "error";
            }
            $stmt->close();
        }
    }
}

$customers_stmt = $conn->prepare("SELECT c.Customer_ID, c.Name, h.House_ID, h.House_Number FROM customer c LEFT JOIN house h ON c.House_ID = h.House_ID ORDER BY c.Name");
$customers_stmt->execute();
$customers = $customers_stmt->get_result();

$csrf_token = generate_csrf_token();
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
            max-height: 300px;
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

        .dropdown-item:last-child {
            border-bottom: none;
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

        .rate-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            padding: 12px 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 13px;
            color: #1565c0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        body.dark-mode .rate-info {
            background: linear-gradient(135deg, #1a2a3a 0%, #2a3a4a 100%);
            color: #64b5f6;
        }

        .rate-info i {
            font-size: 16px;
        }
    </style>
</head>

<body>
    <header class="dashboard-header" id="header">
        <div class="header-left">
            <h1><i class="fas fa-file-invoice-dollar"></i> Generate Bill</h1>
            <p>Create new utility bills for customers</p>
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

        <h2 class="section-header"><i class="fas fa-plus-circle"></i> New Bill Form</h2>

        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Bill Type</label>
                        <select name="type" id="billTypeSelect" class="form-control" required onchange="updateRateInfo()">
                            <option value="">Select Type</option>
                            <option value="Electric">Electric Bill</option>
                            <option value="Water">Water Bill</option>
                        </select>
                        <div id="rateInfo" class="rate-info" style="display: none;">
                            <i class="fas fa-info-circle"></i>
                            <span id="rateText"></span>
                        </div>
                    </div>

                    <div class="form-group search-dropdown">
                        <label>Search Customer</label>
                        <input
                            type="text"
                            id="customerSearch"
                            class="form-control"
                            placeholder="Type to search customer by name or ID..."
                            autocomplete="off"
                            onfocus="showDropdown()">
                        <input type="hidden" name="customer_id" id="customerIdInput" required>
                        <div id="customerDropdown" class="dropdown-list"></div>
                    </div>

                    <input type="hidden" name="house_id" id="houseIdInput" required>

                    <div class="form-group">
                        <label>House Details</label>
                        <input type="text" id="houseNumDisplay" class="form-control" placeholder="Auto-filled after selecting customer" readonly>
                    </div>

                    <div class="form-group">
                        <label id="consumptionLabel">Units/Liters Consumed</label>
                        <input type="number" name="consumed" class="form-control" placeholder="0.00" step="0.01" min="0.01" required>
                    </div>

                    <div class="form-group">
                        <label id="rateLabel">Rate per Unit/Liter (₹)</label>
                        <input type="number" name="rate" id="rateInput" class="form-control" placeholder="Enter rate" step="0.01" min="0.01" required>
                    </div>

                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <button type="submit" name="generate" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-save"></i> Generate Bill
                </button>
            </form>
        </div>
    </div>

    <script>
        // Customer data from PHP
        const customers = <?php
                            // Reset pointer to beginning
                            $customers->data_seek(0);
                            $customerData = [];
                            while ($cust = $customers->fetch_assoc()) {
                                $customerData[] = [
                                    'id' => $cust['Customer_ID'],
                                    'name' => $cust['Name'],
                                    'house_id' => $cust['House_ID'],
                                    'house_number' => $cust['House_Number']
                                ];
                            }
                            echo json_encode($customerData);
                            ?>;

        let filteredCustomers = [...customers];

        // Default rates for different bill types
        const defaultRates = {
            'Electric': 7.50,
            'Water': 0.50
        };

        // Search and filter customers
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
                        <small>ID: ${customer.id} | House: ${escapeHtml(customer.house_number || 'N/A')}</small>
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
                document.getElementById('houseNumDisplay').value = customer.house_number ?
                    `House: ${customer.house_number} (ID: ${customer.house_id})` : 'No house assigned';
                document.getElementById('customerDropdown').classList.remove('show');
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-dropdown')) {
                document.getElementById('customerDropdown').classList.remove('show');
            }
        });

        // Update rate info and default rate based on bill type
        function updateRateInfo() {
            const billType = document.getElementById('billTypeSelect').value;
            const rateInfo = document.getElementById('rateInfo');
            const rateText = document.getElementById('rateText');
            const rateInput = document.getElementById('rateInput');
            const consumptionLabel = document.getElementById('consumptionLabel');
            const rateLabel = document.getElementById('rateLabel');

            if (billType === 'Electric') {
                rateInfo.style.display = 'flex';
                rateText.textContent = `Typical electric rate: ₹6.50 - ₹8.00 per kWh`;
                rateInput.value = defaultRates.Electric;
                rateInput.placeholder = defaultRates.Electric;
                consumptionLabel.textContent = 'Units Consumed (kWh)';
                rateLabel.textContent = 'Rate per Unit (₹)';
            } else if (billType === 'Water') {
                rateInfo.style.display = 'flex';
                rateText.textContent = `Typical water rate: ₹0.30 - ₹0.55 per liter`;
                rateInput.value = defaultRates.Water;
                rateInput.placeholder = defaultRates.Water;
                consumptionLabel.textContent = 'Liters Consumed';
                rateLabel.textContent = 'Rate per Liter (₹)';
            } else {
                rateInfo.style.display = 'none';
                rateInput.value = '';
                rateInput.placeholder = 'Enter rate';
                consumptionLabel.textContent = 'Units/Liters Consumed';
                rateLabel.textContent = 'Rate per Unit/Liter (₹)';
            }
        }

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
                btn.innerHTML = mode === 'dark' ? '<i class="fas fa-sun"></i><span>Light Mode</span>' : '<i class="fas fa-moon"></i><span>Dark Mode</span>';
            });
            window.addEventListener('scroll', () => {
                if (window.scrollY > 30) header.classList.add('shrink');
                else header.classList.remove('shrink');
            });
        });
    </script>
</body>

</html>
<?php $customers_stmt->close(); ?>
