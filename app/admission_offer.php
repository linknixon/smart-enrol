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

// The official admission letter should ONLY be generated and available after a student has officially been admitted
if (!$isAdmitted && !$isAdmin) {
    if (ob_get_length()) ob_end_clean();
    $school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
    $school_name       = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
    $school_logo       = getSchoolLogoSrc($pdo);
    $studentFullName   = strtoupper(trim(($student['surname'] ?? '') . ' ' . ($student['firstname'] ?? '') . ' ' . ($student['othernames'] ?? '')));
    $studentClass      = $student['class_of_entry'] ?? 'S1';
    $refNumber         = getAdmissionNumber($student, $pdo);
    $statusText        = ($appStatus === 'rejected') ? 'Application Unsuccessful' : 'Application Under Review';
    $statusBadgeClass  = ($appStatus === 'rejected') ? 'bg-rose-100 text-rose-800 border-rose-300' : 'bg-amber-100 text-amber-900 border-amber-300';
    $statusIconClass   = ($appStatus === 'rejected') ? 'fa-circle-xmark text-rose-600' : 'fa-clock text-amber-600';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Status - <?= htmlspecialchars($studentFullName) ?> | <?= htmlspecialchars($school_short_name) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/fontawesome/css/all.min.css">
    <?= renderThemeConfig($pdo) ?>
    </head>
    <body class="bg-slate-50 font-sans text-slate-800 antialiased min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-3xl border border-slate-200 shadow-xl p-6 sm:p-8 text-center space-y-5">
            <div class="w-20 h-20 bg-white rounded-2xl p-2 border-2 border-gold-500/30 shadow-md mx-auto flex items-center justify-center">
                <img src="<?= htmlspecialchars($school_logo) ?>" alt="School Logo" class="max-w-full max-h-full object-contain">
            </div>
            
            <div class="space-y-1">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border <?= $statusBadgeClass ?>">
                    <i class="fa-solid <?= $statusIconClass ?>"></i> <?= $statusText ?>
                </span>
                <h2 class="text-xl font-black text-slate-900 tracking-tight pt-2">Admission Letter Not Available</h2>
                <p class="text-xs text-slate-500">Official admission offer letters are only issued upon official admission approval.</p>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-left text-xs space-y-2">
                <div class="flex justify-between border-b border-slate-200/80 pb-2">
                    <span class="text-slate-500 font-semibold">Candidate:</span>
                    <span class="font-bold text-slate-900"><?= htmlspecialchars($studentFullName) ?></span>
                </div>
                <div class="flex justify-between border-b border-slate-200/80 pb-2">
                    <span class="text-slate-500 font-semibold">Reference ID:</span>
                    <span class="font-mono font-bold text-brand-700"><?= htmlspecialchars($refNumber) ?></span>
                </div>
                <div class="flex justify-between border-b border-slate-200/80 pb-2">
                    <span class="text-slate-500 font-semibold">Class Applied:</span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($studentClass) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500 font-semibold">Current Status:</span>
                    <span class="font-bold capitalize <?= ($appStatus === 'rejected') ? 'text-rose-600' : 'text-amber-600' ?>">
                        <?= htmlspecialchars($student['application_status'] ?? 'Under Review') ?>
                    </span>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-left text-xs text-emerald-950 space-y-2">
                <div class="flex items-center gap-2 font-black text-emerald-900">
                    <i class="fa-solid fa-circle-info text-emerald-600 text-sm"></i>
                    <span class="uppercase tracking-wider text-[11px]">Admission &amp; Payment Policy</span>
                </div>
                <p class="text-[11.5px] leading-relaxed text-emerald-900">
                    Your application and academic documents are currently undergoing evaluation by the Admissions Committee. <strong>The official Provisional Admission Letter and payment details will be made available here and sent to your registered email address once you are officially admitted.</strong>
                </p>
                <p class="text-[11px] text-emerald-800 italic">
                    No school fees or payments are accepted until your application has been officially approved.
                </p>
            </div>

            <div class="pt-2 flex flex-col gap-2">
                <a href="../track.php" class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-sm">
                    <i class="fa-solid fa-location-crosshairs text-xs"></i> Track Admission Status Online
                </a>
                <a href="../index.php" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition">
                    <i class="fa-solid fa-house text-xs"></i> Back to Homepage
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

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

// Applicant salutation: Dear {Firstname, Second Name} (Applicant full name instead of Parent/Guardian)
$applicantFirstName = trim($student['firstname'] ?? '');
$applicantSecondName = trim($student['surname'] ?? '');
$applicantOtherNames = trim($student['othernames'] ?? '');

$nameParts = [];
if (!empty($applicantFirstName)) $nameParts[] = $applicantFirstName;
if (!empty($applicantSecondName)) $nameParts[] = $applicantSecondName;
if (!empty($applicantOtherNames)) $nameParts[] = $applicantOtherNames;

if (!empty($nameParts)) {
    $applicantFullNameSalutation = ucwords(strtolower(implode(' ', $nameParts)));
} elseif (!empty(trim($student['full_name'] ?? ''))) {
    $applicantFullNameSalutation = ucwords(strtolower(trim($student['full_name'])));
} elseif (!empty(trim($student['name'] ?? ''))) {
    $applicantFullNameSalutation = ucwords(strtolower(trim($student['name'])));
} else {
    $applicantFullNameSalutation = 'Candidate';
}

$applicantSalutation = 'Dear ' . htmlspecialchars($applicantFullNameSalutation) . ',';
$parentSalutation = $applicantSalutation;

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
        '<tr><td width="3%" style="vertical-align: top; font-weight: bold;">3.</td><td width="97%" style="text-align: justify;"><strong>Payment of School Fees:</strong> All prescribed school dues must be cleared prior to or upon reporting. Details on how to pay have been shared via your registered email address.</td></tr>' .
        '</table>';
} else {
    $conditionsList = '<table cellpadding="1.5" cellspacing="0" style="width: 100%; border: none; font-size: 7.2pt; line-height: 1.35; margin: 0; font-family: \'trebuchetms\', sans-serif;">' .
        '<tr><td width="3%" style="vertical-align: top; font-weight: bold;">1.</td><td width="97%" style="text-align: justify;"><strong>Evaluation Notice:</strong> Admission is competitive and subject to the availability of vacancies and verification of submitted academic results against official UNEB records.</td></tr>' .
        '<tr><td width="3%" style="vertical-align: top; font-weight: bold;">2.</td><td width="97%" style="text-align: justify;"><strong>Payment Notice:</strong> No payment is required until an official offer of admission has been issued. Details on how to pay will be shared via email upon confirmation of admission.</td></tr>' .
        '<tr><td width="3%" style="vertical-align: top; font-weight: bold;">3.</td><td width="97%" style="text-align: justify;"><strong>Status Tracking:</strong> You will be notified of the committee\'s decision via email to your registered email contact (' . htmlspecialchars($student['parent_email'] ?: 'Registered Email') . ') and updates will be accessible on the school portal (' . htmlspecialchars($school_website) . ').</td></tr>' .
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

'<p style="margin: 2px 0 2px 0; padding: 0; font-size: 7.4pt;">' . $applicantSalutation . '</p>' .
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
$totalPages = ($isAdmitted || $isAdmin) ? 4 : 1;

$pdf->Line(15, 285, 195, 285, ['width' => 0.2, 'color' => [0, 0, 0]]);
$pdf->SetXY(15, 286);
$pdf->SetFont('trebuchetms', '', 6.2);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(180, 3.5, 'Official Admissions Packet   |   Verification Code: ' . $student_number . '   |   Page 1 of ' . $totalPages . '   |   Issued: ' . $generatedTime, 0, 1, 'C');

/* ================= HELPER FUNCTIONS FOR ENCLOSURE PAGES ================= */
if (!function_exists('renderEnclosureLetterhead')) {
    function renderEnclosureLetterhead($pdf, $school_name, $school_motto, $school_address, $school_phone, $school_email, $school_website, $hasLogo, $logo_source, $logoH, $studentFullName, $student_number, $classNameLong, $enclosureTitle, $academic_year) {
        if ($hasLogo && !empty($logo_source) && file_exists($logo_source)) {
            try {
                $pdf->Image($logo_source, 88, 8, 34, $logoH, '', '', '', false, 300, '', false, false, 0, 'CM');
            } catch (Exception $e) {}
        }
        
        $headerY = $hasLogo ? (8 + $logoH + 1.5) : 10;
        $pdf->SetXY(15, $headerY);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('trebuchetms', 'B', 12.5);
        $pdf->Cell(180, 5, strtoupper($school_name), 0, 1, 'C');

        if (!empty($school_motto)) {
            $pdf->SetX(15);
            $pdf->SetFont('trebuchetms', 'I', 8.5);
            $pdf->Cell(180, 3.8, '"' . $school_motto . '"', 0, 1, 'C');
        }

        $pdf->SetX(15);
        $pdf->SetFont('trebuchetms', '', 7.5);
        $pdf->Cell(180, 3.4, $school_address . '   |   Telephone: ' . $school_phone . '   |   Email: ' . $school_email, 0, 1, 'C');
        $pdf->SetX(15);
        $pdf->Cell(180, 3.2, 'Website: ' . $school_website, 0, 1, 'C');

        // Double divider line
        $divY = $pdf->GetY() + 1.2;
        $pdf->Line(15, $divY, 195, $divY, ['width' => 0.5, 'color' => [0, 0, 0]]);
        $pdf->Line(15, $divY + 0.5, 195, $divY + 0.5, ['width' => 0.2, 'color' => [0, 0, 0]]);

        // Enclosure Title Banner
        $bannerY = $divY + 2.5;
        $pdf->SetXY(15, $bannerY);
        $pdf->SetFont('trebuchetms', 'B', 9.5);
        $pdf->Cell(180, 4.5, $enclosureTitle . ' — ' . $academic_year, 0, 1, 'C');

        // Candidate particulars strip
        $pdf->SetX(15);
        $pdf->SetFont('trebuchetms', '', 7.5);
        $pdf->Cell(180, 4, 'Candidate: ' . $studentFullName . '   |   Student Code: ' . $student_number . '   |   Class: ' . $classNameLong, 0, 1, 'C');

        $pdf->Line(15, $pdf->GetY() + 1, 195, $pdf->GetY() + 1, ['width' => 0.2, 'color' => [0, 0, 0]]);
        $pdf->SetY($pdf->GetY() + 2.5);
    }
}

if (!function_exists('renderEnclosureFooter')) {
    function renderEnclosureFooter($pdf, $school_name, $student_number, $pageNumber, $totalPages = 4) {
        date_default_timezone_set('Africa/Kampala');
        $generatedTime = date('d-M-Y H:i:s T');
        $pdf->SetXY(15, 280);
        $pdf->SetFont('trebuchetms', 'I', 6.5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->MultiCell(180, 3, 'This is an official document of ' . $school_name . '. Any unauthorized alteration, erasure, or forgery renders this document completely null and void.', 0, 'C', 0, 1);

        $pdf->Line(15, 285, 195, 285, ['width' => 0.2, 'color' => [0, 0, 0]]);
        $pdf->SetXY(15, 286);
        $pdf->SetFont('trebuchetms', '', 6.2);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(180, 3.5, 'Official Admissions Packet   |   Verification Code: ' . $student_number . '   |   Page ' . $pageNumber . ' of ' . $totalPages . '   |   Issued: ' . $generatedTime, 0, 1, 'C');
    }
}

/* ================= MULTI-PAGE ENCLOSURES (PAGES 2, 3, 4) ================= */
if ($isAdmitted || $isAdmin) {

    /* ---------- PAGE 2: ENCLOSURE 1 - SCHOOL RULES & REGULATIONS ---------- */
    $pdf->AddPage();
    renderEnclosureLetterhead($pdf, $school_name, $school_motto, $school_address, $school_phone, $school_email, $school_website, $hasLogo, $logo_source, $logoH, $studentFullName, $student_number, $classNameLong, 'ENCLOSURE 1: GENERAL SCHOOL RULES AND REGULATIONS', $academic_year);

    $customRules = trim(getSetting($pdo, 'school_rules_content', ''));
    $rulesHtml = '<div style="font-family: \'trebuchetms\', sans-serif; font-size: 7.2pt; color: #000000; line-height: 1.35; text-align: justify;">';

    if (!empty($customRules)) {
        $rulesHtml .= nl2br(htmlspecialchars($customRules));
    } else {
        $rulesHtml .= '
        <div style="font-weight: bold; margin-bottom: 2px;">PREAMBLE &amp; CODE OF DISCIPLINE:</div>
        <p style="margin: 0 0 3px 0;">All students admitted to ' . htmlspecialchars($school_name) . ' must observe the following statutory rules with utmost discipline, diligence, and respect. Acceptance of a place in this institution signifies that both the student and parent/guardian unconditionally agree to abide by these regulations.</p>

        <table cellpadding="1.5" cellspacing="0" style="width: 100%; border: none; font-size: 7pt; line-height: 1.32;">
            <tr><td width="4%" style="font-weight: bold; vertical-align: top;">1.</td><td width="96%"><strong>Spiritual &amp; Moral Values:</strong> Students are required to uphold moral discipline, obedience to authority, and respect for staff and fellow students. Bullying, fighting, abusive language, theft, and insubordination attract immediate suspension.</td></tr>
            <tr><td width="4%" style="font-weight: bold; vertical-align: top;">2.</td><td width="96%"><strong>School Uniform &amp; Grooming:</strong> The approved school uniform must be worn at all formal school gatherings, classes, and assemblies. Civilian attire is strictly prohibited on campus. Hair must be neatly kept, uncolored, and without artificial additions.</td></tr>
            <tr><td width="4%" style="font-weight: bold; vertical-align: top;">3.</td><td width="96%"><strong>Academic Attendance &amp; Punctuality:</strong> Attendance at all lessons, laboratory practicals, prep sessions, roll calls, and official functions is mandatory. Dodging lessons or prep constitutes serious indiscipline.</td></tr>
            <tr><td width="4%" style="font-weight: bold; vertical-align: top;">4.</td><td width="96%"><strong>School Bounds &amp; Exeat Permits:</strong> No student is permitted to leave school compound without a formal written exeat permit authorized by the Deputy Head Teacher. Escaping from school (breaking bounds) leads to automatic expulsion.</td></tr>
            <tr><td width="4%" style="font-weight: bold; vertical-align: top;">5.</td><td width="96%"><strong>Prohibited Contraband Articles:</strong> Possession of mobile phones, smart devices, unprescribed drugs, alcohol, weapons, flammable liquids, or electric cooking coils is strictly forbidden and results in immediate expulsion.</td></tr>
            <tr><td width="4%" style="font-weight: bold; vertical-align: top;">6.</td><td width="96%"><strong>Property Care &amp; Restitution:</strong> Willful damage to school buildings, desks, lab apparatus, or library books will be repaired or replaced at the direct expense of the offending student\'s parent or guardian.</td></tr>
        </table>';
    }

    $rulesHtml .= '
    <div style="border: 1px solid #000000; padding: 4px 6px; margin-top: 4px; background-color: #ffffff;">
        <div style="font-weight: bold; font-size: 7.2pt; text-align: center; margin-bottom: 2px;">STUDENT &amp; PARENT / GUARDIAN UNDERTAKING</div>
        <p style="font-size: 6.8pt; margin: 0 0 4px 0; text-align: justify;">We, the undersigned Student and Parent/Guardian, confirm that we have thoroughly read, understood, and solemnly pledge to abide by the rules and regulations of ' . htmlspecialchars($school_short_name) . '. We accept that any breach may result in disciplinary sanctions up to expulsion.</p>
        <table cellpadding="2" cellspacing="0" style="width: 100%; border: none; font-size: 6.8pt;">
            <tr>
                <td width="50%"><strong>Student Signature:</strong> _____________________________</td>
                <td width="50%"><strong>Date:</strong> _____________________________</td>
            </tr>
            <tr>
                <td width="50%"><strong>Parent / Guardian Signature:</strong> _____________________________</td>
                <td width="50%"><strong>Date:</strong> _____________________________</td>
            </tr>
        </table>
    </div>
    </div>';

    $pdf->writeHTML($rulesHtml, true, false, true, false, '');
    renderEnclosureFooter($pdf, $school_name, $student_number, 2, 4);

    /* ---------- PAGE 3: ENCLOSURE 2 - BOARDING REQUIREMENTS CHECKLIST ---------- */
    $pdf->AddPage();
    renderEnclosureLetterhead($pdf, $school_name, $school_motto, $school_address, $school_phone, $school_email, $school_website, $hasLogo, $logo_source, $logoH, $studentFullName, $student_number, $classNameLong, 'ENCLOSURE 2: BOARDING & ACADEMIC REQUIREMENTS CHECKLIST', $academic_year);

    $reqText = trim($schoolRequirements);
    if (empty($reqText)) {
        $reqText = "1. School Uniform Package (Issued at School Bursary upon fees clearance)\n2. Black formal leather shoes and three (3) pairs of plain white socks / stockings\n3. Ten (10) 4-quire counter books, Oxford mathematical set, and scientific calculator\n4. One (1) ream of photocopy paper (Rotatrim / Double A) and 1 long ruler (30cm)\n5. One (1) hoe with sturdy handle and one (1) grass slasher\n6. High-density 2.5-inch mattress (3x6 ft), two (2) pairs of bedsheets, and warm blanket\n7. Metallic trunk / suitcase with two reliable padlocks\n8. Two (2) plastic buckets, one (1) basin, personal toiletries, and mosquito net\n9. Four (4) passport-size photographs and original PLE / UCE result slips";
    }

    $lines = array_values(array_filter(array_map('trim', explode("\n", $reqText))));

    $reqHtml = '<div style="font-family: \'trebuchetms\', sans-serif; font-size: 7.2pt; color: #000000; line-height: 1.35;">' .
        '<p style="margin: 0 0 3px 0; font-size: 7.2pt; text-align: justify;">The admitted student must report with the following mandatory items on opening day. Each requirement will be verified and stamped by the Senior Housemaster / Housemistress before dormitory room allocation.</p>' .
        '<table border="1" cellpadding="2.8" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 6.9pt; font-family: \'trebuchetms\', sans-serif;">' .
            '<thead>' .
                '<tr style="font-weight: bold; background-color: #ffffff;">' .
                    '<th width="8%" align="center" style="font-weight: bold;">No.</th>' .
                    '<th width="62%" align="left" style="font-weight: bold;">Prescribed Item &amp; Specification</th>' .
                    '<th width="15%" align="center" style="font-weight: bold;">Inspection Check</th>' .
                    '<th width="15%" align="center" style="font-weight: bold;">Clearance Status</th>' .
                '</tr>' .
            '</thead>' .
            '<tbody>';

    $i = 1;
    foreach ($lines as $line) {
        $cleanLine = preg_replace('/^\d+[\.\)]\s*/', '', $line);
        if (empty($cleanLine)) continue;
        $reqHtml .= '<tr>' .
            '<td width="8%" align="center">' . $i . '</td>' .
            '<td width="62%" align="left">' . htmlspecialchars($cleanLine) . '</td>' .
            '<td width="15%" align="center">[ &nbsp;&nbsp;&nbsp;&nbsp; ] Verified</td>' .
            '<td width="15%" align="center">Cleared</td>' .
        '</tr>';
        $i++;
    }

    $reqHtml .= '</tbody></table>' .
        '<div style="border: 1px solid #000000; padding: 4px 6px; margin-top: 4px; background-color: #ffffff;">' .
            '<div style="font-weight: bold; font-size: 7.2pt; margin-bottom: 2px;">BOARDING MASTER / MISTRESS CLEARANCE &amp; HOUSE ALLOCATION</div>' .
            '<table cellpadding="2" cellspacing="0" style="width: 100%; border: none; font-size: 6.8pt;">' .
                '<tr>' .
                    '<td width="50%"><strong>Cleared by (Senior Warden):</strong> _____________________________</td>' .
                    '<td width="50%"><strong>Signature &amp; Date:</strong> _____________________________</td>' .
                '</tr>' .
                '<tr>' .
                    '<td width="50%"><strong>Dormitory / House Allocated:</strong> _____________________________</td>' .
                    '<td width="50%"><strong>Bed / Locker Number:</strong> _____________________________</td>' .
                '</tr>' .
            '</table>' .
        '</div>' .
    '</div>';

    $pdf->writeHTML($reqHtml, true, false, true, false, '');
    renderEnclosureFooter($pdf, $school_name, $student_number, 3, 4);

    /* ---------- PAGE 4: ENCLOSURE 3 - MEDICAL EXAMINATION FORM ---------- */
    $pdf->AddPage();
    renderEnclosureLetterhead($pdf, $school_name, $school_motto, $school_address, $school_phone, $school_email, $school_website, $hasLogo, $logo_source, $logoH, $studentFullName, $student_number, $classNameLong, 'ENCLOSURE 3: CONFIDENTIAL STUDENT MEDICAL EXAMINATION FORM', $academic_year);

    $medHtml = '<div style="font-family: \'trebuchetms\', sans-serif; font-size: 7.2pt; color: #000000; line-height: 1.35;">' .
        '<p style="margin: 0 0 3px 0; font-size: 7.2pt; text-align: justify;"><strong>Notice to Medical Officer:</strong> This confidential medical form must be completed by a Registered Medical Doctor or Clinical Officer at an accredited health facility prior to reporting. Accurate medical declaration ensures appropriate care in the school sickbay.</p>' .

        '<div style="font-weight: bold; font-size: 7.2pt; margin: 2px 0 1px 0;">SECTION A: CANDIDATE MEDICAL HISTORY (Declared by Parent/Guardian)</div>' .
        '<table border="1" cellpadding="2.5" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 6.8pt; font-family: \'trebuchetms\', sans-serif;">' .
            '<tr>' .
                '<td width="50%"><strong>Asthma / Respiratory Conditions:</strong> [ &nbsp; ] Yes &nbsp; [ &nbsp; ] No</td>' .
                '<td width="50%"><strong>Epilepsy / Convulsions:</strong> [ &nbsp; ] Yes &nbsp; [ &nbsp; ] No</td>' .
            '</tr>' .
            '<tr>' .
                '<td width="50%"><strong>Sickle Cell Disease / Trait:</strong> [ &nbsp; ] Yes &nbsp; [ &nbsp; ] No</td>' .
                '<td width="50%"><strong>Diabetes / Cardiac Condition:</strong> [ &nbsp; ] Yes &nbsp; [ &nbsp; ] No</td>' .
            '</tr>' .
            '<tr>' .
                '<td colspan="2"><strong>Known Food / Medication Allergies:</strong> ____________________________________________________________________</td>' .
            '</tr>' .
            '<tr>' .
                '<td colspan="2"><strong>Physical Impairments / Special Dietary Requirements:</strong> __________________________________________________</td>' .
            '</tr>' .
        '</table>' .

        '<div style="font-weight: bold; font-size: 7.2pt; margin: 4px 0 1px 0;">SECTION B: CLINICAL EXAMINATION (Conducted by Medical Doctor)</div>' .
        '<table border="1" cellpadding="2.5" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 6.8pt; font-family: \'trebuchetms\', sans-serif;">' .
            '<tr>' .
                '<td width="25%"><strong>Height:</strong> _________ cm</td>' .
                '<td width="25%"><strong>Weight:</strong> _________ kg</td>' .
                '<td width="25%"><strong>Blood Pressure:</strong> _________</td>' .
                '<td width="25%"><strong>Blood Group:</strong> _________</td>' .
            '</tr>' .
            '<tr>' .
                '<td width="50%"><strong>Visual Acuity:</strong> Right: _________ &nbsp; Left: _________</td>' .
                '<td width="50%"><strong>Hearing / ENT Examination:</strong> _____________________________</td>' .
            '</tr>' .
            '<tr>' .
                '<td width="50%"><strong>Cardiovascular / Chest / Lungs:</strong> _____________________</td>' .
                '<td width="50%"><strong>Abdomen / Spleen / Hernia:</strong> _____________________________</td>' .
            '</tr>' .
            '<tr>' .
                '<td colspan="2"><strong>Skin / Communicable Conditions / Urinalysis:</strong> ___________________________________________________________</td>' .
            '</tr>' .
        '</table>' .

        '<div style="font-weight: bold; font-size: 7.2pt; margin: 4px 0 1px 0;">SECTION C: MEDICAL PRACTITIONER\'S CERTIFICATION &amp; RECOMMENDATION</div>' .
        '<div style="border: 1px solid #000000; padding: 4px 6px; background-color: #ffffff;">' .
            '<p style="font-size: 6.8pt; margin: 0 0 3px 0; text-align: justify;">I have clinically examined the candidate named above and hereby certify that in my medical opinion, the student is <strong>[ &nbsp; ] FIT &nbsp;&nbsp; [ &nbsp; ] UNFIT</strong> to undertake normal secondary school academic, boarding, and physical co-curricular activities.</p>' .
            '<table cellpadding="2" cellspacing="0" style="width: 100%; border: none; font-size: 6.8pt;">' .
                '<tr>' .
                    '<td width="60%"><strong>Medical Doctor\'s Full Name:</strong> ___________________________________</td>' .
                    '<td width="40%" rowspan="3" align="center" style="vertical-align: middle; border: 1px dashed #000000;">' .
                        '<span style="font-size: 6pt; color: #000000;">OFFICIAL HOSPITAL /<br>CLINIC RUBBER STAMP</span>' .
                    '</td>' .
                '</tr>' .
                '<tr>' .
                    '<td><strong>Health Facility &amp; Tel:</strong> ___________________________________</td>' .
                '</tr>' .
                '<tr>' .
                    '<td><strong>Doctor\'s Signature:</strong> ________________________ <strong>Date:</strong> ___________</td>' .
                '</tr>' .
            '</table>' .
        '</div>' .
    '</div>';

    $pdf->writeHTML($medHtml, true, false, true, false, '');
    renderEnclosureFooter($pdf, $school_name, $student_number, 4, 4);
}

/* ================= OUTPUT ================= */
if (ob_get_length()) {
    ob_end_clean();
}
$outFilename = ($isAdmitted ? 'Admission_Offer_' : 'Application_Receipt_') . $student_number . '.pdf';
$pdf->Output($outFilename, 'I');
exit;
