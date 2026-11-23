<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require './config/db.php';
require './includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

// Server-side intake deadline enforcement for Senior One
$intakeStatus = getAdmissionStatus($pdo, 'S1');
if (!$intakeStatus['is_open']) {
    if ($intakeStatus['status'] === 'closed') {
        die("Online admissions for Senior One (S1) are currently closed. The application deadline passed on {$intakeStatus['formatted_end']}.");
    } else {
        die("Online admissions for Senior One (S1) have not opened yet. Applications begin on {$intakeStatus['formatted_start']}.");
    }
}

// Enforce School Gender Classification
$schoolGender = getSchoolGenderType($pdo);
$applicantSex = trim($_POST['sex'] ?? '');
if ($schoolGender === 'boys' && $applicantSex !== 'Male') {
    die("Ineligible Application: This institution is classified as a Boys-Only school. Only male candidates are eligible.");
} elseif ($schoolGender === 'girls' && $applicantSex !== 'Female') {
    die("Ineligible Application: This institution is classified as a Girls-Only school. Only female candidates are eligible.");
}

// Passmark Cutoff Enforcement for S1
$aggregates = isset($_POST['aggregates']) ? (int)$_POST['aggregates'] : 0;
$passmarkS1 = getPassMark($pdo, 'S1');
if ($passmarkS1 === null || $passmarkS1 === '') {
    die("Admissions for Senior One (S1) are currently on hold because the cutoff pass mark has not been configured by administration.");
}
if ($aggregates > (int)$passmarkS1) {
    die("Application Ineligible: Your PLE aggregates ({$aggregates}) exceed the school's maximum admission pass mark of {$passmarkS1} aggregates for Senior One.");
}

// Duplicate Applicant Prevention
$indexNumber = trim($_POST['index_number'] ?? '');
if (!empty($indexNumber)) {
    $dupCheck = $pdo->prepare("SELECT COUNT(*) FROM s1_applications WHERE index_number = ?");
    $dupCheck->execute([$indexNumber]);
    if ($dupCheck->fetchColumn() > 0) {
        die("Duplicate Application Detected: An application with UNEB Index Number '" . htmlspecialchars($indexNumber) . "' has already been registered for Senior One.");
    }
}
$parentPhone = trim($_POST['parent_phone'] ?? '');
$dupNameCheck = $pdo->prepare("SELECT COUNT(*) FROM s1_applications WHERE LOWER(TRIM(surname)) = LOWER(TRIM(?)) AND LOWER(TRIM(firstname)) = LOWER(TRIM(?)) AND parent_phone = ?");
$dupNameCheck->execute([$_POST['surname'], $_POST['firstname'], $parentPhone]);
if ($dupNameCheck->fetchColumn() > 0) {
    die("Duplicate Application Detected: An application for student '" . htmlspecialchars($_POST['firstname'] . ' ' . $_POST['surname']) . "' under contact '" . htmlspecialchars($parentPhone) . "' already exists for Senior One.");
}

// Ensure upload folders exist
@mkdir('uploads/passports', 0777, true);
@mkdir('uploads/results', 0777, true);
@mkdir('uploads/parent_ids', 0777, true);

// Handle uploads
$passport = './uploads/passports/' . time() . '_' . basename($_FILES['passport']['name']);
$result   = './uploads/results/' . time() . '_' . basename($_FILES['result_slip']['name']);

if (
    !move_uploaded_file($_FILES['passport']['tmp_name'], $passport) ||
    !move_uploaded_file($_FILES['result_slip']['tmp_name'], $result)
) {
    die("File upload failed");
}

if (function_exists('correctImageExifOrientation')) {
    correctImageExifOrientation($passport);
    correctImageExifOrientation($result);
}

$parentIdFile = null;
if (isset($_FILES['parent_id_file']) && $_FILES['parent_id_file']['error'] === UPLOAD_ERR_OK) {
    $parentIdFile = './uploads/parent_ids/' . time() . '_' . basename($_FILES['parent_id_file']['name']);
    move_uploaded_file($_FILES['parent_id_file']['tmp_name'], $parentIdFile);
    if (function_exists('correctImageExifOrientation')) {
        correctImageExifOrientation($parentIdFile);
    }
}

// Location fields
$parent_village   = trim($_POST['parent_village'] ?? '');
$parent_parish    = trim($_POST['parent_parish'] ?? '');
$parent_subcounty = trim($_POST['parent_subcounty'] ?? '');
$parent_district  = trim($_POST['parent_district'] ?? '');
$parent_address   = trim($_POST['parent_address'] ?? '');
if (empty($parent_address)) {
    $parent_address = implode(', ', array_filter([$parent_village, $parent_parish, $parent_subcounty, $parent_district]));
}

