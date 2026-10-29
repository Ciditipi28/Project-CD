-- NEW FAMILY DATABASE ONLY. Existing records: use import-family.php.
CREATE DATABASE IF NOT EXISTS `carinderease_family_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `carinderease_family_db`;
-- CarinderEase / 20 tables / MySQL 8.0+ or MariaDB 10.4+
-- Import into a NEW database; this file never drops existing data.
SET NAMES utf8mb4;

CREATE TABLE `roles` (
  `role_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_name` VARCHAR(30) NOT NULL,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` INT UNSIGNED NOT NULL,
  `owner_guard` TINYINT GENERATED ALWAYS AS (CASE WHEN role_id=1 THEN 1 ELSE NULL END) STORED,
  UNIQUE KEY uq_single_owner (`owner_guard`),
  `full_name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_categories` (
  `category_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT 1,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_items` (
  `menu_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `item_type` VARCHAR(10) NOT NULL,
  `standard_price` DECIMAL(12,2) NOT NULL,
  `includes_unli_rice` BOOLEAN NOT NULL DEFAULT 0,
  `refill_menu_item_id` INT UNSIGNED NULL DEFAULT NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`menu_item_id`),
  FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`category_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY `uq_name` (`name`),
  FOREIGN KEY (`refill_menu_item_id`) REFERENCES `menu_items` (`menu_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CHECK (standard_price >= 0),
  CHECK (item_type IN ('dish','rice','drink','combo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `daily_menu_items` (
  `daily_menu_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_item_id` INT UNSIGNED NOT NULL,
  `menu_date` DATE NOT NULL,
  `selling_price` DECIMAL(12,2) NOT NULL,
  `prepared_qty` INT UNSIGNED NULL DEFAULT NULL,
  `wasted_qty` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_enabled` BOOLEAN NOT NULL DEFAULT 1,
  `updated_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`daily_menu_item_id`),
  FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`menu_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY uq_composite (`menu_date`, `menu_item_id`),
  CHECK (selling_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `combo_components` (
  `combo_component_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `combo_menu_item_id` INT UNSIGNED NOT NULL,
  `component_menu_item_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`combo_component_id`),
  FOREIGN KEY (`combo_menu_item_id`) REFERENCES `menu_items` (`menu_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`component_menu_item_id`) REFERENCES `menu_items` (`menu_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY uq_composite (`combo_menu_item_id`, `component_menu_item_id`),
  CHECK (quantity > 0),
  CHECK (combo_menu_item_id <> component_menu_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cash_sessions` (
  `cash_session_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `opened_at` DATETIME NOT NULL,
  `closed_at` DATETIME NULL DEFAULT NULL,
  `opening_float` DECIMAL(12,2) NOT NULL,
  `counted_cash` DECIMAL(12,2) NULL DEFAULT NULL,
  `status` VARCHAR(10) NOT NULL DEFAULT 'open',
  `open_guard` TINYINT GENERATED ALWAYS AS (CASE WHEN status = 'open' THEN 1 ELSE NULL END) STORED,
  PRIMARY KEY (`cash_session_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY uq_open_drawer (open_guard),
  CHECK (opening_float >= 0),
  CHECK (counted_cash IS NULL OR counted_cash >= 0),
  CHECK (status IN ('open','closed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sales` (
  `sale_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_number` VARCHAR(30) NOT NULL,
  `checkout_token` VARCHAR(64) NOT NULL,
  `cash_session_id` INT UNSIGNED NOT NULL,
  `sold_at` DATETIME NOT NULL,
  `order_type` VARCHAR(10) NOT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`sale_id`),
  UNIQUE KEY `uq_sale_number` (`sale_number`),
  UNIQUE KEY `uq_checkout_token` (`checkout_token`),
  FOREIGN KEY (`cash_session_id`) REFERENCES `cash_sessions` (`cash_session_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CHECK (total_amount >= 0),
  CHECK (order_type IN ('dine_in','takeout'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sale_items` (
  `sale_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` INT UNSIGNED NOT NULL,
  `daily_menu_item_id` INT UNSIGNED NOT NULL,
  `item_name_snapshot` VARCHAR(100) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `unit_price` DECIMAL(12,2) NOT NULL,
  `unli_rice_granted` BOOLEAN NOT NULL DEFAULT 0,
  `refill_menu_item_id` INT UNSIGNED NULL DEFAULT NULL,
  `refill_closed_at` DATETIME NULL DEFAULT NULL,
  `refill_closed_by` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`sale_item_id`),
  FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`daily_menu_item_id`) REFERENCES `daily_menu_items` (`daily_menu_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`refill_menu_item_id`) REFERENCES `menu_items` (`menu_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`refill_closed_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CHECK (quantity > 0),
  CHECK (unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sale_item_components` (
  `serving_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_item_id` INT UNSIGNED NOT NULL,
  `daily_menu_item_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `serving_type` VARCHAR(10) NOT NULL,
  `operation_token` VARCHAR(64) NOT NULL,
  `served_by` INT UNSIGNED NOT NULL,
  `served_at` DATETIME NOT NULL,
  PRIMARY KEY (`serving_id`),
  FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`sale_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`daily_menu_item_id`) REFERENCES `daily_menu_items` (`daily_menu_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`served_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY uq_composite (`operation_token`, `sale_item_id`, `daily_menu_item_id`, `serving_type`),
  CHECK (quantity > 0),
  CHECK (serving_type IN ('initial','refill'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `payment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` INT UNSIGNED NOT NULL,
  `amount_paid` DECIMAL(12,2) NOT NULL,
  `cash_tendered` DECIMAL(12,2) NOT NULL,
  `change_given` DECIMAL(12,2) NOT NULL,
  `paid_at` DATETIME NOT NULL,
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `uq_sale_id` (`sale_id`),
  FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CHECK (amount_paid >= 0),
  CHECK (cash_tendered >= amount_paid),
  CHECK (change_given = cash_tendered - amount_paid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `units` (
  `unit_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_name` VARCHAR(30) NOT NULL,
  `symbol` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`unit_id`),
  UNIQUE KEY `uq_unit_name` (`unit_name`),
  UNIQUE KEY `uq_symbol` (`symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_items` (
  `inventory_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `reorder_level` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `is_active` BOOLEAN NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`inventory_item_id`),
  FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY `uq_name` (`name`),
  CHECK (reorder_level >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `suppliers` (
  `supplier_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `contact_number` VARCHAR(30) NULL DEFAULT NULL,
  `address` VARCHAR(255) NULL DEFAULT NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT 1,
  PRIMARY KEY (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_receipts` (
  `stock_receipt_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `receipt_token` VARCHAR(64) NOT NULL,
  `supplier_id` INT UNSIGNED NOT NULL,
  `received_by` INT UNSIGNED NOT NULL,
  `received_at` DATETIME NOT NULL,
  `reference_number` VARCHAR(50) NULL DEFAULT NULL,
  `payment_source` VARCHAR(10) NOT NULL,
  `cash_session_id` INT UNSIGNED NULL DEFAULT NULL,
  `status` VARCHAR(10) NOT NULL DEFAULT 'draft',
  `posted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`stock_receipt_id`),
  UNIQUE KEY `uq_receipt_token` (`receipt_token`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`cash_session_id`) REFERENCES `cash_sessions` (`cash_session_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CHECK (status IN ('draft','posted')),
  CHECK (payment_source IN ('owner','drawer'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_receipt_items` (
  `stock_receipt_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stock_receipt_id` INT UNSIGNED NOT NULL,
  `inventory_item_id` INT UNSIGNED NOT NULL,
  `quantity` DECIMAL(12,3) NOT NULL,
  `unit_cost` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`stock_receipt_item_id`),
  FOREIGN KEY (`stock_receipt_id`) REFERENCES `stock_receipts` (`stock_receipt_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`inventory_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY uq_composite (`stock_receipt_id`, `inventory_item_id`),
  CHECK (quantity > 0),
  CHECK (unit_cost >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_movements` (
  `movement_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_item_id` INT UNSIGNED NOT NULL,
  `stock_receipt_item_id` INT UNSIGNED NULL DEFAULT NULL,
  `movement_type` VARCHAR(20) NOT NULL,
  `quantity_change` DECIMAL(12,3) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `operation_token` VARCHAR(64) NOT NULL,
  `recorded_by` INT UNSIGNED NOT NULL,
  `recorded_at` DATETIME NOT NULL,
  PRIMARY KEY (`movement_id`),
  FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`inventory_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY `uq_stock_receipt_item_id` (`stock_receipt_item_id`),
  FOREIGN KEY (`stock_receipt_item_id`) REFERENCES `stock_receipt_items` (`stock_receipt_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY `uq_operation_token` (`operation_token`),
  FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CHECK (quantity_change <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `expense_categories` (
  `expense_category_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT 1,
  PRIMARY KEY (`expense_category_id`),
  UNIQUE KEY `uq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `expenses` (
  `expense_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `expense_category_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `paid_at` DATETIME NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `payment_source` VARCHAR(10) NOT NULL,
  `cash_session_id` INT UNSIGNED NULL DEFAULT NULL,
  `operation_token` VARCHAR(64) NOT NULL,
  `recorded_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`expense_id`),
  FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`expense_category_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`cash_session_id`) REFERENCES `cash_sessions` (`cash_session_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY `uq_operation_token` (`operation_token`),
  FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CHECK (amount > 0),
  CHECK (payment_source IN ('owner','drawer'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `audit_log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `entity_name` VARCHAR(50) NOT NULL,
  `record_id` INT UNSIGNED NOT NULL,
  `old_values` TEXT NULL DEFAULT NULL,
  `new_values` TEXT NULL DEFAULT NULL,
  `reason` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_log_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_sales_date ON sales(sold_at);

CREATE INDEX idx_stock_date ON stock_movements(inventory_item_id, recorded_at);

CREATE INDEX idx_expense_date ON expenses(paid_at);

CREATE INDEX idx_audit_entity ON audit_logs(entity_name,record_id,created_at);

INSERT INTO roles(role_name,description) VALUES ('Owner','Full access'),('Cashier','Counter sales and own session'),('Staff','Kitchen and serving');