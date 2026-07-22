<?php
/**
 * Simple Dashboard - No redirect, shows what's happening
 * Access: https://aper.personel.ink/simple_dashboard.php
 */

// Configure session FIRST - same as config.php
$sessionPath = '/home/persatka/tmp';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0755, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

// Start session with correct name BEFORE any output
session_name('APER_ADMIN_SESSION');
session_start();

echo "<!DOCTYPE html><html><head><style>
body{font-family:monospace;padding:20px;background:#1e1e1e;color:#0f0;}
.box{background:#2d2d2d;padding:15px;margin:10px 0;border-radius:5px;}
.success{color:#4ade80;}
.error{color:#f87171;}
.info{color:#60a5fa;}
</style></head><body>";

echo "<h1>📊 Simple Dashboard</h1>";

echo "<div class='box'>";
echo "Session Status: " . session_status() . " (2=active)\n";
echo "Session ID: " . session_id() . "\n";
echo "</div>";

echo "<div class='box'>";
echo "<h3>📊 Session Data:</h3>";
if (empty($_SESSION)) {
    echo "<span class='error'>❌ SESSION EMPTY - No login!</span>";
} else {
    echo "<span class='success'>✅ LOGGED IN!</span>\n";
    foreach ($_SESSION as $k => $v) {
        echo "$k = $v\n";
    }
}
echo "</div>";

echo "<div class='box'>";
echo "<h3>🔗 Links:</h3>";
echo "<a href='simple_dashboard.php'>🔄 Refresh</a> | ";
echo "<a href='login.php'>🔐 Login</a> | ";
echo "<a href='dashboard.php'>➡️ Real Dashboard</a>";
echo "</div>";

echo "</body></html>";
?>
