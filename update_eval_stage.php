<?php
/**
 * Quick fix to add staff_review and supervising_officer_reject to evaluation_stage ENUM
 * Run this once on the live server: https://personel.ink/update_eval_stage.php
 */
require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Updating evaluation_stage ENUM...</h2>";

try {
    // Check current ENUM
    $stmt = $pdo->query("SHOW COLUMNS FROM evaluations LIKE 'evaluation_stage'");
    $column = $stmt->fetch();
    $currentType = $column['Type'];
    echo "Current ENUM: $currentType<br><br>";

    if (strpos($currentType, 'staff_review') === false) {
        $pdo->exec("ALTER TABLE evaluations MODIFY COLUMN evaluation_stage ENUM('pending', 'supervising_officer', 'staff_review', 'supervising_officer_reject', 'registrar', 'completed') DEFAULT 'pending'");
        echo "✅ Added 'staff_review' to ENUM<br>";
    } else {
        echo "ℹ️ 'staff_review' already exists in ENUM<br>";
    }

    if (strpos($currentType, 'supervising_officer_reject') === false) {
        $pdo->exec("ALTER TABLE evaluations MODIFY COLUMN evaluation_stage ENUM('pending', 'supervising_officer', 'staff_review', 'supervising_officer_reject', 'registrar', 'completed') DEFAULT 'pending'");
        echo "✅ Added 'supervising_officer_reject' to ENUM<br>";
    } else {
        echo "ℹ️ 'supervising_officer_reject' already exists in ENUM<br>";
    }

    // Verify the update
    $stmt = $pdo->query("SHOW COLUMNS FROM evaluations LIKE 'evaluation_stage'");
    $column = $stmt->fetch();
    echo "<br>Updated ENUM: " . $column['Type'] . "<br>";

    echo "<br><strong>Done!</strong>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>