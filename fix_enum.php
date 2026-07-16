<?php
/**
 * Fix ENUM for target_staff_category to include S.O values
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Fixing ENUM values...</h2>";

// First check current ENUM
echo "<h3>Current column structure:</h3>";
$stmt = $pdo->query("DESCRIBE evaluation_questions");
while ($row = $stmt->fetch()) {
    if ($row['Field'] === 'target_staff_category') {
        echo "Current Type: " . $row['Type'] . "<br>";
    }
}

// Try to modify the ENUM - different MariaDB/MySQL versions have different syntax
$enumValues = array('academic', 'non-teaching', 'non-teaching-junior', 'S.O', 'S.O_academic', 'S.O_senior', 'S.O_junior', 'both');
$enumStr = implode("','", $enumValues);

echo "<h3>Attempting to modify ENUM...</h3>";

try {
    // Try MySQL 8.0+ / MariaDB syntax
    $sql = "ALTER TABLE evaluation_questions MODIFY COLUMN target_staff_category ENUM('$enumStr') DEFAULT 'both'";
    $pdo->exec($sql);
    echo "✅ Success! ENUM updated.<br>";
} catch (Exception $e) {
    echo "❌ First attempt failed: " . $e->getMessage() . "<br>";

    // Try alternative syntax
    try {
        $sql = "ALTER TABLE evaluation_questions CHANGE target_staff_category target_staff_category ENUM('$enumStr') DEFAULT 'both'";
        $pdo->exec($sql);
        echo "✅ Alternative syntax worked! ENUM updated.<br>";
    } catch (Exception $e2) {
        echo "❌ Alternative also failed: " . $e2->getMessage() . "<br>";
    }
}

// Verify
echo "<h3>After modification:</h3>";
$stmt = $pdo->query("DESCRIBE evaluation_questions");
while ($row = $stmt->fetch()) {
    if ($row['Field'] === 'target_staff_category') {
        echo "New Type: " . $row['Type'] . "<br>";
    }
}
?>