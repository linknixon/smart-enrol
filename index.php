<?php
session_start();
require 'config/db.php';

// Fetch dynamic configurations
$school_name = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_logo = getSetting($pdo, 'school_logo', 'https://www.ncedges.com/wp-content/uploads/2026/02/mutosp-logo.png');
$school_address = getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro – Uganda');
$school_phone = getSetting($pdo, 'school_phone', '+256 790 001 631');
$school_email = getSetting($pdo, 'school_email', 'info@ncedges.com');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($school_name) ?> – Admissions</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="css/style.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>
<body>

<div class="container">

    <!-- Logo -->
    <div class="text-center">
        <img src="<?= htmlspecialchars($school_logo) ?>" alt="School Logo" class="logo" style="max-height: 120px; object-fit: contain;">
    </div>

    <h1 class="portal-title text-center"><?= htmlspecialchars($school_name) ?> – Admissions Portal</h1>
    <p class="text-muted text-center mb-5">Welcome to the Online Admissions Portal. Please review the admission requirements below before starting your application.</p>

    <div class="portal-layout">
        
        <!-- Left Column: Admission Requirements -->
        <div class="requirements-card">
            <h2><i class="fa-solid fa-file-shield text-success"></i> Admission Requirements</h2>
            
            <h4><i class="fa-solid fa-chevron-right text-success"></i> Senior One (O-Level) Entry Requirements</h4>
            <ul>
                <li><strong>Academic Performance:</strong> Applicants must have sat the UNEB Primary Leaving Examinations (PLE) and obtained a Grade of Division 1 or Division 2 (PLE Aggregates range from 4 to 24).</li>
                <li><strong>UNEB PLE Index Number:</strong> A valid PLE index number registered with UNEB is mandatory.</li>
                <li><strong>Documents for Attachment:</strong>
                    <ul>
                        <li>Scanned copy or clean photograph of the UNEB PLE Result Slip (PDF/JPEG/PNG format).</li>
                        <li>Recent student passport size photograph (white background preferred).</li>
                    </ul>
                </li>
                <li><strong>Parent/Guardian Information:</strong> Valid National Identification Number (NIN) issued by NIRA Uganda, a primary email address, and phone contact.</li>
            </ul>

            <h4><i class="fa-solid fa-chevron-right text-success"></i> Senior Five (A-Level) Entry Requirements</h4>
            <ul>
                <li><strong>Academic Performance:</strong> Applicants must have sat the Uganda Certificate of Education (UCE) and obtained at least a Division 3 or better. Aggregates range between 8 and 54 (computed from the best 8 subjects).</li>
                <li><strong>Approved Combinations:</strong> Applicants must select from the approved combinations:
                    <ul>
                        <li><strong>Sciences:</strong> PCM, PCB, BCM, PEM, MEG, HEM.</li>
                        <li><strong>Arts:</strong> HEG, HEL, HLD, LEG, GEA.</li>
                    </ul>
                </li>
                <li><strong>Documents for Attachment:</strong>
                    <ul>
                        <li>Scanned copy or clean photograph of the UNEB UCE Result Slip (PDF/JPEG/PNG format).</li>
                        <li>Recent student passport size photograph.</li>
                    </ul>
                </li>
                <li><strong>Parent/Guardian Information:</strong> Valid NIRA National ID Number (NIN), contact email, and active telephone numbers.</li>
            </ul>

            <h4><i class="fa-solid fa-circle-info text-success"></i> Step-by-Step Application Procedure</h4>
            <ol>
                <li><strong>Select Level:</strong> Click the "Go to S1" or "Go to S5" button on the right-hand panel.</li>
                <li><strong>Biodata (Step 1):</strong> Complete Student name, Gender, DOB, Religion, and upload student passport photo. User cannot advance until Step 1 details are complete.</li>
                <li><strong>Academic UNEB Info (Step 2):</strong> Provide former school, UNEB index, UNEB aggregates/grades, and upload the UNEB result slip.</li>
                <li><strong>Parent/Guardian Details (Step 3):</strong> Fill in parent details (Name, Phone, NIRA NIN, Address, Email), verify, and click "Submit Application".</li>
                <li><strong>Email Confirmation:</strong> Upon successful submission, check the parent email inbox for a confirmation letter.</li>
            </ol>
        </div>

        <!-- Right Column: Card links & Admitted list -->
        <div>
            <div class="d-flex flex-column gap-4 align-items-center">
                <!-- Senior One -->
                <div class="card w-100">
                    <div>
                        <h3>Senior One Admissions</h3>
                        <p>Begin online application for Senior One entry.</p>
                    </div>
                    <a href="apply.php?class=S1" class="btn-submit">Go to S1 <i class="fa-solid fa-arrow-right"></i></a>
                </div>

                <!-- Senior Five -->
                <div class="card w-100">
                    <div>
                        <h3>Senior Five Admissions</h3>
                        <p>Begin online application for A-Level (S5) entry.</p>
                    </div>
                    <a href="apply.php?class=S5" class="btn-submit">Go to S5 <i class="fa-solid fa-arrow-right"></i></a>
                </div>

                <!-- Other Classes -->
                <div class="card w-100">
                    <div>
                        <h3>Other Classes Admissions</h3>
                        <p>Apply for S2, S3, S4 or S6 transfer entry.</p>
                    </div>
                    <a href="apply.php?class=other" class="btn-submit bg-success">Go to Transfers <i class="fa-solid fa-arrow-right"></i></a>
                </div>

                <!-- Admin Dashboard -->
                <div class="card w-100">
                    <div>
                        <h3>Admin Dashboard</h3>
                        <p>Administrative access to manage entries.</p>
                    </div>
                    <a href="admin/login.php" class="btn-submit btn-admin">Login Now <i class="fa-solid fa-lock"></i></a>
                </div>
            </div>

            <!-- Admission Lists Legend Frame -->
            <fieldset class="legend-frame mt-4">
                <legend><i class="fa-solid fa-graduation-cap"></i> Admission Lists</legend>
                <a href="admin/admission_list.php?class=S1" target="_blank" class="admission-list-btn w-100 mb-2 text-center text-decoration-none d-block py-2 font-semibold"><i class="fa-solid fa-file-invoice"></i> S1 Admission List</a>
                <a href="admin/admission_list.php?class=S5" target="_blank" class="admission-list-btn w-100 mb-2 text-center text-decoration-none d-block py-2 font-semibold"><i class="fa-solid fa-file-invoice"></i> S5 Admission List</a>
                <a href="admin/admission_list.php?class=S2" target="_blank" class="admission-list-btn w-100 text-center text-decoration-none d-block py-2 font-semibold"><i class="fa-solid fa-file-invoice"></i> Other Classes Admitted List</a>
            </fieldset>
        </div>

    </div>

</div>

<!-- Footer -->
<div class="footer">
    <p>
        Address: <?= htmlspecialchars($school_address) ?> | 
        Tel: <?= htmlspecialchars($school_phone) ?> | 
        Email: <a href="mailto:<?= htmlspecialchars($school_email) ?>"><?= htmlspecialchars($school_email) ?></a>
    </p>
</div>

</body>
</html>
