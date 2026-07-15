<?php
/**
 * Add sample Supervising Officer questions for each category
 */
require_once 'config.php';

$pdo = getDBConnection();

echo "Adding sample Supervising Officer questions...\n";

// Sample questions for Academic Staff (S.O_academic)
$academicQuestions = [
    ['Teaching', 'How would you rate the staff\'s teaching quality and effectiveness?', 1],
    ['Teaching', 'Does the staff maintain good class management and student engagement?', 2],
    ['Research', 'How would you rate the staff\'s research output and publications?', 3],
    ['Administrative', 'Does the staff attend departmental meetings and fulfill administrative duties?', 4],
    ['Professional', 'How would you rate the staff\'s professional development and skills?', 5],
];

// Sample questions for Non-Teaching Senior (S.O_senior)
$seniorQuestions = [
    ['Job Performance', 'How would you rate the staff\'s overall job performance?', 1],
    ['Job Performance', 'Does the staff demonstrate efficiency in completing tasks?', 2],
    ['Administrative', 'Does the staff maintain proper records and documentation?', 3],
    ['Professional', 'How would you rate the staff\'s punctuality and attendance?', 4],
    ['Professional', 'Does the staff demonstrate good teamwork and collaboration?', 5],
];

// Sample questions for Junior Staff (S.O_junior)
$juniorQuestions = [
    ['Job Performance', 'How would you rate the staff\'s performance in their assigned duties?', 1],
    ['Job Performance', 'Does the staff complete tasks timely and efficiently?', 2],
    ['Administrative', 'Does the staff maintain good attendance and punctuality?', 3],
    ['Professional', 'How would you rate the staff\'s willingness to learn and improve?', 4],
    ['Professional', 'Does the staff demonstrate good attitude and teamwork?', 5],
];

$added = 0;

// Add Academic Staff questions
foreach ($academicQuestions as $q) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO evaluation_questions (category, question_text, question_order, question_type, target_staff_category, is_active) VALUES (?, ?, ?, 'rating', 'S.O_academic', 1)");
    $stmt->execute([$q[0], $q[1], $q[2]]);
    if ($stmt->rowCount() > 0) {
        $added++;
        echo "Added (Academic): {$q[1]}\n";
    }
}

// Add Senior Staff questions
foreach ($seniorQuestions as $q) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO evaluation_questions (category, question_text, question_order, question_type, target_staff_category, is_active) VALUES (?, ?, ?, 'rating', 'S.O_senior', 1)");
    $stmt->execute([$q[0], $q[1], $q[2]]);
    if ($stmt->rowCount() > 0) {
        $added++;
        echo "Added (Senior): {$q[1]}\n";
    }
}

// Add Junior Staff questions
foreach ($juniorQuestions as $q) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO evaluation_questions (category, question_text, question_order, question_type, target_staff_category, is_active) VALUES (?, ?, ?, 'rating', 'S.O_junior', 1)");
    $stmt->execute([$q[0], $q[1], $q[2]]);
    if ($stmt->rowCount() > 0) {
        $added++;
        echo "Added (Junior): {$q[1]}\n";
    }
}

echo "\nTotal questions added: $added\n";

// Also add a generic S.O question for "all categories"
$stmt = $pdo->prepare("INSERT IGNORE INTO evaluation_questions (category, question_text, question_order, question_type, target_staff_category, is_active) VALUES (?, ?, ?, 'rating', 'S.O', 1)");
$stmt->execute(['General', 'How would you rate the staff\'s overall contribution to the department?', 1]);
if ($stmt->rowCount() > 0) {
    $added++;
    echo "Added (S.O All): How would you rate the staff's overall contribution to the department?\n";
}

echo "\nDone! Total added: $added\n";