<?php
/**
 * Fix: Change target_staff_category from ENUM to VARCHAR
 * This allows any value without needing to modify ENUM
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Changing target_staff_category to VARCHAR...</h2>";

try {
    // Change ENUM to VARCHAR
    $pdo->exec("ALTER TABLE evaluation_questions MODIFY COLUMN target_staff_category VARCHAR(50) DEFAULT 'both'");
    echo "✅ Success! Column changed to VARCHAR.<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Verify
echo "<h3>New column structure:</h3>";
$stmt = $pdo->query("DESCRIBE evaluation_questions");
while ($row = $stmt->fetch()) {
    if ($row['Field'] === 'target_staff_category') {
        echo "Field: {$row['Field']}, Type: {$row['Type']}<br>";
    }
}

echo "<h3>Testing query...</h3>";
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM evaluation_questions WHERE target_staff_category IN ('S.O', 'S.O_academic', 'S.O_senior', 'S.O_junior')");
$row = $stmt->fetch();
echo "SO questions found: " . $row['cnt'] . "<br>";

echo "<h3>Done! Please refresh questions.php</h3>";
?>