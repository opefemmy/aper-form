<?php
require_once 'config.php';
requireAdminLogin();

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
$secondaryColor = $settings['secondary_color'] ?? '#269c16';

$message = getMessage();
$staffId = $_GET['staff_id'] ?? null;
$evalId = $_GET['eval_id'] ?? null;

$pdo = getDBConnection();

// Get settings for institution name
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get staff list for dropdown
$stmt = $pdo->query("SELECT * FROM staff WHERE status = 'active' ORDER BY first_name, surname");
$staffList = $stmt->fetchAll();

// Get selected staff details
$selectedStaff = null;
if ($staffId) {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([$staffId]);
    $selectedStaff = $stmt->fetch();
}

// Get existing evaluation if editing
$existingEval = null;
if ($evalId) {
    $stmt = $pdo->prepare("SELECT * FROM evaluations WHERE id = ?");
    $stmt->execute([$evalId]);
    $existingEval = $stmt->fetch();

    if ($existingEval && !$staffId) {
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
        $stmt->execute([$existingEval['staff_id']]);
        $selectedStaff = $stmt->fetch();
        $staffId = $existingEval['staff_id'];
    }
}

// Determine staff category for question display
$staffCategory = $selectedStaff['staff_category'] ?? 'academic';

// Define different questions for academic vs non-teaching staff
if ($staffCategory === 'non-teaching') {
    // Non-teaching staff questions
    $teaching = [
        ['name' => 'teaching_1', 'label' => 'Job Knowledge & Expertise', 'hint' => 'Understanding of role requirements'],
        ['name' => 'teaching_2', 'label' => 'Quality of Work', 'hint' => 'Accuracy and thoroughness'],
        ['name' => 'teaching_3', 'label' => 'Productivity', 'hint' => 'Volume of work completed'],
        ['name' => 'teaching_4', 'label' => 'Initiative', 'hint' => 'Proactiveness in tasks'],
        ['name' => 'teaching_5', 'label' => 'Adaptability', 'hint' => 'Flexibility with changing demands'],
        ['name' => 'teaching_6', 'label' => 'Technical Skills', 'hint' => 'Required technical competencies'],
    ];
    $research = [
        ['name' => 'research_1', 'label' => 'Process Improvement', 'hint' => 'Suggestions for efficiency'],
        ['name' => 'research_2', 'label' => 'Innovation', 'hint' => 'New ideas implemented'],
        ['name' => 'research_3', 'label' => 'Documentation', 'hint' => 'Record keeping quality'],
        ['name' => 'research_4', 'label' => 'Knowledge Sharing', 'hint' => 'Sharing expertise with others'],
        ['name' => 'research_5', 'label' => 'Problem Solving', 'hint' => 'Addressing issues proactively'],
    ];
} else {
    // Academic (teaching) staff questions
    $teaching = [
        ['name' => 'teaching_1', 'label' => 'Lecture Delivery', 'hint' => 'Quality of teaching delivery'],
        ['name' => 'teaching_2', 'label' => 'Class Attendance', 'hint' => 'Student attendance rates'],
        ['name' => 'teaching_3', 'label' => 'Student Engagement', 'hint' => 'Interaction with students'],
        ['name' => 'teaching_4', 'label' => 'Course Preparation', 'hint' => 'Lesson planning quality'],
        ['name' => 'teaching_5', 'label' => 'Course Coverage', 'hint' => 'Syllabus completion'],
        ['name' => 'teaching_6', 'label' => 'Time Management', 'hint' => 'Punctuality in classes'],
    ];
    $research = [
        ['name' => 'research_1', 'label' => 'Publications', 'hint' => 'Research publications'],
        ['name' => 'research_2', 'label' => 'Conferences', 'hint' => 'Conference presentations'],
        ['name' => 'research_3', 'label' => 'Research Grants', 'hint' => 'Grant acquisition'],
        ['name' => 'research_4', 'label' => 'Journal Articles', 'hint' => 'Journal publications'],
        ['name' => 'research_5', 'label' => 'Innovations', 'hint' => 'Research innovations'],
    ];
}

// Common questions for all staff
$admin = [
    ['name' => 'admin_1', 'label' => 'Attendance', 'hint' => 'Regular presence at workplace'],
    ['name' => 'admin_2', 'label' => 'Punctuality', 'hint' => 'Time consciousness'],
    ['name' => 'admin_3', 'label' => 'Leadership', 'hint' => 'Taking initiative'],
    ['name' => 'admin_4', 'label' => 'Teamwork', 'hint' => 'Collaboration with colleagues'],
    ['name' => 'admin_5', 'label' => 'Record Keeping', 'hint' => 'Documentation accuracy'],
];

