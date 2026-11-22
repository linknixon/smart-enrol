<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

// Enforce strict 20-minute inactivity session timeout
$timeout_seconds = 1200; // 20 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_seconds)) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time();

// Intercept logins to enforce password expiry
require_once __DIR__ . '/../config/db.php';
$adminUsername = $_SESSION['admin_username'] ?? $_SESSION['admin_user'] ?? '';
if ($adminUsername) {
    try {
        $stmt = $pdo->prepare("SELECT role, roles_json, must_change_password FROM admin_users WHERE username = ?");
        $stmt->execute([$adminUsername]);
        $uRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($uRow) {
            // Always synchronize active session with database roles on every page reload/request
            $userRoles = [$uRow['role']];
            if (!empty($uRow['roles_json'])) {
                $decoded = json_decode($uRow['roles_json'], true);
                if (is_array($decoded)) {
                    $userRoles = array_unique(array_merge($userRoles, $decoded));
                }
            }
            $_SESSION['admin_role']  = $uRow['role'];
            $_SESSION['admin_roles'] = $userRoles;

            if ($uRow['must_change_password'] == 1) {
                $currentScript = basename($_SERVER['PHP_SELF'] ?? '');
                if ($currentScript !== 'change_password.php' && $currentScript !== 'logout.php') {
                    header("Location: change_password.php?expired=1");
                    exit;
                }
            }
        }
    } catch (Exception $e) {}
}
