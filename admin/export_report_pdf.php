<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/auth.php';
require __DIR__ . '/../config/db.php';
require_once(__DIR__ . '/../form-one/tcpdf/tcpdf.php');

$currentRole = $_SESSION['admin_role'] ?? 'guest';
if (!hasPermission($pdo, $currentRole, 'can_export_data')) {
    die("Access Denied: You do not have permission to export PDF reports.");
}

/* ================= FILTER INPUTS ================= */
$filterClass  = $_GET['class'] ?? 'ALL';
$filterYear   = $_GET['academic_year'] ?? 'all';
$filterStatus = $_GET['status'] ?? 'all';
$filterSex    = $_GET['sex'] ?? 'all';
$searchQuery  = trim($_GET['search'] ?? '');

/* ================= SYSTEM CONFIGURATION ================= */
$school_name        = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_motto       = getSetting($pdo, 'school_motto', 'Education for Service');
$school_address     = getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro - Uganda');
$school_phone       = getSetting($pdo, 'school_phone', '+256 790 001 631');
$school_email       = getSetting($pdo, 'school_email', 'info@ncedges.com');
$school_website     = getSetting($pdo, 'school_website', 'www.mutolere.sc.ug');
$academic_year      = ($filterYear !== 'all') ? $filterYear : getAcademicYear($pdo, $filterClass !== 'ALL' ? $filterClass : null);

$head_teacher_name  = getSetting($pdo, 'head_teacher_name', 'MR. MBONYE CHARLES');
$head_teacher_title = getSetting($pdo, 'head_teacher_title', 'Head Teacher');

$primaryHex = getSetting($pdo, 'theme_primary_color', '#0c4a24');
$accentHex  = getSetting($pdo, 'theme_accent_color', '#b58d2f');
$primaryRgb = hex2rgb($primaryHex, [12, 74, 36]);
$accentRgb  = hex2rgb($accentHex, [181, 141, 47]);

$logo_source = getSchoolLogoFilePath($pdo, __DIR__ . '/..');
$signaturePath = getSchoolSignatureFilePath($pdo, __DIR__ . '/..');
$hasSignature  = (!empty($signaturePath) && file_exists($signaturePath));

/* ================= QUERY DATA ================= */
$query = "SELECT * FROM applications";
$where = [];
$params = [];

if ($filterClass !== 'ALL') {
    if ($filterClass === 'OTHER') {
        $where[] = "class_of_entry IN ('S2', 'S3', 'S4', 'S6')";
    } else {
        $where[] = "class_of_entry = ?";
        $params[] = $filterClass;
    }
}

if ($filterStatus !== 'all') {
    $where[] = "application_status = ?";
    $params[] = $filterStatus;
}

if ($filterSex !== 'all') {
    $where[] = "sex = ?";
    $params[] = $filterSex;
}

if ($filterYear !== 'all') {
    $where[] = "academic_year = ?";
    $params[] = $filterYear;
}

if (!empty($searchQuery)) {
    $where[] = "(firstname LIKE ? OR surname LIKE ? OR index_number LIKE ? OR lin LIKE ? OR parent_name LIKE ? OR former_school LIKE ?)";
    $term = "%$searchQuery%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (count($where) > 0) {
    $query .= " WHERE " . implode(" AND ", $where);
}
$query .= " ORDER BY surname ASC, firstname ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalCount = count($records);
$admittedCount = 0;
$pendingCount = 0;
$rejectedCount = 0;
foreach ($records as $r) {
    $st = strtolower($r['application_status'] ?? 'pending');
    if ($st === 'admitted') $admittedCount++;
    elseif ($st === 'rejected') $rejectedCount++;
    else $pendingCount++;
}

/* ================= TCPDF INITIALIZATION (LANDSCAPE A4) ================= */
class OfficialReportPDF extends TCPDF {
    public $school_name = '';
    public $school_short_name = '';
    public $primaryRgb = [12, 74, 36];

