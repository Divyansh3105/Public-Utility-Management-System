<?php
/**
 * Public Utility Management System — Dynamic Slab Tariffs & Automated Late Fee Engine
 * Provides progressive tiered rate calculations, breakdown logging, and automated late fee management.
 */

require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/functions.php');

/**
 * Fetch all active tariff categories
 */
function getTariffCategories(mysqli $conn, ?string $utilityType = null): array
{
    $sql = "SELECT * FROM tariff_categories WHERE is_active = 1";
    if ($utilityType) {
        $sql .= " AND (utility_type = '" . $conn->real_escape_string($utilityType) . "' OR utility_type = 'Both')";
    }
    $sql .= " ORDER BY category_id ASC";
    
    $result = $conn->query($sql);
    $categories = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    return $categories;
}

/**
 * Fetch a single tariff category by ID
 */
function getTariffCategoryById(mysqli $conn, int $categoryId): ?array
{
    $stmt = $conn->prepare("SELECT * FROM tariff_categories WHERE category_id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc();
    $stmt->close();
    return $data ?: null;
}

/**
 * Fetch ordered tariff slabs for a category and utility type
 */
function getTariffSlabs(mysqli $conn, int $categoryId, string $utilityType): array
{
    $stmt = $conn->prepare("
        SELECT * FROM tariff_slabs 
        WHERE category_id = ? AND utility_type = ? 
        ORDER BY min_units ASC
    ");
    if (!$stmt) return [];
    $stmt->bind_param("is", $categoryId, $utilityType);
    $stmt->execute();
    $res = $stmt->get_result();
    $slabs = [];
    while ($row = $res->fetch_assoc()) {
        $slabs[] = $row;
    }
    $stmt->close();
    return $slabs;
}

/**
 * Fetch active late fee rule for a utility type
 */
function getLateFeeRule(mysqli $conn, string $utilityType): ?array
{
    $stmt = $conn->prepare("
        SELECT * FROM late_fee_rules 
        WHERE (utility_type = ? OR utility_type = 'Both') AND is_active = 1 
        ORDER BY (utility_type = ?) DESC LIMIT 1
    ");
    if (!$stmt) return null;
    $stmt->bind_param("ss", $utilityType, $utilityType);
    $stmt->execute();
    $res = $stmt->get_result();
    $rule = $res->fetch_assoc();
    $stmt->close();

    if (!$rule) {
        // Fallback default rule
        return [
            'rule_id' => 0,
            'utility_type' => $utilityType,
            'grace_period_days' => 3,
            'fee_type' => 'percentage',
            'fee_value' => 5.00,
            'min_late_fee' => 50.00,
            'max_late_fee' => 500.00,
            'is_active' => 1
        ];
    }
    return $rule;
}

/**
 * Calculate progressive tiered slab bill breakdown
 *
 * @param mysqli $conn Database connection
 * @param string $utilityType 'Electric' or 'Water'
 * @param float $units Consumed units (kWh or Liters)
 * @param int $categoryId Tariff Category ID (defaults to 1 = Domestic)
 * @param string|null $dueDate Target due date (e.g. Y-m-d)
 * @return array Detailed calculation breakdown
 */
function calculateBillWithSlabs(mysqli $conn, string $utilityType, float $units, int $categoryId = 1, ?string $dueDate = null): array
{
    $units = max(0.00, round((float)$units, 2));
    $category = getTariffCategoryById($conn, $categoryId) ?? [
        'category_id' => 1,
        'category_name' => 'Residential / Domestic',
        'category_code' => 'DOMESTIC'
    ];

    $slabs = getTariffSlabs($conn, $category['category_id'], $utilityType);

    // Fallback if no slabs configured in DB
    if (empty($slabs)) {
        $defaultRate = ($utilityType === 'Water') ? 0.50 : 7.50;
        $baseAmount = round($units * $defaultRate, 2);
        $fixedCharge = ($utilityType === 'Water') ? 20.00 : 50.00;
        $taxPercent = 5.00;
        $taxAmount = round(($baseAmount + $fixedCharge) * ($taxPercent / 100), 2);
        $totalAmount = round($baseAmount + $fixedCharge + $taxAmount, 2);

        $breakdown = [
            'category_id' => $category['category_id'],
            'category_name' => $category['category_name'],
            'category_code' => $category['category_code'],
            'utility_type' => $utilityType,
            'units_consumed' => $units,
            'slabs' => [
                [
                    'slab_id' => 0,
                    'slab_name' => 'Standard Rate',
                    'min_units' => 0,
                    'max_units' => null,
                    'units_in_slab' => $units,
                    'rate_per_unit' => $defaultRate,
                    'subtotal' => $baseAmount
                ]
            ],
            'base_amount' => $baseAmount,
            'fixed_charge' => $fixedCharge,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'late_fee' => 0.00,
            'total_amount' => $totalAmount,
            'effective_rate' => $units > 0 ? round($totalAmount / $units, 2) : 0.00,
            'due_date' => $dueDate ?: date('Y-m-d', strtotime('+15 days')),
            'grace_due_date' => date('Y-m-d', strtotime(($dueDate ?: date('Y-m-d', strtotime('+15 days'))) . ' + 3 days'))
        ];
        return $breakdown;
    }

    $slabItems = [];
    $baseAmount = 0.00;
    $maxFixedCharge = 0.00;
    $appliedTaxPercent = 5.00;

    foreach ($slabs as $index => $slab) {
        $min = (float)$slab['min_units'];
        $max = isset($slab['max_units']) && $slab['max_units'] !== null && $slab['max_units'] !== '' ? (float)$slab['max_units'] : null;
        $rate = (float)$slab['rate_per_unit'];
        $slabFixed = (float)($slab['fixed_charge'] ?? 0.00);
        $slabTax = (float)($slab['tax_percent'] ?? 5.00);

        if ($units <= $min && $min > 0) {
            // Did not reach this slab
            continue;
        }

        // Calculate units falling in this tier
        if ($max === null) {
            // Infinite / top slab
            $unitsInSlab = max(0.00, $units - $min);
        } else {
            $effectiveUpper = min($units, $max);
            $unitsInSlab = max(0.00, $effectiveUpper - $min);
        }

        if ($unitsInSlab > 0) {
            $subtotal = round($unitsInSlab * $rate, 2);
            $baseAmount += $subtotal;
            if ($slabFixed > $maxFixedCharge) {
                $maxFixedCharge = $slabFixed;
            }
            $appliedTaxPercent = $slabTax;

            $slabItems[] = [
                'slab_id' => (int)$slab['slab_id'],
                'slab_name' => $slab['slab_name'],
                'min_units' => $min,
                'max_units' => $max,
                'units_in_slab' => round($unitsInSlab, 2),
                'rate_per_unit' => $rate,
                'subtotal' => $subtotal
            ];
        }
    }

    // If consumption was 0, still apply minimum base fixed charge
    if (empty($slabItems) && !empty($slabs)) {
        $firstSlab = $slabs[0];
        $maxFixedCharge = (float)($firstSlab['fixed_charge'] ?? 0.00);
        $appliedTaxPercent = (float)($firstSlab['tax_percent'] ?? 5.00);
        $slabItems[] = [
            'slab_id' => (int)$firstSlab['slab_id'],
            'slab_name' => $firstSlab['slab_name'],
            'min_units' => (float)$firstSlab['min_units'],
            'max_units' => $firstSlab['max_units'] !== null ? (float)$firstSlab['max_units'] : null,
            'units_in_slab' => 0.00,
            'rate_per_unit' => (float)$firstSlab['rate_per_unit'],
            'subtotal' => 0.00
        ];
    }

    $baseAmount = round($baseAmount, 2);
    $fixedCharge = round($maxFixedCharge, 2);
    $taxAmount = round(($baseAmount + $fixedCharge) * ($appliedTaxPercent / 100), 2);
    $totalAmount = round($baseAmount + $fixedCharge + $taxAmount, 2);

    $effectiveDueDate = $dueDate ?: date('Y-m-d', strtotime('+15 days'));
    $lateFeeRule = getLateFeeRule($conn, $utilityType);
    $graceDays = (int)($lateFeeRule['grace_period_days'] ?? 3);
    $graceDueDate = date('Y-m-d', strtotime($effectiveDueDate . " + {$graceDays} days"));

    return [
        'category_id' => (int)$category['category_id'],
        'category_name' => $category['category_name'],
        'category_code' => $category['category_code'],
        'utility_type' => $utilityType,
        'units_consumed' => $units,
        'slabs' => $slabItems,
        'base_amount' => $baseAmount,
        'fixed_charge' => $fixedCharge,
        'tax_percent' => $appliedTaxPercent,
        'tax_amount' => $taxAmount,
        'late_fee' => 0.00,
        'total_amount' => $totalAmount,
        'effective_rate' => $units > 0 ? round($totalAmount / $units, 2) : 0.00,
        'due_date' => $effectiveDueDate,
        'grace_due_date' => $graceDueDate,
        'grace_period_days' => $graceDays
    ];
}

/**
 * Calculate late fee penalty for a bill
 *
 * @param mysqli $conn
 * @param string $utilityType 'Electric' or 'Water'
 * @param array $bill Bill row containing Due_Date, Bill_Amount, Base_Amount, etc.
 * @param string|null $asOfDate Optional test date (default: today)
 * @return array Late fee calculation status and amount
 */
function calculateLateFeeForBill(mysqli $conn, string $utilityType, array $bill, ?string $asOfDate = null): array
{
    $rule = getLateFeeRule($conn, $utilityType);
    $graceDays = (int)($rule['grace_period_days'] ?? 3);
    $dueDate = $bill['Due_Date'] ?? date('Y-m-d');
    $graceDueDate = $bill['Grace_Due_Date'] ?? date('Y-m-d', strtotime($dueDate . " + {$graceDays} days"));
    $currentDate = $asOfDate ?? date('Y-m-d');

    $isOverdue = (strtotime($currentDate) > strtotime($graceDueDate));
    $daysPastDue = max(0, (int)round((strtotime($currentDate) - strtotime($dueDate)) / (60 * 60 * 24)));
    $daysPastGrace = max(0, (int)round((strtotime($currentDate) - strtotime($graceDueDate)) / (60 * 60 * 24)));

    if (!$isOverdue) {
        return [
            'is_overdue' => false,
            'days_past_due' => $daysPastDue,
            'days_past_grace' => 0,
            'grace_due_date' => $graceDueDate,
            'late_fee' => 0.00,
            'rule_applied' => $rule
        ];
    }

    // Bill base for late fee calculation (use Base_Amount or Bill_Amount)
    $calcBase = (float)($bill['Base_Amount'] ?? $bill['Bill_Amount'] ?? 0.00);
    if ($calcBase <= 0) $calcBase = (float)($bill['Bill_Amount'] ?? 0.00);

    $feeType = $rule['fee_type'] ?? 'percentage';
    $feeValue = (float)($rule['fee_value'] ?? 5.00);
    $minFee = (float)($rule['min_late_fee'] ?? 50.00);
    $maxFee = (float)($rule['max_late_fee'] ?? 500.00);

    $calculatedFee = 0.00;
    if ($feeType === 'percentage') {
        $calculatedFee = ($calcBase * ($feeValue / 100));
    } elseif ($feeType === 'fixed') {
        $calculatedFee = $feeValue;
    } elseif ($feeType === 'daily_fixed') {
        $calculatedFee = $feeValue * max(1, $daysPastGrace);
    }

    $finalLateFee = round(max($minFee, min($maxFee, $calculatedFee)), 2);

    return [
        'is_overdue' => true,
        'days_past_due' => $daysPastDue,
        'days_past_grace' => $daysPastGrace,
        'grace_due_date' => $graceDueDate,
        'late_fee' => $finalLateFee,
        'rule_applied' => $rule
    ];
}

/**
 * Scan all unpaid overdue bills past grace period and apply late fee penalty
 *
 * @param mysqli $conn
 * @return array Summary of processed bills
 */
function applyLateFeesToOverdueBills(mysqli $conn): array
{
    $results = [
        'electric_updated' => 0,
        'water_updated' => 0,
        'total_late_fees_added' => 0.00,
        'notifications_triggered' => 0,
        'details' => []
    ];

    $today = date('Y-m-d');

    // 1. Process Electric Bills
    $sqlElectric = "
        SELECT eb.*, c.Name as Customer_Name, c.Email, c.Phone 
        FROM electric_bill eb
        JOIN customer c ON eb.Customer_ID = c.Customer_ID
        WHERE eb.Status = 'Unpaid' 
          AND eb.Late_Fee = 0.00 
          AND CURDATE() > DATE_ADD(eb.Due_Date, INTERVAL 3 DAY)
    ";
    $resElectric = $conn->query($sqlElectric);
    if ($resElectric) {
        while ($bill = $resElectric->fetch_assoc()) {
            $feeInfo = calculateLateFeeForBill($conn, 'Electric', $bill, $today);
            if ($feeInfo['is_overdue'] && $feeInfo['late_fee'] > 0) {
                $lateFee = $feeInfo['late_fee'];
                $newTotal = round((float)$bill['Bill_Amount'] + $lateFee, 2);
                $graceDueDate = $feeInfo['grace_due_date'];

                $stmt = $conn->prepare("
                    UPDATE electric_bill 
                    SET Late_Fee = ?, Bill_Amount = ?, Grace_Due_Date = ? 
                    WHERE Bill_ID = ? AND Status = 'Unpaid'
                ");
                if ($stmt) {
                    $stmt->bind_param("ddsi", $lateFee, $newTotal, $graceDueDate, $bill['Bill_ID']);
                    if ($stmt->execute()) {
                        $results['electric_updated']++;
                        $results['total_late_fees_added'] += $lateFee;
                        $results['details'][] = [
                            'type' => 'Electric',
                            'bill_id' => $bill['Bill_ID'],
                            'customer' => $bill['Customer_Name'],
                            'late_fee' => $lateFee,
                            'new_total' => $newTotal
                        ];
                    }
                    $stmt->close();
                }
            }
        }
    }

    // 2. Process Water Bills
    $sqlWater = "
        SELECT wb.*, c.Name as Customer_Name, c.Email, c.Phone 
        FROM water_bill wb
        JOIN customer c ON wb.Customer_ID = c.Customer_ID
        WHERE wb.Status = 'Unpaid' 
          AND wb.Late_Fee = 0.00 
          AND CURDATE() > DATE_ADD(wb.Due_Date, INTERVAL 3 DAY)
    ";
    $resWater = $conn->query($sqlWater);
    if ($resWater) {
        while ($bill = $resWater->fetch_assoc()) {
            $feeInfo = calculateLateFeeForBill($conn, 'Water', $bill, $today);
            if ($feeInfo['is_overdue'] && $feeInfo['late_fee'] > 0) {
                $lateFee = $feeInfo['late_fee'];
                $newTotal = round((float)$bill['Bill_Amount'] + $lateFee, 2);
                $graceDueDate = $feeInfo['grace_due_date'];

                $stmt = $conn->prepare("
                    UPDATE water_bill 
                    SET Late_Fee = ?, Bill_Amount = ?, Grace_Due_Date = ? 
                    WHERE Bill_ID = ? AND Status = 'Unpaid'
                ");
                if ($stmt) {
                    $stmt->bind_param("ddsi", $lateFee, $newTotal, $graceDueDate, $bill['Bill_ID']);
                    if ($stmt->execute()) {
                        $results['water_updated']++;
                        $results['total_late_fees_added'] += $lateFee;
                        $results['details'][] = [
                            'type' => 'Water',
                            'bill_id' => $bill['Bill_ID'],
                            'customer' => $bill['Customer_Name'],
                            'late_fee' => $lateFee,
                            'new_total' => $newTotal
                        ];
                    }
                    $stmt->close();
                }
            }
        }
    }

    return $results;
}
