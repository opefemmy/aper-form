<?php
require_once 'config.php';
requireAdminLogin();

$message = getMessage();
$pdo = getDBConnection();

// Get settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$instName = $settings['institution_name'] ?? 'Institution';
$instAddress = $settings['institution_address'] ?? '';
$logo = $settings['institution_logo'] ?? '';

// Get filter parameters
$filterStaff = $_GET['staff_id'] ?? '';
$filterSession = $_GET['session'] ?? '';

// Build query to get all evaluations with uploaded files
$query = "
    SELECT e.id as eval_id, e.staff_id, e.evaluation_year, e.status, e.evaluation_stage,
           s.surname, s.first_name, s.department, s.faculty, s.email,
           e.responses
    FROM evaluations e
    JOIN staff s ON e.staff_id = s.id
    WHERE e.status = 'submitted'
";

$params = [];

if ($filterStaff) {
    $query .= " AND (s.staff_id LIKE ? OR s.surname LIKE ? OR s.first_name LIKE ?)";
    $searchTerm = "%$filterStaff%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($filterSession) {
    $query .= " AND e.evaluation_year = ?";
    $params[] = $filterSession;
}

$query .= " ORDER BY e.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$evaluations = $stmt->fetchAll();

// Get all sessions for filter
$stmt = $pdo->query("SELECT DISTINCT evaluation_year FROM evaluations ORDER BY evaluation_year DESC");
$sessions = $stmt->fetchAll();

// Process evaluations to extract uploaded files
$evaluationsWithFiles = [];
foreach ($evaluations as $eval) {
    $responses = is_array($eval['responses']) ? $eval['responses'] : json_decode($eval['responses'], true);
    $files = [];

    if (is_array($responses)) {
        foreach ($responses as $qId => $response) {
            if (!empty($response) && is_string($response) && file_exists($response)) {
                // Get question text
                $q = $pdo->prepare("SELECT question_text, question_type FROM evaluation_questions WHERE id = ?");
                $q->execute([$qId]);
                $qRow = $q->fetch();
                $questionText = $qRow['question_text'] ?? 'Question #' . $qId;

                $files[] = [
                    'question_id' => $qId,
                    'question_text' => $questionText,
                    'file_path' => $response,
                    'file_name' => basename($response)
                ];
            }
        }
    }

    if (!empty($files)) {
        $eval['uploaded_files'] = $files;
        $evaluationsWithFiles[] = $eval;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uploaded Files - <?php echo htmlspecialchars($instName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="theme-overrides.css" rel="stylesheet">
    <style>
        :root { --primary-blue: <?php echo $settings['primary_color'] ?? '#247d57'; ?>; }
        body { background: #f3f4f6; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #247d57 0%, #1a5238 100%); color: white; }
        .sidebar a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 12px 15px; display: block; border-radius: 8px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); color: white; }
        .file-card { background: white; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; border: 1px solid #e5e7eb; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .file-icon { font-size: 2rem; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center sidebar-header">
                    <?php if (!empty($logo)): ?>
                        <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" style="max-height: 55px; margin-bottom: 10px;">
                    <?php else: ?>
                        <i class="fas fa-graduation-cap fa-2x mb-2" style="font-size: 2rem;"></i>
                    <?php endif; ?>
                    <h5 class="mb-0" style="font-weight: 800;"><?php echo htmlspecialchars($instName); ?></h5>
                </div>
                <div class="py-3">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="staff.php"><i class="fas fa-users"></i> Staff</a>
                    <a href="staff-upload.php"><i class="fas fa-upload"></i> Upload Staff</a>
                    <a href="manage-evaluators.php"><i class="fas fa-user-tie"></i> Evaluators</a>
                    <a href="questions.php"><i class="fas fa-question-circle"></i> Questions</a>
                    <a href="question-sub-categories.php"><i class="fas fa-folder-tree"></i> Sub-Categories</a>
                    <a href="evaluate.php"><i class="fas fa-clipboard-check"></i> Evaluate</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                    <a href="view-uploaded-files.php" class="active"><i class="fas fa-folder-open"></i> Uploaded Files</a>
                    <?php if (hasPermission('download_all_data')): ?>
                    <a href="download-data.php"><i class="fas fa-download"></i> Download Data</a>
                    <?php endif; ?>
                    <a href="sessions.php"><i class="fas fa-calendar"></i> Sessions</a>
                    <a href="logout.php" class="text-warning"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show">
                        <?php echo $message['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-folder-open me-2"></i>Uploaded Files</h2>
                    <div class="text-muted">
                        <small><?php echo count($evaluationsWithFiles); ?> evaluations with uploaded files</small>
                    </div>
                </div>

                <!-- Filter Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Search Staff</label>
                                <input type="text" class="form-control" name="staff_id" placeholder="Staff ID, Name..." value="<?php echo htmlspecialchars($filterStaff); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Session/Year</label>
                                <select class="form-select" name="session">
                                    <option value="">All Sessions</option>
                                    <?php foreach ($sessions as $s): ?>
                                    <option value="<?php echo $s['evaluation_year']; ?>" <?php echo $filterSession == $s['evaluation_year'] ? 'selected' : ''; ?>>
                                        <?php echo $s['evaluation_year']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Uploaded Files List -->
                <?php if (empty($evaluationsWithFiles)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No uploaded files found. Staff will upload files when answering file upload type questions.
                </div>
                <?php else: ?>

                <?php foreach ($evaluationsWithFiles as $eval): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['surname']); ?>
                            <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars($eval['staff_id']); ?></span>
                        </div>
                        <div>
                            <span class="badge bg-<?php echo $eval['evaluation_stage'] === 'completed' ? 'success' : 'warning'; ?>">
                                <?php echo strtoupper($eval['evaluation_stage']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3"><strong>Department:</strong> <?php echo htmlspecialchars($eval['department']); ?></div>
                            <div class="col-md-3"><strong>Faculty:</strong> <?php echo htmlspecialchars($eval['faculty'] ?? 'N/A'); ?></div>
                            <div class="col-md-3"><strong>Session:</strong> <?php echo $eval['evaluation_year']; ?></div>
                            <div class="col-md-3"><strong>Files:</strong> <?php echo count($eval['uploaded_files']); ?> files</div>
                        </div>

                        <div class="row">
                            <?php foreach ($eval['uploaded_files'] as $file): ?>
                            <?php
                            $fileExt = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                            $icon = 'fa-file text-secondary';
                            $iconClass = '';
                            if ($fileExt === 'pdf') { $icon = 'fa-file-pdf text-danger'; }
                            elseif (in_array($fileExt, ['doc', 'docx'])) { $icon = 'fa-file-word text-primary'; }
                            elseif (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif'])) { $icon = 'fa-file-image text-success'; }
                            ?>
                            <div class="col-md-6">
                                <div class="file-card">
                                    <div class="d-flex align-items-center">
                                        <i class="fas <?php echo $icon; ?> file-icon me-3"></i>
                                        <div class="flex-grow-1">
                                            <strong><?php echo htmlspecialchars($file['question_text']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($file['file_name']); ?></small>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="btn btn-primary btn-sm">
                                            <i class="fas fa-download me-1"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Footer -->
    <footer class="mt-4 py-3" style="background: linear-gradient(180deg, <?php echo $settings['primary_color'] ?? '#247d57'; ?> 0%, <?php echo $settings['secondary_color'] ?? '#1a5238'; ?> 100%); color: white; border-radius: 8px;">
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