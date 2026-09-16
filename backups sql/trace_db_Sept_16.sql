-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 16, 2026 at 01:23 PM
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
-- Database: `trace_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admin_user_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_user_id`, `action`, `table_name`, `record_id`, `description`, `created_at`) VALUES
(1, 1, 'logout', 'users', 1, 'Admin signed out.', '2026-05-11 11:30:56'),
(2, 1, 'login', 'users', 1, 'Admin signed in.', '2026-05-11 11:31:18'),
(3, 1, 'login', 'users', 1, 'Admin signed in.', '2026-05-16 01:23:00'),
(4, 1, 'login', 'users', 1, 'Admin signed in.', '2026-09-10 09:19:53'),
(5, 1, 'login', 'users', 1, 'Admin signed in.', '2026-09-15 05:57:36'),
(6, 1, 'logout', 'users', 1, 'Admin signed out.', '2026-09-16 11:07:06'),
(7, 1, 'login', 'users', 1, 'Admin signed in.', '2026-09-16 11:08:47');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `pickup_address` text NOT NULL,
  `dropoff_address` text NOT NULL,
  `pickup_latitude` decimal(10,7) DEFAULT NULL,
  `pickup_longitude` decimal(10,7) DEFAULT NULL,
  `dropoff_latitude` decimal(10,7) DEFAULT NULL,
  `dropoff_longitude` decimal(10,7) DEFAULT NULL,
  `scheduled_date` date NOT NULL,
  `scheduled_time` time NOT NULL,
  `pickup_time` time DEFAULT NULL,
  `dropoff_time` time DEFAULT NULL,
  `adjusted_pickup_time` time DEFAULT NULL,
  `trip_type` enum('one_way','round_trip','recurring') NOT NULL DEFAULT 'one_way',
  `notes` text DEFAULT NULL,
  `booking_status` enum('pending','approved','assigned','driver_arriving','picked_up','in_transit','dropped_off','completed','cancelled') NOT NULL DEFAULT 'pending',
  `assigned_driver_id` bigint(20) UNSIGNED DEFAULT NULL,
  `recurring_group_id` varchar(64) DEFAULT NULL,
  `monthly_plan_id` bigint(20) UNSIGNED DEFAULT NULL,
  `monthly_plan_route_id` bigint(20) UNSIGNED DEFAULT NULL,
  `driver_payout_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `parent_id`, `student_id`, `pickup_address`, `dropoff_address`, `pickup_latitude`, `pickup_longitude`, `dropoff_latitude`, `dropoff_longitude`, `scheduled_date`, `scheduled_time`, `pickup_time`, `dropoff_time`, `adjusted_pickup_time`, `trip_type`, `notes`, `booking_status`, `assigned_driver_id`, `recurring_group_id`, `monthly_plan_id`, `monthly_plan_route_id`, `driver_payout_amount`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Alijis, Bacolod city', 'school gate', NULL, NULL, NULL, NULL, '2026-05-16', '07:00:00', NULL, NULL, NULL, 'recurring', NULL, 'dropped_off', 1, NULL, NULL, NULL, 0.00, '2026-05-13 08:12:50', '2026-05-16 01:13:08'),
(2, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'school gate', NULL, NULL, NULL, NULL, '2026-05-14', '07:00:00', NULL, NULL, NULL, 'one_way', NULL, 'driver_arriving', 2, NULL, NULL, NULL, 0.00, '2026-05-14 05:28:20', '2026-05-14 05:30:23'),
(3, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'school gate', NULL, NULL, NULL, NULL, '2026-05-14', '07:00:00', NULL, NULL, NULL, 'one_way', NULL, 'assigned', 2, NULL, NULL, NULL, 0.00, '2026-05-14 05:28:57', '2026-05-14 05:29:27'),
(4, 3, 3, 'Block 10A, Lot 8, Alijis, Bacolod City', 'School Gate', NULL, NULL, NULL, NULL, '2026-05-14', '07:00:00', NULL, NULL, NULL, 'one_way', NULL, 'pending', 1, NULL, NULL, NULL, 0.00, '2026-05-14 06:02:15', '2026-05-14 06:02:15'),
(35, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-10', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-10-to_school', 1, 2, 45.35, '2026-09-10 11:06:35', '2026-09-10 11:06:35'),
(36, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-11', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-11-to_school', 1, 2, 45.35, '2026-09-10 11:06:35', '2026-09-10 11:06:35'),
(37, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-14', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-14-to_school', 1, 2, 45.35, '2026-09-10 11:06:35', '2026-09-10 11:06:35'),
(38, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-15', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-15-to_school', 1, 2, 45.35, '2026-09-10 11:06:35', '2026-09-10 11:06:35'),
(39, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-16', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-16-to_school', 1, 2, 45.35, '2026-09-10 11:06:35', '2026-09-10 11:06:35'),
(40, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-17', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-17-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(41, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-18', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-18-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(42, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-21', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-21-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(43, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-22', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-22-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(44, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-23', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-23-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(45, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-24', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-24-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(46, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-25', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-25-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(47, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-28', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-28-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(48, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-29', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-29-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(49, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-09-30', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-09-30-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(50, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-01', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-01-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(51, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-02', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-02-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(52, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-05', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-05-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(53, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-06', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-06-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(54, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-07', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-07-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(55, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-08', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-08-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(56, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-09', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-09-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(57, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-12', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-12-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(58, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-13', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-13-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(59, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-14', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-14-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(60, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-15', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-15-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(61, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-16', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-16-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(62, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-19', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-19-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(63, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-20', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-20-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36'),
(64, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6765000, 122.9509000, 10.6624846, 122.9429570, '2026-10-21', '07:00:00', '07:00:00', '07:45:00', NULL, 'recurring', 'monthly payment plan pickup to drop-off', 'pending', NULL, 'plan-1-route-2-2026-10-21-to_school', 1, 2, 45.35, '2026-09-10 11:06:36', '2026-09-10 11:06:36');

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `license_expiry` date NOT NULL,
  `license_photo_path` varchar(255) NOT NULL,
  `license_verified` tinyint(1) NOT NULL DEFAULT 0,
  `vehicle_type` varchar(50) NOT NULL,
  `vehicle_plate_number` varchar(30) NOT NULL,
  `vehicle_model` varchar(100) NOT NULL,
  `vehicle_color` varchar(50) NOT NULL,
  `vehicle_photo_path` varchar(255) DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `is_online` tinyint(1) NOT NULL DEFAULT 0,
  `current_latitude` decimal(10,7) DEFAULT NULL,
  `current_longitude` decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `user_id`, `license_number`, `license_expiry`, `license_photo_path`, `license_verified`, `vehicle_type`, `vehicle_plate_number`, `vehicle_model`, `vehicle_color`, `vehicle_photo_path`, `approval_status`, `is_online`, `current_latitude`, `current_longitude`, `created_at`, `updated_at`) VALUES
(1, 3, 'MN11019911', '2027-05-13', 'storage/uploads/drivers/license_photo-6a043049a33c61.39106505.jpg', 0, 'School Service', 'ABS1263', 'toyota', 'green', 'storage/uploads/vehicles/vehicle_orcr-6a043049a37383.92683752.jpg', 'approved', 0, 10.6763440, 122.9532210, '2026-05-13 08:03:21', '2026-05-16 01:18:40'),
(2, 4, 'MA1036371', '2027-05-13', 'storage/uploads/drivers/license_photo-6a0431e40c6f69.88775383.jpg', 0, 'School Service', 'KAMS1027208', 'honda', 'white', 'storage/uploads/vehicles/vehicle_orcr-6a0431e40d4772.97747669.jpg', 'approved', 0, NULL, NULL, '2026-05-13 08:10:12', '2026-05-14 05:33:06');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sender_user_id` bigint(20) UNSIGNED NOT NULL,
  `receiver_user_id` bigint(20) UNSIGNED NOT NULL,
  `ride_id` bigint(20) UNSIGNED DEFAULT NULL,
  `message_text` text NOT NULL,
  `message_type` enum('text','system','alert') NOT NULL DEFAULT 'text',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_user_id`, `receiver_user_id`, `ride_id`, `message_text`, `message_type`, `is_read`, `created_at`) VALUES
(1, 3, 2, 1, 'okay', 'text', 0, '2026-05-13 08:22:23'),
(2, 2, 6, 1, 'gaano kada james', 'text', 0, '2026-05-14 05:22:25');

-- --------------------------------------------------------

--
-- Table structure for table `monthly_plans`
--

CREATE TABLE `monthly_plans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED NOT NULL,
  `billing_month` date NOT NULL,
  `destination_key` varchar(80) DEFAULT NULL,
  `destination_address` text DEFAULT NULL,
  `destination_latitude` decimal(10,7) DEFAULT NULL,
  `destination_longitude` decimal(10,7) DEFAULT NULL,
  `trip_type` varchar(20) NOT NULL DEFAULT 'one_way',
  `route_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `child_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `service_days` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `gross_daily_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_daily_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monthly_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `scheduled_time` time NOT NULL DEFAULT '07:00:00',
  `return_time` time DEFAULT NULL,
  `status` enum('draft','active','cancelled') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `monthly_plans`
--

INSERT INTO `monthly_plans` (`id`, `parent_id`, `billing_month`, `destination_key`, `destination_address`, `destination_latitude`, `destination_longitude`, `trip_type`, `route_count`, `child_count`, `service_days`, `gross_daily_total`, `discount_amount`, `net_daily_total`, `monthly_amount`, `scheduled_time`, `return_time`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, '2026-09-10', '10.66248,122.94296', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6624846, 122.9429570, 'one_way', 1, 1, 30, 45.35, 0.00, 45.35, 1360.50, '07:00:00', '07:45:00', 'active', '2026-09-10 11:06:33', '2026-09-10 11:06:33');

-- --------------------------------------------------------

--
-- Table structure for table `monthly_plan_refunds`
--

CREATE TABLE `monthly_plan_refunds` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED NOT NULL,
  `monthly_plan_id` bigint(20) UNSIGNED NOT NULL,
  `monthly_plan_route_id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED DEFAULT NULL,
  `scheduled_date` date NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reason` text DEFAULT NULL,
  `reference_number` varchar(80) DEFAULT NULL,
  `status` enum('pending','approved','paid','cancelled') NOT NULL DEFAULT 'approved',
  `refunded_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monthly_plan_routes`
--

CREATE TABLE `monthly_plan_routes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `monthly_plan_id` bigint(20) UNSIGNED NOT NULL,
  `destination_key` varchar(80) NOT NULL,
  `destination_address` text NOT NULL,
  `destination_latitude` decimal(10,7) DEFAULT NULL,
  `destination_longitude` decimal(10,7) DEFAULT NULL,
  `child_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `gross_daily_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_daily_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `monthly_plan_routes`
