<?php
require_once 'config.php';
requireAdminLogin();

// Check if user has permission to download all data
if (!hasPermission('download_all_data')) {
    die("You don't have permission to download all data.");
}

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
$primaryColor = $settings['primary_color'] ?? '#1e3a8a';
$secondaryColor = $settings['secondary_color'] ?? '#3b82f6';

// Handle export to Excel
if (isset($_GET['export']) && $_GET['export'] == 'all') {
    // Get all evaluations with staff details
    $stmt = $pdo->query("
        SELECT
            s.staff_id,
            s.surname,
            s.first_name,
            s.email,
            s.department,
            s.faculty,
            s.designation,
            s.grade_level,
            s.staff_category,
            e.evaluation_year,
            e.teaching_1, e.teaching_2, e.teaching_3, e.teaching_4, e.teaching_5, e.teaching_6,
            e.research_1, e.research_2, e.research_3, e.research_4, e.research_5,
            e.admin_1, e.admin_2, e.admin_3, e.admin_4, e.admin_5,
            e.community_1, e.community_2, e.community_3,
            e.professional_1, e.professional_2, e.professional_3, e.professional_4,
            e.total_score,
            e.percentage,
            e.performance_grade,
            e.performance_status,
            e.status,
            e.created_at
        FROM evaluations e
        JOIN staff s ON e.staff_id = s.id
        ORDER BY e.created_at DESC
    ");
    $evaluations = $stmt->fetchAll();

    // Generate Excel-like CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="all_evaluation_data_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Header row
    fputcsv($output, [
        'Staff ID', 'Surname', 'First Name', 'Email', 'Department', 'Faculty',
        'Designation', 'Grade Level', 'Staff Category', 'Year',
        'Teaching 1', 'Teaching 2', 'Teaching 3', 'Teaching 4', 'Teaching 5', 'Teaching 6',
        'Research 1', 'Research 2', 'Research 3', 'Research 4', 'Research 5',
        'Admin 1', 'Admin 2', 'Admin 3', 'Admin 4', 'Admin 5',
        'Community 1', 'Community 2', 'Community 3',
        'Professional 1', 'Professional 2', 'Professional 3', 'Professional 4',
        'Total Score', 'Percentage', 'Grade', 'Status', 'Performance Status', 'Submission Date'
    ]);

    // Data rows
    foreach ($evaluations as $row) {
        fputcsv($output, [
            $row['staff_id'],
            $row['surname'],
            $row['first_name'],
            $row['email'],
            $row['department'],
            $row['faculty'],
            $row['designation'],
            $row['grade_level'],
            $row['staff_category'],
            $row['evaluation_year'],
            $row['teaching_1'], $row['teaching_2'], $row['teaching_3'], $row['teaching_4'], $row['teaching_5'], $row['teaching_6'],
            $row['research_1'], $row['research_2'], $row['research_3'], $row['research_4'], $row['research_5'],
            $row['admin_1'], $row['admin_2'], $row['admin_3'], $row['admin_4'], $row['admin_5'],
            $row['community_1'], $row['community_2'], $row['community_3'],
            $row['professional_1'], $row['professional_2'], $row['professional_3'], $row['professional_4'],
            $row['total_score'],
            $row['percentage'],
            $row['performance_grade'],
            $row['status'],
            $row['performance_status'],
            $row['created_at']
        ]);
    }

    fclose($output);
    exit;
}

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM evaluations WHERE status = 'submitted'");
$totalSubmitted = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM staff");
$totalStaff = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM evaluations");
$totalEvaluations = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT AVG(percentage) as avg FROM evaluations WHERE status = 'submitted'");
$avgScore = $stmt->fetch()['avg'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Data - <?php echo htmlspecialchars($instName); ?></title>
    <?php if (!empty($logo)): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logo); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: <?php echo $primaryColor; ?>; }
        body { background: #f3f4f6; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%); color: white; }
        .sidebar a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 12px 15px; display: block; border-radius: 8px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center py-4 border-bottom border-secondary">
                    <?php if (!empty($logo)): ?>
                        <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" style="max-height: 45px; margin-bottom: 8px; border: 2px solid white; border-radius: 6px; padding: 2px;">
                    <?php else: ?>
                        <i class="fas fa-graduation-cap fa-2x mb-2"></i>
                    <?php endif; ?>
                    <h5 class="mb-0" style="font-size: 1rem;"><?php echo htmlspecialchars($instName); ?></h5>
                </div>
                <div class="py-3">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="staff.php"><i class="fas fa-users"></i> Staff</a>
                    <a href="evaluate.php"><i class="fas fa-clipboard-check"></i> Evaluate</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                    <a href="download-data.php" class="active"><i class="fas fa-download"></i> Download Data</a>
                    <a href="sessions.php"><i class="fas fa-calendar"></i> Sessions</a>
                    <a href="logout.php" class="text-warning"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <h2 class="mb-4"><i class="fas fa-download me-2"></i>Download All Evaluation Data</h2>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show">
                        <?php echo $message['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-primary"><?php echo $totalStaff; ?></h3>
                                <p class="mb-0">Total Staff</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-success"><?php echo $totalSubmitted; ?></h3>
                                <p class="mb-0">Submitted Evaluations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-info"><?php echo $totalEvaluations; ?></h3>
                                <p class="mb-0">Total Evaluations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-warning"><?php echo number_format($avgScore, 1); ?>%</h3>
                                <p class="mb-0">Average Score</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Download Options -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-file-csv me-2"></i>Export Options</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Download All Data:</strong> Exports all evaluation data including staff details, scores per question, total scores, percentages, and performance grades.
                                </div>
                                <a href="?export=all" class="btn btn-success btn-lg">
                                    <i class="fas fa-download me-2"></i>Download All Evaluation Data (CSV)
                                </a>
                                <p class="text-muted mt-2">This will download a CSV file that can be opened in Excel or Google Sheets.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Preview -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-table me-2"></i>Recent Submissions Preview</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->query("
                            SELECT s.staff_id, s.surname, s.first_name, s.department, e.total_score, e.percentage, e.performance_grade, e.status, e.created_at
                            FROM evaluations e
                            JOIN staff s ON e.staff_id = s.id
                            ORDER BY e.created_at DESC
                            LIMIT 10
                        ");
                        $recentEvals = $stmt->fetchAll();
                        ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Staff ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Total Score</th>
                                        <th>Percentage</th>
                                        <th>Grade</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentEvals as $eval): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($eval['staff_id']); ?></td>
                                        <td><?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['surname']); ?></td>
                                        <td><?php echo htmlspecialchars($eval['department'] ?? 'N/A'); ?></td>
                                        <td><?php echo $eval['total_score']; ?>/115</td>
                                        <td><?php echo $eval['percentage']; ?>%</td>
                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($eval['performance_grade']); ?></span></td>
                                        <td><span class="badge bg-<?php echo $eval['status'] == 'submitted' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($eval['status']); ?></span></td>
                                        <td><?php echo date('M j, Y', strtotime($eval['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>