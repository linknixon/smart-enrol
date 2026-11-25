<?php
// Suppress display errors in PDF output stream to prevent header corruption
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/../config/db.php';
require_once(__DIR__ . '/../form-one/tcpdf/tcpdf.php');

/* ================= VALIDATION ================= */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid request: Missing application identifier.');
}

$id = (int)$_GET['id'];

/* ================= FETCH STUDENT ================= */
$stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die('Student application record not found.');
}

$isAdmin = !empty($_SESSION['admin_user']) || !empty($_SESSION['role']);
$appStatus = strtolower($student['application_status'] ?? 'pending');
$isAdmitted = ($appStatus === 'admitted');

/* ================= SYSTEM CONFIGURATION ================= */
$school_name        = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_motto       = getSetting($pdo, 'school_motto', 'Education for Service');
$school_address     = getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro - Uganda');
$school_phone       = getSetting($pdo, 'school_phone', '+256 790 001 631');
$school_email       = getSetting($pdo, 'school_email', 'info@ncedges.com');
$school_website     = getSetting($pdo, 'school_website', 'www.mutolere.sc.ug');
$studentClass       = $student['class_of_entry'] ?? 'S1';
$academic_year      = getAcademicYear($pdo, $studentClass);
$year               = getAcademicYearShort($pdo, $studentClass);
$school_code        = getSetting($pdo, 'school_code', '011');
$head_teacher_name  = getSetting($pdo, 'head_teacher_name', 'MRS. PEACE RUZAZA');
$head_teacher_title = getSetting($pdo, 'head_teacher_title', 'Head Teacher');

$logo_source = getSchoolLogoFilePath($pdo, __DIR__ . '/..');

/* ================= REPORTING DATE & ENCLOSURES ================= */
$reportingDate = getReportingDate($pdo, $studentClass);
$schoolRequirements = getSetting($pdo, 'school_requirements', '');

// Resolve Head Teacher E-Signature (Direct local file path for TCPDF stability)
$signaturePath = getSchoolSignatureFilePath($pdo, __DIR__ . '/..');
$hasSignature = (!empty($signaturePath) && file_exists($signaturePath));

// Resolve Circular Student Passport Photo (Direct local file path for TCPDF stability)
$studentPhotoPath = getCircularStudentPhotoFilePath($student['passport_photo'] ?? '', __DIR__ . '/..');
$hasStudentPhoto = (!empty($studentPhotoPath) && file_exists($studentPhotoPath));

if (!function_exists('formatCombinationElective')) {
    function formatCombinationElective($combCode, $pdo) {
        if (empty($combCode)) return '';
        $combsList = null;
        if ($pdo) {
            $rawCombs = getSetting($pdo, 'a_level_combinations', '');
            $combsList = !empty($rawCombs) ? json_decode($rawCombs, true) : null;
        }
        
        $cleanCode = trim($combCode);
        
        if (strpos($cleanCode, '/') !== false) {
            $parts = explode('/', $cleanCode);
            $core = trim($parts[0]);
            $elec = trim($parts[1]);
            if (strcasecmp($elec, 'SMT') === 0) $elec = 'Sub-Maths';
            
            if ($combsList && is_array($combsList)) {
                foreach ($combsList as $group => $items) {
                    if (is_array($items)) {
                        foreach ($items as $k => $label) {
                            if (strcasecmp($k, $cleanCode) === 0 || strcasecmp($k, $core . '/' . $elec) === 0 || strcasecmp($k, $core . '/SMT') === 0) {
                                return $core . '/' . $elec . ' (' . $label . ')';
                            }
                        }
                    }
                }
            }
            return $core . '/' . $elec;
        }
        
        if ($combsList && is_array($combsList)) {
            foreach ($combsList as $group => $items) {
                if (is_array($items)) {
                    foreach ($items as $k => $label) {
                        if (stripos($k, $cleanCode . '/') === 0) {
                            $parts = explode('/', $k);
                            $elec = trim($parts[1] ?? 'ICT');
                            if (strcasecmp($elec, 'SMT') === 0) $elec = 'Sub-Maths';
                            return $cleanCode . '/' . $elec . ' (' . $label . ')';
                        }
                    }
                }
            }
        }
        
        return $cleanCode . '/ICT';
    }
}

