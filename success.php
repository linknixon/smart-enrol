<?php
require 'config/db.php';
require_once __DIR__ . '/form-one/tcpdf/tcpdf_barcodes_2d.php';

$school_name        = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_logo        = getSchoolLogoSrc($pdo);
$applicantName      = htmlspecialchars($_GET['name'] ?? 'Applicant');
$appId              = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isAutoAdmitted     = (!empty($_GET['admitted']) && $_GET['admitted'] == '1');
$assignedComb       = htmlspecialchars($_GET['comb'] ?? '');

// Generate local SVG QR Code linking to online admission letter verification
$qrTargetUrl = getBaseUrl($pdo) . '/app/admission_offer.php?id=' . $appId;
$qrSvgCode   = '';
if ($appId > 0) {
    try {
        $barcodeObj = new TCPDF2DBarcode($qrTargetUrl, 'QRCODE,H');
        $rawSvg     = $barcodeObj->getBarcodeSVGcode(4, 4, 'black');
        $cleanSvg   = preg_replace('/<\?xml.*?\?>/i', '', $rawSvg);
        $cleanSvg   = preg_replace('/<!DOCTYPE.*?>/i', '', $cleanSvg);
        $qrSvgCode  = trim($cleanSvg);
    } catch (Exception $e) {
        $qrSvgCode  = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isAutoAdmitted ? 'Congratulations - Admitted' : 'Application Submitted Successfully' ?> | <?= htmlspecialchars($school_short_name) ?></title>
<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<?= renderThemeConfig($pdo) ?>
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-3xl border border-slate-200 shadow-xl p-6 sm:p-8 text-center space-y-5">
        
        <!-- School Logo -->
        <div class="w-20 h-20 bg-white rounded-2xl p-2 border-2 border-gold-500/30 shadow-md mx-auto flex items-center justify-center">
            <img src="<?= htmlspecialchars($school_logo) ?>" alt="School Logo" class="max-w-full max-h-full object-contain">
        </div>

        <!-- Success Icon: Received -->
        <div class="w-16 h-16 rounded-full bg-brand-50 text-brand-700 flex items-center justify-center text-3xl mx-auto shadow-inner">
            <i class="fa-solid fa-check"></i>
        </div>

        <!-- Content -->
        <div class="space-y-1.5">
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Application Received!</h2>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                Thank you, <strong class="text-brand-700"><?= $applicantName ?></strong>. Your admission application has been successfully submitted to <strong class="text-slate-800"><?= htmlspecialchars($school_name) ?></strong>.
            </p>
        </div>

        <!-- QR Code Verification & Online Admission Letter Access Card -->
        <?php if ($appId > 0 && !empty($qrSvgCode)): ?>
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-center space-y-2.5">
                <div class="flex items-center justify-center gap-1.5 text-xs font-black uppercase tracking-wider text-slate-800">
                    <i class="fa-solid fa-qrcode text-brand-700"></i>
                    <span>Official Document QR Verification</span>
                </div>
                <div class="w-36 h-36 bg-white p-2 rounded-xl border border-slate-200 shadow-xs mx-auto flex items-center justify-center">
                    <div class="w-full h-full [&>svg]:w-full [&>svg]:h-full [&>svg]:max-w-full [&>svg]:max-h-full">
                        <?= $qrSvgCode ?>
                    </div>
                </div>
                <p class="text-[11px] text-slate-500 leading-snug max-w-xs mx-auto">
                    Scan this QR code with any smartphone camera to view your official application status &amp; admission offer. The official admission letter will only be generated and available once your application has been officially admitted.
                </p>
                <div class="text-[9.5px] font-mono text-slate-400 break-all px-2">
                    <?= htmlspecialchars($qrTargetUrl) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Next Steps & School Fees Payment Guidance Callout -->
        <div class="p-4 sm:p-5 rounded-2xl bg-emerald-50 border border-emerald-200 text-left text-xs text-emerald-950 space-y-3 shadow-xs">
            <div class="flex items-center gap-2 font-black text-emerald-900 border-b border-emerald-200/80 pb-2">
                <i class="fa-solid fa-receipt text-emerald-600 text-sm"></i>
                <span class="uppercase tracking-wider text-[11px]">School Fees Payment Details &amp; Next Steps</span>
            </div>

            <div class="space-y-2 text-[11.5px] leading-relaxed text-emerald-900">
                <div class="flex items-start gap-2">
                    <span class="w-5 h-5 rounded-full bg-emerald-200 text-emerald-900 font-bold flex items-center justify-center text-[10px] shrink-0 mt-0.5">1</span>
                    <div>
                        <strong class="text-emerald-950">Admissions Committee Review:</strong> Your uploaded academic documents and bio-data are currently undergoing evaluation. <em>No payments are required or accepted until admission is officially approved.</em>
                    </div>
                </div>

                <div class="flex items-start gap-2">
                    <span class="w-5 h-5 rounded-full bg-emerald-200 text-emerald-900 font-bold flex items-center justify-center text-[10px] shrink-0 mt-0.5">2</span>
                    <div>
                        <strong class="text-emerald-950">Payment Details Sent via Email:</strong> Official payment details are only sent once an applicant has been officially admitted. Once your application is officially approved, full bank payment details and fee schedules will be shared directly to your registered email address.
                    </div>
                </div>

                <div class="flex items-start gap-2">
                    <span class="w-5 h-5 rounded-full bg-emerald-200 text-emerald-900 font-bold flex items-center justify-center text-[10px] shrink-0 mt-0.5">3</span>
                    <div>
                        <strong class="text-emerald-950">Admission Letter Available upon Admission:</strong> The official Provisional Admission Offer Letter will only be generated once your application is admitted. You can view it by scanning the QR code above or tracking your status online.
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification Advice Callout -->
        <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-left text-xs text-amber-950 space-y-1">
            <div class="flex items-center gap-2 font-black text-amber-900">
                <i class="fa-solid fa-bell text-amber-600 text-xs"></i>
                <span class="uppercase tracking-wider text-[10.5px]">Important Applicant Advice</span>
            </div>
            <p class="text-[11px] leading-relaxed text-amber-900/90 font-medium">
                Please <strong>regularly check your registered email address</strong> for real-time status updates and official admission announcements.
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="pt-1 flex flex-col gap-2">
            <a href="track.php" class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-sm">
                <i class="fa-solid fa-location-crosshairs text-xs"></i> Track Admission Status
            </a>
            <div class="grid grid-cols-2 gap-2 pt-1">
                <a href="index.php" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition">
                    <i class="fa-solid fa-house text-xs"></i> Home
                </a>
                <a href="app/admission_list.php" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition">
                    <i class="fa-solid fa-list-check text-xs"></i> Admitted Lists
                </a>
            </div>
        </div>

    </div>

</body>
</html>
