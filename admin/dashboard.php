<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/auth.php';
require __DIR__ . '/../config/db.php';

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
        ['category' => 'Sciences', 'code' => 'BCM/ICT', 'name' => 'Biology, Chemistry, Mathematics & ICT'],
        ['category' => 'Sciences', 'code' => 'PEM/ICT', 'name' => 'Physics, Economics, Mathematics & ICT'],
        ['category' => 'Sciences', 'code' => 'MEG/ICT', 'name' => 'Mathematics, Economics, Geography & ICT'],
        ['category' => 'Sciences', 'code' => 'BCG/ICT', 'name' => 'Biology, Chemistry, Geography & ICT'],
        ['category' => 'Arts & Humanities', 'code' => 'HEG/ICT', 'name' => 'History, Economics, Geography & ICT'],
        ['category' => 'Arts & Humanities', 'code' => 'HEL/ICT', 'name' => 'History, Economics, Literature & ICT'],
        ['category' => 'Arts & Humanities', 'code' => 'HLD/ICT', 'name' => 'History, Literature, Divinity & ICT'],
        ['category' => 'Arts & Humanities', 'code' => 'DEG/ICT', 'name' => 'Divinity, Economics, Geography & ICT'],
        ['category' => 'Arts & Humanities', 'code' => 'HED/ICT', 'name' => 'History, Economics, Divinity & ICT'],
        ['category' => 'Arts & Humanities', 'code' => 'LEG/ICT', 'name' => 'Literature, Economics, Geography & ICT']
    ];
}

$where = [];
$params = [];

if ($classFilter === 'S1') {
    $where[] = "class_of_entry = 'S1'";
} elseif ($classFilter === 'S5') {
    $where[] = "class_of_entry = 'S5'";
} elseif ($classFilter === 'OTHER') {
    $where[] = "class_of_entry IN ('S2', 'S3', 'S4', 'S6')";
} elseif (in_array($classFilter, ['S2', 'S3', 'S4', 'S6'])) {
    $where[] = "class_of_entry = ?";
    $params[] = $classFilter;
}

if ($statusFilter !== 'ALL') {
    $where[] = "application_status = ?";
    $params[] = $statusFilter;
}

if ($yearFilter !== 'all') {
    $where[] = "academic_year = ?";
    $params[] = $yearFilter;
}

