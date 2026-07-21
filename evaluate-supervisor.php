<?php
require_once 'config.php';
require_once 'mail.php';

// Define the Appointment and Promotion Committee name (renamed from Dean)
define('APC_COMMITTEE_NAME', 'Appointment and Promotion Committee');

// Check if evaluator (Supervising Officer/Registrar) is logged in
if (isEvaluatorLoggedIn()) {
    // Evaluator is logged in - set up the page for them
    $evaluatorType = getEvaluatorType();
    $evaluatorId = $_SESSION['staff_id'];
    $evaluatorName = $_SESSION['staff_name'];
    // Map evaluator_type to admin role - 'supervising-officer' is the role for Supervising Officer
    $adminRole = strtolower($evaluatorType);
    if ($adminRole === 'supervising officer') {
        $adminRole = 'supervising-officer';
    }
    $admin = [
        'id' => $evaluatorId,
        'name' => $evaluatorName,
        'role' => $adminRole,
        'email' => ''
    ];
    $adminId = $evaluatorId;
    $adminName = $evaluatorName;
} else {
    // Admin login required
    requireAdminLogin();
    $admin = getCurrentAdmin();
    $adminRole = $admin['role'];
    $adminId = $admin['id'];
    $adminName = $admin['name'];

    // Only allow supervising officers, registrars, admins
    if (!in_array($adminRole, ['supervising-officer', 'supervisor', 'registrar', 'super_admin', 'admin'])) {
        die("You don't have permission to access this page.");
    }
}

// Get settings
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$institutionName = $settings['institution_name'] ?? 'Institution';
$institutionAddress = $settings['institution_address'] ?? '';
$institutionLogo = $settings['institution_logo'] ?? '';
$primaryColor = $settings['primary_color'] ?? '#247d57';

$message = getMessage();
$evalId = $_GET['eval_id'] ?? null;
$staffId = $_GET['staff_id'] ?? null;

// Determine what stage of evaluation we're handling
// Check URL param first (can be 'supervising_officer_reject' for rejected evaluations)
$currentStage = $_GET['stage'] ?? '';

// Override based on admin role, but allow URL param to take precedence for rejected evaluations
if ($adminRole === 'supervising-officer' && empty($_GET['stage'])) {
    $currentStage = 'supervising-officer';
} elseif ($adminRole === 'registrar') {
    $currentStage = 'registrar';
} elseif (empty($currentStage)) {
    $currentStage = 'pending';
}

// Get staff evaluator profile if exists (for staff who are also Supervising Officer/Registrar)
$evaluatorProfile = null;
$stmt = $pdo->prepare("SELECT * FROM staff WHERE email = ? AND evaluator_type IN ('Supervising Officer', 'Registrar')");
$stmt->execute([$admin['email'] ?? '']);
$evaluatorProfile = $stmt->fetch();

// Get staff list based on role
$evaluatorDept = '';
$evaluatorFac = '';
$evaluatorId = $_SESSION['staff_id'] ?? 0;

// Get evaluator's department/faculty from session or database
if (isset($_SESSION['is_evaluator']) && $_SESSION['is_evaluator']) {
    $evaluatorDept = $_SESSION['staff_department'] ?? '';
    $evaluatorFac = $_SESSION['staff_faculty'] ?? '';
} elseif ($evaluatorProfile) {
    $evaluatorDept = $evaluatorProfile['department'] ?? '';
    $evaluatorFac = $evaluatorProfile['faculty'] ?? '';
}

if ($adminRole === 'supervisor' || $adminRole === 'supervising-officer') {
    // Supervising Officer sees ALL staff (like registrar)
    $stmt = $pdo->query("SELECT * FROM staff WHERE status = 'active' AND evaluator_type IS NULL ORDER BY department, first_name, surname");
    $staffList = $stmt->fetchAll();
} elseif ($adminRole === 'registrar') {
    // Registrar sees ALL staff
    $stmt = $pdo->query("SELECT * FROM staff WHERE status = 'active' ORDER BY first_name, surname");
    $staffList = $stmt->fetchAll();
} else {
    // Admin/Super admin sees all
    $stmt = $pdo->query("SELECT * FROM staff WHERE status = 'active' ORDER BY first_name, surname");
    $staffList = $stmt->fetchAll();
}

// Get pending evaluations for this evaluator
// Use session variables for department/faculty
$evalDept = $_SESSION['staff_department'] ?? '';
$evalFac = $_SESSION['staff_faculty'] ?? '';

