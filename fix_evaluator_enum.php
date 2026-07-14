<?php
require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Cleaning up evaluator_type - Removing Dean and HOD...</h2>";

try {
    // Check current ENUM values
    $stmt = $pdo->query("SHOW COLUMNS FROM staff LIKE 'evaluator_type'");
    $column = $stmt->fetch();
    echo "Current type: " . $column['Type'] . "<br><br>";

    // First convert any Dean or HOD to Supervising Officer
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM staff WHERE evaluator_type IN ('Dean', 'HOD')");
    $countToConvert = $stmt->fetch()['cnt'];

    if ($countToConvert > 0) {
        $pdo->exec("UPDATE staff SET evaluator_type = 'Supervising Officer' WHERE evaluator_type IN ('Dean', 'HOD')");
        echo "✅ Converted $countToConvert Dean/HOD to Supervising Officer<br><br>";
    }

    // Now update the ENUM to only have Supervising Officer and Registrar
    $pdo->exec("ALTER TABLE staff MODIFY COLUMN evaluator_type ENUM('Supervising Officer', 'Registrar', '') DEFAULT ''");

    echo "✅ Removed Dean and HOD from allowed values<br><br>";

    // Verify the update
    $stmt = $pdo->query("SHOW COLUMNS FROM staff LIKE 'evaluator_type'");
    $column = $stmt->fetch();
    echo "New type: " . $column['Type'] . "<br><br>";

    // Show current evaluators
    $stmt = $pdo->query("SELECT id, designation, department, evaluator_type, status FROM staff WHERE evaluator_type IN ('Supervising Officer', 'Registrar')");
    $evaluators = $stmt->fetchAll();

    echo "<h3>Current Evaluators (only Supervising Officer & Registrar):</h3>";
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