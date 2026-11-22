<?php
require 'auth.php';
require '../config/db.php';
require '../form-one/includes/mailer.php';

$currentRole = $_SESSION['admin_role'] ?? 'guest';
if (!hasPermission($pdo, $currentRole, 'can_reject_students')) {
    die("Permission denied: You do not have privilege to reject admission applications.");
}

// Fetch dynamic configurations
$school_name       = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_website    = getSetting($pdo, 'school_website', 'www.mutolere.sc.ug');

$logo_source = getSchoolLogoSrc($pdo);
if (strpos($logo_source, 'http') === false) {
    $logo_source = getBaseUrl($pdo) . '/' . ltrim($logo_source, '/');
}

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid applicant ID request");
}

/* Fetch applicant */
$stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
$stmt->execute([$id]);
$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    die("Applicant record not found in system");
}

/* Rejection reason */
$rejection_reason = trim($_POST['rejection_reason'] ?? $_GET['reason'] ?? '');
if (empty($rejection_reason)) {
    $rejection_reason = "Did not meet the institutional admission cutoff pass mark or class capacity requirements.";
}

/* Update status to rejected and persist reason */
$update = $pdo->prepare("
    UPDATE applications 
    SET application_status = 'rejected', rejection_reason = ? 
    WHERE id = ?
");
$update->execute([$rejection_reason, $id]);

// Log activity
logActivity(
    $pdo, 
    $_SESSION['admin_username'], 
    "Reject Student", 
    "Rejected applicant: " . $applicant['firstname'] . " " . $applicant['surname'] . " (" . $applicant['class_of_entry'] . "). Reason: " . $rejection_reason
);

/* Build Rejection Email */
$className = "Senior " . substr($applicant['class_of_entry'], 1);
$studentName = htmlspecialchars(trim($applicant['firstname'] . ' ' . $applicant['surname'] . ' ' . ($applicant['othernames'] ?? '')));
$parentName = htmlspecialchars($applicant['parent_name'] ?? 'Parent / Guardian');
$primary_color = getPrimaryColor($pdo);
$accent_color  = getAccentColor($pdo);
$safeReason = htmlspecialchars($rejection_reason);

$message = "
<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<link rel='preconnect' href='https://fonts.googleapis.com'>
<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
<link href='https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap' rel='stylesheet'>
<style>
body, div, p, span, h1, h2, h3, h4, th, td, a {
    font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
}
</style>
</head>
<body style='margin: 0; padding: 0; background-color: #f8fafc;'>
<div style=\"font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 30px 15px; font-size: 14px; line-height: 1.6; color: #1e293b;\">
    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;'>
        <!-- Header -->
        <div style='background-color: {$primary_color}; padding: 25px; text-align: center; border-bottom: 3px solid {$accent_color};'>
            <img src='cid:school_logo' alt='School Logo' style='max-height: 65px; max-width: 140px; margin-bottom: 10px; object-fit: contain;' onerror=\"this.onerror=null;this.src='{$logo_source}';\">
            <h2 style='color: #ffffff; margin: 0; font-size: 18px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;'>{$school_name}</h2>
            <p style='color: {$accent_color}; margin: 4px 0 0 0; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;'>Office of Admissions Registry</p>
        </div>
        
        <!-- Body -->
        <div style='padding: 30px;'>
            <div style='display: inline-block; padding: 4px 12px; background-color: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 700; text-transform: uppercase; border-radius: 9999px; letter-spacing: 0.5px; margin-bottom: 12px;'>
                Admission Decision Notice
            </div>
            
            <h3 style='color: #0f172a; margin-top: 0; font-size: 18px; font-weight: 800;'>Provisional Admission Update</h3>
            <p>Dear {$parentName},</p>
            <p>Thank you for submitting an application on behalf of <strong>{$studentName}</strong> for admission to <strong>{$className}</strong> at <strong>{$school_name}</strong>.</p>
            <p>Following thorough consideration by the Admissions Committee, we regret to inform you that this application was <strong>not successful</strong> for the upcoming academic intake.</p>
            
            <!-- Reason for Rejection Card -->
            <div style='background-color: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #dc2626; border-radius: 8px; padding: 15px 18px; margin: 22px 0;'>
                <span style='display: block; font-size: 11px; font-weight: 800; color: #991b1b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;'>Reason for Decision:</span>
                <span style='font-size: 13.5px; color: #1e293b; line-height: 1.5; font-weight: 500;'>{$safeReason}</span>
            </div>

            <p style='color: #475569; font-size: 13px;'>We acknowledge the time and effort invested in this application. Due to strict enrollment capacity ceilings and merit cut-off requirements, we are unable to accommodate all qualified candidates.</p>
            <p style='color: #475569; font-size: 13px;'>We wish {$studentName} every success in their continued academic journey and future pursuits.</p>
            
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
            <p style='margin-bottom: 2px; font-size: 13px; color: #64748b;'>Yours faithfully,</p>
            <p style='margin-top: 4px; font-weight: 700; color: {$primary_color}; font-size: 14px;'>
                Office of Admissions Registry<br>
                <span style='color: #64748b; font-size: 12px; font-weight: normal;'>{$school_name}</span>
            </p>
        </div>
        
        <!-- Footer -->
        <div style='background-color: #f8fafc; padding: 18px; text-align: center; border-top: 1px solid #e2e8f0;'>
            <p style='margin: 0; font-size: 11px; color: #94a3b8;'>This is an official automated notification from {$school_short_name} Online Admissions Portal.</p>
            <p style='margin: 4px 0 0 0; font-size: 11px; color: #94a3b8;'>&copy; " . date('Y') . " {$school_name}. All rights reserved.</p>
        </div>
    </div>
</div>
</body>
</html>
";

/* Send email to the applicant / parent contact */
if (!empty($applicant['parent_email'])) {
    sendEmail(
        $applicant['parent_email'],
        "{$className} Admission Decision - {$school_short_name}",
        $message
    );
}

/* Redirect back to dashboard */
header("Location: dashboard.php?success=" . urlencode("Application for {$studentName} marked as rejected and notification email sent."));
exit;
