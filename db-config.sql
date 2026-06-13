-- ============================================================
--  db-config.sql — LionTech Business Manager
--  CLEAN VERSION — all columns consolidated in CREATE TABLE
--  Run once on a fresh InventaireLiontech_db
--  Compatible MySQL 5.7+ · UTF-8
-- ============================================================

CREATE DATABASE IF NOT EXISTS `InventaireLiontech_db`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `InventaireLiontech_db`;

-- ============================================================
--  BUSINESSES
-- ============================================================
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

-- ============================================================
--  USERS  (caissier role included)
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`          INT UNSIGNED  DEFAULT NULL,
  `full_name`            VARCHAR(255)  NOT NULL,
  `login_id`             VARCHAR(100)  NOT NULL,
  `email`                VARCHAR(255)  DEFAULT NULL,
  `phone`                VARCHAR(30)   DEFAULT NULL,
  `password_hash`        VARCHAR(255)  NOT NULL,
  `role`                 ENUM('super_admin','business_owner','manager','employee','caissier') NOT NULL DEFAULT 'employee',
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
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_users_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Super admin
INSERT IGNORE INTO `users`
  (`business_id`,`full_name`,`login_id`,`email`,`password_hash`,`role`,`status`,`pin_must_change`)
VALUES (NULL,'LionTechInventoryAdmin','InvenAdmin26','InvenAdmin26',
  '$2y$12$1zBfe7StsnbakHDiD4idieJTTLTip954VMyzUbbp6mzq5yWVltvpi','super_admin','active',0);

-- ============================================================
--  PRODUCTS  (cost_price included)
-- ============================================================
CREATE TABLE IF NOT EXISTS `products` (
  `product_id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `business_id`            INT UNSIGNED   NOT NULL,
  `name`                   VARCHAR(255)   NOT NULL,
  `sku`                    VARCHAR(100)   DEFAULT NULL,
  `barcode`                VARCHAR(150)   DEFAULT NULL,
  `quantity`               DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `unit_price`             DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `cost_price`             DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `cost_price_updated_by`  INT UNSIGNED   DEFAULT NULL,
  `cost_price_updated_at`  DATETIME       DEFAULT NULL,
  `low_stock_level`        DECIMAL(12,2)  NOT NULL DEFAULT 5.00,
  `expiration_date`        DATE           DEFAULT NULL,
  `supplier`               VARCHAR(255)   DEFAULT NULL,
  `image_url`              VARCHAR(500)   DEFAULT NULL,
  `description`            TEXT           DEFAULT NULL,
  `status`                 ENUM('active','archived') NOT NULL DEFAULT 'active',
  `category`               VARCHAR(100)   DEFAULT NULL,
  `unit`                   VARCHAR(50)    NOT NULL DEFAULT 'piece',
  `created_by`             INT UNSIGNED   DEFAULT NULL,
  `created_at`             DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  KEY `idx_products_business_status` (`business_id`,`status`),
  KEY `idx_products_barcode` (`barcode`),
  CONSTRAINT `fk_products_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  BUSINESS SETTINGS  (tva, caisse_code, manager_perms)
-- ============================================================
CREATE TABLE IF NOT EXISTS `business_settings` (
  `setting_id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id`            INT UNSIGNED NOT NULL,
  `logo_path`              VARCHAR(255) DEFAULT NULL,
  `brand_color`            VARCHAR(20)  NOT NULL DEFAULT '#0B1F3A',
  `language`               VARCHAR(10)  NOT NULL DEFAULT 'en',
  `currency`               VARCHAR(10)  NOT NULL DEFAULT 'XAF',
  `gps_radius_meters`      INT          NOT NULL DEFAULT 200,
  `require_stock_approval` TINYINT(1)   NOT NULL DEFAULT 1,
  `tva_enabled`            TINYINT(1)   NOT NULL DEFAULT 0,
  `tva_rate`               DECIMAL(5,2) NOT NULL DEFAULT 19.25,
  `caisse_code`            VARCHAR(10)  DEFAULT NULL COMMENT '4-digit code for employee caisse access',
  `manager_vente_perms`    TEXT         DEFAULT NULL COMMENT 'JSON: what manager can see on Vente.php',
  `updated_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `uq_settings_business` (`business_id`),
  KEY `idx_settings_business` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  STOCK IN REQUESTS  (cost_price + potential columns)
