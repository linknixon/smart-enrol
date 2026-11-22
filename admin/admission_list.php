<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../config/db.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

$classFilter = $_GET['class'] ?? 'S1';

// Fetch dynamic configurations
$school_name        = getSetting($pdo, 'school_name', 'St. Paul\'s Mutolere Secondary School');
$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$school_address     = getSetting($pdo, 'school_address', 'P.O. Box 12, Kisoro - Uganda');
$school_phone       = getSetting($pdo, 'school_phone', '+256 790 001 631');
$school_code        = getSetting($pdo, 'school_code', '011');
$academic_year      = getAcademicYear($pdo, $classFilter);
$year               = getAcademicYearShort($pdo, $classFilter);
$head_teacher_name  = getSetting($pdo, 'head_teacher_name', 'MR. MBONYE CHARLES');
$head_teacher_title = getSetting($pdo, 'head_teacher_title', 'Head Teacher');

$logo_source = getSchoolLogoSrc($pdo, '../');
$school_logo = $logo_source;
$school_website_url = getSchoolWebsiteUrl($pdo);
$adminFirstName     = getAdminDisplayName($pdo, $_SESSION['admin_username'] ?? '');
$currentRole        = $_SESSION['admin_role'] ?? '';

/* ================= FILTER INPUTS ================= */
$currentAcademicYear = getSetting($pdo, 'academic_year', date('Y'));
$primary_color       = getPrimaryColor($pdo);

