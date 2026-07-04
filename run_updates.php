<?php
/**
 * Run Database Updates
 * This script applies all the necessary database changes
 */

require_once 'config.php';

echo "Starting database updates...\n\n";

$pdo = getDBConnection();

try {
    // 1. Add target_staff_category column to evaluation_questions table
    echo "1. Adding target_staff_category column to evaluation_questions...\n";
    $pdo->exec("ALTER TABLE evaluation_questions ADD COLUMN target_staff_category ENUM('academic', 'non-teaching', 'both') DEFAULT 'both'");
    echo "   ✓ Done\n";

    // 2. Add login_background_image setting
    echo "2. Adding login_background_image setting...\n";
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('login_background_image', '') ON DUPLICATE KEY UPDATE setting_value = ''");
    $stmt->execute();
    echo "   ✓ Done\n";

    // 3. Create grade_levels table
    echo "3. Creating grade_levels table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS grade_levels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level_name VARCHAR(50) NOT NULL,
        level_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "   ✓ Done\n";

    // Insert default grade levels
    echo "4. Inserting default grade levels...\n";
    $defaultLevels = ['Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5', 'Level 6', 'Level 7', 'Level 8', 'Level 9', 'Level 10'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO grade_levels (level_name, level_order, is_active) VALUES (?, ?, 1)");
    foreach ($defaultLevels as $index => $level) {
        $stmt->execute([$level, $index + 1]);
    }
    echo "   ✓ Done\n";

    // 4. Add password reset columns to staff table
    echo "5. Adding password reset columns to staff table...\n";
    $pdo->exec("ALTER TABLE staff ADD COLUMN password_reset_token VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE staff ADD COLUMN password_reset_expires DATETIME NULL");
    echo "   ✓ Done\n";

    // 5. Add can_retake column to evaluations table
    echo "6. Adding can_retake column to evaluations table...\n";
    $pdo->exec("ALTER TABLE evaluations ADD COLUMN can_retake TINYINT(1) DEFAULT 1");
    echo "   ✓ Done\n";

    // 6. Create staff_category_questions table
    echo "7. Creating staff_category_questions table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS staff_category_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(50) NOT NULL,
        staff_category ENUM('academic', 'non-teaching') NOT NULL,
        question_text TEXT NOT NULL,
        question_order INT DEFAULT 0,
        question_type ENUM('rating', 'single_choice', 'multiple_choice', 'true_false', 'short_answer', 'long_answer', 'yes_no', 'scale') DEFAULT 'rating',
        options TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "   ✓ Done\n";

    // 7. Add registrar admin
    echo "8. Adding registrar admin account...\n";
    $hashedPassword = password_hash('Aper@2026', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role) VALUES (?, ?, ?, 'registrar') ON DUPLICATE KEY UPDATE email = email");
    $stmt->execute(['Registrar', 'registrar@aper.com', $hashedPassword]);
    echo "   ✓ Done\n";

    // 8. Add designated_evaluator column to admins table
    echo "9. Adding designated_evaluator column to admins table...\n";
    $pdo->exec("ALTER TABLE admins ADD COLUMN designated_evaluator TINYINT(1) DEFAULT 0");
    echo "   ✓ Done\n";

    // 10. Update existing questions to have target_staff_category = 'both'
    echo "10. Updating existing questions to target all staff...\n";
    $pdo->exec("UPDATE evaluation_questions SET target_staff_category = 'both' WHERE target_staff_category IS NULL");
    echo "   ✓ Done\n";

    echo "\n===========================================\n";
    echo "✅ All database updates completed successfully!\n";
    echo "===========================================\n\n";

    echo "Login credentials:\n";
    echo "- Registrar: registrar@aper.com / Aper@2026\n";
    echo "- Admin: admin@aper.com / Aper@2026\n";
    echo "- Super Admin: super@admin.com / Aper@2026\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}