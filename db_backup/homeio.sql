-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 08, 2025 at 05:33 AM
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
  `model` text DEFAULT NULL,
  `device_name` text DEFAULT NULL,
  `preferredName` text DEFAULT NULL,
  `controllable` tinyint(1) DEFAULT NULL,
  `colorTemp_rangeMin` int(4) DEFAULT NULL,
  `colorTemp_rangeMax` int(4) DEFAULT NULL,
  `retrievable` tinyint(1) DEFAULT NULL,
  `show_in_room` tinyint(1) NOT NULL DEFAULT 1,
  `supportCmds` text DEFAULT NULL,
  `online` tinyint(1) DEFAULT 0,
  `powerState` text DEFAULT NULL,
  `brightness` int(3) DEFAULT NULL,
  `colorTemp` int(4) DEFAULT NULL,
  `low` int(3) NOT NULL DEFAULT 10,
  `medium` int(3) NOT NULL DEFAULT 50,
  `high` int(3) NOT NULL DEFAULT 100,
  `preferredPowerState` varchar(10) DEFAULT NULL,
  `preferredBrightness` int(11) DEFAULT NULL,
  `preferredColorTem` int(4) NOT NULL DEFAULT 3600,
  `energy_today` float DEFAULT NULL,
  `power` float DEFAULT NULL,
  `voltage` float DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `added` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `devices`
--

INSERT INTO `devices` (`device`, `brand`, `x10Code`, `model`, `device_name`, `preferredName`, `controllable`, `colorTemp_rangeMin`, `colorTemp_rangeMax`, `retrievable`, `show_in_room`, `supportCmds`, `online`, `powerState`, `brightness`, `colorTemp`, `low`, `medium`, `high`, `preferredPowerState`, `preferredBrightness`, `preferredColorTem`, `energy_today`, `power`, `voltage`, `updated`, `added`) VALUES
('2C:4F:D0:C9:07:C9:4D:E8', 'govee', 'a5', 'H5086', 'Desk Light/Fan', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 1, NULL, NULL, NULL, 10, 50, 100, 'on', NULL, 3600, NULL, NULL, NULL, '2025-01-08 04:44:14', '2024-12-21 23:46:51'),
('0F:A7:D0:C9:07:C9:26:88', 'govee', 'a6', 'H5086', 'Glow Light', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 1, NULL, NULL, NULL, 10, 50, 100, 'on', NULL, 3600, NULL, NULL, NULL, '2025-01-08 04:54:21', '2024-12-21 23:46:51'),
('4C:58:D0:C9:07:C9:4C:16', 'govee', 'a3', 'H5086', 'Den - Fan', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 1, NULL, NULL, NULL, 11, 50, 100, 'off', 100, 3600, NULL, NULL, NULL, '2025-01-08 04:54:21', '2024-12-21 23:46:51'),
('07:A1:D0:C9:07:C9:50:D4', 'govee', 'a11', 'H5086', 'Bedroom - Fan', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 1, NULL, NULL, NULL, 10, 50, 100, 'on', NULL, 3600, NULL, NULL, NULL, '2025-01-06 06:24:53', '2024-12-21 23:46:51'),
('06:25:D0:C9:07:C8:6D:2C', 'govee', NULL, 'H5086', 'Bedroom - Heater', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 1, NULL, NULL, NULL, 10, 50, 100, 'on', NULL, 3600, NULL, NULL, NULL, '2025-01-08 04:54:22', '2024-12-21 23:46:51'),
('06:61:D0:C9:07:C8:DC:D8', 'govee', NULL, 'H5086', 'Plug 7', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 1, NULL, NULL, NULL, 10, 50, 100, NULL, NULL, 3600, NULL, NULL, NULL, '2025-01-06 06:14:47', '2024-12-21 23:46:51'),
('06:F8:D0:C9:07:C9:52:96', 'govee', NULL, 'H5086', 'Plug 8', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 1, NULL, NULL, NULL, 10, 50, 100, 'off', NULL, 3600, NULL, NULL, NULL, '2025-01-06 06:14:47', '2024-12-21 23:46:51'),
('78:9B:D0:C9:07:E2:E5:E6', 'govee', NULL, 'H6008', 'Light 2', NULL, 1, 2700, 6500, 1, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, NULL, NULL, NULL, 10, 50, 100, NULL, NULL, 3600, NULL, NULL, NULL, '2025-01-05 04:03:09', '2024-12-21 23:46:51'),
('3B:C7:D0:C9:07:D6:C1:10', 'govee', NULL, 'H6008', 'Light 1', NULL, 1, 2700, 6500, 1, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, NULL, NULL, NULL, 10, 50, 100, NULL, NULL, 3600, NULL, NULL, NULL, '2025-01-05 04:03:09', '2024-12-21 23:46:51'),
('2B:0B:D0:C9:07:DB:55:2C', 'govee', 'a4', 'H6008', 'Light 5', NULL, 1, 2700, 6500, 1, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, NULL, NULL, NULL, 10, 50, 100, 'on', 100, 3600, NULL, NULL, NULL, '2025-01-05 04:03:09', '2024-12-21 23:46:51'),
('1B:1B:D0:C9:07:D8:06:D6', 'govee', NULL, 'H6008', 'Light 6', NULL, 1, 2700, 6500, 1, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, NULL, NULL, NULL, 10, 50, 100, 'on', 100, 3600, NULL, NULL, NULL, '2025-01-05 04:03:10', '2024-12-21 23:46:51'),
('1C:05:D4:0F:41:86:6B:62', 'govee', 'a2', 'H6099', 'Den - TV Backlight', NULL, 1, 2000, 9000, 1, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, NULL, NULL, NULL, 10, 25, 100, 'on', 25, 3550, NULL, NULL, NULL, '2025-01-08 04:54:24', '2024-12-21 23:46:51'),
('3B:BF:D0:C9:07:DE:7E:1C', 'govee', NULL, 'H6008', 'Light 3', NULL, 1, 2700, 6500, 1, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, NULL, NULL, NULL, 10, 50, 100, NULL, NULL, 3600, NULL, NULL, NULL, '2025-01-05 04:03:11', '2024-12-21 23:46:51'),
('2B:46:D0:C9:07:D5:FD:16', 'govee', NULL, 'H6008', 'Light 4', NULL, 1, 2700, 6500, 1, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, NULL, NULL, NULL, 10, 50, 100, NULL, NULL, 3600, NULL, NULL, NULL, '2025-01-05 04:03:12', '2024-12-21 23:46:51'),
('12:3D:D0:C9:07:C8:DD:FA', 'govee', NULL, 'H5086', 'Air Filter', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 1, NULL, NULL, NULL, 1, 50, 100, 'on', NULL, 3600, NULL, NULL, NULL, '2025-01-06 06:24:52', '2024-12-22 01:53:50'),
('58:A1:D0:C9:07:D5:74:2C', 'govee', NULL, 'H6008', 'Light 8', NULL, 1, 2700, 6500, 1, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, NULL, NULL, NULL, 1, 20, 100, 'off', 100, 3600, NULL, NULL, NULL, '2025-01-08 04:12:36', '2024-12-22 16:57:37'),
('3F:6C:D0:C9:07:DD:20:58', 'govee', NULL, 'H6008', 'Light 7', NULL, 1, 2700, 6500, 1, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, NULL, NULL, NULL, 1, 20, 100, 'on', 100, 3600, NULL, NULL, NULL, '2025-01-08 04:12:40', '2024-12-22 17:03:32'),
('d6831fe8-fe58-4321-8029-2d739f62a055', 'hue', NULL, 'light', 'TV Lamp Right', NULL, 1, NULL, NULL, 1, 1, '[\"brightness\",\"colorTem\",\"color\"]', 1, 'off', 100, 319, 30, 70, 100, 'on', 100, 3600, NULL, NULL, NULL, '2025-01-08 04:49:11', '2024-12-27 01:29:24'),
('bab38ad6-9d55-47f7-8094-40f2c6282978', 'hue', NULL, 'light', 'TV Lamp Left', 'Left Lamp', 1, NULL, NULL, 1, 1, '[\"brightness\",\"colorTem\",\"color\"]', 1, 'off', 100, 366, 30, 70, 100, 'on', 100, 3600, NULL, NULL, NULL, '2025-01-08 04:49:11', '2024-12-27 03:05:59'),
('vssb7720c5204b9fb2043a7ca44ce36b', 'vesync', NULL, 'XYD0001', 'Light A', NULL, 1, NULL, NULL, 1, 1, '[\"brightness\",\"colorTem\",\"color\"]', 0, 'off', 0, NULL, 10, 50, 100, 'off', 100, 3600, NULL, NULL, NULL, '2025-01-08 04:40:36', '2025-01-01 05:55:36'),
('vssbc3f227d470f8fdb5a1c678c5e5cf', 'vesync', NULL, 'XYD0001', 'Light B', NULL, 1, NULL, NULL, 1, 1, '[\"brightness\",\"colorTem\",\"color\"]', 0, 'off', 0, NULL, 10, 50, 100, 'off', 100, 3600, NULL, NULL, NULL, '2025-01-08 04:40:36', '2025-01-01 05:55:36'),
('vsaq816c68944907b8941a281dbad898', 'vesync', NULL, 'LUH-A602S-WUS', 'Humidifier', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 1, 'on', NULL, NULL, 10, 50, 100, 'on', NULL, 3600, NULL, NULL, NULL, '2025-01-08 04:40:36', '2025-01-01 05:55:36'),
('ff72baa0-de29-4851-990a-186449bb0938', 'vesync', NULL, 'wifi-switch-1.3', 'Storage Lights', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 0, 'off', NULL, NULL, 10, 50, 100, NULL, NULL, 3600, 0, 0, 0, '2025-01-01 08:38:27', '2025-01-01 06:23:12'),
('ed359c31-5ef7-4db1-8bb6-55fe9e58ac65', 'vesync', NULL, 'wifi-switch-1.3', 'Trickle Charger', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 1, 'on', NULL, NULL, 10, 50, 100, NULL, NULL, 3600, 0.0002, 5.15, 125.09, '2025-01-08 04:40:36', '2025-01-01 06:23:12'),
('8feb66bd-153c-4a0d-97ef-b0c60e26ffb5', 'vesync', NULL, 'wifi-switch-1.3', 'Happy Light', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 1, 'on', NULL, NULL, 10, 50, 100, 'on', NULL, 3600, 0.0001, 1.09, 124.09, '2025-01-08 04:40:36', '2025-01-01 06:23:12'),
('befb9ea6-3b9f-4858-8768-5a471ee4a5d0', 'vesync', NULL, 'wifi-switch-1.3', 'Plug B', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 0, 'off', NULL, NULL, 10, 50, 100, NULL, NULL, 3600, 0, 0, 0, '2025-01-08 04:40:36', '2025-01-01 06:23:12'),
('bf3450c1-de73-4537-b386-82bdc2593560', 'vesync', NULL, 'wifi-switch-1.3', 'Plug C', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 0, 'off', NULL, NULL, 10, 50, 100, NULL, NULL, 3600, 0, 0, 0, '2025-01-08 04:40:36', '2025-01-01 06:23:12'),
('0LRUORgQzvvSdFtThrvqFLc9pi9KrJV1', 'vesync', NULL, 'ESW15-USA', 'Plug A', NULL, 1, NULL, NULL, 1, 1, '[\"turn\"]', 0, 'on', NULL, NULL, 10, 50, 100, NULL, NULL, 3600, 0, 0, 0, '2025-01-08 04:40:36', '2025-01-01 06:23:12'),
('1a98c11c-30fe-4521-8266-a2f2fbd6d946', 'hue', NULL, 'light', 'Torch Lamp', NULL, 1, NULL, NULL, 1, 1, '[\"brightness\",\"colorTem\",\"color\"]', 1, 'on', 100, NULL, 10, 50, 100, 'on', 100, 3600, NULL, NULL, NULL, '2025-01-08 04:41:46', '2025-01-04 00:40:01'),
('682a0c16-6d63-4877-9c02-f1711691c488', 'hue', NULL, 'light', 'Nightstand Lamp Right', NULL, 1, NULL, NULL, 1, 1, '[\"brightness\",\"colorTem\",\"color\"]', 1, 'on', 1, NULL, 1, 50, 100, 'on', 1, 3600, NULL, NULL, NULL, '2025-01-08 04:43:07', '2025-01-04 00:40:01'),
('bf537c22-c32c-4907-b7c7-a5974c2d90d6', 'hue', NULL, 'light', 'Torch Lamp Side', NULL, 1, NULL, NULL, 1, 1, '[\"brightness\",\"colorTem\",\"color\"]', 1, 'on', 100, NULL, 10, 50, 100, 'on', 100, 3600, NULL, NULL, NULL, '2025-01-08 04:41:43', '2025-01-04 00:40:01'),
('e5c95310-d979-4484-a524-b7e424e17f88', 'hue', NULL, 'light', 'Nightstand Lamp Left', NULL, 1, NULL, NULL, 1, 1, '[\"brightness\",\"colorTem\",\"color\"]', 1, 'on', 30, NULL, 1, 50, 100, 'on', 1, 3600, NULL, NULL, NULL, '2025-01-08 04:57:55', '2025-01-04 00:40:01'),
('D1:83:C7:6B:02:46:22:30', 'govee', NULL, 'H6167', 'Bedroom - TV Backlight', NULL, 1, NULL, NULL, 1, 1, '[\"turn\",\"brightness\",\"color\",\"colorTem\"]', 1, NULL, NULL, NULL, 10, 50, 100, 'on', 1, 3600, NULL, NULL, NULL, '2025-01-08 04:44:19', '2025-01-08 01:43:23');

-- --------------------------------------------------------

--
-- Table structure for table `device_groups`
--

