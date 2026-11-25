<?php
require_once __DIR__ . '/auth.php';

// Fetch dynamic configurations
$school_name       = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_address    = getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro - Uganda');
$school_phone      = getSetting($pdo, 'school_phone', '+256 790 001 631');
$logo_source       = getSchoolLogoSrc($pdo, '../');
$school_website_url = getSchoolWebsiteUrl($pdo);
$adminFirstName     = getAdminDisplayName($pdo, $_SESSION['admin_username'] ?? '');
$currentRole        = $_SESSION['admin_role'] ?? '';
$primary_color      = getPrimaryColor($pdo);

if (!hasPermission($pdo, $currentRole, 'can_view_reports')) {
    header("Location: dashboard.php?error=" . urlencode("Access Denied: You do not have permission to view reports."));
    exit;
}

$filterClass = $_GET['class'] ?? 'ALL';
$filterYear  = $_GET['academic_year'] ?? 'all';
$filterStatus = $_GET['status'] ?? 'all';
$filterSex   = $_GET['sex'] ?? 'all';
$search      = trim($_GET['search'] ?? '');

$curYear = getSetting($pdo, 'academic_year', date('Y'));

// Check if academic_year column exists in applications; self-heal if missing
$hasAcademicYearCol = false;
try {
    $chkCol = $pdo->query("SHOW COLUMNS FROM applications LIKE 'academic_year'");
    if ($chkCol && $chkCol->rowCount() > 0) {
        $hasAcademicYearCol = true;
    } else {
        $pdo->exec("ALTER TABLE applications ADD academic_year VARCHAR(20) DEFAULT NULL");
        $pdo->exec("UPDATE applications SET academic_year = '{$curYear}' WHERE academic_year IS NULL OR academic_year = ''");
        $hasAcademicYearCol = true;
    }
} catch (Exception $e) {
    $hasAcademicYearCol = false;
}

