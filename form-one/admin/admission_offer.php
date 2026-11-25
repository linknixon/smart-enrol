<?php
// Suppress display errors in PDF output stream to prevent header corruption
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

require '../config/db.php';
require_once('../tcpdf/tcpdf.php');

/* ================= VALIDATION ================= */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid request');
}

$id = (int)$_GET['id'];

/* ================= FETCH STUDENT ================= */
$stmt = $pdo->prepare("
    SELECT * FROM s1_applications
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
$academic_year      = getAcademicYear($pdo, 'S1');
$year               = getAcademicYearShort($pdo, 'S1');
$school_code        = getSetting($pdo, 'school_code', '011');
$head_teacher_name  = getSetting($pdo, 'head_teacher_name', 'MR. MBONYE CHARLES');
$head_teacher_title = getSetting($pdo, 'head_teacher_title', 'Head Teacher');

$primaryHex = getSetting($pdo, 'theme_primary_color', '#0c4a24');
$accentHex  = getSetting($pdo, 'theme_accent_color', '#b58d2f');
$primaryRgb = hex2rgb($primaryHex, [12, 74, 36]);
$accentRgb  = hex2rgb($accentHex, [181, 141, 47]);

$logo_source = getSchoolLogoFilePath($pdo, __DIR__ . '/../..');
$signaturePath = getSchoolSignatureFilePath($pdo, __DIR__ . '/../..');
$hasSignature  = (!empty($signaturePath) && file_exists($signaturePath));

/* ================= STUDENT NUMBER ================= */
$classCode = '01';

$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM s1_applications
    WHERE application_status = 'admitted' AND id <= ?
");
$countStmt->execute([$id]);
$serial = str_pad($countStmt->fetchColumn(), 2, '0', STR_PAD_LEFT);

$student_number = $year . $classCode . $school_code . $serial;

/* ================= FEE STRUCTURE SETTINGS ================= */
$classNameLong = "Senior One (S1)";
$tuition    = (int)getSetting($pdo, 'fees_s1_tuition', 550000);
$admission  = (int)getSetting($pdo, 'fees_s1_admission', 100000);
$functional = (int)getSetting($pdo, 'fees_s1_functional', 150000);
$totalFees  = $tuition + $admission + $functional;

/* ================= PDF SETUP (STRICTLY 1-PAGE) ================= */
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetCreator($school_name);
$pdf->SetAuthor($school_name);
$pdf->SetTitle('Official Admission Offer - ' . $student_number);
$pdf->SetMargins(14, 8, 14);
$pdf->SetAutoPageBreak(false); // Guarantee strictly 1 single page
$pdf->AddPage();

/* ================= TOP DECORATIVE STRIP ================= */
$pdf->Rect(0, 0, 210, 2.5, 'F', [], $primaryRgb);
$pdf->Rect(0, 2.5, 210, 0.8, 'F', [], $accentRgb);

/* ================= LETTERHEAD ================= */
$hasLogo = false;
if (!empty($logo_source) && (file_exists($logo_source) || filter_var($logo_source, FILTER_VALIDATE_URL))) {
    try {
        $pdf->Image($logo_source, 14, 6.5, 20, 20, '', '', '', false, 300, '', false, false, 0, 'CM');
        $hasLogo = true;
    } catch (Exception $e) {
        $hasLogo = false;
    }
}

$headerStartX = $hasLogo ? 37 : 14;
$headerWidth  = $hasLogo ? 159 : 182;

$pdf->SetXY($headerStartX, 6.5);
$pdf->SetTextColor($primaryRgb[0], $primaryRgb[1], $primaryRgb[2]);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell($headerWidth, 5.5, strtoupper($school_name), 0, 1, 'L');

if (!empty($school_motto)) {
    $pdf->SetX($headerStartX);
    $pdf->SetTextColor($accentRgb[0], $accentRgb[1], $accentRgb[2]);
    $pdf->SetFont('helvetica', 'BI', 8);
    $pdf->Cell($headerWidth, 4, '"' . $school_motto . '"', 0, 1, 'L');
}

$pdf->SetX($headerStartX);
$pdf->SetTextColor(71, 85, 105);
$pdf->SetFont('helvetica', '', 7.5);
$pdf->Cell($headerWidth, 3.8, $school_address . '   |   Tel: ' . $school_phone . '   |   Email: ' . $school_email, 0, 1, 'L');
$pdf->SetX($headerStartX);
$pdf->Cell($headerWidth, 3.8, 'Website: ' . $school_website, 0, 1, 'L');

// Divider rules
$pdf->Line(14, 27, 196, 27, ['width' => 0.4, 'color' => $primaryRgb]);
$pdf->Line(14, 27.6, 196, 27.6, ['width' => 0.15, 'color' => $accentRgb]);

/* ================= TITLE BANNER ================= */
$pdf->SetXY(14, 30);
$pdf->SetFillColor(248, 250, 252);
$pdf->SetDrawColor(203, 213, 225);
$pdf->SetTextColor($primaryRgb[0], $primaryRgb[1], $primaryRgb[2]);
$pdf->SetFont('helvetica', 'B', 10);
$titleText = 'OFFICIAL OFFER OF ADMISSION - SENIOR ONE (S1) | ' . $academic_year . ' ACADEMIC YEAR';
$pdf->Cell(182, 6.5, $titleText, 1, 1, 'C', true);

/* ================= LETTER BODY CONTENT ================= */
$pdf->SetXY(14, 38.5);

$studentFullName = strtoupper(trim($student['surname'] . ' ' . $student['firstname'] . ' ' . ($student['othernames'] ?? '')));

$detailsHtml = '
<table cellpadding="3" cellspacing="0" style="width: 100%; border: 1px solid #cbd5e1; background-color: #f8fafc; font-size: 8pt;">
    <tr>
        <td width="50%" style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
            <strong>Student Name:</strong> <span style="font-weight: bold; color: #0f172a;">' . htmlspecialchars($studentFullName) . '</span>
        </td>
        <td width="50%" style="border-bottom: 1px solid #e2e8f0;">
            <strong>Student Number:</strong> <span style="font-weight: bold; color: ' . $primaryHex . ';">' . $student_number . '</span>
        </td>
    </tr>
    <tr>
        <td width="50%" style="border-right: 1px solid #e2e8f0;">
            <strong>Gender:</strong> ' . ucfirst($student['sex']) . ' &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Class:</strong> ' . $classNameLong . '
        </td>
        <td width="50%">
            <strong>PLE Index No:</strong> ' . htmlspecialchars($student['index_number']) . ' &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Result:</strong> Agg ' . htmlspecialchars($student['aggregates']) . ' (' . htmlspecialchars($student['division']) . ')
        </td>
    </tr>
</table>

<p style="margin-top: 6px; margin-bottom: 3px; font-size: 8pt; color: #0f172a;">
    Dear <strong>' . htmlspecialchars(ucwords(strtolower(trim($student['firstname'] . ' ' . $student['surname'] . ' ' . ($student['othernames'] ?? ''))))) . '</strong>,
</p>
<p style="margin-top: 0px; margin-bottom: 6px; font-size: 8pt; color: #334155; line-height: 1.35;">
    We are pleased to inform you that you have been <strong>successfully admitted</strong> to <strong>' . $classNameLong . '</strong> at <strong>' . htmlspecialchars($school_name) . '</strong> for the <strong>' . htmlspecialchars($academic_year) . ' Academic Year</strong>. This admission offer is awarded on the basis of excellent academic performance in the Primary Leaving Examinations.
</p>

<div style="font-size: 8.5pt; font-weight: bold; color: ' . $primaryHex . '; margin-bottom: 3px;">
    TERM I TUITION & FEES SCHEDULE
</div>
<table cellpadding="3.5" cellspacing="0" style="width: 100%; border: 1px solid #cbd5e1; font-size: 8pt; border-collapse: collapse;">
    <thead>
        <tr style="background-color: ' . $primaryHex . '; color: #ffffff; font-weight: bold;">
            <th width="70%" style="border: 1px solid ' . $primaryHex . ';">Fee Component / Description</th>
            <th width="30%" align="right" style="border: 1px solid ' . $primaryHex . ';">Amount (UGX)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="border: 1px solid #e2e8f0;">Tuition Fees (Termly)</td>
            <td align="right" style="border: 1px solid #e2e8f0; font-family: helvetica;">' . number_format($tuition) . '</td>
        </tr>
        <tr style="background-color: #f8fafc;">
            <td style="border: 1px solid #e2e8f0;">Admission & Registration Fees</td>
            <td align="right" style="border: 1px solid #e2e8f0; font-family: helvetica;">' . number_format($admission) . '</td>
        </tr>
        <tr>
            <td style="border: 1px solid #e2e8f0;">Functional Fees (Development, Library, ICT, ID Card, Medical)</td>
            <td align="right" style="border: 1px solid #e2e8f0; font-family: helvetica;">' . number_format($functional) . '</td>
        </tr>
        <tr style="background-color: #ecfdf5; font-weight: bold; color: ' . $primaryHex . ';">
            <td style="border: 1px solid #cbd5e1; font-weight: bold;">Total Term I Fees Payable</td>
            <td align="right" style="border: 1px solid #cbd5e1; font-weight: bold; font-family: helvetica;">' . number_format($totalFees) . '</td>
        </tr>
    </tbody>
</table>
<div style="font-size: 7.5pt; color: #475569; font-style: italic; margin-top: 2.5px; margin-bottom: 5px;">
    * Note: The Admission fee of UGX ' . number_format($admission) . ' is an initial commitment fee payable upon confirmation to secure the vacancy, and is deductible from the total Term I Tuition Fees upon reporting.
</div>

<p style="margin-top: 4px; margin-bottom: 6px; font-size: 8pt; color: #334155; line-height: 1.35;">
    <strong>Reporting Instructions:</strong> You are required to report to school on the official opening date with this original offer letter, PLE result slip, 2 passport photos, and all requirements specified in the school prospectus. <span style="color:#b45309; font-weight:bold;">Bring a copy of this letter on reporting date.</span>
</p>

<p style="margin-bottom: 2px; font-size: 8pt;">Yours faithfully,</p>
<table cellpadding="2" cellspacing="0" style="width: 100%; border: none; font-size: 7.5pt;">
    <tr>
        <td width="28%" align="left" style="vertical-align: middle;">
            <!-- QR Barcode rendered via TCPDF -->
        </td>
        <td width="36%" align="center" style="vertical-align: middle; height: 48px;">
            <span style="font-size: 7.5pt; color: #64748b; font-weight: bold; line-height: 1.5;">
                Date: ________________
            </span>
        </td>
        <td width="36%" align="right" style="vertical-align: bottom;">';

if ($hasSignature) {
    $detailsHtml .= '
            <div style="margin: 0 0 2px 0;">
                <img src="' . $signaturePath . '" style="height: 11mm; max-width: 40mm; object-fit: contain;" />
            </div>';
}

$detailsHtml .= '
            <div style="font-weight: bold; color: ' . $primaryHex . '; font-size: 9pt;">' . htmlspecialchars(strtoupper($head_teacher_name)) . '</div>
            <div style="color: #334155; font-size: 8pt;">' . htmlspecialchars($head_teacher_title) . '</div>
            <div style="color: #64748b; font-size: 7.5pt;">' . htmlspecialchars($school_short_name) . '</div>
        </td>
    </tr>
</table>
';

$pdf->writeHTML($detailsHtml, true, false, true, false, '');

$currentY = $pdf->GetY();

/* ================= 2D QR CODE VERIFICATION ================= */
$qrStyle = [
    'border' => 0,
    'vpadding' => 0,
    'hpadding' => 0,
    'fgcolor' => $primaryRgb,
    'bgcolor' => false
];
$qrLink = getBaseUrl($pdo) . '/form-one/admin/admission_list.php';
$pdf->write2DBarcode($qrLink, 'QRCODE,H', 18, $currentY - 24, 20, 20, $qrStyle, 'N');

$pdf->SetXY(14, $currentY - 3.5);
$pdf->SetFont('helvetica', 'I', 6.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(28, 3, 'Scan to Verify Authenticity', 0, 0, 'C');

/* ================= SECURITY FOOTER ================= */
date_default_timezone_set('Africa/Kampala'); // EAT
$generatedTime = date('d-m-Y H:i:s T');

$pdf->Line(14, 285, 196, 285, ['width' => 0.2, 'color' => [203, 213, 225]]);
$pdf->SetXY(14, 286);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->SetTextColor(148, 163, 184);
$pdf->Cell(182, 3.5, 'Generated: ' . $generatedTime . ' | Verification ID: ' . $student_number . ' | Official Admissions Document', 0, 1, 'C');

/* ================= OUTPUT ================= */
if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output('Admission_Offer_' . $student_number . '.pdf', 'I');
exit;
