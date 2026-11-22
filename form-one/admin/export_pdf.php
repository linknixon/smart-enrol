<?php
require '../config/db.php';
require 'fpdf.php';
require 'qrlib.php'; // QR code generation

$school_name = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$logoUrl     = getSchoolLogoFilePath($pdo, __DIR__ . '/../../');
$qrUrl       = getBaseUrl($pdo) . '/form-one/admin/admission_list.php';

class PDF extends FPDF {

    public $schoolName = '';
    public $logoUrl = '';
    public $qrUrl = '';
    public $pageWidth;

    function Header() {

        $this->pageWidth = $this->GetPageWidth();

        /* ===== LOGO (Centered & Official Size) ===== */
        if($this->logoUrl && file_exists($this->logoUrl)) {
            $logoWidth = 22;
            $logoX = ($this->pageWidth - $logoWidth) / 2;
            $this->Image($this->logoUrl, $logoX, 10, $logoWidth);
            $this->Ln(25);
        } else {
            $this->Ln(5);
        }

        /* ===== SCHOOL NAME ===== */
        $this->SetFont('Helvetica','B',15);
        $this->Cell(0,8, strtoupper($this->schoolName),0,1,'C');

        /* ===== SUBTITLE ===== */
        $this->SetFont('Helvetica','BU',12);
        $this->Cell(0,7,'Senior One Admitted Students Register',0,1,'C');

        $this->Ln(4);

        /* ===== PROFESSIONAL LINE ===== */
        $this->Line(15, $this->GetY(), $this->pageWidth - 15, $this->GetY());
        $this->Ln(6);

        /* ===== TABLE HEADER (Centered) ===== */
        $this->SetFont('Helvetica','B',10);

        $tableWidth = 145;
        $startX = ($this->pageWidth - $tableWidth) / 2;
        $this->SetX($startX);

        $this->Cell(45,8,'Name',1);
        $this->Cell(15,8,'Age',1);
        $this->Cell(35,8,'Index No',1);
        $this->Cell(25,8,'Aggregates',1);
        $this->Cell(25,8,'Division',1);
        $this->Ln();
    }

    function Footer() {

        $this->SetY(-30);
        $this->SetFont('Helvetica','I',8);

        // Generated date
        $this->Cell(0,5,'Generated on: '.date('Y-m-d H:i:s'),0,1,'L');

        // Page number
        $this->Cell(0,5,'Page '.$this->PageNo().'/{nb}',0,1,'C');

        // QR Code (Bottom Right)
        if (!empty($this->qrUrl)) {
            $qrFile = tempnam(sys_get_temp_dir(), 'qr_').'.png';
            QRcode::png(
                $this->qrUrl,
                $qrFile,
                QR_ECLEVEL_L,
                3
            );

            $qrSize = 20;
            $qrX = $this->pageWidth - 30;
            $qrY = $this->GetY();

            if (file_exists($qrFile)) {
                $this->Image($qrFile, $qrX, $qrY, $qrSize, $qrSize);
                @unlink($qrFile);
            }
        }
    }
}

/* ===== CREATE PDF ===== */
$pdf = new PDF();
$pdf->schoolName = $school_name;
$pdf->logoUrl    = $logoUrl;
$pdf->qrUrl      = $qrUrl;
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Helvetica','',9.5);

/* ===== FETCH ADMITTED STUDENTS ===== */
$stmt = $pdo->query("
    SELECT * FROM s1_applications 
    WHERE application_status = 'admitted' 
    ORDER BY surname ASC
");

/* ===== TABLE ROWS (Centered Properly) ===== */
$tableWidth = 145;
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $startX = ($pdf->GetPageWidth() - $tableWidth) / 2;
    $pdf->SetX($startX);

    $pdf->Cell(45,8,$row['surname'].' '.$row['firstname'],1);
    $pdf->Cell(15,8,$row['age'],1);
    $pdf->Cell(35,8,$row['index_number'],1);
    $pdf->Cell(25,8,$row['aggregates'],1);
    $pdf->Cell(25,8,$row['division'],1);
    $pdf->Ln();
}

/* ===== OUTPUT PDF ===== */
$pdf->Output('D','Senior_One_Admitted_Students.pdf');
exit;
