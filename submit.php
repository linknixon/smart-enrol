<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require './config/db.php';
require './form-one/includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method");
}

// Ensure uploads folders exist
@mkdir('uploads/passports', 0777, true);
@mkdir('uploads/results', 0777, true);

$class = $_POST['class_of_entry'] ?? '';
if (!in_array($class, ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'])) {
    die("Invalid class of entry");
}

// 1. Process Passport Photo Upload
if (!isset($_FILES['passport']) || $_FILES['passport']['error'] !== UPLOAD_ERR_OK) {
    die("Passport photo is required");
}
$passport = 'uploads/passports/' . time() . '_' . basename($_FILES['passport']['name']);
if (!move_uploaded_file($_FILES['passport']['tmp_name'], $passport)) {
    die("Failed to upload passport photo");
}

// 2. Process Result Slip/Transcript Upload
$resultSlipInputName = '';
if ($class === 'S1') {
    $resultSlipInputName = 'result_slip_s1';
} elseif ($class === 'S5') {
    $resultSlipInputName = 'result_slip_s5';
} else {
    $resultSlipInputName = 'result_slip_transfer';
}

if (!isset($_FILES[$resultSlipInputName]) || $_FILES[$resultSlipInputName]['error'] !== UPLOAD_ERR_OK) {
    die("Academic record attachment (result slip or report card) is required");
}
$resultSlip = 'uploads/results/' . time() . '_' . basename($_FILES[$resultSlipInputName]['name']);
if (!move_uploaded_file($_FILES[$resultSlipInputName]['tmp_name'], $resultSlip)) {
    die("Failed to upload academic record attachment");
}

// Calculate age from Date of Birth
$dob = $_POST['dob'] ?? '';
$age = 0;
if (!empty($dob)) {
    $age = date_diff(date_create($dob), date_create('today'))->y;
}

// Define variables based on class of entry
$formerSchool   = '';
$indexNumber    = null;
$english        = null;
$mathematics    = null;
$sst            = null;
$science        = null;
$aggregates     = null;
$division       = null;
$combination    = null;
$transferReason = null;

if ($class === 'S1') {
    $formerSchool = $_POST['former_school_s1'] ?? '';
    $indexNumber  = $_POST['index_number_s1'] ?? '';
    $english      = $_POST['english'] ?? '';
    $mathematics  = $_POST['mathematics'] ?? '';
    $sst          = $_POST['sst'] ?? '';
    $science      = $_POST['science'] ?? '';
    $aggregates   = isset($_POST['aggregates_s1']) ? (int)$_POST['aggregates_s1'] : 0;
    $division     = ($aggregates <= 12) ? 'Div 1' : 'Div 2';
} elseif ($class === 'S5') {
    $formerSchool = $_POST['former_school_s5'] ?? '';
    $indexNumber  = $_POST['index_number_s5'] ?? '';
    $aggregates   = isset($_POST['aggregates_s5']) ? (int)$_POST['aggregates_s5'] : 0;
    $division     = $_POST['division_s5'] ?? '';
    $combination  = $_POST['combination_s5'] ?? '';
} else {
    // S2, S3, S4, S6
    $formerSchool   = $_POST['former_school_transfer'] ?? '';
    $division       = $_POST['last_class_attended'] ?? ''; // Store last class attended in division column
    $transferReason = $_POST['transfer_reason'] ?? '';
}

// Insert Application Record
$stmt = $pdo->prepare("
INSERT INTO applications (
    surname, firstname, othernames, sex, dob, age, religion, class_of_entry, passport_photo,
    former_school, index_number, english, mathematics, sst, science,
    aggregates, division, combination, transfer_reason, result_slip,
    parent_name, parent_nin, parent_phone, parent_address, parent_email
) VALUES (
    ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
)
");

$stmt->execute([
    $_POST['surname'],
    $_POST['firstname'],
    $_POST['othernames'] ?? '',
    $_POST['sex'],
    $dob,
    $age,
    $_POST['religion'] ?? '',
    $class,
    $passport,
    $formerSchool,
    $indexNumber,
    $english,
    $mathematics,
    $sst,
    $science,
    $aggregates,
    $division,
    $combination,
    $transferReason,
    $resultSlip,
    $_POST['parent_name'],
    $_POST['parent_nin'],
    $_POST['parent_phone'],
    $_POST['parent_address'],
    $_POST['parent_email']
]);

// -----------------------------
// SEND EMAIL TO APPLICANT (Elegant Template)
// -----------------------------
$studentName = htmlspecialchars($_POST['firstname'] . ' ' . $_POST['surname']);
$parentName = htmlspecialchars($_POST['parent_name']);
$submissionDate = date('d-m-Y H:i:s');
$classEntry = "Senior " . substr($class, 1) . " (" . $class . ")";

$parentMessage = "
<div style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px; font-size: 14px; line-height: 1.6; color: #1e293b;'>
    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
        <!-- Header -->
        <div style='background-color: #0c4a24; padding: 25px; text-align: center; border-bottom: 3px solid #b58d2f;'>
            <img src='https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png' alt='Logo' style='width: 70px; height: 70px; margin-bottom: 10px; background-color: white; border-radius: 50%; padding: 4px;'>
            <h2 style='color: #ffffff; margin: 0; font-size: 18px; font-weight: 700; letter-spacing: 0.5px;'>ST. PAUL'S MUTOLERE SECONDARY SCHOOL</h2>
            <p style='color: #d4af37; margin: 5px 0 0 0; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;'>Online Admissions Portal</p>
        </div>
        <!-- Body -->
        <div style='padding: 30px;'>
            <p style='margin-top: 0; font-weight: bold; color: #0c4a24; font-size: 15px;'>Dear Parent / Guardian ($parentName),</p>
            <p>Thank you for submitting your child\'s application to St. Paul\'s Mutolere Secondary School. We are pleased to confirm that the application has been successfully received and is currently under review by our admissions committee.</p>
            
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
                    </tr>";

if ($class === 'S1' || $class === 'S5') {
    $parentMessage .= "
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>Index Number:</td>
                        <td style='padding: 6px 0; color: #1e293b; font-family: monospace;'>{$indexNumber}</td>
                    </tr>";
}

$parentMessage .= "
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>Date Submitted:</td>
                        <td style='padding: 6px 0; color: #1e293b;'>{$submissionDate}</td>
                    </tr>
                </table>
            </div>

            <p>No further action is required from you at this time. Once the admissions committee completes the evaluation, a formal notification letter detailing the final decision will be sent to this email address.</p>
            <p>For any inquiries regarding your application, please reach out to the admissions office at <strong>info@ncedges.com</strong> or call <strong>+256 790 001 631</strong>.</p>
            
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;'>
            
            <p style='margin-bottom: 0;'>Yours faithfully,</p>
            <p style='margin-top: 5px; font-weight: bold; color: #0c4a24;'>Office of Admissions<br><span style='color: #64748b; font-size: 12px; font-weight: normal;'>St. Paul's Mutolere Secondary School</span></p>
        </div>
        <!-- Footer -->
        <div style='background-color: #f1f5f9; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;'>
            <p style='margin: 0; font-size: 11px; color: #94a3b8;'>This is an automated notification. Please do not reply directly to this email.</p>
            <p style='margin: 5px 0 0 0; font-size: 11px; color: #94a3b8;'>&copy; " . date('Y') . " St. Paul's Mutolere Secondary School. All rights reserved.</p>
        </div>
    </div>
</div>
";

sendEmail(
    $_POST['parent_email'],
    "Application Received: St. Paul's Mutolere SS",
    $parentMessage
);

// -----------------------------
// SEND ALERT EMAIL TO ADMINS (Elegant HTML)
// -----------------------------
$adminSubject = "New admissions request: " . $studentName . " (" . $class . ")";
$adminMessage = "
<div style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px; font-size: 14px; line-height: 1.6; color: #1e293b;'>
    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
        <!-- Header -->
        <div style='background-color: #0c4a24; padding: 25px; text-align: center; border-bottom: 3px solid #b58d2f;'>
            <img src='https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png' alt='Logo' style='width: 70px; height: 70px; margin-bottom: 10px; background-color: white; border-radius: 50%; padding: 4px;'>
            <h2 style='color: #ffffff; margin: 0; font-size: 18px; font-weight: 700; letter-spacing: 0.5px;'>ST. PAUL'S MUTOLERE SECONDARY SCHOOL</h2>
            <p style='color: #d4af37; margin: 5px 0 0 0; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;'>Admissions Control Center</p>
        </div>
        <!-- Body -->
        <div style='padding: 30px;'>
            <p style='margin-top: 0; font-weight: bold; color: #0c4a24; font-size: 15px;'>Alert: New Admission Application Submitted</p>
            <p>A new application has been successfully submitted via the online admissions portal. Below is a summary of the applicant\'s details:</p>
            
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
                    </tr>";

if ($class === 'S1' || $class === 'S5') {
    $parentMessage .= "
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>Index Number:</td>
                        <td style='padding: 6px 0; color: #1e293b; font-family: monospace;'>{$indexNumber}</td>
                    </tr>";
}

if ($class === 'S1') {
    $adminMessage .= "
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>PLE Aggregates:</td>
                        <td style='padding: 6px 0; color: #1e293b;'>{$aggregates} ({$division})</td>
                    </tr>";
} elseif ($class === 'S5') {
    $adminMessage .= "
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>UCE Aggregates:</td>
                        <td style='padding: 6px 0; color: #1e293b;'>{$aggregates} ({$division})</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>Combination:</td>
                        <td style='padding: 6px 0; color: #1e293b;'>{$combination}</td>
                    </tr>";
} else {
    $adminMessage .= "
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>Last Class:</td>
                        <td style='padding: 6px 0; color: #1e293b;'>{$division}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>Transfer Reason:</td>
                        <td style='padding: 6px 0; color: #1e293b;'>{$transferReason}</td>
                    </tr>";
}

$adminMessage .= "
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
                <a href='https://apply.mutolere.sc.ug/admin/dashboard.php' style='background-color: #0c4a24; color: #ffffff; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 6px rgba(12,74,36,0.15);'>
                    Access Admissions Admin Panel
                </a>
            </div>
            
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
            <p style='margin: 0; font-size: 12px; color: #64748b; text-align: center;'>Admissions Portal System Notification</p>
        </div>
    </div>
</div>
";

$adminSettingsEmail = getSetting($pdo, 'admin_email', 'info@ncedges.com');
$adminEmails = array_filter(array_map('trim', explode(',', $adminSettingsEmail)));
foreach ($adminEmails as $admin) {
    sendEmail(
        $admin,
        "New Application Alert: {$studentName} ({$class})",
        $adminMessage
    );
}

// Redirect on success
header("Location: success.php?name=" . urlencode($studentName));
exit;
