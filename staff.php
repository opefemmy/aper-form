<?php
require_once 'config.php';
requireAdminLogin();

$message = getMessage();

// Get settings for institution details
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$instName = $settings['institution_name'] ?? 'Institution';
$instAddress = $settings['institution_address'] ?? '';
$logo = $settings['institution_logo'] ?? '';
$primaryColor = $settings['primary_color'] ?? '#308a1e';
$secondaryColor = $settings['secondary_color'] ?? '#269c16';

$message = getMessage();
$action = $_GET['action'] ?? 'list';
$staffId = $_GET['id'] ?? null;

// Get grade levels from database
$stmt = $pdo->query("SELECT * FROM grade_levels WHERE is_active = 1 ORDER BY level_order");
$gradeLevels = $stmt->fetchAll();
if (empty($gradeLevels)) {
    $gradeLevels = [];
    for ($i = 1; $i <= 10; $i++) {
        $gradeLevels[] = ['level_name' => "Level $i", 'level_order' => $i];
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDBConnection();

        if (isset($_POST['add_staff']) || isset($_POST['update_staff'])) {
            $surname = sanitize($_POST['surname']);
            $firstName = sanitize($_POST['first_name']);
            $data = [
                'staff_id' => sanitize($_POST['staff_id']),
                'surname' => $surname,
                'first_name' => $firstName,
                'email' => sanitize($_POST['email']),
                'phone' => sanitize($_POST['phone']),
                'department' => sanitize($_POST['department']),
                'faculty' => sanitize($_POST['faculty']),
                'designation' => sanitize($_POST['designation']),
                'grade_level' => sanitize($_POST['grade_level']),
                'employment_status' => sanitize($_POST['employment_status']),
                'staff_category' => sanitize($_POST['staff_category']),
                'years_of_service' => intval($_POST['years_of_service']),
            ];

            if (isset($_POST['update_staff']) && $staffId) {
                $stmt = $pdo->prepare("UPDATE staff SET staff_id=?, surname=?, first_name=?, email=?, phone=?, department=?, faculty=?, designation=?, grade_level=?, employment_status=?, staff_category=?, years_of_service=? WHERE id=?");
                $stmt->execute(array_merge(array_values($data), [$staffId]));
                showMessage('Staff member updated successfully!', 'success');
            } else {
                $stmt = $pdo->prepare("INSERT INTO staff (staff_id, surname, first_name, email, phone, department, faculty, designation, grade_level, employment_status, staff_category, years_of_service) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute(array_values($data));
                showMessage('Staff member added successfully!', 'success');
            }

            redirect('staff.php');
        }

        if (isset($_POST['delete_staff'])) {
            $deleteStaffId = intval($_POST['staff_id'] ?? 0);
            if ($deleteStaffId) {
                $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
                $stmt->execute([$deleteStaffId]);
                showMessage('Staff member deleted successfully!', 'success');
                redirect('staff.php');
            }
        }

        // Handle password reset
        if (isset($_POST['reset_password']) && $staffId) {
            $newPassword = sanitize($_POST['new_password'] ?? '');
            if (empty($newPassword) || strlen($newPassword) < 6) {
                showMessage('Password must be at least 6 characters', 'danger');
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE staff SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $staffId]);
                showMessage('Password reset successfully!', 'success');
            }
        }

        // Handle reset evaluation (allow staff to resubmit)
        if (isset($_POST['reset_evaluation']) && $staffId) {
            $stmt = $pdo->prepare("UPDATE evaluations SET status = 'draft', can_retake = 1 WHERE staff_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$staffId]);
            showMessage('Evaluation reset! Staff can now resubmit their form.', 'success');
        }
    } catch (Exception $e) {
        showMessage('Error: ' . $e->getMessage(), 'danger');
    }
}

// Get single staff if editing
$staffMember = null;
if ($staffId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([$staffId]);
    $staffMember = $stmt->fetch();
}