$community = [
    ['name' => 'community_1', 'label' => 'Community Development', 'hint' => 'Contribution to community'],
    ['name' => 'community_2', 'label' => 'Committee Participation', 'hint' => 'Involvement in committees'],
    ['name' => 'community_3', 'label' => 'Institutional Representation', 'hint' => 'Representing the institution'],
];

$professional = [
    ['name' => 'professional_1', 'label' => 'Workshops', 'hint' => 'Workshop attendance'],
    ['name' => 'professional_2', 'label' => 'Training', 'hint' => 'Training programs'],
    ['name' => 'professional_3', 'label' => 'Certifications', 'hint' => 'Professional certifications'],
    ['name' => 'professional_4', 'label' => 'Seminars', 'hint' => 'Seminar participation'],
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

        $admin = getCurrentAdmin();

        // Collect all scores
        $scores = [
            'teaching_1' => intval($_POST['teaching_1'] ?? 0),
            'teaching_2' => intval($_POST['teaching_2'] ?? 0),
            'teaching_3' => intval($_POST['teaching_3'] ?? 0),
            'teaching_4' => intval($_POST['teaching_4'] ?? 0),
            'teaching_5' => intval($_POST['teaching_5'] ?? 0),
            'teaching_6' => intval($_POST['teaching_6'] ?? 0),
            'research_1' => intval($_POST['research_1'] ?? 0),
            'research_2' => intval($_POST['research_2'] ?? 0),
            'research_3' => intval($_POST['research_3'] ?? 0),
            'research_4' => intval($_POST['research_4'] ?? 0),
            'research_5' => intval($_POST['research_5'] ?? 0),
            'admin_1' => intval($_POST['admin_1'] ?? 0),
            'admin_2' => intval($_POST['admin_2'] ?? 0),
            'admin_3' => intval($_POST['admin_3'] ?? 0),
            'admin_4' => intval($_POST['admin_4'] ?? 0),
            'admin_5' => intval($_POST['admin_5'] ?? 0),
            'community_1' => intval($_POST['community_1'] ?? 0),
            'community_2' => intval($_POST['community_2'] ?? 0),
            'community_3' => intval($_POST['community_3'] ?? 0),
            'professional_1' => intval($_POST['professional_1'] ?? 0),
            'professional_2' => intval($_POST['professional_2'] ?? 0),
            'professional_3' => intval($_POST['professional_3'] ?? 0),
            'professional_4' => intval($_POST['professional_4'] ?? 0),
        ];

        $totalScore = array_sum($scores);
        $questionsAnswered = count(array_filter($scores));
        $averageScore = $questionsAnswered > 0 ? round($totalScore / $questionsAnswered, 2) : 0;
        $maxPossible = 23 * 5;
        $percentage = $maxPossible > 0 ? round(($totalScore / $maxPossible) * 100, 1) : 0;
        $gradeInfo = calculateGrade($percentage);

        $data = [
            'staff_id' => $staffId,
            'academic_session_id' => intval($_POST['academic_session_id'] ?? 0),
            'evaluation_year' => intval($_POST['evaluation_year']),

            // Scores
            'teaching_1' => $scores['teaching_1'], 'teaching_2' => $scores['teaching_2'],
            'teaching_3' => $scores['teaching_3'], 'teaching_4' => $scores['teaching_4'],
            'teaching_5' => $scores['teaching_5'], 'teaching_6' => $scores['teaching_6'],
            'research_1' => $scores['research_1'], 'research_2' => $scores['research_2'],
            'research_3' => $scores['research_3'], 'research_4' => $scores['research_4'],
            'research_5' => $scores['research_5'],
            'admin_1' => $scores['admin_1'], 'admin_2' => $scores['admin_2'],
            'admin_3' => $scores['admin_3'], 'admin_4' => $scores['admin_4'],
            'admin_5' => $scores['admin_5'],
            'community_1' => $scores['community_1'], 'community_2' => $scores['community_2'],
            'community_3' => $scores['community_3'],
            'professional_1' => $scores['professional_1'], 'professional_2' => $scores['professional_2'],
            'professional_3' => $scores['professional_3'], 'professional_4' => $scores['professional_4'],

            // Calculated
            'total_score' => $totalScore,
            'average_score' => $averageScore,
            'percentage' => $percentage,
            'performance_grade' => $gradeInfo[0],
            'performance_status' => $gradeInfo[1],

            // Supervisor
            'supervisor_name' => sanitize($_POST['supervisor_name']),
            'supervisor_designation' => sanitize($_POST['supervisor_designation']),
            'supervisor_remarks' => sanitize($_POST['supervisor_remarks']),
            'overall_rating' => sanitize($_POST['overall_rating']),
            'recommendation' => sanitize($_POST['recommendation']),
            'supervisor_signature' => sanitize($_POST['supervisor_signature']),
            'supervisor_date' => sanitize($_POST['supervisor_date']),

            // Registrar
            'registrar_name' => sanitize($_POST['registrar_name']),
            'registrar_remarks' => sanitize($_POST['registrar_remarks']),
            'approval_status' => sanitize($_POST['approval_status']),
            'registrar_signature' => sanitize($_POST['registrar_signature']),
            'registrar_date' => sanitize($_POST['registrar_date']),

            // Status
            'status' => sanitize($_POST['status'] ?? 'draft'),
            'evaluated_by' => $admin['id'],

            // Staff category
            'staff_category' => sanitize($staffCategory),
        ];

        if ($evalId) {
            // Update existing
            $fields = [];
            $values = [];
            foreach ($data as $key => $value) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
            $values[] = $evalId;

            $sql = "UPDATE evaluations SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
        } else {
            // Insert new
            $keys = array_keys($data);
            $fields = implode(', ', $keys);
            $placeholders = implode(', ', array_fill(0, count($data), '?'));

            $sql = "INSERT INTO evaluations ($fields) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
            $evalId = $pdo->lastInsertId();
        }

        $pdo->commit();
        showMessage('Evaluation saved successfully!', 'success');

        if (isset($_POST['save_and_close'])) {
            redirect('reports.php');
        } else {
            redirect("evaluate.php?eval_id=$evalId");
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
    <title>Performance Evaluation - <?php echo htmlspecialchars($institutionName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: #308a1e; }
        body { background: #f3f4f6; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #308a1e 0%, #269c16 100%); color: white; }
        .sidebar a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 12px 15px; display: block; border-radius: 8px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); color: white; }
        .question-item { background: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #e2e8f0; }
        .rating-label { padding: 0.5rem 0.75rem; background: #f8fafc; border-radius: 20px; cursor: pointer; margin-right: 0.25rem; margin-bottom: 0.25rem; display: inline-block; }
        .rating-label:hover { background: #dbeafe; }
        .rating-label input:checked + span { background: var(--primary-blue); color: white; }
        .score-display { background: linear-gradient(135deg, #308a1e, #269c16); color: white; padding: 1rem; border-radius: 10px; text-align: center; }
        .score-display .value { font-size: 2rem; font-weight: 700; }

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
        }
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
                </div>
                <div class="py-3">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="staff.php"><i class="fas fa-users"></i> Staff</a>
                    <a href="evaluate.php" class="active"><i class="fas fa-clipboard-check"></i> Evaluate</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                    <a href="sessions.php"><i class="fas fa-calendar"></i> Sessions</a>
                    <a href="logout.php" class="text-warning"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
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

                <form method="POST" id="evalForm">
                <!-- Staff Selection -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Staff Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Select Staff *</label>
                                <select class="form-select" name="staff_id" id="staffSelect" required <?php echo $evalId ? 'disabled' : ''; ?>>
                                    <option value="">-- Select Staff --</option>
                                    <?php foreach ($staffList as $staff): ?>
                                        <option value="<?php echo $staff['id']; ?>" <?php echo ($staffId == $staff['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['surname'] . ' - ' . $staff['staff_id']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($evalId): ?>
                                    <input type="hidden" name="staff_id" value="<?php echo $staffId; ?>">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Academic Session *</label>
                                <select class="form-select" name="academic_session_id" required>
                                    <option value="">-- Select Session --</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?php echo $session['id']; ?>" <?php echo ($existingEval['academic_session_id'] ?? '') == $session['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($session['session_name'] . ' - ' . $session['semester']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Evaluation Year *</label>
                                <input type="number" class="form-control" name="evaluation_year" value="<?php echo $existingEval['evaluation_year'] ?? ($settings['evaluation_year'] ?? date('Y')); ?>" min="2020" max="2030" required>
                            </div>
                        </div>

                        <?php if ($selectedStaff): ?>
                        <div class="alert alert-info mt-3">
                            <h6><i class="fas fa-info-circle me-2"></i>Selected Staff Details</h6>
                            <div class="row">
                                <div class="col-md-3"><strong>Staff ID:</strong> <?php echo htmlspecialchars($selectedStaff['staff_id']); ?></div>
                                <div class="col-md-3"><strong>Department:</strong> <?php echo htmlspecialchars($selectedStaff['department']); ?></div>
                                <div class="col-md-3"><strong>Faculty:</strong> <?php echo htmlspecialchars($selectedStaff['faculty']); ?></div>
                                <div class="col-md-3"><strong>Designation:</strong> <?php echo htmlspecialchars($selectedStaff['designation']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Live Score Display -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="score-display">
                            <div class="value" id="totalScore">0</div>
                            <div>Total Score</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="score-display">
                            <div class="value" id="avgScore">0</div>
                            <div>Average</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="score-display">
                            <div class="value" id="percentScore">0%</div>
                            <div>Percentage</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="score-display" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <div class="value" id="gradeDisplay">-</div>
                            <div>Grade</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="score-display" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <div class="value" id="statusDisplay">Pending</div>
                            <div>Status</div>
                        </div>
                    </div>
                </div>

                <!-- Performance Evaluation Questions -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Performance Evaluation</h5>
                    </div>
                    <div class="card-body">

                        <!-- Teaching/Performance Section -->
                        <div class="mb-4">
                            <h6 class="text-primary border-bottom pb-2">
                                <i class="fas fa-chalkboard-teacher me-2"></i>
                                <?php echo $staffCategory === 'non-teaching' ? 'Job Performance' : 'Teaching Performance'; ?>
                                <small class="text-muted">(<?php echo $staffCategory === 'non-teaching' ? 'Non-Teaching Staff' : 'Academic Staff'; ?>)</small>
                            </h6>
                            <?php foreach ($teaching as $q): ?>
                            <div class="question-item">
                                <label class="form-label fw-bold" title="<?php echo $q['hint'] ?? ''; ?>">
                                    <?php echo $q['label']; ?>
                                    <?php if (!empty($q['hint'])): ?>
                                    <i class="fas fa-info-circle text-muted ms-1" style="font-size: 10px;" title="<?php echo htmlspecialchars($q['hint']); ?>"></i>
                                    <?php endif; ?>
                                </label>
                                <div>
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <label class="rating-label">
                                        <input type="radio" name="<?php echo $q['name']; ?>" value="<?php echo $i; ?>"
                                               onchange="calculateScores()" <?php echo ($existingEval[$q['name']] ?? '') == $i ? 'checked' : ''; ?>>
                                        <span><?php echo $i; ?></span>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Research/Development Section -->
                        <div class="mb-4">
                            <h6 class="text-primary border-bottom pb-2">
                                <i class="fas fa-flask me-2"></i>
                                <?php echo $staffCategory === 'non-teaching' ? 'Development & Improvement' : 'Research Performance'; ?>
                            </h6>
                            <?php foreach ($research as $q): ?>
                            <div class="question-item">
                                <label class="form-label fw-bold" title="<?php echo $q['hint'] ?? ''; ?>">
                                    <?php echo $q['label']; ?>
                                    <?php if (!empty($q['hint'])): ?>
                                    <i class="fas fa-info-circle text-muted ms-1" style="font-size: 10px;" title="<?php echo htmlspecialchars($q['hint']); ?>"></i>
                                    <?php endif; ?>
                                </label>
                                <div>
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <label class="rating-label">
                                        <input type="radio" name="<?php echo $q['name']; ?>" value="<?php echo $i; ?>"
                                               onchange="calculateScores()" <?php echo ($existingEval[$q['name']] ?? '') == $i ? 'checked' : ''; ?>>
                                        <span><?php echo $i; ?></span>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Administrative Duties -->
                        <div class="mb-4">
                            <h6 class="text-primary border-bottom pb-2"><i class="fas fa-briefcase me-2"></i>Administrative Duties</h6>
                            <?php foreach ($admin as $q): ?>
                            <div class="question-item">
                                <label class="form-label fw-bold" title="<?php echo $q['hint'] ?? ''; ?>">
                                    <?php echo $q['label']; ?>
                                    <?php if (!empty($q['hint'])): ?>
                                    <i class="fas fa-info-circle text-muted ms-1" style="font-size: 10px;" title="<?php echo htmlspecialchars($q['hint']); ?>"></i>
                                    <?php endif; ?>
                                </label>
                                <div>
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <label class="rating-label">
                                        <input type="radio" name="<?php echo $q['name']; ?>" value="<?php echo $i; ?>"
                                               onchange="calculateScores()" <?php echo ($existingEval[$q['name']] ?? '') == $i ? 'checked' : ''; ?>>
                                        <span><?php echo $i; ?></span>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Community Service -->
                        <div class="mb-4">
                            <h6 class="text-primary border-bottom pb-2"><i class="fas fa-users me-2"></i>Community Service</h6>
                            <?php foreach ($community as $q): ?>
                            <div class="question-item">
                                <label class="form-label fw-bold" title="<?php echo $q['hint'] ?? ''; ?>">
                                    <?php echo $q['label']; ?>
                                    <?php if (!empty($q['hint'])): ?>
                                    <i class="fas fa-info-circle text-muted ms-1" style="font-size: 10px;" title="<?php echo htmlspecialchars($q['hint']); ?>"></i>
                                    <?php endif; ?>
                                </label>
                                <div>
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <label class="rating-label">
                                        <input type="radio" name="<?php echo $q['name']; ?>" value="<?php echo $i; ?>"
                                               onchange="calculateScores()" <?php echo ($existingEval[$q['name']] ?? '') == $i ? 'checked' : ''; ?>>
                                        <span><?php echo $i; ?></span>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Professional Development -->
                        <div class="mb-4">
                            <h6 class="text-primary border-bottom pb-2"><i class="fas fa-certificate me-2"></i>Professional Development</h6>
                            <?php foreach ($professional as $q): ?>
                            <div class="question-item">
                                <label class="form-label fw-bold" title="<?php echo $q['hint'] ?? ''; ?>">
                                    <?php echo $q['label']; ?>
                                    <?php if (!empty($q['hint'])): ?>
                                    <i class="fas fa-info-circle text-muted ms-1" style="font-size: 10px;" title="<?php echo htmlspecialchars($q['hint']); ?>"></i>
                                    <?php endif; ?>
                                </label>
                                <div>
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <label class="rating-label">
                                        <input type="radio" name="<?php echo $q['name']; ?>" value="<?php echo $i; ?>"
                                               onchange="calculateScores()" <?php echo ($existingEval[$q['name']] ?? '') == $i ? 'checked' : ''; ?>>
                                        <span><?php echo $i; ?></span>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </div>

                <!-- Supervisor Assessment -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Supervisor Assessment</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supervisor Name</label>
                                <input type="text" class="form-control" name="supervisor_name" value="<?php echo htmlspecialchars($existingEval['supervisor_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supervisor Designation</label>
                                <input type="text" class="form-control" name="supervisor_designation" value="<?php echo htmlspecialchars($existingEval['supervisor_designation'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Overall Rating</label>
                                <select class="form-select" name="overall_rating">
                                    <option value="">Select Rating</option>
                                    <option value="Outstanding" <?php echo ($existingEval['overall_rating'] ?? '') == 'Outstanding' ? 'selected' : ''; ?>>Outstanding</option>
                                    <option value="Excellent" <?php echo ($existingEval['overall_rating'] ?? '') == 'Excellent' ? 'selected' : ''; ?>>Excellent</option>
                                    <option value="Very Good" <?php echo ($existingEval['overall_rating'] ?? '') == 'Very Good' ? 'selected' : ''; ?>>Very Good</option>
                                    <option value="Good" <?php echo ($existingEval['overall_rating'] ?? '') == 'Good' ? 'selected' : ''; ?>>Good</option>
                                    <option value="Fair" <?php echo ($existingEval['overall_rating'] ?? '') == 'Fair' ? 'selected' : ''; ?>>Fair</option>
                                    <option value="Poor" <?php echo ($existingEval['overall_rating'] ?? '') == 'Poor' ? 'selected' : ''; ?>>Poor</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Recommendation</label>
                                <select class="form-select" name="recommendation">
                                    <option value="">Select Recommendation</option>
                                    <option value="Promoted" <?php echo ($existingEval['recommendation'] ?? '') == 'Promoted' ? 'selected' : ''; ?>>Promoted</option>
                                    <option value="Confirmed" <?php echo ($existingEval['recommendation'] ?? '') == 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="Continued" <?php echo ($existingEval['recommendation'] ?? '') == 'Continued' ? 'selected' : ''; ?>>Continued</option>
                                    <option value="Probation" <?php echo ($existingEval['recommendation'] ?? '') == 'Probation' ? 'selected' : ''; ?>>Probation</option>
                                    <option value="Terminated" <?php echo ($existingEval['recommendation'] ?? '') == 'Terminated' ? 'selected' : ''; ?>>Terminated</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Remarks</label>
                                <textarea class="form-control" name="supervisor_remarks" rows="3"><?php echo htmlspecialchars($existingEval['supervisor_remarks'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Digital Signature</label>
                                <input type="text" class="form-control" name="supervisor_signature" value="<?php echo htmlspecialchars($existingEval['supervisor_signature'] ?? ''); ?>" placeholder="Type name as signature">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="supervisor_date" value="<?php echo $existingEval['supervisor_date'] ?? date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Registrar/Management -->
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="fas fa-building me-2"></i>Registrar/Management Approval</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Registrar Name</label>
                                <input type="text" class="form-control" name="registrar_name" value="<?php echo htmlspecialchars($existingEval['registrar_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Approval Status</label>
                                <select class="form-select" name="approval_status">
                                    <option value="">Select Status</option>
                                    <option value="Approved" <?php echo ($existingEval['approval_status'] ?? '') == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="Pending" <?php echo ($existingEval['approval_status'] ?? '') == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Rejected" <?php echo ($existingEval['approval_status'] ?? '') == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Remarks</label>
                                <textarea class="form-control" name="registrar_remarks" rows="3"><?php echo htmlspecialchars($existingEval['registrar_remarks'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Digital Signature</label>
                                <input type="text" class="form-control" name="registrar_signature" value="<?php echo htmlspecialchars($existingEval['registrar_signature'] ?? ''); ?>" placeholder="Type name as signature">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="registrar_date" value="<?php echo $existingEval['registrar_date'] ?? date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status and Submit -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Evaluation Status</label>
                                <select class="form-select" name="status">
                                    <option value="draft" <?php echo ($existingEval['status'] ?? 'draft') == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="submitted" <?php echo ($existingEval['status'] ?? '') == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                    <option value="approved" <?php echo ($existingEval['status'] ?? '') == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo ($existingEval['status'] ?? '') == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="save_evaluation" class="btn btn-primary btn-lg" title="Save evaluation and continue editing">
                                <i class="fas fa-save me-2"></i>Save Evaluation
                            </button>
                            <button type="submit" name="save_and_close" class="btn btn-success btn-lg" title="Save evaluation and return to reports">
                                <i class="fas fa-check me-2"></i>Save & Close
                            </button>
                            <a href="reports.php" class="btn btn-secondary btn-lg" title="Discard changes and return to reports">Cancel</a>
                        </div>
                    </div>
                </div>

                </form>
            </div>
        </div>
    </div>

    <script>
    function calculateScores() {
        let total = 0;
        let count = 0;

        // Get all radio buttons that are checked
        const radios = document.querySelectorAll('input[type="radio"]:checked');
        radios.forEach(radio => {
            total += parseInt(radio.value);
            count++;
        });

        const avg = count > 0 ? (total / count).toFixed(2) : 0;
        const maxPossible = 23 * 5;
        const percentage = maxPossible > 0 ? ((total / maxPossible) * 100).toFixed(1) : 0;

        // Update display
        document.getElementById('totalScore').textContent = total;
        document.getElementById('avgScore').textContent = avg;
        document.getElementById('percentScore').textContent = percentage + '%';

        // Calculate grade
        let grade = '-';
        let status = 'Pending';

        if (percentage >= 90) { grade = 'Outstanding'; status = 'Excellent'; }
        else if (percentage >= 80) { grade = 'Excellent'; status = 'Very Good'; }
        else if (percentage >= 70) { grade = 'Very Good'; status = 'Good'; }
        else if (percentage >= 60) { grade = 'Good'; status = 'Satisfactory'; }
        else if (percentage >= 50) { grade = 'Fair'; status = 'Needs Improvement'; }
        else if (percentage > 0) { grade = 'Poor'; status = 'Unsatisfactory'; }

        document.getElementById('gradeDisplay').textContent = grade;
        document.getElementById('statusDisplay').textContent = status;
    }

    // Calculate on page load (for existing evaluations)
    document.addEventListener('DOMContentLoaded', calculateScores);
    </script>

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
    </script>
</body>
</html>