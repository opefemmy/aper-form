<?php
/**
 * Migration script to rename hod categories to S.O (Supervising Officer)
 *
 * Changes:
 * - hod_junior → S.O_junior
 * - hod_senior → S.O_senior
 * - hod_academic → S.O_academic
 * - hod → S.O
 */

require_once 'config.php';

$pdo = getDBConnection();

echo "Starting HOD to S.O rename migration...\n";

try {
    // 1. First, modify the ENUM column to include new values
    echo "Step 1: Updating ENUM definition...\n";

    // Check current enum values
    $stmt = $pdo->query("DESCRIBE evaluation_questions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        if ($col['Field'] === 'target_staff_category') {
            echo "Current type: " . $col['Type'] . "\n";
        }
    }

    // Modify enum to include both old and new values temporarily
    $pdo->exec("ALTER TABLE evaluation_questions MODIFY COLUMN target_staff_category
        ENUM('academic', 'non-teaching', 'non-teaching-junior', 'hod', 'hod_junior', 'hod_senior', 'hod_academic', 'S.O', 'S.O_junior', 'S.O_senior', 'S.O_academic', 'both')
        DEFAULT 'both'");

    echo "Step 2: Updating existing data...\n";

    // Update the category values
    $pdo->exec("UPDATE evaluation_questions SET target_staff_category = 'S.O_junior' WHERE target_staff_category = 'hod_junior'");
    $pdo->exec("UPDATE evaluation_questions SET target_staff_category = 'S.O_senior' WHERE target_staff_category = 'hod_senior'");
    $pdo->exec("UPDATE evaluation_questions SET target_staff_category = 'S.O_academic' WHERE target_staff_category = 'hod_academic'");
    $pdo->exec("UPDATE evaluation_questions SET target_staff_category = 'S.O' WHERE target_staff_category = 'hod'");

    echo "Updated evaluation_questions table.\n";

    // Verify the updates
    $stmt = $pdo->query("SELECT target_staff_category, COUNT(*) as cnt FROM evaluation_questions GROUP BY target_staff_category");
    echo "\nCurrent distribution:\n";
    while ($row = $stmt->fetch()) {
        echo "  {$row['target_staff_category']}: {$row['cnt']}\n";
    }

    echo "\nMigration completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}