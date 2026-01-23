-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 23, 2026 at 12:18 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dkuscheduler1`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `department_id` int(11) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `external_link` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `title`, `message`, `created_by`, `created_at`, `department_id`, `attachment`, `external_link`, `updated_at`) VALUES
(2, '1st Announcement', 'this is just a test drive !', 4, '2025-10-07 09:13:31', 1, '1759828411_Chris Brown - Under The Influence (Official Video).mp4', 'http://localhost/phpmyadmin/index.php?route=/table/sql&db=dkuscheduler1&table=announcements', '2025-10-08 06:00:58'),
(3, '2nd Announcement', 'be ready for the exam', 4, '2025-10-14 12:58:27', 1, '1760446707_Ethiopian Music with Lyrics - Abdu Kiar - Yichalal - አብዱ ኪያር - ይቻላል - ከግጥም ጋር.mp4', '', NULL),
(4, 'NEW ONE', 'fgiagfihfbgdjugh', 4, '2025-12-13 07:55:27', 1, '1765612527_pro.docx', '', NULL),
(5, 'admins announcement', 'just a test drive', 1, '2025-12-15 07:30:34', NULL, 'announcements/1765783834_693fb91a6e83f.docx', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `announcement_comments`
--

CREATE TABLE `announcement_comments` (
  `comment_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcement_comments`
--

INSERT INTO `announcement_comments` (`comment_id`, `announcement_id`, `user_id`, `comment`, `created_at`) VALUES
(1, 2, 10, 'nice one', '2025-10-07 10:20:39'),
(3, 2, 15, 'wow', '2025-10-08 05:59:20'),
(4, 3, 10, 'okay', '2025-10-14 12:59:58'),
(5, 3, 15, 'gech shut up', '2025-12-31 13:46:08'),
(6, 5, 47, 'nice one', '2025-12-31 14:41:08'),
(7, 4, 47, 'nice one', '2026-01-01 17:54:33'),
(8, 5, 51, 'wow', '2026-01-10 17:09:00');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_likes`
--

