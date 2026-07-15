<?php
require_once 'config.php';
requireStaffLogin();
require_once 'mail.php';

$pdo = getDBConnection();
$staffId = $_SESSION['staff_id'];
$staffName = $_SESSION['staff_name'];

// Get settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$instName = $settings['institution_name'] ?? 'Institution';

// Get staff category for this staff
$staffStmt = $pdo->prepare("SELECT staff_category FROM staff WHERE id = ?");
$staffStmt->execute([$staffId]);
$staffData = $staffStmt->fetch();
$staffCategory = $staffData['staff_category'] ?? 'academic';

// Get SO evaluation questions for this staff category
$soQuestions = [];
$soQStmt = $pdo->prepare("SELECT * FROM evaluation_questions WHERE is_active = 1 AND target_staff_category = 'hod' ORDER BY category, id");
$soQStmt->execute();
$soQuestions = $soQStmt->fetchAll();

// Get current year
$currentYear = date('Y');

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $evalId = intval($_POST['eval_id'] ?? 0);
    $consent = $_POST['consent'] ?? '';
    $comments = sanitize($_POST['staff_comments'] ?? '');

    if (empty($evalId)) {
        $message = '<div class="alert alert-danger">Invalid evaluation ID</div>';
    } elseif (!in_array($consent, ['consent', 'reject'])) {
        $message = '<div class="alert alert-danger">Please select either Consent or Reject</div>';
    } else {
        // Get the evaluation
        $stmt = $pdo->prepare("SELECT * FROM evaluations WHERE id = ? AND staff_id = ?");
        $stmt->execute([$evalId, $staffId]);
        $eval = $stmt->fetch();

        if (!$eval) {
            $message = '<div class="alert alert-danger">Evaluation not found</div>';
        } else {
            if ($consent === 'consent') {
                // Staff consents - move to Registrar
                $stmt = $pdo->prepare("UPDATE evaluations SET evaluation_stage = 'registrar', staff_consent = 'consented', staff_consent_date = NOW() WHERE id = ?");
                $stmt->execute([$evalId]);
                $message = '<div class="alert alert-success">You have consented to the evaluation. It has been sent to the Registrar for final approval.</div>';

                // Send email notification to registrar
                try {
                    sendEvaluationStageNotification('registrar', $eval, $staff);
                } catch (Exception $e) {
                    error_log("Email notification error: " . $e->getMessage());
                }
            } else {
                // Staff rejects - goes back to Supervising Officer for review
                $stmt = $pdo->prepare("UPDATE evaluations SET evaluation_stage = 'supervising_officer_reject', staff_consent = 'rejected', staff_rejection_reason = ?, staff_consent_date = NOW() WHERE id = ?");
                $stmt->execute([$comments, $evalId]);
                $message = '<div class="alert alert-warning">You have rejected the evaluation. Your concerns have been sent to the Supervising Officer for review.</div>';

                // Send email notification to supervising officer
                try {
                    $soStmt = $pdo->prepare("SELECT * FROM staff WHERE department = ? AND (evaluator_type = 'Supervising Officer' OR evaluator_type = 'supervisor') LIMIT 1");
                    $soStmt->execute([$staff['department']]);
                    $supervisor = $soStmt->fetch();

                    if ($supervisor) {
                        sendEvaluationStageNotification('supervising_officer_reject', $eval, $staff, $supervisor);
                    }
                } catch (Exception $e) {
                    error_log("Email notification error: " . $e->getMessage());
                }
            }
        }
    }
}

// Get evaluations waiting for staff review (stage = 'staff_review')
$stmt = $pdo->prepare("SELECT * FROM evaluations WHERE staff_id = ? AND evaluation_year = ? AND evaluation_stage = 'staff_review' AND status = 'submitted'");
$stmt->execute([$staffId, $currentYear]);
$pendingReviews = $stmt->fetchAll();

// Get evaluation history
$stmt = $pdo->query("SELECT * FROM evaluations WHERE staff_id = $staffId AND evaluation_year = $currentYear ORDER BY created_at DESC");
$evalHistory = $stmt->fetchAll();

