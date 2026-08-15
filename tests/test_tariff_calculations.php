<?php
/**
 * Test Suite for Dynamic Slab Tariffs & Automated Late Fee Engine
 */

require_once(__DIR__ . '/../includes/db_connect.php');
require_once(__DIR__ . '/../includes/tariff_engine.php');

echo "=== PUBLIC UTILITY SYSTEM: TARIFF ENGINE TEST SUITE ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest($description, $condition) {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] $description\n";
        $passCount++;
    } else {
        echo "  [FAIL] $description\n";
        $failCount++;
    }
}

// 1. Test Domestic Electricity Calculation: 50 units (lifeline tier 0-100 @ 4.50)
echo "1. Testing Domestic Electricity (50 units):\n";
$res50 = calculateBillWithSlabs($conn, 'Electric', 50.00, 1, '2026-09-01');
// 50 * 4.50 = 225.00 base, fixed = 30.00, subtotal = 255.00, tax 5% = 12.75, total = 267.75
assertTest("Base amount is 225.00", abs($res50['base_amount'] - 225.00) < 0.01);
assertTest("Fixed charge is 30.00", abs($res50['fixed_charge'] - 30.00) < 0.01);
assertTest("Tax amount is 12.75", abs($res50['tax_amount'] - 12.75) < 0.01);
assertTest("Total amount is 267.75", abs($res50['total_amount'] - 267.75) < 0.01);
assertTest("1 slab tier utilized", count($res50['slabs']) === 1);

// 2. Test Domestic Electricity Multi-Slab Calculation: 250 units (0-100 @ 4.50, 100-250 @ 6.50)
echo "\n2. Testing Multi-Slab Domestic Electricity (250 units):\n";
$res250 = calculateBillWithSlabs($conn, 'Electric', 250.00, 1, '2026-09-01');
// Tier 1: 100 * 4.50 = 450.00
// Tier 2: 150 * 6.50 = 975.00
// Base = 1425.00, Fixed = 50.00, Tax 5% of 1475 = 73.75, Total = 1548.75
assertTest("Base amount is 1425.00", abs($res250['base_amount'] - 1425.00) < 0.01);
assertTest("Fixed charge is 50.00", abs($res250['fixed_charge'] - 50.00) < 0.01);
assertTest("Tax amount is 73.75", abs($res250['tax_amount'] - 73.75) < 0.01);
assertTest("Total amount is 1548.75", abs($res250['total_amount'] - 1548.75) < 0.01);
assertTest("2 slab tiers utilized", count($res250['slabs']) === 2);

// 3. Test Commercial Electricity: 150 units (0-150 @ 8.00)
echo "\n3. Testing Commercial Electricity (150 units):\n";
$resComm = calculateBillWithSlabs($conn, 'Electric', 150.00, 2, '2026-09-01');
// Base = 150 * 8.00 = 1200.00, Fixed = 150.00, Tax 9% of 1350 = 121.50, Total = 1471.50
assertTest("Commercial base is 1200.00", abs($resComm['base_amount'] - 1200.00) < 0.01);
assertTest("Commercial fixed is 150.00", abs($resComm['fixed_charge'] - 150.00) < 0.01);
assertTest("Commercial total is 1471.50", abs($resComm['total_amount'] - 1471.50) < 0.01);

// 4. Test Late Fee Calculation
echo "\n4. Testing Late Fee Engine:\n";
// Scenario A: Within grace period (Due: 2026-08-10, Grace Due: 2026-08-13, Today: 2026-08-12)
$mockBillActive = [
    'Due_Date' => '2026-08-10',
    'Grace_Due_Date' => '2026-08-13',
    'Base_Amount' => 1000.00,
    'Bill_Amount' => 1100.00
];
$feeActive = calculateLateFeeForBill($conn, 'Electric', $mockBillActive, '2026-08-12');
assertTest("Bill within grace period is not overdue", $feeActive['is_overdue'] === false);
assertTest("Late fee is 0.00", $feeActive['late_fee'] === 0.00);

// Scenario B: Overdue past grace period (Due: 2026-08-01, Grace: 2026-08-04, Today: 2026-08-15)
$feeOverdue = calculateLateFeeForBill($conn, 'Electric', $mockBillActive, '2026-08-15');
// 5% of 1000 = 50.00 (min late fee 50.00)
assertTest("Bill past grace period is overdue", $feeOverdue['is_overdue'] === true);
assertTest("Late fee of 50.00 applied", abs($feeOverdue['late_fee'] - 50.00) < 0.01);

echo "\n============================================\n";
echo "TEST RESULTS: Passed: $passCount | Failed: $failCount\n";
echo "============================================\n";
