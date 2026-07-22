<?php
/**
 * Dashboard Test - Shows session status on dashboard
 * Access: https://aper.personel.ink/dashboard_test.php
 */

// Configure session FIRST
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

echo "<h1>📊 Dashboard Test</h1>";

echo "<div class='box'>";
echo "PHP Session Status: " . session_status() . " (2=active)\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Save Path: " . session_save_path() . "\n";
echo "</div>";

echo "<div class='box'>";
echo "<h3>📊 Session Data:</h3>";
if (empty($_SESSION)) {
    echo "<span class='error'>❌ SESSION IS EMPTY - Not logged in!</span>";
} else {
    echo "<span class='success'>✅ Session has data:</span>\n";
    foreach ($_SESSION as $key => $value) {
        echo "  $key = $value\n";
    }
}
echo "</div>";

echo "<div class='box'>";
echo "<h3>🔧 Quick Actions:</h3>";

if (isset($_GET['login'])) {
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_name'] = 'Super Administrator';
    $_SESSION['admin_email'] = 'super@admin.com';
    $_SESSION['admin_role'] = 'super_admin';
    echo "<div class='box success'>✅ SESSION SET - Now try dashboard!</div>";
}

if (isset($_GET['logout'])) {
    session_destroy();
    echo "<div class='box error'>🗑️ SESSION DESTROYED</div>";
}

echo "<a href='?login=1' class='info'>📝 Force Login (Set Session)</a> | ";
echo "<a href='?' class='info'>🔄 Refresh</a> | ";
echo "<a href='?logout=1' class='error'>🗑️ Logout</a>";
echo "</div>";

echo "<div class='box'>";
echo "<h3>🔗 Test Links:</h3>";
echo "<a href='dashboard_test.php' class='info'>📊 This Page</a> | ";
echo "<a href='dashboard.php' class='success'>➡️ Go to Real Dashboard</a> | ";
echo "<a href='login.php' class='error'>🔐 Go to Login</a>";
echo "</div>";

echo "</body></html>";
?>
