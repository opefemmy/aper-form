<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>All questions in database:</h2>";

$stmt = $pdo->query("SELECT id, target_staff_category, is_active, question_text FROM evaluation_questions ORDER BY id");
$count = 0;
while ($row = $stmt->fetch()) {
    $count++;
    echo "[$count] ID: {$row['id']}, Target: '{$row['target_staff_category']}', Active: {$row['is_active']}<br>";
    echo "    Text: {$row['question_text']}<br><br>";
}

echo "<h2>Questions grouped by target:</h2>";
$stmt = $pdo->query("SELECT target_staff_category, COUNT(*) as cnt FROM evaluation_questions GROUP BY target_staff_category");
while ($row = $stmt->fetch()) {
    echo "{$row['target_staff_category']}: {$row['cnt']} questions<br>";
}
?>