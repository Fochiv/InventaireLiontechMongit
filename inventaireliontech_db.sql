-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 13, 2026 at 04:12 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventaireliontech_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `business_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'info',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `business_id`, `action`, `description`, `icon`, `ip_address`, `created_at`) VALUES
(1, 1, 1, 'business_created', 'Nouveau business créé : korabeauty', 'building', '::1', '2026-06-05 10:51:30'),
(2, 2, 1, 'employee_created', 'New employee account created: maman koter', 'user-plus', '::1', '2026-06-05 11:01:33'),
(3, 3, 1, 'employee_clock_in', 'Employé clock in: maman koter', 'clock', '::1', '2026-06-05 11:07:45'),
(4, 3, 1, 'employee_clock_out', 'Employé clock out: maman koter', 'clock', '::1', '2026-06-05 11:27:52'),
(5, 3, 1, 'employee_clock_in', 'Employé clock in: maman koter', 'clock', '::1', '2026-06-05 11:28:26'),
(6, 2, 1, 'clock_in', 'Clock in: koralie', 'clock', '::1', '2026-06-05 12:32:53'),
(7, NULL, NULL, 'business_request_submitted', 'Nouvelle demande business : mall of beauty — nadia okafor', 'building', '::1', '2026-06-05 23:56:23'),
(8, 1, 2, 'business_created', 'Business approuvé : mall of beauty', 'building', '::1', '2026-06-05 23:59:26'),
(9, NULL, NULL, 'business_request_submitted', 'Nouvelle demande business : boulangerie duplex — dagidam toure', 'building', '::1', '2026-06-06 07:59:05'),
(10, 2, 1, 'clock_out', 'Clock out: koralie', 'clock', '::1', '2026-06-06 15:07:44'),
(11, 2, 1, 'employee_created', 'Nouvel employé créé : Abdi Razack', 'user-plus', '::1', '2026-06-06 15:32:44'),
(12, 5, 1, 'clock_in', 'Clock in: Abdi Razack', 'clock', '::1', '2026-06-06 15:44:31'),
(13, 5, 1, 'clock_out', 'Clock out: Abdi Razack', 'clock', '::1', '2026-06-06 16:44:34'),
(14, 5, 1, 'employee_created', 'Nouvel employé créé : ben foch', 'user-plus', '::1', '2026-06-06 17:04:59'),
(15, 6, 1, 'employee_clock_in', 'Employé clock in: ben foch', 'clock', '::1', '2026-06-06 17:25:21'),
(16, 2, 1, 'clock_in', 'Clock in: koralie', 'clock', '::1', '2026-06-06 22:57:41'),
(17, 6, 1, 'employee_clock_out', 'Employé clock out: ben foch', 'clock', '::1', '2026-06-06 23:21:24');

-- --------------------------------------------------------

--
-- Table structure for table `approval_requests`
--

CREATE TABLE `approval_requests` (
  `approval_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `requested_by` int(10) UNSIGNED DEFAULT NULL,
  `request_type` enum('stock_in','stock_out','attendance_correction') NOT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `details` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_in_latitude` decimal(10,7) DEFAULT NULL,
  `clock_in_longitude` decimal(10,7) DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `clock_out_latitude` decimal(10,7) DEFAULT NULL,
  `clock_out_longitude` decimal(10,7) DEFAULT NULL,
  `date` date NOT NULL,
  `status` enum('present','late','absent','pending') NOT NULL DEFAULT 'present',
  `gps_status` enum('on_site','outside_range','rejected','not_checked') NOT NULL DEFAULT 'not_checked',
  `manager_review_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_locked` tinyint(1) NOT NULL DEFAULT 1,
  `device_info` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_corrections`
--

