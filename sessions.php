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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDBConnection();

        if (isset($_POST['add_session'])) {
            $customYear = intval($_POST['custom_year'] ?? date('Y'));
            $sessionName = $customYear . '/' . ($customYear + 1);
            $stmt = $pdo->prepare("INSERT INTO academic_sessions (session_name, semester, year, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $sessionName,
                sanitize($_POST['semester']),
                intval($_POST['year']),
                isset($_POST['is_active']) ? 1 : 0
            ]);
            showMessage('Academic session added successfully!', 'success');
        }

        if (isset($_POST['set_active'])) {
            // First, deactivate all
            $pdo->query("UPDATE academic_sessions SET is_active = 0");
            // Then activate the selected one
            $stmt = $pdo->prepare("UPDATE academic_sessions SET is_active = 1 WHERE id = ?");
            $stmt->execute([intval($_POST['session_id'])]);
            showMessage('Active session updated!', 'success');
        }

        if (isset($_POST['delete_session'])) {
            $stmt = $pdo->prepare("DELETE FROM academic_sessions WHERE id = ?");
            $stmt->execute([intval($_POST['session_id'])]);
            showMessage('Session deleted successfully!', 'success');
        }

        redirect('sessions.php');
    } catch (Exception $e) {
        showMessage('Error: ' . $e->getMessage(), 'danger');
    }
}

// Get all sessions
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT * FROM academic_sessions ORDER BY year DESC, semester DESC");
$sessions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Sessions - <?php echo htmlspecialchars($institutionName); ?></title>
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
        .hamburger { display: none; background: none; border: none; cursor: pointer; padding: 10px; z-index: 1001; position: fixed; top: 10px; left: 10px; }
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
    <!-- Mobile Hamburger Menu -->
    <button class="hamburger" onclick="toggleSidebar()">
        <span></span><span></span><span></span>
    </button>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3" id="sidebar">
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
                    <a href="evaluate.php"><i class="fas fa-clipboard-check"></i> Evaluate</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                    <a href="sessions.php" class="active"><i class="fas fa-calendar"></i> Sessions</a>
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

                <h2 class="mb-4"><i class="fas fa-calendar me-2"></i>Academic Sessions</h2>

                <!-- Add New Session -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add New Session</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Start Year</label>
                                <input type="number" class="form-control" name="custom_year" value="" placeholder="e.g., 2024" min="2000" max="2100" required>
                                <small class="text-muted">Enter the starting year (e.g., 2024 for 2024/2025)</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Semester</label>
                                <select class="form-select" name="semester" required>
                                    <option value="First">First Semester</option>
                                    <option value="Second">Second Semester</option>
                                    <option value="Annual">Annual</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Evaluation Year</label>
                                <input type="number" class="form-control" name="year" value="<?php echo date('Y'); ?>" min="2000" max="2100" required>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Active</label>
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                    <label class="form-check-label" for="is_active">Yes</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="add_session" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Add Session
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sessions List -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Existing Sessions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Session</th>
                                        <th>Semester</th>
                                        <th>Year</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sessions)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No sessions found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($sessions as $session): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($session['session_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($session['semester']); ?></td>
                                            <td><?php echo $session['year']; ?></td>
                                            <td>
                                                <?php if ($session['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$session['is_active']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                        <button type="submit" name="set_active" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> Set Active
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this session?');">
                                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                    <button type="submit" name="delete_session" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.hamburger').classList.toggle('active');
            document.querySelector('.sidebar-overlay').classList.toggle('active');
        }
    </script>
</body>
</html>