<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../config/db.php';
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

/* ================= STUDENT NUMBER ================= */
$year        = '26';
$school_code = '011';
$class = $student['class_of_entry'];

$classMap = [
    'S1' => '01',
    'S2' => '02',
    'S3' => '03',
    'S4' => '04',
    'S5' => '05',
    'S6' => '06',
];
$classCode = $classMap[$class] ?? '00';

$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM applications
    WHERE application_status = 'admitted' AND class_of_entry = ? AND id <= ?
");
$countStmt->execute([$class, $id]);
$serial = str_pad($countStmt->fetchColumn(), 2, '0', STR_PAD_LEFT);

$student_number = $year.$classCode.$school_code.$serial;

/* ================= SYSTEM CONFIGURATION ================= */
$school_name    = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_logo    = getSetting($pdo, 'school_logo', 'https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png');
$school_address = getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro – Uganda');
$school_phone   = getSetting($pdo, 'school_phone', '+256 790 001 631');
$school_email   = getSetting($pdo, 'school_email', 'info@ncedges.com');
$school_website = getSetting($pdo, 'school_website', 'www.mutolere.sc.ug');

$logo_source = $school_logo;
if (strpos($logo_source, 'http') === false) {
    $logo_source = '../' . $logo_source;
}

/* ================= FEE STRUCTURE SETTINGS ================= */
$classNameLong = "Senior " . substr($class, 1) . " (" . $class . ")";
$tuition = 0;
$admission = 0;
$functional = 0;

if ($class === 'S1') {
    $tuition    = (int)getSetting($pdo, 'fees_s1_tuition', 550000);
    $admission  = (int)getSetting($pdo, 'fees_s1_admission', 100000);
    $functional = (int)getSetting($pdo, 'fees_s1_functional', 150000);
} elseif ($class === 'S5') {
    $tuition    = (int)getSetting($pdo, 'fees_s5_tuition', 600000);
    $admission  = (int)getSetting($pdo, 'fees_s5_admission', 120000);
    $functional = (int)getSetting($pdo, 'fees_s5_functional', 180000);
} else {
    // S2, S3, S4, S6 (Transfer other classes)
    $tuition    = (int)getSetting($pdo, 'fees_trans_tuition', 570000);
    $admission  = (int)getSetting($pdo, 'fees_trans_admission', 110000);
    $functional = (int)getSetting($pdo, 'fees_trans_functional', 170000);
}
$totalFees = $tuition + $admission + $functional;

/* ================= PDF SETUP ================= */
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

/* ================= LOGO ================= */
$pdf->Image($logo_source, 90, 8, 25, '', '', '', false, 300);
$pdf->Ln(22);

/* ================= SCHOOL HEADER ================= */
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 6, strtoupper($school_name), 0, 1, 'C');

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 4.5, $school_address . ' | Tel: ' . $school_phone, 0, 1, 'C');
$pdf->Cell(0, 4.5, 'Email: ' . $school_email . ' | Website: ' . $school_website, 0, 1, 'C');

$pdf->Ln(2);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(4);

/* ================= TITLE ================= */
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'OFFICIAL OFFER OF ADMISSION – SENIOR ' . substr($class, 1) . ' (' . $class . ') 2026', 0, 1, 'C');
$pdf->Ln(2);

/* ================= LETTER BODY ================= */
$pdf->SetFont('helvetica', '', 9.5);

$html = '
<table cellpadding="4" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
<tr>
    <td width="50%"><strong>Student Name:</strong> '.$student['surname'].' '.$student['firstname'].'</td>
    <td width="50%"><strong>Student Number:</strong> <span style="color: #0c4a24; font-weight: bold;">'.$student_number.'</span></td>
</tr>
<tr>
    <td width="50%"><strong>Gender:</strong> '.ucfirst($student['sex']).'</td>
    <td width="50%"><strong>Class of Entry:</strong> '.$classNameLong.'</td>
</tr>';

