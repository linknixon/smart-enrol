<?php
require 'auth.php';
require '../config/db.php';
require '../includes/mailer.php';

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request");
}

$id = (int)$_GET['id'];

// Fetch applicant
$stmt = $pdo->prepare("SELECT * FROM s5_applications WHERE id = ?");
$stmt->execute([$id]);
$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    die("Applicant not found");
}

// Update status to rejected
$update = $pdo->prepare("UPDATE s5_applications SET application_status = 'rejected' WHERE id = ?");
$update->execute([$id]);

// Log activity
logActivity($pdo, $_SESSION['admin_username'], "Reject Student (S5)", "Rejected applicant: " . $applicant['firstname'] . " " . $applicant['surname'] . " (Index: " . $applicant['uce_index'] . ")");

// Prepare rejection email
$subject = "Rejected: Senior Five (A-Level) Admission Update 2026";

$message = "
<p>Dear Parent/Guardian of {$applicant['firstname']} {$applicant['surname']},</p>

<p>Thank you for applying to St. Paul's Mutolere Secondary School.</p>

<p>After careful consideration, we regret to inform you that the application has <strong>not been successful</strong> for Senior Five (A-Level) 2026.</p>

<p>We appreciate your interest and encourage you to apply in the future.</p>

<p>Regards,<br>
Admissions Office<br>
St. Paul's Mutolere Secondary School</p>
";

// Send email
sendEmail($applicant['parent_email'], $subject, $message);

// Redirect to dashboard
header("Location: dashboard.php?msg=rejected");
exit;
