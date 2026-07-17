-- ==========================================================================
-- ST. PAUL'S MUTOLERE SECONDARY SCHOOL - DATABASE IMPORT SCHEMA
-- Admissions Portal Setup Script
-- Database Name: mutolere_admissions
-- Host: localhost
-- ==========================================================================

-- CREATE DATABASE IF NOT EXISTS `mutolere_admissions` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `mutolere_admissions`;

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
-- Table structure for table `s1_applications`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `s1_applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
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
  `english` INT NOT NULL,
  `mathematics` INT NOT NULL,
  `sst` INT NOT NULL,
  `science` INT NOT NULL,
  `aggregates` INT NOT NULL,
  `division` VARCHAR(20) NOT NULL,
  `result_slip` VARCHAR(255) NOT NULL,
  `parent_name` VARCHAR(150) NOT NULL,
  `parent_nin` VARCHAR(50) NOT NULL,
  `parent_phone` VARCHAR(50) NOT NULL,
  `parent_address` VARCHAR(255) NOT NULL,
  `parent_email` VARCHAR(100) NOT NULL,
  `application_status` VARCHAR(20) DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `s5_applications`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `s5_applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
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
  `combination` VARCHAR(20) NOT NULL,
  `result_slip` VARCHAR(255) NOT NULL,
  `parent_name` VARCHAR(150) NOT NULL,
  `parent_nin` VARCHAR(50) NOT NULL,
  `parent_phone` VARCHAR(50) NOT NULL,
  `parent_address` VARCHAR(255) NOT NULL,
  `parent_email` VARCHAR(100) NOT NULL,
  `application_status` VARCHAR(20) DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Default administrator account credentials:
-- Username: admin
-- Password: admin
-- --------------------------------------------------------
INSERT INTO `admin_users` (`firstname`, `lastname`, `email`, `username`, `password`, `role`, `must_change_password`) VALUES
('System', 'Administrator', 'admin@mutolere.sc.ug', 'admin', '$2y$10$wE9K9Z7/jI3p47p1w5mUie7O7T/Y4d0ZzZ.x3k8fB6E6.7sLDeh/O', 'super_admin', 0)
ON DUPLICATE KEY UPDATE `username`=`username`;
