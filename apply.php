<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require 'config/db.php';

// Fetch dynamic configurations
$school_name        = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_motto       = getSetting($pdo, 'school_motto', 'Education for Service');
$school_code        = getSetting($pdo, 'school_code', '011');
$school_logo        = getSchoolLogoSrc($pdo);

// Determine initial selected class from URL parameter
$preSelectedClass = 'S1';
if (isset($_GET['class'])) {
    $c = strtoupper(trim($_GET['class']));
    if (in_array($c, ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'])) {
        $preSelectedClass = $c;
    } elseif ($c === 'OTHER') {
        $preSelectedClass = 'S2';
    }
}

// Intake admission statuses and dynamic academic year
$statusS1    = getAdmissionStatus($pdo, 'S1');
$statusS5    = getAdmissionStatus($pdo, 'S5');
$statusTrans = getAdmissionStatus($pdo, 'trans');
$academic_year = getAcademicYear($pdo, $preSelectedClass);

// School gender classification & admission passmarks
$school_gender_type = getSchoolGenderType($pdo);
$passmark_s1        = getPassMark($pdo, 'S1');
$passmark_s5        = getPassMark($pdo, 'S5');
$primary_color      = getPrimaryColor($pdo);

// Cloudflare Turnstile anti-bot settings
$cf_turnstile_enabled  = getSetting($pdo, 'cf_turnstile_enabled', '0');
$cf_turnstile_site_key = getSetting($pdo, 'cf_turnstile_site_key', '');

// Dynamic A-Level Combinations from Settings
$defaultCombinations = [
    'Sciences' => [
        'PCM/ICT' => 'PCM/ICT (Physics, Chemistry, Mathematics & ICT)',
        'PCM/SMT' => 'PCM/SMT (Physics, Chemistry, Mathematics & Sub-Math)',
        'PCB/ICT' => 'PCB/ICT (Physics, Chemistry, Biology & ICT)',
        'PCB/SMT' => 'PCB/SMT (Physics, Chemistry, Biology & Sub-Math)',
        'BCM/ICT' => 'BCM/ICT (Biology, Chemistry, Mathematics & ICT)',
        'BCM/SMT' => 'BCM/SMT (Biology, Chemistry, Mathematics & Sub-Math)',
        'PEM/ICT' => 'PEM/ICT (Physics, Economics, Mathematics & ICT)',
        'PEM/SMT' => 'PEM/SMT (Physics, Economics, Mathematics & Sub-Math)',
        'MEG/ICT' => 'MEG/ICT (Mathematics, Economics, Geography & ICT)',
        'MEG/SMT' => 'MEG/SMT (Mathematics, Economics, Geography & Sub-Math)',
        'BCG/ICT' => 'BCG/ICT (Biology, Chemistry, Geography & ICT)',
        'MAG/ICT' => 'MAG/ICT (Mathematics, Agriculture, Geography & ICT)',
    ],
    'Arts & Humanities' => [
        'HEG/ICT' => 'HEG/ICT (History, Economics, Geography & ICT)',
        'HEG/SMT' => 'HEG/SMT (History, Economics, Geography & Sub-Math)',
        'HEL/ICT' => 'HEL/ICT (History, Economics, Literature & ICT)',
        'HEL/SMT' => 'HEL/SMT (History, Economics, Literature & Sub-Math)',
        'HLD/ICT' => 'HLD/ICT (History, Literature, Divinity & ICT)',
        'DEG/ICT' => 'DEG/ICT (Divinity, Economics, Geography & ICT)',
        'HED/ICT' => 'HED/ICT (History, Economics, Divinity & ICT)',
        'LEG/ICT' => 'LEG/ICT (Literature, Economics, Geography & ICT)',
        'GEA/ICT' => 'GEA/ICT (Geography, Entrepreneurship, Art & ICT)',
        'MEA/ICT' => 'MEA/ICT (Mathematics, Economics, Art & ICT)',
    ]
];

$configuredCombsJson = getSetting($pdo, 'a_level_combinations', '');
$aLevelCombinations = $defaultCombinations;
if (!empty($configuredCombsJson)) {
    $decoded = json_decode($configuredCombsJson, true);
    if (is_array($decoded) && !empty($decoded)) {
        $aLevelCombinations = $decoded;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Admission Application | <?= htmlspecialchars($school_short_name) ?></title>
<link rel="icon" type="image/png" href="<?= !empty($school_logo) ? htmlspecialchars($school_logo) : 'images/default_logo.png' ?>">
<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com" data-cfasync="false"></script>
<?php if ($cf_turnstile_enabled === '1' && !empty($cf_turnstile_site_key)): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<!-- Google Fonts & Font Awesome -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= renderThemeConfig($pdo) ?>
<style>
html, body {
    overflow-x: hidden !important;
    max-width: 100vw !important;
    width: 100% !important;
    position: relative;
}
*, *:before, *:after {
    box-sizing: border-box;
}
.field-error-highlight {
    border-color: #f43f5e !important;
    border-width: 2px !important;
    box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.25) !important;
    background-color: #fff1f2 !important;
    animation: fieldShake 0.35s ease-in-out;
}
@keyframes fieldShake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-4px); }
    40%, 80% { transform: translateX(4px); }
}
.step-item.active .step-circle {
    background-color: var(--primary-color) !important;
    color: #ffffff !important;
    box-shadow: 0 0 0 4px rgba(12, 74, 36, 0.15);
}
.step-item.completed .step-circle {
    background-color: var(--gold-color) !important;
    color: #ffffff !important;
}
.step-item.active .step-title {
    color: var(--primary-color) !important;
    font-weight: 700 !important;
}
</style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased min-h-screen flex flex-col py-6 sm:py-10 px-4">

    <!-- Container Card -->
    <div class="max-w-3xl w-full mx-auto space-y-6">

        <!-- Top Header & School Identity -->
        <div class="bg-white rounded-2xl border border-slate-200 p-3.5 sm:p-6 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
            <div class="flex items-center gap-3 sm:gap-4 min-w-0 w-full sm:w-auto">
                <a href="index.php" class="w-11 h-11 sm:w-16 sm:h-16 bg-white rounded-xl p-1 sm:p-1.5 border border-slate-200/90 shadow-xs flex items-center justify-center shrink-0 hover:scale-105 transition" title="Back to Main Portal">
                    <img src="<?= htmlspecialchars($school_logo) ?>" alt="<?= htmlspecialchars($school_short_name) ?> Logo" class="max-w-full max-h-full object-contain">
                </a>
                <div class="min-w-0 flex-1 text-left">
                    <h1 class="text-sm sm:text-lg md:text-xl font-black text-slate-900 tracking-tight uppercase leading-snug">
                        <?= htmlspecialchars($school_name) ?>
                    </h1>
                    <?php if (!empty($school_motto)): ?>
                        <p class="text-[11px] sm:text-xs font-bold text-gold-600 italic tracking-wide mt-0.5 truncate max-w-[260px] xs:max-w-xs sm:max-w-none" title="<?= htmlspecialchars($school_motto) ?>">
                            “<?= htmlspecialchars($school_motto) ?>”
                        </p>
                    <?php endif; ?>
                    <div class="flex items-center gap-2 flex-wrap mt-1">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold text-[9px] sm:text-[10px]">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Admissions Open
                        </span>
                        <span class="text-[10px] sm:text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            AY <?= htmlspecialchars($academic_year) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0 w-full sm:w-auto pt-2.5 sm:pt-0 border-t sm:border-t-0 border-slate-100 justify-end">
                <a href="<?= htmlspecialchars(getSchoolWebsiteUrl($pdo)) ?>" target="_blank" class="flex-1 sm:flex-initial text-center justify-center px-3 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition flex items-center gap-1.5">
                    <i class="fa-solid fa-globe text-gold-600 text-[11px]"></i>
                    <span>School Website</span>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-slate-400"></i>
                </a>
                <a href="index.php" class="flex-1 sm:flex-initial text-center justify-center px-3.5 py-2 rounded-xl text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 shadow-sm transition flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left text-[11px]"></i>
                    <span>Main Portal</span>
                </a>
            </div>
        </div>

        <!-- Class Selection Bar -->
        <div class="bg-white rounded-2xl border border-slate-200 p-3 shadow-sm">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-2">
                <span class="text-xs font-bold text-slate-500 uppercase px-2 tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-layer-group text-brand-700"></i> Admission Category:
                </span>
                <div class="grid grid-cols-3 gap-1.5 w-full sm:w-auto max-w-full">
                    <button type="button" id="tabS1" class="class-tab-btn px-2 sm:px-4 py-2 rounded-xl text-[11px] sm:text-xs font-bold transition text-center" onclick="selectClassCategory('S1')">
                        Senior One (S1) <?= !$statusS1['is_open'] ? '<span class="block sm:inline text-[9px] text-rose-500 font-extrabold">(Closed)</span>' : '' ?>
                    </button>
                    <button type="button" id="tabS5" class="class-tab-btn px-2 sm:px-4 py-2 rounded-xl text-[11px] sm:text-xs font-bold transition text-center" onclick="selectClassCategory('S5')">
                        Senior Five (S5) <?= !$statusS5['is_open'] ? '<span class="block sm:inline text-[9px] text-rose-500 font-extrabold">(Closed)</span>' : '' ?>
                    </button>
                    <button type="button" id="tabTransfers" class="class-tab-btn px-2 sm:px-4 py-2 rounded-xl text-[11px] sm:text-xs font-bold transition text-center" onclick="selectClassCategory('OTHER')">
                        Transfers (S2-S6) <?= !$statusTrans['is_open'] ? '<span class="block sm:inline text-[9px] text-rose-500 font-extrabold">(Closed)</span>' : '' ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Wizard Card -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-8 max-w-full overflow-x-hidden">

            <!-- Dynamic JavaScript Field Warning Alert Banner -->
            <div id="fieldValidationWarningBanner" class="hidden mb-6 p-4 rounded-xl bg-rose-50 border-2 border-rose-300 text-rose-900 shadow-sm flex items-start justify-between gap-3 transition-all">
                <div class="flex items-start gap-3">
                    <span class="w-8 h-8 rounded-lg bg-rose-600 text-white flex items-center justify-center shrink-0 mt-0.5 text-sm shadow-xs">
                        <i class="fa-solid fa-circle-exclamation"></i>
                    </span>
                    <div>
                        <h4 class="text-xs font-black uppercase tracking-wider text-rose-800" id="fieldWarningTitle">Required Information Missing</h4>
                        <p class="text-xs sm:text-sm font-bold text-rose-950 mt-0.5 leading-relaxed" id="fieldWarningMessage">
                            Please check the highlighted field.
                        </p>
                    </div>
                </div>
                <button type="button" class="text-rose-400 hover:text-rose-700 text-base font-bold shrink-0 p-1" onclick="dismissFieldWarning()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Step Progress Indicator -->
            <div id="wizardProgress" class="mb-8">
                <div class="flex items-center justify-between relative">
                    <!-- Background Line -->
                    <div class="absolute left-0 top-1/2 -translate-y-1/2 w-full h-1 bg-slate-100 z-0"></div>
                    <div id="progressFill" class="absolute left-0 top-1/2 -translate-y-1/2 h-1 bg-brand-700 transition-all duration-300 z-0" style="width: 0%;"></div>

                    <!-- Step 1 -->
                    <div class="step-item relative z-10 text-center flex-1 active" id="stepIndicator1">
                        <div class="step-circle w-9 h-9 rounded-full bg-slate-100 text-slate-600 font-bold text-xs flex items-center justify-center mx-auto transition">1</div>
                        <span class="step-title block text-[11px] font-semibold text-slate-500 mt-1.5">Student Bio Data</span>
                    </div>

                    <!-- Step 2 -->
                    <div class="step-item relative z-10 text-center flex-1" id="stepIndicator2">
                        <div class="step-circle w-9 h-9 rounded-full bg-slate-100 text-slate-600 font-bold text-xs flex items-center justify-center mx-auto transition">2</div>
                        <span class="step-title block text-[11px] font-semibold text-slate-500 mt-1.5">Academic Credentials</span>
                    </div>

                    <!-- Step 3 -->
                    <div class="step-item relative z-10 text-center flex-1" id="stepIndicator3">
                        <div class="step-circle w-9 h-9 rounded-full bg-slate-100 text-slate-600 font-bold text-xs flex items-center justify-center mx-auto transition">3</div>
                        <span class="step-title block text-[11px] font-semibold text-slate-500 mt-1.5">Parent & Submit</span>
                    </div>
                </div>
            </div>

            <!-- Closed Admissions Notice (dynamically shown if class intake is closed or upcoming) -->
            <div id="closedNotice" class="hidden text-center py-10 px-4 space-y-6">
                <div class="w-20 h-20 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center text-3xl mx-auto shadow-inner border border-rose-100">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <div class="space-y-2 max-w-lg mx-auto">
                    <span id="closedCategoryPill" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-rose-100 text-rose-800">
                        <i class="fa-solid fa-triangle-exclamation"></i> Admissions Closed
                    </span>
                    <h2 id="closedNoticeTitle" class="text-2xl font-black text-slate-900 tracking-tight">Admissions Closed</h2>
                    <p id="closedNoticeDesc" class="text-sm text-slate-600 leading-relaxed"></p>
                </div>

                <!-- Contact & Inquiries Box -->
                <div class="max-w-md mx-auto p-4 bg-slate-50 border border-slate-200 rounded-2xl text-left space-y-2 text-xs text-slate-600">
                    <div class="font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-circle-question text-brand-700"></i>
                        <span>Admissions Office Inquiries</span>
                    </div>
                    <p class="leading-relaxed">
                        For late application appeals, special intake considerations, or inquiries, please contact our admissions office:
                    </p>
                    <div class="pt-1 space-y-1 font-semibold text-slate-700">
                        <div><i class="fa-solid fa-phone text-gold-600 w-4"></i> <?= htmlspecialchars(getSetting($pdo, 'school_phone', '+256 790 001 631')) ?></div>
                        <div><i class="fa-solid fa-envelope text-gold-600 w-4"></i> <?= htmlspecialchars(getSetting($pdo, 'school_email', 'admissions@stpaulsmutolere.sc.ug')) ?></div>
                        <div><i class="fa-solid fa-location-dot text-gold-600 w-4"></i> <?= htmlspecialchars(getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro - Uganda')) ?></div>
                    </div>
                </div>

                <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <a href="index.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition">
                        <i class="fa-solid fa-arrow-left"></i> Return to Main Portal
                    </a>
                    <a href="admin/admission_list.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-sm">
                        <i class="fa-solid fa-list-check"></i> View Admitted Register
                    </a>
                </div>
            </div>

            <!-- Form -->
            <form id="admissionForm" action="submit.php" method="POST" enctype="multipart/form-data">

                <!-- STEP 1: Student Bio Data -->
                <div id="step1" class="wizard-step space-y-5">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-user-graduate text-brand-700"></i> Step 1: Student Personal Details
                        </h3>
                        <span class="text-xs font-semibold text-slate-400">All fields required</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Surname <span class="text-rose-500 font-bold">*</span></label>
                            <input type="text" name="surname" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="e.g. MUGISHA">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">First Name <span class="text-rose-500 font-bold">*</span></label>
                            <input type="text" name="firstname" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="e.g. BRUCE">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Other Names (Optional)</label>
                            <input type="text" name="othernames" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="e.g. KWIZERA">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Sex / Gender <span class="text-rose-500 font-bold">*</span></label>
                            <?php if ($school_gender_type === 'boys'): ?>
                                <select name="sex" required class="w-full px-3.5 py-2.5 border-2 border-brand-700/50 rounded-xl text-sm font-bold text-brand-900 bg-brand-50/20 focus:outline-none focus:border-brand-700 transition">
                                    <option value="Male" selected>Male (Boys Only School)</option>
                                </select>
                                <p class="text-[10px] text-brand-700 font-semibold mt-1"><i class="fa-solid fa-mars"></i> Classified: Boys-Only School</p>
                            <?php elseif ($school_gender_type === 'girls'): ?>
                                <select name="sex" required class="w-full px-3.5 py-2.5 border-2 border-brand-700/50 rounded-xl text-sm font-bold text-brand-900 bg-brand-50/20 focus:outline-none focus:border-brand-700 transition">
                                    <option value="Female" selected>Female (Girls Only School)</option>
                                </select>
                                <p class="text-[10px] text-brand-700 font-semibold mt-1"><i class="fa-solid fa-venus"></i> Classified: Girls-Only School</p>
                            <?php else: ?>
                                <select name="sex" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition bg-white">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Date of Birth <span class="text-rose-500 font-bold">*</span></label>
                            <input type="date" name="dob" id="dobInput" max="<?= date('Y-m-d', strtotime('-9 years')) ?>" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition">
                            <p id="ageWarning" class="hidden text-[11px] text-rose-600 font-bold mt-1"><i class="fa-solid fa-triangle-exclamation"></i> Applicant must be 9 years or older to apply.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Religious Affiliation <span class="text-rose-500 font-bold">*</span></label>
                            <select name="religion" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition bg-white">
                                <option value="">Select Religion</option>
                                <option value="Catholic">Catholic</option>
                                <option value="Anglican">Anglican</option>
                                <option value="Pentecostal / Born Again">Pentecostal / Born Again</option>
                                <option value="Muslim">Muslim</option>
                                <option value="Seventh Day Adventist (SDA)">Seventh Day Adventist (SDA)</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Class of Entry <span class="text-rose-500 font-bold">*</span></label>
                            <select name="class_of_entry" id="classOfEntry" required class="w-full px-3.5 py-2.5 border-2 border-brand-700/60 rounded-xl text-sm font-bold text-brand-900 bg-brand-50/30 focus:outline-none focus:border-brand-700 transition">
                                <option value="S1" <?= $preSelectedClass === 'S1' ? 'selected' : '' ?>>Senior One (S1) - O-Level Entry (PLE)</option>
                                <option value="S2" <?= $preSelectedClass === 'S2' ? 'selected' : '' ?>>Senior Two (S2) - Transfer</option>
                                <option value="S3" <?= $preSelectedClass === 'S3' ? 'selected' : '' ?>>Senior Three (S3) - Transfer</option>
                                <option value="S4" <?= $preSelectedClass === 'S4' ? 'selected' : '' ?>>Senior Four (S4) - Transfer</option>
                                <option value="S5" <?= $preSelectedClass === 'S5' ? 'selected' : '' ?>>Senior Five (S5) - A-Level Entry (UCE NLSC)</option>
                                <option value="S6" <?= $preSelectedClass === 'S6' ? 'selected' : '' ?>>Senior Six (S6) - A-Level Transfer (UCE NLSC)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Learner Identification Number (LIN) (Optional)</label>
                            <input type="text" name="lin" id="linInput" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-mono uppercase focus:outline-none focus:border-brand-700 transition">
                            <p class="text-[10px] text-slate-500 mt-1 leading-snug"><i class="fa-solid fa-circle-info mr-1 text-brand-700"></i> It will be required later by the school but if available provide it because this LIN is obtained from your previous school.</p>
                        </div>
                    </div>

                    <div class="pt-2">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase">Student Passport Photograph <span class="text-rose-500 font-bold">*</span></label>
                        </div>
                        <input type="file" name="passport" id="passportFile" accept="image/*" required class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
                        <p class="text-[10px] text-slate-400 mt-1">Upload from camera, photo gallery, downloads or files. The system automatically formats with a pure white background.</p>
                    </div>

                    <div class="pt-6 border-t border-slate-100 flex justify-end">
                        <button type="button" class="next-btn w-full sm:w-auto px-6 py-2.5 rounded-xl text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-sm flex items-center justify-center gap-2 whitespace-nowrap flex-nowrap shrink-0" onclick="goToStep(2)">
                            Next: Academic History <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Academic History & Result Slip -->
                <div id="step2" class="wizard-step space-y-5 hidden">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-graduation-cap text-brand-700"></i> Step 2: Academic Credentials & Attachments
                        </h3>
                        <span id="academicLevelBadge" class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-brand-100 text-brand-800">
                            Senior One (PLE)
                        </span>
                    </div>

                    <!-- 2.A: Senior One PLE Section -->
                    <div id="pleContainer" class="space-y-4">
                        <!-- Cutoff Pass Mark Badge -->
                        <div class="flex items-center justify-between p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-lg bg-gold-50 text-gold-700 flex items-center justify-center font-bold text-sm border border-gold-200">
                                    <i class="fa-solid fa-award"></i>
                                </span>
                                <div>
                                    <span class="block text-xs font-bold text-slate-800 uppercase">PLE Admission Cutoff Pass Mark</span>
                                    <span class="block text-[11px] text-slate-500">Maximum qualifying aggregates required for Senior One</span>
                                </div>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-black <?= ($passmark_s1 !== null && $passmark_s1 !== '') ? 'bg-brand-100 text-brand-900 border border-brand-300' : 'bg-rose-100 text-rose-800 border border-rose-300' ?>">
                                <?= ($passmark_s1 !== null && $passmark_s1 !== '') ? 'Max ' . htmlspecialchars($passmark_s1) . ' Aggregates' : 'Cutoff Not Set (Submissions Paused)' ?>
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Former Primary School <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="former_school_s1" id="formerSchoolS1" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="e.g. Mutolere Primary School">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">UNEB PLE Index Number <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="index_number_s1" id="indexNumberS1" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-mono focus:outline-none focus:border-brand-700 transition uppercase" placeholder="e.g. 001234/001">
                            </div>
                        </div>

                        <!-- PLE Grades Breakdown -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2">
                            <span class="block text-xs font-bold text-slate-700 uppercase">PLE Subject Grades <span class="text-rose-500 font-bold">*</span> (Automatic Aggregates Calculation)</span>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-1">
                                <?php
                                $pleSubjects = [
                                    'english'     => 'English',
                                    'mathematics' => 'Mathematics',
                                    'sst'         => 'Social Studies',
                                    'science'     => 'Science'
                                ];
                                foreach ($pleSubjects as $k => $subj):
                                ?>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-600 mb-1"><?= $subj ?> <span class="text-rose-500 font-bold">*</span></label>
                                    <select name="<?= $k ?>" id="<?= $k ?>" class="ple-grade-select w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-white focus:outline-none focus:border-brand-700">
                                        <option value="D1">D1 (1)</option>
                                        <option value="D2">D2 (2)</option>
                                        <option value="C3">C3 (3)</option>
                                        <option value="C4">C4 (4)</option>
                                        <option value="C5">C5 (5)</option>
                                        <option value="C6">C6 (6)</option>
                                        <option value="P7">P7 (7)</option>
                                        <option value="P8">P8 (8)</option>
                                        <option value="F9">F9 (9)</option>
                                    </select>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Total PLE Aggregates <span class="text-rose-500 font-bold">*</span></label>
                                <input type="number" name="aggregates_s1" id="aggregatesS1" readonly class="w-full px-3.5 py-2.5 border border-slate-200 bg-slate-100 rounded-xl text-sm font-black text-brand-700 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">PLE Class Division <span class="text-rose-500 font-bold">*</span></label>
                                <select name="division_s1" id="divisionS1" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-bold bg-white focus:outline-none focus:border-brand-700 transition">
                                    <option value="Division 1">Division 1</option>
                                    <option value="Division 2">Division 2</option>
                                    <option value="Division 3">Division 3</option>
                                    <option value="Division 4">Division 4</option>
                                    <option value="Division U">Division U</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Attach UNEB PLE Result Slip <span class="text-rose-500 font-bold">*</span></label>
                                <input type="file" name="result_slip_s1" id="resultSlipS1" accept="image/*,application/pdf" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
                                <p class="text-[10px] text-slate-400 mt-1">Upload from camera, photo gallery, downloads or files.</p>
                            </div>
                        </div>
                    </div>

                    <!-- 2.B: Senior Five & Senior Six UCE NLSC Section -->
                    <div id="uceContainer" class="space-y-5 hidden">
                        <!-- S5 Cutoff Pass Mark Badge -->
                        <div id="s5CutoffBadge" class="flex items-center justify-between p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-lg bg-gold-50 text-gold-700 flex items-center justify-center font-bold text-sm border border-gold-200">
                                    <i class="fa-solid fa-award"></i>
                                </span>
                                <div>
                                    <span class="block text-xs font-bold text-slate-800 uppercase">UCE Admission Cutoff Pass Mark</span>
                                    <span class="block text-[11px] text-slate-500">Maximum qualifying UCE (Best 8) aggregates for Senior Five</span>
                                </div>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-black <?= ($passmark_s5 !== null && $passmark_s5 !== '') ? 'bg-brand-100 text-brand-900 border border-brand-300' : 'bg-rose-100 text-rose-800 border border-rose-300' ?>">
                                <?= ($passmark_s5 !== null && $passmark_s5 !== '') ? 'Max ' . htmlspecialchars($passmark_s5) . ' Aggregates' : 'Cutoff Not Set (Submissions Paused)' ?>
                            </span>
                        </div>

                        <!-- S6 Advanced Transfer Evaluation Badge -->
                        <div id="s6TransferBadge" class="hidden flex items-center justify-between p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-200">
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-sm border border-emerald-300">
                                    <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                </span>
                                <div>
                                    <span class="block text-xs font-bold text-emerald-950 uppercase">Senior Six (S6) Advanced Transfer Admission</span>
                                    <span class="block text-[11px] text-emerald-800">Transfers are evaluated on foundational UCE NLSC results & S5 academic progress</span>
                                </div>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-800 border border-emerald-300">
                                Transfer Evaluation
                            </span>
                        </div>

                        <!-- UNEB NLSC Curriculum Banner & Interactive Descriptors Drawer -->
                        <div class="bg-gradient-to-r from-slate-50 via-brand-50/30 to-slate-50 p-4 rounded-2xl border border-brand-200/60 shadow-2xs space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-brand-700 text-white flex flex-col items-center justify-center font-black text-xs shadow-sm shrink-0">
                                        <span class="text-[9px] font-semibold opacity-80">UNEB</span>
                                        <span>NLSC</span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-black text-slate-900 uppercase tracking-wide">UCE New Lower Secondary Curriculum (NLSC)</span>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black bg-brand-100 text-brand-800 border border-brand-200 uppercase">2024+ Cohort</span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 mt-0.5">Official Letter Grading (A – E), Continuous Assessment Project Work & Certification Results.</p>
                                    </div>
                                </div>
                                <button type="button" onclick="toggleUceDescriptors()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-brand-700 bg-white border border-brand-200 hover:bg-brand-50 transition shadow-2xs self-start sm:self-auto cursor-pointer whitespace-nowrap flex-nowrap shrink-0">
                                    <i class="fa-solid fa-book-open text-brand-600"></i>
                                    <span id="descToggleText">View Achievement Descriptors</span>
                                    <i id="descToggleIcon" class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200"></i>
                                </button>
                            </div>

                            <!-- Collapsible UNEB Descriptors Guide (Attachment 2 Standard Matrix) -->
                            <div id="uceDescriptorsGuide" class="hidden pt-3 border-t border-slate-200 space-y-3">
                                <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-3 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-2 shadow-xs">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center text-amber-400 font-bold shrink-0">
                                            <i class="fa-solid fa-award text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-black tracking-wide uppercase">Uganda National Examinations Board (UNEB)</p>
                                            <p class="text-[11px] text-amber-200 font-semibold">UCE NLSC ACHIEVEMENT LEVELS AND THEIR DESCRIPTORS</p>
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-mono text-slate-300 self-start sm:self-auto bg-white/10 px-2 py-0.5 rounded uppercase">Official Standard</span>
                                </div>

                                <!-- Responsive UNEB NLSC Matrix (Desktop Table & Mobile Clean Cards - Zero Horizontal Scroll) -->
                                <div class="hidden sm:block overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-2xs">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-slate-900 text-white text-[11px] font-black uppercase tracking-wider">
                                                <th class="py-2.5 px-4 w-28 text-center border-r border-slate-800">Grade</th>
                                                <th class="py-2.5 px-4 w-40 border-r border-slate-800">Achievement Level</th>
                                                <th class="py-2.5 px-4">Descriptor (Curriculum Competency)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 text-xs">
                                            <!-- Grade A -->
                                            <tr class="hover:bg-emerald-50/50 transition">
                                                <td class="py-3 px-4 text-center border-r border-slate-100">
                                                    <span class="inline-block px-3 py-1 rounded-lg font-black text-xs bg-[#0f8b54] text-white shadow-xs">Grade A</span>
                                                </td>
                                                <td class="py-3 px-4 border-r border-slate-100">
                                                    <div class="font-bold text-slate-900 text-sm">Exceptional</div>
                                                    <span class="inline-block font-mono text-[10px] text-emerald-800 bg-emerald-100/80 px-1.5 py-0.5 rounded font-bold mt-0.5">5 Points (Max)</span>
                                                </td>
                                                <td class="py-3 px-4 text-slate-700 leading-relaxed text-[11.5px]">
                                                    Demonstrates an <strong class="text-slate-900 font-bold">extraordinary level of competency</strong> by applying innovatively and creatively the acquired knowledge and skills in real life situations.
                                                </td>
                                            </tr>
                                            <!-- Grade B -->
                                            <tr class="hover:bg-rose-50/40 transition">
                                                <td class="py-3 px-4 text-center border-r border-slate-100">
                                                    <span class="inline-block px-3 py-1 rounded-lg font-black text-xs bg-[#8c232c] text-white shadow-xs">Grade B</span>
                                                </td>
                                                <td class="py-3 px-4 border-r border-slate-100">
                                                    <div class="font-bold text-slate-900 text-sm">Outstanding</div>
                                                    <span class="inline-block font-mono text-[10px] text-sky-800 bg-sky-100/80 px-1.5 py-0.5 rounded font-bold mt-0.5">4 Points</span>
                                                </td>
                                                <td class="py-3 px-4 text-slate-700 leading-relaxed text-[11.5px]">
                                                    Demonstrates a <strong class="text-slate-900 font-bold">high level of competency</strong> by applying the acquired knowledge and skills in real life situations.
                                                </td>
                                            </tr>
                                            <!-- Grade C -->
                                            <tr class="hover:bg-emerald-50/50 transition">
                                                <td class="py-3 px-4 text-center border-r border-slate-100">
                                                    <span class="inline-block px-3 py-1 rounded-lg font-black text-xs bg-[#0f8b54] text-white shadow-xs">Grade C</span>
                                                </td>
                                                <td class="py-3 px-4 border-r border-slate-100">
                                                    <div class="font-bold text-slate-900 text-sm">Satisfactory</div>
                                                    <span class="inline-block font-mono text-[10px] text-amber-800 bg-amber-100/80 px-1.5 py-0.5 rounded font-bold mt-0.5">3 Points</span>
                                                </td>
                                                <td class="py-3 px-4 text-slate-700 leading-relaxed text-[11.5px]">
                                                    Demonstrates an <strong class="text-slate-900 font-bold">adequate level of competency</strong> by applying the acquired knowledge and skills in real life situations.
                                                </td>
                                            </tr>
                                            <!-- Grade D -->
                                            <tr class="hover:bg-rose-50/40 transition">
                                                <td class="py-3 px-4 text-center border-r border-slate-100">
                                                    <span class="inline-block px-3 py-1 rounded-lg font-black text-xs bg-[#8c232c] text-white shadow-xs">Grade D</span>
                                                </td>
                                                <td class="py-3 px-4 border-r border-slate-100">
                                                    <div class="font-bold text-slate-900 text-sm">Basic</div>
                                                    <span class="inline-block font-mono text-[10px] text-orange-800 bg-orange-100/80 px-1.5 py-0.5 rounded font-bold mt-0.5">2 Points</span>
                                                </td>
                                                <td class="py-3 px-4 text-slate-700 leading-relaxed text-[11.5px]">
                                                    Demonstrates a <strong class="text-slate-900 font-bold">minimum level of competency</strong> in applying the acquired knowledge and skills in real life situations.
                                                </td>
                                            </tr>
                                            <!-- Grade E -->
                                            <tr class="hover:bg-emerald-50/50 transition">
                                                <td class="py-3 px-4 text-center border-r border-slate-100">
                                                    <span class="inline-block px-3 py-1 rounded-lg font-black text-xs bg-[#0c663e] text-white shadow-xs">Grade E</span>
                                                </td>
                                                <td class="py-3 px-4 border-r border-slate-100">
                                                    <div class="font-bold text-slate-900 text-sm">Elementary</div>
                                                    <span class="inline-block font-mono text-[10px] text-rose-800 bg-rose-100/80 px-1.5 py-0.5 rounded font-bold mt-0.5">1 Point</span>
                                                </td>
                                                <td class="py-3 px-4 text-slate-700 leading-relaxed text-[11.5px]">
                                                    Demonstrates below the <strong class="text-slate-900 font-bold">basic level of competency</strong> in applying the acquired knowledge and skills in real life situations.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Native Stacked Cards (Visible only on small phones, 0 horizontal scroll) -->
                                <div class="block sm:hidden space-y-2.5 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <div class="p-2.5 rounded-lg bg-white border border-emerald-200 space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="px-2 py-0.5 rounded font-black text-[11px] bg-[#0f8b54] text-white">Grade A</span>
                                            <span class="font-bold text-slate-900 text-xs">Exceptional</span>
                                            <span class="text-[10px] font-mono text-emerald-800 bg-emerald-100 px-1.5 py-0.2 rounded font-bold">5 Pts (Max)</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 leading-tight">Extraordinary competency; applies innovative & creative skills in real life.</p>
                                    </div>
                                    <div class="p-2.5 rounded-lg bg-white border border-slate-200 space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="px-2 py-0.5 rounded font-black text-[11px] bg-[#8c232c] text-white">Grade B</span>
                                            <span class="font-bold text-slate-900 text-xs">Outstanding</span>
                                            <span class="text-[10px] font-mono text-sky-800 bg-sky-100 px-1.5 py-0.2 rounded font-bold">4 Pts</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 leading-tight">High level of competency; applies acquired knowledge in real life.</p>
                                    </div>
                                    <div class="p-2.5 rounded-lg bg-white border border-slate-200 space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="px-2 py-0.5 rounded font-black text-[11px] bg-[#0f8b54] text-white">Grade C</span>
                                            <span class="font-bold text-slate-900 text-xs">Satisfactory</span>
                                            <span class="text-[10px] font-mono text-amber-800 bg-amber-100 px-1.5 py-0.2 rounded font-bold">3 Pts</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 leading-tight">Adequate competency in applying acquired knowledge in real life.</p>
                                    </div>
                                    <div class="p-2.5 rounded-lg bg-white border border-slate-200 space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="px-2 py-0.5 rounded font-black text-[11px] bg-[#8c232c] text-white">Grade D</span>
                                            <span class="font-bold text-slate-900 text-xs">Basic</span>
                                            <span class="text-[10px] font-mono text-orange-800 bg-orange-100 px-1.5 py-0.2 rounded font-bold">2 Pts</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 leading-tight">Minimum level of competency in applying knowledge in real life.</p>
                                    </div>
                                    <div class="p-2.5 rounded-lg bg-white border border-slate-200 space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="px-2 py-0.5 rounded font-black text-[11px] bg-[#0c663e] text-white">Grade E</span>
                                            <span class="font-bold text-slate-900 text-xs">Elementary</span>
                                            <span class="text-[10px] font-mono text-rose-800 bg-rose-100 px-1.5 py-0.2 rounded font-bold">1 Pt</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 leading-tight">Below basic competency in applying acquired knowledge in real life.</p>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-400 italic text-right">Reference: UNEB NLSC Assessment Framework · 35 Martyrs' Way Ntinda, P.O Box 7066, Kampala Uganda</p>
                            </div>
                        </div>

                        <!-- Candidate O-Level School & Index -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Former O-Level Secondary School <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="former_school_s5" id="formerSchoolS5" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="e.g. St. Paul's SS">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">UNEB UCE Index Number <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="index_number_s5" id="indexNumberS5" pattern="^U[0-9]{4}/[0-9]{3,4}$" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-mono focus:outline-none focus:border-brand-700 transition uppercase" placeholder="e.g. U0452/009">
                                <p class="text-[10px] text-slate-400 mt-1">Official format: Center Number / Candidate Number (e.g. U0452/009)</p>
                            </div>
                        </div>

                        <!-- Certification Tier & Project Work (Attachment 1) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Overall Certification Result <span class="text-rose-500 font-bold">*</span></label>
                                <select name="division_s5" id="divisionS5" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-bold focus:outline-none focus:border-brand-700 transition bg-white">
                                    <option value="">Select UNEB Overall Result</option>
                                    <option value="Result 1">Result 1 (Distinction / Highest Certification)</option>
                                    <option value="Result 2">Result 2 (High Level Certification)</option>
                                    <option value="Result 3">Result 3 (Satisfactory Certification)</option>
                                    <option value="Result 4">Result 4 (Basic Level Certification)</option>
                                    <option value="Result 5">Result 5 (Elementary Certification)</option>
                                </select>
                                <p class="text-[10px] text-slate-400 mt-1">Matches "RESULT : 1" on your official UNEB result slip</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Continuous Assessment Project Work <span class="text-rose-500 font-bold">*</span></label>
                                <select name="uce_project_work" id="uceProjectWork" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-bold focus:outline-none focus:border-brand-700 transition bg-white">
                                    <option value="">Select Project Work Grade</option>
                                    <option value="A">Grade A (Exceptional)</option>
                                    <option value="B">Grade B (Outstanding)</option>
                                    <option value="C">Grade C (Satisfactory)</option>
                                    <option value="D">Grade D (Basic)</option>
                                    <option value="E">Grade E (Elementary)</option>
                                </select>
                                <p class="text-[10px] text-slate-400 mt-1">Matches "PROJECT WORK : C" on your official UNEB result slip</p>
                            </div>
                        </div>

                        <!-- UCE NLSC Subjects Breakdown & Real-Time Competency Meter -->
                        <div class="bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200 space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-200 pb-3">
                                <div>
                                    <span class="block text-xs font-bold text-slate-800 uppercase tracking-wide">UCE Subject Achievement Levels <span class="text-rose-500 font-bold">*</span></span>
                                    <span class="block text-[11px] text-slate-500">Select the official Letter Grade (A, B, C, D, or E) obtained in each subject.</span>
                                </div>
                                <!-- Real-time Profile Pill -->
                                <div class="flex items-center gap-2">
                                    <div id="nlscProfileBadge" class="px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-xs font-mono font-bold text-brand-900 shadow-2xs flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span><strong id="countA">0</strong> A</span>
                                        <span class="text-slate-300">|</span>
                                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-sky-500"></span><strong id="countB">0</strong> B</span>
                                        <span class="text-slate-300">|</span>
                                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span><strong id="countC">0</strong> C</span>
                                        <span class="text-slate-300">|</span>
                                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-rose-500"></span><strong id="countOther">0</strong> D/E</span>
                                    </div>
                                    <div class="px-3 py-1.5 rounded-xl bg-brand-700 text-white text-xs font-bold shadow-2xs">
                                        Score: <span id="competencyScoreDisplay" class="font-mono">0</span> pts
                                    </div>
                                </div>
                            </div>

                            <!-- 8 Core Subjects Grid -->
                            <div class="space-y-1">
                                <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wider block">1. Core Compulsory Subjects (8 Subjects)</span>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-1">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">English (ENG) <span class="text-rose-500 font-bold">*</span></label>
                                        <select name="uce_eng" id="uce_eng" class="nlsc-grade-select w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-white focus:outline-none focus:border-brand-700">
                                            <option value="">Select Grade</option>
                                            <option value="A">Grade A (Exceptional)</option>
                                            <option value="B">Grade B (Outstanding)</option>
                                            <option value="C">Grade C (Satisfactory)</option>
                                            <option value="D">Grade D (Basic)</option>
                                            <option value="E">Grade E (Elementary)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">Mathematics (MAT) <span class="text-rose-500 font-bold">*</span></label>
                                        <select name="uce_mat" id="uce_mat" class="nlsc-grade-select w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-white focus:outline-none focus:border-brand-700">
                                            <option value="">Select Grade</option>
                                            <option value="A">Grade A (Exceptional)</option>
                                            <option value="B">Grade B (Outstanding)</option>
                                            <option value="C">Grade C (Satisfactory)</option>
                                            <option value="D">Grade D (Basic)</option>
                                            <option value="E">Grade E (Elementary)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">Physics (PHY) <span class="text-rose-500 font-bold">*</span></label>
                                        <select name="uce_phy" id="uce_phy" class="nlsc-grade-select w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-white focus:outline-none focus:border-brand-700">
                                            <option value="">Select Grade</option>
                                            <option value="A">Grade A (Exceptional)</option>
                                            <option value="B">Grade B (Outstanding)</option>
                                            <option value="C">Grade C (Satisfactory)</option>
                                            <option value="D">Grade D (Basic)</option>
                                            <option value="E">Grade E (Elementary)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">Chemistry (CHE) <span class="text-rose-500 font-bold">*</span></label>
                                        <select name="uce_che" id="uce_che" class="nlsc-grade-select w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-white focus:outline-none focus:border-brand-700">
                                            <option value="">Select Grade</option>
                                            <option value="A">Grade A (Exceptional)</option>
                                            <option value="B">Grade B (Outstanding)</option>
                                            <option value="C">Grade C (Satisfactory)</option>
                                            <option value="D">Grade D (Basic)</option>
                                            <option value="E">Grade E (Elementary)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">Biology (BIO) <span class="text-rose-500 font-bold">*</span></label>
                                        <select name="uce_bio" id="uce_bio" class="nlsc-grade-select w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-white focus:outline-none focus:border-brand-700">
                                            <option value="">Select Grade</option>
                                            <option value="A">Grade A (Exceptional)</option>
                                            <option value="B">Grade B (Outstanding)</option>
                                            <option value="C">Grade C (Satisfactory)</option>
                                            <option value="D">Grade D (Basic)</option>
                                            <option value="E">Grade E (Elementary)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">History & Political (HIS) <span class="text-rose-500 font-bold">*</span></label>
                                        <select name="uce_his" id="uce_his" class="nlsc-grade-select w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-white focus:outline-none focus:border-brand-700">
                                            <option value="">Select Grade</option>
                                            <option value="A">Grade A (Exceptional)</option>
                                            <option value="B">Grade B (Outstanding)</option>
                                            <option value="C">Grade C (Satisfactory)</option>
                                            <option value="D">Grade D (Basic)</option>
                                            <option value="E">Grade E (Elementary)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">Geography (GEO) <span class="text-rose-500 font-bold">*</span></label>
                                        <select name="uce_geo" id="uce_geo" class="nlsc-grade-select w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-white focus:outline-none focus:border-brand-700">
                                            <option value="">Select Grade</option>
                                            <option value="A">Grade A (Exceptional)</option>
                                            <option value="B">Grade B (Outstanding)</option>
                                            <option value="C">Grade C (Satisfactory)</option>
                                            <option value="D">Grade D (Basic)</option>
                                            <option value="E">Grade E (Elementary)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">Religious Educ. (CRE/IRE) <span class="text-[10px] text-slate-400 font-normal ml-1">(Elective)</span></label>
                                        <select name="uce_cre_ire" id="uce_cre_ire" class="nlsc-grade-select w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-white focus:outline-none focus:border-brand-700">
                                            <option value="">No Grade / Optional</option>
                                            <option value="A">Grade A (Exceptional)</option>
                                            <option value="B">Grade B (Outstanding)</option>
                                            <option value="C">Grade C (Satisfactory)</option>
                                            <option value="D">Grade D (Basic)</option>
                                            <option value="E">Grade E (Elementary)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Electives Section -->
                            <div class="space-y-1 pt-2 border-t border-slate-200/80">
                                <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wider block">2. Elective / Optional Subjects (1 or 2 Subjects)</span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                    <!-- Elective 1 -->
                                    <div class="p-2.5 bg-white rounded-xl border border-slate-200 space-y-1.5">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[11px] font-bold text-slate-800 uppercase">Elective Subject 1 <span class="text-rose-500 font-bold">*</span></span>
                                            <span class="text-[10px] text-slate-400">e.g. Agriculture / ICT</span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <select name="uce_elec1_name" id="uce_elec1_name" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-white focus:outline-none focus:border-brand-700">
                                                <option value="Agriculture (AGR)" selected>Agriculture (AGR)</option>
                                                <option value="Information & Comm. Tech (ICT)">Information & Comm. Tech (ICT)</option>
                                                <option value="Literature in English (LIT)">Literature in English (LIT)</option>
                                                <option value="Art & Design (ART)">Art & Design (ART)</option>
                                                <option value="Entrepreneurship (ENT)">Entrepreneurship (ENT)</option>
                                                <option value="Performing Arts / Music (MUS)">Performing Arts / Music (MUS)</option>
                                                <option value="Physical Education (PHE)">Physical Education (PHE)</option>
                                                <option value="French Language (FRN)">French Language (FRN)</option>
                                                <option value="Arabic Language (ARB)">Arabic Language (ARB)</option>
                                                <option value="Luganda (LUG)">Luganda (LUG)</option>
                                                <option value="Runyankore-Rukiga (RRK)">Runyankore-Rukiga (RRK)</option>
                                                <option value="Other Language / Elective">Other Language / Elective</option>
                                            </select>
                                            <select name="uce_elec1_grade" id="uce_elec1_grade" class="nlsc-grade-select w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-white focus:outline-none focus:border-brand-700">
                                                <option value="">Select Grade</option>
                                                <option value="A">Grade A (Exceptional)</option>
                                                <option value="B">Grade B (Outstanding)</option>
                                                <option value="C">Grade C (Satisfactory)</option>
                                                <option value="D">Grade D (Basic)</option>
                                                <option value="E">Grade E (Elementary)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- Elective 2 (Optional) -->
                                    <div class="p-2.5 bg-white rounded-xl border border-slate-200 space-y-1.5">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[11px] font-bold text-slate-800 uppercase">Elective Subject 2 (Optional)</span>
                                            <span class="text-[10px] text-slate-400">If 10 subjects taken</span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <select name="uce_elec2_name" id="uce_elec2_name" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-white focus:outline-none focus:border-brand-700">
                                                <option value="">None / Not Applicable</option>
                                                <option value="Information & Comm. Tech (ICT)">Information & Comm. Tech (ICT)</option>
                                                <option value="Literature in English (LIT)">Literature in English (LIT)</option>
                                                <option value="Art & Design (ART)">Art & Design (ART)</option>
                                                <option value="Entrepreneurship (ENT)">Entrepreneurship (ENT)</option>
                                                <option value="Performing Arts / Music (MUS)">Performing Arts / Music (MUS)</option>
                                                <option value="Physical Education (PHE)">Physical Education (PHE)</option>
                                                <option value="French Language (FRN)">French Language (FRN)</option>
                                                <option value="Arabic Language (ARB)">Arabic Language (ARB)</option>
                                                <option value="Luganda (LUG)">Luganda (LUG)</option>
                                                <option value="Runyankore-Rukiga (RRK)">Runyankore-Rukiga (RRK)</option>
                                                <option value="Other Language / Elective">Other Language / Elective</option>
                                            </select>
                                            <select name="uce_elec2_grade" id="uce_elec2_grade" class="nlsc-grade-select w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-white focus:outline-none focus:border-brand-700">
                                                <option value="">No Grade</option>
                                                <option value="A">Grade A (Exceptional)</option>
                                                <option value="B">Grade B (Outstanding)</option>
                                                <option value="C">Grade C (Satisfactory)</option>
                                                <option value="D">Grade D (Basic)</option>
                                                <option value="E">Grade E (Elementary)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden Aggregates equivalent input used by cutoff enforcement -->
                            <input type="hidden" name="aggregates_s5" id="aggregatesS5" value="0">
                        </div>

                        <!-- Auto-Detected A-Level Subject Combination (Automatically calculated from Performance) -->
                        <div class="space-y-2.5 p-4 rounded-2xl bg-gradient-to-r from-emerald-50/70 via-teal-50/50 to-slate-50 border border-emerald-200/90 shadow-2xs">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <label class="text-xs font-black text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                                    <i class="fa-solid fa-wand-magic-sparkles text-amber-500 text-sm"></i>
                                    <span>A-Level Subject Combination</span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9.5px] font-extrabold uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Auto-Detected
                                    </span>
                                </label>
                                <button type="button" id="toggleCombinationOverrideBtn" class="text-[11px] font-bold text-brand-700 hover:text-brand-900 underline transition inline-flex items-center gap-1" onclick="toggleCombinationOverride()">
                                    <i class="fa-solid fa-sliders"></i>
                                    <span>Change / Select Manually</span>
                                </button>
                            </div>

                            <!-- Live Auto-Detection Visual Badge Card -->
                            <div id="autoCombinationCard" class="p-3.5 rounded-xl bg-white border-2 border-emerald-500/50 shadow-xs flex items-start gap-3 transition-all">
                                <div class="w-10 h-10 rounded-xl bg-emerald-700 text-white flex items-center justify-center shrink-0 shadow-xs text-base">
                                    <i class="fa-solid fa-check-double"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span id="autoCombGroupBadge" class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 border border-emerald-200">
                                            Sciences Track
                                        </span>
                                        <span class="text-[11px] text-slate-500 font-semibold">Matched to Performance</span>
                                    </div>
                                    <h4 id="autoCombTitle" class="text-base sm:text-lg font-black text-slate-900 tracking-tight mt-0.5 leading-snug">
                                        PCM/ICT (Physics, Chemistry, Mathematics & ICT)
                                    </h4>
                                    <p id="autoCombDesc" class="text-xs text-slate-600 mt-1 leading-relaxed">
                                        Auto-detected from your UCE subject competencies. Update grades above to re-calculate.
                                    </p>
                                </div>
                            </div>

                            <!-- Hidden / Form-Bound input holding the active combination value -->
                            <input type="hidden" name="combination_s5" id="combinationS5" value="PCM/ICT">

                            <!-- Optional Manual Override Select (Hidden by default, opened only when requested) -->
                            <div id="combinationOverrideSection" class="hidden pt-2 border-t border-slate-200 space-y-1.5">
                                <label class="block text-[11px] font-bold text-slate-700 uppercase">Select Alternative Combination:</label>
                                <select id="combinationOverrideSelect" class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-xs font-bold text-slate-800 bg-white focus:outline-none focus:border-brand-700 transition" onchange="applyManualCombination(this.value)">
                                    <option value="">-- Choose from Available Combinations --</option>
                                    <?php foreach ($aLevelCombinations as $group => $items): ?>
                                        <optgroup label="<?= htmlspecialchars($group) ?>">
                                            <?php foreach ($items as $val => $label): ?>
                                                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- S6 Transfer Specific Fields (Only displayed when S6 is selected) -->
                        <div id="s6TransferFields" class="hidden p-4 sm:p-5 rounded-2xl bg-emerald-50/50 border border-emerald-200 space-y-4">
                            <div class="flex items-center gap-2 border-b border-emerald-200/80 pb-2">
                                <span class="w-6 h-6 rounded-md bg-emerald-700 text-white flex items-center justify-center text-xs">
                                    <i class="fa-solid fa-graduation-cap"></i>
                                </span>
                                <span class="text-xs font-black text-emerald-950 uppercase tracking-wide">Senior Six Transfer History & Progress</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Former Senior Five (S5) School <span class="text-rose-500 font-bold">*</span></label>
                                    <input type="text" name="s6_former_school" id="s6FormerSchool" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition bg-white" placeholder="e.g. Previous A-Level High School">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">S5 Principal Passes Achieved <span class="text-rose-500 font-bold">*</span></label>
                                    <select name="s6_principal_passes" id="s6PrincipalPasses" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition bg-white">
                                        <option value="">Select S5 Performance</option>
                                        <option value="3 Principal Passes (e.g. AAA, ABB, BBC)">3 Principal Passes (e.g. AAA, ABB, BBC)</option>
                                        <option value="2 Principal Passes (e.g. BCC, CDD)">2 Principal Passes (e.g. BCC, CDD)</option>
                                        <option value="1 Principal Pass">1 Principal Pass</option>
                                        <option value="Subsidiary Pass Only">Subsidiary Pass Only</option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">UCE Division / Certification <span class="text-rose-500 font-bold">*</span></label>
                                    <select name="division_s6" id="divisionS6" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-bold focus:outline-none focus:border-brand-700 transition bg-white">
                                        <option value="Result 1">Result 1 (Distinction / Grade 1)</option>
                                        <option value="Result 2">Result 2 (High Level / Grade 2)</option>
                                        <option value="Result 3">Result 3 (Satisfactory / Grade 3)</option>
                                        <option value="Div 1">Division 1</option>
                                        <option value="Div 2">Division 2</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">UCE Best 8 Aggregates <span class="text-rose-500 font-bold">*</span></label>
                                    <input type="number" name="aggregates_s6" id="aggregatesS6" min="8" max="72" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-bold focus:outline-none focus:border-brand-700 transition bg-white" placeholder="e.g. 18">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Reason for Senior Six Transfer <span class="text-rose-500 font-bold">*</span></label>
                                <textarea name="s6_transfer_reason" id="s6TransferReason" rows="2" class="w-full px-3.5 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition bg-white" placeholder="State reason for seeking transfer to Senior Six (relocation, change of environment, etc.)..."></textarea>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-bold text-slate-700 uppercase">Attach S5 End of Year Report Card / Transcripts <span class="text-rose-500 font-bold">*</span></label>
                                </div>
                                <input type="file" name="s6_report_file" id="s6ReportFile" accept="image/*,application/pdf" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-emerald-100 file:text-emerald-800 hover:file:bg-emerald-200 cursor-pointer">
                                <p class="text-[10px] text-slate-400 mt-1">Upload from camera, photo gallery, downloads or files.</p>
                            </div>
                        </div>

                        <!-- Attach UNEB UCE NLSC Result Slip -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-bold text-slate-700 uppercase">Attach Official UNEB UCE Result Slip <span class="text-rose-500 font-bold">*</span></label>
                            </div>
                            <input type="file" name="result_slip_s5" id="resultSlipS5" accept="image/*,application/pdf" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
                            <p class="text-[10px] text-slate-500 mt-1"><i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> Accepted: Upload from camera, photo gallery, downloads or files (official slip, provisional slip, or UNEB SMS).</p>
                        </div>
                    </div>

                    <!-- 2.C: Transfers Section -->
                    <div id="transferContainer" class="space-y-4 hidden">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Former School Name <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="former_school_transfer" id="formerSchoolTransfer" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="e.g. Previous Secondary School">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Last Class Attended <span class="text-rose-500 font-bold">*</span></label>
                                <select name="last_class_attended" id="lastClassAttended" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition bg-white">
                                    <option value="">Select Last Class</option>
                                    <option value="Senior One (S1)">Senior One (S1)</option>
                                    <option value="Senior Two (S2)">Senior Two (S2)</option>
                                    <option value="Senior Three (S3)">Senior Three (S3)</option>
                                    <option value="Senior Four (S4)">Senior Four (S4)</option>
                                    <option value="Senior Five (S5)">Senior Five (S5)</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Previous Division / Class Standing <span class="text-rose-500 font-bold">*</span></label>
                                <select name="division_transfer" id="divisionTransfer" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-bold focus:outline-none focus:border-brand-700 transition bg-white">
                                    <option value="Division 1">Division 1 (First Grade / Distinction)</option>
                                    <option value="Division 2">Division 2 (Second Grade / High Standing)</option>
                                    <option value="Division 3">Division 3 (Third Grade / Credit)</option>
                                    <option value="Division 4">Division 4 (Pass)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Previous Aggregates / Average Marks <span class="text-rose-500 font-bold">*</span></label>
                                <input type="number" name="aggregates_transfer" id="aggregatesTransfer" min="4" max="90" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-bold focus:outline-none focus:border-brand-700 transition" placeholder="e.g. 14">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Reason for Seeking Transfer <span class="text-rose-500 font-bold">*</span></label>
                            <textarea name="transfer_reason" id="transferReason" rows="2" class="w-full px-3.5 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="State reason (relocation, academic advancement, etc.)..."></textarea>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-bold text-slate-700 uppercase">Attach Previous Report Card / Academic Transcript <span class="text-rose-500 font-bold">*</span></label>
                            </div>
                            <input type="file" name="result_slip_transfer" id="resultSlipTransfer" accept="image/*,application/pdf" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
                            <p class="text-[10px] text-slate-400 mt-1">Upload from camera, photo gallery, downloads or files.</p>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-center justify-between gap-3">
                        <button type="button" class="w-full sm:w-auto px-5 py-2.5 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition flex items-center justify-center gap-2 whitespace-nowrap flex-nowrap shrink-0" onclick="goToStep(1)">
                            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back
                        </button>
                        <button type="button" class="next-btn w-full sm:w-auto px-6 py-2.5 rounded-xl text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-sm flex items-center justify-center gap-2 whitespace-nowrap flex-nowrap shrink-0" onclick="goToStep(3)">
                            Next: Parent / Guardian <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: Parent / Guardian & Submit -->
                <div id="step3" class="wizard-step space-y-5 hidden">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-users text-brand-700"></i> Step 3: Parent / Guardian Details & Submit
                        </h3>
                        <span class="text-xs font-semibold text-slate-400">Final Step</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Parent / Guardian Full Name <span class="text-rose-500 font-bold">*</span></label>
                            <input type="text" name="parent_name" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-sans font-medium text-slate-800 focus:outline-none focus:border-brand-700 transition" placeholder="e.g. RWAMBO JOHN">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Telephone Contact (Uganda Format) <span class="text-rose-500 font-bold">*</span></label>
                            <input type="tel" inputmode="tel" name="parent_phone" id="parentPhone" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-sans font-medium text-slate-800 focus:outline-none focus:border-brand-700 transition" placeholder="e.g. 0772123456 or +256772123456">
                            <p class="text-[10px] text-slate-400 mt-1">Active phone number for SMS and WhatsApp communications.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Parent NIRA NIN (National ID) <span class="text-rose-500 font-bold">*</span></label>
                            <input type="text" inputmode="text" name="parent_nin" id="parentNin" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase()" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-sans font-medium uppercase text-slate-800 focus:outline-none focus:border-brand-700 transition" placeholder="e.g. CM89012345ABCD">
                            <p class="text-[10px] text-slate-400 mt-1">14-character Ugandan National Identification Number.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Parent / Guardian Email <span class="text-rose-500 font-bold">*</span></label>
                            <input type="email" inputmode="email" name="parent_email" id="parentEmail" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-sans font-medium text-slate-800 focus:outline-none focus:border-brand-700 transition" placeholder="e.g. parent@example.com">
                            <p class="text-[10px] text-slate-400 mt-1">An automated confirmation letter will be emailed here.</p>
                        </div>
                    </div>

                    <!-- Physical Residential Address (Uganda LC Administrative Structure) -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-slate-700 uppercase">
                            <i class="fa-solid fa-map-location-dot text-brand-700 mr-1"></i> Residential Physical Address (Uganda Location Details)
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Village / Cell / LC1 <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="parent_village" id="parentVillage" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="e.g. Nyarubuye Cell">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Parish / Ward <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="parent_parish" id="parentParish" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="e.g. Gisozi Parish">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Sub-county / Town Council / Division <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="parent_subcounty" id="parentSubcounty" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="e.g. Nyarusiza Sub-county">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">District <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="parent_district" id="parentDistrict" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="e.g. Kisoro District">
                            </div>
                        </div>
                        <input type="hidden" name="parent_address" id="parentAddress">
                    </div>

                    <!-- Parent / Guardian National ID Document Upload -->
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-slate-700 uppercase">
                                <i class="fa-solid fa-id-card text-brand-700 mr-1"></i> Upload Parent / Guardian National ID <span class="text-rose-500 font-bold">*</span>
                            </label>
                        </div>
                        <input type="file" name="parent_id_file" id="parentIdFile" accept="image/*,application/pdf" required class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
                        <p class="text-[10px] text-slate-400">Upload from camera, photo gallery, downloads or files (PDF/PNG/JPG copy of Parent/Guardian National ID or valid passport).</p>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" id="declarationCheck" required class="mt-1 w-4 h-4 rounded text-brand-700 focus:ring-brand-700 border-slate-300">
                            <span class="text-xs text-slate-600 leading-relaxed">
                                I hereby declare that the details provided in this application are true and accurate to the best of my knowledge. I understand that falsification of academic documents leads to immediate disqualification.
                            </span>
                        </label>
                    </div>

                    <?php if ($cf_turnstile_enabled === '1' && !empty($cf_turnstile_site_key)): ?>
                    <!-- Cloudflare Turnstile Anti-Bot Verification -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 flex flex-col items-center justify-center gap-2">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider"><i class="fa-solid fa-shield-halved text-brand-700 mr-1"></i> Anti-Spam Security Verification</span>
                        <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($cf_turnstile_site_key) ?>" data-theme="light"></div>
                    </div>
                    <?php endif; ?>

                    <div class="pt-6 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-center justify-between gap-3">
                        <button type="button" class="w-full sm:w-auto px-5 py-2.5 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition flex items-center justify-center gap-2 whitespace-nowrap flex-nowrap shrink-0" onclick="goToStep(2)">
                            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back
                        </button>
                        <button type="submit" id="submitBtn" class="w-full sm:w-auto px-8 py-3 rounded-xl text-sm font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-md flex items-center justify-center gap-2 whitespace-nowrap flex-nowrap shrink-0">
                            <i class="fa-solid fa-paper-plane text-xs"></i> Submit Application
                        </button>
                    </div>
                </div>

            </form>
        </div>

        <!-- Footer -->
        <footer class="mt-8 text-white text-xs py-6 px-6 rounded-2xl space-y-3" style="background-color: <?= $primary_color ?>;">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-3 text-center sm:text-left">
                <div>
                    <span class="font-bold text-white text-xs sm:text-sm"><?= htmlspecialchars($school_name) ?></span>
                    <span class="block text-[11px] text-slate-100">&copy; <?= date('Y') ?> All Rights Reserved. Official Online Admissions System.</span>
                </div>
                <div class="flex items-center gap-3 text-xs font-semibold text-slate-100">
                    <button type="button" onclick="openLegalModal('privacyModal')" class="hover:text-white transition underline-offset-4 hover:underline">
                        Privacy Policy
                    </button>
                    <span>·</span>
                    <button type="button" onclick="openLegalModal('termsModal')" class="hover:text-white transition underline-offset-4 hover:underline">
                        Terms of Use
                    </button>
                    <span>·</span>
                    <a href="track.php" class="hover:text-white transition underline-offset-4 hover:underline">
                        Track Status
                    </a>
                    <span>·</span>
                    <a href="admin/login.php" class="hover:text-white transition underline-offset-4 hover:underline">
                        Staff Login
                    </a>
                </div>
            </div>
            <div class="pt-3 border-t border-white/20 flex flex-col sm:flex-row justify-between items-center text-[11px] text-slate-100 gap-2">
                <span>School Motto: <?= htmlspecialchars($school_motto) ?> · UNEB Center No: <?= htmlspecialchars($school_code) ?></span>
                <span>
                    Developed by 
                    <a href="https://ncloud.co.ug/shop?item=school-admission-system" target="_blank" rel="noopener noreferrer" class="text-white hover:text-gold-300 font-bold underline decoration-white/50 hover:decoration-gold-300 transition">
                        Nova Cloud Edges
                    </a>
                </span>
            </div>
        </footer>

    </div>

    <!-- Floating Back-to-Top Button -->
    <button id="backToTopBtn" type="button" aria-label="Back to Top"
        class="fixed bottom-6 right-6 z-50 p-3.5 rounded-full text-white shadow-xl opacity-0 pointer-events-none transition-all duration-300 hover:scale-110 focus:outline-none"
        style="background-color: <?= $primary_color ?>;">
        <i class="fa-solid fa-arrow-up text-sm"></i>
    </button>

    <!-- Wizard & Tab Logic -->
    <script>
    let currentStep = 1;

    const intakeStatuses = <?= json_encode([
        'S1' => $statusS1,
        'S5' => $statusS5,
        'OTHER' => $statusTrans
    ]) ?>;

    const cutoffPassmarks = {
        'S1': <?= json_encode($passmark_s1) ?>,
        'S5': <?= json_encode($passmark_s5) ?>
    };

    const pleContainer = document.getElementById('pleContainer');
    const uceContainer = document.getElementById('uceContainer');
    const transferContainer = document.getElementById('transferContainer');
    const classOfEntrySelect = document.getElementById('classOfEntry');
    const academicBadge = document.getElementById('academicLevelBadge');

    const tabS1 = document.getElementById('tabS1');
    const tabS5 = document.getElementById('tabS5');
    const tabTransfers = document.getElementById('tabTransfers');

    function getCategoryForClass(val) {
        if (val === 'S1') return 'S1';
        if (val === 'S5') return 'S5';
        return 'OTHER';
    }

    function selectClassCategory(category) {
        if (category === 'S1') {
            classOfEntrySelect.value = 'S1';
        } else if (category === 'S5') {
            classOfEntrySelect.value = 'S5';
        } else {
            // Transfers
            if (!['S2', 'S3', 'S4', 'S6'].includes(classOfEntrySelect.value)) {
                classOfEntrySelect.value = 'S2';
            }
        }
        updateClassVisibility();
    }

    function updateClassVisibility() {
        const val = classOfEntrySelect.value;
        const cat = getCategoryForClass(val);
        const catStatus = intakeStatuses[cat];

        // Reset tabs style
        [tabS1, tabS5, tabTransfers].forEach(t => {
            t.className = "class-tab-btn px-4 py-2 rounded-xl text-xs font-bold transition text-center bg-slate-100 text-slate-600 hover:bg-slate-200";
        });

        // Highlight active tab
        if (val === 'S1') {
            tabS1.className = "class-tab-btn px-4 py-2 rounded-xl text-xs font-bold transition text-center bg-brand-700 text-white shadow-sm";
        } else if (val === 'S5') {
            tabS5.className = "class-tab-btn px-4 py-2 rounded-xl text-xs font-bold transition text-center bg-brand-700 text-white shadow-sm";
        } else {
            tabTransfers.className = "class-tab-btn px-4 py-2 rounded-xl text-xs font-bold transition text-center bg-brand-700 text-white shadow-sm";
        }

        const wizardProgress = document.getElementById('wizardProgress');
        const admissionForm = document.getElementById('admissionForm');
        const closedNotice = document.getElementById('closedNotice');
        const closedCategoryPill = document.getElementById('closedCategoryPill');
        const closedTitle = document.getElementById('closedNoticeTitle');
        const closedDesc = document.getElementById('closedNoticeDesc');

        if (!catStatus.is_open) {
            // Category admissions are closed or upcoming
            wizardProgress.classList.add('hidden');
            admissionForm.classList.add('hidden');
            closedNotice.classList.remove('hidden');

            const classLabel = (val === 'S1') ? 'Senior One (S1)' : ((val === 'S5') ? 'Senior Five (S5)' : 'Transfers (' + val + ')');

            if (catStatus.status === 'closed') {
                closedCategoryPill.className = "inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-rose-100 text-rose-800";
                closedCategoryPill.innerHTML = '<i class="fa-solid fa-lock"></i> Application Deadline Reached';
                closedTitle.textContent = "Admissions Closed for " + classLabel;
                closedDesc.innerHTML = "Online admissions for <strong>" + classLabel + "</strong> officially closed on <strong>" + catStatus.formatted_end + "</strong>. The application deadline set by the admissions board has passed for this intake cycle.";
            } else {
                closedCategoryPill.className = "inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-amber-100 text-amber-800";
                closedCategoryPill.innerHTML = '<i class="fa-solid fa-clock"></i> Applications Not Yet Open';
                closedTitle.textContent = "Admissions Opening Soon for " + classLabel;
                closedDesc.innerHTML = "Online applications for <strong>" + classLabel + "</strong> will officially open on <strong>" + catStatus.formatted_start + "</strong>. Please return once the intake cycle begins.";
            }
            return;
        }

        // Category is OPEN
        wizardProgress.classList.remove('hidden');
        admissionForm.classList.remove('hidden');
        closedNotice.classList.add('hidden');

        // Hide all academic containers
        pleContainer.classList.add('hidden');
        uceContainer.classList.add('hidden');
        transferContainer.classList.add('hidden');

        // Clear required constraints on all dynamic academic fields
        pleContainer.querySelectorAll("input, select").forEach(el => el.removeAttribute('required'));
        uceContainer.querySelectorAll("input, select, textarea").forEach(el => el.removeAttribute('required'));
        transferContainer.querySelectorAll("input, select, textarea").forEach(el => el.removeAttribute('required'));

        const s5CutoffBadge = document.getElementById('s5CutoffBadge');
        const s6TransferBadge = document.getElementById('s6TransferBadge');
        const s6TransferFields = document.getElementById('s6TransferFields');

        if (val === 'S1') {
            pleContainer.classList.remove('hidden');
            academicBadge.textContent = "Senior One (PLE)";
            pleContainer.querySelectorAll("input[type=text], input[type=file]").forEach(el => {
                if (el.name !== 'othernames') el.setAttribute('required', 'required');
            });
        } else if (val === 'S5') {
            uceContainer.classList.remove('hidden');
            if (s5CutoffBadge) s5CutoffBadge.classList.remove('hidden');
            if (s6TransferBadge) s6TransferBadge.classList.add('hidden');
            if (s6TransferFields) s6TransferFields.classList.add('hidden');
            academicBadge.textContent = "Senior Five (UCE NLSC)";

            // Mandatory fields for S5 (Religious Educ CRE/IRE is an Elective, not compulsory)
            ['formerSchoolS5', 'indexNumberS5', 'divisionS5', 'uceProjectWork', 'uce_elec1_name', 'resultSlipS5'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.setAttribute('required', 'required');
            });
            document.querySelectorAll('#uceContainer .nlsc-grade-select').forEach(el => {
                if (el.id !== 'uce_elec2_grade' && el.id !== 'uce_cre_ire') {
                    el.setAttribute('required', 'required');
                } else {
                    el.removeAttribute('required');
                }
            });
        } else if (val === 'S6') {
            uceContainer.classList.remove('hidden');
            if (s5CutoffBadge) s5CutoffBadge.classList.add('hidden');
            if (s6TransferBadge) s6TransferBadge.classList.remove('hidden');
            if (s6TransferFields) s6TransferFields.classList.remove('hidden');
            academicBadge.textContent = "Senior Six (A-Level Transfer & UCE NLSC)";

            // Mandatory fields for S6 (Foundational UCE NLSC + S5 Transfer credentials)
            ['formerSchoolS5', 'indexNumberS5', 'divisionS5', 'uceProjectWork', 'uce_elec1_name', 'resultSlipS5', 's6FormerSchool', 's6PrincipalPasses', 's6TransferReason', 's6ReportFile'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.setAttribute('required', 'required');
            });
            document.querySelectorAll('#uceContainer .nlsc-grade-select').forEach(el => {
                if (el.id !== 'uce_elec2_grade' && el.id !== 'uce_cre_ire') {
                    el.setAttribute('required', 'required');
                } else {
                    el.removeAttribute('required');
                }
            });
        } else {
            // S2, S3, S4
            transferContainer.classList.remove('hidden');
            academicBadge.textContent = "Transfers (" + val + ")";
            transferContainer.querySelectorAll("input, select, textarea").forEach(el => el.setAttribute('required', 'required'));
        }
    }

    classOfEntrySelect.addEventListener('change', updateClassVisibility);
    updateClassVisibility();

    // Toggle UNEB NLSC Descriptors Drawer
    function toggleUceDescriptors() {
        const guide = document.getElementById('uceDescriptorsGuide');
        const icon = document.getElementById('descToggleIcon');
        const text = document.getElementById('descToggleText');
        if (!guide) return;
        if (guide.classList.contains('hidden')) {
            guide.classList.remove('hidden');
            if (icon) icon.style.transform = 'rotate(180deg)';
            if (text) text.textContent = 'Hide Achievement Descriptors';
        } else {
            guide.classList.add('hidden');
            if (icon) icon.style.transform = 'rotate(0deg)';
            if (text) text.textContent = 'View Achievement Descriptors';
        }
    }

    // Dynamic Field Validation & Warning Notification System
    function showFieldWarning(inputElem, message) {
        const banner = document.getElementById('fieldValidationWarningBanner');
        const msgEl = document.getElementById('fieldWarningMessage');
        const titleEl = document.getElementById('fieldWarningTitle');
        
        if (banner && msgEl) {
            msgEl.innerHTML = '<i class="fa-solid fa-hand-point-right mr-1.5 text-rose-600"></i> ' + message;
            if (titleEl) titleEl.textContent = "Required Action Needed";
            banner.classList.remove('hidden');
        }

        // Resolve element if selector or ID string passed
        if (typeof inputElem === 'string') {
            inputElem = document.getElementById(inputElem) || document.querySelector(inputElem);
        }

        // Highlight element
        if (inputElem) {
            document.querySelectorAll('.field-error-highlight').forEach(el => el.classList.remove('field-error-highlight'));
            inputElem.classList.add('field-error-highlight');
            try {
                inputElem.scrollIntoView({ behavior: 'smooth', block: 'center' });
                inputElem.focus();
            } catch(e) {}
            
            const clearFn = function() {
                inputElem.classList.remove('field-error-highlight');
                if (banner) banner.classList.add('hidden');
                inputElem.removeEventListener('input', clearFn);
                inputElem.removeEventListener('change', clearFn);
            };
            inputElem.addEventListener('input', clearFn);
            inputElem.addEventListener('change', clearFn);
        }
    }

    function dismissFieldWarning() {
        const banner = document.getElementById('fieldValidationWarningBanner');
        if (banner) banner.classList.add('hidden');
        document.querySelectorAll('.field-error-highlight').forEach(el => el.classList.remove('field-error-highlight'));
    }

    // Real-Time A-Level Subject Combination Auto-Detection Engine
    window.combinationIsManualOverride = false;

    function autoDetectS5Combination() {
        if (window.combinationIsManualOverride) return;

        const getG = (id) => {
            const el = document.getElementById(id);
            return el ? el.value : '';
        };

        const mat = getG('uce_mat');
        const phy = getG('uce_phy');
        const che = getG('uce_che');
        const bio = getG('uce_bio');
        const his = getG('uce_his');
        const geo = getG('uce_geo');
        const eng = getG('uce_eng');
        const cre = getG('uce_cre_ire');
        const elec1Grade = getG('uce_elec1_grade');

        const points = { 'A': 5, 'B': 4, 'C': 3, 'D': 2, 'E': 1 };
        const matPts = points[mat] || 0;
        const phyPts = points[phy] || 0;
        const chePts = points[che] || 0;
        const bioPts = points[bio] || 0;
        const hisPts = points[his] || 0;
        const geoPts = points[geo] || 0;
        const engPts = points[eng] || 0;
        const crePts = points[cre] || 0;
        const el1Pts = points[elec1Grade] || 0;

        // Science Combination Scores
        const pcmScore = (matPts >= 3 && phyPts >= 3 && chePts >= 3) ? (matPts + phyPts + chePts) : (matPts >= 2 && phyPts >= 2 ? matPts + phyPts : 0);
        const bcmScore = (bioPts >= 3 && chePts >= 3 && matPts >= 3) ? (bioPts + chePts + matPts) : 0;
        const pcbScore = (bioPts >= 3 && chePts >= 3 && phyPts >= 3) ? (bioPts + chePts + phyPts) : 0;
        const pemScore = (matPts >= 3 && phyPts >= 3 && (geoPts >= 3 || el1Pts >= 3)) ? (matPts + phyPts + Math.max(geoPts, el1Pts)) : 0;

        // Economics & Business Scores
        const megScore = (matPts >= 3 && geoPts >= 3) ? (matPts + geoPts + Math.max(hisPts, el1Pts)) : 0;

        // Arts & Humanities Scores
        const hegScore = (hisPts >= 3 && geoPts >= 3) ? (hisPts + geoPts + Math.max(matPts, el1Pts, crePts)) : 0;
        const helScore = (hisPts >= 3 && engPts >= 3) ? (hisPts + engPts + Math.max(geoPts, crePts)) : 0;
        const hldScore = (hisPts >= 3 && crePts >= 3) ? (hisPts + crePts + Math.max(geoPts, engPts)) : 0;

        let detectedCode = 'PCM/ICT';
        let detectedGroup = 'Sciences Track';
        let detectedFullName = 'PCM/ICT (Physics, Chemistry, Mathematics & ICT)';
        let detectedReason = '';

        const bestScience = Math.max(pcmScore, bcmScore, pcbScore, pemScore);
        const bestArts = Math.max(hegScore, helScore, hldScore);
        const bestEcon = megScore;

        if (bestScience >= 9 && bestScience >= bestArts && bestScience >= bestEcon) {
            if (pcmScore >= bcmScore && pcmScore >= pcbScore) {
                detectedCode = 'PCM/ICT';
                detectedFullName = 'PCM/ICT (Physics, Chemistry, Mathematics & ICT)';
                detectedReason = 'Auto-detected from your Exceptional / Outstanding performance in Mathematics (' + (mat || 'N/A') + '), Physics (' + (phy || 'N/A') + '), and Chemistry (' + (che || 'N/A') + ').';
            } else if (bcmScore >= pcbScore) {
                detectedCode = 'BCM/ICT';
                detectedFullName = 'BCM/ICT (Biology, Chemistry, Mathematics & ICT)';
                detectedReason = 'Auto-detected from your high performance in Biology (' + (bio || 'N/A') + '), Chemistry (' + (che || 'N/A') + '), and Mathematics (' + (mat || 'N/A') + ').';
            } else {
                detectedCode = 'PCB/ICT';
                detectedFullName = 'PCB/ICT (Physics, Chemistry, Biology & ICT)';
                detectedReason = 'Auto-detected from your strong foundation in Biology (' + (bio || 'N/A') + '), Chemistry (' + (che || 'N/A') + '), and Physics (' + (phy || 'N/A') + ').';
            }
            detectedGroup = 'Sciences Track';
        } else if (bestEcon >= 9 && bestEcon >= bestArts) {
            detectedCode = 'MEG/ICT';
            detectedGroup = 'Economics & Business Track';
            detectedFullName = 'MEG/ICT (Mathematics, Economics, Geography & ICT)';
            detectedReason = 'Auto-detected from your strong aptitude in Mathematics (' + (mat || 'N/A') + ') and Geography (' + (geo || 'N/A') + ').';
        } else if (bestArts >= 8) {
            if (helScore > hegScore) {
                detectedCode = 'HEL/ICT';
                detectedFullName = 'HEL/ICT (History, Economics, Literature & ICT)';
                detectedReason = 'Auto-detected based on your high performance in History (' + (his || 'N/A') + ') and English Language (' + (eng || 'N/A') + ').';
            } else if (hldScore > hegScore && crePts >= 3) {
                detectedCode = 'HLD/ICT';
                detectedFullName = 'HLD/ICT (History, Literature, Divinity & ICT)';
                detectedReason = 'Auto-detected based on your high performance in History (' + (his || 'N/A') + ') and Religious Education (' + (cre || 'N/A') + ').';
            } else {
                detectedCode = 'HEG/ICT';
                detectedFullName = 'HEG/ICT (History, Economics, Geography & ICT)';
                detectedReason = 'Auto-detected based on your high performance in History (' + (his || 'N/A') + ') and Geography (' + (geo || 'N/A') + ').';
            }
            detectedGroup = 'Arts & Humanities Track';
        } else {
            if (matPts >= hisPts) {
                detectedCode = 'PCM/ICT';
                detectedFullName = 'PCM/ICT (Physics, Chemistry, Mathematics & ICT)';
                detectedGroup = 'Sciences Track';
                detectedReason = 'Assigned optimal Science combination based on performance profile.';
            } else {
                detectedCode = 'HEG/ICT';
                detectedFullName = 'HEG/ICT (History, Economics, Geography & ICT)';
                detectedGroup = 'Arts & Humanities Track';
                detectedReason = 'Assigned optimal Arts combination based on performance profile.';
            }
        }

        const combInput = document.getElementById('combinationS5');
        if (combInput) combInput.value = detectedCode;

        const badgeEl = document.getElementById('autoCombGroupBadge');
        if (badgeEl) badgeEl.textContent = detectedGroup;

        const titleEl = document.getElementById('autoCombTitle');
        if (titleEl) titleEl.textContent = detectedFullName;

        const descEl = document.getElementById('autoCombDesc');
        if (descEl) descEl.textContent = detectedReason;
    }

    function toggleCombinationOverride() {
        const sec = document.getElementById('combinationOverrideSection');
        const btn = document.getElementById('toggleCombinationOverrideBtn');
        if (!sec) return;
        if (sec.classList.contains('hidden')) {
            sec.classList.remove('hidden');
            btn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> <span>Switch to Auto-Detect</span>';
        } else {
            sec.classList.add('hidden');
            btn.innerHTML = '<i class="fa-solid fa-sliders"></i> <span>Change / Select Manually</span>';
            window.combinationIsManualOverride = false;
            autoDetectS5Combination();
        }
    }

    function applyManualCombination(val) {
        if (!val) return;
        window.combinationIsManualOverride = true;
        const combInput = document.getElementById('combinationS5');
        if (combInput) combInput.value = val;

        const titleEl = document.getElementById('autoCombTitle');
        if (titleEl) titleEl.textContent = val;

        const badgeEl = document.getElementById('autoCombGroupBadge');
        if (badgeEl) badgeEl.textContent = 'Manually Selected';

        const descEl = document.getElementById('autoCombDesc');
        if (descEl) descEl.textContent = 'Combination explicitly selected. Click "Switch to Auto-Detect" above to restore performance-based detection.';
    }

    // UNEB NLSC Real-Time Competency Calculation (Attachment 1 & 2)
    const nlscGradePoints = { "A": 5, "B": 4, "C": 3, "D": 2, "E": 1 };
    const nlscAggEquiv    = { "A": 1, "B": 2, "C": 3, "D": 4, "E": 5 };

    function calcNLSCCompetency() {
        const selects = document.querySelectorAll('.nlsc-grade-select');
        let counts = { A: 0, B: 0, C: 0, D: 0, E: 0 };
        let totalScore = 0;
        let equivScores = [];

        selects.forEach(sel => {
            const val = sel.value;
            if (val && nlscGradePoints[val]) {
                counts[val] = (counts[val] || 0) + 1;
                totalScore += nlscGradePoints[val];
                equivScores.push(nlscAggEquiv[val]);
            }
        });

        const countAEl = document.getElementById('countA');
        const countBEl = document.getElementById('countB');
        const countCEl = document.getElementById('countC');
        const countOtherEl = document.getElementById('countOther');
        const scoreDisp = document.getElementById('competencyScoreDisplay');
        const aggS5 = document.getElementById('aggregatesS5');

        if (countAEl) countAEl.textContent = counts.A;
        if (countBEl) countBEl.textContent = counts.B;
        if (countCEl) countCEl.textContent = counts.C;
        if (countOtherEl) countOtherEl.textContent = (counts.D + counts.E);
        if (scoreDisp) scoreDisp.textContent = totalScore;

        // Best 8 aggregate equivalent (lower is better, e.g. 8 A's = 8 aggregates)
        equivScores.sort((a, b) => a - b);
        let best8Sum = 0;
        for (let i = 0; i < Math.min(8, equivScores.length); i++) {
            best8Sum += equivScores[i];
        }
        if (aggS5) aggS5.value = best8Sum;

        // Trigger Real-time Combination Auto-Detection
        autoDetectS5Combination();
    }

    document.querySelectorAll('.nlsc-grade-select').forEach(sel => {
        sel.addEventListener('change', calcNLSCCompetency);
    });
    calcNLSCCompetency();

    // Auto calculate PLE aggregates
    const gradeValues = { "D1":1,"D2":2,"C3":3,"C4":4,"C5":5,"C6":6,"P7":7,"P8":8,"F9":9 };
    const pleSelects = document.querySelectorAll(".ple-grade-select");
    const aggregatesS1 = document.getElementById("aggregatesS1");

    function calcPLEAggregates() {
        let total = 0;
        pleSelects.forEach(sel => {
            total += (gradeValues[sel.value] || 0);
        });
        if (aggregatesS1) aggregatesS1.value = total;
        const divS1 = document.getElementById("divisionS1");
        if (divS1) {
            if (total >= 4 && total <= 12) divS1.value = "Division 1";
            else if (total <= 23) divS1.value = "Division 2";
            else if (total <= 29) divS1.value = "Division 3";
            else if (total <= 34) divS1.value = "Division 4";
            else divS1.value = "Division U";
        }
    }
    pleSelects.forEach(sel => sel.addEventListener("change", calcPLEAggregates));
    calcPLEAggregates();

    // Step Navigation
    function goToStep(step) {
        if (step > currentStep) {
            if (!validateStep(currentStep)) return;
        }

        dismissFieldWarning();

        // Hide all steps
        document.getElementById('step1').classList.add('hidden');
        document.getElementById('step2').classList.add('hidden');
        document.getElementById('step3').classList.add('hidden');

        // Show requested step
        document.getElementById('step' + step).classList.remove('hidden');

        // Update progress bar
        const fillPercent = ((step - 1) / 2) * 100;
        document.getElementById('progressFill').style.width = fillPercent + '%';

        // Update step indicators
        for (let i = 1; i <= 3; i++) {
            const ind = document.getElementById('stepIndicator' + i);
            ind.classList.remove('active', 'completed');
            if (i === step) {
                ind.classList.add('active');
            } else if (i < step) {
                ind.classList.add('completed');
            }
        }

        currentStep = step;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Comprehensive Step Validation with explicit field warnings
    function validateStep(step) {
        dismissFieldWarning();

        // STEP 1 VALIDATIONS: Student Bio Data
        if (step === 1) {
            const surname = document.querySelector("input[name='surname']");
            if (!surname || surname.value.trim().length < 2) {
                showFieldWarning(surname, "You have not entered the candidate's Surname (minimum 2 letters required).");
                return false;
            }

            const firstname = document.querySelector("input[name='firstname']");
            if (!firstname || firstname.value.trim().length < 2) {
                showFieldWarning(firstname, "You have not entered the candidate's First Name.");
                return false;
            }

            const sex = document.querySelector("select[name='sex']");
            if (!sex || !sex.value) {
                showFieldWarning(sex, "You have not selected the candidate's Sex / Gender.");
                return false;
            }

            const dobInp = document.getElementById('dobInput');
            if (!dobInp || !dobInp.value) {
                showFieldWarning(dobInp, "You have not selected the candidate's Date of Birth.");
                return false;
            }
            const bDate = new Date(dobInp.value);
            const now = new Date();
            let candidateAge = now.getFullYear() - bDate.getFullYear();
            const m = now.getMonth() - bDate.getMonth();
            if (m < 0 || (m === 0 && now.getDate() < bDate.getDate())) {
                candidateAge--;
            }
            if (candidateAge < 9) {
                showFieldWarning(dobInp, "Candidate must be at least 9 years of age to apply. Current calculated age is " + candidateAge + " years.");
                return false;
            }
            if (candidateAge > 26) {
                showFieldWarning(dobInp, "Please enter a realistic date of birth (candidate age between 9 and 25 years).");
                return false;
            }

            const religion = document.querySelector("select[name='religion']");
            if (!religion || !religion.value) {
                showFieldWarning(religion, "You have not selected the candidate's Religion.");
                return false;
            }

            const passInp = document.getElementById('passportFile');
            if (!passInp || !passInp.files || passInp.files.length === 0) {
                showFieldWarning(passInp, "You have not uploaded the candidate's Passport Photograph.");
                return false;
            }
            const passExt = passInp.files[0].name.split('.').pop().toLowerCase();
            if (!['png', 'jpg', 'jpeg', 'webp'].includes(passExt)) {
                showFieldWarning(passInp, "Invalid passport photo format. Please upload a PNG, JPG, JPEG, or WEBP image.");
                return false;
            }
            if (passInp.files[0].size > 5 * 1024 * 1024) {
                showFieldWarning(passInp, "Passport photo file size must not exceed 5MB.");
                return false;
            }
        }

        // STEP 2 VALIDATIONS: Academic Credentials
        if (step === 2) {
            const classVal = classOfEntrySelect.value;

            if (classVal === 'S1') {
                const fs = document.getElementById('formerSchoolS1') || document.querySelector("input[name='former_school_s1']");
                if (!fs || !fs.value.trim()) {
                    showFieldWarning(fs || "input[name='former_school_s1']", "You have not entered the candidate's Former Primary School.");
                    return false;
                }

                const idx = document.getElementById('indexNumberS1') || document.querySelector("input[name='index_number_s1']");
                if (!idx || !idx.value.trim()) {
                    showFieldWarning(idx || "input[name='index_number_s1']", "You have not entered the candidate's PLE Index Number.");
                    return false;
                }

                const eng = document.getElementById('english') || document.querySelector("select[name='english']");
                if (!eng || !eng.value) {
                    showFieldWarning(eng || "select[name='english']", "You have not selected a grade for English Language.");
                    return false;
                }

                const mat = document.getElementById('mathematics') || document.querySelector("select[name='mathematics']");
                if (!mat || !mat.value) {
                    showFieldWarning(mat || "select[name='mathematics']", "You have not selected a grade for Mathematics.");
                    return false;
                }

                const sci = document.getElementById('science') || document.querySelector("select[name='science']");
                if (!sci || !sci.value) {
                    showFieldWarning(sci || "select[name='science']", "You have not selected a grade for Science.");
                    return false;
                }

                const sst = document.getElementById('sst') || document.querySelector("select[name='sst']");
                if (!sst || !sst.value) {
                    showFieldWarning(sst || "select[name='sst']", "You have not selected a grade for Social Studies (SST).");
                    return false;
                }

                const pleAggInp = document.getElementById('aggregatesS1') || document.querySelector("input[name='aggregates_s1']");
                const pleAgg = parseInt(pleAggInp ? pleAggInp.value : 0, 10) || 0;
                if (cutoffPassmarks['S1'] !== null && cutoffPassmarks['S1'] !== '') {
                    const cutoffS1 = parseInt(cutoffPassmarks['S1'], 10);
                    if (pleAgg > cutoffS1) {
                        showFieldWarning(pleAggInp, "Cutoff Aggregates Exceeded: Your PLE aggregates (" + pleAgg + ") exceed the school's maximum admission cutoff of " + cutoffS1 + " aggregates.");
                        return false;
                    }
                }

                const s1Slip = document.getElementById('resultSlipS1') || document.querySelector("input[name='result_slip_s1']");
                if (!s1Slip || !s1Slip.files || s1Slip.files.length === 0) {
                    showFieldWarning(s1Slip || "input[name='result_slip_s1']", "You have not uploaded the candidate's official PLE Result Slip.");
                    return false;
                }
                const s1Ext = s1Slip.files[0].name.split('.').pop().toLowerCase();
                if (!['pdf', 'png', 'jpg', 'jpeg'].includes(s1Ext)) {
                    showFieldWarning(s1Slip, "Please attach your PLE result slip in PDF, PNG, JPG, or JPEG format.");
                    return false;
                }
            } else if (classVal === 'S5') {
                const fs5 = document.getElementById('formerSchoolS5');
                if (!fs5 || !fs5.value.trim()) {
                    showFieldWarning(fs5, "You have not entered the Former O-Level Secondary School.");
                    return false;
                }

                const idx5 = document.getElementById('indexNumberS5');
                if (!idx5 || !idx5.value.trim()) {
                    showFieldWarning(idx5, "You have not entered the candidate's UNEB UCE Index Number.");
                    return false;
                }

                const pw = document.getElementById('uceProjectWork');
                if (!pw || !pw.value) {
                    showFieldWarning(pw, "You have not selected the UCE Project Work Achievement Grade.");
                    return false;
                }

                // Check S5 Compulsory Subjects
                const uceSubjects = [
                    { id: 'uce_eng', name: 'English Language' },
                    { id: 'uce_mat', name: 'Mathematics' },
                    { id: 'uce_phy', name: 'Physics' },
                    { id: 'uce_che', name: 'Chemistry' },
                    { id: 'uce_bio', name: 'Biology' },
                    { id: 'uce_his', name: 'History & Political Educ.' },
                    { id: 'uce_geo', name: 'Geography' }
                ];
                for (let subj of uceSubjects) {
                    const elem = document.getElementById(subj.id);
                    if (!elem || !elem.value) {
                        showFieldWarning(elem, "You have not selected a grade for " + subj.name + ".");
                        return false;
                    }
                }

                // Elective Subject 1 Check
                const el1Grade = document.getElementById('uce_elec1_grade');
                if (!el1Grade || !el1Grade.value) {
                    showFieldWarning(el1Grade, "You have not selected a grade for Elective Subject 1.");
                    return false;
                }

                // Cutoff verification
                const uceAgg = parseInt(document.getElementById('aggregatesS5').value, 10) || 0;
                if (cutoffPassmarks['S5'] !== null && cutoffPassmarks['S5'] !== '') {
                    const cutoffS5 = parseInt(cutoffPassmarks['S5'], 10);
                    if (uceAgg > cutoffS5) {
                        showFieldWarning(document.getElementById('aggregatesS5'), "Your calculated UCE aggregates (" + uceAgg + ") exceed the maximum admission cutoff of " + cutoffS5 + " aggregates for Senior Five.");
                        return false;
                    }
                }

                const s5Slip = document.getElementById('resultSlipS5');
                if (!s5Slip || !s5Slip.files || s5Slip.files.length === 0) {
                    showFieldWarning(s5Slip, "You have not uploaded the official UNEB UCE Result Slip.");
                    return false;
                }
                const s5Ext = s5Slip.files[0].name.split('.').pop().toLowerCase();
                if (!['pdf', 'png', 'jpg', 'jpeg'].includes(s5Ext)) {
                    showFieldWarning(s5Slip, "Please attach your UCE result slip in PDF, PNG, JPG, or JPEG format.");
                    return false;
                }
            } else if (classVal === 'S6') {
                const s6School = document.getElementById('s6FormerSchool');
                if (!s6School || !s6School.value.trim()) {
                    showFieldWarning(s6School, "You have not entered the Former Senior Five (S5) School.");
                    return false;
                }

                const s6Passes = document.getElementById('s6PrincipalPasses');
                if (!s6Passes || !s6Passes.value) {
                    showFieldWarning(s6Passes, "You have not selected the S5 Principal Passes achieved.");
                    return false;
                }

                const s5Slip = document.getElementById('resultSlipS5');
                if (!s5Slip || !s5Slip.files || s5Slip.files.length === 0) {
                    showFieldWarning(s5Slip, "You have not uploaded the official UCE Result Slip.");
                    return false;
                }

                const s6Rep = document.getElementById('s6ReportFile');
                if (!s6Rep || !s6Rep.files || s6Rep.files.length === 0) {
                    showFieldWarning(s6Rep, "You have not uploaded the Senior Five (S5) Report Card / Transcript.");
                    return false;
                }
            } else {
                // S2, S3, S4 Transfers
                const fSchoolTr = document.getElementById('formerSchoolTransfer') || document.querySelector("input[name='former_school_transfer']");
                if (!fSchoolTr || !fSchoolTr.value.trim()) {
                    showFieldWarning(fSchoolTr || "input[name='former_school_transfer']", "You have not entered the Former School attended.");
                    return false;
                }

                const lastCls = document.getElementById('lastClassAttended') || document.querySelector("select[name='last_class_attended']");
                if (!lastCls || !lastCls.value) {
                    showFieldWarning(lastCls || "select[name='last_class_attended']", "You have not selected the Last Class Attended.");
                    return false;
                }

                const divTr = document.getElementById('divisionTransfer') || document.querySelector("select[name='division_transfer']");
                if (!divTr || !divTr.value) {
                    showFieldWarning(divTr || "select[name='division_transfer']", "You have not selected the Previous Class Standing / Division.");
                    return false;
                }

                const trReason = document.getElementById('transferReason') || document.querySelector("textarea[name='transfer_reason']");
                if (!trReason || !trReason.value.trim()) {
                    showFieldWarning(trReason || "textarea[name='transfer_reason']", "You have not provided the Reason for School Transfer.");
                    return false;
                }

                const trSlip = document.getElementById('resultSlipTransfer') || document.querySelector("input[name='result_slip_transfer']");
                if (!trSlip || !trSlip.files || trSlip.files.length === 0) {
                    showFieldWarning(trSlip || "input[name='result_slip_transfer']", "You have not uploaded the Academic Report Card from your former school.");
                    return false;
                }
            }
        }

        // STEP 3 VALIDATIONS: Parent / Guardian & Submit
        if (step === 3) {
            const pName = document.querySelector("input[name='parent_name']");
            if (!pName || pName.value.trim().length < 2) {
                showFieldWarning(pName, "You have not entered the Parent / Guardian Full Name.");
                return false;
            }

            const pNin = document.querySelector("input[name='parent_nin']");
            if (pNin) {
                pNin.value = pNin.value.trim().toUpperCase();
                const ninRegex = /^(CM|CF|RM|RF)[0-9A-Z]{12}$/;
                if (!ninRegex.test(pNin.value)) {
                    showFieldWarning(pNin, "Please enter a valid Ugandan 14-character NIN starting with CM, CF, RM, or RF (e.g. CM89012345ABCD).");
                    return false;
                }
            }

            const pPhone = document.querySelector("input[name='parent_phone']");
            if (pPhone) {
                const phoneRegex = /^(?:\+256|256|0)[37][0-9]{8}$/;
                if (!phoneRegex.test(pPhone.value.trim())) {
                    showFieldWarning(pPhone, "Please enter a valid Uganda telephone number (e.g. 0772123456 or +256772123456).");
                    return false;
                }
            }

            const pEmail = document.querySelector("input[name='parent_email']");
            if (pEmail) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(pEmail.value.trim())) {
                    showFieldWarning(pEmail, "Please enter a valid Parent / Guardian Email Address.");
                    return false;
                }
            }

            const pDistrict = document.getElementById('parentDistrict');
            if (!pDistrict || !pDistrict.value.trim()) {
                showFieldWarning(pDistrict, "You have not entered the District of Residence.");
                return false;
            }

            const pSubcounty = document.getElementById('parentSubcounty');
            if (!pSubcounty || !pSubcounty.value.trim()) {
                showFieldWarning(pSubcounty, "You have not entered the Sub-county / Town of Residence.");
                return false;
            }

            const parentIdInp = document.getElementById('parentIdFile');
            if (!parentIdInp || !parentIdInp.files || parentIdInp.files.length === 0) {
                showFieldWarning(parentIdInp, "You have not uploaded the Parent / Guardian National ID Document.");
                return false;
            }
            const idExt = parentIdInp.files[0].name.split('.').pop().toLowerCase();
            if (!['pdf', 'png', 'jpg', 'jpeg'].includes(idExt)) {
                showFieldWarning(parentIdInp, "Please upload your Parent National ID in PDF, PNG, JPG, or JPEG format.");
                return false;
            }

            const declCheck = document.getElementById('declarationCheck');
            if (declCheck && !declCheck.checked) {
                showFieldWarning(declCheck, "You must check the declaration box confirming truthfulness and authenticity of your documents.");
                return false;
            }

            const v = (document.getElementById('parentVillage') || {}).value || '';
            const p = (document.getElementById('parentParish') || {}).value || '';
            const s = (document.getElementById('parentSubcounty') || {}).value || '';
            const d = (document.getElementById('parentDistrict') || {}).value || '';
            const addr = [v, p, s, d].filter(Boolean).join(', ');
            const pAddr = document.getElementById('parentAddress');
            if (pAddr) pAddr.value = addr;
        }

        return true;
    }

    // Clear custom validity and validation error highlight on input
    document.querySelectorAll("input, select, textarea").forEach(inp => {
        inp.addEventListener("input", function() { 
            if (this.name === 'parent_nin') this.value = this.value.toUpperCase();
            this.setCustomValidity(""); 
            if (this.classList.contains('field-error-highlight')) {
                this.classList.remove('field-error-highlight');
                dismissFieldWarning();
            }
        });
        inp.addEventListener("change", function() { 
            this.setCustomValidity(""); 
            if (this.classList.contains('field-error-highlight')) {
                this.classList.remove('field-error-highlight');
                dismissFieldWarning();
            }
        });
    });

    document.getElementById('admissionForm').addEventListener('submit', function(e) {
        if (!validateStep(3)) {
            e.preventDefault();
            return;
        }
        const v = (document.getElementById('parentVillage') || {}).value || '';
        const p = (document.getElementById('parentParish') || {}).value || '';
        const s = (document.getElementById('parentSubcounty') || {}).value || '';
        const d = (document.getElementById('parentDistrict') || {}).value || '';
        const addr = [v, p, s, d].filter(Boolean).join(', ');
        const pAddr = document.getElementById('parentAddress');
        if (pAddr) pAddr.value = addr;

        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
    });

    // Real-time Age Warning on DOB input
    const dobInpElem = document.getElementById('dobInput');
    const ageWarningElem = document.getElementById('ageWarning');
    if (dobInpElem) {
        dobInpElem.addEventListener('change', function() {
            if (!this.value) {
                if (ageWarningElem) ageWarningElem.classList.add('hidden');
                return;
            }
            const bDate = new Date(this.value);
            const now = new Date();
            let exactAge = now.getFullYear() - bDate.getFullYear();
            const m = now.getMonth() - bDate.getMonth();
            if (m < 0 || (m === 0 && now.getDate() < bDate.getDate())) {
                exactAge--;
            }
            if (exactAge < 9) {
                if (ageWarningElem) {
                    ageWarningElem.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-1"></i> Applicant must be 9 years or older to apply (Calculated: ' + exactAge + ' yrs).';
                    ageWarningElem.classList.remove('hidden');
                }
            } else {
                if (ageWarningElem) ageWarningElem.classList.add('hidden');
            }
        });
    }

    // Back to top floating button logic
    const bttBtn = document.getElementById('backToTopBtn');
    if (bttBtn) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 250) {
                bttBtn.classList.remove('opacity-0', 'pointer-events-none');
                bttBtn.classList.add('opacity-100', 'pointer-events-auto');
            } else {
                bttBtn.classList.add('opacity-0', 'pointer-events-none');
                bttBtn.classList.remove('opacity-100', 'pointer-events-auto');
            }
        });
        bttBtn.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // Legal Modals (Privacy Policy & Terms of Use)
    function openLegalModal(modalId) {
        const m = document.getElementById(modalId);
        if (m) m.classList.remove('hidden');
    }
    function closeLegalModal(modalId) {
        const m = document.getElementById(modalId);
        if (m) m.classList.add('hidden');
    }
    </script>

    <!-- Privacy Policy Modal -->
    <div id="privacyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-shield-halved"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Admissions Privacy Policy</h3>
                        <p class="text-[11px] text-slate-500">Protection of Candidate & Guardian Data under Uganda Law</p>
                    </div>
                </div>
                <button type="button" onclick="closeLegalModal('privacyModal')" class="text-slate-400 hover:text-slate-600 p-1">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto space-y-4 text-xs text-slate-600 leading-relaxed">
                <div>
                    <h4 class="font-bold text-slate-800 text-sm mb-1">1. Information We Collect</h4>
                    <p>When you submit an application through the <?= htmlspecialchars($school_short_name) ?> Online Admissions Portal, we collect candidate academic records, UNEB index numbers, PLE/UCE result slips, student bio-data, parent/guardian contact details, National Identification Numbers (NIN), and official ID documentation.</p>
                </div>
                <div>
                    <h4 class="font-bold text-slate-800 text-sm mb-1">2. Purpose of Data Processing</h4>
                    <p>All information provided is strictly processed for evaluating applicant eligibility, determining merit-based placement, issuing official admission offer letters, communicating intake decisions, and maintaining institutional academic archives in compliance with Ministry of Education & Sports regulations.</p>
                </div>
                <div>
                    <h4 class="font-bold text-slate-800 text-sm mb-1">3. Compliance with Uganda Data Protection and Privacy Act, 2019</h4>
                    <p>In accordance with the Data Protection and Privacy Act, 2019 of the Republic of Uganda, all personal and sensitive academic records are handled with stringent confidentiality. Data is never sold, leased, or disclosed to unauthorized third parties.</p>
                </div>
                <div>
                    <h4 class="font-bold text-slate-800 text-sm mb-1">4. Document Security & Access Control</h4>
                    <p>Uploaded documents (National IDs, result slips, passport photos) are stored on secure servers with restricted cryptographic access available solely to certified Admissions Registry officers and school administration.</p>
                </div>
            </div>
            <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" onclick="closeLegalModal('privacyModal')" class="px-5 py-2 rounded-xl text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-sm">
                    Understood & Close
                </button>
            </div>
        </div>
    </div>

    <!-- Terms of Use Modal -->
    <div id="termsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-gold-50 text-gold-700 flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-file-contract"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Admissions Portal Terms of Use</h3>
                        <p class="text-[11px] text-slate-500">Guidelines & Regulations for Online Admissions Applications</p>
                    </div>
                </div>
                <button type="button" onclick="closeLegalModal('termsModal')" class="text-slate-400 hover:text-slate-600 p-1">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto space-y-4 text-xs text-slate-600 leading-relaxed">
                <div>
                    <h4 class="font-bold text-slate-800 text-sm mb-1">1. Authenticity of Academic Records</h4>
                    <p>Applicants and guardians certify that all submitted PLE, UCE, or transfer transcripts, index numbers, and grades are genuine UNEB-certified records. The submission of falsified documents constitutes a criminal offense and results in immediate disqualification and reporting to UNEB authorities.</p>
                </div>
                <div>
                    <h4 class="font-bold text-slate-800 text-sm mb-1">2. Provisional Nature of Admission Offers</h4>
                    <p>All online offers of admission are provisional and strictly subject to mandatory physical verification of original UNEB result slips and birth certificates on the official reporting date at the school campus.</p>
                </div>
                <div>
                    <h4 class="font-bold text-slate-800 text-sm mb-1">3. Merit Cutoff & Capacity Constraints</h4>
                    <p>Admission is granted strictly based on configured institutional cut-off aggregates, subject combination suitability, and intake capacity. The Admissions Board reserves the right to close admissions upon reaching class limits.</p>
                </div>
                <div>
                    <h4 class="font-bold text-slate-800 text-sm mb-1">4. Tuition & Fee Payment Regulations</h4>
                    <p>Admission fees and functional levies must be settled through the school's authorized bank accounts or digital collection channels before reporting. Fees once paid are non-refundable and non-transferable.</p>
                </div>
            </div>
            <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" onclick="closeLegalModal('termsModal')" class="px-5 py-2 rounded-xl text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-sm">
                    I Agree & Close
                </button>
            </div>
        </div>
    </div>
</body>
</html>
