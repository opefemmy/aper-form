<?php
/**
 * Create/Reset All Admin Accounts
 * Run this script to create or reset ALL default admin accounts
 * All accounts will use password: Aper@2026
 */

require_once 'config.php';

$pdo = getDBConnection();

$password = 'Aper@2026';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo "==========================================\n";
echo "  Admin Accounts Setup\n";
echo "==========================================\n\n";

// All default admin accounts to create
$adminAccounts = [
    ['Super Administrator', 'super@admin.com', 'super_admin'],
    ['Admin User', 'admin@aper.com', 'admin'],
    ['Evaluator Staff', 'evaluator@aper.com', 'evaluator'],
    ['Report Viewer', 'viewer@aper.com', 'viewer'],
    ['Registrar', 'registrar@aper.com', 'registrar'],
];

$created = 0;
$updated = 0;

try {
    foreach ($adminAccounts as $account) {
        list($name, $email, $role) = $account;

        // Check if this email already exists
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing account
            $stmt = $pdo->prepare("UPDATE admins SET name = ?, password = ?, role = ?, status = 'active' WHERE email = ?");
            $stmt->execute([$name, $hashedPassword, $role, $email]);
            echo "✅ Updated: $email ($role)\n";
            $updated++;
        } else {
            // Insert new account
            $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$name, $email, $hashedPassword, $role]);
            echo "✅ Created: $email ($role)\n";
            $created++;
        }
    }

    echo "\n==========================================\n";
    echo "  All Accounts Ready!\n";
    echo "==========================================\n\n";
    echo "📋 Login Credentials (Password: Aper@2026):\n\n";
    echo "   • super@admin.com     - Super Administrator\n";
    echo "   • admin@aper.com      - Admin User\n";
    echo "   • evaluator@aper.com - Evaluator Staff\n";
    echo "   • viewer@aper.com     - Report Viewer\n";
    echo "   • registrar@aper.com  - Registrar\n";
    echo "\n==========================================\n";
    echo "\nLogin at: " . SITE_URL . "/login.php\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
