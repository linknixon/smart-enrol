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
$academic_year      = getAcademicYear($pdo);
$school_logo        = getSchoolLogoSrc($pdo);
$school_address     = getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro - Uganda');
$school_phone       = getSetting($pdo, 'school_phone', '+256 790 001 631');
$school_email       = getSetting($pdo, 'school_email', 'info@ncedges.com');
$school_website     = getSetting($pdo, 'school_website', 'www.mutolere.sc.ug');
$school_website_url = getSchoolWebsiteUrl($pdo);
$primary_color      = getPrimaryColor($pdo);

$status_s1    = getAdmissionStatus($pdo, 'S1');
$status_s5    = getAdmissionStatus($pdo, 'S5');
$status_trans = getAdmissionStatus($pdo, 'trans');

// Fees Structure matrices
$fees_classes = [
    's1' => [
        'name' => 'Senior One (S1)',
        'subtitle' => 'PLE Entry',
        'level' => 'O-Level',
        'tuition' => (int)getSetting($pdo, 'fees_s1_tuition', 550000),
        'admission' => (int)getSetting($pdo, 'fees_s1_admission', 100000),
        'functional' => (int)getSetting($pdo, 'fees_s1_functional', 150000),
        'apply_url' => 'apply.php?class=S1',
        'is_open' => $status_s1['is_open']
    ],
    's2' => [
        'name' => 'Senior Two (S2)',
        'subtitle' => 'O-Level Transfer',
        'level' => 'O-Level',
        'tuition' => (int)getSetting($pdo, 'fees_s2_tuition', 560000),
        'admission' => (int)getSetting($pdo, 'fees_s2_admission', 100000),
        'functional' => (int)getSetting($pdo, 'fees_s2_functional', 155000),
        'apply_url' => 'apply.php?class=S2',
        'is_open' => $status_trans['is_open']
    ],
    's3' => [
        'name' => 'Senior Three (S3)',
        'subtitle' => 'O-Level Transfer',
        'level' => 'O-Level',
        'tuition' => (int)getSetting($pdo, 'fees_s3_tuition', 570000),
        'admission' => (int)getSetting($pdo, 'fees_s3_admission', 100000),
        'functional' => (int)getSetting($pdo, 'fees_s3_functional', 160000),
        'apply_url' => 'apply.php?class=S3',
        'is_open' => $status_trans['is_open']
    ],
    's4' => [
        'name' => 'Senior Four (S4)',
        'subtitle' => 'Candidate Transfer',
        'level' => 'O-Level',
        'tuition' => (int)getSetting($pdo, 'fees_s4_tuition', 580000),
        'admission' => (int)getSetting($pdo, 'fees_s4_admission', 110000),
        'functional' => (int)getSetting($pdo, 'fees_s4_functional', 165000),
        'apply_url' => 'apply.php?class=S4',
        'is_open' => $status_trans['is_open']
    ],
    's5' => [
        'name' => 'Senior Five (S5)',
        'subtitle' => 'UCE NLSC Entry',
        'level' => 'A-Level',
        'tuition' => (int)getSetting($pdo, 'fees_s5_tuition', 600000),
        'admission' => (int)getSetting($pdo, 'fees_s5_admission', 120000),
        'functional' => (int)getSetting($pdo, 'fees_s5_functional', 180000),
        'apply_url' => 'apply.php?class=S5',
        'is_open' => $status_s5['is_open']
    ],
    's6' => [
        'name' => 'Senior Six (S6)',
        'subtitle' => 'A-Level Transfer',
        'level' => 'A-Level',
        'tuition' => (int)getSetting($pdo, 'fees_s6_tuition', 620000),
        'admission' => (int)getSetting($pdo, 'fees_s6_admission', 120000),
        'functional' => (int)getSetting($pdo, 'fees_s6_functional', 185000),
        'apply_url' => 'apply.php?class=S6',
        'is_open' => $status_trans['is_open']
    ]
];

