<?php
require 'auth.php';
require '../config/db.php';
require '../includes/mailer.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request");
}

$id = (int) $_GET['id'];

/* Fetch applicant */
$stmt = $pdo->prepare("SELECT * FROM s1_applications WHERE id = ?");
$stmt->execute([$id]);
$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    die("Applicant not found");
}

/* Update status to rejected */
$update = $pdo->prepare(
    "UPDATE s1_applications 
     SET application_status = 'rejected' 
     WHERE id = ?"
);
$update->execute([$id]);

// Log activity
logActivity($pdo, $_SESSION['admin_username'], "Reject Student (S1)", "Rejected applicant: " . $applicant['firstname'] . " " . $applicant['surname'] . " (Index: " . $applicant['index_number'] . ")");

/* Rejection email */
$message = "
<h3>Admission Update</h3>

<p>Dear Parent/Guardian,</p>

<p>Thank you for applying to <strong>St.Paul's Mutolere SS</strong>.</p>

<p>After careful consideration, we regret to inform you that the applicant
<strong>{$applicant['firstname']} {$applicant['surname']}</strong>
has <strong>not been admitted</strong> to Senior One at this time.</p>

<p>We appreciate your interest in our school.</p>

<p><em>Admissions Office</em></p>
";

/* Send email */
sendEmail(
    $applicant['parent_email'],
    "Senior One Admission Update",
    $message
);

/* Redirect back to dashboard */
header("Location: dashboard.php");
exit;
