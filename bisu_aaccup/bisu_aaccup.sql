-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
-- Host: localhost
-- Generation Time: Mar 07, 2026 at 12:58 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bisu_aaccup`
--

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `area_id` int(11) NOT NULL,
  `area_no` int(11) NOT NULL,
  `area_title` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `areas`
--

INSERT INTO `areas` (`area_id`, `area_no`, `area_title`) VALUES
(1, 1, 'Vision, Mission, Goals, and Objectives'),
(2, 2, 'Faculty'),
(3, 3, 'Curriculum and Instruction'),
(4, 4, 'Support to Students'),
(5, 5, 'Research'),
(6, 6, 'Extension and Community Involvement'),
(7, 7, 'Library'),
(8, 8, 'Physical Plant and Facilities'),
(9, 9, 'Laboratories'),
(10, 10, 'Administration');

-- --------------------------------------------------------

--
-- Table structure for table `colleges`
--

CREATE TABLE `colleges` (
  `college_id` int(11) NOT NULL,
  `college_name` varchar(100) NOT NULL,
  `college_code` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `colleges`
--

INSERT INTO `colleges` (`college_id`, `college_name`, `college_code`) VALUES
(1, 'College of Sciences', 'COS'),
(2, 'College of Fisheries and Marine Sciences', 'CFMS'),
(3, 'College of Business Management', 'CBM'),
(4, 'College of Teacher Education', 'CTE');

-- --------------------------------------------------------

--
-- Table structure for table `cycles`
--

CREATE TABLE `cycles` (
  `cycle_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `level` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `valid_from` date DEFAULT NULL,
  `survey_date` date DEFAULT NULL,
  `submission_deadline` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL -- Note: This column is legacy and may not be used.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cycle_statuses`
--

CREATE TABLE `cycle_statuses` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cycle_statuses`
--

INSERT INTO `cycle_statuses` (`status_id`, `status_name`) VALUES
(1, 'Active'),
(4, 'Completed'),
(3, 'Pending Certificate'),
(2, 'Review');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `doc_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `reviewed_file_path` varchar(255) DEFAULT NULL,
  `type_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_feedback`
--

CREATE TABLE `document_feedback` (
  `feedback_id` int(11) NOT NULL,
  `doc_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `feedback_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `type_id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_types`
--

INSERT INTO `document_types` (`type_id`, `type_name`) VALUES
(5, 'Capsule Report'),
(3, 'Compliance'),
(1, 'Evidence'),
(4, 'Narrative Report'),
(2, 'PPP'),
(6, 'Survey Instrument');

-- --------------------------------------------------------

--
-- Table structure for table `faculty_area_assignments`
--

CREATE TABLE `faculty_area_assignments` (
  `user_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL DEFAULT 0,
  `deadline` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `program_id` int(11) NOT NULL,
  `college_id` int(11) NOT NULL,
  `program_name` varchar(100) NOT NULL,
  `program_code` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`program_id`, `college_id`, `program_name`, `program_code`) VALUES
(1, 1, 'Bachelor of Science in Computer Science', 'BSCS'),
(2, 1, 'Bachelor of Science in Environmental Science', 'BSES'),
(3, 2, 'Bachelor of Science in Marine Biology', 'BSMB'),
(4, 2, 'Bachelor of Science in Fisheries', 'BSF'),
(5, 3, 'Bachelor of Science in Office Administration', 'BSOA'),
(6, 3, 'Bachelor of Science in Hospitality Management', 'BSHM'),
(7, 4, 'Bachelor of Elementary Education', 'BEEd'),
(8, 4, 'Bachelor of Secondary Education ', 'BSED');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(4, 'Accreditor'),
(6, 'Accreditor (External)'),
(7, 'Accreditor (Internal)'),
(1, 'Admin'),
(5, 'Chairperson'),
(2, 'Dean'),
(3, 'Faculty / Focal Person');

-- --------------------------------------------------------

--
-- Table structure for table `survey_parameters`
--

