<?php
require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Promoting Staff to Supervising Officer</h2>";

// Find the staff member
$searchName = 'Olusegun Opemipo';
$stmt = $pdo->prepare("SELECT id, staff_id, surname, first_name, department, faculty, evaluator_type FROM staff WHERE (surname LIKE ? OR first_name LIKE ?) AND staff_id NOT LIKE 'DEPT-%' AND staff_id NOT LIKE 'FAC-%' LIMIT 1");
$stmt->execute(["%$searchName%", "%$searchName%"]);
$staff = $stmt->fetch();

if (!$staff) {
    echo "<p>Staff member '$searchName' not found!</p>";

    // Show available staff
    echo "<h3>Available Staff to Promote:</h3>";
    $stmt = $pdo->query("SELECT id, staff_id, surname, first_name, department, evaluator_type FROM staff WHERE (evaluator_type = '' OR evaluator_type IS NULL OR evaluator_type = 'HOD') AND staff_id NOT LIKE 'DEPT-%' AND staff_id NOT LIKE 'FAC-%' ORDER BY surname, first_name LIMIT 20");
    $staffList = $stmt->fetchAll();
    echo "<ul>";
    foreach ($staffList as $s) {
        echo "<li>{$s['surname']} {$s['first_name']} - {$s['staff_id']} ({$s['department']}) - Current Type: " . ($s['evaluator_type'] ?: 'None') . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Found: <strong>{$staff['surname']} {$staff['first_name']}</strong> ({$staff['staff_id']})</p>";
    echo "<p>Department: {$staff['department']}</p>";
    echo "<p>Faculty: {$staff['faculty']}</p>";
    echo "<p>Current Evaluator Type: " . ($staff['evaluator_type'] ?: 'None') . "</p>";

    if (!empty($staff['evaluator_type'])) {
        echo "<p class='text-warning'>This staff is already an evaluator!</p>";
    } else {
        // Set password and promote
        $password = 'password123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Use staff_id as designation
        $designation = $staff['staff_id'];

        $stmt = $pdo->prepare("UPDATE staff SET evaluator_type = 'Supervising Officer', designation = ?, password = ?, status = 'active' WHERE id = ?");
        $stmt->execute([$designation, $hashedPassword, $staff['id']]);

        echo "<p class='text-success'>✅ Successfully promoted to Supervising Officer!</p>";
        echo "<p><strong>Designation (Username):</strong> {$designation}</p>";
        echo "<p><strong>Password:</strong> {$password}</p>";
        echo "<p>You can now login at <a href='unified-login.php'>unified-login.php</a></p>";
    }
}

echo "<br><br><a href='manage-evaluators.php'>Go to Manage Evaluators</a>";
?>