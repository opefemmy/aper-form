<!DOCTYPE html>
<html>
<head>
    <title>Admin Login Diagnostic</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; }
        .error { background: #fee2e2; color: #dc2626; padding: 15px; border-radius: 8px; }
        .success { background: #dcfce7; color: #16a34a; padding: 15px; border-radius: 8px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>🔍 Admin Login Diagnostic</h1>

<?php
// Diagnostic script - works even with some issues

echo "<div class='box'>";
echo "<h2>1. PHP Extensions Check</h2>";

// Check PDO
echo "PDO available: ";
if (class_exists('PDO')) {
    echo "<span style='color:green'>✅ YES</span><br>";
    echo "PDO drivers: " . implode(', ', PDO::getAvailableDrivers()) . "<br>";
} else {
    echo "<span style='color:red'>❌ NO - This is the problem!</span><br>";
}

// Check password_verify
echo "password_verify available: ";
if (function_exists('password_verify')) {
    echo "<span style='color:green'>✅ YES</span><br>";
} else {
    echo "<span style='color:red'>❌ NO</span><br>";
}

echo "</div>";

echo "<div class='box'>";
echo "<h2>2. Database Connection Test</h2>";

$db_host = 'localhost';
$db_name = 'escohsti_aperform_db';
$db_user = 'root';
$db_pass = '';

try {
    // Try different PDO options
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    echo "<span style='color:green'>✅ Database connected successfully!</span><br>";
} catch (PDOException $e) {
    echo "<span style='color:red'>❌ Database connection failed:</span> " . $e->getMessage() . "<br>";
}

echo "</div>";

if (isset($pdo)) {
    echo "<div class='box'>";
    echo "<h2>3. Admins Table Contents</h2>";

    try {
        // Check if admins table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'admins'");
        if ($stmt->rowCount() == 0) {
            echo "<span style='color:red'>❌ Admins table does NOT exist!</span><br>";
        } else {
            echo "<span style='color:green'>✅ Admins table exists</span><br><br>";

            // Get all admins
            $stmt = $pdo->query("SELECT id, name, email, role, status, LENGTH(password) as pwd_len FROM admins");
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($admins) == 0) {
                echo "<span style='color:red'>⚠️ No admin accounts found!</span><br>";
            } else {
                echo "<table>";
                echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Pwd Hash</th></tr>";
                foreach ($admins as $admin) {
                    echo "<tr>";
                    echo "<td>" . $admin['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($admin['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($admin['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($admin['role']) . "</td>";
                    echo "<td>" . htmlspecialchars($admin['status']) . "</td>";
                    echo "<td>" . substr($admin['password'], 0, 30) . "...</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        }
    } catch (PDOException $e) {
        echo "<span style='color:red'>❌ Error reading admins:</span> " . $e->getMessage() . "<br>";
    }

    echo "</div>";

    echo "<div class='box'>";
    echo "<h2>4. Password Test</h2>";

    // Get first admin
    $stmt = $pdo->query("SELECT email, password FROM admins LIMIT 1");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        $testPassword = 'Aper@2026';
        $hashInDB = $admin['password'];

        echo "Testing password: <strong>$testPassword</strong><br>";
        echo "Hash in DB for " . $admin['email'] . ": " . substr($hashInDB, 0, 50) . "...<br><br>";

        $verifyResult = password_verify($testPassword, $hashInDB);

        if ($verifyResult) {
            echo "<div class='success'>✅ PASSWORD VERIFIES CORRECTLY!</div>";
        } else {
            echo "<div class='error'>❌ PASSWORD DOES NOT MATCH!</div>";
            echo "<br><strong>FIX:</strong> Run this SQL in phpMyAdmin:<br>";
            echo "<pre style='background:#eee;padding:10px;overflow-x:auto;'>";
            $newHash = password_hash($testPassword, PASSWORD_DEFAULT);
            echo "UPDATE admins SET password = '$newHash' WHERE email = '" . $admin['email'] . "';";
            echo "</pre>";
        }
    }

    echo "</div>";

    echo "<div class='box'>";
    echo "<h2>5. Quick Fix - Reset ALL Admin Passwords</h2>";
    echo "<p>Copy and run this SQL in phpMyAdmin:</p>";

    $newHash = password_hash('Aper@2026', PASSWORD_DEFAULT);

    echo "<textarea rows='6' style='width:100%;font-family:monospace;'>";
    echo "-- Reset ALL admin passwords to 'Aper@2026'\n";
    echo "UPDATE admins SET password = '$newHash';\n";
    echo "\n-- If no admins exist, create them:\n";
    echo "INSERT INTO admins (name, email, password, role, status) VALUES\n";
    echo "('Super Admin', 'super@admin.com', '$newHash', 'super_admin', 'active'),\n";
    echo "('Admin', 'admin@aper.com', '$newHash', 'admin', 'active'),\n";
    echo "('Registrar', 'registrar@aper.com', '$newHash', 'registrar', 'active')\n";
    echo "ON DUPLICATE KEY UPDATE password = VALUES(password);";
    echo "</textarea>";

    echo "</div>";
}
?>

<div class='box'>
    <h2>6. Server PHP Info</h2>
    <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
    <p><strong>Loaded Extensions:</strong> <?php echo implode(', ', get_loaded_extensions()); ?></p>
</div>
</body>
</html>
