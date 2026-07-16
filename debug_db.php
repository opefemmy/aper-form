<?php
require_once 'config.php';
$pdo = getDBConnection();

echo "<h2>Debug: All questions in database</h2>";

// Show ALL questions (no filter)
$stmt = $pdo->query("SELECT id, question_text, target_staff_category, is_active FROM evaluation_questions ORDER BY id");
$count = 0;
while ($row = $stmt->fetch()) {
    $count++;
    echo "ID: {$row['id']}, Active: {$row['is_active']}, Target: {$row['target_staff_category']}, Text: " . substr($row['question_text'], 0, 50) . "...<br>";
}
echo "<br>Total questions: $count<br>";

echo "<h2>Debug: Questions with S.O* target</h2>";
$stmt = $pdo->query("SELECT id, question_text, target_staff_category, is_active FROM evaluation_questions WHERE target_staff_category IN ('S.O', 'S.O_academic', 'S.O_senior', 'S.O_junior')");
$soCount = 0;
while ($row = $stmt->fetch()) {
    $soCount++;
    echo "ID: {$row['id']}, Active: {$row['is_active']}, Target: {$row['target_staff_category']}, Text: " . substr($row['question_text'], 0, 50) . "...<br>";
}
echo "<br>Total SO questions: $soCount<br>";

echo "<h2>Debug: Check if questions exist at all</h2>";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM evaluation_questions");
$row = $stmt->fetch();
echo "Total in table: " . $row['total'] . "<br>";

$stmt = $pdo->query("SELECT COUNT(*) as active FROM evaluation_questions WHERE is_active = 1");
$row = $stmt->fetch();
echo "Active questions: " . $row['active'] . "<br>";
?>