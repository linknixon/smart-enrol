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

$id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0);
if ($id <= 0) {
    die("Invalid request");
}

/* ================= FETCH APPLICANT ================= */
$stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
$stmt->execute([$id]);
$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    die("Applicant not found");
}

/* ================= MANUAL A-LEVEL COMBINATION ASSIGNMENT ================= */
$assignedCombination = trim($_POST['combination'] ?? $_GET['combination'] ?? '');
$customComb = trim($_POST['custom_combination'] ?? $_GET['custom_combination'] ?? '');
if ($assignedCombination === 'CUSTOM' || $assignedCombination === 'OTHER' || !empty($customComb)) {
    $assignedCombination = strtoupper($customComb);
}

if (!empty($assignedCombination)) {
    $pdo->prepare("UPDATE applications SET combination = ? WHERE id = ?")->execute([$assignedCombination, $id]);
    $applicant['combination'] = $assignedCombination;
}

/* ================= SCHOOLPAY / STUDENT NUMBER ASSIGNMENT ================= */
$isManual = isManualStudentNumberEnabled($pdo);
$inputStudentNumber = trim($_POST['student_number'] ?? $_GET['student_number'] ?? '');

if ($isManual) {
    if (empty($inputStudentNumber) && empty($applicant['student_number'])) {
        die("Error: Manual SchoolPay Payment Code / Student Number is required before admitting candidate. Please return and enter the code.");
    }
    if (!empty($inputStudentNumber)) {
        $cleanStudentNumber = strtoupper(preg_replace('/[^A-Za-z0-9\/\-_]/', '', $inputStudentNumber));
        $pdo->prepare("UPDATE applications SET student_number = ? WHERE id = ?")->execute([$cleanStudentNumber, $id]);
        $applicant['student_number'] = $cleanStudentNumber;
        $student_number = $cleanStudentNumber;
    } else {
        $student_number = $applicant['student_number'];
    }
} else {
    if (!empty($inputStudentNumber)) {
        $cleanStudentNumber = strtoupper(preg_replace('/[^A-Za-z0-9\/\-_]/', '', $inputStudentNumber));
        $pdo->prepare("UPDATE applications SET student_number = ? WHERE id = ?")->execute([$cleanStudentNumber, $id]);
        $applicant['student_number'] = $cleanStudentNumber;
        $student_number = $cleanStudentNumber;
    } elseif (!empty($applicant['student_number'])) {
        $student_number = $applicant['student_number'];
    } else {
        $student_number = getAdmissionNumber($applicant, $pdo);
        try {
            $pdo->prepare("UPDATE applications SET student_number = ? WHERE id = ?")->execute([$student_number, $id]);
            $applicant['student_number'] = $student_number;
        } catch (Exception $e) {}
    }
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
$combLog = !empty($applicant['combination']) ? " - Assigned Combination: " . $applicant['combination'] : "";
$codeLog = !empty($student_number) ? " - Student No / SchoolPay Code: " . $student_number : "";
logActivity($pdo, $_SESSION['admin_username'], "Admit Student", "Admitted applicant: " . $applicant['firstname'] . " " . $applicant['surname'] . " (" . $applicant['class_of_entry'] . "){$combLog}{$codeLog}");

/* ================= PDF & REPORTING DETAILS ================= */
$pdfLink = getBaseUrl($pdo) . "/app/admission_offer.php?id=" . $id;
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

$combinationNotice = "";
if (!empty($applicant['combination']) && in_array($applicant['class_of_entry'], ['S5', 'S6'])) {
    $combinationNotice = "
    <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid {$primary_color}; padding: 12px 15px; border-radius: 6px; margin: 15px 0;'>
        <span style='font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;'>Officially Assigned A-Level Combination:</span><br>
        <strong style='font-size: 15px; color: {$primary_color};'>" . htmlspecialchars($applicant['combination']) . "</strong>
    </div>";
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
@media only screen and (max-width: 620px) {
    .outer-wrapper { padding: 8px 4px !important; }
    .email-container { width: 100% !important; max-width: 100% !important; border-radius: 8px !important; margin: 0 auto !important; }
    .email-body { padding: 18px 12px !important; font-size: 14.5px !important; line-height: 1.6 !important; }
    .email-header { padding: 22px 14px !important; }
    .email-header h2 { font-size: 17px !important; line-height: 1.3 !important; }
    .inner-box { padding: 12px 10px !important; margin: 14px 0 !important; border-radius: 8px !important; }
    .btn-action { display: block !important; width: 100% !important; box-sizing: border-box !important; text-align: center !important; padding: 13px 12px !important; font-size: 14px !important; }
    .email-footer { padding: 16px 10px !important; }
}
</style>
</head>
<body style='margin: 0; padding: 0; background-color: #f8fafc;'>
<div class='outer-wrapper' style=\"font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 20px 8px; font-size: 14.5px; line-height: 1.6; color: #1e293b;\">
    <div class='email-container' style='max-width: 620px; width: 100%; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;'>
        <!-- Header -->
        <div class='email-header' style='background-color: {$primary_color}; padding: 26px 20px; text-align: center; border-bottom: 3px solid {$accent_color};'>
            <table role='presentation' border='0' cellpadding='0' cellspacing='0' align='center' style='margin: 0 auto 12px auto; text-align: center; border-collapse: collapse;'>
                <tr>
                    <td align='center' style='text-align: center; background: transparent; background-color: transparent;'>
                        <img src='cid:school_logo' alt='School Logo' style='display: block; margin: 0 auto; max-height: 70px; max-width: 150px; height: auto; width: auto; background-color: transparent; background: transparent; border: 0; outline: none; object-fit: contain;' onerror=\"this.onerror=null;this.src='{$logo_source}';\">
                    </td>
                </tr>
            </table>
            <h2 style='color: #ffffff; margin: 0; font-size: 19px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;'>{$school_name}</h2>
            <p style='color: {$accent_color}; margin: 4px 0 0 0; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;'>Office of Admissions Registry</p>
        </div>
        <!-- Body -->
        <div class='email-body' style='padding: 26px 20px;'>
            <div style='display: inline-block; padding: 4px 12px; background-color: #ecfdf5; color: #065f46; font-size: 11px; font-weight: 700; text-transform: uppercase; border-radius: 9999px; letter-spacing: 0.5px; margin-bottom: 12px;'>
                Official Admission Offer
            </div>
            <h3 style='color: {$primary_color}; margin-top: 0; font-size: 18px; font-weight: 800;'>Provisional Offer of Admission</h3>
            <p>Dear Parent / Guardian ($parentName),</p>
            <p>We are pleased to inform you that your child <strong>$studentName</strong> has been <strong>SUCCESSFULLY ADMITTED</strong> to <strong>$className</strong> at <strong>{$school_name}</strong> for the <strong>{$academic_year} Academic Year</strong>.</p>
            
            {$reportingNotice}
            {$combinationNotice}

            <!-- Official Payment Code Box -->
            <div class='inner-box' style='background-color: #f0fdf4; border: 2px solid #86efac; border-radius: 12px; padding: 18px 16px; margin: 20px 0; text-align: left;'>
                <div style='font-size: 11px; font-weight: 800; color: #166534; text-transform: uppercase; letter-spacing: 1px;'>
                    <span style='background-color: #dcfce7; padding: 3px 8px; border-radius: 6px;'>Official School Fees Payment Reference</span>
                </div>
                <div style='font-size: 24px; font-weight: 900; font-family: monospace; color: #15803d; letter-spacing: 1px; margin: 8px 0 10px 0;'>
                    {$student_number}
                </div>
                <p style='margin: 0 0 8px 0; font-size: 13.5px; font-weight: 700; color: #0f172a;'>
                    Payment Details:
                </p>
                <p style='margin: 0; font-size: 13px; color: #334155; line-height: 1.6;'>
                    Your payment details have been shared with you via email. Please reference your unique student identification code (<strong>{$student_number}</strong>) when making school fees payments as detailed in your official offer packet.
                </p>
            </div>

            <!-- What to do next instructions -->
            <div class='inner-box' style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid {$primary_color}; padding: 16px 14px; border-radius: 10px; margin: 20px 0;'>
                <h4 style='margin-top: 0; color: {$primary_color}; font-size: 14.5px; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px;'>
                    What to Do Next:
                </h4>
                <ol style='margin: 8px 0 0 0; padding-left: 18px; font-size: 13px; color: #334155; line-height: 1.65;'>
                    <li><strong>Download and Print:</strong> Click the button below to download and print the official Provisional Admission Offer Letter (PDF).</li>
                    <li><strong>Secure Vacancy:</strong> Complete the admission commitment fee or full term fees using the official payment instructions shared on your email.</li>
                    <li><strong>Reporting Day:</strong> Arrive at school on <strong>" . (!empty($reportingDate) ? htmlspecialchars($reportingDate) : "the official reporting date") . "</strong> with this printed offer letter, original UNEB result slips, bank deposit receipt, 2 physical passport-size photographs, and student requirements.</li>
                </ol>
            </div>

            <p style='margin-top: 18px;'>Please click the button below to download and print the official Provisional Admission Offer Letter (PDF):</p>
            
            <div style='text-align: center; margin: 24px 0;'>
                <a href='{$pdfLink}' class='btn-action' style='background-color: {$primary_color}; color: #ffffff; padding: 13px 26px; border-radius: 10px; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 10px rgba(12,74,36,0.2);'>
                    Download Admission Offer Letter (PDF)
                </a>
            </div>

            <p style='font-size: 12.5px; color: #64748b;'>Or copy this link to your web browser:<br>
            <a href='{$pdfLink}' style='color: {$accent_color}; word-break: break-all; font-weight: 600;'>{$pdfLink}</a></p>

            {$enclosuresHtml}

            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;'>
            <p>We look forward to welcoming $studentName to our school community.</p>
            
            <p style='margin-bottom: 2px; font-size: 13px; color: #64748b;'>Yours faithfully,</p>
            <p style='margin-top: 4px; font-weight: 700; color: {$primary_color}; font-size: 14px;'>Office of Admissions Registry<br><span style='color: #64748b; font-size: 12px; font-weight: normal;'>{$school_name}</span></p>
        </div>
        <!-- Footer -->
        <div class='email-footer' style='background-color: #f8fafc; padding: 18px; text-align: center; border-top: 1px solid #e2e8f0;'>
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
$returnUrl = $_POST['return_to'] ?? $_GET['return_to'] ?? 'applications.php?msg=admitted';
if (!str_starts_with($returnUrl, 'applications.php') && !str_starts_with($returnUrl, 'dashboard.php')) {
    $returnUrl = 'applications.php?msg=admitted';
}
header("Location: " . $returnUrl);
exit;
