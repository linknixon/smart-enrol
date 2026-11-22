<?php
session_start();
require 'auth.php';
require __DIR__ . '/../../form-five/config/db.php';

$school_short_name = getSetting($pdo, 'school_short_name', 'St. Paul\'s Mutolere SS');
$logo_source       = getSchoolLogoSrc($pdo, '../../');
$academic_year     = getAcademicYear($pdo);

// Secretary role cannot view activity logs
if ($_SESSION['admin_role'] === 'secretary') {
    header("Location: dashboard.php");
    exit;
}

// Fetch last 150 activity logs
$stmt = $pdo->query("SELECT * FROM admin_logs ORDER BY id DESC LIMIT 150");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Logs (S5) | <?= htmlspecialchars($school_short_name) ?></title>
<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com" data-cfasync="false"></script>
<!-- Font Awesome & Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= renderThemeConfig($pdo) ?>
</head>
<body class="bg-slate-100 font-sans text-slate-800 min-height-screen flex">

    <!-- Sidebar (Cruip dark style) -->
    <aside class="w-64 bg-slate-900 text-slate-100 flex flex-col shrink-0 min-h-screen">
        <!-- Brand Header -->
        <div class="px-6 py-5 border-b border-slate-800 flex items-center gap-3">
            <img src="<?= htmlspecialchars($logo_source) ?>" alt="Logo" class="w-10 h-10 bg-white rounded-full p-1 border-2 border-gold-500 object-contain">
            <div>
                <span class="block text-sm font-bold text-white tracking-tight leading-tight uppercase"><?= htmlspecialchars($school_short_name) ?></span>
                <span class="block text-xs font-semibold text-gold-500 uppercase tracking-widest">S5 Admin Portal</span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 px-4 py-6 space-y-1.5">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-chart-line text-lg"></i> Dashboard (S5)
            </a>
            <a href="s5_admission_list.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-graduation-cap text-lg"></i> Admitted List (S5)
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-file-invoice text-lg text-gold-500"></i> Custom Reports
            </a>
            <a href="users.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-users text-lg"></i> User Management
            </a>
            <a href="logs.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-brand-700 text-white transition duration-150">
                <i class="fa-solid fa-list-check text-lg"></i> Activity Logs
            </a>
            <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="../../admin/settings.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-gear text-lg text-gold-500"></i> System Settings
            </a>
            <?php endif; ?>
            <div class="pt-6 border-t border-slate-800 my-4"></div>
            <a href="../../form-one/admin/dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition duration-150">
                <i class="fa-solid fa-right-left text-lg text-gold-500"></i> Go to S1 Portal
            </a>
        </nav>

        <!-- Footer Note -->
        <div class="px-6 py-4 border-t border-slate-800 text-xs text-slate-500 text-center">
            Admissions Office · <?= htmlspecialchars($academic_year) ?>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        
        <!-- Header -->
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex justify-between items-center shadow-sm relative z-10">
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">System Audit & Activity Logs</h1>

            <!-- User Profile Dropdown -->
            <div class="relative">
                <button id="profileDropdownBtn" onclick="toggleDropdown()" class="flex items-center gap-2 focus:outline-none py-1.5 px-3 rounded-full hover:bg-slate-100 transition duration-150">
                    <span class="text-sm font-bold text-slate-700"><?= htmlspecialchars($_SESSION['admin_username']) ?></span>
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-brand-700 text-white font-bold text-xs uppercase">
                        <?= substr($_SESSION['admin_username'], 0, 2) ?>
                    </span>
                    <i class="fa-solid fa-chevron-down text-slate-400 text-xs"></i>
                </button>

                <!-- Dropdown Card -->
                <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-xl shadow-lg py-2 z-20">
                    <div class="px-4 py-2 border-b border-slate-100">
                        <span class="block text-xs font-bold text-slate-400 uppercase">Role</span>
                        <span class="block text-sm font-semibold text-slate-700 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $_SESSION['admin_role'])) ?></span>
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
        <div class="flex-1 px-8 py-6 space-y-6">

            <!-- Main Logs Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">System Audit Trail (Last 150 Events)</h3>
                </div>

                <!-- Table Wrapper -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                <th class="px-6 py-3.5 text-center">ID</th>
                                <th class="px-6 py-3.5">Timestamp</th>
                                <th class="px-6 py-3.5">Username</th>
                                <th class="px-6 py-3.5">Action / Operation</th>
                                <th class="px-6 py-3.5">Details</th>
                                <th class="px-6 py-3.5 text-center">IP Address</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php foreach ($logs as $l): ?>
                            <tr class="hover:bg-slate-50 transition duration-150">
                                <td class="px-6 py-4 text-center font-bold text-slate-400"><?= $l['id'] ?></td>
                                <td class="px-6 py-4 font-semibold text-slate-500 whitespace-nowrap"><?= date('d-m-Y H:i:s', strtotime($l['created_at'])) ?></td>
                                <td class="px-6 py-4 font-bold text-brand-700"><?= htmlspecialchars($l['username']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 text-xs font-bold rounded bg-slate-100 text-slate-800 border border-slate-200 capitalize">
                                        <?= htmlspecialchars($l['action']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-600 font-medium"><?= htmlspecialchars($l['details']) ?></td>
                                <td class="px-6 py-4 text-center font-mono text-xs text-slate-400 font-semibold"><?= htmlspecialchars($l['ip_address'] ?: 'N/A') ?></td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (count($logs) === 0): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-400 font-semibold">
                                    <i class="fa-solid fa-list-check text-3xl block mb-2 text-slate-300"></i>
                                    No audit logs available.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </main>

    <!-- Vanilla Javascript Profile Toggler -->
    <script>
    function toggleDropdown() {
        const drop = document.getElementById('profileDropdown');
        drop.classList.toggle('hidden');
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
