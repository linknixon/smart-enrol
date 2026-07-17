<?php
require '../config/db.php';
require 'fpdf.php';
require 'qrlib.php'; // for QR code generation

class PDF extends FPDF {
    public $logoUrl = '';
    public $pageWidth;

function Header() {
    // Logo top-left
    if($this->logoUrl) {
        $this->Image($this->logoUrl, 10, 6, 40); // width increased
    }

    // School name next to logo
    $this->SetFont('Helvetica','B',16);
    $this->SetXY(55, 10);
    $this->Cell(0,10,'Our Lady of Good Counsel S.S.S Gayaza',0,1,'L');

    // Subtitle / Report title - underlined
    $this->SetFont('Helvetica','BU',14); // B = bold, U = underline
    $this->SetX(55);
    $this->Cell(0,10,'Senior One Admitted Students',0,1,'L');

    $this->Ln(5);

    // Column headers
    $this->SetFont('Helvetica','B',10);
    $this->Cell(40,8,'Name',1);
    $this->Cell(15,8,'Age',1);
    $this->Cell(30,8,'Index No',1);
    $this->Cell(20,8,'Agg',1);
    $this->Cell(25,8,'Division',1);
    $this->Ln();
    $this->pageWidth = $this->GetPageWidth();
}
    function Footer() {
        $this->SetY(-30);
        $this->SetFont('Helvetica','I',8);
        $this->Cell(0,5,'Generated on: '.date('Y-m-d H:i:s'),0,1,'L');
        $this->Cell(0,5,'Page '.$this->PageNo().'/{nb}',0,1,'C');

        // QR Code at bottom-right
        $qrFile = tempnam(sys_get_temp_dir(), 'qr_').'.png';
        QRcode::png('https://projects.ncedges.com', $qrFile, QR_ECLEVEL_L, 2);
        $this->Image($qrFile, $this->pageWidth-30, $this->GetY(), 20, 20);
        unlink($qrFile);
    }
}

/* Create PDF */
$pdf = new PDF();
$pdf->logoUrl = 'https://www.ncedges.com/wp-content/uploads/2024/12/Nova_logo-removebg-preview.png';
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Helvetica','',10);

/* Fetch admitted students */
$stmt = $pdo->query("SELECT * FROM s1_applications WHERE application_status = 'admitted' ORDER BY surname ASC");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $pdf->Cell(40,8,$row['surname'].' '.$row['firstname'],1);
    $pdf->Cell(15,8,$row['age'],1);
    $pdf->Cell(30,8,$row['index_number'],1);
    $pdf->Cell(20,8,$row['aggregates'],1);
    $pdf->Cell(25,8,$row['division'],1);
    $pdf->Ln();
}

/* Output PDF */
$pdf->Output('D','admitted_s1_students.pdf');
exit;
