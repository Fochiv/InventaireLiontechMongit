-- ============================================================
--  db-config.sql — LionTech Business Manager
--  Fichier SQL unique et consolidé — compatible MySQL 5.7+ / MariaDB 10.3+
--  Toutes les colonnes sont incluses dès la création des tables.
--  Aucun ALTER TABLE ADD COLUMN nécessaire.
--  Dernière mise à jour : 2026
-- ============================================================

CREATE DATABASE IF NOT EXISTS `InventaireLiontech_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `InventaireLiontech_db`;

-- ============================================================
--  SECTION 1 — TABLES PRINCIPALES
-- ============================================================

-- ------------------------------------------------------------
-- Table: businesses
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `businesses` (
  `business_id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_name`           VARCHAR(255)  NOT NULL,
  `business_type`           VARCHAR(100)  DEFAULT NULL,
  `phone`                   VARCHAR(30)   DEFAULT NULL,
  `city`                    VARCHAR(100)  DEFAULT NULL,
  `address`                 TEXT          DEFAULT NULL,
  `country`                 VARCHAR(80)   NOT NULL DEFAULT 'Cameroun',
  `email`                   VARCHAR(255)  DEFAULT NULL,
  `logo_url`                VARCHAR(500)  DEFAULT NULL,
  `disabled`                TINYINT(1)    NOT NULL DEFAULT 0,
  `subscription_status`     ENUM('trial','active','expired','suspended') NOT NULL DEFAULT 'trial',
  `subscription_expires_at` DATETIME      DEFAULT NULL,
  `created_at`              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`business_id`),
  UNIQUE KEY `uq_business_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: users
