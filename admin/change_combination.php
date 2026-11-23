<?php
require_once __DIR__ . '/auth.php';

$currentRole = $_SESSION['admin_role'] ?? '';
if (!hasPermission($pdo, $currentRole, 'can_admit_students') && !hasPermission($pdo, $currentRole, 'can_manage_settings') && $currentRole !== 'super_admin') {
    http_response_code(403);
    die("Unauthorized action.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;
$combination = trim($_POST['combination'] ?? '');

if ($id <= 0 || empty($combination)) {
    header("Location: dashboard.php?error=" . urlencode("Invalid student ID or combination."));
    exit;
}

// Fetch applicant
$stmt = $pdo->prepare("SELECT id, firstname, surname, class_of_entry, combination FROM applications WHERE id = ?");
$stmt->execute([$id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    header("Location: dashboard.php?error=" . urlencode("Student application not found."));
    exit;
}

$oldComb = $app['combination'] ?? 'None';
$updateStmt = $pdo->prepare("UPDATE applications SET combination = ? WHERE id = ?");
$updateStmt->execute([$combination, $id]);

$studentName = trim($app['firstname'] . ' ' . $app['surname']);
logActivity($pdo, $_SESSION['admin_username'], "Change Combination", "Updated A-Level combination for {$studentName} (#{$id}) from '{$oldComb}' to '{$combination}'");

header("Location: dashboard.php?msg=" . urlencode("combination_updated"));
exit;
