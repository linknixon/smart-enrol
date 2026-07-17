<?php
require 'auth.php';
require '../config/db.php';
require '../form-one/includes/mailer.php';

// Fetch dynamic configurations
$school_name    = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_logo    = getSetting($pdo, 'school_logo', 'https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png');
$school_website = getSetting($pdo, 'school_website', 'www.mutolere.sc.ug');

$logo_source = $school_logo;
if (strpos($logo_source, 'http') === false) {
    if (strpos($school_website, 'http') === false) {
        $school_website = 'http://' . $school_website;
    }
    $logo_source = rtrim($school_website, '/') . '/' . ltrim($logo_source, '/');
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

/* ================= UPDATE STATUS ================= */
$update = $pdo->prepare("
    UPDATE applications 
    SET application_status = 'admitted' 
    WHERE id = ?
");
$update->execute([$id]);

// Log activity
logActivity($pdo, $_SESSION['admin_username'], "Admit Student", "Admitted applicant: " . $applicant['firstname'] . " " . $applicant['surname'] . " (" . $applicant['class_of_entry'] . ")");

/* ================= PDF LINK ================= */
$pdfLink = "https://apply.mutolere.sc.ug/admin/admission_offer.php?id=".$id;

/* ================= EMAIL MESSAGE ================= */
$className = "Senior " . substr($applicant['class_of_entry'], 1);
$parentName = htmlspecialchars($applicant['parent_name']);
$studentName = htmlspecialchars($applicant['firstname'] . ' ' . $applicant['surname']);

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
            <h3 style='color: #0c4a24; margin-top: 0; font-weight: bold;'>Admission Offer Letter</h3>
            <p>Dear Parent / Guardian ($parentName),</p>
            <p>We are pleased to inform you that your child <strong>$studentName</strong> has been <strong>SUCCESSFULLY ADMITTED</strong> to <strong>$className</strong> at {$school_name} for the 2026 Academic Year.</p>
            
            <p>Please click the button below to download and print the official Admission Offer Letter (PDF):</p>
            
            <div style='text-align: center; margin: 25px 0;'>
                <a href='{$pdfLink}' style='background-color: #0c4a24; color: #ffffff; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 6px rgba(12,74,36,0.15);'>
                    Download Admission Offer Letter (PDF)
                </a>
            </div>

            <p>Or copy this link to your web browser:<br>
            <a href='{$pdfLink}' style='color: #b58d2f; word-break: break-all;'>{$pdfLink}</a></p>

            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
            <p>We look forward to welcoming $studentName to our school community.</p>
            
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

/* ================= SEND EMAIL ================= */
sendEmail(
    $applicant['parent_email'],
    "$className Admission Offer – {$school_name}",
    $message
);

/* ================= REDIRECT ================= */
header("Location: dashboard.php?msg=admitted");
exit;
