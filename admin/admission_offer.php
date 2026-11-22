<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/db.php';
require_once(__DIR__ . '/../form-one/tcpdf/tcpdf.php');

/* ================= VALIDATION ================= */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid request');
}

$id = (int)$_GET['id'];

/* ================= FETCH STUDENT ================= */
$stmt = $pdo->prepare("
    SELECT * FROM applications
    WHERE id = ? AND application_status = 'admitted'
");
$stmt->execute([$id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die('Student not found or not admitted');
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

$primaryHex = getSetting($pdo, 'theme_primary_color', '#0c4a24');
$accentHex  = getSetting($pdo, 'theme_accent_color', '#b58d2f');
$primaryRgb = hex2rgb($primaryHex, [12, 74, 36]);
$accentRgb  = hex2rgb($accentHex, [181, 141, 47]);

$logo_source = getSchoolLogoFilePath($pdo, __DIR__ . '/..');

/* ================= REPORTING DATE & ENCLOSURES ================= */
$reportingDate = getReportingDate($pdo, $studentClass);
$schoolRequirements = getSetting($pdo, 'school_requirements', '');
$admissionLetterCustomNote = getSetting($pdo, 'admission_letter_custom_note', 'Please note: This provisional admission offer is subject to physical verification of original UNEB results slips and birth certificate upon arrival on the reporting date. Fees once paid are non-refundable.');

$docRules    = getSetting($pdo, 'doc_school_rules', '');
$docCircular = getSetting($pdo, 'doc_admission_circular', '');
$docMedForm  = getSetting($pdo, 'doc_medical_form', '');

// Resolve Head Teacher E-Signature
$signaturePath = getSchoolSignatureFilePath($pdo, __DIR__ . '/..');
$hasSignature = (!empty($signaturePath) && file_exists($signaturePath));
$sigSrc = '';
if ($hasSignature) {
    $sigData = base64_encode(file_get_contents($signaturePath));
    $sigExt = strtolower(pathinfo($signaturePath, PATHINFO_EXTENSION));
    $sigMime = in_array($sigExt, ['jpg', 'jpeg']) ? 'image/jpeg' : 'image/png';
    $sigSrc = 'data:' . $sigMime . ';base64,' . $sigData;
}

// Resolve Circular Student Passport Photo
$studentPhotoPath = getCircularStudentPhotoFilePath($student['passport_photo'] ?? '', __DIR__ . '/..');
$hasStudentPhoto = (!empty($studentPhotoPath) && file_exists($studentPhotoPath));
$photoSrc = '';
if ($hasStudentPhoto) {
    $photoData = base64_encode(file_get_contents($studentPhotoPath));
    $photoExt = strtolower(pathinfo($studentPhotoPath, PATHINFO_EXTENSION));
    $photoMime = in_array($photoExt, ['jpg', 'jpeg']) ? 'image/jpeg' : 'image/png';
    $photoSrc = 'data:' . $photoMime . ';base64,' . $photoData;
}

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

$tuition    = (int)getSetting($pdo, "fees_{$clsKey}_tuition", 0);
$admission  = (int)getSetting($pdo, "fees_{$clsKey}_admission", 0);
$functional = (int)getSetting($pdo, "fees_{$clsKey}_functional", 0);

// Fallbacks if not configured individually
if ($tuition === 0 && $admission === 0 && $functional === 0) {
    if ($class === 'S1') {
        $tuition    = (int)getSetting($pdo, 'fees_s1_tuition', 550000);
        $admission  = (int)getSetting($pdo, 'fees_s1_admission', 100000);
        $functional = (int)getSetting($pdo, 'fees_s1_functional', 150000);
    } elseif ($class === 'S5') {
        $tuition    = (int)getSetting($pdo, 'fees_s5_tuition', 600000);
        $admission  = (int)getSetting($pdo, 'fees_s5_admission', 120000);
        $functional = (int)getSetting($pdo, 'fees_s5_functional', 180000);
    } else {
        $tuition    = (int)getSetting($pdo, 'fees_trans_tuition', 570000);
        $admission  = (int)getSetting($pdo, 'fees_trans_admission', 110000);
        $functional = (int)getSetting($pdo, 'fees_trans_functional', 170000);
    }
}
$totalFees = $tuition + $admission + $functional;

/* ================= PDF SETUP (STRICTLY 1-PAGE A4 STANDARD) ================= */
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetCreator($school_name);
$pdf->SetAuthor($school_name);
$pdf->SetTitle('Official Admission Offer - ' . $student_number);
$pdf->SetMargins(15, 10, 15); // Standard formal 15mm margins
$pdf->SetAutoPageBreak(false); // Guarantee strictly 1 single page
$pdf->AddPage();

/* ================= CENTERED OFFICIAL LETTERHEAD ================= */
$hasLogo = false;
$logoH = 20; // Prominent standard logo height
if (!empty($logo_source) && file_exists($logo_source)) {
    try {
        // Center the logo proportionally on 210mm page width: (210 - 36) / 2 = 87
        $pdf->Image($logo_source, 87, 8, 36, $logoH, '', '', '', false, 300, '', false, false, 0, 'CM');
        $hasLogo = true;
    } catch (Exception $e) {
        $hasLogo = false;
    }
}

$headerY = $hasLogo ? (8 + $logoH + 2) : 10;

$pdf->SetXY(15, $headerY);
$pdf->SetTextColor(15, 23, 42); // Formal black/slate
$pdf->SetFont('trebuchetms', 'B', 13);
$pdf->Cell(180, 5, strtoupper($school_name), 0, 1, 'C');

if (!empty($school_motto)) {
    $pdf->SetX(15);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->SetFont('trebuchetms', 'I', 8.5);
    $pdf->Cell(180, 3.5, '"' . $school_motto . '"', 0, 1, 'C');
}

$pdf->SetX(15);
$pdf->SetTextColor(71, 85, 105);
$pdf->SetFont('trebuchetms', '', 7.5);
$pdf->Cell(180, 3.3, $school_address . '   •   Tel: ' . $school_phone . '   •   Email: ' . $school_email, 0, 1, 'C');
$pdf->SetX(15);
$pdf->Cell(180, 3.2, 'Website: ' . $school_website, 0, 1, 'C');

// Official subtle divider line
$divY = $pdf->GetY() + 1;
$pdf->Line(15, $divY, 195, $divY, ['width' => 0.4, 'color' => [15, 23, 42]]);
$pdf->Line(15, $divY + 0.4, 195, $divY + 0.4, ['width' => 0.15, 'color' => [148, 163, 184]]);

/* ================= TITLE ================= */
$titleY = $divY + 2;
$pdf->SetXY(15, $titleY);
$pdf->SetTextColor(12, 74, 36); // Official forest green
$pdf->SetFont('trebuchetms', 'B', 9.5);
$titleText = 'OFFICIAL OFFER OF ADMISSION — ' . strtoupper($classNameLong);
if (($class === 'S5' || $class === 'S6') && !empty($studentCombinationShort)) {
    $titleText .= ' (' . strtoupper(htmlspecialchars($studentCombinationShort)) . ')';
}
$titleText .= ' | ' . $academic_year . ' ACADEMIC YEAR';
$pdf->Cell(180, 5, $titleText, 0, 1, 'C', false);

/* ================= LETTER BODY CONTENT ================= */
$contentY = $titleY + 5.8;
$pdf->SetXY(15, $contentY);

$studentFullName = strtoupper(trim($student['surname'] . ' ' . $student['firstname'] . ' ' . ($student['othernames'] ?? '')));

// Build Circular Photo HTML cell for top right box
$photoCellHtml = '';
if ($hasStudentPhoto && !empty($photoSrc)) {
    $photoCellHtml = '<td width="18%" align="center" style="vertical-align: middle; padding: 0px;">
        <img src="' . $photoSrc . '" width="68" height="68" />
    </td>';
} else {
    $photoCellHtml = '<td width="18%" align="center" style="vertical-align: middle; padding: 0px;">
        <span style="font-size: 6.5pt; color: #64748b; font-weight: bold; line-height: 1.3;">PASSPORT<br>PHOTO</span>
    </td>';
}

$detailsColWidth = '82%';

// Pre-compute Academic Result & Status Tags for Details Grid
$academicIndexTag = '';
if ($class === 'S1') {
    $academicIndexTag = '<strong>PLE Index:</strong> ' . htmlspecialchars($student['index_number']) . ' (Agg ' . htmlspecialchars($student['aggregates']) . ' ' . htmlspecialchars($student['division']) . ')';
} elseif ($class === 'S5' || $class === 'S6') {
    $resultTag = !empty($student['division']) ? htmlspecialchars($student['division']) : ('Agg ' . htmlspecialchars($student['aggregates']));
    if (!empty($student['uce_project_work'])) {
        $resultTag .= ' · Proj: ' . htmlspecialchars($student['uce_project_work']);
    }
    $academicIndexTag = '<strong>UCE Index:</strong> ' . htmlspecialchars($student['index_number']) . ' (' . $resultTag . ')';
} else {
    $academicIndexTag = '<strong>Former School:</strong> ' . htmlspecialchars($student['former_school']);
}

$statusOrCombTag = '';
if ($class === 'S5' || $class === 'S6') {
    $statusOrCombTag = '<strong>Combination:</strong> <span style="font-weight: bold; color: #0c4a24;">' . htmlspecialchars($studentCombinationDisplay) . '</span>';
} else {
    $statusOrCombTag = '<strong>Admission Status:</strong> <span style="font-weight: bold; color: #0c4a24;">OFFICIAL PROVISIONAL ADMISSION</span>';
}

$admissionParagraph = "On behalf of the Board of Governors and Administration of <strong>" . htmlspecialchars($school_name) . "</strong>, we are pleased to inform you that your child has been <strong>successfully admitted</strong> to <strong>" . $classNameLong . "</strong>";
if (($class === 'S5' || $class === 'S6') && !empty($studentCombinationDisplay)) {
    $admissionParagraph .= " offering the <strong>Subject Combination: " . htmlspecialchars($studentCombinationDisplay) . "</strong>";
}
$admissionParagraph .= " for the <strong>" . htmlspecialchars($academic_year) . " Academic Year</strong>. This provisional offer is awarded on academic merit and satisfactory record of conduct.";

// Build Reporting Instructions Content Flush to Margin
$instructionsBody = '<div style="font-size: 7.4pt; color: #0f172a; text-indent: 0; text-align: justify; margin: 0; padding: 0;"><strong style="color: #0c4a24;">Reporting Instructions:</strong> The admitted student is expected to report on <strong>' . (!empty($reportingDate) ? htmlspecialchars($reportingDate) : 'the official school reporting date') . '</strong>. <strong>Bring a physical copy of this offer letter on the reporting date</strong> alongside original UNEB result slips, 2 recent passport photographs, and all student requirements listed in the school prospectus.</div>';
if (!empty($schoolRequirements)) {
    $reqSummary = htmlspecialchars(substr($schoolRequirements, 0, 160));
    $instructionsBody .= '<div style="font-size: 6.8pt; color: #334155; margin-top: 2px; text-indent: 0; text-align: justify;"><strong>School Requirements:</strong> ' . $reqSummary . '... (Refer to attached School Prospectus for full checklist).</div>';
}
$instructionsBody .= '<div style="font-size: 6.8pt; color: #475569; margin-top: 2px; text-indent: 0; text-align: justify;"><strong>Official Enclosures:</strong> (1) School Rules & Regulations (2) Term Admissions Circular (3) Medical Examination Form.</div>';

// Build Student Details Card Rows Strictly Flush on Margin Ruler (No Leading Paragraph Spaces)
$detailsHtml = '<div style="font-family: \'trebuchetms\', \'Trebuchet MS\', Arial, sans-serif; font-size: 7.8pt; color: #0f172a; line-height: 1.35; text-align: justify; margin: 0; padding: 0;">' .
'<table cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; margin: 0 0 2px 0;">' .
    '<tr>' .
        '<td width="' . $detailsColWidth . '" style="vertical-align: top; padding-right: 5px;">' .
            '<table cellpadding="3" cellspacing="0" style="width: 100%; border: 1px solid #0c4a24; background-color: #ffffff; font-size: 7.5pt;">' .
                '<tr>' .
                    '<td width="55%" style="border-right: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1; background-color: #f8fafc;"><strong>Student Name:</strong> <span style="font-weight: bold; color: #0c4a24;">' . htmlspecialchars($studentFullName) . '</span></td>' .
                    '<td width="45%" style="border-bottom: 1px solid #cbd5e1; background-color: #f8fafc;"><strong>Student Number:</strong> <span style="font-weight: bold; font-family: courier, monospace; color: #0c4a24;">' . $student_number . '</span></td>' .
                '</tr>' .
                '<tr>' .
                    '<td width="55%" style="border-right: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1;"><strong>Sex:</strong> ' . ucfirst($student['sex']) . ' &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Class:</strong> ' . $classNameLong . '</td>' .
                    '<td width="45%" style="border-bottom: 1px solid #cbd5e1;">' . $academicIndexTag . '</td>' .
                '</tr>' .
                '<tr>' .
                    '<td width="55%" style="border-right: 1px solid #cbd5e1;"><strong>Reporting Date:</strong> <span style="font-weight: bold; color: #0f172a;">' . (!empty($reportingDate) ? htmlspecialchars($reportingDate) : 'Official Opening Date') . '</span></td>' .
                    '<td width="45%">' . $statusOrCombTag . '</td>' .
                '</tr>' .
            '</table>' .
        '</td>' .
        $photoCellHtml .
    '</tr>' .
'</table>' .
'<p style="margin: 2px 0 1.5px 0; padding: 0; font-size: 7.8pt; color: #0f172a; text-align: left; text-indent: 0;">Dear Parent / Guardian of <strong>' . htmlspecialchars($student['firstname'] . ' ' . $student['surname']) . '</strong>,</p>' .
'<p style="margin: 0 0 3px 0; padding: 0; font-size: 7.8pt; color: #1e293b; line-height: 1.35; text-align: justify; text-indent: 0;">' . trim($admissionParagraph) . '</p>' .
'<div style="font-size: 7.6pt; font-weight: bold; color: #0c4a24; margin: 0 0 1.5px 0; padding: 0; text-align: left; text-indent: 0;">TERM I TUITION & FEES MATRIX (UGX)</div>' .
'<table cellpadding="2.5" cellspacing="0" style="width: 100%; border: 1px solid #0c4a24; font-size: 7.4pt; border-collapse: collapse; margin: 0;">' .
    '<thead>' .
        '<tr style="background-color: #0c4a24; color: #ffffff; font-weight: bold;">' .
            '<th width="70%" style="border: 1px solid #0c4a24; color: #ffffff; text-align: left;">Fee Component / Description</th>' .
            '<th width="30%" align="right" style="border: 1px solid #0c4a24; color: #ffffff; text-align: right;">Amount (UGX)</th>' .
        '</tr>' .
    '</thead>' .
    '<tbody>' .
        '<tr>' .
            '<td style="border: 1px solid #cbd5e1; text-align: left;">Tuition & Instructional Fees (Termly)</td>' .
            '<td align="right" style="border: 1px solid #cbd5e1; font-family: helvetica; text-align: right;">' . number_format($tuition) . '</td>' .
        '</tr>' .
        '<tr>' .
            '<td style="border: 1px solid #cbd5e1; text-align: left;">Admission, Enrolment & Registration Fees</td>' .
            '<td align="right" style="border: 1px solid #cbd5e1; font-family: helvetica; text-align: right;">' . number_format($admission) . '</td>' .
        '</tr>' .
        '<tr>' .
            '<td style="border: 1px solid #cbd5e1; text-align: left;">Functional Fees (Development, ICT, Library, Medical & Identity Card)</td>' .
            '<td align="right" style="border: 1px solid #cbd5e1; font-family: helvetica; text-align: right;">' . number_format($functional) . '</td>' .
        '</tr>' .
        '<tr style="font-weight: bold; background-color: #ecfdf5; color: #064e3b;">' .
            '<td style="border: 1px solid #0c4a24; font-weight: bold; color: #064e3b; text-align: left;">Total Term I Fees Payable</td>' .
            '<td align="right" style="border: 1px solid #0c4a24; font-weight: bold; font-family: helvetica; color: #064e3b; text-align: right;">' . number_format($totalFees) . '</td>' .
        '</tr>' .
    '</tbody>' .
'</table>' .
'<div style="font-size: 6.6pt; color: #475569; font-style: italic; margin: 1px 0 3px 0; padding: 0; text-align: left; text-indent: 0;">* Note: The Admission commitment fee of UGX ' . number_format($admission) . ' secures the student\'s vacancy upon receipt and is deductible from total Term I tuition upon reporting.</div>' .
'<div style="border: 1px solid #16a34a; border-left: 3px solid #0c4a24; background-color: #f8fafc; padding: 4px 6px; margin: 0 0 3px 0; text-align: justify; line-height: 1.35; text-indent: 0;">' . $instructionsBody . '</div>' .
'</div>';

$pdf->writeHTML($detailsHtml, true, false, true, false, '');

/* ================= SIGNATURE & VERIFICATION SECTION ================= */
$sigY = $pdf->GetY() + 1.5;

// Build Bottom Verification and Signature Section
$sigHtml = '
<div style="font-family: \'trebuchetms\', \'Trebuchet MS\', Arial, sans-serif;">
<table cellpadding="0" cellspacing="0" style="width: 100%; border: none; font-size: 7.4pt;">
    <tr>
        <td width="48%" align="left" style="vertical-align: top; padding-top: 2px;">
            <table cellpadding="0" cellspacing="0" style="border: none;">
                <tr>
                    <td width="20mm" style="vertical-align: middle;">
                        <!-- QR code space: 16mm x 16mm -->
                    </td>
                    <td width="66mm" style="vertical-align: middle; font-size: 7pt; color: #475569; line-height: 1.35;">
                        <strong style="color: #0f172a; font-size: 7.4pt;">Official Document Verification</strong><br>
                        Issued by Admissions Registry.<br>
                        Scan QR code to verify authenticity.
                    </td>
                </tr>
            </table>
        </td>
        <td width="52%" align="center" style="vertical-align: top; text-align: center;">
            <div style="font-size: 7.4pt; color: #334155; margin-bottom: 2px;">Yours faithfully,</div>';

if ($hasSignature && !empty($sigSrc)) {
    $sigHtml .= '
            <div style="margin: 1px 0 2px 0;">
                <img src="' . $sigSrc . '" height="38" />
            </div>';
} else {
    $sigHtml .= '
            <div style="height: 6px; border-bottom: 1px dashed #334155; width: 85px; margin: 4px auto 3px auto;"></div>';
}

$sigHtml .= '
            <div style="font-weight: bold; color: #0f172a; font-size: 8.2pt; line-height: 1.2;">' . htmlspecialchars(strtoupper($head_teacher_name)) . '</div>
            <div style="color: #334155; font-size: 7.4pt;">' . htmlspecialchars($head_teacher_title) . '</div>
            <div style="color: #475569; font-size: 7pt;">' . htmlspecialchars($school_short_name) . '</div>
        </td>
    </tr>
</table>
</div>
';

$pdf->SetXY(15, $sigY);
$pdf->writeHTML($sigHtml, true, false, true, false, '');

/* ================= 2D QR CODE VERIFICATION ================= */
$qrStyle = [
    'border' => 0,
    'vpadding' => 0,
    'hpadding' => 0,
    'fgcolor' => [15, 23, 42],
    'bgcolor' => false
];
$qrLink = getBaseUrl($pdo) . '/admin/admission_list.php?class=' . urlencode($class);
$pdf->write2DBarcode($qrLink, 'QRCODE,H', 16, $sigY + 2.5, 16, 16, $qrStyle, 'N');

/* ================= FOOTER PROVISIONAL OFFER NOTICE ================= */
$pdf->SetXY(15, 278);
$pdf->SetFont('trebuchetms', 'I', 6.4);
$pdf->SetTextColor(71, 85, 105);
$pdf->MultiCell(180, 3.2, '* Please note: This provisional admission offer is subject to physical verification of original UNEB results slips and birth certificate upon arrival on the reporting date. Fees once paid are non-refundable.', 0, 'C', 0, 1);

/* ================= SECURITY FOOTER ================= */
date_default_timezone_set('Africa/Kampala'); // EAT
$generatedTime = date('d-M-Y H:i:s T');

$pdf->Line(15, 285.5, 195, 285.5, ['width' => 0.2, 'color' => [203, 213, 225]]);
$pdf->SetXY(15, 286.5);
$pdf->SetFont('trebuchetms', 'I', 6.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(180, 3.5, 'Generated: ' . $generatedTime . ' | Verification ID: ' . $student_number . ' | Official Admissions Document', 0, 1, 'C');

/* ================= OUTPUT ================= */
$pdf->Output('Admission_Offer_' . $student_number . '.pdf', 'I');
