<?php
require_once 'config.php';
$pdo = getDBConnection();

$filterCategory = 'supervising-officer';

echo "Filter: $filterCategory<br>";

// Debug the query
echo "<h3>Query for supervising-officer:</h3>";
$stmt = $pdo->query("SELECT * FROM evaluation_questions
    WHERE is_active = 1 AND (target_staff_category LIKE 'S.O%' OR target_staff_category = 'S.O')
    ORDER BY COALESCE(category_order, 99999), category, COALESCE(question_order, 99999), id");

$count = 0;
while ($row = $stmt->fetch()) {
    $count++;
    echo "ID: " . $row['id'] . ", Category: " . $row['category'] . ", Target: " . $row['target_staff_category'] . "<br>";
}

echo "<br>Total: $count questions found<br>";

echo "<h3>All questions in database:</h3>";
$stmt = $pdo->query("SELECT id, category, target_staff_category, is_active FROM evaluation_questions ORDER BY id");
while ($row = $stmt->fetch()) {
    echo "ID: " . $row['id'] . ", Target: '" . $row['target_staff_category'] . "', Active: " . $row['is_active'] . "<br>";
}
?>