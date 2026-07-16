<?php
/**
 * Complete fix: Change column to VARCHAR and update SO questions
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Step 1: Check current column type</h2>";
$stmt = $pdo->query("DESCRIBE evaluation_questions");
while ($row = $stmt->fetch()) {
    if ($row['Field'] === 'target_staff_category') {
        echo "Current Type: " . $row['Type'] . "<br>";
    }
}

echo "<h2>Step 2: Change column to VARCHAR</h2>";
try {
    $pdo->exec("ALTER TABLE evaluation_questions MODIFY COLUMN target_staff_category VARCHAR(50) DEFAULT 'both'");
    echo "✅ Success! Column is now VARCHAR<br>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

echo "<h2>Step 3: Update S.O_academic questions</h2>";
$academicQuestions = [
    'How would you rate the staff\'s teaching quality and effectiveness?',
    'Does the staff maintain good class management and student engagement?',
    'How would you rate the staff\'s research output and publications?',
    'Does the staff attend departmental meetings and fulfill administrative duties?',
    'How would you rate the staff\'s professional development and skills?',
];
$stmt = $pdo->prepare("UPDATE evaluation_questions SET target_staff_category = 'S.O_academic' WHERE question_text = ?");
foreach ($academicQuestions as $q) {
    $stmt->execute([$q]);
    echo "Updated: " . substr($q, 0, 40) . "...<br>";
}

echo "<h2>Step 4: Update S.O_senior questions</h2>";
$seniorQuestions = [
    'How would you rate the staff\'s overall job performance?',
    'Does the staff demonstrate efficiency in completing tasks?',
    'Does the staff maintain proper records and documentation?',
    'How would you rate the staff\'s punctuality and attendance?',
    'Does the staff demonstrate good teamwork and collaboration?',
];
$stmt = $pdo->prepare("UPDATE evaluation_questions SET target_staff_category = 'S.O_senior' WHERE question_text = ?");
foreach ($seniorQuestions as $q) {
    $stmt->execute([$q]);
    echo "Updated: " . substr($q, 0, 40) . "...<br>";
}

echo "<h2>Step 5: Update S.O_junior questions</h2>";
$juniorQuestions = [
    'How would you rate the staff\'s performance in their assigned duties?',
    'Does the staff complete tasks timely and efficiently?',
    'Does the staff maintain good attendance and punctuality?',
    'How would you rate the staff\'s willingness to learn and improve?',
    'Does the staff demonstrate good attitude and teamwork?',
];
$stmt = $pdo->prepare("UPDATE evaluation_questions SET target_staff_category = 'S.O_junior' WHERE question_text = ?");
foreach ($juniorQuestions as $q) {
    $stmt->execute([$q]);
    echo "Updated: " . substr($q, 0, 40) . "...<br>";
}

echo "<h2>Step 6: Update generic S.O questions</h2>";
$stmt = $pdo->prepare("UPDATE evaluation_questions SET target_staff_category = 'S.O' WHERE question_text = 'How would you rate the staff\'s overall contribution to the department?'");
$stmt->execute();
echo "Updated: How would you rate the staff's overall contribution...<br>";

echo "<h2>Step 7: Verify results</h2>";
$stmt = $pdo->query("SELECT target_staff_category, COUNT(*) as cnt FROM evaluation_questions GROUP BY target_staff_category ORDER BY target_staff_category");
while ($row = $stmt->fetch()) {
    echo "{$row['target_staff_category']}: {$row['cnt']} questions<br>";
}

echo "<h2>Step 8: Test the filters</h2>";
$stmt = $pdo->query("SELECT * FROM evaluation_questions WHERE is_active = 1 AND target_staff_category IN ('S.O', 'S.O_academic', 'S.O_senior', 'S.O_junior')");
$questions = $stmt->fetchAll();
echo "Supervising Officer questions found: " . count($questions) . "<br>";

echo "<h3>✅ Done! Please refresh questions.php?filter=supervising-officer</h3>";
?>