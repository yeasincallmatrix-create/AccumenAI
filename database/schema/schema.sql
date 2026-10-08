/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  CONSTRAINT `academic_result_aggregation_items_academic_assessment_id_foreign` FOREIGN KEY (`academic_assessment_id`) REFERENCES `academic_assessments` (`id`),
  CONSTRAINT `academic_result_aggregation_items_scheme_id_foreign` FOREIGN KEY (`scheme_id`) REFERENCES `academic_result_aggregation_schemes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
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
  `institute_key` bigint(20) GENERATED ALWAYS AS (coalesce(`institute_id`,0)) VIRTUAL,
  `branch_key` bigint(20) GENERATED ALWAYS AS (coalesce(`branch_id`,0)) VIRTUAL,
  `alive` bigint(20) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_account_groups_code_v3` (`institute_key`,`branch_key`,`code`,`alive`),
  KEY `account_groups_branch_id_foreign` (`branch_id`),
  KEY `account_groups_parent_id_foreign` (`parent_id`),
  KEY `idx_groups_tenant_global` (`institute_id`,`is_system`),
  CONSTRAINT `account_groups_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `account_groups_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_groups_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `account_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_account_groups_alive_ins` BEFORE INSERT ON `account_groups` FOR EACH ROW SET NEW.alive = IF(NEW.deleted_at IS NULL, 1, -NEW.id) */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_account_groups_alive_upd` BEFORE UPDATE ON `account_groups` FOR EACH ROW SET NEW.alive = IF(NEW.deleted_at IS NULL, 1, -OLD.id) */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
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
DROP TABLE IF EXISTS `accounting_audit_trails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounting_audit_trails` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `actor_type` enum('user','system','ai','cron','import','guest') NOT NULL DEFAULT 'user',
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `entity_type` varchar(60) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `bed_id` bigint(20) unsigned DEFAULT NULL,
  `admitting_doctor_id` bigint(20) unsigned DEFAULT NULL,
  `admission_date` date NOT NULL,
  `admission_time` time NOT NULL,
  `primary_diagnosis` text DEFAULT NULL,
  `secondary_diagnosis` text DEFAULT NULL,
  `status` enum('active','discharged','transferred','expired') NOT NULL DEFAULT 'active',
  `discharge_date` date DEFAULT NULL,
  `discharge_time` time DEFAULT NULL,
  `discharge_summary` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `discharged_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admissions_bed_id_foreign` (`bed_id`),
  KEY `admissions_discharged_by_foreign` (`discharged_by`),
  KEY `admissions_institute_id_index` (`institute_id`),
  KEY `admissions_patient_id_index` (`patient_id`),
  KEY `admissions_status_index` (`status`),
  KEY `admissions_admission_date_index` (`admission_date`),
  KEY `admissions_admitting_doctor_id_foreign` (`admitting_doctor_id`),
  KEY `admissions_branch_id_index` (`branch_id`),
  CONSTRAINT `admissions_admitting_doctor_id_foreign` FOREIGN KEY (`admitting_doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admissions_bed_id_foreign` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admissions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admissions_discharged_by_foreign` FOREIGN KEY (`discharged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admissions_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admissions_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `advance_tax_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `advance_tax_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `country_code` char(2) NOT NULL,
  `currency_code` char(3) NOT NULL,
  `reference_no` varchar(50) NOT NULL,
  `financial_year` varchar(20) NOT NULL,
  `quarter` enum('Q1','Q2','Q3','Q4') NOT NULL,
  `estimated_income` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(6,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `due_date` date NOT NULL,
  `payment_date` date DEFAULT NULL,
  `challan_no` varchar(100) DEFAULT NULL,
  `status` enum('due','paid','overdue') NOT NULL DEFAULT 'due',
  `notes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `advance_tax_payments_reference_no_unique` (`reference_no`),
  KEY `advance_tax_payments_institute_id_financial_year_index` (`institute_id`,`financial_year`),
  KEY `advance_tax_payments_institute_id_status_index` (`institute_id`,`status`),
  CONSTRAINT `advance_tax_payments_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `ambulance_drivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ambulance_drivers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `driver_number` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `license_type` varchar(30) DEFAULT NULL,
  `license_expiry` date DEFAULT NULL,
  `employee_type` varchar(30) NOT NULL DEFAULT 'full_time',
  `joined_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `medical_fitness_expiry` date DEFAULT NULL,
  `emergency_contact` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ambulance_driver_number` (`institute_id`,`driver_number`),
  KEY `ambulance_drivers_branch_id_foreign` (`branch_id`),
  KEY `ambulance_drivers_user_id_foreign` (`user_id`),
  KEY `ambulance_drivers_institute_id_status_index` (`institute_id`,`status`),
  CONSTRAINT `ambulance_drivers_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ambulance_drivers_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ambulance_drivers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ambulance_trips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ambulance_trips` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `trip_number` varchar(50) NOT NULL,
  `ambulance_id` bigint(20) unsigned DEFAULT NULL,
  `driver_id` bigint(20) unsigned DEFAULT NULL,
  `attendant_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned DEFAULT NULL,
  `emergency_visit_id` bigint(20) unsigned DEFAULT NULL,
  `admission_id` bigint(20) unsigned DEFAULT NULL,
  `trip_type` varchar(30) NOT NULL,
  `pickup_location` varchar(255) NOT NULL,
  `pickup_address` text DEFAULT NULL,
  `pickup_lat` decimal(10,7) DEFAULT NULL,
  `pickup_lng` decimal(10,7) DEFAULT NULL,
  `dropoff_location` varchar(255) NOT NULL,
  `dropoff_address` text DEFAULT NULL,
  `dropoff_lat` decimal(10,7) DEFAULT NULL,
  `dropoff_lng` decimal(10,7) DEFAULT NULL,
  `patient_condition_at_pickup` text DEFAULT NULL,
  `vitals_at_pickup` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`vitals_at_pickup`)),
  `treatment_en_route` text DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT NULL,
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `arrived_at_pickup` timestamp NULL DEFAULT NULL,
  `departed_pickup` timestamp NULL DEFAULT NULL,
  `arrived_at_dropoff` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `odometer_start_km` decimal(10,2) DEFAULT NULL,
  `odometer_end_km` decimal(10,2) DEFAULT NULL,
  `distance_km` decimal(8,2) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'requested',
  `priority` varchar(20) NOT NULL DEFAULT 'routine',
  `base_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `distance_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `waiting_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `invoice_id` bigint(20) unsigned DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `driver_notes` text DEFAULT NULL,
  `dispatch_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ambulance_trip_number` (`institute_id`,`trip_number`),
  KEY `ambulance_trips_branch_id_foreign` (`branch_id`),
  KEY `ambulance_trips_attendant_id_foreign` (`attendant_id`),
  KEY `ambulance_trips_institute_id_status_index` (`institute_id`,`status`),
  KEY `ambulance_trips_ambulance_id_status_index` (`ambulance_id`,`status`),
  KEY `ambulance_trips_driver_id_status_index` (`driver_id`,`status`),
  KEY `ambulance_trips_patient_id_requested_at_index` (`patient_id`,`requested_at`),
  CONSTRAINT `ambulance_trips_ambulance_id_foreign` FOREIGN KEY (`ambulance_id`) REFERENCES `ambulances` (`id`),
  CONSTRAINT `ambulance_trips_attendant_id_foreign` FOREIGN KEY (`attendant_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ambulance_trips_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ambulance_trips_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `ambulance_drivers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ambulance_trips_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ambulance_trips_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ambulances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ambulances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `vehicle_number` varchar(50) NOT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `make` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `type` varchar(30) NOT NULL,
  `fuel_type` varchar(30) DEFAULT NULL,
  `capacity_patients` int(11) NOT NULL DEFAULT 1,
  `capacity_attendants` int(11) NOT NULL DEFAULT 2,
  `equipment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`equipment`)),
  `status` varchar(30) NOT NULL DEFAULT 'available',
  `last_service_date` date DEFAULT NULL,
  `next_service_date` date DEFAULT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `fitness_expiry` date DEFAULT NULL,
  `odometer_km` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ambulance_vehicle_number` (`institute_id`,`vehicle_number`),
  KEY `ambulances_branch_id_foreign` (`branch_id`),
  KEY `ambulances_institute_id_status_index` (`institute_id`,`status`),
  KEY `ambulances_institute_id_is_active_index` (`institute_id`,`is_active`),
  CONSTRAINT `ambulances_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ambulances_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `doctor_id` bigint(20) unsigned DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `serial_number` int(11) DEFAULT NULL,
  `queue_order` int(11) DEFAULT NULL,
  `status` enum('scheduled','checked_in','in_progress','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  `fee_applied` decimal(10,2) DEFAULT NULL,
  `fee_collected_amount` decimal(10,2) DEFAULT NULL,
  `fee_discount_type` varchar(10) DEFAULT NULL,
  `fee_discount_value` decimal(10,2) DEFAULT NULL,
  `fee_discount_amount` decimal(10,2) DEFAULT NULL,
  `fee_collected_by_id` bigint(20) unsigned DEFAULT NULL,
  `fee_collected_by_name` varchar(150) DEFAULT NULL,
  `fee_collected_at` timestamp NULL DEFAULT NULL,
  `complaints` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appointments_doctor_day_serial` (`institute_id`,`doctor_id`,`appointment_date`,`serial_number`),
  KEY `appointments_institute_id_index` (`institute_id`),
  KEY `appointments_patient_id_index` (`patient_id`),
  KEY `appointments_doctor_id_index` (`doctor_id`),
  KEY `appointments_appointment_date_index` (`appointment_date`),
  KEY `appointments_status_index` (`status`),
  KEY `appointments_queue_lookup_index` (`institute_id`,`doctor_id`,`appointment_date`,`status`),
  KEY `appointments_patient_history_index` (`institute_id`,`patient_id`,`doctor_id`,`status`,`appointment_date`),
  KEY `appointments_branch_id_index` (`branch_id`),
  KEY `appointments_deleted_at_index` (`deleted_at`),
  CONSTRAINT `appointments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approval_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `step_order` int(10) unsigned NOT NULL,
  `approver_id` bigint(20) unsigned DEFAULT NULL,
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance_student_date` (`batch_id`,`student_id`,`class_date`),
  KEY `idx_attendance_institute` (`institute_id`),
  KEY `idx_attendance_batch_date` (`batch_id`,`class_date`),
  KEY `idx_attendance_student` (`student_id`),
  KEY `fk_attendance_marked_by` (`marked_by`),
  KEY `attendance_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_attendance_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_marked_by` FOREIGN KEY (`marked_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `backup_chunk_references`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_chunk_references` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `manifest_id` bigint(20) unsigned NOT NULL,
  `chunk_id` bigint(20) unsigned NOT NULL,
  `content_sha256` char(64) NOT NULL,
  `drive_file_id` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bc_ref_manifest_chunk_unique` (`manifest_id`,`chunk_id`),
  KEY `backup_chunk_references_tenant_id_index` (`tenant_id`),
  KEY `backup_chunk_references_manifest_id_index` (`manifest_id`),
  KEY `backup_chunk_references_chunk_id_index` (`chunk_id`),
  KEY `backup_chunk_references_drive_file_id_index` (`drive_file_id`),
  KEY `bc_ref_tenant_hash_idx` (`tenant_id`,`content_sha256`),
  KEY `bc_ref_tenant_file_idx` (`tenant_id`,`drive_file_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `backup_chunk_trash`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_chunk_trash` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `drive_file_id` varchar(100) NOT NULL,
  `content_sha256` char(64) NOT NULL,
  `size_bytes` bigint(20) NOT NULL DEFAULT 0,
  `trashed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `backup_chunk_trash_tenant_id_index` (`tenant_id`),
  KEY `backup_chunk_trash_expires_at_index` (`expires_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `backup_chunks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_chunks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `manifest_id` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `content_sha256` char(64) NOT NULL,
  `file_hmac` char(64) DEFAULT NULL,
  `drive_file_id` varchar(100) NOT NULL,
  `source_table` varchar(100) DEFAULT NULL,
  `chunk_index` int(11) NOT NULL DEFAULT 1,
  `total_chunks` int(11) NOT NULL DEFAULT 1,
  `size_bytes` bigint(20) NOT NULL,
  `reference_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `backup_chunks_manifest_id_content_sha256_unique` (`manifest_id`,`content_sha256`),
  KEY `backup_chunks_manifest_id_index` (`manifest_id`),
  KEY `backup_chunks_tenant_id_content_sha256_index` (`tenant_id`,`content_sha256`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `backup_manifests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_manifests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `backup_id` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `drive_file_id` varchar(100) DEFAULT NULL,
  `checksum_file_id` varchar(100) DEFAULT NULL,
  `manifest_json` mediumtext NOT NULL,
  `manifest_sha256` char(64) NOT NULL,
  `file_hmac` char(64) DEFAULT NULL,
  `total_chunks` int(11) NOT NULL DEFAULT 0,
  `total_size_bytes` bigint(20) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `backup_manifests_backup_id_unique` (`backup_id`),
  KEY `backup_manifests_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `owner_user_id` bigint(20) unsigned NOT NULL,
  `destination` enum('drive','local') NOT NULL DEFAULT 'local',
  `is_chunked` tinyint(1) NOT NULL DEFAULT 0,
  `drive_folder_id` varchar(100) DEFAULT NULL,
  `drive_file_id` varchar(255) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `file_hmac` varchar(64) DEFAULT NULL,
  `size_bytes` bigint(20) NOT NULL DEFAULT 0,
  `checksum_sha256` varchar(64) DEFAULT NULL,
  `encrypted` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('pending','uploading','completed','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `progress_percent` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `progress_stage` varchar(50) DEFAULT NULL,
  `progress_message` varchar(500) DEFAULT NULL,
  `job_id` varchar(100) DEFAULT NULL,
  `total_chunks` int(10) unsigned NOT NULL DEFAULT 0,
  `uploaded_chunks` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `backups_tenant_id_index` (`tenant_id`),
  KEY `backups_status_index` (`status`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `bank_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 100,
  `match_operator` varchar(10) NOT NULL DEFAULT 'and',
  `pattern_field` varchar(30) NOT NULL DEFAULT 'description',
  `pattern_type` varchar(20) NOT NULL DEFAULT 'contains',
  `pattern_value` varchar(500) NOT NULL,
  `amount_operator` varchar(20) DEFAULT NULL,
  `amount_min` decimal(19,4) DEFAULT NULL,
  `amount_max` decimal(19,4) DEFAULT NULL,
  `direction` varchar(10) DEFAULT NULL,
  `action_type` varchar(30) NOT NULL,
  `account_id` bigint(20) unsigned DEFAULT NULL,
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `narration` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `times_applied` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_rules_branch_id_foreign` (`branch_id`),
  KEY `bank_rules_account_id_foreign` (`account_id`),
  KEY `bank_rules_party_id_foreign` (`party_id`),
  KEY `bank_rules_institute_id_is_active_priority_index` (`institute_id`,`is_active`,`priority`),
  CONSTRAINT `bank_rules_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_rules_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_rules_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_rules_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_statement_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_statement_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rule_id` bigint(20) unsigned DEFAULT NULL,
  `match_confidence` int(11) DEFAULT NULL,
  `matched_je_id` bigint(20) unsigned DEFAULT NULL,
  `categorized_account_id` bigint(20) unsigned DEFAULT NULL,
  `category_status` varchar(20) NOT NULL DEFAULT 'unmatched',
  `matched_at` timestamp NULL DEFAULT NULL,
  `statement_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `transaction_date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `counterparty` varchar(255) DEFAULT NULL,
  `amount` decimal(19,4) NOT NULL,
  `type` enum('deposit','withdrawal') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bsl_stmt_date` (`statement_id`,`transaction_date`),
  KEY `idx_bsl_inst_ref` (`institute_id`,`reference`),
  KEY `bank_statement_lines_rule_id_foreign` (`rule_id`),
  KEY `bank_statement_lines_matched_je_id_foreign` (`matched_je_id`),
  KEY `bank_statement_lines_categorized_account_id_foreign` (`categorized_account_id`),
  CONSTRAINT `bank_statement_lines_categorized_account_id_foreign` FOREIGN KEY (`categorized_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_statement_lines_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_statement_lines_matched_je_id_foreign` FOREIGN KEY (`matched_je_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_statement_lines_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `bank_rules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_statement_lines_statement_id_foreign` FOREIGN KEY (`statement_id`) REFERENCES `bank_statements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_statements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_statements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `import_hash` varchar(64) DEFAULT NULL,
  `import_source` varchar(20) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `imported_at` timestamp NULL DEFAULT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `bank_account_id` bigint(20) unsigned NOT NULL,
  `statement_date` date NOT NULL,
  `opening_balance` decimal(19,4) DEFAULT NULL,
  `closing_balance` decimal(19,4) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('imported','reconciled','cancelled') NOT NULL DEFAULT 'imported',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_statements_branch_id_foreign` (`branch_id`),
  KEY `bank_statements_bank_account_id_foreign` (`bank_account_id`),
  KEY `idx_bs_inst_bank_date` (`institute_id`,`bank_account_id`,`statement_date`),
  KEY `idx_statement_import_hash` (`institute_id`,`import_hash`),
  CONSTRAINT `bank_statements_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_statements_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_statements_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `banned_emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `banned_emails` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `banned_by` bigint(20) unsigned DEFAULT NULL,
  `banned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_permanent` tinyint(1) NOT NULL DEFAULT 1,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `banned_emails_email_unique` (`email`),
  KEY `banned_emails_email_index` (`email`),
  KEY `banned_emails_is_permanent_index` (`is_permanent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `beds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `beds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `ward_id` bigint(20) unsigned NOT NULL,
  `bed_number` varchar(20) NOT NULL,
  `status` enum('available','occupied','reserved','maintenance') NOT NULL DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `beds_ward_id_bed_number_unique` (`ward_id`,`bed_number`),
  KEY `beds_institute_id_index` (`institute_id`),
  KEY `beds_status_index` (`status`),
  KEY `beds_branch_id_index` (`branch_id`),
  CONSTRAINT `beds_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `beds_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `beds_ward_id_foreign` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blood_donors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blood_donors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `donor_number` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `weight_kg` decimal(5,1) DEFAULT NULL,
  `hemoglobin` decimal(4,1) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `is_eligible` tinyint(1) NOT NULL DEFAULT 1,
  `last_donation_date` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_blood_donor_number` (`institute_id`,`donor_number`),
  KEY `blood_donors_branch_id_foreign` (`branch_id`),
  KEY `blood_donors_institute_id_status_index` (`institute_id`,`status`),
  KEY `blood_donors_institute_id_blood_group_index` (`institute_id`,`blood_group`),
  CONSTRAINT `blood_donors_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blood_donors_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blood_issue_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blood_issue_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `blood_request_id` bigint(20) unsigned NOT NULL,
  `blood_unit_id` bigint(20) unsigned NOT NULL,
  `issued_by` bigint(20) unsigned DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `returned_at` timestamp NULL DEFAULT NULL,
  `return_reason` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'issued',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `blood_issue_items_issued_by_foreign` (`issued_by`),
  KEY `blood_issue_items_blood_request_id_index` (`blood_request_id`),
  KEY `blood_issue_items_blood_unit_id_index` (`blood_unit_id`),
  KEY `blood_issue_items_status_index` (`status`),
  CONSTRAINT `blood_issue_items_blood_request_id_foreign` FOREIGN KEY (`blood_request_id`) REFERENCES `blood_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blood_issue_items_blood_unit_id_foreign` FOREIGN KEY (`blood_unit_id`) REFERENCES `blood_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blood_issue_items_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blood_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blood_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `request_number` varchar(50) NOT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `doctor_id` bigint(20) unsigned DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `component` varchar(30) NOT NULL,
  `units_requested` int(11) NOT NULL DEFAULT 1,
  `units_issued` int(11) NOT NULL DEFAULT 0,
  `urgency` varchar(20) NOT NULL DEFAULT 'routine',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `clinical_indication` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `fulfilled_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_blood_request_number` (`institute_id`,`request_number`),
  KEY `blood_requests_branch_id_foreign` (`branch_id`),
  KEY `blood_requests_requested_by_foreign` (`requested_by`),
  KEY `blood_requests_doctor_id_foreign` (`doctor_id`),
  KEY `blood_requests_approved_by_foreign` (`approved_by`),
  KEY `blood_requests_institute_id_status_index` (`institute_id`,`status`),
  KEY `blood_requests_institute_id_blood_group_status_index` (`institute_id`,`blood_group`,`status`),
  KEY `blood_requests_patient_id_index` (`patient_id`),
  CONSTRAINT `blood_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blood_requests_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blood_requests_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blood_requests_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blood_requests_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blood_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blood_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blood_units` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `unit_number` varchar(50) NOT NULL,
  `donor_id` bigint(20) unsigned DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `component` varchar(30) NOT NULL,
  `volume_ml` int(11) NOT NULL DEFAULT 0,
  `collection_date` timestamp NULL DEFAULT NULL,
  `expiry_date` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'available',
  `crossmatch_required` tinyint(1) NOT NULL DEFAULT 1,
  `screening_hiv` varchar(10) NOT NULL DEFAULT 'pending',
  `screening_hbsag` varchar(10) NOT NULL DEFAULT 'pending',
  `screening_hcv` varchar(10) NOT NULL DEFAULT 'pending',
  `screening_syphilis` varchar(10) NOT NULL DEFAULT 'pending',
  `screening_malaria` varchar(10) NOT NULL DEFAULT 'pending',
  `current_patient_id` bigint(20) unsigned DEFAULT NULL,
  `reserved_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_blood_unit_number` (`institute_id`,`unit_number`),
  KEY `blood_units_branch_id_foreign` (`branch_id`),
  KEY `blood_units_donor_id_foreign` (`donor_id`),
  KEY `blood_units_current_patient_id_foreign` (`current_patient_id`),
  KEY `blood_units_institute_id_status_index` (`institute_id`,`status`),
  KEY `blood_units_institute_id_blood_group_status_index` (`institute_id`,`blood_group`,`status`),
  KEY `blood_units_institute_id_component_status_index` (`institute_id`,`component`,`status`),
  KEY `blood_units_expiry_date_index` (`expiry_date`),
  CONSTRAINT `blood_units_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blood_units_current_patient_id_foreign` FOREIGN KEY (`current_patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blood_units_donor_id_foreign` FOREIGN KEY (`donor_id`) REFERENCES `blood_donors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blood_units_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` text NOT NULL,
  `expiration` int(10) unsigned NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(10) unsigned NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `cds_findings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cds_findings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `prescription_id` bigint(20) unsigned DEFAULT NULL,
  `cds_rule_version_id` bigint(20) unsigned NOT NULL,
  `severity` varchar(10) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `message` text NOT NULL,
  `explanation` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`explanation`)),
  `trigger_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`trigger_data`)),
  `evaluated_at` timestamp NULL DEFAULT NULL,
  `resolved_by` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cds_findings_patient_id_foreign` (`patient_id`),
  KEY `cds_findings_institute_id_patient_id_index` (`institute_id`,`patient_id`),
  KEY `cds_findings_prescription_id_status_index` (`prescription_id`,`status`),
  KEY `cds_findings_cds_rule_version_id_index` (`cds_rule_version_id`),
  CONSTRAINT `cds_findings_cds_rule_version_id_foreign` FOREIGN KEY (`cds_rule_version_id`) REFERENCES `cds_rule_versions` (`id`),
  CONSTRAINT `cds_findings_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cds_findings_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cds_findings_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cds_rule_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cds_rule_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cds_rule_id` bigint(20) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `definition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`definition`)),
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `effective_from` timestamp NULL DEFAULT NULL,
  `effective_until` timestamp NULL DEFAULT NULL,
  `validated_by` varchar(120) DEFAULT NULL,
  `validated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cds_rule_version` (`cds_rule_id`,`version`),
  KEY `cds_rule_versions_status_index` (`status`),
  CONSTRAINT `cds_rule_versions_cds_rule_id_foreign` FOREIGN KEY (`cds_rule_id`) REFERENCES `cds_rules` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cds_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cds_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rule_key` varchar(80) NOT NULL,
  `rule_type` varchar(30) NOT NULL,
  `severity` varchar(10) NOT NULL,
  `block_policy` varchar(10) NOT NULL DEFAULT 'warn',
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `source` varchar(120) NOT NULL,
  `source_version` varchar(60) DEFAULT NULL,
  `validated_by` varchar(120) DEFAULT NULL,
  `validated_at` timestamp NULL DEFAULT NULL,
  `current_version` int(10) unsigned NOT NULL DEFAULT 1,
  `effective_from` timestamp NULL DEFAULT NULL,
  `effective_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cds_rules_rule_key_unique` (`rule_key`),
  KEY `cds_rules_status_rule_type_index` (`status`,`rule_type`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chart_of_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chart_of_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
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
  `is_postable` tinyint(1) NOT NULL DEFAULT 1,
  `is_header` tinyint(1) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `industries` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`industries`)),
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `legacy_head_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `institute_key` bigint(20) GENERATED ALWAYS AS (coalesce(`institute_id`,0)) VIRTUAL,
  `branch_key` bigint(20) GENERATED ALWAYS AS (coalesce(`branch_id`,0)) VIRTUAL,
  `category` varchar(50) GENERATED ALWAYS AS (case when `type` in ('asset','liability','equity') then 'balance_sheet' when `type` in ('income','expense') then 'income_statement' else NULL end) VIRTUAL,
  `alive` bigint(20) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coa_code_v3` (`institute_key`,`branch_key`,`code`,`alive`),
  KEY `chart_of_accounts_branch_id_foreign` (`branch_id`),
  KEY `chart_of_accounts_parent_id_foreign` (`parent_id`),
  KEY `idx_coa_group` (`account_group_id`),
  KEY `idx_coa_active` (`institute_id`,`is_active`),
  KEY `idx_coa_legacy` (`legacy_head_id`),
  KEY `chart_of_accounts_currency_id_foreign` (`currency_id`),
  KEY `idx_coa_scope_type` (`institute_id`,`branch_id`,`type`),
  KEY `idx_coa_cash_flow_category` (`institute_id`,`cash_flow_category`),
  KEY `idx_coa_tenant_global` (`institute_id`,`is_system`,`is_active`),
  KEY `idx_coa_code` (`code`),
  CONSTRAINT `chart_of_accounts_account_group_id_foreign` FOREIGN KEY (`account_group_id`) REFERENCES `account_groups` (`id`),
  CONSTRAINT `chart_of_accounts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chart_of_accounts_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chart_of_accounts_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chart_of_accounts_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_chart_of_accounts_alive_ins` BEFORE INSERT ON `chart_of_accounts` FOR EACH ROW SET NEW.alive = IF(NEW.deleted_at IS NULL, 1, -NEW.id) */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_chart_of_accounts_alive_upd` BEFORE UPDATE ON `chart_of_accounts` FOR EACH ROW SET NEW.alive = IF(NEW.deleted_at IS NULL, 1, -OLD.id) */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinical_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clinical_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_type` varchar(30) DEFAULT NULL,
  `actor_name` varchar(150) DEFAULT NULL,
  `auditable_type` varchar(120) NOT NULL,
  `auditable_id` bigint(20) unsigned NOT NULL,
  `action` varchar(40) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_clinical_audit_subject` (`institute_id`,`auditable_type`,`auditable_id`),
  KEY `clinical_audit_logs_patient_id_index` (`patient_id`),
  KEY `clinical_audit_logs_branch_id_index` (`branch_id`),
  CONSTRAINT `clinical_audit_logs_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `clinical_audit_logs_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clinical_audit_logs_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinical_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clinical_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `note_number` varchar(50) NOT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `author_id` bigint(20) unsigned NOT NULL,
  `note_type` varchar(50) NOT NULL,
  `encounter_type` varchar(50) DEFAULT NULL,
  `encounter_id` bigint(20) unsigned DEFAULT NULL,
  `subjective` text DEFAULT NULL,
  `objective` text DEFAULT NULL,
  `assessment` text DEFAULT NULL,
  `plan` text DEFAULT NULL,
  `content` text DEFAULT NULL,
  `addendum` text DEFAULT NULL,
  `noted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_signed` tinyint(1) NOT NULL DEFAULT 0,
  `signed_at` timestamp NULL DEFAULT NULL,
  `signature_hash` varchar(64) DEFAULT NULL,
  `is_amended` tinyint(1) NOT NULL DEFAULT 0,
  `amendment_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_clinical_note_number` (`institute_id`,`note_number`),
  KEY `clinical_notes_branch_id_foreign` (`branch_id`),
  KEY `clinical_notes_patient_id_foreign` (`patient_id`),
  KEY `clinical_notes_author_id_foreign` (`author_id`),
  KEY `clinical_notes_institute_id_patient_id_note_type_index` (`institute_id`,`patient_id`,`note_type`),
  KEY `clinical_notes_encounter_type_encounter_id_index` (`encounter_type`,`encounter_id`),
  CONSTRAINT `clinical_notes_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `clinical_notes_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `clinical_notes_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clinical_notes_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coa_group_reanchor_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coa_group_reanchor_backup` (
  `coa_row_id` bigint(20) unsigned NOT NULL,
  `original_group_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`coa_row_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coa_group_reanchor_created`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coa_group_reanchor_created` (
  `group_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coa_reanchor_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coa_reanchor_backup` (
  `coa_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`coa_id`),
  KEY `coa_reanchor_backup_institute_idx` (`institute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coa_reanchor_created`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coa_reanchor_created` (
  `coa_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`coa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `command_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `command_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint(20) unsigned DEFAULT NULL,
  `command` varchar(100) NOT NULL,
  `label` varchar(150) DEFAULT NULL,
  `risk` varchar(20) NOT NULL DEFAULT 'low',
  `output_summary` text DEFAULT NULL,
  `full_output` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `exit_code` int(11) NOT NULL DEFAULT 0,
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `command_logs_created_at_index` (`created_at`),
  KEY `command_logs_admin_id_index` (`admin_id`),
  KEY `command_logs_command_index` (`command`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `corporate_tax_computations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `corporate_tax_computations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `country_code` char(2) NOT NULL,
  `currency_code` char(3) NOT NULL,
  `reference_no` varchar(50) NOT NULL,
  `financial_year` varchar(20) NOT NULL,
  `entity_type` varchar(50) NOT NULL DEFAULT 'private_limited',
  `total_income` decimal(15,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_income` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(6,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `minimum_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `final_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_tax_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_payable` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','filed','paid') NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `corporate_tax_computations_reference_no_unique` (`reference_no`),
  KEY `corporate_tax_computations_institute_id_financial_year_index` (`institute_id`,`financial_year`),
  KEY `corporate_tax_computations_institute_id_status_index` (`institute_id`,`status`),
  CONSTRAINT `corporate_tax_computations_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `country_currency_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `country_currency_map` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_code` char(2) NOT NULL,
  `country_name` varchar(100) NOT NULL,
  `currency_code` char(3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `country_currency_map_country_code_unique` (`country_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `country_tax_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `country_tax_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_code` char(2) NOT NULL,
  `tds_label` varchar(50) NOT NULL,
  `tds_label_local` varchar(100) DEFAULT NULL,
  `module_label` varchar(60) NOT NULL,
  `tax_authority` varchar(50) NOT NULL,
  `tax_authority_full` varchar(200) NOT NULL,
  `fiscal_year_pattern` varchar(20) NOT NULL,
  `return_frequency` varchar(20) NOT NULL,
  `return_deadlines` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`return_deadlines`)),
  `certificate_form_name` varchar(100) NOT NULL,
  `return_form_name` varchar(100) DEFAULT NULL,
  `tin_label` varchar(30) NOT NULL,
  `tin_format_regex` varchar(100) DEFAULT NULL,
  `has_advance_tax` tinyint(1) NOT NULL DEFAULT 1,
  `has_minimum_tax` tinyint(1) NOT NULL DEFAULT 0,
  `minimum_tax_rate` decimal(5,2) DEFAULT NULL,
  `corporate_tax_rates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`corporate_tax_rates`)),
  `currency_code` char(3) NOT NULL,
  `extra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `country_tax_configs_country_code_unique` (`country_code`),
  KEY `country_tax_configs_country_code_index` (`country_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `country_tax_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `country_tax_modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_code` char(2) NOT NULL,
  `tax_module` varchar(60) NOT NULL,
  `tax_name` varchar(100) NOT NULL,
  `default_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `country_tax_modules_country_code_tax_module_unique` (`country_code`,`tax_module`),
  KEY `country_tax_modules_country_code_index` (`country_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  CONSTRAINT `course_curricula_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `course_curricula_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_curricula_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) DEFAULT NULL,
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_leads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) DEFAULT NULL,
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `currencies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` char(3) NOT NULL,
  `name` varchar(100) NOT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `symbol_native` varchar(10) DEFAULT NULL,
  `decimal_places` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `rounding` tinyint(4) NOT NULL DEFAULT 0,
  `is_base` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_currencies_code` (`code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `dealership_api_endpoints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_api_endpoints` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `endpoint_key` varchar(80) NOT NULL,
  `http_method` enum('GET','POST','PUT','PATCH','DELETE') NOT NULL,
  `uri` varchar(160) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `required_permission` varchar(80) DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `version` varchar(20) NOT NULL DEFAULT 'v1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dae_key_unique` (`institute_id`,`endpoint_key`,`http_method`),
  KEY `dae_enabled_idx` (`institute_id`,`is_enabled`),
  KEY `dealership_api_endpoints_institute_id_index` (`institute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_api_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_api_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `sales_force_id` bigint(20) unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `abilities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`abilities`)),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dat_token_unique` (`institute_id`,`token_hash`),
  KEY `dat_sf_idx` (`institute_id`,`sales_force_id`),
  KEY `dat_revoke_idx` (`institute_id`,`revoked_at`),
  KEY `dealership_api_tokens_institute_id_index` (`institute_id`),
  KEY `dealership_api_tokens_sales_force_id_index` (`sales_force_id`),
  CONSTRAINT `fk_dapi_token_sr` FOREIGN KEY (`sales_force_id`) REFERENCES `dealership_sales_force` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_attendance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `sales_force_id` bigint(20) unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_at` timestamp NULL DEFAULT NULL,
  `check_out_at` timestamp NULL DEFAULT NULL,
  `beat_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('present','absent','half_day','leave') NOT NULL DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dat_scope_unique` (`institute_id`,`sales_force_id`,`attendance_date`),
  KEY `dealership_attendance_institute_id_attendance_date_index` (`institute_id`,`attendance_date`),
  KEY `dealership_attendance_institute_id_index` (`institute_id`),
  KEY `dealership_attendance_sales_force_id_index` (`sales_force_id`),
  KEY `dealership_attendance_beat_id_index` (`beat_id`),
  CONSTRAINT `fk_dat_beat` FOREIGN KEY (`beat_id`) REFERENCES `dealership_beats` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dat_sr` FOREIGN KEY (`sales_force_id`) REFERENCES `dealership_sales_force` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_beats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_beats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_bn` varchar(255) DEFAULT NULL,
  `region` varchar(255) DEFAULT NULL,
  `route` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dealership_beats_institute_id_code_unique` (`institute_id`,`code`),
  KEY `dealership_beats_institute_id_index` (`institute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_brand_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_brand_targets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `brand_id` bigint(20) unsigned NOT NULL,
  `period_type` enum('monthly','quarterly','yearly') NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `target_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `achieved_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dbt_scope_unique` (`institute_id`,`brand_id`,`period_type`,`period_start`),
  KEY `dealership_brand_targets_institute_id_index` (`institute_id`),
  KEY `dealership_brand_targets_brand_id_index` (`brand_id`),
  CONSTRAINT `fk_dbt_brand` FOREIGN KEY (`brand_id`) REFERENCES `dealership_brands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_brands` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_bn` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dealership_brands_institute_id_code_unique` (`institute_id`,`code`),
  KEY `dealership_brands_institute_id_index` (`institute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_credit_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_credit_limits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `credit_limit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `overdue_days_block` int(10) unsigned NOT NULL DEFAULT 30,
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  `last_reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dealership_credit_limits_institute_customer_unique` (`institute_id`,`customer_id`),
  KEY `dealership_credit_limits_institute_id_index` (`institute_id`),
  KEY `fk_dcl_customer` (`customer_id`),
  CONSTRAINT `fk_dcl_customer` FOREIGN KEY (`customer_id`) REFERENCES `dealership_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_bn` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `channel` enum('retail','wholesale') NOT NULL DEFAULT 'retail',
  `beat_id` bigint(20) unsigned DEFAULT NULL,
  `credit_limit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dealership_customers_institute_id_code_unique` (`institute_id`,`code`),
  KEY `dealership_customers_institute_id_channel_is_active_index` (`institute_id`,`channel`,`is_active`),
  KEY `dealership_customers_institute_id_index` (`institute_id`),
  KEY `dealership_customers_beat_id_index` (`beat_id`),
  CONSTRAINT `fk_dcust_beat` FOREIGN KEY (`beat_id`) REFERENCES `dealership_beats` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_incentives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_incentives` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `sales_force_id` bigint(20) unsigned NOT NULL,
  `rule_name` varchar(120) NOT NULL,
  `rule_type` enum('flat','percentage','tiered') NOT NULL,
  `threshold_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `incentive_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `earned_on` date DEFAULT NULL,
  `status` enum('pending','approved','paid','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_di_sr` (`sales_force_id`),
  CONSTRAINT `fk_di_sr` FOREIGN KEY (`sales_force_id`) REFERENCES `dealership_sales_force` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_inventory_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_inventory_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `inventory_item_id` bigint(20) unsigned DEFAULT NULL,
  `inventory_sku` varchar(255) DEFAULT NULL,
  `sync_mode` enum('manual','auto') NOT NULL DEFAULT 'manual',
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dealership_inventory_links_institute_product_unique` (`institute_id`,`product_id`),
  KEY `dealership_inventory_links_institute_id_index` (`institute_id`),
  KEY `fk_dil_product` (`product_id`),
  CONSTRAINT `fk_dil_product` FOREIGN KEY (`product_id`) REFERENCES `dealership_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_order_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_order_approvals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `sr_order_id` bigint(20) unsigned NOT NULL,
  `action` enum('submitted','approved','rejected','reopened') NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dealership_order_approvals_sr_order_id_index` (`sr_order_id`),
  KEY `dealership_order_approvals_institute_id_index` (`institute_id`),
  CONSTRAINT `fk_dapprovals_order` FOREIGN KEY (`sr_order_id`) REFERENCES `dealership_sr_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_price_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_price_lists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `brand_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `channel` enum('general','retail','wholesale','sub_dealer') NOT NULL DEFAULT 'general',
  `price` decimal(15,2) NOT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dealership_price_lists_brand_id_index` (`brand_id`),
  KEY `dealership_price_lists_product_id_index` (`product_id`),
  KEY `dealership_price_lists_institute_id_index` (`institute_id`),
  KEY `dpl_scope_idx` (`institute_id`,`brand_id`,`channel`,`effective_from`),
  CONSTRAINT `fk_dpl_brand` FOREIGN KEY (`brand_id`) REFERENCES `dealership_brands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dpl_product` FOREIGN KEY (`product_id`) REFERENCES `dealership_products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `brand_id` bigint(20) unsigned NOT NULL,
  `sku` varchar(80) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_bn` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `retail_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dealership_products_institute_id_sku_unique` (`institute_id`,`sku`),
  KEY `dealership_products_institute_id_brand_id_is_active_index` (`institute_id`,`brand_id`,`is_active`),
  KEY `dealership_products_institute_id_index` (`institute_id`),
  KEY `dealership_products_brand_id_index` (`brand_id`),
  CONSTRAINT `fk_dproducts_brand` FOREIGN KEY (`brand_id`) REFERENCES `dealership_brands` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_push_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_push_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `recipient_sales_force_id` bigint(20) unsigned NOT NULL,
  `title` varchar(160) NOT NULL,
  `body` text NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `status` enum('queued','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dpn_queue_idx` (`institute_id`,`status`,`scheduled_at`),
  KEY `dpn_recipient_idx` (`institute_id`,`recipient_sales_force_id`),
  KEY `dealership_push_notifications_institute_id_index` (`institute_id`),
  KEY `dealership_push_notifications_recipient_sales_force_id_index` (`recipient_sales_force_id`),
  CONSTRAINT `fk_dpn_recipient` FOREIGN KEY (`recipient_sales_force_id`) REFERENCES `dealership_sales_force` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_report_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_report_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `report_key` varchar(80) NOT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `scope_hash` varchar(64) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `generated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `drs_scope_unique` (`institute_id`,`report_key`,`scope_hash`),
  KEY `drs_lookup_idx` (`institute_id`,`report_key`,`period_start`),
  KEY `dealership_report_snapshots_institute_id_index` (`institute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_sales_force`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_sales_force` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `employee_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_bn` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `beat_id` bigint(20) unsigned DEFAULT NULL,
  `monthly_target` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dealership_sales_force_institute_id_employee_code_unique` (`institute_id`,`employee_code`),
  KEY `dealership_sales_force_institute_id_index` (`institute_id`),
  KEY `dealership_sales_force_beat_id_index` (`beat_id`),
  CONSTRAINT `fk_dsf_beat` FOREIGN KEY (`beat_id`) REFERENCES `dealership_beats` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_sr_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_sr_collections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `receipt_no` varchar(255) NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `sales_force_id` bigint(20) unsigned NOT NULL,
  `sr_order_id` bigint(20) unsigned DEFAULT NULL,
  `method` enum('cash','cheque','bank_transfer','mobile_banking') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `collected_on` date NOT NULL,
  `status` enum('pending','cleared','bounced') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dealership_sr_collections_institute_receipt_unique` (`institute_id`,`receipt_no`),
  KEY `dealership_sr_collections_customer_id_index` (`customer_id`),
  KEY `dealership_sr_collections_sales_force_id_index` (`sales_force_id`),
  KEY `dealership_sr_collections_sr_order_id_index` (`sr_order_id`),
  KEY `dealership_sr_collections_institute_id_index` (`institute_id`),
  CONSTRAINT `fk_dcoll_customer` FOREIGN KEY (`customer_id`) REFERENCES `dealership_customers` (`id`),
  CONSTRAINT `fk_dcoll_order` FOREIGN KEY (`sr_order_id`) REFERENCES `dealership_sr_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dcoll_sf` FOREIGN KEY (`sales_force_id`) REFERENCES `dealership_sales_force` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_sr_commission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_sr_commission` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `sales_force_id` bigint(20) unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `base_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `commission_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','paid','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dsc_scope_unique` (`institute_id`,`sales_force_id`,`period_start`,`period_end`),
  KEY `dealership_sr_commission_institute_id_status_index` (`institute_id`,`status`),
  KEY `dealership_sr_commission_institute_id_index` (`institute_id`),
  KEY `dealership_sr_commission_sales_force_id_index` (`sales_force_id`),
  CONSTRAINT `fk_dsc_sr` FOREIGN KEY (`sales_force_id`) REFERENCES `dealership_sales_force` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_sr_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_sr_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `sr_order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `qty` decimal(15,3) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `line_total` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dealership_sr_order_items_sr_order_id_index` (`sr_order_id`),
  KEY `dealership_sr_order_items_product_id_index` (`product_id`),
  KEY `dealership_sr_order_items_institute_id_index` (`institute_id`),
  CONSTRAINT `fk_dsr_items_order` FOREIGN KEY (`sr_order_id`) REFERENCES `dealership_sr_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dsr_items_product` FOREIGN KEY (`product_id`) REFERENCES `dealership_products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_sr_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_sr_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `order_no` varchar(255) NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `sales_force_id` bigint(20) unsigned NOT NULL,
  `channel` enum('general','retail','wholesale','sub_dealer') NOT NULL DEFAULT 'general',
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','submitted','approved','rejected','delivered') NOT NULL DEFAULT 'draft',
  `remarks` text DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dealership_sr_orders_institute_order_unique` (`institute_id`,`order_no`),
  KEY `dealership_sr_orders_customer_id_index` (`customer_id`),
  KEY `dealership_sr_orders_sales_force_id_index` (`sales_force_id`),
  KEY `dealership_sr_orders_institute_id_index` (`institute_id`),
  CONSTRAINT `fk_dsr_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `dealership_customers` (`id`),
  CONSTRAINT `fk_dsr_orders_sf` FOREIGN KEY (`sales_force_id`) REFERENCES `dealership_sales_force` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealership_sr_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealership_sr_targets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `sales_force_id` bigint(20) unsigned NOT NULL,
  `period_type` enum('monthly','quarterly','yearly') NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `target_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `achieved_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dst_scope_unique` (`institute_id`,`sales_force_id`,`period_type`,`period_start`),
  KEY `dealership_sr_targets_institute_id_period_start_period_end_index` (`institute_id`,`period_start`,`period_end`),
  KEY `dealership_sr_targets_institute_id_index` (`institute_id`),
  KEY `dealership_sr_targets_sales_force_id_index` (`sales_force_id`),
  CONSTRAINT `fk_dst_sr` FOREIGN KEY (`sales_force_id`) REFERENCES `dealership_sales_force` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dental_charts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dental_charts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `dentist_id` bigint(20) unsigned DEFAULT NULL,
  `tooth_conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tooth_conditions`)),
  `total_teeth` int(11) NOT NULL DEFAULT 32,
  `caries_count` int(11) NOT NULL DEFAULT 0,
  `filled_count` int(11) NOT NULL DEFAULT 0,
  `missing_count` int(11) NOT NULL DEFAULT 0,
  `crown_count` int(11) NOT NULL DEFAULT 0,
  `rct_count` int(11) NOT NULL DEFAULT 0,
  `general_notes` text DEFAULT NULL,
  `oral_hygiene` varchar(30) DEFAULT NULL,
  `last_assessed_at` timestamp NULL DEFAULT NULL,
  `assessed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_patient_dental_chart` (`institute_id`,`patient_id`),
  KEY `dental_charts_branch_id_foreign` (`branch_id`),
  KEY `dental_charts_patient_id_foreign` (`patient_id`),
  KEY `dental_charts_dentist_id_foreign` (`dentist_id`),
  KEY `dental_charts_institute_id_patient_id_index` (`institute_id`,`patient_id`),
  CONSTRAINT `dental_charts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dental_charts_dentist_id_foreign` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dental_charts_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dental_charts_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dental_procedure_catalog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dental_procedure_catalog` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(30) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `category` varchar(50) NOT NULL,
  `body_site` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `default_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `default_duration_minutes` int(11) NOT NULL DEFAULT 30,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dental_procedure_catalog_institute_id_category_is_active_index` (`institute_id`,`category`,`is_active`),
  CONSTRAINT `dental_procedure_catalog_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dental_procedures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dental_procedures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `procedure_number` varchar(50) NOT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `dentist_id` bigint(20) unsigned NOT NULL,
  `appointment_id` bigint(20) unsigned DEFAULT NULL,
  `dental_chart_id` bigint(20) unsigned DEFAULT NULL,
  `procedure_code` varchar(30) DEFAULT NULL,
  `procedure_name` varchar(200) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `tooth_number` varchar(10) DEFAULT NULL,
  `tooth_surface` varchar(50) DEFAULT NULL,
  `quadrant` varchar(20) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `procedure_notes` text DEFAULT NULL,
  `anesthesia_type` varchar(50) DEFAULT NULL,
  `anesthesia_agent` varchar(100) DEFAULT NULL,
  `anesthesia_volume_ml` decimal(5,2) DEFAULT NULL,
  `medications_prescribed` text DEFAULT NULL,
  `materials_used` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`materials_used`)),
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `duration_minutes` int(11) DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `follow_up_instructions` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'completed',
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dental_procedure_number` (`institute_id`,`procedure_number`),
  KEY `dental_procedures_branch_id_foreign` (`branch_id`),
  KEY `dental_procedures_dentist_id_foreign` (`dentist_id`),
  KEY `dental_procedures_dental_chart_id_foreign` (`dental_chart_id`),
  KEY `dental_procedures_institute_id_status_index` (`institute_id`,`status`),
  KEY `dental_procedures_patient_id_performed_at_index` (`patient_id`,`performed_at`),
  CONSTRAINT `dental_procedures_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dental_procedures_dental_chart_id_foreign` FOREIGN KEY (`dental_chart_id`) REFERENCES `dental_charts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dental_procedures_dentist_id_foreign` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`id`),
  CONSTRAINT `dental_procedures_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dental_procedures_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dental_treatment_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dental_treatment_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `plan_number` varchar(50) NOT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `dentist_id` bigint(20) unsigned NOT NULL,
  `chief_complaint` text NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_summary` text DEFAULT NULL,
  `planned_steps` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`planned_steps`)),
  `total_steps` int(11) NOT NULL DEFAULT 0,
  `completed_steps` int(11) NOT NULL DEFAULT 0,
  `start_date` date NOT NULL,
  `expected_end_date` date DEFAULT NULL,
  `total_estimated_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `discontinue_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dental_plan_number` (`institute_id`,`plan_number`),
  KEY `dental_treatment_plans_branch_id_foreign` (`branch_id`),
  KEY `dental_treatment_plans_patient_id_foreign` (`patient_id`),
  KEY `dental_treatment_plans_dentist_id_foreign` (`dentist_id`),
  KEY `dental_treatment_plans_institute_id_status_index` (`institute_id`,`status`),
  CONSTRAINT `dental_treatment_plans_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dental_treatment_plans_dentist_id_foreign` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`id`),
  CONSTRAINT `dental_treatment_plans_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dental_treatment_plans_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deployment_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deployment_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('git','zip') NOT NULL DEFAULT 'git',
  `version` varchar(255) DEFAULT NULL COMMENT 'Git commit hash or version string',
  `status` enum('success','failed','rolled_back') NOT NULL DEFAULT 'success',
  `log` longtext DEFAULT NULL COMMENT 'Full output of deployment process',
  `code_backup_path` varchar(500) DEFAULT NULL,
  `backup_path` varchar(255) DEFAULT NULL COMMENT 'Path to code backup',
  `db_backup_path` varchar(255) DEFAULT NULL COMMENT 'Path to SQL backup',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deployment_logs_type_index` (`type`),
  KEY `deployment_logs_status_index` (`status`),
  KEY `deployment_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dgda_import_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dgda_import_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(40) NOT NULL DEFAULT 'dgda_official_export',
  `filename` varchar(200) DEFAULT NULL,
  `checksum` varchar(64) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `records_seen` int(10) unsigned NOT NULL DEFAULT 0,
  `records_valid` int(10) unsigned NOT NULL DEFAULT 0,
  `records_created` int(10) unsigned NOT NULL DEFAULT 0,
  `records_updated` int(10) unsigned NOT NULL DEFAULT 0,
  `records_unchanged` int(10) unsigned NOT NULL DEFAULT 0,
  `records_rejected` int(10) unsigned NOT NULL DEFAULT 0,
  `records_unmatched` int(10) unsigned NOT NULL DEFAULT 0,
  `records_ambiguous` int(10) unsigned NOT NULL DEFAULT 0,
  `error_summary` text DEFAULT NULL,
  `source_version` varchar(100) DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dgda_medicines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dgda_medicines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_code` varchar(5) NOT NULL DEFAULT 'BD',
  `dgda_code` varchar(100) NOT NULL,
  `dar_number` varchar(100) DEFAULT NULL,
  `concept_id` varchar(100) DEFAULT NULL,
  `brand_name` varchar(255) NOT NULL,
  `generic_name` varchar(255) DEFAULT NULL,
  `strength` varchar(100) DEFAULT NULL,
  `dosage_form` varchar(100) DEFAULT NULL,
  `route` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `pack_size` varchar(50) DEFAULT NULL,
  `normalized_name` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dgda_medicines_dgda_code_unique` (`dgda_code`),
  KEY `dgda_medicines_country_code_index` (`country_code`),
  KEY `dgda_medicines_dar_number_index` (`dar_number`),
  KEY `dgda_medicines_brand_name_index` (`brand_name`),
  KEY `dgda_medicines_generic_name_index` (`generic_name`),
  KEY `dgda_medicines_normalized_name_index` (`normalized_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dgda_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dgda_registrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dgda_import_batch_id` bigint(20) unsigned DEFAULT NULL,
  `dar_number` varchar(60) NOT NULL,
  `brand_name` varchar(190) DEFAULT NULL,
  `generic_name` varchar(190) DEFAULT NULL,
  `strength_raw` varchar(100) DEFAULT NULL,
  `dosage_form_raw` varchar(60) DEFAULT NULL,
  `manufacturer_name` varchar(190) DEFAULT NULL,
  `importer_name` varchar(190) DEFAULT NULL,
  `status_raw` varchar(60) DEFAULT NULL,
  `valid_upto` date DEFAULT NULL,
  `medicine_product_id` bigint(20) unsigned DEFAULT NULL,
  `match_status` varchar(20) NOT NULL DEFAULT 'unmatched',
  `match_detail` text DEFAULT NULL,
  `raw_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_payload`)),
  `retrieved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dgda_registrations_dar_number_unique` (`dar_number`),
  KEY `dgda_registrations_dgda_import_batch_id_foreign` (`dgda_import_batch_id`),
  KEY `dgda_registrations_medicine_product_id_index` (`medicine_product_id`),
  KEY `dgda_registrations_match_status_index` (`match_status`),
  CONSTRAINT `dgda_registrations_dgda_import_batch_id_foreign` FOREIGN KEY (`dgda_import_batch_id`) REFERENCES `dgda_import_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dgda_registrations_medicine_product_id_foreign` FOREIGN KEY (`medicine_product_id`) REFERENCES `medicine_products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dgda_sync_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dgda_sync_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_type` varchar(30) NOT NULL,
  `source_file` varchar(255) DEFAULT NULL,
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `imported` int(11) NOT NULL DEFAULT 0,
  `updated` int(11) NOT NULL DEFAULT 0,
  `skipped` int(11) NOT NULL DEFAULT 0,
  `failed` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'running',
  `error_log` text DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `started_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diet_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `diet_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `plan_number` varchar(50) NOT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `prescribed_by` bigint(20) unsigned NOT NULL,
  `admission_id` bigint(20) unsigned DEFAULT NULL,
  `plan_name` varchar(200) NOT NULL,
  `diet_type` varchar(50) NOT NULL,
  `restrictions` text DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `daily_calories` int(11) DEFAULT NULL,
  `protein_grams` decimal(6,2) DEFAULT NULL,
  `carbs_grams` decimal(6,2) DEFAULT NULL,
  `fat_grams` decimal(6,2) DEFAULT NULL,
  `sodium_mg` decimal(8,2) DEFAULT NULL,
  `potassium_mg` decimal(8,2) DEFAULT NULL,
  `fluid_ml` decimal(8,2) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `days_planned` int(11) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `discontinue_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_diet_plan_number` (`institute_id`,`plan_number`),
  KEY `diet_plans_branch_id_foreign` (`branch_id`),
  KEY `diet_plans_prescribed_by_foreign` (`prescribed_by`),
  KEY `diet_plans_institute_id_status_index` (`institute_id`,`status`),
  KEY `diet_plans_patient_id_start_date_index` (`patient_id`,`start_date`),
  CONSTRAINT `diet_plans_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `diet_plans_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `diet_plans_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `diet_plans_prescribed_by_foreign` FOREIGN KEY (`prescribed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diet_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `diet_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `diet_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `meal_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meal_items`)),
  `total_calories` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `diet_templates_institute_id_diet_type_is_active_index` (`institute_id`,`diet_type`,`is_active`),
  CONSTRAINT `diet_templates_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discharge_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `discharge_summaries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `summary_number` varchar(50) NOT NULL,
  `admission_id` bigint(20) unsigned NOT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `prepared_by` bigint(20) unsigned NOT NULL,
  `admission_date` date NOT NULL,
  `discharge_date` date NOT NULL,
  `length_of_stay_days` int(11) NOT NULL,
  `admission_diagnosis` text NOT NULL,
  `final_diagnosis` text DEFAULT NULL,
  `hospital_course` text NOT NULL,
  `procedures_done` text DEFAULT NULL,
  `investigations_summary` text DEFAULT NULL,
  `treatment_given` text DEFAULT NULL,
  `discharge_medications` text NOT NULL,
  `discharge_instructions` text NOT NULL,
  `diet_instructions` text DEFAULT NULL,
  `activity_restrictions` text DEFAULT NULL,
  `condition_on_discharge` varchar(50) NOT NULL,
  `follow_up_date` date DEFAULT NULL,
  `follow_up_instructions` text DEFAULT NULL,
  `follow_up_department` varchar(100) DEFAULT NULL,
  `document_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_discharge_summary_number` (`institute_id`,`summary_number`),
  KEY `discharge_summaries_branch_id_foreign` (`branch_id`),
  KEY `discharge_summaries_patient_id_foreign` (`patient_id`),
  KEY `discharge_summaries_prepared_by_foreign` (`prepared_by`),
  KEY `discharge_summaries_document_id_foreign` (`document_id`),
  KEY `discharge_summaries_institute_id_patient_id_index` (`institute_id`,`patient_id`),
  KEY `discharge_summaries_admission_id_index` (`admission_id`),
  CONSTRAINT `discharge_summaries_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discharge_summaries_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `discharge_summaries_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `medical_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `discharge_summaries_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discharge_summaries_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discharge_summaries_prepared_by_foreign` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dividend_payouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dividend_payouts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dividend_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `shareholder_id` bigint(20) unsigned NOT NULL,
  `shares` int(11) NOT NULL,
  `gross_amount` decimal(15,2) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 10.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL,
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `paid_date` date DEFAULT NULL,
  `payment_method` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dividend_payouts_dividend_id_shareholder_id_unique` (`dividend_id`,`shareholder_id`),
  KEY `dividend_payouts_shareholder_id_foreign` (`shareholder_id`),
  KEY `dividend_payouts_institute_id_status_index` (`institute_id`,`status`),
  CONSTRAINT `dividend_payouts_dividend_id_foreign` FOREIGN KEY (`dividend_id`) REFERENCES `dividends` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dividend_payouts_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dividend_payouts_shareholder_id_foreign` FOREIGN KEY (`shareholder_id`) REFERENCES `shareholders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dividends`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dividends` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `reference_no` varchar(30) DEFAULT NULL,
  `declared_date` date NOT NULL,
  `record_date` date DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `financial_year` varchar(20) NOT NULL,
  `total_dividend` decimal(15,2) NOT NULL,
  `per_share_amount` decimal(15,4) NOT NULL,
  `total_shares` int(11) NOT NULL,
  `total_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_net` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','declared','paid','cancelled') NOT NULL DEFAULT 'draft',
  `board_resolution` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dividends_institute_id_reference_no_unique` (`institute_id`,`reference_no`),
  KEY `dividends_institute_id_financial_year_index` (`institute_id`,`financial_year`),
  KEY `dividends_institute_id_status_index` (`institute_id`,`status`),
  CONSTRAINT `dividends_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `doctor_availabilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `doctor_availabilities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `doctor_id` bigint(20) unsigned NOT NULL,
  `day_of_week` enum('sunday','monday','tuesday','wednesday','thursday','friday','saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_duration` int(11) NOT NULL DEFAULT 10,
  `room_no` varchar(50) DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_doctor_day_time` (`doctor_id`,`day_of_week`,`start_time`),
  KEY `doctor_availabilities_doctor_id_index` (`doctor_id`),
  KEY `doctor_availabilities_day_of_week_index` (`day_of_week`),
  CONSTRAINT `doctor_availabilities_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `medical_doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `doctor_branch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `doctor_branch` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `doctor_id` bigint(20) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doctor_branch` (`branch_id`,`doctor_id`),
  KEY `doctor_branch_doctor_id_foreign` (`doctor_id`),
  KEY `doctor_branch_tenant_branch_index` (`institute_id`,`branch_id`),
  CONSTRAINT `doctor_branch_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doctor_branch_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `medical_doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doctor_branch_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `emergency_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `emergency_visits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `visit_number` varchar(50) NOT NULL,
  `patient_id` bigint(20) unsigned DEFAULT NULL,
  `patient_name_temp` varchar(200) DEFAULT NULL,
  `patient_age` int(11) DEFAULT NULL,
  `patient_gender` varchar(10) DEFAULT NULL,
  `patient_phone` varchar(20) DEFAULT NULL,
  `triage_level` varchar(20) NOT NULL DEFAULT 'green',
  `triaged_at` timestamp NULL DEFAULT NULL,
  `triaged_by` bigint(20) unsigned DEFAULT NULL,
  `arrival_mode` varchar(30) DEFAULT NULL,
  `arrival_reference` varchar(200) DEFAULT NULL,
  `arrived_at` timestamp NULL DEFAULT NULL,
  `chief_complaint` text DEFAULT NULL,
  `history_notes` text DEFAULT NULL,
  `vitals_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`vitals_snapshot`)),
  `examination_findings` text DEFAULT NULL,
  `provisional_diagnosis` text DEFAULT NULL,
  `treatment_given` text DEFAULT NULL,
  `attending_doctor_id` bigint(20) unsigned DEFAULT NULL,
  `attended_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'waiting',
  `disposition` varchar(50) DEFAULT NULL,
  `admission_id` bigint(20) unsigned DEFAULT NULL,
  `disposition_at` timestamp NULL DEFAULT NULL,
  `disposition_by` bigint(20) unsigned DEFAULT NULL,
  `disposition_notes` text DEFAULT NULL,
  `triage_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_emergency_visit_number` (`institute_id`,`visit_number`),
  KEY `emergency_visits_branch_id_foreign` (`branch_id`),
  KEY `emergency_visits_patient_id_foreign` (`patient_id`),
  KEY `emergency_visits_attending_doctor_id_foreign` (`attending_doctor_id`),
  KEY `emergency_visits_triaged_by_foreign` (`triaged_by`),
  KEY `emergency_visits_disposition_by_foreign` (`disposition_by`),
  KEY `emergency_visits_admission_id_foreign` (`admission_id`),
  KEY `emergency_visits_institute_id_status_index` (`institute_id`,`status`),
  KEY `emergency_visits_institute_id_triage_level_status_index` (`institute_id`,`triage_level`,`status`),
  KEY `emergency_visits_institute_id_arrived_at_index` (`institute_id`,`arrived_at`),
  CONSTRAINT `emergency_visits_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `emergency_visits_attending_doctor_id_foreign` FOREIGN KEY (`attending_doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `emergency_visits_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `emergency_visits_disposition_by_foreign` FOREIGN KEY (`disposition_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `emergency_visits_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `emergency_visits_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `emergency_visits_triaged_by_foreign` FOREIGN KEY (`triaged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `encounter_diagnoses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encounter_diagnoses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `encounter_id` bigint(20) unsigned NOT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `label` varchar(255) NOT NULL,
  `diagnosis_type` varchar(20) NOT NULL DEFAULT 'secondary',
  `source` varchar(20) NOT NULL DEFAULT 'free_text',
  `mapping_status` varchar(20) NOT NULL DEFAULT 'unresolved',
  `code` varchar(60) DEFAULT NULL,
  `code_system` varchar(60) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `removed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `encounter_diagnoses_unique_label` (`encounter_id`,`label`,`status`),
  KEY `encounter_diagnoses_recorded_by_foreign` (`recorded_by`),
  KEY `encounter_diagnoses_institute_id_index` (`institute_id`),
  KEY `encounter_diagnoses_encounter_id_index` (`encounter_id`),
  KEY `encounter_diagnoses_status_index` (`status`),
  KEY `encounter_diagnoses_tenant_status_index` (`institute_id`,`status`),
  CONSTRAINT `encounter_diagnoses_encounter_id_foreign` FOREIGN KEY (`encounter_id`) REFERENCES `medical_encounters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `encounter_diagnoses_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `encounter_diagnoses_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `component_marks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`component_marks`)),
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exam_subject_components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_subject_components` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `exam_subject_id` bigint(20) unsigned NOT NULL,
  `component_name` varchar(80) NOT NULL,
  `max_marks` decimal(7,2) NOT NULL DEFAULT 0.00,
  `weight` decimal(5,2) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exam_subject_components_exam_subject_id_sort_order_index` (`exam_subject_id`,`sort_order`),
  CONSTRAINT `exam_subject_components_exam_subject_id_foreign` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `expense_number` varchar(50) NOT NULL,
  `paid_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `payment_account_id` bigint(20) unsigned NOT NULL,
  `expense_date` date NOT NULL,
  `vendor_name` varchar(200) DEFAULT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `expense_category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'BDT',
  `exchange_rate` decimal(15,6) NOT NULL DEFAULT 1.000000,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_group_id` bigint(20) unsigned DEFAULT NULL,
  `expense_account_id` bigint(20) unsigned NOT NULL,
  `journal_entry_id` bigint(20) unsigned DEFAULT NULL,
  `is_billable` tinyint(1) NOT NULL DEFAULT 0,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `markup_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `billable_amount` decimal(15,2) DEFAULT NULL,
  `billing_status` varchar(20) NOT NULL DEFAULT 'unbillable',
  `billed_invoice_id` bigint(20) unsigned DEFAULT NULL,
  `billed_at` timestamp NULL DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_expense_number` (`institute_id`,`expense_number`),
  KEY `expenses_branch_id_foreign` (`branch_id`),
  KEY `expenses_paid_by_user_id_foreign` (`paid_by_user_id`),
  KEY `expenses_payment_account_id_foreign` (`payment_account_id`),
  KEY `expenses_expense_account_id_foreign` (`expense_account_id`),
  KEY `expenses_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `expenses_tax_group_id_foreign` (`tax_group_id`),
  KEY `expenses_billed_invoice_id_foreign` (`billed_invoice_id`),
  KEY `expenses_institute_id_expense_date_index` (`institute_id`,`expense_date`),
  KEY `idx_billable_status` (`institute_id`,`is_billable`,`billing_status`),
  KEY `expenses_customer_id_billing_status_index` (`customer_id`,`billing_status`),
  CONSTRAINT `expenses_billed_invoice_id_foreign` FOREIGN KEY (`billed_invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `parties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_expense_account_id_foreign` FOREIGN KEY (`expense_account_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `expenses_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_paid_by_user_id_foreign` FOREIGN KEY (`paid_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_payment_account_id_foreign` FOREIGN KEY (`payment_account_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `expenses_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feature_registry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feature_registry` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `feature_key` varchar(100) NOT NULL,
  `module_key` varchar(60) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_feature_key` varchar(100) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('active','inactive','coming_soon') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `feature_registry_feature_key_unique` (`feature_key`),
  KEY `feature_registry_module_key_status_index` (`module_key`,`status`),
  KEY `feature_registry_module_key_index` (`module_key`),
  KEY `feature_registry_parent_feature_key_index` (`parent_feature_key`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `home_page_countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `home_page_countries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `home_page_id` bigint(20) unsigned NOT NULL,
  `country_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `home_page_countries_home_page_id_country_id_unique` (`home_page_id`,`country_id`),
  KEY `home_page_countries_country_id_foreign` (`country_id`),
  CONSTRAINT `home_page_countries_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `home_page_countries_home_page_id_foreign` FOREIGN KEY (`home_page_id`) REFERENCES `home_pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `home_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `home_pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `description` text DEFAULT NULL,
  `hero_title` varchar(255) NOT NULL,
  `hero_subtitle` text DEFAULT NULL,
  `hero_badge` varchar(255) DEFAULT NULL,
  `hero_cta_text` varchar(100) NOT NULL DEFAULT 'Get Started Free',
  `hero_cta_url` varchar(500) DEFAULT NULL,
  `hero_image_url` varchar(500) DEFAULT NULL,
  `sections_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sections_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_global` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `home_pages_slug_unique` (`slug`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `hr_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) DEFAULT NULL,
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
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `marital_status` enum('single','married','divorced','separated','widowed') DEFAULT NULL,
  `education_qualification` varchar(500) DEFAULT NULL,
  `expertise` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`expertise`)),
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `present_address` text DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
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
  `present_country_id` bigint(20) unsigned DEFAULT NULL,
  `present_admin_1_id` bigint(20) unsigned DEFAULT NULL,
  `present_admin_2_id` bigint(20) unsigned DEFAULT NULL,
  `present_admin_3_id` bigint(20) unsigned DEFAULT NULL,
  `present_zip_code` varchar(10) DEFAULT NULL,
  `permanent_country_id` bigint(20) unsigned DEFAULT NULL,
  `permanent_admin_1_id` bigint(20) unsigned DEFAULT NULL,
  `permanent_admin_2_id` bigint(20) unsigned DEFAULT NULL,
  `permanent_admin_3_id` bigint(20) unsigned DEFAULT NULL,
  `permanent_zip_code` varchar(10) DEFAULT NULL,
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `industries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `industries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `code` varchar(30) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `industries_slug_unique` (`slug`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `industry_subcategories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `industry_subcategories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `industry_key` varchar(60) NOT NULL,
  `subcategory_key` varchar(60) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(60) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `industry_subcategories_industry_key_subcategory_key_unique` (`industry_key`,`subcategory_key`),
  KEY `industry_subcategories_industry_key_index` (`industry_key`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `institute_feature_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_feature_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `feature_key` varchar(100) NOT NULL,
  `enabled` tinyint(1) NOT NULL,
  `overridden_by` bigint(20) unsigned DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ifo_institute_feature_unique` (`institute_id`,`feature_key`),
  KEY `institute_feature_overrides_feature_key_index` (`feature_key`),
  KEY `institute_feature_overrides_overridden_by_foreign` (`overridden_by`),
  CONSTRAINT `institute_feature_overrides_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `institute_feature_overrides_overridden_by_foreign` FOREIGN KEY (`overridden_by`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `institute_medicines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_medicines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `medicine_product_id` bigint(20) unsigned NOT NULL,
  `local_code` varchar(50) NOT NULL,
  `local_name` varchar(200) DEFAULT NULL,
  `preferred` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `purchase_price` decimal(15,2) DEFAULT NULL,
  `selling_price` decimal(15,2) DEFAULT NULL,
  `vat_percentage` decimal(5,2) DEFAULT NULL,
  `reorder_level` int(11) DEFAULT NULL,
  `reorder_quantity` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_institute_local_code` (`institute_id`,`local_code`),
  KEY `institute_medicines_medicine_product_id_foreign` (`medicine_product_id`),
  KEY `institute_medicines_institute_id_active_index` (`institute_id`,`active`),
  CONSTRAINT `institute_medicines_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `institute_medicines_medicine_product_id_foreign` FOREIGN KEY (`medicine_product_id`) REFERENCES `medicine_products` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `institute_module_overrides_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_module_overrides_archive` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `module_key` varchar(60) NOT NULL,
  `enabled` tinyint(4) NOT NULL,
  `overridden_by` bigint(20) unsigned DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_by` bigint(20) unsigned DEFAULT NULL,
  `archive_reason` varchar(60) DEFAULT NULL,
  `old_package_id` bigint(20) unsigned DEFAULT NULL,
  `new_package_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_institute` (`institute_id`),
  KEY `idx_archived_at` (`archived_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `institute_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institute_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dgda_enabled` tinyint(1) NOT NULL DEFAULT 0,
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
  `date_format` varchar(10) NOT NULL DEFAULT 'dmy',
  `notification_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_settings`)),
  `certificate_approval_mode` varchar(20) NOT NULL DEFAULT 'admin',
  `sales_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sales_config`)),
  `purchase_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`purchase_config`)),
  `academic_unit_label` varchar(40) DEFAULT NULL,
  `structure_template_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_institute_settings_institute` (`institute_id`),
  KEY `institute_settings_structure_template_id_foreign` (`structure_template_id`),
  KEY `institute_settings_certificate_approval_mode_index` (`certificate_approval_mode`),
  KEY `institute_settings_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_institute_settings_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `institute_settings_structure_template_id_foreign` FOREIGN KEY (`structure_template_id`) REFERENCES `structure_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `industry_id` bigint(20) unsigned DEFAULT NULL,
  `sub_industry` varchar(60) DEFAULT NULL,
  `subcategory_key` varchar(60) DEFAULT NULL,
  `terminology_overrides` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`terminology_overrides`)),
  `rule_overrides` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rule_overrides`)),
  `sub_industry_id` bigint(20) unsigned DEFAULT NULL,
  `short_name` varchar(60) DEFAULT NULL,
  `slug` varchar(80) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `cover_photo` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `country` varchar(80) NOT NULL DEFAULT 'Bangladesh',
  `country_code` char(2) DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
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
  `advanced_accounting_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `business_entity_type` varchar(30) DEFAULT NULL,
  `authorized_capital` decimal(15,2) DEFAULT NULL,
  `issued_capital` decimal(15,2) DEFAULT NULL,
  `paid_up_capital` decimal(15,2) DEFAULT NULL,
  `shares_outstanding` bigint(20) unsigned DEFAULT NULL,
  `share_face_value` decimal(10,2) DEFAULT 10.00,
  `incorporation_date` date DEFAULT NULL,
  `registration_no` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_institutes_slug` (`slug`),
  UNIQUE KEY `uq_institutes_uuid` (`uuid`),
  UNIQUE KEY `uq_institutes_institute_code` (`institute_code`),
  UNIQUE KEY `institutes_uid_unique` (`uid`),
  KEY `idx_institutes_status` (`status`),
  KEY `idx_institutes_package` (`package_id`),
  KEY `institutes_industry_id_index` (`industry_id`),
  KEY `institutes_sub_industry_id_index` (`sub_industry_id`),
  KEY `fk_institutes_country` (`country_id`),
  KEY `institutes_country_code_index` (`country_code`),
  KEY `institutes_subcategory_key_index` (`subcategory_key`),
  CONSTRAINT `fk_institutes_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_institutes_package` FOREIGN KEY (`package_id`) REFERENCES `subscription_packages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `institutes_industry_id_foreign` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `institutes_sub_industry_id_foreign` FOREIGN KEY (`sub_industry_id`) REFERENCES `sub_industries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `reference_type` varchar(255) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `progressive_contract_id` bigint(20) unsigned DEFAULT NULL,
  `is_progressive` tinyint(1) NOT NULL DEFAULT 0,
  `is_final_progressive` tinyint(1) NOT NULL DEFAULT 0,
  `milestone_name` varchar(200) DEFAULT NULL,
  `progress_percentage` decimal(5,2) DEFAULT NULL,
  `cumulative_billed` decimal(15,2) NOT NULL DEFAULT 0.00,
  `retention_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
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
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_reason` varchar(255) DEFAULT NULL,
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
  KEY `invoices_progressive_contract_id_foreign` (`progressive_contract_id`),
  KEY `invoices_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_invoices_created_by` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoices_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `student_enrollments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoices_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invoices_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_progressive_contract_id_foreign` FOREIGN KEY (`progressive_contract_id`) REFERENCES `progressive_contracts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_sales_delivery_id_foreign` FOREIGN KEY (`sales_delivery_id`) REFERENCES `sales_deliveries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lab_analyzer_parameter_maps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_analyzer_parameter_maps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `analyzer_id` bigint(20) unsigned NOT NULL,
  `vendor_code` varchar(50) NOT NULL,
  `vendor_name` varchar(200) DEFAULT NULL,
  `universal_code` varchar(50) NOT NULL,
  `lab_test_id` bigint(20) unsigned DEFAULT NULL,
  `parameter_key` varchar(50) DEFAULT NULL,
  `unit_from` varchar(30) DEFAULT NULL,
  `unit_to` varchar(30) DEFAULT NULL,
  `conversion_factor` decimal(15,8) NOT NULL DEFAULT 1.00000000,
  `ref_low` decimal(15,4) DEFAULT NULL,
  `ref_high` decimal(15,4) DEFAULT NULL,
  `ref_range_text` varchar(100) DEFAULT NULL,
  `ref_range_approved_by` bigint(20) unsigned DEFAULT NULL,
  `ref_range_approved_at` timestamp NULL DEFAULT NULL,
  `ref_range_approval_notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_analyzer_vendor_code` (`analyzer_id`,`vendor_code`),
  KEY `lab_analyzer_parameter_maps_lab_test_id_foreign` (`lab_test_id`),
  KEY `lab_analyzer_parameter_maps_institute_id_universal_code_index` (`institute_id`,`universal_code`),
  KEY `lab_analyzer_parameter_maps_analyzer_id_is_active_index` (`analyzer_id`,`is_active`),
  CONSTRAINT `lab_analyzer_parameter_maps_analyzer_id_foreign` FOREIGN KEY (`analyzer_id`) REFERENCES `lab_analyzers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_analyzer_parameter_maps_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_analyzer_parameter_maps_lab_test_id_foreign` FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lab_analyzers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_analyzers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `manufacturer` varchar(200) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_no` varchar(100) DEFAULT NULL,
  `instrument_type` varchar(50) NOT NULL,
  `protocol` varchar(30) NOT NULL,
  `adapter_key` varchar(100) NOT NULL,
  `adapter_version` varchar(20) NOT NULL DEFAULT 'v1',
  `connection_type` varchar(30) NOT NULL,
  `host` varchar(100) DEFAULT NULL,
  `port` int(11) DEFAULT NULL,
  `serial_port` varchar(50) DEFAULT NULL,
  `baud_rate` int(11) DEFAULT NULL,
  `parity` varchar(10) DEFAULT NULL,
  `stop_bits` int(11) DEFAULT NULL,
  `data_bits` int(11) DEFAULT NULL,
  `capabilities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`capabilities`)),
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'inactive',
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `last_error_at` timestamp NULL DEFAULT NULL,
  `last_error_message` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_lab_analyzers_code` (`institute_id`,`code`),
  UNIQUE KEY `uniq_lab_analyzers_serial` (`institute_id`,`serial_no`),
  KEY `lab_analyzers_branch_id_foreign` (`branch_id`),
  KEY `lab_analyzers_institute_id_is_enabled_index` (`institute_id`,`is_enabled`),
  KEY `lab_analyzers_institute_id_status_index` (`institute_id`,`status`),
  CONSTRAINT `lab_analyzers_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_analyzers_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lab_device_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_device_credentials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `analyzer_id` bigint(20) unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `previous_token_hash` varchar(64) DEFAULT NULL,
  `previous_token_prefix` varchar(12) DEFAULT NULL,
  `previous_token_expires_at` timestamp NULL DEFAULT NULL,
  `token_prefix` varchar(12) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `abilities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`abilities`)),
  `rotated_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_device_credential_analyzer` (`analyzer_id`),
  KEY `lab_device_credentials_token_prefix_index` (`token_prefix`),
  KEY `lab_device_credentials_institute_id_revoked_at_index` (`institute_id`,`revoked_at`),
  CONSTRAINT `lab_device_credentials_analyzer_id_foreign` FOREIGN KEY (`analyzer_id`) REFERENCES `lab_analyzers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_device_credentials_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lab_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `analyzer_id` bigint(20) unsigned DEFAULT NULL,
  `direction` varchar(20) NOT NULL DEFAULT 'inbound',
  `protocol` varchar(30) NOT NULL,
  `adapter_key` varchar(100) DEFAULT NULL,
  `adapter_version` varchar(20) DEFAULT NULL,
  `message_id` varchar(100) DEFAULT NULL,
  `idempotency_hash` varchar(64) NOT NULL,
  `raw_payload` mediumtext NOT NULL,
  `parsed_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parsed_json`)),
  `status` varchar(20) NOT NULL DEFAULT 'received',
  `resolution_status` varchar(30) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `resolved_by` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `error_code` varchar(50) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `last_attempted_at` timestamp NULL DEFAULT NULL,
  `accession_number` varchar(100) DEFAULT NULL,
  `sample_id` bigint(20) unsigned DEFAULT NULL,
  `lab_order_id` bigint(20) unsigned DEFAULT NULL,
  `source_ip` varchar(45) DEFAULT NULL,
  `source_host` varchar(100) DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_message_idempotency` (`institute_id`,`idempotency_hash`),
  KEY `lab_messages_sample_id_foreign` (`sample_id`),
  KEY `lab_messages_lab_order_id_foreign` (`lab_order_id`),
  KEY `lab_messages_institute_id_status_created_at_index` (`institute_id`,`status`,`created_at`),
  KEY `lab_messages_analyzer_id_received_at_index` (`analyzer_id`,`received_at`),
  KEY `lab_messages_accession_number_index` (`accession_number`),
  KEY `lab_messages_message_id_index` (`message_id`),
  CONSTRAINT `lab_messages_analyzer_id_foreign` FOREIGN KEY (`analyzer_id`) REFERENCES `lab_analyzers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_messages_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_messages_lab_order_id_foreign` FOREIGN KEY (`lab_order_id`) REFERENCES `lab_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_messages_sample_id_foreign` FOREIGN KEY (`sample_id`) REFERENCES `lab_samples` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lab_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `sample_id` bigint(20) unsigned DEFAULT NULL,
  `encounter_id` bigint(20) unsigned DEFAULT NULL,
  `doctor_id` bigint(20) unsigned DEFAULT NULL,
  `prescription_id` bigint(20) unsigned DEFAULT NULL,
  `order_number` varchar(50) NOT NULL,
  `accession_number` varchar(50) DEFAULT NULL,
  `order_date` date NOT NULL,
  `priority` enum('routine','urgent','emergency') NOT NULL DEFAULT 'routine',
  `status` enum('ordered','collected','processing','completed','cancelled') NOT NULL DEFAULT 'ordered',
  `clinical_notes` text DEFAULT NULL,
  `result_notes` text DEFAULT NULL,
  `collected_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `collected_by` bigint(20) unsigned DEFAULT NULL,
  `completed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lab_orders_institute_number` (`institute_id`,`order_number`),
  KEY `lab_orders_prescription_id_foreign` (`prescription_id`),
  KEY `lab_orders_collected_by_foreign` (`collected_by`),
  KEY `lab_orders_completed_by_foreign` (`completed_by`),
  KEY `lab_orders_institute_id_index` (`institute_id`),
  KEY `lab_orders_patient_id_index` (`patient_id`),
  KEY `lab_orders_order_number_index` (`order_number`),
  KEY `lab_orders_status_index` (`status`),
  KEY `lab_orders_doctor_id_foreign` (`doctor_id`),
  KEY `lab_orders_encounter_id_index` (`encounter_id`),
  KEY `lab_orders_branch_id_index` (`branch_id`),
  KEY `lab_orders_sample_id_foreign` (`sample_id`),
  KEY `lab_orders_accession_number_index` (`accession_number`),
  CONSTRAINT `lab_orders_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_orders_collected_by_foreign` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_orders_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_orders_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_orders_encounter_id_foreign` FOREIGN KEY (`encounter_id`) REFERENCES `medical_encounters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_orders_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_orders_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_orders_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_orders_sample_id_foreign` FOREIGN KEY (`sample_id`) REFERENCES `lab_samples` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lab_result_parameters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_result_parameters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `lab_result_id` bigint(20) unsigned NOT NULL,
  `parameter_key` varchar(50) NOT NULL,
  `parameter_name` varchar(200) DEFAULT NULL,
  `value_decimal` decimal(15,4) DEFAULT NULL,
  `value_text` varchar(100) DEFAULT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `flag` varchar(10) DEFAULT NULL,
  `ref_low` decimal(15,4) DEFAULT NULL,
  `ref_high` decimal(15,4) DEFAULT NULL,
  `ref_range_text` varchar(100) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `analyzer_id` bigint(20) unsigned DEFAULT NULL,
  `lab_message_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_result_parameter` (`lab_result_id`,`parameter_key`),
  KEY `lab_result_parameters_analyzer_id_foreign` (`analyzer_id`),
  KEY `lab_result_parameters_lab_message_id_foreign` (`lab_message_id`),
  KEY `lab_result_parameters_institute_id_parameter_key_index` (`institute_id`,`parameter_key`),
  CONSTRAINT `lab_result_parameters_analyzer_id_foreign` FOREIGN KEY (`analyzer_id`) REFERENCES `lab_analyzers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_result_parameters_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_result_parameters_lab_message_id_foreign` FOREIGN KEY (`lab_message_id`) REFERENCES `lab_messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_result_parameters_lab_result_id_foreign` FOREIGN KEY (`lab_result_id`) REFERENCES `lab_results` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lab_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lab_order_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `lab_test_id` bigint(20) unsigned NOT NULL,
  `analyzer_id` bigint(20) unsigned DEFAULT NULL,
  `lab_message_id` bigint(20) unsigned DEFAULT NULL,
  `result_value` varchar(255) DEFAULT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `flag` varchar(10) DEFAULT NULL,
  `result_text` text DEFAULT NULL,
  `normal_range` text DEFAULT NULL,
  `status` enum('pending','normal','abnormal','critical') NOT NULL DEFAULT 'pending',
  `comments` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lab_results_lab_test_id_foreign` (`lab_test_id`),
  KEY `lab_results_lab_order_id_index` (`lab_order_id`),
  KEY `lab_results_status_index` (`status`),
  KEY `lab_results_analyzer_id_foreign` (`analyzer_id`),
  KEY `lab_results_lab_message_id_foreign` (`lab_message_id`),
  KEY `lab_results_institute_status_index` (`institute_id`,`status`),
  CONSTRAINT `lab_results_analyzer_id_foreign` FOREIGN KEY (`analyzer_id`) REFERENCES `lab_analyzers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_results_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_results_lab_message_id_foreign` FOREIGN KEY (`lab_message_id`) REFERENCES `lab_messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_results_lab_order_id_foreign` FOREIGN KEY (`lab_order_id`) REFERENCES `lab_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_results_lab_test_id_foreign` FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lab_samples`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_samples` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `accession_number` varchar(50) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `lab_order_id` bigint(20) unsigned DEFAULT NULL,
  `sample_type` varchar(50) DEFAULT NULL,
  `container_type` varchar(50) DEFAULT NULL,
  `collected_at` timestamp NULL DEFAULT NULL,
  `collected_by` bigint(20) unsigned DEFAULT NULL,
  `collection_site` varchar(100) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'collected',
  `rejection_reason` text DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sample_accession` (`institute_id`,`accession_number`),
  KEY `lab_samples_branch_id_foreign` (`branch_id`),
  KEY `lab_samples_lab_order_id_foreign` (`lab_order_id`),
  KEY `lab_samples_collected_by_foreign` (`collected_by`),
  KEY `lab_samples_institute_id_status_index` (`institute_id`,`status`),
  KEY `lab_samples_institute_id_barcode_index` (`institute_id`,`barcode`),
  KEY `lab_samples_patient_id_collected_at_index` (`patient_id`,`collected_at`),
  CONSTRAINT `lab_samples_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_samples_collected_by_foreign` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_samples_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_samples_lab_order_id_foreign` FOREIGN KEY (`lab_order_id`) REFERENCES `lab_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_samples_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lab_tests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_tests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `normal_range` text DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_lab_tests_institute_code` (`institute_id`,`code`),
  KEY `lab_tests_institute_id_index` (`institute_id`),
  KEY `lab_tests_code_index` (`code`),
  CONSTRAINT `lab_tests_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lab_worklists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_worklists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `analyzer_id` bigint(20) unsigned NOT NULL,
  `sample_id` bigint(20) unsigned DEFAULT NULL,
  `lab_order_id` bigint(20) unsigned DEFAULT NULL,
  `order_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`order_snapshot`)),
  `tests_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tests_snapshot`)),
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `acked_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lab_worklists_sample_id_foreign` (`sample_id`),
  KEY `lab_worklists_lab_order_id_foreign` (`lab_order_id`),
  KEY `lab_worklists_institute_id_status_index` (`institute_id`,`status`),
  KEY `lab_worklists_analyzer_id_status_index` (`analyzer_id`,`status`),
  CONSTRAINT `lab_worklists_analyzer_id_foreign` FOREIGN KEY (`analyzer_id`) REFERENCES `lab_analyzers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_worklists_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_worklists_lab_order_id_foreign` FOREIGN KEY (`lab_order_id`) REFERENCES `lab_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_worklists_sample_id_foreign` FOREIGN KEY (`sample_id`) REFERENCES `lab_samples` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `meal_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meal_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `diet_plan_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `meal_date` date NOT NULL,
  `meal_type` varchar(30) NOT NULL,
  `scheduled_time` time NOT NULL,
  `menu_items` text NOT NULL,
  `calories` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'scheduled',
  `prepared_at` timestamp NULL DEFAULT NULL,
  `prepared_by` bigint(20) unsigned DEFAULT NULL,
  `served_at` timestamp NULL DEFAULT NULL,
  `served_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `meal_schedules_branch_id_foreign` (`branch_id`),
  KEY `meal_schedules_institute_id_meal_date_meal_type_index` (`institute_id`,`meal_date`,`meal_type`),
  KEY `meal_schedules_diet_plan_id_meal_date_index` (`diet_plan_id`,`meal_date`),
  CONSTRAINT `meal_schedules_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `meal_schedules_diet_plan_id_foreign` FOREIGN KEY (`diet_plan_id`) REFERENCES `diet_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `meal_schedules_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medical_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medical_departments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medical_departments_institute_id_index` (`institute_id`),
  CONSTRAINT `medical_departments_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medical_doctors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medical_doctors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `specialty_id` bigint(20) unsigned DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `registration_number` varchar(50) NOT NULL,
  `qualification` text DEFAULT NULL,
  `experience_years` int(11) NOT NULL DEFAULT 0,
  `employment_type` varchar(30) DEFAULT NULL,
  `designation_id` bigint(20) unsigned DEFAULT NULL,
  `doctor_fee_percentage` decimal(5,2) DEFAULT NULL,
  `allow_discount` tinyint(1) NOT NULL DEFAULT 0,
  `max_discount_percent` decimal(5,2) DEFAULT NULL,
  `consultation_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
  `first_visit_fee` decimal(10,2) NOT NULL DEFAULT 700.00,
  `follow_up_fee` decimal(10,2) NOT NULL DEFAULT 500.00,
  `follow_up_days` int(11) NOT NULL DEFAULT 30,
  `collect_fee_before_visit` tinyint(1) NOT NULL DEFAULT 0,
  `chamber_address` text DEFAULT NULL,
  `room_no` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medical_doctors_registration_number_unique` (`registration_number`),
  KEY `medical_doctors_institute_id_index` (`institute_id`),
  KEY `medical_doctors_user_id_index` (`user_id`),
  KEY `medical_doctors_specialty_id_index` (`specialty_id`),
  KEY `medical_doctors_registration_number_index` (`registration_number`),
  KEY `medical_doctors_department_id_index` (`department_id`),
  KEY `medical_doctors_designation_id_index` (`designation_id`),
  CONSTRAINT `medical_doctors_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `medical_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_doctors_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_doctors_specialty_id_foreign` FOREIGN KEY (`specialty_id`) REFERENCES `medical_specialties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_doctors_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medical_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medical_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `document_number` varchar(50) DEFAULT NULL,
  `document_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `original_filename` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_hash` varchar(64) DEFAULT NULL,
  `page_count` int(11) DEFAULT NULL,
  `source_type` varchar(100) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `is_confidential` tinyint(1) NOT NULL DEFAULT 0,
  `is_patient_visible` tinyint(1) NOT NULL DEFAULT 0,
  `access_level` varchar(20) NOT NULL DEFAULT 'clinical',
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `document_date` date DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned NOT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_document_number` (`institute_id`,`document_number`),
  KEY `medical_documents_branch_id_foreign` (`branch_id`),
  KEY `medical_documents_patient_id_foreign` (`patient_id`),
  KEY `medical_documents_uploaded_by_foreign` (`uploaded_by`),
  KEY `medical_documents_verified_by_foreign` (`verified_by`),
  KEY `medical_documents_institute_id_patient_id_document_type_index` (`institute_id`,`patient_id`,`document_type`),
  KEY `medical_documents_institute_id_document_date_index` (`institute_id`,`document_date`),
  KEY `medical_documents_source_type_source_id_index` (`source_type`,`source_id`),
  CONSTRAINT `medical_documents_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_documents_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_documents_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  CONSTRAINT `medical_documents_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medical_encounters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medical_encounters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `appointment_id` bigint(20) unsigned DEFAULT NULL,
  `doctor_id` bigint(20) unsigned DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `specialty_id` bigint(20) unsigned DEFAULT NULL,
  `admission_id` bigint(20) unsigned DEFAULT NULL,
  `encounter_number` varchar(50) NOT NULL,
  `encounter_type` varchar(20) NOT NULL DEFAULT 'OPD',
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `chief_complaint` text DEFAULT NULL,
  `history_of_present_illness` text DEFAULT NULL,
  `examination_notes` text DEFAULT NULL,
  `assessment_notes` text DEFAULT NULL,
  `plan_notes` text DEFAULT NULL,
  `follow_up_notes` text DEFAULT NULL,
  `diagnosis_text` text DEFAULT NULL,
  `diagnosis_code` varchar(60) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `completed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medical_encounters_institute_number` (`institute_id`,`encounter_number`),
  UNIQUE KEY `medical_encounters_appointment_id_unique` (`appointment_id`),
  KEY `medical_encounters_department_id_foreign` (`department_id`),
  KEY `medical_encounters_specialty_id_foreign` (`specialty_id`),
  KEY `medical_encounters_admission_id_foreign` (`admission_id`),
  KEY `medical_encounters_institute_id_index` (`institute_id`),
  KEY `medical_encounters_patient_id_index` (`patient_id`),
  KEY `medical_encounters_doctor_id_index` (`doctor_id`),
  KEY `medical_encounters_status_index` (`status`),
  KEY `medical_encounters_started_at_index` (`started_at`),
  KEY `encounters_tenant_patient_status_index` (`institute_id`,`patient_id`,`status`),
  KEY `medical_encounters_branch_id_index` (`branch_id`),
  CONSTRAINT `medical_encounters_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_encounters_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_encounters_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_encounters_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `medical_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_encounters_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_encounters_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_encounters_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_encounters_specialty_id_foreign` FOREIGN KEY (`specialty_id`) REFERENCES `medical_specialties` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medical_follow_ups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medical_follow_ups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `encounter_id` bigint(20) unsigned DEFAULT NULL,
  `problem_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `planned_date` date NOT NULL,
  `reason` text NOT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'planned',
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medical_follow_ups_encounter_id_foreign` (`encounter_id`),
  KEY `medical_follow_ups_problem_id_foreign` (`problem_id`),
  KEY `medical_follow_ups_assigned_to_foreign` (`assigned_to`),
  KEY `medical_follow_ups_created_by_foreign` (`created_by`),
  KEY `medical_follow_ups_institute_id_index` (`institute_id`),
  KEY `medical_follow_ups_patient_id_index` (`patient_id`),
  KEY `medical_follow_ups_status_index` (`status`),
  KEY `medical_follow_ups_planned_date_index` (`planned_date`),
  KEY `follow_ups_tenant_patient_status_index` (`institute_id`,`patient_id`,`status`),
  KEY `medical_follow_ups_branch_id_index` (`branch_id`),
  CONSTRAINT `medical_follow_ups_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_follow_ups_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_follow_ups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_follow_ups_encounter_id_foreign` FOREIGN KEY (`encounter_id`) REFERENCES `medical_encounters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_follow_ups_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_follow_ups_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_follow_ups_problem_id_foreign` FOREIGN KEY (`problem_id`) REFERENCES `medical_patient_problems` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medical_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medical_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `admission_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `type` enum('opd','ipd','pharmacy','lab','surgery') NOT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `due_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','pending','paid','partial','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` enum('cash','card','bank_transfer','mobile_banking','tpa','other') DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `items_data` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medical_invoices_institute_number` (`institute_id`,`invoice_number`),
  KEY `medical_invoices_admission_id_foreign` (`admission_id`),
  KEY `medical_invoices_institute_id_index` (`institute_id`),
  KEY `medical_invoices_patient_id_index` (`patient_id`),
  KEY `medical_invoices_invoice_number_index` (`invoice_number`),
  KEY `medical_invoices_status_index` (`status`),
  KEY `medical_invoices_branch_id_index` (`branch_id`),
  KEY `medical_invoices_deleted_at_index` (`deleted_at`),
  CONSTRAINT `medical_invoices_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_invoices_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_invoices_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_invoices_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medical_patient_problems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medical_patient_problems` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `encounter_id` bigint(20) unsigned DEFAULT NULL,
  `encounter_diagnosis_id` bigint(20) unsigned DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `label` varchar(255) NOT NULL,
  `problem_type` varchar(20) NOT NULL DEFAULT 'other',
  `source` varchar(20) NOT NULL DEFAULT 'free_text',
  `mapping_status` varchar(20) NOT NULL DEFAULT 'unresolved',
  `code` varchar(60) DEFAULT NULL,
  `code_system` varchar(60) DEFAULT NULL,
  `onset_date` date DEFAULT NULL,
  `resolved_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medical_patient_problems_encounter_diagnosis_id_foreign` (`encounter_diagnosis_id`),
  KEY `medical_patient_problems_recorded_by_foreign` (`recorded_by`),
  KEY `medical_patient_problems_institute_id_index` (`institute_id`),
  KEY `medical_patient_problems_patient_id_index` (`patient_id`),
  KEY `medical_patient_problems_status_index` (`status`),
  KEY `patient_problems_tenant_patient_status_index` (`institute_id`,`patient_id`,`status`),
  KEY `medical_patient_problems_encounter_id_index` (`encounter_id`),
  CONSTRAINT `medical_patient_problems_encounter_diagnosis_id_foreign` FOREIGN KEY (`encounter_diagnosis_id`) REFERENCES `encounter_diagnoses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_patient_problems_encounter_id_foreign` FOREIGN KEY (`encounter_id`) REFERENCES `medical_encounters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_patient_problems_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_patient_problems_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_patient_problems_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medical_specialties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medical_specialties` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `department_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medical_specialties_institute_id_index` (`institute_id`),
  KEY `medical_specialties_department_id_index` (`department_id`),
  CONSTRAINT `medical_specialties_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `medical_departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_specialties_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medicine_code_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicine_code_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `medicine_id` bigint(20) unsigned NOT NULL,
  `old_code` varchar(50) DEFAULT NULL,
  `new_code` varchar(50) NOT NULL,
  `reason` varchar(100) NOT NULL DEFAULT 'sequential_migration',
  `migrated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `migrated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medicine_code_history_medicine_id_foreign` (`medicine_id`),
  KEY `medicine_code_history_institute_id_medicine_id_index` (`institute_id`,`medicine_id`),
  KEY `medicine_code_history_old_code_index` (`old_code`),
  KEY `medicine_code_history_new_code_index` (`new_code`),
  CONSTRAINT `medicine_code_history_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medicine_code_history_medicine_id_foreign` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medicine_concepts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicine_concepts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `canonical_name` varchar(190) NOT NULL,
  `normalized_name` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `concept_type` varchar(20) NOT NULL DEFAULT 'single',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicine_concepts_normalized_name_unique` (`normalized_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medicine_forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicine_forms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicine_forms_name_unique` (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medicine_identifiers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicine_identifiers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `medicine_product_id` bigint(20) unsigned DEFAULT NULL,
  `medicine_ingredient_id` bigint(20) unsigned DEFAULT NULL,
  `system` varchar(40) NOT NULL,
  `identifier_type` varchar(40) NOT NULL,
  `value` varchar(190) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_identifier_system_value` (`system`,`identifier_type`,`value`),
  KEY `medicine_identifiers_medicine_product_id_index` (`medicine_product_id`),
  KEY `identifiers_ingredient_fk` (`medicine_ingredient_id`),
  CONSTRAINT `identifiers_ingredient_fk` FOREIGN KEY (`medicine_ingredient_id`) REFERENCES `medicine_ingredients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medicine_identifiers_medicine_product_id_foreign` FOREIGN KEY (`medicine_product_id`) REFERENCES `medicine_products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medicine_import_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicine_import_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` char(36) NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `uploaded_by` bigint(20) unsigned NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `clean_rows` int(11) NOT NULL DEFAULT 0,
  `conflict_rows` int(11) NOT NULL DEFAULT 0,
  `error_rows` int(11) NOT NULL DEFAULT 0,
  `parsed_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parsed_data`)),
  `conflicts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`conflicts`)),
  `status` varchar(20) NOT NULL DEFAULT 'pending_review',
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `imported_count` int(11) NOT NULL DEFAULT 0,
  `skipped_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicine_import_batches_batch_id_unique` (`batch_id`),
  KEY `medicine_import_batches_uploaded_by_foreign` (`uploaded_by`),
  KEY `medicine_import_batches_institute_id_status_index` (`institute_id`,`status`),
  CONSTRAINT `medicine_import_batches_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medicine_import_batches_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medicine_ingredients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicine_ingredients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `canonical_name` varchar(190) NOT NULL,
  `normalized_name` varchar(190) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicine_ingredients_normalized_name_unique` (`normalized_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medicine_product_ingredients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicine_product_ingredients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `medicine_product_id` bigint(20) unsigned NOT NULL,
  `medicine_ingredient_id` bigint(20) unsigned NOT NULL,
  `strength_value` decimal(12,3) DEFAULT NULL,
  `strength_unit` varchar(20) DEFAULT NULL,
  `sequence` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_ingredient_seq` (`medicine_product_id`,`medicine_ingredient_id`,`sequence`),
  KEY `medicine_product_ingredients_medicine_ingredient_id_foreign` (`medicine_ingredient_id`),
  CONSTRAINT `medicine_product_ingredients_medicine_ingredient_id_foreign` FOREIGN KEY (`medicine_ingredient_id`) REFERENCES `medicine_ingredients` (`id`),
  CONSTRAINT `medicine_product_ingredients_medicine_product_id_foreign` FOREIGN KEY (`medicine_product_id`) REFERENCES `medicine_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medicine_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicine_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `medicine_concept_id` bigint(20) unsigned NOT NULL,
  `medicine_form_id` bigint(20) unsigned DEFAULT NULL,
  `medicine_route_id` bigint(20) unsigned DEFAULT NULL,
  `display_name` varchar(200) NOT NULL,
  `normalized_name` varchar(220) NOT NULL,
  `strength_raw` varchar(100) DEFAULT NULL,
  `brand_name` varchar(150) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `side_effects` text DEFAULT NULL,
  `contraindications` text DEFAULT NULL,
  `storage_conditions` text DEFAULT NULL,
  `requires_prescription` tinyint(1) NOT NULL DEFAULT 1,
  `is_controlled` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicine_products_normalized_name_unique` (`normalized_name`),
  KEY `medicine_products_medicine_form_id_foreign` (`medicine_form_id`),
  KEY `medicine_products_medicine_route_id_foreign` (`medicine_route_id`),
  KEY `medicine_products_medicine_concept_id_index` (`medicine_concept_id`),
  CONSTRAINT `medicine_products_medicine_concept_id_foreign` FOREIGN KEY (`medicine_concept_id`) REFERENCES `medicine_concepts` (`id`),
  CONSTRAINT `medicine_products_medicine_form_id_foreign` FOREIGN KEY (`medicine_form_id`) REFERENCES `medicine_forms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medicine_products_medicine_route_id_foreign` FOREIGN KEY (`medicine_route_id`) REFERENCES `medicine_routes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medicine_routes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicine_routes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicine_routes_name_unique` (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medicines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dgda_code` varchar(60) DEFAULT NULL,
  `dgda_dar_number` varchar(60) DEFAULT NULL,
  `dgda_concept_id` varchar(160) DEFAULT NULL,
  `dgda_synced_at` timestamp NULL DEFAULT NULL,
  `dgda_status` varchar(20) NOT NULL DEFAULT 'pending',
  `institute_id` bigint(20) unsigned NOT NULL,
  `medicine_product_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(50) DEFAULT NULL,
  `generic_name` varchar(150) NOT NULL,
  `brand_name` varchar(150) DEFAULT NULL,
  `normalized_name` varchar(250) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `dosage_form` varchar(50) NOT NULL,
  `strength` varchar(50) DEFAULT NULL,
  `unit` varchar(20) NOT NULL,
  `pack_size` int(11) NOT NULL DEFAULT 1,
  `purchase_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `vat_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `reorder_level` int(11) NOT NULL DEFAULT 0,
  `reorder_quantity` int(11) NOT NULL DEFAULT 0,
  `requires_prescription` tinyint(1) NOT NULL DEFAULT 1,
  `is_controlled` tinyint(1) NOT NULL DEFAULT 0,
  `side_effects` text DEFAULT NULL,
  `contraindications` text DEFAULT NULL,
  `storage_conditions` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicines_dgda_code_unique` (`dgda_code`),
  UNIQUE KEY `uniq_med_inst_norm` (`institute_id`,`normalized_name`),
  UNIQUE KEY `uniq_medicine_code_per_institute_deleted` (`institute_id`,`code`,`deleted_at`),
  KEY `medicines_institute_id_index` (`institute_id`),
  KEY `medicines_generic_name_index` (`generic_name`),
  KEY `medicines_code_index` (`code`),
  KEY `medicines_medicine_product_id_foreign` (`medicine_product_id`),
  KEY `idx_med_inst_norm` (`institute_id`,`normalized_name`),
  KEY `idx_medicines_deleted_at` (`deleted_at`),
  CONSTRAINT `medicines_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medicines_medicine_product_id_foreign` FOREIGN KEY (`medicine_product_id`) REFERENCES `medicine_products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mobile_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mobile_devices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `fcm_token` varchar(255) NOT NULL,
  `platform` enum('android','ios') NOT NULL,
  `device_name` varchar(120) NOT NULL,
  `app_version` varchar(20) DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `md_token_unique` (`institute_id`,`fcm_token`),
  KEY `md_user_idx` (`institute_id`,`user_id`),
  KEY `mobile_devices_institute_id_index` (`institute_id`),
  KEY `mobile_devices_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mobile_sync_idempotency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mobile_sync_idempotency` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `client_id` varchar(36) NOT NULL,
  `entity` varchar(60) NOT NULL,
  `server_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `msi_client_unique` (`institute_id`,`client_id`),
  KEY `msi_lookup_idx` (`institute_id`,`entity`,`created_at`),
  KEY `mobile_sync_idempotency_institute_id_index` (`institute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_access_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_access_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `module_key` varchar(60) NOT NULL,
  `action` varchar(60) NOT NULL COMMENT 'enabled, disabled, override_added, override_removed, package_changed',
  `risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `actor_id` bigint(20) unsigned DEFAULT NULL COMMENT 'User or PlatformAdmin ID',
  `actor_type` varchar(30) DEFAULT NULL COMMENT 'Realm: platform_admin|institute_user|user|guardian|platform_staff|system',
  `previous_state` varchar(60) DEFAULT NULL,
  `new_state` varchar(60) DEFAULT NULL,
  `package_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `reason` varchar(60) DEFAULT NULL COMMENT 'Structured decision reason code (e.g. PACKAGE_FEATURE_NOT_ENTITLED)',
  `feature_key` varchar(100) DEFAULT NULL COMMENT 'Feature key for feature-level decisions (e.g. medical.pharmacy)',
  `decision` varchar(10) DEFAULT NULL COMMENT 'Decision outcome: allow or deny',
  `request_id` varchar(36) DEFAULT NULL COMMENT 'HTTP request correlation ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `module_access_logs_institute_id_foreign` (`institute_id`),
  KEY `module_access_logs_package_id_foreign` (`package_id`),
  KEY `module_access_logs_risk_level_index` (`risk_level`),
  CONSTRAINT `module_access_logs_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `module_access_logs_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `subscription_packages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_registry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_registry` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(60) NOT NULL,
  `parent_key` varchar(60) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('core','industry') NOT NULL DEFAULT 'core',
  `is_core` tinyint(1) NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `dependencies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of module keys this module depends on' CHECK (json_valid(`dependencies`)),
  `sort_order` tinyint(4) NOT NULL DEFAULT 0,
  `icon` varchar(50) DEFAULT NULL,
  `coming_soon` tinyint(1) DEFAULT 0,
  `index_route` varchar(150) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_registry_key_unique` (`key`),
  KEY `module_registry_is_core_index` (`is_core`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_code` char(2) DEFAULT NULL,
  `module_key` varchar(60) NOT NULL,
  `rule_key` varchar(100) NOT NULL,
  `rule_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`rule_value`)),
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_rules_country_code_module_key_rule_key_unique` (`country_code`,`module_key`,`rule_key`),
  KEY `module_rules_module_key_index` (`module_key`),
  KEY `module_rules_country_code_index` (`country_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_terminology`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_terminology` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_code` char(2) DEFAULT NULL,
  `term_key` varchar(100) NOT NULL,
  `term_value` varchar(255) NOT NULL,
  `context` varchar(60) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_terminology_country_code_term_key_unique` (`country_code`,`term_key`),
  KEY `module_terminology_term_key_index` (`term_key`),
  KEY `module_terminology_country_code_index` (`country_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `number_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `number_sequences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `sequence_type` varchar(30) NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `last_number` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_number_sequences` (`institute_id`,`sequence_type`,`year`),
  CONSTRAINT `number_sequences_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nursing_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nursing_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admission_id` bigint(20) unsigned NOT NULL,
  `note` text NOT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `recorded_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nursing_notes_recorded_by_foreign` (`recorded_by`),
  KEY `nursing_notes_admission_id_index` (`admission_id`),
  KEY `nursing_notes_recorded_at_index` (`recorded_at`),
  CONSTRAINT `nursing_notes_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nursing_notes_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `package_country_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `package_country_prices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint(20) unsigned NOT NULL,
  `country_code` varchar(2) NOT NULL,
  `currency_code` varchar(3) NOT NULL,
  `price_monthly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_yearly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT NULL,
  `discount_ends_at` date DEFAULT NULL,
  `trial_days` smallint(5) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pkg_country` (`package_id`,`country_code`),
  KEY `package_country_prices_country_code_index` (`country_code`),
  CONSTRAINT `package_country_prices_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `subscription_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `package_features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `package_features` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint(20) unsigned NOT NULL,
  `feature_key` varchar(100) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `package_features_package_id_feature_key_unique` (`package_id`,`feature_key`),
  KEY `package_features_package_id_index` (`package_id`),
  KEY `package_features_feature_key_index` (`feature_key`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `package_industries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `package_industries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint(20) unsigned NOT NULL,
  `industry_key` varchar(60) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `price_monthly` decimal(10,2) DEFAULT NULL,
  `price_yearly` decimal(10,2) DEFAULT NULL,
  `discount_percent` decimal(5,2) DEFAULT NULL,
  `discount_ends_at` date DEFAULT NULL,
  `trial_days` smallint(5) unsigned DEFAULT NULL,
  `currency` varchar(3) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_package_industry` (`package_id`,`industry_key`),
  KEY `package_industries_industry_key_index` (`industry_key`),
  CONSTRAINT `package_industries_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `subscription_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `package_industry_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `package_industry_modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint(20) unsigned NOT NULL,
  `industry_key` varchar(60) NOT NULL,
  `module_key` varchar(60) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `category` enum('mandatory','default','optional','hidden') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_package_industry_module` (`package_id`,`industry_key`,`module_key`),
  KEY `idx_industry_package` (`industry_key`,`package_id`),
  KEY `package_industry_modules_module_key_index` (`module_key`),
  CONSTRAINT `package_industry_modules_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `subscription_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `package_scoped_features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `package_scoped_features` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `package_scope_id` bigint(20) unsigned NOT NULL,
  `feature_key` varchar(100) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scoped_feature` (`package_scope_id`,`feature_key`),
  KEY `package_scoped_features_feature_key_index` (`feature_key`),
  CONSTRAINT `package_scoped_features_package_scope_id_foreign` FOREIGN KEY (`package_scope_id`) REFERENCES `package_scopes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `package_scoped_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `package_scoped_modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `package_scope_id` bigint(20) unsigned NOT NULL,
  `module_key` varchar(60) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `scope_hash` varchar(120) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scoped_module` (`package_scope_id`,`module_key`),
  UNIQUE KEY `uq_scoped_module_hash` (`scope_hash`),
  KEY `package_scoped_modules_module_key_index` (`module_key`),
  CONSTRAINT `package_scoped_modules_package_scope_id_foreign` FOREIGN KEY (`package_scope_id`) REFERENCES `package_scopes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `package_scopes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `package_scopes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint(20) unsigned NOT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `industry_id` bigint(20) unsigned DEFAULT NULL,
  `sub_industry_id` bigint(20) unsigned DEFAULT NULL,
  `inherit_from_parent` tinyint(1) NOT NULL DEFAULT 1,
  `price_monthly` decimal(10,2) DEFAULT NULL,
  `price_yearly` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `scope_hash` varchar(120) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_package_scope_hash` (`scope_hash`),
  KEY `idx_scope_lookup` (`country_id`,`industry_id`,`sub_industry_id`),
  KEY `package_scopes_industry_id_foreign` (`industry_id`),
  KEY `package_scopes_sub_industry_id_foreign` (`sub_industry_id`),
  KEY `package_scopes_package_id_foreign` (`package_id`),
  CONSTRAINT `package_scopes_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `package_scopes_industry_id_foreign` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `package_scopes_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `subscription_packages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `package_scopes_sub_industry_id_foreign` FOREIGN KEY (`sub_industry_id`) REFERENCES `sub_industries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `party_type` varchar(20) NOT NULL DEFAULT 'customer',
  `is_customer` tinyint(1) NOT NULL DEFAULT 1,
  `is_vendor` tinyint(1) NOT NULL DEFAULT 0,
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partners` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `nid` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `capital` decimal(15,2) NOT NULL DEFAULT 0.00,
  `share_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `capital_account_id` bigint(20) unsigned DEFAULT NULL,
  `drawing_account_id` bigint(20) unsigned DEFAULT NULL,
  `joined_at` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `partners_institute_id_foreign` (`institute_id`),
  KEY `partners_capital_account_id_foreign` (`capital_account_id`),
  KEY `partners_drawing_account_id_foreign` (`drawing_account_id`),
  CONSTRAINT `partners_capital_account_id_foreign` FOREIGN KEY (`capital_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `partners_drawing_account_id_foreign` FOREIGN KEY (`drawing_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `partners_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `patient_allergies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patient_allergies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `medicine_id` bigint(20) unsigned DEFAULT NULL,
  `allergen_type` varchar(20) NOT NULL DEFAULT 'drug',
  `allergen_name` varchar(160) NOT NULL,
  `reaction` varchar(255) DEFAULT NULL,
  `severity` varchar(20) NOT NULL DEFAULT 'moderate',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_allergies_patient_id_foreign` (`patient_id`),
  KEY `patient_allergies_institute_id_patient_id_index` (`institute_id`,`patient_id`),
  KEY `patient_allergies_medicine_id_foreign` (`medicine_id`),
  CONSTRAINT `patient_allergies_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patient_allergies_medicine_id_foreign` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patient_allergies_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `patient_timeline_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patient_timeline_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `event_date` date NOT NULL,
  `source_type` varchar(100) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `doctor_id` bigint(20) unsigned DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `severity` varchar(20) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_timeline_events_doctor_id_foreign` (`doctor_id`),
  KEY `patient_timeline_events_institute_id_patient_id_event_at_index` (`institute_id`,`patient_id`,`event_at`),
  KEY `patient_timeline_events_patient_id_event_type_event_at_index` (`patient_id`,`event_type`,`event_at`),
  KEY `patient_timeline_events_source_type_source_id_index` (`source_type`,`source_id`),
  CONSTRAINT `patient_timeline_events_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patient_timeline_events_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patient_timeline_events_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `patients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `mr_number` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `relation_to_primary` varchar(20) DEFAULT NULL,
  `primary_contact_id` bigint(20) unsigned DEFAULT NULL,
  `is_dependent` tinyint(1) NOT NULL DEFAULT 0,
  `is_patient` tinyint(1) NOT NULL DEFAULT 1,
  `guardian_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `present_address` text DEFAULT NULL,
  `present_country_id` bigint(20) unsigned DEFAULT NULL,
  `present_admin_1_id` bigint(20) unsigned DEFAULT NULL,
  `present_admin_2_id` bigint(20) unsigned DEFAULT NULL,
  `present_admin_3_id` bigint(20) unsigned DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','UKN') DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `chronic_conditions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_patients_institute_mr` (`institute_id`,`mr_number`),
  KEY `patients_present_country_id_foreign` (`present_country_id`),
  KEY `patients_present_admin_1_id_foreign` (`present_admin_1_id`),
  KEY `patients_present_admin_2_id_foreign` (`present_admin_2_id`),
  KEY `patients_present_admin_3_id_foreign` (`present_admin_3_id`),
  KEY `patients_institute_id_index` (`institute_id`),
  KEY `patients_mr_number_index` (`mr_number`),
  KEY `patients_phone_index` (`phone`),
  KEY `patients_primary_contact_id_index` (`primary_contact_id`),
  KEY `patients_is_patient_index` (`is_patient`),
  CONSTRAINT `patients_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patients_present_admin_1_id_foreign` FOREIGN KEY (`present_admin_1_id`) REFERENCES `administrative_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patients_present_admin_2_id_foreign` FOREIGN KEY (`present_admin_2_id`) REFERENCES `administrative_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patients_present_admin_3_id_foreign` FOREIGN KEY (`present_admin_3_id`) REFERENCES `administrative_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patients_present_country_id_foreign` FOREIGN KEY (`present_country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patients_primary_contact_id_foreign` FOREIGN KEY (`primary_contact_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_reason` varchar(255) DEFAULT NULL,
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
  KEY `payments_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_payments_installment` FOREIGN KEY (`installment_id`) REFERENCES `installments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_received_by` FOREIGN KEY (`received_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pharmacy_dispenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pharmacy_dispenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `prescription_item_id` bigint(20) unsigned NOT NULL,
  `stock_id` bigint(20) unsigned NOT NULL,
  `quantity_dispensed` int(11) NOT NULL DEFAULT 1,
  `dispensed_by` bigint(20) unsigned DEFAULT NULL,
  `dispense_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pharmacy_dispenses_stock_id_foreign` (`stock_id`),
  KEY `pharmacy_dispenses_institute_id_index` (`institute_id`),
  KEY `pharmacy_dispenses_prescription_item_id_index` (`prescription_item_id`),
  KEY `pharmacy_dispenses_dispense_date_index` (`dispense_date`),
  KEY `pharmacy_dispenses_dispensed_by_foreign` (`dispensed_by`),
  KEY `pharmacy_dispenses_branch_id_index` (`branch_id`),
  CONSTRAINT `pharmacy_dispenses_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pharmacy_dispenses_dispensed_by_foreign` FOREIGN KEY (`dispensed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pharmacy_dispenses_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pharmacy_dispenses_prescription_item_id_foreign` FOREIGN KEY (`prescription_item_id`) REFERENCES `prescription_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pharmacy_dispenses_stock_id_foreign` FOREIGN KEY (`stock_id`) REFERENCES `pharmacy_stock` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pharmacy_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pharmacy_stock` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `medicine_id` bigint(20) unsigned NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `manufacturing_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `quantity_received` int(11) NOT NULL DEFAULT 0,
  `current_quantity` int(11) NOT NULL DEFAULT 0,
  `purchase_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `supplier_invoice_no` varchar(50) DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pharmacy_stock_medicine_id_batch_number_unique` (`medicine_id`,`batch_number`),
  KEY `pharmacy_stock_institute_id_index` (`institute_id`),
  KEY `pharmacy_stock_expiry_date_index` (`expiry_date`),
  KEY `pharmacy_stock_current_quantity_index` (`current_quantity`),
  KEY `pharmacy_stock_branch_id_index` (`branch_id`),
  CONSTRAINT `pharmacy_stock_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pharmacy_stock_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pharmacy_stock_medicine_id_foreign` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `physiotherapy_exercises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `physiotherapy_exercises` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `body_area` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `default_reps` int(11) DEFAULT NULL,
  `default_sets` int(11) DEFAULT NULL,
  `default_hold_seconds` int(11) DEFAULT NULL,
  `difficulty` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `physiotherapy_exercises_institute_id_is_active_index` (`institute_id`,`is_active`),
  CONSTRAINT `physiotherapy_exercises_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `physiotherapy_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `physiotherapy_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `plan_number` varchar(50) NOT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `therapist_id` bigint(20) unsigned NOT NULL,
  `referring_doctor_id` bigint(20) unsigned DEFAULT NULL,
  `appointment_id` bigint(20) unsigned DEFAULT NULL,
  `chief_complaint` text NOT NULL,
  `assessment` text DEFAULT NULL,
  `diagnosis` varchar(200) DEFAULT NULL,
  `treatment_goals` text DEFAULT NULL,
  `pain_score_initial` int(11) DEFAULT NULL,
  `modality` varchar(100) DEFAULT NULL,
  `sessions_planned` int(11) NOT NULL DEFAULT 10,
  `sessions_completed` int(11) NOT NULL DEFAULT 0,
  `frequency` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `expected_end_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `discontinue_reason` text DEFAULT NULL,
  `fee_per_session` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_physiotherapy_plan_number` (`institute_id`,`plan_number`),
  KEY `physiotherapy_plans_branch_id_foreign` (`branch_id`),
  KEY `physiotherapy_plans_therapist_id_foreign` (`therapist_id`),
  KEY `physiotherapy_plans_referring_doctor_id_foreign` (`referring_doctor_id`),
  KEY `physiotherapy_plans_institute_id_status_index` (`institute_id`,`status`),
  KEY `physiotherapy_plans_patient_id_start_date_index` (`patient_id`,`start_date`),
  CONSTRAINT `physiotherapy_plans_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `physiotherapy_plans_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `physiotherapy_plans_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `physiotherapy_plans_referring_doctor_id_foreign` FOREIGN KEY (`referring_doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `physiotherapy_plans_therapist_id_foreign` FOREIGN KEY (`therapist_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `physiotherapy_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `physiotherapy_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `physiotherapy_plan_id` bigint(20) unsigned NOT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `session_number` varchar(50) NOT NULL,
  `session_order` int(11) NOT NULL,
  `therapist_id` bigint(20) unsigned NOT NULL,
  `session_date` date NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 30,
  `pain_score_before` int(11) DEFAULT NULL,
  `pain_score_after` int(11) DEFAULT NULL,
  `assessment_notes` text DEFAULT NULL,
  `treatment_given` text DEFAULT NULL,
  `exercises_done` text DEFAULT NULL,
  `equipment_used` text DEFAULT NULL,
  `progress_notes` text DEFAULT NULL,
  `next_session_focus` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'scheduled',
  `attended_at` timestamp NULL DEFAULT NULL,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_physiotherapy_session_number` (`institute_id`,`session_number`),
  KEY `physiotherapy_sessions_branch_id_foreign` (`branch_id`),
  KEY `physiotherapy_sessions_therapist_id_foreign` (`therapist_id`),
  KEY `physiotherapy_sessions_physiotherapy_plan_id_session_order_index` (`physiotherapy_plan_id`,`session_order`),
  KEY `physiotherapy_sessions_institute_id_session_date_index` (`institute_id`,`session_date`),
  CONSTRAINT `physiotherapy_sessions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `physiotherapy_sessions_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `physiotherapy_sessions_physiotherapy_plan_id_foreign` FOREIGN KEY (`physiotherapy_plan_id`) REFERENCES `physiotherapy_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `physiotherapy_sessions_therapist_id_foreign` FOREIGN KEY (`therapist_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `prescription_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prescription_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `prescription_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_type` varchar(30) NOT NULL DEFAULT 'system',
  `actor_name` varchar(160) DEFAULT NULL,
  `action` varchar(40) NOT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prescription_audit_logs_prescription_id_foreign` (`prescription_id`),
  KEY `prescription_audit_logs_institute_id_prescription_id_index` (`institute_id`,`prescription_id`),
  CONSTRAINT `prescription_audit_logs_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescription_audit_logs_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prescription_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prescription_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` bigint(20) unsigned NOT NULL,
  `medicine_id` bigint(20) unsigned DEFAULT NULL,
  `medicine_concept_id` bigint(20) unsigned DEFAULT NULL,
  `medicine_product_id` bigint(20) unsigned DEFAULT NULL,
  `medicine_name` varchar(200) NOT NULL,
  `generic_name_snapshot` varchar(150) DEFAULT NULL,
  `display_name_snapshot` varchar(200) DEFAULT NULL,
  `strength_snapshot` varchar(100) DEFAULT NULL,
  `dosage_form_snapshot` varchar(50) DEFAULT NULL,
  `unit_snapshot` varchar(20) DEFAULT NULL,
  `pack_size_snapshot` int(10) unsigned DEFAULT NULL,
  `category_snapshot` varchar(100) DEFAULT NULL,
  `route_snapshot` varchar(50) DEFAULT NULL,
  `rxnorm_code_snapshot` varchar(20) DEFAULT NULL,
  `dgda_code` varchar(100) DEFAULT NULL,
  `dosage` varchar(50) NOT NULL,
  `frequency` varchar(50) NOT NULL,
  `duration_days` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `special_instructions` text DEFAULT NULL,
  `status` enum('pending','dispensed','cancelled') NOT NULL DEFAULT 'pending',
  `item_status` varchar(20) NOT NULL DEFAULT 'active',
  `discontinued_reason` varchar(255) DEFAULT NULL,
  `discontinued_at` timestamp NULL DEFAULT NULL,
  `continued_from_item_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prescription_items_medicine_id_foreign` (`medicine_id`),
  KEY `prescription_items_prescription_id_index` (`prescription_id`),
  KEY `prescription_items_status_index` (`status`),
  KEY `rx_items_product_snapshot_index` (`medicine_product_id`),
  KEY `idx_rx_items_status` (`prescription_id`,`item_status`),
  KEY `prescription_items_continued_from_item_id_index` (`continued_from_item_id`),
  CONSTRAINT `prescription_items_continued_from_item_id_foreign` FOREIGN KEY (`continued_from_item_id`) REFERENCES `prescription_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescription_items_medicine_id_foreign` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescription_items_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prescriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prescriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `parent_prescription_id` bigint(20) unsigned DEFAULT NULL,
  `amendment_reason` text DEFAULT NULL,
  `amended_by` bigint(20) unsigned DEFAULT NULL,
  `amended_at` timestamp NULL DEFAULT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `encounter_id` bigint(20) unsigned DEFAULT NULL,
  `doctor_id` bigint(20) unsigned DEFAULT NULL,
  `prescription_number` varchar(50) NOT NULL,
  `prescription_date` date NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `investigations` text DEFAULT NULL,
  `chief_complaints` text DEFAULT NULL,
  `examination_findings` text DEFAULT NULL,
  `advice` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `is_finalized` tinyint(1) NOT NULL DEFAULT 0,
  `signed_at` timestamp NULL DEFAULT NULL,
  `signed_by` bigint(20) unsigned DEFAULT NULL,
  `signature_hash` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prescriptions_institute_number` (`institute_id`,`prescription_number`,`version`),
  UNIQUE KEY `uniq_rx_doctor_patient_date_version` (`doctor_id`,`patient_id`,`prescription_date`,`version`),
  KEY `prescriptions_institute_id_index` (`institute_id`),
  KEY `prescriptions_patient_id_index` (`patient_id`),
  KEY `prescriptions_doctor_id_index` (`doctor_id`),
  KEY `prescriptions_prescription_number_index` (`prescription_number`),
  KEY `prescriptions_prescription_date_index` (`prescription_date`),
  KEY `prescriptions_encounter_id_index` (`encounter_id`),
  KEY `prescriptions_branch_id_index` (`branch_id`),
  KEY `idx_rx_doctor_patient_date_version` (`doctor_id`,`patient_id`,`prescription_date`,`version`),
  KEY `fk_prescription_parent` (`parent_prescription_id`),
  KEY `prescriptions_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_prescription_parent` FOREIGN KEY (`parent_prescription_id`) REFERENCES `prescriptions` (`id`),
  CONSTRAINT `prescriptions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_encounter_id_foreign` FOREIGN KEY (`encounter_id`) REFERENCES `medical_encounters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescriptions_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `progressive_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `progressive_contracts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `contract_number` varchar(50) NOT NULL,
  `party_id` bigint(20) unsigned NOT NULL,
  `sales_quotation_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `total_value` decimal(15,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'BDT',
  `exchange_rate` decimal(15,6) NOT NULL DEFAULT 1.000000,
  `retention_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `retention_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `retention_released` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_billed` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remaining_value` decimal(15,2) NOT NULL,
  `progress_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `start_date` date NOT NULL,
  `expected_end_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `tax_group_id` bigint(20) unsigned DEFAULT NULL,
  `tax_method` varchar(20) NOT NULL DEFAULT 'exclusive',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_contract_number` (`institute_id`,`contract_number`),
  KEY `progressive_contracts_branch_id_foreign` (`branch_id`),
  KEY `progressive_contracts_sales_quotation_id_foreign` (`sales_quotation_id`),
  KEY `progressive_contracts_tax_group_id_foreign` (`tax_group_id`),
  KEY `progressive_contracts_institute_id_status_index` (`institute_id`,`status`),
  KEY `progressive_contracts_party_id_status_index` (`party_id`,`status`),
  CONSTRAINT `progressive_contracts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `progressive_contracts_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `progressive_contracts_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`),
  CONSTRAINT `progressive_contracts_sales_quotation_id_foreign` FOREIGN KEY (`sales_quotation_id`) REFERENCES `sales_quotations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `progressive_contracts_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_invoice_items_institute_id_foreign` (`institute_id`),
  KEY `purchase_invoice_items_purchase_order_line_id_foreign` (`purchase_order_line_id`),
  KEY `purchase_invoice_items_goods_receipt_item_id_foreign` (`goods_receipt_item_id`),
  KEY `purchase_invoice_items_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `purchase_invoice_items_tax_group_id_foreign` (`tax_group_id`),
  KEY `idx_pii_order` (`purchase_invoice_id`,`sort_order`),
  KEY `purchase_invoice_items_deleted_at_index` (`deleted_at`),
  CONSTRAINT `purchase_invoice_items_goods_receipt_item_id_foreign` FOREIGN KEY (`goods_receipt_item_id`) REFERENCES `goods_receipt_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_invoice_items_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_invoice_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_invoice_items_purchase_invoice_id_foreign` FOREIGN KEY (`purchase_invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_invoice_items_purchase_order_line_id_foreign` FOREIGN KEY (`purchase_order_line_id`) REFERENCES `purchase_order_lines` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_invoice_items_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `queue_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `queue_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_type` varchar(20) NOT NULL,
  `actor_name` varchar(150) DEFAULT NULL,
  `action` varchar(20) NOT NULL DEFAULT 'reorder',
  `old_order` int(11) DEFAULT NULL,
  `new_order` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `needs_verification` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `queue_audit_logs_appointment_id_foreign` (`appointment_id`),
  KEY `queue_audit_logs_institute_id_appointment_id_index` (`institute_id`,`appointment_id`),
  CONSTRAINT `queue_audit_logs_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `queue_audit_logs_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `radiology_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `radiology_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `radiology_order_id` bigint(20) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `original_filename` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `radiology_images_radiology_order_id_index` (`radiology_order_id`),
  CONSTRAINT `radiology_images_radiology_order_id_foreign` FOREIGN KEY (`radiology_order_id`) REFERENCES `radiology_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `radiology_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `radiology_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `order_number` varchar(50) NOT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `doctor_id` bigint(20) unsigned DEFAULT NULL,
  `appointment_id` bigint(20) unsigned DEFAULT NULL,
  `modality` varchar(30) NOT NULL,
  `body_part` varchar(100) NOT NULL,
  `laterality` varchar(20) DEFAULT NULL,
  `clinical_indication` text DEFAULT NULL,
  `is_contrast` tinyint(1) NOT NULL DEFAULT 0,
  `contrast_type` varchar(100) DEFAULT NULL,
  `is_urgent` tinyint(1) NOT NULL DEFAULT 0,
  `is_fasting_required` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'ordered',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `performed_at` timestamp NULL DEFAULT NULL,
  `performed_by` bigint(20) unsigned DEFAULT NULL,
  `technique` text DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `impression` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `radiologist_name` varchar(200) DEFAULT NULL,
  `radiologist_id` bigint(20) unsigned DEFAULT NULL,
  `reported_at` timestamp NULL DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `invoice_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_radiology_order_number` (`institute_id`,`order_number`),
  KEY `radiology_orders_branch_id_foreign` (`branch_id`),
  KEY `radiology_orders_doctor_id_foreign` (`doctor_id`),
  KEY `radiology_orders_institute_id_status_index` (`institute_id`,`status`),
  KEY `radiology_orders_institute_id_modality_status_index` (`institute_id`,`modality`,`status`),
  KEY `radiology_orders_institute_id_scheduled_at_index` (`institute_id`,`scheduled_at`),
  KEY `radiology_orders_patient_id_performed_at_index` (`patient_id`,`performed_at`),
  CONSTRAINT `radiology_orders_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `radiology_orders_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `radiology_orders_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `radiology_orders_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recurring_generations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recurring_generations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `template_id` bigint(20) unsigned NOT NULL,
  `scheduled_for` date NOT NULL,
  `generated_at` timestamp NULL DEFAULT NULL,
  `generated_type` varchar(100) DEFAULT NULL,
  `generated_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `error_message` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recurring_generations_template_id_scheduled_for_index` (`template_id`,`scheduled_for`),
  KEY `recurring_generations_institute_id_status_index` (`institute_id`,`status`),
  CONSTRAINT `recurring_generations_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recurring_generations_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `recurring_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recurring_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recurring_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `template_number` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `transaction_type` varchar(30) NOT NULL,
  `frequency` varchar(20) NOT NULL,
  `interval_count` int(11) NOT NULL DEFAULT 1,
  `custom_cron` varchar(100) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `max_occurrences` int(11) DEFAULT NULL,
  `occurrences_generated` int(11) NOT NULL DEFAULT 0,
  `next_run_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_generated_at` timestamp NULL DEFAULT NULL,
  `auto_post` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `consecutive_failures` int(11) NOT NULL DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `template_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`template_data`)),
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_recurring_template_number` (`institute_id`,`template_number`),
  KEY `recurring_templates_branch_id_foreign` (`branch_id`),
  KEY `recurring_templates_created_by_foreign` (`created_by`),
  KEY `recurring_templates_institute_id_status_index` (`institute_id`,`status`),
  KEY `idx_next_run_status` (`next_run_at`,`status`),
  KEY `recurring_templates_transaction_type_status_index` (`transaction_type`,`status`),
  CONSTRAINT `recurring_templates_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recurring_templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recurring_templates_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restore_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `restore_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `restore_log_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `mode` varchar(20) NOT NULL,
  `affected_tables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`affected_tables`)),
  `total_affected` int(11) NOT NULL DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restore_audits_tenant_id_index` (`tenant_id`),
  KEY `restore_audits_restore_log_id_index` (`restore_log_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restore_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `restore_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `backup_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `mode` enum('merge','full_wipe','smart') NOT NULL DEFAULT 'merge',
  `records_affected` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`records_affected`)),
  `rollback_path` varchar(500) DEFAULT NULL,
  `rollback_token` varchar(64) DEFAULT NULL,
  `rollback_expires_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','completed','failed','rolled_back') NOT NULL DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `progress_percent` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `progress_stage` varchar(50) DEFAULT NULL,
  `progress_message` varchar(500) DEFAULT NULL,
  `job_id` varchar(100) DEFAULT NULL,
  `downloaded_chunks` int(10) unsigned NOT NULL DEFAULT 0,
  `total_chunks` int(10) unsigned NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restore_logs_tenant_id_index` (`tenant_id`),
  KEY `restore_logs_rollback_token_index` (`rollback_token`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restore_previews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `restore_previews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `backup_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `mode` varchar(20) NOT NULL,
  `diff` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`diff`)),
  `total_insert` int(11) NOT NULL DEFAULT 0,
  `total_update` int(11) NOT NULL DEFAULT 0,
  `total_soft_delete` int(11) NOT NULL DEFAULT 0,
  `total_kept` int(11) NOT NULL DEFAULT 0,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restore_previews_tenant_id_backup_id_index` (`tenant_id`,`backup_id`),
  KEY `restore_previews_expires_at_index` (`expires_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restore_rollbacks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `restore_rollbacks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `restore_log_id` bigint(20) unsigned NOT NULL,
  `rollback_token` varchar(64) NOT NULL,
  `snapshot_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`snapshot_data`)),
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `restore_rollbacks_rollback_token_unique` (`rollback_token`),
  KEY `restore_rollbacks_tenant_id_index` (`tenant_id`),
  KEY `restore_rollbacks_expires_at_index` (`expires_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restore_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `restore_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `backup_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restore_tokens_tenant_id_user_id_index` (`tenant_id`,`user_id`),
  KEY `restore_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `rxnorm_concepts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rxnorm_concepts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rxnorm_import_batch_id` bigint(20) unsigned DEFAULT NULL,
  `rxcui` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `tty` varchar(12) NOT NULL,
  `source_release` varchar(100) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `replaced_by_rxcui` varchar(20) DEFAULT NULL,
  `medicine_product_id` bigint(20) unsigned DEFAULT NULL,
  `medicine_ingredient_id` bigint(20) unsigned DEFAULT NULL,
  `match_status` varchar(20) NOT NULL DEFAULT 'unmapped',
  `match_detail` text DEFAULT NULL,
  `candidates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`candidates`)),
  `raw_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_payload`)),
  `retrieved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rxnorm_concepts_rxcui_unique` (`rxcui`),
  KEY `rxnorm_concepts_rxnorm_import_batch_id_foreign` (`rxnorm_import_batch_id`),
  KEY `rxnorm_concepts_medicine_product_id_index` (`medicine_product_id`),
  KEY `rxnorm_concepts_medicine_ingredient_id_index` (`medicine_ingredient_id`),
  KEY `rxnorm_concepts_match_status_index` (`match_status`),
  KEY `rxnorm_concepts_tty_index` (`tty`),
  CONSTRAINT `rxnorm_concepts_medicine_ingredient_id_foreign` FOREIGN KEY (`medicine_ingredient_id`) REFERENCES `medicine_ingredients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rxnorm_concepts_medicine_product_id_foreign` FOREIGN KEY (`medicine_product_id`) REFERENCES `medicine_products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rxnorm_concepts_rxnorm_import_batch_id_foreign` FOREIGN KEY (`rxnorm_import_batch_id`) REFERENCES `rxnorm_import_batches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rxnorm_import_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rxnorm_import_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(40) NOT NULL DEFAULT 'rxnorm_rxnav',
  `release_version` varchar(100) DEFAULT NULL,
  `filename` varchar(200) DEFAULT NULL,
  `checksum` varchar(64) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `records_seen` int(10) unsigned NOT NULL DEFAULT 0,
  `records_valid` int(10) unsigned NOT NULL DEFAULT 0,
  `records_created` int(10) unsigned NOT NULL DEFAULT 0,
  `records_updated` int(10) unsigned NOT NULL DEFAULT 0,
  `records_unchanged` int(10) unsigned NOT NULL DEFAULT 0,
  `records_rejected` int(10) unsigned NOT NULL DEFAULT 0,
  `records_unmapped` int(10) unsigned NOT NULL DEFAULT 0,
  `records_ambiguous` int(10) unsigned NOT NULL DEFAULT 0,
  `error_summary` text DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_order_lines_institute_id_foreign` (`institute_id`),
  KEY `sales_order_lines_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `sales_order_lines_tax_group_id_foreign` (`tax_group_id`),
  KEY `idx_so_lines_order` (`order_id`,`sort_order`),
  KEY `sales_order_lines_deleted_at_index` (`deleted_at`),
  CONSTRAINT `sales_order_lines_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_order_lines_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_order_lines_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_order_lines_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `tax_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schema_migrations` (
  `version` varchar(255) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `share_capital_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `share_capital_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `type` enum('issuance','transfer','buyback','bonus','rights') NOT NULL,
  `shareholder_id` bigint(20) unsigned DEFAULT NULL,
  `from_shareholder_id` bigint(20) unsigned DEFAULT NULL,
  `to_shareholder_id` bigint(20) unsigned DEFAULT NULL,
  `shares` int(11) NOT NULL,
  `face_value` decimal(10,2) NOT NULL,
  `premium_per_share` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `certificate_no` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `share_capital_transactions_shareholder_id_foreign` (`shareholder_id`),
  KEY `share_capital_transactions_from_shareholder_id_foreign` (`from_shareholder_id`),
  KEY `share_capital_transactions_to_shareholder_id_foreign` (`to_shareholder_id`),
  KEY `share_capital_transactions_institute_id_type_index` (`institute_id`,`type`),
  KEY `share_capital_transactions_institute_id_transaction_date_index` (`institute_id`,`transaction_date`),
  CONSTRAINT `share_capital_transactions_from_shareholder_id_foreign` FOREIGN KEY (`from_shareholder_id`) REFERENCES `shareholders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `share_capital_transactions_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `share_capital_transactions_shareholder_id_foreign` FOREIGN KEY (`shareholder_id`) REFERENCES `shareholders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `share_capital_transactions_to_shareholder_id_foreign` FOREIGN KEY (`to_shareholder_id`) REFERENCES `shareholders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `share_certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `share_certificates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `shareholder_id` bigint(20) unsigned NOT NULL,
  `certificate_no` varchar(50) NOT NULL,
  `shares` int(11) NOT NULL,
  `face_value` decimal(10,2) NOT NULL,
  `total_value` decimal(15,2) NOT NULL,
  `issue_date` date NOT NULL,
  `cancel_date` date DEFAULT NULL,
  `status` enum('active','cancelled','transferred') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `share_certificates_institute_id_certificate_no_unique` (`institute_id`,`certificate_no`),
  KEY `share_certificates_shareholder_id_foreign` (`shareholder_id`),
  KEY `share_certificates_institute_id_shareholder_id_index` (`institute_id`,`shareholder_id`),
  CONSTRAINT `share_certificates_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `share_certificates_shareholder_id_foreign` FOREIGN KEY (`shareholder_id`) REFERENCES `shareholders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shareholders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shareholders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `nid` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `shares` bigint(20) unsigned NOT NULL DEFAULT 0,
  `face_value` decimal(10,2) NOT NULL DEFAULT 10.00,
  `share_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_director` tinyint(1) NOT NULL DEFAULT 0,
  `director_designation` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `certificate_no` varchar(255) DEFAULT NULL,
  `issued_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shareholders_institute_id_foreign` (`institute_id`),
  CONSTRAINT `shareholders_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `reg_no` varchar(10) NOT NULL,
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
  `present_post_office` varchar(100) DEFAULT NULL,
  `present_zip_code` varchar(10) DEFAULT NULL,
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
  UNIQUE KEY `students_reg_no_unique` (`reg_no`),
  UNIQUE KEY `uq_students_inst_email` (`institute_id`,`email`),
  UNIQUE KEY `uq_students_inst_phone` (`institute_id`,`phone`),
  UNIQUE KEY `uq_students_inst_nid` (`institute_id`,`nid_number`),
  UNIQUE KEY `uq_students_inst_birth_cert` (`institute_id`,`birth_cert_number`),
  UNIQUE KEY `uq_students_inst_passport` (`institute_id`,`passport_number`),
  UNIQUE KEY `uq_students_inst_app_number` (`institute_id`,`application_number`),
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sub_industries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sub_industries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `industry_id` bigint(20) unsigned NOT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `code` varchar(30) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `scope_hash` varchar(120) GENERATED ALWAYS AS (concat(coalesce(cast(`industry_id` as char charset utf8mb4),'G'),'-',coalesce(cast(`country_id` as char charset utf8mb4),'G'),'-',`slug`)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sub_industry_scope_slug` (`scope_hash`),
  KEY `sub_industries_industry_id_index` (`industry_id`),
  KEY `sub_industries_country_id_index` (`country_id`),
  CONSTRAINT `sub_industries_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sub_industries_industry_id_foreign` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subcategory_default_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subcategory_default_modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `subcategory_id` bigint(20) unsigned NOT NULL,
  `module_key` varchar(60) NOT NULL,
  `category` enum('mandatory','default','optional','hidden') NOT NULL DEFAULT 'default',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subcategory_default_modules_subcategory_id_module_key_unique` (`subcategory_id`,`module_key`),
  CONSTRAINT `subcategory_default_modules_subcategory_id_foreign` FOREIGN KEY (`subcategory_id`) REFERENCES `industry_subcategories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `super_admin_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `super_admin_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `module_key` varchar(60) NOT NULL,
  `override_layer` enum('industry','country') NOT NULL,
  `reason` text NOT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `two_factor_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_sent_to` text DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `super_admin_overrides_unique` (`institute_id`,`module_key`,`override_layer`),
  KEY `super_admin_overrides_approved_by_foreign` (`approved_by`),
  KEY `super_admin_overrides_expires_at_index` (`expires_at`),
  CONSTRAINT `super_admin_overrides_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `super_admin_overrides_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `tax_deduction_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_deduction_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_code` char(2) NOT NULL,
  `currency_code` char(3) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'tds',
  `rate` decimal(6,2) NOT NULL DEFAULT 0.00,
  `threshold` decimal(15,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_deduction_rules_country_code_code_unique` (`country_code`,`code`),
  KEY `tax_deduction_rules_is_active_index` (`is_active`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `tax_return_reconciliations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_return_reconciliations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `country_code` char(2) NOT NULL DEFAULT 'BD',
  `financial_year` varchar(20) NOT NULL,
  `tds_payable_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tds_receivable_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_tax_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `corporate_tax_payable` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_tax_liability` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_credits` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_payable` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency_code` char(3) NOT NULL DEFAULT 'BDT',
  `status` enum('draft','computed','filed','settled') NOT NULL DEFAULT 'draft',
  `filing_date` date DEFAULT NULL,
  `acknowledgment_no` varchar(50) DEFAULT NULL,
  `breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`breakdown`)),
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_return_reconciliations_institute_id_financial_year_unique` (`institute_id`,`financial_year`),
  CONSTRAINT `tax_return_reconciliations_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `tds_certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tds_certificates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `deduction_id` bigint(20) unsigned NOT NULL,
  `country_code` char(2) NOT NULL,
  `certificate_no` varchar(50) NOT NULL,
  `financial_year` varchar(20) NOT NULL,
  `issue_date` date NOT NULL,
  `status` enum('draft','issued','cancelled') NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tds_certificates_certificate_no_unique` (`certificate_no`),
  KEY `tds_certificates_deduction_id_foreign` (`deduction_id`),
  KEY `tds_certificates_institute_id_financial_year_index` (`institute_id`,`financial_year`),
  CONSTRAINT `tds_certificates_deduction_id_foreign` FOREIGN KEY (`deduction_id`) REFERENCES `tds_deductions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tds_certificates_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tds_certificates_received`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tds_certificates_received` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `country_code` char(2) NOT NULL DEFAULT 'BD',
  `party_id` bigint(20) unsigned NOT NULL,
  `certificate_no` varchar(50) NOT NULL,
  `certificate_date` date NOT NULL,
  `tax_period` varchar(20) NOT NULL,
  `financial_year` varchar(20) NOT NULL,
  `total_base` decimal(15,2) NOT NULL,
  `total_tds` decimal(15,2) NOT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'BDT',
  `attachment_path` varchar(500) DEFAULT NULL,
  `status` enum('received','verified','disputed') NOT NULL DEFAULT 'received',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tds_cert_recv_unique` (`institute_id`,`party_id`,`certificate_no`,`financial_year`),
  KEY `tds_certificates_received_party_id_foreign` (`party_id`),
  KEY `tds_cert_fy_status_idx` (`institute_id`,`financial_year`,`status`),
  CONSTRAINT `tds_certificates_received_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tds_certificates_received_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tds_deductions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tds_deductions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `rule_id` bigint(20) unsigned DEFAULT NULL,
  `country_code` char(2) NOT NULL,
  `currency_code` char(3) NOT NULL,
  `reference_no` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `payee_name` varchar(200) NOT NULL,
  `payee_tin` varchar(50) DEFAULT NULL,
  `gross_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(6,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `deduction_date` date NOT NULL,
  `deposit_date` date DEFAULT NULL,
  `deposit_challan_no` varchar(100) DEFAULT NULL,
  `status` enum('pending','deposited','certificate_issued') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tds_deductions_reference_no_unique` (`reference_no`),
  KEY `tds_deductions_rule_id_foreign` (`rule_id`),
  KEY `tds_deductions_institute_id_status_index` (`institute_id`,`status`),
  KEY `tds_deductions_institute_id_type_index` (`institute_id`,`type`),
  KEY `tds_deductions_institute_id_deduction_date_index` (`institute_id`,`deduction_date`),
  CONSTRAINT `tds_deductions_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tds_deductions_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `tax_deduction_rules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tds_receivables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tds_receivables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `country_code` char(2) NOT NULL DEFAULT 'BD',
  `party_id` bigint(20) unsigned NOT NULL,
  `invoice_id` bigint(20) unsigned DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `gross_amount` decimal(15,2) NOT NULL,
  `rate_percent` decimal(5,2) NOT NULL,
  `tds_amount` decimal(15,2) NOT NULL,
  `net_amount` decimal(15,2) NOT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'BDT',
  `deduction_date` date NOT NULL,
  `tax_period` varchar(20) NOT NULL,
  `financial_year` varchar(20) NOT NULL,
  `status` enum('pending_certificate','certified','reconciled') NOT NULL DEFAULT 'pending_certificate',
  `certificate_id` bigint(20) unsigned DEFAULT NULL,
  `journal_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tds_receivables_party_id_foreign` (`party_id`),
  KEY `tds_recv_fy_status_idx` (`institute_id`,`financial_year`,`status`),
  KEY `tds_recv_party_idx` (`institute_id`,`party_id`),
  CONSTRAINT `tds_receivables_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tds_receivables_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_access_denials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_access_denials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `deny_type` enum('module','feature') NOT NULL,
  `deny_key` varchar(100) NOT NULL,
  `denied_by` bigint(20) unsigned NOT NULL,
  `denied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `status` enum('active','lifted','expired') NOT NULL DEFAULT 'active',
  `lifted_at` timestamp NULL DEFAULT NULL,
  `lifted_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_active_deny` (`institute_id`,`deny_type`,`deny_key`,`status`),
  KEY `tenant_access_denials_institute_id_index` (`institute_id`),
  KEY `tenant_access_denials_expires_at_index` (`expires_at`),
  KEY `tenant_access_denials_denied_by_foreign` (`denied_by`),
  KEY `tenant_access_denials_lifted_by_foreign` (`lifted_by`),
  CONSTRAINT `tenant_access_denials_denied_by_foreign` FOREIGN KEY (`denied_by`) REFERENCES `platform_admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_access_denials_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_access_denials_lifted_by_foreign` FOREIGN KEY (`lifted_by`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_access_grants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_access_grants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `grant_type` enum('module','feature','tier') NOT NULL,
  `grant_key` varchar(100) NOT NULL,
  `granted_by` bigint(20) unsigned NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('active','revoked','expired','superseded') NOT NULL DEFAULT 'active',
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoked_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_access_grants_institute_id_index` (`institute_id`),
  KEY `tenant_access_grants_status_index` (`status`),
  KEY `tenant_access_grants_expires_at_index` (`expires_at`),
  KEY `tenant_access_grants_granted_by_foreign` (`granted_by`),
  KEY `tenant_access_grants_revoked_by_foreign` (`revoked_by`),
  CONSTRAINT `tenant_access_grants_granted_by_foreign` FOREIGN KEY (`granted_by`) REFERENCES `platform_admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_access_grants_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_access_grants_revoked_by_foreign` FOREIGN KEY (`revoked_by`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_backup_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_backup_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `encrypted_dek` text NOT NULL,
  `key_version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_backup_keys_tenant_id_unique` (`tenant_id`),
  KEY `tenant_backup_keys_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_currency_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_currency_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `country_code` char(2) DEFAULT NULL,
  `base_currency` char(3) NOT NULL,
  `multi_currency_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `available_currencies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`available_currencies`)),
  `currency_position` enum('before','after') NOT NULL DEFAULT 'before',
  `thousand_separator` varchar(5) NOT NULL DEFAULT ',',
  `decimal_separator` varchar(5) NOT NULL DEFAULT '.',
  `decimal_places` tinyint(4) NOT NULL DEFAULT 2,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_currency_settings_institute_id_unique` (`institute_id`),
  CONSTRAINT `tenant_currency_settings_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `tenant_drive_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_drive_connections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `connected_by_user_id` bigint(20) unsigned NOT NULL,
  `google_user_email` varchar(150) NOT NULL,
  `google_user_id` varchar(100) NOT NULL,
  `refresh_token` text NOT NULL,
  `drive_folder_id` varchar(100) DEFAULT NULL,
  `connected_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `app_folder_id` varchar(100) DEFAULT NULL,
  `chunks_folder_id` varchar(100) DEFAULT NULL,
  `manifests_folder_id` varchar(100) DEFAULT NULL,
  `trash_folder_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_drive_connections_tenant_id_unique` (`tenant_id`),
  KEY `tenant_drive_connections_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tpa_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tpa_claims` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `claim_number` varchar(50) NOT NULL,
  `tpa_company_name` varchar(150) NOT NULL,
  `policy_number` varchar(50) NOT NULL,
  `claim_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `approved_amount` decimal(15,2) DEFAULT NULL,
  `status` enum('pending','approved','rejected','partial','settled') NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `claim_date` date NOT NULL,
  `approval_date` date DEFAULT NULL,
  `settlement_date` date DEFAULT NULL,
  `documents` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tpa_claims_institute_number` (`institute_id`,`claim_number`),
  KEY `tpa_claims_invoice_id_foreign` (`invoice_id`),
  KEY `tpa_claims_institute_id_index` (`institute_id`),
  KEY `tpa_claims_patient_id_index` (`patient_id`),
  KEY `tpa_claims_claim_number_index` (`claim_number`),
  KEY `tpa_claims_status_index` (`status`),
  CONSTRAINT `tpa_claims_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tpa_claims_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `medical_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tpa_claims_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_attendance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `class_date` date NOT NULL,
  `status` varchar(20) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `marked_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_attendance_institute_id_class_date_index` (`institute_id`,`class_date`),
  KEY `training_attendance_batch_id_index` (`batch_id`),
  KEY `training_attendance_student_id_index` (`student_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  CONSTRAINT `training_batch_results_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `training_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_batch_results_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_batch_results_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `training_students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `is_test` tinyint(1) NOT NULL DEFAULT 0,
  `institute_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `curriculum_id` bigint(20) unsigned DEFAULT NULL,
  `academic_year_id` bigint(20) unsigned DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `teacher_id` bigint(20) unsigned DEFAULT NULL,
  `room_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `batch_code` varchar(50) DEFAULT NULL,
  `shift` varchar(30) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `seat_capacity` int(10) unsigned DEFAULT NULL,
  `seat_filled` int(10) unsigned DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL,
  `attendance_threshold` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_batches_institute_id_index` (`institute_id`),
  KEY `training_batches_batch_code_index` (`batch_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_certificates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) DEFAULT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `certificate_type_id` bigint(20) unsigned DEFAULT NULL,
  `template_id` bigint(20) unsigned DEFAULT NULL,
  `result_id` bigint(20) unsigned DEFAULT NULL,
  `certificate_number` varchar(50) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `qr_code_path` varchar(500) DEFAULT NULL,
  `verification_url` varchar(500) DEFAULT NULL,
  `digital_signature` text DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL,
  `revoked_reason` varchar(500) DEFAULT NULL,
  `issued_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_certificates_institute_id_index` (`institute_id`),
  KEY `training_certificates_student_id_index` (`student_id`),
  KEY `training_certificates_batch_id_index` (`batch_id`),
  KEY `training_certificates_certificate_number_index` (`certificate_number`),
  KEY `training_certificates_uuid_index` (`uuid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_classes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `education_system_id` bigint(20) unsigned DEFAULT NULL,
  `academic_level_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `code` varchar(50) DEFAULT NULL,
  `sequence` int(11) DEFAULT NULL,
  `display_order` int(11) DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_classes_institute_id_index` (`institute_id`),
  KEY `training_classes_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_course_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_course_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `subject_type` varchar(30) NOT NULL DEFAULT 'professional',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tcc_inst_slug` (`institute_id`,`slug`),
  KEY `training_course_categories_institute_id_index` (`institute_id`),
  CONSTRAINT `training_course_categories_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_course_curricula`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_course_curricula` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `training_batch_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `version` varchar(50) NOT NULL DEFAULT 'v1',
  `effective_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `description` text DEFAULT NULL,
  `total_duration_hours` int(11) DEFAULT NULL,
  `total_classes` int(11) DEFAULT NULL,
  `learning_objectives` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`learning_objectives`)),
  `version_notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_training_curr_ver` (`institute_id`,`training_batch_id`,`version`),
  KEY `training_course_curricula_institute_id_status_index` (`institute_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_course_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_course_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `curriculum_module_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_course_materials_institute_id_index` (`institute_id`),
  KEY `training_course_materials_course_id_index` (`course_id`),
  KEY `training_course_materials_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `training_course_materials_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_course_materials_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_course_materials_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_course_sub_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_course_sub_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tcsc_cat_slug` (`category_id`,`slug`),
  KEY `training_course_sub_categories_institute_id_index` (`institute_id`),
  CONSTRAINT `training_course_sub_categories_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `training_course_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_course_sub_categories_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_course_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_course_subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `assigned_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tcs_course_subject` (`course_id`,`subject_id`),
  KEY `training_course_subjects_subject_id_index` (`subject_id`),
  CONSTRAINT `training_course_subjects_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_course_subjects_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `training_subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_courses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `is_test` tinyint(1) NOT NULL DEFAULT 0,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `sub_category_id` bigint(20) unsigned DEFAULT NULL,
  `course_code` varchar(40) NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(200) DEFAULT NULL,
  `short_name` varchar(60) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `modules` longtext DEFAULT NULL,
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
  `requirements` longtext DEFAULT NULL,
  `outcomes` longtext DEFAULT NULL,
  `prerequisites` longtext DEFAULT NULL,
  `status` enum('active','inactive','draft') NOT NULL DEFAULT 'draft',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_courses_institute_id_index` (`institute_id`),
  KEY `training_courses_slug_index` (`slug`),
  KEY `training_courses_course_code_index` (`course_code`),
  KEY `training_courses_category_id_index` (`category_id`),
  KEY `training_courses_sub_category_id_foreign` (`sub_category_id`),
  CONSTRAINT `training_courses_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `training_course_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `training_courses_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `training_courses_sub_category_id_foreign` FOREIGN KEY (`sub_category_id`) REFERENCES `training_course_sub_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `roll_no` int(11) DEFAULT NULL,
  `trainee_id` bigint(20) unsigned DEFAULT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `enrollment_date` date DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL,
  `payment_status` varchar(30) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_enrollments_institute_id_index` (`institute_id`),
  KEY `training_enrollments_batch_id_index` (`batch_id`),
  KEY `training_enrollments_student_id_index` (`student_id`),
  KEY `training_enrollments_trainee_id_index` (`trainee_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_exam_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_exam_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `exam_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `marks_obtained` decimal(8,2) DEFAULT NULL,
  `written_marks` decimal(8,2) DEFAULT NULL,
  `practical_marks` decimal(8,2) DEFAULT NULL,
  `viva_marks` decimal(8,2) DEFAULT NULL,
  `other_marks` decimal(8,2) DEFAULT NULL,
  `component_marks` decimal(8,2) DEFAULT NULL,
  `attendance_marks` decimal(8,2) DEFAULT NULL,
  `grade` varchar(10) DEFAULT NULL,
  `gpa` decimal(5,2) DEFAULT NULL,
  `result_status` varchar(30) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `entered_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_exam_results_exam_student_subject_unique` (`exam_id`,`student_id`,`subject_id`),
  KEY `training_exam_results_institute_id_index` (`institute_id`),
  KEY `training_exam_results_exam_id_index` (`exam_id`),
  KEY `training_exam_results_student_id_index` (`student_id`),
  KEY `training_exam_results_subject_id_index` (`subject_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_exams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_exams` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `exam_date` date DEFAULT NULL,
  `full_marks` decimal(8,2) DEFAULT NULL,
  `pass_marks` decimal(8,2) DEFAULT NULL,
  `written_percent` decimal(5,2) DEFAULT NULL,
  `practical_percent` decimal(5,2) DEFAULT NULL,
  `viva_percent` decimal(5,2) DEFAULT NULL,
  `weight_percent` decimal(5,2) DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `is_test` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_exams_institute_id_index` (`institute_id`),
  KEY `training_exams_batch_id_index` (`batch_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `is_test` tinyint(1) NOT NULL DEFAULT 0,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `day_of_week` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `room` varchar(80) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_schedules_institute_id_index` (`institute_id`),
  KEY `training_schedules_batch_id_index` (`batch_id`),
  KEY `training_schedules_subject_id_index` (`subject_id`),
  KEY `training_schedules_batch_id_day_of_week_index` (`batch_id`,`day_of_week`),
  KEY `training_schedules_batch_effective_index` (`batch_id`,`effective_to`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_students` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `reg_no` varchar(50) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `is_test` tinyint(1) NOT NULL DEFAULT 0,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `uuid` char(36) DEFAULT NULL,
  `student_id_number` varchar(50) DEFAULT NULL,
  `application_number` varchar(50) DEFAULT NULL,
  `application_date` date DEFAULT NULL,
  `admission_status` varchar(30) DEFAULT NULL,
  `admission_source` varchar(50) DEFAULT NULL,
  `admission_reject_reason` varchar(500) DEFAULT NULL,
  `applied_course_id` bigint(20) unsigned DEFAULT NULL,
  `applied_academic_year_id` bigint(20) unsigned DEFAULT NULL,
  `preferred_batch_id` bigint(20) unsigned DEFAULT NULL,
  `admission_assigned_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_by` bigint(20) unsigned DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `roll_number` varchar(50) DEFAULT NULL,
  `photo` varchar(500) DEFAULT NULL,
  `document` varchar(500) DEFAULT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `mother_name` varchar(150) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `nid_number` varchar(50) DEFAULT NULL,
  `birth_cert_number` varchar(50) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `guardian_phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `present_country_id` bigint(20) unsigned DEFAULT NULL,
  `present_admin_1_id` bigint(20) unsigned DEFAULT NULL,
  `present_admin_2_id` bigint(20) unsigned DEFAULT NULL,
  `present_admin_3_id` bigint(20) unsigned DEFAULT NULL,
  `present_address` varchar(500) DEFAULT NULL,
  `permanent_address` varchar(500) DEFAULT NULL,
  `present_post_office` varchar(50) DEFAULT NULL,
  `present_zip_code` varchar(20) DEFAULT NULL,
  `permanent_post_office` varchar(50) DEFAULT NULL,
  `permanent_zip_code` varchar(20) DEFAULT NULL,
  `permanent_country_id` bigint(20) unsigned DEFAULT NULL,
  `permanent_admin_1_id` bigint(20) unsigned DEFAULT NULL,
  `permanent_admin_2_id` bigint(20) unsigned DEFAULT NULL,
  `permanent_admin_3_id` bigint(20) unsigned DEFAULT NULL,
  `national_id_or_birth_certificate` varchar(50) DEFAULT NULL,
  `passport_number` varchar(50) DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_phone` varchar(30) DEFAULT NULL,
  `admission_date` date DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL,
  `crm_contact_id` bigint(20) unsigned DEFAULT NULL,
  `crm_lead_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_students_institute_id_index` (`institute_id`),
  KEY `training_students_reg_no_index` (`reg_no`),
  KEY `training_students_student_id_index` (`student_id`),
  KEY `training_students_uuid_index` (`uuid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `subject_type` varchar(30) DEFAULT NULL,
  `subject_code` varchar(50) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `slug` varchar(180) DEFAULT NULL,
  `short_name` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_subjects_institute_id_index` (`institute_id`),
  KEY `training_subjects_slug_index` (`slug`),
  KEY `training_subjects_subject_code_index` (`subject_code`),
  KEY `training_subjects_category_id_foreign` (`category_id`),
  CONSTRAINT `training_subjects_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `training_course_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_transactions_institute_date` (`institute_id`,`transaction_date`),
  KEY `idx_transactions_account_head` (`account_head_id`),
  KEY `fk_transactions_branch` (`branch_id`),
  KEY `fk_transactions_payment` (`payment_id`),
  KEY `fk_transactions_created_by` (`created_by`),
  KEY `transactions_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_transactions_account_head` FOREIGN KEY (`account_head_id`) REFERENCES `account_heads` (`id`),
  CONSTRAINT `fk_transactions_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_transactions_created_by` FOREIGN KEY (`created_by`) REFERENCES `institute_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_transactions_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_transactions_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `google_id` varchar(255) DEFAULT NULL,
  `avatar` varchar(500) DEFAULT NULL,
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
  UNIQUE KEY `users_google_id_unique` (`google_id`),
  KEY `users_last_login_at_index` (`last_login_at`),
  KEY `users_inactivity_warning_sent_at_index` (`inactivity_warning_sent_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vaccination_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vaccination_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `record_number` varchar(50) NOT NULL,
  `vaccination_schedule_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `vaccine_master_id` bigint(20) unsigned NOT NULL,
  `dose_number` int(11) NOT NULL,
  `administered_date` date NOT NULL,
  `administered_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `administered_by` bigint(20) unsigned NOT NULL,
  `site` varchar(50) DEFAULT NULL,
  `route` varchar(30) DEFAULT NULL,
  `dose_volume` varchar(30) DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `batch_expiry` date DEFAULT NULL,
  `manufacturer` varchar(200) DEFAULT NULL,
  `vaccine_vial_id` varchar(50) DEFAULT NULL,
  `consent_obtained` tinyint(1) NOT NULL DEFAULT 1,
  `pre_vaccination_notes` text DEFAULT NULL,
  `post_vaccination_notes` text DEFAULT NULL,
  `adverse_event` varchar(100) DEFAULT NULL,
  `adverse_event_details` text DEFAULT NULL,
  `observation_end_at` timestamp NULL DEFAULT NULL,
  `next_dose_due` date DEFAULT NULL,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `certificate_number` varchar(50) DEFAULT NULL,
  `certificate_issued_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vrec_inst_recnum` (`institute_id`,`record_number`),
  UNIQUE KEY `vrec_inst_certnum` (`institute_id`,`certificate_number`),
  KEY `vaccination_records_branch_id_foreign` (`branch_id`),
  KEY `vaccination_records_vaccine_master_id_foreign` (`vaccine_master_id`),
  KEY `vaccination_records_vaccination_schedule_id_foreign` (`vaccination_schedule_id`),
  KEY `vaccination_records_administered_by_foreign` (`administered_by`),
  KEY `vaccination_records_institute_id_administered_date_index` (`institute_id`,`administered_date`),
  KEY `vaccination_records_patient_id_administered_date_index` (`patient_id`,`administered_date`),
  CONSTRAINT `vaccination_records_administered_by_foreign` FOREIGN KEY (`administered_by`) REFERENCES `users` (`id`),
  CONSTRAINT `vaccination_records_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vaccination_records_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vaccination_records_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vaccination_records_vaccination_schedule_id_foreign` FOREIGN KEY (`vaccination_schedule_id`) REFERENCES `vaccination_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vaccination_records_vaccine_master_id_foreign` FOREIGN KEY (`vaccine_master_id`) REFERENCES `vaccine_masters` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vaccination_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vaccination_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `vaccine_master_id` bigint(20) unsigned NOT NULL,
  `dose_number` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `given_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'scheduled',
  `age_in_days_at_due` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `contraindication_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vsch_unique_schedule` (`institute_id`,`patient_id`,`vaccine_master_id`,`dose_number`),
  KEY `vaccination_schedules_branch_id_foreign` (`branch_id`),
  KEY `vaccination_schedules_vaccine_master_id_foreign` (`vaccine_master_id`),
  KEY `vsch_inst_status_due` (`institute_id`,`status`,`due_date`),
  KEY `vsch_patient_vax_dose` (`patient_id`,`vaccine_master_id`,`dose_number`),
  CONSTRAINT `vaccination_schedules_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vaccination_schedules_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vaccination_schedules_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vaccination_schedules_vaccine_master_id_foreign` FOREIGN KEY (`vaccine_master_id`) REFERENCES `vaccine_masters` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vaccine_masters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vaccine_masters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(30) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `short_name` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `protects_against` text DEFAULT NULL,
  `route` varchar(30) DEFAULT NULL,
  `site` varchar(50) DEFAULT NULL,
  `dose_volume` varchar(30) DEFAULT NULL,
  `doses_in_series` int(11) NOT NULL DEFAULT 1,
  `min_age_days` int(11) DEFAULT NULL,
  `max_age_days` int(11) DEFAULT NULL,
  `interval_days_min` int(11) DEFAULT NULL,
  `default_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vaccine_masters_institute_id_is_active_index` (`institute_id`,`is_active`),
  KEY `vaccine_masters_category_index` (`category`),
  CONSTRAINT `vaccine_masters_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vaccine_stocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vaccine_stocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `vaccine_master_id` bigint(20) unsigned NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `manufacture_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `quantity_received` int(11) NOT NULL,
  `quantity_used` int(11) NOT NULL DEFAULT 0,
  `quantity_available` int(11) NOT NULL,
  `storage_location` varchar(100) DEFAULT NULL,
  `temperature_min` decimal(5,2) DEFAULT NULL,
  `temperature_max` decimal(5,2) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vstk_inst_vax_batch` (`institute_id`,`vaccine_master_id`,`batch_number`),
  KEY `vaccine_stocks_branch_id_foreign` (`branch_id`),
  KEY `vaccine_stocks_vaccine_master_id_foreign` (`vaccine_master_id`),
  KEY `vaccine_stocks_institute_id_vaccine_master_id_status_index` (`institute_id`,`vaccine_master_id`,`status`),
  KEY `vaccine_stocks_expiry_date_index` (`expiry_date`),
  CONSTRAINT `vaccine_stocks_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vaccine_stocks_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vaccine_stocks_vaccine_master_id_foreign` FOREIGN KEY (`vaccine_master_id`) REFERENCES `vaccine_masters` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vital_signs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vital_signs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admission_id` bigint(20) unsigned DEFAULT NULL,
  `appointment_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned DEFAULT NULL,
  `doctor_id` bigint(20) unsigned DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `blood_pressure_systolic` int(11) DEFAULT NULL,
  `blood_pressure_diastolic` int(11) DEFAULT NULL,
  `pulse` int(11) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `respiratory_rate` int(11) DEFAULT NULL,
  `spo2` int(11) DEFAULT NULL,
  `pain_score` int(11) DEFAULT NULL,
  `blood_sugar` decimal(6,1) DEFAULT NULL,
  `weight` decimal(5,1) DEFAULT NULL,
  `height` decimal(5,1) DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `recorded_at` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vital_signs_recorded_by_foreign` (`recorded_by`),
  KEY `vital_signs_admission_id_index` (`admission_id`),
  KEY `vital_signs_recorded_at_index` (`recorded_at`),
  KEY `vital_signs_patient_id_recorded_at_index` (`patient_id`,`recorded_at`),
  KEY `vital_signs_appointment_id_index` (`appointment_id`),
  KEY `vital_signs_doctor_id_recorded_at_index` (`doctor_id`,`recorded_at`),
  CONSTRAINT `vital_signs_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vital_signs_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institute_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('general','cabin','icu','ccu','nicu','private') NOT NULL,
  `total_beds` int(11) NOT NULL DEFAULT 0,
  `available_beds` int(11) NOT NULL DEFAULT 0,
  `daily_rate` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wards_institute_id_index` (`institute_id`),
  KEY `wards_branch_id_index` (`branch_id`),
  CONSTRAINT `wards_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wards_institute_id_foreign` FOREIGN KEY (`institute_id`) REFERENCES `institutes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `worker_heartbeats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `worker_heartbeats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `worker_id` varchar(100) NOT NULL,
  `hostname` varchar(150) NOT NULL,
  `pid` int(11) NOT NULL,
  `queue` varchar(100) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `jobs_processed` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `worker_heartbeats_worker_id_unique` (`worker_id`),
  KEY `worker_heartbeats_last_seen_at_index` (`last_seen_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

