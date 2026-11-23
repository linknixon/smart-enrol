<?php
require 'config/db.php';
$school_name        = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_logo        = getSchoolLogoSrc($pdo);
$applicantName      = htmlspecialchars($_GET['name'] ?? 'Applicant');
$appId              = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isAutoAdmitted     = (!empty($_GET['admitted']) && $_GET['admitted'] == '1');
$assignedComb       = htmlspecialchars($_GET['comb'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isAutoAdmitted ? 'Congratulations - Admitted' : 'Application Submitted Successfully' ?> | <?= htmlspecialchars($school_short_name) ?></title>
<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com" data-cfasync="false"></script>
<!-- Google Fonts & Font Awesome -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= renderThemeConfig($pdo) ?>
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-3xl border border-slate-200 shadow-xl p-6 sm:p-8 text-center space-y-5">
        
        <!-- School Logo -->
        <div class="w-20 h-20 bg-white rounded-2xl p-2 border-2 border-gold-500/30 shadow-md mx-auto flex items-center justify-center">
            <img src="<?= htmlspecialchars($school_logo) ?>" alt="School Logo" class="max-w-full max-h-full object-contain">
        </div>

        <?php if ($isAutoAdmitted): ?>
            <!-- Success Icon: Admitted -->
            <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl mx-auto shadow-inner border border-emerald-200">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>

            <!-- Content -->
            <div class="space-y-1.5">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-300">
                    <i class="fa-solid fa-circle-check text-emerald-600"></i> Merit Auto-Admitted
                </span>
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Congratulations!</h2>
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                    Dear <strong class="text-brand-700"><?= $applicantName ?></strong>, based on your qualifying academic merit credentials, your provisional admission offer has been <strong>APPROVED</strong>!
                </p>
                <?php if (!empty($assignedComb)): ?>
                    <div class="p-2.5 rounded-xl bg-brand-50 border border-brand-200 text-xs font-bold text-brand-900 mt-2">
                        Assigned A-Level Combination: <span class="text-gold-700"><?= $assignedComb ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
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
        <?php endif; ?>

        <!-- Prominent Notification Advice Callout -->
        <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-left text-xs text-amber-950 space-y-1.5">
            <div class="flex items-center gap-2 font-black text-amber-900">
                <i class="fa-solid fa-bell text-amber-600 text-sm"></i>
                <span class="uppercase tracking-wider text-[11px]">Important Applicant Advice</span>
            </div>
            <p class="text-[11.5px] leading-relaxed text-amber-900/90 font-medium">
                Please <strong>regularly check your registered email address and phone messages (SMS)</strong> for real-time status updates, admissions committee decisions, and official downloadable provisional admission offer letters.
            </p>
        </div>

        <!-- Next Steps Summary -->
        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 text-left text-xs text-slate-600 space-y-2">
            <div class="flex items-center gap-2 font-bold text-slate-800">
                <i class="fa-solid fa-circle-info text-brand-700"></i>
                <span>Next Steps:</span>
            </div>
            <ul class="list-disc list-inside space-y-1 text-[11px] text-slate-500">
                <?php if ($isAutoAdmitted): ?>
                    <li>An official Provisional Offer Letter with QR verification has been dispatched to your parent/guardian email.</li>
                    <li>Download and print your offer letter to present on the official reporting date.</li>
                    <li>Your name is registered on the school's official Admitted Register.</li>
                <?php else: ?>
                    <li>An automated confirmation email has been dispatched to your parent/guardian.</li>
                    <li>The Admissions Committee is reviewing your credentials and attachments.</li>
                    <li>Once admitted, your name will appear on the official Admitted Register.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Action Buttons -->
        <div class="pt-1 flex flex-col gap-2">
            <?php if ($isAutoAdmitted && $appId > 0): ?>
                <a href="admin/admission_offer.php?id=<?= $appId ?>" target="_blank" class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-md">
                    <i class="fa-solid fa-file-pdf"></i> Download Official Offer Letter (PDF)
                </a>
            <?php elseif ($appId > 0): ?>
                <a href="admin/admission_offer.php?id=<?= $appId ?>" target="_blank" class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-slate-800 bg-slate-100 hover:bg-slate-200 border border-slate-300 transition shadow-sm">
                    <i class="fa-solid fa-file-lines text-slate-600"></i> Download Application Receipt & Review Slip (PDF)
                </a>
            <?php endif; ?>
            <a href="track.php" class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-sm">
                <i class="fa-solid fa-location-crosshairs text-xs"></i> Track Admission Status
            </a>
            <div class="grid grid-cols-2 gap-2 pt-1">
                <a href="index.php" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition">
                    <i class="fa-solid fa-house text-xs"></i> Home
                </a>
                <a href="admin/admission_list.php" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition">
                    <i class="fa-solid fa-list-check text-xs"></i> Admitted Lists
                </a>
            </div>
        </div>

    </div>

</body>
</html>