// Get all staff
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT * FROM staff ORDER BY surname, first_name");
$staffList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - <?php echo htmlspecialchars($instName); ?></title>
    <?php if (!empty($logo)): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logo); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="theme-overrides.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: <?php echo $primaryColor; ?>; --secondary-blue: <?php echo $secondaryColor; ?>; }
        body { background: #f3f4f6; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #308a1e 0%, #269c16 100%); color: white; }
        .sidebar .sidebar-header { padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.3); margin-bottom: 10px; }
        .sidebar .sidebar-header h5 { font-size: 1.1rem !important; font-weight: 800 !important; color: #10b981 !important; }
        .sidebar .sidebar-header small { font-size: 0.8rem !important; font-weight: 600 !important; color: #10b981 !important; }
        .sidebar .sidebar-header img { border: 2px solid white !important; border-radius: 8px !important; max-height: 55px; }
        .sidebar a { color: rgba(255,255,255,0.95); text-decoration: none; padding: 10px 12px; display: block; border-radius: 6px; margin-bottom: 3px; font-size: 0.95rem; font-weight: 600; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.25); color: white; font-weight: 700; }
        .sidebar a i { width: 28px; font-weight: 700; }

        /* Mobile Menu */
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
                <div class="py-2">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="staff.php" class="active"><i class="fas fa-users"></i> Staff</a>
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
            <div class="col-md-9 col-lg-10 p-4">
                <!-- Mobile Menu Button -->
                <button class="hamburger position-fixed" style="top: 10px; left: 10px; z-index: 1002;" onclick="toggleSidebar()">
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

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-users me-2"></i>Staff Management</h2>
                    <a href="?action=add" class="btn btn-primary" title="Add a new staff member to the system">
                        <i class="fas fa-plus me-2"></i>Add Staff
                    </a>
                </div>

                <?php if ($action === 'add' || $action === 'edit'): ?>
                <!-- Add/Edit Form -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-<?php echo $action === 'edit' ? 'edit' : 'plus'; ?> me-2"></i><?php echo $action === 'edit' ? 'Edit' : 'Add'; ?> Staff</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Staff ID *</label>
                                    <input type="text" class="form-control" name="staff_id" value="<?php echo htmlspecialchars($staffMember['staff_id'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Surname *</label>
                                    <input type="text" class="form-control" name="surname" value="<?php echo htmlspecialchars($staffMember['surname'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($staffMember['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($staffMember['email'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($staffMember['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" name="department" value="<?php echo htmlspecialchars($staffMember['department'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Faculty</label>
                                    <input type="text" class="form-control" name="faculty" value="<?php echo htmlspecialchars($staffMember['faculty'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Designation</label>
                                    <input type="text" class="form-control" name="designation" value="<?php echo htmlspecialchars($staffMember['designation'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Grade Level</label>
                                    <select class="form-select" name="grade_level">
                                        <?php foreach ($gradeLevels as $level): ?>
                                        <option value="<?php echo htmlspecialchars($level['level_name']); ?>" <?php echo ($staffMember['grade_level'] ?? '') == $level['level_name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($level['level_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Employment Status</label>
                                    <select class="form-select" name="employment_status">
                                        <option value="Permanent" <?php echo ($staffMember['employment_status'] ?? '') == 'Permanent' ? 'selected' : ''; ?>>Permanent</option>
                                        <option value="Contract" <?php echo ($staffMember['employment_status'] ?? '') == 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                        <option value="Part-time" <?php echo ($staffMember['employment_status'] ?? '') == 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Staff Category <span class="text-danger">*</span></label>
                                    <select class="form-select" name="staff_category" required title="Select whether this staff is Academic (teaching), Non-Teaching (administrative), Non-Teaching Junior, or HOD">
                                        <option value="academic" <?php echo ($staffMember['staff_category'] ?? 'academic') == 'academic' ? 'selected' : ''; ?>>Academic (Teaching)</option>
                                        <option value="non-teaching" <?php echo ($staffMember['staff_category'] ?? '') == 'non-teaching' ? 'selected' : ''; ?>>Non-Teaching (Administrative)</option>
                                        <option value="non-teaching-junior" <?php echo ($staffMember['staff_category'] ?? '') == 'non-teaching-junior' ? 'selected' : ''; ?>>Junior Staff</option>
                                        <option value="hod" <?php echo ($staffMember['staff_category'] ?? '') == 'hod' ? 'selected' : ''; ?>>Supervising Officer</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Years of Service</label>
                                    <input type="number" class="form-control" name="years_of_service" value="<?php echo htmlspecialchars($staffMember['years_of_service'] ?? '0'); ?>" min="0">
                                </div>
                            </div>
                            <button type="submit" name="<?php echo $action === 'edit' ? 'update_staff' : 'add_staff'; ?>" class="btn btn-primary" title="<?php echo $action === 'edit' ? 'Save changes to staff member' : 'Add new staff member to database'; ?>">
                                <i class="fas fa-save me-2"></i><?php echo $action === 'edit' ? 'Update' : 'Add'; ?> Staff
                            </button>
                            <a href="staff.php" class="btn btn-secondary" title="Discard changes and return to staff list">Cancel</a>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Staff List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Staff ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Faculty</th>
                                        <th>Designation</th>
                                        <th>Grade Level</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($staffList)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">No staff members found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($staffList as $staff): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($staff['staff_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['surname']); ?></td>
                                            <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                            <td><?php echo htmlspecialchars($staff['department'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($staff['faculty'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($staff['designation'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($staff['grade_level'] ?? ''); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $staff['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($staff['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?action=edit&id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-primary" title="Edit staff member details">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-sm btn-warning" title="Reset Password" data-bs-toggle="modal" data-bs-target="#resetPasswordModal<?php echo $staff['id']; ?>">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info" title="Reset Evaluation (Allow Resubmit)" data-bs-toggle="modal" data-bs-target="#resetEvalModal<?php echo $staff['id']; ?>">
                                                    <i class="fas fa-redo"></i>
                                                </button>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this staff member permanently?');">
                                                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                                    <button type="submit" name="delete_staff" class="btn btn-sm btn-danger" title="Delete this staff member permanently">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>

                                                <!-- Password Reset Modal -->
                                                <div class="modal fade" id="resetPasswordModal<?php echo $staff['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <form method="POST">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Reset Password for <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['surname']); ?></h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">New Password</label>
                                                                        <input type="password" class="form-control" name="new_password" required minlength="6" placeholder="Enter new password (min 6 characters)">
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Confirm Password</label>
                                                                        <input type="password" class="form-control" name="confirm_password" required minlength="6" placeholder="Confirm new password">
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" name="reset_password" class="btn btn-warning">Reset Password</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Reset Evaluation Modal -->
                                                <div class="modal fade" id="resetEvalModal<?php echo $staff['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <form method="POST">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Reset Evaluation for <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['surname']); ?></h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>This will allow the staff member to resubmit their evaluation form. Use this if they accidentally submitted prematurely.</p>
                                                                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" name="reset_evaluation" class="btn btn-info">Reset Evaluation</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Footer -->
    <footer class="mt-4 py-3" style="background: linear-gradient(180deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%); color: white; border-radius: 8px;">
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