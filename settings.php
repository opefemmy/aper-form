<?php
require_once 'config.php';
requireAdminLogin();

$message = getMessage();

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        $settings = [
            'institution_name' => sanitize($_POST['institution_name']),
            'institution_address' => sanitize($_POST['institution_address']),
            'website_url' => sanitize($_POST['website_url']),
            'academic_session' => sanitize($_POST['academic_session']),
            'semester' => sanitize($_POST['semester']),
            'evaluation_year' => sanitize($_POST['evaluation_year']),
            'email_from' => sanitize($_POST['email_from']),
            'email_to' => sanitize($_POST['email_to']),
            'smtp_host' => sanitize($_POST['smtp_host']),
            'smtp_port' => sanitize($_POST['smtp_port']),
            'smtp_username' => sanitize($_POST['smtp_username']),
            'smtp_password' => sanitize($_POST['smtp_password']),
            'primary_color' => sanitize($_POST['primary_color']),
            'secondary_color' => sanitize($_POST['secondary_color']),
            'login_background_image' => $settings['login_background_image'] ?? '',
        ];

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }

        // Handle logo upload
        if (isset($_FILES['institution_logo']) && $_FILES['institution_logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['institution_logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

            if (!in_array($ext, $allowed)) {
                showMessage('Invalid file type. Allowed: jpg, jpeg, png, gif, svg, webp', 'danger');
            } else {
                $filename = 'logo.' . $ext;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['institution_logo']['tmp_name'], $targetPath)) {
                    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('institution_logo', ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    $stmt->execute([SITE_URL . '/uploads/' . $filename]);
                    showMessage('Logo uploaded successfully!', 'success');
                }
            }
        }

        // Handle login background image upload
        if (isset($_FILES['login_background_image']) && $_FILES['login_background_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['login_background_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed)) {
                showMessage('Invalid file type for background. Allowed: jpg, jpeg, png, gif, webp', 'danger');
            } else {
                $filename = 'login_background.' . $ext;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['login_background_image']['tmp_name'], $targetPath)) {
                    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('login_background_image', ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    $stmt->execute([SITE_URL . '/uploads/' . $filename]);
                    showMessage('Login background image uploaded successfully!', 'success');
                }
            }
        }

        // Handle remove login background
        if (isset($_POST['remove_login_background']) && $settings['login_background_image']) {
            $bgPath = str_replace(SITE_URL, __DIR__, $settings['login_background_image']);
            if (file_exists($bgPath)) {
                unlink($bgPath);
            }
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('login_background_image', '')
                ON DUPLICATE KEY UPDATE setting_value = ''");
            $stmt->execute();
            showMessage('Login background removed!', 'success');
        }

        // Handle grade levels
        if (isset($_POST['save_grade_levels'])) {
            $gradeLevels = $_POST['grade_levels'] ?? [];
            $pdo->exec("TRUNCATE TABLE grade_levels");
            $stmt = $pdo->prepare("INSERT INTO grade_levels (level_name, level_order, is_active) VALUES (?, ?, ?)");
            foreach ($gradeLevels as $index => $levelName) {
                if (!empty(trim($levelName))) {
                    $stmt->execute([trim($levelName), $index + 1, 1]);
                }
            }
            showMessage('Grade levels saved successfully!', 'success');
        }

        $pdo->commit();
        showMessage('Settings saved successfully!', 'success');
        redirect('settings.php');
    } catch (Exception $e) {
        $pdo->rollBack();
        showMessage('Error saving settings: ' . $e->getMessage(), 'danger');
    }
}

// Get current settings
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get grade levels from database
$stmt = $pdo->query("SELECT * FROM grade_levels WHERE is_active = 1 ORDER BY level_order");
$gradeLevels = $stmt->fetchAll();
if (empty($gradeLevels)) {
    // Default grade levels if table is empty
    $gradeLevels = [];
    for ($i = 1; $i <= 10; $i++) {
        $gradeLevels[] = ['level_name' => "Level $i", 'level_order' => $i, 'is_active' => 1];
    }
}

