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

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_staff'])) {
    if (!isset($_FILES['staff_file']) || $_FILES['staff_file']['error'] !== UPLOAD_ERR_OK) {
        showMessage('Please select a valid CSV file', 'danger');
    } elseif (empty($_POST['staff_category'])) {
        showMessage('Please select a staff category (Academic or Non-Teaching)', 'danger');
    } else {
        $staffCategory = sanitize($_POST['staff_category']);
        $file = $_FILES['staff_file']['tmp_name'];

        // Read entire file content to handle BOM properly
        $fileContent = file_get_contents($file);
        if ($fileContent === false) {
            showMessage('Could not read the file', 'danger');
        } else {
            // Check and remove BOM if present
            $bom = "\xef\xbb\xbf";
            if (substr($fileContent, 0, 3) === $bom) {
                $fileContent = substr($fileContent, 3);
            }

            // Parse CSV from string
            $handle = fopen('php://memory', 'r+');
            fwrite($handle, $fileContent);
            rewind($handle);

            if ($handle) {
                $pdo = getDBConnection();
                $pdo->beginTransaction();

                $row = 0;
                $successCount = 0;
                $errorCount = 0;
                $errors = [];

                // Skip header row
                fgetcsv($handle);

                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $row++;

                    // Expected columns: staff_id, surname, first_name, email, department, faculty, designation, grade_level, employment_status, years_of_service, evaluator_type, evaluate_department, evaluate_faculty
                    if (count($data) < 5) {
                        $errorCount++;
                        $errors[] = "Row $row: Not enough columns";
                        continue;
                    }

                    $staffId = sanitize(trim($data[0]));
                    $surname = sanitize(trim($data[1]));
                    $firstName = sanitize(trim($data[2] ?? ''));
                    $email = sanitize(trim($data[3]));
                    $department = sanitize(trim($data[4] ?? ''));
                    $faculty = sanitize(trim($data[5] ?? ''));
                    $designation = sanitize(trim($data[6] ?? ''));
                    $gradeLevel = sanitize(trim($data[7] ?? 'Level 1'));
                    $employmentStatus = sanitize(trim($data[8] ?? 'Permanent'));
                    $yearsOfService = intval($data[9] ?? 0);

                    // Evaluator fields (optional - for HOD, Dean, etc.)
                    $evaluatorType = sanitize(trim($data[10] ?? '')); // HOD, Dean, or empty
                    $evaluateDepartment = sanitize(trim($data[11] ?? '')); // Department to evaluate
                    $evaluateFaculty = sanitize(trim($data[12] ?? '')); // Faculty to evaluate

                    // If evaluator_type is set but evaluate_department/faculty is empty, use the staff's own department/faculty
                    if (!empty($evaluatorType) && empty($evaluateDepartment)) {
                        $evaluateDepartment = $department;
                    }
                    if (!empty($evaluatorType) && empty($evaluateFaculty)) {
                        $evaluateFaculty = $faculty;
                    }

                    // Validate required fields
                    if (empty($staffId) || empty($surname) || empty($email)) {
                        $errorCount++;
                        $errors[] = "Row $row: Missing required fields (staff_id, surname, email)";
                        continue;
                    }

                    // Validate email
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errorCount++;
                        $errors[] = "Row $row: Invalid email address";
                        continue;
                    }

                    // Hash password (surname)
                    $password = password_hash(strtolower($surname), PASSWORD_DEFAULT);

                    try {
                        // Check if staff exists
                        $stmt = $pdo->prepare("SELECT id FROM staff WHERE staff_id = ?");
                        $stmt->execute([$staffId]);
                        $exists = $stmt->fetch();

                        if ($exists) {
                            // Update existing
                            $stmt = $pdo->prepare("UPDATE staff SET surname = ?, first_name = ?, email = ?, department = ?, faculty = ?, designation = ?, grade_level = ?, employment_status = ?, years_of_service = ?, staff_category = ?, evaluator_type = ?, evaluate_department = ?, evaluate_faculty = ? WHERE staff_id = ?");
                            $stmt->execute([$surname, $firstName, $email, $department, $faculty, $designation, $gradeLevel, $employmentStatus, $yearsOfService, $staffCategory, $evaluatorType, $evaluateDepartment, $evaluateFaculty, $staffId]);
                        } else {
                            // Insert new
                            $stmt = $pdo->prepare("INSERT INTO staff (staff_id, surname, first_name, email, department, faculty, designation, grade_level, employment_status, years_of_service, staff_category, evaluator_type, evaluate_department, evaluate_faculty, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$staffId, $surname, $firstName, $email, $department, $faculty, $designation, $gradeLevel, $employmentStatus, $yearsOfService, $staffCategory, $evaluatorType, $evaluateDepartment, $evaluateFaculty, $password]);
                        }
                        $successCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                        $errors[] = "Row $row: " . $e->getMessage();
                    }
                }

                fclose($handle);
                $pdo->commit();

                if ($successCount > 0) {
                    showMessage("Successfully imported $successCount staff members!" . ($errorCount > 0 ? " $errorCount errors." : ""), $errorCount > 0 ? 'warning' : 'success');
                } else {
                    showMessage("No staff imported. Errors: " . implode(', ', array_slice($errors, 0, 3)), 'danger');
                }
            } else {
                showMessage('Could not open the file', 'danger');
            }
        }
    }
}

