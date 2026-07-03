<?php
require_once 'config.php';

startSession();

// If already logged in, redirect to dashboard
if (isAdminLoggedIn()) {
    redirect(ADMIN_URL . '/dashboard.php');
}

// Redirect to unified login
redirect(SITE_URL . '/unified-login.php');

$error = '';

// Get settings
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $instName = $settings['institution_name'] ?? 'APER System';
    $instAddress = $settings['institution_address'] ?? '';
    $logo = $settings['institution_logo'] ?? '';
    $primaryColor = $settings['primary_color'] ?? '#1e3a8a';
    $secondaryColor = $settings['secondary_color'] ?? '#3b82f6';
} catch (Exception $e) {
    $instName = 'APER System';
    $instAddress = '';
    $logo = '';
    $primaryColor = '#1e3a8a';
    $secondaryColor = '#3b82f6';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // Login successful
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role'] = $admin['role'];

                redirect(ADMIN_URL . '/dashboard.php');
            } else {
                $error = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - APER System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: <?php echo $primaryColor; ?>;
            --secondary-blue: #3b82f6;
        }
        body {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            border-radius: 15px 15px 0 0;
        }
        .login-header .logo-img {
            max-height: 80px;
            margin-bottom: 15px;
            border: 3px solid white;
            border-radius: 10px;
            padding: 5px;
            background: rgba(255,255,255,0.1);
        }
        .login-header h1 {
            margin: 10px 0 5px 0;
            font-size: 2.2rem;
            font-weight: 800;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            text-align: center;
            line-height: 1.3;
        }
        .login-header .address {
            font-size: 1.1rem;
            margin-top: 10px;
            font-weight: 600;
            text-align: center;
            opacity: 1;
            padding: 0 15px;
        }
        .login-header .login-type {
            font-size: 1.2rem;
            margin-top: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .login-header i {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .login-body {
            padding: 2rem;
        }
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem 1rem;
        }
        .form-control:focus {
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        .btn-login {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            border-radius: 8px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
        }
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
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
            <p class="login-type mb-0">Admin Login</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
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
                <button type="submit" class="btn btn-primary btn-login w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>
            <div class="text-center mt-3">
                <a href="<?php echo SITE_URL; ?>" class="text-muted">
                    <i class="fas fa-home me-1"></i>Back to Website
                </a>
            </div>
        </div>
    </div>
</body>
</html>