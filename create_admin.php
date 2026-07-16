<?php
/**
 * Create Admin Account
 * Run this script to create an admin account
 */

require_once 'config.php';

$pdo = getDBConnection();

// Create admin account
$hashedPassword = password_hash('Aper@2026', PASSWORD_DEFAULT);

try {
    // Try to insert - if exists, update
    $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')");
    $stmt->execute(['System Admin', 'admin@aper.com', $hashedPassword]);
    echo "✅ Admin account created successfully!\n";
    echo "Email: admin@aper.com\n";
    echo "Password: Aper@2026\n";
} catch (Exception $e) {
    // If already exists, update password
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE email = 'admin@aper.com'");
        $stmt->execute([$hashedPassword]);
        echo "✅ Admin password updated successfully!\n";
        echo "Email: admin@aper.com\n";
        echo "Password: Aper@2026\n";
    } else {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

// Also create registrar if not exists
$hashedPassword2 = password_hash('Aper@2026', PASSWORD_DEFAULT);
try {
    $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role, status) VALUES (?, ?, ?, 'registrar', 'active')");
    $stmt->execute(['Registrar', 'registrar@aper.com', $hashedPassword2]);
    echo "✅ Registrar account created successfully!\n";
} catch (Exception $e) {
    // Ignore if exists
    echo "ℹ️  Registrar account already exists (skipped)\n";
}

echo "\nNow try logging in with:\n";
echo "Email: admin@aper.com\n";
echo "Password: Aper@2026\n";
?>