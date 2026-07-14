<?php
require_once 'config.php';
requireAdminLogin();

$messageData = getMessage();
$message = '';
if ($messageData && is_array($messageData)) {
    $messageType = $messageData['type'] ?? 'success';
    $messageText = $messageData['message'] ?? '';
    $message = '<div class="alert alert-' . $messageType . '">' . $messageText . '</div>';
}
$action = $_GET['action'] ?? 'list';
$editId = $_GET['id'] ?? null;

$pdo = getDBConnection();

// Get settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$instName = $settings['institution_name'] ?? 'Institution';

// Get all unique departments from staff
$stmt = $pdo->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department");
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all unique faculties from staff
$stmt = $pdo->query("SELECT DISTINCT faculty FROM staff WHERE faculty IS NOT NULL AND faculty != '' ORDER BY faculty");
$faculties = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle add new department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_department'])) {
    $departmentName = sanitize($_POST['department_name'] ?? '');
    $departmentFaculty = sanitize($_POST['department_faculty'] ?? '');

    if (!empty($departmentName)) {
        // Check if department already exists
        $stmt = $pdo->prepare("SELECT id FROM staff WHERE department = ? AND staff_id LIKE 'DEPT-%'");
        $stmt->execute([$departmentName]);
        if ($stmt->fetch()) {
            showMessage('Department "' . htmlspecialchars($departmentName) . '" already exists!', 'warning');
        } else {
            // Insert a placeholder staff record to store the department
            $stmt = $pdo->prepare("INSERT INTO staff (staff_id, surname, first_name, department, faculty, employment_status) VALUES (?, 'SYSTEM', 'DEPARTMENT', ?, ?, 'active')");
            $placeholderId = 'DEPT-' . str_replace(' ', '-', $departmentName);
            $stmt->execute([$placeholderId, $departmentName, $departmentFaculty]);
            showMessage('Department "' . htmlspecialchars($departmentName) . '" added successfully!', 'success');
        }
    }
    redirect('manage-evaluators.php');
}

// Handle add new faculty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_faculty'])) {
    $facultyName = sanitize($_POST['faculty_name'] ?? '');

    if (!empty($facultyName)) {
        // Check if faculty already exists
        $stmt = $pdo->prepare("SELECT id FROM staff WHERE faculty = ? AND staff_id LIKE 'FAC-%'");
        $stmt->execute([$facultyName]);
        if ($stmt->fetch()) {
            showMessage('Faculty "' . htmlspecialchars($facultyName) . '" already exists!', 'warning');
        } else {
            // Insert a placeholder staff record to store the faculty
            $stmt = $pdo->prepare("INSERT INTO staff (staff_id, surname, first_name, department, faculty, employment_status) VALUES (?, 'SYSTEM', 'FACULTY', ?, ?, 'active')");
            $placeholderId = 'FAC-' . str_replace(' ', '-', $facultyName);
            $stmt->execute([$placeholderId, '', $facultyName]);
            showMessage('Faculty "' . htmlspecialchars($facultyName) . '" added successfully!', 'success');
        }
    }
    redirect('manage-evaluators.php');
}

// Handle delete department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_department'])) {
    $deptToDelete = sanitize($_POST['department_to_delete'] ?? '');

    if (!empty($deptToDelete)) {
        // Delete the placeholder record for this department
        $stmt = $pdo->prepare("DELETE FROM staff WHERE department = ? AND staff_id LIKE 'DEPT-%'");
        $stmt->execute([$deptToDelete]);
        showMessage('Department "' . htmlspecialchars($deptToDelete) . '" deleted successfully!', 'success');
    }
    redirect('manage-evaluators.php');
}

