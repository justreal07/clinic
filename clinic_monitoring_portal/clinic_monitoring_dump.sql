-- Clinic Monitoring Portal — SQL schema + seed data
-- Generated: 2025-11-25
-- Run this on your MySQL server to set up the database and tables for the project.

CREATE DATABASE IF NOT EXISTS `clinic_monitoring` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `clinic_monitoring`;

-- Table: clinic_staff
CREATE TABLE IF NOT EXISTS `clinic_staff` (
  `staff_id` INT AUTO_INCREMENT PRIMARY KEY,
  `staff_number` VARCHAR(64) DEFAULT NULL,
  `first_name` VARCHAR(150) NOT NULL,
  `last_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(200) DEFAULT NULL,
  `phone` VARCHAR(64) DEFAULT NULL,
  `position` VARCHAR(120) DEFAULT NULL,
  `password` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_staff_number` (`staff_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: students
CREATE TABLE IF NOT EXISTS `students` (
  `student_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_number` VARCHAR(64) NOT NULL,
  `first_name` VARCHAR(150) NOT NULL,
  `last_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(200) DEFAULT NULL,
  `phone` VARCHAR(64) DEFAULT NULL,
  `course` VARCHAR(255) DEFAULT NULL,
  `department` VARCHAR(255) DEFAULT NULL,
  `year_of_study` VARCHAR(100) DEFAULT NULL,
  `gender` VARCHAR(30) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_student_number` (`student_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: courses (optional)
CREATE TABLE IF NOT EXISTS `courses` (
  `course_id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_code` VARCHAR(64) NOT NULL,
  `course_name` VARCHAR(255) NOT NULL,
  `department` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: visits (lightweight visits used by record_visit.php)
CREATE TABLE IF NOT EXISTS `visits` (
  `visit_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `staff_id` INT DEFAULT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `medicine_given` VARCHAR(255) DEFAULT NULL,
  `visit_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`student_id`),
  INDEX (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: clinic_visits (more feature-rich visits table used elsewhere)
CREATE TABLE IF NOT EXISTS `clinic_visits` (
  `visit_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `staff_id` INT DEFAULT NULL,
  `visit_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `visit_reason` TEXT DEFAULT NULL,
  `diagnosis` TEXT DEFAULT NULL,
  `treatment` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'pending',
  INDEX (`student_id`),
  INDEX (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: inventory
CREATE TABLE IF NOT EXISTS `inventory` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: inventory_log
CREATE TABLE IF NOT EXISTS `inventory_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `inventory_id` INT DEFAULT NULL,
  `change_amount` INT NOT NULL,
  `before_qty` INT NOT NULL,
  `after_qty` INT NOT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `user_id` INT DEFAULT NULL,
  `visit_id` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`inventory_id`),
  INDEX (`user_id`),
  INDEX (`visit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: predefined_inventory (canonical names used for suggestions)
CREATE TABLE IF NOT EXISTS `predefined_inventory` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_name_type` (name(191), type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- OPTIONAL: Small default data seeds
-- Sample courses (optional) - add or change as needed
INSERT IGNORE INTO `courses` (`course_code`,`course_name`,`department`) VALUES
('BSCS', 'Bachelor of Science in Computer Science', 'Computer Science'),
('BSIT', 'Bachelor of Science in Information Technology', 'Information Technology'),
('BSBA', 'Bachelor of Science in Business Administration', 'Business'),
('BSED', 'Bachelor of Science in Education', 'Education'),
('BSTM', 'Bachelor of Science in Tourism Management', 'Tourism');

-- Seed default predefined inventory (if you want initial options available)
INSERT IGNORE INTO `predefined_inventory` (`name`,`type`) VALUES
('Paracetamol','Medicine'),
('Ibuprofen','Medicine'),
('Cetirizine','Medicine'),
('Syndex','Medicine'),
('Diatabs','Medicine'),
('Antibiotic','Medicine'),
('Cough Syrup','Medicine'),
('Antacid','Medicine'),
('ORS','Medicine'),
('Amoxicillin','Medicine'),
('Bandaid','Item'),
('First-aid','Item'),
('Thermometer','Item'),
('Gauze','Item'),
('Saline','Item'),
('Cotton','Item'),
('Alcohol','Item'),
('Disposable Gloves','Item');

-- Helpful comment: after importing, ensure your project's config.php uses
-- the same MySQL user/password settings and update them if needed.

COMMIT;
