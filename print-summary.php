<?php
require_once 'config.php';
startSession();

// Check if evaluation ID is provided
$evalId = $_GET['id'] ?? 0;
if (!$evalId) {
    die('Evaluation ID required');
}

$pdo = getDBConnection();

// Get evaluation
$stmt = $pdo->prepare("SELECT e.*, s.staff_id AS staff_identifier, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level, s.staff_category
    FROM evaluations e
    JOIN staff s ON e.staff_id = s.id
    WHERE e.id = ?");
$stmt->execute([$evalId]);
$eval = $stmt->fetch();

if (!$eval) {
    die('Evaluation not found');
}

// Verify access (staff can only view their own, admins can view all)
if (!isAdminLoggedIn() && !isStaffLoggedIn()) {
    redirect(SITE_URL . '/unified-login.php');
}

if (isStaffLoggedIn() && $_SESSION['staff_id'] != $eval['staff_id']) {
    die('Access denied: You can only print your own evaluation');
}

// Check if this is staff viewing their own evaluation (show self-assessment only)
$isStaffOwnPrint = isStaffLoggedIn() && $_SESSION['staff_id'] == $eval['staff_id'];

// Get staff's actual responses (self-evaluation answers)
$staffResponses = [];
if (isset($eval['responses']) && !empty($eval['responses'])) {
    $staffResponses = is_array($eval['responses']) ? $eval['responses'] : json_decode($eval['responses'], true);
}

// Get settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$instName = $settings['institution_name'] ?? 'Institution';
$instAddress = $settings['institution_address'] ?? '';
$logo = $settings['institution_logo'] ?? '';

// Get academic session
$stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE id = ?");
$stmt->execute([$eval['academic_session_id']]);
$session = $stmt->fetch();

// Questions mapping
$teaching = [
    ['key' => 'teaching_1', 'label' => 'Lecture Delivery'],
    ['key' => 'teaching_2', 'label' => 'Class Attendance'],
    ['key' => 'teaching_3', 'label' => 'Student Engagement'],
    ['key' => 'teaching_4', 'label' => 'Course Preparation'],
    ['key' => 'teaching_5', 'label' => 'Course Coverage'],
    ['key' => 'teaching_6', 'label' => 'Time Management']
];
$research = [
    ['key' => 'research_1', 'label' => 'Publications'],
    ['key' => 'research_2', 'label' => 'Conferences'],
    ['key' => 'research_3', 'label' => 'Research Grants'],
    ['key' => 'research_4', 'label' => 'Journal Articles'],
    ['key' => 'research_5', 'label' => 'Innovations']
];
$admin = [
    ['key' => 'admin_1', 'label' => 'Attendance'],
    ['key' => 'admin_2', 'label' => 'Punctuality'],
    ['key' => 'admin_3', 'label' => 'Leadership'],
    ['key' => 'admin_4', 'label' => 'Teamwork'],
    ['key' => 'admin_5', 'label' => 'Record Keeping']
];
$community = [
    ['key' => 'community_1', 'label' => 'Community Development'],
    ['key' => 'community_2', 'label' => 'Committee Participation'],
    ['key' => 'community_3', 'label' => 'Institutional Representation']
];
$professional = [
    ['key' => 'professional_1', 'label' => 'Workshops'],
    ['key' => 'professional_2', 'label' => 'Training'],
    ['key' => 'professional_3', 'label' => 'Certifications'],
    ['key' => 'professional_4', 'label' => 'Seminars']
];

function getScoreLabel($score) {
    $labels = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very Good', 5 => 'Excellent'];
    return $labels[$score] ?? 'N/A';
}

// Helper function to get answered questions only
function getAnsweredQuestions($questions, $responses) {
    $answered = [];
    $totalScore = 0;
    $maxScore = 0;
    foreach ($questions as $q) {
        $key = $q['key'];
        // Check if answer exists in responses (could be in JSON or direct column)
        $value = $responses[$key] ?? null;
        if ($value === null && isset($eval[$key])) {
            $value = $eval[$key];
        }
        if ($value !== null && $value !== '' && $value !== 0) {
            $answered[] = [
                'key' => $key,
                'label' => $q['label'],
                'score' => $value
            ];
            $totalScore += intval($value);
            $maxScore += 5;
        }
    }
    return ['questions' => $answered, 'total' => $totalScore, 'max' => $maxScore];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation Summary - <?php echo htmlspecialchars($instName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="theme-overrides.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; }
            .container { max-width: 100% !important; }
            .watermark { opacity: 0.08 !important; }
            .background-logo { opacity: 0.05 !important; }
        }
        body { background: white; padding: 20px; position: relative; }
        /* Full background logo - Institution Logo */
        .background-logo {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
            max-width: 600px;
            height: auto;
            opacity: 0.12;
            z-index: 0;
            pointer-events: none;
        }
        /* Center watermark */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 400px;
            height: 400px;
            opacity: 0.1;
            z-index: 0;
            pointer-events: none;
        }
        .watermark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .print-header { text-align: center; border-bottom: 3px solid #308a1e; padding-bottom: 15px; margin-bottom: 20px; position: relative; z-index: 1; }
        .print-header img.logo-img { max-height: 70px; margin-bottom: 10px; }
        .staff-info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #308a1e; position: relative; z-index: 1; }
        .score-summary { background: linear-gradient(135deg, #308a1e, #269c16); color: white; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px; position: relative; z-index: 1; }
        .score-box { font-size: 2rem; font-weight: bold; }
        .question-section { margin-bottom: 20px; position: relative; z-index: 1; }
        .question-section h5 { border-bottom: 2px solid #308a1e; padding-bottom: 8px; color: #308a1e; font-weight: bold; }
        .question-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .question-label { font-weight: 500; }
        .question-score { font-weight: bold; color: #308a1e; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #dee2e6; font-size: 0.9rem; position: relative; z-index: 1; }
        .signature-section { margin-top: 20px; display: flex; justify-content: space-between; }
        .signature-box { width: 30%; text-align: center; }
        .signature-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 5px; }
    </style>
</head>
<body>
    <!-- Full Background Logo -->
    <?php if (!empty($logo)): ?>
    <img src="<?php echo htmlspecialchars($logo); ?>" alt="Background Logo" class="background-logo">
    <?php endif; ?>

    <!-- Center Watermark -->
    <?php if (!empty($logo)): ?>
    <div class="watermark">
        <img src="<?php echo htmlspecialchars($logo); ?>" alt="Watermark">
    </div>
    <?php endif; ?>

    <div class="container">
        <div class="no-print text-center mb-4">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print
            </button>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>

        <div class="print-header">
            <?php if (!empty($logo)): ?>
            <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" class="logo-img">
            <?php endif; ?>
            <h2 class="text-success"><?php echo htmlspecialchars($instName); ?></h2>
            <?php if (!empty($instAddress)): ?>
            <p class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($instAddress); ?></p>
            <?php endif; ?>
            <h4 class="mt-3">Annual Performance Evaluation Report</h4>
        </div>

        <div class="staff-info">
            <div class="row">
                <div class="col-md-4"><strong>Staff ID:</strong> <?php echo htmlspecialchars($eval['staff_identifier'] ?? $eval['staff_id']); ?></div>
                <div class="col-md-4"><strong>Name:</strong> <?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['surname']); ?></div>
                <div class="col-md-4"><strong>Category:</strong> <?php echo $eval['staff_category'] == 'academic' ? 'Academic Staff' : ($eval['staff_category'] == 'hod' ? 'HOD' : 'Non-Teaching Staff'); ?></div>
            </div>
            <div class="row mt-2">
                <div class="col-md-4"><strong>Department:</strong> <?php echo htmlspecialchars($eval['department'] ?? 'N/A'); ?></div>
                <div class="col-md-4"><strong>Faculty:</strong> <?php echo htmlspecialchars($eval['faculty'] ?? 'N/A'); ?></div>
                <div class="col-md-4"><strong>Designation:</strong> <?php echo htmlspecialchars($eval['designation'] ?? 'N/A'); ?></div>
            </div>
            <div class="row mt-2">
                <div class="col-md-4"><strong>Grade Level:</strong> <?php echo htmlspecialchars($eval['grade_level'] ?? 'N/A'); ?></div>
                <div class="col-md-4"><strong>Academic Session:</strong> <?php echo htmlspecialchars($session['session_name'] ?? 'N/A'); ?></div>
                <div class="col-md-4"><strong>Evaluation Year:</strong> <?php echo $eval['evaluation_year']; ?></div>
            </div>
        </div>

        <div class="row score-summary">
            <div class="col-md-4">
                <div class="score-box"><?php echo $eval['percentage']; ?>%</div>
                <div>Score (%)</div>
            </div>
            <div class="col-md-4">
                <div class="score-box"><?php echo htmlspecialchars($eval['performance_grade']); ?></div>
                <div>Grade</div>
            </div>
            <div class="col-md-4">
                <div class="score-box"><?php echo htmlspecialchars($eval['performance_status']); ?></div>
                <div>Status</div>
            </div>
        </div>
                <div>Grade</div>
            </div>
            <div class="col-md-3">
                <div class="score-box"><?php echo ucfirst($eval['status']); ?></div>
                <div>Status</div>
            </div>
        </div>

        <?php
        // Get only answered questions for each category
        $teachingData = getAnsweredQuestions($teaching, $staffResponses);
        $researchData = getAnsweredQuestions($research, $staffResponses);
        $adminData = getAnsweredQuestions($admin, $staffResponses);
        $communityData = getAnsweredQuestions($community, $staffResponses);
        $professionalData = getAnsweredQuestions($professional, $staffResponses);

        // Only show sections that have answered questions
        if (!empty($teachingData['questions'])):
        ?>
        <div class="question-section">
            <h5>Teaching Performance (<?php echo count($teachingData['questions']); ?> questions) <?php echo $isStaffOwnPrint ? '(Self-Assessment)' : ''; ?></h5>
            <?php foreach ($teachingData['questions'] as $q): ?>
            <div class="question-item">
                <span class="question-label"><?php echo $q['label']; ?></span>
                <span class="question-score"><?php echo $q['score']; ?>/5 (<?php echo getScoreLabel($q['score']); ?>)</span>
            </div>
            <?php endforeach; ?>
            <div class="question-item" style="font-weight: bold;">
                <span>Teaching Subtotal</span>
                <span><?php echo $teachingData['total']; ?>/<?php echo $teachingData['max']; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($researchData['questions'])): ?>
        <div class="question-section">
            <h5>Research Performance (<?php echo count($researchData['questions']); ?> questions) <?php echo $isStaffOwnPrint ? '(Self-Assessment)' : ''; ?></h5>
            <?php foreach ($researchData['questions'] as $q): ?>
            <div class="question-item">
                <span class="question-label"><?php echo $q['label']; ?></span>
                <span class="question-score"><?php echo $q['score']; ?>/5 (<?php echo getScoreLabel($q['score']); ?>)</span>
            </div>
            <?php endforeach; ?>
            <div class="question-item" style="font-weight: bold;">
                <span>Research Subtotal</span>
                <span><?php echo $researchData['total']; ?>/<?php echo $researchData['max']; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($adminData['questions'])): ?>
        <div class="question-section">
            <h5>Administrative Duties (<?php echo count($adminData['questions']); ?> questions) <?php echo $isStaffOwnPrint ? '(Self-Assessment)' : ''; ?></h5>
            <?php foreach ($adminData['questions'] as $q): ?>
            <div class="question-item">
                <span class="question-label"><?php echo $q['label']; ?></span>
                <span class="question-score"><?php echo $q['score']; ?>/5 (<?php echo getScoreLabel($q['score']); ?>)</span>
            </div>
            <?php endforeach; ?>
            <div class="question-item" style="font-weight: bold;">
                <span>Administrative Subtotal</span>
                <span><?php echo $adminData['total']; ?>/<?php echo $adminData['max']; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($communityData['questions'])): ?>
        <div class="question-section">
            <h5>Community Service (<?php echo count($communityData['questions']); ?> questions) <?php echo $isStaffOwnPrint ? '(Self-Assessment)' : ''; ?></h5>
            <?php foreach ($communityData['questions'] as $q): ?>
            <div class="question-item">
                <span class="question-label"><?php echo $q['label']; ?></span>
                <span class="question-score"><?php echo $q['score']; ?>/5 (<?php echo getScoreLabel($q['score']); ?>)</span>
            </div>
            <?php endforeach; ?>
            <div class="question-item" style="font-weight: bold;">
                <span>Community Subtotal</span>
                <span><?php echo $communityData['total']; ?>/<?php echo $communityData['max']; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($professionalData['questions'])): ?>
        <div class="question-section">
            <h5>Professional Development (<?php echo count($professionalData['questions']); ?> questions) <?php echo $isStaffOwnPrint ? '(Self-Assessment)' : ''; ?></h5>
            <?php foreach ($professionalData['questions'] as $q): ?>
            <div class="question-item">
                <span class="question-label"><?php echo $q['label']; ?></span>
                <span class="question-score"><?php echo $q['score']; ?>/5 (<?php echo getScoreLabel($q['score']); ?>)</span>
            </div>
            <?php endforeach; ?>
            <div class="question-item" style="font-weight: bold;">
                <span>Professional Subtotal</span>
                <span><?php echo $professionalData['total']; ?>/<?php echo $professionalData['max']; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="footer">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Submitted on:</strong> <?php echo date('F j, Y, g:i A', strtotime($eval['created_at'])); ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <p><strong>Performance Status:</strong> <?php echo htmlspecialchars($eval['performance_status']); ?></p>
                </div>
            </div>
            <p class="text-center text-muted"><?php echo !empty($settings['copyright_text']) ? htmlspecialchars($settings['copyright_text']) : htmlspecialchars($instName) . ' - Annual Performance Evaluation Report'; ?></p>
            <p class="text-center text-muted" style="font-size: 0.8rem;">This is a computer-generated document. No signature required.</p>
        </div>
    </div>
</body>
</html>