// Available academic years for admin filter
$availableYears = [];
if ($isAdmin) {
    try {
        $availableYears = $pdo->query("SELECT DISTINCT academic_year FROM applications WHERE academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
    if (!in_array($currentAcademicYear, $availableYears)) {
        array_unshift($availableYears, $currentAcademicYear);
    }
}

$filterYear = $_GET['academic_year'] ?? ($isAdmin ? 'all' : $currentAcademicYear);
$classFilter = $_GET['class'] ?? 'S1';
$filterSex   = $_GET['sex'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');

$where = ["application_status IN ('admitted', 'reported')"];
$params = [];

if (!$isAdmin) {
    // Public view strictly shows the active academic year; older historical years are archived
    $where[] = "academic_year = ?";
    $params[] = $currentAcademicYear;
} else {
    if ($filterYear !== 'all') {
        $where[] = "academic_year = ?";
        $params[] = $filterYear;
    }
}

if ($classFilter !== 'ALL') {
    if ($classFilter === 'OTHER') {
        $where[] = "class_of_entry IN ('S2', 'S3', 'S4', 'S6')";
    } else {
        $where[] = "class_of_entry = ?";
        $params[] = $classFilter;
    }
}

if ($filterSex !== 'all') {
    $where[] = "sex = ?";
    $params[] = $filterSex;
}

if (!empty($searchQuery)) {
    $where[] = "(firstname LIKE ? OR surname LIKE ? OR othernames LIKE ? OR index_number LIKE ?)";
    $term = "%$searchQuery%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$whereClause = implode(" AND ", $where);

// Count total admitted matching filters
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE $whereClause");
$countStmt->execute($params);
$totalAdmitted = (int)$countStmt->fetchColumn();

// Pagination: 20 rows per page
$perPage = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$totalPages = max(1, (int)ceil($totalAdmitted / $perPage));
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

/* Fetch admitted students (paginated) */
$fetchQuery = "SELECT * FROM applications WHERE $whereClause ORDER BY surname ASC, firstname ASC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($fetchQuery);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Student Number components
$classMap = [
    'S1' => '01',
    'S2' => '02',
    'S3' => '03',
    'S4' => '04',
    'S5' => '05',
    'S6' => '06',
];
$classCode = $classMap[$classFilter] ?? '00';

/* ================= CSV EXPORT (FULL FILTERED LIST) ================= */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (!$isAdmin) {
        header("Location: login.php");
        exit;
    }
    // Fetch ALL admitted students matching filters without pagination for complete CSV export
    $exportQuery = "SELECT * FROM applications WHERE $whereClause ORDER BY surname ASC, firstname ASC";
    $exportStmt = $pdo->prepare($exportQuery);
    $exportStmt->execute($params);
    $allStudents = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Admitted_Register_' . $classFilter . '_' . date('Ymd_His') . '.csv');
    
    $output = fopen("php://output", "w");
    fputcsv($output, ['#', 'Admission Number', 'Student Full Name', 'Gender', 'Class', 'Index Number', 'Aggregates', 'Division / Combination']);

    $idx = 1;
    foreach ($allStudents as $s) {
        $acad = ($s['class_of_entry'] === 'S1') ? ('PLE Div ' . ($s['division'] ?? '')) : (($s['class_of_entry'] === 'S5' || $s['class_of_entry'] === 'S6') ? (($s['combination'] ?? '') . ' (' . ($s['division'] ?? '') . ')') : ('Div ' . ($s['division'] ?? '')));
        fputcsv($output, [
            $idx++,
            getAdmissionNumber($s),
            trim($s['surname'] . ' ' . $s['firstname'] . ' ' . ($s['othernames'] ?? '')),
            ucfirst($s['sex'] ?? ''),
            $s['class_of_entry'] ?? '',
            $s['index_number'] ?? 'N/A',
            $s['aggregates'] ?? 'N/A',
            $acad
        ]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admitted Register | <?= htmlspecialchars($school_short_name) ?></title>
<link rel="icon" type="image/png" href="<?= !empty($logo_source) ? htmlspecialchars($logo_source) : '../images/default_logo.png' ?>">
<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com"></script>
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

    <?php if ($isAdmin): ?>
    <!-- Mobile Drawer Overlay & Sidebar -->
    <div id="mobileSidebarBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden md:hidden no-print" onclick="toggleMobileSidebar()"></div>
    <aside id="mobileSidebar" class="fixed top-0 bottom-0 left-0 w-72 bg-slate-900 text-slate-100 flex flex-col z-50 transform -translate-x-full transition-transform duration-200 ease-in-out md:hidden shadow-2xl no-print">
        <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="<?= $logo_source ?>" alt="Logo" class="w-9 h-9 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
                <div class="min-w-0">
                    <span class="block text-xs font-bold text-white tracking-tight uppercase truncate"><?= htmlspecialchars($school_short_name ?: $school_name) ?></span>
                    <span class="block text-[9px] font-semibold text-gold-500 uppercase tracking-widest">Consolidated Portal</span>
                </div>
            </div>
            <button type="button" onclick="toggleMobileSidebar()" class="text-slate-400 hover:text-white p-1" aria-label="Close menu">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <nav class="flex-1 px-4 py-4 space-y-1.5 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1-S6)
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
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
    </aside>

    <!-- Desktop Sidebar (Hidden on print) -->
    <aside class="hidden md:flex w-64 bg-slate-900 text-slate-100 flex-col shrink-0 min-h-screen no-print">
        <div class="px-6 py-5 border-b border-slate-800 flex items-center gap-3">
            <img src="<?= $logo_source ?>" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
            <div class="min-w-0">
                <span class="block text-sm font-bold text-white tracking-tight leading-tight uppercase truncate" title="<?= htmlspecialchars($school_name) ?>"><?= htmlspecialchars($school_short_name ?: substr($school_name, 0, 16)) ?></span>
                <span class="block text-xs font-semibold text-gold-500 uppercase tracking-widest text-[9px]">Consolidated Portal</span>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S1-S6)
            </a>
            <a href="admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
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
            Admissions Office · <?= htmlspecialchars($academic_year) ?>
        </div>
    </aside>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <header class="bg-white border-b border-slate-200 px-4 sm:px-6 md:px-8 py-3.5 flex justify-between items-center shadow-sm relative z-10 no-print">
            <div class="flex items-center gap-3">
                <?php if ($isAdmin): ?>
                <button type="button" onclick="toggleMobileSidebar()" class="md:hidden p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl focus:outline-none" aria-label="Toggle Navigation">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <?php else: ?>
                <a href="../index.php" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-arrow-left"></i> Main Portal
                </a>
                <img src="<?= $logo_source ?>" alt="Logo" class="w-8 h-8 object-contain">
                <?php endif; ?>
                <h1 class="text-base sm:text-xl font-bold text-slate-800 tracking-tight">Admitted Student Registers</h1>
            </div>
            <div>
                <?php if ($isAdmin): ?>
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
                    <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-xl shadow-lg py-2 z-20 text-left">
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
                <?php else: ?>
                <span class="text-xs font-semibold text-slate-500 bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-lg flex items-center gap-1.5">
                    <i class="fa-solid fa-graduation-cap text-gold-600"></i> Official Register
                </span>
                <?php endif; ?>
            </div>
        </header>

        <!-- Page Content -->
        <div class="flex-1 px-8 py-6 space-y-6 print-container">

            <!-- Print Banner Header -->
            <div class="hidden print:block text-center border-b-4 border-slate-800 pb-4 mb-6">
                <div class="flex justify-center items-center gap-4">
                    <img src="<?= $logo_source ?>" alt="Logo" class="w-16 h-16 object-contain">
                    <div class="text-left">
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight"><?= strtoupper($school_name) ?></h1>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest"><?= htmlspecialchars($school_address) ?> | Tel: <?= htmlspecialchars($school_phone) ?></p>
                        <p class="text-sm font-extrabold text-brand-700 mt-1 uppercase">Official Admitted Register - Senior <?= substr($classFilter, 1) ?> (<?= $classFilter ?>)</p>
                    </div>
                </div>
                <div class="mt-4 flex justify-between text-[10px] font-bold text-slate-600 border-t border-slate-200 pt-2">
                    <span>CLASS LEVEL: <?= $classFilter ?> REGISTER</span>
                    <span>TOTAL ADMITTED: <?= count($students) ?></span>
                    <span>GENERATED AT: <?= date('d-m-Y H:i:s') ?></span>
                </div>
            </div>

            <!-- Page Controls (Hidden on print) -->
            <form id="admissionFilterForm" method="GET" class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-<?= $isAdmin ? '5' : '4' ?> gap-3 items-end no-print">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Class Level</label>
                    <select name="class" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-brand-700 bg-white shadow-sm cursor-pointer">
                        <option value="ALL" <?= $classFilter === 'ALL' ? 'selected' : '' ?>>All Classes (S1-S6)</option>
                        <option value="S1" <?= $classFilter === 'S1' ? 'selected' : '' ?>>Senior One (S1)</option>
                        <option value="S2" <?= $classFilter === 'S2' ? 'selected' : '' ?>>Senior Two (S2)</option>
                        <option value="S3" <?= $classFilter === 'S3' ? 'selected' : '' ?>>Senior Three (S3)</option>
                        <option value="S4" <?= $classFilter === 'S4' ? 'selected' : '' ?>>Senior Four (S4)</option>
                        <option value="S5" <?= $classFilter === 'S5' ? 'selected' : '' ?>>Senior Five (S5)</option>
                        <option value="S6" <?= $classFilter === 'S6' ? 'selected' : '' ?>>Senior Six (S6)</option>
                        <option value="OTHER" <?= $classFilter === 'OTHER' ? 'selected' : '' ?>>Transfers (S2, S3, S4, S6)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Gender</label>
                    <select name="sex" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-brand-700 bg-white shadow-sm cursor-pointer">
                        <option value="all" <?= $filterSex === 'all' ? 'selected' : '' ?>>All Genders</option>
                        <option value="Male" <?= $filterSex === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $filterSex === 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>

                <?php if ($isAdmin): ?>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Academic Year</label>
                    <select name="academic_year" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-brand-700 bg-white shadow-sm cursor-pointer">
                        <option value="all" <?= $filterYear === 'all' ? 'selected' : '' ?>>All Years (Archived & Current)</option>
                        <?php foreach ($availableYears as $ay): ?>
                            <option value="<?= htmlspecialchars($ay) ?>" <?= $filterYear === $ay ? 'selected' : '' ?>><?= htmlspecialchars($ay) ?> Academic Year</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Search Candidates</label>
                    <input type="text" name="q" id="liveSearchInput" value="<?= htmlspecialchars($searchQuery) ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-700 focus:outline-none focus:border-brand-700 shadow-sm" placeholder="Auto-search by name, index...">
                </div>

                <div class="flex gap-2 whitespace-nowrap flex-nowrap shrink-0">
                    <button type="submit" class="flex-1 px-3 py-2 bg-brand-700 hover:bg-brand-800 text-white rounded-xl text-xs font-bold shadow-sm transition duration-150 inline-flex items-center justify-center gap-1.5 whitespace-nowrap flex-nowrap shrink-0">
                        <i class="fa-solid fa-filter"></i> Filter
                    </button>
                    <?php if ($isAdmin): ?>
                    <a href="export_admitted_pdf.php?class=<?= urlencode($classFilter) ?>&sex=<?= urlencode($filterSex) ?>&academic_year=<?= urlencode($filterYear) ?>&q=<?= urlencode($searchQuery) ?>" target="_blank" class="flex-1 px-2.5 py-2 bg-rose-700 hover:bg-rose-800 text-white rounded-xl text-xs font-bold shadow-sm transition duration-150 inline-flex items-center justify-center gap-1 text-center whitespace-nowrap flex-nowrap shrink-0">
                        <i class="fa-solid fa-file-pdf"></i> PDF
                    </a>
                    <a href="admission_list.php?class=<?= urlencode($classFilter) ?>&sex=<?= urlencode($filterSex) ?>&academic_year=<?= urlencode($filterYear) ?>&q=<?= urlencode($searchQuery) ?>&export=csv" class="flex-1 px-2.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition duration-150 inline-flex items-center justify-center gap-1 text-center whitespace-nowrap flex-nowrap shrink-0">
                        <i class="fa-solid fa-file-csv"></i> CSV
                    </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Admitted Table Card -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col print:border-none print:shadow-none">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center no-print text-white" style="background-color: <?= $primary_color ?>;">
                    <div>
                        <h3 class="font-extrabold text-white text-sm uppercase tracking-wider">Official Admissions Register</h3>
                        <p class="text-xs text-white/80 mt-0.5">Showing 20 admitted students per page <?= !$isAdmin ? '· Active Cohort: ' . htmlspecialchars($currentAcademicYear) : ($filterYear !== 'all' ? '· Year: ' . htmlspecialchars($filterYear) : '· All Cohorts') ?></p>
                    </div>
                    <span class="text-xs font-bold text-white bg-white/20 border border-white/30 px-3 py-1.5 rounded-xl">Total Admitted: <?= $totalAdmitted ?></span>
                </div>

                <div class="w-full overflow-hidden">
                    <table class="w-full table-fixed text-left border-collapse text-xs" id="admissionTable">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px] print:bg-white print:border-b-2 print:border-black">
                                <th class="w-[4%] px-2 py-3 text-center">#</th>
                                <th class="w-[14%] px-2 py-3 text-center">Admission No.</th>
                                <th class="w-[30%] px-3 py-3">Student Full Name</th>
                                <th class="w-[8%] px-2 py-3 text-center">Gender</th>
                                <th class="w-[8%] px-2 py-3 text-center">Class</th>
                                <th class="w-[16%] px-2 py-3 text-center">Index Number</th>
                                <th class="w-[20%] px-3 py-3 text-center">Combination / Division</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs print:divide-y print:divide-slate-300">
                            <?php 
                            $idx = 1;
                            foreach ($students as $s): 
                                $serialNo = $offset + $idx;
                                $sClass = $s['class_of_entry'];
                                $acad = ($sClass === 'S1') ? ('PLE Div ' . ($s['division'] ?? 'N/A')) : (($sClass === 'S5' || $sClass === 'S6') ? (($s['combination'] ?? 'N/A') . ' (' . ($s['division'] ?? '') . ')') : ('Div ' . ($s['division'] ?? 'N/A')));
                            ?>
                            <tr class="hover:bg-slate-50 transition duration-150">
                                <td class="px-2 py-3 text-center font-bold text-slate-400 font-mono print:text-black"><?= $serialNo ?></td>
                                <td class="px-2 py-3 text-center font-mono font-bold text-slate-700 print:text-black">
                                    <span class="inline-flex items-center gap-1 group cursor-pointer hover:text-brand-700 transition" onclick="copyToClipboard('<?= htmlspecialchars(addslashes(getAdmissionNumber($s))) ?>', this)" title="Click to copy Student No">
                                        <span><?= htmlspecialchars(getAdmissionNumber($s)) ?></span>
                                        <i class="fa-regular fa-copy text-[8.5px] text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    </span>
                                </td>
                                <td class="px-3 py-3 font-bold text-slate-800 print:text-black leading-tight break-words">
                                    <?= htmlspecialchars(trim($s['surname'].' '.$s['firstname'].' '.($s['othernames'] ?? ''))) ?>
                                    <?php 
                                    $isAutoAdm = (!empty($s['is_auto_admitted']) && $s['is_auto_admitted'] == 1) || (stripos($s['admitted_by'] ?? '', 'AI') !== false) || (stripos($s['admitted_by'] ?? '', 'Auto') !== false);
                                    if ($isAutoAdm && ($s['auto_admit_review_status'] ?? 'needs_review') === 'needs_review'): ?>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded bg-amber-50 text-amber-800 border border-amber-300 text-[8.5px] font-bold ml-1.5 no-print shadow-2xs" title="Auto-Admitted by System: Requires Admissions Committee Review">
                                            <i class="fa-solid fa-robot text-amber-600"></i> Needs Review
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 py-3 text-center capitalize text-slate-600 print:text-black"><?= htmlspecialchars($s['sex']) ?></td>
                                <td class="px-2 py-3 text-center font-bold text-brand-700 print:text-black"><?= htmlspecialchars($sClass) ?></td>
                                <td class="px-2 py-3 text-center font-mono text-slate-600 print:text-black">
                                    <span class="inline-flex items-center gap-1 group cursor-pointer hover:text-brand-700 transition" onclick="copyToClipboard('<?= htmlspecialchars(addslashes($s['index_number'] ?? '')) ?>', this)" title="Click to copy Index No">
                                        <span><?= htmlspecialchars($s['index_number'] ?? 'N/A') ?></span>
                                        <i class="fa-regular fa-copy text-[8.5px] text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-center font-medium text-slate-700 print:text-black truncate" title="<?= htmlspecialchars($acad) ?>"><?= htmlspecialchars($acad) ?></td>
                            </tr>
                            <?php 
                            $idx++;
                            endforeach; 
                            ?>

                            <?php if (count($students) === 0): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-400 font-semibold print:text-black">
                                    <i class="fa-solid fa-folder-open text-3xl block mb-2 text-slate-300 print:hidden"></i>
                                    No admitted students registered matching the selected criteria.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 20 Rows Per Page Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between no-print">
                    <span class="text-xs text-slate-500">
                        Showing <span class="font-bold text-slate-700"><?= min($offset + 1, $totalAdmitted) ?></span> to <span class="font-bold text-slate-700"><?= min($offset + $perPage, $totalAdmitted) ?></span> of <span class="font-bold text-slate-700"><?= $totalAdmitted ?></span> students (20 per page)
                    </span>
                    <div class="flex items-center gap-1.5">
                        <?php if ($page > 1): ?>
                            <a href="?class=<?= urlencode($classFilter) ?>&sex=<?= urlencode($filterSex) ?>&q=<?= urlencode($searchQuery) ?>&page=<?= $page - 1 ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
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
                            <a href="?class=<?= urlencode($classFilter) ?>&sex=<?= urlencode($filterSex) ?>&q=<?= urlencode($searchQuery) ?>&page=<?= $page + 1 ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
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
    function filterTable() {
        const input = document.getElementById("searchInput").value.toLowerCase();
        const rows = document.querySelectorAll("#admissionTable tbody tr");

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(input) ? "" : "none";
        });
    }

    // Floating Back to Top Button
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

    // Auto-search Debouncer on filter input
    const searchInp = document.getElementById('liveSearchInput');
    if (searchInp) {
        let debounceTimer;
        searchInp.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                document.getElementById('admissionFilterForm')?.submit();
            }, 600);
        });
    }

    // Profile Dropdown Hover Support
    const profileContainer = document.getElementById('profileDropdownBtn')?.parentElement;
    if (profileContainer) {
        let hideTimer;
        profileContainer.addEventListener('mouseenter', function() {
            clearTimeout(hideTimer);
            document.getElementById('profileDropdown')?.classList.remove('hidden');
        });
        profileContainer.addEventListener('mouseleave', function() {
            hideTimer = setTimeout(() => {
                document.getElementById('profileDropdown')?.classList.add('hidden');
            }, 200);
        });
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
    </script>

    <!-- Floating Back-to-Top Button -->
    <button id="backToTopBtn" type="button" aria-label="Back to Top"
        class="fixed bottom-6 right-6 z-50 p-3.5 rounded-full text-white shadow-xl opacity-0 pointer-events-none transition-all duration-300 hover:scale-110 focus:outline-none"
        style="background-color: <?= $primary_color ?>;">
        <i class="fa-solid fa-arrow-up text-sm"></i>
    </button>
</body>
</html>