--

INSERT INTO `monthly_plan_routes` (`id`, `monthly_plan_id`, `destination_key`, `destination_address`, `destination_latitude`, `destination_longitude`, `child_count`, `gross_daily_amount`, `discount_amount`, `net_daily_amount`, `created_at`, `updated_at`) VALUES
(2, 1, '10.66248,122.94296', 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6624846, 122.9429570, 1, 45.35, 0.00, 45.35, '2026-09-10 11:06:35', '2026-09-10 11:06:35');

-- --------------------------------------------------------

--
-- Table structure for table `monthly_plan_route_students`
--

CREATE TABLE `monthly_plan_route_students` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `monthly_plan_route_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `pickup_address` text NOT NULL,
  `pickup_latitude` decimal(10,7) DEFAULT NULL,
  `pickup_longitude` decimal(10,7) DEFAULT NULL,
  `distance_km` decimal(8,2) NOT NULL DEFAULT 0.00,
  `gross_daily_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_daily_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `monthly_plan_route_students`
--

INSERT INTO `monthly_plan_route_students` (`id`, `monthly_plan_route_id`, `student_id`, `pickup_address`, `pickup_latitude`, `pickup_longitude`, `distance_km`, `gross_daily_amount`, `net_daily_amount`, `created_at`) VALUES
(2, 2, 2, 'Juarez Street, Brgy. Alijis, bacolod city ', 10.6765000, 122.9509000, 1.78, 45.35, 45.35, '2026-09-10 11:06:35');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `body` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `type`, `reference_id`, `is_read`, `created_at`) VALUES
(1, 2, 'Student added', 'A student account was linked to your profile.', 'student', 6, 0, '2026-05-13 08:12:08'),
(2, 4, 'New booking request', 'A parent selected you for a student trip.', 'booking', 1, 0, '2026-05-13 08:12:50'),
(3, 2, 'Booking submitted', 'Your booking is waiting for driver approval.', 'booking', 1, 0, '2026-05-13 08:12:50'),
(4, 2, 'Booking approved', 'Your selected driver approved the booking.', 'booking', 1, 0, '2026-05-13 08:13:44'),
(5, 4, 'Booking approved', 'The booking is now assigned to you.', 'booking', 1, 0, '2026-05-13 08:13:44'),
(6, 3, 'Booking transferred', 'A booking was transferred to you.', 'booking', 1, 0, '2026-05-13 08:14:48'),
(7, 4, 'Booking transferred', 'The booking was transferred to joeseph Ramos.', 'booking', 1, 0, '2026-05-13 08:14:48'),
(8, 2, 'Driver changed', 'Your booking was transferred to another approved driver.', 'booking', 1, 0, '2026-05-13 08:14:48'),
(9, 2, 'Ride status updated', 'Ride status is now Driver Arriving.', 'ride', 1, 0, '2026-05-13 08:15:31'),
(10, 2, 'Ride status updated', 'Ride status is now Arrived.', 'ride', 1, 0, '2026-05-13 08:15:37'),
(11, 2, 'Ride status updated', 'Ride status is now Picked Up.', 'ride', 1, 0, '2026-05-13 08:15:40'),
(12, 2, 'Ride status updated', 'Ride status is now In Transit.', 'ride', 1, 0, '2026-05-13 08:15:48'),
(13, 2, 'New message', 'joeseph sent you a message.', 'message', 1, 0, '2026-05-13 08:22:23'),
(14, 6, 'New message', 'kate nicole sent you a message.', 'message', 2, 0, '2026-05-14 05:22:25'),
(15, 2, 'Ride status updated', 'Ride status is now Driver Arriving.', 'ride', 1, 0, '2026-05-14 05:24:16'),
(16, 2, 'Ride status updated', 'Ride status is now Driver Arriving.', 'ride', 1, 0, '2026-05-14 05:24:16'),
(17, 2, 'Ride status updated', 'Ride status is now Driver Arriving.', 'ride', 1, 0, '2026-05-14 05:24:17'),
(18, 2, 'Ride status updated', 'Ride status is now Driver Arriving.', 'ride', 1, 0, '2026-05-14 05:24:18'),
(19, 2, 'Ride status updated', 'Ride status is now Driver Arriving.', 'ride', 1, 0, '2026-05-14 05:24:18'),
(20, 2, 'Ride status updated', 'Ride status is now Arrived.', 'ride', 1, 0, '2026-05-14 05:24:19'),
(21, 2, 'Ride status updated', 'Ride status is now Picked Up.', 'ride', 1, 0, '2026-05-14 05:24:19'),
(22, 7, 'Student added', 'A student account was linked to your profile.', 'student', 8, 0, '2026-05-14 05:27:27'),
(23, 4, 'New booking request', 'A parent selected you for a student trip.', 'booking', 2, 0, '2026-05-14 05:28:20'),
(24, 7, 'Booking submitted', 'Your booking is waiting for driver approval.', 'booking', 2, 0, '2026-05-14 05:28:20'),
(25, 4, 'New booking request', 'A parent selected you for a student trip.', 'booking', 3, 0, '2026-05-14 05:28:57'),
(26, 7, 'Booking submitted', 'Your booking is waiting for driver approval.', 'booking', 3, 0, '2026-05-14 05:28:57'),
(27, 7, 'Booking approved', 'Your selected driver approved the booking.', 'booking', 3, 0, '2026-05-14 05:29:27'),
(28, 4, 'Booking approved', 'The booking is now assigned to you.', 'booking', 3, 0, '2026-05-14 05:29:27'),
(29, 7, 'Booking approved', 'Your selected driver approved the booking.', 'booking', 2, 0, '2026-05-14 05:29:31'),
(30, 4, 'Booking approved', 'The booking is now assigned to you.', 'booking', 2, 0, '2026-05-14 05:29:31'),
(31, 7, 'Ride status updated', 'Ride status is now Driver Arriving.', 'ride', 3, 0, '2026-05-14 05:30:23'),
(32, 27, 'Student added', 'A student account was linked to your profile.', 'student', 28, 0, '2026-05-14 06:01:32'),
(33, 3, 'New booking request', 'A parent selected you for a student trip.', 'booking', 4, 0, '2026-05-14 06:02:15'),
(34, 27, 'Booking submitted', 'Your booking is waiting for driver approval.', 'booking', 4, 0, '2026-05-14 06:02:15'),
(35, 2, 'Ride status updated', 'Ride status is now Dropped Off.', 'ride', 1, 0, '2026-05-14 06:04:16'),
(36, 2, 'Ride status updated', 'Ride status is now Picked Up.', 'ride', 1, 0, '2026-05-14 06:04:16'),
(37, 2, 'Ride status updated', 'Ride status is now Driver Arriving.', 'ride', 1, 0, '2026-05-14 06:04:16'),
(38, 2, 'Ride status updated', 'Ride status is now Picked Up.', 'ride', 1, 0, '2026-05-14 06:04:16'),
(39, 2, 'Ride status updated', 'Ride status is now Arrived.', 'ride', 1, 0, '2026-05-14 06:04:16'),
(40, 2, 'Ride status updated', 'Ride status is now Driver Arriving.', 'ride', 1, 0, '2026-05-14 06:04:16'),
(41, 2, 'Ride status updated', 'Ride status is now Driver Arriving.', 'ride', 1, 0, '2026-05-14 06:04:16'),
(42, 2, 'Ride status updated', 'Ride status is now Arrived.', 'ride', 1, 0, '2026-05-14 06:04:16'),
(43, 2, 'Ride status updated', 'Ride status is now Driver Arriving.', 'ride', 1, 0, '2026-05-14 06:04:16'),
(44, 2, 'Ride status updated', 'Ride status is now Dropped Off.', 'ride', 1, 0, '2026-05-14 06:04:16'),
(45, 2, 'Ride status updated', 'Ride status is now Driver Arriving.', 'ride', 1, 0, '2026-05-14 06:04:16'),
(46, 2, 'Ride status updated', 'Ride status is now Driver Arriving.', 'ride', 1, 0, '2026-05-16 01:13:02'),
(47, 2, 'Ride status updated', 'Ride status is now Arrived.', 'ride', 1, 0, '2026-05-16 01:13:04'),
(48, 2, 'Ride status updated', 'Ride status is now Picked Up.', 'ride', 1, 0, '2026-05-16 01:13:05'),
(49, 2, 'Ride status updated', 'Ride status is now In Transit.', 'ride', 1, 0, '2026-05-16 01:13:06'),
(50, 2, 'Ride status updated', 'Ride status is now Dropped Off.', 'ride', 1, 0, '2026-05-16 01:13:08'),
(51, 2, 'Student updated', 'Student profile details were updated.', 'student', 1, 0, '2026-05-16 01:19:21'),
(52, 7, 'Student updated', 'Student profile details were updated.', 'student', 2, 0, '2026-09-10 11:06:06'),
(53, 7, 'Monthly plan paid', 'Your monthly payment plan is active and daily transport routes were opened for drivers.', 'payment', 1, 0, '2026-09-10 11:06:35'),
(54, 7, 'Monthly plan paid', 'Your monthly payment plan is active and daily transport routes were opened for drivers.', 'payment', 1, 0, '2026-09-10 11:06:36');

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `address` text NOT NULL,
  `address_latitude` decimal(10,7) DEFAULT NULL,
  `address_longitude` decimal(10,7) DEFAULT NULL,
  `valid_id_path` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(150) NOT NULL,
  `emergency_contact_number` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `parents`
