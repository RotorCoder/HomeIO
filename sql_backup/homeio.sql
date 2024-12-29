-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Dec 29, 2024 at 11:59 AM
-- Server version: 10.11.6-MariaDB-0+deb12u1
-- PHP Version: 8.2.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `homeio`
--

-- --------------------------------------------------------

--
-- Table structure for table `command_queue`
--

CREATE TABLE `command_queue` (
  `id` int(11) NOT NULL,
  `device` varchar(255) NOT NULL,
  `brand` varchar(50) NOT NULL DEFAULT '''govee''',
  `model` varchar(255) NOT NULL,
  `command` text NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `device` text NOT NULL,
  `brand` text NOT NULL,
  `x10Code` text DEFAULT NULL,
  `deviceGroup` int(1) DEFAULT NULL,
  `showInGroupOnly` tinyint(1) DEFAULT NULL,
  `room` int(2) NOT NULL DEFAULT 1,
  `model` text DEFAULT NULL,
  `device_name` text DEFAULT NULL,
  `controllable` tinyint(1) DEFAULT NULL,
  `colorTemp_rangeMin` int(4) DEFAULT NULL,
  `colorTemp_rangeMax` int(4) DEFAULT NULL,
  `retrievable` tinyint(1) DEFAULT NULL,
  `supportCmds` text DEFAULT NULL,
  `online` tinyint(1) DEFAULT 0,
  `powerState` text DEFAULT NULL,
  `brightness` int(3) DEFAULT NULL,
  `colorTemp` int(4) DEFAULT NULL,
  `low` int(3) NOT NULL DEFAULT 10,
  `medium` int(3) NOT NULL DEFAULT 50,
  `high` int(3) NOT NULL DEFAULT 100,
  `preferredColorTem` int(4) NOT NULL DEFAULT 3600,
  `added` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `devices`
--

INSERT INTO `devices` (`device`, `brand`, `x10Code`, `deviceGroup`, `showInGroupOnly`, `room`, `model`, `device_name`, `controllable`, `colorTemp_rangeMin`, `colorTemp_rangeMax`, `retrievable`, `supportCmds`, `online`, `powerState`, `brightness`, `colorTemp`, `low`, `medium`, `high`, `preferredColorTem`, `added`, `updated`) VALUES
('2C:4F:D0:C9:07:C9:4D:E8', 'govee', 'a5', NULL, NULL, 5, 'H5086', 'Desk Light/Fan', 1, NULL, NULL, 1, '[\"turn\"]', 1, 'on', NULL, NULL, 10, 50, 100, 3600, '2024-12-21 23:46:51', '2024-12-29 08:03:11'),
('0F:A7:D0:C9:07:C9:26:88', 'govee', 'a6', NULL, NULL, 5, 'H5086', 'Glow Light', 1, NULL, NULL, 1, '[\"turn\"]', 1, 'on', NULL, NULL, 10, 50, 100, 3600, '2024-12-21 23:46:51', '2024-12-29 08:03:11'),
('4C:58:D0:C9:07:C9:4C:16', 'govee', 'a3', NULL, NULL, 2, 'H5086', 'Den - Fan', 1, NULL, NULL, 1, '[\"turn\"]', 1, 'off', NULL, NULL, 11, 50, 100, 3600, '2024-12-21 23:46:51', '2024-12-29 10:18:23'),
('07:A1:D0:C9:07:C9:50:D4', 'govee', 'a11', NULL, NULL, 3, 'H5086', 'Bedroom - Fan', 1, NULL, NULL, 1, '[\"turn\"]', 1, 'on', NULL, NULL, 10, 50, 100, 3600, '2024-12-21 23:46:51', '2024-12-29 00:18:55'),
('06:25:D0:C9:07:C8:6D:2C', 'govee', NULL, NULL, NULL, 3, 'H5086', 'Bedroom - Heater', 1, NULL, NULL, 1, '[\"turn\"]', 1, 'on', NULL, NULL, 10, 50, 100, 3600, '2024-12-21 23:46:51', '2024-12-29 02:18:49'),
('06:61:D0:C9:07:C8:DC:D8', 'govee', NULL, NULL, NULL, 1, 'H5086', '7', 1, NULL, NULL, 1, '[\"turn\"]', 0, 'off', NULL, NULL, 10, 50, 100, 3600, '2024-12-21 23:46:51', '2024-12-24 06:43:49'),
('06:F8:D0:C9:07:C9:52:96', 'govee', NULL, NULL, NULL, 1, 'H5086', '8', 1, NULL, NULL, 1, '[\"turn\"]', 0, 'off', NULL, NULL, 10, 50, 100, 3600, '2024-12-21 23:46:51', '2024-12-26 07:58:47'),
('78:9B:D0:C9:07:E2:E5:E6', 'govee', NULL, NULL, 0, 1, 'H6008', 'Den - Left Light', 1, 2700, 6500, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 0, 'on', 100, NULL, 10, 50, 100, 3600, '2024-12-21 23:46:51', '2024-12-27 03:18:09'),
('3B:C7:D0:C9:07:D6:C1:10', 'govee', NULL, NULL, 0, 1, 'H6008', 'Den - Right Light', 1, 2700, 6500, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 0, 'on', 100, NULL, 10, 50, 100, 3600, '2024-12-21 23:46:51', '2024-12-27 03:18:10'),
('2B:0B:D0:C9:07:DB:55:2C', 'govee', 'a4', 13, 0, 5, 'H6008', 'Torch Lamp', 1, 2700, 6500, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, 'on', 100, NULL, 10, 50, 100, 3600, '2024-12-21 23:46:51', '2024-12-29 08:03:09'),
('1B:1B:D0:C9:07:D8:06:D6', 'govee', NULL, 13, 1, 5, 'H6008', 'Torch Lamp Side', 1, 2700, 6500, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, 'on', 100, NULL, 10, 50, 100, 3600, '2024-12-21 23:46:51', '2024-12-29 08:03:09'),
('1C:05:D4:0F:41:86:6B:62', 'govee', 'a2', NULL, 0, 2, 'H6099', 'Den - TV Backlight', 1, 2000, 9000, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, 'on', 10, NULL, 10, 25, 100, 3550, '2024-12-21 23:46:51', '2024-12-29 00:47:08'),
('3B:BF:D0:C9:07:DE:7E:1C', 'govee', NULL, NULL, NULL, 1, 'H6008', 'Ceiling 3', 1, 2700, 6500, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 0, 'off', 100, NULL, 10, 50, 100, 3600, '2024-12-21 23:46:51', '2024-12-27 03:18:13'),
('2B:46:D0:C9:07:D5:FD:16', 'govee', NULL, NULL, NULL, 1, 'H6008', 'Ceiling 4', 1, 2700, 6500, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 0, 'off', 100, NULL, 10, 50, 100, 3600, '2024-12-21 23:46:51', '2024-12-27 03:18:13'),
('12:3D:D0:C9:07:C8:DD:FA', 'govee', NULL, NULL, NULL, 3, 'H5086', 'Air Filter', 1, NULL, NULL, 1, '[\"turn\"]', 1, 'on', NULL, NULL, 1, 50, 100, 3600, '2024-12-22 01:53:50', '2024-12-28 10:26:46'),
('58:A1:D0:C9:07:D5:74:2C', 'govee', 'a9', 10, 0, 3, 'H6008', 'Bedroom - Right Light', 1, 2700, 6500, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, 'on', 1, NULL, 1, 20, 100, 3600, '2024-12-22 16:57:37', '2024-12-29 08:00:57'),
('3F:6C:D0:C9:07:DD:20:58', 'govee', 'a10', 10, 1, 3, 'H6008', 'Bedroom - Left Light', 1, 2700, 6500, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, 'on', 1, NULL, 1, 20, 100, 3600, '2024-12-22 17:03:32', '2024-12-29 08:00:57'),
('d6831fe8-fe58-4321-8029-2d739f62a055', 'hue', '', 11, 1, 2, 'light', 'Right Light', 1, NULL, NULL, 1, '[\"brightness\",\"colorTem\",\"color\"]', 1, 'on', 30, 319, 30, 70, 100, 3600, '2024-12-27 01:29:24', '2024-12-29 00:53:29'),
('bab38ad6-9d55-47f7-8094-40f2c6282978', 'hue', 'a1', 11, NULL, 2, 'light', 'Left Light', 1, NULL, NULL, 1, '[\"brightness\",\"colorTem\",\"color\"]', 1, 'on', 30, 319, 30, 70, 100, 3600, '2024-12-27 03:05:59', '2024-12-29 00:53:32');

-- --------------------------------------------------------

--
-- Table structure for table `device_groups`
--

CREATE TABLE `device_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `model` varchar(50) NOT NULL,
  `reference_device` varchar(50) NOT NULL,
  `created` timestamp NULL DEFAULT current_timestamp(),
  `updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `device_groups`
--

INSERT INTO `device_groups` (`id`, `name`, `model`, `reference_device`, `created`, `updated`) VALUES
(10, 'Bedroom - Lights', 'H6008', '58:A1:D0:C9:07:D5:74:2C', '2024-12-26 07:01:29', '2024-12-26 07:01:29'),
(11, 'Living Room - Lights', 'null', 'bab38ad6-9d55-47f7-8094-40f2c6282978', '2024-12-27 01:50:11', '2024-12-27 03:22:02'),
(13, 'Office - Lights', 'H6008', '2B:0B:D0:C9:07:DB:55:2C', '2024-12-28 16:39:21', '2024-12-28 16:39:21');

-- --------------------------------------------------------

--
-- Table structure for table `govee_api_calls`
--

CREATE TABLE `govee_api_calls` (
  `id` int(11) NOT NULL,
  `Date` timestamp NULL DEFAULT NULL,
  `API-RateLimit-Remaining` int(5) DEFAULT NULL,
  `API-RateLimit-Reset` timestamp NULL DEFAULT NULL,
  `API-RateLimit-Limit` int(5) DEFAULT NULL,
  `X-RateLimit-Limit` int(6) DEFAULT NULL,
  `X-RateLimit-Remaining` int(6) DEFAULT NULL,
  `X-RateLimit-Reset` timestamp NULL DEFAULT NULL,
  `X-Response-Time` text DEFAULT NULL,
  `date_inserted` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(8) NOT NULL,
  `source` varchar(40) NOT NULL,
  `severity` varchar(20) NOT NULL,
  `data` varchar(100) NOT NULL,
  `datetime` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_name` text NOT NULL,
  `tab_order` int(2) NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_name`, `tab_order`) VALUES
(1, 'Unassigned', 100),
(2, 'Living Room', 1),
(3, 'Bedroom', 3),
(5, 'Office', 2);

-- --------------------------------------------------------

--
-- Table structure for table `thermometers`
--

CREATE TABLE `thermometers` (
  `mac` varchar(17) NOT NULL,
  `model` text NOT NULL,
  `name` text NOT NULL,
  `display_name` text DEFAULT NULL,
  `number` int(2) DEFAULT NULL,
  `rssi` int(2) NOT NULL,
  `temp` int(3) NOT NULL,
  `humidity` int(2) NOT NULL,
  `battery` int(3) NOT NULL,
  `room` int(2) DEFAULT NULL,
  `added` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `thermometers`
--

INSERT INTO `thermometers` (`mac`, `model`, `name`, `display_name`, `number`, `rssi`, `temp`, `humidity`, `battery`, `room`, `added`, `updated`) VALUES
('A4:C1:38:3A:F5:5C', 'GVH5075', 'GVH5075_F55C', 'Office', 4, -78, 73, 45, 100, 5, '2024-12-28 09:43:26', '2024-12-29 11:58:33'),
('A4:C1:38:73:96:B4', 'GVH5075', 'GVH5075_96B4', 'Living Room', 5, -74, 71, 49, 100, 2, '2024-12-28 09:43:23', '2024-12-29 11:58:58'),
('A4:C1:38:F6:CC:80', 'GVH5075', 'GVH5075_CC80', 'Bedroom', 6, -90, 70, 61, 100, 3, '2024-12-28 09:43:58', '2024-12-29 11:58:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `command_queue`
--
ALTER TABLE `command_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_created` (`status`,`created_at`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD UNIQUE KEY `id` (`device`) USING HASH,
  ADD UNIQUE KEY `x10Code` (`x10Code`) USING HASH;

--
-- Indexes for table `device_groups`
--
ALTER TABLE `device_groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `govee_api_calls`
--
ALTER TABLE `govee_api_calls`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `thermometers`
--
ALTER TABLE `thermometers`
  ADD PRIMARY KEY (`mac`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `command_queue`
--
ALTER TABLE `command_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `device_groups`
--
ALTER TABLE `device_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `govee_api_calls`
--
ALTER TABLE `govee_api_calls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(8) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
