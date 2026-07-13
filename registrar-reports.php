<?php
require_once 'config.php';

// Check if evaluator (Registrar) is logged in
if (!isEvaluatorLoggedIn() || getEvaluatorType() !== 'Registrar') {
    // Also check for admin login
    if (!isAdminLoggedIn() || getAdminRole() !== 'registrar') {
        redirect(SITE_URL . '/unified-login.php');
    }
}

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
$logo = $settings['institution_logo'] ?? '';

// Get filters
$filterYear = $_GET['year'] ?? date('Y');
$filterFaculty = $_GET['faculty'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query
$sql = "SELECT e.*, s.staff_id, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level
    FROM evaluations e
    JOIN staff s ON e.staff_id = s.id
    WHERE e.evaluation_year = ?";
$params = [$filterYear];

if (!empty($filterFaculty)) {
    $sql .= " AND s.faculty = ?";
    $params[] = $filterFaculty;
}

if (!empty($filterStatus)) {
    $sql .= " AND e.approval_status = ?";
    $params[] = $filterStatus;
}

if (!empty($searchQuery)) {
    $sql .= " AND (s.staff_id LIKE ? OR s.surname LIKE ? OR s.first_name LIKE ? OR s.department LIKE ? OR s.faculty LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$sql .= " ORDER BY s.surname, s.first_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$evaluations = $stmt->fetchAll();

// Get unique faculties
$stmt = $pdo->query("SELECT DISTINCT faculty FROM staff WHERE faculty IS NOT NULL AND faculty != '' ORDER BY faculty");
$faculties = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get stats - FIXED: Use evaluation_stage for accurate counting (only valid evaluations)
$stmt = $pdo->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN evaluation_stage = 'completed' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN evaluation_stage IN ('dean', 'registrar') THEN 1 ELSE 0 END) as processed,
    SUM(CASE WHEN evaluation_stage = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN approval_status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM evaluations WHERE evaluation_year = $filterYear AND (status = 'submitted' OR status = 'approved' OR evaluation_stage != 'pending')");
$stats = $stmt->fetch();

// Set default values for missing keys
$stats['total'] = $stats['total'] ?? 0;
$stats['approved'] = $stats['approved'] ?? 0;
$stats['pending'] = $stats['pending'] ?? 0;
$stats['rejected'] = $stats['rejected'] ?? 0;
$stats['processed'] = $stats['processed'] ?? 0;

$userName = $_SESSION['staff_name'] ?? $_SESSION['admin_name'] ?? 'Registrar';
$userRole = getEvaluatorType() ?? getAdminRole();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Reports - <?php echo htmlspecialchars($instName); ?></title>
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
        .dark-mode .card-header { background: #2d2d2d !important; color: #e0e0e0 !important; }
        .dark-mode .table { color: #e0e0e0 !important; }
        .dark-mode .table-light { background: #3d3d3d !important; color: #e0e0e0 !important; }
        .dark-mode .form-control { background: #3d3d3d !important; color: #e0e0e0 !important; border-color: #555 !important; }
        .dark-mode .form-select { background: #3d3d3d !important; color: #e0e0e0 !important; border-color: #555 !important; }
        .dark-mode .input-group-text { background: #3d3d3d !important; color: #e0e0e0 !important; border-color: #555 !important; }
        .dark-mode .text-muted { color: #aaa !important; }
        .dark-mode h2, .dark-mode h4, .dark-mode h5 { color: #e0e0e0 !important; }
        .dark-mode .badge { color: #fff !important; }
        .sidebar { background: linear-gradient(135deg, var(--primary), var(--secondary)); min-height: 100vh; padding: 20px; }
        .sidebar a { color: white; text-decoration: none; padding: 12px 15px; display: block; border-radius: 5px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.2); }
        .stat-card { border-radius: 10px; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .badge-approved { background: #22c55e; }
        .badge-rejected { background: #ef4444; }
        .badge-pending { background: #f59e0b; }
        .dark-mode-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <!-- Dark Mode Toggle -->
    <button class="btn btn-dark-mode-toggle dark-mode-toggle" onclick="toggleDarkMode()">
        <i class="fas fa-moon"></i> Dark Mode
    </button>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <h4 class="text-white mb-4"><i class="fas fa-university"></i> <?php echo htmlspecialchars($instName); ?></h4>
                <a href="evaluate-supervisor.php"><i class="fas fa-clipboard-check"></i> Evaluation</a>
                <a href="registrar-reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="logout.php" class="text-warning"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content p-4">
                <?php echo $message; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-chart-bar"></i> Registrar Reports</h2>
                        <p class="text-muted mb-0">Welcome, <?php echo htmlspecialchars($userName); ?> (Registrar)</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary fs-6">Academic Year: <?php echo $filterYear; ?></span>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $stats['total']; ?></h3>
                                <p class="mb-0">Total Evaluations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $stats['approved']; ?></h3>
                                <p class="mb-0">Approved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $stats['pending']; ?></h3>
                                <p class="mb-0">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-danger text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $stats['rejected']; ?></h3>
                                <p class="mb-0">Rejected</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter"></i> Filter & Search Reports</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Staff ID, Name, Department..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Academic Year</label>
                                <select name="year" class="form-select">
                                    <?php for($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Faculty</label>
                                <select name="faculty" class="form-select">
                                    <option value="">All Faculties</option>
                                    <?php foreach ($faculties as $fac): ?>
                                    <option value="<?php echo htmlspecialchars($fac); ?>" <?php echo $filterFaculty == $fac ? 'selected' : ''; ?>><?php echo htmlspecialchars($fac); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="Approved" <?php echo $filterStatus == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="Pending" <?php echo $filterStatus == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Rejected" <?php echo $filterStatus == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                <a href="registrar-reports.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Reports Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Evaluation Records</h5>
                        <span class="badge bg-secondary"><?php echo count($evaluations); ?> records</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Staff ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Faculty</th>
                                        <th>Final Score (HOD)</th>
                                        <th>Final Grade</th>
                                        <th>Approval Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($evaluations as $eval): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($eval['staff_id']); ?></td>
                                        <td><?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['surname']); ?></td>
                                        <td><?php echo htmlspecialchars($eval['department'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($eval['faculty'] ?? 'N/A'); ?></td>
                                        <td><?php echo $eval['percentage']; ?>%</td>
                                        <td><span class="badge bg-<?php echo $eval['performance_grade'] == 'A' ? 'success' : ($eval['performance_grade'] == 'B' ? 'primary' : ($eval['performance_grade'] == 'C' ? 'warning' : 'danger')); ?>"><?php echo $eval['performance_grade']; ?></span></td>
                                        <td>
                                            <span class="badge bg-<?php echo $eval['approval_status'] == 'Approved' ? 'success' : ($eval['approval_status'] == 'Rejected' ? 'danger' : 'warning'); ?>">
                                                <?php echo $eval['approval_status'] ?: 'Pending'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="print-summary.php?id=<?php echo $eval['id']; ?>" target="_blank" class="btn btn-sm btn-primary">
                                                <i class="fas fa-print"></i> Print
                                            </a>
                                            <a href="pdf-report.php?id=<?php echo $eval['id']; ?>" target="_blank" class="btn btn-sm btn-success">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($evaluations)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox me-2"></i>No evaluation records found
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Check for saved dark mode preference
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }

        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('darkMode', isDark);
        }
    </script>
</body>
</html>