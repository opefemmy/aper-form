<?php
require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Fixing evaluator_type ENUM in database...</h2>";

try {
    // Check current ENUM values
    $stmt = $pdo->query("SHOW COLUMNS FROM staff LIKE 'evaluator_type'");
    $column = $stmt->fetch();
    echo "Current type: " . $column['Type'] . "<br><br>";

    // Drop and recreate the column with new ENUM values
    $pdo->exec("ALTER TABLE staff MODIFY COLUMN evaluator_type ENUM('Supervising Officer', 'Registrar', 'Dean', 'HOD', '') DEFAULT ''");

    echo "✅ Updated evaluator_type to include 'Supervising Officer'<br><br>";

    // Verify the update
    $stmt = $pdo->query("SHOW COLUMNS FROM staff LIKE 'evaluator_type'");
    $column = $stmt->fetch();
    echo "New type: " . $column['Type'] . "<br><br>";

    // Convert any HOD to Supervising Officer
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM staff WHERE evaluator_type = 'HOD'");
    $hodCount = $stmt->fetch()['cnt'];

    if ($hodCount > 0) {
        $pdo->exec("UPDATE staff SET evaluator_type = 'Supervising Officer' WHERE evaluator_type = 'HOD'");
        echo "✅ Converted $hodCount HOD(s) to Supervising Officer<br><br>";
    }

    // Show current evaluators
    $stmt = $pdo->query("SELECT id, designation, department, evaluator_type, status FROM staff WHERE evaluator_type IN ('Supervising Officer', 'Registrar')");
    $evaluators = $stmt->fetchAll();

    echo "<h3>Current Evaluators:</h3>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Designation</th><th>Department</th><th>Type</th><th>Status</th></tr>";
    foreach ($evaluators as $e) {
        echo "<tr><td>{$e['id']}</td><td>{$e['designation']}</td><td>{$e['department']}</td><td>{$e['evaluator_type']}</td><td>{$e['status']}</td></tr>";
    }
    echo "</table>";

    echo "<br><br><a href='manage-evaluators.php'>Go to Manage Evaluators</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>