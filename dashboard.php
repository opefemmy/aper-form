<?php
require_once 'config.php';

// Check for evaluator login first - redirect to evaluator dashboard
if (isEvaluatorLoggedIn()) {
    redirect(SITE_URL . '/evaluator-dashboard.php');
}

requireAdminLogin();

$admin = getCurrentAdmin();

// Get statistics
$pdo = getDBConnection();

// Total active staff
$stmt = $pdo->query("SELECT COUNT(*) as total FROM staff WHERE status = 'active'");
$staffCount = $stmt->fetch()['total'];

// Total evaluations - only count submitted evaluations (valid evaluations)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM evaluations WHERE status = 'submitted' OR status = 'approved' OR evaluation_stage != 'pending'");
$evalCount = $stmt->fetch()['total'];

// Pending evaluations - staff submitted but awaiting HOD evaluation
$stmt = $pdo->query("SELECT COUNT(*) as total FROM evaluations WHERE evaluation_stage = 'pending' AND status = 'submitted'");
$pendingCount = $stmt->fetch()['total'];

// Completed evaluations - fully approved (stage = completed)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM evaluations WHERE evaluation_stage = 'completed'");
$completedCount = $stmt->fetch()['total'];

// Recent evaluations - only show valid evaluations that have been submitted
$stmt = $pdo->query("
    SELECT e.*, CONCAT(s.first_name, ' ', s.surname) as full_name, s.staff_id, s.department
    FROM evaluations e
    JOIN staff s ON e.staff_id = s.id
    WHERE e.status = 'submitted' OR e.status = 'approved'
    ORDER BY e.created_at DESC
    LIMIT 5
");
$recentEvals = $stmt->fetchAll();

// Get settings
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get colors
$primaryColor = $settings['primary_color'] ?? '#308a1e';
$secondaryColor = $settings['secondary_color'] ?? '#269c16';
$logo = $settings['institution_logo'] ?? '';
$instName = $settings['institution_name'] ?? 'Institution';
$instAddress = $settings['institution_address'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($instName); ?></title>
    <?php if (!empty($logo)): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logo); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="theme-overrides.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: <?php echo $primaryColor; ?>;
            --secondary-blue: <?php echo $secondaryColor; ?>;
        }
        body {
            background: #f3f4f6;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #308a1e 0%, #269c16 100%);
            color: white;
        }
        .sidebar .sidebar-header {
            padding: 15px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.3);
            margin-bottom: 10px;
        }
        .sidebar .sidebar-header h5 {
            font-size: 1.1rem !important;
            font-weight: 800 !important;
            margin: 8px 0 5px 0;
            line-height: 1.3;
            color: #10b981 !important;
        }
        .sidebar .sidebar-header small {
            color: #10b981 !important;
            font-weight: 600;
            opacity: 0.9;
        }
        .sidebar .sidebar-header small {
            font-size: 0.8rem !important;
            font-weight: 600 !important;
            opacity: 0.95;
        }
        .sidebar .sidebar-header img {
            border: 2px solid white !important;
            border-radius: 8px !important;
            max-height: 55px;
        }
        .sidebar a {
            color: rgba(255,255,255,0.95);
            text-decoration: none;
            padding: 10px 12px;
            display: block;
            border-radius: 6px;
            margin-bottom: 3px;
            transition: all 0.3s;
            font-size: 0.95rem;
            font-weight: 600;
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.25);
            color: white;
            font-weight: 700;
        }
        .sidebar a i {
            width: 28px;
            font-weight: 700;
        }
        .top-bar {
            background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%);
            color: white;
            padding: 1.2rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .top-bar h3 {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0;
        }
        .top-bar small {
            color: rgba(255,255,255,0.9);
        }
        .top-bar a {
            color: white;
            text-decoration: none;
        }
        .top-bar a:hover {
            text-decoration: underline;
        }
        .sidebar a i {
            width: 25px;
        }

        /* Mobile Hamburger Menu */
        .hamburger {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 10px;
            z-index: 1001;
        }
        .hamburger span {
            display: block;
            width: 25px;
            height: 3px;
            background: white;
            margin: 5px 0;
            border-radius: 2px;
            transition: 0.3s;
        }
        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 6px);
        }
        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }
        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(5px, -6px);
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .hamburger {
                display: block;
            }
            .sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                bottom: 0;
                width: 280px;
                z-index: 1000;
                transition: left 0.3s ease;
                overflow-y: auto;
            }
            .sidebar.active {
                left: 0;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
            .sidebar-overlay.active {
                display: block;
            }
            .top-bar {
                padding: 0.75rem 1rem !important;
            }
            .top-bar h3 {
                font-size: 1.1rem !important;
            }
            .top-bar img {
                max-height: 40px !important;
            }
            .stat-card {
                padding: 1rem;
            }
            .stat-card .value {
                font-size: 1.5rem;
            }
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #308a1e;
        }
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
                    <?php if (!empty($instAddress)): ?>
                        <small class="d-block" style="max-width: 180px; margin: 5px auto 0; font-weight: 600;"><?php echo htmlspecialchars($instAddress); ?></small>
                    <?php endif; ?>
                </div>
                <div class="py-3">
                    <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="master_password.php"><i class="fas fa-key"></i> Master Password</a>
                    <a href="staff.php"><i class="fas fa-users"></i> Staff</a>
                    <a href="staff-upload.php"><i class="fas fa-upload"></i> Upload Staff</a>
                    <a href="manage-evaluators.php"><i class="fas fa-user-tie"></i> Evaluators</a>
                    <a href="questions.php"><i class="fas fa-question-circle"></i> Questions</a>
                    <a href="roles.php"><i class="fas fa-user-tag"></i> Staff Roles</a>
                    <a href="evaluate.php"><i class="fas fa-clipboard-check"></i> Evaluate</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                    <?php if (hasPermission('download_all_data')): ?>
                    <a href="download-data.php"><i class="fas fa-download"></i> Download Data</a>
                    <?php endif; ?>
                    <a href="sessions.php"><i class="fas fa-calendar"></i> Sessions</a>
                    <a href="logout.php" class="text-warning"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-0">
                <!-- Top Bar -->
                <div class="top-bar">
                    <div class="container-fluid">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <!-- Mobile Menu Button -->
                                <button class="hamburger me-3" onclick="toggleSidebar()">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </button>
                                <?php if (!empty($logo)): ?>
                                <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" style="max-height: 55px; margin-right: 15px; border: 2px solid white; border-radius: 8px; padding: 3px; background: rgba(255,255,255,0.2);">
                                <?php endif; ?>
                                <div>
                                    <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars($instName); ?></h3>
                                    <?php if (!empty($instAddress)): ?>
                                    <small><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($instAddress); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <small class="text-white-50">Welcome, <?php echo htmlspecialchars($admin['name']); ?></small>
                                <br>
                                <a href="logout.php" class="text-white small"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Content -->
                <div class="p-4">

                <!-- Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="icon bg-primary text-white"><i class="fas fa-users"></i></div>
                                <div class="ms-3">
                                    <div class="value"><?php echo $staffCount; ?></div>
                                    <div class="text-muted">Active Staff</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="icon bg-info text-white"><i class="fas fa-clipboard-list"></i></div>
                                <div class="ms-3">
                                    <div class="value"><?php echo $evalCount; ?></div>
                                    <div class="text-muted">Total Evaluations</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="icon bg-warning text-white"><i class="fas fa-clock"></i></div>
                                <div class="ms-3">
                                    <div class="value"><?php echo $pendingCount; ?></div>
                                    <div class="text-muted">Pending</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="icon bg-success text-white"><i class="fas fa-check-circle"></i></div>
                                <div class="ms-3">
                                    <div class="value"><?php echo $completedCount; ?></div>
                                    <div class="text-muted">Completed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <a href="staff.php?action=add" class="text-decoration-none">
                            <div class="stat-card text-center">
                                <i class="fas fa-user-plus fa-2x text-primary mb-2"></i>
                                <h5>Add New Staff</h5>
                                <p class="text-muted mb-0">Register a new staff member</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="evaluate.php" class="text-decoration-none">
                            <div class="stat-card text-center">
                                <i class="fas fa-edit fa-2x text-success mb-2"></i>
                                <h5>Start Evaluation</h5>
                                <p class="text-muted mb-0">Evaluate a staff member</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="reports.php" class="text-decoration-none">
                            <div class="stat-card text-center">
                                <i class="fas fa-file-alt fa-2x text-info mb-2"></i>
                                <h5>View Reports</h5>
                                <p class="text-muted mb-0">View all evaluation reports</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Recent Evaluations -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Evaluations</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Staff ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Score</th>
                                        <th>Grade</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentEvals)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No evaluations yet</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentEvals as $eval): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($eval['staff_id']); ?></td>
                                            <td><?php echo htmlspecialchars($eval['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($eval['department']); ?></td>
                                            <td><?php echo $eval['total_score']; ?>/115</td>
                                            <td>
                                                <span class="badge bg-<?php
                                                    echo $eval['performance_grade'] == 'Outstanding' ? 'success' :
                                                        ($eval['performance_grade'] == 'Excellent' ? 'primary' :
                                                        ($eval['performance_grade'] == 'Very Good' ? 'info' :
                                                        ($eval['performance_grade'] == 'Good' ? 'warning' : 'danger')));
                                                ?>">
                                                    <?php echo $eval['performance_grade']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php
                                                    echo $eval['status'] == 'approved' ? 'success' :
                                                        ($eval['status'] == 'submitted' ? 'primary' : 'warning');
                                                ?>">
                                                    <?php echo ucfirst($eval['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($eval['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        const hamburger = document.querySelector('.hamburger');

        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        hamburger.classList.toggle('active');
    }

    // Close sidebar when pressing escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        }
    });

    // Close sidebar when window is resized to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const hamburger = document.querySelector('.hamburger');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            hamburger.classList.remove('active');
        }
    });
    </script>
    <!-- Footer -->
    <footer class="mt-4 py-3" style="background: linear-gradient(180deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%); color: white; border-radius: 8px;">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <small><?php echo !empty($settings['copyright_text']) ? htmlspecialchars($settings['copyright_text']) : '&copy; ' . date('Y') . ' ' . htmlspecialchars($instName) . '. All rights reserved.'; ?></small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>Powered by APER System</small>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>