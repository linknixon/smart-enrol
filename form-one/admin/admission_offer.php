<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

/* ================= STUDENT NUMBER =================
   Format: 26 (year) + 01 (S1) + 011 (school) + 01 (student)
*/
$year        = '26';
$class       = '01';
$school_code = '011';

$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM s1_applications
    WHERE application_status = 'admitted' AND id <= ?
");
$countStmt->execute([$id]);
$serial = str_pad($countStmt->fetchColumn(), 2, '0', STR_PAD_LEFT);

$student_number = $year.$class.$school_code.$serial;

/* ================= PDF SETUP ================= */
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

/* ================= LOGO ================= */
$logo = 'https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png';
$pdf->Image($logo, 90, 8, 25, '', '', '', false, 300);
$pdf->Ln(22);

/* ================= SCHOOL HEADER ================= */
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 6, 'ST. PAUL\'S MUTOLERE SECONDARY SCHOOL', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 4.5, 'P.O. Box 12, Kisoro – Uganda | Tel: +256 790 001 631', 0, 1, 'C');
$pdf->Cell(0, 4.5, 'Email: info@ncedges.com | Website: www.mutolere.sc.ug', 0, 1, 'C');

$pdf->Ln(2);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(4);

/* ================= TITLE ================= */
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'OFFICIAL OFFER OF ADMISSION – SENIOR ONE (S1) 2026', 0, 1, 'C');
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
    <td width="50%"><strong>PLE Index No:</strong> '.$student['index_number'].'</td>
</tr>
<tr>
    <td width="100%"><strong>Performance:</strong> Aggregates '.$student['aggregates'].' ('.$student['division'].')</td>
</tr>
</table>

<p style="margin-top: 15px;">Dear Parent / Guardian of <strong>'.$student['firstname'].' '.$student['surname'].'</strong>,</p>

<p>
We are pleased to inform you that your child has been <strong>successfully admitted</strong> to <strong>Senior One (S1)</strong> at St. Paul\'s Mutolere Secondary School for the <strong>2026 Academic Year</strong>. This offer is based on their excellent performance in the Primary Leaving Examinations.
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
            <td align="right">550,000</td>
        </tr>
        <tr>
            <td>Admission & Registration Fees</td>
            <td align="right">100,000</td>
        </tr>
        <tr>
            <td>Functional Fees (Development, Library, Medical, Identity Card)</td>
            <td align="right">150,000</td>
        </tr>
        <tr style="font-weight: bold; background-color: #f1f5f9;">
            <td>Total Term I Fees Payable</td>
            <td align="right">800,000</td>
        </tr>
    </tbody>
</table>

<p style="font-size: 8.5px; color: #334155; font-style: italic; font-weight: bold; line-height: 1.3;">
* Note: The Admission fee of UGX 100,000 is part of the Tuition Fees and should be paid upon confirmation. When the student reports to school, this amount will be deducted from the total tuition.
</p>

<p>
You are required to report to school on the official opening date with this admission letter, UPE results slip, passport photo, and all requested requirements in the student prospectus.
</p>

<p style="margin-bottom: 2px;">Yours faithfully,</p>
<table style="width: 100%; border: none; margin-top: 5px;">
    <tr>
        <td width="60%">
            <br>
            <span style="font-weight: bold; color: #0c4a24;">MR. MBONYE CHARLES</span><br>
            Head Teacher<br>
            St. Paul\'s Mutolere SS
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

$qrLink = 'https://apply.mutolere.sc.ug/form-one/admin/admission_list.php';
$qrSize = 25; // size in mm

// Bottom-left coordinates (opposite side of stamp)
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
