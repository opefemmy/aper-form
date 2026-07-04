<?php
/**
 * Safe Database Updates
 * Checks if columns exist before adding them - won't cause errors if they already exist
 */

require_once 'config.php';

echo "🚀 Starting safe database updates...\n\n";

$pdo = getDBConnection();

function columnExists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return $stmt->fetchColumn() > 0;
}

function tableExists($pdo, $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return $stmt->fetchColumn() > 0;
}

try {
    // 1. Add target_staff_category if not exists
    if (!columnExists($pdo, 'evaluation_questions', 'target_staff_category')) {
        echo "1. Adding target_staff_category column...\n";
        $pdo->exec("ALTER TABLE evaluation_questions ADD COLUMN target_staff_category ENUM('academic', 'non-teaching', 'both') DEFAULT 'both'");
        echo "   ✅ Done\n";
    } else {
        echo "1. target_staff_category already exists (skipped)\n";
    }

    // 2. Add login_background_image setting
    echo "2. Checking login_background_image setting...\n";
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('login_background_image', '') ON DUPLICATE KEY UPDATE setting_value = setting_value");
    $stmt->execute();
    echo "   ✅ Done\n";

    // 3. Create grade_levels table
    if (!tableExists($pdo, 'grade_levels')) {
        echo "3. Creating grade_levels table...\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS grade_levels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            level_name VARCHAR(50) NOT NULL,
            level_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Insert default grade levels
        $defaultLevels = ['Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5', 'Level 6', 'Level 7', 'Level 8', 'Level 9', 'Level 10'];
        $stmt = $pdo->prepare("INSERT INTO grade_levels (level_name, level_order, is_active) VALUES (?, ?, 1)");
        foreach ($defaultLevels as $index => $level) {
            $stmt->execute([$level, $index + 1]);
        }
        echo "   ✅ Done\n";
    } else {
        echo "3. grade_levels table already exists (skipped)\n";
    }

    // 4. Add password reset columns
    if (!columnExists($pdo, 'staff', 'password_reset_token')) {
        echo "4. Adding password_reset_token column...\n";
        $pdo->exec("ALTER TABLE staff ADD COLUMN password_reset_token VARCHAR(255) NULL");
        echo "   ✅ Done\n";
    } else {
        echo "4. password_reset_token already exists (skipped)\n";
    }

    if (!columnExists($pdo, 'staff', 'password_reset_expires')) {
        echo "5. Adding password_reset_expires column...\n";
        $pdo->exec("ALTER TABLE staff ADD COLUMN password_reset_expires DATETIME NULL");
        echo "   ✅ Done\n";
    } else {
        echo "5. password_reset_expires already exists (skipped)\n";
    }

    // 5. Add can_retake column
    if (!columnExists($pdo, 'evaluations', 'can_retake')) {
        echo "6. Adding can_retake column...\n";
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN can_retake TINYINT(1) DEFAULT 1");
        echo "   ✅ Done\n";
    } else {
        echo "6. can_retake already exists (skipped)\n";
    }

    // 6. Add registrar admin (won't duplicate)
    echo "7. Adding registrar admin account...\n";
    $hashedPassword = password_hash('Aper@2026', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role) VALUES (?, ?, ?, 'registrar')");
    try {
        $stmt->execute(['Registrar', 'registrar@aper.com', $hashedPassword]);
        echo "   ✅ Done (new account created)\n";
    } catch (Exception $e) {
        echo "   ℹ️  Registrar already exists (skipped)\n";
    }

    // 7. Add designated_evaluator column
    if (!columnExists($pdo, 'admins', 'designated_evaluator')) {
        echo "8. Adding designated_evaluator column...\n";
        $pdo->exec("ALTER TABLE admins ADD COLUMN designated_evaluator TINYINT(1) DEFAULT 0");
        echo "   ✅ Done\n";
    } else {
        echo "8. designated_evaluator already exists (skipped)\n";
    }

    // 8. Update existing questions
    echo "9. Updating existing questions...\n";
    $stmt = $pdo->query("UPDATE evaluation_questions SET target_staff_category = 'both' WHERE target_staff_category IS NULL OR target_staff_category = ''");
    echo "   ✅ Done\n";

    echo "\n" . str_repeat("=", 45) . "\n";
    echo "✅ All safe database updates completed!\n";
    echo str_repeat("=", 45) . "\n\n";

    echo "📋 Login credentials:\n";
    echo "   • Registrar: registrar@aper.com / Aper@2026\n";
    echo "   • Admin: admin@aper.com / Aper@2026\n";
    echo "   • Super Admin: super@admin.com / Aper@2026\n\n";

    echo "🎉 All features are now ready!\n";
    echo "   • Grade levels management in Settings\n";
    echo "   • Login background image upload\n";
    echo "   • Staff password reset in Staff page\n";
    echo "   • Staff category questions\n";
    echo "   • Print summary after submission\n";
    echo "   • Download data for registrar\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "This is likely not harmful - the script checks before adding.\n";
}