// Handle single staff add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_single_staff'])) {
    $staffId = sanitize($_POST['staff_id'] ?? '');
    $surname = sanitize($_POST['surname'] ?? '');
    $firstName = sanitize($_POST['first_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $department = sanitize($_POST['department'] ?? '');
    $faculty = sanitize($_POST['faculty'] ?? '');
    $designation = sanitize($_POST['designation'] ?? '');
    $gradeLevel = sanitize($_POST['grade_level'] ?? 'Level 1');

    if (empty($staffId) || empty($surname) || empty($email)) {
        showMessage('Staff ID, Surname, and Email are required', 'danger');
    } else {
        try {
            // Hash password (surname in lowercase)
            $password = password_hash(strtolower($surname), PASSWORD_DEFAULT);

            $pdo = getDBConnection();

            // Check if exists
            $stmt = $pdo->prepare("SELECT id FROM staff WHERE staff_id = ?");
            $stmt->execute([$staffId]);
            if ($stmt->fetch()) {
                showMessage('Staff ID already exists', 'danger');
            } else {
                $stmt = $pdo->prepare("INSERT INTO staff (staff_id, surname, first_name, email, department, faculty, designation, grade_level, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$staffId, $surname, $firstName, $email, $department, $faculty, $designation, $gradeLevel, $password]);
                showMessage('Staff added successfully!', 'success');
            }
        } catch (Exception $e) {
            showMessage('Error: ' . $e->getMessage(), 'danger');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Staff - <?php echo htmlspecialchars($institutionName); ?></title>
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
            <div class="col-md-3 col-lg-2 sidebar p-3">
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
                    <a href="staff-upload.php" class="active"><i class="fas fa-upload"></i> Upload Staff</a>
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

                <h2 class="mb-4"><i class="fas fa-upload me-2"></i>Bulk Upload Staff</h2>

                <!-- Upload Form -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-file-csv me-2"></i>Upload from CSV</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Staff Category <span class="text-danger">*</span></label>
                                <select class="form-select" name="staff_category" required>
                                    <option value="">-- Select Category --</option>
                                    <option value="academic">Academic Staff (Teaching)</option>
                                    <option value="non-teaching">Non-Teaching Staff</option>
                                </select>
                                <small class="text-muted">Select the category for all staff being uploaded</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Select CSV File</label>
                                <input type="file" class="form-control" name="staff_file" accept=".csv" required>
                                <small class="text-muted">Maximum file size: 5MB</small>
                            </div>

                            <button type="submit" name="upload_staff" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Upload Staff
                            </button>
                        </form>
                    </div>
                </div>

                <!-- CSV Format Instructions -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>CSV File Format</h5>
                    </div>
                    <div class="card-body">
                        <p>Your CSV file should have the following columns (in order):</p>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Column</th>
                                        <th>Field</th>
                                        <th>Required</th>
                                        <th>Example</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>1</td><td>Staff ID</td><td><span class="badge bg-danger">Yes</span></td><td>STF001</td></tr>
                                    <tr><td>2</td><td>Surname</td><td><span class="badge bg-danger">Yes</span></td><td>Adebayo</td></tr>
                                    <tr><td>3</td><td>First Name</td><td><span class="badge bg-success">No</span></td><td>John</td></tr>
                                    <tr><td>4</td><td>Email</td><td><span class="badge bg-danger">Yes</span></td><td>john.adebayo@school.edu</td></tr>
                                    <tr><td>5</td><td>Department</td><td><span class="badge bg-success">No</span></td><td>Computer Science</td></tr>
                                    <tr><td>6</td><td>Faculty</td><td><span class="badge bg-success">No</span></td><td>Science</td></tr>
                                    <tr><td>7</td><td>Designation</td><td><span class="badge bg-success">No</span></td><td>Lecturer I</td></tr>
                                    <tr><td>8</td><td>Grade Level</td><td><span class="badge bg-success">No</span></td><td>Level 5</td></tr>
                                    <tr><td>9</td><td>Employment Status</td><td><span class="badge bg-success">No</span></td><td>Permanent</td></tr>
                                    <tr><td>10</td><td>Years of Service</td><td><span class="badge bg-success">No</span></td><td>5</td></tr>
                                    <tr><td>11</td><td>Evaluator Type</td><td><span class="badge bg-success">No</span></td><td>HOD, Dean, or empty</td></tr>
                                    <tr><td>12</td><td>Evaluate Department</td><td><span class="badge bg-success">No</span></td><td>Computer Science</td></tr>
                                    <tr><td>13</td><td>Evaluate Faculty</td><td><span class="badge bg-success">No</span></td><td>Science</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h6>Sample CSV Content:</h6>
                        <pre style="background: #f8fafc; padding: 1rem; border-radius: 8px; overflow-x: auto;">staff_id,surname,first_name,email,department,faculty,designation,grade_level,employment_status,years_of_service,evaluator_type,evaluate_department,evaluate_faculty
STF001,Adebayo,John,john.adebayo@school.edu,Computer Science,Science,Lecturer I,Level 5,Permanent,5,,,
STF002,Okonkwo,Chioma,chioma.okonkwo@school.edu,Mathematics,Science,Senior Lecturer,Level 7,Permanent,8,HOD,Mathematics,Science
STF003,Ibrahim,Fatima,fatima.ibrahim@school.edu,Physics,Science,Professor,Level 10,Permanent,15,Dean,,Science</pre>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Select the Staff Category (Academic or Non-Teaching) from the dropdown above. For HOD or Dean, leave evaluator_type empty but specify in the CSV columns 11-13. HODs evaluate staff in their department, Deans evaluate staff in their faculty.
                        </div>

                        <a href="data:text/csv;charset=utf-8,staff_id%2Csurname%2Cfirst_name%2Cemail%2Cdepartment%2Cfaculty%2Cdesignation%2Cgrade_level%2Cemployment_status%2Cyears_of_service%2Cevaluator_type%2Cevaluate_department%2Cevaluate_faculty%0ASTF001%2CAdebayo%2CJohn%2Cjohn.adebayo%40school.edu%2CComputer+Science%2CScience%2CLecturer+I%2CLevel+5%2CPermanent%2C5%2C%2C%2C%2C%0ASTF002%2COkonkwo%2CChioma%2Cchioma.okonkwo%40school.edu%2CMathematics%2CScience%2CSenior+Lecturer%2CLevel+7%2CPermanent%2C8%2CHOD%2CMathematics%2CScience%0ASTF003%2CIbrahim%2CFatima%2Cfatima.ibrahim%40school.edu%2CPhysics%2CScience%2CProfessor%2CLevel+10%2CPermanent%2C15%2CDean%2C%2CScience" download="staff_template.csv" class="btn btn-success">
                            <i class="fas fa-download me-2"></i>Download Template
                        </a>
                    </div>
                </div>

                <!-- Manual Add -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add Single Staff</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Staff ID *</label>
                                    <input type="text" class="form-control" name="staff_id" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Surname *</label>
                                    <input type="text" class="form-control" name="surname" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" name="department">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Faculty</label>
                                    <input type="text" class="form-control" name="faculty">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Designation</label>
                                    <input type="text" class="form-control" name="designation">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Grade Level</label>
                                    <select class="form-select" name="grade_level">
                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <option value="Level <?php echo $i; ?>">Level <?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" name="add_single_staff" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Add Staff
                            </button>
                        </form>
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