<?php
require_once 'config.php';

startSession();

// If already logged in, redirect to dashboard
if (isStaffLoggedIn()) {
    redirect(SITE_URL . '/staff-dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staffId = sanitize($_POST['staff_id'] ?? '');
    $surname = $_POST['surname'] ?? '';

    if (empty($staffId) || empty($surname)) {
        $error = 'Please enter both Staff ID and Surname';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_id = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$staffId]);
            $staff = $stmt->fetch();

            // Check if surname matches (case-insensitive)
            if ($staff && strtolower($staff['surname']) === strtolower($surname)) {
                // Login successful
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

// Get settings for institution
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $instName = $settings['institution_name'] ?? 'Annual Performance Evaluation';
    $instAddress = $settings['institution_address'] ?? '';
    $logo = $settings['institution_logo'] ?? '';
    $primaryColor = $settings['primary_color'] ?? '#1e3a8a';
    $secondaryColor = $settings['secondary_color'] ?? '#3b82f6';
} catch (Exception $e) {
    $instName = 'Annual Performance Evaluation';
    $instAddress = '';
    $logo = '';
    $primaryColor = '#1e3a8a';
    $secondaryColor = '#3b82f6';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - <?php echo htmlspecialchars($instName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: <?php echo $primaryColor; ?>; --secondary-blue: <?php echo $secondaryColor; ?>; }
        body { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: white; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 450px; width: 100%; }
        .login-header { background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%); color: white; padding: 2.5rem 2rem; text-align: center; border-radius: 15px 15px 0 0; }
        .login-header .logo-img { max-height: 80px; margin-bottom: 15px; border: 3px solid white; border-radius: 10px; padding: 5px; background: rgba(255,255,255,0.1); }
        .login-header h1 { margin: 10px 0 5px 0; font-size: 2.2rem; font-weight: 800; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); text-align: center; line-height: 1.3; }
        .login-header .address { font-size: 1.1rem; margin-top: 10px; font-weight: 600; text-align: center; opacity: 1; padding: 0 15px; }
        .login-header .login-type { font-size: 1.2rem; margin-top: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .login-header i { font-size: 3rem; margin-bottom: 10px; }
        .login-body { padding: 2rem; }
        .form-control { border: 2px solid #e2e8f0; border-radius: 8px; padding: 0.75rem 1rem; }
        .form-control:focus { border-color: var(--secondary-blue); box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        .btn-login { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); border: none; padding: 0.75rem; font-weight: 600; border-radius: 8px; }
        .btn-login:hover { background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); }
        .error-message { background: #fee2e2; color: #dc2626; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
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
            <p class="login-type mb-0">Staff Self-Evaluation Portal</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="hint">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Login Details:</strong><br>
                Username: Your Staff ID (e.g., STF001)<br>
                Password: Your Surname
            </div>

            <form method="POST" action="">
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
                <button type="submit" class="btn btn-primary btn-login w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>
            <div class="text-center mt-3">
                <a href="<?php echo ADMIN_URL; ?>/login.php" class="text-muted">
                    <i class="fas fa-user-shield me-1"></i>Admin Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>