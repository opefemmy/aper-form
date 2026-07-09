<?php
require_once 'config.php';
requireAdminLogin();

$message = getMessage();
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

// Get all unique departments
$stmt = $pdo->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department");
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all unique faculties
$stmt = $pdo->query("SELECT DISTINCT faculty FROM staff WHERE faculty IS NOT NULL AND faculty != '' ORDER BY faculty");
$faculties = $stmt->fetchAll(PDO::FETCH_COLUMN);

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
            if (empty($designation)) {
                showMessage('Designation (username) is required', 'danger');
            } elseif (empty($password) || strlen($password) < 6) {
                showMessage('Password must be at least 6 characters', 'danger');
            } else {
                // Check if designation already exists
                $stmt = $pdo->prepare("SELECT id FROM staff WHERE designation = ?");
                $stmt->execute([$designation]);
                if ($stmt->fetch()) {
                    showMessage('Designation already exists. Please use a different designation.', 'danger');
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO staff (designation, surname, first_name, email, phone, department, faculty, evaluator_type, password, staff_category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'non-teaching')");
                    $stmt->execute([$designation, $surname, $firstName, $email, $phone, $department, $faculty, $evaluatorType, $hashedPassword]);
                    showMessage(ucfirst($evaluatorType) . ' added successfully!', 'success');
                    redirect('manage-evaluators.php');
                }
            }
        }

        if (isset($_POST['update_evaluator']) && $editId) {
            if (empty($designation)) {
                showMessage('Designation (username) is required', 'danger');
            } else {
                // Check if designation exists for other records
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

                    // Update password if provided
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
$stmt = $pdo->query("SELECT * FROM staff WHERE evaluator_type IN ('HOD', 'Dean', 'Registrar') ORDER BY evaluator_type, department, surname");
$evaluators = $stmt->fetchAll();

// Get evaluator for editing
$editEvaluator = null;
if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ? AND evaluator_type IN ('HOD', 'Dean', 'Registrar')");
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
        .badge-hod { background: #f59e0b; }
        .badge-dean { background: #3b82f6; }
        .badge-registrar { background: #8b5cf6; }
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

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-user-tie"></i> Manage Evaluators (HOD, Dean, Registrar)</h2>
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
                                        echo $eval['evaluator_type'] === 'HOD' ? 'warning' : ($eval['evaluator_type'] === 'Dean' ? 'primary' : 'info');
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
                                <?php if ($eval['department']): ?>
                                <p class="card-text text-muted mb-1">
                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($eval['department']); ?>
                                </p>
                                <?php endif; ?>
                                <?php if ($eval['faculty']): ?>
                                <p class="card-text text-muted mb-1">
                                    <i class="fas fa-school"></i> <?php echo htmlspecialchars($eval['faculty']); ?>
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
                            <i class="fas fa-info-circle"></i> No evaluators found. Click "Add Evaluator" to create one.
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
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
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Evaluator Type <span class="text-danger">*</span></label>
                                <select name="evaluator_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="HOD" <?php echo ($editEvaluator['evaluator_type'] ?? '') === 'HOD' ? 'selected' : ''; ?>>HOD (Head of Department)</option>
                                    <option value="Dean" <?php echo ($editEvaluator['evaluator_type'] ?? '') === 'Dean' ? 'selected' : ''; ?>>Dean</option>
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
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <input type="text" name="department" class="form-control" list="departmentsList" value="<?php echo htmlspecialchars($editEvaluator['department'] ?? ''); ?>">
                                <datalist id="departmentsList">
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Faculty</label>
                                <input type="text" name="faculty" class="form-control" list="facultiesList" value="<?php echo htmlspecialchars($editEvaluator['faculty'] ?? ''); ?>">
                                <datalist id="facultiesList">
                                    <?php foreach ($faculties as $fac): ?>
                                    <option value="<?php echo htmlspecialchars($fac); ?>">
                                    <?php endforeach; ?>
                                </datalist>
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
                    </div>
                    <div class="modal-footer">
                        <?php if ($editEvaluator): ?>
                        <a href="manage-evaluators.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_evaluator" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Evaluator
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="add_evaluator" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Evaluator
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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