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
$stmt = $pdo->prepare("SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level, s.staff_category
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
    die('Access denied');
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation Summary - <?php echo htmlspecialchars($instName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                <div class="col-md-4"><strong>Staff ID:</strong> <?php echo htmlspecialchars($eval['staff_id']); ?></div>
                <div class="col-md-4"><strong>Name:</strong> <?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['surname']); ?></div>
                <div class="col-md-4"><strong>Category:</strong> <?php echo $eval['staff_category'] == 'academic' ? 'Academic Staff' : 'Non-Teaching Staff'; ?></div>
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
            <div class="col-md-3">
                <div class="score-box"><?php echo $eval['total_score']; ?>/115</div>
                <div>Total Score</div>
            </div>
            <div class="col-md-3">
                <div class="score-box"><?php echo $eval['percentage']; ?>%</div>
                <div>Percentage</div>
            </div>
            <div class="col-md-3">
                <div class="score-box"><?php echo htmlspecialchars($eval['performance_grade']); ?></div>
                <div>Grade</div>
            </div>
            <div class="col-md-3">
                <div class="score-box"><?php echo ucfirst($eval['status']); ?></div>
                <div>Status</div>
            </div>
        </div>

        <div class="question-section">
            <h5>Teaching Performance (6 questions)</h5>
            <?php foreach ($teaching as $q): ?>
            <div class="question-item">
                <span class="question-label"><?php echo $q['label']; ?></span>
                <span class="question-score"><?php echo $eval[$q['key']]; ?>/5 (<?php echo getScoreLabel($eval[$q['key']]); ?>)</span>
            </div>
            <?php endforeach; ?>
            <div class="question-item" style="font-weight: bold;">
                <span>Teaching Subtotal</span>
                <span><?php echo $eval['teaching_1'] + $eval['teaching_2'] + $eval['teaching_3'] + $eval['teaching_4'] + $eval['teaching_5'] + $eval['teaching_6']; ?>/30</span>
            </div>
        </div>

        <div class="question-section">
            <h5>Research Performance (5 questions)</h5>
            <?php foreach ($research as $q): ?>
            <div class="question-item">
                <span class="question-label"><?php echo $q['label']; ?></span>
                <span class="question-score"><?php echo $eval[$q['key']]; ?>/5 (<?php echo getScoreLabel($eval[$q['key']]); ?>)</span>
            </div>
            <?php endforeach; ?>
            <div class="question-item" style="font-weight: bold;">
                <span>Research Subtotal</span>
                <span><?php echo $eval['research_1'] + $eval['research_2'] + $eval['research_3'] + $eval['research_4'] + $eval['research_5']; ?>/25</span>
            </div>
        </div>

        <div class="question-section">
            <h5>Administrative Duties (5 questions)</h5>
            <?php foreach ($admin as $q): ?>
            <div class="question-item">
                <span class="question-label"><?php echo $q['label']; ?></span>
                <span class="question-score"><?php echo $eval[$q['key']]; ?>/5 (<?php echo getScoreLabel($eval[$q['key']]); ?>)</span>
            </div>
            <?php endforeach; ?>
            <div class="question-item" style="font-weight: bold;">
                <span>Administrative Subtotal</span>
                <span><?php echo $eval['admin_1'] + $eval['admin_2'] + $eval['admin_3'] + $eval['admin_4'] + $eval['admin_5']; ?>/25</span>
            </div>
        </div>

        <div class="question-section">
            <h5>Community Service (3 questions)</h5>
            <?php foreach ($community as $q): ?>
            <div class="question-item">
                <span class="question-label"><?php echo $q['label']; ?></span>
                <span class="question-score"><?php echo $eval[$q['key']]; ?>/5 (<?php echo getScoreLabel($eval[$q['key']]); ?>)</span>
            </div>
            <?php endforeach; ?>
            <div class="question-item" style="font-weight: bold;">
                <span>Community Subtotal</span>
                <span><?php echo $eval['community_1'] + $eval['community_2'] + $eval['community_3']; ?>/15</span>
            </div>
        </div>

        <div class="question-section">
            <h5>Professional Development (4 questions)</h5>
            <?php foreach ($professional as $q): ?>
            <div class="question-item">
                <span class="question-label"><?php echo $q['label']; ?></span>
                <span class="question-score"><?php echo $eval[$q['key']]; ?>/5 (<?php echo getScoreLabel($eval[$q['key']]); ?>)</span>
            </div>
            <?php endforeach; ?>
            <div class="question-item" style="font-weight: bold;">
                <span>Professional Subtotal</span>
                <span><?php echo $eval['professional_1'] + $eval['professional_2'] + $eval['professional_3'] + $eval['professional_4']; ?>/20</span>
            </div>
        </div>

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