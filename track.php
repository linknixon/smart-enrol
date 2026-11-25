<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/config/db.php';

// Dynamic school brand configurations
$school_name        = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_motto       = getSetting($pdo, 'school_motto', 'Education for Service');
$school_code        = getSetting($pdo, 'school_code', '011');
$school_logo        = getSchoolLogoSrc($pdo);
$school_phone       = getSetting($pdo, 'school_phone', '+256 790 001 631');
$school_email       = getSetting($pdo, 'school_email', 'admissions@sesemegirls.sc.ug');
$school_address     = getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro - Uganda');
$school_website_url = getSchoolWebsiteUrl($pdo);
$primary_color      = getPrimaryColor($pdo);

$searchQuery = trim($_POST['q'] ?? $_GET['q'] ?? '');
$searched = !empty($searchQuery);
$application = null;
$multipleApplications = [];
$searchError = '';

if ($searched) {
    // Cloudflare Turnstile anti-bot verification
    $turnstileToken = $_POST['cf-turnstile-response'] ?? $_GET['cf-turnstile-response'] ?? '';
    if (!verifyTurnstileToken($pdo, $turnstileToken)) {
        $searchError = "Security Verification Failed: Please complete the Cloudflare anti-bot check.";
        $searched = false;
    } else {
        // Clean search string
        $cleanQuery = trim($searchQuery);
        
        // Normalize phone format if applicable (remove spaces, hyphens)
        $phoneAlt = preg_replace('/[^0-9+]/', '', $cleanQuery);
        $phoneLocal = preg_replace('/^\+256/', '0', $phoneAlt);
        $phoneIntl = preg_replace('/^0/', '+256', $phoneAlt);

        $sql = "SELECT * FROM applications 
                WHERE index_number = :q1 
                   OR lin = :q2 
                   OR parent_email = :q3 
                   OR parent_phone = :q4 
                   OR parent_phone = :q5 
                   OR parent_phone = :q6 
                   OR parent_nin = :q7
                ORDER BY id DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':q1' => $cleanQuery,
        ':q2' => $cleanQuery,
        ':q3' => $cleanQuery,
        ':q4' => $cleanQuery,
        ':q5' => $phoneLocal,
        ':q6' => $phoneIntl,
        ':q7' => strtoupper($cleanQuery)
    ]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($results)) {
        if (count($results) === 1) {
            $application = $results[0];
        } else {
            $multipleApplications = $results;
            // If specific id is selected
            if (isset($_GET['app_id'])) {
                foreach ($results as $r) {
                    if ((int)$r['id'] === (int)$_GET['app_id']) {
                        $application = $r;
                        break;
                    }
                }
            }
            if (!$application) {
                $application = $results[0]; // default to latest
            }
        }
    } else {
        $searchError = "No application record found matching '<strong>" . htmlspecialchars($searchQuery) . "</strong>'. Please ensure you have entered the exact Phone number, Email address, UNEB Index number, or Learner Identification Number (LIN) used during application.";
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Track Application Status | <?= htmlspecialchars($school_short_name) ?></title>
<link rel="icon" type="image/png" href="<?= !empty($school_logo) ? htmlspecialchars($school_logo) : 'images/default_logo.png' ?>">
<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com" data-cfasync="false"></script>
<!-- Google Fonts & Font Awesome -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= renderThemeConfig($pdo) ?>
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased min-h-screen flex flex-col py-0 sm:py-6 px-0 sm:px-4">

    <!-- Boxed Container Wrapper -->
    <div class="max-w-5xl w-full mx-auto bg-white sm:rounded-3xl sm:border sm:border-slate-200 sm:shadow-xl overflow-hidden flex flex-col">

        <!-- Navigation Header -->
        <header class="bg-white/95 backdrop-blur-md sticky top-0 z-40 border-b border-slate-200 shadow-sm transition">
            <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8 py-2.5 sm:py-3.5 flex justify-between items-center gap-2 sm:gap-4">
                <!-- Brand Info -->
                <a href="index.php" class="flex items-center gap-2.5 sm:gap-3.5 group min-w-0 pr-1 sm:pr-2">
                    <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-white border border-slate-200/90 p-1 flex items-center justify-center shrink-0 group-hover:scale-105 transition shadow-xs">
                        <img src="<?= htmlspecialchars($school_logo) ?>" alt="<?= htmlspecialchars($school_short_name) ?> Logo" class="max-w-full max-h-full object-contain">
                    </div>
                    <div class="min-w-0 flex-1">
                        <span class="block text-[13px] sm:text-base md:text-lg font-black text-slate-900 tracking-tight leading-snug uppercase group-hover:text-brand-700 transition truncate">
                            <?= htmlspecialchars($school_short_name) ?>
                        </span>
                        <?php if (!empty($school_motto)): ?>
                            <span class="block text-[10px] sm:text-[11.5px] font-bold text-gold-600 italic tracking-wide leading-tight truncate mt-0.5" title="<?= htmlspecialchars($school_motto) ?>">
                                “<?= htmlspecialchars($school_motto) ?>”
                            </span>
                        <?php else: ?>
                            <span class="block text-[9.5px] sm:text-[11px] font-semibold text-gold-600 uppercase tracking-wider leading-tight truncate">
                                Application Tracking Portal
                            </span>
                        <?php endif; ?>
                    </div>
                </a>

                <!-- Action Buttons -->
                <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                    <a href="index.php" class="inline-flex items-center gap-1 sm:gap-1.5 px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-xl text-xs sm:text-sm font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition whitespace-nowrap">
                        <i class="fa-solid fa-arrow-left text-[11px] sm:text-xs text-brand-700"></i>
                        <span class="hidden sm:inline">Main Portal</span>
                        <span class="sm:hidden">Home</span>
                    </a>
                    <a href="apply.php" class="inline-flex items-center gap-1 sm:gap-1.5 px-3 sm:px-4 py-1.5 sm:py-2 rounded-xl text-xs sm:text-sm font-bold text-white bg-brand-700 hover:bg-brand-800 shadow-sm shadow-brand-700/20 transition hover:-translate-y-0.5 whitespace-nowrap">
                        <i class="fa-solid fa-paper-plane text-[10px] sm:text-xs"></i>
                        <span>Apply</span>
                        <span class="hidden sm:inline">Now</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Tracking Banner & Search Hero -->
        <div class="bg-gradient-to-b from-brand-50/60 via-white to-slate-50 border-b border-slate-200 py-10 px-4 sm:px-8">
            <div class="max-w-3xl mx-auto text-center space-y-3">
                <h1 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight">
                    Track Your Admissions Progress
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 max-w-xl mx-auto leading-relaxed">
                    Check your real-time admission progress, review stage, and download your official provisional admission offer letter once admitted.
                </p>

                <!-- Search Input Form -->
                <form action="track.php" method="POST" class="mt-6 pt-2">
                    <div class="flex flex-col sm:flex-row gap-2 max-w-2xl mx-auto bg-white p-2 rounded-2xl border-2 border-brand-700/30 shadow-lg shadow-brand-700/5 focus-within:border-brand-700 transition">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </span>
                            <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" required autofocus
                                placeholder="Enter Phone, Email, Index Number, or LIN..."
                                class="w-full pl-10 pr-4 py-2.5 text-xs sm:text-sm font-medium text-slate-800 placeholder-slate-400 rounded-xl focus:outline-none">
                        </div>
                        <button type="submit" class="px-6 py-2.5 bg-brand-700 hover:bg-brand-800 text-white rounded-xl text-xs sm:text-sm font-bold shadow-md shadow-brand-700/20 transition duration-150 flex items-center justify-center gap-2 shrink-0">
                            <span>Track Application</span>
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </button>
                    </div>

                    <?= renderTurnstileWidget($pdo) ?>

                    <p class="text-[11px] text-slate-400 mt-2">
                        <i class="fa-solid fa-circle-info mr-1"></i> Searchable by Parent's Phone, Registered Email, UNEB Index No, or Learner ID (LIN).
                    </p>
                </form>
            </div>
        </div>

        <!-- Tracking Results Body -->
        <main class="flex-1 p-4 sm:p-8 space-y-6">

            <?php if ($searchError): ?>
                <div class="max-w-3xl mx-auto bg-rose-50 border border-rose-200 rounded-2xl p-6 text-center space-y-3 shadow-sm animate-in fade-in duration-200">
                    <div class="w-12 h-12 bg-rose-100 text-rose-600 rounded-2xl flex items-center justify-center mx-auto text-xl">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <h3 class="text-sm sm:text-base font-bold text-rose-900">Application Record Not Found</h3>
                    <p class="text-xs sm:text-sm text-rose-700 leading-relaxed"><?= $searchError ?></p>
                    <div class="pt-2 flex flex-wrap justify-center gap-3">
                        <a href="track.php" class="px-4 py-2 rounded-xl text-xs font-bold bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 transition">
                            <i class="fa-solid fa-rotate-left mr-1"></i> Try Another Search
                        </a>
                        <a href="apply.php" class="px-4 py-2 rounded-xl text-xs font-bold bg-brand-700 text-white hover:bg-brand-800 transition">
                            <i class="fa-solid fa-paper-plane mr-1"></i> Submit New Application
                        </a>
                    </div>
                </div>
            <?php elseif ($application): ?>

                <?php 
                $status = strtolower($application['application_status'] ?? 'pending');
                $classCode = $application['class_of_entry'] ?? 'S1';
                $fullName = trim($application['firstname'] . ' ' . $application['surname'] . ' ' . ($application['othernames'] ?? ''));
                $appYear = !empty($application['academic_year']) ? $application['academic_year'] : date('Y');
                
                // Determine Stepper progress indices
                // 1. Submitted (Done)
                // 2. Under Review (Done if admitted or rejected, Active if pending)
                // 3. Admissions Board Evaluation (Done if admitted or rejected, In-progress if pending)
                // 4. Decision (Admitted: Success, Rejected: Warning, Pending: Waiting)
                $isAdmittedOrReported = ($status === 'admitted' || $status === 'reported');
                $step1 = 'done';
                $step2 = ($isAdmittedOrReported || $status === 'rejected') ? 'done' : 'active';
                $step3 = ($isAdmittedOrReported || $status === 'rejected') ? 'done' : 'active';
                $step4 = ($status === 'reported') ? 'reported' : (($status === 'admitted') ? 'success' : (($status === 'rejected') ? 'rejected' : 'waiting'));
                
                // Fetch class fee summary
                $cKey = strtolower($classCode);
                $tuition = getSetting($pdo, "fees_{$cKey}_tuition", '0');
                $admissionFee = getSetting($pdo, "fees_{$cKey}_admission", '0');
                $functional = getSetting($pdo, "fees_{$cKey}_functional", '0');
                $totalTermFees = (int)$tuition + (int)$admissionFee + (int)$functional;
                
                $reportingDate = getSetting($pdo, ($classCode === 'S1' ? 'reporting_date_s1' : ($classCode === 'S5' ? 'reporting_date_s5' : 'reporting_date_trans')), 'To be communicated upon admission');
                ?>

                <!-- If multiple applications found under same contact, show picker -->
                <?php if (count($multipleApplications) > 1): ?>
                <div class="max-w-4xl mx-auto bg-amber-50 border border-amber-200 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2 text-amber-900 font-bold">
                        <i class="fa-solid fa-users-viewfinder text-amber-600 text-base"></i>
                        <span>Multiple student applications (<?= count($multipleApplications) ?>) found under this contact:</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($multipleApplications as $otherApp): ?>
                            <a href="track.php?q=<?= urlencode($searchQuery) ?>&app_id=<?= $otherApp['id'] ?>" class="px-3 py-1.5 rounded-lg font-bold border transition <?= ((int)$otherApp['id'] === (int)$application['id']) ? 'bg-amber-600 text-white border-amber-600 shadow-sm' : 'bg-white text-slate-700 border-amber-200 hover:bg-amber-100' ?>">
                                <?= htmlspecialchars(trim($otherApp['firstname'] . ' ' . $otherApp['surname'])) ?> (<?= htmlspecialchars($otherApp['class_of_entry']) ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($status === 'reported'): ?>
                <!-- Reported & Enrolled Student Card (Simplified View) -->
                <div class="max-w-2xl mx-auto bg-white rounded-3xl border-2 border-emerald-300 shadow-xl overflow-hidden animate-in fade-in duration-300">
                    <div class="p-8 sm:p-10 space-y-6 text-center">
                        <div class="w-20 h-20 bg-emerald-100 text-emerald-800 rounded-full flex items-center justify-center mx-auto text-3xl shadow-inner border border-emerald-200">
                            <i class="fa-solid fa-graduation-cap"></i>
                        </div>
                        
                        <div class="space-y-3">
                            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-purple-100 text-purple-900 border border-purple-300">
                                <i class="fa-solid fa-user-check text-purple-700"></i> Enrolled & Currently at School
                            </span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-snug">
                                Congratulations, <?= htmlspecialchars($fullName) ?>!
                            </h2>
                            <p class="text-sm sm:text-base text-slate-700 max-w-lg mx-auto leading-relaxed">
                                The Admissions Committee of <strong><?= htmlspecialchars($school_name) ?></strong> has offered you admission into <strong><?= htmlspecialchars($classCode) ?></strong> for Academic Year <strong><?= htmlspecialchars($appYear) ?></strong>.
                            </p>
                        </div>

                        <!-- Only Class & Gender Display -->
                        <div class="grid grid-cols-2 gap-4 max-w-sm mx-auto pt-4 border-t border-slate-100">
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-center">
                                <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Class</span>
                                <span class="block text-base sm:text-lg font-black text-slate-900 mt-1"><?= htmlspecialchars($classCode) ?></span>
                            </div>
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-center">
                                <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Gender</span>
                                <span class="block text-base sm:text-lg font-black text-slate-900 mt-1"><?= ucfirst(htmlspecialchars($application['sex'])) ?></span>
                            </div>
                        </div>

                        <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200 text-xs text-emerald-900 font-semibold max-w-md mx-auto flex items-center justify-center gap-2">
                            <i class="fa-solid fa-circle-check text-emerald-700 text-base shrink-0"></i>
                            <span>Campus reporting verified. Student is currently on campus attending school.</span>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <!-- Candidate Overview Card -->
                <div class="max-w-4xl mx-auto bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-6 sm:p-8 bg-slate-50/70 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="flex items-center gap-4">
                            <?php if (!empty($application['passport_photo']) && file_exists(__DIR__ . '/' . $application['passport_photo'])): ?>
                                <img src="<?= htmlspecialchars($application['passport_photo']) ?>" alt="Photo" class="w-16 h-16 rounded-2xl object-cover border-2 border-brand-700 shadow-md">
                            <?php else: ?>
                                <div class="w-16 h-16 rounded-2xl bg-brand-700 text-white flex items-center justify-center text-xl font-black shadow-md">
                                    <?= strtoupper(substr($application['firstname'], 0, 1) . substr($application['surname'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-brand-100 text-brand-900 border border-brand-200 mb-1">
                                    Student Number: <?= htmlspecialchars(getAdmissionNumber($application)) ?> · Application #<?= str_pad($application['id'], 5, '0', STR_PAD_LEFT) ?> · Cohort <?= htmlspecialchars($appYear) ?>
                                </span>
                                <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight leading-tight">
                                    <?= htmlspecialchars($fullName) ?>
                                </h2>
                                <p class="text-xs text-slate-500 font-medium mt-0.5">
                                    Class: <strong class="text-slate-800"><?= htmlspecialchars($classCode) ?></strong> · 
                                    Index: <strong class="text-slate-800 font-mono"><?= htmlspecialchars($application['index_number'] ?: 'N/A') ?></strong>
                                    <?php if (!empty($application['lin'])): ?>
                                        · LIN: <strong class="text-slate-800 font-mono"><?= htmlspecialchars($application['lin']) ?></strong>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Status Badge Pill -->
                        <div>
                            <?php if ($status === 'admitted'): ?>
                                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-xs sm:text-sm font-black bg-emerald-100 text-emerald-800 border-2 border-emerald-300 shadow-sm animate-pulse">
                                    <i class="fa-solid fa-circle-check text-emerald-600"></i> PROVISIONALLY ADMITTED
                                </span>
                            <?php elseif ($status === 'rejected'): ?>
                                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-xs sm:text-sm font-black bg-rose-100 text-rose-800 border-2 border-rose-300 shadow-sm">
                                    <i class="fa-solid fa-circle-xmark text-rose-600"></i> NOT ADMITTED
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-xs sm:text-sm font-black bg-amber-100 text-amber-800 border-2 border-amber-300 shadow-sm">
                                    <i class="fa-solid fa-hourglass-half text-amber-600 animate-spin"></i> UNDER EVALUATION
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Interactive Progress Tracker Stepper -->
                    <div class="p-6 sm:p-8 space-y-8">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-6">
                                <i class="fa-solid fa-bars-progress text-brand-700 mr-1.5"></i> Application Review Milestones
                            </h3>

                            <div class="relative">
                                <!-- Horizontal Connecting Bar (Desktop) -->
                                <div class="hidden sm:block absolute top-1/2 left-0 right-0 h-1 bg-slate-200 -translate-y-1/2 z-0"></div>
                                <div class="hidden sm:block absolute top-1/2 left-0 h-1 bg-brand-700 -translate-y-1/2 z-0 transition-all duration-500"
                                     style="width: <?= ($step4 === 'success') ? '100%' : (($step4 === 'rejected') ? '100%' : '65%') ?>;"></div>

                                <!-- Stepper Nodes -->
                                <div class="grid grid-cols-1 sm:grid-cols-4 gap-6 relative z-10">
                                    
                                    <!-- Step 1: Submission -->
                                    <div class="flex sm:flex-col items-center sm:text-center gap-3 sm:gap-2">
                                        <div class="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shadow-md ring-4 ring-white shrink-0">
                                            <i class="fa-solid fa-check"></i>
                                        </div>
                                        <div>
                                            <span class="block text-xs font-bold text-slate-900">1. Application Filed</span>
                                            <span class="block text-[10px] text-slate-400"><?= !empty($application['created_at']) ? date('M j, Y', strtotime($application['created_at'])) : 'Completed' ?></span>
                                        </div>
                                    </div>

                                    <!-- Step 2: Verification -->
                                    <div class="flex sm:flex-col items-center sm:text-center gap-3 sm:gap-2">
                                        <div class="w-10 h-10 rounded-full <?= ($step2 === 'done') ? 'bg-emerald-600 text-white' : 'bg-brand-700 text-white animate-pulse' ?> flex items-center justify-center font-bold text-sm shadow-md ring-4 ring-white shrink-0">
                                            <?= ($step2 === 'done') ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-file-circle-check"></i>' ?>
                                        </div>
                                        <div>
                                            <span class="block text-xs font-bold text-slate-900">2. Documents Verified</span>
                                            <span class="block text-[10px] text-slate-400">UNEB & Bio-data Review</span>
                                        </div>
                                    </div>

                                    <!-- Step 3: Admissions Board -->
                                    <div class="flex sm:flex-col items-center sm:text-center gap-3 sm:gap-2">
                                        <div class="w-10 h-10 rounded-full <?= ($step3 === 'done') ? 'bg-emerald-600 text-white' : 'bg-brand-700 text-white ring-brand-200 ring-4' ?> flex items-center justify-center font-bold text-sm shadow-md ring-4 ring-white shrink-0">
                                            <?= ($step3 === 'done') ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-user-check"></i>' ?>
                                        </div>
                                        <div>
                                            <span class="block text-xs font-bold text-slate-900">3. Board Assessment</span>
                                            <span class="block text-[10px] text-slate-400">Merit & Cutoff Standing</span>
                                        </div>
                                    </div>

                                    <!-- Step 4: Decision -->
                                    <div class="flex sm:flex-col items-center sm:text-center gap-3 sm:gap-2">
                                        <?php if ($step4 === 'success'): ?>
                                            <div class="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shadow-md ring-4 ring-white shrink-0">
                                                <i class="fa-solid fa-graduation-cap"></i>
                                            </div>
                                            <div>
                                                <span class="block text-xs font-bold text-emerald-700">4. Offer Extended</span>
                                                <span class="block text-[10px] text-emerald-600 font-semibold">Provisional Admission</span>
                                            </div>
                                        <?php elseif ($step4 === 'rejected'): ?>
                                            <div class="w-10 h-10 rounded-full bg-rose-600 text-white flex items-center justify-center font-bold text-sm shadow-md ring-4 ring-white shrink-0">
                                                <i class="fa-solid fa-xmark"></i>
                                            </div>
                                            <div>
                                                <span class="block text-xs font-bold text-rose-700">4. Intake Decision</span>
                                                <span class="block text-[10px] text-rose-600 font-semibold">Application Declined</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-full bg-slate-200 text-slate-400 flex items-center justify-center font-bold text-sm shadow-md ring-4 ring-white shrink-0">
                                                <i class="fa-solid fa-clock"></i>
                                            </div>
                                            <div>
                                                <span class="block text-xs font-bold text-slate-500">4. Final Decision</span>
                                                <span class="block text-[10px] text-slate-400">Decision Pending</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- Milestone Decision Detail Panels -->
                        <?php if ($status === 'admitted'): ?>
                            <div class="p-6 rounded-2xl bg-emerald-50 border border-emerald-200 space-y-4 animate-in fade-in duration-300">
                                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-emerald-200/80">
                                    <div>
                                        <span class="text-xs font-black text-emerald-800 uppercase tracking-widest block mb-1">
                                            <i class="fa-solid fa-award mr-1"></i> Official Admission Confirmation
                                        </span>
                                        <h4 class="text-base sm:text-xl font-extrabold text-emerald-950">
                                            Congratulations, <?= htmlspecialchars($fullName) ?>!
                                        </h4>
                                        <p class="text-xs text-emerald-800 mt-1">
                                            The Admissions Committee of <?= htmlspecialchars($school_name) ?> has offered you admission into <strong><?= htmlspecialchars($classCode) ?></strong> for Academic Year <strong><?= htmlspecialchars($appYear) ?></strong>.
                                        </p>
                                    </div>
                                    <a href="app/admission_offer.php?id=<?= $application['id'] ?>" target="_blank" class="w-full sm:w-auto justify-center px-5 py-3 rounded-xl text-xs sm:text-sm font-black text-white bg-emerald-700 hover:bg-emerald-800 shadow-md shadow-emerald-700/20 transition flex items-center gap-2 shrink-0 text-center">
                                        <i class="fa-solid fa-file-pdf"></i>
                                        <span>Download Official Offer Letter (PDF)</span>
                                    </a>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                                    <div class="bg-white/80 p-3.5 rounded-xl border border-emerald-200/60">
                                        <span class="block text-[11px] font-bold text-slate-500 uppercase">Reporting Date</span>
                                        <span class="block text-sm font-extrabold text-slate-800 mt-0.5"><?= htmlspecialchars($reportingDate) ?></span>
                                    </div>
                                    <div class="bg-white/80 p-3.5 rounded-xl border border-emerald-200/60">
                                        <span class="block text-[11px] font-bold text-slate-500 uppercase">Class & Stream</span>
                                        <span class="block text-sm font-extrabold text-slate-800 mt-0.5"><?= htmlspecialchars($classCode) ?> <?= ($application['combination'] ? ' · ' . htmlspecialchars($application['combination']) : '') ?></span>
                                    </div>
                                    <div class="bg-white/80 p-3.5 rounded-xl border border-emerald-200/60">
                                        <span class="block text-[11px] font-bold text-slate-500 uppercase">Term 1 Fees Payable</span>
                                        <span class="block text-sm font-extrabold text-slate-800 mt-0.5 font-mono">UGX <?= number_format($totalTermFees) ?></span>
                                    </div>
                                </div>

                                <p class="text-[11px] text-emerald-800 italic pt-1">
                                    * Please note: This provisional offer is subject to physical verification of your original UNEB result slips and birth certificate upon arrival on the reporting date.
                                </p>
                            </div>
                        <?php elseif ($status === 'rejected'): ?>
                            <div class="p-6 rounded-2xl bg-rose-50 border border-rose-200 space-y-3 animate-in fade-in duration-300">
                                <div class="flex items-center gap-2.5 text-rose-900 font-bold text-base">
                                    <i class="fa-solid fa-circle-exclamation text-rose-600 text-xl"></i>
                                    <span>Application Not Successful</span>
                                </div>
                                <p class="text-xs sm:text-sm text-rose-800 leading-relaxed">
                                    We regret to inform you that your application for admission into <strong><?= htmlspecialchars($classCode) ?></strong> could not be accommodated at this time.
                                </p>
                                <?php if (!empty($application['rejection_reason'])): ?>
                                    <div class="p-3.5 bg-white rounded-xl border border-rose-200 text-xs text-rose-900 space-y-1">
                                        <span class="block font-bold text-[11px] uppercase text-rose-500">Official Committee Reason:</span>
                                        <p class="font-medium"><?= nl2br(htmlspecialchars($application['rejection_reason'])) ?></p>
                                    </div>
                                <?php endif; ?>
                                <p class="text-[11px] text-rose-700">
                                    For inquiries regarding alternative placements or next academic intakes, you may contact the Admissions Registry at <a href="mailto:<?= htmlspecialchars($school_email) ?>" class="underline font-bold"><?= htmlspecialchars($school_email) ?></a> or call <span class="font-bold"><?= htmlspecialchars($school_phone) ?></span>.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="p-6 rounded-2xl bg-amber-50 border border-amber-200 space-y-3 animate-in fade-in duration-300">
                                <div class="flex items-center gap-2.5 text-amber-900 font-bold text-base">
                                    <i class="fa-solid fa-hourglass-start text-amber-600 text-xl animate-spin"></i>
                                    <span>Application Under Active Review</span>
                                </div>
                                <p class="text-xs sm:text-sm text-amber-800 leading-relaxed">
                                    Your application and academic credentials have been received and are currently undergoing evaluation against the school's intake capacity and pass mark policies. Decisions are posted as soon as the Admissions Board concludes deliberations.
                                </p>
                                <div class="bg-white/80 p-3.5 rounded-xl border border-amber-200/70 text-xs text-slate-700 space-y-1">
                                    <div class="flex items-center gap-2 font-semibold">
                                        <i class="fa-solid fa-bell text-amber-600"></i>
                                        <span>Automated Notifications:</span>
                                    </div>
                                    <p class="text-[11px] text-slate-500">
                                        You will receive an official notification via email at <strong class="text-slate-800"><?= htmlspecialchars($application['parent_email']) ?></strong> and SMS on <strong class="text-slate-800"><?= htmlspecialchars($application['parent_phone']) ?></strong> immediately when a decision is published.
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Full Bio-Data & Submission Snapshot Summary -->
                        <div class="pt-4 border-t border-slate-100">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                                Submission Information Summary
                            </h4>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                                <div>
                                    <span class="block text-[11px] text-slate-400">Gender</span>
                                    <span class="font-bold text-slate-800"><?= htmlspecialchars($application['sex']) ?></span>
                                </div>
                                <div>
                                    <span class="block text-[11px] text-slate-400">Date of Birth / Age</span>
                                    <span class="font-bold text-slate-800"><?= htmlspecialchars($application['dob']) ?> (<?= (int)$application['age'] ?> yrs)</span>
                                </div>
                                <div>
                                    <span class="block text-[11px] text-slate-400">Parent / Guardian</span>
                                    <span class="font-bold text-slate-800"><?= htmlspecialchars($application['parent_name']) ?></span>
                                </div>
                                <div>
                                    <span class="block text-[11px] text-slate-400">Parent Telephone</span>
                                    <span class="font-bold text-slate-800 font-mono"><?= htmlspecialchars($application['parent_phone']) ?></span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Fresh State Prompt -->
                <div class="max-w-2xl mx-auto text-center py-12 px-4 space-y-4">
                    <div class="w-16 h-16 rounded-3xl bg-brand-50 text-brand-700 flex items-center justify-center mx-auto text-2xl shadow-sm">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800">Track An Existing Application</h3>
                    <p class="text-xs sm:text-sm text-slate-500 max-w-md mx-auto leading-relaxed">
                        Enter any of your registration credentials above (Parent's Phone, Email, UNEB Index Number, or LIN) to view current standing and access your official admission documents.
                    </p>
                    <div class="pt-4 flex justify-center gap-3">
                        <a href="index.php#registers" class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                            <i class="fa-solid fa-list-ol mr-1"></i> View Admitted Student Registers
                        </a>
                        <a href="apply.php" class="px-4 py-2.5 rounded-xl text-xs font-bold bg-brand-700 hover:bg-brand-800 text-white shadow-sm transition">
                            <i class="fa-solid fa-paper-plane mr-1"></i> Apply for Admission
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </main>

        <!-- Footer -->
        <footer class="mt-auto text-white text-xs py-8" style="background-color: <?= $primary_color ?>;">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 text-center sm:text-left">
                    <div>
                        <span class="block font-bold text-white text-sm sm:text-base"><?= htmlspecialchars($school_name) ?></span>
                        <span class="block text-[11px] text-slate-100">&copy; <?= date('Y') ?> All Rights Reserved. Official Online Admissions System.</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs font-semibold text-slate-100">
                        <a href="index.php" class="hover:text-white transition underline-offset-4 hover:underline">Home</a>
                        <span>·</span>
                        <a href="apply.php" class="hover:text-white transition underline-offset-4 hover:underline">Apply Now</a>
                        <span>·</span>
                        <a href="app/login.php" class="hover:text-white transition underline-offset-4 hover:underline">Staff Login</a>
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

    <script>
    // Back to top floating button logic
    const bttBtn = document.getElementById('backToTopBtn');
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
    </script>
</body>
</html>
