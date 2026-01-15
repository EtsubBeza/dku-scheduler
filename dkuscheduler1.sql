-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 10, 2026 at 01:00 PM
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
(7, 4, 47, 'nice one', '2026-01-01 17:54:33');

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
(5, 4, 10),
(9, 4, 15),
(13, 4, 47),
(14, 5, 10),
(16, 5, 12),
(8, 5, 15),
(10, 5, 47);

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
(36, 'GE101', 'Geography', 3, NULL, '2026-01-05 17:30:42', '', 'Compulsory', 3, 0, 0, '', 1);

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
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`, `department_code`, `category`, `description`) VALUES
(1, 'Computer Science', 'CS', 'Natural', ''),
(2, 'Electrical Engineering', 'EG', 'Natural', ''),
(3, 'Business Administration', 'BA', 'Social', ''),
(4, 'Sociology', 'SOC', 'Social', ''),
(5, 'Psychology', 'PSY', 'Social', ''),
(6, 'History', 'HIST', 'Social', ''),
(7, 'Biology', 'BIO', 'Natural', ''),
(8, 'Chemistry', 'CHEM', 'Natural', ''),
(9, 'Physics', 'PHY', 'Natural', ''),
(12, 'Civics', 'CIV', 'Social', ''),
(13, 'Undeclared', NULL, '', NULL);

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
(1688, 38, 934, '2026-01-05 18:28:30', NULL, NULL),
(1689, 38, 939, '2026-01-05 18:28:30', NULL, NULL),
(1690, 38, 944, '2026-01-05 18:28:30', NULL, NULL),
(1691, 38, 935, '2026-01-05 18:28:30', NULL, NULL),
(1692, 38, 940, '2026-01-05 18:28:30', NULL, NULL),
(1693, 38, 945, '2026-01-05 18:28:30', NULL, NULL),
(1694, 38, 936, '2026-01-05 18:28:30', NULL, NULL),
(1695, 38, 941, '2026-01-05 18:28:30', NULL, NULL),
(1696, 38, 946, '2026-01-05 18:28:30', NULL, NULL),
(1697, 38, 937, '2026-01-05 18:28:30', NULL, NULL),
(1698, 38, 942, '2026-01-05 18:28:30', NULL, NULL),
(1699, 38, 947, '2026-01-05 18:28:31', NULL, NULL),
(1700, 38, 938, '2026-01-05 18:28:31', NULL, NULL),
(1701, 38, 943, '2026-01-05 18:28:31', NULL, NULL),
(1702, 38, 948, '2026-01-05 18:28:31', NULL, NULL),
(1703, 9, 934, '2026-01-05 18:28:31', NULL, NULL),
(1704, 9, 939, '2026-01-05 18:28:31', NULL, NULL),
(1705, 9, 944, '2026-01-05 18:28:31', NULL, NULL),
(1706, 9, 935, '2026-01-05 18:28:31', NULL, NULL),
(1707, 9, 940, '2026-01-05 18:28:31', NULL, NULL),
(1708, 9, 945, '2026-01-05 18:28:31', NULL, NULL),
(1709, 9, 936, '2026-01-05 18:28:31', NULL, NULL),
(1710, 9, 941, '2026-01-05 18:28:32', NULL, NULL),
(1711, 9, 946, '2026-01-05 18:28:32', NULL, NULL),
(1712, 9, 937, '2026-01-05 18:28:32', NULL, NULL),
(1713, 9, 942, '2026-01-05 18:28:32', NULL, NULL),
(1714, 9, 947, '2026-01-05 18:28:32', NULL, NULL),
(1715, 9, 938, '2026-01-05 18:28:32', NULL, NULL),
(1716, 9, 943, '2026-01-05 18:28:32', NULL, NULL),
(1717, 9, 948, '2026-01-05 18:28:32', NULL, NULL),
(1718, 26, 934, '2026-01-05 18:28:32', NULL, NULL),
(1719, 26, 939, '2026-01-05 18:28:32', NULL, NULL),
(1720, 26, 944, '2026-01-05 18:28:32', NULL, NULL),
(1721, 26, 935, '2026-01-05 18:28:32', NULL, NULL),
(1722, 26, 940, '2026-01-05 18:28:33', NULL, NULL),
(1723, 26, 945, '2026-01-05 18:28:33', NULL, NULL),
(1724, 26, 936, '2026-01-05 18:28:33', NULL, NULL),
(1725, 26, 941, '2026-01-05 18:28:33', NULL, NULL),
(1726, 26, 946, '2026-01-05 18:28:33', NULL, NULL),
(1727, 26, 937, '2026-01-05 18:28:33', NULL, NULL),
(1728, 26, 942, '2026-01-05 18:28:33', NULL, NULL),
(1729, 26, 947, '2026-01-05 18:28:33', NULL, NULL),
(1730, 26, 938, '2026-01-05 18:28:33', NULL, NULL),
(1731, 26, 943, '2026-01-05 18:28:33', NULL, NULL),
(1732, 26, 948, '2026-01-05 18:28:33', NULL, NULL),
(1733, 3, 934, '2026-01-05 18:28:33', NULL, NULL),
(1734, 3, 939, '2026-01-05 18:28:33', NULL, NULL),
(1735, 3, 944, '2026-01-05 18:28:33', NULL, NULL),
(1736, 3, 935, '2026-01-05 18:28:33', NULL, NULL),
(1737, 3, 940, '2026-01-05 18:28:33', NULL, NULL),
(1738, 3, 945, '2026-01-05 18:28:33', NULL, NULL),
(1739, 3, 936, '2026-01-05 18:28:33', NULL, NULL),
(1740, 3, 941, '2026-01-05 18:28:33', NULL, NULL),
(1741, 3, 946, '2026-01-05 18:28:33', NULL, NULL),
(1742, 3, 937, '2026-01-05 18:28:33', NULL, NULL),
(1743, 3, 942, '2026-01-05 18:28:33', NULL, NULL),
(1744, 3, 947, '2026-01-05 18:28:33', NULL, NULL),
(1745, 3, 938, '2026-01-05 18:28:33', NULL, NULL),
(1746, 3, 943, '2026-01-05 18:28:33', NULL, NULL),
(1747, 3, 948, '2026-01-05 18:28:33', NULL, NULL),
(1748, 6, 934, '2026-01-05 18:28:34', NULL, NULL),
(1749, 6, 939, '2026-01-05 18:28:34', NULL, NULL),
(1750, 6, 944, '2026-01-05 18:28:34', NULL, NULL),
(1751, 6, 935, '2026-01-05 18:28:34', NULL, NULL),
(1752, 6, 940, '2026-01-05 18:28:34', NULL, NULL),
(1753, 6, 945, '2026-01-05 18:28:34', NULL, NULL),
(1754, 6, 936, '2026-01-05 18:28:34', NULL, NULL),
(1755, 6, 941, '2026-01-05 18:28:34', NULL, NULL),
(1756, 6, 946, '2026-01-05 18:28:34', NULL, NULL),
(1757, 6, 937, '2026-01-05 18:28:34', NULL, NULL),
(1758, 6, 942, '2026-01-05 18:28:34', NULL, NULL),
(1759, 6, 947, '2026-01-05 18:28:34', NULL, NULL),
(1760, 6, 938, '2026-01-05 18:28:34', NULL, NULL),
(1761, 6, 943, '2026-01-05 18:28:34', NULL, NULL),
(1762, 6, 948, '2026-01-05 18:28:34', NULL, NULL),
(1763, 10, 934, '2026-01-05 18:28:34', NULL, NULL),
(1764, 10, 939, '2026-01-05 18:28:34', NULL, NULL),
(1765, 10, 944, '2026-01-05 18:28:34', NULL, NULL),
(1766, 10, 935, '2026-01-05 18:28:34', NULL, NULL),
(1767, 10, 940, '2026-01-05 18:28:34', NULL, NULL),
(1768, 10, 945, '2026-01-05 18:28:34', NULL, NULL),
(1769, 10, 936, '2026-01-05 18:28:34', NULL, NULL),
(1770, 10, 941, '2026-01-05 18:28:34', NULL, NULL),
(1771, 10, 946, '2026-01-05 18:28:34', NULL, NULL),
(1772, 10, 937, '2026-01-05 18:28:35', NULL, NULL),
(1773, 10, 942, '2026-01-05 18:28:35', NULL, NULL),
(1774, 10, 947, '2026-01-05 18:28:35', NULL, NULL),
(1775, 10, 938, '2026-01-05 18:28:35', NULL, NULL),
(1776, 10, 943, '2026-01-05 18:28:35', NULL, NULL),
(1777, 10, 948, '2026-01-05 18:28:35', NULL, NULL),
(1778, 15, 934, '2026-01-05 18:28:35', NULL, NULL),
(1779, 15, 939, '2026-01-05 18:28:35', NULL, NULL),
(1780, 15, 944, '2026-01-05 18:28:35', NULL, NULL),
(1781, 15, 935, '2026-01-05 18:28:35', NULL, NULL),
(1782, 15, 940, '2026-01-05 18:28:35', NULL, NULL),
(1783, 15, 945, '2026-01-05 18:28:35', NULL, NULL),
(1784, 15, 936, '2026-01-05 18:28:35', NULL, NULL),
(1785, 15, 941, '2026-01-05 18:28:35', NULL, NULL),
(1786, 15, 946, '2026-01-05 18:28:35', NULL, NULL),
(1787, 15, 937, '2026-01-05 18:28:35', NULL, NULL),
(1788, 15, 942, '2026-01-05 18:28:35', NULL, NULL),
(1789, 15, 947, '2026-01-05 18:28:35', NULL, NULL),
(1790, 15, 938, '2026-01-05 18:28:35', NULL, NULL),
(1791, 15, 943, '2026-01-05 18:28:35', NULL, NULL),
(1792, 15, 948, '2026-01-05 18:28:35', NULL, NULL),
(1793, 47, 934, '2026-01-06 02:12:18', NULL, NULL),
(1794, 47, 939, '2026-01-06 02:12:18', NULL, NULL),
(1795, 47, 944, '2026-01-06 02:12:18', NULL, NULL),
(1796, 47, 935, '2026-01-06 02:22:33', NULL, NULL),
(1797, 47, 940, '2026-01-06 02:22:33', NULL, NULL),
(1798, 47, 945, '2026-01-06 02:22:33', NULL, NULL),
(1799, 47, 936, '2026-01-06 02:22:34', NULL, NULL),
(1800, 47, 941, '2026-01-06 02:22:34', NULL, NULL),
(1801, 47, 946, '2026-01-06 02:22:34', NULL, NULL),
(1802, 47, 937, '2026-01-06 02:22:34', NULL, NULL),
(1803, 47, 942, '2026-01-06 02:22:34', NULL, NULL),
(1804, 47, 947, '2026-01-06 02:22:34', NULL, NULL),
(1805, 47, 1118, '2026-01-06 02:23:29', NULL, NULL),
(1806, 47, 1123, '2026-01-06 02:23:29', NULL, NULL),
(1807, 47, 1119, '2026-01-06 02:23:30', NULL, NULL),
(1808, 47, 1120, '2026-01-06 02:23:30', NULL, NULL),
(1809, 47, 1121, '2026-01-06 02:23:30', NULL, NULL),
(1810, 47, 938, '2026-01-06 02:23:30', NULL, NULL),
(1811, 47, 943, '2026-01-06 02:23:30', NULL, NULL),
(1812, 47, 948, '2026-01-06 02:23:30', NULL, NULL),
(1813, 47, 1122, '2026-01-06 02:23:30', NULL, NULL),
(1814, 51, 1124, '2026-01-08 07:47:53', 36, NULL),
(1815, 53, 1124, '2026-01-08 07:47:53', 36, NULL),
(1816, 51, 1125, '2026-01-08 07:47:53', 33, NULL),
(1817, 53, 1125, '2026-01-08 07:47:53', 33, NULL),
(1818, 51, 1126, '2026-01-08 07:47:53', 35, NULL),
(1819, 53, 1126, '2026-01-08 07:47:53', 35, NULL),
(1820, 51, 1127, '2026-01-08 07:47:53', 32, NULL),
(1821, 53, 1127, '2026-01-08 07:47:53', 32, NULL),
(1822, 51, 1128, '2026-01-08 07:47:53', 34, NULL),
(1823, 53, 1128, '2026-01-08 07:47:53', 34, NULL),
(1824, 51, 1129, '2026-01-08 07:47:53', 36, NULL),
(1825, 53, 1129, '2026-01-08 07:47:53', 36, NULL),
(1826, 51, 1130, '2026-01-08 07:47:53', 33, NULL),
(1827, 53, 1130, '2026-01-08 07:47:53', 33, NULL),
(1828, 51, 1131, '2026-01-08 07:47:53', 35, NULL),
(1829, 53, 1131, '2026-01-08 07:47:53', 35, NULL),
(1830, 51, 1132, '2026-01-08 07:47:53', 32, NULL),
(1831, 53, 1132, '2026-01-08 07:47:53', 32, NULL),
(1832, 51, 1133, '2026-01-08 07:47:53', 34, NULL),
(1833, 53, 1133, '2026-01-08 07:47:53', 34, NULL),
(1834, 51, 1134, '2026-01-08 07:47:53', 36, NULL),
(1835, 53, 1134, '2026-01-08 07:47:53', 36, NULL),
(1836, 51, 1135, '2026-01-08 07:47:53', 33, NULL),
(1837, 53, 1135, '2026-01-08 07:47:53', 33, NULL),
(1838, 51, 1136, '2026-01-08 07:47:53', 35, NULL),
(1839, 53, 1136, '2026-01-08 07:47:53', 35, NULL),
(1840, 51, 1137, '2026-01-08 07:47:53', 32, NULL),
(1841, 53, 1137, '2026-01-08 07:47:53', 32, NULL),
(1842, 51, 1138, '2026-01-08 07:47:53', 34, NULL),
(1843, 53, 1138, '2026-01-08 07:47:53', 34, NULL),
(1844, 59, 1139, '2026-01-08 07:47:53', 36, NULL),
(1845, 55, 1139, '2026-01-08 07:47:53', 36, NULL),
(1846, 59, 1140, '2026-01-08 07:47:53', 33, NULL),
(1847, 55, 1140, '2026-01-08 07:47:53', 33, NULL),
(1848, 59, 1141, '2026-01-08 07:47:53', 35, NULL),
(1849, 55, 1141, '2026-01-08 07:47:53', 35, NULL),
(1850, 59, 1142, '2026-01-08 07:47:53', 32, NULL),
(1851, 55, 1142, '2026-01-08 07:47:53', 32, NULL),
(1852, 59, 1143, '2026-01-08 07:47:53', 34, NULL),
(1853, 55, 1143, '2026-01-08 07:47:53', 34, NULL),
(1854, 59, 1144, '2026-01-08 07:47:53', 36, NULL),
(1855, 55, 1144, '2026-01-08 07:47:53', 36, NULL),
(1856, 59, 1145, '2026-01-08 07:47:53', 33, NULL),
(1857, 55, 1145, '2026-01-08 07:47:53', 33, NULL),
(1858, 59, 1146, '2026-01-08 07:47:53', 35, NULL),
(1859, 55, 1146, '2026-01-08 07:47:53', 35, NULL),
(1860, 59, 1147, '2026-01-08 07:47:53', 32, NULL),
(1861, 55, 1147, '2026-01-08 07:47:53', 32, NULL),
(1862, 59, 1148, '2026-01-08 07:47:53', 34, NULL),
(1863, 55, 1148, '2026-01-08 07:47:53', 34, NULL),
(1864, 59, 1149, '2026-01-08 07:47:53', 36, NULL),
(1865, 55, 1149, '2026-01-08 07:47:53', 36, NULL),
(1866, 59, 1150, '2026-01-08 07:47:53', 33, NULL),
(1867, 55, 1150, '2026-01-08 07:47:53', 33, NULL),
(1868, 59, 1151, '2026-01-08 07:47:53', 35, NULL),
(1869, 55, 1151, '2026-01-08 07:47:53', 35, NULL),
(1870, 59, 1152, '2026-01-08 07:47:53', 32, NULL),
(1871, 55, 1152, '2026-01-08 07:47:53', 32, NULL),
(1872, 59, 1153, '2026-01-08 07:47:53', 34, NULL),
(1873, 55, 1153, '2026-01-08 07:47:53', 34, NULL),
(1874, 56, 1154, '2026-01-08 07:47:53', 36, NULL),
(1875, 60, 1154, '2026-01-08 07:47:53', 36, NULL),
(1876, 56, 1155, '2026-01-08 07:47:53', 33, NULL),
(1877, 60, 1155, '2026-01-08 07:47:53', 33, NULL),
(1878, 56, 1156, '2026-01-08 07:47:53', 35, NULL),
(1879, 60, 1156, '2026-01-08 07:47:53', 35, NULL),
(1880, 56, 1157, '2026-01-08 07:47:53', 32, NULL),
(1881, 60, 1157, '2026-01-08 07:47:53', 32, NULL),
(1882, 56, 1158, '2026-01-08 07:47:53', 34, NULL),
(1883, 60, 1158, '2026-01-08 07:47:53', 34, NULL),
(1884, 56, 1159, '2026-01-08 07:47:53', 36, NULL),
(1885, 60, 1159, '2026-01-08 07:47:53', 36, NULL),
(1886, 56, 1160, '2026-01-08 07:47:53', 33, NULL),
(1887, 60, 1160, '2026-01-08 07:47:53', 33, NULL),
(1888, 56, 1161, '2026-01-08 07:47:53', 35, NULL),
(1889, 60, 1161, '2026-01-08 07:47:53', 35, NULL),
(1890, 56, 1162, '2026-01-08 07:47:53', 32, NULL),
(1891, 60, 1162, '2026-01-08 07:47:53', 32, NULL),
(1892, 56, 1163, '2026-01-08 07:47:53', 34, NULL),
(1893, 60, 1163, '2026-01-08 07:47:53', 34, NULL),
(1894, 56, 1164, '2026-01-08 07:47:53', 36, NULL),
(1895, 60, 1164, '2026-01-08 07:47:53', 36, NULL),
(1896, 56, 1165, '2026-01-08 07:47:53', 33, NULL),
(1897, 60, 1165, '2026-01-08 07:47:53', 33, NULL),
(1898, 56, 1166, '2026-01-08 07:47:53', 35, NULL),
(1899, 60, 1166, '2026-01-08 07:47:53', 35, NULL),
(1900, 56, 1167, '2026-01-08 07:47:53', 32, NULL),
(1901, 60, 1167, '2026-01-08 07:47:53', 32, NULL),
(1902, 56, 1168, '2026-01-08 07:47:53', 34, NULL),
(1903, 60, 1168, '2026-01-08 07:47:53', 34, NULL);

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
(5, 20, 'Midterm', '2025-12-06', '09:00:00', '10:30:00', 1, 14, '2024-2025', 'Spring', 40, NULL, 4, '2025-12-05 07:13:30', '2025-12-05 07:30:22', 1, 'active', 'regular', '1', NULL, NULL),
(6, 19, 'Final', '2025-12-17', '09:00:00', '10:30:00', 1, 11, '2025-2026', 'Fall', 40, NULL, 4, '2025-12-13 07:52:43', '2025-12-13 07:52:43', 1, 'active', 'regular', '1', NULL, NULL),
(30, 36, 'Final', '2026-01-09', '09:00:00', '10:30:00', 1, NULL, '2026-2027', '1st Semester', 40, NULL, 1, '2026-01-08 19:51:14', '2026-01-08 19:51:14', 1, 'active', 'regular', '1', NULL, 1),
(32, 18, 'Midterm', '2026-01-22', '09:00:00', '10:30:00', 4, 13, '2024-2025', '1st Semester', 40, NULL, 4, '2026-01-08 19:53:49', '2026-01-08 19:56:22', 1, 'active', 'regular', '4', NULL, NULL),
(33, 18, 'Final', '2026-01-23', '09:00:00', '10:30:00', 2, 14, '2024-2025', '1st Semester', 40, NULL, 4, '2026-01-08 19:54:17', '2026-01-08 19:57:14', 1, 'active', 'extension', 'E1', NULL, NULL);

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
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT 'schedule_request',
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `related_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `sender_id`, `receiver_id`, `type`, `title`, `message`, `status`, `related_id`, `created_at`) VALUES
(1, 1, 4, 'schedule_request', 'Schedule Request for Course', 'Admin has requested you to schedule the course \'Evolutionary Biology\' for your department.', 'unread', 29, '2026-01-03 14:11:26');

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
(5, 'Classroom 204', 35, '201', 1),
(8, 'Classroom 205', 40, '200', 1),
(9, 'Classroom 206', 40, '200', 1),
(10, 'Classroom 207', 40, '200', 1),
(11, 'Classroom 208', 40, '200', 1);

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
(934, 18, 5, 1, '2024-2025', '', '4', 0, 'Monday', '08:00:00', '11:00:00', '2026-01-05 18:27:49', NULL, 1, 1),
(935, 19, 11, 1, '2024-2025', '', '4', 0, 'Monday', '02:30:00', '04:20:00', '2026-01-05 18:27:49', NULL, 1, 1),
(936, 24, 12, 1, '2024-2025', '', '4', 0, 'Monday', '04:30:00', '06:20:00', '2026-01-05 18:27:49', NULL, 1, 1),
(937, 23, 13, 1, '2024-2025', '', '4', 0, 'Tuesday', '08:00:00', '11:00:00', '2026-01-05 18:27:49', NULL, 1, 1),
(938, 22, 14, 1, '2024-2025', '', '4', 0, 'Tuesday', '02:30:00', '04:20:00', '2026-01-05 18:27:49', NULL, 1, 1),
(939, 18, 5, 1, '2024-2025', '', '4', 0, 'Tuesday', '04:30:00', '06:20:00', '2026-01-05 18:27:49', NULL, 1, 1),
(940, 19, 11, 1, '2024-2025', '', '4', 0, 'Wednesday', '08:00:00', '11:00:00', '2026-01-05 18:27:49', NULL, 1, 1),
(941, 24, 12, 1, '2024-2025', '', '4', 0, 'Wednesday', '02:30:00', '04:20:00', '2026-01-05 18:27:49', NULL, 1, 1),
(942, 23, 13, 1, '2024-2025', '', '4', 0, 'Wednesday', '04:30:00', '06:20:00', '2026-01-05 18:27:49', NULL, 1, 1),
(943, 22, 14, 1, '2024-2025', '', '4', 0, 'Thursday', '08:00:00', '11:00:00', '2026-01-05 18:27:49', NULL, 1, 1),
(944, 18, 5, 1, '2024-2025', '', '4', 0, 'Thursday', '02:30:00', '04:20:00', '2026-01-05 18:27:49', NULL, 1, 1),
(945, 19, 11, 1, '2024-2025', '', '4', 0, 'Thursday', '04:30:00', '06:20:00', '2026-01-05 18:27:49', NULL, 1, 1),
(946, 24, 12, 1, '2024-2025', '', '4', 0, 'Friday', '08:00:00', '11:00:00', '2026-01-05 18:27:49', NULL, 1, 1),
(947, 23, 13, 1, '2024-2025', '', '4', 0, 'Friday', '02:30:00', '04:20:00', '2026-01-05 18:27:49', NULL, 1, 1),
(948, 22, 14, 1, '2024-2025', '', '4', 0, 'Friday', '04:30:00', '06:20:00', '2026-01-05 18:27:49', NULL, 1, 1),
(1073, 36, 5, 4, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1074, 33, 13, 4, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1075, 35, 11, 4, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1076, 32, 31, 4, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1077, 34, 64, 4, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1078, 36, 5, 4, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1079, 33, 13, 4, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1080, 35, 11, 4, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1081, 32, 31, 4, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1082, 34, 64, 4, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1083, 36, 5, 4, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1084, 33, 13, 4, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1085, 35, 11, 4, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1086, 32, 31, 4, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1087, 34, 64, 4, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 1),
(1088, 36, 13, 5, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1089, 33, 65, 5, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1090, 35, 29, 5, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1091, 32, 30, 5, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1092, 34, 14, 5, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1093, 36, 13, 5, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1094, 33, 65, 5, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1095, 35, 29, 5, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1096, 32, 30, 5, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1097, 34, 14, 5, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1098, 36, 13, 5, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1099, 33, 65, 5, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1100, 35, 29, 5, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1101, 32, 30, 5, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1102, 34, 14, 5, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 2),
(1103, 36, 30, 8, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1104, 33, 11, 8, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1105, 35, 65, 8, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1106, 32, 12, 8, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1107, 34, 30, 8, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1108, 36, 30, 8, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1109, 33, 11, 8, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1110, 35, 65, 8, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1111, 32, 12, 8, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1112, 34, 30, 8, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1113, 36, 30, 8, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1114, 33, 11, 8, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1115, 35, 65, 8, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '08:00:00', '11:00:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1116, 32, 12, 8, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '14:30:00', '16:20:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1117, 34, 30, 8, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '16:30:00', '18:00:00', '2026-01-05 23:49:59', NULL, 1, 3),
(1118, 18, 5, 1, '2024-2025', '', 'E1', 1, 'Saturday', '08:00:00', '11:00:00', '2026-01-06 02:23:14', NULL, 1, 1),
(1119, 19, 11, 1, '2024-2025', '', 'E1', 1, 'Saturday', '02:30:00', '04:00:00', '2026-01-06 02:23:14', NULL, 1, 1),
(1120, 24, 12, 1, '2024-2025', '', 'E1', 1, 'Saturday', '04:30:00', '06:00:00', '2026-01-06 02:23:14', NULL, 1, 1),
(1121, 23, 13, 1, '2024-2025', '', 'E1', 1, 'Sunday', '08:00:00', '11:00:00', '2026-01-06 02:23:14', NULL, 1, 1),
(1122, 22, 14, 1, '2024-2025', '', 'E1', 1, 'Sunday', '02:30:00', '04:00:00', '2026-01-06 02:23:14', NULL, 1, 1),
(1123, 18, 5, 1, '2024-2025', '', 'E1', 1, 'Sunday', '04:30:00', '06:00:00', '2026-01-06 02:23:14', NULL, 1, 1),
(1124, 36, 5, 1, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1125, 33, 13, 1, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1126, 35, 11, 1, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1127, 32, 31, 1, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1128, 34, 64, 1, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1129, 36, 5, 1, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1130, 33, 13, 1, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1131, 35, 11, 1, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1132, 32, 31, 1, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1133, 34, 64, 1, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1134, 36, 5, 1, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1135, 33, 13, 1, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1136, 35, 11, 1, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1137, 32, 31, 1, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1138, 34, 64, 1, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 1),
(1139, 36, 13, 4, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1140, 33, 61, 4, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1141, 35, 29, 4, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1142, 32, 30, 4, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1143, 34, 14, 4, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1144, 36, 13, 4, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1145, 33, 61, 4, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1146, 35, 29, 4, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1147, 32, 30, 4, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1148, 34, 14, 4, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1149, 36, 13, 4, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1150, 33, 61, 4, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1151, 35, 29, 4, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1152, 32, 30, 4, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1153, 34, 14, 4, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 2),
(1154, 36, 30, 4, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 3),
(1155, 33, 11, 4, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 3),
(1156, 35, 65, 4, '2026-2027', 'Fall', 'freshman', 0, 'Monday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 3),
(1157, 32, 12, 4, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 3),
(1158, 34, 30, 4, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 3),
(1159, 36, 30, 4, '2026-2027', 'Fall', 'freshman', 0, 'Tuesday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 3),
(1160, 33, 11, 4, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 3),
(1161, 35, 65, 4, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 3),
(1162, 32, 12, 4, '2026-2027', 'Fall', 'freshman', 0, 'Wednesday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 3),
(1163, 34, 30, 4, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 3),
(1164, 36, 30, 4, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 3),
(1165, 33, 11, 4, '2026-2027', 'Fall', 'freshman', 0, 'Thursday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 3),
(1166, 35, 65, 4, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '08:00:00', '11:00:00', '2026-01-08 07:47:53', NULL, 1, 3),
(1167, 32, 12, 4, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '14:30:00', '16:20:00', '2026-01-08 07:47:53', NULL, 1, 3),
(1168, 34, 30, 4, '2026-2027', 'Fall', 'freshman', 0, 'Friday', '16:30:00', '18:00:00', '2026-01-08 07:47:53', NULL, 1, 3);

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
  `year` varchar(10) DEFAULT NULL,
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
(3, 'Etsub', '$2y$10$A.BPEtTfhnWyjN6bMbXH3O0DzetSL.WLBok7ONjMdbRkUES1xEzTe', 'Etsub Beza', '1401260', 'etsubbeza20@gmail.com', 'student', 1, '4', '2025-09-18 12:46:13', NULL, 0, NULL, 1, 0, NULL),
(4, 'Mr Birhanu', '$2y$10$v9TF.hfL250gTduHbNuyyu0XwmggSU9lxf9DU.IyrwCVjCQ6s.8f6', 'Birhanu', NULL, 'birhanu@gmail.com', 'department_head', 1, NULL, '2025-09-18 12:48:08', 'profile_4_1759820705.jpg', 0, NULL, 1, 0, NULL),
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
(51, 'Abe', '$2y$10$ZUWz2DmDdvB13Ws75GXAg.ncICAWuxBHUSD7gc/E/jnYO.ZEEhidW', 'Abebe', '1401359', 'abe@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:02:28', NULL, 0, NULL, 1, 0, NULL),
(53, 'Biruk', '$2y$10$q3UXZHiHxcd2nq92W5T4LOi6cxvxd1moQjnD2fqlWylE42QlcjHtS', 'Biruk', '1401322', 'burra@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:04:07', NULL, 0, NULL, 1, 0, NULL),
(55, 'reta', '$2y$10$MimTYE5Hbb7Ca.YK/7bXXO1I0ohLlmdwmXkVSD7JV.xG2J2yEPZOS', 'reta', '1401332', 'reta@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:05:20', NULL, 0, NULL, 1, 0, NULL),
(56, 'semret', '$2y$10$LVz2nKV6xclAPxwkDZqjs.ko2WK3rI2aGGjAicdOeU7PomXJHmJrm', 'semri', '1401348', 'semri@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:06:07', NULL, 0, NULL, 1, 0, NULL),
(57, 'roza', '$2y$10$Qk7a5bXSYcbo.hbv7UCSuejAvbVVUwJfZJX6tRJ8kpuD.m5ZcbrKu', 'rozi', '1401346', 'rozi@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:06:40', NULL, 0, NULL, 1, 0, NULL),
(58, 'kaleb', '$2y$10$/do2hIiPtI1mc2R6YK/z.O5M42/RhpCkQPy1appWhaqniswQxe6QO', 'kaleb', '1401311', 'kaleb@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:07:21', NULL, 0, NULL, 1, 0, NULL),
(59, 'Nahom', '$2y$10$MrtkFRTfkXxmEz92p2r3rum3wn.cAJOVT2MRAw0VKT7mlLS8iM7.m', 'nahom', '1401355', 'nahi@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:07:59', NULL, 0, NULL, 1, 0, NULL),
(60, 'sisay', '$2y$10$n/LFe4YTKqH.L28Vg9caYu7/ay65ihmS1mmkG0VrY2GsUcn6gUS6S', 'sis', '1401356', 'siss@gmail.com', 'student', NULL, 'Freshman', '2026-01-05 18:08:35', NULL, 0, NULL, 1, 0, NULL),
(61, 'TBA', '', '', NULL, 'tba@university.edu', 'instructor', NULL, 'instructor', '2026-01-05 23:20:34', NULL, 0, NULL, 1, 0, NULL),
(62, 'Mr one', '$2y$10$6t/p.Q8T6ub1SBLLYd/RjObvcaAsIIWfjnlULNQZo2m0YiIzJ8FwW', 'one', NULL, 'one@gmai.com', 'instructor', 7, '', '2026-01-06 00:05:33', NULL, 0, NULL, 1, 0, NULL),
(63, 'Mr two', '$2y$10$PxyjZIYM9jhFjoNbd6d6UOnCTo1mMQXp03ILkPnUzoMPg38Pc38yO', 'two', NULL, 'two@gmail.com', 'instructor', 5, '', '2026-01-06 00:06:05', NULL, 0, NULL, 1, 0, NULL),
(64, 'Mr three', '$2y$10$aOO7nqiDi21ZP772tENsre/MH4RsqXn2oaA38iSLI4KeSQM8Y1mY2', 'three', NULL, 'three@gmail.com', 'instructor', 13, '', '2026-01-06 00:06:36', NULL, 0, NULL, 1, 0, NULL),
(65, 'Mr four', '$2y$10$EL54Ky9NznSMqwVHc/Xlh.29dUUKxxhW8oCY6JEAbvcaqAGrwALUG', 'four', NULL, 'four@gmail.com', 'instructor', 6, '', '2026-01-06 00:07:08', NULL, 0, NULL, 1, 0, NULL);

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
  ADD UNIQUE KEY `department_name` (`department_name`);

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
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

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
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `announcement_likes`
--
ALTER TABLE `announcement_likes`
  MODIFY `like_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `announcement_reactions`
--
ALTER TABLE `announcement_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `course_assignments`
--
ALTER TABLE `course_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1904;

--
-- AUTO_INCREMENT for table `exam_schedules`
--
ALTER TABLE `exam_schedules`
  MODIFY `exam_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `instructor_workload`
--
ALTER TABLE `instructor_workload`
  MODIFY `workload_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1169;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

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
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `schedule`
--
ALTER TABLE `schedule`
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_ibfk_2` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
