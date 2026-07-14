<?php
require_once 'config.php';
requireAdminLogin();

$messageData = getMessage();
$message = '';
if ($messageData && is_array($messageData)) {
    $messageType = $messageData['type'] ?? 'success';
    $messageText = $messageData['message'] ?? '';
    $message = '<div class="alert alert-' . $messageType . '">' . $messageText . '</div>';
}

$pdo = getDBConnection();

// Get settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$instName = $settings['institution_name'] ?? 'Institution';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_hod_questions'])) {
        // Save HOD questions as JSON
        $hodQuestions = [
            'teaching' => [
                ['name' => 'teaching_1', 'label' => sanitize($_POST['teaching_1'] ?? 'Teaching Quality')],
                ['name' => 'teaching_2', 'label' => sanitize($_POST['teaching_2'] ?? 'Class Control')],
                ['name' => 'teaching_3', 'label' => sanitize($_POST['teaching_3'] ?? 'Course Preparation')],
            ],
            'research' => [
                ['name' => 'research_1', 'label' => sanitize($_POST['research_1'] ?? 'Research Output')],
                ['name' => 'research_2', 'label' => sanitize($_POST['research_2'] ?? 'Publications')],
            ],
            'admin' => [
                ['name' => 'admin_1', 'label' => sanitize($_POST['admin_1'] ?? 'Punctuality')],
                ['name' => 'admin_2', 'label' => sanitize($_POST['admin_2'] ?? 'Administrative Duties')],
                ['name' => 'admin_3', 'label' => sanitize($_POST['admin_3'] ?? 'Teamwork')],
            ],
            'community' => [
                ['name' => 'community_1', 'label' => sanitize($_POST['community_1'] ?? 'Community Service')],
            ],
            'professional' => [
                ['name' => 'professional_1', 'label' => sanitize($_POST['professional_1'] ?? 'Professional Development')],
            ],
        ];

        $json = json_encode($hodQuestions);

        // Check if setting exists
        $stmt = $pdo->query("SELECT id FROM settings WHERE setting_key = 'hod_questions'");
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'hod_questions'");
            $stmt->execute([$json]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('hod_questions', ?)");
            $stmt->execute([$json]);
        }
        showMessage('Supervising Officer questions saved successfully!', 'success');
        redirect('hod-questions.php');
    }
}

// Get current HOD questions
$hodQuestions = json_decode($settings['hod_questions'] ?? '', true);
if (!$hodQuestions) {
    // Default questions
    $hodQuestions = [
        'teaching' => [
            ['name' => 'teaching_1', 'label' => 'Teaching Quality'],
            ['name' => 'teaching_2', 'label' => 'Class Control & Attendance'],
            ['name' => 'teaching_3', 'label' => 'Course Material Preparation'],
        ],
        'research' => [
            ['name' => 'research_1', 'label' => 'Research Output'],
            ['name' => 'research_2', 'label' => 'Academic Publications'],
        ],
        'admin' => [
            ['name' => 'admin_1', 'label' => 'Punctuality & Attendance'],
            ['name' => 'admin_2', 'label' => 'Administrative Duties'],
            ['name' => 'admin_3', 'label' => 'Teamwork & Cooperation'],
        ],
        'community' => [
            ['name' => 'community_1', 'label' => 'Community Service'],
        ],
        'professional' => [
            ['name' => 'professional_1', 'label' => 'Professional Development'],
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configure Supervising Officer Questions - <?php echo htmlspecialchars($instName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="theme-overrides.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: <?php echo $settings['primary_color'] ?? '#308a1e'; ?>;
            --secondary: <?php echo $settings['secondary_color'] ?? '#269c16'; ?>;
        }
        body { background: #f5f5f5; }
        .sidebar { background: linear-gradient(135deg, var(--primary), var(--secondary)); min-height: 100vh; padding: 20px; }
        .sidebar a { color: white; text-decoration: none; padding: 12px 15px; display: block; border-radius: 5px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.2); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <h4 class="text-white mb-4"><i class="fas fa-university"></i> <?php echo htmlspecialchars($instName); ?></h4>
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="hod-questions.php" class="active"><i class="fas fa-question-circle"></i> Supervising Officer Questions</a>
                <a href="logout.php" class="text-warning"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content p-4">
                <?php echo $message; ?>

                <h2 class="mb-4"><i class="fas fa-cog"></i> Configure Supervising Officer Evaluation Questions</h2>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> These questions will be shown to Supervising Officers when evaluating staff in their department.
                    Dean and Registrar will only see the summary and add comments - they will not answer these questions.
                </div>

                <form method="POST" class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Supervising Officer Evaluation Questions</h5>
                    </div>
                    <div class="card-body">
                        <!-- Teaching Questions -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 text-primary">Teaching Performance (3 questions)</h6>
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Question 1</label>
                                    <input type="text" name="teaching_1" class="form-control" value="<?php echo htmlspecialchars($hodQuestions['teaching'][0]['label'] ?? 'Teaching Quality'); ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Question 2</label>
                                    <input type="text" name="teaching_2" class="form-control" value="<?php echo htmlspecialchars($hodQuestions['teaching'][1]['label'] ?? 'Class Control'); ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Question 3</label>
                                    <input type="text" name="teaching_3" class="form-control" value="<?php echo htmlspecialchars($hodQuestions['teaching'][2]['label'] ?? 'Course Preparation'); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Research Questions -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 text-primary">Research Performance (2 questions)</h6>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Question 1</label>
                                    <input type="text" name="research_1" class="form-control" value="<?php echo htmlspecialchars($hodQuestions['research'][0]['label'] ?? 'Research Output'); ?>">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Question 2</label>
                                    <input type="text" name="research_2" class="form-control" value="<?php echo htmlspecialchars($hodQuestions['research'][1]['label'] ?? 'Publications'); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Admin Questions -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 text-primary">Administrative Duties (3 questions)</h6>
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Question 1</label>
                                    <input type="text" name="admin_1" class="form-control" value="<?php echo htmlspecialchars($hodQuestions['admin'][0]['label'] ?? 'Punctuality'); ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Question 2</label>
                                    <input type="text" name="admin_2" class="form-control" value="<?php echo htmlspecialchars($hodQuestions['admin'][1]['label'] ?? 'Administrative Duties'); ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Question 3</label>
                                    <input type="text" name="admin_3" class="form-control" value="<?php echo htmlspecialchars($hodQuestions['admin'][2]['label'] ?? 'Teamwork'); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Community -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 text-primary">Community Service (1 question)</h6>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Question 1</label>
                                    <input type="text" name="community_1" class="form-control" value="<?php echo htmlspecialchars($hodQuestions['community'][0]['label'] ?? 'Community Service'); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Professional -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 text-primary">Professional Development (1 question)</h6>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Question 1</label>
                                    <input type="text" name="professional_1" class="form-control" value="<?php echo htmlspecialchars($hodQuestions['professional'][0]['label'] ?? 'Professional Development'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" name="save_hod_questions" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Questions
                        </button>
                        <a href="settings.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>