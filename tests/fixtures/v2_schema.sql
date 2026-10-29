-- CarinderEase v2. Fresh installation only. Existing databases use upgrade.php.
SET NAMES utf8mb4;

CREATE TABLE `carinderias` (
  `carinderia_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `status` VARCHAR(12) NOT NULL DEFAULT 'pending',
  `review_note` VARCHAR(255) NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`carinderia_id`),
  CHECK (status IN ('pending','approved','suspended','rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `carinderia_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` INT UNSIGNED NOT NULL,
  `email` VARCHAR(190) NULL,
  `email_verified_at` DATETIME NULL,
  `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` DATETIME NULL,
  `last_login` DATETIME NULL,
  `role_name` VARCHAR(20) GENERATED ALWAYS AS (CASE role_id WHEN 1 THEN 'Owner' WHEN 2 THEN 'Cashier' WHEN 3 THEN 'Staff' WHEN 4 THEN 'System Admin' END) STORED,
  `status` VARCHAR(10) GENERATED ALWAYS AS (CASE WHEN is_active=1 THEN 'active' ELSE 'inactive' END) STORED,
  `full_name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`user_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY uq_email (`email`),
  CHECK (role_id IN (1,2,3,4)),
  CHECK ((role_id=4 AND carinderia_id IS NULL) OR (role_id<>4 AND carinderia_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_categories` (
  `carinderia_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT 1,
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`category_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_name` (`carinderia_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_items` (
  `carinderia_id` INT UNSIGNED NOT NULL,
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
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`menu_item_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`menu_item_id`),
  FOREIGN KEY (`carinderia_id`,`category_id`) REFERENCES `menu_categories` (`carinderia_id`,`category_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY `uq_name` (`carinderia_id`,`name`),
  FOREIGN KEY (`carinderia_id`,`refill_menu_item_id`) REFERENCES `menu_items` (`carinderia_id`,`menu_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CHECK (standard_price >= 0),
  CHECK (item_type IN ('dish','rice','drink','combo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `daily_menu_items` (
  `carinderia_id` INT UNSIGNED NOT NULL,
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
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`daily_menu_item_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`daily_menu_item_id`),
  FOREIGN KEY (`carinderia_id`,`menu_item_id`) REFERENCES `menu_items` (`carinderia_id`,`menu_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`carinderia_id`,`updated_by`) REFERENCES `users` (`carinderia_id`,`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY uq_composite (`carinderia_id`,`menu_date`, `menu_item_id`),
  CHECK (selling_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `combo_components` (
  `carinderia_id` INT UNSIGNED NOT NULL,
  `combo_component_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `combo_menu_item_id` INT UNSIGNED NOT NULL,
  `component_menu_item_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`combo_component_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`combo_component_id`),
  FOREIGN KEY (`carinderia_id`,`combo_menu_item_id`) REFERENCES `menu_items` (`carinderia_id`,`menu_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`carinderia_id`,`component_menu_item_id`) REFERENCES `menu_items` (`carinderia_id`,`menu_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY uq_composite (`carinderia_id`,`combo_menu_item_id`, `component_menu_item_id`),
  CHECK (quantity > 0),
  CHECK (combo_menu_item_id <> component_menu_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cash_sessions` (
  `carinderia_id` INT UNSIGNED NOT NULL,
  `cash_session_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `opened_at` DATETIME NOT NULL,
  `closed_at` DATETIME NULL DEFAULT NULL,
  `opening_float` DECIMAL(12,2) NOT NULL,
  `counted_cash` DECIMAL(12,2) NULL DEFAULT NULL,
  `status` VARCHAR(10) NOT NULL DEFAULT 'open',
  `open_guard` TINYINT GENERATED ALWAYS AS (CASE WHEN status = 'open' THEN 1 ELSE NULL END) STORED,
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`cash_session_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`cash_session_id`),
  FOREIGN KEY (`carinderia_id`,`user_id`) REFERENCES `users` (`carinderia_id`,`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY uq_open_drawer (`carinderia_id`,open_guard),
  CHECK (opening_float >= 0),
  CHECK (counted_cash IS NULL OR counted_cash >= 0),
  CHECK (status IN ('open','closed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sales` (
  `carinderia_id` INT UNSIGNED NOT NULL,
  `sale_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_number` VARCHAR(30) NOT NULL,
  `checkout_token` VARCHAR(64) NOT NULL,
  `cash_session_id` INT UNSIGNED NOT NULL,
  `sold_at` DATETIME NOT NULL,
  `order_type` VARCHAR(10) NOT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL,
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`sale_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  `paid_at` DATETIME NOT NULL,
  `cash_tendered` DECIMAL(12,2) NOT NULL,
  `change_given` DECIMAL(12,2) NOT NULL,
  CHECK (cash_tendered >= total_amount),
  CHECK (change_given = cash_tendered-total_amount),
  PRIMARY KEY (`sale_id`),
  UNIQUE KEY `uq_sale_number` (`carinderia_id`,`sale_number`),
  UNIQUE KEY `uq_checkout_token` (`carinderia_id`,`checkout_token`),
  FOREIGN KEY (`carinderia_id`,`cash_session_id`) REFERENCES `cash_sessions` (`carinderia_id`,`cash_session_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CHECK (total_amount >= 0),
  CHECK (order_type IN ('dine_in','takeout'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sale_items` (
  `carinderia_id` INT UNSIGNED NOT NULL,
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
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`sale_item_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`sale_item_id`),
  FOREIGN KEY (`carinderia_id`,`sale_id`) REFERENCES `sales` (`carinderia_id`,`sale_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`carinderia_id`,`daily_menu_item_id`) REFERENCES `daily_menu_items` (`carinderia_id`,`daily_menu_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`carinderia_id`,`refill_menu_item_id`) REFERENCES `menu_items` (`carinderia_id`,`menu_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`carinderia_id`,`refill_closed_by`) REFERENCES `users` (`carinderia_id`,`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CHECK (quantity > 0),
  CHECK (unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sale_item_components` (
  `carinderia_id` INT UNSIGNED NOT NULL,
  `serving_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_item_id` INT UNSIGNED NOT NULL,
  `daily_menu_item_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `serving_type` VARCHAR(10) NOT NULL,
  `operation_token` VARCHAR(64) NOT NULL,
  `served_by` INT UNSIGNED NOT NULL,
  `served_at` DATETIME NOT NULL,
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`serving_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`serving_id`),
  FOREIGN KEY (`carinderia_id`,`sale_item_id`) REFERENCES `sale_items` (`carinderia_id`,`sale_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`carinderia_id`,`daily_menu_item_id`) REFERENCES `daily_menu_items` (`carinderia_id`,`daily_menu_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`carinderia_id`,`served_by`) REFERENCES `users` (`carinderia_id`,`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY uq_composite (`carinderia_id`,`operation_token`, `sale_item_id`, `daily_menu_item_id`, `serving_type`),
  CHECK (quantity > 0),
  CHECK (serving_type IN ('initial','refill'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `units` (
  `carinderia_id` INT UNSIGNED NOT NULL,
  `unit_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_name` VARCHAR(30) NOT NULL,
  `symbol` VARCHAR(10) NOT NULL,
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`unit_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`unit_id`),
  UNIQUE KEY `uq_unit_name` (`carinderia_id`,`unit_name`),
  UNIQUE KEY `uq_symbol` (`carinderia_id`,`symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_items` (
  `carinderia_id` INT UNSIGNED NOT NULL,
  `inventory_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `reorder_level` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `is_active` BOOLEAN NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`inventory_item_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`inventory_item_id`),
  FOREIGN KEY (`carinderia_id`,`unit_id`) REFERENCES `units` (`carinderia_id`,`unit_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY `uq_name` (`carinderia_id`,`name`),
  CHECK (reorder_level >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `suppliers` (
  `carinderia_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `contact_number` VARCHAR(30) NULL DEFAULT NULL,
  `address` VARCHAR(255) NULL DEFAULT NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT 1,
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`supplier_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_receipts` (
  `carinderia_id` INT UNSIGNED NOT NULL,
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
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`stock_receipt_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`stock_receipt_id`),
  UNIQUE KEY `uq_receipt_token` (`carinderia_id`,`receipt_token`),
  FOREIGN KEY (`carinderia_id`,`supplier_id`) REFERENCES `suppliers` (`carinderia_id`,`supplier_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`carinderia_id`,`received_by`) REFERENCES `users` (`carinderia_id`,`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`carinderia_id`,`cash_session_id`) REFERENCES `cash_sessions` (`carinderia_id`,`cash_session_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CHECK (status IN ('draft','posted')),
  CHECK (payment_source IN ('owner','drawer'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_receipt_items` (
  `carinderia_id` INT UNSIGNED NOT NULL,
  `stock_receipt_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stock_receipt_id` INT UNSIGNED NOT NULL,
  `inventory_item_id` INT UNSIGNED NOT NULL,
  `quantity` DECIMAL(12,3) NOT NULL,
  `unit_cost` DECIMAL(12,2) NOT NULL,
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`stock_receipt_item_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`stock_receipt_item_id`),
  FOREIGN KEY (`carinderia_id`,`stock_receipt_id`) REFERENCES `stock_receipts` (`carinderia_id`,`stock_receipt_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`carinderia_id`,`inventory_item_id`) REFERENCES `inventory_items` (`carinderia_id`,`inventory_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY uq_composite (`carinderia_id`,`stock_receipt_id`, `inventory_item_id`),
  CHECK (quantity > 0),
  CHECK (unit_cost >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_movements` (
  `carinderia_id` INT UNSIGNED NOT NULL,
  `movement_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_item_id` INT UNSIGNED NOT NULL,
  `stock_receipt_item_id` INT UNSIGNED NULL DEFAULT NULL,
  `movement_type` VARCHAR(20) NOT NULL,
  `quantity_change` DECIMAL(12,3) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `operation_token` VARCHAR(64) NOT NULL,
  `recorded_by` INT UNSIGNED NOT NULL,
  `recorded_at` DATETIME NOT NULL,
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`movement_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`movement_id`),
  FOREIGN KEY (`carinderia_id`,`inventory_item_id`) REFERENCES `inventory_items` (`carinderia_id`,`inventory_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY `uq_stock_receipt_item_id` (`carinderia_id`,`stock_receipt_item_id`),
  FOREIGN KEY (`carinderia_id`,`stock_receipt_item_id`) REFERENCES `stock_receipt_items` (`carinderia_id`,`stock_receipt_item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY `uq_operation_token` (`carinderia_id`,`operation_token`),
  FOREIGN KEY (`carinderia_id`,`recorded_by`) REFERENCES `users` (`carinderia_id`,`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CHECK (quantity_change <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `expense_categories` (
  `carinderia_id` INT UNSIGNED NOT NULL,
  `expense_category_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT 1,
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`expense_category_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`expense_category_id`),
  UNIQUE KEY `uq_name` (`carinderia_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `expenses` (
  `carinderia_id` INT UNSIGNED NOT NULL,
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
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`expense_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  PRIMARY KEY (`expense_id`),
  FOREIGN KEY (`carinderia_id`,`expense_category_id`) REFERENCES `expense_categories` (`carinderia_id`,`expense_category_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (`carinderia_id`,`cash_session_id`) REFERENCES `cash_sessions` (`carinderia_id`,`cash_session_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  UNIQUE KEY `uq_operation_token` (`carinderia_id`,`operation_token`),
  FOREIGN KEY (`carinderia_id`,`recorded_by`) REFERENCES `users` (`carinderia_id`,`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CHECK (amount > 0),
  CHECK (payment_source IN ('owner','drawer'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `carinderia_id` INT UNSIGNED NULL,
  `audit_log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `entity_name` VARCHAR(50) NOT NULL,
  `record_id` INT UNSIGNED NOT NULL,
  `old_values` TEXT NULL DEFAULT NULL,
  `new_values` TEXT NULL DEFAULT NULL,
  `reason` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_pk (`carinderia_id`,`audit_log_id`),
  FOREIGN KEY (`carinderia_id`) REFERENCES `carinderias` (`carinderia_id`) ON DELETE RESTRICT,
  `ip_address` VARCHAR(45) NULL,
  PRIMARY KEY (`audit_log_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `auth_tokens` (
  `token_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `selector` VARCHAR(64) NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `purpose` VARCHAR(16) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `secret_hash` VARCHAR(255) NOT NULL,
  `payload` TEXT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `consumed_at` DATETIME NULL,
  PRIMARY KEY (`token_id`),
  UNIQUE KEY uq_selector (`selector`),
  KEY idx_token_rate (`email`,`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CHECK (purpose IN ('register','reset','remember','invite','email'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
