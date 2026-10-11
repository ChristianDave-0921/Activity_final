-- ==============================================================
-- Student Request Records System - Database SQL Export
-- Database: `student_db`
-- Compatible with MySQL / MariaDB (XAMPP)
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `student_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `student_db`;

-- --------------------------------------------------------------
-- Table structure for table `users` (Admin / Authentication)
-- --------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'user',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default administrator account
-- Password: password123 (hashed via password_hash bcrypt)
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`) VALUES
(1, 'admin', '$2y$10$8conSP3EBA4XdoNwjVnsH.XCymUYrscNe0SGtvubKT9RJMUKXzF8u', 'Administrator', 'admin@studentrecords.edu', 'admin');

-- --------------------------------------------------------------
-- Table structure for table `students` (Student Records)
-- --------------------------------------------------------------
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `student_id` VARCHAR(50) NOT NULL UNIQUE,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `course` VARCHAR(100) NOT NULL,
  `year_level` INT(11) NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Regular',
  `profile_picture` VARCHAR(255) DEFAULT NULL,
  `record_file` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed initial student records
INSERT INTO `students` (`id`, `student_id`, `first_name`, `last_name`, `email`, `course`, `year_level`, `status`) VALUES
(1, '2026001', 'Juan', 'Dela Cruz', 'juan.delacruz@example.com', 'BSIT', 3, 'Regular'),
(2, '2026002', 'Maria', 'Santos', 'maria.santos@example.com', 'BSCS', 2, 'Regular'),
(3, '2026003', 'Mark', 'Reyes', 'mark.reyes@example.com', 'BSIT', 4, 'Irregular'),
(4, '2026004', 'Angela', 'Aquino', 'angela.aquino@example.com', 'BSIS', 1, 'Regular'),
(5, '2026005', 'John Paul', 'Garcia', 'jp.garcia@example.com', 'BSEMC', 2, 'Regular');

-- --------------------------------------------------------------
-- Table structure for table `file_requests` (Student Document Requests)
-- --------------------------------------------------------------
DROP TABLE IF EXISTS `file_requests`;
CREATE TABLE `file_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `document_type` VARCHAR(150) NOT NULL,
  `purpose` TEXT DEFAULT NULL,
  `supporting_file` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
  `admin_notes` TEXT DEFAULT NULL,
  `approved_file` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

