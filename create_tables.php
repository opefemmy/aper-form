<?php
/**
 * Create Missing Tables
 * Creates evaluation_questions and other required tables
 */

require_once 'config.php';

echo "Creating missing tables...\n\n";

$pdo = getDBConnection();

try {
    // Create evaluation_questions table
    echo "Creating evaluation_questions table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS evaluation_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(50) NOT NULL,
        question_text TEXT NOT NULL,
        question_order INT DEFAULT 0,
        question_type ENUM('rating', 'single_choice', 'multiple_choice', 'true_false', 'short_answer', 'long_answer', 'yes_no', 'scale') DEFAULT 'rating',
        options TEXT,
        is_active TINYINT(1) DEFAULT 1,
        target_staff_category ENUM('academic', 'non-teaching', 'both') DEFAULT 'both',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert default questions
    $defaultQuestions = [
        // Teaching Performance
        ['Teaching', 'How would you rate your lecture delivery?', 1, 'rating'],
        ['Teaching', 'How would you rate your class attendance?', 2, 'rating'],
        ['Teaching', 'How would you rate student engagement in your classes?', 3, 'rating'],
        ['Teaching', 'How would you rate your course preparation?', 4, 'rating'],
        ['Teaching', 'How would you rate your course coverage?', 5, 'rating'],
        ['Teaching', 'How would you rate your time management in teaching?', 6, 'rating'],

        // Research Performance
        ['Research', 'How many publications have you produced this year?', 1, 'rating'],
        ['Research', 'How would you rate your conference participation?', 2, 'rating'],
        ['Research', 'How would you rate your research grants acquisition?', 3, 'rating'],
        ['Research', 'How would you rate your journal article publications?', 4, 'rating'],
        ['Research', 'How would you rate your innovations/research outputs?', 5, 'rating'],

        // Administrative Duties
        ['Administrative', 'How would you rate your attendance to official meetings?', 1, 'rating'],
        ['Administrative', 'How would you rate your punctuality to duties?', 2, 'rating'],
        ['Administrative', 'How would you rate your leadership qualities?', 3, 'rating'],
        ['Administrative', 'How would you rate your teamwork?', 4, 'rating'],
        ['Administrative', 'How would you rate your record keeping?', 5, 'rating'],

        // Community Service
        ['Community', 'How would you rate your community development activities?', 1, 'rating'],
        ['Community', 'How would you rate your committee participation?', 2, 'rating'],
        ['Community', 'How would you rate your institutional representation?', 3, 'rating'],

        // Professional Development
        ['Professional', 'How many workshops have you attended?', 1, 'rating'],
        ['Professional', 'How many training programs have you completed?', 2, 'rating'],
        ['Professional', 'How many certifications have you earned?', 3, 'rating'],
        ['Professional', 'How many seminars have you attended?', 4, 'rating'],
    ];

    $stmt = $pdo->prepare("INSERT INTO evaluation_questions (category, question_text, question_order, question_type, target_staff_category) VALUES (?, ?, ?, ?, 'both')");

    foreach ($defaultQuestions as $q) {
        $stmt->execute($q);
    }
    echo "   ✅ Created with " . count($defaultQuestions) . " default questions\n";

    // Create grade_levels table
    echo "Creating grade_levels table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS grade_levels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level_name VARCHAR(50) NOT NULL,
        level_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $defaultLevels = ['Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5', 'Level 6', 'Level 7', 'Level 8', 'Level 9', 'Level 10'];
    $stmt = $pdo->prepare("INSERT INTO grade_levels (level_name, level_order, is_active) VALUES (?, ?, 1)");
    foreach ($defaultLevels as $index => $level) {
        $stmt->execute([$level, $index + 1]);
    }
    echo "   ✅ Created with " . count($defaultLevels) . " default levels\n";

    // Add new columns to existing tables if needed
    echo "Checking existing tables for required columns...\n";

    // Add target_staff_category to staff if not exists
    try {
        $pdo->exec("ALTER TABLE staff ADD COLUMN staff_category ENUM('academic', 'non-teaching') DEFAULT 'academic'");
        echo "   ✅ Added staff_category to staff table\n";
    } catch (Exception $e) {
        echo "   ℹ️  staff_category already exists in staff table\n";
    }

    // Add can_retake to evaluations if not exists
    try {
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN can_retake TINYINT(1) DEFAULT 1");
        echo "   ✅ Added can_retake to evaluations table\n";
    } catch (Exception $e) {
        echo "   ℹ️  can_retake already exists in evaluations table\n";
    }

    // Add evaluation workflow columns
    try {
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN evaluation_stage ENUM('pending', 'hod', 'dean', 'registrar', 'completed') DEFAULT 'pending'");
        echo "   ✅ Added evaluation_stage to evaluations table\n";
    } catch (Exception $e) {
        echo "   ℹ️  evaluation_stage already exists in evaluations table\n";
    }

    try {
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN hod_id INT DEFAULT NULL");
        echo "   ✅ Added hod_id to evaluations table\n";
    } catch (Exception $e) {
        echo "   ℹ️  hod_id already exists in evaluations table\n";
    }

    try {
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN dean_id INT DEFAULT NULL");
        echo "   ✅ Added dean_id to evaluations table\n";
    } catch (Exception $e) {
        echo "   ℹ️  dean_id already exists in evaluations table\n";
    }

    try {
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN hod_remarks TEXT");
        echo "   ✅ Added hod_remarks to evaluations table\n";
    } catch (Exception $e) {
        echo "   ℹ️  hod_remarks already exists in evaluations table\n";
    }

    try {
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN dean_remarks TEXT");
        echo "   ✅ Added dean_remarks to evaluations table\n";
    } catch (Exception $e) {
        echo "   ℹ️  dean_remarks already exists in evaluations table\n";
    }

    // Add supervisor_department and supervisor_faculty to staff table for HOD/Dean relationship
    try {
        $pdo->exec("ALTER TABLE staff ADD COLUMN supervisor_department VARCHAR(100) DEFAULT NULL");
        echo "   ✅ Added supervisor_department to staff table\n";
    } catch (Exception $e) {
        echo "   ℹ️  supervisor_department already exists in staff table\n";
    }

    try {
        $pdo->exec("ALTER TABLE staff ADD COLUMN supervisor_faculty VARCHAR(100) DEFAULT NULL");
        echo "   ✅ Added supervisor_faculty to staff table\n";
    } catch (Exception $e) {
        echo "   ℹ️  supervisor_faculty already exists in staff table\n";
    }

    try {
        $pdo->exec("ALTER TABLE staff ADD COLUMN hod_evaluator_id INT DEFAULT NULL");
        echo "   ✅ Added hod_evaluator_id to staff table\n";
    } catch (Exception $e) {
        echo "   ℹ️  hod_evaluator_id already exists in staff table\n";
    }

    try {
        $pdo->exec("ALTER TABLE staff ADD COLUMN dean_evaluator_id INT DEFAULT NULL");
        echo "   ✅ Added dean_evaluator_id to staff table\n";
    } catch (Exception $e) {
        echo "   ℹ️  dean_evaluator_id already exists in staff table\n";
    }

    // Add login_background_image setting
    echo "Adding settings...\n";
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('login_background_image', '') ON DUPLICATE KEY UPDATE setting_value = setting_value");
    $stmt->execute();
    echo "   ✅ login_background_image setting added\n";

    // Add registrar admin
    $hashedPassword = password_hash('Aper@2026', PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role) VALUES (?, ?, ?, 'registrar')");
        $stmt->execute(['Registrar', 'registrar@aper.com', $hashedPassword]);
        echo "   ✅ Registrar account created\n";
    } catch (Exception $e) {
        echo "   ℹ️  Registrar account already exists\n";
    }

    echo "\n✅ All tables created successfully!\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}