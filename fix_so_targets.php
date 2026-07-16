<?php
/**
 * Fix: Update existing questions to have correct S.O targets
 * This updates questions that look like SO questions to have the right target_staff_category
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Fixing SO question targets...</h2>";

// Questions that should be S.O_academic (for Academic Staff evaluation by SO)
$academicQuestions = [
    'How would you rate the staff\'s teaching quality and effectiveness?',
    'Does the staff maintain good class management and student engagement?',
    'How would you rate the staff\'s research output and publications?',
    'Does the staff attend departmental meetings and fulfill administrative duties?',
    'How would you rate the staff\'s professional development and skills?',
];

// Questions that should be S.O_senior (for Non-Teaching Senior evaluation by SO)
$seniorQuestions = [
    'How would you rate the staff\'s overall job performance?',
    'Does the staff demonstrate efficiency in completing tasks?',
    'Does the staff maintain proper records and documentation?',
    'How would you rate the staff\'s punctuality and attendance?',
    'Does the staff demonstrate good teamwork and collaboration?',
];

// Questions that should be S.O_junior (for Junior Staff evaluation by SO)
$juniorQuestions = [
    'How would you rate the staff\'s performance in their assigned duties?',
    'Does the staff complete tasks timely and efficiently?',
    'Does the staff maintain good attendance and punctuality?',
    'How would you rate the staff\'s willingness to learn and improve?',
    'Does the staff demonstrate good attitude and teamwork?',
];

// Questions that should be generic S.O (for all staff evaluation by SO)
$genericQuestions = [
    'How would you rate the staff\'s overall contribution to the department?',
];

// Update academic questions
$count = 0;
foreach ($academicQuestions as $q) {
    $stmt = $pdo->prepare("UPDATE evaluation_questions SET target_staff_category = 'S.O_academic' WHERE question_text = ? AND target_staff_category = 'both'");
    $stmt->execute([$q]);
    $count += $stmt->rowCount();
}
echo "Updated $count S.O_academic questions<br>";

// Update senior questions
$count = 0;
foreach ($seniorQuestions as $q) {
    $stmt = $pdo->prepare("UPDATE evaluation_questions SET target_staff_category = 'S.O_senior' WHERE question_text = ? AND target_staff_category = 'both'");
    $stmt->execute([$q]);
    $count += $stmt->rowCount();
}
echo "Updated $count S.O_senior questions<br>";

// Update junior questions
$count = 0;
foreach ($juniorQuestions as $q) {
    $stmt = $pdo->prepare("UPDATE evaluation_questions SET target_staff_category = 'S.O_junior' WHERE question_text = ? AND target_staff_category = 'both'");
    $stmt->execute([$q]);
    $count += $stmt->rowCount();
}
echo "Updated $count S.O_junior questions<br>";

// Update generic questions
$count = 0;
foreach ($genericQuestions as $q) {
    $stmt = $pdo->prepare("UPDATE evaluation_questions SET target_staff_category = 'S.O' WHERE question_text = ? AND target_staff_category = 'both'");
    $stmt->execute([$q]);
    $count += $stmt->rowCount();
}
echo "Updated $count S.O questions<br>";

// Verify
echo "<h3>Verifying:</h3>";
$stmt = $pdo->query("SELECT target_staff_category, COUNT(*) as cnt FROM evaluation_questions GROUP BY target_staff_category ORDER BY target_staff_category");
while ($row = $stmt->fetch()) {
    echo "{$row['target_staff_category']}: {$row['cnt']} questions<br>";
}

echo "<h3>Done! Please refresh questions.php?filter=supervising-officer</h3>";
?>