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

// Handle role operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_role'])) {
        $permissions = [
            'evaluate_own' => isset($_POST['evaluate_own']),
            'view_own' => isset($_POST['view_own']),
            'evaluate_department' => isset($_POST['evaluate_department']),
            'view_department' => isset($_POST['view_department']),
            'approve_department' => isset($_POST['approve_department']),
            'evaluate_faculty' => isset($_POST['evaluate_faculty']),
            'view_faculty' => isset($_POST['view_faculty']),
            'approve_faculty' => isset($_POST['approve_faculty']),
            'view_all' => isset($_POST['view_all']),
            'export_reports' => isset($_POST['export_reports']),
        ];
        $stmt = $pdo->prepare("INSERT INTO staff_roles (role_name, role_description, permissions) VALUES (?, ?, ?)");
        $stmt->execute([
            sanitize($_POST['role_name']),
            sanitize($_POST['role_description']),
            json_encode($permissions)
        ]);
        showMessage('Role created successfully!', 'success');
    }

    if (isset($_POST['update_role'])) {
        $permissions = [
            'evaluate_own' => isset($_POST['evaluate_own']),
            'view_own' => isset($_POST['view_own']),
            'evaluate_department' => isset($_POST['evaluate_department']),
            'view_department' => isset($_POST['view_department']),
            'approve_department' => isset($_POST['approve_department']),
            'evaluate_faculty' => isset($_POST['evaluate_faculty']),
            'view_faculty' => isset($_POST['view_faculty']),
            'approve_faculty' => isset($_POST['approve_faculty']),
            'view_all' => isset($_POST['view_all']),
            'export_reports' => isset($_POST['export_reports']),
        ];
        $stmt = $pdo->prepare("UPDATE staff_roles SET role_name = ?, role_description = ?, permissions = ? WHERE id = ?");
        $stmt->execute([
            sanitize($_POST['role_name']),
            sanitize($_POST['role_description']),
            json_encode($permissions),
            intval($_POST['role_id'])
        ]);
        showMessage('Role updated successfully!', 'success');
    }

    if (isset($_POST['delete_role'])) {
        $stmt = $pdo->prepare("DELETE FROM staff_roles WHERE id = ? AND is_default = 0");
        $stmt->execute([intval($_POST['role_id'])]);
        showMessage('Role deleted successfully!', 'success');
    }

    if (isset($_POST['assign_role'])) {
        $stmt = $pdo->prepare("UPDATE staff SET staff_role_id = ? WHERE id = ?");
        $stmt->execute([intval($_POST['staff_role_id']), intval($_POST['staff_id'])]);
        showMessage('Role assigned to staff!', 'success');
    }

    redirect('roles.php');
}

// Get all roles
$stmt = $pdo->query("SELECT * FROM staff_roles ORDER BY id");
$roles = $stmt->fetchAll();