/* ================= STUDENT NUMBER & COMBINATION ================= */
$class = $student['class_of_entry'];
$student_number = getAdmissionNumber($student, $pdo);
$studentCombinationDisplay = '';
$studentCombinationShort = '';
if (($class === 'S5' || $class === 'S6') && !empty($student['combination'])) {
    $studentCombinationDisplay = formatCombinationElective($student['combination'], $pdo);
    if (strpos($studentCombinationDisplay, ' (') !== false) {
        $studentCombinationShort = substr($studentCombinationDisplay, 0, strpos($studentCombinationDisplay, ' ('));
    } else {
        $studentCombinationShort = $studentCombinationDisplay;
    }
}

/* ================= CLASS-SPECIFIC FEE STRUCTURE ================= */
$classNameLong = "Senior " . substr($class, 1) . " (" . $class . ")";
$clsKey = strtolower($class); // 's1', 's2', 's3', 's4', 's5', 's6'

$feesMatrix = getFeesStructureMatrix($pdo);
$cleanClass = strtoupper(str_replace(['.', ' ', '-'], '', $class));
$matchedRow = null;
if (!empty($feesMatrix['rows']) && is_array($feesMatrix['rows'])) {
    foreach ($feesMatrix['rows'] as $r) {
        $rowClass = strtoupper(str_replace(['.', ' ', '-'], '', $r['class'] ?? ''));
        if ($rowClass === $cleanClass) {
            $matchedRow = $r;
            break;
        }
    }
}

if ($matchedRow) {
    $admission   = (int)preg_replace('/[^\d]/', '', $matchedRow['admission_fee'] ?? '100000');
    $uniforms    = (int)preg_replace('/[^\d]/', '', $matchedRow['uniforms'] ?? '400000');
    $vocational  = (int)preg_replace('/[^\d]/', '', $matchedRow['vocational'] ?? '0');
    $tuition     = (int)preg_replace('/[^\d]/', '', $matchedRow['tuition_boarding'] ?? '1265000');
    $tuition_day = (int)preg_replace('/[^\d]/', '', $matchedRow['tuition_day'] ?? '750000');
    $functional  = (int)preg_replace('/[^\d]/', '', $matchedRow['functional'] ?? '150000');
} else {
    $tuition     = (int)getSetting($pdo, "fees_{$clsKey}_tuition", 1265000);
    $admission   = (int)getSetting($pdo, "fees_{$clsKey}_admission", 100000);
    $uniforms    = 400000;
    $vocational  = in_array($cleanClass, ['S4', 'S6']) ? 0 : 100000;
    $tuition_day = 750000;
    $functional  = (int)getSetting($pdo, "fees_{$clsKey}_functional", 150000);
}
$totalFees = $tuition + $admission + $uniforms + $vocational + $functional;

/* ================= PDF SETUP (STRICTLY MONOCHROME A4 STANDARD) ================= */
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetCreator($school_name);
$pdf->SetAuthor($school_name);
$docTitle = ($isAdmitted || $isAdmin) ? ('Admission Offer - ' . $student_number) : ('Application Receipt - ' . $student_number);
$pdf->SetTitle($docTitle);
$pdf->SetMargins(15, 10, 15);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

/* ================= CENTERED OFFICIAL LETTERHEAD ================= */
$hasLogo = false;
$logoH = 19;
if (!empty($logo_source) && file_exists($logo_source)) {
    try {
        $pdf->Image($logo_source, 88, 8, 34, $logoH, '', '', '', false, 300, '', false, false, 0, 'CM');
        $hasLogo = true;
    } catch (Exception $e) {
        $hasLogo = false;
    }
}

$headerY = $hasLogo ? (8 + $logoH + 1.5) : 10;

$pdf->SetXY(15, $headerY);
$pdf->SetTextColor(0, 0, 0); // Strictly black
$pdf->SetFont('trebuchetms', 'B', 12.5);
$pdf->Cell(180, 5, strtoupper($school_name), 0, 1, 'C');

if (!empty($school_motto)) {
    $pdf->SetX(15);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('trebuchetms', 'I', 8.5);
    $pdf->Cell(180, 3.8, '"' . $school_motto . '"', 0, 1, 'C');
}

$pdf->SetX(15);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('trebuchetms', '', 7.5);
$pdf->Cell(180, 3.4, $school_address . '   |   Telephone: ' . $school_phone . '   |   Email: ' . $school_email, 0, 1, 'C');
$pdf->SetX(15);
$pdf->Cell(180, 3.2, 'Website: ' . $school_website, 0, 1, 'C');

