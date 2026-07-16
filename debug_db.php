<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

try {
    $pdo = getDBConnection();
    echo "Database connected successfully!<br><br>";

    // Check table structure
    echo "<h2>Table Structure:</h2>";
    $stmt = $pdo->query("DESCRIBE evaluation_questions");
    while ($row = $stmt->fetch()) {
        if ($row['Field'] === 'target_staff_category') {
            echo "Field: {$row['Field']}, Type: {$row['Type']}<br>";
        }
    }

    // Show ALL questions
    echo "<h2>All questions:</h2>";
    $stmt = $pdo->query("SELECT id, question_text, target_staff_category, is_active FROM evaluation_questions ORDER BY id");
    $count = 0;
    while ($row = $stmt->fetch()) {
        $count++;
        echo "ID: {$row['id']}, Active: {$row['is_active']}, Target: '{$row['target_staff_category']}', Text: " . substr($row['question_text'], 0, 60) . "...<br>";
    }
    echo "<br>Total: $count questions<br>";

    // Test the exact query used in questions.php
    echo "<h2>Testing the filter query:</h2>";
    $stmt = $pdo->query("SELECT * FROM evaluation_questions WHERE is_active = 1 AND target_staff_category IN ('S.O', 'S.O_academic', 'S.O_senior', 'S.O_junior')");
    $soCount = 0;
    while ($row = $stmt->fetch()) {
        $soCount++;
        echo "ID: {$row['id']}, Target: '{$row['target_staff_category']}', Text: " . substr($row['question_text'], 0, 60) . "...<br>";
    }
    echo "<br>SO Questions found: $soCount<br>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>