CREATE TABLE `announcement_likes` (
  `like_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcement_likes`
--

INSERT INTO `announcement_likes` (`like_id`, `announcement_id`, `user_id`) VALUES
(2, 2, 4),
(15, 2, 10),
(3, 2, 15),
(4, 3, 10),
(7, 3, 15),
(12, 3, 47),
(18, 4, 10),
(9, 4, 15),
(13, 4, 47),
(14, 5, 10),
(16, 5, 12),
(8, 5, 15),
(10, 5, 47),
(17, 5, 51);

-- --------------------------------------------------------

--
-- Table structure for table `announcement_reactions`
--

CREATE TABLE `announcement_reactions` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reaction_type` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcement_reactions`
--

INSERT INTO `announcement_reactions` (`id`, `announcement_id`, `user_id`, `reaction_type`, `created_at`) VALUES
(2, 2, 10, 'like', '2025-10-07 10:20:30'),
(3, 2, 15, 'like', '2025-10-07 10:35:58');

-- --------------------------------------------------------

--
-- Table structure for table `colleges`
--

CREATE TABLE `colleges` (
  `college_id` int(11) NOT NULL,
  `college_name` varchar(100) NOT NULL,
  `category` enum('Natural','Social') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `colleges`
--

INSERT INTO `colleges` (`college_id`, `college_name`, `category`, `created_at`) VALUES
(1, 'College of Natural and Computational Sciences', 'Natural', '2026-01-23 08:27:33'),
(2, 'College of Agriculture and Environmental Sciences', 'Natural', '2026-01-23 08:27:33'),
(3, 'College of Health Sciences', 'Natural', '2026-01-23 08:27:33'),
(4, 'College of Engineering and Technology', 'Natural', '2026-01-23 08:27:33'),
(5, 'College of Social Sciences and Humanities', 'Social', '2026-01-23 08:27:33'),
(6, 'College of Business and Economics', 'Social', '2026-01-23 08:27:33'),
(7, 'College of Law and Governance', 'Social', '2026-01-23 08:27:33'),
(8, 'College of Education and Behavioral Sciences', 'Social', '2026-01-23 08:27:33');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `credit_hours` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `prerequisite` varchar(255) DEFAULT NULL,
  `category` enum('Compulsory','Elective','Optional') DEFAULT 'Compulsory',
  `contact_hours` int(11) NOT NULL DEFAULT 3,
  `lab_hours` int(11) NOT NULL DEFAULT 0,
  `tutorial_hours` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_freshman` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_code`, `course_name`, `credit_hours`, `department_id`, `created_at`, `prerequisite`, `category`, `contact_hours`, `lab_hours`, `tutorial_hours`, `description`, `is_freshman`) VALUES
(13, 'hi07', 'history', 0, 6, '2025-10-14 12:44:58', NULL, 'Compulsory', 3, 0, 0, NULL, 0),
(14, 'ph21', 'physics', 0, 9, '2025-10-14 12:45:07', NULL, 'Compulsory', 3, 0, 0, NULL, 0),
(18, 'cs101', 'Compiler Design', 5, 1, '2025-11-12 11:07:48', 'Computer organization and architecture', 'Compulsory', 3, 2, 0, '', 0),
(19, 'cs102', 'Computer programming', 5, 1, '2025-11-12 11:09:59', 'none', 'Compulsory', 3, 2, 0, '', 0),
(20, 'cs103', 'web programming', 5, 1, '2025-11-12 11:10:35', 'none', 'Compulsory', 3, 2, 0, '', 0),
(21, 'cs104', 'software enginering', 5, 1, '2025-11-12 11:11:00', 'none', 'Compulsory', 3, 2, 0, '', 0),
(22, 'CS105', 'Object oriented programming', 3, 1, '2025-11-12 11:11:56', 'None', 'Compulsory', 2, 1, 0, '', 0),
(23, 'cs106', 'Image processing', 5, 1, '2025-11-12 11:12:21', 'none', 'Compulsory', 3, 2, 0, '', 0),
(24, 'cs123', 'Data Communication and Computer Networks', 5, 1, '2025-12-01 08:44:08', '', 'Compulsory', 3, 2, 0, '', 0),
(25, 'BI12', 'Introduction to Biology', 5, 7, '2025-12-13 07:21:22', '', 'Compulsory', 3, 2, 0, '', 0),
(26, 'BI23', 'Anatomy', 5, 7, '2025-12-13 07:22:11', '', 'Compulsory', 3, 2, 0, '', 0),
(27, 'BI34', 'Biochemistry', 5, 7, '2025-12-13 07:22:33', '', 'Compulsory', 3, 2, 0, '', 0),
(28, 'BI56', 'Molecular Biology', 3, 7, '2025-12-13 07:22:54', '', 'Compulsory', 3, 0, 0, '', 0),
(29, 'BI67', 'Evolutionary Biology', 5, 7, '2025-12-13 07:23:22', '', 'Compulsory', 3, 2, 0, '', 0),
(30, 'BI65', 'Food Science', 3, 7, '2025-12-13 07:23:51', '', 'Compulsory', 3, 0, 0, '', 0),
(31, 'PH217', 'physics', 5, 2, '2025-12-19 13:27:15', '', 'Compulsory', 3, 2, 0, '', 0),
(32, 'PH101', 'Introduction to physics', 5, NULL, '2026-01-05 17:28:29', '', 'Compulsory', 3, 2, 0, '', 1),
(33, 'CH101', 'Introduction to chemistry', 5, NULL, '2026-01-05 17:29:11', '', 'Compulsory', 3, 2, 0, '', 1),
(34, 'LO101', 'Logic', 3, NULL, '2026-01-05 17:29:47', '', 'Compulsory', 3, 0, 0, '', 1),
(35, 'MA101', 'Introduction to mathematics', 5, NULL, '2026-01-05 17:30:15', '', 'Compulsory', 3, 2, 0, '', 1),
(36, 'GE101', 'Geography', 3, NULL, '2026-01-05 17:30:42', '', 'Compulsory', 3, 0, 0, '', 1),
(37, 'SOC123', 'Business Management', 5, 3, '2026-01-10 17:02:46', '', 'Compulsory', 3, 2, 0, '', 0);

-- --------------------------------------------------------

--
-- Table structure for table `course_assignments`
--

CREATE TABLE `course_assignments` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `academic_year` varchar(10) NOT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('assigned','completed','cancelled') DEFAULT 'assigned'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_assignments`
--

INSERT INTO `course_assignments` (`id`, `course_id`, `user_id`, `semester`, `academic_year`, `assigned_date`, `status`) VALUES
(23, 26, 33, 'Fall', '2025-2026', '2025-12-13 07:31:07', 'assigned'),
(24, 27, 29, 'Fall', '2025-2026', '2025-12-13 07:31:15', 'assigned'),
(25, 29, 30, 'Fall', '2025-2026', '2025-12-13 07:31:23', 'assigned'),
(26, 30, 32, 'Fall', '2025-2026', '2025-12-13 07:31:34', 'assigned'),
(27, 25, 31, 'Fall', '2025-2026', '2025-12-13 07:31:44', 'assigned'),
(44, 18, 14, '1st Semester', '2024-2025', '2026-01-05 21:10:59', 'assigned'),
(45, 19, 5, '1st Semester', '2024-2025', '2026-01-05 21:11:13', 'assigned'),
(46, 24, 12, '1st Semester', '2024-2025', '2026-01-05 21:11:26', 'assigned'),
(47, 23, 13, '1st Semester', '2024-2025', '2026-01-05 21:11:42', 'assigned'),
(48, 22, 11, '1st Semester', '2024-2025', '2026-01-05 21:11:51', 'assigned');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `department_code` varchar(10) DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `college_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`, `department_code`, `category`, `description`, `college_id`) VALUES
(1, 'Computer Science', 'CS', 'Natural', '', 1),
(2, 'Electrical Engineering', 'EG', 'Natural', '', NULL),
(3, 'Business Administration', 'BA', 'Social', '', NULL),
(4, 'Sociology', 'SOC', 'Social', '', NULL),
(5, 'Psychology', 'PSY', 'Social', '', NULL),
(6, 'History', 'HIST', 'Social', '', NULL),
(7, 'Biology', 'BIO', 'Natural', '', NULL),
(8, 'Chemistry', 'CHEM', 'Natural', '', NULL),
(9, 'Physics', 'PHY', 'Natural', '', NULL),
(12, 'Civics', 'CIV', 'Social', '', NULL),
(14, 'Nursing', 'NRS', 'Natural', '', NULL),
(16, 'agroeconomics', NULL, 'Natural', '', 2);

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `course_id` int(11) DEFAULT NULL,
  `year` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`enrollment_id`, `student_id`, `schedule_id`, `enrolled_at`, `course_id`, `year`) VALUES
(2027, 38, 1355, '2026-01-21 17:50:31', NULL, NULL),
(2028, 38, 1360, '2026-01-21 17:50:31', NULL, NULL),
(2029, 38, 1365, '2026-01-21 17:50:31', NULL, NULL),
(2030, 38, 1370, '2026-01-21 17:50:32', NULL, NULL),
(2031, 38, 1373, '2026-01-21 17:50:32', NULL, NULL),
(2032, 38, 1376, '2026-01-21 17:50:32', NULL, NULL),
(2033, 38, 1379, '2026-01-21 17:50:32', NULL, NULL),
(2034, 38, 1382, '2026-01-21 17:50:32', NULL, NULL),
(2035, 38, 1400, '2026-01-21 17:50:32', NULL, NULL),
(2036, 38, 1403, '2026-01-21 17:50:32', NULL, NULL),
(2037, 38, 1356, '2026-01-21 17:50:32', NULL, NULL),
(2038, 38, 1361, '2026-01-21 17:50:32', NULL, NULL),
(2039, 38, 1366, '2026-01-21 17:50:32', NULL, NULL),
(2040, 38, 1371, '2026-01-21 17:50:32', NULL, NULL),
(2041, 38, 1374, '2026-01-21 17:50:32', NULL, NULL),
(2042, 38, 1377, '2026-01-21 17:50:32', NULL, NULL),
(2043, 38, 1380, '2026-01-21 17:50:32', NULL, NULL),
(2044, 38, 1383, '2026-01-21 17:50:32', NULL, NULL),
(2045, 38, 1385, '2026-01-21 17:50:32', NULL, NULL),
(2046, 38, 1388, '2026-01-21 17:50:32', NULL, NULL),
(2047, 38, 1391, '2026-01-21 17:50:33', NULL, NULL),
(2048, 38, 1394, '2026-01-21 17:50:33', NULL, NULL),
(2049, 38, 1397, '2026-01-21 17:50:33', NULL, NULL),
(2050, 38, 1401, '2026-01-21 17:50:33', NULL, NULL),
(2051, 38, 1404, '2026-01-21 17:50:33', NULL, NULL),
(2052, 38, 1357, '2026-01-21 17:50:33', NULL, NULL),
(2053, 38, 1362, '2026-01-21 17:50:33', NULL, NULL),
(2054, 38, 1367, '2026-01-21 17:50:33', NULL, NULL),
(2055, 38, 1372, '2026-01-21 17:50:33', NULL, NULL),
(2056, 38, 1375, '2026-01-21 17:50:33', NULL, NULL),
(2057, 38, 1378, '2026-01-21 17:50:33', NULL, NULL),
(2058, 38, 1381, '2026-01-21 17:50:33', NULL, NULL),
(2059, 38, 1384, '2026-01-21 17:50:33', NULL, NULL),
(2060, 38, 1386, '2026-01-21 17:50:33', NULL, NULL),
(2061, 38, 1389, '2026-01-21 17:50:33', NULL, NULL),
(2062, 38, 1392, '2026-01-21 17:50:33', NULL, NULL),
(2063, 38, 1395, '2026-01-21 17:50:33', NULL, NULL),
(2064, 38, 1398, '2026-01-21 17:50:33', NULL, NULL),
(2065, 38, 1402, '2026-01-21 17:50:33', NULL, NULL),
(2066, 38, 1405, '2026-01-21 17:50:33', NULL, NULL),
(2067, 38, 1358, '2026-01-21 17:50:33', NULL, NULL),
(2068, 38, 1363, '2026-01-21 17:50:33', NULL, NULL),
(2069, 38, 1368, '2026-01-21 17:50:33', NULL, NULL),
(2070, 38, 1387, '2026-01-21 17:50:33', NULL, NULL),
(2071, 38, 1390, '2026-01-21 17:50:33', NULL, NULL),
(2072, 38, 1393, '2026-01-21 17:50:33', NULL, NULL),
(2073, 38, 1396, '2026-01-21 17:50:34', NULL, NULL),
(2074, 38, 1399, '2026-01-21 17:50:34', NULL, NULL),
(2075, 38, 1359, '2026-01-21 17:50:34', NULL, NULL),
(2076, 38, 1364, '2026-01-21 17:50:34', NULL, NULL),
(2077, 38, 1369, '2026-01-21 17:50:34', NULL, NULL),
(2078, 9, 1355, '2026-01-21 17:50:34', NULL, NULL),
(2079, 9, 1360, '2026-01-21 17:50:34', NULL, NULL),
(2080, 9, 1365, '2026-01-21 17:50:34', NULL, NULL),
(2081, 9, 1370, '2026-01-21 17:50:34', NULL, NULL),
(2082, 9, 1373, '2026-01-21 17:50:34', NULL, NULL),
(2083, 9, 1376, '2026-01-21 17:50:34', NULL, NULL),
(2084, 9, 1379, '2026-01-21 17:50:34', NULL, NULL),
(2085, 9, 1382, '2026-01-21 17:50:34', NULL, NULL),
(2086, 9, 1400, '2026-01-21 17:50:34', NULL, NULL),
(2087, 9, 1403, '2026-01-21 17:50:34', NULL, NULL),
(2088, 9, 1356, '2026-01-21 17:50:34', NULL, NULL),
(2089, 9, 1361, '2026-01-21 17:50:34', NULL, NULL),
(2090, 9, 1366, '2026-01-21 17:50:34', NULL, NULL),
(2091, 9, 1371, '2026-01-21 17:50:34', NULL, NULL),
(2092, 9, 1374, '2026-01-21 17:50:34', NULL, NULL),
(2093, 9, 1377, '2026-01-21 17:50:34', NULL, NULL),
(2094, 9, 1380, '2026-01-21 17:50:34', NULL, NULL),
(2095, 9, 1383, '2026-01-21 17:50:34', NULL, NULL),
(2096, 9, 1385, '2026-01-21 17:50:34', NULL, NULL),
(2097, 9, 1388, '2026-01-21 17:50:34', NULL, NULL),
(2098, 9, 1391, '2026-01-21 17:50:34', NULL, NULL),
(2099, 9, 1394, '2026-01-21 17:50:34', NULL, NULL),
(2100, 9, 1397, '2026-01-21 17:50:34', NULL, NULL),
(2101, 9, 1401, '2026-01-21 17:50:34', NULL, NULL),
(2102, 9, 1404, '2026-01-21 17:50:34', NULL, NULL),
(2103, 9, 1357, '2026-01-21 17:50:35', NULL, NULL),
(2104, 9, 1362, '2026-01-21 17:50:35', NULL, NULL),
(2105, 9, 1367, '2026-01-21 17:50:35', NULL, NULL),
(2106, 9, 1372, '2026-01-21 17:50:35', NULL, NULL),
(2107, 9, 1375, '2026-01-21 17:50:35', NULL, NULL),
(2108, 9, 1378, '2026-01-21 17:50:35', NULL, NULL),
(2109, 9, 1381, '2026-01-21 17:50:35', NULL, NULL),
(2110, 9, 1384, '2026-01-21 17:50:35', NULL, NULL),
(2111, 9, 1386, '2026-01-21 17:50:35', NULL, NULL),
(2112, 9, 1389, '2026-01-21 17:50:35', NULL, NULL),
(2113, 9, 1392, '2026-01-21 17:50:35', NULL, NULL),
(2114, 9, 1395, '2026-01-21 17:50:35', NULL, NULL),
(2115, 9, 1398, '2026-01-21 17:50:35', NULL, NULL),
(2116, 9, 1402, '2026-01-21 17:50:35', NULL, NULL),
(2117, 9, 1405, '2026-01-21 17:50:35', NULL, NULL),
(2118, 9, 1358, '2026-01-21 17:50:35', NULL, NULL),
(2119, 9, 1363, '2026-01-21 17:50:35', NULL, NULL),
(2120, 9, 1368, '2026-01-21 17:50:35', NULL, NULL),
(2121, 9, 1387, '2026-01-21 17:50:35', NULL, NULL),
(2122, 9, 1390, '2026-01-21 17:50:35', NULL, NULL),
(2123, 9, 1393, '2026-01-21 17:50:35', NULL, NULL),
(2124, 9, 1396, '2026-01-21 17:50:35', NULL, NULL),
(2125, 9, 1399, '2026-01-21 17:50:35', NULL, NULL),
(2126, 9, 1359, '2026-01-21 17:50:35', NULL, NULL),
(2127, 9, 1364, '2026-01-21 17:50:35', NULL, NULL),
(2128, 9, 1369, '2026-01-21 17:50:36', NULL, NULL),
(2129, 26, 1355, '2026-01-21 17:50:36', NULL, NULL),
(2130, 26, 1360, '2026-01-21 17:50:36', NULL, NULL),
(2131, 26, 1365, '2026-01-21 17:50:36', NULL, NULL),
(2132, 26, 1370, '2026-01-21 17:50:36', NULL, NULL),
(2133, 26, 1373, '2026-01-21 17:50:36', NULL, NULL),
(2134, 26, 1376, '2026-01-21 17:50:36', NULL, NULL),
(2135, 26, 1379, '2026-01-21 17:50:36', NULL, NULL),
(2136, 26, 1382, '2026-01-21 17:50:36', NULL, NULL),
(2137, 26, 1400, '2026-01-21 17:50:36', NULL, NULL),
(2138, 26, 1403, '2026-01-21 17:50:36', NULL, NULL),
(2139, 26, 1356, '2026-01-21 17:50:36', NULL, NULL),
(2140, 26, 1361, '2026-01-21 17:50:36', NULL, NULL),
(2141, 26, 1366, '2026-01-21 17:50:36', NULL, NULL),
(2142, 26, 1371, '2026-01-21 17:50:36', NULL, NULL),
(2143, 26, 1374, '2026-01-21 17:50:36', NULL, NULL),
(2144, 26, 1377, '2026-01-21 17:50:36', NULL, NULL),
(2145, 26, 1380, '2026-01-21 17:50:36', NULL, NULL),
(2146, 26, 1383, '2026-01-21 17:50:36', NULL, NULL),
(2147, 26, 1385, '2026-01-21 17:50:36', NULL, NULL),
(2148, 26, 1388, '2026-01-21 17:50:36', NULL, NULL),
(2149, 26, 1391, '2026-01-21 17:50:36', NULL, NULL),
(2150, 26, 1394, '2026-01-21 17:50:36', NULL, NULL),
(2151, 26, 1397, '2026-01-21 17:50:36', NULL, NULL),
(2152, 26, 1401, '2026-01-21 17:50:36', NULL, NULL),
(2153, 26, 1404, '2026-01-21 17:50:37', NULL, NULL),
(2154, 26, 1357, '2026-01-21 17:50:37', NULL, NULL),
(2155, 26, 1362, '2026-01-21 17:50:37', NULL, NULL),
(2156, 26, 1367, '2026-01-21 17:50:37', NULL, NULL),
(2157, 26, 1372, '2026-01-21 17:50:37', NULL, NULL),
(2158, 26, 1375, '2026-01-21 17:50:37', NULL, NULL),
(2159, 26, 1378, '2026-01-21 17:50:37', NULL, NULL),
(2160, 26, 1381, '2026-01-21 17:50:37', NULL, NULL),
(2161, 26, 1384, '2026-01-21 17:50:37', NULL, NULL),
(2162, 26, 1386, '2026-01-21 17:50:37', NULL, NULL),
(2163, 26, 1389, '2026-01-21 17:50:37', NULL, NULL),
(2164, 26, 1392, '2026-01-21 17:50:37', NULL, NULL),
(2165, 26, 1395, '2026-01-21 17:50:37', NULL, NULL),
(2166, 26, 1398, '2026-01-21 17:50:37', NULL, NULL),
(2167, 26, 1402, '2026-01-21 17:50:37', NULL, NULL),
(2168, 26, 1405, '2026-01-21 17:50:37', NULL, NULL),
(2169, 26, 1358, '2026-01-21 17:50:37', NULL, NULL),
(2170, 26, 1363, '2026-01-21 17:50:38', NULL, NULL),
(2171, 26, 1368, '2026-01-21 17:50:38', NULL, NULL),
(2172, 26, 1387, '2026-01-21 17:50:38', NULL, NULL),
(2173, 26, 1390, '2026-01-21 17:50:38', NULL, NULL),
(2174, 26, 1393, '2026-01-21 17:50:38', NULL, NULL),
(2175, 26, 1396, '2026-01-21 17:50:38', NULL, NULL),
(2176, 26, 1399, '2026-01-21 17:50:38', NULL, NULL),
(2177, 26, 1359, '2026-01-21 17:50:38', NULL, NULL),
(2178, 26, 1364, '2026-01-21 17:50:38', NULL, NULL),
(2179, 26, 1369, '2026-01-21 17:50:38', NULL, NULL),
(2180, 3, 1355, '2026-01-21 17:50:38', NULL, NULL),
(2181, 3, 1360, '2026-01-21 17:50:38', NULL, NULL),
(2182, 3, 1365, '2026-01-21 17:50:38', NULL, NULL),
(2183, 3, 1370, '2026-01-21 17:50:38', NULL, NULL),
(2184, 3, 1373, '2026-01-21 17:50:38', NULL, NULL),
(2185, 3, 1376, '2026-01-21 17:50:38', NULL, NULL),
(2186, 3, 1379, '2026-01-21 17:50:38', NULL, NULL),
(2187, 3, 1382, '2026-01-21 17:50:38', NULL, NULL),
(2188, 3, 1400, '2026-01-21 17:50:38', NULL, NULL),
(2189, 3, 1403, '2026-01-21 17:50:38', NULL, NULL),
(2190, 3, 1356, '2026-01-21 17:50:38', NULL, NULL),
(2191, 3, 1361, '2026-01-21 17:50:38', NULL, NULL),
(2192, 3, 1366, '2026-01-21 17:50:39', NULL, NULL),
(2193, 3, 1371, '2026-01-21 17:50:39', NULL, NULL),
(2194, 3, 1374, '2026-01-21 17:50:39', NULL, NULL),
(2195, 3, 1377, '2026-01-21 17:50:39', NULL, NULL),
(2196, 3, 1380, '2026-01-21 17:50:39', NULL, NULL),
(2197, 3, 1383, '2026-01-21 17:50:39', NULL, NULL),
(2198, 3, 1385, '2026-01-21 17:50:39', NULL, NULL),
(2199, 3, 1388, '2026-01-21 17:50:39', NULL, NULL),
(2200, 3, 1391, '2026-01-21 17:50:39', NULL, NULL),
(2201, 3, 1394, '2026-01-21 17:50:39', NULL, NULL),
(2202, 3, 1397, '2026-01-21 17:50:39', NULL, NULL),
(2203, 3, 1401, '2026-01-21 17:50:39', NULL, NULL),
(2204, 3, 1404, '2026-01-21 17:50:39', NULL, NULL),
(2205, 3, 1357, '2026-01-21 17:50:39', NULL, NULL),
(2206, 3, 1362, '2026-01-21 17:50:39', NULL, NULL),
(2207, 3, 1367, '2026-01-21 17:50:39', NULL, NULL),
(2208, 3, 1372, '2026-01-21 17:50:39', NULL, NULL),
(2209, 3, 1375, '2026-01-21 17:50:39', NULL, NULL),
(2210, 3, 1378, '2026-01-21 17:50:39', NULL, NULL),
(2211, 3, 1381, '2026-01-21 17:50:39', NULL, NULL),
(2212, 3, 1384, '2026-01-21 17:50:39', NULL, NULL),
(2213, 3, 1386, '2026-01-21 17:50:40', NULL, NULL),
(2214, 3, 1389, '2026-01-21 17:50:40', NULL, NULL),
(2215, 3, 1392, '2026-01-21 17:50:40', NULL, NULL),
(2216, 3, 1395, '2026-01-21 17:50:40', NULL, NULL),
(2217, 3, 1398, '2026-01-21 17:50:40', NULL, NULL),
(2218, 3, 1402, '2026-01-21 17:50:40', NULL, NULL),
(2219, 3, 1405, '2026-01-21 17:50:40', NULL, NULL),
(2220, 3, 1358, '2026-01-21 17:50:40', NULL, NULL),
(2221, 3, 1363, '2026-01-21 17:50:40', NULL, NULL),
(2222, 3, 1368, '2026-01-21 17:50:40', NULL, NULL),
(2223, 3, 1387, '2026-01-21 17:50:40', NULL, NULL),
(2224, 3, 1390, '2026-01-21 17:50:40', NULL, NULL),
(2225, 3, 1393, '2026-01-21 17:50:40', NULL, NULL),
(2226, 3, 1396, '2026-01-21 17:50:40', NULL, NULL),
(2227, 3, 1399, '2026-01-21 17:50:40', NULL, NULL),
(2228, 3, 1359, '2026-01-21 17:50:40', NULL, NULL),
(2229, 3, 1364, '2026-01-21 17:50:40', NULL, NULL),
(2230, 3, 1369, '2026-01-21 17:50:40', NULL, NULL),
(2231, 6, 1355, '2026-01-21 17:50:40', NULL, NULL),
(2232, 6, 1360, '2026-01-21 17:50:40', NULL, NULL),
(2233, 6, 1365, '2026-01-21 17:50:40', NULL, NULL),
(2234, 6, 1370, '2026-01-21 17:50:40', NULL, NULL),
(2235, 6, 1373, '2026-01-21 17:50:41', NULL, NULL),
(2236, 6, 1376, '2026-01-21 17:50:41', NULL, NULL),
(2237, 6, 1379, '2026-01-21 17:50:41', NULL, NULL),
(2238, 6, 1382, '2026-01-21 17:50:41', NULL, NULL),
(2239, 6, 1400, '2026-01-21 17:50:41', NULL, NULL),
(2240, 6, 1403, '2026-01-21 17:50:41', NULL, NULL),
(2241, 6, 1356, '2026-01-21 17:50:41', NULL, NULL),
(2242, 6, 1361, '2026-01-21 17:50:41', NULL, NULL),
(2243, 6, 1366, '2026-01-21 17:50:41', NULL, NULL),
(2244, 6, 1371, '2026-01-21 17:50:41', NULL, NULL),
(2245, 6, 1374, '2026-01-21 17:50:41', NULL, NULL),
(2246, 6, 1377, '2026-01-21 17:50:41', NULL, NULL),
(2247, 6, 1380, '2026-01-21 17:50:41', NULL, NULL),
(2248, 6, 1383, '2026-01-21 17:50:42', NULL, NULL),
(2249, 6, 1385, '2026-01-21 17:50:42', NULL, NULL),
(2250, 6, 1388, '2026-01-21 17:50:42', NULL, NULL),
(2251, 6, 1391, '2026-01-21 17:50:42', NULL, NULL),
(2252, 6, 1394, '2026-01-21 17:50:42', NULL, NULL),
(2253, 6, 1397, '2026-01-21 17:50:42', NULL, NULL),
(2254, 6, 1401, '2026-01-21 17:50:42', NULL, NULL),
(2255, 6, 1404, '2026-01-21 17:50:42', NULL, NULL),
(2256, 6, 1357, '2026-01-21 17:50:42', NULL, NULL),
(2257, 6, 1362, '2026-01-21 17:50:42', NULL, NULL),
(2258, 6, 1367, '2026-01-21 17:50:42', NULL, NULL),
(2259, 6, 1372, '2026-01-21 17:50:42', NULL, NULL),
(2260, 6, 1375, '2026-01-21 17:50:42', NULL, NULL),
(2261, 6, 1378, '2026-01-21 17:50:42', NULL, NULL),
(2262, 6, 1381, '2026-01-21 17:50:42', NULL, NULL),
(2263, 6, 1384, '2026-01-21 17:50:42', NULL, NULL),
(2264, 6, 1386, '2026-01-21 17:50:42', NULL, NULL),
(2265, 6, 1389, '2026-01-21 17:50:43', NULL, NULL),
(2266, 6, 1392, '2026-01-21 17:50:43', NULL, NULL),
(2267, 6, 1395, '2026-01-21 17:50:43', NULL, NULL),
(2268, 6, 1398, '2026-01-21 17:50:43', NULL, NULL),
(2269, 6, 1402, '2026-01-21 17:50:43', NULL, NULL),
(2270, 6, 1405, '2026-01-21 17:50:43', NULL, NULL),
(2271, 6, 1358, '2026-01-21 17:50:43', NULL, NULL),
(2272, 6, 1363, '2026-01-21 17:50:43', NULL, NULL),
(2273, 6, 1368, '2026-01-21 17:50:43', NULL, NULL),
(2274, 6, 1387, '2026-01-21 17:50:43', NULL, NULL),
(2275, 6, 1390, '2026-01-21 17:50:43', NULL, NULL),
(2276, 6, 1393, '2026-01-21 17:50:43', NULL, NULL),
(2277, 6, 1396, '2026-01-21 17:50:43', NULL, NULL),
(2278, 6, 1399, '2026-01-21 17:50:43', NULL, NULL),
(2279, 6, 1359, '2026-01-21 17:50:43', NULL, NULL),
(2280, 6, 1364, '2026-01-21 17:50:43', NULL, NULL),
(2281, 6, 1369, '2026-01-21 17:50:43', NULL, NULL),
(2282, 66, 1355, '2026-01-21 17:50:43', NULL, NULL),
(2283, 66, 1360, '2026-01-21 17:50:43', NULL, NULL),
(2284, 66, 1365, '2026-01-21 17:50:43', NULL, NULL),
(2285, 66, 1370, '2026-01-21 17:50:44', NULL, NULL),
(2286, 66, 1373, '2026-01-21 17:50:44', NULL, NULL),
(2287, 66, 1376, '2026-01-21 17:50:44', NULL, NULL),
(2288, 66, 1379, '2026-01-21 17:50:44', NULL, NULL),
(2289, 66, 1382, '2026-01-21 17:50:44', NULL, NULL),
(2290, 66, 1400, '2026-01-21 17:50:44', NULL, NULL),
(2291, 66, 1403, '2026-01-21 17:50:44', NULL, NULL),
(2292, 66, 1356, '2026-01-21 17:50:44', NULL, NULL),
(2293, 66, 1361, '2026-01-21 17:50:44', NULL, NULL),
(2294, 66, 1366, '2026-01-21 17:50:44', NULL, NULL),
(2295, 66, 1371, '2026-01-21 17:50:44', NULL, NULL),
(2296, 66, 1374, '2026-01-21 17:50:44', NULL, NULL),
(2297, 66, 1377, '2026-01-21 17:50:44', NULL, NULL),
(2298, 66, 1380, '2026-01-21 17:50:44', NULL, NULL),
(2299, 66, 1383, '2026-01-21 17:50:44', NULL, NULL),
(2300, 66, 1385, '2026-01-21 17:50:44', NULL, NULL),
(2301, 66, 1388, '2026-01-21 17:50:44', NULL, NULL),
(2302, 66, 1391, '2026-01-21 17:50:44', NULL, NULL),
(2303, 66, 1394, '2026-01-21 17:50:44', NULL, NULL),
(2304, 66, 1397, '2026-01-21 17:50:44', NULL, NULL),
(2305, 66, 1401, '2026-01-21 17:50:44', NULL, NULL),
(2306, 66, 1404, '2026-01-21 17:50:44', NULL, NULL),
(2307, 66, 1357, '2026-01-21 17:50:44', NULL, NULL),
(2308, 66, 1362, '2026-01-21 17:50:44', NULL, NULL),
(2309, 66, 1367, '2026-01-21 17:50:44', NULL, NULL),
(2310, 66, 1372, '2026-01-21 17:50:44', NULL, NULL),
(2311, 66, 1375, '2026-01-21 17:50:44', NULL, NULL),
(2312, 66, 1378, '2026-01-21 17:50:44', NULL, NULL),
(2313, 66, 1381, '2026-01-21 17:50:45', NULL, NULL),
(2314, 66, 1384, '2026-01-21 17:50:45', NULL, NULL),
(2315, 66, 1386, '2026-01-21 17:50:45', NULL, NULL),
(2316, 66, 1389, '2026-01-21 17:50:45', NULL, NULL),
(2317, 66, 1392, '2026-01-21 17:50:45', NULL, NULL),
(2318, 66, 1395, '2026-01-21 17:50:45', NULL, NULL),
(2319, 66, 1398, '2026-01-21 17:50:45', NULL, NULL),
(2320, 66, 1402, '2026-01-21 17:50:45', NULL, NULL),
(2321, 66, 1405, '2026-01-21 17:50:45', NULL, NULL),
(2322, 66, 1358, '2026-01-21 17:50:45', NULL, NULL),
(2323, 66, 1363, '2026-01-21 17:50:45', NULL, NULL),
(2324, 66, 1368, '2026-01-21 17:50:45', NULL, NULL),
(2325, 66, 1387, '2026-01-21 17:50:45', NULL, NULL),
(2326, 66, 1390, '2026-01-21 17:50:46', NULL, NULL),
(2327, 66, 1393, '2026-01-21 17:50:46', NULL, NULL),
(2328, 66, 1396, '2026-01-21 17:50:46', NULL, NULL),
(2329, 66, 1399, '2026-01-21 17:50:46', NULL, NULL),
(2330, 66, 1359, '2026-01-21 17:50:46', NULL, NULL),
(2331, 66, 1364, '2026-01-21 17:50:46', NULL, NULL),
(2332, 66, 1369, '2026-01-21 17:50:46', NULL, NULL),
(2333, 10, 1355, '2026-01-21 17:50:46', NULL, NULL),
(2334, 10, 1360, '2026-01-21 17:50:46', NULL, NULL),
(2335, 10, 1365, '2026-01-21 17:50:46', NULL, NULL),
(2336, 10, 1370, '2026-01-21 17:50:46', NULL, NULL),
(2337, 10, 1373, '2026-01-21 17:50:46', NULL, NULL),
(2338, 10, 1376, '2026-01-21 17:50:46', NULL, NULL),
(2339, 10, 1379, '2026-01-21 17:50:46', NULL, NULL),
(2340, 10, 1382, '2026-01-21 17:50:46', NULL, NULL),
(2341, 10, 1400, '2026-01-21 17:50:46', NULL, NULL),
(2342, 10, 1403, '2026-01-21 17:50:46', NULL, NULL),
(2343, 10, 1356, '2026-01-21 17:50:47', NULL, NULL),
(2344, 10, 1361, '2026-01-21 17:50:47', NULL, NULL),
(2345, 10, 1366, '2026-01-21 17:50:47', NULL, NULL),
(2346, 10, 1371, '2026-01-21 17:50:47', NULL, NULL),
(2347, 10, 1374, '2026-01-21 17:50:47', NULL, NULL),
(2348, 10, 1377, '2026-01-21 17:50:47', NULL, NULL),
(2349, 10, 1380, '2026-01-21 17:50:47', NULL, NULL),
(2350, 10, 1383, '2026-01-21 17:50:47', NULL, NULL),
(2351, 10, 1385, '2026-01-21 17:50:47', NULL, NULL),
(2352, 10, 1388, '2026-01-21 17:50:47', NULL, NULL),
(2353, 10, 1391, '2026-01-21 17:50:47', NULL, NULL),
(2354, 10, 1394, '2026-01-21 17:50:48', NULL, NULL),
(2355, 10, 1397, '2026-01-21 17:50:48', NULL, NULL),
(2356, 10, 1401, '2026-01-21 17:50:48', NULL, NULL),
(2357, 10, 1404, '2026-01-21 17:50:48', NULL, NULL),
(2358, 10, 1357, '2026-01-21 17:50:48', NULL, NULL),
(2359, 10, 1362, '2026-01-21 17:50:48', NULL, NULL),
(2360, 10, 1367, '2026-01-21 17:50:48', NULL, NULL),
(2361, 10, 1372, '2026-01-21 17:50:48', NULL, NULL),
(2362, 10, 1375, '2026-01-21 17:50:48', NULL, NULL),
(2363, 10, 1378, '2026-01-21 17:50:48', NULL, NULL),
(2364, 10, 1381, '2026-01-21 17:50:48', NULL, NULL),
(2365, 10, 1384, '2026-01-21 17:50:48', NULL, NULL),
(2366, 10, 1386, '2026-01-21 17:50:48', NULL, NULL),
(2367, 10, 1389, '2026-01-21 17:50:48', NULL, NULL),
(2368, 10, 1392, '2026-01-21 17:50:48', NULL, NULL),
(2369, 10, 1395, '2026-01-21 17:50:48', NULL, NULL),
(2370, 10, 1398, '2026-01-21 17:50:48', NULL, NULL),
(2371, 10, 1402, '2026-01-21 17:50:48', NULL, NULL),
(2372, 10, 1405, '2026-01-21 17:50:48', NULL, NULL),
(2373, 10, 1358, '2026-01-21 17:50:48', NULL, NULL),
(2374, 10, 1363, '2026-01-21 17:50:48', NULL, NULL),
(2375, 10, 1368, '2026-01-21 17:50:48', NULL, NULL),
(2376, 10, 1387, '2026-01-21 17:50:48', NULL, NULL),
(2377, 10, 1390, '2026-01-21 17:50:48', NULL, NULL),
(2378, 10, 1393, '2026-01-21 17:50:48', NULL, NULL),
(2379, 10, 1396, '2026-01-21 17:50:48', NULL, NULL),
(2380, 10, 1399, '2026-01-21 17:50:49', NULL, NULL),
(2381, 10, 1359, '2026-01-21 17:50:49', NULL, NULL),
(2382, 10, 1364, '2026-01-21 17:50:49', NULL, NULL),
(2383, 10, 1369, '2026-01-21 17:50:49', NULL, NULL),
(2384, 15, 1355, '2026-01-21 17:50:49', NULL, NULL),
(2385, 15, 1360, '2026-01-21 17:50:49', NULL, NULL),
(2386, 15, 1365, '2026-01-21 17:50:49', NULL, NULL),
(2387, 15, 1370, '2026-01-21 17:50:49', NULL, NULL),
(2388, 15, 1373, '2026-01-21 17:50:49', NULL, NULL),
(2389, 15, 1376, '2026-01-21 17:50:49', NULL, NULL),
(2390, 15, 1379, '2026-01-21 17:50:49', NULL, NULL),
(2391, 15, 1382, '2026-01-21 17:50:49', NULL, NULL),
(2392, 15, 1400, '2026-01-21 17:50:49', NULL, NULL),
(2393, 15, 1403, '2026-01-21 17:50:49', NULL, NULL),
(2394, 15, 1356, '2026-01-21 17:50:49', NULL, NULL),
(2395, 15, 1361, '2026-01-21 17:50:49', NULL, NULL),
(2396, 15, 1366, '2026-01-21 17:50:49', NULL, NULL),
(2397, 15, 1371, '2026-01-21 17:50:49', NULL, NULL),
(2398, 15, 1374, '2026-01-21 17:50:49', NULL, NULL),
(2399, 15, 1377, '2026-01-21 17:50:49', NULL, NULL),
(2400, 15, 1380, '2026-01-21 17:50:49', NULL, NULL),
(2401, 15, 1383, '2026-01-21 17:50:49', NULL, NULL),
(2402, 15, 1385, '2026-01-21 17:50:49', NULL, NULL),
(2403, 15, 1388, '2026-01-21 17:50:49', NULL, NULL),
(2404, 15, 1391, '2026-01-21 17:50:49', NULL, NULL),
(2405, 15, 1394, '2026-01-21 17:50:49', NULL, NULL),
(2406, 15, 1397, '2026-01-21 17:50:49', NULL, NULL),
(2407, 15, 1401, '2026-01-21 17:50:50', NULL, NULL),
(2408, 15, 1404, '2026-01-21 17:50:50', NULL, NULL),
(2409, 15, 1357, '2026-01-21 17:50:50', NULL, NULL),
(2410, 15, 1362, '2026-01-21 17:50:50', NULL, NULL),
(2411, 15, 1367, '2026-01-21 17:50:50', NULL, NULL),
(2412, 15, 1372, '2026-01-21 17:50:50', NULL, NULL),
(2413, 15, 1375, '2026-01-21 17:50:50', NULL, NULL),
(2414, 15, 1378, '2026-01-21 17:50:50', NULL, NULL),
(2415, 15, 1381, '2026-01-21 17:50:50', NULL, NULL),
(2416, 15, 1384, '2026-01-21 17:50:50', NULL, NULL),
(2417, 15, 1386, '2026-01-21 17:50:50', NULL, NULL),
(2418, 15, 1389, '2026-01-21 17:50:50', NULL, NULL),
(2419, 15, 1392, '2026-01-21 17:50:50', NULL, NULL),
(2420, 15, 1395, '2026-01-21 17:50:50', NULL, NULL),
(2421, 15, 1398, '2026-01-21 17:50:50', NULL, NULL),
(2422, 15, 1402, '2026-01-21 17:50:51', NULL, NULL),
(2423, 15, 1405, '2026-01-21 17:50:51', NULL, NULL),
(2424, 15, 1358, '2026-01-21 17:50:51', NULL, NULL),
(2425, 15, 1363, '2026-01-21 17:50:51', NULL, NULL),
(2426, 15, 1368, '2026-01-21 17:50:51', NULL, NULL),
(2427, 15, 1387, '2026-01-21 17:50:51', NULL, NULL),
(2428, 15, 1390, '2026-01-21 17:50:51', NULL, NULL),
(2429, 15, 1393, '2026-01-21 17:50:51', NULL, NULL),
(2430, 15, 1396, '2026-01-21 17:50:51', NULL, NULL),
(2431, 15, 1399, '2026-01-21 17:50:51', NULL, NULL),
(2432, 15, 1359, '2026-01-21 17:50:51', NULL, NULL),
(2433, 15, 1364, '2026-01-21 17:50:51', NULL, NULL),
(2434, 15, 1369, '2026-01-21 17:50:51', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `exam_schedules`
--

CREATE TABLE `exam_schedules` (
  `exam_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `exam_type` enum('Midterm','Final','Quiz','Assignment') NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_id` int(11) NOT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `max_students` int(11) DEFAULT 30,
  `instructions` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_published` tinyint(1) DEFAULT 0,
  `status` varchar(20) DEFAULT 'active',
  `student_type` enum('regular','extension') DEFAULT 'regular',
  `year` varchar(10) NOT NULL DEFAULT '1',
  `instructor_id` int(11) DEFAULT NULL,
  `section_number` int(11) DEFAULT NULL COMMENT 'Section number for the exam'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_schedules`
--

INSERT INTO `exam_schedules` (`exam_id`, `course_id`, `exam_type`, `exam_date`, `start_time`, `end_time`, `room_id`, `supervisor_id`, `academic_year`, `semester`, `max_students`, `instructions`, `created_by`, `created_at`, `updated_at`, `is_published`, `status`, `student_type`, `year`, `instructor_id`, `section_number`) VALUES
(37, 36, 'Midterm', '2026-01-16', '09:00:00', '10:30:00', 1, NULL, '2026-2027', '1st Semester', 40, NULL, 1, '2026-01-15 07:57:03', '2026-01-15 07:57:03', 1, 'active', 'regular', 'freshman', NULL, 1),
(38, 36, 'Final', '2026-01-22', '09:00:00', '10:30:00', 4, NULL, '2026-2027', '1st Semester', 40, NULL, 1, '2026-01-15 07:58:08', '2026-01-15 07:58:08', 1, 'active', 'regular', 'freshman', NULL, 1),
(42, 18, 'Midterm', '2026-01-17', '09:00:00', '10:30:00', 1, 5, '2024-2025', '1st Semester', 40, NULL, 4, '2026-01-15 08:49:25', '2026-01-15 08:49:25', 1, 'active', 'extension', 'E1', NULL, NULL),
(44, 19, 'Midterm', '2026-01-18', '09:00:00', '10:30:00', 1, 64, '2024-2025', '1st Semester', 40, NULL, 4, '2026-01-15 08:56:36', '2026-01-15 11:07:37', 1, 'active', 'extension', 'E1', NULL, NULL),
(47, 18, 'Midterm', '2026-01-15', '09:00:00', '10:30:00', 2, 33, '2024-2025', '1st Semester', 40, NULL, 4, '2026-01-15 11:39:36', '2026-01-15 11:39:36', 1, 'active', 'regular', '4', NULL, NULL),
(48, 19, 'Final', '2026-01-16', '09:00:00', '10:30:00', 2, 13, '2024-2025', '1st Semester', 40, NULL, 4, '2026-01-15 11:40:19', '2026-01-15 11:40:19', 1, 'active', 'regular', '4', NULL, NULL),
(49, 19, 'Quiz', '2026-01-17', '09:00:00', '10:30:00', 2, 65, '2024-2025', '1st Semester', 40, NULL, 4, '2026-01-15 11:41:17', '2026-01-15 11:57:08', 1, 'active', 'regular', '4', NULL, NULL),
(51, 23, 'Final', '2026-01-22', '09:00:00', '10:30:00', 1, 5, '2024-2025', '1st Semester', 40, NULL, 4, '2026-01-15 11:50:22', '2026-01-15 11:50:22', 1, 'active', 'extension', 'E1', NULL, NULL),
(52, 24, 'Final', '2026-01-21', '09:00:00', '10:30:00', 4, 64, '2024-2025', '1st Semester', 40, NULL, 4, '2026-01-15 11:54:30', '2026-01-15 11:54:30', 1, 'active', 'regular', '4', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `instructor_workload`
--

CREATE TABLE `instructor_workload` (
  `workload_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `credit_hours` int(11) DEFAULT 0,
  `semester` varchar(50) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `assigned_date` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `instructor_workload`
--

INSERT INTO `instructor_workload` (`workload_id`, `instructor_id`, `course_id`, `credit_hours`, `semester`, `academic_year`, `assigned_date`) VALUES
(13, 33, 26, 5, 'Fall', '2025-2026', '2025-12-13'),
(14, 29, 27, 5, 'Fall', '2025-2026', '2025-12-13'),
(15, 30, 29, 5, 'Fall', '2025-2026', '2025-12-13'),
(16, 32, 30, 3, 'Fall', '2025-2026', '2025-12-13'),
(17, 31, 25, 5, 'Fall', '2025-2026', '2025-12-13'),
(34, 14, 18, 5, '1st Semester', '2024-2025', '2026-01-06'),
(35, 5, 19, 5, '1st Semester', '2024-2025', '2026-01-06'),
(36, 12, 24, 5, '1st Semester', '2024-2025', '2026-01-06'),
(37, 13, 23, 5, '1st Semester', '2024-2025', '2026-01-06'),
(38, 11, 22, 3, '1st Semester', '2024-2025', '2026-01-06');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `user_id`, `token`, `code`, `expires_at`, `is_used`, `verified`, `created_at`) VALUES
(9, 3, 'd7ecb7b550390ca02aee7e07f27cb685e8c626ee96875b588ec136a30e6016bb', '421004', '2026-01-15 20:24:42', 1, 1, '2026-01-15 17:14:42'),
(10, 66, '0d24d9f738b079b16c1e21d90c0c5fa604cd9571cd1cd91bf7ad09ef12f8f69f', '199070', '2026-01-15 20:28:35', 1, 1, '2026-01-15 17:18:35'),
(13, 66, '9dd9554bb521efd6fd652ba43cfcc8995a9f4365d3c6802566d9c9149f977215', '173746', '2026-01-15 20:38:33', 0, 1, '2026-01-15 17:28:33'),
(14, 3, '8466fa341e4d61bebeb44ec84dc25193f9505feea40f68352a1480cf447a35e8', '973846', '2026-01-21 11:15:10', 0, 0, '2026-01-21 08:05:10');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_name` varchar(50) NOT NULL,
  `capacity` int(11) NOT NULL,
  `building` varchar(100) DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_name`, `capacity`, `building`, `is_available`) VALUES
