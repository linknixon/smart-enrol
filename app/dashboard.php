<?php
require_once __DIR__ . '/auth.php';

// Fetch dynamic configurations
$school_name       = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$logo_source       = getSchoolLogoSrc($pdo, '../');
$school_website_url = getSchoolWebsiteUrl($pdo);
$adminFirstName     = getAdminDisplayName($pdo, $_SESSION['admin_username'] ?? '');
$currentRole        = $_SESSION['admin_role'] ?? '';

// Parse filter inputs
$classFilter  = $_GET['class'] ?? 'ALL';
$statusFilter = $_GET['status'] ?? 'ALL';
$yearFilter   = $_GET['academic_year'] ?? 'all';
$search       = trim($_GET['search'] ?? '');
$primary_color = getPrimaryColor($pdo);

// Available academic years
$availableYears = [];
try {
    $availableYears = $pdo->query("SELECT DISTINCT academic_year FROM applications WHERE academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
$curYear = getSetting($pdo, 'academic_year', date('Y'));
if (!in_array($curYear, $availableYears)) {
    array_unshift($availableYears, $curYear);
}

// Fetch configured A-Level combinations
$rawCombs = getSetting($pdo, 'a_level_combinations', '');
$aLevelCombinations = [];
if (!empty($rawCombs)) {
    $decoded = json_decode($rawCombs, true);
    if (is_array($decoded)) {
        foreach ($decoded as $cat => $items) {
            if (is_array($items)) {
                foreach ($items as $code => $label) {
                    $aLevelCombinations[] = ['category' => $cat, 'code' => $code, 'name' => $label];
                }
            }
        }
    }
}
if (empty($aLevelCombinations)) {
    $aLevelCombinations = [
        ['category' => 'Sciences', 'code' => 'PCM/ICT', 'name' => 'Physics, Chemistry, Mathematics & ICT'],
        ['category' => 'Sciences', 'code' => 'PCB/ICT', 'name' => 'Physics, Chemistry, Biology & ICT'],
        ['category' => 'Sciences', 'code' => 'PCB/SMT', 'name' => 'Physics, Chemistry, Biology & Sub-Math'],
        ['category' => 'Sciences', 'code' => 'BCM/ICT', 'name' => 'Biology, Chemistry, Mathematics & ICT'],
        ['category' => 'Sciences', 'code' => 'PEM/ICT', 'name' => 'Physics, Economics, Mathematics & ICT'],
        ['category' => 'Sciences', 'code' => 'MEG/ICT', 'name' => 'Mathematics, Economics, Geography & ICT'],
        ['category' => 'Sciences', 'code' => 'BCG/ICT', 'name' => 'Biology, Chemistry, Geography & ICT'],
        ['category' => 'Sciences', 'code' => 'BCG/SMT', 'name' => 'Biology, Chemistry, Geography & Sub-Math'],
        ['category' => 'Sciences', 'code' => 'MAG/ICT', 'name' => 'Mathematics, Agriculture, Geography & ICT'],
        ['category' => 'Arts & Humanities', 'code' => 'HEG/ICT', 'name' => 'History, Economics, Geography & ICT'],
        ['category' => 'Arts & Humanities', 'code' => 'HEG/SMT', 'name' => 'History, Economics, Geography & Sub-Math'],
        ['category' => 'Arts & Humanities', 'code' => 'HEL/ICT', 'name' => 'History, Economics, Literature & ICT'],
        ['category' => 'Arts & Humanities', 'code' => 'HEL/SMT', 'name' => 'History, Economics, Literature & Sub-Math'],
        ['category' => 'Arts & Humanities', 'code' => 'HLD/ICT', 'name' => 'History, Literature, Divinity & ICT'],
        ['category' => 'Arts & Humanities', 'code' => 'HLD/SMT', 'name' => 'History, Literature, Divinity & Sub-Math'],
        ['category' => 'Arts & Humanities', 'code' => 'DEG/ICT', 'name' => 'Divinity, Economics, Geography & ICT'],
        ['category' => 'Arts & Humanities', 'code' => 'DEG/SMT', 'name' => 'Divinity, Economics, Geography & Sub-Math'],
        ['category' => 'Arts & Humanities', 'code' => 'HED/ICT', 'name' => 'History, Economics, Divinity & ICT'],
        ['category' => 'Arts & Humanities', 'code' => 'LEG/ICT', 'name' => 'Literature, Economics, Geography & ICT'],
        ['category' => 'Arts & Humanities', 'code' => 'GEA/ICT', 'name' => 'Geography, Entrepreneurship, Art & ICT'],
        ['category' => 'Arts & Humanities', 'code' => 'MEA/ICT', 'name' => 'Mathematics, Economics, Art & ICT']
    ];
}

// Metrics (Independent overall counts)
$totalApps = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$pendingCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE application_status = 'pending'")->fetchColumn();
$admittedCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE application_status = 'admitted'")->fetchColumn();
$reportedCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE application_status = 'reported'")->fetchColumn();
$rejectedCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE application_status = 'rejected'")->fetchColumn();

// Fetch A-Level combinations for quick combination change
$combinationsRaw = getSetting($pdo, 'a_level_combinations', '');
$aLevelCombs = !empty($combinationsRaw) ? json_decode($combinationsRaw, true) : null;
if (!is_array($aLevelCombs) || empty($aLevelCombs)) {
    $aLevelCombs = [
        ["code" => "PCM/ICT", "name" => "Physics, Chemistry, Mathematics & ICT"],
        ["code" => "PCB/ICT", "name" => "Physics, Chemistry, Biology & ICT"],
        ["code" => "PCB/SMT", "name" => "Physics, Chemistry, Biology & Sub-Math"],
        ["code" => "BCM/ICT", "name" => "Biology, Chemistry, Mathematics & ICT"],
        ["code" => "PEM/ICT", "name" => "Physics, Economics, Mathematics & ICT"],
        ["code" => "MEG/ICT", "name" => "Mathematics, Economics, Geography & ICT"],
        ["code" => "BCG/ICT", "name" => "Biology, Chemistry, Geography & ICT"],
        ["code" => "BCG/SMT", "name" => "Biology, Chemistry, Geography & Sub-Math"],
        ["code" => "MAG/ICT", "name" => "Mathematics, Agriculture, Geography & ICT"],
        ["code" => "HEG/ICT", "name" => "History, Economics, Geography & ICT"],
        ["code" => "HEG/SMT", "name" => "History, Economics, Geography & Sub-Math"],
        ["code" => "HEL/ICT", "name" => "History, Economics, Literature & ICT"],
        ["code" => "HEL/SMT", "name" => "History, Economics, Literature & Sub-Math"],
        ["code" => "HLD/ICT", "name" => "History, Literature, Divinity & ICT"],
        ["code" => "HLD/SMT", "name" => "History, Literature, Divinity & Sub-Math"],
        ["code" => "DEG/ICT", "name" => "Divinity, Economics, Geography & ICT"],
        ["code" => "DEG/SMT", "name" => "Divinity, Economics, Geography & Sub-Math"],
        ["code" => "HED/ICT", "name" => "History, Economics, Divinity & ICT"],
        ["code" => "LEG/ICT", "name" => "Literature, Economics, Geography & ICT"],
        ["code" => "GEA/ICT", "name" => "Geography, Entrepreneurship, Art & ICT"],
        ["code" => "MEA/ICT", "name" => "Mathematics, Economics, Art & ICT"]
    ];
}

// Multi-Year Intake Trends & Categorical Analytics
$yearLabels = [];
$admittedTrend = [];
$enrolledTrend = [];
$rejectedTrend = [];
$academicTrend = [];
$allDistinctYears = [];
$categoricalAnalytics = [];

try {
    $multiYearQuery = $pdo->query("
        SELECT 
            academic_year,
            COUNT(CASE WHEN application_status IN ('admitted', 'reported') THEN 1 END) AS admitted_count,
            COUNT(CASE WHEN application_status = 'reported' THEN 1 END) AS enrolled_count,
            COUNT(CASE WHEN application_status = 'rejected' THEN 1 END) AS rejected_count,
            ROUND(AVG(CASE WHEN aggregates > 0 THEN aggregates ELSE NULL END), 1) AS avg_aggs
        FROM applications 
        WHERE academic_year IS NOT NULL AND academic_year != ''
        GROUP BY academic_year
        ORDER BY academic_year ASC
    ");
    $multiYearRows = $multiYearQuery->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($multiYearRows)) {
        foreach ($multiYearRows as $r) {
            $yearLabels[] = (string)$r['academic_year'];
            $admittedTrend[] = (int)$r['admitted_count'];
            $enrolledTrend[] = (int)$r['enrolled_count'];
            $rejectedTrend[] = (int)$r['rejected_count'];
            $academicTrend[] = !empty($r['avg_aggs']) ? (float)$r['avg_aggs'] : 0;
            $allDistinctYears[] = (string)$r['academic_year'];
        }
    }
} catch (Exception $e) {}

$activeSysYear = getAcademicYear($pdo);
if (!in_array($activeSysYear, $allDistinctYears)) {
    $allDistinctYears[] = $activeSysYear;
}
rsort($allDistinctYears);

if (empty($yearLabels)) {
    $cYear = (int)date('Y');
    $yearLabels = [(string)($cYear - 2), (string)($cYear - 1), (string)$cYear];
    $admittedTrend = [0, 0, 0];
    $enrolledTrend = [0, 0, 0];
    $rejectedTrend = [0, 0, 0];
    $academicTrend = [0, 0, 0];
}

// Build Categorical Analytics by Academic Year
$initCatStruct = function($label) {
    return [
        'year_label' => $label,
        'total' => 0,
        'admitted' => 0,
        'enrolled' => 0,
        'rejected' => 0,
        'pending' => 0,
        'classes' => ['S1' => 0, 'S2' => 0, 'S3' => 0, 'S4' => 0, 'S5' => 0, 'S6' => 0],
        'admitted_by_class' => ['S1' => 0, 'S2' => 0, 'S3' => 0, 'S4' => 0, 'S5' => 0, 'S6' => 0],
        'enrolled_by_class' => ['S1' => 0, 'S2' => 0, 'S3' => 0, 'S4' => 0, 'S5' => 0, 'S6' => 0],
        'rejected_by_class' => ['S1' => 0, 'S2' => 0, 'S3' => 0, 'S4' => 0, 'S5' => 0, 'S6' => 0],
        'gender' => ['Female' => 0, 'Male' => 0, 'Other' => 0],
        'divisions' => ['Division 1' => 0, 'Division 2' => 0, 'Division 3' => 0, 'Division 4' => 0, 'Division U' => 0],
        'class_aggs_sum' => ['S1' => 0, 'S2' => 0, 'S3' => 0, 'S4' => 0, 'S5' => 0, 'S6' => 0],
        'class_aggs_cnt' => ['S1' => 0, 'S2' => 0, 'S3' => 0, 'S4' => 0, 'S5' => 0, 'S6' => 0],
        'avg_aggs_by_class' => ['S1' => 0, 'S2' => 0, 'S3' => 0, 'S4' => 0, 'S5' => 0, 'S6' => 0],
        'aggs_sum' => 0,
        'aggs_count' => 0,
        'overall_avg_aggs' => 0,
        'enrollment_rate' => 0
    ];
};

$categoricalAnalytics['all'] = $initCatStruct('All Historical Years');
foreach ($allDistinctYears as $yr) {
    $categoricalAnalytics[$yr] = $initCatStruct('Academic Year ' . $yr);
}

try {
    $catRawStmt = $pdo->query("
        SELECT academic_year, class_of_entry, sex, division, application_status, aggregates
        FROM applications 
        WHERE academic_year IS NOT NULL AND academic_year != ''
    ");
    $catRawRows = $catRawStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($catRawRows as $r) {
        $yr = (string)$r['academic_year'];
        if (!isset($categoricalAnalytics[$yr])) {
            $categoricalAnalytics[$yr] = $initCatStruct('Academic Year ' . $yr);
        }

        $targets = ['all', $yr];
        $st = strtolower(trim($r['application_status'] ?? 'pending'));
        $cls = strtoupper(trim($r['class_of_entry'] ?? ''));
        $sx = ucfirst(strtolower(trim($r['sex'] ?? '')));
        $div = trim($r['division'] ?? '');
        $ag = (int)($r['aggregates'] ?? 0);

        foreach ($targets as $t) {
            $categoricalAnalytics[$t]['total']++;
            if ($st === 'reported') {
                $categoricalAnalytics[$t]['enrolled']++;
                $categoricalAnalytics[$t]['admitted']++;
            } elseif ($st === 'admitted') {
                $categoricalAnalytics[$t]['admitted']++;
            } elseif ($st === 'rejected') {
                $categoricalAnalytics[$t]['rejected']++;
            } else {
                $categoricalAnalytics[$t]['pending']++;
            }

            if (isset($categoricalAnalytics[$t]['classes'][$cls])) {
                $categoricalAnalytics[$t]['classes'][$cls]++;
                if ($st === 'admitted' || $st === 'reported') {
                    $categoricalAnalytics[$t]['admitted_by_class'][$cls]++;
                }
                if ($st === 'reported') {
                    $categoricalAnalytics[$t]['enrolled_by_class'][$cls]++;
                }
                if ($st === 'rejected') {
                    $categoricalAnalytics[$t]['rejected_by_class'][$cls]++;
                }
            }

            if ($sx === 'Female' || $sx === 'Male') {
                $categoricalAnalytics[$t]['gender'][$sx]++;
            } else {
                $categoricalAnalytics[$t]['gender']['Other']++;
            }

            if (stripos($div, '1') !== false) {
                $categoricalAnalytics[$t]['divisions']['Division 1']++;
            } elseif (stripos($div, '2') !== false) {
                $categoricalAnalytics[$t]['divisions']['Division 2']++;
            } elseif (stripos($div, '3') !== false) {
                $categoricalAnalytics[$t]['divisions']['Division 3']++;
            } elseif (stripos($div, '4') !== false) {
                $categoricalAnalytics[$t]['divisions']['Division 4']++;
            } elseif (stripos($div, 'u') !== false) {
                $categoricalAnalytics[$t]['divisions']['Division U']++;
            }

            if ($ag > 0) {
                $categoricalAnalytics[$t]['aggs_sum'] += $ag;
                $categoricalAnalytics[$t]['aggs_count']++;
                if (isset($categoricalAnalytics[$t]['class_aggs_sum'][$cls])) {
                    $categoricalAnalytics[$t]['class_aggs_sum'][$cls] += $ag;
                    $categoricalAnalytics[$t]['class_aggs_cnt'][$cls]++;
                }
            }
        }
    }

    // Finalize averages and yield rates
    foreach ($categoricalAnalytics as $k => &$cData) {
        $cData['enrollment_rate'] = ($cData['admitted'] > 0) ? round(($cData['enrolled'] / $cData['admitted']) * 100, 1) : 0;
        $cData['overall_avg_aggs'] = ($cData['aggs_count'] > 0) ? round($cData['aggs_sum'] / $cData['aggs_count'], 1) : 0;
        foreach (['S1', 'S2', 'S3', 'S4', 'S5', 'S6'] as $c) {
            $cnt = $cData['class_aggs_cnt'][$c] ?? 0;
            $sum = $cData['class_aggs_sum'][$c] ?? 0;
            $cData['avg_aggs_by_class'][$c] = ($cnt > 0) ? round($sum / $cnt, 1) : 0;
        }
    }
    unset($cData);
} catch (Exception $e) {}

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="overflow-x-hidden">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | <?= htmlspecialchars($school_short_name) ?></title>
<link rel="icon" type="image/png" href="<?= !empty($logo_source) ? htmlspecialchars($logo_source) : '../images/default_logo.png' ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<?= renderThemeConfig($pdo) ?>
<!-- Local Chart.js -->
<script src="js/chart.min.js" data-cfasync="false"></script>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-h-screen flex flex-col md:flex-row overflow-x-hidden">

    <!-- Mobile Drawer Overlay & Sidebar -->
    <div id="mobileSidebarBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden" onclick="toggleMobileSidebar()"></div>
    <aside id="mobileSidebar" class="fixed top-0 bottom-0 left-0 w-72 text-white flex flex-col z-50 transform -translate-x-full transition-transform duration-200 ease-in-out md:hidden shadow-2xl" style="background-color: <?= htmlspecialchars($primary_color) ?>;">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="<?= $logo_source ?>" alt="Logo" width="36" height="36" style="width: 36px; height: 36px; object-fit: contain;" class="w-9 h-9 bg-white rounded-full p-1 border-2 border-gold-500 object-contain logo-img">
                <div class="min-w-0">
                    <span class="block text-xs font-bold text-white tracking-tight uppercase truncate"><?= htmlspecialchars($school_short_name) ?></span>
                    <span class="block text-[9px] font-semibold text-gold-400 uppercase tracking-widest">Consolidated Portal</span>
                </div>
            </div>
            <button type="button" onclick="toggleMobileSidebar()" class="text-white/70 hover:text-white p-1" aria-label="Close menu">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <nav class="flex-1 px-4 py-4 space-y-1.5 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-bold bg-white/20 text-white ring-1 ring-white/30 shadow-sm transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1-S6)
            </a>
            <a href="applications.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-id-card-clip text-lg text-gold-400"></i> Applications
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted Register
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-file-invoice text-lg text-gold-400"></i> Custom Reports
            </a>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-users text-lg"></i> Staff Users
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_roles') || hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="roles.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-shield-halved text-lg"></i> Roles & Access
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_view_logs')): ?>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-clock-rotate-left text-lg"></i> Activity Logs
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_settings') || hasPermission($pdo, $currentRole, 'can_manage_admission_policies')): ?>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-sliders text-lg text-gold-400"></i> <?= hasPermission($pdo, $currentRole, 'can_manage_settings') ? 'System Settings' : 'Policies & Fees' ?>
            </a>
            <?php endif; ?>
            <div class="pt-4 border-t border-white/10 mt-4">
                <a href="<?= htmlspecialchars($school_website_url) ?>" target="_blank" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-white/80 hover:text-white rounded-lg hover:bg-white/10 transition">
                    <i class="fa-solid fa-globe text-gold-400"></i> Main School Website <i class="fa-solid fa-arrow-up-right-from-square text-[9px] ml-auto"></i>
                </a>
            </div>
        </nav>
        <div class="px-5 py-3 border-t border-white/10 text-[11px] text-white/60 text-center">
            Admissions Office · <?= htmlspecialchars(getAcademicYear($pdo)) ?>
        </div>
    </aside>

    <!-- Desktop Sidebar -->
    <aside class="hidden md:flex w-64 text-white flex-col shrink-0 min-h-screen" style="background-color: <?= htmlspecialchars($primary_color) ?>;">
        <!-- Brand Header -->
        <div class="px-6 py-5 border-b border-white/10 flex items-center gap-3">
            <img src="<?= $logo_source ?>" alt="Logo" width="40" height="40" style="width: 40px; height: 40px; object-fit: contain;" class="w-10 h-10 bg-white rounded-full p-1 border-2 border-gold-500 object-contain logo-img">
            <div>
                <span class="block text-sm font-bold text-white tracking-tight leading-tight uppercase"><?= htmlspecialchars($school_short_name) ?></span>
                <span class="block text-xs font-semibold text-gold-400 uppercase tracking-widest text-[9px]">Consolidated Portal</span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 px-4 py-6 space-y-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-bold bg-white/20 text-white ring-1 ring-white/30 shadow-sm transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1-S6)
            </a>
            <a href="applications.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-id-card-clip text-lg text-gold-400"></i> Applications
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted Register
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-file-invoice text-lg text-gold-400"></i> Custom Reports
            </a>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-users text-lg"></i> Staff Users
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_roles') || hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="roles.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-shield-halved text-lg"></i> Roles & Access
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_view_logs')): ?>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-clock-rotate-left text-lg"></i> Activity Logs
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_settings') || hasPermission($pdo, $currentRole, 'can_manage_admission_policies')): ?>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-sliders text-lg text-gold-400"></i> <?= hasPermission($pdo, $currentRole, 'can_manage_settings') ? 'System Settings' : 'Policies & Fees' ?>
            </a>
            <?php endif; ?>

            <div class="pt-4 border-t border-white/10 mt-4">
                <a href="<?= htmlspecialchars($school_website_url) ?>" target="_blank" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-white/80 hover:text-white rounded-lg hover:bg-white/10 transition">
                    <i class="fa-solid fa-globe text-gold-400"></i> Main School Website <i class="fa-solid fa-arrow-up-right-from-square text-[9px] ml-auto"></i>
                </a>
            </div>
        </nav>

        <div class="px-6 py-4 border-t border-white/10 text-xs text-white/60 text-center">
            Admissions Office · <?= htmlspecialchars(getAcademicYear($pdo)) ?>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto overflow-x-hidden">
        
        <!-- Header -->
        <header class="bg-white border-b border-slate-200 px-4 sm:px-6 md:px-8 py-3.5 flex justify-between items-center shadow-sm relative z-10">
            <div class="flex items-center gap-3">
                <button type="button" onclick="toggleMobileSidebar()" class="md:hidden p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl focus:outline-none" aria-label="Toggle Navigation">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <h1 class="text-base sm:text-xl font-bold text-slate-800 tracking-tight">Admissions Control Dashboard</h1>
            </div>

            <!-- User Profile Dropdown -->
            <div class="relative">
                <button id="profileDropdownBtn" onclick="toggleDropdown()" class="flex items-center gap-2.5 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl hover:bg-slate-100 transition focus:outline-none">
                    <div class="w-8 h-8 rounded-full bg-brand-700 text-white flex items-center justify-center font-bold text-xs uppercase shadow-sm">
                        <?= strtoupper(substr($adminFirstName, 0, 1)) ?>
                    </div>
                    <div class="text-left hidden sm:block">
                        <span class="block text-xs font-bold text-slate-800">Welcome, <?= htmlspecialchars($adminFirstName) ?></span>
                        <span class="block text-[10px] text-slate-400 capitalize"><?= htmlspecialchars($_SESSION['admin_role'] === 'secretary' ? 'Admissions Registry' : str_replace('_', ' ', $_SESSION['admin_role'])) ?></span>
                    </div>
                    <i class="fa-solid fa-chevron-down text-slate-400 text-xs ml-1"></i>
                </button>

                <!-- Dropdown Card -->
                <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-xl shadow-lg py-2 z-20">
                    <div class="px-4 py-2 border-b border-slate-100">
                        <span class="block text-xs font-bold text-slate-400 uppercase">Role</span>
                        <span class="block text-sm font-semibold text-slate-700 capitalize"><?= htmlspecialchars($_SESSION['admin_role'] === 'secretary' ? 'Admissions Registry' : str_replace('_', ' ', $_SESSION['admin_role'])) ?></span>
                    </div>
                    <a href="change_password.php" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition duration-150">
                        <i class="fa-solid fa-key text-slate-400"></i> Change Password
                    </a>
                    <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50 transition duration-150">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="flex-1 px-4 sm:px-6 md:px-8 py-5 sm:py-6 space-y-5 sm:space-y-6">

            <!-- Flash Message Alert -->
            <?php if($msg): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 p-4 rounded-lg flex items-center gap-3 shadow-sm animate-pulse">
                <i class="fa-solid fa-circle-check text-xl text-emerald-500"></i>
                <span class="text-sm font-semibold">Action completed successfully.</span>
            </div>
            <?php endif; ?>

            <!-- Metrics Cards Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5 sm:gap-4">
                <!-- Total -->
                <div class="bg-white p-2.5 sm:p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider">Total</span>
                        <span class="block text-lg sm:text-2xl font-extrabold text-slate-800 mt-0.5"><?= $totalApps ?></span>
                    </div>
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center text-sm sm:text-base shrink-0">
                        <i class="fa-solid fa-folder-open"></i>
                    </div>
                </div>
                <!-- Pending -->
                <div class="bg-white p-2.5 sm:p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider">Pending</span>
                        <span class="block text-lg sm:text-2xl font-extrabold text-slate-800 mt-0.5"><?= $pendingCount ?></span>
                    </div>
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-sm sm:text-base shrink-0">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                </div>
                <!-- Admitted -->
                <div class="bg-white p-2.5 sm:p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider">Admitted</span>
                        <span class="block text-lg sm:text-2xl font-extrabold text-slate-800 mt-0.5"><?= $admittedCount ?></span>
                    </div>
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm sm:text-base shrink-0">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
                <!-- Reported (Enrolled & Archived) -->
                <div class="bg-white p-2.5 sm:p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] sm:text-xs font-bold text-purple-700 uppercase tracking-wider">Enrolled</span>
                        <span class="block text-lg sm:text-2xl font-extrabold text-purple-900 mt-0.5"><?= $reportedCount ?></span>
                    </div>
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-purple-50 text-purple-700 flex items-center justify-center text-sm sm:text-base shrink-0">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </div>
                <!-- Rejected -->
                <div class="col-span-2 sm:col-span-1 bg-white p-2.5 sm:p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider">Rejected</span>
                        <span class="block text-lg sm:text-2xl font-extrabold text-slate-800 mt-0.5"><?= $rejectedCount ?></span>
                    </div>
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-sm sm:text-base shrink-0">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>
                </div>
            </div>

            <!-- Applications Registry Quick Navigation Banner -->
            <div class="bg-gradient-to-r from-brand-900 via-brand-800 to-slate-900 rounded-2xl p-4 sm:p-5 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm border border-brand-800">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-lg text-gold-400 border border-white/20 shrink-0">
                        <i class="fa-solid fa-id-card-clip"></i>
                    </span>
                    <div>
                        <h3 class="font-extrabold text-sm sm:text-base text-white tracking-tight">Student Candidate Applications & Registry</h3>
                        <p class="text-xs text-brand-200">Admit candidates, review documents, assign subject combinations, and track admission statuses.</p>
                    </div>
                </div>
                <a href="applications.php" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gold-500 hover:bg-gold-600 text-slate-900 font-extrabold text-xs sm:text-sm rounded-xl transition shadow-sm shrink-0">
                    <span>Open Applications Registry</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </a>
            </div>

            <!-- Interactive Analytics & Multi-Year Trends Switcher -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center text-lg font-bold border border-brand-200 shrink-0">
                        <i class="fa-solid fa-chart-pie"></i>
                    </span>
                    <div>
                        <h2 class="text-sm sm:text-base font-extrabold text-slate-900 uppercase tracking-tight flex items-center gap-2">
                            Intake Analytics & Trends
                            <span id="activeGraphYearBadge" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-brand-100 text-brand-800">
                                All Historical Years
                            </span>
                        </h2>
                        <p class="text-[11px] text-slate-500">Interactive year switching for intake charts and categorical demographic breakdown.</p>
                    </div>
                </div>

                <!-- Year Selector Switcher -->
                <div class="flex items-center gap-2 self-start md:self-auto">
                    <label for="graphYearFilter" class="text-xs font-bold text-slate-600 uppercase tracking-wider whitespace-nowrap flex items-center gap-1.5">
                        <i class="fa-solid fa-clock-rotate-left text-brand-700"></i> Select Year:
                    </label>
                    <select id="graphYearFilter" onchange="switchGraphYear(this.value)" class="px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-brand-700 cursor-pointer shadow-2xs">
                        <option value="all">All Historical Years (Overview)</option>
                        <?php foreach($allDistinctYears as $yr): ?>
                            <option value="<?= htmlspecialchars($yr) ?>">Academic Year <?= htmlspecialchars($yr) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Analysed Categorical Data Summary Panel -->
            <div id="categoricalDataCard" class="bg-gradient-to-r from-slate-900 via-slate-800 to-brand-950 text-white p-4 sm:p-5 rounded-2xl shadow-md border border-slate-700/80 space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-white/10 pb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-lg bg-gold-500/20 text-gold-400 flex items-center justify-center font-bold text-sm shrink-0 border border-gold-500/30">
                            <i class="fa-solid fa-layer-group"></i>
                        </span>
                        <div>
                            <span id="catHeaderTitle" class="text-xs sm:text-sm font-black uppercase tracking-wider text-white">
                                Categorical Data Analysis · All Historical Years
                            </span>
                            <p class="text-[11px] text-slate-400 mt-0.5">Holistic demographic overview, intake distribution, and performance merit.</p>
                        </div>
                    </div>
                    <!-- KPI Counter Chips in Responsive 2x2 Grid on Mobile -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs w-full lg:w-auto">
                        <div class="px-2.5 py-1 rounded-lg bg-white/10 border border-white/15 flex items-center gap-1.5 shadow-2xs">
                            <span class="text-slate-400 text-[11px]">Total Applied:</span>
                            <span id="catTotalApps" class="font-black text-amber-300 font-mono">0</span>
                        </div>
                        <div class="px-2.5 py-1 rounded-lg bg-emerald-500/20 border border-emerald-500/30 flex items-center gap-1.5 text-emerald-300 shadow-2xs">
                            <span class="text-[11px]">Admitted:</span>
                            <span id="catTotalAdmitted" class="font-black font-mono">0</span>
                        </div>
                        <div class="px-2.5 py-1 rounded-lg bg-purple-500/20 border border-purple-500/30 flex items-center gap-1.5 text-purple-300 shadow-2xs">
                            <span class="text-[11px]">Enrolled:</span>
                            <span id="catTotalEnrolled" class="font-black font-mono">0</span>
                        </div>
                        <div class="px-2.5 py-1 rounded-lg bg-gold-500/20 border border-gold-500/30 flex items-center gap-1.5 text-gold-300 shadow-2xs">
                            <span class="text-[11px]">Yield Rate:</span>
                            <span id="catYieldRate" class="font-black font-mono">0%</span>
                        </div>
                    </div>
                </div>

                <!-- 3 Categorical Breakdown Modules: Class Intake, Gender Balance, Academic Divisions -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-4">
                    <!-- 1. Class Intake Distribution -->
                    <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-3.5 space-y-2.5 shadow-2xs">
                        <div class="flex items-center justify-between text-[11px] uppercase font-bold text-slate-300 tracking-wider">
                            <span class="flex items-center gap-1.5"><i class="fa-solid fa-graduation-cap text-gold-400"></i> Class Intake (S1 - S6)</span>
                            <span class="text-[10px] text-slate-400 font-mono lowercase">total / admitted</span>
                        </div>
                        <div id="catClassPills" class="grid grid-cols-3 gap-1.5 text-center">
                            <!-- Populated via JS -->
                        </div>
                    </div>

                    <!-- 2. Gender Demographics -->
                    <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-3.5 space-y-2.5 shadow-2xs">
                        <div class="flex items-center justify-between text-[11px] uppercase font-bold text-slate-300 tracking-wider">
                            <span class="flex items-center gap-1.5"><i class="fa-solid fa-venus-mars text-sky-400"></i> Gender Distribution</span>
                            <span id="catGenderRatioText" class="text-white font-mono text-[11px]">0F : 0M</span>
                        </div>
                        <div class="w-full bg-slate-700/80 rounded-full h-3 overflow-hidden flex shadow-inner">
                            <div id="catGenderFemaleBar" class="bg-pink-500 h-3 transition-all duration-300" style="width: 50%" title="Female"></div>
                            <div id="catGenderMaleBar" class="bg-sky-500 h-3 transition-all duration-300" style="width: 50%" title="Male"></div>
                        </div>
                        <div class="flex justify-between items-center text-xs font-semibold pt-0.5">
                            <span id="catFemaleCount" class="px-2 py-0.5 rounded bg-pink-500/20 text-pink-300 border border-pink-500/30 text-[11px]">Female: 0 (0%)</span>
                            <span id="catMaleCount" class="px-2 py-0.5 rounded bg-sky-500/20 text-sky-300 border border-sky-500/30 text-[11px]">Male: 0 (0%)</span>
                        </div>
                    </div>

                    <!-- 3. Academic Division / Standing Profile -->
                    <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-3.5 space-y-2.5 shadow-2xs">
                        <div class="flex items-center justify-between text-[11px] uppercase font-bold text-slate-300 tracking-wider">
                            <span class="flex items-center gap-1.5"><i class="fa-solid fa-award text-amber-400"></i> Performance Divisions</span>
                            <span class="text-[10px] text-slate-400 font-mono lowercase">candidates</span>
                        </div>
                        <div id="catDivisionBadges" class="flex flex-wrap gap-1.5 text-xs">
                            <!-- Populated via JS -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multi-Year Intake Trends & Academic Performance Charts Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                
                <!-- Graph 1: Admitted Students by Year -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center text-sm font-bold border border-emerald-200">
                                <i class="fa-solid fa-circle-check"></i>
                            </span>
                            <div>
                                <h3 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-tight">Admitted Students</h3>
                                <p id="admittedGraphSubtitle" class="text-[10.5px] text-slate-400">Total offers extended vs. Years</p>
                            </div>
                        </div>
                        <span id="admittedGraphBadge" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800">
                            <?= end($admittedTrend) ?> Admitted (<?= end($yearLabels) ?>)
                        </span>
                    </div>
                    <div class="pt-3 h-44 relative">
                        <canvas id="admittedChart"></canvas>
                    </div>
                </div>

                <!-- Graph 2: Enrolled / Reported Students by Year -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-purple-50 text-purple-700 flex items-center justify-center text-sm font-bold border border-purple-200">
                                <i class="fa-solid fa-user-check"></i>
                            </span>
                            <div>
                                <h3 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-tight">Enrolled Students</h3>
                                <p id="enrolledGraphSubtitle" class="text-[10.5px] text-slate-400">Physically reported & active vs. Years</p>
                            </div>
                        </div>
                        <span id="enrolledGraphBadge" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-purple-100 text-purple-900">
                            <?= end($enrolledTrend) ?> Enrolled (<?= end($yearLabels) ?>)
                        </span>
                    </div>
                    <div class="pt-3 h-44 relative">
                        <canvas id="enrolledChart"></canvas>
                    </div>
                </div>

                <!-- Graph 3: Rejected Students by Year -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-rose-50 text-rose-700 flex items-center justify-center text-sm font-bold border border-rose-200">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </span>
                            <div>
                                <h3 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-tight">Rejected Applications</h3>
                                <p id="rejectedGraphSubtitle" class="text-[10.5px] text-slate-400">Declined submissions vs. Years</p>
                            </div>
                        </div>
                        <span id="rejectedGraphBadge" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800">
                            <?= end($rejectedTrend) ?> Declined (<?= end($yearLabels) ?>)
                        </span>
                    </div>
                    <div class="pt-3 h-44 relative">
                        <canvas id="rejectedChart"></canvas>
                    </div>
                </div>

            </div>

            <!-- Academic Performance Trend Card -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-lg bg-gold-50 text-gold-700 flex items-center justify-center text-sm font-bold border border-gold-200">
                            <i class="fa-solid fa-chart-line"></i>
                        </span>
                        <div>
                            <h3 id="academicGraphTitle" class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-tight">Academic Performance Trends Across Years</h3>
                            <p id="academicGraphSubtitle" class="text-[10.5px] text-slate-400">Mean UNEB Aggregates of Applicants (Lower score = Higher Distinction standing in Uganda)</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="inline-flex items-center gap-1.5 font-bold text-slate-600">
                            <span class="w-3 h-3 rounded-full bg-gold-500 inline-block"></span> Mean Score Standing
                        </span>
                    </div>
                </div>
                <div class="pt-3 h-44 relative">
                    <canvas id="academicPerformanceChart"></canvas>
                </div>
            </div>

        </div>
    </main>

    <!-- Floating Back to Top Button (Strictly Round Circle) -->
    <button id="backToTopBtn" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" 
            class="fixed bottom-6 right-6 z-50 w-11 h-11 rounded-full flex items-center justify-center aspect-square text-white shadow-xl opacity-0 pointer-events-none transition-all duration-300 hover:scale-110 focus:outline-none"
            style="background-color: <?= htmlspecialchars($primary_color) ?>;"
            aria-label="Back to Top">
        <i class="fa-solid fa-arrow-up text-sm"></i>
    </button>

    <script>
    function toggleMobileSidebar() {
        const sidebar = document.getElementById('mobileSidebar');
        const backdrop = document.getElementById('mobileSidebarBackdrop');
        if (!sidebar || !backdrop) return;
        sidebar.classList.toggle('-translate-x-full');
        backdrop.classList.toggle('hidden');
    }
    function toggleDropdown() {
        const dd = document.getElementById('profileDropdown');
        if (dd) dd.classList.toggle('hidden');
    }
    window.addEventListener('click', function(e) {
        const btn = document.getElementById('profileDropdownBtn');
        const dd = document.getElementById('profileDropdown');
        if (btn && dd && !btn.contains(e.target) && !dd.contains(e.target)) {
            dd.classList.add('hidden');
        }
    });
    window.addEventListener('scroll', function() {
        const btn = document.getElementById('backToTopBtn');
        if (btn) {
            if (window.scrollY > 300) {
                btn.classList.remove('opacity-0', 'pointer-events-none');
                btn.classList.add('opacity-100');
            } else {
                btn.classList.add('opacity-0', 'pointer-events-none');
                btn.classList.remove('opacity-100');
            }
        }
    });
    </script>

    <!-- Chart.js Multi-Year & Categorical Analytics Initialization -->
    <script>
    const categoricalData = <?= json_encode($categoricalAnalytics, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const multiYearLabels = <?= json_encode($yearLabels) ?>;
    const multiYearAdmitted = <?= json_encode($admittedTrend) ?>;
    const multiYearEnrolled = <?= json_encode($enrolledTrend) ?>;
    const multiYearRejected = <?= json_encode($rejectedTrend) ?>;
    const multiYearAcademic = <?= json_encode($academicTrend) ?>;

    let admittedChartInstance = null;
    let enrolledChartInstance = null;
    let rejectedChartInstance = null;
    let academicChartInstance = null;

    const commonBarOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(15, 23, 42, 0.9)',
                padding: 9,
                cornerRadius: 8,
                titleFont: { size: 11, weight: 'bold' },
                bodyFont: { size: 10.5 }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0, font: { size: 9.5 } },
                grid: { color: 'rgba(226, 232, 240, 0.6)' }
            },
            x: {
                ticks: { font: { size: 10, weight: 'bold' } },
                grid: { display: false }
            }
        }
    };

    function switchGraphYear(year) {
        const cat = categoricalData[year] || categoricalData['all'] || {};
        
        // 1. Update Active Year Badge & Header Titles
        const yearBadge = document.getElementById('activeGraphYearBadge');
        if (yearBadge) {
            yearBadge.textContent = (year === 'all') ? 'All Historical Years' : ('Academic Year ' + year);
        }
        const catTitle = document.getElementById('catHeaderTitle');
        if (catTitle) {
            catTitle.textContent = (year === 'all') 
                ? 'Categorical Data Analysis · All Historical Years' 
                : ('Categorical Data Analysis · Academic Year ' + year);
        }

        // 2. Update KPI Counter Chips
        const totalAppsEl = document.getElementById('catTotalApps');
        if (totalAppsEl) totalAppsEl.textContent = cat.total || 0;
        const totalAdmEl = document.getElementById('catTotalAdmitted');
        if (totalAdmEl) totalAdmEl.textContent = cat.admitted || 0;
        const totalEnrEl = document.getElementById('catTotalEnrolled');
        if (totalEnrEl) totalEnrEl.textContent = cat.enrolled || 0;
        const yieldEl = document.getElementById('catYieldRate');
        if (yieldEl) {
            yieldEl.textContent = (cat.enrollment_rate || 0) + '%';
        }

        // 3. Update Class Intake Pills (Detailed & High Contrast)
        const classPillsEl = document.getElementById('catClassPills');
        if (classPillsEl) {
            const classes = ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'];
            const classCounts = cat.classes || {};
            const admCounts = cat.admitted_by_class || {};
            classPillsEl.innerHTML = classes.map(c => `
                <div class="p-2 bg-slate-900/90 rounded-xl border border-slate-700/80 text-center shadow-xs">
                    <span class="block text-[10.5px] font-black text-amber-300 uppercase">${c}</span>
                    <span class="block text-sm font-black text-white leading-tight mt-0.5">${classCounts[c] || 0}</span>
                    <span class="block text-[9.5px] text-emerald-400 font-bold mt-0.5">${admCounts[c] || 0} adm</span>
                </div>
            `).join('');
        }

        // 4. Update Gender Demographics
        const gender = cat.gender || { Female: 0, Male: 0 };
        const totalGender = (gender.Female || 0) + (gender.Male || 0);
        const fPercent = totalGender > 0 ? Math.round((gender.Female / totalGender) * 100) : 50;
        const mPercent = totalGender > 0 ? (100 - fPercent) : 50;

        const fBar = document.getElementById('catGenderFemaleBar');
        const mBar = document.getElementById('catGenderMaleBar');
        if (fBar && mBar) {
            fBar.style.width = fPercent + '%';
            mBar.style.width = mPercent + '%';
        }
        const ratioText = document.getElementById('catGenderRatioText');
        if (ratioText) {
            ratioText.textContent = `${gender.Female || 0}F (${fPercent}%) : ${gender.Male || 0}M (${mPercent}%)`;
        }
        const fCount = document.getElementById('catFemaleCount');
        const mCount = document.getElementById('catMaleCount');
        if (fCount) fCount.textContent = `Female: ${gender.Female || 0} (${fPercent}%)`;
        if (mCount) mCount.textContent = `Male: ${gender.Male || 0} (${mPercent}%)`;

        // 5. Update Academic Divisions
        const divContainer = document.getElementById('catDivisionBadges');
        if (divContainer) {
            const divs = cat.divisions || {};
            const divList = [
                { key: 'Division 1', color: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' },
                { key: 'Division 2', color: 'bg-sky-500/20 text-sky-300 border-sky-500/40' },
                { key: 'Division 3', color: 'bg-amber-500/20 text-amber-300 border-amber-500/40' },
                { key: 'Division 4', color: 'bg-orange-500/20 text-orange-300 border-orange-500/40' },
                { key: 'Division U', color: 'bg-rose-500/20 text-rose-300 border-rose-500/40' }
            ];
            divContainer.innerHTML = divList.map(d => `
                <span class="px-2.5 py-1 rounded-lg border text-xs font-bold flex items-center gap-1.5 ${d.color} shadow-2xs">
                    <span>${d.key}:</span>
                    <strong class="font-mono text-white">${divs[d.key] || 0}</strong>
                </span>
            `).join('');
        }

        // 6. Re-render Charts with Year or Multi-Year Data
        renderDashboardCharts(year, cat);
    }

    function renderDashboardCharts(year, cat) {
        const isAll = (year === 'all');
        const classes = ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'];

        // Subtitles and badges
        const admBadge = document.getElementById('admittedGraphBadge');
        const admSub = document.getElementById('admittedGraphSubtitle');
        const enrBadge = document.getElementById('enrolledGraphBadge');
        const enrSub = document.getElementById('enrolledGraphSubtitle');
        const rejBadge = document.getElementById('rejectedGraphBadge');
        const rejSub = document.getElementById('rejectedGraphSubtitle');
        const acTitle = document.getElementById('academicGraphTitle');
        const acSub = document.getElementById('academicGraphSubtitle');

        let admLabels, admValues, enrLabels, enrValues, rejLabels, rejValues, acLabels, acValues;

        if (isAll) {
            admLabels = multiYearLabels;
            admValues = multiYearAdmitted;
            if (admBadge) admBadge.textContent = (multiYearAdmitted[multiYearAdmitted.length - 1] || 0) + ' Admitted (Latest)';
            if (admSub) admSub.textContent = 'Total offers extended vs. Years';

            enrLabels = multiYearLabels;
            enrValues = multiYearEnrolled;
            if (enrBadge) enrBadge.textContent = (multiYearEnrolled[multiYearEnrolled.length - 1] || 0) + ' Enrolled (Latest)';
            if (enrSub) enrSub.textContent = 'Physically reported & active vs. Years';

            rejLabels = multiYearLabels;
            rejValues = multiYearRejected;
            if (rejBadge) rejBadge.textContent = (multiYearRejected[multiYearRejected.length - 1] || 0) + ' Declined (Latest)';
            if (rejSub) rejSub.textContent = 'Declined submissions vs. Years';

            acLabels = multiYearLabels;
            acValues = multiYearAcademic;
            if (acTitle) acTitle.textContent = 'Academic Performance Trends Across Years';
            if (acSub) acSub.textContent = 'Mean UNEB Aggregates of Applicants (Lower score = Higher Distinction standing in Uganda)';
        } else {
            // Specific Year: Categorical Breakdown by Class
            const admByClass = cat.admitted_by_class || {};
            const enrByClass = cat.enrolled_by_class || {};
            const avgByClass = cat.avg_aggs_by_class || {};

            admLabels = classes;
            admValues = classes.map(c => admByClass[c] || 0);
            if (admBadge) admBadge.textContent = (cat.admitted || 0) + ' Admitted (' + year + ')';
            if (admSub) admSub.textContent = 'Offers extended by entry class in ' + year;

            enrLabels = classes;
            enrValues = classes.map(c => enrByClass[c] || 0);
            if (enrBadge) enrBadge.textContent = (cat.enrolled || 0) + ' Enrolled (' + year + ')';
            if (enrSub) enrSub.textContent = 'Physically reported by entry class in ' + year;

            // Chart 3: Categorical Status Breakdown in Selected Year
            rejLabels = ['Admitted', 'Enrolled', 'Pending', 'Rejected'];
            rejValues = [cat.admitted || 0, cat.enrolled || 0, cat.pending || 0, cat.rejected || 0];
            if (rejBadge) rejBadge.textContent = (cat.rejected || 0) + ' Rejected (' + year + ')';
            if (rejSub) rejSub.textContent = 'Application status breakdown in ' + year;

            // Chart 4: Academic Performance by Class
            acLabels = classes;
            acValues = classes.map(c => avgByClass[c] || 0);
            if (acTitle) acTitle.textContent = 'Class-by-Class Mean Aggregates (' + year + ')';
            if (acSub) acSub.textContent = 'Average entry aggregates per class level in ' + year + ' (Lower score = Higher Distinction standing)';
        }

        // 1. Admitted Chart (Emerald)
        const ctxAdmitted = document.getElementById('admittedChart');
        if (ctxAdmitted) {
            if (admittedChartInstance) admittedChartInstance.destroy();
            admittedChartInstance = new Chart(ctxAdmitted, {
                type: 'bar',
                data: {
                    labels: admLabels,
                    datasets: [{
                        label: isAll ? 'Admitted Students' : 'Admitted (' + year + ')',
                        data: admValues,
                        backgroundColor: 'rgba(16, 185, 129, 0.85)',
                        hoverBackgroundColor: 'rgba(5, 150, 105, 1)',
                        borderRadius: 6,
                        maxBarThickness: 32
                    }]
                },
                options: commonBarOptions
            });
        }

        // 2. Enrolled Chart (Purple)
        const ctxEnrolled = document.getElementById('enrolledChart');
        if (ctxEnrolled) {
            if (enrolledChartInstance) enrolledChartInstance.destroy();
            enrolledChartInstance = new Chart(ctxEnrolled, {
                type: 'bar',
                data: {
                    labels: enrLabels,
                    datasets: [{
                        label: isAll ? 'Enrolled Students' : 'Enrolled (' + year + ')',
                        data: enrValues,
                        backgroundColor: 'rgba(126, 34, 206, 0.85)',
                        hoverBackgroundColor: 'rgba(107, 33, 168, 1)',
                        borderRadius: 6,
                        maxBarThickness: 32
                    }]
                },
                options: commonBarOptions
            });
        }

        // 3. Rejected / Status Decisions Chart
        const ctxRejected = document.getElementById('rejectedChart');
        if (ctxRejected) {
            if (rejectedChartInstance) rejectedChartInstance.destroy();
            const colors = isAll 
                ? 'rgba(244, 63, 94, 0.85)' 
                : ['rgba(16, 185, 129, 0.85)', 'rgba(126, 34, 206, 0.85)', 'rgba(245, 158, 11, 0.85)', 'rgba(244, 63, 94, 0.85)'];
            rejectedChartInstance = new Chart(ctxRejected, {
                type: 'bar',
                data: {
                    labels: rejLabels,
                    datasets: [{
                        label: isAll ? 'Rejected Applications' : 'Candidates (' + year + ')',
                        data: rejValues,
                        backgroundColor: colors,
                        borderRadius: 6,
                        maxBarThickness: 32
                    }]
                },
                options: commonBarOptions
            });
        }

        // 4. Academic Performance Chart
        const ctxAcademic = document.getElementById('academicPerformanceChart');
        if (ctxAcademic) {
            if (academicChartInstance) academicChartInstance.destroy();
            academicChartInstance = new Chart(ctxAcademic, {
                type: isAll ? 'line' : 'bar',
                data: {
                    labels: acLabels,
                    datasets: [{
                        label: 'Mean Aggregates',
                        data: acValues,
                        borderColor: 'rgba(217, 119, 6, 1)',
                        backgroundColor: isAll ? 'rgba(217, 119, 6, 0.12)' : 'rgba(217, 119, 6, 0.85)',
                        fill: isAll,
                        tension: 0.3,
                        pointRadius: isAll ? 5 : 0,
                        pointHoverRadius: isAll ? 7 : 0,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: 'rgba(217, 119, 6, 1)',
                        pointBorderWidth: 2.5,
                        borderRadius: 6,
                        maxBarThickness: 32
                    }]
                },
                options: {
                    ...commonBarOptions,
                    scales: {
                        ...commonBarOptions.scales,
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: { size: 9.5 },
                                callback: function(val) { return val + ' Aggs'; }
                            },
                            grid: { color: 'rgba(226, 232, 240, 0.6)' }
                        }
                    }
                }
            });
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        switchGraphYear('all');
    });
    </script>
</body>
</html>
