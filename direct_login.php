<?php
/**
 * Direct Login Test
 * Access: https://aper.personel.ink/direct_login.php
 */

// Configure session FIRST
$sessionPath = '/home/persatka/tmp';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0755, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

// Database credentials
$db_host = 'localhost';
$db_name = 'persatka_aperform';
$db_user = 'persatka_opefemmy';
$db_pass = 'Programmer@123$';

echo "<!DOCTYPE html><html><head><style>
body{font-family:monospace;padding:20px;background:#1e1e1e;color:#0f0;}
.box{background:#2d2d2d;padding:15px;margin:10px 0;border-radius:5px;}
.success{color:#4ade80;}
.error{color:#f87171;}
.info{color:#60a5fa;}
</style></head><body>";

$email = $_GET['email'] ?? 'super@admin.com';
$password = $_GET['password'] ?? 'Aper@2026';

echo "<h1>🔐 Direct Login Test</h1>";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        // Start session and set variables
        session_name('APER_ADMIN_SESSION');
        session_start();

        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_role'] = $admin['role'];

        echo "<div class='box success'>✅ LOGIN SUCCESS!</div>";
        echo "<div class='box'>";
        echo "Session ID: " . session_id() . "\n";
        echo "Session Data:\n";
        print_r($_SESSION);
        echo "</div>";

        echo "<div class='box'>";
        echo "<h3>Redirecting in 3 seconds...</h3>";
        echo "<meta http-equiv='refresh' content='3;url=dashboard.php'>";
        echo "<a href='dashboard.php'>Click here to go to dashboard now</a>";
        echo "</div>";
    } else {
        echo "<div class='box error'>❌ Login failed - invalid credentials</div>";
    }

} catch (PDOException $e) {
    echo "<div class='box error'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>