// Official double black divider line
$divY = $pdf->GetY() + 1.2;
$pdf->Line(15, $divY, 195, $divY, ['width' => 0.5, 'color' => [0, 0, 0]]);
$pdf->Line(15, $divY + 0.5, 195, $divY + 0.5, ['width' => 0.2, 'color' => [0, 0, 0]]);

/* ================= REFERENCE NUMBER & DATE ================= */
$refY = $divY + 2.5;
$pdf->SetXY(15, $refY);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('trebuchetms', '', 8);

$studentFullName = strtoupper(trim($student['surname'] . ' ' . $student['firstname'] . ' ' . ($student['othernames'] ?? '')));
$currentDateFormatted = date('jS F, Y');
$refNumber = 'MSS/ADM/' . $year . '/' . $student_number;

$pdf->Cell(95, 4, 'Our Ref: ' . $refNumber, 0, 0, 'L');
$pdf->Cell(85, 4, 'Date: ' . $currentDateFormatted, 0, 1, 'R');

/* ================= ADDRESSEE & STUDENT PARTICULARS ================= */
$contentY = $pdf->GetY() + 1.5;
$pdf->SetXY(15, $contentY);

// Academic credential label
$academicCredential = '';
if ($class === 'S1') {
    $academicCredential = 'Index No: ' . htmlspecialchars($student['index_number']) . ' (Agg: ' . htmlspecialchars($student['aggregates'] ?: 'N/A') . ', Div: ' . htmlspecialchars($student['division'] ?: 'N/A') . ')';
} elseif ($class === 'S5' || $class === 'S6') {
    $academicCredential = 'Index No: ' . htmlspecialchars($student['index_number']) . ' (' . htmlspecialchars($student['division'] ?: ('Agg ' . $student['aggregates'])) . ')';
} else {
    $academicCredential = 'Former School: ' . htmlspecialchars($student['former_school'] ?: 'N/A');
}

$parentName = trim(($student['father_name'] ?? '') ?: (($student['mother_name'] ?? '') ?: ($student['guardian_name'] ?? '')));
if (empty($parentName)) {
    $parentSalutation = 'Dear Parent / Guardian,';
} else {
    $parentSalutation = 'Dear ' . htmlspecialchars($parentName) . ' (Parent/Guardian),';
}

// Letter Subject Line (Monochrome, formal, underlined)
if ($isAdmitted) {
    $subjectLine = 'RE: PROVISIONAL OFFER OF ADMISSION TO ' . strtoupper($classNameLong) . ' — ' . $academic_year . ' ACADEMIC YEAR';
    if (($class === 'S5' || $class === 'S6') && !empty($studentCombinationShort)) {
        $subjectLine .= ' (COMBINATION: ' . strtoupper(htmlspecialchars($studentCombinationShort)) . ')';
    }
} elseif ($isAdmin) {
    $subjectLine = 'RE: PROVISIONAL ADMISSION PREVIEW — ' . strtoupper($classNameLong) . ' — ' . $academic_year . ' ACADEMIC YEAR';
} else {
    $subjectLine = 'RE: ACKNOWLEDGEMENT OF APPLICATION FOR ADMISSION TO ' . strtoupper($classNameLong) . ' — ' . $academic_year . ' ACADEMIC YEAR';
}

// Main body text in authentic administrative prose (strictly non-AI)
if ($isAdmitted) {
    $openingPara = "I am pleased to inform you that following your satisfactory performance in the " . (($class === 'S1') ? "Primary Leaving Examination (PLE)" : "Uganda Certificate of Education (UCE)") . ", you have been offered a provisional vacancy in <strong>" . $classNameLong . "</strong> at <strong>" . htmlspecialchars($school_name) . "</strong> for the <strong>" . htmlspecialchars($academic_year) . " Academic Year</strong>.";
    if (($class === 'S5' || $class === 'S6') && !empty($studentCombinationDisplay)) {
        $openingPara .= " You have been allocated the following subject combination: <strong>" . htmlspecialchars($studentCombinationDisplay) . "</strong>.";
    }
    $openingPara .= " This offer is made on academic merit and is subject to the following terms and conditions:";
} else {
    $openingPara = "This is to acknowledge receipt of your application for admission into <strong>" . $classNameLong . "</strong> at <strong>" . htmlspecialchars($school_name) . "</strong> for the <strong>" . htmlspecialchars($academic_year) . " Academic Year</strong>. Your application is registered under Reference Number <strong>" . $student_number . "</strong> and is currently under active review by the Admissions Committee in accordance with the established school admission criteria.";
}

