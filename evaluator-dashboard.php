<?php
require_once 'config.php';

// Define the Appointment and Promotion Committee name (renamed from Dean)
define('APC_COMMITTEE_NAME', 'Appointment and Promotion Committee');

// Check if evaluator (Supervising Officer/Registrar) is logged in
if (!isEvaluatorLoggedIn()) {
    // Also check for admin login
    if (isAdminLoggedIn()) {
        redirect(SITE_URL . '/dashboard.php');
    } else {
        redirect(SITE_URL . '/unified-login.php');
    }
}

$pdo = getDBConnection();

// Get settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$instName = $settings['institution_name'] ?? 'Institution';

$evaluatorType = getEvaluatorType();
$evaluatorId = $_SESSION['staff_id'];
$evaluatorName = $_SESSION['staff_name'];
$evaluatorDept = $_SESSION['staff_department'] ?? '';
$evaluatorFac = $_SESSION['staff_faculty'] ?? '';

// Get stats based on evaluator type - New workflow: Staff -> Supervising Officer -> Staff Review -> Supervising Officer Final -> Registrar -> Completed
if ($evaluatorType === 'Supervising Officer') {
    // Supervising Officer pending: staff who have submitted but Supervising Officer hasn't evaluated yet (stage = 'pending')
    // OR staff who have reviewed and need final comments (stage = 'staff_review')
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluations e JOIN staff s ON e.staff_id = s.id WHERE s.department = ? AND e.evaluation_stage IN ('pending', 'staff_review') AND e.status = 'submitted'");
    $stmt->execute([$evaluatorDept]);
    $pendingCount = $stmt->fetchColumn();

    // Supervising Officer completed: staff who have been evaluated and passed to registrar or completed
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluations e JOIN staff s ON e.staff_id = s.id WHERE s.department = ? AND e.evaluation_stage IN ('supervising_officer', 'supervising_officer_final', 'registrar', 'completed') AND e.status = 'submitted'");
    $stmt->execute([$evaluatorDept]);
    $completedByHod = $stmt->fetchColumn();
} else {
    // Registrar pending: evaluations that have passed Supervising Officer final review and waiting for Registrar
    $stmt = $pdo->query("SELECT COUNT(*) FROM evaluations WHERE evaluation_stage IN ('registrar', 'supervising_officer_final') AND status = 'submitted'");
    $pendingCount = $stmt->fetchColumn();

    // Registrar completed: fully approved evaluations (stage = 'completed')
    $stmt = $pdo->query("SELECT COUNT(*) FROM evaluations WHERE evaluation_stage = 'completed'");
    $completedByHod = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluator Dashboard - <?php echo htmlspecialchars($instName); ?></title>
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
        .dark-mode { background: #1a1a1a !important; color: #e0e0e0 !important; }
        .dark-mode .card { background: #2d2d2d !important; color: #e0e0e0 !important; }
        .dark-mode h2, .dark-mode h4, .dark-mode h5 { color: #e0e0e0 !important; }
        .dark-mode .text-muted { color: #aaa !important; }
        .dark-mode-toggle { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .sidebar { background: linear-gradient(135deg, var(--primary), var(--secondary)); min-height: 100vh; padding: 20px; }
        .sidebar a { color: white; text-decoration: none; padding: 12px 15px; display: block; border-radius: 5px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.2); }
        .stat-card { border-radius: 10px; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
    <button class="btn btn-dark-mode-toggle dark-mode-toggle" onclick="toggleDarkMode()">
        <i class="fas fa-moon"></i> Dark Mode
    </button>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <h4 class="text-white mb-4"><i class="fas fa-university"></i> <?php echo htmlspecialchars($instName); ?></h4>
                <a href="evaluator-dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
                <a href="evaluate-supervisor.php"><i class="fas fa-clipboard-check"></i> Evaluations</a>
                <?php if ($evaluatorType === 'Registrar'): ?>
                <a href="registrar-reports.php"><i class="fas fa-chart-bar"></i> Reports & Print</a>
                <a href="download-data.php"><i class="fas fa-download"></i> Download Data</a>
                <?php endif; ?>
                <a href="logout.php" class="text-warning"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-tachometer-alt"></i> Welcome, <?php echo htmlspecialchars($evaluatorName); ?></h2>
                        <p class="text-muted mb-0">
                            <span class="badge bg-<?php echo $evaluatorType === 'Supervising Officer' ? 'warning' : 'info'; ?>">
                                <?php echo $evaluatorType; ?>
                            </span>
                            <?php if ($evaluatorType === 'Supervising Officer'): ?>
                            Department: <?php echo htmlspecialchars($evaluatorDept); ?>
                            <?php else: ?>
                            Full Access
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Stats -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body text-center">
                                <h2><?php echo $pendingCount; ?></h2>
                                <p class="mb-0">Pending Evaluations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body text-center">
                                <h2><?php echo $completedByHod; ?></h2>
                                <p class="mb-0">Evaluations Processed</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex gap-3 flex-wrap">
                                    <a href="evaluate-supervisor.php" class="btn btn-primary btn-lg">
                                        <i class="fas fa-clipboard-check me-2"></i>Start Evaluation
                                    </a>
                                    <?php if ($evaluatorType === 'Registrar'): ?>
                                    <a href="registrar-reports.php" class="btn btn-success btn-lg">
                                        <i class="fas fa-print me-2"></i>View Reports
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
        }
    </script>
</body>
</html>