<?php
require 'auth.php';
require '../config/db.php';

$currentRole = $_SESSION['admin_role'] ?? 'viewer';
if (!hasPermission($pdo, $currentRole, 'can_take_application_actions') && !hasPermission($pdo, $currentRole, 'can_admit_students') && $currentRole !== 'super_admin') {
    die("Access Denied: You do not possess the required security permissions to review application dossiers.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request parameters.");
}

$id = (int)$_GET['id'];

$fetch = $pdo->prepare("SELECT firstname, surname, class_of_entry, application_status, is_auto_admitted FROM applications WHERE id = ?");
$fetch->execute([$id]);
$applicant = $fetch->fetch(PDO::FETCH_ASSOC);

if ($applicant) {
    $reviewer = $_SESSION['admin_username'] ?? 'Administrator';
    $stmt = $pdo->prepare("
        UPDATE applications 
        SET auto_admit_review_status = 'reviewed',
            reviewed_by = ?,
            reviewed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$reviewer, $id]);

    logActivity($pdo, $reviewer, "Auto-Admit Review Verified", "Reviewed and confirmed auto-admitted dossier for: " . $applicant['firstname'] . " " . $applicant['surname'] . " (" . $applicant['class_of_entry'] . ")");
}

$redirect = !empty($_GET['from']) && $_GET['from'] === 'details' ? "details.php?id={$id}&msg=auto_admit_reviewed" : "dashboard.php?msg=auto_admit_reviewed";
header("Location: " . $redirect);
exit;