// Conditions and instructions
if ($isAdmitted) {
    $reportingNotice = (!empty($reportingDate) ? htmlspecialchars($reportingDate) : 'the official opening day of Term I');
    $conditionsList = '<table cellpadding="1.5" cellspacing="0" style="width: 100%; border: none; font-size: 7.2pt; line-height: 1.35; margin: 0; font-family: \'trebuchetms\', sans-serif;">' .
        '<tr><td width="3%" style="vertical-align: top; font-weight: bold;">1.</td><td width="97%" style="text-align: justify;"><strong>Reporting Date &amp; Forfeiture:</strong> You are expected to report to the school on <strong>' . $reportingNotice . '</strong> between 8:00 AM and 4:00 PM. Please take note that this provisional vacancy will automatically lapse and be re-allocated if you fail to report within five (5) working days from the opening date without prior written permission approved by the Head Teacher.</td></tr>' .
        '<tr><td width="3%" style="vertical-align: top; font-weight: bold;">2.</td><td width="97%" style="text-align: justify;"><strong>Verification of Credentials:</strong> Admission is strictly provisional and conditional upon the physical presentation and verification of your original UNEB Result Slip, Primary Leaving / UCE Certificate, Birth Certificate, and two (2) recent passport-sized photographs on arrival.</td></tr>' .
        '<tr><td width="3%" style="vertical-align: top; font-weight: bold;">3.</td><td width="97%" style="text-align: justify;"><strong>Payment of School Fees:</strong> All prescribed school dues must be cleared prior to or upon reporting. Details on how to pay will be shared.</td></tr>' .
        '</table>';
} else {
    $conditionsList = '<table cellpadding="1.5" cellspacing="0" style="width: 100%; border: none; font-size: 7.2pt; line-height: 1.35; margin: 0; font-family: \'trebuchetms\', sans-serif;">' .
        '<tr><td width="3%" style="vertical-align: top; font-weight: bold;">1.</td><td width="97%" style="text-align: justify;"><strong>Evaluation Notice:</strong> Admission is competitive and subject to the availability of vacancies and verification of submitted academic results against official UNEB records.</td></tr>' .
        '<tr><td width="3%" style="vertical-align: top; font-weight: bold;">2.</td><td width="97%" style="text-align: justify;"><strong>Payment Notice:</strong> No payment is required until an official offer of admission has been issued. Details on how to pay will be shared upon confirmation of admission.</td></tr>' .
        '<tr><td width="3%" style="vertical-align: top; font-weight: bold;">3.</td><td width="97%" style="text-align: justify;"><strong>Status Tracking:</strong> You will be notified of the committee\'s decision via SMS to your registered telephone contact (' . htmlspecialchars($student['parent_phone'] ?: 'Parent Contact') . ') and updates will be accessible on the school portal (' . htmlspecialchars($school_website) . ').</td></tr>' .
        '</table>';
}

$feeTableTitle = $isAdmitted ? 'APPROVED TERM I SCHOOL FEES SCHEDULE' : 'ESTIMATED TERM I SCHOOL FEES SCHEDULE';

// Build Complete Document HTML with Trebuchet MS and Clean Standard Tables
$letterHtml = '<div style="font-family: \'trebuchetms\', sans-serif; font-size: 7.4pt; color: #000000; line-height: 1.35; text-align: justify;">' .

