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
$primaryColor = $settings['primary_color'] ?? '#1e3a8a';
$secondaryColor = $settings['secondary_color'] ?? '#3b82f6';

$message = getMessage();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDBConnection();

        if (isset($_POST['add_session'])) {
            $stmt = $pdo->prepare("INSERT INTO academic_sessions (session_name, semester, year, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                sanitize($_POST['session_name']),
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
    <title>Academic Sessions - APER Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: #1e3a8a; }
        body { background: #f3f4f6; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%); color: white; }
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
                    <i class="fas fa-graduation-cap fa-2x mb-2"></i>
                    <h5 class="mb-0">APER System</h5>
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
                                <label class="form-label">Session Name</label>
                                <select class="form-select" name="session_name" required>
                                    <?php
                                    $currentYear = date('Y');
                                    for ($y = $currentYear + 1; $y >= $currentYear - 5; $y--) {
                                        echo "<option value='{$y}/" . ($y + 1) . "'>{$y}/" . ($y + 1) . "</option>";
                                    }
                                    ?>
                                </select>
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
                                <label class="form-label">Year</label>
                                <input type="number" class="form-control" name="year" value="<?php echo date('Y'); ?>" min="2020" max="2030" required>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Active</label>
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active">
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
</body>
</html>