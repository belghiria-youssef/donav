-- Controles System Tables
-- Supports controle templates per year level with individual/team grading modes
-- Separated into: Templates (structure definition) + Instances (actual grading per class)

-- ============================================================================
-- CLEANUP: Drop old controle tables (if they exist with old schema)
-- ============================================================================
-- Drop in reverse dependency order
DROP TABLE IF EXISTS `controle_results`;
DROP TABLE IF EXISTS `controle_notes_team`;
DROP TABLE IF EXISTS `controle_notes_individual`;
DROP TABLE IF EXISTS `controle_instances`;
DROP TABLE IF EXISTS `controle_template_phases`;
DROP TABLE IF EXISTS `controle_templates`;

-- Also drop old v1 tables if they exist
DROP TABLE IF EXISTS `controle_notes`;
DROP TABLE IF EXISTS `controle_phases`;
DROP TABLE IF EXISTS `controles`;

-- ============================================================================
-- STEP 1: Create year_levels table (teacher-defined)
-- ============================================================================
-- Teachers can create their own year levels (1ère année, 2ème année, Master 1, etc.)
CREATE TABLE IF NOT EXISTS `year_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Display name like "1ère année", "Master 1", etc.',
  `short_name` varchar(20) DEFAULT NULL COMMENT 'Short code like "1A", "M1", etc.',
  `order_index` int(11) NOT NULL DEFAULT 0 COMMENT 'For sorting display',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_year_teacher` (`created_by`),
  CONSTRAINT `fk_year_teacher` FOREIGN KEY (`created_by`) REFERENCES `enseignants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- STEP 2: Add year_level_id to classes table (references year_levels)
-- ============================================================================
-- Classes/Groups are linked to a year level
ALTER TABLE `classes` ADD COLUMN IF NOT EXISTS `year_level_id` int(11) DEFAULT NULL 
  COMMENT 'Reference to year_levels table';
ALTER TABLE `classes` ADD CONSTRAINT `fk_class_year_level` 
  FOREIGN KEY (`year_level_id`) REFERENCES `year_levels` (`id`) ON DELETE SET NULL;

-- Drop old year_level column if it exists (was tinyint, now we use year_level_id)
-- ALTER TABLE `classes` DROP COLUMN IF EXISTS `year_level`;

-- ============================================================================
-- STEP 3: Controle Templates - Define structure only (per year level)
-- ============================================================================
-- Templates define the structure of a controle (phases, percentages, grading modes)
-- Templates are created per year level, not per class
CREATE TABLE IF NOT EXISTS `controle_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `year_level_id` int(11) NOT NULL COMMENT 'Reference to year_levels table',
  `total_points` decimal(5,2) NOT NULL DEFAULT 20.00,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_year_level` (`year_level_id`),
  KEY `fk_template_teacher` (`created_by`),
  CONSTRAINT `fk_template_year_level` FOREIGN KEY (`year_level_id`) REFERENCES `year_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_template_teacher` FOREIGN KEY (`created_by`) REFERENCES `enseignants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- STEP 4: Template Phases - Define phases within a template
-- ============================================================================
-- Each phase has a grading mode (individual or team)
-- If team mode: all team members receive the same note given to the team
CREATE TABLE IF NOT EXISTS `controle_template_phases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `percentage` decimal(5,2) NOT NULL COMMENT 'Weight percentage of this phase in total',
  `max_points` decimal(5,2) NOT NULL DEFAULT 20.00 COMMENT 'Maximum points for this phase',
  `grading_mode` enum('individual','team') NOT NULL DEFAULT 'individual' 
    COMMENT 'individual=each student graded separately, team=all team members get same note',
  `order_index` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_phases_template` (`template_id`),
  CONSTRAINT `fk_phases_template` FOREIGN KEY (`template_id`) REFERENCES `controle_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- STEP 5: Controle Instances - Apply template to a specific class for grading
-- ============================================================================
-- When teacher wants to grade a class, they create an instance of a template
-- This links a template to a specific class for actual note entry
CREATE TABLE IF NOT EXISTS `controle_instances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `session_name` varchar(255) DEFAULT NULL COMMENT 'Optional name like "Session Normale 2026"',
  `is_locked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Lock to prevent further edits',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_instance_template` (`template_id`),
  KEY `fk_instance_class` (`class_id`),
  KEY `fk_instance_teacher` (`created_by`),
  CONSTRAINT `fk_instance_template` FOREIGN KEY (`template_id`) REFERENCES `controle_templates` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_instance_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_instance_teacher` FOREIGN KEY (`created_by`) REFERENCES `enseignants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- STEP 6: Individual Notes - For phases with individual grading mode
