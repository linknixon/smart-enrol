<?php
require_once __DIR__ . '/auth.php';

$currentRole = $_SESSION['admin_role'] ?? 'viewer';
$currentStaffName = $_SESSION['admin_name'] ?? 'Staff Officer';

// Branding
$school_name = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$logo_source = getSchoolLogoSrc($pdo, '../');
$curYear = getAcademicYear($pdo);
$school_website_url = getSchoolWebsiteUrl($pdo);
$primary_color = getPrimaryColor($pdo);
$isManualStudentNumber = isManualStudentNumberEnabled($pdo);

$msg = $_GET['msg'] ?? '';

// Distinct Academic Years in the Database
$availableYears = [];
try {
    $stmtYears = $pdo->query("SELECT DISTINCT academic_year FROM applications WHERE academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year DESC");
    $availableYears = $stmtYears->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
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

// Filters
$classFilter = $_GET['class'] ?? 'ALL';
$statusFilter = $_GET['status'] ?? 'ALL';
$yearFilter = $_GET['academic_year'] ?? 'all';
$search = trim($_GET['search'] ?? '');

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

// Pre-resolve photo URLs and build index array
$applicationsById = [];
foreach ($applications as &$appItem) {
    $appItem['photo_url'] = getStudentPhotoUrl($appItem['passport_photo'] ?? '', '../');
    $applicationsById[$appItem['id']] = $appItem;
}
unset($appItem);

// Overall status metrics (independent overall counts)
$totalApps = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$pendingCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE application_status = 'pending'")->fetchColumn();
$admittedCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE application_status = 'admitted'")->fetchColumn();
$reportedCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE application_status = 'reported'")->fetchColumn();
$rejectedCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE application_status = 'rejected'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications Registry · <?= htmlspecialchars($school_short_name) ?></title>
    <link rel="icon" type="image/png" href="<?= !empty($logo_source) ? htmlspecialchars($logo_source) : '../images/default_logo.png' ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Outfit', sans-serif; }
        .shadow-2xs { box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
    </style>
    <?= renderThemeConfig($pdo) ?>
</head>
<body class="bg-slate-50 text-slate-700 antialiased flex min-h-screen">

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
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1-S6)
            </a>
            <a href="applications.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-bold bg-white/20 text-white ring-1 ring-white/30 shadow-sm transition duration-150">
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
        <div class="px-6 py-5 border-b border-white/10 flex items-center gap-3">
            <img src="<?= $logo_source ?>" alt="Logo" width="40" height="40" style="width: 40px; height: 40px; object-fit: contain;" class="w-10 h-10 bg-white rounded-full p-1 border-2 border-gold-500 object-contain logo-img">
            <div>
                <span class="block text-sm font-bold text-white tracking-tight leading-tight uppercase"><?= htmlspecialchars($school_short_name) ?></span>
                <span class="block text-xs font-semibold text-gold-400 uppercase tracking-widest text-[9px]">Consolidated Portal</span>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1-S6)
            </a>
            <a href="applications.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-bold bg-white/20 text-white ring-1 ring-white/30 shadow-sm transition duration-150">
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
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">

        <!-- Top Header Bar -->
        <header class="bg-white border-b border-slate-200 px-4 sm:px-6 md:px-8 py-3.5 flex items-center justify-between sticky top-0 z-30 shadow-2xs">
            <div class="flex items-center gap-3">
                <button type="button" onclick="toggleMobileSidebar()" class="md:hidden text-slate-600 hover:text-slate-900 p-2 rounded-lg hover:bg-slate-100 transition" aria-label="Open menu">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h1 class="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fa-solid fa-id-card-clip text-brand-700"></i> Applications Registry
                    </h1>
                    <p class="text-xs text-slate-400 hidden sm:block">Centralized Candidate Management & Admissions Processing (S1-S6)</p>
                </div>
            </div>

            <!-- Profile Menu -->
            <div class="relative">
                <button type="button" onclick="toggleDropdown()" id="profileDropdownBtn" class="flex items-center gap-2.5 py-1.5 px-2.5 rounded-xl border border-slate-200 hover:border-slate-300 bg-white transition shadow-2xs">
                    <div class="w-7 h-7 rounded-lg bg-brand-700 text-white flex items-center justify-center font-bold text-xs uppercase">
                        <?= strtoupper(substr($currentStaffName, 0, 1)) ?>
                    </div>
                    <div class="text-left hidden sm:block">
                        <span class="block text-xs font-bold text-slate-800 leading-tight"><?= htmlspecialchars($currentStaffName) ?></span>
                        <span class="block text-[10px] font-semibold text-gold-600 uppercase"><?= htmlspecialchars($currentRole) ?></span>
                    </div>
                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 ms-1"></i>
                </button>
                <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-xl shadow-lg py-1 z-50 text-xs">
                    <div class="px-4 py-2 border-b border-slate-100 sm:hidden">
                        <span class="block font-bold text-slate-800"><?= htmlspecialchars($currentStaffName) ?></span>
                        <span class="text-[10px] text-slate-400 uppercase font-semibold"><?= htmlspecialchars($currentRole) ?></span>
                    </div>
                    <a href="change_password.php" class="flex items-center gap-2.5 px-4 py-2 text-slate-700 hover:bg-slate-50 transition">
                        <i class="fa-solid fa-key text-slate-400"></i> Change Password
                    </a>
                    <a href="logout.php" class="flex items-center gap-2.5 px-4 py-2 text-rose-600 hover:bg-rose-50 transition font-bold">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
                    </a>
                </div>
            </div>
        </header>

        <!-- Page Body Content -->
        <div class="flex-1 px-3 sm:px-6 md:px-8 py-4 sm:py-6 space-y-4 sm:space-y-5">

            <!-- Flash Message Alert -->
            <?php if($msg): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 p-3.5 sm:p-4 rounded-xl flex items-center gap-3 shadow-2xs animate-pulse text-xs sm:text-sm font-semibold">
                <i class="fa-solid fa-circle-check text-lg text-emerald-500"></i>
                <span>Action completed successfully.</span>
            </div>
            <?php endif; ?>

            <!-- Status Quick-Filter Pills -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-3">
                <a href="applications.php?class=<?= urlencode($classFilter) ?>&academic_year=<?= urlencode($yearFilter) ?>&search=<?= urlencode($search) ?>&status=ALL" 
                   class="p-2.5 sm:p-3 rounded-xl border transition flex items-center justify-between <?= $statusFilter === 'ALL' ? 'bg-brand-50 border-brand-300 ring-2 ring-brand-700/20' : 'bg-white border-slate-200 hover:border-slate-300' ?>">
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">All Applications</span>
                        <span class="block text-lg sm:text-xl font-extrabold text-slate-800 mt-0.5"><?= $totalApps ?></span>
                    </div>
                    <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center text-sm shrink-0">
                        <i class="fa-solid fa-folder-open"></i>
                    </div>
                </a>

                <a href="applications.php?class=<?= urlencode($classFilter) ?>&academic_year=<?= urlencode($yearFilter) ?>&search=<?= urlencode($search) ?>&status=pending" 
                   class="p-2.5 sm:p-3 rounded-xl border transition flex items-center justify-between <?= $statusFilter === 'pending' ? 'bg-amber-50 border-amber-300 ring-2 ring-amber-500/20' : 'bg-white border-slate-200 hover:border-slate-300' ?>">
                    <div>
                        <span class="block text-[10px] font-bold text-amber-700 uppercase tracking-wider">Pending Review</span>
                        <span class="block text-lg sm:text-xl font-extrabold text-amber-900 mt-0.5"><?= $pendingCount ?></span>
                    </div>
                    <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-sm shrink-0">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                </a>

                <a href="applications.php?class=<?= urlencode($classFilter) ?>&academic_year=<?= urlencode($yearFilter) ?>&search=<?= urlencode($search) ?>&status=admitted" 
                   class="p-2.5 sm:p-3 rounded-xl border transition flex items-center justify-between <?= $statusFilter === 'admitted' ? 'bg-emerald-50 border-emerald-300 ring-2 ring-emerald-500/20' : 'bg-white border-slate-200 hover:border-slate-300' ?>">
                    <div>
                        <span class="block text-[10px] font-bold text-emerald-700 uppercase tracking-wider">Admitted</span>
                        <span class="block text-lg sm:text-xl font-extrabold text-emerald-900 mt-0.5"><?= $admittedCount ?></span>
                    </div>
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm shrink-0">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </a>

                <a href="applications.php?class=<?= urlencode($classFilter) ?>&academic_year=<?= urlencode($yearFilter) ?>&search=<?= urlencode($search) ?>&status=reported" 
                   class="p-2.5 sm:p-3 rounded-xl border transition flex items-center justify-between <?= $statusFilter === 'reported' ? 'bg-purple-50 border-purple-300 ring-2 ring-purple-500/20' : 'bg-white border-slate-200 hover:border-slate-300' ?>">
                    <div>
                        <span class="block text-[10px] font-bold text-purple-700 uppercase tracking-wider">Enrolled</span>
                        <span class="block text-lg sm:text-xl font-extrabold text-purple-900 mt-0.5"><?= $reportedCount ?></span>
                    </div>
                    <div class="w-8 h-8 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center text-sm shrink-0">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </a>

                <a href="applications.php?class=<?= urlencode($classFilter) ?>&academic_year=<?= urlencode($yearFilter) ?>&search=<?= urlencode($search) ?>&status=rejected" 
                   class="col-span-2 sm:col-span-1 p-2.5 sm:p-3 rounded-xl border transition flex items-center justify-between <?= $statusFilter === 'rejected' ? 'bg-rose-50 border-rose-300 ring-2 ring-rose-500/20' : 'bg-white border-slate-200 hover:border-slate-300' ?>">
                    <div>
                        <span class="block text-[10px] font-bold text-rose-700 uppercase tracking-wider">Rejected</span>
                        <span class="block text-lg sm:text-xl font-extrabold text-rose-900 mt-0.5"><?= $rejectedCount ?></span>
                    </div>
                    <div class="w-8 h-8 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center text-sm shrink-0">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>
                </a>
            </div>

            <!-- Filters Area -->
            <form id="dashboardFilterForm" method="GET" class="bg-white p-3.5 sm:p-5 rounded-2xl border border-slate-200 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 items-end">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Filter by Entry Class</label>
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
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Status</label>
                    <select name="status" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-brand-700 bg-white">
                        <option value="ALL" <?= $statusFilter === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                        <option value="admitted" <?= $statusFilter === 'admitted' ? 'selected' : '' ?>>Admitted</option>
                        <option value="reported" <?= $statusFilter === 'reported' ? 'selected' : '' ?>>Reported & Enrolled (Archived)</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Academic Year</label>
                    <select name="academic_year" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-brand-700 bg-white">
                        <option value="all" <?= $yearFilter === 'all' ? 'selected' : '' ?>>All Academic Years</option>
                        <?php foreach ($availableYears as $ay): ?>
                            <option value="<?= htmlspecialchars($ay) ?>" <?= $yearFilter === $ay ? 'selected' : '' ?>><?= htmlspecialchars($ay) ?> Academic Year</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex gap-2">
                    <div class="flex-1 relative">
                        <input type="text" name="search" id="dashboardSearchInput" value="<?= htmlspecialchars($search) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-700 focus:outline-none focus:border-brand-700" placeholder="Auto-search candidate...">
                    </div>
                    <button type="submit" class="px-3.5 py-2 bg-brand-700 hover:bg-brand-800 text-white rounded-xl text-xs sm:text-sm font-bold shadow-sm transition" title="Search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                    <a href="applications.php" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs sm:text-sm font-bold shadow-sm transition flex items-center" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>

            <!-- Candidate Registry Card Container -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <!-- Header with View Switcher -->
                <div class="px-4 sm:px-6 py-3.5 border-b border-slate-100 bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-extrabold text-slate-800 text-sm uppercase tracking-wider">Candidate Registry</h3>
                            <span class="text-xs font-bold text-slate-800 bg-white border border-slate-300 px-2 py-0.5 rounded-full shadow-2xs">Filtered Total: <?= $filteredCount ?></span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-0.5">Showing <?= count($applications) ?> applicants on this page · 20 per page</p>
                    </div>

                    <div class="flex items-center gap-2.5 self-start sm:self-auto">
                        <a href="reports.php" class="inline-flex items-center gap-1.5 px-3 py-1 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 rounded-lg text-xs font-bold transition shadow-2xs">
                            <i class="fa-solid fa-file-invoice text-gold-500"></i> <span class="hidden xs:inline">Custom Reports</span>
                        </a>
                    </div>
                </div>

                <!-- Candidate Registry Table View (Single Unified View) -->
                <div id="candidateTableContainer" class="overflow-x-auto w-full" style="-webkit-overflow-scrolling: touch;">
                    <div class="flex items-center justify-end gap-1.5 text-[10.5px] text-slate-500 py-1.5 px-4 bg-slate-50 border-b border-slate-100 font-medium md:hidden">
                        <i class="fa-solid fa-arrows-left-right text-brand-700"></i> Swipe table sideways to view all columns
                    </div>
                    <table class="w-full text-left border-collapse text-xs table-auto min-w-[850px]">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                                <th class="p-3 w-12 text-center">Photo</th>
                                <th class="p-3">Applicant Name</th>
                                <th class="p-3 text-center">Class</th>
                                <th class="p-3 text-center">UNEB / NLSC Results</th>
                                <th class="p-3 text-center">Gender / Religion</th>
                                <th class="p-3">Former School</th>
                                <th class="p-3">Caregiver Details</th>
                                <th class="p-3 text-center">Applied On</th>
                                <th class="p-3 text-center">Status</th>
                                <th class="p-3 text-center w-36">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php foreach ($applications as $app): 
                                $status = strtolower($app['application_status'] ?? 'pending');
                                $fullName = trim($app['firstname'].' '.$app['surname'].' '.($app['othernames'] ?? ''));
                                $primaryParent = !empty($app['father_name']) ? $app['father_name'] : (!empty($app['mother_name']) ? $app['mother_name'] : ($app['guardian_name'] ?? ($app['parent_name'] ?? '—')));
                                $primaryPhone = !empty($app['father_contact']) ? $app['father_contact'] : (!empty($app['mother_contact']) ? $app['mother_contact'] : ($app['guardian_contact'] ?? ($app['parent_phone'] ?? '')));
                            ?>
                            <tr class="hover:bg-slate-50/80 transition duration-150">
                                <!-- Photo -->
                                <td class="p-2.5 text-center">
                                    <div class="w-8 h-8 rounded-full overflow-hidden border border-slate-200 bg-slate-100 mx-auto flex items-center justify-center">
                                        <?php if(!empty($app['photo_url']) && !str_starts_with($app['photo_url'], 'data:image/svg')): ?>
                                            <img src="<?= htmlspecialchars($app['photo_url']) ?>" alt="Photo" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <span class="font-bold text-[10px] text-slate-400"><?= strtoupper(substr($app['firstname'] ?? 'S', 0, 1) . substr($app['surname'] ?? '', 0, 1)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <!-- Name -->
                                <td class="p-3 font-semibold text-slate-800">
                                    <button type="button" onclick="openAppModalById(<?= $app['id'] ?>)" class="hover:text-brand-700 text-left">
                                        <?= htmlspecialchars($fullName) ?>
                                    </button>
                                    <span class="block text-[10px] text-slate-400 font-mono">ID: #<?= str_pad((string)$app['id'], 5, '0', STR_PAD_LEFT) ?></span>
                                </td>
                                <!-- Class -->
                                <td class="p-3 text-center">
                                    <span class="px-2 py-0.5 rounded font-black text-xs bg-brand-50 text-brand-700 border border-brand-200">
                                        <?= htmlspecialchars($app['class_of_entry']) ?>
                                    </span>
                                    <?php if(!empty($app['combination'])): ?>
                                        <span class="block text-[10px] text-slate-500 font-bold mt-0.5"><?= htmlspecialchars($app['combination']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <!-- Results -->
                                <td class="p-3 text-center text-xs">
                                    <?php if ($app['class_of_entry'] === 'S1'): ?>
                                        <span class="font-bold text-slate-700"><?= $app['ple_division'] ? 'Div '.$app['ple_division'] : '—' ?></span>
                                        <span class="text-[10px] text-slate-400 block"><?= $app['ple_aggregates'] ? $app['ple_aggregates'].' Aggs' : '' ?></span>
                                    <?php elseif (in_array($app['class_of_entry'], ['S5', 'S6'])): ?>
                                        <?php if(!empty($app['uce_result_level'])): ?>
                                            <span class="font-bold text-brand-700"><?= htmlspecialchars($app['uce_result_level']) ?></span>
                                            <span class="text-[10px] text-slate-400 block font-mono"><?= $app['nlsc_competency_score'] ?? '' ?> pts</span>
                                        <?php else: ?>
                                            <span class="font-bold text-slate-700"><?= $app['uce_division'] ? 'Div '.$app['uce_division'] : '—' ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-xs">Transfer</span>
                                    <?php endif; ?>
                                </td>
                                <!-- Gender & Religion -->
                                <td class="p-3 text-center text-xs text-slate-600">
                                    <span><?= htmlspecialchars($app['sex'] ?? $app['gender'] ?? '—') ?></span>
                                    <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($app['religion'] ?? '—') ?></span>
                                </td>
                                <!-- Former School -->
                                <td class="p-3 text-xs text-slate-600 max-w-[150px] truncate" title="<?= htmlspecialchars($app['former_school'] ?? '—') ?>">
                                    <?= htmlspecialchars($app['former_school'] ?? '—') ?>
                                </td>
                                <!-- Caregiver -->
                                <td class="p-3 text-xs">
                                    <span class="font-semibold text-slate-800 block truncate max-w-[140px]"><?= htmlspecialchars($primaryParent) ?></span>
                                    <?php if(!empty($primaryPhone)): ?>
                                        <a href="tel:<?= htmlspecialchars($primaryPhone) ?>" class="text-[10.5px] text-brand-700 hover:underline font-mono">
                                            <?= htmlspecialchars($primaryPhone) ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <!-- Applied On -->
                                <td class="p-3 text-center text-[11px] text-slate-500 font-mono whitespace-nowrap">
                                    <?= date('d M Y', strtotime($app['created_at'])) ?>
                                </td>
                                <!-- Status -->
                                <td class="p-3 text-center">
                                    <?php
                                    $sClass = 'bg-amber-100 text-amber-800';
                                    $sText = 'Pending';
                                    if ($status === 'admitted') { $sClass = 'bg-emerald-100 text-emerald-800'; $sText = 'Admitted'; }
                                    elseif ($status === 'reported') { $sClass = 'bg-purple-100 text-purple-800'; $sText = 'Enrolled'; }
                                    elseif ($status === 'rejected') { $sClass = 'bg-rose-100 text-rose-800'; $sText = 'Rejected'; }
                                    ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase <?= $sClass ?>">
                                        <?= $sText ?>
                                    </span>
                                </td>
                                <!-- Actions -->
                                <td class="p-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button" onclick="openAppModalById(<?= $app['id'] ?>)" class="inline-flex items-center justify-center px-2 py-1 h-6 text-[10.5px] font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-md transition duration-150" title="View Candidate Dossier">
                                            <i class="fa-solid fa-eye mr-1 text-[9px]"></i> View
                                        </button>
                                        <?php if(hasPermission($pdo, $currentRole, 'can_take_application_actions')): ?>
                                            <?php if($status === 'pending'): ?>
                                                <?php if(hasPermission($pdo, $currentRole, 'can_admit_students')): ?>
                                                    <?php if($isManualStudentNumber || in_array($app['class_of_entry'], ['S5', 'S6'])): ?>
                                                        <button type="button" onclick="openAdmitModal(<?= $app['id'] ?>, '<?= htmlspecialchars(addslashes($fullName), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($app['class_of_entry']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($app['combination'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($app['student_number'] ?? ''), ENT_QUOTES) ?>')" class="inline-flex items-center justify-center px-2 py-1 h-6 text-[10.5px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-md transition duration-150 shadow-2xs" title="<?= $isManualStudentNumber ? 'Admit & Assign SchoolPay Payment Code' : 'Admit & Assign Combination' ?>">
                                                            <i class="fa-solid fa-check mr-1 text-[9px]"></i> Admit
                                                        </button>
                                                    <?php else: ?>
                                                        <a href="admit.php?id=<?= $app['id'] ?>" onclick="return confirm('Confirm admission of <?= htmlspecialchars(addslashes($fullName)) ?> to <?= htmlspecialchars($app['class_of_entry']) ?>? Official student number will be auto-generated.')" class="inline-flex items-center justify-center px-2 py-1 h-6 text-[10.5px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-md transition duration-150 shadow-2xs" title="Admit Candidate">
                                                            <i class="fa-solid fa-check mr-1 text-[9px]"></i> Admit
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if(hasPermission($pdo, $currentRole, 'can_reject_students')): ?>
                                                    <button type="button" onclick="openRejectModal(<?= $app['id'] ?>, '<?= htmlspecialchars(addslashes($fullName), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($app['class_of_entry']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($app['parent_email'] ?? ''), ENT_QUOTES) ?>')" class="inline-flex items-center justify-center px-2 py-1 h-6 text-[10.5px] font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-md transition duration-150 shadow-2xs cursor-pointer" title="Reject Candidate">
                                                        <i class="fa-solid fa-xmark mr-1 text-[9px]"></i> Reject
                                                    </button>
                                                <?php endif; ?>
                                            <?php elseif($status === 'admitted'): ?>
                                                <a href="admission_offer.php?id=<?= $app['id'] ?>" target="_blank" class="inline-flex items-center justify-center px-2 py-1 h-6 text-[10.5px] font-bold text-white bg-sky-600 hover:bg-sky-700 rounded-md transition duration-150 shadow-2xs" title="Print Official Admission Offer">
                                                    <i class="fa-solid fa-file-pdf mr-1 text-[9px]"></i> Offer
                                                </a>
                                                <a href="mark_reported.php?id=<?= $app['id'] ?>" onclick="return confirm('Confirm that <?= htmlspecialchars(addslashes($fullName), ENT_QUOTES) ?> has reported and enrolled at school?')" class="inline-flex items-center justify-center px-2 py-1 h-6 text-[10.5px] font-bold text-white bg-purple-700 hover:bg-purple-800 rounded-md transition duration-150 shadow-2xs" title="Mark as Reported & Enrolled">
                                                    <i class="fa-solid fa-user-check mr-1 text-[8.5px]"></i> Reported
                                                </a>
                                                <?php if(hasPermission($pdo, $currentRole, 'can_manage_settings') || $_SESSION['admin_role'] === 'super_admin'): ?>
                                                    <a href="reverse.php?id=<?= $app['id'] ?>" class="inline-flex items-center justify-center px-1.5 py-1 h-6 text-[10px] font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-md transition duration-150 shadow-2xs" title="Reverse Status">
                                                        Reverse
                                                    </a>
                                                <?php endif; ?>
                                            <?php elseif($status === 'reported'): ?>
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
                                <td colspan="10" class="px-6 py-12 text-center text-slate-400 font-semibold">
                                    <i class="fa-solid fa-folder-open text-3xl block mb-2 text-slate-300"></i>
                                    No application entries found.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Bar -->
                <?php if ($totalPages > 1): ?>
                <div class="px-4 sm:px-6 py-3.5 border-t border-slate-100 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-2.5">
                    <span class="text-xs text-slate-500">
                        Showing <span class="font-bold text-slate-700"><?= min($offset + 1, $filteredCount) ?></span> to <span class="font-bold text-slate-700"><?= min($offset + $perPage, $filteredCount) ?></span> of <span class="font-bold text-slate-700"><?= $filteredCount ?></span> applications
                    </span>
                    <div class="flex items-center gap-1.5">
                        <?php 
                        $queryBase = 'applications.php?class=' . urlencode($classFilter) . '&status=' . urlencode($statusFilter) . '&academic_year=' . urlencode($yearFilter) . '&search=' . urlencode($search);
                        ?>
                        <?php if ($page > 1): ?>
                            <a href="<?= $queryBase ?>&page=<?= $page - 1 ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 transition shadow-2xs">
                                <i class="fa-solid fa-chevron-left me-1"></i> Prev
                            </a>
                        <?php else: ?>
                            <span class="px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-xs font-bold text-slate-400 cursor-not-allowed">
                                <i class="fa-solid fa-chevron-left me-1"></i> Prev
                            </span>
                        <?php endif; ?>

                        <span class="px-3 py-1.5 text-xs font-bold text-brand-700 bg-brand-50 border border-brand-200 rounded-lg shadow-2xs">
                            Page <?= $page ?> of <?= $totalPages ?>
                        </span>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?= $queryBase ?>&page=<?= $page + 1 ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 transition shadow-2xs">
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
    <div id="rejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-2 sm:p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
            <div class="px-4 sm:px-6 py-3.5 sm:py-4 bg-rose-50 border-b border-rose-100 flex items-center justify-between">
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
    <div id="nlscModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-955/80 backdrop-blur-sm p-2 sm:p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
            <div class="px-4 sm:px-6 py-3.5 sm:py-4 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center text-sm font-bold border border-white/20 text-white">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wide text-white">UCE NLSC Candidate Result Profile</h3>
                        <p class="text-[11px] text-slate-300">Uganda National Examinations Board (New Lower Secondary Curriculum)</p>
                    </div>
                </div>
                <button type="button" onclick="closeNlscModal()" class="text-slate-300 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
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

                <div id="nlscS6TransferCard" class="hidden p-3.5 bg-emerald-50/70 border border-emerald-200 rounded-xl space-y-1.5 text-xs">
                    <span class="font-bold text-emerald-950 uppercase text-[11px] block"><i class="fa-solid fa-arrow-right-arrow-left"></i> Senior Six Transfer Background</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11.5px]">
                        <div><strong class="text-slate-500">Former S5 School:</strong> <span id="nlscS5School" class="font-semibold text-slate-800"></span></div>
                        <div><strong class="text-slate-500">S5 Passes:</strong> <span id="nlscS5Passes" class="font-semibold text-slate-800"></span></div>
                    </div>
                    <div class="text-[11.5px]"><strong class="text-slate-500">Reason:</strong> <span id="nlscS5Reason" class="text-slate-700 italic"></span></div>
                    <div id="nlscS5ReportLink" class="pt-1"></div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-800 uppercase tracking-wide">Subject Achievement Levels & Descriptors</span>
                        <div id="nlscGradeSummaryBadges" class="flex items-center gap-1.5"></div>
                    </div>
                    <div class="border border-slate-200 rounded-xl overflow-x-auto shadow-2xs" style="-webkit-overflow-scrolling: touch;">
                        <table class="w-full text-xs text-left min-w-[500px]">
                            <thead class="bg-slate-100 text-slate-600 font-bold uppercase text-[10px] border-b border-slate-200">
                                <tr>
                                    <th class="px-3.5 py-2">Subject Name</th>
                                    <th class="px-3.5 py-2 text-center">Letter Grade</th>
                                    <th class="px-3.5 py-2 text-center">Points</th>
                                    <th class="px-3.5 py-2">Achievement Level Descriptor</th>
                                </tr>
                            </thead>
                            <tbody id="nlscSubjectsTbody" class="divide-y divide-slate-100 bg-white"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-end">
                <button type="button" onclick="closeNlscModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-100 transition shadow-2xs cursor-pointer">
                    Close Window
                </button>
            </div>
        </div>
    </div>

    <!-- View Complete Application Details Modal -->
    <div id="fullApplicationModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-2 sm:p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[92vh] flex flex-col overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150 relative">
            <!-- Modal Header -->
            <div class="px-4 sm:px-6 py-3.5 sm:py-4 bg-gradient-to-r from-brand-900 via-brand-800 to-slate-900 text-white flex items-center justify-between shrink-0 relative pr-14">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-lg border border-white/20 text-gold-400 shrink-0">
                        <i class="fa-solid fa-id-card-clip"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 id="modalStudentName" class="text-base font-extrabold uppercase tracking-tight text-white">Application Details</h3>
                            <span id="modalStatusBadge" class="px-2.5 py-0.5 rounded-full text-[11px] font-black uppercase"></span>
                        </div>
                        <p class="text-xs text-brand-200 font-medium">Cohort Intake <span id="modalAcademicYear" class="font-bold text-white"></span> · Application ID: <span id="modalAppId" class="font-mono text-gold-400"></span></p>
                    </div>
                </div>
                <!-- Close Button in Top Right Corner as an X -->
                <button type="button" onclick="closeFullAppModal()" class="absolute top-3 right-3 sm:top-3.5 sm:right-4 w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer border border-white/20 shadow-sm" title="Close Window (X)">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Modal Content -->
            <div class="p-3.5 sm:p-6 overflow-y-auto space-y-4 sm:space-y-6 text-xs text-slate-700">
                <!-- Rejection Banner -->
                <div id="modalRejectionBanner" class="hidden p-3.5 bg-rose-50 border-l-4 border-rose-500 rounded-r-xl space-y-1">
                    <div class="flex items-center gap-2 text-rose-800 font-bold text-xs uppercase">
                        <i class="fa-solid fa-circle-xmark text-sm"></i> Official Rejection Notice
                    </div>
                    <p id="modalRejectionReason" class="text-rose-700 text-xs font-medium italic"></p>
                </div>

                <!-- Section 1: Candidate Bio-Data -->
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
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Nationality</span>
                            <span id="modalNationality" class="font-bold text-slate-800 text-xs"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80 sm:col-span-2">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Place of Residence</span>
                            <span id="modalResidence" class="font-bold text-slate-800 text-xs"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80 sm:col-span-2 lg:col-span-3">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Health / Medical Condition</span>
                            <div id="modalHealthStatus" class="mt-0.5"></div>
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

                <!-- Section 2: Parent / Guardian Information -->
                <div class="space-y-2">
                    <h4 class="font-bold text-slate-800 uppercase text-xs tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-users text-brand-700"></i> Parent / Guardian Information
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Father's Details</span>
                            <div id="modalFatherDetails" class="text-xs text-slate-800 mt-0.5"></div>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Mother's Details</span>
                            <div id="modalMotherDetails" class="text-xs text-slate-800 mt-0.5"></div>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Guardian / Sponsor</span>
                            <div id="modalGuardianDetails" class="text-xs text-slate-800 mt-0.5"></div>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Primary Contact Name</span>
                            <span id="modalParentName" class="font-bold text-slate-800 text-xs"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Primary Phone</span>
                            <span id="modalParentPhone" class="font-bold text-slate-800 text-xs font-mono"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Email Address</span>
                            <span id="modalParentEmail" class="font-medium text-slate-700 text-xs truncate block"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">National ID (NIN)</span>
                            <span id="modalParentNin" class="font-mono font-bold text-slate-800 text-xs"></span>
                        </div>
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80 sm:col-span-2">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Parent National ID Attachment</span>
                            <div id="modalParentIdFile" class="mt-1"></div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Academic Records -->
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

                        <!-- S1 PLE Box -->
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

                        <!-- S5/S6 A-Level Box -->
                        <div id="modalAlevelBox" class="hidden p-3.5 bg-white rounded-lg border border-slate-200 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-800 uppercase text-[11px] block">A-Level Subject Combination & UCE NLSC Performance</span>
                                <div class="flex items-center gap-2">
                                    <span id="modalCombination" class="px-2.5 py-0.5 rounded font-black text-xs bg-slate-100 text-slate-800 border border-slate-300"></span>
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
                                    <span id="modalProjectWork" class="font-black text-slate-800"></span>
                                </div>
                                <div class="p-2 bg-slate-50 rounded border border-slate-100">
                                    <span class="text-[10px] text-slate-500 uppercase block font-bold">Competency Score</span>
                                    <span id="modalNlscScore" class="font-black text-slate-800 font-mono"></span>
                                </div>
                                <div class="p-2 bg-slate-100 rounded border border-slate-200 flex items-center justify-center">
                                    <button type="button" id="modalViewNlscDetailsBtn" class="text-xs font-bold text-slate-700 hover:text-slate-900 flex items-center gap-1 cursor-pointer">
                                        <i class="fa-solid fa-list-check text-slate-500"></i> View Full NLSC Grid
                                    </button>
                                </div>
                            </div>

                            <!-- Embedded Full NLSC Subject Scores Table -->
                            <div id="modalAlevelSubjectsContainer" class="space-y-1.5 pt-2 border-t border-slate-100">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-700 uppercase text-[10px] tracking-wider block">UCE NLSC Subject Scores & Competencies:</span>
                                    <span id="modalAlevelSubjectCount" class="text-[10.5px] font-bold text-slate-500"></span>
                                </div>
                                <div class="overflow-x-auto rounded-lg border border-slate-200" style="-webkit-overflow-scrolling: touch;">
                                    <table class="w-full text-left text-xs border-collapse min-w-[480px]">
                                        <thead>
                                            <tr class="bg-slate-100 text-slate-600 font-bold uppercase text-[10px] border-b border-slate-200">
                                                <th class="px-3 py-1.5">Subject</th>
                                                <th class="px-3 py-1.5 text-center w-24">Grade</th>
                                                <th class="px-3 py-1.5 text-center w-20">Points</th>
                                                <th class="px-3 py-1.5">Competency Level</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modalAlevelSubjectsTbody" class="divide-y divide-slate-100 bg-white"></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- S6 Transfer Specific Block -->
                            <div id="modalS6TransferDetails" class="hidden p-3 bg-slate-50 rounded-lg border border-slate-200 space-y-1 text-xs">
                                <span class="font-bold text-slate-800 uppercase text-[10px] block">Senior Six Transfer Details</span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                                    <div><strong class="text-slate-500">Former S5 School:</strong> <span id="modalS5FormerSchool" class="font-semibold text-slate-800"></span></div>
                                    <div><strong class="text-slate-500">S5 Passes:</strong> <span id="modalS5Passes" class="font-semibold text-slate-800"></span></div>
                                </div>
                                <div class="text-[11px]"><strong class="text-slate-500">Reason:</strong> <span id="modalS5Reason" class="text-slate-700 italic"></span></div>
                                <div id="modalS6ReportLink" class="mt-1"></div>
                            </div>
                        </div>

                        <!-- Transfer Box -->
                        <div id="modalTransferBox" class="hidden p-3.5 bg-white rounded-lg border border-slate-200 space-y-1.5 text-xs">
                            <span class="font-bold text-slate-700 uppercase text-[11px] block">O-Level Transfer Details</span>
                            <div><strong class="text-slate-500">Last Class Attended:</strong> <span id="modalLastClass" class="font-bold text-slate-800"></span></div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Audit Trail -->
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
            <div class="px-4 sm:px-6 py-3 sm:py-4 bg-slate-50 border-t border-slate-200 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-2.5 shrink-0">
                <button type="button" onclick="closeFullAppModal()" class="w-full sm:w-auto px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-100 transition shadow-2xs cursor-pointer text-center">
                    Close
                </button>
                <div id="modalActionButtons" class="flex flex-wrap items-center justify-end gap-2"></div>
            </div>
        </div>
    </div>

    <!-- Admit Candidate & Confirm Subject Combination / SchoolPay Code Modal -->
    <div id="admitModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
            <div class="px-6 py-4 bg-emerald-800 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center text-sm font-bold border border-white/20 text-emerald-200">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-tight" id="admitModalTitle">Admit Candidate</h3>
                        <p class="text-[11px] text-emerald-200" id="admitModalSubtitle">Admissions Committee Review & Student Number Assignment</p>
                    </div>
                </div>
                <button type="button" onclick="closeAdmitModal()" class="text-white/70 hover:text-white p-1">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>
            <form method="POST" action="admit.php" class="p-6 space-y-4" onsubmit="return validateAdmitForm();">
                <input type="hidden" name="id" id="admitApplicantId" value="">
                <input type="hidden" name="return_to" value="applications.php?msg=admitted">
                
                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 font-semibold">Candidate:</span>
                        <span id="admitStudentName" class="font-bold text-slate-900 text-sm"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 font-semibold">Class of Entry:</span>
                        <span id="admitClass" class="font-black text-brand-800 bg-brand-50 px-2 py-0.5 rounded border border-brand-200"></span>
                    </div>
                    <div id="admitDesiredCombRow" class="flex justify-between items-center hidden">
                        <span class="text-slate-500 font-semibold">Candidate Desired Combination:</span>
                        <span id="admitDesiredComb" class="font-bold text-amber-800 bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-200"></span>
                    </div>
                </div>

                <!-- SchoolPay Payment Code / Student Number Field -->
                <div id="admitStudentNumberWrap" class="space-y-1.5 <?= $isManualStudentNumber ? '' : 'hidden' ?>">
                    <label class="block text-xs font-bold text-slate-700 uppercase">
                        SchoolPay Payment Code / Student Number <span class="text-rose-600" id="admitStudentNumReqStar">*</span>
                    </label>
                    <input type="text" name="student_number" id="admitStudentNumberInput" placeholder="e.g. 1002345678 or SCH/2026/001" class="w-full px-3.5 py-2.5 border-2 border-slate-200 rounded-xl text-xs font-mono font-bold uppercase focus:outline-none focus:border-brand-700 text-slate-800" <?= $isManualStudentNumber ? 'required' : '' ?>>
                    <p class="text-[10.5px] text-slate-500">
                        Enter the official student payment reference code. This code will be printed on the official Admission Letter.
                    </p>
                </div>

                <!-- A-Level Combination Field (Visible for S5/S6) -->
                <div id="admitCombWrap" class="space-y-1.5 hidden">
                    <label class="block text-xs font-bold text-slate-700 uppercase">
                        Select Official Assigned Combination <span class="text-rose-600">*</span>
                    </label>
                    <select name="combination" id="admitCombSelect" onchange="toggleAdmitCustomComb(this.value)" class="w-full px-3.5 py-2.5 border-2 border-slate-200 rounded-xl text-xs font-bold bg-white focus:outline-none focus:border-brand-700 text-slate-800">
                        <option value="">-- Choose Assigned Combination --</option>
                        <?php foreach($aLevelCombinations as $c): ?>
                            <option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['code']) ?> - <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['category']) ?>)</option>
                        <?php endforeach; ?>
                        <option value="CUSTOM">+ Type Custom Combination...</option>
                    </select>
                </div>

                <div id="admitCustomWrap" class="hidden space-y-1">
                    <label class="block text-[11px] font-bold text-slate-700 uppercase">Type Custom Combination Code:</label>
                    <input type="text" name="custom_combination" id="admitCustomInput" placeholder="e.g. PCB/ICT, BCM/ICT, MEG/ICT" class="w-full px-3.5 py-2 border border-slate-300 rounded-xl text-xs font-mono font-bold uppercase focus:outline-none focus:border-brand-700">
                    <p class="text-[10px] text-slate-400">All combinations with Principal Mathematics take on Subsidiary ICT (not Sub-Math).</p>
                </div>

                <!-- Auto-generation notice when manual assignment is OFF -->
                <div id="admitAutoNumNotice" class="p-3 bg-blue-50/80 border border-blue-200 rounded-xl text-[11px] text-blue-900 leading-relaxed flex items-start gap-2 <?= $isManualStudentNumber ? 'hidden' : '' ?>">
                    <i class="fa-solid fa-circle-info text-blue-600 mt-0.5 shrink-0"></i>
                    <span><strong>Student Number:</strong> The system will automatically generate a sequential student admission number upon confirmation according to school settings.</span>
                </div>

                <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl text-[11px] text-amber-900 leading-relaxed flex items-start gap-2">
                    <i class="fa-solid fa-circle-info text-amber-600 mt-0.5 shrink-0"></i>
                    <span>Confirming this will officially admit the candidate and dispatch their official Provisional Admission Offer Letter with payment details via registered email.</span>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeAdmitModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-sm flex items-center gap-1.5">
                        <i class="fa-solid fa-check"></i> Confirm Admission
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change A-Level Combination Modal -->
    <div id="changeCombModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
            <div class="px-6 py-4 bg-brand-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center text-sm font-bold border border-white/20 text-gold-400">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-tight">Assign / Change Subject Combination</h3>
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

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase">
                        Select A-Level Combination <span class="text-rose-600">*</span>
                    </label>
                    <select name="combination" id="changeCombSelect" onchange="toggleChangeCombCustom(this.value)" required class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs bg-white focus:outline-none focus:border-brand-700 font-semibold text-slate-800">
                        <option value="">-- Select Combination --</option>
                        <?php foreach($aLevelCombinations as $c): ?>
                            <option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['code']) ?> - <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['category']) ?>)</option>
                        <?php endforeach; ?>
                        <option value="CUSTOM">+ Other / Type Custom Combination...</option>
                    </select>
                </div>

                <div id="changeCombCustomWrap" class="hidden space-y-1">
                    <label class="block text-[11px] font-bold text-slate-700 uppercase">Type Custom Combination Code:</label>
                    <input type="text" name="custom_combination" id="changeCombCustomInput" placeholder="e.g. PCB/ICT, BCM/ICT, MEG/ICT" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono font-bold uppercase focus:outline-none focus:border-brand-700">
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

    <!-- Floating Back to Top Button (Strictly Round Circle) -->
    <button id="backToTopBtn" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" 
            class="fixed bottom-6 right-6 z-50 w-11 h-11 rounded-full flex items-center justify-center aspect-square text-white shadow-xl opacity-0 pointer-events-none transition-all duration-300 hover:scale-110 focus:outline-none"
            style="background-color: <?= htmlspecialchars($primary_color) ?>;"
            aria-label="Back to Top">
        <i class="fa-solid fa-arrow-up text-sm"></i>
    </button>

    <script>
    const canAdmitStudents = <?= hasPermission($pdo, $currentRole, 'can_admit_students') ? 'true' : 'false' ?>;
    const canRejectStudents = <?= hasPermission($pdo, $currentRole, 'can_reject_students') ? 'true' : 'false' ?>;
    const canManageSettings = <?= (hasPermission($pdo, $currentRole, 'can_manage_settings') || ($_SESSION['admin_role'] ?? '') === 'super_admin') ? 'true' : 'false' ?>;
    const canTakeAppActions = <?= hasPermission($pdo, $currentRole, 'can_take_application_actions') ? 'true' : 'false' ?>;
    const isManualStudentNumber = <?= $isManualStudentNumber ? 'true' : 'false' ?>;

    const applicationsRegistry = <?= json_encode($applicationsById ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    function openAppModalById(id) {
        if (applicationsRegistry && applicationsRegistry[id]) {
            openFullAppModal(applicationsRegistry[id]);
        }
    }

    // Close modal on Escape key or backdrop click
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeFullAppModal();
            closeRejectModal();
            closeAdmitS5Modal();
            closeNlscModal();
            closeChangeCombModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const fullModal = document.getElementById('fullApplicationModal');
        if (fullModal) {
            fullModal.addEventListener('click', function(e) {
                if (e.target === this) closeFullAppModal();
            });
        }
    });

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
        const dobStr = app.date_of_birth ? app.date_of_birth : '';
        let dobDisplay = dobStr || 'Not Provided';
        if (dobStr && !isNaN(Date.parse(dobStr))) {
            const birthYear = new Date(dobStr).getFullYear();
            const age = new Date().getFullYear() - birthYear;
            if (age > 0 && age < 60) dobDisplay += ` (${age} yrs)`;
        }
        document.getElementById('modalDobAge').textContent = dobDisplay;
        document.getElementById('modalReligion').textContent = app.religion || 'Not Specified';
        document.getElementById('modalNationality').textContent = app.nationality || 'Ugandan';
        document.getElementById('modalResidence').textContent = app.residence_place || 'Not Provided';

        // Health / Medical status
        const healthEl = document.getElementById('modalHealthStatus');
        if (app.health_problem && app.health_problem.toLowerCase() === 'yes') {
            healthEl.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-50 text-rose-800 border border-rose-200">
                <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> Special Condition: ${escapeHtml(app.health_problem_details || 'Specified Yes, details not specified')}
            </span>`;
        } else {
            healthEl.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                <i class="fa-solid fa-circle-check text-emerald-600"></i> None Reported (Fit)
            </span>`;
        }

        document.getElementById('modalLin').textContent = app.lin || 'N/A';
        document.getElementById('modalVillage').textContent = app.village || '—';
        document.getElementById('modalParish').textContent = app.parish || '—';
        document.getElementById('modalSubcounty').textContent = app.subcounty || '—';
        document.getElementById('modalDistrict').textContent = app.district || '—';

        const addressParts = [app.village, app.parish, app.subcounty, app.district].filter(Boolean);
        document.getElementById('modalConsolidatedAddress').textContent = addressParts.length ? addressParts.join(', ') : 'Not Specified';

        // Parent / Guardian
        let fatherHtml = '<span class="text-slate-400 italic">Not provided</span>';
        if (app.father_name) {
            fatherHtml = `<strong>${escapeHtml(app.father_name)}</strong>`;
            if (app.father_contact) fatherHtml += `<br><a href="tel:${escapeHtml(app.father_contact)}" class="text-brand-700 font-mono hover:underline"><i class="fa-solid fa-phone text-[9px]"></i> ${escapeHtml(app.father_contact)}</a>`;
        }
        document.getElementById('modalFatherDetails').innerHTML = fatherHtml;

        let motherHtml = '<span class="text-slate-400 italic">Not provided</span>';
        if (app.mother_name) {
            motherHtml = `<strong>${escapeHtml(app.mother_name)}</strong>`;
            if (app.mother_contact) motherHtml += `<br><a href="tel:${escapeHtml(app.mother_contact)}" class="text-brand-700 font-mono hover:underline"><i class="fa-solid fa-phone text-[9px]"></i> ${escapeHtml(app.mother_contact)}</a>`;
        }
        document.getElementById('modalMotherDetails').innerHTML = motherHtml;

        let guardianHtml = '<span class="text-slate-400 italic">None specified</span>';
        if (app.guardian_name) {
            guardianHtml = `<strong>${escapeHtml(app.guardian_name)}</strong>`;
            if (app.guardian_contact) guardianHtml += `<br><a href="tel:${escapeHtml(app.guardian_contact)}" class="text-brand-700 font-mono hover:underline"><i class="fa-solid fa-phone text-[9px]"></i> ${escapeHtml(app.guardian_contact)}</a>`;
        }
        document.getElementById('modalGuardianDetails').innerHTML = guardianHtml;

        document.getElementById('modalParentName').textContent = app.parent_name || '—';
        document.getElementById('modalParentPhone').textContent = app.parent_phone || '—';
        document.getElementById('modalParentEmail').textContent = app.parent_email || '—';
        document.getElementById('modalParentNin').textContent = app.parent_nin || '—';

        const parentIdEl = document.getElementById('modalParentIdFile');
        if (app.parent_id_file) {
            parentIdEl.innerHTML = `<a href="../${escapeHtml(app.parent_id_file.replace(/^\.?\//, ''))}" target="_blank" class="inline-flex items-center gap-1.5 text-[11px] font-bold text-brand-700 hover:text-brand-900 bg-brand-50 px-2.5 py-1 rounded-lg border border-brand-200">
                <i class="fa-solid fa-id-card"></i> View National ID File
            </a>`;
        } else {
            parentIdEl.innerHTML = '<span class="text-slate-400 italic text-[11px]">No National ID file uploaded</span>';
        }

        // Academic details
        document.getElementById('modalFormerSchool').textContent = app.former_school || '—';
        document.getElementById('modalIndexNumber').textContent = app.index_number || '—';

        const slipEl = document.getElementById('modalResultSlipLink');
        if (app.result_slip) {
            slipEl.innerHTML = `<a href="../${escapeHtml(app.result_slip.replace(/^\.?\//, ''))}" target="_blank" class="inline-flex items-center gap-1.5 text-[11px] font-bold text-brand-700 hover:text-brand-900 bg-brand-50 px-2.5 py-1 rounded-lg border border-brand-200">
                <i class="fa-solid fa-file-pdf"></i> View Result Slip
            </a>`;
        } else {
            slipEl.innerHTML = '<span class="text-slate-400 italic text-[11px]">No slip uploaded</span>';
        }

        // Boxes for S1, S5, Transfers
        const pleBox = document.getElementById('modalPleBox');
        const alevelBox = document.getElementById('modalAlevelBox');
        const transferBox = document.getElementById('modalTransferBox');
        pleBox.classList.add('hidden');
        alevelBox.classList.add('hidden');
        transferBox.classList.add('hidden');

        if (app.class_of_entry === 'S1') {
            pleBox.classList.remove('hidden');
            document.getElementById('modalPleEng').textContent = app.ple_english || '—';
            document.getElementById('modalPleMath').textContent = app.ple_mathematics || '—';
            document.getElementById('modalPleSci').textContent = app.ple_science || '—';
            document.getElementById('modalPleSst').textContent = app.ple_sst || '—';
            document.getElementById('modalPleAggs').textContent = app.ple_aggregates || '—';
            document.getElementById('modalPleDiv').textContent = app.ple_division ? 'Division ' + app.ple_division : '—';
        } else if (app.class_of_entry === 'S5' || app.class_of_entry === 'S6') {
            alevelBox.classList.remove('hidden');
            document.getElementById('modalCombination').textContent = app.combination || 'Not Assigned';
            document.getElementById('modalUceDivision').textContent = app.uce_result_level || (app.uce_division ? 'Div ' + app.uce_division : '—');
            document.getElementById('modalProjectWork').textContent = app.project_score || app.project_work || '—';
            document.getElementById('modalNlscScore').textContent = app.nlsc_competency_score ? app.nlsc_competency_score + ' pts' : '—';

            // Change comb button hook
            const chgBtn = document.getElementById('modalChangeCombBtn');
            if (chgBtn) {
                chgBtn.onclick = function() {
                    openChangeCombModal(app.id, fullName, app.combination || '');
                };
            }

            // NLSC details button hook
            const nlscBtn = document.getElementById('modalViewNlscDetailsBtn');
            if (nlscBtn) {
                nlscBtn.onclick = function() {
                    openNlscModal(app);
                };
            }

            // Populate NLSC subjects table if present
            const subjectsTbody = document.getElementById('modalAlevelSubjectsTbody');
            subjectsTbody.innerHTML = '';
            let parsedScores = null;
            try {
                if (app.uce_scores) parsedScores = typeof app.uce_scores === 'string' ? JSON.parse(app.uce_scores) : app.uce_scores;
            } catch(e){}

            if (parsedScores && typeof parsedScores === 'object' && Object.keys(parsedScores).length > 0) {
                document.getElementById('modalAlevelSubjectCount').textContent = Object.keys(parsedScores).length + ' Subjects';
                for (const [subj, val] of Object.entries(parsedScores)) {
                    let grade = '—', pts = '—', desc = 'Basic Competence';
                    if (typeof val === 'object' && val !== null) {
                        grade = val.grade || val.score || '—';
                        pts = val.points || '—';
                        desc = val.descriptor || '';
                    } else {
                        grade = String(val);
                    }
                    subjectsTbody.innerHTML += `<tr>
                        <td class="px-3 py-1.5 font-bold text-slate-800">${escapeHtml(subj)}</td>
                        <td class="px-3 py-1.5 text-center font-black text-brand-700">${escapeHtml(grade)}</td>
                        <td class="px-3 py-1.5 text-center font-mono font-bold text-slate-600">${escapeHtml(pts)}</td>
                        <td class="px-3 py-1.5 text-slate-600">${escapeHtml(desc)}</td>
                    </tr>`;
                }
            } else {
                document.getElementById('modalAlevelSubjectCount').textContent = 'No detailed breakdown';
                subjectsTbody.innerHTML = '<tr><td colspan="4" class="px-3 py-3 text-center text-slate-400 italic">No subject level scores recorded</td></tr>';
            }

            // S6 transfer details
            const s6Block = document.getElementById('modalS6TransferDetails');
            if (app.class_of_entry === 'S6' && (app.s5_former_school || app.s5_passes)) {
                s6Block.classList.remove('hidden');
                document.getElementById('modalS5FormerSchool').textContent = app.s5_former_school || '—';
                document.getElementById('modalS5Passes').textContent = app.s5_passes || '—';
                document.getElementById('modalS5Reason').textContent = app.transfer_reason || '—';
                const s6Rep = document.getElementById('modalS6ReportLink');
                if (app.s5_report_card) {
                    s6Rep.innerHTML = `<a href="../${escapeHtml(app.s5_report_card.replace(/^\.?\//, ''))}" target="_blank" class="text-emerald-700 font-bold hover:underline"><i class="fa-solid fa-file-lines"></i> View S5 Report Card</a>`;
                } else {
                    s6Rep.innerHTML = '';
                }
            } else {
                s6Block.classList.add('hidden');
            }
        } else {
            transferBox.classList.remove('hidden');
            document.getElementById('modalLastClass').textContent = app.last_class || app.class_of_entry || '—';
        }

        // Audit Trail
        document.getElementById('modalCreatedAt').textContent = app.created_at || '—';
        document.getElementById('modalAdmittedAt').textContent = app.admitted_at || 'Not Admitted';
        document.getElementById('modalAdmittedBy').textContent = app.admitted_by || '—';

        // Action Buttons in Modal
        const actionsContainer = document.getElementById('modalActionButtons');
        actionsContainer.innerHTML = '';

        if (canTakeAppActions) {
            if (st === 'pending') {
                if (canAdmitStudents) {
                    if (isManualStudentNumber || app.class_of_entry === 'S5' || app.class_of_entry === 'S6') {
                        actionsContainer.innerHTML += `<button type="button" onclick="closeFullAppModal(); openAdmitModal(${app.id}, '${escapeJs(fullName)}', '${escapeJs(app.class_of_entry)}', '${escapeJs(app.combination || '')}', '${escapeJs(app.student_number || '')}');" class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-2xs flex items-center gap-1.5">
                            <i class="fa-solid fa-check"></i> Admit Candidate
                        </button>`;
                    } else {
                        actionsContainer.innerHTML += `<a href="admit.php?id=${app.id}" onclick="return confirm('Confirm admission of ${escapeJs(fullName)} to ${escapeJs(app.class_of_entry)}? Official student number will be auto-generated.')" class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-2xs flex items-center gap-1.5">
                            <i class="fa-solid fa-check"></i> Admit Candidate
                        </a>`;
                    }
                }
                if (canRejectStudents) {
                    actionsContainer.innerHTML += `<button type="button" onclick="closeFullAppModal(); openRejectModal(${app.id}, '${escapeJs(fullName)}', '${escapeJs(app.class_of_entry)}', '${escapeJs(app.parent_email || '')}');" class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 transition shadow-2xs flex items-center gap-1.5">
                        <i class="fa-solid fa-xmark"></i> Reject Application
                    </button>`;
                }
            } else if (st === 'admitted') {
                actionsContainer.innerHTML += `<a href="admission_offer.php?id=${app.id}" target="_blank" class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-white bg-sky-600 hover:bg-sky-700 transition shadow-2xs flex items-center gap-1.5">
                    <i class="fa-solid fa-file-pdf"></i> Print Admission Offer
                </a>`;
                actionsContainer.innerHTML += `<a href="mark_reported.php?id=${app.id}" onclick="return confirm('Confirm that ${escapeJs(fullName)} has reported and enrolled at school?')" class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-white bg-purple-700 hover:bg-purple-800 transition shadow-2xs flex items-center gap-1.5">
                    <i class="fa-solid fa-user-check"></i> Mark as Reported
                </a>`;
                if (canManageSettings) {
                    actionsContainer.innerHTML += `<a href="reverse.php?id=${app.id}" class="px-3 py-1.5 rounded-xl text-xs font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 transition shadow-2xs">
                        Reverse Status
                    </a>`;
                }
            } else if (st === 'reported') {
                actionsContainer.innerHTML += `<a href="admission_offer.php?id=${app.id}" target="_blank" class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200 transition shadow-2xs flex items-center gap-1.5">
                    <i class="fa-solid fa-file-pdf"></i> View Admission Letter
                </a>`;
            }
        }

        document.getElementById('fullApplicationModal').classList.remove('hidden');
    }

    function closeFullAppModal() {
        document.getElementById('fullApplicationModal').classList.add('hidden');
    }

    // Reject Modal
    function openRejectModal(id, name, cls, email) {
        document.getElementById('rejectApplicantId').value = id;
        document.getElementById('rejectCandidateName').textContent = name;
        document.getElementById('rejectCandidateClass').textContent = cls;
        document.getElementById('rejectCandidateEmail').textContent = email || 'No email on record';
        document.getElementById('presetReasons').value = 'Did not satisfy institutional admission cut-off pass mark / aggregates requirement.';
        document.getElementById('rejectionReasonText').value = 'Did not satisfy institutional admission cut-off pass mark / aggregates requirement.';
        document.getElementById('rejectModal').classList.remove('hidden');
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }

    function applyPresetReason(val) {
        if (val === 'custom') {
            document.getElementById('rejectionReasonText').value = '';
            document.getElementById('rejectionReasonText').focus();
        } else {
            document.getElementById('rejectionReasonText').value = val;
        }
    }

    // NLSC Modal
    function openNlscModal(data) {
        if (!data) return;
        const name = [data.firstname, data.surname, data.othernames].filter(Boolean).join(' ');
        document.getElementById('nlscStudentName').textContent = name;
        document.getElementById('nlscClassComb').textContent = (data.class_of_entry || 'S5') + (data.combination ? ' · ' + data.combination : '');
        document.getElementById('nlscIndexNumber').textContent = data.index_number || '—';
        document.getElementById('nlscFormerSchool').textContent = data.former_school || '—';
        document.getElementById('nlscResultLevel').textContent = data.uce_result_level || '—';
        document.getElementById('nlscProjectWork').textContent = data.project_score || data.project_work || '—';
        document.getElementById('nlscCompetencyScore').textContent = data.nlsc_competency_score || '0';

        const slipLink = document.getElementById('nlscResultSlipLink');
        if (data.result_slip) {
            slipLink.innerHTML = `<a href="../${escapeHtml(data.result_slip.replace(/^\.?\//, ''))}" target="_blank" class="px-2.5 py-1 rounded-lg bg-brand-50 text-brand-700 border border-brand-200 text-xs font-bold hover:bg-brand-100 transition"><i class="fa-solid fa-file-pdf"></i> Result Slip</a>`;
        } else {
            slipLink.innerHTML = '';
        }

        const tbody = document.getElementById('nlscSubjectsTbody');
        tbody.innerHTML = '';
        let scores = null;
        try {
            if (data.uce_scores) scores = typeof data.uce_scores === 'string' ? JSON.parse(data.uce_scores) : data.uce_scores;
        } catch(e){}

        if (scores && typeof scores === 'object') {
            for (const [subj, val] of Object.entries(scores)) {
                let grade = '—', pts = '—', desc = 'Basic Competence';
                if (typeof val === 'object' && val !== null) {
                    grade = val.grade || val.score || '—';
                    pts = val.points || '—';
                    desc = val.descriptor || '';
                } else {
                    grade = String(val);
                }
                tbody.innerHTML += `<tr>
                    <td class="px-3.5 py-2 font-bold text-slate-800">${escapeHtml(subj)}</td>
                    <td class="px-3.5 py-2 text-center font-black text-brand-700">${escapeHtml(grade)}</td>
                    <td class="px-3.5 py-2 text-center font-mono font-bold text-slate-700">${escapeHtml(pts)}</td>
                    <td class="px-3.5 py-2 text-slate-600">${escapeHtml(desc)}</td>
                </tr>`;
            }
        } else {
            tbody.innerHTML = '<tr><td colspan="4" class="px-3.5 py-4 text-center text-slate-400">No subject competencies found</td></tr>';
        }

        document.getElementById('nlscModal').classList.remove('hidden');
    }

    function closeNlscModal() {
        document.getElementById('nlscModal').classList.add('hidden');
    }

    // Unified Admit Modal (with SchoolPay Code / Student Number & A-Level Combination support)
    function openAdmitModal(id, name, classVal, currentComb, currentStudentNum) {
        const modal = document.getElementById('admitModal');
        if (!modal) return;

        document.getElementById('admitApplicantId').value = id;
        document.getElementById('admitStudentName').textContent = name;
        
        let displayClass = classVal;
        if (classVal === 'S5') displayClass = 'Senior Five (S5)';
        else if (classVal === 'S6') displayClass = 'Senior Six (S6)';
        else if (classVal === 'S1') displayClass = 'Senior One (S1)';
        else if (classVal === 'S2') displayClass = 'Senior Two (S2)';
        else if (classVal === 'S3') displayClass = 'Senior Three (S3)';
        else if (classVal === 'S4') displayClass = 'Senior Four (S4)';
        document.getElementById('admitClass').textContent = displayClass;

        const studentNumWrap = document.getElementById('admitStudentNumberWrap');
        const studentNumInp = document.getElementById('admitStudentNumberInput');
        const autoNumNotice = document.getElementById('admitAutoNumNotice');
        const studentNumReqStar = document.getElementById('admitStudentNumReqStar');

        if (studentNumInp) {
            studentNumInp.value = currentStudentNum || '';
        }

        if (isManualStudentNumber) {
            if (studentNumWrap) studentNumWrap.classList.remove('hidden');
            if (studentNumInp) studentNumInp.required = true;
            if (autoNumNotice) autoNumNotice.classList.add('hidden');
            if (studentNumReqStar) studentNumReqStar.classList.remove('hidden');
        } else {
            if (currentStudentNum) {
                if (studentNumWrap) studentNumWrap.classList.remove('hidden');
                if (studentNumInp) studentNumInp.required = false;
                if (autoNumNotice) autoNumNotice.classList.add('hidden');
                if (studentNumReqStar) studentNumReqStar.classList.add('hidden');
            } else {
                if (studentNumWrap) studentNumWrap.classList.add('hidden');
                if (studentNumInp) studentNumInp.required = false;
                if (autoNumNotice) autoNumNotice.classList.remove('hidden');
                if (studentNumReqStar) studentNumReqStar.classList.add('hidden');
            }
        }

        const isALevel = (classVal === 'S5' || classVal === 'S6');
        const combWrap = document.getElementById('admitCombWrap');
        const desiredRow = document.getElementById('admitDesiredCombRow');
        const combSelect = document.getElementById('admitCombSelect');
        const customWrap = document.getElementById('admitCustomWrap');
        const customInp = document.getElementById('admitCustomInput');

        if (customWrap) customWrap.classList.add('hidden');
        if (customInp) {
            customInp.value = '';
            customInp.required = false;
        }

        if (isALevel) {
            if (desiredRow) desiredRow.classList.remove('hidden');
            const desiredEl = document.getElementById('admitDesiredComb');
            if (desiredEl) desiredEl.textContent = currentComb || 'None Specified';

            if (combWrap) combWrap.classList.remove('hidden');
            if (combSelect) {
                combSelect.required = true;
                combSelect.value = currentComb || '';
                if (currentComb && combSelect.value !== currentComb) {
                    combSelect.value = 'CUSTOM';
                    if (customWrap) customWrap.classList.remove('hidden');
                    if (customInp) {
                        customInp.value = currentComb;
                        customInp.required = true;
                    }
                }
            }
        } else {
            if (desiredRow) desiredRow.classList.add('hidden');
            if (combWrap) combWrap.classList.add('hidden');
            if (combSelect) {
                combSelect.required = false;
                combSelect.value = '';
            }
        }

        modal.classList.remove('hidden');
        if (isManualStudentNumber && studentNumInp) {
            setTimeout(() => studentNumInp.focus(), 80);
        }
    }

    function closeAdmitModal() {
        const modal = document.getElementById('admitModal');
        if (modal) modal.classList.add('hidden');
    }

    function openAdmitS5Modal(id, name, classVal, currentComb) {
        openAdmitModal(id, name, classVal, currentComb, '');
    }

    function closeAdmitS5Modal() {
        closeAdmitModal();
    }

    function toggleAdmitCustomComb(val) {
        const wrap = document.getElementById('admitCustomWrap');
        const inp = document.getElementById('admitCustomInput');
        if (val === 'CUSTOM') {
            if (wrap) wrap.classList.remove('hidden');
            if (inp) { inp.focus(); inp.required = true; }
        } else {
            if (wrap) wrap.classList.add('hidden');
            if (inp) { inp.required = false; }
        }
    }

    function validateAdmitForm() {
        if (isManualStudentNumber) {
            const inp = document.getElementById('admitStudentNumberInput');
            if (inp && !inp.value.trim()) {
                alert('Please enter the SchoolPay Payment Code / Student Number before confirming admission.');
                inp.focus();
                return false;
            }
        }
        const combWrap = document.getElementById('admitCombWrap');
        if (combWrap && !combWrap.classList.contains('hidden')) {
            const sel = document.getElementById('admitCombSelect');
            if (sel && !sel.value) {
                alert('Please select an official subject combination for this A-Level candidate.');
                sel.focus();
                return false;
            }
            if (sel && sel.value === 'CUSTOM') {
                const cust = document.getElementById('admitCustomInput');
                if (cust && !cust.value.trim()) {
                    alert('Please type the custom combination code.');
                    cust.focus();
                    return false;
                }
            }
        }
        return true;
    }

    // Change Combination Modal
    function openChangeCombModal(id, name, currentComb) {
        document.getElementById('changeCombApplicantId').value = id;
        document.getElementById('changeCombStudentName').textContent = name;
        document.getElementById('changeCombCurrentVal').textContent = currentComb || 'None';
        const select = document.getElementById('changeCombSelect');
        const customWrap = document.getElementById('changeCombCustomWrap');
        const customInp = document.getElementById('changeCombCustomInput');
        if (customWrap) customWrap.classList.add('hidden');
        if (customInp) customInp.value = '';

        if (select) {
            select.value = currentComb || '';
            if (currentComb && select.value !== currentComb) {
                select.value = 'CUSTOM';
                if (customWrap) customWrap.classList.remove('hidden');
                if (customInp) customInp.value = currentComb;
            }
        }
        document.getElementById('changeCombModal').classList.remove('hidden');
    }

    function closeChangeCombModal() {
        document.getElementById('changeCombModal').classList.add('hidden');
    }

    function toggleChangeCombCustom(val) {
        const wrap = document.getElementById('changeCombCustomWrap');
        const inp = document.getElementById('changeCombCustomInput');
        if (val === 'CUSTOM') {
            if (wrap) wrap.classList.remove('hidden');
            if (inp) { inp.focus(); inp.required = true; }
        } else {
            if (wrap) wrap.classList.add('hidden');
            if (inp) { inp.required = false; }
        }
    }

    // Helper functions
    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    function escapeJs(str) {
        if (!str) return '';
        return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    // Sidebar & Profile Dropdown Toggles
    function toggleMobileSidebar() {
        const sidebar = document.getElementById('mobileSidebar');
        const backdrop = document.getElementById('mobileSidebarBackdrop');
        if (sidebar && backdrop) {
            const isClosed = sidebar.classList.contains('-translate-x-full');
            if (isClosed) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
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

    // Auto-search Debouncer
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

    // Back to top button
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
</body>
</html>