-- ============================================================
CREATE TABLE IF NOT EXISTS `stock_in_requests` (
  `request_id`        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`       INT UNSIGNED  NOT NULL,
  `product_id`        INT UNSIGNED  NOT NULL,
  `quantity`          DECIMAL(12,2) NOT NULL,
  `cost_price`        DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Purchase price per unit at delivery',
  `potential_revenue` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'qty × sell_price',
  `potential_profit`  DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'qty × (sell_price - cost_price)',
  `supplier`          VARCHAR(255)  DEFAULT NULL,
  `delivery_date`     DATE          DEFAULT NULL,
  `note`              TEXT          DEFAULT NULL,
  `proof_image_url`   VARCHAR(500)  DEFAULT NULL,
  `status`            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_by`        INT UNSIGNED  DEFAULT NULL,
  `approved_by`       INT UNSIGNED  DEFAULT NULL,
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at`       DATETIME      DEFAULT NULL,
  `rejection_reason`  VARCHAR(500)  DEFAULT NULL,
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
--  STOCK OUT REQUESTS  (movement_type, broken_qty, loss_amount)
-- ============================================================
CREATE TABLE IF NOT EXISTS `stock_out_requests` (
  `request_id`       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`      INT UNSIGNED  NOT NULL,
  `product_id`       INT UNSIGNED  NOT NULL,
  `quantity`         DECIMAL(12,2) NOT NULL,
  `reason`           VARCHAR(255)  NOT NULL,
  `movement_type`    ENUM('normal','broken','lost') NOT NULL DEFAULT 'normal',
  `broken_qty`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `loss_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'qty × unit_price for broken/lost',
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

-- ============================================================
--  CAISSE — sessions_caisse
-- ============================================================
CREATE TABLE IF NOT EXISTS `sessions_caisse` (
  `session_id`     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`    INT UNSIGNED  NOT NULL,
  `caissier_id`    INT UNSIGNED  NOT NULL,
  `ouverture_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fermeture_at`   DATETIME      DEFAULT NULL,
  `fond_ouverture` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_ventes`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_remb`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_especes`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_mtn`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_orange`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `rapport_envoye` TINYINT(1)    NOT NULL DEFAULT 0,
  `statut`         ENUM('ouverte','fermee') NOT NULL DEFAULT 'ouverte',
  PRIMARY KEY (`session_id`),
  KEY `idx_sc_business` (`business_id`),
  KEY `idx_sc_caissier` (`caissier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ============================================================
--  CAISSE — transactions_caisse  (offline_id included)
-- ============================================================
CREATE TABLE IF NOT EXISTS `transactions_caisse` (
  `transaction_id`  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `business_id`     INT UNSIGNED  NOT NULL,
  `session_id`      INT UNSIGNED  NULL DEFAULT NULL,
  `caissier_id`     INT UNSIGNED  NOT NULL,
  `numero_facture`  VARCHAR(20)   NOT NULL,
  `type_operation`  ENUM('vente','remboursement','abime') NOT NULL DEFAULT 'vente',
  `sous_total`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `remise_type`     ENUM('aucune','pourcentage','fixe') NOT NULL DEFAULT 'aucune',
  `remise_valeur`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `remise_montant`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tva_active`      TINYINT(1)    NOT NULL DEFAULT 0,
  `tva_taux`        DECIMAL(5,2)  NOT NULL DEFAULT 19.25,
  `tva_montant`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_ttc`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `montant_recu`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `monnaie_rendue`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `client_nom`      VARCHAR(255)  DEFAULT NULL,
  `client_phone`    VARCHAR(30)   DEFAULT NULL,
  `note`            TEXT          DEFAULT NULL,
  `offline_id`      VARCHAR(100)  DEFAULT NULL,
  `statut`          ENUM('validee','pending_remb','remb_validee','remb_rejetee','pending_abime','abime_validee') NOT NULL DEFAULT 'validee',
  `transaction_ref` INT UNSIGNED  DEFAULT NULL,
  `validee_par`     INT UNSIGNED  DEFAULT NULL,
  `validee_at`      DATETIME      DEFAULT NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `uq_numero_facture` (`business_id`,`numero_facture`),
  KEY `idx_tc_business` (`business_id`),
  KEY `idx_tc_session`  (`session_id`),
  KEY `idx_tc_caissier` (`caissier_id`),
  KEY `idx_tc_statut`   (`statut`),
  KEY `idx_tc_date`     (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ============================================================
--  CAISSE — items_transaction
-- ============================================================
CREATE TABLE IF NOT EXISTS `items_transaction` (
  `item_id`        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `transaction_id` INT UNSIGNED  NOT NULL,
  `product_id`     INT UNSIGNED  NOT NULL,
  `product_name`   VARCHAR(255)  NOT NULL,
  `product_sku`    VARCHAR(100)  DEFAULT NULL,
  `quantite`       DECIMAL(12,2) NOT NULL,
  `prix_unitaire`  DECIMAL(12,2) NOT NULL,
  `total_ligne`    DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`item_id`),
  KEY `idx_it_transaction` (`transaction_id`),
  KEY `idx_it_product`     (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ============================================================
--  CAISSE — paiements_mixtes
-- ============================================================
CREATE TABLE IF NOT EXISTS `paiements_mixtes` (
  `paiement_id`    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `transaction_id` INT UNSIGNED  NOT NULL,
  `mode`           ENUM('especes','mtn_momo','orange_money') NOT NULL,
  `montant`        DECIMAL(12,2) NOT NULL,
  `reference`      VARCHAR(100)  DEFAULT NULL,
  PRIMARY KEY (`paiement_id`),
  KEY `idx_pm_transaction` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ============================================================
--  CAISSE — preuves_abime
-- ============================================================
CREATE TABLE IF NOT EXISTS `preuves_abime` (
  `preuve_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_id` INT UNSIGNED NOT NULL,
  `photo_url`      VARCHAR(500) NOT NULL,
  `uploaded_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`preuve_id`),
  KEY `idx_pa_transaction` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ============================================================
--  CAISSE — facture_sequence
-- ============================================================
CREATE TABLE IF NOT EXISTS `facture_sequence` (
  `business_id` INT UNSIGNED NOT NULL,
  `annee`       YEAR         NOT NULL,
  `dernier_num` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`business_id`,`annee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ============================================================
--  PIN CODES
-- ============================================================
CREATE TABLE IF NOT EXISTS `pin_codes` (
  `pin_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `business_id` INT UNSIGNED NOT NULL,
  `pin_hash`    VARCHAR(255) NOT NULL,
  `must_change` TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pin_id`),
  UNIQUE KEY `uq_pin_user` (`user_id`),
  CONSTRAINT `fk_pin_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ============================================================
--  ATTENDANCE
-- ============================================================
CREATE TABLE IF NOT EXISTS `attendance` (
  `attendance_id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`                 INT UNSIGNED  NOT NULL,
  `business_id`             INT UNSIGNED  NOT NULL,
  `clock_in`                DATETIME      DEFAULT NULL,
  `clock_in_latitude`       DECIMAL(10,7) DEFAULT NULL,
  `clock_in_longitude`      DECIMAL(10,7) DEFAULT NULL,
  `clock_out`               DATETIME      DEFAULT NULL,
  `clock_out_latitude`      DECIMAL(10,7) DEFAULT NULL,
  `clock_out_longitude`     DECIMAL(10,7) DEFAULT NULL,
  `date`                    DATE          NOT NULL,
  `status`                  ENUM('present','late','absent','pending') NOT NULL DEFAULT 'present',
  `gps_status`              ENUM('on_site','outside_range','rejected','not_checked') NOT NULL DEFAULT 'not_checked',
  `manager_review_required` TINYINT(1)    NOT NULL DEFAULT 0,
  `is_locked`               TINYINT(1)    NOT NULL DEFAULT 1,
  `device_info`             VARCHAR(500)  DEFAULT NULL,
  `ip_address`              VARCHAR(45)   DEFAULT NULL,
  PRIMARY KEY (`attendance_id`),
  KEY `idx_user_date`     (`user_id`,`date`),
  KEY `idx_business_date` (`business_id`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id`     INT UNSIGNED NOT NULL,
  `user_id`         INT UNSIGNED DEFAULT NULL,
  `title`           VARCHAR(150) NOT NULL,
  `message`         TEXT         DEFAULT NULL,
  `type`            ENUM('info','warning','danger','success') NOT NULL DEFAULT 'info',
  `is_read`         TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notif_business` (`business_id`),
  KEY `idx_notif_user`     (`user_id`),
  KEY `idx_notif_is_read`  (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  ACTIVITY LOGS
-- ============================================================
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
  KEY `idx_activity_business` (`business_id`),
  KEY `idx_activity_date`     (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  STOCK MOVEMENTS (log)
-- ============================================================
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
  KEY `idx_sm_business` (`business_id`),
  KEY `idx_sm_product`  (`product_id`),
  CONSTRAINT `fk_sm_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sm_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  BUSINESS FEATURES
-- ============================================================
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
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SUBSCRIPTIONS & PAYMENTS
-- ============================================================
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
  PRIMARY KEY (`subscription_id`),
  KEY `idx_subs_business` (`business_id`),
  CONSTRAINT `fk_subs_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `approval_requests` (
  `approval_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id`  INT UNSIGNED NOT NULL,
  `requested_by` INT UNSIGNED DEFAULT NULL,
  `request_type` ENUM('stock_in','stock_out','attendance_correction') NOT NULL,
  `reference_id` INT UNSIGNED DEFAULT NULL,
  `details`      TEXT         DEFAULT NULL,
  `status`       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by`  INT UNSIGNED DEFAULT NULL,
  `approved_at`  DATETIME     DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`approval_id`),
  KEY `idx_approval_business` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `work_schedules` (
  `schedule_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `business_id` INT UNSIGNED NOT NULL,
  `monday`      TINYINT(1) NOT NULL DEFAULT 0,
  `tuesday`     TINYINT(1) NOT NULL DEFAULT 0,
  `wednesday`   TINYINT(1) NOT NULL DEFAULT 0,
  `thursday`    TINYINT(1) NOT NULL DEFAULT 0,
  `friday`      TINYINT(1) NOT NULL DEFAULT 0,
  `saturday`    TINYINT(1) NOT NULL DEFAULT 0,
  `sunday`      TINYINT(1) NOT NULL DEFAULT 0,
  `start_time`  TIME         DEFAULT NULL,
  `end_time`    TIME         DEFAULT NULL,
  `notes`       VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  UNIQUE KEY `uq_schedule_user` (`user_id`),
  CONSTRAINT `fk_schedule_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_schedule_business`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

---- receipt /feacture system.
CREATE TABLE IF NOT EXISTS receipt_settings (
  setting_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  brand_name VARCHAR(255) DEFAULT NULL,
  logo_url VARCHAR(500) DEFAULT NULL,
  brand_color VARCHAR(20) NOT NULL DEFAULT '#0B1F3A',
  return_policy TEXT DEFAULT NULL,
  footer_message TEXT DEFAULT NULL,
  show_cashier TINYINT(1) NOT NULL DEFAULT 1,
  show_client_phone TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_id),
  UNIQUE KEY uq_receipt_settings_business (business_id)
);

CREATE TABLE IF NOT EXISTS receipts (
  receipt_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  transaction_id INT UNSIGNED NOT NULL,
  receipt_number VARCHAR(50) NOT NULL,
  client_name VARCHAR(255) DEFAULT NULL,
  client_phone VARCHAR(30) DEFAULT NULL,
  cashier_id INT UNSIGNED DEFAULT NULL,
  cashier_name VARCHAR(255) DEFAULT NULL,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  receipt_snapshot JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (receipt_id),
  UNIQUE KEY uq_receipt_transaction (transaction_id),
  KEY idx_receipt_business_phone (business_id, client_phone),
  KEY idx_receipt_number (receipt_number)
);
-----owner receipt custom-----
CREATE TABLE IF NOT EXISTS receipt_settings (
  setting_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  brand_name VARCHAR(255) DEFAULT NULL,
  logo_url VARCHAR(500) DEFAULT NULL,
  brand_color VARCHAR(20) NOT NULL DEFAULT '#0B1F3A',
  return_policy TEXT DEFAULT NULL,
  footer_message TEXT DEFAULT NULL,
  show_cashier TINYINT(1) NOT NULL DEFAULT 1,
  show_client_phone TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_id),
  UNIQUE KEY uq_receipt_settings_business (business_id)
);




----- partie client------
-- 1. Client accounts (new table)
CREATE TABLE IF NOT EXISTS clients (
    client_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(255) NOT NULL,
    phone         VARCHAR(30)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    qr_token      VARCHAR(100) NOT NULL,
    account_status ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (client_id),
    UNIQUE KEY uq_client_phone (phone),
    UNIQUE KEY uq_client_qr   (qr_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Client receipt actions (new table)
CREATE TABLE IF NOT EXISTS client_receipt_actions (
    action_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id    INT UNSIGNED DEFAULT NULL,
    client_phone VARCHAR(30)  NOT NULL,
    receipt_id   INT UNSIGNED NOT NULL,
    business_id  INT UNSIGNED NOT NULL,
    is_saved     TINYINT(1)   NOT NULL DEFAULT 0,
    is_hidden    TINYINT(1)   NOT NULL DEFAULT 0,
    is_reported  TINYINT(1)   NOT NULL DEFAULT 0,
    report_reason TEXT        DEFAULT NULL,
    category     ENUM('food','clothes','pharmacy','electronics',
                      'beauty','transport','restaurant','other') DEFAULT 'other',
    is_favorite_business TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (action_id),
    UNIQUE KEY uq_cl_receipt (client_phone, receipt_id),
    KEY idx_cra_phone   (client_phone),
    KEY idx_cra_receipt (receipt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Add public_token to receipts (if missing)
ALTER TABLE receipts ADD COLUMN IF NOT EXISTS public_token VARCHAR(80) DEFAULT NULL;
ALTER TABLE receipts ADD UNIQUE KEY IF NOT EXISTS uq_receipt_token (public_token);

------ pin reset clinet, changer pin client-----
ALTER TABLE clients ADD COLUMN secret_key      VARCHAR(30) DEFAULT NULL;
ALTER TABLE clients ADD COLUMN secret_category VARCHAR(20) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS client_reset_attempts (
    attempt_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_phone VARCHAR(30)  NOT NULL,
    attempt_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (attempt_id),
    KEY idx_reset_phone_time (client_phone, attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
--  END — LionTech Business Manager db-config.sql
-- ============================================================