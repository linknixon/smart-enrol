<?php
require_once __DIR__ . '/auth.php';

$school_short_name  = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$logo_source        = getSchoolLogoSrc($pdo, '../');
$school_website_url = getSchoolWebsiteUrl($pdo);
$adminFirstName     = getAdminDisplayName($pdo, $_SESSION['admin_username'] ?? '');
$currentRole        = $_SESSION['admin_role'] ?? '';

if (!hasPermission($pdo, $currentRole, 'can_view_logs')) {
    header("Location: dashboard.php?error=unauthorized");
    exit;
}

// Pagination & 90 Days Constraint
$perPage = 50;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$isSuperAdmin = (($currentRole ?? '') === 'super_admin');

// Super admin sees all logs; other roles do NOT see any super_admin activity
if ($isSuperAdmin) {
    $countSql = "SELECT COUNT(*) FROM admin_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
    $fetchSql = "SELECT * FROM admin_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) ORDER BY id DESC LIMIT ? OFFSET ?";
} else {
    $countSql = "SELECT COUNT(*) FROM admin_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) AND username NOT IN (SELECT username FROM admin_users WHERE role = 'super_admin') AND details NOT LIKE '%super_admin%' AND action NOT LIKE '%super_admin%'";
    $fetchSql = "SELECT * FROM admin_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) AND username NOT IN (SELECT username FROM admin_users WHERE role = 'super_admin') AND details NOT LIKE '%super_admin%' AND action NOT LIKE '%super_admin%' ORDER BY id DESC LIMIT ? OFFSET ?";
}

$countStmt = $pdo->query($countSql);
$totalLogs = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalLogs / $perPage));
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch logs
$stmt = $pdo->prepare($fetchSql);
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Logs | <?= htmlspecialchars($school_short_name) ?></title>
<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com" data-cfasync="false"></script>
<!-- Font Awesome & Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com">
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
                <img src="<?= htmlspecialchars($logo_source) ?>" alt="Logo" class="w-9 h-9 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
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
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
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
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
                <i class="fa-solid fa-clock-rotate-left text-lg"></i> Activity Logs
            </a>
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

    <!-- Desktop Sidebar -->
    <aside class="hidden md:flex w-64 bg-slate-900 text-slate-100 flex flex-col shrink-0 min-h-screen">
        <div class="px-6 py-5 border-b border-slate-800 flex items-center gap-3">
            <img src="<?= htmlspecialchars($logo_source) ?>" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
            <div>
                <span class="block text-sm font-bold text-white tracking-tight leading-tight uppercase"><?= htmlspecialchars($school_short_name) ?></span>
                <span class="block text-xs font-semibold text-gold-500 uppercase tracking-widest text-[9px]">Consolidated Portal</span>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
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
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
                <i class="fa-solid fa-clock-rotate-left text-lg"></i> Activity Logs
            </a>
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

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <header class="bg-white border-b border-slate-200 px-4 sm:px-6 md:px-8 py-3.5 flex justify-between items-center shadow-sm relative z-10">
            <div class="flex items-center gap-3">
                <button type="button" onclick="toggleMobileSidebar()" class="md:hidden p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl focus:outline-none" aria-label="Toggle Navigation">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h1 class="text-base sm:text-xl font-bold text-slate-800 tracking-tight">System Activity Logs</h1>
                    <p class="text-xs text-slate-400 mt-0.5 hidden sm:block">Real-time audit trail of portal operations, admissions decisions, and submission tracking for the last 90 days.</p>
                </div>
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

        <div class="flex-1 px-8 py-6 space-y-6">

            <!-- Logs Registry Table Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <div>
                        <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">
                            <i class="fa-solid fa-clock-rotate-left text-brand-700 me-1.5"></i> Activity & Admissions Logs (Last 90 Days)
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">Captures system audits, admissions changes, and application submission failure reasons.</p>
                    </div>
                    <span class="text-xs font-bold text-brand-700 bg-brand-50 border border-brand-200 px-3 py-1 rounded-full">
                        Total 90-Day Logs: <?= number_format($totalLogs) ?>
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                <th class="px-4 py-3 text-center">ID</th>
                                <th class="px-4 py-3">User / Actor</th>
                                <th class="px-4 py-3">Operation / Event</th>
                                <th class="px-4 py-3">Details / Reason</th>
                                <th class="px-4 py-3 text-center">IP Address</th>
                                <th class="px-4 py-3 text-center">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php foreach ($logs as $l): 
                                $isBlock = (strpos($l['action'], 'Submission Blocked') !== false);
                                $isSubmission = (strpos($l['action'], 'Application Submitted') !== false);
                            ?>
                            <tr class="hover:bg-slate-50 transition duration-150 <?= $isBlock ? 'bg-rose-50/40' : '' ?>">
                                <td class="px-4 py-3 text-center font-bold text-slate-400 font-mono text-xs"><?= $l['id'] ?></td>
                                <td class="px-4 py-3 font-bold text-slate-700 font-mono text-xs">
                                    <?php if ($l['username'] === 'System' || $l['username'] === 'Applicant Portal'): ?>
                                        <span class="inline-flex items-center gap-1 text-slate-500"><i class="fa-solid fa-server text-[10px]"></i> <?= htmlspecialchars($l['username']) ?></span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-brand-800"><i class="fa-solid fa-user-shield text-[10px] text-brand-600"></i> <?= htmlspecialchars($l['username']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($isBlock): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                            <i class="fa-solid fa-circle-exclamation text-[10px]"></i> <?= htmlspecialchars($l['action']) ?>
                                        </span>
                                    <?php elseif ($isSubmission): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            <i class="fa-solid fa-circle-check text-[10px]"></i> <?= htmlspecialchars($l['action']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="font-semibold text-slate-700 text-xs uppercase tracking-wider"><?= htmlspecialchars($l['action']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 font-medium text-xs max-w-md break-words"><?= htmlspecialchars($l['details']) ?></td>
                                <td class="px-4 py-3 text-center font-mono text-xs text-slate-400"><?= htmlspecialchars($l['ip_address']) ?></td>
                                <td class="px-4 py-3 text-center font-mono text-xs text-slate-500 whitespace-nowrap"><?= htmlspecialchars($l['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (count($logs) === 0): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-400 font-semibold">
                                    <i class="fa-solid fa-folder-open text-3xl block mb-2 text-slate-300"></i>
                                    No activity logs registered in the last 90 days.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 50 Rows Per Page Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
                    <span class="text-xs text-slate-500">
                        Showing <span class="font-bold text-slate-700"><?= min($offset + 1, $totalLogs) ?></span> to <span class="font-bold text-slate-700"><?= min($offset + $perPage, $totalLogs) ?></span> of <span class="font-bold text-slate-700"><?= $totalLogs ?></span> operations (50 per page)
                    </span>
                    <div class="flex items-center gap-1.5">
                        <?php if ($page > 1): ?>
                            <a href="logs.php?page=<?= $page - 1 ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
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
                            <a href="logs.php?page=<?= $page + 1 ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
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
    </script>
</body>
</html>
