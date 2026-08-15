-- Public Utility Management System — Database Indexes & Unified Views

-- Unified Bills View
CREATE OR REPLACE VIEW `vw_all_bills` AS
SELECT 'Electric' AS Bill_Type, Bill_ID, Customer_ID, House_ID, Units_Consumed AS Consumption, Rate_per_unit AS Rate, Bill_Amount, Due_Date, Status
FROM electric_bill
UNION ALL
SELECT 'Water' AS Bill_Type, Bill_ID, Customer_ID, House_ID, Consumption_Liters AS Consumption, Rate_per_liter AS Rate, Bill_Amount, Due_Date, Status
FROM water_bill;

-- Indexes for Customer Lookups
ALTER TABLE `customer` ADD INDEX IF NOT EXISTS `idx_customer_email` (`Email`);
ALTER TABLE `customer` ADD INDEX IF NOT EXISTS `idx_customer_phone` (`Phone`);

-- Indexes for Employee Lookups
ALTER TABLE `employee` ADD INDEX IF NOT EXISTS `idx_employee_phone` (`Phone`);

-- Indexes for Electric & Water Bills
ALTER TABLE `electric_bill` ADD INDEX IF NOT EXISTS `idx_ebill_customer` (`Customer_ID`);
ALTER TABLE `electric_bill` ADD INDEX IF NOT EXISTS `idx_ebill_status` (`Status`);
ALTER TABLE `electric_bill` ADD INDEX IF NOT EXISTS `idx_ebill_due_date` (`Due_Date`);

ALTER TABLE `water_bill` ADD INDEX IF NOT EXISTS `idx_wbill_customer` (`Customer_ID`);
ALTER TABLE `water_bill` ADD INDEX IF NOT EXISTS `idx_wbill_status` (`Status`);
ALTER TABLE `water_bill` ADD INDEX IF NOT EXISTS `idx_wbill_due_date` (`Due_Date`);

-- Indexes for Payments
ALTER TABLE `payment` ADD INDEX IF NOT EXISTS `idx_payment_bill` (`Bill_Type`, `Bill_ID`);
ALTER TABLE `payment` ADD INDEX IF NOT EXISTS `idx_payment_date` (`Date_of_Payment`);