// Handle delete faculty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_faculty'])) {
    $facToDelete = sanitize($_POST['faculty_to_delete'] ?? '');

    if (!empty($facToDelete)) {
        // Delete the placeholder record for this faculty
        $stmt = $pdo->prepare("DELETE FROM staff WHERE faculty = ? AND staff_id LIKE 'FAC-%'");
        $stmt->execute([$facToDelete]);
        showMessage('Faculty "' . htmlspecialchars($facToDelete) . '" deleted successfully!', 'success');
    }
    redirect('manage-evaluators.php');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $evaluatorType = sanitize($_POST['evaluator_type']);
        $designation = sanitize($_POST['designation']);
        $surname = sanitize($_POST['surname']);
        $firstName = sanitize($_POST['first_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $department = sanitize($_POST['department']);
        $faculty = sanitize($_POST['faculty']);
        $password = $_POST['password'] ?? '';

        if (isset($_POST['add_evaluator'])) {
            // Validation based on evaluator type
            if (empty($designation)) {
                showMessage('Designation (username) is required', 'danger');
            } elseif (empty($password) || strlen($password) < 6) {
                showMessage('Password must be at least 6 characters', 'danger');
            } elseif ($evaluatorType === 'Supervising Officer' && empty($department)) {
                showMessage('Department is required for Supervising Officer', 'danger');
            } else {
                // Check if designation already exists
                $stmt = $pdo->prepare("SELECT id FROM staff WHERE designation = ?");
                $stmt->execute([$designation]);
                if ($stmt->fetch()) {
                    showMessage('Designation already exists. Please use a different designation.', 'danger');
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    // Generate a unique staff_id for the evaluator
                    $staffId = 'EVAL-' . strtoupper(str_replace(' ', '-', $designation));
                    $stmt = $pdo->prepare("INSERT INTO staff (staff_id, designation, surname, first_name, email, phone, department, faculty, evaluator_type, password, staff_category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'non-teaching')");
                    $stmt->execute([$staffId, $designation, $surname, $firstName, $email, $phone, $department, $faculty, $evaluatorType, $hashedPassword]);
                    showMessage(ucfirst($evaluatorType) . ' added successfully!', 'success');
                    redirect('manage-evaluators.php');
                }
            }
        }

        // Handle promote staff to evaluator
        if (isset($_POST['promote_evaluator'])) {
            $promoteStaffId = intval($_POST['promote_staff_id'] ?? 0);
            $promoteEvaluatorType = sanitize($_POST['promote_evaluator_type']);
            $promotePassword = $_POST['promote_password'] ?? '';

            if (!$promoteStaffId) {
                showMessage('Please select a staff member to promote', 'danger');
            } elseif (empty($promoteEvaluatorType)) {
                showMessage('Please select evaluator type', 'danger');
            } elseif (empty($promotePassword) || strlen($promotePassword) < 6) {
                showMessage('Password must be at least 6 characters', 'danger');
            } else {
                // Get the staff member details
                $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
                $stmt->execute([$promoteStaffId]);
                $staffMember = $stmt->fetch();

                if (!$staffMember) {
                    showMessage('Staff member not found', 'danger');
                } else {
                    // Check if already an evaluator
                    if (!empty($staffMember['evaluator_type'])) {
                        showMessage('This staff member is already an evaluator', 'warning');
                    } else {
                        $hashedPassword = password_hash($promotePassword, PASSWORD_DEFAULT);
                        // Use staff_id as designation if empty
                        $newDesignation = $staffMember['staff_id'] ?: $staffMember['surname'] . '_' . $promoteEvaluatorType;

                        $stmt = $pdo->prepare("UPDATE staff SET evaluator_type = ?, designation = ?, password = ? WHERE id = ?");
                        $stmt->execute([$promoteEvaluatorType, $newDesignation, $hashedPassword, $promoteStaffId]);
                        showMessage(ucfirst($staffMember['surname'] . ' ' . $staffMember['first_name']) . ' promoted as ' . $promoteEvaluatorType . ' successfully!', 'success');
                        redirect('manage-evaluators.php');
                    }
                }
            }
        }

        if (isset($_POST['update_evaluator']) && $editId) {
            if (empty($designation)) {
                showMessage('Designation (username) is required', 'danger');
            } elseif ($evaluatorType === 'Supervising Officer' && empty($department)) {
                showMessage('Department is required for Supervising Officer', 'danger');
            } else {
                $stmt = $pdo->prepare("SELECT id FROM staff WHERE designation = ? AND id != ?");
                $stmt->execute([$designation, $editId]);
                if ($stmt->fetch()) {
                    showMessage('Designation already exists. Please use a different designation.', 'danger');
                } else {
                    $updateData = [
                        'designation' => $designation,
                        'surname' => $surname,
                        'first_name' => $firstName,
                        'email' => $email,
                        'phone' => $phone,
                        'department' => $department,
                        'faculty' => $faculty,
                        'evaluator_type' => $evaluatorType,
                    ];

                    if (!empty($password) && strlen($password) >= 6) {
                        $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
                    }

                    $fields = implode(' = ?, ', array_keys($updateData)) . ' = ?';
                    $values = array_values($updateData);
                    $values[] = $editId;

                    $sql = "UPDATE staff SET $fields WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                    showMessage(ucfirst($evaluatorType) . ' updated successfully!', 'success');
                    redirect('manage-evaluators.php');
                }
            }
        }

        if (isset($_POST['delete_evaluator'])) {
            $deleteId = intval($_POST['evaluator_id'] ?? 0);
            if ($deleteId) {
                $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ? AND evaluator_type != ''");
                $stmt->execute([$deleteId]);
                showMessage('Evaluator deleted successfully!', 'success');
                redirect('manage-evaluators.php');
            }
        }
    } catch (Exception $e) {
        showMessage('Error: ' . $e->getMessage(), 'danger');
    }
}