if ($search !== '') {
    $where[] = "(firstname LIKE ? OR surname LIKE ? OR index_number LIKE ? OR lin LIKE ? OR parent_name LIKE ? OR former_school LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Pagination: 20 rows per page
$perPage = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$countSql = "SELECT COUNT(*) FROM applications";
if (count($where) > 0) {
    $countSql .= " WHERE " . implode(" AND ", $where);
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$filteredCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($filteredCount / $perPage));
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = "SELECT * FROM applications";
if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
$paramIdx = 1;
foreach ($params as $p) {
    $stmt->bindValue($paramIdx++, $p);
}
$stmt->bindValue($paramIdx++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($paramIdx++, $offset, PDO::PARAM_INT);
$stmt->execute();
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pre-resolve validated student photo relative URL for frontend modal and table
foreach ($applications as &$appItem) {
    $appItem['photo_url'] = getStudentPhotoUrl($appItem['passport_photo'] ?? '', '../');
}
unset($appItem);

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
        ["code" => "PCM", "name" => "Physics, Chemistry, Mathematics (PCM)"],
        ["code" => "PCB", "name" => "Physics, Chemistry, Biology (PCB)"],
        ["code" => "BCM", "name" => "Biology, Chemistry, Mathematics (BCM)"],
        ["code" => "PEM", "name" => "Physics, Economics, Mathematics (PEM)"],
        ["code" => "MEG", "name" => "Mathematics, Economics, Geography (MEG)"],
        ["code" => "HEG", "name" => "History, Economics, Geography (HEG)"],
        ["code" => "HEL", "name" => "History, Economics, Literature (HEL)"],
        ["code" => "DEG", "name" => "Divinity, Economics, Geography (DEG)"],
        ["code" => "GEA", "name" => "Geography, Economics, Art (GEA)"],
        ["code" => "HED", "name" => "History, Economics, Divinity (HED)"]
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
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | <?= htmlspecialchars($school_short_name) ?></title>
<link rel="icon" type="image/png" href="<?= !empty($logo_source) ? htmlspecialchars($logo_source) : '../images/default_logo.png' ?>">
<!-- Tailwind CDN & Chart.js -->
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Font Awesome & Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= renderThemeConfig($pdo) ?>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-h-screen flex flex-col md:flex-row overflow-x-hidden">

    <!-- Mobile Drawer Overlay & Sidebar -->
    <div id="mobileSidebarBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden" onclick="toggleMobileSidebar()"></div>
    <aside id="mobileSidebar" class="fixed top-0 bottom-0 left-0 w-72 bg-slate-900 text-slate-100 flex flex-col z-50 transform -translate-x-full transition-transform duration-200 ease-in-out md:hidden shadow-2xl">
        <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="<?= $logo_source ?>" alt="Logo" class="w-9 h-9 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
                <div class="min-w-0">
                    <span class="block text-xs font-bold text-white tracking-tight uppercase truncate"><?= htmlspecialchars($school_short_name) ?></span>
                    <span class="block text-[9px] font-semibold text-gold-500 uppercase tracking-widest">Consolidated Portal</span>
                </div>
            </div>
            <button type="button" onclick="toggleMobileSidebar()" class="text-slate-400 hover:text-white p-1" aria-label="Close menu">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <nav class="flex-1 px-4 py-4 space-y-1.5 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1-S6)
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted Register
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-file-invoice text-lg text-gold-500"></i> Custom Reports
            </a>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-users text-lg"></i> Staff Users
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_roles') || hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="roles.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-shield-halved text-lg"></i> Roles & Access
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_view_logs')): ?>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-clock-rotate-left text-lg"></i> Activity Logs
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_settings') || hasPermission($pdo, $currentRole, 'can_manage_admission_policies')): ?>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-sliders text-lg text-gold-500"></i> <?= hasPermission($pdo, $currentRole, 'can_manage_settings') ? 'System Settings' : 'Policies & Fees' ?>
            </a>
            <?php endif; ?>
            <div class="pt-4 border-t border-slate-800 mt-4">
                <a href="<?= htmlspecialchars($school_website_url) ?>" target="_blank" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition">
                    <i class="fa-solid fa-globe text-gold-500"></i> Main School Website <i class="fa-solid fa-arrow-up-right-from-square text-[9px] ml-auto"></i>
                </a>
            </div>
        </nav>
        <div class="px-5 py-3 border-t border-slate-800 text-[11px] text-slate-500 text-center">
            Admissions Office · <?= htmlspecialchars(getAcademicYear($pdo)) ?>
        </div>
    </aside>

    <!-- Desktop Sidebar (Cruip dark design style) -->
    <aside class="hidden md:flex w-64 bg-slate-900 text-slate-100 flex-col shrink-0 min-h-screen">
        <!-- Brand Header -->
        <div class="px-6 py-5 border-b border-slate-800 flex items-center gap-3">
            <img src="<?= $logo_source ?>" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
            <div>
                <span class="block text-sm font-bold text-white tracking-tight leading-tight uppercase"><?= htmlspecialchars($school_short_name) ?></span>
                <span class="block text-xs font-semibold text-gold-500 uppercase tracking-widest text-[9px]">Consolidated Portal</span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 px-4 py-6 space-y-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1-S6)
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted Register
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-file-invoice text-lg text-gold-500"></i> Custom Reports
            </a>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-users text-lg"></i> Staff Users
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_roles') || hasPermission($pdo, $currentRole, 'can_manage_users')): ?>
            <a href="roles.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-shield-halved text-lg"></i> Roles & Access
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_view_logs')): ?>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-clock-rotate-left text-lg"></i> Activity Logs
            </a>
            <?php endif; ?>
            <?php if (hasPermission($pdo, $currentRole, 'can_manage_settings') || hasPermission($pdo, $currentRole, 'can_manage_admission_policies')): ?>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-sliders text-lg text-gold-500"></i> <?= hasPermission($pdo, $currentRole, 'can_manage_settings') ? 'System Settings' : 'Policies & Fees' ?>
            </a>
            <?php endif; ?>

            <div class="pt-4 border-t border-slate-800 mt-4">
                <a href="<?= htmlspecialchars($school_website_url) ?>" target="_blank" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition">
                    <i class="fa-solid fa-globe text-gold-500"></i> Main School Website <i class="fa-solid fa-arrow-up-right-from-square text-[9px] ml-auto"></i>
                </a>
            </div>
        </nav>

        <div class="px-6 py-4 border-t border-slate-800 text-xs text-slate-500 text-center">
            Admissions Office · <?= htmlspecialchars(getAcademicYear($pdo)) ?>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        
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
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
                <!-- Total -->
                <div class="bg-white p-3 sm:p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider">Total</span>
                        <span class="block text-xl sm:text-2xl font-extrabold text-slate-800 mt-0.5"><?= $totalApps ?></span>
                    </div>
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center text-base shrink-0">
                        <i class="fa-solid fa-folder-open"></i>
                    </div>
                </div>
                <!-- Pending -->
                <div class="bg-white p-3 sm:p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider">Pending</span>
                        <span class="block text-xl sm:text-2xl font-extrabold text-slate-800 mt-0.5"><?= $pendingCount ?></span>
                    </div>
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-base shrink-0">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                </div>
                <!-- Admitted -->
                <div class="bg-white p-3 sm:p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider">Admitted</span>
                        <span class="block text-xl sm:text-2xl font-extrabold text-slate-800 mt-0.5"><?= $admittedCount ?></span>
                    </div>
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-base shrink-0">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
                <!-- Reported (Enrolled & Archived) -->
                <div class="bg-white p-3 sm:p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] sm:text-xs font-bold text-purple-700 uppercase tracking-wider">Enrolled (Reported)</span>
                        <span class="block text-xl sm:text-2xl font-extrabold text-purple-900 mt-0.5"><?= $reportedCount ?></span>
                    </div>
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-purple-50 text-purple-700 flex items-center justify-center text-base shrink-0">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </div>
                <!-- Rejected -->
                <div class="bg-white p-3 sm:p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider">Rejected</span>
                        <span class="block text-xl sm:text-2xl font-extrabold text-slate-800 mt-0.5"><?= $rejectedCount ?></span>
                    </div>
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-base shrink-0">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>
                </div>
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
                    <!-- KPI Counter Chips -->
                    <div class="flex flex-wrap items-center gap-2 text-xs">
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

            <!-- Filters Area -->
            <form id="dashboardFilterForm" method="GET" class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Filter by Entry Class</label>
                    <select name="class" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-brand-700 bg-white">
                        <option value="ALL" <?= $classFilter === 'ALL' ? 'selected' : '' ?>>All Classes (S1-S6)</option>
                        <option value="S1" <?= $classFilter === 'S1' ? 'selected' : '' ?>>Senior One (S1)</option>
                        <option value="S2" <?= $classFilter === 'S2' ? 'selected' : '' ?>>Senior Two (S2)</option>
                        <option value="S3" <?= $classFilter === 'S3' ? 'selected' : '' ?>>Senior Three (S3)</option>
                        <option value="S4" <?= $classFilter === 'S4' ? 'selected' : '' ?>>Senior Four (S4)</option>
                        <option value="S5" <?= $classFilter === 'S5' ? 'selected' : '' ?>>Senior Five (S5)</option>
                        <option value="S6" <?= $classFilter === 'S6' ? 'selected' : '' ?>>Senior Six (S6)</option>
                        <option value="OTHER" <?= $classFilter === 'OTHER' ? 'selected' : '' ?>>Other / Transfers (S2, S3, S4, S6)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Status</label>
                    <select name="status" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-brand-700 bg-white">
                        <option value="ALL" <?= $statusFilter === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                        <option value="admitted" <?= $statusFilter === 'admitted' ? 'selected' : '' ?>>Admitted</option>
                        <option value="reported" <?= $statusFilter === 'reported' ? 'selected' : '' ?>>Reported & Enrolled (Archived)</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Academic Year</label>
                    <select name="academic_year" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-brand-700 bg-white">
                        <option value="all" <?= $yearFilter === 'all' ? 'selected' : '' ?>>All Academic Years</option>
                        <?php foreach ($availableYears as $ay): ?>
                            <option value="<?= htmlspecialchars($ay) ?>" <?= $yearFilter === $ay ? 'selected' : '' ?>><?= htmlspecialchars($ay) ?> Academic Year</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex gap-2">
                    <div class="flex-1 relative">
                        <input type="text" name="search" id="dashboardSearchInput" value="<?= htmlspecialchars($search) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-700 focus:outline-none focus:border-brand-700" placeholder="Auto-search...">
                    </div>
                    <button type="submit" class="px-3.5 py-2 bg-brand-700 hover:bg-brand-800 text-white rounded-xl text-xs sm:text-sm font-bold shadow-sm transition">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                    <a href="dashboard.php" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs sm:text-sm font-bold shadow-sm transition flex items-center">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>

            <!-- Table Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <div>
                        <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">Candidate Registry</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Showing 20 applicants per page</p>
                    </div>
                    <span class="text-xs font-bold text-brand-700 bg-brand-50 border border-brand-200 px-2.5 py-1 rounded">Filtered Total: <?= $filteredCount ?></span>
                </div>

                <div class="overflow-x-auto">
                    <?php
                    $applicationsById = [];
                    foreach ($applications as $a) {
                        $applicationsById[$a['id']] = $a;
                    }
                    ?>
                    <table class="w-full text-left border-collapse text-xs table-auto">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                                <th class="px-2 py-2 text-center w-8">#</th>
                                <th class="px-1.5 py-2 text-center w-10">Photo</th>
                                <th class="px-2 py-2">Student Full Name</th>
                                <th class="px-1.5 py-2 text-center w-12">Class</th>
                                <th class="px-2 py-2 text-center">Index / ID</th>
                                <th class="px-1.5 py-2 text-center w-14 whitespace-nowrap">Aggs</th>
                                <th class="px-2 py-2">Academic details</th>
                                <th class="px-2 py-2">Parent / Contact</th>
                                <th class="px-1.5 py-2 text-center w-16">Record</th>
                                <th class="px-1.5 py-2 text-center w-20">Status</th>
                                <th class="px-2 py-2 text-center whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($applications as $app): 
                                $status = strtolower($app['application_status'] ?? 'pending');
                            ?>
                            <tr class="hover:bg-slate-50 transition duration-150">
                                <td class="px-2 py-2 text-center font-bold text-slate-400 font-mono"><?= $app['id'] ?></td>
                                <td class="px-1.5 py-2 text-center">
                                    <?php 
                                    $photoUrl = getStudentPhotoUrl($app['passport_photo'] ?? '', '../');
                                    if(!empty($photoUrl)): 
                                    ?>
                                        <a href="download_photo.php?id=<?= $app['id'] ?>" title="Download Student Photo" class="inline-block relative group">
                                            <img src="<?= htmlspecialchars($photoUrl) ?>" class="w-8 h-8 object-cover rounded-full border border-slate-200 mx-auto shadow-sm group-hover:ring-2 group-hover:ring-brand-500 transition" alt="Student Photo">
                                            <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full bg-brand-700 text-white text-[8px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition shadow"><i class="fa-solid fa-arrow-down"></i></span>
                                        </a>
                                    <?php else: ?>
                                        <span class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-sm mx-auto"><i class="fa-solid fa-user-ninja"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 py-2">
                                    <span class="block font-bold text-slate-800 leading-tight"><?= htmlspecialchars($app['firstname'].' '.$app['surname'].' '.$app['othernames']) ?></span>
                                    <span class="block text-[10px] text-slate-400 uppercase mt-0.5"><?= htmlspecialchars($app['sex'].' · Age: '.$app['age']) ?></span>
                                </td>
                                <td class="px-1.5 py-2 text-center font-bold text-slate-700"><?= htmlspecialchars($app['class_of_entry']) ?></td>
                                <td class="px-2 py-2 text-center">
                                    <?php if(!empty($app['index_number'])): ?>
                                        <span class="inline-flex items-center gap-1 group cursor-pointer text-slate-700 hover:text-brand-700 font-mono text-[11px] transition" onclick="copyToClipboard('<?= htmlspecialchars(addslashes($app['index_number'])) ?>', this)" title="Click to copy Index No">
                                            <span><?= htmlspecialchars($app['index_number']) ?></span>
                                            <i class="fa-regular fa-copy text-[9px] text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 font-mono text-[11px]">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-1.5 py-2 text-center font-bold text-brand-700 whitespace-nowrap">
                                    <?php if(!empty($app['division'])): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-brand-50 text-brand-800 border border-brand-200 block"><?= htmlspecialchars($app['division']) ?></span>
                                        <span class="block text-[9px] text-slate-400 font-mono mt-0.5">Agg: <?= htmlspecialchars($app['aggregates'] ?? 'N/A') ?></span>
                                    <?php else: ?>
                                        <span class="block font-black text-slate-800 text-xs"><?= htmlspecialchars($app['aggregates'] ?? 'N/A') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 py-2 max-w-[200px]">
                                    <span class="block text-xs font-bold text-slate-700 truncate" title="<?= htmlspecialchars($app['former_school']) ?>"><?= htmlspecialchars($app['former_school']) ?></span>
                                    <?php if($app['class_of_entry'] === 'S1'): ?>
                                        <span class="block text-[10px] text-slate-400 mt-0.5">Primary Leaving Examination (PLE)</span>
                                    <?php elseif($app['class_of_entry'] === 'S5' || $app['class_of_entry'] === 'S6'): ?>
                                        <span class="block text-[10.5px] text-gold-700 font-bold mt-0.5">
                                            <i class="fa-solid fa-graduation-cap text-gold-600"></i> <?= htmlspecialchars($app['combination']) ?>
                                        </span>
                                        <?php if(!empty($app['uce_project_work'])): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] text-slate-500 font-semibold mt-0.5 whitespace-nowrap">
                                                <span class="px-1.5 py-0.2 rounded bg-slate-100 font-mono font-bold text-slate-700">Project: <?= htmlspecialchars($app['uce_project_work']) ?></span>
                                                <?= ($app['class_of_entry'] === 'S6') ? '<span class="text-emerald-700 font-bold">(S6 Transfer)</span>' : '' ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if(!empty($app['s6_former_school'])): ?>
                                            <span class="block text-[9.5px] text-slate-400 truncate max-w-xs" title="Former S5: <?= htmlspecialchars($app['s6_former_school']) ?>">S5: <?= htmlspecialchars($app['s6_former_school']) ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($app['uce_subjects_json'])): ?>
                                            <button type="button" 
                                                    onclick='openNlscModal(<?= json_encode([
                                                        "name" => trim($app['firstname']." ".$app['surname']." ".($app['othernames'] ?? "")),
                                                        "class" => $app['class_of_entry'],
                                                        "index" => $app['index_number'] ?? "N/A",
                                                        "former_school" => $app['former_school'] ?? "N/A",
                                                        "combination" => $app['combination'] ?? "N/A",
                                                        "division" => $app['division'] ?? "N/A",
                                                        "project_work" => $app['uce_project_work'] ?? "N/A",
                                                        "result_slip" => $app['result_slip'] ?? "",
                                                        "s6_former_school" => $app['s6_former_school'] ?? "",
                                                        "s6_report_file" => $app['s6_report_file'] ?? "",
                                                        "nlsc" => json_decode($app['uce_subjects_json'], true)
                                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' 
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 mt-1 rounded bg-brand-50 text-brand-700 hover:bg-brand-100 text-[9.5px] font-bold border border-brand-200 transition cursor-pointer whitespace-nowrap">
                                                <i class="fa-solid fa-table-list"></i> View NLSC Grades
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="block text-[10px] text-brand-700 font-semibold mt-0.5">Last Class: <?= htmlspecialchars($app['division']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 py-2 max-w-[200px]">
                                    <span class="block text-xs font-bold text-slate-800 leading-tight"><?= htmlspecialchars($app['parent_name']) ?></span>
                                    <?php if(!empty($app['parent_phone'])): ?>
                                        <span class="inline-flex items-center gap-1 group cursor-pointer text-[11px] text-slate-600 hover:text-brand-700 mt-0.5 transition" onclick="copyToClipboard('<?= htmlspecialchars(addslashes($app['parent_phone'])) ?>', this)" title="Click to copy Phone">
                                            <i class="fa-solid fa-phone text-[9px] text-slate-400"></i>
                                            <span><?= htmlspecialchars($app['parent_phone']) ?></span>
                                            <i class="fa-regular fa-copy text-[9px] text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </span>
                                    <?php endif; ?>
                                    <?php if(!empty($app['parent_email'])): ?>
                                        <span class="flex items-center gap-1 group cursor-pointer text-[11px] text-slate-500 hover:text-brand-700 mt-0.5 truncate max-w-[190px] transition" onclick="copyToClipboard('<?= htmlspecialchars(addslashes($app['parent_email'])) ?>', this)" title="Click to copy Email: <?= htmlspecialchars($app['parent_email']) ?>">
                                            <i class="fa-solid fa-envelope text-[9px] text-slate-400 shrink-0"></i>
                                            <span class="truncate"><?= htmlspecialchars($app['parent_email']) ?></span>
                                            <i class="fa-regular fa-copy text-[9px] text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity shrink-0"></i>
                                        </span>
                                    <?php endif; ?>
                                    <?php if(!empty($app['parent_nin'])): ?>
                                        <span class="inline-flex items-center gap-1 group cursor-pointer text-[9.5px] font-mono text-slate-400 hover:text-brand-700 mt-0.5 transition" onclick="copyToClipboard('<?= htmlspecialchars(addslashes($app['parent_nin'])) ?>', this)" title="Click to copy NIN">
                                            <span>NIN: <?= htmlspecialchars($app['parent_nin']) ?></span>
                                            <i class="fa-regular fa-copy text-[8px] text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </span>
                                    <?php endif; ?>
                                    <?php if(!empty($app['parent_id_file'])): ?>
                                        <a href="../<?= htmlspecialchars(ltrim($app['parent_id_file'], './')) ?>" target="_blank" class="inline-flex items-center gap-1 text-[9px] font-bold text-brand-700 hover:text-brand-900 mt-0.5">
                                             <i class="fa-solid fa-id-card"></i> View ID
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="px-1.5 py-2 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <?php if(!empty($app['result_slip']) && file_exists('../' . $app['result_slip'])): ?>
                                            <a href="../<?= $app['result_slip'] ?>" target="_blank" class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-sky-50 text-sky-700 hover:bg-sky-100 rounded text-[10.5px] font-bold transition duration-150">
                                                <i class="fa-solid fa-file-pdf"></i> <?= ($app['class_of_entry'] === 'S1') ? 'PLE Slip' : 'UCE Slip' ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 font-bold">N/A</span>
                                        <?php endif; ?>

                                        <?php if(!empty($app['s6_report_file']) && file_exists('../' . $app['s6_report_file'])): ?>
                                            <a href="../<?= $app['s6_report_file'] ?>" target="_blank" class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 rounded text-[10px] font-bold transition duration-150 border border-emerald-200">
                                                <i class="fa-solid fa-file-lines"></i> S5 Report
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-1.5 py-2 text-center">
                                    <?php 
                                    $isAuto = (!empty($app['is_auto_admitted']) && $app['is_auto_admitted'] == 1) || (stripos($app['admitted_by'] ?? '', 'AI') !== false) || (stripos($app['admitted_by'] ?? '', 'Auto') !== false);
                                    $reviewSt = $app['auto_admit_review_status'] ?? ($isAuto ? 'needs_review' : null);
                                    ?>
                                    <?php if($status=='reported'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-purple-50 text-purple-800 border border-purple-200"><i class="fa-solid fa-user-check mr-1 text-[8.5px]"></i> Reported</span>
                                    <?php elseif($status=='admitted'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">Admitted</span>
                                        <?php if($isAuto && $reviewSt === 'needs_review'): ?>
                                            <a href="review_auto_admit.php?id=<?= $app['id'] ?>" onclick="return confirm('Confirm review of system auto-admitted application for <?= htmlspecialchars(addslashes($app['firstname'].' '.$app['surname'])) ?>?')" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-300 text-[8.5px] font-black uppercase tracking-wider mt-0.5 hover:bg-amber-100 transition shadow-2xs block whitespace-nowrap" title="System Auto-Admitted: Click to mark as Reviewed">
                                                <i class="fa-solid fa-robot text-amber-600"></i> Needs Review
                                            </a>
                                        <?php elseif($isAuto && $reviewSt === 'reviewed'): ?>
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-sky-50 text-sky-800 border border-sky-200 text-[8.5px] font-bold mt-0.5 block whitespace-nowrap" title="Auto-admit reviewed by <?= htmlspecialchars($app['reviewed_by'] ?? 'Admin') ?>">
                                                <i class="fa-solid fa-check-double text-sky-600"></i> Reviewed
                                            </span>
                                        <?php endif; ?>
                                    <?php elseif($status=='rejected'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-800 border border-rose-200" title="<?= htmlspecialchars($app['rejection_reason'] ?? 'Rejected') ?>">Rejected</span>
                                        <?php if(!empty($app['rejection_reason'])): ?>
                                            <span class="block text-[9px] text-rose-600 font-medium italic mt-0.5 max-w-[110px] mx-auto truncate" title="<?= htmlspecialchars($app['rejection_reason']) ?>">
                                                <?= htmlspecialchars($app['rejection_reason']) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 py-2 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center justify-center gap-1.5 flex-nowrap whitespace-nowrap">
                                        <button type="button" 
                                                onclick="openAppModalById(<?= $app['id'] ?>)" 
                                                class="inline-flex items-center justify-center px-2 py-1 h-6 text-[10.5px] font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded-md transition duration-150 shadow-2xs cursor-pointer" title="View Complete Application Details">
                                            <i class="fa-solid fa-eye text-brand-700 mr-1 text-[9px]"></i> Details
                                        </button>

                                        <?php if(hasPermission($pdo, $currentRole, 'can_take_application_actions')): ?>
                                            <?php if($status=='pending'): ?>
                                                <?php if(hasPermission($pdo, $currentRole, 'can_admit_students')): ?>
                                                    <a href="admit.php?id=<?= $app['id'] ?>" class="inline-flex items-center justify-center px-2 py-1 h-6 text-[10.5px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-md transition duration-150 shadow-2xs" title="Admit Candidate">
                                                        <i class="fa-solid fa-check mr-1 text-[9px]"></i> Admit
                                                    </a>
                                                <?php endif; ?>
                                                <?php if(hasPermission($pdo, $currentRole, 'can_reject_students')): ?>
                                                    <button type="button" 
                                                            onclick="openRejectModal(<?= $app['id'] ?>, '<?= htmlspecialchars(addslashes(trim($app['firstname'].' '.$app['surname'])), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($app['class_of_entry']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($app['parent_email'] ?? ''), ENT_QUOTES) ?>')" 
                                                            class="inline-flex items-center justify-center px-2 py-1 h-6 text-[10.5px] font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-md transition duration-150 shadow-2xs cursor-pointer" title="Reject Candidate">
                                                        <i class="fa-solid fa-xmark mr-1 text-[9px]"></i> Reject
                                                    </button>
                                                <?php endif; ?>
                                            <?php elseif($status=='admitted'): ?>
                                                <a href="admission_offer.php?id=<?= $app['id'] ?>" target="_blank" class="inline-flex items-center justify-center px-2 py-1 h-6 text-[10.5px] font-bold text-white bg-sky-600 hover:bg-sky-700 rounded-md transition duration-150 shadow-2xs" title="Print Official Admission Offer">
                                                    <i class="fa-solid fa-file-pdf mr-1 text-[9px]"></i> Offer
                                                </a>
                                                <a href="mark_reported.php?id=<?= $app['id'] ?>" 
                                                   onclick="return confirm('Confirm that <?= htmlspecialchars(addslashes(trim($app['firstname'].' '.$app['surname'])), ENT_QUOTES) ?> has physically reported and enrolled at school? This will archive their admission.')" 
                                                   class="inline-flex items-center justify-center px-2 py-1 h-6 text-[10.5px] font-bold text-white bg-purple-700 hover:bg-purple-800 rounded-md transition duration-150 shadow-2xs" title="Mark as Reported & Enrolled">
                                                    <i class="fa-solid fa-user-check mr-1 text-[8.5px]"></i> Reported
                                                </a>
                                                <?php if(hasPermission($pdo, $currentRole, 'can_manage_settings') || $_SESSION['admin_role'] === 'super_admin'): ?>
                                                    <a href="reverse.php?id=<?= $app['id'] ?>" class="inline-flex items-center justify-center px-1.5 py-1 h-6 text-[10px] font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-md transition duration-150 shadow-2xs" title="Reverse Status">
                                                        Reverse
                                                    </a>
                                                <?php endif; ?>
                                            <?php elseif($status=='reported'): ?>
                                                <a href="admission_offer.php?id=<?= $app['id'] ?>" target="_blank" class="inline-flex items-center justify-center px-2 py-1 h-6 text-[10.5px] font-bold text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-md transition duration-150 shadow-2xs" title="View Official Admission Offer">
                                                    <i class="fa-solid fa-file-pdf mr-1 text-[9px]"></i> Offer
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (count($applications) === 0): ?>
                            <tr>
                                <td colspan="11" class="px-6 py-12 text-center text-slate-400 font-semibold">
                                    <i class="fa-solid fa-folder-open text-3xl block mb-2 text-slate-300"></i>
                                    No application entries found.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 20 Rows Per Page Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
                    <span class="text-xs text-slate-500">
                        Showing <span class="font-bold text-slate-700"><?= min($offset + 1, $filteredCount) ?></span> to <span class="font-bold text-slate-700"><?= min($offset + $perPage, $filteredCount) ?></span> of <span class="font-bold text-slate-700"><?= $filteredCount ?></span> applications (20 per page)
                    </span>
                    <div class="flex items-center gap-1.5">
                        <?php 
                        $queryBase = 'dashboard.php?class=' . urlencode($classFilter) . '&search=' . urlencode($search);
                        ?>
                        <?php if ($page > 1): ?>
                            <a href="<?= $queryBase ?>&page=<?= $page - 1 ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
                                <i class="fa-solid fa-chevron-left me-1"></i> Prev
                            </a>
                        <?php else: ?>
                            <span class="px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-xs font-bold text-slate-400 cursor-not-allowed">
                                <i class="fa-solid fa-chevron-left me-1"></i> Prev
                            </span>
                        <?php endif; ?>

                        <span class="px-3 py-1.5 text-xs font-bold text-brand-700 bg-brand-50 border border-brand-200 rounded-lg">
                            Page <?= $page ?> of <?= $totalPages ?>
                        </span>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?= $queryBase ?>&page=<?= $page + 1 ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
                                Next <i class="fa-solid fa-chevron-right ms-1"></i>
                            </a>
                        <?php else: ?>
                            <span class="px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-xs font-bold text-slate-400 cursor-not-allowed">
                                Next <i class="fa-solid fa-chevron-right ms-1"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- Reject Application with Reason Modal -->
    <div id="rejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
            <div class="px-6 py-4 bg-rose-50 border-b border-rose-100 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-rose-900 uppercase tracking-tight">Reject Admission Application</h3>
                        <p class="text-[11px] text-rose-700">Official rejection notice with reason will be emailed to applicant</p>
                    </div>
                </div>
                <button type="button" onclick="closeRejectModal()" class="text-slate-400 hover:text-slate-600 p-1">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>

            <form method="POST" action="reject.php" class="p-6 space-y-4">
                <input type="hidden" name="id" id="rejectApplicantId" value="">

                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500 font-semibold">Candidate:</span>
                        <span id="rejectCandidateName" class="font-bold text-slate-800"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 font-semibold">Class Applied:</span>
                        <span id="rejectCandidateClass" class="font-bold text-brand-700"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 font-semibold">Email Notification To:</span>
                        <span id="rejectCandidateEmail" class="font-mono font-semibold text-slate-700"></span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                        Select Standard Reason (Presets)
                    </label>
                    <select id="presetReasons" onchange="applyPresetReason(this.value)" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs bg-white focus:outline-none focus:border-rose-600">
                        <option value="Did not satisfy institutional admission cut-off pass mark / aggregates requirement.">Cut-off Aggregates Requirement Not Satisfied</option>
                        <option value="Requested subject combination requirements not met based on UNEB performance.">Subject Combination Ineligible</option>
                        <option value="Incomplete or unverifiable academic result documentation provided during submission.">Incomplete Academic Documentation</option>
                        <option value="Intake enrollment capacity has reached maximum capacity for this academic class.">Class Enrollment Capacity Reached</option>
                        <option value="Late submission received past the official admissions intake deadline.">Intake Deadline Passed</option>
                        <option value="custom">Custom Reason (Type below)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                        Official Rejection Reason <span class="text-rose-600">*</span>
                    </label>
                    <textarea name="rejection_reason" id="rejectionReasonText" rows="3" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-rose-600 transition" placeholder="State clear, official reason for rejecting this application..."></textarea>
                    <p class="text-[10px] text-slate-400 mt-1">This explanation will be prominently featured in the official email notification sent to the applicant.</p>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeRejectModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 transition shadow-sm flex items-center gap-1.5">
                        <i class="fa-solid fa-paper-plane text-[10px]"></i> Confirm & Send Rejection Email
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View UCE NLSC Subject Competencies Modal -->
    <div id="nlscModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-gradient-to-r from-brand-900 to-brand-800 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center text-sm font-bold border border-white/20">
                        <i class="fa-solid fa-graduation-cap text-gold-400"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wide">UCE NLSC Candidate Result Profile</h3>
                        <p class="text-[11px] text-brand-200">Uganda National Examinations Board (New Lower Secondary Curriculum)</p>
                    </div>
                </div>
                <button type="button" onclick="closeNlscModal()" class="text-white/70 hover:text-white p-1 cursor-pointer">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Modal Content -->
            <div class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                <!-- Candidate Info Card -->
                <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2 text-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                            <span class="text-slate-400 font-semibold block text-[10px] uppercase">Student Full Name:</span>
                            <span id="nlscStudentName" class="font-bold text-slate-800 text-sm"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-semibold block text-[10px] uppercase">Class & Desired Combination:</span>
                            <span id="nlscClassComb" class="font-bold text-brand-700 text-sm"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-semibold block text-[10px] uppercase">UNEB UCE Index Number:</span>
                            <span id="nlscIndexNumber" class="font-mono font-bold text-slate-700"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-semibold block text-[10px] uppercase">Former O-Level School:</span>
                            <span id="nlscFormerSchool" class="font-semibold text-slate-700"></span>
                        </div>
                    </div>

                    <!-- Certification & CA Project Highlights -->
                    <div class="pt-2 border-t border-slate-200 flex flex-wrap items-center gap-3">
                        <div class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-bold text-slate-800">
                            Result: <span id="nlscResultLevel" class="text-brand-700 font-black"></span>
                        </div>
                        <div class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-bold text-slate-800">
                            Project Work: <span id="nlscProjectWork" class="text-gold-700 font-black"></span>
                        </div>
                        <div class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-bold text-slate-800">
                            Total Competency: <span id="nlscCompetencyScore" class="text-emerald-700 font-black font-mono"></span> pts
                        </div>
                        <div id="nlscResultSlipLink" class="ms-auto"></div>
                    </div>
                </div>

                <!-- S6 Transfer Specific Block (if present) -->
                <div id="nlscS6TransferCard" class="hidden p-3.5 bg-emerald-50/70 border border-emerald-200 rounded-xl space-y-1.5 text-xs">
                    <span class="font-bold text-emerald-950 uppercase text-[11px] block"><i class="fa-solid fa-arrow-right-arrow-left"></i> Senior Six Transfer Background</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11.5px]">
                        <div><strong class="text-slate-500">Former S5 School:</strong> <span id="nlscS5School" class="font-semibold text-slate-800"></span></div>
                        <div><strong class="text-slate-500">S5 Passes:</strong> <span id="nlscS5Passes" class="font-semibold text-slate-800"></span></div>
                    </div>
                    <div class="text-[11.5px]"><strong class="text-slate-500">Reason:</strong> <span id="nlscS5Reason" class="text-slate-700 italic"></span></div>
                    <div id="nlscS5ReportLink" class="pt-1"></div>
                </div>

                <!-- Subject Competency Table -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-800 uppercase tracking-wide">Subject Achievement Levels & Descriptors</span>
                        <div id="nlscGradeSummaryBadges" class="flex items-center gap-1.5"></div>
                    </div>
                    <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-100 text-slate-600 font-bold uppercase text-[10px] border-b border-slate-200">
                                <tr>
                                    <th class="px-3.5 py-2">Subject Name</th>
                                    <th class="px-3.5 py-2 text-center">Letter Grade</th>
                                    <th class="px-3.5 py-2 text-center">Points</th>
                                    <th class="px-3.5 py-2">Achievement Level Descriptor</th>
                                </tr>
                            </thead>
                            <tbody id="nlscSubjectsTbody" class="divide-y divide-slate-100 bg-white">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-end">
                <button type="button" onclick="closeNlscModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-100 transition shadow-2xs cursor-pointer">
                    Close Window
                </button>
            </div>
        </div>
    </div>

    <!-- View Complete Application Details Modal -->
    <div id="fullApplicationModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[92vh] flex flex-col overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-gradient-to-r from-brand-900 via-brand-800 to-slate-900 text-white flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-lg border border-white/20 text-gold-400">
                        <i class="fa-solid fa-id-card-clip"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 id="modalStudentName" class="text-base font-extrabold uppercase tracking-tight text-white">Application Details</h3>
                            <span id="modalStatusBadge" class="px-2.5 py-0.5 rounded-full text-[11px] font-black uppercase"></span>
                        </div>
                        <p class="text-xs text-brand-200 font-medium">Cohort Intake <span id="modalAcademicYear" class="font-bold text-white"></span> · Application ID: <span id="modalAppId" class="font-mono text-gold-400"></span></p>
                    </div>
                </div>
                <button type="button" onclick="closeFullAppModal()" class="text-white/70 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <!-- Modal Scrollable Content -->
            <div class="p-6 overflow-y-auto space-y-6 text-xs text-slate-700">

                <!-- Rejection Banner (Conditional) -->
                <div id="modalRejectionBanner" class="hidden p-3.5 bg-rose-50 border-l-4 border-rose-500 rounded-r-xl space-y-1">
                    <div class="flex items-center gap-2 text-rose-800 font-bold text-xs uppercase">
                        <i class="fa-solid fa-circle-xmark text-sm"></i> Official Rejection Notice
                    </div>
                    <p id="modalRejectionReason" class="text-rose-700 text-xs font-medium italic"></p>
                </div>

                <!-- Section 1: Candidate Bio-Data & Residential Address -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-5 bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <div class="md:col-span-3 flex flex-col items-center justify-center text-center p-2 bg-white rounded-xl border border-slate-200 shadow-2xs">
                        <div class="w-24 h-24 rounded-full overflow-hidden border-2 border-brand-700 bg-slate-100 flex items-center justify-center mb-2 shadow-inner">
                            <img id="modalStudentPhoto" src="" alt="Candidate Photo" class="w-full h-full object-cover hidden">
                            <div id="modalStudentPhotoPlaceholder" class="w-full h-full flex flex-col items-center justify-center text-slate-400">
                                <i class="fa-solid fa-user text-3xl"></i>
                                <span class="text-[9px] mt-1 font-semibold uppercase">No Photo</span>
                            </div>
                        </div>
                        <span id="modalFullName" class="font-extrabold text-slate-800 text-sm leading-tight text-center"></span>
                        <span id="modalClassBadge" class="mt-1 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-brand-50 text-brand-700 border border-brand-200"></span>
                        <div id="modalPhotoDownloadWrapper" class="mt-2 hidden">
                            <a id="modalPhotoDownloadLink" href="#" class="inline-flex items-center gap-1 text-[10.5px] font-bold text-brand-700 hover:text-brand-900 bg-brand-50 hover:bg-brand-100 px-2.5 py-1 rounded-lg border border-brand-200 transition">
                                <i class="fa-solid fa-download"></i> Download Photo
                            </a>
                        </div>
                    </div>

                    <div class="md:col-span-9 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Gender / Sex</span>
                            <span id="modalSex" class="font-bold text-slate-800 text-xs"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Date of Birth & Age</span>
                            <span id="modalDobAge" class="font-bold text-slate-800 text-xs"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Religious Affiliation</span>
                            <span id="modalReligion" class="font-bold text-slate-800 text-xs"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80 sm:col-span-2 lg:col-span-3">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Learner Identification Number (LIN)</span>
                            <span id="modalLin" class="font-mono font-black text-brand-700 text-xs"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80 sm:col-span-2 lg:col-span-3">
                            <span class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Residential Physical Address (4-Field Verified)</span>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px]">
                                <div><span class="text-slate-400">Village:</span> <strong id="modalVillage" class="text-slate-700 block"></strong></div>
                                <div><span class="text-slate-400">Parish:</span> <strong id="modalParish" class="text-slate-700 block"></strong></div>
                                <div><span class="text-slate-400">Sub-county:</span> <strong id="modalSubcounty" class="text-slate-700 block"></strong></div>
                                <div><span class="text-slate-400">District:</span> <strong id="modalDistrict" class="text-slate-700 block"></strong></div>
                            </div>
                            <div class="mt-1.5 pt-1.5 border-t border-slate-100 text-[11px] text-slate-500">
                                <strong>Consolidated:</strong> <span id="modalConsolidatedAddress" class="font-medium text-slate-700"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Parent / Guardian & Emergency Contact -->
                <div class="space-y-2">
                    <h4 class="font-bold text-slate-800 uppercase text-xs tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-users text-brand-700"></i> Parent / Guardian Information
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Full Name</span>
                            <span id="modalParentName" class="font-bold text-slate-800 text-xs"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Relationship</span>
                            <span id="modalParentRelationship" class="font-bold text-slate-800 text-xs"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Telephone Number</span>
                            <span id="modalParentPhone" class="font-bold text-slate-800 text-xs"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Email Address</span>
                            <span id="modalParentEmail" class="font-medium text-slate-700 text-xs truncate block"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">National ID (NIN)</span>
                            <span id="modalParentNin" class="font-mono font-bold text-slate-800 text-xs"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80 sm:col-span-2 lg:col-span-3">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Parent National ID Attachment</span>
                            <div id="modalParentIdFile" class="mt-1"></div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Academic Background & Examination Results -->
                <div class="space-y-2">
                    <h4 class="font-bold text-slate-800 uppercase text-xs tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-graduation-cap text-brand-700"></i> Academic Records & Prior Institution
                    </h4>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                                <span class="block text-[10px] uppercase font-bold text-slate-400">Former Institution</span>
                                <span id="modalFormerSchool" class="font-bold text-slate-800 text-xs"></span>
                            </div>
                            <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                                <span class="block text-[10px] uppercase font-bold text-slate-400">Index / Identification No</span>
                                <span id="modalIndexNumber" class="font-mono font-bold text-slate-800 text-xs"></span>
                            </div>
                            <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                                <span class="block text-[10px] uppercase font-bold text-slate-400">Official Result Slip</span>
                                <div id="modalResultSlipLink" class="mt-1"></div>
                            </div>
                        </div>

                        <!-- S1 PLE Results Box -->
                        <div id="modalPleBox" class="hidden p-3.5 bg-white rounded-lg border border-slate-200 space-y-2">
                            <span class="font-bold text-slate-700 uppercase text-[11px] block">Primary Leaving Examination (PLE) Breakdown</span>
                            <div class="grid grid-cols-2 sm:grid-cols-6 gap-2 text-center text-xs">
                                <div class="p-2 bg-slate-50 rounded border border-slate-100">
                                    <span class="text-[10px] text-slate-400 uppercase block font-bold">English</span>
                                    <span id="modalPleEng" class="font-black text-slate-800"></span>
                                </div>
                                <div class="p-2 bg-slate-50 rounded border border-slate-100">
                                    <span class="text-[10px] text-slate-400 uppercase block font-bold">Math</span>
                                    <span id="modalPleMath" class="font-black text-slate-800"></span>
                                </div>
                                <div class="p-2 bg-slate-50 rounded border border-slate-100">
                                    <span class="text-[10px] text-slate-400 uppercase block font-bold">Science</span>
                                    <span id="modalPleSci" class="font-black text-slate-800"></span>
                                </div>
                                <div class="p-2 bg-slate-50 rounded border border-slate-100">
                                    <span class="text-[10px] text-slate-400 uppercase block font-bold">SST</span>
                                    <span id="modalPleSst" class="font-black text-slate-800"></span>
                                </div>
                                <div class="p-2 bg-brand-50 rounded border border-brand-200">
                                    <span class="text-[10px] text-brand-600 uppercase block font-bold">Aggregates</span>
                                    <span id="modalPleAggs" class="font-black text-brand-800 text-sm"></span>
                                </div>
                                <div class="p-2 bg-gold-50 rounded border border-gold-200">
                                    <span class="text-[10px] text-gold-700 uppercase block font-bold">Division</span>
                                    <span id="modalPleDiv" class="font-black text-gold-900 text-sm"></span>
                                </div>
                            </div>
                        </div>

                        <!-- S5/S6 A-Level Performance Box -->
                        <div id="modalAlevelBox" class="hidden p-3.5 bg-white rounded-lg border border-slate-200 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-700 uppercase text-[11px] block">A-Level Subject Combination & UCE NLSC Competencies</span>
                                <div class="flex items-center gap-2">
                                    <span id="modalCombination" class="px-2.5 py-0.5 rounded font-black text-xs bg-brand-100 text-brand-800 border border-brand-300"></span>
                                    <button type="button" id="modalChangeCombBtn" class="px-2 py-0.5 rounded text-[10.5px] font-bold bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-300 transition">
                                        <i class="fa-solid fa-pen-to-square text-[10px]"></i> Change
                                    </button>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                                <div class="p-2 bg-slate-50 rounded border border-slate-100">
                                    <span class="text-[10px] text-slate-400 uppercase block font-bold">Result Level</span>
                                    <span id="modalUceDivision" class="font-black text-slate-800"></span>
                                </div>
                                <div class="p-2 bg-slate-50 rounded border border-slate-100">
                                    <span class="text-[10px] text-slate-400 uppercase block font-bold">Project Work</span>
                                    <span id="modalProjectWork" class="font-black text-gold-700"></span>
                                </div>
                                <div class="p-2 bg-emerald-50 rounded border border-emerald-200">
                                    <span class="text-[10px] text-emerald-600 uppercase block font-bold">Competency Score</span>
                                    <span id="modalNlscScore" class="font-black text-emerald-800 font-mono"></span>
                                </div>
                                <div class="p-2 bg-sky-50 rounded border border-sky-200 flex items-center justify-center">
                                    <button type="button" id="modalViewNlscDetailsBtn" class="text-xs font-bold text-sky-700 hover:text-sky-900 flex items-center gap-1">
                                        <i class="fa-solid fa-list-check"></i> View Full NLSC Grid
                                    </button>
                                </div>
                            </div>

                            <!-- S6 Transfer Specific Block -->
                            <div id="modalS6TransferDetails" class="hidden p-3 bg-emerald-50/70 rounded-lg border border-emerald-200 space-y-1 text-xs">
                                <span class="font-bold text-emerald-950 uppercase text-[10px] block">Senior Six Transfer Details</span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                                    <div><strong class="text-slate-500">Former S5 School:</strong> <span id="modalS5FormerSchool" class="font-semibold text-slate-800"></span></div>
                                    <div><strong class="text-slate-500">S5 Passes:</strong> <span id="modalS5Passes" class="font-semibold text-slate-800"></span></div>
                                </div>
                                <div class="text-[11px]"><strong class="text-slate-500">Reason:</strong> <span id="modalS5Reason" class="text-slate-700 italic"></span></div>
                                <div id="modalS6ReportLink" class="mt-1"></div>
                            </div>
                        </div>

                        <!-- S2-S4 Transfer Academic Standing Box -->
                        <div id="modalTransferBox" class="hidden p-3.5 bg-white rounded-lg border border-slate-200 space-y-1.5 text-xs">
                            <span class="font-bold text-slate-700 uppercase text-[11px] block">O-Level Transfer Details</span>
                            <div><strong class="text-slate-500">Last Class Attended:</strong> <span id="modalLastClass" class="font-bold text-brand-700"></span></div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: System Audit Trail -->
                <div class="space-y-2">
                    <h4 class="font-bold text-slate-800 uppercase text-xs tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-clock-rotate-left text-brand-700"></i> Submission & Verification Audit
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs">
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Application Date</span>
                            <span id="modalCreatedAt" class="font-medium text-slate-700"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Admitted On</span>
                            <span id="modalAdmittedAt" class="font-medium text-slate-700"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Processed By</span>
                            <span id="modalAdmittedBy" class="font-bold text-brand-700"></span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modal Action Footer -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
                <button type="button" onclick="closeFullAppModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-100 transition shadow-2xs cursor-pointer">
                    Close
                </button>
                <div id="modalActionButtons" class="flex items-center gap-2"></div>
            </div>
        </div>
    </div>

    <!-- Floating Back to Top Button -->
    <button id="backToTopBtn" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" 
            class="fixed bottom-6 right-6 z-40 bg-brand-700 hover:bg-brand-800 text-white w-10 h-10 rounded-full shadow-lg items-center justify-center transition-all duration-300 opacity-0 pointer-events-none flex"
            aria-label="Back to Top">
        <i class="fa-solid fa-arrow-up text-sm"></i>
    </button>

    <script>
    const canAdmitStudents = <?= hasPermission($pdo, $currentRole, 'can_admit_students') ? 'true' : 'false' ?>;
    const canRejectStudents = <?= hasPermission($pdo, $currentRole, 'can_reject_students') ? 'true' : 'false' ?>;
    const canManageSettings = <?= (hasPermission($pdo, $currentRole, 'can_manage_settings') || ($_SESSION['admin_role'] ?? '') === 'super_admin') ? 'true' : 'false' ?>;
    const canTakeAppActions = <?= hasPermission($pdo, $currentRole, 'can_take_application_actions') ? 'true' : 'false' ?>;

    const applicationsRegistry = <?= json_encode($applicationsById ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    function openAppModalById(id) {
        if (applicationsRegistry && applicationsRegistry[id]) {
            openFullAppModal(applicationsRegistry[id]);
        }
    }

    function copyToClipboard(text, el) {
        if (!text || text === 'N/A' || text === 'Not Provided') return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => showCopyToast(text, el)).catch(() => fallbackCopy(text, el));
        } else {
            fallbackCopy(text, el);
        }
    }

    function fallbackCopy(text, el) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            showCopyToast(text, el);
        } catch(e) {}
        document.body.removeChild(ta);
    }

    function showCopyToast(text, el) {
        const toast = document.createElement('div');
        toast.className = 'fixed z-50 bg-slate-900 text-white text-[11px] font-bold px-2.5 py-1 rounded-lg shadow-xl pointer-events-none transition-all duration-200';
        toast.innerHTML = '<i class="fa-solid fa-check text-emerald-400 mr-1"></i> Copied: ' + text;
        if (el && el.getBoundingClientRect) {
            const rect = el.getBoundingClientRect();
            toast.style.left = Math.max(10, rect.left) + 'px';
            toast.style.top = Math.max(10, rect.top - 28) + 'px';
        } else {
            toast.style.bottom = '20px';
            toast.style.right = '20px';
        }
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-4px)';
            setTimeout(() => toast.remove(), 250);
        }, 1200);
    }

    function setSafeText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = (val !== null && val !== undefined && val !== '') ? val : '—';
    }
    function setSafeHtml(id, val) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = val || '';
    }

    function openFullAppModal(app) {
        if (!app) return;

        const fullName = [app.firstname, app.surname, app.othernames].filter(Boolean).join(' ');
        document.getElementById('modalStudentName').textContent = fullName;
        document.getElementById('modalFullName').textContent = fullName;
        document.getElementById('modalAppId').textContent = '#' + String(app.id).padStart(5, '0');
        document.getElementById('modalAcademicYear').textContent = app.academic_year || 'Current';

        // Status badge
        const st = (app.application_status || 'pending').toLowerCase();
        const badge = document.getElementById('modalStatusBadge');
        if (st === 'reported') {
            badge.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-black uppercase bg-purple-600 text-white';
            badge.textContent = 'Reported & Enrolled';
        } else if (st === 'admitted') {
            badge.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-black uppercase bg-emerald-500 text-white';
            badge.textContent = 'Admitted';
        } else if (st === 'rejected') {
            badge.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-black uppercase bg-rose-500 text-white';
            badge.textContent = 'Rejected';
        } else {
            badge.className = 'px-2.5 py-0.5 rounded-full text-[11px] font-black uppercase bg-amber-400 text-slate-900';
            badge.textContent = 'Pending Review';
        }

        // Rejection banner
        const rejBanner = document.getElementById('modalRejectionBanner');
        if (st === 'rejected' && app.rejection_reason) {
            rejBanner.classList.remove('hidden');
            document.getElementById('modalRejectionReason').textContent = app.rejection_reason;
        } else {
            rejBanner.classList.add('hidden');
        }

        // Photo
        const photoImg = document.getElementById('modalStudentPhoto');
        const photoPlaceholder = document.getElementById('modalStudentPhotoPlaceholder');
        const photoWrapper = document.getElementById('modalPhotoDownloadWrapper');
        const photoDownLink = document.getElementById('modalPhotoDownloadLink');
        const photoSrc = app.photo_url || (app.passport_photo ? ('../' + app.passport_photo.replace(/^\.?\//, '')) : '');

        if (photoSrc && !photoSrc.startsWith('data:image/svg')) {
            photoImg.onload = function() {
                photoImg.classList.remove('hidden');
                photoPlaceholder.classList.add('hidden');
            };
            photoImg.onerror = function() {
                photoImg.classList.add('hidden');
                photoPlaceholder.classList.remove('hidden');
            };
            photoImg.src = photoSrc;
            photoImg.classList.remove('hidden');
            photoPlaceholder.classList.add('hidden');
            if (photoDownLink && photoWrapper) {
                photoDownLink.href = 'download_photo.php?id=' + app.id;
                photoWrapper.classList.remove('hidden');
            }
        } else {
            photoImg.src = '';
            photoImg.classList.add('hidden');
            photoPlaceholder.classList.remove('hidden');
            if (photoWrapper) photoWrapper.classList.add('hidden');
        }

        // Class badge
        document.getElementById('modalClassBadge').textContent = 'Class Applied: ' + (app.class_of_entry || 'N/A');

        // Bio-data
        document.getElementById('modalSex').textContent = app.sex || app.gender || 'N/A';
        
        let ageText = 'N/A';
        if (app.dob) {
            const birth = new Date(app.dob);
            const now = new Date();
            let age = now.getFullYear() - birth.getFullYear();
            const m = now.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) age--;
            ageText = app.dob + ' (' + age + ' yrs)';
        }
        document.getElementById('modalDobAge').textContent = ageText;
        document.getElementById('modalReligion').textContent = app.religion || 'Not Specified';
        document.getElementById('modalLin').textContent = app.lin ? app.lin : 'Not Provided (Learner Identification Number Pending)';

        // Address
        const village = app.parent_village || app.village || 'N/A';
        const parish = app.parent_parish || app.parish || 'N/A';
        const subcounty = app.parent_subcounty || app.subcounty || 'N/A';
        const district = app.parent_district || app.district || 'N/A';
        document.getElementById('modalVillage').textContent = village;
        document.getElementById('modalParish').textContent = parish;
        document.getElementById('modalSubcounty').textContent = subcounty;
        document.getElementById('modalDistrict').textContent = district;
        document.getElementById('modalConsolidatedAddress').textContent = app.parent_address || app.address || [village, parish, subcounty, district].filter(v => v !== 'N/A').join(', ') || 'N/A';

        // Parent
        document.getElementById('modalParentName').textContent = app.parent_name || 'N/A';
        document.getElementById('modalParentRelationship').textContent = app.parent_relationship || 'Parent / Guardian';
        document.getElementById('modalParentPhone').textContent = app.parent_phone || 'N/A';
        document.getElementById('modalParentEmail').textContent = app.parent_email || 'Not Provided';
        document.getElementById('modalParentNin').textContent = app.parent_nin || 'Not Provided';

        const parentIdDiv = document.getElementById('modalParentIdFile');
        if (app.parent_id_file) {
            parentIdDiv.innerHTML = '<a href="../' + app.parent_id_file.replace(/^\.?\//, '') + '" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1 bg-brand-50 text-brand-700 hover:bg-brand-100 rounded-lg text-xs font-bold border border-brand-200"><i class="fa-solid fa-id-card"></i> View Parent National ID</a>';
        } else {
            parentIdDiv.innerHTML = '<span class="text-slate-400 italic font-medium">No National ID document uploaded</span>';
        }

        // Academic & Prior School
        document.getElementById('modalFormerSchool').textContent = app.former_school || 'N/A';
        document.getElementById('modalIndexNumber').textContent = app.index_number || 'N/A';

        const slipDiv = document.getElementById('modalResultSlipLink');
        if (app.result_slip) {
            slipDiv.innerHTML = '<a href="../' + app.result_slip.replace(/^\.?\//, '') + '" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1 bg-sky-50 text-sky-700 hover:bg-sky-100 rounded-lg text-xs font-bold border border-sky-200"><i class="fa-solid fa-file-pdf"></i> View Result Slip</a>';
        } else {
            slipDiv.innerHTML = '<span class="text-slate-400 italic">No slip uploaded</span>';
        }

        // Class Specific Panels
        const pleBox = document.getElementById('modalPleBox');
        const alevelBox = document.getElementById('modalAlevelBox');
        const transferBox = document.getElementById('modalTransferBox');

        pleBox.classList.add('hidden');
        alevelBox.classList.add('hidden');
        transferBox.classList.add('hidden');

        if (app.class_of_entry === 'S1') {
            pleBox.classList.remove('hidden');
            document.getElementById('modalPleEng').textContent = app.english || app.ple_eng || '—';
            document.getElementById('modalPleMath').textContent = app.mathematics || app.ple_math || '—';
            document.getElementById('modalPleSci').textContent = app.science || app.ple_sci || '—';
            document.getElementById('modalPleSst').textContent = app.sst || app.ple_sst || '—';
            document.getElementById('modalPleAggs').textContent = app.aggregates || app.ple_aggregates || '—';
            document.getElementById('modalPleDiv').textContent = app.division || app.ple_division || '—';
        } else if (app.class_of_entry === 'S5' || app.class_of_entry === 'S6') {
            alevelBox.classList.remove('hidden');
            document.getElementById('modalCombination').textContent = (app.combination || app.combination_code || 'Combination Pending');
            const changeCombBtn = document.getElementById('modalChangeCombBtn');
            if (changeCombBtn) {
                changeCombBtn.classList.remove('hidden');
                changeCombBtn.onclick = function() {
                    openChangeCombModal(app.id, fullName, app.combination || '');
                };
            }
            document.getElementById('modalUceDivision').textContent = app.division || 'N/A';
            document.getElementById('modalProjectWork').textContent = app.project_work ? ('Grade ' + app.project_work) : 'N/A';

            let nlscScore = 'N/A';
            let nlscData = null;
            if (app.uce_subjects_json) {
                try {
                    nlscData = typeof app.uce_subjects_json === 'string' ? JSON.parse(app.uce_subjects_json) : app.uce_subjects_json;
                    if (nlscData && nlscData.competency_score) {
                        nlscScore = nlscData.competency_score + ' pts';
                    }
                } catch(e) {}
            }
            document.getElementById('modalNlscScore').textContent = nlscScore;

            const nlscBtn = document.getElementById('modalViewNlscDetailsBtn');
            if (nlscData) {
                nlscBtn.parentElement.classList.remove('hidden');
                nlscBtn.onclick = function() {
                    openNlscModal({
                        name: fullName,
                        class: app.class_of_entry,
                        combination: app.combination,
                        index: app.index_number,
                        former_school: app.former_school,
                        division: app.division,
                        project_work: app.project_work,
                        result_slip: app.result_slip,
                        nlsc: nlscData,
                        s6_former_school: app.s6_former_school || (nlscData.s6_transfer ? nlscData.s6_transfer.former_s5_school : ''),
                        s6_report_file: app.s6_report_file || (nlscData.s6_transfer ? nlscData.s6_transfer.report_file : '')
                    });
                };
            } else {
                nlscBtn.parentElement.classList.add('hidden');
            }

            // S6 Transfer specifics
            const s6Block = document.getElementById('modalS6TransferDetails');
            if (app.class_of_entry === 'S6' || (nlscData && nlscData.s6_transfer)) {
                s6Block.classList.remove('hidden');
                const tr = (nlscData && nlscData.s6_transfer) ? nlscData.s6_transfer : {};
                document.getElementById('modalS5FormerSchool').textContent = tr.former_s5_school || app.s6_former_school || 'N/A';
                document.getElementById('modalS5Passes').textContent = tr.s5_principal_passes || 'N/A';
                document.getElementById('modalS5Reason').textContent = tr.transfer_reason || tr.reason || 'N/A';
                
                const repDiv = document.getElementById('modalS6ReportLink');
                const repFile = tr.report_file || tr.report_card || app.s6_report_file;
                if (repFile) {
                    repDiv.innerHTML = '<a href="../' + repFile.replace(/^\.?\//, '') + '" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-100 text-emerald-800 hover:bg-emerald-200 rounded-lg text-xs font-bold border border-emerald-300"><i class="fa-solid fa-file-lines"></i> View S5 Report Card</a>';
                } else {
                    repDiv.innerHTML = '<span class="text-slate-400 italic">No report uploaded</span>';
                }
            } else {
                s6Block.classList.add('hidden');
            }
        } else {
            // S2, S3, S4
            transferBox.classList.remove('hidden');
            document.getElementById('modalLastClass').textContent = app.division || 'Not Specified';
        }

        // Audit Trail
        document.getElementById('modalCreatedAt').textContent = app.created_at ? new Date(app.created_at).toLocaleString() : 'N/A';
        const admittedText = (st === 'admitted' && app.admitted_at) ? new Date(app.admitted_at).toLocaleString() : 
                             ((st === 'reported' && app.reported_at) ? (new Date(app.reported_at).toLocaleString() + ' (Reported)') : '—');
        document.getElementById('modalAdmittedAt').textContent = admittedText;
        const admittedByText = (st === 'admitted' && app.admitted_by) ? app.admitted_by : 
                               ((st === 'reported' && app.reported_by) ? app.reported_by : '—');
        document.getElementById('modalAdmittedBy').textContent = admittedByText;

        // Action Buttons Container
        const actionsDiv = document.getElementById('modalActionButtons');
        actionsDiv.innerHTML = '';

        if (canTakeAppActions) {
            if (st === 'pending') {
                if (canAdmitStudents) {
                    const admitBtn = document.createElement('a');
                    admitBtn.href = 'admit.php?id=' + app.id;
                    admitBtn.className = 'px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5';
                    admitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Admit Candidate';
                    actionsDiv.appendChild(admitBtn);
                }
                if (canRejectStudents) {
                    const rejectBtn = document.createElement('button');
                    rejectBtn.type = 'button';
                    rejectBtn.className = 'px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5 cursor-pointer';
                    rejectBtn.innerHTML = '<i class="fa-solid fa-xmark"></i> Reject Candidate';
                    rejectBtn.onclick = function() {
                        closeFullAppModal();
                        openRejectModal(app.id, fullName, app.class_of_entry, app.parent_email || '');
                    };
                    actionsDiv.appendChild(rejectBtn);
                }
            } else if (st === 'admitted') {
                const offerBtn = document.createElement('a');
                offerBtn.href = 'admission_offer.php?id=' + app.id;
                offerBtn.target = '_blank';
                offerBtn.className = 'px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5';
                offerBtn.innerHTML = '<i class="fa-solid fa-file-pdf"></i> Download Offer Letter';
                actionsDiv.appendChild(offerBtn);

                const reportBtn = document.createElement('a');
                reportBtn.href = 'mark_reported.php?id=' + app.id;
                reportBtn.className = 'px-4 py-2 bg-purple-700 hover:bg-purple-800 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5';
                reportBtn.innerHTML = '<i class="fa-solid fa-user-check"></i> Mark Reported & Enrolled';
                reportBtn.onclick = function(e) {
                    if (!confirm('Confirm that student ' + fullName + ' has physically reported and enrolled at school? This will archive their admission.')) {
                        e.preventDefault();
                    }
                };
                actionsDiv.appendChild(reportBtn);

                const isAuto = (app.is_auto_admitted == 1) || (app.admitted_by && (app.admitted_by.includes('AI') || app.admitted_by.includes('Auto')));
                if (isAuto && app.auto_admit_review_status !== 'reviewed') {
                    const reviewBtn = document.createElement('a');
                    reviewBtn.href = 'review_auto_admit.php?id=' + app.id;
                    reviewBtn.className = 'px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5 whitespace-nowrap';
                    reviewBtn.innerHTML = '<i class="fa-solid fa-check-to-slot"></i> Mark Reviewed';
                    reviewBtn.onclick = function(e) {
                        if (!confirm('Confirm that you have reviewed the auto-admitted documents and credentials for ' + fullName + '?')) {
                            e.preventDefault();
                        }
                    };
                    actionsDiv.appendChild(reviewBtn);
                }

                if (canManageSettings) {
                    const reverseBtn = document.createElement('a');
                    reverseBtn.href = 'reverse.php?id=' + app.id;
                    reverseBtn.className = 'px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5';
                    reverseBtn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Reverse';
                    actionsDiv.appendChild(reverseBtn);
                }
            } else if (st === 'reported') {
                const offerBtn = document.createElement('a');
                offerBtn.href = 'admission_offer.php?id=' + app.id;
                offerBtn.target = '_blank';
                offerBtn.className = 'px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5';
                offerBtn.innerHTML = '<i class="fa-solid fa-file-pdf"></i> View Admission Offer';
                actionsDiv.appendChild(offerBtn);
                // Reported admissions cannot be reversed
            }
        }

        const modalEl = document.getElementById('fullApplicationModal');
        if (modalEl) modalEl.classList.remove('hidden');
    }

    function closeFullAppModal() {
        document.getElementById('fullApplicationModal').classList.add('hidden');
    }

    function openNlscModal(data) {
        document.getElementById('nlscStudentName').textContent = data.name;
        document.getElementById('nlscClassComb').textContent = data.class + ' · ' + (data.combination || 'N/A');
        document.getElementById('nlscIndexNumber').textContent = data.index;
        document.getElementById('nlscFormerSchool').textContent = data.former_school;
        document.getElementById('nlscResultLevel').textContent = data.division || 'N/A';
        document.getElementById('nlscProjectWork').textContent = 'Grade ' + (data.project_work || 'N/A');

        const nlsc = data.nlsc || {};
        document.getElementById('nlscCompetencyScore').textContent = nlsc.competency_score || 0;

        // Result slip link
        const slipDiv = document.getElementById('nlscResultSlipLink');
        if (data.result_slip) {
            slipDiv.innerHTML = '<a href="../' + data.result_slip + '" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-sky-50 text-sky-700 hover:bg-sky-100 border border-sky-200 text-xs font-bold"><i class="fa-solid fa-file-pdf"></i> View Result Slip</a>';
        } else {
            slipDiv.innerHTML = '';
        }

        // S6 transfer card
        const s6Card = document.getElementById('nlscS6TransferCard');
        if (data.class === 'S6' || nlsc.s6_transfer) {
            s6Card.classList.remove('hidden');
            const tr = nlsc.s6_transfer || {};
            document.getElementById('nlscS5School').textContent = tr.former_s5_school || data.s6_former_school || 'N/A';
            document.getElementById('nlscS5Passes').textContent = tr.s5_principal_passes || 'N/A';
            document.getElementById('nlscS5Reason').textContent = tr.transfer_reason || tr.reason || 'N/A';
            const repDiv = document.getElementById('nlscS5ReportLink');
            const repFile = tr.report_file || tr.report_card || data.s6_report_file;
            if (repFile) {
                repDiv.innerHTML = '<a href="../' + repFile + '" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 hover:bg-emerald-200 border border-emerald-300 text-xs font-bold"><i class="fa-solid fa-file-lines"></i> View S5 Report Card</a>';
            } else {
                repDiv.innerHTML = '';
            }
        } else {
            s6Card.classList.add('hidden');
        }

        // Grade summary badges
        const counts = nlsc.counts || { A: 0, B: 0, C: 0, D: 0, E: 0 };
        document.getElementById('nlscGradeSummaryBadges').innerHTML = 
            '<span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">' + (counts.A || 0) + ' A</span>' +
            '<span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-sky-100 text-sky-800 border border-sky-200">' + (counts.B || 0) + ' B</span>' +
            '<span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-200">' + (counts.C || 0) + ' C</span>' +
            '<span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800 border border-rose-200">' + ((counts.D || 0) + (counts.E || 0)) + ' D/E</span>';

        // Subjects Table
        const tbody = document.getElementById('nlscSubjectsTbody');
        tbody.innerHTML = '';
        const subjects = nlsc.subjects || {};
        const pts = { 'A': 5, 'B': 4, 'C': 3, 'D': 2, 'E': 1 };
        const badgeColors = {
            'A': 'bg-emerald-100 text-emerald-800 border-emerald-300',
            'B': 'bg-sky-100 text-sky-800 border-sky-300',
            'C': 'bg-amber-100 text-amber-800 border-amber-300',
            'D': 'bg-orange-100 text-orange-800 border-orange-300',
            'E': 'bg-rose-100 text-rose-800 border-rose-300'
        };

        for (let code in subjects) {
            const subj = subjects[code];
            const g = (subj.grade || '').toUpperCase();
            const p = pts[g] || 0;
            const col = badgeColors[g] || 'bg-slate-100 text-slate-800 border-slate-200';
            const desc = subj.level || 'Standard Competency Level';

            const tr = document.createElement('tr');
            tr.innerHTML = 
                '<td class="px-3.5 py-2 font-bold text-slate-800">' + (subj.name || code) + '</td>' +
                '<td class="px-3.5 py-2 text-center"><span class="px-2.5 py-0.5 rounded font-black text-xs border ' + col + '">Grade ' + g + '</span></td>' +
                '<td class="px-3.5 py-2 text-center font-mono font-bold text-slate-700">' + p + '</td>' +
                '<td class="px-3.5 py-2 text-slate-600 font-medium">' + desc + '</td>';
            tbody.appendChild(tr);
        }

        document.getElementById('nlscModal').classList.remove('hidden');
    }

    function closeNlscModal() {
        document.getElementById('nlscModal').classList.add('hidden');
    }
    function openRejectModal(id, name, cls, email) {
        document.getElementById('rejectApplicantId').value = id;
        document.getElementById('rejectCandidateName').textContent = name;
        document.getElementById('rejectCandidateClass').textContent = cls;
        document.getElementById('rejectCandidateEmail').textContent = email || 'No email provided';
        document.getElementById('rejectionReasonText').value = "Did not satisfy institutional admission cut-off pass mark / aggregates requirement.";
        document.getElementById('presetReasons').value = "Did not satisfy institutional admission cut-off pass mark / aggregates requirement.";
        document.getElementById('rejectModal').classList.remove('hidden');
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }

    function applyPresetReason(val) {
        if (val && val !== 'custom') {
            document.getElementById('rejectionReasonText').value = val;
        } else if (val === 'custom') {
            document.getElementById('rejectionReasonText').value = '';
            document.getElementById('rejectionReasonText').focus();
        }
    }

    function toggleMobileSidebar() {
        const sidebar = document.getElementById('mobileSidebar');
        const backdrop = document.getElementById('mobileSidebarBackdrop');
        if (!sidebar || !backdrop) return;
        const isClosed = sidebar.classList.contains('-translate-x-full');
        if (isClosed) {
            sidebar.classList.remove('-translate-x-full');
            backdrop.classList.remove('hidden');
        } else {
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
        }
    }
    function toggleDropdown() {
        const drop = document.getElementById('profileDropdown');
        if (drop) drop.classList.toggle('hidden');
    }
    window.addEventListener('click', function(e) {
        const btn = document.getElementById('profileDropdownBtn');
        const drop = document.getElementById('profileDropdown');
        if (btn && drop && !btn.contains(e.target) && !drop.contains(e.target)) {
            drop.classList.add('hidden');
        }
    });

    // Profile menu hover
    const profileBtnEl = document.getElementById('profileDropdownBtn');
    const profileDropEl = document.getElementById('profileDropdown');
    if (profileBtnEl && profileDropEl) {
        let hoverTimer;
        const parentBox = profileBtnEl.parentElement;
        parentBox.addEventListener('mouseenter', () => {
            clearTimeout(hoverTimer);
            profileDropEl.classList.remove('hidden');
        });
        parentBox.addEventListener('mouseleave', () => {
            hoverTimer = setTimeout(() => {
                profileDropEl.classList.add('hidden');
            }, 250);
        });
    }

    // Auto-search debouncer
    const dashboardSearch = document.getElementById('dashboardSearchInput');
    if (dashboardSearch) {
        let debounceTimeout;
        dashboardSearch.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                document.getElementById('dashboardFilterForm').submit();
            }, 500);
        });
    }

    // Change A-Level Combination Modal
    function openChangeCombModal(id, name, currentComb) {
        document.getElementById('changeCombApplicantId').value = id;
        document.getElementById('changeCombStudentName').textContent = name;
        document.getElementById('changeCombCurrentVal').textContent = currentComb || 'None';
        const select = document.getElementById('changeCombSelect');
        if (select) {
            select.value = currentComb || '';
        }
        document.getElementById('changeCombModal').classList.remove('hidden');
    }

    function closeChangeCombModal() {
        document.getElementById('changeCombModal').classList.add('hidden');
    }

    // Floating Back-to-top button
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

    <!-- Change A-Level Combination Modal -->
    <div id="changeCombModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
            <div class="px-6 py-4 bg-brand-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center text-sm font-bold border border-white/20 text-gold-400">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-tight">Change Subject Combination</h3>
                        <p class="text-[11px] text-brand-200">Update A-Level combination for student</p>
                    </div>
                </div>
                <button type="button" onclick="closeChangeCombModal()" class="text-white/70 hover:text-white p-1">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>
            <form method="POST" action="change_combination.php" class="p-6 space-y-4">
                <input type="hidden" name="id" id="changeCombApplicantId" value="">
                
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs space-y-1">
                    <div class="flex justify-between">
                        <span class="text-slate-500 font-semibold">Candidate:</span>
                        <span id="changeCombStudentName" class="font-bold text-slate-800"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 font-semibold">Current Combination:</span>
                        <span id="changeCombCurrentVal" class="font-bold text-brand-700"></span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                        Select New A-Level Combination <span class="text-rose-600">*</span>
                    </label>
                    <select name="combination" id="changeCombSelect" required class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs bg-white focus:outline-none focus:border-brand-700 font-semibold text-slate-800">
                        <option value="">-- Select Combination --</option>
                        <?php foreach($aLevelCombinations as $c): ?>
                            <option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['code']) ?> - <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['category']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeChangeCombModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-sm flex items-center gap-1.5">
                        <i class="fa-solid fa-check"></i> Save Combination
                    </button>
                </div>
            </form>
        </div>
    </div>

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
