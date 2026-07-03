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
$primaryColor = $settings['primary_color'] ?? '#1e3a8a';
$secondaryColor = $settings['secondary_color'] ?? '#3b82f6';

$message = getMessage();
$action = $_GET['action'] ?? 'list';
$staffId = $_GET['id'] ?? null;

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

        if (isset($_POST['delete_staff']) && $staffId) {
            $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
            $stmt->execute([$staffId]);
            showMessage('Staff member deleted successfully!', 'success');
            redirect('staff.php');
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
    <title>Staff Management - APER Admin</title>
    <?php if (!empty($logo)): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logo); ?>">
    <?php endif; ?>
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
                <div class="text-center sidebar-header" style="padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 10px;">
                    <?php if (!empty($logo)): ?>
                        <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" style="max-height: 45px; margin-bottom: 8px; border: 2px solid white; border-radius: 6px; padding: 2px;">
                    <?php else: ?>
                        <i class="fas fa-graduation-cap fa-2x mb-2"></i>
                    <?php endif; ?>
                    <h5 class="mb-0" style="font-size: 1rem; font-weight: 700;"><?php echo htmlspecialchars($instName); ?></h5>
                    <?php if (!empty($instAddress)): ?>
                        <small class="d-block text-truncate" style="max-width: 150px; margin: 0 auto; font-size: 0.7rem;"><?php echo htmlspecialchars($instAddress); ?></small>
                    <?php endif; ?>
                </div>
                <div class="py-2">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="staff.php" class="active"><i class="fas fa-users"></i> Staff</a>
                    <a href="staff-upload.php"><i class="fas fa-upload"></i> Upload Staff</a>
                    <a href="questions.php"><i class="fas fa-question-circle"></i> Questions</a>
                    <a href="roles.php"><i class="fas fa-user-tag"></i> Staff Roles</a>
                    <a href="evaluate.php"><i class="fas fa-clipboard-check"></i> Evaluate</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
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
                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <option value="Level <?php echo $i; ?>" <?php echo ($staffMember['grade_level'] ?? '') == "Level $i" ? 'selected' : ''; ?>>Level <?php echo $i; ?></option>
                                        <?php endfor; ?>
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
                                    <select class="form-select" name="staff_category" required title="Select whether this staff is Academic (teaching) or Non-Teaching (administrative)">
                                        <option value="academic" <?php echo ($staffMember['staff_category'] ?? 'academic') == 'academic' ? 'selected' : ''; ?>>Academic (Teaching)</option>
                                        <option value="non-teaching" <?php echo ($staffMember['staff_category'] ?? '') == 'non-teaching' ? 'selected' : ''; ?>>Non-Teaching (Administrative)</option>
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
                                                <a href="?action=delete&id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-danger" title="Delete this staff member permanently" onclick="return confirm('Delete this staff member?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
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
</body>
</html>