    public function Footer() {
        $this->SetY(-10);
        $this->SetFont('trebuchetms', 'I', 7);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 5, 'Official Admissions Registry Document  •  Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new OfficialReportPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->school_name = $school_name;
$pdf->school_short_name = $school_short_name;
$pdf->primaryRgb = $primaryRgb;

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetCreator($school_name);
$pdf->SetAuthor($school_name);
$pdf->SetTitle('Official Admission Report - ' . $filterClass . ' (' . $academic_year . ')');
$pdf->SetMargins(12, 8, 12);
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();

/* ================= TOP DECORATIVE STRIP ================= */
$pdf->Rect(0, 0, 297, 2.5, 'F', [], $primaryRgb);
$pdf->Rect(0, 2.5, 297, 0.8, 'F', [], $accentRgb);

/* ================= CENTERED OFFICIAL LETTERHEAD ================= */
$hasLogo = false;
if (!empty($logo_source) && file_exists($logo_source)) {
    try {
        // Center of Landscape A4 (297mm) is 148.5mm -> X = 148.5 - 9 = 139.5
        $pdf->Image($logo_source, 139.5, 6, 18, 18, '', '', '', false, 300, '', false, false, 0, 'CM');
        $hasLogo = true;
    } catch (Exception $e) {
        $hasLogo = false;
    }
}

$headerY = $hasLogo ? 25.5 : 8;

$pdf->SetXY(12, $headerY);
$pdf->SetTextColor($primaryRgb[0], $primaryRgb[1], $primaryRgb[2]);
$pdf->SetFont('trebuchetms', 'B', 14);
$pdf->Cell(273, 5.5, strtoupper($school_name), 0, 1, 'C');

if (!empty($school_motto)) {
    $pdf->SetX(12);
    $pdf->SetTextColor($accentRgb[0], $accentRgb[1], $accentRgb[2]);
    $pdf->SetFont('trebuchetms', 'I', 8.5);
    $pdf->Cell(273, 3.8, '"' . $school_motto . '"', 0, 1, 'C');
}

$pdf->SetX(12);
$pdf->SetTextColor(71, 85, 105);
$pdf->SetFont('trebuchetms', '', 7.5);
$pdf->Cell(273, 3.6, $school_address . '   •   Tel: ' . $school_phone . '   •   Email: ' . $school_email . '   •   Website: ' . $school_website, 0, 1, 'C');

// Divider Line
$divY = $pdf->GetY() + 1;
$pdf->Line(12, $divY, 285, $divY, ['width' => 0.4, 'color' => $primaryRgb]);
$pdf->Line(12, $divY + 0.6, 285, $divY + 0.6, ['width' => 0.15, 'color' => $accentRgb]);

/* ================= TITLE & METADATA BAR ================= */
$classTitle = ($filterClass === 'ALL') ? 'ALL CLASSES (S1 - S6)' : (($filterClass === 'OTHER') ? 'TRANSFERS (S2, S3, S4, S6)' : 'SENIOR ' . substr($filterClass, 1) . ' (' . $filterClass . ')');

$titleHtml = '
<div style="font-family: \'trebuchetms\', \'Trebuchet MS\', Arial, sans-serif;">
<table cellpadding="3" cellspacing="0" style="width: 100%; margin-top: 2px;">
    <tr>
        <td width="70%" style="font-size: 10.5pt; font-weight: bold; color: ' . $primaryHex . ';">
            OFFICIAL ADMISSIONS REGISTER & CANDIDATE REPORT
            <span style="font-size: 8.5pt; font-weight: normal; color: #475569;"><br>' . htmlspecialchars($classTitle) . '  •  ACADEMIC INTAKE YEAR ' . htmlspecialchars($academic_year) . '</span>
        </td>
        <td width="30%" align="right" style="font-size: 7.2pt; color: #64748b; line-height: 1.35;">
            <strong>Generated On:</strong> ' . date('d/m/Y H:i:s') . '<br>
            <strong>Prepared By:</strong> ' . htmlspecialchars($_SESSION['admin_username'] ?? 'Admissions Registry') . '<br>
            <strong>Total Filtered:</strong> <span style="font-weight: bold; color: ' . $primaryHex . ';">' . $totalCount . ' Candidates</span>
        </td>
    </tr>
</table>

<!-- Summary Metrics Strip -->
<table cellpadding="3" cellspacing="0" style="width: 100%; border: 1px solid #cbd5e1; background-color: #f8fafc; font-size: 7.2pt; margin-top: 3px; margin-bottom: 5px;">
    <tr>
        <td width="25%" style="border-right: 1px solid #e2e8f0;">
            <strong style="color: #475569;">CLASS FILTER:</strong> <span style="font-weight: bold; color: #0f172a;">' . htmlspecialchars($filterClass) . '</span>
        </td>
        <td width="25%" style="border-right: 1px solid #e2e8f0;">
            <strong style="color: #475569;">STATUS FILTER:</strong> <span style="font-weight: bold; color: #0f172a;">' . strtoupper(htmlspecialchars($filterStatus)) . '</span>
        </td>
        <td width="25%" style="border-right: 1px solid #e2e8f0;">
            <strong style="color: #475569;">GENDER:</strong> <span style="font-weight: bold; color: #0f172a;">' . strtoupper(htmlspecialchars($filterSex)) . '</span>
        </td>
        <td width="25%">
            <strong style="color: #475569;">BREAKDOWN:</strong> 
            <span style="color: #0c4a24; font-weight: bold;">' . $admittedCount . ' Admitted</span> | 
            <span style="color: #d97706; font-weight: bold;">' . $pendingCount . ' Pending</span> | 
            <span style="color: #dc2626; font-weight: bold;">' . $rejectedCount . ' Rejected</span>
        </td>
    </tr>
</table>
</div>
';

$metaY = $divY + 2.5;
$pdf->SetXY(12, $metaY);
$pdf->writeHTML($titleHtml, true, false, true, false, '');

/* ================= TABLE OF CANDIDATES ================= */
$tableHtml = '
<div style="font-family: \'trebuchetms\', \'Trebuchet MS\', Arial, sans-serif;">
<table cellpadding="3" cellspacing="0" style="width: 100%; border: 1px solid #cbd5e1; font-size: 6.8pt; border-collapse: collapse;">
    <thead>
        <tr style="background-color: ' . $primaryHex . '; color: #ffffff; font-weight: bold; text-align: center;">
            <th width="3%" style="border: 1px solid ' . $primaryHex . ';">#</th>
            <th width="11%" style="border: 1px solid ' . $primaryHex . ';">Admission No</th>
            <th width="16%" align="left" style="border: 1px solid ' . $primaryHex . ';">Student Full Name</th>
            <th width="4%" style="border: 1px solid ' . $primaryHex . ';">Sex</th>
            <th width="4%" style="border: 1px solid ' . $primaryHex . ';">Class</th>
            <th width="8%" style="border: 1px solid ' . $primaryHex . ';">Index Number</th>
            <th width="4%" style="border: 1px solid ' . $primaryHex . ';">Aggs</th>
            <th width="14%" align="left" style="border: 1px solid ' . $primaryHex . ';">Academic Record</th>
            <th width="16%" align="left" style="border: 1px solid ' . $primaryHex . ';">Parent / Contact</th>
            <th width="7%" style="border: 1px solid ' . $primaryHex . ';">Status</th>
            <th width="6.5%" style="border: 1px solid ' . $primaryHex . ';">Admitted On</th>
            <th width="6.5%" align="left" style="border: 1px solid ' . $primaryHex . ';">Admitted By</th>
        </tr>
    </thead>
    <tbody>';

$idx = 1;
foreach ($records as $r) {
    $fullName = trim($r['surname'] . ' ' . $r['firstname'] . ' ' . ($r['othernames'] ?? ''));
    $st = strtolower($r['application_status'] ?? 'pending');
    $statusColor = '#d97706';
    $statusLabel = 'Pending';
    if ($st === 'reported') {
        $statusColor = '#7e22ce';
        $statusLabel = 'Enrolled';
    } elseif ($st === 'admitted') {
        $statusColor = '#0c4a24';
        $statusLabel = 'Admitted';
    } elseif ($st === 'rejected') {
        $statusColor = '#dc2626';
        $statusLabel = 'Rejected';
    }

    $admittedDate = '—';
    if ($st === 'reported') {
        $admittedDate = !empty($r['reported_at']) ? date('d/m/Y', strtotime($r['reported_at'])) : (!empty($r['admitted_at']) ? date('d/m/Y', strtotime($r['admitted_at'])) : '—');
    } elseif ($st === 'admitted') {
        if (!empty($r['admitted_at'])) {
            $admittedDate = date('d/m/Y', strtotime($r['admitted_at']));
        } elseif (!empty($r['created_at'])) {
            $admittedDate = date('d/m/Y', strtotime($r['created_at']));
        }
    }

    $academicInfo = htmlspecialchars($r['former_school'] ?? 'N/A');
    if ($r['class_of_entry'] === 'S1') {
        $academicInfo .= '<br><span style="color:#64748b;">PLE Div: ' . htmlspecialchars($r['division'] ?? 'N/A') . '</span>';
    } elseif ($r['class_of_entry'] === 'S5' || $r['class_of_entry'] === 'S6') {
        $academicInfo .= '<br><span style="color:' . $accentHex . '; font-weight:bold;">' . htmlspecialchars($r['combination'] ?? 'N/A') . ' (' . htmlspecialchars($r['division'] ?? '') . ')</span>';
    } else {
        $academicInfo .= '<br><span style="color:#64748b;">Last: ' . htmlspecialchars($r['division'] ?? 'N/A') . '</span>';
    }

    $parentInfo = '<strong>' . htmlspecialchars($r['parent_name'] ?? 'N/A') . '</strong><br><span style="color:#64748b;">' . htmlspecialchars($r['parent_phone'] ?? '') . '</span>';
    if (!empty($r['parent_email'])) {
        $parentInfo .= '<br><span style="color:#475569; font-size:6pt;">' . htmlspecialchars($r['parent_email']) . '</span>';
    }

    $admittedBy = !empty($r['reported_by']) ? htmlspecialchars($r['reported_by']) : (!empty($r['admitted_by']) ? htmlspecialchars($r['admitted_by']) : (($st === 'admitted' || $st === 'reported') ? 'Admissions Office' : '—'));

    $bg = ($idx % 2 === 0) ? '#f8fafc' : '#ffffff';

    $tableHtml .= '
        <tr style="background-color: ' . $bg . ';">
            <td width="3%" align="center" style="border: 1px solid #e2e8f0; font-weight: bold; color: #64748b;">' . $idx . '</td>
            <td width="11%" align="center" style="border: 1px solid #e2e8f0; font-family: monospace; font-weight: bold; color: #334155;">' . htmlspecialchars(getAdmissionNumber($r)) . '</td>
            <td width="16%" style="border: 1px solid #e2e8f0; font-weight: bold; color: #0f172a;">' . htmlspecialchars($fullName) . '</td>
            <td width="4%" align="center" style="border: 1px solid #e2e8f0;">' . htmlspecialchars($r['sex']) . '</td>
            <td width="4%" align="center" style="border: 1px solid #e2e8f0; font-weight: bold;">' . htmlspecialchars($r['class_of_entry']) . '</td>
            <td width="8%" align="center" style="border: 1px solid #e2e8f0; font-family: monospace;">' . htmlspecialchars($r['index_number'] ?? 'N/A') . '</td>
            <td width="4%" align="center" style="border: 1px solid #e2e8f0; font-weight: bold; color: ' . $primaryHex . ';">' . htmlspecialchars($r['aggregates'] ?? 'N/A') . '</td>
            <td width="14%" style="border: 1px solid #e2e8f0;">' . $academicInfo . '</td>
            <td width="16%" style="border: 1px solid #e2e8f0;">' . $parentInfo . '</td>
            <td width="7%" align="center" style="border: 1px solid #e2e8f0; font-weight: bold; color: ' . $statusColor . ';">' . $statusLabel . '</td>
            <td width="6.5%" align="center" style="border: 1px solid #e2e8f0; font-weight: bold; color: #334155;">' . $admittedDate . '</td>
            <td width="6.5%" style="border: 1px solid #e2e8f0; color: #334155;">' . $admittedBy . '</td>
        </tr>';
    $idx++;
}

if ($totalCount === 0) {
    $tableHtml .= '
        <tr>
            <td colspan="11" align="center" style="padding: 15px; border: 1px solid #e2e8f0; color: #94a3b8; font-style: italic;">
                No applicant records found matching the specified report criteria.
            </td>
        </tr>';
}

$tableHtml .= '
    </tbody>
</table>

<!-- Sign-off Block -->
<br><br>
<table cellpadding="4" cellspacing="0" style="width: 100%; border: none; font-size: 7.2pt;">
    <tr>
        <td width="50%" align="left" style="vertical-align: top;">
            <span style="font-weight: bold; color: #475569;">OFFICIAL REPORT APPROVED BY:</span><br>';

if ($hasSignature) {
    $tableHtml .= '
            <div style="margin: 2px 0 2px 0;">
                <img src="' . htmlspecialchars($signaturePath) . '" height="38" />
            </div>';
} else {
    $tableHtml .= '
            <div style="height: 8px; border-bottom: 1px dashed #334155; width: 120px; margin: 16px 0 4px 0;"></div>';
}

$tableHtml .= '
            <div>
                <strong style="color: ' . $primaryHex . '; font-size: 8pt;">' . htmlspecialchars(strtoupper($head_teacher_name)) . '</strong><br>
                <span>' . htmlspecialchars($head_teacher_title) . '  •  ' . htmlspecialchars($school_short_name) . '</span><br>
                <span style="font-size: 7.5pt; color: #64748b;">Date: ____________________</span>
            </div>
        </td>
        <td width="50%" align="right" style="vertical-align: top;">
            <span style="font-weight: bold; color: #475569;">COMPILED & CERTIFIED BY:</span><br><br>
            <div style="margin-left: auto;">
                <strong style="color: ' . $primaryHex . '; font-size: 8pt;">OFFICE OF ADMISSIONS REGISTRY</strong><br>
                <span>Admissions Committee  •  ' . htmlspecialchars($school_short_name) . '</span><br>
                <span style="font-size: 6.8pt; color: #0284c7; font-weight: bold;">Status: System Verified  •  Date: ' . date('d/m/Y') . '</span>
            </div>
        </td>
    </tr>
</table>
</div>
';

$pdf->writeHTML($tableHtml, true, false, true, false, '');

/* ================= OUTPUT ================= */
$pdf->Output('Admission_Report_' . $filterClass . '_' . date('Ymd_His') . '.pdf', 'I');
