<?php
require 'auth.php';
require '../config/db.php';
require '../includes/mailer.php';

// Fetch dynamic configurations
$school_name    = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');

$logo_source = getSchoolLogoSrc($pdo);
if (strpos($logo_source, 'http') === false) {
    $logo_source = getBaseUrl($pdo) . '/' . ltrim($logo_source, '/');
}

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

$studentName = htmlspecialchars($applicant['firstname'] . ' ' . $applicant['surname']);
$parentName  = htmlspecialchars($applicant['parent_name']);

/* Rejection email */
$message = "
<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<style>
@media only screen and (max-width: 620px) {
    .outer-wrapper { padding: 8px 4px !important; }
    .email-container { width: 100% !important; max-width: 100% !important; border-radius: 8px !important; margin: 0 auto !important; }
    .email-body { padding: 18px 12px !important; font-size: 14.5px !important; line-height: 1.6 !important; }
    .email-header { padding: 22px 14px !important; }
    .email-header h2 { font-size: 17px !important; line-height: 1.3 !important; }
    .email-footer { padding: 16px 10px !important; }
}
</style>
</head>
<body style='margin: 0; padding: 0; background-color: #f8fafc;'>
<div class='outer-wrapper' style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 20px 8px; font-size: 14.5px; line-height: 1.6; color: #1e293b;\">
    <div class='email-container' style='max-width: 620px; width: 100%; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;'>
        <!-- Header -->
        <div class='email-header' style='background-color: #0c4a24; padding: 26px 20px; text-align: center; border-bottom: 3px solid #b58d2f;'>
            <table role='presentation' border='0' cellpadding='0' cellspacing='0' align='center' style='margin: 0 auto 12px auto; text-align: center; border-collapse: collapse;'>
                <tr>
                    <td align='center' style='text-align: center; background: transparent; background-color: transparent;'>
                        <img src='{$logo_source}' alt='School Logo' style='display: block; margin: 0 auto; max-height: 70px; max-width: 150px; height: auto; width: auto; background-color: transparent; background: transparent; border: 0; outline: none; object-fit: contain;'>
                    </td>
                </tr>
            </table>
            <h2 style='color: #ffffff; margin: 0; font-size: 19px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;'>{$school_name}</h2>
            <p style='color: #d4af37; margin: 5px 0 0 0; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;'>Admissions Office</p>
        </div>
        <!-- Body -->
        <div class='email-body' style='padding: 26px 20px;'>
            <h3 style='color: #dc2626; margin-top: 0; font-weight: bold; font-size: 18px;'>Senior One Admission Update</h3>
            <p>Dear Parent / Guardian ($parentName),</p>
            <p>Thank you for submitting $studentName's application to <strong>{$school_name}</strong>.</p>
            <p>After careful evaluation of the application, we regret to inform you that your child has <strong>not been admitted</strong> to Senior One for the upcoming academic year. This decision is final due to limited capacity and merit cut-off limits.</p>
            <p>We appreciate your interest in our institution and wish $studentName the absolute best in their future academic endeavors.</p>
            
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
            <p style='margin-bottom: 0;'>Yours faithfully,</p>
            <p style='margin-top: 5px; font-weight: bold; color: #0c4a24;'>Office of Admissions<br><span style='color: #64748b; font-size: 12px; font-weight: normal;'>{$school_name}</span></p>
        </div>
        <!-- Footer -->
        <div class='email-footer' style='background-color: #f1f5f9; padding: 18px; text-align: center; border-top: 1px solid #e2e8f0;'>
            <p style='margin: 0; font-size: 11px; color: #94a3b8;'>This is an automated notification. Please do not reply directly to this email.</p>
            <p style='margin: 5px 0 0 0; font-size: 11px; color: #94a3b8;'>&copy; " . date('Y') . " {$school_name}. All rights reserved.</p>
        </div>
    </div>
</div>
</body>
</html>
";

/* Send email */
sendEmail(
    $applicant['parent_email'],
    "Senior One Admission Update - {$school_name}",
    $message
);

/* Redirect back to dashboard */
header("Location: dashboard.php");
exit;
