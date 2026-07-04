<?php
/**
 * Set Master Password
 * Use this script to set a master password that can unlock any user account
 */

require_once 'config.php';
requireAdminLogin();

$message = getMessage();
$pdo = getDBConnection();

// Get current master password setting
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'master_password'");
$row = $stmt->fetch();
$masterPassword = $row['setting_value'] ?? '';

// Update master password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_master'])) {
    $newPassword = $_POST['master_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword) || strlen($newPassword) < 6) {
        showMessage('Password must be at least 6 characters', 'danger');
    } elseif ($newPassword !== $confirmPassword) {
        showMessage('Passwords do not match', 'danger');
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('master_password', ?)
            ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$hashed, $hashed]);
        showMessage('Master password set successfully!', 'success');
    }
}

// Remove master password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_master'])) {
    $stmt = $pdo->prepare("DELETE FROM settings WHERE setting_key = 'master_password'");
    $stmt->execute();
    showMessage('Master password removed!', 'success');
}

// Get settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$instName = $settings['institution_name'] ?? 'Institution';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Password - <?php echo htmlspecialchars($instName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f3f4f6; padding: 20px; }
    </style>
</head>
<body>
    <div class="container" style="max-width: 600px;">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>Master Password</h5>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show">
                        <?php echo $message['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>What is Master Password?</strong><br>
                    The master password can be used to login as ANY staff or admin user. Use this only for emergency access or when you need to recover an account.
                </div>

                <?php if (!empty($masterPassword)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Master password is set!</strong><br>
                        You can use this password to login as any user.
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">New Master Password</label>
                        <input type="password" class="form-control" name="master_password" minlength="6" placeholder="Enter master password (min 6 characters)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" name="confirm_password" minlength="6" placeholder="Confirm master password">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="set_master" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Set Master Password
                        </button>
                        <?php if (!empty($masterPassword)): ?>
                        <button type="submit" name="remove_master" class="btn btn-danger" onclick="return confirm('Remove master password? This cannot be undone.');">
                            <i class="fas fa-trash me-2"></i>Remove
                        </button>
                        <?php endif; ?>
                    </div>
                </form>

                <hr>
                <h6>How to use:</h6>
                <ol>
                    <li>Go to the login page</li>
                    <li>Enter any user ID (staff ID or email)</li>
                    <li>Enter the <strong>master password</strong> instead of the user's regular password</li>
                    <li>You will be logged in as that user</li>
                </ol>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>