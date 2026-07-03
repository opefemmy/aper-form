<?php
require_once 'config.php';
requireStaffLogin();

$staff = getCurrentStaff();

// Get settings
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Check if evaluation already exists for this staff
$stmt = $pdo->prepare("SELECT * FROM evaluations WHERE staff_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$staff['id']]);
$existingEval = $stmt->fetch();

// Get active academic session
$stmt = $pdo->query("SELECT * FROM academic_sessions WHERE is_active = 1 LIMIT 1");
$activeSession = $stmt->fetch();

// Handle form submission
$submitMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    $staffId = $_POST['staff_id'] ?? 0;
    $academicSessionId = $_POST['academic_session_id'] ?? 0;
    $evaluationYear = $_POST['evaluation_year'] ?? date('Y');

    // Collect all scores
    $scores = [
        'teaching_1' => $_POST['teaching_1'] ?? 0,
        'teaching_2' => $_POST['teaching_2'] ?? 0,
        'teaching_3' => $_POST['teaching_3'] ?? 0,
        'teaching_4' => $_POST['teaching_4'] ?? 0,
        'teaching_5' => $_POST['teaching_5'] ?? 0,
        'teaching_6' => $_POST['teaching_6'] ?? 0,
        'research_1' => $_POST['research_1'] ?? 0,
        'research_2' => $_POST['research_2'] ?? 0,
        'research_3' => $_POST['research_3'] ?? 0,
        'research_4' => $_POST['research_4'] ?? 0,
        'research_5' => $_POST['research_5'] ?? 0,
        'admin_1' => $_POST['admin_1'] ?? 0,
        'admin_2' => $_POST['admin_2'] ?? 0,
        'admin_3' => $_POST['admin_3'] ?? 0,
        'admin_4' => $_POST['admin_4'] ?? 0,
        'admin_5' => $_POST['admin_5'] ?? 0,
        'community_1' => $_POST['community_1'] ?? 0,
        'community_2' => $_POST['community_2'] ?? 0,
        'community_3' => $_POST['community_3'] ?? 0,
        'professional_1' => $_POST['professional_1'] ?? 0,
        'professional_2' => $_POST['professional_2'] ?? 0,
        'professional_3' => $_POST['professional_3'] ?? 0,
        'professional_4' => $_POST['professional_4'] ?? 0,
    ];

    // Calculate totals
    $totalScore = array_sum($scores);
    $questionCount = 23;
    $averageScore = $totalScore / $questionCount;
    $percentage = round(($totalScore / 115) * 100, 2);

    // Calculate grade
    $gradeResult = calculateGrade($percentage);

    try {
        // Check if evaluation exists
        $checkStmt = $pdo->prepare("SELECT id FROM evaluations WHERE staff_id = ? AND academic_session_id = ? AND evaluation_year = ?");
        $checkStmt->execute([$staffId, $academicSessionId, $evaluationYear]);
        $existingId = $checkStmt->fetch();

        if ($existingId) {
            // Update existing
            $sql = "UPDATE evaluations SET
                teaching_1=?, teaching_2=?, teaching_3=?, teaching_4=?, teaching_5=?, teaching_6=?,
                research_1=?, research_2=?, research_3=?, research_4=?, research_5=?,
                admin_1=?, admin_2=?, admin_3=?, admin_4=?, admin_5=?,
                community_1=?, community_2=?, community_3=?,
                professional_1=?, professional_2=?, professional_3=?, professional_4=?,
                total_score=?, average_score=?, percentage=?, performance_grade=?, performance_status=?,
                status='submitted', updated_at=NOW()
                WHERE id=?";
            $updateStmt = $pdo->prepare($sql);
            $updateStmt->execute([
                ...array_values($scores), $totalScore, $averageScore, $percentage, $gradeResult[0], $gradeResult[1],
                $existingId['id']
            ]);
            $submitMessage = 'Evaluation updated and submitted successfully!';
        } else {
            // Insert new
            $sql = "INSERT INTO evaluations (
                staff_id, academic_session_id, evaluation_year,
                teaching_1, teaching_2, teaching_3, teaching_4, teaching_5, teaching_6,
                research_1, research_2, research_3, research_4, research_5,
                admin_1, admin_2, admin_3, admin_4, admin_5,
                community_1, community_2, community_3,
                professional_1, professional_2, professional_3, professional_4,
                total_score, average_score, percentage, performance_grade, performance_status, status
            ) VALUES (?, ?, ?, " . str_repeat('?,', 23) . "?, ?, ?, ?, ?, 'submitted')";
            $insertStmt = $pdo->prepare($sql);
            $insertStmt->execute([
                $staffId, $academicSessionId, $evaluationYear,
                ...array_values($scores), $totalScore, $averageScore, $percentage, $gradeResult[0], $gradeResult[1]
            ]);
            $submitMessage = 'Evaluation submitted successfully!';
        }
        // Refresh the evaluation data
        $stmt = $pdo->prepare("SELECT * FROM evaluations WHERE staff_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$staff['id']]);
        $existingEval = $stmt->fetch();
    } catch (Exception $e) {
        $submitMessage = 'Error: ' . $e->getMessage();
    }
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Evaluation - Staff Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: #1e3a8a; --secondary-blue: #3b82f6; }
        body { background: #f3f4f6; }
        .top-bar { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); color: white; padding: 1rem 0; }
        .staff-info-card { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .score-card { background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white; padding: 1.5rem; border-radius: 12px; text-align: center; }
        .score-card .value { font-size: 2.5rem; font-weight: 700; }
        .question-item { background: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #e2e8f0; }
        .rating-label { padding: 0.5rem 0.75rem; background: #f8fafc; border-radius: 20px; cursor: pointer; margin-right: 0.25rem; display: inline-block; }
        .rating-label:hover { background: #dbeafe; }
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
                <div class="col-md-3"><strong>Session:</strong> <?php echo htmlspecialchars($activeSession['session_name'] ?? 'N/A'); ?></div>
            </div>
        </div>

        <!-- Evaluation Status -->
        <?php if ($existingEval && $existingEval['status'] !== 'draft'): ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="score-card">
                    <div class="value"><?php echo $existingEval['total_score']; ?>/115</div>
                    <div>Total Score</div>
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

        <div class="alert alert-info">
            <i class="fas fa-check-circle me-2"></i>
            Your evaluation has been submitted. Please contact your supervisor for any changes.
        </div>
        <?php else: ?>

        <!-- Self Evaluation Form -->
        <form method="POST" id="evalForm">
            <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
            <input type="hidden" name="academic_session_id" value="<?php echo $activeSession['id'] ?? 0; ?>">
            <input type="hidden" name="evaluation_year" value="<?php echo $settings['evaluation_year'] ?? date('Y'); ?>">

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Self-Evaluation Form</h5>
                </div>
                <div class="card-body">

                    <!-- Teaching Performance -->
                    <div class="mb-4">
                        <h6 class="text-primary border-bottom pb-2">Teaching Performance</h6>
                        <?php $teaching = [['t1','Lecture Delivery'],['t2','Class Attendance'],['t3','Student Engagement'],['t4','Course Preparation'],['t5','Course Coverage'],['t6','Time Management']];
                        foreach ($teaching as $q): ?>
                        <div class="question-item">
                            <label class="form-label fw-bold"><?php echo $q[1]; ?></label>
                            <div>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <label class="rating-label">
                                    <input type="radio" name="teaching_<?php echo substr($q[0],1); ?>" value="<?php echo $i; ?>" onchange="calculateScores()" required>
                                    <span><?php echo $i; ?></span>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Research Performance -->
                    <div class="mb-4">
                        <h6 class="text-primary border-bottom pb-2">Research Performance</h6>
                        <?php $research = [['r1','Publications'],['r2','Conferences'],['r3','Research Grants'],['r4','Journal Articles'],['r5','Innovations']];
                        foreach ($research as $q): ?>
                        <div class="question-item">
                            <label class="form-label fw-bold"><?php echo $q[1]; ?></label>
                            <div>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <label class="rating-label">
                                    <input type="radio" name="research_<?php echo substr($q[0],1); ?>" value="<?php echo $i; ?>" onchange="calculateScores()" required>
                                    <span><?php echo $i; ?></span>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Administrative Duties -->
                    <div class="mb-4">
                        <h6 class="text-primary border-bottom pb-2">Administrative Duties</h6>
                        <?php $admin = [['a1','Attendance'],['a2','Punctuality'],['a3','Leadership'],['a4','Teamwork'],['a5','Record Keeping']];
                        foreach ($admin as $q): ?>
                        <div class="question-item">
                            <label class="form-label fw-bold"><?php echo $q[1]; ?></label>
                            <div>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <label class="rating-label">
                                    <input type="radio" name="admin_<?php echo substr($q[0],1); ?>" value="<?php echo $i; ?>" onchange="calculateScores()" required>
                                    <span><?php echo $i; ?></span>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Community Service -->
                    <div class="mb-4">
                        <h6 class="text-primary border-bottom pb-2">Community Service</h6>
                        <?php $community = [['c1','Community Development'],['c2','Committee Participation'],['c3','Institutional Representation']];
                        foreach ($community as $q): ?>
                        <div class="question-item">
                            <label class="form-label fw-bold"><?php echo $q[1]; ?></label>
                            <div>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <label class="rating-label">
                                    <input type="radio" name="community_<?php echo substr($q[0],1); ?>" value="<?php echo $i; ?>" onchange="calculateScores()" required>
                                    <span><?php echo $i; ?></span>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Professional Development -->
                    <div class="mb-4">
                        <h6 class="text-primary border-bottom pb-2">Professional Development</h6>
                        <?php $professional = [['p1','Workshops'],['p2','Training'],['p3','Certifications'],['p4','Seminars']];
                        foreach ($professional as $q): ?>
                        <div class="question-item">
                            <label class="form-label fw-bold"><?php echo $q[1]; ?></label>
                            <div>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <label class="rating-label">
                                    <input type="radio" name="professional_<?php echo substr($q[0],1); ?>" value="<?php echo $i; ?>" onchange="calculateScores()" required>
                                    <span><?php echo $i; ?></span>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" name="submit_evaluation" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane me-2"></i>Submit Evaluation
                </button>
            </div>
        </form>

        <?php endif; ?>
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
    }
    </script>
</body>
</html>