--

INSERT INTO `parents` (`id`, `user_id`, `address`, `address_latitude`, `address_longitude`, `valid_id_path`, `emergency_contact_name`, `emergency_contact_number`, `created_at`, `updated_at`) VALUES
(1, 2, 'alijis, Bacolod City ', NULL, NULL, 'storage/uploads/parents/valid_id-6a042f78c1b576.71867299.jpg', 'Emergency contact', '09060557704', '2026-05-13 07:59:52', '2026-05-13 07:59:52'),
(2, 7, 'Juarez St. Brgy. Alijis, Bacolod City', 10.6760000, 122.5620000, 'storage/uploads/parents/valid_id-6a055cf9427640.28909524.jpg', 'Emergency contact', '09708228407', '2026-05-14 05:26:17', '2026-05-14 05:26:17'),
(3, 27, 'Block 10A, Lot 8, Brgy. Alijis, Bacolod City', 10.6393614, 122.9382253, 'storage/uploads/parents/valid_id-6a0564df8fce46.31338021.jpg', 'Emergency contact', '09102240385', '2026-05-14 05:59:59', '2026-05-14 05:59:59');

-- --------------------------------------------------------

--
-- Table structure for table `rides`
--

CREATE TABLE `rides` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `driver_id` bigint(20) UNSIGNED NOT NULL,
  `ride_status` enum('assigned','driver_arriving','arrived','picked_up','in_transit','dropped_off','completed','cancelled') NOT NULL DEFAULT 'assigned',
  `started_at` datetime DEFAULT NULL,
  `arrived_pickup_at` datetime DEFAULT NULL,
  `picked_up_at` datetime DEFAULT NULL,
  `dropped_off_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rides`
