-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: monetix_test
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `academic_assessments`
--

DROP TABLE IF EXISTS `academic_assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_assessments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `academic_year_id` bigint(20) unsigned NOT NULL,
  `class_grade_id` bigint(20) unsigned DEFAULT NULL,
  `academic_group_id` bigint(20) unsigned DEFAULT NULL,
  `assessment_type_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `exam_date` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `notes` varchar(500) DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `group_key` bigint(20) unsigned GENERATED ALWAYS AS (ifnull(`academic_group_id`,0)) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assessment_institute_year_class_group_name` (`institute_id`,`academic_year_id`,`class_grade_id`,`group_key`,`name`),
  KEY `academic_assessments_branch_id_foreign` (`branch_id`),
  KEY `academic_assessments_academic_year_id_foreign` (`academic_year_id`),
  KEY `academic_assessments_class_grade_id_foreign` (`class_grade_id`),
  KEY `academic_assessments_academic_group_id_foreign` (`academic_group_id`),
  KEY `academic_assessments_assessment_type_id_foreign` (`assessment_type_id`),
  KEY `academic_assessments_created_by_foreign` (`created_by`),
  KEY `aca_year_class_status_idx` (`institute_id`,`academic_year_id`,`class_grade_id`,`status`),
  KEY `aca_institute_branch_idx` (`institute_id`,`branch_id`),
  KEY `academic_assessments_locked_by_foreign` (`locked_by`),
  KEY `aca_locked_at_idx` (`locked_at`),
  CONSTRAINT `academic_assessments_academic_group_id_foreign` FOREIGN KEY (`academic_group_id`) REFERENCES `academic_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_assessments_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_assessments_assessment_type_id_foreign` FOREIGN KEY (`assessment_type_id`) REFERENCES `assessment_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_assessments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_assessments_class_grade_id_foreign` FOREIGN KEY (`class_grade_id`) REFERENCES `class_grades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_assessments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_assessments_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_assessments_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_assessments`
--

LOCK TABLES `academic_assessments` WRITE;
/*!40000 ALTER TABLE `academic_assessments` DISABLE KEYS */;
/*!40000 ALTER TABLE `academic_assessments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_cumulative_result_entries`
--

DROP TABLE IF EXISTS `academic_cumulative_result_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_cumulative_result_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cumulative_result_id` bigint(20) unsigned NOT NULL,
  `final_result_id` bigint(20) unsigned NOT NULL,
  `gpa` decimal(5,2) DEFAULT NULL,
  `grade_points_earned` decimal(10,2) NOT NULL DEFAULT 0.00,
  `credits_earned` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subjects_passed` smallint(5) unsigned NOT NULL DEFAULT 0,
  `subjects_failed` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acume_cumulative_final_unique` (`cumulative_result_id`,`final_result_id`),
  KEY `acume_final_result_idx` (`final_result_id`),
  CONSTRAINT `academic_cumulative_result_entries_cumulative_result_id_foreign` FOREIGN KEY (`cumulative_result_id`) REFERENCES `academic_cumulative_results` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_cumulative_result_entries_final_result_id_foreign` FOREIGN KEY (`final_result_id`) REFERENCES `academic_final_results` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_cumulative_result_entries`
--

LOCK TABLES `academic_cumulative_result_entries` WRITE;
/*!40000 ALTER TABLE `academic_cumulative_result_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `academic_cumulative_result_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_cumulative_results`
--

DROP TABLE IF EXISTS `academic_cumulative_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_cumulative_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `academic_level_id` bigint(20) unsigned DEFAULT NULL,
  `cumulative_gpa` decimal(5,2) DEFAULT NULL,
  `gpa_mode` varchar(20) DEFAULT NULL,
  `total_grade_points` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_credits` decimal(10,2) NOT NULL DEFAULT 0.00,
  `periods_completed` smallint(5) unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acumr_institute_student_level_unique` (`institute_id`,`student_id`,`academic_level_id`),
  KEY `academic_cumulative_results_academic_level_id_foreign` (`academic_level_id`),
  KEY `acumr_student_idx` (`student_id`),
  KEY `acumr_institute_status_idx` (`institute_id`,`status`),
  CONSTRAINT `academic_cumulative_results_academic_level_id_foreign` FOREIGN KEY (`academic_level_id`) REFERENCES `academic_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_cumulative_results_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_cumulative_results_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_cumulative_results`
--

LOCK TABLES `academic_cumulative_results` WRITE;
/*!40000 ALTER TABLE `academic_cumulative_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `academic_cumulative_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_final_result_policies`
--

DROP TABLE IF EXISTS `academic_final_result_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_final_result_policies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `scheme_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `absent_renormalization` tinyint(1) NOT NULL DEFAULT 1,
  `grade_scale_id` bigint(20) unsigned DEFAULT NULL,
  `require_approval` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `frp_scheme_unique` (`scheme_id`),
  KEY `academic_final_result_policies_branch_id_foreign` (`branch_id`),
  KEY `academic_final_result_policies_grade_scale_id_foreign` (`grade_scale_id`),
  KEY `academic_final_result_policies_created_by_foreign` (`created_by`),
  KEY `frp_institute_idx` (`institute_id`),
  CONSTRAINT `academic_final_result_policies_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_final_result_policies_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_final_result_policies_grade_scale_id_foreign` FOREIGN KEY (`grade_scale_id`) REFERENCES `grade_scales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_final_result_policies_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_final_result_policies_scheme_id_foreign` FOREIGN KEY (`scheme_id`) REFERENCES `academic_result_aggregation_schemes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_final_result_policies`
--

LOCK TABLES `academic_final_result_policies` WRITE;
/*!40000 ALTER TABLE `academic_final_result_policies` DISABLE KEYS */;
/*!40000 ALTER TABLE `academic_final_result_policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_final_result_rows`
--

DROP TABLE IF EXISTS `academic_final_result_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_final_result_rows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `result_id` bigint(20) unsigned NOT NULL,
  `placement_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `status` varchar(20) NOT NULL,
  `aggregate` decimal(5,2) DEFAULT NULL,
  `grade` varchar(10) DEFAULT NULL,
  `grade_point` decimal(4,2) DEFAULT NULL,
  `subject_status` varchar(10) DEFAULT NULL,
  `gpa_included` tinyint(1) NOT NULL DEFAULT 0,
  `credits` decimal(4,2) DEFAULT NULL,
  `optional` tinyint(1) NOT NULL DEFAULT 0,
  `incomplete_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `afrr_result_placement_subject_unique` (`result_id`,`placement_id`,`subject_id`),
  KEY `afrr_placement_idx` (`placement_id`),
  KEY `afrr_subject_idx` (`subject_id`),
  CONSTRAINT `academic_final_result_rows_placement_id_foreign` FOREIGN KEY (`placement_id`) REFERENCES `student_academic_placements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_final_result_rows_result_id_foreign` FOREIGN KEY (`result_id`) REFERENCES `academic_final_results` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_final_result_rows_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_final_result_rows`
--

LOCK TABLES `academic_final_result_rows` WRITE;
/*!40000 ALTER TABLE `academic_final_result_rows` DISABLE KEYS */;
/*!40000 ALTER TABLE `academic_final_result_rows` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_final_result_students`
--

DROP TABLE IF EXISTS `academic_final_result_students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_final_result_students` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `result_id` bigint(20) unsigned NOT NULL,
  `placement_id` bigint(20) unsigned NOT NULL,
  `gpa` decimal(4,2) DEFAULT NULL,
  `gpa_status` varchar(20) NOT NULL DEFAULT 'computed',
  `gpa_mode` varchar(20) DEFAULT NULL,
  `gpa_reason` text DEFAULT NULL,
  `passed_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `failed_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `afrs_result_placement_unique` (`result_id`,`placement_id`),
  KEY `afrs_placement_idx` (`placement_id`),
  CONSTRAINT `academic_final_result_students_placement_id_foreign` FOREIGN KEY (`placement_id`) REFERENCES `student_academic_placements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_final_result_students_result_id_foreign` FOREIGN KEY (`result_id`) REFERENCES `academic_final_results` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_final_result_students`
--

LOCK TABLES `academic_final_result_students` WRITE;
/*!40000 ALTER TABLE `academic_final_result_students` DISABLE KEYS */;
/*!40000 ALTER TABLE `academic_final_result_students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_final_results`
--

DROP TABLE IF EXISTS `academic_final_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_final_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `policy_id` bigint(20) unsigned NOT NULL,
  `scheme_id` bigint(20) unsigned NOT NULL,
  `workflow_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'review',
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `published_by` bigint(20) unsigned DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `computed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `academic_final_results_branch_id_foreign` (`branch_id`),
  KEY `academic_final_results_reviewed_by_foreign` (`reviewed_by`),
  KEY `academic_final_results_approved_by_foreign` (`approved_by`),
  KEY `academic_final_results_locked_by_foreign` (`locked_by`),
  KEY `academic_final_results_published_by_foreign` (`published_by`),
  KEY `afr_policy_status_idx` (`policy_id`,`status`),
  KEY `afr_institute_idx` (`institute_id`),
  KEY `afr_scheme_idx` (`scheme_id`),
  KEY `academic_final_results_workflow_id_index` (`workflow_id`),
  CONSTRAINT `academic_final_results_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_final_results_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_final_results_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_final_results_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_final_results_policy_id_foreign` FOREIGN KEY (`policy_id`) REFERENCES `academic_final_result_policies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_final_results_published_by_foreign` FOREIGN KEY (`published_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_final_results_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_final_results_scheme_id_foreign` FOREIGN KEY (`scheme_id`) REFERENCES `academic_result_aggregation_schemes` (`id`),
  CONSTRAINT `academic_final_results_workflow_id_foreign` FOREIGN KEY (`workflow_id`) REFERENCES `workflows` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_final_results`
--

LOCK TABLES `academic_final_results` WRITE;
/*!40000 ALTER TABLE `academic_final_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `academic_final_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_groups`
--

DROP TABLE IF EXISTS `academic_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_id` bigint(20) unsigned NOT NULL,
  `education_system_id` bigint(20) unsigned NOT NULL,
  `academic_level_id` bigint(20) unsigned NOT NULL,
  `class_grade_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(60) NOT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_groups_class_grade_id_code_unique` (`class_grade_id`,`code`),
  KEY `academic_groups_country_id_foreign` (`country_id`),
  KEY `academic_groups_education_system_id_foreign` (`education_system_id`),
  KEY `academic_groups_academic_level_id_status_index` (`academic_level_id`,`status`),
  CONSTRAINT `academic_groups_academic_level_id_foreign` FOREIGN KEY (`academic_level_id`) REFERENCES `academic_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_groups_class_grade_id_foreign` FOREIGN KEY (`class_grade_id`) REFERENCES `class_grades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_groups_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_groups_education_system_id_foreign` FOREIGN KEY (`education_system_id`) REFERENCES `education_systems` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1306 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_groups`
--

LOCK TABLES `academic_groups` WRITE;
/*!40000 ALTER TABLE `academic_groups` DISABLE KEYS */;
INSERT INTO `academic_groups` VALUES (886,26,56,216,853,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(887,26,56,216,853,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(888,26,56,216,853,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(889,26,56,216,854,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(890,26,56,216,854,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(891,26,56,216,854,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(892,26,56,216,855,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(893,26,56,216,855,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(894,26,56,216,855,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(895,26,56,216,856,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(896,26,56,216,856,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(897,26,56,216,856,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(898,26,56,216,857,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(899,26,56,216,857,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(900,26,56,216,857,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(901,26,56,217,858,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(902,26,56,217,858,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(903,26,56,217,858,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(904,26,56,217,859,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(905,26,56,217,859,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(906,26,56,217,859,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(907,27,57,220,869,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(908,27,57,220,869,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(909,27,57,220,869,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(910,27,57,220,870,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(911,27,57,220,870,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(912,27,57,220,870,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(913,27,57,220,871,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(914,27,57,220,871,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(915,27,57,220,871,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(916,27,57,220,872,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(917,27,57,220,872,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(918,27,57,220,872,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(919,27,57,220,873,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(920,27,57,220,873,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(921,27,57,220,873,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(922,27,57,221,874,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(923,27,57,221,874,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(924,27,57,221,874,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(925,27,57,221,875,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(926,27,57,221,875,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(927,27,57,221,875,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(928,28,58,224,885,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(929,28,58,224,885,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(930,28,58,224,885,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(931,28,58,224,886,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(932,28,58,224,886,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(933,28,58,224,886,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(934,28,58,224,887,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(935,28,58,224,887,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(936,28,58,224,887,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(937,28,58,224,888,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(938,28,58,224,888,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(939,28,58,224,888,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(940,28,58,224,889,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(941,28,58,224,889,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(942,28,58,224,889,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(943,28,58,225,890,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(944,28,58,225,890,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(945,28,58,225,890,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(946,28,58,225,891,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(947,28,58,225,891,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(948,28,58,225,891,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(949,29,59,228,901,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(950,29,59,228,901,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(951,29,59,228,901,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(952,29,59,228,902,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(953,29,59,228,902,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(954,29,59,228,902,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(955,29,59,228,903,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(956,29,59,228,903,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(957,29,59,228,903,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(958,29,59,228,904,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(959,29,59,228,904,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(960,29,59,228,904,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(961,29,59,228,905,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(962,29,59,228,905,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(963,29,59,228,905,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(964,29,59,229,906,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(965,29,59,229,906,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(966,29,59,229,906,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(967,29,59,229,907,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(968,29,59,229,907,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(969,29,59,229,907,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(970,30,60,232,917,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(971,30,60,232,917,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(972,30,60,232,917,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(973,30,60,232,918,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(974,30,60,232,918,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(975,30,60,232,918,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(976,30,60,232,919,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(977,30,60,232,919,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(978,30,60,232,919,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(979,30,60,232,920,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(980,30,60,232,920,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(981,30,60,232,920,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(982,30,60,232,921,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(983,30,60,232,921,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(984,30,60,232,921,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(985,30,60,233,922,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(986,30,60,233,922,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(987,30,60,233,922,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(988,30,60,233,923,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(989,30,60,233,923,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(990,30,60,233,923,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(991,31,61,236,933,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(992,31,61,236,933,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(993,31,61,236,933,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(994,31,61,236,934,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(995,31,61,236,934,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(996,31,61,236,934,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(997,31,61,236,935,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(998,31,61,236,935,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(999,31,61,236,935,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1000,31,61,236,936,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1001,31,61,236,936,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1002,31,61,236,936,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1003,31,61,236,937,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1004,31,61,236,937,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1005,31,61,236,937,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1006,31,61,237,938,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1007,31,61,237,938,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1008,31,61,237,938,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1009,31,61,237,939,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1010,31,61,237,939,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1011,31,61,237,939,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1012,32,62,240,949,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1013,32,62,240,949,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1014,32,62,240,949,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1015,32,62,240,950,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1016,32,62,240,950,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1017,32,62,240,950,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1018,32,62,240,951,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1019,32,62,240,951,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1020,32,62,240,951,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1021,32,62,240,952,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1022,32,62,240,952,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1023,32,62,240,952,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1024,32,62,240,953,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1025,32,62,240,953,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1026,32,62,240,953,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1027,32,62,241,954,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1028,32,62,241,954,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1029,32,62,241,954,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1030,32,62,241,955,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1031,32,62,241,955,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1032,32,62,241,955,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1033,33,63,244,965,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1034,33,63,244,965,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1035,33,63,244,965,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1036,33,63,244,966,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1037,33,63,244,966,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1038,33,63,244,966,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1039,33,63,244,967,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1040,33,63,244,967,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1041,33,63,244,967,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1042,33,63,244,968,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1043,33,63,244,968,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1044,33,63,244,968,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1045,33,63,244,969,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1046,33,63,244,969,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1047,33,63,244,969,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1048,33,63,245,970,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1049,33,63,245,970,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1050,33,63,245,970,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1051,33,63,245,971,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1052,33,63,245,971,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1053,33,63,245,971,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1054,34,64,248,981,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1055,34,64,248,981,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1056,34,64,248,981,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1057,34,64,248,982,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1058,34,64,248,982,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1059,34,64,248,982,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1060,34,64,248,983,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1061,34,64,248,983,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1062,34,64,248,983,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1063,34,64,248,984,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1064,34,64,248,984,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1065,34,64,248,984,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1066,34,64,248,985,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1067,34,64,248,985,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1068,34,64,248,985,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1069,34,64,249,986,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1070,34,64,249,986,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1071,34,64,249,986,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1072,34,64,249,987,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1073,34,64,249,987,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1074,34,64,249,987,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1075,35,65,252,997,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1076,35,65,252,997,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1077,35,65,252,997,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1078,35,65,252,998,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1079,35,65,252,998,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1080,35,65,252,998,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1081,35,65,252,999,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1082,35,65,252,999,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1083,35,65,252,999,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1084,35,65,252,1000,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1085,35,65,252,1000,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1086,35,65,252,1000,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1087,35,65,252,1001,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1088,35,65,252,1001,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1089,35,65,252,1001,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1090,35,65,253,1002,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1091,35,65,253,1002,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1092,35,65,253,1002,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1093,35,65,253,1003,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1094,35,65,253,1003,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1095,35,65,253,1003,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1096,36,66,256,1013,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1097,36,66,256,1013,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1098,36,66,256,1013,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1099,36,66,256,1014,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1100,36,66,256,1014,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1101,36,66,256,1014,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1102,36,66,256,1015,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1103,36,66,256,1015,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1104,36,66,256,1015,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1105,36,66,256,1016,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1106,36,66,256,1016,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1107,36,66,256,1016,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1108,36,66,256,1017,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1109,36,66,256,1017,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1110,36,66,256,1017,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1111,36,66,257,1018,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1112,36,66,257,1018,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1113,36,66,257,1018,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1114,36,66,257,1019,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1115,36,66,257,1019,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1116,36,66,257,1019,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1117,37,67,260,1029,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1118,37,67,260,1029,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1119,37,67,260,1029,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1120,37,67,260,1030,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1121,37,67,260,1030,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1122,37,67,260,1030,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1123,37,67,260,1031,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1124,37,67,260,1031,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1125,37,67,260,1031,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1126,37,67,260,1032,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1127,37,67,260,1032,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1128,37,67,260,1032,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1129,37,67,260,1033,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1130,37,67,260,1033,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1131,37,67,260,1033,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1132,37,67,261,1034,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1133,37,67,261,1034,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1134,37,67,261,1034,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1135,37,67,261,1035,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1136,37,67,261,1035,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1137,37,67,261,1035,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1138,38,68,264,1045,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1139,38,68,264,1045,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1140,38,68,264,1045,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1141,38,68,264,1046,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1142,38,68,264,1046,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1143,38,68,264,1046,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1144,38,68,264,1047,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1145,38,68,264,1047,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1146,38,68,264,1047,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1147,38,68,264,1048,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1148,38,68,264,1048,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1149,38,68,264,1048,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1150,38,68,264,1049,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1151,38,68,264,1049,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1152,38,68,264,1049,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1153,38,68,265,1050,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1154,38,68,265,1050,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1155,38,68,265,1050,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1156,38,68,265,1051,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1157,38,68,265,1051,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1158,38,68,265,1051,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1159,39,69,268,1061,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1160,39,69,268,1061,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1161,39,69,268,1061,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1162,39,69,268,1062,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1163,39,69,268,1062,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1164,39,69,268,1062,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1165,39,69,268,1063,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1166,39,69,268,1063,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1167,39,69,268,1063,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1168,39,69,268,1064,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1169,39,69,268,1064,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1170,39,69,268,1064,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1171,39,69,268,1065,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1172,39,69,268,1065,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1173,39,69,268,1065,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1174,39,69,269,1066,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1175,39,69,269,1066,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1176,39,69,269,1066,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1177,39,69,269,1067,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1178,39,69,269,1067,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1179,39,69,269,1067,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1180,40,70,272,1077,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1181,40,70,272,1077,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1182,40,70,272,1077,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1183,40,70,272,1078,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1184,40,70,272,1078,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1185,40,70,272,1078,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1186,40,70,272,1079,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1187,40,70,272,1079,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1188,40,70,272,1079,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1189,40,70,272,1080,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1190,40,70,272,1080,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1191,40,70,272,1080,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1192,40,70,272,1081,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1193,40,70,272,1081,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1194,40,70,272,1081,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1195,40,70,273,1082,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1196,40,70,273,1082,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1197,40,70,273,1082,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1198,40,70,273,1083,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1199,40,70,273,1083,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1200,40,70,273,1083,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1201,41,71,276,1093,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1202,41,71,276,1093,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1203,41,71,276,1093,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1204,41,71,276,1094,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1205,41,71,276,1094,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1206,41,71,276,1094,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1207,41,71,276,1095,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1208,41,71,276,1095,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1209,41,71,276,1095,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1210,41,71,276,1096,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1211,41,71,276,1096,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1212,41,71,276,1096,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1213,41,71,276,1097,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1214,41,71,276,1097,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1215,41,71,276,1097,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1216,41,71,277,1098,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1217,41,71,277,1098,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1218,41,71,277,1098,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1219,41,71,277,1099,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1220,41,71,277,1099,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1221,41,71,277,1099,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1222,42,72,280,1109,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1223,42,72,280,1109,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1224,42,72,280,1109,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1225,42,72,280,1110,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1226,42,72,280,1110,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1227,42,72,280,1110,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1228,42,72,280,1111,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1229,42,72,280,1111,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1230,42,72,280,1111,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1231,42,72,280,1112,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1232,42,72,280,1112,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1233,42,72,280,1112,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1234,42,72,280,1113,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1235,42,72,280,1113,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1236,42,72,280,1113,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1237,42,72,281,1114,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1238,42,72,281,1114,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1239,42,72,281,1114,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1240,42,72,281,1115,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1241,42,72,281,1115,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1242,42,72,281,1115,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1243,43,73,284,1125,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1244,43,73,284,1125,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1245,43,73,284,1125,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1246,43,73,284,1126,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1247,43,73,284,1126,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1248,43,73,284,1126,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1249,43,73,284,1127,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1250,43,73,284,1127,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1251,43,73,284,1127,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1252,43,73,284,1128,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1253,43,73,284,1128,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1254,43,73,284,1128,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1255,43,73,284,1129,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1256,43,73,284,1129,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1257,43,73,284,1129,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1258,43,73,285,1130,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1259,43,73,285,1130,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1260,43,73,285,1130,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1261,43,73,285,1131,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1262,43,73,285,1131,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1263,43,73,285,1131,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1264,44,74,288,1141,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1265,44,74,288,1141,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1266,44,74,288,1141,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1267,44,74,288,1142,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1268,44,74,288,1142,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1269,44,74,288,1142,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1270,44,74,288,1143,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1271,44,74,288,1143,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1272,44,74,288,1143,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1273,44,74,288,1144,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1274,44,74,288,1144,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1275,44,74,288,1144,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1276,44,74,288,1145,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1277,44,74,288,1145,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1278,44,74,288,1145,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1279,44,74,289,1146,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1280,44,74,289,1146,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1281,44,74,289,1146,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1282,44,74,289,1147,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1283,44,74,289,1147,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1284,44,74,289,1147,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1285,45,75,292,1157,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1286,45,75,292,1157,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1287,45,75,292,1157,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1288,45,75,292,1158,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1289,45,75,292,1158,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1290,45,75,292,1158,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1291,45,75,292,1159,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1292,45,75,292,1159,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1293,45,75,292,1159,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1294,45,75,292,1160,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1295,45,75,292,1160,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1296,45,75,292,1160,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1297,45,75,292,1161,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1298,45,75,292,1161,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1299,45,75,292,1161,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1300,45,75,293,1162,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1301,45,75,293,1162,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1302,45,75,293,1162,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1303,45,75,293,1163,'Science','science',1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1304,45,75,293,1163,'Humanities','humanities',2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1305,45,75,293,1163,'Business Studies','business',3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48');
/*!40000 ALTER TABLE `academic_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_levels`
--

DROP TABLE IF EXISTS `academic_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_id` bigint(20) unsigned NOT NULL,
  `education_system_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(60) NOT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_levels_education_system_id_code_unique` (`education_system_id`,`code`),
  KEY `academic_levels_country_id_education_system_id_status_index` (`country_id`,`education_system_id`,`status`),
  CONSTRAINT `academic_levels_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_levels_education_system_id_foreign` FOREIGN KEY (`education_system_id`) REFERENCES `education_systems` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=295 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_levels`
--

LOCK TABLES `academic_levels` WRITE;
/*!40000 ALTER TABLE `academic_levels` DISABLE KEYS */;
INSERT INTO `academic_levels` VALUES (215,26,56,'Primary','primary',1,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(216,26,56,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(217,26,56,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(218,26,56,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(219,27,57,'Primary','primary',1,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(220,27,57,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(221,27,57,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(222,27,57,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(223,28,58,'Primary','primary',1,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(224,28,58,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(225,28,58,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(226,28,58,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(227,29,59,'Primary','primary',1,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(228,29,59,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(229,29,59,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(230,29,59,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(231,30,60,'Primary','primary',1,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(232,30,60,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(233,30,60,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(234,30,60,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(235,31,61,'Primary','primary',1,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(236,31,61,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(237,31,61,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(238,31,61,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(239,32,62,'Primary','primary',1,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(240,32,62,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(241,32,62,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(242,32,62,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(243,33,63,'Primary','primary',1,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(244,33,63,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(245,33,63,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(246,33,63,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(247,34,64,'Primary','primary',1,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(248,34,64,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(249,34,64,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(250,34,64,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(251,35,65,'Primary','primary',1,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(252,35,65,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(253,35,65,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(254,35,65,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(255,36,66,'Primary','primary',1,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(256,36,66,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(257,36,66,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(258,36,66,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(259,37,67,'Primary','primary',1,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(260,37,67,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(261,37,67,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(262,37,67,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(263,38,68,'Primary','primary',1,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(264,38,68,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(265,38,68,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(266,38,68,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(267,39,69,'Primary','primary',1,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(268,39,69,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(269,39,69,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(270,39,69,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(271,40,70,'Primary','primary',1,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(272,40,70,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(273,40,70,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(274,40,70,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(275,41,71,'Primary','primary',1,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(276,41,71,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(277,41,71,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(278,41,71,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(279,42,72,'Primary','primary',1,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(280,42,72,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(281,42,72,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(282,42,72,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(283,43,73,'Primary','primary',1,1,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(284,43,73,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(285,43,73,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(286,43,73,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(287,44,74,'Primary','primary',1,1,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(288,44,74,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(289,44,74,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(290,44,74,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(291,45,75,'Primary','primary',1,1,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(292,45,75,'Secondary','secondary',2,1,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(293,45,75,'Higher Secondary','higher_secondary',3,1,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(294,45,75,'Tertiary','tertiary',4,1,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48');
/*!40000 ALTER TABLE `academic_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_result_aggregation_items`
--

DROP TABLE IF EXISTS `academic_result_aggregation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_result_aggregation_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scheme_id` bigint(20) unsigned NOT NULL,
  `academic_assessment_id` bigint(20) unsigned NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ari_scheme_assessment_unique` (`scheme_id`,`academic_assessment_id`),
  KEY `ari_assessment_idx` (`academic_assessment_id`),
  CONSTRAINT `academic_result_aggregation_items_academic_assessment_id_foreign` FOREIGN KEY (`academic_assessment_id`) REFERENCES `academic_result_aggregation_schemes` (`id`),
  CONSTRAINT `academic_result_aggregation_items_scheme_id_foreign` FOREIGN KEY (`scheme_id`) REFERENCES `academic_result_aggregation_schemes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_result_aggregation_items`
--

LOCK TABLES `academic_result_aggregation_items` WRITE;
/*!40000 ALTER TABLE `academic_result_aggregation_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `academic_result_aggregation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_result_aggregation_schemes`
--

DROP TABLE IF EXISTS `academic_result_aggregation_schemes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_result_aggregation_schemes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `academic_year_id` bigint(20) unsigned NOT NULL,
  `class_grade_id` bigint(20) unsigned NOT NULL,
  `academic_group_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `academic_result_aggregation_schemes_branch_id_foreign` (`branch_id`),
  KEY `academic_result_aggregation_schemes_class_grade_id_foreign` (`class_grade_id`),
  KEY `academic_result_aggregation_schemes_academic_group_id_foreign` (`academic_group_id`),
  KEY `academic_result_aggregation_schemes_created_by_foreign` (`created_by`),
  KEY `ars_year_class_idx` (`academic_year_id`,`class_grade_id`),
  KEY `ars_institute_idx` (`institute_id`),
  CONSTRAINT `academic_result_aggregation_schemes_academic_group_id_foreign` FOREIGN KEY (`academic_group_id`) REFERENCES `academic_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_result_aggregation_schemes_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_result_aggregation_schemes_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_result_aggregation_schemes_class_grade_id_foreign` FOREIGN KEY (`class_grade_id`) REFERENCES `class_grades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_result_aggregation_schemes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_result_aggregation_schemes_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_result_aggregation_schemes`
--

LOCK TABLES `academic_result_aggregation_schemes` WRITE;
/*!40000 ALTER TABLE `academic_result_aggregation_schemes` DISABLE KEYS */;
/*!40000 ALTER TABLE `academic_result_aggregation_schemes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_selection_groups`
--

DROP TABLE IF EXISTS `academic_selection_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_selection_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `class_grade_id` bigint(20) unsigned NOT NULL,
  `academic_group_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(40) NOT NULL,
  `selection_type` varchar(20) NOT NULL DEFAULT 'optional',
  `minimum_selection` int(10) unsigned DEFAULT NULL,
  `maximum_selection` int(10) unsigned DEFAULT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asg_class_code_unique` (`class_grade_id`,`code`),
  KEY `academic_selection_groups_academic_group_id_foreign` (`academic_group_id`),
  KEY `asg_class_status_order_idx` (`class_grade_id`,`status`,`display_order`),
  KEY `academic_selection_groups_status_index` (`status`),
  CONSTRAINT `academic_selection_groups_academic_group_id_foreign` FOREIGN KEY (`academic_group_id`) REFERENCES `academic_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_selection_groups_class_grade_id_foreign` FOREIGN KEY (`class_grade_id`) REFERENCES `class_grades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_selection_groups`
--

LOCK TABLES `academic_selection_groups` WRITE;
/*!40000 ALTER TABLE `academic_selection_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `academic_selection_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_student_marks`
--

DROP TABLE IF EXISTS `academic_student_marks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_student_marks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `academic_assessment_id` bigint(20) unsigned NOT NULL,
  `assessment_subject_id` bigint(20) unsigned NOT NULL,
  `assessment_component_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `academic_placement_id` bigint(20) unsigned NOT NULL,
  `obtained_mark` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'entered',
  `entered_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asm_component_student_unique` (`assessment_component_id`,`student_id`),
  KEY `academic_student_marks_institute_id_foreign` (`institute_id`),
  KEY `academic_student_marks_academic_placement_id_foreign` (`academic_placement_id`),
  KEY `academic_student_marks_entered_by_foreign` (`entered_by`),
  KEY `academic_student_marks_updated_by_foreign` (`updated_by`),
  KEY `asm_student_idx` (`student_id`),
  KEY `asm_assessment_idx` (`academic_assessment_id`),
  KEY `asm_subject_idx` (`assessment_subject_id`),
  CONSTRAINT `academic_student_marks_academic_assessment_id_foreign` FOREIGN KEY (`academic_assessment_id`) REFERENCES `academic_assessments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_student_marks_academic_placement_id_foreign` FOREIGN KEY (`academic_placement_id`) REFERENCES `student_academic_placements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_student_marks_assessment_component_id_foreign` FOREIGN KEY (`assessment_component_id`) REFERENCES `assessment_subject_components` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_student_marks_assessment_subject_id_foreign` FOREIGN KEY (`assessment_subject_id`) REFERENCES `assessment_subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_student_marks_entered_by_foreign` FOREIGN KEY (`entered_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_student_marks_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_student_marks_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_student_marks_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_student_marks`
--

LOCK TABLES `academic_student_marks` WRITE;
/*!40000 ALTER TABLE `academic_student_marks` DISABLE KEYS */;
/*!40000 ALTER TABLE `academic_student_marks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_years`
--

DROP TABLE IF EXISTS `academic_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_years` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(40) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_years_institute_id_code_unique` (`institute_id`,`code`),
  KEY `academic_years_institute_id_status_index` (`institute_id`,`status`),
  CONSTRAINT `academic_years_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_years`
--

LOCK TABLES `academic_years` WRITE;
/*!40000 ALTER TABLE `academic_years` DISABLE KEYS */;
INSERT INTO `academic_years` VALUES (4,133,'2026-6a968bc6cb98e','AY6a968bc6cb991','2026-01-01','2026-12-31',0,1,'2026-09-01 08:24:38','2026-09-01 08:24:38'),(5,135,'2026-2-6a968bc7df747','AY6a968bc7df74a','2026-01-01','2026-12-31',0,1,'2026-09-01 08:24:39','2026-09-01 08:24:39'),(6,138,'2026-6a968bc8e2528','AY6a968bc8e252b','2026-01-01','2026-12-31',0,1,'2026-09-01 08:24:40','2026-09-01 08:24:40'),(7,216,'2026-6a968e883b482','AY6a968e883b484','2026-01-01','2026-12-31',0,1,'2026-09-01 08:36:24','2026-09-01 08:36:24'),(8,218,'2026-2-6a968e88e928f','AY6a968e88e9291','2026-01-01','2026-12-31',0,1,'2026-09-01 08:36:24','2026-09-01 08:36:24'),(9,223,'2026-6a968e8aaed1d','AY6a968e8aaed1f','2026-01-01','2026-12-31',0,1,'2026-09-01 08:36:26','2026-09-01 08:36:26');
/*!40000 ALTER TABLE `academic_years` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `account_groups`
--

DROP TABLE IF EXISTS `account_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` enum('asset','liability','equity','income','expense') NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_account_groups_code` (`institute_id`,`branch_id`,`code`),
  KEY `account_groups_branch_id_foreign` (`branch_id`),
  KEY `account_groups_parent_id_foreign` (`parent_id`),
  CONSTRAINT `account_groups_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `account_groups_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_groups_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `account_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_groups`
--

LOCK TABLES `account_groups` WRITE;
/*!40000 ALTER TABLE `account_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `account_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `account_heads`
--

DROP TABLE IF EXISTS `account_heads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_heads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_account_heads_institute_name` (`institute_id`,`name`),
  CONSTRAINT `fk_account_heads_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_heads`
--

LOCK TABLES `account_heads` WRITE;
/*!40000 ALTER TABLE `account_heads` DISABLE KEYS */;
/*!40000 ALTER TABLE `account_heads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `accounting_audit_trails`
--

DROP TABLE IF EXISTS `accounting_audit_trails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounting_audit_trails` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `actor_type` enum('user','system','ai','cron','import') NOT NULL DEFAULT 'user',
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `action` enum('create','update','delete','post','reverse','void','waive','lock','close','reopen','import','migrate','export','recurring_fee_generated') NOT NULL,
  `entity_type` varchar(60) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `before_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_payload`)),
  `after_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_payload`)),
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `accounting_audit_trails_branch_id_foreign` (`branch_id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_actor` (`actor_type`,`actor_id`),
  KEY `idx_audit_date` (`institute_id`,`created_at`),
  CONSTRAINT `accounting_audit_trails_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `accounting_audit_trails_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounting_audit_trails`
--

LOCK TABLES `accounting_audit_trails` WRITE;
/*!40000 ALTER TABLE `accounting_audit_trails` DISABLE KEYS */;
/*!40000 ALTER TABLE `accounting_audit_trails` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `accounting_periods`
--

DROP TABLE IF EXISTS `accounting_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounting_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `fiscal_year_id` bigint(20) unsigned NOT NULL,
  `name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('open','closed','locked') NOT NULL DEFAULT 'open',
  `closed_by` bigint(20) unsigned DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_accounting_periods_name` (`institute_id`,`branch_id`,`fiscal_year_id`,`name`),
  KEY `accounting_periods_branch_id_foreign` (`branch_id`),
  KEY `idx_accounting_periods_year` (`fiscal_year_id`),
  KEY `idx_periods_scope_status` (`institute_id`,`status`),
  KEY `idx_periods_fiscal_status` (`fiscal_year_id`,`status`),
  CONSTRAINT `accounting_periods_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `accounting_periods_fiscal_year_id_foreign` FOREIGN KEY (`fiscal_year_id`) REFERENCES `fiscal_years` (`id`) ON DELETE CASCADE,
  CONSTRAINT `accounting_periods_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounting_periods`
--

LOCK TABLES `accounting_periods` WRITE;
/*!40000 ALTER TABLE `accounting_periods` DISABLE KEYS */;
/*!40000 ALTER TABLE `accounting_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `accounting_settings`
--

DROP TABLE IF EXISTS `accounting_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounting_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `settings_key` varchar(80) NOT NULL,
  `settings_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`settings_value`)),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_accounting_settings` (`institute_id`,`branch_id`,`settings_key`),
  KEY `accounting_settings_branch_id_foreign` (`branch_id`),
  CONSTRAINT `accounting_settings_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `accounting_settings_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounting_settings`
--

LOCK TABLES `accounting_settings` WRITE;
/*!40000 ALTER TABLE `accounting_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `accounting_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `user_type` enum('platform_admin','institute_user') NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `activity` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_logs_institute` (`institute_id`),
  KEY `idx_activity_logs_user` (`user_type`,`user_id`),
  KEY `idx_activity_logs_created` (`created_at`),
  CONSTRAINT `activity_logs_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_logs_archive`
--

DROP TABLE IF EXISTS `activity_logs_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs_archive` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_id` bigint(20) unsigned NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `original_created_at` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `activity_logs_archive_original_id_index` (`original_id`),
  KEY `activity_logs_archive_archived_at_index` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs_archive`
--

LOCK TABLES `activity_logs_archive` WRITE;
/*!40000 ALTER TABLE `activity_logs_archive` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs_archive` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `administrative_levels`
--

DROP TABLE IF EXISTS `administrative_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `administrative_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_id` bigint(20) unsigned NOT NULL,
  `level_number` tinyint(3) unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `administrative_levels_country_id_level_number_unique` (`country_id`,`level_number`),
  KEY `administrative_levels_country_id_status_index` (`country_id`,`status`),
  CONSTRAINT `administrative_levels_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `administrative_levels`
--

LOCK TABLES `administrative_levels` WRITE;
/*!40000 ALTER TABLE `administrative_levels` DISABLE KEYS */;
/*!40000 ALTER TABLE `administrative_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `administrative_units`
--

DROP TABLE IF EXISTS `administrative_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `administrative_units` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_id` bigint(20) unsigned NOT NULL,
  `administrative_level_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `code` varchar(120) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `au_country_code_unique` (`country_id`,`code`),
  KEY `administrative_units_administrative_level_id_foreign` (`administrative_level_id`),
  KEY `au_country_level_parent_idx` (`country_id`,`administrative_level_id`,`parent_id`),
  KEY `au_parent_idx` (`parent_id`),
  KEY `au_code_idx` (`code`),
  KEY `au_name_idx` (`name`),
  CONSTRAINT `administrative_units_administrative_level_id_foreign` FOREIGN KEY (`administrative_level_id`) REFERENCES `administrative_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `administrative_units_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `administrative_units_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `administrative_units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7404 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `administrative_units`
--

LOCK TABLES `administrative_units` WRITE;
/*!40000 ALTER TABLE `administrative_units` DISABLE KEYS */;
/*!40000 ALTER TABLE `administrative_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_api_keys`
--

DROP TABLE IF EXISTS `ai_api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_api_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(255) NOT NULL,
  `capability` varchar(255) NOT NULL DEFAULT 'text',
  `name` varchar(255) DEFAULT NULL,
  `api_key` text DEFAULT NULL,
  `base_url` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_api_keys`
--

LOCK TABLES `ai_api_keys` WRITE;
/*!40000 ALTER TABLE `ai_api_keys` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_api_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_logs`
--

DROP TABLE IF EXISTS `ai_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `user_type` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `feature` varchar(255) NOT NULL DEFAULT 'assistant',
  `prompt` text DEFAULT NULL,
  `tools` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tools`)),
  `status` varchar(255) NOT NULL DEFAULT 'ok',
  `tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `error` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ai_logs_institute_id_created_at_index` (`institute_id`,`created_at`),
  KEY `ai_logs_user_type_user_id_index` (`user_type`,`user_id`),
  KEY `ai_logs_institute_id_index` (`institute_id`),
  CONSTRAINT `ai_logs_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_logs`
--

LOCK TABLES `ai_logs` WRITE;
/*!40000 ALTER TABLE `ai_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_usage`
--

DROP TABLE IF EXISTS `ai_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_usage` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `period_type` varchar(10) NOT NULL,
  `period` varchar(20) NOT NULL,
  `requests` int(10) unsigned NOT NULL DEFAULT 0,
  `tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_usage_institute_id_period_type_period_unique` (`institute_id`,`period_type`,`period`),
  KEY `ai_usage_institute_id_index` (`institute_id`),
  CONSTRAINT `ai_usage_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_usage`
--

LOCK TABLES `ai_usage` WRITE;
/*!40000 ALTER TABLE `ai_usage` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_usage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `alumni`
--

DROP TABLE IF EXISTS `alumni`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alumni` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `alumni_reference_number` varchar(40) DEFAULT NULL,
  `graduation_date` date DEFAULT NULL,
  `completion_academic_year_id` bigint(20) unsigned DEFAULT NULL,
  `completed_course_id` bigint(20) unsigned DEFAULT NULL,
  `completed_batch_id` bigint(20) unsigned DEFAULT NULL,
  `crm_contact_id` bigint(20) unsigned DEFAULT NULL,
  `current_occupation` varchar(150) DEFAULT NULL,
  `job_title` varchar(150) DEFAULT NULL,
  `employer` varchar(150) DEFAULT NULL,
  `employment_sector` varchar(150) DEFAULT NULL,
  `higher_education` text DEFAULT NULL,
  `career_notes` text DEFAULT NULL,
  `current_city` varchar(120) DEFAULT NULL,
  `current_country` varchar(120) DEFAULT NULL,
  `public_contact_preference` enum('private','email','phone','both') NOT NULL DEFAULT 'private',
  `profile_visibility` enum('private','public') NOT NULL DEFAULT 'private',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alumni_institute_student` (`institute_id`,`student_id`),
  KEY `alumni_student_id_foreign` (`student_id`),
  KEY `alumni_crm_contact_id_foreign` (`crm_contact_id`),
  KEY `alumni_created_by_foreign` (`created_by`),
  KEY `alumni_updated_by_foreign` (`updated_by`),
  KEY `idx_alumni_institute` (`institute_id`),
  KEY `idx_alumni_status` (`status`),
  KEY `idx_alumni_graduation_date` (`graduation_date`),
  KEY `idx_alumni_completion_year` (`completion_academic_year_id`),
  KEY `idx_alumni_completed_course` (`completed_course_id`),
  KEY `idx_alumni_completed_batch` (`completed_batch_id`),
  CONSTRAINT `alumni_completed_batch_id_foreign` FOREIGN KEY (`completed_batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `alumni_completed_course_id_foreign` FOREIGN KEY (`completed_course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `alumni_completion_academic_year_id_foreign` FOREIGN KEY (`completion_academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `alumni_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `alumni_crm_contact_id_foreign` FOREIGN KEY (`crm_contact_id`) REFERENCES `crm_contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `alumni_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alumni_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alumni_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alumni`
--

LOCK TABLES `alumni` WRITE;
/*!40000 ALTER TABLE `alumni` DISABLE KEYS */;
/*!40000 ALTER TABLE `alumni` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `approval_actions`
--

DROP TABLE IF EXISTS `approval_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `step_order` int(10) unsigned NOT NULL,
  `approver_id` bigint(20) unsigned NOT NULL,
  `action` enum('approved','rejected') NOT NULL,
  `notes` text DEFAULT NULL,
  `acted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approval_actions_institute_id_foreign` (`institute_id`),
  KEY `approval_actions_approver_id_foreign` (`approver_id`),
  KEY `approval_actions_request_id_step_order_index` (`request_id`,`step_order`),
  CONSTRAINT `approval_actions_approver_id_foreign` FOREIGN KEY (`approver_id`) REFERENCES `institute_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approval_actions_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approval_actions_request_id_foreign` FOREIGN KEY (`request_id`) REFERENCES `approval_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approval_actions`
--

LOCK TABLES `approval_actions` WRITE;
/*!40000 ALTER TABLE `approval_actions` DISABLE KEYS */;
/*!40000 ALTER TABLE `approval_actions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `approval_requests`
--

DROP TABLE IF EXISTS `approval_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `workflow_id` bigint(20) unsigned NOT NULL,
  `ref_type` varchar(255) NOT NULL,
  `ref_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `status` enum('draft','submitted','pending_approval','approved','rejected') NOT NULL DEFAULT 'draft',
  `current_step` int(10) unsigned NOT NULL DEFAULT 0,
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT NULL,
  `resolved_by` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approval_requests_workflow_id_foreign` (`workflow_id`),
  KEY `approval_requests_requested_by_foreign` (`requested_by`),
  KEY `approval_requests_resolved_by_foreign` (`resolved_by`),
  KEY `approval_requests_institute_id_status_index` (`institute_id`,`status`),
  KEY `approval_requests_ref_type_ref_id_index` (`ref_type`,`ref_id`),
  CONSTRAINT `approval_requests_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approval_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `approval_requests_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `approval_requests_workflow_id_foreign` FOREIGN KEY (`workflow_id`) REFERENCES `approval_workflows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approval_requests`
--

LOCK TABLES `approval_requests` WRITE;
/*!40000 ALTER TABLE `approval_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `approval_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `approval_steps`
--

DROP TABLE IF EXISTS `approval_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_steps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workflow_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `step_order` int(10) unsigned NOT NULL,
  `approver_role_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approval_steps_institute_id_foreign` (`institute_id`),
  KEY `approval_steps_approver_role_id_foreign` (`approver_role_id`),
  KEY `approval_steps_workflow_id_step_order_index` (`workflow_id`,`step_order`),
  CONSTRAINT `approval_steps_approver_role_id_foreign` FOREIGN KEY (`approver_role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approval_steps_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approval_steps_workflow_id_foreign` FOREIGN KEY (`workflow_id`) REFERENCES `approval_workflows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approval_steps`
--

LOCK TABLES `approval_steps` WRITE;
/*!40000 ALTER TABLE `approval_steps` DISABLE KEYS */;
/*!40000 ALTER TABLE `approval_steps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `approval_workflows`
--

DROP TABLE IF EXISTS `approval_workflows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_workflows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `module` enum('expense','purchase','payment','journal_adjustment') NOT NULL,
  `amount_from` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `amount_to` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approval_workflows_created_by_foreign` (`created_by`),
  KEY `approval_workflows_institute_id_module_is_active_index` (`institute_id`,`module`,`is_active`),
  CONSTRAINT `approval_workflows_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `approval_workflows_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approval_workflows`
--

LOCK TABLES `approval_workflows` WRITE;
/*!40000 ALTER TABLE `approval_workflows` DISABLE KEYS */;
/*!40000 ALTER TABLE `approval_workflows` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `archive_jobs`
--

DROP TABLE IF EXISTS `archive_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `archive_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(50) NOT NULL,
  `status` enum('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
  `total_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `archived_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`criteria`)),
  `error` text DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `archive_jobs`
--

LOCK TABLES `archive_jobs` WRITE;
/*!40000 ALTER TABLE `archive_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `archive_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assessment_subject_components`
--

DROP TABLE IF EXISTS `assessment_subject_components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assessment_subject_components` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assessment_subject_id` bigint(20) unsigned NOT NULL,
  `component_id` bigint(20) unsigned NOT NULL,
  `full_mark` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pass_mark` decimal(10,2) NOT NULL DEFAULT 0.00,
  `mandatory_pass` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asc_component_unique` (`assessment_subject_id`,`component_id`),
  KEY `asc_component_idx` (`component_id`),
  CONSTRAINT `assessment_subject_components_assessment_subject_id_foreign` FOREIGN KEY (`assessment_subject_id`) REFERENCES `assessment_subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assessment_subject_components_component_id_foreign` FOREIGN KEY (`component_id`) REFERENCES `components` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessment_subject_components`
--

LOCK TABLES `assessment_subject_components` WRITE;
/*!40000 ALTER TABLE `assessment_subject_components` DISABLE KEYS */;
/*!40000 ALTER TABLE `assessment_subject_components` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assessment_subjects`
--

DROP TABLE IF EXISTS `assessment_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assessment_subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `pass_rule` varchar(30) NOT NULL DEFAULT 'total_only',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assessment_subjects_assessment_id_subject_id_unique` (`assessment_id`,`subject_id`),
  KEY `assessment_subjects_subject_id_index` (`subject_id`),
  CONSTRAINT `assessment_subjects_assessment_id_foreign` FOREIGN KEY (`assessment_id`) REFERENCES `academic_assessments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assessment_subjects_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessment_subjects`
--

LOCK TABLES `assessment_subjects` WRITE;
/*!40000 ALTER TABLE `assessment_subjects` DISABLE KEYS */;
/*!40000 ALTER TABLE `assessment_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assessment_types`
--

DROP TABLE IF EXISTS `assessment_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assessment_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assessment_types_institute_id_slug_unique` (`institute_id`,`slug`),
  KEY `assessment_types_country_id_foreign` (`country_id`),
  CONSTRAINT `assessment_types_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `assessment_types_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessment_types`
--

LOCK TABLES `assessment_types` WRITE;
/*!40000 ALTER TABLE `assessment_types` DISABLE KEYS */;
INSERT INTO `assessment_types` VALUES (1,NULL,NULL,'First Term','first-term',NULL,1,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(2,NULL,NULL,'Second Term','second-term',NULL,2,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(3,NULL,NULL,'Mid Term','mid-term',NULL,3,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(4,NULL,NULL,'Half Yearly','half-yearly',NULL,4,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(5,NULL,NULL,'Final','final',NULL,5,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(6,NULL,NULL,'Class Test','class-test',NULL,6,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(7,NULL,NULL,'Quiz','quiz',NULL,7,1,'2026-09-01 08:23:48','2026-09-01 08:23:48');
/*!40000 ALTER TABLE `assessment_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asset_audit_logs`
--

DROP TABLE IF EXISTS `asset_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `asset_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(60) NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_audit_logs_asset_id_foreign` (`asset_id`),
  KEY `idx_asset_audit_logs_asset` (`institute_id`,`asset_id`),
  CONSTRAINT `asset_audit_logs_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `fixed_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_audit_logs_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asset_audit_logs`
--

LOCK TABLES `asset_audit_logs` WRITE;
/*!40000 ALTER TABLE `asset_audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asset_categories`
--

DROP TABLE IF EXISTS `asset_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `asset_account_id` bigint(20) unsigned DEFAULT NULL,
  `accumulated_depreciation_account_id` bigint(20) unsigned DEFAULT NULL,
  `depreciation_expense_account_id` bigint(20) unsigned DEFAULT NULL,
  `disposal_gain_account_id` bigint(20) unsigned DEFAULT NULL,
  `disposal_loss_account_id` bigint(20) unsigned DEFAULT NULL,
  `impairment_account_id` bigint(20) unsigned DEFAULT NULL,
  `default_useful_life_months` smallint(5) unsigned DEFAULT NULL,
  `default_depreciation_method` varchar(40) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_categories_name` (`institute_id`,`branch_id`,`name`),
  KEY `asset_categories_branch_id_foreign` (`branch_id`),
  KEY `asset_categories_asset_account_id_foreign` (`asset_account_id`),
  KEY `asset_categories_accumulated_depreciation_account_id_foreign` (`accumulated_depreciation_account_id`),
  KEY `asset_categories_depreciation_expense_account_id_foreign` (`depreciation_expense_account_id`),
  KEY `asset_categories_disposal_gain_account_id_foreign` (`disposal_gain_account_id`),
  KEY `asset_categories_disposal_loss_account_id_foreign` (`disposal_loss_account_id`),
  KEY `asset_categories_impairment_account_id_foreign` (`impairment_account_id`),
  KEY `idx_asset_categories_institute` (`institute_id`,`is_active`),
  CONSTRAINT `asset_categories_accumulated_depreciation_account_id_foreign` FOREIGN KEY (`accumulated_depreciation_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_categories_asset_account_id_foreign` FOREIGN KEY (`asset_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_categories_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_categories_depreciation_expense_account_id_foreign` FOREIGN KEY (`depreciation_expense_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_categories_disposal_gain_account_id_foreign` FOREIGN KEY (`disposal_gain_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_categories_disposal_loss_account_id_foreign` FOREIGN KEY (`disposal_loss_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_categories_impairment_account_id_foreign` FOREIGN KEY (`impairment_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_categories_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asset_categories`
--

LOCK TABLES `asset_categories` WRITE;
/*!40000 ALTER TABLE `asset_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asset_cost_components`
--

DROP TABLE IF EXISTS `asset_cost_components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_cost_components` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `asset_id` bigint(20) unsigned NOT NULL,
  `component_type` varchar(40) NOT NULL DEFAULT 'purchase',
  `amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `description` varchar(255) DEFAULT NULL,
  `reference` varchar(120) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_cost_components_institute_id_foreign` (`institute_id`),
  KEY `idx_asset_cost_components_asset` (`asset_id`),
  CONSTRAINT `asset_cost_components_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `fixed_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_cost_components_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asset_cost_components`
--

LOCK TABLES `asset_cost_components` WRITE;
/*!40000 ALTER TABLE `asset_cost_components` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_cost_components` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asset_depreciation_entries`
--

DROP TABLE IF EXISTS `asset_depreciation_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_depreciation_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `asset_id` bigint(20) unsigned NOT NULL,
  `run_id` bigint(20) unsigned DEFAULT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `opening_nbv` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `depreciation_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `accumulated_depreciation` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `closing_nbv` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `method` varchar(40) DEFAULT NULL,
  `rate` decimal(10,4) DEFAULT NULL,
  `units` decimal(19,4) DEFAULT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_depr_entries_asset_period` (`asset_id`,`period_start`),
  KEY `asset_depreciation_entries_institute_id_foreign` (`institute_id`),
  KEY `asset_depreciation_entries_run_id_foreign` (`run_id`),
  KEY `idx_asset_depr_entries_asset` (`asset_id`),
  CONSTRAINT `asset_depreciation_entries_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `fixed_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_depreciation_entries_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_depreciation_entries_run_id_foreign` FOREIGN KEY (`run_id`) REFERENCES `asset_depreciation_runs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asset_depreciation_entries`
--

LOCK TABLES `asset_depreciation_entries` WRITE;
/*!40000 ALTER TABLE `asset_depreciation_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_depreciation_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asset_depreciation_runs`
--

DROP TABLE IF EXISTS `asset_depreciation_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_depreciation_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'posted',
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_depr_runs_period` (`institute_id`,`branch_id`,`period_start`),
  KEY `asset_depreciation_runs_branch_id_foreign` (`branch_id`),
  KEY `asset_depreciation_runs_journal_id_foreign` (`journal_id`),
  CONSTRAINT `asset_depreciation_runs_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_depreciation_runs_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_depreciation_runs_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asset_depreciation_runs`
--

LOCK TABLES `asset_depreciation_runs` WRITE;
/*!40000 ALTER TABLE `asset_depreciation_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_depreciation_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asset_disposals`
--

DROP TABLE IF EXISTS `asset_disposals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_disposals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `asset_id` bigint(20) unsigned NOT NULL,
  `disposal_type` varchar(30) NOT NULL DEFAULT 'sale',
  `disposal_date` date NOT NULL,
  `sale_proceeds` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `gain_loss` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `reason` varchar(255) DEFAULT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_disposals_institute_id_foreign` (`institute_id`),
  KEY `asset_disposals_journal_id_foreign` (`journal_id`),
  KEY `idx_asset_disposals_asset` (`asset_id`),
  CONSTRAINT `asset_disposals_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `fixed_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_disposals_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_disposals_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asset_disposals`
--

LOCK TABLES `asset_disposals` WRITE;
/*!40000 ALTER TABLE `asset_disposals` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_disposals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asset_impairments`
--

DROP TABLE IF EXISTS `asset_impairments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_impairments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `asset_id` bigint(20) unsigned NOT NULL,
  `impairment_date` date NOT NULL,
  `impairment_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `recoverable_amount` decimal(19,4) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_impairments_institute_id_foreign` (`institute_id`),
  KEY `asset_impairments_journal_id_foreign` (`journal_id`),
  KEY `idx_asset_impairments_asset` (`asset_id`),
  CONSTRAINT `asset_impairments_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `fixed_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_impairments_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_impairments_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asset_impairments`
--

LOCK TABLES `asset_impairments` WRITE;
/*!40000 ALTER TABLE `asset_impairments` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_impairments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asset_locations`
--

DROP TABLE IF EXISTS `asset_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_locations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_locations_name` (`institute_id`,`branch_id`,`name`),
  KEY `asset_locations_branch_id_foreign` (`branch_id`),
  CONSTRAINT `asset_locations_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_locations_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asset_locations`
--

LOCK TABLES `asset_locations` WRITE;
/*!40000 ALTER TABLE `asset_locations` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asset_method_changes`
--

DROP TABLE IF EXISTS `asset_method_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_method_changes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `asset_id` bigint(20) unsigned NOT NULL,
  `old_method` varchar(40) DEFAULT NULL,
  `new_method` varchar(40) NOT NULL,
  `old_useful_life_months` smallint(5) unsigned DEFAULT NULL,
  `new_useful_life_months` smallint(5) unsigned DEFAULT NULL,
  `old_residual_value` decimal(19,4) DEFAULT NULL,
  `new_residual_value` decimal(19,4) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'requested',
  `effective_date` date DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_method_changes_institute_id_foreign` (`institute_id`),
  KEY `idx_asset_method_changes_asset` (`asset_id`),
  CONSTRAINT `asset_method_changes_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `fixed_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_method_changes_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asset_method_changes`
--

LOCK TABLES `asset_method_changes` WRITE;
/*!40000 ALTER TABLE `asset_method_changes` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_method_changes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asset_qr_codes`
--

DROP TABLE IF EXISTS `asset_qr_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_qr_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `asset_id` bigint(20) unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `generated_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_qr_codes_token` (`token`),
  KEY `asset_qr_codes_institute_id_foreign` (`institute_id`),
  KEY `idx_asset_qr_codes_asset` (`asset_id`),
  CONSTRAINT `asset_qr_codes_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `fixed_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_qr_codes_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asset_qr_codes`
--

LOCK TABLES `asset_qr_codes` WRITE;
/*!40000 ALTER TABLE `asset_qr_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_qr_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asset_revaluations`
--

DROP TABLE IF EXISTS `asset_revaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_revaluations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `asset_id` bigint(20) unsigned NOT NULL,
  `revaluation_date` date NOT NULL,
  `previous_carrying_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `new_carrying_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `difference` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `reason` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'requested',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_revaluations_institute_id_foreign` (`institute_id`),
  KEY `asset_revaluations_journal_id_foreign` (`journal_id`),
  KEY `idx_asset_revaluations_asset` (`asset_id`),
  CONSTRAINT `asset_revaluations_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `fixed_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_revaluations_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_revaluations_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asset_revaluations`
--

LOCK TABLES `asset_revaluations` WRITE;
/*!40000 ALTER TABLE `asset_revaluations` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_revaluations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asset_transfers`
--

DROP TABLE IF EXISTS `asset_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `asset_id` bigint(20) unsigned NOT NULL,
  `from_branch_id` bigint(20) unsigned DEFAULT NULL,
  `to_branch_id` bigint(20) unsigned DEFAULT NULL,
  `from_location_id` bigint(20) unsigned DEFAULT NULL,
  `to_location_id` bigint(20) unsigned DEFAULT NULL,
  `from_department` varchar(80) DEFAULT NULL,
  `to_department` varchar(80) DEFAULT NULL,
  `from_custodian` varchar(120) DEFAULT NULL,
  `to_custodian` varchar(120) DEFAULT NULL,
  `transfer_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_transfers_institute_id_foreign` (`institute_id`),
  KEY `asset_transfers_from_branch_id_foreign` (`from_branch_id`),
  KEY `asset_transfers_to_branch_id_foreign` (`to_branch_id`),
  KEY `asset_transfers_from_location_id_foreign` (`from_location_id`),
  KEY `asset_transfers_to_location_id_foreign` (`to_location_id`),
  KEY `idx_asset_transfers_asset` (`asset_id`),
  CONSTRAINT `asset_transfers_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `fixed_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_transfers_from_branch_id_foreign` FOREIGN KEY (`from_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_transfers_from_location_id_foreign` FOREIGN KEY (`from_location_id`) REFERENCES `asset_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_transfers_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_transfers_to_branch_id_foreign` FOREIGN KEY (`to_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_transfers_to_location_id_foreign` FOREIGN KEY (`to_location_id`) REFERENCES `asset_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asset_transfers`
--

LOCK TABLES `asset_transfers` WRITE;
/*!40000 ALTER TABLE `asset_transfers` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `class_date` date NOT NULL,
  `status` enum('present','absent','late','leave') NOT NULL DEFAULT 'present',
  `remarks` varchar(255) DEFAULT NULL,
  `marked_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance_student_date` (`batch_id`,`student_id`,`class_date`),
  KEY `idx_attendance_institute` (`institute_id`),
  KEY `idx_attendance_batch_date` (`batch_id`,`class_date`),
  KEY `idx_attendance_student` (`student_id`),
  KEY `fk_attendance_marked_by` (`marked_by`),
  CONSTRAINT `fk_attendance_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_marked_by` FOREIGN KEY (`marked_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=218 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_archive`
--

DROP TABLE IF EXISTS `attendance_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_archive` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_id` bigint(20) unsigned NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `original_created_at` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `attendance_archive_original_id_index` (`original_id`),
  KEY `attendance_archive_archived_at_index` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_archive`
--

LOCK TABLES `attendance_archive` WRITE;
/*!40000 ALTER TABLE `attendance_archive` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_archive` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `user_type` enum('platform_admin','institute_user','guardian','system') NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(60) NOT NULL,
  `record_id` bigint(20) unsigned DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_institute` (`institute_id`),
  KEY `idx_audit_logs_module_record` (`module`,`record_id`),
  KEY `idx_audit_logs_created` (`created_at`),
  CONSTRAINT `audit_logs_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=708 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs_archive`
--

DROP TABLE IF EXISTS `audit_logs_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs_archive` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_id` bigint(20) unsigned NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `original_created_at` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `audit_logs_archive_original_id_index` (`original_id`),
  KEY `audit_logs_archive_archived_at_index` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs_archive`
--

LOCK TABLES `audit_logs_archive` WRITE;
/*!40000 ALTER TABLE `audit_logs_archive` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs_archive` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_verification_logs`
--

DROP TABLE IF EXISTS `backup_verification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_verification_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `backup_id` bigint(20) unsigned DEFAULT NULL,
  `file` varchar(500) DEFAULT NULL,
  `status` enum('pending','verified','failed') NOT NULL DEFAULT 'pending',
  `report` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`report`)),
  `checksum` varchar(64) DEFAULT NULL,
  `table_count` int(10) unsigned NOT NULL DEFAULT 0,
  `row_count` int(10) unsigned NOT NULL DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `backup_verification_logs_backup_id_index` (`backup_id`),
  KEY `backup_verification_logs_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_verification_logs`
--

LOCK TABLES `backup_verification_logs` WRITE;
/*!40000 ALTER TABLE `backup_verification_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `backup_verification_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_reconciliations`
--

DROP TABLE IF EXISTS `bank_reconciliations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_reconciliations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `statement_line_id` bigint(20) unsigned NOT NULL,
  `journal_id` bigint(20) unsigned NOT NULL,
  `status` enum('matched','unmatched','ignored') NOT NULL DEFAULT 'matched',
  `matched_by` bigint(20) unsigned DEFAULT NULL,
  `matched_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_reconciliations_institute_id_foreign` (`institute_id`),
  KEY `bank_reconciliations_matched_by_foreign` (`matched_by`),
  KEY `idx_br_line_status` (`statement_line_id`,`status`),
  KEY `idx_br_journal_status` (`journal_id`,`status`),
  CONSTRAINT `bank_reconciliations_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_reconciliations_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_reconciliations_matched_by_foreign` FOREIGN KEY (`matched_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_reconciliations_statement_line_id_foreign` FOREIGN KEY (`statement_line_id`) REFERENCES `bank_statement_lines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_reconciliations`
--

LOCK TABLES `bank_reconciliations` WRITE;
/*!40000 ALTER TABLE `bank_reconciliations` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_reconciliations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_statement_lines`
--

DROP TABLE IF EXISTS `bank_statement_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_statement_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `statement_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `transaction_date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `amount` decimal(19,4) NOT NULL,
  `type` enum('deposit','withdrawal') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bsl_stmt_date` (`statement_id`,`transaction_date`),
  KEY `idx_bsl_inst_ref` (`institute_id`,`reference`),
  CONSTRAINT `bank_statement_lines_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_statement_lines_statement_id_foreign` FOREIGN KEY (`statement_id`) REFERENCES `bank_statements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_statement_lines`
--

LOCK TABLES `bank_statement_lines` WRITE;
/*!40000 ALTER TABLE `bank_statement_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_statement_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_statements`
--

DROP TABLE IF EXISTS `bank_statements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_statements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `bank_account_id` bigint(20) unsigned NOT NULL,
  `statement_date` date NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('imported','reconciled','cancelled') NOT NULL DEFAULT 'imported',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_statements_branch_id_foreign` (`branch_id`),
  KEY `bank_statements_bank_account_id_foreign` (`bank_account_id`),
  KEY `idx_bs_inst_bank_date` (`institute_id`,`bank_account_id`,`statement_date`),
  CONSTRAINT `bank_statements_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_statements_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_statements_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_statements`
--

LOCK TABLES `bank_statements` WRITE;
/*!40000 ALTER TABLE `bank_statements` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_statements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `batches`
--

DROP TABLE IF EXISTS `batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `is_test` tinyint(1) DEFAULT 0,
  `institute_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `curriculum_id` bigint(20) unsigned DEFAULT NULL,
  `academic_year_id` bigint(20) unsigned DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `teacher_id` bigint(20) unsigned DEFAULT NULL,
  `room_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `batch_code` varchar(40) NOT NULL,
  `shift` enum('morning','day','evening','weekend','online') NOT NULL DEFAULT 'day',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `seat_capacity` smallint(5) unsigned NOT NULL DEFAULT 30,
  `seat_filled` smallint(5) unsigned NOT NULL DEFAULT 0,
  `status` enum('upcoming','ongoing','completed','cancelled','archived') NOT NULL DEFAULT 'upcoming',
  `attendance_threshold` int(11) NOT NULL DEFAULT 80,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batches_institute_code` (`institute_id`,`batch_code`),
  KEY `idx_batches_institute` (`institute_id`),
  KEY `idx_batches_course` (`course_id`),
  KEY `idx_batches_teacher` (`teacher_id`),
  KEY `fk_batches_branch` (`branch_id`),
  KEY `fk_batches_room` (`room_id`),
  KEY `batches_academic_year_id_foreign` (`academic_year_id`),
  KEY `batches_institute_year_idx` (`institute_id`,`academic_year_id`),
  KEY `batches_curriculum_id_foreign` (`curriculum_id`),
  CONSTRAINT `batches_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `batches_curriculum_id_foreign` FOREIGN KEY (`curriculum_id`) REFERENCES `course_curricula` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_batches_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_batches_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_batches_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_batches_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_batches_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `batches`
--

LOCK TABLES `batches` WRITE;
/*!40000 ALTER TABLE `batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `branches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(4) NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `manager_user_id` bigint(20) unsigned DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_principal` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `branches_code_unique` (`code`),
  KEY `idx_branches_institute` (`institute_id`),
  KEY `fk_branches_manager` (`manager_user_id`),
  CONSTRAINT `fk_branches_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_branches_manager` FOREIGN KEY (`manager_user_id`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (4,'1290',89,'B1',NULL,NULL,NULL,NULL,'active',1,NULL,'2026-09-01 14:24:31','2026-09-01 14:24:31'),(5,'7393',89,'B2',NULL,NULL,NULL,NULL,'active',0,NULL,'2026-09-01 14:24:31','2026-09-01 14:24:31'),(6,'3460',91,'Br',NULL,NULL,NULL,NULL,'active',1,NULL,'2026-09-01 14:24:32','2026-09-01 14:24:32'),(7,'7001',113,'Branch 6a968bc2e502a',NULL,NULL,NULL,NULL,'active',1,NULL,'2026-09-01 14:24:34','2026-09-01 14:24:34'),(8,'9048',115,'Branch 6a968bc34d454',NULL,NULL,NULL,NULL,'active',1,NULL,'2026-09-01 14:24:35','2026-09-01 14:24:35'),(9,'2514',115,'Branch 6a968bc34fa4f',NULL,NULL,NULL,NULL,'active',0,NULL,'2026-09-01 14:24:35','2026-09-01 14:24:35'),(10,'7602',117,'Branch 6a968bc3a4754',NULL,NULL,NULL,NULL,'active',1,NULL,'2026-09-01 14:24:35','2026-09-01 14:24:35'),(11,'3761',117,'Branch 6a968bc3a6451',NULL,NULL,NULL,NULL,'active',0,NULL,'2026-09-01 14:24:35','2026-09-01 14:24:35'),(12,'0286',130,'Br',NULL,NULL,NULL,NULL,'active',1,NULL,'2026-09-01 14:24:37','2026-09-01 14:24:37'),(13,'7658',132,'B1',NULL,NULL,NULL,NULL,'active',1,NULL,'2026-09-01 14:24:38','2026-09-01 14:24:38'),(14,'1670',132,'B2',NULL,NULL,NULL,NULL,'active',0,NULL,'2026-09-01 14:24:38','2026-09-01 14:24:38'),(15,'2358',169,'B1',NULL,NULL,NULL,NULL,'active',1,NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17'),(16,'9043',169,'B2',NULL,NULL,NULL,NULL,'active',0,NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17'),(17,'2865',173,'Br',NULL,NULL,NULL,NULL,'active',1,NULL,'2026-09-01 14:36:18','2026-09-01 14:36:18'),(18,'9577',190,'Branch 6a968e8459ef6',NULL,NULL,NULL,NULL,'active',1,NULL,'2026-09-01 14:36:20','2026-09-01 14:36:20'),(19,'7291',194,'Branch 6a968e84c5f75',NULL,NULL,NULL,NULL,'active',1,NULL,'2026-09-01 14:36:20','2026-09-01 14:36:20'),(20,'4690',194,'Branch 6a968e84c8599',NULL,NULL,NULL,NULL,'active',0,NULL,'2026-09-01 14:36:20','2026-09-01 14:36:20'),(21,'5350',197,'Branch 6a968e8543b0c',NULL,NULL,NULL,NULL,'active',1,NULL,'2026-09-01 14:36:21','2026-09-01 14:36:21'),(22,'1477',197,'Branch 6a968e8545959',NULL,NULL,NULL,NULL,'active',0,NULL,'2026-09-01 14:36:21','2026-09-01 14:36:21'),(23,'0424',217,'Br',NULL,NULL,NULL,NULL,'active',1,NULL,'2026-09-01 14:36:24','2026-09-01 14:36:24'),(24,'4760',219,'B1',NULL,NULL,NULL,NULL,'active',1,NULL,'2026-09-01 14:36:25','2026-09-01 14:36:25'),(25,'6691',219,'B2',NULL,NULL,NULL,NULL,'active',0,NULL,'2026-09-01 14:36:25','2026-09-01 14:36:25');
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `budget_lines`
--

DROP TABLE IF EXISTS `budget_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `budget_version_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `coa_id` bigint(20) unsigned NOT NULL,
  `accounting_period_id` bigint(20) unsigned DEFAULT NULL,
  `month` int(10) unsigned NOT NULL COMMENT '1-12 for monthly, 0 for annual total',
  `amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budget_lines_account_month` (`budget_version_id`,`coa_id`,`month`),
  KEY `budget_lines_branch_id_foreign` (`branch_id`),
  KEY `budget_lines_accounting_period_id_foreign` (`accounting_period_id`),
  KEY `budget_lines_budget_version_id_index` (`budget_version_id`),
  KEY `budget_lines_institute_id_index` (`institute_id`),
  KEY `budget_lines_coa_id_index` (`coa_id`),
  CONSTRAINT `budget_lines_accounting_period_id_foreign` FOREIGN KEY (`accounting_period_id`) REFERENCES `accounting_periods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `budget_lines_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `budget_lines_budget_version_id_foreign` FOREIGN KEY (`budget_version_id`) REFERENCES `budget_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_lines_coa_id_foreign` FOREIGN KEY (`coa_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `budget_lines_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `budget_lines`
--

LOCK TABLES `budget_lines` WRITE;
/*!40000 ALTER TABLE `budget_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `budget_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `budget_versions`
--

DROP TABLE IF EXISTS `budget_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `budget_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `version` int(10) unsigned NOT NULL,
  `status` enum('draft','submitted','approved','rejected','locked') NOT NULL DEFAULT 'draft',
  `total_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `submitted_by` bigint(20) unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budget_versions_version` (`budget_id`,`version`),
  KEY `budget_versions_branch_id_foreign` (`branch_id`),
  KEY `budget_versions_budget_id_index` (`budget_id`),
  KEY `budget_versions_institute_id_index` (`institute_id`),
  CONSTRAINT `budget_versions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `budget_versions_budget_id_foreign` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_versions_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `budget_versions`
--

LOCK TABLES `budget_versions` WRITE;
/*!40000 ALTER TABLE `budget_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `budget_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `budgets`
--

DROP TABLE IF EXISTS `budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budgets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `fiscal_year_id` bigint(20) unsigned NOT NULL,
  `currency_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('revenue','expense','cost','asset') NOT NULL DEFAULT 'expense',
  `status` enum('draft','submitted','approved','rejected','locked') NOT NULL DEFAULT 'draft',
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `total_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `notes` text DEFAULT NULL,
  `submitted_by` bigint(20) unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint(20) unsigned DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budgets_type` (`institute_id`,`branch_id`,`fiscal_year_id`,`type`),
  KEY `budgets_branch_id_foreign` (`branch_id`),
  KEY `budgets_currency_id_foreign` (`currency_id`),
  KEY `budgets_institute_id_index` (`institute_id`),
  KEY `budgets_fiscal_year_id_index` (`fiscal_year_id`),
  KEY `budgets_status_index` (`status`),
  KEY `idx_budgets_status` (`institute_id`,`status`),
  CONSTRAINT `budgets_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `budgets_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `budgets_fiscal_year_id_foreign` FOREIGN KEY (`fiscal_year_id`) REFERENCES `fiscal_years` (`id`),
  CONSTRAINT `budgets_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `budgets`
--

LOCK TABLES `budgets` WRITE;
/*!40000 ALTER TABLE `budgets` DISABLE KEYS */;
/*!40000 ALTER TABLE `budgets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `calendar_event_reminders`
--

DROP TABLE IF EXISTS `calendar_event_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendar_event_reminders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `reminder_type` varchar(30) NOT NULL DEFAULT 'notification',
  `minutes_before` int(11) NOT NULL DEFAULT 30,
  `is_sent` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `calendar_event_reminders_event_id_index` (`event_id`),
  KEY `calendar_event_reminders_user_id_is_sent_index` (`user_id`,`is_sent`),
  CONSTRAINT `calendar_event_reminders_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `calendar_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendar_event_reminders`
--

LOCK TABLES `calendar_event_reminders` WRITE;
/*!40000 ALTER TABLE `calendar_event_reminders` DISABLE KEYS */;
/*!40000 ALTER TABLE `calendar_event_reminders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `calendar_events`
--

DROP TABLE IF EXISTS `calendar_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendar_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(60) NOT NULL DEFAULT 'class',
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_all_day` tinyint(1) NOT NULL DEFAULT 0,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `class_grade_id` bigint(20) unsigned DEFAULT NULL,
  `academic_group_id` bigint(20) unsigned DEFAULT NULL,
  `teacher_id` bigint(20) unsigned DEFAULT NULL,
  `room_id` bigint(20) unsigned DEFAULT NULL,
  `academic_year_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `recurrence_rule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recurrence_rule`)),
  `parent_event_id` bigint(20) unsigned DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `calendar_events_uuid_unique` (`uuid`),
  KEY `calendar_events_course_id_foreign` (`course_id`),
  KEY `calendar_events_subject_id_foreign` (`subject_id`),
  KEY `calendar_events_class_grade_id_foreign` (`class_grade_id`),
  KEY `calendar_events_academic_group_id_foreign` (`academic_group_id`),
  KEY `calendar_events_academic_year_id_foreign` (`academic_year_id`),
  KEY `calendar_events_parent_event_id_foreign` (`parent_event_id`),
  KEY `calendar_events_institute_id_index` (`institute_id`),
  KEY `calendar_events_institute_id_start_date_index` (`institute_id`,`start_date`),
  KEY `calendar_events_institute_id_event_type_index` (`institute_id`,`event_type`),
  KEY `calendar_events_branch_id_start_date_index` (`branch_id`,`start_date`),
  KEY `calendar_events_teacher_id_start_date_index` (`teacher_id`,`start_date`),
  KEY `calendar_events_batch_id_start_date_index` (`batch_id`,`start_date`),
  KEY `calendar_events_room_id_start_date_index` (`room_id`,`start_date`),
  CONSTRAINT `calendar_events_academic_group_id_foreign` FOREIGN KEY (`academic_group_id`) REFERENCES `academic_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calendar_events_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calendar_events_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calendar_events_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calendar_events_class_grade_id_foreign` FOREIGN KEY (`class_grade_id`) REFERENCES `class_grades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calendar_events_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calendar_events_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `calendar_events_parent_event_id_foreign` FOREIGN KEY (`parent_event_id`) REFERENCES `calendar_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calendar_events_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calendar_events_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calendar_events_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendar_events`
--

LOCK TABLES `calendar_events` WRITE;
/*!40000 ALTER TABLE `calendar_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `calendar_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_memos`
--

DROP TABLE IF EXISTS `cash_memos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_memos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `memo_number` varchar(30) NOT NULL,
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `payment_method` enum('cash','bkash','nagad','bank','other') NOT NULL DEFAULT 'cash',
  `created_by` bigint(20) unsigned NOT NULL,
  `offline_origin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cash_memos_institute_number` (`institute_id`,`memo_number`),
  KEY `idx_cash_memos_institute` (`institute_id`),
  KEY `idx_cash_memos_student` (`student_id`),
  KEY `fk_cash_memos_offline_origin` (`offline_origin_id`),
  KEY `cash_memos_journal_id_foreign` (`journal_id`),
  KEY `idx_cash_memos_party` (`party_id`),
  CONSTRAINT `cash_memos_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_memos_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cash_memos_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cash_memos_offline_origin` FOREIGN KEY (`offline_origin_id`) REFERENCES `offline_sync_queue` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cash_memos_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_memos`
--

LOCK TABLES `cash_memos` WRITE;
/*!40000 ALTER TABLE `cash_memos` DISABLE KEYS */;
/*!40000 ALTER TABLE `cash_memos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificate_types`
--

DROP TABLE IF EXISTS `certificate_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificate_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `certificate_types_institute_id_slug_unique` (`institute_id`,`slug`),
  KEY `certificate_types_institute_id_index` (`institute_id`),
  CONSTRAINT `certificate_types_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificate_types`
--

LOCK TABLES `certificate_types` WRITE;
/*!40000 ALTER TABLE `certificate_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `certificate_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificates`
--

DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `certificate_type_id` bigint(20) unsigned DEFAULT NULL,
  `template_id` tinyint(4) NOT NULL DEFAULT 1,
  `result_id` bigint(20) unsigned DEFAULT NULL,
  `certificate_number` varchar(60) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `verification_url` varchar(255) DEFAULT NULL,
  `digital_signature` text DEFAULT NULL,
  `status` enum('pending','active','rejected','revoked') NOT NULL DEFAULT 'pending',
  `revoked_reason` varchar(255) DEFAULT NULL,
  `issued_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_certificates_uuid` (`uuid`),
  UNIQUE KEY `uq_certificates_number` (`certificate_number`),
  KEY `idx_certificates_institute` (`institute_id`),
  KEY `idx_certificates_student` (`student_id`),
  KEY `fk_certificates_course` (`course_id`),
  KEY `fk_certificates_batch` (`batch_id`),
  KEY `fk_certificates_result` (`result_id`),
  KEY `fk_certificates_issued_by` (`issued_by`),
  KEY `fk_certificates_reviewed_by` (`reviewed_by`),
  KEY `certificates_certificate_type_id_index` (`certificate_type_id`),
  CONSTRAINT `certificates_certificate_type_id_foreign` FOREIGN KEY (`certificate_type_id`) REFERENCES `certificate_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_certificates_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_certificates_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_certificates_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_certificates_issued_by` FOREIGN KEY (`issued_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_certificates_result` FOREIGN KEY (`result_id`) REFERENCES `results` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_certificates_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_certificates_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificates`
--

LOCK TABLES `certificates` WRITE;
/*!40000 ALTER TABLE `certificates` DISABLE KEYS */;
/*!40000 ALTER TABLE `certificates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chart_of_accounts`
--

DROP TABLE IF EXISTS `chart_of_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chart_of_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `account_group_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('asset','liability','equity','income','expense') NOT NULL,
  `cash_flow_category` enum('operating','investing','financing') DEFAULT NULL,
  `is_cash` tinyint(1) NOT NULL DEFAULT 0,
  `is_bank` tinyint(1) NOT NULL DEFAULT 0,
  `is_receivable` tinyint(1) NOT NULL DEFAULT 0,
  `is_payable` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `legacy_head_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coa_code` (`institute_id`,`branch_id`,`code`),
  KEY `chart_of_accounts_branch_id_foreign` (`branch_id`),
  KEY `chart_of_accounts_parent_id_foreign` (`parent_id`),
  KEY `idx_coa_group` (`account_group_id`),
  KEY `idx_coa_active` (`institute_id`,`is_active`),
  KEY `idx_coa_legacy` (`legacy_head_id`),
  KEY `chart_of_accounts_currency_id_foreign` (`currency_id`),
  KEY `idx_coa_scope_type` (`institute_id`,`branch_id`,`type`),
  KEY `idx_coa_cash_flow_category` (`institute_id`,`cash_flow_category`),
  CONSTRAINT `chart_of_accounts_account_group_id_foreign` FOREIGN KEY (`account_group_id`) REFERENCES `account_groups` (`id`),
  CONSTRAINT `chart_of_accounts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chart_of_accounts_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chart_of_accounts_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chart_of_accounts_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chart_of_accounts`
--

LOCK TABLES `chart_of_accounts` WRITE;
/*!40000 ALTER TABLE `chart_of_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `chart_of_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_grades`
--

DROP TABLE IF EXISTS `class_grades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `class_grades` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_id` bigint(20) unsigned NOT NULL,
  `education_system_id` bigint(20) unsigned NOT NULL,
  `academic_level_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(60) NOT NULL,
  `sequence` int(10) unsigned DEFAULT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_grades_academic_level_id_code_unique` (`academic_level_id`,`code`),
  KEY `class_grades_education_system_id_foreign` (`education_system_id`),
  KEY `cg_country_system_level_status_idx` (`country_id`,`education_system_id`,`academic_level_id`,`status`),
  CONSTRAINT `class_grades_academic_level_id_foreign` FOREIGN KEY (`academic_level_id`) REFERENCES `academic_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_grades_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_grades_education_system_id_foreign` FOREIGN KEY (`education_system_id`) REFERENCES `education_systems` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1168 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_grades`
--

LOCK TABLES `class_grades` WRITE;
/*!40000 ALTER TABLE `class_grades` DISABLE KEYS */;
INSERT INTO `class_grades` VALUES (848,26,56,215,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(849,26,56,215,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(850,26,56,215,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(851,26,56,215,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(852,26,56,215,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(853,26,56,216,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(854,26,56,216,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(855,26,56,216,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(856,26,56,216,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(857,26,56,216,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(858,26,56,217,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(859,26,56,217,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(860,26,56,218,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(861,26,56,218,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(862,26,56,218,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(863,26,56,218,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(864,27,57,219,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(865,27,57,219,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(866,27,57,219,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(867,27,57,219,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(868,27,57,219,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(869,27,57,220,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(870,27,57,220,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(871,27,57,220,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(872,27,57,220,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(873,27,57,220,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(874,27,57,221,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(875,27,57,221,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(876,27,57,222,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(877,27,57,222,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(878,27,57,222,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(879,27,57,222,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(880,28,58,223,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(881,28,58,223,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(882,28,58,223,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(883,28,58,223,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(884,28,58,223,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(885,28,58,224,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(886,28,58,224,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(887,28,58,224,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(888,28,58,224,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(889,28,58,224,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(890,28,58,225,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(891,28,58,225,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(892,28,58,226,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(893,28,58,226,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(894,28,58,226,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(895,28,58,226,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(896,29,59,227,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(897,29,59,227,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(898,29,59,227,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(899,29,59,227,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(900,29,59,227,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(901,29,59,228,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(902,29,59,228,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(903,29,59,228,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(904,29,59,228,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(905,29,59,228,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(906,29,59,229,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(907,29,59,229,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(908,29,59,230,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(909,29,59,230,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(910,29,59,230,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(911,29,59,230,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(912,30,60,231,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(913,30,60,231,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(914,30,60,231,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(915,30,60,231,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(916,30,60,231,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(917,30,60,232,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(918,30,60,232,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(919,30,60,232,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(920,30,60,232,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(921,30,60,232,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(922,30,60,233,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(923,30,60,233,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(924,30,60,234,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(925,30,60,234,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(926,30,60,234,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(927,30,60,234,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(928,31,61,235,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(929,31,61,235,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(930,31,61,235,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(931,31,61,235,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(932,31,61,235,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(933,31,61,236,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(934,31,61,236,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(935,31,61,236,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(936,31,61,236,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(937,31,61,236,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(938,31,61,237,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(939,31,61,237,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(940,31,61,238,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(941,31,61,238,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(942,31,61,238,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(943,31,61,238,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(944,32,62,239,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(945,32,62,239,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(946,32,62,239,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(947,32,62,239,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(948,32,62,239,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(949,32,62,240,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(950,32,62,240,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(951,32,62,240,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(952,32,62,240,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(953,32,62,240,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(954,32,62,241,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(955,32,62,241,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(956,32,62,242,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(957,32,62,242,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(958,32,62,242,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(959,32,62,242,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(960,33,63,243,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(961,33,63,243,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(962,33,63,243,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(963,33,63,243,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(964,33,63,243,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(965,33,63,244,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(966,33,63,244,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(967,33,63,244,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(968,33,63,244,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(969,33,63,244,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(970,33,63,245,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(971,33,63,245,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(972,33,63,246,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(973,33,63,246,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(974,33,63,246,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(975,33,63,246,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(976,34,64,247,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(977,34,64,247,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(978,34,64,247,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(979,34,64,247,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(980,34,64,247,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(981,34,64,248,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(982,34,64,248,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(983,34,64,248,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(984,34,64,248,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(985,34,64,248,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(986,34,64,249,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(987,34,64,249,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(988,34,64,250,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(989,34,64,250,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(990,34,64,250,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(991,34,64,250,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(992,35,65,251,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(993,35,65,251,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(994,35,65,251,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(995,35,65,251,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(996,35,65,251,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(997,35,65,252,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(998,35,65,252,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(999,35,65,252,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1000,35,65,252,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1001,35,65,252,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1002,35,65,253,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1003,35,65,253,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1004,35,65,254,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1005,35,65,254,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1006,35,65,254,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1007,35,65,254,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(1008,36,66,255,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1009,36,66,255,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1010,36,66,255,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1011,36,66,255,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1012,36,66,255,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1013,36,66,256,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1014,36,66,256,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1015,36,66,256,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1016,36,66,256,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1017,36,66,256,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1018,36,66,257,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1019,36,66,257,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1020,36,66,258,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1021,36,66,258,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1022,36,66,258,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1023,36,66,258,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1024,37,67,259,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1025,37,67,259,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1026,37,67,259,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1027,37,67,259,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1028,37,67,259,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1029,37,67,260,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1030,37,67,260,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1031,37,67,260,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1032,37,67,260,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1033,37,67,260,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1034,37,67,261,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1035,37,67,261,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1036,37,67,262,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1037,37,67,262,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1038,37,67,262,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1039,37,67,262,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1040,38,68,263,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1041,38,68,263,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1042,38,68,263,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1043,38,68,263,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1044,38,68,263,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1045,38,68,264,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1046,38,68,264,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1047,38,68,264,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1048,38,68,264,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1049,38,68,264,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1050,38,68,265,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1051,38,68,265,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1052,38,68,266,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1053,38,68,266,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1054,38,68,266,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1055,38,68,266,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1056,39,69,267,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1057,39,69,267,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1058,39,69,267,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1059,39,69,267,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1060,39,69,267,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1061,39,69,268,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1062,39,69,268,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1063,39,69,268,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1064,39,69,268,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1065,39,69,268,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1066,39,69,269,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1067,39,69,269,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1068,39,69,270,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1069,39,69,270,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1070,39,69,270,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1071,39,69,270,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1072,40,70,271,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1073,40,70,271,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1074,40,70,271,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1075,40,70,271,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1076,40,70,271,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1077,40,70,272,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1078,40,70,272,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1079,40,70,272,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1080,40,70,272,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1081,40,70,272,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1082,40,70,273,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1083,40,70,273,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1084,40,70,274,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1085,40,70,274,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1086,40,70,274,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1087,40,70,274,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1088,41,71,275,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1089,41,71,275,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1090,41,71,275,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1091,41,71,275,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1092,41,71,275,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1093,41,71,276,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1094,41,71,276,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1095,41,71,276,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1096,41,71,276,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1097,41,71,276,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1098,41,71,277,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1099,41,71,277,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1100,41,71,278,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1101,41,71,278,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1102,41,71,278,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1103,41,71,278,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1104,42,72,279,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1105,42,72,279,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1106,42,72,279,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1107,42,72,279,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1108,42,72,279,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1109,42,72,280,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1110,42,72,280,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(1111,42,72,280,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1112,42,72,280,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1113,42,72,280,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1114,42,72,281,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1115,42,72,281,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1116,42,72,282,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1117,42,72,282,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1118,42,72,282,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1119,42,72,282,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1120,43,73,283,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1121,43,73,283,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1122,43,73,283,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1123,43,73,283,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1124,43,73,283,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1125,43,73,284,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1126,43,73,284,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1127,43,73,284,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1128,43,73,284,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1129,43,73,284,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1130,43,73,285,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1131,43,73,285,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1132,43,73,286,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1133,43,73,286,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1134,43,73,286,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1135,43,73,286,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1136,44,74,287,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1137,44,74,287,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1138,44,74,287,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1139,44,74,287,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1140,44,74,287,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1141,44,74,288,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1142,44,74,288,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1143,44,74,288,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1144,44,74,288,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1145,44,74,288,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1146,44,74,289,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1147,44,74,289,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1148,44,74,290,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1149,44,74,290,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1150,44,74,290,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1151,44,74,290,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1152,45,75,291,'Class 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1153,45,75,291,'Class 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1154,45,75,291,'Class 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1155,45,75,291,'Class 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1156,45,75,291,'Class 5','5',5,5,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1157,45,75,292,'Class 6','6',6,6,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1158,45,75,292,'Class 7','7',7,7,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1159,45,75,292,'Class 8','8',8,8,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1160,45,75,292,'Class 9','9',9,9,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1161,45,75,292,'Class 10','10',10,10,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1162,45,75,293,'Class 11','11',11,11,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1163,45,75,293,'Class 12','12',12,12,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1164,45,75,294,'Year 1','1',1,1,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1165,45,75,294,'Year 2','2',2,2,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1166,45,75,294,'Year 3','3',3,3,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(1167,45,75,294,'Year 4','4',4,4,1,NULL,NULL,'2026-09-01 08:23:48','2026-09-01 08:23:48');
/*!40000 ALTER TABLE `class_grades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `components`
--

DROP TABLE IF EXISTS `components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `components` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `components_institute_id_slug_unique` (`institute_id`,`slug`),
  KEY `components_country_id_foreign` (`country_id`),
  CONSTRAINT `components_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `components_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `components`
--

LOCK TABLES `components` WRITE;
/*!40000 ALTER TABLE `components` DISABLE KEYS */;
INSERT INTO `components` VALUES (1,NULL,NULL,'Written','written',NULL,1,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(2,NULL,NULL,'MCQ','mcq',NULL,2,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(3,NULL,NULL,'Practical','practical',NULL,3,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(4,NULL,NULL,'Viva','viva',NULL,4,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(5,NULL,NULL,'Attendance','attendance',NULL,5,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(6,NULL,NULL,'Assignment','assignment',NULL,6,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(7,NULL,NULL,'Project','project',NULL,7,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(8,NULL,NULL,'Presentation','presentation',NULL,8,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(9,NULL,NULL,'Class Work','class-work',NULL,9,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(10,NULL,NULL,'Lab','lab',NULL,10,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(11,NULL,NULL,'Portfolio','portfolio',NULL,11,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(12,NULL,NULL,'Other','other',NULL,12,1,'2026-09-01 08:23:48','2026-09-01 08:23:48');
/*!40000 ALTER TABLE `components` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `countries`
--

DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `countries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `iso2` char(2) NOT NULL,
  `iso3` char(3) DEFAULT NULL,
  `phone_code` varchar(10) DEFAULT NULL,
  `academic_unit_label` varchar(40) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `countries_iso2_unique` (`iso2`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `countries`
--

LOCK TABLES `countries` WRITE;
/*!40000 ALTER TABLE `countries` DISABLE KEYS */;
INSERT INTO `countries` VALUES (1,'Bangladesh','BD','BGD','880',NULL,1,'2026-08-15 07:52:27','2026-08-15 07:52:27'),(2,'United Arab Emirates','AE','ARE','971',NULL,1,'2026-08-15 13:42:51','2026-08-28 10:45:52'),(3,'Australia','AU','AUS','61',NULL,1,'2026-08-15 13:42:51','2026-08-28 10:45:42'),(4,'Canada','CA','CAN','1',NULL,1,'2026-08-15 13:42:51','2026-08-15 13:42:51'),(5,'United Kingdom','GB','GBR','44',NULL,1,'2026-08-15 13:42:51','2026-08-15 13:42:51'),(6,'India','IN','IND','91',NULL,1,'2026-08-15 13:42:51','2026-08-15 13:42:51'),(7,'Kuwait','KW','KWT','965',NULL,1,'2026-08-15 13:42:51','2026-08-15 13:42:51'),(8,'Qatar','QA','QAT','974',NULL,1,'2026-08-15 13:42:51','2026-08-15 13:42:51'),(9,'Singapore','SG','SGP','65',NULL,1,'2026-08-15 13:42:51','2026-08-15 13:42:51'),(10,'United States','US','USA','1',NULL,1,'2026-08-15 13:42:51','2026-08-15 13:42:51'),(11,'Malaysia','MY','MYS','60',NULL,1,'2026-08-15 13:52:10','2026-08-15 13:52:10'),(12,'Saudi Arabia','SA','SAU','966',NULL,1,'2026-08-15 13:52:10','2026-08-15 13:52:10'),(13,'Italy','IT','ITA','39',NULL,1,'2026-08-28 10:45:44','2026-08-28 10:45:44'),(14,'Spain','ES','ESP','34',NULL,1,'2026-08-28 10:45:44','2026-08-28 10:45:44'),(15,'France','FR','FRA','33',NULL,1,'2026-08-28 10:45:45','2026-08-28 10:45:45'),(16,'Germany','DE','DEU','49',NULL,1,'2026-08-28 10:45:45','2026-08-28 10:45:45'),(17,'Portugal','PT','PRT','351',NULL,1,'2026-08-28 10:45:45','2026-08-28 10:45:45'),(18,'Pakistan','PK','PAK','92',NULL,1,'2026-08-28 10:45:45','2026-08-28 10:45:45'),(19,'New Zealand','NZ','NZL','64',NULL,1,'2026-08-28 10:45:45','2026-08-28 10:45:45'),(20,'Myanmar','MM','MMR','95',NULL,1,'2026-08-28 10:45:45','2026-08-28 10:45:45'),(21,'Vietnam','VN','VNM','84',NULL,1,'2026-08-28 10:45:46','2026-08-28 10:45:46'),(22,'Laos','LA','LAO','856',NULL,1,'2026-08-28 10:45:46','2026-08-28 10:45:46'),(23,'Cambodia','KH','KHM','855',NULL,1,'2026-08-28 10:45:46','2026-08-28 10:45:46'),(24,'Maldives','MV','MDV','960',NULL,1,'2026-08-28 10:45:46','2026-08-28 10:45:46'),(25,'TestCountry','ZZ','ZZZ','999',NULL,1,'2026-08-28 11:35:05','2026-08-28 11:35:05');
/*!40000 ALTER TABLE `countries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `country_pass_mark_defaults`
--

DROP TABLE IF EXISTS `country_pass_mark_defaults`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `country_pass_mark_defaults` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_code` varchar(10) NOT NULL,
  `component_type` varchar(40) NOT NULL DEFAULT 'default',
  `component_name` varchar(120) DEFAULT NULL,
  `pass_percentage` decimal(5,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cpm_country_component_unique` (`country_code`,`component_type`,`component_name`),
  KEY `country_pass_mark_defaults_country_code_index` (`country_code`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `country_pass_mark_defaults`
--

LOCK TABLES `country_pass_mark_defaults` WRITE;
/*!40000 ALTER TABLE `country_pass_mark_defaults` DISABLE KEYS */;
/*!40000 ALTER TABLE `country_pass_mark_defaults` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_categories`
--

DROP TABLE IF EXISTS `course_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `subject_type` enum('professional','academic') NOT NULL DEFAULT 'professional',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_categories_inst_slug` (`institute_id`,`slug`),
  KEY `idx_course_categories_institute` (`institute_id`),
  CONSTRAINT `fk_course_categories_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_categories`
--

LOCK TABLES `course_categories` WRITE;
/*!40000 ALTER TABLE `course_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `course_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_curricula`
--

DROP TABLE IF EXISTS `course_curricula`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_curricula` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `effective_date` date DEFAULT NULL,
  `status` enum('draft','active','archived') NOT NULL DEFAULT 'draft',
  `description` text DEFAULT NULL,
  `total_duration_hours` decimal(8,2) DEFAULT NULL,
  `total_classes` int(10) unsigned DEFAULT NULL,
  `learning_objectives` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`learning_objectives`)),
  `version_notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_curricula_institute_course_version` (`institute_id`,`course_id`,`version`),
  KEY `course_curricula_course_id_foreign` (`course_id`),
  KEY `course_curricula_created_by_foreign` (`created_by`),
  KEY `course_curricula_updated_by_foreign` (`updated_by`),
  KEY `idx_curricula_institute_course_status` (`institute_id`,`course_id`,`status`),
  CONSTRAINT `course_curricula_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_curricula_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `course_curricula_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_curricula_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_curricula`
--

LOCK TABLES `course_curricula` WRITE;
/*!40000 ALTER TABLE `course_curricula` DISABLE KEYS */;
/*!40000 ALTER TABLE `course_curricula` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_materials`
--

DROP TABLE IF EXISTS `course_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `curriculum_module_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `course_materials_institute_id_foreign` (`institute_id`),
  KEY `course_materials_uploaded_by_foreign` (`uploaded_by`),
  KEY `idx_course_materials_course` (`course_id`),
  KEY `idx_course_materials_module` (`curriculum_module_id`),
  CONSTRAINT `course_materials_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_materials_curriculum_module_id_foreign` FOREIGN KEY (`curriculum_module_id`) REFERENCES `curriculum_modules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `course_materials_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_materials_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_materials`
--

LOCK TABLES `course_materials` WRITE;
/*!40000 ALTER TABLE `course_materials` DISABLE KEYS */;
/*!40000 ALTER TABLE `course_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_requests`
--

DROP TABLE IF EXISTS `course_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `requested_by` bigint(20) unsigned NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `review_note` varchar(500) DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_requests_institute_course` (`institute_id`,`course_id`),
  KEY `idx_course_requests_status` (`status`),
  KEY `idx_course_requests_course` (`course_id`),
  KEY `idx_course_requests_requested_by` (`requested_by`),
  KEY `idx_course_requests_reviewed_by` (`reviewed_by`),
  CONSTRAINT `fk_course_requests_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_course_requests_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_course_requests_requested` FOREIGN KEY (`requested_by`) REFERENCES `institute_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_course_requests_reviewed` FOREIGN KEY (`reviewed_by`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=180 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_requests`
--

LOCK TABLES `course_requests` WRITE;
/*!40000 ALTER TABLE `course_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `course_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_sub_categories`
--

DROP TABLE IF EXISTS `course_sub_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_sub_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_subcat_cat_slug` (`category_id`,`slug`),
  KEY `idx_course_subcat_institute` (`institute_id`),
  CONSTRAINT `fk_course_subcat_category` FOREIGN KEY (`category_id`) REFERENCES `course_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_course_subcat_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_sub_categories`
--

LOCK TABLES `course_sub_categories` WRITE;
/*!40000 ALTER TABLE `course_sub_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `course_sub_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_subjects`
--

DROP TABLE IF EXISTS `course_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `assigned_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_subject` (`course_id`,`subject_id`),
  KEY `idx_course_subjects_course` (`course_id`),
  KEY `idx_course_subjects_subject` (`subject_id`),
  KEY `fk_course_subjects_assigned_by` (`assigned_by`),
  CONSTRAINT `course_subjects_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `fk_course_subjects_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_course_subjects_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_course_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_subjects`
--

LOCK TABLES `course_subjects` WRITE;
/*!40000 ALTER TABLE `course_subjects` DISABLE KEYS */;
/*!40000 ALTER TABLE `course_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `is_test` tinyint(1) DEFAULT 0,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `sub_category_id` bigint(20) unsigned DEFAULT NULL,
  `course_code` varchar(40) NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(200) DEFAULT NULL,
  `short_name` varchar(60) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `modules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`modules`)),
  `level` enum('basic','intermediate','advanced') NOT NULL DEFAULT 'basic',
  `language` varchar(30) DEFAULT NULL,
  `duration_type` enum('hours','days','weeks','months','years') NOT NULL DEFAULT 'months',
  `duration_value` decimal(6,2) NOT NULL DEFAULT 0.00,
  `weekly_classes` tinyint(3) unsigned DEFAULT NULL,
  `class_duration_minutes` smallint(5) unsigned DEFAULT NULL,
  `total_classes` smallint(5) unsigned DEFAULT NULL,
  `total_hours` decimal(6,2) DEFAULT NULL,
  `mode` enum('offline','online','hybrid') NOT NULL DEFAULT 'offline',
  `batch_capacity_default` smallint(5) unsigned NOT NULL DEFAULT 30,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `admission_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `exam_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `certificate_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `thumbnail` varchar(255) DEFAULT NULL,
  `banner` varchar(255) DEFAULT NULL,
  `intro_video` varchar(500) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `meta_title` varchar(200) DEFAULT NULL,
  `meta_description` varchar(500) DEFAULT NULL,
  `meta_keywords` varchar(500) DEFAULT NULL,
  `requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requirements`)),
  `outcomes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`outcomes`)),
  `prerequisites` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`prerequisites`)),
  `status` enum('active','inactive','draft') NOT NULL DEFAULT 'draft',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_courses_institute_code` (`institute_id`,`course_code`),
  UNIQUE KEY `uq_courses_slug` (`slug`),
  KEY `idx_courses_institute` (`institute_id`),
  KEY `idx_courses_institute_status_featured` (`institute_id`,`status`,`is_featured`),
  KEY `idx_courses_category` (`category_id`),
  KEY `fk_courses_subcategory` (`sub_category_id`),
  FULLTEXT KEY `ft_courses_name_desc` (`name`,`description`),
  CONSTRAINT `fk_courses_category` FOREIGN KEY (`category_id`) REFERENCES `course_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_courses_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_courses_subcategory` FOREIGN KEY (`sub_category_id`) REFERENCES `course_sub_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=168 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_activities`
--

DROP TABLE IF EXISTS `crm_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `subject_type` varchar(40) NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `type` varchar(40) NOT NULL DEFAULT 'note',
  `summary` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `activity_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `assigned_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_activities_created_by_foreign` (`created_by`),
  KEY `crm_activity_institute_branch_idx` (`institute_id`,`branch_id`),
  KEY `crm_activity_branch_idx` (`branch_id`),
  KEY `crm_activity_subject_idx` (`subject_type`,`subject_id`),
  KEY `crm_activity_at_idx` (`activity_at`),
  KEY `crm_activity_assigned_idx` (`assigned_user_id`),
  KEY `crm_activity_type_idx` (`type`),
  CONSTRAINT `crm_activities_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_activities_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_activities_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_activities_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_activities`
--

LOCK TABLES `crm_activities` WRITE;
/*!40000 ALTER TABLE `crm_activities` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_contact_types`
--

DROP TABLE IF EXISTS `crm_contact_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_contact_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `crm_contact_types_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_contact_types`
--

LOCK TABLES `crm_contact_types` WRITE;
/*!40000 ALTER TABLE `crm_contact_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_contact_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_contacts`
--

DROP TABLE IF EXISTS `crm_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `contact_type_id` bigint(20) unsigned DEFAULT NULL,
  `salutation` varchar(20) DEFAULT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `phone_alt` varchar(30) DEFAULT NULL,
  `whatsapp` varchar(30) DEFAULT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `is_customer` tinyint(1) NOT NULL DEFAULT 0,
  `is_prospect` tinyint(1) NOT NULL DEFAULT 0,
  `customer_since` date DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_user_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_contacts_country_id_foreign` (`country_id`),
  KEY `crm_contacts_source_id_foreign` (`source_id`),
  KEY `crm_contacts_created_by_foreign` (`created_by`),
  KEY `crm_contacts_updated_by_foreign` (`updated_by`),
  KEY `crm_contact_institute_branch_idx` (`institute_id`,`branch_id`),
  KEY `crm_contact_branch_idx` (`branch_id`),
  KEY `crm_contact_type_idx` (`contact_type_id`),
  KEY `crm_contact_org_idx` (`organization_id`),
  KEY `crm_contact_assigned_idx` (`assigned_user_id`),
  KEY `crm_contact_email_idx` (`email`),
  KEY `crm_contact_status_idx` (`status`),
  KEY `crm_contact_first_name_idx` (`first_name`),
  KEY `crm_contact_last_name_idx` (`last_name`),
  CONSTRAINT `crm_contacts_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_contacts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_contacts_contact_type_id_foreign` FOREIGN KEY (`contact_type_id`) REFERENCES `crm_contact_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_contacts_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_contacts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_contacts_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `crm_contacts_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `crm_organizations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_contacts_source_id_foreign` FOREIGN KEY (`source_id`) REFERENCES `crm_lead_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_contacts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_contacts`
--

LOCK TABLES `crm_contacts` WRITE;
/*!40000 ALTER TABLE `crm_contacts` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_lead_sources`
--

DROP TABLE IF EXISTS `crm_lead_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_lead_sources` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL,
  `name` varchar(120) NOT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `crm_lead_sources_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_lead_sources`
--

LOCK TABLES `crm_lead_sources` WRITE;
/*!40000 ALTER TABLE `crm_lead_sources` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_lead_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_lead_statuses`
--

DROP TABLE IF EXISTS `crm_lead_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_lead_statuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL,
  `name` varchar(120) NOT NULL,
  `color` varchar(20) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `crm_lead_statuses_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_lead_statuses`
--

LOCK TABLES `crm_lead_statuses` WRITE;
/*!40000 ALTER TABLE `crm_lead_statuses` DISABLE KEYS */;
INSERT INTO `crm_lead_statuses` (`slug`, `name`, `color`, `is_default`, `display_order`, `status`, `created_at`, `updated_at`) VALUES
('new','New','#0D6EFD',1,1,'active','2026-08-28 10:35:00','2026-08-28 10:35:00'),
('contacted','Contacted','#6C757D',0,2,'active','2026-08-28 10:35:00','2026-08-28 10:35:00'),
('qualified','Qualified','#198754',0,3,'active','2026-08-28 10:35:00','2026-08-28 10:35:00'),
('proposal','Proposal Sent','#FD7E14',0,4,'active','2026-08-28 10:35:00','2026-08-28 10:35:00'),
('negotiation','In Negotiation','#6F42C1',0,5,'active','2026-08-28 10:35:00','2026-08-28 10:35:00'),
('converted','Converted','#198754',0,6,'active','2026-08-28 10:35:00','2026-08-28 10:35:00');
/*!40000 ALTER TABLE `crm_lead_statuses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_leads`
--

DROP TABLE IF EXISTS `crm_leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_leads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `status_id` bigint(20) unsigned DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `contact_id` bigint(20) unsigned DEFAULT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `interest_summary` text DEFAULT NULL,
  `value_amount` decimal(14,2) DEFAULT NULL,
  `assigned_user_id` bigint(20) unsigned DEFAULT NULL,
  `converted_at` datetime DEFAULT NULL,
  `converted_contact_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_leads_organization_id_foreign` (`organization_id`),
  KEY `crm_leads_converted_contact_id_foreign` (`converted_contact_id`),
  KEY `crm_leads_created_by_foreign` (`created_by`),
  KEY `crm_leads_updated_by_foreign` (`updated_by`),
  KEY `crm_lead_institute_branch_idx` (`institute_id`,`branch_id`),
  KEY `crm_lead_branch_idx` (`branch_id`),
  KEY `crm_lead_status_idx` (`status_id`),
  KEY `crm_lead_source_idx` (`source_id`),
  KEY `crm_lead_assigned_idx` (`assigned_user_id`),
  KEY `crm_lead_email_idx` (`email`),
  KEY `crm_lead_contact_idx` (`contact_id`),
  CONSTRAINT `crm_leads_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_leads_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_leads_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `crm_contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_leads_converted_contact_id_foreign` FOREIGN KEY (`converted_contact_id`) REFERENCES `crm_contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_leads_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_leads_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `crm_leads_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `crm_organizations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_leads_source_id_foreign` FOREIGN KEY (`source_id`) REFERENCES `crm_lead_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_leads_status_id_foreign` FOREIGN KEY (`status_id`) REFERENCES `crm_lead_statuses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_leads_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_leads`
--

LOCK TABLES `crm_leads` WRITE;
/*!40000 ALTER TABLE `crm_leads` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_leads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_notes`
--

DROP TABLE IF EXISTS `crm_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `subject_type` varchar(40) NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `body` text NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_notes_created_by_foreign` (`created_by`),
  KEY `crm_note_institute_branch_idx` (`institute_id`,`branch_id`),
  KEY `crm_note_branch_idx` (`branch_id`),
  KEY `crm_note_subject_idx` (`subject_type`,`subject_id`),
  CONSTRAINT `crm_notes_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_notes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_notes_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_notes`
--

LOCK TABLES `crm_notes` WRITE;
/*!40000 ALTER TABLE `crm_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_organizations`
--

DROP TABLE IF EXISTS `crm_organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_organizations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `website` varchar(191) DEFAULT NULL,
  `industry` varchar(120) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `is_customer` tinyint(1) NOT NULL DEFAULT 0,
  `is_prospect` tinyint(1) NOT NULL DEFAULT 0,
  `customer_since` date DEFAULT NULL,
  `assigned_user_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_organizations_country_id_foreign` (`country_id`),
  KEY `crm_organizations_created_by_foreign` (`created_by`),
  KEY `crm_organizations_updated_by_foreign` (`updated_by`),
  KEY `crm_org_institute_branch_idx` (`institute_id`,`branch_id`),
  KEY `crm_org_branch_idx` (`branch_id`),
  KEY `crm_org_assigned_idx` (`assigned_user_id`),
  KEY `crm_org_email_idx` (`email`),
  KEY `crm_org_status_idx` (`status`),
  KEY `crm_org_name_idx` (`name`),
  CONSTRAINT `crm_organizations_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_organizations_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_organizations_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_organizations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_organizations_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `crm_organizations_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_organizations`
--

LOCK TABLES `crm_organizations` WRITE;
/*!40000 ALTER TABLE `crm_organizations` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_organizations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_tasks`
--

DROP TABLE IF EXISTS `crm_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `subject_type` varchar(40) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` varchar(20) NOT NULL DEFAULT 'normal',
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `due_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `assigned_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_tasks_created_by_foreign` (`created_by`),
  KEY `crm_task_institute_branch_idx` (`institute_id`,`branch_id`),
  KEY `crm_task_branch_idx` (`branch_id`),
  KEY `crm_task_subject_idx` (`subject_type`,`subject_id`),
  KEY `crm_task_status_idx` (`status`),
  KEY `crm_task_assigned_idx` (`assigned_user_id`),
  KEY `crm_task_due_idx` (`due_at`),
  CONSTRAINT `crm_tasks_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_tasks_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_tasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_tasks_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_tasks`
--

LOCK TABLES `crm_tasks` WRITE;
/*!40000 ALTER TABLE `crm_tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `currencies`
--

DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `currencies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` char(3) NOT NULL,
  `name` varchar(100) NOT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `decimal_places` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `is_base` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_currencies_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `currencies`
--

LOCK TABLES `currencies` WRITE;
/*!40000 ALTER TABLE `currencies` DISABLE KEYS */;
INSERT INTO `currencies` (`code`, `name`, `symbol`, `decimal_places`, `is_base`, `is_active`, `created_at`, `updated_at`) VALUES
('BDT','Bangladeshi Taka','৳',2,0,1,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
('USD','US Dollar','$',2,0,1,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
('INR','Indian Rupee','₹',2,0,1,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
('PKR','Pakistani Rupee','₨',2,0,1,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
('EUR','Euro','€',2,0,1,'2026-08-28 10:35:00','2026-08-28 10:35:00');
/*!40000 ALTER TABLE `currencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curriculum_lessons`
--

DROP TABLE IF EXISTS `curriculum_lessons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `curriculum_lessons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `curriculum_module_id` bigint(20) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(10) unsigned DEFAULT NULL,
  `learning_objective` text DEFAULT NULL,
  `content_reference` varchar(500) DEFAULT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_lessons_institute_id_foreign` (`institute_id`),
  KEY `idx_curriculum_lessons_order` (`curriculum_module_id`,`display_order`),
  CONSTRAINT `curriculum_lessons_curriculum_module_id_foreign` FOREIGN KEY (`curriculum_module_id`) REFERENCES `curriculum_modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `curriculum_lessons_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curriculum_lessons`
--

LOCK TABLES `curriculum_lessons` WRITE;
/*!40000 ALTER TABLE `curriculum_lessons` DISABLE KEYS */;
/*!40000 ALTER TABLE `curriculum_lessons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curriculum_modules`
--

DROP TABLE IF EXISTS `curriculum_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `curriculum_modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `curriculum_id` bigint(20) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `module_type` varchar(40) DEFAULT NULL,
  `theory_marks` decimal(6,2) DEFAULT NULL,
  `practical_marks` decimal(6,2) DEFAULT NULL,
  `viva_marks` decimal(6,2) DEFAULT NULL,
  `total_marks` decimal(6,2) DEFAULT NULL,
  `credit_hours` decimal(5,2) DEFAULT NULL,
  `class_count` int(10) unsigned DEFAULT NULL,
  `duration_hours` decimal(6,2) DEFAULT NULL,
  `is_optional` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_modules_institute_id_foreign` (`institute_id`),
  KEY `idx_curriculum_modules_order` (`curriculum_id`,`display_order`),
  CONSTRAINT `curriculum_modules_curriculum_id_foreign` FOREIGN KEY (`curriculum_id`) REFERENCES `course_curricula` (`id`) ON DELETE CASCADE,
  CONSTRAINT `curriculum_modules_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curriculum_modules`
--

LOCK TABLES `curriculum_modules` WRITE;
/*!40000 ALTER TABLE `curriculum_modules` DISABLE KEYS */;
/*!40000 ALTER TABLE `curriculum_modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_groups`
--

DROP TABLE IF EXISTS `customer_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `discount_rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_groups_name` (`institute_id`,`branch_id`,`name`),
  KEY `customer_groups_branch_id_foreign` (`branch_id`),
  CONSTRAINT `customer_groups_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_groups_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_groups`
--

LOCK TABLES `customer_groups` WRITE;
/*!40000 ALTER TABLE `customer_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `customer_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `database_alerts`
--

DROP TABLE IF EXISTS `database_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `database_alerts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `message` varchar(500) NOT NULL,
  `severity` enum('warning','critical') NOT NULL DEFAULT 'warning',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `alerted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `database_alerts_type_created_at_index` (`type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `database_alerts`
--

LOCK TABLES `database_alerts` WRITE;
/*!40000 ALTER TABLE `database_alerts` DISABLE KEYS */;
/*!40000 ALTER TABLE `database_alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `database_query_logs`
--

DROP TABLE IF EXISTS `database_query_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `database_query_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `query` text NOT NULL,
  `execution_time` decimal(10,2) NOT NULL DEFAULT 0.00,
  `connection` varchar(50) NOT NULL DEFAULT 'mysql',
  `status` varchar(20) NOT NULL DEFAULT 'success',
  `error` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `database_query_logs_created_at_index` (`created_at`),
  KEY `database_query_logs_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `database_query_logs`
--

LOCK TABLES `database_query_logs` WRITE;
/*!40000 ALTER TABLE `database_query_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `database_query_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_categories`
--

DROP TABLE IF EXISTS `document_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `code` varchar(60) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `lifecycle_stage` varchar(60) DEFAULT NULL,
  `allowed_file_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_file_types`)),
  `max_file_size_kb` int(10) unsigned DEFAULT NULL,
  `expiry_applicable` tinyint(1) NOT NULL DEFAULT 0,
  `verification_required` tinyint(1) NOT NULL DEFAULT 0,
  `entity_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`entity_types`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_document_categories_institute` (`institute_id`),
  KEY `idx_document_categories_active_order` (`is_active`,`sort_order`),
  CONSTRAINT `document_categories_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_categories`
--

LOCK TABLES `document_categories` WRITE;
/*!40000 ALTER TABLE `document_categories` DISABLE KEYS */;
INSERT INTO `document_categories` (`id`, `institute_id`, `name`, `slug`, `code`, `description`, `is_required`, `lifecycle_stage`, `allowed_file_types`, `max_file_size_kb`, `expiry_applicable`, `verification_required`, `entity_types`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1,NULL,'Academic Transcripts','academic-transcripts','AT','Official academic transcripts and grade reports',1,'enrollment',NULL,NULL,0,0,'["student"]',1,1,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(2,NULL,'Identity Documents','identity-documents','ID','Government-issued identity documents',1,'admission',NULL,NULL,1,1,'["student"]',1,2,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(3,NULL,'Certificates','certificates','CERT','Completion and achievement certificates',0,'graduation',NULL,NULL,0,0,'["student","institute"]',1,3,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(4,NULL,'Financial Documents','financial-documents','FIN','Fee receipts and financial records',0,'finance',NULL,NULL,0,0,'["student","institute"]',1,4,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(5,NULL,'Medical Records','medical-records','MED','Health and medical documentation',0,'admission',NULL,NULL,0,1,'["student"]',1,5,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(6,NULL,'Staff Credentials','staff-credentials','STF','Teacher and staff qualification documents',1,'employment',NULL,NULL,1,1,'["institute_user"]',1,6,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(7,NULL,'Admission Forms','admission-forms','ADM','Application and admission forms',1,'admission',NULL,NULL,0,0,'["student"]',1,7,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(8,NULL,'Transfer Certificates','transfer-certificates','TC','School leaving and transfer certificates',0,'graduation',NULL,NULL,0,1,'["student"]',1,8,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(9,NULL,'Recommendation Letters','recommendation-letters','REC','Letters of recommendation',0,'graduation',NULL,NULL,0,0,'["student"]',1,9,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(10,NULL,'Institute Registration','institute-registration','REG','Institute registration and license documents',1,'setup',NULL,NULL,1,1,'["institute"]',1,10,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(11,NULL,'Government Permits','government-permits','GOV','Government permits and approvals',1,'setup',NULL,NULL,1,1,'["institute"]',1,11,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(12,NULL,'Curriculum Documents','curriculum-documents','CUR','Syllabus and curriculum guides',0,'academic',NULL,NULL,0,0,'["institute"]',1,12,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(13,NULL,'Exam Papers','exam-papers','EXM','Previous exam papers and question banks',0,'academic',NULL,NULL,0,0,'["institute"]',1,13,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(14,NULL,'Report Cards','report-cards','RPT','Student report cards and progress reports',0,'academic',NULL,NULL,0,0,'["student"]',1,14,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(15,NULL,'Attendance Records','attendance-records','ATT','Student and staff attendance logs',0,'academic',NULL,NULL,0,0,'["student","institute_user"]',1,15,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(16,NULL,'Discipline Records','discipline-records','DISC','Behavioral and disciplinary records',0,'ongoing',NULL,NULL,0,1,'["student"]',1,16,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(17,NULL,'Parent Consent Forms','parent-consent-forms','PCF','Parental consent and authorization forms',1,'admission',NULL,NULL,0,1,'["student"]',1,17,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(18,NULL,'Transport Documents','transport-documents','TRN','Transport route and safety documents',0,'setup',NULL,NULL,0,0,'["institute"]',1,18,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(19,NULL,'Insurance Documents','insurance-documents','INS','Student and staff insurance records',0,'enrollment',NULL,NULL,1,0,'["student","institute_user"]',1,19,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(20,NULL,'Legacy Records','legacy-records','LEG','Archived and legacy documents',0,'archive',NULL,NULL,0,0,'["student","institute"]',1,20,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(21,NULL,'Staff Contracts','staff-contracts','SC','Employment contracts and agreements',1,'employment',NULL,NULL,1,1,'["institute_user"]',1,21,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(22,NULL,'Payroll Records','payroll-records','PAY','Salary and compensation records',0,'finance',NULL,NULL,1,1,'["institute_user"]',1,22,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(23,NULL,'Quality Assurance','quality-assurance','QA','Quality assessment and accreditation documents',0,'academic',NULL,NULL,0,1,'["institute"]',1,23,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
(24,NULL,'Miscellaneous','miscellaneous','MISC','Other supporting documents',0,NULL,NULL,NULL,0,0,'["student","institute","institute_user"]',1,24,'2026-08-28 10:35:00','2026-08-28 10:35:00');
/*!40000 ALTER TABLE `document_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_versions`
--

DROP TABLE IF EXISTS `document_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint(20) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `disk` varchar(50) NOT NULL DEFAULT 'public',
  `mime_type` varchar(120) DEFAULT NULL,
  `extension` varchar(20) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `checksum` varchar(64) DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_versions_uploaded_by_foreign` (`uploaded_by`),
  KEY `idx_document_versions_doc_ver` (`document_id`,`version`),
  CONSTRAINT `document_versions_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_versions_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_versions`
--

LOCK TABLES `document_versions` WRITE;
/*!40000 ALTER TABLE `document_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `documentable_type` varchar(120) NOT NULL,
  `documentable_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `document_number` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `disk` varchar(50) NOT NULL DEFAULT 'public',
  `mime_type` varchar(120) DEFAULT NULL,
  `extension` varchar(20) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `checksum` varchar(64) DEFAULT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  `verification_status` varchar(40) NOT NULL DEFAULT 'pending_verification',
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'uploaded',
  `placement_id` bigint(20) unsigned DEFAULT NULL,
  `enrollment_id` bigint(20) unsigned DEFAULT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `documents_uploaded_by_foreign` (`uploaded_by`),
  KEY `idx_documents_institute` (`institute_id`),
  KEY `idx_documents_branch` (`branch_id`),
  KEY `idx_documents_category` (`category_id`),
  KEY `idx_documents_documentable` (`documentable_type`,`documentable_id`),
  KEY `idx_documents_status` (`status`),
  KEY `documents_verified_by_foreign` (`verified_by`),
  KEY `idx_documents_verification` (`institute_id`,`verification_status`),
  KEY `idx_documents_expiry` (`institute_id`,`expiry_date`),
  KEY `idx_documents_entity_category` (`documentable_type`,`documentable_id`,`category_id`),
  KEY `idx_documents_number` (`institute_id`,`document_number`),
  CONSTRAINT `documents_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `documents_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `documents_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `documents_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `education_systems`
--

DROP TABLE IF EXISTS `education_systems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `education_systems` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(60) NOT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `education_systems_country_id_code_unique` (`country_id`,`code`),
  KEY `education_systems_country_id_status_index` (`country_id`,`status`),
  CONSTRAINT `education_systems_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `education_systems`
--

LOCK TABLES `education_systems` WRITE;
/*!40000 ALTER TABLE `education_systems` DISABLE KEYS */;
INSERT INTO `education_systems` VALUES (1,1,'General Education','general',1,1,NULL,'2026-08-28 10:35:38','2026-08-28 10:36:10'),(2,1,'Madrasa Education','madrasa',2,1,NULL,'2026-08-28 10:35:38','2026-08-28 10:36:10'),(3,1,'Technical Education','technical',3,1,NULL,'2026-08-28 10:35:38','2026-08-28 10:36:10'),(4,10,'Public Education','public',1,1,NULL,'2026-08-28 10:35:39','2026-08-28 10:36:11'),(5,10,'Private Education','private',2,1,NULL,'2026-08-28 10:35:39','2026-08-28 10:36:11'),(6,10,'Homeschool','homeschool',3,1,NULL,'2026-08-28 10:35:39','2026-08-28 10:36:11'),(7,5,'National Curriculum','national',1,1,NULL,'2026-08-28 10:35:39','2026-08-28 10:36:11'),(8,5,'Private School','private',2,1,NULL,'2026-08-28 10:35:39','2026-08-28 10:36:11'),(9,5,'International Baccalaureate','ib',3,1,NULL,'2026-08-28 10:35:39','2026-08-28 10:36:11'),(12,6,'India National Education System','national',1,1,NULL,'2026-08-28 10:45:43','2026-08-28 10:50:05'),(13,9,'Singapore National Education System','national',1,1,NULL,'2026-08-28 10:45:44','2026-08-28 10:50:05'),(14,11,'Malaysia National Education System','national',1,1,NULL,'2026-08-28 10:45:44','2026-08-28 10:50:05'),(15,7,'Kuwait National Education System','national',1,1,NULL,'2026-08-28 10:45:44','2026-08-28 10:50:05'),(16,8,'Qatar National Education System','national',1,1,NULL,'2026-08-28 10:45:44','2026-08-28 10:50:05'),(17,12,'Saudi Arabia National Education System','national',1,1,NULL,'2026-08-28 10:45:44','2026-08-28 10:50:05'),(18,13,'Italy National Education System','national',1,1,NULL,'2026-08-28 10:45:44','2026-08-28 10:50:05'),(19,14,'Spain National Education System','national',1,1,NULL,'2026-08-28 10:45:44','2026-08-28 10:50:06'),(20,15,'France National Education System','national',1,1,NULL,'2026-08-28 10:45:45','2026-08-28 10:50:06'),(21,16,'Germany National Education System','national',1,1,NULL,'2026-08-28 10:45:45','2026-08-28 10:50:06'),(22,17,'Portugal National Education System','national',1,1,NULL,'2026-08-28 10:45:45','2026-08-28 10:50:06'),(23,18,'Pakistan National Education System','national',1,1,NULL,'2026-08-28 10:45:45','2026-08-28 10:50:06'),(24,3,'Australia National Education System','national',1,1,NULL,'2026-08-28 10:45:45','2026-08-28 10:50:06'),(25,4,'Canada National Education System','national',1,1,NULL,'2026-08-28 10:45:45','2026-08-28 10:50:06'),(26,19,'New Zealand National Education System','national',1,1,NULL,'2026-08-28 10:45:45','2026-08-28 10:50:07'),(27,20,'Myanmar National Education System','national',1,1,NULL,'2026-08-28 10:45:45','2026-08-28 10:50:07'),(28,21,'Vietnam National Education System','national',1,1,NULL,'2026-08-28 10:45:46','2026-08-28 10:50:07'),(29,22,'Laos National Education System','national',1,1,NULL,'2026-08-28 10:45:46','2026-08-28 10:50:07'),(30,23,'Cambodia National Education System','national',1,1,NULL,'2026-08-28 10:45:46','2026-08-28 10:50:07'),(31,24,'Maldives National Education System','national',1,1,NULL,'2026-08-28 10:45:46','2026-08-28 10:50:07'),(32,6,'Indian Education System','in_national',1,1,NULL,'2026-08-28 10:53:38','2026-08-28 10:55:07'),(33,9,'Singapore Education System','sg_national',1,1,NULL,'2026-08-28 10:53:38','2026-08-28 10:55:07'),(34,11,'Malaysian Education System','my_national',1,1,NULL,'2026-08-28 10:53:38','2026-08-28 10:55:07'),(35,7,'Kuwait National Education System','kw_national',1,1,NULL,'2026-08-28 10:53:38','2026-08-28 10:55:07'),(36,8,'Qatar National Education System','qa_national',1,1,NULL,'2026-08-28 10:53:38','2026-08-28 10:55:07'),(37,12,'Saudi Arabia National Education System','sa_national',1,1,NULL,'2026-08-28 10:53:38','2026-08-28 10:55:07'),(38,13,'Italian Education System','it_national',1,1,NULL,'2026-08-28 10:53:38','2026-08-28 10:55:08'),(39,14,'Spanish Education System','es_national',1,1,NULL,'2026-08-28 10:53:39','2026-08-28 10:55:08'),(40,15,'French Education System','fr_national',1,1,NULL,'2026-08-28 10:53:39','2026-08-28 10:55:08'),(41,16,'German Education System','de_national',1,1,NULL,'2026-08-28 10:53:39','2026-08-28 10:55:08'),(42,17,'Portuguese Education System','pt_national',1,1,NULL,'2026-08-28 10:53:39','2026-08-28 10:55:08'),(43,18,'Pakistan Education System','pk_national',1,1,NULL,'2026-08-28 10:53:39','2026-08-28 10:55:08'),(44,3,'Australian Education System','au_national',1,1,NULL,'2026-08-28 10:53:39','2026-08-28 10:55:08'),(45,4,'Canadian Education System','ca_national',1,1,NULL,'2026-08-28 10:53:39','2026-08-28 10:55:08'),(46,19,'New Zealand Education System','nz_national',1,1,NULL,'2026-08-28 10:53:39','2026-08-28 10:55:09'),(47,20,'Myanmar Education System','mm_national',1,1,NULL,'2026-08-28 10:53:39','2026-08-28 10:55:09'),(48,21,'Vietnam Education System','vn_national',1,1,NULL,'2026-08-28 10:53:40','2026-08-28 10:55:09'),(49,22,'Laos Education System','la_national',1,1,NULL,'2026-08-28 10:53:40','2026-08-28 10:55:09'),(50,23,'Cambodia Education System','kh_national',1,1,NULL,'2026-08-28 10:53:40','2026-08-28 10:55:09'),(51,24,'Maldives Education System','mv_national',1,1,NULL,'2026-08-28 10:53:40','2026-08-28 10:55:09');
/*!40000 ALTER TABLE `education_systems` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_otps`
--

DROP TABLE IF EXISTS `email_otps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_otps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `guard` varchar(20) NOT NULL DEFAULT 'web',
  `user_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `consumed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_otps_guard_user_id_email_index` (`guard`,`user_id`,`email`),
  KEY `email_otps_institute_id_index` (`institute_id`),
  KEY `email_otps_expires_at_index` (`expires_at`),
  CONSTRAINT `email_otps_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_otps`
--

LOCK TABLES `email_otps` WRITE;
/*!40000 ALTER TABLE `email_otps` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_otps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `endpoint_performance_logs`
--

DROP TABLE IF EXISTS `endpoint_performance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `endpoint_performance_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `route` varchar(255) NOT NULL,
  `request_count` int(10) unsigned NOT NULL DEFAULT 1,
  `average_response_time` decimal(10,2) NOT NULL DEFAULT 0.00,
  `maximum_response_time` decimal(10,2) NOT NULL DEFAULT 0.00,
  `error_count` int(10) unsigned NOT NULL DEFAULT 0,
  `http_4xx_count` int(10) unsigned NOT NULL DEFAULT 0,
  `http_5xx_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `endpoint_performance_logs_route_index` (`route`),
  KEY `endpoint_performance_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `endpoint_performance_logs`
--

LOCK TABLES `endpoint_performance_logs` WRITE;
/*!40000 ALTER TABLE `endpoint_performance_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `endpoint_performance_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `roll_no` int(11) NOT NULL,
  `trainee_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `enrollment_date` date DEFAULT NULL,
  `status` enum('active','completed','dropped') NOT NULL DEFAULT 'active',
  `payment_status` enum('pending','paid','partial') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `enrollments_batch_id_trainee_id_unique` (`batch_id`,`trainee_id`),
  UNIQUE KEY `enrollments_batch_roll_unique` (`institute_id`,`batch_id`,`roll_no`),
  KEY `enrollments_trainee_id_foreign` (`trainee_id`),
  KEY `enrollments_student_id_index` (`student_id`),
  CONSTRAINT `enrollments_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_trainee_id_foreign` FOREIGN KEY (`trainee_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollments`
--

LOCK TABLES `enrollments` WRITE;
/*!40000 ALTER TABLE `enrollments` DISABLE KEYS */;
/*!40000 ALTER TABLE `enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exam_results`
--

DROP TABLE IF EXISTS `exam_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `exam_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `marks_obtained` decimal(6,2) DEFAULT NULL,
  `written_marks` decimal(10,2) DEFAULT NULL,
  `practical_marks` decimal(10,2) DEFAULT NULL,
  `viva_marks` decimal(10,2) DEFAULT NULL,
  `other_marks` decimal(10,2) DEFAULT NULL,
  `attendance_marks` decimal(10,2) DEFAULT NULL,
  `grade` varchar(10) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL,
  `result_status` enum('pass','fail','absent') NOT NULL DEFAULT 'pass',
  `remarks` varchar(255) DEFAULT NULL,
  `entered_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exam_results_exam_student_subject` (`exam_id`,`student_id`,`subject_id`),
  KEY `idx_exam_results_institute` (`institute_id`),
  KEY `idx_exam_results_student` (`student_id`),
  KEY `fk_exam_results_entered_by` (`entered_by`),
  KEY `idx_exam_results_exam` (`exam_id`),
  KEY `idx_exam_results_subject` (`subject_id`),
  CONSTRAINT `exam_results_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `fk_exam_results_entered_by` FOREIGN KEY (`entered_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exam_results_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_results_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_results_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_results_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exam_results`
--

LOCK TABLES `exam_results` WRITE;
/*!40000 ALTER TABLE `exam_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `exam_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exam_subjects`
--

DROP TABLE IF EXISTS `exam_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `exam_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `written_marks` decimal(10,2) NOT NULL DEFAULT 0.00,
  `practical_marks` decimal(10,2) NOT NULL DEFAULT 0.00,
  `viva_marks` decimal(10,2) NOT NULL DEFAULT 0.00,
  `other_marks` decimal(10,2) NOT NULL DEFAULT 0.00,
  `attendance_marks` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pass_marks` decimal(10,2) DEFAULT NULL,
  `exam_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exam_subjects_exam_id_subject_id_unique` (`exam_id`,`subject_id`),
  KEY `exam_subjects_subject_id_index` (`subject_id`),
  CONSTRAINT `exam_subjects_exam_id_foreign` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_subjects_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exam_subjects`
--

LOCK TABLES `exam_subjects` WRITE;
/*!40000 ALTER TABLE `exam_subjects` DISABLE KEYS */;
/*!40000 ALTER TABLE `exam_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exam_types`
--

DROP TABLE IF EXISTS `exam_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(60) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `weight_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exam_types_institute_slug` (`institute_id`,`slug`),
  CONSTRAINT `fk_exam_types_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exam_types`
--

LOCK TABLES `exam_types` WRITE;
/*!40000 ALTER TABLE `exam_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `exam_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exams`
--

DROP TABLE IF EXISTS `exams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exams` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `title` varchar(150) NOT NULL,
  `exam_date` datetime NOT NULL,
  `full_marks` decimal(6,2) NOT NULL DEFAULT 100.00,
  `pass_marks` decimal(6,2) NOT NULL DEFAULT 40.00,
  `written_percent` decimal(5,2) NOT NULL DEFAULT 100.00,
  `practical_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `viva_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `status` enum('scheduled','ongoing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exams_institute` (`institute_id`),
  KEY `idx_exams_batch` (`batch_id`),
  KEY `idx_exams_course` (`course_id`),
  KEY `fk_exams_created_by` (`created_by`),
  CONSTRAINT `fk_exams_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exams_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exams_created_by` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exams_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exams`
--

LOCK TABLES `exams` WRITE;
/*!40000 ALTER TABLE `exams` DISABLE KEYS */;
/*!40000 ALTER TABLE `exams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exchange_rates`
--

DROP TABLE IF EXISTS `exchange_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exchange_rates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `from_currency_id` bigint(20) unsigned NOT NULL,
  `to_currency_id` bigint(20) unsigned NOT NULL,
  `rate` decimal(19,8) NOT NULL,
  `rate_date` date NOT NULL,
  `source` varchar(40) DEFAULT NULL,
  `buy_rate` decimal(19,8) DEFAULT NULL,
  `sell_rate` decimal(19,8) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exchange_rates` (`institute_id`,`branch_id`,`from_currency_id`,`to_currency_id`,`rate_date`),
  KEY `exchange_rates_branch_id_foreign` (`branch_id`),
  KEY `exchange_rates_from_currency_id_foreign` (`from_currency_id`),
  KEY `exchange_rates_to_currency_id_foreign` (`to_currency_id`),
  KEY `idx_exchange_rates_dates` (`institute_id`,`rate_date`),
  CONSTRAINT `exchange_rates_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `exchange_rates_from_currency_id_foreign` FOREIGN KEY (`from_currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `exchange_rates_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exchange_rates_to_currency_id_foreign` FOREIGN KEY (`to_currency_id`) REFERENCES `currencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exchange_rates`
--

LOCK TABLES `exchange_rates` WRITE;
/*!40000 ALTER TABLE `exchange_rates` DISABLE KEYS */;
/*!40000 ALTER TABLE `exchange_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fee_heads`
--

DROP TABLE IF EXISTS `fee_heads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_heads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `type` enum('admission','course_tuition','registration','exam','certificate','other') NOT NULL DEFAULT 'other',
  `default_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `income_coa_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
  `billing_frequency` enum('monthly','quarterly','annually','one_time') NOT NULL DEFAULT 'one_time',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fee_heads_inst_branch_name` (`institute_id`,`branch_id`,`name`),
  KEY `idx_fee_heads_inst_branch_type` (`institute_id`,`branch_id`,`type`),
  KEY `fk_fee_heads_branch` (`branch_id`),
  KEY `fk_fee_heads_income_coa` (`income_coa_id`),
  KEY `fee_heads_created_by_foreign` (`created_by`),
  KEY `fee_heads_updated_by_foreign` (`updated_by`),
  CONSTRAINT `fee_heads_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_heads_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fee_heads_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fee_heads_income_coa` FOREIGN KEY (`income_coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fee_heads_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fee_heads`
--

LOCK TABLES `fee_heads` WRITE;
/*!40000 ALTER TABLE `fee_heads` DISABLE KEYS */;
/*!40000 ALTER TABLE `fee_heads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fee_structure_items`
--

DROP TABLE IF EXISTS `fee_structure_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_structure_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fee_structure_id` bigint(20) unsigned NOT NULL,
  `fee_head_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_optional` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fee_structure_items_struct_head` (`fee_structure_id`,`fee_head_id`),
  KEY `idx_fee_structure_items_structure` (`fee_structure_id`),
  KEY `fk_fee_structure_items_head` (`fee_head_id`),
  CONSTRAINT `fk_fee_structure_items_head` FOREIGN KEY (`fee_head_id`) REFERENCES `fee_heads` (`id`),
  CONSTRAINT `fk_fee_structure_items_structure` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fee_structure_items`
--

LOCK TABLES `fee_structure_items` WRITE;
/*!40000 ALTER TABLE `fee_structure_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `fee_structure_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fee_structures`
--

DROP TABLE IF EXISTS `fee_structures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_structures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `academic_year_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `installments_count` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `installments_interval_days` smallint(5) unsigned NOT NULL DEFAULT 30,
  `status` enum('draft','active','archived') NOT NULL DEFAULT 'draft',
  `billing_frequency` enum('monthly','quarterly','annually','one_time') NOT NULL DEFAULT 'monthly',
  `auto_generate_monthly` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fee_structures_inst_branch_status` (`institute_id`,`branch_id`,`status`),
  KEY `idx_fee_structures_inst_course` (`institute_id`,`course_id`),
  KEY `idx_fee_structures_inst_batch` (`institute_id`,`batch_id`),
  KEY `fk_fee_structures_branch` (`branch_id`),
  KEY `fk_fee_structures_course` (`course_id`),
  KEY `fk_fee_structures_batch` (`batch_id`),
  KEY `fk_fee_structures_academic_year` (`academic_year_id`),
  KEY `fee_structures_created_by_foreign` (`created_by`),
  KEY `fee_structures_updated_by_foreign` (`updated_by`),
  CONSTRAINT `fee_structures_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_structures_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fee_structures_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fee_structures_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fee_structures_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fee_structures_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fee_structures_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fee_structures`
--

LOCK TABLES `fee_structures` WRITE;
/*!40000 ALTER TABLE `fee_structures` DISABLE KEYS */;
/*!40000 ALTER TABLE `fee_structures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fiscal_years`
--

DROP TABLE IF EXISTS `fiscal_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fiscal_years` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('open','closed','archived') NOT NULL DEFAULT 'open',
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `closed_by` bigint(20) unsigned DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fiscal_years_name` (`institute_id`,`branch_id`,`name`),
  KEY `fiscal_years_branch_id_foreign` (`branch_id`),
  KEY `idx_fy_current` (`institute_id`,`is_current`,`status`),
  CONSTRAINT `fiscal_years_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fiscal_years_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fiscal_years`
--

LOCK TABLES `fiscal_years` WRITE;
/*!40000 ALTER TABLE `fiscal_years` DISABLE KEYS */;
/*!40000 ALTER TABLE `fiscal_years` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fixed_assets`
--

DROP TABLE IF EXISTS `fixed_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fixed_assets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `location_id` bigint(20) unsigned DEFAULT NULL,
  `vendor_party_id` bigint(20) unsigned DEFAULT NULL,
  `asset_code` varchar(60) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `serial_number` varchar(120) DEFAULT NULL,
  `manufacturer` varchar(120) DEFAULT NULL,
  `model` varchar(120) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `capitalization_date` date DEFAULT NULL,
  `purchase_document_no` varchar(80) DEFAULT NULL,
  `invoice_reference` varchar(80) DEFAULT NULL,
  `acquisition_cost` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `additional_capitalized_cost` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `residual_value` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `useful_life_months` smallint(5) unsigned DEFAULT NULL,
  `depreciation_method` varchar(40) NOT NULL DEFAULT 'straight_line',
  `depreciation_frequency` varchar(20) NOT NULL DEFAULT 'monthly',
  `depreciation_convention` varchar(20) NOT NULL DEFAULT 'full_month',
  `depreciation_rate` decimal(10,4) DEFAULT NULL,
  `depreciation_start_date` date DEFAULT NULL,
  `accumulated_depreciation` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `impairment_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `is_depreciable` tinyint(1) NOT NULL DEFAULT 1,
  `unit_of_measure` varchar(40) DEFAULT NULL,
  `total_units` decimal(19,4) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `department` varchar(80) DEFAULT NULL,
  `responsible_person` varchar(120) DEFAULT NULL,
  `warranty_provider` varchar(120) DEFAULT NULL,
  `warranty_start` date DEFAULT NULL,
  `warranty_end` date DEFAULT NULL,
  `warranty_reference` varchar(80) DEFAULT NULL,
  `warranty_notes` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fixed_assets_code` (`institute_id`,`asset_code`),
  KEY `fixed_assets_branch_id_foreign` (`branch_id`),
  KEY `fixed_assets_category_id_foreign` (`category_id`),
  KEY `fixed_assets_location_id_foreign` (`location_id`),
  KEY `fixed_assets_vendor_party_id_foreign` (`vendor_party_id`),
  KEY `idx_fixed_assets_status` (`institute_id`,`status`),
  KEY `idx_fixed_assets_category` (`institute_id`,`category_id`),
  CONSTRAINT `fixed_assets_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fixed_assets_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fixed_assets_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fixed_assets_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `asset_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fixed_assets_vendor_party_id_foreign` FOREIGN KEY (`vendor_party_id`) REFERENCES `parties` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fixed_assets`
--

LOCK TABLES `fixed_assets` WRITE;
/*!40000 ALTER TABLE `fixed_assets` DISABLE KEYS */;
/*!40000 ALTER TABLE `fixed_assets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fx_revaluations`
--

DROP TABLE IF EXISTS `fx_revaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fx_revaluations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `fiscal_year_id` bigint(20) unsigned NOT NULL,
  `period_id` bigint(20) unsigned DEFAULT NULL,
  `currency_id` bigint(20) unsigned NOT NULL,
  `as_of_date` date NOT NULL,
  `closing_rate` decimal(19,8) NOT NULL,
  `carrying_value` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `revalued_value` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `difference` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('posted','reversed') NOT NULL DEFAULT 'posted',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fx_revaluations_key` (`institute_id`,`branch_id`,`fiscal_year_id`,`period_id`,`currency_id`,`as_of_date`),
  KEY `fx_revaluations_branch_id_foreign` (`branch_id`),
  KEY `fx_revaluations_fiscal_year_id_foreign` (`fiscal_year_id`),
  KEY `fx_revaluations_period_id_foreign` (`period_id`),
  KEY `fx_revaluations_currency_id_foreign` (`currency_id`),
  KEY `fx_revaluations_journal_id_foreign` (`journal_id`),
  KEY `idx_fx_revaluations_date` (`institute_id`,`as_of_date`),
  KEY `idx_fxr_scope_status` (`institute_id`,`status`,`currency_id`),
  KEY `idx_fxr_institute` (`institute_id`,`currency_id`),
  CONSTRAINT `fx_revaluations_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fx_revaluations_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fx_revaluations_fiscal_year_id_foreign` FOREIGN KEY (`fiscal_year_id`) REFERENCES `fiscal_years` (`id`),
  CONSTRAINT `fx_revaluations_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fx_revaluations_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fx_revaluations_period_id_foreign` FOREIGN KEY (`period_id`) REFERENCES `accounting_periods` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fx_revaluations`
--

LOCK TABLES `fx_revaluations` WRITE;
/*!40000 ALTER TABLE `fx_revaluations` DISABLE KEYS */;
/*!40000 ALTER TABLE `fx_revaluations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gallery_albums`
--

DROP TABLE IF EXISTS `gallery_albums`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gallery_albums` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `title` varchar(150) NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `category` varchar(80) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gallery_albums_institute` (`institute_id`),
  CONSTRAINT `fk_gallery_albums_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gallery_albums`
--

LOCK TABLES `gallery_albums` WRITE;
/*!40000 ALTER TABLE `gallery_albums` DISABLE KEYS */;
/*!40000 ALTER TABLE `gallery_albums` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gallery_media`
--

DROP TABLE IF EXISTS `gallery_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gallery_media` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `album_id` bigint(20) unsigned NOT NULL,
  `type` enum('image','video') NOT NULL DEFAULT 'image',
  `file_path` varchar(255) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `caption` varchar(200) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gallery_media_institute` (`institute_id`),
  KEY `idx_gallery_media_album` (`album_id`),
  CONSTRAINT `fk_gallery_media_album` FOREIGN KEY (`album_id`) REFERENCES `gallery_albums` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gallery_media_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gallery_media`
--

LOCK TABLES `gallery_media` WRITE;
/*!40000 ALTER TABLE `gallery_media` DISABLE KEYS */;
/*!40000 ALTER TABLE `gallery_media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `geo_imports`
--

DROP TABLE IF EXISTS `geo_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `geo_imports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_id` bigint(20) unsigned NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `format` varchar(10) NOT NULL DEFAULT 'jsonl',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `mode` varchar(10) NOT NULL DEFAULT 'upsert',
  `total_records` int(10) unsigned NOT NULL DEFAULT 0,
  `inserted_records` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_records` int(10) unsigned NOT NULL DEFAULT 0,
  `skipped_records` int(10) unsigned NOT NULL DEFAULT 0,
  `duplicate_count` int(10) unsigned NOT NULL DEFAULT 0,
  `error_count` int(10) unsigned NOT NULL DEFAULT 0,
  `error_summary` text DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `geo_imports_country_id_foreign` (`country_id`),
  KEY `geo_imports_created_by_foreign` (`created_by`),
  KEY `geo_imports_status_country_id_index` (`status`,`country_id`),
  CONSTRAINT `geo_imports_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `geo_imports_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `geo_imports`
--

LOCK TABLES `geo_imports` WRITE;
/*!40000 ALTER TABLE `geo_imports` DISABLE KEYS */;
/*!40000 ALTER TABLE `geo_imports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `goods_receipt_items`
--

DROP TABLE IF EXISTS `goods_receipt_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_receipt_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `goods_receipt_id` bigint(20) unsigned NOT NULL,
  `purchase_order_line_id` bigint(20) unsigned DEFAULT NULL,
  `inventory_item_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `ordered_quantity` decimal(19,4) NOT NULL,
  `previously_received_quantity` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `received_quantity` decimal(19,4) NOT NULL,
  `rejected_quantity` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(19,4) NOT NULL,
  `batch_number` varchar(80) DEFAULT NULL,
  `lot_number` varchar(80) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `manufacture_date` date DEFAULT NULL,
  `serial_numbers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`serial_numbers`)),
  `received_condition` varchar(30) DEFAULT NULL COMMENT 'good/damaged/expired etc',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `goods_receipt_items_goods_receipt_id_foreign` (`goods_receipt_id`),
  KEY `goods_receipt_items_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `goods_receipt_items_batch_id_foreign` (`batch_id`),
  CONSTRAINT `goods_receipt_items_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `goods_receipt_items_goods_receipt_id_foreign` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goods_receipt_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `goods_receipt_items`
--

LOCK TABLES `goods_receipt_items` WRITE;
/*!40000 ALTER TABLE `goods_receipt_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `goods_receipt_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `goods_receipts`
--

DROP TABLE IF EXISTS `goods_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `purchase_order_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `warehouse_id` bigint(20) unsigned NOT NULL,
  `receipt_date` date NOT NULL,
  `status` enum('draft','confirmed','cancelled') NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `confirmed_by` bigint(20) unsigned DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `reversed_by` bigint(20) unsigned DEFAULT NULL,
  `reversal_reason` text DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gr_inst_number` (`institute_id`,`receipt_number`),
  KEY `goods_receipts_branch_id_foreign` (`branch_id`),
  KEY `goods_receipts_supplier_id_foreign` (`supplier_id`),
  KEY `goods_receipts_warehouse_id_foreign` (`warehouse_id`),
  KEY `idx_gr_scope_status` (`institute_id`,`branch_id`,`status`),
  KEY `idx_gr_po` (`institute_id`,`purchase_order_id`),
  KEY `idx_gr_supplier` (`institute_id`,`supplier_id`),
  CONSTRAINT `goods_receipts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `goods_receipts_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goods_receipts_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `parties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goods_receipts_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `inventory_warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `goods_receipts`
--

LOCK TABLES `goods_receipts` WRITE;
/*!40000 ALTER TABLE `goods_receipts` DISABLE KEYS */;
/*!40000 ALTER TABLE `goods_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grade_scale_rows`
--

DROP TABLE IF EXISTS `grade_scale_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grade_scale_rows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `grade_scale_id` bigint(20) unsigned NOT NULL,
  `grade` varchar(20) NOT NULL,
  `min_score` decimal(8,2) NOT NULL,
  `max_score` decimal(8,2) NOT NULL,
  `grade_point` decimal(5,2) NOT NULL,
  `is_pass` tinyint(1) NOT NULL DEFAULT 1,
  `gpa_included` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `grade_scale_rows_scale_status_idx` (`grade_scale_id`,`status`),
  CONSTRAINT `grade_scale_rows_grade_scale_id_foreign` FOREIGN KEY (`grade_scale_id`) REFERENCES `grade_scales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=413 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grade_scale_rows`
--

LOCK TABLES `grade_scale_rows` WRITE;
/*!40000 ALTER TABLE `grade_scale_rows` DISABLE KEYS */;
INSERT INTO `grade_scale_rows` VALUES (298,26,'A',80.00,100.00,4.00,1,1,1,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(299,26,'B',60.00,79.00,3.00,1,1,2,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(300,26,'C',40.00,59.00,2.00,1,1,3,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(301,26,'D',33.00,39.00,1.00,1,1,4,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(302,26,'F',0.00,32.00,0.00,0,1,5,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(303,27,'A1',91.00,100.00,10.00,1,1,1,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(304,27,'A2',81.00,90.00,9.00,1,1,2,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(305,27,'B1',71.00,80.00,8.00,1,1,3,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(306,27,'B2',61.00,70.00,7.00,1,1,4,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(307,27,'C1',51.00,60.00,6.00,1,1,5,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(308,27,'C2',41.00,50.00,5.00,1,1,6,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(309,27,'D',33.00,40.00,4.00,1,1,7,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(310,27,'E',0.00,32.00,0.00,0,1,8,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(311,28,'A',80.00,100.00,5.00,1,1,1,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(312,28,'B',70.00,79.00,4.00,1,1,2,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(313,28,'C',60.00,69.00,3.00,1,1,3,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(314,28,'D',50.00,59.00,2.00,1,1,4,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(315,28,'F',0.00,49.00,0.00,0,1,5,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(316,29,'A',80.00,100.00,4.00,1,1,1,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(317,29,'B',65.00,79.00,3.00,1,1,2,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(318,29,'C',50.00,64.00,2.00,1,1,3,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(319,29,'D',40.00,49.00,1.00,1,1,4,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(320,29,'F',0.00,39.00,0.00,0,1,5,1,'2026-09-01 08:23:45','2026-09-01 08:23:45'),(321,30,'A',90.00,100.00,4.00,1,1,1,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(322,30,'B',80.00,89.00,3.00,1,1,2,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(323,30,'C',70.00,79.00,2.00,1,1,3,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(324,30,'D',60.00,69.00,1.00,1,1,4,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(325,30,'F',0.00,59.00,0.00,0,1,5,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(326,31,'A',90.00,100.00,4.00,1,1,1,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(327,31,'B',80.00,89.00,3.00,1,1,2,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(328,31,'C',70.00,79.00,2.00,1,1,3,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(329,31,'D',60.00,69.00,1.00,1,1,4,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(330,31,'F',0.00,59.00,0.00,0,1,5,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(331,32,'A',90.00,100.00,4.00,1,1,1,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(332,32,'B',80.00,89.00,3.00,1,1,2,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(333,32,'C',70.00,79.00,2.00,1,1,3,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(334,32,'D',60.00,69.00,1.00,1,1,4,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(335,32,'F',0.00,59.00,0.00,0,1,5,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(336,33,'A',90.00,100.00,10.00,1,1,1,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(337,33,'B',80.00,89.00,9.00,1,1,2,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(338,33,'C',70.00,79.00,8.00,1,1,3,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(339,33,'D',60.00,69.00,7.00,1,1,4,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(340,33,'F',0.00,59.00,0.00,0,1,5,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(341,34,'A',90.00,100.00,10.00,1,1,1,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(342,34,'B',80.00,89.00,9.00,1,1,2,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(343,34,'C',70.00,79.00,8.00,1,1,3,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(344,34,'D',60.00,69.00,7.00,1,1,4,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(345,34,'E',50.00,59.00,6.00,1,1,5,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(346,34,'F',0.00,49.00,0.00,0,1,6,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(347,35,'A',16.00,20.00,20.00,1,1,1,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(348,35,'B',14.00,15.00,18.00,1,1,2,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(349,35,'C',12.00,13.00,16.00,1,1,3,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(350,35,'D',10.00,11.00,14.00,1,1,4,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(351,35,'F',0.00,9.00,0.00,0,1,5,1,'2026-09-01 08:23:46','2026-09-01 08:23:46'),(352,36,'1',90.00,100.00,4.00,1,1,1,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(353,36,'2',80.00,89.00,3.00,1,1,2,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(354,36,'3',70.00,79.00,2.00,1,1,3,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(355,36,'4',60.00,69.00,1.00,1,1,4,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(356,36,'5',0.00,59.00,0.00,0,1,5,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(357,37,'A',90.00,100.00,20.00,1,1,1,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(358,37,'B',80.00,89.00,18.00,1,1,2,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(359,37,'C',70.00,79.00,16.00,1,1,3,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(360,37,'D',60.00,69.00,14.00,1,1,4,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(361,37,'E',50.00,59.00,12.00,1,1,5,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(362,37,'F',0.00,49.00,0.00,0,1,6,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(363,38,'A',80.00,100.00,4.00,1,1,1,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(364,38,'B',65.00,79.00,3.00,1,1,2,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(365,38,'C',50.00,64.00,2.00,1,1,3,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(366,38,'D',40.00,49.00,1.00,1,1,4,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(367,38,'F',0.00,39.00,0.00,0,1,5,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(368,39,'A',85.00,100.00,7.00,1,1,1,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(369,39,'B',75.00,84.00,6.00,1,1,2,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(370,39,'C',65.00,74.00,5.00,1,1,3,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(371,39,'D',55.00,64.00,4.00,1,1,4,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(372,39,'E',50.00,54.00,3.00,1,1,5,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(373,39,'F',0.00,49.00,0.00,0,1,6,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(374,40,'A',80.00,100.00,4.00,1,1,1,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(375,40,'B',70.00,79.00,3.00,1,1,2,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(376,40,'C',60.00,69.00,2.00,1,1,3,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(377,40,'D',50.00,59.00,1.00,1,1,4,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(378,40,'F',0.00,49.00,0.00,0,1,5,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(379,41,'A',85.00,100.00,9.00,1,1,1,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(380,41,'B',75.00,84.00,8.00,1,1,2,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(381,41,'C',65.00,74.00,7.00,1,1,3,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(382,41,'D',55.00,64.00,6.00,1,1,4,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(383,41,'E',50.00,54.00,5.00,1,1,5,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(384,41,'F',0.00,49.00,0.00,0,1,6,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(385,42,'A',80.00,100.00,5.00,1,1,1,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(386,42,'B',70.00,79.00,4.00,1,1,2,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(387,42,'C',60.00,69.00,3.00,1,1,3,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(388,42,'D',50.00,59.00,2.00,1,1,4,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(389,42,'E',40.00,49.00,1.00,1,1,5,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(390,42,'F',0.00,39.00,0.00,0,1,6,1,'2026-09-01 08:23:47','2026-09-01 08:23:47'),(391,43,'A',90.00,100.00,10.00,1,1,1,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(392,43,'B',80.00,89.00,9.00,1,1,2,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(393,43,'C',70.00,79.00,8.00,1,1,3,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(394,43,'D',60.00,69.00,7.00,1,1,4,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(395,43,'E',50.00,59.00,6.00,1,1,5,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(396,43,'F',0.00,49.00,0.00,0,1,6,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(397,44,'A',90.00,100.00,10.00,1,1,1,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(398,44,'B',80.00,89.00,9.00,1,1,2,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(399,44,'C',70.00,79.00,8.00,1,1,3,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(400,44,'D',60.00,69.00,7.00,1,1,4,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(401,44,'E',50.00,59.00,6.00,1,1,5,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(402,44,'F',0.00,49.00,0.00,0,1,6,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(403,45,'A',80.00,100.00,5.00,1,1,1,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(404,45,'B',70.00,79.00,4.00,1,1,2,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(405,45,'C',60.00,69.00,3.00,1,1,3,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(406,45,'D',50.00,59.00,2.00,1,1,4,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(407,45,'F',0.00,49.00,0.00,0,1,5,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(408,46,'A',80.00,100.00,5.00,1,1,1,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(409,46,'B',70.00,79.00,4.00,1,1,2,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(410,46,'C',60.00,69.00,3.00,1,1,3,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(411,46,'D',50.00,59.00,2.00,1,1,4,1,'2026-09-01 08:23:48','2026-09-01 08:23:48'),(412,46,'F',0.00,49.00,0.00,0,1,5,1,'2026-09-01 08:23:48','2026-09-01 08:23:48');
/*!40000 ALTER TABLE `grade_scale_rows` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grade_scales`
--

DROP TABLE IF EXISTS `grade_scales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grade_scales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `education_system_id` bigint(20) unsigned DEFAULT NULL,
  `academic_level_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `gpa_mode` varchar(20) NOT NULL DEFAULT 'equal_weight',
  `optional_subject_gpa` varchar(20) NOT NULL DEFAULT 'included',
  `optional_subject_bonus_threshold` decimal(4,2) NOT NULL DEFAULT 2.00,
  `optional_subject_bonus_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `multiple_optional_policy` enum('single','best','sum') NOT NULL DEFAULT 'single' COMMENT 'Multiple optional policy: single (default, one optional), best (max bonus), sum (all)',
  `max_gpa` decimal(4,2) NOT NULL DEFAULT 5.00,
  `marks_decimal_places` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `percentage_decimal_places` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `gpa_decimal_places` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `cgpa_decimal_places` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `rounding_mode` varchar(20) NOT NULL DEFAULT 'half_up',
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `scope_key` varchar(120) GENERATED ALWAYS AS (concat_ws(':',ifnull(`institute_id`,0),ifnull(`country_id`,0),ifnull(`education_system_id`,0),ifnull(`academic_level_id`,0))) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grade_scales_scope_unique` (`scope_key`),
  KEY `grade_scales_institute_id_foreign` (`institute_id`),
  KEY `grade_scales_education_system_id_foreign` (`education_system_id`),
  KEY `grade_scales_academic_level_id_foreign` (`academic_level_id`),
  KEY `grade_scales_resolve_idx` (`country_id`,`institute_id`,`status`),
  CONSTRAINT `grade_scales_academic_level_id_foreign` FOREIGN KEY (`academic_level_id`) REFERENCES `academic_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grade_scales_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grade_scales_education_system_id_foreign` FOREIGN KEY (`education_system_id`) REFERENCES `education_systems` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grade_scales_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grade_scales`
--

LOCK TABLES `grade_scales` WRITE;
/*!40000 ALTER TABLE `grade_scales` DISABLE KEYS */;
INSERT INTO `grade_scales` VALUES (26,NULL,NULL,NULL,NULL,'Global Default Grade Scale','equal_weight','included',2.00,1,'best',5.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:45','2026-09-01 08:23:45','0:0:0:0'),(27,NULL,26,NULL,NULL,'India National Grade Scale','equal_weight','included',2.00,1,'best',10.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:45','2026-09-01 08:23:45','0:26:0:0'),(28,NULL,27,NULL,NULL,'Singapore National Grade Scale','equal_weight','included',2.00,1,'best',5.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:45','2026-09-01 08:23:45','0:27:0:0'),(29,NULL,28,NULL,NULL,'Malaysia National Grade Scale','equal_weight','included',2.00,1,'best',4.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:45','2026-09-01 08:23:45','0:28:0:0'),(30,NULL,29,NULL,NULL,'Kuwait National Grade Scale','equal_weight','included',2.00,1,'best',4.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:46','2026-09-01 08:23:46','0:29:0:0'),(31,NULL,30,NULL,NULL,'Qatar National Grade Scale','equal_weight','included',2.00,1,'best',4.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:46','2026-09-01 08:23:46','0:30:0:0'),(32,NULL,31,NULL,NULL,'Saudi Arabia National Grade Scale','equal_weight','included',2.00,1,'best',4.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:46','2026-09-01 08:23:46','0:31:0:0'),(33,NULL,32,NULL,NULL,'Italy National Grade Scale','equal_weight','included',2.00,1,'best',10.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:46','2026-09-01 08:23:46','0:32:0:0'),(34,NULL,33,NULL,NULL,'Spain National Grade Scale','equal_weight','included',2.00,1,'best',10.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:46','2026-09-01 08:23:46','0:33:0:0'),(35,NULL,34,NULL,NULL,'France National Grade Scale','equal_weight','included',2.00,1,'best',20.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:46','2026-09-01 08:23:46','0:34:0:0'),(36,NULL,35,NULL,NULL,'Germany National Grade Scale','equal_weight','included',2.00,1,'best',5.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:47','2026-09-01 08:23:47','0:35:0:0'),(37,NULL,36,NULL,NULL,'Portugal National Grade Scale','equal_weight','included',2.00,1,'best',20.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:47','2026-09-01 08:23:47','0:36:0:0'),(38,NULL,37,NULL,NULL,'Pakistan National Grade Scale','equal_weight','included',2.00,1,'best',4.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:47','2026-09-01 08:23:47','0:37:0:0'),(39,NULL,38,NULL,NULL,'Australia National Grade Scale','equal_weight','included',2.00,1,'best',7.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:47','2026-09-01 08:23:47','0:38:0:0'),(40,NULL,39,NULL,NULL,'Canada National Grade Scale','equal_weight','included',2.00,1,'best',4.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:47','2026-09-01 08:23:47','0:39:0:0'),(41,NULL,40,NULL,NULL,'New Zealand National Grade Scale','equal_weight','included',2.00,1,'best',9.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:47','2026-09-01 08:23:47','0:40:0:0'),(42,NULL,41,NULL,NULL,'Myanmar National Grade Scale','equal_weight','included',2.00,1,'best',5.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:47','2026-09-01 08:23:47','0:41:0:0'),(43,NULL,42,NULL,NULL,'Vietnam National Grade Scale','equal_weight','included',2.00,1,'best',10.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:48','2026-09-01 08:23:48','0:42:0:0'),(44,NULL,43,NULL,NULL,'Laos National Grade Scale','equal_weight','included',2.00,1,'best',10.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:48','2026-09-01 08:23:48','0:43:0:0'),(45,NULL,44,NULL,NULL,'Cambodia National Grade Scale','equal_weight','included',2.00,1,'best',5.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:48','2026-09-01 08:23:48','0:44:0:0'),(46,NULL,45,NULL,NULL,'Maldives National Grade Scale','equal_weight','included',2.00,1,'best',5.00,2,2,2,2,'half_up',0,1,'2026-09-01 08:23:48','2026-09-01 08:23:48','0:45:0:0');
/*!40000 ALTER TABLE `grade_scales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grading_scale`
--

DROP TABLE IF EXISTS `grading_scale`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grading_scale` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `grade_name` varchar(10) NOT NULL,
  `min_percent` decimal(5,2) NOT NULL,
  `max_percent` decimal(5,2) NOT NULL,
  `grade_point` decimal(3,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_grading_scale_institute` (`institute_id`),
  CONSTRAINT `fk_grading_scale_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grading_scale`
--

LOCK TABLES `grading_scale` WRITE;
/*!40000 ALTER TABLE `grading_scale` DISABLE KEYS */;
/*!40000 ALTER TABLE `grading_scale` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guardians`
--

DROP TABLE IF EXISTS `guardians`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guardians` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `preferred_language` varchar(5) NOT NULL DEFAULT 'en',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `preferred_2fa_method` varchar(20) DEFAULT NULL,
  `sms_2fa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `email_2fa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `remember_token` varchar(100) DEFAULT NULL,
  `failed_login_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `inactivity_warning_sent_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_guardians_institute_phone` (`institute_id`,`phone`),
  UNIQUE KEY `uq_guardians_institute_email` (`institute_id`,`email`),
  KEY `idx_guardians_institute` (`institute_id`),
  KEY `idx_guardians_status` (`status`),
  CONSTRAINT `guardians_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guardians`
--

LOCK TABLES `guardians` WRITE;
/*!40000 ALTER TABLE `guardians` DISABLE KEYS */;
/*!40000 ALTER TABLE `guardians` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_application_histories`
--

DROP TABLE IF EXISTS `hr_application_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_application_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `application_id` bigint(20) unsigned NOT NULL,
  `from_stage` enum('new','screening','shortlisted','interview','assessment','selected','rejected','hired','withdrawn') DEFAULT NULL,
  `to_stage` enum('new','screening','shortlisted','interview','assessment','selected','rejected','hired','withdrawn') NOT NULL,
  `notes` text DEFAULT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_ah_inst` (`institute_id`),
  KEY `idx_hr_ah_app` (`application_id`),
  KEY `fk_hr_ah_changed` (`changed_by`),
  CONSTRAINT `fk_hr_ah_app` FOREIGN KEY (`application_id`) REFERENCES `hr_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_ah_changed` FOREIGN KEY (`changed_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_ah_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_application_histories`
--

LOCK TABLES `hr_application_histories` WRITE;
/*!40000 ALTER TABLE `hr_application_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_application_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_applications`
--

DROP TABLE IF EXISTS `hr_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_applications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `vacancy_id` bigint(20) unsigned DEFAULT NULL,
  `candidate_lead_id` bigint(20) unsigned NOT NULL,
  `candidate_contact_id` bigint(20) unsigned DEFAULT NULL,
  `hired_employee_id` bigint(20) unsigned DEFAULT NULL,
  `current_stage` enum('new','screening','shortlisted','interview','assessment','selected','rejected','hired','withdrawn') NOT NULL DEFAULT 'new',
  `assigned_recruiter_id` bigint(20) unsigned DEFAULT NULL,
  `application_date` date NOT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_app_vac_lead` (`institute_id`,`vacancy_id`,`candidate_lead_id`),
  KEY `idx_hr_app_inst` (`institute_id`),
  KEY `idx_hr_app_vac` (`vacancy_id`),
  KEY `idx_hr_app_lead` (`candidate_lead_id`),
  KEY `idx_hr_app_stage` (`current_stage`),
  KEY `idx_hr_app_recruiter` (`assigned_recruiter_id`),
  KEY `fk_hr_app_branch` (`branch_id`),
  KEY `fk_hr_app_contact` (`candidate_contact_id`),
  KEY `fk_hr_app_employee` (`hired_employee_id`),
  KEY `fk_hr_app_source` (`source_id`),
  KEY `fk_hr_app_created` (`created_by`),
  KEY `fk_hr_app_updated` (`updated_by`),
  CONSTRAINT `fk_hr_app_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_app_contact` FOREIGN KEY (`candidate_contact_id`) REFERENCES `crm_contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_app_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_app_employee` FOREIGN KEY (`hired_employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_app_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_app_lead` FOREIGN KEY (`candidate_lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_app_recruiter` FOREIGN KEY (`assigned_recruiter_id`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_app_source` FOREIGN KEY (`source_id`) REFERENCES `crm_lead_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_app_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_app_vac` FOREIGN KEY (`vacancy_id`) REFERENCES `hr_vacancies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_applications`
--

LOCK TABLES `hr_applications` WRITE;
/*!40000 ALTER TABLE `hr_applications` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_attendance_corrections`
--

DROP TABLE IF EXISTS `hr_attendance_corrections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_attendance_corrections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `attendance_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `correction_date` date NOT NULL,
  `requested_status` enum('present','absent','late','early_departure','leave','holiday','weekend','half_day') NOT NULL,
  `requested_check_in` time DEFAULT NULL,
  `requested_check_out` time DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_corr_institute` (`institute_id`),
  KEY `idx_hr_corr_employee` (`employee_id`),
  KEY `idx_hr_corr_status` (`status`),
  KEY `fk_hr_corr_attendance` (`attendance_id`),
  KEY `fk_hr_corr_requested` (`requested_by`),
  KEY `fk_hr_corr_reviewed` (`reviewed_by`),
  CONSTRAINT `fk_hr_corr_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `hr_attendances` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_corr_employee` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_corr_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_corr_requested` FOREIGN KEY (`requested_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_corr_reviewed` FOREIGN KEY (`reviewed_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_attendance_corrections`
--

LOCK TABLES `hr_attendance_corrections` WRITE;
/*!40000 ALTER TABLE `hr_attendance_corrections` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_attendance_corrections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_attendances`
--

DROP TABLE IF EXISTS `hr_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_attendances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','late','early_departure','leave','holiday','weekend','half_day') NOT NULL DEFAULT 'present',
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `working_minutes` smallint(5) unsigned DEFAULT NULL,
  `late_minutes` smallint(5) unsigned DEFAULT NULL,
  `overtime_minutes` smallint(5) unsigned DEFAULT NULL,
  `source` enum('manual','system','api','import') NOT NULL DEFAULT 'manual',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_attendances_inst_emp_date` (`institute_id`,`employee_id`,`attendance_date`),
  KEY `idx_hr_attendances_institute` (`institute_id`),
  KEY `idx_hr_attendances_employee` (`employee_id`),
  KEY `idx_hr_attendances_date` (`attendance_date`),
  KEY `idx_hr_attendances_status` (`status`),
  KEY `fk_hr_att_branch` (`branch_id`),
  KEY `fk_hr_att_shift` (`shift_id`),
  KEY `fk_hr_att_created` (`created_by`),
  KEY `fk_hr_att_updated` (`updated_by`),
  CONSTRAINT `fk_hr_att_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_att_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_att_employee` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_att_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_att_shift` FOREIGN KEY (`shift_id`) REFERENCES `hr_work_shifts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_att_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_attendances`
--

LOCK TABLES `hr_attendances` WRITE;
/*!40000 ALTER TABLE `hr_attendances` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_departments`
--

DROP TABLE IF EXISTS `hr_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_departments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `parent_department_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_departments_institute` (`institute_id`),
  KEY `idx_hr_departments_branch` (`branch_id`),
  KEY `idx_hr_departments_parent` (`parent_department_id`),
  KEY `idx_hr_departments_active` (`institute_id`,`is_active`),
  KEY `fk_hr_departments_created_by` (`created_by`),
  KEY `fk_hr_departments_updated_by` (`updated_by`),
  CONSTRAINT `fk_hr_departments_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_departments_created_by` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_departments_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_departments_parent` FOREIGN KEY (`parent_department_id`) REFERENCES `hr_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_departments_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_departments`
--

LOCK TABLES `hr_departments` WRITE;
/*!40000 ALTER TABLE `hr_departments` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_designations`
--

DROP TABLE IF EXISTS `hr_designations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_designations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_designations_institute` (`institute_id`),
  KEY `idx_hr_designations_department` (`department_id`),
  KEY `idx_hr_designations_active` (`institute_id`,`is_active`),
  KEY `fk_hr_designations_created_by` (`created_by`),
  KEY `fk_hr_designations_updated_by` (`updated_by`),
  CONSTRAINT `fk_hr_designations_created_by` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_designations_department` FOREIGN KEY (`department_id`) REFERENCES `hr_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_designations_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_designations_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_designations`
--

LOCK TABLES `hr_designations` WRITE;
/*!40000 ALTER TABLE `hr_designations` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_designations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_employee_code_sequences`
--

DROP TABLE IF EXISTS `hr_employee_code_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employee_code_sequences` (
  `institute_id` bigint(20) unsigned NOT NULL,
  `last_sequence` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`institute_id`),
  CONSTRAINT `fk_hr_employee_seq_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_employee_code_sequences`
--

LOCK TABLES `hr_employee_code_sequences` WRITE;
/*!40000 ALTER TABLE `hr_employee_code_sequences` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_employee_code_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_employee_requests`
--

DROP TABLE IF EXISTS `hr_employee_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employee_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `request_type` enum('profile_update','transfer','promotion','other') NOT NULL DEFAULT 'other',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_req2_inst` (`institute_id`),
  KEY `idx_hr_req2_emp` (`employee_id`),
  KEY `idx_hr_req2_status` (`status`),
  KEY `fk_hr_req2_branch` (`branch_id`),
  KEY `fk_hr_req2_requested` (`requested_by`),
  KEY `fk_hr_req2_reviewed` (`reviewed_by`),
  CONSTRAINT `fk_hr_req2_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_req2_emp` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_req2_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_req2_requested` FOREIGN KEY (`requested_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_req2_reviewed` FOREIGN KEY (`reviewed_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_employee_requests`
--

LOCK TABLES `hr_employee_requests` WRITE;
/*!40000 ALTER TABLE `hr_employee_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_employee_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_employee_salary_assignments`
--

DROP TABLE IF EXISTS `hr_employee_salary_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employee_salary_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `salary_structure_id` bigint(20) unsigned DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `pay_frequency` enum('monthly','weekly','biweekly','fortnightly') NOT NULL DEFAULT 'monthly',
  `effective_date` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `basic_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `housing_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `medical_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `transport_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `overtime_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bonus_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deduction_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_es_assign_inst` (`institute_id`),
  KEY `idx_hr_es_assign_employee` (`employee_id`),
  KEY `idx_hr_es_assign_structure` (`salary_structure_id`),
  KEY `idx_hr_es_assign_date` (`effective_date`),
  KEY `idx_hr_es_assign_emp_date` (`institute_id`,`employee_id`,`effective_date`),
  KEY `fk_hr_es_assign_branch` (`branch_id`),
  KEY `fk_hr_es_assign_currency` (`currency_id`),
  KEY `fk_hr_es_assign_created` (`created_by`),
  KEY `fk_hr_es_assign_updated` (`updated_by`),
  CONSTRAINT `fk_hr_es_assign_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_es_assign_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_es_assign_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_es_assign_emp` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_es_assign_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_es_assign_structure` FOREIGN KEY (`salary_structure_id`) REFERENCES `hr_salary_structures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_es_assign_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_employee_salary_assignments`
--

LOCK TABLES `hr_employee_salary_assignments` WRITE;
/*!40000 ALTER TABLE `hr_employee_salary_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_employee_salary_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_employee_skills`
--

DROP TABLE IF EXISTS `hr_employee_skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employee_skills` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `skill_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `proficiency_level` enum('beginner','intermediate','advanced','expert') NOT NULL DEFAULT 'beginner',
  `acquired_date` date DEFAULT NULL,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `verified_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_skills_inst_emp` (`institute_id`,`employee_id`),
  KEY `idx_hr_skills_name` (`skill_name`),
  KEY `fk_hr_skills_employee` (`employee_id`),
  KEY `fk_hr_skills_verified` (`verified_by`),
  CONSTRAINT `fk_hr_skills_employee` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_skills_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_skills_verified` FOREIGN KEY (`verified_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_employee_skills`
--

LOCK TABLES `hr_employee_skills` WRITE;
/*!40000 ALTER TABLE `hr_employee_skills` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_employee_skills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_employees`
--

DROP TABLE IF EXISTS `hr_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `designation_id` bigint(20) unsigned DEFAULT NULL,
  `reporting_manager_id` bigint(20) unsigned DEFAULT NULL,
  `institute_user_id` bigint(20) unsigned DEFAULT NULL,
  `employee_code` varchar(40) NOT NULL,
  `first_name` varchar(60) NOT NULL,
  `middle_name` varchar(60) DEFAULT NULL,
  `last_name` varchar(60) NOT NULL,
  `display_name` varchar(180) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(120) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `national_id` varchar(60) DEFAULT NULL,
  `passport_no` varchar(60) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `employment_status` enum('active','inactive','suspended','resigned','terminated') NOT NULL DEFAULT 'active',
  `employment_type` enum('full_time','part_time','contractual','permanent','temporary','intern','probation') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_employees_institute_code` (`institute_id`,`employee_code`),
  KEY `idx_hr_employees_institute` (`institute_id`),
  KEY `idx_hr_employees_branch` (`branch_id`),
  KEY `idx_hr_employees_department` (`department_id`),
  KEY `idx_hr_employees_designation` (`designation_id`),
  KEY `idx_hr_employees_manager` (`reporting_manager_id`),
  KEY `idx_hr_employees_status` (`employment_status`),
  KEY `idx_hr_employees_type` (`employment_type`),
  KEY `idx_hr_employees_institute_status` (`institute_id`,`employment_status`),
  KEY `fk_hr_employees_user` (`institute_user_id`),
  KEY `fk_hr_employees_created_by` (`created_by`),
  KEY `fk_hr_employees_updated_by` (`updated_by`),
  CONSTRAINT `fk_hr_employees_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_employees_created_by` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_employees_department` FOREIGN KEY (`department_id`) REFERENCES `hr_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_employees_designation` FOREIGN KEY (`designation_id`) REFERENCES `hr_designations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_employees_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_employees_manager` FOREIGN KEY (`reporting_manager_id`) REFERENCES `hr_employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_employees_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_employees_user` FOREIGN KEY (`institute_user_id`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_employees`
--

LOCK TABLES `hr_employees` WRITE;
/*!40000 ALTER TABLE `hr_employees` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_employment_histories`
--

DROP TABLE IF EXISTS `hr_employment_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employment_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `event_type` enum('joining','branch_transfer','department_transfer','designation_change','manager_change','employment_type_change','employment_status_change','salary_reference','promotion','demotion','resignation','resignation_approved','resignation_rejected','termination','reactivation','rejoin') NOT NULL,
  `effective_date` date NOT NULL,
  `previous_branch_id` bigint(20) unsigned DEFAULT NULL,
  `new_branch_id` bigint(20) unsigned DEFAULT NULL,
  `previous_department_id` bigint(20) unsigned DEFAULT NULL,
  `new_department_id` bigint(20) unsigned DEFAULT NULL,
  `previous_designation_id` bigint(20) unsigned DEFAULT NULL,
  `new_designation_id` bigint(20) unsigned DEFAULT NULL,
  `previous_manager_id` bigint(20) unsigned DEFAULT NULL,
  `new_manager_id` bigint(20) unsigned DEFAULT NULL,
  `previous_employment_type` varchar(30) DEFAULT NULL,
  `new_employment_type` varchar(30) DEFAULT NULL,
  `previous_employment_status` varchar(30) DEFAULT NULL,
  `new_employment_status` varchar(30) DEFAULT NULL,
  `previous_salary_reference` varchar(100) DEFAULT NULL,
  `new_salary_reference` varchar(100) DEFAULT NULL,
  `title` varchar(150) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected','cancelled') DEFAULT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_histories_institute` (`institute_id`),
  KEY `idx_hr_histories_employee` (`employee_id`),
  KEY `idx_hr_histories_event` (`event_type`),
  KEY `idx_hr_histories_effective` (`effective_date`),
  KEY `idx_hr_histories_inst_emp` (`institute_id`,`employee_id`),
  KEY `fk_hr_hist_prev_branch` (`previous_branch_id`),
  KEY `fk_hr_hist_new_branch` (`new_branch_id`),
  KEY `fk_hr_hist_prev_dept` (`previous_department_id`),
  KEY `fk_hr_hist_new_dept` (`new_department_id`),
  KEY `fk_hr_hist_prev_desig` (`previous_designation_id`),
  KEY `fk_hr_hist_new_desig` (`new_designation_id`),
  KEY `fk_hr_hist_prev_mgr` (`previous_manager_id`),
  KEY `fk_hr_hist_new_mgr` (`new_manager_id`),
  KEY `fk_hr_hist_changed_by` (`changed_by`),
  CONSTRAINT `fk_hr_hist_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_hist_new_branch` FOREIGN KEY (`new_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_hist_new_dept` FOREIGN KEY (`new_department_id`) REFERENCES `hr_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_hist_new_desig` FOREIGN KEY (`new_designation_id`) REFERENCES `hr_designations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_hist_new_mgr` FOREIGN KEY (`new_manager_id`) REFERENCES `hr_employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_hist_prev_branch` FOREIGN KEY (`previous_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_hist_prev_dept` FOREIGN KEY (`previous_department_id`) REFERENCES `hr_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_hist_prev_desig` FOREIGN KEY (`previous_designation_id`) REFERENCES `hr_designations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_hist_prev_mgr` FOREIGN KEY (`previous_manager_id`) REFERENCES `hr_employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_histories_employee` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_histories_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_employment_histories`
--

LOCK TABLES `hr_employment_histories` WRITE;
/*!40000 ALTER TABLE `hr_employment_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_employment_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_employment_periods`
--

DROP TABLE IF EXISTS `hr_employment_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employment_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `end_reason` enum('resigned','terminated','inactive','other') DEFAULT NULL,
  `status` enum('active','closed') NOT NULL DEFAULT 'active',
  `started_by` bigint(20) unsigned DEFAULT NULL,
  `ended_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_periods_institute` (`institute_id`),
  KEY `idx_hr_periods_employee` (`employee_id`),
  KEY `idx_hr_periods_status` (`status`),
  KEY `idx_hr_periods_inst_emp_status` (`institute_id`,`employee_id`,`status`),
  KEY `fk_hr_periods_started_by` (`started_by`),
  KEY `fk_hr_periods_ended_by` (`ended_by`),
  CONSTRAINT `fk_hr_periods_employee` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_periods_ended_by` FOREIGN KEY (`ended_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_periods_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_periods_started_by` FOREIGN KEY (`started_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_employment_periods`
--

LOCK TABLES `hr_employment_periods` WRITE;
/*!40000 ALTER TABLE `hr_employment_periods` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_employment_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_holidays`
--

DROP TABLE IF EXISTS `hr_holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_holidays` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `holiday_date` date NOT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_holidays_inst_branch_date` (`institute_id`,`branch_id`,`holiday_date`),
  KEY `idx_hr_holidays_institute` (`institute_id`),
  KEY `idx_hr_holidays_date` (`holiday_date`),
  KEY `fk_hr_holidays_branch` (`branch_id`),
  KEY `fk_hr_holidays_created` (`created_by`),
  KEY `fk_hr_holidays_updated` (`updated_by`),
  CONSTRAINT `fk_hr_holidays_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_holidays_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_holidays_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_holidays_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_holidays`
--

LOCK TABLES `hr_holidays` WRITE;
/*!40000 ALTER TABLE `hr_holidays` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_holidays` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_interviews`
--

DROP TABLE IF EXISTS `hr_interviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_interviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `application_id` bigint(20) unsigned NOT NULL,
  `vacancy_id` bigint(20) unsigned DEFAULT NULL,
  `candidate_lead_id` bigint(20) unsigned NOT NULL,
  `interviewer_id` bigint(20) unsigned DEFAULT NULL,
  `interview_type` enum('onsite','online','phone','panel') NOT NULL DEFAULT 'onsite',
  `scheduled_at` datetime NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `recommendation` enum('hire','reject','hold','pending') NOT NULL DEFAULT 'pending',
  `status` enum('scheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_int_inst` (`institute_id`),
  KEY `idx_hr_int_app` (`application_id`),
  KEY `idx_hr_int_interviewer` (`interviewer_id`),
  KEY `idx_hr_int_status` (`status`),
  KEY `fk_hr_int_branch` (`branch_id`),
  KEY `fk_hr_int_vac` (`vacancy_id`),
  KEY `fk_hr_int_lead` (`candidate_lead_id`),
  KEY `fk_hr_int_created` (`created_by`),
  KEY `fk_hr_int_updated` (`updated_by`),
  CONSTRAINT `fk_hr_int_app` FOREIGN KEY (`application_id`) REFERENCES `hr_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_int_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_int_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_int_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_int_interviewer` FOREIGN KEY (`interviewer_id`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_int_lead` FOREIGN KEY (`candidate_lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_int_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_int_vac` FOREIGN KEY (`vacancy_id`) REFERENCES `hr_vacancies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_interviews`
--

LOCK TABLES `hr_interviews` WRITE;
/*!40000 ALTER TABLE `hr_interviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_interviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_kpis`
--

DROP TABLE IF EXISTS `hr_kpis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_kpis` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `target` varchar(150) DEFAULT NULL,
  `measurement` varchar(100) DEFAULT NULL,
  `weight` decimal(5,2) NOT NULL DEFAULT 1.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_kpis_inst_active` (`institute_id`,`is_active`),
  KEY `fk_hr_kpis_branch` (`branch_id`),
  CONSTRAINT `fk_hr_kpis_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_kpis_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_kpis`
--

LOCK TABLES `hr_kpis` WRITE;
/*!40000 ALTER TABLE `hr_kpis` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_kpis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_leave_applications`
--

DROP TABLE IF EXISTS `hr_leave_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_leave_applications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `leave_type_id` bigint(20) unsigned DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_count` decimal(5,1) NOT NULL,
  `reason` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `applied_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_leave_apps_institute` (`institute_id`),
  KEY `idx_hr_leave_apps_employee` (`employee_id`),
  KEY `idx_hr_leave_apps_type` (`leave_type_id`),
  KEY `idx_hr_leave_apps_status` (`status`),
  KEY `idx_hr_leave_apps_dates` (`employee_id`,`start_date`,`end_date`),
  KEY `fk_hr_leave_apps_branch` (`branch_id`),
  KEY `fk_hr_leave_apps_applied` (`applied_by`),
  KEY `fk_hr_leave_apps_approved` (`approved_by`),
  CONSTRAINT `fk_hr_leave_apps_applied` FOREIGN KEY (`applied_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_leave_apps_approved` FOREIGN KEY (`approved_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_leave_apps_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_leave_apps_employee` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_leave_apps_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_leave_apps_type` FOREIGN KEY (`leave_type_id`) REFERENCES `hr_leave_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_leave_applications`
--

LOCK TABLES `hr_leave_applications` WRITE;
/*!40000 ALTER TABLE `hr_leave_applications` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_leave_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_leave_balances`
--

DROP TABLE IF EXISTS `hr_leave_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_leave_balances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `leave_type_id` bigint(20) unsigned NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `allocated` decimal(5,1) NOT NULL DEFAULT 0.0,
  `carry_forward` decimal(5,1) NOT NULL DEFAULT 0.0,
  `used` decimal(5,1) NOT NULL DEFAULT 0.0,
  `pending` decimal(5,1) NOT NULL DEFAULT 0.0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_balances_emp_type_year` (`employee_id`,`leave_type_id`,`year`),
  KEY `idx_hr_balances_institute` (`institute_id`),
  KEY `fk_hr_balances_type` (`leave_type_id`),
  CONSTRAINT `fk_hr_balances_employee` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_balances_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_balances_type` FOREIGN KEY (`leave_type_id`) REFERENCES `hr_leave_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_leave_balances`
--

LOCK TABLES `hr_leave_balances` WRITE;
/*!40000 ALTER TABLE `hr_leave_balances` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_leave_balances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_leave_types`
--

DROP TABLE IF EXISTS `hr_leave_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_leave_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(40) NOT NULL,
  `yearly_allowance` smallint(5) unsigned NOT NULL DEFAULT 0,
  `carry_forward` tinyint(1) NOT NULL DEFAULT 0,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_leave_types_inst_code` (`institute_id`,`code`),
  KEY `idx_hr_leave_types_institute` (`institute_id`),
  KEY `fk_hr_leave_types_created` (`created_by`),
  KEY `fk_hr_leave_types_updated` (`updated_by`),
  CONSTRAINT `fk_hr_leave_types_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_leave_types_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_leave_types_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_leave_types`
--

LOCK TABLES `hr_leave_types` WRITE;
/*!40000 ALTER TABLE `hr_leave_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_leave_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_offers`
--

DROP TABLE IF EXISTS `hr_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_offers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `application_id` bigint(20) unsigned NOT NULL,
  `candidate_lead_id` bigint(20) unsigned NOT NULL,
  `proposed_designation_id` bigint(20) unsigned DEFAULT NULL,
  `proposed_department_id` bigint(20) unsigned DEFAULT NULL,
  `proposed_branch_id` bigint(20) unsigned DEFAULT NULL,
  `salary_reference` varchar(100) DEFAULT NULL,
  `offered_salary` decimal(12,2) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `offer_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('draft','sent','accepted','rejected','withdrawn','expired') NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_offers_app` (`application_id`),
  KEY `idx_hr_offers_inst` (`institute_id`),
  KEY `idx_hr_offers_lead` (`candidate_lead_id`),
  KEY `idx_hr_offers_status` (`status`),
  KEY `fk_hr_offers_branch` (`branch_id`),
  KEY `fk_hr_offers_desig` (`proposed_designation_id`),
  KEY `fk_hr_offers_dept` (`proposed_department_id`),
  KEY `fk_hr_offers_proposed_branch` (`proposed_branch_id`),
  KEY `fk_hr_offers_created` (`created_by`),
  KEY `fk_hr_offers_updated` (`updated_by`),
  CONSTRAINT `fk_hr_offers_app` FOREIGN KEY (`application_id`) REFERENCES `hr_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_offers_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_offers_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_offers_dept` FOREIGN KEY (`proposed_department_id`) REFERENCES `hr_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_offers_desig` FOREIGN KEY (`proposed_designation_id`) REFERENCES `hr_designations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_offers_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_offers_lead` FOREIGN KEY (`candidate_lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_offers_proposed_branch` FOREIGN KEY (`proposed_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_offers_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_offers`
--

LOCK TABLES `hr_offers` WRITE;
/*!40000 ALTER TABLE `hr_offers` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_offers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_payroll_adjustments`
--

DROP TABLE IF EXISTS `hr_payroll_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_payroll_adjustments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `payroll_id` bigint(20) unsigned DEFAULT NULL,
  `payroll_period_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `adjustment_type` enum('bonus','deduction','allowance','correction','overtime','commission','tax') NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  `reason` varchar(500) NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'approved',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_pa_inst` (`institute_id`),
  KEY `idx_hr_pa_payroll` (`payroll_id`),
  KEY `idx_hr_pa_employee` (`employee_id`),
  KEY `idx_hr_pa_type` (`adjustment_type`),
  KEY `idx_hr_pa_period` (`institute_id`,`payroll_period_id`),
  KEY `fk_hr_pa_branch` (`branch_id`),
  KEY `fk_hr_pa_period` (`payroll_period_id`),
  KEY `fk_hr_pa_created` (`created_by`),
  KEY `fk_hr_pa_approved` (`approved_by`),
  CONSTRAINT `fk_hr_pa_approved` FOREIGN KEY (`approved_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_pa_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_pa_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_pa_employee` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_pa_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_pa_payroll` FOREIGN KEY (`payroll_id`) REFERENCES `hr_payrolls` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_pa_period` FOREIGN KEY (`payroll_period_id`) REFERENCES `hr_payroll_periods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_payroll_adjustments`
--

LOCK TABLES `hr_payroll_adjustments` WRITE;
/*!40000 ALTER TABLE `hr_payroll_adjustments` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_payroll_adjustments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_payroll_items`
--

DROP TABLE IF EXISTS `hr_payroll_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_payroll_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `payroll_id` bigint(20) unsigned NOT NULL,
  `item_type` enum('earning','deduction') NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_pi_inst` (`institute_id`),
  KEY `idx_hr_pi_payroll` (`payroll_id`),
  KEY `idx_hr_pi_type` (`item_type`),
  CONSTRAINT `fk_hr_pi_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_pi_payroll` FOREIGN KEY (`payroll_id`) REFERENCES `hr_payrolls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_payroll_items`
--

LOCK TABLES `hr_payroll_items` WRITE;
/*!40000 ALTER TABLE `hr_payroll_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_payroll_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_payroll_no_sequences`
--

DROP TABLE IF EXISTS `hr_payroll_no_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_payroll_no_sequences` (
  `institute_id` bigint(20) unsigned NOT NULL,
  `last_sequence` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`institute_id`),
  CONSTRAINT `fk_hr_payroll_seq_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_payroll_no_sequences`
--

LOCK TABLES `hr_payroll_no_sequences` WRITE;
/*!40000 ALTER TABLE `hr_payroll_no_sequences` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_payroll_no_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_payroll_periods`
--

DROP TABLE IF EXISTS `hr_payroll_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_payroll_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `pay_frequency` enum('monthly','weekly','biweekly','fortnightly') NOT NULL DEFAULT 'monthly',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('draft','processing','approved','paid','cancelled','void') NOT NULL DEFAULT 'draft',
  `total_employees` int(10) unsigned NOT NULL DEFAULT 0,
  `total_gross` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `total_deductions` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `total_net` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_by` bigint(20) unsigned DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_payroll_periods_inst_branch_dates` (`institute_id`,`branch_id`,`start_date`,`end_date`),
  KEY `idx_hr_pp_inst` (`institute_id`),
  KEY `idx_hr_pp_branch` (`branch_id`),
  KEY `idx_hr_pp_status` (`status`),
  KEY `idx_hr_pp_dates` (`institute_id`,`start_date`,`end_date`),
  KEY `fk_hr_pp_currency` (`currency_id`),
  KEY `fk_hr_pp_generated` (`generated_by`),
  KEY `fk_hr_pp_approved` (`approved_by`),
  KEY `fk_hr_pp_paid` (`paid_by`),
  KEY `fk_hr_pp_cancelled` (`cancelled_by`),
  KEY `fk_hr_pp_created` (`created_by`),
  KEY `fk_hr_pp_updated` (`updated_by`),
  CONSTRAINT `fk_hr_pp_approved` FOREIGN KEY (`approved_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_pp_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_pp_cancelled` FOREIGN KEY (`cancelled_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_pp_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_pp_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_pp_generated` FOREIGN KEY (`generated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_pp_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_pp_paid` FOREIGN KEY (`paid_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_pp_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_payroll_periods`
--

LOCK TABLES `hr_payroll_periods` WRITE;
/*!40000 ALTER TABLE `hr_payroll_periods` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_payroll_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_payrolls`
--

DROP TABLE IF EXISTS `hr_payrolls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_payrolls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `payroll_period_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `salary_assignment_id` bigint(20) unsigned DEFAULT NULL,
  `payslip_no` varchar(40) NOT NULL,
  `status` enum('draft','approved','paid','cancelled','void') NOT NULL DEFAULT 'draft',
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `working_days` smallint(5) unsigned NOT NULL DEFAULT 0,
  `present_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `leave_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `unpaid_leave_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `overtime_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `overtime_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `gross_earnings` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `total_deductions` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `net_salary` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `earnings_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`earnings_snapshot`)),
  `deductions_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`deductions_snapshot`)),
  `calculation_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`calculation_snapshot`)),
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `payment_journal_id` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_by` bigint(20) unsigned DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_payrolls_inst_no` (`institute_id`,`payslip_no`),
  UNIQUE KEY `uq_hr_payrolls_period_employee` (`institute_id`,`payroll_period_id`,`employee_id`),
  KEY `idx_hr_payrolls_inst` (`institute_id`),
  KEY `idx_hr_payrolls_period` (`payroll_period_id`),
  KEY `idx_hr_payrolls_employee` (`employee_id`),
  KEY `idx_hr_payrolls_status` (`status`),
  KEY `idx_hr_payrolls_emp_status` (`institute_id`,`employee_id`,`status`),
  KEY `idx_hr_payrolls_branch` (`institute_id`,`branch_id`),
  KEY `fk_hr_payrolls_branch` (`branch_id`),
  KEY `fk_hr_payrolls_assignment` (`salary_assignment_id`),
  KEY `fk_hr_payrolls_currency` (`currency_id`),
  KEY `fk_hr_payrolls_journal` (`journal_id`),
  KEY `fk_hr_payrolls_pay_journal` (`payment_journal_id`),
  KEY `fk_hr_payrolls_created` (`created_by`),
  KEY `fk_hr_payrolls_updated` (`updated_by`),
  KEY `fk_hr_payrolls_approved` (`approved_by`),
  KEY `fk_hr_payrolls_paid` (`paid_by`),
  KEY `fk_hr_payrolls_cancelled` (`cancelled_by`),
  CONSTRAINT `fk_hr_payrolls_approved` FOREIGN KEY (`approved_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_payrolls_assignment` FOREIGN KEY (`salary_assignment_id`) REFERENCES `hr_employee_salary_assignments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_payrolls_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_payrolls_cancelled` FOREIGN KEY (`cancelled_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_payrolls_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_payrolls_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_payrolls_employee` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_payrolls_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_payrolls_journal` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_payrolls_paid` FOREIGN KEY (`paid_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_payrolls_pay_journal` FOREIGN KEY (`payment_journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_payrolls_period` FOREIGN KEY (`payroll_period_id`) REFERENCES `hr_payroll_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_payrolls_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_payrolls`
--

LOCK TABLES `hr_payrolls` WRITE;
/*!40000 ALTER TABLE `hr_payrolls` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_payrolls` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_performance_periods`
--

DROP TABLE IF EXISTS `hr_performance_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_performance_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('draft','active','closed') NOT NULL DEFAULT 'active',
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_perf_periods_inst_status` (`institute_id`,`status`),
  KEY `idx_hr_perf_periods_branch` (`branch_id`),
  KEY `fk_hr_perf_periods_created` (`created_by`),
  KEY `fk_hr_perf_periods_updated` (`updated_by`),
  CONSTRAINT `fk_hr_perf_periods_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_perf_periods_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_perf_periods_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_perf_periods_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_performance_periods`
--

LOCK TABLES `hr_performance_periods` WRITE;
/*!40000 ALTER TABLE `hr_performance_periods` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_performance_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_performance_review_kpis`
--

DROP TABLE IF EXISTS `hr_performance_review_kpis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_performance_review_kpis` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `review_id` bigint(20) unsigned NOT NULL,
  `kpi_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `target` varchar(150) DEFAULT NULL,
  `measurement` varchar(100) DEFAULT NULL,
  `weight` decimal(5,2) NOT NULL DEFAULT 1.00,
  `score` decimal(5,2) DEFAULT NULL,
  `max_score` decimal(5,2) NOT NULL DEFAULT 100.00,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_review_kpis_review` (`review_id`),
  KEY `fk_hr_review_kpis_kpi` (`kpi_id`),
  CONSTRAINT `fk_hr_review_kpis_kpi` FOREIGN KEY (`kpi_id`) REFERENCES `hr_kpis` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_review_kpis_review` FOREIGN KEY (`review_id`) REFERENCES `hr_performance_reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_performance_review_kpis`
--

LOCK TABLES `hr_performance_review_kpis` WRITE;
/*!40000 ALTER TABLE `hr_performance_review_kpis` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_performance_review_kpis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_performance_reviews`
--

DROP TABLE IF EXISTS `hr_performance_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_performance_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `reviewer_id` bigint(20) unsigned DEFAULT NULL,
  `period_id` bigint(20) unsigned NOT NULL,
  `review_date` date NOT NULL,
  `overall_score` decimal(5,2) DEFAULT NULL,
  `self_score` decimal(5,2) DEFAULT NULL,
  `manager_score` decimal(5,2) DEFAULT NULL,
  `hr_score` decimal(5,2) DEFAULT NULL,
  `status` enum('draft','pending','submitted','manager_review','hr_review','approved','rejected') NOT NULL DEFAULT 'draft',
  `comments` text DEFAULT NULL,
  `self_comments` text DEFAULT NULL,
  `manager_comments` text DEFAULT NULL,
  `hr_comments` text DEFAULT NULL,
  `promotion_recommendation` varchar(50) DEFAULT NULL,
  `training_recommendation` varchar(500) DEFAULT NULL,
  `improvement_plan` text DEFAULT NULL,
  `recognition` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_reviews_employee_period` (`employee_id`,`period_id`),
  KEY `idx_hr_reviews_inst_status` (`institute_id`,`status`),
  KEY `idx_hr_reviews_employee` (`employee_id`),
  KEY `idx_hr_reviews_reviewer` (`reviewer_id`),
  KEY `fk_hr_reviews_branch` (`branch_id`),
  KEY `fk_hr_reviews_period` (`period_id`),
  KEY `fk_hr_reviews_created` (`created_by`),
  KEY `fk_hr_reviews_updated` (`updated_by`),
  CONSTRAINT `fk_hr_reviews_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_reviews_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_reviews_employee` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_reviews_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_reviews_period` FOREIGN KEY (`period_id`) REFERENCES `hr_performance_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_reviews_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `hr_employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_reviews_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_performance_reviews`
--

LOCK TABLES `hr_performance_reviews` WRITE;
/*!40000 ALTER TABLE `hr_performance_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_performance_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_requisitions`
--

DROP TABLE IF EXISTS `hr_requisitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_requisitions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `designation_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `openings` smallint(5) unsigned NOT NULL DEFAULT 1,
  `employment_type` enum('full_time','part_time','contractual','permanent','temporary','intern','probation') DEFAULT NULL,
  `required_skills` text DEFAULT NULL,
  `experience` text DEFAULT NULL,
  `education` varchar(255) DEFAULT NULL,
  `salary_min` decimal(12,2) DEFAULT NULL,
  `salary_max` decimal(12,2) DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('draft','pending_approval','approved','rejected','published','closed','cancelled') NOT NULL DEFAULT 'draft',
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_req_inst` (`institute_id`),
  KEY `idx_hr_req_branch` (`branch_id`),
  KEY `idx_hr_req_status` (`institute_id`,`status`),
  KEY `idx_hr_req_dept` (`department_id`),
  KEY `fk_hr_req_desig` (`designation_id`),
  KEY `fk_hr_req_currency` (`currency_id`),
  KEY `fk_hr_req_requested` (`requested_by`),
  KEY `fk_hr_req_approved` (`approved_by`),
  KEY `fk_hr_req_created` (`created_by`),
  KEY `fk_hr_req_updated` (`updated_by`),
  CONSTRAINT `fk_hr_req_approved` FOREIGN KEY (`approved_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_req_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_req_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_req_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_req_dept` FOREIGN KEY (`department_id`) REFERENCES `hr_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_req_desig` FOREIGN KEY (`designation_id`) REFERENCES `hr_designations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_req_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_req_requested` FOREIGN KEY (`requested_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_req_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_requisitions`
--

LOCK TABLES `hr_requisitions` WRITE;
/*!40000 ALTER TABLE `hr_requisitions` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_requisitions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_salary_structure_components`
--

DROP TABLE IF EXISTS `hr_salary_structure_components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_salary_structure_components` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `salary_structure_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(40) NOT NULL,
  `component_type` enum('earning','deduction','tax','statutory') NOT NULL DEFAULT 'earning',
  `amount_type` enum('fixed','percent') NOT NULL DEFAULT 'fixed',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `percent_base` decimal(5,2) DEFAULT NULL,
  `is_taxable` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_ssc_structure_code` (`salary_structure_id`,`code`),
  KEY `idx_hr_ssc_inst` (`institute_id`),
  KEY `idx_hr_ssc_structure` (`salary_structure_id`),
  CONSTRAINT `fk_hr_ssc_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_ssc_structure` FOREIGN KEY (`salary_structure_id`) REFERENCES `hr_salary_structures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_salary_structure_components`
--

LOCK TABLES `hr_salary_structure_components` WRITE;
/*!40000 ALTER TABLE `hr_salary_structure_components` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_salary_structure_components` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_salary_structures`
--

DROP TABLE IF EXISTS `hr_salary_structures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_salary_structures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(40) NOT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `pay_frequency` enum('monthly','weekly','biweekly','fortnightly') NOT NULL DEFAULT 'monthly',
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `basic_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `housing_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `medical_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `transport_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `overtime_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bonus_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deduction_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_salary_structures_inst_code` (`institute_id`,`code`),
  KEY `idx_hr_salary_structures_inst` (`institute_id`),
  KEY `idx_hr_salary_structures_branch` (`branch_id`),
  KEY `idx_hr_salary_structures_dept` (`department_id`),
  KEY `idx_hr_salary_structures_currency` (`currency_id`),
  KEY `idx_hr_salary_structures_active` (`institute_id`,`is_active`),
  KEY `fk_hr_salary_structures_created` (`created_by`),
  KEY `fk_hr_salary_structures_updated` (`updated_by`),
  CONSTRAINT `fk_hr_salary_structures_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_salary_structures_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_salary_structures_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_salary_structures_dept` FOREIGN KEY (`department_id`) REFERENCES `hr_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_salary_structures_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_salary_structures_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_salary_structures`
--

LOCK TABLES `hr_salary_structures` WRITE;
/*!40000 ALTER TABLE `hr_salary_structures` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_salary_structures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_training_enrollments`
--

DROP TABLE IF EXISTS `hr_training_enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_training_enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `training_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `status` enum('enrolled','attending','completed','dropped','cancelled') NOT NULL DEFAULT 'enrolled',
  `attendance_status` enum('present','absent','partial') DEFAULT NULL,
  `enrollment_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `result` enum('pass','fail','pending') NOT NULL DEFAULT 'pending',
  `score` decimal(5,2) DEFAULT NULL,
  `certificate_path` varchar(255) DEFAULT NULL,
  `document_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_enrollments_training_employee` (`training_id`,`employee_id`),
  KEY `idx_hr_enrollments_inst_status` (`institute_id`,`status`),
  KEY `idx_hr_enrollments_employee` (`employee_id`),
  KEY `fk_hr_enroll_document` (`document_id`),
  CONSTRAINT `fk_hr_enroll_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_enroll_employee` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_enroll_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_enroll_training` FOREIGN KEY (`training_id`) REFERENCES `hr_trainings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_training_enrollments`
--

LOCK TABLES `hr_training_enrollments` WRITE;
/*!40000 ALTER TABLE `hr_training_enrollments` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_training_enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_trainings`
--

DROP TABLE IF EXISTS `hr_trainings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_trainings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `provider` varchar(150) DEFAULT NULL,
  `trainer` varchar(150) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `location` varchar(200) DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT 0,
  `capacity` int(10) unsigned DEFAULT NULL,
  `cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','planned','ongoing','completed','cancelled') NOT NULL DEFAULT 'planned',
  `enrolled_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_trainings_inst_status` (`institute_id`,`status`),
  KEY `idx_hr_trainings_branch` (`branch_id`),
  CONSTRAINT `fk_hr_trainings_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_trainings_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_trainings`
--

LOCK TABLES `hr_trainings` WRITE;
/*!40000 ALTER TABLE `hr_trainings` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_trainings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_vacancies`
--

DROP TABLE IF EXISTS `hr_vacancies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_vacancies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `requisition_id` bigint(20) unsigned DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `designation_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `openings` smallint(5) unsigned NOT NULL DEFAULT 1,
  `employment_type` enum('full_time','part_time','contractual','permanent','temporary','intern','probation') DEFAULT NULL,
  `salary_min` decimal(12,2) DEFAULT NULL,
  `salary_max` decimal(12,2) DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('draft','pending_approval','approved','published','closed','cancelled') NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_vac_inst` (`institute_id`),
  KEY `idx_hr_vac_branch` (`branch_id`),
  KEY `idx_hr_vac_req` (`requisition_id`),
  KEY `idx_hr_vac_status` (`institute_id`,`status`),
  KEY `fk_hr_vac_dept` (`department_id`),
  KEY `fk_hr_vac_desig` (`designation_id`),
  KEY `fk_hr_vac_currency` (`currency_id`),
  KEY `fk_hr_vac_created` (`created_by`),
  KEY `fk_hr_vac_updated` (`updated_by`),
  CONSTRAINT `fk_hr_vac_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_vac_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_vac_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_vac_dept` FOREIGN KEY (`department_id`) REFERENCES `hr_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_vac_desig` FOREIGN KEY (`designation_id`) REFERENCES `hr_designations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_vac_inst` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_vac_req` FOREIGN KEY (`requisition_id`) REFERENCES `hr_requisitions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_vac_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_vacancies`
--

LOCK TABLES `hr_vacancies` WRITE;
/*!40000 ALTER TABLE `hr_vacancies` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_vacancies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_work_shifts`
--

DROP TABLE IF EXISTS `hr_work_shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_work_shifts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `grace_minutes` smallint(5) unsigned NOT NULL DEFAULT 0,
  `working_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`working_days`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_shifts_institute` (`institute_id`),
  KEY `idx_hr_shifts_branch` (`branch_id`),
  KEY `idx_hr_shifts_employee` (`employee_id`),
  KEY `fk_hr_shifts_created` (`created_by`),
  KEY `fk_hr_shifts_updated` (`updated_by`),
  CONSTRAINT `fk_hr_shifts_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_shifts_created` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_shifts_employee` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_shifts_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_shifts_updated` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_work_shifts`
--

LOCK TABLES `hr_work_shifts` WRITE;
/*!40000 ALTER TABLE `hr_work_shifts` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_work_shifts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `identity_audit_logs`
--

DROP TABLE IF EXISTS `identity_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `identity_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(80) NOT NULL,
  `identifier_type` varchar(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `identity_audit_logs_user_id_event_index` (`user_id`,`event`),
  CONSTRAINT `identity_audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `identity_audit_logs`
--

LOCK TABLES `identity_audit_logs` WRITE;
/*!40000 ALTER TABLE `identity_audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `identity_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `import_batch_rows`
--

DROP TABLE IF EXISTS `import_batch_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_batch_rows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `import_batch_id` bigint(20) unsigned NOT NULL,
  `row_number` int(10) unsigned NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `status` enum('pending','success','failed') NOT NULL DEFAULT 'pending',
  `error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `import_batch_rows_import_batch_id_status_index` (`import_batch_id`,`status`),
  CONSTRAINT `import_batch_rows_import_batch_id_foreign` FOREIGN KEY (`import_batch_id`) REFERENCES `import_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `import_batch_rows`
--

LOCK TABLES `import_batch_rows` WRITE;
/*!40000 ALTER TABLE `import_batch_rows` DISABLE KEYS */;
/*!40000 ALTER TABLE `import_batch_rows` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `import_batches`
--

DROP TABLE IF EXISTS `import_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(50) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `total_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `success_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `failed_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `rollback_token` varchar(64) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed','rolled_back') NOT NULL DEFAULT 'pending',
  `errors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`errors`)),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `import_batches_rollback_token_unique` (`rollback_token`),
  KEY `import_batches_module_status_index` (`module`,`status`),
  KEY `import_batches_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `import_batches`
--

LOCK TABLES `import_batches` WRITE;
/*!40000 ALTER TABLE `import_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `import_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `industry_settings`
--

DROP TABLE IF EXISTS `industry_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `industry_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `industry_key` varchar(255) NOT NULL,
  `theme_slug` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `industry_key` (`industry_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `industry_settings`
--

LOCK TABLES `industry_settings` WRITE;
/*!40000 ALTER TABLE `industry_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `industry_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `industry_template_mappings`
--

DROP TABLE IF EXISTS `industry_template_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `industry_template_mappings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `industry` varchar(60) NOT NULL,
  `sub_industry` varchar(60) DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `structure_template_id` bigint(20) unsigned NOT NULL,
  `priority` int(10) unsigned NOT NULL DEFAULT 100,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `itm_industry_sub_country_unique` (`industry`,`sub_industry`,`country_id`),
  KEY `itm_industry_idx` (`industry`),
  KEY `itm_sub_industry_idx` (`sub_industry`),
  KEY `itm_country_idx` (`country_id`),
  KEY `itm_template_idx` (`structure_template_id`),
  KEY `itm_priority_idx` (`priority`),
  KEY `itm_status_idx` (`status`),
  KEY `itm_industry_sub_country_status_idx` (`industry`,`sub_industry`,`country_id`,`status`),
  CONSTRAINT `industry_template_mappings_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `industry_template_mappings_structure_template_id_foreign` FOREIGN KEY (`structure_template_id`) REFERENCES `structure_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `industry_template_mappings`
--

LOCK TABLES `industry_template_mappings` WRITE;
/*!40000 ALTER TABLE `industry_template_mappings` DISABLE KEYS */;
INSERT INTO `industry_template_mappings` VALUES (27,'education','school',NULL,15,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(28,'education','college',NULL,16,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(29,'education','polytechnic',NULL,22,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(30,'education','university',NULL,17,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(31,'education','madrasha',NULL,20,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(32,'education','primary_school',NULL,15,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(33,'education','secondary_high_school',NULL,15,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(34,'education','school_college',NULL,15,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(35,'training_center','training_institute',NULL,18,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(36,'training_center','professional_training_center',NULL,18,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(37,'training_center','dance_academy',NULL,25,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(38,'training_center','it_training_center',NULL,18,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(39,'training_center','vocational_training_center',NULL,21,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(40,'training_center','vocational_training_center',NULL,21,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(41,'training_center','institution',NULL,18,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(42,'training_center','professional_training_academy',NULL,18,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(43,'training_center','computer_it_training_institute',NULL,18,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(44,'training_center','computer_it_training_institute',NULL,18,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(45,'training_center','vocational_institute',NULL,21,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(46,'training_center','vocational_institute',NULL,21,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(47,'training_center','technical_training_center',NULL,22,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(48,'training_center','technical_training_center',NULL,22,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(49,'training_center','skill_development_center',NULL,21,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(50,'training_center','martial_arts',NULL,24,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(51,'training_center','music_academy',NULL,26,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(52,'training_center','sports_academy',NULL,27,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(53,'training_center','language_academy',NULL,28,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(54,'training_center','coaching_centre',NULL,19,100,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(55,'education',NULL,NULL,15,999,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(56,'training_center',NULL,NULL,18,999,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27');
/*!40000 ALTER TABLE `industry_template_mappings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `installments`
--

DROP TABLE IF EXISTS `installments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `installments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `installment_no` tinyint(3) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `due_date` date NOT NULL,
  `status` enum('pending','paid','overdue') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_installments_institute` (`institute_id`),
  KEY `idx_installments_invoice` (`invoice_id`),
  KEY `idx_installments_student` (`student_id`),
  CONSTRAINT `fk_installments_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_installments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_installments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `installments`
--

LOCK TABLES `installments` WRITE;
/*!40000 ALTER TABLE `installments` DISABLE KEYS */;
/*!40000 ALTER TABLE `installments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institute_academic_groups`
--

DROP TABLE IF EXISTS `institute_academic_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_academic_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `academic_group_id` bigint(20) unsigned DEFAULT NULL,
  `class_grade_id` bigint(20) unsigned DEFAULT NULL,
  `institute_class_grade_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) DEFAULT NULL,
  `display_order` int(10) unsigned DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_custom` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `institute_academic_groups_institute_id_academic_group_id_unique` (`institute_id`,`academic_group_id`),
  KEY `institute_academic_groups_academic_group_id_foreign` (`academic_group_id`),
  KEY `institute_academic_groups_class_grade_id_foreign` (`class_grade_id`),
  KEY `institute_academic_groups_institute_class_grade_id_foreign` (`institute_class_grade_id`),
  KEY `iag_institute_class_status_idx` (`institute_id`,`class_grade_id`,`status`),
  CONSTRAINT `institute_academic_groups_academic_group_id_foreign` FOREIGN KEY (`academic_group_id`) REFERENCES `academic_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `institute_academic_groups_class_grade_id_foreign` FOREIGN KEY (`class_grade_id`) REFERENCES `class_grades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `institute_academic_groups_institute_class_grade_id_foreign` FOREIGN KEY (`institute_class_grade_id`) REFERENCES `institute_class_grades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `institute_academic_groups_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institute_academic_groups`
--

LOCK TABLES `institute_academic_groups` WRITE;
/*!40000 ALTER TABLE `institute_academic_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `institute_academic_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institute_academic_levels`
--

DROP TABLE IF EXISTS `institute_academic_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_academic_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `academic_level_id` bigint(20) unsigned DEFAULT NULL,
  `education_system_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) DEFAULT NULL,
  `display_order` int(10) unsigned DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_custom` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `institute_academic_levels_institute_id_academic_level_id_unique` (`institute_id`,`academic_level_id`),
  KEY `institute_academic_levels_academic_level_id_foreign` (`academic_level_id`),
  KEY `institute_academic_levels_education_system_id_foreign` (`education_system_id`),
  KEY `ial_institute_system_status_idx` (`institute_id`,`education_system_id`,`status`),
  CONSTRAINT `institute_academic_levels_academic_level_id_foreign` FOREIGN KEY (`academic_level_id`) REFERENCES `academic_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `institute_academic_levels_education_system_id_foreign` FOREIGN KEY (`education_system_id`) REFERENCES `education_systems` (`id`) ON DELETE CASCADE,
  CONSTRAINT `institute_academic_levels_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institute_academic_levels`
--

LOCK TABLES `institute_academic_levels` WRITE;
/*!40000 ALTER TABLE `institute_academic_levels` DISABLE KEYS */;
/*!40000 ALTER TABLE `institute_academic_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institute_class_grades`
--

DROP TABLE IF EXISTS `institute_class_grades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_class_grades` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `class_grade_id` bigint(20) unsigned DEFAULT NULL,
  `academic_level_id` bigint(20) unsigned DEFAULT NULL,
  `institute_academic_level_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) DEFAULT NULL,
  `sequence` int(10) unsigned DEFAULT NULL,
  `display_order` int(10) unsigned DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_custom` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `institute_class_grades_institute_id_class_grade_id_unique` (`institute_id`,`class_grade_id`),
  KEY `institute_class_grades_class_grade_id_foreign` (`class_grade_id`),
  KEY `institute_class_grades_academic_level_id_foreign` (`academic_level_id`),
  KEY `institute_class_grades_institute_academic_level_id_foreign` (`institute_academic_level_id`),
  KEY `icg_institute_level_status_idx` (`institute_id`,`academic_level_id`,`status`),
  KEY `icg_institute_customlevel_status_idx` (`institute_id`,`institute_academic_level_id`,`status`),
  CONSTRAINT `institute_class_grades_academic_level_id_foreign` FOREIGN KEY (`academic_level_id`) REFERENCES `academic_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `institute_class_grades_class_grade_id_foreign` FOREIGN KEY (`class_grade_id`) REFERENCES `class_grades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `institute_class_grades_institute_academic_level_id_foreign` FOREIGN KEY (`institute_academic_level_id`) REFERENCES `institute_academic_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `institute_class_grades_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institute_class_grades`
--

LOCK TABLES `institute_class_grades` WRITE;
/*!40000 ALTER TABLE `institute_class_grades` DISABLE KEYS */;
/*!40000 ALTER TABLE `institute_class_grades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institute_courses`
--

DROP TABLE IF EXISTS `institute_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_courses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `assigned_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_institute_course` (`institute_id`,`course_id`),
  KEY `idx_inst_courses_institute` (`institute_id`),
  KEY `idx_inst_courses_course` (`course_id`),
  KEY `fk_inst_courses_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_inst_courses_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inst_courses_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inst_courses_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=214 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institute_courses`
--

LOCK TABLES `institute_courses` WRITE;
/*!40000 ALTER TABLE `institute_courses` DISABLE KEYS */;
/*!40000 ALTER TABLE `institute_courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institute_module_entitlements`
--

DROP TABLE IF EXISTS `institute_module_entitlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_module_entitlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `module_key` varchar(60) NOT NULL,
  `status` enum('active','expired','revoked','trialing','pending') NOT NULL DEFAULT 'active',
  `is_grant` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'true=grant, false=explicit deny',
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `trial_starts_at` timestamp NULL DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `monthly_price` decimal(10,2) DEFAULT NULL,
  `yearly_price` decimal(10,2) DEFAULT NULL,
  `billing_cycle` enum('monthly','yearly','one_time') DEFAULT NULL,
  `auto_renew` tinyint(1) NOT NULL DEFAULT 0,
  `discount_percent` decimal(5,2) DEFAULT NULL,
  `purchased_by` bigint(20) unsigned DEFAULT NULL,
  `granted_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `institute_module_entitlements_purchased_by_foreign` (`purchased_by`),
  KEY `institute_module_entitlements_granted_by_foreign` (`granted_by`),
  KEY `idx_ime_inst_module` (`institute_id`,`module_key`),
  KEY `idx_ime_inst_status_ends` (`institute_id`,`status`,`ends_at`),
  KEY `idx_ime_trial_ends` (`trial_ends_at`),
  KEY `idx_ime_starts` (`starts_at`),
  KEY `idx_ime_module` (`module_key`),
  CONSTRAINT `institute_module_entitlements_granted_by_foreign` FOREIGN KEY (`granted_by`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `institute_module_entitlements_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `institute_module_entitlements_purchased_by_foreign` FOREIGN KEY (`purchased_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1087 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institute_module_entitlements`
--

LOCK TABLES `institute_module_entitlements` WRITE;
/*!40000 ALTER TABLE `institute_module_entitlements` DISABLE KEYS */;
/*!40000 ALTER TABLE `institute_module_entitlements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institute_module_overrides`
--

DROP TABLE IF EXISTS `institute_module_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_module_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `module_key` varchar(60) NOT NULL,
  `enabled` tinyint(1) NOT NULL,
  `overridden_by` bigint(20) unsigned DEFAULT NULL COMMENT 'User or PlatformAdmin ID',
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `institute_module_overrides_institute_id_module_key_unique` (`institute_id`,`module_key`),
  CONSTRAINT `institute_module_overrides_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institute_module_overrides`
--

LOCK TABLES `institute_module_overrides` WRITE;
/*!40000 ALTER TABLE `institute_module_overrides` DISABLE KEYS */;
/*!40000 ALTER TABLE `institute_module_overrides` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institute_payment_gateways`
--

DROP TABLE IF EXISTS `institute_payment_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_payment_gateways` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `gateway_id` bigint(20) unsigned NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `credentials` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`credentials`)),
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `institute_payment_gateways_institute_id_gateway_id_unique` (`institute_id`,`gateway_id`),
  KEY `institute_payment_gateways_gateway_id_foreign` (`gateway_id`),
  CONSTRAINT `institute_payment_gateways_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `payment_gateways` (`id`) ON DELETE CASCADE,
  CONSTRAINT `institute_payment_gateways_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institute_payment_gateways`
--

LOCK TABLES `institute_payment_gateways` WRITE;
/*!40000 ALTER TABLE `institute_payment_gateways` DISABLE KEYS */;
/*!40000 ALTER TABLE `institute_payment_gateways` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institute_settings`
--

DROP TABLE IF EXISTS `institute_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `theme` varchar(40) NOT NULL DEFAULT 'default',
  `primary_color` varchar(10) NOT NULL DEFAULT '#0D6EFD',
  `secondary_color` varchar(10) NOT NULL DEFAULT '#FFC107',
  `sidebar_color` varchar(10) DEFAULT NULL,
  `tall_navigation` tinyint(1) NOT NULL DEFAULT 0,
  `ai_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_config`)),
  `training_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`training_config`)),
  `logo` varchar(255) DEFAULT NULL,
  `favicon` varchar(255) DEFAULT NULL,
  `smtp_host` varchar(150) DEFAULT NULL,
  `smtp_port` smallint(5) unsigned DEFAULT NULL,
  `smtp_username` varchar(150) DEFAULT NULL,
  `smtp_password_enc` varchar(255) DEFAULT NULL,
  `smtp_encryption` enum('none','ssl','tls') DEFAULT NULL,
  `sms_provider` varchar(60) DEFAULT NULL,
  `sms_api_key_enc` varchar(255) DEFAULT NULL,
  `payment_gateway` varchar(60) DEFAULT NULL,
  `payment_config_enc` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_config_enc`)),
  `timezone` varchar(60) NOT NULL DEFAULT 'Asia/Dhaka',
  `language` varchar(10) NOT NULL DEFAULT 'bn',
  `notification_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_settings`)),
  `certificate_approval_mode` varchar(20) NOT NULL DEFAULT 'admin',
  `sales_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sales_config`)),
  `purchase_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`purchase_config`)),
  `academic_unit_label` varchar(40) DEFAULT NULL,
  `structure_template_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_institute_settings_institute` (`institute_id`),
  KEY `institute_settings_structure_template_id_foreign` (`structure_template_id`),
  KEY `institute_settings_certificate_approval_mode_index` (`certificate_approval_mode`),
  CONSTRAINT `fk_institute_settings_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `institute_settings_structure_template_id_foreign` FOREIGN KEY (`structure_template_id`) REFERENCES `structure_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institute_settings`
--

LOCK TABLES `institute_settings` WRITE;
/*!40000 ALTER TABLE `institute_settings` DISABLE KEYS */;
INSERT INTO `institute_settings` VALUES (32,63,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:29','2026-09-01 14:24:29'),(33,68,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,17,'2026-09-01 14:24:29','2026-09-01 14:24:29'),(34,72,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:30','2026-09-01 14:24:30'),(35,75,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:30','2026-09-01 14:24:30'),(36,76,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,16,'2026-09-01 14:24:30','2026-09-01 14:24:30'),(37,79,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,17,'2026-09-01 14:24:30','2026-09-01 14:24:30'),(38,84,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:31','2026-09-01 14:24:31'),(39,90,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,17,'2026-09-01 14:24:31','2026-09-01 14:24:31'),(40,97,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:33','2026-09-01 14:24:33'),(41,102,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,17,'2026-09-01 14:24:33','2026-09-01 14:24:33'),(42,106,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:34','2026-09-01 14:24:34'),(43,110,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,17,'2026-09-01 14:24:34','2026-09-01 14:24:34'),(44,114,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:34','2026-09-01 14:24:34'),(45,116,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:35','2026-09-01 14:24:35'),(46,118,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:35','2026-09-01 14:24:35'),(47,120,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:36','2026-09-01 14:24:36'),(48,122,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,17,'2026-09-01 14:24:36','2026-09-01 14:24:36'),(49,124,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:36','2026-09-01 14:24:36'),(50,125,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:36','2026-09-01 14:24:36'),(51,130,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:37','2026-09-01 14:24:37'),(52,132,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:38','2026-09-01 14:24:38'),(53,134,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:39','2026-09-01 14:24:39'),(54,136,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:24:40','2026-09-01 14:24:40'),(55,159,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:16','2026-09-01 14:36:16'),(56,160,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,17,'2026-09-01 14:36:16','2026-09-01 14:36:16'),(57,163,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,17,'2026-09-01 14:36:17','2026-09-01 14:36:17'),(58,167,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:17','2026-09-01 14:36:17'),(59,170,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,16,'2026-09-01 14:36:17','2026-09-01 14:36:17'),(60,172,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,17,'2026-09-01 14:36:18','2026-09-01 14:36:18'),(61,175,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:18','2026-09-01 14:36:18'),(62,181,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:19','2026-09-01 14:36:19'),(63,185,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,17,'2026-09-01 14:36:19','2026-09-01 14:36:19'),(64,189,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:20','2026-09-01 14:36:20'),(65,193,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,17,'2026-09-01 14:36:20','2026-09-01 14:36:20'),(66,198,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:21','2026-09-01 14:36:21'),(67,201,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:21','2026-09-01 14:36:21'),(68,202,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:22','2026-09-01 14:36:22'),(69,204,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:22','2026-09-01 14:36:22'),(70,206,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:22','2026-09-01 14:36:22'),(71,209,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,17,'2026-09-01 14:36:23','2026-09-01 14:36:23'),(72,211,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:23','2026-09-01 14:36:23'),(73,212,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:23','2026-09-01 14:36:23'),(74,217,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:24','2026-09-01 14:36:24'),(75,219,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:25','2026-09-01 14:36:25'),(76,220,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:25','2026-09-01 14:36:25'),(77,221,'default','#0D6EFD','#FFC107',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Asia/Dhaka','bn',NULL,'admin',NULL,NULL,NULL,15,'2026-09-01 14:36:26','2026-09-01 14:36:26');
/*!40000 ALTER TABLE `institute_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institute_subjects`
--

DROP TABLE IF EXISTS `institute_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) DEFAULT NULL,
  `display_order` int(10) unsigned DEFAULT NULL,
  `requirement_type` varchar(20) DEFAULT NULL,
  `selection_group_id` bigint(20) unsigned DEFAULT NULL,
  `minimum_selection` int(10) unsigned DEFAULT NULL,
  `maximum_selection` int(10) unsigned DEFAULT NULL,
  `credit_hours` decimal(5,2) DEFAULT NULL,
  `gpa_included` tinyint(1) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `is_custom` tinyint(1) NOT NULL DEFAULT 0,
  `assigned_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_institute_subject` (`institute_id`,`subject_id`),
  KEY `idx_inst_subjects_institute` (`institute_id`),
  KEY `idx_inst_subjects_subject` (`subject_id`),
  KEY `fk_inst_subjects_assigned_by` (`assigned_by`),
  KEY `institute_subjects_selection_group_id_foreign` (`selection_group_id`),
  CONSTRAINT `fk_inst_subjects_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inst_subjects_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inst_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `institute_subjects_selection_group_id_foreign` FOREIGN KEY (`selection_group_id`) REFERENCES `academic_selection_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `institute_subjects_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institute_subjects`
--

LOCK TABLES `institute_subjects` WRITE;
/*!40000 ALTER TABLE `institute_subjects` DISABLE KEYS */;
/*!40000 ALTER TABLE `institute_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institute_subscriptions`
--

DROP TABLE IF EXISTS `institute_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `package_id` bigint(20) unsigned NOT NULL,
  `billing_cycle` enum('monthly','yearly','trial') NOT NULL DEFAULT 'monthly',
  `price_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `payment_reference` varchar(120) DEFAULT NULL,
  `status` enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inst_subs_institute` (`institute_id`),
  KEY `idx_inst_subs_package` (`package_id`),
  CONSTRAINT `fk_inst_subs_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inst_subs_package` FOREIGN KEY (`package_id`) REFERENCES `subscription_packages` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institute_subscriptions`
--

LOCK TABLES `institute_subscriptions` WRITE;
/*!40000 ALTER TABLE `institute_subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `institute_subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institute_users`
--

DROP TABLE IF EXISTS `institute_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(60) NOT NULL DEFAULT '',
  `last_name` varchar(60) NOT NULL DEFAULT '',
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `employee_id` varchar(40) DEFAULT NULL,
  `father_name` varchar(120) DEFAULT NULL,
  `mother_name` varchar(120) DEFAULT NULL,
  `religion` varchar(30) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `preferred_language` varchar(10) NOT NULL DEFAULT 'en',
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `photo` varchar(255) DEFAULT NULL,
  `nid_photo` varchar(255) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `designation` varchar(80) DEFAULT NULL,
  `department` varchar(80) DEFAULT NULL,
  `qualification` varchar(150) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `preferred_2fa_method` varchar(20) DEFAULT NULL,
  `sms_2fa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `email_2fa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `remember_token` varchar(100) DEFAULT NULL,
  `failed_login_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `inactivity_warning_sent_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `full_name` varchar(121) GENERATED ALWAYS AS (trim(concat(`first_name`,' ',`last_name`))) STORED,
  `name` varchar(121) GENERATED ALWAYS AS (trim(concat(`first_name`,' ',`last_name`))) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_institute_users_inst_email` (`institute_id`,`email`),
  UNIQUE KEY `uq_institute_users_uuid` (`uuid`),
  UNIQUE KEY `uq_institute_users_phone` (`phone`),
  UNIQUE KEY `uq_institute_users_email` (`email`),
  UNIQUE KEY `uq_institute_users_institute_employee` (`institute_id`,`employee_id`),
  KEY `idx_institute_users_institute` (`institute_id`),
  KEY `idx_institute_users_branch` (`branch_id`),
  KEY `idx_institute_users_role` (`role_id`),
  CONSTRAINT `fk_institute_users_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_institute_users_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_institute_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institute_users`
--

LOCK TABLES `institute_users` WRITE;
/*!40000 ALTER TABLE `institute_users` DISABLE KEYS */;
INSERT INTO `institute_users` VALUES (42,'Owner','6a968bbc4b65b','8c777e8c-a5de-11f1-9275-e0d55e5927b4',59,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bbc4b65b@test.com','+8801510683119','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$vzqE97UiNCpps.ZNT43sb.XZ8Q3w/LDVn0FoWHMPm95paxN4jQ9r6','2026-09-01 08:24:28',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:28','2026-09-01 14:24:28','Owner 6a968bbc4b65b','Owner 6a968bbc4b65b'),(43,'Owner','6a968bbd10661','8ceaa89e-a5de-11f1-9275-e0d55e5927b4',61,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bbd10661@test.com','+8801251069068','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$02qlWroEG.8OeoGfNUYPvuQ2O4Mj97/xL8lHnyw8.nte7S9k8bIAO','2026-09-01 08:24:29',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29','Owner 6a968bbd10661','Owner 6a968bbd10661'),(44,'Owner','6a968bbd6d18c','8d24b5bf-a5de-11f1-9275-e0d55e5927b4',64,NULL,9,NULL,NULL,NULL,NULL,'o6a968bbd6d18c@test.com','+8801510694479','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$mZB/uHHljfOYAGJA.0Ndz.aXBmbrY4FMTN6MqMU0MigcjaybQe9SK','2026-09-01 08:24:29',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29','Owner 6a968bbd6d18c','Owner 6a968bbd6d18c'),(45,'Owner','6a968bbd76906','8d2a89a0-a5de-11f1-9275-e0d55e5927b4',66,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bbd76906@test.com','+8801510694863','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$qO7LF8fjhfEjJ7LkY0S5W.D9gyGNgpwclE9JI3YOxwB/wkfTxy42W','2026-09-01 08:24:29',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29','Owner 6a968bbd76906','Owner 6a968bbd76906'),(46,'Owner','6a968bbdc2793','8d5a01a2-a5de-11f1-9275-e0d55e5927b4',70,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bbdc2793@test.com','+8801510697973','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$FZYeIxI9tol8ngdL7xpk6ese7R9.Nrp0ZoAOjrG6wQfN1t8xy8sOy','2026-09-01 08:24:29',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29','Owner 6a968bbdc2793','Owner 6a968bbdc2793'),(47,'Owner','6a968bbe2778b','8d9199ec-a5de-11f1-9275-e0d55e5927b4',74,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bbe2778b@test.com','+8801510701623','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$gFNvodl7Uayw6ctPFHPOeuFl2oKNKD.YGJU07QxkYtmCai67Ctb3q','2026-09-01 08:24:30',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30','Owner 6a968bbe2778b','Owner 6a968bbe2778b'),(48,'Owner','6a968bbe48e1f','8da6b501-a5de-11f1-9275-e0d55e5927b4',75,NULL,9,NULL,NULL,NULL,NULL,'o6a968bbe48e1f@test.com','+8801510702993','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$EruZya9m5OnwF3xn8WGliO2KhQZauUk0aUiDC1415c48031JNL5NW','2026-09-01 08:24:30',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30','Owner 6a968bbe48e1f','Owner 6a968bbe48e1f'),(49,'Owner','6a968bbe85cf2','8dcc9d2a-a5de-11f1-9275-e0d55e5927b4',78,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bbe85cf2@test.com','+8801510705488','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$3bmOeExcRwaRruXlFWGz1.9NeYMltiAuE4zehyicSsQ.J52CuLNG2','2026-09-01 08:24:30',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30','Owner 6a968bbe85cf2','Owner 6a968bbe85cf2'),(50,'Owner','6a968bbedc1b0','8e028587-a5de-11f1-9275-e0d55e5927b4',82,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bbedc1b0@test.com','+8801510709023','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$IdTdmUZbnkt3WHcTtShwveKq0zDamjMJd/ZJArt9295MH7Gy6MUM.','2026-09-01 08:24:30',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30','Owner 6a968bbedc1b0','Owner 6a968bbedc1b0'),(51,'Owner','6a968bbeef435','8e0e8405-a5de-11f1-9275-e0d55e5927b4',83,NULL,9,NULL,NULL,NULL,NULL,'o6a968bbeef435@test.com','+8801510709806','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$NF3z45S8WB43Vq0bb3QgaOJ1T/oTw1DWk9zLyv5hRg2/BSb9Cpdea','2026-09-01 08:24:30',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30','Owner 6a968bbeef435','Owner 6a968bbeef435'),(52,'Owner','6a968bbf196c0','8e218518-a5de-11f1-9275-e0d55e5927b4',84,NULL,9,NULL,NULL,NULL,NULL,'o6a968bbf196c0@test.com','+8801251071105','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$zMmQY.kyZve/6nrtUrdjS.FY4RpTHv7Fcnw61gTp734/vW8/tqbmi','2026-09-01 08:24:31',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:31','2026-09-01 14:24:31','Owner 6a968bbf196c0','Owner 6a968bbf196c0'),(53,'Owner','6a968bbf490a8','8e3f494c-a5de-11f1-9275-e0d55e5927b4',87,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bbf490a8@test.com','+8801510713001','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$uHNHxB259B1WyejCDsFJxuHc9rwzL9Ghz8zLqzglPO79MN9T6H1Ly','2026-09-01 08:24:31',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:31','2026-09-01 14:24:31','Owner 6a968bbf490a8','Owner 6a968bbf490a8'),(54,'Owner','6a968bbfa320d','8e77aee3-a5de-11f1-9275-e0d55e5927b4',89,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bbfa320d@test.com','+8801510716691','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$p40QyIWmByIGmr8KIJhkdusPxR8CY3jv1fo4B0dOSiSVANDhZG8Oq','2026-09-01 08:24:31',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:31','2026-09-01 14:24:31','Owner 6a968bbfa320d','Owner 6a968bbfa320d'),(55,'Owner','6a968bc01ee08','8ebd6ccf-a5de-11f1-9275-e0d55e5927b4',91,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bc01ee08@test.com','+8801510721272','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$bKYsOAD.NW2Jm5ifjqcEueUFD.m2mjpLPcJFHy3fdonMQZ.PDdkxK','2026-09-01 08:24:32',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:32','2026-09-01 14:24:32','Owner 6a968bc01ee08','Owner 6a968bc01ee08'),(56,'Owner','6a968bc07afbe','8ef70abf-a5de-11f1-9275-e0d55e5927b4',93,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bc07afbe@test.com','+8801510725044','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$ir5zQpQf5BCj5BKbrpL82eoBaAAboyGczpvX./w6WRRTsM4dg4VCW','2026-09-01 08:24:32',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:32','2026-09-01 14:24:32','Owner 6a968bc07afbe','Owner 6a968bc07afbe'),(57,'Owner','6a968bc0d83d6','8f315a00-a5de-11f1-9275-e0d55e5927b4',95,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bc0d83d6@test.com','+8801510728866','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$fQd1OkdDu81sZfSoFzOkweq5Fjab2BF/qX1O6703EPcalZaDzwaR.','2026-09-01 08:24:32',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:32','2026-09-01 14:24:32','Owner 6a968bc0d83d6','Owner 6a968bc0d83d6'),(58,'Owner','6a968bc13d94b','8f694021-a5de-11f1-9275-e0d55e5927b4',98,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bc13d94b@test.com','+8801251073253','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$o8F0AVNg/PV6GFpjs30fX.WadRQ43jKx2I7Ej2Hu.42sLpkDE3o72','2026-09-01 08:24:33',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:33','2026-09-01 14:24:33','Owner 6a968bc13d94b','Owner 6a968bc13d94b'),(59,'Owner','6a968bc13e702','8f69c765-a5de-11f1-9275-e0d55e5927b4',97,NULL,9,NULL,NULL,NULL,NULL,'o6a968bc13e702@test.com','+8801510732566','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$p2ZyV.fbIwYpSV/GFLmM9.4mHFo27mRukxwsiCRmVbzA7H7Q95j.6','2026-09-01 08:24:33',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:33','2026-09-01 14:24:33','Owner 6a968bc13e702','Owner 6a968bc13e702'),(60,'Owner','6a968bc192f3b','8f9e974e-a5de-11f1-9275-e0d55e5927b4',100,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bc192f3b@test.com','+8801510736026','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$AqWTUmbLBgp1WLbnXQG7Au2OBGC1b7djEk0uhV2OPKLulU/bKu0bO','2026-09-01 08:24:33',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:33','2026-09-01 14:24:33','Owner 6a968bc192f3b','Owner 6a968bc192f3b'),(61,'Owner','6a968bc19e4a0','8fa5cc0f-a5de-11f1-9275-e0d55e5927b4',102,NULL,9,NULL,NULL,NULL,NULL,'o6a968bc19e4a0@test.com','+8801510736492','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$J.oiwcWq.FtA0ZIAg7Q2TO0IVZTsSl2nEn.3P/C44KNPtJckfcFp6','2026-09-01 08:24:33',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:33','2026-09-01 14:24:33','Owner 6a968bc19e4a0','Owner 6a968bc19e4a0'),(62,'Owner','6a968bc1f118e','8fd966a1-a5de-11f1-9275-e0d55e5927b4',105,NULL,9,NULL,NULL,NULL,NULL,'owner6a968bc1f118e@test.com','+8801510739883','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$Ym7DxBH7.vG1c8y4frTgKuatnsK8WVjZ..0TEazxl4Q88xpPC2hl6','2026-09-01 08:24:33',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:33','2026-09-01 14:24:33','Owner 6a968bc1f118e','Owner 6a968bc1f118e'),(63,'Owner','6a968bc210cc6','8fe5ceb9-a5de-11f1-9275-e0d55e5927b4',106,NULL,9,NULL,NULL,NULL,NULL,'o6a968bc210cc6@test.com','+8801510740695','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$nioHYTmqDj4.ZNQBpBbeLOkwpT0rJRHUeK75T1cDKp9U//PH9Q2eK','2026-09-01 08:24:34',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:34','2026-09-01 14:24:34','Owner 6a968bc210cc6','Owner 6a968bc210cc6'),(64,'Owner','6a968bc271dea','9022865b-a5de-11f1-9275-e0d55e5927b4',110,NULL,9,NULL,NULL,NULL,NULL,'o6a968bc271dea@test.com','+8801510744673','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$r9qnQq2eeYjckpJGpF0I9OiOQVRiBtwCe9P0u2I.0L0CZvsDnQFrG','2026-09-01 08:24:34',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:34','2026-09-01 14:24:34','Owner 6a968bc271dea','Owner 6a968bc271dea'),(65,'Owner','6a968bc2f34db','90737797-a5de-11f1-9275-e0d55e5927b4',114,NULL,9,NULL,NULL,NULL,NULL,'o6a968bc2f34db@test.com','+8801510749974','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$L5wgfDpB0BPBd2ref4mlSufs2wp3FYEpFT5cGgwU73sBWRZE/VyQ2','2026-09-01 08:24:34',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:34','2026-09-01 14:24:34','Owner 6a968bc2f34db','Owner 6a968bc2f34db'),(66,'Owner','6a968bc35cf8e','90ae141c-a5de-11f1-9275-e0d55e5927b4',116,NULL,9,NULL,NULL,NULL,NULL,'o6a968bc35cf8e@test.com','+8801510753817','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$gzLVo1604fwYu/IS4YEYpONQRj27XgijXDuGOVoFS/tHHbBbtmWHO','2026-09-01 08:24:35',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:35','2026-09-01 14:24:35','Owner 6a968bc35cf8e','Owner 6a968bc35cf8e'),(67,'Owner','6a968bc3bb903','90e95a8e-a5de-11f1-9275-e0d55e5927b4',118,NULL,9,NULL,NULL,NULL,NULL,'o6a968bc3bb903@test.com','+8801251075769','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$BYIWslGazL42VpXYuaDGlefEMUjw0voClXGH/oUhjvT.9im73JsCC','2026-09-01 08:24:35',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:35','2026-09-01 14:24:35','Owner 6a968bc3bb903','Owner 6a968bc3bb903'),(68,'Owner','6a968bc41f48c','912006c9-a5de-11f1-9275-e0d55e5927b4',120,NULL,9,NULL,NULL,NULL,NULL,'o6a968bc41f48c@test.com','+8801510761288','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$KkkyEPEyDJg.GugPsKWS5u4D3yTNnMIyRcW8zaKMXKH45oyDkeIHK','2026-09-01 08:24:36',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:36','2026-09-01 14:24:36','Owner 6a968bc41f48c','Owner 6a968bc41f48c'),(69,'Owner','6a968bc477ebb','91579714-a5de-11f1-9275-e0d55e5927b4',122,NULL,9,NULL,NULL,NULL,NULL,'o6a968bc477ebb@test.com','+8801510764922','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$7oRNi0fkKfr2Y1B4bYU3cu/0y336IRhiKxMnRm1o64KH5PKmD06Qm','2026-09-01 08:24:36',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:36','2026-09-01 14:24:36','Owner 6a968bc477ebb','Owner 6a968bc477ebb'),(70,'Owner','6a968bc4db1f7','9196716f-a5de-11f1-9275-e0d55e5927b4',125,NULL,9,NULL,NULL,NULL,NULL,'o6a968bc4db1f7@test.com','+8801510768985','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$WUFsipb4fEYXpZ2Uv8pouu/yoLza/x0aEVTbjVR135f8qdWGslxKe','2026-09-01 08:24:36',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:36','2026-09-01 14:24:36','Owner 6a968bc4db1f7','Owner 6a968bc4db1f7'),(71,'Owner','6a968bc547925','91d1dff7-a5de-11f1-9275-e0d55e5927b4',128,NULL,9,NULL,NULL,NULL,NULL,'o6a968bc547925@test.com','+8801251077294','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$EdW82Crgf8X1MGTTALeYP.QM0BDBGgLP4Xs91i8rgy7RgiKNAL2tu','2026-09-01 08:24:37',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:37','2026-09-01 14:24:37','Owner 6a968bc547925','Owner 6a968bc547925'),(72,'Owner','6a968bc5de6b3','92301548-a5de-11f1-9275-e0d55e5927b4',130,NULL,9,NULL,NULL,NULL,NULL,'o6a968bc5de6b3@test.com','+8801510779118','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$GKNQN89/GLv3lmzNXH7WeOAE65..aaewBImmDCxhDexyOOxZr5GnC','2026-09-01 08:24:37',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:37','2026-09-01 14:24:37','Owner 6a968bc5de6b3','Owner 6a968bc5de6b3'),(73,'Owner','6a968bc6cfa0b','92c25ef9-a5de-11f1-9275-e0d55e5927b4',132,NULL,9,NULL,NULL,NULL,NULL,'o6a968bc6cfa0b@test.com','+8801510788514','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$QLhOSaCpFII7gM.cO2tSCOwXR1S7/idEIoNVzvGKNJMcZJ.yOVU.y','2026-09-01 08:24:38',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:38','2026-09-01 14:24:38','Owner 6a968bc6cfa0b','Owner 6a968bc6cfa0b'),(74,'View','6a968bc77c235','93240127-a5de-11f1-9275-e0d55e5927b4',134,NULL,10,NULL,NULL,NULL,NULL,'v6a968bc77c237@test.com','+8801510795085','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$jg/cKR6BnO0J/Eeumn9Y..543fPti.FzNjZD/GGrxU5Sc.m2.6GIW','2026-09-01 08:24:39',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:39','2026-09-01 14:24:39','View 6a968bc77c235','View 6a968bc77c235'),(75,'Owner','6a968bc853b42','93a33e25-a5de-11f1-9275-e0d55e5927b4',136,NULL,9,NULL,NULL,NULL,NULL,'o6a968bc853b42@test.com','+8801510803437','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$bJW9ZxMuWHuq/VEmPCHd9ujypkPfRh9pu7qwX8Y/aL24hIOjPmYIW','2026-09-01 08:24:40',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:24:40','2026-09-01 14:24:40','Owner 6a968bc853b42','Owner 6a968bc853b42'),(76,'Owner','6a968e7e0c138','311f0545-a5e0-11f1-9275-e0d55e5927b4',147,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e7e0c138@test.com','+8801517740505','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$qtq/b8u3WMQbwy0/qTuRNeNXalhn.WtPjHiwV/s53GcbG1Vj7a7yy','2026-09-01 08:36:14',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:14','2026-09-01 14:36:14','Owner 6a968e7e0c138','Owner 6a968e7e0c138'),(77,'Owner','6a968e7ec66ac','31936719-a5e0-11f1-9275-e0d55e5927b4',150,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e7ec66ac@test.com','+8801517748138','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$UMc7cK55Jm6s4qFGjASMaedI.jHTOB8lXfVzg79TAd.R6ioNL9nMe','2026-09-01 08:36:14',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:14','2026-09-01 14:36:14','Owner 6a968e7ec66ac','Owner 6a968e7ec66ac'),(78,'Owner','6a968e7f56837','31e593d2-a5e0-11f1-9275-e0d55e5927b4',151,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e7f56837@test.com','+8801517753554','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$QgbnoYG/3Jc5AJRj/iOWAOblKUbwjnZ7ZV4.fQEZukr7aJgo6pXUq','2026-09-01 08:36:15',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:15','2026-09-01 14:36:15','Owner 6a968e7f56837','Owner 6a968e7f56837'),(79,'Owner','6a968e7fb38f5','321fc07a-a5e0-11f1-9275-e0d55e5927b4',154,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e7fb38f5@test.com','+8801517757365','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$YsVInS/OgTQMPDNZOz46hePfzaM.zzeLwaH/gcX9/Oe7WbBjMyaBG','2026-09-01 08:36:15',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:15','2026-09-01 14:36:15','Owner 6a968e7fb38f5','Owner 6a968e7fb38f5'),(80,'Owner','6a968e802d121','326444bd-a5e0-11f1-9275-e0d55e5927b4',156,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e802d121@test.com','+8801517761855','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$biDLdDKCmy2RP3BiKyuicuCPggRbco0VeOhgjM8VK39ekA3EZWRBO','2026-09-01 08:36:16',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:16','2026-09-01 14:36:16','Owner 6a968e802d121','Owner 6a968e802d121'),(81,'Owner','6a968e80b36ee','32b84b55-a5e0-11f1-9275-e0d55e5927b4',158,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e80b36ee@test.com','+8801517767358','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$qq5aIATEAG08QwgE44CrnO0w8dBtcBqIngNYdzX..bKIUt6s0DLSS','2026-09-01 08:36:16',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:16','2026-09-01 14:36:16','Owner 6a968e80b36ee','Owner 6a968e80b36ee'),(82,'Owner','6a968e812f116','32fe0cf7-a5e0-11f1-9275-e0d55e5927b4',162,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e812f116@test.com','+8801517771936','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$9mM7EQmfjoQY8CBiVviG2.AKbG3XI84uG.//8gqoARo1InkTHamK6','2026-09-01 08:36:17',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17','Owner 6a968e812f116','Owner 6a968e812f116'),(83,'Owner','6a968e81891c9','3336932b-a5e0-11f1-9275-e0d55e5927b4',166,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e81891c9@test.com','+8801517775623','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$TWSiGPTXzV.34EAwYzCQrOVasB8OYvF9.VXNz8jGY59.xeEwwjvcG','2026-09-01 08:36:17',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17','Owner 6a968e81891c9','Owner 6a968e81891c9'),(84,'Owner','6a968e81e31b2','336ea559-a5e0-11f1-9275-e0d55e5927b4',169,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e81e31b2@test.com','+8801251777931','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$BGAhK55GAc0K3Vkh.li5neTiWfyg2zJgA4Dwc2tbTbByzO6vb5Wcq','2026-09-01 08:36:17',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17','Owner 6a968e81e31b2','Owner 6a968e81e31b2'),(85,'Owner','6a968e8260392','33b5e437-a5e0-11f1-9275-e0d55e5927b4',173,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e8260392@test.com','+8801517783951','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$1K30Q3I/p9zGgTdXPXU2we32K27RYWSMNNqfbW5.EeeHTt2RYECXm','2026-09-01 08:36:18',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:18','2026-09-01 14:36:18','Owner 6a968e8260392','Owner 6a968e8260392'),(86,'Owner','6a968e82a4f7a','33e056a1-a5e0-11f1-9275-e0d55e5927b4',175,NULL,1,NULL,NULL,NULL,NULL,'o6a968e82a4f7a@test.com','+8801517786766','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$NoK7Wy1OSIGhXeD2FR7vWObiB.EDUS/nJYIMogcVI9mFm.HvnAWay','2026-09-01 08:36:18',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:18','2026-09-01 14:36:18','Owner 6a968e82a4f7a','Owner 6a968e82a4f7a'),(87,'Owner','6a968e82ba489','33edbbb3-a5e0-11f1-9275-e0d55e5927b4',177,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e82ba489@test.com','+8801517787638','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$HAZipxWb6ApNyUufSyHVt.4/Bi9hcZ43GSJIPlApEsQciwsE5iaje','2026-09-01 08:36:18',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:18','2026-09-01 14:36:18','Owner 6a968e82ba489','Owner 6a968e82ba489'),(88,'Owner','6a968e83160b7','341f91e2-a5e0-11f1-9275-e0d55e5927b4',178,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e83160b7@test.com','+8801251779091','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$JNuGHFQOa/b7QKp8.jFT4.1K6QVWR0nUSepShVA2E8O8vpySqkvni','2026-09-01 08:36:19',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19','Owner 6a968e83160b7','Owner 6a968e83160b7'),(89,'Owner','6a968e832a5c3','342c8758-a5e0-11f1-9275-e0d55e5927b4',181,NULL,1,NULL,NULL,NULL,NULL,'o6a968e832a5c3@test.com','+8801517791742','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$5/tl/4M7WZCssJgQsmq01uJ/pmCeROvnIsngrjQ7caKNcMwe9Un7.','2026-09-01 08:36:19',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19','Owner 6a968e832a5c3','Owner 6a968e832a5c3'),(90,'Owner','6a968e8373a5a','345a2395-a5e0-11f1-9275-e0d55e5927b4',184,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e8373a5a@test.com','+8801517794745','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$vcdvg9u5uRfP2pcX4n/jfuTZ5AbhuOtRC9s9uVJg0VQL/Cdzw41CO','2026-09-01 08:36:19',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19','Owner 6a968e8373a5a','Owner 6a968e8373a5a'),(91,'Owner','6a968e83ac4e7','347d7fe2-a5e0-11f1-9275-e0d55e5927b4',185,NULL,1,NULL,NULL,NULL,NULL,'o6a968e83ac4e7@test.com','+8801517797065','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$pHVCv/I/LRnIDPLS5lEM3.ms1P.76ccofP7CkDgVMXOT.8EBaL0rW','2026-09-01 08:36:19',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19','Owner 6a968e83ac4e7','Owner 6a968e83ac4e7'),(92,'Owner','6a968e83f03e5','34a7ef66-a5e0-11f1-9275-e0d55e5927b4',187,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e83f03e5@test.com','+8801517799848','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$eHPcZUmtYUKZoOMtkAPkaOG1Pr38SnwQNtd/HUVE8NFLYaE4n2FCG','2026-09-01 08:36:19',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19','Owner 6a968e83f03e5','Owner 6a968e83f03e5'),(93,'Owner','6a968e84455a4','34d5c5b5-a5e0-11f1-9275-e0d55e5927b4',189,NULL,1,NULL,NULL,NULL,NULL,'o6a968e84455a4@test.com','+8801517802849','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$Ps7MDA76mQC.pPBdlQkzTuogSFbQq9LAdbiUWC6ud6yEyIBu5ZRPO','2026-09-01 08:36:20',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:20','2026-09-01 14:36:20','Owner 6a968e84455a4','Owner 6a968e84455a4'),(94,'Owner','6a968e8464dc7','34e9bb7b-a5e0-11f1-9275-e0d55e5927b4',191,NULL,1,NULL,NULL,NULL,NULL,'owner6a968e8464dc7@test.com','+8801517804138','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$Gdt2Zep.4.ChqZTXaF.8xORME8nxp6bEqQgLLq25QdjWjAFrNeHPi','2026-09-01 08:36:20',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:20','2026-09-01 14:36:20','Owner 6a968e8464dc7','Owner 6a968e8464dc7'),(95,'Owner','6a968e84c6505','35267c86-a5e0-11f1-9275-e0d55e5927b4',193,NULL,1,NULL,NULL,NULL,NULL,'o6a968e84c6505@test.com','+8801517808131','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$09vuIQmfQQTtyMSwIEDYGOff3W5BKWXE0qdudtVBqqDS3TSvQTRiG','2026-09-01 08:36:20',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:20','2026-09-01 14:36:20','Owner 6a968e84c6505','Owner 6a968e84c6505'),(96,'Owner','6a968e851bef7','355480b1-a5e0-11f1-9275-e0d55e5927b4',195,NULL,1,NULL,NULL,NULL,NULL,'o6a968e851bef7@test.com','+8801517811154','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$dUusCVQTeysGVZiBABQX.u7XyfN./zOZEnMANnRMpyfyOkip5rccq','2026-09-01 08:36:21',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:21','2026-09-01 14:36:21','Owner 6a968e851bef7','Owner 6a968e851bef7'),(97,'Owner','6a968e8567fc8','358401c5-a5e0-11f1-9275-e0d55e5927b4',198,NULL,1,NULL,NULL,NULL,NULL,'o6a968e8567fc8@test.com','+8801517814267','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$mxkpoOXO8ItjJsfhPaVxcexYe0P5K003FqcQl18mJP0mKwSRTBhq.','2026-09-01 08:36:21',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:21','2026-09-01 14:36:21','Owner 6a968e8567fc8','Owner 6a968e8567fc8'),(98,'Owner','6a968e85e5f50','35d2e79e-a5e0-11f1-9275-e0d55e5927b4',201,NULL,1,NULL,NULL,NULL,NULL,'o6a968e85e5f50@test.com','+8801517819427','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$wqvpgxUaWB.FD6g5DwBCiu8NGnT/wT2BnSb.rlH9Cx0GO9bjTi6BO','2026-09-01 08:36:21',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:21','2026-09-01 14:36:21','Owner 6a968e85e5f50','Owner 6a968e85e5f50'),(99,'Owner','6a968e8610c1a','35e64af3-a5e0-11f1-9275-e0d55e5927b4',202,NULL,1,NULL,NULL,NULL,NULL,'o6a968e8610c1a@test.com','+8801517820693','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$mk21En4gorBrZEsVcFOJ5.ukxFh/As017dLZvkDHT2gNNllF.4Iiy','2026-09-01 08:36:22',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:22','2026-09-01 14:36:22','Owner 6a968e8610c1a','Owner 6a968e8610c1a'),(100,'Owner','6a968e866130a','36186dd3-a5e0-11f1-9275-e0d55e5927b4',204,NULL,1,NULL,NULL,NULL,NULL,'o6a968e866130a@test.com','+8801517823992','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$K0.ShbhgYVED0PBZXRAvXuNrW90scobRVLUh3WBWxlUkyaVSe3ddi','2026-09-01 08:36:22',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:22','2026-09-01 14:36:22','Owner 6a968e866130a','Owner 6a968e866130a'),(101,'Owner','6a968e86bf895','3653854b-a5e0-11f1-9275-e0d55e5927b4',206,NULL,1,NULL,NULL,NULL,NULL,'o6a968e86bf895@test.com','+8801517827853','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$MjY6zMhhYgyFQEpb76yZkuTyol/E/lpjJoo3INsiy7SlhaZieErp2','2026-09-01 08:36:22',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:22','2026-09-01 14:36:22','Owner 6a968e86bf895','Owner 6a968e86bf895'),(102,'Owner','6a968e86dc6fc','36655e79-a5e0-11f1-9275-e0d55e5927b4',208,NULL,1,NULL,NULL,NULL,NULL,'o6a968e86dc6fc@test.com','+8801517829037','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$jUGYyMWDxmlFl.3/x.gYMuSa3U/AZQq0Y1//WeSFuFJuoNrtW.bNG','2026-09-01 08:36:22',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:22','2026-09-01 14:36:22','Owner 6a968e86dc6fc','Owner 6a968e86dc6fc'),(103,'Owner','6a968e8742b8a','369e0958-a5e0-11f1-9275-e0d55e5927b4',209,NULL,1,NULL,NULL,NULL,NULL,'o6a968e8742b8a@test.com','+8801517832741','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$RDIPX2XLx37XGEXr0fwU6.Eosp1oCph2MlKmTr20SM90J2fEpAmUS','2026-09-01 08:36:23',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:23','2026-09-01 14:36:23','Owner 6a968e8742b8a','Owner 6a968e8742b8a'),(104,'Owner','6a968e87aa31f','36deb933-a5e0-11f1-9275-e0d55e5927b4',212,NULL,1,NULL,NULL,NULL,NULL,'o6a968e87aa31f@test.com','+8801517836982','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$McQ9tred3l.Ecojc38pi8O7nxZpb0wXGKxIQ3W7Mg3CY3l47Vynwe','2026-09-01 08:36:23',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:23','2026-09-01 14:36:23','Owner 6a968e87aa31f','Owner 6a968e87aa31f'),(105,'Owner','6a968e88121b7','3718121e-a5e0-11f1-9275-e0d55e5927b4',215,NULL,1,NULL,NULL,NULL,NULL,'o6a968e88121b7@test.com','+8801251784075','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$/QLFEmmdQ11omafbRLg08eA3YSAaDgSjau9oZzGveQZ5fkpeNBQmy','2026-09-01 08:36:24',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:24','2026-09-01 14:36:24','Owner 6a968e88121b7','Owner 6a968e88121b7'),(106,'Owner','6a968e88894a3','3762ee34-a5e0-11f1-9275-e0d55e5927b4',217,NULL,1,NULL,NULL,NULL,NULL,'o6a968e88894a3@test.com','+8801517845635','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$iUzwi4t7On.bFwT2kb9GW.bS7maBWwJR3z0A0j39.g.WrF7ylyT1y','2026-09-01 08:36:24',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:24','2026-09-01 14:36:24','Owner 6a968e88894a3','Owner 6a968e88894a3'),(107,'Owner','6a968e891ec24','37b89026-a5e0-11f1-9275-e0d55e5927b4',219,NULL,1,NULL,NULL,NULL,NULL,'o6a968e891ec24@test.com','+8801517851267','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$fr4iAWfgarP3ggr9pdf9rOdrR65AjLtBXiAXfTKJZRo5/XdmLmjcu','2026-09-01 08:36:25',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:25','2026-09-01 14:36:25','Owner 6a968e891ec24','Owner 6a968e891ec24'),(108,'View','6a968e89922da','38009e1a-a5e0-11f1-9275-e0d55e5927b4',220,NULL,9,NULL,NULL,NULL,NULL,'v6a968e89922dd@test.com','+8801517855988','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$oR0KX.XRoYZbRub4iZibqu2KpgEPuFNAwP72r31VHS6CpdwUN0lbi','2026-09-01 08:36:25',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:25','2026-09-01 14:36:25','View 6a968e89922da','View 6a968e89922da'),(109,'Owner','6a968e8a2134a','38538619-a5e0-11f1-9275-e0d55e5927b4',221,NULL,1,NULL,NULL,NULL,NULL,'o6a968e8a2134a@test.com','+8801517861367','en',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$ODDqFxhwATlIbxR7/sng1.ZPCJCJJJQkd1X0R07duKL26Hu0sNq7a','2026-09-01 08:36:26',NULL,NULL,NULL,NULL,0,0,NULL,0,NULL,NULL,NULL,NULL,'active',NULL,'2026-09-01 14:36:26','2026-09-01 14:36:26','Owner 6a968e8a2134a','Owner 6a968e8a2134a');
/*!40000 ALTER TABLE `institute_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institutes`
--

DROP TABLE IF EXISTS `institutes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institutes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(10) DEFAULT NULL,
  `is_test` tinyint(1) DEFAULT 0,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `name` varchar(150) NOT NULL,
  `founded_year` year(4) DEFAULT NULL,
  `industry` varchar(60) DEFAULT 'education',
  `sub_industry` varchar(60) DEFAULT NULL,
  `short_name` varchar(60) DEFAULT NULL,
  `slug` varchar(80) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `cover_photo` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `country` varchar(80) NOT NULL DEFAULT 'Bangladesh',
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `division` varchar(80) DEFAULT NULL,
  `district` varchar(80) DEFAULT NULL,
  `upazila` varchar(80) DEFAULT NULL,
  `admin_level_1_id` bigint(20) unsigned DEFAULT NULL,
  `admin_level_2_id` bigint(20) unsigned DEFAULT NULL,
  `admin_level_3_id` bigint(20) unsigned DEFAULT NULL,
  `institute_code` varchar(20) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `website` varchar(150) DEFAULT NULL,
  `facebook` varchar(150) DEFAULT NULL,
  `youtube` varchar(150) DEFAULT NULL,
  `google_map_url` varchar(255) DEFAULT NULL,
  `license_number` varchar(80) DEFAULT NULL,
  `trade_license` varchar(255) DEFAULT NULL,
  `registration_number` varchar(80) DEFAULT NULL,
  `e_tin` varchar(40) DEFAULT NULL,
  `package_id` bigint(20) unsigned DEFAULT NULL,
  `subscription_expiry` date DEFAULT NULL,
  `status` enum('pending','active','suspended','expired','cancelled') NOT NULL DEFAULT 'pending',
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `onboarded_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deletion_requested_at` timestamp NULL DEFAULT NULL,
  `deletion_requested_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_institutes_slug` (`slug`),
  UNIQUE KEY `uq_institutes_uuid` (`uuid`),
  UNIQUE KEY `uq_institutes_institute_code` (`institute_code`),
  UNIQUE KEY `institutes_uid_unique` (`uid`),
  KEY `idx_institutes_status` (`status`),
  KEY `idx_institutes_package` (`package_id`),
  CONSTRAINT `fk_institutes_package` FOREIGN KEY (`package_id`) REFERENCES `subscription_packages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=224 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institutes`
--

LOCK TABLES `institutes` WRITE;
/*!40000 ALTER TABLE `institutes` DISABLE KEYS */;
INSERT INTO `institutes` VALUES (57,'0O71X82451',0,'8c28bf87-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bbbbef93',NULL,'education','school',NULL,'test-6a968bbbbef95',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:27','2026-09-01 14:24:27',NULL,NULL),(58,'WM87AL0093',0,'8c5f213d-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bbc1ee71',NULL,'education','college',NULL,'test-6a968bbc1ee74',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:28','2026-09-01 14:24:28',NULL,NULL),(59,'W5SB788185',0,'8c7534af-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bbc446c9',NULL,'education','university',NULL,'p3-6a968bbc446ca',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:28','2026-09-01 14:24:28',NULL,NULL),(60,'QSO5AE7780',0,'8cb8ec1f-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bbcb120d',NULL,'education','university',NULL,'test-6a968bbcb120f',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:28','2026-09-01 14:24:28',NULL,NULL),(61,'NWG3Y69210',0,'8ce97ad0-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bbd094b8',NULL,'education','school',NULL,'p3-6a968bbd094ba',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29',NULL,NULL),(62,'QDA9FR6267',0,'8cf9b222-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bbd242de',NULL,'education','computer_it_training_institute',NULL,'test-6a968bbd242e0',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29',NULL,NULL),(63,'ZUDBVU8674',0,'8d0796e3-a5de-11f1-9275-e0d55e5927b4','P4 6a968bbd3b63f',NULL,'education','school',NULL,'p4-6a968bbd3b641',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29',NULL,NULL),(64,'RV8N8C7365',0,'8d2110eb-a5de-11f1-9275-e0d55e5927b4','P5 6a968bbd644a1',NULL,'education','school',NULL,'p5-6a968bbd644a3',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29',NULL,NULL),(65,'GVPRRW6933',0,'8d238905-a5de-11f1-9275-e0d55e5927b4','P5 6a968bbd69255',NULL,'education','school',NULL,'p5-6a968bbd69257',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29',NULL,NULL),(66,'J8488P4185',0,'8d294e04-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bbd7281e',NULL,'education','computer_it_training_institute',NULL,'p3-6a968bbd72820',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29',NULL,NULL),(67,'BEHJSW9738',0,'8d2e307c-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bbd79c4d',NULL,'education','martial_arts',NULL,'test-6a968bbd79c4f',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29',NULL,NULL),(68,'9D0XAZ3919',0,'8d41271d-a5de-11f1-9275-e0d55e5927b4','P4 6a968bbd9809b',NULL,'education','university',NULL,'p4-6a968bbd9809c',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29',NULL,NULL),(69,'8NXCKH6587',0,'8d551ba1-a5de-11f1-9275-e0d55e5927b4','P5 6a968bbdb846c',NULL,'education','university',NULL,'p5-6a968bbdb846d',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29',NULL,NULL),(70,'Q54V3K9446',0,'8d58f271-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bbdbe944',NULL,'education','school',NULL,'p3-6a968bbdbe946',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29',NULL,NULL),(71,'089W493674',0,'8d5eed62-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bbdc8704',NULL,'education','dance_academy',NULL,'test-6a968bbdc8706',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:29','2026-09-01 14:24:29',NULL,NULL),(72,'K74M8K3592',0,'8d825cd5-a5de-11f1-9275-e0d55e5927b4','P4 6a968bbe09a46',NULL,'education','computer_it_training_institute',NULL,'p4-6a968bbe09a48',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30',NULL,NULL),(73,'APM8VW9428',0,'8d8bc8c8-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bbe1bbd6',NULL,'education','music_academy',NULL,'test-6a968bbe1bbd8',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30',NULL,NULL),(74,'465YBD5189',0,'8d9097d1-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bbe23ec9',NULL,'education','school',NULL,'p3-6a968bbe23ecb',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30',NULL,NULL),(75,'J3GKXN1046',0,'8da585bd-a5de-11f1-9275-e0d55e5927b4','P5 6a968bbe44d30',NULL,'education','school',NULL,'p5-6a968bbe44d32',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30',NULL,NULL),(76,'PNC6O75865',0,'8db3e1c4-a5de-11f1-9275-e0d55e5927b4','P4 6a968bbe5b8c7',NULL,'education','school',NULL,'p4-6a968bbe5b8c9',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30',NULL,NULL),(77,'5QOCGZ4325',0,'8dc14167-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bbe7175b',NULL,'education','sports_academy',NULL,'test-6a968bbe7175d',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30',NULL,NULL),(78,'VY9GH14241',0,'8dcb9d0d-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bbe81fa9',NULL,'education','school',NULL,'p3-6a968bbe81fab',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30',NULL,NULL),(79,'ITLNF74655',0,'8de8cf8b-a5de-11f1-9275-e0d55e5927b4','P4 6a968bbeaf3f3',NULL,'education','school',NULL,'p4-6a968bbeaf3f5',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30',NULL,NULL),(80,'TXRT8I3825',0,'8df54d88-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bbec4530',NULL,'education','language_academy',NULL,'test-6a968bbec4532',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30',NULL,NULL),(81,'0SRLIL4099',0,'8dff1070-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bbed4a42',NULL,'education','school',NULL,'p3-6a968bbed4a44',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30',NULL,NULL),(82,'5RF21H7816',0,'8e016b31-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bbed8269',NULL,'education','school',NULL,'p3-6a968bbed826b',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30',NULL,NULL),(83,'MYNNZ92620',0,'8e0d4164-a5de-11f1-9275-e0d55e5927b4','P5 6a968bbeeaa0a',NULL,'education','school',NULL,'p5-6a968bbeeaa0c',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:30','2026-09-01 14:24:30',NULL,NULL),(84,'2GTI6E6463',0,'8e1f2023-a5de-11f1-9275-e0d55e5927b4','P4 6a968bbf10d63',NULL,'education','school',NULL,'p4-6a968bbf10d65',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:31','2026-09-01 14:24:31',NULL,NULL),(85,'NFRNGU8838',0,'8e269b30-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bbf1f116',NULL,'education','coaching_centre',NULL,'test-6a968bbf1f119',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:31','2026-09-01 14:24:31',NULL,NULL),(86,'4KM9LJ5489',0,'8e3a8772-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bbf3f3f5',NULL,'education','school',NULL,'p3-6a968bbf3f3f7',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:31','2026-09-01 14:24:31',NULL,NULL),(87,'1FYSWG6762',0,'8e3ce81f-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bbf43196',NULL,'education','school',NULL,'p3-6a968bbf43198',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:31','2026-09-01 14:24:31',NULL,NULL),(88,'G3EE3V9082',0,'8e5a5e46-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bbf71394',NULL,'education','school',NULL,'test-6a968bbf71396',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:31','2026-09-01 14:24:31',NULL,NULL),(89,'AUHVTT8829',0,'8e73aa95-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bbf9a362',NULL,'education','school',NULL,'p3-6a968bbf9a364',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:31','2026-09-01 14:24:31',NULL,NULL),(90,'G8L9B77682',0,'8e907e7d-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bbfc7701',NULL,'education','school',NULL,'test-6a968bbfc7703',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:31','2026-09-01 14:24:31',NULL,NULL),(91,'D5FAGM4721',0,'8ebb0ca0-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bc010a13',NULL,'education','school',NULL,'p3-6a968bc010a15',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:32','2026-09-01 14:24:32',NULL,NULL),(92,'81NH375315',0,'8ec3431c-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc024bf8',NULL,'education','school',NULL,'test-6a968bc024bfa',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:32','2026-09-01 14:24:32',NULL,NULL),(93,'CQ6P764199',0,'8ef5f24a-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bc076582',NULL,'education','school',NULL,'p3-6a968bc076584',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:32','2026-09-01 14:24:32',NULL,NULL),(94,'G4ELM22475',0,'8ef83354-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc07b3c5',NULL,'education','university',NULL,'test-6a968bc07b3c6',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:32','2026-09-01 14:24:32',NULL,NULL),(95,'8C1VXY1947',0,'8f302e97-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bc0d401e',NULL,'education','university',NULL,'p3-6a968bc0d4020',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:32','2026-09-01 14:24:32',NULL,NULL),(96,'BM8YCL9662',0,'8f3a1f0f-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc0e2573',NULL,'education','school',NULL,'test-6a968bc0e2575',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:32','2026-09-01 14:24:32',NULL,NULL),(97,'JJXYFC3960',0,'8f675eef-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc13585a',NULL,'education','school',NULL,'p4-6a968bc13585c',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:33','2026-09-01 14:24:33',NULL,NULL),(98,'DAS41U1336',0,'8f6838a4-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bc1365a0',NULL,'education','school',NULL,'p3-6a968bc1365a1',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:33','2026-09-01 14:24:33',NULL,NULL),(99,'GVR70K6032',0,'8f71a64d-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc1489fa',NULL,'education','university',NULL,'test-6a968bc1489fc',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:33','2026-09-01 14:24:33',NULL,NULL),(100,'AKOPMY4457',0,'8f9d4a7d-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bc18d5d5',NULL,'education','school',NULL,'p3-6a968bc18d5d7',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:33','2026-09-01 14:24:33',NULL,NULL),(101,'KORQP12248',0,'8f9e2aec-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc18ff13',NULL,'education','school',NULL,'test-6a968bc18ff15',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:33','2026-09-01 14:24:33',NULL,NULL),(102,'J9MNF33299',0,'8fa37c55-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc198d8c',NULL,'education','university',NULL,'p4-6a968bc198d8e',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:33','2026-09-01 14:24:33',NULL,NULL),(103,'4CHM4O7487',0,'8fd14513-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc1e1622',NULL,'education','school',NULL,'test-6a968bc1e1625',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:33','2026-09-01 14:24:33',NULL,NULL),(104,'IBK7XH4358',0,'8fd3b846-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc1e5b70',NULL,'education','school',NULL,'test-6a968bc1e5b72',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:33','2026-09-01 14:24:33',NULL,NULL),(105,'4ON8EF4693',0,'8fd86408-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bc1ed61d',NULL,'education','school',NULL,'p3-6a968bc1ed621',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:33','2026-09-01 14:24:33',NULL,NULL),(106,'CI0OSO3370',0,'8fe36314-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc208648',NULL,'education','computer_it_training_institute',NULL,'p4-6a968bc20864a',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:34','2026-09-01 14:24:34',NULL,NULL),(107,'QTLZJZ2868',0,'8fef0984-a5de-11f1-9275-e0d55e5927b4','Phase3 6a968bc21d706',NULL,'education','school',NULL,'p3-6a968bc21d708',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:34','2026-09-01 14:24:34',NULL,NULL),(108,'MIGOQC1850',0,'90078c8c-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc2423c0',NULL,'education','school',NULL,'test-6a968bc2423c2',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:34','2026-09-01 14:24:34',NULL,NULL),(109,'8OX34K1898',0,'90097a65-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc24854e',NULL,'education','school',NULL,'test-6a968bc248550',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:34','2026-09-01 14:24:34',NULL,NULL),(110,'HANK441956',0,'902176f7-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc26e4ca',NULL,'education','school',NULL,'p4-6a968bc26e4cc',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:34','2026-09-01 14:24:34',NULL,NULL),(111,'QMSLP12111',0,'903a5efa-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc295df2',NULL,'education','school',NULL,'test-6a968bc295df3',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:34','2026-09-01 14:24:34',NULL,NULL),(112,'D9KOSR8863',0,'903c9906-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc2999af',NULL,'education','school',NULL,'test-6a968bc2999b1',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:34','2026-09-01 14:24:34',NULL,NULL),(113,'F4PR0P3387',0,'90696e74-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc2e176c',NULL,'education','school',NULL,'test-6a968bc2e176e',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:34','2026-09-01 14:24:34',NULL,NULL),(114,'OLY65Y0553',0,'906fc8de-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc2eb917',NULL,'education','school',NULL,'p4-6a968bc2eb918',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:34','2026-09-01 14:24:34',NULL,NULL),(115,'LPA68D2036',0,'90a33d3a-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc34513e',NULL,'education','school',NULL,'test-6a968bc345140',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:35','2026-09-01 14:24:35',NULL,NULL),(116,'MAYDF35298',0,'90abc245-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc356b49',NULL,'education','school',NULL,'p4-6a968bc356b4b',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:35','2026-09-01 14:24:35',NULL,NULL),(117,'QTC40Y9320',0,'90d9bd57-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc3a07ce',NULL,'education','school',NULL,'test-6a968bc3a07d0',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:35','2026-09-01 14:24:35',NULL,NULL),(118,'AFATDC5882',0,'90e60bb3-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc3b4b6d',NULL,'education','school',NULL,'p4-6a968bc3b4b6f',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:35','2026-09-01 14:24:35',NULL,NULL),(119,'Q5D3W38985',0,'91172fe8-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc40ad1f',NULL,'education','school',NULL,'test-6a968bc40ad21',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:36','2026-09-01 14:24:36',NULL,NULL),(120,'4Y2QY95325',0,'911dc3e8-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc418adf',NULL,'education','school',NULL,'p4-6a968bc418ae0',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:36','2026-09-01 14:24:36',NULL,NULL),(121,'1B61X60877',0,'914d4ad5-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc463f04',NULL,'education','school',NULL,'test-6a968bc463f06',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:36','2026-09-01 14:24:36',NULL,NULL),(122,'RZ0ZJV4889',0,'91545558-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc470ae4',NULL,'education','university',NULL,'p4-6a968bc470ae5',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:36','2026-09-01 14:24:36',NULL,NULL),(123,'1G7QFJ8759',0,'9183bac1-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc4bbcce',NULL,'education','school',NULL,'test-6a968bc4bbccf',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:36','2026-09-01 14:24:36',NULL,NULL),(124,'99VXAM7887',0,'918e3411-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc4cdc11',NULL,'education','school',NULL,'p4-6a968bc4cdc13',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:36','2026-09-01 14:24:36',NULL,NULL),(125,'ACF8W98445',0,'919015cb-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc4d0fba',NULL,'education','school',NULL,'p4-6a968bc4d0fbc',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:36','2026-09-01 14:24:36',NULL,NULL),(126,'LHXH4L9861',0,'91b9b131-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc51c064',NULL,'education','school',NULL,'test-6a968bc51c066',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:37','2026-09-01 14:24:37',NULL,NULL),(127,'4ETFMR0269',0,'91ca763c-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc5373df',NULL,'education','school',NULL,'p4-6a968bc5373e1',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:37','2026-09-01 14:24:37',NULL,NULL),(128,'BEFQF08522',0,'91d015b3-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc542901',NULL,'education','school',NULL,'p4-6a968bc542903',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:37','2026-09-01 14:24:37',NULL,NULL),(129,'QIT7B22299',0,'91f92a77-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc583db7',NULL,'education','school',NULL,'test-6a968bc583dba',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:37','2026-09-01 14:24:37',NULL,NULL),(130,'FSOPQI1665',0,'921d6412-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc5b97a3',NULL,'education','school',NULL,'p4-6a968bc5b97a5',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:37','2026-09-01 14:24:37',NULL,NULL),(131,'6O6HKO9166',0,'924117f7-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc6003f4',NULL,'education','school',NULL,'test-6a968bc6003f6',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:38','2026-09-01 14:24:38',NULL,NULL),(132,'WEHOGI7996',0,'92b40e34-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc6b840a',NULL,'education','school',NULL,'p4-6a968bc6b840b',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:38','2026-09-01 14:24:38',NULL,NULL),(133,'JD32MF0264',0,'92b894ed-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc6bea28',NULL,'education','school',NULL,'test-6a968bc6bea2a',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:38','2026-09-01 14:24:38',NULL,NULL),(134,'DM968G6513',0,'9321148c-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc76defc',NULL,'education','school',NULL,'p4-6a968bc76defe',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:39','2026-09-01 14:24:39',NULL,NULL),(135,'W1U8T76414',0,'93600022-a5de-11f1-9275-e0d55e5927b4','Test Institute 6a968bc7ca791',NULL,'education','school',NULL,'test-6a968bc7ca793',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:39','2026-09-01 14:24:39',NULL,NULL),(136,'SDEBFH6021',0,'939eaaa4-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc848494',NULL,'education','school',NULL,'p4-6a968bc848495',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:40','2026-09-01 14:24:40',NULL,NULL),(137,'96889R4981',0,'93b789eb-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc870ef2',NULL,'education','school',NULL,'p4-6a968bc870ef4',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:40','2026-09-01 14:24:40',NULL,NULL),(138,'TD0AR83665',0,'93f94e86-a5de-11f1-9275-e0d55e5927b4','P4 6a968bc8dae51',NULL,'education','school',NULL,'p4-6a968bc8dae52',NULL,NULL,NULL,NULL,NULL,'India',26,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:24:40','2026-09-01 14:24:40',NULL,NULL),(142,'38Z0U57326',0,'2f9c0dd2-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e7b7b385',NULL,'education','school',NULL,'test-6a968e7b7b389',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:11','2026-09-01 14:36:11',NULL,NULL),(143,'X2MO4D7865',0,'30185b09-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e7c4ebb8',NULL,'education','college',NULL,'test-6a968e7c4ebba',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:12','2026-09-01 14:36:12',NULL,NULL),(144,'KBT6WY7255',0,'3051cecf-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e7ca9243',NULL,'education','university',NULL,'test-6a968e7ca9245',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:12','2026-09-01 14:36:12',NULL,NULL),(145,'O8YYRE9617',0,'30a15d30-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e7d32a04',NULL,'education','computer_it_training_institute',NULL,'test-6a968e7d32a06',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:13','2026-09-01 14:36:13',NULL,NULL),(146,'RQMX7M5329',0,'30ed2e48-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e7daf146',NULL,'education','martial_arts',NULL,'test-6a968e7daf148',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:13','2026-09-01 14:36:13',NULL,NULL),(147,'5XK9EN6786',0,'311cf88d-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e7e034c9',NULL,'education','university',NULL,'p3-6a968e7e034cb',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:14','2026-09-01 14:36:14',NULL,NULL),(148,'MQXMOV5150',0,'313e83bc-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e7e37df5',NULL,'education','dance_academy',NULL,'test-6a968e7e37df7',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:14','2026-09-01 14:36:14',NULL,NULL),(149,'IFV3OA2565',0,'318e14f5-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e7ebb9e7',NULL,'education','music_academy',NULL,'test-6a968e7ebb9ea',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:14','2026-09-01 14:36:14',NULL,NULL),(150,'OEZCBN9041',0,'3190931d-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e7ebe682',NULL,'education','school',NULL,'p3-6a968e7ebe685',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:14','2026-09-01 14:36:14',NULL,NULL),(151,'9M772W5798',0,'31e46303-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e7f4d9f1',NULL,'education','computer_it_training_institute',NULL,'p3-6a968e7f4d9f2',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:15','2026-09-01 14:36:15',NULL,NULL),(152,'Y6AIYL1824',0,'31e55ff4-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e7f50873',NULL,'education','sports_academy',NULL,'test-6a968e7f50875',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:15','2026-09-01 14:36:15',NULL,NULL),(153,'PRTEZ84824',0,'321c6873-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e7faa406',NULL,'education','language_academy',NULL,'test-6a968e7faa408',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:15','2026-09-01 14:36:15',NULL,NULL),(154,'FL6VOK8674',0,'321e8691-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e7faf99c',NULL,'education','school',NULL,'p3-6a968e7faf99e',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:15','2026-09-01 14:36:15',NULL,NULL),(155,'G9WTL74832',0,'325b6aa5-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e80196a7',NULL,'education','coaching_centre',NULL,'test-6a968e80196a9',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:16','2026-09-01 14:36:16',NULL,NULL),(156,'WB3MHU7388',0,'3262e8ab-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e802723d',NULL,'education','school',NULL,'p3-6a968e802723f',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:16','2026-09-01 14:36:16',NULL,NULL),(157,'33TM6Y3440',0,'3299c5a1-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e807ff13',NULL,'education','school',NULL,'test-6a968e807ff15',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:16','2026-09-01 14:36:16',NULL,NULL),(158,'8H5LNS7516',0,'32b6dea4-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e80ad0cf',NULL,'education','school',NULL,'p3-6a968e80ad0d1',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:16','2026-09-01 14:36:16',NULL,NULL),(159,'5DHZUX4657',0,'32d85c7c-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e80e4f5c',NULL,'education','school',NULL,'p4-6a968e80e4f5e',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:16','2026-09-01 14:36:16',NULL,NULL),(160,'5666TI2010',0,'32db09e0-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e80e8d68',NULL,'education','school',NULL,'test-6a968e80e8d6a',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:16','2026-09-01 14:36:16',NULL,NULL),(161,'SRWQMR9396',0,'32fa2e6b-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e81228ff',NULL,'education','school',NULL,'p3-6a968e8122900',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17',NULL,NULL),(162,'E5K2C96057',0,'32fcd98e-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e812a852',NULL,'education','school',NULL,'p3-6a968e812a854',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17',NULL,NULL),(163,'4TMRVN2395',0,'3309d36f-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e813f4ad',NULL,'education','university',NULL,'p4-6a968e813f4b1',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17',NULL,NULL),(164,'TWDNZP2263',0,'330e50d8-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e8145c7e',NULL,'education','school',NULL,'test-6a968e8145c80',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17',NULL,NULL),(165,'PTHHTP5766',0,'333197a6-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e817ef1f',NULL,'education','school',NULL,'p3-6a968e817ef21',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17',NULL,NULL),(166,'GQ4VA21425',0,'3333f623-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e818318a',NULL,'education','school',NULL,'p3-6a968e818318c',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17',NULL,NULL),(167,'I1ULFL9988',0,'333d6097-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e81920dc',NULL,'education','computer_it_training_institute',NULL,'p4-6a968e81920de',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17',NULL,NULL),(168,'JEHVPG7721',0,'333ee829-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e8194ac9',NULL,'education','university',NULL,'test-6a968e8194aca',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17',NULL,NULL),(169,'OH3GAT4313',0,'336ac4fb-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e81daffe',NULL,'education','school',NULL,'p3-6a968e81db000',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17',NULL,NULL),(170,'CVP6G91735',0,'3370f315-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e81e4739',NULL,'education','school',NULL,'p4-6a968e81e473b',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17',NULL,NULL),(171,'T2EALH7240',0,'3377077e-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e81ee015',NULL,'education','school',NULL,'test-6a968e81ee016',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:17','2026-09-01 14:36:17',NULL,NULL),(172,'8EV6DZ5372',0,'33a32669-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e823b7b1',NULL,'education','school',NULL,'p4-6a968e823b7b3',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:18','2026-09-01 14:36:18',NULL,NULL),(173,'RYZZ8H9820',0,'33b1f7c8-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e8255002',NULL,'education','school',NULL,'p3-6a968e8255004',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:18','2026-09-01 14:36:18',NULL,NULL),(174,'E868EJ7141',0,'33bbf3aa-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e826786b',NULL,'education','university',NULL,'test-6a968e826786d',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:18','2026-09-01 14:36:18',NULL,NULL),(175,'34FIYQ6339',0,'33ddd7b3-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e829ec69',NULL,'education','school',NULL,'p4-6a968e829ec6a',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:18','2026-09-01 14:36:18',NULL,NULL),(176,'YJ3O9Z9828',0,'33ea4e05-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e82b22f1',NULL,'education','school',NULL,'test-6a968e82b22f2',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:18','2026-09-01 14:36:18',NULL,NULL),(177,'GPM58Z3122',0,'33ec9e56-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e82b5b0e',NULL,'education','school',NULL,'p3-6a968e82b5b10',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:18','2026-09-01 14:36:18',NULL,NULL),(178,'65YZCJ2568',0,'341e7b08-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e830e82a',NULL,'education','university',NULL,'p3-6a968e830e82c',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19',NULL,NULL),(179,'JOLBRN3058',0,'341eaeae-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e830f557',NULL,'education','school',NULL,'test-6a968e830f559',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19',NULL,NULL),(180,'2BBD9G3937',0,'34222782-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e8316d44',NULL,'education','school',NULL,'test-6a968e8316d46',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19',NULL,NULL),(181,'NV33NV4450',0,'34298374-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e8322686',NULL,'education','school',NULL,'p4-6a968e8322688',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19',NULL,NULL),(182,'IDUGK18277',0,'3455f0b6-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e836a1ab',NULL,'education','school',NULL,'test-6a968e836a1ad',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19',NULL,NULL),(183,'II6E880966',0,'34581228-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e836e9b5',NULL,'education','school',NULL,'test-6a968e836e9b7',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19',NULL,NULL),(184,'RPSL415977',0,'3458eed1-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e836e866',NULL,'education','school',NULL,'p3-6a968e836e868',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19',NULL,NULL),(185,'88WCOF7339',0,'347b0a2c-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e83a4f68',NULL,'education','university',NULL,'p4-6a968e83a4f6a',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19',NULL,NULL),(186,'98ZO5O1826',0,'34a60b25-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e83e873e',NULL,'education','school',NULL,'test-6a968e83e8740',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19',NULL,NULL),(187,'X7VT164480',0,'34a6beb8-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e83ec5e4',NULL,'education','school',NULL,'p3-6a968e83ec5e6',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19',NULL,NULL),(188,'5A8ID26110',0,'34a85ac8-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e83eed7d',NULL,'education','school',NULL,'test-6a968e83eed7e',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:19','2026-09-01 14:36:19',NULL,NULL),(189,'Y82QTY5210',0,'34d2b2ef-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e8434fdf',NULL,'education','computer_it_training_institute',NULL,'p4-6a968e8434fe1',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:20','2026-09-01 14:36:20',NULL,NULL),(190,'QP2N510448',0,'34e18b5d-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e84545ca',NULL,'education','school',NULL,'test-6a968e84545cc',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:20','2026-09-01 14:36:20',NULL,NULL),(191,'8XW7AQ0069',0,'34e85e49-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e8460b63',NULL,'education','school',NULL,'p3-6a968e8460b64',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:20','2026-09-01 14:36:20',NULL,NULL),(192,'SU5NTL4013',0,'34ffd5e2-a5e0-11f1-9275-e0d55e5927b4','Phase3 6a968e8484b39',NULL,'education','school',NULL,'p3-6a968e8484b3a',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:20','2026-09-01 14:36:20',NULL,NULL),(193,'IF1BLJ8822',0,'3524f267-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e84c05f2',NULL,'education','school',NULL,'p4-6a968e84c05f4',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:20','2026-09-01 14:36:20',NULL,NULL),(194,'20NDGJ7049',0,'3525051c-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e84bf7ce',NULL,'education','school',NULL,'test-6a968e84bf7d0',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:20','2026-09-01 14:36:20',NULL,NULL),(195,'1VIHDS4474',0,'35507946-a5e0-11f1-9275-e0d55e5927b4','P5 6a968e850ec44',NULL,'education','school',NULL,'p5-6a968e850ec46',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:21','2026-09-01 14:36:21',NULL,NULL),(196,'VT43YD8874',0,'3553695a-a5e0-11f1-9275-e0d55e5927b4','P5 6a968e85172d1',NULL,'education','school',NULL,'p5-6a968e85172d3',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:21','2026-09-01 14:36:21',NULL,NULL),(197,'94L5L58662',0,'356c4da9-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e853fd0a',NULL,'education','school',NULL,'test-6a968e853fd0c',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:21','2026-09-01 14:36:21',NULL,NULL),(198,'42RO3R6690',0,'3581ecb0-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e8561e77',NULL,'education','school',NULL,'p4-6a968e8561e79',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:21','2026-09-01 14:36:21',NULL,NULL),(199,'3FRGSS9816',0,'3595295d-a5e0-11f1-9275-e0d55e5927b4','P5 6a968e858047a',NULL,'education','university',NULL,'p5-6a968e858047b',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:21','2026-09-01 14:36:21',NULL,NULL),(200,'96SCH39764',0,'35aedc63-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e85aa8cd',NULL,'education','school',NULL,'test-6a968e85aa8cf',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:21','2026-09-01 14:36:21',NULL,NULL),(201,'THBFBE9546',0,'35cf09ed-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e85da9fe',NULL,'education','school',NULL,'p4-6a968e85daa00',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:21','2026-09-01 14:36:21',NULL,NULL),(202,'N4GBQJ0414',0,'35e4b475-a5e0-11f1-9275-e0d55e5927b4','P5 6a968e8608693',NULL,'education','school',NULL,'p5-6a968e8608695',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:22','2026-09-01 14:36:22',NULL,NULL),(203,'2BEFMO7676',0,'35ea152e-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e8613a27',NULL,'education','school',NULL,'test-6a968e8613a28',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:22','2026-09-01 14:36:22',NULL,NULL),(204,'A72D6M3625',0,'3613f4db-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e86563fc',NULL,'education','school',NULL,'p4-6a968e86563fe',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:22','2026-09-01 14:36:22',NULL,NULL),(205,'A0WX8H3242',0,'3628caaf-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e8676416',NULL,'education','school',NULL,'test-6a968e8676418',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:22','2026-09-01 14:36:22',NULL,NULL),(206,'QP8WY25895',0,'364f33ca-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e86b4f3d',NULL,'education','school',NULL,'p4-6a968e86b4f3f',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:22','2026-09-01 14:36:22',NULL,NULL),(207,'ZCDZ2F2320',0,'3663c4fb-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e86d6818',NULL,'education','school',NULL,'test-6a968e86d681a',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:22','2026-09-01 14:36:22',NULL,NULL),(208,'U1ZUK02284',0,'3663ca9b-a5e0-11f1-9275-e0d55e5927b4','P5 6a968e86d4e8b',NULL,'education','school',NULL,'p5-6a968e86d4e8d',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:22','2026-09-01 14:36:22',NULL,NULL),(209,'SL3QWY2864',0,'3695e1e5-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e872e6fa',NULL,'education','university',NULL,'p4-6a968e872e6fc',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:23','2026-09-01 14:36:23',NULL,NULL),(210,'Z09PUT4110',0,'36b9a5d8-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e876b30a',NULL,'education','school',NULL,'test-6a968e876b30c',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:23','2026-09-01 14:36:23',NULL,NULL),(211,'DD3AGB8641',0,'36d71c0e-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e879c3a4',NULL,'education','school',NULL,'p4-6a968e879c3a6',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:23','2026-09-01 14:36:23',NULL,NULL),(212,'RR2ACH8252',0,'36d9765f-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e879fdb8',NULL,'education','school',NULL,'p4-6a968e879fdba',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:23','2026-09-01 14:36:23',NULL,NULL),(213,'083RK20869',0,'36f8115a-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e87d10aa',NULL,'education','school',NULL,'test-6a968e87d10ab',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:23','2026-09-01 14:36:23',NULL,NULL),(214,'L55HV36432',0,'3713efa8-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e8806caa',NULL,'education','school',NULL,'p4-6a968e8806cab',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:24','2026-09-01 14:36:24',NULL,NULL),(215,'2OG1C18539',0,'37160681-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e880d22c',NULL,'education','school',NULL,'p4-6a968e880d22e',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:24','2026-09-01 14:36:24',NULL,NULL),(216,'TFSG293623',0,'372d3931-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e8830e77',NULL,'education','school',NULL,'test-6a968e8830e79',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:24','2026-09-01 14:36:24',NULL,NULL),(217,'123Q4Z3662',0,'3759cc05-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e8877989',NULL,'education','school',NULL,'p4-6a968e887798a',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:24','2026-09-01 14:36:24',NULL,NULL),(218,'Z6K9K65317',0,'379be7c2-a5e0-11f1-9275-e0d55e5927b4','Test Institute 6a968e88e26d2',NULL,'education','school',NULL,'test-6a968e88e26d4',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:24','2026-09-01 14:36:24',NULL,NULL),(219,'L1Z7F83617',0,'37b0fb85-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e8910c75',NULL,'education','school',NULL,'p4-6a968e8910c77',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:25','2026-09-01 14:36:25',NULL,NULL),(220,'J5SM616677',0,'37fc6868-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e8986cf6',NULL,'education','school',NULL,'p4-6a968e8986cf8',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:25','2026-09-01 14:36:25',NULL,NULL),(221,'0RDXXX1613',0,'384fa3ab-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e8a15e47',NULL,'education','school',NULL,'p4-6a968e8a15e4a',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:26','2026-09-01 14:36:26',NULL,NULL),(222,'QGDMUW7212',0,'386935aa-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e8a4296f',NULL,'education','school',NULL,'p4-6a968e8a42971',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:26','2026-09-01 14:36:26',NULL,NULL),(223,'WL95NO6769',0,'38a8cf0f-a5e0-11f1-9275-e0d55e5927b4','P4 6a968e8aa841e',NULL,'education','school',NULL,'p4-6a968e8aa8420',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,'pending',0,NULL,NULL,NULL,'2026-09-01 14:36:26','2026-09-01 14:36:26',NULL,NULL);
/*!40000 ALTER TABLE `institutes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institution_user`
--

DROP TABLE IF EXISTS `institution_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institution_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `is_test` tinyint(1) DEFAULT 0,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `user_id` bigint(20) unsigned NOT NULL,
  `institution_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` varchar(40) DEFAULT NULL,
  `designation` varchar(80) DEFAULT NULL,
  `department` varchar(80) DEFAULT NULL,
  `qualification` varchar(150) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `father_name` varchar(120) DEFAULT NULL,
  `mother_name` varchar(120) DEFAULT NULL,
  `religion` varchar(30) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `nid_photo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `legacy_institute_user_id` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `institution_user_user_id_institution_id_unique` (`user_id`,`institution_id`),
  UNIQUE KEY `institution_user_uuid_unique` (`uuid`),
  UNIQUE KEY `institution_user_legacy_institute_user_id_unique` (`legacy_institute_user_id`),
  KEY `institution_user_institution_id_index` (`institution_id`),
  KEY `institution_user_role_id_index` (`role_id`),
  KEY `institution_user_branch_id_index` (`branch_id`),
  KEY `institution_user_status_index` (`status`),
  CONSTRAINT `fk_institution_user_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_institution_user_institute` FOREIGN KEY (`institution_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_institution_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_institution_user_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institution_user`
--

LOCK TABLES `institution_user` WRITE;
/*!40000 ALTER TABLE `institution_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `institution_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_adjustment_items`
--

DROP TABLE IF EXISTS `inventory_adjustment_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_adjustment_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `adjustment_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `system_qty` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `counted_qty` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `difference` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(19,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (`id`),
  KEY `inventory_adjustment_items_institute_id_foreign` (`institute_id`),
  KEY `inventory_adjustment_items_branch_id_foreign` (`branch_id`),
  KEY `inventory_adjustment_items_item_id_foreign` (`item_id`),
  KEY `inventory_adjustment_items_batch_id_foreign` (`batch_id`),
  KEY `idx_inventory_adjustment_items_adjustment` (`adjustment_id`),
  CONSTRAINT `inventory_adjustment_items_adjustment_id_foreign` FOREIGN KEY (`adjustment_id`) REFERENCES `inventory_adjustments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_adjustment_items_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_adjustment_items_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_adjustment_items_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_adjustment_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_adjustment_items`
--

LOCK TABLES `inventory_adjustment_items` WRITE;
/*!40000 ALTER TABLE `inventory_adjustment_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_adjustment_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_adjustments`
--

DROP TABLE IF EXISTS `inventory_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_adjustments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `warehouse_id` bigint(20) unsigned NOT NULL,
  `adjustment_no` varchar(30) NOT NULL,
  `adjustment_type` varchar(20) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_adjustments_no` (`institute_id`,`adjustment_no`),
  KEY `inventory_adjustments_branch_id_foreign` (`branch_id`),
  KEY `inventory_adjustments_warehouse_id_foreign` (`warehouse_id`),
  KEY `inventory_adjustments_journal_id_foreign` (`journal_id`),
  CONSTRAINT `inventory_adjustments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_adjustments_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_adjustments_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_adjustments_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `inventory_warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_adjustments`
--

LOCK TABLES `inventory_adjustments` WRITE;
/*!40000 ALTER TABLE `inventory_adjustments` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_adjustments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_batches`
--

DROP TABLE IF EXISTS `inventory_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `warehouse_id` bigint(20) unsigned NOT NULL,
  `batch_number` varchar(80) NOT NULL,
  `manufacture_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_batches_no` (`institute_id`,`item_id`,`warehouse_id`,`batch_number`),
  KEY `inventory_batches_branch_id_foreign` (`branch_id`),
  KEY `inventory_batches_item_id_foreign` (`item_id`),
  KEY `inventory_batches_warehouse_id_foreign` (`warehouse_id`),
  KEY `idx_inventory_batches_expiry` (`institute_id`,`expiry_date`),
  CONSTRAINT `inventory_batches_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_batches_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_batches_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_batches_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `inventory_warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_batches`
--

LOCK TABLES `inventory_batches` WRITE;
/*!40000 ALTER TABLE `inventory_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_categories`
--

DROP TABLE IF EXISTS `inventory_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `inventory_account_id` bigint(20) unsigned DEFAULT NULL,
  `cogs_account_id` bigint(20) unsigned DEFAULT NULL,
  `sales_account_id` bigint(20) unsigned DEFAULT NULL,
  `expense_account_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_categories_name` (`institute_id`,`branch_id`,`name`),
  KEY `inventory_categories_branch_id_foreign` (`branch_id`),
  KEY `inventory_categories_parent_id_foreign` (`parent_id`),
  KEY `inventory_categories_inventory_account_id_foreign` (`inventory_account_id`),
  KEY `inventory_categories_cogs_account_id_foreign` (`cogs_account_id`),
  KEY `inventory_categories_sales_account_id_foreign` (`sales_account_id`),
  KEY `inventory_categories_expense_account_id_foreign` (`expense_account_id`),
  KEY `idx_inventory_categories_parent` (`institute_id`,`parent_id`),
  CONSTRAINT `inventory_categories_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_categories_cogs_account_id_foreign` FOREIGN KEY (`cogs_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_categories_expense_account_id_foreign` FOREIGN KEY (`expense_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_categories_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_categories_inventory_account_id_foreign` FOREIGN KEY (`inventory_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `inventory_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_categories_sales_account_id_foreign` FOREIGN KEY (`sales_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_categories`
--

LOCK TABLES `inventory_categories` WRITE;
/*!40000 ALTER TABLE `inventory_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_count_items`
--

DROP TABLE IF EXISTS `inventory_count_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_count_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `count_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `system_qty` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `counted_qty` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `difference` decimal(19,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (`id`),
  KEY `inventory_count_items_institute_id_foreign` (`institute_id`),
  KEY `inventory_count_items_branch_id_foreign` (`branch_id`),
  KEY `inventory_count_items_item_id_foreign` (`item_id`),
  KEY `inventory_count_items_batch_id_foreign` (`batch_id`),
  KEY `idx_inventory_count_items_count` (`count_id`),
  CONSTRAINT `inventory_count_items_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_count_items_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_count_items_count_id_foreign` FOREIGN KEY (`count_id`) REFERENCES `inventory_counts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_count_items_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_count_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_count_items`
--

LOCK TABLES `inventory_count_items` WRITE;
/*!40000 ALTER TABLE `inventory_count_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_count_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_counts`
--

DROP TABLE IF EXISTS `inventory_counts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_counts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `warehouse_id` bigint(20) unsigned NOT NULL,
  `count_no` varchar(30) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `counted_at` date DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `counted_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_counts_no` (`institute_id`,`count_no`),
  KEY `inventory_counts_branch_id_foreign` (`branch_id`),
  KEY `inventory_counts_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `inventory_counts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_counts_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_counts_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `inventory_warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_counts`
--

LOCK TABLES `inventory_counts` WRITE;
/*!40000 ALTER TABLE `inventory_counts` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_counts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_items`
--

DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `item_type` varchar(30) NOT NULL DEFAULT 'stock_item',
  `sku` varchar(60) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `purchase_price` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `selling_price` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `reorder_level` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `min_stock` decimal(19,4) DEFAULT NULL,
  `max_stock` decimal(19,4) DEFAULT NULL,
  `tax_group_id` bigint(20) unsigned DEFAULT NULL,
  `inventory_account_id` bigint(20) unsigned DEFAULT NULL,
  `cogs_account_id` bigint(20) unsigned DEFAULT NULL,
  `sales_account_id` bigint(20) unsigned DEFAULT NULL,
  `expense_account_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_items_sku` (`institute_id`,`branch_id`,`sku`),
  UNIQUE KEY `uq_inventory_items_barcode` (`institute_id`,`barcode`),
  KEY `inventory_items_branch_id_foreign` (`branch_id`),
  KEY `inventory_items_tax_group_id_foreign` (`tax_group_id`),
  KEY `inventory_items_inventory_account_id_foreign` (`inventory_account_id`),
  KEY `inventory_items_cogs_account_id_foreign` (`cogs_account_id`),
  KEY `inventory_items_sales_account_id_foreign` (`sales_account_id`),
  KEY `inventory_items_expense_account_id_foreign` (`expense_account_id`),
  KEY `idx_inventory_items_type` (`institute_id`,`item_type`,`is_active`),
  KEY `idx_inventory_items_category` (`category_id`),
  CONSTRAINT `inventory_items_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_cogs_account_id_foreign` FOREIGN KEY (`cogs_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_expense_account_id_foreign` FOREIGN KEY (`expense_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_items_inventory_account_id_foreign` FOREIGN KEY (`inventory_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_sales_account_id_foreign` FOREIGN KEY (`sales_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_items`
--

LOCK TABLES `inventory_items` WRITE;
/*!40000 ALTER TABLE `inventory_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_movements`
--

DROP TABLE IF EXISTS `inventory_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `warehouse_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `movement_type` varchar(30) NOT NULL,
  `quantity` decimal(19,4) NOT NULL,
  `unit_cost` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `movement_no` varchar(30) NOT NULL,
  `reference_type` varchar(40) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `occurred_at` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `line_meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`line_meta`)),
  `status` varchar(20) NOT NULL DEFAULT 'posted',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_movements_no` (`institute_id`,`movement_no`),
  KEY `inventory_movements_branch_id_foreign` (`branch_id`),
  KEY `inventory_movements_warehouse_id_foreign` (`warehouse_id`),
  KEY `inventory_movements_item_id_foreign` (`item_id`),
  KEY `inventory_movements_batch_id_foreign` (`batch_id`),
  KEY `inventory_movements_journal_id_foreign` (`journal_id`),
  KEY `idx_inventory_movements_wh` (`institute_id`,`warehouse_id`,`item_id`,`occurred_at`),
  KEY `idx_inventory_movements_item` (`institute_id`,`item_id`,`batch_id`),
  KEY `idx_inventory_movements_ref` (`reference_type`,`reference_id`),
  CONSTRAINT `inventory_movements_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_movements_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_movements_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_movements_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_movements_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_movements_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `inventory_warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_movements`
--

LOCK TABLES `inventory_movements` WRITE;
/*!40000 ALTER TABLE `inventory_movements` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_serial_numbers`
--

DROP TABLE IF EXISTS `inventory_serial_numbers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_serial_numbers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `warehouse_id` bigint(20) unsigned DEFAULT NULL,
  `serial_number` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'in_stock',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_serials_no` (`institute_id`,`item_id`,`serial_number`),
  KEY `inventory_serial_numbers_branch_id_foreign` (`branch_id`),
  KEY `inventory_serial_numbers_item_id_foreign` (`item_id`),
  KEY `inventory_serial_numbers_batch_id_foreign` (`batch_id`),
  KEY `inventory_serial_numbers_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `inventory_serial_numbers_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_serial_numbers_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_serial_numbers_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_serial_numbers_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_serial_numbers_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `inventory_warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_serial_numbers`
--

LOCK TABLES `inventory_serial_numbers` WRITE;
/*!40000 ALTER TABLE `inventory_serial_numbers` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_serial_numbers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_stock_levels`
--

DROP TABLE IF EXISTS `inventory_stock_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_stock_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `warehouse_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `avg_cost` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_stock_levels` (`institute_id`,`warehouse_id`,`item_id`,`batch_id`),
  KEY `inventory_stock_levels_branch_id_foreign` (`branch_id`),
  KEY `inventory_stock_levels_warehouse_id_foreign` (`warehouse_id`),
  KEY `inventory_stock_levels_item_id_foreign` (`item_id`),
  KEY `inventory_stock_levels_batch_id_foreign` (`batch_id`),
  CONSTRAINT `inventory_stock_levels_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_stock_levels_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_stock_levels_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_stock_levels_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_stock_levels_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `inventory_warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_stock_levels`
--

LOCK TABLES `inventory_stock_levels` WRITE;
/*!40000 ALTER TABLE `inventory_stock_levels` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_stock_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_transfer_items`
--

DROP TABLE IF EXISTS `inventory_transfer_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_transfer_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `transfer_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` decimal(19,4) NOT NULL,
  `unit_cost` decimal(19,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (`id`),
  KEY `inventory_transfer_items_institute_id_foreign` (`institute_id`),
  KEY `inventory_transfer_items_branch_id_foreign` (`branch_id`),
  KEY `inventory_transfer_items_item_id_foreign` (`item_id`),
  KEY `inventory_transfer_items_batch_id_foreign` (`batch_id`),
  KEY `idx_inventory_transfer_items_transfer` (`transfer_id`),
  CONSTRAINT `inventory_transfer_items_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_transfer_items_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transfer_items_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_transfer_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_transfer_items_transfer_id_foreign` FOREIGN KEY (`transfer_id`) REFERENCES `inventory_transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_transfer_items`
--

LOCK TABLES `inventory_transfer_items` WRITE;
/*!40000 ALTER TABLE `inventory_transfer_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_transfer_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_transfers`
--

DROP TABLE IF EXISTS `inventory_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `source_warehouse_id` bigint(20) unsigned NOT NULL,
  `destination_warehouse_id` bigint(20) unsigned NOT NULL,
  `transfer_no` varchar(30) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_transfers_no` (`institute_id`,`transfer_no`),
  KEY `inventory_transfers_branch_id_foreign` (`branch_id`),
  KEY `inventory_transfers_source_warehouse_id_foreign` (`source_warehouse_id`),
  KEY `inventory_transfers_destination_warehouse_id_foreign` (`destination_warehouse_id`),
  CONSTRAINT `inventory_transfers_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transfers_destination_warehouse_id_foreign` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `inventory_warehouses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_transfers_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_transfers_source_warehouse_id_foreign` FOREIGN KEY (`source_warehouse_id`) REFERENCES `inventory_warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_transfers`
--

LOCK TABLES `inventory_transfers` WRITE;
/*!40000 ALTER TABLE `inventory_transfers` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_warehouses`
--

DROP TABLE IF EXISTS `inventory_warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_warehouses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(30) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_warehouses_code` (`institute_id`,`branch_id`,`code`),
  KEY `inventory_warehouses_branch_id_foreign` (`branch_id`),
  CONSTRAINT `inventory_warehouses_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_warehouses_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_warehouses`
--

LOCK TABLES `inventory_warehouses` WRITE;
/*!40000 ALTER TABLE `inventory_warehouses` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_warehouses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `description` varchar(200) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` decimal(19,4) DEFAULT NULL,
  `unit_price` decimal(19,4) DEFAULT NULL,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `tax_rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `coa_id` bigint(20) unsigned DEFAULT NULL,
  `inventory_item_id` bigint(20) unsigned DEFAULT NULL,
  `sales_order_line_id` bigint(20) unsigned DEFAULT NULL,
  `fee_head_id` bigint(20) unsigned DEFAULT NULL,
  `tax_group_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_items_invoice` (`invoice_id`),
  KEY `invoice_items_tax_group_id_foreign` (`tax_group_id`),
  KEY `idx_invoice_items_coa` (`coa_id`),
  KEY `idx_invoice_items_invoice_fee_head` (`invoice_id`,`fee_head_id`),
  KEY `fk_invoice_items_fee_head` (`fee_head_id`),
  KEY `idx_invoice_items_inventory` (`inventory_item_id`),
  KEY `idx_invoice_items_so_line` (`sales_order_line_id`),
  CONSTRAINT `fk_invoice_items_fee_head` FOREIGN KEY (`fee_head_id`) REFERENCES `fee_heads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_items_coa_id_foreign` FOREIGN KEY (`coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoice_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoice_items_sales_order_line_id_foreign` FOREIGN KEY (`sales_order_line_id`) REFERENCES `sales_order_lines` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoice_items_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `enrollment_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_type` enum('admission','course_fee','exam_fee','certificate_fee','other') NOT NULL DEFAULT 'course_fee',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payable_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `due_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('unpaid','partial','paid','cancelled') NOT NULL DEFAULT 'unpaid',
  `due_date` date DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `exchange_rate` decimal(19,8) DEFAULT NULL,
  `base_payable_amount` decimal(19,4) DEFAULT NULL,
  `tax_group_id` bigint(20) unsigned DEFAULT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `sales_order_id` bigint(20) unsigned DEFAULT NULL,
  `sales_delivery_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`invoice_meta`)),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoices_institute_number` (`institute_id`,`invoice_number`),
  KEY `idx_invoices_institute` (`institute_id`),
  KEY `idx_invoices_student` (`student_id`),
  KEY `idx_invoices_status` (`institute_id`,`status`),
  KEY `fk_invoices_enrollment` (`enrollment_id`),
  KEY `fk_invoices_created_by` (`created_by`),
  KEY `invoices_currency_id_foreign` (`currency_id`),
  KEY `invoices_tax_group_id_foreign` (`tax_group_id`),
  KEY `invoices_journal_id_foreign` (`journal_id`),
  KEY `idx_invoices_party` (`party_id`),
  KEY `idx_invoices_status_due` (`institute_id`,`status`,`due_amount`),
  KEY `idx_invoices_sales_order` (`sales_order_id`),
  KEY `idx_invoices_sales_delivery` (`sales_delivery_id`),
  CONSTRAINT `fk_invoices_created_by` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoices_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `student_enrollments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoices_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invoices_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_sales_delivery_id_foreign` FOREIGN KEY (`sales_delivery_id`) REFERENCES `sales_deliveries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_entries`
--

DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `journal_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `coa_id` bigint(20) unsigned NOT NULL,
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `journal_date` date NOT NULL,
  `foreign_debit` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `foreign_credit` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `exchange_rate` decimal(19,8) NOT NULL DEFAULT 1.00000000,
  `debit` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `credit` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `memo` varchar(255) DEFAULT NULL,
  `line_meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`line_meta`)),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_journal_entries_journal` (`journal_id`),
  KEY `idx_journal_entries_coa` (`coa_id`,`journal_date`),
  KEY `idx_journal_entries_party` (`party_id`),
  KEY `idx_journal_entries_branch` (`institute_id`,`branch_id`,`journal_date`),
  KEY `journal_entries_branch_id_foreign` (`branch_id`),
  KEY `journal_entries_currency_id_foreign` (`currency_id`),
  KEY `idx_journal_entries_currency` (`institute_id`,`currency_id`),
  KEY `idx_je_party_coa` (`party_id`,`coa_id`),
  KEY `idx_je_journal_coa` (`journal_id`,`coa_id`),
  KEY `idx_je_coa_date` (`coa_id`,`journal_date`),
  KEY `idx_je_party` (`party_id`),
  CONSTRAINT `journal_entries_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `journal_entries_coa_id_foreign` FOREIGN KEY (`coa_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `journal_entries_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `journal_entries_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journal_entries_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journal_entries_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_entries`
--

LOCK TABLES `journal_entries` WRITE;
/*!40000 ALTER TABLE `journal_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `journal_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journals`
--

DROP TABLE IF EXISTS `journals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `journal_no` varchar(30) NOT NULL,
  `journal_date` date NOT NULL,
  `fiscal_year_id` bigint(20) unsigned NOT NULL,
  `period_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('sale','purchase','receipt','payment','journal','contra','opening','adjustment') NOT NULL,
  `ref_type` varchar(40) DEFAULT NULL,
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `currency_id` bigint(20) unsigned NOT NULL,
  `exchange_rate` decimal(19,8) NOT NULL DEFAULT 1.00000000,
  `status` enum('draft','posted','reversed','void') NOT NULL DEFAULT 'draft',
  `description` varchar(500) DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `reversed_by` bigint(20) unsigned DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `reversal_of` bigint(20) unsigned DEFAULT NULL,
  `source` enum('app','ai','sync','migration','import') NOT NULL DEFAULT 'app',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_journals_journal_no` (`journal_no`),
  UNIQUE KEY `uq_journals_no` (`institute_id`,`branch_id`,`journal_no`),
  KEY `journals_period_id_foreign` (`period_id`),
  KEY `idx_journals_date` (`institute_id`,`journal_date`),
  KEY `idx_journals_fy` (`fiscal_year_id`),
  KEY `idx_journals_ref` (`ref_type`,`ref_id`),
  KEY `idx_journals_currency` (`currency_id`),
  KEY `journals_branch_id_foreign` (`branch_id`),
  KEY `idx_journals_status_institute` (`status`,`institute_id`),
  KEY `idx_journals_reversal_of` (`reversal_of`),
  CONSTRAINT `journals_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `journals_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `journals_fiscal_year_id_foreign` FOREIGN KEY (`fiscal_year_id`) REFERENCES `fiscal_years` (`id`),
  CONSTRAINT `journals_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journals_period_id_foreign` FOREIGN KEY (`period_id`) REFERENCES `accounting_periods` (`id`),
  CONSTRAINT `journals_reversal_of_foreign` FOREIGN KEY (`reversal_of`) REFERENCES `journals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journals`
--

LOCK TABLES `journals` WRITE;
/*!40000 ALTER TABLE `journals` DISABLE KEYS */;
/*!40000 ALTER TABLE `journals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `user_type` enum('platform_admin','institute_user') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `is_success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_email` (`email`),
  KEY `idx_login_attempts_ip_time` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=457 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=330 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0000_00_00_000000_baseline_account_heads',1),(2,'0000_00_00_000000_baseline_activity_logs',1),(3,'0000_00_00_000000_baseline_attendance',1),(4,'0000_00_00_000000_baseline_audit_logs',1),(5,'0000_00_00_000000_baseline_batches',1),(6,'0000_00_00_000000_baseline_branches',1),(7,'0000_00_00_000000_baseline_cash_memos',1),(8,'0000_00_00_000000_baseline_certificates',1),(9,'0000_00_00_000000_baseline_courses',1),(10,'0000_00_00_000000_baseline_course_categories',1),(11,'0000_00_00_000000_baseline_course_requests',1),(12,'0000_00_00_000000_baseline_course_subjects',1),(13,'0000_00_00_000000_baseline_course_sub_categories',1),(14,'0000_00_00_000000_baseline_exams',1),(15,'0000_00_00_000000_baseline_exam_results',1),(16,'0000_00_00_000000_baseline_exam_types',1),(17,'0000_00_00_000000_baseline_gallery_albums',1),(18,'0000_00_00_000000_baseline_gallery_media',1),(19,'0000_00_00_000000_baseline_grading_scale',1),(20,'0000_00_00_000000_baseline_installments',1),(21,'0000_00_00_000000_baseline_institutes',1),(22,'0000_00_00_000000_baseline_institute_courses',1),(23,'0000_00_00_000000_baseline_institute_settings',1),(24,'0000_00_00_000000_baseline_institute_subjects',1),(25,'0000_00_00_000000_baseline_institute_subscriptions',1),(26,'0000_00_00_000000_baseline_institute_users',1),(27,'0000_00_00_000000_baseline_invoices',1),(28,'0000_00_00_000000_baseline_invoice_items',1),(29,'0000_00_00_000000_baseline_login_attempts',1),(30,'0000_00_00_000000_baseline_notices',1),(31,'0000_00_00_000000_baseline_notifications',1),(32,'0000_00_00_000000_baseline_notification_reads',1),(33,'0000_00_00_000000_baseline_offline_sync_queue',1),(34,'0000_00_00_000000_baseline_payments',1),(35,'0000_00_00_000000_baseline_permissions',1),(36,'0000_00_00_000000_baseline_platform_admins',1),(37,'0000_00_00_000000_baseline_reg_no_sequence',1),(38,'0000_00_00_000000_baseline_results',1),(39,'0000_00_00_000000_baseline_roles',1),(40,'0000_00_00_000000_baseline_role_permissions',1),(41,'0000_00_00_000000_baseline_rooms',1),(42,'0000_00_00_000000_baseline_schema_migrations',1),(43,'0000_00_00_000000_baseline_students',1),(44,'0000_00_00_000000_baseline_student_enrollments',1),(45,'0000_00_00_000000_baseline_subjects',1),(46,'0000_00_00_000000_baseline_subscription_packages',1),(47,'0000_00_00_000000_baseline_themes',1),(48,'0000_00_00_000000_baseline_transactions',1),(49,'0000_00_00_000000_baseline_users',1),(50,'2026_08_12_000000_seed_default_role_permissions',2),(51,'2026_08_13_000000_add_industry_to_institutes_table',3),(52,'2026_08_13_000100_add_auth_columns_to_institute_users_table',4),(53,'2026_08_13_000200_add_auth_columns_to_platform_admins_table',4),(54,'2026_08_13_000300_create_sessions_table',4),(55,'2026_08_13_000400_create_password_reset_tokens_table',5),(56,'2026_08_14_000000_create_institution_user_table',6),(57,'2026_08_14_000100_expand_users_table',7),(58,'2026_08_14_000200_add_uuid_defaults',7),(59,'2026_08_14_000300_add_account_type_to_users_table',7),(60,'2026_08_14_000300_add_preferences_to_user_tables',7),(61,'2026_08_14_000400_create_settings_table',8),(62,'2026_08_14_000500_add_sidebar_color_to_institute_settings_table',9),(63,'2026_08_15_000000_add_ai_config_to_institute_settings_table',10),(64,'2026_08_15_000100_create_ai_logs_table',10),(65,'2026_08_15_000200_create_ai_usage_table',10),(66,'2026_08_15_000300_add_ai_permissions',10),(67,'0001_01_01_000002_create_jobs_table',11),(69,'2026_08_14_000600_add_deleted_at_to_certificates_table',12),(70,'2026_08_14_195437_add_sub_industry_to_institutes_table',12),(72,'2026_08_15_124321_add_document_to_students_table',13),(73,'2026_08_15_130000_add_unique_login_and_document_indexes_to_students_table',14),(74,'2026_08_15_180000_add_country_to_students_table',14),(75,'2026_08_15_190000_create_geo_tables',14),(76,'2026_08_15_190100_add_global_address_columns',14),(77,'2026_08_16_000000_add_tall_navigation_to_institute_settings_table',14),(78,'2026_08_16_200000_add_geo_import_history_and_unique_unit_code',14),(79,'2026_08_16_210000_add_archived_to_batches_status',14),(80,'2026_08_16_220000_add_deleted_at_to_batches_table',14),(81,'2026_08_16_230000_create_exam_subjects_table',14),(82,'2026_08_16_240000_add_other_marks_to_exam_subjects_table',14),(83,'2026_08_16_250000_widen_exam_marks_columns',14),(84,'2026_08_17_000000_create_subject_requests_table',14),(85,'2026_08_17_010000_add_attendance_marks_to_exam_tables',14),(86,'2026_08_17_020000_add_pass_marks_to_exam_subjects_table',14),(87,'2026_08_17_100000_create_academic_structure_tables',14),(88,'2026_08_17_100100_create_institute_academic_structure_tables',14),(89,'2026_08_17_100200_add_education_manage_permission',14),(90,'2026_08_17_110000_create_subject_academic_assignments_table',14),(91,'2026_08_17_110100_add_override_columns_to_institute_subjects_table',14),(92,'2026_08_17_120000_create_academic_selection_groups_table',14),(93,'2026_08_17_120100_add_requirement_columns_to_subject_academic_assignments_table',14),(94,'2026_08_17_120200_add_selection_columns_to_institute_subjects_table',14),(95,'2026_08_17_130000_create_academic_years_table',14),(96,'2026_08_17_130100_create_student_academic_placements_table',14),(97,'2026_08_17_130200_create_student_subject_selections_table',14),(98,'2026_08_17_131000_add_institute_id_to_student_subject_selections_table',14),(99,'2026_08_17_140000_create_assessment_types_table',14),(100,'2026_08_17_140100_create_components_table',14),(101,'2026_08_17_140200_create_academic_assessments_table',14),(102,'2026_08_17_140300_create_assessment_subjects_table',14),(103,'2026_08_17_140400_create_assessment_subject_components_table',14),(104,'2026_08_17_150000_add_pass_rule_to_assessment_subjects_table',14),(105,'2026_08_17_150100_create_academic_student_marks_table',14),(106,'2026_08_17_160000_create_academic_result_aggregation_schemes_table',14),(107,'2026_08_17_160100_create_academic_result_aggregation_items_table',14),(108,'2026_08_17_170000_create_grade_scales_table',14),(109,'2026_08_17_170100_create_grade_scale_rows_table',14),(110,'2026_08_17_170200_add_credit_hours_and_gpa_inclusion_to_subject_tables',14),(111,'2026_08_18_100000_create_academic_final_result_policies_table',14),(112,'2026_08_18_100100_create_academic_final_results_table',14),(113,'2026_08_18_100200_create_academic_final_result_students_table',14),(114,'2026_08_18_100300_create_academic_final_result_rows_table',14),(115,'2026_08_18_110000_create_promotion_policies_table',14),(116,'2026_08_18_110100_create_promotion_policy_rules_table',14),(117,'2026_08_18_110200_create_promotion_decisions_table',14),(118,'2026_08_18_110300_create_promotion_decision_items_table',14),(119,'2026_08_18_110400_add_promotion_manage_permission',14),(120,'2026_08_19_000000_create_crm_tables',14),(121,'2026_08_19_000100_add_crm_permissions',14),(122,'2026_08_19_010000_create_accounting_core_tables',14),(123,'2026_08_19_010100_create_accounting_coa_tables',14),(124,'2026_08_19_010200_create_accounting_party_tables',14),(125,'2026_08_19_010300_create_accounting_period_tables',14),(126,'2026_08_19_010400_create_accounting_journal_tables',14),(127,'2026_08_19_010500_create_accounting_reporting_tables',14),(128,'2026_08_19_010600_alter_legacy_finance_tables',14),(129,'2026_08_19_010700_seed_accounting_currencies',14),(130,'2026_08_19_010800_add_accounting_permissions',14),(131,'2026_08_19_020000_allow_institute_wide_journals',14),(132,'2026_08_19_020100_allow_whole_institute_reporting',14),(133,'2026_08_20_000000_add_student_crm_links_to_students_table',14),(134,'2026_08_21_000000_add_admission_workflow_to_students_table',14),(135,'2026_08_21_000100_add_fx_columns_to_accounting_tables',14),(136,'2026_08_21_000100_add_registration_sequence_columns_to_reg_no_sequence_table',14),(137,'2026_08_21_000200_add_fx_permissions',14),(138,'2026_08_21_000200_create_fee_heads_table',14),(139,'2026_08_21_000300_create_fee_structures_tables',14),(140,'2026_08_21_000400_create_student_waivers_table',14),(141,'2026_08_21_000500_add_fee_head_id_to_invoice_items_table',14),(142,'2026_08_21_000600_add_waive_to_audit_action_enum',14),(143,'2026_08_21_000700_add_admission_pipeline_to_students_table',14),(144,'2026_08_21_000800_add_lock_to_academic_assessments_table',14),(145,'2026_08_21_154657_create_personal_access_tokens_table',14),(146,'2026_08_21_160000_create_budget_tables',14),(147,'2026_08_21_160100_add_budget_permissions',14),(148,'2026_08_21_170000_add_accounting_hardening_constraints',14),(149,'2026_08_22_000000_create_teacher_profiles_table',14),(150,'2026_08_22_000100_create_teacher_academic_assignments_table',14),(151,'2026_08_22_000200_create_teacher_code_sequences_table',14),(152,'2026_08_22_000300_add_teacher_permissions',14),(153,'2026_08_23_000000_add_academic_year_id_to_batches_table',14),(154,'2026_08_23_000000_create_course_curriculum_tables',14),(155,'2026_08_23_000100_add_curriculum_reference_to_batches_table',14),(156,'2026_08_23_000100_create_user_module_access_table',14),(157,'2026_08_23_000200_add_course_master_content_columns_to_courses_table',14),(158,'2026_08_23_000300_create_course_materials_table',14),(159,'2026_08_23_000400_add_curriculum_permissions',14),(161,'2026_08_23_061432_add_cash_flow_defaults_and_accounting_indexes',15),(162,'2026_08_23_064506_create_bank_reconciliation_tables',15),(163,'2026_08_23_070623_create_approval_workflow_tables',15),(164,'2026_08_24_000100_create_notification_templates_table',15),(165,'2026_08_24_000200_create_notification_logs_table',15),(166,'2026_08_24_000300_create_notification_preferences_table',15),(167,'2026_08_24_000400_add_notification_settings_to_institute_settings_table',15),(168,'2026_08_24_000500_create_documents_tables',15),(169,'2026_08_24_000600_add_document_permissions',15),(170,'2026_08_24_000700_create_guardian_tables',15),(171,'2026_08_25_000000_create_certificate_types_table',15),(172,'2026_08_25_000100_add_certificate_type_id_to_certificates_table',15),(173,'2026_08_25_000100_create_inventory_tables',15),(174,'2026_08_25_000200_add_inventory_permissions',15),(175,'2026_08_25_100000_create_calendar_events_table',15),(176,'2026_08_25_100100_create_calendar_event_reminders_table',15),(177,'2026_08_28_000100_create_alumni_tables',15),(178,'2026_08_28_000200_add_alumni_permissions',15),(179,'2026_08_29_000100_create_fixed_asset_tables',15),(180,'2026_08_29_000100_extend_documents_for_step51',15),(181,'2026_08_29_000200_add_fixed_asset_permissions',15),(182,'2026_08_29_000200_add_workflow_permissions',15),(183,'2026_08_30_000100_create_tax_engine_tables',15),(184,'2026_08_30_000200_add_tax_engine_permissions',15),(185,'2026_08_31_000100_add_production_hardening_indexes',15),(186,'2026_09_01_000000_create_online_payment_tables',15),(187,'2026_09_01_000100_add_online_payment_permissions',15),(188,'2026_09_01_000200_alter_payment_method_enum_add_online',15),(189,'2026_09_02_000100_create_saa_s_module_tables',15),(190,'2026_09_03_000100_create_hr_core_tables',15),(191,'2026_09_03_000200_add_hr_permissions',15),(192,'2026_09_03_000300_add_soft_deletes_to_tax_jurisdictions_table',15),(193,'2026_09_03_000400_create_hr_employment_lifecycle_tables',15),(194,'2026_09_03_000500_add_hr_lifecycle_permissions',15),(195,'2026_09_03_000600_add_hr_document_support',15),(196,'2026_09_04_000100_create_hr_attendance_leave_tables',15),(197,'2026_09_04_000200_add_hr_attendance_leave_permissions',15),(198,'2026_09_04_000300_create_hr_payroll_core_tables',15),(199,'2026_09_04_000400_add_hr_payroll_permissions',15),(200,'2026_09_04_000500_create_hr_recruitment_tables',15),(201,'2026_09_04_000600_add_hr_recruitment_permissions',15),(202,'2026_09_05_000100_create_hr_performance_training_tables',15),(203,'2026_09_05_000200_add_hr_performance_training_permissions',15),(204,'2026_09_05_000300_create_hr_selfservice_workflow_tables',15),(205,'2026_09_05_000400_add_hr_selfservice_permissions',15),(206,'2026_09_05_000500_add_hr_reports_permission',15),(207,'2026_09_06_000100_add_sales_config_to_institute_settings',15),(208,'2026_09_06_000200_create_sales_sequences',15),(209,'2026_09_06_000300_add_sales_permissions',15),(210,'2026_09_06_000400_add_purchase_config_to_institute_settings',15),(211,'2026_09_07_000100_create_purchase_order_and_goods_receipt_tables',15),(212,'2026_09_07_000100_create_sales_quotations',15),(213,'2026_09_07_000200_add_purchase_order_goods_receipt_permissions',15),(214,'2026_09_08_000100_create_sales_orders',16),(215,'2026_09_08_000200_create_sales_deliveries',17),(216,'2026_09_09_000100_create_purchase_orders',18),(217,'2026_09_10_000100_create_purchase_quotations',19),(218,'2026_09_10_000100_create_sales_returns',20),(219,'2026_09_11_000100_create_purchase_invoices',21),(220,'2026_09_12_000100_create_purchase_returns',22),(221,'2026_09_15_000400_create_institute_module_entitlements_table',23),(222,'2026_09_23_000100_create_purchase_requests',24),(223,'2026_09_09_000100_create_purchase_orders',25),(224,'2026_09_10_000100_create_purchase_quotations',26),(225,'2026_09_11_000100_create_purchase_invoices',27),(226,'2026_09_12_000100_create_purchase_returns',28),(227,'2026_09_23_000100_create_purchase_requests',29),(228,'2026_09_08_000100_create_sales_orders',30),(229,'2026_09_08_000200_create_sales_deliveries',31),(230,'2026_09_10_000100_create_sales_returns',32),(231,'2026_09_07_000300_add_receipt_to_purchase_sequences_document_type',33),(232,'2026_09_09_000100_add_sales_link_to_invoices',33),(233,'2026_09_10_000200_add_batch_fields_to_goods_receipt_items',33),(234,'2026_09_10_000200_alter_sales_sequences_for_returns',33),(235,'2026_09_10_000300_add_goods_receipt_reverse_permission',33),(236,'2026_09_15_000100_normalize_package_slugs_to_canonical',33),(237,'2026_09_15_000200_add_attendance_view_permission',33),(238,'2026_09_15_000300_add_fk_converted_to_order_id_to_sales_quotations',33),(239,'2026_10_01_000100_create_system_backups_table',34),(240,'2026_10_01_000200_create_tenant_protection_tables',34),(241,'2026_10_01_000300_create_system_schema_versions_table',35),(242,'2026_10_01_000400_create_backup_verification_logs_table',36),(243,'2026_10_01_000500_create_database_query_logs_table',37),(244,'2026_10_01_000600_create_archive_tables',38),(245,'2026_10_01_000700_create_system_seed_versions_table',39),(246,'2026_10_01_000800_create_import_batches_table',40),(247,'2026_08_23_135214_add_daily_weekly_to_system_backups_type_enum',41),(251,'2026_08_23_141848_create_performance_indexes_step123',42),(252,'2026_08_15_000400_create_industry_settings_table',11),(253,'2026_09_16_000100_add_cash_flow_category_to_chart_of_accounts',11),(254,'2026_08_12_000000_create_roles_permissions_tables',11),(255,'2026_10_01_000900_create_query_fingerprints_table',43),(256,'2026_10_01_001000_create_endpoint_performance_logs_table',43),(257,'2026_08_19_000000_create_academic_cumulative_results_table',44),(258,'2026_08_19_000001_create_academic_cumulative_result_entries_table',44),(259,'2026_08_19_000002_add_precision_and_rounding_to_grade_scales_table',44),(260,'2026_08_24_000001_add_vat_module_to_registry',44),(261,'2026_08_24_000002_add_approval_columns_to_students_table',44),(262,'2026_08_24_000100_create_learning_structure_engine_tables',44),(263,'2026_08_24_000800_widen_audit_logs_action_column',44),(264,'2026_08_24_000900_add_recurring_fields_to_fee_heads',44),(265,'2026_08_24_000910_add_frequency_to_fee_structures',44),(266,'2026_08_24_000920_add_receipt_fields_to_payments',44),(267,'2026_08_24_000930_create_monthly_fee_periods_table',44),(268,'2026_08_25_000010_create_platform_service_configs_table',44),(269,'2026_08_25_000100_add_recurring_fee_generated_to_audit_trails',44),(270,'2026_08_25_000200_add_unique_receipt_constraint_to_payments',44),(271,'2026_08_25_000300_add_fk_to_fee_heads_fee_structures',44),(272,'2026_08_26_000001_add_identity_fields_to_users_table',44),(273,'2026_08_26_000002_create_phone_verification_otps_table',44),(274,'2026_08_26_000003_make_email_nullable',44),(275,'2026_08_26_000004_create_phone_password_reset_otps_table',44),(276,'2026_08_26_000005_add_two_factor_to_guardians_table',44),(277,'2026_08_26_171509_add_certificate_approval_mode_to_institute_settings_table',44),(278,'2026_08_27_000001_create_pending_registrations_table',44),(279,'2026_08_27_000001_harden_subject_foreign_keys_to_restrict',44),(280,'2026_08_27_000001_update_certificate_approval_mode_default_to_admin',44),(281,'2026_08_27_000002_allow_custom_course_level',44),(282,'2026_08_27_000002_harden_aggregation_foreign_keys_to_restrict',44),(283,'2026_08_27_000003_add_unique_to_academic_assessments',44),(284,'2026_08_27_000004_add_optional_bonus_threshold_to_grade_scales',44),(285,'2026_08_27_170000_add_inactivity_to_users_table',44),(286,'2026_08_28_000000_fix_course_curricula_user_foreign_keys',44),(287,'2026_08_28_000001_add_multiple_optional_policy_to_grade_scales',44),(288,'2026_08_28_100000_restructure_industry_institution_domain_taxonomy',44),(289,'2026_08_29_000000_verify_trusted_demo_accounts',44),(290,'2026_08_31_000100_create_e18_email_otp_and_2fa_methods',44),(291,'2026_08_31_000200_create_phone_2fa_otps_table',44),(292,'2026_10_02_000100_add_lockout_to_platform_admins_table',44),(293,'2026_10_03_000100_enforce_single_immutable_platform_admin',44),(294,'2026_10_03_000200_create_platform_staffs_table',44),(295,'2026_10_04_000001_add_is_test_to_core_tables',45),(296,'2026_08_28_000100_add_archive_to_class_grades_table',46),(297,'2026_08_28_000101_add_structure_snapshot_to_student_academic_placements_table',46),(298,'2026_08_28_000001_change_class_grade_fk_to_restrict',47),(299,'2026_08_29_000001_change_weight_to_decimal',48),(300,'2026_08_29_000002_add_workflow_id_to_academic_final_results_table',48),(301,'2026_08_29_000003_add_cancelled_to_promotion_decisions_table',48),(302,'2026_08_29_000004_create_country_pass_mark_defaults_table',48),(303,'2026_08_28_000102_add_soft_delete_to_student_academic_placements_table',49),(304,'2026_11_01_000001_fix_course_level_enum',50),(305,'2026_11_01_000002_create_training_enrollments_table',50),(306,'2026_11_01_000003_add_training_config_to_institute_settings',50),(307,'2026_08_20_000001_update_batches_status_enum_ongoing',51),(308,'2026_08_30_132117_create_training_batch_results_table',52),(309,'2026_11_01_000004_add_uid_to_users_table',53),(310,'2026_11_01_000005_add_uid_to_institutes_table',54),(311,'2026_11_02_000001_expand_uid_to_ten_chars',55),(312,'2026_11_02_000002_add_reg_no_to_students',56),(313,'2026_11_02_000003_fix_tenant_uid_to_ten_chars',57),(314,'2026_11_02_000005_add_roll_no_to_enrollments_table',58),(315,'2026_11_02_000006_add_student_id_to_students_table',59),(316,'2026_11_02_000007_add_user_id_to_students_table',60),(317,'2026_08_30_163529_change_training_batch_results_to_student_id',61),(318,'2026_08_30_170912_add_attendance_threshold_to_batches',62),(319,'2026_11_02_000008_add_template_id_to_certificates_table',63),(320,'2026_11_14_000001_backfill_empty_student_ids',64),(321,'2026_08_31_000001_add_logo_path_to_institutes_table',65),(322,'2026_11_14_000002_create_ai_api_keys_table',66),(323,'2026_11_14_000003_add_capability_to_ai_api_keys_table',67),(324,'2026_11_03_000001_change_enrollments_trainee_id_to_student_id',68),(325,'2026_09_01_031222_migrate_yasin_to_enrollments',69),(326,'2026_11_14_000004_add_is_principal_to_branches_table',70),(327,'2026_09_01_033705_add_code_to_branches_table',71),(328,'2026_09_01_033931_add_missing_foreign_keys_to_58_tables',72),(329,'2026_11_14_000005_merge_student_enrollments_into_enrollments',73);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `module_access_logs`
--

DROP TABLE IF EXISTS `module_access_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_access_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `module_key` varchar(60) NOT NULL,
  `action` varchar(60) NOT NULL COMMENT 'enabled, disabled, override_added, override_removed, package_changed',
  `actor_id` bigint(20) unsigned DEFAULT NULL COMMENT 'User or PlatformAdmin ID',
  `previous_state` varchar(60) DEFAULT NULL,
  `new_state` varchar(60) DEFAULT NULL,
  `package_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `module_access_logs_institute_id_foreign` (`institute_id`),
  KEY `module_access_logs_package_id_foreign` (`package_id`),
  CONSTRAINT `module_access_logs_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `module_access_logs_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `subscription_packages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `module_access_logs`
--

LOCK TABLES `module_access_logs` WRITE;
/*!40000 ALTER TABLE `module_access_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `module_access_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `module_registry`
--

DROP TABLE IF EXISTS `module_registry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_registry` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(60) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('core','industry') NOT NULL DEFAULT 'core',
  `description` varchar(255) DEFAULT NULL,
  `dependencies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of module keys this module depends on' CHECK (json_valid(`dependencies`)),
  `sort_order` tinyint(4) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_registry_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `module_registry`
--

LOCK TABLES `module_registry` WRITE;
/*!40000 ALTER TABLE `module_registry` DISABLE KEYS */;
INSERT INTO `module_registry` VALUES (1,'crm','CRM','core','Customer relationship management',NULL,1,'active','2026-08-23 05:52:07','2026-08-23 05:52:07'),(2,'accounting','Accounting','core','Financial accounting & ledger',NULL,2,'active','2026-08-23 05:52:07','2026-08-23 05:52:07'),(3,'finance','Finance','core','Finance management & invoicing',NULL,3,'active','2026-08-23 05:52:07','2026-08-23 05:52:07'),(4,'inventory','Inventory','core','Inventory & stock management',NULL,4,'active','2026-08-23 05:52:07','2026-08-23 05:52:07'),(5,'hr','HR','core','Human resources management',NULL,5,'active','2026-08-23 05:52:07','2026-08-23 05:52:07'),(6,'sales','Sales','core','Sales pipeline & quotes',NULL,6,'active','2026-08-23 05:52:07','2026-08-23 05:52:07'),(7,'purchase','Purchase','core','Purchase orders & procurement',NULL,7,'active','2026-08-23 05:52:07','2026-08-23 05:52:07'),(8,'reports','Reports','core','Analytics & reporting',NULL,8,'active','2026-08-23 05:52:07','2026-08-23 05:52:07'),(9,'notifications','Notifications','core','In-app & push notifications',NULL,9,'active','2026-08-23 05:52:07','2026-08-23 05:52:07'),(10,'ai','AI','core','AI assistant & tools',NULL,10,'active','2026-08-23 05:52:07','2026-08-23 05:52:07'),(11,'education','Education','industry','Education management (students, exams, results, certificates)',NULL,20,'active','2026-08-23 05:52:07','2026-08-23 05:52:07'),(12,'vat','VAT / Tax','core','VAT & tax configuration, returns and compliance',NULL,11,'active','2026-08-28 03:54:39','2026-08-28 03:54:39');
/*!40000 ALTER TABLE `module_registry` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `monthly_fee_periods`
--

DROP TABLE IF EXISTS `monthly_fee_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `monthly_fee_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `fee_structure_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `enrollment_id` bigint(20) unsigned NOT NULL,
  `period_month` date NOT NULL,
  `invoice_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','generated','paid','overdue') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_monthly_fee_period` (`institute_id`,`fee_structure_id`,`student_id`,`enrollment_id`,`period_month`),
  KEY `idx_mfp_inst_status` (`institute_id`,`status`),
  KEY `idx_mfp_inst_period` (`institute_id`,`period_month`),
  KEY `idx_mfp_student` (`student_id`),
  KEY `idx_mfp_invoice` (`invoice_id`),
  KEY `fk_mfp_branch` (`branch_id`),
  KEY `fk_mfp_fee_structure` (`fee_structure_id`),
  KEY `fk_mfp_enrollment` (`enrollment_id`),
  CONSTRAINT `fk_mfp_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mfp_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `student_enrollments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mfp_fee_structure` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mfp_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mfp_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mfp_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `monthly_fee_periods`
--

LOCK TABLES `monthly_fee_periods` WRITE;
/*!40000 ALTER TABLE `monthly_fee_periods` DISABLE KEYS */;
/*!40000 ALTER TABLE `monthly_fee_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notices`
--

DROP TABLE IF EXISTS `notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `description` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `publish_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('draft','published','expired') NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notices_institute_slug` (`institute_id`,`slug`),
  KEY `idx_notices_institute_status` (`institute_id`,`status`,`publish_date`),
  KEY `fk_notices_branch` (`branch_id`),
  KEY `fk_notices_created_by` (`created_by`),
  CONSTRAINT `fk_notices_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notices_created_by` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notices_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notices`
--

LOCK TABLES `notices` WRITE;
/*!40000 ALTER TABLE `notices` DISABLE KEYS */;
/*!40000 ALTER TABLE `notices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_logs`
--

DROP TABLE IF EXISTS `notification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `template_id` bigint(20) unsigned DEFAULT NULL,
  `notification_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(100) NOT NULL,
  `recipient_type` enum('institute_user','platform_admin','student','external_email','external_phone') DEFAULT NULL,
  `recipient_id` bigint(20) unsigned DEFAULT NULL,
  `recipient_contact` varchar(255) DEFAULT NULL,
  `channel` enum('in_app','email','sms') NOT NULL,
  `status` enum('queued','sending','sent','failed','skipped') NOT NULL DEFAULT 'queued',
  `subject` varchar(190) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `queued_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `retry_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_retries` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `provider` varchar(60) DEFAULT NULL,
  `provider_message_id` varchar(190) DEFAULT NULL,
  `provider_response` text DEFAULT NULL,
  `error` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_logs_status_retry_count_index` (`status`,`retry_count`),
  KEY `notification_logs_recipient_type_recipient_id_index` (`recipient_type`,`recipient_id`),
  KEY `notification_logs_institute_id_event_index` (`institute_id`,`event`),
  KEY `notification_logs_institute_id_index` (`institute_id`),
  KEY `notification_logs_template_id_index` (`template_id`),
  KEY `notification_logs_notification_id_index` (`notification_id`),
  CONSTRAINT `notification_logs_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `notification_logs_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `notification_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_logs`
--

LOCK TABLES `notification_logs` WRITE;
/*!40000 ALTER TABLE `notification_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_preferences`
--

DROP TABLE IF EXISTS `notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_preferences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `recipient_type` enum('institute_user','platform_admin','student','external_email','external_phone') NOT NULL,
  `recipient_id` bigint(20) unsigned NOT NULL,
  `event` varchar(100) DEFAULT NULL,
  `channel` enum('in_app','email','sms') NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_preferences_scope_unique` (`recipient_type`,`recipient_id`,`event`,`channel`),
  KEY `notification_preferences_institute_id_index` (`institute_id`),
  CONSTRAINT `notification_preferences_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_preferences`
--

LOCK TABLES `notification_preferences` WRITE;
/*!40000 ALTER TABLE `notification_preferences` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_reads`
--

DROP TABLE IF EXISTS `notification_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_reads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` bigint(20) unsigned NOT NULL,
  `user_type` enum('platform_admin','institute_user','student') NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `read_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notification_reads` (`notification_id`,`user_type`,`user_id`),
  KEY `idx_notification_reads_user` (`user_type`,`user_id`),
  CONSTRAINT `fk_notification_reads_notification` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_reads`
--

LOCK TABLES `notification_reads` WRITE;
/*!40000 ALTER TABLE `notification_reads` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_reads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_templates`
--

DROP TABLE IF EXISTS `notification_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(100) NOT NULL,
  `channel` enum('in_app','email','sms') NOT NULL,
  `language` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(120) NOT NULL,
  `subject` varchar(190) DEFAULT NULL,
  `body` text NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_templates_scope_unique` (`institute_id`,`event`,`channel`,`language`),
  KEY `notification_templates_institute_id_index` (`institute_id`),
  CONSTRAINT `notification_templates_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_templates`
--

LOCK TABLES `notification_templates` WRITE;
/*!40000 ALTER TABLE `notification_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope` enum('platform','institute','user') NOT NULL,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `target_user_type` enum('platform_admin','institute_user','student') DEFAULT NULL,
  `target_user_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(40) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` varchar(500) NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `created_by_type` enum('platform_admin','institute_user','system') NOT NULL DEFAULT 'system',
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_scope_institute` (`scope`,`institute_id`),
  KEY `idx_notifications_target` (`target_user_type`,`target_user_id`),
  KEY `idx_notifications_created` (`created_at`),
  KEY `fk_notifications_institute` (`institute_id`),
  CONSTRAINT `fk_notifications_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications_archive`
--

DROP TABLE IF EXISTS `notifications_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications_archive` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_id` bigint(20) unsigned NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `original_created_at` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `notifications_archive_original_id_index` (`original_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications_archive`
--

LOCK TABLES `notifications_archive` WRITE;
/*!40000 ALTER TABLE `notifications_archive` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications_archive` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `offline_sync_queue`
--

DROP TABLE IF EXISTS `offline_sync_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `offline_sync_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_uuid` char(36) NOT NULL,
  `entity_type` varchar(40) NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `created_offline_at` datetime NOT NULL,
  `synced_at` datetime DEFAULT NULL,
  `status` enum('pending_review','approved','rejected') NOT NULL DEFAULT 'pending_review',
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reject_reason` varchar(255) DEFAULT NULL,
  `materialized_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_offline_sync_client_uuid` (`client_uuid`),
  KEY `idx_offline_sync_institute_status` (`institute_id`,`status`),
  KEY `idx_offline_sync_created_by` (`created_by`),
  CONSTRAINT `fk_offline_sync_created_by` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_offline_sync_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `offline_sync_queue`
--

LOCK TABLES `offline_sync_queue` WRITE;
/*!40000 ALTER TABLE `offline_sync_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `offline_sync_queue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `online_payment_attempts`
--

DROP TABLE IF EXISTS `online_payment_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `online_payment_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `gateway_id` bigint(20) unsigned NOT NULL,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `installment_id` bigint(20) unsigned DEFAULT NULL,
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(19,4) NOT NULL,
  `base_amount` decimal(19,4) DEFAULT NULL,
  `exchange_rate` decimal(19,8) DEFAULT NULL,
  `currency_code` varchar(10) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `gateway_reference` varchar(255) DEFAULT NULL,
  `idempotency_key` varchar(255) DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `initiated_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `online_payment_attempts_gateway_reference_unique` (`gateway_reference`),
  UNIQUE KEY `online_payment_attempts_idempotency_key_unique` (`idempotency_key`),
  KEY `online_payment_attempts_branch_id_foreign` (`branch_id`),
  KEY `online_payment_attempts_gateway_id_foreign` (`gateway_id`),
  KEY `online_payment_attempts_invoice_id_foreign` (`invoice_id`),
  KEY `online_payment_attempts_installment_id_foreign` (`installment_id`),
  KEY `online_payment_attempts_student_id_foreign` (`student_id`),
  KEY `online_payment_attempts_payment_id_foreign` (`payment_id`),
  KEY `online_payment_attempts_journal_id_foreign` (`journal_id`),
  KEY `online_payment_attempts_created_by_foreign` (`created_by`),
  KEY `online_payment_attempts_institute_id_invoice_id_status_index` (`institute_id`,`invoice_id`,`status`),
  KEY `online_payment_attempts_institute_id_student_id_status_index` (`institute_id`,`student_id`,`status`),
  CONSTRAINT `online_payment_attempts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `online_payment_attempts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `online_payment_attempts_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `payment_gateways` (`id`),
  CONSTRAINT `online_payment_attempts_installment_id_foreign` FOREIGN KEY (`installment_id`) REFERENCES `installments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `online_payment_attempts_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `online_payment_attempts_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `online_payment_attempts_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `online_payment_attempts_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `online_payment_attempts_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `online_payment_attempts`
--

LOCK TABLES `online_payment_attempts` WRITE;
/*!40000 ALTER TABLE `online_payment_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `online_payment_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opening_balances`
--

DROP TABLE IF EXISTS `opening_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `opening_balances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `fiscal_year_id` bigint(20) unsigned NOT NULL,
  `coa_id` bigint(20) unsigned NOT NULL,
  `debit` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `credit` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `source` enum('manual','carry_forward','migration') NOT NULL DEFAULT 'manual',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_opening_balances` (`institute_id`,`branch_id`,`fiscal_year_id`,`coa_id`),
  KEY `opening_balances_fiscal_year_id_foreign` (`fiscal_year_id`),
  KEY `opening_balances_coa_id_foreign` (`coa_id`),
  KEY `opening_balances_branch_id_foreign` (`branch_id`),
  CONSTRAINT `opening_balances_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opening_balances_coa_id_foreign` FOREIGN KEY (`coa_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `opening_balances_fiscal_year_id_foreign` FOREIGN KEY (`fiscal_year_id`) REFERENCES `fiscal_years` (`id`) ON DELETE CASCADE,
  CONSTRAINT `opening_balances_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opening_balances`
--

LOCK TABLES `opening_balances` WRITE;
/*!40000 ALTER TABLE `opening_balances` DISABLE KEYS */;
/*!40000 ALTER TABLE `opening_balances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `package_modules`
--

DROP TABLE IF EXISTS `package_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `package_modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint(20) unsigned NOT NULL,
  `module_key` varchar(60) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `package_modules_package_id_module_key_unique` (`package_id`,`module_key`),
  CONSTRAINT `package_modules_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `subscription_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `package_modules`
--

LOCK TABLES `package_modules` WRITE;
/*!40000 ALTER TABLE `package_modules` DISABLE KEYS */;
/*!40000 ALTER TABLE `package_modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parties`
--

DROP TABLE IF EXISTS `parties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parties` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('customer','supplier','both') NOT NULL DEFAULT 'customer',
  `customer_group_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `tin` varchar(50) DEFAULT NULL,
  `billing_currency_id` bigint(20) unsigned DEFAULT NULL,
  `credit_limit` decimal(19,4) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `party_meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`party_meta`)),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parties_phone` (`institute_id`,`branch_id`,`type`,`phone`),
  KEY `parties_branch_id_foreign` (`branch_id`),
  KEY `parties_billing_currency_id_foreign` (`billing_currency_id`),
  KEY `idx_parties_group` (`customer_group_id`),
  KEY `idx_parties_scope` (`institute_id`,`branch_id`,`type`),
  CONSTRAINT `parties_billing_currency_id_foreign` FOREIGN KEY (`billing_currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `parties_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `parties_customer_group_id_foreign` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `parties_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parties`
--

LOCK TABLES `parties` WRITE;
/*!40000 ALTER TABLE `parties` DISABLE KEYS */;
/*!40000 ALTER TABLE `parties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_gateways`
--

DROP TABLE IF EXISTS `payment_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_gateways` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `config_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config_schema`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_gateways_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_gateways`
--

LOCK TABLES `payment_gateways` WRITE;
/*!40000 ALTER TABLE `payment_gateways` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_gateways` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_methods`
--

DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_methods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `coa_id` bigint(20) unsigned DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_methods_name` (`institute_id`,`branch_id`,`name`),
  KEY `payment_methods_branch_id_foreign` (`branch_id`),
  KEY `payment_methods_coa_id_foreign` (`coa_id`),
  CONSTRAINT `payment_methods_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_methods_coa_id_foreign` FOREIGN KEY (`coa_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_methods_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_methods`
--

LOCK TABLES `payment_methods` WRITE;
/*!40000 ALTER TABLE `payment_methods` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `installment_id` bigint(20) unsigned DEFAULT NULL,
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `exchange_rate` decimal(19,8) DEFAULT NULL,
  `base_amount` decimal(19,4) DEFAULT NULL,
  `applied_amount` decimal(19,4) DEFAULT NULL,
  `payment_method` enum('cash','bkash','nagad','rocket','bank','card','online','other') NOT NULL DEFAULT 'cash',
  `payment_method_id` bigint(20) unsigned DEFAULT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `receipt_printed_at` timestamp NULL DEFAULT NULL,
  `paid_at` datetime NOT NULL DEFAULT current_timestamp(),
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payments_institute_receipt` (`institute_id`,`receipt_number`),
  KEY `idx_payments_institute` (`institute_id`),
  KEY `idx_payments_invoice` (`invoice_id`),
  KEY `idx_payments_student` (`student_id`),
  KEY `fk_payments_installment` (`installment_id`),
  KEY `fk_payments_received_by` (`received_by`),
  KEY `payments_payment_method_id_foreign` (`payment_method_id`),
  KEY `payments_journal_id_foreign` (`journal_id`),
  KEY `idx_payments_party` (`party_id`),
  KEY `payments_currency_id_foreign` (`currency_id`),
  CONSTRAINT `fk_payments_installment` FOREIGN KEY (`installment_id`) REFERENCES `installments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_received_by` FOREIGN KEY (`received_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pending_registrations`
--

DROP TABLE IF EXISTS `pending_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pending_registrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `otp_hash` varchar(255) DEFAULT NULL,
  `otp_expires_at` timestamp NULL DEFAULT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `resend_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `last_sent_at` timestamp NULL DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `organization_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`organization_data`)),
  `address_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`address_data`)),
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pending_registrations_email_unique` (`email`),
  KEY `pending_registrations_otp_expires_at_index` (`otp_expires_at`),
  KEY `pending_registrations_expires_at_index` (`expires_at`),
  KEY `pending_registrations_verified_at_index` (`verified_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pending_registrations`
--

LOCK TABLES `pending_registrations` WRITE;
/*!40000 ALTER TABLE `pending_registrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `pending_registrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(60) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=183 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'institutes','View Institutes','institutes.view','2026-08-05 02:14:57'),(2,'institutes','Manage Institutes','institutes.manage','2026-08-05 02:14:57'),(3,'branches','View Branches','branches.view','2026-08-05 02:14:57'),(4,'branches','Manage Branches','branches.manage','2026-08-05 02:14:57'),(5,'courses','View Courses','courses.view','2026-08-05 02:14:57'),(6,'courses','Manage Courses','courses.manage','2026-08-05 02:14:57'),(7,'batches','View Batches','batches.view','2026-08-05 02:14:57'),(8,'batches','Manage Batches','batches.manage','2026-08-05 02:14:57'),(9,'students','View Students','students.view','2026-08-05 02:14:57'),(10,'students','Manage Students','students.manage','2026-08-05 02:14:57'),(11,'attendance','Mark Attendance','attendance.manage','2026-08-05 02:14:57'),(12,'exams','Manage Exams','exams.manage','2026-08-05 02:14:57'),(13,'results','Publish Results','results.publish','2026-08-05 02:14:57'),(14,'certificates','Issue Certificates','certificates.manage','2026-08-05 02:14:57'),(15,'notices','Manage Notices','notices.manage','2026-08-05 02:14:57'),(16,'gallery','Manage Gallery','gallery.manage','2026-08-05 02:14:57'),(17,'finance','View Finance','finance.view','2026-08-05 02:14:57'),(18,'finance','Manage Finance','finance.manage','2026-08-05 02:14:57'),(19,'staff','Manage Staff','staff.manage','2026-08-05 02:14:57'),(20,'settings','Manage Settings','settings.manage','2026-08-05 02:14:57'),(21,'ai','AI Assistant','ai.assistant','2026-08-14 10:17:55'),(22,'ai','AI Analytics','ai.analytics','2026-08-14 10:17:55'),(23,'ai','AI Content','ai.content','2026-08-14 10:17:55'),(24,'ai','AI Reports','ai.reports','2026-08-14 10:17:55'),(25,'ai','AI Automation','ai.automation','2026-08-14 10:17:56'),(26,'education','Academic Structure','education.manage','2026-08-23 17:51:00'),(27,'education','Academic Promotion','promotion.manage','2026-08-23 17:51:07'),(28,'crm','CRM View','crm.view','2026-08-23 17:51:10'),(29,'crm','CRM Create','crm.create','2026-08-23 17:51:10'),(30,'crm','CRM Update','crm.update','2026-08-23 17:51:10'),(31,'crm','CRM Delete','crm.delete','2026-08-23 17:51:10'),(32,'crm','CRM Manage','crm.manage','2026-08-23 17:51:10'),(33,'accounting','Accounts View','accounts.view','2026-08-23 17:51:15'),(34,'accounting','Accounts Manage','accounts.manage','2026-08-23 17:51:15'),(35,'accounting','Journal Create','journals.create','2026-08-23 17:51:15'),(36,'accounting','Journal Post','journals.post','2026-08-23 17:51:15'),(37,'accounting','Journal Reverse','journals.reverse','2026-08-23 17:51:15'),(38,'accounting','Financial Reports View','reports.financial.view','2026-08-23 17:51:15'),(39,'accounting','Financial Reports Export','reports.financial.export','2026-08-23 17:51:15'),(40,'accounting','Accounting Settings Manage','settings.accounting.manage','2026-08-23 17:51:15'),(41,'accounting','FX Rates Manage','fx.rates.manage','2026-08-23 17:51:17'),(42,'accounting','FX Revaluation Run','fx.revaluation.run','2026-08-23 17:51:17'),(43,'budgeting','Budget View','budget.view','2026-08-23 11:51:20'),(44,'budgeting','Budget Create','budget.create','2026-08-23 11:51:20'),(45,'budgeting','Budget Edit','budget.edit','2026-08-23 11:51:20'),(46,'budgeting','Budget Submit','budget.submit','2026-08-23 11:51:20'),(47,'budgeting','Budget Approve','budget.approve','2026-08-23 11:51:20'),(48,'budgeting','Budget Lock','budget.lock','2026-08-23 11:51:20'),(49,'budgeting','Budget Revise','budget.revise','2026-08-23 11:51:20'),(50,'budgeting','Budget Report','budget.report','2026-08-23 11:51:20'),(51,'education','View Teachers','teacher.view','2026-08-23 17:51:21'),(52,'education','Create Teachers','teacher.create','2026-08-23 17:51:21'),(53,'education','Update Teachers','teacher.update','2026-08-23 17:51:21'),(54,'education','Delete Teachers','teacher.delete','2026-08-23 17:51:21'),(55,'education','Manage Teachers','teacher.manage','2026-08-23 17:51:21'),(56,'education','View Curricula','curriculum.view','2026-08-23 17:51:25'),(57,'education','Manage Curricula','curriculum.manage','2026-08-23 17:51:25'),(58,'documents','Documents View','documents.view','2026-08-23 17:51:50'),(59,'documents','Documents Manage','documents.manage','2026-08-23 17:51:50'),(60,'inventory','Inventory View','inventory.view','2026-08-23 17:51:57'),(61,'inventory','Inventory Create','inventory.create','2026-08-23 17:51:57'),(62,'inventory','Inventory Update','inventory.update','2026-08-23 17:51:57'),(63,'inventory','Inventory Adjust','inventory.adjust','2026-08-23 17:51:57'),(64,'inventory','Inventory Transfer','inventory.transfer','2026-08-23 17:51:57'),(65,'inventory','Inventory Count','inventory.count','2026-08-23 17:51:57'),(66,'inventory','Inventory Approve','inventory.approve','2026-08-23 17:51:57'),(67,'inventory','Inventory Post','inventory.post','2026-08-23 17:51:57'),(68,'inventory','Inventory Reports View','inventory.reports.view','2026-08-23 17:51:57'),(69,'alumni','Alumni View','alumni.view','2026-08-23 17:51:59'),(70,'alumni','Alumni Create','alumni.create','2026-08-23 17:51:59'),(71,'alumni','Alumni Update','alumni.update','2026-08-23 17:51:59'),(72,'alumni','Alumni Delete','alumni.delete','2026-08-23 17:51:59'),(73,'alumni','Alumni Manage','alumni.manage','2026-08-23 17:51:59'),(74,'asset','Asset View','asset.view','2026-08-23 17:52:04'),(75,'asset','Asset Create','asset.create','2026-08-23 17:52:04'),(76,'asset','Asset Update','asset.update','2026-08-23 17:52:04'),(77,'asset','Asset Capitalize','asset.capitalize','2026-08-23 17:52:04'),(78,'asset','Asset Transfer','asset.transfer','2026-08-23 17:52:04'),(79,'asset','Asset Depreciate','asset.depreciate','2026-08-23 17:52:04'),(80,'asset','Asset Dispose','asset.dispose','2026-08-23 17:52:04'),(81,'asset','Asset Impair','asset.impair','2026-08-23 17:52:04'),(82,'asset','Asset Revalue','asset.revalue','2026-08-23 17:52:04'),(83,'asset','Asset Approve','asset.approve','2026-08-23 17:52:04'),(84,'asset','Asset Post','asset.post','2026-08-23 17:52:04'),(85,'asset','Asset Reports View','asset.reports.view','2026-08-23 17:52:04'),(86,'asset','Asset QR View','asset.qr.view','2026-08-23 17:52:04'),(87,'asset','Asset QR Manage','asset.qr.manage','2026-08-23 17:52:04'),(88,'workflows','Workflows View','workflows.view','2026-08-23 17:52:04'),(89,'workflows','Workflows Manage','workflows.manage','2026-08-23 17:52:04'),(90,'tax','Tax View','tax.view','2026-08-23 17:52:05'),(91,'tax','Tax Manage','tax.manage','2026-08-23 17:52:05'),(92,'tax','Tax Rates','tax.rates','2026-08-23 17:52:05'),(93,'tax','Tax Returns','tax.returns','2026-08-23 17:52:05'),(94,'tax','Tax Reports','tax.reports','2026-08-23 17:52:05'),(95,'online_payments','View Online Payments','online_payments.view','2026-08-23 11:52:07'),(96,'online_payments','Manage Online Payments','online_payments.manage','2026-08-23 11:52:07'),(97,'hr','View Employees','hr.employee.view','2026-08-23 17:52:09'),(98,'hr','Create Employees','hr.employee.create','2026-08-23 17:52:09'),(99,'hr','Update Employees','hr.employee.update','2026-08-23 17:52:09'),(100,'hr','Delete Employees','hr.employee.delete','2026-08-23 17:52:09'),(101,'hr','Manage Employees','hr.employee.manage','2026-08-23 17:52:09'),(102,'hr','View Departments','hr.department.view','2026-08-23 17:52:09'),(103,'hr','Create Departments','hr.department.create','2026-08-23 17:52:09'),(104,'hr','Update Departments','hr.department.update','2026-08-23 17:52:09'),(105,'hr','Delete Departments','hr.department.delete','2026-08-23 17:52:09'),(106,'hr','View Designations','hr.designation.view','2026-08-23 17:52:09'),(107,'hr','Create Designations','hr.designation.create','2026-08-23 17:52:09'),(108,'hr','Update Designations','hr.designation.update','2026-08-23 17:52:09'),(109,'hr','Delete Designations','hr.designation.delete','2026-08-23 17:52:09'),(110,'hr','Manage HR','hr.manage','2026-08-23 17:52:09'),(111,'hr','View Employment History','hr.history.view','2026-08-23 17:52:10'),(112,'hr','Transfer Employees','hr.transfer','2026-08-23 17:52:10'),(113,'hr','Promote Employees','hr.promotion','2026-08-23 17:52:10'),(114,'hr','Manage Resignations','hr.resignation','2026-08-23 17:52:10'),(115,'hr','Terminate Employees','hr.termination','2026-08-23 17:52:10'),(116,'hr','Reactivate Employees','hr.reactivation','2026-08-23 17:52:10'),(117,'hr','HR Document View','hr.document.view','2026-08-23 17:52:10'),(118,'hr','HR Document Manage','hr.document.manage','2026-08-23 17:52:10'),(119,'hr','HR Document Verify','hr.document.verify','2026-08-23 17:52:10'),(120,'hr','View Attendance','hr.attendance.view','2026-08-23 17:52:13'),(121,'hr','Manage Attendance','hr.attendance.manage','2026-08-23 17:52:13'),(122,'hr','View Leave','hr.leave.view','2026-08-23 17:52:13'),(123,'hr','Create Leave','hr.leave.create','2026-08-23 17:52:13'),(124,'hr','Update Leave','hr.leave.update','2026-08-23 17:52:13'),(125,'hr','Manage Leave','hr.leave.manage','2026-08-23 17:52:13'),(126,'hr','Manage Leave Policies','hr.leave.policy.manage','2026-08-23 17:52:13'),(127,'hr','Approve Leave','hr.leave.approve','2026-08-23 17:52:13'),(128,'hr','Manage Holidays','hr.holiday.manage','2026-08-23 17:52:13'),(129,'hr','Manage Shifts','hr.shift.manage','2026-08-23 17:52:13'),(130,'hr','Salary View','hr.salary.view','2026-08-23 17:52:17'),(131,'hr','Salary Manage','hr.salary.manage','2026-08-23 17:52:17'),(132,'hr','Payroll View','hr.payroll.view','2026-08-23 17:52:17'),(133,'hr','Payroll Manage','hr.payroll.manage','2026-08-23 17:52:17'),(134,'hr','Payroll Approve','hr.payroll.approve','2026-08-23 17:52:17'),(135,'hr','Payroll Pay','hr.payroll.pay','2026-08-23 17:52:17'),(136,'hr','Payslip View Own','hr.payslip.own','2026-08-23 17:52:17'),(137,'hr','Recruitment View','hr.recruitment.view','2026-08-23 17:52:21'),(138,'hr','Recruitment Manage','hr.recruitment.manage','2026-08-23 17:52:21'),(139,'hr','Requisition View','hr.requisition.view','2026-08-23 17:52:21'),(140,'hr','Requisition Manage','hr.requisition.manage','2026-08-23 17:52:21'),(141,'hr','Requisition Approve','hr.requisition.approve','2026-08-23 17:52:21'),(142,'hr','Vacancy View','hr.vacancy.view','2026-08-23 17:52:21'),(143,'hr','Vacancy Manage','hr.vacancy.manage','2026-08-23 17:52:21'),(144,'hr','Application View','hr.application.view','2026-08-23 17:52:21'),(145,'hr','Application Manage','hr.application.manage','2026-08-23 17:52:21'),(146,'hr','Interview Manage','hr.interview.manage','2026-08-23 17:52:21'),(147,'hr','Offer Manage','hr.offer.manage','2026-08-23 17:52:21'),(148,'hr','Hiring Manage','hr.hiring.manage','2026-08-23 17:52:21'),(149,'hr','Performance View','hr.performance.view','2026-08-23 17:52:23'),(150,'hr','Performance Manage','hr.performance.manage','2026-08-23 17:52:23'),(151,'hr','Performance Review','hr.performance.review','2026-08-23 17:52:23'),(152,'hr','Performance Approve','hr.performance.approve','2026-08-23 17:52:23'),(153,'hr','KPI Manage','hr.kpi.manage','2026-08-23 17:52:23'),(154,'hr','Training View','hr.training.view','2026-08-23 17:52:23'),(155,'hr','Training Manage','hr.training.manage','2026-08-23 17:52:23'),(156,'hr','Training Enrollment','hr.training.enroll','2026-08-23 17:52:23'),(157,'hr','Skills Manage','hr.skills.manage','2026-08-23 17:52:23'),(158,'hr','Skills View','hr.skills.view','2026-08-23 17:52:23'),(159,'hr','Self Service View','hr.self.view','2026-08-23 17:52:24'),(160,'hr','Self Service Manage','hr.self.manage','2026-08-23 17:52:24'),(161,'hr','Team View','hr.team.view','2026-08-23 17:52:24'),(162,'hr','HR Dashboard View','hr.dashboard.view','2026-08-23 17:52:24'),(163,'hr','Workflow View','hr.workflow.view','2026-08-23 17:52:24'),(164,'hr','Workflow Manage','hr.workflow.manage','2026-08-23 17:52:24'),(165,'hr','HR Reports View','hr.reports.view','2026-08-23 17:52:24'),(166,'sales','View Sales','sales.view','2026-08-23 17:52:24'),(167,'sales','Create Sales','sales.create','2026-08-23 17:52:24'),(168,'sales','Update Sales','sales.update','2026-08-23 17:52:24'),(169,'sales','Delete Sales','sales.delete','2026-08-23 17:52:24'),(170,'sales','Manage Sales','sales.manage','2026-08-23 17:52:24'),(171,'purchase','View Purchase Orders','purchase_order.view','2026-08-23 11:52:25'),(172,'purchase','Create Purchase Orders','purchase_order.create','2026-08-23 11:52:25'),(173,'purchase','Update Purchase Orders','purchase_order.update','2026-08-23 11:52:25'),(174,'purchase','Delete Purchase Orders','purchase_order.delete','2026-08-23 11:52:25'),(175,'purchase','Approve Purchase Orders','purchase_order.approve','2026-08-23 11:52:25'),(176,'purchase','View Goods Receipts','goods_receipt.view','2026-08-23 11:52:25'),(177,'purchase','Create Goods Receipts','goods_receipt.create','2026-08-23 11:52:25'),(178,'purchase','Confirm Goods Receipts','goods_receipt.confirm','2026-08-23 11:52:25'),(179,'purchase','Cancel Goods Receipts','goods_receipt.cancel','2026-08-23 11:52:25'),(180,'purchase','Reverse Goods Receipts','goods_receipt.reverse','2026-08-23 11:54:51'),(181,'hr','View Attendance','attendance.view','2026-08-23 11:54:51'),(182,'education','Admission Approve','admission.approve','2026-08-28 15:54:39');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `phone_2fa_otps`
--

DROP TABLE IF EXISTS `phone_2fa_otps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `phone_2fa_otps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `guard` varchar(20) NOT NULL DEFAULT 'web',
  `user_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `consumed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `phone_2fa_otps_guard_user_id_phone_index` (`guard`,`user_id`,`phone`),
  KEY `phone_2fa_otps_institute_id_index` (`institute_id`),
  KEY `phone_2fa_otps_expires_at_index` (`expires_at`),
  CONSTRAINT `phone_2fa_otps_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `phone_2fa_otps`
--

LOCK TABLES `phone_2fa_otps` WRITE;
/*!40000 ALTER TABLE `phone_2fa_otps` DISABLE KEYS */;
/*!40000 ALTER TABLE `phone_2fa_otps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `phone_password_reset_otps`
--

DROP TABLE IF EXISTS `phone_password_reset_otps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `phone_password_reset_otps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL,
  `consumed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `phone_password_reset_otps_user_id_foreign` (`user_id`),
  KEY `phone_password_reset_otps_phone_expires_at_index` (`phone`,`expires_at`),
  KEY `phone_password_reset_otps_phone_index` (`phone`),
  KEY `phone_password_reset_otps_expires_at_index` (`expires_at`),
  CONSTRAINT `phone_password_reset_otps_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `phone_password_reset_otps`
--

LOCK TABLES `phone_password_reset_otps` WRITE;
/*!40000 ALTER TABLE `phone_password_reset_otps` DISABLE KEYS */;
/*!40000 ALTER TABLE `phone_password_reset_otps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `phone_verification_otps`
--

DROP TABLE IF EXISTS `phone_verification_otps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `phone_verification_otps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `phone` varchar(20) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `consumed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `phone_verification_otps_user_id_phone_index` (`user_id`,`phone`),
  KEY `phone_verification_otps_expires_at_index` (`expires_at`),
  CONSTRAINT `phone_verification_otps_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `phone_verification_otps`
--

LOCK TABLES `phone_verification_otps` WRITE;
/*!40000 ALTER TABLE `phone_verification_otps` DISABLE KEYS */;
/*!40000 ALTER TABLE `phone_verification_otps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `platform_admins`
--

DROP TABLE IF EXISTS `platform_admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `singleton_guard` tinyint(4) NOT NULL DEFAULT 1,
  `first_name` varchar(60) NOT NULL DEFAULT '',
  `last_name` varchar(60) NOT NULL DEFAULT '',
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `email` varchar(150) NOT NULL,
  `preferred_language` varchar(10) NOT NULL DEFAULT 'en',
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `theme` varchar(60) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `failed_login_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `preferred_2fa_method` varchar(20) DEFAULT NULL,
  `sms_2fa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `email_2fa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `is_owner` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `inactivity_warning_sent_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `full_name` varchar(121) GENERATED ALWAYS AS (trim(concat(`first_name`,' ',`last_name`))) STORED,
  `name` varchar(121) GENERATED ALWAYS AS (trim(concat(`first_name`,' ',`last_name`))) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_platform_admins_email` (`email`),
  UNIQUE KEY `uq_platform_admins_uuid` (`uuid`),
  UNIQUE KEY `uq_platform_admins_singleton` (`singleton_guard`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `platform_admins`
--

LOCK TABLES `platform_admins` WRITE;
/*!40000 ALTER TABLE `platform_admins` DISABLE KEYS */;
/*!40000 ALTER TABLE `platform_admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `platform_audit_logs`
--

DROP TABLE IF EXISTS `platform_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint(20) unsigned DEFAULT NULL,
  `section` varchar(50) NOT NULL,
  `setting_key` varchar(150) NOT NULL,
  `action` varchar(30) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `platform_audit_logs_section_created_at_index` (`section`,`created_at`),
  KEY `platform_audit_logs_admin_id_created_at_index` (`admin_id`,`created_at`),
  CONSTRAINT `platform_audit_logs_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `platform_audit_logs`
--

LOCK TABLES `platform_audit_logs` WRITE;
/*!40000 ALTER TABLE `platform_audit_logs` DISABLE KEYS */;
INSERT INTO `platform_audit_logs` VALUES (58,NULL,'test_data','batch','test_cleanup_executed','127.0.0.1','Symfony','{\"actor_id\":0,\"actor_type\":\"system\",\"target_type\":\"test_data\",\"target_id\":\"batch\",\"action\":\"test_cleanup_executed\",\"reason\":\"Explicit is_test=true cleanup\",\"environment\":\"testing\",\"database\":\"monetix_test\",\"timestamp\":\"2026-09-01T14:32:40+06:00\",\"backup_reference\":\"monetix_manual_20260901_143235.sql\",\"before_state\":{\"counts\":{\"users\":0,\"users_protected\":2,\"users_unknown_null\":0,\"institutes\":0,\"institutes_protected\":82,\"institutes_unknown_null\":0,\"institution_user\":0,\"institution_user_protected\":0,\"institution_user_unknown_null\":0,\"students\":0,\"students_protected\":3,\"students_unknown_null\":0,\"courses\":0,\"courses_protected\":0,\"courses_unknown_null\":0,\"batches\":0,\"batches_protected\":0,\"batches_unknown_null\":0},\"email_pattern_counts_blocked\":{\"users_email_like_test\":1,\"users_email_like_example\":1},\"note\":\"Only is_test=true records are eligible for deletion. Email patterns are BLOCKED.\"},\"after_state\":{\"institution_user\":0,\"students\":0,\"courses\":0,\"batches\":0,\"institutes\":0,\"users\":0}}','2026-09-01 08:32:40','2026-09-01 08:32:40'),(59,NULL,'test_data','batch','test_cleanup_executed','127.0.0.1','Symfony','{\"actor_id\":0,\"actor_type\":\"system\",\"target_type\":\"test_data\",\"target_id\":\"batch\",\"action\":\"test_cleanup_executed\",\"reason\":\"Explicit is_test=true cleanup\",\"environment\":\"testing\",\"database\":\"monetix_test\",\"timestamp\":\"2026-09-01T14:32:47+06:00\",\"backup_reference\":\"monetix_manual_20260901_143241.sql\",\"before_state\":{\"counts\":{\"users\":1,\"users_protected\":1,\"users_unknown_null\":0,\"institutes\":0,\"institutes_protected\":82,\"institutes_unknown_null\":0,\"institution_user\":0,\"institution_user_protected\":0,\"institution_user_unknown_null\":0,\"students\":0,\"students_protected\":3,\"students_unknown_null\":0,\"courses\":0,\"courses_protected\":0,\"courses_unknown_null\":0,\"batches\":0,\"batches_protected\":0,\"batches_unknown_null\":0},\"email_pattern_counts_blocked\":{\"users_email_like_test\":2,\"users_email_like_example\":2},\"note\":\"Only is_test=true records are eligible for deletion. Email patterns are BLOCKED.\"},\"after_state\":{\"institution_user\":0,\"students\":0,\"courses\":0,\"batches\":0,\"institutes\":0,\"users\":1}}','2026-09-01 08:32:47','2026-09-01 08:32:47'),(60,NULL,'test_data','batch','test_cleanup_executed','127.0.0.1','Symfony','{\"actor_id\":0,\"actor_type\":\"system\",\"target_type\":\"test_data\",\"target_id\":\"batch\",\"action\":\"test_cleanup_executed\",\"reason\":\"Explicit is_test=true cleanup\",\"environment\":\"testing\",\"database\":\"monetix_test\",\"timestamp\":\"2026-09-01T14:32:53+06:00\",\"backup_reference\":\"monetix_manual_20260901_143247.sql\",\"before_state\":{\"counts\":{\"users\":0,\"users_protected\":2,\"users_unknown_null\":1,\"institutes\":0,\"institutes_protected\":82,\"institutes_unknown_null\":0,\"institution_user\":0,\"institution_user_protected\":0,\"institution_user_unknown_null\":0,\"students\":0,\"students_protected\":3,\"students_unknown_null\":0,\"courses\":0,\"courses_protected\":0,\"courses_unknown_null\":0,\"batches\":0,\"batches_protected\":0,\"batches_unknown_null\":0},\"email_pattern_counts_blocked\":{\"users_email_like_test\":1,\"users_email_like_example\":1},\"note\":\"Only is_test=true records are eligible for deletion. Email patterns are BLOCKED.\"},\"after_state\":{\"institution_user\":0,\"students\":0,\"courses\":0,\"batches\":0,\"institutes\":0,\"users\":0}}','2026-09-01 08:32:53','2026-09-01 08:32:53');
/*!40000 ALTER TABLE `platform_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `platform_service_configs`
--

DROP TABLE IF EXISTS `platform_service_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_service_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `service` varchar(50) NOT NULL COMMENT 'email, sms, payment, storage, maps, ai, queue etc',
  `provider` varchar(50) DEFAULT NULL,
  `key` varchar(100) NOT NULL COMMENT 'config key within service',
  `value` text DEFAULT NULL,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT 0,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform_service_configs_service_provider_key_unique` (`service`,`provider`,`key`),
  KEY `platform_service_configs_service_is_enabled_index` (`service`,`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `platform_service_configs`
--

LOCK TABLES `platform_service_configs` WRITE;
/*!40000 ALTER TABLE `platform_service_configs` DISABLE KEYS */;
/*!40000 ALTER TABLE `platform_service_configs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `platform_staff_permissions`
--

DROP TABLE IF EXISTS `platform_staff_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_staff_permissions` (
  `platform_staff_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`platform_staff_id`,`permission_id`),
  KEY `platform_staff_permissions_permission_id_foreign` (`permission_id`),
  CONSTRAINT `platform_staff_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `platform_staff_permissions_platform_staff_id_foreign` FOREIGN KEY (`platform_staff_id`) REFERENCES `platform_staffs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `platform_staff_permissions`
--

LOCK TABLES `platform_staff_permissions` WRITE;
/*!40000 ALTER TABLE `platform_staff_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `platform_staff_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `platform_staffs`
--

DROP TABLE IF EXISTS `platform_staffs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_staffs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `first_name` varchar(60) NOT NULL DEFAULT '',
  `last_name` varchar(60) NOT NULL DEFAULT '',
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `preferred_language` varchar(10) NOT NULL DEFAULT 'en',
  `preferences` longtext DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'support',
  `status` enum('active','suspended','inactive') NOT NULL DEFAULT 'active',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `failed_login_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_platform_staffs_uuid` (`uuid`),
  UNIQUE KEY `uq_platform_staffs_email` (`email`),
  KEY `platform_staffs_role_index` (`role`),
  KEY `platform_staffs_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `platform_staffs`
--

LOCK TABLES `platform_staffs` WRITE;
/*!40000 ALTER TABLE `platform_staffs` DISABLE KEYS */;
/*!40000 ALTER TABLE `platform_staffs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotion_decision_items`
--

DROP TABLE IF EXISTS `promotion_decision_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promotion_decision_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `decision_id` bigint(20) unsigned NOT NULL,
  `placement_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `decision` varchar(30) NOT NULL DEFAULT 'pending',
  `reasons` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`reasons`)),
  `target_class_grade_id` bigint(20) unsigned DEFAULT NULL,
  `target_academic_group_id` bigint(20) unsigned DEFAULT NULL,
  `next_placement_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pdi_decision_placement_unique` (`decision_id`,`placement_id`),
  KEY `promotion_decision_items_student_id_foreign` (`student_id`),
  KEY `promotion_decision_items_target_class_grade_id_foreign` (`target_class_grade_id`),
  KEY `promotion_decision_items_target_academic_group_id_foreign` (`target_academic_group_id`),
  KEY `promotion_decision_items_next_placement_id_foreign` (`next_placement_id`),
  KEY `promotion_decision_items_reviewed_by_foreign` (`reviewed_by`),
  KEY `promotion_decision_items_approved_by_foreign` (`approved_by`),
  KEY `pdi_decision_verdict_idx` (`decision_id`,`decision`),
  KEY `pdi_placement_idx` (`placement_id`),
  CONSTRAINT `promotion_decision_items_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_decision_items_decision_id_foreign` FOREIGN KEY (`decision_id`) REFERENCES `promotion_decisions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_decision_items_next_placement_id_foreign` FOREIGN KEY (`next_placement_id`) REFERENCES `student_academic_placements` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_decision_items_placement_id_foreign` FOREIGN KEY (`placement_id`) REFERENCES `student_academic_placements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_decision_items_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_decision_items_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_decision_items_target_academic_group_id_foreign` FOREIGN KEY (`target_academic_group_id`) REFERENCES `academic_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_decision_items_target_class_grade_id_foreign` FOREIGN KEY (`target_class_grade_id`) REFERENCES `class_grades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotion_decision_items`
--

LOCK TABLES `promotion_decision_items` WRITE;
/*!40000 ALTER TABLE `promotion_decision_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_decision_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotion_decisions`
--

DROP TABLE IF EXISTS `promotion_decisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promotion_decisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `policy_id` bigint(20) unsigned NOT NULL,
  `result_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `academic_year_id` bigint(20) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `promotion_decisions_branch_id_foreign` (`branch_id`),
  KEY `promotion_decisions_academic_year_id_foreign` (`academic_year_id`),
  KEY `promotion_decisions_reviewed_by_foreign` (`reviewed_by`),
  KEY `promotion_decisions_approved_by_foreign` (`approved_by`),
  KEY `promotion_decisions_created_by_foreign` (`created_by`),
  KEY `pd_policy_status_idx` (`policy_id`,`status`),
  KEY `pd_result_status_idx` (`result_id`,`status`),
  KEY `pd_institute_idx` (`institute_id`),
  KEY `promotion_decisions_cancelled_by_foreign` (`cancelled_by`),
  CONSTRAINT `promotion_decisions_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_decisions_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_decisions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_decisions_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_decisions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_decisions_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_decisions_policy_id_foreign` FOREIGN KEY (`policy_id`) REFERENCES `promotion_policies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_decisions_result_id_foreign` FOREIGN KEY (`result_id`) REFERENCES `academic_final_results` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_decisions_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotion_decisions`
--

LOCK TABLES `promotion_decisions` WRITE;
/*!40000 ALTER TABLE `promotion_decisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_decisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotion_policies`
--

DROP TABLE IF EXISTS `promotion_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promotion_policies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `academic_year_id` bigint(20) unsigned NOT NULL,
  `class_grade_id` bigint(20) unsigned NOT NULL,
  `academic_group_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `promotion_policies_branch_id_foreign` (`branch_id`),
  KEY `promotion_policies_class_grade_id_foreign` (`class_grade_id`),
  KEY `promotion_policies_academic_group_id_foreign` (`academic_group_id`),
  KEY `promotion_policies_created_by_foreign` (`created_by`),
  KEY `pp_institute_status_idx` (`institute_id`,`status`),
  KEY `pp_year_class_idx` (`academic_year_id`,`class_grade_id`),
  CONSTRAINT `promotion_policies_academic_group_id_foreign` FOREIGN KEY (`academic_group_id`) REFERENCES `academic_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_policies_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_policies_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_policies_class_grade_id_foreign` FOREIGN KEY (`class_grade_id`) REFERENCES `class_grades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_policies_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_policies_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotion_policies`
--

LOCK TABLES `promotion_policies` WRITE;
/*!40000 ALTER TABLE `promotion_policies` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotion_policy_rules`
--

DROP TABLE IF EXISTS `promotion_policy_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promotion_policy_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `policy_id` bigint(20) unsigned NOT NULL,
  `rule_type` varchar(30) NOT NULL,
  `field` varchar(40) DEFAULT NULL,
  `operator` varchar(10) DEFAULT NULL,
  `value` varchar(20) DEFAULT NULL,
  `pass_action` varchar(30) NOT NULL DEFAULT 'promoted',
  `fail_action` varchar(30) NOT NULL DEFAULT 'repeat',
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ppr_policy_order_idx` (`policy_id`,`display_order`),
  CONSTRAINT `promotion_policy_rules_policy_id_foreign` FOREIGN KEY (`policy_id`) REFERENCES `promotion_policies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotion_policy_rules`
--

LOCK TABLES `promotion_policy_rules` WRITE;
/*!40000 ALTER TABLE `promotion_policy_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_policy_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_invoice_items`
--

DROP TABLE IF EXISTS `purchase_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_invoice_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `purchase_invoice_id` bigint(20) unsigned NOT NULL,
  `purchase_order_line_id` bigint(20) unsigned DEFAULT NULL,
  `goods_receipt_item_id` bigint(20) unsigned DEFAULT NULL,
  `inventory_item_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `quantity` decimal(19,4) NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `unit_price` decimal(19,4) NOT NULL,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_type` varchar(20) NOT NULL DEFAULT 'fixed',
  `tax_group_id` bigint(20) unsigned DEFAULT NULL,
  `tax_rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(19,4) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_invoice_items_institute_id_foreign` (`institute_id`),
  KEY `purchase_invoice_items_purchase_order_line_id_foreign` (`purchase_order_line_id`),
  KEY `purchase_invoice_items_goods_receipt_item_id_foreign` (`goods_receipt_item_id`),
  KEY `purchase_invoice_items_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `purchase_invoice_items_tax_group_id_foreign` (`tax_group_id`),
  KEY `idx_pii_order` (`purchase_invoice_id`,`sort_order`),
  CONSTRAINT `purchase_invoice_items_goods_receipt_item_id_foreign` FOREIGN KEY (`goods_receipt_item_id`) REFERENCES `goods_receipt_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_invoice_items_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_invoice_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_invoice_items_purchase_invoice_id_foreign` FOREIGN KEY (`purchase_invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_invoice_items_purchase_order_line_id_foreign` FOREIGN KEY (`purchase_order_line_id`) REFERENCES `purchase_order_lines` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_invoice_items_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=185 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_invoice_items`
--

LOCK TABLES `purchase_invoice_items` WRITE;
/*!40000 ALTER TABLE `purchase_invoice_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_invoices`
--

DROP TABLE IF EXISTS `purchase_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_number` varchar(40) NOT NULL,
  `purchase_order_id` bigint(20) unsigned DEFAULT NULL,
  `goods_receipt_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `currency_id` bigint(20) unsigned NOT NULL,
  `payment_terms` varchar(40) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `subtotal` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_type` varchar(20) NOT NULL DEFAULT 'fixed',
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `grand_total` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `paid_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `due_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchase_invoices_number` (`institute_id`,`invoice_number`),
  KEY `purchase_invoices_branch_id_foreign` (`branch_id`),
  KEY `purchase_invoices_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `purchase_invoices_goods_receipt_id_foreign` (`goods_receipt_id`),
  KEY `purchase_invoices_supplier_id_foreign` (`supplier_id`),
  KEY `purchase_invoices_currency_id_foreign` (`currency_id`),
  KEY `purchase_invoices_journal_id_foreign` (`journal_id`),
  KEY `idx_pi_scope_status` (`institute_id`,`branch_id`,`status`),
  KEY `idx_pi_supplier` (`institute_id`,`supplier_id`),
  KEY `idx_pi_po` (`institute_id`,`purchase_order_id`),
  CONSTRAINT `purchase_invoices_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_invoices_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `purchase_invoices_goods_receipt_id_foreign` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_invoices_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_invoices_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_invoices_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_invoices_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `parties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=185 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_invoices`
--

LOCK TABLES `purchase_invoices` WRITE;
/*!40000 ALTER TABLE `purchase_invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_order_lines`
--

DROP TABLE IF EXISTS `purchase_order_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `inventory_item_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `quantity` decimal(19,4) NOT NULL,
  `received_quantity` decimal(19,4) DEFAULT 0.0000,
  `rejected_quantity` decimal(19,4) DEFAULT 0.0000,
  `unit` varchar(30) DEFAULT NULL,
  `unit_price` decimal(19,4) NOT NULL,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_type` varchar(20) NOT NULL DEFAULT 'fixed',
  `discount_rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `tax_group_id` bigint(20) unsigned DEFAULT NULL,
  `tax_rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(19,4) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_order_lines_institute_id_foreign` (`institute_id`),
  KEY `purchase_order_lines_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `purchase_order_lines_tax_group_id_foreign` (`tax_group_id`),
  KEY `idx_po_lines_order` (`order_id`,`sort_order`),
  CONSTRAINT `purchase_order_lines_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_order_lines_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_order_lines_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_order_lines_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=473 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order_lines`
--

LOCK TABLES `purchase_order_lines` WRITE;
/*!40000 ALTER TABLE `purchase_order_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_order_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `order_number` varchar(40) NOT NULL,
  `reference_number` varchar(80) DEFAULT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `warehouse_id` bigint(20) unsigned DEFAULT NULL,
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `subtotal` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_type` varchar(20) NOT NULL DEFAULT 'fixed',
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `grand_total` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchase_orders_number` (`institute_id`,`order_number`),
  KEY `purchase_orders_branch_id_foreign` (`branch_id`),
  KEY `purchase_orders_supplier_id_foreign` (`supplier_id`),
  KEY `purchase_orders_warehouse_id_foreign` (`warehouse_id`),
  KEY `purchase_orders_currency_id_foreign` (`currency_id`),
  KEY `idx_po_scope_status` (`institute_id`,`branch_id`,`status`),
  KEY `idx_po_supplier` (`institute_id`,`supplier_id`),
  KEY `idx_po_date` (`institute_id`,`order_date`),
  KEY `idx_po_warehouse` (`institute_id`,`warehouse_id`),
  CONSTRAINT `purchase_orders_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_orders_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `parties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_orders_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `inventory_warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=417 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_orders`
--

LOCK TABLES `purchase_orders` WRITE;
/*!40000 ALTER TABLE `purchase_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_quotation_lines`
--

DROP TABLE IF EXISTS `purchase_quotation_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_quotation_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `quotation_id` bigint(20) unsigned NOT NULL,
  `inventory_item_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `quantity` decimal(19,4) NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `unit_price` decimal(19,4) NOT NULL,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_type` varchar(20) NOT NULL DEFAULT 'fixed',
  `tax_group_id` bigint(20) unsigned DEFAULT NULL,
  `tax_rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(19,4) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_quotation_lines_institute_id_foreign` (`institute_id`),
  KEY `purchase_quotation_lines_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `purchase_quotation_lines_tax_group_id_foreign` (`tax_group_id`),
  KEY `idx_pq_lines_order` (`quotation_id`,`sort_order`),
  CONSTRAINT `purchase_quotation_lines_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_quotation_lines_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_quotation_lines_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `purchase_quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_quotation_lines_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_quotation_lines`
--

LOCK TABLES `purchase_quotation_lines` WRITE;
/*!40000 ALTER TABLE `purchase_quotation_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_quotation_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_quotations`
--

DROP TABLE IF EXISTS `purchase_quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_quotations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `quotation_number` varchar(40) NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `quotation_date` date NOT NULL,
  `validity_date` date DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `subtotal` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_type` varchar(20) NOT NULL DEFAULT 'fixed',
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `grand_total` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `converted_to_order_id` bigint(20) unsigned DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pq_number` (`institute_id`,`quotation_number`),
  KEY `purchase_quotations_branch_id_foreign` (`branch_id`),
  KEY `purchase_quotations_supplier_id_foreign` (`supplier_id`),
  KEY `purchase_quotations_currency_id_foreign` (`currency_id`),
  KEY `purchase_quotations_converted_to_order_id_foreign` (`converted_to_order_id`),
  KEY `idx_pq_scope_status` (`institute_id`,`branch_id`,`status`),
  KEY `idx_pq_supplier` (`institute_id`,`supplier_id`),
  KEY `idx_pq_date` (`institute_id`,`quotation_date`),
  CONSTRAINT `purchase_quotations_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_quotations_converted_to_order_id_foreign` FOREIGN KEY (`converted_to_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_quotations_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_quotations_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_quotations_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `parties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_quotations`
--

LOCK TABLES `purchase_quotations` WRITE;
/*!40000 ALTER TABLE `purchase_quotations` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_quotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_request_items`
--

DROP TABLE IF EXISTS `purchase_request_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_request_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `purchase_request_id` bigint(20) unsigned NOT NULL,
  `inventory_item_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `quantity` decimal(19,4) NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `estimated_unit_price` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_request_items_institute_id_foreign` (`institute_id`),
  KEY `purchase_request_items_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `idx_pr_lines_request` (`purchase_request_id`,`sort_order`),
  CONSTRAINT `purchase_request_items_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_request_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_request_items_purchase_request_id_foreign` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_request_items`
--

LOCK TABLES `purchase_request_items` WRITE;
/*!40000 ALTER TABLE `purchase_request_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_request_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_requests`
--

DROP TABLE IF EXISTS `purchase_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `request_number` varchar(40) NOT NULL,
  `requester_id` bigint(20) unsigned NOT NULL,
  `request_date` date NOT NULL,
  `required_by_date` date DEFAULT NULL,
  `warehouse_id` bigint(20) unsigned DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `justification` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `estimated_total` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `converted_by` bigint(20) unsigned DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `converted_order_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchase_requests_number` (`institute_id`,`request_number`),
  KEY `purchase_requests_branch_id_foreign` (`branch_id`),
  KEY `purchase_requests_requester_id_foreign` (`requester_id`),
  KEY `purchase_requests_warehouse_id_foreign` (`warehouse_id`),
  KEY `purchase_requests_currency_id_foreign` (`currency_id`),
  KEY `purchase_requests_converted_order_id_foreign` (`converted_order_id`),
  KEY `idx_pr_scope_status` (`institute_id`,`branch_id`,`status`),
  KEY `idx_pr_requester` (`institute_id`,`requester_id`),
  KEY `idx_pr_date` (`institute_id`,`request_date`),
  CONSTRAINT `purchase_requests_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_converted_order_id_foreign` FOREIGN KEY (`converted_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_requests_requester_id_foreign` FOREIGN KEY (`requester_id`) REFERENCES `institute_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_requests_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `inventory_warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_requests`
--

LOCK TABLES `purchase_requests` WRITE;
/*!40000 ALTER TABLE `purchase_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_return_items`
--

DROP TABLE IF EXISTS `purchase_return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_return_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `purchase_return_id` bigint(20) unsigned NOT NULL,
  `purchase_order_line_id` bigint(20) unsigned DEFAULT NULL,
  `goods_receipt_item_id` bigint(20) unsigned DEFAULT NULL,
  `inventory_item_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `quantity` decimal(19,4) NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `unit_price` decimal(19,4) NOT NULL,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_type` varchar(20) NOT NULL DEFAULT 'fixed',
  `tax_rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(19,4) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_return_items_institute_id_foreign` (`institute_id`),
  KEY `purchase_return_items_purchase_order_line_id_foreign` (`purchase_order_line_id`),
  KEY `purchase_return_items_goods_receipt_item_id_foreign` (`goods_receipt_item_id`),
  KEY `purchase_return_items_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `idx_pri_order` (`purchase_return_id`,`sort_order`),
  CONSTRAINT `purchase_return_items_goods_receipt_item_id_foreign` FOREIGN KEY (`goods_receipt_item_id`) REFERENCES `goods_receipt_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_return_items_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_return_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_return_items_purchase_order_line_id_foreign` FOREIGN KEY (`purchase_order_line_id`) REFERENCES `purchase_order_lines` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_return_items_purchase_return_id_foreign` FOREIGN KEY (`purchase_return_id`) REFERENCES `purchase_returns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_return_items`
--

LOCK TABLES `purchase_return_items` WRITE;
/*!40000 ALTER TABLE `purchase_return_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_return_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_returns`
--

DROP TABLE IF EXISTS `purchase_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_returns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `return_number` varchar(40) NOT NULL,
  `credit_note_number` varchar(40) DEFAULT NULL,
  `purchase_order_id` bigint(20) unsigned DEFAULT NULL,
  `goods_receipt_id` bigint(20) unsigned DEFAULT NULL,
  `purchase_invoice_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `warehouse_id` bigint(20) unsigned DEFAULT NULL,
  `return_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `subtotal` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `grand_total` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchase_returns_number` (`institute_id`,`return_number`),
  UNIQUE KEY `uq_purchase_returns_credit` (`institute_id`,`credit_note_number`),
  KEY `purchase_returns_branch_id_foreign` (`branch_id`),
  KEY `purchase_returns_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `purchase_returns_goods_receipt_id_foreign` (`goods_receipt_id`),
  KEY `purchase_returns_purchase_invoice_id_foreign` (`purchase_invoice_id`),
  KEY `purchase_returns_supplier_id_foreign` (`supplier_id`),
  KEY `purchase_returns_warehouse_id_foreign` (`warehouse_id`),
  KEY `purchase_returns_journal_id_foreign` (`journal_id`),
  KEY `idx_pr_scope_status` (`institute_id`,`branch_id`,`status`),
  KEY `idx_pr_supplier` (`institute_id`,`supplier_id`),
  CONSTRAINT `purchase_returns_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_returns_goods_receipt_id_foreign` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_returns_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_returns_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_returns_purchase_invoice_id_foreign` FOREIGN KEY (`purchase_invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_returns_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_returns_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `parties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_returns_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `inventory_warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_returns`
--

LOCK TABLES `purchase_returns` WRITE;
/*!40000 ALTER TABLE `purchase_returns` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_sequences`
--

DROP TABLE IF EXISTS `purchase_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_sequences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `document_type` enum('invoice','quotation','order','return','receipt') NOT NULL DEFAULT 'invoice',
  `prefix` varchar(20) NOT NULL DEFAULT '',
  `next_number` int(10) unsigned NOT NULL DEFAULT 1,
  `padding` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchase_sequences_inst_branch_type` (`institute_id`,`branch_id`,`document_type`),
  KEY `purchase_sequences_branch_id_foreign` (`branch_id`),
  CONSTRAINT `purchase_sequences_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_sequences_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=754 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_sequences`
--

LOCK TABLES `purchase_sequences` WRITE;
/*!40000 ALTER TABLE `purchase_sequences` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_supplier_payments`
--

DROP TABLE IF EXISTS `purchase_supplier_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_supplier_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `purchase_invoice_id` bigint(20) unsigned NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  `payment_method` varchar(20) NOT NULL DEFAULT 'cash',
  `payment_method_id` bigint(20) unsigned DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_supplier_payments_branch_id_foreign` (`branch_id`),
  KEY `purchase_supplier_payments_purchase_invoice_id_foreign` (`purchase_invoice_id`),
  KEY `purchase_supplier_payments_supplier_id_foreign` (`supplier_id`),
  KEY `purchase_supplier_payments_payment_method_id_foreign` (`payment_method_id`),
  KEY `purchase_supplier_payments_journal_id_foreign` (`journal_id`),
  KEY `idx_psp_invoice` (`institute_id`,`purchase_invoice_id`),
  KEY `idx_psp_supplier` (`institute_id`,`supplier_id`),
  CONSTRAINT `purchase_supplier_payments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_supplier_payments_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_supplier_payments_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_supplier_payments_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_supplier_payments_purchase_invoice_id_foreign` FOREIGN KEY (`purchase_invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_supplier_payments_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `parties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_supplier_payments`
--

LOCK TABLES `purchase_supplier_payments` WRITE;
/*!40000 ALTER TABLE `purchase_supplier_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_supplier_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `query_fingerprints`
--

DROP TABLE IF EXISTS `query_fingerprints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `query_fingerprints` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fingerprint` varchar(64) NOT NULL,
  `normalized_query` text NOT NULL,
  `execution_count` int(10) unsigned NOT NULL DEFAULT 0,
  `total_duration` decimal(12,2) NOT NULL DEFAULT 0.00,
  `average_duration` decimal(10,2) NOT NULL DEFAULT 0.00,
  `maximum_duration` decimal(10,2) NOT NULL DEFAULT 0.00,
  `first_seen` timestamp NULL DEFAULT NULL,
  `last_seen` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `query_fingerprints_fingerprint_unique` (`fingerprint`),
  KEY `query_fingerprints_execution_count_index` (`execution_count`),
  KEY `query_fingerprints_average_duration_index` (`average_duration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `query_fingerprints`
--

LOCK TABLES `query_fingerprints` WRITE;
/*!40000 ALTER TABLE `query_fingerprints` DISABLE KEYS */;
/*!40000 ALTER TABLE `query_fingerprints` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reg_no_sequence`
--

DROP TABLE IF EXISTS `reg_no_sequence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reg_no_sequence` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `year_code` char(2) DEFAULT NULL,
  `zip_code` char(4) DEFAULT NULL,
  `trade_code` char(3) DEFAULT NULL,
  `last_sequence` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reg_no_sequence_combo` (`year_code`,`zip_code`,`trade_code`)
) ENGINE=InnoDB AUTO_INCREMENT=305 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reg_no_sequence`
--

LOCK TABLES `reg_no_sequence` WRITE;
/*!40000 ALTER TABLE `reg_no_sequence` DISABLE KEYS */;
/*!40000 ALTER TABLE `reg_no_sequence` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `results`
--

DROP TABLE IF EXISTS `results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `total_marks` decimal(7,2) NOT NULL DEFAULT 0.00,
  `obtained_marks` decimal(7,2) NOT NULL DEFAULT 0.00,
  `percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `grade` varchar(10) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL,
  `result_status` enum('pass','fail','pending') NOT NULL DEFAULT 'pending',
  `published_at` datetime DEFAULT NULL,
  `published_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_results_student_course_batch` (`student_id`,`course_id`,`batch_id`),
  KEY `idx_results_institute` (`institute_id`),
  KEY `idx_results_lookup` (`institute_id`,`student_id`,`result_status`),
  KEY `fk_results_course` (`course_id`),
  KEY `fk_results_batch` (`batch_id`),
  KEY `fk_results_published_by` (`published_by`),
  CONSTRAINT `fk_results_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_results_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_results_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_results_published_by` FOREIGN KEY (`published_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_results_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=139 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `results`
--

LOCK TABLES `results` WRITE;
/*!40000 ALTER TABLE `results` DISABLE KEYS */;
/*!40000 ALTER TABLE `results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_permissions` (`role_id`,`permission_id`),
  KEY `fk_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=580 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1,1),(2,1,2),(3,1,3),(4,1,4),(5,1,5),(6,1,6),(7,1,7),(8,1,8),(9,1,9),(10,1,10),(11,1,11),(12,1,12),(13,1,13),(14,1,14),(15,1,15),(16,1,16),(17,1,17),(18,1,18),(19,1,19),(20,1,20),(87,1,21),(88,1,22),(89,1,23),(90,1,24),(91,1,25),(96,1,26),(98,1,27),(100,1,28),(101,1,29),(102,1,30),(103,1,31),(104,1,32),(116,1,33),(117,1,34),(118,1,35),(119,1,36),(120,1,37),(121,1,38),(122,1,39),(123,1,40),(146,1,41),(147,1,42),(153,1,43),(154,1,44),(155,1,45),(156,1,46),(157,1,47),(158,1,48),(159,1,49),(160,1,50),(181,1,51),(182,1,52),(183,1,53),(184,1,54),(185,1,55),(194,1,56),(195,1,57),(201,1,58),(202,1,59),(210,1,60),(211,1,61),(212,1,62),(213,1,63),(214,1,64),(215,1,65),(216,1,66),(217,1,67),(218,1,68),(246,1,69),(247,1,70),(248,1,71),(249,1,72),(250,1,73),(259,1,74),(260,1,75),(261,1,76),(262,1,77),(263,1,78),(264,1,79),(265,1,80),(266,1,81),(267,1,82),(268,1,83),(269,1,84),(270,1,85),(271,1,86),(272,1,87),(309,1,88),(310,1,89),(317,1,90),(318,1,91),(319,1,92),(320,1,93),(321,1,94),(336,1,95),(335,1,96),(341,1,97),(342,1,98),(343,1,99),(344,1,100),(345,1,101),(346,1,102),(347,1,103),(348,1,104),(349,1,105),(350,1,106),(351,1,107),(352,1,108),(353,1,109),(354,1,110),(374,1,111),(375,1,112),(376,1,113),(377,1,114),(378,1,115),(379,1,116),(388,1,117),(389,1,118),(390,1,119),(397,1,120),(398,1,121),(399,1,122),(400,1,123),(401,1,124),(402,1,125),(403,1,126),(404,1,127),(405,1,128),(406,1,129),(424,1,130),(425,1,131),(426,1,132),(427,1,133),(428,1,134),(429,1,135),(430,1,136),(445,1,137),(446,1,138),(447,1,139),(448,1,140),(449,1,141),(450,1,142),(451,1,143),(452,1,144),(453,1,145),(454,1,146),(455,1,147),(456,1,148),(476,1,149),(477,1,150),(478,1,151),(479,1,152),(480,1,153),(481,1,154),(482,1,155),(483,1,156),(484,1,157),(485,1,158),(504,1,159),(505,1,160),(506,1,161),(507,1,162),(508,1,163),(509,1,164),(527,1,165),(530,1,166),(531,1,167),(532,1,168),(533,1,169),(534,1,170),(545,1,171),(546,1,172),(547,1,173),(548,1,174),(549,1,175),(550,1,176),(551,1,177),(552,1,178),(553,1,179),(571,1,180),(573,1,181),(579,1,182),(21,2,1),(22,2,2),(23,2,3),(24,2,4),(25,2,5),(26,2,6),(27,2,7),(28,2,8),(29,2,9),(30,2,10),(31,2,11),(32,2,12),(33,2,13),(34,2,14),(35,2,15),(36,2,16),(37,2,17),(38,2,18),(39,2,19),(40,2,20),(92,2,21),(93,2,22),(94,2,23),(95,2,24),(97,2,26),(99,2,27),(105,2,28),(106,2,29),(107,2,30),(108,2,31),(109,2,32),(124,2,33),(125,2,34),(126,2,35),(127,2,36),(128,2,37),(129,2,38),(130,2,39),(131,2,40),(148,2,41),(149,2,42),(161,2,43),(162,2,44),(163,2,45),(164,2,46),(165,2,47),(166,2,48),(167,2,49),(168,2,50),(186,2,51),(187,2,52),(188,2,53),(189,2,54),(190,2,55),(196,2,56),(197,2,57),(203,2,58),(204,2,59),(219,2,60),(220,2,61),(221,2,62),(222,2,63),(223,2,64),(224,2,65),(225,2,66),(226,2,67),(227,2,68),(251,2,69),(252,2,70),(253,2,71),(254,2,72),(255,2,73),(273,2,74),(274,2,75),(275,2,76),(276,2,77),(277,2,78),(278,2,79),(279,2,80),(280,2,81),(281,2,82),(282,2,83),(283,2,84),(284,2,85),(285,2,86),(286,2,87),(311,2,88),(312,2,89),(322,2,90),(323,2,91),(324,2,92),(325,2,93),(326,2,94),(338,2,95),(337,2,96),(355,2,97),(356,2,98),(357,2,99),(358,2,100),(359,2,101),(360,2,102),(361,2,103),(362,2,104),(363,2,105),(364,2,106),(365,2,107),(366,2,108),(367,2,109),(368,2,110),(380,2,111),(381,2,112),(382,2,113),(383,2,114),(384,2,115),(385,2,116),(391,2,117),(392,2,118),(393,2,119),(407,2,120),(408,2,121),(409,2,122),(410,2,123),(411,2,124),(412,2,125),(413,2,126),(414,2,127),(415,2,128),(416,2,129),(431,2,130),(432,2,131),(433,2,132),(434,2,133),(435,2,134),(436,2,135),(437,2,136),(457,2,137),(458,2,138),(459,2,139),(460,2,140),(461,2,141),(462,2,142),(463,2,143),(464,2,144),(465,2,145),(466,2,146),(467,2,147),(468,2,148),(486,2,149),(487,2,150),(488,2,151),(489,2,152),(490,2,153),(491,2,154),(492,2,155),(493,2,156),(494,2,157),(495,2,158),(510,2,159),(511,2,160),(512,2,161),(513,2,162),(514,2,163),(515,2,164),(528,2,165),(535,2,166),(536,2,167),(537,2,168),(538,2,169),(539,2,170),(554,2,171),(555,2,172),(556,2,173),(557,2,174),(558,2,175),(559,2,176),(560,2,177),(561,2,178),(562,2,179),(572,2,180),(574,2,181),(578,2,182),(41,3,1),(42,3,3),(43,3,4),(44,3,5),(45,3,6),(46,3,7),(47,3,8),(48,3,9),(49,3,10),(50,3,11),(51,3,12),(52,3,13),(53,3,14),(54,3,15),(55,3,16),(56,3,17),(57,3,19),(110,3,28),(111,3,29),(112,3,30),(132,3,33),(133,3,35),(134,3,36),(135,3,38),(152,3,42),(177,3,43),(178,3,44),(179,3,46),(180,3,50),(191,3,51),(192,3,52),(193,3,53),(198,3,56),(199,3,57),(205,3,58),(206,3,59),(228,3,60),(229,3,61),(230,3,62),(231,3,63),(232,3,64),(233,3,65),(234,3,68),(256,3,69),(257,3,71),(287,3,74),(288,3,75),(289,3,76),(290,3,78),(291,3,79),(292,3,85),(293,3,86),(313,3,88),(314,3,89),(332,3,90),(333,3,93),(369,3,97),(370,3,98),(371,3,99),(372,3,102),(373,3,106),(386,3,111),(387,3,112),(394,3,117),(395,3,118),(396,3,119),(417,3,120),(418,3,121),(419,3,122),(420,3,123),(421,3,127),(438,3,130),(439,3,132),(440,3,133),(441,3,134),(442,3,135),(443,3,136),(469,3,137),(470,3,139),(471,3,142),(472,3,144),(473,3,145),(474,3,146),(475,3,147),(496,3,149),(497,3,151),(498,3,154),(499,3,156),(500,3,158),(516,3,159),(517,3,160),(518,3,161),(519,3,162),(520,3,163),(521,3,164),(529,3,165),(540,3,166),(541,3,167),(542,3,168),(563,3,171),(564,3,172),(565,3,176),(566,3,177),(575,3,181),(577,3,182),(58,4,5),(59,4,7),(60,4,9),(61,4,11),(62,4,12),(63,4,13),(200,4,56),(209,4,58),(316,4,88),(422,4,122),(423,4,123),(444,4,136),(501,4,149),(502,4,154),(503,4,158),(522,4,159),(523,4,160),(576,4,181),(64,5,5),(65,5,7),(66,5,9),(67,5,17),(68,5,18),(115,5,28),(136,5,33),(137,5,34),(138,5,35),(139,5,36),(140,5,37),(141,5,38),(142,5,39),(143,5,40),(150,5,41),(151,5,42),(169,5,43),(170,5,44),(171,5,45),(172,5,46),(173,5,47),(174,5,48),(175,5,49),(176,5,50),(235,5,60),(236,5,61),(237,5,62),(238,5,63),(239,5,64),(240,5,65),(241,5,66),(242,5,67),(243,5,68),(294,5,74),(295,5,75),(296,5,76),(297,5,77),(298,5,78),(299,5,79),(300,5,80),(301,5,81),(302,5,82),(303,5,83),(304,5,84),(305,5,85),(306,5,86),(307,5,87),(327,5,90),(328,5,91),(329,5,92),(330,5,93),(331,5,94),(340,5,95),(339,5,96),(526,5,159),(543,5,166),(544,5,170),(567,5,171),(568,5,175),(569,5,176),(570,5,178),(69,6,3),(70,6,5),(71,6,7),(72,6,9),(73,6,10),(74,6,15),(113,6,28),(114,6,29),(144,6,33),(145,6,35),(207,6,58),(208,6,59),(244,6,60),(245,6,61),(258,6,69),(308,6,74),(315,6,88),(334,6,90),(524,6,159),(525,6,160),(75,7,5),(76,7,7),(77,7,9),(78,7,12),(79,7,13),(80,7,14),(81,8,5),(82,8,7),(83,8,9),(84,8,11),(85,8,12),(86,8,13);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_institute_slug` (`institute_id`,`slug`),
  KEY `idx_roles_institute` (`institute_id`),
  CONSTRAINT `fk_roles_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,NULL,'Institute Owner','institute-owner',1,'active','2026-08-05 02:14:56'),(2,NULL,'Institute Admin','institute-admin',1,'active','2026-08-05 02:14:56'),(3,NULL,'Branch Manager','branch-manager',1,'active','2026-08-05 02:14:56'),(4,NULL,'Teacher','teacher',1,'active','2026-08-05 02:14:56'),(5,NULL,'Accountant','accountant',1,'active','2026-08-05 02:14:56'),(6,NULL,'Receptionist','receptionist',1,'active','2026-08-05 02:14:56'),(7,NULL,'Exam Controller','exam-controller',1,'active','2026-08-05 02:14:56'),(8,NULL,'Trainer','trainer',1,'active','2026-08-05 02:14:56'),(9,NULL,'Viewer','viewer',0,'active','2026-09-01 14:36:25');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rooms`
--

DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rooms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `capacity` smallint(5) unsigned NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rooms_institute` (`institute_id`),
  KEY `idx_rooms_branch` (`branch_id`),
  CONSTRAINT `fk_rooms_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rooms_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rooms`
--

LOCK TABLES `rooms` WRITE;
/*!40000 ALTER TABLE `rooms` DISABLE KEYS */;
/*!40000 ALTER TABLE `rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_deliveries`
--

DROP TABLE IF EXISTS `sales_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_deliveries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `delivery_number` varchar(40) NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `warehouse_id` bigint(20) unsigned DEFAULT NULL,
  `delivery_date` date NOT NULL,
  `shipping_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `delivered_by` bigint(20) unsigned DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_deliveries_number` (`institute_id`,`delivery_number`),
  KEY `sales_deliveries_branch_id_foreign` (`branch_id`),
  KEY `sales_deliveries_order_id_foreign` (`order_id`),
  KEY `sales_deliveries_customer_id_foreign` (`customer_id`),
  KEY `sales_deliveries_warehouse_id_foreign` (`warehouse_id`),
  KEY `idx_sales_deliveries_scope_status` (`institute_id`,`branch_id`,`status`),
  KEY `idx_sales_deliveries_order` (`institute_id`,`order_id`),
  KEY `idx_sales_deliveries_customer` (`institute_id`,`customer_id`),
  CONSTRAINT `sales_deliveries_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_deliveries_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `parties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_deliveries_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_deliveries_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_deliveries_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `inventory_warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_deliveries`
--

LOCK TABLES `sales_deliveries` WRITE;
/*!40000 ALTER TABLE `sales_deliveries` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_deliveries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_delivery_lines`
--

DROP TABLE IF EXISTS `sales_delivery_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_delivery_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `delivery_id` bigint(20) unsigned NOT NULL,
  `order_line_id` bigint(20) unsigned NOT NULL,
  `inventory_item_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `ordered_quantity` decimal(19,4) NOT NULL,
  `previously_delivered_quantity` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `delivery_quantity` decimal(19,4) NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_delivery_lines_institute_id_foreign` (`institute_id`),
  KEY `sales_delivery_lines_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `idx_sdl_delivery` (`delivery_id`),
  KEY `idx_sdl_order_line` (`order_line_id`),
  CONSTRAINT `sales_delivery_lines_delivery_id_foreign` FOREIGN KEY (`delivery_id`) REFERENCES `sales_deliveries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_delivery_lines_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_delivery_lines_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_delivery_lines_order_line_id_foreign` FOREIGN KEY (`order_line_id`) REFERENCES `sales_order_lines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_delivery_lines`
--

LOCK TABLES `sales_delivery_lines` WRITE;
/*!40000 ALTER TABLE `sales_delivery_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_delivery_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_order_lines`
--

DROP TABLE IF EXISTS `sales_order_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_order_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `inventory_item_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `quantity` decimal(19,4) NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `unit_price` decimal(19,4) NOT NULL,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_type` varchar(20) NOT NULL DEFAULT 'fixed',
  `tax_group_id` bigint(20) unsigned DEFAULT NULL,
  `tax_rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(19,4) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_order_lines_institute_id_foreign` (`institute_id`),
  KEY `sales_order_lines_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `sales_order_lines_tax_group_id_foreign` (`tax_group_id`),
  KEY `idx_so_lines_order` (`order_id`,`sort_order`),
  CONSTRAINT `sales_order_lines_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_order_lines_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_order_lines_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_order_lines_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=448 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order_lines`
--

LOCK TABLES `sales_order_lines` WRITE;
/*!40000 ALTER TABLE `sales_order_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_order_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_orders`
--

DROP TABLE IF EXISTS `sales_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `order_number` varchar(40) NOT NULL,
  `quotation_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `currency_id` bigint(20) unsigned NOT NULL,
  `payment_terms` varchar(40) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `subtotal` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_type` varchar(20) NOT NULL DEFAULT 'fixed',
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `grand_total` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_orders_number` (`institute_id`,`order_number`),
  UNIQUE KEY `uq_sales_orders_quotation` (`institute_id`,`quotation_id`),
  KEY `sales_orders_branch_id_foreign` (`branch_id`),
  KEY `sales_orders_quotation_id_foreign` (`quotation_id`),
  KEY `sales_orders_customer_id_foreign` (`customer_id`),
  KEY `sales_orders_currency_id_foreign` (`currency_id`),
  KEY `idx_sales_orders_scope_status` (`institute_id`,`branch_id`,`status`),
  KEY `idx_sales_orders_customer` (`institute_id`,`customer_id`),
  KEY `idx_sales_orders_date` (`institute_id`,`order_date`),
  CONSTRAINT `sales_orders_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_orders_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `sales_orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `parties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_orders_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_orders_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `sales_quotations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=355 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_orders`
--

LOCK TABLES `sales_orders` WRITE;
/*!40000 ALTER TABLE `sales_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_quotation_lines`
--

DROP TABLE IF EXISTS `sales_quotation_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_quotation_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `quotation_id` bigint(20) unsigned NOT NULL,
  `inventory_item_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `quantity` decimal(19,4) NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `unit_price` decimal(19,4) NOT NULL,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_type` varchar(20) NOT NULL DEFAULT 'fixed',
  `tax_group_id` bigint(20) unsigned DEFAULT NULL,
  `tax_rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(19,4) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_quotation_lines_institute_id_foreign` (`institute_id`),
  KEY `sales_quotation_lines_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `sales_quotation_lines_tax_group_id_foreign` (`tax_group_id`),
  KEY `idx_q_lines_order` (`quotation_id`,`sort_order`),
  CONSTRAINT `sales_quotation_lines_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_quotation_lines_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_quotation_lines_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `sales_quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_quotation_lines_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_quotation_lines`
--

LOCK TABLES `sales_quotation_lines` WRITE;
/*!40000 ALTER TABLE `sales_quotation_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_quotation_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_quotations`
--

DROP TABLE IF EXISTS `sales_quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_quotations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `quotation_number` varchar(40) NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `quotation_date` date NOT NULL,
  `validity_date` date NOT NULL,
  `currency_id` bigint(20) unsigned NOT NULL,
  `payment_terms` varchar(40) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `subtotal` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_type` varchar(20) NOT NULL DEFAULT 'fixed',
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `grand_total` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `converted_to_order_id` bigint(20) unsigned DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quotations_number` (`institute_id`,`quotation_number`),
  KEY `sales_quotations_branch_id_foreign` (`branch_id`),
  KEY `sales_quotations_customer_id_foreign` (`customer_id`),
  KEY `sales_quotations_currency_id_foreign` (`currency_id`),
  KEY `idx_quotations_scope_status` (`institute_id`,`branch_id`,`status`),
  KEY `idx_quotations_customer` (`institute_id`,`customer_id`),
  KEY `idx_quotations_date` (`institute_id`,`quotation_date`),
  KEY `sales_quotations_converted_to_order_id_foreign` (`converted_to_order_id`),
  CONSTRAINT `sales_quotations_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_quotations_converted_to_order_id_foreign` FOREIGN KEY (`converted_to_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_quotations_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `sales_quotations_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `parties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_quotations_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_quotations`
--

LOCK TABLES `sales_quotations` WRITE;
/*!40000 ALTER TABLE `sales_quotations` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_quotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_return_items`
--

DROP TABLE IF EXISTS `sales_return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_return_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `return_id` bigint(20) unsigned NOT NULL,
  `invoice_item_id` bigint(20) unsigned DEFAULT NULL,
  `sales_order_line_id` bigint(20) unsigned DEFAULT NULL,
  `inventory_item_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `quantity` decimal(19,4) NOT NULL,
  `unit_price` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `tax_rate` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_return_items_institute_id_foreign` (`institute_id`),
  KEY `sales_return_items_invoice_item_id_foreign` (`invoice_item_id`),
  KEY `sales_return_items_sales_order_line_id_foreign` (`sales_order_line_id`),
  KEY `sales_return_items_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `sales_return_items_return_id_index` (`return_id`),
  CONSTRAINT `sales_return_items_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_return_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_return_items_invoice_item_id_foreign` FOREIGN KEY (`invoice_item_id`) REFERENCES `invoice_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_return_items_return_id_foreign` FOREIGN KEY (`return_id`) REFERENCES `sales_returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_return_items_sales_order_line_id_foreign` FOREIGN KEY (`sales_order_line_id`) REFERENCES `sales_order_lines` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_return_items`
--

LOCK TABLES `sales_return_items` WRITE;
/*!40000 ALTER TABLE `sales_return_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_return_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_return_refunds`
--

DROP TABLE IF EXISTS `sales_return_refunds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_return_refunds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `return_id` bigint(20) unsigned NOT NULL,
  `method` enum('cash','bank','other','credit') NOT NULL DEFAULT 'credit',
  `amount` decimal(19,4) NOT NULL,
  `reference` varchar(200) DEFAULT NULL,
  `refund_date` date NOT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `payment_method_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_return_refunds_institute_id_foreign` (`institute_id`),
  KEY `sales_return_refunds_branch_id_foreign` (`branch_id`),
  KEY `sales_return_refunds_journal_id_foreign` (`journal_id`),
  KEY `sales_return_refunds_payment_method_id_foreign` (`payment_method_id`),
  KEY `sales_return_refunds_created_by_foreign` (`created_by`),
  KEY `sales_return_refunds_return_id_index` (`return_id`),
  CONSTRAINT `sales_return_refunds_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_return_refunds_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_return_refunds_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_return_refunds_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_return_refunds_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_return_refunds_return_id_foreign` FOREIGN KEY (`return_id`) REFERENCES `sales_returns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_return_refunds`
--

LOCK TABLES `sales_return_refunds` WRITE;
/*!40000 ALTER TABLE `sales_return_refunds` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_return_refunds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_returns`
--

DROP TABLE IF EXISTS `sales_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_returns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `return_number` varchar(40) NOT NULL,
  `credit_note_number` varchar(40) DEFAULT NULL,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `warehouse_id` bigint(20) unsigned DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `return_date` date NOT NULL,
  `status` enum('draft','approved','posted','cancelled','reversed') NOT NULL DEFAULT 'draft',
  `refund_status` enum('none','pending','partial','refunded','credited') NOT NULL DEFAULT 'none',
  `refund_method` enum('credit','cash','bank','other') DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `subtotal` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `grand_total` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `refundable_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `refunded_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `inventory_journal_id` bigint(20) unsigned DEFAULT NULL,
  `reversal_of` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_returns_institute_id_return_number_unique` (`institute_id`,`return_number`),
  UNIQUE KEY `sales_returns_institute_id_credit_note_number_unique` (`institute_id`,`credit_note_number`),
  KEY `sales_returns_branch_id_foreign` (`branch_id`),
  KEY `sales_returns_order_id_foreign` (`order_id`),
  KEY `sales_returns_customer_id_foreign` (`customer_id`),
  KEY `sales_returns_warehouse_id_foreign` (`warehouse_id`),
  KEY `sales_returns_currency_id_foreign` (`currency_id`),
  KEY `sales_returns_journal_id_foreign` (`journal_id`),
  KEY `sales_returns_inventory_journal_id_foreign` (`inventory_journal_id`),
  KEY `sales_returns_reversal_of_foreign` (`reversal_of`),
  KEY `sales_returns_created_by_foreign` (`created_by`),
  KEY `sales_returns_approved_by_foreign` (`approved_by`),
  KEY `sales_returns_posted_by_foreign` (`posted_by`),
  KEY `sales_returns_cancelled_by_foreign` (`cancelled_by`),
  KEY `sales_returns_institute_id_branch_id_status_index` (`institute_id`,`branch_id`,`status`),
  KEY `sales_returns_invoice_id_index` (`invoice_id`),
  CONSTRAINT `sales_returns_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_returns_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_returns_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_returns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_returns_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_returns_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `parties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_returns_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_returns_inventory_journal_id_foreign` FOREIGN KEY (`inventory_journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_returns_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_returns_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_returns_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_returns_posted_by_foreign` FOREIGN KEY (`posted_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_returns_reversal_of_foreign` FOREIGN KEY (`reversal_of`) REFERENCES `sales_returns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_returns_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `inventory_warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_returns`
--

LOCK TABLES `sales_returns` WRITE;
/*!40000 ALTER TABLE `sales_returns` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_sequences`
--

DROP TABLE IF EXISTS `sales_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_sequences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `document_type` enum('quotation','sales_order','delivery','sales_return','credit_note') NOT NULL,
  `prefix` varchar(20) NOT NULL DEFAULT '',
  `next_number` int(10) unsigned NOT NULL DEFAULT 1,
  `padding` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_sequences_inst_branch_type` (`institute_id`,`branch_id`,`document_type`),
  KEY `sales_sequences_branch_id_foreign` (`branch_id`),
  CONSTRAINT `sales_sequences_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_sequences_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_sequences`
--

LOCK TABLES `sales_sequences` WRITE;
/*!40000 ALTER TABLE `sales_sequences` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schema_migrations`
--

DROP TABLE IF EXISTS `schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schema_migrations` (
  `version` varchar(255) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schema_migrations`
--

LOCK TABLES `schema_migrations` WRITE;
/*!40000 ALTER TABLE `schema_migrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `schema_migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `guard` varchar(30) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_guard_index` (`guard`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `statement_snapshots`
--

DROP TABLE IF EXISTS `statement_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `statement_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `fiscal_year_id` bigint(20) unsigned NOT NULL,
  `period_id` bigint(20) unsigned DEFAULT NULL,
  `statement_type` enum('trial_balance','balance_sheet','income_statement','cash_flow','ledger','receivables','payables') NOT NULL,
  `as_of_date` date NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `checksum` char(64) NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 1,
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `statement_snapshots_fiscal_year_id_foreign` (`fiscal_year_id`),
  KEY `statement_snapshots_period_id_foreign` (`period_id`),
  KEY `idx_snapshots` (`institute_id`,`branch_id`,`fiscal_year_id`,`statement_type`,`as_of_date`),
  KEY `statement_snapshots_branch_id_foreign` (`branch_id`),
  CONSTRAINT `statement_snapshots_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `statement_snapshots_fiscal_year_id_foreign` FOREIGN KEY (`fiscal_year_id`) REFERENCES `fiscal_years` (`id`) ON DELETE CASCADE,
  CONSTRAINT `statement_snapshots_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `statement_snapshots_period_id_foreign` FOREIGN KEY (`period_id`) REFERENCES `accounting_periods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `statement_snapshots`
--

LOCK TABLES `statement_snapshots` WRITE;
/*!40000 ALTER TABLE `statement_snapshots` DISABLE KEYS */;
/*!40000 ALTER TABLE `statement_snapshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `structure_label_dictionary`
--

DROP TABLE IF EXISTS `structure_label_dictionary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `structure_label_dictionary` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `code` varchar(80) NOT NULL,
  `category` varchar(40) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sld_category_code_unique` (`category`,`code`),
  KEY `sld_category_status_idx` (`category`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `structure_label_dictionary`
--

LOCK TABLES `structure_label_dictionary` WRITE;
/*!40000 ALTER TABLE `structure_label_dictionary` DISABLE KEYS */;
INSERT INTO `structure_label_dictionary` VALUES (62,'School','school','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(63,'College','college','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(64,'University','university','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(65,'Faculty','faculty','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(66,'Institute','institute','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(67,'Academy','academy','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(68,'Center','center','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(69,'Workshop','workshop','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(70,'Coaching Center','coaching_center','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(71,'Madrasa','madrasa','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(72,'Vocational Institute','vocational_institute','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(73,'Technical Institute','technical_institute','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(74,'Music Academy','music_academy','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(75,'Dance Academy','dance_academy','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(76,'Martial Arts Academy','martial_arts_academy','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(77,'Sports Academy','sports_academy','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(78,'Language Academy','language_academy','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(79,'Learning Center','learning_center','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(80,'Skill Academy','skill_academy','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(81,'Professional Training Center','professional_training_center','top_level',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(82,'Class','class','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(83,'Grade','grade','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(84,'Year','year','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(85,'Semester','semester','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(86,'Term','term','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(87,'Section','section','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(88,'Division','division','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(89,'Course','course','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(90,'Program','program','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(91,'Module','module','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(92,'Session','session','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(93,'Batch','batch','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(94,'Workshop','workshop_level','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(95,'Discipline','discipline','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(96,'Level','level','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(97,'Belt','belt','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(98,'Stage','stage','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(99,'Rank','rank','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(100,'Instrument','instrument','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(101,'Dance Style','dance_style','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(102,'Vocal','vocal','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(103,'Genre','genre','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(104,'Sport','sport','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(105,'Age Group','age_group','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(106,'Team','team','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(107,'Squad','squad','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(108,'Department','department','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(109,'Group','group','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(110,'Stream','stream','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(111,'Category','category','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(112,'Phase','phase','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(113,'Trade','trade','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(114,'Language','language','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(115,'Subject','subject','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(116,'Faculty','faculty_level','level_label',1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(117,'Grade Numbers','grade_numbers','value_template',1,'{\"values\":[\"1\",\"2\",\"3\",\"4\",\"5\",\"6\",\"7\",\"8\",\"9\",\"10\",\"11\",\"12\"]}','2026-09-01 08:24:27','2026-09-01 08:24:27'),(118,'Sections','sections','value_template',1,'{\"values\":[\"Section A\",\"Section B\",\"Section C\",\"Section D\"]}','2026-09-01 08:24:27','2026-09-01 08:24:27'),(119,'Year Numbers','year_numbers','value_template',1,'{\"values\":[\"1st Year\",\"2nd Year\",\"3rd Year\",\"4th Year\"]}','2026-09-01 08:24:27','2026-09-01 08:24:27'),(120,'Belt Colors','belt_colors','value_template',1,'{\"values\":[\"White\",\"Yellow\",\"Orange\",\"Green\",\"Blue\",\"Purple\",\"Brown\",\"Black\"]}','2026-09-01 08:24:27','2026-09-01 08:24:27'),(121,'Age Groups','age_groups','value_template',1,'{\"values\":[\"Under-8\",\"Under-10\",\"Under-12\",\"Under-14\",\"Under-16\",\"Under-18\",\"18+\"]}','2026-09-01 08:24:27','2026-09-01 08:24:27'),(122,'Batch Timings','batch_timings','value_template',1,'{\"values\":[\"Morning\",\"Afternoon\",\"Evening\",\"Weekend\"]}','2026-09-01 08:24:27','2026-09-01 08:24:27');
/*!40000 ALTER TABLE `structure_label_dictionary` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `structure_nodes`
--

DROP TABLE IF EXISTS `structure_nodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `structure_nodes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `template_id` bigint(20) unsigned NOT NULL,
  `template_level_id` bigint(20) unsigned DEFAULT NULL,
  `parent_node_id` bigint(20) unsigned DEFAULT NULL,
  `level_order` int(10) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(80) DEFAULT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_custom` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `structure_nodes_template_level_id_foreign` (`template_level_id`),
  KEY `sn_institute_idx` (`institute_id`),
  KEY `sn_template_idx` (`template_id`),
  KEY `sn_template_level_idx` (`template_id`,`level_order`),
  KEY `sn_parent_idx` (`parent_node_id`),
  KEY `sn_branch_idx` (`branch_id`),
  KEY `sn_status_idx` (`status`),
  KEY `sn_institute_template_level_idx` (`institute_id`,`template_id`,`level_order`),
  KEY `sn_institute_parent_idx` (`institute_id`,`parent_node_id`),
  CONSTRAINT `structure_nodes_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `structure_nodes_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `structure_nodes_parent_node_id_foreign` FOREIGN KEY (`parent_node_id`) REFERENCES `structure_nodes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `structure_nodes_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `structure_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `structure_nodes_template_level_id_foreign` FOREIGN KEY (`template_level_id`) REFERENCES `structure_template_levels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=124 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `structure_nodes`
--

LOCK TABLES `structure_nodes` WRITE;
/*!40000 ALTER TABLE `structure_nodes` DISABLE KEYS */;
INSERT INTO `structure_nodes` VALUES (9,69,17,46,NULL,1,'F',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:29','2026-09-01 08:24:29'),(10,69,17,47,9,2,'D',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:29','2026-09-01 08:24:29'),(11,69,17,48,10,3,'P',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:29','2026-09-01 08:24:29'),(12,70,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:29','2026-09-01 08:24:29'),(13,69,17,49,11,4,'S',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:29','2026-09-01 08:24:29'),(14,70,15,41,NULL,1,'Class 2',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:29','2026-09-01 08:24:29'),(15,74,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:30','2026-09-01 08:24:30'),(16,74,15,42,15,2,'Sec A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:30','2026-09-01 08:24:30'),(17,81,15,41,NULL,1,'Class A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:30','2026-09-01 08:24:30'),(18,86,15,41,NULL,1,'Class A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:31','2026-09-01 08:24:31'),(19,89,15,41,NULL,1,'Shared',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:31','2026-09-01 08:24:31'),(20,89,15,42,19,2,'Sec B1',NULL,0,1,1,4,NULL,'2026-09-01 08:24:31','2026-09-01 08:24:31'),(21,91,15,41,NULL,1,'Shared Class',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:32','2026-09-01 08:24:32'),(22,94,17,46,NULL,1,'Science',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:32','2026-09-01 08:24:32'),(23,94,17,47,22,2,'CS',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:32','2026-09-01 08:24:32'),(24,94,17,48,23,3,'BSc',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:32','2026-09-01 08:24:32'),(25,94,17,49,24,4,'Sem 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:32','2026-09-01 08:24:32'),(26,95,17,46,NULL,1,'Fac',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:32','2026-09-01 08:24:32'),(27,96,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:32','2026-09-01 08:24:32'),(28,96,15,42,27,2,'Sec A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:32','2026-09-01 08:24:32'),(29,98,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:33','2026-09-01 08:24:33'),(30,98,15,42,29,2,'Sec A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:33','2026-09-01 08:24:33'),(31,99,17,46,NULL,1,'Fac',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:33','2026-09-01 08:24:33'),(32,101,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:33','2026-09-01 08:24:33'),(33,100,15,41,NULL,1,'Class X',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:33','2026-09-01 08:24:33'),(34,101,15,42,32,2,'Sec A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:33','2026-09-01 08:24:33'),(35,103,15,41,NULL,1,'Class A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:33','2026-09-01 08:24:33'),(36,105,15,41,NULL,1,'Inactive',NULL,0,0,1,NULL,NULL,'2026-09-01 08:24:33','2026-09-01 08:24:33'),(37,108,15,41,NULL,1,'Class A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:34','2026-09-01 08:24:34'),(38,113,15,41,NULL,1,'Shared Class',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:34','2026-09-01 08:24:34'),(39,114,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:35','2026-09-01 08:24:35'),(40,115,15,41,NULL,1,'B1 Class',NULL,0,1,1,8,NULL,'2026-09-01 08:24:35','2026-09-01 08:24:35'),(41,116,15,41,NULL,1,'NewName',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:35','2026-09-01 08:24:35'),(42,117,15,41,NULL,1,'Shared',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:35','2026-09-01 08:24:35'),(43,117,15,42,42,2,'Sec',NULL,0,1,1,10,NULL,'2026-09-01 08:24:35','2026-09-01 08:24:35'),(45,119,15,41,NULL,1,'Class 5',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:36','2026-09-01 08:24:36'),(46,121,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:36','2026-09-01 08:24:36'),(47,121,15,42,46,2,'Sec A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:36','2026-09-01 08:24:36'),(48,122,17,46,NULL,1,'Fac',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:36','2026-09-01 08:24:36'),(49,123,15,41,NULL,1,'New',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:36','2026-09-01 08:24:36'),(50,124,15,41,NULL,1,'Class A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:36','2026-09-01 08:24:36'),(51,126,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:37','2026-09-01 08:24:37'),(52,126,15,41,NULL,1,'Class 2',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:37','2026-09-01 08:24:37'),(53,126,15,42,52,2,'Sec',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:37','2026-09-01 08:24:37'),(54,129,15,41,NULL,1,'A',NULL,2,1,1,NULL,NULL,'2026-09-01 08:24:37','2026-09-01 08:24:37'),(55,129,15,41,NULL,1,'B',NULL,3,1,1,NULL,NULL,'2026-09-01 08:24:37','2026-09-01 08:24:37'),(56,129,15,41,NULL,1,'C',NULL,1,1,1,NULL,NULL,'2026-09-01 08:24:37','2026-09-01 08:24:37'),(57,130,15,41,NULL,1,'Shared',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:37','2026-09-01 08:24:37'),(59,133,15,41,NULL,1,'Class X',NULL,0,0,1,NULL,NULL,'2026-09-01 08:24:38','2026-09-01 08:24:38'),(60,132,15,41,NULL,1,'Shared',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:38','2026-09-01 08:24:38'),(61,132,15,42,60,2,'Sec',NULL,0,1,1,13,NULL,'2026-09-01 08:24:38','2026-09-01 08:24:38'),(62,136,15,41,NULL,1,'Allowed',NULL,0,1,1,NULL,NULL,'2026-09-01 08:24:40','2026-09-01 08:24:40'),(69,154,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:15','2026-09-01 08:36:15'),(70,154,15,41,NULL,1,'Class 2',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:15','2026-09-01 08:36:15'),(71,156,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:16','2026-09-01 08:36:16'),(72,156,15,42,71,2,'Sec A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:16','2026-09-01 08:36:16'),(73,161,15,41,NULL,1,'Class A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:17','2026-09-01 08:36:17'),(74,165,15,41,NULL,1,'Class A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:17','2026-09-01 08:36:17'),(75,168,17,46,NULL,1,'Science',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:17','2026-09-01 08:36:17'),(76,168,17,47,75,2,'CS',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:17','2026-09-01 08:36:17'),(77,168,17,48,76,3,'BSc',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:17','2026-09-01 08:36:17'),(78,168,17,49,77,4,'Sem 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:17','2026-09-01 08:36:17'),(79,169,16,43,NULL,1,'Shared',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:17','2026-09-01 08:36:17'),(80,169,16,44,79,2,'Sec B1',NULL,0,1,1,15,NULL,'2026-09-01 08:36:17','2026-09-01 08:36:17'),(81,171,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:17','2026-09-01 08:36:17'),(82,171,15,42,81,2,'Sec A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:18','2026-09-01 08:36:18'),(83,173,15,41,NULL,1,'Shared Class',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:18','2026-09-01 08:36:18'),(84,174,17,46,NULL,1,'Fac',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:18','2026-09-01 08:36:18'),(85,176,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:18','2026-09-01 08:36:18'),(86,176,15,42,85,2,'Sec A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:18','2026-09-01 08:36:18'),(87,178,17,46,NULL,1,'Fac',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:19','2026-09-01 08:36:19'),(88,179,15,41,NULL,1,'Class A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:19','2026-09-01 08:36:19'),(89,182,15,41,NULL,1,'Class A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:19','2026-09-01 08:36:19'),(90,184,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:19','2026-09-01 08:36:19'),(91,184,15,42,90,2,'Sec A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:19','2026-09-01 08:36:19'),(92,187,15,41,NULL,1,'Class X',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:19','2026-09-01 08:36:19'),(93,190,15,41,NULL,1,'Shared Class',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:20','2026-09-01 08:36:20'),(94,191,15,41,NULL,1,'Inactive',NULL,0,0,1,NULL,NULL,'2026-09-01 08:36:20','2026-09-01 08:36:20'),(95,194,15,41,NULL,1,'B1 Class',NULL,0,1,1,19,NULL,'2026-09-01 08:36:20','2026-09-01 08:36:20'),(97,197,15,41,NULL,1,'Shared',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:21','2026-09-01 08:36:21'),(98,197,15,42,97,2,'Sec',NULL,0,1,1,21,NULL,'2026-09-01 08:36:21','2026-09-01 08:36:21'),(99,198,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:21','2026-09-01 08:36:21'),(100,199,17,46,NULL,1,'F',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:21','2026-09-01 08:36:21'),(101,199,17,47,100,2,'D',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:21','2026-09-01 08:36:21'),(102,199,17,48,101,3,'P',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:21','2026-09-01 08:36:21'),(103,199,17,49,102,4,'S',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:21','2026-09-01 08:36:21'),(104,200,15,41,NULL,1,'Class 5',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:21','2026-09-01 08:36:21'),(105,201,15,41,NULL,1,'NewName',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:21','2026-09-01 08:36:21'),(106,203,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:22','2026-09-01 08:36:22'),(107,203,15,42,106,2,'Sec A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:22','2026-09-01 08:36:22'),(109,205,15,41,NULL,1,'New',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:22','2026-09-01 08:36:22'),(110,207,15,41,NULL,1,'Class 1',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:22','2026-09-01 08:36:22'),(111,207,15,41,NULL,1,'Class 2',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:22','2026-09-01 08:36:22'),(112,207,15,42,111,2,'Sec',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:22','2026-09-01 08:36:22'),(113,209,17,46,NULL,1,'Fac',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:23','2026-09-01 08:36:23'),(114,210,15,41,NULL,1,'A',NULL,2,1,1,NULL,NULL,'2026-09-01 08:36:23','2026-09-01 08:36:23'),(115,210,15,41,NULL,1,'B',NULL,3,1,1,NULL,NULL,'2026-09-01 08:36:23','2026-09-01 08:36:23'),(116,210,15,41,NULL,1,'C',NULL,1,1,1,NULL,NULL,'2026-09-01 08:36:23','2026-09-01 08:36:23'),(117,211,15,41,NULL,1,'Class A',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:23','2026-09-01 08:36:23'),(119,216,15,41,NULL,1,'Class X',NULL,0,0,1,NULL,NULL,'2026-09-01 08:36:24','2026-09-01 08:36:24'),(120,217,15,41,NULL,1,'Shared',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:24','2026-09-01 08:36:24'),(121,219,15,41,NULL,1,'Shared',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:25','2026-09-01 08:36:25'),(122,219,15,42,121,2,'Sec',NULL,0,1,1,24,NULL,'2026-09-01 08:36:25','2026-09-01 08:36:25'),(123,221,15,41,NULL,1,'Allowed',NULL,0,1,1,NULL,NULL,'2026-09-01 08:36:26','2026-09-01 08:36:26');
/*!40000 ALTER TABLE `structure_nodes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `structure_template_levels`
--

DROP TABLE IF EXISTS `structure_template_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `structure_template_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint(20) unsigned NOT NULL,
  `level_order` int(10) unsigned NOT NULL,
  `label` varchar(80) NOT NULL,
  `label_key` varchar(80) DEFAULT NULL,
  `required` tinyint(1) NOT NULL DEFAULT 1,
  `has_values` tinyint(1) NOT NULL DEFAULT 1,
  `value_source` varchar(80) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stl_template_levelorder_unique` (`template_id`,`level_order`),
  KEY `stl_template_order_idx` (`template_id`,`level_order`),
  CONSTRAINT `structure_template_levels_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `structure_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `structure_template_levels`
--

LOCK TABLES `structure_template_levels` WRITE;
/*!40000 ALTER TABLE `structure_template_levels` DISABLE KEYS */;
INSERT INTO `structure_template_levels` VALUES (41,15,1,'Class','class',1,1,'grade_numbers',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(42,15,2,'Section','section',1,1,'sections',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(43,16,1,'Year','year',1,1,'year_numbers',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(44,16,2,'Group','group',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(45,16,3,'Section','section',1,1,'sections',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(46,17,1,'Faculty','faculty_level',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(47,17,2,'Department','department',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(48,17,3,'Program','program',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(49,17,4,'Semester','semester',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(50,18,1,'Course','course',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(51,18,2,'Batch','batch',1,1,'batch_timings',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(52,19,1,'Subject','subject',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(53,19,2,'Batch','batch',1,1,'batch_timings',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(54,20,1,'Level','level',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(55,20,2,'Class','class',1,1,'grade_numbers',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(56,20,3,'Section','section',1,1,'sections',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(57,21,1,'Trade','trade',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(58,21,2,'Level','level',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(59,21,3,'Batch','batch',1,1,'batch_timings',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(60,22,1,'Program','program',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(61,22,2,'Semester','semester',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(62,22,3,'Batch','batch',1,1,'batch_timings',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(63,23,1,'Discipline','discipline',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(64,23,2,'Level','level',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(65,23,3,'Batch','batch',1,1,'batch_timings',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(66,24,1,'Discipline','discipline',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(67,24,2,'Belt','belt',1,1,'belt_colors',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(68,24,3,'Batch','batch',1,1,'batch_timings',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(69,25,1,'Dance Style','dance_style',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(70,25,2,'Grade','grade',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(71,25,3,'Batch','batch',1,1,'batch_timings',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(72,26,1,'Instrument','instrument',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(73,26,2,'Level','level',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(74,26,3,'Batch','batch',1,1,'batch_timings',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(75,27,1,'Sport','sport',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(76,27,2,'Age Group','age_group',1,1,'age_groups',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(77,27,3,'Team','team',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(78,28,1,'Language','language',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(79,28,2,'Level','level',1,1,NULL,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(80,28,3,'Batch','batch',1,1,'batch_timings',NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(81,29,1,'X','class',1,1,NULL,NULL,'2026-09-01 08:24:34','2026-09-01 08:24:34'),(82,30,1,'X','class',1,1,NULL,NULL,'2026-09-01 08:24:37','2026-09-01 08:24:37'),(83,31,1,'X','class',1,1,NULL,NULL,'2026-09-01 08:36:19','2026-09-01 08:36:19'),(84,32,1,'X','class',1,1,NULL,NULL,'2026-09-01 08:36:24','2026-09-01 08:36:24');
/*!40000 ALTER TABLE `structure_template_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `structure_templates`
--

DROP TABLE IF EXISTS `structure_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `structure_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `code` varchar(80) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_global` tinyint(1) NOT NULL DEFAULT 1,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `st_code_global_institute_unique` (`code`,`is_global`,`institute_id`),
  KEY `st_global_status_idx` (`is_global`,`status`),
  KEY `st_institute_status_idx` (`institute_id`,`status`),
  CONSTRAINT `structure_templates_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `structure_templates`
--

LOCK TABLES `structure_templates` WRITE;
/*!40000 ALTER TABLE `structure_templates` DISABLE KEYS */;
INSERT INTO `structure_templates` VALUES (15,'School','school','Class → Section',1,NULL,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(16,'College','college','Year → Group → Section',1,NULL,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(17,'University','university','Faculty → Department → Program → Semester',1,NULL,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(18,'Training Institute','training_institute','Course → Batch',1,NULL,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(19,'Coaching Center','coaching_center','Subject → Batch',1,NULL,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(20,'Madrasa','madrasa','Level → Class → Section',1,NULL,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(21,'Vocational Institute','vocational_institute','Trade → Level → Batch',1,NULL,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(22,'Technical Institute','technical_institute','Program → Semester → Batch',1,NULL,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(23,'Martial Arts — Style Based','martial_arts_style','Discipline → Level → Batch',1,NULL,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(24,'Martial Arts — Belt Based','martial_arts_belt','Discipline → Belt → Batch',1,NULL,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(25,'Dance Academy','dance_academy','Dance Style → Grade → Batch',1,NULL,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(26,'Music Academy','music_academy','Instrument → Level → Batch',1,NULL,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(27,'Sports Academy','sports_academy','Sport → Age Group → Team',1,NULL,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(28,'Language Academy','language_academy','Language → Level → Batch',1,NULL,1,NULL,'2026-09-01 08:24:27','2026-09-01 08:24:27'),(29,'Private','private_6a968bc29d0b3',NULL,0,111,1,NULL,'2026-09-01 08:24:34','2026-09-01 08:24:34'),(30,'Private','priv_6a968bc546233',NULL,0,127,1,NULL,'2026-09-01 08:24:37','2026-09-01 08:24:37'),(31,'Private','private_6a968e83f23ba',NULL,0,186,1,NULL,'2026-09-01 08:36:19','2026-09-01 08:36:19'),(32,'Private','priv_6a968e8810743',NULL,0,214,1,NULL,'2026-09-01 08:36:24','2026-09-01 08:36:24');
/*!40000 ALTER TABLE `structure_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_academic_placements`
--

DROP TABLE IF EXISTS `student_academic_placements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_academic_placements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `academic_year_id` bigint(20) unsigned NOT NULL,
  `class_grade_id` bigint(20) unsigned DEFAULT NULL,
  `academic_group_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `notes` varchar(500) DEFAULT NULL,
  `structure_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`structure_snapshot`)),
  `structure_version` int(10) unsigned NOT NULL DEFAULT 1,
  `class_grade_name_snapshot` varchar(120) DEFAULT NULL,
  `academic_group_name_snapshot` varchar(120) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_academic_placements_student_id_academic_year_id_unique` (`student_id`,`academic_year_id`),
  KEY `student_academic_placements_academic_year_id_foreign` (`academic_year_id`),
  KEY `student_academic_placements_class_grade_id_foreign` (`class_grade_id`),
  KEY `student_academic_placements_academic_group_id_foreign` (`academic_group_id`),
  KEY `sap_institute_year_status_idx` (`institute_id`,`academic_year_id`,`status`),
  CONSTRAINT `student_academic_placements_academic_group_id_foreign` FOREIGN KEY (`academic_group_id`) REFERENCES `academic_groups` (`id`),
  CONSTRAINT `student_academic_placements_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_academic_placements_class_grade_id_foreign` FOREIGN KEY (`class_grade_id`) REFERENCES `class_grades` (`id`),
  CONSTRAINT `student_academic_placements_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_academic_placements_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_academic_placements`
--

LOCK TABLES `student_academic_placements` WRITE;
/*!40000 ALTER TABLE `student_academic_placements` DISABLE KEYS */;
INSERT INTO `student_academic_placements` VALUES (3,133,432,4,NULL,NULL,'active',NULL,NULL,1,NULL,NULL,'2026-09-01 08:24:38','2026-09-01 08:24:38',NULL),(4,135,433,5,NULL,NULL,'active',NULL,NULL,1,NULL,NULL,'2026-09-01 08:24:39','2026-09-01 08:24:39',NULL),(5,138,434,6,NULL,NULL,'active',NULL,NULL,1,NULL,NULL,'2026-09-01 08:24:40','2026-09-01 08:24:40',NULL),(6,216,435,7,NULL,NULL,'active',NULL,NULL,1,NULL,NULL,'2026-09-01 08:36:24','2026-09-01 08:36:24',NULL),(7,218,436,8,NULL,NULL,'active',NULL,NULL,1,NULL,NULL,'2026-09-01 08:36:24','2026-09-01 08:36:24',NULL),(8,223,437,9,NULL,NULL,'active',NULL,NULL,1,NULL,NULL,'2026-09-01 08:36:26','2026-09-01 08:36:26',NULL);
/*!40000 ALTER TABLE `student_academic_placements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_enrollments`
--

DROP TABLE IF EXISTS `student_enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `roll_number` varchar(20) NOT NULL,
  `enrollment_date` date NOT NULL,
  `fee_payable` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','completed','dropped','transferred') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enrollments_batch_roll` (`batch_id`,`roll_number`),
  KEY `idx_enrollments_institute` (`institute_id`),
  KEY `idx_enrollments_student` (`student_id`),
  KEY `idx_enrollments_course` (`course_id`),
  KEY `idx_enrollments_batch` (`batch_id`),
  CONSTRAINT `fk_enrollments_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enrollments_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enrollments_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enrollments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=524 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_enrollments`
--

LOCK TABLES `student_enrollments` WRITE;
/*!40000 ALTER TABLE `student_enrollments` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_guardians`
--

DROP TABLE IF EXISTS `student_guardians`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_guardians` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `guardian_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `relationship` enum('father','mother','guardian','other') NOT NULL DEFAULT 'guardian',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_guardians_pair` (`guardian_id`,`student_id`),
  KEY `student_guardians_student_id_foreign` (`student_id`),
  KEY `idx_student_guardians_student` (`institute_id`,`student_id`),
  KEY `idx_student_guardians_active` (`guardian_id`,`status`),
  CONSTRAINT `student_guardians_guardian_id_foreign` FOREIGN KEY (`guardian_id`) REFERENCES `guardians` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_guardians_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_guardians_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_guardians`
--

LOCK TABLES `student_guardians` WRITE;
/*!40000 ALTER TABLE `student_guardians` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_guardians` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_placement_nodes`
--

DROP TABLE IF EXISTS `student_placement_nodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_placement_nodes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_academic_placement_id` bigint(20) unsigned NOT NULL,
  `level_order` int(10) unsigned NOT NULL,
  `node_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `spn_placement_level_unique` (`student_academic_placement_id`,`level_order`),
  UNIQUE KEY `spn_placement_node_unique` (`student_academic_placement_id`,`node_id`),
  KEY `spn_node_idx` (`node_id`),
  CONSTRAINT `student_placement_nodes_node_id_foreign` FOREIGN KEY (`node_id`) REFERENCES `structure_nodes` (`id`),
  CONSTRAINT `student_placement_nodes_student_academic_placement_id_foreign` FOREIGN KEY (`student_academic_placement_id`) REFERENCES `student_academic_placements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_placement_nodes`
--

LOCK TABLES `student_placement_nodes` WRITE;
/*!40000 ALTER TABLE `student_placement_nodes` DISABLE KEYS */;
INSERT INTO `student_placement_nodes` VALUES (1,3,1,59,'2026-09-01 08:24:38','2026-09-01 08:24:38'),(2,6,1,119,'2026-09-01 08:36:24','2026-09-01 08:36:24');
/*!40000 ALTER TABLE `student_placement_nodes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_subject_selections`
--

DROP TABLE IF EXISTS `student_subject_selections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_subject_selections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `academic_placement_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `selection_group_id` bigint(20) unsigned DEFAULT NULL,
  `is_selected` tinyint(1) NOT NULL DEFAULT 1,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 0,
  `source` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sss_placement_subject_unique` (`academic_placement_id`,`subject_id`),
  KEY `student_subject_selections_subject_id_foreign` (`subject_id`),
  KEY `student_subject_selections_selection_group_id_foreign` (`selection_group_id`),
  KEY `sss_placement_selected_idx` (`academic_placement_id`,`is_selected`),
  KEY `student_subject_selections_institute_id_foreign` (`institute_id`),
  CONSTRAINT `student_subject_selections_academic_placement_id_foreign` FOREIGN KEY (`academic_placement_id`) REFERENCES `student_academic_placements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_subject_selections_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_subject_selections_selection_group_id_foreign` FOREIGN KEY (`selection_group_id`) REFERENCES `academic_selection_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_subject_selections_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_subject_selections`
--

LOCK TABLES `student_subject_selections` WRITE;
/*!40000 ALTER TABLE `student_subject_selections` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_subject_selections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_waivers`
--

DROP TABLE IF EXISTS `student_waivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_waivers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_id` bigint(20) unsigned DEFAULT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `enrollment_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `waived_by` bigint(20) unsigned DEFAULT NULL,
  `waived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_waivers_inst_branch` (`institute_id`,`branch_id`),
  KEY `idx_student_waivers_inst_student` (`institute_id`,`student_id`),
  KEY `fk_student_waivers_branch` (`branch_id`),
  KEY `fk_student_waivers_invoice` (`invoice_id`),
  KEY `fk_student_waivers_student` (`student_id`),
  KEY `fk_student_waivers_enrollment` (`enrollment_id`),
  KEY `fk_student_waivers_waived_by` (`waived_by`),
  CONSTRAINT `fk_student_waivers_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_student_waivers_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `student_enrollments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_student_waivers_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_waivers_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_student_waivers_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_waivers_waived_by` FOREIGN KEY (`waived_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_waivers`
--

LOCK TABLES `student_waivers` WRITE;
/*!40000 ALTER TABLE `student_waivers` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_waivers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `reg_no` varchar(20) DEFAULT NULL,
  `student_id` varchar(6) NOT NULL,
  `is_test` tinyint(1) DEFAULT 0,
  `first_name` varchar(60) NOT NULL DEFAULT '',
  `last_name` varchar(60) NOT NULL DEFAULT '',
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `student_id_number` varchar(40) NOT NULL,
  `application_number` varchar(30) DEFAULT NULL,
  `application_date` date DEFAULT NULL,
  `admission_status` enum('draft','submitted','under_review','approved','rejected','cancelled','enrolled','withdrawn') NOT NULL DEFAULT 'enrolled',
  `admission_source` varchar(60) DEFAULT NULL,
  `admission_reject_reason` varchar(255) DEFAULT NULL,
  `applied_course_id` bigint(20) unsigned DEFAULT NULL,
  `applied_academic_year_id` bigint(20) unsigned DEFAULT NULL,
  `preferred_batch_id` bigint(20) unsigned DEFAULT NULL,
  `admission_assigned_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_by` bigint(20) unsigned DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `registration_number` varchar(12) DEFAULT NULL,
  `roll_number` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `document` varchar(255) DEFAULT NULL,
  `father_name` varchar(120) DEFAULT NULL,
  `mother_name` varchar(120) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `religion` varchar(40) DEFAULT NULL,
  `nationality` varchar(60) NOT NULL DEFAULT 'Bangladeshi',
  `nid_number` varchar(30) DEFAULT NULL,
  `birth_cert_number` varchar(30) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `guardian_phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `country` varchar(80) DEFAULT NULL,
  `present_country_id` bigint(20) unsigned DEFAULT NULL,
  `present_admin_1_id` bigint(20) unsigned DEFAULT NULL,
  `present_admin_2_id` bigint(20) unsigned DEFAULT NULL,
  `present_admin_3_id` bigint(20) unsigned DEFAULT NULL,
  `present_address` varchar(255) DEFAULT NULL,
  `permanent_address` varchar(255) DEFAULT NULL,
  `present_division_id` varchar(10) DEFAULT NULL,
  `present_district_id` varchar(10) DEFAULT NULL,
  `present_upazila_id` varchar(10) DEFAULT NULL,
  `present_post_office` varchar(100) DEFAULT NULL,
  `present_zip_code` varchar(10) DEFAULT NULL,
  `permanent_division_id` varchar(10) DEFAULT NULL,
  `permanent_district_id` varchar(10) DEFAULT NULL,
  `permanent_upazila_id` varchar(10) DEFAULT NULL,
  `permanent_post_office` varchar(100) DEFAULT NULL,
  `permanent_zip_code` varchar(10) DEFAULT NULL,
  `permanent_country_id` bigint(20) unsigned DEFAULT NULL,
  `permanent_admin_1_id` bigint(20) unsigned DEFAULT NULL,
  `permanent_admin_2_id` bigint(20) unsigned DEFAULT NULL,
  `permanent_admin_3_id` bigint(20) unsigned DEFAULT NULL,
  `national_id_or_birth_certificate` varchar(40) DEFAULT NULL,
  `passport_number` varchar(40) DEFAULT NULL,
  `emergency_contact_name` varchar(120) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `admission_date` date NOT NULL,
  `status` enum('active','completed','dropped','suspended') NOT NULL DEFAULT 'active',
  `crm_contact_id` bigint(20) unsigned DEFAULT NULL,
  `crm_lead_id` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `full_name` varchar(121) GENERATED ALWAYS AS (trim(concat(`first_name`,' ',`last_name`))) STORED,
  `name` varchar(121) GENERATED ALWAYS AS (trim(concat(`first_name`,' ',`last_name`))) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_uuid` (`uuid`),
  UNIQUE KEY `uq_students_inst_student_no` (`institute_id`,`student_id_number`),
  UNIQUE KEY `students_institute_id_student_id_unique` (`institute_id`,`student_id`),
  UNIQUE KEY `uq_students_registration_number` (`registration_number`),
  UNIQUE KEY `uq_students_inst_email` (`institute_id`,`email`),
  UNIQUE KEY `uq_students_inst_phone` (`institute_id`,`phone`),
  UNIQUE KEY `uq_students_inst_nid` (`institute_id`,`nid_number`),
  UNIQUE KEY `uq_students_inst_birth_cert` (`institute_id`,`birth_cert_number`),
  UNIQUE KEY `uq_students_inst_passport` (`institute_id`,`passport_number`),
  UNIQUE KEY `uq_students_inst_app_number` (`institute_id`,`application_number`),
  UNIQUE KEY `students_reg_no_unique` (`reg_no`),
  KEY `idx_students_institute` (`institute_id`),
  KEY `idx_students_branch` (`branch_id`),
  KEY `idx_students_phone` (`phone`),
  KEY `students_crm_contact_idx` (`crm_contact_id`),
  KEY `students_crm_lead_idx` (`crm_lead_id`),
  KEY `idx_students_inst_admission_status` (`institute_id`,`admission_status`),
  KEY `fk_students_applied_course` (`applied_course_id`),
  KEY `fk_students_applied_academic_year` (`applied_academic_year_id`),
  KEY `students_preferred_batch_idx` (`preferred_batch_id`),
  KEY `students_admission_assigned_idx` (`admission_assigned_user_id`),
  KEY `students_created_by_idx` (`created_by`),
  KEY `students_approved_by_idx` (`approved_by`),
  KEY `students_rejected_by_idx` (`rejected_by`),
  KEY `students_user_id_foreign` (`user_id`),
  CONSTRAINT `fk_students_applied_academic_year` FOREIGN KEY (`applied_academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_students_applied_course` FOREIGN KEY (`applied_course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_students_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_students_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `students_admission_assigned_fk` FOREIGN KEY (`admission_assigned_user_id`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `students_crm_contact_id_foreign` FOREIGN KEY (`crm_contact_id`) REFERENCES `crm_contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `students_crm_lead_id_foreign` FOREIGN KEY (`crm_lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `students_preferred_batch_fk` FOREIGN KEY (`preferred_batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `students_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=438 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (432,NULL,'6426095870','656466',0,'Test','Student','92bb6be3-a5de-11f1-9275-e0d55e5927b4',133,NULL,'S6a968bc6c905a',NULL,NULL,'enrolled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-01','active',NULL,NULL,NULL,'2026-09-01 14:24:38','2026-09-01 14:24:38','Test Student','Test Student'),(433,NULL,'1426099740','244866',0,'Place','Test','93610cd5-a5de-11f1-9275-e0d55e5927b4',135,NULL,'S6a968bc7de048',NULL,NULL,'enrolled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-01','active',NULL,NULL,NULL,'2026-09-01 14:24:39','2026-09-01 14:24:39','Place Test','Place Test'),(434,NULL,'6526095170','259224',0,'Place','6a968bc8df1c4','93faf14b-a5de-11f1-9275-e0d55e5927b4',138,NULL,'S6a968bc8df1e6',NULL,NULL,'enrolled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-01','active',NULL,NULL,NULL,'2026-09-01 14:24:40','2026-09-01 14:24:40','Place 6a968bc8df1c4','Place 6a968bc8df1c4'),(435,NULL,'2326099650','579565',0,'Test','Student','373020e2-a5e0-11f1-9275-e0d55e5927b4',216,NULL,'S6a968e8837b60',NULL,NULL,'enrolled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-01','active',NULL,NULL,NULL,'2026-09-01 14:36:24','2026-09-01 14:36:24','Test Student','Test Student'),(436,NULL,'1726099150','776459',0,'Place','Test','379d9204-a5e0-11f1-9275-e0d55e5927b4',218,NULL,'S6a968e88e69d9',NULL,NULL,'enrolled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-01','active',NULL,NULL,NULL,'2026-09-01 14:36:24','2026-09-01 14:36:24','Place Test','Place Test'),(437,NULL,'6926091460','814911',0,'Place','6a968e8aac933','38a9ed26-a5e0-11f1-9275-e0d55e5927b4',223,NULL,'S6a968e8aac953',NULL,NULL,'enrolled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Bangladeshi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-01','active',NULL,NULL,NULL,'2026-09-01 14:36:26','2026-09-01 14:36:26','Place 6a968e8aac933','Place 6a968e8aac933');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subject_academic_assignments`
--

DROP TABLE IF EXISTS `subject_academic_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subject_academic_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `subject_id` bigint(20) unsigned NOT NULL,
  `class_grade_id` bigint(20) unsigned NOT NULL,
  `academic_group_id` bigint(20) unsigned DEFAULT NULL,
  `requirement_type` varchar(20) NOT NULL DEFAULT 'mandatory',
  `selection_group_id` bigint(20) unsigned DEFAULT NULL,
  `credit_hours` decimal(5,2) DEFAULT NULL,
  `gpa_included` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `group_key` bigint(20) unsigned GENERATED ALWAYS AS (ifnull(`academic_group_id`,0)) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `saa_subject_class_group_unique` (`subject_id`,`class_grade_id`,`group_key`),
  KEY `subject_academic_assignments_academic_group_id_foreign` (`academic_group_id`),
  KEY `saa_class_group_status_idx` (`class_grade_id`,`academic_group_id`,`status`),
  KEY `subject_academic_assignments_status_index` (`status`),
  KEY `subject_academic_assignments_selection_group_id_foreign` (`selection_group_id`),
  KEY `saa_requirement_type_idx` (`class_grade_id`,`requirement_type`),
  CONSTRAINT `subject_academic_assignments_academic_group_id_foreign` FOREIGN KEY (`academic_group_id`) REFERENCES `academic_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subject_academic_assignments_class_grade_id_foreign` FOREIGN KEY (`class_grade_id`) REFERENCES `class_grades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subject_academic_assignments_selection_group_id_foreign` FOREIGN KEY (`selection_group_id`) REFERENCES `academic_selection_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `subject_academic_assignments_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subject_academic_assignments`
--

LOCK TABLES `subject_academic_assignments` WRITE;
/*!40000 ALTER TABLE `subject_academic_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `subject_academic_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subject_requests`
--

DROP TABLE IF EXISTS `subject_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subject_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `subject_type` varchar(50) NOT NULL DEFAULT 'professional',
  `name` varchar(255) NOT NULL,
  `short_name` varchar(100) DEFAULT NULL,
  `subject_code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `review_note` text DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject_requests_institute_id_status_index` (`institute_id`,`status`),
  KEY `subject_requests_institute_id_index` (`institute_id`),
  KEY `subject_requests_category_id_index` (`category_id`),
  KEY `subject_requests_status_index` (`status`),
  CONSTRAINT `subject_requests_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subject_requests`
--

LOCK TABLES `subject_requests` WRITE;
/*!40000 ALTER TABLE `subject_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `subject_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `subject_type` enum('professional','academic') NOT NULL DEFAULT 'professional',
  `subject_code` varchar(40) NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `short_name` varchar(60) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','active','inactive') NOT NULL DEFAULT 'active',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subjects_institute_code` (`institute_id`,`subject_code`),
  UNIQUE KEY `uq_subjects_institute_slug` (`institute_id`,`slug`),
  KEY `idx_subjects_institute` (`institute_id`),
  KEY `idx_subjects_category` (`category_id`),
  KEY `idx_subjects_status` (`status`),
  CONSTRAINT `fk_subjects_category` FOREIGN KEY (`category_id`) REFERENCES `course_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_subjects_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscription_packages`
--

DROP TABLE IF EXISTS `subscription_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscription_packages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `price_monthly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_yearly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_students` int(10) unsigned DEFAULT NULL,
  `max_teachers` int(10) unsigned DEFAULT NULL,
  `max_courses` int(10) unsigned DEFAULT NULL,
  `max_branches` int(10) unsigned DEFAULT NULL,
  `storage_limit_mb` int(10) unsigned DEFAULT NULL,
  `sms_limit_monthly` int(10) unsigned DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subscription_packages_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscription_packages`
--

LOCK TABLES `subscription_packages` WRITE;
/*!40000 ALTER TABLE `subscription_packages` DISABLE KEYS */;
INSERT INTO `subscription_packages` VALUES (2,'BASIC','BASIC',1500.00,15000.00,300,10,15,2,2000,200,NULL,0,'active','2026-08-05 02:14:56','2026-08-23 11:54:51'),(3,'ADVANCED','ADVANCED',4000.00,40000.00,1500,40,60,5,10000,1000,NULL,0,'active','2026-08-05 02:14:56','2026-08-23 11:54:51'),(4,'PREMIUM','PREMIUM',9000.00,90000.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'active','2026-08-05 02:14:56','2026-08-23 11:54:51'),(5,'FREE','FREE',0.00,0.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'active','2026-08-23 11:54:51','2026-08-23 11:54:51');
/*!40000 ALTER TABLE `subscription_packages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_credit_balances`
--

DROP TABLE IF EXISTS `supplier_credit_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_credit_balances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `purchase_return_id` bigint(20) unsigned NOT NULL,
  `credit_amount` decimal(19,4) NOT NULL,
  `used_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `remaining_amount` decimal(19,4) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_credit_balances_branch_id_foreign` (`branch_id`),
  KEY `supplier_credit_balances_supplier_id_foreign` (`supplier_id`),
  KEY `supplier_credit_balances_purchase_return_id_foreign` (`purchase_return_id`),
  KEY `idx_scb_supplier` (`institute_id`,`supplier_id`,`status`),
  CONSTRAINT `supplier_credit_balances_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_credit_balances_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `supplier_credit_balances_purchase_return_id_foreign` FOREIGN KEY (`purchase_return_id`) REFERENCES `purchase_returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `supplier_credit_balances_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `parties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_credit_balances`
--

LOCK TABLES `supplier_credit_balances` WRITE;
/*!40000 ALTER TABLE `supplier_credit_balances` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_credit_balances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_refunds`
--

DROP TABLE IF EXISTS `supplier_refunds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_refunds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `purchase_return_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(19,4) NOT NULL,
  `refund_method` varchar(20) NOT NULL DEFAULT 'cash',
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_refunds_branch_id_foreign` (`branch_id`),
  KEY `supplier_refunds_supplier_id_foreign` (`supplier_id`),
  KEY `supplier_refunds_purchase_return_id_foreign` (`purchase_return_id`),
  KEY `supplier_refunds_journal_id_foreign` (`journal_id`),
  KEY `idx_sr_supplier` (`institute_id`,`supplier_id`),
  CONSTRAINT `supplier_refunds_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_refunds_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `supplier_refunds_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_refunds_purchase_return_id_foreign` FOREIGN KEY (`purchase_return_id`) REFERENCES `purchase_returns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_refunds_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `parties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_refunds`
--

LOCK TABLES `supplier_refunds` WRITE;
/*!40000 ALTER TABLE `supplier_refunds` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_refunds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_backups`
--

DROP TABLE IF EXISTS `system_backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_backups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `path` varchar(500) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `checksum` varchar(64) DEFAULT NULL,
  `type` enum('manual','pre_restore','pre_orphan_cleanup','scheduled','health_check','daily','weekly') NOT NULL DEFAULT 'manual',
  `status` enum('pending','completed','failed','verified') NOT NULL DEFAULT 'pending',
  `migration_count` int(10) unsigned NOT NULL DEFAULT 0,
  `migration_version` varchar(100) DEFAULT NULL,
  `table_count` bigint(20) unsigned NOT NULL DEFAULT 0,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_by_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `system_backups_type_status_index` (`type`,`status`),
  KEY `system_backups_created_at_index` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_backups`
--

LOCK TABLES `system_backups` WRITE;
/*!40000 ALTER TABLE `system_backups` DISABLE KEYS */;
INSERT INTO `system_backups` VALUES (7,'monetix_manual_20260901_143235.sql','backups/monetix_manual_20260901_143235.sql',850993,'1347efc8a77e63a681e4ed076ab09432e2a8e909e33f51e86f96d5e4b5ffe243','manual','verified',323,'2026_11_14_000005_merge_student_enrollments_into_enrollments',304,'{\"db\":\"monetix_test\",\"generated_at\":\"2026-09-01T14:32:40+06:00\",\"driver\":\"mysqldump\"}',NULL,'user','2026-09-01 08:32:40','2026-09-01 08:32:40'),(8,'monetix_manual_20260901_143241.sql','backups/monetix_manual_20260901_143241.sql',852720,'4ec58bb02bc22b2e5b2ba1ce49d0363d94d9643ae2410ef9249abbda99fbaabd','manual','verified',323,'2026_11_14_000005_merge_student_enrollments_into_enrollments',304,'{\"db\":\"monetix_test\",\"generated_at\":\"2026-09-01T14:32:47+06:00\",\"driver\":\"mysqldump\"}',NULL,'user','2026-09-01 08:32:47','2026-09-01 08:32:47'),(9,'monetix_manual_20260901_143247.sql','backups/monetix_manual_20260901_143247.sql',854344,'6760420022782a4e2d297fa30ecf1261c80f21b5a84170625915d446ad2c7366','manual','verified',323,'2026_11_14_000005_merge_student_enrollments_into_enrollments',304,'{\"db\":\"monetix_test\",\"generated_at\":\"2026-09-01T14:32:53+06:00\",\"driver\":\"mysqldump\"}',NULL,'user','2026-09-01 08:32:53','2026-09-01 08:32:53');
/*!40000 ALTER TABLE `system_backups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_health_audits`
--

DROP TABLE IF EXISTS `system_health_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_health_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `status` enum('healthy','warning','critical') NOT NULL DEFAULT 'healthy',
  `score` tinyint(3) unsigned NOT NULL DEFAULT 100,
  `checks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`checks`)),
  `missing_tables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`missing_tables`)),
  `missing_seeds` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`missing_seeds`)),
  `orphans` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`orphans`)),
  `missing_indexes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`missing_indexes`)),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `system_health_audits_created_at_index` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_health_audits`
--

LOCK TABLES `system_health_audits` WRITE;
/*!40000 ALTER TABLE `system_health_audits` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_health_audits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_recovery_times`
--

DROP TABLE IF EXISTS `system_recovery_times`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_recovery_times` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `backup_id` bigint(20) unsigned DEFAULT NULL,
  `backup_preparation_ms` int(10) unsigned DEFAULT 0,
  `verification_ms` int(10) unsigned DEFAULT 0,
  `schema_validation_ms` int(10) unsigned DEFAULT 0,
  `simulated_restore_ms` int(10) unsigned DEFAULT 0,
  `total_ms` int(10) unsigned DEFAULT 0,
  `temp_database` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_recovery_times`
--

LOCK TABLES `system_recovery_times` WRITE;
/*!40000 ALTER TABLE `system_recovery_times` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_recovery_times` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_schema_versions`
--

DROP TABLE IF EXISTS `system_schema_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_schema_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(100) DEFAULT NULL,
  `database_version` varchar(50) DEFAULT NULL,
  `laravel_version` varchar(50) DEFAULT NULL,
  `migration_count` int(10) unsigned NOT NULL DEFAULT 0,
  `checksum` varchar(64) DEFAULT NULL,
  `installed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `system_schema_versions_version_index` (`version`),
  KEY `system_schema_versions_installed_at_index` (`installed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_schema_versions`
--

LOCK TABLES `system_schema_versions` WRITE;
/*!40000 ALTER TABLE `system_schema_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_schema_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_seed_versions`
--

DROP TABLE IF EXISTS `system_seed_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_seed_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `seed_name` varchar(100) NOT NULL,
  `version` varchar(50) NOT NULL DEFAULT '1',
  `checksum` varchar(64) DEFAULT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_seed_versions_seed_name_version_unique` (`seed_name`,`version`),
  KEY `system_seed_versions_seed_name_index` (`seed_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_seed_versions`
--

LOCK TABLES `system_seed_versions` WRITE;
/*!40000 ALTER TABLE `system_seed_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_seed_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_audit_logs`
--

DROP TABLE IF EXISTS `tax_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(50) NOT NULL,
  `actor_type` varchar(50) DEFAULT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_audit_logs_branch_id_foreign` (`branch_id`),
  KEY `tax_audit_logs_institute_id_entity_type_entity_id_index` (`institute_id`,`entity_type`,`entity_id`),
  CONSTRAINT `tax_audit_logs_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_audit_logs_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_audit_logs`
--

LOCK TABLES `tax_audit_logs` WRITE;
/*!40000 ALTER TABLE `tax_audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `tax_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_groups`
--

DROP TABLE IF EXISTS `tax_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('vat','sales_tax','withholding','custom') NOT NULL DEFAULT 'vat',
  `rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `is_compound` tinyint(1) NOT NULL DEFAULT 0,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_groups_branch_id_foreign` (`branch_id`),
  KEY `idx_tax_groups_institute` (`institute_id`,`is_active`),
  CONSTRAINT `tax_groups_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_groups_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_groups`
--

LOCK TABLES `tax_groups` WRITE;
/*!40000 ALTER TABLE `tax_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `tax_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_jurisdictions`
--

DROP TABLE IF EXISTS `tax_jurisdictions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_jurisdictions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(30) NOT NULL,
  `country_iso2` char(2) NOT NULL,
  `state_code` varchar(30) DEFAULT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jurisdiction_code` (`institute_id`,`branch_id`,`code`),
  KEY `tax_jurisdictions_branch_id_foreign` (`branch_id`),
  KEY `tax_jurisdictions_parent_id_foreign` (`parent_id`),
  KEY `tax_jurisdictions_institute_id_country_iso2_index` (`institute_id`,`country_iso2`),
  CONSTRAINT `tax_jurisdictions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_jurisdictions_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tax_jurisdictions_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `tax_jurisdictions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_jurisdictions`
--

LOCK TABLES `tax_jurisdictions` WRITE;
/*!40000 ALTER TABLE `tax_jurisdictions` DISABLE KEYS */;
/*!40000 ALTER TABLE `tax_jurisdictions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_rate_history`
--

DROP TABLE IF EXISTS `tax_rate_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_rate_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `tax_rate_id` bigint(20) unsigned NOT NULL,
  `old_rate` decimal(10,4) NOT NULL,
  `new_rate` decimal(10,4) NOT NULL,
  `changed_at` date NOT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_rate_history_institute_id_foreign` (`institute_id`),
  KEY `tax_rate_history_tax_rate_id_foreign` (`tax_rate_id`),
  CONSTRAINT `tax_rate_history_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tax_rate_history_tax_rate_id_foreign` FOREIGN KEY (`tax_rate_id`) REFERENCES `tax_rates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_rate_history`
--

LOCK TABLES `tax_rate_history` WRITE;
/*!40000 ALTER TABLE `tax_rate_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `tax_rate_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_rates`
--

DROP TABLE IF EXISTS `tax_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_rates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `jurisdiction_id` bigint(20) unsigned DEFAULT NULL,
  `tax_group_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('vat','sales_tax','withholding','excise','custom') NOT NULL DEFAULT 'vat',
  `rate_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `is_compound` tinyint(1) NOT NULL DEFAULT 0,
  `is_inclusive` tinyint(1) NOT NULL DEFAULT 0,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_rates_branch_id_foreign` (`branch_id`),
  KEY `tax_rates_jurisdiction_id_foreign` (`jurisdiction_id`),
  KEY `tax_rates_tax_group_id_foreign` (`tax_group_id`),
  KEY `idx_tax_rates_resolve` (`institute_id`,`jurisdiction_id`,`is_active`),
  KEY `idx_tax_rates_group` (`institute_id`,`tax_group_id`),
  CONSTRAINT `tax_rates_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rates_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tax_rates_jurisdiction_id_foreign` FOREIGN KEY (`jurisdiction_id`) REFERENCES `tax_jurisdictions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rates_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_rates`
--

LOCK TABLES `tax_rates` WRITE;
/*!40000 ALTER TABLE `tax_rates` DISABLE KEYS */;
/*!40000 ALTER TABLE `tax_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_return_lines`
--

DROP TABLE IF EXISTS `tax_return_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_return_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `tax_return_id` bigint(20) unsigned NOT NULL,
  `tax_rate_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(200) NOT NULL,
  `total_sales` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `total_purchases` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `tax_collected` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `tax_paid` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `net_tax` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_return_lines_institute_id_foreign` (`institute_id`),
  KEY `tax_return_lines_tax_return_id_foreign` (`tax_return_id`),
  KEY `tax_return_lines_tax_rate_id_foreign` (`tax_rate_id`),
  CONSTRAINT `tax_return_lines_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tax_return_lines_tax_rate_id_foreign` FOREIGN KEY (`tax_rate_id`) REFERENCES `tax_rates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_return_lines_tax_return_id_foreign` FOREIGN KEY (`tax_return_id`) REFERENCES `tax_return_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_return_lines`
--

LOCK TABLES `tax_return_lines` WRITE;
/*!40000 ALTER TABLE `tax_return_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `tax_return_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_return_periods`
--

DROP TABLE IF EXISTS `tax_return_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_return_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `jurisdiction_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('open','filed','overdue') NOT NULL DEFAULT 'open',
  `total_sales` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `total_purchases` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `tax_collected` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `tax_paid` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `net_tax` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_return_periods_branch_id_foreign` (`branch_id`),
  KEY `tax_return_periods_jurisdiction_id_foreign` (`jurisdiction_id`),
  KEY `tax_return_periods_journal_id_foreign` (`journal_id`),
  KEY `tax_return_periods_institute_id_jurisdiction_id_status_index` (`institute_id`,`jurisdiction_id`,`status`),
  CONSTRAINT `tax_return_periods_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_return_periods_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tax_return_periods_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_return_periods_jurisdiction_id_foreign` FOREIGN KEY (`jurisdiction_id`) REFERENCES `tax_jurisdictions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_return_periods`
--

LOCK TABLES `tax_return_periods` WRITE;
/*!40000 ALTER TABLE `tax_return_periods` DISABLE KEYS */;
/*!40000 ALTER TABLE `tax_return_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_rules`
--

DROP TABLE IF EXISTS `tax_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `jurisdiction_id` bigint(20) unsigned DEFAULT NULL,
  `tax_rate_id` bigint(20) unsigned NOT NULL,
  `item_type` varchar(50) NOT NULL DEFAULT '*',
  `product_category` varchar(50) NOT NULL DEFAULT '*',
  `tax_group_id` bigint(20) unsigned DEFAULT NULL,
  `priority` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_rules_branch_id_foreign` (`branch_id`),
  KEY `tax_rules_jurisdiction_id_foreign` (`jurisdiction_id`),
  KEY `tax_rules_tax_rate_id_foreign` (`tax_rate_id`),
  KEY `tax_rules_tax_group_id_foreign` (`tax_group_id`),
  KEY `idx_tax_rules_resolve` (`institute_id`,`jurisdiction_id`,`is_active`),
  CONSTRAINT `tax_rules_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rules_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tax_rules_jurisdiction_id_foreign` FOREIGN KEY (`jurisdiction_id`) REFERENCES `tax_jurisdictions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rules_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_rules_tax_rate_id_foreign` FOREIGN KEY (`tax_rate_id`) REFERENCES `tax_rates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_rules`
--

LOCK TABLES `tax_rules` WRITE;
/*!40000 ALTER TABLE `tax_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `tax_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_academic_assignments`
--

DROP TABLE IF EXISTS `teacher_academic_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_academic_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `institute_user_id` bigint(20) unsigned NOT NULL,
  `academic_year_id` bigint(20) unsigned DEFAULT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `class_grade_id` bigint(20) unsigned DEFAULT NULL,
  `academic_group_id` bigint(20) unsigned DEFAULT NULL,
  `responsibility` enum('course_instructor','subject_teacher','class_teacher','batch_coordinator','practical_instructor','examiner') NOT NULL DEFAULT 'subject_teacher',
  `status` enum('active','completed') NOT NULL DEFAULT 'active',
  `assigned_at` date DEFAULT NULL,
  `completed_at` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_teacher_assign_inst_branch` (`institute_id`,`branch_id`),
  KEY `idx_teacher_assign_inst_teacher` (`institute_id`,`institute_user_id`),
  KEY `idx_teacher_assign_inst_year` (`institute_id`,`academic_year_id`),
  KEY `idx_teacher_assign_inst_status` (`institute_id`,`status`),
  KEY `idx_teacher_assign_inst_course` (`institute_id`,`course_id`),
  KEY `idx_teacher_assign_inst_subject` (`institute_id`,`subject_id`),
  KEY `idx_teacher_assign_inst_batch` (`institute_id`,`batch_id`),
  KEY `fk_teacher_assign_branch` (`branch_id`),
  KEY `fk_teacher_assign_user` (`institute_user_id`),
  KEY `fk_teacher_assign_year` (`academic_year_id`),
  KEY `fk_teacher_assign_course` (`course_id`),
  KEY `fk_teacher_assign_subject` (`subject_id`),
  KEY `fk_teacher_assign_batch` (`batch_id`),
  KEY `fk_teacher_assign_class` (`class_grade_id`),
  KEY `fk_teacher_assign_group` (`academic_group_id`),
  KEY `fk_teacher_assign_created_by` (`created_by`),
  KEY `fk_teacher_assign_updated_by` (`updated_by`),
  CONSTRAINT `fk_teacher_assign_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_teacher_assign_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_teacher_assign_class` FOREIGN KEY (`class_grade_id`) REFERENCES `class_grades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_teacher_assign_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_teacher_assign_created_by` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_teacher_assign_group` FOREIGN KEY (`academic_group_id`) REFERENCES `academic_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_teacher_assign_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_assign_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_teacher_assign_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_teacher_assign_user` FOREIGN KEY (`institute_user_id`) REFERENCES `institute_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_assign_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `teacher_academic_assignments_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_academic_assignments`
--

LOCK TABLES `teacher_academic_assignments` WRITE;
/*!40000 ALTER TABLE `teacher_academic_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `teacher_academic_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_code_sequences`
--

DROP TABLE IF EXISTS `teacher_code_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_code_sequences` (
  `institute_id` bigint(20) unsigned NOT NULL,
  `last_sequence` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`institute_id`),
  CONSTRAINT `fk_teacher_code_seq_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_code_sequences`
--

LOCK TABLES `teacher_code_sequences` WRITE;
/*!40000 ALTER TABLE `teacher_code_sequences` DISABLE KEYS */;
/*!40000 ALTER TABLE `teacher_code_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_profiles`
--

DROP TABLE IF EXISTS `teacher_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `institute_id` bigint(20) unsigned NOT NULL,
  `institute_user_id` bigint(20) unsigned NOT NULL,
  `specialization` varchar(150) DEFAULT NULL,
  `experience_years` smallint(5) unsigned DEFAULT NULL,
  `employment_type` enum('full_time','part_time','contractual','adjunct','volunteer') DEFAULT NULL,
  `employment_status` enum('active','inactive','suspended','resigned','terminated','on_leave') NOT NULL DEFAULT 'active',
  `date_of_birth` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(120) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`skills`)),
  `languages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`languages`)),
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_profiles_user` (`institute_user_id`),
  KEY `idx_teacher_profiles_institute` (`institute_id`),
  KEY `fk_teacher_profiles_created_by` (`created_by`),
  KEY `fk_teacher_profiles_updated_by` (`updated_by`),
  CONSTRAINT `fk_teacher_profiles_created_by` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_teacher_profiles_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_profiles_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_teacher_profiles_user` FOREIGN KEY (`institute_user_id`) REFERENCES `institute_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_profiles`
--

LOCK TABLES `teacher_profiles` WRITE;
/*!40000 ALTER TABLE `teacher_profiles` DISABLE KEYS */;
/*!40000 ALTER TABLE `teacher_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_deletion_requests`
--

DROP TABLE IF EXISTS `tenant_deletion_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_deletion_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `deletable_type` varchar(100) NOT NULL,
  `deletable_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `requested_by` bigint(20) unsigned NOT NULL,
  `requested_by_type` varchar(50) NOT NULL DEFAULT 'user',
  `reason` varchar(500) DEFAULT NULL,
  `confirmation_token` varchar(64) NOT NULL,
  `status` enum('pending','confirmed','cancelled','expired') NOT NULL DEFAULT 'pending',
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_deletion_requests_confirmation_token_unique` (`confirmation_token`),
  KEY `tenant_deletion_requests_deletable_type_deletable_id_index` (`deletable_type`,`deletable_id`),
  KEY `tenant_deletion_requests_institute_id_status_index` (`institute_id`,`status`),
  KEY `tenant_deletion_requests_expires_at_index` (`expires_at`),
  CONSTRAINT `tenant_deletion_requests_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_deletion_requests`
--

LOCK TABLES `tenant_deletion_requests` WRITE;
/*!40000 ALTER TABLE `tenant_deletion_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_deletion_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_recovery_archives`
--

DROP TABLE IF EXISTS `tenant_recovery_archives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_recovery_archives` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `archivable_type` varchar(100) NOT NULL,
  `archivable_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`snapshot`)),
  `archived_by` bigint(20) unsigned DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_recovery_archives_archivable_type_archivable_id_index` (`archivable_type`,`archivable_id`),
  KEY `tenant_recovery_archives_institute_id_index` (`institute_id`),
  CONSTRAINT `tenant_recovery_archives_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_recovery_archives`
--

LOCK TABLES `tenant_recovery_archives` WRITE;
/*!40000 ALTER TABLE `tenant_recovery_archives` DISABLE KEYS */;
/*!40000 ALTER TABLE `tenant_recovery_archives` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `themes`
--

DROP TABLE IF EXISTS `themes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `themes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `primary_color` varchar(10) NOT NULL DEFAULT '#0D6EFD',
  `secondary_color` varchar(10) NOT NULL DEFAULT '#FFC107',
  `is_dark` tinyint(1) NOT NULL DEFAULT 0,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_themes_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `themes`
--

LOCK TABLES `themes` WRITE;
/*!40000 ALTER TABLE `themes` DISABLE KEYS */;
INSERT INTO `themes` (`id`, `name`, `slug`, `primary_color`, `secondary_color`, `is_dark`, `is_default`, `status`, `created_at`, `updated_at`) VALUES
(1,'Default Blue','default-blue','#0D6EFD','#FFC107',0,1,'active','2026-08-28 10:35:00','2026-08-28 10:35:00'),
(2,'Forest Green','forest-green','#198754','#FFC107',0,0,'active','2026-08-28 10:35:00','2026-08-28 10:35:00'),
(3,'Royal Purple','royal-purple','#6F42C1','#E83E8C',0,0,'active','2026-08-28 10:35:00','2026-08-28 10:35:00'),
(4,'Sunset Orange','sunset-orange','#FD7E14','#FFC107',0,0,'active','2026-08-28 10:35:00','2026-08-28 10:35:00'),
(5,'Dark Mode','dark-mode','#343A40','#0DCAF0',1,0,'active','2026-08-28 10:35:00','2026-08-28 10:35:00'),
(6,'Crimson Red','crimson-red','#DC3545','#FFC107',0,0,'active','2026-08-28 10:35:00','2026-08-28 10:35:00'),
(7,'Ocean Teal','ocean-teal','#20C997','#0D6EFD',0,0,'active','2026-08-28 10:35:00','2026-08-28 10:35:00');
/*!40000 ALTER TABLE `themes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_batch_results`
--

DROP TABLE IF EXISTS `training_batch_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_batch_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `total_marks` decimal(8,2) NOT NULL DEFAULT 0.00,
  `obtained_marks` decimal(8,2) NOT NULL DEFAULT 0.00,
  `percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `status` enum('pass','fail') NOT NULL DEFAULT 'fail',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_batch_results_batch_id_student_id_unique` (`batch_id`,`student_id`),
  KEY `training_batch_results_institute_id_foreign` (`institute_id`),
  KEY `training_batch_results_student_id_foreign` (`student_id`),
  CONSTRAINT `training_batch_results_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_batch_results_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_batch_results_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_batch_results`
--

LOCK TABLES `training_batch_results` WRITE;
/*!40000 ALTER TABLE `training_batch_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `training_batch_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `account_head_id` bigint(20) unsigned NOT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('income','expense') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference_no` varchar(60) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transactions_institute_date` (`institute_id`,`transaction_date`),
  KEY `idx_transactions_account_head` (`account_head_id`),
  KEY `fk_transactions_branch` (`branch_id`),
  KEY `fk_transactions_payment` (`payment_id`),
  KEY `fk_transactions_created_by` (`created_by`),
  CONSTRAINT `fk_transactions_account_head` FOREIGN KEY (`account_head_id`) REFERENCES `account_heads` (`id`),
  CONSTRAINT `fk_transactions_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_transactions_created_by` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_transactions_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_transactions_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_module_access`
--

DROP TABLE IF EXISTS `user_module_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_module_access` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `user_type` varchar(30) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `module_key` varchar(60) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_module_access` (`institute_id`,`user_type`,`user_id`,`module_key`),
  KEY `user_module_access_institute_id_module_key_index` (`institute_id`,`module_key`),
  KEY `user_module_access_user_type_user_id_index` (`user_type`,`user_id`),
  CONSTRAINT `user_module_access_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_module_access`
--

LOCK TABLES `user_module_access` WRITE;
/*!40000 ALTER TABLE `user_module_access` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_module_access` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `is_test` tinyint(1) DEFAULT 0 COMMENT 'Explicit test/demo marker; NULL/false = PROTECTED',
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `uid` varchar(10) DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `first_name` varchar(60) DEFAULT NULL,
  `last_name` varchar(60) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `preferred_language` varchar(10) NOT NULL DEFAULT 'en',
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `photo` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `pending_email` varchar(150) DEFAULT NULL,
  `pending_email_token_hash` varchar(255) DEFAULT NULL,
  `pending_email_expires_at` timestamp NULL DEFAULT NULL,
  `pending_phone` varchar(20) DEFAULT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `preferred_2fa_method` varchar(20) DEFAULT NULL,
  `sms_2fa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `email_2fa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `remember_token` varchar(100) DEFAULT NULL,
  `failed_login_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `inactivity_warning_sent_at` timestamp NULL DEFAULT NULL,
  `inactivity_final_warning_sent_at` timestamp NULL DEFAULT NULL,
  `inactivity_deleted_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `account_type` enum('owner','staff') NOT NULL DEFAULT 'owner',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_uuid_unique` (`uuid`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`),
  UNIQUE KEY `users_uid_unique` (`uid`),
  KEY `users_last_login_at_index` (`last_login_at`),
  KEY `users_inactivity_warning_sent_at_index` (`inactivity_warning_sent_at`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (17,0,'72dc6282-a5de-11f1-9275-e0d55e5927b4',NULL,'Test User','Gudrun','Waters','test@example.com',NULL,'en',NULL,NULL,'2026-09-01 08:23:45',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'sqa2EnkWdW',0,NULL,NULL,NULL,NULL,NULL,NULL,'$2y$04$YbkdJH/AIvqqQllTrfpOjOy/YpSsLIOHdCyTBrRw9hzV0IvsxJt1G','active','owner',NULL,'2026-09-01 14:23:45','2026-09-01 14:23:45');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workflow_histories`
--

DROP TABLE IF EXISTS `workflow_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workflow_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workflow_id` bigint(20) unsigned NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `from_status` varchar(40) DEFAULT NULL,
  `to_status` varchar(40) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `workflow_histories_actor_id_foreign` (`actor_id`),
  KEY `idx_workflow_histories_workflow` (`workflow_id`),
  CONSTRAINT `workflow_histories_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `workflow_histories_workflow_id_foreign` FOREIGN KEY (`workflow_id`) REFERENCES `workflows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workflow_histories`
--

LOCK TABLES `workflow_histories` WRITE;
/*!40000 ALTER TABLE `workflow_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `workflow_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workflow_steps`
--

DROP TABLE IF EXISTS `workflow_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workflow_steps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workflow_id` bigint(20) unsigned NOT NULL,
  `step_order` int(10) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `responsible_role` varchar(80) DEFAULT NULL,
  `responsible_permission` varchar(80) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'pending',
  `acted_by` bigint(20) unsigned DEFAULT NULL,
  `acted_at` timestamp NULL DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `workflow_steps_acted_by_foreign` (`acted_by`),
  KEY `idx_workflow_steps_order` (`workflow_id`,`step_order`),
  CONSTRAINT `workflow_steps_acted_by_foreign` FOREIGN KEY (`acted_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `workflow_steps_workflow_id_foreign` FOREIGN KEY (`workflow_id`) REFERENCES `workflows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workflow_steps`
--

LOCK TABLES `workflow_steps` WRITE;
/*!40000 ALTER TABLE `workflow_steps` DISABLE KEYS */;
/*!40000 ALTER TABLE `workflow_steps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workflows`
--

DROP TABLE IF EXISTS `workflows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workflows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `workflow_type` varchar(80) NOT NULL,
  `title` varchar(255) NOT NULL,
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `entity_type` varchar(120) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'draft',
  `current_step` int(10) unsigned NOT NULL DEFAULT 1,
  `initiated_by` bigint(20) unsigned DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `workflows_initiated_by_foreign` (`initiated_by`),
  KEY `workflows_assigned_to_foreign` (`assigned_to`),
  KEY `idx_workflows_institute_status` (`institute_id`,`status`),
  KEY `idx_workflows_institute_type` (`institute_id`,`workflow_type`),
  KEY `idx_workflows_student` (`student_id`),
  KEY `idx_workflows_branch` (`branch_id`),
  CONSTRAINT `workflows_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `workflows_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `workflows_initiated_by_foreign` FOREIGN KEY (`initiated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `workflows_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `workflows_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workflows`
--

LOCK TABLES `workflows` WRITE;
/*!40000 ALTER TABLE `workflows` DISABLE KEYS */;
/*!40000 ALTER TABLE `workflows` ENABLE KEYS */;
UNLOCK TABLES;
INSERT IGNORE INTO `institutes` (`id`,`uid`,`is_test`,`uuid`,`name`,`founded_year`,`industry`,`sub_industry`,`short_name`,`slug`,`logo`,`cover_photo`,`logo_path`,`description`,`address`,`country`,`country_id`,`division`,`district`,`upazila`,`admin_level_1_id`,`admin_level_2_id`,`admin_level_3_id`,`institute_code`,`postal_code`,`phone`,`whatsapp`,`email`,`website`,`facebook`,`youtube`,`google_map_url`,`license_number`,`trade_license`,`registration_number`,`e_tin`,`package_id`,`subscription_expiry`,`status`,`verified`,`onboarded_at`,`deleted_at`,`deleted_by`,`created_at`,`updated_at`,`deletion_requested_at`,`deletion_requested_by`) VALUES (42,'9BO68J7541',0,'5807b0eb-a2d7-11f1-8813-e0d55e5927b4','Mawa Academy',NULL,'training_center','training_institute',NULL,'mawa-academy',NULL,NULL,NULL,NULL,NULL,'Bangladesh',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active',0,NULL,NULL,NULL,'2026-08-28 11:55:20','2026-08-31 20:35:33',NULL,NULL);

-- Ensure currencies exist (encoding-safe INSERT for parallel DB compatibility)
INSERT IGNORE INTO `currencies` (`code`,`name`,`symbol`,`decimal_places`,`is_base`,`is_active`,`created_at`,`updated_at`) VALUES
('BDT','Bangladeshi Taka',0x09F3,2,0,1,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
('USD','US Dollar','$',2,0,1,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
('INR','Indian Rupee',0x20B9,2,0,1,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
('PKR','Pakistani Rupee',0x20A8,2,0,1,'2026-08-28 10:35:00','2026-08-28 10:35:00'),
('EUR','Euro',0x20AC,2,0,1,'2026-08-28 10:35:00','2026-08-28 10:35:00');

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-01 14:51:14
