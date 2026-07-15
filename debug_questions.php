<?php
/**
 * Debug script to check questions in database
 */
require_once 'config.php';

$pdo = getDBConnection();

echo "<h2>Debug: Questions in Database</h2>";

// Check all questions
echo "<h3>All Active Questions</h3>";
$stmt = $pdo->query("SELECT id, category, question_text, target_staff_category, is_active FROM evaluation_questions WHERE is_active = 1 ORDER BY target_staff_category, category, id");
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Category</th><th>Question</th><th>Target Staff Category</th><th>Active</th></tr>";
foreach ($questions as $q) {
    echo "<tr>";
    echo "<td>{$q['id']}</td>";
    echo "<td>{$q['category']}</td>";
    echo "<td>{$q['question_text']}</td>";
    echo "<td>{$q['target_staff_category']}</td>";
    echo "<td>{$q['is_active']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Questions grouped by target_staff_category</h3>";
$stmt = $pdo->query("SELECT target_staff_category, COUNT(*) as cnt FROM evaluation_questions WHERE is_active = 1 GROUP BY target_staff_category ORDER BY target_staff_category");
while ($row = $stmt->fetch()) {
    echo "{$row['target_staff_category']}: {$row['cnt']} questions<br>";
}

// Check pending evaluations
echo "<h3>Pending Evaluations (stage = 'pending')</h3>";
$stmt = $pdo->query("SELECT e.id, e.evaluation_stage, e.status, s.first_name, s.surname, s.department
    FROM evaluations e
    JOIN staff s ON e.staff_id = s.id
    WHERE e.evaluation_stage = 'pending' AND e.status = 'submitted'
    ORDER BY e.created_at DESC LIMIT 20");
$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pending)) {
    echo "No pending evaluations found!<br>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Stage</th><th>Status</th><th>Staff Name</th><th>Department</th></tr>";
    foreach ($pending as $p) {
        echo "<tr>";
        echo "<td>{$p['id']}</td>";
        echo "<td>{$p['evaluation_stage']}</td>";
        echo "<td>{$p['status']}</td>";
        echo "<td>{$p['first_name']} {$p['surname']}</td>";
        echo "<td>{$p['department']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test the query used in evaluate-supervisor.php
echo "<h3>Testing SO Questions Query (for S.O_academic)</h3>";
$stmt = $pdo->prepare("SELECT * FROM evaluation_questions
    WHERE is_active = 1
    AND (
        target_staff_category = ?
        OR target_staff_category = 'S.O'
        OR target_staff_category = 'both'
        OR target_staff_category IS NULL
        OR target_staff_category = ''
    )
    ORDER BY COALESCE(question_order, 99999), category, id");
$stmt->execute(['S.O_academic']);
$soQuestions = $stmt->fetchAll();

echo "Found " . count($soQuestions) . " questions for S.O_academic<br>";

if (count($soQuestions) > 0) {
    echo "<ul>";
    foreach ($soQuestions as $q) {
        echo "<li>[{$q['target_staff_category']}] {$q['question_text']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>No questions found! This is the problem.</p>";
}

// Test with LIKE
echo "<h3>Testing with LIKE 'S.O%'</h3>";
$stmt = $pdo->query("SELECT * FROM evaluation_questions
    WHERE is_active = 1
    AND (
        target_staff_category LIKE 'S.O%'
        OR target_staff_category = 'S.O'
        OR target_staff_category = 'both'
        OR target_staff_category IS NULL
        OR target_staff_category = ''
    )
    ORDER BY COALESCE(question_order, 99999), category, id");
$soQuestionsLike = $stmt->fetchAll();
echo "Found " . count($soQuestionsLike) . " questions with LIKE<br>";