--

INSERT INTO `rides` (`id`, `booking_id`, `driver_id`, `ride_status`, `started_at`, `arrived_pickup_at`, `picked_up_at`, `dropped_off_at`, `completed_at`, `cancelled_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'dropped_off', '2026-05-13 16:15:31', '2026-05-13 16:15:37', '2026-05-13 16:15:40', '2026-05-14 14:04:16', NULL, NULL, '2026-05-13 08:13:44', '2026-05-16 01:13:08'),
(2, 3, 2, 'assigned', NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-14 05:29:27', '2026-05-14 05:29:27'),
(3, 2, 2, 'driver_arriving', '2026-05-14 13:30:23', NULL, NULL, NULL, NULL, NULL, '2026-05-14 05:29:31', '2026-05-14 05:30:23');

-- --------------------------------------------------------

--
-- Table structure for table `ride_locations`
--

CREATE TABLE `ride_locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ride_id` bigint(20) UNSIGNED NOT NULL,
  `driver_id` bigint(20) UNSIGNED NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `speed` decimal(6,2) DEFAULT NULL,
  `heading` decimal(6,2) DEFAULT NULL,
  `recorded_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ride_locations`
--

INSERT INTO `ride_locations` (`id`, `ride_id`, `driver_id`, `latitude`, `longitude`, `speed`, `heading`, `recorded_at`, `created_at`) VALUES
(1, 1, 1, 10.6765000, 122.9509000, NULL, NULL, '2026-05-14 05:24:07', '2026-05-14 05:24:07'),
(2, 1, 1, 10.6765000, 122.9509000, NULL, NULL, '2026-05-14 06:02:41', '2026-05-14 06:02:41'),
(3, 1, 1, 10.6763440, 122.9532210, NULL, NULL, '2026-05-14 06:03:56', '2026-05-14 06:04:16');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `code`, `name`, `created_at`) VALUES
(1, 'admin', 'Administrator', '2026-05-11 11:30:45'),
(2, 'driver', 'Driver', '2026-05-11 11:30:45'),
(3, 'parent', 'Parent', '2026-05-11 11:30:45'),
(4, 'student', 'Student', '2026-05-11 11:30:45');

-- --------------------------------------------------------

--
-- Table structure for table `service_payments`
--

CREATE TABLE `service_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED NOT NULL,
  `monthly_plan_id` bigint(20) UNSIGNED DEFAULT NULL,
  `billing_month` date NOT NULL,
  `route_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `service_days` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `daily_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) NOT NULL DEFAULT 'cash',
  `reference_number` varchar(100) DEFAULT NULL,
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `route_snapshot` text DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_payments`
--

INSERT INTO `service_payments` (`id`, `parent_id`, `monthly_plan_id`, `billing_month`, `route_count`, `service_days`, `daily_total`, `amount_due`, `amount_paid`, `payment_method`, `reference_number`, `status`, `route_snapshot`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '2026-09-10', 1, 30, 45.35, 1360.50, 1360.50, 'cash', '', 'paid', '[{\"destinationKey\":\"10.66248,122.94296\",\"destinationAddress\":\"Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines\",\"destinationLatitude\":10.6624846,\"destinationLongitude\":122.942957,\"children\":[{\"studentId\":2,\"studentName\":\"Benedict Sonio\",\"pickupAddress\":\"Juarez Street, Brgy. Alijis, bacolod city \",\"pickupLatitude\":10.6765,\"pickupLongitude\":122.9509,\"dropoffAddress\":\"Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines\",\"distanceKm\":1.78,\"grossDailyAmount\":45.35,\"discountAmount\":0,\"discountRate\":0,\"netDailyAmount\":45.35,\"routeComplete\":true,\"monthlyAmount\":1360.5}],\"childCount\":1,\"grossDailyAmount\":45.35,\"discountAmount\":0,\"netDailyAmount\":45.35,\"monthlyAmount\":1360.5,\"routeComplete\":true}]', '2026-09-10 19:06:33', '2026-09-10 11:06:33', '2026-09-10 11:06:33'),
(2, 2, 1, '2026-09-10', 1, 30, 45.35, 1360.50, 1360.50, 'cash', '', 'paid', '[{\"destinationKey\":\"10.66248,122.94296\",\"destinationAddress\":\"Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines\",\"destinationLatitude\":10.6624846,\"destinationLongitude\":122.942957,\"children\":[{\"studentId\":2,\"studentName\":\"Benedict Sonio\",\"pickupAddress\":\"Juarez Street, Brgy. Alijis, bacolod city \",\"pickupLatitude\":10.6765,\"pickupLongitude\":122.9509,\"dropoffAddress\":\"Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines\",\"distanceKm\":1.78,\"grossDailyAmount\":45.35,\"discountAmount\":0,\"discountRate\":0,\"netDailyAmount\":45.35,\"routeComplete\":true,\"monthlyAmount\":1360.5}],\"childCount\":1,\"grossDailyAmount\":45.35,\"discountAmount\":0,\"netDailyAmount\":45.35,\"monthlyAmount\":1360.5,\"routeComplete\":true}]', '2026-09-10 19:06:35', '2026-09-10 11:06:35', '2026-09-10 11:06:35');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED NOT NULL,
  `lrn` varchar(30) NOT NULL,
  `school_name` varchar(150) NOT NULL,
  `grade_level` varchar(50) NOT NULL,
  `pickup_address` text NOT NULL,
  `pickup_latitude` decimal(10,7) DEFAULT NULL,
  `pickup_longitude` decimal(10,7) DEFAULT NULL,
  `dropoff_address` text NOT NULL,
  `dropoff_latitude` decimal(10,7) DEFAULT NULL,
  `dropoff_longitude` decimal(10,7) DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `parent_id`, `lrn`, `school_name`, `grade_level`, `pickup_address`, `pickup_latitude`, `pickup_longitude`, `dropoff_address`, `dropoff_latitude`, `dropoff_longitude`, `medical_notes`, `status`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 'JDA05050400', 'CHMSU', 'grade11', 'Alijis, Bacolod city', NULL, NULL, 'school gate', NULL, NULL, '', 'active', '2026-05-13 08:12:08', '2026-05-16 01:19:21'),
(2, 8, 2, 'BNB06050400', 'CHMSU ', '9', 'Juarez Street, Brgy. Alijis, bacolod city ', 10.6765000, 122.9509000, 'Araneta Avenue, Jose Rizal, Barangay 14, Bacolod, Negros Island Region, Philippines', 10.6624846, 122.9429570, '', 'active', '2026-05-14 05:27:27', '2026-09-10 11:06:06'),
(3, 28, 3, 'RJ12030300', 'CHMSU ', 'grade 8', 'Block 10A, Lot 8, Alijis, Bacolod City', NULL, NULL, 'School Gate', NULL, NULL, '', 'active', '2026-05-14 06:01:32', '2026-05-14 06:01:32');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mobile_number` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `status` enum('pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `first_name`, `middle_name`, `last_name`, `email`, `mobile_number`, `password_hash`, `profile_photo`, `status`, `is_verified`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'System', NULL, 'Admin', 'admin@trace.test', '09000000000', '$2y$10$TBaiMFxt3f7QqGsSc6g77.nJnilxgjSf4CagE8E0l3J6i3/SiqKR2', NULL, 'active', 1, '2026-09-16 19:08:47', '2026-05-11 11:30:45', '2026-09-16 11:08:47'),
(2, 3, 'kate nicole', NULL, 'bermejo', 'bermejokatenicoleee@gmail.com', '09060557704', '$2y$10$1gp59p7MvufDuBW.9XCMM.G06ikXZoHz2G53aglnttskvo/NpP8sm', 'storage/uploads/profiles/profile_photo-6a042f78978743.04066137.jpg', 'active', 1, '2026-05-16 09:18:56', '2026-05-13 07:59:52', '2026-05-16 01:18:56'),
(3, 2, 'joeseph', NULL, 'Ramos', 'joesephramos@gmail.com', '09112626821', '$2y$10$Lb5.mgdcjkafWI6XSthmwe9kXUF/kAZcwQJoPeXHWvwTxfRyHKs3y', 'storage/uploads/profiles/profile_photo-6a04304982a4e0.14484271.jpg', 'active', 1, '2026-05-16 09:12:57', '2026-05-13 08:03:21', '2026-05-16 01:12:57'),
(4, 2, 'John Ahron', NULL, 'Brizuela', 'ahronbrizuela@gmail.com', '0909786654', '$2y$10$QCvxobKzCYiQIFznB0r8au4XqRKVJTVe8CgpJp65v4Een1RNyJ2tS', 'storage/uploads/profiles/profile_photo-6a0431e3e56a78.79053095.jpg', 'active', 1, '2026-05-14 13:29:18', '2026-05-13 08:10:12', '2026-05-14 05:29:18'),
(6, 4, 'james', NULL, 'ausente', 'student-1@trace.local', 'student-1', '$2y$10$Gyd5J5rN6WS61CJ8W/5s9OCV2OZmykMe6ue1LOL.QkukkXAFku12u', NULL, 'active', 1, NULL, '2026-05-13 08:12:08', '2026-05-16 01:19:21'),
(7, 3, 'Rey Daniel', NULL, 'Sonio', 'sonioreydaniel@gmail.com', '09708228407', '$2y$10$fTmlc8an6AypdMXjLz1h0e1sU2y9NrNVsPHtLMOcEX2vz/SbzWKpm', 'storage/uploads/profiles/profile_photo-6a055cf9263155.28857334.jpg', 'active', 1, '2026-09-10 19:01:24', '2026-05-14 05:26:17', '2026-09-10 11:01:24'),
(8, 4, 'Benedict', NULL, 'Sonio', 'student-1778736447@trace.local', 'student-1778736447', '$2y$10$Yau1pHl5XsOzWq9p1V8eM.NnSOI.coqJHyJZnhfaPMMr85HPIanE6', NULL, 'active', 1, NULL, '2026-05-14 05:27:27', '2026-05-14 05:27:27'),
(27, 3, 'Joshua', NULL, 'De Jose', 'joshua@gmail.com', '09102240385', '$2y$10$6wVBGepmUc8m4tYd.m58s.5Wul45rmI6QydS65DN0O1qgxrdElM9.', 'storage/uploads/profiles/profile_photo-6a0564df6c0fc6.15987645.jpg', 'active', 1, NULL, '2026-05-14 05:59:59', '2026-05-14 05:59:59'),
(28, 4, 'John', NULL, 'De Jose', 'student-1778738492@trace.local', 'student-1778738492', '$2y$10$kEEutyPG0d27aqbqM1MUuuaOQSCl5cUPkZDyFkZox1dOimLR2Nuj6', NULL, 'active', 1, NULL, '2026-05-14 06:01:32', '2026-05-14 06:01:32');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `driver_id` bigint(20) UNSIGNED NOT NULL,
  `plate_number` varchar(30) NOT NULL,
  `model` varchar(100) NOT NULL,
  `color` varchar(50) NOT NULL,
  `capacity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `registration_path` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_admin_logs_admin` (`admin_user_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bookings_parent` (`parent_id`),
  ADD KEY `fk_bookings_student` (`student_id`),
  ADD KEY `fk_bookings_driver` (`assigned_driver_id`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD UNIQUE KEY `vehicle_plate_number` (`vehicle_plate_number`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_messages_sender` (`sender_user_id`),
  ADD KEY `fk_messages_receiver` (`receiver_user_id`),
  ADD KEY `fk_messages_ride` (`ride_id`);

--
-- Indexes for table `monthly_plans`
--
ALTER TABLE `monthly_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_monthly_plans_parent_month` (`parent_id`,`billing_month`),
  ADD KEY `idx_monthly_plans_destination` (`parent_id`,`billing_month`,`destination_key`);

--
-- Indexes for table `monthly_plan_refunds`
--
ALTER TABLE `monthly_plan_refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_monthly_plan_refunds_parent` (`parent_id`,`scheduled_date`),
  ADD KEY `idx_monthly_plan_refunds_plan_day` (`monthly_plan_id`,`monthly_plan_route_id`,`scheduled_date`);

--
-- Indexes for table `monthly_plan_routes`
--
ALTER TABLE `monthly_plan_routes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_monthly_plan_routes_destination` (`monthly_plan_id`,`destination_key`);

--
-- Indexes for table `monthly_plan_route_students`
--
ALTER TABLE `monthly_plan_route_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_monthly_plan_route_students` (`monthly_plan_route_id`,`student_id`),
  ADD KEY `fk_monthly_plan_route_students_student` (`student_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifications_user` (`user_id`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `rides`
--
ALTER TABLE `rides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `fk_rides_driver` (`driver_id`);

--
-- Indexes for table `ride_locations`
--
ALTER TABLE `ride_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ride_locations_ride_recorded` (`ride_id`,`recorded_at`),
  ADD KEY `fk_ride_locations_driver` (`driver_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `service_payments`
--
ALTER TABLE `service_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_payments_parent_month` (`parent_id`,`billing_month`),
  ADD KEY `idx_service_payments_plan` (`monthly_plan_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `lrn` (`lrn`),
  ADD KEY `fk_students_parent` (`parent_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `mobile_number` (`mobile_number`),
  ADD KEY `fk_users_role` (`role_id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`),
  ADD KEY `fk_vehicles_driver` (`driver_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `monthly_plans`
--
ALTER TABLE `monthly_plans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `monthly_plan_refunds`
--
ALTER TABLE `monthly_plan_refunds`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `monthly_plan_routes`
--
ALTER TABLE `monthly_plan_routes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `monthly_plan_route_students`
--
ALTER TABLE `monthly_plan_route_students`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rides`
--
ALTER TABLE `rides`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ride_locations`
--
ALTER TABLE `ride_locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `service_payments`
--
ALTER TABLE `service_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `fk_admin_logs_admin` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_bookings_driver` FOREIGN KEY (`assigned_driver_id`) REFERENCES `drivers` (`id`),
  ADD CONSTRAINT `fk_bookings_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`),
  ADD CONSTRAINT `fk_bookings_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `drivers`
--
ALTER TABLE `drivers`
  ADD CONSTRAINT `fk_drivers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_receiver` FOREIGN KEY (`receiver_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_messages_ride` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`),
  ADD CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `monthly_plans`
--
ALTER TABLE `monthly_plans`
  ADD CONSTRAINT `fk_monthly_plans_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`);

--
-- Constraints for table `monthly_plan_refunds`
--
ALTER TABLE `monthly_plan_refunds`
  ADD CONSTRAINT `fk_monthly_plan_refunds_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`);

--
-- Constraints for table `monthly_plan_routes`
--
ALTER TABLE `monthly_plan_routes`
  ADD CONSTRAINT `fk_monthly_plan_routes_plan` FOREIGN KEY (`monthly_plan_id`) REFERENCES `monthly_plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `monthly_plan_route_students`
--
ALTER TABLE `monthly_plan_route_students`
  ADD CONSTRAINT `fk_monthly_plan_route_students_route` FOREIGN KEY (`monthly_plan_route_id`) REFERENCES `monthly_plan_routes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_monthly_plan_route_students_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `fk_parents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `rides`
--
ALTER TABLE `rides`
  ADD CONSTRAINT `fk_rides_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `fk_rides_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`);

--
-- Constraints for table `ride_locations`
--
ALTER TABLE `ride_locations`
  ADD CONSTRAINT `fk_ride_locations_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`),
  ADD CONSTRAINT `fk_ride_locations_ride` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`);

--
-- Constraints for table `service_payments`
--
ALTER TABLE `service_payments`
  ADD CONSTRAINT `fk_service_payments_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`),
  ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `fk_vehicles_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
