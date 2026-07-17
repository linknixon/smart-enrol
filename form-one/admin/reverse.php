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

$fetch = $pdo->prepare("SELECT firstname, surname, index_number FROM s1_applications WHERE id = ?");
$fetch->execute([$id]);
$applicant = $fetch->fetch(PDO::FETCH_ASSOC);

if ($applicant) {
    $stmt = $pdo->prepare("
        UPDATE s1_applications 
        SET application_status = 'pending'
        WHERE id = ?
    ");
    $stmt->execute([$id]);

    // Log activity
    logActivity($pdo, $_SESSION['admin_username'], "Reverse Status (S1)", "Reversed S1 status for: " . $applicant['firstname'] . " " . $applicant['surname'] . " (Index: " . $applicant['index_number'] . ")");
}

header("Location: dashboard.php?msg=reversed");
exit;
