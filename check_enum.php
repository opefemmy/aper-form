<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Current ENUM values:</h2>";
$stmt = $pdo->query("DESCRIBE evaluation_questions");
while ($row = $stmt->fetch()) {
    if ($row['Field'] === 'target_staff_category') {
        echo "Type: " . $row['Type'] . "<br>";
    }
}

echo "<h2>All questions in database:</h2>";
$stmt = $pdo->query("SELECT id, target_staff_category, question_text FROM evaluation_questions ORDER BY id");
while ($row = $stmt->fetch()) {
    echo "ID: {$row['id']}, Target: '{$row['target_staff_category']}', Text: " . substr($row['question_text'], 0, 50) . "...<br>";
}

echo "<h2>Unique target values:</h2>";
$stmt = $pdo->query("SELECT DISTINCT target_staff_category FROM evaluation_questions");
while ($row = $stmt->fetch()) {
    echo "'{$row['target_staff_category']}'<br>";
}
?>