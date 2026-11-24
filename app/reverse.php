<?php
require 'auth.php';
require '../config/db.php';

if($_SESSION['admin_role'] !== 'super_admin'){
    die("Access denied.");
}

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    die("Invalid request");
}

$id = (int)$_GET['id'];

$fetch = $pdo->prepare("SELECT firstname, surname, class_of_entry, application_status FROM applications WHERE id = ?");
$fetch->execute([$id]);
$applicant = $fetch->fetch(PDO::FETCH_ASSOC);

if ($applicant) {
    if (strtolower($applicant['application_status'] ?? '') === 'reported') {
        die("Security Restriction: Physically reported and enrolled admissions cannot be reversed.");
    }

    $stmt = $pdo->prepare("
        UPDATE applications 
        SET application_status = 'pending'
        WHERE id = ?
    ");
    $stmt->execute([$id]);

    // Log activity
    logActivity($pdo, $_SESSION['admin_username'], "Reverse Status", "Reversed status to pending for: " . $applicant['firstname'] . " " . $applicant['surname'] . " (" . $applicant['class_of_entry'] . ")");
}

header("Location: dashboard.php?msg=reversed");
exit;
