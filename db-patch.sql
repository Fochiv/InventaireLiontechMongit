-- ============================================================
-- db-patch.sql — LionTech Business Manager
-- Database patches for schema mismatches
-- Run this ONCE on your MySQL/MariaDB after the main db-config.sql
-- ============================================================

-- ------------------------------------------------------------
-- PATCH 1: Add missing columns to `users` table
-- ------------------------------------------------------------
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `phone`               VARCHAR(30)   DEFAULT NULL AFTER `email`,
  ADD COLUMN IF NOT EXISTS `temporary_pin_plain` VARCHAR(20)   DEFAULT NULL AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `force_pin_change`    TINYINT(1)    NOT NULL DEFAULT 0 AFTER `temporary_pin_plain`;

-- ------------------------------------------------------------
-- PATCH 2: Add missing columns to `businesses` table
-- ------------------------------------------------------------
ALTER TABLE `businesses`
  ADD COLUMN IF NOT EXISTS `business_type`           VARCHAR(100)  DEFAULT NULL AFTER `business_name`,
  ADD COLUMN IF NOT EXISTS `phone`                   VARCHAR(30)   DEFAULT NULL AFTER `business_type`,
  ADD COLUMN IF NOT EXISTS `city`                    VARCHAR(100)  DEFAULT NULL AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `address`                 VARCHAR(255)  DEFAULT NULL AFTER `city`,
  ADD COLUMN IF NOT EXISTS `country`                 VARCHAR(80)   DEFAULT 'Cameroun' AFTER `address`,
  ADD COLUMN IF NOT EXISTS `subscription_status`     ENUM('trial','active','expired','suspended') NOT NULL DEFAULT 'trial' AFTER `country`,
  ADD COLUMN IF NOT EXISTS `subscription_expires_at` DATETIME      DEFAULT NULL AFTER `subscription_status`,
  ADD COLUMN IF NOT EXISTS `updated_at`              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- ------------------------------------------------------------
-- PATCH 3: Add `status` column to `attendance` (late/present/absent)
-- ------------------------------------------------------------
ALTER TABLE `attendance`
  ADD COLUMN IF NOT EXISTS `status` ENUM('present','late','absent','pending') NOT NULL DEFAULT 'present' AFTER `date`;

-- ------------------------------------------------------------
-- PATCH 4: Add `unit` column to `products` if not already present
-- ------------------------------------------------------------
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `unit` VARCHAR(50) NOT NULL DEFAULT 'piece' AFTER `category`;

-- ------------------------------------------------------------
-- PATCH 5: Create `stock_out` table (if not using stock_movements)
-- This mirrors `stock_in_requests` for outgoing stock requests.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_out` (
  `request_id`       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`      INT UNSIGNED  NOT NULL,
  `product_id`       INT UNSIGNED  NOT NULL,
  `quantity`         DECIMAL(12,2) NOT NULL,
  `reason`           VARCHAR(255)  DEFAULT NULL,
  `note`             TEXT          DEFAULT NULL,
  `proof_image_url`  VARCHAR(500)  DEFAULT NULL,
  `status`           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_by`       INT UNSIGNED  DEFAULT NULL,
  `approved_by`      INT UNSIGNED  DEFAULT NULL,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at`      DATETIME      DEFAULT NULL,
  `rejection_reason` VARCHAR(500)  DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `idx_stock_out_business` (`business_id`),
  KEY `idx_stock_out_product`  (`product_id`),
  KEY `idx_stock_out_status`   (`status`),
  CONSTRAINT `fk_stock_out_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_out_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PATCH 6: Create `sales` table (used by some report queries)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
  `sale_id`     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id` INT UNSIGNED  NOT NULL,
  `product_id`  INT UNSIGNED  DEFAULT NULL,
  `quantity`    DECIMAL(12,2) NOT NULL DEFAULT 1,
  `unit_price`  DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total`       DECIMAL(14,2) NOT NULL DEFAULT 0,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `created_by`  INT UNSIGNED  DEFAULT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sale_id`),
  KEY `idx_sales_business`  (`business_id`),
  KEY `idx_sales_product`   (`product_id`),
  KEY `idx_sales_created_at`(`created_at`),
  CONSTRAINT `fk_sales_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PATCH 7: Ensure `activity_logs` has all columns
-- ------------------------------------------------------------
ALTER TABLE `activity_logs`
  ADD COLUMN IF NOT EXISTS `icon`       VARCHAR(50) DEFAULT 'info' AFTER `description`,
  ADD COLUMN IF NOT EXISTS `ip_address` VARCHAR(45) DEFAULT NULL AFTER `icon`;

-- ------------------------------------------------------------
-- PATCH 8: Fix `payments` table — add `notes` column if missing
-- ------------------------------------------------------------
ALTER TABLE `payments`
  ADD COLUMN IF NOT EXISTS `notes` TEXT DEFAULT NULL AFTER `reference`;

-- ------------------------------------------------------------
-- PATCH 9: Ensure `products` has `image_url` and `low_stock_level`
-- (These were in db-config.sql ALTER TABLE but just in case)
-- ------------------------------------------------------------
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `image_url`       VARCHAR(500)  DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `low_stock_level` DECIMAL(12,2) NOT NULL DEFAULT 5.00,
  ADD COLUMN IF NOT EXISTS `status`          ENUM('active','archived') NOT NULL DEFAULT 'active';

-- ------------------------------------------------------------
-- PATCH 10: Create `pin_codes` table for employee PIN authentication
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pin_codes` (
  `pin_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `business_id`  INT UNSIGNED NOT NULL,
  `pin_hash`     VARCHAR(255) NOT NULL,
  `must_change`  TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pin_id`),
  UNIQUE KEY `uq_pin_user` (`user_id`),
  CONSTRAINT `fk_pin_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- END OF PATCH FILE
-- To verify: SELECT table_name, column_name FROM information_schema.columns
--            WHERE table_schema='InventaireLiontech_db'
--            ORDER BY table_name, ordinal_position;
-- ============================================================
