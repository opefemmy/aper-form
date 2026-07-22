<?php
/**
 * Session Test
 * Access: https://aper.personel.ink/session_test.php
 */

echo "<!DOCTYPE html><html><head><style>
body{font-family:monospace;padding:20px;background:#1e1e1e;color:#0f0;}
.box{background:#2d2d2d;padding:15px;margin:10px 0;border-radius:5px;}
.success{color:#4ade80;}
.error{color:#f87171;}
.info{color:#60a5fa;}
</style></head><body>";
echo "<h1>🧪 Session Test</h1>";

// Start session
session_name('APER_ADMIN_SESSION');
session_start();

echo "<div class='box'>";
echo "Session Status: " . session_status() . " (2 = active)\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Save Path: " . session_save_path() . "\n";
echo "</div>";

if (isset($_GET['set'])) {
    $_SESSION['test'] = 'Hello Session!';
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_name'] = 'Super Admin';
    echo "<div class='box success'>✅ Session variables SET</div>";
}

if (isset($_GET['clear'])) {
    session_destroy();
    echo "<div class='box'>🗑️ Session DESTROYED</div>";
}

echo "<div class='box'>";
echo "Current Session Data:\n";
print_r($_SESSION);
echo "</div>";

echo "<div class='box'>";
echo "<h3>Actions:</h3>";
echo "<a href='?set=1' style='color:#60a5fa;'>📝 Set Session</a> | ";
echo "<a href='?' style='color:#60a5fa;'>🔄 Refresh</a> | ";
echo "<a href='?clear=1' style='color:#f87171;'>🗑️ Destroy</a>";
echo "</div>";

echo "<div class='box'>";
echo "<h3>Test Links:</h3>";
echo "<a href='login.php' style='color:#60a5fa;'>➡️ Go to login</a><br>";
echo "<a href='dashboard.php' style='color:#60a5fa;'>➡️ Go to dashboard</a>";
echo "</div>";

echo "</body></html>";
?>