-- ============================================================================
-- Stores notes for phases where each student is graded individually
CREATE TABLE IF NOT EXISTS `controle_notes_individual` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instance_id` int(11) NOT NULL,
  `phase_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `note` decimal(5,2) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_instance_phase_student` (`instance_id`, `phase_id`, `student_id`),
  KEY `fk_notes_ind_instance` (`instance_id`),
  KEY `fk_notes_ind_phase` (`phase_id`),
  KEY `fk_notes_ind_student` (`student_id`),
  CONSTRAINT `fk_notes_ind_instance` FOREIGN KEY (`instance_id`) REFERENCES `controle_instances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_ind_phase` FOREIGN KEY (`phase_id`) REFERENCES `controle_template_phases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_ind_student` FOREIGN KEY (`student_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- STEP 7: Team Notes - For phases with team grading mode
-- ============================================================================
-- Stores notes for phases where team members all receive the same note
-- The note given to a team applies to ALL members of that team
CREATE TABLE IF NOT EXISTS `controle_notes_team` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instance_id` int(11) NOT NULL,
  `phase_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `note` decimal(5,2) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_instance_phase_team` (`instance_id`, `phase_id`, `team_id`),
  KEY `fk_notes_team_instance` (`instance_id`),
  KEY `fk_notes_team_phase` (`phase_id`),
  KEY `fk_notes_team_team` (`team_id`),
  CONSTRAINT `fk_notes_team_instance` FOREIGN KEY (`instance_id`) REFERENCES `controle_instances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_team_phase` FOREIGN KEY (`phase_id`) REFERENCES `controle_template_phases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_team_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- STEP 8: Computed Final Results - Cached final notes per student per instance
-- ============================================================================
-- Stores the computed final note for each student
-- For team phases: student's note is fetched from their team's note
CREATE TABLE IF NOT EXISTS `controle_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instance_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `final_note` decimal(5,2) NOT NULL,
  `breakdown_json` text COMMENT 'JSON with phase-by-phase breakdown',
  `computed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_instance_student` (`instance_id`, `student_id`),
  KEY `fk_results_instance` (`instance_id`),
  KEY `fk_results_student` (`student_id`),
  CONSTRAINT `fk_results_instance` FOREIGN KEY (`instance_id`) REFERENCES `controle_instances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_results_student` FOREIGN KEY (`student_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- VIEWS for easier querying
-- ============================================================================

-- View: Get templates with their phases count
CREATE OR REPLACE VIEW `v_controle_templates` AS
SELECT 
  t.*,
  y.name as year_level_name,
  COUNT(p.id) as phase_count,
  COALESCE(SUM(p.percentage), 0) as total_percentage
FROM `controle_templates` t
LEFT JOIN `year_levels` y ON t.year_level_id = y.id
LEFT JOIN `controle_template_phases` p ON t.id = p.template_id
GROUP BY t.id;

-- View: Get available templates for a class based on year level
CREATE OR REPLACE VIEW `v_class_available_templates` AS
SELECT 
  c.id as class_id,
  c.nom as class_name,
  c.year_level_id,
  y.name as year_level_name,
  t.id as template_id,
  t.title as template_title,
  t.total_points
FROM `classes` c
JOIN `year_levels` y ON c.year_level_id = y.id
JOIN `controle_templates` t ON c.year_level_id = t.year_level_id
WHERE t.is_active = 1;
