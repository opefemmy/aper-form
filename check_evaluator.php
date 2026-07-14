<?php
require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Checking Evaluators in Database</h2>";

// Get all evaluators
$stmt = $pdo->query("SELECT id, staff_id, designation, department, faculty, evaluator_type, password, status, email FROM staff WHERE evaluator_type IN ('Supervising Officer', 'Registrar', 'HOD') ORDER BY evaluator_type, department");
$evaluators = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Found " . count($evaluators) . " evaluator(s):</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Staff ID</th><th>Designation</th><th>Dept</th><th>Faculty</th><th>Type</th><th>Status</th><th>Has Password</th><th>Email</th></tr>";

foreach ($evaluators as $eval) {
    echo "<tr>";
    echo "<td>" . $eval['id'] . "</td>";
    echo "<td>" . htmlspecialchars($eval['staff_id'] ?? 'NULL') . "</td>";
    echo "<td><strong>" . htmlspecialchars($eval['designation'] ?? 'NULL') . "</strong></td>";
    echo "<td>" . htmlspecialchars($eval['department'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($eval['faculty'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($eval['evaluator_type'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($eval['status'] ?? 'NULL') . "</td>";
    echo "<td>" . (!empty($eval['password']) ? '✅ Yes' : '❌ NO') . "</td>";
    echo "<td>" . htmlspecialchars($eval['email'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><br><h3>Database Table Structure:</h3>";
$stmt = $pdo->query("DESCRIBE staff");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Columns: " . implode(", ", $columns);

echo "<br><br><a href='manage-evaluators.php'>Go to Manage Evaluators</a>";
?>