'<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 7.2pt; font-family: \'trebuchetms\', sans-serif; margin-bottom: 2px;">' .
    '<tr>' .
        '<td width="55%"><strong>Student Name:</strong> ' . htmlspecialchars($studentFullName) . '</td>' .
        '<td width="29%"><strong>Student Code:</strong> ' . $student_number . '</td>' .
        '<td width="16%" rowspan="3" align="center" style="vertical-align: middle;">' .
            ($hasStudentPhoto 
                ? '<img src="' . htmlspecialchars($studentPhotoPath) . '" width="54" height="54" />' 
                : '<span style="font-size: 5.5pt; font-weight: bold; line-height: 1.2;">STUDENT<br>PASSPORT<br>PHOTO</span>') .
        '</td>' .
    '</tr>' .
    '<tr>' .
        '<td width="55%"><strong>Class:</strong> ' . $classNameLong . ' &nbsp;|&nbsp; <strong>Sex:</strong> ' . ucfirst($student['sex'] ?? 'N/A') . ' &nbsp;|&nbsp; <strong>Nationality:</strong> ' . htmlspecialchars($student['nationality'] ?? 'Ugandan') . '</td>' .
        '<td width="29%">' . $academicCredential . '</td>' .
    '</tr>' .
    '<tr>' .
        '<td width="55%"><strong>Former School:</strong> ' . htmlspecialchars($student['former_school'] ?: 'N/A') . '</td>' .
        '<td width="29%"><strong>Status:</strong> ' . ($isAdmitted ? 'PROVISIONALLY ADMITTED' : 'APPLICATION UNDER REVIEW') . '</td>' .
    '</tr>' .
'</table>' .

'<div style="text-align: center; margin: 3px 0 2px 0;">' .
    '<strong style="font-size: 8.2pt; text-decoration: underline;">' . $subjectLine . '</strong>' .
'</div>' .

'<p style="margin: 2px 0 2px 0; padding: 0; font-size: 7.4pt;">' . $parentSalutation . '</p>' .
'<p style="margin: 0 0 3px 0; padding: 0; font-size: 7.3pt; text-align: justify;">' . $openingPara . '</p>' .

$conditionsList .

'<div style="font-size: 7.2pt; font-weight: bold; margin: 3px 0 1.5px 0; text-align: left;">' . $feeTableTitle . ' (UGX)</div>' .
'<table border="1" cellpadding="2.8" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 7pt; font-family: \'trebuchetms\', sans-serif; margin: 0;">' .
    '<thead>' .
        '<tr style="font-weight: bold; background-color: #ffffff;">' .
            '<th width="8%" align="center" style="font-weight: bold; text-align: center;">No.</th>' .
            '<th width="64%" align="left" style="font-weight: bold; text-align: left;">Item Description</th>' .
            '<th width="28%" align="right" style="font-weight: bold; text-align: right;">Amount (UGX)</th>' .
        '</tr>' .
    '</thead>' .
    '<tbody>' .
        '<tr>' .
            '<td width="8%" align="center">1</td>' .
            '<td width="64%" align="left">Tuition and Boarding Fees (Termly)</td>' .
            '<td width="28%" align="right">' . number_format($tuition) . '</td>' .
        '</tr>' .
        '<tr>' .
            '<td width="8%" align="center">2</td>' .
            '<td width="64%" align="left">Admission &amp; Enrolment Fee (Paid Once upon Entry)</td>' .
            '<td width="28%" align="right">' . number_format($admission) . '</td>' .
        '</tr>' .
        '<tr>' .
            '<td width="8%" align="center">3</td>' .
            '<td width="64%" align="left">School Uniforms Package (Paid Once upon Entry)</td>' .
            '<td width="28%" align="right">' . number_format($uniforms) . '</td>' .
        '</tr>' .
        ($vocational > 0 ? ('<tr><td width="8%" align="center">4</td><td width="64%" align="left">Vocational Skills Training (Termly)</td><td width="28%" align="right">' . number_format($vocational) . '</td></tr>') : '') .
        '<tr>' .
            '<td width="8%" align="center">' . ($vocational > 0 ? '5' : '4') . '</td>' .
            '<td width="64%" align="left">Functional Fees (ICT, Laboratory, Library &amp; Student Identity Card)</td>' .
            '<td width="28%" align="right">' . number_format($functional) . '</td>' .
        '</tr>' .
        '<tr style="font-weight: bold;">' .
            '<td colspan="2" align="left" style="font-weight: bold; text-align: left;"><strong>Total Term I Fees Payable (Boarding)</strong></td>' .
            '<td width="28%" align="right" style="font-weight: bold; text-align: right;"><strong>UGX ' . number_format($totalFees) . '</strong></td>' .
        '</tr>' .
    '</tbody>' .
