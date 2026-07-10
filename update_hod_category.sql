-- SQL Commands to add HOD as a staff category
-- Run these commands in your MySQL database

-- 1. Update evaluation_questions table to include 'hod' in target_staff_category ENUM
-- Since MySQL doesn't allow altering ENUM, we need to drop and recreate:
ALTER TABLE evaluation_questions MODIFY COLUMN target_staff_category ENUM('academic', 'non-teaching', 'hod', 'both') DEFAULT 'both';

-- 2. Update staff table to include 'hod' in staff_category ENUM
ALTER TABLE staff MODIFY COLUMN staff_category ENUM('academic', 'non-teaching', 'hod') DEFAULT 'academic';

-- 3. Optionally: Add some default HOD questions
INSERT INTO evaluation_questions (category, question_text, question_order, question_type, options, target_staff_category) VALUES
('Leadership', 'How would you rate the HOD\'s leadership quality?', 1, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod'),
('Leadership', 'Does the HOD provide adequate guidance to departmental staff?', 2, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod'),
('Administration', 'How effective is the HOD in departmental administration?', 3, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod'),
('Administration', 'Does the HOD maintain proper records and documentation?', 4, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod'),
('Staff Management', 'How would you rate the HOD\'s staff management skills?', 5, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod'),
('Staff Management', 'Does the HOD handle staff conflicts effectively?', 6, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod'),
('Curriculum', 'How effective is the HOD in curriculum development?', 7, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod'),
('Research', 'Does the HOD encourage and support research activities?', 8, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod'),
('Community', 'Does the HOD promote community engagement?', 9, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod'),
('Professional', 'Does the HOD demonstrate professional development?', 10, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod');

-- 4. Verify the changes
SELECT * FROM evaluation_questions WHERE target_staff_category = 'hod';