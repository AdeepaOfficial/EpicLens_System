-- ==============================================================================
-- EpicLens System - Campus Event Equipment Tracking System
-- Clean Relational Database Schema (DDL)
-- Note: Contains zero user data and zero sample records for secure version control.
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- Table: users
-- Roles: 'admin' (full management), 'staff' (event operations)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
  `phone` VARCHAR(30) DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table: item_code_sequences
-- Manages atomic, gapless sequence generation for unique category item codes.
-- Deleted codes are never reused to maintain permanent audit integrity.
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `item_code_sequences` (
  `prefix` VARCHAR(10) NOT NULL PRIMARY KEY,
  `last_number` INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Seed Default Category Prefixes into Sequence Tracker
-- ------------------------------------------------------------------------------
INSERT INTO `item_code_sequences` (`prefix`, `last_number`) VALUES
('CM', 0), -- Camera
('TP', 0), -- Tripod
('LN', 0), -- Lens
('GM', 0), -- Gimbal
('MC', 0), -- Microphone
('FL', 0), -- Flash
('LT', 0), -- Light
('OT', 0)  -- Other
ON DUPLICATE KEY UPDATE `prefix` = `prefix`;

-- ------------------------------------------------------------------------------
-- Table: equipment
-- Master records of student-owned gear registered for campus event lending.
-- Strictly constrained to the 3 official event statuses:
-- 'Not Received Yet', 'Received', 'Returned'
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `equipment` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `item_code` VARCHAR(20) NOT NULL UNIQUE,
  `category` ENUM('Camera', 'Lens', 'Gimbal', 'Tripod', 'Microphone', 'Flash', 'Light', 'Other') NOT NULL,
  `model` VARCHAR(100) NOT NULL,
  `serial_number` VARCHAR(100) DEFAULT NULL,
  `owner_name` VARCHAR(100) NOT NULL,
  `owner_phone` VARCHAR(30) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('Not Received Yet', 'Received', 'Returned') NOT NULL DEFAULT 'Not Received Yet',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_equipment_status` (`status`),
  INDEX `idx_equipment_category` (`category`),
  INDEX `idx_equipment_owner` (`owner_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table: transactions
-- Permanent audit trail capturing the 4-stage lifecycle:
-- Stage 1: Received from Student Owner (Event check-in)
-- Stage 2: Assigned to Event Crew User
-- Stage 3: Returned by Event Crew User to Event Team
-- Stage 4: Returned to Permanent Student Owner
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `equipment_id` INT UNSIGNED NOT NULL,
  `received_by_user_id` INT UNSIGNED DEFAULT NULL,
  `received_by_name` VARCHAR(255) DEFAULT NULL,
  `received_at` DATETIME DEFAULT NULL,
  `equipment_user_name` VARCHAR(255) DEFAULT NULL,
  `equipment_user_phone` VARCHAR(30) DEFAULT NULL,
  `event_user_assigned_at` DATETIME DEFAULT NULL,
  `user_returned_by_user_id` INT UNSIGNED DEFAULT NULL,
  `user_returned_by_name` VARCHAR(255) DEFAULT NULL,
  `user_returned_at` DATETIME DEFAULT NULL,
  `user_returned_condition` VARCHAR(50) DEFAULT NULL,
  `returned_by_user_id` INT UNSIGNED DEFAULT NULL,
  `returned_by_name` VARCHAR(255) DEFAULT NULL,
  `returned_at` DATETIME DEFAULT NULL,
  `received_condition` VARCHAR(50) DEFAULT 'Good',
  `returned_condition` VARCHAR(50) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('Received', 'Returned') NOT NULL DEFAULT 'Received',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_tx_equipment_id` (`equipment_id`),
  INDEX `idx_tx_status` (`status`),
  INDEX `idx_tx_received_user` (`received_by_user_id`),
  INDEX `idx_tx_returned_user` (`returned_by_user_id`),
  INDEX `idx_tx_user_returned_user` (`user_returned_by_user_id`),
  CONSTRAINT `fk_transactions_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
