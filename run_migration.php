<?php
/**
 * Run Database Migration
 * This script runs the database changes for the new workflow
 */

require_once 'config.php';

echo "=== Running Database Migration ===\n\n";

$pdo = getDBConnection();

try {
    // 1. Update staff table to include 'non-teaching-junior' in staff_category ENUM
    echo "1. Updating staff_category ENUM in staff table...\n";
    $pdo->exec("ALTER TABLE staff MODIFY COLUMN staff_category ENUM('academic', 'non-teaching', 'non-teaching-junior', 'hod') DEFAULT 'academic'");
    echo "   ✅ Done\n";

    // 2. Update evaluations table to include 'non-teaching-junior' in staff_category ENUM
    echo "2. Updating staff_category ENUM in evaluations table...\n";
    $pdo->exec("ALTER TABLE evaluations MODIFY COLUMN staff_category ENUM('academic', 'non-teaching', 'non-teaching-junior', 'hod') DEFAULT 'academic'");
    echo "   ✅ Done\n";

    // 3. Update evaluation_questions table to include 'non-teaching-junior' in target_staff_category ENUM
    echo "3. Updating target_staff_category ENUM in evaluation_questions table...\n";
    $pdo->exec("ALTER TABLE evaluation_questions MODIFY COLUMN target_staff_category ENUM('academic', 'non-teaching', 'non-teaching-junior', 'hod', 'both') DEFAULT 'both'");
    echo "   ✅ Done\n";

    // 4. Update evaluator_type ENUM in staff table to change HOD to Supervising Officer and remove Dean
    echo "4. Updating evaluator_type ENUM in staff table...\n";
    $pdo->exec("ALTER TABLE staff MODIFY COLUMN evaluator_type ENUM('Supervising Officer', 'Registrar', '') DEFAULT ''");
    echo "   ✅ Done\n";

    // 5. Add staff_consent column
    echo "5. Adding staff_consent column to evaluations table...\n";
    try {
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN staff_consent VARCHAR(20) DEFAULT NULL");
        echo "   ✅ Added\n";
    } catch (Exception $e) {
        echo "   ℹ️  Column already exists or error: " . $e->getMessage() . "\n";
    }

    // 6. Add staff_rejection_reason column
    echo "6. Adding staff_rejection_reason column to evaluations table...\n";
    try {
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN staff_rejection_reason TEXT DEFAULT NULL");
        echo "   ✅ Added\n";
    } catch (Exception $e) {
        echo "   ℹ️  Column already exists or error: " . $e->getMessage() . "\n";
    }

    // 7. Add supervising_officer_final_comments column
    echo "7. Adding supervising_officer_final_comments column to evaluations table...\n";
    try {
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN supervising_officer_final_comments TEXT DEFAULT NULL");
        echo "   ✅ Added\n";
    } catch (Exception $e) {
        echo "   ℹ️  Column already exists or error: " . $e->getMessage() . "\n";
    }

    // 8. Update any existing HOD evaluators to Supervising Officer
    echo "8. Updating existing HOD evaluators to Supervising Officer...\n";
    $stmt = $pdo->prepare("UPDATE staff SET evaluator_type = 'Supervising Officer' WHERE evaluator_type = 'HOD'");
    $stmt->execute();
    $affected = $stmt->rowCount();
    echo "   ✅ Updated $affected evaluators\n";

    // 9. Update any existing evaluations with 'hod' stage to 'supervising_officer'
    echo "9. Updating existing evaluation stages...\n";
    $stmt = $pdo->prepare("UPDATE evaluations SET evaluation_stage = 'supervising_officer' WHERE evaluation_stage = 'hod'");
    $stmt->execute();
    $affected = $stmt->rowCount();
    echo "   ✅ Updated $affected evaluation stages (hod -> supervising_officer)\n";

    $stmt = $pdo->prepare("UPDATE evaluations SET evaluation_stage = 'supervising_officer_final' WHERE evaluation_stage = 'dean'");
    $stmt->execute();
    $affected = $stmt->rowCount();
    echo "   ✅ Updated $affected evaluation stages (dean -> supervising_officer_final)\n";

    echo "\n=== Migration Completed Successfully! ===\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}