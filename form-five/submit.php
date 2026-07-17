<?php

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require './config/db.php';
require './includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

// -----------------------------
// VALIDATE AGGREGATES
// -----------------------------
$aggregates = isset($_POST['aggregates']) ? (int)$_POST['aggregates'] : 0;
if ($aggregates < 8 || $aggregates > 54) {
    die("Aggregates must be between 8 and 54");
}

// -----------------------------
// ENSURE UPLOAD DIRECTORIES EXIST
// -----------------------------
$passportDir = './uploads/passports';
$resultDir   = './uploads/results';

@mkdir($passportDir, 0777, true);
@mkdir($resultDir, 0777, true);

// -----------------------------
// HANDLE FILE UPLOADS
// -----------------------------
if (!isset($_FILES['passport']['tmp_name'], $_FILES['result_slip']['tmp_name'])) {
    die("Both passport and result slip must be uploaded");
}

$passportPath = $passportDir . '/' . time() . '_' . basename($_FILES['passport']['name']);
$resultPath   = $resultDir   . '/' . time() . '_' . basename($_FILES['result_slip']['name']);

if (!move_uploaded_file($_FILES['passport']['tmp_name'], $passportPath)) {
    die("Failed to upload passport photo");
}
if (!move_uploaded_file($_FILES['result_slip']['tmp_name'], $resultPath)) {
    die("Failed to upload UCE result slip");
}

// -----------------------------
// CALCULATE AGE
// -----------------------------
$age = date_diff(date_create($_POST['dob']), date_create('today'))->y;

// -----------------------------
// INSERT INTO DATABASE
// -----------------------------
$stmt = $pdo->prepare("
INSERT INTO s5_applications (
    surname, firstname, othernames, sex, dob, age, passport_photo,
    former_school, uce_index, aggregates, division, combination, result_slip,
    parent_name, parent_nin, parent_phone, parent_address, parent_email
) VALUES (
    ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
)
");

$stmt->execute([
    $_POST['surname'],
    $_POST['firstname'],
    $_POST['othernames'],
    $_POST['sex'],
    $_POST['dob'],
    $age,
    $passportPath,

    $_POST['former_school'],
    $_POST['uce_index'],
    $aggregates,
    $_POST['division'],
    $_POST['combination'],
    $resultPath,

    $_POST['parent_name'],
    $_POST['parent_nin'],
    $_POST['parent_phone'],
    $_POST['parent_address'],
    $_POST['parent_email']
]);

// -----------------------------
// EMAIL TO PARENT (Elegant HTML Template)
$studentName = htmlspecialchars($_POST['firstname'] . ' ' . $_POST['surname']);
$classEntry = "Senior Five (S5) - Combination: " . htmlspecialchars($_POST['combination']);
$indexNumber = htmlspecialchars($_POST['uce_index']);
$parentName = htmlspecialchars($_POST['parent_name']);
$submissionDate = date('d-M-Y h:i A');

$parentMessage = "
<div style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px; font-size: 14px; line-height: 1.6; color: #1e293b;'>
    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
        <!-- Header -->
        <div style='background-color: #0c4a24; padding: 25px; text-align: center; border-bottom: 3px solid #b58d2f;'>
            <img src='https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png' alt='Logo' style='width: 70px; height: 70px; margin-bottom: 10px; background-color: white; border-radius: 50%; padding: 4px;'>
            <h2 style='color: #ffffff; margin: 0; font-size: 18px; font-weight: 700; letter-spacing: 0.5px;'>ST. PAUL'S MUTOLERE SECONDARY SCHOOL</h2>
            <p style='color: #d4af37; margin: 5px 0 0 0; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;'>Excellence Through Discipline, Faith & Knowledge</p>
        </div>
        <!-- Body -->
        <div style='padding: 30px;'>
            <p style='margin-top: 0;'>Dear Parent/Guardian,</p>
            <p>Thank you for submitting your application to St. Paul's Mutolere Secondary School. We are pleased to confirm that your application has been successfully received and is currently under review by our admissions committee.</p>
            
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
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>UCE Index Number:</td>
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
    "Application Received: Senior Five Admission",
    $parentMessage
);

// EMAIL NOTIFICATIONS TO ADMINS (Elegant HTML Template)
$adminSubject = "New S5 Online Application: " . $studentName;
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
            <p style='margin-top: 0; font-weight: bold; color: #0c4a24; font-size: 15px;'>Alert: New Senior Five Application Submitted</p>
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
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>UCE Index Number:</td>
                        <td style='padding: 6px 0; color: #1e293b; font-family: monospace;'>{$indexNumber}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; color: #64748b; font-weight: bold; vertical-align: top;'>UCE Aggregates:</td>
                        <td style='padding: 6px 0; color: #1e293b; font-weight: bold;'>{$aggregates} (Division: {$_POST['division']})</td>
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
                <a href='https://apply.mutolere.sc.ug/form-five/admin/dashboard.php' style='background-color: #0c4a24; color: #ffffff; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 6px rgba(12,74,36,0.15);'>
                    Access S5 Admin Dashboard
                </a>
            </div>
            
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
            <p style='margin: 0; font-size: 12px; color: #64748b; text-align: center;'>Admissions Portal System Notification</p>
        </div>
    </div>
</div>
";

$adminEmails = ['info@ncedges.com','cmbonye65@gmail.com','mutoleresp@gmail.com','musareona146@gmail.com'];
foreach ($adminEmails as $admin) {
    sendEmail(
        $admin,
        $adminSubject,
        $adminMessage
    );
}

// -----------------------------
// REDIRECT TO SUCCESS PAGE
// -----------------------------
header("Location: success.php");
exit;
