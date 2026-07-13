-- Database Update: Add sub_category and file_upload support to evaluation_questions
-- Run this SQL file in your database to add the new features

-- 1. Add sub_category column to evaluation_questions
ALTER TABLE evaluation_questions ADD COLUMN sub_category VARCHAR(100) DEFAULT NULL;

-- 2. Update question_type enum to include file_upload
ALTER TABLE evaluation_questions MODIFY COLUMN question_type ENUM('rating', 'single_choice', 'multiple_choice', 'true_false', 'short_answer', 'long_answer', 'yes_no', 'scale', 'file_upload') DEFAULT 'rating';

-- 3. Add allowed_file_types column to specify what file types are allowed
ALTER TABLE evaluation_questions ADD COLUMN allowed_file_types VARCHAR(255) DEFAULT 'pdf,doc,docx';

-- 4. Add max_file_size column (in MB)
ALTER TABLE evaluation_questions ADD COLUMN max_file_size INT DEFAULT 5;

-- 5. Create question_sub_categories table for managing sub-categories
CREATE TABLE IF NOT EXISTS question_sub_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100) NOT NULL,
    sub_category_name VARCHAR(100) NOT NULL,
    sub_category_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_category_sub (category, sub_category_name)
);

-- Insert default sub-categories
INSERT IGNORE INTO question_sub_categories (category, sub_category_name, sub_category_order) VALUES
-- Teaching sub-categories
('Teaching', 'Lecture Delivery', 1),
('Teaching', 'Student Engagement', 2),
('Teaching', 'Course Preparation', 3),
('Teaching', 'Course Coverage', 4),
('Teaching', 'Time Management', 5),
('Teaching', 'Assessment & Feedback', 6),
-- Research sub-categories
('Research', 'Publications', 1),
('Research', 'Conference Participation', 2),
('Research', 'Research Grants', 3),
('Research', 'Innovations', 4),
('Research', 'Journal Articles', 5),
-- Administrative sub-categories
('Administrative', 'Meeting Attendance', 1),
('Administrative', 'Punctuality', 2),
('Administrative', 'Leadership', 3),
('Administrative', 'Teamwork', 4),
('Administrative', 'Record Keeping', 5),
-- Community sub-categories
('Community', 'Community Development', 1),
('Community', 'Committee Participation', 2),
('Community', 'Institutional Representation', 3),
-- Professional sub-categories
('Professional', 'Workshops', 1),
('Professional', 'Training Programs', 2),
('Professional', 'Certifications', 3),
('Professional', 'Seminars', 4);

-- 6. Create question_uploads table to track uploaded files
CREATE TABLE IF NOT EXISTS question_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    question_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES evaluation_questions(id) ON DELETE CASCADE
);