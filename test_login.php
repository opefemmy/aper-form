<?php
/**
 * Test Login
 * Access: https://aper.personel.ink/test_login.php?email=super@admin.com&password=Aper@2026
 */

$db_host = 'localhost';
$db_name = 'persatka_aperform';
$db_user = 'persatka_opefemmy';
$db_pass = 'Programmer@123$';

echo "<pre style='background:#1e1e1e;color:#0f0;padding:20px;font-family:monospace;'>";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $email = $_GET['email'] ?? '';
    $password = $_GET['password'] ?? '';

    echo "Testing Login:\n";
    echo "Email: $email\n";
    echo "Password: $password\n\n";

    if (empty($email) || empty($password)) {
        echo "Usage: test_login.php?email=super@admin.com&password=Aper@2026\n";
        exit;
    }

    // Check admins table
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        echo "✅ Admin found in database:\n";
        echo "  ID: " . $admin['id'] . "\n";
        echo "  Name: " . $admin['name'] . "\n";
        echo "  Email: " . $admin['email'] . "\n";
        echo "  Role: " . $admin['role'] . "\n";
        echo "  Status: " . $admin['status'] . "\n";
        echo "  Password Hash: " . substr($admin['password'], 0, 50) . "...\n\n";

        $verify = password_verify($password, $admin['password']);
        echo "password_verify('$password', hash): " . ($verify ? "✅ TRUE - LOGIN WILL WORK!" : "❌ FALSE - WRONG PASSWORD!") . "\n";
    } else {
        echo "❌ No admin found with email: $email\n\n";

        // Show all admins
        echo "All admins in database:\n";
        $stmt = $pdo->query("SELECT email, role, status FROM admins");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - " . $row['email'] . " (" . $row['role'] . ")\n";
        }
    }

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
