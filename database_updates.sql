-- Database Updates for New Features
-- Run this SQL in your MySQL database

-- 1. Add target_staff_category column to evaluation_questions table
ALTER TABLE evaluation_questions ADD COLUMN target_staff_category ENUM('academic', 'non-teaching', 'both') DEFAULT 'both';

-- 2. Add login_background_image setting
INSERT INTO settings (setting_key, setting_value) VALUES ('login_background_image', '') ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- 3. Create grade_levels table for customizable grade levels
CREATE TABLE IF NOT EXISTS grade_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_name VARCHAR(50) NOT NULL,
    level_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default grade levels if table is empty
INSERT INTO grade_levels (level_name, level_order) VALUES
('Level 1', 1),
('Level 2', 2),
('Level 3', 3),
('Level 4', 4),
('Level 5', 5),
('Level 6', 6),
('Level 7', 7),
('Level 8', 8),
('Level 9', 9),
('Level 10', 10)
ON DUPLICATE KEY UPDATE level_name = level_name;

-- 4. Add password_reset_token and password_reset_expires to staff table
ALTER TABLE staff ADD COLUMN password_reset_token VARCHAR(255) NULL;
ALTER TABLE staff ADD COLUMN password_reset_expires DATETIME NULL;

-- 5. Add can_retake column to evaluations table for re-enabling submission
ALTER TABLE evaluations ADD COLUMN can_retake TINYINT(1) DEFAULT 1;

-- 6. Create staff_category_questions table for custom questions per category
CREATE TABLE IF NOT EXISTS staff_category_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    staff_category ENUM('academic', 'non-teaching') NOT NULL,
    question_text TEXT NOT NULL,
    question_order INT DEFAULT 0,
    question_type ENUM('rating', 'single_choice', 'multiple_choice', 'true_false', 'short_answer', 'long_answer', 'yes_no', 'scale') DEFAULT 'rating',
    options TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. Add registrar role if not exists
INSERT INTO admins (name, email, password, role) VALUES
('Registrar', 'registrar@aper.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'registrar')
ON DUPLICATE KEY UPDATE email = email;

-- 8. Add designated_evaluator column to admins table
ALTER TABLE admins ADD COLUMN designated_evaluator TINYINT(1) DEFAULT 0;

-- 9. Add staff_category to evaluation_questions if not exists
ALTER TABLE evaluation_questions ADD COLUMN staff_category ENUM('academic', 'non-teaching', 'both') DEFAULT 'both';

-- 10. Add evaluation workflow columns (HOD and Dean stages)
ALTER TABLE evaluations ADD COLUMN evaluation_stage VARCHAR(50) DEFAULT 'pending';

-- HOD Assessment columns
ALTER TABLE evaluations ADD COLUMN hod_id INT NULL;
ALTER TABLE evaluations ADD COLUMN hod_name VARCHAR(100) NULL;
ALTER TABLE evaluations ADD COLUMN hod_remarks TEXT NULL;
ALTER TABLE evaluations ADD COLUMN hod_date DATE NULL;

-- Dean Assessment columns
ALTER TABLE evaluations ADD COLUMN dean_id INT NULL;
ALTER TABLE evaluations ADD COLUMN dean_name VARCHAR(100) NULL;
ALTER TABLE evaluations ADD COLUMN dean_remarks TEXT NULL;
ALTER TABLE evaluations ADD COLUMN dean_date DATE NULL;

-- Add foreign key for hod_id and dean_id
ALTER TABLE evaluations ADD FOREIGN KEY (hod_id) REFERENCES admins(id) ON DELETE SET NULL;
ALTER TABLE evaluations ADD FOREIGN KEY (dean_id) REFERENCES admins(id) ON DELETE SET NULL;