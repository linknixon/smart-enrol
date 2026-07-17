<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

// Intercept logins to enforce password expiry
require_once __DIR__ . '/../config/db.php';
$stmt = $pdo->prepare("SELECT must_change_password FROM admin_users WHERE username = ?");
$stmt->execute([$_SESSION['admin_username']]);
$mustChange = $stmt->fetchColumn();

if ($mustChange == 1) {
    $currentScript = basename($_SERVER['PHP_SELF']);
    if ($currentScript !== 'change_password.php' && $currentScript !== 'logout.php') {
        header("Location: change_password.php?expired=1");
        exit;
    }
}
