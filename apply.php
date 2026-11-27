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

// Document upload requirement policies
$show_parent_id_upload      = getSetting($pdo, 'show_parent_id_upload', '1');
$show_passport_photo_upload = getSetting($pdo, 'show_passport_photo_upload', '1');

// Fees Matrices for Public Verification
$fees_classes = [
    'S1' => [
        'name' => 'Senior One (S1)',
        'tuition' => (int)getSetting($pdo, 'fees_s1_tuition', 550000),
        'admission' => (int)getSetting($pdo, 'fees_s1_admission', 100000),
        'functional' => (int)getSetting($pdo, 'fees_s1_functional', 150000),
    ],
    'S2' => [
        'name' => 'Senior Two (S2)',
        'tuition' => (int)getSetting($pdo, 'fees_s2_tuition', 560000),
        'admission' => (int)getSetting($pdo, 'fees_s2_admission', 100000),
        'functional' => (int)getSetting($pdo, 'fees_s2_functional', 155000),
    ],
    'S3' => [
        'name' => 'Senior Three (S3)',
        'tuition' => (int)getSetting($pdo, 'fees_s3_tuition', 570000),
        'admission' => (int)getSetting($pdo, 'fees_s3_admission', 100000),
        'functional' => (int)getSetting($pdo, 'fees_s3_functional', 160000),
    ],
    'S4' => [
        'name' => 'Senior Four (S4)',
        'tuition' => (int)getSetting($pdo, 'fees_s4_tuition', 580000),
        'admission' => (int)getSetting($pdo, 'fees_s4_admission', 110000),
        'functional' => (int)getSetting($pdo, 'fees_s4_functional', 165000),
    ],
    'S5' => [
        'name' => 'Senior Five (S5)',
        'tuition' => (int)getSetting($pdo, 'fees_s5_tuition', 600000),
        'admission' => (int)getSetting($pdo, 'fees_s5_admission', 120000),
        'functional' => (int)getSetting($pdo, 'fees_s5_functional', 180000),
    ],
    'S6' => [
        'name' => 'Senior Six (S6)',
        'tuition' => (int)getSetting($pdo, 'fees_s6_tuition', 620000),
        'admission' => (int)getSetting($pdo, 'fees_s6_admission', 120000),
        'functional' => (int)getSetting($pdo, 'fees_s6_functional', 185000),
    ],
];

// Cloudflare Turnstile anti-bot settings
$cf_turnstile_enabled  = getSetting($pdo, 'cf_turnstile_enabled', '0');
$cf_turnstile_site_key = getSetting($pdo, 'cf_turnstile_site_key', '');