$instName = $settings['institution_name'] ?? 'Institution';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo htmlspecialchars($instName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: #1e3a8a; }
        body { background: #f3f4f6; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%); color: white; }
        .sidebar a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 12px 15px; display: block; border-radius: 8px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); color: white; }
        .sidebar a i { width: 25px; }

        /* Mobile Hamburger Menu */
        .hamburger { display: none; background: none; border: none; cursor: pointer; padding: 10px; z-index: 1001; }
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
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center py-4 border-bottom border-secondary">
                    <i class="fas fa-graduation-cap fa-2x mb-2"></i>
                    <h5 class="mb-0"><?php echo htmlspecialchars($instName); ?></h5>
                </div>
                <div class="py-3">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a>
                    <a href="staff.php"><i class="fas fa-users"></i> Staff</a>
                    <a href="staff-upload.php"><i class="fas fa-upload"></i> Upload Staff</a>
                    <a href="questions.php"><i class="fas fa-question-circle"></i> Questions</a>
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
            <div class="col-md-9 col-lg-10 p-4">
                <!-- Mobile Menu Button -->
                <button class="hamburger position-fixed" style="top: 10px; left: 10px;" onclick="toggleSidebar()">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show">
                        <?php echo $message['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <h2 class="mb-4"><i class="fas fa-cog me-2"></i>System Settings</h2>

                <form method="POST" enctype="multipart/form-data">
                    <!-- Institution Settings -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-university me-2"></i>Institution Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Institution Name</label>
                                    <input type="text" class="form-control" name="institution_name"
                                           value="<?php echo htmlspecialchars($settings['institution_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Institution Address</label>
                                    <input type="text" class="form-control" name="institution_address"
                                           value="<?php echo htmlspecialchars($settings['institution_address'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Website URL</label>
                                    <input type="url" class="form-control" name="website_url"
                                           value="<?php echo htmlspecialchars($settings['website_url'] ?? 'https://ekscotech.edu.ng'); ?>" placeholder="https://yourwebsite.edu.ng">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Institution Logo</label>
                                    <input type="file" class="form-control" name="institution_logo" accept="image/*">
                                    <?php if (!empty($settings['institution_logo'])): ?>
                                        <img src="<?php echo $settings['institution_logo']; ?>" class="mt-2" style="max-height: 80px;">
                                        <small class="text-muted d-block">Current logo shown</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Settings -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Academic Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Academic Session</label>
                                    <select class="form-select" name="academic_session">
                                        <?php
                                        $currentYear = date('Y');
                                        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                            $session = ($y-1) . '/' . $y;
                                            $selected = ($settings['academic_session'] ?? '') == $session ? 'selected' : '';
                                            echo "<option value='$session' $selected>$session</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Semester</label>
                                    <select class="form-select" name="semester">
                                        <option value="First" <?php echo ($settings['semester'] ?? '') == 'First' ? 'selected' : ''; ?>>First Semester</option>
                                        <option value="Second" <?php echo ($settings['semester'] ?? '') == 'Second' ? 'selected' : ''; ?>>Second Semester</option>
                                        <option value="Annual" <?php echo ($settings['semester'] ?? '') == 'Annual' ? 'selected' : ''; ?>>Annual</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Evaluation Year</label>
                                    <input type="number" class="form-control" name="evaluation_year"
                                           value="<?php echo $settings['evaluation_year'] ?? date('Y'); ?>" min="2020" max="2030">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Email Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email From Address</label>
                                    <input type="email" class="form-control" name="email_from"
                                           value="<?php echo htmlspecialchars($settings['email_from'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email To Address (for reports)</label>
                                    <input type="email" class="form-control" name="email_to"
                                           value="<?php echo htmlspecialchars($settings['email_to'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Color Settings -->
                    <div class="card mb-4">
                        <div class="card-header bg-info">
                            <h5 class="mb-0"><i class="fas fa-palette me-2"></i>Color Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Primary Color</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" name="primary_color" value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#1e3a8a'); ?>">
                                        <input type="text" class="form-control" name="primary_color_text" value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#1e3a8a'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Secondary Color</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" name="secondary_color" value="<?php echo htmlspecialchars($settings['secondary_color'] ?? '#3b82f6'); ?>">
                                        <input type="text" class="form-control" name="secondary_color_text" value="<?php echo htmlspecialchars($settings['secondary_color'] ?? '#3b82f6'); ?>">
                                    </div>
                                </div>
                            </div>
                            <p class="text-muted">These colors will be used throughout the system.</p>
                        </div>
                    </div>

                    <!-- SMTP Settings -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0"><i class="fas fa-server me-2"></i>SMTP Configuration</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SMTP Host</label>
                                    <input type="text" class="form-control" name="smtp_host"
                                           value="<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com'); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">SMTP Port</label>
                                    <input type="text" class="form-control" name="smtp_port"
                                           value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">SMTP Encryption</label>
                                    <select class="form-select" name="smtp_encryption">
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SMTP Username</label>
                                    <input type="text" class="form-control" name="smtp_username"
                                           value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SMTP Password</label>
                                    <input type="password" class="form-control" name="smtp_password"
                                           value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" placeholder="App password for Gmail">
                                    <small class="text-muted">For Gmail, use an App Password</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Login Page Settings -->
                    <div class="card mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-image me-2"></i>Login Page Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Login Page Background Image</label>
                                    <input type="file" class="form-control" name="login_background_image" accept="image/*">
                                    <?php if (!empty($settings['login_background_image'])): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo $settings['login_background_image']; ?>" style="max-height: 150px; border-radius: 8px;">
                                            <small class="text-muted d-block">Current background shown</small>
                                            <button type="submit" name="remove_login_background" class="btn btn-sm btn-danger mt-2">
                                                <i class="fas fa-trash me-1"></i>Remove Background
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <small class="text-muted">Recommended size: 1920x1080px. This will appear as background on the login page.</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grade Levels Settings -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Grade Levels</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Define the grade levels that can be assigned to staff. Click "Save Grade Levels" after making changes.</p>
                            <div id="gradeLevelsContainer">
                                <?php foreach ($gradeLevels as $index => $level): ?>
                                <div class="row mb-2 grade-level-row">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" name="grade_levels[]" value="<?php echo htmlspecialchars($level['level_name']); ?>" placeholder="e.g., Level 1">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="addGradeLevel()">
                                <i class="fas fa-plus me-1"></i>Add Level
                            </button>
                            <button type="submit" name="save_grade_levels" class="btn btn-success mt-2">
                                <i class="fas fa-save me-1"></i>Save Grade Levels
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="save_settings" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Save Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script>
    function addGradeLevel() {
        const container = document.getElementById('gradeLevelsContainer');
        const row = document.createElement('div');
        row.className = 'row mb-2 grade-level-row';
        row.innerHTML = '<div class="col-md-6"><input type="text" class="form-control" name="grade_levels[]" value="" placeholder="e.g., Level ' + (container.children.length + 1) + '"></div>';
        container.appendChild(row);
    }
    </script>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <script>
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
        document.querySelector('.sidebar-overlay').classList.toggle('active');
        document.querySelector('.hamburger').classList.toggle('active');
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelector('.sidebar').classList.remove('active');
            document.querySelector('.sidebar-overlay').classList.remove('active');
            document.querySelector('.hamburger').classList.remove('active');
        }
    });
    </script>
</body>
</html>