CREATE TABLE `survey_parameters` (
  `param_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `parameter_text` text NOT NULL,
  `parameter_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `survey_parameters`
--

INSERT INTO `survey_parameters` (`param_id`, `area_id`, `parameter_text`, `parameter_order`) VALUES
(1, 1, 'The VMGO are clearly articulated and disseminated to stakeholders.', 1),
(2, 1, 'The VMGO are consistent with the mandate of the institution.', 2),
(3, 2, 'The faculty qualifications are appropriate for the program.', 1),
(4, 2, 'There is a sufficient number of full-time faculty members.', 2),
(5, 3, 'The curriculum is updated and relevant to industry needs.', 1),
(6, 3, 'The instructional methods are effective and varied.', 2),
(7, 4, 'Student support services (e.g., guidance, health) are adequate.', 1),
(8, 5, 'The institution encourages and supports faculty research.', 1),
(9, 6, 'The institution has a visible and impactful extension program.', 1),
(10, 7, 'The library resources are adequate and up-to-date.', 1),
(11, 8, 'The physical facilities are conducive to learning.', 1),
(12, 9, 'The laboratories are well-equipped and maintained.', 1),
(13, 10, 'The administration provides effective leadership and support.', 1);

-- --------------------------------------------------------

--
-- Table structure for table `survey_ratings`
--

CREATE TABLE `survey_ratings` (
  `rating_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `parameter_index` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `rated_by` int(11) NOT NULL,
  `accreditor_type` enum('internal','external') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `middlename` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `college_id` int(11) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `firstname`, `middlename`, `lastname`, `email`, `password`, `role_id`, `program_id`, `college_id`, `avatar_path`) VALUES
(1, 'Gina', 'M.', 'Galbo, EdD', 'admin@bisu.edu.ph', '$2y$10$uDTxa/SbrXRZ2s2Ogwx8lu3PHZDVS2NX0zu9W2CT59wCe0SsiKzb.', 1, NULL, NULL, NULL),
(2, 'COS', '', 'Dean', 'dean.cos@bisu.edu.ph', '$2y$10$YyT1c5U6ktO7JxfZnm1MJObripgFA0.0hEiCHAnnvACeLb0rgzTpi', 2, NULL, 1, NULL),
(3, 'CS', '', 'Chairperson', 'chair.csc@bisu.edu.ph', '$2y$10$mmzlpcXpiWDVFIWlB9x7huTfRQf6xTktgTJYyZqgGX26rL8L2MyJ2', 5, 1, NULL, NULL),
(4, 'CS', '', 'Faculty', 'faculty.csc@bisu.edu.ph', '$2y$10$8K1p/aP0mXNlX.lY7X9X.eGZzR5QyM8m8m8m8m8m8m8m8m8m8', 3, 1, NULL, NULL),
(5, 'Internal', '', 'Accreditor', 'accreditor.internal@bisu.edu.ph', '$2y$10$8K1p/aP0mXNlX.lY7X9X.eGZzR5QyM8m8m8m8m8m8m8m8m8m8', 7, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`area_id`);

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`college_id`);

--
-- Indexes for table `cycles`
--
ALTER TABLE `cycles`
  ADD PRIMARY KEY (`cycle_id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `status_id` (`status_id`);

--
-- Indexes for table `cycle_statuses`
--
ALTER TABLE `cycle_statuses`
  ADD PRIMARY KEY (`status_id`),
  ADD UNIQUE KEY `status_name` (`status_name`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`doc_id`),
  ADD KEY `cycle_id` (`cycle_id`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `type_id` (`type_id`);

--
-- Indexes for table `document_feedback`
--
ALTER TABLE `document_feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `faculty_area_assignments`
--
ALTER TABLE `faculty_area_assignments`
  ADD PRIMARY KEY (`user_id`,`area_id`,`type_id`),
  ADD KEY `area_id` (`area_id`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`type_id`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`program_id`),
  ADD KEY `college_id` (`college_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `survey_parameters`
--
ALTER TABLE `survey_parameters`
  ADD PRIMARY KEY (`param_id`),
  ADD KEY `area_id` (`area_id`);

--
-- Indexes for table `survey_ratings`
--
ALTER TABLE `survey_ratings`
  ADD PRIMARY KEY (`rating_id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `rated_by` (`rated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`email`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `college_id` (`college_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `area_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `college_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cycles`
--
ALTER TABLE `cycles`
  MODIFY `cycle_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cycle_statuses`
--
ALTER TABLE `cycle_statuses`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_feedback`
--
ALTER TABLE `document_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `program_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `survey_parameters`
--
ALTER TABLE `survey_parameters`
  MODIFY `param_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `survey_ratings`
--
ALTER TABLE `survey_ratings`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cycles`
--
ALTER TABLE `cycles`
  ADD CONSTRAINT `cycles_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`),
  ADD CONSTRAINT `cycles_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `cycle_statuses` (`status_id`);

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`cycle_id`) REFERENCES `cycles` (`cycle_id`),
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`),
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `documents_ibfk_4` FOREIGN KEY (`type_id`) REFERENCES `document_types` (`type_id`);

--
-- Constraints for table `document_feedback`
--
ALTER TABLE `document_feedback`
  ADD CONSTRAINT `document_feedback_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`doc_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_feedback_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `faculty_area_assignments`
--
ALTER TABLE `faculty_area_assignments`
  ADD CONSTRAINT `faculty_area_assignments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `faculty_area_assignments_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE;

--
-- Constraints for table `programs`
--
ALTER TABLE `programs`
  ADD CONSTRAINT `programs_ibfk_1` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON DELETE CASCADE;

--
-- Constraints for table `survey_parameters`
--
ALTER TABLE `survey_parameters`
  ADD CONSTRAINT `survey_parameters_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE;

--
-- Constraints for table `survey_ratings`
--
ALTER TABLE `survey_ratings`
  ADD CONSTRAINT `survey_ratings_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `survey_ratings_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `survey_ratings_ibfk_3` FOREIGN KEY (`rated_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`),
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`);

-- --------------------------------------------------------

--
-- Repository workflow tables
-- Active accreditation workflow uses repository-based collaboration.
--

CREATE TABLE IF NOT EXISTS `repositories` (
  `repository_id` int(11) NOT NULL AUTO_INCREMENT,
  `repository_name` varchar(255) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `accreditation_year` year(4) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `course_type` varchar(150) DEFAULT NULL,
  `repository_status` enum('draft','in_review','approved','archived') NOT NULL DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`repository_id`),
  KEY `idx_repositories_program` (`program_id`),
  KEY `idx_repositories_status` (`repository_status`),
  KEY `idx_repositories_created_by` (`created_by`),
  CONSTRAINT `fk_repositories_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_repositories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `repository_members` (
  `repository_member_id` int(11) NOT NULL AUTO_INCREMENT,
  `repository_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `member_role` enum('focal','accreditor') NOT NULL,
  `can_upload` tinyint(1) NOT NULL DEFAULT 0,
  `can_review` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`repository_member_id`),
  UNIQUE KEY `uniq_repository_member` (`repository_id`,`user_id`,`member_role`),
  KEY `idx_repository_members_user` (`user_id`),
  CONSTRAINT `fk_repository_members_repository` FOREIGN KEY (`repository_id`) REFERENCES `repositories` (`repository_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_repository_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `repository_sections` (
  `section_id` int(11) NOT NULL AUTO_INCREMENT,
  `repository_id` int(11) NOT NULL,
  `parent_section_id` int(11) DEFAULT NULL,
  `section_name` varchar(255) NOT NULL,
  `section_kind` enum('folder','area') NOT NULL DEFAULT 'folder',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`section_id`),
  KEY `idx_repository_sections_repository` (`repository_id`),
  KEY `idx_repository_sections_parent` (`parent_section_id`),
  CONSTRAINT `fk_repository_sections_repository` FOREIGN KEY (`repository_id`) REFERENCES `repositories` (`repository_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_repository_sections_parent` FOREIGN KEY (`parent_section_id`) REFERENCES `repository_sections` (`section_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `repository_documents` (
  `repository_document_id` int(11) NOT NULL AUTO_INCREMENT,
  `repository_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `document_status` enum('draft','for_review','finalized','approved') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`repository_document_id`),
  KEY `idx_repository_documents_repository` (`repository_id`),
  KEY `idx_repository_documents_section` (`section_id`),
  KEY `idx_repository_documents_uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_repository_documents_repository` FOREIGN KEY (`repository_id`) REFERENCES `repositories` (`repository_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_repository_documents_section` FOREIGN KEY (`section_id`) REFERENCES `repository_sections` (`section_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_repository_documents_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `repository_comments` (
  `repository_comment_id` int(11) NOT NULL AUTO_INCREMENT,
  `repository_document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`repository_comment_id`),
  KEY `idx_repository_comments_document` (`repository_document_id`),
  KEY `idx_repository_comments_user` (`user_id`),
  CONSTRAINT `fk_repository_comments_document` FOREIGN KEY (`repository_document_id`) REFERENCES `repository_documents` (`repository_document_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_repository_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