// Dynamic A-Level Combinations from Settings
$defaultCombinations = [
    'Sciences' => [
        'PCM/ICT' => 'PCM/ICT (Physics, Chemistry, Mathematics & ICT)',
        'PCB/ICT' => 'PCB/ICT (Physics, Chemistry, Biology & ICT)',
        'PCB/SMT' => 'PCB/SMT (Physics, Chemistry, Biology & Sub-Math)',
        'BCM/ICT' => 'BCM/ICT (Biology, Chemistry, Mathematics & ICT)',
        'PEM/ICT' => 'PEM/ICT (Physics, Economics, Mathematics & ICT)',
        'MEG/ICT' => 'MEG/ICT (Mathematics, Economics, Geography & ICT)',
        'BCG/ICT' => 'BCG/ICT (Biology, Chemistry, Geography & ICT)',
        'BCG/SMT' => 'BCG/SMT (Biology, Chemistry, Geography & Sub-Math)',
        'MAG/ICT' => 'MAG/ICT (Mathematics, Agriculture, Geography & ICT)',
    ],
    'Arts & Humanities' => [
        'HEG/ICT' => 'HEG/ICT (History, Economics, Geography & ICT)',
        'HEG/SMT' => 'HEG/SMT (History, Economics, Geography & Sub-Math)',
        'HEL/ICT' => 'HEL/ICT (History, Economics, Literature & ICT)',
        'HEL/SMT' => 'HEL/SMT (History, Economics, Literature & Sub-Math)',
        'HLD/ICT' => 'HLD/ICT (History, Literature, Divinity & ICT)',
        'HLD/SMT' => 'HLD/SMT (History, Literature, Divinity & Sub-Math)',
        'DEG/ICT' => 'DEG/ICT (Divinity, Economics, Geography & ICT)',
        'DEG/SMT' => 'DEG/SMT (Divinity, Economics, Geography & Sub-Math)',
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

// Dynamic UCE Subjects List for S5 Form
$uceSubjectsList = getUceSubjectsList($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Admission Application | <?= htmlspecialchars($school_short_name) ?></title>
<link rel="icon" type="image/png" href="<?= !empty($school_logo) ? htmlspecialchars($school_logo) : 'images/default_logo.png' ?>">
<?php if ($cf_turnstile_enabled === '1' && !empty($cf_turnstile_site_key)): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
@keyframes feesAttentionPulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 0 0 8px rgba(220, 38, 38, 0);
    }
}
.fees-attention-pulse {
    animation: feesAttentionPulse 1.5s infinite ease-in-out;
}
</style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased min-h-screen flex flex-col py-6 sm:py-10 px-4">

    <!-- Container Card -->
    <div class="max-w-3xl w-full mx-auto space-y-6">

        <!-- Top Header & School Identity -->
        <div class="bg-white rounded-2xl border border-slate-200 p-3 sm:p-4 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
            <a href="index.php" class="flex items-center gap-2.5 sm:gap-3 group min-w-0 shrink" title="Back to Main Portal">
                <div class="w-11 h-11 sm:w-13 sm:h-13 rounded-2xl bg-white border border-slate-200 p-1.5 flex items-center justify-center shrink-0 group-hover:scale-105 transition shadow-sm">
                    <img src="<?= htmlspecialchars($school_logo) ?>" alt="<?= htmlspecialchars($school_short_name) ?> Logo" class="max-w-full max-h-full w-auto h-auto object-contain">
                </div>
                <div class="min-w-0 overflow-hidden">
                    <h1 class="brand-school-name text-[13px] sm:text-base lg:text-[17px] font-black text-slate-900 tracking-tight leading-tight uppercase group-hover:text-brand-700 transition whitespace-nowrap truncate" style="word-spacing: -0.05em;">
                        <?= htmlspecialchars(!empty($school_short_name) ? $school_short_name : $school_name) ?>
                    </h1>
                    <?php if (!empty($school_motto)): ?>
                        <p class="brand-school-motto text-[10px] sm:text-[11px] font-bold text-gold-600 italic tracking-wide leading-tight truncate whitespace-nowrap mt-0.5" title="<?= htmlspecialchars($school_motto) ?>">
                            “<?= htmlspecialchars($school_motto) ?>”
                        </p>
                    <?php else: ?>
                        <p class="brand-school-motto text-[9.5px] sm:text-[11px] font-semibold text-gold-600 uppercase tracking-wider leading-tight whitespace-nowrap">
                            Online Admissions Portal
                        </p>
                    <?php endif; ?>
                </div>
            </a>

            <div class="flex items-center gap-2 shrink-0 w-full sm:w-auto pt-2.5 sm:pt-0 border-t sm:border-t-0 border-slate-100 justify-end flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 font-extrabold text-[10px] sm:text-[11px]">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Admissions Open &bull; AY <?= htmlspecialchars($academic_year) ?>
                </span>
                <a href="<?= htmlspecialchars(getSchoolWebsiteUrl($pdo)) ?>" target="_blank" class="px-3 py-1.5 sm:py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition flex items-center gap-1.5">
                    <i class="fa-solid fa-globe text-gold-600 text-[11px]"></i>
                    <span class="hidden xs:inline">Website</span>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-slate-400"></i>
                </a>
                <a href="index.php" class="px-3.5 py-1.5 sm:py-2 rounded-xl text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 shadow-sm transition flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left text-[11px]"></i>
                    <span>Portal</span>
                </a>
            </div>
        </div>

        <!-- Class Selection Bar -->
        <div class="bg-white rounded-xl border border-slate-200 p-2 sm:p-2.5 shadow-xs">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-2">
                <span class="text-[11px] font-bold text-slate-500 uppercase px-1 tracking-wider flex items-center gap-1.5 shrink-0">
                    <i class="fa-solid fa-layer-group text-brand-700 text-xs"></i> Admission Category:
                </span>
                <div class="grid grid-cols-3 gap-1.5 w-full sm:w-auto max-w-full">
                    <button type="button" id="tabS1" class="class-tab-btn px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-lg text-[11px] sm:text-xs font-bold transition text-center leading-tight bg-brand-700 text-white shadow-xs" onclick="selectClassCategory('S1')">
                        Senior One (S1) <?= !$statusS1['is_open'] ? '<span class="block sm:inline text-[9px] text-rose-500 font-extrabold">(Closed)</span>' : '' ?>
                    </button>
                    <button type="button" id="tabS5" class="class-tab-btn px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-lg text-[11px] sm:text-xs font-bold transition text-center leading-tight bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200/60" onclick="selectClassCategory('S5')">
                        Senior Five (S5) <?= !$statusS5['is_open'] ? '<span class="block sm:inline text-[9px] text-rose-500 font-extrabold">(Closed)</span>' : '' ?>
                    </button>
                    <button type="button" id="tabTransfers" class="class-tab-btn px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-lg text-[11px] sm:text-xs font-bold transition text-center leading-tight bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200/60" onclick="selectClassCategory('OTHER')">
                        Transfers (S2-S6) <?= !$statusTrans['is_open'] ? '<span class="block sm:inline text-[9px] text-rose-500 font-extrabold">(Closed)</span>' : '' ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Fees Structure Review Card (Check Before Applying) -->
        <?php 
        $feesMatrix = getFeesStructureMatrix($pdo); 
        $feesYear   = !empty($academic_year) ? $academic_year : getAcademicYear($pdo);
        ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-3.5 sm:p-5">
            <div class="flex items-center justify-between gap-3 cursor-pointer" onclick="toggleFeesStructure()">
                <div>
                    <h4 class="text-xs sm:text-sm font-black text-slate-900 uppercase tracking-wide">
                        Fees structure for <?= htmlspecialchars($feesYear) ?>
                    </h4>
                    <p class="text-[11px] text-slate-500 mt-0.5">Review the approved fees structure breakdown before submitting your application below.</p>
                </div>
                <button type="button" id="feesToggleBtn" class="fees-attention-pulse px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-red-600 hover:bg-red-700 shadow-xs ring-1 ring-red-400/50 flex items-center gap-1.5 transition-all duration-300 hover:scale-105 active:scale-95 shrink-0">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-yellow-300"></span>
                    </span>
                    <span id="feesToggleLabel">View Fees</span>
                    <i class="fa-solid fa-chevron-down transition-transform duration-200 text-[10px]" id="feesToggleIcon"></i>
                </button>
            </div>

            <!-- Fees Table Content (Collapsed by default) -->
            <div id="feesStructureContent" class="hidden mt-4 pt-4 border-t border-slate-100">
                <!-- Mobile Controls Header -->
                <div class="md:hidden flex flex-col gap-3 mb-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-wide">
                            <i class="fa-solid fa-layer-group text-brand-700 mr-1"></i> Class Fees Breakdown
                        </span>
                        <button type="button" onclick="toggleMobileFeesView('applyFeesMobileCardView', 'applyFeesTableViewContainer', 'applyFeesViewToggleLabel', 'applyFeesViewToggleIcon')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-200 transition">
                            <i class="fa-solid fa-table-list text-xs text-brand-700" id="applyFeesViewToggleIcon"></i>
                            <span id="applyFeesViewToggleLabel">Table View</span>
                        </button>
                    </div>

                    <!-- Class filter pill chips -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none" style="-webkit-overflow-scrolling: touch;">
                        <button type="button" onclick="filterFeesCards('all', this, 'fees-card-apply')" class="fees-filter-btn-apply px-3 py-1.5 rounded-xl bg-brand-700 text-white text-xs font-bold shadow-xs whitespace-nowrap transition">All Classes</button>
                        <?php foreach ($feesMatrix['rows'] as $r): 
                            $cId = preg_replace('/[^A-Za-z0-9]/', '', strtolower($r['class']));
                        ?>
                        <button type="button" onclick="filterFeesCards('<?= $cId ?>', this, 'fees-card-apply')" class="fees-filter-btn-apply px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-bold border border-slate-200 whitespace-nowrap transition"><?= htmlspecialchars($r['class']) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 1. Mobile Cards View (Visible by default on mobile, hidden on tablet/desktop) -->
                <div id="applyFeesMobileCardView" class="block md:hidden space-y-4">
                    <?php foreach ($feesMatrix['rows'] as $row): 
                        $cId = preg_replace('/[^A-Za-z0-9]/', '', strtolower($row['class']));
                        $cCode = strtoupper(str_replace(['.', ' ', '-'], '', $row['class']));
                        $isAlevel = in_array($cCode, ['S5', 'S6']);
                    ?>
                    <div class="fees-card-apply bg-white rounded-2xl border-2 border-slate-200/90 shadow-sm overflow-hidden transition-all duration-200" data-class="<?= $cId ?>">
                        <!-- Card Header -->
                        <div class="p-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-8 h-8 rounded-xl bg-brand-700 text-white font-black text-xs flex items-center justify-center shadow-xs">
                                    <?= htmlspecialchars($row['class']) ?>
                                </span>
                                <div>
                                    <h4 class="font-black text-slate-900 text-xs leading-tight">
                                        Senior <?= substr($cCode, 1) ?> (<?= htmlspecialchars($row['class']) ?>)
                                    </h4>
                                    <span class="text-[9.5px] font-bold text-slate-500 uppercase tracking-wider">
                                        <?= $isAlevel ? 'Advanced Level (A-Level)' : 'Ordinary Level (O-Level)' ?>
                                    </span>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[9.5px] font-extrabold bg-brand-50 text-brand-800 border border-brand-200">
                                Approved
                            </span>
                        </div>

                        <!-- Fee Line Items -->
                        <div class="p-3 space-y-1.5 text-xs">
                            <?php foreach ($feesMatrix['columns'] as $col): 
                                $val = trim($row[$col['id']] ?? '');
                                if (empty($val) || $val === '-') continue;
                            ?>
                            <div class="flex items-center justify-between py-1 border-b border-slate-100 last:border-b-0 gap-2">
                                <div class="min-w-0">
                                    <span class="font-bold text-slate-800 text-[11px] block leading-tight">
                                        <?= htmlspecialchars($col['label']) ?>
                                    </span>
                                    <?php if (!empty($col['sub'])): ?>
                                        <span class="text-[9.5px] text-slate-400 lowercase font-medium">
                                            <?= htmlspecialchars($col['sub']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="font-mono font-extrabold text-slate-900 text-xs bg-slate-50 px-2 py-0.5 rounded-lg border border-slate-200 tabular-nums shrink-0">
                                    <?= htmlspecialchars($val) ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Card Action Footer -->
                        <div class="p-2.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-2">
                            <span class="text-[10px] font-medium text-slate-500">Boarding &amp; Tuition Structure</span>
                            <button type="button" onclick="selectClassFromFees('<?= $cCode ?>')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-brand-700 hover:bg-brand-800 text-white text-[11px] font-bold shadow-xs transition">
                                <span>Select <?= htmlspecialchars($row['class']) ?></span>
                                <i class="fa-solid fa-arrow-down text-[10px]"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- 2. Full Table View (Visible on tablet/desktop, togglable on mobile) -->
                <div id="applyFeesTableViewContainer" class="hidden md:block overflow-x-auto rounded-xl border border-slate-300 shadow-xs" style="-webkit-overflow-scrolling: touch;">
                    <table class="w-full text-xs text-left border-collapse min-w-[720px]">
                        <thead>
                            <tr class="bg-slate-100/80 border-b border-slate-300 text-slate-800 text-[11px] font-extrabold uppercase">
                                <th class="p-3 border-r border-slate-300 text-center w-20 sticky left-0 bg-slate-100 z-10 shadow-xs">CLASS</th>
                                <?php foreach ($feesMatrix['columns'] as $col): ?>
                                <th class="p-3 border-r border-slate-300 text-center last:border-r-0">
                                    <div class="font-extrabold text-slate-900 leading-tight"><?= htmlspecialchars($col['label']) ?></div>
                                    <?php if (!empty($col['sub'])): ?>
                                    <div class="text-[9.5px] font-medium text-slate-500 lowercase mt-0.5"><?= htmlspecialchars($col['sub']) ?></div>
                                    <?php endif; ?>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white font-mono text-[11px]">
                            <?php foreach ($feesMatrix['rows'] as $row): ?>
                            <tr class="hover:bg-brand-50/40 transition">
                                <td class="p-3 border-r border-slate-300 font-bold text-center text-slate-900 bg-slate-50/95 font-sans sticky left-0 z-10 shadow-xs">
                                    <?= htmlspecialchars($row['class']) ?>
                                </td>
                                <?php foreach ($feesMatrix['columns'] as $col): 
                                    $val = trim($row[$col['id']] ?? '');
                                ?>
                                <td class="p-3 border-r border-slate-300 text-right pr-4 sm:pr-6 text-slate-700 last:border-r-0 font-medium tabular-nums whitespace-nowrap">
                                    <?= !empty($val) ? htmlspecialchars($val) : '<div class="text-center font-sans text-slate-300 font-light">-</div>' ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                        <span class="step-title block text-[10px] sm:text-[11px] font-semibold text-slate-500 mt-1.5 px-0.5 leading-tight">Student Bio Data</span>
                    </div>

                    <!-- Step 2 -->
                    <div class="step-item relative z-10 text-center flex-1" id="stepIndicator2">
                        <div class="step-circle w-9 h-9 rounded-full bg-slate-100 text-slate-600 font-bold text-xs flex items-center justify-center mx-auto transition">2</div>
                        <span class="step-title block text-[10px] sm:text-[11px] font-semibold text-slate-500 mt-1.5 px-0.5 leading-tight">Academic Credentials</span>
                    </div>

                    <!-- Step 3 -->
                    <div class="step-item relative z-10 text-center flex-1" id="stepIndicator3">
                        <div class="step-circle w-9 h-9 rounded-full bg-slate-100 text-slate-600 font-bold text-xs flex items-center justify-center mx-auto transition">3</div>
                        <span class="step-title block text-[10px] sm:text-[11px] font-semibold text-slate-500 mt-1.5 px-0.5 leading-tight">Parent & Submit</span>
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
                    <a href="app/admission_list.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-sm">
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
                            <input type="text" name="surname" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="Surname / Family Name">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">First Name <span class="text-rose-500 font-bold">*</span></label>
                            <input type="text" name="firstname" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="First Name / Given Name">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Other Names (Optional)</label>
                            <input type="text" name="othernames" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="Other Names (Optional)">
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
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nationality <span class="text-rose-500 font-bold">*</span></label>
                            <select name="nationality" id="nationalitySelect" onchange="toggleNationalityOther(this.value)" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition bg-white font-medium text-slate-800">
                                <option value="Ugandan" selected>Ugandan</option>
                                <option value="Kenyan">Kenyan</option>
                                <option value="Tanzanian">Tanzanian</option>
                                <option value="Rwandan">Rwandan</option>
                                <option value="South Sudanese">South Sudanese</option>
                                <option value="Burundian">Burundian</option>
                                <option value="Congolese (DRC)">Congolese (DRC)</option>
                                <option value="Other">Other Nationality</option>
                            </select>
                            <input type="text" name="nationality_other" id="nationalityOther" placeholder="Specify nationality..." class="hidden mt-2 w-full px-3.5 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Place of Residence <span class="text-rose-500 font-bold">*</span></label>
                            <input type="text" name="residence" id="residenceInput" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="Town, City, or Residential Area">
                            <p class="text-[10px] text-slate-400 mt-1">Town, city, or physical home area where the student resides.</p>
                        </div>
                    </div>

                    <!-- Special Health Problem / Medical Needs -->
                    <div class="p-3.5 sm:p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 uppercase flex items-center gap-1.5">
                                    <i class="fa-solid fa-notes-medical text-rose-600"></i> Do you have any special health problem? <span class="text-rose-500 font-bold">*</span>
                                </label>
                                <p class="text-[11px] text-slate-500 mt-0.5">Indicate if student requires medical attention, chronic care, or has allergies.</p>
                            </div>
                            <div class="flex items-center gap-2.5 shrink-0">
                                <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-bold text-slate-700 bg-white px-3 py-1.5 rounded-lg border border-slate-200 hover:border-brand-700 transition">
                                    <input type="radio" name="health_problem" value="No" checked onchange="toggleHealthDetails(false)" class="text-brand-700 focus:ring-brand-700">
                                    <span>No (None)</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-bold text-rose-700 bg-white px-3 py-1.5 rounded-lg border border-slate-200 hover:border-rose-600 transition">
                                    <input type="radio" name="health_problem" value="Yes" onchange="toggleHealthDetails(true)" class="text-rose-600 focus:ring-rose-500">
                                    <span>Yes, Specify</span>
                                </label>
                            </div>
                        </div>
                        <div id="healthDetailsBox" class="hidden pt-2 border-t border-slate-200">
                            <label class="block text-[11px] font-bold text-rose-800 uppercase mb-1">Specify Health Problem / Medical Condition & Care Instructions:</label>
                            <textarea name="health_details" id="healthDetailsInput" rows="2" class="w-full px-3 py-2 border border-rose-300 rounded-xl text-xs sm:text-sm text-slate-800 bg-white focus:outline-none focus:border-rose-600 transition" placeholder="e.g. Asthma (uses inhaler), Allergies to nuts/penicillin, Sickle Cell trait, Requires specialized diet..."></textarea>
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

                    <?php if ($show_passport_photo_upload === '1'): ?>
                    <div class="pt-2" id="passportPhotoContainer">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase">Student Passport Photograph <span class="text-rose-500 font-bold">*</span></label>
                        </div>
                        <input type="file" name="passport" id="passportFile" accept="image/*" required class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
                        <p class="text-[10px] text-slate-400 mt-1">Upload from camera, photo gallery, downloads or files. The system automatically formats with a pure white background.</p>
                    </div>
                    <?php endif; ?>

                    <div class="pt-6 border-t border-slate-100 flex justify-end">
                        <button type="button" class="next-btn w-full sm:w-auto px-5 py-2 rounded-lg text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-xs flex items-center justify-center gap-1.5 whitespace-nowrap flex-nowrap shrink-0" onclick="goToStep(2)">
                            <span>Next: Academic History</span> <i class="fa-solid fa-arrow-right text-[10px]"></i>
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
                                <input type="text" name="former_school_s1" id="formerSchoolS1" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="Former Primary School Name">
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

                        <!-- Senior Five Academic Notice -->
                        <div class="p-3 bg-brand-50/40 rounded-xl border border-brand-200/50 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-lg bg-brand-700 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                    <i class="fa-solid fa-graduation-cap"></i>
                                </span>
                                <div>
                                    <span class="block text-xs font-bold text-slate-800 uppercase">Senior Five (S5) Academic Performance (UCE)</span>
                                    <span class="block text-[11px] text-slate-500">Provide your official UNEB UCE results, subject grades, and desired A-Level combination below.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Candidate O-Level School & Index -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Former O-Level Secondary School <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="former_school_s5" id="formerSchoolS5" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="Former Secondary School Name">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">UNEB UCE Index Number <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="index_number_s5" id="indexNumberS5" pattern="^U[0-9]{4}/[0-9]{3,4}$" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-mono focus:outline-none focus:border-brand-700 transition uppercase" placeholder="e.g. U0000/001">
                                <p class="text-[10px] text-slate-400 mt-1">Official format: Center Number / Candidate Number (e.g. U0000/001)</p>
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

                        <!-- UCE Subjects & Letter Grades Grid (Data-Efficient & Dynamic) -->
                        <div class="bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200 space-y-3">
                            <div class="border-b border-slate-200 pb-2">
                                <span class="block text-xs font-bold text-slate-800 uppercase tracking-wide">UCE Subject Letter Grades <span class="text-rose-500 font-bold">*</span></span>
                                <span class="block text-[11px] text-slate-500">Select the official letter grade (A – E) obtained for each subject taken.</span>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-1">
                                <?php foreach ($uceSubjectsList as $subj): 
                                    $sCode = strtoupper(trim($subj['code']));
                                    $sName = trim($subj['name']);
                                    $isCore = (!empty($subj['is_core']) && $subj['is_core'] != '0');
                                    $fieldId = 'uce_' . strtolower(preg_replace('/[^a-z0-9_]/i', '_', $sCode));
                                ?>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 mb-1" for="<?= $fieldId ?>">
                                            <?= htmlspecialchars($sName) ?> (<?= htmlspecialchars($sCode) ?>)
                                            <?php if ($isCore): ?>
                                                <span class="text-rose-500 font-bold" title="Compulsory Core Subject">*</span>
                                            <?php else: ?>
                                                <span class="text-[10px] text-slate-400 font-normal">(Elective)</span>
                                            <?php endif; ?>
                                        </label>
                                        <select name="<?= $fieldId ?>" id="<?= $fieldId ?>" data-core="<?= $isCore ? '1' : '0' ?>" data-name="<?= htmlspecialchars($sName) ?>" class="nlsc-grade-select w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold bg-white focus:outline-none focus:border-brand-700">
                                            <option value=""><?= $isCore ? 'Select Grade' : 'Optional / Not Taken' ?></option>
                                            <option value="A">Grade A (Exceptional)</option>
                                            <option value="B">Grade B (Outstanding)</option>
                                            <option value="C">Grade C (Satisfactory)</option>
                                            <option value="D">Grade D (Basic)</option>
                                            <option value="E">Grade E (Elementary)</option>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Hidden Aggregates equivalent input used by cutoff enforcement -->
                            <input type="hidden" name="aggregates_s5" id="aggregatesS5" value="0">
                        </div>

                        <!-- Desired A-Level Subject Combination Selection -->
                        <div class="space-y-3 p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200 shadow-2xs">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <label class="text-xs font-black text-slate-800 uppercase tracking-wide flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-md bg-brand-700 text-white flex items-center justify-center text-xs">
                                        <i class="fa-solid fa-book-open"></i>
                                    </span>
                                    <span>Desired A-Level Subject Combination <span class="text-rose-500 font-bold">*</span></span>
                                </label>
                                <span class="text-[11px] font-semibold text-slate-500">
                                    Choose from list or type custom
                                </span>
                            </div>

                            <p class="text-[11.5px] text-slate-600 leading-relaxed">
                                Select your desired A-Level combination below. If your preferred combination is missing, select <strong>"Other / Type Custom Combination"</strong> to type it manually. The school admissions committee will review your UCE results and officially assign your final combination upon admission.
                            </p>

                            <!-- Combination Select Dropdown -->
                            <div>
                                <select name="combination_select_s5" id="combinationSelectS5" onchange="handleCombinationChange(this.value)" class="w-full px-3.5 py-2.5 border-2 border-slate-200 rounded-xl text-sm font-bold text-slate-800 bg-white focus:outline-none focus:border-brand-700 transition">
                                    <option value="">-- Select Your Desired A-Level Combination --</option>
                                    <?php foreach ($aLevelCombinations as $group => $items): ?>
                                        <optgroup label="<?= htmlspecialchars($group) ?>">
                                            <?php foreach ($items as $val => $label): ?>
                                                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                    <option value="OTHER" class="text-brand-700 font-black">+ Other / Type Custom Combination...</option>
                                </select>
                            </div>

                            <!-- Manual Type Input (Revealed if "OTHER" is chosen) -->
                            <div id="customCombinationWrap" class="hidden space-y-1.5 pt-1">
                                <label class="block text-[11px] font-bold text-brand-800 uppercase">
                                    Type Desired Combination <span class="text-rose-500 font-bold">*</span> (e.g. PCB/ICT, PCM/ICT, BCM/ICT, MEG/ICT)
                                </label>
                                <div class="relative">
                                    <input type="text" id="customCombinationInput" oninput="handleCustomCombInput(this.value)" class="w-full px-3.5 py-2.5 border-2 border-brand-300 rounded-xl text-sm font-mono font-bold uppercase focus:outline-none focus:border-brand-700 bg-white" placeholder="e.g. PCB/ICT, BCM/ICT, MEG/ICT">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10.5px] font-bold text-brand-700 bg-brand-50 px-2 py-0.5 rounded border border-brand-200">Custom</span>
                                </div>
                                <p class="text-[10.5px] text-slate-400">Note: In Uganda, all combinations taking Principal Mathematics take Subsidiary ICT (not Sub-Math).</p>
                            </div>

                            <!-- Hidden field holding the final submitted value -->
                            <input type="hidden" name="combination_s5" id="combinationS5" value="">
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
                                    <input type="text" name="s6_former_school" id="s6FormerSchool" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition bg-white" placeholder="Former A-Level School Name">
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
                            <p class="text-[10px] text-slate-500 mt-1"><i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> Accepted: Upload from camera, photo gallery, downloads or files (official slip or provisional result slip).</p>
                        </div>
                    </div>

                    <!-- 2.C: Transfers Section -->
                    <div id="transferContainer" class="space-y-4 hidden">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Former School Name <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="former_school_transfer" id="formerSchoolTransfer" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="Former School Name">
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

                    <div class="pt-6 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-center justify-between gap-2.5">
                        <button type="button" class="w-full sm:w-auto px-4 py-2 rounded-lg text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition flex items-center justify-center gap-1.5 whitespace-nowrap flex-nowrap shrink-0" onclick="goToStep(1)">
                            <i class="fa-solid fa-arrow-left text-[10px]"></i> <span>Back</span>
                        </button>
                        <button type="button" class="next-btn w-full sm:w-auto px-5 py-2 rounded-lg text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-xs flex items-center justify-center gap-1.5 whitespace-nowrap flex-nowrap shrink-0" onclick="goToStep(3)">
                            <span>Next: Parent / Guardian</span> <i class="fa-solid fa-arrow-right text-[10px]"></i>
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

                    <input type="hidden" name="caregiver_type" id="caregiverTypeInput" value="parents">
                    <input type="hidden" name="parent_name" id="parentNameConsolidated" value="">
                    <input type="hidden" name="parent_phone" id="parentPhoneConsolidated" value="">

                    <!-- Strategic Caregiver Type Switcher -->
                    <div class="space-y-2">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 uppercase flex items-center gap-1.5">
                                    <i class="fa-solid fa-hands-holding-child text-brand-700"></i> Primary Family / Caregiver Category <span class="text-rose-500 font-bold">*</span>
                                </label>
                                <p class="text-[11px] text-slate-500">Choose whether applicant details are submitted by parents or by a legal guardian / sponsor.</p>
                            </div>
                        </div>
                        <div class="bg-slate-100 p-1 rounded-2xl flex flex-col sm:flex-row gap-1 max-w-md border border-slate-200">
                            <button type="button" id="btnCaregiverParents" onclick="selectCaregiverType('parents')" class="flex-1 py-2 px-2.5 sm:px-3 rounded-xl text-[11px] sm:text-xs font-black transition shadow-xs bg-white text-brand-900 border border-slate-200 flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-people-roof text-brand-700"></i> Parents (Father & Mother)
                            </button>
                            <button type="button" id="btnCaregiverGuardian" onclick="selectCaregiverType('guardian')" class="flex-1 py-2 px-2.5 sm:px-3 rounded-xl text-[11px] sm:text-xs font-bold transition text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-hands-holding-child text-gold-600"></i> Guardian / Sponsor Only
                            </button>
                        </div>
                    </div>

                    <!-- SECTION A: PARENTS PARTICULARS (Father & Mother) -->
                    <div id="caregiverParentsSection" class="space-y-3.5">
                        <div class="p-3 bg-brand-50/50 rounded-xl border border-brand-200/60 text-[11px] text-brand-950 flex items-start gap-2">
                            <i class="fa-solid fa-circle-info text-brand-700 mt-0.5 shrink-0"></i>
                            <span>Provide particulars for both parents, or either Father or Mother (at least one valid telephone contact is required). Single parents can fill either contact freely.</span>
                        </div>

                        <!-- Father Particulars -->
                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-2.5">
                            <span class="block text-xs font-black text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                                <i class="fa-solid fa-user-tie text-brand-700"></i> Father's Particulars
                            </span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Father's Full Name</label>
                                    <input type="text" name="father_name" id="fatherName" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-sans focus:outline-none focus:border-brand-700 transition bg-white" placeholder="Father's Full Name">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Father's Telephone Contact</label>
                                    <input type="tel" inputmode="tel" name="father_phone" id="fatherPhone" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-sans focus:outline-none focus:border-brand-700 transition bg-white" placeholder="e.g. 0772123456 or +256772123456">
                                </div>
                            </div>
                        </div>

                        <!-- Mother Particulars -->
                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-2.5">
                            <span class="block text-xs font-black text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                                <i class="fa-solid fa-person-dress text-brand-700"></i> Mother's Particulars
                            </span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Mother's Full Name</label>
                                    <input type="text" name="mother_name" id="motherName" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-sans focus:outline-none focus:border-brand-700 transition bg-white" placeholder="Mother's Full Name">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Mother's Telephone Contact</label>
                                    <input type="tel" inputmode="tel" name="mother_phone" id="motherPhone" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-sans focus:outline-none focus:border-brand-700 transition bg-white" placeholder="e.g. 0782654321 or +256782654321">
                                </div>
                            </div>
                        </div>

                        <!-- Optional Guardian / Sponsor Expansion if Different from Parent -->
                        <div class="p-3.5 bg-white rounded-xl border border-dashed border-slate-300">
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                                <input type="checkbox" id="hasSponsorCheck" onchange="toggleSponsorBox(this.checked)" class="rounded text-brand-700 focus:ring-brand-700 w-4 h-4">
                                <span>Fees are paid by a Guardian / Sponsor (different from Father & Mother)</span>
                            </label>
                            <div id="sponsorFieldsBox" class="hidden mt-3 pt-3 border-t border-slate-200 space-y-3">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div class="sm:col-span-1">
                                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Guardian / Sponsor's Name</label>
                                        <input type="text" name="guardian_name_opt" id="guardianNameOpt" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm bg-white focus:outline-none focus:border-brand-700" placeholder="Guardian Name or Sponsor Organization">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Guardian / Sponsor's Contact</label>
                                        <input type="tel" inputmode="tel" name="guardian_phone_opt" id="guardianPhoneOpt" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm bg-white focus:outline-none focus:border-brand-700" placeholder="e.g. 0701234567">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Relationship</label>
                                        <input type="text" name="guardian_relation_opt" id="guardianRelationOpt" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm bg-white focus:outline-none focus:border-brand-700" placeholder="e.g. Uncle / Sponsor NGO">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION B: GUARDIAN / SPONSOR ONLY (For orphans or students with guardian) -->
                    <div id="caregiverGuardianSection" class="hidden space-y-3.5">
                        <div class="p-3 bg-gold-50/70 rounded-xl border border-gold-200 text-[11px] text-slate-800 flex items-start gap-2">
                            <i class="fa-solid fa-hands-holding-child text-gold-700 mt-0.5 shrink-0 text-sm"></i>
                            <span>This section is designated for applicants who do not have parents present or are under the care of a legal guardian, relative, or educational sponsor.</span>
                        </div>

                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                            <span class="block text-xs font-black text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                                <i class="fa-solid fa-user-shield text-brand-700"></i> Guardian / Sponsor Particulars
                            </span>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="sm:col-span-1">
                                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Guardian / Sponsor's Full Name <span class="text-rose-500 font-bold">*</span></label>
                                    <input type="text" name="guardian_name_main" id="guardianNameMain" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-sans focus:outline-none focus:border-brand-700 transition bg-white" placeholder="Guardian / Sponsor's Full Name">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Relationship to Student <span class="text-rose-500 font-bold">*</span></label>
                                    <select name="guardian_relation_main" id="guardianRelationMain" class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:border-brand-700">
                                        <option value="Uncle / Aunt">Uncle / Aunt</option>
                                        <option value="Grandparent">Grandparent</option>
                                        <option value="Elder Brother / Sister">Elder Brother / Sister</option>
                                        <option value="Legal Guardian">Legal Guardian</option>
                                        <option value="Sponsor / NGO">Educational Sponsor / NGO</option>
                                        <option value="Foster Parent">Foster Parent</option>
                                        <option value="Other">Other Caregiver</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Guardian / Sponsor's Contact <span class="text-rose-500 font-bold">*</span></label>
                                    <input type="tel" inputmode="tel" name="guardian_phone_main" id="guardianPhoneMain" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-sans focus:outline-none focus:border-brand-700 transition bg-white" placeholder="e.g. 0772123456 or +256772123456">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email & National ID NIN Section (Universal) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Guardian's / Parent's E-mail <span class="text-rose-500 font-bold">*</span></label>
                            <input type="email" inputmode="email" name="parent_email" id="parentEmail" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-sans font-medium text-slate-800 focus:outline-none focus:border-brand-700 transition" placeholder="e.g. parent@example.com">
                            <p class="text-[10px] text-slate-400 mt-1">Official provisional admission letter and application receipt will be sent here.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Parent / Guardian National ID NIN <?= ($show_parent_id_upload === '1') ? '<span class="text-rose-500 font-bold">*</span>' : '<span class="text-slate-400 font-normal">(Optional)</span>' ?></label>
                            <input type="text" inputmode="text" name="parent_nin" id="parentNin" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase()" <?= ($show_parent_id_upload === '1') ? 'required' : '' ?> class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-sans font-medium uppercase text-slate-800 focus:outline-none focus:border-brand-700 transition" placeholder="e.g. CM89012345ABCD">
                            <p class="text-[10px] text-slate-400 mt-1">14-character Ugandan National Identification Number.</p>
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
                                <input type="text" name="parent_village" id="parentVillage" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="Village / Cell / Zone">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Parish / Ward <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="parent_parish" id="parentParish" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="Parish / Ward">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Sub-county / Town Council / Division <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="parent_subcounty" id="parentSubcounty" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="Sub-county / Division / Town Council">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">District <span class="text-rose-500 font-bold">*</span></label>
                                <input type="text" name="parent_district" id="parentDistrict" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-700 transition" placeholder="District / City of Origin">
                            </div>
                        </div>
                        <input type="hidden" name="parent_address" id="parentAddress">
                    </div>

                    <?php if ($show_parent_id_upload === '1'): ?>
                    <!-- Parent / Guardian National ID Document Upload -->
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5" id="parentIdContainer">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-slate-700 uppercase">
                                <i class="fa-solid fa-id-card text-brand-700 mr-1"></i> Upload Parent / Guardian National ID <span class="text-rose-500 font-bold">*</span>
                            </label>
                        </div>
                        <input type="file" name="parent_id_file" id="parentIdFile" accept="image/*,application/pdf" required class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
                        <p class="text-[10px] text-slate-400">Upload from camera, photo gallery, downloads or files (PDF/PNG/JPG copy of Parent/Guardian National ID or valid passport).</p>
                    </div>
                    <?php endif; ?>

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

                    <div class="pt-6 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-center justify-between gap-2.5">
                        <button type="button" class="w-full sm:w-auto px-4 py-2 rounded-lg text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition flex items-center justify-center gap-1.5 whitespace-nowrap flex-nowrap shrink-0" onclick="goToStep(2)">
                            <i class="fa-solid fa-arrow-left text-[10px]"></i> <span>Back</span>
                        </button>
                        <button type="submit" id="submitBtn" class="w-full sm:w-auto px-6 py-2.5 rounded-lg text-xs sm:text-sm font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-xs flex items-center justify-center gap-2 whitespace-nowrap flex-nowrap shrink-0">
                            <i class="fa-solid fa-paper-plane text-xs"></i> <span>Submit Application</span>
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
                    <a href="app/login.php" class="hover:text-white transition underline-offset-4 hover:underline">
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

    <!-- Floating Back-to-Top Button (Strictly Round Circle) -->
    <button id="backToTopBtn" type="button" aria-label="Back to Top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
        class="fixed bottom-6 right-6 z-50 w-11 h-11 rounded-full flex items-center justify-center aspect-square text-white shadow-xl opacity-0 pointer-events-none transition-all duration-300 hover:scale-110 focus:outline-none"
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
            t.className = "class-tab-btn px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-lg text-[11px] sm:text-xs font-bold transition text-center bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200/60";
        });

        // Highlight active tab
        if (val === 'S1') {
            tabS1.className = "class-tab-btn px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-lg text-[11px] sm:text-xs font-bold transition text-center bg-brand-700 text-white shadow-xs";
        } else if (val === 'S5') {
            tabS5.className = "class-tab-btn px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-lg text-[11px] sm:text-xs font-bold transition text-center bg-brand-700 text-white shadow-xs";
        } else {
            tabTransfers.className = "class-tab-btn px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-lg text-[11px] sm:text-xs font-bold transition text-center bg-brand-700 text-white shadow-xs";
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

            // Mandatory fields for S5
            ['formerSchoolS5', 'indexNumberS5', 'divisionS5', 'uceProjectWork', 'resultSlipS5'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.setAttribute('required', 'required');
            });
            document.querySelectorAll('#uceContainer .nlsc-grade-select[data-core="1"]').forEach(el => {
                el.setAttribute('required', 'required');
            });
            document.querySelectorAll('#uceContainer .nlsc-grade-select[data-core="0"]').forEach(el => {
                el.removeAttribute('required');
            });
        } else if (val === 'S6') {
            uceContainer.classList.remove('hidden');
            if (s5CutoffBadge) s5CutoffBadge.classList.add('hidden');
            if (s6TransferBadge) s6TransferBadge.classList.remove('hidden');
            if (s6TransferFields) s6TransferFields.classList.remove('hidden');
            academicBadge.textContent = "Senior Six (A-Level Transfer & UCE NLSC)";

            // Mandatory fields for S6 (Foundational UCE NLSC + S5 Transfer credentials)
            ['formerSchoolS5', 'indexNumberS5', 'divisionS5', 'uceProjectWork', 'resultSlipS5', 's6FormerSchool', 's6PrincipalPasses', 's6TransferReason', 's6ReportFile'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.setAttribute('required', 'required');
            });
            document.querySelectorAll('#uceContainer .nlsc-grade-select[data-core="1"]').forEach(el => {
                el.setAttribute('required', 'required');
            });
            document.querySelectorAll('#uceContainer .nlsc-grade-select[data-core="0"]').forEach(el => {
                el.removeAttribute('required');
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

    // Manual A-Level Subject Combination Selection & Custom Input Handler
    function handleCombinationChange(val) {
        const wrap = document.getElementById('customCombinationWrap');
        const customInp = document.getElementById('customCombinationInput');
        const hiddenInp = document.getElementById('combinationS5');
        
        if (val === 'OTHER') {
            if (wrap) wrap.classList.remove('hidden');
            if (customInp) {
                customInp.focus();
                if (hiddenInp) hiddenInp.value = customInp.value.trim().toUpperCase();
            }
        } else {
            if (wrap) wrap.classList.add('hidden');
            if (hiddenInp) hiddenInp.value = val;
        }
    }

    function handleCustomCombInput(val) {
        const hiddenInp = document.getElementById('combinationS5');
        if (hiddenInp) hiddenInp.value = val.trim().toUpperCase();
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

            const natSelect = document.getElementById('nationalitySelect');
            if (!natSelect || !natSelect.value) {
                showFieldWarning(natSelect, "Please select the candidate's Nationality.");
                return false;
            }
            if (natSelect.value === 'Other') {
                const natOther = document.getElementById('nationalityOther');
                if (!natOther || !natOther.value.trim()) {
                    showFieldWarning(natOther, "Please specify the candidate's Nationality.");
                    return false;
                }
            }

            const residenceInp = document.getElementById('residenceInput');
            if (!residenceInp || !residenceInp.value.trim()) {
                showFieldWarning(residenceInp, "You have not entered the Place of Residence / Home area.");
                return false;
            }

            const healthProb = document.querySelector("input[name='health_problem']:checked");
            if (healthProb && healthProb.value === 'Yes') {
                const healthDet = document.getElementById('healthDetailsInput');
                if (!healthDet || !healthDet.value.trim()) {
                    showFieldWarning(healthDet, "Please specify the special health problem / medical condition, or select 'No (None)'.");
                    return false;
                }
            }

            const showPassportPhoto = <?= json_encode($show_passport_photo_upload === '1') ?>;
            if (showPassportPhoto) {
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
                const indexPattern = /^U[0-9]{4}\/[0-9]{3,4}$/i;
                if (!indexPattern.test(idx5.value.trim())) {
                    showFieldWarning(idx5, "Please enter a valid UNEB Index Number format (e.g. U0000/001).");
                    return false;
                }

                const div5 = document.getElementById('divisionS5');
                if (!div5 || !div5.value) {
                    showFieldWarning(div5, "Please select the Overall Certification Result / Division.");
                    return false;
                }

                const pw = document.getElementById('uceProjectWork');
                if (!pw || !pw.value) {
                    showFieldWarning(pw, "You have not selected the UCE Project Work Achievement Grade.");
                    return false;
                }

                // Check all Core Compulsory Subjects dynamically
                const coreSelects = document.querySelectorAll('#uceContainer select.nlsc-grade-select[data-core="1"]');
                for (let sel of coreSelects) {
                    if (!sel.value) {
                        const subjName = sel.getAttribute('data-name') || 'this compulsory subject';
                        showFieldWarning(sel, "You have not selected a grade for " + subjName + ".");
                        return false;
                    }
                }

                // S5 Subject Combination Check
                const combValS5 = ((document.getElementById('combinationS5') || {}).value || '').trim();
                if (!combValS5) {
                    const selBox = document.getElementById('combinationSelectS5');
                    showFieldWarning(selBox, "Please select or type your desired A-Level Subject Combination.");
                    return false;
                }

                // UCE Result slip
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

                // Cutoff verification
                const uceAgg = parseInt(document.getElementById('aggregatesS5').value, 10) || 0;
                if (cutoffPassmarks['S5'] !== null && cutoffPassmarks['S5'] !== '') {
                    const cutoffS5 = parseInt(cutoffPassmarks['S5'], 10);
                    if (uceAgg > cutoffS5) {
                        showFieldWarning(document.getElementById('aggregatesS5'), "Your calculated UCE aggregates (" + uceAgg + ") exceed the maximum admission cutoff of " + cutoffS5 + " aggregates for Senior Five.");
                        return false;
                    }
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

                // S6 Subject Combination Check
                const combValS6 = ((document.getElementById('combinationS5') || {}).value || '').trim();
                if (!combValS6) {
                    const selBox = document.getElementById('combinationSelectS5');
                    showFieldWarning(selBox, "Please select or type your desired A-Level Subject Combination.");
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
            const caregiverTypeInp = document.getElementById('caregiverTypeInput');
            const caregiverType = caregiverTypeInp ? caregiverTypeInp.value : 'parents';
            const phoneRegex = /^(?:\+256|256|0)[37][0-9]{8}$/;

            if (caregiverType === 'guardian') {
                const gName = document.getElementById('guardianNameMain');
                if (!gName || gName.value.trim().length < 2) {
                    showFieldWarning(gName, "Please enter the Guardian / Sponsor's Full Name.");
                    return false;
                }
                const gPhone = document.getElementById('guardianPhoneMain');
                if (!gPhone || !phoneRegex.test(gPhone.value.trim())) {
                    showFieldWarning(gPhone, "Please enter a valid Guardian / Sponsor Telephone Contact (e.g. 0772123456 or +256772123456).");
                    return false;
                }
                const gRel = (document.getElementById('guardianRelationMain') || {}).value || 'Guardian';
                document.getElementById('parentNameConsolidated').value = gName.value.trim() + ' (' + gRel + ')';
                document.getElementById('parentPhoneConsolidated').value = gPhone.value.trim();
            } else {
                // Parents mode
                const fName = (document.getElementById('fatherName') || {}).value ? document.getElementById('fatherName').value.trim() : '';
                const fPhone = (document.getElementById('fatherPhone') || {}).value ? document.getElementById('fatherPhone').value.trim() : '';
                const mName = (document.getElementById('motherName') || {}).value ? document.getElementById('motherName').value.trim() : '';
                const mPhone = (document.getElementById('motherPhone') || {}).value ? document.getElementById('motherPhone').value.trim() : '';
                const gNameOpt = (document.getElementById('guardianNameOpt') || {}).value ? document.getElementById('guardianNameOpt').value.trim() : '';
                const gPhoneOpt = (document.getElementById('guardianPhoneOpt') || {}).value ? document.getElementById('guardianPhoneOpt').value.trim() : '';

                if (!fName && !mName && !gNameOpt) {
                    showFieldWarning(document.getElementById('fatherName'), "Please enter at least Father's Name, Mother's Name, or Guardian's Name.");
                    return false;
                }

                if (!fPhone && !mPhone && !gPhoneOpt) {
                    showFieldWarning(document.getElementById('fatherPhone'), "Please provide at least one active telephone contact (Father's, Mother's, or Sponsor's contact).");
                    return false;
                }

                if (fPhone && !phoneRegex.test(fPhone)) {
                    showFieldWarning(document.getElementById('fatherPhone'), "Father's telephone contact is invalid. Use standard format e.g. 0772123456 or +256772123456.");
                    return false;
                }
                if (mPhone && !phoneRegex.test(mPhone)) {
                    showFieldWarning(document.getElementById('motherPhone'), "Mother's telephone contact is invalid. Use standard format e.g. 0782654321 or +256782654321.");
                    return false;
                }
                if (gPhoneOpt && !phoneRegex.test(gPhoneOpt)) {
                    showFieldWarning(document.getElementById('guardianPhoneOpt'), "Guardian's telephone contact is invalid. Use standard format e.g. 0701123456 or +256701123456.");
                    return false;
                }

                const names = [];
                if (fName) names.push("Father: " + fName);
                if (mName) names.push("Mother: " + mName);
                if (gNameOpt) names.push("Guardian: " + gNameOpt);
                document.getElementById('parentNameConsolidated').value = names.join(' | ');
                document.getElementById('parentPhoneConsolidated').value = fPhone || mPhone || gPhoneOpt;
            }

            const pEmail = document.getElementById('parentEmail');
            if (!pEmail || !pEmail.value.trim()) {
                showFieldWarning(pEmail, "You have not entered the Guardian / Parent Email Address.");
                return false;
            }
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(pEmail.value.trim())) {
                showFieldWarning(pEmail, "Please enter a valid Guardian / Parent Email Address.");
                return false;
            }

            const showParentId = <?= json_encode($show_parent_id_upload === '1') ?>;
            const pNin = document.getElementById('parentNin');
            if (pNin) {
                pNin.value = pNin.value.trim().toUpperCase();
                if (showParentId || pNin.value.length > 0) {
                    const ninRegex = /^(CM|CF|RM|RF)[0-9A-Z]{12}$/;
                    if (!ninRegex.test(pNin.value)) {
                        showFieldWarning(pNin, "Please enter a valid Ugandan 14-character NIN starting with CM, CF, RM, or RF (e.g. CM89012345ABCD).");
                        return false;
                    }
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

            if (showParentId) {
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

    function toggleFeesStructure() {
        const content = document.getElementById('feesStructureContent');
        const btn = document.getElementById('feesToggleBtn');
        const icon = document.getElementById('feesToggleIcon');
        const label = document.getElementById('feesToggleLabel');
        if (!content) return;
        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            if (icon) icon.className = 'fa-solid fa-chevron-up transition-transform duration-200 text-[11px]';
            if (label) label.textContent = 'Hide Fees';
            if (btn) {
                btn.classList.remove('fees-attention-pulse', 'bg-red-600', 'hover:bg-red-700', 'ring-red-400/60');
                btn.classList.add('bg-slate-700', 'hover:bg-slate-800', 'ring-slate-300');
            }
        } else {
            content.classList.add('hidden');
            if (icon) icon.className = 'fa-solid fa-chevron-down transition-transform duration-200 text-[11px]';
            if (label) label.textContent = 'View Fees';
            if (btn) {
                btn.classList.remove('bg-slate-700', 'hover:bg-slate-800', 'ring-slate-300');
                btn.classList.add('fees-attention-pulse', 'bg-red-600', 'hover:bg-red-700', 'ring-red-400/60');
            }
        }
    }

    function toggleNationalityOther(val) {
        const otherInp = document.getElementById('nationalityOther');
        if (otherInp) {
            if (val === 'Other') {
                otherInp.classList.remove('hidden');
                otherInp.focus();
            } else {
                otherInp.classList.add('hidden');
                otherInp.value = '';
            }
        }
    }

    function toggleHealthDetails(show) {
        const box = document.getElementById('healthDetailsBox');
        const input = document.getElementById('healthDetailsInput');
        if (box) {
            if (show) {
                box.classList.remove('hidden');
                if (input) input.focus();
            } else {
                box.classList.add('hidden');
                if (input) input.value = '';
            }
        }
    }

    function selectCaregiverType(type) {
        const input = document.getElementById('caregiverTypeInput');
        const parentsSec = document.getElementById('caregiverParentsSection');
        const guardianSec = document.getElementById('caregiverGuardianSection');
        const btnParents = document.getElementById('btnCaregiverParents');
        const btnGuardian = document.getElementById('btnCaregiverGuardian');

        if (input) input.value = type;

        if (type === 'guardian') {
            if (parentsSec) parentsSec.classList.add('hidden');
            if (guardianSec) guardianSec.classList.remove('hidden');
            if (btnParents) {
                btnParents.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5';
            }
            if (btnGuardian) {
                btnGuardian.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-black transition shadow-xs bg-white text-gold-900 border border-slate-200 flex items-center justify-center gap-1.5';
            }
        } else {
            if (guardianSec) guardianSec.classList.add('hidden');
            if (parentsSec) parentsSec.classList.remove('hidden');
            if (btnParents) {
                btnParents.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-black transition shadow-xs bg-white text-brand-900 border border-slate-200 flex items-center justify-center gap-1.5';
            }
            if (btnGuardian) {
                btnGuardian.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5';
            }
        }
    }

    function toggleSponsorBox(show) {
        const box = document.getElementById('sponsorFieldsBox');
        if (box) {
            if (show) {
                box.classList.remove('hidden');
            } else {
                box.classList.add('hidden');
            }
        }
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

    // Fees structure mobile helpers
    function filterFeesCards(cls, btn, cardClass) {
        const cards = document.querySelectorAll('.' + cardClass);
        cards.forEach(card => {
            if (cls === 'all' || card.getAttribute('data-class') === cls) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
        const btns = btn.parentElement.querySelectorAll('button');
        btns.forEach(b => {
            b.classList.remove('bg-brand-700', 'text-white', 'shadow-xs');
            b.classList.add('bg-slate-100', 'text-slate-700', 'border', 'border-slate-200');
        });
        btn.classList.add('bg-brand-700', 'text-white', 'shadow-xs');
        btn.classList.remove('bg-slate-100', 'text-slate-700', 'border', 'border-slate-200');
    }

    function toggleMobileFeesView(cardContainerId, tableContainerId, labelId, iconId) {
        const cardView = document.getElementById(cardContainerId);
        const tableView = document.getElementById(tableContainerId);
        const label = document.getElementById(labelId);
        const icon = document.getElementById(iconId);
        if (!cardView || !tableView) return;

        if (tableView.classList.contains('hidden')) {
            tableView.classList.remove('hidden');
            cardView.classList.add('hidden');
            if (label) label.textContent = 'Cards View';
            if (icon) icon.className = 'fa-solid fa-id-card text-xs text-brand-700';
        } else {
            tableView.classList.add('hidden');
            cardView.classList.remove('hidden');
            if (label) label.textContent = 'Table View';
            if (icon) icon.className = 'fa-solid fa-table-list text-xs text-brand-700';
        }
    }

    function selectClassFromFees(cCode) {
        const sel = document.getElementById('classOfEntry');
        if (sel) {
            sel.value = cCode;
            if (typeof updateClassVisibility === 'function') {
                updateClassVisibility();
            }
        }
        const el = document.getElementById('classSelectionCard') || document.getElementById('admissionForm') || sel;
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
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
