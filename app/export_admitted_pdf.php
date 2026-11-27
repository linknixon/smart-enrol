<?php
// Suppress display errors in PDF output stream to prevent header corruption
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/auth.php';
require __DIR__ . '/../config/db.php';
require_once(__DIR__ . '/../form-one/tcpdf/tcpdf.php');

$currentRole = $_SESSION['admin_role'] ?? 'guest';
if (!hasPermission($pdo, $currentRole, 'can_export_data')) {
    die("Access Denied: You do not have permission to export PDF reports.");
}

/* ================= FILTER INPUTS ================= */
$filterClass = $_GET['class'] ?? 'ALL';
$filterYear  = $_GET['academic_year'] ?? 'all';
$filterSex   = $_GET['sex'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');

/* ================= SYSTEM CONFIGURATION ================= */
$school_name        = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_motto       = getSetting($pdo, 'school_motto', 'Education for Service');
$school_address     = getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro - Uganda');
$school_phone       = getSetting($pdo, 'school_phone', '+256 790 001 631');
$school_email       = getSetting($pdo, 'school_email', 'info@ncedges.com');
$school_website     = getSetting($pdo, 'school_website', 'www.mutolere.sc.ug');
$academic_year      = ($filterYear !== 'all') ? $filterYear : getAcademicYear($pdo, $filterClass !== 'ALL' ? $filterClass : null);

$head_teacher_name  = getSetting($pdo, 'head_teacher_name', 'MRS. PEACE RUZAZA');
$head_teacher_title = getSetting($pdo, 'head_teacher_title', 'Head Teacher');

$primaryHex = getSetting($pdo, 'theme_primary_color', '#0c4a24');
$accentHex  = getSetting($pdo, 'theme_accent_color', '#b58d2f');
$primaryRgb = hex2rgb($primaryHex, [12, 74, 36]);
$accentRgb  = hex2rgb($accentHex, [181, 141, 47]);

$logo_source = getSchoolLogoFilePath($pdo, __DIR__ . '/..');
$signaturePath = getSchoolSignatureFilePath($pdo, __DIR__ . '/..');
$hasSignature  = (!empty($signaturePath) && file_exists($signaturePath));

/* ================= QUERY DATA ================= */
$query = "SELECT * FROM applications WHERE application_status IN ('admitted', 'reported')";
$params = [];

if ($filterClass !== 'ALL') {
    if ($filterClass === 'OTHER') {
        $query .= " AND class_of_entry IN ('S2', 'S3', 'S4', 'S6')";
    } else {
        $query .= " AND class_of_entry = ?";
        $params[] = $filterClass;
    }
}

if ($filterSex !== 'all') {
    $query .= " AND sex = ?";
    $params[] = $filterSex;
}

$hasAcademicYearCol = false;
try {
    $chkCol = $pdo->query("SHOW COLUMNS FROM applications LIKE 'academic_year'");
    $hasAcademicYearCol = ($chkCol && $chkCol->rowCount() > 0);
} catch (Exception $e) {}

if ($hasAcademicYearCol && $filterYear !== 'all') {
    $query .= " AND academic_year = ?";
    $params[] = $filterYear;
}

if (!empty($searchQuery)) {
    $query .= " AND (firstname LIKE ? OR surname LIKE ? OR othernames LIKE ? OR index_number LIKE ? OR lin LIKE ?)";
    $term = "%$searchQuery%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY surname ASC, firstname ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalCount = count($records);

/* ================= TCPDF INITIALIZATION (PORTRAIT A4) ================= */
class OfficialAdmittedRegisterPDF extends TCPDF {
    public $school_name = '';
    public $school_short_name = '';
    public $primaryRgb = [12, 74, 36];

    public function Footer() {
        $this->SetY(-10);
        $this->SetFont('trebuchetms', 'I', 7.5);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 5, 'Official Admissions Registry Document  •  Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Portrait mode strictly
$pdf = new OfficialAdmittedRegisterPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->school_name = $school_name;
$pdf->school_short_name = $school_short_name;
$pdf->primaryRgb = $primaryRgb;

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetCreator($school_name);
$pdf->SetAuthor($school_name);
$pdf->SetTitle('Official Admitted Register - ' . $filterClass . ' (' . $academic_year . ')');
$pdf->SetMargins(12, 8, 12);
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();

/* ================= TOP DECORATIVE STRIP ================= */
$pdf->Rect(0, 0, 210, 2.5, 'F', [], $primaryRgb);
$pdf->Rect(0, 2.5, 210, 0.8, 'F', [], $accentRgb);

/* ================= CENTERED OFFICIAL LETTERHEAD ================= */
$hasLogo = false;
if (!empty($logo_source) && file_exists($logo_source)) {
    try {
        // Center of Portrait A4 (210mm) is 105mm -> X = 105 - 9 = 96
        $pdf->Image($logo_source, 96, 6, 18, 18, '', '', '', false, 300, '', false, false, 0, 'CM');
        $hasLogo = true;
    } catch (Exception $e) {
        $hasLogo = false;
    }
}

$headerY = $hasLogo ? 25.5 : 8;

$pdf->SetXY(12, $headerY);
$pdf->SetTextColor($primaryRgb[0], $primaryRgb[1], $primaryRgb[2]);
$pdf->SetFont('trebuchetms', 'B', 13.5);
$pdf->Cell(186, 5.5, strtoupper($school_name), 0, 1, 'C');

if (!empty($school_motto)) {
    $pdf->SetX(12);
    $pdf->SetTextColor($accentRgb[0], $accentRgb[1], $accentRgb[2]);
    $pdf->SetFont('trebuchetms', 'I', 8.5);
    $pdf->Cell(186, 3.8, '"' . $school_motto . '"', 0, 1, 'C');
}

$pdf->SetX(12);
$pdf->SetTextColor(71, 85, 105);
$pdf->SetFont('trebuchetms', '', 7.5);
$pdf->Cell(186, 3.6, $school_address . '   •   Tel: ' . $school_phone . '   •   Email: ' . $school_email, 0, 1, 'C');
$pdf->SetX(12);
$pdf->Cell(186, 3.4, 'Website: ' . $school_website, 0, 1, 'C');

// Divider Line
$divY = $pdf->GetY() + 1;
$pdf->Line(12, $divY, 198, $divY, ['width' => 0.4, 'color' => $primaryRgb]);
$pdf->Line(12, $divY + 0.6, 198, $divY + 0.6, ['width' => 0.15, 'color' => $accentRgb]);

/* ================= TITLE & METADATA BAR ================= */
$classTitle = ($filterClass === 'ALL') ? 'ALL ADMITTED CLASSES (S1 - S6)' : (($filterClass === 'OTHER') ? 'TRANSFERS (S2, S3, S4, S6)' : 'SENIOR ' . substr($filterClass, 1) . ' (' . $filterClass . ')');

$titleHtml = '
<div style="font-family: \'trebuchetms\', \'Trebuchet MS\', Arial, sans-serif;">
<table cellpadding="3" cellspacing="0" style="width: 100%; margin-top: 2px;">
    <tr>
        <td width="65%" style="font-size: 10pt; font-weight: bold; color: ' . $primaryHex . ';">
            OFFICIAL ADMITTED STUDENTS REGISTER
            <span style="font-size: 8pt; font-weight: normal; color: #475569;"><br>' . htmlspecialchars($classTitle) . '  •  ACADEMIC INTAKE YEAR ' . htmlspecialchars($academic_year) . '</span>
        </td>
        <td width="35%" align="right" style="font-size: 7.2pt; color: #64748b; line-height: 1.35;">
            <strong>Generated On:</strong> ' . date('d/m/Y H:i:s') . '<br>
            <strong>Prepared By:</strong> ' . htmlspecialchars($_SESSION['admin_username'] ?? 'Admissions Registry') . '<br>
            <strong>Total Admitted:</strong> <span style="font-weight: bold; color: ' . $primaryHex . ';">' . $totalCount . ' Candidates</span>
        </td>
    </tr>
</table>

<!-- Summary Metrics Strip -->
<table cellpadding="3" cellspacing="0" style="width: 100%; border: 1px solid #cbd5e1; background-color: #f8fafc; font-size: 7.2pt; margin-top: 3px; margin-bottom: 5px;">
    <tr>
        <td width="30%" style="border-right: 1px solid #e2e8f0;">
            <strong style="color: #475569;">CLASS LEVEL:</strong> <span style="font-weight: bold; color: #0f172a;">' . htmlspecialchars($filterClass) . '</span>
        </td>
        <td width="30%" style="border-right: 1px solid #e2e8f0;">
            <strong style="color: #475569;">GENDER:</strong> <span style="font-weight: bold; color: #0f172a;">' . strtoupper(htmlspecialchars($filterSex)) . '</span>
        </td>
        <td width="40%">
            <strong style="color: #475569;">STATUS:</strong> 
            <span style="color: #0c4a24; font-weight: bold;">OFFICIALLY ADMITTED (' . $totalCount . ')</span>
        </td>
    </tr>
</table>
</div>
';

$metaY = $divY + 2.5;
$pdf->SetXY(12, $metaY);
$pdf->writeHTML($titleHtml, true, false, true, false, '');

/* ================= TABLE OF ADMITTED STUDENTS ================= */
// Exact requested fields: STUDENT NAME, GENDER, CLASS, INDEX NUMBER, AGGS
$tableHtml = '
<div style="font-family: \'trebuchetms\', \'Trebuchet MS\', Arial, sans-serif;">
<table cellpadding="3.5" cellspacing="0" style="width: 100%; border: 1px solid #cbd5e1; font-size: 7.5pt; border-collapse: collapse;">
    <thead>
        <tr style="background-color: ' . $primaryHex . '; color: #ffffff; font-weight: bold; text-align: center;">
            <th width="5%" style="border: 1px solid ' . $primaryHex . ';">#</th>
            <th width="16%" style="border: 1px solid ' . $primaryHex . ';">ADMISSION NO.</th>
            <th width="33%" align="left" style="border: 1px solid ' . $primaryHex . ';">STUDENT NAME</th>
            <th width="10%" style="border: 1px solid ' . $primaryHex . ';">GENDER</th>
            <th width="10%" style="border: 1px solid ' . $primaryHex . ';">CLASS</th>
            <th width="15%" style="border: 1px solid ' . $primaryHex . ';">INDEX NUMBER</th>
            <th width="11%" style="border: 1px solid ' . $primaryHex . ';">RESULT / AGGS</th>
        </tr>
    </thead>
    <tbody>';

$idx = 1;
foreach ($records as $r) {
    $fullName = trim($r['surname'] . ' ' . $r['firstname'] . ' ' . ($r['othernames'] ?? ''));
    $bg = ($idx % 2 === 0) ? '#f8fafc' : '#ffffff';
    $perfScore = in_array($r['class_of_entry'], ['S5', 'S6']) ? getS5OverallResult($r) : ($r['aggregates'] ?? 'N/A');

    $tableHtml .= '
        <tr style="background-color: ' . $bg . ';">
            <td width="5%" align="center" style="border: 1px solid #e2e8f0; font-weight: bold; color: #64748b;">' . $idx . '</td>
            <td width="16%" align="center" style="border: 1px solid #e2e8f0; font-family: monospace; font-weight: bold; color: #334155;">' . htmlspecialchars(getAdmissionNumber($r)) . '</td>
            <td width="33%" style="border: 1px solid #e2e8f0; font-weight: bold; color: #0f172a;">' . htmlspecialchars($fullName) . '</td>
            <td width="10%" align="center" style="border: 1px solid #e2e8f0; text-transform: capitalize;">' . htmlspecialchars($r['sex']) . '</td>
            <td width="10%" align="center" style="border: 1px solid #e2e8f0; font-weight: bold; color: ' . $primaryHex . ';">' . htmlspecialchars($r['class_of_entry']) . '</td>
            <td width="15%" align="center" style="border: 1px solid #e2e8f0; font-family: monospace;">' . htmlspecialchars($r['index_number'] ?? 'N/A') . '</td>
            <td width="11%" align="center" style="border: 1px solid #e2e8f0; font-weight: bold; color: ' . $accentHex . ';">' . htmlspecialchars($perfScore) . '</td>
        </tr>';
    $idx++;
}

if ($totalCount === 0) {
    $tableHtml .= '
        <tr>
            <td colspan="7" align="center" style="padding: 18px; color: #94a3b8; font-style: italic; border: 1px solid #e2e8f0;">
                No admitted candidates found matching the selected criteria.
            </td>
        </tr>';
}

$tableHtml .= '
    </tbody>
</table>
</div>
';

$pdf->writeHTML($tableHtml, true, false, true, false, '');

/* ================= SIGN-OFF & STAMP SECTION ================= */
if ($pdf->GetY() > 245) {
    $pdf->AddPage();
}

$signHtml = '
<br><br>
<div style="font-family: \'trebuchetms\', \'Trebuchet MS\', Arial, sans-serif; font-size: 7.8pt;">
<table cellpadding="4" cellspacing="0" style="width: 100%; border-top: 1px dashed #cbd5e1; padding-top: 10px;">
    <tr>
        <td width="50%" align="left" style="color: #334155; vertical-align: top;">
            <strong>Official Admissions Register Approved By:</strong><br>';

if ($hasSignature) {
    $signHtml .= '
            <div style="margin: 2px 0 2px 0;">
                <img src="' . htmlspecialchars($signaturePath) . '" height="38" />
            </div>';
} else {
    $signHtml .= '
            <div style="height: 8px; border-bottom: 1px dashed #334155; width: 110px; margin: 16px 0 4px 0;"></div>';
}

$signHtml .= '
            <strong>' . htmlspecialchars($head_teacher_name) . '</strong><br>
            <span style="color: #64748b;">' . htmlspecialchars($head_teacher_title) . '  •  ' . htmlspecialchars($school_short_name) . '</span>
        </td>
        <td width="50%" align="right" style="color: #334155; vertical-align: top;">
            <strong>Compiled & Certified By:</strong><br><br>
            <strong style="color: ' . $primaryHex . '; font-size: 8.2pt;">OFFICE OF ADMISSIONS REGISTRY</strong><br>
            <span style="color: #64748b;">Admissions Committee  •  ' . htmlspecialchars($school_short_name) . '</span><br>
            <span style="font-size: 7pt; color: #0284c7; font-weight: bold;">Status: System Verified  •  Date: ' . date('d/m/Y') . '</span>
        </td>
    </tr>
</table>
</div>
';

$pdf->writeHTML($signHtml, true, false, true, false, '');

// Clean any buffered output and output PDF
if (ob_get_length()) ob_end_clean();
$filename = 'Admitted_Register_' . $filterClass . '_' . date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'I');
exit;