// Calculate age & division
$age = date_diff(date_create($_POST['dob']), date_create('today'))->y;
$division = ($_POST['aggregates'] <= 12) ? 'Div 1' : 'Div 2';

// ✅ CORRECTED INSERT (22 columns, 22 placeholders)
$stmt = $pdo->prepare("
INSERT INTO s1_applications (
    surname, firstname, othernames, sex, dob, age, religion, passport_photo,
    former_school, index_number, english, mathematics, sst, science,
    aggregates, division, result_slip,
    parent_name, parent_nin, parent_phone, parent_address, parent_email
) VALUES (
    ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
)
");

$stmt->execute([
    $_POST['surname'],
    $_POST['firstname'],
    $_POST['othernames'],
    $_POST['sex'],
    $_POST['dob'],
    $age,
    $_POST['religion'],
    $passport,

    $_POST['former_school'],
    $_POST['index_number'],
    $_POST['english'],
    $_POST['mathematics'],
    $_POST['sst'],
    $_POST['science'],

    $_POST['aggregates'],
    $division,
    $result,

    $_POST['parent_name'],
    $_POST['parent_nin'],
    $_POST['parent_phone'],
    $_POST['parent_address'],
    $_POST['parent_email']
]);

   // Dynamic settings
$school_name       = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_motto      = getSetting($pdo, 'school_motto', 'Excellence Through Discipline, Faith & Knowledge');
$school_email      = getSetting($pdo, 'school_email', 'info@ncedges.com');
$school_phone      = getSetting($pdo, 'school_phone', '+256 790 001 631');
$admin_email       = getSetting($pdo, 'admin_email', 'info@ncedges.com');

$logo_source = getSchoolLogoSrc($pdo);
if (strpos($logo_source, 'http') === false) {
    $logo_source = getBaseUrl($pdo) . '/' . ltrim($logo_source, '/');
}
$adminDashboardUrl = getBaseUrl($pdo) . '/form-one/admin/dashboard.php';

   // Email parent (Elegant HTML Template)
$studentName = htmlspecialchars($_POST['firstname'] . ' ' . $_POST['surname']);
$classEntry = "Senior One (S1)";
$indexNumber = htmlspecialchars($_POST['index_number']);
$parentName = htmlspecialchars($_POST['parent_name']);
$submissionDate = date('d-M-Y h:i A');

$parentMessage = "
<div style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px; font-size: 14px; line-height: 1.6; color: #1e293b;'>
    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
        <!-- Header -->
        <div style='background-color: #0c4a24; padding: 25px; text-align: center; border-bottom: 3px solid #b58d2f;'>
            <table role='presentation' border='0' cellpadding='0' cellspacing='0' align='center' style='margin: 0 auto 12px auto; text-align: center; border-collapse: collapse;'>
                <tr>
                    <td align='center' style='text-align: center; background: transparent; background-color: transparent;'>
                        <img src='{$logo_source}' alt='School Logo' style='display: block; margin: 0 auto; max-height: 70px; max-width: 150px; height: auto; width: auto; background-color: transparent; background: transparent; border: 0; outline: none; object-fit: contain;'>
                    </td>
                </tr>
            </table>
            <h2 style='color: #ffffff; margin: 0; font-size: 18px; font-weight: 700; letter-spacing: 0.5px;'>{$school_name}</h2>
            <p style='color: #d4af37; margin: 5px 0 0 0; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;'>{$school_motto}</p>
        </div>
        <!-- Body -->
        <div style='padding: 30px;'>
            <p style='margin-top: 0;'>Dear Parent/Guardian ($parentName),</p>
            <p>Thank you for submitting your application to <strong>{$school_name}</strong>. We are pleased to confirm that your application has been successfully received and is currently under review by our admissions committee.</p>
            
            <div style='background-color: #f8fafc; border-left: 4px solid #0c4a24; padding: 20px; border-radius: 8px; margin: 25px 0;'>
                <h4 style='margin-top: 0; color: #0c4a24; font-size: 14px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; font-weight: 700;'>Application Details Summary</h4>
                <table style='width: 100%; font-size: 13px; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; width: 140px; vertical-align: top;'>Student Name:</td>
                        <td style='padding: 6px 0; color: #1e293b; font-weight: bold;'>{$studentName}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>Class of Entry:</td>
                        <td style='padding: 6px 0; color: #1e293b;'>{$classEntry}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>PLE Index Number:</td>
                        <td style='padding: 6px 0; color: #1e293b; font-family: monospace;'>{$indexNumber}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>Parent/Guardian:</td>
                        <td style='padding: 6px 0; color: #1e293b;'>{$parentName}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>Date Submitted:</td>
                        <td style='padding: 6px 0; color: #1e293b;'>{$submissionDate}</td>
                    </tr>
                </table>
            </div>

            <p>No further action is required from you at this time. Once the admissions committee completes the evaluation, a formal notification letter detailing the final decision will be sent to this email address.</p>
            <p>For any inquiries regarding your application, please reach out to the admissions office at <strong>{$school_email}</strong> or call <strong>{$school_phone}</strong>.</p>
            
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;'>
            
            <p style='margin-bottom: 0;'>Yours faithfully,</p>
            <p style='margin-top: 5px; font-weight: bold; color: #0c4a24;'>Office of Admissions<br><span style='color: #64748b; font-size: 12px; font-weight: normal;'>{$school_name}</span></p>
        </div>
        <!-- Footer -->
        <div style='background-color: #f1f5f9; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;'>
            <p style='margin: 0; font-size: 11px; color: #94a3b8;'>This is an automated notification. Please do not reply directly to this email.</p>
            <p style='margin: 5px 0 0 0; font-size: 11px; color: #94a3b8;'>&copy; " . date('Y') . " {$school_name}. All rights reserved.</p>
        </div>
    </div>
</div>
";

sendEmail(
    $_POST['parent_email'],
    "Application Received: Senior One Admission - {$school_short_name}",
    $parentMessage
);
// Notify admins (Elegant HTML Template)
$adminSubject = "New S1 Online Application: {$studentName} - {$school_short_name}";
$adminMessage = "
<div style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px; font-size: 14px; line-height: 1.6; color: #1e293b;'>
    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
        <!-- Header -->
        <div style='background-color: #0c4a24; padding: 25px; text-align: center; border-bottom: 3px solid #b58d2f;'>
            <table role='presentation' border='0' cellpadding='0' cellspacing='0' align='center' style='margin: 0 auto 12px auto; text-align: center; border-collapse: collapse;'>
                <tr>
                    <td align='center' style='text-align: center; background: transparent; background-color: transparent;'>
                        <img src='{$logo_source}' alt='School Logo' style='display: block; margin: 0 auto; max-height: 70px; max-width: 150px; height: auto; width: auto; background-color: transparent; background: transparent; border: 0; outline: none; object-fit: contain;'>
                    </td>
                </tr>
            </table>
            <h2 style='color: #ffffff; margin: 0; font-size: 18px; font-weight: 700; letter-spacing: 0.5px;'>{$school_name}</h2>
            <p style='color: #d4af37; margin: 5px 0 0 0; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;'>Admissions Control Center</p>
        </div>
        <!-- Body -->
        <div style='padding: 30px;'>
            <p style='margin-top: 0; font-weight: bold; color: #0c4a24; font-size: 15px;'>Alert: New Senior One Application Submitted</p>
            <p>A new application has been successfully submitted via the online admissions portal. Below is a summary of the applicant's details:</p>
            
            <div style='background-color: #f8fafc; border-left: 4px solid #b58d2f; padding: 20px; border-radius: 8px; margin: 25px 0;'>
                <h4 style='margin-top: 0; color: #0c4a24; font-size: 14px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; font-weight: 700;'>Student Credentials</h4>
                <table style='width: 100%; font-size: 13px; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; width: 140px; vertical-align: top;'>Student Name:</td>
                        <td style='padding: 6px 0; color: #1e293b; font-weight: bold;'>{$studentName}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>Gender:</td>
                        <td style='padding: 6px 0; color: #1e293b;'>{$_POST['sex']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>Class of Entry:</td>
                        <td style='padding: 6px 0; color: #1e293b;'>{$classEntry}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>PLE Index Number:</td>
                        <td style='padding: 6px 0; color: #1e293b; font-family: monospace;'>{$indexNumber}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>PLE Aggregates:</td>
                        <td style='padding: 6px 0; color: #1e293b; font-weight: bold;'>{$_POST['aggregates']} ({$division})</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>Parent/Guardian:</td>
                        <td style='padding: 6px 0; color: #1e293b;'>{$parentName} ({$_POST['parent_phone']})</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>Date Submitted:</td>
                        <td style='padding: 6px 0; color: #1e293b;'>{$submissionDate}</td>
                    </tr>
                </table>
            </div>

            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$adminDashboardUrl}' style='background-color: #0c4a24; color: #ffffff; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 6px rgba(12,74,36,0.15);'>
                    Access S1 Admin Dashboard
                </a>
            </div>
            
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
            <p style='margin: 0; font-size: 12px; color: #64748b; text-align: center;'>Admissions Portal System Notification</p>
        </div>
    </div>
</div>
";

$adminEmails = array_filter(array_map('trim', explode(',', $admin_email)));
foreach ($adminEmails as $admin) {
    sendEmail(
        $admin,
        $adminSubject,
        $adminMessage
    );
}

// Redirect
header("Location: success.php");
exit;