CREATE TABLE `attendance_corrections` (
  `correction_id` int(10) UNSIGNED NOT NULL,
  `attendance_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `requested_by` int(10) UNSIGNED NOT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `original_clock_in` datetime DEFAULT NULL,
  `original_clock_out` datetime DEFAULT NULL,
  `requested_clock_in` datetime DEFAULT NULL,
  `requested_clock_out` datetime DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_correction_requests`
--

CREATE TABLE `attendance_correction_requests` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `attendance_id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `requested_clock_in` datetime DEFAULT NULL,
  `requested_clock_out` datetime DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_settings`
--

CREATE TABLE `attendance_settings` (
  `setting_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(11) NOT NULL,
  `gps_required` tinyint(1) NOT NULL DEFAULT 1,
  `business_latitude` decimal(10,7) DEFAULT NULL,
  `business_longitude` decimal(10,7) DEFAULT NULL,
  `gps_radius_meters` int(11) NOT NULL DEFAULT 200,
  `selfie_required` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance_settings`
--

INSERT INTO `attendance_settings` (`setting_id`, `business_id`, `gps_required`, `business_latitude`, `business_longitude`, `gps_radius_meters`, `selfie_required`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, NULL, 100, 0, '2026-06-05 11:26:05', '2026-06-11 14:45:55');

-- --------------------------------------------------------

--
-- Table structure for table `businesses`
--

CREATE TABLE `businesses` (
  `business_id` int(10) UNSIGNED NOT NULL,
  `business_name` varchar(255) NOT NULL,
  `business_type` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `country` varchar(80) NOT NULL DEFAULT 'Cameroun',
  `email` varchar(255) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `disabled` tinyint(1) NOT NULL DEFAULT 0,
  `subscription_status` enum('trial','active','expired','suspended') NOT NULL DEFAULT 'trial',
  `subscription_expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `businesses`
--

INSERT INTO `businesses` (`business_id`, `business_name`, `business_type`, `phone`, `city`, `address`, `country`, `email`, `logo_url`, `disabled`, `subscription_status`, `subscription_expires_at`, `created_at`, `updated_at`) VALUES
(1, 'korabeauty', 'Salon', '69986924', 'douala', '', 'Cameroun', NULL, NULL, 0, 'active', '2030-12-05 23:59:59', '2026-06-05 10:51:30', '2026-06-11 14:45:55'),
(2, 'mall of beauty', 'art seller', '69986926', 'garoua', NULL, 'Cameroon', NULL, NULL, 0, 'active', '2026-07-06 23:59:59', '2026-06-05 23:59:26', '2026-06-05 23:59:26');

-- --------------------------------------------------------

--
-- Table structure for table `business_features`
--

CREATE TABLE `business_features` (
  `feature_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `inventory_management` tinyint(1) NOT NULL DEFAULT 1,
  `employee_management` tinyint(1) NOT NULL DEFAULT 0,
  `employee_attendance` tinyint(1) NOT NULL DEFAULT 0,
  `sales_tracking` tinyint(1) NOT NULL DEFAULT 0,
  `reports` tinyint(1) NOT NULL DEFAULT 1,
  `low_stock_alerts` tinyint(1) NOT NULL DEFAULT 1,
  `mobile_employee_access` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_features`
--

INSERT INTO `business_features` (`feature_id`, `business_id`, `inventory_management`, `employee_management`, `employee_attendance`, `sales_tracking`, `reports`, `low_stock_alerts`, `mobile_employee_access`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1, 1, 1, 1, 1, '2026-06-05 10:51:30', '2026-06-05 10:51:30'),
(2, 2, 1, 1, 0, 0, 1, 1, 0, '2026-06-05 23:59:26', '2026-06-05 23:59:26');

-- --------------------------------------------------------

--
-- Table structure for table `business_locations`
--

CREATE TABLE `business_locations` (
  `location_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `location_name` varchar(150) NOT NULL DEFAULT 'Emplacement principal',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `allowed_radius_meters` int(10) UNSIGNED NOT NULL DEFAULT 200,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `business_requests`
--

CREATE TABLE `business_requests` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `business_name` varchar(255) NOT NULL,
  `business_type` varchar(100) DEFAULT NULL,
  `country` varchar(100) NOT NULL,
  `currency` varchar(20) NOT NULL,
  `city` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(30) NOT NULL,
  `business_email` varchar(255) DEFAULT NULL,
  `owner_first_name` varchar(100) NOT NULL,
  `owner_last_name` varchar(100) NOT NULL,
  `owner_full_name` varchar(255) NOT NULL,
  `owner_phone` varchar(30) NOT NULL,
  `owner_email` varchar(255) DEFAULT NULL,
  `plan_name` enum('Basic','Standard','Premium') NOT NULL DEFAULT 'Basic',
  `amount` decimal(12,2) NOT NULL DEFAULT 2000.00,
  `billing_cycle` enum('monthly','3_months','6_months','yearly') NOT NULL DEFAULT 'monthly',
  `preferred_payment_method` varchar(50) NOT NULL,
  `has_employees` tinyint(1) NOT NULL DEFAULT 0,
  `requested_features` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `created_business_id` int(10) UNSIGNED DEFAULT NULL,
  `created_owner_user_id` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_requests`
--

INSERT INTO `business_requests` (`request_id`, `business_name`, `business_type`, `country`, `currency`, `city`, `address`, `phone`, `business_email`, `owner_first_name`, `owner_last_name`, `owner_full_name`, `owner_phone`, `owner_email`, `plan_name`, `amount`, `billing_cycle`, `preferred_payment_method`, `has_employees`, `requested_features`, `status`, `rejection_reason`, `created_business_id`, `created_owner_user_id`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(1, 'mall of beauty', 'Supermarket', 'Cameroon', 'XAF', 'buea', NULL, '69986926', NULL, 'nadia', 'okafor', 'nadia okafor', '699986924', NULL, 'Premium', 10000.00, 'monthly', 'mtn_orange_money', 1, '[\"inventory_management\",\"reports\",\"low_stock_alerts\",\"employee_management\",\"employee_attendance\",\"mobile_employee_access\"]', 'rejected', NULL, NULL, NULL, 1, '2026-06-05 23:28:18', '2026-06-05 23:23:39', '2026-06-05 23:28:18'),
(2, 'mall of beauty', 'art seller', 'Cameroon', 'XAF', 'garoua', NULL, '69986926', NULL, 'nadia', 'okafor', 'nadia okafor', '69986926', NULL, 'Standard', 5000.00, 'monthly', 'mtn_orange_money', 1, '[\"inventory_management\",\"reports\",\"low_stock_alerts\",\"employee_management\"]', 'approved', NULL, 2, 2, 1, '2026-06-05 23:59:26', '2026-06-05 23:56:23', '2026-06-05 23:59:26'),
(3, 'boulangerie duplex', 'boulangerie', 'Côte d\'Ivoire', 'XAF', 'lome', NULL, '69986929', NULL, 'dagidam', 'toure', 'dagidam toure', '69986929', NULL, 'Basic', 2000.00, 'monthly', 'mtn_orange_money', 0, '[\"inventory_management\",\"reports\",\"low_stock_alerts\"]', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-06-06 07:59:05', '2026-06-06 07:59:05');

-- --------------------------------------------------------

--
-- Table structure for table `business_settings`
--

CREATE TABLE `business_settings` (
  `setting_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `brand_color` varchar(20) NOT NULL DEFAULT '#0B1F3A',
  `language` varchar(10) NOT NULL DEFAULT 'fr',
  `currency` varchar(10) NOT NULL DEFAULT 'XAF',
  `gps_radius_meters` int(11) NOT NULL DEFAULT 200,
  `require_stock_approval` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `caisse_code` varchar(10) DEFAULT NULL,
  `tva_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `tva_rate` decimal(5,2) NOT NULL DEFAULT 19.25,
  `manager_vente_perms` text DEFAULT NULL COMMENT 'JSON permissions for manager on Vente.php dashboard'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_settings`
--

INSERT INTO `business_settings` (`setting_id`, `business_id`, `logo_path`, `brand_color`, `language`, `currency`, `gps_radius_meters`, `require_stock_approval`, `updated_at`, `caisse_code`, `tva_enabled`, `tva_rate`, `manager_vente_perms`) VALUES
(1, 1, NULL, '#f113b6', 'fr', 'XAF', 100, 1, '2026-06-11 14:45:55', NULL, 0, 19.25, '{\"cashier_perf\":true,\"stock_in\":true,\"stock_out\":true,\"receipts\":true,\"fraud\":false,\"money\":false}');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `client_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `qr_token` varchar(100) NOT NULL,
  `account_status` enum('active','inactive','banned') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `secret_key` varchar(30) DEFAULT NULL,
  `secret_category` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`client_id`, `full_name`, `phone`, `password_hash`, `qr_token`, `account_status`, `created_at`, `updated_at`, `secret_key`, `secret_category`) VALUES
(1, 'jean claude', '699986924', '$2y$10$ni7YB9VeVJTokQ1wEsWIy.vnrBO14mRHDCxJiz/tg.2ULTPTeftim', 'db7c5a1806e8b902a6cc6c1b9228b731e76d4182', 'active', '2026-06-12 23:16:29', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `client_receipt_actions`
--

CREATE TABLE `client_receipt_actions` (
  `action_id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `client_phone` varchar(30) NOT NULL,
  `receipt_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `is_saved` tinyint(1) NOT NULL DEFAULT 0,
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `is_reported` tinyint(1) NOT NULL DEFAULT 0,
  `report_reason` text DEFAULT NULL,
  `category` enum('food','clothes','pharmacy','electronics','beauty','transport','restaurant','other') DEFAULT 'other',
  `is_favorite_business` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_reset_attempts`
--

CREATE TABLE `client_reset_attempts` (
  `attempt_id` int(10) UNSIGNED NOT NULL,
  `client_phone` varchar(30) NOT NULL,
  `attempt_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_attendance`
--

CREATE TABLE `employee_attendance` (
  `attendance_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `clock_in_at` datetime NOT NULL,
  `clock_out_at` datetime DEFAULT NULL,
  `clock_in_latitude` decimal(10,7) DEFAULT NULL,
  `clock_in_longitude` decimal(10,7) DEFAULT NULL,
  `clock_in_accuracy` decimal(10,2) DEFAULT NULL,
  `clock_out_latitude` decimal(10,7) DEFAULT NULL,
  `clock_out_longitude` decimal(10,7) DEFAULT NULL,
  `clock_out_accuracy` decimal(10,2) DEFAULT NULL,
  `gps_status` enum('on_site','pending_review','rejected_far','no_gps_allowed') NOT NULL DEFAULT 'pending_review',
  `distance_meters` decimal(10,2) DEFAULT NULL,
  `status` enum('clocked_in','clocked_out','pending_review','rejected') NOT NULL DEFAULT 'clocked_in',
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_attendance`
--

INSERT INTO `employee_attendance` (`attendance_id`, `business_id`, `user_id`, `clock_in_at`, `clock_out_at`, `clock_in_latitude`, `clock_in_longitude`, `clock_in_accuracy`, `clock_out_latitude`, `clock_out_longitude`, `clock_out_accuracy`, `gps_status`, `distance_meters`, `status`, `note`, `created_at`, `updated_at`) VALUES
(1, 1, 3, '2026-06-05 11:07:45', '2026-06-05 11:27:52', NULL, NULL, NULL, NULL, NULL, NULL, 'pending_review', NULL, 'clocked_out', 'GPS non vérifié', '2026-06-05 11:07:45', '2026-06-05 11:27:52'),
(2, 1, 3, '2026-06-05 11:28:26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending_review', NULL, 'clocked_in', 'GPS non vérifié', '2026-06-05 11:28:26', NULL),
(3, 1, 2, '2026-06-05 12:32:53', '2026-06-06 15:07:44', NULL, NULL, NULL, NULL, NULL, NULL, 'pending_review', NULL, 'clocked_out', 'GPS non vérifié', '2026-06-05 12:32:53', '2026-06-06 15:07:44'),
(4, 1, 5, '2026-06-06 15:44:31', '2026-06-06 16:44:34', NULL, NULL, NULL, NULL, NULL, NULL, 'pending_review', NULL, 'clocked_out', 'GPS non vérifié', '2026-06-06 15:44:31', '2026-06-06 16:44:34'),
(5, 1, 6, '2026-06-06 17:25:21', '2026-06-06 23:21:24', NULL, NULL, NULL, NULL, NULL, NULL, 'pending_review', NULL, 'clocked_out', 'GPS non vérifié', '2026-06-06 17:25:21', '2026-06-06 23:21:24'),
(6, 1, 2, '2026-06-06 22:57:41', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending_review', NULL, 'clocked_in', 'GPS non vérifié', '2026-06-06 22:57:41', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employee_profiles`
--

CREATE TABLE `employee_profiles` (
  `employee_profile_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `employee_role` enum('employee','cashier','stock_manager','manager','other') NOT NULL DEFAULT 'employee',
  `job_title` varchar(150) DEFAULT NULL,
  `profile_photo_url` varchar(500) DEFAULT NULL,
  `pin_must_change` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_profiles`
--

INSERT INTO `employee_profiles` (`employee_profile_id`, `user_id`, `business_id`, `first_name`, `last_name`, `employee_role`, `job_title`, `profile_photo_url`, `pin_must_change`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 'maman', 'koter', 'other', 'stocker', NULL, 1, 2, '2026-06-05 11:01:33', '2026-06-06 15:08:29'),
(2, 5, 1, 'Abdi', 'Razack', 'manager', NULL, NULL, 0, 2, '2026-06-06 15:32:44', '2026-06-06 15:42:19'),
(3, 6, 1, 'ben', 'foch', 'cashier', NULL, 'uploads/profiles/1780783499_2898.jpg', 0, 5, '2026-06-06 17:04:59', '2026-06-06 17:06:33');

-- --------------------------------------------------------

--
-- Table structure for table `employee_tasks`
--

CREATE TABLE `employee_tasks` (
  `task_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(11) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `title` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facture_sequence`
--

CREATE TABLE `facture_sequence` (
  `business_id` int(10) UNSIGNED NOT NULL,
  `annee` year(4) NOT NULL,
  `dernier_num` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `movement_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `movement_type` enum('stock_in','stock_out','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items_transaction`
--

CREATE TABLE `items_transaction` (
  `item_id` int(10) UNSIGNED NOT NULL,
  `transaction_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_sku` varchar(100) DEFAULT NULL,
  `quantite` decimal(12,2) NOT NULL,
  `prix_unitaire` decimal(12,2) NOT NULL,
  `total_ligne` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `liontech_payments`
--

CREATE TABLE `liontech_payments` (
  `payment_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `months_paid` int(11) NOT NULL DEFAULT 1,
  `payment_method` enum('orange_money','mtn_momo','bank_transfer','cash') NOT NULL,
  `transaction_reference` varchar(150) DEFAULT NULL,
  `proof_image_url` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` varchar(255) DEFAULT NULL,
  `rejection_detail` text DEFAULT NULL,
  `submitted_by` int(10) UNSIGNED NOT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `whatsapp_sent` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `liontech_payments`
--

INSERT INTO `liontech_payments` (`payment_id`, `business_id`, `amount`, `months_paid`, `payment_method`, `transaction_reference`, `proof_image_url`, `status`, `rejection_reason`, `rejection_detail`, `submitted_by`, `approved_by`, `whatsapp_sent`, `created_at`, `approved_at`) VALUES
(1, 1, 50000.00, 2, 'orange_money', 'Ci4567893490879', 'LionTech_Complete_MVP_Remaining_Pages/uploads/payments/pay_20260605_180448_1b75020b.png', 'rejected', 'Informations du business incorrectes', NULL, 2, 1, 0, '2026-06-05 11:04:48', '2026-06-05 11:48:23');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `login_id` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `login_id`, `ip_address`, `attempted_at`) VALUES
(3, 'LionTechInventoryAdmin', '::1', '2026-06-05 10:48:12'),
(5, 'mamanhoter275', '::1', '2026-06-05 11:05:41'),
(8, 'IvenAdmin26', '::1', '2026-06-05 12:21:16'),
(9, 'mamankoter275', '::1', '2026-06-06 15:49:00'),
(12, 'mamankoter275', '::1', '2026-06-06 17:03:21'),
(13, 'mamankoter275', '::1', '2026-06-06 17:03:25'),
(15, 'mamankoter275', '::1', '2026-06-11 01:02:52');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `message` text DEFAULT NULL,
  `type` enum('info','warning','danger','success') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `business_id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, NULL, 'Stock Out Approval Needed', 'An employee submitted a stock out request for coco chanel.', 'warning', 0, '2026-06-05 11:14:43'),
(2, 1, NULL, 'Stock Out Approval Needed', 'An employee submitted a stock out request for Ariana grande.', 'warning', 0, '2026-06-06 17:34:43');

-- --------------------------------------------------------

--
-- Table structure for table `paiements_mixtes`
--

CREATE TABLE `paiements_mixtes` (
  `paiement_id` int(10) UNSIGNED NOT NULL,
  `transaction_id` int(10) UNSIGNED NOT NULL,
  `mode` enum('especes','mtn_momo','orange_money') NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `reference` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `subscription_id` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'XAF',
  `method` enum('mtn_momo','orange_money','bank_transfer','cash') NOT NULL,
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_settings`
--

CREATE TABLE `payment_settings` (
  `setting_id` int(10) UNSIGNED NOT NULL,
  `orange_money_number` varchar(30) DEFAULT NULL,
  `orange_money_name` varchar(100) DEFAULT NULL,
  `mtn_momo_number` varchar(30) DEFAULT NULL,
  `mtn_momo_name` varchar(100) DEFAULT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `bank_account_number` varchar(100) DEFAULT NULL,
  `bank_account_holder` varchar(150) DEFAULT NULL,
  `bank_branch` varchar(150) DEFAULT NULL,
  `updated_by_name` varchar(150) DEFAULT NULL,
  `updated_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_settings`
--

INSERT INTO `payment_settings` (`setting_id`, `orange_money_number`, `orange_money_name`, `mtn_momo_number`, `mtn_momo_name`, `bank_name`, `bank_account_number`, `bank_account_holder`, `bank_branch`, `updated_by_name`, `updated_by_user_id`, `updated_at`) VALUES
(1, '', NULL, '', NULL, '', NULL, NULL, NULL, NULL, NULL, '2026-06-05 10:43:19');

-- --------------------------------------------------------

--
-- Table structure for table `payment_settings_log`
--

CREATE TABLE `payment_settings_log` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `changed_by_name` varchar(150) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `field_changed` varchar(100) NOT NULL,
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pin_codes`
--

CREATE TABLE `pin_codes` (
  `pin_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `pin_hash` varchar(255) NOT NULL,
  `must_change` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pin_codes`
--

INSERT INTO `pin_codes` (`pin_id`, `user_id`, `business_id`, `pin_hash`, `must_change`, `created_at`, `updated_at`) VALUES
(1, 6, 1, '$2y$10$RZzJ1QyiR5t4WXA1H4F/A.gX8rdYXhNBc.XxKCJhHs4DtVYEWUfUa', 0, '2026-06-11 00:57:10', '2026-06-12 12:53:12');

-- --------------------------------------------------------

--
-- Table structure for table `preuves_abime`
--

CREATE TABLE `preuves_abime` (
  `preuve_id` int(10) UNSIGNED NOT NULL,
  `transaction_id` int(10) UNSIGNED NOT NULL,
  `photo_url` varchar(500) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `barcode` varchar(150) DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `low_stock_level` decimal(12,2) NOT NULL DEFAULT 5.00,
  `expiration_date` date DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  `category` varchar(100) DEFAULT NULL,
  `unit` varchar(50) NOT NULL DEFAULT 'piece',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cost_price_updated_by` int(10) UNSIGNED DEFAULT NULL,
  `cost_price_updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `business_id`, `name`, `sku`, `barcode`, `quantity`, `unit_price`, `low_stock_level`, `expiration_date`, `supplier`, `image_url`, `description`, `status`, `category`, `unit`, `created_by`, `created_at`, `updated_at`, `cost_price`, `cost_price_updated_by`, `cost_price_updated_at`) VALUES
(1, 1, 'yve saint laurent', 'PRD-53F3BCF6', NULL, 40.00, 35.00, 12.00, '2027-06-05', 'ysl', 'uploads/products/1780675091-4339-ysl.webp', NULL, 'active', 'parfum', 'carton', 2, '2026-06-05 10:58:11', '2026-06-06 23:20:22', 0.00, NULL, NULL),
(2, 1, 'coco chanel', 'PRD-1AD44392', NULL, 55.00, 55.00, 10.00, '2027-06-05', 'coco chanel', 'uploads/products/1780675215-6812-coco-chanel.jpg', NULL, 'active', 'parfum', 'bouteille', 2, '2026-06-05 11:00:15', '2026-06-05 11:23:44', 0.00, NULL, NULL),
(3, 1, 'Ariana grande', 'PRD-93EB01C5', NULL, 76.00, 25000.00, 10.00, NULL, 'ariana grande', 'uploads/products/1780778850-4810-untamed.jpg', NULL, 'active', 'parfum', 'bouteille', 5, '2026-06-06 15:47:30', '2026-06-06 23:20:24', 0.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

CREATE TABLE `receipts` (
  `receipt_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `transaction_id` int(10) UNSIGNED NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `client_phone` varchar(30) DEFAULT NULL,
  `cashier_id` int(10) UNSIGNED DEFAULT NULL,
  `cashier_name` varchar(255) DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `receipt_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`receipt_snapshot`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `public_token` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `receipt_settings`
--

CREATE TABLE `receipt_settings` (
  `setting_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `brand_name` varchar(255) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `brand_color` varchar(20) NOT NULL DEFAULT '#0B1F3A',
  `return_policy` text DEFAULT NULL,
  `footer_message` text DEFAULT NULL,
  `show_cashier` tinyint(1) NOT NULL DEFAULT 1,
  `show_client_phone` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `receipt_settings`
--

INSERT INTO `receipt_settings` (`setting_id`, `business_id`, `brand_name`, `logo_url`, `brand_color`, `return_policy`, `footer_message`, `show_cashier`, `show_client_phone`, `created_at`, `updated_at`) VALUES
(1, 1, 'korabeauty', 'http://localhost/InventoryLiontech/uploads/receipt_logos/receipt_1_1781207155.png', '#8cb4e8', '', 'Merci pour votre achat.', 1, 1, '2026-06-11 14:45:55', '2026-06-11 14:45:55');

-- --------------------------------------------------------

--
-- Table structure for table `report_exports`
--

CREATE TABLE `report_exports` (
  `export_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `report_type` varchar(80) NOT NULL DEFAULT 'general',
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `export_format` varchar(20) NOT NULL DEFAULT 'csv',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_questions`
--

CREATE TABLE `security_questions` (
  `sq_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `question_1` varchar(255) NOT NULL,
  `answer_1_hash` varchar(255) NOT NULL,
  `question_2` varchar(255) NOT NULL,
  `answer_2_hash` varchar(255) NOT NULL,
  `question_3` varchar(255) NOT NULL,
  `answer_3_hash` varchar(255) NOT NULL,
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `is_flagged` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `security_questions`
--

INSERT INTO `security_questions` (`sq_id`, `user_id`, `question_1`, `answer_1_hash`, `question_2`, `answer_2_hash`, `question_3`, `answer_3_hash`, `failed_attempts`, `is_flagged`, `created_at`, `updated_at`) VALUES
(1, 2, 'Dans quelle ville avez-vous grandi ?', '$2y$10$5Ney0xTwB0TZ67jHPqQs2OF/io.juVKbMRwQzVd4xt3cXQq/Y9Ec2', 'Quel est le surnom que vous aviez enfant ?', '$2y$10$ZCjz6hYEe4lbQShM.DN5PeYFT30RPdqDdQsqxG0cOR1uRhi1Rx/EO', 'Quel est le prénom de votre meilleur(e) ami(e) d\'enfance ?', '$2y$10$ZWa7BytWrhVsvmo/lVg6ceTFd9FRSO0V8I5y0tbfs2HDc.NHXIKq.', 0, 0, '2026-06-05 10:54:19', NULL),
(2, 3, 'Quel était le nom de votre premier animal de compagnie ?', '$2y$10$DEKl4QR9CtxFhZdgn3DWM.hM34OImFw88OkYkiqLPJIeDLWvwyLey', 'Dans quelle ville avez-vous grandi ?', '$2y$10$O4oiEHntDKm98QZHVWUem.6B/ipCYL22jk/GIGayGMTiFuteB1h6u', 'Quel est le surnom que vous aviez enfant ?', '$2y$10$TtQCl/.puwjm.7ShddpPt.9UvtscVqqaiY3ATLqva70HfsgvQEkK.', 0, 0, '2026-06-05 11:07:31', NULL),
(3, 5, 'Quel est le nom de votre première école ?', '$2y$10$UY4gwT.ORAwHLgEUvZNJzetUgR7a8988Sym8dBYtTO0LmsGM5aFmK', 'Quel est le nom de jeune fille de votre mère ?', '$2y$10$myMbWs1ufBHUYcxosud7fORjHQQnHabWZkhjzRg6s2zKqltY0t1ia', 'Quel est le prénom de votre meilleur(e) ami(e) d\'enfance ?', '$2y$10$PlIY8SPJB0ypK9pr7VIEwOsAQMXYgAiFiL3yIHtoVcX5l1OSKjctO', 0, 0, '2026-06-06 15:42:49', NULL),
(4, 6, 'Quel est le nom de votre première école ?', '$2y$10$BRyGeiu6j6kpFEFd691v8uTfZtGZ2wu5aOpOgnvOnBx3nUsNaDbRa', 'Quelle est la ville de naissance de votre père ?', '$2y$10$adMXu3xEP7bZ51.wEWOEQuiGFyw.k/UoBcyJbOtIFI/EtowiySVKC', 'Dans quelle ville avez-vous grandi ?', '$2y$10$zVYNEB6Kw3UuKfYj2FjUKuOSA8yaJLgh7QVQOkNJDLD7xI3c/xGT2', 0, 0, '2026-06-06 17:07:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sessions_caisse`
--

CREATE TABLE `sessions_caisse` (
  `session_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `caissier_id` int(10) UNSIGNED NOT NULL,
  `ouverture_at` datetime NOT NULL DEFAULT current_timestamp(),
  `fermeture_at` datetime DEFAULT NULL,
  `fond_ouverture` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_ventes` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_remb` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_especes` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_mtn` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_orange` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rapport_envoye` tinyint(1) NOT NULL DEFAULT 0,
  `statut` enum('ouverte','fermee') NOT NULL DEFAULT 'ouverte'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `sessions_caisse`
--

INSERT INTO `sessions_caisse` (`session_id`, `business_id`, `caissier_id`, `ouverture_at`, `fermeture_at`, `fond_ouverture`, `total_ventes`, `total_remb`, `total_especes`, `total_mtn`, `total_orange`, `rapport_envoye`, `statut`) VALUES
(1, 1, 2, '2026-06-09 11:07:57', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'ouverte'),
(2, 1, 6, '2026-06-12 12:56:11', '2026-06-12 12:56:11', 6000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'fermee'),
(3, 1, 6, '2026-06-12 12:56:11', '2026-06-12 12:57:35', 6000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'fermee'),
(4, 1, 6, '2026-06-12 12:57:35', '2026-06-12 12:57:35', 7000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'fermee'),
(5, 1, 6, '2026-06-12 12:57:35', '2026-06-12 13:03:04', 7000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'fermee'),
(6, 1, 6, '2026-06-12 13:03:04', '2026-06-12 13:03:04', 7000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'fermee'),
(7, 1, 6, '2026-06-12 13:03:04', '2026-06-12 22:29:04', 7000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'fermee'),
(8, 1, 6, '2026-06-12 22:29:04', '2026-06-12 22:29:04', 5000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'fermee'),
(9, 1, 6, '2026-06-12 22:29:04', NULL, 5000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'ouverte');

-- --------------------------------------------------------

--
-- Table structure for table `stock_in_requests`
--

CREATE TABLE `stock_in_requests` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `note` text DEFAULT NULL,
  `proof_image_url` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `potential_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
  `potential_profit` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_in_requests`
--

INSERT INTO `stock_in_requests` (`request_id`, `business_id`, `product_id`, `quantity`, `supplier`, `delivery_date`, `note`, `proof_image_url`, `status`, `created_by`, `approved_by`, `created_at`, `approved_at`, `rejection_reason`, `cost_price`, `potential_revenue`, `potential_profit`) VALUES
(1, 1, 1, 6.00, 'ysl', '2026-06-05', NULL, 'uploads/stock_in/1780675914-1697-campus-trade5.png', 'approved', 3, 2, '2026-06-05 11:11:54', '2026-06-05 11:22:57', NULL, 0.00, 0.00, 0.00),
(2, 1, 1, 32.00, 'ysl', '2026-06-07', NULL, 'uploads/stock_in/1780785238-6471-bussiness-video1.jpeg', 'approved', 6, 2, '2026-06-06 17:33:58', '2026-06-06 23:20:22', NULL, 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `movement_id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED DEFAULT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `movement_type` enum('initial','stock_in','stock_out','adjustment','damage','loss','sale') NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `proof_image_url` varchar(500) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`movement_id`, `request_id`, `business_id`, `product_id`, `movement_type`, `quantity`, `reason`, `supplier`, `proof_image_url`, `created_by`, `approved_by`, `created_at`) VALUES
(1, NULL, 1, 1, 'initial', 2.00, 'Initial product quantity', NULL, NULL, 2, NULL, '2026-06-05 10:58:11'),
(2, NULL, 1, 2, 'initial', 56.00, 'Initial product quantity', NULL, NULL, 2, NULL, '2026-06-05 11:00:15'),
(3, 1, 1, 1, 'stock_in', 6.00, 'Approuvé', NULL, NULL, 3, 2, '2026-06-05 11:22:57'),
(4, 1, 1, 2, 'stock_out', 1.00, 'Approuvé', NULL, NULL, 3, 2, '2026-06-05 11:23:44'),
(5, NULL, 1, 3, 'initial', 78.00, 'Initial product quantity', NULL, NULL, 5, NULL, '2026-06-06 15:47:30'),
(6, 2, 1, 1, 'stock_in', 32.00, 'Approuvé', NULL, NULL, 6, 2, '2026-06-06 23:20:22'),
(7, 2, 1, 3, 'stock_out', 2.00, 'Approuvé', NULL, NULL, 6, 2, '2026-06-06 23:20:24');

-- --------------------------------------------------------

--
-- Table structure for table `stock_out`
--

CREATE TABLE `stock_out` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `proof_image_url` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_out_requests`
--

CREATE TABLE `stock_out_requests` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `proof_image_url` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `movement_type` enum('normal','broken','lost') NOT NULL DEFAULT 'normal',
  `broken_qty` int(11) NOT NULL DEFAULT 0,
  `loss_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `proof_photo` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_out_requests`
--

INSERT INTO `stock_out_requests` (`request_id`, `business_id`, `product_id`, `quantity`, `reason`, `recipient`, `note`, `proof_image_url`, `status`, `created_by`, `approved_by`, `created_at`, `approved_at`, `rejection_reason`, `movement_type`, `broken_qty`, `loss_amount`, `proof_photo`) VALUES
(1, 1, 2, 1.00, 'Damaged', 'parfum', 'casser par client', 'uploads/stock_out/stockout_20260605_181443_405162e2.jpeg', 'approved', 3, 2, '2026-06-05 11:14:43', '2026-06-05 11:23:44', NULL, 'normal', 0, 0.00, NULL),
(2, 1, 3, 2.00, 'Damaged', 'parfum', 'casser', 'uploads/stock_out/stockout_20260607_003443_1dc30fa4.jpg', 'approved', 6, 2, '2026-06-06 17:34:43', '2026-06-06 23:20:24', NULL, 'normal', 0, 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `subscription_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `plan_name` varchar(100) NOT NULL DEFAULT 'Standard',
  `amount` decimal(12,2) NOT NULL DEFAULT 10000.00,
  `currency` varchar(10) NOT NULL DEFAULT 'XAF',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('trial','active','expired','cancelled') NOT NULL DEFAULT 'trial',
  `renewed_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`subscription_id`, `business_id`, `plan_name`, `amount`, `currency`, `start_date`, `end_date`, `status`, `renewed_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'Premium', 50000.00, 'XAF', '2026-06-05', '2030-12-05', 'active', 1, '2026-06-05 10:51:30', '2026-06-05 10:51:30'),
(2, 2, 'Standard', 5000.00, 'XAF', '2026-06-06', '2026-07-06', 'active', 1, '2026-06-05 23:59:26', '2026-06-05 23:59:26');

-- --------------------------------------------------------

--
-- Table structure for table `transactions_caisse`
--

CREATE TABLE `transactions_caisse` (
  `transaction_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `session_id` int(10) UNSIGNED NOT NULL,
  `caissier_id` int(10) UNSIGNED NOT NULL,
  `numero_facture` varchar(20) NOT NULL,
  `type_operation` enum('vente','remboursement','abime') NOT NULL DEFAULT 'vente',
  `sous_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remise_type` enum('aucune','pourcentage','fixe') NOT NULL DEFAULT 'aucune',
  `remise_valeur` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remise_montant` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tva_active` tinyint(1) NOT NULL DEFAULT 0,
  `tva_taux` decimal(5,2) NOT NULL DEFAULT 19.25,
  `tva_montant` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_ttc` decimal(12,2) NOT NULL DEFAULT 0.00,
  `montant_recu` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monnaie_rendue` decimal(12,2) NOT NULL DEFAULT 0.00,
  `client_nom` varchar(255) DEFAULT NULL,
  `client_phone` varchar(30) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `statut` enum('validee','pending_remb','remb_validee','remb_rejetee','pending_abime','abime_validee') NOT NULL DEFAULT 'validee',
  `transaction_ref` int(10) UNSIGNED DEFAULT NULL,
  `validee_par` int(10) UNSIGNED DEFAULT NULL,
  `validee_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `offline_id` varchar(100) DEFAULT NULL COMMENT 'Client-generated ID for offline sale deduplication'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `login_id` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','business_owner','manager','employee','caissier') NOT NULL DEFAULT 'employee',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `security_flagged` tinyint(1) NOT NULL DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `temporary_pin_plain` varchar(20) DEFAULT NULL,
  `force_pin_change` tinyint(1) NOT NULL DEFAULT 0,
  `pin_must_change` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `business_id`, `full_name`, `login_id`, `email`, `phone`, `password_hash`, `role`, `status`, `security_flagged`, `last_login`, `temporary_pin_plain`, `force_pin_change`, `pin_must_change`, `created_at`, `updated_at`) VALUES
(1, NULL, 'LionTechInventoryAdmin', 'InvenAdmin26', 'InvenAdmin26', NULL, '$2y$12$1zBfe7StsnbakHDiD4idieJTTLTip954VMyzUbbp6mzq5yWVltvpi', 'super_admin', 'active', 0, '2026-06-06 16:19:56', NULL, 0, 0, '2026-06-05 10:43:18', '2026-06-06 16:19:56'),
(2, 1, 'koralie', 'naga', NULL, '699986924', '$2y$10$Lyxk2eTBfCEde/zwn7QyNeWGHH68oL/eEwIm2pBSuprnrHD3FFtxy', 'business_owner', 'active', 0, '2026-06-12 22:26:40', '668694', 0, 1, '2026-06-05 10:51:30', '2026-06-12 22:26:40'),
(3, 1, 'maman koter', 'mamankoter275', NULL, '+11651347948', '$2y$10$VFBg2QQkY1WtOFj/HMZ2.OKZIYSSRvOqFyz09/Tpm4YywVKNOECjK', 'employee', 'active', 0, '2026-06-05 12:37:56', NULL, 0, 1, '2026-06-05 11:01:33', '2026-06-06 15:08:29'),
(4, 2, 'nadia okafor', 'nadia.okafor79', NULL, '69986926', '$2y$10$u6114PVeTjJoCYgi/8fLre528pajXxVokFDPNkRSQ1bAVMFF4dP2W', 'business_owner', 'active', 0, NULL, '398024', 0, 1, '2026-06-05 23:59:26', '2026-06-05 23:59:26'),
(5, 1, 'Abdi Razack', 'abdirazack663', NULL, '+11651347948', '$2y$10$LUNR.v454U9FceoiUI964.12vJ6uYHEqBMW03RN4f70jcUdMRDstK', 'manager', 'active', 0, '2026-06-11 01:04:12', NULL, 0, 1, '2026-06-06 15:32:44', '2026-06-11 01:04:12'),
(6, 1, 'ben foch', 'benfoch467', NULL, '+11651347948', '$2y$10$SfRVIcj4y/4D1FU9o348z.QlSwAgWYBPAaIDUP6QzWVH7EguizcGq', 'employee', 'active', 0, '2026-06-12 22:28:46', NULL, 0, 0, '2026-06-06 17:04:59', '2026-06-12 22:28:46');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_monthly_businesses`
-- (See below for the actual view)
--
CREATE TABLE `v_monthly_businesses` (
`month` varchar(7)
,`new_businesses` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_monthly_revenue`
-- (See below for the actual view)
--
CREATE TABLE `v_monthly_revenue` (
`month` varchar(7)
,`total_revenue` decimal(34,2)
,`payment_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `work_schedules`
--

CREATE TABLE `work_schedules` (
  `schedule_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `monday` tinyint(1) NOT NULL DEFAULT 0,
  `tuesday` tinyint(1) NOT NULL DEFAULT 0,
  `wednesday` tinyint(1) NOT NULL DEFAULT 0,
  `thursday` tinyint(1) NOT NULL DEFAULT 0,
  `friday` tinyint(1) NOT NULL DEFAULT 0,
  `saturday` tinyint(1) NOT NULL DEFAULT 0,
  `sunday` tinyint(1) NOT NULL DEFAULT 0,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_schedules`
--

INSERT INTO `work_schedules` (`schedule_id`, `user_id`, `business_id`, `monday`, `tuesday`, `wednesday`, `thursday`, `friday`, `saturday`, `sunday`, `start_time`, `end_time`, `notes`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 1, 0, 1, 0, 1, 1, 1, '08:00:00', '16:45:00', NULL, '2026-06-06 17:32:57', '2026-06-06 23:24:55');

-- --------------------------------------------------------

--
-- Structure for view `v_monthly_businesses`
--
DROP TABLE IF EXISTS `v_monthly_businesses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_monthly_businesses`  AS SELECT date_format(`businesses`.`created_at`,'%Y-%m') AS `month`, count(0) AS `new_businesses` FROM `businesses` GROUP BY date_format(`businesses`.`created_at`,'%Y-%m') ORDER BY date_format(`businesses`.`created_at`,'%Y-%m') ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_monthly_revenue`
--
DROP TABLE IF EXISTS `v_monthly_revenue`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_monthly_revenue`  AS SELECT date_format(`payments`.`paid_at`,'%Y-%m') AS `month`, sum(`payments`.`amount`) AS `total_revenue`, count(0) AS `payment_count` FROM `payments` WHERE `payments`.`status` = 'completed' AND `payments`.`paid_at` is not null GROUP BY date_format(`payments`.`paid_at`,'%Y-%m') ORDER BY date_format(`payments`.`paid_at`,'%Y-%m') ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_business_id` (`business_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `approval_requests`
--
ALTER TABLE `approval_requests`
  ADD PRIMARY KEY (`approval_id`),
  ADD KEY `idx_approval_business` (`business_id`),
  ADD KEY `idx_approval_status` (`status`),
  ADD KEY `idx_approval_type` (`request_type`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `idx_user_date` (`user_id`,`date`),
  ADD KEY `idx_business_date` (`business_id`,`date`),
  ADD KEY `idx_attendance_business_date` (`business_id`,`date`);

--
-- Indexes for table `attendance_corrections`
--
ALTER TABLE `attendance_corrections`
  ADD PRIMARY KEY (`correction_id`),
  ADD KEY `idx_attendance_id` (`attendance_id`),
  ADD KEY `idx_business_id` (`business_id`);

--
-- Indexes for table `attendance_correction_requests`
--
ALTER TABLE `attendance_correction_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_corr_business` (`business_id`),
  ADD KEY `idx_corr_attendance` (`attendance_id`);

--
-- Indexes for table `attendance_settings`
--
ALTER TABLE `attendance_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `business_id` (`business_id`),
  ADD KEY `idx_attendance_settings_business` (`business_id`);

--
-- Indexes for table `businesses`
--
ALTER TABLE `businesses`
  ADD PRIMARY KEY (`business_id`),
  ADD UNIQUE KEY `uq_business_email` (`email`);

--
-- Indexes for table `business_features`
--
ALTER TABLE `business_features`
  ADD PRIMARY KEY (`feature_id`),
  ADD UNIQUE KEY `uq_features_business` (`business_id`);

--
-- Indexes for table `business_locations`
--
ALTER TABLE `business_locations`
  ADD PRIMARY KEY (`location_id`),
  ADD KEY `idx_business_location` (`business_id`);

--
-- Indexes for table `business_requests`
--
ALTER TABLE `business_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_business_requests_status` (`status`),
  ADD KEY `idx_business_requests_plan` (`plan_name`),
  ADD KEY `idx_business_requests_created_at` (`created_at`),
  ADD KEY `fk_business_requests_business` (`created_business_id`),
  ADD KEY `fk_business_requests_owner` (`created_owner_user_id`),
  ADD KEY `fk_business_requests_reviewed_by` (`reviewed_by`);

--
-- Indexes for table `business_settings`
--
ALTER TABLE `business_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `business_id` (`business_id`),
  ADD KEY `idx_settings_business` (`business_id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`client_id`),
  ADD UNIQUE KEY `uq_client_phone` (`phone`),
  ADD UNIQUE KEY `uq_client_qr` (`qr_token`);

--
-- Indexes for table `client_receipt_actions`
--
ALTER TABLE `client_receipt_actions`
  ADD PRIMARY KEY (`action_id`),
  ADD UNIQUE KEY `uq_cl_receipt` (`client_phone`,`receipt_id`),
  ADD KEY `idx_cra_phone` (`client_phone`),
  ADD KEY `idx_cra_receipt` (`receipt_id`);

--
-- Indexes for table `client_reset_attempts`
--
ALTER TABLE `client_reset_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_reset_phone_time` (`client_phone`,`attempt_at`);

--
-- Indexes for table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `idx_att_business_user` (`business_id`,`user_id`),
  ADD KEY `idx_att_clock_in` (`clock_in_at`),
  ADD KEY `idx_att_status` (`status`);

--
-- Indexes for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD PRIMARY KEY (`employee_profile_id`),
  ADD UNIQUE KEY `uq_employee_profile_user` (`user_id`),
  ADD KEY `idx_employee_business` (`business_id`);

--
-- Indexes for table `employee_tasks`
--
ALTER TABLE `employee_tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `idx_tasks_business_assigned` (`business_id`,`assigned_to`),
  ADD KEY `idx_tasks_status` (`status`);

--
-- Indexes for table `facture_sequence`
--
ALTER TABLE `facture_sequence`
  ADD PRIMARY KEY (`business_id`,`annee`);

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `idx_move_business` (`business_id`),
  ADD KEY `idx_move_product` (`product_id`),
  ADD KEY `idx_move_user` (`user_id`),
  ADD KEY `idx_move_created_at` (`created_at`);

--
-- Indexes for table `items_transaction`
--
ALTER TABLE `items_transaction`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_it_transaction` (`transaction_id`),
  ADD KEY `idx_it_product` (`product_id`);

--
-- Indexes for table `liontech_payments`
--
ALTER TABLE `liontech_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `uq_transaction_ref` (`transaction_reference`),
  ADD KEY `idx_payment_business` (`business_id`),
  ADD KEY `idx_payment_status` (`status`),
  ADD KEY `idx_payment_method` (`payment_method`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_id_time` (`login_id`,`attempted_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notif_business` (`business_id`),
  ADD KEY `idx_notif_user` (`user_id`),
  ADD KEY `idx_notif_is_read` (`is_read`);

--
-- Indexes for table `paiements_mixtes`
--
ALTER TABLE `paiements_mixtes`
  ADD PRIMARY KEY (`paiement_id`),
  ADD KEY `idx_pm_transaction` (`transaction_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `idx_business_id` (`business_id`),
  ADD KEY `idx_subscription_id` (`subscription_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_paid_at` (`paid_at`);

--
-- Indexes for table `payment_settings`
--
ALTER TABLE `payment_settings`
  ADD PRIMARY KEY (`setting_id`);

--
-- Indexes for table `payment_settings_log`
--
ALTER TABLE `payment_settings_log`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `pin_codes`
--
ALTER TABLE `pin_codes`
  ADD PRIMARY KEY (`pin_id`),
  ADD UNIQUE KEY `uq_pin_user` (`user_id`);

--
-- Indexes for table `preuves_abime`
--
ALTER TABLE `preuves_abime`
  ADD PRIMARY KEY (`preuve_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `idx_business_id` (`business_id`),
  ADD KEY `idx_products_status` (`status`),
  ADD KEY `idx_products_category` (`category`),
  ADD KEY `idx_products_barcode` (`barcode`),
  ADD KEY `idx_products_business_status` (`business_id`,`status`);

--
-- Indexes for table `receipts`
--
ALTER TABLE `receipts`
  ADD PRIMARY KEY (`receipt_id`),
  ADD UNIQUE KEY `uq_receipt_transaction` (`transaction_id`),
  ADD UNIQUE KEY `uq_receipt_token` (`public_token`),
  ADD KEY `idx_receipt_business_phone` (`business_id`,`client_phone`),
  ADD KEY `idx_receipt_number` (`receipt_number`);

--
-- Indexes for table `receipt_settings`
--
ALTER TABLE `receipt_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `uq_receipt_settings_business` (`business_id`);

--
-- Indexes for table `report_exports`
--
ALTER TABLE `report_exports`
  ADD PRIMARY KEY (`export_id`),
  ADD KEY `idx_report_business` (`business_id`),
  ADD KEY `idx_report_user` (`user_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD KEY `idx_sales_business` (`business_id`),
  ADD KEY `idx_sales_product` (`product_id`),
  ADD KEY `idx_sales_created_at` (`created_at`);

--
-- Indexes for table `security_questions`
--
ALTER TABLE `security_questions`
  ADD PRIMARY KEY (`sq_id`),
  ADD UNIQUE KEY `uq_sq_user` (`user_id`);

--
-- Indexes for table `sessions_caisse`
--
ALTER TABLE `sessions_caisse`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_sc_business` (`business_id`),
  ADD KEY `idx_sc_caissier` (`caissier_id`);

--
-- Indexes for table `stock_in_requests`
--
ALTER TABLE `stock_in_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_stock_in_business` (`business_id`),
  ADD KEY `idx_stock_in_product` (`product_id`),
  ADD KEY `idx_stock_in_status` (`status`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `idx_stock_business` (`business_id`),
  ADD KEY `idx_stock_product` (`product_id`),
  ADD KEY `idx_stock_type` (`movement_type`),
  ADD KEY `idx_stock_movements_business_date` (`business_id`,`created_at`);

--
-- Indexes for table `stock_out`
--
ALTER TABLE `stock_out`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_stock_out2_business` (`business_id`),
  ADD KEY `idx_stock_out2_product` (`product_id`),
  ADD KEY `idx_stock_out2_status` (`status`);

--
-- Indexes for table `stock_out_requests`
--
ALTER TABLE `stock_out_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_stock_out_business` (`business_id`),
  ADD KEY `idx_stock_out_product` (`product_id`),
  ADD KEY `idx_stock_out_status` (`status`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`subscription_id`),
  ADD KEY `idx_business_id` (`business_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_end_date` (`end_date`);

--
-- Indexes for table `transactions_caisse`
--
ALTER TABLE `transactions_caisse`
  ADD PRIMARY KEY (`transaction_id`),
  ADD UNIQUE KEY `uq_numero_facture` (`business_id`,`numero_facture`),
  ADD UNIQUE KEY `uq_offline_id` (`business_id`,`offline_id`),
  ADD KEY `idx_tc_business` (`business_id`),
  ADD KEY `idx_tc_session` (`session_id`),
  ADD KEY `idx_tc_caissier` (`caissier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_login_id` (`login_id`),
  ADD KEY `idx_business_id` (`business_id`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `work_schedules`
--
ALTER TABLE `work_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD UNIQUE KEY `uq_schedule_user` (`user_id`),
  ADD KEY `fk_schedule_business` (`business_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `approval_requests`
--
ALTER TABLE `approval_requests`
  MODIFY `approval_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_corrections`
--
ALTER TABLE `attendance_corrections`
  MODIFY `correction_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_correction_requests`
--
ALTER TABLE `attendance_correction_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_settings`
--
ALTER TABLE `attendance_settings`
  MODIFY `setting_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `businesses`
--
ALTER TABLE `businesses`
  MODIFY `business_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `business_features`
--
ALTER TABLE `business_features`
  MODIFY `feature_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `business_locations`
--
ALTER TABLE `business_locations`
  MODIFY `location_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `business_requests`
--
ALTER TABLE `business_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `business_settings`
--
ALTER TABLE `business_settings`
  MODIFY `setting_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `client_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `client_receipt_actions`
--
ALTER TABLE `client_receipt_actions`
  MODIFY `action_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_reset_attempts`
--
ALTER TABLE `client_reset_attempts`
  MODIFY `attempt_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  MODIFY `attendance_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  MODIFY `employee_profile_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employee_tasks`
--
ALTER TABLE `employee_tasks`
  MODIFY `task_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `movement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items_transaction`
--
ALTER TABLE `items_transaction`
  MODIFY `item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `liontech_payments`
--
ALTER TABLE `liontech_payments`
  MODIFY `payment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `paiements_mixtes`
--
ALTER TABLE `paiements_mixtes`
  MODIFY `paiement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_settings`
--
ALTER TABLE `payment_settings`
  MODIFY `setting_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payment_settings_log`
--
ALTER TABLE `payment_settings_log`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pin_codes`
--
ALTER TABLE `pin_codes`
  MODIFY `pin_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `preuves_abime`
--
ALTER TABLE `preuves_abime`
  MODIFY `preuve_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `receipt_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `receipt_settings`
--
ALTER TABLE `receipt_settings`
  MODIFY `setting_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `report_exports`
--
ALTER TABLE `report_exports`
  MODIFY `export_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_questions`
--
ALTER TABLE `security_questions`
  MODIFY `sq_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sessions_caisse`
--
ALTER TABLE `sessions_caisse`
  MODIFY `session_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `stock_in_requests`
--
ALTER TABLE `stock_in_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `movement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `stock_out`
--
ALTER TABLE `stock_out`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_out_requests`
--
ALTER TABLE `stock_out_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `subscription_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `transactions_caisse`
--
ALTER TABLE `transactions_caisse`
  MODIFY `transaction_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `work_schedules`
--
ALTER TABLE `work_schedules`
  MODIFY `schedule_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_corrections`
--
ALTER TABLE `attendance_corrections`
  ADD CONSTRAINT `fk_corr_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`attendance_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_corr_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE;

--
-- Constraints for table `business_features`
--
ALTER TABLE `business_features`
  ADD CONSTRAINT `fk_features_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `business_locations`
--
ALTER TABLE `business_locations`
  ADD CONSTRAINT `fk_location_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE;

--
-- Constraints for table `business_requests`
--
ALTER TABLE `business_requests`
  ADD CONSTRAINT `fk_business_requests_business` FOREIGN KEY (`created_business_id`) REFERENCES `businesses` (`business_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_business_requests_owner` FOREIGN KEY (`created_owner_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_business_requests_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD CONSTRAINT `fk_employee_profile_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_employee_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD CONSTRAINT `fk_move_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_move_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_move_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_pay_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pay_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`subscription_id`) ON DELETE SET NULL;

--
-- Constraints for table `pin_codes`
--
ALTER TABLE `pin_codes`
  ADD CONSTRAINT `fk_pin_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_in_requests`
--
ALTER TABLE `stock_in_requests`
  ADD CONSTRAINT `fk_stock_in_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stock_in_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stock_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_out`
--
ALTER TABLE `stock_out`
  ADD CONSTRAINT `fk_stock_out2_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stock_out2_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_out_requests`
--
ALTER TABLE `stock_out_requests`
  ADD CONSTRAINT `fk_stock_out_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stock_out_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `fk_subs_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `work_schedules`
--
ALTER TABLE `work_schedules`
  ADD CONSTRAINT `fk_schedule_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_schedule_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