if ($class === 'S1') {
    $html .= '
    <tr>
        <td width="50%"><strong>PLE Index No:</strong> '.$student['index_number'].'</td>
        <td width="50%"><strong>PLE Performance:</strong> Aggregates '.$student['aggregates'].' ('.$student['division'].')</td>
    </tr>';
} elseif ($class === 'S5') {
    $html .= '
    <tr>
        <td width="50%"><strong>UCE Index No:</strong> '.$student['index_number'].'</td>
        <td width="50%"><strong>UCE Performance:</strong> Aggregates '.$student['aggregates'].' ('.$student['division'].')</td>
    </tr>
    <tr>
        <td width="100%"><strong>A-Level combination:</strong> <span style="font-weight: bold; color: #b58d2f;">'.$student['combination'].'</span></td>
    </tr>';
} else {
    // S2, S3, S4, S6
    $html .= '
    <tr>
        <td width="50%"><strong>Former School:</strong> '.$student['former_school'].'</td>
        <td width="50%"><strong>Last Class Attended:</strong> '.$student['division'].'</td>
    </tr>';
}

$html .= '
</table>

<p style="margin-top: 15px;">Dear Parent / Guardian of <strong>'.$student['firstname'].' '.$student['surname'].'</strong>,</p>

<p>
We are pleased to inform you that your child has been <strong>successfully admitted</strong> to <strong>'.$classNameLong.'</strong> at <strong>'.$school_name.'</strong> for the <strong>2026 Academic Year</strong>. This admission offer is based on their academic track record.
</p>

<p style="font-weight: bold; color: #0c4a24; margin-top: 10px; margin-bottom: 5px;">Term I Fees Structure & Payment Schedule</p>
<table border="1" cellpadding="5" style="border-collapse: collapse; border: 1px solid #cbd5e1; width: 100%;">
    <thead>
        <tr style="background-color: #0c4a24; color: #ffffff; font-weight: bold;">
            <th width="65%">Item / Description</th>
            <th width="35%" align="right">Amount (UGX)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Tuition Fees (Termly)</td>
            <td align="right">'.number_format($tuition).'</td>
        </tr>
        <tr>
            <td>Admission & Registration Fees</td>
            <td align="right">'.number_format($admission).'</td>
        </tr>
        <tr>
            <td>Functional Fees (Development, Library, Medical, Identity Card)</td>
            <td align="right">'.number_format($functional).'</td>
        </tr>
        <tr style="font-weight: bold; background-color: #f1f5f9;">
            <td>Total Term I Fees Payable</td>
            <td align="right">'.number_format($totalFees).'</td>
        </tr>
    </tbody>
</table>

<p style="font-size: 8.5px; color: #334155; font-style: italic; font-weight: bold; line-height: 1.3;">
* Note: The Admission fee of UGX '.number_format($admission).' is part of the Tuition Fees and should be paid upon confirmation. When the student reports to school, this amount will be deducted from the total tuition.
</p>

<p>
You are required to report to school on the official opening date with this admission letter, academic record slips, passport photo, and all requested requirements in the student prospectus.
</p>

<p style="margin-bottom: 2px;">Yours faithfully,</p>
<table style="width: 100%; border: none; margin-top: 5px;">
    <tr>
        <td width="60%">
            <br>
            <span style="font-weight: bold; color: #0c4a24;">MR. MBONYE CHARLES</span><br>
            Head Teacher<br>
            '.$school_name.'
        </td>
        <td width="40%" align="right" style="vertical-align: bottom;">
            <br><br>
            _____________________________<br>
            <em>Stamp & Date</em>
        </td>
    </tr>
</table>
';

$pdf->writeHTML($html, true, false, true, false, '');

/*QR Code   */
$style = array(
    'border' => 0,
    'vpadding' => 'auto',
    'hpadding' => 'auto',
    'fgcolor' => array(0,74,36),
    'bgcolor' => false
);

$qrLink = 'https://apply.mutolere.sc.ug/admin/admission_list.php?class=' . $class;
$qrSize = 25; // size in mm

// Bottom-left coordinates
$pdf->SetAutoPageBreak(false);
$pdf->write2DBarcode($qrLink, 'QRCODE,H', 15, $pdf->getPageHeight() - 35, $qrSize, $qrSize, $style, 'N');
/*QR Code  */

/* ================= FOOTER ================= */
date_default_timezone_set('Africa/Nairobi'); // EAT
$generatedTime = date('d-m-Y H:i:s T');

$pdf->SetY(-15);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 4, "Generated: $generatedTime | Official Admission Letter", 0, 1, 'R');

/* ================= OUTPUT ================= */
$pdf->Output('Admission_Offer_'.$student_number.'.pdf', 'I');
