-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 18, 2026 at 06:46 PM
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
(3, 'Etsub', '$2y$10$eUl59ScJVCk0D0ALCsEBn.vhaKBKVBSWd7.34MASi6N6oHZqfS3Zm', 'Etsub Beza', '1401260', 'etsubbeza20@gmail.com', 'student', 1, '4', '2025-09-18 12:46:13', '1768497383_696920e731315.jpg', 0, NULL, 1, 0, NULL),
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
(66, 'Eyoba', '$2y$10$/2wFluAGd1zZEwtYEv3bgeAlSBB4N.4Ail7NXSUxQGcLQP20ypwQ.', 'Eyob Nade', '14012599', 'etsub68@gmail.com', 'student', 1, '4', '2026-01-15 17:17:51', '1768497566_6969219ef138c.jpg', 0, NULL, 1, 0, NULL);

--
-- Indexes for dumped tables
--

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
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
