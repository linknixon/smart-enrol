<?php
require 'auth.php';
require '../config/db.php';
require '../form-one/includes/mailer.php';

$currentRole = $_SESSION['admin_role'] ?? 'guest';
if (!hasPermission($pdo, $currentRole, 'can_admit_students')) {
    die("Permission denied: You do not have privilege to admit applicants.");
}

// Fetch dynamic configurations
$school_name    = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_website = getSetting($pdo, 'school_website', 'www.mutolere.sc.ug');

$logo_source = getSchoolLogoSrc($pdo);
if (strpos($logo_source, 'http') === false) {
    $logo_source = getBaseUrl($pdo) . '/' . ltrim($logo_source, '/');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request");
}

$id = (int)$_GET['id'];

/* ================= FETCH APPLICANT ================= */
$stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
$stmt->execute([$id]);
$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    die("Applicant not found");
}

$academic_year = getAcademicYear($pdo, $applicant['class_of_entry'] ?? 'S1');

/* ================= RESOLVE ADMITTING ADMIN ================= */
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';
$adminFullName = $adminUsername;
try {
    $uStmt = $pdo->prepare("SELECT firstname, lastname FROM admin_users WHERE username = ?");
    $uStmt->execute([$adminUsername]);
    $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
    if ($uRow && (!empty($uRow['firstname']) || !empty($uRow['lastname']))) {
        $adminFullName = trim($uRow['firstname'] . ' ' . $uRow['lastname']);
    }
} catch (Exception $e) {}

/* ================= UPDATE STATUS ================= */
$update = $pdo->prepare("
    UPDATE applications 
    SET application_status = 'admitted', admitted_by = ?, admitted_at = NOW() 
    WHERE id = ?
");
$update->execute([$adminFullName, $id]);

// Log activity
logActivity($pdo, $_SESSION['admin_username'], "Admit Student", "Admitted applicant: " . $applicant['firstname'] . " " . $applicant['surname'] . " (" . $applicant['class_of_entry'] . ")");

/* ================= PDF & REPORTING DETAILS ================= */
$pdfLink = getBaseUrl($pdo) . "/admin/admission_offer.php?id=" . $id;
$reportingDate = getReportingDate($pdo, $applicant['class_of_entry'] ?? 'S1');
$primary_color = getPrimaryColor($pdo);
$accent_color  = getAccentColor($pdo);

$baseUrl = getBaseUrl($pdo);
$docRules    = getSetting($pdo, 'doc_school_rules', '');
$docCircular = getSetting($pdo, 'doc_admission_circular', '');
$docMedForm  = getSetting($pdo, 'doc_medical_form', '');

/* ================= EMAIL MESSAGE ================= */
$className = "Senior " . substr($applicant['class_of_entry'], 1);
$parentName = htmlspecialchars($applicant['parent_name']);
$studentName = htmlspecialchars($applicant['firstname'] . ' ' . $applicant['surname']);

$enclosuresHtml = '';
$hasEnclosures = (!empty($docRules) || !empty($docCircular) || !empty($docMedForm));
if ($hasEnclosures) {
    $enclosuresHtml .= "
    <div style='background-color: #f1f5f9; padding: 15px; border-radius: 8px; margin: 20px 0;'>
        <h4 style='margin-top: 0; color: {$primary_color}; font-size: 13px; font-weight: bold;'>Official School Enclosures & Attachments:</h4>
        <ul style='margin: 5px 0 0 0; padding-left: 20px; font-size: 13px; color: #334155;'>";
    if (!empty($docRules)) {
        $enclosuresHtml .= "<li><a href='{$baseUrl}/{$docRules}' target='_blank' style='color: {$primary_color}; font-weight: bold;'>Download School Rules & Regulations (PDF)</a></li>";
    }
    if (!empty($docCircular)) {
        $enclosuresHtml .= "<li><a href='{$baseUrl}/{$docCircular}' target='_blank' style='color: {$primary_color}; font-weight: bold;'>Download Term I Admissions Circular (PDF)</a></li>";
    }
    if (!empty($docMedForm)) {
        $enclosuresHtml .= "<li><a href='{$baseUrl}/{$docMedForm}' target='_blank' style='color: {$primary_color}; font-weight: bold;'>Download Student Medical Examination Form (PDF)</a></li>";
    }
    $enclosuresHtml .= "
        </ul>
    </div>";
}

$reportingNotice = "";
if (!empty($reportingDate)) {
    $reportingNotice = "<p style='background-color: #ecfdf5; border-left: 4px solid {$primary_color}; padding: 10px 15px; border-radius: 6px; font-size: 13px;'>
        <strong>Official Reporting Date:</strong> <span style='color: {$primary_color}; font-weight: bold;'>{$reportingDate}</span><br>
        <span style='color: #b45309; font-weight: bold;'>* Please bring a printed copy of your admission letter on reporting date.</span>
    </p>";
}

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
            <div style='display: inline-block; padding: 4px 12px; background-color: #ecfdf5; color: #065f46; font-size: 11px; font-weight: 700; text-transform: uppercase; border-radius: 9999px; letter-spacing: 0.5px; margin-bottom: 12px;'>
                Official Admission Offer
            </div>
            <h3 style='color: {$primary_color}; margin-top: 0; font-size: 18px; font-weight: 800;'>Provisional Offer of Admission</h3>
            <p>Dear Parent / Guardian ($parentName),</p>
            <p>We are pleased to inform you that your child <strong>$studentName</strong> has been <strong>SUCCESSFULLY ADMITTED</strong> to <strong>$className</strong> at <strong>{$school_name}</strong> for the <strong>{$academic_year} Academic Year</strong>.</p>
            
            {$reportingNotice}

            <p>Please click the button below to download and print the official Provisional Admission Offer Letter (PDF):</p>
            
            <div style='text-align: center; margin: 25px 0;'>
                <a href='{$pdfLink}' style='background-color: {$primary_color}; color: #ffffff; padding: 13px 28px; border-radius: 10px; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 10px rgba(12,74,36,0.2);'>
                    Download Admission Offer Letter (PDF)
                </a>
            </div>

            <p style='font-size: 12.5px; color: #64748b;'>Or copy this link to your web browser:<br>
            <a href='{$pdfLink}' style='color: {$accent_color}; word-break: break-all; font-weight: 600;'>{$pdfLink}</a></p>

            {$enclosuresHtml}

            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
            <p>We look forward to welcoming $studentName to our school community.</p>
            
            <p style='margin-bottom: 2px; font-size: 13px; color: #64748b;'>Yours faithfully,</p>
            <p style='margin-top: 4px; font-weight: 700; color: {$primary_color}; font-size: 14px;'>Office of Admissions Registry<br><span style='color: #64748b; font-size: 12px; font-weight: normal;'>{$school_name}</span></p>
        </div>
        <!-- Footer -->
        <div style='background-color: #f8fafc; padding: 18px; text-align: center; border-top: 1px solid #e2e8f0;'>
            <p style='margin: 0; font-size: 11px; color: #94a3b8;'>This is an official automated notification. Please do not reply directly to this email.</p>
            <p style='margin: 4px 0 0 0; font-size: 11px; color: #94a3b8;'>&copy; " . date('Y') . " {$school_name}. All rights reserved.</p>
        </div>
    </div>
</div>
</body>
</html>
";

/* ================= SEND EMAIL ================= */
sendEmail(
    $applicant['parent_email'],
    "$className Admission Offer - {$academic_year} - {$school_name}",
    $message
);

/* ================= REDIRECT ================= */
header("Location: dashboard.php?msg=admitted");
exit;
