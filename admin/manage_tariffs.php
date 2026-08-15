<?php
require_once('../includes/db_connect.php');
require_once('../includes/functions.php');
require_once('../includes/log_functions.php');
require_once('../includes/tariff_engine.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    redirect('../index.php');
    exit;
}

$active_page = 'tariffs';
$admin_id = $_SESSION['admin_id'] ?? 1;

// Handle AJAX Simulation Request
if (isset($_GET['action']) && $_GET['action'] === 'simulate') {
    header('Content-Type: application/json');
    $utility = sanitize_input($_GET['utility'] ?? 'Electric');
    $units = floatval($_GET['units'] ?? 0);
    $catId = intval($_GET['category_id'] ?? 1);

    $breakdown = calculateBillWithSlabs($conn, $utility, $units, $catId);
    echo json_encode($breakdown);
    exit;
}

// Handle Form Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash_msg("Invalid or expired session token. Please try again.", "error");
        redirect('manage_tariffs.php');
        exit;
    }

    $action = $_POST['action'];

    // 1. Save or Update Slab
    if ($action === 'save_slab') {
        $slabId = intval($_POST['slab_id'] ?? 0);
        $categoryId = intval($_POST['category_id']);
        $utilityType = sanitize_input($_POST['utility_type']);
        $slabName = sanitize_input($_POST['slab_name']);
        $minUnits = floatval($_POST['min_units']);
        $maxUnits = ($_POST['max_units'] !== '' && $_POST['max_units'] !== null) ? floatval($_POST['max_units']) : null;
        $rate = floatval($_POST['rate_per_unit']);
        $fixed = floatval($_POST['fixed_charge'] ?? 0);
        $tax = floatval($_POST['tax_percent'] ?? 5);

        if ($rate <= 0 || empty($slabName)) {
            set_flash_msg("Slab name and positive rate are required.", "error");
        } else {
            if ($slabId > 0) {
                // Update
                $stmt = $conn->prepare("
                    UPDATE tariff_slabs 
                    SET category_id = ?, utility_type = ?, slab_name = ?, min_units = ?, max_units = ?, rate_per_unit = ?, fixed_charge = ?, tax_percent = ? 
                    WHERE slab_id = ?
                ");
                $stmt->bind_param("issdddddi", $categoryId, $utilityType, $slabName, $minUnits, $maxUnits, $rate, $fixed, $tax, $slabId);
                $ok = $stmt->execute();
                $stmt->close();
                logAdminAction($conn, $admin_id, "Updated tariff slab #$slabId ($slabName)");
                set_flash_msg("Tariff slab updated successfully!", "success");
            } else {
                // Insert
                $stmt = $conn->prepare("
                    INSERT INTO tariff_slabs (category_id, utility_type, slab_name, min_units, max_units, rate_per_unit, fixed_charge, tax_percent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("issddddd", $categoryId, $utilityType, $slabName, $minUnits, $maxUnits, $rate, $fixed, $tax);
                $ok = $stmt->execute();
                $newId = $stmt->insert_id;
                $stmt->close();
                logAdminAction($conn, $admin_id, "Created tariff slab #$newId ($slabName)");
                set_flash_msg("New tariff slab created successfully!", "success");
            }
        }
        redirect('manage_tariffs.php?tab=slabs');
        exit;
    }

    // 2. Delete Slab
    if ($action === 'delete_slab') {
        $slabId = intval($_POST['slab_id']);
        $stmt = $conn->prepare("DELETE FROM tariff_slabs WHERE slab_id = ?");
        $stmt->bind_param("i", $slabId);
        $stmt->execute();
        $stmt->close();
        logAdminAction($conn, $admin_id, "Deleted tariff slab #$slabId");
        set_flash_msg("Tariff slab removed successfully.", "success");
        redirect('manage_tariffs.php?tab=slabs');
        exit;
    }

    // 3. Save Category
    if ($action === 'save_category') {
        $catId = intval($_POST['category_id'] ?? 0);
        $code = strtoupper(trim(sanitize_input($_POST['category_code'])));
        $name = sanitize_input($_POST['category_name']);
        $util = sanitize_input($_POST['utility_type'] ?? 'Both');
        $desc = sanitize_input($_POST['description'] ?? '');

        if (empty($code) || empty($name)) {
            set_flash_msg("Category code and name are required.", "error");
        } else {
            if ($catId > 0) {
                $stmt = $conn->prepare("
                    UPDATE tariff_categories 
                    SET category_code = ?, category_name = ?, utility_type = ?, description = ? 
                    WHERE category_id = ?
                ");
                $stmt->bind_param("ssssi", $code, $name, $util, $desc, $catId);
                $stmt->execute();
                $stmt->close();
                logAdminAction($conn, $admin_id, "Updated tariff category #$catId ($name)");
                set_flash_msg("Tariff category updated successfully!", "success");
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO tariff_categories (category_code, category_name, utility_type, description) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("ssss", $code, $name, $util, $desc);
                $stmt->execute();
                $stmt->close();
                logAdminAction($conn, $admin_id, "Created tariff category $code");
                set_flash_msg("New category plan created successfully!", "success");
            }
        }
        redirect('manage_tariffs.php?tab=categories');
        exit;
    }

    // 4. Update Late Fee Rules
    if ($action === 'save_late_fee') {
        $electricGrace = intval($_POST['electric_grace'] ?? 3);
        $electricType = sanitize_input($_POST['electric_fee_type'] ?? 'percentage');
        $electricVal = floatval($_POST['electric_fee_val'] ?? 5);
        $electricMin = floatval($_POST['electric_min_fee'] ?? 50);
        $electricMax = floatval($_POST['electric_max_fee'] ?? 500);

        $waterGrace = intval($_POST['water_grace'] ?? 3);
        $waterType = sanitize_input($_POST['water_fee_type'] ?? 'percentage');
        $waterVal = floatval($_POST['water_fee_val'] ?? 5);
        $waterMin = floatval($_POST['water_min_fee'] ?? 30);
        $waterMax = floatval($_POST['water_max_fee'] ?? 300);

        // Update Electric Rule
        $stmt1 = $conn->prepare("
            UPDATE late_fee_rules 
            SET grace_period_days = ?, fee_type = ?, fee_value = ?, min_late_fee = ?, max_late_fee = ? 
            WHERE utility_type = 'Electric'
        ");
        $stmt1->bind_param("isddd", $electricGrace, $electricType, $electricVal, $electricMin, $electricMax);
        $stmt1->execute();
        $stmt1->close();

        // Update Water Rule
        $stmt2 = $conn->prepare("
            UPDATE late_fee_rules 
            SET grace_period_days = ?, fee_type = ?, fee_value = ?, min_late_fee = ?, max_late_fee = ? 
            WHERE utility_type = 'Water'
        ");
        $stmt2->bind_param("isddd", $waterGrace, $waterType, $waterVal, $waterMin, $waterMax);
        $stmt2->execute();
        $stmt2->close();

        logAdminAction($conn, $admin_id, "Updated utility late fee & grace period parameters");
        set_flash_msg("Late fee & penalty rules updated successfully!", "success");
        redirect('manage_tariffs.php?tab=late_fees');
        exit;
    }

    // 5. Trigger Manual Late Fee Calculation Run
    if ($action === 'run_late_fee_batch') {
        $batchRes = applyLateFeesToOverdueBills($conn);
        $count = $batchRes['electric_updated'] + $batchRes['water_updated'];
        $added = number_format($batchRes['total_late_fees_added'], 2);
        logAdminAction($conn, $admin_id, "Manually executed late fee calculation batch ($count bills updated, ₹$added penalty)");
        set_flash_msg("Batch execution completed: $count overdue bills adjusted with ₹$added total late surcharges.", "success");
        redirect('manage_tariffs.php?tab=late_fees');
        exit;
    }
}

// Fetch all data for display
$current_tab = sanitize_input($_GET['tab'] ?? 'slabs');
$categories = getTariffCategories($conn);

$slabs_sql = "
    SELECT s.*, c.category_name, c.category_code 
    FROM tariff_slabs s 
    JOIN tariff_categories c ON s.category_id = c.category_id 
    ORDER BY s.category_id ASC, s.utility_type ASC, s.min_units ASC
";
$slabs_res = $conn->query($slabs_sql);
$all_slabs = [];
if ($slabs_res) {
    while ($row = $slabs_res->fetch_assoc()) {
        $all_slabs[] = $row;
    }
}

$ruleElectric = getLateFeeRule($conn, 'Electric');
$ruleWater = getLateFeeRule($conn, 'Water');

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tariff & Late Fee Engine - Public Utility System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .tabs-header {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 5px;
            flex-wrap: wrap;
        }

        body.dark-mode .tabs-header {
            border-bottom-color: #3b3b4f;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 12px 20px;
            font-size: 15px;
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

        .tab-btn:hover {
            color: #4f46e5;
            background: #f1f5f9;
        }

        body.dark-mode .tab-btn:hover {
            background: #252538;
            color: #818cf8;
        }

        .tab-btn.active {
            color: #ffffff;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .tariff-card {
            background: var(--bg-card, #ffffff);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }

        body.dark-mode .tariff-card {
            background: #1e1e2d;
            border-color: #2e2e42;
        }

        .slab-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .tag-electric {
            background: #fef3c7;
            color: #92400e;
        }

        body.dark-mode .tag-electric {
            background: #451a03;
            color: #fde68a;
        }

        .tag-water {
            background: #e0f2fe;
            color: #0369a1;
        }

        body.dark-mode .tag-water {
            background: #082f49;
            color: #7dd3fc;
        }

        .simulator-box {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }

        body.dark-mode .simulator-box {
            background: linear-gradient(135deg, #181826 0%, #1e1b4b 100%);
            border-color: #3730a3;
        }

        .tier-progress-bar {
            display: flex;
            height: 28px;
            border-radius: 8px;
            overflow: hidden;
            background: #e2e8f0;
            margin: 15px 0;
        }

        .tier-segment {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            color: #ffffff;
            transition: width 0.3s ease;
        }

        .tier-color-1 { background: #3b82f6; }
        .tier-color-2 { background: #8b5cf6; }
        .tier-color-3 { background: #ec4899; }
        .tier-color-4 { background: #f59e0b; }
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
                        <h1><i class="fas fa-layer-group"></i> Dynamic Tariff & Late Fee Engine</h1>
                        <p>Configure progressive tiered utility slabs, category plans, and automated penalty rules</p>
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
                <?= display_flash_msg() ?>

                <!-- Navigation Tabs -->
                <div class="tabs-header">
                    <a href="manage_tariffs.php?tab=slabs" class="tab-btn <?= $current_tab === 'slabs' ? 'active' : '' ?>">
                        <i class="fas fa-bars-staggered"></i> Tariff Slabs & Tiers
                    </a>
                    <a href="manage_tariffs.php?tab=categories" class="tab-btn <?= $current_tab === 'categories' ? 'active' : '' ?>">
                        <i class="fas fa-tags"></i> Customer Categories
                    </a>
                    <a href="manage_tariffs.php?tab=late_fees" class="tab-btn <?= $current_tab === 'late_fees' ? 'active' : '' ?>">
                        <i class="fas fa-clock-rotate-left"></i> Late Fee & Grace Rules
                    </a>
                    <a href="manage_tariffs.php?tab=simulator" class="tab-btn <?= $current_tab === 'simulator' ? 'active' : '' ?>">
                        <i class="fas fa-calculator"></i> Live Tariff Simulator
                    </a>
                </div>

                <!-- TAB 1: SLABS & TIERS -->
                <?php if ($current_tab === 'slabs'): ?>
                    <div class="tariff-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <h2 style="margin: 0;"><i class="fas fa-bolt"></i> Configured Tariff Slabs</h2>
                                <p style="margin: 5px 0 0; color: #64748b; font-size: 14px;">Progressive pricing tiers applied sequentially during bill calculation</p>
                            </div>
                            <button onclick="openSlabModal()" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add New Tariff Slab
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Category</th>
                                        <th>Utility</th>
                                        <th>Tier Name</th>
                                        <th>Unit Range (Min - Max)</th>
                                        <th>Rate / Unit</th>
                                        <th>Fixed Charge</th>
                                        <th>Tax / Duty</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($all_slabs)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; color: #94a3b8; padding: 25px;">No tariff slabs configured yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($all_slabs as $s): ?>
                                            <tr>
                                                <td>#<?= $s['slab_id'] ?></td>
                                                <td>
                                                    <strong><?= e($s['category_name']) ?></strong>
                                                    <br><small style="color: #64748b;"><?= e($s['category_code']) ?></small>
                                                </td>
                                                <td>
                                                    <span class="slab-tag <?= $s['utility_type'] === 'Electric' ? 'tag-electric' : 'tag-water' ?>">
                                                        <i class="fas <?= $s['utility_type'] === 'Electric' ? 'fa-bolt' : 'fa-droplet' ?>"></i>
                                                        <?= e($s['utility_type']) ?>
                                                    </span>
                                                </td>
                                                <td><strong><?= e($s['slab_name']) ?></strong></td>
                                                <td>
                                                    <?= number_format($s['min_units'], 2) ?>
                                                    <?= $s['utility_type'] === 'Electric' ? 'kWh' : 'L' ?>
                                                    to
                                                    <?= $s['max_units'] !== null ? number_format($s['max_units'], 2) . ($s['utility_type'] === 'Electric' ? ' kWh' : ' L') : '<span class="badge badge-info">Above / Infinity</span>' ?>
                                                </td>
                                                <td><strong style="color: #10b981;">₹<?= number_format($s['rate_per_unit'], 2) ?></strong></td>
                                                <td>₹<?= number_format($s['fixed_charge'], 2) ?></td>
                                                <td><?= number_format($s['tax_percent'], 1) ?>%</td>
                                                <td>
                                                    <button onclick='editSlab(<?= json_encode($s) ?>)' class="btn btn-sm btn-secondary" title="Edit Slab">
                                                        <i class="fas fa-pen"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline-block;" onsubmit="return confirm('Delete slab #<?= $s['slab_id'] ?>?');">
                                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                        <input type="hidden" name="action" value="delete_slab">
                                                        <input type="hidden" name="slab_id" value="<?= $s['slab_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- TAB 2: CATEGORIES -->
                <?php if ($current_tab === 'categories'): ?>
                    <div class="tariff-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <h2 style="margin: 0;"><i class="fas fa-tags"></i> Customer Tariff Categories</h2>
                                <p style="margin: 5px 0 0; color: #64748b; font-size: 14px;">Define plans for Domestic, Commercial, and Industrial customers</p>
                            </div>
                            <button onclick="openCategoryModal()" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add New Category Plan
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Category Code</th>
                                        <th>Plan Name</th>
                                        <th>Utility Scope</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td>#<?= $cat['category_id'] ?></td>
                                            <td><span class="badge badge-primary"><?= e($cat['category_code']) ?></span></td>
                                            <td><strong><?= e($cat['category_name']) ?></strong></td>
                                            <td><?= e($cat['utility_type']) ?></td>
                                            <td style="color: #64748b; max-width: 350px;"><?= e($cat['description']) ?></td>
                                            <td>
                                                <button onclick='editCategory(<?= json_encode($cat) ?>)' class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-pen"></i> Edit
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- TAB 3: LATE FEE RULES -->
                <?php if ($current_tab === 'late_fees'): ?>
                    <div class="tariff-card">
                        <h2><i class="fas fa-clock-rotate-left"></i> Automated Late Fee & Grace Period Rules</h2>
                        <p style="color: #64748b; margin-bottom: 25px;">When unpaid bills exceed their Due Date plus configured Grace Days, the engine automatically calculates and adds a penalty surcharge.</p>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                            <input type="hidden" name="action" value="save_late_fee">

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 25px;">
                                <!-- Electric Rules -->
                                <div style="background: #f8fafc; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0;">
                                    <h3 style="color: #d97706; margin-top: 0;"><i class="fas fa-bolt"></i> Electricity Late Fee Rules</h3>
                                    <div class="form-group">
                                        <label>Grace Period (Days after Due Date)</label>
                                        <input type="number" name="electric_grace" class="form-control" value="<?= intval($ruleElectric['grace_period_days'] ?? 3) ?>" min="0" max="30" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Calculation Method</label>
                                        <select name="electric_fee_type" class="form-control">
                                            <option value="percentage" <?= ($ruleElectric['fee_type'] ?? '') === 'percentage' ? 'selected' : '' ?>>Percentage of Base Bill (%)</option>
                                            <option value="fixed" <?= ($ruleElectric['fee_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed One-time Surcharge (₹)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Fee Value (% or ₹)</label>
                                        <input type="number" step="0.01" name="electric_fee_val" class="form-control" value="<?= floatval($ruleElectric['fee_value'] ?? 5) ?>" required>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                        <div class="form-group">
                                            <label>Min Late Fee (₹)</label>
                                            <input type="number" step="0.01" name="electric_min_fee" class="form-control" value="<?= floatval($ruleElectric['min_late_fee'] ?? 50) ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Max Cap (₹)</label>
                                            <input type="number" step="0.01" name="electric_max_fee" class="form-control" value="<?= floatval($ruleElectric['max_late_fee'] ?? 500) ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Water Rules -->
                                <div style="background: #f8fafc; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0;">
                                    <h3 style="color: #0284c7; margin-top: 0;"><i class="fas fa-droplet"></i> Water Late Fee Rules</h3>
                                    <div class="form-group">
                                        <label>Grace Period (Days after Due Date)</label>
                                        <input type="number" name="water_grace" class="form-control" value="<?= intval($ruleWater['grace_period_days'] ?? 3) ?>" min="0" max="30" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Calculation Method</label>
                                        <select name="water_fee_type" class="form-control">
                                            <option value="percentage" <?= ($ruleWater['fee_type'] ?? '') === 'percentage' ? 'selected' : '' ?>>Percentage of Base Bill (%)</option>
                                            <option value="fixed" <?= ($ruleWater['fee_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed One-time Surcharge (₹)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Fee Value (% or ₹)</label>
                                        <input type="number" step="0.01" name="water_fee_val" class="form-control" value="<?= floatval($ruleWater['fee_value'] ?? 5) ?>" required>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                        <div class="form-group">
                                            <label>Min Late Fee (₹)</label>
                                            <input type="number" step="0.01" name="water_min_fee" class="form-control" value="<?= floatval($ruleWater['min_late_fee'] ?? 30) ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Max Cap (₹)</label>
                                            <input type="number" step="0.01" name="water_max_fee" class="form-control" value="<?= floatval($ruleWater['max_late_fee'] ?? 300) ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top: 25px; display: flex; gap: 15px; flex-wrap: wrap;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Late Fee Parameters
                                </button>
                            </div>
                        </form>

                        <hr style="margin: 30px 0; border: 0; border-top: 1px solid #e2e8f0;">

                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                            <div>
                                <h3 style="margin: 0;"><i class="fas fa-bolt-lightning"></i> Manual Batch Overdue Check</h3>
                                <p style="margin: 5px 0 0; color: #64748b; font-size: 14px;">Scan all unpaid bills past grace date right now and calculate surcharge penalties.</p>
                            </div>
                            <form method="POST" onsubmit="return confirm('Run automated late fee calculation for all unpaid overdue bills now?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                <input type="hidden" name="action" value="run_late_fee_batch">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-play"></i> Execute Overdue Batch Run
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- TAB 4: LIVE SIMULATOR -->
                <?php if ($current_tab === 'simulator'): ?>
                    <div class="tariff-card">
                        <h2><i class="fas fa-calculator"></i> Interactive Progressive Tariff Simulator</h2>
                        <p style="color: #64748b;">Test slab calculations in real time to verify tier breakdowns, fixed charges, and tax additions.</p>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 20px;">
                            <div class="form-group">
                                <label>Utility Type</label>
                                <select id="simUtility" class="form-control" onchange="runSimulation()">
                                    <option value="Electric">Electricity (kWh)</option>
                                    <option value="Water">Water (Liters)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Tariff Plan Category</label>
                                <select id="simCategory" class="form-control" onchange="runSimulation()">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['category_id'] ?>"><?= e($cat['category_name']) ?> (<?= e($cat['category_code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label id="simUnitsLabel">Units Consumed (kWh)</label>
                                <input type="number" id="simUnits" class="form-control" value="250" step="1" min="0" oninput="runSimulation()">
                            </div>
                        </div>

                        <!-- Live Breakdown Output -->
                        <div class="simulator-box" id="simResultBox">
                            <h3 style="margin-top: 0; display: flex; justify-content: space-between; align-items: center;">
                                <span><i class="fas fa-receipt"></i> Progressive Calculation Summary</span>
                                <span id="simEffectiveRate" class="badge badge-info" style="font-size: 13px;"></span>
                            </h3>

                            <div class="tier-progress-bar" id="simBar"></div>

                            <div id="simTierList" style="margin: 20px 0;"></div>

                            <div style="background: white; border-radius: 8px; padding: 15px; border: 1px solid #e2e8f0; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                                <div>
                                    <small style="color: #64748b; display: block;">Energy / Base Cost</small>
                                    <strong id="simBaseCost" style="font-size: 18px; color: #1e293b;">₹0.00</strong>
                                </div>
                                <div>
                                    <small style="color: #64748b; display: block;">Fixed Meter Charge</small>
                                    <strong id="simFixedCharge" style="font-size: 18px; color: #1e293b;">₹0.00</strong>
                                </div>
                                <div>
                                    <small style="color: #64748b; display: block;">Tax / Duty (<span id="simTaxRate">5</span>%)</small>
                                    <strong id="simTaxAmount" style="font-size: 18px; color: #1e293b;">₹0.00</strong>
                                </div>
                                <div style="background: #e0e7ff; padding: 8px 12px; border-radius: 6px;">
                                    <small style="color: #4338ca; font-weight: bold; display: block;">Estimated Total Bill</small>
                                    <strong id="simTotalAmount" style="font-size: 22px; color: #3730a3;">₹0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL: ADD / EDIT SLAB -->
    <div id="slabModal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; width: 90%; max-width: 550px; border-radius: 12px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 id="slabModalTitle" style="margin: 0;"><i class="fas fa-layer-group"></i> Add Tariff Slab</h3>
                <button type="button" onclick="closeSlabModal()" style="border: none; background: none; font-size: 20px; cursor: pointer;">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="action" value="save_slab">
                <input type="hidden" name="slab_id" id="modalSlabId" value="0">

                <div class="form-group">
                    <label>Tariff Plan Category</label>
                    <select name="category_id" id="modalSlabCat" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= e($cat['category_name']) ?> (<?= e($cat['category_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Utility Type</label>
                    <select name="utility_type" id="modalSlabUtil" class="form-control" required>
                        <option value="Electric">Electric</option>
                        <option value="Water">Water</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Slab / Tier Name</label>
                    <input type="text" name="slab_name" id="modalSlabName" class="form-control" placeholder="e.g. Subsidized Lifeline Tier" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Min Units</label>
                        <input type="number" step="0.01" name="min_units" id="modalSlabMin" class="form-control" value="0" required>
                    </div>
                    <div class="form-group">
                        <label>Max Units (Leave blank for Top/Infinity)</label>
                        <input type="number" step="0.01" name="max_units" id="modalSlabMax" class="form-control" placeholder="Infinity">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label>Rate / Unit (₹)</label>
                        <input type="number" step="0.01" name="rate_per_unit" id="modalSlabRate" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label>Fixed Fee (₹)</label>
                        <input type="number" step="0.01" name="fixed_charge" id="modalSlabFixed" class="form-control" value="0.00">
                    </div>
                    <div class="form-group">
                        <label>Tax / Duty (%)</label>
                        <input type="number" step="0.01" name="tax_percent" id="modalSlabTax" class="form-control" value="5.00">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" onclick="closeSlabModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Slab</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ADD / EDIT CATEGORY -->
    <div id="catModal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; width: 90%; max-width: 500px; border-radius: 12px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 id="catModalTitle" style="margin: 0;"><i class="fas fa-tags"></i> Add Category Plan</h3>
                <button type="button" onclick="closeCatModal()" style="border: none; background: none; font-size: 20px; cursor: pointer;">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="action" value="save_category">
                <input type="hidden" name="category_id" id="modalCatId" value="0">

                <div class="form-group">
                    <label>Category Code</label>
                    <input type="text" name="category_code" id="modalCatCode" class="form-control" placeholder="e.g. DOMESTIC" required>
                </div>
                <div class="form-group">
                    <label>Plan Name</label>
                    <input type="text" name="category_name" id="modalCatName" class="form-control" placeholder="e.g. Residential Household Plan" required>
                </div>
                <div class="form-group">
                    <label>Utility Scope</label>
                    <select name="utility_type" id="modalCatUtil" class="form-control">
                        <option value="Both">Both (Electric & Water)</option>
                        <option value="Electric">Electric Only</option>
                        <option value="Water">Water Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="modalCatDesc" class="form-control" rows="3"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" onclick="closeCatModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Category</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openSlabModal() {
            document.getElementById('slabModalTitle').innerHTML = '<i class="fas fa-plus"></i> Add New Tariff Slab';
            document.getElementById('modalSlabId').value = '0';
            document.getElementById('modalSlabName').value = '';
            document.getElementById('modalSlabMin').value = '0';
            document.getElementById('modalSlabMax').value = '';
            document.getElementById('modalSlabRate').value = '';
            document.getElementById('modalSlabFixed').value = '0';
            document.getElementById('modalSlabTax').value = '5.00';
            document.getElementById('slabModal').style.display = 'flex';
        }

        function editSlab(slab) {
            document.getElementById('slabModalTitle').innerHTML = '<i class="fas fa-pen"></i> Edit Tariff Slab #' + slab.slab_id;
            document.getElementById('modalSlabId').value = slab.slab_id;
            document.getElementById('modalSlabCat').value = slab.category_id;
            document.getElementById('modalSlabUtil').value = slab.utility_type;
            document.getElementById('modalSlabName').value = slab.slab_name;
            document.getElementById('modalSlabMin').value = slab.min_units;
            document.getElementById('modalSlabMax').value = slab.max_units !== null ? slab.max_units : '';
            document.getElementById('modalSlabRate').value = slab.rate_per_unit;
            document.getElementById('modalSlabFixed').value = slab.fixed_charge;
            document.getElementById('modalSlabTax').value = slab.tax_percent;
            document.getElementById('slabModal').style.display = 'flex';
        }

        function closeSlabModal() {
            document.getElementById('slabModal').style.display = 'none';
        }

        function openCategoryModal() {
            document.getElementById('catModalTitle').innerHTML = '<i class="fas fa-plus"></i> Add New Category';
            document.getElementById('modalCatId').value = '0';
            document.getElementById('modalCatCode').value = '';
            document.getElementById('modalCatName').value = '';
            document.getElementById('modalCatUtil').value = 'Both';
            document.getElementById('modalCatDesc').value = '';
            document.getElementById('catModal').style.display = 'flex';
        }

        function editCategory(cat) {
            document.getElementById('catModalTitle').innerHTML = '<i class="fas fa-pen"></i> Edit Category #' + cat.category_id;
            document.getElementById('modalCatId').value = cat.category_id;
            document.getElementById('modalCatCode').value = cat.category_code;
            document.getElementById('modalCatName').value = cat.category_name;
            document.getElementById('modalCatUtil').value = cat.utility_type;
            document.getElementById('modalCatDesc').value = cat.description;
            document.getElementById('catModal').style.display = 'flex';
        }

        function closeCatModal() {
            document.getElementById('catModal').style.display = 'none';
        }

        // Live Tariff Simulator Script
        async function runSimulation() {
            const utility = document.getElementById('simUtility')?.value || 'Electric';
            const categoryId = document.getElementById('simCategory')?.value || '1';
            const units = document.getElementById('simUnits')?.value || 0;

            const unitLabel = document.getElementById('simUnitsLabel');
            if (unitLabel) unitLabel.innerText = utility === 'Electric' ? 'Units Consumed (kWh)' : 'Volume Consumed (Liters)';

            try {
                const res = await fetch(`manage_tariffs.php?action=simulate&utility=${utility}&category_id=${categoryId}&units=${units}`);
                const data = await res.json();

                document.getElementById('simEffectiveRate').innerText = `Effective: ₹${data.effective_rate}/${utility === 'Electric' ? 'kWh' : 'L'}`;
                document.getElementById('simBaseCost').innerText = `₹${parseFloat(data.base_amount).toFixed(2)}`;
                document.getElementById('simFixedCharge').innerText = `₹${parseFloat(data.fixed_charge).toFixed(2)}`;
                document.getElementById('simTaxRate').innerText = data.tax_percent;
                document.getElementById('simTaxAmount').innerText = `₹${parseFloat(data.tax_amount).toFixed(2)}`;
                document.getElementById('simTotalAmount').innerText = `₹${parseFloat(data.total_amount).toFixed(2)}`;

                // Render Visual Bar & List
                const colors = ['tier-color-1', 'tier-color-2', 'tier-color-3', 'tier-color-4'];
                let barHtml = '';
                let listHtml = '<div style="display: flex; flex-direction: column; gap: 8px;">';

                if (data.slabs && data.slabs.length > 0) {
                    data.slabs.forEach((slab, i) => {
                        const colorClass = colors[i % colors.length];
                        const pct = data.units_consumed > 0 ? (slab.units_in_slab / data.units_consumed) * 100 : 0;
                        if (pct > 0) {
                            barHtml += `<div class="tier-segment ${colorClass}" style="width: ${pct}%" title="${slab.slab_name}: ${slab.units_in_slab} units">${slab.units_in_slab} ${utility === 'Electric' ? 'kWh' : 'L'}</div>`;
                        }

                        listHtml += `
                            <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: white; border-radius: 6px; border-left: 4px solid ${i === 0 ? '#3b82f6' : (i === 1 ? '#8b5cf6' : '#ec4899')}; font-size: 14px;">
                                <div>
                                    <strong>${slab.slab_name}</strong> 
                                    <span style="color: #64748b;">(${slab.units_in_slab} ${utility === 'Electric' ? 'kWh' : 'L'} @ ₹${parseFloat(slab.rate_per_unit).toFixed(2)}/${utility === 'Electric' ? 'unit' : 'L'})</span>
                                </div>
                                <div><strong>₹${parseFloat(slab.subtotal).toFixed(2)}</strong></div>
                            </div>
                        `;
                    });
                }
                listHtml += '</div>';

                document.getElementById('simBar').innerHTML = barHtml || '<div style="padding: 5px; font-size: 12px; color: #94a3b8;">No units consumed</div>';
                document.getElementById('simTierList').innerHTML = listHtml;
            } catch (err) {
                console.error(err);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('simUnits')) {
                runSimulation();
            }
        });
    </script>
</body>

</html>
