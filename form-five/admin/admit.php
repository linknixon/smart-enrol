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

// Update status to admitted
$update = $pdo->prepare("UPDATE s5_applications SET application_status = 'admitted' WHERE id = ?");
$update->execute([$id]);

// Log activity
logActivity($pdo, $_SESSION['admin_username'], "Admit Student (S5)", "Admitted applicant: " . $applicant['firstname'] . " " . $applicant['surname'] . " (Index: " . $applicant['uce_index'] . ")");

// Prepare admission email
$subject = "Senior Five (A-Level) Admission Offer 2026";
$pdfLink = "https://apply.mutolere.sc.ug/form-five/admin/s5_admission_offer.php?id={$id}";
$listLink = "https://apply.mutolere.sc.ug/form-five/admin/s5_admission_list.php";

$message = "
<p>Dear Parent/Guardian of <strong>{$applicant['firstname']} {$applicant['surname']}</strong>,</p>

<p>We are pleased to inform you that your child has been <strong>ADMITTED</strong> to Senior Five (A-Level) at <strong> St. Paul's Mutolere Secondary School</strong> for the 2026 academic year.</p>

<p>Approved Subject Combination: <strong>{$applicant['combination']}</strong></p>

<p style='text-align:center; margin:20px 0;'>
    <a href='{$pdfLink}' style='background:#198754;color:#fff;padding:12px 18px;
       text-decoration:none;border-radius:6px;display:inline-block;font-weight:bold;'>
        Download Admission Offer Letter (PDF)
    </a>
</p>

<p style='text-align:center; margin:20px 0;'>
    <a href='{$listLink}' style='background:#007bff;color:#fff;padding:10px 15px;
       text-decoration:none;border-radius:6px;display:inline-block;font-weight:bold;'>
        View Admission List
    </a>
</p>

<p>Students should report to school with:</p>
<ul>
    <li>Printed admission letter</li>
    <li>Parent / Guardian National ID</li>
    <li>Original UCE result slip</li>
</ul>

<p>Regards,<br>
<strong>Admissions Office</strong><br>
St. Paul's Mutolere School</p>
";

// Send email
sendEmail($applicant['parent_email'], $subject, $message);

// Redirect to dashboard
header("Location: dashboard.php?msg=admitted");
exit;
