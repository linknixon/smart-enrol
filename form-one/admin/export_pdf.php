<?php
require '../config/db.php';
require 'fpdf.php';
require 'qrlib.php'; // QR code generation

class PDF extends FPDF {

    public $logoUrl = '';
    public $pageWidth;

    function Header() {

        $this->pageWidth = $this->GetPageWidth();

        /* ===== LOGO (Centered & Official Size) ===== */
        if($this->logoUrl) {
            $logoWidth = 25; // reduced size
            $logoX = ($this->pageWidth - $logoWidth) / 2;
            $this->Image($this->logoUrl, $logoX, 10, $logoWidth);
            $this->Ln(28); // space below logo
        }

        /* ===== SCHOOL NAME ===== */
        $this->SetFont('Helvetica','B',16);
        $this->Cell(0,8,'MUTOLERE SECONDARY SCHOOL',0,1,'C');

        /* ===== SUBTITLE ===== */
        $this->SetFont('Helvetica','BU',14);
        $this->Cell(0,8,'Senior One Admitted Students',0,1,'C');

        $this->Ln(5);

        /* ===== PROFESSIONAL LINE ===== */
        $this->Line(15, $this->GetY(), $this->pageWidth - 15, $this->GetY());
        $this->Ln(6);

        /* ===== TABLE HEADER (Centered) ===== */
        $this->SetFont('Helvetica','B',10);

        $tableWidth = 130;
        $startX = ($this->pageWidth - $tableWidth) / 2;
        $this->SetX($startX);

        $this->Cell(40,8,'Name',1);
        $this->Cell(15,8,'Age',1);
        $this->Cell(30,8,'Index No',1);
        $this->Cell(20,8,'Aggregate',1);
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
        $qrFile = tempnam(sys_get_temp_dir(), 'qr_').'.png';
        QRcode::png(
            'https://apply.mutolere.sc.ug/form-one/admin/admission_list.php',
            $qrFile,
            QR_ECLEVEL_L,
            3
        );

        $qrSize = 20;
        $qrX = $this->pageWidth - 30;
        $qrY = $this->GetY();

        $this->Image($qrFile, $qrX, $qrY, $qrSize, $qrSize);
        unlink($qrFile);
    }
}

/* ===== CREATE PDF ===== */
$pdf = new PDF();
$pdf->logoUrl = 'https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png';
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Helvetica','',10);

/* ===== FETCH ADMITTED STUDENTS ===== */
$stmt = $pdo->query("
    SELECT * FROM s1_applications 
    WHERE application_status = 'admitted' 
    ORDER BY surname ASC
");

/* ===== TABLE ROWS (Centered Properly) ===== */
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

    $tableWidth = 130;
    $startX = ($pdf->GetPageWidth() - $tableWidth) / 2;
    $pdf->SetX($startX);

    $pdf->Cell(40,8,$row['surname'].' '.$row['firstname'],1);
    $pdf->Cell(15,8,$row['age'],1);
    $pdf->Cell(30,8,$row['index_number'],1);
    $pdf->Cell(20,8,$row['aggregates'],1);
    $pdf->Cell(25,8,$row['division'],1);
    $pdf->Ln();
}

/* ===== OUTPUT PDF ===== */
$pdf->Output('D','admitted_s1_students.pdf');
exit;
