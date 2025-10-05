-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 05, 2025 at 05:59 AM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u491086721_bus_ticket_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `active_trips`
--

CREATE TABLE `active_trips` (
  `trip_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `trip_date` date NOT NULL,
  `scheduled_departure` datetime NOT NULL,
  `actual_departure` datetime DEFAULT NULL,
  `estimated_arrival` datetime NOT NULL,
  `actual_arrival` datetime DEFAULT NULL,
  `status` enum('scheduled','boarding','departed','in_transit','arrived','completed','cancelled') DEFAULT 'scheduled',
  `passenger_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `active_trips`
--

INSERT INTO `active_trips` (`trip_id`, `schedule_id`, `bus_id`, `driver_id`, `route_id`, `trip_date`, `scheduled_departure`, `actual_departure`, `estimated_arrival`, `actual_arrival`, `status`, `passenger_count`, `created_at`, `updated_at`) VALUES
(35, 0, 6, 6, 27, '2025-09-02', '2025-09-02 01:39:22', '2025-09-02 01:46:27', '2025-09-02 02:08:22', '2025-09-02 01:49:26', 'completed', 0, '2025-09-01 17:39:22', '2025-09-01 17:49:28'),
(36, 0, 6, 6, 27, '2025-09-02', '2025-09-02 01:49:43', '2025-09-02 01:49:46', '2025-09-02 02:18:43', '2025-09-02 01:58:08', 'completed', 0, '2025-09-01 17:49:43', '2025-09-01 17:58:11'),
(37, 0, 6, 6, 27, '2025-09-02', '2025-09-02 01:58:54', '2025-09-02 01:58:58', '2025-09-02 02:27:54', '2025-09-02 02:05:34', 'completed', 0, '2025-09-01 17:58:54', '2025-09-01 18:05:36'),
(38, 0, 6, 6, 27, '2025-09-02', '2025-09-02 02:05:51', '2025-09-02 02:05:57', '2025-09-02 02:34:51', '2025-09-02 02:07:55', 'completed', 0, '2025-09-01 18:05:51', '2025-09-01 18:08:03'),
(39, 0, 6, 6, 27, '2025-09-02', '2025-09-02 02:08:20', '2025-09-02 02:08:23', '2025-09-02 02:37:20', '2025-09-02 02:09:15', 'completed', 0, '2025-09-01 18:08:20', '2025-09-01 18:09:17'),
(40, 0, 6, 6, 27, '2025-09-02', '2025-09-02 02:09:26', '2025-09-02 02:09:30', '2025-09-02 02:38:26', '2025-09-02 02:46:56', 'completed', 3, '2025-09-01 18:09:26', '2025-09-01 18:46:58'),
(41, 0, 6, 6, 26, '2025-09-02', '2025-09-02 02:50:25', '2025-09-02 02:50:29', '2025-09-02 03:21:25', '2025-09-02 02:51:54', 'completed', 0, '2025-09-01 18:50:25', '2025-09-01 18:51:56'),
(42, 0, 6, 6, 27, '2025-09-02', '2025-09-02 02:52:00', '2025-09-02 02:52:03', '2025-09-02 03:21:00', '2025-09-02 16:01:31', 'completed', 19, '2025-09-01 18:52:00', '2025-09-02 08:01:33'),
(43, 0, 6, 6, 27, '2025-09-02', '2025-09-02 16:06:47', '2025-09-02 17:08:15', '2025-09-02 16:35:47', '2025-09-02 17:08:18', 'completed', 0, '2025-09-02 08:06:47', '2025-09-02 09:08:19'),
(44, 0, 6, 6, 27, '2025-09-03', '2025-09-03 01:56:58', '2025-09-03 01:57:01', '2025-09-03 02:25:58', '2025-10-03 13:44:15', 'completed', 2, '2025-09-02 17:56:58', '2025-10-03 13:44:59'),
(45, 0, 6, 6, 27, '2025-10-03', '2025-10-03 21:45:12', '2025-10-03 13:45:21', '2025-10-03 22:14:12', '2025-10-03 13:45:57', 'completed', 9, '2025-10-03 13:45:12', '2025-10-03 13:46:01'),
(46, 0, 6, 6, 27, '2025-10-05', '2025-10-05 13:29:01', '2025-10-05 05:29:24', '2025-10-05 13:58:01', '2025-10-05 05:31:48', 'completed', 0, '2025-10-05 05:29:01', '2025-10-05 05:31:50');

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_log`
--

CREATE TABLE `admin_activity_log` (
  `log_id` int(11) NOT NULL,
  `admin_username` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_activity_log`
--

INSERT INTO `admin_activity_log` (`log_id`, `admin_username`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 'admin', 'Delete Route', 'Route ID: 2', '::1', '2025-08-20 01:59:23'),
(2, 'admin', 'Delete Route', 'Route ID: 2', '::1', '2025-08-20 02:10:18'),
(3, 'admin', 'Delete Route', 'Route ID: 2', '::1', '2025-08-20 02:10:39'),
(4, 'admin', 'Create Route', 'Route: cyn-sm (Cauayan → Cabatuan)', '::1', '2025-08-20 02:21:07'),
(5, 'admin', 'Delete Route', 'Route ID: 9', '::1', '2025-08-20 02:22:06'),
(6, 'admin', 'Create Driver', 'Driver: Patrick Miguel Blas (Code: DRV20257573)', '::1', '2025-08-20 03:43:54'),
(7, 'admin', 'Delete Driver', 'Driver ID: 1', '::1', '2025-08-20 03:44:30'),
(8, 'admin', 'Logout', NULL, '::1', '2025-08-20 11:30:24'),
(9, 'admin', 'Create Driver', 'Driver: Patrick Miguel Blas (Code: DRV20255776)', '::1', '2025-08-20 11:31:42'),
(10, 'admin', 'Create Bus', 'Bus: 07 - 1234567', '::1', '2025-08-20 11:32:14'),
(11, 'admin', 'Logout', NULL, '::1', '2025-08-20 11:33:08'),
(12, 'admin', 'Logout', NULL, '::1', '2025-08-21 02:28:31'),
(13, 'admin', 'Logout', NULL, '::1', '2025-08-21 02:32:59'),
(14, 'admin', 'Logout', NULL, '::1', '2025-08-21 02:49:13'),
(15, 'admin', 'Logout', NULL, '::1', '2025-08-21 03:44:13'),
(16, 'admin', 'Logout', NULL, '::1', '2025-08-25 09:21:45'),
(17, 'admin', 'Delete Driver', 'Driver ID: 2', '::1', '2025-08-27 14:01:55'),
(18, 'admin', 'Create Driver', 'Driver: Patrick Miguel Blas (Code: DRV20257286)', '::1', '2025-08-27 14:02:28'),
(19, 'admin', 'Delete Route', 'Route ID: 5', '::1', '2025-08-27 14:10:53'),
(20, 'admin', 'Delete Route', 'Route ID: 6', '::1', '2025-08-27 14:10:56'),
(21, 'admin', 'Delete Route', 'Route ID: 7', '::1', '2025-08-27 14:10:59'),
(22, 'admin', 'Delete Route', 'Route ID: 8', '::1', '2025-08-27 14:11:00'),
(23, 'admin', 'Delete Route', 'Route ID: 1', '::1', '2025-08-27 14:11:02'),
(24, 'admin', 'Delete Route', 'Route ID: 3', '::1', '2025-08-27 14:11:04'),
(25, 'admin', 'Delete Route', 'Route ID: 4', '::1', '2025-08-27 14:11:06'),
(26, 'admin', 'Bus Maintenance', 'Bus ID: 1', '::1', '2025-08-27 14:19:30'),
(27, 'admin', 'Create Route', 'Route: Cauayan trip (San Mateo, Isabela → Cauayan City, Isabela)', '::1', '2025-08-27 14:20:59'),
(28, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 14:21:04'),
(29, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 14:21:05'),
(30, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 14:22:44'),
(31, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 14:22:45'),
(32, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 14:22:45'),
(33, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 14:22:46'),
(34, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 14:22:46'),
(35, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 14:22:47'),
(36, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 14:22:48'),
(37, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 14:22:49'),
(38, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 14:22:49'),
(39, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 14:22:50'),
(40, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 14:22:50'),
(41, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 14:22:51'),
(42, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 14:22:51'),
(43, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 14:22:51'),
(44, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 14:22:52'),
(45, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 14:22:52'),
(46, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 14:22:52'),
(47, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 14:22:53'),
(48, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 14:22:53'),
(49, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 14:22:53'),
(50, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 14:22:53'),
(51, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 14:22:54'),
(52, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 14:23:04'),
(53, 'admin', 'Logout', NULL, '::1', '2025-08-27 14:23:58'),
(54, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 16:04:49'),
(55, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 16:17:04'),
(56, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 16:32:08'),
(57, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 16:33:29'),
(58, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 16:39:35'),
(59, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 16:39:36'),
(60, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 16:39:37'),
(61, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 16:39:37'),
(62, 'admin', 'Update Route Status', 'Route ID: 10 → active', '::1', '2025-08-27 16:39:38'),
(63, 'admin', 'Update Route Status', 'Route ID: 10 → inactive', '::1', '2025-08-27 16:39:45'),
(64, 'admin', 'Delete Route', 'Route ID: 10', '::1', '2025-08-27 16:42:38'),
(65, 'admin', 'Delete Route', 'Route ID: 10', '::1', '2025-08-27 16:42:41'),
(66, 'admin', 'Create Route', 'Route: Cauayan trip (San Mateo, Isabela → Cauayan City, Isabela)', '::1', '2025-08-27 16:43:04'),
(67, 'admin', 'Update Route Status', 'Route ID: 11 → inactive', '::1', '2025-08-27 16:43:19'),
(68, 'admin', 'Update Route Status', 'Route ID: 11 → active', '::1', '2025-08-27 16:43:20'),
(69, 'admin', 'Update Route Status', 'Route ID: 11 → inactive', '::1', '2025-08-27 16:43:21'),
(70, 'admin', 'Update Route Status', 'Route ID: 11 → active', '::1', '2025-08-27 16:43:21'),
(71, 'admin', 'Update Route Status', 'Route ID: 11 → inactive', '::1', '2025-08-27 16:43:22'),
(72, 'admin', 'Update Route Status', 'Route ID: 11 → active', '::1', '2025-08-27 16:43:22'),
(73, 'admin', 'Update Route Status', 'Route ID: 11 → inactive', '::1', '2025-08-27 16:43:23'),
(74, 'admin', 'Update Route Status', 'Route ID: 11 → active', '::1', '2025-08-27 16:43:26'),
(75, 'admin', 'Update Route Status', 'Route ID: 11 → inactive', '::1', '2025-08-27 16:43:26'),
(76, 'admin', 'Update Route Status', 'Route ID: 11 (Cauayan trip) → active', '::1', '2025-08-28 05:35:20'),
(77, 'admin', 'Update Route Status', 'Route ID: 11 (Cauayan trip) → inactive', '::1', '2025-08-28 05:37:22'),
(78, 'admin', 'Delete Route', 'Route ID: 11', '::1', '2025-08-28 05:37:38'),
(79, 'admin', 'Create Route', 'Route: Cauayan trip (San Mateo, Isabela → Cauayan City, Isabela)', '::1', '2025-08-28 05:37:54'),
(80, 'admin', 'Update Route Status', 'Route ID: 12 (Cauayan trip) → inactive', '::1', '2025-08-28 05:38:03'),
(81, 'admin', 'Update Route Status', 'Route ID: 12 (Cauayan trip) → active', '::1', '2025-08-28 05:38:04'),
(82, 'admin', 'Update Route Status', 'Route ID: 12 (Cauayan trip) → inactive', '::1', '2025-08-28 05:38:05'),
(83, 'admin', 'Update Route Status', 'Route ID: 12 (Cauayan trip) → active', '::1', '2025-08-28 05:38:07'),
(84, 'admin', 'Update Route Status', 'Route ID: 12 (Cauayan trip) → inactive', '::1', '2025-08-28 05:38:12'),
(85, 'admin', 'Update Route Status', 'Route ID: 12 (Cauayan trip) → active', '::1', '2025-08-28 05:38:14'),
(86, 'admin', 'Activate Bus', 'Bus ID: 1', '::1', '2025-08-28 05:40:02'),
(87, 'admin', 'Assign Bus to Route', 'Bus #07 (1234567) assigned to Route ID: 12 (Cauayan trip)', '::1', '2025-08-28 05:40:15'),
(88, 'admin', 'Logout', NULL, '::1', '2025-08-28 05:40:20'),
(89, 'admin', 'Delete Driver', 'Driver ID: 3', '::1', '2025-08-28 05:44:11'),
(90, 'admin', 'Delete Bus', 'Bus ID: 1', '::1', '2025-08-28 05:44:16'),
(91, 'admin', 'Create Driver', 'Driver: Patrick Miguel Blas (Code: DRV20256965)', '::1', '2025-08-28 05:44:43'),
(92, 'admin', 'Create Bus', 'Bus: 007 - 1234567', '::1', '2025-08-28 05:45:08'),
(93, 'admin', 'Assign Bus to Route', 'Bus #007 (1234567) assigned to Route ID: 12 (Cauayan trip)', '::1', '2025-08-28 05:45:23'),
(94, 'admin', 'Create Route', 'Route: San Mateo trip (Cauayan City, Isabela → San Mateo, Isabela)', '::1', '2025-08-28 06:17:50'),
(95, 'admin', 'Unassign Bus from Route', 'Bus #007 (1234567) unassigned from route: Cauayan trip', '::1', '2025-08-28 06:18:07'),
(96, 'admin', 'Assign Bus to Route', 'Bus #007 (1234567) assigned to Route ID: 12 (Cauayan trip)', '::1', '2025-08-28 06:18:12'),
(97, 'admin', 'Unassign Bus from Route', 'Bus #007 (1234567) unassigned from route: Cauayan trip', '::1', '2025-08-28 06:18:12'),
(98, 'admin', 'Assign Bus to Route', 'Bus #007 (1234567) assigned to Route ID: 12 (Cauayan trip)', '::1', '2025-08-28 06:18:19'),
(99, 'admin', 'Unassign Bus from Route', 'Bus #007 (1234567) unassigned from route: Cauayan trip', '::1', '2025-08-28 06:18:19'),
(100, 'admin', 'Assign Bus to Route', 'Bus #007 (1234567) assigned to Route ID: 13 (San Mateo trip)', '::1', '2025-08-28 06:18:42'),
(101, 'admin', 'Unassign Bus from Route', 'Bus #007 (1234567) unassigned from route: San Mateo trip', '::1', '2025-08-28 06:18:52'),
(102, 'admin', 'Create Driver', 'Driver: Mharian (Code: DRV20252577)', '::1', '2025-08-28 06:19:31'),
(103, 'admin', 'Create Bus', 'Bus: 08 - 1234563', '::1', '2025-08-28 06:19:58'),
(104, 'admin', 'Assign Bus to Route', 'Bus #007 (1234567) assigned to Route ID: 12 (Cauayan trip)', '::1', '2025-08-28 06:20:14'),
(105, 'admin', 'Assign Bus to Route', 'Bus #08 (1234563) assigned to Route ID: 13 (San Mateo trip)', '::1', '2025-08-28 06:20:19'),
(106, 'admin', 'Delete Driver', 'Driver ID: 5', '::1', '2025-08-28 06:51:46'),
(107, 'admin', 'Delete Driver', 'Driver ID: 4', '::1', '2025-08-28 06:51:49'),
(108, 'admin', 'Create Route', 'Route: Cauayan trip (San Mateo, Isabela → Cauayan City, Isabela)', '::1', '2025-08-28 06:58:58'),
(109, 'admin', 'Create Route', 'Reverse Route: Cauayan City, Isabela to San Mateo, Isabela (Cauayan City, Isabela → San Mateo, Isabela)', '::1', '2025-08-28 06:58:58'),
(110, 'admin', 'Create Route', 'Route: Cauayan trip (San Mateo, Isabela → Cauayan City, Isabela)', '::1', '2025-08-28 07:36:19'),
(111, 'admin', 'Create Route', 'Reverse Route: Cauayan City, Isabela to San Mateo, Isabela (Cauayan City, Isabela → San Mateo, Isabela)', '::1', '2025-08-28 07:36:19'),
(112, 'admin', 'Create Driver', 'Driver: Patrick Miguel Blas (Code: DRV20253934)', '::1', '2025-08-28 08:36:34'),
(113, 'admin', 'Create Bus', 'Bus: 07 - 1234567', '::1', '2025-08-28 08:36:50'),
(114, 'admin', 'Update Route Status', 'Route ID: 16 (Cauayan trip) → inactive', '::1', '2025-08-28 08:46:54'),
(115, 'admin', 'Update Route Status', 'Route ID: 16 (Cauayan trip) → active', '::1', '2025-08-28 08:46:57'),
(116, 'admin', 'Assign Bus', 'Bus ID 1 assigned to Route ID 16 (single)', '::1', '2025-08-28 08:55:04'),
(117, 'admin', 'Logout', NULL, '::1', '2025-08-28 08:55:57'),
(118, 'admin', 'Logout', NULL, '::1', '2025-08-29 06:13:50'),
(119, 'admin', 'Logout', NULL, '::1', '2025-08-29 06:30:55'),
(120, 'admin', 'Assign Bus to Route', 'Bus #07 (1234567) assigned to Route ID: 17 (Cauayan City, Isabela to San Mateo, Isabela)', '::1', '2025-08-29 07:21:40'),
(121, 'admin', 'Update Bus', 'Bus: #07 (1234567) - ID: 1', '::1', '2025-08-29 07:21:40'),
(122, 'admin', 'Update Route Status', 'Route ID: 16 → inactive', '::1', '2025-08-29 07:27:53'),
(123, 'admin', 'Logout', NULL, '::1', '2025-08-29 07:29:46'),
(124, 'admin', 'Create Route', 'Route: Cauayan trip 2 (Cauayan, Isabela, Cagayan Valley, 3305, Philippines → San Mateo, Isabela, Cagayan Valley, Philippines)', '::1', '2025-08-29 13:21:08'),
(125, 'admin', 'Create Route', 'Reverse Route: San Mateo, Isabela, Cagayan Valley, Philippines to Cauayan, Isabela, Cagayan Valley, 3305, Philippines', '::1', '2025-08-29 13:21:08'),
(126, 'admin', 'Unassign Bus', 'Bus unassigned from Route ID 17', '::1', '2025-08-29 13:29:32'),
(127, 'admin', 'Create Route', 'Route: Cauayan trip (San Mateo, Isabela, Cagayan Valley, Philippines → SM City Cauayan, P. Burgos Street, District II, Cauayan, Isabela, Cagayan Valley, 3305, Philippines)', '::1', '2025-08-29 13:30:45'),
(128, 'admin', 'Create Route', 'Reverse Route: SM City Cauayan, P. Burgos Street, District II, Cauayan, Isabela, Cagayan Valley, 3305, Philippines to San Mateo, Isabela, Cagayan Valley, Philippines', '::1', '2025-08-29 13:30:45'),
(129, 'admin', 'Create Route', 'Route: Cauayan trip (San Mateo, Isabela, Cagayan Valley, Philippines → SM City Cauayan, P. Burgos Street, District II, Cauayan, Isabela, Cagayan Valley, 3305, Philippines)', '::1', '2025-08-29 13:32:34'),
(130, 'admin', 'Create Route', 'Reverse Route: SM City Cauayan, P. Burgos Street, District II, Cauayan, Isabela, Cagayan Valley, 3305, Philippines to San Mateo, Isabela, Cagayan Valley, Philippines', '::1', '2025-08-29 13:32:34'),
(131, 'admin', 'Create Route', 'Route: Cauayan trip (San Mateo, Isabela, Cagayan Valley, Philippines → SM City Cauayan, P. Burgos Street, District II, Cauayan, Isabela, Cagayan Valley, 3305, Philippines)', '::1', '2025-08-29 13:40:15'),
(132, 'admin', 'Create Route', 'Reverse Route: SM City Cauayan, P. Burgos Street, District II, Cauayan, Isabela, Cagayan Valley, 3305, Philippines to San Mateo, Isabela, Cagayan Valley, Philippines', '::1', '2025-08-29 13:40:15'),
(133, 'admin', 'Create Route', 'Route: Cauayan trip (San Mateo, Isabela, Cagayan Valley, Philippines → SM City Cauayan, P. Burgos Street, District II, Cauayan, Isabela, Cagayan Valley, 3305, Philippines)', '::1', '2025-08-29 13:44:30'),
(134, 'admin', 'Assign Bus', 'Bus ID 1 assigned to Route ID 26 (single)', '::1', '2025-08-29 13:45:03'),
(135, 'admin', 'Create Route', 'Route: San Mateo trip (Cauayan, Isabela, Cagayan Valley, 3305, Philippines → San Mateo, Isabela, Cagayan Valley, Philippines)', '::1', '2025-08-29 13:45:55'),
(136, 'admin', 'Assign Bus', 'Bus ID 1 assigned to Route ID 27 (single)', '::1', '2025-08-29 13:46:05'),
(137, 'admin', 'Assign Bus', 'Bus ID 1 assigned to Route ID 26 (single)', '::1', '2025-08-29 13:46:39'),
(138, 'admin', 'Update Route Status', 'Route ID: 27 → inactive', '::1', '2025-08-29 14:25:02'),
(139, 'admin', 'Update Route Status', 'Route ID: 27 → active', '::1', '2025-08-29 14:25:04'),
(140, 'admin', 'Deactivate Driver', 'Driver ID: 6', '::1', '2025-08-29 14:27:53'),
(141, 'admin', 'Delete Bus', 'Bus ID: 1', '::1', '2025-08-29 14:27:57'),
(142, 'admin', 'Create Bus', 'Bus: 007 - 1234567', '::1', '2025-08-29 14:28:11'),
(143, 'admin', 'Activate Driver', 'Driver ID: 6', '::1', '2025-08-29 14:28:20'),
(144, 'admin', 'Update Driver', 'Driver: Patrick Miguel Blas (ID: 6)', '::1', '2025-08-29 14:29:30'),
(145, 'admin', 'Assign Bus to Route', 'Bus #007 (1234567) assigned to Route ID: 26 (Cauayan trip)', '::1', '2025-08-29 14:29:58'),
(146, 'admin', 'Update Bus', 'Bus: #007 (1234567) - ID: 2', '::1', '2025-08-29 14:29:58'),
(147, 'admin', 'Update Bus', 'Bus: #007 (1234567) - ID: 2', '::1', '2025-08-29 14:30:25'),
(148, 'admin', 'Assign Bus', 'Bus ID 2 assigned to Route ID 26', '::1', '2025-08-29 14:30:48'),
(149, 'admin', 'Update Route Status', 'Route ID: 27 → inactive', '::1', '2025-08-29 14:32:26'),
(150, 'admin', 'Update Route Status', 'Route ID: 27 → active', '::1', '2025-08-29 14:32:28'),
(151, 'admin', 'Create Bus', 'Bus: 008 - 1234566', '::1', '2025-08-29 14:33:57'),
(152, 'admin', 'Create Bus', 'Bus: 00 - 1234565', '::1', '2025-08-29 14:34:15'),
(153, 'admin', 'Create Driver', 'Driver: Nicole Mahilum (Code: DRV20254958)', '::1', '2025-08-29 14:34:48'),
(154, 'admin', 'Update Driver', 'Driver: Patrick Miguel Blas (ID: 6)', '::1', '2025-08-29 14:35:00'),
(155, 'admin', 'Create Driver', 'Driver: Mharian (Code: DRV20250557)', '::1', '2025-08-29 14:35:30'),
(156, 'admin', 'Assign Bus', 'Bus ID 3 assigned to Route ID 26', '::1', '2025-08-29 14:35:49'),
(157, 'admin', 'Assign Bus', 'Bus ID 4 assigned to Route ID 26', '::1', '2025-08-29 14:35:56'),
(158, 'admin', 'Delete Bus', 'Bus ID: 2', '::1', '2025-08-29 14:37:09'),
(159, 'admin', 'Delete Bus', 'Bus ID: 3', '::1', '2025-08-29 14:37:12'),
(160, 'admin', 'Delete Bus', 'Bus ID: 4', '::1', '2025-08-29 14:37:15'),
(161, 'admin', 'Create Bus', 'Bus: 007 - 1234567', '::1', '2025-08-29 14:37:34'),
(162, 'admin', 'Update Bus', 'Bus: #007 (1234567) - ID: 5', '::1', '2025-08-29 14:37:42'),
(163, 'admin', 'Update Bus', 'Bus: #007 (1) - ID: 5', '::1', '2025-08-29 14:38:02'),
(164, 'admin', 'Create Bus', 'Bus: 008 - 2', '::1', '2025-08-29 14:38:16'),
(165, 'admin', 'Create Bus', 'Bus: 009 - 3', '::1', '2025-08-29 14:38:27'),
(166, 'admin', 'Update Driver', 'Driver: Patrick Miguel Blas (ID: 6)', '::1', '2025-08-29 14:38:54'),
(167, 'admin', 'Update Driver', 'Driver: Patrick Miguel Blas (ID: 6)', '::1', '2025-08-29 14:39:26'),
(168, 'admin', 'Assign Bus', 'Bus ID 5 assigned to Route ID 26', '::1', '2025-08-29 14:39:36'),
(169, 'admin', 'Assign Bus', 'Bus ID 6 assigned to Route ID 26', '::1', '2025-08-29 14:39:43'),
(170, 'admin', 'Assign Bus', 'Bus ID 7 assigned to Route ID 26', '::1', '2025-08-29 14:39:48'),
(171, 'admin', 'Update Bus', 'Bus: #009 (3) - ID: 7', '::1', '2025-08-29 14:40:26'),
(172, 'admin', 'Update Bus', 'Bus: #009 (3) - ID: 7', '::1', '2025-08-29 14:41:21'),
(173, 'admin', 'Update Bus', 'Bus: #009 (3) - ID: 7', '::1', '2025-08-29 14:41:48'),
(174, 'admin', 'Update Bus', 'Bus: #008 (2) - ID: 6', '::1', '2025-08-29 14:41:57'),
(175, 'admin', 'Update Bus', 'Bus: #007 (1) - ID: 5', '::1', '2025-08-29 14:42:09'),
(176, 'admin', 'Update Bus', 'Bus: #007 (1) - ID: 5', '::1', '2025-08-29 14:42:11'),
(177, 'admin', 'Unassign Bus from Route', 'Bus #007 (1) unassigned from route', '::1', '2025-08-29 14:42:18'),
(178, 'admin', 'Update Bus', 'Bus: #007 (1) - ID: 5', '::1', '2025-08-29 14:42:18'),
(179, 'admin', 'Update Bus', 'Bus: #007 (1) - ID: 5', '::1', '2025-08-29 14:42:22'),
(180, 'admin', 'Unassign Bus from Route', 'Bus #008 (2) unassigned from route', '::1', '2025-08-29 14:42:27'),
(181, 'admin', 'Update Bus', 'Bus: #008 (2) - ID: 6', '::1', '2025-08-29 14:42:27'),
(182, 'admin', 'Unassign Bus from Route', 'Bus #009 (3) unassigned from route', '::1', '2025-08-29 14:42:35'),
(183, 'admin', 'Update Bus', 'Bus: #009 (3) - ID: 7', '::1', '2025-08-29 14:42:35'),
(184, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:43:33'),
(185, 'admin', 'Update Bus', 'Bus: #007 (1) - ID: 5', '::1', '2025-08-29 14:43:48'),
(186, 'admin', 'Update Bus', 'Bus: #007 (1) - ID: 5', '::1', '2025-08-29 14:45:46'),
(187, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:47:38'),
(188, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:48:01'),
(189, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:48:12'),
(190, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:48:45'),
(191, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:49:05'),
(192, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:50:35'),
(193, 'admin', 'Update Bus', 'Bus: #009 (3) - ID: 7', '::1', '2025-08-29 14:50:47'),
(194, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:50:59'),
(195, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:51:49'),
(196, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:52:22'),
(197, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:52:38'),
(198, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:53:00'),
(199, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:53:11'),
(200, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:55:04'),
(201, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:55:19'),
(202, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:55:33'),
(203, 'admin', 'Deactivate Driver', 'Driver ID: 8', '::1', '2025-08-29 14:55:36'),
(204, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:56:02'),
(205, 'admin', 'Activate Driver', 'Driver ID: 8', '::1', '2025-08-29 14:58:04'),
(206, 'admin', 'Update Route Status', 'Route ID: 26 → inactive', '::1', '2025-08-29 14:58:11'),
(207, 'admin', 'Update Route Status', 'Route ID: 26 → active', '::1', '2025-08-29 14:58:14'),
(208, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-08-29 14:59:00'),
(209, 'admin', 'Assign Bus', 'Bus ID 5 assigned to Route ID 27', '::1', '2025-08-29 15:21:51'),
(210, 'admin', 'Assign Bus', 'Bus ID 6 assigned to Route ID 27', '::1', '2025-08-29 15:21:56'),
(211, 'admin', 'Update Driver', 'Driver: Nicole Mahilum (ID: 7)', '::1', '2025-08-29 15:22:44'),
(212, 'admin', 'Assign Bus', 'Bus ID 7 assigned to Route ID 27', '::1', '2025-08-29 15:22:49'),
(213, 'admin', 'Logout', NULL, '::1', '2025-08-29 15:25:14'),
(214, 'admin', 'Logout', NULL, '::1', '2025-08-29 17:16:57'),
(215, 'admin', 'Update Driver', 'Driver: Patrick Miguel Blas (ID: 6)', '::1', '2025-08-29 21:33:43'),
(216, 'admin', 'Logout', NULL, '::1', '2025-08-29 21:33:56'),
(217, 'admin', 'Create Bus', 'Bus: 010 - 12345670', '::1', '2025-08-30 04:42:01'),
(218, 'admin', 'Create Driver', 'Driver: batman (Code: DRV20252503)', '::1', '2025-08-30 04:42:42'),
(219, 'admin', 'Assign Bus', 'Bus ID 8 assigned to Route ID 27', '::1', '2025-08-30 04:43:20'),
(220, 'admin', 'Bus Maintenance', 'Bus ID: 8', '::1', '2025-08-30 04:59:12'),
(221, 'admin', 'Bus Maintenance', 'Bus ID: 8', '::1', '2025-08-30 04:59:13'),
(222, 'admin', 'Bus Maintenance', 'Bus ID: 8', '::1', '2025-08-30 04:59:28'),
(223, 'admin', 'Bus Maintenance', 'Bus ID: 8', '::1', '2025-08-30 04:59:29'),
(224, 'admin', 'Bus Maintenance', 'Bus ID: 8', '::1', '2025-08-30 04:59:29'),
(225, 'admin', 'Bus Maintenance', 'Bus ID: 8', '::1', '2025-08-30 04:59:39'),
(226, 'admin', 'Bus Maintenance', 'Bus ID: 8', '::1', '2025-08-30 05:00:10'),
(227, 'admin', 'Bus Maintenance', 'Bus ID: 8', '::1', '2025-08-30 05:00:11'),
(228, 'admin', 'Activate Bus', 'Bus ID: 8', '::1', '2025-08-30 05:00:16'),
(229, 'admin', 'Bus Maintenance', 'Bus ID: 8', '::1', '2025-08-30 05:00:24'),
(230, 'admin', 'Deactivate Driver', 'Driver ID: 9', '::1', '2025-08-30 05:04:40'),
(231, 'admin', 'Activate Driver', 'Driver ID: 9', '::1', '2025-08-30 05:05:37'),
(232, 'admin', 'Deactivate Driver', 'Driver ID: 9', '::1', '2025-08-30 05:05:53'),
(233, 'admin', 'Deactivate Driver', 'Driver ID: 9', '::1', '2025-08-30 05:06:31'),
(234, 'admin', 'Deactivate Driver', 'Driver ID: 9', '::1', '2025-08-30 05:06:32'),
(235, 'admin', 'Deactivate Driver', 'Driver ID: 9', '::1', '2025-08-30 05:06:32'),
(236, 'admin', 'Deactivate Driver', 'Driver ID: 9', '::1', '2025-08-30 05:06:33'),
(237, 'admin', 'Deactivate Driver', 'Driver ID: 9', '::1', '2025-08-30 05:06:33'),
(238, 'admin', 'Activate Driver', 'Driver ID: 9', '::1', '2025-08-30 05:07:22'),
(239, 'admin', 'Bus Maintenance', 'Bus ID: 8', '::1', '2025-08-30 05:07:42'),
(240, 'admin', 'Activate Bus', 'Bus ID: 8', '::1', '2025-08-30 05:07:44'),
(241, 'admin', 'Activate Bus', 'Bus ID: 8', '::1', '2025-08-30 05:39:30'),
(242, 'admin', 'Activate Driver', 'Driver ID: 9', '::1', '2025-08-30 05:40:04'),
(243, 'admin', 'Logout', NULL, '::1', '2025-08-30 05:40:08'),
(244, 'admin', 'Deactivate Driver', 'Driver ID: 9', '::1', '2025-08-30 06:31:01'),
(245, 'admin', 'Deactivate Driver', 'Driver ID: 9', '::1', '2025-08-30 06:31:07'),
(246, 'admin', 'Activate Driver', 'Driver ID: 9', '::1', '2025-08-30 06:31:17'),
(247, 'admin', 'Bus Maintenance', 'Bus ID: 8', '::1', '2025-08-30 06:31:35'),
(248, 'admin', 'Activate Bus', 'Bus ID: 8', '::1', '2025-08-30 06:31:46'),
(249, 'admin', 'Deactivate Bus', 'Bus ID: 8', '::1', '2025-08-30 06:31:50'),
(250, 'admin', 'Deactivate Driver', 'Driver ID: 9', '::1', '2025-08-30 06:32:21'),
(251, 'admin', 'Deactivate Driver', 'Driver ID: 9', '::1', '2025-08-30 06:32:23'),
(252, 'admin', 'Activate Driver', 'Driver ID: 9', '::1', '2025-08-30 06:32:31'),
(253, 'admin', 'Activate Driver', 'Driver ID: 9', '::1', '2025-08-30 06:32:42'),
(254, 'admin', 'Activate Driver', 'Driver ID: 9', '::1', '2025-08-30 06:43:35'),
(255, 'admin', 'Logout', NULL, '::1', '2025-08-30 06:46:24'),
(256, 'admin', 'Update Driver', 'Driver: Mharian (ID: 8)', '::1', '2025-09-02 07:59:32'),
(257, 'admin', 'Update Driver', 'Driver: Nicole Mahilum (ID: 7)', '::1', '2025-09-02 07:59:43'),
(258, 'admin', 'Delete Bus', 'Bus ID: 7', '::1', '2025-09-02 07:59:51'),
(259, 'admin', 'Delete Bus', 'Bus ID: 5', '::1', '2025-09-02 07:59:57'),
(260, 'admin', 'Update Driver', 'Driver: batman (ID: 9)', '::1', '2025-09-02 08:00:20'),
(261, 'admin', 'Delete Bus', 'Bus ID: 8', '::1', '2025-09-02 08:00:28'),
(262, 'admin', 'Bus Maintenance', 'Bus ID: 6', '::1', '2025-09-02 08:01:54'),
(263, 'admin', 'Activate Bus', 'Bus ID: 6', '::1', '2025-09-02 08:01:58'),
(264, 'admin', 'Logout', NULL, '49.151.88.244', '2025-09-24 20:17:57'),
(265, 'admin', 'Logout', NULL, '49.151.88.244', '2025-09-24 20:18:13'),
(266, 'admin', 'Logout', NULL, '49.151.88.244', '2025-10-05 05:33:49'),
(267, 'admin', 'Logout', NULL, '49.151.88.244', '2025-10-05 05:39:45');

-- --------------------------------------------------------

--
-- Table structure for table `admin_sessions`
--

CREATE TABLE `admin_sessions` (
  `session_id` varchar(255) NOT NULL,
  `admin_username` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_sessions`
--

INSERT INTO `admin_sessions` (`session_id`, `admin_username`, `created_at`, `expires_at`, `ip_address`, `user_agent`) VALUES
('391deff01fb1412180d5bf59da099ea3381b0620bb84593be18b010bdf583330', 'admin', '2025-08-20 20:52:26', '2025-08-20 21:52:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
('426b931927ade0642cec07bcc165d234a3f79937372e443439060f4785b677f2', 'admin', '2025-08-31 15:02:17', '2025-08-31 16:02:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
('47b2bc1b795045ef6486015e52507d3180cc4aca8b525d86659801c038529f0e', 'admin', '2025-08-19 22:26:54', '2025-08-19 23:28:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
('54fcb32fedda2e23a424d533c1660e5d7e986683ce1848fcee67719e7d12a365', 'admin', '2025-08-28 13:43:20', '2025-08-28 14:45:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
('85843e513e72f3e8d1f0257bb626978a59ce4273211e9c0825f38c42010daa80', 'admin', '2025-08-21 21:57:10', '2025-08-21 22:57:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
('965f35856db88dc274964ddc07c0766d9db528a0083719084748c051077c1171', 'admin', '2025-08-19 21:39:06', '2025-08-19 23:25:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
('c19fa88865d979431e7fe53bc88025f39d5c4a4fef004f5a2f05b854c01255dc', 'admin', '2025-10-01 13:32:30', '2025-10-01 14:33:27', '180.191.16.93', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36'),
('c281f0a5c6ae237524f71687c16e3ddc95f8259bb902ccc9248699d7270ba195', 'admin', '2025-09-02 15:31:38', '2025-09-02 17:01:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
('c8051b9bcd2e6618bc400ce6c4efd6c0d6b4b458489b51d6f2c09d1d40a2834f', 'admin', '2025-08-21 10:15:56', '2025-08-21 11:15:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `booking_reference` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `trip_id` int(11) DEFAULT NULL,
  `seat_number` int(11) NOT NULL,
  `travel_date` varchar(50) NOT NULL,
  `departure_time` time NOT NULL,
  `fare` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','cancelled','refunded') DEFAULT 'pending',
  `booking_status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `payment_reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pickup_stop` varchar(100) DEFAULT NULL,
  `destination_stop` varchar(100) DEFAULT NULL,
  `boarded` tinyint(1) DEFAULT 0,
  `driver_approved` tinyint(1) DEFAULT 0,
  `driver_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `booking_reference`, `user_id`, `bus_id`, `route_id`, `trip_id`, `seat_number`, `travel_date`, `departure_time`, `fare`, `payment_status`, `booking_status`, `payment_reference`, `created_at`, `updated_at`, `pickup_stop`, `destination_stop`, `boarded`, `driver_approved`, `driver_id`) VALUES
(35, 'BUS202509021D6426', 3, 6, 27, 40, 1, '0000-00-00', '02:09:26', 50.00, 'pending', 'cancelled', NULL, '2025-09-01 18:10:25', '2025-09-01 18:11:53', NULL, NULL, 0, 0, 6),
(36, 'BUS2025090214BEAA', 3, 6, 27, 40, 1, '0000-00-00', '02:09:26', 50.00, 'pending', 'cancelled', NULL, '2025-09-01 18:12:01', '2025-09-01 18:13:02', NULL, NULL, 0, 0, 6),
(37, 'BUS2025090216F295', 3, 6, 27, 40, 1, '0000-00-00', '02:09:26', 50.00, 'pending', 'cancelled', NULL, '2025-09-01 18:13:21', '2025-09-01 18:19:49', NULL, NULL, 0, 0, 6),
(38, 'BUS20250902DE8FE0', 3, 6, 27, 40, 1, '2014', '02:09:26', 50.00, 'pending', 'cancelled', NULL, '2025-09-01 18:20:13', '2025-09-01 18:28:43', NULL, NULL, 0, 1, 6),
(39, 'BUS20250902CB2B08', 3, 6, 27, 40, 1, '2025-09-02', '02:09:26', 50.00, 'pending', 'completed', NULL, '2025-09-01 18:29:00', '2025-09-01 18:47:02', NULL, NULL, 1, 1, 6),
(40, 'BUS20250902E87300', 3, 6, 26, 41, 1, '2025-09-02', '02:50:25', 50.00, 'pending', 'completed', NULL, '2025-09-01 18:50:38', '2025-09-01 18:51:58', NULL, NULL, 1, 1, 6),
(41, 'BUS2025090280F879', 3, 6, 27, 42, 1, '2025-09-02', '02:52:00', 50.00, 'pending', 'completed', NULL, '2025-09-01 18:52:08', '2025-09-02 16:41:10', NULL, NULL, 1, 1, 6),
(42, 'BUS2025090377D279', 3, 6, 27, 44, 1, '2025-09-03', '01:56:58', 50.00, 'pending', 'cancelled', NULL, '2025-09-02 17:57:27', '2025-09-02 18:11:12', NULL, NULL, 0, 0, NULL),
(43, 'BUS20251005DEEE82', 3, 6, 27, 46, 1, '2025-10-05', '13:29:01', 50.00, 'pending', 'confirmed', NULL, '2025-10-05 05:29:33', '2025-10-05 05:31:24', NULL, NULL, 1, 1, 6);

-- --------------------------------------------------------

--
-- Table structure for table `buses`
--

CREATE TABLE `buses` (
  `bus_id` int(11) NOT NULL,
  `bus_number` varchar(50) NOT NULL,
  `plate_number` varchar(50) NOT NULL,
  `capacity` int(11) NOT NULL,
  `status` enum('available','assigned','on_trip','maintenance') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `assigned_route_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buses`
--

INSERT INTO `buses` (`bus_id`, `bus_number`, `plate_number`, `capacity`, `status`, `created_at`, `updated_at`, `assigned_route_id`) VALUES
(6, '008', '2', 20, 'available', '2025-08-29 14:38:16', '2025-10-05 05:31:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bus_locations`
--

CREATE TABLE `bus_locations` (
  `location_id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `speed` decimal(5,2) DEFAULT 0.00,
  `heading` decimal(5,2) DEFAULT 0.00,
  `accuracy` decimal(8,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bus_locations`
--

INSERT INTO `bus_locations` (`location_id`, `bus_id`, `latitude`, `longitude`, `speed`, `heading`, `accuracy`, `updated_at`, `last_updated`) VALUES
(3278, 6, 16.87552000, 121.59549440, 0.00, 0.00, 1008.65, '2025-10-05 05:31:50', '2025-10-05 05:31:50');

-- --------------------------------------------------------

--
-- Table structure for table `bus_route_assignments`
--

CREATE TABLE `bus_route_assignments` (
  `id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bus_route_assignments`
--

INSERT INTO `bus_route_assignments` (`id`, `bus_id`, `route_id`, `assigned_at`) VALUES
(5, 6, 26, '2025-08-29 14:39:43'),
(8, 6, 27, '2025-08-29 15:21:56');

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `driver_id` int(11) NOT NULL,
  `driver_code` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `mobile_number` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `license_number` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `assigned_bus_id` int(11) DEFAULT NULL,
  `status` enum('pending','active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by_admin` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`driver_id`, `driver_code`, `full_name`, `mobile_number`, `email`, `license_number`, `password`, `assigned_bus_id`, `status`, `created_at`, `updated_at`, `created_by_admin`) VALUES
(6, 'DRV20253934', 'Sample Driver', '09602618779', 'driver@gmail.com', '12312313123', '$2y$10$LJ4kwndAjG4VeWiJDuyoSeTn.QAwdBGWjLjbObpETclOQkhvP1aCq', 6, 'active', '2025-08-28 08:36:34', '2025-10-05 05:37:59', 1),
(7, 'DRV20254958', 'Jane Smith', '09602618772', 'janesmith@gmail.com', '12312313121', '$2y$10$nl.F/7Sn4btPLhxe.4SeZuGU4ZR8GEYyPce8o9nOyT/kM5INp2GQm', NULL, 'active', '2025-08-29 14:34:48', '2025-10-05 05:38:42', 1),
(8, 'DRV20250557', 'Mharian', '09602618778', 'mharian@gmail.com', '12312313124', '$2y$10$KIRIXcJ46sdbNGgzUv6ZDOezqY73Pij/Q8HqqXBDo02wjoGM4M66i', NULL, 'active', '2025-08-29 14:35:30', '2025-09-02 07:59:32', 1),
(9, 'DRV20252503', 'batman', '09602618777', 'batman@gmail.com', '77777', '$2y$10$mg3tw4BULXgzo.mMbSIFvOI8yS8.CzFvvynhafYK4eq1QTm93.guu', NULL, 'active', '2025-08-30 04:42:42', '2025-09-02 08:00:20', 1);

-- --------------------------------------------------------

--
-- Table structure for table `passenger_locations`
--

CREATE TABLE `passenger_locations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `booking_reference` varchar(50) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `speed` float DEFAULT 0,
  `heading` float DEFAULT 0,
  `accuracy` float DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `passenger_locations`
--

INSERT INTO `passenger_locations` (`id`, `user_id`, `booking_id`, `bus_id`, `booking_reference`, `latitude`, `longitude`, `speed`, `heading`, `accuracy`, `updated_at`) VALUES
(374, 3, 41, 6, 'BUS2025090280F879', 16.88451694, 121.59275054, 0, 0, 0, '2025-09-01 19:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('gcash_qr') DEFAULT 'gcash_qr',
  `qr_code_data` text DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('pending','completed','failed') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `routes`
--

CREATE TABLE `routes` (
  `route_id` int(11) NOT NULL,
  `route_name` varchar(100) NOT NULL,
  `origin` varchar(100) NOT NULL,
  `destination` varchar(100) NOT NULL,
  `distance_km` decimal(8,2) DEFAULT NULL,
  `estimated_duration_minutes` int(11) DEFAULT NULL,
  `fare` decimal(10,2) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `origin_lat` decimal(10,8) DEFAULT NULL,
  `origin_lng` decimal(11,8) DEFAULT NULL,
  `dest_lat` decimal(10,8) DEFAULT NULL,
  `dest_lng` decimal(11,8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `routes`
--

INSERT INTO `routes` (`route_id`, `route_name`, `origin`, `destination`, `distance_km`, `estimated_duration_minutes`, `fare`, `status`, `created_at`, `origin_lat`, `origin_lng`, `dest_lat`, `dest_lng`) VALUES
(26, 'Cauayan trip', 'San Mateo, Isabela, Cagayan Valley, Philippines', 'SM City Cauayan, P. Burgos Street, District II, Cauayan, Isabela, Cagayan Valley, 3305, Philippines', 23.40, 31, 50.00, 'active', '2025-08-29 13:44:29', NULL, NULL, NULL, NULL),
(27, 'San Mateo trip', 'Cauayan, Isabela, Cagayan Valley, 3305, Philippines', 'San Mateo, Isabela, Cagayan Valley, Philippines', 23.50, 29, 50.00, 'active', '2025-08-29 13:45:55', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `trips`
--

CREATE TABLE `trips` (
  `trip_id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `scheduled_departure` datetime NOT NULL,
  `actual_departure` datetime DEFAULT NULL,
  `estimated_arrival` datetime DEFAULT NULL,
  `actual_arrival` datetime DEFAULT NULL,
  `status` enum('scheduled','boarding','on_trip','completed','cancelled') DEFAULT 'scheduled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trip_schedules`
--

CREATE TABLE `trip_schedules` (
  `schedule_id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `bus_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `departure_time` time NOT NULL,
  `arrival_time` time NOT NULL,
  `days_of_week` varchar(255) NOT NULL DEFAULT 'monday,tuesday,wednesday,thursday,friday,saturday,sunday',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `mobile_number` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `mobile_number`, `password`, `full_name`, `email`, `created_at`, `updated_at`, `status`) VALUES
(3, '09602618771', '$2y$10$mG9Yo1lEEao9zKJeTPmX/.Efeu4L2Z5ZqeWjqqa9xS4z2Sc0DutKS', 'Mharian', 'mharian@gmail.com', '2025-08-27 13:37:19', '2025-08-27 13:37:19', 'active'),
(4, '09602618779', '$2y$10$L.bExXOYgzoXRTrKeElxAuSDPINjsYEu6HTF7er7Xoi8vpMyW/Xtq', 'Sample', 'sample@gmail.com', '2025-09-02 17:04:56', '2025-10-05 05:37:16', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `active_trips`
--
ALTER TABLE `active_trips`
  ADD PRIMARY KEY (`trip_id`),
  ADD KEY `idx_active_trips_date_status` (`scheduled_departure`,`status`);

--
-- Indexes for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_admin_username` (`admin_username`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD UNIQUE KEY `booking_reference` (`booking_reference`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `route_id` (`route_id`),
  ADD KEY `idx_bookings_trip` (`trip_id`),
  ADD KEY `idx_bookings_travel_date` (`travel_date`),
  ADD KEY `idx_bookings_payment_status` (`payment_status`),
  ADD KEY `bookings_ibfk_2` (`bus_id`),
  ADD KEY `idx_bookings_travel_date_status` (`travel_date`,`booking_status`),
  ADD KEY `fk_bookings_driver` (`driver_id`);

--
-- Indexes for table `buses`
--
ALTER TABLE `buses`
  ADD PRIMARY KEY (`bus_id`);

--
-- Indexes for table `bus_locations`
--
ALTER TABLE `bus_locations`
  ADD PRIMARY KEY (`location_id`),
  ADD UNIQUE KEY `idx_bus_location` (`bus_id`),
  ADD KEY `idx_location_time` (`updated_at`),
  ADD KEY `idx_bus_locations_updated` (`updated_at`);

--
-- Indexes for table `bus_route_assignments`
--
ALTER TABLE `bus_route_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bus_id` (`bus_id`,`route_id`),
  ADD KEY `route_id` (`route_id`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`driver_id`),
  ADD UNIQUE KEY `driver_code` (`driver_code`),
  ADD UNIQUE KEY `mobile_number` (`mobile_number`);

--
-- Indexes for table `passenger_locations`
--
ALTER TABLE `passenger_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_user_id` (`user_id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`booking_id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_bus_id` (`bus_id`),
  ADD KEY `idx_updated_at` (`updated_at`),
  ADD KEY `idx_booking_reference` (`booking_reference`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `routes`
--
ALTER TABLE `routes`
  ADD PRIMARY KEY (`route_id`);

--
-- Indexes for table `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`trip_id`),
  ADD KEY `route_id` (`route_id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `trips_ibfk_1` (`bus_id`);

--
-- Indexes for table `trip_schedules`
--
ALTER TABLE `trip_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `idx_schedule_route` (`route_id`),
  ADD KEY `idx_schedule_bus` (`bus_id`),
  ADD KEY `idx_schedule_driver` (`driver_id`),
  ADD KEY `idx_departure_time` (`departure_time`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `mobile_number` (`mobile_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `active_trips`
--
ALTER TABLE `active_trips`
  MODIFY `trip_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=268;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `buses`
--
ALTER TABLE `buses`
  MODIFY `bus_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `bus_locations`
--
ALTER TABLE `bus_locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3986;

--
-- AUTO_INCREMENT for table `bus_route_assignments`
--
ALTER TABLE `bus_route_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `driver_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `passenger_locations`
--
ALTER TABLE `passenger_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=447;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `routes`
--
ALTER TABLE `routes`
  MODIFY `route_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `trips`
--
ALTER TABLE `trips`
  MODIFY `trip_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trip_schedules`
--
ALTER TABLE `trip_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`bus_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`route_id`) REFERENCES `routes` (`route_id`),
  ADD CONSTRAINT `bookings_ibfk_4` FOREIGN KEY (`trip_id`) REFERENCES `active_trips` (`trip_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bookings_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`);

--
-- Constraints for table `bus_locations`
--
ALTER TABLE `bus_locations`
  ADD CONSTRAINT `bus_locations_ibfk_1` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`bus_id`) ON DELETE CASCADE;

--
-- Constraints for table `bus_route_assignments`
--
ALTER TABLE `bus_route_assignments`
  ADD CONSTRAINT `bus_route_assignments_ibfk_1` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`bus_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bus_route_assignments_ibfk_2` FOREIGN KEY (`route_id`) REFERENCES `routes` (`route_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`);

--
-- Constraints for table `trips`
--
ALTER TABLE `trips`
  ADD CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`bus_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trips_ibfk_2` FOREIGN KEY (`route_id`) REFERENCES `routes` (`route_id`),
  ADD CONSTRAINT `trips_ibfk_3` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`);

--
-- Constraints for table `trip_schedules`
--
ALTER TABLE `trip_schedules`
  ADD CONSTRAINT `trip_schedules_ibfk_1` FOREIGN KEY (`route_id`) REFERENCES `routes` (`route_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trip_schedules_ibfk_2` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`bus_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trip_schedules_ibfk_3` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
