<?php
/**
 * Quick Database Update Script
 * Access this file in your browser to run the database updates
 * URL: http://localhost/aper%20form/run_db_update.php
 */

require_once 'config.php';

echo "<h1>Running Database Updates...</h1>\n";

$pdo = getDBConnection();

try {
    // 1. Add sub_category column
    echo "<p>1. Adding sub_category column...</p>\n";
    try {
        $pdo->exec("ALTER TABLE evaluation_questions ADD COLUMN sub_category VARCHAR(100) DEFAULT NULL");
        echo "<p style='color:green;'>✅ sub_category column added</p>\n";
    } catch (Exception $e) {
        echo "<p style='color:orange;'>ℹ️  " . $e->getMessage() . "</p>\n";
    }

    // 2. Add file_upload question type
    echo "<p>2. Adding file_upload question type...</p>\n";
    try {
        $pdo->exec("ALTER TABLE evaluation_questions MODIFY COLUMN question_type ENUM('rating', 'single_choice', 'multiple_choice', 'true_false', 'short_answer', 'long_answer', 'yes_no', 'scale', 'file_upload') DEFAULT 'rating'");
        echo "<p style='color:green;'>✅ file_upload type added</p>\n";
    } catch (Exception $e) {
        echo "<p style='color:orange;'>ℹ️  " . $e->getMessage() . "</p>\n";
    }

    // 3. Add allowed_file_types column
    echo "<p>3. Adding allowed_file_types column...</p>\n";
    try {
        $pdo->exec("ALTER TABLE evaluation_questions ADD COLUMN allowed_file_types VARCHAR(255) DEFAULT 'pdf,doc,docx'");
        echo "<p style='color:green;'>✅ allowed_file_types column added</p>\n";
    } catch (Exception $e) {
        echo "<p style='color:orange;'>ℹ️  " . $e->getMessage() . "</p>\n";
    }

    // 4. Add max_file_size column
    echo "<p>4. Adding max_file_size column...</p>\n";
    try {
        $pdo->exec("ALTER TABLE evaluation_questions ADD COLUMN max_file_size INT DEFAULT 5");
        echo "<p style='color:green;'>✅ max_file_size column added</p>\n";
    } catch (Exception $e) {
        echo "<p style='color:orange;'>ℹ️  " . $e->getMessage() . "</p>\n";
    }

    // 5. Create question_sub_categories table
    echo "<p>5. Creating question_sub_categories table...</p>\n";
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
        echo "<p style='color:green;'>✅ question_sub_categories table created</p>\n";

        // Insert default sub-categories
        $defaultSubCategories = [
            ['Teaching', 'Lecture Delivery', 1],
            ['Teaching', 'Student Engagement', 2],
            ['Teaching', 'Course Preparation', 3],
            ['Teaching', 'Course Coverage', 4],
            ['Teaching', 'Time Management', 5],
            ['Teaching', 'Assessment & Feedback', 6],
            ['Research', 'Publications', 1],
            ['Research', 'Conference Participation', 2],
            ['Research', 'Research Grants', 3],
            ['Research', 'Innovations', 4],
            ['Research', 'Journal Articles', 5],
            ['Administrative', 'Meeting Attendance', 1],
            ['Administrative', 'Punctuality', 2],
            ['Administrative', 'Leadership', 3],
            ['Administrative', 'Teamwork', 4],
            ['Administrative', 'Record Keeping', 5],
            ['Community', 'Community Development', 1],
            ['Community', 'Committee Participation', 2],
            ['Community', 'Institutional Representation', 3],
            ['Professional', 'Workshops', 1],
            ['Professional', 'Training Programs', 2],
            ['Professional', 'Certifications', 3],
            ['Professional', 'Seminars', 4],
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO question_sub_categories (category, sub_category_name, sub_category_order) VALUES (?, ?, ?)");
        foreach ($defaultSubCategories as $sc) {
            $stmt->execute($sc);
        }
        echo "<p style='color:green;'>✅ Added " . count($defaultSubCategories) . " default sub-categories</p>\n";

    } catch (Exception $e) {
        echo "<p style='color:orange;'>ℹ️  " . $e->getMessage() . "</p>\n";
    }

    // 6. Create uploads directory
    echo "<p>6. Creating uploads directory...</p>\n";
    $uploadDir = 'uploads/question_documents';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        echo "<p style='color:green;'>✅ Created uploads/question_documents directory</p>\n";
    } else {
        echo "<p style='color:orange;'>ℹ️  uploads directory already exists</p>\n";
    }

    // 7. Create question_uploads table
    echo "<p>7. Creating question_uploads table...</p>\n";
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
        echo "<p style='color:green;'>✅ question_uploads table created</p>\n";
    } catch (Exception $e) {
        echo "<p style='color:orange;'>ℹ️  " . $e->getMessage() . "</p>\n";
    }

    // 8. Add question_group column for grouping related questions
    echo "<p>8. Adding question_group column...</p>\n";
    try {
        $pdo->exec("ALTER TABLE evaluation_questions ADD COLUMN question_group VARCHAR(100) DEFAULT NULL");
        echo "<p style='color:green;'>✅ question_group column added</p>\n";
    } catch (Exception $e) {
        echo "<p style='color:orange;'>ℹ️  " . $e->getMessage() . "</p>\n";
    }

    // 9. Add question_label column for sub-parts (a, b, c, etc.)
    echo "<p>9. Adding question_label column...</p>\n";
    try {
        $pdo->exec("ALTER TABLE evaluation_questions ADD COLUMN question_label VARCHAR(10) DEFAULT NULL");
        echo "<p style='color:green;'>✅ question_label column added</p>\n";
    } catch (Exception $e) {
        echo "<p style='color:orange;'>ℹ️  " . $e->getMessage() . "</p>\n";
    }

    echo "<h2 style='color:green;'>✅ All database updates completed!</h2>\n";
    echo "<p><a href='questions.php'>Go to Questions Page</a></p>\n";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Error: " . $e->getMessage() . "</h2>\n";
}