<?php
/**
 * Database Update: Add sub_category and file_upload support to evaluation_questions
 */

require_once 'config.php';

echo "Running database updates for sub-categories and file upload...\n\n";

$pdo = getDBConnection();

try {
    // 1. Add sub_category column to evaluation_questions
    echo "1. Adding sub_category column to evaluation_questions...\n";
    try {
        $pdo->exec("ALTER TABLE evaluation_questions ADD COLUMN sub_category VARCHAR(100) DEFAULT NULL");
        echo "   ✅ sub_category column added\n";
    } catch (Exception $e) {
        echo "   ℹ️  sub_category already exists\n";
    }

    // 2. Add question_type for file_upload
    echo "2. Updating question_type enum to include file_upload...\n";
    try {
        $pdo->exec("ALTER TABLE evaluation_questions MODIFY COLUMN question_type ENUM('rating', 'single_choice', 'multiple_choice', 'true_false', 'short_answer', 'long_answer', 'yes_no', 'scale', 'file_upload') DEFAULT 'rating'");
        echo "   ✅ question_type updated with file_upload\n";
    } catch (Exception $e) {
        echo "   ℹ️  question_type already has file_upload\n";
    }

    // 3. Add allowed_file_types column to specify what file types are allowed
    echo "3. Adding allowed_file_types column...\n";
    try {
        $pdo->exec("ALTER TABLE evaluation_questions ADD COLUMN allowed_file_types VARCHAR(255) DEFAULT 'pdf,doc,docx'");
        echo "   ✅ allowed_file_types column added\n";
    } catch (Exception $e) {
        echo "   ℹ️  allowed_file_types already exists\n";
    }

    // 4. Add max_file_size column (in MB)
    echo "4. Adding max_file_size column...\n";
    try {
        $pdo->exec("ALTER TABLE evaluation_questions ADD COLUMN max_file_size INT DEFAULT 5");
        echo "   ✅ max_file_size column added\n";
    } catch (Exception $e) {
        echo "   ℹ️  max_file_size already exists\n";
    }

    // 5. Create question_sub_categories table for managing sub-categories
    echo "5. Creating question_sub_categories table...\n";
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS question_sub_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(100) NOT NULL,
            sub_category_name VARCHAR(100) NOT NULL,
            sub_category_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_category_sub (category, sub_category_name)
        )");
        echo "   ✅ question_sub_categories table created\n";

        // Insert default sub-categories
        $defaultSubCategories = [
            // Teaching sub-categories
            ['Teaching', 'Lecture Delivery', 1],
            ['Teaching', 'Student Engagement', 2],
            ['Teaching', 'Course Preparation', 3],
            ['Teaching', 'Course Coverage', 4],
            ['Teaching', 'Time Management', 5],
            ['Teaching', 'Assessment & Feedback', 6],

            // Research sub-categories
            ['Research', 'Publications', 1],
            ['Research', 'Conference Participation', 2],
            ['Research', 'Research Grants', 3],
            ['Research', 'Innovations', 4],
            ['Research', 'Journal Articles', 5],

            // Administrative sub-categories
            ['Administrative', 'Meeting Attendance', 1],
            ['Administrative', 'Punctuality', 2],
            ['Administrative', 'Leadership', 3],
            ['Administrative', 'Teamwork', 4],
            ['Administrative', 'Record Keeping', 5],

            // Community sub-categories
            ['Community', 'Community Development', 1],
            ['Community', 'Committee Participation', 2],
            ['Community', 'Institutional Representation', 3],

            // Professional sub-categories
            ['Professional', 'Workshops', 1],
            ['Professional', 'Training Programs', 2],
            ['Professional', 'Certifications', 3],
            ['Professional', 'Seminars', 4],
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO question_sub_categories (category, sub_category_name, sub_category_order) VALUES (?, ?, ?)");
        foreach ($defaultSubCategories as $sc) {
            $stmt->execute($sc);
        }
        echo "   ✅ Added " . count($defaultSubCategories) . " default sub-categories\n";

    } catch (Exception $e) {
        echo "   ℹ️  question_sub_categories table already exists or error: " . $e->getMessage() . "\n";
    }

    // 6. Create uploads directory for question documents
    echo "6. Creating uploads directory...\n";
    $uploadDir = 'uploads/question_documents';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        echo "   ✅ Created uploads/question_documents directory\n";
    } else {
        echo "   ℹ️  uploads directory already exists\n";
    }

    // 7. Create question_uploads table to track uploaded files
    echo "7. Creating question_uploads table...\n";
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS question_uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            evaluation_id INT NOT NULL,
            question_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT DEFAULT 0,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
            FOREIGN KEY (question_id) REFERENCES evaluation_questions(id) ON DELETE CASCADE
        )");
        echo "   ✅ question_uploads table created\n";
    } catch (Exception $e) {
        echo "   ℹ️  question_uploads table already exists or error: " . $e->getMessage() . "\n";
    }

    echo "\n✅ All database updates completed successfully!\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}