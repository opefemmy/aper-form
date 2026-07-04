<?php
/**
 * Fix Registrar Role
 * Run this once on your live server to add registrar role
 */

require_once 'config.php';
requireAdminLogin();

$pdo = getDBConnection();

echo "Running fix...\n";

// Add registrar to enum
$pdo->exec("ALTER TABLE admins MODIFY COLUMN role ENUM('super_admin','admin','evaluator','viewer','registrar') DEFAULT 'admin'");

// Update registrar
$pdo->exec("UPDATE admins SET role = 'registrar' WHERE email = 'registrar@aper.com'");

echo "✅ Done! Registrar role is now active.\n";
echo "Login: registrar@aper.com / Aper@2026\n";