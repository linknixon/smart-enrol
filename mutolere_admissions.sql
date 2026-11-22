-- ==========================================================================
-- SMART-ENROL ADMISSIONS SYSTEM - DATABASE IMPORT SCHEMA
-- Database Name: mutolere_admissions / mutolere-admissions
-- Host: localhost
-- ==========================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+03:00";

-- --------------------------------------------------------
-- Database Creation & Selection
-- --------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `mutolere_admissions` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mutolere_admissions`;

-- --------------------------------------------------------
-- Web Application User Provisioning
-- --------------------------------------------------------
CREATE USER IF NOT EXISTS 'webadmin'@'localhost' IDENTIFIED BY 'User@2026@';
GRANT ALL PRIVILEGES ON `mutolere_admissions`.* TO 'webadmin'@'localhost';
FLUSH PRIVILEGES;

-- --------------------------------------------------------
-- Table structure for table `admin_users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firstname` VARCHAR(100) DEFAULT '',
  `lastname` VARCHAR(100) DEFAULT '',
  `email` VARCHAR(100) DEFAULT '',
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `must_change_password` TINYINT DEFAULT 0,
  `role` VARCHAR(50) NOT NULL DEFAULT 'admin',
  `roles_json` TEXT DEFAULT NULL,
  `mfa_secret` VARCHAR(100) DEFAULT NULL,
  `mfa_enabled` TINYINT DEFAULT 0,
  `failed_attempts` INT DEFAULT 0,
  `lock_until` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `admin_logs`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL,
  `action` VARCHAR(255) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `system_roles`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_key` VARCHAR(50) NOT NULL UNIQUE,
  `role_name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `can_manage_settings` TINYINT DEFAULT 0,
  `can_manage_admission_policies` TINYINT DEFAULT 0,
  `can_admit_students` TINYINT DEFAULT 0,
  `can_reject_students` TINYINT DEFAULT 0,
  `can_view_reports` TINYINT DEFAULT 1,
  `can_export_data` TINYINT DEFAULT 0,
  `can_manage_users` TINYINT DEFAULT 0,
  `can_manage_roles` TINYINT DEFAULT 0,
  `can_take_application_actions` TINYINT DEFAULT 0,
  `can_view_logs` TINYINT DEFAULT 0,
  `is_system` TINYINT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `system_settings`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `applications` (Unified Master Table)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admission_number` VARCHAR(50) DEFAULT NULL UNIQUE,
  `lin` VARCHAR(50) DEFAULT NULL,
  `surname` VARCHAR(100) NOT NULL,
  `firstname` VARCHAR(100) NOT NULL,
  `othernames` VARCHAR(100) DEFAULT NULL,
  `sex` VARCHAR(10) NOT NULL,
  `dob` DATE NOT NULL,
  `age` INT NOT NULL,
  `religion` VARCHAR(50) DEFAULT '',
  `class_of_entry` VARCHAR(10) NOT NULL,
  `passport_photo` VARCHAR(255) NOT NULL,
  `former_school` VARCHAR(150) NOT NULL,
  `index_number` VARCHAR(50) DEFAULT NULL,
  `english` VARCHAR(10) DEFAULT NULL,
  `mathematics` VARCHAR(10) DEFAULT NULL,
  `sst` VARCHAR(10) DEFAULT NULL,
  `science` VARCHAR(10) DEFAULT NULL,
  `aggregates` INT DEFAULT NULL,
  `division` VARCHAR(20) DEFAULT NULL,
  `combination` VARCHAR(100) DEFAULT NULL,
  `transfer_reason` TEXT DEFAULT NULL,
  `result_slip` VARCHAR(255) NOT NULL,
  `parent_name` VARCHAR(150) NOT NULL,
  `parent_nin` VARCHAR(50) NOT NULL,
  `parent_phone` VARCHAR(50) NOT NULL,
  `parent_address` VARCHAR(255) NOT NULL,
  `parent_village` VARCHAR(100) DEFAULT NULL,
  `parent_parish` VARCHAR(100) DEFAULT NULL,
  `parent_subcounty` VARCHAR(100) DEFAULT NULL,
  `parent_district` VARCHAR(100) DEFAULT NULL,
  `parent_id_file` VARCHAR(255) DEFAULT NULL,
  `parent_email` VARCHAR(100) NOT NULL,
  `academic_year` VARCHAR(10) DEFAULT '2026',
  `application_status` VARCHAR(20) DEFAULT 'pending',
  `is_reported` TINYINT DEFAULT 0,
  `reported_at` DATETIME DEFAULT NULL,
  `admitted_by` VARCHAR(150) DEFAULT NULL,
  `admitted_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `s1_applications`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `s1_applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admission_number` VARCHAR(50) DEFAULT NULL,
  `lin` VARCHAR(50) DEFAULT NULL,
  `surname` VARCHAR(100) NOT NULL,
  `firstname` VARCHAR(100) NOT NULL,
  `othernames` VARCHAR(100) DEFAULT NULL,
  `sex` VARCHAR(10) NOT NULL,
  `dob` DATE NOT NULL,
  `age` INT NOT NULL,
  `religion` VARCHAR(50) NOT NULL,
  `passport_photo` VARCHAR(255) NOT NULL,
  `former_school` VARCHAR(150) NOT NULL,
  `index_number` VARCHAR(50) NOT NULL UNIQUE,
  `english` VARCHAR(10) NOT NULL,
  `mathematics` VARCHAR(10) NOT NULL,
  `sst` VARCHAR(10) NOT NULL,
  `science` VARCHAR(10) NOT NULL,
  `aggregates` INT NOT NULL,
  `division` VARCHAR(20) NOT NULL,
  `result_slip` VARCHAR(255) NOT NULL,
  `parent_name` VARCHAR(150) NOT NULL,
  `parent_nin` VARCHAR(50) NOT NULL,
  `parent_phone` VARCHAR(50) NOT NULL,
  `parent_address` VARCHAR(255) NOT NULL,
  `parent_village` VARCHAR(100) DEFAULT NULL,
  `parent_parish` VARCHAR(100) DEFAULT NULL,
  `parent_subcounty` VARCHAR(100) DEFAULT NULL,
  `parent_district` VARCHAR(100) DEFAULT NULL,
  `parent_id_file` VARCHAR(255) DEFAULT NULL,
  `parent_email` VARCHAR(100) NOT NULL,
  `academic_year` VARCHAR(10) DEFAULT '2026',
  `application_status` VARCHAR(20) DEFAULT 'pending',
  `is_reported` TINYINT DEFAULT 0,
  `reported_at` DATETIME DEFAULT NULL,
  `admitted_by` VARCHAR(150) DEFAULT NULL,
  `admitted_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `s5_applications`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `s5_applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admission_number` VARCHAR(50) DEFAULT NULL,
  `lin` VARCHAR(50) DEFAULT NULL,
  `surname` VARCHAR(100) NOT NULL,
  `firstname` VARCHAR(100) NOT NULL,
  `othernames` VARCHAR(100) DEFAULT NULL,
  `sex` VARCHAR(10) NOT NULL,
  `dob` DATE NOT NULL,
  `age` INT NOT NULL,
  `passport_photo` VARCHAR(255) NOT NULL,
  `former_school` VARCHAR(150) NOT NULL,
  `uce_index` VARCHAR(50) NOT NULL UNIQUE,
  `aggregates` INT NOT NULL,
  `division` VARCHAR(20) NOT NULL,
  `combination` VARCHAR(100) NOT NULL,
  `result_slip` VARCHAR(255) NOT NULL,
  `parent_name` VARCHAR(150) NOT NULL,
  `parent_nin` VARCHAR(50) NOT NULL,
  `parent_phone` VARCHAR(50) NOT NULL,
  `parent_address` VARCHAR(255) NOT NULL,
  `parent_village` VARCHAR(100) DEFAULT NULL,
  `parent_parish` VARCHAR(100) DEFAULT NULL,
  `parent_subcounty` VARCHAR(100) DEFAULT NULL,
  `parent_district` VARCHAR(100) DEFAULT NULL,
  `parent_id_file` VARCHAR(255) DEFAULT NULL,
  `parent_email` VARCHAR(100) NOT NULL,
  `academic_year` VARCHAR(10) DEFAULT '2026',
  `application_status` VARCHAR(20) DEFAULT 'pending',
  `is_reported` TINYINT DEFAULT 0,
  `reported_at` DATETIME DEFAULT NULL,
  `admitted_by` VARCHAR(150) DEFAULT NULL,
  `admitted_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seed default administrator account
-- Username: admin
-- Password: User@2026@
-- --------------------------------------------------------
INSERT INTO `admin_users` (`firstname`, `lastname`, `email`, `username`, `password`, `role`, `must_change_password`) VALUES
('System', 'Administrator', 'admin@mutolere.sc.ug', 'admin', '$2y$10$46pkijfP8tMVuD95zZZ90unzMys3ZpeBivqh/V1gCJh2GpxfgOy7C', 'super_admin', 0)
ON DUPLICATE KEY UPDATE `password`=VALUES(`password`), `role`='super_admin';

-- --------------------------------------------------------
-- Seed system roles (CRUDAS Matrix)
-- --------------------------------------------------------
INSERT INTO `system_roles` (`role_key`, `role_name`, `description`, `can_manage_settings`, `can_manage_admission_policies`, `can_admit_students`, `can_reject_students`, `can_view_reports`, `can_export_data`, `can_manage_users`, `can_manage_roles`, `can_take_application_actions`, `can_view_logs`, `is_system`) VALUES
('super_admin', 'Super Administrator', 'Full root access to all system modules, configurations, settings, security, and roles.', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1),
('admin', 'System Administrator', 'Administrative control over admissions, user accounts, system configuration, and data exports.', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1),
('admissions_registry', 'Admissions Registry', 'Manages student dossiers, processes intake decisions (admit/reject), and tracks student admissions.', 0, 1, 1, 1, 1, 1, 0, 0, 1, 0, 1),
('bursar', 'Bursar & Finance Office', 'Monitors admitted student rosters, financial reporting, and exports fee assessment lists.', 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 1),
('viewer', 'Read-Only Observer', 'Strict read-only oversight across application rosters and statistical summaries.', 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1),
('ict_admin', 'ICT Officer', 'Manages IT configurations, user management, and system auditing.', 1, 0, 0, 0, 1, 1, 1, 0, 0, 1, 1)
ON DUPLICATE KEY UPDATE `role_key`=`role_key`;
