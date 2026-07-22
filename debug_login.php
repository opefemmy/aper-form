<?php
/**
 * Debug Login - Shows what's happening during login
 * Access: https://aper.personel.ink/debug_login.php
 */

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
echo "<h1>🔍 Login Debug</h1>";

$action = $_GET['action'] ?? '';

// Simulate login
if ($action === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    echo "<div class='box'>📝 POST Data:\n";
    echo "  Email: $email\n";
    echo "  Password: $password\n</div>";

    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            echo "<div class='box'>✅ Admin found: " . $admin['name'] . " (" . $admin['role'] . ")</div>";

            $verify = password_verify($password, $admin['password']);
            echo "<div class='box'>🔑 password_verify result: " . ($verify ? "TRUE" : "FALSE") . "</div>";

            if ($verify) {
                echo "<div class='box success'>🎉 LOGIN SUCCESS! Should redirect to dashboard.php</div>";
                echo "<div class='box'>📋 Session data that would be set:\n";
                echo "  admin_id: " . $admin['id'] . "\n";
                echo "  admin_name: " . $admin['name'] . "\n";
                echo "  admin_email: " . $admin['email'] . "\n";
                echo "  admin_role: " . $admin['role'] . "\n";
                echo "</div>";
            } else {
                echo "<div class='box error'>❌ Password mismatch!</div>";
            }
        } else {
            echo "<div class='box error'>❌ No admin found with email: $email</div>";
        }

    } catch (PDOException $e) {
        echo "<div class='box error'>❌ DB Error: " . $e->getMessage() . "</div>";
    }
}

// Show login form
echo "<div class='box'>";
echo "<h3>Test Login:</h3>";
echo "<form method='POST' action='?action=login'>";
echo "Email: <input type='text' name='email' value='super@admin.com' style='width:300px;'><br><br>";
echo "Password: <input type='password' name='password' value='Aper@2026' style='width:300px;'><br><br>";
echo "<button type='submit'>Test Login</button>";
echo "</form>";
echo "</div>";

echo "<div class='box'>";
echo "<h3>Direct Links:</h3>";
echo "<a href='login.php' style='color:#60a5fa;'>➡️ Go to actual login page</a><br>";
echo "<a href='dashboard.php' style='color:#60a5fa;'>➡️ Go to dashboard (if logged in)</a>";
echo "</div>";

echo "</body></html>";
?>
