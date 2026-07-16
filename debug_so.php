<?php
require_once 'config.php';
$pdo = getDBConnection();

// Check what questions exist
$stmt = $pdo->query("SELECT DISTINCT target_staff_category FROM evaluation_questions ORDER BY target_staff_category");
echo "<h2>All target_staff_category values:</h2>";
while ($row = $stmt->fetch()) {
    echo $row['target_staff_category'] . "<br>";
}

// Count questions by category
$stmt = $pdo->query("SELECT target_staff_category, COUNT(*) as cnt FROM evaluation_questions GROUP BY target_staff_category ORDER BY cnt DESC");
echo "<h2>Questions by category:</h2>";
while ($row = $stmt->fetch()) {
    echo $row['target_staff_category'] . ": " . $row['cnt'] . "<br>";
}

// Check active questions
$stmt = $pdo->query("SELECT target_staff_category, COUNT(*) as cnt FROM evaluation_questions WHERE is_active = 1 GROUP BY target_staff_category ORDER BY cnt DESC");
echo "<h2>Active questions by category:</h2>";
while ($row = $stmt->fetch()) {
    echo $row['target_staff_category'] . ": " . $row['cnt'] . "<br>";
}

// Check if any SO questions exist
$stmt = $pdo->query("SELECT id, question_text, target_staff_category, is_active FROM evaluation_questions WHERE target_staff_category LIKE 'S.O%' OR target_staff_category = 'S.O'");
echo "<h2>SO Questions:</h2>";
while ($row = $stmt->fetch()) {
    echo "ID: " . $row['id'] . ", Active: " . $row['is_active'] . ", Category: " . $row['target_staff_category'] . "<br>";
}
?>