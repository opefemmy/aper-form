<?php
/**
 * Force add Supervising Officer questions - ignores existing checks
 */
require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Adding SO Questions...</h2>";

$added = 0;

// Academic Staff questions (S.O_academic)
$academicQuestions = [
    ['Teaching', 'How would you rate the staff\'s teaching quality and effectiveness?', 1],
    ['Teaching', 'Does the staff maintain good class management and student engagement?', 2],
    ['Research', 'How would you rate the staff\'s research output and publications?', 3],
    ['Administrative', 'Does the staff attend departmental meetings and fulfill administrative duties?', 4],
    ['Professional', 'How would you rate the staff\'s professional development and skills?', 5],
];

// Non-Teaching Senior questions (S.O_senior)
$seniorQuestions = [
    ['Job Performance', 'How would you rate the staff\'s overall job performance?', 1],
    ['Job Performance', 'Does the staff demonstrate efficiency in completing tasks?', 2],
    ['Administrative', 'Does the staff maintain proper records and documentation?', 3],
    ['Professional', 'How would you rate the staff\'s punctuality and attendance?', 4],
    ['Professional', 'Does the staff demonstrate good teamwork and collaboration?', 5],
];

// Junior Staff questions (S.O_junior)
$juniorQuestions = [
    ['Job Performance', 'How would you rate the staff\'s performance in their assigned duties?', 1],
    ['Job Performance', 'Does the staff complete tasks timely and efficiently?', 2],
    ['Administrative', 'Does the staff maintain good attendance and punctuality?', 3],
    ['Professional', 'How would you rate the staff\'s willingness to learn and improve?', 4],
    ['Professional', 'Does the staff demonstrate good attitude and teamwork?', 5],
];

// Add Academic Staff questions
foreach ($academicQuestions as $q) {
    $stmt = $pdo->prepare("INSERT INTO evaluation_questions (category, question_text, question_order, question_type, target_staff_category, is_active) VALUES (?, ?, ?, 'rating', 'S.O_academic', 1)");
    $stmt->execute([$q[0], $q[1], $q[2]]);
    $added++;
    echo "Added: {$q[1]} (S.O_academic)<br>";
}

// Add Senior Staff questions
foreach ($seniorQuestions as $q) {
    $stmt = $pdo->prepare("INSERT INTO evaluation_questions (category, question_text, question_order, question_type, target_staff_category, is_active) VALUES (?, ?, ?, 'rating', 'S.O_senior', 1)");
    $stmt->execute([$q[0], $q[1], $q[2]]);
    $added++;
    echo "Added: {$q[1]} (S.O_senior)<br>";
}

// Add Junior Staff questions
foreach ($juniorQuestions as $q) {
    $stmt = $pdo->prepare("INSERT INTO evaluation_questions (category, question_text, question_order, question_type, target_staff_category, is_active) VALUES (?, ?, ?, 'rating', 'S.O_junior', 1)");
    $stmt->execute([$q[0], $q[1], $q[2]]);
    $added++;
    echo "Added: {$q[1]} (S.O_junior)<br>";
}

// Add generic S.O question
$stmt = $pdo->prepare("INSERT INTO evaluation_questions (category, question_text, question_order, question_type, target_staff_category, is_active) VALUES (?, ?, ?, 'rating', 'S.O', 1)");
$stmt->execute(['General', 'How would you rate the staff\'s overall contribution to the department?', 1]);
$added++;
echo "Added: How would you rate the staff's overall contribution to the department? (S.O)<br>";

echo "<h3>Total questions added: $added</h3>";

// Now show what's in the database
echo "<h3>Current SO questions in database:</h3>";
$stmt = $pdo->query("SELECT id, question_text, target_staff_category FROM evaluation_questions WHERE target_staff_category LIKE 'S.O%' OR target_staff_category = 'S.O'");
while ($row = $stmt->fetch()) {
    echo "ID: {$row['id']}, Target: {$row['target_staff_category']}, Text: {$row['question_text']}<br>";
}

echo "<h3>All questions by category:</h3>";
$stmt = $pdo->query("SELECT target_staff_category, COUNT(*) as cnt FROM evaluation_questions GROUP BY target_staff_category");
while ($row = $stmt->fetch()) {
    echo "{$row['target_staff_category']}: {$row['cnt']}<br>";
}
?>