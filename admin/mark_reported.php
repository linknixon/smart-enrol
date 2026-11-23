<?php
require_once __DIR__ . '/auth.php';

$currentRole = $_SESSION['admin_role'] ?? '';
if (!hasPermission($pdo, $currentRole, 'can_admit_students') && !hasPermission($pdo, $currentRole, 'can_manage_settings') && $currentRole !== 'super_admin') {
    http_response_code(403);
    die("Unauthorized action.");
}

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0);

if ($id <= 0) {
    header("Location: dashboard.php?error=" . urlencode("Invalid application ID."));
    exit;
}

// Fetch applicant
$stmt = $pdo->prepare("SELECT id, firstname, surname, class_of_entry, application_status FROM applications WHERE id = ?");
$stmt->execute([$id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    header("Location: dashboard.php?error=" . urlencode("Student application not found."));
    exit;
}

$adminUser = $_SESSION['admin_username'] ?? 'Admissions Office';

// Mark application as reported / fully admitted (archived from active queue)
$update = $pdo->prepare("UPDATE applications SET application_status = 'reported', reported_at = NOW(), reported_by = ? WHERE id = ?");
$update->execute([$adminUser, $id]);

$studentName = trim($app['firstname'] . ' ' . $app['surname']);
logActivity($pdo, $adminUser, "Confirm Physical Reporting", "Verified physical arrival and marked {$studentName} (#{$id}) as Fully Admitted & Enrolled");

header("Location: dashboard.php?msg=" . urlencode("student_reported"));
exit;
