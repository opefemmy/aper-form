<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$pdo = getDBConnection();

$filterCategory = $_GET['filter'] ?? 'supervising-officer';

echo "<h2>Testing filter: $filterCategory</h2>";

// Test different filter values
$tests = [
    'supervising-officer' => "SELECT * FROM evaluation_questions WHERE is_active = 1 AND target_staff_category IN ('S.O', 'S.O_academic', 'S.O_senior', 'S.O_junior')",
    'S.O_junior' => "SELECT * FROM evaluation_questions WHERE is_active = 1 AND target_staff_category IN ('S.O_junior', 'S.O')",
    'S.O_senior' => "SELECT * FROM evaluation_questions WHERE is_active = 1 AND target_staff_category IN ('S.O_senior', 'S.O')",
    'S.O_academic' => "SELECT * FROM evaluation_questions WHERE is_active = 1 AND target_staff_category IN ('S.O_academic', 'S.O')",
    'S.O' => "SELECT * FROM evaluation_questions WHERE is_active = 1 AND target_staff_category IN ('S.O', 'S.O_academic', 'S.O_senior', 'S.O_junior')",
];

foreach ($tests as $name => $sql) {
    echo "<h3>Filter: $name</h3>";
    try {
        $stmt = $pdo->query($sql);
        $questions = $stmt->fetchAll();
        echo "Found: " . count($questions) . " questions<br>";
        foreach ($questions as $q) {
            echo "- Target: '{$q['target_staff_category']}', Text: " . substr($q['question_text'], 0, 50) . "...<br>";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }
    echo "<br>";
}
?>