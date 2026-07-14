<?php
require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Adding staff consent columns to evaluations table...</h2>";

try {
    // Check if columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM evaluations LIKE 'staff_consent'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN staff_consent ENUM('consented', 'rejected', '') DEFAULT ''");
        echo "✅ Added staff_consent column<br>";
    } else {
        echo "ℹ️ staff_consent column already exists<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM evaluations LIKE 'staff_rejection_reason'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN staff_rejection_reason TEXT");
        echo "✅ Added staff_rejection_reason column<br>";
    } else {
        echo "ℹ️ staff_rejection_reason column already exists<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM evaluations LIKE 'staff_consent_date'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN staff_consent_date DATETIME DEFAULT NULL");
        echo "✅ Added staff_consent_date column<br>";
    } else {
        echo "ℹ️ staff_consent_date column already exists<br>";
    }

    // Check and add supervising_officer_reject stage to ENUM
    $stmt = $pdo->query("SHOW COLUMNS FROM evaluations LIKE 'evaluation_stage'");
    $column = $stmt->fetch();
    echo "<br>Current ENUM: " . $column['Type'] . "<br>";

    if (strpos($column['Type'], 'supervising_officer_reject') === false) {
        $pdo->exec("ALTER TABLE evaluations MODIFY COLUMN evaluation_stage ENUM('pending', 'supervising_officer', 'staff_review', 'supervising_officer_reject', 'registrar', 'completed') DEFAULT 'pending'");
        echo "✅ Added supervising_officer_reject to ENUM<br>";
    }

    if (strpos($column['Type'], 'staff_review') === false) {
        $pdo->exec("ALTER TABLE evaluations MODIFY COLUMN evaluation_stage ENUM('pending', 'supervising_officer', 'staff_review', 'supervising_officer_reject', 'registrar', 'completed') DEFAULT 'pending'");
        echo "✅ Added staff_review to ENUM<br>";
    }

    echo "<br><strong>Done!</strong>";
    echo "<br><br><a href='staff-review.php'>Go to Staff Review</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>