CREATE TABLE `device_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `x10Code` text DEFAULT NULL,
  `rooms` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rooms`)),
  `model` varchar(50) NOT NULL,
  `devices` text DEFAULT NULL,
  `created` timestamp NULL DEFAULT current_timestamp(),
  `updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `device_groups`
--

INSERT INTO `device_groups` (`id`, `name`, `x10Code`, `rooms`, `model`, `devices`, `created`, `updated`) VALUES
(25, 'Bedroom - Lights', 'a9', '[15,3]', 'light', '[\"e5c95310-d979-4484-a524-b7e424e17f88\",\"D1:83:C7:6B:02:46:22:30\",\"682a0c16-6d63-4877-9c02-f1711691c488\"]', '2025-01-06 02:27:14', '2025-01-08 04:42:53'),
(29, 'Living Room - Lights', 'a1', '[15,2]', 'light', '[\"d6831fe8-fe58-4321-8029-2d739f62a055\",\"bab38ad6-9d55-47f7-8094-40f2c6282978\"]', '2025-01-06 02:59:42', '2025-01-08 04:42:46'),
(32, 'Office - Lights', 'a8', '[15,5]', 'light', '[\"bf537c22-c32c-4907-b7c7-a5974c2d90d6\",\"1a98c11c-30fe-4521-8266-a2f2fbd6d946\"]', '2025-01-08 04:06:52', '2025-01-08 04:42:31');

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
-- Table structure for table `remote_buttons`
--

CREATE TABLE `remote_buttons` (
  `id` int(11) NOT NULL,
  `remote_name` varchar(50) NOT NULL,
  `button_number` int(11) NOT NULL,
  `status` text NOT NULL DEFAULT 'received',
  `raw_data` varchar(100) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `remote_buttons`
--

INSERT INTO `remote_buttons` (`id`, `remote_name`, `button_number`, `status`, `raw_data`, `timestamp`) VALUES
(1, 'GV5125207B', 1, 'executed', 'b9:bc:04:bb:3b:46:89:e7:17:c2:0c:3b:2a:3b:a6:52:16:a8:0e:ac:c1:f6:21:ff', '2025-01-05 02:05:40'),
(2, 'GV5125207B', 2, 'executed', 'b9:bc:04:bb:64:c2:00:b5:23:95:45:4b:e5:09:8a:2c:82:67:98:25:af:bd:c2:44', '2025-01-05 02:05:50'),
(3, 'GV5125207B', 4, 'executed', 'b9:bc:04:bb:a9:1e:96:29:da:bf:a7:ac:9c:94:cd:44:cb:88:0c:d8:ee:bf:53:a5', '2025-01-05 02:06:08'),
(4, 'GV5125615A', 3, 'executed', '43:49:03:1b:ee:5c:33:5b:ab:c6:93:a4:54:1b:32:bf:64:07:54:51:9b:71:a1:65', '2025-01-05 02:06:18'),
(5, 'GV5125207B', 4, 'executed', 'b9:bc:04:bb:a9:1e:96:29:da:bf:a7:ac:9c:94:cd:44:cb:88:0c:d8:ee:bf:53:a5', '2025-01-05 02:07:09'),
(6, 'GV5125615A', 3, 'executed', '43:49:03:1b:ee:5c:33:5b:ab:c6:93:a4:54:1b:32:bf:64:07:54:51:9b:71:a1:65', '2025-01-05 02:07:19'),
(7, 'GV5125615A', 2, 'executed', '43:49:03:20:e5:7e:b5:b0:42:56:28:c2:fd:bf:c7:65:bb:67:5b:23:7a:95:21:ac', '2025-01-05 02:11:43'),
(8, 'GV5125615A', 1, 'executed', '43:49:03:20:ed:30:1c:31:59:4a:db:ee:8d:6d:0c:79:cc:41:cb:51:1e:98:da:02', '2025-01-05 02:11:45'),
(9, 'GV5125615A', 2, 'executed', '43:49:03:21:17:24:af:f0:d3:63:69:2b:0a:b0:33:24:f8:73:03:84:b7:7f:6e:0c', '2025-01-05 02:11:56'),
(10, 'GV5125615A', 5, 'executed', '43:49:03:32:67:68:d1:52:77:7d:cc:b3:bb:80:21:7f:86:39:3a:b7:47:b4:a8:c5', '2025-01-05 02:30:51'),
(11, 'GV5125615A', 5, 'executed', '43:49:03:32:70:f0:27:14:b2:70:e8:88:b2:61:26:3c:b5:9e:72:78:9f:b1:48:2f', '2025-01-05 02:30:53'),
(12, 'GV5125615A', 6, 'executed', '43:49:03:33:b3:b6:a3:57:18:43:99:4b:34:89:0a:90:70:54:f4:a5:52:28:3b:02', '2025-01-05 02:32:16'),
(13, 'GV5125615A', 5, 'executed', '43:49:03:33:bc:62:e7:ce:d8:18:c2:67:23:a4:ea:fa:89:30:a9:a8:6d:8f:2b:17', '2025-01-05 02:32:18'),
(14, 'GV5125615A', 5, 'executed', '43:49:03:33:c5:d6:66:bc:8b:a6:23:53:bd:fb:d9:73:f4:fa:ba:50:5f:07:f5:11', '2025-01-05 02:32:20'),
(15, 'GV5125615A', 3, 'executed', '43:49:03:33:d2:4c:d0:97:61:2e:72:83:5e:07:f5:da:d6:25:1a:c2:8c:82:98:67', '2025-01-05 02:32:23'),
(16, 'GV5125615A', 1, 'executed', '43:49:03:35:d3:62:3c:ea:4f:b3:08:0b:1d:71:4e:c9:a3:1b:46:71:0d:ab:72:66', '2025-01-05 02:34:35'),
(17, 'GV5125615A', 1, 'executed', '43:49:03:35:f5:b8:b3:65:93:1b:2a:18:7e:e7:bd:cb:26:5c:95:1d:fa:c7:c0:19', '2025-01-05 02:34:44'),
(18, 'GV5125615A', 4, 'executed', '43:49:03:35:fc:34:1c:c5:16:6f:e7:bc:c1:c4:4b:52:34:a6:de:6b:f6:1d:a9:e7', '2025-01-05 02:34:45'),
(19, 'GV5125615A', 6, 'executed', '43:49:03:37:b8:62:ea:c1:1e:ce:80:1f:62:88:6c:f6:2d:94:83:74:68:94:f9:cf', '2025-01-05 02:36:39'),
(20, 'GV5125615A', 5, 'executed', '43:49:03:37:be:de:9a:a9:fa:a1:cb:ad:49:2f:55:89:b9:9f:69:f3:d0:4d:fe:79', '2025-01-05 02:36:41'),
(21, 'GV5125615A', 1, 'executed', '43:49:03:37:c8:b6:6f:05:3a:b4:e3:bb:53:16:84:34:9e:15:c4:86:ec:06:26:ea', '2025-01-05 02:36:43'),
(22, 'GV5125615A', 1, 'executed', '43:49:03:38:78:d8:18:96:b9:00:e3:a9:54:2c:c3:a5:b9:f1:82:e8:14:46:bc:b7', '2025-01-05 02:37:28'),
(23, 'GV5125615A', 3, 'executed', '43:49:03:38:82:2e:56:74:0f:d4:7d:4a:78:3a:4b:ae:18:db:8c:15:fd:1b:91:6f', '2025-01-05 02:37:31'),
(24, 'GV5125615A', 4, 'executed', '43:49:03:38:8c:ec:f2:09:7c:07:b6:70:89:a3:c1:a6:c6:07:10:ad:7a:20:2d:87', '2025-01-05 02:37:33'),
(25, 'GV5125615A', 6, 'executed', '43:49:03:38:99:26:6b:fb:d5:60:ee:37:15:9e:6a:1e:f2:a8:7e:6d:32:e2:ee:0c', '2025-01-05 02:37:37'),
(26, 'GV5125615A', 5, 'executed', '43:49:03:38:a0:6a:af:cb:46:86:18:b6:15:96:5e:4c:62:b0:c0:00:8a:2e:3b:b0', '2025-01-05 02:37:38'),
(27, 'GV5125615A', 1, 'executed', '43:49:03:38:a9:b6:d0:60:1a:4d:26:31:f1:c0:67:77:7a:c4:ff:33:f4:36:99:97', '2025-01-05 02:37:41'),
(28, 'GV5125615A', 2, 'executed', '43:49:03:38:b4:f6:81:7c:db:8a:ae:fc:c1:43:3e:43:ec:07:2b:3f:30:b3:be:88', '2025-01-05 02:37:44'),
(29, 'GV5125615A', 5, 'executed', '43:49:03:39:69:78:cb:e2:f3:ff:69:8f:5d:3c:ed:77:87:da:3d:2e:9a:ea:5a:f0', '2025-01-05 02:38:30'),
(30, 'GV5125615A', 6, 'executed', '43:49:03:39:72:ce:7e:8e:4e:4b:97:96:b8:20:f0:67:9c:f3:3b:1f:d0:88:3a:d1', '2025-01-05 02:38:32'),
(31, 'GV5125615A', 1, 'executed', '43:49:03:39:7b:66:c4:95:2b:e7:75:47:f1:fc:c7:af:ad:9d:ee:a3:d4:07:cf:5d', '2025-01-05 02:38:34'),
(32, 'GV5125615A', 2, 'executed', '43:49:03:39:87:dc:3c:d0:f0:db:c2:ac:1b:a3:47:70:ab:88:10:1b:eb:bd:c5:6e', '2025-01-05 02:38:38'),
(33, 'GV5125615A', 5, 'executed', '43:49:03:3a:5b:62:0b:22:93:b8:69:68:9d:49:48:25:a8:18:4f:e2:08:31:b9:9c', '2025-01-05 02:39:32'),
(34, 'GV5125615A', 6, 'executed', '43:49:03:3a:64:9a:34:56:4d:15:bb:9a:b7:f0:16:01:e3:3c:ef:6c:d9:4b:5f:08', '2025-01-05 02:39:34'),
(35, 'GV5125615A', 5, 'executed', '43:49:03:3c:6d:b2:8a:87:fc:fc:3d:f4:a2:3c:54:46:57:9e:1b:4f:28:43:9c:03', '2025-01-05 02:41:48'),
(36, 'GV5125615A', 1, 'executed', '43:49:03:3c:79:92:3b:db:8a:33:00:d4:1c:19:96:d2:8e:06:e1:5d:59:e7:97:b7', '2025-01-05 02:41:51'),
(37, 'GV5125615A', 2, 'executed', '43:49:03:3d:28:2e:6f:42:a0:62:0a:27:2c:67:9e:bb:fa:cf:f0:0c:5a:b4:e5:6d', '2025-01-05 02:42:35'),
(38, 'GV5125207B', 5, 'executed', 'b9:bc:04:dd:2d:a0:8f:fe:8b:ed:86:d0:8a:50:a2:a0:5f:a9:ac:96:2e:c5:ad:4c', '2025-01-05 02:42:44'),
(39, 'GV5125207B', 4, 'executed', 'b9:bc:04:dd:36:88:cf:ee:83:43:7e:4c:69:dc:b6:97:d8:3d:9a:04:8d:3f:4c:79', '2025-01-05 02:42:46'),
(40, 'GV5125207B', 1, 'executed', 'b9:bc:04:dd:43:08:5d:b2:b6:4f:d1:09:74:e5:de:b2:c9:be:26:73:0a:07:dd:64', '2025-01-05 02:42:50'),
(41, 'GV5125615A', 2, 'executed', '43:49:03:3d:28:2e:6f:42:a0:62:0a:27:2c:67:9e:bb:fa:cf:f0:0c:5a:b4:e5:6d', '2025-01-05 02:43:37'),
(42, 'GV5125615A', 6, 'executed', '43:49:03:3e:22:38:1e:c9:56:ab:86:e7:75:be:82:0b:c3:5a:80:5c:9c:b1:36:9b', '2025-01-05 02:43:39'),
(43, 'GV5125615A', 5, 'executed', '43:49:03:3e:2b:ca:da:d5:f5:0f:4f:94:14:ae:19:fd:05:26:1c:b9:02:bb:a4:7b', '2025-01-05 02:43:42'),
(44, 'GV5125615A', 1, 'executed', '43:49:03:3e:33:40:bf:8a:85:32:bb:e5:a5:88:cd:a4:9a:ef:d2:fa:b2:e8:af:87', '2025-01-05 02:43:44'),
(45, 'GV5125615A', 2, 'executed', '43:49:03:3e:3c:fa:74:a8:c1:b8:95:b1:b5:fd:d4:99:82:51:40:bd:ad:f3:b4:52', '2025-01-05 02:43:46'),
(46, 'GV5125207B', 1, 'executed', 'b9:bc:04:dd:43:08:5d:b2:b6:4f:d1:09:74:e5:de:b2:c9:be:26:73:0a:07:dd:64', '2025-01-05 02:43:51'),
(47, 'GV5125615A', 6, 'executed', '43:49:03:3e:f9:e2:f7:95:d3:bb:c6:3b:e4:e3:e3:5e:af:6c:0a:d9:81:92:61:e9', '2025-01-05 02:44:35'),
(48, 'GV5125615A', 5, 'executed', '43:49:03:3f:03:f6:2f:0c:60:95:c2:e2:45:f0:5d:29:cd:d1:b0:ce:85:15:5e:62', '2025-01-05 02:44:37'),
(49, 'GV5125615A', 1, 'executed', '43:49:03:41:86:94:69:fa:09:de:38:16:f6:25:ad:0b:bf:09:0a:9a:a3:1c:23:c9', '2025-01-05 02:47:22'),
(50, 'GV5125615A', 1, 'executed', '43:49:03:41:8f:18:01:01:2c:09:3f:f2:14:e9:56:f7:2e:a9:91:a5:52:7b:e9:9d', '2025-01-05 02:47:24'),
(51, 'GV5125615A', 2, 'executed', '43:49:03:41:d6:6c:a4:ce:5a:13:2c:77:ee:03:0b:8c:a2:31:51:c6:9c:06:35:cb', '2025-01-05 02:47:42'),
(52, 'GV5125615A', 5, 'executed', '43:49:03:41:fc:d2:9b:bf:50:94:93:a5:50:b9:7a:d4:9b:ac:c3:46:57:05:14:8e', '2025-01-05 02:47:52'),
(53, 'GV5125615A', 5, 'executed', '43:49:03:42:10:dc:f5:78:3b:8a:81:7e:c5:a3:e1:01:de:58:65:c1:14:62:b0:ec', '2025-01-05 02:47:57'),
(54, 'GV5125615A', 6, 'executed', '43:49:03:42:18:48:c5:c7:4b:a5:f3:f8:12:fc:aa:89:76:4c:ca:97:19:18:e3:4e', '2025-01-05 02:47:59'),
(55, 'GV5125615A', 6, 'executed', '43:49:03:42:61:fe:0f:5f:2b:7f:64:46:f1:25:93:9a:56:f3:59:ea:a4:50:f2:e2', '2025-01-05 02:48:18'),
(56, 'GV5125615A', 1, 'executed', '43:49:03:42:b6:2c:c0:5b:a4:01:64:18:46:d6:91:5a:4d:22:f2:43:05:60:e1:89', '2025-01-05 02:48:40'),
(57, 'GV5125615A', 2, 'executed', '43:49:03:42:be:06:f9:bf:e7:ca:e8:22:76:70:d6:cb:8c:f2:80:c7:bf:1e:99:a3', '2025-01-05 02:48:42'),
(58, 'GV5125615A', 6, 'executed', '43:49:03:42:e6:10:87:3b:7b:98:3f:5b:ef:5a:32:6b:0c:9d:b7:25:ad:86:7d:e2', '2025-01-05 02:48:52'),
(59, 'GV5125615A', 3, 'executed', '43:49:03:45:a0:62:f6:eb:9e:b7:25:8a:84:e0:1c:f0:f4:2e:49:39:73:ed:77:05', '2025-01-05 02:51:51'),
(60, 'GV5125615A', 4, 'executed', '43:49:03:45:a7:24:59:60:d8:45:2e:88:85:db:f0:f9:f8:96:22:ac:36:a7:2d:c3', '2025-01-05 02:51:52'),
(61, 'GV5125615A', 5, 'executed', '43:49:03:4d:49:fc:1a:f4:4a:eb:b9:5b:4c:07:dd:3e:97:fc:c4:bc:da:11:86:7f', '2025-01-05 03:00:13'),
(62, 'GV5125615A', 6, 'executed', '43:49:03:4d:57:44:80:fc:ec:b1:c2:2b:61:a0:c0:7e:5d:3d:d2:5b:43:ac:34:38', '2025-01-05 03:00:16'),
(63, 'GV5125615A', 1, 'executed', '43:49:03:4d:60:5e:c4:1b:1f:f7:81:ad:4a:40:40:52:c6:44:60:99:30:64:d1:a4', '2025-01-05 03:00:18'),
(64, 'GV5125615A', 4, 'executed', '43:49:03:4d:6d:74:43:e7:f4:21:52:57:c5:7d:f7:13:82:77:fa:9d:ab:46:19:4e', '2025-01-05 03:00:22'),
(65, 'GV5125615A', 3, 'executed', '43:49:03:4d:78:50:09:c8:97:28:b3:7d:85:34:9c:9b:5b:b3:36:98:39:f8:34:9b', '2025-01-05 03:00:25'),
(66, 'GV5125615A', 5, 'executed', '43:49:03:52:30:f2:25:3f:e5:71:7c:dd:c1:7e:d5:a4:93:23:30:4a:28:e0:fc:3d', '2025-01-05 03:05:34'),
(67, 'GV5125615A', 6, 'executed', '43:49:03:52:3b:f6:59:8d:f3:3e:2a:8a:8a:89:18:17:e8:ec:c9:60:01:60:4a:4b', '2025-01-05 03:05:37'),
(68, 'GV5125615A', 4, 'executed', '43:49:03:52:54:88:73:bf:8f:82:5c:40:4e:b9:48:b5:3d:1c:0a:27:ec:1b:05:df', '2025-01-05 03:05:43'),
(69, 'GV5125615A', 6, 'executed', '43:49:03:52:5c:12:8d:ce:4e:84:0f:b2:8d:a6:fd:95:90:2a:c5:e0:9d:b7:03:7f', '2025-01-05 03:05:45'),
(70, 'GV5125615A', 1, 'executed', '43:49:03:52:67:16:7d:43:d4:af:f3:9c:65:71:5a:77:0b:89:3a:7b:67:7e:60:e8', '2025-01-05 03:05:48'),
(71, 'GV5125615A', 3, 'executed', '43:49:03:52:6e:6e:7d:5f:63:b5:7a:27:51:ed:99:7b:c2:42:f4:eb:64:7b:ea:01', '2025-01-05 03:05:50'),
(72, 'GV5125615A', 4, 'executed', '43:49:03:52:74:fe:31:5c:66:57:9d:3d:dd:a8:36:fb:11:8c:2b:98:cf:58:fd:b2', '2025-01-05 03:05:51'),
(73, 'GV5125615A', 5, 'executed', '43:49:03:5b:84:c2:89:c2:bf:37:d7:6c:49:3c:6b:6d:1f:f6:50:d3:46:cd:2b:fb', '2025-01-05 03:15:45'),
(74, 'GV5125615A', 6, 'executed', '43:49:03:5b:a3:62:bb:97:41:b5:5b:c2:b9:9e:f5:6a:87:95:da:d9:4e:ba:08:3a', '2025-01-05 03:15:53'),
(75, 'GV5125615A', 1, 'executed', '43:49:03:5b:b8:70:28:00:64:1c:21:bd:21:44:c0:0e:d0:a7:52:87:8e:52:50:0e', '2025-01-05 03:15:58'),
(76, 'GV5125615A', 3, 'executed', '43:49:03:5b:c8:ce:f5:c4:1f:c6:41:0c:0d:6b:20:ca:1f:2f:78:dc:d8:d2:e1:60', '2025-01-05 03:16:03'),
(77, 'GV5125615A', 5, 'executed', '43:49:03:5e:b3:c2:fa:9d:05:81:05:7f:1e:93:9f:17:17:89:e5:77:9e:ff:f6:7c', '2025-01-05 03:19:14'),
(78, 'GV5125615A', 6, 'executed', '43:49:03:5e:db:36:73:1a:c5:f1:fc:bc:32:57:c7:e9:80:38:29:d5:01:ef:28:f0', '2025-01-05 03:19:24'),
(79, 'GV5125615A', 2, 'executed', '43:49:03:5e:e6:4e:26:cf:28:3a:89:9f:0e:e1:0d:13:1a:68:2c:b6:12:68:dc:c7', '2025-01-05 03:19:27'),
(80, 'GV5125207B', 6, 'executed', 'b9:bc:04:ff:01:c8:17:86:ed:ca:16:82:b7:0f:d5:c6:72:31:2d:d3:d4:96:7f:e4', '2025-01-05 03:19:41'),
(81, 'GV5125207B', 1, 'executed', 'b9:bc:04:ff:0a:e2:54:d4:cf:48:6c:a3:e8:77:0b:b3:06:4d:01:5a:43:9f:f2:28', '2025-01-05 03:19:43'),
(82, 'GV5125207B', 5, 'executed', 'b9:bc:04:ff:13:84:14:ef:0c:dc:6f:df:99:85:04:15:17:73:72:38:00:0f:c7:25', '2025-01-05 03:19:45'),
(83, 'GV5125207B', 2, 'executed', 'b9:bc:04:ff:1b:d6:ab:89:2d:60:4b:20:48:1e:f9:ec:6a:2f:4c:a7:19:fd:ad:00', '2025-01-05 03:19:48'),
(84, 'GV5125207B', 6, 'executed', 'b9:bc:04:ff:23:24:bf:04:c4:68:56:e2:34:e0:0d:cf:e6:d2:43:0c:48:1b:2e:75', '2025-01-05 03:19:49'),
(85, 'GV5125615A', 2, 'executed', '43:49:03:5e:e6:4e:26:cf:28:3a:89:9f:0e:e1:0d:13:1a:68:2c:b6:12:68:dc:c7', '2025-01-05 03:20:28'),
(86, 'GV5125207B', 6, 'executed', 'b9:bc:04:ff:f7:9a:bc:5e:98:13:b4:68:3f:35:03:3e:e1:1e:21:0a:e4:07:c9:3d', '2025-01-05 03:20:44'),
(87, 'GV5125207B', 1, 'executed', 'b9:bc:00:02:13:22:54:8d:a5:90:06:c1:5c:86:c5:e4:85:2e:99:0c:3a:a4:ac:ad', '2025-01-05 03:23:02'),
(88, 'GV5125207B', 5, 'executed', 'b9:bc:00:02:1e:44:13:a2:34:b5:7e:56:f3:42:1a:21:10:5b:18:fb:1c:1a:e4:56', '2025-01-05 03:23:05'),
(89, 'GV5125207B', 5, 'executed', 'b9:bc:00:02:2d:4e:14:8f:43:da:21:11:4e:4d:63:dc:7c:70:67:68:83:ea:b4:f9', '2025-01-05 03:23:09'),
(90, 'GV5125207B', 2, 'executed', 'b9:bc:00:02:36:22:95:4f:46:2e:f6:a5:b4:a8:66:d3:02:56:bf:c5:18:8a:75:ee', '2025-01-05 03:23:11'),
(91, 'GV5125207B', 1, 'executed', 'b9:bc:00:02:4e:28:72:6f:29:f6:c7:ec:81:0b:56:7f:27:3c:6c:01:dd:4b:b2:3a', '2025-01-05 03:23:17'),
(92, 'GV5125207B', 5, 'executed', 'b9:bc:00:02:59:a4:d3:73:a4:7f:43:d3:b5:73:5d:4f:05:59:83:21:96:24:a7:99', '2025-01-05 03:23:20'),
(93, 'GV5125207B', 6, 'executed', 'b9:bc:00:02:d2:1c:fb:02:92:8e:6f:06:0e:f1:9b:14:49:e3:64:e4:6b:70:80:f0', '2025-01-05 03:23:51'),
(94, 'GV5125615A', 1, 'executed', '43:49:03:63:0e:36:ad:c3:63:f4:4b:79:bf:84:76:da:af:ac:a5:ed:c1:97:1e:db', '2025-01-05 03:23:59'),
(95, 'GV5125615A', 5, 'executed', '43:49:03:63:1a:02:9f:46:25:cd:58:3e:80:04:7a:41:83:c0:d9:0e:a3:8c:44:c0', '2025-01-05 03:24:02'),
(96, 'GV5125615A', 3, 'executed', '43:49:03:63:23:3a:37:78:96:8c:25:60:a9:0f:11:92:7c:2e:7d:ab:44:73:ef:8b', '2025-01-05 03:24:05'),
(97, 'GV5125615A', 1, 'executed', '43:49:03:63:2c:5e:cd:cb:8d:47:98:64:e7:01:28:8a:e7:bd:73:1c:73:10:58:10', '2025-01-05 03:24:07'),
(98, 'GV5125615A', 3, 'executed', '43:49:03:63:33:de:da:7b:a6:d6:66:ff:86:98:71:c7:6a:28:9f:4b:46:bc:1f:6a', '2025-01-05 03:24:09'),
(99, 'GV5125615A', 2, 'executed', '43:49:03:63:3c:9e:40:2b:2b:a1:02:b6:9a:39:9c:d7:37:cb:0d:d9:0a:5d:f2:27', '2025-01-05 03:24:11'),
(100, 'GV5125615A', 4, 'executed', '43:49:03:63:47:c0:bc:d1:e0:e8:61:2a:32:f9:11:84:6c:0b:d1:da:bb:59:6f:51', '2025-01-05 03:24:14'),
(101, 'GV5125615A', 1, 'executed', '43:49:03:63:69:4e:a3:0f:7f:bf:ad:eb:fb:04:b8:21:8f:70:a2:d6:4d:a2:69:95', '2025-01-05 03:24:23'),
(102, 'GV5125207B', 6, 'executed', 'b9:bc:00:02:d2:1c:fb:02:92:8e:6f:06:0e:f1:9b:14:49:e3:64:e4:6b:70:80:f0', '2025-01-05 03:24:52'),
(103, 'GV5125615A', 6, 'executed', '43:49:03:64:46:0c:ef:15:22:e8:42:2b:84:1b:78:b6:39:d9:3c:fb:ff:db:9e:71', '2025-01-05 03:25:19'),
(104, 'GV5125615A', 5, 'executed', '43:49:03:64:4f:76:13:80:e1:42:f8:ab:44:71:b0:78:20:a7:15:96:b8:79:02:05', '2025-01-05 03:25:22'),
(105, 'GV5125615A', 3, 'executed', '43:49:03:64:78:02:c8:1c:86:7d:03:33:74:73:19:72:aa:d9:20:6c:ef:06:e3:07', '2025-01-05 03:25:32'),
(106, 'GV5125615A', 4, 'executed', '43:49:03:64:81:9e:5f:b9:5a:60:1d:a6:f6:69:31:16:c9:63:12:d1:b0:f1:4b:a4', '2025-01-05 03:25:35'),
(107, 'GV5125615A', 6, 'executed', '43:49:03:64:9e:90:61:8a:45:c0:ef:50:3e:6d:82:32:4c:3d:c2:50:f9:53:b6:e6', '2025-01-05 03:25:42'),
(108, 'GV5125615A', 4, 'executed', '43:49:03:64:ab:06:f8:57:0a:82:a3:c3:23:19:90:1b:d7:a8:b7:cb:2c:fb:75:91', '2025-01-05 03:25:45'),
(109, 'GV5125615A', 5, 'executed', '43:49:03:67:bc:2e:90:de:63:c1:56:93:3a:a2:87:75:9d:27:65:91:76:23:a8:27', '2025-01-05 03:29:06'),
(110, 'GV5125615A', 6, 'executed', '43:49:03:67:ca:e8:e7:ad:d0:bb:57:38:e3:05:0d:61:f8:89:a7:93:ad:dd:b2:fe', '2025-01-05 03:29:10'),
(111, 'GV5125615A', 4, 'executed', '43:49:03:67:d7:18:0b:03:98:92:2d:a7:ec:e2:fa:b5:b9:7f:ec:26:9f:97:2c:39', '2025-01-05 03:29:13'),
(112, 'GV5125615A', 1, 'executed', '43:49:04:b0:91:0a:5a:69:19:dc:ff:99:72:ea:65:9f:2f:2e:fe:84:cf:33:28:04', '2025-01-05 09:28:13'),
(113, 'GV5125615A', 3, 'executed', '43:49:04:b0:99:ac:f1:f2:67:66:05:15:e1:14:e3:bf:b8:4e:85:50:48:cb:34:da', '2025-01-05 09:28:15'),
(114, 'GV5125615A', 4, 'executed', '43:49:04:b0:a0:78:32:3a:ef:e8:ad:2f:e0:5e:68:60:b4:4b:e1:b5:6c:f6:4c:e0', '2025-01-05 09:28:17'),
(115, 'GV5125615A', 2, 'executed', '43:49:04:d0:e6:26:c0:58:c0:eb:2b:44:63:f7:62:84:a1:98:c6:8f:48:18:be:09', '2025-01-05 10:03:32'),
(116, 'GV5125615A', 1, 'executed', '43:49:04:d0:ef:18:c3:5d:97:7c:8e:0f:39:2d:f2:b0:85:f7:0c:cb:d4:92:8d:cd', '2025-01-05 10:03:34'),
(117, 'GV5125615A', 3, 'executed', '43:49:04:e6:35:44:4d:92:c0:33:90:55:a0:4c:b8:42:c6:a2:75:8d:7c:16:e0:a8', '2025-01-05 10:26:48'),
(118, 'GV5125615A', 1, 'executed', '43:49:04:e6:3e:18:3d:be:0c:b9:c2:c8:c3:47:09:5d:53:74:da:31:93:04:22:e7', '2025-01-05 10:26:50'),
(119, 'GV5125207B', 1, 'executed', 'b9:bc:01:88:2a:2e:c5:be:a2:45:56:e8:a5:d3:ba:ba:94:7f:7e:f9:34:df:e9:b8', '2025-01-05 10:28:59'),
(120, 'GV5125207B', 2, 'executed', 'b9:bc:01:88:32:30:76:7b:9e:38:a5:7f:0e:a6:d9:bc:2b:6d:cc:97:ed:18:64:f6', '2025-01-05 10:29:01'),
(121, 'GV5125615A', 2, 'executed', '43:49:00:0f:8b:7e:08:a1:58:47:91:47:ef:5a:1b:02:03:bb:1d:b3:8f:37:03:ab', '2025-01-05 11:11:57'),
(122, 'GV5125615A', 1, 'executed', '43:49:00:0f:92:d6:57:c5:c8:55:b3:17:02:63:75:9c:22:b8:5d:39:fc:9d:64:d7', '2025-01-05 11:11:59'),
(123, 'GV5125615A', 3, 'executed', '43:49:00:0f:a5:00:59:68:ae:95:32:9c:d7:0b:b1:29:1d:2c:36:de:88:43:91:35', '2025-01-05 11:12:04'),
(124, 'GV5125615A', 2, 'executed', '43:49:00:0f:be:78:ac:81:f9:5c:ad:51:c3:2e:d3:22:d9:2e:8d:fc:c6:44:63:24', '2025-01-05 11:12:10'),
(125, 'GV5125615A', 1, 'executed', '43:49:00:b1:37:f8:fc:df:c0:dc:8e:2d:4a:a4:49:63:8c:c1:68:48:79:12:e2:79', '2025-01-05 14:08:31'),
(126, 'GV5125615A', 2, 'executed', '43:49:00:b1:4b:44:3e:34:bd:bb:37:c1:ba:35:3e:bc:ce:ac:3c:7d:b9:bf:64:cd', '2025-01-05 14:08:36'),
(127, 'GV5125615A', 5, 'executed', '43:49:01:2b:28:ba:db:00:46:b2:a9:9b:9f:44:04:3a:9e:c3:e6:24:c3:ae:d8:dd', '2025-01-05 16:21:41'),
(128, 'GV5125615A', 6, 'executed', '43:49:01:2b:31:b6:7c:fb:43:a9:84:a4:86:ce:49:a1:eb:e6:ac:84:0d:75:83:a3', '2025-01-05 16:21:43'),
(129, 'GV5125615A', 3, 'executed', '43:49:01:2b:3a:d0:34:87:03:42:2a:2e:00:35:bd:6a:62:4e:f1:74:31:ee:b8:fb', '2025-01-05 16:21:46'),
(130, 'GV5125615A', 6, 'executed', '43:49:01:2b:43:c2:8d:0e:d7:ce:4f:20:a0:53:a4:b8:b4:b9:32:62:74:69:22:ff', '2025-01-05 16:21:48'),
(131, 'GV5125615A', 5, 'executed', '43:49:01:2b:4c:b4:95:8f:f5:52:13:18:a3:9c:bb:cf:8f:fa:d9:9e:a7:73:d1:2b', '2025-01-05 16:21:50'),
(132, 'GV5125207B', 6, 'executed', 'b9:bc:02:cb:5d:7a:fc:30:a7:78:73:66:cb:d2:84:81:09:ee:04:19:15:49:df:b2', '2025-01-05 16:21:54'),
(133, 'GV5125207B', 5, 'executed', 'b9:bc:02:cb:68:88:f7:a8:f0:69:99:ea:b5:b3:3c:ac:d8:2e:33:f5:dd:77:4d:0e', '2025-01-05 16:21:56'),
(134, 'GV5125615A', 5, 'executed', '43:49:01:2b:4c:b4:95:8f:f5:52:13:18:a3:9c:bb:cf:8f:fa:d9:9e:a7:73:d1:2b', '2025-01-05 16:22:51'),
(135, 'GV5125207B', 5, 'executed', 'b9:bc:02:cb:68:88:f7:a8:f0:69:99:ea:b5:b3:3c:ac:d8:2e:33:f5:dd:77:4d:0e', '2025-01-05 16:22:58'),
(136, 'GV5125207B', 2, 'executed', 'b9:bc:02:d2:ad:c8:54:6f:c9:a8:6e:f6:68:c5:c8:63:c3:09:dd:73:ae:cf:ab:3c', '2025-01-05 16:29:53'),
(137, 'GV5125207B', 1, 'executed', 'b9:bc:02:d2:ba:02:5f:69:9e:85:08:2d:d5:3d:c4:a5:8f:7e:04:b3:7a:bd:5e:54', '2025-01-05 16:29:56'),
(138, 'GV5125207B', 5, 'executed', 'b9:bc:02:d2:c6:1e:64:a2:63:ca:6b:89:48:68:5f:ec:56:bf:ca:39:81:69:d2:15', '2025-01-05 16:29:59'),
(139, 'GV5125207B', 6, 'executed', 'b9:bc:02:d2:cd:e4:fb:ac:b2:fd:7b:81:d9:5a:18:c2:56:d7:7c:40:a3:9c:c0:2d', '2025-01-05 16:30:01'),
(140, 'GV5125207B', 2, 'executed', 'b9:bc:02:d2:df:32:7a:02:d2:d0:1b:84:1b:dc:73:f8:16:71:95:ba:92:dc:4e:e7', '2025-01-05 16:30:05'),
(141, 'GV5125207B', 6, 'executed', 'b9:bc:02:d2:e6:8a:99:26:2e:a7:8f:ac:ab:0f:73:54:5f:0c:1b:a7:95:e0:2e:af', '2025-01-05 16:30:07'),
(142, 'GV5122427B', 1, 'executed', '37:0c:00:0e:48:cc:a0:2a:7e:79:ad:3a:6d:7f:40:5b:41:e5:9c:22:ff:73:d3:aa', '2025-01-06 19:06:55'),
(143, 'GV51224B48', 1, 'executed', 'b5:c3:00:10:20:c0:f4:9b:43:73:b9:33:96:81:19:26:90:6c:2b:95:b7:8b:23:12', '2025-01-06 19:06:57'),
(144, 'GV51224B48', 1, 'executed', 'b5:c3:00:10:2e:4e:92:cd:74:17:c1:e5:4e:a8:d9:14:73:cd:57:f9:35:5f:fb:b7', '2025-01-06 19:07:01'),
(145, 'GV5122427B', 1, 'executed', '37:0c:00:0e:6a:e6:f7:3d:8e:27:4b:8a:2f:13:bd:91:d5:ef:bb:5d:47:53:79:14', '2025-01-06 19:07:03'),
(146, 'GV51224B48', 1, 'executed', 'b5:c3:00:10:2e:4e:92:cd:74:17:c1:e5:4e:a8:d9:14:73:cd:57:f9:35:5f:fb:b7', '2025-01-06 19:08:02'),
(147, 'GV5122427B', 1, 'executed', '37:0c:00:0e:6a:e6:f7:3d:8e:27:4b:8a:2f:13:bd:91:d5:ef:bb:5d:47:53:79:14', '2025-01-06 19:08:05'),
(148, 'GV5122427B', 1, 'executed', '37:0c:00:1d:75:2c:d2:5e:94:ba:c6:31:13:c1:eb:3b:35:d0:08:83:f8:14:e1:7f', '2025-01-06 19:23:29'),
(149, 'GV5122427B', 1, 'executed', '37:0c:00:1d:a2:22:53:7f:09:44:03:c0:18:4f:46:be:10:c1:72:40:84:e5:47:ab', '2025-01-06 19:23:40'),
(150, 'GV5122427B', 1, 'executed', '37:0c:00:1d:dc:06:ca:8a:ec:07:ff:74:f9:e2:8d:16:83:da:fa:e9:f6:b4:1a:7d', '2025-01-06 19:23:55'),
(151, 'GV5122427B', 1, 'executed', '37:0c:00:1d:e6:d8:10:44:5d:d2:10:6b:11:81:47:c2:d6:1b:49:8f:ef:9b:d6:26', '2025-01-06 19:23:58'),
(152, 'GV5122427B', 1, 'executed', '37:0c:00:1e:af:0a:40:2a:f7:ea:d2:58:75:e5:50:0f:a2:9b:4f:28:36:58:f2:ac', '2025-01-06 19:24:49'),
(153, 'GV5122427B', 1, 'executed', '37:0c:00:1e:b9:a0:06:7c:fd:7d:1a:c2:e5:39:a4:bf:c7:88:d1:e5:86:e2:e2:5c', '2025-01-06 19:24:52'),
(154, 'GV5122427B', 1, 'executed', '37:0c:00:1e:d6:42:81:9d:69:39:64:2d:fd:84:17:f9:b7:6e:ed:c0:d1:8f:d7:64', '2025-01-06 19:24:59'),
(155, 'GV5122427B', 1, 'executed', '37:0c:00:1e:e1:14:bf:03:8e:ae:a3:73:d3:c3:9f:7d:54:da:57:f6:e1:96:a0:23', '2025-01-06 19:25:02'),
(156, 'GV5122427B', 1, 'executed', '37:0c:00:1e:fa:be:1e:d7:98:a6:a2:ef:42:01:fb:f1:a5:80:49:2f:56:0f:15:7e', '2025-01-06 19:25:09'),
(157, 'GV5122427B', 1, 'executed', '37:0c:00:1f:04:50:78:37:39:57:c2:72:27:7a:90:e9:c1:d6:d8:67:8f:e7:17:68', '2025-01-06 19:25:11'),
(158, 'GV5122427B', 1, 'executed', '37:0c:00:1f:12:4c:37:1b:3b:c1:45:08:c7:26:05:c8:fc:8f:90:d8:b2:53:3b:cf', '2025-01-06 19:25:15'),
(159, 'GV5122427B', 1, 'executed', '37:0c:00:1f:1d:f0:28:c5:b1:44:13:91:3d:c6:fb:b4:93:78:69:9d:5b:a8:19:72', '2025-01-06 19:25:18'),
(160, 'GV51224B48', 1, 'executed', 'b5:c3:00:21:15:4c:15:80:26:a8:35:3c:50:34:02:64:33:b7:5b:ab:49:c9:46:45', '2025-01-06 19:25:29'),
(161, 'GV51224B48', 1, 'executed', 'b5:c3:00:21:21:36:ec:a6:24:2d:80:68:69:15:fb:0b:1e:b3:03:23:47:da:93:01', '2025-01-06 19:25:31'),
(162, 'GV51224B48', 1, 'executed', 'b5:c3:00:21:2d:b6:2c:df:d1:61:67:b5:e9:d8:b6:95:cb:2e:05:72:f0:21:13:ba', '2025-01-06 19:25:35'),
(163, 'GV51224B48', 1, 'executed', 'b5:c3:00:21:38:92:7e:3b:c1:a5:9a:eb:18:f9:16:8a:5b:74:4a:56:c0:65:82:21', '2025-01-06 19:25:38'),
(164, 'GV51224B48', 1, 'executed', 'b5:c3:00:21:75:00:84:c2:f0:d4:e7:10:ee:fc:b2:92:e2:12:e9:c2:89:31:66:80', '2025-01-06 19:25:53'),
(165, 'GV5122427B', 1, 'executed', '37:0c:00:1f:b4:5e:52:91:00:fa:80:26:8b:ef:88:7a:11:22:e5:74:39:06:1e:23', '2025-01-06 19:25:56'),
(166, 'GV51224B48', 1, 'executed', 'b5:c3:00:21:75:00:84:c2:f0:d4:e7:10:ee:fc:b2:92:e2:12:e9:c2:89:31:66:80', '2025-01-06 19:26:54'),
(167, 'GV5122427B', 1, 'executed', '37:0c:00:1f:b4:5e:52:91:00:fa:80:26:8b:ef:88:7a:11:22:e5:74:39:06:1e:23', '2025-01-06 19:26:57'),
(168, 'GV51224B48', 1, 'executed', 'b5:c3:00:2d:c7:06:bb:7c:d9:66:ff:eb:86:d2:37:9f:85:ba:ac:f2:18:bb:f8:7b', '2025-01-06 19:39:20'),
(169, 'GV51224B48', 1, 'executed', 'b5:c3:00:2d:d2:5a:42:30:43:28:8b:f6:f5:49:5b:0b:36:4d:6d:d7:77:57:36:d7', '2025-01-06 19:39:23'),
(170, 'GV51224B48', 1, 'executed', 'b5:c3:00:2d:d9:e4:1e:c8:d6:41:40:6b:b9:a8:51:43:55:5e:53:09:67:15:34:21', '2025-01-06 19:39:25'),
(171, 'GV51224B48', 1, 'executed', 'b5:c3:00:2d:e4:34:74:ca:1f:e3:d0:79:c1:3c:2f:42:b5:60:70:0e:34:00:7f:83', '2025-01-06 19:39:28'),
(172, 'GV51224B48', 1, 'executed', 'b5:c3:00:2e:98:5c:ef:05:35:85:c9:cd:63:06:72:de:95:01:0f:d2:30:26:3f:20', '2025-01-06 19:40:14'),
(173, 'GV51224B48', 1, 'executed', 'b5:c3:00:2e:a7:98:27:58:00:2a:0a:96:d9:d7:e3:c0:30:4e:c6:41:e2:58:bd:23', '2025-01-06 19:40:18'),
(174, 'GV5125615A', 6, 'executed', '43:49:02:13:01:8a:d6:6c:dd:47:b3:84:0e:41:5e:a0:70:cc:97:ce:af:a7:c2:b9', '2025-01-06 19:52:45'),
(175, 'GV5125615A', 5, 'executed', '43:49:02:13:09:46:2c:b9:8a:75:93:90:a9:eb:ef:04:26:ea:15:25:72:07:71:54', '2025-01-06 19:52:47'),
(176, 'GV5125207B', 6, 'executed', 'b9:bc:03:b3:4f:22:82:cb:61:6b:82:1b:7e:13:6d:0b:e5:56:38:e9:89:25:b0:63', '2025-01-06 19:52:50'),
(177, 'GV5125207B', 5, 'executed', 'b9:bc:03:b3:55:bc:4e:ff:ad:8b:fa:3f:e4:dc:fc:f0:08:67:61:58:55:6e:db:dc', '2025-01-06 19:52:51'),
(178, 'GV5125207B', 3, 'executed', 'b9:bc:03:b3:5e:e0:1c:8b:9f:b8:e8:57:1e:80:fe:fd:79:6f:0b:0e:e2:26:90:15', '2025-01-06 19:52:54'),
(179, 'GV5125207B', 4, 'executed', 'b9:bc:03:b3:6b:7e:74:70:57:95:86:88:e7:86:cf:d0:8d:fd:7c:b9:1c:de:61:22', '2025-01-06 19:52:57'),
(180, 'GV5125207B', 1, 'executed', 'b9:bc:03:b3:73:6c:7c:38:c6:90:18:ed:be:8e:2d:a1:81:9e:0f:47:30:3a:37:df', '2025-01-06 19:52:59'),
(181, 'GV5125207B', 2, 'executed', 'b9:bc:03:b3:7f:7e:f1:50:3b:2e:b7:49:b1:a5:1e:76:cc:f0:c9:e6:d0:90:b8:3a', '2025-01-06 19:53:02'),
(182, 'GV5125615A', 5, 'executed', '43:49:02:13:09:46:2c:b9:8a:75:93:90:a9:eb:ef:04:26:ea:15:25:72:07:71:54', '2025-01-06 19:53:48'),
(183, 'GV5125207B', 2, 'executed', 'b9:bc:03:b3:7f:7e:f1:50:3b:2e:b7:49:b1:a5:1e:76:cc:f0:c9:e6:d0:90:b8:3a', '2025-01-06 19:54:03'),
(184, 'GV5125615A', 5, 'executed', '43:49:02:46:09:f4:85:fc:6f:64:d5:cc:b6:f3:b4:d9:95:5f:eb:e2:3c:36:39:f8', '2025-01-06 20:48:29'),
(185, 'GV5125615A', 6, 'executed', '43:49:02:46:13:68:d3:4f:3c:f1:31:c9:8a:f3:08:53:15:4d:2a:81:bb:13:f1:c0', '2025-01-06 20:48:32'),
(186, 'GV5125207B', 5, 'executed', 'b9:bc:03:e6:67:a4:8b:b6:62:43:2f:ec:cb:43:49:6d:32:75:a0:a3:bc:d3:04:9a', '2025-01-06 20:48:37'),
(187, 'GV5125207B', 1, 'executed', 'b9:bc:03:e6:6f:b0:6f:c3:8c:12:16:bb:6e:bb:a5:b8:e7:99:1d:15:16:42:32:8e', '2025-01-06 20:48:39'),
(188, 'GV5125615A', 6, 'executed', '43:49:02:46:13:68:d3:4f:3c:f1:31:c9:8a:f3:08:53:15:4d:2a:81:bb:13:f1:c0', '2025-01-06 20:49:33'),
(189, 'GV5125207B', 1, 'executed', 'b9:bc:03:e6:6f:b0:6f:c3:8c:12:16:bb:6e:bb:a5:b8:e7:99:1d:15:16:42:32:8e', '2025-01-06 20:49:41'),
(190, 'GV5125207B', 5, 'executed', 'b9:bc:03:e7:c7:84:da:5c:41:a7:0b:92:42:68:bb:de:82:49:f5:2d:01:08:db:e1', '2025-01-06 20:50:07'),
(191, 'GV5125207B', 6, 'executed', 'b9:bc:03:e7:d2:e2:39:42:24:e6:5d:0f:6c:29:8c:88:a3:14:6a:48:5f:5a:78:03', '2025-01-06 20:50:10'),
(192, 'GV5125207B', 1, 'executed', 'b9:bc:03:e7:dd:8c:b4:2b:e5:57:88:af:9c:a0:9f:14:2c:e8:a0:4c:98:52:07:73', '2025-01-06 20:50:13'),
(193, 'GV5125207B', 1, 'executed', 'b9:bc:03:e7:f1:a0:90:11:22:63:da:c7:3b:5b:ab:56:bf:bc:3b:1a:98:63:41:99', '2025-01-06 20:50:18'),
(194, 'GV5125207B', 1, 'executed', 'b9:bc:03:e8:3c:1e:2d:56:5c:39:4b:64:70:ef:d0:01:48:43:aa:3c:41:45:78:73', '2025-01-06 20:50:37'),
(195, 'GV5125207B', 1, 'executed', 'b9:bc:03:e8:52:9e:de:73:87:64:91:7f:56:79:b4:9f:79:69:8d:08:6f:2a:e3:ae', '2025-01-06 20:50:43'),
(196, 'GV5125207B', 1, 'executed', 'b9:bc:03:e8:87:96:ae:86:79:c2:2d:01:a7:52:5f:ff:c6:9f:1f:8b:7f:dd:b4:f1', '2025-01-06 20:50:56'),
(197, 'GV5125207B', 1, 'executed', 'b9:bc:03:ea:24:98:9f:0b:93:5b:f8:4e:c6:2a:33:28:d8:26:1b:d5:47:cb:a0:6d', '2025-01-06 20:52:42'),
(198, 'GV5125207B', 3, 'executed', 'b9:bc:03:ea:2b:fa:ff:d8:e9:91:9a:09:06:49:45:c6:99:88:7c:00:14:26:00:2e', '2025-01-06 20:52:44'),
(199, 'GV5125207B', 1, 'executed', 'b9:bc:03:ea:49:50:67:4f:9a:1f:c9:81:21:54:99:76:fd:39:58:16:c7:67:64:e6', '2025-01-06 20:52:52'),
(200, 'GV5125207B', 5, 'executed', 'b9:bc:03:ea:79:20:1c:fd:d8:3d:b8:94:29:2c:cd:a8:a6:64:26:52:f4:23:1e:52', '2025-01-06 20:53:04'),
(201, 'GV5125207B', 1, 'executed', 'b9:bc:03:ea:84:24:f6:32:42:0e:db:71:15:dd:21:6f:d0:71:d1:c4:d9:79:57:eb', '2025-01-06 20:53:07'),
(202, 'GV5125207B', 5, 'executed', 'b9:bc:03:ea:b9:d0:21:65:8c:95:57:7b:fc:d7:7b:a9:82:aa:75:0d:f7:5b:b0:89', '2025-01-06 20:53:20'),
(203, 'GV5125207B', 1, 'executed', 'b9:bc:03:ea:db:fe:9f:3f:fe:11:3d:50:23:bb:2c:3c:9e:2f:9f:53:20:20:e0:64', '2025-01-06 20:53:29'),
(204, 'GV5125207B', 5, 'executed', 'b9:bc:03:ea:e5:90:24:6c:cc:38:5b:9a:6d:ca:c9:21:22:ef:03:21:eb:91:25:d4', '2025-01-06 20:53:32'),
(205, 'GV5125207B', 5, 'executed', 'b9:bc:03:ea:ed:a6:68:df:d6:c9:de:c6:3b:f6:0b:b5:45:2e:8e:a0:ec:f0:5e:54', '2025-01-06 20:53:34'),
(206, 'GV5125207B', 6, 'executed', 'b9:bc:03:ea:f5:b2:dc:71:54:a7:0a:b0:7d:08:fe:9a:f4:16:2a:57:4c:d5:c6:53', '2025-01-06 20:53:36'),
(207, 'GV5125207B', 2, 'executed', 'b9:bc:03:eb:06:ce:f0:96:f6:de:36:98:a4:c6:e6:99:cf:06:c2:49:0c:d2:5d:e4', '2025-01-06 20:53:40'),
(208, 'GV5125207B', 5, 'executed', 'b9:bc:03:ef:28:30:a2:cc:68:b3:0b:09:e3:c9:fd:96:1c:d6:ff:14:79:e5:cb:26', '2025-01-06 20:58:11'),
(209, 'GV5125207B', 1, 'executed', 'b9:bc:03:ef:33:ac:12:40:1b:02:f4:24:51:5b:f5:c8:6c:7f:2a:78:29:01:31:89', '2025-01-06 20:58:14'),
(210, 'GV5125207B', 3, 'executed', 'b9:bc:03:ef:3b:a4:a9:93:5e:c8:d1:8f:b4:89:23:30:37:71:3e:9f:bf:06:74:d1', '2025-01-06 20:58:16'),
(211, 'GV5125207B', 1, 'executed', 'b9:bc:03:ef:48:10:8c:45:fd:c5:ba:42:24:4b:c9:02:81:12:73:23:e9:d6:07:41', '2025-01-06 20:58:19'),
(212, 'GV5125207B', 2, 'executed', 'b9:bc:03:f1:83:c8:1e:68:f9:d3:2e:f9:d9:73:e1:d8:3a:d4:c4:90:3d:6b:6c:9e', '2025-01-06 21:00:45'),
(213, 'GV5125207B', 2, 'executed', 'b9:bc:03:f1:97:3c:dc:2e:60:da:04:b7:a8:b3:eb:1a:c4:b0:b8:eb:6e:c2:73:4a', '2025-01-06 21:00:50'),
(214, 'GV5125207B', 4, 'executed', 'b9:bc:03:f1:9e:4e:e4:51:ff:05:0e:43:d6:53:b9:f9:57:3e:b7:28:1a:c9:fb:19', '2025-01-06 21:00:52'),
(215, 'GV5125207B', 6, 'executed', 'b9:bc:03:f1:a7:a4:00:b3:7d:f5:c0:d4:f2:fc:5f:b0:b5:95:e1:85:db:25:90:a6', '2025-01-06 21:00:54'),
(216, 'GV5125207B', 6, 'executed', 'b9:bc:03:f1:b4:d8:ca:2a:75:01:a5:2a:5e:53:e0:78:da:49:09:59:5f:aa:8d:14', '2025-01-06 21:00:58'),
(217, 'GV5125207B', 2, 'executed', 'b9:bc:03:f1:f3:6c:5d:f4:4d:10:30:c7:59:11:94:44:b0:3f:1a:44:4e:b6:69:72', '2025-01-06 21:01:14'),
(218, 'GV5125207B', 4, 'executed', 'b9:bc:03:f2:09:b0:10:f4:f8:a0:e4:72:b2:33:94:54:af:2b:fa:eb:5b:8f:74:76', '2025-01-06 21:01:19'),
(219, 'GV5125207B', 2, 'executed', 'b9:bc:03:f4:4d:f6:d6:b6:97:7b:0e:65:4a:93:49:a9:71:90:2c:82:c0:db:41:94', '2025-01-06 21:03:48'),
(220, 'GV5125207B', 2, 'executed', 'b9:bc:03:f4:56:34:f1:26:80:e0:2b:58:6d:ea:51:14:04:f9:46:b5:ed:78:54:f9', '2025-01-06 21:03:50'),
(221, 'GV5125207B', 6, 'executed', 'b9:bc:03:f4:5f:e4:de:e0:42:b4:2d:bc:c6:e0:82:3e:61:87:a2:ac:35:d7:12:69', '2025-01-06 21:03:53'),
(222, 'GV5125207B', 6, 'executed', 'b9:bc:03:f4:68:40:92:c8:bc:13:84:a8:25:e1:d0:e6:ba:bb:a2:43:dc:f4:49:e2', '2025-01-06 21:03:55'),
(223, 'GV5125207B', 2, 'executed', 'b9:bc:03:f6:4b:24:8c:bf:5a:58:7b:73:d4:a7:08:77:40:58:ec:bf:04:10:d5:98', '2025-01-06 21:05:58'),
(224, 'GV5125207B', 2, 'executed', 'b9:bc:03:f6:53:62:f0:7d:3d:2a:74:5c:fe:64:c4:11:ff:f9:10:97:d6:d8:83:94', '2025-01-06 21:06:00'),
(225, 'GV5125207B', 4, 'executed', 'b9:bc:03:f6:6a:64:9e:2a:58:5f:f9:43:aa:d0:0c:f0:a2:a5:60:5b:05:3a:f1:88', '2025-01-06 21:06:06'),
(226, 'GV5125207B', 6, 'executed', 'b9:bc:03:f6:73:2e:61:f3:57:c9:7b:d8:ad:88:eb:57:da:b4:2e:0b:4c:b7:1c:a3', '2025-01-06 21:06:09'),
(227, 'GV5125207B', 6, 'executed', 'b9:bc:03:f6:83:78:65:1c:2d:21:d2:6a:8b:5e:2f:aa:82:fd:41:1d:a5:e8:55:4e', '2025-01-06 21:06:13'),
(228, 'GV5125207B', 6, 'executed', 'b9:bc:03:f6:94:e4:72:17:d3:9e:78:c0:61:be:9f:6d:fa:3f:2f:cd:a7:08:38:02', '2025-01-06 21:06:17'),
(229, 'GV5125207B', 3, 'executed', 'b9:bc:03:f8:d4:0c:f0:b5:46:0b:98:96:e5:ac:17:63:b9:75:9f:78:8b:1f:c2:f1', '2025-01-06 21:08:44'),
(230, 'GV5125207B', 5, 'executed', 'b9:bc:03:f8:da:ba:aa:45:34:d3:91:e5:7e:81:0e:98:82:be:a7:a3:58:cb:88:9c', '2025-01-06 21:08:46'),
(231, 'GV5125207B', 1, 'executed', 'b9:bc:03:f8:e6:2c:4b:b1:b2:78:f0:88:14:de:27:32:b1:ac:7b:a5:9d:ba:8d:e0', '2025-01-06 21:08:49'),
(232, 'GV5122427B', 1, 'executed', '37:0c:00:7e:94:b0:22:25:d9:75:f7:12:68:f8:6c:d5:a0:8c:03:0c:6e:13:07:b3', '2025-01-06 21:09:33'),
(233, 'GV51224B48', 1, 'executed', 'b5:c3:00:80:7d:48:14:9a:92:bd:5e:03:be:96:01:6b:10:96:d0:9b:78:29:2a:2a', '2025-01-06 21:09:41'),
(234, 'GV51224B48', 1, 'executed', 'b5:c3:00:80:86:44:8c:2d:cf:72:ec:74:94:77:14:60:08:86:38:de:29:03:45:8d', '2025-01-06 21:09:43'),
(235, 'GV51224B48', 1, 'executed', 'b5:c3:00:80:9a:80:a6:09:e9:7b:db:13:4d:08:fa:be:84:56:d6:25:ba:1d:f0:69', '2025-01-06 21:09:48'),
(236, 'GV5125207B', 1, 'executed', 'b9:bc:03:f8:e6:2c:4b:b1:b2:78:f0:88:14:de:27:32:b1:ac:7b:a5:9d:ba:8d:e0', '2025-01-06 21:09:50'),
(237, 'GV51224B48', 1, 'executed', 'b5:c3:00:80:a4:ee:aa:1f:d4:b9:3f:43:ca:01:de:7c:ef:57:7a:00:17:d1:7a:19', '2025-01-06 21:09:51'),
(238, 'GV5122427B', 1, 'executed', '37:0c:00:7e:94:b0:22:25:d9:75:f7:12:68:f8:6c:d5:a0:8c:03:0c:6e:13:07:b3', '2025-01-06 21:10:35'),
(239, 'GV51224B48', 1, 'executed', 'b5:c3:00:80:a4:ee:aa:1f:d4:b9:3f:43:ca:01:de:7c:ef:57:7a:00:17:d1:7a:19', '2025-01-06 21:10:52'),
(240, 'GV5125207B', 3, 'executed', 'b9:bc:03:fa:f7:c8:31:1b:89:45:73:48:56:64:b5:76:94:be:67:f9:2f:e2:08:20', '2025-01-06 21:11:05'),
(241, 'GV5125207B', 4, 'executed', 'b9:bc:03:fb:05:24:3f:af:f4:b1:f4:17:cc:24:95:65:44:7b:c0:67:e5:65:6a:db', '2025-01-06 21:11:08'),
(242, 'GV5125207B', 4, 'executed', 'b9:bc:03:fb:10:1e:5d:98:50:57:fa:e0:5c:b8:9c:59:dc:17:1f:88:26:94:5c:27', '2025-01-06 21:11:11'),
(243, 'GV5125207B', 4, 'executed', 'b9:bc:03:fb:17:da:38:3f:d6:34:83:a0:96:44:53:49:7b:23:a4:b3:91:b8:61:d1', '2025-01-06 21:11:13'),
(244, 'GV5125207B', 2, 'executed', 'b9:bc:03:fb:1f:6e:40:2e:f6:31:3e:b3:ab:10:fc:2a:f4:9e:a0:77:39:e9:1e:f3', '2025-01-06 21:11:15'),
(245, 'GV5125207B', 2, 'executed', 'b9:bc:03:fb:27:70:22:bd:68:8b:42:75:13:f1:ee:72:4e:af:08:c8:5a:a8:b9:b7', '2025-01-06 21:11:17'),
(246, 'GV5125207B', 6, 'executed', 'b9:bc:03:fb:2e:50:74:e8:c8:4f:88:67:cd:6d:98:9b:eb:95:f2:ce:8d:6c:e1:0d', '2025-01-06 21:11:19'),
(247, 'GV5125207B', 6, 'executed', 'b9:bc:03:fb:3a:94:67:4e:9f:1e:29:4e:31:13:8c:eb:a0:e1:35:37:d4:2d:4a:c2', '2025-01-06 21:11:22'),
(248, 'GV5125207B', 1, 'executed', 'b9:bc:03:fb:65:a0:6d:5d:34:94:02:01:f1:99:76:b4:a9:eb:1c:4c:5c:3d:89:b2', '2025-01-06 21:11:33'),
(249, 'GV5125207B', 3, 'executed', 'b9:bc:03:fb:75:40:b5:33:c5:a9:58:b4:d5:09:ea:4d:46:dd:7d:6f:fd:68:ab:2b', '2025-01-06 21:11:37'),
(250, 'GV5125207B', 5, 'executed', 'b9:bc:04:1a:43:4e:c3:19:bf:5e:66:26:26:23:3b:40:69:8f:0b:d8:a2:6d:9b:d9', '2025-01-06 21:45:15'),
(251, 'GV5125207B', 1, 'executed', 'b9:bc:04:1a:4a:88:ad:75:05:8e:33:f4:f2:93:af:75:89:7d:6c:be:ee:11:92:76', '2025-01-06 21:45:17'),
(252, 'GV5125207B', 6, 'executed', 'b9:bc:04:1a:53:de:69:50:1a:ef:c2:dc:23:6a:50:e8:84:ac:5e:ba:cd:93:01:37', '2025-01-06 21:45:20'),
(253, 'GV5125207B', 3, 'executed', 'b9:bc:04:1c:5f:b2:06:16:c1:d5:82:04:e8:35:fa:e4:52:14:96:ec:a3:9d:65:c2', '2025-01-06 21:47:34'),
(254, 'GV5125207B', 4, 'executed', 'b9:bc:04:1c:7c:b8:62:fd:77:3c:54:9d:82:a8:61:6f:51:13:f4:4b:5e:f7:78:8a', '2025-01-06 21:47:41'),
(255, 'GV5125207B', 4, 'executed', 'b9:bc:04:1c:88:b6:ca:84:2b:03:0c:1c:81:3f:00:2b:c2:bf:9f:73:e6:93:fd:3a', '2025-01-06 21:47:44'),
(256, 'GV5125207B', 4, 'executed', 'b9:bc:04:1c:90:7c:25:c4:22:83:9a:f8:d9:89:3c:7a:3b:e3:92:1e:30:54:e0:f0', '2025-01-06 21:47:46'),
(257, 'GV5125207B', 2, 'executed', 'b9:bc:04:1c:98:ce:86:47:f9:0e:fe:47:40:14:35:37:1d:78:8d:03:32:5c:42:7e', '2025-01-06 21:47:48'),
(258, 'GV5125207B', 2, 'executed', 'b9:bc:04:1c:ae:0e:e6:63:d8:f1:ca:9a:df:25:24:67:8a:e5:7e:9c:d8:29:b3:57', '2025-01-06 21:47:54'),
(259, 'GV5125207B', 2, 'executed', 'b9:bc:04:34:55:90:24:7c:fd:3d:03:80:e9:00:d4:86:40:3b:01:39:c8:d5:6a:fb', '2025-01-06 22:13:44'),
(260, 'GV5125207B', 2, 'executed', 'b9:bc:04:34:69:36:67:cf:e3:25:36:8b:7b:1b:36:55:30:d0:b7:69:b5:c6:05:a7', '2025-01-06 22:13:48'),
(261, 'GV5125207B', 4, 'executed', 'b9:bc:04:34:7e:da:44:9a:94:3f:b2:8e:de:76:6b:99:b5:52:06:bd:95:60:5f:49', '2025-01-06 22:13:54'),
(262, 'GV5125207B', 4, 'executed', 'b9:bc:04:34:8f:c4:f5:30:1d:ea:f9:06:d5:68:7b:69:30:04:e5:79:7f:43:27:7b', '2025-01-06 22:13:59'),
(263, 'GV5125207B', 4, 'executed', 'b9:bc:04:34:a3:ec:0e:68:67:1c:e8:63:d0:b6:e4:d8:32:41:78:a5:af:7a:ac:e1', '2025-01-06 22:14:04'),
(264, 'GV5125207B', 1, 'executed', 'b9:bc:04:34:dd:26:8c:5b:8d:e3:18:b5:b4:18:72:d5:66:8f:23:af:cd:13:eb:54', '2025-01-06 22:14:18'),
(265, 'GV5125207B', 3, 'executed', 'b9:bc:04:34:ec:1c:18:f9:1c:bd:49:5f:85:ac:f0:68:d2:bb:32:7f:3f:9a:10:7a', '2025-01-06 22:14:22'),
(266, 'GV5125207B', 2, 'executed', 'b9:bc:04:74:d8:68:b5:ca:bb:7e:06:28:01:14:42:d3:67:d2:9a:90:99:cb:20:63', '2025-01-06 23:24:10'),
(267, 'GV5125207B', 2, 'executed', 'b9:bc:04:74:ed:f8:1b:2a:67:31:0e:80:d5:14:a7:e0:d1:85:69:90:2d:5f:f6:70', '2025-01-06 23:24:16'),
(268, 'GV5125207B', 1, 'executed', 'b9:bc:04:75:18:0a:9d:c2:bc:61:c7:94:8c:3d:a6:b1:b1:23:14:9e:c6:fb:53:86', '2025-01-06 23:24:26'),
(269, 'GV5125207B', 3, 'executed', 'b9:bc:04:75:22:3c:66:2b:ca:54:73:97:82:5d:c2:c3:dd:6b:f2:37:25:68:31:4f', '2025-01-06 23:24:29'),
(270, 'GV5125207B', 4, 'executed', 'b9:bc:04:76:bd:18:59:57:b6:e6:5f:45:2e:b4:3c:e6:08:16:80:79:c1:67:6e:55', '2025-01-06 23:26:14'),
(271, 'GV5125207B', 4, 'executed', 'b9:bc:04:76:cb:64:af:92:df:ed:30:39:0d:2f:89:a2:2c:a2:b8:fa:c4:6f:a0:91', '2025-01-06 23:26:18'),
(272, 'GV5125207B', 4, 'executed', 'b9:bc:04:76:db:22:0b:6b:e3:00:3c:91:9e:5d:35:9a:c4:69:16:7a:cd:e5:75:48', '2025-01-06 23:26:22'),
(273, 'GV5125207B', 5, 'executed', 'b9:bc:04:77:06:ce:c7:42:3e:c1:ef:af:78:ad:56:b3:5b:18:e8:ce:6a:b0:05:91', '2025-01-06 23:26:33'),
(274, 'GV5125207B', 1, 'executed', 'b9:bc:04:77:15:d8:36:9c:fb:83:69:e5:6b:9f:21:88:fe:b1:f2:cb:f7:a5:a6:4d', '2025-01-06 23:26:37'),
(275, 'GV5125207B', 3, 'executed', 'b9:bc:04:77:1d:b2:69:44:d9:ec:92:8d:7d:f4:51:c4:61:3f:07:74:e8:66:0f:06', '2025-01-06 23:26:39'),
(276, 'GV5125207B', 1, 'executed', 'b9:bc:04:78:d8:d2:38:6b:0b:4c:ca:df:ff:a1:b4:9c:fc:0c:45:09:29:22:08:a2', '2025-01-06 23:28:32'),
(277, 'GV5125207B', 5, 'executed', 'b9:bc:04:7a:2c:3c:c4:2b:8d:de:cb:33:9a:cc:85:f7:42:b8:37:cb:33:38:02:fc', '2025-01-06 23:29:59'),
(278, 'GV5125207B', 1, 'executed', 'b9:bc:04:7a:91:fe:f9:51:91:f4:e7:c5:ac:82:82:01:2c:6d:8d:9d:6d:65:a9:9a', '2025-01-06 23:30:25'),
(279, 'GV5125207B', 4, 'executed', 'b9:bc:04:7a:9b:04:83:fb:76:2e:e1:1b:e5:69:68:c1:0f:e1:67:b6:4d:d5:71:bc', '2025-01-06 23:30:27'),
(280, 'GV5125207B', 4, 'executed', 'b9:bc:04:7a:a6:26:04:74:d1:6d:74:9b:60:f6:fb:a3:61:8c:71:07:8a:06:c0:7e', '2025-01-06 23:30:30'),
(281, 'GV5125207B', 5, 'executed', 'b9:bc:04:7a:ee:60:42:bc:99:82:57:c8:44:7d:9c:a5:0d:3e:f0:c2:cf:3b:9d:be', '2025-01-06 23:30:49'),
(282, 'GV5125207B', 6, 'executed', 'b9:bc:04:7a:ff:40:88:eb:50:71:82:57:5c:c4:c5:41:aa:e6:15:32:4a:fb:5c:ea', '2025-01-06 23:30:53'),
(283, 'GV5125207B', 6, 'executed', 'b9:bc:04:7b:08:c8:71:52:21:3e:65:1e:37:4c:c1:01:ab:f3:15:2a:d1:35:35:e6', '2025-01-06 23:30:55'),
(284, 'GV5122427B', 1, 'executed', '37:0c:01:00:67:2e:69:b8:15:ad:cf:29:a6:70:fd:cc:ef:24:8b:cc:cf:03:e2:d4', '2025-01-06 23:31:21'),
(285, 'GV5122427B', 1, 'executed', '37:0c:01:00:74:08:5d:87:54:7a:d2:ea:b2:fb:b8:86:dc:93:98:23:5b:d7:96:d7', '2025-01-06 23:31:24'),
(286, 'GV5125207B', 6, 'executed', 'b9:bc:04:7b:08:c8:71:52:21:3e:65:1e:37:4c:c1:01:ab:f3:15:2a:d1:35:35:e6', '2025-01-06 23:31:57'),
(287, 'GV5122427B', 1, 'executed', '37:0c:01:00:74:08:5d:87:54:7a:d2:ea:b2:fb:b8:86:dc:93:98:23:5b:d7:96:d7', '2025-01-06 23:32:25'),
(288, 'GV5122427B', 1, 'executed', '37:0c:01:04:29:d6:f3:0b:e9:86:ba:46:8f:6c:99:f5:9d:94:9b:39:3f:20:35:99', '2025-01-06 23:35:27'),
(289, 'GV51224B48', 1, 'executed', 'b5:c3:01:07:48:fa:e5:2b:67:e1:e6:7b:93:bb:b3:23:d9:2b:f8:fe:d6:51:90:5f', '2025-01-06 23:36:54'),
(290, 'GV51224B48', 1, 'executed', 'b5:c3:01:07:53:5e:b3:68:c3:26:7c:47:ea:42:a0:91:85:e0:3c:be:3b:af:4b:20', '2025-01-06 23:36:56'),
(291, 'GV5122427B', 1, 'executed', '37:0c:01:36:d9:d0:0b:f1:83:43:27:8c:6b:37:dc:82:d5:e2:af:a6:e4:1c:53:1d', '2025-01-07 00:30:49'),
(292, 'GV5122427B', 1, 'executed', '37:0c:01:36:e8:b2:49:7a:79:9c:4c:63:c9:0c:0a:48:27:f0:9a:89:f7:35:0f:63', '2025-01-07 00:30:53'),
(293, 'GV5122427B', 1, 'executed', '37:0c:01:72:d0:2a:d5:39:7f:78:05:9d:d1:8a:06:59:fa:c0:5a:1e:5f:7d:44:2b', '2025-01-07 01:36:19'),
(294, 'GV51224B48', 1, 'executed', 'b5:c3:01:74:c7:90:c2:ef:24:a3:be:b1:ad:77:35:5f:fb:6f:7d:80:f2:2f:a8:f0', '2025-01-07 01:36:29'),
(295, 'GV51224B48', 1, 'executed', 'b5:c3:01:74:fe:22:37:85:0d:e0:6e:73:0f:73:49:d7:d3:95:3f:e4:a7:fc:80:07', '2025-01-07 01:36:43'),
(296, 'GV5122427B', 1, 'executed', '37:0c:01:72:d0:2a:d5:39:7f:78:05:9d:d1:8a:06:59:fa:c0:5a:1e:5f:7d:44:2b', '2025-01-07 01:37:20'),
(297, 'GV51224B48', 1, 'executed', 'b5:c3:01:75:a1:b0:7b:49:8a:7e:23:0c:14:52:1d:45:df:d3:2c:53:3a:75:fe:88', '2025-01-07 01:37:25'),
(298, 'GV5122427B', 1, 'executed', '37:0c:01:7e:71:aa:94:81:09:81:a4:db:aa:ab:de:73:95:74:ae:d5:0e:ce:9a:3c', '2025-01-07 01:49:01'),
(299, 'GV5122427B', 1, 'executed', '37:0c:01:7f:66:50:79:d8:21:24:a6:67:91:89:e7:7e:61:7b:de:1c:af:45:65:54', '2025-01-07 01:50:03'),
(300, 'GV5122427B', 1, 'executed', '37:0c:01:81:2e:7c:73:1c:15:bf:d4:9b:5b:9b:d1:06:7b:89:31:b3:2f:2f:e5:ef', '2025-01-07 01:52:00'),
(301, 'GV5122427B', 1, 'executed', '37:0c:01:81:38:ae:b7:b5:0c:e0:b8:32:a7:99:e5:38:bb:c7:3b:19:a4:0c:1f:e6', '2025-01-07 01:52:03'),
(302, 'GV5122427B', 1, 'executed', '37:0c:01:81:75:d0:1a:46:45:ea:75:d4:51:61:1e:18:9b:74:18:fe:81:5f:87:7a', '2025-01-07 01:52:18'),
(303, 'GV5122427B', 1, 'executed', '37:0c:01:81:88:22:bd:ba:55:82:30:b4:f3:4d:74:43:58:48:e9:db:de:ae:09:ef', '2025-01-07 01:52:23'),
(304, 'GV5122427B', 1, 'executed', '37:0c:01:82:52:84:06:2d:83:01:c3:a6:51:1d:c6:bd:84:14:09:2a:ee:51:b6:cf', '2025-01-07 01:53:15'),
(305, 'GV5122427B', 1, 'executed', '37:0c:01:82:5f:0e:19:6b:56:92:d3:0b:6a:8f:0c:b8:37:3a:8c:2d:77:93:ad:80', '2025-01-07 01:53:18'),
(306, 'GV5122427B', 1, 'executed', '37:0c:01:83:45:a4:90:34:d1:51:4f:35:28:b3:a7:f8:4a:d1:a1:6c:51:d9:da:83', '2025-01-07 01:54:17'),
(307, 'GV5122427B', 1, 'executed', '37:0c:01:83:55:b2:8a:88:a4:e7:b2:eb:05:9e:f2:11:a5:94:c5:f3:c8:1c:fc:d3', '2025-01-07 01:54:21'),
(308, 'GV51224B48', 1, 'executed', 'b5:c3:03:3e:53:b6:4f:bf:0e:07:1d:b2:10:63:df:f8:25:ed:07:69:66:a9:19:c6', '2025-01-07 09:56:12'),
(309, 'GV5125615A', 6, 'executed', '43:49:02:46:13:68:d3:4f:3c:f1:31:c9:8a:f3:08:53:15:4d:2a:81:bb:13:f1:c0', '2025-01-07 10:10:31'),
(310, 'GV5125615A', 4, 'executed', '43:49:00:24:72:46:9d:2d:0d:b1:70:c8:8d:5c:1d:8b:8d:4e:c7:a9:6b:43:e1:23', '2025-01-07 10:10:31'),
(311, 'GV5125615A', 6, 'executed', '43:49:00:24:7d:a4:f1:90:89:ba:5b:85:b1:8a:60:5b:5e:68:d2:db:ae:43:0d:99', '2025-01-07 10:10:34'),
(312, 'GV5125615A', 5, 'executed', '43:49:00:24:8b:c8:ae:62:75:e1:04:d8:56:76:bc:14:5b:89:f2:c1:9d:a6:bf:c0', '2025-01-07 10:10:38'),
(313, 'GV5125207B', 1, 'executed', 'b9:bc:01:c5:04:da:cd:86:1f:04:9e:60:aa:fa:76:ee:b9:2a:93:0b:e9:88:03:ee', '2025-01-07 10:10:47'),
(314, 'GV5125207B', 6, 'executed', 'b9:bc:01:c5:10:ba:24:1a:35:fc:50:4d:c4:15:05:8c:23:85:b2:ca:83:4e:6b:bd', '2025-01-07 10:10:50'),
(315, 'GV5125207B', 6, 'executed', 'b9:bc:01:c5:24:38:1b:fc:26:b1:50:d1:d1:67:51:dc:1c:ce:88:40:d6:64:f9:f4', '2025-01-07 10:10:55'),
(316, 'GV5125207B', 6, 'executed', 'b9:bc:01:c5:2e:92:5d:25:38:f8:17:4f:27:bc:01:f3:3a:7a:19:e1:c7:5c:16:ab', '2025-01-07 10:10:57'),
(317, 'GV5125615A', 5, 'executed', '43:49:00:24:8b:c8:ae:62:75:e1:04:d8:56:76:bc:14:5b:89:f2:c1:9d:a6:bf:c0', '2025-01-07 10:11:39'),
(318, 'GV5125207B', 6, 'executed', 'b9:bc:01:c5:2e:92:5d:25:38:f8:17:4f:27:bc:01:f3:3a:7a:19:e1:c7:5c:16:ab', '2025-01-07 10:11:59'),
(319, 'GV5125207B', 5, 'executed', 'b9:bc:01:ee:27:f2:48:44:f6:e7:65:2d:87:25:fb:d9:12:a9:88:12:13:bc:fe:a5', '2025-01-07 10:55:42'),
(320, 'GV5125207B', 1, 'executed', 'b9:bc:01:ee:34:f4:7f:62:bb:aa:8e:75:b6:46:9e:c5:6d:03:56:d8:b7:f7:c3:99', '2025-01-07 10:55:45'),
(321, 'GV5122427B', 1, 'executed', '37:0c:03:a6:0a:b0:0d:a4:0c:10:31:e2:af:e9:6b:9a:45:d6:73:4b:b7:35:8f:d2', '2025-01-07 11:51:28'),
(322, 'GV5125207B', 5, 'executed', 'b9:bc:02:8b:3d:c6:2b:0c:11:7d:e6:7b:bf:ff:81:d8:5b:14:08:3e:84:63:b7:31', '2025-01-07 13:47:13'),
(323, 'GV5125207B', 5, 'executed', 'b9:bc:02:8b:49:6a:e7:84:98:aa:3f:19:82:d6:55:87:53:3f:eb:d9:e5:64:b1:b4', '2025-01-07 13:47:16'),
(324, 'GV5122427B', 1, 'executed', '37:0c:00:07:a8:64:23:e9:df:5e:ab:c9:a9:80:2e:58:ae:2b:f4:25:ea:6e:11:59', '2025-01-07 18:17:40'),
(325, 'GV5125207B', 5, 'executed', 'b9:bc:02:8b:49:6a:e7:84:98:aa:3f:19:82:d6:55:87:53:3f:eb:d9:e5:64:b1:b4', '2025-01-07 22:12:03'),
(326, 'GV5125207B', 1, 'executed', 'b9:bc:04:59:8e:82:4b:a0:a9:39:c6:6e:9c:b9:ff:eb:70:b0:b5:10:f6:00:12:18', '2025-01-07 22:12:03'),
(327, 'GV5125207B', 5, 'executed', 'b9:bc:04:59:99:b8:25:e4:b0:06:23:6b:9c:14:90:a9:80:56:d6:8b:dd:07:a2:3c', '2025-01-07 22:12:05'),
(328, 'GV5125615A', 5, 'executed', '43:49:00:24:8b:c8:ae:62:75:e1:04:d8:56:76:bc:14:5b:89:f2:c1:9d:a6:bf:c0', '2025-01-07 22:12:08'),
(329, 'GV5125615A', 1, 'executed', '43:49:02:b9:38:98:45:b2:36:43:22:63:9c:d9:50:b4:53:ac:be:25:84:e3:35:c1', '2025-01-07 22:12:09'),
(330, 'GV5125615A', 5, 'executed', '43:49:02:b9:45:c2:b0:b0:76:0a:b1:45:af:85:96:bb:39:4f:23:f1:21:37:ca:f4', '2025-01-07 22:12:12'),
(331, 'GV5125615A', 1, 'executed', '43:49:02:b9:64:8a:d2:19:75:c4:9e:19:bb:d6:60:3e:45:85:68:95:b0:ae:4b:4d', '2025-01-07 22:12:20'),
(332, 'GV5125207B', 5, 'executed', 'b9:bc:04:59:99:b8:25:e4:b0:06:23:6b:9c:14:90:a9:80:56:d6:8b:dd:07:a2:3c', '2025-01-07 22:13:07'),
(333, 'GV5122427B', 1, 'executed', '37:0c:00:df:6e:2a:44:8c:a9:43:30:9a:51:5b:5f:dc:d4:20:cb:44:88:8f:5d:f7', '2025-01-07 22:13:20'),
(334, 'GV5125615A', 1, 'executed', '43:49:02:b9:64:8a:d2:19:75:c4:9e:19:bb:d6:60:3e:45:85:68:95:b0:ae:4b:4d', '2025-01-07 22:13:21'),
(335, 'GV5122427B', 1, 'executed', '37:0c:00:df:6e:2a:44:8c:a9:43:30:9a:51:5b:5f:dc:d4:20:cb:44:88:8f:5d:f7', '2025-01-07 22:13:21'),
(336, 'GV5122427B', 1, 'executed', '37:0c:00:df:81:9e:1d:b9:7b:7b:20:16:7e:b3:a2:08:76:89:51:de:e0:7a:c2:f5', '2025-01-07 22:13:25'),
(337, 'GV5125615A', 2, 'executed', '43:49:02:ba:fa:0c:c0:8d:ab:1f:86:cc:97:5f:3f:7c:fe:0e:3a:c2:9f:f9:76:e3', '2025-01-07 22:14:04'),
(338, 'GV5125615A', 6, 'executed', '43:49:02:bb:06:96:cb:dd:3d:90:d2:63:ba:2a:68:88:5f:be:0b:a8:7c:39:d5:7c', '2025-01-07 22:14:07'),
(339, 'GV5125615A', 3, 'executed', '43:49:02:bb:16:7c:3b:73:73:2a:61:e8:93:76:6a:f3:8b:04:2b:49:69:b8:9f:69', '2025-01-07 22:14:11'),
(340, 'GV5125615A', 1, 'executed', '43:49:02:bb:2e:96:82:41:7b:ba:ff:71:1e:06:8b:6a:2d:41:9b:e9:de:d6:e9:91', '2025-01-07 22:14:17'),
(341, 'GV5122427B', 1, 'executed', '37:0c:00:df:81:9e:1d:b9:7b:7b:20:16:7e:b3:a2:08:76:89:51:de:e0:7a:c2:f5', '2025-01-07 22:14:26'),
(342, 'GV5125615A', 1, 'executed', '43:49:02:bb:2e:96:82:41:7b:ba:ff:71:1e:06:8b:6a:2d:41:9b:e9:de:d6:e9:91', '2025-01-07 22:15:18'),
(343, 'GV5125207B', 1, 'executed', 'b9:bc:04:68:84:e6:71:03:51:eb:ac:39:d8:15:16:ee:9c:85:ae:c8:0d:fa:e0:c9', '2025-01-07 22:28:23'),
(344, 'GV5125207B', 1, 'executed', 'b9:bc:04:68:98:a0:fd:04:dd:ca:2a:54:a4:e8:72:d0:cf:ff:d9:2f:59:59:43:51', '2025-01-07 22:28:28'),
(345, 'GV5125207B', 5, 'executed', 'b9:bc:04:68:f2:e6:b9:0b:ef:fb:d3:8b:07:89:d5:b3:85:a9:82:bb:cc:68:89:0c', '2025-01-07 22:28:51'),
(346, 'GV5125207B', 3, 'executed', 'b9:bc:04:69:25:9a:f7:57:08:3c:de:35:c7:ca:e5:f1:03:8f:1a:00:cb:9c:70:4e', '2025-01-07 22:29:04'),
(347, 'GV5125207B', 5, 'executed', 'b9:bc:04:69:3f:8a:56:64:57:4d:0f:af:d0:2e:63:dc:f2:f3:7e:68:bd:91:53:38', '2025-01-07 22:29:11'),
(348, 'GV5125207B', 1, 'executed', 'b9:bc:04:69:4c:f0:9e:31:28:5a:66:dd:e9:2a:0b:7e:d1:20:0c:48:91:b1:5e:7d', '2025-01-07 22:29:14'),
(349, 'GV5125207B', 5, 'executed', 'b9:bc:04:69:5c:d6:7d:4e:c5:ac:ec:00:69:45:98:5d:fb:e7:da:aa:1e:47:19:dc', '2025-01-07 22:29:18'),
(350, 'GV5125207B', 3, 'executed', 'b9:bc:04:69:75:86:7e:48:25:20:5c:6d:49:bd:e9:92:a5:78:dc:9b:c9:4c:1f:f6', '2025-01-07 22:29:24'),
(351, 'GV5125207B', 1, 'executed', 'b9:bc:04:69:82:ce:49:4f:97:2b:ac:85:78:10:79:8e:8e:ab:6b:ac:d8:d1:33:82', '2025-01-07 22:29:28'),
(352, 'GV5125207B', 3, 'executed', 'b9:bc:04:69:8f:c6:3c:0f:81:0f:84:df:2f:49:2a:77:c8:3b:25:8f:a4:a4:d9:26', '2025-01-07 22:29:31'),
(353, 'GV5125207B', 1, 'executed', 'b9:bc:04:73:9e:6c:f5:78:bf:11:2f:b8:07:42:ec:49:56:27:0d:5a:ff:7f:93:e5', '2025-01-07 22:40:30'),
(354, 'GV5125207B', 1, 'executed', 'b9:bc:04:73:ae:8e:71:8f:e7:57:12:3f:8b:b0:c5:3a:47:ac:69:71:3f:a6:66:fb', '2025-01-07 22:40:34'),
(355, 'GV5125207B', 5, 'executed', 'b9:bc:04:74:0d:5c:33:f0:33:f6:8d:1a:80:dc:9d:30:89:e3:7f:ad:23:ee:42:fb', '2025-01-07 22:40:59'),
(356, 'GV5125207B', 1, 'executed', 'b9:bc:04:74:1a:c2:26:39:36:b4:5b:72:07:20:5f:e6:30:8b:ee:3e:7b:6c:dd:f3', '2025-01-07 22:41:02'),
(357, 'GV5125207B', 4, 'executed', 'b9:bc:04:74:26:a2:b5:d5:3e:89:b8:09:82:cc:13:3c:e5:f6:97:b5:81:0a:77:28', '2025-01-07 22:41:05'),
(358, 'GV5125207B', 4, 'executed', 'b9:bc:04:74:3f:b6:4e:77:35:9a:90:7d:b5:db:02:cb:28:58:dd:cb:40:81:01:f4', '2025-01-07 22:41:11'),
(359, 'GV5125207B', 4, 'executed', 'b9:bc:04:74:76:48:13:b1:0d:ec:3c:6e:84:d4:ac:72:3b:55:4d:f5:1d:6f:8c:b7', '2025-01-07 22:41:25'),
(360, 'GV5125207B', 2, 'executed', 'b9:bc:04:74:7e:54:58:23:48:73:7f:32:30:2f:ba:cb:46:98:57:49:33:54:88:04', '2025-01-07 22:41:28'),
(361, 'GV5125207B', 2, 'executed', 'b9:bc:04:74:9f:ce:58:58:66:ba:b4:60:e4:e8:16:19:1e:a9:5c:20:14:ef:b0:27', '2025-01-07 22:41:36'),
(362, 'GV5125207B', 4, 'executed', 'b9:bc:04:74:a8:8e:d9:a9:79:9a:30:1e:b1:df:f6:d8:23:2d:9e:bb:ba:23:c9:d0', '2025-01-07 22:41:38'),
(363, 'GV5125207B', 4, 'executed', 'b9:bc:04:74:b9:dc:f3:f0:74:e6:6a:db:b0:09:dc:b6:a5:74:5b:1c:4f:64:68:ce', '2025-01-07 22:41:43'),
(364, 'GV5125207B', 6, 'executed', 'b9:bc:04:74:cd:64:c3:48:a9:d5:4c:15:93:84:20:95:e0:0d:f8:12:47:3b:22:9c', '2025-01-07 22:41:48'),
(365, 'GV5125207B', 6, 'executed', 'b9:bc:04:74:f2:94:d1:16:73:cd:5f:5c:88:04:45:a2:8e:ae:33:25:39:7f:e2:04', '2025-01-07 22:41:57'),
(366, 'GV5125207B', 6, 'executed', 'b9:bc:04:75:01:d0:28:b5:44:87:ca:04:15:26:49:20:73:4b:52:a5:c3:21:97:83', '2025-01-07 22:42:01'),
(367, 'GV5125615A', 1, 'executed', '43:49:02:e6:b3:d6:3c:d8:4e:69:e5:31:da:c1:fa:a7:b2:d0:0d:67:8f:fd:ac:06', '2025-01-07 23:01:49'),
(368, 'GV5125615A', 5, 'executed', '43:49:02:e6:c3:58:d6:30:e7:e5:c9:72:83:11:15:fc:49:45:08:2a:29:8d:5b:93', '2025-01-07 23:01:53'),
(369, 'GV5125207B', 1, 'executed', 'b9:bc:04:89:3c:04:b2:07:d7:c4:9c:47:52:6d:22:90:d2:45:d5:92:97:89:be:67', '2025-01-07 23:04:07'),
(370, 'GV5125615A', 3, 'executed', '43:49:02:e9:08:7a:1f:73:65:d5:d1:7b:b3:a0:ab:93:1c:42:47:aa:0d:2e:e8:6d', '2025-01-07 23:04:22'),
(371, 'GV5125207B', 1, 'executed', 'b9:bc:04:89:3c:04:b2:07:d7:c4:9c:47:52:6d:22:90:d2:45:d5:92:97:89:be:67', '2025-01-07 23:05:08'),
(372, 'GV5125615A', 3, 'executed', '43:49:02:e9:08:7a:1f:73:65:d5:d1:7b:b3:a0:ab:93:1c:42:47:aa:0d:2e:e8:6d', '2025-01-07 23:05:23'),
(373, 'GV5125207B', 1, 'executed', 'b9:bc:04:92:b9:8c:cb:b7:08:4e:1c:fe:45:d2:79:11:2a:1f:b2:8f:3f:cb:b5:b5', '2025-01-07 23:14:28'),
(374, 'GV5125207B', 3, 'executed', 'b9:bc:04:92:c5:3a:3f:74:e4:3f:37:2c:8e:7a:94:62:fe:a5:b8:a1:34:8f:1f:21', '2025-01-07 23:14:31'),
(375, 'GV5125207B', 5, 'executed', 'b9:bc:04:92:cd:14:22:a6:44:05:03:db:26:69:9e:9b:57:7f:07:26:29:f7:f2:63', '2025-01-07 23:14:33'),
(376, 'GV5125207B', 1, 'executed', 'b9:bc:04:92:e4:d4:c5:88:15:64:b5:60:9b:69:44:29:46:03:0c:83:ba:23:77:ae', '2025-01-07 23:14:39'),
(377, 'GV5125207B', 2, 'executed', 'b9:bc:04:92:ed:a8:13:d6:ee:4d:cf:78:5b:38:3d:6e:17:9a:0d:92:5a:7c:97:c0', '2025-01-07 23:14:42'),
(378, 'GV5125207B', 2, 'executed', 'b9:bc:04:93:05:04:98:c7:25:8a:fe:bd:43:dc:cb:c2:fc:b1:99:5e:62:36:6e:96', '2025-01-07 23:14:48');
INSERT INTO `remote_buttons` (`id`, `remote_name`, `button_number`, `status`, `raw_data`, `timestamp`) VALUES
(379, 'GV5125207B', 4, 'executed', 'b9:bc:04:93:14:7c:56:a6:84:bc:8a:c2:8c:9d:75:9c:1d:22:69:40:7b:ba:3f:84', '2025-01-07 23:14:52'),
(380, 'GV5125207B', 4, 'executed', 'b9:bc:04:93:1c:b0:f9:d9:0e:b3:dd:ef:e3:eb:df:c0:4c:fc:83:fc:c9:8a:e1:9b', '2025-01-07 23:14:54'),
(381, 'GV5125207B', 4, 'executed', 'b9:bc:04:93:29:76:70:40:9d:73:72:1a:f9:af:1b:94:33:fe:19:0f:13:0e:4b:22', '2025-01-07 23:14:57'),
(382, 'GV5125207B', 6, 'executed', 'b9:bc:04:93:35:74:9b:84:3a:0b:ad:04:08:ef:0a:07:3d:65:d8:db:69:0a:b4:30', '2025-01-07 23:15:00'),
(383, 'GV5125207B', 6, 'executed', 'b9:bc:04:93:40:8c:14:6d:45:0a:10:55:63:32:f9:15:32:a4:ef:86:3a:dc:6c:2e', '2025-01-07 23:15:03'),
(384, 'GV5125207B', 6, 'executed', 'b9:bc:04:93:4a:5a:9f:57:e1:90:22:67:c7:2f:e2:1b:fb:40:c7:d6:60:44:de:eb', '2025-01-07 23:15:05'),
(385, 'GV5125207B', 6, 'executed', 'b9:bc:04:b1:66:c0:2d:78:16:5c:21:b5:40:3d:61:2d:b5:48:a0:cd:13:28:b9:e5', '2025-01-07 23:47:58'),
(386, 'GV5125207B', 2, 'executed', 'b9:bc:04:b1:70:70:bf:73:ac:f6:87:c9:57:84:fd:22:c5:71:ac:df:a6:0f:f5:61', '2025-01-07 23:48:01'),
(387, 'GV5125207B', 2, 'executed', 'b9:bc:04:b1:81:be:cc:80:49:77:9c:c0:c1:6e:33:9f:ad:2e:66:20:4e:03:36:55', '2025-01-07 23:48:05'),
(388, 'GV5125207B', 1, 'executed', 'b9:bc:04:b2:73:8a:5e:93:41:90:30:8f:0e:6e:db:67:50:14:c9:fc:6a:84:8c:de', '2025-01-07 23:49:07'),
(389, 'GV5125207B', 5, 'executed', 'b9:bc:04:b2:7b:64:fc:c1:0d:88:fa:3e:d4:f0:c8:2c:2b:23:49:d9:65:75:97:5a', '2025-01-07 23:49:09'),
(390, 'GV5125207B', 4, 'executed', 'b9:bc:04:b2:85:8c:97:1f:6a:59:fa:98:89:9a:7c:d0:8a:f0:b0:37:18:93:34:72', '2025-01-07 23:49:12'),
(391, 'GV5125207B', 4, 'executed', 'b9:bc:04:b2:94:78:a1:77:24:e6:71:8e:45:34:d9:5d:e7:ea:5a:f6:9a:12:52:fa', '2025-01-07 23:49:16'),
(392, 'GV5125207B', 2, 'executed', 'b9:bc:04:b2:a0:d0:1a:a4:a2:25:0b:bc:49:bb:cd:c4:f0:e8:49:04:15:2e:8d:5a', '2025-01-07 23:49:19'),
(393, 'GV5125615A', 3, 'executed', '43:49:03:19:ca:64:3f:1f:fd:12:2c:39:fe:02:c7:47:f2:ff:56:b2:9c:22:74:80', '2025-01-07 23:57:36'),
(394, 'GV5125615A', 1, 'executed', '43:49:03:19:d3:a6:00:fd:c5:fd:cf:ab:72:12:45:b5:dd:92:7e:f7:20:ec:97:c6', '2025-01-07 23:57:39'),
(395, 'GV5125615A', 1, 'executed', '43:49:03:19:de:82:b2:20:ee:cc:20:39:c6:9d:2a:85:9c:bc:26:e8:47:65:40:f5', '2025-01-07 23:57:42'),
(396, 'GV5125615A', 3, 'executed', '43:49:03:19:e6:ac:1e:7c:25:eb:54:df:1c:8f:36:19:a8:45:e0:01:9b:a0:89:7c', '2025-01-07 23:57:44'),
(397, 'GV5125615A', 1, 'executed', '43:49:03:19:f6:06:ee:0b:f5:65:e8:c7:b6:9b:b8:74:0a:2a:3a:33:f0:a5:36:3e', '2025-01-07 23:57:48'),
(398, 'GV5125615A', 5, 'executed', '43:49:03:19:fe:c6:93:79:6f:69:7a:6b:5b:95:bb:a1:b9:59:be:cf:a7:ff:14:46', '2025-01-07 23:57:50'),
(399, 'GV5125615A', 1, 'executed', '43:49:03:1a:0d:e4:67:78:6f:1d:12:08:03:be:9e:6b:70:60:b8:2b:85:f2:80:9f', '2025-01-07 23:57:54');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_name` text NOT NULL,
  `tab_order` int(2) NOT NULL DEFAULT 100,
  `icon` text NOT NULL DEFAULT 'fa-house',
  `devices` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`devices`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_name`, `tab_order`, `icon`, `devices`) VALUES
(1, 'Unassigned', 100, '', '[\"06:61:D0:C9:07:C8:DC:D8\", \"bab38ad6-9d55-47f7-8094-40f2c6282978\", \"d6831fe8-fe58-4321-8029-2d739f62a055\", \"e5c95310-d979-4484-a524-b7e424e17f88\", \"682a0c16-6d63-4877-9c02-f1711691c488\", \"D1:83:C7:6B:02:46:22:30\", \"58:A1:D0:C9:07:D5:74:2C\", \"3F:6C:D0:C9:07:DD:20:58\", \"bf537c22-c32c-4907-b7c7-a5974c2d90d6\"]'),
(2, 'Living Room', 2, 'fa-couch', '[\"1C:05:D4:0F:41:86:6B:62\", \"4C:58:D0:C9:07:C9:4C:16\"]'),
(3, 'Bedroom', 4, 'fa-bed', '[\"07:A1:D0:C9:07:C9:50:D4\", \"12:3D:D0:C9:07:C8:DD:FA\", \"06:25:D0:C9:07:C8:6D:2C\", \"vsaq816c68944907b8941a281dbad898\"]'),
(5, 'Office', 3, 'fa-computer', '[\"2C:4F:D0:C9:07:C9:4D:E8\", \"0F:A7:D0:C9:07:C9:26:88\", \"8feb66bd-153c-4a0d-97ef-b0c60e26ffb5\"]'),
(15, 'Favorites', 1, 'fa-star', '[\"4C:58:D0:C9:07:C9:4C:16\"]');

-- --------------------------------------------------------

--
-- Table structure for table `thermometers`
--

CREATE TABLE `thermometers` (
  `mac` varchar(17) NOT NULL,
  `model` text NOT NULL,
  `name` text NOT NULL,
  `display_name` text DEFAULT NULL,
  `room` text DEFAULT NULL,
  `number` int(2) DEFAULT NULL,
  `rssi` int(2) NOT NULL,
  `temp` int(11) DEFAULT NULL,
  `humidity` int(11) DEFAULT NULL,
  `battery` int(3) NOT NULL,
  `added` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `thermometers`
--

INSERT INTO `thermometers` (`mac`, `model`, `name`, `display_name`, `room`, `number`, `rssi`, `temp`, `humidity`, `battery`, `added`, `updated`) VALUES
('A4:C1:38:3A:F5:5C', 'GVH5075', 'GVH5075_F55C', 'Office', '5', 4, -66, 73, 41, 100, '2024-12-28 09:43:26', '2025-01-08 05:30:12'),
('A4:C1:38:73:96:B4', 'GVH5075', 'GVH5075_96B4', 'Living Room', '2', 5, -68, 70, 44, 100, '2024-12-28 09:43:23', '2025-01-08 05:30:01'),
('A4:C1:38:C5:37:D8', 'GVH5075', 'GVH5075_37D8', 'Entryway', NULL, 2, -92, 74, 34, 100, '2025-01-03 23:40:18', '2025-01-08 05:30:33'),
('A4:C1:38:EC:C2:B8', 'GVH5075', 'GVH5075_C2B8', 'Basement', NULL, 1, -64, 69, 45, 100, '2025-01-03 23:40:21', '2025-01-08 05:30:04'),
('A4:C1:38:F6:CC:80', 'GVH5075', 'GVH5075_CC80', 'Bedroom', '3', 6, -82, 68, 58, 100, '2024-12-28 09:43:58', '2025-01-08 05:30:06'),
('CA:5F:C2:86:1E:6E', 'GVH5100', 'GVH5100_1E6E', 'Garage', NULL, NULL, -84, 57, 36, 100, '2025-01-05 06:55:27', '2025-01-08 05:30:05'),
('CA:5F:C3:86:41:5C', 'GVH5100', 'GVH5100_415C', 'Outside 2', '15', NULL, -62, 28, 62, 100, '2025-01-05 06:55:00', '2025-01-08 05:30:02'),
('D0:05:85:46:3D:7C', 'GVH5100', 'GVH5100_3D7C', '3', NULL, NULL, -58, 72, 42, 100, '2025-01-05 06:55:36', '2025-01-08 05:25:02');

-- --------------------------------------------------------

--
-- Table structure for table `thermometer_history`
--

CREATE TABLE `thermometer_history` (
  `id` int(11) NOT NULL,
  `mac` varchar(17) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `rssi` int(11) DEFAULT NULL,
  `temperature` int(11) DEFAULT NULL,
  `humidity` int(11) DEFAULT NULL,
  `battery` int(11) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Indexes for table `remote_buttons`
--
ALTER TABLE `remote_buttons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_remote_timestamp` (`remote_name`,`timestamp`);

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
-- Indexes for table `thermometer_history`
--
ALTER TABLE `thermometer_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mac_timestamp` (`mac`,`timestamp`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `govee_api_calls`
--
ALTER TABLE `govee_api_calls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `remote_buttons`
--
ALTER TABLE `remote_buttons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=400;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `thermometer_history`
--
ALTER TABLE `thermometer_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