if ($adminRole === 'supervisor' || $adminRole === 'supervising-officer') {
    // Supervising Officer sees staff in their department who have submitted (evaluation_stage = 'pending')
    // OR staff who have reviewed and need Supervising Officer to address their concerns (evaluation_stage = 'supervising_officer_reject')
    if (!empty($evalDept)) {
        // Pending: staff submitted but Supervising Officer hasn't evaluated yet
        $stmt = $pdo->prepare("SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level, s.staff_category
            FROM evaluations e
            JOIN staff s ON e.staff_id = s.id
            WHERE s.department = ? AND e.evaluation_stage = 'pending' AND e.status = 'submitted'
            ORDER BY e.created_at DESC");
        $stmt->execute([$evalDept]);
        $pendingEvals = $stmt->fetchAll();

        // Rejected by staff - needs Supervising Officer review
        $stmt = $pdo->prepare("SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level, s.staff_category
            FROM evaluations e
            JOIN staff s ON e.staff_id = s.id
            WHERE s.department = ? AND e.evaluation_stage = 'supervising_officer_reject' AND e.status = 'submitted'
            ORDER BY e.created_at DESC");
        $stmt->execute([$evalDept]);
        $rejectedByStaff = $stmt->fetchAll();

        // Processed: evaluations that have passed Supervising Officer stage (including staff_review)
        $stmt = $pdo->prepare("SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level, s.staff_category
            FROM evaluations e
            JOIN staff s ON e.staff_id = s.id
            WHERE s.department = ? AND e.evaluation_stage IN ('staff_review', 'supervising_officer_reject', 'registrar', 'completed')
            ORDER BY e.updated_at DESC");
        $stmt->execute([$evalDept]);
        $processedEvals = $stmt->fetchAll();
    } else {
        $pendingEvals = [];
        $processedEvals = [];
        $rejectedByStaff = [];
    }
} elseif ($adminRole === 'registrar') {
    // Registrar sees all evaluations waiting for final approval AND rejected ones that have been reviewed
    $stmt = $pdo->prepare("SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level, s.staff_category
        FROM evaluations e
        JOIN staff s ON e.staff_id = s.id
        WHERE e.evaluation_stage IN ('registrar', 'supervising_officer_reject')
        ORDER BY e.created_at DESC");
    $stmt->execute();
    $pendingEvals = $stmt->fetchAll();

    // Get already completed evaluations
    $stmt = $pdo->query("SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level, s.staff_category
        FROM evaluations e
        JOIN staff s ON e.staff_id = s.id
        WHERE e.evaluation_stage = 'completed'
        ORDER BY e.updated_at DESC");
    $processedEvals = $stmt->fetchAll();
} else {
    // Admin/Super admin sees all non-completed
    $stmt = $pdo->query("SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level, s.staff_category
        FROM evaluations e
        JOIN staff s ON e.staff_id = s.id
        WHERE e.evaluation_stage != 'completed'
        ORDER BY e.created_at DESC");
    $pendingEvals = $stmt->fetchAll();

    // Get completed evaluations
    $stmt = $pdo->query("SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level, s.staff_category
        FROM evaluations e
        JOIN staff s ON e.staff_id = s.id
        WHERE e.evaluation_stage = 'completed'
        ORDER BY e.updated_at DESC");
    $processedEvals = $stmt->fetchAll();
}

// Get selected evaluation
$selectedEval = null;
$selectedStaff = null;

// First priority: get by eval_id
if ($evalId) {
    // Use e.staff_id (numeric ID from evaluations table) with alias to avoid collision with s.staff_id (string)
    $stmt = $pdo->prepare("SELECT e.*, s.staff_id as staff_id_string, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level, s.staff_category
        FROM evaluations e
        JOIN staff s ON e.staff_id = s.id
        WHERE e.id = ?");
    $stmt->execute([$evalId]);
    $selectedEval = $stmt->fetch();

    if ($selectedEval) {
        // Use e.staff_id (the numeric ID from evaluations table) - this is what we need
        $staffId = $selectedEval['staff_id']; // This is e.staff_id from the query above
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
        $stmt->execute([$staffId]);
        $selectedStaff = $stmt->fetch();
    }
} elseif ($staffId) {
    // Second priority: get by staff_id directly
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([$staffId]);
    $selectedStaff = $stmt->fetch();

    // Get latest evaluation for this staff
    if ($selectedStaff) {
        $stmt = $pdo->prepare("SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level, s.staff_category
            FROM evaluations e
            JOIN staff s ON e.staff_id = s.id
            WHERE e.staff_id = ? AND e.evaluation_year = ?
            ORDER BY e.created_at DESC LIMIT 1");
        $stmt->execute([$staffId, date('Y')]);
        $selectedEval = $stmt->fetch();
    }
}

// Determine staff category for question display
$staffCategory = ($selectedStaff && isset($selectedStaff['staff_category'])) ? $selectedStaff['staff_category'] : 'academic';

// Get evaluator type
$evaluatorRole = $adminRole;

// Define questions based on evaluator role
// HOD evaluates based on staff self-evaluation, adds remarks
// Dean reviews and adds comments
// Registrar approves/rejects

// Load HOD-specific questions from database
// Try to get questions from evaluation_questions table filtered by evaluator_category
$questionsByCategory = [];

// Initialize showQuestions to false by default
$showQuestions = false;

if ($evaluatorRole === 'supervisor' || $evaluatorRole === 'supervising-officer' || $evaluatorRole === 'hod' || $evaluatorRole === 'dean' || $evaluatorRole === 'admin' || $evaluatorRole === 'super_admin') {
    // Get staff category to filter SO questions appropriately
    $staffCategoryForQuestions = null;

    // Only load questions when a staff member is selected
    // Use 'academic' as default if staff_category is empty or not set
    if ($selectedStaff) {
        $showQuestions = true;
        $sc = $selectedStaff['staff_category'] ?? 'academic';

        // Map staff category to SO question category
        if ($sc === 'non-teaching-junior') {
            $staffCategoryForQuestions = 'S.O_junior';
        } elseif ($sc === 'non-teaching') {
            $staffCategoryForQuestions = 'S.O_senior';
        } else {
            // Default to academic (includes empty/null/any other value)
            $staffCategoryForQuestions = 'S.O_academic';
        }

        error_log("Selected staff: " . ($selectedStaff['first_name'] ?? 'unknown') . ", category: $sc, SO questions for: $staffCategoryForQuestions");
    } else {
        error_log("No staff selected");
    }

    // Get SO evaluation questions - ONLY when a staff member is selected
    // Only include: S.O_junior, S.O_senior, S.O_academic, S.O (generic)
    try {
        if ($showQuestions && $staffCategoryForQuestions) {
            // Specific category for selected staff - only show that category + generic S.O
            // Use IN clause like questions.php does
            $stmt = $pdo->prepare("SELECT * FROM evaluation_questions
                WHERE is_active = 1
                AND (
                    target_staff_category = ?
                    OR target_staff_category = 'S.O'
                )
                ORDER BY COALESCE(question_order, 99999), category, id");
            $stmt->execute([$staffCategoryForQuestions]);
            $dbQuestions = $stmt->fetchAll();

            // Debug: log what category is being used
            error_log("SO Questions - Staff category: " . $staffCategoryForQuestions . ", Questions found: " . count($dbQuestions));
        } else {
            // No staff selected - don't show any questions
            $dbQuestions = [];
            error_log("SO Questions - No staff selected or showQuestions=false, no questions loaded. selectedStaff: " . ($selectedStaff ? 'yes' : 'no') . ", staff_category: " . ($selectedStaff['staff_category'] ?? 'none'));
        }

        // Debug: log what category is being used
        error_log("SO Questions - Staff category: " . ($staffCategoryForQuestions ?? 'none') . ", Questions found: " . count($dbQuestions));

        // Group questions by ACTUAL category from database (dynamic - uses user's custom categories)
        $questionsByCategory = [];
        foreach ($dbQuestions as $q) {
            $qName = 'q_' . $q['id'];
            $qLabel = $q['question_text'];
            $qCat = $q['category'] ?? 'General';

            if (!isset($questionsByCategory[$qCat])) {
                $questionsByCategory[$qCat] = [];
            }
            $questionsByCategory[$qCat][] = ['name' => $qName, 'label' => $qLabel, 'id' => $q['id']];
        }

        // Sort categories by order if available
        ksort($questionsByCategory);
    } catch (Exception $e) {
        // Log error but continue with empty arrays
        error_log("Error loading HOD questions: " . $e->getMessage());
    }
}

// Calculate scores and grade
function calculateGrade($percentage) {
    if ($percentage >= 90) return ['Outstanding', 'Excellent Performance'];
    if ($percentage >= 80) return ['Excellent', 'Very Good Performance'];
    if ($percentage >= 70) return ['Very Good', 'Good Performance'];
    if ($percentage >= 60) return ['Good', 'Satisfactory'];
    if ($percentage >= 50) return ['Fair', 'Needs Improvement'];
    return ['Poor', 'Unsatisfactory'];
}

// Handle bulk approval for Registrar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_approve_all']) && $adminRole === 'registrar') {
    try {
        $pdo->beginTransaction();

        // Get all evaluations at 'registrar' stage (ready for final approval)
        $stmt = $pdo->query("SELECT id FROM evaluations WHERE evaluation_stage = 'registrar'");
        $evalsToApprove = $stmt->fetchAll();

        $adminId = $_SESSION['admin_id'] ?? 0;
        $adminName = $_SESSION['admin_name'] ?? 'Registrar';

        $approvedCount = 0;
        foreach ($evalsToApprove as $eval) {
            $updateStmt = $pdo->prepare("UPDATE evaluations SET
                evaluation_stage = 'completed',
                registrar_name = ?,
                registrar_remarks = 'Approved via bulk approval',
                approval_status = 'Approved',
                registrar_signature = ?,
                registrar_date = ?,
                status = 'approved',
                updated_at = NOW()
                WHERE id = ?");
            $updateStmt->execute([$adminName, $adminName, date('Y-m-d'), $eval['id']]);
            $approvedCount++;

            // Send email notification to staff
            try {
                $staffStmt = $pdo->prepare("SELECT e.*, s.* FROM evaluations e JOIN staff s ON e.staff_id = s.id WHERE e.id = ?");
                $staffStmt->execute([$eval['id']]);
                $staffData = $staffStmt->fetch();

                if ($staffData) {
                    sendEvaluationStageNotification('completed', $eval, $staffData);
                }
            } catch (Exception $e) {
                error_log("Email notification error: " . $e->getMessage());
            }
        }

        $pdo->commit();
        showMessage("Successfully approved $approvedCount evaluations!", 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        showMessage('Error: ' . $e->getMessage(), 'danger');
    }
    redirect('evaluate-supervisor.php');
}

// Handle form submission - FIXED: Handle both save_evaluation and save_and_next
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_evaluation']) || isset($_POST['save_and_next']))) {

    // Check if this is a Save & Next action
    $isSaveAndNext = isset($_POST['save_and_next']);
    try {
        $pdo->beginTransaction();

        // Determine next stage - Workflow: Staff -> Supervising Officer -> Staff Review -> (consent: Registrar, reject: Supervising Officer Review) -> Registrar -> Completed
        $nextStage = 'completed';
        if ($adminRole === 'supervisor' || $adminRole === 'supervising-officer') {
            // After Supervising Officer evaluates, goes to Staff for review
            $nextStage = 'staff_review';
        } elseif ($adminRole === 'registrar') {
            $nextStage = 'completed'; // Registrar is final approval
        }

        // Collect scores from form (HOD evaluation) - Use dynamic categories
        $scores = [];
        $allQuestions = [];

        if (!empty($questionsByCategory)) {
            foreach ($questionsByCategory as $categoryQuestions) {
                $allQuestions = array_merge($allQuestions, $categoryQuestions);
            }
        }

        foreach ($allQuestions as $q) {
            $scores[$q['name']] = intval($_POST[$q['name']] ?? 0);
        }

        // Get existing staff self-evaluation scores from the evaluation
        $staffScores = [];
        if ($selectedEval && isset($selectedEval['responses']) && !empty($selectedEval['responses'])) {
            $staffResponses = is_array($selectedEval['responses']) ? $selectedEval['responses'] : json_decode($selectedEval['responses'], true);
            if (is_array($staffResponses)) {
                foreach ($staffResponses as $key => $value) {
                    if (is_numeric($value)) {
                        $staffScores[$key] = intval($value);
                    }
                }
            }
        }

        // Also check individual score columns for legacy data
        $scoreColumns = ['teaching_1', 'teaching_2', 'teaching_3', 'teaching_4', 'teaching_5', 'teaching_6',
                        'research_1', 'research_2', 'research_3', 'research_4', 'research_5',
                        'admin_1', 'admin_2', 'admin_3', 'admin_4', 'admin_5',
                        'community_1', 'community_2', 'community_3',
                        'professional_1', 'professional_2', 'professional_3', 'professional_4'];
        if ($selectedEval) {
            foreach ($scoreColumns as $col) {
                if (isset($selectedEval[$col]) && is_numeric($selectedEval[$col])) {
                    $staffScores[$col] = intval($selectedEval[$col]);
                }
            }
        }

        // Initialize update data
        $updateData = [];

        // Simplified workflow: Staff -> Supervising Officer -> Registrar -> Completed
        if ($adminRole === 'supervisor' || $adminRole === 'supervising-officer' || $adminRole === 'admin' || $adminRole === 'super_admin') {
            // Supervising Officer evaluates - always first evaluation for this stage
            $isFirstEvaluation = !$selectedEval || $selectedEval['evaluation_stage'] === 'pending';

            if ($isFirstEvaluation) {
                // First Supervising Officer evaluation - calculate scores
                $supervisingOfficerScores = $scores;

                // Calculate Supervising Officer total and grade
                $totalScore = array_sum(array_filter($supervisingOfficerScores, function($v) { return $v > 0; }));
                $questionsAnswered = count(array_filter($supervisingOfficerScores, function($v) { return $v > 0; }));
                $averageScore = $questionsAnswered > 0 ? round($totalScore / $questionsAnswered, 2) : 0;
                $maxPossible = count($supervisingOfficerScores) * 5;
                if ($maxPossible == 0) $maxPossible = 23 * 5;
                $percentage = $maxPossible > 0 ? round(($totalScore / $maxPossible) * 100, 1) : 0;
                $gradeInfo = calculateGrade($percentage);

                // Build update data - use Supervising Officer scores for grading
                $updateData = [
                    'total_score' => $totalScore,
                    'average_score' => $averageScore,
                    'percentage' => $percentage,
                    'performance_grade' => $gradeInfo[0],
                    'performance_status' => $gradeInfo[1],
                    'evaluated_by' => $adminId,
                    // Store Supervising Officer-specific scores
                    'hod_total_score' => $totalScore,
                    'hod_percentage' => $percentage,
                    'hod_performance_grade' => $gradeInfo[0],
                    'hod_performance_status' => $gradeInfo[1],
                ];

                // Supervising Officer evaluation - advance to Staff Review stage
                $updateData['evaluation_stage'] = 'staff_review';
                $updateData['hod_id'] = $adminId;
                $updateData['supervisor_name'] = sanitize($_POST['supervisor_name'] ?? $adminName);
                $updateData['supervisor_designation'] = sanitize($_POST['supervisor_designation'] ?? 'Supervising Officer');
                $updateData['supervisor_remarks'] = sanitize($_POST['supervisor_remarks'] ?? '');
                $updateData['supervisor_signature'] = sanitize($_POST['supervisor_signature'] ?? $adminName);
                $updateData['supervisor_date'] = date('Y-m-d');
                $updateData['overall_rating'] = sanitize($_POST['overall_rating'] ?? '');
                $updateData['recommendation'] = sanitize($_POST['recommendation'] ?? '');

                // Save Supervising Officer's scores to responses field (prefixed with 'so_')
                $soResponses = [];
                foreach ($scores as $key => $value) {
                    $soResponses['so_' . $key] = $value;
                }
                if (!empty($soResponses)) {
                    // Merge with existing responses
                    $existingResponses = [];
                    if ($selectedEval && isset($selectedEval['responses']) && !empty($selectedEval['responses'])) {
                        $existingResponses = is_array($selectedEval['responses']) ? $selectedEval['responses'] : json_decode($selectedEval['responses'], true);
                    }
                    $mergedResponses = array_merge($existingResponses ?? [], $soResponses);
                    $updateData['responses'] = json_encode($mergedResponses);
                }
            } else {
                // Re-evaluation after staff rejection - update supervisor details and pass back to staff review
                $updateData['evaluation_stage'] = 'staff_review';
                $updateData['supervisor_name'] = sanitize($_POST['supervisor_name'] ?? $adminName);
                $updateData['supervisor_designation'] = sanitize($_POST['supervisor_designation'] ?? 'Supervising Officer');
                $updateData['supervisor_remarks'] = sanitize($_POST['supervisor_remarks'] ?? '');
                $updateData['supervisor_signature'] = sanitize($_POST['supervisor_signature'] ?? $adminName);
                $updateData['supervisor_date'] = date('Y-m-d');
            }
        } elseif ($adminRole === 'registrar') {
            // Registrar final approval - PRESERVE Supervising Officer's score (don't recalculate)
            $updateData['evaluation_stage'] = 'completed';
            $updateData['registrar_name'] = sanitize($_POST['registrar_name'] ?? $adminName);
            $updateData['registrar_remarks'] = sanitize($_POST['registrar_remarks'] ?? '');
            $updateData['approval_status'] = sanitize($_POST['approval_status'] ?? 'Approved');
            $updateData['registrar_signature'] = sanitize($_POST['registrar_signature'] ?? $adminName);
            $updateData['registrar_date'] = date('Y-m-d');
            $updateData['status'] = 'approved';
        }

        // Note: We don't save individual score fields - only the calculated percentage, grade, etc.
        // The score calculation happens above and stores: total_score, percentage, performance_grade, etc.

        // Check if we need to create a new evaluation or update existing
        if (!$evalId && $staffId) {
            // Check if evaluation exists for this staff in current year
            $checkStmt = $pdo->prepare("SELECT id FROM evaluations WHERE staff_id = ? AND evaluation_year = ?");
            $checkStmt->execute([$staffId, date('Y')]);
            $existingEval = $checkStmt->fetch();

            if ($existingEval) {
                $evalId = $existingEval['id'];
            } else {
                // Get active academic session
                $sessionStmt = $pdo->query("SELECT id FROM academic_sessions WHERE is_active = 1 LIMIT 1");
                $activeSession = $sessionStmt->fetch();
                $academicSessionId = $activeSession['id'] ?? 1;

                // Create new evaluation - use stage from $updateData if set (e.g., 'staff_review' from SO evaluation)
                // Otherwise determine based on admin role
                $stage = $updateData['evaluation_stage'] ?? (($adminRole === 'supervisor' || $adminRole === 'supervising-officer') ? 'staff_review' :
                                         ($adminRole === 'hod' ? 'hod' :
                                         ($adminRole === 'dean' ? 'dean' : 'pending')));

                $insertData = array_merge($updateData, [
                    'staff_id' => $staffId,
                    'academic_session_id' => $academicSessionId,
                    'evaluation_year' => date('Y'),
                    'status' => 'submitted',
                    'evaluation_stage' => $stage,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                $fields = array_keys($insertData);
                $placeholders = implode(', ', array_fill(0, count($insertData), '?'));
                $sql = "INSERT INTO evaluations (" . implode(', ', $fields) . ") VALUES ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($insertData));
                $evalId = $pdo->lastInsertId();
            }
        }

        // Build SQL for update
        $fields = [];
        $values = [];
        foreach ($updateData as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $evalId;

        $sql = "UPDATE evaluations SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        $pdo->commit();

        // Send email notification to staff when SO passes evaluation
        if ($updateData['evaluation_stage'] === 'staff_review') {
            try {
                // Get staff details
                $staffStmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
                $staffStmt->execute([$staffId]);
                $staffMember = $staffStmt->fetch();

                if ($staffMember) {
                    // Get supervisor details for the email
                    $supervisor = [
                        'first_name' => $adminName,
                        'surname' => '',
                        'email' => ''
                    ];
                    if (isset($admin['email'])) {
                        $supervisor['email'] = $admin['email'];
                    }

                    sendEvaluationStageNotification('staff_review', ['id' => $evalId, 'evaluation_year' => date('Y')], $staffMember, $supervisor);
                }
            } catch (Exception $e) {
                error_log("Email notification error: " . $e->getMessage());
            }
        }

        // Send email notification when Registrar approves (completed)
        if ($updateData['evaluation_stage'] === 'completed') {
            try {
                $staffStmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
                $staffStmt->execute([$staffId]);
                $staffMember = $staffStmt->fetch();

                if ($staffMember) {
                    // Get the evaluation for percentage and grade
                    $evalStmt = $pdo->prepare("SELECT * FROM evaluations WHERE id = ?");
                    $evalStmt->execute([$evalId]);
                    $evaluation = $evalStmt->fetch();

                    sendEvaluationStageNotification('completed', $evaluation, $staffMember);
                }
            } catch (Exception $e) {
                error_log("Email notification error: " . $e->getMessage());
            }
        }

        showMessage('Evaluation saved successfully!', 'success');

        // Redirect based on action
        if (isset($_POST['save_and_next'])) {
            redirect('evaluate-supervisor.php?stage=' . $nextStage);
        } else {
            redirect("evaluate-supervisor.php?eval_id=$evalId");
        }

        } catch (Exception $e) {
        $pdo->rollBack();
        showMessage('Error: ' . $e->getMessage(), 'danger');
    }
}

// Handle SO Push to Registrar (when staff rejects and SO decides not to re-evaluate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['push_to_registrar'])) {
    $evalId = intval($_POST['eval_id'] ?? 0);
    $pushReason = sanitize($_POST['so_push_reason'] ?? '');

    if (empty($evalId)) {
        showMessage('Invalid evaluation ID', 'danger');
    } elseif (empty($pushReason)) {
        showMessage('Please provide a reason for pushing to Registrar', 'warning');
    } else {
        try {
            $pdo->beginTransaction();

            // Update evaluation: push to registrar with reason
            $stmt = $pdo->prepare("UPDATE evaluations SET
                evaluation_stage = 'registrar',
                so_push_to_registrar = 1,
                so_push_reason = ?,
                supervising_officer_final_comments = ?
                WHERE id = ?");
            $stmt->execute([$pushReason, "Pushed to Registrar: " . $pushReason, $evalId]);

            $pdo->commit();
            showMessage('Evaluation pushed to Registrar successfully!', 'success');

            // Redirect to pending list
            redirect('evaluate-supervisor.php?stage=registrar');

        } catch (Exception $e) {
            $pdo->rollBack();
            showMessage('Error: ' . $e->getMessage(), 'danger');
        }
    }
}

// Get academic sessions
$stmt = $pdo->query("SELECT * FROM academic_sessions ORDER BY year DESC, semester");
$sessions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($adminRole); ?> Evaluation - <?php echo htmlspecialchars($institutionName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="theme-overrides.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: #247d57; }
        body { background: #f3f4f6; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #247d57 0%, #1a5238 100%); color: white; }
        .sidebar .sidebar-header h5 { color: #10b981 !important; font-weight: 700; }
        .sidebar .sidebar-header small { color: #10b981 !important; font-weight: 600; }
        .sidebar a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 12px 15px; display: block; border-radius: 8px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); color: white; }

        /* Mobile Hamburger Menu */
        .hamburger { display: none; background: none; border: none; cursor: pointer; padding: 10px; z-index: 1001; }
        .hamburger span { display: block; width: 25px; height: 3px; background: white; margin: 5px 0; border-radius: 2px; transition: 0.3s; }
        .hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 6px); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(5px, -6px); }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 768px) {
            .hamburger { display: block; }
            .sidebar { position: fixed; left: -280px; top: 0; bottom: 0; width: 280px; z-index: 1000; transition: left 0.3s ease; overflow-y: auto; }
            .sidebar.active { left: 0; }
            .main-content { margin-left: 0 !important; }
        }

        .question-item { background: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #e2e8f0; }
        .rating-label { padding: 0.5rem 0.75rem; background: #f8fafc; border-radius: 20px; cursor: pointer; margin-right: 0.25rem; margin-bottom: 0.25rem; display: inline-block; }
        .rating-label:hover { background: #dbeafe; }
        .score-display { background: linear-gradient(135deg, #247d57, #1a5238); color: white; padding: 1rem; border-radius: 10px; text-align: center; }
        .score-display .value { font-size: 2rem; font-weight: 700; }
        .staff-card { cursor: pointer; transition: all 0.2s; }
        .staff-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .staff-card.active { border: 2px solid #247d57; background: #f0fdf4; }
        .stage-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center sidebar-header">
                    <?php if (!empty($institutionLogo)): ?>
                        <img src="<?php echo htmlspecialchars($institutionLogo); ?>" alt="Logo" style="max-height: 55px; margin-bottom: 10px;">
                    <?php else: ?>
                        <i class="fas fa-graduation-cap fa-2x mb-2" style="font-size: 2rem;"></i>
                    <?php endif; ?>
                    <h5 class="mb-0" style="font-weight: 800;"><?php echo htmlspecialchars($institutionName); ?></h5>
                    <?php if (!empty($institutionAddress)): ?>
                        <small class="d-block" style="max-width: 180px; margin: 5px auto 0; font-weight: 600;"><?php echo htmlspecialchars($institutionAddress); ?></small>
                    <?php endif; ?>
                    <small class="d-block mt-2"><?php echo ucfirst($adminRole); ?> Portal</small>
                </div>
                <div class="py-3">
                    <?php if (isset($_SESSION['is_evaluator']) && $_SESSION['is_evaluator']): ?>
                    <a href="evaluator-dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <?php if ($adminRole === 'registrar'): ?>
                    <a href="registrar-reports.php"><i class="fas fa-chart-bar"></i> Reports & Print</a>
                    <?php endif; ?>
                    <?php else: ?>
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                    <?php endif; ?>
                    <a href="evaluate-supervisor.php" class="active"><i class="fas fa-user-check"></i> My Evaluations</a>
                    <a href="logout.php" class="text-warning"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content p-4">
                <!-- Mobile Menu Button -->
                <button class="hamburger position-fixed" style="top: 10px; left: 10px;" onclick="toggleSidebar()">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show">
                        <?php echo $message['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <h2 class="mb-4">
                    <i class="fas fa-user-check me-2"></i>
                    <?php echo $adminRole === 'dean' ? APC_COMMITTEE_NAME : ucfirst($adminRole); ?> Evaluation Portal
                    <span class="badge bg-warning ms-2"><?php echo count($pendingEvals); ?> Pending</span>
                </h2>

                <!-- Bulk Approval for Registrar -->
                <?php if ($adminRole === 'registrar' && count($pendingEvals) > 0): ?>
                <div class="alert alert-success mb-4">
                    <form method="POST" onsubmit="return confirm('Are you sure you want to approve all <?php echo count($pendingEvals); ?> evaluations at once?');">
                        <button type="submit" name="bulk_approve_all" class="btn btn-success">
                            <i class="fas fa-check-double me-2"></i>Approve All (<?php echo count($pendingEvals); ?>)
                        </button>
                        <small class="ms-2 text-muted">This will approve all pending evaluations at once.</small>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Stage Info -->
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    Currently viewing: <strong><?php echo strtoupper($currentStage); ?></strong> stage evaluations.
                    <?php if ($adminRole === 'supervisor'): ?>
                        Staff will move to <?php echo APC_COMMITTEE_NAME; ?> after your evaluation.
                    <?php elseif ($adminRole === 'dean'): ?>
                        Staff will move to Registrar for final approval after your evaluation.
                    <?php elseif ($adminRole === 'registrar'): ?>
                        This is the final approval stage.
                    <?php endif; ?>
                </div>

                <div class="row">
                    <!-- Staff List Sidebar -->
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Staff to Evaluate</h5>
                            </div>
                            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                                <?php if (empty($pendingEvals)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                        <p>No pending evaluations</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($pendingEvals as $eval): ?>
                                        <div class="card staff-card mb-2 p-2 <?php echo ($evalId == $eval['id']) ? 'active' : ''; ?>"
                                             onclick="window.location.href='evaluate-supervisor.php?eval_id=<?php echo $eval['id']; ?>&stage=<?php echo $currentStage; ?>'">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['surname']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($eval['department']); ?></small>
                                                    <br><small><span class="badge bg-<?php
                                                        $cat = $eval['staff_category'] ?? 'academic';
                                                        if ($cat === 'non-teaching-junior') echo 'info';
                                                        elseif ($cat === 'non-teaching') echo 'warning';
                                                        else echo 'success';
                                                    ?>"><?php
                                                        $cat = $eval['staff_category'] ?? 'academic';
                                                        if ($cat === 'non-teaching-junior') echo 'Junior';
                                                        elseif ($cat === 'non-teaching') echo 'Non-Teach';
                                                        else echo 'Academic';
                                                    ?></span></small>
                                                </div>
                                                <span class="badge bg-<?php echo $eval['evaluation_stage'] === 'pending' ? 'secondary' : ($eval['evaluation_stage'] === 'hod' ? 'warning' : 'info'); ?> stage-badge">
                                                    <?php echo strtoupper($eval['evaluation_stage']); ?>
                                                </span>
                                            </div>
                                            <div class="mt-1">
                                                <small><strong>Score:</strong> <?php echo $eval['percentage']; ?>%</small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Rejected by Staff - Needs SO Decision -->
                        <?php if (!empty($rejectedByStaff) && ($adminRole === 'supervisor' || $adminRole === 'supervising-officer')): ?>
                        <div class="card mt-3">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Staff Rejections</h5>
                            </div>
                            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($rejectedByStaff as $eval): ?>
                                    <div class="card staff-card mb-2 p-2 <?php echo ($evalId == $eval['id']) ? 'active' : ''; ?>"
                                         onclick="window.location.href='evaluate-supervisor.php?eval_id=<?php echo $eval['id']; ?>&stage=supervising_officer_reject'">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['surname']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($eval['department']); ?></small>
                                                <br><small class="text-danger"><i class="fas fa-times-circle"></i> Rejected</small>
                                            </div>
                                            <span class="badge bg-danger">REJECTED</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Processed Evaluations -->
                        <?php if (!empty($processedEvals)): ?>
                        <div class="card mt-3">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Evaluation Processed</h5>
                            </div>
                            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($processedEvals as $eval): ?>
                                    <div class="card staff-card mb-2 p-2 <?php echo ($evalId == $eval['id']) ? 'active' : ''; ?>"
                                         onclick="window.location.href='evaluate-supervisor.php?eval_id=<?php echo $eval['id']; ?>&stage=<?php echo $currentStage; ?>'">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['surname']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($eval['department']); ?></small>
                                            </div>
                                            <span class="badge bg-<?php
                                                $stage = $eval['evaluation_stage'];
                                                if ($stage === 'pending') echo 'secondary';
                                                elseif ($stage === 'staff_review') echo 'info';
                                                elseif ($stage === 'supervising_officer_reject') echo 'danger';
                                                elseif (in_array($stage, ['registrar'])) echo 'warning';
                                                else echo 'success';
                                            ?> stage-badge">
                                                <?php echo strtoupper($stage); ?>
                                            </span>
                                        </div>
                                        <div class="mt-1">
                                            <small><strong>Score:</strong> <?php echo $eval['percentage']; ?>%</small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Evaluation Form -->
                    <div class="col-md-8">
                        <?php if ($selectedEval && is_array($selectedEval)): ?>
                            <form method="POST" id="evalForm">
                                <input type="hidden" name="eval_id" value="<?php echo $evalId; ?>">

                                <!-- Show Staff Rejection Reason if in supervising_officer_reject stage -->
                                <?php if ($currentStage === 'supervising_officer_reject' && !empty($selectedEval['staff_rejection_reason'])): ?>
                                <div class="card mb-4 border-danger">
                                    <div class="card-header bg-danger text-white">
                                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Staff Rejection Reason</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($selectedEval['staff_rejection_reason'])); ?></p>
                                    </div>
                                </div>

                                <!-- SO Decision Options -->
                                <div class="card mb-4 border-warning">
                                    <div class="card-header bg-warning text-dark">
                                        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Your Decision</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>The staff has rejected the evaluation. Please choose an option:</p>

                                        <!-- Option 1: Re-evaluate -->
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="so_decision" id="reevaluate" value="reevaluate" checked onchange="togglePushOption()">
                                            <label class="form-check-label" for="reevaluate">
                                                <strong>Re-evaluate</strong> - Complete a new evaluation for this staff
                                            </label>
                                        </div>

                                        <!-- Option 2: Push to Registrar -->
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="so_decision" id="pushToRegistrar" value="push" onchange="togglePushOption()">
                                            <label class="form-check-label" for="pushToRegistrar">
                                                <strong>Push to Registrar</strong> - Skip re-evaluation and send directly to Registrar with reason
                                            </label>
                                        </div>

                                        <!-- Push Reason (shown when push to registrar is selected) -->
                                        <div id="pushReasonDiv" style="display:none;">
                                            <label class="form-label">Reason for pushing to Registrar:</label>
                                            <textarea class="form-control" name="so_push_reason" rows="3" placeholder="Explain why you are not re-evaluating and are pushing to Registrar..."></textarea>
                                            <small class="text-muted">This reason will be visible to the Registrar.</small>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                function togglePushOption() {
                                    var pushRadio = document.getElementById('pushToRegistrar');
                                    var reasonDiv = document.getElementById('pushReasonDiv');
                                    if (pushRadio.checked) {
                                        reasonDiv.style.display = 'block';
                                    } else {
                                        reasonDiv.style.display = 'none';
                                    }
                                }
                                </script>
                                <?php endif; ?>

                                <!-- Staff Info -->
                                <?php if ($selectedStaff && is_array($selectedStaff)): ?>
                                <div class="card mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Evaluating: <?php echo htmlspecialchars(($selectedStaff['first_name'] ?? '') . ' ' . ($selectedStaff['surname'] ?? '')); ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3"><strong>Staff ID:</strong> <?php echo htmlspecialchars($selectedStaff['staff_id'] ?? 'N/A'); ?></div>
                                            <div class="col-md-3"><strong>Department:</strong> <?php echo htmlspecialchars($selectedStaff['department'] ?? 'N/A'); ?></div>
                                            <div class="col-md-3"><strong>Faculty:</strong> <?php echo htmlspecialchars($selectedStaff['faculty'] ?? 'N/A'); ?></div>
                                            <div class="col-md-3"><strong>Grade Level:</strong> <?php echo htmlspecialchars($selectedStaff['grade_level'] ?? 'N/A'); ?></div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-md-3">
                                                <strong>Category:</strong>
                                                <span class="badge bg-<?php
                                                    $cat = $selectedStaff['staff_category'] ?? 'academic';
                                                    if ($cat === 'non-teaching-junior') echo 'info';
                                                    elseif ($cat === 'non-teaching') echo 'warning';
                                                    else echo 'success';
                                                ?>">
                                                    <?php
                                                        $cat = $selectedStaff['staff_category'] ?? 'academic';
                                                        if ($cat === 'non-teaching-junior') echo 'Junior Staff (Level 5 & below)';
                                                        elseif ($cat === 'non-teaching') echo 'Non-Teaching Senior (Level 6+)';
                                                        else echo 'Academic Staff';
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-md-3"><strong>Your Rating:</strong> <span id="percentScore">0</span>%</div>
                                            <div class="col-md-3"><strong>Grade:</strong> <span id="gradeDisplay"><?php echo $selectedEval['performance_grade'] ?? '-'; ?></span></div>
                                            <div class="col-md-3"><strong>Status:</strong> <span id="statusDisplay"><?php echo $selectedEval['performance_status'] ?? '-'; ?></span></div>
                                        </div>
                                        <div class="mt-2">
                                            <span class="badge bg-<?php
                                                $stage = $selectedEval['evaluation_stage'] ?? 'pending';
                                                if ($stage === 'pending') echo 'secondary';
                                                elseif ($stage === 'staff_review') echo 'info';
                                                elseif ($stage === 'supervising_officer_reject') echo 'danger';
                                                elseif ($stage === 'registrar') echo 'warning';
                                                else echo 'success';
                                            ?>">
                                                Stage: <?php echo strtoupper($stage); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning">No staff selected. Please select a staff member from the list above.</div>
                                <?php endif; ?>

                                <!-- Live Score Display -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="score-display">
                                            <div class="value" id="totalScore"><?php echo $selectedEval['percentage']; ?>%</div>
                                            <div>Score (%)</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="score-display">
                                            <div class="value" id="avgScore"><?php echo $selectedEval['performance_grade']; ?></div>
                                            <div>Grade</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="score-display">
                                            <div class="value" id="percentScore"><?php echo $selectedEval['percentage']; ?>%</div>
                                            <div>Percentage</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="score-display" style="background: linear-gradient(135deg, #10b981, #059669);">
                                            <div class="value" id="gradeDisplay"><?php echo $selectedEval['performance_grade']; ?></div>
                                            <div>Grade</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Staff Self-Evaluation Summary -->
                                <?php
                                // Get staff's self-evaluation scores
                                $staffEvalScores = [];
                                if ($selectedEval && isset($selectedEval['responses']) && !empty($selectedEval['responses'])) {
                                    $staffResponses = is_array($selectedEval['responses']) ? $selectedEval['responses'] : json_decode($selectedEval['responses'], true);
                                    if (is_array($staffResponses)) {
                                        foreach ($staffResponses as $key => $value) {
                                            if (is_numeric($value)) {
                                                $staffEvalScores[$key] = intval($value);
                                            }
                                        }
                                    }
                                }
                                // Also check individual columns
                                if ($selectedEval) {
                                    $scoreColumns = ['teaching_1', 'teaching_2', 'teaching_3', 'teaching_4', 'teaching_5', 'teaching_6',
                                                    'research_1', 'research_2', 'research_3', 'research_4', 'research_5',
                                                    'admin_1', 'admin_2', 'admin_3', 'admin_4', 'admin_5',
                                                    'community_1', 'community_2', 'community_3',
                                                    'professional_1', 'professional_2', 'professional_3', 'professional_4'];
                                    foreach ($scoreColumns as $col) {
                                        if (isset($selectedEval[$col]) && is_numeric($selectedEval[$col])) {
                                            $staffEvalScores[$col] = intval($selectedEval[$col]);
                                        }
                                    }
                                }
                                ?>
                                <?php if (!empty($staffEvalScores) && ($adminRole === 'supervisor' || $adminRole === 'supervising-officer')): ?>
                                <div class="card mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Staff Self-Evaluation Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <strong>Note:</strong> Only your Supervising Officer evaluation determines the final score and grade.
                                                <small class="text-muted">The staff's self-evaluation is shown for reference only.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Supervising Officer Evaluation Results (shown to Registrar) -->
                                <?php if (($adminRole === 'registrar') && !empty($selectedEval['percentage'])): ?>
                                <div class="card mb-4 border-warning">
                                    <div class="card-header bg-warning text-dark">
                                        <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>FINAL GRADE (From Supervising Officer Evaluation)</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-md-3">
                                                <div class="score-display">
                                                    <div class="value"><?php echo $selectedEval['percentage']; ?>%</div>
                                                    <div>Final Score</div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="score-display" style="background: linear-gradient(135deg, #10b981, #059669);">
                                                    <div class="value"><?php echo $selectedEval['performance_grade']; ?></div>
                                                    <div>Final Grade</div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="score-display">
                                                    <div class="value"><?php echo $selectedEval['performance_status']; ?></div>
                                                    <div>Performance Status</div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="score-display">
                                                    <div class="value"><?php echo $selectedEval['supervisor_name'] ?? 'Supervising Officer'; ?></div>
                                                    <div>Evaluated By</div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if (!empty($selectedEval['supervisor_remarks'])): ?>
                                        <div class="alert alert-info">
                                            <strong><i class="fas fa-comment me-2"></i>Supervising Officer Remarks:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($selectedEval['supervisor_remarks'])); ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="alert alert-success mt-3">
                                            <i class="fas fa-star me-2"></i><strong>This is the final grade.</strong> The Supervising Officer evaluation score and grade shown above will be the final grade for this staff member. Your approval confirms this grade.
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Rating Questions (Only show if not already answered) -->
                                <div class="card mb-4">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0"><i class="fas fa-star me-2"></i>Performance Ratings</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($adminRole === 'supervisor' || $adminRole === 'supervising-officer' || $adminRole === 'hod'): ?>
                                        <p class="text-muted">Rate the staff member on each criterion (1-5)</p>

                                        <!-- Dynamic Categories - Uses user's custom categories from database -->
                                        <?php if (!empty($questionsByCategory)): ?>
                                            <?php foreach ($questionsByCategory as $categoryName => $categoryQuestions): ?>
                                            <div class="mb-4">
                                                <h6 class="text-primary border-bottom pb-2"><?php echo htmlspecialchars($categoryName); ?></h6>
                                                <?php foreach ($categoryQuestions as $q): ?>
                                                <div class="question-item">
                                                    <label class="form-label fw-bold"><?php echo htmlspecialchars($q['label']); ?></label>
                                                    <div>
                                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                                        <label class="rating-label">
                                                            <input type="radio" name="<?php echo $q['name']; ?>" value="<?php echo $i; ?>"
                                                                   onchange="calculateScores()" <?php echo ($selectedEval[$q['name']] ?? '') == $i ? 'checked' : ''; ?>>
                                                            <span><?php echo $i; ?></span>
                                                        </label>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; // End dynamic categories ?>
                                        <?php endif; // End show questions only for HOD ?>

                                        <!-- Show message if no questions found for selected staff category -->
                                        <?php if (empty($questionsByCategory) && ($adminRole === 'supervisor' || $adminRole === 'supervising-officer' || $adminRole === 'hod')): ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            No evaluation questions found for this staff category.
                                            Please contact the administrator to add questions for
                                            <strong><?php echo htmlspecialchars($selectedStaff['staff_category'] ?? 'this category'); ?></strong>.
                                        </div>
                                        <?php endif; ?>

                                        <!-- Show message if no questions (Dean/Registrar) -->
                                        <?php if ($adminRole !== 'supervisor' && $adminRole !== 'hod'): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            The staff has completed their self-evaluation. Please review the scores and add your comments below.
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Remarks -->
                                <div class="card mb-4">
                                    <div class="card-header bg-<?php echo $adminRole === 'registrar' ? 'warning' : 'primary'; ?> text-white">
                                        <h5 class="mb-0">
                                            <i class="fas fa-comment me-2"></i>
                                            <?php echo $adminRole === 'registrar' ? 'Registrar Remarks & Approval' : 'Supervisor Remarks'; ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($adminRole === 'supervisor' || $adminRole === 'admin' || $adminRole === 'super_admin'): ?>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Supervisor Name</label>
                                                <input type="text" class="form-control" name="supervisor_name" value="<?php echo htmlspecialchars($selectedEval['supervisor_name'] ?? $adminName); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Designation</label>
                                                <input type="text" class="form-control" name="supervisor_designation" value="<?php echo htmlspecialchars($selectedEval['supervisor_designation'] ?? 'Supervising Officer'); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Overall Rating</label>
                                                <select class="form-select" name="overall_rating">
                                                    <option value="">Select Rating</option>
                                                    <option value="Outstanding" <?php echo ($selectedEval['overall_rating'] ?? '') == 'Outstanding' ? 'selected' : ''; ?>>Outstanding</option>
                                                    <option value="Excellent" <?php echo ($selectedEval['overall_rating'] ?? '') == 'Excellent' ? 'selected' : ''; ?>>Excellent</option>
                                                    <option value="Very Good" <?php echo ($selectedEval['overall_rating'] ?? '') == 'Very Good' ? 'selected' : ''; ?>>Very Good</option>
                                                    <option value="Good" <?php echo ($selectedEval['overall_rating'] ?? '') == 'Good' ? 'selected' : ''; ?>>Good</option>
                                                    <option value="Fair" <?php echo ($selectedEval['overall_rating'] ?? '') == 'Fair' ? 'selected' : ''; ?>>Fair</option>
                                                    <option value="Poor" <?php echo ($selectedEval['overall_rating'] ?? '') == 'Poor' ? 'selected' : ''; ?>>Poor</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Recommendation</label>
                                                <select class="form-select" name="recommendation">
                                                    <option value="">Select Recommendation</option>
                                                    <option value="Promoted" <?php echo ($selectedEval['recommendation'] ?? '') == 'Promoted' ? 'selected' : ''; ?>>Promoted</option>
                                                    <option value="Confirmed" <?php echo ($selectedEval['recommendation'] ?? '') == 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                    <option value="Continued" <?php echo ($selectedEval['recommendation'] ?? '') == 'Continued' ? 'selected' : ''; ?>>Continued</option>
                                                    <option value="Probation" <?php echo ($selectedEval['recommendation'] ?? '') == 'Probation' ? 'selected' : ''; ?>>Probation</option>
                                                </select>
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label">Remarks</label>
                                                <textarea class="form-control" name="supervisor_remarks" rows="3"><?php echo htmlspecialchars($selectedEval['supervisor_remarks'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Digital Signature</label>
                                                <input type="text" class="form-control" name="supervisor_signature" value="<?php echo htmlspecialchars($selectedEval['supervisor_signature'] ?? $adminName); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date</label>
                                                <input type="date" class="form-control" name="supervisor_date" value="<?php echo $selectedEval['supervisor_date'] ?? date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                        <?php elseif ($adminRole === 'registrar'): ?>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Registrar Name</label>
                                                <input type="text" class="form-control" name="registrar_name" value="<?php echo htmlspecialchars($selectedEval['registrar_name'] ?? $adminName); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Approval Status</label>
                                                <select class="form-select" name="approval_status">
                                                    <option value="Approved" <?php echo ($selectedEval['approval_status'] ?? '') == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                                    <option value="Pending" <?php echo ($selectedEval['approval_status'] ?? '') == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="Rejected" <?php echo ($selectedEval['approval_status'] ?? '') == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                </select>
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label">Registrar Remarks</label>
                                                <textarea class="form-control" name="registrar_remarks" rows="3"><?php echo htmlspecialchars($selectedEval['registrar_remarks'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Digital Signature</label>
                                                <input type="text" class="form-control" name="registrar_signature" value="<?php echo htmlspecialchars($selectedEval['registrar_signature'] ?? $adminName); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date</label>
                                                <input type="date" class="form-control" name="registrar_date" value="<?php echo $selectedEval['registrar_date'] ?? date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Submit Buttons -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <?php if ($currentStage === 'supervising_officer_reject'): ?>
                                            <!-- When staff has rejected - show decision buttons -->
                                            <div class="d-flex gap-2 flex-wrap">
                                                <button type="button" class="btn btn-success btn-lg" onclick="handleSODecision()">
                                                    <i class="fas fa-check me-2"></i>Submit Decision
                                                </button>
                                                <a href="evaluate-supervisor.php" class="btn btn-secondary btn-lg">
                                                    <i class="fas fa-times me-2"></i>Cancel
                                                </a>
                                            </div>
                                            <small class="text-muted mt-2 d-block">Select your decision above (Re-evaluate or Push to Registrar) before submitting.</small>
                                        <?php else: ?>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <?php if ($adminRole === 'registrar'): ?>
                                                <button type="submit" name="save_and_next" class="btn btn-success btn-lg">
                                                    <i class="fas fa-check-circle me-2"></i>Final Approval
                                                </button>
                                                <?php elseif ($adminRole === 'supervising-officer' || $adminRole === 'supervisor'): ?>
                                                <button type="submit" name="save_and_next" class="btn btn-success btn-lg">
                                                    <i class="fas fa-arrow-right me-2"></i>Save & Next (Pass to <?php echo htmlspecialchars($selectedStaff['first_name'] ?? 'Staff'); ?>)
                                                </button>
                                                <?php elseif ($adminRole === 'registrar'): ?>
                                                <button type="submit" name="save_and_next" class="btn btn-success btn-lg">
                                                    <i class="fas fa-check-circle me-2"></i>Final Approval
                                                </button>
                                                <?php else: ?>
                                                <button type="submit" name="save_and_next" class="btn btn-success btn-lg">
                                                    <i class="fas fa-arrow-right me-2"></i>Save & Next (Pass to Registrar)
                                                </button>
                                                <?php endif; ?>
                                                <a href="evaluate-supervisor.php" class="btn btn-secondary btn-lg">
                                                    <i class="fas fa-times me-2"></i>Cancel
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <script>
                                function handleSODecision() {
                                    var pushRadio = document.getElementById('pushToRegistrar');
                                    var reasonField = document.querySelector('textarea[name="so_push_reason"]');

                                    if (pushRadio && pushRadio.checked) {
                                        // Check if reason is provided
                                        if (!reasonField.value.trim()) {
                                            alert('Please provide a reason for pushing to Registrar');
                                            reasonField.focus();
                                            return;
                                        }
                                        // Submit as push to registrar
                                        document.getElementById('evalForm').innerHTML += '<input type="hidden" name="push_to_registrar" value="1">';
                                        document.getElementById('evalForm').submit();
                                    } else {
                                        // Normal re-evaluate - submit normally
                                        document.getElementById('evalForm').submit();
                                    }
                                }
                                </script>
                            </form>
                        <?php else: ?>
                            <div class="card mb-4">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Select Staff to Evaluate</h5>
                                </div>
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-hand-pointer fa-4x text-muted mb-3"></i>
                                    <h4>Select a staff member to evaluate</h4>
                                    <p class="text-muted">Click on a staff member from the list on the left to begin evaluation</p>
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Evaluation questions will appear here</strong> once you select a staff member.
                                        The questions will be filtered based on the staff member's category (Academic, Non-Teaching Senior, or Junior Staff).
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <script>
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
        document.querySelector('.sidebar-overlay').classList.toggle('active');
        document.querySelector('.hamburger').classList.toggle('active');
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelector('.sidebar').classList.remove('active');
            document.querySelector('.sidebar-overlay').classList.remove('active');
            document.querySelector('.hamburger').classList.remove('active');
        }
    });

    function calculateScores() {
        let total = 0;
        let count = 0;
        const radios = document.querySelectorAll('input[type="radio"]:checked');
        radios.forEach(radio => {
            total += parseInt(radio.value);
            count++;
        });

        // Calculate percentage based on actual questions answered (each question is max 5 points)
        const maxPossible = count * 5;
        const percentage = maxPossible > 0 ? ((total / maxPossible) * 100).toFixed(1) : 0;

        // Update displays to show percentage
        document.getElementById('totalScore').textContent = percentage + '%';
        document.getElementById('avgScore').textContent = count > 0 ? (total / count).toFixed(1) : '0';
        document.getElementById('percentScore').textContent = percentage;

        // Calculate grade based on HOD percentage only (not combined)
        let grade = '-';
        let status = '-';
        if (percentage >= 90) { grade = 'Outstanding'; status = 'Excellent Performance'; }
        else if (percentage >= 80) { grade = 'Excellent'; status = 'Very Good Performance'; }
        else if (percentage >= 70) { grade = 'Very Good'; status = 'Good Performance'; }
        else if (percentage >= 60) { grade = 'Good'; status = 'Satisfactory'; }
        else if (percentage >= 50) { grade = 'Fair'; status = 'Needs Improvement'; }
        else if (percentage > 0) { grade = 'Poor'; status = 'Unsatisfactory'; }

        document.getElementById('gradeDisplay').textContent = grade;
        document.getElementById('statusDisplay').textContent = status;
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