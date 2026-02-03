-- Migration: Convert from percentage-based to direct points system
-- This migration:
-- 1. Renames 'percentage' column to 'points' in controle_template_phases
-- 2. Drops the 'max_points' column (no longer needed)
-- 3. Updates existing data to convert percentage to direct point values

-- Step 1: Add new 'points' column
ALTER TABLE controle_template_phases 
ADD COLUMN points DECIMAL(5,2) NOT NULL DEFAULT 0 
AFTER title;

-- Step 2: Convert existing percentage values to points
-- Formula: points = (percentage / 100) * template.total_points
UPDATE controle_template_phases p
INNER JOIN controle_templates t ON p.template_id = t.id
SET p.points = ROUND((p.percentage / 100) * t.total_points, 2);

-- Step 3: Drop old columns
ALTER TABLE controle_template_phases 
DROP COLUMN percentage,
DROP COLUMN max_points;

-- Verification query (run this after migration to check):
-- SELECT t.title as template, t.total_points, p.title as phase, p.points
-- FROM controle_templates t
-- LEFT JOIN controle_template_phases p ON t.id = p.template_id
-- ORDER BY t.id, p.id;
