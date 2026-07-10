<?php
require_once 'config.php';

// Check if evaluator (HOD/Dean/Registrar) is logged in
if (isEvaluatorLoggedIn()) {
    // Evaluator is logged in - set up the page for them
    $evaluatorType = getEvaluatorType();
    $evaluatorId = $_SESSION['staff_id'];
    $evaluatorName = $_SESSION['staff_name'];
    $adminRole = strtolower($evaluatorType); // 'hod', 'dean', or 'registrar'
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

    // Only allow supervisors, deans, and registrars
    if (!in_array($adminRole, ['supervisor', 'dean', 'registrar', 'super_admin', 'admin'])) {
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
$primaryColor = $settings['primary_color'] ?? '#308a1e';

$message = getMessage();
$evalId = $_GET['eval_id'] ?? null;
$staffId = $_GET['staff_id'] ?? null;

// Determine what stage of evaluation we're handling
$currentStage = $_GET['stage'] ?? 'hod';
if ($adminRole === 'dean') {
    $currentStage = 'dean';
} elseif ($adminRole === 'registrar') {
    $currentStage = 'registrar';
}

// Get staff evaluator profile if exists (for staff who are also HOD/Dean)
$evaluatorProfile = null;
$stmt = $pdo->prepare("SELECT * FROM staff WHERE email = ? AND evaluator_type IN ('HOD', 'Dean', 'Registrar')");
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

if ($adminRole === 'supervisor' || $adminRole === 'hod') {
    // HOD sees only staff in their department (NOT themselves)
    if (!empty($evaluatorDept)) {
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE status = 'active' AND department = ? AND id != ? ORDER BY first_name, surname");
        $stmt->execute([$evaluatorDept, $evaluatorId]);
        $staffList = $stmt->fetchAll();
    } else {
        $staffList = [];
    }
} elseif ($adminRole === 'dean') {
    // Dean sees only staff in their faculty (NOT themselves)
    if (!empty($evaluatorFac)) {
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE status = 'active' AND faculty = ? AND id != ? ORDER BY first_name, surname");
        $stmt->execute([$evaluatorFac, $evaluatorId]);
        $staffList = $stmt->fetchAll();
    } else {
        $staffList = [];
    }
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

if ($adminRole === 'supervisor' || $adminRole === 'hod') {
    // HOD sees pending evaluations from their department
    if (!empty($evalDept)) {
        $stmt = $pdo->prepare("SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level
            FROM evaluations e
            JOIN staff s ON e.staff_id = s.id
            WHERE e.evaluation_stage IN ('pending', 'hod') AND s.department = ?
            ORDER BY e.created_at DESC");
        $stmt->execute([$evalDept]);
        $pendingEvals = $stmt->fetchAll();
    } else {
        $pendingEvals = [];
    }
} elseif ($adminRole === 'registrar') {
    // Registrar sees all evaluations that have passed Dean
    $stmt = $pdo->prepare("SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level
        FROM evaluations e
        JOIN staff s ON e.staff_id = s.id
        WHERE e.evaluation_stage IN ('dean')
        ORDER BY e.created_at DESC");
    $stmt->execute();
    $pendingEvals = $stmt->fetchAll();
} else {
    $stmt = $pdo->query("SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level
        FROM evaluations e
        JOIN staff s ON e.staff_id = s.id
        WHERE e.evaluation_stage != 'completed'
        ORDER BY e.created_at DESC");
    $pendingEvals = $stmt->fetchAll();
}

// Get selected evaluation
$selectedEval = null;
$selectedStaff = null;

// First priority: get by eval_id
if ($evalId) {
    $stmt = $pdo->prepare("SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level, s.staff_category
        FROM evaluations e
        JOIN staff s ON e.staff_id = s.id
        WHERE e.id = ?");
    $stmt->execute([$evalId]);
    $selectedEval = $stmt->fetch();

    if ($selectedEval) {
        $staffId = $selectedEval['staff_id'];
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

if ($staffCategory === 'non-teaching') {
    // Non-teaching questions
    if ($evaluatorRole === 'supervisor' || $evaluatorRole === 'hod') {
        $teaching = [
            ['name' => 'teaching_1', 'label' => 'Job Knowledge & Expertise'],
            ['name' => 'teaching_2', 'label' => 'Quality of Work'],
            ['name' => 'teaching_3', 'label' => 'Productivity'],
            ['name' => 'teaching_4', 'label' => 'Initiative'],
            ['name' => 'teaching_5', 'label' => 'Adaptability'],
            ['name' => 'teaching_6', 'label' => 'Technical Skills'],
        ];
        $research = [
            ['name' => 'research_1', 'label' => 'Process Improvement'],
            ['name' => 'research_2', 'label' => 'Innovation'],
            ['name' => 'research_3', 'label' => 'Documentation'],
            ['name' => 'research_4', 'label' => 'Knowledge Sharing'],
            ['name' => 'research_5', 'label' => 'Problem Solving'],
        ];
    } else {
        // Dean and Registrar see summary only - no new questions
        $teaching = [];
        $research = [];
    }
} else {
    // Academic staff questions
    if ($evaluatorRole === 'supervisor' || $evaluatorRole === 'hod') {
        $teaching = [
            ['name' => 'teaching_1', 'label' => 'Lecture Delivery'],
            ['name' => 'teaching_2', 'label' => 'Class Attendance'],
            ['name' => 'teaching_3', 'label' => 'Student Engagement'],
            ['name' => 'teaching_4', 'label' => 'Course Preparation'],
            ['name' => 'teaching_5', 'label' => 'Course Coverage'],
            ['name' => 'teaching_6', 'label' => 'Time Management'],
        ];
        $research = [
            ['name' => 'research_1', 'label' => 'Publications'],
            ['name' => 'research_2', 'label' => 'Conferences'],
            ['name' => 'research_3', 'label' => 'Research Grants'],
            ['name' => 'research_4', 'label' => 'Journal Articles'],
            ['name' => 'research_5', 'label' => 'Innovations'],
        ];
    } else {
        // Dean and Registrar see summary only
        $teaching = [];
        $research = [];
    }
}

$adminQuestions = [
    ['name' => 'admin_1', 'label' => 'Attendance'],
    ['name' => 'admin_2', 'label' => 'Punctuality'],
    ['name' => 'admin_3', 'label' => 'Leadership'],
    ['name' => 'admin_4', 'label' => 'Teamwork'],
    ['name' => 'admin_5', 'label' => 'Record Keeping'],
];

$community = [
    ['name' => 'community_1', 'label' => 'Community Development'],
    ['name' => 'community_2', 'label' => 'Committee Participation'],
    ['name' => 'community_3', 'label' => 'Institutional Representation'],
];

$professional = [
    ['name' => 'professional_1', 'label' => 'Workshops'],
    ['name' => 'professional_2', 'label' => 'Training'],
    ['name' => 'professional_3', 'label' => 'Certifications'],
    ['name' => 'professional_4', 'label' => 'Seminars'],
];

// Calculate scores and grade
function calculateGrade($percentage) {
    if ($percentage >= 90) return ['Outstanding', 'Excellent Performance'];
    if ($percentage >= 80) return ['Excellent', 'Very Good Performance'];
    if ($percentage >= 70) return ['Very Good', 'Good Performance'];
    if ($percentage >= 60) return ['Good', 'Satisfactory'];
    if ($percentage >= 50) return ['Fair', 'Needs Improvement'];
    return ['Poor', 'Unsatisfactory'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_evaluation'])) {
    try {
        $pdo->beginTransaction();

        // Determine next stage
        // Determine next stage - after HOD, goes to Dean; after Dean, goes to Registrar; after Registrar, completed
        $nextStage = 'completed';
        if ($adminRole === 'supervisor' || $adminRole === 'hod') {
            $nextStage = 'dean'; // After HOD evaluates, passes to Dean
        } elseif ($adminRole === 'dean') {
            $nextStage = 'registrar'; // After Dean evaluates, passes to Registrar
        } elseif ($adminRole === 'registrar') {
            $nextStage = 'completed'; // Registrar is final approval
        }

        // Collect scores from form
        $scores = [];
        // Ensure all arrays are arrays
        $teachingArr = is_array($teaching) ? $teaching : [];
        $researchArr = is_array($research) ? $research : [];
        $adminArr = is_array($adminQuestions) ? $adminQuestions : [];
        $communityArr = is_array($community) ? $community : [];
        $professionalArr = is_array($professional) ? $professional : [];

        $allQuestions = array_merge($teachingArr, $researchArr, $adminArr, $communityArr, $professionalArr);
        foreach ($allQuestions as $q) {
            $scores[$q['name']] = intval($_POST[$q['name']] ?? 0);
        }

        $totalScore = array_sum($scores);
        $questionsAnswered = count(array_filter($scores));
        $averageScore = $questionsAnswered > 0 ? round($totalScore / $questionsAnswered, 2) : 0;
        $maxPossible = 23 * 5;
        $percentage = $maxPossible > 0 ? round(($totalScore / $maxPossible) * 100, 1) : 0;
        $gradeInfo = calculateGrade($percentage);

        // Build update data
        $updateData = [
            'total_score' => $totalScore,
            'average_score' => $averageScore,
            'percentage' => $percentage,
            'performance_grade' => $gradeInfo[0],
            'performance_status' => $gradeInfo[1],
            'evaluated_by' => $adminId,
        ];

        // Add stage-specific fields
        if ($adminRole === 'supervisor' || $adminRole === 'admin' || $adminRole === 'super_admin') {
            $updateData['evaluation_stage'] = 'hod';
            $updateData['hod_id'] = $adminId;
            $updateData['supervisor_name'] = sanitize($_POST['supervisor_name'] ?? $adminName);
            $updateData['supervisor_designation'] = sanitize($_POST['supervisor_designation'] ?? 'HOD');
            $updateData['supervisor_remarks'] = sanitize($_POST['supervisor_remarks'] ?? '');
            $updateData['supervisor_signature'] = sanitize($_POST['supervisor_signature'] ?? $adminName);
            $updateData['supervisor_date'] = date('Y-m-d');
            $updateData['overall_rating'] = sanitize($_POST['overall_rating'] ?? '');
            $updateData['recommendation'] = sanitize($_POST['recommendation'] ?? '');
        } elseif ($adminRole === 'dean') {
            $updateData['evaluation_stage'] = 'dean';
            $updateData['dean_id'] = $adminId;
            $updateData['dean_remarks'] = sanitize($_POST['dean_remarks'] ?? '');
            $updateData['dean_name'] = sanitize($_POST['dean_name'] ?? $adminName);
            $updateData['dean_date'] = sanitize($_POST['dean_date'] ?? date('Y-m-d'));
        } elseif ($adminRole === 'registrar') {
            $updateData['evaluation_stage'] = 'completed';
            $updateData['registrar_name'] = sanitize($_POST['registrar_name'] ?? $adminName);
            $updateData['registrar_remarks'] = sanitize($_POST['registrar_remarks'] ?? '');
            $updateData['approval_status'] = sanitize($_POST['approval_status'] ?? 'Approved');
            $updateData['registrar_signature'] = sanitize($_POST['registrar_signature'] ?? $adminName);
            $updateData['registrar_date'] = date('Y-m-d');
            $updateData['status'] = 'approved';
        }

        // Update scores
        foreach ($scores as $key => $value) {
            $updateData[$key] = $value;
        }

        // Build SQL
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: #308a1e; }
        body { background: #f3f4f6; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #308a1e 0%, #269c16 100%); color: white; }
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
        .score-display { background: linear-gradient(135deg, #308a1e, #269c16); color: white; padding: 1rem; border-radius: 10px; text-align: center; }
        .score-display .value { font-size: 2rem; font-weight: 700; }
        .staff-card { cursor: pointer; transition: all 0.2s; }
        .staff-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .staff-card.active { border: 2px solid #308a1e; background: #f0fdf4; }
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
                    <?php echo ucfirst($adminRole); ?> Evaluation Portal
                    <span class="badge bg-warning ms-2"><?php echo count($pendingEvals); ?> Pending</span>
                </h2>

                <!-- Stage Info -->
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    Currently viewing: <strong><?php echo strtoupper($currentStage); ?></strong> stage evaluations.
                    <?php if ($adminRole === 'supervisor'): ?>
                        Staff will move to Dean after your evaluation.
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
                                                </div>
                                                <span class="badge bg-<?php echo $eval['evaluation_stage'] === 'pending' ? 'secondary' : ($eval['evaluation_stage'] === 'hod' ? 'warning' : 'info'); ?> stage-badge">
                                                    <?php echo strtoupper($eval['evaluation_stage']); ?>
                                                </span>
                                            </div>
                                            <div class="mt-1">
                                                <small><strong>Score:</strong> <?php echo $eval['total_score']; ?>/115 (<?php echo $eval['percentage']; ?>%)</small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Evaluation Form -->
                    <div class="col-md-8">
                        <?php if ($selectedEval && is_array($selectedEval)): ?>
                            <form method="POST" id="evalForm">
                                <input type="hidden" name="eval_id" value="<?php echo $evalId; ?>">

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
                                            <div class="col-md-4"><strong>Current Score:</strong> <?php echo $selectedEval['total_score'] ?? 0; ?>/115</div>
                                            <div class="col-md-4"><strong>Percentage:</strong> <?php echo $selectedEval['percentage'] ?? 0; ?>%</div>
                                            <div class="col-md-4"><strong>Grade:</strong> <?php echo htmlspecialchars($selectedEval['performance_grade'] ?? 'N/A'); ?></div>
                                        </div>
                                        <div class="mt-2">
                                            <span class="badge bg-<?php echo ($selectedEval['evaluation_stage'] ?? 'pending') === 'pending' ? 'secondary' : (($selectedEval['evaluation_stage'] ?? 'pending') === 'hod' ? 'warning' : (($selectedEval['evaluation_stage'] ?? 'pending') === 'dean' ? 'info' : 'success')); ?>">
                                                Stage: <?php echo strtoupper($selectedEval['evaluation_stage'] ?? 'pending'); ?>
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
                                            <div class="value" id="totalScore"><?php echo $selectedEval['total_score']; ?></div>
                                            <div>Total Score</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="score-display">
                                            <div class="value" id="avgScore"><?php echo $selectedEval['average_score']; ?></div>
                                            <div>Average</div>
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

                                <!-- Rating Questions (Only show if not already answered) -->
                                <div class="card mb-4">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0"><i class="fas fa-star me-2"></i>Performance Ratings</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($adminRole === 'supervisor' || $adminRole === 'hod'): ?>
                                        <p class="text-muted">Rate the staff member on each criterion (1-5)</p>

                                        <!-- Teaching -->
                                        <div class="mb-4">
                                            <h6 class="text-primary border-bottom pb-2"><?php echo $staffCategory === 'non-teaching' ? 'Job Performance' : 'Teaching Performance'; ?></h6>
                                            <?php foreach ($teaching as $q): ?>
                                            <div class="question-item">
                                                <label class="form-label fw-bold"><?php echo $q['label']; ?></label>
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

                                        <!-- Research -->
                                        <div class="mb-4">
                                            <h6 class="text-primary border-bottom pb-2"><?php echo $staffCategory === 'non-teaching' ? 'Development & Improvement' : 'Research Performance'; ?></h6>
                                            <?php foreach ($research as $q): ?>
                                            <div class="question-item">
                                                <label class="form-label fw-bold"><?php echo $q['label']; ?></label>
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

                                        <!-- Admin -->
                                        <div class="mb-4">
                                            <h6 class="text-primary border-bottom pb-2">Administrative Duties</h6>
                                            <?php foreach ($adminQuestions as $q): ?>
                                            <div class="question-item">
                                                <label class="form-label fw-bold"><?php echo $q['label']; ?></label>
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

                                        <!-- Community -->
                                        <div class="mb-4">
                                            <h6 class="text-primary border-bottom pb-2">Community Service</h6>
                                            <?php foreach ($community as $q): ?>
                                            <div class="question-item">
                                                <label class="form-label fw-bold"><?php echo $q['label']; ?></label>
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

                                        <!-- Professional -->
                                        <div class="mb-4">
                                            <h6 class="text-primary border-bottom pb-2">Professional Development</h6>
                                            <?php foreach ($professional as $q): ?>
                                            <div class="question-item">
                                                <label class="form-label fw-bold"><?php echo $q['label']; ?></label>
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
                                        <?php endif; // End show questions only for HOD ?>

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
                                                <input type="text" class="form-control" name="supervisor_designation" value="<?php echo htmlspecialchars($selectedEval['supervisor_designation'] ?? 'HOD'); ?>">
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
                                        <?php elseif ($adminRole === 'dean'): ?>
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label"><strong>Dean Comments/Observations</strong></label>
                                                <textarea class="form-control" name="dean_remarks" rows="4" placeholder="Enter your evaluation comments, observations, and recommendations..."><?php echo htmlspecialchars($selectedEval['dean_remarks'] ?? ''); ?></textarea>
                                                <small class="text-muted">Provide detailed comments on the staff performance before forwarding to Registrar</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Dean Name</label>
                                                <input type="text" class="form-control" name="dean_name" value="<?php echo htmlspecialchars($selectedEval['dean_name'] ?? $adminName); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Date</label>
                                                <input type="date" class="form-control" name="dean_date" value="<?php echo $selectedEval['dean_date'] ?? date('Y-m-d'); ?>">
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
                                        <div class="d-flex gap-2 flex-wrap">
                                            <button type="submit" name="save_evaluation" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Save
                                            </button>
                                            <button type="submit" name="save_and_next" class="btn btn-success">
                                                <i class="fas fa-arrow-right me-2"></i>Save & Next (Pass to <?php echo $adminRole === 'registrar' ? 'Complete' : ($adminRole === 'dean' ? 'Registrar' : 'Dean'); ?>)
                                            </button>
                                            <a href="evaluate-supervisor.php" class="btn btn-secondary">
                                                <i class="fas fa-times me-2"></i>Cancel
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-clipboard-check fa-4x text-muted mb-3"></i>
                                    <h4>Select a staff member to evaluate</h4>
                                    <p class="text-muted">Click on a staff member from the list to begin evaluation</p>
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
        const avg = count > 0 ? (total / count).toFixed(2) : 0;
        const maxPossible = 23 * 5;
        const percentage = maxPossible > 0 ? ((total / maxPossible) * 100).toFixed(1) : 0;

        document.getElementById('totalScore').textContent = total;
        document.getElementById('avgScore').textContent = avg;
        document.getElementById('percentScore').textContent = percentage + '%';

        let grade = '-';
        if (percentage >= 90) grade = 'Outstanding';
        else if (percentage >= 80) grade = 'Excellent';
        else if (percentage >= 70) grade = 'Very Good';
        else if (percentage >= 60) grade = 'Good';
        else if (percentage >= 50) grade = 'Fair';
        else if (percentage > 0) grade = 'Poor';

        document.getElementById('gradeDisplay').textContent = grade;
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