<?php
/**
 * Fix Admin Passwords
 * Updates all admin passwords to Aper@2026
 */

require_once 'config.php';

$pdo = getDBConnection();

$newPassword = password_hash('Aper@2026', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE admins SET password = ?");
$stmt->execute([$newPassword]);

echo "✅ All admin passwords updated to: Aper@2026\n";
echo "Login with any admin email and password: Aper@2026\n";