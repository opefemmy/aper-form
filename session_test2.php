<?php
/**
 * Complete Session Test
 * Access: https://aper.personel.ink/session_test2.php
 */

// Configure session BEFORE any output - critical!
$sessionPath = '/home/persatka/tmp';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0755, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

session_name('APER_ADMIN_SESSION');
session_start();

echo "<!DOCTYPE html><html><head><style>
body{font-family:monospace;padding:20px;background:#1e1e1e;color:#0f0;}
.box{background:#2d2d2d;padding:15px;margin:10px 0;border-radius:5px;}
.success{color:#4ade80;}
.error{color:#f87171;}
.info{color:#60a5fa;}
</style></head><body>";
echo "<h1>🧪 Complete Session Test</h1>";

echo "<div class='box'>";
echo "PHP Session Status: " . session_status() . " (0=disabled, 1=none, 2=active)\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Save Path: " . session_save_path() . "\n";
echo "Cookie Domain: " . ($_SERVER['HTTP_HOST'] ?? '') . "\n";
echo "</div>";

$action = $_GET['action'] ?? '';

if ($action === 'set') {
    $_SESSION['test_value'] = 'Hello at ' . date('H:i:s');
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_name'] = 'Super Admin';
    $_SESSION['admin_role'] = 'super_admin';
    echo "<div class='box success'>✅ SESSION DATA SET!</div>";
}

if ($action === 'unset') {
    unset($_SESSION['test_value']);
    echo "<div class='box'>❌ test_value unset</div>";
}

if ($action === 'destroy') {
    session_destroy();
    echo "<div class='box error'>🗑️ SESSION DESTROYED</div>";
}

echo "<div class='box'>";
echo "<h3>📊 Current Session Data:</h3>";
if (empty($_SESSION)) {
    echo "<span class='error'>Session is EMPTY</span>";
} else {
    echo "<span class='success'>Session has data:</span>\n";
    foreach ($_SESSION as $key => $value) {
        echo "  $key = $value\n";
    }
}
echo "</div>";

echo "<div class='box'>";
echo "<h3>🔧 Actions:</h3>";
echo "<a href='?action=set' class='info'>📝 Set Session Data</a> | ";
echo "<a href='?' class='info'>🔄 Refresh</a> | ";
echo "<a href='?action=destroy' class='error'>🗑️ Destroy Session</a>";
echo "</div>";

echo "<div class='box'>";
echo "<h3>🔗 Test Pages:</h3>";
echo "<a href='login.php'>➡️ Login Page</a><br>";
echo "<a href='dashboard.php'>➡️ Dashboard (requires login)</a><br>";
echo "<a href='session_test2.php'>➡️ This Page</a>";
echo "</div>";

echo "</body></html>";
?>
