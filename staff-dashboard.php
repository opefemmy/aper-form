<?php
require_once 'config.php';
requireStaffLogin();

$pdo = getDBConnection();
$staff = getCurrentStaff();

// Get staff category from database
$stmt = $pdo->prepare("SELECT staff_category FROM staff WHERE id = ?");
$stmt->execute([$staff['id']]);
$staffRow = $stmt->fetch();
$staffCategory = $staffRow['staff_category'] ?? 'academic';

// Get settings
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$primaryColor = $settings['primary_color'] ?? '#308a1e';
$secondaryColor = $settings['secondary_color'] ?? '#269c16';

// Check if evaluation already exists for this staff
$stmt = $pdo->prepare("SELECT * FROM evaluations WHERE staff_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$staff['id']]);
$existingEval = $stmt->fetch();

// Get active academic session
$stmt = $pdo->query("SELECT * FROM academic_sessions WHERE is_active = 1 LIMIT 1");
$activeSession = $stmt->fetch();

// Get questions from database based on staff category (include HOD category for HOD staff)
$stmt = $pdo->prepare("SELECT * FROM evaluation_questions WHERE is_active = 1 AND (target_staff_category = ? OR target_staff_category = 'both' OR target_staff_category = 'hod') ORDER BY category, question_order, id");
$stmt->execute([$staffCategory]);
$dbQuestions = $stmt->fetchAll();

// Group questions by category
$questionsByCategory = [];
foreach ($dbQuestions as $q) {
    $questionsByCategory[$q['category']][] = $q;
}

// Calculate grade function
function calculateGrade($percentage) {
    if ($percentage >= 90) return ['Outstanding', 'Excellent Performance'];
    if ($percentage >= 80) return ['Excellent', 'Very Good Performance'];
    if ($percentage >= 70) return ['Very Good', 'Good Performance'];
    if ($percentage >= 60) return ['Good', 'Satisfactory'];
    if ($percentage >= 50) return ['Fair', 'Needs Improvement'];
    return ['Poor', 'Unsatisfactory'];
}

// Handle form submission
$submitMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    $staffId = $_POST['staff_id'] ?? 0;
    $academicSessionId = $_POST['academic_session_id'] ?? 0;
    $evaluationYear = $_POST['evaluation_year'] ?? date('Y');

    // Collect dynamic responses from the database questions
    $responses = [];
    $numericScore = 0;
    $ratingQuestionCount = 0;

    foreach ($dbQuestions as $q) {
        $fieldName = 'q_' . $q['id'];

        if ($q['question_type'] === 'multiple_choice') {
            // Multiple choice - checkbox array
            $responses[$q['id']] = $_POST[$fieldName] ?? [];
        } else {
            // All other types
            $responses[$q['id']] = $_POST[$fieldName] ?? '';
        }

        // For numeric rating questions, calculate score
        if (($q['question_type'] === 'rating' || $q['question_type'] === 'scale') && !empty($responses[$q['id']])) {
            $numericScore += intval($responses[$q['id']]);
            $ratingQuestionCount++;
        }
    }

    // Calculate totals based on actual rating questions
    $totalScore = $numericScore;
    $questionCount = count(array_filter($dbQuestions, fn($q) => $q['question_type'] === 'rating' || $q['question_type'] === 'scale'));
    $averageScore = $questionCount > 0 ? round($totalScore / $questionCount, 2) : 0;
    $maxPossible = $questionCount * 5;
    $percentage = $maxPossible > 0 ? round(($totalScore / $maxPossible) * 100, 2) : 0;

    // Calculate grade
    $gradeResult = calculateGrade($percentage);

    try {
        // Check if responses column exists, add if not
        try {
            $colStmt = $pdo->query("SHOW COLUMNS FROM evaluations LIKE 'responses'");
            $columnExists = $colStmt->fetch() !== false;
            if (!$columnExists) {
                $pdo->exec("ALTER TABLE evaluations ADD COLUMN responses JSON AFTER staff_category");
            }
        } catch (Exception $e) {
            // Column creation might have failed, continue anyway
        }

        // Check if evaluation exists
        $checkStmt = $pdo->prepare("SELECT id, status, responses FROM evaluations WHERE staff_id = ? AND academic_session_id = ? AND evaluation_year = ?");
        $checkStmt->execute([$staffId, $academicSessionId, $evaluationYear]);
        $existingId = $checkStmt->fetch();

        // Only allow submission if not already submitted (or if it's a draft)
        if ($existingId && $existingId['status'] === 'submitted') {
            $submitMessage = 'You have already submitted your evaluation for this session. Please contact the administrator if you need to make changes.';
        } elseif ($existingId) {
            // Update existing - allow resubmission
            $newStatus = 'submitted';

            // Get legacy scores from existing columns if needed
            $legacyScores = [];
            if (isset($existingId['responses']) && $existingId['responses']) {
                $legacyScores = is_array($existingId['responses']) ? $existingId['responses'] : json_decode($existingId['responses'], true);
            }

            // Merge new dynamic responses with any legacy data
            $allResponses = array_merge($legacyScores ?? [], $responses);

            $updateStmt = $pdo->prepare("UPDATE evaluations SET
                responses = ?,
                total_score = ?,
                average_score = ?,
                percentage = ?,
                performance_grade = ?,
                performance_status = ?,
                status = ?,
                staff_category = ?,
                updated_at = NOW()
                WHERE id = ?");
            $updateStmt->execute([
                json_encode($allResponses),
                $totalScore,
                $averageScore,
                $percentage,
                $gradeResult[0],
                $gradeResult[1],
                $newStatus,
                $staffCategory,
                $existingId['id']
            ]);
            $submitMessage = 'Evaluation updated and submitted successfully!';

            // Refresh the evaluation data
            $stmt = $pdo->prepare("SELECT * FROM evaluations WHERE staff_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$staff['id']]);
            $existingEval = $stmt->fetch();
        } elseif (!$existingId) {
            // Insert new (first submission)
            $insertStmt = $pdo->prepare("INSERT INTO evaluations (
                staff_id, academic_session_id, evaluation_year,
                responses,
                total_score, average_score, percentage, performance_grade, performance_status, status, evaluation_stage, staff_category
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', 'pending', ?)");
            $insertStmt->execute([
                $staffId,
                $academicSessionId,
                $evaluationYear,
                json_encode($responses),
                $totalScore,
                $averageScore,
                $percentage,
                $gradeResult[0],
                $gradeResult[1],
                $staffCategory
            ]);
            $submitMessage = 'Evaluation submitted successfully!';

            // Refresh the evaluation data
            $stmt = $pdo->prepare("SELECT * FROM evaluations WHERE staff_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$staff['id']]);
            $existingEval = $stmt->fetch();
        }
        // If already submitted, don't do anything - message is already set above
    } catch (Exception $e) {
        $submitMessage = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Evaluation - Staff Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="theme-overrides.css" rel="stylesheet">
    <style>
        :root { --primary-blue: #1e3a8a; --secondary-blue: #3b82f6; }
        body { background: #f3f4f6; }
        .top-bar { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); color: white; padding: 1rem 0; }
        .staff-info-card { background: white; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .score-card { background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white; padding: 1.5rem; border-radius: 16px; text-align: center; box-shadow: 0 10px 15px -3px rgba(30, 58, 138, 0.3); }
        .score-card .value { font-size: 2.5rem; font-weight: 700; }
        .question-item { background: white; padding: 1.25rem; border-radius: 12px; margin-bottom: 1rem; border: 1px solid #e5e7eb; transition: all 0.3s ease; }
        .question-item:hover { border-color: #3b82f6; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .rating-label { padding: 0.5rem 0.75rem; background: #f8fafc; border-radius: 20px; cursor: pointer; margin-right: 0.25rem; display: inline-block; text-align: center; min-width: 45px; }
        .rating-label:hover { background: #dbeafe; }
        .rating-label input:checked + span { background: var(--primary-blue); color: white; border-radius: 15px; padding: 2px 8px; }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .top-bar .container { flex-direction: column; align-items: flex-start !important; }
            .top-bar .text-end { margin-top: 10px; }
            .score-card { padding: 1rem; margin-bottom: 10px; }
            .score-card .value { font-size: 1.5rem; }
            .staff-info-card .row > div { margin-bottom: 5px; }
        }

        /* Dark Mode Styles */
        body.dark-mode { background: #1a1a2e; color: #e0e0e0; }
        body.dark-mode .staff-info-card { background: #16213e; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        body.dark-mode .question-item { background: #16213e; border-color: #2a2a4a; }
        body.dark-mode .question-item label { color: #e0e0e0; }
        body.dark-mode .rating-label { background: #2a2a4a; color: #e0e0e0; }
        body.dark-mode .rating-label:hover { background: #3a3a5a; }
        body.dark-mode .card { background: #16213e; border-color: #2a2a4a; }
        body.dark-mode .card-header { background: #1e3a8a !important; }
        body.dark-mode .form-control { background: #2a2a4a; border-color: #3a3a5a; color: #e0e0e0; }
        body.dark-mode .form-control::placeholder { color: #888; }
        body.dark-mode .form-select { background: #2a2a4a; border-color: #3a3a5a; color: #e0e0e0; }
        body.dark-mode .form-check-label { color: #e0e0e0; }
        body.dark-mode .alert-info { background: #1e3a8a; border-color: #2563eb; color: #e0e0e0; }
        body.dark-mode .text-muted { color: #aaa !important; }
        body.dark-mode .table { color: #e0e0e0; }
        body.dark-mode footer { background: linear-gradient(180deg, #1e3a8a 0%, #2563eb 100%) !important; }

        /* Dark mode toggle button */
        .dark-mode-toggle {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .dark-mode-toggle:hover { background: rgba(255,255,255,0.3); }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <?php if (!empty($settings['institution_logo'])): ?>
                    <img src="<?php echo htmlspecialchars($settings['institution_logo']); ?>" alt="Logo" style="max-height: 50px; margin-right: 15px; border: 2px solid white; border-radius: 5px; padding: 3px; background: rgba(255,255,255,0.2);">
                    <?php endif; ?>
                    <div>
                        <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars($settings['institution_name'] ?? 'Institution'); ?></h3>
                        <?php if (!empty($settings['institution_address'])): ?>
                        <small><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($settings['institution_address']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-end">
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-2"></i><?php echo $staff['name']; ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                        <button class="dark-mode-toggle ms-2" onclick="toggleDarkMode()" title="Toggle Dark Mode">
                            <i class="fas fa-moon"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <?php if ($submitMessage): ?>
        <div class="alert alert-<?php echo strpos($submitMessage, 'Error') !== false ? 'danger' : 'success'; ?> alert-dismissible fade show">
            <i class="fas fa-<?php echo strpos($submitMessage, 'Error') !== false ? 'exclamation-circle' : 'check-circle'; ?> me-2"></i>
            <?php echo $submitMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Staff Info -->
        <div class="staff-info-card mb-4">
            <div class="row">
                <div class="col-md-2"><strong>Staff ID:</strong> <?php echo htmlspecialchars($staff['staff_number']); ?></div>
                <div class="col-md-3"><strong>Name:</strong> <?php echo htmlspecialchars($staff['name']); ?></div>
                <div class="col-md-2"><strong>Department:</strong> <?php echo htmlspecialchars($staff['department'] ?? 'N/A'); ?></div>
                <div class="col-md-2"><strong>Level:</strong> <?php echo htmlspecialchars($staff['grade_level'] ?? 'N/A'); ?></div>
                <div class="col-md-2"><strong>Category:</strong> <?php echo $staffCategory == 'academic' ? 'Academic' : 'Non-Teaching'; ?></div>
                <div class="col-md-1"><strong>Session:</strong> <?php echo htmlspecialchars($activeSession['session_name'] ?? 'N/A'); ?></div>
            </div>
        </div>

        <!-- Evaluation Status -->
        <?php if ($existingEval):
            $ratingQuestionCount = count(array_filter($dbQuestions, fn($q) => $q['question_type'] === 'rating' || $q['question_type'] === 'scale'));
            $maxPoints = $ratingQuestionCount * 5;
        ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="score-card">
                    <div class="value"><?php echo $existingEval['total_score']; ?>/<?php echo $maxPoints; ?></div>
                    <div>Points</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="score-card">
                    <div class="value"><?php echo $existingEval['percentage']; ?>%</div>
                    <div>Percentage</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="score-card" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <div class="value"><?php echo $existingEval['performance_grade']; ?></div>
                    <div>Grade</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="score-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <div class="value"><?php echo ucfirst($existingEval['status']); ?></div>
                    <div>Status</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($existingEval && is_array($existingEval) && ($existingEval['status'] ?? '') !== 'draft'): ?>
        <div class="alert alert-info">
            <i class="fas fa-check-circle me-2"></i>
            Your evaluation has been submitted. You can still update and resubmit at any time.
        </div>

        <!-- Print Summary - Available immediately after submission (evidence of participation) -->
        <div class="text-center mb-3">
            <a href="print-summary.php?id=<?php echo $existingEval['id']; ?>" target="_blank" class="btn btn-primary btn-lg">
                <i class="fas fa-print me-2"></i>Print Summary (Evidence of Participation)
            </a>
            <p class="text-muted mt-2"><small>Print this as evidence that you have completed your evaluation</small></p>
        </div>
        <?php endif; ?>

        <?php if ($existingEval && is_array($existingEval) && ($existingEval['status'] ?? '') === 'approved'): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Congratulations!</strong> Your evaluation has been fully approved. You can now download your official evaluation report.
        </div>
        <div class="text-center mb-4">
            <a href="pdf-report.php?id=<?php echo $existingEval['id']; ?>" target="_blank" class="btn btn-success btn-lg">
                <i class="fas fa-file-pdf me-2"></i>Download Official Report (Final Grade)
            </a>
        </div>
        <?php endif; ?>

        <!-- Self Evaluation Form - Always show for editing -->
        <form method="POST" id="evalForm">
            <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
            <input type="hidden" name="academic_session_id" value="<?php echo $activeSession['id'] ?? 0; ?>">
            <input type="hidden" name="evaluation_year" value="<?php echo $settings['evaluation_year'] ?? date('Y'); ?>">

            <!-- Live Score Display -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="score-card" style="background: linear-gradient(135deg, #308a1e, #269c16);">
                        <div class="value" id="liveTotalScore">0</div>
                        <div>Points</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="score-card" style="background: linear-gradient(135deg, #10b981, #059669);">
                        <div class="value" id="liveAvgScore">0</div>
                        <div>Average</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="score-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <div class="value" id="livePercentScore">0%</div>
                        <div>Percentage</div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Self-Evaluation Form</h5>
                </div>
                <div class="card-body">

                    <?php if (empty($questionsByCategory)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No evaluation questions have been configured yet. Please contact the administrator.
                    </div>
                    <?php else: ?>

                    <?php
                    // Define category display names
                    $categoryNames = [
                        'Teaching' => 'Teaching Performance',
                        'Research' => 'Research Performance',
                        'Administrative' => 'Administrative Duties',
                        'Community' => 'Community Service',
                        'Professional' => 'Professional Development'
                    ];

                    // Load existing responses from JSON column
                    $existingResponses = [];
                    if (!empty($existingEval['responses'])) {
                        if (is_array($existingEval['responses'])) {
                            $existingResponses = $existingEval['responses'];
                        } else {
                            $existingResponses = json_decode($existingEval['responses'], true) ?? [];
                        }
                    }

                    // Render each category
                    foreach ($questionsByCategory as $category => $categoryQuestions):
                    ?>
                    <div class="mb-4">
                        <h6 class="text-primary border-bottom pb-2">
                            <i class="fas fa-folder me-2"></i><?php echo htmlspecialchars($categoryNames[$category] ?? $category); ?>
                        </h6>
                        <?php foreach ($categoryQuestions as $index => $q):
                            $fieldName = 'q_' . $q['id'];
                            $existingValue = $existingResponses[$q['id']] ?? '';
                        ?>
                        <div class="question-item">
                            <label class="form-label fw-bold"><?php echo htmlspecialchars($q['question_text']); ?></label>
                            <div>
                                <?php
                                // Render based on question type
                                if ($q['question_type'] === 'rating' || $q['question_type'] === 'scale'):
                                    for ($i = 5; $i >= 1; $i--): ?>
                                <label class="rating-label">
                                    <input type="radio" name="<?php echo $fieldName; ?>" value="<?php echo $i; ?>" onchange="calculateScores()" <?php echo $existingValue == $i ? 'checked' : ''; ?>>
                                    <span><?php echo $i; ?></span>
                                </label>
                                    <?php endfor;
                                elseif ($q['question_type'] === 'yes_no'): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="<?php echo $fieldName; ?>" value="yes" <?php echo $existingValue == 'yes' ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="<?php echo $fieldName; ?>" value="no" <?php echo $existingValue == 'no' ? 'checked' : ''; ?>>
                                    <label class="form-check-label">No</label>
                                </div>
                                <?php elseif ($q['question_type'] === 'true_false'): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="<?php echo $fieldName; ?>" value="true" <?php echo $existingValue == 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label">True</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="<?php echo $fieldName; ?>" value="false" <?php echo $existingValue == 'false' ? 'checked' : ''; ?>>
                                    <label class="form-check-label">False</label>
                                </div>
                                <?php elseif ($q['question_type'] === 'single_choice' && !empty($q['options'])):
                                    $options = explode("\n", $q['options']); ?>
                                <select class="form-select" name="<?php echo $fieldName; ?>" onchange="calculateScores()">
                                    <option value="">Select an option</option>
                                    <?php foreach ($options as $opt): ?>
                                    <option value="<?php echo htmlspecialchars(trim($opt)); ?>" <?php echo $existingValue == trim($opt) ? 'selected' : ''; ?>><?php echo htmlspecialchars(trim($opt)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php elseif ($q['question_type'] === 'multiple_choice' && !empty($q['options'])):
                                    $options = explode("\n", $q['options']);
                                    // Handle both array and string (legacy) formats
                                    if (is_array($existingValue)) {
                                        $existingMulti = $existingValue;
                                    } else {
                                        $existingMulti = explode(',', $existingValue ?? '');
                                    } ?>
                                <?php foreach ($options as $opt): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="<?php echo $fieldName; ?>[]" value="<?php echo htmlspecialchars(trim($opt)); ?>" <?php echo in_array(trim($opt), $existingMulti) ? 'checked' : ''; ?>>
                                    <label class="form-check-label"><?php echo htmlspecialchars(trim($opt)); ?></label>
                                </div>
                                <?php endforeach; ?>
                                <?php elseif ($q['question_type'] === 'short_answer'): ?>
                                <input type="text" class="form-control" name="<?php echo $fieldName; ?>" value="<?php echo htmlspecialchars($existingValue ?? ''); ?>" placeholder="Enter your answer">
                                <?php elseif ($q['question_type'] === 'long_answer'): ?>
                                <textarea class="form-control" name="<?php echo $fieldName; ?>" rows="3" placeholder="Enter your answer"><?php echo htmlspecialchars($existingValue ?? ''); ?></textarea>
                                <?php else: ?>
                                <?php
                                $ratingLabels = [
                                    5 => 'Excellent',
                                    4 => 'Very Good',
                                    3 => 'Good',
                                    2 => 'Fair',
                                    1 => 'Poor'
                                ];
                                for ($i = 5; $i >= 1; $i--): ?>
                                <label class="rating-label" title="<?php echo $ratingLabels[$i]; ?>">
                                    <input type="radio" name="<?php echo $fieldName; ?>" value="<?php echo $i; ?>" onchange="calculateScores()" <?php echo $existingValue == $i ? 'checked' : ''; ?>>
                                    <span><?php echo $i; ?></span>
                                    <small class="d-block" style="font-size: 9px;"><?php echo $ratingLabels[$i]; ?></small>
                                </label>
                                <?php endfor; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>

            <?php if ($existingEval && $existingEval['status'] === 'submitted'): ?>
            <div class="alert alert-info">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Already Submitted:</strong> You have already submitted your evaluation for this session. Contact the administrator to make changes.
            </div>
            <?php else: ?>
            <div class="d-flex gap-2">
                <button type="submit" name="submit_evaluation" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane me-2"></i>Submit Evaluation
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function calculateScores() {
        let total = 0;
        let count = 0;
        const radios = document.querySelectorAll('input[type="radio"]:checked');
        radios.forEach(radio => {
            total += parseInt(radio.value);
            count++;
        });

        const avg = count > 0 ? (total / count).toFixed(2) : 0;
        // Dynamic max possible based on number of rating questions
        const maxPossible = window.questionCount ? window.questionCount * 5 : 23 * 5;
        const percentage = maxPossible > 0 ? ((total / maxPossible) * 100).toFixed(1) : 0;

        // Update score display if it exists
        const totalEl = document.getElementById('liveTotalScore');
        const avgEl = document.getElementById('liveAvgScore');
        const percentEl = document.getElementById('livePercentScore');

        if (totalEl) totalEl.textContent = total;
        if (avgEl) avgEl.textContent = avg;
        if (percentEl) percentEl.textContent = percentage + '%';
    }

    // Set question count for dynamic score calculation
    window.questionCount = <?php echo count(array_filter($dbQuestions, fn($q) => $q['question_type'] === 'rating' || $q['question_type'] === 'scale')); ?>;

    // Dark mode toggle
    function toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
    }

    // Check for saved dark mode preference
    if (localStorage.getItem('darkMode') === 'true') {
        document.body.classList.add('dark-mode');
    }
    </script>

    <!-- Footer -->
    <footer class="mt-4 py-3" style="background: linear-gradient(180deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%); color: white; border-radius: 8px;">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <small><?php echo !empty($settings['copyright_text']) ? htmlspecialchars($settings['copyright_text']) : '&copy; ' . date('Y') . ' ' . htmlspecialchars($settings['institution_name'] ?? 'Institution') . '. All rights reserved.'; ?></small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>Powered by APER System</small>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>