$messageData = getMessage();
if ($messageData && is_array($messageData)) {
    $message = '<div class="alert alert-' . ($messageData['type'] ?? 'success') . '">' . ($messageData['message'] ?? '') . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Review - <?php echo htmlspecialchars($instName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: <?php echo $settings['primary_color'] ?? '#308a1e'; ?>; }
        body { background: #f5f5f5; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .btn-primary { background: var(--primary); border: none; }
        .btn-primary:hover { background: var(--primary); opacity: 0.9; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-clipboard-check"></i> Staff Review - <?php echo htmlspecialchars($instName); ?></h2>
                    <div>
                        <a href="staff-dashboard.php" class="btn btn-secondary"><i class="fas fa-home"></i> Dashboard</a>
                        <a href="staff-logout.php" class="btn btn-outline-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>

                <?php echo $message; ?>

                <!-- Pending Reviews -->
                <h4 class="mb-3"><i class="fas fa-clock"></i> Evaluations Awaiting Your Review</h4>

                <?php if (empty($pendingReviews)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No evaluations are currently waiting for your review.
                </div>
                <?php else: ?>
                    <?php foreach ($pendingReviews as $eval): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Supervising Officer Evaluation Review</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4"><strong>Score:</strong> <?php echo $eval['percentage']; ?>%</div>
                                <div class="col-md-4"><strong>Grade:</strong> <?php echo $eval['performance_grade']; ?></div>
                                <div class="col-md-4"><strong>Status:</strong> <?php echo $eval['performance_status']; ?></div>
                            </div>

                            <div class="mb-3">
                                <strong>Supervising Officer:</strong> <?php echo htmlspecialchars($eval['supervisor_name'] ?? 'N/A'); ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($eval['supervisor_designation'] ?? ''); ?></small>
                            </div>

                            <?php if (!empty($eval['supervisor_remarks'])): ?>
                            <div class="mb-3">
                                <strong>Supervising Officer Remarks:</strong>
                                <p class="bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($eval['supervisor_remarks'])); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($eval['overall_rating'])): ?>
                            <div class="mb-3">
                                <strong>Overall Rating:</strong> <?php echo htmlspecialchars($eval['overall_rating']); ?>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($eval['recommendation'])): ?>
                            <div class="mb-3">
                                <strong>Recommendation:</strong> <?php echo htmlspecialchars($eval['recommendation']); ?>
                            </div>
                            <?php endif; ?>

                            <hr>

                            <!-- Supervising Officer's Evaluation of Each Question -->
                            <h5><i class="fas fa-list-check me-2"></i>Supervising Officer's Evaluation</h5>
                            <?php
                            // Get SO responses from evaluation
                            $soResponses = [];
                            if (!empty($eval['responses'])) {
                                $allResponses = is_array($eval['responses']) ? $eval['responses'] : json_decode($eval['responses'], true);
                                if (is_array($allResponses)) {
                                    foreach ($allResponses as $key => $value) {
                                        if (strpos($key, 'so_') === 0) {
                                            $soResponses[$key] = $value;
                                        }
                                    }
                                }
                            }

                            if (!empty($soResponses) && !empty($soQuestions)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead class="table-primary">
                                        <tr>
                                            <th>Category</th>
                                            <th>Question</th>
                                            <th>Score (out of 5)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($soQuestions as $sq): ?>
                                        <?php
                                        $fieldName = 'so_q_' . $sq['id'];
                                        $score = $soResponses[$fieldName] ?? null;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sq['category']); ?></td>
                                            <td><?php echo htmlspecialchars($sq['question_text']); ?></td>
                                            <td>
                                                <?php if ($score !== null): ?>
                                                <span class="badge bg-<?php echo $score >= 4 ? 'success' : ($score >= 3 ? 'warning' : 'danger'); ?> fs-6">
                                                    <?php echo $score; ?>/5
                                                </span>
                                                <?php else: ?>
                                                <span class="text-muted">Not scored</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php elseif (empty($soResponses)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No individual question scores available yet. The Supervising Officer has submitted the overall evaluation.
                            </div>
                            <?php endif; ?>

                            <hr>

                            <form method="POST" class="mt-4">
                                <input type="hidden" name="eval_id" value="<?php echo $eval['id']; ?>">

                                <h5>Your Decision</h5>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="consent" id="consent" value="consent" required>
                                        <label class="form-check-label text-success" for="consent">
                                            <i class="fas fa-check-circle"></i> <strong>I Consent</strong> - I agree with this evaluation
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="consent" id="reject" value="reject" required>
                                        <label class="form-check-label text-danger" for="reject">
                                            <i class="fas fa-times-circle"></i> <strong>I Reject</strong> - I disagree with this evaluation
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3" id="rejectReason" style="display:none;">
                                    <label class="form-label"><strong>Please state your reasons for rejection:</strong></label>
                                    <textarea class="form-control" name="staff_comments" rows="4" placeholder="Explain why you disagree with the evaluation..."></textarea>
                                    <small class="text-muted">Your concerns will be sent to the Supervising Officer for review.</small>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane"></i> Submit Decision
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Evaluation History -->
                <h4 class="mb-3 mt-4"><i class="fas fa-history"></i> Evaluation History</h4>
                <div class="card">
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Stage</th>
                                    <th>Score</th>
                                    <th>Grade</th>
                                    <th>Your Consent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($evalHistory as $hist): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($hist['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $hist['evaluation_stage'] === 'pending' ? 'secondary' :
                                            ($hist['evaluation_stage'] === 'staff_review' ? 'info' :
                                            ($hist['evaluation_stage'] === 'supervising_officer_reject' ? 'danger' :
                                            ($hist['evaluation_stage'] === 'registrar' ? 'warning' : 'success')));
                                        ?>">
                                            <?php echo strtoupper($hist['evaluation_stage']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $hist['percentage'] ?? '-'; ?>%</td>
                                    <td><?php echo $hist['performance_grade'] ?? '-'; ?></td>
                                    <td>
                                        <?php if ($hist['staff_consent'] === 'consented'): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Consented</span>
                                        <?php elseif ($hist['staff_consent'] === 'rejected'): ?>
                                            <span class="badge bg-danger"><i class="fas fa-times"></i> Rejected</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide rejection reason based on selection
        document.querySelectorAll('input[name="consent"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                var rejectDiv = document.getElementById('rejectReason');
                if (document.getElementById('reject').checked) {
                    rejectDiv.style.display = 'block';
                } else {
                    rejectDiv.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>