// Fetch Hero Slider Images
$slider_images_json = getSetting($pdo, 'hero_slider_images', '');
$slider_images = [];
if (!empty($slider_images_json)) {
    $decoded = json_decode($slider_images_json, true);
    if (is_array($decoded) && count($decoded) > 0) {
        $slider_images = $decoded;
    }
}
if (empty($slider_images)) {
    $slider_images = [
        'images/campus.png',
        'images/students.png',
        'images/lab.png'
    ];
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($school_name) ?> - Online Admissions Portal</title>
<link rel="icon" type="image/png" href="<?= !empty($school_logo) ? htmlspecialchars($school_logo) : 'images/default_logo.png' ?>">
<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<?= renderThemeConfig($pdo) ?>
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased min-h-screen flex flex-col py-0 sm:py-6 px-0 sm:px-4">

    <!-- Boxed Container Wrapper -->
    <div class="max-w-6xl w-full mx-auto bg-white sm:rounded-3xl sm:border sm:border-slate-200 sm:shadow-xl overflow-hidden flex flex-col">

        <!-- Main Navigation Header -->
        <header class="bg-white/95 backdrop-blur-md sticky top-0 z-40 border-b border-slate-200 shadow-sm transition">
            <div class="max-w-6xl mx-auto px-3 sm:px-6 lg:px-8 py-2.5 sm:py-3.5 flex justify-between items-center gap-3 sm:gap-6">
                <!-- Brand Info -->
                <a href="index.php" class="flex items-center gap-2.5 sm:gap-3 group min-w-0 shrink">
                    <div class="w-11 h-11 sm:w-13 sm:h-13 rounded-2xl bg-white border border-slate-200 p-1.5 flex items-center justify-center shrink-0 group-hover:scale-105 transition shadow-sm">
                        <img src="<?= htmlspecialchars($school_logo) ?>" alt="<?= htmlspecialchars($school_short_name) ?> Logo" class="max-w-full max-h-full w-auto h-auto object-contain">
                    </div>
                    <div class="min-w-0">
                        <span class="brand-school-name block text-[13px] sm:text-base lg:text-[17px] font-black text-slate-900 tracking-tight leading-tight uppercase group-hover:text-brand-700 transition" style="word-spacing: -0.05em;">
                            <?= htmlspecialchars(!empty($school_short_name) ? $school_short_name : $school_name) ?>
                        </span>
                        <?php if (!empty($school_motto)): ?>
                            <span class="block text-[10px] sm:text-[11px] font-bold text-gold-600 italic tracking-wide leading-tight line-clamp-1 mt-0.5" title="<?= htmlspecialchars($school_motto) ?>">
                                “<?= htmlspecialchars($school_motto) ?>”
                            </span>
                        <?php else: ?>
                            <span class="block text-[9.5px] sm:text-[11px] font-semibold text-gold-600 uppercase tracking-wider leading-tight">
                                Online Admissions Portal
                            </span>
                        <?php endif; ?>
                    </div>
                </a>

                <!-- Navigation Links (Full on XL screens, responsive) -->
                <nav class="hidden xl:flex items-center gap-5 text-sm font-semibold text-slate-600 shrink-0">
                    <a href="#pathways" class="hover:text-brand-700 transition whitespace-nowrap">Pathways</a>
                    <a href="#fees-structure" class="hover:text-brand-700 transition whitespace-nowrap flex items-center gap-1"><i class="fa-solid fa-coins text-gold-500 text-xs"></i> Fees</a>
                    <a href="#requirements" class="hover:text-brand-700 transition whitespace-nowrap">Requirements</a>
                    <a href="#registers" class="hover:text-brand-700 transition whitespace-nowrap">Registers</a>
                    <a href="track.php" class="hover:text-brand-700 transition whitespace-nowrap flex items-center gap-1.5"><i class="fa-solid fa-location-crosshairs text-brand-700"></i> Track</a>
                    <a href="#contact" class="hover:text-brand-700 transition whitespace-nowrap">Contact</a>
                    <a href="<?= htmlspecialchars($school_website_url) ?>" target="_blank" class="text-brand-700 hover:text-brand-900 transition whitespace-nowrap flex items-center gap-1.5 font-bold">
                        <i class="fa-solid fa-globe text-gold-500"></i> Website <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                    </a>
                </nav>

                <!-- Action Buttons & Mobile Toggle -->
                <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
                    <a href="app/login.php" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs sm:text-sm font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition whitespace-nowrap">
                        <i class="fa-solid fa-user-lock text-xs text-brand-700"></i> Staff
                    </a>
                    <a href="apply.php" class="inline-flex items-center gap-1.5 px-3.5 sm:px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-white bg-brand-700 hover:bg-brand-800 shadow-sm shadow-brand-700/20 transition hover:-translate-y-0.5 whitespace-nowrap">
                        <i class="fa-solid fa-paper-plane text-[11px] sm:text-xs"></i> <span>Apply</span><span class="hidden sm:inline"> Now</span>
                    </a>
                    <!-- Mobile / Tablet Navigation Toggle -->
                    <button type="button" onclick="document.getElementById('mobileNav').classList.toggle('hidden')" class="xl:hidden w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center rounded-xl text-slate-700 hover:text-brand-700 hover:bg-slate-100 border border-slate-200 transition focus:outline-none" aria-label="Toggle navigation">
                        <i class="fa-solid fa-bars text-sm sm:text-base"></i>
                    </button>
                </div>
            </div>

            <!-- Mobile Navigation Menu -->
            <div id="mobileNav" class="hidden xl:hidden border-t border-slate-200 bg-white px-4 py-3 space-y-1.5 shadow-xl">
                <a href="#pathways" onclick="document.getElementById('mobileNav').classList.add('hidden')" class="flex items-center gap-3 py-2 px-3 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-brand-700 transition">
                    <i class="fa-solid fa-layer-group text-gold-500 w-5 text-center"></i>
                    <span>Entry Pathways</span>
                </a>
                <a href="#fees-structure" onclick="document.getElementById('mobileNav').classList.add('hidden')" class="flex items-center gap-3 py-2 px-3 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-brand-700 transition">
                    <i class="fa-solid fa-coins text-gold-500 w-5 text-center"></i>
                    <span>Fees Structure</span>
                </a>
                <a href="#requirements" onclick="document.getElementById('mobileNav').classList.add('hidden')" class="flex items-center gap-3 py-2 px-3 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-brand-700 transition">
                    <i class="fa-solid fa-list-check text-gold-500 w-5 text-center"></i>
                    <span>Application Procedure</span>
                </a>
                <a href="#registers" onclick="document.getElementById('mobileNav').classList.add('hidden')" class="flex items-center gap-3 py-2 px-3 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-brand-700 transition">
                    <i class="fa-solid fa-graduation-cap text-gold-500 w-5 text-center"></i>
                    <span>Admitted Lists</span>
                </a>
                <a href="track.php" onclick="document.getElementById('mobileNav').classList.add('hidden')" class="flex items-center gap-3 py-2 px-3 rounded-xl text-sm font-bold text-brand-700 hover:bg-brand-50 transition">
                    <i class="fa-solid fa-location-crosshairs text-brand-700 w-5 text-center"></i>
                    <span>Track Application</span>
                </a>
                <a href="#contact" onclick="document.getElementById('mobileNav').classList.add('hidden')" class="flex items-center gap-3 py-2 px-3 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-brand-700 transition">
                    <i class="fa-solid fa-envelope text-gold-500 w-5 text-center"></i>
                    <span>School Contacts</span>
                </a>
                <a href="<?= htmlspecialchars($school_website_url) ?>" target="_blank" class="flex items-center gap-3 py-2 px-3 rounded-xl text-sm font-bold text-brand-700 hover:bg-slate-50 transition">
                    <i class="fa-solid fa-globe text-gold-500 w-5 text-center"></i>
                    <span>Main School Website</span>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-400 ml-auto"></i>
                </a>
                <div class="pt-2 border-t border-slate-100 flex gap-2">
                    <a href="app/login.php" class="flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition">
                        <i class="fa-solid fa-user-lock text-brand-700"></i>
                        <span>Staff Login</span>
                    </a>
                    <a href="apply.php" class="flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-xl text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 shadow-sm transition">
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                        <span>Apply Now</span>
                    </a>
                </div>
            </div>
        </header>

    <!-- Hero Section with Background Slider -->
    <section class="relative overflow-hidden pt-12 pb-16 border-b border-slate-200 text-white min-h-[460px] flex items-center justify-center">
        <!-- Background Slider Layer -->
        <div id="heroSliderContainer" class="absolute inset-0 z-0 overflow-hidden">
            <?php foreach($slider_images as $idx => $img): ?>
                <div class="hero-slide absolute inset-0 bg-cover bg-center transition-opacity duration-1000 ease-in-out <?= $idx === 0 ? 'opacity-100' : 'opacity-0' ?>" 
                     style="background-image: url('<?= htmlspecialchars($img) ?>'); transform: scale(1.03);"></div>
            <?php endforeach; ?>
            <!-- Translucent Tint Overlay for clear slider visibility & readability -->
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/35 via-black/25 to-slate-950/35"></div>
        </div>

        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 w-full">
            <div class="text-center max-w-3xl mx-auto space-y-4">

                <!-- Main Headline -->
                <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight leading-tight sm:leading-none drop-shadow-md">
                    Welcome to <span class="text-gold-400"><?= htmlspecialchars($school_name) ?></span>
                </h1>

                <!-- Subtitle / Motto -->
                <p class="text-sm sm:text-base text-slate-200 leading-relaxed max-w-2xl mx-auto font-normal drop-shadow">
                    Apply for Senior One, Senior Five (A-Level), or Mid-Entry Transfers in a fast, paperless, and secure application process. Complete your application online in just 3 easy steps.
                </p>

                <!-- Action CTAs -->
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <a href="apply.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-6 py-3 rounded-xl text-xs sm:text-sm font-bold text-white bg-brand-700 hover:bg-brand-800 shadow-lg shadow-brand-950/40 transition hover:-translate-y-0.5 border border-white/20">
                        <i class="fa-solid fa-file-pen text-sm"></i> Start Student Application
                    </a>
                    <a href="track.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-xs sm:text-sm font-bold text-slate-900 bg-white hover:bg-slate-100 shadow-md transition">
                        <i class="fa-solid fa-location-crosshairs text-brand-700"></i> Track Application
                    </a>
                    <a href="#registers" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-xs sm:text-sm font-bold text-white bg-white/20 hover:bg-white/30 backdrop-blur-md border border-white/20 shadow-sm transition">
                        <i class="fa-solid fa-list-check text-gold-400"></i> Check Admitted Lists
                    </a>
                </div>

                <!-- Trust / Feature Pillars -->
                <div class="pt-5 grid grid-cols-2 md:grid-cols-4 gap-3 max-w-3xl mx-auto text-left">
                    <div class="bg-white/95 backdrop-blur-sm p-3 rounded-xl border border-white/30 shadow-md flex items-center gap-2.5 text-slate-800">
                        <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center shrink-0 text-xs">
                            <i class="fa-solid fa-bolt"></i>
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-slate-800">Fast Process</span>
                            <span class="block text-[10px] text-slate-500">Under 5 minutes</span>
                        </div>
                    </div>

                    <div class="bg-white/95 backdrop-blur-sm p-3 rounded-xl border border-white/30 shadow-md flex items-center gap-2.5 text-slate-800">
                        <div class="w-8 h-8 rounded-lg bg-gold-50 text-gold-600 flex items-center justify-center shrink-0 text-xs">
                            <i class="fa-solid fa-envelope-circle-check"></i>
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-slate-800">Email Alerts</span>
                            <span class="block text-[10px] text-slate-500">Instant confirmation</span>
                        </div>
                    </div>

                    <div class="bg-white/95 backdrop-blur-sm p-3 rounded-xl border border-white/30 shadow-md flex items-center gap-2.5 text-slate-800">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 text-xs">
                            <i class="fa-solid fa-file-shield"></i>
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-slate-800">UNEB Verified</span>
                            <span class="block text-[10px] text-slate-500">Center <?= htmlspecialchars($school_code) ?></span>
                        </div>
                    </div>

                    <div class="bg-white/95 backdrop-blur-sm p-3 rounded-xl border border-white/30 shadow-md flex items-center gap-2.5 text-slate-800">
                        <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-700 flex items-center justify-center shrink-0 text-xs">
                            <i class="fa-solid fa-qrcode"></i>
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-slate-800">QR Letters</span>
                            <span class="block text-[10px] text-slate-500">Verifiable PDF offers</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Admission Pathways Section -->
    <section id="pathways" class="py-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <span class="text-xs font-bold text-gold-600 uppercase tracking-widest block mb-1">Admissions Pathways</span>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Select Your Desired Entry Level</h2>
            <p class="text-sm text-slate-500 mt-2">Choose the level of admission that matches your current academic credentials to get started.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- Senior One Card -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition flex flex-col justify-between group hover:border-brand-700/40">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center text-xl font-bold group-hover:scale-105 transition">
                            <i class="fa-solid fa-school"></i>
                        </div>
                        <?php if ($status_s1['status'] === 'open'): ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                <i class="fa-solid fa-circle text-[7px] text-emerald-500 me-1"></i> Admissions Open
                            </span>
                        <?php elseif ($status_s1['status'] === 'upcoming'): ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                <i class="fa-solid fa-clock text-[8px] text-amber-500 me-1"></i> <?= htmlspecialchars($status_s1['badge_text']) ?>
                            </span>
                        <?php else: ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                <i class="fa-solid fa-circle-xmark text-[8px] text-rose-500 me-1"></i> Admissions Closed
                            </span>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Senior One (S1)</h3>
                        <p class="text-xs text-slate-500 mt-1">For Primary Leaving Examination (PLE) graduates seeking entry into O-Level.</p>
                        <?php if ($status_s1['status'] === 'open'): ?>
                            <div class="text-[11px] font-semibold text-emerald-700 flex items-center gap-1.5 pt-2">
                                <i class="fa-solid fa-clock text-xs"></i> Deadline: <?= htmlspecialchars($status_s1['formatted_end']) ?>
                            </div>
                        <?php elseif ($status_s1['status'] === 'closed'): ?>
                            <div class="text-[11px] font-semibold text-rose-600 flex items-center gap-1.5 pt-2">
                                <i class="fa-solid fa-calendar-xmark text-xs"></i> Intake Closed (Deadline was <?= htmlspecialchars($status_s1['formatted_end']) ?>)
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="pt-3 border-t border-slate-100 space-y-2">
                        <div class="flex items-start gap-2 text-xs text-slate-600">
                            <i class="fa-solid fa-circle-check text-brand-700 mt-0.5"></i>
                            <span>PLE Grade: Division 1 or Division 2 (Agg. 4 - 24)</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs text-slate-600">
                            <i class="fa-solid fa-circle-check text-brand-700 mt-0.5"></i>
                            <span>Valid UNEB PLE Index Number</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs text-slate-600">
                            <i class="fa-solid fa-circle-check text-brand-700 mt-0.5"></i>
                            <span>Scanned UNEB PLE Result Slip & Passport Photo</span>
                        </div>
                    </div>
                </div>

                <div class="pt-6 mt-4">
                    <?php if ($status_s1['is_open']): ?>
                        <a href="apply.php?class=S1" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-sm">
                            Apply for Senior One <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    <?php else: ?>
                        <button type="button" disabled class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-slate-400 bg-slate-100 border border-slate-200 cursor-not-allowed">
                            Admissions Closed <i class="fa-solid fa-lock text-[10px]"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Senior Five Card -->
            <div class="bg-white rounded-2xl border-2 <?= $status_s5['is_open'] ? 'border-brand-700' : 'border-slate-200' ?> p-6 shadow-md relative flex flex-col justify-between group">
                <?php if ($status_s5['is_open']): ?>
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-brand-700 text-white text-[10px] font-extrabold uppercase tracking-widest px-3 py-0.5 rounded-full shadow-sm">
                        Featured Level
                    </div>
                <?php endif; ?>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-xl bg-gold-50 text-gold-600 flex items-center justify-center text-xl font-bold group-hover:scale-105 transition">
                            <i class="fa-solid fa-graduation-cap"></i>
                        </div>
                        <?php if ($status_s5['status'] === 'open'): ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                <i class="fa-solid fa-circle text-[7px] text-emerald-500 me-1"></i> Admissions Open
                            </span>
                        <?php elseif ($status_s5['status'] === 'upcoming'): ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                <i class="fa-solid fa-clock text-[8px] text-amber-500 me-1"></i> <?= htmlspecialchars($status_s5['badge_text']) ?>
                            </span>
                        <?php else: ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                <i class="fa-solid fa-circle-xmark text-[8px] text-rose-500 me-1"></i> Admissions Closed
                            </span>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Senior Five (S5)</h3>
                        <p class="text-xs text-slate-500 mt-1">For Uganda Certificate of Education (UCE) graduates pursuing Sciences or Arts.</p>
                        <?php if ($status_s5['status'] === 'open'): ?>
                            <div class="text-[11px] font-semibold text-emerald-700 flex items-center gap-1.5 pt-2">
                                <i class="fa-solid fa-clock text-xs"></i> Deadline: <?= htmlspecialchars($status_s5['formatted_end']) ?>
                            </div>
                        <?php elseif ($status_s5['status'] === 'closed'): ?>
                            <div class="text-[11px] font-semibold text-rose-600 flex items-center gap-1.5 pt-2">
                                <i class="fa-solid fa-calendar-xmark text-xs"></i> Intake Closed (Deadline was <?= htmlspecialchars($status_s5['formatted_end']) ?>)
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="pt-3 border-t border-slate-100 space-y-2">
                        <div class="flex items-start gap-2 text-xs text-slate-600">
                            <i class="fa-solid fa-circle-check text-brand-700 mt-0.5"></i>
                            <span>UCE Grade: Division 1, 2, or 3</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs text-slate-600">
                            <i class="fa-solid fa-circle-check text-brand-700 mt-0.5"></i>
                            <span>Sciences: PCM, PCB, BCM, PEM, MEG, HEM</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs text-slate-600">
                            <i class="fa-solid fa-circle-check text-brand-700 mt-0.5"></i>
                            <span>Arts: HEG, HEL, HLD, LEG, GEA</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs text-slate-600">
                            <i class="fa-solid fa-circle-check text-brand-700 mt-0.5"></i>
                            <span>UCE Result Slip & Passport Photo</span>
                        </div>
                    </div>
                </div>

                <div class="pt-6 mt-4">
                    <?php if ($status_s5['is_open']): ?>
                        <a href="apply.php?class=S5" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-sm">
                            Apply for Senior Five <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    <?php else: ?>
                        <button type="button" disabled class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-slate-400 bg-slate-100 border border-slate-200 cursor-not-allowed">
                            Admissions Closed <i class="fa-solid fa-lock text-[10px]"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Transfer Admissions Card -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition flex flex-col justify-between group hover:border-brand-700/40">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-xl font-bold group-hover:scale-105 transition">
                            <i class="fa-solid fa-arrow-right-arrow-left"></i>
                        </div>
                        <?php if ($status_trans['status'] === 'open'): ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                <i class="fa-solid fa-circle text-[7px] text-emerald-500 me-1"></i> Admissions Open
                            </span>
                        <?php elseif ($status_trans['status'] === 'upcoming'): ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                <i class="fa-solid fa-clock text-[8px] text-amber-500 me-1"></i> <?= htmlspecialchars($status_trans['badge_text']) ?>
                            </span>
                        <?php else: ?>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                <i class="fa-solid fa-circle-xmark text-[8px] text-rose-500 me-1"></i> Admissions Closed
                            </span>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Transfers (S2, S3, S4, S6)</h3>
                        <p class="text-xs text-slate-500 mt-1">For students currently in other schools seeking admission into intermediate classes.</p>
                        <?php if ($status_trans['status'] === 'open'): ?>
                            <div class="text-[11px] font-semibold text-emerald-700 flex items-center gap-1.5 pt-2">
                                <i class="fa-solid fa-clock text-xs"></i> Deadline: <?= htmlspecialchars($status_trans['formatted_end']) ?>
                            </div>
                        <?php elseif ($status_trans['status'] === 'closed'): ?>
                            <div class="text-[11px] font-semibold text-rose-600 flex items-center gap-1.5 pt-2">
                                <i class="fa-solid fa-calendar-xmark text-xs"></i> Intake Closed (Deadline was <?= htmlspecialchars($status_trans['formatted_end']) ?>)
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="pt-3 border-t border-slate-100 space-y-2">
                        <div class="flex items-start gap-2 text-xs text-slate-600">
                            <i class="fa-solid fa-circle-check text-brand-700 mt-0.5"></i>
                            <span>Previous Terminal Report Card or Transcript</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs text-slate-600">
                            <i class="fa-solid fa-circle-check text-brand-700 mt-0.5"></i>
                            <span>Valid Transfer Recommendation / Statement</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs text-slate-600">
                            <i class="fa-solid fa-circle-check text-brand-700 mt-0.5"></i>
                            <span>Student Passport Photo & Parent NIN</span>
                        </div>
                    </div>
                </div>

                <div class="pt-6 mt-4">
                    <?php if ($status_trans['is_open']): ?>
                        <a href="apply.php?class=other" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-slate-800 bg-slate-100 hover:bg-slate-200 transition">
                            Apply for Transfer <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    <?php else: ?>
                        <button type="button" disabled class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-slate-400 bg-slate-100 border border-slate-200 cursor-not-allowed">
                            Admissions Closed <i class="fa-solid fa-lock text-[10px]"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </section>

    <!-- Official Approved Fees Structure Section -->
    <?php 
    $feesMatrix = getFeesStructureMatrix($pdo); 
    $feesYear   = !empty($academic_year) ? $academic_year : getAcademicYear($pdo);
    ?>
    <section id="fees-structure" class="py-14 bg-slate-50 border-t border-slate-200">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-10">
                <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    Fees structure for <?= htmlspecialchars($feesYear) ?>
                </h2>
                <p class="text-xs sm:text-sm text-slate-600 mt-2 leading-relaxed">
                    Review the approved termly tuition and functional fees matrix before submitting your application. All figures are in Ugandan Shillings (UGX).
                </p>
            </div>

            <!-- Official Fees Table Container -->
            <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-6 shadow-sm overflow-hidden">
                <!-- Mobile Controls Header -->
                <div class="md:hidden flex flex-col gap-3 mb-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-wide">
                            <i class="fa-solid fa-layer-group text-brand-700 mr-1"></i> Class Fees Breakdown
                        </span>
                        <button type="button" onclick="toggleMobileFeesView('feesMobileCardView', 'feesTableViewContainer', 'feesViewToggleLabel', 'feesViewToggleIcon')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-200 transition">
                            <i class="fa-solid fa-table-list text-xs text-brand-700" id="feesViewToggleIcon"></i>
                            <span id="feesViewToggleLabel">Table View</span>
                        </button>
                    </div>

                    <!-- Class filter pill chips -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none" style="-webkit-overflow-scrolling: touch;">
                        <button type="button" onclick="filterFeesCards('all', this, 'fees-card-index')" class="fees-filter-btn-index px-3 py-1.5 rounded-xl bg-brand-700 text-white text-xs font-bold shadow-xs whitespace-nowrap transition">All Classes</button>
                        <?php foreach ($feesMatrix['rows'] as $r): 
                            $cId = preg_replace('/[^A-Za-z0-9]/', '', strtolower($r['class']));
                        ?>
                        <button type="button" onclick="filterFeesCards('<?= $cId ?>', this, 'fees-card-index')" class="fees-filter-btn-index px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-bold border border-slate-200 whitespace-nowrap transition"><?= htmlspecialchars($r['class']) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 1. Mobile Cards View (Visible by default on mobile, hidden on tablet/desktop) -->
                <div id="feesMobileCardView" class="block md:hidden space-y-4">
                    <?php foreach ($feesMatrix['rows'] as $row): 
                        $cId = preg_replace('/[^A-Za-z0-9]/', '', strtolower($row['class']));
                        $cCode = strtoupper(str_replace(['.', ' ', '-'], '', $row['class']));
                        $isAlevel = in_array($cCode, ['S5', 'S6']);
                    ?>
                    <div class="fees-card-index bg-white rounded-2xl border-2 border-slate-200/90 shadow-sm overflow-hidden transition-all duration-200" data-class="<?= $cId ?>">
                        <!-- Card Header -->
                        <div class="p-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="w-9 h-9 rounded-xl bg-brand-700 text-white font-black text-sm flex items-center justify-center shadow-xs">
                                    <?= htmlspecialchars($row['class']) ?>
                                </span>
                                <div>
                                    <h4 class="font-black text-slate-900 text-sm leading-tight">
                                        Senior <?= substr($cCode, 1) ?> (<?= htmlspecialchars($row['class']) ?>)
                                    </h4>
                                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                                        <?= $isAlevel ? 'Advanced Level (A-Level)' : 'Ordinary Level (O-Level)' ?>
                                    </span>
                                </div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-brand-50 text-brand-800 border border-brand-200">
                                Approved
                            </span>
                        </div>

                        <!-- Fee Line Items -->
                        <div class="p-3.5 space-y-2 text-xs">
                            <?php foreach ($feesMatrix['columns'] as $col): 
                                $val = trim($row[$col['id']] ?? '');
                                if (empty($val) || $val === '-') continue;
                            ?>
                            <div class="flex items-center justify-between py-1 border-b border-slate-100 last:border-b-0 gap-2">
                                <div class="min-w-0">
                                    <span class="font-bold text-slate-800 text-[11.5px] block leading-tight">
                                        <?= htmlspecialchars($col['label']) ?>
                                    </span>
                                    <?php if (!empty($col['sub'])): ?>
                                        <span class="text-[10px] text-slate-400 lowercase font-medium">
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
                        <div class="p-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-2">
                            <span class="text-[10.5px] font-medium text-slate-500">Boarding &amp; Tuition Matrix</span>
                            <a href="apply.php?class=<?= urlencode($cCode) ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-brand-700 hover:bg-brand-800 text-white text-xs font-bold shadow-xs transition">
                                <span>Apply for <?= htmlspecialchars($row['class']) ?></span>
                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- 2. Full Table View (Visible on tablet/desktop, togglable on mobile) -->
                <div id="feesTableViewContainer" class="hidden md:block overflow-x-auto rounded-xl border border-slate-300 shadow-xs" style="-webkit-overflow-scrolling: touch;">
                    <table class="w-full text-xs text-left border-collapse min-w-[760px]">
                        <thead>
                            <tr class="bg-slate-100/90 border-b border-slate-300 text-slate-800 text-[11px] font-extrabold uppercase">
                                <th class="p-3.5 border-r border-slate-300 text-center w-24 sticky left-0 bg-slate-100 z-10 shadow-xs">CLASS</th>
                                <?php foreach ($feesMatrix['columns'] as $col): ?>
                                <th class="p-3.5 border-r border-slate-300 text-center last:border-r-0">
                                    <div class="font-extrabold text-slate-900 leading-tight"><?= htmlspecialchars($col['label']) ?></div>
                                    <?php if (!empty($col['sub'])): ?>
                                    <div class="text-[9.5px] font-medium text-slate-500 lowercase mt-0.5"><?= htmlspecialchars($col['sub']) ?></div>
                                    <?php endif; ?>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white font-mono text-xs">
                            <?php foreach ($feesMatrix['rows'] as $row): ?>
                            <tr class="hover:bg-brand-50/40 transition">
                                <td class="p-3.5 border-r border-slate-300 font-bold text-center text-slate-900 bg-slate-50/95 font-sans sticky left-0 z-10 shadow-xs">
                                    <?= htmlspecialchars($row['class']) ?>
                                </td>
                                <?php foreach ($feesMatrix['columns'] as $col): 
                                    $val = trim($row[$col['id']] ?? '');
                                ?>
                                <td class="p-3.5 border-r border-slate-300 text-right pr-5 sm:pr-7 text-slate-700 last:border-r-0 font-medium tabular-nums whitespace-nowrap">
                                    <?= !empty($val) ? htmlspecialchars($val) : '<div class="text-center font-sans text-slate-300 font-light">-</div>' ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100">
                    <div class="text-[11px] text-slate-500 font-sans">
                        <i class="fa-solid fa-circle-check text-brand-700 mr-1"></i> Official fee breakdown approved by the School Board of Governors.
                    </div>
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <a href="apply.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-sm">
                            Proceed to Online Application <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Step-by-Step Guide Section -->
    <section id="requirements" class="py-16 bg-white border-y border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <span class="text-xs font-bold text-gold-600 uppercase tracking-widest block mb-1">Simple & Structured</span>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">How to Complete Your Application</h2>
                <p class="text-sm text-slate-500 mt-2">Follow our 4-step streamlined procedure to submit your student application.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Step 1 -->
                <div class="relative bg-slate-50 p-6 rounded-2xl border border-slate-200">
                    <span class="text-3xl font-black text-brand-700 absolute top-4 right-4" style="color: <?= $primary_color ?>;">01</span>
                    <div class="w-10 h-10 rounded-xl bg-brand-700 text-white flex items-center justify-center text-sm font-bold mb-4 shadow-sm">
                        <i class="fa-solid fa-user-pen"></i>
                    </div>
                    <h4 class="font-bold text-slate-900 text-sm mb-1">1. Student Bio Data</h4>
                    <p class="text-xs text-slate-500 leading-relaxed">Fill in full legal names, gender, date of birth, religion, and upload a clear passport photo.</p>
                </div>

                <!-- Step 2 -->
                <div class="relative bg-slate-50 p-6 rounded-2xl border border-slate-200">
                    <span class="text-3xl font-black text-brand-700 absolute top-4 right-4" style="color: <?= $primary_color ?>;">02</span>
                    <div class="w-10 h-10 rounded-xl bg-brand-700 text-white flex items-center justify-center text-sm font-bold mb-4 shadow-sm">
                        <i class="fa-solid fa-award"></i>
                    </div>
                    <h4 class="font-bold text-slate-900 text-sm mb-1">2. Academic Info</h4>
                    <p class="text-xs text-slate-500 leading-relaxed">Enter your UNEB index number, former school, grade breakdown, and attach your result slip.</p>
                </div>

                <!-- Step 3 -->
                <div class="relative bg-slate-50 p-6 rounded-2xl border border-slate-200">
                    <span class="text-3xl font-black text-brand-700 absolute top-4 right-4" style="color: <?= $primary_color ?>;">03</span>
                    <div class="w-10 h-10 rounded-xl bg-brand-700 text-white flex items-center justify-center text-sm font-bold mb-4 shadow-sm">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <h4 class="font-bold text-slate-900 text-sm mb-1">3. Parent / Guardian</h4>
                    <p class="text-xs text-slate-500 leading-relaxed">Provide parent/guardian contact, official NIRA National ID Number (NIN), and email address.</p>
                </div>

                <!-- Step 4 -->
                <div class="relative bg-slate-50 p-6 rounded-2xl border border-slate-200">
                    <span class="text-3xl font-black text-brand-700 absolute top-4 right-4" style="color: <?= $primary_color ?>;">04</span>
                    <div class="w-10 h-10 rounded-xl bg-brand-700 text-white flex items-center justify-center text-sm font-bold mb-4 shadow-sm">
                        <i class="fa-solid fa-envelope-circle-check"></i>
                    </div>
                    <h4 class="font-bold text-slate-900 text-sm mb-1">4. Review & Confirm</h4>
                    <p class="text-xs text-slate-500 leading-relaxed">Submit your details and receive an immediate email confirmation with your application summary.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Admission Registers Section -->
    <section id="registers" class="py-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl p-6 sm:p-10 text-white shadow-xl" style="background-color: <?= $primary_color ?>;">
            <div class="max-w-3xl space-y-3">
                <span class="text-xs font-bold text-gold-500 uppercase tracking-widest block">Official Admissions Register</span>
                <h2 class="text-2xl sm:text-3xl font-black tracking-tight">Check Admitted Student Lists</h2>
                <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                    Once our Admissions Committee evaluates applications, approved students are published on the official Admitted Register. You can look up admitted candidates by class below.
                </p>

                <div class="pt-3 flex flex-wrap gap-2.5">
                    <a href="app/admission_list.php?class=S1" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-white text-slate-900 hover:bg-slate-100 transition shadow-sm">
                        <i class="fa-solid fa-list-ol text-brand-700"></i> Senior One List
                    </a>
                    <a href="app/admission_list.php?class=S5" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-gold-500 text-slate-900 hover:bg-gold-400 transition shadow-sm">
                        <i class="fa-solid fa-graduation-cap"></i> Senior Five List
                    </a>
                    <a href="app/admission_list.php?class=S2" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-slate-800 text-slate-200 hover:bg-slate-700 border border-slate-700 transition">
                        <i class="fa-solid fa-users"></i> Transfers List
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- School Contacts Section -->
    <section id="contact" class="py-10 bg-white border-t border-slate-200">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-8">
                <span class="text-xs font-bold text-gold-600 uppercase tracking-widest block mb-1">Get In Touch</span>
                <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Admissions Office & Inquiries</h2>
                <p class="text-xs text-slate-500 mt-1">Have questions about admissions or requirements? Reach out to our admissions team directly.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-center space-y-1.5">
                    <div class="w-9 h-9 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center mx-auto text-sm">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>
                    <h4 class="text-[11px] font-bold text-slate-800 uppercase">Physical Address</h4>
                    <p class="text-xs text-slate-600"><?= htmlspecialchars($school_address) ?></p>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-center space-y-1.5">
                    <div class="w-9 h-9 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center mx-auto text-sm">
                        <i class="fa-solid fa-phone"></i>
                    </div>
                    <h4 class="text-[11px] font-bold text-slate-800 uppercase">Telephone Contacts</h4>
                    <p class="text-xs text-slate-700 font-medium font-sans"><?= htmlspecialchars($school_phone) ?></p>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-center space-y-1.5">
                    <div class="w-9 h-9 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center mx-auto text-sm">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                    <h4 class="text-[11px] font-bold text-slate-800 uppercase">Official Email</h4>
                    <p class="text-xs text-slate-700 font-medium font-sans">
                        <a href="mailto:<?= htmlspecialchars($school_email) ?>" class="text-brand-700 hover:underline"><?= htmlspecialchars($school_email) ?></a>
                    </p>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-center space-y-1.5">
                    <div class="w-9 h-9 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center mx-auto text-sm">
                        <i class="fa-solid fa-globe"></i>
                    </div>
                    <h4 class="text-[11px] font-bold text-slate-800 uppercase">School Website</h4>
                    <p class="text-xs text-slate-700 font-medium font-sans">
                        <a href="http://<?= htmlspecialchars($school_website) ?>" target="_blank" class="text-brand-700 hover:underline"><?= htmlspecialchars($school_website) ?></a>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="mt-auto text-white text-xs py-8" style="background-color: <?= $primary_color ?>;">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 text-center sm:text-left">
                <div>
                    <span class="block font-bold text-white text-xs sm:text-sm"><?= htmlspecialchars($school_name) ?></span>
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
                        Track Application
                    </a>
                    <span>·</span>
                    <a href="app/login.php" class="hover:text-white transition underline-offset-4 hover:underline">
                        Staff Login
                    </a>
                </div>
            </div>

            <div class="pt-4 border-t border-white/20 flex flex-col sm:flex-row justify-between items-center text-[11px] text-slate-100 gap-2">
                <span>School Motto: <?= htmlspecialchars($school_motto) ?> · UNEB Center No: <?= htmlspecialchars($school_code) ?></span>
                <span>
                    Developed by 
                    <a href="https://ncloud.co.ug/shop?item=school-admission-system" target="_blank" rel="noopener noreferrer" class="text-white hover:text-gold-300 font-bold underline decoration-white/50 hover:decoration-gold-300 transition">
                        Nova Cloud Edges
                    </a>
                </span>
            </div>
        </div>
    </footer>

    </div><!-- End Boxed Container Wrapper -->

    <!-- Floating Back-to-Top Button (Strictly Round Circle) -->
    <button id="backToTopBtn" type="button" aria-label="Back to Top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
        class="fixed bottom-6 right-6 z-50 w-11 h-11 rounded-full flex items-center justify-center aspect-square text-white shadow-xl opacity-0 pointer-events-none transition-all duration-300 hover:scale-110 focus:outline-none"
        style="background-color: <?= $primary_color ?>;">
        <i class="fa-solid fa-arrow-up text-sm"></i>
    </button>

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

    <script>
    // Hero Background Slider
    (function() {
        const slides = document.querySelectorAll('.hero-slide');
        if (slides.length <= 1) return;
        let current = 0;
        setInterval(() => {
            slides[current].classList.remove('opacity-100');
            slides[current].classList.add('opacity-0');
            current = (current + 1) % slides.length;
            slides[current].classList.remove('opacity-0');
            slides[current].classList.add('opacity-100');
        }, 5000);
    })();

    function openLegalModal(modalId) {
        const m = document.getElementById(modalId);
        if (m) m.classList.remove('hidden');
    }
    function closeLegalModal(modalId) {
        const m = document.getElementById(modalId);
        if (m) m.classList.add('hidden');
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
    </script>
</body>
</html>
