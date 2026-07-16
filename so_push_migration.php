<?php
/**
 * Migration: Add columns for SO push to registrar feature
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Adding SO Push to Registrar columns...</h2>";

// Add so_push_to_registrar column
try {
    $pdo->exec("ALTER TABLE evaluations ADD COLUMN so_push_to_registrar TINYINT(1) DEFAULT 0");
    echo "✅ Added so_push_to_registrar column<br>";
} catch (Exception $e) {
    echo "ℹ️  so_push_to_registrar: " . $e->getMessage() . "<br>";
}

// Add so_push_reason column
try {
    $pdo->exec("ALTER TABLE evaluations ADD COLUMN so_push_reason TEXT DEFAULT NULL");
    echo "✅ Added so_push_reason column<br>";
} catch (Exception $e) {
    echo "ℹ️  so_push_reason: " . $e->getMessage() . "<br>";
}

// Verify
echo "<h3>Verifying:</h3>";
$stmt = $pdo->query("DESCRIBE evaluations");
while ($row = $stmt->fetch()) {
    if (in_array($row['Field'], ['so_push_to_registrar', 'so_push_reason'])) {
        echo "{$row['Field']}: {$row['Type']}<br>";
    }
}

echo "<h3>✅ Done! Feature ready.</h3>";
?>