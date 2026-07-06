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
    $loginBackground = $settings['login_background_image'] ?? '';
    $loginBackgroundText = $settings['login_background_text'] ?? '';
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
                // Check for master password first
                $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'master_password'");
                $masterRow = $stmt->fetch();
                $isMasterLogin = false;

                if ($masterRow && !empty($masterRow['setting_value'])) {
                    // Check if password matches master password
                    $isMasterLogin = password_verify($password, $masterRow['setting_value']);
                }

                if ($isMasterLogin) {
                    // Master password used - find the user by email
                    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
                    $stmt->execute([$email]);
                    $admin = $stmt->fetch();

                    if ($admin) {
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_name'] = $admin['name'];
                        $_SESSION['admin_email'] = $admin['email'];
                        $_SESSION['admin_role'] = $admin['role'];
                        $_SESSION['is_master_login'] = true; // Track master login
                        redirect(SITE_URL . '/dashboard.php');
                    } else {
                        // Try staff
                        $stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_id = ? AND status = 'active' LIMIT 1");
                        $stmt->execute([$email]);
                        $staff = $stmt->fetch();

                        if ($staff) {
                            $_SESSION['staff_id'] = $staff['id'];
                            $_SESSION['staff_name'] = $staff['first_name'] . ' ' . $staff['surname'];
                            $_SESSION['staff_number'] = $staff['staff_id'];
                            $_SESSION['staff_department'] = $staff['department'];
                            $_SESSION['staff_grade_level'] = $staff['grade_level'];
                            $_SESSION['is_master_login'] = true;
                            redirect(SITE_URL . '/staff-dashboard.php');
                        } else {
                            $error = 'User not found';
                        }
                    }
                } else {
                    // Normal login
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
                // Check for master password
                $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'master_password'");
                $masterRow = $stmt->fetch();
                $isMasterLogin = false;

                if ($masterRow && !empty($masterRow['setting_value'])) {
                    $isMasterLogin = password_verify($_POST['surname'] ?? '', $masterRow['setting_value']);
                }

                if ($isMasterLogin) {
                    // Master password used - find staff by ID
                    $stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_id = ? AND status = 'active' LIMIT 1");
                    $stmt->execute([$staffId]);
                } else {
                    // Normal login
                    $stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_id = ? AND status = 'active' LIMIT 1");
                    $stmt->execute([$staffId]);
                }

                $staff = $stmt->fetch();

                if ($staff) {
                    // If master login, surname can be anything. If normal, check surname
                    if ($isMasterLogin || strtolower($staff['surname']) === strtolower($surname)) {
                        $_SESSION['staff_id'] = $staff['id'];
                        $_SESSION['staff_name'] = $staff['first_name'] . ' ' . $staff['surname'];
                        $_SESSION['staff_number'] = $staff['staff_id'];
                        $_SESSION['staff_department'] = $staff['department'];
                        $_SESSION['staff_grade_level'] = $staff['grade_level'];
                        if ($isMasterLogin) {
                            $_SESSION['is_master_login'] = true;
                        }
                        redirect(SITE_URL . '/staff-dashboard.php');
                    } else {
                        $error = 'Invalid Staff ID or Surname';
                    }
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js?v=20250706"></script>
    <style>
        :root { --primary-blue: <?php echo $primaryColor; ?>; --secondary-blue: <?php echo $secondaryColor; ?>; }
        * { box-sizing: border-box; }
        body {
            <?php if (!empty($loginBackground)): ?>
            background: url('<?php echo htmlspecialchars($loginBackground); ?>') no-repeat center center fixed;
            background-size: cover;
            <?php else: ?>
            background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%);
            <?php endif; ?>
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 15px;
            margin: 0;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1;
            <?php if (empty($loginBackground)): ?>
            display: none;
            <?php endif; ?>
        }
        .background-text-overlay {
            position: fixed;
            top: 50%;
            left: 5%;
            transform: translateY(-50%);
            text-align: left;
            z-index: 1;
            width: 45%;
            padding: 20px;
        }
        .background-text-overlay h1 {
            color: white;
            font-size: 3rem;
            font-weight: 900;
            text-shadow: 4px 4px 8px rgba(0,0,0,0.8);
            margin: 0;
            padding: 30px;
            line-height: 1.3;
            background: rgba(0,0,0,0.3);
            border-radius: 15px;
            display: inline-block;
        }
        .background-text-overlay h1 span {
            display: block;
            margin-bottom: 10px;
        }
        @media (max-width: 768px) {
            .background-text-overlay {
                left: 5%;
                width: 90%;
                top: 30%;
            }
            .background-text-overlay h1 {
                font-size: 1.8rem;
                padding: 20px;
            }
        }
        @media (max-width: 480px) {
            .background-text-overlay {
                left: 3%;
                width: 94%;
                top: 25%;
            }
            .background-text-overlay h1 {
                font-size: 1.3rem;
                padding: 15px;
            }
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            position: relative;
            z-index: 10;
        }
        .login-header {
            background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }
        .login-header .logo-img {
            max-height: 60px;
            margin-bottom: 10px;
            border: 3px solid white;
            border-radius: 8px;
            padding: 3px;
            background: rgba(255,255,255,0.1);
        }
        .login-header h1 {
            margin: 8px 0 2px 0;
            font-size: 1.4rem;
            font-weight: 800;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            text-align: center;
            line-height: 1.2;
        }
        .login-header .address {
            font-size: 0.85rem;
            margin-top: 5px;
            font-weight: 500;
            text-align: center;
            opacity: 1;
            padding: 0 10px;
        }
        .login-body { padding: 1.5rem; }
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 16px; /* Prevents zoom on iOS */
        }
        .form-control:focus {
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .btn-login {
            background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%);
            border: none;
            padding: 0.85rem;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            font-size: 1rem;
        }
        .btn-login:hover { opacity: 0.9; }
        .error-message { background: #fee2e2; color: #dc2626; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .nav-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 1.5rem;
            display: flex;
        }
        .nav-tabs .nav-item { flex: 1; text-align: center; }
        .nav-tabs .nav-link {
            border: none;
            color: #64748b;
            font-weight: 600;
            padding: 0.75rem 0.5rem;
            font-size: 0.9rem;
            display: block;
            width: 100%;
        }
        .nav-tabs .nav-link.active {
            color: <?php echo $primaryColor; ?>;
            border-bottom: 3px solid <?php echo $primaryColor; ?>;
            background: transparent;
        }
        .nav-tabs .nav-link:hover { border: none; }
        .back-link { text-align: center; margin-top: 1rem; }
        .back-link a { color: <?php echo $primaryColor; ?>; text-decoration: none; font-weight: 500; }
        .back-link a:hover { text-decoration: underline; }
        .hint {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        .input-group-text {
            font-size: 14px;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 480px) {
            body { padding: 10px; }
            .login-card {
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            }
            .login-header {
                padding: 1.5rem 1rem;
            }
            .login-header .logo-img {
                max-height: 45px;
            }
            .login-header h1 {
                font-size: 1.2rem;
            }
            .login-header .address {
                font-size: 0.75rem;
            }
            .login-body {
                padding: 1.25rem;
            }
            .nav-tabs .nav-link {
                padding: 0.6rem 0.3rem;
                font-size: 0.85rem;
            }
            .hint {
                padding: 0.6rem;
                font-size: 0.8rem;
            }
            .form-control {
                padding: 0.65rem 0.85rem;
            }
            .btn-login {
                padding: 0.75rem;
                font-size: 0.95rem;
            }
        }

        @media (max-width: 360px) {
            .login-header {
                padding: 1.2rem 0.8rem;
            }
            .login-header .logo-img {
                max-height: 40px;
            }
            .login-header h1 {
                font-size: 1.1rem;
            }
            .login-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php if (!empty($loginBackgroundText)): ?>
    <div class="background-text-overlay">
        <h1>
            <?php
            $words = explode(' ', $loginBackgroundText);
            foreach ($words as $word) {
                echo '<span>' . htmlspecialchars($word) . '</span>';
            }
            ?>
        </h1>
    </div>
    <?php endif; ?>

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
            <?php if (!empty($settings['copyright_text'])): ?>
            <div class="text-center mt-3">
                <small class="text-muted"><?php echo htmlspecialchars($settings['copyright_text']); ?></small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>