// Get evaluators
$stmt = $pdo->query("SELECT * FROM staff WHERE evaluator_type IN ('Supervising Officer', 'Registrar') ORDER BY evaluator_type, department, surname");
$evaluators = $stmt->fetchAll();

// Get evaluator for editing
$editEvaluator = null;
if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ? AND evaluator_type IN ('Supervising Officer', 'Registrar')");
    $stmt->execute([$editId]);
    $editEvaluator = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Evaluators - <?php echo htmlspecialchars($instName); ?></title>
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
        .sidebar { background: linear-gradient(135deg, var(--primary), var(--secondary)); min-height: 100vh; padding: 20px; }
        .sidebar a { color: white; text-decoration: none; padding: 12px 15px; display: block; border-radius: 5px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.2); }
        .main-content { padding: 20px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .btn-primary { background: var(--primary); border: none; }
        .btn-primary:hover { background: var(--secondary); }
        .evaluator-card { transition: transform 0.2s; }
        .evaluator-card:hover { transform: translateY(-5px); }
        .dept-fac-badge { font-size: 0.75rem; padding: 3px 8px; }
        .add-new-link { color: var(--primary); cursor: pointer; text-decoration: underline; }
        .add-new-link:hover { color: var(--secondary); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <h4 class="text-white mb-4"><i class="fas fa-university"></i> <?php echo htmlspecialchars($instName); ?></h4>
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="master_password.php"><i class="fas fa-key"></i> Master Password</a>
                <a href="staff.php"><i class="fas fa-users"></i> Staff</a>
                <a href="staff-upload.php"><i class="fas fa-upload"></i> Upload Staff</a>
                <a href="manage-evaluators.php" class="active"><i class="fas fa-user-tie"></i> Evaluators</a>
                <a href="questions.php"><i class="fas fa-question-circle"></i> Questions</a>
                <a href="roles.php"><i class="fas fa-user-tag"></i> Staff Roles</a>
                <a href="evaluate.php"><i class="fas fa-clipboard-check"></i> Evaluate</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="download-data.php"><i class="fas fa-download"></i> Download Data</a>
                <a href="sessions.php"><i class="fas fa-calendar"></i> Sessions</a>
                <a href="logout.php" class="text-warning"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <?php echo $message; ?>

                <!-- Department/Faculty Manager -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-building"></i> Manage Departments & Faculties</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="border-bottom pb-2 mb-3">Departments (<?php echo count($departments); ?>)</h6>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <?php if (empty($departments)): ?>
                                        <span class="text-muted">No departments yet</span>
                                    <?php else: ?>
                                        <?php foreach ($departments as $dept): ?>
                                            <span class="badge bg-secondary d-flex align-items-center">
                                                <?php echo htmlspecialchars($dept); ?>
                                                <form method="POST" class="d-inline ms-2">
                                                    <input type="hidden" name="department_to_delete" value="<?php echo htmlspecialchars($dept); ?>">
                                                    <button type="submit" name="delete_department" class="badge bg-secondary border-0 p-0 ms-1" style="font-size: 0.7rem;" onclick="return confirm('Delete department &quot;<?php echo htmlspecialchars($dept); ?>&quot;?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                                    <i class="fas fa-plus"></i> Add Department
                                </button>
                            </div>
                            <div class="col-md-6">
                                <h6 class="border-bottom pb-2 mb-3">Faculties (<?php echo count($faculties); ?>)</h6>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <?php if (empty($faculties)): ?>
                                        <span class="text-muted">No faculties yet</span>
                                    <?php else: ?>
                                        <?php foreach ($faculties as $fac): ?>
                                            <span class="badge bg-info d-flex align-items-center">
                                                <?php echo htmlspecialchars($fac); ?>
                                                <form method="POST" class="d-inline ms-2">
                                                    <input type="hidden" name="faculty_to_delete" value="<?php echo htmlspecialchars($fac); ?>">
                                                    <button type="submit" name="delete_faculty" class="badge bg-info border-0 p-0 ms-1" style="font-size: 0.7rem;" onclick="return confirm('Delete faculty &quot;<?php echo htmlspecialchars($fac); ?>&quot;?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#addFacultyModal">
                                    <i class="fas fa-plus"></i> Add Faculty
                                </button>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="fas fa-info-circle"></i>
                            <strong>Tip:</strong> Departments and faculties are automatically loaded from your uploaded staff.
                            Add staff with the correct department, then promote them as Supervising Officer.
                            If you need a department/faculty that doesn't exist in your staff data, you can add it here.
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-user-tie"></i> Manage Evaluators (Supervising Officer, Registrar)</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEvaluatorModal">
                        <i class="fas fa-plus"></i> Add Evaluator
                    </button>
                </div>

                <!-- Evaluators List -->
                <div class="row">
                    <?php foreach ($evaluators as $eval): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card evaluator-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="badge bg-<?php
                                        echo $eval['evaluator_type'] === 'Supervising Officer' ? 'warning' : 'info';
                                    ?>">
                                        <?php echo htmlspecialchars($eval['evaluator_type']); ?>
                                    </span>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="manage-evaluators.php?action=edit&id=<?php echo $eval['id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </a></li>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="evaluator_id" value="<?php echo $eval['id']; ?>">
                                                    <button type="submit" name="delete_evaluator" class="dropdown-item text-danger" onclick="return confirm('Are you sure you want to delete this evaluator?')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <h5 class="card-title"><?php echo htmlspecialchars($eval['surname'] . ' ' . $eval['first_name']); ?></h5>
                                <p class="card-text text-muted mb-1">
                                    <i class="fas fa-user"></i> <strong>Username:</strong> <?php echo htmlspecialchars($eval['designation']); ?>
                                </p>
                                <?php if ($eval['evaluator_type'] === 'Supervising Officer' && $eval['department']): ?>
                                <p class="card-text text-muted mb-1">
                                    <i class="fas fa-building"></i> <strong>Department:</strong> <?php echo htmlspecialchars($eval['department']); ?>
                                </p>
                                <?php endif; ?>
                                    <i class="fas fa-school"></i> <strong>Faculty:</strong> <?php echo htmlspecialchars($eval['faculty']); ?>
                                </p>
                                <?php endif; ?>
                                <?php if ($eval['evaluator_type'] === 'Registrar'): ?>
                                <p class="card-text text-muted mb-1">
                                    <i class="fas fa-globe"></i> <strong>Scope:</strong> All Departments/Faculties
                                </p>
                                <?php endif; ?>
                                <?php if ($eval['email']): ?>
                                <p class="card-text text-muted mb-0">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($eval['email']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($evaluators)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No evaluators found. Click "Add Evaluator" to create one or promote from staff.
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal fade" id="addDepartmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Department Name</label>
                            <input type="text" name="department_name" class="form-control" placeholder="e.g., Computer Science" required>
                            <small class="text-muted">This will be available when assigning HOD</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign to Faculty</label>
                            <select name="department_faculty" class="form-select">
                                <option value="">Select Faculty (Optional)</option>
                                <?php foreach ($faculties as $fac): ?>
                                <option value="<?php echo htmlspecialchars($fac); ?>"><?php echo htmlspecialchars($fac); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_department" class="btn btn-success">Add Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Faculty Modal -->
    <div class="modal fade" id="addFacultyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Faculty</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Faculty Name</label>
                            <input type="text" name="faculty_name" class="form-control" placeholder="e.g., Engineering" required>
                            <small class="text-muted">This will be available when assigning Dean</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_faculty" class="btn btn-info">Add Faculty</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add/Edit Evaluator Modal -->
    <div class="modal fade" id="addEvaluatorModal" tabindex="-1" <?php echo $editEvaluator ? 'data-bs-backdrop="static"' : ''; ?>>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus"></i>
                        <?php echo $editEvaluator ? 'Edit Evaluator' : 'Add Evaluator'; ?>
                    </h5>
                    <?php if ($editEvaluator): ?>
                    <a href="manage-evaluators.php" class="btn-close"></a>
                    <?php else: ?>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    <?php endif; ?>
                </div>

                <?php if (!$editEvaluator): ?>
                <ul class="nav nav-tabs" id="evaluatorTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="create-tab" data-bs-toggle="tab" data-bs-target="#createPane" type="button" role="tab">
                            <i class="fas fa-plus"></i> Create New
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="promote-tab" data-bs-toggle="tab" data-bs-target="#promotePane" type="button" role="tab">
                            <i class="fas fa-user-plus"></i> Promote from Staff
                        </button>
                    </li>
                </ul>
                <?php endif; ?>

                <form method="POST">
                    <div class="modal-body">
                        <?php if (!$editEvaluator): ?>
                        <div class="tab-content" id="evaluatorTabsContent">
                            <div class="tab-pane fade show active" id="createPane" role="tabpanel">
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Evaluator Type <span class="text-danger">*</span></label>
                                <select name="evaluator_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="Supervising Officer" <?php echo ($editEvaluator['evaluator_type'] ?? '') === 'Supervising Officer' ? 'selected' : ''; ?>>Supervising Officer</option>
                                    <option value="Registrar" <?php echo ($editEvaluator['evaluator_type'] ?? '') === 'Registrar' ? 'selected' : ''; ?>>Registrar</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Designation (Username) <span class="text-danger">*</span></label>
                                <input type="text" name="designation" class="form-control" placeholder="e.g., HOD-Computer Science" value="<?php echo htmlspecialchars($editEvaluator['designation'] ?? ''); ?>" required>
                                <small class="text-muted">This will be used as login username</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Surname</label>
                                <input type="text" name="surname" class="form-control" value="<?php echo htmlspecialchars($editEvaluator['surname'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($editEvaluator['first_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($editEvaluator['email'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($editEvaluator['phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3" id="departmentField">
                                <label class="form-label">Department <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" name="department" id="departmentInput" class="form-control" list="departmentsList" value="<?php echo htmlspecialchars($editEvaluator['department'] ?? ''); ?>" autocomplete="off">
                                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <datalist id="departmentsList">
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <small class="text-muted">Required for HOD - select or add new</small>
                            </div>
                            <div class="col-md-6 mb-3" id="facultyField">
                                <label class="form-label">Faculty <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" name="faculty" id="facultyInput" class="form-control" list="facultiesList" value="<?php echo htmlspecialchars($editEvaluator['faculty'] ?? ''); ?>" autocomplete="off">
                                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#addFacultyModal">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <datalist id="facultiesList">
                                    <?php foreach ($faculties as $fac): ?>
                                    <option value="<?php echo htmlspecialchars($fac); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <small class="text-muted">Required for Dean - select or add new</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Password <?php echo $editEvaluator ? '<span class="text-muted">(leave empty to keep current)</span>' : '<span class="text-danger">*</span>'; ?>
                                </label>
                                <input type="password" name="password" class="form-control" placeholder="Enter password" <?php echo $editEvaluator ? '' : 'required'; ?> minlength="6">
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                        </div>

                        <?php if (!$editEvaluator): ?>
                            </div>

                            <!-- Promote from Staff Tab -->
                            <div class="tab-pane fade" id="promotePane" role="tabpanel">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Select a staff member from your uploaded staff list to promote as evaluator. Their department/faculty will be automatically linked.
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Select Staff to Promote <span class="text-danger">*</span></label>
                                        <select name="promote_staff_id" class="form-select" id="promoteStaffSelect">
                                            <option value="">Select Staff Member</option>
                                            <?php
                                            $stmt = $pdo->query("SELECT id, staff_id, surname, first_name, department, faculty FROM staff WHERE (evaluator_type = '' OR evaluator_type IS NULL) AND staff_id NOT LIKE 'DEPT-%' AND staff_id NOT LIKE 'FAC-%' ORDER BY surname, first_name");
                                            while ($staffMember = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                echo '<option value="' . $staffMember['id'] . '" data-dept="' . htmlspecialchars($staffMember['department'] ?? '') . '" data-fac="' . htmlspecialchars($staffMember['faculty'] ?? '') . '">';
                                                echo htmlspecialchars($staffMember['surname'] . ' ' . $staffMember['first_name'] . ' (' . $staffMember['staff_id'] . ')');
                                                if ($staffMember['department']) echo ' - ' . $staffMember['department'];
                                                echo '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Evaluator Type <span class="text-danger">*</span></label>
                                        <select name="promote_evaluator_type" class="form-select" id="promoteEvaluatorType">
                                            <option value="">Select Type</option>
                                            <option value="Supervising Officer">Supervising Officer</option>
                                            <option value="Registrar">Registrar</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Department (Auto-filled)</label>
                                        <input type="text" name="promote_department" id="promoteDepartment" class="form-control" readonly>
                                        <small class="text-muted">From staff record</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Faculty (Auto-filled)</label>
                                        <input type="text" name="promote_faculty" id="promoteFaculty" class="form-control" readonly>
                                        <small class="text-muted">From staff record</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Set Login Password <span class="text-danger">*</span></label>
                                        <input type="password" name="promote_password" class="form-control" placeholder="Set password for evaluator login" minlength="6">
                                        <small class="text-muted">Minimum 6 characters</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <?php if ($editEvaluator): ?>
                        <a href="manage-evaluators.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_evaluator" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Evaluator
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="add_evaluator" class="btn btn-primary" id="addBtn">
                            <i class="fas fa-plus"></i> Add Evaluator
                        </button>
                        <button type="submit" name="promote_evaluator" class="btn btn-success" id="promoteBtn" style="display:none;">
                            <i class="fas fa-user-plus"></i> Promote to Evaluator
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleFields() {
            var evaluatorType = document.querySelector('select[name="evaluator_type"]').value;
            var deptField = document.getElementById('departmentField');
            var facField = document.getElementById('facultyField');
            var deptInput = document.getElementById('departmentInput');
            var facInput = document.getElementById('facultyInput');

            if (evaluatorType === 'Supervising Officer') {
                deptField.style.display = 'block';
                facField.style.display = 'none';
                deptInput.required = true;
                facInput.required = false;
                facInput.value = '';
            } else {
                deptField.style.display = 'block';
                facField.style.display = 'block';
                deptInput.required = false;
                facInput.required = false;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            var evaluatorSelect = document.querySelector('select[name="evaluator_type"]');
            if (evaluatorSelect) {
                evaluatorSelect.addEventListener('change', toggleFields);
                toggleFields();
            }

            // Tab switching
            var createTab = document.getElementById('create-tab');
            var promoteTab = document.getElementById('promote-tab');
            var addBtn = document.getElementById('addBtn');
            var promoteBtn = document.getElementById('promoteBtn');

            if (createTab && promoteTab) {
                createTab.addEventListener('shown.bs.tab', function() {
                    if (addBtn) addBtn.style.display = 'inline-block';
                    if (promoteBtn) promoteBtn.style.display = 'none';
                });
                promoteTab.addEventListener('shown.bs.tab', function() {
                    if (addBtn) addBtn.style.display = 'none';
                    if (promoteBtn) promoteBtn.style.display = 'inline-block';
                });
            }

            // Auto-fill department/faculty when selecting staff to promote
            var promoteStaffSelect = document.getElementById('promoteStaffSelect');
            if (promoteStaffSelect) {
                promoteStaffSelect.addEventListener('change', function() {
                    var selectedOption = this.options[this.selectedIndex];
                    var dept = selectedOption.getAttribute('data-dept');
                    var fac = selectedOption.getAttribute('data-fac');
                    document.getElementById('promoteDepartment').value = dept || '';
                    document.getElementById('promoteFaculty').value = fac || '';
                });
            }
        });
    </script>
    <?php if ($editEvaluator): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('addEvaluatorModal'));
            myModal.show();
        });
    </script>
    <?php endif; ?>
</body>
</html>