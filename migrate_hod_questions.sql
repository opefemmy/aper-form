-- SQL Migration: Move HOD Questions from JSON to Database
-- Run this in your database to migrate HOD questions

-- 1. First, insert default HOD questions if they don't exist
-- Academic HOD Questions
INSERT INTO evaluation_questions (category, question_text, question_order, question_type, options, target_staff_category, is_active)
SELECT 'Leadership', 'How would you rate the HOD\'s leadership quality in academic matters?', 1, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM evaluation_questions WHERE question_text LIKE '%leadership quality%' AND target_staff_category = 'hod');

INSERT INTO evaluation_questions (category, question_text, question_order, question_type, options, target_staff_category, is_active)
SELECT 'Curriculum', 'Does the HOD effectively coordinate curriculum development?', 2, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM evaluation_questions WHERE question_text LIKE '%curriculum%' AND target_staff_category = 'hod');

INSERT INTO evaluation_questions (category, question_text, question_order, question_type, options, target_staff_category, is_active)
SELECT 'Teaching', 'Does the HOD monitor teaching quality effectively?', 3, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM evaluation_questions WHERE question_text LIKE '%teaching quality%' AND target_staff_category = 'hod');

INSERT INTO evaluation_questions (category, question_text, question_order, question_type, options, target_staff_category, is_active)
SELECT 'Research', 'Does the HOD encourage and support research activities?', 4, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM evaluation_questions WHERE question_text LIKE '%research%' AND target_staff_category = 'hod');

INSERT INTO evaluation_questions (category, question_text, question_order, question_type, options, target_staff_category, is_active)
SELECT 'Staff Development', 'Does the HOD provide guidance for staff professional development?', 5, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM evaluation_questions WHERE question_text LIKE '%staff professional development%' AND target_staff_category = 'hod');

INSERT INTO evaluation_questions (category, question_text, question_order, question_type, options, target_staff_category, is_active)
SELECT 'Administration', 'How effective is the HOD in departmental administration?', 6, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM evaluation_questions WHERE question_text LIKE '%departmental administration%' AND target_staff_category = 'hod');

INSERT INTO evaluation_questions (category, question_text, question_order, question_type, options, target_staff_category, is_active)
SELECT 'Meetings', 'Does the HOD conduct effective department meetings?', 7, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM evaluation_questions WHERE question_text LIKE '%department meetings%' AND target_staff_category = 'hod');

INSERT INTO evaluation_questions (category, question_text, question_order, question_type, options, target_staff_category, is_active)
SELECT 'Records', 'Does the HOD maintain proper academic records and documentation?', 8, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM evaluation_questions WHERE question_text LIKE '%academic records%' AND target_staff_category = 'hod');

INSERT INTO evaluation_questions (category, question_text, question_order, question_type, options, target_staff_category, is_active)
SELECT 'Timetable', 'Does the HOD effectively manage course timetabling?', 9, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM evaluation_questions WHERE question_text LIKE '%timetabling%' AND target_staff_category = 'hod');

INSERT INTO evaluation_questions (category, question_text, question_order, question_type, options, target_staff_category, is_active)
SELECT 'Community', 'Does the HOD promote community engagement?', 10, 'rating', '1=Poor,2=Fair,3=Good,4=Very Good,5=Excellent', 'hod', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM evaluation_questions WHERE question_text LIKE '%community engagement%' AND target_staff_category = 'hod');

-- 2. Verify the questions were added
SELECT * FROM evaluation_questions WHERE target_staff_category = 'hod';

-- 3. You can now manage HOD questions from the Questions page (questions.php)
-- Just set "Staff Category" to "HOD Only" when adding/editing questions