'</table>' .
'<div style="font-size: 6.4pt; margin: 1.5px 0 2px 0; text-align: left;">Note: Tuition for Day Scholars is UGX ' . number_format($tuition_day) . '/term. School fees once paid are non-refundable and non-transferable.</div>' .
'<div style="font-size: 6.8pt; margin: 0 0 2px 0;"><strong>Enclosures:</strong> (1) School Rules &amp; Regulations &nbsp;|&nbsp; (2) Boarding Requirements Checklist &nbsp;|&nbsp; (3) Medical Examination Form</div>' .
'</div>';

$pdf->writeHTML($letterHtml, true, false, true, false, '');

/* ================= SIGNATURE & QR VERIFICATION ================= */
$sigY = $pdf->GetY() + 2.5;

// 1. 2D QR Code Verification (Monochrome)
$qrStyle = [
    'border' => 0,
    'vpadding' => 0,
    'hpadding' => 0,
    'fgcolor' => [0, 0, 0],
    'bgcolor' => false
];
$qrLink = getBaseUrl($pdo) . '/app/admission_offer.php?id=' . (int)$student['id'];
$pdf->write2DBarcode($qrLink, 'QRCODE,H', 15, $sigY + 0.5, 18, 18, $qrStyle, 'N');

// 2. Official Document Verification Text (Neatly aligned beside QR code)
$pdf->SetXY(35.5, $sigY + 2);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('trebuchetms', 'B', 7.5);
$pdf->Cell(62, 4, 'Official Document Verification', 0, 1, 'L');

$pdf->SetX(35.5);
$pdf->SetFont('trebuchetms', '', 7);
$pdf->Cell(62, 3.8, 'Admissions Registry Office', 0, 1, 'L');

$pdf->SetX(35.5);
$pdf->SetFont('trebuchetms', '', 6.5);
$pdf->MultiCell(62, 3.2, "Scan QR code to verify validity on the school admissions\nportal.", 0, 'L');

// 3. Head Teacher Signature Block
$pdf->SetXY(110, $sigY);
$pdf->SetFont('trebuchetms', '', 7.5);
$pdf->Cell(85, 4, 'Yours faithfully,', 0, 1, 'C');

if ($hasSignature) {
    try {
        $pdf->Image($signaturePath, 137.5, $sigY + 4, 30, 11, '', '', '', false, 300, '', false, false, 0, 'CM');
    } catch (Exception $e) {
        $pdf->Line(130, $sigY + 14, 170, $sigY + 14, ['width' => 0.3, 'color' => [0, 0, 0]]);
    }
} else {
    $pdf->Line(130, $sigY + 14, 170, $sigY + 14, ['width' => 0.3, 'color' => [0, 0, 0]]);
}

$pdf->SetXY(110, $sigY + 15.5);
$pdf->SetFont('trebuchetms', 'B', 8);
$pdf->Cell(85, 4, strtoupper($head_teacher_name), 0, 1, 'C');

$pdf->SetXY(110, $sigY + 19.5);
$pdf->SetFont('trebuchetms', '', 7.2);
$pdf->Cell(85, 3.5, $head_teacher_title, 0, 1, 'C');

$pdf->SetXY(110, $sigY + 23);
$pdf->SetFont('trebuchetms', '', 6.8);
$pdf->Cell(85, 3.5, $school_short_name, 0, 1, 'C');

/* ================= FOOTER DISCLAIMER ================= */
$pdf->SetXY(15, 280);
$pdf->SetFont('trebuchetms', 'I', 6.5);
$pdf->SetTextColor(0, 0, 0);
$pdf->MultiCell(180, 3, 'This is an official document of ' . $school_name . '. Any unauthorized alteration, erasure, or forgery renders this offer completely null and void.', 0, 'C', 0, 1);

/* ================= SECURITY FOOTER ================= */
date_default_timezone_set('Africa/Kampala');
$generatedTime = date('d-M-Y H:i:s T');

$pdf->Line(15, 285, 195, 285, ['width' => 0.2, 'color' => [0, 0, 0]]);
$pdf->SetXY(15, 286);
$pdf->SetFont('trebuchetms', '', 6.2);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(180, 3.5, 'Official Admissions Document   |   Verification Code: ' . $student_number . '   |   Issued: ' . $generatedTime, 0, 1, 'C');

/* ================= OUTPUT ================= */
if (ob_get_length()) {
    ob_end_clean();
}
$outFilename = ($isAdmitted ? 'Admission_Offer_' : 'Application_Receipt_') . $student_number . '.pdf';
$pdf->Output($outFilename, 'I');
exit;
