-- Add Non-Teaching Staff Junior category
-- Run this SQL to update the database

-- 1. Update staff table to include 'non-teaching-junior' in staff_category ENUM
ALTER TABLE staff MODIFY COLUMN staff_category ENUM('academic', 'non-teaching', 'non-teaching-junior', 'hod') DEFAULT 'academic';

-- 2. Update evaluations table to include 'non-teaching-junior' in staff_category ENUM
ALTER TABLE evaluations MODIFY COLUMN staff_category ENUM('academic', 'non-teaching', 'non-teaching-junior', 'hod') DEFAULT 'academic';

-- 3. Update evaluation_questions table to include 'non-teaching-junior' in target_staff_category ENUM
ALTER TABLE evaluation_questions MODIFY COLUMN target_staff_category ENUM('academic', 'non-teaching', 'non-teaching-junior', 'hod', 'both') DEFAULT 'both';

-- 4. Update evaluator_type ENUM in staff table to change HOD to Supervising Officer and remove Dean
ALTER TABLE staff MODIFY COLUMN evaluator_type ENUM('Supervising Officer', 'Registrar', '') DEFAULT '';

-- 5. Drop dean-related columns if they exist (we're removing Dean role entirely)
-- These are optional cleanup - only run if you want to remove Dean data completely
-- ALTER TABLE evaluations DROP COLUMN IF EXISTS dean_id;
-- ALTER TABLE evaluations DROP COLUMN IF EXISTS dean_name;
-- ALTER TABLE evaluations DROP COLUMN IF EXISTS dean_remarks;
-- ALTER TABLE evaluations DROP COLUMN IF EXISTS dean_date;

-- 6. Add new columns for staff review workflow
-- Add staff_consent column (staff agrees or disagrees with supervising officer's grade)
ALTER TABLE evaluations ADD COLUMN staff_consent VARCHAR(20) DEFAULT NULL;
-- Add staff_rejection_reason column (reason if staff disagrees)
ALTER TABLE evaluations ADD COLUMN staff_rejection_reason TEXT DEFAULT NULL;
-- Add supervising_officer_final_comments column (comments after staff review)
ALTER TABLE evaluations ADD COLUMN supervising_officer_final_comments TEXT DEFAULT NULL;