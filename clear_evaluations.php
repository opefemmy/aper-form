<?php
require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Reset Evaluation Data (Keep Uploaded Staff)</h2>";

try {
    // Count current records
    $stmt = $pdo->query("SELECT COUNT(*) FROM evaluations");
    $evalCount = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM staff WHERE staff_id NOT LIKE 'DEPT-%' AND staff_id NOT LIKE 'FAC-%' AND staff_id NOT LIKE 'EVAL-%'");
    $staffCount = $stmt->fetchColumn();

    echo "<p>Current records:</p>";
    echo "<ul>";
    echo "<li>Evaluations: <strong>$evalCount</strong></li>";
    echo "<li>Staff (excluding departments/faculties/evaluators): <strong>$staffCount</strong></li>";
    echo "</ul>";

    if ($evalCount > 0) {
        // Delete all evaluations
        $pdo->exec("DELETE FROM evaluations");
        echo "<p class='text-success'>✅ Deleted $evalCount evaluation records</p>";
    } else {
        echo "<p>No evaluations to delete</p>";
    }

    // Show remaining staff
    $stmt = $pdo->query("SELECT COUNT(*) FROM staff WHERE staff_id NOT LIKE 'DEPT-%' AND staff_id NOT LIKE 'FAC-%' AND staff_id NOT LIKE 'EVAL-%'");
    $newStaffCount = $stmt->fetchColumn();

    echo "<h3>After reset:</h3>";
    echo "<ul>";
    echo "<li>Staff records kept: <strong>$newStaffCount</strong></li>";
    echo "<li>Evaluations: <strong>0</strong></li>";
    echo "</ul>";

    echo "<br><br><a href='dashboard.php' class='btn btn-primary'>Go to Dashboard</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>