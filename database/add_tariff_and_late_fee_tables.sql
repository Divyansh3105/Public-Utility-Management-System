-- Public Utility Management System — Dynamic Tariff Slabs & Late Fee Tables

-- 1. Tariff Categories Table (Domestic, Commercial, Industrial)
CREATE TABLE IF NOT EXISTS `tariff_categories` (
  `category_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `category_code` VARCHAR(50) NOT NULL UNIQUE,
  `category_name` VARCHAR(100) NOT NULL,
  `utility_type` ENUM('Electric', 'Water', 'Both') NOT NULL DEFAULT 'Both',
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed Default Categories
INSERT INTO `tariff_categories` (`category_id`, `category_code`, `category_name`, `utility_type`, `description`, `is_active`) VALUES
(1, 'DOMESTIC', 'Residential / Domestic', 'Both', 'Standard residential household tariff plan with progressive subsidized slabs.', 1),
(2, 'COMMERCIAL', 'Commercial / Retail', 'Both', 'Retail shops, offices, restaurants, and commercial establishments.', 1),
(3, 'INDUSTRIAL', 'Industrial / High Demand', 'Both', 'Manufacturing units, workshops, and large-scale utility consumers.', 1)
ON DUPLICATE KEY UPDATE `category_name` = VALUES(`category_name`);

-- 2. Tariff Slabs Table (Progressive Tiers per Category & Utility)
CREATE TABLE IF NOT EXISTS `tariff_slabs` (
  `slab_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT(11) NOT NULL,
  `utility_type` ENUM('Electric', 'Water') NOT NULL,
  `slab_name` VARCHAR(100) NOT NULL,
  `min_units` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_units` DECIMAL(10,2) NULL, -- NULL means infinity / above threshold
  `rate_per_unit` DECIMAL(10,2) NOT NULL,
  `fixed_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_percent` DECIMAL(5,2) NOT NULL DEFAULT 5.00, -- e.g. 5% Duty/Tax
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_slab_cat_util` (`category_id`, `utility_type`),
  CONSTRAINT `fk_slab_category` FOREIGN KEY (`category_id`) REFERENCES `tariff_categories` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed Default Slabs for Electricity & Water
-- Electric Domestic: 0-100 @ 4.50, 100-300 @ 6.50, >300 @ 8.50 (Fixed charge: 30, 50, 80)
INSERT INTO `tariff_slabs` (`slab_id`, `category_id`, `utility_type`, `slab_name`, `min_units`, `max_units`, `rate_per_unit`, `fixed_charge`, `tax_percent`) VALUES
(1, 1, 'Electric', 'Lifeline / Subsidy Tier', 0.00, 100.00, 4.50, 30.00, 5.00),
(2, 1, 'Electric', 'Standard Domestic Tier', 100.00, 300.00, 6.50, 50.00, 5.00),
(3, 1, 'Electric', 'High Consumption Tier', 300.00, NULL, 8.50, 80.00, 5.00),

-- Electric Commercial: 0-200 @ 8.00, >200 @ 11.00 (Fixed: 150, 250)
(4, 2, 'Electric', 'Small Commercial Tier', 0.00, 200.00, 8.00, 150.00, 9.00),
(5, 2, 'Electric', 'Heavy Commercial Tier', 200.00, NULL, 11.00, 250.00, 9.00),

-- Electric Industrial: 0-500 @ 10.50, >500 @ 13.00 (Fixed: 400, 600)
(6, 3, 'Electric', 'Standard Industrial Tier', 0.00, 500.00, 10.50, 400.00, 12.00),
(7, 3, 'Electric', 'High-Tension Industrial', 500.00, NULL, 13.00, 600.00, 12.00),

-- Water Domestic: 0-10,000 Liters @ 0.30/L, 10,000-25,000 @ 0.50/L, >25,000 @ 0.80/L
(8, 1, 'Water', 'Essential Household Water', 0.00, 10000.00, 0.30, 20.00, 0.00),
(9, 1, 'Water', 'Comfort Usage Tier', 10000.00, 25000.00, 0.50, 40.00, 0.00),
(10, 1, 'Water', 'Excess Usage Tier', 25000.00, NULL, 0.80, 60.00, 0.00),

-- Water Commercial: 0-20,000 Liters @ 0.60/L, >20,000 @ 1.00/L
(11, 2, 'Water', 'Commercial Water Standard', 0.00, 20000.00, 0.60, 100.00, 5.00),
(12, 2, 'Water', 'Commercial High Volume', 20000.00, NULL, 1.00, 180.00, 5.00),

-- Water Industrial: Flat 1.20/L
(13, 3, 'Water', 'Industrial Water Plan', 0.00, NULL, 1.20, 300.00, 5.00)
ON DUPLICATE KEY UPDATE 
  `slab_name` = VALUES(`slab_name`),
  `min_units` = VALUES(`min_units`),
  `max_units` = VALUES(`max_units`),
  `rate_per_unit` = VALUES(`rate_per_unit`),
  `fixed_charge` = VALUES(`fixed_charge`),
  `tax_percent` = VALUES(`tax_percent`);

-- 3. Late Fee & Penalty Rules Table
CREATE TABLE IF NOT EXISTS `late_fee_rules` (
  `rule_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `utility_type` ENUM('Electric', 'Water', 'Both') NOT NULL DEFAULT 'Both',
  `grace_period_days` INT(11) NOT NULL DEFAULT 3,
  `fee_type` ENUM('percentage', 'fixed', 'daily_fixed') NOT NULL DEFAULT 'percentage',
  `fee_value` DECIMAL(10,2) NOT NULL DEFAULT 5.00, -- e.g. 5% or 100 fixed
  `min_late_fee` DECIMAL(10,2) NOT NULL DEFAULT 50.00,
  `max_late_fee` DECIMAL(10,2) NOT NULL DEFAULT 500.00,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed Default Late Fee Rules
INSERT INTO `late_fee_rules` (`rule_id`, `utility_type`, `grace_period_days`, `fee_type`, `fee_value`, `min_late_fee`, `max_late_fee`, `is_active`) VALUES
(1, 'Electric', 3, 'percentage', 5.00, 50.00, 500.00, 1),
(2, 'Water', 3, 'percentage', 5.00, 30.00, 300.00, 1)
ON DUPLICATE KEY UPDATE `grace_period_days` = VALUES(`grace_period_days`);

-- 4. Extend Customer Table with Tariff Category
ALTER TABLE `customer` 
  ADD COLUMN IF NOT EXISTS `Tariff_Category_ID` INT(11) NOT NULL DEFAULT 1 AFTER `House_ID`,
  ADD INDEX IF NOT EXISTS `idx_customer_tariff` (`Tariff_Category_ID`);

-- 5. Extend Electric Bill Table with Slabs & Late Fee Breakdown
ALTER TABLE `electric_bill`
  ADD COLUMN IF NOT EXISTS `Tariff_Category_ID` INT(11) NULL DEFAULT 1 AFTER `House_ID`,
  ADD COLUMN IF NOT EXISTS `Base_Amount` DECIMAL(10,2) NULL AFTER `Rate_per_unit`,
  ADD COLUMN IF NOT EXISTS `Fixed_Charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `Base_Amount`,
  ADD COLUMN IF NOT EXISTS `Tax_Amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `Fixed_Charge`,
  ADD COLUMN IF NOT EXISTS `Late_Fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `Tax_Amount`,
  ADD COLUMN IF NOT EXISTS `Grace_Due_Date` DATE NULL AFTER `Due_Date`,
  ADD COLUMN IF NOT EXISTS `Slab_Breakdown_JSON` TEXT NULL AFTER `Status`;

-- 6. Extend Water Bill Table with Slabs & Late Fee Breakdown
ALTER TABLE `water_bill`
  ADD COLUMN IF NOT EXISTS `Tariff_Category_ID` INT(11) NULL DEFAULT 1 AFTER `House_ID`,
  ADD COLUMN IF NOT EXISTS `Base_Amount` DECIMAL(10,2) NULL AFTER `Rate_per_liter`,
  ADD COLUMN IF NOT EXISTS `Fixed_Charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `Base_Amount`,
  ADD COLUMN IF NOT EXISTS `Tax_Amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `Fixed_Charge`,
  ADD COLUMN IF NOT EXISTS `Late_Fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `Tax_Amount`,
  ADD COLUMN IF NOT EXISTS `Grace_Due_Date` DATE NULL AFTER `Due_Date`,
  ADD COLUMN IF NOT EXISTS `Slab_Breakdown_JSON` TEXT NULL AFTER `Status`;
