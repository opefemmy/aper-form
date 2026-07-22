<?php
/**
 * Direct Admin Login Fix
 * Run this to create/fix admin accounts
 * Access: https://aper.personel.ink/fix_admin.php
 */

// Database credentials
$db_host = 'localhost';
$db_name = 'persatka_aperform';
$db_user = 'persatka_opefemmy';
$db_pass = 'Programmer@123$';

echo "<!DOCTYPE html><html><head><style>
body{font-family:Arial;padding:20px;background:#f5f5f5;}
.box{background:white;padding:20px;margin:10px 0;border-radius:8px;}
.success{background:#dcfce7;color:#16a34a;padding:15px;border-radius:8px;}
.error{background:#fee2e2;color:#dc2626;padding:15px;border-radius:8px;}
.btn{background:#2563eb;color:white;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;}
</style></head><body>";
echo "<h1>🔧 Admin Account Fix</h1>";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='box'>✅ Database connected!</div>";

    // Check admins table
    $stmt = $pdo->query("SELECT id, name, email, role, status FROM admins");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='box'><h3>Current Admin Accounts:</h3>";
    if (count($admins) == 0) {
        echo "<p>No admin accounts found!</p>";
    } else {
        echo "<table border='1' cellpadding='10'><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr>";
        foreach ($admins as $a) {
            echo "<tr><td>{$a['id']}</td><td>{$a['name']}</td><td>{$a['email']}</td><td>{$a['role']}</td><td>{$a['status']}</td></tr>";
        }
        echo "</table>";
    }
    echo "</div>";

    // Fix/Reset passwords
    if (isset($_GET['fix'])) {
        $password = 'Aper@2026';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Update all existing admins
        $stmt = $pdo->prepare("UPDATE admins SET password = ?, status = 'active'");
        $stmt->execute([$hash]);

        // Insert missing admins if needed
        $admins_to_create = [
            ['Super Administrator', 'super@admin.com', 'super_admin'],
            ['Admin User', 'admin@aper.com', 'admin'],
            ['Evaluator Staff', 'evaluator@aper.com', 'evaluator'],
            ['Report Viewer', 'viewer@aper.com', 'viewer'],
            ['Registrar', 'registrar@aper.com', 'registrar'],
        ];

        foreach ($admins_to_create as $admin) {
            try {
                $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$admin[0], $admin[1], $hash, $admin[2]]);
                echo "<div class='success'>✅ Created: {$admin[1]} ({$admin[2]})</div>";
            } catch (Exception $e) {
                // Already exists - that's ok
            }
        }

        echo "<div class='success'>✅ All admin passwords reset to: <strong>$password</strong></div>";
    }

    echo "<div class='box'>";
    echo "<h3>Login Credentials:</h3>";
    echo "<ul>";
    echo "<li><strong>super@admin.com</strong> - Super Admin</li>";
    echo "<li><strong>admin@aper.com</strong> - Admin</li>";
    echo "<li><strong>registrar@aper.com</strong> - Registrar</li>";
    echo "</ul>";
    echo "<p>Password for all: <strong>Aper@2026</strong></p>";
    echo "</div>";

    echo "<a href='?fix=1' class='btn'>🔧 Reset All Passwords</a>";
    echo " <a href='login.php' class='btn' style='background:#16a34a;'>➡️ Go to Login</a>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Database Error: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>
