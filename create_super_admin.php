<?php
/**
 * Create/Reset Super Admin Account
 * Run this script to create or reset the super admin account
 */

require_once 'config.php';

$pdo = getDBConnection();

// Super admin credentials - CHANGE THESE AS NEEDED
$name = 'Super Administrator';
$email = 'super@admin.com';
$password = 'Aper@2026';  // Default password - CHANGE THIS!
$role = 'super_admin';
$status = 'active';

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo "==========================================\n";
echo "  Super Admin Account Setup\n";
echo "==========================================\n\n";

try {
    // Check if super_admin already exists
    $stmt = $pdo->prepare("SELECT id, email FROM admins WHERE role = 'super_admin' LIMIT 1");
    $stmt->execute();
    $existing = $stmt->fetch();

    if ($existing) {
        // Update existing super_admin
        $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ?, password = ?, status = ? WHERE role = 'super_admin'");
        $stmt->execute([$name, $email, $hashedPassword, $status]);
        echo "✅ Super Admin account UPDATED successfully!\n";
        echo "   Old email: " . $existing['email'] . "\n";
    } else {
        // Insert new super_admin
        $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hashedPassword, $role, $status]);
        echo "✅ Super Admin account CREATED successfully!\n";
    }

    echo "\n--- Login Credentials ---\n";
    echo "Email:    $email\n";
    echo "Password: $password\n";
    echo "Role:     super_admin\n";
    echo "-------------------------\n";

    // Also create admin account if not exists
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $adminExists = $stmt->fetch();

    if (!$adminExists) {
        $adminPassword = password_hash('Aper@2026', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')");
        $stmt->execute(['Admin User', 'admin@aper.com', $adminPassword]);
        echo "\n✅ Admin account also created: admin@aper.com / Aper@2026\n";
    }

    // Also create registrar account if not exists
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE role = 'registrar' LIMIT 1");
    $stmt->execute();
    $registrarExists = $stmt->fetch();

    if (!$registrarExists) {
        $regPassword = password_hash('Aper@2026', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role, status) VALUES (?, ?, ?, 'registrar', 'active')");
        $stmt->execute(['Registrar', 'registrar@aper.com', $regPassword]);
        echo "✅ Registrar account also created: registrar@aper.com / Aper@2026\n";
    }

    echo "\n==========================================\n";
    echo "All admin accounts ready!\n";
    echo "==========================================\n";
    echo "\nLogin at: " . SITE_URL . "/login.php\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
