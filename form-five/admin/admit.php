<?php
require 'auth.php';
require '../config/db.php';
require '../includes/mailer.php';

// Fetch dynamic configurations
$school_name    = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_website = getSetting($pdo, 'school_website', 'www.mutolere.sc.ug');
$academic_year  = getAcademicYear($pdo, 'S5');

$logo_source = getSchoolLogoSrc($pdo);
if (strpos($logo_source, 'http') === false) {
    $logo_source = getBaseUrl($pdo) . '/' . ltrim($logo_source, '/');
}

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

// Resolve admitting admin name
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

// Update status to admitted
$update = $pdo->prepare("UPDATE s5_applications SET application_status = 'admitted', admitted_by = ?, admitted_at = NOW() WHERE id = ?");
$update->execute([$adminFullName, $id]);

// Log activity
logActivity($pdo, $_SESSION['admin_username'], "Admit Student (S5)", "Admitted applicant: " . $applicant['firstname'] . " " . $applicant['surname'] . " (Index: " . $applicant['uce_index'] . ")");

/* ================= LINKS ================= */
$pdfLink = getBaseUrl($pdo) . "/form-five/admin/s5_admission_offer.php?id=" . $id;
$listLink = getBaseUrl($pdo) . "/form-five/admin/s5_admission_list.php";

/* ================= EMAIL MESSAGE ================= */
$studentName = htmlspecialchars($applicant['firstname'] . ' ' . $applicant['surname']);
$parentName  = htmlspecialchars($applicant['parent_name']);
$combination = htmlspecialchars($applicant['combination']);

$message = "
<div style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px; font-size: 14px; line-height: 1.6; color: #1e293b;'>
    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
        <!-- Header -->
        <div style='background-color: #0c4a24; padding: 25px; text-align: center; border-bottom: 3px solid #b58d2f;'>
            <img src='{$logo_source}' alt='Logo' style='width: 70px; height: 70px; margin-bottom: 10px; background-color: white; border-radius: 50%; padding: 4px; object-fit: contain;'>
            <h2 style='color: #ffffff; margin: 0; font-size: 18px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;'>{$school_name}</h2>
            <p style='color: #d4af37; margin: 5px 0 0 0; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;'>Admissions Office</p>
        </div>
        <!-- Body -->
        <div style='padding: 30px;'>
            <h3 style='color: #0c4a24; margin-top: 0; font-weight: bold;'>Senior Five (A-Level) Official Admission Offer</h3>
            <p>Dear Parent / Guardian ($parentName),</p>
            <p>We are pleased to inform you that your child <strong>$studentName</strong> has been <strong>SUCCESSFULLY ADMITTED</strong> to <strong>Senior Five (S5)</strong> at <strong>{$school_name}</strong> for the <strong>{$academic_year} Academic Year</strong>.</p>
            
            <div style='background-color: #f1f5f9; padding: 12px 18px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #b58d2f;'>
                <span style='font-size: 13px; color: #334155; font-weight: bold;'>Approved Subject Combination:</span>
                <span style='font-size: 15px; color: #0c4a24; font-weight: 800; margin-left: 5px;'>{$combination}</span>
            </div>

            <p>Please click the button below to download and print the official Admission Offer Letter (PDF):</p>
            
            <div style='text-align: center; margin: 25px 0;'>
                <a href='{$pdfLink}' style='background-color: #0c4a24; color: #ffffff; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 6px rgba(12,74,36,0.15);'>
                    Download Admission Offer Letter (PDF)
                </a>
            </div>

            <p style='text-align: center; margin: 15px 0;'>
                <a href='{$listLink}' style='color: #b58d2f; font-weight: bold; text-decoration: underline;'>
                    View Senior Five Admitted Students Register
                </a>
            </p>

            <p><strong>Requirements upon reporting:</strong></p>
            <ul style='color: #475569;'>
                <li>Printed Official Admission Offer Letter</li>
                <li>Original UNEB UCE Result Slip / Certificate</li>
                <li>Parent / Guardian National ID copy</li>
                <li>Recent passport size photograph</li>
            </ul>

            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
            <p>We look forward to welcoming $studentName to our A-Level academic community.</p>
            
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

// Send email
sendEmail(
    $applicant['parent_email'],
    "Senior Five (A-Level) Admission Offer - {$academic_year} - {$school_name}",
    $message
);

// Redirect to dashboard
header("Location: dashboard.php?msg=admitted");
exit;
