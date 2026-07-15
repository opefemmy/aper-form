<?php
/**
 * Migration to add category_order column to evaluation_questions
 */
require_once 'config.php';

$pdo = getDBConnection();

try {
    // Check if column already exists
    $stmt = $pdo->query("DESCRIBE evaluation_questions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('category_order', $columns)) {
        $pdo->exec("ALTER TABLE evaluation_questions ADD COLUMN category_order INT DEFAULT 0");
        echo "Added category_order column successfully!";
    } else {
        echo "category_order column already exists.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}