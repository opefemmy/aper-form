<?php
require_once 'config.php';

$pdo = getDBConnection();
$evalId = $_GET['eval_id'] ?? 55;

// Get evaluation and staff info
$stmt = $pdo->prepare("SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level, s.staff_category
    FROM evaluations e
    JOIN staff s ON e.staff_id = s.id
    WHERE e.id = ?");
$stmt->execute([$evalId]);
$eval = $stmt->fetch();

echo "<h1>Debug Evaluation ID: $evalId</h1>";
echo "<pre>";
print_r($eval);
echo "</pre>";

if ($eval) {
    $staffCat = $eval['staff_category'] ?: 'academic';
    echo "<h2>Staff Category: $staffCat</h2>";

    // Determine which question category to use
    $soCat = '';
    if ($staffCat === 'non-teaching-junior') {
        $soCat = 'S.O_junior';
    } elseif ($staffCat === 'non-teaching') {
        $soCat = 'S.O_senior';
    } else {
        $soCat = 'S.O_academic';
    }
    echo "<h2>Looking for SO questions with category: $soCat</h2>";

    // Get questions for this category
    $stmt = $pdo->prepare("SELECT * FROM evaluation_questions
        WHERE is_active = 1
        AND (
            target_staff_category = ?
            OR target_staff_category = 'S.O'
        )
        ORDER BY COALESCE(question_order, 99999), category, id");
    $stmt->execute([$soCat]);
    $questions = $stmt->fetchAll();

    echo "<h3>Questions found: " . count($questions) . "</h3>";
    echo "<pre>";
    print_r($questions);
    echo "</pre>";

    // Also show all SO questions in database
    echo "<h3>All SO questions in database:</h3>";
    $stmt = $pdo->query("SELECT id, question_text, target_staff_category, category FROM evaluation_questions
        WHERE target_staff_category LIKE 'S.O%' OR target_staff_category = 'S.O'
        ORDER BY target_staff_category, category, id");
    $allSOQuestions = $stmt->fetchAll();
    echo "<pre>";
    print_r($allSOQuestions);
    echo "</pre>";
}
?>