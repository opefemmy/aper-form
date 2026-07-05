<?php
/**
 * Database Configuration
 * Annual Performance Evaluation System
 */

// Database credentials - UPDATE THESE FOR YOUR SERVER
define('DB_HOST', 'localhost');
define('DB_NAME', 'escohsti_aperform_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site URL - Auto-detect based on current host
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8088';
define('SITE_URL', $protocol . '://' . $host);
define('ADMIN_URL', SITE_URL);

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('EMAIL_FROM', 'noreply@yourdomain.com');
define('EMAIL_TO', 'evaluation@yourdomain.com');

// Session name
define('SESSION_NAME', 'APER_ADMIN_SESSION');

// Timezone
date_default_timezone_set('Africa/Lagos');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Database Connection
 */
function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    return $pdo;
}

/**
 * Start Session
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn() {
    startSession();
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Check if staff is logged in
 */
function isStaffLoggedIn() {
    startSession();
    return isset($_SESSION['staff_id']) && !empty($_SESSION['staff_id']);
}

/**
 * Get current admin role
 */
function getAdminRole() {
    startSession();
    return $_SESSION['admin_role'] ?? 'viewer';
}

/**
 * Check if admin has permission
 */
function hasPermission($permission) {
    $role = getAdminRole();

    // Super admin has all permissions
    if ($role === 'super_admin') {
        return true;
    }

    // Role-based permissions
    $permissions = [
        'super_admin' => ['settings', 'staff_add', 'staff_edit', 'staff_delete', 'evaluate', 'reports_view', 'reports_export', 'reports_pdf', 'sessions', 'users_manage', 'delete_evaluation', 'staff_upload', 'supervisor_assess', 'registrar_approve', 'download_all_data'],
        'admin' => ['settings', 'staff_add', 'staff_edit', 'staff_delete', 'evaluate', 'reports_view', 'reports_export', 'reports_pdf', 'sessions', 'delete_evaluation', 'staff_upload', 'supervisor_assess', 'registrar_approve', 'download_all_data'],
        'supervisor' => ['supervisor_assess', 'reports_view'],
        'registrar' => ['registrar_approve', 'reports_view', 'reports_export', 'download_all_data'],
        'evaluator' => ['evaluate', 'reports_view'],
        'viewer' => ['reports_view'],
    ];

    return isset($permissions[$role]) && in_array($permission, $permissions[$role]);
}

/**
 * Require specific permission
 */
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        die("You don't have permission to access this page.");
    }
}

/**
 * Redirect if not logged in
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . ADMIN_URL . '/login.php');
        exit;
    }
}

/**
 * Require staff login
 */
function requireStaffLogin() {
    if (!isStaffLoggedIn()) {
        header('Location: ' . SITE_URL . '/staff-login.php');
        exit;
    }
}

/**
 * Get current admin info
 */
function getCurrentAdmin() {
    startSession();
    if (isAdminLoggedIn()) {
        return [
            'id' => $_SESSION['admin_id'],
            'name' => $_SESSION['admin_name'],
            'email' => $_SESSION['admin_email'],
            'role' => $_SESSION['admin_role']
        ];
    }
    return null;
}

/**
 * Get current staff info (with department and grade level)
 */
function getCurrentStaff() {
    startSession();
    if (isStaffLoggedIn()) {
        // Try to get full details from session or database
        if (isset($_SESSION['staff_department']) && isset($_SESSION['staff_grade_level'])) {
            return [
                'id' => $_SESSION['staff_id'],
                'name' => $_SESSION['staff_name'],
                'staff_number' => $_SESSION['staff_number'],
                'department' => $_SESSION['staff_department'],
                'grade_level' => $_SESSION['staff_grade_level']
            ];
        }
        // Fallback: fetch from database
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, staff_id, surname, first_name, department, grade_level FROM staff WHERE id = ?");
        $stmt->execute([$_SESSION['staff_id']]);
        $staff = $stmt->fetch();
        if ($staff) {
            return [
                'id' => $staff['id'],
                'name' => $staff['first_name'] . ' ' . $staff['surname'],
                'staff_number' => $staff['staff_id'],
                'department' => $staff['department'],
                'grade_level' => $staff['grade_level']
            ];
        }
        return [
            'id' => $_SESSION['staff_id'],
            'name' => $_SESSION['staff_name'],
            'staff_number' => $_SESSION['staff_number'],
            'department' => '',
            'grade_level' => ''
        ];
    }
    return null;
}

/**
 * Get staff details by ID
 */
function getStaffDetails($staffId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([$staffId]);
    return $stmt->fetch();
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Show message
 */
function showMessage($message, $type = 'success') {
    startSession();
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function getMessage() {
    startSession();
    if (isset($_SESSION['message'])) {
        $msg = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'success';
        unset($_SESSION['message'], $_SESSION['message_type']);
        return ['message' => $msg, 'type' => $type];
    }
    return null;
}

/**
 * Role labels
 */
function getRoleLabel($role) {
    $labels = [
        'super_admin' => 'Super Administrator',
        'admin' => 'Administrator',
        'evaluator' => 'Evaluator',
        'viewer' => 'Viewer',
        'registrar' => 'Registrar'
    ];
    return $labels[$role] ?? $role;
}

/**
 * Role colors
 */
function getRoleColor($role) {
    $colors = [
        'super_admin' => 'danger',
        'admin' => 'primary',
        'supervisor' => 'warning',
        'registrar' => 'info',
        'evaluator' => 'success',
        'viewer' => 'secondary'
    ];
    return $colors[$role] ?? 'secondary';
}

/**
 * Get system colors from settings
 */
function getSystemColors() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('primary_color', 'secondary_color')");
    $colors = ['primary_color' => '#1e3a8a', 'secondary_color' => '#3b82f6'];
    while ($row = $stmt->fetch()) {
        if (!empty($row['setting_value'])) {
            $colors[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $colors;
}

/**
 * Get institution logo URL
 */
function getInstitutionLogo() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'institution_logo'");
    $row = $stmt->fetch();
    return $row['setting_value'] ?? '';
}

/**
 * Get institution name
 */
function getInstitutionName() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'institution_name'");
    $row = $stmt->fetch();
    return $row['setting_value'] ?? 'Institution';
}