// Get all staff for assignment
$stmt = $pdo->query("SELECT s.id, s.staff_id, s.first_name, s.surname, s.department, r.role_name
    FROM staff s
    LEFT JOIN staff_roles r ON s.staff_role_id = r.id
    ORDER BY s.surname, s.first_name");
$staffList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Roles - <?php echo htmlspecialchars($instName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: <?php echo $settings['primary_color'] ?? '#308a1e'; ?>; }
        body { background: #f3f4f6; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, <?php echo $settings['primary_color'] ?? '#308a1e'; ?> 0%, <?php echo $settings['secondary_color'] ?? '#269c16'; ?> 100%); color: white; }
        .sidebar a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 12px 15px; display: block; border-radius: 8px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); color: white; }
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
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="staff.php"><i class="fas fa-users"></i> Staff</a>
                    <a href="staff-upload.php"><i class="fas fa-upload"></i> Upload Staff</a>
                    <a href="questions.php"><i class="fas fa-question-circle"></i> Questions</a>
                    <a href="roles.php" class="active"><i class="fas fa-user-tag"></i> Staff Roles</a>
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
                    <h2><i class="fas fa-user-tag me-2"></i>Staff Roles & Permissions</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                        <i class="fas fa-plus me-2"></i>Create Role
                    </button>
                </div>

                <p class="text-muted mb-4">
                    Create staff roles and assign permissions. Each role defines what access staff members have on the portal.
                </p>

                <!-- Roles List -->
                <div class="row">
                    <?php foreach ($roles as $role):
                        $perms = json_decode($role['permissions'], true) ?? [];
                    ?>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo htmlspecialchars($role['role_name']); ?></h5>
                                <div>
                                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editRoleModal<?php echo $role['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (!$role['is_default']): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                        <button type="submit" name="delete_role" class="btn btn-sm btn-danger" onclick="return confirm('Delete this role?');">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="text-muted"><?php echo htmlspecialchars($role['role_description'] ?? 'No description'); ?></p>
                                <h6>Permissions:</h6>
                                <div class="row">
                                    <?php if (isset($perms['evaluate_own']) && $perms['evaluate_own']): ?>
                                    <div class="col-6"><span class="badge bg-success"><i class="fas fa-check"></i> Evaluate Own</span></div>
                                    <?php endif; ?>
                                    <?php if (isset($perms['view_own']) && $perms['view_own']): ?>
                                    <div class="col-6"><span class="badge bg-success"><i class="fas fa-check"></i> View Own</span></div>
                                    <?php endif; ?>
                                    <?php if (isset($perms['evaluate_department']) && $perms['evaluate_department']): ?>
                                    <div class="col-6"><span class="badge bg-info"><i class="fas fa-check"></i> Evaluate Dept</span></div>
                                    <?php endif; ?>
                                    <?php if (isset($perms['view_department']) && $perms['view_department']): ?>
                                    <div class="col-6"><span class="badge bg-info"><i class="fas fa-check"></i> View Dept</span></div>
                                    <?php endif; ?>
                                    <?php if (isset($perms['approve_department']) && $perms['approve_department']): ?>
                                    <div class="col-6"><span class="badge bg-warning"><i class="fas fa-check"></i> Approve Dept</span></div>
                                    <?php endif; ?>
                                    <?php if (isset($perms['view_all']) && $perms['view_all']): ?>
                                    <div class="col-6"><span class="badge bg-danger"><i class="fas fa-check"></i> View All</span></div>
                                    <?php endif; ?>
                                    <?php if (isset($perms['export_reports']) && $perms['export_reports']): ?>
                                    <div class="col-6"><span class="badge bg-secondary"><i class="fas fa-check"></i> Export Reports</span></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Role Modal -->
                    <div class="modal fade" id="editRoleModal<?php echo $role['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Role: <?php echo htmlspecialchars($role['role_name']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Role Name</label>
                                            <input type="text" class="form-control" name="role_name" value="<?php echo htmlspecialchars($role['role_name']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea class="form-control" name="role_description"><?php echo htmlspecialchars($role['role_description'] ?? ''); ?></textarea>
                                        </div>
                                        <h6>Permissions</h6>
                                        <div class="row">
                                            <div class="col-6 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="evaluate_own" id="eo<?php echo $role['id']; ?>" <?php echo $perms['evaluate_own'] ?? false ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="eo<?php echo $role['id']; ?>">Evaluate Own</label>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="view_own" id="vo<?php echo $role['id']; ?>" <?php echo $perms['view_own'] ?? false ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="vo<?php echo $role['id']; ?>">View Own</label>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="evaluate_department" id="ed<?php echo $role['id']; ?>" <?php echo $perms['evaluate_department'] ?? false ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="ed<?php echo $role['id']; ?>">Evaluate Department</label>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="view_department" id="vd<?php echo $role['id']; ?>" <?php echo $perms['view_department'] ?? false ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="vd<?php echo $role['id']; ?>">View Department</label>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="approve_department" id="ad<?php echo $role['id']; ?>" <?php echo $perms['approve_department'] ?? false ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="ad<?php echo $role['id']; ?>">Approve Department</label>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="view_all" id="va<?php echo $role['id']; ?>" <?php echo $perms['view_all'] ?? false ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="va<?php echo $role['id']; ?>">View All</label>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="export_reports" id="er<?php echo $role['id']; ?>" <?php echo $perms['export_reports'] ?? false ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="er<?php echo $role['id']; ?>">Export Reports</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="update_role" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Assign Role to Staff -->
                <div class="card mt-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Assign Role to Staff</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Select Staff</label>
                                <select class="form-select" name="staff_id" required>
                                    <option value="">Choose Staff...</option>
                                    <?php foreach ($staffList as $s): ?>
                                    <option value="<?php echo $s['id']; ?>">
                                        <?php echo htmlspecialchars($s['staff_id'] . ' - ' . $s['first_name'] . ' ' . $s['surname'] . ' (' . $s['department'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Assign Role</label>
                                <select class="form-select" name="staff_role_id" required>
                                    <option value="">Choose Role...</option>
                                    <?php foreach ($roles as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" name="assign_role" class="btn btn-success w-100">Assign</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Current Staff Roles -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Staff with Roles</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Staff ID</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Current Role</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staffList as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['staff_id']); ?></td>
                                    <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['surname']); ?></td>
                                    <td><?php echo htmlspecialchars($s['department'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $s['role_name'] ? 'primary' : 'secondary'; ?>">
                                            <?php echo htmlspecialchars($s['role_name'] ?? 'Not Assigned'); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Role Modal -->
    <div class="modal fade" id="addRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Role Name</label>
                            <input type="text" class="form-control" name="role_name" placeholder="e.g., Senior Lecturer" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="role_description" placeholder="Brief description of this role"></textarea>
                        </div>
                        <h6>Permissions</h6>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="evaluate_own" id="eo_new" checked>
                                    <label class="form-check-label" for="eo_new">Evaluate Own Form</label>
                                </div>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="view_own" id="vo_new" checked>
                                    <label class="form-check-label" for="vo_new">View Own Results</label>
                                </div>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="evaluate_department" id="ed_new">
                                    <label class="form-check-label" for="ed_new">Evaluate Department</label>
                                </div>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="view_department" id="vd_new">
                                    <label class="form-check-label" for="vd_new">View Department</label>
                                </div>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="approve_department" id="ad_new">
                                    <label class="form-check-label" for="ad_new">Approve Department</label>
                                </div>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="view_all" id="va_new">
                                    <label class="form-check-label" for="va_new">View All Staff</label>
                                </div>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="export_reports" id="er_new">
                                    <label class="form-check-label" for="er_new">Export Reports</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_role" class="btn btn-primary">Create Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>