-- (Toutes colonnes incluses : phone, temporary_pin_plain,
--  force_pin_change, pin_must_change, security_flagged)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`          INT UNSIGNED  DEFAULT NULL,
  `full_name`            VARCHAR(255)  NOT NULL,
  `login_id`             VARCHAR(100)  NOT NULL,
  `email`                VARCHAR(255)  DEFAULT NULL,
  `phone`                VARCHAR(30)   DEFAULT NULL,
  `password_hash`        VARCHAR(255)  NOT NULL,
  `role`                 ENUM('super_admin','business_owner','manager','employee') NOT NULL,
  `status`               ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `security_flagged`     TINYINT(1)    NOT NULL DEFAULT 0,
  `last_login`           DATETIME      DEFAULT NULL,
  `temporary_pin_plain`  VARCHAR(20)   DEFAULT NULL,
  `force_pin_change`     TINYINT(1)    NOT NULL DEFAULT 0,
  `pin_must_change`      TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_login_id` (`login_id`),
  KEY `idx_business_id` (`business_id`),
  KEY `idx_role`         (`role`),
  KEY `idx_status`       (`status`),
  CONSTRAINT `fk_users_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: products / inventaire
-- (Toutes colonnes incluses : barcode, low_stock_level,
--  expiration_date, supplier, image_url, description,
--  status, unit, created_by, updated_at)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `product_id`      INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `business_id`     INT UNSIGNED   NOT NULL,
  `name`            VARCHAR(255)   NOT NULL,
  `sku`             VARCHAR(100)   DEFAULT NULL,
  `barcode`         VARCHAR(150)   DEFAULT NULL,
  `quantity`        DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `unit_price`      DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `low_stock_level` DECIMAL(12,2)  NOT NULL DEFAULT 5.00,
  `expiration_date` DATE           DEFAULT NULL,
  `supplier`        VARCHAR(255)   DEFAULT NULL,
  `image_url`       VARCHAR(500)   DEFAULT NULL,
  `description`     TEXT           DEFAULT NULL,
  `status`          ENUM('active','archived') NOT NULL DEFAULT 'active',
  `category`        VARCHAR(100)   DEFAULT NULL,
  `unit`            VARCHAR(50)    NOT NULL DEFAULT 'piece',
  `created_by`      INT UNSIGNED   DEFAULT NULL,
  `created_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  KEY `idx_business_id`             (`business_id`),
  KEY `idx_products_status`         (`status`),
  KEY `idx_products_category`       (`category`),
  KEY `idx_products_barcode`        (`barcode`),
  KEY `idx_products_business_status`(`business_id`, `status`),
  CONSTRAINT `fk_products_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: attendance
-- (Toutes colonnes GPS et status incluses)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attendance` (
  `attendance_id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`                INT UNSIGNED  NOT NULL,
  `business_id`            INT UNSIGNED  NOT NULL,
  `clock_in`               DATETIME      DEFAULT NULL,
  `clock_in_latitude`      DECIMAL(10,7) DEFAULT NULL,
  `clock_in_longitude`     DECIMAL(10,7) DEFAULT NULL,
  `clock_out`              DATETIME      DEFAULT NULL,
  `clock_out_latitude`     DECIMAL(10,7) DEFAULT NULL,
  `clock_out_longitude`    DECIMAL(10,7) DEFAULT NULL,
  `date`                   DATE          NOT NULL,
  `status`                 ENUM('present','late','absent','pending') NOT NULL DEFAULT 'present',
  `gps_status`             ENUM('on_site','outside_range','rejected','not_checked') NOT NULL DEFAULT 'not_checked',
  `manager_review_required`TINYINT(1)    NOT NULL DEFAULT 0,
  `is_locked`              TINYINT(1)    NOT NULL DEFAULT 1,
  `device_info`            VARCHAR(500)  DEFAULT NULL,
  `ip_address`             VARCHAR(45)   DEFAULT NULL,
  PRIMARY KEY (`attendance_id`),
  KEY `idx_user_date`            (`user_id`, `date`),
  KEY `idx_business_date`        (`business_id`, `date`),
  KEY `idx_attendance_business_date`(`business_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SECTION 2 — AUTHENTIFICATION
-- ============================================================

-- ------------------------------------------------------------
-- Table: login_attempts (protection brute-force)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login_id`     VARCHAR(100) NOT NULL,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_id_time` (`login_id`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: user_sessions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `session_id` VARCHAR(128) NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `ip_address` VARCHAR(45)  NOT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME     NOT NULL,
  PRIMARY KEY (`session_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: security_questions (récupération de compte)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `security_questions` (
  `sq_id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED  NOT NULL,
  `question_1`      VARCHAR(255)  NOT NULL,
  `answer_1_hash`   VARCHAR(255)  NOT NULL,
  `question_2`      VARCHAR(255)  NOT NULL,
  `answer_2_hash`   VARCHAR(255)  NOT NULL,
  `question_3`      VARCHAR(255)  NOT NULL,
  `answer_3_hash`   VARCHAR(255)  NOT NULL,
  `failed_attempts` INT           NOT NULL DEFAULT 0,
  `is_flagged`      TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`sq_id`),
  UNIQUE KEY `uq_sq_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SECTION 3 — SUPER ADMIN
-- ============================================================

-- ------------------------------------------------------------
-- Table: subscriptions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `subscription_id` INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`     INT UNSIGNED  NOT NULL,
  `plan_name`       VARCHAR(100)  NOT NULL DEFAULT 'Standard',
  `amount`          DECIMAL(12,2) NOT NULL DEFAULT 10000.00,
  `currency`        VARCHAR(10)   NOT NULL DEFAULT 'XAF',
  `start_date`      DATE          NOT NULL,
  `end_date`        DATE          NOT NULL,
  `status`          ENUM('trial','active','expired','cancelled') NOT NULL DEFAULT 'trial',
  `renewed_by`      INT UNSIGNED  DEFAULT NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`subscription_id`),
  KEY `idx_business_id` (`business_id`),
  KEY `idx_status`      (`status`),
  KEY `idx_end_date`    (`end_date`),
  CONSTRAINT `fk_subs_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: payments
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id`      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`     INT UNSIGNED  NOT NULL,
  `subscription_id` INT UNSIGNED  DEFAULT NULL,
  `amount`          DECIMAL(12,2) NOT NULL,
  `currency`        VARCHAR(10)   NOT NULL DEFAULT 'XAF',
  `method`          ENUM('mtn_momo','orange_money','bank_transfer','cash') NOT NULL,
  `status`          ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `reference`       VARCHAR(100)  DEFAULT NULL,
  `notes`           TEXT          DEFAULT NULL,
  `paid_at`         DATETIME      DEFAULT NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `idx_business_id`     (`business_id`),
  KEY `idx_subscription_id` (`subscription_id`),
  KEY `idx_status`          (`status`),
  KEY `idx_paid_at`         (`paid_at`),
  CONSTRAINT `fk_pay_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_pay_subscription`
    FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`subscription_id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: activity_logs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `log_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `business_id` INT UNSIGNED DEFAULT NULL,
  `action`      VARCHAR(100) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `icon`        VARCHAR(50)  DEFAULT 'info',
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_user_id`    (`user_id`),
  KEY `idx_business_id`(`business_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_action`     (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Vues : rapports mensuels
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW `v_monthly_revenue` AS
SELECT
  DATE_FORMAT(paid_at, '%Y-%m') AS month,
  SUM(amount)                   AS total_revenue,
  COUNT(*)                      AS payment_count
FROM payments
WHERE status = 'completed'
  AND paid_at IS NOT NULL
GROUP BY DATE_FORMAT(paid_at, '%Y-%m')
ORDER BY month ASC;

CREATE OR REPLACE VIEW `v_monthly_businesses` AS
SELECT
  DATE_FORMAT(created_at, '%Y-%m') AS month,
  COUNT(*)                         AS new_businesses
FROM businesses
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY month ASC;

-- ------------------------------------------------------------
-- Compte super admin — UN SEUL INSERT
-- Login ID : InvenAdmin26
-- Mot de passe : Inventory#Admin126
-- ------------------------------------------------------------
INSERT INTO `users`
  (`business_id`, `full_name`, `login_id`, `email`, `password_hash`, `role`, `status`, `pin_must_change`)
VALUES (
  NULL,
  'LionTechInventoryAdmin',
  'InvenAdmin26',
  'InvenAdmin26',
  '$2y$12$1zBfe7StsnbakHDiD4idieJTTLTip954VMyzUbbp6mzq5yWVltvpi',
  'super_admin',
  'active',
  0
);

-- ============================================================
--  SECTION 4 — GESTION DES EMPLOYÉS
-- ============================================================

-- ------------------------------------------------------------
-- Table: business_features
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `business_features` (
  `feature_id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id`            INT UNSIGNED NOT NULL,
  `inventory_management`   TINYINT(1)   NOT NULL DEFAULT 1,
  `employee_management`    TINYINT(1)   NOT NULL DEFAULT 0,
  `employee_attendance`    TINYINT(1)   NOT NULL DEFAULT 0,
  `sales_tracking`         TINYINT(1)   NOT NULL DEFAULT 0,
  `reports`                TINYINT(1)   NOT NULL DEFAULT 1,
  `low_stock_alerts`       TINYINT(1)   NOT NULL DEFAULT 1,
  `mobile_employee_access` TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`feature_id`),
  UNIQUE KEY `uq_features_business` (`business_id`),
  CONSTRAINT `fk_features_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: employee_profiles
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employee_profiles` (
  `employee_profile_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`             INT UNSIGNED NOT NULL,
  `business_id`         INT UNSIGNED NOT NULL,
  `first_name`          VARCHAR(100) NOT NULL,
  `last_name`           VARCHAR(100) NOT NULL,
  `employee_role`       ENUM('employee','cashier','stock_manager','manager','other') NOT NULL DEFAULT 'employee',
  `job_title`           VARCHAR(150) DEFAULT NULL,
  `profile_photo_url`   VARCHAR(500) DEFAULT NULL,
  `pin_must_change`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`          INT UNSIGNED DEFAULT NULL,
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`employee_profile_id`),
  UNIQUE KEY `uq_employee_profile_user` (`user_id`),
  KEY `idx_employee_business` (`business_id`),
  CONSTRAINT `fk_employee_profile_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_profile_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: business_locations (vérification GPS pointage)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `business_locations` (
  `location_id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`           INT UNSIGNED  NOT NULL,
  `location_name`         VARCHAR(150)  NOT NULL DEFAULT 'Emplacement principal',
  `latitude`              DECIMAL(10,7) DEFAULT NULL,
  `longitude`             DECIMAL(10,7) DEFAULT NULL,
  `allowed_radius_meters` INT UNSIGNED  NOT NULL DEFAULT 200,
  `created_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`location_id`),
  KEY `idx_business_location` (`business_id`),
  CONSTRAINT `fk_location_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: attendance_corrections
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attendance_corrections` (
  `correction_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attendance_id`       INT UNSIGNED NOT NULL,
  `business_id`         INT UNSIGNED NOT NULL,
  `requested_by`        INT UNSIGNED NOT NULL,
  `approved_by`         INT UNSIGNED DEFAULT NULL,
  `original_clock_in`   DATETIME     DEFAULT NULL,
  `original_clock_out`  DATETIME     DEFAULT NULL,
  `requested_clock_in`  DATETIME     DEFAULT NULL,
  `requested_clock_out` DATETIME     DEFAULT NULL,
  `reason`              TEXT         NOT NULL,
  `status`              ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at`         DATETIME     DEFAULT NULL,
  PRIMARY KEY (`correction_id`),
  KEY `idx_attendance_id` (`attendance_id`),
  KEY `idx_business_id`   (`business_id`),
  CONSTRAINT `fk_corr_attendance`
    FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`attendance_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_corr_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SECTION 5 — MOUVEMENTS DE STOCK
-- ============================================================

-- ------------------------------------------------------------
-- Table: stock_movements
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `movement_id`     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `request_id`      INT UNSIGNED  DEFAULT NULL,
  `business_id`     INT UNSIGNED  NOT NULL,
  `product_id`      INT UNSIGNED  NOT NULL,
  `movement_type`   ENUM('initial','stock_in','stock_out','adjustment','damage','loss','sale') NOT NULL,
  `quantity`        DECIMAL(12,2) NOT NULL,
  `reason`          VARCHAR(255)  DEFAULT NULL,
  `supplier`        VARCHAR(255)  DEFAULT NULL,
  `proof_image_url` VARCHAR(500)  DEFAULT NULL,
  `created_by`      INT UNSIGNED  DEFAULT NULL,
  `approved_by`     INT UNSIGNED  DEFAULT NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`movement_id`),
  KEY `idx_stock_business`            (`business_id`),
  KEY `idx_stock_product`             (`product_id`),
  KEY `idx_stock_type`                (`movement_type`),
  KEY `idx_stock_movements_business_date`(`business_id`, `created_at`),
  CONSTRAINT `fk_stock_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SECTION 6 — ENTRÉES DE STOCK
-- ============================================================

CREATE TABLE IF NOT EXISTS `stock_in_requests` (
  `request_id`       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`      INT UNSIGNED  NOT NULL,
  `product_id`       INT UNSIGNED  NOT NULL,
  `quantity`         DECIMAL(12,2) NOT NULL,
  `supplier`         VARCHAR(255)  DEFAULT NULL,
  `delivery_date`    DATE          DEFAULT NULL,
  `note`             TEXT          DEFAULT NULL,
  `proof_image_url`  VARCHAR(500)  DEFAULT NULL,
  `status`           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_by`       INT UNSIGNED  DEFAULT NULL,
  `approved_by`      INT UNSIGNED  DEFAULT NULL,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at`      DATETIME      DEFAULT NULL,
  `rejection_reason` VARCHAR(500)  DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `idx_stock_in_business` (`business_id`),
  KEY `idx_stock_in_product`  (`product_id`),
  KEY `idx_stock_in_status`   (`status`),
  CONSTRAINT `fk_stock_in_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_in_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SECTION 7 — TABLEAU DE BORD PROPRIÉTAIRE
-- ============================================================

CREATE TABLE IF NOT EXISTS `inventory_movements` (
  `movement_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id`   INT UNSIGNED NOT NULL,
  `product_id`    INT UNSIGNED NOT NULL,
  `user_id`       INT UNSIGNED DEFAULT NULL,
  `movement_type` ENUM('stock_in','stock_out','adjustment') NOT NULL,
  `quantity`      INT          NOT NULL,
  `reason`        VARCHAR(150) DEFAULT NULL,
  `notes`         TEXT         DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`movement_id`),
  KEY `idx_move_business`   (`business_id`),
  KEY `idx_move_product`    (`product_id`),
  KEY `idx_move_user`       (`user_id`),
  KEY `idx_move_created_at` (`created_at`),
  CONSTRAINT `fk_move_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_move_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_move_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SECTION 8 — TABLEAU DE BORD EMPLOYÉ
-- ============================================================

CREATE TABLE IF NOT EXISTS `attendance_settings` (
  `setting_id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`        INT           NOT NULL UNIQUE,
  `gps_required`       TINYINT(1)    NOT NULL DEFAULT 1,
  `business_latitude`  DECIMAL(10,7) DEFAULT NULL,
  `business_longitude` DECIMAL(10,7) DEFAULT NULL,
  `gps_radius_meters`  INT           NOT NULL DEFAULT 200,
  `selfie_required`    TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  KEY `idx_attendance_settings_business` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_attendance` (
  `attendance_id`       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`         INT           NOT NULL,
  `user_id`             INT           NOT NULL,
  `clock_in_at`         DATETIME      NOT NULL,
  `clock_out_at`        DATETIME      DEFAULT NULL,
  `clock_in_latitude`   DECIMAL(10,7) DEFAULT NULL,
  `clock_in_longitude`  DECIMAL(10,7) DEFAULT NULL,
  `clock_in_accuracy`   DECIMAL(10,2) DEFAULT NULL,
  `clock_out_latitude`  DECIMAL(10,7) DEFAULT NULL,
  `clock_out_longitude` DECIMAL(10,7) DEFAULT NULL,
  `clock_out_accuracy`  DECIMAL(10,2) DEFAULT NULL,
  `gps_status`          ENUM('on_site','pending_review','rejected_far','no_gps_allowed') NOT NULL DEFAULT 'pending_review',
  `distance_meters`     DECIMAL(10,2) DEFAULT NULL,
  `status`              ENUM('clocked_in','clocked_out','pending_review','rejected') NOT NULL DEFAULT 'clocked_in',
  `note`                TEXT          DEFAULT NULL,
  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`attendance_id`),
  KEY `idx_att_business_user` (`business_id`, `user_id`),
  KEY `idx_att_clock_in`      (`clock_in_at`),
  KEY `idx_att_status`        (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_correction_requests` (
  `request_id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attendance_id`       INT          NOT NULL,
  `business_id`         INT          NOT NULL,
  `requested_by`        INT          NOT NULL,
  `reviewed_by`         INT          DEFAULT NULL,
  `requested_clock_in`  DATETIME     DEFAULT NULL,
  `requested_clock_out` DATETIME     DEFAULT NULL,
  `reason`              TEXT         NOT NULL,
  `status`              ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at`         DATETIME     DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `idx_corr_business`   (`business_id`),
  KEY `idx_corr_attendance` (`attendance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_tasks` (
  `task_id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id` INT          NOT NULL,
  `assigned_to` INT          NOT NULL,
  `assigned_by` INT          DEFAULT NULL,
  `title`       VARCHAR(180) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `status`      ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `due_date`    DATE         DEFAULT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`task_id`),
  KEY `idx_tasks_business_assigned` (`business_id`, `assigned_to`),
  KEY `idx_tasks_status`            (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SECTION 9 — RAPPORTS
-- ============================================================

CREATE TABLE IF NOT EXISTS `report_exports` (
  `export_id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id`   INT          NOT NULL,
  `user_id`       INT          NOT NULL,
  `report_type`   VARCHAR(80)  NOT NULL DEFAULT 'general',
  `date_from`     DATE         DEFAULT NULL,
  `date_to`       DATE         DEFAULT NULL,
  `export_format` VARCHAR(20)  NOT NULL DEFAULT 'csv',
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`export_id`),
  KEY `idx_report_business` (`business_id`),
  KEY `idx_report_user`     (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SECTION 10 — NOTIFICATIONS ET APPROBATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`     INT UNSIGNED  NOT NULL,
  `user_id`         INT UNSIGNED  DEFAULT NULL,
  `title`           VARCHAR(150)  NOT NULL,
  `message`         TEXT          DEFAULT NULL,
  `type`            ENUM('info','warning','danger','success') NOT NULL DEFAULT 'info',
  `is_read`         TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notif_business` (`business_id`),
  KEY `idx_notif_user`     (`user_id`),
  KEY `idx_notif_is_read`  (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `approval_requests` (
  `approval_id`  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`  INT UNSIGNED  NOT NULL,
  `requested_by` INT UNSIGNED  DEFAULT NULL,
  `request_type` ENUM('stock_in','stock_out','attendance_correction') NOT NULL,
  `reference_id` INT UNSIGNED  DEFAULT NULL,
  `details`      TEXT          DEFAULT NULL,
  `status`       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by`  INT UNSIGNED  DEFAULT NULL,
  `approved_at`  DATETIME      DEFAULT NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`approval_id`),
  KEY `idx_approval_business` (`business_id`),
  KEY `idx_approval_status`   (`status`),
  KEY `idx_approval_type`     (`request_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `business_settings` (
  `setting_id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id`            INT UNSIGNED NOT NULL UNIQUE,
  `logo_path`              VARCHAR(255) DEFAULT NULL,
  `brand_color`            VARCHAR(20)  NOT NULL DEFAULT '#0B1F3A',
  `language`               VARCHAR(10)  NOT NULL DEFAULT 'fr',
  `currency`               VARCHAR(10)  NOT NULL DEFAULT 'XAF',
  `gps_radius_meters`      INT          NOT NULL DEFAULT 200,
  `require_stock_approval` TINYINT(1)   NOT NULL DEFAULT 1,
  `updated_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  KEY `idx_settings_business` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SECTION 11 — SORTIES DE STOCK
-- ============================================================

CREATE TABLE IF NOT EXISTS `stock_out_requests` (
  `request_id`       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`      INT UNSIGNED  NOT NULL,
  `product_id`       INT UNSIGNED  NOT NULL,
  `quantity`         DECIMAL(12,2) NOT NULL,
  `reason`           VARCHAR(255)  NOT NULL,
  `recipient`        VARCHAR(255)  DEFAULT NULL,
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

-- Table alternative pour sorties (utilisée par certaines vues)
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
  KEY `idx_stock_out2_business` (`business_id`),
  KEY `idx_stock_out2_product`  (`product_id`),
  KEY `idx_stock_out2_status`   (`status`),
  CONSTRAINT `fk_stock_out2_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_out2_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SECTION 12 — VENTES
-- ============================================================

CREATE TABLE IF NOT EXISTS `sales` (
  `sale_id`        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`    INT UNSIGNED  NOT NULL,
  `product_id`     INT UNSIGNED  DEFAULT NULL,
  `quantity`       DECIMAL(12,2) NOT NULL DEFAULT 1,
  `unit_price`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total`          DECIMAL(14,2) NOT NULL DEFAULT 0,
  `payment_method` VARCHAR(50)   DEFAULT NULL,
  `created_by`     INT UNSIGNED  DEFAULT NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sale_id`),
  KEY `idx_sales_business`   (`business_id`),
  KEY `idx_sales_product`    (`product_id`),
  KEY `idx_sales_created_at` (`created_at`),
  CONSTRAINT `fk_sales_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SECTION 13 — CODES PIN EMPLOYÉS
-- ============================================================

CREATE TABLE IF NOT EXISTS `pin_codes` (
  `pin_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `business_id` INT UNSIGNED NOT NULL,
  `pin_hash`    VARCHAR(255) NOT NULL,
  `must_change` TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pin_id`),
  UNIQUE KEY `uq_pin_user` (`user_id`),
  CONSTRAINT `fk_pin_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SECTION 14 — PAIEMENTS (SYSTÈME LIONTECH)
-- ============================================================

-- Paramètres de paiement (gérés par le super admin)
CREATE TABLE IF NOT EXISTS `payment_settings` (
  `setting_id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `orange_money_number` VARCHAR(30)   DEFAULT NULL,
  `orange_money_name`   VARCHAR(100)  DEFAULT NULL,
  `mtn_momo_number`     VARCHAR(30)   DEFAULT NULL,
  `mtn_momo_name`       VARCHAR(100)  DEFAULT NULL,
  `bank_name`           VARCHAR(150)  DEFAULT NULL,
  `bank_account_number` VARCHAR(100)  DEFAULT NULL,
  `bank_account_holder` VARCHAR(150)  DEFAULT NULL,
  `bank_branch`         VARCHAR(150)  DEFAULT NULL,
  `updated_by_name`     VARCHAR(150)  DEFAULT NULL,
  `updated_by_user_id`  INT UNSIGNED  DEFAULT NULL,
  `updated_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ligne par défaut (vide)
INSERT INTO `payment_settings`
  (`orange_money_number`, `mtn_momo_number`, `bank_name`)
VALUES ('', '', '')
ON DUPLICATE KEY UPDATE `setting_id` = `setting_id`;

-- Journal d'audit des paramètres de paiement
CREATE TABLE IF NOT EXISTS `payment_settings_log` (
  `log_id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `changed_by_name` VARCHAR(150)  NOT NULL,
  `user_id`         INT UNSIGNED  DEFAULT NULL,
  `field_changed`   VARCHAR(100)  NOT NULL,
  `old_value`       VARCHAR(255)  DEFAULT NULL,
  `new_value`       VARCHAR(255)  DEFAULT NULL,
  `ip_address`      VARCHAR(45)   DEFAULT NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paiements soumis par les propriétaires
CREATE TABLE IF NOT EXISTS `liontech_payments` (
  `payment_id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`           INT UNSIGNED  NOT NULL,
  `amount`                DECIMAL(12,2) NOT NULL,
  `months_paid`           INT           NOT NULL DEFAULT 1,
  `payment_method`        ENUM('orange_money','mtn_momo','bank_transfer','cash') NOT NULL,
  `transaction_reference` VARCHAR(150)  DEFAULT NULL,
  `proof_image_url`       VARCHAR(500)  DEFAULT NULL,
  `status`                ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason`      VARCHAR(255)  DEFAULT NULL,
  `rejection_detail`      TEXT          DEFAULT NULL,
  `submitted_by`          INT UNSIGNED  NOT NULL,
  `approved_by`           INT UNSIGNED  DEFAULT NULL,
  `whatsapp_sent`         TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at`           DATETIME      DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `uq_transaction_ref` (`transaction_reference`),
  KEY `idx_payment_business` (`business_id`),
  KEY `idx_payment_status`   (`status`),
  KEY `idx_payment_method`   (`payment_method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  FIN DU FICHIER db-config.sql
--  Pour vérifier : SELECT table_name FROM information_schema.tables
--                  WHERE table_schema='InventaireLiontech_db'
--                  ORDER BY table_name;
-- ============================================================
