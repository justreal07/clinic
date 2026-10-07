-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 25, 2025 at 04:36 AM
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
-- Database: `clinic_monitoring`
--

-- --------------------------------------------------------

--
-- Table structure for table `clinic_staff`
--

CREATE TABLE `clinic_staff` (
  `staff_id` int(11) NOT NULL,
  `staff_number` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_staff`
--

INSERT INTO `clinic_staff` (`staff_id`, `staff_number`, `first_name`, `last_name`, `email`, `phone`, `position`, `password`, `created_at`) VALUES
(1, 'admin', 'System', 'Administrator', NULL, NULL, 'System Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2025-09-15 06:04:28'),
(4, '2316-02178C', 'Julius', 'Rivera', NULL, NULL, NULL, '$2y$10$f8V5vHcoEIcQ8WDfFPQn8ONf73MOqAIfV7j6//kYi.q8gL06ZbL8C', '2025-10-08 14:13:10'),
(5, '2316-02131C', 'Jimwel', 'Tolentino', 'jimwel00028@gmail.com', '', 'Staff', '$2y$10$/OB8q93L.21o9tm99fFz4.HTfjrXI5Hd.Bkhp4k3YABJQRp6ogXf.', '2025-11-05 08:52:00'),
(6, '2316-02130C', 'Jimwel', 'Tolentino', '', '', NULL, '$2y$10$4IxVWqEZ5oDk2bwCMyiP1eDguy/AHYD5R6XnoSXQ5LHBgjLdeYaUW', '2025-11-25 02:32:53');

-- --------------------------------------------------------

--
-- Table structure for table `clinic_visits`
--

CREATE TABLE `clinic_visits` (
  `visit_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `visit_date` datetime NOT NULL,
  `reason` text DEFAULT NULL,
  `visit_reason` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `credits` int(11) DEFAULT 3,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_code`, `course_name`, `department`, `credits`, `created_at`) VALUES
(1, 'CS101', 'Introduction to Computer Science', 'Computer Science', 3, '2025-09-15 06:18:54'),
(2, 'CS102', 'Data Structures', 'Computer Science', 3, '2025-09-15 06:18:54'),
(3, 'CS201', 'Algorithms', 'Computer Science', 3, '2025-09-15 06:18:54'),
(4, 'MATH101', 'Calculus I', 'Mathematics', 4, '2025-09-15 06:18:54'),
(5, 'MATH102', 'Calculus II', 'Mathematics', 4, '2025-09-15 06:18:54'),
(6, 'ENG101', 'English Composition', 'Humanities', 3, '2025-09-15 06:18:54'),
(7, 'BIO101', 'General Biology', 'Biology', 4, '2025-09-15 06:18:54'),
(8, 'CHEM101', 'General Chemistry', 'Chemistry', 4, '2025-09-15 06:18:54');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `name`, `type`, `quantity`, `created_at`, `updated_at`) VALUES
(1, 'Laxatives', 'Medicine', 10, '2025-11-07 19:10:06', '2025-11-07 19:10:06'),
(2, 'Bandaid', 'Item', 20, '2025-11-07 19:11:44', '2025-11-25 11:26:37'),
(4, 'Antibiotic', 'Medicine', 15, '2025-11-25 08:31:57', '2025-11-25 08:32:13'),
(8, 'Amoxicillin', 'Medicine', 17, '2025-11-25 11:20:41', '2025-11-25 11:20:41'),
(9, 'Antacid', 'Medicine', 7, '2025-11-25 11:20:51', '2025-11-25 11:20:51'),
(10, 'Antibiotic', 'Medicine', 14, '2025-11-25 11:20:57', '2025-11-25 11:20:57'),
(11, 'Cetirizine', 'Medicine', 23, '2025-11-25 11:21:14', '2025-11-25 11:21:14'),
(12, 'Cough Syrup', 'Medicine', 5, '2025-11-25 11:21:27', '2025-11-25 11:21:27'),
(13, 'Diatabs', 'Medicine', 10, '2025-11-25 11:21:35', '2025-11-25 11:21:35'),
(14, 'Ibuprofen', 'Medicine', 5, '2025-11-25 11:21:40', '2025-11-25 11:21:40'),
(15, 'Loperamide', 'Medicine', 5, '2025-11-25 11:21:55', '2025-11-25 11:21:55'),
(16, 'oitment', 'Medicine', 5, '2025-11-25 11:22:04', '2025-11-25 11:22:04'),
(17, 'ORS', 'Medicine', 3, '2025-11-25 11:22:11', '2025-11-25 11:22:11'),
(18, 'Paracetamol', 'Medicine', 10, '2025-11-25 11:22:16', '2025-11-25 11:22:16'),
(19, 'Alcohol', 'Item', 6, '2025-11-25 11:23:38', '2025-11-25 11:23:38'),
(20, 'Cotton', 'Item', 8, '2025-11-25 11:23:52', '2025-11-25 11:23:52'),
(21, 'Disposable Gloves', 'Item', 5, '2025-11-25 11:24:02', '2025-11-25 11:24:02'),
(22, 'First-aid', 'Item', 10, '2025-11-25 11:24:11', '2025-11-25 11:24:11'),
(23, 'Gauze', 'Item', 15, '2025-11-25 11:24:18', '2025-11-25 11:24:18'),
(24, 'Saline', 'Item', 5, '2025-11-25 11:24:24', '2025-11-25 11:24:24'),
(25, 'Thermometer', 'Item', 3, '2025-11-25 11:24:37', '2025-11-25 11:24:37');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_log`
--

CREATE TABLE `inventory_log` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `change_amount` int(11) NOT NULL,
  `before_qty` int(11) NOT NULL,
  `after_qty` int(11) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visit_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_log`
--

INSERT INTO `inventory_log` (`id`, `inventory_id`, `change_amount`, `before_qty`, `after_qty`, `note`, `user_id`, `visit_id`, `created_at`) VALUES
(1, 2, 1, 19, 20, 'edited via inventory admin', NULL, NULL, '2025-11-25 11:26:37');

-- --------------------------------------------------------

--
-- Table structure for table `predefined_inventory`
--

CREATE TABLE `predefined_inventory` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `predefined_inventory`
--

INSERT INTO `predefined_inventory` (`id`, `name`, `type`, `created_at`) VALUES
(1, 'Laxatives', 'Medicine', '2025-11-25 08:19:02'),
(2, 'Bandaid', 'Item', '2025-11-25 08:19:02'),
(3, 'Antibiotic', 'Medicine', '2025-11-25 08:19:02'),
(4, 'Paracetamol', 'Medicine', '2025-11-25 08:19:02'),
(5, 'Loperamide', 'Medicine', '2025-11-25 08:19:02'),
(7, 'oitment', 'Medicine', '2025-11-25 08:19:02'),
(19, 'Ibuprofen', 'Medicine', '2025-11-25 11:07:22'),
(20, 'Cetirizine', 'Medicine', '2025-11-25 11:07:22'),
(21, 'Syndex', 'Medicine', '2025-11-25 11:07:22'),
(22, 'Diatabs', 'Medicine', '2025-11-25 11:07:22'),
(24, 'Cough Syrup', 'Medicine', '2025-11-25 11:07:22'),
(25, 'Antacid', 'Medicine', '2025-11-25 11:07:22'),
(26, 'ORS', 'Medicine', '2025-11-25 11:07:22'),
(27, 'Amoxicillin', 'Medicine', '2025-11-25 11:07:22'),
(29, 'First-aid', 'Item', '2025-11-25 11:07:22'),
(30, 'Thermometer', 'Item', '2025-11-25 11:07:22'),
(31, 'Gauze', 'Item', '2025-11-25 11:07:22'),
(32, 'Saline', 'Item', '2025-11-25 11:07:22'),
(33, 'Cotton', 'Item', '2025-11-25 11:07:22'),
(34, 'Alcohol', 'Item', '2025-11-25 11:07:22'),
(35, 'Disposable Gloves', 'Item', '2025-11-25 11:07:22');

-- --------------------------------------------------------

-- ... (rest of dump) ...
