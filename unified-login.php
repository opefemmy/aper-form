<?php
require_once 'config.php';

startSession();

// If already logged in, redirect to appropriate dashboard
if (isAdminLoggedIn()) {
    redirect(SITE_URL . '/dashboard.php');
}
if (isStaffLoggedIn()) {
    redirect(SITE_URL . '/staff-dashboard.php');
}

$error = '';

// Get settings
try {
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
    $websiteUrl = $settings['website_url'] ?? 'https://ekscotech.edu.ng';
} catch (Exception $e) {
    $instName = 'Institution';
    $instAddress = '';
    $logo = '';
    $primaryColor = '#1e3a8a';
    $secondaryColor = '#3b82f6';
    $websiteUrl = 'https://ekscotech.edu.ng';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginType = $_POST['login_type'] ?? 'staff';
    $pdo = getDBConnection();

    if ($loginType === 'admin') {
        // Admin login
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password'])) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_name'] = $admin['name'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_role'] = $admin['role'];
                    redirect(SITE_URL . '/dashboard.php');
                } else {
                    $error = 'Invalid email or password';
                }
            } catch (PDOException $e) {
                $error = 'Database error. Please try again.';
            }
        }
    } else {
        // Staff login
        $staffId = sanitize($_POST['staff_id'] ?? '');
        $surname = $_POST['surname'] ?? '';

        if (empty($staffId) || empty($surname)) {
            $error = 'Please enter both Staff ID and Surname';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_id = ? AND status = 'active' LIMIT 1");
                $stmt->execute([$staffId]);
                $staff = $stmt->fetch();

                if ($staff && strtolower($staff['surname']) === strtolower($surname)) {
                    $_SESSION['staff_id'] = $staff['id'];
                    $_SESSION['staff_name'] = $staff['first_name'] . ' ' . $staff['surname'];
                    $_SESSION['staff_number'] = $staff['staff_id'];
                    $_SESSION['staff_department'] = $staff['department'];
                    $_SESSION['staff_grade_level'] = $staff['grade_level'];
                    redirect(SITE_URL . '/staff-dashboard.php');
                } else {
                    $error = 'Invalid Staff ID or Surname';
                }
            } catch (PDOException $e) {
                $error = 'Database error. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($instName); ?></title>
    <?php if (!empty($logo)): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logo); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: <?php echo $primaryColor; ?>; --secondary-blue: <?php echo $secondaryColor; ?>; }
        body { background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-card { background: white; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; max-width: 500px; width: 100%; }
        .login-header { background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%); color: white; padding: 2.5rem 2rem; text-align: center; }
        .login-header .logo-img { max-height: 80px; margin-bottom: 15px; border: 3px solid white; border-radius: 10px; padding: 5px; background: rgba(255,255,255,0.1); }
        .login-header h1 { margin: 10px 0 2px 0; font-size: 1.8rem; font-weight: 800; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); text-align: center; line-height: 1.2; }
        .login-header .address { font-size: 0.95rem; margin-top: 5px; font-weight: 500; text-align: center; opacity: 1; padding: 0 20px; }
        .login-body { padding: 2rem; }
        .form-control { border: 2px solid #e2e8f0; border-radius: 8px; padding: 0.75rem 1rem; }
        .form-control:focus { border-color: var(--secondary-blue); box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        .btn-login { background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%); border: none; padding: 0.75rem; font-weight: 600; border-radius: 8px; width: 100%; }
        .btn-login:hover { opacity: 0.9; }
        .error-message { background: #fee2e2; color: #dc2626; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .nav-tabs { border-bottom: 2px solid #e2e8f0; margin-bottom: 1.5rem; }
        .nav-tabs .nav-link { border: none; color: #64748b; font-weight: 600; padding: 0.75rem 1.5rem; }
        .nav-tabs .nav-link.active { color: <?php echo $primaryColor; ?>; border-bottom: 3px solid <?php echo $primaryColor; ?>; background: transparent; }
        .nav-tabs .nav-link:hover { border: none; }
        .back-link { text-align: center; margin-top: 1rem; }
        .back-link a { color: <?php echo $primaryColor; ?>; text-decoration: none; font-weight: 500; }
        .back-link a:hover { text-decoration: underline; }
        .hint { background: #f0f9ff; border: 1px solid #bae6fd; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <?php if (!empty($logo)): ?>
            <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" class="logo-img">
            <?php else: ?>
            <i class="fas fa-graduation-cap" style="font-size: 4rem; margin-bottom: 15px;"></i>
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($instName); ?></h1>
            <?php if (!empty($instAddress)): ?>
            <p class="address mb-0"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($instAddress); ?></p>
            <?php endif; ?>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Login Type Tabs -->
            <ul class="nav nav-tabs" id="loginTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff-login" type="button">
                        <i class="fas fa-user me-2"></i>Staff Login
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin-login" type="button">
                        <i class="fas fa-user-shield me-2"></i>Admin Login
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="loginTabsContent">
                <!-- Staff Login -->
                <div class="tab-pane fade show active" id="staff-login" role="tabpanel">
                    <form method="POST" action="">
                        <input type="hidden" name="login_type" value="staff">
                        <div class="hint">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Staff Login:</strong><br>
                            Username: Staff ID (e.g., STF001)<br>
                            Password: Your Surname
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Staff ID (Username)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" name="staff_id" placeholder="e.g., STF001" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Surname (Password)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="surname" placeholder="Enter your surname" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-login">
                            <i class="fas fa-sign-in-alt me-2"></i>Staff Login
                        </button>
                    </form>
                </div>

                <!-- Admin Login -->
                <div class="tab-pane fade" id="admin-login" role="tabpanel">
                    <form method="POST" action="">
                        <input type="hidden" name="login_type" value="admin">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" name="email" placeholder="admin@yourdomain.com" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="password" placeholder="Enter your password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-login">
                            <i class="fas fa-sign-in-alt me-2"></i>Admin Login
                        </button>
                    </form>
                </div>
            </div>

            <div class="back-link">
                <a href="<?php echo htmlspecialchars($websiteUrl); ?>" target="_blank">
                    <i class="fas fa-globe me-2"></i>Back to Website
                </a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>