(1, 'Classroom 201', 40, '200', 1),
(2, 'Classroom 202', 40, '200', 1),
(4, 'Classroom 203', 40, '200', 1),
(8, 'Classroom 205', 40, '200', 1),
(9, 'Classroom 206', 40, '200', 1),
(10, 'Classroom 207', 40, '200', 1),
(11, 'Classroom 208', 40, '200', 1),
(13, 'Classroom 301', 40, 'Freshman', 1),
(14, 'Classroom 302', 40, 'Freshman', 1),
(15, 'Classroom 303', 40, 'Freshman', 1),
(16, 'Classroom 304', 40, 'Freshman', 1),
(17, 'Classroom 305', 40, 'Freshman', 1),
(18, 'Classroom 306', 40, 'Freshman', 1),
(19, 'Classroom 307', 40, 'Freshman', 1),
(20, 'Classroom 308', 40, 'Freshman', 1),
(21, 'Classroom 309', 40, 'Freshman', 1),
(22, 'Classroom 310', 40, 'Freshman', 1);

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE `schedule` (
  `schedule_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` enum('Fall','Spring','Summer') NOT NULL,
  `year` varchar(10) NOT NULL,
  `is_extension` tinyint(1) DEFAULT 0,
  `day` varchar(20) DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `department_id` int(11) DEFAULT NULL,
  `student_group` int(11) DEFAULT 1,
  `section_number` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule`
--

INSERT INTO `schedule` (`schedule_id`, `course_id`, `instructor_id`, `room_id`, `academic_year`, `semester`, `year`, `is_extension`, `day`, `start_time`, `end_time`, `created_at`, `department_id`, `student_group`, `section_number`) VALUES
(1355, 18, 14, 2, '2024-2025', '', '4', 0, 'Monday', '08:00:00', '11:00:00', '2026-01-20 17:39:26', NULL, 1, 1),
(1356, 19, 5, 2, '2024-2025', '', '4', 0, 'Monday', '02:30:00', '04:20:00', '2026-01-20 17:39:26', NULL, 1, 1),
(1357, 24, 12, 2, '2024-2025', '', '4', 0, 'Monday', '04:30:00', '06:20:00', '2026-01-20 17:39:26', NULL, 1, 1),
(1358, 23, 13, 2, '2024-2025', '', '4', 0, 'Tuesday', '08:00:00', '11:00:00', '2026-01-20 17:39:26', NULL, 1, 1),
(1359, 22, 11, 2, '2024-2025', '', '4', 0, 'Tuesday', '02:30:00', '04:20:00', '2026-01-20 17:39:26', NULL, 1, 1),
(1360, 18, 14, 2, '2024-2025', '', '4', 0, 'Tuesday', '04:30:00', '06:20:00', '2026-01-20 17:39:26', NULL, 1, 1),
(1361, 19, 5, 2, '2024-2025', '', '4', 0, 'Wednesday', '08:00:00', '11:00:00', '2026-01-20 17:39:26', NULL, 1, 1),
(1362, 24, 12, 2, '2024-2025', '', '4', 0, 'Wednesday', '02:30:00', '04:20:00', '2026-01-20 17:39:26', NULL, 1, 1),
(1363, 23, 13, 2, '2024-2025', '', '4', 0, 'Wednesday', '04:30:00', '06:20:00', '2026-01-20 17:39:26', NULL, 1, 1),
(1364, 22, 11, 2, '2024-2025', '', '4', 0, 'Thursday', '08:00:00', '11:00:00', '2026-01-20 17:39:26', NULL, 1, 1),
(1365, 18, 14, 2, '2024-2025', '', '4', 0, 'Thursday', '02:30:00', '04:20:00', '2026-01-20 17:39:26', NULL, 1, 1),
(1366, 19, 5, 2, '2024-2025', '', '4', 0, 'Thursday', '04:30:00', '06:20:00', '2026-01-20 17:39:26', NULL, 1, 1),
(1367, 24, 12, 2, '2024-2025', '', '4', 0, 'Friday', '08:00:00', '11:00:00', '2026-01-20 17:39:27', NULL, 1, 1),
(1368, 23, 13, 2, '2024-2025', '', '4', 0, 'Friday', '02:30:00', '04:20:00', '2026-01-20 17:39:27', NULL, 1, 1),
(1369, 22, 11, 2, '2024-2025', '', '4', 0, 'Friday', '04:30:00', '06:20:00', '2026-01-20 17:39:27', NULL, 1, 1),
(1370, 18, 14, 9, '2024-2025', '', '2', 0, 'Monday', '08:00:00', '11:00:00', '2026-01-20 17:40:40', NULL, 1, 1),
(1371, 19, 5, 9, '2024-2025', '', '2', 0, 'Monday', '02:30:00', '04:20:00', '2026-01-20 17:40:40', NULL, 1, 1),
(1372, 24, 12, 9, '2024-2025', '', '2', 0, 'Monday', '04:30:00', '06:20:00', '2026-01-20 17:40:40', NULL, 1, 1),
(1373, 18, 14, 9, '2024-2025', '', '2', 0, 'Tuesday', '08:00:00', '11:00:00', '2026-01-20 17:40:40', NULL, 1, 1),
(1374, 19, 5, 9, '2024-2025', '', '2', 0, 'Tuesday', '02:30:00', '04:20:00', '2026-01-20 17:40:40', NULL, 1, 1),
(1375, 24, 12, 9, '2024-2025', '', '2', 0, 'Tuesday', '04:30:00', '06:20:00', '2026-01-20 17:40:40', NULL, 1, 1),
(1376, 18, 14, 9, '2024-2025', '', '2', 0, 'Wednesday', '08:00:00', '11:00:00', '2026-01-20 17:40:40', NULL, 1, 1),
(1377, 19, 5, 9, '2024-2025', '', '2', 0, 'Wednesday', '02:30:00', '04:20:00', '2026-01-20 17:40:40', NULL, 1, 1),
(1378, 24, 12, 9, '2024-2025', '', '2', 0, 'Wednesday', '04:30:00', '06:20:00', '2026-01-20 17:40:40', NULL, 1, 1),
(1379, 18, 14, 9, '2024-2025', '', '2', 0, 'Thursday', '08:00:00', '11:00:00', '2026-01-20 17:40:40', NULL, 1, 1),
(1380, 19, 5, 9, '2024-2025', '', '2', 0, 'Thursday', '02:30:00', '04:20:00', '2026-01-20 17:40:41', NULL, 1, 1),
(1381, 24, 12, 9, '2024-2025', '', '2', 0, 'Thursday', '04:30:00', '06:20:00', '2026-01-20 17:40:41', NULL, 1, 1),
(1382, 18, 14, 9, '2024-2025', '', '2', 0, 'Friday', '08:00:00', '11:00:00', '2026-01-20 17:40:41', NULL, 1, 1),
(1383, 19, 5, 9, '2024-2025', '', '2', 0, 'Friday', '02:30:00', '04:20:00', '2026-01-20 17:40:41', NULL, 1, 1),
(1384, 24, 12, 9, '2024-2025', '', '2', 0, 'Friday', '04:30:00', '06:20:00', '2026-01-20 17:40:41', NULL, 1, 1),
(1385, 19, 5, 10, '2024-2025', '', '3', 0, 'Monday', '08:00:00', '11:00:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1386, 24, 12, 10, '2024-2025', '', '3', 0, 'Monday', '02:30:00', '04:20:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1387, 23, 13, 10, '2024-2025', '', '3', 0, 'Monday', '04:30:00', '06:20:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1388, 19, 5, 10, '2024-2025', '', '3', 0, 'Tuesday', '08:00:00', '11:00:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1389, 24, 12, 10, '2024-2025', '', '3', 0, 'Tuesday', '02:30:00', '04:20:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1390, 23, 13, 10, '2024-2025', '', '3', 0, 'Tuesday', '04:30:00', '06:20:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1391, 19, 5, 10, '2024-2025', '', '3', 0, 'Wednesday', '08:00:00', '11:00:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1392, 24, 12, 10, '2024-2025', '', '3', 0, 'Wednesday', '02:30:00', '04:20:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1393, 23, 13, 10, '2024-2025', '', '3', 0, 'Wednesday', '04:30:00', '06:20:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1394, 19, 5, 10, '2024-2025', '', '3', 0, 'Thursday', '08:00:00', '11:00:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1395, 24, 12, 10, '2024-2025', '', '3', 0, 'Thursday', '02:30:00', '04:20:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1396, 23, 13, 10, '2024-2025', '', '3', 0, 'Thursday', '04:30:00', '06:20:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1397, 19, 5, 10, '2024-2025', '', '3', 0, 'Friday', '08:00:00', '11:00:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1398, 24, 12, 10, '2024-2025', '', '3', 0, 'Friday', '02:30:00', '04:20:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1399, 23, 13, 10, '2024-2025', '', '3', 0, 'Friday', '04:30:00', '06:20:00', '2026-01-20 17:41:14', NULL, 1, 1),
(1400, 18, 14, 11, '2024-2025', '', 'E1', 1, 'Saturday', '08:00:00', '11:00:00', '2026-01-20 17:41:30', NULL, 1, 1),
(1401, 19, 5, 11, '2024-2025', '', 'E1', 1, 'Saturday', '02:30:00', '04:00:00', '2026-01-20 17:41:30', NULL, 1, 1),
(1402, 24, 12, 11, '2024-2025', '', 'E1', 1, 'Saturday', '04:30:00', '06:00:00', '2026-01-20 17:41:30', NULL, 1, 1),
(1403, 18, 14, 11, '2024-2025', '', 'E1', 1, 'Sunday', '08:00:00', '11:00:00', '2026-01-20 17:41:30', NULL, 1, 1),
(1404, 19, 5, 11, '2024-2025', '', 'E1', 1, 'Sunday', '02:30:00', '04:00:00', '2026-01-20 17:41:30', NULL, 1, 1),
(1405, 24, 12, 11, '2024-2025', '', 'E1', 1, 'Sunday', '04:30:00', '06:00:00', '2026-01-20 17:41:30', NULL, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','instructor','student','department_head') NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `year` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `id_number` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `student_id`, `email`, `role`, `department_id`, `year`, `created_at`, `profile_picture`, `email_verified`, `verification_token`, `is_approved`, `is_verified`, `id_number`) VALUES
(1, 'admin', '$2y$10$pVPtgMCpzkCxHDTr0FId7em84Q404ZOFMo1pssS0sMu8KXMtUM4RS', 'System Admin', NULL, 'admin@dku.edu', 'admin', 0, '1', '2025-09-18 12:14:32', 'admin_1_1765634282.jpg', 1, NULL, 1, 1, NULL),
(3, 'Etsub', '$2y$10$eUl59ScJVCk0D0ALCsEBn.vhaKBKVBSWd7.34MASi6N6oHZqfS3Zm', 'Etsub Beza', '1401260', 'etsubbeza20@gmail.com', 'student', 1, '4', '2025-09-18 12:46:13', '1768497383_696920e731315.jpg', 0, NULL, 1, 0, NULL),
(4, 'Mr Head', '$2y$10$v9TF.hfL250gTduHbNuyyu0XwmggSU9lxf9DU.IyrwCVjCQ6s.8f6', 'Birhanu', NULL, 'birhanu@gmail.com', 'department_head', 1, NULL, '2025-09-18 12:48:08', 'profile_4_1759820705.jpg', 0, NULL, 1, 0, NULL),
(5, 'Mr Wasihun', '$2y$10$dmcl6iXZTxcIMptocnODluqEhtrupdMs3xswfaXVn5YKERGjyjEQu', 'Wassihun', NULL, 'wassihun@gmail.com', 'instructor', 1, NULL, '2025-09-18 12:48:29', 'profile_5_1758275006.jpg', 0, NULL, 1, 0, NULL),
(6, 'Eyasu', '$2y$10$xBuiWrRzHpC6JxsSR2C4leM2W9ddTzbeRajPDi6x/86HE7/TYR4Am', 'Eyasu Jida', '1401261', 'eya@gmail.com', 'student', 1, '4', '2025-09-18 12:48:47', NULL, 0, NULL, 1, 0, NULL),
(9, 'Abuki', '$2y$10$L5Z.yWXqWVxzIU8GT60C9u4P4s6XaUTVkMF68I/sqyurKTtFfOmOm', 'Aboboker Mohamed', '1401245', 'ab@gmail.com', 'student', 1, '4', '2025-09-18 14:34:06', 'profile_9_1758273515.jpg', 0, NULL, 1, 0, NULL),
(10, 'Gech', '$2y$10$y3R4meHAa28eEokfeEj5j.XSmKkfawH2dtIhjHR3Ogd.FI9JRrD4u', 'Getachew Worku', '1401345', 'gech12@gmail.com', 'student', 1, '4', '2025-09-18 14:36:08', '1759496549_profile_4_1758289535.jpg', 0, NULL, 1, 0, NULL),
(11, 'Mrs Eleni', '$2y$10$7c.BQhNFC97Zdmm4dXzZWOJ7k/gBRee8PRPuy4VtZkSoD.vQz2iyy', 'Eleni', NULL, 'eleni@gmail.com', 'instructor', 1, NULL, '2025-09-19 13:06:40', NULL, 0, NULL, 1, 0, NULL),
(12, 'Mr Derejaw', '$2y$10$xV6HbvTf2wY63Dd6VjG6P.vGFS.bJIRXOpm7piPpCgxTWHXd.xln.', 'Derejaw', NULL, 'derejaw@gmail.com', 'instructor', 1, NULL, '2025-09-19 13:07:10', 'profile_12_1759496632.jpg', 0, NULL, 1, 0, NULL),
(13, 'Mr Behailu', '$2y$10$4GN7jMkVGSyl5JqwAcYkvu65oMiJDZcN3n2RiSiDO7HCVDbpOwqK.', 'Behaylu', NULL, 'behailu@gmail.com', 'instructor', 1, NULL, '2025-09-19 13:08:10', NULL, 0, NULL, 1, 0, NULL),
(14, 'Mr Abebaw', '$2y$10$3YGztdH1VsrulZYVRVbxru0.I17bX5hTOURWHCHZdKTJnDBDXBhHK', 'Abebaw', NULL, 'abebaw@gmail.com', 'instructor', 1, NULL, '2025-09-19 13:08:35', NULL, 0, NULL, 1, 0, NULL),
(15, 'Yokii', '$2y$10$9EkuQKAI6g1Xg9GKb4d3aO54i1KF6y3og19UwwYsJxoXdbfn1q2Ve', 'Yokabd', '1402345', 'yok@gmail.com', 'student', 1, '4', '2025-10-07 07:25:35', '1759821997_photo_2025-05-25_11-20-54.jpg', 0, NULL, 1, 0, NULL),
(25, 'Abenezer', '$2y$10$snxWLHWTJZ.hGCzCCZE/VOoRNDv.34dFa2zjpbjikoK9QfVeFFRmC', 'Abenezer Beza', '1401269', 'abeni@gmail.com', 'student', 7, '1', '2025-10-08 07:58:25', '1765610265_693d131915e21.jpg', 0, 'c862e7fd1c5d0db61b79503a4791c85ea4ca8a27464dd0826d8c737706576735', 1, 0, NULL),
(26, 'Bantie', '$2y$10$P.LSkO8NL1nKFE7iD.ogyuZsy3mg.BaicdgX.BuGGrfQ.OTZZJjGW', 'Bantie Alene', '1401251', 'bantie@gmail.com', 'student', 1, '4', '2025-10-08 08:29:11', NULL, 0, NULL, 1, 0, NULL),
(28, 'Mr Sami', '$2y$10$M5O7rl0Korn92g/JHofCM.2o7ahxBeIExjgmA3HwmcIGTXJ9px..G', 'Samuel Yared', NULL, 'sami@gmail.com', 'department_head', 7, NULL, '2025-12-13 07:10:35', 'profile_28_1765610903.jpg', 0, NULL, 1, 0, NULL),
(29, 'Mr hailu', '$2y$10$Yd.gqCkTMKoNqNHvmMrrZO8uIK7gP8mcbcx5xkjv8JtK1xGZYcPvq', 'Hailu Abeje', NULL, 'hailu@gmail.com', 'instructor', 7, NULL, '2025-12-13 07:25:12', NULL, 0, NULL, 1, 0, NULL),
(30, 'Mr Sisay', '$2y$10$hC0fjTlZjNMfGSzZrklouehE54eC2rUzS9bbMX0fpb4MQO2sMwfI2', 'Sisay Shemeles', NULL, 'sisay@gmail.com', 'instructor', 7, NULL, '2025-12-13 07:25:50', NULL, 0, NULL, 1, 0, NULL),
(31, 'Mrs Samri', '$2y$10$vBgY8EUUyfYGbelVPzgh1.hERwZU/1mMxKpALYrd3nZwtloqWXp6K', 'Samrawit Girma', NULL, 'samri@gmail.com', 'instructor', 7, NULL, '2025-12-13 07:26:25', NULL, 0, NULL, 1, 0, NULL),
(32, 'Mrs Belen', '$2y$10$iEQw.HaHTOh/mugtUPemTOrUuCTSm3Fs4oXYXXNm58k4ZxZoaPTXG', 'Belen Yirga', NULL, 'belen@gmail.com', 'instructor', 7, NULL, '2025-12-13 07:27:04', NULL, 0, NULL, 1, 0, NULL),
(33, 'Mr Abel', '$2y$10$lOmV6GrUXnJsIialEIAxmeHxCMoOLFV79iReP3ISXDEPGkGUZ5ls2', 'Abel Belay', NULL, 'abel@gmail.com', 'instructor', 7, NULL, '2025-12-13 07:27:36', NULL, 0, NULL, 1, 0, NULL),
(34, 'Halid', '$2y$10$wOeiHKf55scmn/IQGIBn4e3UNb9vY00v7GcIfw8Y3gO8Hd1cLJ/i6', 'Halid Fantaw', '1401256', 'halid@gmail.com', 'student', 7, '1', '2025-12-13 07:29:20', NULL, 0, NULL, 1, 0, NULL),
(35, 'Sitra', '$2y$10$7rjHSRrJ0jFGXUVzDQ.CZul1EHBAJn0asWzhnQTL4oLl.5c5a7jGC', 'Sitra Alemu', '1401255', 'sitra@gmail.com', 'student', 7, '1', '2025-12-13 07:29:47', NULL, 0, NULL, 1, 0, NULL),
(36, 'Hani', '$2y$10$Fr.FdzuncbJMrHoU117.iuIXVo.f6Rvt4M0Iifs44mD0bfJPWnk6G', 'Hana H/Maryam', '1401254', 'hani@gmail.com', 'student', 7, '1', '2025-12-13 07:30:17', NULL, 0, NULL, 1, 0, NULL),
(38, 'Abiye', '$2y$10$pQ4Lfkxfd8x.OQ.Afqe3cu1g8rbXVS4hU7wVBjQfDq1qazaPSpV/S', 'Abiye Birhan', '1401259', 'abiye@gmail.com', 'student', 1, '4', '2025-12-13 08:00:56', NULL, 0, NULL, 1, 0, NULL),
(41, 'Aman', '$2y$10$QydqsP1WwEttohgnwOzd4.jxayjAQRXQpZ1/TAFRB8jhXAw.aN6CK', 'Amanuel Asmare', '1401252', 'aman@gmail.com', 'student', 1, '2', '2025-12-14 05:20:14', NULL, 0, NULL, 0, 0, NULL),
(42, 'Faris', '$2y$10$tKRPnBvfI26RA0oUwWlXTOH1.Tx6W54OAqFlKmI8znMj4DAUPCjIW', 'Faris sultan', '1309421', 'faris@gmail.com', 'student', 1, '3', '2025-12-15 07:43:49', NULL, 0, NULL, 1, 0, NULL),
(43, 'Sami', '$2y$10$/x3SY5LEq6OrvCvvok3FfeKYrATqEUOa65HgkexTD/OdvoIBYJMOS', 'Samuel Belete', '1401257', 'sam@gmail.com', 'student', 1, '1', '2025-12-15 07:45:46', NULL, 0, NULL, 1, 0, NULL),
(44, 'Aster', '$2y$10$vHfuqSDC4I/OPmD0bG9k3.E.3j42qV6df/UwH9PzP.vxjAd7Xtwsi', 'Aster Aweke', '1401250', 'astu@gmail.com', 'student', 1, 'E2', '2025-12-15 08:31:59', NULL, 0, NULL, 1, 0, NULL),
(45, 'Mr Abrham', '$2y$10$aPvSYXQSbQZHSpeZr6/RS.jp0FD.1hqz7C6boVxcjoJJqQW894OVW', 'Abrham Fisiha', NULL, 'abrham@gmail.com', 'department_head', 2, NULL, '2025-12-19 13:28:33', NULL, 0, NULL, 1, 0, NULL),
(46, 'Mr Beza', '$2y$10$bQguN/cry35akLvZmnRR3.8QUUzXqceMHKlMO5sUG.PmvvwIFxGRG', 'BEZA', NULL, 'b@gmail.com', 'department_head', 8, NULL, '2025-12-19 16:24:01', NULL, 0, NULL, 1, 0, NULL),
(47, 'abel', '$2y$10$MONxzSRIkSffBgTeeFRG/uhgjhCf2a4BfKS2wJyqgCYaDlZ5owUEO', 'abel abel', '1412599', 'abella@gmail.com', 'student', 1, 'E1', '2025-12-31 12:52:30', '1767188273_69552731ba355.jpg', 0, NULL, 1, 0, NULL),
(48, 'Andu', '$2y$10$.b/vv0IaJv8QsbCPgOYzWO5QNIaNgH0q.flaKuxUGnKTcE5KzUCiy', 'Andualem', '1401253', 'andu@gmail.com', 'student', 1, 'E3', '2026-01-01 08:04:54', NULL, 0, NULL, 1, 0, NULL),
(49, 'eyu', '$2y$10$Qhee5YXLbWlRtG1tUvZv8umud9TVEjCPg2WkM2WcqsKFGgcMF1Pmi', 'Eyeruss', '1401324', 'eyu@gmail.com', 'student', NULL, 'Freshman', '2026-01-03 13:34:48', NULL, 0, NULL, 1, 0, NULL),
(51, 'Abe', '$2y$10$ZUWz2DmDdvB13Ws75GXAg.ncICAWuxBHUSD7gc/E/jnYO.ZEEhidW', 'Abebe', '1401359', 'abe@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:02:28', '1768064915_69628793e1e83.jpg', 0, NULL, 1, 0, NULL),
(53, 'Biruk', '$2y$10$q3UXZHiHxcd2nq92W5T4LOi6cxvxd1moQjnD2fqlWylE42QlcjHtS', 'Biruk', '1401322', 'burra@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:04:07', NULL, 0, NULL, 1, 0, NULL),
(55, 'reta', '$2y$10$MimTYE5Hbb7Ca.YK/7bXXO1I0ohLlmdwmXkVSD7JV.xG2J2yEPZOS', 'reta', '1401332', 'reta@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:05:20', NULL, 0, NULL, 1, 0, NULL),
(56, 'semret', '$2y$10$LVz2nKV6xclAPxwkDZqjs.ko2WK3rI2aGGjAicdOeU7PomXJHmJrm', 'semri', '1401348', 'semri@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:06:07', NULL, 0, NULL, 1, 0, NULL),
(57, 'roza', '$2y$10$Qk7a5bXSYcbo.hbv7UCSuejAvbVVUwJfZJX6tRJ8kpuD.m5ZcbrKu', 'rozi', '1401346', 'rozi@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:06:40', NULL, 0, NULL, 1, 0, NULL),
(58, 'kaleb', '$2y$10$/do2hIiPtI1mc2R6YK/z.O5M42/RhpCkQPy1appWhaqniswQxe6QO', 'kaleb', '1401311', 'kaleb@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:07:21', NULL, 0, NULL, 1, 0, NULL),
(59, 'Nahom', '$2y$10$MrtkFRTfkXxmEz92p2r3rum3wn.cAJOVT2MRAw0VKT7mlLS8iM7.m', 'nahom', '1401355', 'nahi@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:07:59', NULL, 0, NULL, 1, 0, NULL),
(60, 'sisay', '$2y$10$n/LFe4YTKqH.L28Vg9caYu7/ay65ihmS1mmkG0VrY2GsUcn6gUS6S', 'sis', '1401356', 'siss@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:08:35', NULL, 0, NULL, 1, 0, NULL),
(62, 'Mr one', '$2y$10$6t/p.Q8T6ub1SBLLYd/RjObvcaAsIIWfjnlULNQZo2m0YiIzJ8FwW', 'one', NULL, 'one@gmai.com', 'instructor', 7, '', '2026-01-06 00:05:33', NULL, 0, NULL, 1, 0, NULL),
(63, 'Mr two', '$2y$10$PxyjZIYM9jhFjoNbd6d6UOnCTo1mMQXp03ILkPnUzoMPg38Pc38yO', 'two', NULL, 'two@gmail.com', 'department_head', 14, '', '2026-01-06 00:06:05', NULL, 0, NULL, 1, 0, NULL),
(64, 'Mr three', '$2y$10$aOO7nqiDi21ZP772tENsre/MH4RsqXn2oaA38iSLI4KeSQM8Y1mY2', 'three', NULL, 'three@gmail.com', 'instructor', 13, '', '2026-01-06 00:06:36', NULL, 0, NULL, 1, 0, NULL),
(65, 'Mr four', '$2y$10$EL54Ky9NznSMqwVHc/Xlh.29dUUKxxhW8oCY6JEAbvcaqAGrwALUG', 'four', NULL, 'four@gmail.com', 'instructor', 6, '', '2026-01-06 00:07:08', NULL, 0, NULL, 1, 0, NULL),
(66, 'Eyoba', '$2y$10$/2wFluAGd1zZEwtYEv3bgeAlSBB4N.4Ail7NXSUxQGcLQP20ypwQ.', 'Eyob Nade', '14012599', 'etsub68@gmail.com', 'student', 1, '4', '2026-01-15 17:17:51', '1768497566_6969219ef138c.jpg', 0, NULL, 1, 0, NULL),
(67, 'Samri', '$2y$10$WpwcyHgKB2tKnUh6OljHdu1IvIMwgCVSa0X/fJFSwEJOM53zMvjSe', 'Samri', 'DKU1401254', 'samri1@gmail.com', 'student', 1, '2', '2026-01-20 16:34:46', '1768926942_696faedec0a61.jpg', 0, NULL, 1, 0, NULL),
(68, 'Chaw', '$2y$10$76SBxhNfOs3FGNtMJoIZ7uUHGaCbFMoKmbvhT6EZ8mV81.N73XZXO', 'Chaw Chong', 'Dku1201234', 'chaw@gmail.com', 'student', 1, '4', '2026-01-22 09:56:02', 'profile_6971f432258363.69334591.jpg', 0, NULL, 0, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_documents`
--

CREATE TABLE `user_documents` (
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_documents`
--

INSERT INTO `user_documents` (`document_id`, `user_id`, `filename`, `original_name`, `file_type`, `uploaded_at`) VALUES
(1, 68, 'doc_6971f4325e7b85.53682823.docx', 'Ai questions.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2026-01-22 09:56:02');

-- --------------------------------------------------------

--
-- Table structure for table `workload_limits`
--

CREATE TABLE `workload_limits` (
  `limit_id` int(11) NOT NULL,
  `role` varchar(50) NOT NULL,
  `max_credit_hours` int(11) DEFAULT 12,
  `warning_threshold` int(11) DEFAULT 9,
  `department_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workload_limits`
--

INSERT INTO `workload_limits` (`limit_id`, `role`, `max_credit_hours`, `warning_threshold`, `department_id`) VALUES
(1, 'instructor', 12, 9, NULL),
(2, 'department_head', 6, 4, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`);

--
-- Indexes for table `announcement_comments`
--
ALTER TABLE `announcement_comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `announcement_id` (`announcement_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `announcement_likes`
--
ALTER TABLE `announcement_likes`
  ADD PRIMARY KEY (`like_id`),
  ADD UNIQUE KEY `unique_like` (`announcement_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `announcement_reactions`
--
ALTER TABLE `announcement_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reaction` (`announcement_id`,`user_id`,`reaction_type`);

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`college_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `course_assignments`
--
ALTER TABLE `course_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_course_assignment` (`course_id`,`user_id`,`semester`,`academic_year`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD UNIQUE KEY `department_name` (`department_name`),
  ADD KEY `fk_departments_colleges` (`college_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `fk_enrollments_course` (`course_id`);

--
-- Indexes for table `exam_schedules`
--
ALTER TABLE `exam_schedules`
  ADD PRIMARY KEY (`exam_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `supervisor_id` (`supervisor_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `instructor_id` (`instructor_id`);

--
-- Indexes for table `instructor_workload`
--
ALTER TABLE `instructor_workload`
  ADD PRIMARY KEY (`workload_id`),
  ADD UNIQUE KEY `unique_instructor_workload` (`instructor_id`,`course_id`,`semester`,`academic_year`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `room_name` (`room_name`);

--
-- Indexes for table `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `instructor_id` (`instructor_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `workload_limits`
--
ALTER TABLE `workload_limits`
  ADD PRIMARY KEY (`limit_id`),
  ADD UNIQUE KEY `unique_role_dept` (`role`,`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `announcement_comments`
--
ALTER TABLE `announcement_comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `announcement_likes`
--
ALTER TABLE `announcement_likes`
  MODIFY `like_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `announcement_reactions`
--
ALTER TABLE `announcement_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `college_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `course_assignments`
--
ALTER TABLE `course_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2435;

--
-- AUTO_INCREMENT for table `exam_schedules`
--
ALTER TABLE `exam_schedules`
  MODIFY `exam_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `instructor_workload`
--
ALTER TABLE `instructor_workload`
  MODIFY `workload_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1406;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `user_documents`
--
ALTER TABLE `user_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `workload_limits`
--
ALTER TABLE `workload_limits`
  MODIFY `limit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcement_comments`
--
ALTER TABLE `announcement_comments`
  ADD CONSTRAINT `announcement_comments_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`announcement_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `announcement_likes`
--
ALTER TABLE `announcement_likes`
  ADD CONSTRAINT `announcement_likes_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`announcement_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE;

--
-- Constraints for table `course_assignments`
--
ALTER TABLE `course_assignments`
  ADD CONSTRAINT `course_assignments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_departments_colleges` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON DELETE SET NULL;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `schedule` (`schedule_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_enrollments_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE SET NULL;

--
-- Constraints for table `exam_schedules`
--
ALTER TABLE `exam_schedules`
  ADD CONSTRAINT `exam_schedules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_schedules_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_schedules_ibfk_3` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `exam_schedules_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_schedules_ibfk_5` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `instructor_workload`
--
ALTER TABLE `instructor_workload`
  ADD CONSTRAINT `instructor_workload_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `instructor_workload_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `schedule`
--
ALTER TABLE `schedule`
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_ibfk_2` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD CONSTRAINT `user_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