// Available academic years
$availableYears = [];
if ($hasAcademicYearCol) {
    try {
        $availableYears = $pdo->query("SELECT DISTINCT academic_year FROM applications WHERE academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
}
if (!in_array($curYear, $availableYears)) {
    array_unshift($availableYears, $curYear);
}

$academic_year = ($filterYear !== 'all') ? $filterYear : getAcademicYear($pdo, $filterClass !== 'ALL' ? $filterClass : null);

$query = "SELECT * FROM applications";
$where = [];
$params = [];

if ($filterClass !== 'ALL') {
    if ($filterClass === 'OTHER') {
        $where[] = "class_of_entry IN ('S2', 'S3', 'S4', 'S6')";
    } else {
        $where[] = "class_of_entry = ?";
        $params[] = $filterClass;
    }
}

if ($filterStatus !== 'all') {
    $where[] = "application_status = ?";
    $params[] = $filterStatus;
}

if ($filterSex !== 'all') {
    $where[] = "sex = ?";
    $params[] = $filterSex;
}

if ($hasAcademicYearCol && $filterYear !== 'all') {
    $where[] = "academic_year = ?";
    $params[] = $filterYear;
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

$whereClause = count($where) > 0 ? (" WHERE " . implode(" AND ", $where)) : "";

// Count total matching records
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM applications" . $whereClause);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

// Pagination: 20 rows per page
$perPage = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Paginated query for table
$query = "SELECT * FROM applications" . $whereClause . " ORDER BY surname ASC, firstname ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($query);
$paramIdx = 1;
foreach ($params as $p) {
    $stmt->bindValue($paramIdx++, $p);
}
$stmt->bindValue($paramIdx++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($paramIdx++, $offset, PDO::PARAM_INT);
$stmt->execute();
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= CSV EXPORT ================= */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Admissions_Report_' . $filterClass . '_' . ($filterYear !== 'all' ? $filterYear . '_' : '') . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'Student Full Name', 'Gender', 'Class', 'LIN', 'Index Number', 'Aggregates', 'Former School', 'Division / Combination', 'Parent Name', 'Parent Phone', 'Status', 'Academic Year', 'Admitted On', 'Admitted By']);
    
    // Fetch ALL records for full CSV export
    $exportStmt = $pdo->prepare("SELECT * FROM applications" . $whereClause . " ORDER BY surname ASC, firstname ASC");
    $exportStmt->execute($params);
    $allApps = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    $idx = 1;
    foreach ($allApps as $app) {
        $status = strtolower($app['application_status'] ?? 'pending');
        $admittedOn = ($status === 'admitted') ? (!empty($app['admitted_at']) ? date('d/m/Y H:i', strtotime($app['admitted_at'])) : (!empty($app['created_at']) ? date('d/m/Y H:i', strtotime($app['created_at'])) : '—')) : '—';
        $admittedBy = !empty($app['admitted_by']) ? $app['admitted_by'] : (($status === 'admitted') ? 'Admissions Office' : '—');
        $acad = ($app['class_of_entry'] === 'S1') ? ('PLE Div ' . ($app['division'] ?? '')) : (($app['class_of_entry'] === 'S5' || $app['class_of_entry'] === 'S6') ? (($app['combination'] ?? '') . ' (' . ($app['division'] ?? '') . ')') : ('Last: ' . ($app['division'] ?? '')));
        fputcsv($out, [
            $idx++,
            trim($app['firstname'] . ' ' . $app['surname'] . ' ' . ($app['othernames'] ?? '')),
            $app['sex'] ?? '',
            $app['class_of_entry'] ?? '',
            $app['lin'] ?? '—',
            $app['index_number'] ?? '',
            $app['aggregates'] ?? '',
            $app['former_school'] ?? '',
            $acad,
            $app['parent_name'] ?? '',
            $app['parent_phone'] ?? '',
            ucfirst($status),
            $app['academic_year'] ?? $academic_year,
            $admittedOn,
            $admittedBy
        ]);
    }
    fclose($out);
    exit;
}

$count = count($applications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Custom Admission Reports | <?= htmlspecialchars($school_short_name) ?></title>
<link rel="icon" type="image/png" href="<?= !empty($logo_source) ? htmlspecialchars($logo_source) : '../images/default_logo.png' ?>">
<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com" data-cfasync="false"></script>
<!-- Font Awesome & Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= renderThemeConfig($pdo) ?>
<style>
@media print {
    .no-print {
        display: none !important;
    }
    body {
        background-color: white !important;
        color: black !important;
        font-size: 11px;
    }
    .print-container {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
    }
    table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    th, td {
        border: 1px solid #cbd5e1 !important;
        padding: 6px 8px !important;
    }
}
</style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-h-screen flex flex-col md:flex-row overflow-x-hidden">

    <!-- Mobile Drawer Overlay & Sidebar -->
    <div id="mobileSidebarBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden no-print" onclick="toggleMobileSidebar()"></div>
    <aside id="mobileSidebar" class="fixed top-0 bottom-0 left-0 w-72 text-white flex flex-col z-50 transform -translate-x-full transition-transform duration-200 ease-in-out md:hidden shadow-2xl no-print" style="background-color: <?= htmlspecialchars($primary_color) ?>;">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="<?= $logo_source ?>" alt="Logo" class="w-9 h-9 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
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
            <a href="applications.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-id-card-clip text-lg text-gold-400"></i> Applications
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted Register
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-bold bg-white/20 text-white ring-1 ring-white/30 shadow-sm transition duration-150">
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
    </aside>

    <!-- Desktop Sidebar (Hidden on print) -->
    <aside class="hidden md:flex w-64 text-white flex-col shrink-0 min-h-screen no-print" style="background-color: <?= htmlspecialchars($primary_color) ?>;">
        <div class="px-6 py-5 border-b border-white/10 flex items-center gap-3">
            <img src="<?= $logo_source ?>" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
            <div>
                <span class="block text-sm font-bold text-white tracking-tight leading-tight uppercase"><?= htmlspecialchars($school_short_name) ?></span>
                <span class="block text-xs font-semibold text-gold-400 uppercase tracking-widest text-[9px]">Consolidated Portal</span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 px-4 py-6 space-y-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1-S6)
            </a>
            <a href="applications.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-id-card-clip text-lg text-gold-400"></i> Applications
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white transition duration-150">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted Register
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-bold bg-white/20 text-white ring-1 ring-white/30 shadow-sm transition duration-150">
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

            <div class="pt-4 border-t border-slate-800 mt-4">
                <a href="<?= htmlspecialchars($school_website_url) ?>" target="_blank" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition">
                    <i class="fa-solid fa-globe text-gold-500"></i> Main School Website <i class="fa-solid fa-arrow-up-right-from-square text-[9px] ml-auto"></i>
                </a>
            </div>
        </nav>

        <div class="px-6 py-4 border-t border-slate-800 text-xs text-slate-500 text-center">
            Admissions Office · <?= htmlspecialchars($academic_year) ?>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <header class="bg-white border-b border-slate-200 px-4 sm:px-6 md:px-8 py-3.5 flex justify-between items-center shadow-sm relative z-10 no-print">
            <div class="flex items-center gap-3">
                <button type="button" onclick="toggleMobileSidebar()" class="md:hidden p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl focus:outline-none" aria-label="Toggle Navigation">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <h1 class="text-base sm:text-xl font-bold text-slate-800 tracking-tight">Custom Admissions Reports</h1>
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

        <div class="flex-1 px-4 sm:px-6 py-6 space-y-6 print-container min-w-0">

            <!-- Official Report Header (Only on print) -->
            <div class="hidden print:block text-center border-b-4 border-slate-800 pb-4 mb-6">
                <div class="flex justify-center items-center gap-4">
                    <img src="<?= $logo_source ?>" alt="Logo" class="w-16 h-16 object-contain">
                    <div class="text-left">
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight"><?= strtoupper($school_name) ?></h1>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest"><?= htmlspecialchars($school_address) ?> | Tel: <?= htmlspecialchars($school_phone) ?></p>
                        <p class="text-sm font-extrabold text-brand-700 mt-1 uppercase">Custom Admission Report Summary • Intake <?= htmlspecialchars($academic_year) ?></p>
                    </div>
                </div>
                <div class="mt-4 flex justify-between text-[10px] font-bold text-slate-600 border-t border-slate-200 pt-2">
                    <span>INTAKE YEAR: <?= htmlspecialchars($academic_year) ?></span>
                    <span>CLASS FILTER: <?= $filterClass ?></span>
                    <span>STATUS FILTER: <?= strtoupper($filterStatus) ?></span>
                    <span>GENDER: <?= strtoupper($filterSex) ?></span>
                    <span>TIMESTAMP: <?= date('d-m-Y H:i:s') ?></span>
                </div>
            </div>

            <!-- Filters Area (Hidden on print) -->
            <form id="reportsFilterForm" method="GET" class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end no-print">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Class of Entry</label>
                    <select name="class" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-brand-700 bg-white shadow-2xs cursor-pointer">
                        <option value="ALL" <?= $filterClass === 'ALL' ? 'selected' : '' ?>>All Classes (S1-S6)</option>
                        <option value="S1" <?= $filterClass === 'S1' ? 'selected' : '' ?>>Senior One (S1)</option>
                        <option value="S2" <?= $filterClass === 'S2' ? 'selected' : '' ?>>Senior Two (S2)</option>
                        <option value="S3" <?= $filterClass === 'S3' ? 'selected' : '' ?>>Senior Three (S3)</option>
                        <option value="S4" <?= $filterClass === 'S4' ? 'selected' : '' ?>>Senior Four (S4)</option>
                        <option value="S5" <?= $filterClass === 'S5' ? 'selected' : '' ?>>Senior Five (S5)</option>
                        <option value="S6" <?= $filterClass === 'S6' ? 'selected' : '' ?>>Senior Six (S6)</option>
                        <option value="OTHER" <?= $filterClass === 'OTHER' ? 'selected' : '' ?>>Other / Transfers</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Academic Year</label>
                    <select name="academic_year" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-brand-700 bg-white shadow-2xs cursor-pointer">
                        <option value="all" <?= $filterYear === 'all' ? 'selected' : '' ?>>All Academic Years</option>
                        <?php foreach ($availableYears as $ay): ?>
                            <option value="<?= htmlspecialchars($ay) ?>" <?= $filterYear === $ay ? 'selected' : '' ?>><?= htmlspecialchars($ay) ?> Academic Year</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Application Status</label>
                    <select name="status" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-brand-700 bg-white shadow-2xs cursor-pointer">
                        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                        <option value="admitted" <?= $filterStatus === 'admitted' ? 'selected' : '' ?>>Admitted</option>
                        <option value="reported" <?= $filterStatus === 'reported' ? 'selected' : '' ?>>Enrolled & Reported</option>
                        <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Gender</label>
                    <select name="sex" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-brand-700 bg-white shadow-2xs cursor-pointer">
                        <option value="all" <?= $filterSex === 'all' ? 'selected' : '' ?>>All Genders</option>
                        <option value="Male" <?= $filterSex === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $filterSex === 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <div class="flex-1 relative">
                        <input type="text" name="search" id="reportsSearchInput" value="<?= htmlspecialchars($search) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-700 focus:outline-none focus:border-brand-700" placeholder="Auto-search...">
                    </div>
                    <a href="export_report_pdf.php?class=<?= urlencode($filterClass) ?>&academic_year=<?= urlencode($filterYear) ?>&status=<?= urlencode($filterStatus) ?>&sex=<?= urlencode($filterSex) ?>&search=<?= urlencode($search) ?>" target="_blank" class="px-3 py-2 bg-rose-700 hover:bg-rose-800 text-white rounded-xl text-xs font-bold shadow-2xs transition inline-flex items-center justify-center gap-1 whitespace-nowrap flex-nowrap shrink-0" title="Export PDF">
                        <i class="fa-solid fa-file-pdf"></i>
                    </a>
                    <a href="reports.php?class=<?= urlencode($filterClass) ?>&academic_year=<?= urlencode($filterYear) ?>&status=<?= urlencode($filterStatus) ?>&sex=<?= urlencode($filterSex) ?>&search=<?= urlencode($search) ?>&export=csv" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-2xs transition inline-flex items-center justify-center gap-1 whitespace-nowrap flex-nowrap shrink-0" title="Export CSV">
                        <i class="fa-solid fa-file-csv"></i>
                    </a>
                </div>
            </form>

            <!-- Table Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col print:border-none print:shadow-none">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center no-print">
                    <div>
                        <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">Report Records</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Showing 20 report records per page</p>
                    </div>
                    <span class="text-xs font-bold text-brand-700 bg-brand-50 border border-brand-200 px-2.5 py-1 rounded">Total Records: <?= $totalRecords ?></span>
                </div>

                <div class="md:hidden flex items-center justify-end gap-1.5 text-[10.5px] text-slate-500 py-1.5 px-4 bg-slate-50 border-b border-slate-100 font-medium no-print">
                    <i class="fa-solid fa-arrows-left-right text-brand-700"></i> Swipe table horizontally to view all report columns
                </div>
                <div class="w-full overflow-x-auto print:overflow-visible" style="-webkit-overflow-scrolling: touch;">
                    <table class="w-full min-w-[980px] print:min-w-0 print:table-fixed text-left border-collapse text-xs sm:text-sm">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-black uppercase tracking-wider text-xs print:bg-white print:border-b-2 print:border-black">
                                <th class="w-[3.5%] px-1.5 py-3 text-center">#</th>
                                <th class="w-[11%] px-1.5 py-3 text-center">Admission No</th>
                                <th class="w-[15%] px-2 py-3">Student Name</th>
                                <th class="w-[5%] px-1 py-3 text-center">Gender</th>
                                <th class="w-[4.5%] px-1 py-3 text-center">Class</th>
                                <th class="w-[8.5%] px-1.5 py-3 text-center">Index No</th>
                                <th class="w-[4.5%] px-1 py-3 text-center">Aggs</th>
                                <th class="w-[14%] px-2 py-3">Academics Details</th>
                                <th class="w-[15.5%] px-2 py-3">Parent / Contact</th>
                                <th class="w-[8%] px-1 py-3 text-center">Status</th>
                                <th class="w-[8%] px-1 py-3 text-center">Admitted On</th>
                                <th class="w-[8%] px-1.5 py-3 text-center">Admitted By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm print:divide-y print:divide-slate-300">
                            <?php 
                            $idx = 1;
                            foreach ($applications as $app): 
                                $serialNo = $offset + $idx;
                                $status = strtolower($app['application_status'] ?? 'pending');
                                
                                $admittedDate = '—';
                                if ($status === 'reported') {
                                    $admittedDate = !empty($app['reported_at']) ? date('d/m/Y', strtotime($app['reported_at'])) : (!empty($app['admitted_at']) ? date('d/m/Y', strtotime($app['admitted_at'])) : '—');
                                } elseif ($status === 'admitted') {
                                    if (!empty($app['admitted_at'])) {
                                        $admittedDate = date('d/m/Y', strtotime($app['admitted_at']));
                                    } elseif (!empty($app['created_at'])) {
                                        $admittedDate = date('d/m/Y', strtotime($app['created_at']));
                                    }
                                }

                                $admittedBy = '—';
                                if ($status === 'reported') {
                                    $admittedBy = !empty($app['reported_by']) ? $app['reported_by'] : (!empty($app['admitted_by']) ? $app['admitted_by'] : 'Admissions Office');
                                } elseif (!empty($app['admitted_by'])) {
                                    $admittedBy = $app['admitted_by'];
                                } elseif ($status === 'admitted') {
                                    $admittedBy = 'Admissions Office';
                                }
                            ?>
                            <tr class="hover:bg-slate-50 transition duration-150">
                                <td class="px-1.5 py-3 text-center font-bold text-slate-400 font-mono text-xs print:text-black"><?= $serialNo ?></td>
                                <td class="px-1.5 py-3 text-center font-mono font-bold text-slate-800 print:text-black text-xs">
                                    <?= htmlspecialchars(getAdmissionNumber($app)) ?>
                                </td>
                                <td class="px-2 py-3 font-bold text-slate-900 print:text-black text-sm leading-tight break-words">
                                    <?= htmlspecialchars(trim($app['firstname'].' '.$app['surname'].' '.($app['othernames'] ?? ''))) ?>
                                </td>
                                <td class="px-1 py-3 text-center capitalize font-semibold text-slate-700 print:text-black text-xs"><?= htmlspecialchars($app['sex']) ?></td>
                                <td class="px-1 py-3 text-center font-black text-brand-800 print:text-black text-xs"><?= htmlspecialchars($app['class_of_entry']) ?></td>
                                <td class="px-1.5 py-3 text-center font-mono text-slate-700 print:text-black text-xs">
                                    <?php if(!empty($app['index_number'])): ?>
                                        <span class="inline-flex items-center gap-1 group cursor-pointer text-slate-800 hover:text-brand-700 transition" onclick="copyToClipboard('<?= htmlspecialchars(addslashes($app['index_number'])) ?>', this)" title="Click to copy Index">
                                            <span><?= htmlspecialchars($app['index_number']) ?></span>
                                            <i class="fa-regular fa-copy text-[9px] text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400">N/A</span>
                                    <?php endif; ?>
                                    <?php if(!empty($app['lin'])): ?>
                                        <span class="inline-flex items-center gap-1 group cursor-pointer text-[10px] font-mono text-brand-700 font-bold mt-0.5 transition" onclick="copyToClipboard('<?= htmlspecialchars(addslashes($app['lin'])) ?>', this)" title="Click to copy LIN">
                                            <span>LIN: <?= htmlspecialchars($app['lin']) ?></span>
                                            <i class="fa-regular fa-copy text-[8px] text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-1 py-3 text-center font-black text-brand-800 print:text-black text-xs"><?= htmlspecialchars($app['aggregates'] ?? 'N/A') ?></td>
                                <td class="px-2 py-3 text-slate-700 print:text-black leading-tight">
                                    <span class="block text-xs font-semibold text-slate-900 truncate" title="<?= htmlspecialchars($app['former_school'] ?? 'N/A') ?>"><?= htmlspecialchars($app['former_school'] ?? 'N/A') ?></span>
                                    <?php if($app['class_of_entry'] === 'S1'): ?>
                                        <span class="block text-xs text-slate-500 font-medium truncate">PLE Div: <?= htmlspecialchars($app['division'] ?? 'N/A') ?></span>
                                    <?php elseif($app['class_of_entry'] === 'S5' || $app['class_of_entry'] === 'S6'): ?>
                                        <span class="block text-xs text-gold-700 font-bold truncate"><?= htmlspecialchars($app['combination'] ?? 'N/A') ?> (<?= htmlspecialchars($app['division'] ?? '') ?>)</span>
                                    <?php else: ?>
                                        <span class="block text-xs text-slate-500 font-medium truncate">Last: <?= htmlspecialchars($app['division'] ?? 'N/A') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 py-3 text-slate-700 print:text-black leading-tight">
                                    <span class="block text-xs font-bold text-slate-900 truncate" title="<?= htmlspecialchars($app['parent_name'] ?? 'N/A') ?>"><?= htmlspecialchars($app['parent_name'] ?? 'N/A') ?></span>
                                    <?php if(!empty($app['parent_phone'])): ?>
                                        <span class="inline-flex items-center gap-1 group cursor-pointer text-xs font-mono text-slate-700 hover:text-brand-700 transition" onclick="copyToClipboard('<?= htmlspecialchars(addslashes($app['parent_phone'])) ?>', this)" title="Click to copy Phone">
                                            <i class="fa-solid fa-phone text-[9px] text-slate-400"></i>
                                            <span><?= htmlspecialchars($app['parent_phone']) ?></span>
                                            <i class="fa-regular fa-copy text-[9px] text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </span>
                                    <?php endif; ?>
                                    <?php if(!empty($app['parent_email'])): ?>
                                        <span class="flex items-center gap-1 group cursor-pointer text-xs text-slate-600 hover:text-brand-700 truncate max-w-[170px] transition" onclick="copyToClipboard('<?= htmlspecialchars(addslashes($app['parent_email'])) ?>', this)" title="Click to copy Email: <?= htmlspecialchars($app['parent_email']) ?>">
                                            <i class="fa-solid fa-envelope text-[9px] text-slate-400 shrink-0"></i>
                                            <span class="truncate"><?= htmlspecialchars($app['parent_email']) ?></span>
                                            <i class="fa-regular fa-copy text-[9px] text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity shrink-0"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-1 py-3 text-center">
                                    <?php if($status=='reported'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-black bg-purple-100 text-purple-900 border border-purple-300 shadow-2xs whitespace-nowrap"><i class="fa-solid fa-user-check text-purple-700 text-[10px]"></i> Enrolled</span>
                                    <?php elseif($status=='admitted'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 shadow-2xs whitespace-nowrap"><i class="fa-solid fa-circle-check text-emerald-600 text-[10px]"></i> Admitted</span>
                                    <?php elseif($status=='rejected'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-300 shadow-2xs whitespace-nowrap"><i class="fa-solid fa-circle-xmark text-rose-600 text-[10px]"></i> Rejected</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300 shadow-2xs whitespace-nowrap"><i class="fa-solid fa-hourglass-half text-amber-600 text-[10px]"></i> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-1 py-3 text-center text-xs text-slate-700 print:text-black">
                                    <span class="font-semibold block"><?= $admittedDate ?></span>
                                </td>
                                <td class="px-1.5 py-3 text-center print:text-black">
                                    <?php if($status === 'admitted' || $status === 'reported'): ?>
                                        <span class="inline-block truncate max-w-full px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200" title="<?= htmlspecialchars($admittedBy) ?>">
                                            <?= htmlspecialchars($admittedBy) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-xs">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                            $idx++;
                            endforeach; 
                            ?>

                            <?php if (count($applications) === 0): ?>
                            <tr>
                                <td colspan="11" class="px-6 py-12 text-center text-slate-400 font-semibold print:text-black">
                                    <i class="fa-solid fa-folder-open text-3xl block mb-2 text-slate-300 print:hidden"></i>
                                    No records found matching current criteria.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 20 Rows Per Page Pagination -->
                <?php if ($totalPages > 1): 
                    $pageQueryBase = '?class=' . urlencode($filterClass) . '&academic_year=' . urlencode($filterYear) . '&status=' . urlencode($filterStatus) . '&sex=' . urlencode($filterSex) . '&search=' . urlencode($search);
                ?>
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between no-print">
                    <span class="text-xs text-slate-500">
                        Showing <span class="font-bold text-slate-700"><?= min($offset + 1, $totalRecords) ?></span> to <span class="font-bold text-slate-700"><?= min($offset + $perPage, $totalRecords) ?></span> of <span class="font-bold text-slate-700"><?= $totalRecords ?></span> records (20 per page)
                    </span>
                    <div class="flex items-center gap-1.5">
                        <?php if ($page > 1): ?>
                            <a href="<?= $pageQueryBase ?>&page=<?= $page - 1 ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
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
                            <a href="<?= $pageQueryBase ?>&page=<?= $page + 1 ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
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

            <!-- Print Signatures (Only on print) -->
            <?php 
            $htSigPath = getSchoolSignatureFilePath($pdo);
            $hasHtSig = ($htSigPath && file_exists($htSigPath));
            ?>
            <div class="hidden print:flex justify-between items-end mt-16 text-xs pt-8 border-t border-dashed border-slate-300">
                <div>
                    <span class="block font-bold text-slate-700 mb-2">Official Admissions Register Approved By:</span>
                    <?php if ($hasHtSig): ?>
                        <img src="data:image/png;base64,<?= base64_encode(file_get_contents($htSigPath)) ?>" class="h-10 mb-1 max-w-[120px] object-contain">
                        <span class="block border-t border-black w-32 pt-1 font-semibold text-center">Head Teacher</span>
                    <?php else: ?>
                        <div class="h-8"></div>
                        <span class="block border-t border-black w-32 pt-1 font-semibold text-center">Head Teacher</span>
                    <?php endif; ?>
                    <span class="block text-[10px] text-slate-500 mt-1">Date: ____________________</span>
                </div>
                <div class="text-right">
                    <span class="block font-bold text-slate-700">Official Verification:</span>
                    <span class="block mt-2 font-semibold text-slate-800">OFFICE OF ADMISSIONS REGISTRY</span>
                    <span class="block text-[10px] text-slate-500 font-mono">Status: System Certified • Date: <?= date('d/m/Y') ?></span>
                </div>
            </div>

        </div>
    </main>

    <!-- Floating Back to Top Button -->
    <button id="backToTopBtn" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" 
            class="fixed bottom-6 right-6 z-40 bg-brand-700 hover:bg-brand-800 text-white w-10 h-10 rounded-full shadow-lg items-center justify-center transition-all duration-300 opacity-0 pointer-events-none flex"
            aria-label="Back to Top">
        <i class="fa-solid fa-arrow-up text-sm"></i>
    </button>

    <script>
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

    // Auto-search debouncer
    const reportsSearch = document.getElementById('reportsSearchInput');
    if (reportsSearch) {
        let debounceTimeout;
        reportsSearch.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                document.getElementById('reportsFilterForm').submit();
            }, 500);
        });
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
</body>
</html>
