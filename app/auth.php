<?php
// Enforce official database connection and domain-wide session initialization
require_once __DIR__ . '/../config/db.php';

// Verify active administrator session
if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['admin_username'])) {
    session_unset();
    header("Location: login.php");
    exit;
}

// Enforce 2-hour inactivity session timeout
$timeout_seconds = 7200; // 2 hours
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_seconds)) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time();

// Synchronize user role, identity, and status directly from the database on every request
$adminUsername = $_SESSION['admin_username'];
try {
    $stmt = $pdo->prepare("SELECT id, role, roles_json, must_change_password, lock_until FROM admin_users WHERE username = ? LIMIT 1");
    $stmt->execute([$adminUsername]);
    $uRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$uRow) {
        // Account has been removed from database; terminate session immediately
        session_unset();
        session_destroy();
        header("Location: login.php?error=account_removed");
        exit;
    }

    // Terminate session if account is locked
    if (!empty($uRow['lock_until']) && strtotime($uRow['lock_until']) > time()) {
        session_unset();
        session_destroy();
        header("Location: login.php?error=account_locked");
        exit;
    }

    $userRoles = [$uRow['role']];
    if (!empty($uRow['roles_json'])) {
        $decoded = json_decode($uRow['roles_json'], true);
        if (is_array($decoded)) {
            $userRoles = array_unique(array_merge($userRoles, $decoded));
        }
    }
    $_SESSION['admin_id']    = $uRow['id'];
    $_SESSION['admin_role']  = $uRow['role'];
    $_SESSION['admin_roles'] = $userRoles;

    if ($uRow['must_change_password'] == 1) {
        $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
        if ($currentScript !== 'change_password.php' && $currentScript !== 'logout.php') {
            header("Location: change_password.php?expired=1");
            exit;
        }
    }
} catch (Exception $e) {}
