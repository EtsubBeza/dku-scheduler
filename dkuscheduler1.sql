-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 29, 2025 at 12:08 PM
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
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `credit_hours` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_code`, `course_name`, `credit_hours`, `department_id`, `created_at`) VALUES
(2, 'co22', 'Computer programming', 0, 1, '2025-09-18 14:30:56'),
(5, 'hi07', 'history', 0, 3, '2025-09-18 17:28:33'),
(6, 'co24', 'computer security', 0, 1, '2025-09-19 12:45:44'),
(7, 'co12', 'compiler design', 0, 1, '2025-09-19 12:45:56'),
(8, 'co224', 'Object oriented programming', 0, 1, '2025-09-19 13:09:37'),
(9, 'co223', 'Image processing', 0, 1, '2025-09-19 13:09:56'),
(10, 'SE23', 'software enginering', 0, 1, '2025-09-19 13:10:32');

-- --------------------------------------------------------

--
-- Table structure for table `course_assignments`
--

CREATE TABLE `course_assignments` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_assignments`
--

INSERT INTO `course_assignments` (`id`, `course_id`, `instructor_id`) VALUES
(1, 6, 12),
(2, 7, 11),
(6, 8, 13),
(4, 9, 5),
(3, 10, 14);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`, `category`) VALUES
(1, 'Computer Science', 'Natural'),
(2, 'Electrical Engineering', 'Natural'),
(3, 'Business Administration', 'Social'),
(4, 'Sociology', 'Social'),
(5, 'Psychology', 'Social'),
(6, 'History', 'Social'),
(7, 'Biology', 'Natural'),
(8, 'Chemistry', 'Natural'),
(9, 'Physics', 'Natural');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`enrollment_id`, `student_id`, `schedule_id`, `enrolled_at`) VALUES
(5, 9, 2, '2025-09-18 16:51:00'),
(7, 3, 2, '2025-09-19 13:14:19'),
(9, 6, 2, '2025-09-19 13:14:19'),
(13, 10, 2, '2025-09-19 13:14:19');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_name` varchar(50) NOT NULL,
  `capacity` int(11) NOT NULL,
  `building` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_name`, `capacity`, `building`) VALUES
(1, 'Classroom 201', 40, NULL),
(2, 'Classroom 202', 0, NULL),
(4, 'Classroom 203', 0, NULL),
(5, 'Classroom 204', 35, '201');

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
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule`
--

INSERT INTO `schedule` (`schedule_id`, `course_id`, `instructor_id`, `room_id`, `academic_year`, `semester`, `day_of_week`, `start_time`, `end_time`, `created_at`) VALUES
(2, 2, 5, 1, '2024', '', 'Tuesday', '02:22:00', '04:44:00', '2025-09-18 16:19:53'),
(3, 2, 5, 2, '2024', '', 'Tuesday', '02:30:00', '04:30:00', '2025-09-18 16:50:18'),
(4, 6, 5, 1, '2024', '', '', '02:30:00', '04:30:00', '2025-09-19 13:00:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','instructor','student','department_head') NOT NULL,
  `department_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `role`, `department_id`, `created_at`, `profile_picture`) VALUES
(1, 'admin', '$2y$10$pVPtgMCpzkCxHDTr0FId7em84Q404ZOFMo1pssS0sMu8KXMtUM4RS', 'System Admin', 'admin@dku.edu', 'admin', 0, '2025-09-18 12:14:32', NULL),
(3, 'Etsub', '$2y$10$A.BPEtTfhnWyjN6bMbXH3O0DzetSL.WLBok7ONjMdbRkUES1xEzTe', '', 'etsubbeza20@gmail.com', 'student', 1, '2025-09-18 12:46:13', NULL),
(4, 'Mr Birhanu', '$2y$10$j/06LWyJk28h.v37em9yje/yrlHk5TbJs6vDEi79RXRLLDqpMqzKe', '', 'birhanu@gmail.com', 'department_head', 1, '2025-09-18 12:48:08', 'profile_4_1758289535.jpg'),
(5, 'Mr Wasihun', '$2y$10$dmcl6iXZTxcIMptocnODluqEhtrupdMs3xswfaXVn5YKERGjyjEQu', '', 'wassihun@gmail.com', 'instructor', 1, '2025-09-18 12:48:29', 'profile_5_1758275006.jpg'),
(6, 'Eyasu', '$2y$10$xBuiWrRzHpC6JxsSR2C4leM2W9ddTzbeRajPDi6x/86HE7/TYR4Am', '', 'eya@gmail.com', 'student', 1, '2025-09-18 12:48:47', NULL),
(9, 'Abuki', '$2y$10$3XQ4Bi3HXOI71fEB0Nq2le5QYXp56ZkWUsGijo9/elLlZossbYH.O', '', 'ab@gmail.com', 'student', 1, '2025-09-18 14:34:06', 'profile_9_1758273515.jpg'),
(10, 'Gech', '$2y$10$Qg70IPHxsSQQxvHfZ6sou.SGhbcHXvbA0eIptrvAkicWKkE4w.fT.', '', 'gech12@gmail.com', 'student', 1, '2025-09-18 14:36:08', NULL),
(11, 'Mrs Eleni', '$2y$10$7c.BQhNFC97Zdmm4dXzZWOJ7k/gBRee8PRPuy4VtZkSoD.vQz2iyy', '', 'eleni@gmail.com', 'instructor', 1, '2025-09-19 13:06:40', NULL),
(12, 'Mr Derejaw', '$2y$10$dRuY8kZ1r9lBpGb4mBUfJutuSgYEL9HB/s.AMrDixkyODWngG8sSi', '', 'derejaw@gmail.com', 'instructor', 1, '2025-09-19 13:07:10', NULL),
(13, 'Mr Behailu', '$2y$10$4GN7jMkVGSyl5JqwAcYkvu65oMiJDZcN3n2RiSiDO7HCVDbpOwqK.', '', 'behailu@gmail.com', 'instructor', 1, '2025-09-19 13:08:10', NULL),
(14, 'Mr Abebaw', '$2y$10$3YGztdH1VsrulZYVRVbxru0.I17bX5hTOURWHCHZdKTJnDBDXBhHK', '', 'abebaw@gmail.com', 'instructor', 1, '2025-09-19 13:08:35', NULL);

--
-- Indexes for dumped tables
--

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
  ADD UNIQUE KEY `unique_assignment` (`course_id`,`instructor_id`),
  ADD KEY `instructor_id` (`instructor_id`);

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
  ADD KEY `schedule_id` (`schedule_id`);

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `course_assignments`
--
ALTER TABLE `course_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

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
  ADD CONSTRAINT `course_assignments_ibfk_2` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `schedule` (`schedule_id`) ON DELETE CASCADE;

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
